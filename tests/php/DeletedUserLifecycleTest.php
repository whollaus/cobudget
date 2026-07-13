<?php

declare(strict_types=1);

namespace CoBudget\Tests;

use CoBudget\Tests\Support\TestRunner;

return [
	'Deleted users are represented by non-reusable tombstone participants' => function(TestRunner $t): void {
		$initialMigration = $t->read('lib/Migration/Version000001Date20260713000000.php');
		$upgradeMigration = $initialMigration;
		$participants = $t->read('lib/Service/ParticipantService.php');

		foreach ([$initialMigration, $upgradeMigration] as $migration) {
			$t->assertContains("createTable('cobudget_deleted_users')", $migration, 'Fresh installs and upgrades should create the deleted-user registry');
			$t->assertContains("addUniqueIndex(['tombstone_id']", $migration, 'Former participant identifiers must be unique');
		}
		$t->assertContains("public const FORMER_PREFIX = 'former:'", $participants, 'Former participants should use a reserved non-login identifier namespace');
		$t->assertContains('bin2hex(random_bytes(16))', $participants, 'Former participant identifiers should not expose or reuse the deleted Nextcloud user id');
		$t->assertContains("from('cobudget_deleted_users')", $participants, 'Participant display names should resolve from the deleted-user registry');
	},

	'Nextcloud user deletion fails closed when CoBudget cannot preserve shared data' => function(TestRunner $t): void {
		$application = $t->read('lib/AppInfo/Application.php');
		$listener = $t->methodBody('lib/Listener/BeforeUserDeletedListener.php', 'handle');

		$t->assertContains('BeforeUserDeletedEvent::class, BeforeUserDeletedListener::class', $application, 'CoBudget should prepare shared data before Nextcloud removes the account');
		$t->assertContains('$this->userDeletionService->deleteUser(', $listener, 'The listener should invoke the transactional deletion service');
		$t->assertContains('throw $e;', $listener, 'A failed CoBudget cleanup should abort account deletion instead of leaving partial financial data');
		$t->assertContains("'user_id' => \$user->getUID()", $listener, 'Deletion failures should identify the affected account in the server log');
		$t->assertContains("'exception_message' => \$e->getMessage()", $listener, 'Deletion failures should expose their exact internal cause in the server log');
	},

	'Unused Nextcloud accounts bypass CoBudget cleanup safely' => function(TestRunner $t): void {
		$deleteUser = $t->methodBody('lib/Service/UserDeletionService.php', 'deleteUser');
		$references = $t->methodBody('lib/Service/UserDeletionService.php', 'hasUserReferences');

		$t->assertContains('if (!$this->hasUserReferences($userId))', $deleteUser, 'Accounts without CoBudget data should not enter the destructive cleanup transaction');
		foreach (['cobudget_members', 'cobudget_entries', 'cobudget_entry_shares', 'cobudget_settlements', 'cobudget_budget_goals'] as $table) {
			$t->assertContains($table, $references, 'The no-data preflight should inspect ' . $table);
		}
		$t->assertContains("eq('old_value'", $references, 'Historical user references should prevent an unsafe early return');
		$t->assertContains("eq('new_value'", $references, 'Historical user references should prevent an unsafe early return');
	},

	'Orphan hashtag cleanup uses an unambiguous joined column' => function(TestRunner $t): void {
		$cleanup = $t->methodBody('lib/Service/UserDeletionService.php', 'deleteOrphanHashtags');

		$t->assertContains("selectAlias('h.id', 'id')", $cleanup, 'The joined hashtag query must not select the ambiguous unqualified id column');
		$t->assertContains("leftJoin('h', 'cobudget_entry_hashtags', 'eh'", $cleanup, 'Orphan detection should retain the hashtag relation join');
	},

	'Deleted-user cleanup preserves shared areas and immutable financial references' => function(TestRunner $t): void {
		$deleteUser = $t->methodBody('lib/Service/UserDeletionService.php', 'deleteUser');
		$projectsForUser = $t->methodBody('lib/Service/UserDeletionService.php', 'projectsForUser');
		$remap = $t->methodBody('lib/Service/UserDeletionService.php', 'remapRemainingSharedReferences');
		$recurrences = $t->methodBody('lib/Service/UserDeletionService.php', 'stopDeletedUserRecurrences');
		$attachments = $t->methodBody('lib/Service/UserDeletionService.php', 'detachSurvivingAttachmentCopies');
		$ensureProjections = $t->methodBody('lib/Service/UserDeletionService.php', 'ensureOpenProjectProjections');
		$personalWorkspaces = $t->methodBody('lib/Service/UserDeletionService.php', 'personalWorkspaceIdsForMember');

		$t->assertContains('$this->db->beginTransaction()', $deleteUser, 'The complete lifecycle change should be atomic');
		$t->assertContains('$this->participants->createFormerParticipant($displayName)', $deleteUser, 'Shared references should move to an anonymized former participant');
		$t->assertContains('$this->updateProjectOwner($projectId, $activeMembers[0])', $deleteUser, 'Areas owned by the deleted user should transfer to an active member');
		$t->assertContains('$this->ensureOpenProjectProjections($projectId)', $deleteUser, 'Every open allocation must have a personal row before the account disappears');
		$t->assertContains('$this->personalWorkspaceIdsForMember($userId, $survivingProjectIds)', $deleteUser, 'The deleted members personal projection workspaces must be preserved');
		$t->assertContains('array_merge($survivingWorkspaceIds, $preservedPersonalWorkspaceIds)', $deleteUser, 'Area and personal projection workspaces should survive together');
		$t->assertContains('$this->entryProjectionService->ensureOpenSharedEntry($entryId)', $ensureProjections, 'Projection repair must retain stored exact-cent snapshots');
		$t->assertContains("select('personal_workspace_id')", $personalWorkspaces, 'Membership workspace assignments are part of the preserved lifecycle state');
		$t->assertContains("eq('entry_kind',", $personalWorkspaces, 'Existing personal projection workspace ids are preserved as a second safety source');
		$t->assertContains("idsByStringColumn('cobudget_workspaces', 'user_id', \$userId)", $projectsForUser, 'Cleanup should include areas in workspaces still owned by the deleted account after an earlier area ownership transfer');
		$t->assertContains("in('workspace_id'", $projectsForUser, 'Owned workspace areas should be included in the lifecycle query');
		foreach (['cobudget_members', 'cobudget_entries', 'cobudget_entry_shares', 'cobudget_settlement_balances', 'cobudget_settlement_transfers'] as $table) {
			$t->assertContains($table, $remap, 'Shared financial references should retain the former participant in ' . $table);
		}
		$t->assertContains("set('recurrence_interval'", $recurrences, 'Recurring payments owned by or assigned directly to the deleted account should stop');
		$t->assertNotContains("set('reminder_date'", $recurrences, 'Shared reminders should remain available to surviving active members');
		$t->assertContains("set('source_attachment_id'", $attachments, 'Surviving receipt copies should become independent before the deleted owner metadata disappears');
		$t->assertContains("neq('owner_user_id'", $attachments, 'Only receipt copies belonging to surviving users should be detached');
	},

	'Former members block new allocations until settlement but can then leave the future split' => function(TestRunner $t): void {
		$projectMembers = $t->methodBody('lib/Controller/ProjectController.php', 'projectMembers');
		$removeMember = $t->methodBody('lib/Controller/ProjectController.php', 'removeMember');
		$addMember = $t->methodBody('lib/Controller/ProjectController.php', 'addMember');
		$updateShares = $t->methodBody('lib/Controller/ProjectController.php', 'updateShares');
		$createEntry = $t->methodBody('lib/Controller/EntryController.php', 'create');
		$entryValidation = $t->methodBody('lib/Controller/EntryController.php', 'validateEntryUserId');
		$splitValidation = $t->methodBody('lib/Controller/EntryController.php', 'validateActiveSplitTarget');
		$recurringRun = $t->methodBody('lib/Cron/RecurringEntriesJob.php', 'run');
		$settingsView = $t->read('src/views/ProjectSettingsView.vue');
		$entryModal = $t->read('src/components/AddEntryModal.vue');

		$t->assertContains("'isFormer' => \$participant['isFormer']", $projectMembers, 'Area member responses should expose former-member state');
		$t->assertNotContains('$this->participantService->isFormer($userId)', $removeMember, 'A former member may leave the future split after all shared payments are settled');
		$t->assertContains('$this->entryProjectionService->detachSettledMember($id, $userId)', $removeMember, 'Removing a former member should first preserve their settled personal payments');
		$t->assertContains('$this->projectHasFormerMember($id)', $addMember, 'A former member should block adding another member');
		$t->assertContains('$this->projectHasFormerMember($id)', $updateShares, 'A former member should block editing the future default split');
		$t->assertContains('$this->projectHasFormerMember($projectId)', $createEntry, 'A former member should block new shared payments');
		$t->assertContains('$this->participantService->isActive($entryUserId)', $entryValidation, 'A former participant cannot be selected as payer for a new payment');
		$t->assertContains('$this->participantService->isActive($splitUserId)', $splitValidation, 'A former participant cannot be selected as a new direct allocation target');
		$t->assertContains("\$entryKind === 'shared' && \$this->projectHasFormerMember", $recurringRun, 'Shared recurrence generation should pause while an area contains a former member');
		$t->assertNotContains('member.id !== project.owner_id && !member.isFormer', $settingsView, 'The area UI should allow the owner to remove a former non-owner after settlement');
		$t->assertContains('removeFormerMemberMessage()', $settingsView, 'Former-member removal should clearly explain that settled history remains unchanged');
		$t->assertContains('this.normalizedProjectMembers.length > 1', $entryModal, 'The split selector should remain available when an area includes former members');
	},

	'Ownership transfer is owner-only atomic and restricted to active members' => function(TestRunner $t): void {
		$routes = $t->read('appinfo/routes.php');
		$transfer = $t->methodBody('lib/Controller/ProjectController.php', 'transferOwnership');

		$t->assertContains("'name' => 'project#transferOwnership'", $routes, 'Area ownership transfer should have an explicit API route');
		$t->assertContains('requireProjectOwner($id)', $transfer, 'Only the current owner should transfer an area');
		$t->assertContains('$this->participantService->isActive($userId)', $transfer, 'The new owner must be an active Nextcloud account');
		$t->assertContains('$this->db->beginTransaction()', $transfer, 'Ownership transfer should be atomic');
		$t->assertContains("update('cobudget_projects')", $transfer, 'Ownership transfer should update the canonical area owner');
	},

	'Backups resets and user resets account for former participants' => function(TestRunner $t): void {
		$backup = $t->read('lib/Service/BackupService.php');
		$resetAll = $t->read('lib/Command/ResetAllCommand.php');
		$userReset = $t->methodBody('lib/Service/UserResetService.php', 'preview');

		$t->assertContains("'cobudget_deleted_users'", $backup, 'Full backups should preserve the former-participant registry');
		$t->assertContains('Backup references an unknown former member.', $backup, 'Restore should reject unknown former participant identifiers');
		$t->assertContains("'cobudget_deleted_users'", $resetAll, 'The global destructive reset should clear the former-participant registry');
		$t->assertContains('$this->participantService->isFormer(', $userReset, 'Personal reset should detect former participants before deleting an owned shared area');
	},
];
