<?php

declare(strict_types=1);

namespace CoBudget\Tests;

require_once dirname(__DIR__, 2) . '/lib/Service/EntryShareCalculator.php';

use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\EntryShareCalculator;

return [
	'Entry share snapshots preserve exact cents for a 50/50 split' => function (TestRunner $t): void {
		$allocations = EntryShareCalculator::calculate(
			5287,
			'project_shares',
			null,
			'user-a',
			['user-a' => 5000, 'user-b' => 5000],
		);

		$t->assertSame(2644, $allocations['user-a']['amount_cents'], 'The first deterministic member should receive the half-up remainder cent');
		$t->assertSame(2643, $allocations['user-b']['amount_cents'], 'The final member should receive the exact residual');
		$t->assertSame(5287, array_sum(array_column($allocations, 'amount_cents')), 'Stored member amounts must equal the original payment exactly');
		$t->assertSame(10000, array_sum(array_column($allocations, 'share_basis_points')), 'Stored member percentages must equal 100 percent exactly');
	},

	'Entry share snapshots normalize arbitrary member weights deterministically' => function (TestRunner $t): void {
		$allocations = EntryShareCalculator::calculate(
			100,
			'project_shares',
			null,
			'user-a',
			['user-a' => 1, 'user-b' => 1, 'user-c' => 1],
		);

		$t->assertSame([3333, 3333, 3334], array_column($allocations, 'share_basis_points'), 'Equal weights should normalize to exact basis points in stable member order');
		$t->assertSame([33, 33, 34], array_column($allocations, 'amount_cents'), 'Residual cents should be assigned without losing or inventing money');
	},

	'Single-user share snapshots assign the complete payment to the selected member' => function (TestRunner $t): void {
		$allocations = EntryShareCalculator::calculate(
			12345,
			'single_user',
			'user-b',
			'user-a',
			['user-a' => 5000, 'user-b' => 5000],
		);

		$t->assertSame([
			'user-b' => [
				'share_basis_points' => 10000,
				'amount_cents' => 12345,
			],
		], $allocations, 'Single-user allocation should ignore the area default percentages');
	},

	'Entry share snapshots fall back safely when member shares are missing' => function (TestRunner $t): void {
		$allocations = EntryShareCalculator::calculate(999, 'project_shares', null, 'payer', []);

		$t->assertSame(999, $allocations['payer']['amount_cents'], 'A missing member list should preserve the payment for its payer');
		$t->assertSame(10000, $allocations['payer']['share_basis_points'], 'The fallback payer should receive the complete basis-point share');
	},

	'Payment share snapshots are wired into writes, settlements, exports, and cleanup' => function (TestRunner $t): void {
		$migration = $t->read('lib/Migration/Version000003Date20260710000000.php');
		$entryController = $t->read('lib/Controller/EntryController.php');
		$projectController = $t->read('lib/Controller/ProjectController.php');
		$backupService = $t->read('lib/Service/BackupService.php');
		$shareService = $t->read('lib/Service/EntryShareService.php');
		$recurringJob = $t->read('lib/Cron/RecurringEntriesJob.php');
		$resetService = $t->read('lib/Service/UserResetService.php');
		$workspaceController = $t->read('lib/Controller/WorkspaceController.php');

		$t->assertContains("createTable('cobudget_entry_shares')", $migration, 'The migration should create the immutable payment-share table');
		$t->assertContains("addUniqueIndex(['entry_id', 'user_id']", $migration, 'Each payment/member snapshot should be unique');
		$t->assertContains('$this->entryShareService->syncEntry($id)', $entryController, 'Payment creates and allocation-changing edits should store fresh snapshots');
		$t->assertContains("array_key_exists('snapshot_share_cents'", $projectController, 'Area balances should prefer stored personal cents');
		$t->assertContains("'cobudget_entry_shares'", $backupService, 'Backup and restore should include exact share snapshots');
		$t->assertContains('$storedEntryShares[$entryId][$sourceUserId]', $backupService, 'Personal imports should use the exported exact personal amount');
		$personalShares = $t->methodBody('lib/Service/EntryShareService.php', 'personalSharesForEntries');
		$t->assertContains("groupBy('entry_id')", $personalShares, 'Snapshot existence should be loaded without transferring every member allocation');
		$t->assertContains('$shares[$entryId] = 0', $personalShares, 'A payment snapshot without the current member must resolve to an exact zero share');
		$t->assertContains("andWhere(\$qb->expr()->eq('user_id'", $personalShares, 'Only the current members amount should be loaded after snapshot existence is known');
		$t->assertContains('$this->entryShareService->syncEntry($newEntryId)', $recurringJob, 'Generated recurring payments should store their own current snapshot');
		$t->assertContains('$this->entryShareService->sharesForEntries', $resetService, 'Reset transfers should preserve stored member amounts');
		$t->assertContains('$this->entryShareService->deleteForEntries($entryIds)', $workspaceController, 'Workspace cleanup should remove payment-share rows before payments');
	},

	'Changing area defaults does not rewrite existing payment snapshots' => function (TestRunner $t): void {
		$updateShares = $t->methodBody('lib/Controller/ProjectController.php', 'updateShares');

		$t->assertNotContains('syncEntry', $updateShares, 'Changing area defaults must affect only future or explicitly edited payments');
		$t->assertNotContains('cobudget_entry_shares', $updateShares, 'Area-default updates must not rewrite historical payment allocations');
	},
];
