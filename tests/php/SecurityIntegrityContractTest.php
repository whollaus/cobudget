<?php

declare(strict_types=1);

namespace CoBudget\Tests;

use CoBudget\Tests\Support\TestRunner;

return [
	'User restore is scoped, mapped, reported, and protected by a safety backup' => function(TestRunner $t): void {
		$restore = $t->methodBody('lib/Service/BackupService.php', 'restoreBackup');
		$scope = $t->methodBody('lib/Service/BackupService.php', 'assertUserRestoreScope');
		$collect = $t->methodBody('lib/Service/BackupService.php', 'collectBackupData');

		$t->assertContains('$restoreLock = $this->acquireRestoreLock()', $restore, 'User restore should take a global restore lock');
		$t->assertContains('$this->releaseRestoreLock($restoreLock)', $restore, 'User restore should always release the restore lock');
		$t->assertContains('$this->assertBackupArchive($archive, \'user\')', $restore, 'User restore should only accept user backups');
		$t->assertContains('$userMap = $this->normalizeUserMap($userMap)', $restore, 'User restore should normalize explicit user mappings');
		$t->assertContains('$userMap[$sourceUserId] = $userId', $restore, 'User restore should auto-map the backup owner to the target user');
		$t->assertContains('filterUserRestoreTables($this->applyUserMapToTables($archive[\'tables\'], $userMap), $skippedRows)', $restore, 'User restore should apply user mapping before filtering user-scope tables');
		$t->assertContains('$this->assertUserRestoreScope($tables, $userId)', $restore, 'User restore should reject shared or foreign data before deleting current rows');
		$t->assertContains('$this->assertReferencedUsersExist($tables, [$userId])', $restore, 'User restore should reject unknown mapped users before writing rows');
		$t->assertContains('$this->assertBackupInternalReferences($tables)', $restore, 'User restore should reject internally orphaned backup rows before writing rows');
		$t->assertContains('$safetyBackup = $this->createBackup($userId, $folderOverride, $this->getSafetyBackupRetentionCount($userId))', $restore, 'User restore should create a safety backup before destructive changes');
		$t->assertContains('$this->db->beginTransaction()', $restore, 'User restore should run destructive changes inside a transaction');
		$t->assertContains('$currentData = $this->collectBackupData($userId)', $restore, 'User restore should delete the target user scope based on current collected data');
		$t->assertContains('$this->deleteRowsByBackupData($this->filterUserRestoreTables($currentData[\'tables\']))', $restore, 'User restore should filter current data with the same user-restore scope before deleting rows');
		$t->assertContains('$this->deleteSettingsForUsers([$userId])', $restore, 'User restore should delete only the target user settings');
		$t->assertContains('buildRestoreReport(\'user\', $fileName, $tables, [$userId => $settings], $userMap, $skippedRows)', $restore, 'User restore should return a restore report with user mappings and skipped rows');

		$t->assertContains('if ((string)($project[\'owner_id\'] ?? \'\') !== $userId)', $scope, 'User restore should reject projects not owned by the target user');
		$t->assertContains('gemeinsame Bereiche, die nicht diesem Benutzer gehoeren', $scope, 'User restore should explain shared out-of-scope project data');
		$t->assertContains('$this->assertRowsBelongToUser($tables, \'cobudget_workspaces\', \'user_id\', $userId)', $scope, 'User restore should scope workspaces to the target user');
		$t->assertContains('$this->assertRowsBelongToUser($tables, \'cobudget_budget_goals\', \'user_id\', $userId)', $scope, 'User restore should scope budget goals to the target user');
		$t->assertContains('$projectId !== null && !isset($ownedProjectIds[$projectId])', $scope, 'User restore should reject project-scoped rows outside target-owned projects');
		$t->assertContains('$projectId === null && (string)($entry[\'user_id\'] ?? \'\') !== $userId', $scope, 'User restore should reject personal payments from another user');
		$t->assertContains('Beleg-Pfade ohne passende Zahlung im Benutzer-Scope', $scope, 'User restore should reject attachment paths without an in-scope payment');
		$t->assertContains('Abrechnungsdaten ohne passende Abrechnung im Benutzer-Scope', $scope, 'User restore should reject orphan settlement balances or transfers');

		$t->assertContains('$workspaces = $this->fetchRowsByUser(\'cobudget_workspaces\', $userId)', $collect, 'User backup should collect all workspaces belonging to the user');
		$t->assertContains('$projectIds = $this->fetchProjectIdsForUser($userId, $workspaceIds)', $collect, 'User backup should collect only projects owned by the user');
		$t->assertContains('$entries = $this->fetchEntries($userId, $workspaceIds, $projectIds)', $collect, 'User backup should collect personal entries and entries in owned projects');
		$t->assertContains('cobudget_entry_attachments', $collect, 'User backup should include attachment paths for collected entries');
		$t->assertContains('cobudget_settlement_balances', $collect, 'User backup should include settlement balances for collected settlements');
		$t->assertContains('cobudget_settlement_transfers', $collect, 'User backup should include settlement transfers for collected settlements');
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
		$filterUserRestoreTables = $t->methodBody('lib/Service/BackupService.php', 'filterUserRestoreTables');

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
		$t->assertContains('$this->clearSkippedUserRestoreReferences($tables, \'category_id\', $skippedReferenceIds[\'cobudget_categories\'])', $filterUserRestoreTables, 'User restore should clear references to skipped global categories');
		$t->assertContains('$this->clearSkippedUserRestoreReferences($tables, \'payment_partner_id\', $skippedReferenceIds[\'cobudget_payment_partners\'])', $filterUserRestoreTables, 'User restore should clear references to skipped global payment partners');
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
			'project member lookup' => $member,
			'project owner lookup' => $owner,
			'entry visibility lookup' => $entry,
			'category lookup' => $category,
			'payment partner lookup' => $paymentPartner,
		] as $label => $body) {
			$t->assertContains('$workspaceId = $this->getWorkspaceId()', $body, $label . ' should resolve the active workspace centrally');
			$t->assertContains('workspace_id', $body, $label . ' should scope queries by workspace');
			$t->assertContains('$this->userId', $body, $label . ' should include the current user in the guard');
		}

		$t->assertContains('innerJoin(\'p\', \'cobudget_members\', \'m\'', $member, 'Project member lookup should require an actual member row');
		$t->assertContains('owner_id', $owner, 'Project owner lookup should require the current user to be the owner');
		$t->assertContains('leftJoin(\'e\', \'cobudget_members\', \'m\'', $entry, 'Entry visibility should allow shared-area entries only through membership');
		$t->assertContains('e.user_id', $entry, 'Entry visibility should still allow personal entries owned by the user');
		$t->assertContains('$projectId !== null && !$this->projectMemberInActiveWorkspace($projectId)', $category, 'Project categories should require project membership');
		$t->assertContains('$projectId !== null && !$this->projectMemberInActiveWorkspace($projectId)', $paymentPartner, 'Project payment partners should require project membership');
		$t->assertContains('is_hidden', $category, 'Global categories should ignore hidden rows');
		$t->assertContains('is_hidden', $paymentPartner, 'Global payment partners should ignore hidden rows');
	},

	'Receipt attachment endpoints authorize entry visibility and exact attachment rows' => function(TestRunner $t): void {
		$list = $t->methodBody('lib/Controller/EntryController.php', 'attachments');
		$upload = $t->methodBody('lib/Controller/EntryController.php', 'uploadAttachment');
		$download = $t->methodBody('lib/Controller/EntryController.php', 'downloadAttachment');
		$destroy = $t->methodBody('lib/Controller/EntryController.php', 'destroyAttachment');
		$fetchOne = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryAttachment');
		$fetchMany = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryAttachments');

		foreach ([
			'attachments list' => $list,
			'attachment upload' => $upload,
			'attachment download' => $download,
			'attachment delete' => $destroy,
		] as $label => $body) {
			$t->assertContains('receiptsEnabled()', $body, $label . ' should honor the receipts feature switch');
			$t->assertContains('entryVisibleInActiveWorkspace($id)', $body, $label . ' should require visible payment access');
		}

		$t->assertContains('workspaceBelongsToUser($workspaceId)', $download, 'Attachment download with explicit workspace should reject workspaces not owned by the user');
		$t->assertContains('$this->workspaceId = $workspaceId', $download, 'Attachment download should switch to the explicitly authorized workspace before checking entry visibility');
		$t->assertContains('fetchEntryAttachment($attachmentId, $id, (int)$activeWorkspaceId)', $download, 'Attachment download should fetch the exact attachment row for entry and workspace');
		$t->assertContains('fetchEntryAttachment($attachmentId, $id, (int)$workspaceId)', $destroy, 'Attachment delete should fetch the exact attachment row for entry and workspace');
		$t->assertContains('$this->deleteAttachmentRow($attachmentId, $id, (int)$workspaceId)', $destroy, 'Attachment delete should scope row deletion by attachment, entry, and workspace');
		$t->assertContains('owner_user_id', $upload, 'Attachment upload should persist the file owner user');
		$t->assertContains('file_path', $upload, 'Attachment upload should persist the relative Nextcloud file path for backup/restore portability');
		$t->assertContains('entry_id', $fetchOne, 'Single attachment lookup should require entry id');
		$t->assertContains('workspace_id', $fetchOne, 'Single attachment lookup should require workspace id');
		$t->assertContains('entry_id', $fetchMany, 'Attachment list should require entry id');
		$t->assertContains('workspace_id', $fetchMany, 'Attachment list should require workspace id');
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
		$t->assertContains('!$this->projectMemberInActiveWorkspace($rule[\'projectId\'])', $validate, 'Budget project criteria should require membership in the active workspace');
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

		$t->assertContains('projectMemberInActiveWorkspace($id)', $settlements, 'Settlement history endpoint should be visible only to area members');
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
