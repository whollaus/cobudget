<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use ($root): string {
	$fullPath = $root . '/' . ltrim($path, '/');
	if (!is_file($fullPath)) {
		throw new RuntimeException('Missing file: ' . $path);
	}

	return (string)file_get_contents($fullPath);
};

$assertContains = static function (string $haystack, string $needle, string $label) use (&$failures): void {
	if (strpos($haystack, $needle) === false) {
		$failures[] = $label . ' is missing `' . $needle . '`';
	}
};

$assertNotContains = static function (string $haystack, string $needle, string $label) use (&$failures): void {
	if (strpos($haystack, $needle) !== false) {
		$failures[] = $label . ' still contains `' . $needle . '`';
	}
};

$assertMatches = static function (string $haystack, string $pattern, string $label) use (&$failures): void {
	if (preg_match($pattern, $haystack) !== 1) {
		$failures[] = $label . ' does not match ' . $pattern;
	}
};

try {
	$trait = $read('lib/Controller/WorkspaceAwareTrait.php');
	$assertContains($trait, 'protected ?DataResponse $workspaceHeaderErrorResponse', 'WorkspaceAwareTrait strict header state');
	$assertContains($trait, 'Invalid workspace id', 'WorkspaceAwareTrait invalid header error');
	$assertContains($trait, 'Workspace not found or no permission', 'WorkspaceAwareTrait foreign header error');
	$assertContains($trait, 'Http::STATUS_BAD_REQUEST', 'WorkspaceAwareTrait invalid header status');
	$assertContains($trait, 'Http::STATUS_FORBIDDEN', 'WorkspaceAwareTrait foreign header status');
	$assertContains($trait, 'if ($this->workspaceHeaderErrorResponse !== null)', 'WorkspaceAwareTrait no fallback after invalid header');
	$assertContains($trait, 'workspaceBelongsToUser($workspaceId)', 'WorkspaceAwareTrait owner check');

	foreach ([
		'EntryController.php',
		'ProjectController.php',
		'CategoryController.php',
		'PaymentPartnerController.php',
		'TemplateController.php',
		'WorkspaceController.php',
		'UserController.php',
	] as $controller) {
		$source = $read('lib/Controller/' . $controller);
		$assertContains($source, 'use WorkspaceAwareTrait;', $controller . ' uses WorkspaceAwareTrait');
		$assertContains($source, 'authErrorResponse()', $controller . ' checks auth/workspace errors');
	}

		$assertContains($trait, 'validateEntryPayload(', 'WorkspaceAwareTrait centralized entry payload validation');
		$assertContains($trait, 'projectMemberInActiveWorkspace($projectId)', 'WorkspaceAwareTrait project reference guard');
		$assertContains($trait, 'categoryAvailableInActiveWorkspace($categoryId, $projectId)', 'WorkspaceAwareTrait category reference guard');
		$assertContains($trait, 'paymentPartnerAvailableInActiveWorkspace($paymentPartnerId, $projectId)', 'WorkspaceAwareTrait paymentPartner reference guard');
		$assertContains($trait, 'validateAmountCents($amount, $amountCents)', 'WorkspaceAwareTrait amount validation returns cents');
		$assertContains($trait, 'validateRequiredTimestamp($date', 'WorkspaceAwareTrait date validation');

		$entry = $read('lib/Controller/EntryController.php');
	$assertContains($entry, 'validateEntryPayload(', 'EntryController uses centralized entry payload validation');
	$assertContains($entry, 'entryVisibleInActiveWorkspace($id)', 'EntryController visible workspace guard');
	$assertContains($entry, 'validateEntryUserId($projectId, $entryUserId)', 'EntryController validates selected project payer');
	$assertContains($entry, 'validateSplitMode($splitMode)', 'EntryController validates split mode');
	$assertContains($entry, "'amount_cents'", 'EntryController writes amount_cents');
	$assertContains($entry, "'split_mode'", 'EntryController writes split_mode');
	$assertContains($entry, 'normalizeAmountRow($entry)', 'EntryController normalizes amount API output');
	$assertContains($entry, 'public function dashboard(', 'EntryController exposes bundled dashboard endpoint');
	$assertContains($entry, 'public function exportCsv(', 'EntryController exposes CSV export endpoint');
	$assertContains($entry, 'DataDownloadResponse', 'EntryController returns CSV downloads through DataDownloadResponse');
	$assertContains($entry, 'fetchEntryRows($workspaceId, self::EXPORT_LIMIT, 0', 'EntryController CSV export reuses workspace-scoped filtered entry rows');
	$assertContains($entry, 'buildEntriesCsv($entries, $projectShareBasisPoints)', 'EntryController CSV export builds rows from the filtered result set');
	$assertContains($entry, 'entryPersonalAmountCents($entry, $projectShareBasisPoints)', 'EntryController CSV export includes personal shares for Bereich entries');
	$assertContains($entry, 'exportTagLabels($entry)', 'EntryController CSV export includes Kennzeichen labels');
	$assertContains($entry, "number_format(\$amountCents / 100, 2, '.', '')", 'EntryController CSV export uses decimal points for calculations');
	$assertContains($entry, 'fetchEntryListPayload($workspaceId', 'EntryController dashboard reuses entry payload');
	$assertContains($entry, 'buildDashboardMetrics(', 'EntryController dashboard builds metrics server-side');
	$assertContains($entry, 'fetchDashboardProjects($workspaceId)', 'EntryController dashboard bundles projects');
	$assertContains($entry, 'fetchDashboardCategories($workspaceId)', 'EntryController dashboard bundles categories');
	$assertContains($entry, 'fetchDashboardPaymentPartners($workspaceId)', 'EntryController dashboard bundles paymentPartners');
	$assertContains($entry, 'countDashboardTags(', 'EntryController dashboard bundles Kennzeichen counts');
	$assertContains($entry, 'summaryOnly', 'EntryController dashboard supports lightweight summary requests');
	$assertContains($entry, "'future' => \$this->summarizeDashboardEntries(\$futureData", 'EntryController dashboard exposes all planned payment metrics');
	$assertMatches($entry, '/andWhere\\(\\$qb->expr\\(\\)->eq\\(\'workspace_id\'/', 'EntryController scoped mutations');

	$routes = $read('appinfo/routes.php');
	$assertContains($routes, "'entry#dashboard'", 'Routes expose dashboard API');
	$assertContains($routes, "'/api/dashboard'", 'Routes expose dashboard API URL');
	$assertContains($routes, "'entry#exportCsv'", 'Routes expose entry CSV export API');
	$assertContains($routes, "'/api/entries/export'", 'Routes expose entry CSV export URL');

	$project = $read('lib/Controller/ProjectController.php');
	$assertContains($project, 'projectMemberInActiveWorkspace($id)', 'ProjectController member workspace guard');
	$assertContains($project, 'projectOwnerInActiveWorkspace($id)', 'ProjectController owner workspace guard');
	$assertContains($project, 'updateShares', 'ProjectController exposes configurable member shares');
	$assertContains($project, "set('share_basis_points'", 'ProjectController persists member shares');
	$assertContains($project, 'beginTransaction()', 'ProjectController critical transactions');
	$assertContains($project, 'rollBack()', 'ProjectController rollback');
	$assertContains($project, 'amountCentsFromRow($entry)', 'ProjectController calculates with cents');
	$assertContains($project, 'calculateRepaymentTransfers', 'ProjectController calculates repayment suggestions');
	$assertContains($project, 'cobudget_settlements', 'ProjectController stores settlement headers');
	$assertContains($project, 'cobudget_settlement_balances', 'ProjectController stores settlement balance snapshots');
	$assertContains($project, 'cobudget_settlement_transfers', 'ProjectController stores settlement transfers');
	$assertContains($project, "set('settlement_id'", 'ProjectController links settled entries to settlement snapshots');

	$category = $read('lib/Controller/CategoryController.php');
	$assertContains($category, 'editableCategoryInActiveWorkspace($id)', 'CategoryController delete guard');
	$assertContains($category, 'categoryAvailableInActiveWorkspace($id)', 'CategoryController hide/unhide guard');
	$assertContains($category, 'IGroupManager', 'CategoryController injects the Nextcloud admin group manager');
	$assertContains($category, 'requireAdmin()', 'CategoryController admin routes use an explicit admin guard');
	$assertContains($category, '$this->groupManager->isAdmin', 'CategoryController checks Nextcloud admin status');

	$paymentPartner = $read('lib/Controller/PaymentPartnerController.php');
	$assertContains($paymentPartner, 'editablePaymentPartnerInActiveWorkspace($id)', 'PaymentPartnerController delete guard');
	$assertContains($paymentPartner, 'paymentPartnerAvailableInActiveWorkspace($id)', 'PaymentPartnerController hide/unhide guard');
	$assertContains($paymentPartner, 'IGroupManager', 'PaymentPartnerController injects the Nextcloud admin group manager');
	$assertContains($paymentPartner, 'requireAdmin()', 'PaymentPartnerController admin routes use an explicit admin guard');
	$assertContains($paymentPartner, '$this->groupManager->isAdmin', 'PaymentPartnerController checks Nextcloud admin status');

	$template = $read('lib/Controller/TemplateController.php');
	$assertContains($template, 'templateOwnedInActiveWorkspace($id)', 'TemplateController delete guard');
	$assertContains($template, 'catch (\\Throwable $e)', 'TemplateController JSON error catch');
	$assertContains($template, "'amount_cents'", 'TemplateController writes amount_cents');
	$assertContains($template, "'split_mode'", 'TemplateController writes split_mode');
	$assertContains($template, 'markUsed', 'TemplateController exposes template usage marker');
	$assertContains($template, 'usage_count', 'TemplateController tracks template usage');

	$workspace = $read('lib/Controller/WorkspaceController.php');
	$assertContains($workspace, 'beginTransaction()', 'WorkspaceController transactional delete');
	$assertContains($workspace, 'owner_id', 'WorkspaceController project owner scope');
	$assertContains($workspace, 'user_id', 'WorkspaceController user scope');
	$assertContains($workspace, 'entryIdsForWorkspaceDelete', 'WorkspaceController collects all entries that must be removed');
	$assertContains($workspace, 'cobudget_entry_attachments', 'WorkspaceController removes attachment rows before deleting entries');
	$assertContains($workspace, 'cobudget_settlement_balances', 'WorkspaceController removes settlement balance rows for deleted areas');
	$assertContains($workspace, 'cobudget_settlement_transfers', 'WorkspaceController removes settlement transfer rows for deleted areas');
	$assertContains($workspace, "deleteRowsByColumnValues(\$table, 'project_id', \$projectIds)", 'WorkspaceController removes project-scoped rows independently of row user_id');
	$assertContains($workspace, 'IQueryBuilder::PARAM_INT_ARRAY', 'WorkspaceController uses typed array parameters for delete scopes');

	$user = $read('lib/Controller/UserController.php');
	$assertContains($user, 'IDBConnection $db', 'UserController can validate workspace headers');
	$assertContains($user, '$this->initWorkspace()', 'UserController initializes workspace header guard');
	$assertContains($user, 'UserResetService', 'UserController injects the user reset service');
	$assertContains($user, 'public function resetPreview()', 'UserController exposes a reset preview endpoint');
	$assertContains($user, 'public function resetAll()', 'UserController exposes a reset endpoint');
	$assertContains($user, "getParam('confirmation', '')", 'User reset API reads the confirmation token server-side');
	$assertContains($user, 'ResetBlockedException', 'User reset API returns a conflict for blocked shared areas');
	$assertContains($routes, "'user#resetPreview'", 'Routes expose user reset preview API');
	$assertContains($routes, "'/api/settings/reset-preview'", 'Routes expose user reset preview URL');
	$assertContains($routes, "'user#resetAll'", 'Routes expose user reset API');
	$assertContains($routes, "'/api/settings/reset'", 'Routes expose user reset URL');

	$application = $read('lib/AppInfo/Application.php');
	$assertContains($application, 'registerNotifierService(Notifier::class)', 'Application uses supported notifier registration');
	if (strpos($application, 'registerNotifier(') !== false) {
		$failures[] = 'Application must not call removed registerNotifier API';
	}
	$assertContains($application, 'use OCP\\BackgroundJob\\IJobList;', 'Application imports background job list');
	$assertContains($application, 'use OCP\\IDBConnection;', 'Application imports database connection');
	$assertContains($application, 'CreateBackupCommand::class', 'Application registers user backup command');
	$assertContains($application, 'CreateFullBackupCommand::class', 'Application registers full backup command');
	$assertContains($application, 'RestoreBackupCommand::class', 'Application registers user restore command');
	$assertContains($application, 'RestoreFullBackupCommand::class', 'Application registers full restore command');
	$assertContains($application, 'RecurringEntriesJob::class', 'Application registers recurring job on boot');
	$assertContains($application, 'RemindersJob::class', 'Application registers reminders job on boot');
	$assertContains($application, 'BackupJob::class', 'Application registers backup job on boot');
	$assertContains($application, 'BudgetSnapshotJob::class', 'Application registers budget snapshot job on boot');
	$assertContains($application, '$jobList->has($jobClass, null)', 'Application avoids duplicate null-argument jobs');
	$assertContains($application, '$jobList->has($jobClass, [])', 'Application avoids duplicate empty-array jobs');
	$assertContains($application, '$jobList->add($jobClass, [])', 'Application adds missing jobs after zip-only updates');
	$assertContains($application, 'prioritizeUnrunWebCronJob($db, $jobClass)', 'Application prioritizes never-run jobs for WebCron');
	$assertContains($application, "update('jobs')", 'Application can reset Nextcloud job queue metadata');
	$assertContains($application, "eq('last_run'", 'Application only prioritizes jobs that have never run');
	$assertContains($application, "set('last_checked'", 'Application resets last_checked for newly registered jobs');

	$recurringJob = $read('lib/Cron/RecurringEntriesJob.php');
	$assertContains($recurringJob, 'beginTransaction()', 'RecurringEntriesJob transaction');
	$assertContains($recurringJob, "'workspace_id'", 'RecurringEntriesJob preserves workspace_id');
	$assertContains($recurringJob, "'amount_cents'", 'RecurringEntriesJob preserves amount_cents');
	$assertContains($recurringJob, "'split_mode'", 'RecurringEntriesJob preserves split_mode');
	$assertContains($recurringJob, "'recurrence_series_id'", 'RecurringEntriesJob preserves recurrence series id');
	$assertContains($recurringJob, 'deactivateCurrentSeriesHead($entry)', 'RecurringEntriesJob deactivates the previous series head');
	$assertContains($recurringJob, 'recurrenceSeriesIdFromRow($entry)', 'RecurringEntriesJob resolves the recurrence series id');
	$assertContains($recurringJob, '$hasNextRun', 'RecurringEntriesJob transfers recurrence fields only when there is a next run');
	$assertContains($recurringJob, 'private const RECURRENCE_HOUR = 9', 'RecurringEntriesJob fixed recurrence hour');
	$assertContains($recurringJob, 'private const JOB_INTERVAL_SECONDS = 5 * 60', 'RecurringEntriesJob 5-minute web-cron interval');
	$assertContains($recurringJob, 'setInterval(self::JOB_INTERVAL_SECONDS)', 'RecurringEntriesJob uses 5-minute interval');
	$assertContains($recurringJob, 'recurrenceDueCutoff($now)', 'RecurringEntriesJob catches up after the due time');
	$assertContains($recurringJob, 'return $now;', 'RecurringEntriesJob processes timestamps due now or in the past');
	$assertContains($recurringJob, 'normalizeToRecurrenceTime(', 'RecurringEntriesJob normalizes generated recurrence dates');

	$remindersJob = $read('lib/Cron/RemindersJob.php');
	$assertContains($remindersJob, 'private const JOB_INTERVAL_SECONDS = 5 * 60', 'RemindersJob 5-minute web-cron interval');
	$assertContains($remindersJob, 'setInterval(self::JOB_INTERVAL_SECONDS)', 'RemindersJob uses 5-minute interval');
	$assertContains($remindersJob, "'title' =>", 'RemindersJob sends a useful notification title');
	$assertContains($remindersJob, "'amount' =>", 'RemindersJob sends amount context');
	$assertContains($remindersJob, "'category' =>", 'RemindersJob sends category context');
	$assertContains($remindersJob, "'paymentPartner' =>", 'RemindersJob sends paymentPartner context');
	$assertContains($remindersJob, "eq('user_id'", 'RemindersJob scopes reminder updates by user_id');
	$assertContains($remindersJob, "eq('workspace_id'", 'RemindersJob scopes reminder updates by workspace_id');

	$backupJob = $read('lib/Cron/BackupJob.php');
	$assertContains($backupJob, 'private const BACKUP_HOUR = 3', 'BackupJob fixed 03:00 hour');
	$assertContains($backupJob, 'private const JOB_INTERVAL_SECONDS = 5 * 60', 'BackupJob catches up through frequent WebCron checks');
	$assertContains($backupJob, 'currentScheduleSlot($now)', 'BackupJob uses a fixed 03:00 daily slot');
	$assertContains($backupJob, 'acquireUserBackupLock($userId, time())', 'BackupJob acquires a per-user lock');
	$assertContains($backupJob, 'releaseUserBackupLock($userId, $backupLock)', 'BackupJob releases the per-user lock');
	$assertContains($backupJob, "insert('preferences')", 'BackupJob lock uses preferences insert');
	$assertContains($backupJob, 'deleteStaleUserBackupLock($userId, $now)', 'BackupJob recovers stale locks');

	$backupService = $read('lib/Service/BackupService.php');
	$assertContains($backupService, "'scope' => 'user'", 'User backup manifest includes scope');
	$assertContains($backupService, "'scope' => 'system'", 'Full backup manifest includes scope');
	$assertContains($backupService, 'private const SETTINGS_DEFAULTS', 'BackupService exports effective settings defaults');
	$assertContains($backupService, "'enable_workspaces' => 'no'", 'BackupService tracks workspace settings defaults');
	$assertContains($backupService, "'show_workspace_switcher' => 'yes'", 'BackupService tracks workspace switcher settings defaults');
	$assertContains($backupService, "'currency' => 'EUR'", 'BackupService defaults currency to EUR when no user preference exists');
	$assertContains($backupService, "'hidden_workspaces' => '[]'", 'BackupService tracks hidden workspace settings defaults');
	$assertContains($backupService, 'settingsDefaultForUser($userId, $key)', 'User backup includes effective defaulted settings values');
	$assertContains($backupService, "if (\$key === 'enable_workspaces' && \$this->userHasManagedWorkspaces(\$userId))", 'BackupService exports workspace feature enabled when extra workspaces exist');
	$assertContains($backupService, 'backupContainsUserManagedWorkspaces($tables, $userId)', 'Restore recovers workspace-enabled state when extra workspaces exist despite older default no settings');
	$assertContains($backupService, 'normalizeFullSettings($archive[\'settings\'], $tables)', 'Full restore normalizes settings with table context');
	$assertContains($backupService, 'fetchAllSettings($userIds)', 'Full backup includes effective settings for every exported user');
	$assertContains($backupService, 'createFullBackup(string $storageUserId', 'BackupService exposes full backup creation');
	$assertContains($backupService, 'deleteBackup(string $userId', 'BackupService exposes backup deletion');
	$assertContains($backupService, 'inspectBackup(string $userId', 'BackupService exposes backup inspection before restore');
	$assertContains($backupService, 'restoreBackup(string $userId', 'BackupService exposes user backup restore');
	$assertContains($backupService, 'restoreFullBackup(string $storageUserId', 'BackupService exposes full backup restore');
	$assertContains($backupService, 'collectFullBackupData()', 'BackupService collects full backup data');
	$assertContains($backupService, 'fetchAllSettings($userIds)', 'Full backup includes effective CoBudget settings');
	$assertContains($backupService, 'cobudget-full-backup-', 'Full backup uses a distinct filename prefix');
	$assertNotContains($backupService, 'LEGACY_BACKUP_FOLDER', 'BackupService should not keep legacy backup folders after the technical reset');
	$assertContains($backupService, 'backupLookupFolders($userId', 'BackupService searches compatible backup folders');
	$assertContains($backupService, 'deleteAllBackupTables()', 'Full restore deletes existing app tables');
	$assertContains($backupService, 'deleteAllCoBudgetSettings()', 'Full restore deletes existing app settings');
	$assertContains($backupService, 'assertReferencedUsersExist(', 'Restore validates referenced users');
	$assertContains($backupService, 'assertUserRestoreScope($tables, $userId)', 'User restore validates shared-area scope before deleting data');
	$assertContains($backupService, 'private function assertUserRestoreScope', 'BackupService has a user restore shared-area guard');
	$assertContains($backupService, 'assertRowsBelongToUser($tables', 'BackupService rejects non-project user rows outside the restore user');
	$assertContains($backupService, 'Dieses Benutzer-Backup enthält Zahlungen aus einem fremden gemeinsamen Bereich', 'User restore rejects foreign shared-area payments');
	$assertContains($backupService, 'Dieses Benutzer-Backup enthält Beleg-Pfade ohne passende Zahlung', 'User restore rejects attachment paths outside imported entries');
	$assertContains($backupService, 'Dieses Benutzer-Backup enthält Abrechnungsdaten ohne passende Abrechnung', 'User restore rejects orphan settlement rows outside imported settlements');
	$assertContains($backupService, "eq('owner_id'", 'User backup areas are owner-scoped');
	$assertContains($backupService, 'applyUserMapToTables(', 'Restore supports user mapping');
	$assertContains($backupService, 'buildBackupUserRows(', 'Backup inspection returns user mapping rows');
	$assertContains($backupService, 'backupContainsWorkspaces(', 'Restore recovers workspace settings from older workspace backups');
	$assertContains($backupService, '$safetyBackup = $this->createBackup(', 'User restore creates a safety backup first');
	$assertContains($backupService, '$safetyBackup = $this->createFullBackup(', 'Full restore creates a safety backup first');
	$assertContains($backupService, "'safety_backup' => \$safetyBackup", 'Restore responses include the safety backup');
	$assertContains($backupService, "'cobudget_budget_snapshots'", 'BackupService exports budget snapshots');
	$assertContains($backupService, 'fetchBudgetSnapshots($userId, $workspaceIds)', 'User backup includes budget snapshots');

	$userResetService = $read('lib/Service/UserResetService.php');
	$assertContains($userResetService, "CONFIRMATION_TEXT = 'RESET'", 'User reset requires an explicit RESET confirmation');
	$assertContains($userResetService, "SAFETY_BACKUP_FOLDER = 'CoBudget/Backups'", 'User reset creates the safety backup in the default backup folder');
	$assertContains($userResetService, 'createBackup($userId, self::SAFETY_BACKUP_FOLDER', 'User reset creates a safety backup before deleting data');
	$assertContains($userResetService, 'blocking_shared_projects', 'User reset reports shared areas that block reset');
	$assertContains($userResetService, 'countUnsettledProjectEntries', 'User reset blocks shared areas with open entries');
	$assertContains($userResetService, 'transferSettledSharedProject', 'User reset transfers settled shared areas to another member');
	$assertContains($userResetService, "'delete_receipts_with_entry'", 'User reset honors the receipt file deletion setting');
	$assertContains($userResetService, 'resetUserSettings', 'User reset clears user settings back to defaults');
	$assertContains($userResetService, 'createDefaultWorkspaceForUser', 'User reset recreates a default workspace');

	$fullBackupCommand = $read('lib/Command/CreateFullBackupCommand.php');
	$assertContains($fullBackupCommand, "setName('cobudget:backup:create-full')", 'Full backup command has the expected OCC name');
	$assertContains($fullBackupCommand, "addOption('user'", 'Full backup command requires a storage user option');
	$assertContains($fullBackupCommand, 'createFullBackup(', 'Full backup command calls BackupService full backup');

	$userRestoreCommand = $read('lib/Command/RestoreBackupCommand.php');
	$assertContains($userRestoreCommand, "setName('cobudget:backup:restore')", 'User restore command has the expected OCC name');
	$assertContains($userRestoreCommand, "addOption('map-user'", 'User restore command supports user mapping');
	$assertContains($userRestoreCommand, "addOption('force'", 'User restore command requires explicit force');
	$assertContains($userRestoreCommand, 'restoreBackup(', 'User restore command calls BackupService restore');
	$assertContains($userRestoreCommand, 'Sicherheitsbackup:', 'User restore command prints the safety backup');

	$fullRestoreCommand = $read('lib/Command/RestoreFullBackupCommand.php');
	$assertContains($fullRestoreCommand, "setName('cobudget:backup:restore-full')", 'Full restore command has the expected OCC name');
	$assertContains($fullRestoreCommand, "addOption('map-user'", 'Full restore command supports user mapping');
	$assertContains($fullRestoreCommand, "addOption('force'", 'Full restore command requires explicit force');
	$assertContains($fullRestoreCommand, 'restoreFullBackup(', 'Full restore command calls BackupService full restore');
	$assertContains($fullRestoreCommand, 'Sicherheitsbackup:', 'Full restore command prints the safety backup');

	$backupController = $read('lib/Controller/BackupController.php');
	$assertContains($backupController, 'public function download(string $fileName)', 'Backup API exposes direct backup downloads');
	$assertContains($backupController, '@NoCSRFRequired', 'Backup download direct links avoid browser CSRF failures');
	$assertContains($backupController, 'authErrorResponse()', 'Backup download still requires an authenticated user');
	$assertContains($backupController, 'getBackupFile((string)$this->userId, $fileName)', 'Backup download is scoped to the current user');
	$assertContains($backupController, 'public function destroy(string $fileName)', 'Backup API exposes backup deletion');
	$assertContains($backupController, 'deleteBackup((string)$this->userId, $fileName)', 'Backup deletion is scoped to the current user');

	$integrityController = $read('lib/Controller/IntegrityController.php');
	$assertContains($integrityController, 'IGroupManager', 'IntegrityController injects the Nextcloud admin group manager');
	$assertContains($integrityController, 'IUserSession', 'IntegrityController checks the current user');
	$assertContains($integrityController, 'requireAdmin()', 'IntegrityController admin routes use an explicit admin guard');
	$assertContains($integrityController, '$this->groupManager->isAdmin', 'IntegrityController checks Nextcloud admin status');

	$budget = $read('lib/Controller/BudgetController.php');
	$assertContains($routes, "'budget#index'", 'Routes expose budget list API');
	$assertContains($routes, "'budget#create'", 'Routes expose budget create API');
	$assertContains($routes, "'budget#update'", 'Routes expose budget update API');
	$assertContains($routes, "'budget#destroy'", 'Routes expose budget delete API');
	$assertContains($budget, 'validateBudgetPayload', 'BudgetController centralizes budget validation');
	$assertContains($budget, "validateAmountCents(\$amount, \$amountCents, false, 'Ungültiges Budget')", 'BudgetController validates budgets as integer cents');
	$assertContains($budget, 'validateCriteria($criteria, $workspaceId)', 'BudgetController validates criteria references');
	$assertContains($budget, 'BudgetSnapshotService', 'BudgetController injects budget snapshot service');
	$assertContains($budget, "snapshotGoalForCurrentPeriod((string)\$this->userId, \$currentGoal, 'changed')", 'BudgetController snapshots previous state before update');
	$assertContains($budget, "snapshotGoalForCurrentPeriod((string)\$this->userId, \$currentGoal, 'deleted')", 'BudgetController snapshots previous state before delete');
	$assertContains($budget, 'projectMemberInActiveWorkspace', 'BudgetController checks project criteria membership');
	$assertContains($budget, 'categorySelectableForBudget', 'BudgetController checks category criteria visibility');
	$assertContains($budget, 'entryShareCentsForUser', 'BudgetController evaluates personal shares for shared areas');
	$assertContains($budget, "eq('user_id'", 'BudgetController scopes budget goals by user');
	$assertContains($budget, "eq('workspace_id'", 'BudgetController scopes budget goals by workspace');

	$budgetSnapshotService = $read('lib/Service/BudgetSnapshotService.php');
	$assertContains($budgetSnapshotService, 'createDueSnapshots', 'BudgetSnapshotService closes due budget periods');
	$assertContains($budgetSnapshotService, 'cobudget_budget_snapshots', 'BudgetSnapshotService writes snapshot table');
	$assertContains($budgetSnapshotService, 'entryShareCentsForUser', 'BudgetSnapshotService evaluates personal shared-area shares');
	$assertContains($budgetSnapshotService, 'snapshotExists', 'BudgetSnapshotService prevents duplicate period snapshots');
	$assertContains($budgetSnapshotService, "innerJoin('s', 'cobudget_budget_goals'", 'BudgetSnapshotService hides deleted budget goals from analytics history');

	$budgetSnapshotJob = $read('lib/Cron/BudgetSnapshotJob.php');
	$assertContains($budgetSnapshotJob, 'createDueSnapshots()', 'BudgetSnapshotJob runs due snapshot creation');

	$analytics = $read('lib/Controller/AnalyticsController.php');
	$assertContains($analytics, 'BudgetSnapshotService', 'AnalyticsController injects budget snapshot service');
	$assertContains($analytics, "'budgetHistory'", 'AnalyticsController exposes budget snapshot history');
	$assertContains($analytics, "'availableForecast'", 'AnalyticsController exposes an available-money forecast');
	$assertContains($analytics, 'buildAvailableForecast($selectedPeriod, $summary, $projection)', 'AnalyticsController builds available-money forecasts from the existing projection');
	$assertContains($analytics, "'rangeLowCents'", 'AnalyticsController exposes a cautious low forecast range');
	$assertContains($analytics, "'rangeHighCents'", 'AnalyticsController exposes a cautious high forecast range');
	$assertContains($analytics, 'loadAnalyticsEntryDates($workspaceId)', 'AnalyticsController builds period options with a lightweight date query');
	$assertContains($analytics, 'loadAnalyticsEntries($workspaceId, $sharesByProject, (int)$selectedPeriod[\'start\'], (int)$selectedPeriod[\'end\'])', 'AnalyticsController loads only the selected analytics period');
	$assertContains($analytics, 'attachAnalyticsAttachmentFlags($periodEntries, $workspaceId)', 'AnalyticsController attaches receipt flags after period filtering');
	$assertContains($analytics, 'entriesForAnalyticsRange($workspaceId, $sharesByProject, $periodEntries, $selectedPeriod', 'AnalyticsController reuses selected period entries for overlapping analytics ranges');
	$assertContains($analytics, 'IQueryBuilder::PARAM_INT_ARRAY', 'AnalyticsController batches attachment lookups with typed array parameters');
	$assertNotContains($analytics, 'ICacheFactory', 'AnalyticsController keeps analytics responses live instead of cache-backed');
	$assertContains($analytics, 'comparisonPeriodFor($selectedPeriod)', 'AnalyticsController compares Schwerpunkte with a matching previous period');
	$assertContains($analytics, 'directionWindowsFor($selectedPeriod)', 'AnalyticsController computes recent direction windows for Schwerpunkt trend badges');
	$assertContains($analytics, 'withBreakdownTrends($rows, $comparisonRows, $directionRecentRows, $directionBaselineRows, $type)', 'AnalyticsController attaches direction and comparison metadata to breakdown rows');
	$assertContains($analytics, '$row[\'trend\'] = $trend', 'AnalyticsController stores recent direction in the visible trend field');
	$assertContains($analytics, '$row[\'comparison\'] = $comparison', 'AnalyticsController stores previous-period comparison separately');
	$assertContains($analytics, '$absoluteDeltaCents < 1000 || $absoluteDeltaPercent < 15', 'AnalyticsController hides tiny Schwerpunkt trend changes');

	$assertContains($routes, "'entry#attachments'", 'Routes expose attachment list API');
	$assertContains($routes, "'entry#uploadAttachment'", 'Routes expose attachment upload API');
	$assertContains($routes, "'entry#downloadAttachment'", 'Routes expose attachment display API');
	$assertContains($routes, "'entry#destroyAttachment'", 'Routes expose attachment delete API');
	$assertContains($entry, 'receiptsEnabled()', 'EntryController gates attachment APIs behind receipt settings');
	$assertContains($entry, 'cobudget_entry_attachments', 'EntryController stores attachment metadata in a dedicated table');
	$assertContains($entry, 'entryVisibleInActiveWorkspace($id)', 'EntryController keeps attachment APIs tied to visible active-workspace entries');
	$assertContains($entry, 'workspaceBelongsToUser($workspaceId)', 'EntryController validates explicit workspace ids for attachment display');
	$assertContains($entry, 'FileDisplayResponse', 'EntryController displays receipt files inline where possible');
	$assertContains($entry, '@NoCSRFRequired', 'EntryController receipt display avoids browser CSRF failures');
	$assertContains($entry, "'receipt_storage_folder'", 'EntryController uses the configured receipt storage folder');
	$assertContains($entry, "'receipt_folder_grouping'", 'EntryController uses the configured receipt folder grouping');
	$assertContains($entry, "'delete_receipts_with_entry'", 'EntryController honors configured receipt file deletion behavior');

	$user = $read('lib/Controller/UserController.php');
	$assertContains($user, "'enable_budget_goals'", 'User settings expose budget goal feature toggle');
	$assertContains($user, "'enable_receipts'", 'User settings expose receipt feature toggle');
	$assertContains($user, 'validateReceiptStorageFolder', 'User settings validate receipt storage folders');
	$assertContains($user, 'validateReceiptFolderGrouping', 'User settings validate receipt folder grouping');
	$assertContains($user, 'CURRENCY_BY_COUNTRY', 'User settings can derive currency from Nextcloud locale');
	$assertContains($user, "'AT' => 'EUR'", 'User settings default Austrian locale to EUR');
	$assertContains($user, "'CH' => 'CHF'", 'User settings default Swiss locale to CHF');
	$assertContains($user, "'US' => 'USD'", 'User settings default US locale to USD');
	$assertContains($user, 'effectiveCurrency()', 'User settings expose an effective currency default');
	$assertContains($user, 'detectCurrencyFromLocale()', 'User settings persist a detected currency when saving empty currency');

	$notifier = $read('lib/Notification/Notifier.php');
	$l10nDe = $read('l10n/de.js');
	$assertContains($notifier, 'CoBudget reminder: %s', 'Notifier uses clearer reminder subject l10n key');
	$assertContains($l10nDe, '"CoBudget reminder: %s": "CoBudget-Erinnerung: %s"', 'German l10n translates reminder subject');
	$assertContains($notifier, 'buildReminderMessage', 'Notifier builds a richer reminder message');
	$assertContains($notifier, 'Payment partner: %s', 'Notifier includes paymentPartner context l10n key');
	$assertContains($l10nDe, '"Payment partner: %s": "Zahlungspartner: %s"', 'German l10n translates paymentPartner context');
	$assertContains($notifier, 'Reminder due since %s', 'Notifier includes due reminder date l10n key');
	$assertContains($l10nDe, '"Reminder due since %s": "Erinnerung fällig seit %s"', 'German l10n translates due reminder date');
	$assertContains($notifier, 'project_entry_created', 'Notifier handles shared area entry notifications');
	$assertContains($notifier, 'project_settled', 'Notifier handles shared area settlement notifications');
	$assertContains($notifier, '%s created an income of %s %s in area %s.', 'Notifier labels income entries via l10n');
	$assertContains($notifier, '%s created an expense of %s %s in area %s.', 'Notifier labels expense entries via l10n');
	$assertContains($l10nDe, '"%s created an expense of %s %s in area %s.": "%1$s hat im Bereich %4$s eine Ausgabe über %2$s %3$s erstellt."', 'German l10n translates shared area expense notifications');
	$assertContains($notifier, 'Paid by: %s', 'Notifier includes expense payer context l10n key');
	$assertContains($notifier, 'Received by: %s', 'Notifier includes income receiver context l10n key');
	$assertContains($l10nDe, '"Paid by: %s": "Bezahlt hat: %s"', 'German l10n translates expense payer context');
	$assertContains($l10nDe, '"Received by: %s": "Betrag erhalten hat: %s"', 'German l10n translates income receiver context');
	$assertContains($notifier, 'You get %s %s back.', 'Notifier includes positive settlement result l10n key');
	$assertContains($notifier, 'You owe %s %s.', 'Notifier includes negative settlement result l10n key');
	$assertContains($l10nDe, '"You get %s %s back.": "Du bekommst %s %s zurück."', 'German l10n translates positive settlement result');
	$assertContains($l10nDe, '"You owe %s %s.": "Du schuldest %s %s."', 'German l10n translates negative settlement result');

	$projectNotificationService = $read('lib/Service/ProjectNotificationService.php');
	$assertContains($projectNotificationService, 'notifyEntryCreated', 'ProjectNotificationService sends entry notifications');
	$assertContains($projectNotificationService, 'prepareSettlementNotifications', 'ProjectNotificationService prepares settlement notifications before commit');
	$assertContains($projectNotificationService, 'sendPreparedNotifications', 'ProjectNotificationService sends prepared settlement notifications after commit');
	$assertContains($projectNotificationService, "SETTING_NOTIFY_ENTRIES = 'notify_project_entries'", 'Project entry notification setting key');
	$assertContains($projectNotificationService, "SETTING_NOTIFY_SETTLEMENTS = 'notify_project_settlements'", 'Project settlement notification setting key');
	$assertContains($projectNotificationService, '$recipientUserId === $actorUserId', 'Project notifications skip the acting user');
	$assertContains($projectNotificationService, 'count($members) <= 1', 'Project notifications skip solo areas');
	$assertContains($projectNotificationService, 'enable_shared_projects', 'Project notifications depend on shared areas setting');
	$assertContains($projectNotificationService, 'linkToRouteAbsolute', 'Project notifications link to the area');

	$initialMigration = $read('lib/Migration/Version000001Date20260624000000.php');
	$assertContains($initialMigration, "'amount_cents'", 'Initial migration stores cents');
	$assertContains($initialMigration, "'bigint'", 'Initial migration stores cents as bigint');
	$assertContains($initialMigration, 'recurrence_series_id', 'Initial migration adds recurrence_series_id');
	$assertContains($initialMigration, 'cb_ent_rec_series', 'Initial migration indexes recurrence_series_id');
	$assertContains($initialMigration, 'share_basis_points', 'Initial migration stores member shares');
	$assertContains($initialMigration, 'split_mode', 'Initial migration stores split mode');
	$assertContains($initialMigration, 'settlement_id', 'Initial migration adds entry settlement_id');
	$assertContains($initialMigration, 'cobudget_settlements', 'Initial migration creates settlement table');
	$assertContains($initialMigration, 'cobudget_settlement_balances', 'Initial migration creates balance snapshot table');
	$assertContains($initialMigration, 'cobudget_settlement_transfers', 'Initial migration creates transfer snapshot table');
	$assertContains($initialMigration, 'usage_count', 'Initial migration adds template usage_count');

	$performanceMigration = $read('lib/Migration/Version000002Date20260703000000.php');
	foreach ([
		'cb_ent_ws_date_id',
		'cb_ent_ws_type_dt',
		'cb_ent_ws_pr_set_dt',
		'cb_ent_ws_remind',
		'cb_ent_ws_recur',
		'cb_mem_user_proj',
		'cb_cat_ws_type_user',
		'cb_pp_ws_type_user',
		'cb_att_ws_entry',
		'cb_set_ws_proj_time',
		'cb_budget_ws_user_upd',
	] as $indexName) {
		$assertContains($performanceMigration, $indexName, 'Performance migration should keep index ' . $indexName);
	}

	$entryController = $read('lib/Controller/EntryController.php');
	$assertContains($entryController, 'fetchProjectMembersByProjectIds', 'Entry dashboard should bulk-load project members');
	$assertContains($entryController, 'fetchOpenExpenseEntriesByProjectIds', 'Entry dashboard should bulk-load open project entries');
	$assertContains($entryController, 'PARAM_INT_ARRAY', 'Entry dashboard bulk queries should use array parameters');

	$categoryController = $read('lib/Controller/CategoryController.php');
	$assertContains($categoryController, 'DEFAULT_GLOBAL_CATEGORIES', 'CategoryController defines global starter categories');
	$assertContains($categoryController, "'Groceries'", 'Starter categories include Groceries l10n key');
	$assertContains($categoryController, "'Rent and utilities'", 'Starter categories include rent/utilities l10n key');
	$assertContains($categoryController, "'Other income'", 'Starter categories include other income l10n key');
	$assertContains($l10nDe, '"Groceries": "Lebensmittel"', 'German l10n translates Groceries starter category');
	$assertContains($l10nDe, '"Rent and utilities": "Miete & Nebenkosten"', 'German l10n translates rent starter category');
	$assertContains($l10nDe, '"Other income": "Sonstige Einnahmen"', 'German l10n translates other income starter category');
	$assertContains($categoryController, 'ensureDefaultGlobalCategories()', 'CategoryController seeds default categories before reads');
	$assertContains($categoryController, 'DEFAULT_CATEGORIES_SEEDED_KEY', 'Category seeding is guarded by an app setting');

	$paymentPartnerController = $read('lib/Controller/PaymentPartnerController.php');
	$assertContains($paymentPartnerController, 'DEFAULT_GLOBAL_PAYMENT_PARTNERS', 'PaymentPartnerController defines global starter payment partners');
	$assertContains($paymentPartnerController, "'Employer'", 'Starter payment partners include Employer l10n key');
	$assertContains($paymentPartnerController, "'Supermarket and bakery'", 'Starter payment partners include supermarket l10n key');
	$assertContains($paymentPartnerController, "'Pharmacy and doctor'", 'Starter payment partners include pharmacy l10n key');
	$assertContains($l10nDe, '"Employer": "Arbeitgeber"', 'German l10n translates Employer starter payment partner');
	$assertContains($l10nDe, '"Supermarket and bakery": "Supermarkt & Bäcker"', 'German l10n translates supermarket starter payment partner');
	$assertContains($l10nDe, '"Pharmacy and doctor": "Apotheke & Arzt"', 'German l10n translates pharmacy starter payment partner');
	$assertContains($paymentPartnerController, 'ensureDefaultGlobalPaymentPartners()', 'PaymentPartnerController seeds default payment partners before reads');
	$assertContains($paymentPartnerController, 'DEFAULT_PAYMENT_PARTNERS_SEEDED_KEY', 'Payment partner seeding is guarded by an app setting');

	$infoXml = $read('appinfo/info.xml');
	if (preg_match('/<version>([^<]+)<\/version>/', $infoXml, $versionMatch) !== 1 || $versionMatch[1] !== '0.2') {
		$failures[] = 'Performance index migration should bump appinfo/info.xml to version 0.2';
	}
	$assertContains($infoXml, 'max-version="33"', 'App metadata should allow the deployed Nextcloud 33 server');

	$app = $read('src/App.vue');
	$workspaceSettings = $read('src/components/WorkspaceSettings.vue');
	$texts = $read('src/l10n/texts.js');
	$assertContains($app, 'skipWorkspaceHeader: true', 'App workspace list is user-global');
	$assertContains($workspaceSettings, 'skipWorkspaceHeader: true', 'WorkspaceSettings APIs are user-global');
	$assertContains($workspaceSettings, '<span class="default-badge">{{ $texts.workspaces.base() }}</span>', 'WorkspaceSettings labels the base workspace through shared texts');
	$assertContains($texts, "base: () => tx('Base')", 'Workspace base label is centralized in texts');
	$assertContains($l10nDe, '"Base": "Basis"', 'German l10n translates the workspace base label');
	$assertContains($texts, "baseTooltip: () => tx('The base workspace is created automatically and cannot be deleted.')", 'Workspace base tooltip is centralized in texts');
	$assertContains($l10nDe, '"The base workspace is created automatically and cannot be deleted.": "Der Basis-Workspace wird automatisch erstellt und kann nicht gelöscht werden."', 'German l10n translates the workspace base tooltip');
	$assertContains($workspaceSettings, 'selectWorkspace(workspace)', 'WorkspaceSettings can switch the active workspace');
	$assertContains($workspaceSettings, ':can-hide="!workspace.is_hidden"', 'WorkspaceSettings can hide workspaces from the quick switcher');
	$assertContains($workspaceSettings, ':can-unhide="workspace.is_hidden"', 'WorkspaceSettings can unhide workspaces from the quick switcher');
} catch (Throwable $e) {
	$failures[] = $e->getMessage();
}

if ($failures !== []) {
	fwrite(STDERR, "Static security checks failed:\n");
	foreach ($failures as $failure) {
		fwrite(STDERR, '- ' . $failure . "\n");
	}
	exit(1);
}

echo "Static security checks passed.\n";
