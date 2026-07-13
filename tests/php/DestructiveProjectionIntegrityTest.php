<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\ProjectionGraphValidator;

$validTables = static function(): array {
	return [
		'cobudget_workspaces' => [
			['id' => 1, 'user_id' => 'alice'],
			['id' => 2, 'user_id' => 'bob'],
		],
		'cobudget_projects' => [
			['id' => 10, 'owner_id' => 'alice', 'workspace_id' => 1],
		],
		'cobudget_members' => [
			['id' => 20, 'project_id' => 10, 'user_id' => 'alice', 'personal_workspace_id' => 1],
			['id' => 21, 'project_id' => 10, 'user_id' => 'bob', 'personal_workspace_id' => 2],
		],
		'cobudget_entries' => [
			['id' => 100, 'user_id' => 'alice', 'project_id' => 10, 'entry_kind' => 'shared', 'source_entry_id' => null, 'is_locked' => false, 'is_settled' => false, 'workspace_id' => 1, 'amount_cents' => 101],
			['id' => 101, 'user_id' => 'alice', 'project_id' => 10, 'entry_kind' => 'personal', 'source_entry_id' => 100, 'is_locked' => true, 'is_settled' => false, 'workspace_id' => 1, 'amount_cents' => 51],
			['id' => 102, 'user_id' => 'bob', 'project_id' => 10, 'entry_kind' => 'personal', 'source_entry_id' => 100, 'is_locked' => true, 'is_settled' => false, 'workspace_id' => 2, 'amount_cents' => 50],
		],
		'cobudget_entry_shares' => [
			['id' => 200, 'entry_id' => 100, 'user_id' => 'alice', 'share_basis_points' => 5000, 'amount_cents' => 51, 'personal_entry_id' => 101],
			['id' => 201, 'entry_id' => 100, 'user_id' => 'bob', 'share_basis_points' => 5000, 'amount_cents' => 50, 'personal_entry_id' => 102],
		],
	];
};

return [
	'Projection graph validator accepts a complete open shared payment' => function(TestRunner $t) use ($validTables): void {
		require_once $t->path('lib/Service/ProjectionGraphValidator.php');
		$t->assertSame([], ProjectionGraphValidator::validate($validTables()), 'A complete source/share/projection graph should be valid');
	},

	'Projection graph validator detects amount and workspace mismatches' => function(TestRunner $t) use ($validTables): void {
		require_once $t->path('lib/Service/ProjectionGraphValidator.php');
		$tables = $validTables();
		$tables['cobudget_entries'][2]['workspace_id'] = 1;
		$tables['cobudget_entries'][2]['amount_cents'] = 49;
		$codes = array_column(ProjectionGraphValidator::validate($tables), 'code');
		$t->assertTrue(in_array('projection_workspace_mismatch', $codes, true), 'A projection in another users workspace must be rejected');
		$t->assertTrue(in_array('projection_amount_mismatch', $codes, true), 'A projection amount that differs from its immutable share must be rejected');
	},

	'Settled share history may remain after a reset removes the personal row' => function(TestRunner $t) use ($validTables): void {
		require_once $t->path('lib/Service/ProjectionGraphValidator.php');
		$tables = $validTables();
		$tables['cobudget_entries'][0]['is_settled'] = true;
		$tables['cobudget_entries'] = [$tables['cobudget_entries'][0]];
		foreach ($tables['cobudget_entry_shares'] as &$share) {
			$share['personal_entry_id'] = null;
		}
		unset($share);
		$t->assertSame([], ProjectionGraphValidator::validate($tables), 'Immutable settled allocations may outlive reset personal rows');
	},

	'Settlement may detach independent personal payments from immutable share history' => function(TestRunner $t) use ($validTables): void {
		require_once $t->path('lib/Service/ProjectionGraphValidator.php');
		$tables = $validTables();
		$tables['cobudget_entries'][0]['is_settled'] = true;
		foreach ([1, 2] as $index) {
			$tables['cobudget_entries'][$index]['source_entry_id'] = null;
			$tables['cobudget_entries'][$index]['is_locked'] = false;
			$tables['cobudget_entries'][$index]['settlement_id'] = 300;
		}
		foreach ($tables['cobudget_entry_shares'] as &$share) {
			$share['personal_entry_id'] = null;
		}
		unset($share);

		$t->assertSame([], ProjectionGraphValidator::validate($tables), 'Settled personal payments may keep their area origin while becoming independent records');
	},

	'Destructive paths share projection deletion and post-operation validation' => function(TestRunner $t): void {
		$projection = $t->methodBody('lib/Service/EntryProjectionService.php', 'prepareEntryDeletion');
		$workspace = $t->methodBody('lib/Controller/WorkspaceController.php', 'destroy');
		$reset = $t->methodBody('lib/Service/UserResetService.php', 'reset');
		$deleteProject = $t->methodBody('lib/Service/UserResetService.php', 'deleteProjectTree');
		$restore = $t->methodBody('lib/Service/BackupService.php', 'restoreFullBackup');
		$backupValidation = $t->methodBody('lib/Service/BackupService.php', 'assertProjectMemberConsistency');

		$t->assertContains("from('cobudget_entries')", $projection, 'Deletion closure should inspect persisted entries');
		$t->assertContains("in('source_entry_id'", $projection, 'Deleting a source should include every linked personal projection');
		$t->assertContains("update('cobudget_entry_shares')", $projection, 'Deleting a personal row should clear reverse share pointers');
		$t->assertContains('Eine offene persoenliche Projektion', $projection, 'Deleting a locked projection without its source should be blocked');
		$t->assertContains('prepareEntryDeletion(', $workspace, 'Workspace deletion should use the shared deletion closure');
		$t->assertContains('prepareEntryDeletion(', $reset, 'Reset should use the shared deletion closure for personal rows');
		$t->assertContains('prepareEntryDeletion(', $deleteProject, 'Reset project deletion should use the shared deletion closure');
		$t->assertContains('assertProjectionIntegrity()', $workspace, 'Workspace deletion should validate before commit');
		$t->assertContains('assertProjectionIntegrity()', $reset, 'Reset should validate before commit');
		$t->assertContains('assertProjectionIntegrity()', $restore, 'Full restore should validate the inserted database before commit');
		$t->assertContains('ProjectionGraphValidator::assertValid($tables)', $backupValidation, 'Backup payloads should use the same graph rules before current data is deleted');
	},

	'Integrity inspection covers all new projection references' => function(TestRunner $t): void {
		$integrity = $t->read('lib/Service/DataIntegrityService.php');
		$command = $t->read('lib/Command/CheckDataIntegrityCommand.php');
		$adminSettings = $t->read('src/components/AdminSettings.vue');
		foreach (['source_entry_id', 'personal_entry_id', 'personal_workspace_id'] as $column) {
			$t->assertContains("'column' => '" . $column . "'", $integrity, 'Integrity checks should cover ' . $column);
		}
		$t->assertContains('ProjectionGraphValidator::validate(', $integrity, 'OCC/admin integrity checks should report semantic projection problems');
		$t->assertContains('ProjectionGraphValidator::assertValid(', $integrity, 'Destructive transactions should be able to assert semantic projection integrity');
		$t->assertContains("\$report['projectionIssueCount']", $command, 'OCC integrity checks should fail for semantic projection problems');
		$t->assertContains("\$report['projectionIssues']", $command, 'OCC integrity output should list semantic projection problems');
		$t->assertContains('integrityProjectionCount', $adminSettings, 'Admin data quality should expose semantic projection problems');
	},
];
