<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;

class BackupService {
	private const DEFAULT_BACKUP_FOLDER = 'CoBudget/Backups';
	private const DEFAULT_RETENTION_COUNT = 7;
	private const DEFAULT_BACKUP_SCHEDULE = 'none';
	private const MAX_RETENTION_COUNT = 100;
	private const RESTORE_LOCK_USER = '__cobudget_restore__';
	private const RESTORE_LOCK_KEY = 'restore_running_since';
	private const RESTORE_LOCK_TTL_SECONDS = 6 * 60 * 60;
	private const USER_BACKUP_FILE_PATTERN = '/^cobudget-backup-\d{8}-\d{6}(?:-\d+)?\.zip$/';
	private const FULL_BACKUP_FILE_PATTERN = '/^cobudget-full-backup-\d{8}-\d{6}(?:-\d+)?\.zip$/';
	private const BACKUP_FILE_PATTERN = '/^cobudget(?:-full)?-backup-\d{8}-\d{6}(?:-\d+)?\.zip$/';
	private const VALID_BACKUP_SCHEDULES = ['none', 'daily', 'weekly', 'monthly'];

	private const BACKUP_TABLES = [
		'cobudget_workspaces',
		'cobudget_projects',
		'cobudget_members',
		'cobudget_categories',
		'cobudget_payment_partners',
		'cobudget_templates',
		'cobudget_entries',
		'cobudget_entry_history',
		'cobudget_hashtags',
		'cobudget_entry_hashtags',
		'cobudget_entry_attachments',
		'cobudget_settlements',
		'cobudget_settlement_balances',
		'cobudget_settlement_transfers',
		'cobudget_budget_goals',
		'cobudget_budget_snapshots',
	];

	private const BACKUP_TABLE_COLUMNS = [
		'cobudget_workspaces' => [
			'id',
			'name',
			'user_id',
			'is_default',
			'created_at',
		],
		'cobudget_projects' => [
			'id',
			'name',
			'owner_id',
			'created_at',
			'color',
			'is_archived',
			'workspace_id',
		],
		'cobudget_members' => [
			'id',
			'project_id',
			'user_id',
			'share_basis_points',
		],
		'cobudget_categories' => [
			'id',
			'name',
			'is_global',
			'user_id',
			'workspace_id',
			'icon',
			'type',
			'project_id',
			'is_hidden',
		],
		'cobudget_payment_partners' => [
			'id',
			'name',
			'is_global',
			'user_id',
			'workspace_id',
			'type',
			'project_id',
			'is_hidden',
		],
		'cobudget_templates' => [
			'id',
			'user_id',
			'name',
			'description',
			'type',
			'amount',
			'amount_cents',
			'category_id',
			'payment_partner_id',
			'project_id',
			'split_mode',
			'is_subscription',
			'is_fixed_cost',
			'is_child_related',
			'is_important',
			'needs_review',
			'is_tax_relevant',
			'workspace_id',
			'usage_count',
		],
		'cobudget_entries' => [
			'id',
			'user_id',
			'project_id',
			'type',
			'amount',
			'amount_cents',
			'currency',
			'date',
			'category_id',
			'payment_partner_id',
			'description',
			'split_mode',
			'is_settled',
			'settled_at',
			'settlement_id',
			'recurrence_interval',
			'recurrence_multiplier',
			'recurrence_next_date',
			'recurrence_end_date',
			'recurrence_parent_id',
			'recurrence_series_id',
			'is_subscription',
			'is_fixed_cost',
			'is_child_related',
			'is_important',
			'needs_review',
			'is_tax_relevant',
			'reminder_date',
			'reminder_notified',
			'reminder_text',
			'workspace_id',
		],
		'cobudget_entry_history' => [
			'id',
			'entry_id',
			'workspace_id',
			'project_id',
			'changed_by',
			'changed_by_display_name',
			'changed_at',
			'change_group',
			'field',
			'old_value',
			'new_value',
			'old_display',
			'new_display',
		],
		'cobudget_hashtags' => [
			'id',
			'workspace_id',
			'normalized_name',
			'display_name',
			'created_at',
			'updated_at',
		],
		'cobudget_entry_hashtags' => [
			'id',
			'entry_id',
			'hashtag_id',
			'workspace_id',
			'created_at',
		],
		'cobudget_entry_attachments' => [
			'id',
			'entry_id',
			'workspace_id',
			'owner_user_id',
			'file_id',
			'file_path',
			'file_name',
			'mime_type',
			'file_size',
			'created_at',
		],
		'cobudget_settlements' => [
			'id',
			'project_id',
			'workspace_id',
			'created_by',
			'created_at',
			'currency',
		],
		'cobudget_settlement_balances' => [
			'id',
			'settlement_id',
			'user_id',
			'display_name',
			'paid_cents',
			'share_cents',
			'balance_cents',
			'share_basis_points',
		],
		'cobudget_settlement_transfers' => [
			'id',
			'settlement_id',
			'from_user_id',
			'from_display_name',
			'to_user_id',
			'to_display_name',
			'amount_cents',
		],
		'cobudget_budget_goals' => [
			'id',
			'user_id',
			'workspace_id',
			'name',
			'amount_cents',
			'period',
			'mode',
			'criteria_json',
			'created_at',
			'updated_at',
		],
		'cobudget_budget_snapshots' => [
			'id',
			'budget_goal_id',
			'user_id',
			'workspace_id',
			'snapshot_reason',
			'goal_name',
			'amount_cents',
			'period',
			'mode',
			'criteria_json',
			'period_start',
			'period_end',
			'spent_cents',
			'planned_cents',
			'buffer_cents',
			'forecast_cents',
			'progress_tenths',
			'status',
			'created_at',
		],
	];

	private const USER_COLUMNS = [
		'cobudget_workspaces' => ['user_id'],
		'cobudget_projects' => ['owner_id'],
		'cobudget_members' => ['user_id'],
		'cobudget_categories' => ['user_id'],
		'cobudget_payment_partners' => ['user_id'],
		'cobudget_templates' => ['user_id'],
		'cobudget_entries' => ['user_id'],
		'cobudget_entry_history' => ['changed_by'],
		'cobudget_entry_attachments' => ['owner_user_id'],
		'cobudget_settlements' => ['created_by'],
		'cobudget_settlement_balances' => ['user_id'],
		'cobudget_settlement_transfers' => ['from_user_id', 'to_user_id'],
		'cobudget_budget_goals' => ['user_id'],
		'cobudget_budget_snapshots' => ['user_id'],
	];

	private const BACKUP_INTERNAL_REFERENCES = [
		['sourceTable' => 'cobudget_projects', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_members', 'column' => 'project_id', 'targetTable' => 'cobudget_projects'],
		['sourceTable' => 'cobudget_categories', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_categories', 'column' => 'project_id', 'targetTable' => 'cobudget_projects'],
		['sourceTable' => 'cobudget_payment_partners', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_payment_partners', 'column' => 'project_id', 'targetTable' => 'cobudget_projects'],
		['sourceTable' => 'cobudget_templates', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_templates', 'column' => 'category_id', 'targetTable' => 'cobudget_categories'],
		['sourceTable' => 'cobudget_templates', 'column' => 'payment_partner_id', 'targetTable' => 'cobudget_payment_partners'],
		['sourceTable' => 'cobudget_templates', 'column' => 'project_id', 'targetTable' => 'cobudget_projects'],
		['sourceTable' => 'cobudget_entries', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_entries', 'column' => 'category_id', 'targetTable' => 'cobudget_categories'],
		['sourceTable' => 'cobudget_entries', 'column' => 'payment_partner_id', 'targetTable' => 'cobudget_payment_partners'],
		['sourceTable' => 'cobudget_entries', 'column' => 'project_id', 'targetTable' => 'cobudget_projects'],
		['sourceTable' => 'cobudget_entries', 'column' => 'settlement_id', 'targetTable' => 'cobudget_settlements'],
		['sourceTable' => 'cobudget_entry_history', 'column' => 'entry_id', 'targetTable' => 'cobudget_entries'],
		['sourceTable' => 'cobudget_entry_history', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_entry_history', 'column' => 'project_id', 'targetTable' => 'cobudget_projects'],
		['sourceTable' => 'cobudget_hashtags', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_entry_hashtags', 'column' => 'entry_id', 'targetTable' => 'cobudget_entries'],
		['sourceTable' => 'cobudget_entry_hashtags', 'column' => 'hashtag_id', 'targetTable' => 'cobudget_hashtags'],
		['sourceTable' => 'cobudget_entry_hashtags', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_entry_attachments', 'column' => 'entry_id', 'targetTable' => 'cobudget_entries'],
		['sourceTable' => 'cobudget_entry_attachments', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_settlements', 'column' => 'project_id', 'targetTable' => 'cobudget_projects'],
		['sourceTable' => 'cobudget_settlements', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_settlement_balances', 'column' => 'settlement_id', 'targetTable' => 'cobudget_settlements'],
		['sourceTable' => 'cobudget_settlement_transfers', 'column' => 'settlement_id', 'targetTable' => 'cobudget_settlements'],
		['sourceTable' => 'cobudget_budget_goals', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_budget_snapshots', 'column' => 'workspace_id', 'targetTable' => 'cobudget_workspaces'],
		['sourceTable' => 'cobudget_budget_snapshots', 'column' => 'budget_goal_id', 'targetTable' => 'cobudget_budget_goals'],
	];

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

	private const SETTINGS_DEFAULTS = [
		'currency' => 'EUR',
		'enable_subscriptions' => 'yes',
		'enable_fixed_costs' => 'yes',
		'enable_child_related' => 'yes',
		'enable_important_payments' => 'yes',
		'enable_review_payments' => 'yes',
		'enable_tax_relevant' => 'yes',
		'enable_future_payments' => 'yes',
		'enable_templates' => 'yes',
		'enable_budget_goals' => 'yes',
		'enable_incomes' => 'yes',
		'enable_projects' => 'yes',
		'enable_shared_projects' => 'yes',
		'notify_project_entries' => 'yes',
		'notify_project_settlements' => 'yes',
		'enable_workspaces' => 'no',
		'show_workspace_switcher' => 'yes',
		'enable_receipts' => 'yes',
		'default_start_page' => 'personal',
		'entries_per_page' => '25',
		'theme_mode' => 'auto',
		'receipt_storage_folder' => 'CoBudget/Belege',
		'receipt_folder_grouping' => 'year',
		'delete_receipts_with_entry' => 'no',
		'backup_storage_folder' => 'CoBudget/Backups',
		'backup_retention_count' => '7',
		'backup_schedule' => 'none',
		'hidden_categories' => '[]',
		'hidden_payment_partners' => '[]',
		'hidden_workspaces' => '[]',
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
	) {
	}

	public function getBackupFolder(string $userId): string {
		return $this->normalizeFolder($this->config->getUserValue($userId, 'cobudget', 'backup_storage_folder', self::DEFAULT_BACKUP_FOLDER));
	}

	public function getRetentionCount(string $userId): int {
		return $this->normalizeRetentionCount($this->config->getUserValue($userId, 'cobudget', 'backup_retention_count', (string)self::DEFAULT_RETENTION_COUNT));
	}

	public function getBackupSchedule(string $userId): string {
		return $this->normalizeSchedule($this->config->getUserValue($userId, 'cobudget', 'backup_schedule', self::DEFAULT_BACKUP_SCHEDULE));
	}

	public function normalizeFolder(string $folder): string {
		$folder = trim($folder);
		$folder = trim($folder, '/');
		if ($folder === '' || str_contains($folder, '\\') || preg_match('~(^|/)\.\.(/|$)~', $folder) === 1 || preg_match('/[\x00-\x1F]/', $folder) === 1) {
			throw new \InvalidArgumentException('Ungültiger Backup-Ordner');
		}
		if (mb_strlen($folder) > 180) {
			throw new \InvalidArgumentException('Backup-Ordner ist zu lang');
		}

		return $folder;
	}

	public function normalizeRetentionCount(int|string $count): int {
		$count = (int)$count;
		if ($count < 1 || $count > self::MAX_RETENTION_COUNT) {
			throw new \InvalidArgumentException('Anzahl der Backups muss zwischen 1 und 100 liegen');
		}

		return $count;
	}

	public function normalizeSchedule(string $schedule): string {
		$schedule = trim($schedule);
		if (!in_array($schedule, self::VALID_BACKUP_SCHEDULES, true)) {
			throw new \InvalidArgumentException('Ungültiger Backup-Zeitraum');
		}

		return $schedule;
	}

	public function createBackup(string $userId, ?string $folderOverride = null, ?int $retentionOverride = null): array {
		if (!class_exists(\ZipArchive::class)) {
			throw new \RuntimeException('ZIP-Unterstützung ist auf diesem Server nicht verfügbar');
		}

		$folderPath = $folderOverride !== null ? $this->normalizeFolder($folderOverride) : $this->getBackupFolder($userId);
		$retentionCount = $retentionOverride !== null ? $this->normalizeRetentionCount($retentionOverride) : $this->getRetentionCount($userId);
		$createdAt = time();
		$fileName = 'cobudget-backup-' . date('Ymd-His', $createdAt) . '.zip';
		$tempFile = tempnam(sys_get_temp_dir(), 'cobudget-backup-');

		if ($tempFile === false) {
			throw new \RuntimeException('Temporäre Backup-Datei konnte nicht erstellt werden');
		}

		try {
			$this->writeBackupZip($tempFile, $userId, $createdAt);
			return $this->storeBackupFile($userId, $folderPath, $retentionCount, $fileName, $tempFile, self::USER_BACKUP_FILE_PATTERN);
		} finally {
			if (is_file($tempFile)) {
				@unlink($tempFile);
			}
		}
	}

	public function createFullBackup(string $storageUserId, ?string $folderOverride = null, ?int $retentionOverride = null): array {
		if (!class_exists(\ZipArchive::class)) {
			throw new \RuntimeException('ZIP-Unterstützung ist auf diesem Server nicht verfügbar');
		}

		$folderPath = $folderOverride !== null ? $this->normalizeFolder($folderOverride) : $this->getBackupFolder($storageUserId);
		$retentionCount = $retentionOverride !== null ? $this->normalizeRetentionCount($retentionOverride) : $this->getRetentionCount($storageUserId);
		$createdAt = time();
		$fileName = 'cobudget-full-backup-' . date('Ymd-His', $createdAt) . '.zip';
		$tempFile = tempnam(sys_get_temp_dir(), 'cobudget-full-backup-');

		if ($tempFile === false) {
			throw new \RuntimeException('Temporäre Backup-Datei konnte nicht erstellt werden');
		}

		try {
			$this->writeFullBackupZip($tempFile, $storageUserId, $createdAt);
			return $this->storeBackupFile($storageUserId, $folderPath, $retentionCount, $fileName, $tempFile, self::FULL_BACKUP_FILE_PATTERN);
		} finally {
			if (is_file($tempFile)) {
				@unlink($tempFile);
			}
		}
	}

	public function listBackups(string $userId): array {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$backups = [];
		foreach ($this->backupLookupFolders($userId) as $folderPath) {
			if (!$userFolder->nodeExists($folderPath)) {
				continue;
			}
			$node = $userFolder->get($folderPath);
			if (!$node instanceof Folder) {
				continue;
			}
			foreach ($this->sortedBackupFiles($node) as $file) {
				$backups[] = $this->formatBackupFile($file, $folderPath);
			}
		}

		usort($backups, static function (array $a, array $b): int {
			$timeCompare = ((int)($b['created_at'] ?? 0)) <=> ((int)($a['created_at'] ?? 0));
			return $timeCompare !== 0 ? $timeCompare : strcmp((string)($b['file_name'] ?? ''), (string)($a['file_name'] ?? ''));
		});

		return array_slice($backups, 0, $this->getRetentionCount($userId));
	}

	public function getBackupFile(string $userId, string $fileName): File {
		return $this->getBackupFileFromFolder($userId, $fileName, null);
	}

	public function deleteBackup(string $userId, string $fileName): void {
		$this->getBackupFileFromFolder($userId, $fileName, null)->delete();
	}

	public function inspectBackup(string $userId, string $fileName, ?string $folderOverride = null): array {
		$archive = $this->readBackupArchive($this->getBackupFileFromFolder($userId, $fileName, $folderOverride));
		$manifest = $archive['manifest'];
		$scope = (string)($manifest['scope'] ?? '');
		if ($scope === 'user') {
			$this->assertBackupArchive($archive, 'user');
		} elseif ($scope === 'system') {
			$this->assertBackupArchive($archive, 'system');
		} else {
			throw new \InvalidArgumentException('Backup nutzt einen unbekannten Umfang');
		}

		$sourceUserId = $scope === 'user'
			? trim((string)($manifest['user_id'] ?? ($manifest['users'][0] ?? '')))
			: '';
		$userMap = $sourceUserId !== '' && $sourceUserId !== $userId ? [$sourceUserId => $userId] : [];
		$userIds = $this->collectUserIdsFromArchive($archive, $sourceUserId);

		return [
			'scope' => $scope,
			'file_name' => $fileName,
			'source_user_id' => $sourceUserId,
			'target_user_id' => $userId,
			'users' => $this->buildBackupUserRows($userIds, $userMap),
			'tables' => array_map('count', $archive['tables']),
		];
	}

	public function restoreBackup(string $userId, string $fileName, ?string $folderOverride = null, array $userMap = []): array {
		$restoreLock = $this->acquireRestoreLock();
		if ($restoreLock === null) {
			throw new \RuntimeException('Es läuft bereits eine CoBudget-Wiederherstellung. Bitte später erneut versuchen.');
		}

		try {
			$archive = $this->readBackupArchive($this->getBackupFileFromFolder($userId, $fileName, $folderOverride));
			$this->assertBackupArchive($archive, 'user');

			$sourceUserId = trim((string)($archive['manifest']['user_id'] ?? ($archive['manifest']['users'][0] ?? '')));
			if ($sourceUserId === '') {
				throw new \InvalidArgumentException('Backup enthaelt keinen Benutzer');
			}

			$userMap = $this->normalizeUserMap($userMap);
			if ($sourceUserId !== $userId) {
				$userMap[$sourceUserId] = $userId;
			}
			$skippedRows = [];
			$tables = $this->filterUserRestoreTables($this->applyUserMapToTables($archive['tables'], $userMap), $skippedRows, $userId);
			$settings = $this->normalizeUserSettings($archive['settings'], $tables, $userId);
			$this->assertUserRestoreScope($tables, $userId);
			$this->assertReferencedUsersExist($tables, [$userId]);
			$this->assertBackupInternalReferences($tables);
			$this->assertProjectMemberConsistency($tables);

			$safetyBackup = $this->createBackup($userId, $folderOverride, $this->getSafetyBackupRetentionCount($userId));

			$this->db->beginTransaction();
			try {
				$currentData = $this->collectBackupData($userId);
				$deleteSkippedRows = [];
				$this->deleteRowsByBackupData($this->filterUserRestoreTables($currentData['tables'], $deleteSkippedRows, $userId));
				$this->deleteSettingsForUsers([$userId]);
				$this->insertTables($tables);
				$this->restoreUserSettings($userId, $settings);
				$this->synchronizeAutoincrementSequences($tables);
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return [
				'scope' => 'user',
				'user_id' => $userId,
				'file_name' => $fileName,
				'safety_backup' => $safetyBackup,
				'tables' => array_map('count', $tables),
				'report' => $this->buildRestoreReport('user', $fileName, $tables, [$userId => $settings], $userMap, $skippedRows),
			];
		} finally {
			$this->releaseRestoreLock($restoreLock);
		}
	}

	public function restoreFullBackup(string $storageUserId, string $fileName, ?string $folderOverride = null, array $userMap = []): array {
		$restoreLock = $this->acquireRestoreLock();
		if ($restoreLock === null) {
			throw new \RuntimeException('Es läuft bereits eine CoBudget-Wiederherstellung. Bitte später erneut versuchen.');
		}

		try {
			$archive = $this->readBackupArchive($this->getBackupFileFromFolder($storageUserId, $fileName, $folderOverride));
			$this->assertBackupArchive($archive, 'system');

			$userMap = $this->normalizeUserMap($userMap);
			$tables = $this->applyUserMapToTables($archive['tables'], $userMap);
			$settings = $this->applyUserMapToSettings($this->normalizeFullSettings($archive['settings'], $tables), $userMap);
			$settings = $this->completeRestoreSettingsForTableUsers($settings, $tables);
			$this->assertReferencedUsersExist($tables, array_keys($settings));
			$this->assertBackupInternalReferences($tables);
			$this->assertProjectMemberConsistency($tables);

			$safetyBackup = $this->createFullBackup($storageUserId, $folderOverride, $this->getSafetyBackupRetentionCount($storageUserId));

			$this->db->beginTransaction();
			try {
				$this->deleteAllBackupTables();
				$this->deleteAllCoBudgetSettings();
				$this->insertTables($tables);
				$this->restoreAllSettings($settings);
				$this->synchronizeAutoincrementSequences($tables);
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}

			return [
				'scope' => 'system',
				'storage_user_id' => $storageUserId,
				'file_name' => $fileName,
				'safety_backup' => $safetyBackup,
				'users' => array_values(array_unique(array_merge($this->collectUserIdsFromTables($tables), array_keys($settings)))),
				'tables' => array_map('count', $tables),
				'report' => $this->buildRestoreReport('system', $fileName, $tables, $settings, $userMap, []),
			];
		} finally {
			$this->releaseRestoreLock($restoreLock);
		}
	}

	private function getSafetyBackupRetentionCount(string $userId): int {
		return min(self::MAX_RETENTION_COUNT, $this->getRetentionCount($userId) + 1);
	}

	private function acquireRestoreLock(): ?string {
		$now = time();
		$this->deleteStaleRestoreLock($now);
		$lockValue = (string)$now;

		$qb = $this->db->getQueryBuilder();
		try {
			$qb->insert('preferences')
				->values([
					'userid' => $qb->createNamedParameter(self::RESTORE_LOCK_USER),
					'appid' => $qb->createNamedParameter('cobudget'),
					'configkey' => $qb->createNamedParameter(self::RESTORE_LOCK_KEY),
					'configvalue' => $qb->createNamedParameter($lockValue),
				]);
			$qb->executeStatement();

			return $lockValue;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function deleteStaleRestoreLock(int $now): void {
		$staleBefore = (string)($now - self::RESTORE_LOCK_TTL_SECONDS);
		$qb = $this->db->getQueryBuilder();
		$qb->delete('preferences')
			->where($qb->expr()->eq('userid', $qb->createNamedParameter(self::RESTORE_LOCK_USER)))
			->andWhere($qb->expr()->eq('appid', $qb->createNamedParameter('cobudget')))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter(self::RESTORE_LOCK_KEY)))
			->andWhere($qb->expr()->lt('configvalue', $qb->createNamedParameter($staleBefore)));
		$qb->executeStatement();
	}

	private function releaseRestoreLock(string $lockValue): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('preferences')
			->where($qb->expr()->eq('userid', $qb->createNamedParameter(self::RESTORE_LOCK_USER)))
			->andWhere($qb->expr()->eq('appid', $qb->createNamedParameter('cobudget')))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter(self::RESTORE_LOCK_KEY)))
			->andWhere($qb->expr()->eq('configvalue', $qb->createNamedParameter($lockValue)));
		$qb->executeStatement();
	}

	private function writeBackupZip(string $tempFile, string $userId, int $createdAt): void {
		$zip = new \ZipArchive();
		if ($zip->open($tempFile, \ZipArchive::OVERWRITE) !== true) {
			throw new \RuntimeException('Backup-ZIP konnte nicht erstellt werden');
		}

		try {
			$data = $this->collectBackupData($userId);
			$manifest = [
				'schema' => 'cobudget-backup/v1',
				'scope' => 'user',
				'app' => 'cobudget',
				'created_at' => $createdAt,
				'created_at_iso' => gmdate('c', $createdAt),
				'user_id' => $userId,
				'users' => [$userId],
				'includes_attachments' => false,
				'attachment_paths_only' => true,
				'tables' => array_map('count', $data['tables']),
			];

			$this->addJson($zip, 'manifest.json', $manifest);
			$this->addJson($zip, 'settings.json', $data['settings']);
			foreach ($data['tables'] as $table => $rows) {
				$this->addJson($zip, 'data/' . $table . '.json', $rows);
			}
		} finally {
			$zip->close();
		}
	}

	private function writeFullBackupZip(string $tempFile, string $storageUserId, int $createdAt): void {
		$zip = new \ZipArchive();
		if ($zip->open($tempFile, \ZipArchive::OVERWRITE) !== true) {
			throw new \RuntimeException('Backup-ZIP konnte nicht erstellt werden');
		}

		try {
			$data = $this->collectFullBackupData();
			$manifest = [
				'schema' => 'cobudget-backup/v1',
				'scope' => 'system',
				'app' => 'cobudget',
				'created_at' => $createdAt,
				'created_at_iso' => gmdate('c', $createdAt),
				'storage_user_id' => $storageUserId,
				'users' => $data['users'],
				'includes_attachments' => false,
				'attachment_paths_only' => true,
				'tables' => array_map('count', $data['tables']),
			];

			$this->addJson($zip, 'manifest.json', $manifest);
			$this->addJson($zip, 'settings.json', $data['settings']);
			foreach ($data['tables'] as $table => $rows) {
				$this->addJson($zip, 'data/' . $table . '.json', $rows);
			}
		} finally {
			$zip->close();
		}
	}

	private function readBackupArchive(File $file): array {
		if (!class_exists(\ZipArchive::class)) {
			throw new \RuntimeException('ZIP-Unterstützung ist auf diesem Server nicht verfügbar');
		}

		$tempFile = tempnam(sys_get_temp_dir(), 'cobudget-restore-');
		if ($tempFile === false) {
			throw new \RuntimeException('Temporäre Restore-Datei konnte nicht erstellt werden');
		}

		try {
			if (file_put_contents($tempFile, $file->getContent()) === false) {
				throw new \RuntimeException('Backup konnte nicht vorbereitet werden');
			}

			$zip = new \ZipArchive();
			if ($zip->open($tempFile) !== true) {
				throw new \InvalidArgumentException('Backup-ZIP konnte nicht geöffnet werden');
			}

			try {
				$this->assertBackupZipEntries($zip);
				$manifest = $this->readJson($zip, 'manifest.json', true);
				$settings = $this->readJson($zip, 'settings.json', false);
				$tables = [];
				foreach (self::BACKUP_TABLES as $table) {
					$tables[$table] = $this->normalizeTableRows($table, $this->readJson($zip, 'data/' . $table . '.json', false));
				}

				return [
					'manifest' => $manifest,
					'settings' => $settings,
					'tables' => $tables,
				];
			} finally {
				$zip->close();
			}
		} finally {
			if (is_file($tempFile)) {
				@unlink($tempFile);
			}
		}
	}

	private function readJson(\ZipArchive $zip, string $path, bool $required): array {
		if ($zip->locateName($path) === false) {
			if ($required) {
				throw new \InvalidArgumentException('Backup ist unvollständig: ' . $path . ' fehlt');
			}
			return [];
		}

		$content = $zip->getFromName($path);
		if (!is_string($content)) {
			throw new \InvalidArgumentException('Backup ist unvollständig: ' . $path . ' konnte nicht gelesen werden');
		}

		try {
			$data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new \InvalidArgumentException('Backup enthält ungültige JSON-Daten in ' . $path);
		}

		if (!is_array($data)) {
			throw new \InvalidArgumentException('Backup enthält unerwartete Daten in ' . $path);
		}

		return $data;
	}

	private function assertBackupZipEntries(\ZipArchive $zip): void {
		$allowedEntries = [
			'manifest.json' => true,
			'settings.json' => true,
		];
		foreach (self::BACKUP_TABLES as $table) {
			$allowedEntries['data/' . $table . '.json'] = true;
		}

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$stat = $zip->statIndex($i);
			$name = is_array($stat) ? (string)($stat['name'] ?? '') : '';
			if (
				$name === ''
				|| str_starts_with($name, '/')
				|| str_contains($name, '\\')
				|| str_contains($name, '..')
				|| str_ends_with($name, '/')
				|| !isset($allowedEntries[$name])
			) {
				throw new \InvalidArgumentException('Backup-ZIP enthält unerwartete Dateien');
			}
		}
	}

	private function assertBackupArchive(array $archive, string $expectedScope): void {
		$this->assertBackupManifest($archive['manifest'], $expectedScope);
		$this->assertManifestTableCounts($archive['manifest'], $archive['tables']);
	}

	private function assertBackupManifest(array $manifest, string $expectedScope): void {
		if (($manifest['schema'] ?? '') !== 'cobudget-backup/v1' || ($manifest['app'] ?? '') !== 'cobudget') {
			throw new \InvalidArgumentException('Backup gehört nicht zu CoBudget oder nutzt ein unbekanntes Format');
		}
		if (($manifest['scope'] ?? '') !== $expectedScope) {
			throw new \InvalidArgumentException($expectedScope === 'system'
				? 'Dieses Backup ist kein vollständiges CoBudget-Backup'
				: 'Dieses Backup ist kein Benutzer-Backup');
		}
	}

	private function assertManifestTableCounts(array $manifest, array $tables): void {
		$manifestTables = $manifest['tables'] ?? null;
		if (!is_array($manifestTables)) {
			throw new \InvalidArgumentException('Backup enthält keine Tabellenübersicht');
		}

		$knownTables = array_flip(self::BACKUP_TABLES);
		foreach ($manifestTables as $table => $expectedCount) {
			if (!is_string($table) || !isset($knownTables[$table])) {
				throw new \InvalidArgumentException('Backup wurde mit einer neueren oder unbekannten CoBudget-Version erstellt');
			}
			if (!is_int($expectedCount) && !(is_string($expectedCount) && ctype_digit($expectedCount))) {
				throw new \InvalidArgumentException('Backup enthält ungültige Tabellenzähler');
			}
			if ((int)$expectedCount !== count($tables[$table] ?? [])) {
				throw new \InvalidArgumentException('Backup ist beschädigt: Tabellenzähler passt nicht zu ' . $table);
			}
		}
	}

	private function normalizeRows(array $rows): array {
		$normalized = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				throw new \InvalidArgumentException('Backup enthält ungültige Tabellendaten');
			}
			$normalized[] = $row;
		}

		return $normalized;
	}

	private function normalizeTableRows(string $table, array $rows): array {
		$normalized = [];
		foreach ($this->normalizeRows($rows) as $row) {
			$normalized[] = $this->filterBackupRowColumns($table, $row);
		}

		return $normalized;
	}

	private function filterBackupRowColumns(string $table, array $row): array {
		$allowedColumns = self::BACKUP_TABLE_COLUMNS[$table] ?? null;
		if ($allowedColumns === null) {
			throw new \InvalidArgumentException('Backup enthält eine unbekannte Tabelle: ' . $table);
		}

		$allowedColumnMap = array_flip($allowedColumns);
		$filtered = [];
		foreach ($row as $column => $value) {
			if (!is_string($column) || $column === '') {
				throw new \InvalidArgumentException('Backup enthält eine ungültige Spalte');
			}
			if (!isset($allowedColumnMap[$column])) {
				throw new \InvalidArgumentException('Backup enthält eine nicht erlaubte Spalte "' . $column . '" in Tabelle "' . $table . '"');
			}
			$filtered[$column] = $value;
		}

		return $filtered;
	}

	private function normalizeUserSettings(array $settings, array $tables = [], ?string $userId = null): array {
		$validKeys = array_flip(self::SETTINGS_KEYS);
		$normalized = self::SETTINGS_DEFAULTS;
		foreach ($settings as $key => $value) {
			if (!is_string($key) || !isset($validKeys[$key]) || $value === null) {
				continue;
			}
			$normalized[$key] = (string)$value;
		}

		if (
			(!array_key_exists('enable_workspaces', $settings) && $this->backupContainsWorkspaces($tables, $userId))
			|| ((string)($normalized['enable_workspaces'] ?? '') !== 'yes' && $this->backupContainsUserManagedWorkspaces($tables, $userId))
		) {
			$normalized['enable_workspaces'] = 'yes';
		}

		return $normalized;
	}

	private function normalizeFullSettings(array $settings, array $tables = []): array {
		$normalized = [];
		foreach ($settings as $userId => $userSettings) {
			if (!is_string($userId) || $userId === '' || !is_array($userSettings)) {
				continue;
			}
			$normalized[$userId] = $this->normalizeUserSettings($userSettings, $tables, $userId);
		}

		return $normalized;
	}

	private function collectUserIdsFromArchive(array $archive, string $sourceUserId = ''): array {
		$userIds = $this->collectUserIdsFromTables($archive['tables']);
		if ($sourceUserId !== '') {
			$userIds[] = $sourceUserId;
		}
		foreach (($archive['manifest']['users'] ?? []) as $userId) {
			$userId = trim((string)$userId);
			if ($userId !== '') {
				$userIds[] = $userId;
			}
		}
		if (($archive['manifest']['scope'] ?? '') === 'system') {
			foreach (array_keys($this->normalizeFullSettings($archive['settings'])) as $userId) {
				$userIds[] = (string)$userId;
			}
		}

		$userIds = array_values(array_unique($userIds));
		sort($userIds, SORT_STRING);

		return $userIds;
	}

	private function buildBackupUserRows(array $userIds, array $suggestedMap): array {
		$rows = [];
		foreach ($userIds as $userId) {
			$userId = trim((string)$userId);
			if ($userId === '') {
				continue;
			}
			$targetUserId = $suggestedMap[$userId] ?? $userId;
			$rows[] = [
				'id' => $userId,
				'display_name' => $this->displayNameForUser($userId),
				'exists' => $this->userManager->userExists($userId),
				'suggested_target_id' => $targetUserId,
				'suggested_target_display_name' => $this->displayNameForUser($targetUserId),
				'target_exists' => $targetUserId !== '' && $this->userManager->userExists($targetUserId),
				'is_auto_mapped' => isset($suggestedMap[$userId]) && $suggestedMap[$userId] !== $userId,
			];
		}

		return $rows;
	}

	private function displayNameForUser(string $userId): string {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return $userId;
		}

		return trim((string)$user->getDisplayName()) ?: $userId;
	}

	private function normalizeUserMap(array $userMap): array {
		$normalized = [];
		foreach ($userMap as $source => $target) {
			$source = trim((string)$source);
			$target = trim((string)$target);
			if ($source === '' || $target === '') {
				throw new \InvalidArgumentException('User-Mapping muss im Format alt:neu angegeben werden');
			}
			$normalized[$source] = $target;
		}

		return $normalized;
	}

	private function applyUserMapToTables(array $tables, array $userMap): array {
		if ($userMap === []) {
			return $tables;
		}

		foreach ($tables as $table => &$rows) {
			foreach ($rows as &$row) {
				foreach (self::USER_COLUMNS[$table] ?? [] as $column) {
					$value = (string)($row[$column] ?? '');
					if ($value !== '' && isset($userMap[$value])) {
						$row[$column] = $userMap[$value];
					}
				}
			}
			unset($row);
		}
		unset($rows);

		return $tables;
	}

	private function applyUserMapToSettings(array $settings, array $userMap): array {
		if ($userMap === []) {
			return $settings;
		}

		$mapped = [];
		foreach ($settings as $userId => $userSettings) {
			$mapped[$userMap[$userId] ?? $userId] = $userSettings;
		}

		return $mapped;
	}

	private function completeRestoreSettingsForTableUsers(array $settings, array $tables): array {
		foreach ($this->collectUserIdsFromTables($tables) as $userId) {
			if (!isset($settings[$userId])) {
				$settings[$userId] = $this->normalizeUserSettings([], $tables, $userId);
			}
		}

		return $settings;
	}

	private function backupContainsWorkspaces(array $tables, ?string $userId = null): bool {
		foreach ($tables['cobudget_workspaces'] ?? [] as $workspace) {
			if ($userId !== null && (string)($workspace['user_id'] ?? '') !== $userId) {
				continue;
			}
			return true;
		}

		return false;
	}

	private function backupContainsUserManagedWorkspaces(array $tables, ?string $userId = null): bool {
		$count = 0;
		foreach ($tables['cobudget_workspaces'] ?? [] as $workspace) {
			if ($userId !== null && (string)($workspace['user_id'] ?? '') !== $userId) {
				continue;
			}
			$count++;
			if (!(bool)($workspace['is_default'] ?? false)) {
				return true;
			}
		}

		return $count > 1;
	}

	private function filterUserRestoreTables(array $tables, array &$skippedRows = [], ?string $userId = null): array {
		if ($userId !== null) {
			$skippedProjectIds = $this->sharedProjectIdsForUserRestore($tables, $userId);
			if ($skippedProjectIds !== []) {
				$this->removeSkippedProjectRows($tables, $skippedProjectIds, $skippedRows);
			}
		}

		$skippedReferenceIds = [
			'cobudget_categories' => [],
			'cobudget_payment_partners' => [],
		];

		foreach (['cobudget_categories', 'cobudget_payment_partners'] as $table) {
			$skipped = 0;
			$tables[$table] = array_values(array_filter($tables[$table] ?? [], static function (array $row) use (&$skipped, &$skippedReferenceIds, $table): bool {
				$isGlobal = (bool)($row['is_global'] ?? false);
				$keep = !$isGlobal || trim((string)($row['user_id'] ?? '')) !== '';
				if (!$keep) {
					$skipped++;
					$id = (int)($row['id'] ?? 0);
					if ($id > 0) {
						$skippedReferenceIds[$table][] = $id;
					}
				}
				return $keep;
			}));
			if ($skipped > 0) {
				$skippedRows[] = [
					'table' => $table,
					'label' => $this->backupTableLabel($table),
					'count' => $skipped,
					'reason' => 'Globale Einträge ohne Benutzer werden bei einem Benutzer-Restore nicht importiert.',
				];
			}
		}

		$this->clearSkippedUserRestoreReferences($tables, 'category_id', $skippedReferenceIds['cobudget_categories']);
		$this->clearSkippedUserRestoreReferences($tables, 'payment_partner_id', $skippedReferenceIds['cobudget_payment_partners']);

		$entryIds = array_fill_keys($this->ids($tables['cobudget_entries'] ?? []), true);
		$tables['cobudget_entry_hashtags'] = array_values(array_filter($tables['cobudget_entry_hashtags'] ?? [], static function (array $row) use ($entryIds): bool {
			return isset($entryIds[(int)($row['entry_id'] ?? 0)]);
		}));

		$hashtagIds = array_fill_keys($this->idsFromColumn($tables['cobudget_entry_hashtags'] ?? [], 'hashtag_id'), true);
		$tables['cobudget_hashtags'] = array_values(array_filter($tables['cobudget_hashtags'] ?? [], static function (array $row) use ($hashtagIds): bool {
			return isset($hashtagIds[(int)($row['id'] ?? 0)]);
		}));

		return $tables;
	}

	private function sharedProjectIdsForUserRestore(array $tables, string $userId): array {
		$membersByProject = $this->memberUsersByProject($tables);
		$skippedProjectIds = [];

		foreach ($tables['cobudget_projects'] ?? [] as $project) {
			$projectId = (int)($project['id'] ?? 0);
			if ($projectId <= 0) {
				continue;
			}

			if ((string)($project['owner_id'] ?? '') !== $userId) {
				$skippedProjectIds[$projectId] = true;
				continue;
			}

			$memberUsers = array_keys($membersByProject[$projectId] ?? []);
			if (count($memberUsers) > 1) {
				$skippedProjectIds[$projectId] = true;
				continue;
			}

			foreach ($memberUsers as $memberUserId) {
				if ($memberUserId !== $userId) {
					$skippedProjectIds[$projectId] = true;
					break;
				}
			}
		}

		return array_values(array_map('intval', array_keys($skippedProjectIds)));
	}

	private function memberUsersByProject(array $tables): array {
		$membersByProject = [];
		foreach ($tables['cobudget_members'] ?? [] as $member) {
			$projectId = (int)($member['project_id'] ?? 0);
			$memberUserId = trim((string)($member['user_id'] ?? ''));
			if ($projectId <= 0 || $memberUserId === '') {
				continue;
			}
			$membersByProject[$projectId][$memberUserId] = true;
		}

		return $membersByProject;
	}

	private function removeSkippedProjectRows(array &$tables, array $projectIds, array &$skippedRows): void {
		$projectIdMap = array_fill_keys(array_map('intval', $projectIds), true);
		if ($projectIdMap === []) {
			return;
		}

		$reason = 'Geteilte Bereiche werden bei einem Benutzer-Restore nicht überschrieben.';
		$entryIds = $this->idsFromRowsMatching($tables['cobudget_entries'] ?? [], static function (array $row) use ($projectIdMap): bool {
			$projectId = (int)($row['project_id'] ?? 0);
			return $projectId > 0 && isset($projectIdMap[$projectId]);
		});
		$settlementIds = $this->idsFromRowsMatching($tables['cobudget_settlements'] ?? [], static function (array $row) use ($projectIdMap): bool {
			$projectId = (int)($row['project_id'] ?? 0);
			return $projectId > 0 && isset($projectIdMap[$projectId]);
		});

		$this->removeRowsByColumnIds($tables, 'cobudget_projects', 'id', $projectIdMap, $skippedRows, $reason);
		foreach ([
			'cobudget_members',
			'cobudget_categories',
			'cobudget_payment_partners',
			'cobudget_templates',
			'cobudget_entries',
			'cobudget_settlements',
		] as $table) {
			$this->removeRowsByColumnIds($tables, $table, 'project_id', $projectIdMap, $skippedRows, $reason);
		}

		$settlementIdMap = array_fill_keys(array_map('intval', $settlementIds), true);
		if ($settlementIdMap !== []) {
			$this->removeRowsByColumnIds($tables, 'cobudget_settlement_balances', 'settlement_id', $settlementIdMap, $skippedRows, $reason);
			$this->removeRowsByColumnIds($tables, 'cobudget_settlement_transfers', 'settlement_id', $settlementIdMap, $skippedRows, $reason);
		}

		$entryIdMap = array_fill_keys(array_map('intval', $entryIds), true);
		if ($entryIdMap !== []) {
			$this->removeRowsByColumnIds($tables, 'cobudget_entry_history', 'entry_id', $entryIdMap, $skippedRows, $reason);
			$this->removeRowsByColumnIds($tables, 'cobudget_entry_attachments', 'entry_id', $entryIdMap, $skippedRows, $reason);
			$this->removeRowsByColumnIds($tables, 'cobudget_entry_hashtags', 'entry_id', $entryIdMap, $skippedRows, $reason);
		}

		$this->removeSkippedProjectBudgets($tables, $projectIdMap, $skippedRows);
	}

	private function removeRowsByColumnIds(array &$tables, string $table, string $column, array $idMap, array &$skippedRows, string $reason): void {
		if ($idMap === [] || !isset($tables[$table])) {
			return;
		}

		$removed = 0;
		$tables[$table] = array_values(array_filter($tables[$table], function (array $row) use ($column, $idMap, &$removed): bool {
			$value = $this->nullableId($row[$column] ?? null);
			if ($value !== null && isset($idMap[$value])) {
				$removed++;
				return false;
			}

			return true;
		}));

		if ($removed > 0) {
			$skippedRows[] = [
				'table' => $table,
				'label' => $this->backupTableLabel($table),
				'count' => $removed,
				'reason' => $reason,
			];
		}
	}

	private function removeSkippedProjectBudgets(array &$tables, array $projectIdMap, array &$skippedRows): void {
		if ($projectIdMap === []) {
			return;
		}

		$budgetGoalIds = $this->idsFromRowsMatching($tables['cobudget_budget_goals'] ?? [], fn (array $row): bool => $this->criteriaReferencesProject($row, $projectIdMap));
		$budgetGoalIdMap = array_fill_keys(array_map('intval', $budgetGoalIds), true);
		$this->removeRowsByColumnIds(
			$tables,
			'cobudget_budget_goals',
			'id',
			$budgetGoalIdMap,
			$skippedRows,
			'Budgetziele mit übersprungenen gemeinsamen Bereichen wurden nicht importiert.'
		);

		$budgetSnapshotIds = $this->idsFromRowsMatching($tables['cobudget_budget_snapshots'] ?? [], function (array $row) use ($projectIdMap, $budgetGoalIdMap): bool {
			$budgetGoalId = $this->nullableId($row['budget_goal_id'] ?? null);
			return ($budgetGoalId !== null && isset($budgetGoalIdMap[$budgetGoalId]))
				|| $this->criteriaReferencesProject($row, $projectIdMap);
		});
		$budgetSnapshotIdMap = array_fill_keys(array_map('intval', $budgetSnapshotIds), true);
		$this->removeRowsByColumnIds(
			$tables,
			'cobudget_budget_snapshots',
			'id',
			$budgetSnapshotIdMap,
			$skippedRows,
			'Budget-Historie mit übersprungenen gemeinsamen Bereichen wurde nicht importiert.'
		);
	}

	private function criteriaReferencesProject(array $row, array $projectIdMap): bool {
		$criteria = json_decode((string)($row['criteria_json'] ?? '{}'), true);
		if (!is_array($criteria) || !isset($criteria['rules']) || !is_array($criteria['rules'])) {
			return false;
		}

		foreach ($criteria['rules'] as $rule) {
			if (!is_array($rule)) {
				continue;
			}

			$projectId = $this->nullableId($rule['projectId'] ?? ($rule['project_id'] ?? null));
			if ($projectId !== null && isset($projectIdMap[$projectId])) {
				return true;
			}
		}

		return false;
	}

	private function clearSkippedUserRestoreReferences(array &$tables, string $column, array $ids): void {
		$idMap = array_fill_keys(array_map('intval', $ids), true);
		if ($idMap === []) {
			return;
		}

		foreach (['cobudget_entries', 'cobudget_templates'] as $table) {
			foreach ($tables[$table] ?? [] as &$row) {
				$value = $this->nullableId($row[$column] ?? null);
				if ($value !== null && isset($idMap[$value])) {
					$row[$column] = null;
				}
			}
			unset($row);
		}
	}

	private function buildRestoreReport(string $scope, string $fileName, array $tables, array $settings, array $userMap, array $skippedRows): array {
		$tableRows = $this->restoreReportTableRows($tables);
		$settingsRows = $this->restoreReportSettingsRows($settings);
		$attachmentCount = count($tables['cobudget_entry_attachments'] ?? []);

		return [
			'scope' => $scope,
			'file_name' => $fileName,
			'imported_total' => array_sum(array_map(static fn (array $row): int => (int)$row['count'], $tableRows)),
			'imported_tables' => $tableRows,
			'settings_total' => array_sum(array_map(static fn (array $row): int => (int)$row['count'], $settingsRows)),
			'settings' => $settingsRows,
			'user_mappings' => $this->restoreReportUserMappings($userMap),
			'skipped' => array_values($skippedRows),
			'attachment_paths' => [
				'count' => $attachmentCount,
				'files_copied' => false,
				'message' => $attachmentCount > 0
					? 'Beleg-Dateien werden nicht kopiert; importiert werden nur die gespeicherten Dateipfade.'
					: '',
			],
			'workspaces' => [
				'count' => count($tables['cobudget_workspaces'] ?? []),
			],
		];
	}

	private function restoreReportTableRows(array $tables): array {
		$rows = [];
		foreach (self::BACKUP_TABLES as $table) {
			$count = count($tables[$table] ?? []);
			if ($count === 0) {
				continue;
			}
			$rows[] = [
				'table' => $table,
				'label' => $this->backupTableLabel($table),
				'count' => $count,
			];
		}

		return $rows;
	}

	private function restoreReportSettingsRows(array $settings): array {
		$rows = [];
		foreach ($settings as $userId => $userSettings) {
			if (!is_array($userSettings)) {
				continue;
			}
			$userId = (string)$userId;
			$rows[] = [
				'user_id' => $userId,
				'display_name' => $this->displayNameForUser($userId),
				'count' => count($userSettings),
				'keys' => array_values(array_keys($userSettings)),
			];
		}

		usort($rows, static fn (array $a, array $b): int => strcmp((string)$a['user_id'], (string)$b['user_id']));

		return $rows;
	}

	private function restoreReportUserMappings(array $userMap): array {
		$rows = [];
		foreach ($userMap as $sourceUserId => $targetUserId) {
			$sourceUserId = (string)$sourceUserId;
			$targetUserId = (string)$targetUserId;
			$rows[] = [
				'source_user_id' => $sourceUserId,
				'source_display_name' => $this->displayNameForUser($sourceUserId),
				'target_user_id' => $targetUserId,
				'target_display_name' => $this->displayNameForUser($targetUserId),
				'changed' => $sourceUserId !== $targetUserId,
			];
		}

		usort($rows, static fn (array $a, array $b): int => strcmp((string)$a['source_user_id'], (string)$b['source_user_id']));

		return $rows;
	}

	private function backupTableLabel(string $table): string {
		return match ($table) {
			'cobudget_workspaces' => 'Workspaces',
			'cobudget_projects' => 'Bereiche',
			'cobudget_members' => 'Bereichsmitglieder',
			'cobudget_categories' => 'Kategorien',
			'cobudget_payment_partners' => 'Zahlungspartner',
			'cobudget_templates' => 'Vorlagen',
			'cobudget_entries' => 'Zahlungen',
			'cobudget_entry_history' => 'Zahlungshistorie',
			'cobudget_hashtags' => 'Hashtags',
			'cobudget_entry_hashtags' => 'Hashtag-Zuordnungen',
			'cobudget_entry_attachments' => 'Beleg-Pfade',
			'cobudget_settlements' => 'Abrechnungen',
			'cobudget_settlement_balances' => 'Abrechnungssalden',
			'cobudget_settlement_transfers' => 'Rückzahlungen',
			'cobudget_budget_goals' => 'Budgetziele',
			'cobudget_budget_snapshots' => 'Budget-Historie',
			default => $table,
		};
	}

	private function assertBackupInternalReferences(array $tables): void {
		$idMaps = [];
		foreach (self::BACKUP_TABLES as $table) {
			$idMaps[$table] = array_fill_keys($this->ids($tables[$table] ?? []), true);
		}

		foreach (self::BACKUP_INTERNAL_REFERENCES as $reference) {
			$sourceTable = (string)$reference['sourceTable'];
			$column = (string)$reference['column'];
			$targetTable = (string)$reference['targetTable'];
			$targetIds = $idMaps[$targetTable] ?? [];

			foreach ($tables[$sourceTable] ?? [] as $row) {
				$value = $row[$column] ?? null;
				if ($this->nullableId($value) === null) {
					continue;
				}

				$targetId = (int)$value;
				if (!isset($targetIds[$targetId])) {
					$sourceId = (int)($row['id'] ?? 0);
					throw new \InvalidArgumentException(
						'Backup enthält verwaiste Referenzen: '
						. $sourceTable . '.' . $column . ' -> ' . $targetTable
						. ' (Zeile ' . $sourceId . ', Ziel-ID ' . $targetId . ').'
					);
				}
			}
		}
	}

	private function assertProjectMemberConsistency(array $tables): void {
		$projectWorkspaces = [];
		$projectOwners = [];
		foreach ($tables['cobudget_projects'] ?? [] as $project) {
			$projectId = (int)($project['id'] ?? 0);
			if ($projectId <= 0) {
				continue;
			}
			$projectWorkspaces[$projectId] = (int)($project['workspace_id'] ?? 0);
			$projectOwners[$projectId] = trim((string)($project['owner_id'] ?? ''));
		}

		$membersByProject = $this->memberUsersByProject($tables);
		foreach ($projectOwners as $projectId => $ownerId) {
			if ($ownerId !== '' && !isset($membersByProject[$projectId][$ownerId])) {
				throw new \InvalidArgumentException('Backup enthält einen Bereich, dessen Ersteller nicht als Mitglied hinterlegt ist.');
			}
		}

		foreach (['cobudget_categories', 'cobudget_payment_partners', 'cobudget_templates'] as $table) {
			foreach ($tables[$table] ?? [] as $row) {
				$this->assertProjectWorkspaceMatches($row, $projectWorkspaces, $table);
			}
		}

		foreach ($tables['cobudget_entries'] ?? [] as $entry) {
			$projectId = $this->nullableId($entry['project_id'] ?? null);
			if ($projectId === null) {
				continue;
			}
			$this->assertProjectWorkspaceMatches($entry, $projectWorkspaces, 'cobudget_entries');
			$userId = trim((string)($entry['user_id'] ?? ''));
			if ($userId === '' || !isset($membersByProject[$projectId][$userId])) {
				throw new \InvalidArgumentException('Backup enthält eine Bereichszahlung für einen Benutzer, der kein Mitglied des Bereichs ist.');
			}
		}

		$workspaceIds = array_fill_keys($this->ids($tables['cobudget_workspaces'] ?? []), true);
		$entryIds = array_fill_keys($this->ids($tables['cobudget_entries'] ?? []), true);
		foreach ($tables['cobudget_entry_history'] ?? [] as $history) {
			$entryId = (int)($history['entry_id'] ?? 0);
			$workspaceId = (int)($history['workspace_id'] ?? 0);
			if ($entryId <= 0 || !isset($entryIds[$entryId]) || $workspaceId <= 0 || !isset($workspaceIds[$workspaceId])) {
				throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält Zahlungshistorie ohne passende Zahlung im Benutzer-Scope. Bitte verwende ein vollständiges Backup.');
			}
			$projectId = $this->nullableId($history['project_id'] ?? null);
			if ($projectId !== null && !isset($projectWorkspaces[$projectId])) {
				throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält Zahlungshistorie für einen geteilten Bereich außerhalb des Benutzer-Restore-Scopes. Bitte verwende ein vollständiges Backup.');
			}
		}

		$settlementProjects = [];
		foreach ($tables['cobudget_settlements'] ?? [] as $settlement) {
			$settlementId = (int)($settlement['id'] ?? 0);
			$projectId = $this->nullableId($settlement['project_id'] ?? null);
			if ($settlementId <= 0 || $projectId === null) {
				continue;
			}
			$this->assertProjectWorkspaceMatches($settlement, $projectWorkspaces, 'cobudget_settlements');
			$createdBy = trim((string)($settlement['created_by'] ?? ''));
			if ($createdBy !== '' && !isset($membersByProject[$projectId][$createdBy])) {
				throw new \InvalidArgumentException('Backup enthält eine Bereichsabrechnung von einem Benutzer, der kein Mitglied des Bereichs ist.');
			}
			$settlementProjects[$settlementId] = $projectId;
		}

		foreach ($tables['cobudget_settlement_balances'] ?? [] as $balance) {
			$settlementId = (int)($balance['settlement_id'] ?? 0);
			$projectId = $settlementProjects[$settlementId] ?? null;
			if ($projectId === null) {
				continue;
			}
			$userId = trim((string)($balance['user_id'] ?? ''));
			if ($userId === '' || !isset($membersByProject[$projectId][$userId])) {
				throw new \InvalidArgumentException('Backup enthält einen Abrechnungssaldo für einen Benutzer, der kein Mitglied des Bereichs ist.');
			}
		}

		foreach ($tables['cobudget_settlement_transfers'] ?? [] as $transfer) {
			$settlementId = (int)($transfer['settlement_id'] ?? 0);
			$projectId = $settlementProjects[$settlementId] ?? null;
			if ($projectId === null) {
				continue;
			}
			foreach (['from_user_id', 'to_user_id'] as $column) {
				$userId = trim((string)($transfer[$column] ?? ''));
				if ($userId === '' || !isset($membersByProject[$projectId][$userId])) {
					throw new \InvalidArgumentException('Backup enthält eine Rückzahlung für einen Benutzer, der kein Mitglied des Bereichs ist.');
				}
			}
		}
	}

	private function assertProjectWorkspaceMatches(array $row, array $projectWorkspaces, string $table): void {
		$projectId = $this->nullableId($row['project_id'] ?? null);
		if ($projectId === null) {
			return;
		}

		$rowWorkspaceId = $this->nullableId($row['workspace_id'] ?? null);
		$projectWorkspaceId = $projectWorkspaces[$projectId] ?? null;
		if ($rowWorkspaceId !== null && $projectWorkspaceId !== null && $rowWorkspaceId !== $projectWorkspaceId) {
			throw new \InvalidArgumentException('Backup enthält Bereichsdaten mit falschem Workspace in ' . $table . '.');
		}
	}

	private function assertReferencedUsersExist(array $tables, array $settingsUserIds): void {
		$userIds = array_values(array_unique(array_merge($this->collectUserIdsFromTables($tables), $settingsUserIds)));
		sort($userIds, SORT_STRING);
		foreach ($userIds as $userId) {
			if ($userId === '') {
				continue;
			}
			if (!$this->userManager->userExists($userId)) {
				throw new \InvalidArgumentException('Benutzer "' . $userId . '" existiert nicht. Bitte vor dem Restore anlegen oder per OCC --map-user zuordnen.');
			}
		}
	}

	private function assertUserRestoreScope(array $tables, string $userId): void {
		$ownedProjectIds = [];
		foreach ($tables['cobudget_projects'] ?? [] as $project) {
			$projectId = (int)($project['id'] ?? 0);
			if ($projectId <= 0) {
				continue;
			}
			if ((string)($project['owner_id'] ?? '') !== $userId) {
				throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält gemeinsame Bereiche, die nicht diesem Benutzer gehoeren. Bitte verwende ein vollständiges Backup oder stelle den Bereich mit dem Ersteller wieder her.');
			}
			$ownedProjectIds[$projectId] = true;
		}

		$this->assertRowsBelongToUser($tables, 'cobudget_workspaces', 'user_id', $userId);
		$this->assertRowsBelongToUser($tables, 'cobudget_budget_goals', 'user_id', $userId);
		$this->assertRowsBelongToUser($tables, 'cobudget_budget_snapshots', 'user_id', $userId);

		foreach ([
			'cobudget_members',
			'cobudget_categories',
			'cobudget_payment_partners',
			'cobudget_templates',
			'cobudget_settlements',
		] as $table) {
			foreach ($tables[$table] ?? [] as $row) {
				$projectId = $this->nullableId($row['project_id'] ?? null);
				if ($projectId !== null && !isset($ownedProjectIds[$projectId])) {
					throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält Daten aus einem gemeinsamen Bereich ausserhalb dieses Benutzer-Scopes. Bitte verwende ein vollständiges Backup.');
				}
				if ($projectId === null && in_array($table, ['cobudget_categories', 'cobudget_payment_partners', 'cobudget_templates'], true)) {
					$rowUserId = trim((string)($row['user_id'] ?? ''));
					$isGlobal = (bool)($row['is_global'] ?? false);
					if (!$isGlobal && $rowUserId !== $userId) {
						throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält persönliche Daten eines anderen Benutzers. Bitte verwende ein vollständiges Backup.');
					}
					if ($isGlobal && $rowUserId !== '' && $rowUserId !== $userId) {
						throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält globale Daten eines anderen Benutzers. Bitte verwende ein vollständiges Backup.');
					}
				}
			}
		}

		$entryIds = [];
		foreach ($tables['cobudget_entries'] ?? [] as $entry) {
			$entryId = (int)($entry['id'] ?? 0);
			$projectId = $this->nullableId($entry['project_id'] ?? null);
			if ($projectId === null && (string)($entry['user_id'] ?? '') !== $userId) {
				throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält persönliche Zahlungen eines anderen Benutzers. Bitte verwende ein vollständiges Backup.');
			}
			if ($projectId !== null && !isset($ownedProjectIds[$projectId])) {
				throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält Zahlungen aus einem fremden gemeinsamen Bereich. Bitte verwende ein vollständiges Backup.');
			}
			if ($entryId > 0) {
				$entryIds[$entryId] = true;
			}
		}

		$workspaceIds = array_fill_keys($this->ids($tables['cobudget_workspaces'] ?? []), true);
		$hashtagIds = [];
		foreach ($tables['cobudget_hashtags'] ?? [] as $hashtag) {
			$hashtagId = (int)($hashtag['id'] ?? 0);
			$workspaceId = (int)($hashtag['workspace_id'] ?? 0);
			if ($workspaceId <= 0 || !isset($workspaceIds[$workspaceId])) {
				throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält Hashtags ausserhalb des Benutzer-Scopes. Bitte verwende ein vollständiges Backup.');
			}
			if ($hashtagId > 0) {
				$hashtagIds[$hashtagId] = true;
			}
		}

		foreach ($tables['cobudget_entry_hashtags'] ?? [] as $link) {
			$entryId = (int)($link['entry_id'] ?? 0);
			$hashtagId = (int)($link['hashtag_id'] ?? 0);
			$workspaceId = (int)($link['workspace_id'] ?? 0);
			if ($entryId <= 0 || !isset($entryIds[$entryId]) || $hashtagId <= 0 || !isset($hashtagIds[$hashtagId]) || $workspaceId <= 0 || !isset($workspaceIds[$workspaceId])) {
				throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält Hashtag-Zuordnungen ausserhalb des Benutzer-Scopes. Bitte verwende ein vollständiges Backup.');
			}
		}

		foreach ($tables['cobudget_entry_attachments'] ?? [] as $attachment) {
			$entryId = (int)($attachment['entry_id'] ?? 0);
			if ($entryId <= 0 || !isset($entryIds[$entryId])) {
				throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält Beleg-Pfade ohne passende Zahlung im Benutzer-Scope. Bitte verwende ein vollständiges Backup.');
			}
		}

		$settlementIds = [];
		foreach ($tables['cobudget_settlements'] ?? [] as $settlement) {
			$settlementId = (int)($settlement['id'] ?? 0);
			if ($settlementId > 0) {
				$settlementIds[$settlementId] = true;
			}
		}

		foreach (['cobudget_settlement_balances', 'cobudget_settlement_transfers'] as $table) {
			foreach ($tables[$table] ?? [] as $row) {
				$settlementId = (int)($row['settlement_id'] ?? 0);
				if ($settlementId <= 0 || !isset($settlementIds[$settlementId])) {
					throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält Abrechnungsdaten ohne passende Abrechnung im Benutzer-Scope. Bitte verwende ein vollständiges Backup.');
				}
			}
		}
	}

	private function assertRowsBelongToUser(array $tables, string $table, string $column, string $userId): void {
		foreach ($tables[$table] ?? [] as $row) {
			if ((string)($row[$column] ?? '') !== $userId) {
				throw new \InvalidArgumentException('Dieses Benutzer-Backup enthält Daten eines anderen Benutzers. Bitte verwende ein vollständiges Backup.');
			}
		}
	}

	private function deleteRowsByBackupData(array $tables): void {
		foreach (array_reverse(self::BACKUP_TABLES) as $table) {
			$this->deleteRowsByIds($table, $this->ids($tables[$table] ?? []));
		}
	}

	private function deleteRowsByIds(string $table, array $ids): void {
		$ids = array_values(array_unique(array_map('intval', $ids)));
		foreach (array_chunk($ids, 500) as $chunk) {
			if ($chunk === []) {
				continue;
			}
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}
	}

	private function deleteAllBackupTables(): void {
		foreach (array_reverse(self::BACKUP_TABLES) as $table) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($table);
			$qb->executeStatement();
		}
	}

	private function deleteSettingsForUsers(array $userIds): void {
		$userIds = array_values(array_filter(array_unique(array_map('strval', $userIds)), static fn (string $userId): bool => $userId !== ''));
		foreach ($userIds as $userId) {
			foreach (self::SETTINGS_KEYS as $key) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('preferences')
					->where($qb->expr()->eq('appid', $qb->createNamedParameter('cobudget')))
					->andWhere($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
					->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));
				$qb->executeStatement();
			}
		}
	}

	private function deleteAllCoBudgetSettings(): void {
		foreach (self::SETTINGS_KEYS as $key) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('preferences')
				->where($qb->expr()->eq('appid', $qb->createNamedParameter('cobudget')))
				->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));
			$qb->executeStatement();
		}
	}

	private function insertTables(array $tables): void {
		foreach (self::BACKUP_TABLES as $table) {
			foreach ($tables[$table] ?? [] as $row) {
				$this->insertRow($table, $row);
			}
		}
	}

	private function insertRow(string $table, array $row): void {
		$row = $this->filterBackupRowColumns($table, $row);
		if ($row === []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert($table);
		foreach ($row as $column => $value) {
			if (!is_string($column) || $column === '') {
				throw new \InvalidArgumentException('Backup enthält eine ungültige Spalte');
			}
			$qb->setValue($column, $qb->createNamedParameter($value, $this->parameterType($value)));
		}
		$qb->executeStatement();
	}

	private function parameterType(mixed $value): int {
		if ($value === null) {
			return \PDO::PARAM_NULL;
		}
		if (is_bool($value)) {
			return \PDO::PARAM_BOOL;
		}
		if (is_int($value)) {
			return \PDO::PARAM_INT;
		}

		return \PDO::PARAM_STR;
	}

	private function restoreUserSettings(string $userId, array $settings): void {
		foreach ($settings as $key => $value) {
			$this->config->setUserValue($userId, 'cobudget', $key, (string)$value);
		}
	}

	private function restoreAllSettings(array $settings): void {
		foreach ($settings as $userId => $userSettings) {
			$this->restoreUserSettings((string)$userId, $userSettings);
		}
	}

	private function synchronizeAutoincrementSequences(array $tables): void {
		$dbType = strtolower($this->systemValueString('dbtype', ''));
		if ($dbType !== 'pgsql' || !method_exists($this->db, 'executeStatement')) {
			return;
		}

		$prefix = $this->systemValueString('dbtableprefix', 'oc_');
		if (preg_match('/^[A-Za-z0-9_]*$/', $prefix) !== 1) {
			return;
		}

		foreach (self::BACKUP_TABLES as $table) {
			$ids = $this->ids($tables[$table] ?? []);
			if ($ids === []) {
				continue;
			}
			$fullTableName = $prefix . $table;
			try {
				$this->db->executeStatement(
					"SELECT setval(pg_get_serial_sequence('" . $fullTableName . "', 'id'), GREATEST((SELECT COALESCE(MAX(id), 0) FROM " . $fullTableName . "), 1))"
				);
			} catch (\Throwable $e) {
				// Restore itself succeeded. Sequence synchronization is a PostgreSQL compatibility best-effort.
			}
		}
	}

	private function systemValueString(string $key, string $default): string {
		if (method_exists($this->config, 'getSystemValueString')) {
			return (string)$this->config->getSystemValueString($key, $default);
		}

		return (string)$this->config->getSystemValue($key, $default);
	}

	private function collectBackupData(string $userId): array {
		$workspaces = $this->fetchRowsByUser('cobudget_workspaces', $userId);
		$workspaceIds = $this->ids($workspaces);
		$projectIds = $this->fetchProjectIdsForUser($userId, $workspaceIds);
		$entries = $this->fetchEntries($userId, $workspaceIds, $projectIds);
		$entryIds = $this->ids($entries);
		$entryHashtags = $this->fetchRowsByIds('cobudget_entry_hashtags', 'entry_id', $entryIds);
		$hashtagIds = $this->idsFromColumn($entryHashtags, 'hashtag_id');
		$settlements = $this->fetchRowsByIds('cobudget_settlements', 'project_id', $projectIds);
		$settlementIds = $this->ids($settlements);

		return [
			'settings' => $this->fetchSettings($userId),
			'tables' => [
				'cobudget_workspaces' => $workspaces,
				'cobudget_projects' => $this->fetchRowsByIds('cobudget_projects', 'id', $projectIds),
				'cobudget_members' => $this->fetchRowsByIds('cobudget_members', 'project_id', $projectIds),
				'cobudget_categories' => $this->fetchCategories($userId, $workspaceIds, $projectIds),
				'cobudget_payment_partners' => $this->fetchPaymentPartners($userId, $workspaceIds, $projectIds),
				'cobudget_templates' => $this->fetchTemplates($userId, $workspaceIds, $projectIds),
				'cobudget_entries' => $entries,
				'cobudget_entry_history' => $this->fetchRowsByIds('cobudget_entry_history', 'entry_id', $entryIds),
				'cobudget_hashtags' => $this->fetchRowsByIds('cobudget_hashtags', 'id', $hashtagIds),
				'cobudget_entry_hashtags' => $entryHashtags,
				'cobudget_entry_attachments' => $this->fetchRowsByIds('cobudget_entry_attachments', 'entry_id', $entryIds),
				'cobudget_settlements' => $settlements,
				'cobudget_settlement_balances' => $this->fetchRowsByIds('cobudget_settlement_balances', 'settlement_id', $settlementIds),
				'cobudget_settlement_transfers' => $this->fetchRowsByIds('cobudget_settlement_transfers', 'settlement_id', $settlementIds),
				'cobudget_budget_goals' => $this->fetchBudgetGoals($userId, $workspaceIds),
				'cobudget_budget_snapshots' => $this->fetchBudgetSnapshots($userId, $workspaceIds),
			],
		];
	}

	private function collectFullBackupData(): array {
		$tables = [];
		foreach (self::BACKUP_TABLES as $table) {
			$tables[$table] = $this->fetchAllRows($table);
		}

		$storedSettings = $this->fetchStoredSettings();
		$userIds = $this->collectUserIdsFromTables($tables);
		$userIds = array_values(array_unique(array_merge($userIds, array_keys($storedSettings))));
		sort($userIds, SORT_STRING);
		$settings = $this->fetchAllSettings($userIds);

		return [
			'users' => $userIds,
			'settings' => $settings,
			'tables' => $tables,
		];
	}

	private function fetchSettings(string $userId): array {
		$settings = [];
		foreach (self::SETTINGS_KEYS as $key) {
			$settings[$key] = $this->config->getUserValue($userId, 'cobudget', $key, $this->settingsDefaultForUser($userId, $key));
		}

		return $settings;
	}

	private function settingsDefaultForUser(string $userId, string $key): string {
		if ($key === 'enable_workspaces' && $this->userHasManagedWorkspaces($userId)) {
			return 'yes';
		}

		return self::SETTINGS_DEFAULTS[$key] ?? '';
	}

	private function userHasManagedWorkspaces(string $userId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_workspaces')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('is_default', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		try {
			return $result->fetch() !== false;
		} finally {
			$result->closeCursor();
		}
	}

	private function fetchAllSettings(array $userIds = []): array {
		$storedSettings = $this->fetchStoredSettings();
		$userIds = array_values(array_unique(array_merge($userIds, array_keys($storedSettings))));
		sort($userIds, SORT_STRING);

		$settings = [];
		foreach ($userIds as $userId) {
			$settings[$userId] = $this->fetchSettings((string)$userId);
		}

		return $settings;
	}

	private function fetchStoredSettings(): array {
		$validKeys = array_flip(self::SETTINGS_KEYS);
		$qb = $this->db->getQueryBuilder();
		$qb->select('userid', 'configkey', 'configvalue')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('cobudget')))
			->orderBy('userid', 'ASC')
			->addOrderBy('configkey', 'ASC');

		$result = $qb->executeQuery();
		try {
			$settings = [];
			while ($row = $result->fetch()) {
				$key = (string)($row['configkey'] ?? '');
				if (!isset($validKeys[$key])) {
					continue;
				}
				$userId = (string)($row['userid'] ?? '');
				if ($userId === '') {
					continue;
				}
				$settings[$userId][$key] = $row['configvalue'];
			}

			return $settings;
		} finally {
			$result->closeCursor();
		}
	}

	private function fetchProjectIdsForUser(string $userId, array $workspaceIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_projects')
			->where($qb->expr()->eq('owner_id', $qb->createNamedParameter($userId)))
			->orderBy('id', 'ASC');

		return array_values(array_unique(array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN))));
	}

	private function fetchEntries(string $userId, array $workspaceIds, array $projectIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('cobudget_entries');

		$userOwnedRows = $qb->expr()->andX(
			$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
			$qb->expr()->isNull('project_id')
		);
		$or = $qb->expr()->orX($userOwnedRows);
		if ($projectIds !== []) {
			$or->add($qb->expr()->in('project_id', $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY)));
		}
		$qb->where($or)->orderBy('id', 'ASC');

		return $qb->executeQuery()->fetchAll();
	}

	private function fetchCategories(string $userId, array $workspaceIds, array $projectIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('cobudget_categories');
		$or = $qb->expr()->orX(
			$qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->isNull('project_id')
			),
			$qb->expr()->eq('is_global', $qb->createNamedParameter(1, \PDO::PARAM_INT))
		);
		if ($projectIds !== []) {
			$or->add($qb->expr()->in('project_id', $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY)));
		}
		$qb->where($or)->orderBy('id', 'ASC');

		return $qb->executeQuery()->fetchAll();
	}

	private function fetchPaymentPartners(string $userId, array $workspaceIds, array $projectIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('cobudget_payment_partners');
		$or = $qb->expr()->orX(
			$qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->isNull('project_id')
			),
			$qb->expr()->eq('is_global', $qb->createNamedParameter(1, \PDO::PARAM_INT))
		);
		if ($projectIds !== []) {
			$or->add($qb->expr()->in('project_id', $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY)));
		}
		$qb->where($or)->orderBy('id', 'ASC');

		return $qb->executeQuery()->fetchAll();
	}

	private function fetchTemplates(string $userId, array $workspaceIds, array $projectIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('cobudget_templates');
		$or = $qb->expr()->orX($qb->expr()->andX(
			$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
			$qb->expr()->isNull('project_id')
		));
		if ($projectIds !== []) {
			$or->add($qb->expr()->in('project_id', $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY)));
		}
		$qb->where($or)->orderBy('id', 'ASC');

		return $qb->executeQuery()->fetchAll();
	}

	private function fetchBudgetGoals(string $userId, array $workspaceIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('cobudget_budget_goals');
		$qb->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('id', 'ASC');

		return $qb->executeQuery()->fetchAll();
	}

	private function fetchBudgetSnapshots(string $userId, array $workspaceIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('cobudget_budget_snapshots');
		$qb->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('id', 'ASC');

		return $qb->executeQuery()->fetchAll();
	}

	private function fetchRowsByUser(string $table, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($table)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('id', 'ASC');

		return $qb->executeQuery()->fetchAll();
	}

	private function fetchRowsByIds(string $table, string $column, array $ids): array {
		if ($ids === []) {
			return [];
		}
		$ids = array_values(array_unique(array_map('intval', $ids)));
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($table)
			->where($qb->expr()->in($column, $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('id', 'ASC');

		return $qb->executeQuery()->fetchAll();
	}

	private function fetchAllRows(string $table): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($table)
			->orderBy('id', 'ASC');

		return $qb->executeQuery()->fetchAll();
	}

	private function collectUserIdsFromTables(array $tables): array {
		$userIds = [];

		foreach ($tables as $table => $rows) {
			foreach ($rows as $row) {
				foreach (self::USER_COLUMNS[$table] ?? [] as $column) {
					$userId = trim((string)($row[$column] ?? ''));
					if ($userId !== '') {
						$userIds[] = $userId;
					}
				}
			}
		}

		return array_values(array_unique($userIds));
	}

	private function ids(array $rows): array {
		return array_values(array_unique(array_map(static fn (array $row): int => (int)$row['id'], $rows)));
	}

	private function idsFromColumn(array $rows, string $column): array {
		return array_values(array_unique(array_map(static fn (array $row): int => (int)($row[$column] ?? 0), $rows)));
	}

	private function idsFromRowsMatching(array $rows, callable $matches): array {
		$ids = [];
		foreach ($rows as $row) {
			if (!$matches($row)) {
				continue;
			}
			$id = (int)($row['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}

		return array_values(array_unique($ids));
	}

	private function nullableId($value): ?int {
		if ($value === null || $value === '' || (int)$value <= 0) {
			return null;
		}

		return (int)$value;
	}

	private function addJson(\ZipArchive $zip, string $path, array $data): void {
		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new \RuntimeException('Backup-Daten konnten nicht serialisiert werden');
		}
		$zip->addFromString($path, $json . "\n");
	}

	private function getBackupFileFromFolder(string $userId, string $fileName, ?string $folderOverride): File {
		$this->assertBackupFileName($fileName);
		$userFolder = $this->rootFolder->getUserFolder($userId);
		foreach ($this->backupLookupFolders($userId, $folderOverride) as $folderPath) {
			$path = trim($folderPath . '/' . $fileName, '/');
			if (!$userFolder->nodeExists($path)) {
				continue;
			}

			$node = $userFolder->get($path);
			if ($node instanceof File) {
				return $node;
			}
		}

		throw new \RuntimeException('Backup wurde nicht gefunden');
	}

	private function backupLookupFolders(string $userId, ?string $folderOverride = null): array {
		if ($folderOverride !== null) {
			return [$this->normalizeFolder($folderOverride)];
		}

		return [$this->getBackupFolder($userId)];
	}

	private function ensureFolderPath(Folder $root, string $relativePath): Folder {
		$current = $root;
		foreach (array_filter(explode('/', trim($relativePath, '/'))) as $segment) {
			if ($current->nodeExists($segment)) {
				$node = $current->get($segment);
				if (!$node instanceof Folder) {
					throw new \RuntimeException('Backup-Ordner kann nicht erstellt werden');
				}
				$current = $node;
				continue;
			}
			$current = $current->newFolder($segment);
		}

		return $current;
	}

	private function resolveUniqueNameInFolder(Folder $folder, string $fileName): string {
		if (!$folder->nodeExists($fileName)) {
			return $fileName;
		}

		$baseName = pathinfo($fileName, PATHINFO_FILENAME);
		$extension = '.' . pathinfo($fileName, PATHINFO_EXTENSION);
		for ($i = 1; $i < 100; $i++) {
			$candidate = $baseName . '-' . $i . $extension;
			if (!$folder->nodeExists($candidate)) {
				return $candidate;
			}
		}

		throw new \RuntimeException('Eindeutiger Backup-Dateiname konnte nicht erstellt werden');
	}

	private function storeBackupFile(string $storageUserId, string $folderPath, int $retentionCount, string $fileName, string $tempFile, string $prunePattern): array {
		$userFolder = $this->rootFolder->getUserFolder($storageUserId);
		$targetFolder = $this->ensureFolderPath($userFolder, $folderPath);
		$fileName = $this->resolveUniqueNameInFolder($targetFolder, $fileName);
		$file = $targetFolder->newFile($fileName);
		$file->putContent((string)file_get_contents($tempFile));
		$this->pruneBackups($targetFolder, $retentionCount, $prunePattern);

		return $this->formatBackupFile($file, $folderPath);
	}

	private function pruneBackups(Folder $folder, int $retentionCount, string $pattern): void {
		$files = $this->sortedBackupFiles($folder, $pattern);
		foreach (array_slice($files, $retentionCount) as $file) {
			$file->delete();
		}
	}

	/**
	 * @return File[]
	 */
	private function sortedBackupFiles(Folder $folder, string $pattern = self::BACKUP_FILE_PATTERN): array {
		$files = [];
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof File && preg_match($pattern, $node->getName()) === 1) {
				$files[] = $node;
			}
		}
		usort($files, static function (File $a, File $b): int {
			$timeCompare = $b->getMTime() <=> $a->getMTime();
			return $timeCompare !== 0 ? $timeCompare : strcmp($b->getName(), $a->getName());
		});

		return $files;
	}

	private function formatBackupFile(File $file, string $folderPath): array {
		return [
			'file_name' => $file->getName(),
			'file_path' => trim($folderPath . '/' . $file->getName(), '/'),
			'file_size' => $file->getSize(),
			'created_at' => $file->getMTime(),
		];
	}

	private function assertBackupFileName(string $fileName): void {
		if (preg_match(self::BACKUP_FILE_PATTERN, $fileName) !== 1) {
			throw new \InvalidArgumentException('Ungültiger Backup-Dateiname');
		}
	}
}
