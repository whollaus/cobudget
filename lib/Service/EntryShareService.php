<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final class EntryShareService {

	public function __construct(private IDBConnection $db) {
	}

	public function syncEntry(int $entryId): void {
		$entry = $this->entryRow($entryId);
		if ($entry === null) {
			throw new \RuntimeException('Payment not found while storing its share snapshot.');
		}

		$projectId = isset($entry['project_id']) ? (int)$entry['project_id'] : 0;
		$oldShareRows = $this->shareRowsForEntry($entryId);
		$personalEntryIds = $this->personalEntryIdsFromRows($oldShareRows);
		if ($projectId > 0) {
			$this->lockProject($projectId);
			$this->adjustRoundingBalances($projectId, $oldShareRows, -1);
		}
		$this->deleteShareRows($entryId);
		if ($projectId <= 0 || (string)($entry['entry_kind'] ?? 'personal') !== 'shared') {
			return;
		}

		$roundingBucket = $this->roundingBucket((string)($entry['type'] ?? 'expense'));
		$allocationState = $this->projectMemberAllocationState($projectId, $roundingBucket);
		$allocations = EntryShareCalculator::calculate(
			$this->amountCents($entry),
			(string)($entry['split_mode'] ?? 'project_shares'),
			isset($entry['split_user_id']) ? (string)$entry['split_user_id'] : null,
			(string)($entry['user_id'] ?? ''),
			$allocationState['shares'],
			$allocationState['rounding_balances'],
		);

		$newShareRows = [];
		foreach ($allocations as $userId => $allocation) {
			$roundingResidualUnits = ($this->amountCents($entry) * $allocation['share_basis_points'])
				- ($allocation['amount_cents'] * 10000);
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_entry_shares')
				->values([
					'entry_id' => $qb->createNamedParameter($entryId, \PDO::PARAM_INT),
					'user_id' => $qb->createNamedParameter($userId),
					'share_basis_points' => $qb->createNamedParameter($allocation['share_basis_points'], \PDO::PARAM_INT),
					'amount_cents' => $qb->createNamedParameter($allocation['amount_cents'], \PDO::PARAM_INT),
					'personal_entry_id' => $qb->createNamedParameter($personalEntryIds[$userId] ?? null, isset($personalEntryIds[$userId]) ? \PDO::PARAM_INT : \PDO::PARAM_NULL),
					'rounding_bucket' => $qb->createNamedParameter($roundingBucket),
					'rounding_residual_units' => $qb->createNamedParameter($roundingResidualUnits, \PDO::PARAM_INT),
				]);
			$qb->executeStatement();
			$newShareRows[] = [
				'user_id' => $userId,
				'rounding_bucket' => $roundingBucket,
				'rounding_residual_units' => $roundingResidualUnits,
			];
		}
		$this->adjustRoundingBalances($projectId, $newShareRows, 1);
	}

	public function hasShares(int $entryId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('entry_id')
			->from('cobudget_entry_shares')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	/**
	 * Adds snapshot_share_cents to shared entries that have a stored allocation.
	 * Personal entries deliberately have no share rows and retain their owner-based fallback.
	 */
	public function attachPersonalShares(array $entries, string $userId): array {
		$entryIds = [];
		foreach ($entries as $entry) {
			$entryId = (int)($entry['id'] ?? 0);
			if ($entryId > 0 && (string)($entry['entry_kind'] ?? '') === 'shared') {
				$entryIds[] = $entryId;
			}
		}
		$shares = $this->personalSharesForEntries($entryIds, $userId);

		foreach ($entries as &$entry) {
			$entryId = (int)($entry['id'] ?? 0);
			if ($entryId > 0 && array_key_exists($entryId, $shares)) {
				$entry['snapshot_share_cents'] = $shares[$entryId];
			}
		}
		unset($entry);

		return $entries;
	}

	/** @return array<int, int> */
	public function personalSharesForEntries(array $entryIds, string $userId): array {
		$entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn (int $id): bool => $id > 0)));
		$userId = trim($userId);
		if ($entryIds === [] || $userId === '') {
			return [];
		}

		$shares = [];
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('entry_id')
				->from('cobudget_entry_shares')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->groupBy('entry_id');
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$entryId = (int)$row['entry_id'];
				$shares[$entryId] = 0;
			}
			$result->closeCursor();

			$qb = $this->db->getQueryBuilder();
			$qb->select('entry_id', 'amount_cents')
				->from('cobudget_entry_shares')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$shares[(int)$row['entry_id']] = max(0, (int)$row['amount_cents']);
			}
			$result->closeCursor();
		}

		return $shares;
	}

	/** @return array<int, array<string, array{share_basis_points: int, amount_cents: int, personal_entry_id: ?int}>> */
	public function sharesForEntries(array $entryIds): array {
		$entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn (int $id): bool => $id > 0)));
		if ($entryIds === []) {
			return [];
		}

		$shares = [];
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('entry_id', 'user_id', 'share_basis_points', 'amount_cents', 'personal_entry_id')
				->from('cobudget_entry_shares')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->orderBy('id', 'ASC');
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$shares[(int)$row['entry_id']][(string)$row['user_id']] = [
					'share_basis_points' => max(0, (int)$row['share_basis_points']),
					'amount_cents' => max(0, (int)$row['amount_cents']),
					'personal_entry_id' => empty($row['personal_entry_id']) ? null : (int)$row['personal_entry_id'],
				];
			}
			$result->closeCursor();
		}

		return $shares;
	}

	public function deleteForEntry(int $entryId): void {
		$shareRows = $this->shareRowsForEntry($entryId);
		if ($shareRows === []) {
			return;
		}
		$entry = $this->entryRow($entryId);
		$projectId = (int)($entry['project_id'] ?? 0);
		if ($projectId > 0) {
			$this->lockProject($projectId);
			$this->adjustRoundingBalances($projectId, $shareRows, -1);
		}
		$this->deleteShareRows($entryId);
	}

	public function deleteForEntries(array $entryIds): void {
		$entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn (int $id): bool => $id > 0)));
		foreach ($entryIds as $entryId) {
			$this->deleteForEntry($entryId);
		}
	}

	public function resetRoundingBalancesForProject(int $projectId): void {
		if ($projectId <= 0) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_members')
			->set('expense_rounding_units', $qb->createNamedParameter(0, \PDO::PARAM_INT))
			->set('income_rounding_units', $qb->createNamedParameter(0, \PDO::PARAM_INT))
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
		$qb->executeStatement();

		$entryIds = [];
		$entries = $this->db->getQueryBuilder();
		$entries->select('id')
			->from('cobudget_entries')
			->where($entries->expr()->eq('project_id', $entries->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($entries->expr()->eq('entry_kind', $entries->createNamedParameter('shared')));
		$result = $entries->executeQuery();
		foreach ($result->fetchAll() as $row) {
			$entryIds[] = (int)$row['id'];
		}
		$result->closeCursor();

		foreach (array_chunk($entryIds, 500) as $chunk) {
			$shares = $this->db->getQueryBuilder();
			$shares->update('cobudget_entry_shares')
				->set('rounding_residual_units', $shares->createNamedParameter(0, \PDO::PARAM_INT))
				->where($shares->expr()->in('entry_id', $shares->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$shares->executeStatement();
		}
	}

	private function entryRow(int $entryId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'project_id', 'entry_kind', 'type', 'amount', 'amount_cents', 'split_mode', 'split_user_id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ?: null;
	}

	/** @return array<string, int> */
	private function personalEntryIdsFromRows(array $rows): array {
		$ids = [];
		foreach ($rows as $row) {
			$userId = trim((string)($row['user_id'] ?? ''));
			$personalEntryId = (int)($row['personal_entry_id'] ?? 0);
			if ($userId !== '' && $personalEntryId > 0) {
				$ids[$userId] = $personalEntryId;
			}
		}

		return $ids;
	}

	private function shareRowsForEntry(int $entryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'personal_entry_id', 'rounding_bucket', 'rounding_residual_units')
			->from('cobudget_entry_shares')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/** @return array{shares: array<string, int>, rounding_balances: array<string, int>} */
	private function projectMemberAllocationState(int $projectId, string $roundingBucket): array {
		$roundingColumn = $this->roundingColumn($roundingBucket);
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'share_basis_points', $roundingColumn)
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$shares = [];
		$roundingBalances = [];
		foreach ($result->fetchAll() as $row) {
			$userId = trim((string)($row['user_id'] ?? ''));
			if ($userId !== '') {
				$shares[$userId] = max(0, (int)($row['share_basis_points'] ?? 0));
				$roundingBalances[$userId] = (int)($row[$roundingColumn] ?? 0);
			}
		}
		$result->closeCursor();

		return [
			'shares' => $shares,
			'rounding_balances' => $roundingBalances,
		];
	}

	private function adjustRoundingBalances(int $projectId, array $shareRows, int $direction): void {
		foreach ($shareRows as $row) {
			$userId = trim((string)($row['user_id'] ?? ''));
			$residualUnits = (int)($row['rounding_residual_units'] ?? 0) * $direction;
			if ($userId === '' || $residualUnits === 0) {
				continue;
			}

			$column = $this->roundingColumn((string)($row['rounding_bucket'] ?? 'expense'));
			$read = $this->db->getQueryBuilder();
			$read->select($column)
				->from('cobudget_members')
				->where($read->expr()->eq('project_id', $read->createNamedParameter($projectId, \PDO::PARAM_INT)))
				->andWhere($read->expr()->eq('user_id', $read->createNamedParameter($userId)))
				->setMaxResults(1);
			$result = $read->executeQuery();
			$currentBalance = $result->fetchOne();
			$result->closeCursor();
			if ($currentBalance === false) {
				continue;
			}

			$update = $this->db->getQueryBuilder();
			$update->update('cobudget_members')
				->set($column, $update->createNamedParameter((int)$currentBalance + $residualUnits, \PDO::PARAM_INT))
				->where($update->expr()->eq('project_id', $update->createNamedParameter($projectId, \PDO::PARAM_INT)))
				->andWhere($update->expr()->eq('user_id', $update->createNamedParameter($userId)));
			$update->executeStatement();
		}
	}

	private function deleteShareRows(int $entryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('cobudget_entry_shares')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	private function lockProject(int $projectId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_projects')
			->set('id', $qb->createFunction('id'))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	private function roundingBucket(string $type): string {
		return trim($type) === 'income' ? 'income' : 'expense';
	}

	private function roundingColumn(string $roundingBucket): string {
		return $this->roundingBucket($roundingBucket) === 'income'
			? 'income_rounding_units'
			: 'expense_rounding_units';
	}

	private function amountCents(array $entry): int {
		if (isset($entry['amount_cents']) && is_numeric($entry['amount_cents'])) {
			return abs((int)$entry['amount_cents']);
		}

		return (int)round(abs((float)($entry['amount'] ?? 0)) * 100, 0, PHP_ROUND_HALF_UP);
	}
}
