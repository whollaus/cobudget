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
	private const DEFAULT_PERSONAL_EXPORT_FOLDER = 'CoBudget/Export';
	private const DEFAULT_FULL_BACKUP_FOLDER = 'CoBudget/Backups';
	private const DEFAULT_RETENTION_COUNT = 7;
	private const DEFAULT_BACKUP_SCHEDULE = 'none';
	private const MAX_RETENTION_COUNT = 100;
	private const FULL_BACKUP_STORAGE_USER_KEY = 'full_backup_storage_user';
	private const FULL_BACKUP_FOLDER_KEY = 'full_backup_storage_folder';
	private const FULL_BACKUP_RETENTION_COUNT_KEY = 'full_backup_retention_count';
	private const FULL_BACKUP_SCHEDULE_KEY = 'full_backup_schedule';
	private const RESTORE_LOCK_USER = '__cobudget_restore__';
	private const RESTORE_LOCK_KEY = 'restore_running_since';
	private const RESTORE_LOCK_TTL_SECONDS = 6 * 60 * 60;
	private const USER_EXPORT_FILE_PATTERN = '/^(?:cobudget-personal-export|cobudget-backup)-\d{8}-\d{6}(?:-\d+)?\.zip$/';
	private const FULL_BACKUP_FILE_PATTERN = '/^cobudget-full-backup-\d{8}-\d{6}(?:-\d+)?\.zip$/';
	private const BACKUP_FILE_PATTERN = '/^(?:cobudget-personal-export|cobudget(?:-full)?-backup)-\d{8}-\d{6}(?:-\d+)?\.zip$/';
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
			'split_user_id',
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
			'split_user_id',
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
		['sourceTable' => 'cobudget_entries', 'column' => 'recurrence_parent_id', 'targetTable' => 'cobudget_entries'],
		['sourceTable' => 'cobudget_entries', 'column' => 'recurrence_series_id', 'targetTable' => 'cobudget_entries'],
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
		'backup_storage_folder' => 'CoBudget/Export',
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
		return $this->normalizePersonalExportFolder(
			$this->config->getUserValue($userId, 'cobudget', 'backup_storage_folder', self::DEFAULT_PERSONAL_EXPORT_FOLDER)
		);
	}

	public function getRetentionCount(string $userId): int {
		return $this->normalizeRetentionCount($this->config->getUserValue($userId, 'cobudget', 'backup_retention_count', (string)self::DEFAULT_RETENTION_COUNT));
	}

	public function getBackupSchedule(string $userId): string {
		return $this->normalizeSchedule($this->config->getUserValue($userId, 'cobudget', 'backup_schedule', self::DEFAULT_BACKUP_SCHEDULE));
	}

	/**
	 * @return array{storage_user_id: string, storage_folder: string, retention_count: int, schedule: string, storage_user_exists: bool}
	 */
	public function getFullBackupSettings(): array {
		$storageUserId = trim($this->config->getAppValue('cobudget', self::FULL_BACKUP_STORAGE_USER_KEY, ''));
		$storageFolder = $this->normalizeFolder($this->config->getAppValue('cobudget', self::FULL_BACKUP_FOLDER_KEY, self::DEFAULT_FULL_BACKUP_FOLDER));
		$retentionCount = $this->normalizeRetentionCount($this->config->getAppValue('cobudget', self::FULL_BACKUP_RETENTION_COUNT_KEY, (string)self::DEFAULT_RETENTION_COUNT));
		$schedule = $this->normalizeSchedule($this->config->getAppValue('cobudget', self::FULL_BACKUP_SCHEDULE_KEY, self::DEFAULT_BACKUP_SCHEDULE));

		return [
			'storage_user_id' => $storageUserId,
			'storage_folder' => $storageFolder,
			'retention_count' => $retentionCount,
			'schedule' => $schedule,
			'storage_user_exists' => $storageUserId !== '' && $this->userManager->userExists($storageUserId),
		];
	}

	/**
	 * @return array{storage_user_id: string, storage_folder: string, retention_count: int, schedule: string, storage_user_exists: bool}
	 */
	public function saveFullBackupSettings(string $storageUserId, string $storageFolder, int|string $retentionCount, string $schedule): array {
		$storageUserId = trim($storageUserId);
		$storageFolder = $this->normalizeFolder($storageFolder);
		$retentionCount = $this->normalizeRetentionCount($retentionCount);
		$schedule = $this->normalizeSchedule($schedule);

		if ($storageUserId === '' && $schedule !== 'none') {
			throw new \InvalidArgumentException('Bitte Speicher-Benutzer angeben.');
		}
		if ($storageUserId !== '' && !$this->userManager->userExists($storageUserId)) {
			throw new \InvalidArgumentException('Speicher-Benutzer wurde nicht gefunden.');
		}

		$this->config->setAppValue('cobudget', self::FULL_BACKUP_STORAGE_USER_KEY, $storageUserId);
		$this->config->setAppValue('cobudget', self::FULL_BACKUP_FOLDER_KEY, $storageFolder);
		$this->config->setAppValue('cobudget', self::FULL_BACKUP_RETENTION_COUNT_KEY, (string)$retentionCount);
		$this->config->setAppValue('cobudget', self::FULL_BACKUP_SCHEDULE_KEY, $schedule);

		return $this->getFullBackupSettings();
	}

	public function createConfiguredFullBackup(): array {
		$settings = $this->getFullBackupSettings();
		$storageUserId = $settings['storage_user_id'];
		if ($storageUserId === '') {
			throw new \InvalidArgumentException('Bitte Speicher-Benutzer angeben.');
		}
		if (!$this->userManager->userExists($storageUserId)) {
			throw new \InvalidArgumentException('Speicher-Benutzer wurde nicht gefunden.');
		}

		return $this->createFullBackup(
			$storageUserId,
			$settings['storage_folder'],
			$settings['retention_count']
		);
	}

	/**
	 * @return array<int, array{file_name: string, file_path: string, file_size: int, created_at: int}>
	 */
	public function listConfiguredFullBackups(): array {
		$settings = $this->getFullBackupSettings();
		$storageUserId = $settings['storage_user_id'];
		if ($storageUserId === '' || !$this->userManager->userExists($storageUserId)) {
			return [];
		}

		$userFolder = $this->rootFolder->getUserFolder($storageUserId);
		$folderPath = $settings['storage_folder'];
		if (!$userFolder->nodeExists($folderPath)) {
			return [];
		}

		$node = $userFolder->get($folderPath);
		if (!$node instanceof Folder) {
			return [];
		}

		$backups = array_map(
			fn(File $file): array => $this->formatBackupFile($file, $folderPath),
			$this->sortedBackupFiles($node, self::FULL_BACKUP_FILE_PATTERN)
		);

		return array_slice($backups, 0, $settings['retention_count']);
	}

	public function getConfiguredFullBackupFile(string $fileName): File {
		$settings = $this->getFullBackupSettings();
		$storageUserId = $settings['storage_user_id'];
		if ($storageUserId === '') {
			throw new \InvalidArgumentException('Bitte Speicher-Benutzer angeben.');
		}
		if (!$this->userManager->userExists($storageUserId)) {
			throw new \InvalidArgumentException('Speicher-Benutzer wurde nicht gefunden.');
		}

		return $this->getBackupFileFromFolder($storageUserId, $fileName, $settings['storage_folder'], self::FULL_BACKUP_FILE_PATTERN);
	}

	public function deleteConfiguredFullBackup(string $fileName): void {
		$this->getConfiguredFullBackupFile($fileName)->delete();
	}

	public function restoreConfiguredFullBackup(string $fileName, string $confirmation): array {
		if ($confirmation !== 'RESTORE') {
			throw new \InvalidArgumentException('Bitte Wiederherstellung mit RESTORE bestätigen.');
		}
		if (preg_match(self::FULL_BACKUP_FILE_PATTERN, $fileName) !== 1) {
			throw new \InvalidArgumentException('Ungültiger Vollbackup-Dateiname');
		}

		$settings = $this->getFullBackupSettings();
		$storageUserId = $settings['storage_user_id'];
		if ($storageUserId === '') {
			throw new \InvalidArgumentException('Bitte Speicher-Benutzer angeben.');
		}
		if (!$this->userManager->userExists($storageUserId)) {
			throw new \InvalidArgumentException('Speicher-Benutzer wurde nicht gefunden.');
		}

		return $this->restoreFullBackup($storageUserId, $fileName, $settings['storage_folder']);
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

	public function normalizePersonalExportFolder(string $folder): string {
		$folder = trim($folder);
		if ($folder === '' || $folder === self::DEFAULT_FULL_BACKUP_FOLDER) {
			return self::DEFAULT_PERSONAL_EXPORT_FOLDER;
		}

		return $this->normalizeFolder($folder);
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
		$fileName = 'cobudget-personal-export-' . date('Ymd-His', $createdAt) . '.zip';
		$tempFile = tempnam(sys_get_temp_dir(), 'cobudget-personal-export-');

		if ($tempFile === false) {
			throw new \RuntimeException('Temporäre Backup-Datei konnte nicht erstellt werden');
		}

		try {
			$this->writeBackupZip($tempFile, $userId, $createdAt);
			return $this->storeBackupFile($userId, $folderPath, $retentionCount, $fileName, $tempFile, self::USER_EXPORT_FILE_PATTERN);
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

		$settings = $this->getFullBackupSettings();
		$folderPath = $folderOverride !== null ? $this->normalizeFolder($folderOverride) : $settings['storage_folder'];
		$retentionCount = $retentionOverride !== null ? $this->normalizeRetentionCount($retentionOverride) : (int)$settings['retention_count'];
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
		$restoreState = $this->personalRestoreState($userId);
		$backups = [];
		foreach ($this->backupLookupFolders($userId) as $folderPath) {
			if (!$userFolder->nodeExists($folderPath)) {
				continue;
			}
			$node = $userFolder->get($folderPath);
			if (!$node instanceof Folder) {
				continue;
			}
			foreach ($this->sortedBackupFiles($node, self::USER_EXPORT_FILE_PATTERN) as $file) {
				$backups[] = $this->formatBackupFile($file, $folderPath) + $restoreState;
			}
		}

		usort($backups, static function (array $a, array $b): int {
			$timeCompare = ((int)($b['created_at'] ?? 0)) <=> ((int)($a['created_at'] ?? 0));
			return $timeCompare !== 0 ? $timeCompare : strcmp((string)($b['file_name'] ?? ''), (string)($a['file_name'] ?? ''));
		});

		return array_slice($backups, 0, $this->getRetentionCount($userId));
	}

	public function personalRestoreState(string $userId): array {
		$blockingTable = $this->personalImportBlockingTable($userId);
		if ($blockingTable === null) {
			return [
				'can_restore' => true,
				'restore_blocked_reason' => '',
				'restore_blocking_table' => '',
			];
		}

		return [
			'can_restore' => false,
			'restore_blocked_reason' => 'Wiederherstellen ist nur möglich, wenn dieser Benutzer noch keine CoBudget-Daten hat. Bitte zuerst alles löschen und zurücksetzen oder einen neuen Benutzer verwenden.',
			'restore_blocking_table' => $blockingTable,
		];
	}

	public function getBackupFile(string $userId, string $fileName): File {
		return $this->getBackupFileFromFolder($userId, $fileName, null, self::USER_EXPORT_FILE_PATTERN);
	}

	public function deleteBackup(string $userId, string $fileName): void {
		$this->getBackupFileFromFolder($userId, $fileName, null, self::USER_EXPORT_FILE_PATTERN)->delete();
	}

	public function inspectBackup(string $userId, string $fileName, ?string $folderOverride = null): array {
		$archive = $this->readBackupArchive($this->getBackupFileFromFolder($userId, $fileName, $folderOverride, self::USER_EXPORT_FILE_PATTERN));
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
		$restoreInfo = $scope === 'user'
			? $this->personalArchiveRestoreInfo($archive, $sourceUserId, $userId)
			: [];

		return [
			'scope' => $scope,
			'file_name' => $fileName,
			'source_user_id' => $sourceUserId,
			'target_user_id' => $userId,
			'users' => $this->buildBackupUserRows($userIds, $userMap),
			'tables' => array_map('count', $archive['tables']),
			'restore' => $restoreInfo,
		];
	}

	public function restoreBackup(string $userId, string $fileName, ?string $folderOverride = null, array $userMap = []): array {
		if ($userMap !== []) {
			throw new \InvalidArgumentException('User-Mapping wird beim persönlichen Import nicht unterstützt.');
		}
		if (!$this->userManager->userExists($userId)) {
			throw new \InvalidArgumentException('Benutzer wurde nicht gefunden.');
		}

		$restoreLock = $this->acquireRestoreLock();
		if ($restoreLock === null) {
			throw new \RuntimeException('Es läuft bereits eine CoBudget-Wiederherstellung. Bitte später erneut versuchen.');
		}

		try {
			$archive = $this->readBackupArchive($this->getBackupFileFromFolder($userId, $fileName, $folderOverride, self::USER_EXPORT_FILE_PATTERN));
			$this->assertBackupArchive($archive, 'user');

			$sourceUserId = trim((string)($archive['manifest']['user_id'] ?? ($archive['manifest']['users'][0] ?? '')));
			if ($sourceUserId === '') {
				throw new \InvalidArgumentException('Persönlicher Export enthält keinen Quellbenutzer.');
			}

			$userMap = $sourceUserId !== $userId ? [$sourceUserId => $userId] : [];
			$tables = $this->preparePersonalImportTables($archive['tables'], $userId, $sourceUserId);
			$settings = [$userId => $this->normalizeUserSettings(
				$this->applyUserMapToSettings([$sourceUserId => is_array($archive['settings']) ? $archive['settings'] : []], $userMap)[$userId] ?? [],
				$tables,
				$userId
			)];

			$this->assertPersonalImportContainsOnlyUser($tables, $userId);
			$this->assertReferencedUsersExist($tables, [$userId]);
			$this->assertBackupInternalReferences($tables);
			$this->assertProjectMemberConsistency($tables);
			$this->assertPersonalImportTargetIsEmpty($userId);

			$safetyBackup = $this->createBackup($userId, null, $this->getSafetyBackupRetentionCount($userId));

			$this->db->beginTransaction();
			try {
				$this->deletePersonalImportTarget($userId);
				$this->insertTablesWithGeneratedIds($tables);
				$this->restoreUserSettings($userId, $settings[$userId]);
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
				'users' => [$userId],
				'tables' => array_map('count', $tables),
				'report' => $this->buildRestoreReport('user', $fileName, $tables, $settings, $userMap, []),
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
			$archive = $this->readBackupArchive($this->getBackupFileFromFolder($storageUserId, $fileName, $folderOverride, self::FULL_BACKUP_FILE_PATTERN));
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
				'type' => 'personal_export',
				'export_mode' => 'personal_share',
				'restore_supported' => true,
				'restore_mode' => 'empty_personal_import',
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
		$normalized['backup_storage_folder'] = $this->normalizePersonalExportFolder(
			(string)($normalized['backup_storage_folder'] ?? self::DEFAULT_PERSONAL_EXPORT_FOLDER)
		);

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

	private function personalArchiveRestoreInfo(array $archive, string $sourceUserId, string $targetUserId): array {
		$userIds = $this->collectUserIdsFromArchive($archive, $sourceUserId);
		$otherUserIds = array_values(array_filter(
			$userIds,
			static fn (string $userId): bool => $sourceUserId !== '' && $userId !== $sourceUserId
		));
		$containsOtherUsers = $otherUserIds !== [];

		return [
			'can_restore_personally' => $sourceUserId !== '',
			'shared_data_restore_mode' => $containsOtherUsers ? 'personal_share' : 'direct',
			'source_user_id' => $sourceUserId,
			'target_user_id' => $targetUserId,
			'will_map_source_user' => $sourceUserId !== '' && $sourceUserId !== $targetUserId,
			'contains_other_users' => $containsOtherUsers,
			'other_users' => $this->buildBackupUserRows($otherUserIds, []),
			'blocked_reason' => '',
		];
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

	private function preparePersonalExportTables(array $tables, string $userId): array {
		return $this->preparePersonalImportTables($tables, $userId, $userId);
	}

	private function preparePersonalImportTables(array $tables, string $userId, ?string $sourceUserId = null): array {
		$sourceUserId = trim((string)($sourceUserId ?? ''));
		if ($sourceUserId === '') {
			$sourceUserId = $userId;
		}
		$prepared = [];
		foreach (self::BACKUP_TABLES as $table) {
			$prepared[$table] = array_values(array_map(
				fn (array $row): array => $this->filterBackupRowColumns($table, $row),
				$this->normalizeRows($tables[$table] ?? [])
			));
		}

		$memberSharesByProject = $this->projectMemberSharesByProject($prepared['cobudget_members'], $sourceUserId);
		$sharedProjectIds = $this->sharedProjectIdsForPersonalImport($prepared['cobudget_projects'], $memberSharesByProject, $sourceUserId);

		$workspaces = [];
		foreach ($prepared['cobudget_workspaces'] as $row) {
			if (trim((string)($row['user_id'] ?? '')) !== $sourceUserId) {
				continue;
			}
			$row['user_id'] = $userId;
			$workspaces[] = $row;
		}
		$prepared['cobudget_workspaces'] = $workspaces;
		$workspaceIds = $this->oldIdSet($workspaces);
		$primaryWorkspaceId = $this->firstOldId($workspaces);

		$projects = [];
		foreach ($prepared['cobudget_projects'] as $row) {
			$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
			if ($workspaceId === null || !isset($workspaceIds[$workspaceId])) {
				continue;
			}
			$projectId = $this->nullableId($row['id'] ?? null);
			if ($projectId !== null && isset($sharedProjectIds[$projectId])) {
				continue;
			}
			$row['owner_id'] = $userId;
			$projects[] = $row;
		}
		$prepared['cobudget_projects'] = $projects;
		$projectIds = $this->oldIdSet($projects);

		$members = [];
		$memberProjectIds = [];
		foreach ($prepared['cobudget_members'] as $row) {
			$projectId = $this->nullableId($row['project_id'] ?? null);
			if ($projectId === null || !isset($projectIds[$projectId]) || isset($memberProjectIds[$projectId])) {
				continue;
			}
			$row['user_id'] = $userId;
			$row['share_basis_points'] = 10000;
			$members[] = $row;
			$memberProjectIds[$projectId] = true;
		}
		$syntheticMemberId = -1;
		foreach ($projects as $project) {
			$projectId = (int)($project['id'] ?? 0);
			if ($projectId <= 0 || isset($memberProjectIds[$projectId])) {
				continue;
			}
			$members[] = [
				'id' => $syntheticMemberId--,
				'project_id' => $projectId,
				'user_id' => $userId,
				'share_basis_points' => 10000,
			];
		}
		$prepared['cobudget_members'] = $members;

		foreach (['cobudget_categories', 'cobudget_payment_partners'] as $table) {
			$rows = [];
			foreach ($prepared[$table] as $row) {
				$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
				$projectId = $this->nullableId($row['project_id'] ?? null);
				if ($projectId !== null && isset($sharedProjectIds[$projectId])) {
					if ($primaryWorkspaceId === null) {
						continue;
					}
					$row['workspace_id'] = $primaryWorkspaceId;
					$row['project_id'] = null;
				} elseif ((bool)($row['is_global'] ?? false)) {
					if ($primaryWorkspaceId === null) {
						continue;
					}
					$row['workspace_id'] = $primaryWorkspaceId;
					$row['project_id'] = null;
				} else {
					if ($workspaceId === null || !isset($workspaceIds[$workspaceId])) {
						continue;
					}
					$row['workspace_id'] = $workspaceId;
					$row['project_id'] = $projectId !== null && isset($projectIds[$projectId]) ? $projectId : null;
				}
				$row['user_id'] = $userId;
				$row['is_global'] = false;
				$rows[] = $row;
			}
			$prepared[$table] = $rows;
		}
		$categoryIds = $this->oldIdSet($prepared['cobudget_categories']);
		$paymentPartnerIds = $this->oldIdSet($prepared['cobudget_payment_partners']);

		$templates = [];
		foreach ($prepared['cobudget_templates'] as $row) {
			if (trim((string)($row['user_id'] ?? '')) !== $sourceUserId) {
				continue;
			}
			$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
			if ($workspaceId === null || !isset($workspaceIds[$workspaceId])) {
				continue;
			}
			$projectId = $this->nullableId($row['project_id'] ?? null);
			$categoryId = $this->nullableId($row['category_id'] ?? null);
			$paymentPartnerId = $this->nullableId($row['payment_partner_id'] ?? null);
			$row['user_id'] = $userId;
			$row['workspace_id'] = $workspaceId;
			$row['project_id'] = $projectId !== null && isset($projectIds[$projectId]) ? $projectId : null;
			if ($projectId !== null && isset($sharedProjectIds[$projectId])) {
				$row['project_id'] = null;
			}
			$row['category_id'] = $categoryId !== null && isset($categoryIds[$categoryId]) ? $categoryId : null;
			$row['payment_partner_id'] = $paymentPartnerId !== null && isset($paymentPartnerIds[$paymentPartnerId]) ? $paymentPartnerId : null;
			if ($row['project_id'] === null) {
				$row['split_mode'] = 'personal';
				$row['split_user_id'] = null;
			}
			$templates[] = $row;
		}
		$prepared['cobudget_templates'] = $templates;

		$entries = [];
		$convertedSharedEntryIds = [];
		foreach ($prepared['cobudget_entries'] as $row) {
			$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
			$entryUserId = trim((string)($row['user_id'] ?? ''));
			$projectId = $this->nullableId($row['project_id'] ?? null);
			$categoryId = $this->nullableId($row['category_id'] ?? null);
			$paymentPartnerId = $this->nullableId($row['payment_partner_id'] ?? null);
			$isSharedProjectEntry = $projectId !== null && isset($sharedProjectIds[$projectId]);

			if ($isSharedProjectEntry) {
				if ($primaryWorkspaceId === null) {
					continue;
				}
				$shareCents = $this->personalImportEntryShareCents($row, $sourceUserId, $memberSharesByProject);
				if ($shareCents === null || $shareCents <= 0) {
					continue;
				}
				$oldEntryId = $this->nullableId($row['id'] ?? null);
				if ($oldEntryId !== null) {
					$convertedSharedEntryIds[$oldEntryId] = true;
				}
				$row['amount_cents'] = $shareCents;
				$row['amount'] = $this->decimalAmountFromCents($shareCents);
				$row['project_id'] = null;
				$row['split_mode'] = 'personal';
				$row['split_user_id'] = null;
				$row['recurrence_interval'] = null;
				$row['recurrence_multiplier'] = null;
				$row['recurrence_next_date'] = null;
				$row['recurrence_end_date'] = null;
				$row['recurrence_parent_id'] = null;
				$row['recurrence_series_id'] = null;
				$workspaceId = $primaryWorkspaceId;
			} else {
				if ($workspaceId === null || !isset($workspaceIds[$workspaceId]) || $entryUserId !== $sourceUserId) {
					continue;
				}
			}

			$row['user_id'] = $userId;
			$row['workspace_id'] = $workspaceId;
			if (!$isSharedProjectEntry) {
				$row['project_id'] = $projectId !== null && isset($projectIds[$projectId]) ? $projectId : null;
			}
			$row['category_id'] = $categoryId !== null && isset($categoryIds[$categoryId]) ? $categoryId : null;
			$row['payment_partner_id'] = $paymentPartnerId !== null && isset($paymentPartnerIds[$paymentPartnerId]) ? $paymentPartnerId : null;
			if ($row['project_id'] === null) {
				$row['split_mode'] = 'personal';
				$row['split_user_id'] = null;
			}
			$row['is_settled'] = false;
			$row['settled_at'] = null;
			$row['settlement_id'] = null;
			$entries[] = $row;
		}
		$prepared['cobudget_entries'] = $entries;
		$entryIds = $this->oldIdSet($entries);

		$history = [];
		foreach ($prepared['cobudget_entry_history'] as $row) {
			$entryId = $this->nullableId($row['entry_id'] ?? null);
			$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
			if (
				$entryId === null || !isset($entryIds[$entryId]) || isset($convertedSharedEntryIds[$entryId])
				|| $workspaceId === null || !isset($workspaceIds[$workspaceId])
			) {
				continue;
			}
			$projectId = $this->nullableId($row['project_id'] ?? null);
			$row['entry_id'] = $entryId;
			$row['workspace_id'] = $workspaceId;
			$row['project_id'] = $projectId !== null && isset($projectIds[$projectId]) ? $projectId : null;
			$row['changed_by'] = $userId;
			$row['changed_by_display_name'] = $this->displayNameForUser($userId);
			$history[] = $row;
		}
		$prepared['cobudget_entry_history'] = $history;

		$hashtags = [];
		foreach ($prepared['cobudget_hashtags'] as $row) {
			$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
			if ($workspaceId === null || !isset($workspaceIds[$workspaceId])) {
				if ($primaryWorkspaceId === null) {
					continue;
				}
				$workspaceId = $primaryWorkspaceId;
			}
			$row['workspace_id'] = $workspaceId;
			$hashtags[] = $row;
		}
		$prepared['cobudget_hashtags'] = $hashtags;
		$hashtagIds = $this->oldIdSet($hashtags);

		$entryHashtags = [];
		foreach ($prepared['cobudget_entry_hashtags'] as $row) {
			$entryId = $this->nullableId($row['entry_id'] ?? null);
			$hashtagId = $this->nullableId($row['hashtag_id'] ?? null);
			$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
			if (
				$entryId === null || !isset($entryIds[$entryId])
				|| $hashtagId === null || !isset($hashtagIds[$hashtagId])
				|| $workspaceId === null
			) {
				continue;
			}
			if (!isset($workspaceIds[$workspaceId])) {
				if ($primaryWorkspaceId === null) {
					continue;
				}
				$workspaceId = $primaryWorkspaceId;
			}
			$row['entry_id'] = $entryId;
			$row['hashtag_id'] = $hashtagId;
			$row['workspace_id'] = $workspaceId;
			$entryHashtags[] = $row;
		}
		$prepared['cobudget_entry_hashtags'] = $entryHashtags;

		$attachments = [];
		foreach ($prepared['cobudget_entry_attachments'] as $row) {
			$entryId = $this->nullableId($row['entry_id'] ?? null);
			$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
			if (
				$entryId === null || !isset($entryIds[$entryId]) || isset($convertedSharedEntryIds[$entryId])
				|| $workspaceId === null || !isset($workspaceIds[$workspaceId])
			) {
				continue;
			}
			$row['entry_id'] = $entryId;
			$row['workspace_id'] = $workspaceId;
			$row['owner_user_id'] = $userId;
			$attachments[] = $row;
		}
		$prepared['cobudget_entry_attachments'] = $attachments;

		$prepared['cobudget_settlements'] = [];
		$prepared['cobudget_settlement_balances'] = [];
		$prepared['cobudget_settlement_transfers'] = [];

		$budgetGoals = [];
		foreach ($prepared['cobudget_budget_goals'] as $row) {
			if (trim((string)($row['user_id'] ?? '')) !== $sourceUserId) {
				continue;
			}
			$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
			if ($workspaceId === null || !isset($workspaceIds[$workspaceId])) {
				continue;
			}
			$row['user_id'] = $userId;
			$row['workspace_id'] = $workspaceId;
			$row['criteria_json'] = $this->sanitizeBudgetCriteriaJson($row['criteria_json'] ?? '{}', $projectIds, $categoryIds);
			$budgetGoals[] = $row;
		}
		$prepared['cobudget_budget_goals'] = $budgetGoals;
		$budgetGoalIds = $this->oldIdSet($budgetGoals);

		$budgetSnapshots = [];
		foreach ($prepared['cobudget_budget_snapshots'] as $row) {
			$budgetGoalId = $this->nullableId($row['budget_goal_id'] ?? null);
			$workspaceId = $this->nullableId($row['workspace_id'] ?? null);
			if (
				$budgetGoalId === null || !isset($budgetGoalIds[$budgetGoalId])
				|| $workspaceId === null || !isset($workspaceIds[$workspaceId])
			) {
				continue;
			}
			$row['user_id'] = $userId;
			$row['workspace_id'] = $workspaceId;
			$row['budget_goal_id'] = $budgetGoalId;
			$row['criteria_json'] = $this->sanitizeBudgetCriteriaJson($row['criteria_json'] ?? '{}', $projectIds, $categoryIds);
			$budgetSnapshots[] = $row;
		}
		$prepared['cobudget_budget_snapshots'] = $budgetSnapshots;

		return $prepared;
	}

	private function projectMemberSharesByProject(array $memberRows, string $sourceUserId): array {
		$sharesByProject = [];
		foreach ($memberRows as $row) {
			$projectId = $this->nullableId($row['project_id'] ?? null);
			$memberUserId = trim((string)($row['user_id'] ?? ''));
			if ($projectId === null || $memberUserId === '') {
				continue;
			}
			$sharesByProject[$projectId][$memberUserId] = max(0, (int)($row['share_basis_points'] ?? 0));
		}

		foreach ($sharesByProject as $projectId => $shares) {
			if (!array_key_exists($sourceUserId, $shares)) {
				continue;
			}
			$totalShare = array_sum($shares);
			if ($totalShare > 0) {
				continue;
			}
			$equalShare = intdiv(10000, max(1, count($shares)));
			foreach (array_keys($shares) as $memberUserId) {
				$sharesByProject[$projectId][$memberUserId] = $equalShare;
			}
		}

		return $sharesByProject;
	}

	private function sharedProjectIdsForPersonalImport(array $projectRows, array $memberSharesByProject, string $sourceUserId): array {
		$sharedProjectIds = [];
		foreach ($projectRows as $row) {
			$projectId = $this->nullableId($row['id'] ?? null);
			if ($projectId === null) {
				continue;
			}
			$members = array_keys($memberSharesByProject[$projectId] ?? []);
			$foreignMembers = array_values(array_filter(
				$members,
				static fn (string $memberUserId): bool => $memberUserId !== $sourceUserId
			));
			$ownerId = trim((string)($row['owner_id'] ?? ''));
			if (count($members) > 1 || $foreignMembers !== [] || ($ownerId !== '' && $ownerId !== $sourceUserId)) {
				$sharedProjectIds[$projectId] = true;
			}
		}

		return $sharedProjectIds;
	}

	private function personalImportEntryShareCents(array $entry, string $sourceUserId, array $memberSharesByProject): ?int {
		$amountCents = $this->backupAmountCents($entry);
		if ($amountCents <= 0) {
			return null;
		}

		$entryUserId = trim((string)($entry['user_id'] ?? ''));
		$projectId = $this->nullableId($entry['project_id'] ?? null);
		if ($projectId === null) {
			return $entryUserId === $sourceUserId ? $amountCents : null;
		}

		if ((string)($entry['split_mode'] ?? '') === 'single_user') {
			$splitTargetUserId = trim((string)($entry['split_user_id'] ?? ''));
			if ($splitTargetUserId === '') {
				$splitTargetUserId = $entryUserId;
			}
			return $splitTargetUserId === $sourceUserId ? $amountCents : null;
		}

		$shares = $memberSharesByProject[$projectId] ?? [];
		if (!array_key_exists($sourceUserId, $shares)) {
			return null;
		}

		$sourceShare = max(0, (int)$shares[$sourceUserId]);
		$totalShare = array_sum(array_map(static fn ($share): int => max(0, (int)$share), $shares));
		if ($sourceShare <= 0 || $totalShare <= 0) {
			return null;
		}

		return $this->roundPersonalImportShareCents($amountCents, $sourceShare, $totalShare);
	}

	private function roundPersonalImportShareCents(int $amountCents, int $share, int $totalShare): int {
		if ($amountCents <= 0 || $share <= 0 || $totalShare <= 0) {
			return 0;
		}

		return intdiv(($amountCents * $share * 2) + $totalShare, $totalShare * 2);
	}

	private function backupAmountCents(array $row): int {
		if (isset($row['amount_cents']) && is_numeric($row['amount_cents'])) {
			return abs((int)$row['amount_cents']);
		}

		return abs((int)round(((float)($row['amount'] ?? 0)) * 100));
	}

	private function decimalAmountFromCents(int $amountCents): string {
		return number_format($amountCents / 100, 2, '.', '');
	}

	private function sanitizeBudgetCriteriaJson(mixed $criteriaJson, array $projectIds, array $categoryIds): string {
		$criteria = json_decode((string)($criteriaJson ?: '{}'), true);
		if (!is_array($criteria)) {
			return json_encode(['rules' => []]) ?: '{"rules":[]}';
		}

		$rules = [];
		$sourceRules = isset($criteria['rules']) && is_array($criteria['rules']) ? $criteria['rules'] : [$criteria];
		foreach ($sourceRules as $rule) {
			if (!is_array($rule)) {
				continue;
			}
			$projectId = $this->nullableId($rule['projectId'] ?? $rule['project_id'] ?? null);
			$categoryId = $this->nullableId($rule['categoryId'] ?? $rule['category_id'] ?? null);
			$tag = trim((string)($rule['tag'] ?? ''));
			$projectId = $projectId !== null && isset($projectIds[$projectId]) ? $projectId : null;
			$categoryId = $categoryId !== null && isset($categoryIds[$categoryId]) ? $categoryId : null;
			if ($projectId === null && $categoryId === null && $tag === '') {
				continue;
			}
			$rules[] = [
				'projectId' => $projectId,
				'categoryId' => $categoryId,
				'tag' => $tag,
			];
		}

		return json_encode(['rules' => array_values($rules)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"rules":[]}';
	}

	private function assertPersonalImportContainsOnlyUser(array $tables, string $userId): void {
		foreach (self::USER_COLUMNS as $table => $columns) {
			foreach ($tables[$table] ?? [] as $row) {
				foreach ($columns as $column) {
					$value = trim((string)($row[$column] ?? ''));
					if ($value !== '' && $value !== $userId) {
						throw new \InvalidArgumentException('Persönlicher Import enthält Daten eines anderen Benutzers.');
					}
				}
			}
		}
	}

	private function assertPersonalImportTargetIsEmpty(string $userId): void {
		$blockingTable = $this->personalImportBlockingTable($userId);
		if ($blockingTable !== null) {
			throw new \RuntimeException('Persönlicher Import ist nur möglich, wenn dieser Benutzer noch keine CoBudget-Daten hat. Bitte zuerst zurücksetzen oder einen neuen Benutzer verwenden.');
		}
	}

	private function personalImportBlockingTable(string $userId): ?string {
		foreach (self::USER_COLUMNS as $table => $columns) {
			// A first app open or reset creates an empty default workspace. Real user data
			// in the remaining tables still blocks a personal import.
			if ($table === 'cobudget_workspaces') {
				continue;
			}
			if ($this->countRowsWhereUserColumns($table, $columns, $userId) > 0) {
				return $table;
			}
		}

		return null;
	}

	private function deletePersonalImportTarget(string $userId): void {
		foreach (array_reverse(self::BACKUP_TABLES) as $table) {
			$columns = self::USER_COLUMNS[$table] ?? [];
			if ($columns === []) {
				continue;
			}
			$this->deleteRowsWhereUserColumns($table, $columns, $userId);
		}
		$this->deleteSettingsForUsers([$userId]);
	}

	private function insertTablesWithGeneratedIds(array $tables): void {
		$idMaps = array_fill_keys(self::BACKUP_TABLES, []);
		$pendingEntryReferences = [];

		foreach (self::BACKUP_TABLES as $table) {
			foreach ($tables[$table] ?? [] as $row) {
				$oldId = $this->nullableId($row['id'] ?? null);
				$oldParentId = $table === 'cobudget_entries' ? $this->nullableId($row['recurrence_parent_id'] ?? null) : null;
				$oldSeriesId = $table === 'cobudget_entries' ? $this->nullableId($row['recurrence_series_id'] ?? null) : null;
				$row = $this->remapGeneratedRowReferences($table, $row, $idMaps);
				if ($table === 'cobudget_entries') {
					$row['recurrence_parent_id'] = null;
					$row['recurrence_series_id'] = null;
				}

				$newId = $this->insertRowGenerated($table, $row);
				if ($oldId !== null && $newId > 0) {
					$idMaps[$table][$oldId] = $newId;
				}
				if ($table === 'cobudget_entries' && $newId > 0 && ($oldParentId !== null || $oldSeriesId !== null)) {
					$pendingEntryReferences[] = [
						'new_id' => $newId,
						'old_parent_id' => $oldParentId,
						'old_series_id' => $oldSeriesId,
					];
				}
			}
		}

		$entryMap = $idMaps['cobudget_entries'];
		foreach ($pendingEntryReferences as $reference) {
			$parentId = $reference['old_parent_id'] !== null ? ($entryMap[$reference['old_parent_id']] ?? null) : null;
			$seriesId = $reference['old_series_id'] !== null ? ($entryMap[$reference['old_series_id']] ?? null) : null;
			if ($parentId === null && $seriesId === null) {
				continue;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('cobudget_entries')
				->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$reference['new_id'], \PDO::PARAM_INT)));
			if ($parentId !== null) {
				$qb->set('recurrence_parent_id', $qb->createNamedParameter((int)$parentId, \PDO::PARAM_INT));
			}
			if ($seriesId !== null) {
				$qb->set('recurrence_series_id', $qb->createNamedParameter((int)$seriesId, \PDO::PARAM_INT));
			}
			$qb->executeStatement();
		}
	}

	private function remapGeneratedRowReferences(string $table, array $row, array $idMaps): array {
		$row = $this->filterBackupRowColumns($table, $row);
		unset($row['id']);

		$mapColumn = function (string $column, string $targetTable) use (&$row, $idMaps): void {
			if (!array_key_exists($column, $row)) {
				return;
			}
			$oldId = $this->nullableId($row[$column]);
			$row[$column] = $oldId !== null ? ($idMaps[$targetTable][$oldId] ?? null) : null;
		};

		match ($table) {
			'cobudget_projects' => $mapColumn('workspace_id', 'cobudget_workspaces'),
			'cobudget_members' => $mapColumn('project_id', 'cobudget_projects'),
			'cobudget_categories', 'cobudget_payment_partners' => (function () use ($mapColumn): void {
				$mapColumn('workspace_id', 'cobudget_workspaces');
				$mapColumn('project_id', 'cobudget_projects');
			})(),
			'cobudget_templates' => (function () use ($mapColumn): void {
				$mapColumn('workspace_id', 'cobudget_workspaces');
				$mapColumn('category_id', 'cobudget_categories');
				$mapColumn('payment_partner_id', 'cobudget_payment_partners');
				$mapColumn('project_id', 'cobudget_projects');
			})(),
			'cobudget_entries' => (function () use ($mapColumn): void {
				$mapColumn('workspace_id', 'cobudget_workspaces');
				$mapColumn('category_id', 'cobudget_categories');
				$mapColumn('payment_partner_id', 'cobudget_payment_partners');
				$mapColumn('project_id', 'cobudget_projects');
				$mapColumn('settlement_id', 'cobudget_settlements');
			})(),
			'cobudget_entry_history' => (function () use ($mapColumn): void {
				$mapColumn('entry_id', 'cobudget_entries');
				$mapColumn('workspace_id', 'cobudget_workspaces');
				$mapColumn('project_id', 'cobudget_projects');
			})(),
			'cobudget_hashtags' => $mapColumn('workspace_id', 'cobudget_workspaces'),
			'cobudget_entry_hashtags' => (function () use ($mapColumn): void {
				$mapColumn('entry_id', 'cobudget_entries');
				$mapColumn('hashtag_id', 'cobudget_hashtags');
				$mapColumn('workspace_id', 'cobudget_workspaces');
			})(),
			'cobudget_entry_attachments' => (function () use ($mapColumn): void {
				$mapColumn('entry_id', 'cobudget_entries');
				$mapColumn('workspace_id', 'cobudget_workspaces');
			})(),
			'cobudget_settlements' => (function () use ($mapColumn): void {
				$mapColumn('project_id', 'cobudget_projects');
				$mapColumn('workspace_id', 'cobudget_workspaces');
			})(),
			'cobudget_settlement_balances', 'cobudget_settlement_transfers' => $mapColumn('settlement_id', 'cobudget_settlements'),
			'cobudget_budget_goals' => (function () use (&$row, $mapColumn, $idMaps): void {
				$mapColumn('workspace_id', 'cobudget_workspaces');
				$row['criteria_json'] = $this->remapBudgetCriteriaJson($row['criteria_json'] ?? '{}', $idMaps['cobudget_projects'], $idMaps['cobudget_categories']);
			})(),
			'cobudget_budget_snapshots' => (function () use (&$row, $mapColumn, $idMaps): void {
				$mapColumn('budget_goal_id', 'cobudget_budget_goals');
				$mapColumn('workspace_id', 'cobudget_workspaces');
				$row['criteria_json'] = $this->remapBudgetCriteriaJson($row['criteria_json'] ?? '{}', $idMaps['cobudget_projects'], $idMaps['cobudget_categories']);
			})(),
			default => null,
		};

		return $row;
	}

	private function remapBudgetCriteriaJson(mixed $criteriaJson, array $projectIdMap, array $categoryIdMap): string {
		$criteria = json_decode((string)($criteriaJson ?: '{}'), true);
		if (!is_array($criteria)) {
			return '{"rules":[]}';
		}

		$rules = [];
		foreach (($criteria['rules'] ?? []) as $rule) {
			if (!is_array($rule)) {
				continue;
			}
			$projectId = $this->nullableId($rule['projectId'] ?? $rule['project_id'] ?? null);
			$categoryId = $this->nullableId($rule['categoryId'] ?? $rule['category_id'] ?? null);
			$tag = trim((string)($rule['tag'] ?? ''));
			$projectId = $projectId !== null ? ($projectIdMap[$projectId] ?? null) : null;
			$categoryId = $categoryId !== null ? ($categoryIdMap[$categoryId] ?? null) : null;
			if ($projectId === null && $categoryId === null && $tag === '') {
				continue;
			}
			$rules[] = [
				'projectId' => $projectId,
				'categoryId' => $categoryId,
				'tag' => $tag,
			];
		}

		return json_encode(['rules' => array_values($rules)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"rules":[]}';
	}

	private function insertRowGenerated(string $table, array $row): int {
		$row = $this->filterBackupRowColumns($table, $row);
		unset($row['id']);
		if ($row === []) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert($table);
		foreach ($row as $column => $value) {
			$qb->setValue($column, $qb->createNamedParameter($value, $this->parameterType($value)));
		}
		$qb->executeStatement();

		return $this->latestInsertedId($table);
	}

	private function latestInsertedId(string $table): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($table)
			->orderBy('id', 'DESC')
			->setMaxResults(1);

		return (int)$qb->executeQuery()->fetchOne();
	}

	private function countRowsWhereUserColumns(string $table, array $columns, string $userId): int {
		$columns = array_values(array_filter($columns, static fn (string $column): bool => $column !== ''));
		if ($columns === []) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$or = $qb->expr()->orX();
		foreach ($columns as $column) {
			$or->add($qb->expr()->eq($column, $qb->createNamedParameter($userId)));
		}
		$qb->selectAlias($qb->func()->count('*'), 'count')
			->from($table)
			->where($or);

		return (int)$qb->executeQuery()->fetchOne();
	}

	private function deleteRowsWhereUserColumns(string $table, array $columns, string $userId): void {
		$columns = array_values(array_filter($columns, static fn (string $column): bool => $column !== ''));
		if ($columns === []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$or = $qb->expr()->orX();
		foreach ($columns as $column) {
			$or->add($qb->expr()->eq($column, $qb->createNamedParameter($userId)));
		}
		$qb->delete($table)->where($or);
		$qb->executeStatement();
	}

	private function oldIdSet(array $rows): array {
		$ids = [];
		foreach ($rows as $row) {
			$id = $this->nullableId($row['id'] ?? null);
			if ($id !== null) {
				$ids[$id] = true;
			}
		}

		return $ids;
	}

	private function firstOldId(array $rows): ?int {
		foreach ($rows as $row) {
			$id = $this->nullableId($row['id'] ?? null);
			if ($id !== null) {
				return $id;
			}
		}

		return null;
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
					throw new \InvalidArgumentException('Backup enthält Zahlungshistorie ohne passende Zahlung oder Workspace.');
				}
				$projectId = $this->nullableId($history['project_id'] ?? null);
				if ($projectId !== null && !isset($projectWorkspaces[$projectId])) {
					throw new \InvalidArgumentException('Backup enthält Zahlungshistorie für einen unbekannten Bereich.');
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
		$ownedWorkspaces = $this->fetchRowsByUser('cobudget_workspaces', $userId);
		$projectIds = $this->fetchProjectIdsForUser($userId);
		$projects = $this->fetchRowsByIds('cobudget_projects', 'id', $projectIds);
		$workspaceIds = array_values(array_unique(array_merge(
			$this->ids($ownedWorkspaces),
			$this->idsFromColumn($projects, 'workspace_id')
		)));
		$workspaces = $this->fetchRowsByIds('cobudget_workspaces', 'id', $workspaceIds);
		$entries = $this->fetchEntries($userId, $workspaceIds, $projectIds);
		$entryIds = $this->ids($entries);
		$entryHashtags = $this->fetchRowsByIds('cobudget_entry_hashtags', 'entry_id', $entryIds);
		$hashtagIds = $this->idsFromColumn($entryHashtags, 'hashtag_id');

		$tables = [
			'cobudget_workspaces' => $workspaces,
			'cobudget_projects' => $projects,
			'cobudget_members' => $this->fetchRowsByIds('cobudget_members', 'project_id', $projectIds),
			'cobudget_categories' => $this->fetchCategories($userId, $workspaceIds, $projectIds),
			'cobudget_payment_partners' => $this->fetchPaymentPartners($userId, $workspaceIds, $projectIds),
			'cobudget_templates' => $this->fetchTemplates($userId, $workspaceIds, $projectIds),
			'cobudget_entries' => $entries,
			'cobudget_entry_history' => $this->fetchRowsByIds('cobudget_entry_history', 'entry_id', $entryIds),
			'cobudget_hashtags' => $this->fetchRowsByIds('cobudget_hashtags', 'id', $hashtagIds),
			'cobudget_entry_hashtags' => $entryHashtags,
			'cobudget_entry_attachments' => $this->fetchRowsByIds('cobudget_entry_attachments', 'entry_id', $entryIds),
			'cobudget_settlements' => [],
			'cobudget_settlement_balances' => [],
			'cobudget_settlement_transfers' => [],
			'cobudget_budget_goals' => $this->fetchBudgetGoals($userId, $workspaceIds),
			'cobudget_budget_snapshots' => $this->fetchBudgetSnapshots($userId, $workspaceIds),
		];
		$tables = $this->preparePersonalExportTables($tables, $userId);

		return [
			'settings' => $this->fetchSettings($userId),
			'tables' => $tables,
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
			if ($key === 'backup_storage_folder') {
				$settings[$key] = $this->getBackupFolder($userId);
				continue;
			}
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

	private function fetchProjectIdsForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_projects')
			->where($qb->expr()->eq('owner_id', $qb->createNamedParameter($userId)))
			->orderBy('id', 'ASC');

		$projectIds = array_map('intval', $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));

		$memberQb = $this->db->getQueryBuilder();
		$memberQb->select('project_id')
			->from('cobudget_members')
			->where($memberQb->expr()->eq('user_id', $memberQb->createNamedParameter($userId)))
			->orderBy('project_id', 'ASC');
		$memberProjectIds = array_map('intval', $memberQb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));

		return array_values(array_unique(array_merge($projectIds, $memberProjectIds)));
	}

	private function fetchEntries(string $userId, array $workspaceIds, array $projectIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('cobudget_entries');

		$personalRows = $qb->expr()->andX(
			$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
			$qb->expr()->isNull('project_id')
		);
		if ($projectIds !== []) {
			$qb->where($qb->expr()->orX(
				$personalRows,
				$qb->expr()->in('project_id', $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY))
			))->orderBy('id', 'ASC');
		} else {
			$qb->where($personalRows)->orderBy('id', 'ASC');
		}

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

	private function getBackupFileFromFolder(string $userId, string $fileName, ?string $folderOverride, string $pattern = self::BACKUP_FILE_PATTERN): File {
		$this->assertBackupFileName($fileName, $pattern);
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

		return array_values(array_unique(array_map(
			fn (string $folder): string => $this->normalizeFolder($folder),
			[
				$this->getBackupFolder($userId),
				self::DEFAULT_PERSONAL_EXPORT_FOLDER,
			]
		)));
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

	private function assertBackupFileName(string $fileName, string $pattern = self::BACKUP_FILE_PATTERN): void {
		if (preg_match($pattern, $fileName) !== 1) {
			throw new \InvalidArgumentException('Ungültiger Export- oder Backup-Dateiname');
		}
	}
}
