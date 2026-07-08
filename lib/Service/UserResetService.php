<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;

class UserResetService {
	private const APP_ID = 'cobudget';
	private const CONFIRMATION_TEXT = 'RESET';
	private const SAFETY_BACKUP_FOLDER = 'CoBudget/Export';
	private const SAFETY_BACKUP_RETENTION = 8;
	private const RESET_LOCK_KEY = 'reset_running_since';
	private const RESET_LOCK_TTL_SECONDS = 6 * 60 * 60;

	private const SETTINGS_KEYS = [
		'currency',
		'enable_subscriptions',
		'enable_fixed_costs',
		'enable_child_related',
		'enable_important_payments',
		'enable_review_payments',
		'enable_tax_relevant',
		'enable_future_payments',
		'enable_templates',
		'enable_budget_goals',
		'enable_incomes',
		'enable_projects',
		'enable_shared_projects',
		'notify_project_entries',
		'notify_project_settlements',
		'enable_workspaces',
		'show_workspace_switcher',
		'enable_receipts',
		'default_start_page',
		'entries_per_page',
		'theme_mode',
		'receipt_storage_folder',
		'receipt_folder_grouping',
		'delete_receipts_with_entry',
		'backup_storage_folder',
		'backup_retention_count',
		'backup_schedule',
		'hidden_categories',
		'hidden_payment_partners',
		'hidden_workspaces',
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private BackupService $backupService,
		private HashtagService $hashtagService,
	) {
	}

	public function confirmationText(): string {
		return self::CONFIRMATION_TEXT;
	}

	public function preview(string $userId): array {
		$projects = $this->projectsForUser($userId);
		$sharedBlocking = [];
		$ownedSharedDeletable = [];
		$sharedLeaving = [];
		$soloProjectIds = [];

		foreach ($projects as $project) {
			$projectId = (int)$project['id'];
			$members = $this->projectMembers($projectId);
			$otherMembers = array_values(array_filter($members, static fn (array $member): bool => (string)$member['user_id'] !== $userId));

			if (count($members) > 1 && $otherMembers !== []) {
				$openCount = $this->countUnsettledProjectEntries($projectId);
				$row = [
					'id' => $projectId,
					'name' => (string)$project['name'],
					'member_count' => count($members),
					'open_entries' => $openCount,
				];

				if ($openCount > 0) {
					$sharedBlocking[] = $row;
				} elseif ((string)($project['owner_id'] ?? '') === $userId) {
					$ownedSharedDeletable[] = $row;
				} else {
					$sharedLeaving[] = $row;
				}
				continue;
			}

			$soloProjectIds[] = $projectId;
		}

		$workspaceIds = $this->workspaceIdsForUser($userId);
		$ownedSharedProjectIds = array_map(static fn (array $project): int => (int)$project['id'], $ownedSharedDeletable);
		$deletedProjectIds = array_values(array_unique(array_merge($soloProjectIds, $ownedSharedProjectIds)));
		$personalEntryIds = $this->entryIdsForPersonalWorkspaceRows($userId, $workspaceIds);
		$soloEntryIds = $this->entryIdsForProjects($soloProjectIds);
		$ownedSharedEntryIds = $this->entryIdsForProjects($ownedSharedProjectIds);
		$deletedEntryIds = array_values(array_unique(array_merge($personalEntryIds, $soloEntryIds, $ownedSharedEntryIds)));

		return [
			'confirmation' => self::CONFIRMATION_TEXT,
			'blocking_shared_projects' => $sharedBlocking,
			'deletable_shared_projects' => $ownedSharedDeletable,
			'leavable_shared_projects' => $sharedLeaving,
			'transferable_shared_projects' => [],
			'counts' => [
				'workspaces' => count($workspaceIds),
				'solo_projects' => count(array_unique($soloProjectIds)),
				'shared_projects_deleted' => count($ownedSharedDeletable),
				'shared_projects_left' => count($sharedLeaving),
				'entries' => count($deletedEntryIds),
				'attachments' => $this->countAttachmentsForEntries($deletedEntryIds),
				'categories' => $this->countUserScopedRows('cobudget_categories', $userId, $workspaceIds, $deletedProjectIds),
				'payment_partners' => $this->countUserScopedRows('cobudget_payment_partners', $userId, $workspaceIds, $deletedProjectIds),
				'templates' => $this->countRowsByColumn('cobudget_templates', 'user_id', $userId),
				'budget_goals' => $this->countRowsByColumn('cobudget_budget_goals', 'user_id', $userId),
			],
		];
	}

	public function reset(string $userId, string $confirmation): array {
		if ($confirmation !== self::CONFIRMATION_TEXT) {
			throw new \InvalidArgumentException('Bitte Reset mit RESET bestätigen');
		}

		$lock = $this->acquireResetLock($userId);
		if ($lock === null) {
			throw new \RuntimeException('Es läuft bereits ein CoBudget-Reset. Bitte später erneut versuchen.');
		}

		try {
			$preview = $this->preview($userId);
			if (($preview['blocking_shared_projects'] ?? []) !== []) {
				throw new ResetBlockedException('Offene gemeinsame Bereiche müssen vor dem Reset zuerst abgerechnet werden.', $preview);
			}

			$safetyBackup = $this->backupService->createBackup($userId, self::SAFETY_BACKUP_FOLDER, self::SAFETY_BACKUP_RETENTION);
			$deleteReceiptFiles = $this->config->getUserValue($userId, self::APP_ID, 'delete_receipts_with_entry', 'no') === 'yes';
			$report = [
				'safety_backup' => $safetyBackup,
				'blocking_shared_projects' => [],
				'deleted_shared_projects' => [],
				'left_shared_projects' => [],
				'transferred_shared_projects' => [],
				'deleted' => [
					'workspaces' => 0,
					'solo_projects' => 0,
					'shared_projects' => 0,
					'entries' => 0,
					'entry_history' => 0,
					'attachments' => 0,
					'attachment_files' => 0,
					'categories' => 0,
					'payment_partners' => 0,
					'templates' => 0,
					'budget_goals' => 0,
					'budget_snapshots' => 0,
				],
				'default_workspace' => null,
			];

			$this->db->beginTransaction();
			try {
				$workspaceIds = $this->workspaceIdsForUser($userId);
				$projects = $this->projectsForUser($userId);
				$soloProjectIds = [];

				foreach ($projects as $project) {
					$projectId = (int)$project['id'];
					$members = $this->projectMembers($projectId);
					$otherMembers = array_values(array_filter($members, static fn (array $member): bool => (string)$member['user_id'] !== $userId));

					if (count($members) > 1 && $otherMembers !== []) {
						if ($this->countUnsettledProjectEntries($projectId) > 0) {
							throw new \RuntimeException('Offene gemeinsame Bereiche müssen vor dem Reset zuerst abgerechnet werden.');
						}

						if ((string)($project['owner_id'] ?? '') === $userId) {
							$projectReport = $this->deleteProjectTree($projectId, $deleteReceiptFiles);
							$report['deleted']['shared_projects']++;
							$this->addDeletedCounts($report, $projectReport);
							$report['deleted_shared_projects'][] = [
								'id' => $projectId,
								'name' => (string)$project['name'],
							];
							continue;
						}

						$this->leaveSettledSharedProject($projectId, $userId);
						$report['left_shared_projects'][] = [
							'id' => $projectId,
							'name' => (string)$project['name'],
						];
						continue;
					}

					$soloProjectIds[] = $projectId;
				}

				foreach (array_values(array_unique($soloProjectIds)) as $projectId) {
					$projectReport = $this->deleteProjectTree((int)$projectId, $deleteReceiptFiles);
					$report['deleted']['solo_projects']++;
					$this->addDeletedCounts($report, $projectReport);
				}

				$personalEntryIds = $this->entryIdsForPersonalWorkspaceRows($userId, $workspaceIds);
				$personalAttachments = $this->deleteAttachmentsForEntries($personalEntryIds, $deleteReceiptFiles);
				$report['deleted']['attachments'] += $personalAttachments['rows'];
				$report['deleted']['attachment_files'] += $personalAttachments['files'];
				$this->hashtagService->deleteHashtagsForEntries($personalEntryIds);
				$report['deleted']['entry_history'] += $this->deleteRowsByIds('cobudget_entry_history', $this->idsByColumnValues('cobudget_entry_history', 'entry_id', $personalEntryIds));
				$report['deleted']['entries'] += $this->deleteRowsByIds('cobudget_entries', $personalEntryIds);

				$report['deleted']['categories'] += $this->deleteUserScopedRows('cobudget_categories', $userId, $workspaceIds, $soloProjectIds);
				$report['deleted']['payment_partners'] += $this->deleteUserScopedRows('cobudget_payment_partners', $userId, $workspaceIds, $soloProjectIds);
				$report['deleted']['templates'] += $this->deleteRowsByStringColumn('cobudget_templates', 'user_id', $userId);
				$report['deleted']['budget_snapshots'] += $this->deleteRowsByStringColumn('cobudget_budget_snapshots', 'user_id', $userId);
				$report['deleted']['budget_goals'] += $this->deleteRowsByStringColumn('cobudget_budget_goals', 'user_id', $userId);
				$this->deleteRowsByStringColumn('cobudget_members', 'user_id', $userId);

				foreach ($workspaceIds as $workspaceId) {
					$this->hashtagService->deleteWorkspaceHashtags((int)$workspaceId);
				}
				$report['deleted']['workspaces'] = $this->deleteRowsByIds('cobudget_workspaces', $workspaceIds);
				$this->resetUserSettings($userId);
				$defaultWorkspaceId = $this->createDefaultWorkspaceForUser($userId);
				$report['default_workspace'] = [
					'id' => $defaultWorkspaceId,
					'name' => 'Haupt-Workspace',
				];

				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return [
				'status' => 'success',
				'preview' => $preview,
				'report' => $report,
			];
		} finally {
			$this->releaseResetLock($userId, $lock);
		}
	}

	private function addDeletedCounts(array &$report, array $counts): void {
		foreach ($counts as $key => $count) {
			if (isset($report['deleted'][$key])) {
				$report['deleted'][$key] += (int)$count;
			}
		}
	}

	private function projectsForUser(string $userId): array {
		$byId = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
			->from('cobudget_projects', 'p')
			->innerJoin('p', 'cobudget_members', 'm', $qb->expr()->eq('p.id', 'm.project_id'))
			->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($userId)))
			->orderBy('p.name', 'ASC');
		foreach ($this->fetchAll($qb) as $row) {
			$byId[(int)$row['id']] = $row;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_projects')
			->where($qb->expr()->eq('owner_id', $qb->createNamedParameter($userId)))
			->orderBy('name', 'ASC');
		foreach ($this->fetchAll($qb) as $row) {
			$byId[(int)$row['id']] = $row;
		}

		return array_values($byId);
	}

	private function projectMembers(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');

		return $this->fetchAll($qb);
	}

	private function countUnsettledProjectEntries(int $projectId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*) AS entry_count'))
			->from('cobudget_entries')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)));

		return (int)($this->fetchOne($qb)['entry_count'] ?? 0);
	}

	private function leaveSettledSharedProject(int $projectId, string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	private function deleteProjectTree(int $projectId, bool $deleteReceiptFiles): array {
		$entryIds = $this->entryIdsForProjects([$projectId]);
		$settlementIds = $this->idsByColumn('cobudget_settlements', 'project_id', $projectId);
		$attachmentReport = $this->deleteAttachmentsForEntries($entryIds, $deleteReceiptFiles);
		$this->hashtagService->deleteHashtagsForEntries($entryIds);

		$deleted = [
			'entries' => 0,
			'entry_history' => $this->deleteRowsByIds('cobudget_entry_history', $this->idsByColumnValues('cobudget_entry_history', 'entry_id', $entryIds)),
			'attachments' => $attachmentReport['rows'],
			'attachment_files' => $attachmentReport['files'],
			'categories' => $this->deleteRowsByIntColumn('cobudget_categories', 'project_id', $projectId),
			'payment_partners' => $this->deleteRowsByIntColumn('cobudget_payment_partners', 'project_id', $projectId),
			'templates' => $this->deleteRowsByIntColumn('cobudget_templates', 'project_id', $projectId),
		];

		$this->deleteRowsByIds('cobudget_settlement_balances', $this->idsByColumnValues('cobudget_settlement_balances', 'settlement_id', $settlementIds));
		$this->deleteRowsByIds('cobudget_settlement_transfers', $this->idsByColumnValues('cobudget_settlement_transfers', 'settlement_id', $settlementIds));
		$this->deleteRowsByIds('cobudget_settlements', $settlementIds);
		$deleted['entries'] += $this->deleteRowsByIds('cobudget_entries', $entryIds);
		$this->deleteRowsByIntColumn('cobudget_members', 'project_id', $projectId);
		$this->deleteRowsByIds('cobudget_projects', [$projectId]);

		return $deleted;
	}

	private function deleteAttachmentsForEntries(array $entryIds, bool $deleteReceiptFiles): array {
		$entryIds = array_values(array_unique(array_map('intval', $entryIds)));
		if ($entryIds === []) {
			return ['rows' => 0, 'files' => 0];
		}

		$attachments = $this->fetchRowsByColumnValues('cobudget_entry_attachments', 'entry_id', $entryIds);
		$filesDeleted = 0;
		if ($deleteReceiptFiles) {
			foreach ($attachments as $attachment) {
				if ($this->deleteAttachmentFile($attachment)) {
					$filesDeleted++;
				}
			}
		}

		return [
			'rows' => $this->deleteRowsByIds('cobudget_entry_attachments', $this->ids($attachments)),
			'files' => $filesDeleted,
		];
	}

	private function deleteAttachmentFile(array $attachment): bool {
		try {
			$ownerUserId = (string)($attachment['owner_user_id'] ?? '');
			$filePath = trim((string)($attachment['file_path'] ?? ''), '/');
			if ($ownerUserId === '' || $filePath === '') {
				return false;
			}

			$userFolder = $this->rootFolder->getUserFolder($ownerUserId);
			if (!$userFolder->nodeExists($filePath)) {
				return false;
			}

			$node = $userFolder->get($filePath);
			if (!$node instanceof File) {
				return false;
			}

			$node->delete();
			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function workspaceIdsForUser(string $userId): array {
		return $this->idsByStringColumn('cobudget_workspaces', 'user_id', $userId);
	}

	private function entryIdsForProjects(array $projectIds): array {
		return $this->idsByColumnValues('cobudget_entries', 'project_id', $projectIds);
	}

	private function entryIdsForPersonalWorkspaceRows(string $userId, array $workspaceIds): array {
		$workspaceIds = array_values(array_unique(array_map('intval', $workspaceIds)));
		if ($workspaceIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->in('workspace_id', $qb->createNamedParameter($workspaceIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->isNull('project_id'));

		return $this->ids($this->fetchAll($qb));
	}

	private function countAttachmentsForEntries(array $entryIds): int {
		$entryIds = array_values(array_unique(array_map('intval', $entryIds)));
		if ($entryIds === []) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*) AS row_count'))
			->from('cobudget_entry_attachments')
			->where($qb->expr()->in('entry_id', $qb->createNamedParameter($entryIds, IQueryBuilder::PARAM_INT_ARRAY)));

		return (int)($this->fetchOne($qb)['row_count'] ?? 0);
	}

	private function countUserScopedRows(string $table, string $userId, array $workspaceIds, array $soloProjectIds): int {
		$qb = $this->userScopedRowsQuery($table, $userId, $workspaceIds, $soloProjectIds);
		$qb->select($qb->createFunction('COUNT(*) AS row_count'));

		return (int)($this->fetchOne($qb)['row_count'] ?? 0);
	}

	private function deleteUserScopedRows(string $table, string $userId, array $workspaceIds, array $soloProjectIds): int {
		$qb = $this->userScopedRowsQuery($table, $userId, $workspaceIds, $soloProjectIds);
		$qb->select('id');
		$ids = $this->ids($this->fetchAll($qb));
		return $this->deleteRowsByIds($table, $ids);
	}

	private function userScopedRowsQuery(string $table, string $userId, array $workspaceIds, array $soloProjectIds): IQueryBuilder {
		$workspaceIds = array_values(array_unique(array_map('intval', $workspaceIds)));
		$soloProjectIds = array_values(array_unique(array_map('intval', $soloProjectIds)));
		$qb = $this->db->getQueryBuilder();
		$qb->from($table)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		$scopes = [$qb->expr()->isNull('project_id')];
		if ($soloProjectIds !== []) {
			$scopes[] = $qb->expr()->in('project_id', $qb->createNamedParameter($soloProjectIds, IQueryBuilder::PARAM_INT_ARRAY));
		}
		if ($workspaceIds !== []) {
			$scopes[] = $qb->expr()->andX(
				$qb->expr()->in('workspace_id', $qb->createNamedParameter($workspaceIds, IQueryBuilder::PARAM_INT_ARRAY)),
				$qb->expr()->isNull('project_id')
			);
		}

		$qb->andWhere($qb->expr()->orX(...$scopes));

		return $qb;
	}

	private function resetUserSettings(string $userId): void {
		foreach (self::SETTINGS_KEYS as $key) {
			$this->config->deleteUserValue($userId, self::APP_ID, $key);
		}
	}

	private function createDefaultWorkspaceForUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('cobudget_workspaces')
			->values([
				'name' => $qb->createNamedParameter('Haupt-Workspace'),
				'user_id' => $qb->createNamedParameter($userId),
				'is_default' => $qb->createNamedParameter(true, \PDO::PARAM_BOOL),
				'created_at' => $qb->createNamedParameter(time(), \PDO::PARAM_INT),
		]);
		$qb->executeStatement();
		return (int)$this->db->lastInsertId('*PREFIX*cobudget_workspaces');
	}

	private function idsByStringColumn(string $table, string $column, string $value): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value)));

		return $this->ids($this->fetchAll($qb));
	}

	private function idsByColumn(string $table, string $column, int $value): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, \PDO::PARAM_INT)));

		return $this->ids($this->fetchAll($qb));
	}

	private function idsByColumnValues(string $table, string $column, array $values): array {
		$values = array_values(array_unique(array_map('intval', $values)));
		if ($values === []) {
			return [];
		}

		$ids = [];
		foreach (array_chunk($values, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from($table)
				->where($qb->expr()->in($column, $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$ids = array_merge($ids, $this->ids($this->fetchAll($qb)));
		}

		return array_values(array_unique($ids));
	}

	private function fetchRowsByColumnValues(string $table, string $column, array $values): array {
		$values = array_values(array_unique(array_map('intval', $values)));
		if ($values === []) {
			return [];
		}

		$rows = [];
		foreach (array_chunk($values, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($table)
				->where($qb->expr()->in($column, $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$rows = array_merge($rows, $this->fetchAll($qb));
		}

		return $rows;
	}

	private function countRowsByColumn(string $table, string $column, string $value): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*) AS row_count'))
			->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value)));

		return (int)($this->fetchOne($qb)['row_count'] ?? 0);
	}

	private function deleteRowsByStringColumn(string $table, string $column, string $value): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value)));

		return $qb->executeStatement();
	}

	private function deleteRowsByIntColumn(string $table, string $column, int $value): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, \PDO::PARAM_INT)));

		return $qb->executeStatement();
	}

	private function deleteRowsByIds(string $table, array $ids): int {
		$ids = array_values(array_unique(array_map('intval', $ids)));
		$deleted = 0;
		foreach (array_chunk($ids, 500) as $chunk) {
			if ($chunk === []) {
				continue;
			}
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$deleted += $qb->executeStatement();
		}

		return $deleted;
	}

	private function ids(array $rows): array {
		$ids = [];
		foreach ($rows as $row) {
			if (isset($row['id'])) {
				$ids[] = (int)$row['id'];
			}
		}

		return array_values(array_unique($ids));
	}

	private function fetchAll(IQueryBuilder $qb): array {
		$result = $qb->executeQuery();
		try {
			return $result->fetchAll();
		} finally {
			$result->closeCursor();
		}
	}

	private function fetchOne(IQueryBuilder $qb): ?array {
		$result = $qb->executeQuery();
		try {
			$row = $result->fetch();
			return is_array($row) ? $row : null;
		} finally {
			$result->closeCursor();
		}
	}

	private function displayName(string $userId): string {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return $userId;
		}

		return trim((string)$user->getDisplayName()) ?: $userId;
	}

	private function acquireResetLock(string $userId): ?string {
		$now = time();
		$this->deleteStaleResetLock($userId, $now);
		$value = (string)$now;
		$qb = $this->db->getQueryBuilder();
		try {
			$qb->insert('preferences')
				->values([
					'userid' => $qb->createNamedParameter($userId),
					'appid' => $qb->createNamedParameter(self::APP_ID),
					'configkey' => $qb->createNamedParameter(self::RESET_LOCK_KEY),
					'configvalue' => $qb->createNamedParameter($value),
				]);
			$qb->executeStatement();

			return $value;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function deleteStaleResetLock(string $userId, int $now): void {
		$staleBefore = (string)($now - self::RESET_LOCK_TTL_SECONDS);
		$qb = $this->db->getQueryBuilder();
		$qb->delete('preferences')
			->where($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('appid', $qb->createNamedParameter(self::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter(self::RESET_LOCK_KEY)))
			->andWhere($qb->expr()->lt('configvalue', $qb->createNamedParameter($staleBefore)));
		$qb->executeStatement();
	}

	private function releaseResetLock(string $userId, string $lockValue): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('preferences')
			->where($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('appid', $qb->createNamedParameter(self::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter(self::RESET_LOCK_KEY)))
			->andWhere($qb->expr()->eq('configvalue', $qb->createNamedParameter($lockValue)));
		$qb->executeStatement();
	}
}

class ResetBlockedException extends \RuntimeException {
	public function __construct(string $message, private array $preview) {
		parent::__construct($message);
	}

	public function preview(): array {
		return $this->preview;
	}
}
