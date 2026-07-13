<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Keeps the locked personal payments of an open shared payment in sync.
 *
 * Callers are responsible for wrapping mutating methods in the same database
 * transaction as the shared source payment.
 */
final class EntryProjectionService {
	private const SOURCE_KIND = 'shared';
	private const PERSONAL_KIND = 'personal';

	public function __construct(
		private IDBConnection $db,
		private EntryShareService $entryShareService,
		private HashtagService $hashtagService,
		private EntryAttachmentProjectionService $attachmentProjectionService,
	) {
	}

	/** @return array<string, int> user id => personal entry id */
	public function syncSharedEntry(int $sourceEntryId): array {
		$source = $this->sourceEntry($sourceEntryId);
		$this->assertOpenSharedSource($source);

		$this->entryShareService->syncEntry($sourceEntryId);
		$shareRows = $this->entryShareService->sharesForEntries([$sourceEntryId])[$sourceEntryId] ?? [];

		return $this->materializeStoredShares($source, $shareRows);
	}

	/**
	 * Restores missing locked projections without recalculating an existing
	 * immutable share snapshot. This is used before a Nextcloud account is
	 * anonymized so every positive allocation remains represented by a row.
	 *
	 * @return array<string, int> user id => personal entry id
	 */
	public function ensureOpenSharedEntry(int $sourceEntryId): array {
		$source = $this->sourceEntry($sourceEntryId);
		$this->assertOpenSharedSource($source);

		$shareRows = $this->entryShareService->sharesForEntries([$sourceEntryId])[$sourceEntryId] ?? [];
		if ($shareRows === []) {
			$this->entryShareService->syncEntry($sourceEntryId);
			$shareRows = $this->entryShareService->sharesForEntries([$sourceEntryId])[$sourceEntryId] ?? [];
		}

		return $this->materializeStoredShares($source, $shareRows);
	}

	/** @param array<string, array{share_basis_points: int, amount_cents: int, personal_entry_id: ?int}> $shareRows */
	private function materializeStoredShares(array $source, array $shareRows): array {
		$sourceEntryId = (int)$source['id'];
		$wantedUserIds = [];
		$personalEntryIds = [];

		foreach ($shareRows as $userId => $allocation) {
			$amountCents = max(0, (int)($allocation['amount_cents'] ?? 0));
			if ($amountCents <= 0) {
				$this->clearShareProjection($sourceEntryId, $userId);
				continue;
			}

			$workspaceId = $this->personalWorkspaceId((int)$source['project_id'], $userId);
			$existing = $this->personalEntry($sourceEntryId, $userId);
			if ($existing !== null && empty($existing['is_locked'])) {
				throw new \RuntimeException('An unlocked personal payment cannot be overwritten by its shared source.');
			}

			$personalEntryId = $existing === null
				? $this->insertPersonalEntry($source, $userId, $workspaceId, $amountCents, (int)$allocation['share_basis_points'])
				: $this->updatePersonalEntry((int)$existing['id'], $source, $userId, $workspaceId, $amountCents, (int)$allocation['share_basis_points']);

			$this->hashtagService->syncEntryHashtags($personalEntryId, $workspaceId, (string)($source['description'] ?? ''));
			$this->setShareProjection($sourceEntryId, $userId, $personalEntryId);
			$wantedUserIds[$userId] = true;
			$personalEntryIds[$userId] = $personalEntryId;
		}

		$this->deleteStaleLockedProjections($sourceEntryId, array_keys($wantedUserIds));
		$this->attachmentProjectionService->syncSharedEntry($sourceEntryId);

		return $personalEntryIds;
	}

	private function assertOpenSharedSource(?array $source): void {
		if ($source === null || (string)($source['entry_kind'] ?? '') !== self::SOURCE_KIND) {
			throw new \RuntimeException('Shared payment not found while creating personal payments.');
		}
		if (!empty($source['is_settled'])) {
			throw new \RuntimeException('Settled shared payments cannot be projected again.');
		}
	}

	public function unlockForSettlement(array $sourceEntryIds, int $settlementId, int $settledAt): void {
		$sourceEntryIds = $this->positiveIds($sourceEntryIds);
		if ($sourceEntryIds === []) {
			return;
		}

		$personalEntryIds = [];
		foreach ($sourceEntryIds as $sourceEntryId) {
			$personalEntryIds = array_merge($personalEntryIds, $this->personalEntryIds($sourceEntryId));
		}

		foreach (array_chunk($sourceEntryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_entries')
				->set('is_locked', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
				->set('source_entry_id', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->set('allocation_basis_points', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->set('settlement_id', $qb->createNamedParameter($settlementId, \PDO::PARAM_INT))
				->set('settled_at', $qb->createNamedParameter($settledAt, \PDO::PARAM_INT))
				->where($qb->expr()->in('source_entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)))
				->andWhere($qb->expr()->eq('is_locked', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$qb->executeStatement();

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_entry_shares')
				->set('personal_entry_id', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}

		foreach (array_chunk($this->positiveIds($personalEntryIds), 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_entry_attachments')
				->set('source_attachment_id', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}
	}

	public function personalWorkspaceIdForMember(int $projectId, string $userId): int {
		return $this->personalWorkspaceId($projectId, $userId);
	}

	/**
	 * Detaches a former area member's already-settled personal payments.
	 * Area-only lookups are cloned into the member's personal base workspace.
	 */
	/** @return array{entries: int, attachments: int} */
	public function detachSettledMember(int $projectId, string $userId): array {
		$workspaceId = $this->personalWorkspaceId($projectId, $userId);
		$entries = $this->settledPersonalEntriesForMember($projectId, $userId);
		$categoryMap = [];
		$partnerMap = [];
		$entryIds = [];

		foreach ($entries as $entry) {
			$entryIds[] = (int)$entry['id'];
			$categoryId = empty($entry['category_id']) ? null : (int)$entry['category_id'];
			$partnerId = empty($entry['payment_partner_id']) ? null : (int)$entry['payment_partner_id'];
			if ($categoryId !== null && !array_key_exists($categoryId, $categoryMap)) {
				$categoryMap[$categoryId] = $this->personalCategoryId($categoryId, $projectId, $userId, $workspaceId);
			}
			if ($partnerId !== null && !array_key_exists($partnerId, $partnerMap)) {
				$partnerMap[$partnerId] = $this->personalPaymentPartnerId($partnerId, $projectId, $userId, $workspaceId);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_entries')
				->set('project_id', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->set('source_entry_id', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->set('allocation_basis_points', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->set('settlement_id', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->set('category_id', $qb->createNamedParameter($categoryId === null ? null : ($categoryMap[$categoryId] ?? $categoryId), $categoryId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->set('payment_partner_id', $qb->createNamedParameter($partnerId === null ? null : ($partnerMap[$partnerId] ?? $partnerId), $partnerId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$entry['id'], \PDO::PARAM_INT)))
				->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)))
				->andWhere($qb->expr()->eq('is_locked', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
			$qb->executeStatement();
		}

		return [
			'entries' => count($entryIds),
			'attachments' => $this->attachmentCountForEntries($entryIds),
		];
	}

	public function deleteOpenProjections(int $sourceEntryId): void {
		$ids = $this->lockedProjectionIds($sourceEntryId);
		$this->deletePersonalEntries($ids);
	}

	/**
	 * Expands an entry deletion to the complete projection graph and detaches
	 * reverse share references. A locked personal projection may never be
	 * deleted without its shared source.
	 *
	 * Callers must run the returned deletion and all related metadata cleanup
	 * in the same transaction.
	 *
	 * @return int[]
	 */
	public function prepareEntryDeletion(array $entryIds): array {
		$entryIds = $this->positiveIds($entryIds);
		if ($entryIds === []) {
			return [];
		}

		$selectedRows = $this->entryRowsByIds($entryIds);
		$sourceIds = [];
		foreach ($selectedRows as $row) {
			if ((string)($row['entry_kind'] ?? '') === self::SOURCE_KIND) {
				$sourceIds[] = (int)$row['id'];
			}
		}
		$sourceIds = $this->positiveIds($sourceIds);

		if ($sourceIds !== []) {
			foreach (array_chunk($sourceIds, 500) as $chunk) {
				$qb = $this->db->getQueryBuilder();
				$qb->select('id')
					->from('cobudget_entries')
					->where($qb->expr()->in('source_entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
					->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)));
				$result = $qb->executeQuery();
				$entryIds = array_merge($entryIds, array_map('intval', array_column($result->fetchAll(), 'id')));
				$result->closeCursor();
			}
		}
		$entryIds = $this->positiveIds($entryIds);
		$sourceMap = array_fill_keys($sourceIds, true);

		foreach ($this->entryRowsByIds($entryIds) as $row) {
			if (
				(string)($row['entry_kind'] ?? '') === self::PERSONAL_KIND
				&& $this->boolValue($row['is_locked'] ?? false)
			) {
				$sourceEntryId = isset($row['source_entry_id']) ? (int)$row['source_entry_id'] : 0;
				if ($sourceEntryId <= 0 || !isset($sourceMap[$sourceEntryId])) {
					throw new \RuntimeException('Eine offene persoenliche Projektion kann nicht ohne ihre gemeinsame Quellzahlung geloescht werden.');
				}
			}
		}

		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_entry_shares')
				->set('personal_entry_id', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
				->where($qb->expr()->in('personal_entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}

		return $entryIds;
	}

	public function sourceEntryIdForPersonal(int $personalEntryId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('source_entry_id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($personalEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)))
			->setMaxResults(1);
		$value = $qb->executeQuery()->fetchOne();

		return $value === false || $value === null ? null : (int)$value;
	}

	/** @return int[] */
	public function personalEntryIds(int $sourceEntryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('source_entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)));
		$result = $qb->executeQuery();
		$ids = array_map('intval', array_column($result->fetchAll(), 'id'));
		$result->closeCursor();

		return $ids;
	}

	/** @return array<string, array<string, mixed>> user id => personal entry */
	public function personalEntriesForSource(int $sourceEntryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_entries')
			->where($qb->expr()->eq('source_entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$entries = [];
		foreach ($result->fetchAll() as $entry) {
			$userId = trim((string)($entry['user_id'] ?? ''));
			if ($userId !== '') {
				$entries[$userId] = $entry;
			}
		}
		$result->closeCursor();

		return $entries;
	}

	private function sourceEntry(int $entryId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_entries')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ?: null;
	}

	/** @return array<int, array<string, mixed>> */
	private function entryRowsByIds(array $entryIds): array {
		$rows = [];
		foreach (array_chunk($this->positiveIds($entryIds), 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'entry_kind', 'source_entry_id', 'is_locked')
				->from('cobudget_entries')
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$rows[(int)$row['id']] = $row;
			}
			$result->closeCursor();
		}

		return $rows;
	}

	private function personalEntry(int $sourceEntryId, string $userId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'is_locked')
			->from('cobudget_entries')
			->where($qb->expr()->eq('source_entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ?: null;
	}

	private function insertPersonalEntry(array $source, string $userId, int $workspaceId, int $amountCents, int $basisPoints): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('cobudget_entries')->values($this->personalEntryValues($qb, $source, $userId, $workspaceId, $amountCents, $basisPoints));
		$qb->executeStatement();

		return (int)$this->db->lastInsertId('*PREFIX*cobudget_entries');
	}

	private function updatePersonalEntry(int $entryId, array $source, string $userId, int $workspaceId, int $amountCents, int $basisPoints): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_entries');
		foreach ($this->personalEntryValues($qb, $source, $userId, $workspaceId, $amountCents, $basisPoints) as $column => $value) {
			$qb->set($column, $value);
		}
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_locked', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
		if ($qb->executeStatement() !== 1) {
			throw new \RuntimeException('Locked personal payment could not be updated.');
		}

		return $entryId;
	}

	private function personalEntryValues($qb, array $source, string $userId, int $workspaceId, int $amountCents, int $basisPoints): array {
		$nullableInt = static fn($value) => $qb->createNamedParameter($value, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
		$nullableString = static fn($value) => $qb->createNamedParameter($value, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);

		return [
			'user_id' => $qb->createNamedParameter($userId),
			'created_by' => $qb->createNamedParameter((string)($source['created_by'] ?? $source['user_id'] ?? $userId)),
			'project_id' => $qb->createNamedParameter((int)$source['project_id'], \PDO::PARAM_INT),
			'entry_kind' => $qb->createNamedParameter(self::PERSONAL_KIND),
			'source_entry_id' => $qb->createNamedParameter((int)$source['id'], \PDO::PARAM_INT),
			'is_locked' => $qb->createNamedParameter(true, \PDO::PARAM_BOOL),
			'allocation_basis_points' => $qb->createNamedParameter($basisPoints, \PDO::PARAM_INT),
			'type' => $qb->createNamedParameter((string)$source['type']),
			'amount' => $qb->createNamedParameter(number_format($amountCents / 100, 2, '.', '')),
			'amount_cents' => $qb->createNamedParameter($amountCents, \PDO::PARAM_INT),
			'currency' => $qb->createNamedParameter((string)$source['currency']),
			'date' => $qb->createNamedParameter((int)$source['date'], \PDO::PARAM_INT),
			'category_id' => $nullableInt(empty($source['category_id']) ? null : (int)$source['category_id']),
			'payment_partner_id' => $nullableInt(empty($source['payment_partner_id']) ? null : (int)$source['payment_partner_id']),
			'description' => $qb->createNamedParameter((string)($source['description'] ?? '')),
			'split_mode' => $qb->createNamedParameter('single_user'),
			'split_user_id' => $qb->createNamedParameter($userId),
			'is_settled' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
			'settled_at' => $nullableInt(null),
			'settlement_id' => $nullableInt(null),
			'recurrence_interval' => $nullableString(null),
			'recurrence_multiplier' => $nullableInt(null),
			'recurrence_next_date' => $nullableInt(null),
			'recurrence_end_date' => $nullableInt(null),
			'recurrence_parent_id' => $nullableInt(null),
			'recurrence_series_id' => $nullableInt(null),
			'is_subscription' => $qb->createNamedParameter($this->boolValue($source['is_subscription'] ?? false), \PDO::PARAM_BOOL),
			'is_fixed_cost' => $qb->createNamedParameter($this->boolValue($source['is_fixed_cost'] ?? false), \PDO::PARAM_BOOL),
			'is_child_related' => $qb->createNamedParameter($this->boolValue($source['is_child_related'] ?? false), \PDO::PARAM_BOOL),
			'is_important' => $qb->createNamedParameter($this->boolValue($source['is_important'] ?? false), \PDO::PARAM_BOOL),
			'needs_review' => $qb->createNamedParameter($this->boolValue($source['needs_review'] ?? false), \PDO::PARAM_BOOL),
			'is_tax_relevant' => $qb->createNamedParameter($this->boolValue($source['is_tax_relevant'] ?? false), \PDO::PARAM_BOOL),
			'reminder_date' => $nullableInt(null),
			'reminder_notified' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
			'reminder_text' => $nullableString(null),
			'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
		];
	}

	private function personalWorkspaceId(int $projectId, string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('personal_workspace_id')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);
		$workspaceId = $qb->executeQuery()->fetchOne();
		if ($workspaceId !== false && $workspaceId !== null && $this->workspaceBelongsToUser((int)$workspaceId, $userId)) {
			return (int)$workspaceId;
		}

		$workspaceId = $this->defaultWorkspaceId($userId);
		$update = $this->db->getQueryBuilder();
		$update->update('cobudget_members')
			->set('personal_workspace_id', $update->createNamedParameter($workspaceId, \PDO::PARAM_INT))
			->where($update->expr()->eq('project_id', $update->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($update->expr()->eq('user_id', $update->createNamedParameter($userId)));
		$update->executeStatement();

		return $workspaceId;
	}

	/** @return array<int, array<string, mixed>> */
	private function settledPersonalEntriesForMember(int $projectId, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'category_id', 'payment_partner_id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)))
			->andWhere($qb->expr()->eq('is_locked', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	private function attachmentCountForEntries(array $entryIds): int {
		$entryIds = $this->positiveIds($entryIds);
		if ($entryIds === []) {
			return 0;
		}

		$count = 0;
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->selectAlias($qb->func()->count('*'), 'attachment_count')
				->from('cobudget_entry_attachments')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$count += (int)$qb->executeQuery()->fetchOne();
		}

		return $count;
	}

	private function personalCategoryId(int $categoryId, int $projectId, string $userId, int $workspaceId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_categories')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($categoryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();
		if (!$row) {
			return $categoryId;
		}

		$existing = $this->personalLookupId('cobudget_categories', (string)$row['name'], (string)$row['type'], $userId, $workspaceId);
		if ($existing !== null) {
			return $existing;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('cobudget_categories')->values([
			'name' => $insert->createNamedParameter((string)$row['name']),
			'is_global' => $insert->createNamedParameter(false, \PDO::PARAM_BOOL),
			'user_id' => $insert->createNamedParameter($userId),
			'workspace_id' => $insert->createNamedParameter($workspaceId, \PDO::PARAM_INT),
			'icon' => $insert->createNamedParameter((string)($row['icon'] ?? 'Shape')),
			'type' => $insert->createNamedParameter((string)$row['type']),
			'project_id' => $insert->createNamedParameter(null, \PDO::PARAM_NULL),
			'is_hidden' => $insert->createNamedParameter(false, \PDO::PARAM_BOOL),
		]);
		$insert->executeStatement();

		return (int)$this->db->lastInsertId('*PREFIX*cobudget_categories');
	}

	private function personalPaymentPartnerId(int $partnerId, int $projectId, string $userId, int $workspaceId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_payment_partners')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($partnerId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();
		if (!$row) {
			return $partnerId;
		}

		$existing = $this->personalLookupId('cobudget_payment_partners', (string)$row['name'], (string)$row['type'], $userId, $workspaceId);
		if ($existing !== null) {
			return $existing;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('cobudget_payment_partners')->values([
			'name' => $insert->createNamedParameter((string)$row['name']),
			'is_global' => $insert->createNamedParameter(false, \PDO::PARAM_BOOL),
			'user_id' => $insert->createNamedParameter($userId),
			'workspace_id' => $insert->createNamedParameter($workspaceId, \PDO::PARAM_INT),
			'type' => $insert->createNamedParameter((string)$row['type']),
			'project_id' => $insert->createNamedParameter(null, \PDO::PARAM_NULL),
			'is_hidden' => $insert->createNamedParameter(false, \PDO::PARAM_BOOL),
		]);
		$insert->executeStatement();

		return (int)$this->db->lastInsertId('*PREFIX*cobudget_payment_partners');
	}

	private function personalLookupId(string $table, string $name, string $type, string $userId, int $workspaceId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($table)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->isNull('project_id'))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($type)))
			->andWhere($qb->expr()->eq($qb->createFunction('LOWER(name)'), $qb->createNamedParameter(mb_strtolower(trim($name)))))
			->setMaxResults(1);
		$value = $qb->executeQuery()->fetchOne();

		return $value === false ? null : (int)$value;
	}

	private function workspaceBelongsToUser(int $workspaceId, string $userId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_workspaces')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);

		return $qb->executeQuery()->fetchOne() !== false;
	}

	private function defaultWorkspaceId(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_workspaces')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('is_default', 'DESC')
			->addOrderBy('id', 'ASC')
			->setMaxResults(1);
		$workspaceId = $qb->executeQuery()->fetchOne();
		if ($workspaceId !== false) {
			return (int)$workspaceId;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('cobudget_workspaces')
			->values([
				'name' => $insert->createNamedParameter('Basis'),
				'user_id' => $insert->createNamedParameter($userId),
				'is_default' => $insert->createNamedParameter(true, \PDO::PARAM_BOOL),
				'created_at' => $insert->createNamedParameter(time(), \PDO::PARAM_INT),
			]);
		$insert->executeStatement();

		return (int)$this->db->lastInsertId('*PREFIX*cobudget_workspaces');
	}

	private function setShareProjection(int $sourceEntryId, string $userId, int $personalEntryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_entry_shares')
			->set('personal_entry_id', $qb->createNamedParameter($personalEntryId, \PDO::PARAM_INT))
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	private function clearShareProjection(int $sourceEntryId, string $userId): void {
		$existing = $this->personalEntry($sourceEntryId, $userId);
		if ($existing !== null && !empty($existing['is_locked'])) {
			$this->deletePersonalEntries([(int)$existing['id']]);
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_entry_shares')
			->set('personal_entry_id', $qb->createNamedParameter(null, \PDO::PARAM_NULL))
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	private function deleteStaleLockedProjections(int $sourceEntryId, array $wantedUserIds): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('source_entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)))
			->andWhere($qb->expr()->eq('is_locked', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
		$result = $qb->executeQuery();
		$staleIds = [];
		$wanted = array_fill_keys($wantedUserIds, true);
		foreach ($result->fetchAll() as $row) {
			if (!isset($wanted[(string)$row['user_id']])) {
				$staleIds[] = (int)$row['id'];
			}
		}
		$result->closeCursor();
		$this->deletePersonalEntries($staleIds);
	}

	/** @return int[] */
	private function lockedProjectionIds(int $sourceEntryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('source_entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter(self::PERSONAL_KIND)))
			->andWhere($qb->expr()->eq('is_locked', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
		$result = $qb->executeQuery();
		$ids = array_map('intval', array_column($result->fetchAll(), 'id'));
		$result->closeCursor();

		return $ids;
	}

	private function deletePersonalEntries(array $entryIds): void {
		$entryIds = $this->positiveIds($entryIds);
		if ($entryIds === []) {
			return;
		}

		$this->hashtagService->deleteHashtagsForEntries($entryIds);
		$this->attachmentProjectionService->deleteAttachmentsForEntries($entryIds);
		foreach (['cobudget_entry_history'] as $table) {
			foreach (array_chunk($entryIds, 500) as $chunk) {
				$delete = $this->db->getQueryBuilder();
				$delete->delete($table)
					->where($delete->expr()->in('entry_id', $delete->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
				$delete->executeStatement();
			}
		}
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$clear = $this->db->getQueryBuilder();
			$clear->update('cobudget_entry_shares')
				->set('personal_entry_id', $clear->createNamedParameter(null, \PDO::PARAM_NULL))
				->where($clear->expr()->in('personal_entry_id', $clear->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$clear->executeStatement();

			$delete = $this->db->getQueryBuilder();
			$delete->delete('cobudget_entries')
				->where($delete->expr()->in('id', $delete->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($delete->expr()->eq('entry_kind', $delete->createNamedParameter(self::PERSONAL_KIND)))
				->andWhere($delete->expr()->eq('is_locked', $delete->createNamedParameter(true, \PDO::PARAM_BOOL)));
			$delete->executeStatement();
		}
	}

	private function boolValue(mixed $value): bool {
		return $value === true || $value === 1 || $value === '1';
	}

	/** @return int[] */
	private function positiveIds(array $ids): array {
		return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
	}
}
