<?php

declare(strict_types=1);

namespace CoBudget\Tests;

use CoBudget\Tests\Support\TestRunner;

return [
	'Shared payments have explicit source projection and receipt schema contracts' => function (TestRunner $t): void {
		$initial = $t->read('lib/Migration/Version000001Date20260713000000.php');
		$shares = $initial;

		foreach ([$initial] as $migration) {
			$t->assertContains("'personal_workspace_id'", $migration, 'Area members need a stable personal Basis workspace');
			$t->assertContains("'entry_kind'", $migration, 'Payments must distinguish shared sources from personal rows');
			$t->assertContains("'source_entry_id'", $migration, 'Personal rows need their shared source reference');
			$t->assertContains("'is_locked'", $migration, 'Open personal projections need an immutable state');
			$t->assertContains("'allocation_basis_points'", $migration, 'Personal rows must retain their exact allocation percentage');
			$t->assertContains("addUniqueIndex(['source_entry_id', 'user_id']", $migration, 'A source may have at most one personal row per participant');
			$t->assertContains("'source_attachment_id'", $migration, 'Receipt copies must retain their source attachment link while open');
			$t->assertContains("addUniqueIndex(['entry_id', 'source_attachment_id']", $migration, 'A personal row may receive each source receipt only once');
		}
		foreach ([$shares] as $migration) {
			$t->assertContains("'personal_entry_id'", $migration, 'Exact share snapshots must reference their materialized personal row');
		}
		$t->assertContains("'created_by'", $initial, 'Fresh installs must store the actual payment creator separately from the payer');
		$t->assertContains("addColumn('created_by', 'string', ['notnull' => false, 'length' => 64])", $initial, 'Fresh installs must not use an invalid empty default for the creator column');
		$t->assertContains("addIndex(['created_by']", $initial, 'Creator authorization needs an indexed payment reference');
	},

	'Open shared payments materialize exact locked personal rows' => function (TestRunner $t): void {
		$sync = $t->methodBody('lib/Service/EntryProjectionService.php', 'syncSharedEntry');
		$ensure = $t->methodBody('lib/Service/EntryProjectionService.php', 'ensureOpenSharedEntry');
		$materialize = $t->methodBody('lib/Service/EntryProjectionService.php', 'materializeStoredShares');
		$values = $t->methodBody('lib/Service/EntryProjectionService.php', 'personalEntryValues');
		$workspace = $t->methodBody('lib/Service/EntryProjectionService.php', 'personalWorkspaceId');

		$t->assertContains('$this->entryShareService->syncEntry($sourceEntryId)', $sync, 'Projection starts with an immutable exact-cent share snapshot');
		$t->assertContains('$this->materializeStoredShares($source, $shareRows)', $sync, 'Normal source writes materialize the newly stored snapshot');
		$t->assertContains('if ($shareRows === [])', $ensure, 'Lifecycle repair should only calculate a snapshot when none exists');
		$t->assertContains('$this->entryShareService->syncEntry($sourceEntryId)', $ensure, 'A legacy source without a snapshot can be repaired before account deletion');
		$t->assertContains('$amountCents <= 0', $materialize, 'Zero-cent allocations must not create a personal payment');
		$t->assertContains('$this->clearShareProjection($sourceEntryId, $userId)', $materialize, 'A changed zero-cent allocation removes its old projection link');
		$t->assertContains('$this->insertPersonalEntry(', $materialize, 'A missing personal projection should be inserted');
		$t->assertContains('$this->updatePersonalEntry(', $materialize, 'An existing locked projection should be replaced from the source');
		$t->assertContains('An unlocked personal payment cannot be overwritten', $materialize, 'Settlement must create a hard synchronization boundary');
		$t->assertContains('$this->deleteStaleLockedProjections(', $materialize, 'Participants removed by a new allocation should not leave stale locked rows');
		$t->assertContains('$this->attachmentProjectionService->syncSharedEntry($sourceEntryId)', $materialize, 'Receipt copies should follow every successful source synchronization');

		$t->assertContains("'entry_kind' => \$qb->createNamedParameter(self::PERSONAL_KIND)", $values, 'Materialized rows are canonical personal payments');
		$t->assertContains("'created_by' => \$qb->createNamedParameter", $values, 'Personal projections preserve the creator of their shared source');
		$t->assertContains("'project_id' => \$qb->createNamedParameter((int)\$source['project_id']", $values, 'Personal projections retain their source area for area-scoped budgets and analytics');
		$t->assertContains("'is_locked' => \$qb->createNamedParameter(true", $values, 'Materialized rows remain locked before settlement');
		$t->assertContains("'amount_cents' => \$qb->createNamedParameter(\$amountCents", $values, 'Materialized rows use the exact allocated cents');
		$t->assertContains("'split_mode' => \$qb->createNamedParameter('single_user')", $values, 'Each personal projection belongs fully to its participant');
		$t->assertContains("'split_user_id' => \$qb->createNamedParameter(\$userId)", $values, 'The personal split target is the projection owner');
		$t->assertContains("'recurrence_interval' => \$nullableString(null)", $values, 'Personal projections must never generate recurring payments');
		$t->assertContains("'reminder_date' => \$nullableInt(null)", $values, 'Personal projections must never send duplicate reminders');
		$t->assertContains("'workspace_id' => \$qb->createNamedParameter(\$workspaceId", $values, 'Each projection is stored in that users personal Basis workspace');

		$t->assertContains("select('personal_workspace_id')", $workspace, 'The member-specific personal workspace is stable across synchronizations');
		$t->assertContains('$this->defaultWorkspaceId($userId)', $workspace, 'Missing member workspace assignments fall back to Basis');
		$t->assertContains("set('personal_workspace_id'", $workspace, 'The resolved Basis workspace is persisted on membership');
	},

	'Creating editing and deleting a shared source is atomic with its projections' => function (TestRunner $t): void {
		$create = $t->methodBody('lib/Controller/EntryController.php', 'create');
		$update = $t->methodBody('lib/Controller/EntryController.php', 'update');
		$destroy = $t->methodBody('lib/Controller/EntryController.php', 'destroy');

		$t->assertContains("\$entryKind = \$this->projectUsesSharedEntries(\$projectId) ? 'shared' : 'personal'", $create, 'Only areas with multiple members create shared source rows');
		$t->assertContains('$this->workspaceIdForEntryScope($projectId)', $create, 'A shared source always receives the canonical area workspace');
		$t->assertContains('$this->db->beginTransaction()', $create, 'Source creation and personal projection are atomic');
		$t->assertContains('$this->entryProjectionService->syncSharedEntry($id)', $create, 'Source creation materializes all personal rows before commit');
		$t->assertContains('$this->db->rollBack()', $create, 'A failed projection rolls back source creation');

		$t->assertContains('An open shared payment cannot be moved to another area.', $update, 'A shared source cannot move between areas');
		$t->assertContains('The workspace of an existing payment cannot be changed', $update, 'A payment cannot move between workspaces');
		$t->assertContains('This personal payment is controlled by an open shared payment.', $update, 'Locked personal rows reject direct edits');
		$t->assertContains('Settled payments cannot be edited', $update, 'Settled source history is immutable');
		$t->assertContains('$this->entryProjectionService->syncSharedEntry($id)', $update, 'Source edits rebuild all locked personal projections');
		$t->assertContains('$this->entryProjectionService->personalEntriesForSource($id)', $update, 'Source edits snapshot personal values before and after projection');
		$t->assertContains('$this->recordPersonalProjectionHistories(', $update, 'Every changed personal projection receives its own value history');
		$t->assertContains('$historyContext = $this->newEntryHistoryContext()', $update, 'Source and personal history rows share the same editor and change group');
		$t->assertContains("\$isReleasedPersonal ? 'personal'", $update, 'A released personal payment must never become a shared source again');
		$t->assertContains("\$splitMode = 'single_user'", $update, 'A released personal payment remains fully assigned to its owner');

		$t->assertContains('Settled payments cannot be deleted', $destroy, 'Settled source and personal history cannot be removed through the normal endpoint');
		$t->assertContains("\$id = (int)\$entry['source_entry_id']", $destroy, 'Deleting a locked personal view resolves its shared source first');
		$t->assertContains('$this->canDeleteSharedEntry($entry)', $destroy, 'Open shared deletion is explicitly authorized');
		$t->assertContains('Only the payment creator or area admin can delete this shared payment.', $destroy, 'Unauthorized area members receive a stable forbidden response');
		$t->assertContains('$this->entryProjectionService->prepareEntryDeletion([$id])', $destroy, 'Deleting an open source expands to its complete projection graph');
		$t->assertContains('$this->deleteEntryRowsByIds($entryIds)', $destroy, 'Source and all personal projections are deleted together');
		$t->assertContains('$this->db->beginTransaction()', $destroy, 'Source deletion and metadata cleanup are atomic');
	},

	'Payment creator identity survives all supported creation and lifecycle paths' => function (TestRunner $t): void {
		$create = $t->methodBody('lib/Controller/EntryController.php', 'create');
		$recurring = $t->methodBody('lib/Cron/RecurringEntriesJob.php', 'run');
		$transfer = $t->methodBody('lib/Service/UserResetService.php', 'insertTransferredPersonalEntry');
		$backup = $t->read('lib/Service/BackupService.php');
		$userDeletion = $t->read('lib/Service/UserDeletionService.php');

		$t->assertContains("'created_by' => \$qb->createNamedParameter((string)\$this->userId)", $create, 'API creation records the authenticated creator independently of the payer');
		$t->assertContains("'created_by' => \$insertQb->createNamedParameter", $recurring, 'Recurring payments preserve their series creator');
		$t->assertContains("'created_by' => \$qb->createNamedParameter(\$recipientUserId)", $transfer, 'Reset transfers create independent recipient-owned personal payments');
		$t->assertContains("'cobudget_entries' => ['user_id', 'created_by', 'split_user_id']", $backup, 'Full backup user mapping includes payment creators');
		$t->assertContains("\$row['created_by'] = \$userId", $backup, 'Personal restore makes imported standalone payments belong to the target account');
		$t->assertContains("['cobudget_entries', 'created_by']", $userDeletion, 'Deleted Nextcloud accounts are removed from creator references');
	},

	'Settlement snapshots history and releases personal payments atomically' => function (TestRunner $t): void {
		$settle = $t->methodBody('lib/Controller/ProjectController.php', 'settle');
		$unlock = $t->methodBody('lib/Service/EntryProjectionService.php', 'unlockForSettlement');

		$t->assertContains('$this->requireProjectOwner($id)', $settle, 'Only the area owner may settle');
		$t->assertContains('$this->calculateBalanceSnapshotCents(', $settle, 'Settlement records balances from exact source allocations');
		$t->assertContains("insert('cobudget_settlements')", $settle, 'Settlement creates an immutable history group');
		$t->assertContains("insert('cobudget_settlement_balances')", $settle, 'Settlement records every member balance');
		$t->assertContains("insert('cobudget_settlement_transfers')", $settle, 'Settlement records the repayment proposal');
		$t->assertContains('$this->entryProjectionService->unlockForSettlement($entryIds, $settlementId, $createdAt)', $settle, 'Personal rows are released in the settlement transaction');
		$t->assertContains("eq('entry_kind', \$qb->createNamedParameter('shared'))", $settle, 'Only shared source rows become settled source history');
		$t->assertContains('$this->db->commit()', $settle, 'All settlement state commits together');

		$t->assertContains("set('is_locked'", $unlock, 'Settlement unlocks the personal rows');
		$t->assertContains("set('source_entry_id'", $unlock, 'Released personal rows no longer depend on the shared source');
		$t->assertContains("set('allocation_basis_points'", $unlock, 'Released personal rows no longer carry open-allocation state');
		$t->assertContains("set('settlement_id'", $unlock, 'Released rows retain their settlement group');
		$t->assertContains("set('settled_at'", $unlock, 'Released rows retain the settlement timestamp');
		$t->assertContains("update('cobudget_entry_shares')", $unlock, 'Settlement clears reverse links while retaining immutable allocation snapshots');
		$t->assertContains("set('personal_entry_id'", $unlock, 'Settled shares no longer control an independent personal payment');
		$t->assertContains("set('source_attachment_id'", $unlock, 'Receipt copies become independent at settlement');
	},

	'Personal projection histories store personal amounts on the personal payment' => function (TestRunner $t): void {
		$history = $t->methodBody('lib/Controller/EntryController.php', 'recordPersonalProjectionHistories');
		$context = $t->methodBody('lib/Controller/EntryController.php', 'newEntryHistoryContext');
		$entries = $t->methodBody('lib/Service/EntryProjectionService.php', 'personalEntriesForSource');

		$t->assertContains("\$oldEntry['amount_cents'] = 0", $history, 'A newly positive personal allocation starts its own amount history at zero');
		$t->assertContains('$this->recordEntryHistory(', $history, 'Projection history is written directly for the personal payment id');
		$t->assertContains("(int)\$newEntry['workspace_id']", $history, 'Personal history stays scoped to the participants Basis workspace');
		$t->assertContains("\$context['change_group']", $t->methodBody('lib/Controller/EntryController.php', 'recordEntryHistory'), 'Personal and shared changes retain one traceable change group');
		$t->assertContains('$entries[$userId] = $entry', $entries, 'Projection snapshots are compared by participant identity');
		$t->assertContains("'changed_by' => \$changedBy", $context, 'Projection history records the actual editor');
	},

	'Area membership and default shares cannot change while shared payments are open' => function (TestRunner $t): void {
		$project = $t->read('lib/Controller/ProjectController.php');
		$add = $t->methodBody('lib/Controller/ProjectController.php', 'addMember');
		$remove = $t->methodBody('lib/Controller/ProjectController.php', 'removeMember');
		$shares = $t->methodBody('lib/Controller/ProjectController.php', 'updateShares');
		$destroy = $t->methodBody('lib/Controller/ProjectController.php', 'destroy');

		foreach ([$add, $remove, $shares] as $method) {
			$t->assertContains('$this->projectHasOpenSharedPayments(', $method, 'Area structure changes must reject open shared source payments');
		}
		$t->assertContains('$this->requireProjectOwner($id)', $add, 'Only the owner may add members');
		$t->assertContains('$this->requireProjectOwner($id)', $remove, 'Only the owner may remove members');
		$t->assertContains('$this->requireProjectOwner($id)', $shares, 'Only the owner may change default shares');
		$t->assertContains('$this->entryProjectionService->detachSettledMember($id, $userId)', $remove, 'A departing member keeps independent settled personal payments');
		$t->assertContains('$this->projectHasPayments($id)', $destroy, 'Permanent deletion must reject areas that still contain payments');
		$t->assertContains('$this->projectHasLifecycleHistory($id)', $destroy, 'Permanent deletion must preserve settlement and audit history');
		$t->assertContains('$this->budgetGoalsReferenceProject($id, $workspaceId, $categoryIds)', $destroy, 'Permanent deletion must not orphan budget criteria');
		$t->assertContains("eq('entry_kind'", $t->methodBody('lib/Controller/ProjectController.php', 'projectHasOpenSharedPayments'), 'Open-payment guards inspect only shared sources');
		$t->assertContains("delete('cobudget_projects')", $destroy, 'A genuinely empty area should be deleted instead of archived');
		$t->assertNotContains("set('is_archived'", $destroy, 'Permanent deletion must remain distinct from archiving');
		$t->assertContains("addUniqueIndex(['project_id', 'user_id']", $t->read('lib/Migration/Version000001Date20260713000000.php'), 'Concurrent member additions are blocked by the database too');
	},

	'Areas with one member use direct personal payments and cannot become shared while payments exist' => function (TestRunner $t): void {
		$scope = $t->methodBody('lib/Controller/EntryController.php', 'normalizeEntryScope');
		$create = $t->methodBody('lib/Controller/EntryController.php', 'create');
		$update = $t->methodBody('lib/Controller/EntryController.php', 'update');
		$show = $t->methodBody('lib/Controller/ProjectController.php', 'show');
		$projectHasPayments = $t->methodBody('lib/Controller/ProjectController.php', 'projectHasPayments');
		$memberManagementLock = $t->methodBody('lib/Controller/ProjectController.php', 'memberManagementLockReason');
		$dashboard = $t->methodBody('lib/Controller/ProjectController.php', 'calculateProjectDashboard');
		$addMember = $t->methodBody('lib/Controller/ProjectController.php', 'addMember');
		$settle = $t->methodBody('lib/Controller/ProjectController.php', 'settle');
		$projectView = $t->read('src/views/ProjectDetail.vue');
		$projectSettings = $t->read('src/views/ProjectSettingsView.vue');

		$t->assertContains('$this->projectUsesSharedEntries($projectId)', $scope, 'The area list resolves its storage mode from the current member count');
		$t->assertContains("? 'shared' : 'personal'", $create, 'A one-member area creates an ordinary personal payment');
		$t->assertContains('$this->projectUsesSharedEntries($projectId)', $update, 'Moving or editing a direct area payment keeps the target area storage mode consistent');
			$t->assertContains('count($members) > 1', $show, 'Solo area details skip meaningless balance and repayment calculations');
			$t->assertContains("\$project['is_shared'] = count(\$members) > 1", $show, 'Area details expose an authoritative shared-state flag');
		$t->assertContains('$isSharedArea = count($members) > 1', $dashboard, 'Solo area dashboards read personal rows while shared dashboards read sources');
		$t->assertContains("eq('entry_kind', \$qb->createNamedParameter('personal'))", $dashboard, 'Solo area dashboards query personal payments');
		$t->assertContains('$this->projectHasPayments(', $addMember, 'A second member cannot be added while any payments still belong to a solo area');
		$t->assertContains("eq('project_id'", $projectHasPayments, 'Solo area locks detect every payment linked to the globally unique area id');
		$t->assertNotContains("eq('workspace_id'", $projectHasPayments, 'Solo area locks must also detect imported personal payments stored in the users personal workspace');
		$t->assertNotContains("eq('entry_kind'", $projectHasPayments, 'Solo area locks must not miss restored or historical payment representations');
		$t->assertContains("'solo_payments'", $memberManagementLock, 'Area details expose the solo-payment member-management lock reason');
		$t->assertContains("'open_shared_payments'", $memberManagementLock, 'Area details expose the open-shared-payment member-management lock reason');
		$t->assertContains("\$project['member_management_locked']", $show, 'Area details expose the member-management lock state');
		$t->assertContains('count($members) <= 1', $settle, 'A one-member area cannot create a meaningless settlement');
			$t->assertContains('return this.hasAdditionalProjectMembers;', $projectView, 'Members and settlement controls are hidden for one-member areas');
			$t->assertContains('this.project?.is_shared !== undefined', $projectView, 'The area UI prefers the server-side shared-state flag');
		$t->assertContains("entryScope: this.hasAdditionalProjectMembers ? 'shared' : 'personal'", $projectView, 'The area table asks for the correct payment representation');
		$t->assertContains('v-if="!memberManagementLocked"', $projectSettings, 'Area settings hide the complete member and split section while structural changes are blocked');
		$t->assertContains('member_management_lock_reason', $projectSettings, 'Area settings choose a contextual lock explanation from the server reason');
	},

	'Departing members keep personal lookups and independent settled rows' => function (TestRunner $t): void {
		$detach = $t->methodBody('lib/Service/EntryProjectionService.php', 'detachSettledMember');
		$category = $t->methodBody('lib/Service/EntryProjectionService.php', 'personalCategoryId');
		$partner = $t->methodBody('lib/Service/EntryProjectionService.php', 'personalPaymentPartnerId');

		$t->assertContains('$this->settledPersonalEntriesForMember($projectId, $userId)', $detach, 'Only already released personal rows are detached');
		$t->assertContains('$this->personalCategoryId(', $detach, 'Area-only categories are converted before membership deletion');
		$t->assertContains('$this->personalPaymentPartnerId(', $detach, 'Area-only payment partners are converted before membership deletion');
		$t->assertContains("set('project_id'", $detach, 'Detached payments no longer depend on the old area');
		$t->assertContains("set('source_entry_id'", $detach, 'Detached payments no longer depend on source history');
		$t->assertContains("set('allocation_basis_points'", $detach, 'Detached payments are ordinary personal payments');
		$t->assertContains("set('settlement_id'", $detach, 'Detached payments no longer depend on the areas settlement rows');
		$t->assertContains("eq('is_locked'", $detach, 'Only released rows may be detached');

		$t->assertContains("eq('project_id'", $category, 'Only area-specific categories are cloned');
		$t->assertContains("'project_id' => \$insert->createNamedParameter(null", $category, 'The cloned category is personal');
		$t->assertContains("eq('project_id'", $partner, 'Only area-specific payment partners are cloned');
		$t->assertContains("'project_id' => \$insert->createNamedParameter(null", $partner, 'The cloned payment partner is personal');
	},

	'Receipts are physical per-user copies while a shared source is open' => function (TestRunner $t): void {
		$attachments = $t->read('lib/Service/EntryAttachmentProjectionService.php');
		$sync = $t->methodBody('lib/Service/EntryAttachmentProjectionService.php', 'syncSharedEntry');
		$copy = $t->methodBody('lib/Service/EntryAttachmentProjectionService.php', 'createCopy');
		$upload = $t->methodBody('lib/Controller/EntryController.php', 'uploadAttachment');
		$delete = $t->methodBody('lib/Controller/EntryController.php', 'destroyAttachment');
		$guard = $t->methodBody('lib/Controller/EntryController.php', 'attachmentMutationError');

		$t->assertContains('$this->participantService->isActive(', $sync, 'No new Files copy is written for an inactive or former participant');
		$t->assertContains('$this->copyExists(', $sync, 'Receipt projection is idempotent');
		$t->assertContains('$this->deleteAttachment($copy)', $sync, 'Removed source receipts remove stale projected copies while open');
		$t->assertContains('$this->rootFolder->getUserFolder($targetUserId)', $copy, 'Every participant receives a physical file in their own Nextcloud Files');
		$t->assertContains("'source_attachment_id'", $copy, 'Projected receipt metadata links to its source while open');
		$t->assertContains('$createdFile->delete()', $copy, 'A failed metadata insert compensates its newly created file');
		$t->assertContains('$this->attachmentProjectionService->syncSharedEntry($id)', $upload, 'Uploading a source receipt propagates it before commit');
		$t->assertContains('$this->attachmentProjectionService->deleteSourceAttachmentCopies($attachmentId)', $delete, 'Deleting a source receipt removes every open personal copy');
		$t->assertContains('Only the receipt owner can delete this file', $delete, 'A member cannot physically delete another users receipt directly');
		$t->assertContains('This receipt is controlled by an open shared payment.', $guard, 'Locked personal receipt copies cannot be changed independently');
		$t->assertContains('Receipts of settled shared payments cannot be changed.', $guard, 'Settled source receipt history is immutable');
		$t->assertContains('source_attachment_id', $attachments, 'The projection service consistently tracks receipt lineage');
	},

	'Shared automation runs only on sources and notifies all active members' => function (TestRunner $t): void {
		$recurring = $t->read('lib/Cron/RecurringEntriesJob.php');
		$reminders = $t->read('lib/Cron/RemindersJob.php');
		$recipients = $t->methodBody('lib/Cron/RemindersJob.php', 'reminderRecipientUserIds');

		$t->assertContains("'entry_kind' => \$insertQb->createNamedParameter(\$entryKind)", $recurring, 'A recurring shared source remains a shared source');
		$t->assertContains('$this->entryProjectionService->syncSharedEntry($newEntryId)', $recurring, 'A generated shared source creates fresh locked personal rows');
		$t->assertContains("if (\$entryKind === 'shared')", $recurring, 'Only shared generated payments trigger projection');
		$t->assertContains("\$entryKind !== 'shared'", $recipients, 'Ordinary personal reminders stay personal');
		$t->assertContains("from('cobudget_members')", $recipients, 'Shared reminders resolve the complete current area membership');
		$t->assertContains('$this->participantService->isActive($userId)', $recipients, 'Shared reminders skip inactive or former accounts');
		$t->assertContains('array_keys($userIds)', $recipients, 'Shared reminder recipients are de-duplicated');
		$t->assertContains("isNotNull('e.reminder_date')", $reminders, 'Only rows with an actual reminder are processed');
	},

	'Personal overview analytics budgets and exports never double-count shared sources' => function (TestRunner $t): void {
		$personalOverview = $t->methodBody('lib/Controller/EntryController.php', 'buildVisibleEntriesQuery');
		$analytics = $t->methodBody('lib/Controller/AnalyticsController.php', 'loadAnalyticsEntries');
		$budgets = $t->methodBody('lib/Controller/BudgetController.php', 'loadVisibleExpenseEntries');
		$export = $t->methodBody('lib/Service/BackupService.php', 'fetchEntries');

		foreach ([$personalOverview, $analytics, $budgets, $export] as $query) {
			$t->assertContains("'personal'", $query, 'Personal financial surfaces must query only materialized personal payments');
			$t->assertContains('user_id', $query, 'Personal financial surfaces must scope rows to the current user');
		}
		$t->assertContains("'shared'", $personalOverview, 'The area-specific list has an explicit shared-source branch');
		$t->assertContains("innerJoin('e', 'cobudget_members'", $personalOverview, 'Area source visibility still requires membership');
		$t->assertNotContains('cobudget_members', $analytics, 'Analytics must not reconstruct personal shares from shared sources');
		$t->assertNotContains('cobudget_members', $budgets, 'Budgets must not reconstruct personal shares from shared sources');
		$t->assertNotContains('project_id', $export, 'Personal export should not collect payments merely because the user shares an area');
	},

	'Planned personal surfaces derive shared recurrences once from their source' => function (TestRunner $t): void {
		$visible = $t->methodBody('lib/Controller/EntryController.php', 'buildVisibleEntriesQuery');
		$aggregate = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryAggregateData');
		$rows = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryRows');
		$ordering = $t->methodBody('lib/Controller/EntryController.php', 'applyEntryOrdering');
		$filters = $t->methodBody('lib/Controller/EntryController.php', 'applyFilters');
		$upcoming = $t->methodBody('lib/Controller/AnalyticsController.php', 'loadUpcomingEntries');
		$projectionValues = $t->methodBody('lib/Service/EntryProjectionService.php', 'personalEntryValues');

		$t->assertContains('bool $includeSharedFutureSources = false', $t->read('lib/Controller/EntryController.php'), 'Shared future sources are an explicit personal-list mode');
		$t->assertContains("isNull('e.source_entry_id')", $visible, 'Linked personal projections are excluded from planned lists');
		$t->assertContains("eq('e.entry_kind', \$qb->createNamedParameter('shared'))", $visible, 'Planned lists include the recurring shared source');
		$t->assertContains("eq('m.personal_workspace_id'", $visible, 'Shared planned entries use the members personal workspace');
		$t->assertContains("gt('future_visibility_share.amount_cents'", $visible, 'Zero-percent members do not receive a planned personal row');
		$t->assertContains('buildVisibleEntriesQuery($workspaceId, $isFuture)', $aggregate, 'Planned dashboard totals use the future-aware visibility query');
		$t->assertContains('buildVisibleEntriesQuery($workspaceId, $isFuture)', $rows, 'Planned dashboard rows use the same future-aware visibility query');
		$t->assertContains('COALESCE(e.recurrence_next_date, e.date)', $ordering, 'Planned rows sort by the next occurrence instead of the source date');
		$t->assertContains('COALESCE(e.recurrence_next_date, e.date)', $filters, 'Planned date filters use the next occurrence instead of the source date');

		$t->assertContains("isNull('e.source_entry_id')", $upcoming, 'Analytics upcoming entries exclude linked personal projections');
		$t->assertContains("eq('e.entry_kind', \$qb->createNamedParameter('shared'))", $upcoming, 'Analytics upcoming entries use shared recurrence sources');
		$t->assertContains("gt('future_share.amount_cents'", $upcoming, 'Analytics hides zero-percent shared projections');
		$t->assertContains("'recurrence_interval' => \$nullableString(null)", $projectionValues, 'Materialized personal rows deliberately carry no recurrence automation');
	},

	'Budgets and historical analytics consume exact personal rows without recalculation' => function (TestRunner $t): void {
		$analytics = $t->methodBody('lib/Controller/AnalyticsController.php', 'loadAnalyticsEntries');
		$budgetEntries = $t->methodBody('lib/Controller/BudgetController.php', 'loadVisibleExpenseEntries');
		$budgetEvaluation = $t->methodBody('lib/Controller/BudgetController.php', 'evaluateGoal');
		$budgetCriteria = $t->methodBody('lib/Controller/BudgetController.php', 'entryMatchesCriteria');
		$snapshotEntries = $t->methodBody('lib/Service/BudgetSnapshotService.php', 'loadVisibleExpenseEntries');
		$snapshotEvaluation = $t->methodBody('lib/Service/BudgetSnapshotService.php', 'evaluateGoal');
		$snapshotCriteria = $t->methodBody('lib/Service/BudgetSnapshotService.php', 'entryMatchesCriteria');

		foreach ([$analytics, $budgetEntries, $snapshotEntries] as $query) {
			$t->assertContains("eq('e.entry_kind'", $query, 'Historical personal surfaces require materialized personal rows');
			$t->assertContains("eq('e.user_id'", $query, 'Historical personal surfaces require the current owner');
			$t->assertContains("eq('e.workspace_id'", $query, 'Historical personal surfaces require the active personal workspace');
			$t->assertNotContains('cobudget_members', $query, 'Historical personal surfaces must not reconstruct shared source allocations');
		}
		foreach ([$budgetEntries, $snapshotEntries] as $query) {
			$t->assertContains("'e.project_id'", $query, 'Area-scoped budgets retain the area reference from personal projections');
		}
		foreach ([$budgetCriteria, $snapshotCriteria] as $matcher) {
			$t->assertContains("(int)(\$entry['project_id'] ?? 0)", $matcher, 'Area criteria match against the personal projection area');
			$t->assertContains("(int)\$rule['projectId']", $matcher, 'Area criteria use the selected budget area id');
		}

		$t->assertContains('$spentCents += max(0, $this->amountCentsFromRow($entry) ?? 0)', $budgetEvaluation, 'Live budgets sum exact personal cents');
		$t->assertContains('$spentCents += max(0, $this->amountCentsFromRow($entry) ?? 0)', $snapshotEvaluation, 'Budget history sums the same exact personal cents');
		$t->assertNotContains('entryShareCentsForUser', $t->read('lib/Controller/BudgetController.php'), 'Live budgets do not recalculate shares');
		$t->assertNotContains('entryShareCentsForUser', $t->read('lib/Service/BudgetSnapshotService.php'), 'Budget snapshots do not recalculate shares');
	},

	'Area collaboration analytics stay source-based and workspace-scoped' => function (TestRunner $t): void {
		$shared = $t->methodBody('lib/Controller/AnalyticsController.php', 'loadSharedProjectEntries');
		$projectShares = $t->methodBody('lib/Controller/AnalyticsController.php', 'loadProjectShares');

		$t->assertContains("eq('e.entry_kind', \$qb->createNamedParameter('shared'))", $shared, 'Collaboration totals use the shared source rows');
		$t->assertContains("eq('m.personal_workspace_id'", $shared, 'Collaboration totals stay in the current members workspace');
		$t->assertContains('amountCents', $shared, 'Collaboration totals retain the complete shared payment amount');
		$t->assertContains('storedOrCalculatedShareCents', $shared, 'Collaboration details retain the current users exact personal share');
		$t->assertContains("eq('me.personal_workspace_id'", $projectShares, 'Area share metadata stays in the current personal workspace');
	},

	'Personal restore imports projections as independent personal payments' => function (TestRunner $t): void {
		$backup = $t->read('lib/Service/BackupService.php');
		$prepare = $t->methodBody('lib/Service/BackupService.php', 'preparePersonalImportTables');

		$t->assertContains("'entry_kind'", $backup, 'Backups allowlist the explicit payment kind');
		$t->assertContains("'source_entry_id'", $backup, 'Backups understand source lineage');
		$t->assertContains("'is_locked'", $backup, 'Backups understand projection locking');
		$t->assertContains("'allocation_basis_points'", $backup, 'Backups understand exact allocation metadata');
		$t->assertContains("'personal_entry_id'", $backup, 'Backups understand share-to-personal references');
		$t->assertContains("'source_attachment_id'", $backup, 'Backups understand receipt-copy lineage');
		$t->assertContains("\$row['entry_kind'] = 'personal'", $backup, 'Imported user payments become ordinary personal rows');
		$t->assertContains("\$row['source_entry_id'] = null", $backup, 'Imported user payments do not depend on a foreign source row');
		$t->assertContains("\$row['is_locked'] = false", $backup, 'Imported user payments are immediately editable');
		$t->assertContains("\$row['allocation_basis_points'] = null", $backup, 'Imported user payments do not retain area allocation state');
		$t->assertContains("\$row['recurrence_interval'] = null", $backup, 'Imported projections do not inherit shared recurrence automation');
		$t->assertContains("\$row['project_id'] = null", $backup, 'Imported projections are detached from a foreign shared area');
		$t->assertContains("\$prepared['cobudget_entry_shares'] = []", $prepare, 'Independent personal imports do not retain shared allocation snapshots');
	},

	'Personal migration preserves all workspaces and remaps workspace-bound settings' => function (TestRunner $t): void {
		$service = $t->read('lib/Service/BackupService.php');
		$collect = $t->methodBody('lib/Service/BackupService.php', 'collectBackupData');
		$prepare = $t->methodBody('lib/Service/BackupService.php', 'preparePersonalImportTables');
		$lookupPreparation = $t->methodBody('lib/Service/BackupService.php', 'preparePersonalLookupRows');
		$restore = $t->methodBody('lib/Service/BackupService.php', 'restoreBackup');
		$remapSettings = $t->methodBody('lib/Service/BackupService.php', 'remapPersonalImportSettings');
		$generatedInsert = $t->methodBody('lib/Service/BackupService.php', 'insertTablesWithGeneratedIds');

		$t->assertContains("fetchRowsByUser('cobudget_workspaces', \$userId)", $collect, 'Every workspace owned by the exporting user is collected, independent of the active workspace');
		$t->assertContains('$this->defaultOldWorkspaceId($workspaces)', $prepare, 'The actual Basis workspace is the fallback instead of the first arbitrary row');
		$t->assertContains('$memberWorkspaceByProject[$projectId] ?? $primaryWorkspaceId', $prepare, 'Legacy shared rows prefer the members recorded personal workspace');
		$t->assertContains('$workspaceId !== null && isset($workspaceIds[$workspaceId])', $prepare, 'Materialized personal shares preserve an existing owned workspace');
		$t->assertContains("\$row['is_locked'] = false", $prepare, 'Every imported personal payment is editable even when its source was still open');
		$t->assertContains("\$row['source_entry_id'] = null", $prepare, 'Imported personal payments no longer depend on source rows from the old server');

		$t->assertContains('$workspaceUsage[$oldId]', $lookupPreparation, 'Global and area-specific lookups are copied into every workspace that uses them');
		$t->assertContains("\$copy['workspace_id'] = \$targetWorkspaceId", $lookupPreparation, 'Portable lookup copies stay in the same workspace as their payments');
		$t->assertContains("\$copy['is_global'] = \$isGlobal", $lookupPreparation, 'Portable lookup rows retain whether their source was global');
		$t->assertContains('existingVisibleGlobalLookupIds()', $generatedInsert, 'Restore checks target-server global categories and payment partners before inserting private copies');
		$t->assertContains('$idMaps[$table][$oldId] = (int)$existingGlobalId', $generatedInsert, 'Matching target globals are reused and mapped to imported references');
		$t->assertContains("\$row['is_global'] = false", $generatedInsert, 'Missing target globals are preserved as private imported values');

		foreach (['hidden_workspaces', 'hidden_categories', 'hidden_payment_partners'] as $setting) {
			$t->assertContains("\$settings['" . $setting . "']", $remapSettings, 'Restore remaps the ID-based setting ' . $setting);
		}
		$t->assertContains("\$settings['default_start_page']", $remapSettings, 'A project start page follows the regenerated project ID');
		$t->assertContains('$idMaps = $this->insertTablesWithGeneratedIds($tables)', $restore, 'Generated IDs are retained for settings remapping');
		$t->assertContains('preparePersonalExportSettings(', $service, 'The archive stores settings against its already portable rows');
	},
];
