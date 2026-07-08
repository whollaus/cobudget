<?php

declare(strict_types=1);

namespace CoBudget\Tests;

use CoBudget\Tests\Support\TestRunner;

return [
	'Personal export restore is limited to empty target users while full restore stays protected' => function(TestRunner $t): void {
		$restore = $t->methodBody('lib/Service/BackupService.php', 'restoreBackup');
		$collect = $t->methodBody('lib/Service/BackupService.php', 'collectBackupData');

		$t->assertContains('$this->assertBackupArchive($archive, \'user\')', $restore, 'Personal export restore should only accept user exports');
		$t->assertContains('$this->assertPersonalImportTargetIsEmpty($userId)', $restore, 'Personal export restore should refuse existing target data');
		$t->assertContains('$tables = $this->preparePersonalImportTables($archive[\'tables\'], $userId, $sourceUserId)', $restore, 'Personal export restore should reduce shared data to the importing user share');
		$t->assertContains('$this->assertPersonalImportContainsOnlyUser($tables, $userId)', $restore, 'Personal export restore should import only rows owned by the target user after normalization');
		$t->assertContains('$safetyBackup = $this->createBackup($userId', $restore, 'Personal export restore should create a safety export before importing');
		$t->assertContains('$this->db->beginTransaction()', $restore, 'Personal export restore should import atomically');
		$t->assertContains('$this->deletePersonalImportTarget($userId)', $restore, 'Personal export restore should only delete the already-empty target scope');
		$t->assertContains('$this->insertTablesWithGeneratedIds($tables)', $restore, 'Personal export restore should regenerate local row IDs');
		$t->assertNotContains('deleteRowsByBackupData', $restore, 'Personal export restore should not delete current data');

		$emptyTarget = $t->methodBody('lib/Service/BackupService.php', 'personalImportBlockingTable');
		$t->assertContains("if (\$table === 'cobudget_workspaces')", $emptyTarget, 'Personal import should allow the empty default workspace created by reset or first app open');
		$t->assertContains('continue;', $emptyTarget, 'Personal import should skip default workspace rows during the empty-target check');

		$service = $t->read('lib/Service/BackupService.php');
		$t->assertContains('public function personalRestoreState(string $userId)', $service, 'Personal export list should expose restore availability');
		$t->assertContains('personalArchiveRestoreInfo(array $archive, string $sourceUserId, string $targetUserId)', $service, 'Backup inspect should expose whether a personal export contains shared users');
		$t->assertContains('private function preparePersonalExportTables(array $tables, string $userId): array', $service, 'Personal export should have an explicit reduction step before archive write');
		$prepareExport = $t->methodBody('lib/Service/BackupService.php', 'preparePersonalExportTables');
		$t->assertContains('preparePersonalImportTables($tables, $userId, $userId)', $prepareExport, 'Personal export should reuse the personal-share reduction with the exporting user as source');

		$t->assertContains('$ownedWorkspaces = $this->fetchRowsByUser(\'cobudget_workspaces\', $userId)', $collect, 'Personal export should collect workspaces belonging to the user');
		$t->assertContains('$projectIds = $this->fetchProjectIdsForUser($userId)', $collect, 'Personal export should collect areas where the user is owner or member');
		$t->assertContains('$entries = $this->fetchEntries($userId, $workspaceIds, $projectIds)', $collect, 'Personal export should collect personal entries and shared-area entries only before export reduction');
		$t->assertContains('$tables = $this->preparePersonalExportTables($tables, $userId)', $collect, 'Personal export should reduce shared-area rows before writing the ZIP');
		$t->assertContains('cobudget_entry_attachments', $collect, 'Personal export should include attachment paths for collected entries');
		$t->assertContains('cobudget_settlement_balances\' => []', $collect, 'Personal export should not include shared settlement balances');
		$t->assertContains('cobudget_settlement_transfers\' => []', $collect, 'Personal export should not include shared settlement transfers');
	},

	'Full restore keeps system scope separate and validates mapped users before deleting data' => function(TestRunner $t): void {
		$restore = $t->methodBody('lib/Service/BackupService.php', 'restoreFullBackup');

		$t->assertContains('$restoreLock = $this->acquireRestoreLock()', $restore, 'Full restore should take the same global restore lock');
		$t->assertContains('$this->assertBackupArchive($archive, \'system\')', $restore, 'Full restore should only accept system backups');
		$t->assertContains('$tables = $this->applyUserMapToTables($archive[\'tables\'], $userMap)', $restore, 'Full restore should apply user mappings to all table user columns');
		$t->assertContains('$settings = $this->applyUserMapToSettings($this->normalizeFullSettings($archive[\'settings\'], $tables), $userMap)', $restore, 'Full restore should apply user mappings to settings owners');
		$t->assertContains('$settings = $this->completeRestoreSettingsForTableUsers($settings, $tables)', $restore, 'Full restore should generate default settings for users referenced only by tables');
		$t->assertContains('$this->assertReferencedUsersExist($tables, array_keys($settings))', $restore, 'Full restore should reject missing mapped users before destructive changes');
		$t->assertContains('$this->assertBackupInternalReferences($tables)', $restore, 'Full restore should reject internally orphaned backup rows before destructive changes');
		$t->assertContains('$this->assertProjectMemberConsistency($tables)', $restore, 'Full restore should reject manipulated shared-area user assignments before destructive changes');
		$t->assertContains('$safetyBackup = $this->createFullBackup($storageUserId, $folderOverride, $this->getSafetyBackupRetentionCount($storageUserId))', $restore, 'Full restore should create a full safety backup first');
		$t->assertContains('$this->deleteAllBackupTables()', $restore, 'Full restore may delete all app tables only after archive validation and safety backup');
		$t->assertContains('$this->deleteAllCoBudgetSettings()', $restore, 'Full restore should delete all CoBudget settings as part of system restore');
		$t->assertContains('buildRestoreReport(\'system\', $fileName, $tables, $settings, $userMap, [])', $restore, 'Full restore should return a system restore report including user mappings');
	},

	'Backup import allows only known tables and columns' => function(TestRunner $t): void {
		$service = $t->read('lib/Service/BackupService.php');
		$normalizeTableRows = $t->methodBody('lib/Service/BackupService.php', 'normalizeTableRows');
		$filterRow = $t->methodBody('lib/Service/BackupService.php', 'filterBackupRowColumns');
		$insertRow = $t->methodBody('lib/Service/BackupService.php', 'insertRow');
		$internalReferences = $t->methodBody('lib/Service/BackupService.php', 'assertBackupInternalReferences');

		foreach ([
			'cobudget_workspaces',
			'cobudget_projects',
			'cobudget_members',
			'cobudget_categories',
			'cobudget_payment_partners',
			'cobudget_templates',
			'cobudget_entries',
			'cobudget_entry_attachments',
			'cobudget_settlements',
			'cobudget_settlement_balances',
			'cobudget_settlement_transfers',
			'cobudget_budget_goals',
			'cobudget_budget_snapshots',
		] as $table) {
			$t->assertContains("'" . $table . "' => [", $service, 'Backup column allow-list should include ' . $table);
		}

		$t->assertContains('$this->filterBackupRowColumns($table, $row)', $normalizeTableRows, 'Backup row normalization should validate every imported row');
		$t->assertContains('self::BACKUP_TABLE_COLUMNS[$table] ?? null', $filterRow, 'Backup import should use the table column allow-list');
		$t->assertContains('Backup enthält eine unbekannte Tabelle', $filterRow, 'Backup import should reject unknown tables');
		$t->assertContains('Backup enthält eine nicht erlaubte Spalte', $filterRow, 'Backup import should reject unsupported columns');
		$t->assertContains('$row = $this->filterBackupRowColumns($table, $row)', $insertRow, 'Database insert should re-check row columns before writing');
		$t->assertContains('$qb->setValue($column, $qb->createNamedParameter($value, $this->parameterType($value)))', $insertRow, 'Backup import should still use named query parameters for restored values');
		$t->assertContains('private const BACKUP_INTERNAL_REFERENCES = [', $service, 'Backup restore should maintain an allow-list of internal references to validate');
		$t->assertContains('cobudget_entry_attachments', $service, 'Backup internal reference validation should cover receipt attachment paths');
		$t->assertContains('cobudget_settlement_balances', $service, 'Backup internal reference validation should cover settlement balances');
		$t->assertContains('cobudget_budget_snapshots', $service, 'Backup internal reference validation should cover budget snapshots');
		$t->assertContains('Backup enthält verwaiste Referenzen', $internalReferences, 'Backup restore should fail with a clear message for internally orphaned rows');
	},

	'Personal export restore keeps only the safe empty-state import path while full restore validates shared-area membership' => function(TestRunner $t): void {
		$service = $t->read('lib/Service/BackupService.php');
		$validateMembers = $t->methodBody('lib/Service/BackupService.php', 'assertProjectMemberConsistency');
		$validateWorkspace = $t->methodBody('lib/Service/BackupService.php', 'assertProjectWorkspaceMatches');

		foreach ([
			'filterUserRestoreTables',
			'sharedProjectIdsForUserRestore',
			'removeSkippedProjectRows',
			'clearSkippedUserRestoreReferences',
			'removeSkippedProjectBudgets',
			'criteriaReferencesProject',
			'assertUserRestoreScope',
			'assertRowsBelongToUser',
		] as $removedMethod) {
			$t->assertNotContains($removedMethod, $service, 'Personal export restore should not keep the removed partial restore helper ' . $removedMethod);
		}
		$t->assertContains('preparePersonalImportTables(', $service, 'Personal export restore should sanitize rows before importing them into an empty target');
		$t->assertContains('assertPersonalImportTargetIsEmpty(', $service, 'Personal export restore should reject non-empty target users');
		$t->assertContains('insertTablesWithGeneratedIds(', $service, 'Personal export restore should regenerate local IDs');
		$t->assertContains("'restore_supported' => true", $service, 'Personal export manifest should mark the guarded empty-state import as supported');
		$t->assertContains("'restore_mode' => 'empty_personal_import'", $service, 'Personal export manifest should document the restore limitation');
		$t->assertContains('Bereichszahlung für einen Benutzer, der kein Mitglied des Bereichs ist', $validateMembers, 'Restore should reject entries whose paying user is not an area member');
		$t->assertContains('Bereichsdaten mit falschem Workspace', $validateWorkspace, 'Restore should reject project-scoped data with mismatching workspaces');
		$t->assertContains('Rückzahlung für einen Benutzer, der kein Mitglied des Bereichs ist', $validateMembers, 'Restore should reject settlement transfers for non-members');
	},

	'Delete and repair paths remove dependent data before parent rows disappear' => function(TestRunner $t): void {
		$destroy = $t->methodBody('lib/Controller/ProjectController.php', 'destroy');
		$integrity = $t->read('lib/Service/DataIntegrityService.php');
		$repair = $t->methodBody('lib/Service/DataIntegrityService.php', 'repair');

		$t->assertContains('$settlementIds = $this->settlementIdsForProject($id)', $destroy, 'Area delete should collect settlement ids before deleting the area');
		$t->assertContains("deleteRowsByColumnValues('cobudget_settlement_balances', 'settlement_id', \$settlementIds)", $destroy, 'Area delete should remove settlement balances before settlement headers');
		$t->assertContains("deleteRowsByColumnValues('cobudget_settlement_transfers', 'settlement_id', \$settlementIds)", $destroy, 'Area delete should remove settlement transfers before settlement headers');
		$t->assertContains("deleteRowsByColumnValues('cobudget_settlements', 'project_id', [\$id])", $destroy, 'Area delete should remove settlement headers scoped to the area');
		$t->assertContains("deleteRowsByColumnValues('cobudget_categories', 'project_id', [\$id])", $destroy, 'Area delete should remove area-scoped categories');
		$t->assertContains("deleteRowsByColumnValues('cobudget_payment_partners', 'project_id', [\$id])", $destroy, 'Area delete should remove area-scoped payment partners');
		$t->assertContains("deleteRowsByColumnValues('cobudget_templates', 'project_id', [\$id])", $destroy, 'Area delete should remove area-scoped templates');

		$t->assertContains("'repairAction' => 'delete'", $integrity, 'Integrity repair should support deleting orphan child rows');
		$t->assertContains("'repairAction' => 'deleteSettlement'", $integrity, 'Integrity repair should support deleting orphan settlement groups');
		$t->assertContains("'repairAction' => 'deleteBudgetGoal'", $integrity, 'Integrity repair should support deleting orphan budget goals with snapshots');
		$t->assertContains('cobudget_entry_attachments', $integrity, 'Integrity checks should include receipt attachment paths');
		$t->assertContains('cobudget_settlement_balances', $integrity, 'Integrity checks should include settlement balances');
		$t->assertContains('cobudget_budget_snapshots', $integrity, 'Integrity checks should include budget snapshots');
		$t->assertContains('$action = (string)($issue[\'repairAction\'] ?? \'clear\')', $repair, 'Repair should dispatch by the issue repair action');
		$t->assertContains('$this->deleteSettlementGroups($ids)', $repair, 'Repair should delete settlement child rows as a group');
		$t->assertContains('$this->deleteBudgetGoals($ids)', $repair, 'Repair should delete budget snapshots before budget goals');
	},

	'Area membership management and settlements are owner-only and transactional' => function(TestRunner $t): void {
		$requireOwner = $t->methodBody('lib/Controller/ProjectController.php', 'requireProjectOwner');
		$t->assertContains('projectOwnerInActiveWorkspace($id)', $requireOwner, 'Owner guard should use active workspace owner lookup');
		$t->assertContains('STATUS_FORBIDDEN', $requireOwner, 'Owner guard should return forbidden for non-owners');

		foreach (['update', 'destroy', 'archive', 'unarchive', 'addMember', 'removeMember', 'updateShares', 'settle'] as $method) {
			$body = $t->methodBody('lib/Controller/ProjectController.php', $method);
			$t->assertContains('requireProjectOwner($id)', $body, $method . ' should be restricted to the area creator');
		}

		foreach (['addMember', 'removeMember', 'updateShares', 'settle', 'destroy'] as $method) {
			$body = $t->methodBody('lib/Controller/ProjectController.php', $method);
			$t->assertContains('$this->db->beginTransaction()', $body, $method . ' should wrap multi-row changes in a transaction');
			$t->assertContains('$this->db->rollBack()', $body, $method . ' should roll back failed multi-row changes');
			$t->assertContains('$this->db->commit()', $body, $method . ' should commit successful multi-row changes');
		}

		$settle = $t->methodBody('lib/Controller/ProjectController.php', 'settle');
		$t->assertContains('$entryIds = $this->unsettledProjectEntryIds($id, $workspaceId)', $settle, 'Settlement should calculate the exact open entry set before writing the settlement');
		$t->assertContains('insert(\'cobudget_settlements\')', $settle, 'Settlement should create a settlement header');
		$t->assertContains('insert(\'cobudget_settlement_balances\')', $settle, 'Settlement should persist the balance snapshot');
		$t->assertContains('insert(\'cobudget_settlement_transfers\')', $settle, 'Settlement should persist repayment suggestions');
		$t->assertContains('set(\'settlement_id\', $qb->createNamedParameter($settlementId, \\PDO::PARAM_INT))', $settle, 'Settlement should link settled entries to their settlement group');
		$t->assertContains('$this->projectNotificationService->sendPreparedNotifications($settlementNotifications)', $settle, 'Settlement notifications should be sent only after the transaction succeeds');
	},

	'Workspace and user isolation guards cover project members, owners, entries, categories, and payment partners' => function(TestRunner $t): void {
		$member = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'projectMemberInActiveWorkspace');
		$owner = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'projectOwnerInActiveWorkspace');
		$entry = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'entryVisibleInActiveWorkspace');
		$category = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'categoryAvailableInActiveWorkspace');
		$paymentPartner = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'paymentPartnerAvailableInActiveWorkspace');

		foreach ([
			'entry visibility lookup' => $entry,
			'category lookup' => $category,
			'payment partner lookup' => $paymentPartner,
		] as $label => $body) {
			$t->assertContains('workspace_id', $body, $label . ' should scope queries by workspace');
			$t->assertContains('$this->userId', $body, $label . ' should include the current user in the guard');
		}
		$t->assertContains('projectVisibleForCurrentUser($projectId)', $member, 'Project member lookup should use current-user project visibility');
		$t->assertNotContains('$workspaceId = $this->getWorkspaceId()', $member, 'Project member lookup should not depend on the selected workspace');
		$t->assertContains('projectOwnerForCurrentUser($projectId)', $owner, 'Project owner lookup should use owner visibility');

		$visibleProject = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'projectVisibleForCurrentUser');
		$t->assertContains('innerJoin(\'p\', \'cobudget_members\', \'m\'', $visibleProject, 'Project visibility should require an actual member row');
		$t->assertContains('m.user_id', $visibleProject, 'Project visibility should require the current user member row');
		$ownerVisibleProject = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'projectOwnerForCurrentUser');
		$t->assertContains('owner_id', $ownerVisibleProject, 'Project owner lookup should require the current user to be the owner');
		$t->assertContains('leftJoin(\'e\', \'cobudget_members\', \'m\'', $entry, 'Entry visibility should allow shared-area entries only through membership');
		$t->assertContains('e.user_id', $entry, 'Entry visibility should still allow personal entries owned by the user');
		$t->assertContains('projectWorkspaceIdForCurrentUser($projectId)', $category, 'Project categories should resolve workspace through project membership');
		$t->assertContains('projectWorkspaceIdForCurrentUser($projectId)', $paymentPartner, 'Project payment partners should resolve workspace through project membership');
		$t->assertContains('is_hidden', $category, 'Global categories should ignore hidden rows');
		$t->assertContains('is_hidden', $paymentPartner, 'Global payment partners should ignore hidden rows');
	},

	'Receipt attachment endpoints authorize entry visibility and exact attachment rows' => function(TestRunner $t): void {
		$source = $t->read('lib/Controller/EntryController.php');
		$list = $t->methodBody('lib/Controller/EntryController.php', 'attachments');
		$upload = $t->methodBody('lib/Controller/EntryController.php', 'uploadAttachment');
		$download = $t->methodBody('lib/Controller/EntryController.php', 'downloadAttachment');
		$destroy = $t->methodBody('lib/Controller/EntryController.php', 'destroyAttachment');
		$fetchOne = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryAttachment');
		$fetchMany = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryAttachments');
		$validateUpload = $t->methodBody('lib/Controller/EntryController.php', 'validateUploadedAttachment');
		$detectMime = $t->methodBody('lib/Controller/EntryController.php', 'detectUploadedAttachmentMimeType');

		foreach ([
			'attachments list' => $list,
			'attachment upload' => $upload,
			'attachment download' => $download,
			'attachment delete' => $destroy,
		] as $label => $body) {
			$t->assertContains('receiptsEnabled()', $body, $label . ' should honor the receipts feature switch');
			$t->assertContains('entryVisibleInActiveWorkspace($id)', $body, $label . ' should require visible payment access');
		}

		$t->assertContains('$workspaceId !== null && (int)$workspaceId !== $activeWorkspaceId', $download, 'Attachment download with explicit workspace should reject mismatched workspaces');
		$t->assertNotContains('$this->workspaceId = $workspaceId', $download, 'Attachment download should not switch the active workspace for member-visible shared entries');
		$t->assertContains('fetchEntryAttachment($attachmentId, $id, (int)$activeWorkspaceId)', $download, 'Attachment download should fetch the exact attachment row for entry and workspace');
		$t->assertContains('fetchEntryAttachment($attachmentId, $id, (int)$workspaceId)', $destroy, 'Attachment delete should fetch the exact attachment row for entry and workspace');
		$t->assertContains('$this->deleteAttachmentRow($attachmentId, $id, (int)$workspaceId)', $destroy, 'Attachment delete should scope row deletion by attachment, entry, and workspace');
		$t->assertContains('owner_user_id', $upload, 'Attachment upload should persist the file owner user');
		$t->assertContains('file_path', $upload, 'Attachment upload should persist the relative Nextcloud file path for backup/restore portability');
		$t->assertContains('DEFAULT_ATTACHMENT_MAX_SIZE_BYTES = 10485760', $source, 'Attachment upload should keep a safe default 10 MB size limit');
		$t->assertContains('ATTACHMENT_MAX_SIZE_CONFIG_KEY', $source, 'Attachment upload size limit should be configurable through system config');
		$t->assertContains('ATTACHMENT_ALLOWED_TYPES_CONFIG_KEY', $source, 'Attachment upload types should be configurable through system config');
		$t->assertContains('DEFAULT_ATTACHMENT_MIME_TYPES', $source, 'Attachment upload should define a safe default MIME allow-list');
		$t->assertContains('BLOCKED_ATTACHMENT_MIME_TYPES', $source, 'Attachment upload should block risky browser-executable MIME types even when configured');
		$t->assertContains('validateUploadedAttachment($upload)', $upload, 'Attachment upload should validate size and type before reading file content');
		$t->assertContains('$mimeType = $this->detectUploadedAttachmentMimeType($upload)', $validateUpload, 'Attachment validation should sniff the MIME type from file content');
		$t->assertContains('attachmentMaxSizeBytes()', $validateUpload, 'Attachment validation should enforce the configured upload size limit');
		$t->assertContains('allowedAttachmentMimeTypes()', $validateUpload, 'Attachment validation should enforce the configured upload type allow-list');
		$t->assertContains('isSafeAttachmentMimeType($mimeType)', $source, 'Attachment config should accept only safe MIME types');
		$t->assertContains('self::HTTP_PAYLOAD_TOO_LARGE', $validateUpload, 'Oversized attachment uploads should use a clear 413 response');
		$t->assertContains('self::HTTP_UNSUPPORTED_MEDIA_TYPE', $validateUpload, 'Unsupported attachment uploads should use a clear 415 response');
		$t->assertContains('pathinfo', $validateUpload, 'Attachment validation should verify the file extension too');
		$t->assertContains('$allowedMimeTypes[$mimeType]', $validateUpload, 'Attachment validation should match extension to detected MIME type');
		$t->assertContains('finfo_file', $detectMime, 'Attachment MIME detection should prefer finfo content sniffing');
		$t->assertContains('entry_id', $fetchOne, 'Single attachment lookup should require entry id');
		$t->assertContains('workspace_id', $fetchOne, 'Single attachment lookup should require workspace id');
		$t->assertContains('entry_id', $fetchMany, 'Attachment list should require entry id');
		$t->assertContains('workspace_id', $fetchMany, 'Attachment list should require workspace id');
	},

	'User search is constrained to reduce account enumeration risk' => function(TestRunner $t): void {
		$source = $t->read('lib/Controller/UserController.php');
		$search = $t->methodBody('lib/Controller/UserController.php', 'search');
		$sharedProjectsEnabled = $t->methodBody('lib/Controller/UserController.php', 'sharedProjectsEnabled');
		$userSearchAllowed = $t->methodBody('lib/Controller/UserController.php', 'userSearchAllowed');

		$t->assertContains('USER_SEARCH_MIN_LENGTH = 3', $source, 'User search should require a minimum query length');
		$t->assertContains('USER_SEARCH_LIMIT = 10', $source, 'User search should keep result sets small');
		$t->assertContains('$term = trim($term)', $search, 'User search should normalize whitespace before querying');
		$t->assertContains('sharedProjectsEnabled()', $search, 'User search should only run when shared areas are enabled');
		$t->assertContains('userSearchAllowed()', $search, 'User search should honor the global Nextcloud enumeration setting');
		$t->assertContains('mb_strlen($term) < self::USER_SEARCH_MIN_LENGTH', $search, 'Short user searches should return no results');
		$t->assertContains('search($term, self::USER_SEARCH_LIMIT)', $search, 'User search should use the smaller result limit');
		$t->assertContains("'enable_projects'", $sharedProjectsEnabled, 'Shared user search should depend on the areas feature');
		$t->assertContains("'enable_shared_projects'", $sharedProjectsEnabled, 'Shared user search should depend on shared areas being enabled');
		$t->assertContains('shareapi_allow_share_dialog_user_enumeration', $userSearchAllowed, 'User search should respect Nextcloud user-enumeration policy');
	},

	'Budget criteria are scoped, allow-listed, and evaluated as AND within a row OR across rows' => function(TestRunner $t): void {
		$normalizeRule = $t->methodBody('lib/Controller/BudgetController.php', 'normalizeRule');
		$tagList = $t->methodBody('lib/Controller/BudgetController.php', 'tagList');
		$validate = $t->methodBody('lib/Controller/BudgetController.php', 'validateCriteria');
		$categorySelectable = $t->methodBody('lib/Controller/BudgetController.php', 'categorySelectableForBudget');
		$visibleEntries = $t->methodBody('lib/Controller/BudgetController.php', 'loadVisibleExpenseEntries');
		$evaluate = $t->methodBody('lib/Controller/BudgetController.php', 'evaluateGoal');
		$entryPersonalCents = $t->methodBody('lib/Controller/BudgetController.php', 'entryPersonalCents');
		$matches = $t->methodBody('lib/Controller/BudgetController.php', 'entryMatchesCriteria');

		$t->assertContains('private const TAG_COLUMNS = [', $t->read('lib/Controller/BudgetController.php'), 'Budget criteria should use a central tag allow-list');
		$t->assertContains('array_key_exists($tag, self::TAG_COLUMNS) ? $tag : \'\'', $normalizeRule, 'Budget criteria should drop unknown tags');
		$t->assertContains('$allowed = array_keys(self::TAG_COLUMNS)', $tagList, 'Legacy budget tag lists should use the same tag allow-list');
		$t->assertContains('!$this->projectMemberInActiveWorkspace($rule[\'projectId\'])', $validate, 'Budget project criteria should require area membership');
		$t->assertContains('!$this->categorySelectableForBudget($rule[\'categoryId\'], $workspaceId)', $validate, 'Budget category criteria should require a selectable expense category');
		$t->assertContains('c.type', $categorySelectable, 'Budget category selection should only allow expense categories');
		$t->assertContains('c.workspace_id', $categorySelectable, 'Budget category selection should scope personal/project categories by workspace');
		$t->assertContains('m.user_id', $categorySelectable, 'Budget project categories should require current user project membership');
		$t->assertContains('e.type', $visibleEntries, 'Budget evaluation should load only expense entries');
		$t->assertContains('e.workspace_id', $visibleEntries, 'Budget evaluation should scope visible entries by workspace');
		$t->assertContains('e.user_id', $visibleEntries, 'Budget evaluation should include personal entries owned by the user');
		$t->assertContains('m.user_id', $visibleEntries, 'Budget evaluation should include shared entries only through membership');
		$t->assertContains('$spentCents += $this->entryPersonalCents($entry, $shares)', $evaluate, 'Budget progress should use the user personal share, not total shared amounts');
		$t->assertContains('entryShareCentsForUser', $entryPersonalCents, 'Shared budget entries should be reduced to the current user share');
		$t->assertContains('if ($rules === [])', $matches, 'Budget goals without criteria should match all visible expenses');
		$t->assertContains('continue;', $matches, 'Budget criteria should require all selected values in a rule to match before returning true');
		$t->assertContains('return true;', $matches, 'Budget criteria should match if at least one rule matches');
		$t->assertContains('return false;', $matches, 'Budget criteria should reject entries that match no rule');
	},

	'Settlement history returns scoped settlement groups with their exact entries' => function(TestRunner $t): void {
		$settlements = $t->methodBody('lib/Controller/ProjectController.php', 'settlements');
		$history = $t->methodBody('lib/Controller/ProjectController.php', 'settlementHistory');
		$count = $t->methodBody('lib/Controller/ProjectController.php', 'settlementEntryCount');
		$entries = $t->methodBody('lib/Controller/ProjectController.php', 'loadSettlementEntries');

		$t->assertContains('projectVisibleForCurrentUser($id)', $settlements, 'Settlement history endpoint should be visible only to area members');
		$t->assertContains('settlementHistory($id, $workspaceId, null, true)', $settlements, 'Settlement history endpoint should include entry groups for each settlement');
		$t->assertContains('cobudget_settlements', $history, 'Settlement history should read settlement group headers');
		$t->assertContains('project_id', $history, 'Settlement history should scope groups by project');
		$t->assertContains('workspace_id', $history, 'Settlement history should scope groups by workspace');
		$t->assertContains('loadSettlementBalances($settlementId)', $history, 'Settlement history should include stored balance snapshots');
		$t->assertContains('loadSettlementTransfers($settlementId)', $history, 'Settlement history should include stored repayment suggestions');
		$t->assertContains('loadSettlementEntries($settlementId, $projectId, $workspaceId)', $history, 'Settlement history should load entries only when requested');
		$t->assertContains('settlement_id', $count, 'Settlement entry count should count entries linked to the settlement id');
		$t->assertContains('e.settlement_id', $entries, 'Settlement entries should be filtered by settlement id');
		$t->assertContains('e.project_id', $entries, 'Settlement entries should be filtered by project id');
		$t->assertContains('e.workspace_id', $entries, 'Settlement entries should be filtered by workspace id');
		$t->assertContains('attachEntryAttachmentCounts($entries, $workspaceId)', $entries, 'Settlement entries should keep attachment metadata scoped to the workspace');
	},
];
