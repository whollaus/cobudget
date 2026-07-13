<?php

declare(strict_types=1);

namespace CoBudget\Tests;

use CoBudget\Tests\Support\TestRunner;

return [
	'Routes point to existing controller methods and have unique verb/url pairs' => function(TestRunner $t): void {
		$config = require $t->path('appinfo/routes.php');
		$t->assertTrue(isset($config['routes']) && is_array($config['routes']), 'routes.php should return a routes array');

		$controllerClassName = static fn(string $controller): string => str_replace(' ', '', ucwords(str_replace('_', ' ', $controller)));
		$seen = [];
		foreach ($config['routes'] as $route) {
			$t->assertTrue(isset($route['name'], $route['url'], $route['verb']), 'Every route should declare name, url, and verb');
			$t->assertMatches('/^[a-z_]+#[A-Za-z][A-Za-z0-9_]*$/', $route['name'], 'Route names should use controller#method format');
			$t->assertTrue(in_array($route['verb'], ['GET', 'POST', 'PUT', 'DELETE'], true), 'Route verb should be supported: ' . $route['name']);

			$key = $route['verb'] . ' ' . $route['url'];
			$t->assertFalse(isset($seen[$key]), 'Route verb/url should be unique: ' . $key);
			$seen[$key] = true;

			[$controller, $method] = explode('#', $route['name'], 2);
			$controllerFile = 'lib/Controller/' . $controllerClassName($controller) . 'Controller.php';
			$source = $t->read($controllerFile);
			$t->assertMatches('/function\s+' . preg_quote($method, '/') . '\s*\(/', $source, 'Route should target an existing method: ' . $route['name']);
		}
	},

	'User-data API route methods check authentication and workspace header errors' => function(TestRunner $t): void {
		$config = require $t->path('appinfo/routes.php');
		$skip = [
			'page#index' => true,
			'category#adminIndex' => true,
			'category#adminCreate' => true,
			'category#adminUpdate' => true,
			'category#adminDestroy' => true,
			'category#adminUpdateIcon' => true,
			'category#adminHide' => true,
			'category#adminUnhide' => true,
			'payment_partner#adminIndex' => true,
			'payment_partner#adminCreate' => true,
			'payment_partner#adminUpdate' => true,
			'payment_partner#adminHide' => true,
			'payment_partner#adminUnhide' => true,
			'payment_partner#adminDestroy' => true,
			'integrity#inspect' => true,
			'integrity#repair' => true,
			'integrity#merge' => true,
			'admin_backup#settings' => true,
			'admin_backup#saveSettings' => true,
			'admin_backup#create' => true,
			'admin_backup#destroy' => true,
			'admin_backup#download' => true,
			'admin_backup#restore' => true,
		];

		$controllerClassName = static fn(string $controller): string => str_replace(' ', '', ucwords(str_replace('_', ' ', $controller)));
		foreach ($config['routes'] as $route) {
			if (isset($skip[$route['name']])) {
				continue;
			}

			[$controller, $method] = explode('#', $route['name'], 2);
			$body = $t->methodBody('lib/Controller/' . $controllerClassName($controller) . 'Controller.php', $method);
			$t->assertContains('authErrorResponse()', $body, 'User-data route should check auth/workspace errors: ' . $route['name']);
			$t->assertTrue(
				strpos($body, "['error' =>") !== false || strpos($body, 'errorResponse(') !== false || strpos($body, 'loggedErrorResponse(') !== false,
				'User-data route should return JSON error responses: ' . $route['name']
			);
		}
	},

	'Admin API route methods require explicit administrator checks' => function(TestRunner $t): void {
		foreach ([
			'CategoryController.php' => ['adminIndex', 'adminCreate', 'adminUpdate', 'adminUpdateIcon', 'adminHide', 'adminUnhide', 'adminDestroy'],
			'PaymentPartnerController.php' => ['adminIndex', 'adminCreate', 'adminUpdate', 'adminHide', 'adminUnhide', 'adminDestroy'],
			'IntegrityController.php' => ['inspect', 'repair', 'merge'],
			'AdminBackupController.php' => ['settings', 'saveSettings', 'create', 'destroy', 'download', 'restore'],
		] as $file => $methods) {
			$source = $t->read('lib/Controller/' . $file);
			$t->assertContains('IGroupManager', $source, $file . ' should inject the Nextcloud admin group manager');
			$t->assertContains('requireAdmin()', $source, $file . ' should use an explicit admin guard');
			$t->assertContains('isAdmin(', $source, $file . ' should check Nextcloud admin status');

			foreach ($methods as $method) {
				$body = $t->methodBody('lib/Controller/' . $file, $method);
				$t->assertContains('requireAdmin()', $body, $file . '::' . $method . ' should require admin rights');
			}
		}
	},

	'Personal settings endpoints are available to normal users' => function(TestRunner $t): void {
		foreach ([
			'CategoryController.php' => 'settingsData',
			'PaymentPartnerController.php' => 'settingsData',
		] as $file => $method) {
			$source = $t->read('lib/Controller/' . $file);
			$t->assertMatches(
				'/\/\*\*[\s\S]*@NoAdminRequired[\s\S]*\*\/\s*public function\s+' . preg_quote($method, '/') . '\s*\(/',
				$source,
				$file . '::' . $method . ' should not be treated as an admin-only settings endpoint'
			);
		}
	},

	'Internal controller exceptions are logged without exposing raw messages' => function(TestRunner $t): void {
		$trait = $t->read('lib/Controller/WorkspaceAwareTrait.php');
		$t->assertContains('protected function loggedErrorResponse(', $trait, 'Workspace-aware controllers should share a logged generic error helper');
		$t->assertContains('$logger->error(', $trait, 'Internal exception details should be logged');
		$t->assertContains("'exception' => \$e", $trait, 'Internal exception logs should include the exception object');
		$t->assertContains("return \$this->errorResponse(\$message, \$status)", $trait, 'Logged errors should return generic JSON client messages');

		foreach (glob($t->path('lib/Controller/*.php')) ?: [] as $file) {
			$source = (string)file_get_contents($file);
			$name = basename($file);
			$t->assertNotContains(
				"['error' => \$e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR",
				$source,
				$name . ' should not expose raw exception messages for 500 JSON responses'
			);
			$t->assertNotContains(
				'errorResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR',
				$source,
				$name . ' should not expose raw exception messages through errorResponse for 500 responses'
			);
		}
	},

	'Application boot ensures background jobs exist after zip-only updates' => function(TestRunner $t): void {
		$source = $t->read('lib/AppInfo/Application.php');
		$register = $t->methodBody('lib/AppInfo/Application.php', 'register');
		$boot = $t->methodBody('lib/AppInfo/Application.php', 'boot');
		$ensure = $t->methodBody('lib/AppInfo/Application.php', 'ensureBackgroundJob');
		$prioritize = $t->methodBody('lib/AppInfo/Application.php', 'prioritizeUnrunWebCronJob');
		$legacyCommandRegister = $t->read('appinfo/register_command.php');

		$t->assertContains('use OCP\\BackgroundJob\\IJobList;', $source, 'Application should import the background job list');
		$t->assertContains('use OCP\\IDBConnection;', $source, 'Application should import the database connection for job prioritization');
		$t->assertContains('use OCA\\CoBudget\\Cron\\RecurringEntriesJob;', $source, 'Application should import the recurring job');
		$t->assertContains('use OCA\\CoBudget\\Cron\\RemindersJob;', $source, 'Application should import the reminders job');
		$t->assertContains('use OCA\\CoBudget\\Cron\\BackupJob;', $source, 'Application should import the backup job');
		$t->assertContains('use OCA\\CoBudget\\Cron\\BudgetSnapshotJob;', $source, 'Application should import the budget snapshot job');
		$t->assertContains('use OCA\\CoBudget\\Notification\\Notifier;', $source, 'Application should import the notification notifier');
		$t->assertContains("method_exists(\$context, 'registerNotifierService')", $register, 'Application should guard notifier registration for Nextcloud API compatibility');
		$t->assertContains('registerNotifierService(Notifier::class)', $register, 'Application should use the supported Nextcloud notifier registration API');
		$t->assertNotContains('registerCommand(', $register, 'Application should not use Nextcloud 34 incompatible command bootstrap registration');
		foreach ([
			'CreateBackupCommand::class',
			'CreateFullBackupCommand::class',
			'CheckDataIntegrityCommand::class',
			'ResetAllCommand::class',
			'RestoreBackupCommand::class',
			'RestoreFullBackupCommand::class',
		] as $commandClass) {
			$t->assertContains($commandClass, $legacyCommandRegister, 'Legacy OCC registration should include ' . $commandClass);
		}
		$t->assertContains('new CoBudgetApplication()', $legacyCommandRegister, 'Legacy OCC registration should boot the app container');
		$t->assertContains('$application->add($container->query($commandClass))', $legacyCommandRegister, 'Legacy OCC registration should add commands to Symfony console');
		$t->assertContains('get(IJobList::class)', $boot, 'Application boot should resolve the Nextcloud job list');
		$t->assertContains('get(IDBConnection::class)', $boot, 'Application boot should resolve the database connection');
		$t->assertContains("method_exists(\$context, 'getServerContainer')", $boot, 'Application boot should guard server container access for Nextcloud API compatibility');
		$t->assertContains('RecurringEntriesJob::class', $boot, 'Application boot should ensure the recurring job exists');
		$t->assertContains('RemindersJob::class', $boot, 'Application boot should ensure the reminders job exists');
		$t->assertContains('BackupJob::class', $boot, 'Application boot should ensure the backup job exists');
		$t->assertContains('BudgetSnapshotJob::class', $boot, 'Application boot should ensure the budget snapshot job exists');
		$t->assertContains('prioritizeUnrunWebCronJob($db, $jobClass)', $boot, 'Application should prioritize newly registered jobs for WebCron');
		$t->assertContains('Background job registration must not block unrelated Nextcloud pages', $boot, 'Application boot should keep background job registration isolated');
		$t->assertContains('Icon cache refresh must never prevent the app from booting', $boot, 'Application boot should keep icon cache refresh isolated');
		$t->assertContains('$jobList->has($jobClass, null)', $ensure, 'Application should avoid duplicate legacy null-argument jobs');
		$t->assertContains('$jobList->has($jobClass, [])', $ensure, 'Application should avoid duplicate empty-array jobs');
		$t->assertContains('$jobList->add($jobClass, [])', $ensure, 'Application should add missing jobs with empty arguments');
		$t->assertContains("update('jobs')", $prioritize, 'Application should update the Nextcloud jobs table for unrun CoBudget jobs');
		$t->assertContains("eq('last_run'", $prioritize, 'Application should only prioritize jobs that have never run');
		$t->assertContains("set('last_checked'", $prioritize, 'Application should reset last_checked for WebCron prioritization');
	},

	'Global reset OCC command requires explicit confirmation and uses transactional deletes' => function(TestRunner $t): void {
		$command = $t->read('lib/Command/ResetAllCommand.php');

		$t->assertContains("setName('cobudget:reset-all')", $command, 'Reset command should expose the expected OCC name');
		$t->assertContains("private const CONFIRMATION_TEXT = 'RESET-COBUDGET';", $command, 'Reset command should require an explicit confirmation token');
		$t->assertContains("'confirm'", $command, 'Reset command should expose a confirmation option');
		$t->assertContains("Command::FAILURE", $command, 'Reset command should fail without confirmation or on rollback');
		$t->assertContains('$this->db->beginTransaction();', $command, 'Reset command should run inside a database transaction');
		$t->assertContains('$this->db->commit();', $command, 'Reset command should commit only after all deletes succeeded');
		$t->assertContains('$this->db->rollBack();', $command, 'Reset command should roll back failed resets');
		$t->assertContains('array_reverse(self::TABLES)', $command, 'Reset command should delete tables in dependency-safe reverse order');
		$t->assertContains("delete('preferences')", $command, 'Reset command should clear CoBudget user preferences');
		$t->assertContains("deleteAppValue(self::APP_ID, \$key)", $command, 'Reset command should clear explicitly owned global CoBudget app settings');
		foreach ([
			'default_categories_seeded',
			'default_payment_partners_seeded',
			'full_backup_storage_user',
			'full_backup_storage_folder',
			'full_backup_retention_count',
			'full_backup_schedule',
			'full_backup_last_auto_at',
			'theming_icon_cache_version',
		] as $appValueKey) {
			$t->assertContains("'" . $appValueKey . "'", $command, 'Global reset should clear CoBudget app value: ' . $appValueKey);
		}
		$t->assertNotContains('deleteAppValues(', $command, 'Global reset must not delete Nextcloud-owned app metadata such as enabled state or installed version');
		$t->assertContains("'cobudget_entries'", $command, 'Reset command should include entries');
		$t->assertContains("'cobudget_entry_history'", $command, 'Reset command should erase sensitive payment history');
		$t->assertContains("'cobudget_hashtags'", $command, 'Reset command should erase hashtag names');
		$t->assertContains("'cobudget_entry_hashtags'", $command, 'Reset command should erase payment-to-hashtag links');
		$t->assertContains("'cobudget_entry_attachments'", $command, 'Reset command should include attachment references');
		$t->assertContains("'cobudget_settlements'", $command, 'Reset command should include settlements');
		$t->assertContains("'cobudget_budget_goals'", $command, 'Reset command should include budget goals');

		$migrationTables = [];
		foreach (glob(dirname(__DIR__, 2) . '/lib/Migration/*.php') ?: [] as $migrationFile) {
			$migrationSource = (string)file_get_contents($migrationFile);
			preg_match_all("/createTable\\('([^']+)'\\)/", $migrationSource, $matches);
			$migrationTables = array_merge($migrationTables, $matches[1] ?? []);
		}
		foreach (array_values(array_unique($migrationTables)) as $table) {
			$t->assertContains("'" . $table . "'", $command, 'Global reset should include every migrated CoBudget table: ' . $table);
		}

		$entriesPosition = strpos($command, "'cobudget_entries'");
		$historyPosition = strpos($command, "'cobudget_entry_history'");
		$hashtagsPosition = strpos($command, "'cobudget_hashtags'");
		$entryHashtagsPosition = strpos($command, "'cobudget_entry_hashtags'");
		$t->assertTrue($entriesPosition !== false && $historyPosition !== false && $entriesPosition < $historyPosition, 'Reverse deletion should erase payment history before payments');
		$t->assertTrue($entriesPosition !== false && $entryHashtagsPosition !== false && $entriesPosition < $entryHashtagsPosition, 'Reverse deletion should erase hashtag links before payments');
		$t->assertTrue($hashtagsPosition !== false && $entryHashtagsPosition !== false && $hashtagsPosition < $entryHashtagsPosition, 'Reverse deletion should erase hashtag links before hashtag names');
		$t->assertNotContains('dropTable', $command, 'Reset command should not drop tables');
		$t->assertNotContains('TRUNCATE', $command, 'Reset command should use portable deletes instead of truncate');
	},

	'Personal restore OCC command prints the structured restore report correctly' => function(TestRunner $t): void {
		$command = $t->read('lib/Command/RestoreBackupCommand.php');

		$t->assertContains("setName('cobudget:backup:restore')", $command, 'Personal restore command should expose the expected OCC name');
		$t->assertContains("(int)(\$row['count'] ?? 0)", $command, 'Personal restore should read the report count field emitted by BackupService');
		$t->assertNotContains("\$row['rows']", $command, 'Personal restore should not read the obsolete rows field');
		$t->assertContains("\$row['label'] ?? \$row['table']", $command, 'Personal restore should print human-readable table labels');
		$t->assertContains("\$result['report']['user_mappings']", $command, 'Personal restore should report source-to-target account mapping');
		$t->assertContains("\$result['report']['attachment_paths']", $command, 'Personal restore should disclose that only receipt paths were imported');
	},

	'Entry mutations validate foreign references and scope writes to active workspace' => function(TestRunner $t): void {
			$create = $t->methodBody('lib/Controller/EntryController.php', 'create');
			$t->assertContains('validateEntryPayload(', $create, 'Entry create should use centralized validation');
			$t->assertContains('validateEntryUserId($projectId, $entryUserId)', $create, 'Entry create should validate project payer membership');
			$t->assertContains("'user_id' => \$qb->createNamedParameter(\$entryUserId)", $create, 'Entry create should write the selected project payer');
			$t->assertContains("if (\$type !== 'expense')", $create, 'Entry create should keep subscription/fixed-cost expense-only');
			$t->assertContains("'amount_cents'", $create, 'Entry create should write amount_cents');
			$t->assertContains("'split_mode'", $create, 'Entry create should write split_mode');
			$t->assertContains('validateSplitMode($splitMode)', $create, 'Entry create should validate split mode');
			$t->assertContains('$workspaceId = $this->workspaceIdForEntryScope($projectId)', $create, 'Entry create should derive workspace from the selected area instead of trusting the active header alone');
			$t->assertContains("'workspace_id'", $create, 'Entry create should write active workspace_id');
			foreach (["'is_child_related'", "'is_important'", "'needs_review'", "'is_tax_relevant'"] as $column) {
				$t->assertContains($column, $create, 'Entry create should write Kennzeichen column ' . $column);
			}

			$validation = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'validateEntryPayload');
			foreach ([
				'validateEntryType($type)',
				'validateAmountCents($amount, $amountCents)',
				'validateRequiredTimestamp($date',
				'validateEntryReferences($projectId, $categoryId, $paymentPartnerId, $recurrenceParentId)',
				'validateRecurrencePayload(',
			] as $requiredGuard) {
				$t->assertContains($requiredGuard, $validation, 'Entry payload validation should guard ' . $requiredGuard);
			}

		$update = $t->methodBody('lib/Controller/EntryController.php', 'update');
		$t->assertContains('entryVisibleInActiveWorkspace($id)', $update, 'Entry update should allow visible active-workspace project entries');
		$t->assertContains('validateEntryUserId($projectId, $entryUserId,', $update, 'Entry update should validate selected project payer membership while preserving an unchanged former payer');
		$t->assertContains("set('user_id'", $update, 'Entry update should write the selected project payer');
		$t->assertContains("if (\$type !== 'expense')", $update, 'Entry update should keep subscription/fixed-cost expense-only');
		$t->assertContains("'amount_cents'", $update, 'Entry update should write amount_cents');
		$t->assertContains("set('split_mode'", $update, 'Entry update should update split_mode');
		$t->assertContains('validateSplitMode($splitMode)', $update, 'Entry update should validate split mode');
		$t->assertContains('$targetWorkspaceId !== $currentWorkspaceId', $update, 'Entry update should reject workspace changes');
		$t->assertContains('$workspaceId = $currentWorkspaceId', $update, 'Entry update should keep the stored workspace immutable');
		$t->assertContains("eq('workspace_id'", $update, 'Entry update should scope by workspace_id');
		$t->assertNotContains("andWhere(\$qb->expr()->eq('user_id'", $update, 'Entry update should not block project members by the previous payer');
		foreach (["set('is_child_related'", "set('is_important'", "set('needs_review'", "set('is_tax_relevant'"] as $setter) {
			$t->assertContains($setter, $update, 'Entry update should update Kennzeichen field ' . $setter);
		}

		$destroy = $t->methodBody('lib/Controller/EntryController.php', 'destroy');
		$t->assertContains('entryVisibleInActiveWorkspace($id)', $destroy, 'Entry delete should allow visible active-workspace project entries');
		$t->assertContains('$this->entryProjectionService->prepareEntryDeletion([$id])', $destroy, 'Entry delete should derive every cross-workspace projection id on the server');
		$t->assertContains('$this->deleteEntryRowsByIds($entryIds)', $destroy, 'Entry delete should remove the complete authorized payment graph');
		$t->assertNotContains("andWhere(\$qb->expr()->eq('user_id'", $destroy, 'Entry delete should not block project members by the previous payer');

		$stopRecurrence = $t->methodBody('lib/Controller/EntryController.php', 'stopRecurrence');
		$t->assertContains('entryVisibleInActiveWorkspace($id)', $stopRecurrence, 'Stop recurrence should only allow visible active-workspace entries');
		$t->assertContains("eq('workspace_id'", $stopRecurrence, 'Stop recurrence should scope update by workspace_id');

		$entryUserValidation = $t->methodBody('lib/Controller/EntryController.php', 'validateEntryUserId');
		$t->assertContains('projectUserMemberInActiveWorkspace($projectId, $entryUserId)', $entryUserValidation, 'Entry payer validation should only allow project members');

		$trait = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'projectUserMemberInActiveWorkspace');
		$t->assertContains("eq('m.user_id'", $trait, 'Workspace guard should check the selected member user id');
		$t->assertContains('projectVisibleForCurrentUser($projectId)', $trait, 'Project member checks should first require current-user project visibility');
		$t->assertNotContains("eq('p.workspace_id'", $trait, 'Shared project member checks must not depend on the currently selected workspace');
	},

	'Dashboard endpoint bundles entries metrics and lookups in the active workspace' => function(TestRunner $t): void {
		$routes = require $t->path('appinfo/routes.php');
		$routeNames = array_column($routes['routes'], 'url', 'name');
		$t->assertTrue(($routeNames['entry#dashboard'] ?? null) === '/api/dashboard', 'Dashboard route should expose /api/dashboard');

		$dashboard = $t->methodBody('lib/Controller/EntryController.php', 'dashboard');
		$t->assertContains('fetchEntryListPayload($workspaceId', $dashboard, 'Dashboard should reuse the entries list payload');
		$t->assertContains('fetchDashboardProjects($workspaceId)', $dashboard, 'Dashboard should include project lookups');
		$t->assertContains('fetchDashboardCategories($workspaceId)', $dashboard, 'Dashboard should include category lookups');
		$t->assertContains('fetchDashboardPaymentPartners($workspaceId)', $dashboard, 'Dashboard should include paymentPartner lookups');
		$t->assertContains('buildDashboardMetricsFromAggregates(', $dashboard, 'Dashboard should return server-side aggregate metrics');
		$t->assertContains('summaryOnly', $dashboard, 'Dashboard should support lightweight summary-only requests');
		$t->assertContains('dashboardTagCountsFromAggregates(', $dashboard, 'Dashboard should return aggregate Kennzeichen counts');

		$metrics = $t->methodBody('lib/Controller/EntryController.php', 'buildDashboardMetricsFromAggregates');
		foreach (['total', 'average', 'currentMonth', 'future', 'future30Days'] as $bucket) {
			$t->assertContains("'" . $bucket . "'", $metrics, 'Dashboard metrics should include ' . $bucket);
		}

		$summarize = $t->methodBody('lib/Controller/EntryController.php', 'accumulateDashboardMetrics');
		$t->assertContains('$signedAmount = $amount;', $summarize, 'Dashboard Kennzeichen metrics should treat income as positive');
		$t->assertContains('$signedAmount = -$amount;', $summarize, 'Dashboard Kennzeichen metrics should treat expenses as negative');
		foreach (['important', 'review', 'childRelated', 'taxRelevant'] as $metric) {
			$t->assertContains("\$summary['" . $metric . "'] += \$signedAmount", $summarize, 'Dashboard metric should be signed for ' . $metric);
		}

		$zeroMetrics = $t->methodBody('lib/Controller/EntryController.php', 'zeroDashboardMetrics');
		foreach (['important', 'review', 'fixedCosts', 'childRelated', 'subscriptions', 'taxRelevant'] as $metric) {
			$t->assertContains("'" . $metric . "'", $zeroMetrics, 'Dashboard metrics should include Kennzeichen metric ' . $metric);
		}

		$zeroCounts = $t->methodBody('lib/Controller/EntryController.php', 'zeroDashboardTagCounts');
		foreach (['income', 'future', 'important', 'review', 'fixedCosts', 'childRelated', 'subscriptions', 'taxRelevant'] as $count) {
			$t->assertContains("'" . $count . "'", $zeroCounts, 'Dashboard tag counts should include count ' . $count);
		}

		$tagCounts = $t->methodBody('lib/Controller/EntryController.php', 'accumulateDashboardTagCounts');
		foreach (['income', 'is_important', 'needs_review', 'is_fixed_cost', 'is_child_related', 'is_subscription', 'is_tax_relevant'] as $column) {
			$t->assertContains($column, $tagCounts, 'Dashboard tag counts should inspect ' . $column);
		}
		$combinedTagCounts = $t->methodBody('lib/Controller/EntryController.php', 'dashboardTagCountsFromAggregates');
		$t->assertContains("\$future['count']", $combinedTagCounts, 'Dashboard tag counts should count planned payments separately');

		$listPayload = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryListPayload');
		$t->assertContains('normalizePagination(', $listPayload, 'Entry list payload should normalize untrusted pagination parameters');
		$t->assertContains('fetchEntryAggregateData(', $listPayload, 'Entry list payload should use the streaming aggregate helper');
		$t->assertNotContains('fetchEntryTotalsData(', $listPayload, 'Entry list payload should not materialize every filtered payment');
		$t->assertContains('fetchEntryRows(', $listPayload, 'Entry list payload should keep a single row query helper');
		$aggregate = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryAggregateData');
		$t->assertContains('while ($entry = $result->fetch())', $aggregate, 'Entry summaries should stream filtered rows instead of fetchAll');
		$t->assertContains('personal_share.amount_cents AS snapshot_share_cents', $aggregate, 'Entry summaries should use immutable personal share snapshots');
		$t->assertContains('entryAggregateAmountCents($entry, $projectShareBasisPoints)', $aggregate, 'Entry summaries should select totals according to the requested personal or shared scope');
		$t->assertContains("\$this->entryScope === 'shared'", $t->methodBody('lib/Controller/EntryController.php', 'entryAggregateAmountCents'), 'Shared area summaries should use the complete shared payment amount');
		$pagination = $t->methodBody('lib/Controller/EntryController.php', 'normalizePagination');
		$t->assertContains('self::MAX_PAGE_SIZE', $pagination, 'Entry page size should have a server-side maximum');
		$t->assertContains('self::MAX_PAGE_OFFSET', $pagination, 'Entry page offset should have a server-side maximum');

		$normalizeEntryRow = $t->methodBody('lib/Controller/EntryController.php', 'normalizeEntryRow');
		$t->assertContains('user_display_name', $normalizeEntryRow, 'Entry rows should expose payer display names for shared-project tooltips');
		$t->assertContains('$this->participantService->participant($userId)', $normalizeEntryRow, 'Entry rows should resolve active and former payer display names through the participant service');

		$applyFilters = $t->methodBody('lib/Controller/EntryController.php', 'applyFilters');
		foreach (['is_child_related', 'is_important', 'needs_review', 'is_tax_relevant'] as $column) {
			$t->assertContains("eq('e." . $column . "'", $applyFilters, 'Entry filters should support Kennzeichen column ' . $column);
		}
	},

	'Entry CSV export reuses entry filters and returns a download response' => function(TestRunner $t): void {
		$routes = require $t->path('appinfo/routes.php');
		$routeNames = array_column($routes['routes'], 'url', 'name');
		$t->assertTrue(($routeNames['entry#exportCsv'] ?? null) === '/api/entries/export', 'Entry CSV export route should expose /api/entries/export');

		$entrySource = $t->read('lib/Controller/EntryController.php');
		$t->assertContains('use OCP\\AppFramework\\Http\\DataDownloadResponse;', $entrySource, 'Entry CSV export should use a download response');

		$export = $t->methodBody('lib/Controller/EntryController.php', 'exportCsv');
		$t->assertContains('authErrorResponse()', $export, 'Entry CSV export should check auth and workspace headers');
		$t->assertContains('$this->getWorkspaceId()', $export, 'Entry CSV export should use the active workspace');
		$t->assertContains('fetchEntryRows($workspaceId, self::EXPORT_LIMIT, 0', $export, 'Entry CSV export should reuse the filtered entry row query without page offset');
		$t->assertContains('buildEntriesCsv($entries, $projectShareBasisPoints)', $export, 'Entry CSV export should build a CSV from filtered rows');
		$t->assertContains('new DataDownloadResponse($csv, $filename, \'text/csv; charset=UTF-8\')', $export, 'Entry CSV export should return a CSV download');

		$csv = $t->methodBody('lib/Controller/EntryController.php', 'buildEntriesCsv');
		foreach (['ID', 'Type key', 'Amount cents', 'Personal share cents', 'Category ID', 'Payment partner ID', 'Area ID', 'Paid/received by ID', 'Labels', 'Important', 'Review', 'Settlement ID', 'Receipt names', 'Receipt paths'] as $column) {
			$t->assertContains($column, $csv, 'Entry CSV export should include column ' . $column);
		}
		$t->assertContains('array_map(fn(string $header): string => $this->l10n->t($header), $headers)', $csv, 'Entry CSV export should translate headers at the app boundary');
		$l10nDe = $t->read('l10n/de.js');
		foreach (['Type key' => 'Typ-Key', 'Amount cents' => 'Betrag Cent', 'Personal share cents' => 'Persönlicher Anteil Cent', 'Receipt paths' => 'Belegpfade'] as $key => $translation) {
			$t->assertContains('"' . $key . '": "' . $translation . '"', $l10nDe, 'German l10n should translate CSV header ' . $key);
		}
		$t->assertContains('fputcsv($handle', $csv, 'Entry CSV export should use a CSV writer');
		$t->assertContains("';'", $csv, 'Entry CSV export should use semicolon-separated CSV');
		$t->assertContains('entryPersonalAmountCents($entry, $projectShareBasisPoints)', $csv, 'Entry CSV export should include the personal project share');
		$t->assertContains('exportTagLabels($entry)', $csv, 'Entry CSV export should include Kennzeichen labels');
		$t->assertContains('attachment_names', $csv, 'Entry CSV export should include attachment names');
		$t->assertContains('attachment_paths', $csv, 'Entry CSV export should include attachment paths');
		foreach ([
			"\$this->exportText(\$entry['description'] ?? '')",
			"\$this->exportText(\$entry['category_name'] ?? '')",
			"\$this->exportText(\$entry['paymentPartner'] ?? '')",
			"\$this->exportText(\$entry['project_name'] ?? '')",
			"\$this->exportText(implode(', ', \$entry['attachment_paths'] ?? []))",
		] as $safeTextExport) {
			$t->assertContains($safeTextExport, $csv, 'User-controlled CSV text should use formula-injection protection');
		}
		$exportText = $t->methodBody('lib/Controller/EntryController.php', 'exportText');
		$t->assertContains('CsvCellSanitizer::sanitize((string)$value)', $exportText, 'CSV text sanitization should use the central sanitizer');
		$t->assertNotContains('exportText($this->exportAmountFromCents', $csv, 'CSV amount fields should remain numeric instead of being converted to text');

		$amountFormat = $t->methodBody('lib/Controller/EntryController.php', 'exportAmountFromCents');
		$t->assertContains("number_format(\$amountCents / 100, 2, '.', '')", $amountFormat, 'Entry CSV export should use the same decimal point format as the app');

		$dateFormat = $t->methodBody('lib/Controller/EntryController.php', 'exportDate');
		$t->assertContains("date('Y-m-d'", $dateFormat, 'Entry CSV export should use neutral ISO dates');
	},

	'Analytics endpoint summarizes visible workspace entries with personal project shares' => function(TestRunner $t): void {
		$routes = require $t->path('appinfo/routes.php');
		$routeNames = array_column($routes['routes'], 'url', 'name');
		$t->assertTrue(($routeNames['analytics#summary'] ?? null) === '/api/analytics/summary', 'Analytics route should expose /api/analytics/summary');

		$summary = $t->methodBody('lib/Controller/AnalyticsController.php', 'summary');
		$t->assertContains('authErrorResponse()', $summary, 'Analytics should check auth and workspace headers');
		$t->assertContains('$this->getWorkspaceId()', $summary, 'Analytics should use the active workspace');
		$t->assertContains('loadProjectShares($workspaceId)', $summary, 'Analytics should load project share configuration');
		$t->assertContains('buildPeriodOptions($this->loadAnalyticsEntryDates($workspaceId))', $summary, 'Analytics should expose dynamic year periods without loading all entry payloads');
		$t->assertContains('loadAnalyticsEntries($workspaceId, (int)$selectedPeriod[\'start\'], (int)$selectedPeriod[\'end\'])', $summary, 'Analytics should load personal entries only for the selected period');
		$t->assertContains('attachAnalyticsAttachmentFlags($periodEntries, $workspaceId)', $summary, 'Analytics should attach receipt flags after period filtering');
		$t->assertContains('comparisonPeriodFor($selectedPeriod)', $summary, 'Analytics should derive a matching previous period for trend comparisons');
		$t->assertContains('entriesForAnalyticsRange($workspaceId, $periodEntries, $selectedPeriod, (int)$comparisonPeriod[\'start\'], (int)$comparisonPeriod[\'end\'])', $summary, 'Analytics should reuse loaded personal entries or query a bounded comparison range');
		$t->assertContains('directionWindowsFor($selectedPeriod)', $summary, 'Analytics should derive recent direction windows for visible trend badges');
		$t->assertContains('entriesForAnalyticsRange($workspaceId, $periodEntries, $selectedPeriod, (int)$directionWindows[\'recentStart\'], (int)$directionWindows[\'recentEnd\'])', $summary, 'Analytics should reuse loaded personal entries or query a bounded recent range');
		$t->assertContains('entriesForAnalyticsRange($workspaceId, $periodEntries, $selectedPeriod, (int)$directionWindows[\'baselineStart\'], (int)$directionWindows[\'baselineEnd\'])', $summary, 'Analytics should reuse loaded personal entries or query a bounded baseline range');
		$t->assertContains('previousFullMonthPeriodFor($selectedPeriod)', $summary, 'Analytics should load current-month comparison data with an explicit previous-month range');
		$t->assertContains('buildProjection(', $summary, 'Analytics should include a projection section');
		$t->assertContains('$projection = $this->buildProjection($periodEntries, $selectedPeriod, $summary)', $summary, 'Analytics should calculate projections once');
		$t->assertContains("'availableForecast' => \$this->buildAvailableForecast(\$selectedPeriod, \$summary, \$projection)", $summary, 'Analytics should include an available-money forecast');
		$t->assertContains('buildBreakdowns($periodEntries, $comparisonEntries, $directionRecentEntries, $directionBaselineEntries)', $summary, 'Analytics should include grouped breakdowns with trend comparisons and recent direction');
		$t->assertContains('buildTagDrilldowns($periodEntries, $comparisonEntries, $directionRecentEntries, $directionBaselineEntries)', $summary, 'Analytics should include Kennzeichen drilldowns with trend comparisons and recent direction');
		$t->assertContains('buildHashtagDrilldowns($periodEntries, $comparisonEntries, $directionRecentEntries, $directionBaselineEntries)', $summary, 'Analytics should include free #tag drilldowns with trend comparisons and recent direction');
		$t->assertContains('buildOutliers($periodEntries)', $summary, 'Analytics should include high amount detection');
		$t->assertContains('loadSharedProjectEntries($workspaceId, $sharesByProject', $summary, 'Analytics should load shared Bereich entries independently from personal totals');
		$t->assertContains('buildSharedProjects($sharedProjectEntries, $sharesByProject)', $summary, 'Analytics should include compact shared Bereich summaries');
		$t->assertContains('loadUpcomingEntries($workspaceId, $sharesByProject)', $summary, 'Analytics should load reminders and planned payments separately');
		$t->assertContains('buildUpcoming($upcomingEntries)', $summary, 'Analytics should include active reminders and planned payments');
		$t->assertContains("'receiptChecks' => \$this->receiptsEnabled() ? \$this->buildReceiptChecks(\$periodEntries) : []", $summary, 'Analytics should expose missing-receipt checks when receipts are enabled');
		$t->assertContains('BudgetSnapshotService', $t->read('lib/Controller/AnalyticsController.php'), 'Analytics should inject budget snapshots');
		$t->assertContains('HashtagService', $t->read('lib/Controller/AnalyticsController.php'), 'Analytics should inject free hashtag service');
		$t->assertNotContains('ICacheFactory', $t->read('lib/Controller/AnalyticsController.php'), 'Analytics should not return stale cached summaries');
		$t->assertNotContains('readAnalyticsCache(', $summary, 'Analytics should compute live summaries');
		$t->assertNotContains('cacheAnalyticsResponse(', $summary, 'Analytics should compute live summaries');
		$t->assertContains('budgetHistory', $summary, 'Analytics should expose budget goal history snapshots');
		$t->assertContains('budgetSnapshotService->history', $summary, 'Analytics should load budget history for the selected range');

		$attachmentFlags = $t->methodBody('lib/Controller/AnalyticsController.php', 'attachAnalyticsAttachmentFlags');
		$t->assertContains("'cobudget_entry_attachments'", $attachmentFlags, 'Analytics receipt checks should read entry attachment rows');
		$t->assertContains('IQueryBuilder::PARAM_INT_ARRAY', $attachmentFlags, 'Analytics receipt checks should use typed array parameters for attachment lookups');
		$t->assertContains("'hasAttachment'", $attachmentFlags, 'Analytics receipt checks should mark entries with files');
		$t->assertContains("'attachmentCount'", $attachmentFlags, 'Analytics receipt checks should expose attachment counts internally');

		$receiptChecks = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildReceiptChecks');
		$t->assertContains("'taxRelevantMissingReceipt'", $receiptChecks, 'Analytics should detect tax-relevant entries without receipts');
		$t->assertContains("'reviewMissingReceipt'", $receiptChecks, 'Analytics should detect review entries without receipts');
		$t->assertContains("!empty(\$entry['hasAttachment'])", $receiptChecks, 'Analytics missing-receipt checks should ignore entries with files');

		$breakdowns = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildBreakdowns');
		$t->assertContains("'tags'", $breakdowns, 'Analytics breakdowns should include Kennzeichen');
		$t->assertContains("'hashtags'", $breakdowns, 'Analytics breakdowns should include free #tags from descriptions');
		$t->assertContains('buildTagBreakdown($entries, \'expense\', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries)', $breakdowns, 'Analytics should aggregate expense Kennzeichen with trend comparisons and direction windows');
		$t->assertContains('buildTagBreakdown($entries, \'income\', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries)', $breakdowns, 'Analytics should aggregate income Kennzeichen with trend comparisons and direction windows');
		$t->assertContains('buildHashtagBreakdown($entries, \'expense\', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries)', $breakdowns, 'Analytics should aggregate expense #tags with trend comparisons and direction windows');
		$t->assertContains('buildHashtagBreakdown($entries, \'income\', $comparisonEntries, $directionRecentEntries, $directionBaselineEntries)', $breakdowns, 'Analytics should aggregate income #tags with trend comparisons and direction windows');

		$tagBreakdown = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildTagBreakdown');
		$t->assertContains('buildTagBreakdownRows($entries, $type)', $tagBreakdown, 'Analytics Kennzeichen breakdown should use reusable row aggregation');
		$t->assertContains('withBreakdownTrends($rows, $comparisonRows, $directionRecentRows, $directionBaselineRows, $type)', $tagBreakdown, 'Analytics Kennzeichen breakdown should attach recent direction and comparison metadata');

		$tagBreakdownRows = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildTagBreakdownRows');
		$t->assertContains('self::TAG_LABELS[$key]', $tagBreakdownRows, 'Analytics Kennzeichen breakdown should use stable labels');
		$t->assertContains('$entry[\'personalCents\']', $tagBreakdownRows, 'Analytics Kennzeichen breakdown should use the personal share');

		$hashtagBreakdown = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildHashtagBreakdown');
		$t->assertContains('buildHashtagBreakdownRows($entries, $type)', $hashtagBreakdown, 'Analytics #tag breakdown should use reusable row aggregation');
		$t->assertContains('withBreakdownTrends($rows, $comparisonRows, $directionRecentRows, $directionBaselineRows, $type)', $hashtagBreakdown, 'Analytics #tag breakdown should attach recent direction and comparison metadata');
		$t->assertContains('withRestBucket($rows, 8)', $hashtagBreakdown, 'Analytics #tag breakdown should collapse long tails');

		$hashtagBreakdownRows = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildHashtagBreakdownRows');
		$t->assertContains('$entry[\'hashtags\']', $hashtagBreakdownRows, 'Analytics #tag breakdown should read parsed hashtags from entries');
		$t->assertContains("'name' => '#' . \$name", $hashtagBreakdownRows, 'Analytics #tag breakdown should expose names with a visible hash prefix');
		$t->assertContains('$entry[\'personalCents\']', $hashtagBreakdownRows, 'Analytics #tag breakdown should use the personal share');

		$normalizeEntry = $t->methodBody('lib/Controller/AnalyticsController.php', 'normalizeAnalyticsEntry');
		$t->assertContains('$categoryName === \'\' ? null', $normalizeEntry, 'Analytics should treat orphaned category ids without a joined name as uncategorized');
		$t->assertContains('$paymentPartnerName === \'\' ? null', $normalizeEntry, 'Analytics should treat orphaned contact ids without a joined name as unset');
		$t->assertContains('$projectName === \'\' ? null', $normalizeEntry, 'Analytics should treat orphaned Bereich ids without a joined name as personal');

		$breakdownRows = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildBreakdownRows');
		$t->assertContains('$id = null;', $breakdownRows, 'Analytics fallback rows should collapse into one technical none bucket');
		$t->assertContains('$key = $this->breakdownEntryKey($entry, $idKey, $nameKey, $fallbackName)', $breakdownRows, 'Analytics breakdowns should use one grouping key for rows and drilldowns');

		$filterBreakdownEntries = $t->methodBody('lib/Controller/AnalyticsController.php', 'filterBreakdownEntries');
		$t->assertContains('breakdownEntryKey($entry, $idKey, $nameKey, $fallbackName) === $key', $filterBreakdownEntries, 'Analytics drilldowns should use the same grouping key as the visible rows');

		$breakdownEntryKey = $t->methodBody('lib/Controller/AnalyticsController.php', 'breakdownEntryKey');
		$t->assertContains("return 'none'", $breakdownEntryKey, 'Analytics entry keys should collapse missing visible names into one bucket');
		$t->assertContains('breakdownUsesNameKey($idKey)', $breakdownEntryKey, 'Analytics should switch category and payment partner breakdowns to visible-name grouping');
		$t->assertContains("return 'name:' . \$this->normalizeBreakdownName(\$name)", $breakdownEntryKey, 'Analytics should merge duplicate visible category/payment partner names');
		$t->assertContains('breakdownKey($entry[$idKey] ?? null)', $breakdownEntryKey, 'Analytics should keep Bereich breakdowns ID-based');

		$breakdownUsesNameKey = $t->methodBody('lib/Controller/AnalyticsController.php', 'breakdownUsesNameKey');
		$t->assertContains("['categoryId', 'paymentPartnerId']", $breakdownUsesNameKey, 'Analytics should group categories and payment partners by visible name');

		$normalizeBreakdownName = $t->methodBody('lib/Controller/AnalyticsController.php', 'normalizeBreakdownName');
		$t->assertContains("preg_replace('/\\s+/u'", $normalizeBreakdownName, 'Analytics should normalize duplicate names before grouping');

		$breakdownKey = $t->methodBody('lib/Controller/AnalyticsController.php', 'breakdownKey');
		$t->assertContains("return 'none'", $breakdownKey, 'Analytics breakdown keys should use one stable bucket for missing values');
		$t->assertContains('(int)$id <= 0', $breakdownKey, 'Analytics breakdown keys should normalize zero-like ids');

		$trend = $t->methodBody('lib/Controller/AnalyticsController.php', 'breakdownTrend');
		$t->assertContains('$absoluteDeltaCents < 1000 || $absoluteDeltaPercent < 15', $trend, 'Analytics trend comparisons should hide tiny changes');
		$t->assertContains('$absoluteDeltaCents >= 2500 && $absoluteDeltaPercent >= 30', $trend, 'Analytics trend comparisons should distinguish strong changes');
		$t->assertContains('formatBreakdownTrend', $trend, 'Analytics trend comparisons should expose compact trend metadata');

		$directionWindows = $t->methodBody('lib/Controller/AnalyticsController.php', 'directionWindowsFor');
		$t->assertContains("if (\$key === 'current-month')", $directionWindows, 'Analytics direction should use a short recent window for the current month');
		$t->assertContains("if (\$key === 'current-year' || \$kind === 'last-12-months')", $directionWindows, 'Analytics direction should use recent months for year-like periods');
		$t->assertContains("strtotime('+6 months', \$start)", $directionWindows, 'Analytics direction should compare half-years for completed year periods');

		$availableForecast = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildAvailableForecast');
		$t->assertContains("\$key !== 'current-year' && \$kind !== 'current-month'", $availableForecast, 'Analytics available forecast should only show for active current periods');
		$t->assertContains("'forecastCents'", $availableForecast, 'Analytics available forecast should expose the expected end balance');
		$t->assertContains("'remainingChangeCents'", $availableForecast, 'Analytics available forecast should expose the remaining change from today');
		$t->assertContains("'rangeLowCents'", $availableForecast, 'Analytics available forecast should expose a cautious low range');
		$t->assertContains("'rangeHighCents'", $availableForecast, 'Analytics available forecast should expose a cautious high range');
		$t->assertContains("'confidenceLabel'", $availableForecast, 'Analytics available forecast should explain the data basis');

		$withTrends = $t->methodBody('lib/Controller/AnalyticsController.php', 'withBreakdownTrends');
		$t->assertContains('$row[\'trend\'] = $trend', $withTrends, 'Analytics visible trend should represent the recent direction');
		$t->assertContains('$row[\'comparison\'] = $comparison', $withTrends, 'Analytics tooltip should retain the previous-period comparison');

		$tagDrilldowns = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildTagDrilldowns');
		$t->assertContains("'categories' => \$this->buildBreakdown", $tagDrilldowns, 'Analytics Kennzeichen drilldowns should include category details');
		$t->assertContains("'paymentPartners' => \$this->buildBreakdown", $tagDrilldowns, 'Analytics Kennzeichen drilldowns should include contact details');
		$t->assertContains("'hashtags' => \$this->buildHashtagBreakdown", $tagDrilldowns, 'Analytics Kennzeichen drilldowns should include free #tag details');
		$t->assertContains("'projects' => \$this->buildBreakdown", $tagDrilldowns, 'Analytics Kennzeichen drilldowns should include Bereich details');

		$hashtagDrilldowns = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildHashtagDrilldowns');
		$t->assertContains('filterHashtagEntries', $hashtagDrilldowns, 'Analytics #tag drilldowns should filter entries by the selected #tag');
		$t->assertContains("'categories' => \$this->buildBreakdown", $hashtagDrilldowns, 'Analytics #tag drilldowns should include category details');
		$t->assertContains("'paymentPartners' => \$this->buildBreakdown", $hashtagDrilldowns, 'Analytics #tag drilldowns should include payment partner details');
		$t->assertContains("'tags' => \$this->buildTagBreakdown", $hashtagDrilldowns, 'Analytics #tag drilldowns should include label details');
		$t->assertContains("'projects' => \$this->buildBreakdown", $hashtagDrilldowns, 'Analytics #tag drilldowns should include Bereich details');

		$loadEntries = $t->methodBody('lib/Controller/AnalyticsController.php', 'loadAnalyticsEntries');
		$t->assertContains('attachHashtagsToEntries($entries)', $loadEntries, 'Analytics entries should be enriched with free #tags before summary aggregation');
		$t->assertContains("eq('e.workspace_id'", $loadEntries, 'Analytics entries should be workspace-scoped');
		$t->assertContains("lte('e.date'", $loadEntries, 'Analytics should ignore future-planned entries');
		$t->assertContains("gte('e.date'", $loadEntries, 'Analytics entries should support bounded period start queries');
		$t->assertContains("lt('e.date'", $loadEntries, 'Analytics entries should support bounded period end queries');
		$t->assertContains("eq('e.entry_kind'", $loadEntries, 'Analytics should count only materialized personal payments');
		$t->assertContains("eq('e.user_id'", $loadEntries, 'Analytics personal payments should belong to the current user');
		$t->assertNotContains("cobudget_members", $loadEntries, 'Analytics totals must not count shared source payments through membership');
		$t->assertContains("'e.is_settled'", $loadEntries, 'Analytics should expose settlement state for shared Bereich summaries');
		$t->assertContains("'e.split_user_id'", $loadEntries, 'Analytics entries should expose explicit single-user split targets');

		$entriesForRange = $t->methodBody('lib/Controller/AnalyticsController.php', 'entriesForAnalyticsRange');
		$t->assertContains('filterEntriesByRange($loadedEntries, $start, $end)', $entriesForRange, 'Analytics should avoid duplicate DB reads when a range is inside already loaded entries');
		$t->assertContains('loadAnalyticsEntries($workspaceId, $start, $end)', $entriesForRange, 'Analytics should query bounded personal ranges when extra data is needed');

		$loadShared = $t->methodBody('lib/Controller/AnalyticsController.php', 'loadSharedProjectEntries');
		$t->assertContains("eq('e.type'", $loadShared, 'Shared Bereich analytics should load expenses only');
		$t->assertContains("gte('e.date'", $loadShared, 'Shared Bereich analytics should respect the selected period start');
		$t->assertContains("lt('e.date'", $loadShared, 'Shared Bereich analytics should respect the selected period end');
		$t->assertContains('storedOrCalculatedShareCents($row', $loadShared, 'Shared Bereich analytics should prefer the stored current-user share even when it is zero');
		$t->assertContains("'e.split_user_id'", $loadShared, 'Shared Bereich analytics should expose explicit single-user split targets');
		$t->assertContains("eq('m.personal_workspace_id'", $loadShared, 'Shared collaboration analytics should stay in the members active personal workspace');

		$sharedProjects = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildSharedProjects');
		$t->assertContains("(\$entry['type'] ?? '') !== 'expense'", $sharedProjects, 'Shared Bereich analytics should focus on expenses paid in shared areas');
		$t->assertContains('count($sharesByProject[$projectId] ?? []) < 2', $sharedProjects, 'Shared Bereich analytics should only include areas with multiple members');
		$t->assertContains("'totalPaidCents'", $sharedProjects, 'Shared Bereich analytics should expose total paid');
		$t->assertContains("'personalShareCents'", $sharedProjects, 'Shared Bereich analytics should expose the current user share');
		$t->assertContains("'currentUserPaidCents'", $sharedProjects, 'Shared Bereich analytics should expose how much the current user paid');
		$t->assertContains("'currentUserBalanceCents'", $sharedProjects, 'Shared Bereich analytics should expose the current user settlement balance');
		$t->assertContains("'openCents'", $sharedProjects, 'Shared Bereich analytics should expose open totals');
		$t->assertContains("'settledCents'", $sharedProjects, 'Shared Bereich analytics should expose settled totals');
		$t->assertContains("'members'", $sharedProjects, 'Shared Bereich analytics should expose who paid how much');

		$personalCents = $t->methodBody('lib/Controller/AnalyticsController.php', 'entryPersonalCents');
		$t->assertContains('storedOrCalculatedShareCents(', $personalCents, 'Analytics should prefer stored payment allocations and retain the legacy split fallback');
		$t->assertContains("(string)(\$entry['user_id'] ?? '') === (string)\$this->userId", $personalCents, 'Analytics should only count personal entries for the current user');

		$upcoming = $t->methodBody('lib/Controller/AnalyticsController.php', 'loadUpcomingEntries');
		$t->assertContains("gt('e.date'", $upcoming, 'Analytics upcoming entries should include future-dated payments');
		$t->assertContains("isNotNull('e.recurrence_next_date')", $upcoming, 'Analytics upcoming entries should include recurring next dates');
		$t->assertContains("isNotNull('e.reminder_date')", $upcoming, 'Analytics upcoming entries should include reminders');
		$t->assertContains("eq('e.reminder_notified'", $upcoming, 'Analytics upcoming reminders should be active only');
		$t->assertContains("isNull('e.source_entry_id')", $upcoming, 'Analytics upcoming entries should exclude linked personal projections');
		$t->assertContains("eq('e.entry_kind', \$qb->createNamedParameter('shared'))", $upcoming, 'Analytics upcoming entries should derive shared recurrences from their shared source');
		$t->assertContains("eq('m.personal_workspace_id'", $upcoming, 'Shared upcoming entries should be scoped through the members personal workspace');
		$t->assertContains("gt('future_share.amount_cents'", $upcoming, 'A zero-percent shared allocation should not appear as a planned personal payment');

		$periods = $t->methodBody('lib/Controller/AnalyticsController.php', 'buildPeriodOptions');
		$t->assertContains("'current-year'", $periods, 'Analytics should include current year period first');
		$t->assertContains("'current-month'", $periods, 'Analytics should include current month period');
		$t->assertContains("'last-12-months'", $periods, 'Analytics should include last 12 months period');
		$t->assertContains('if ((int)$year === $currentYear)', $periods, 'Analytics should not duplicate the current year in year options');
		$t->assertContains("'year:' . \$year", $periods, 'Analytics should include years with bookings');

		$resolvePeriod = $t->methodBody('lib/Controller/AnalyticsController.php', 'resolvePeriod');
		$t->assertContains("\$period = 'current-year'", $resolvePeriod, 'Analytics should fall back to current year');
		$t->assertContains("'label' => 'Aktuelles Jahr'", $resolvePeriod, 'Analytics current year period should use a clear label');

		$summarize = $t->methodBody('lib/Controller/AnalyticsController.php', 'summarizeEntries');
		$t->assertContains('completedMonthsForAverage($period)', $summarize, 'Analytics monthly averages should use completed months only');
		$t->assertContains('$currentMonthStart', $summarize, 'Analytics monthly averages should exclude the current partial month');
		$t->assertContains("'averageIncomePerWeekCents'", $summarize, 'Analytics summary should expose income average per week');
		$t->assertContains("'averageExpensePerWeekCents'", $summarize, 'Analytics summary should expose expense average per week');
		$t->assertContains("'averageIncomePerDayCents'", $summarize, 'Analytics summary should expose income average per day');
		$t->assertContains("'averageExpensePerDayCents'", $summarize, 'Analytics summary should expose expense average per day');
		$t->assertContains("'averageMonthCount'", $summarize, 'Analytics summary should expose the completed month divisor');
		$t->assertContains("'averageDayCount'", $summarize, 'Analytics summary should expose the elapsed day divisor');

		$completedMonths = $t->methodBody('lib/Controller/AnalyticsController.php', 'completedMonthsForAverage');
		$t->assertContains('min($end, $currentMonthStart)', $completedMonths, 'Analytics should cap monthly averages before the current month');
		$t->assertContains('return $months', $completedMonths, 'Analytics should count completed months for average calculations');
	},

	'Initial migration includes tax relevant labels for entries and templates' => function(TestRunner $t): void {
		$migration = $t->read('lib/Migration/Version000001Date20260713000000.php');
		$t->assertContains("'cobudget_entries'", $migration, 'Initial migration should create entries');
		$t->assertContains("'cobudget_templates'", $migration, 'Initial migration should create templates');
		$t->assertContains("'is_tax_relevant'", $migration, 'Initial migration should add is_tax_relevant');
	},

	'Category and paymentPartner lookups include recent six-month usage counts for frequent suggestions' => function(TestRunner $t): void {
		$usage = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'addRecentUsageCounts');
		$t->assertContains("strtotime('-6 months')", $usage, 'Recent usage should use a six-month window');
		$t->assertContains("from('cobudget_entries')", $usage, 'Recent usage should count entries');
		$t->assertContains("eq('user_id'", $usage, 'Recent usage should be user-specific');
		$t->assertContains("eq('workspace_id'", $usage, 'Recent usage should be workspace-specific');
		$t->assertContains('recent_usage_count', $usage, 'Recent usage should expose recent_usage_count');

		$categoryIndex = $t->methodBody('lib/Controller/CategoryController.php', 'index');
		$t->assertContains("addRecentUsageCounts(array_values(\$filtered), 'category_id', \$workspaceId)", $categoryIndex, 'Category lookup should include recent usage counts');

		$paymentPartnerIndex = $t->methodBody('lib/Controller/PaymentPartnerController.php', 'index');
		$t->assertContains("addRecentUsageCounts(array_values(\$filtered), 'payment_partner_id', \$workspaceId)", $paymentPartnerIndex, 'PaymentPartner lookup should include recent usage counts');

		$dashboardCategories = $t->methodBody('lib/Controller/EntryController.php', 'fetchDashboardCategories');
		$t->assertContains("addRecentUsageCounts(\$categories, 'category_id', \$workspaceId)", $dashboardCategories, 'Dashboard category lookups should include recent usage counts');

		$dashboardPaymentPartners = $t->methodBody('lib/Controller/EntryController.php', 'fetchDashboardPaymentPartners');
		$t->assertContains("addRecentUsageCounts(\$paymentPartners, 'payment_partner_id', \$workspaceId)", $dashboardPaymentPartners, 'Dashboard paymentPartner lookups should include recent usage counts');
	},

	'Performance migration adds compound indexes and dashboard bulk loads project data' => function(TestRunner $t): void {
		$migration = $t->read('lib/Migration/Version000001Date20260713000000.php');
		foreach ([
			'cb_ent_ws_date_id',
			'cb_ent_ws_type_dt',
			'cb_ent_ws_pr_set_dt',
			'cb_ent_ws_user_dt',
			'cb_ent_ws_cat_dt',
			'cb_ent_ws_pp_dt',
			'cb_ent_ws_recur',
			'cb_ent_ws_remind',
			'cb_mem_user_proj',
			'cb_mem_proj_user',
			'cb_proj_ws_arch',
			'cb_cat_ws_type_user',
			'cb_pp_ws_type_user',
			'cb_tpl_ws_user_use',
			'cb_att_ws_entry',
			'cb_set_ws_proj_time',
			'cb_budget_ws_user_upd',
		] as $indexName) {
			$t->assertContains($indexName, $migration, 'Performance migration should add compound index ' . $indexName);
		}
		$t->assertContains('addIndexIfMissing', $migration, 'Initial migration index setup should be safe to rerun');
		$t->assertContains("addUniqueIndex(['project_id', 'user_id'], 'cb_mem_proj_user')", $migration, 'Fresh installs should prevent duplicate area memberships');

		$dashboardProjects = $t->methodBody('lib/Controller/EntryController.php', 'fetchDashboardProjects');
		$t->assertContains('fetchProjectMembersByProjectIds($projectIds)', $dashboardProjects, 'Dashboard projects should bulk-load members');
		$t->assertContains('fetchOpenSharedEntriesByProjectIds($projectIds)', $dashboardProjects, 'Dashboard projects should bulk-load open shared source payments');
		$t->assertNotContains("from('cobudget_entries')", $dashboardProjects, 'Dashboard project loop should not query entries per project');
		$t->assertNotContains("from('cobudget_members')", $dashboardProjects, 'Dashboard project loop should not query members per project');

		$bulkMembers = $t->methodBody('lib/Controller/EntryController.php', 'fetchProjectMembersByProjectIds');
		$t->assertContains('PARAM_INT_ARRAY', $bulkMembers, 'Bulk project member lookup should use integer array parameters');
		$t->assertContains('array_chunk($projectIds, 500)', $bulkMembers, 'Bulk project member lookup should chunk large project sets');

		$bulkEntries = $t->methodBody('lib/Controller/EntryController.php', 'fetchOpenSharedEntriesByProjectIds');
		$t->assertContains('PARAM_INT_ARRAY', $bulkEntries, 'Bulk project entry lookup should use integer array parameters');
		$t->assertContains("in('project_id'", $bulkEntries, 'Bulk shared project entry lookup should be scoped by visible project ids');
		$t->assertNotContains("eq('workspace_id'", $bulkEntries, 'Bulk shared project entry lookup must not hide member-visible projects from other workspaces');
		$t->assertNotContains("eq('type'", $bulkEntries, 'Bulk project balance lookup should include both shared expenses and shared income');
		$t->assertContains("eq('is_settled'", $bulkEntries, 'Bulk project entry lookup should only load open entries');
	},

	'Description hashtags are synced, filtered, exported and cleaned up' => function(TestRunner $t): void {
		$migration = $t->read('lib/Migration/Version000001Date20260713000000.php');
		foreach ([
			"'cobudget_hashtags'",
			"'cobudget_entry_hashtags'",
			'cb_hash_ws_name',
			'cb_ehash_entry_hash',
			'workspace_id',
			'normalized_name',
			'display_name',
			'hashtag_id',
		] as $needle) {
			$t->assertContains($needle, $migration, 'Initial migration should include hashtag schema item ' . $needle);
		}

		$service = $t->read('lib/Service/HashtagService.php');
		foreach ([
			'extractFromText',
			'syncEntryHashtags',
			'deleteEntryHashtags',
			'deleteHashtagsForEntries',
			'deleteWorkspaceHashtags',
			'attachHashtagsToEntries',
			'fetchVisibleHashtagsForUser',
			'cleanupUnusedHashtags',
		] as $needle) {
			$t->assertContains($needle, $service, 'HashtagService should provide ' . $needle);
		}
		$t->assertContains("delete('cobudget_entry_hashtags')", $service, 'HashtagService should delete old entry links before syncing edited descriptions');
		$t->assertContains("delete('cobudget_hashtags')", $service, 'HashtagService should remove unused hashtag rows');
		$t->assertContains('entryHashtagUsageCount($id)', $service, 'HashtagService should keep hashtag rows while other entries still use them');
		$t->assertContains("eq('eh.workspace_id', 'h.workspace_id')", $service, 'Visible hashtag lookup should keep link rows workspace-scoped');
		$t->assertContains("eq('e.workspace_id', 'h.workspace_id')", $service, 'Visible hashtag lookup should keep entry rows workspace-scoped');

		$entry = $t->read('lib/Controller/EntryController.php');
		$t->assertContains('syncEntryHashtags($id, $workspaceId', $entry, 'Entry creates and updates should resync description hashtags');
		$t->assertContains('deleteHashtagsForEntries($entryIds)', $entry, 'Entry deletes should remove hashtag links for the complete payment graph');
		$t->assertContains('attachHashtagsToEntries($entries)', $entry, 'Entry payloads should include hashtags');
		$t->assertContains('fetchVisibleHashtagsForUser($workspaceId', $entry, 'Entry list lookups should include visible hashtag filters');
		$t->assertContains('exportHashtagLabels($entry)', $entry, 'CSV export should include hashtag labels');
		$t->assertContains('hashtag_filter.workspace_id', $entry, 'Hashtag filters should stay workspace-scoped');

			$backup = $t->read('lib/Service/BackupService.php');
			$t->assertContains("'cobudget_hashtags'", $backup, 'BackupService should export hashtags');
			$t->assertContains("'cobudget_entry_hashtags'", $backup, 'BackupService should export hashtag links');
			$t->assertContains("'restore_supported' => true", $backup, 'Personal exports should advertise the guarded empty-state import');
			$t->assertContains("'restore_mode' => 'empty_personal_import'", $backup, 'Personal exports should document the empty-state restore mode');

		$reset = $t->read('lib/Service/UserResetService.php');
		$t->assertContains('deleteHashtagsForEntries', $reset, 'User reset should clean hashtag links for deleted entries');
		$t->assertContains('deleteWorkspaceHashtags', $reset, 'User reset should clean workspace hashtags');

		$cron = $t->read('lib/Cron/RecurringEntriesJob.php');
		$t->assertContains('syncEntryHashtags($newEntryId', $cron, 'Recurring entries should copy description hashtags to the generated entry');
	},

	'Project operations enforce membership or owner visibility and keep critical actions transactional' => function(TestRunner $t): void {
		foreach (['create', 'settle'] as $method) {
			$body = $t->methodBody('lib/Controller/ProjectController.php', $method);
			$t->assertContains('beginTransaction()', $body, 'Project ' . $method . ' should start a transaction');
			$t->assertContains('commit()', $body, 'Project ' . $method . ' should commit transaction');
			$t->assertContains('rollBack()', $body, 'Project ' . $method . ' should roll back transaction');
		}

		foreach (['update', 'destroy', 'archive', 'unarchive', 'show', 'addMember', 'removeMember', 'transferOwnership', 'settle'] as $method) {
			$body = $t->methodBody('lib/Controller/ProjectController.php', $method);
			$hasGuard = strpos($body, 'projectMemberInActiveWorkspace(') !== false
				|| strpos($body, 'projectOwnerInActiveWorkspace(') !== false
				|| strpos($body, 'projectVisibleForCurrentUser(') !== false
				|| strpos($body, 'projectOwnerForCurrentUser(') !== false
				|| strpos($body, 'requireProjectOwner(') !== false;
			$t->assertTrue($hasGuard, 'Project ' . $method . ' should verify membership/ownership visibility');
		}

		foreach (['destroy', 'archive', 'unarchive', 'addMember', 'removeMember', 'updateShares', 'transferOwnership', 'settle'] as $method) {
			$body = $t->methodBody('lib/Controller/ProjectController.php', $method);
			$t->assertContains('requireProjectOwner($id)', $body, 'Project ' . $method . ' should require the area creator');
		}
		$update = $t->methodBody('lib/Controller/ProjectController.php', 'update');
		$t->assertContains('projectOwnerForCurrentUser($id)', $update, 'Project update should require the area creator');
		$destroy = $t->methodBody('lib/Controller/ProjectController.php', 'destroy');
		$t->assertContains('projectHasOpenSharedPayments($id, $workspaceId)', $destroy, 'Project delete should reject areas with open shared payments');
		$t->assertContains("update('cobudget_projects')", $destroy, 'Project delete should archive the area instead of removing financial history');
		$t->assertContains("set('is_archived'", $destroy, 'Project delete should set the archive flag');
		$t->assertNotContains("delete('cobudget_projects')", $destroy, 'Project delete must not physically remove the area');

		foreach (['show', 'settlements'] as $method) {
			$body = $t->methodBody('lib/Controller/ProjectController.php', $method);
			$t->assertContains('projectVisibleForCurrentUser($id)', $body, 'Project ' . $method . ' should remain readable for area members');
		}

		$settle = $t->methodBody('lib/Controller/ProjectController.php', 'settle');
		$t->assertContains("update('cobudget_entries')", $settle, 'Project settle should update entries');
		$t->assertContains('$workspaceId = (int)$project[\'workspace_id\']', $settle, 'Project settle should resolve the workspace from the owner-visible area');
		$t->assertContains("eq('is_settled'", $settle, 'Project settle should only update unsettled entries');
		$t->assertContains("set('settlement_id'", $settle, 'Project settle should link entries to a settlement snapshot');
	},

	'Area settlements store snapshots and repayment suggestions' => function(TestRunner $t): void {
		$migration = $t->read('lib/Migration/Version000001Date20260713000000.php');
		foreach ([
			"'settlement_id'",
			"'cobudget_settlements'",
			"'cobudget_settlement_balances'",
			"'cobudget_settlement_transfers'",
			"'paid_cents'",
			"'share_cents'",
			"'balance_cents'",
			"'amount_cents'",
		] as $needle) {
			$t->assertContains($needle, $migration, 'Settlement migration should include ' . $needle);
		}

		$show = $t->methodBody('lib/Controller/ProjectController.php', 'show');
		$t->assertContains("\$project['repaymentTransfers']", $show, 'Project detail response should include repayment suggestions');
		$t->assertFalse(strpos($show, "\$project['settlements']") !== false, 'Project detail response should keep settlement history off the start page');

		$routes = require $t->path('appinfo/routes.php');
		$routeNames = array_column($routes['routes'], 'url', 'name');
		$t->assertTrue(($routeNames['project#settlements'] ?? null) === '/api/projects/{id}/settlements', 'Project settlement history route should exist');

		$settlements = $t->methodBody('lib/Controller/ProjectController.php', 'settlements');
		$t->assertContains('projectVisibleForCurrentUser($id)', $settlements, 'Settlement history should require project membership');
		$t->assertContains('settlementHistory($id, $workspaceId, null, true)', $settlements, 'Settlement history endpoint should include settlement entries');

		$settle = $t->methodBody('lib/Controller/ProjectController.php', 'settle');
		$t->assertContains("insert('cobudget_settlements')", $settle, 'Settlement should create a settlement header');
		$t->assertContains("insert('cobudget_settlement_balances')", $settle, 'Settlement should store balance snapshots');
		$t->assertContains("insert('cobudget_settlement_transfers')", $settle, 'Settlement should store repayment transfers');
		$t->assertContains('unsettledProjectEntryIds($id, $workspaceId)', $settle, 'Settlement should capture the affected entry set');

		$repayments = $t->methodBody('lib/Controller/ProjectController.php', 'calculateRepaymentTransfers');
		$t->assertContains("'fromUserId'", $repayments, 'Repayment suggestions should identify the debtor');
		$t->assertContains("'toUserId'", $repayments, 'Repayment suggestions should identify the creditor');
		$t->assertContains("'amountCents'", $repayments, 'Repayment suggestions should stay in cents');

		$history = $t->methodBody('lib/Controller/ProjectController.php', 'settlementHistory');
		$t->assertContains('loadSettlementBalances($settlementId)', $history, 'Settlement history should include balance snapshots');
		$t->assertContains('loadSettlementTransfers($settlementId)', $history, 'Settlement history should include repayment transfers');
		$t->assertContains('loadSettlementEntries($settlementId, $projectId, $workspaceId)', $history, 'Settlement history should include entry tables when requested');

		$infoXml = $t->read('appinfo/info.xml');
		if (preg_match('/<version>([^<]+)<\/version>/', $infoXml, $versionMatch) !== 1 || $versionMatch[1] !== '0.2.0') {
			throw new \RuntimeException('The clean initial baseline should keep appinfo/info.xml at version 0.2.0');
		}
	},

	'Category and paymentPartner settings stay workspace-scoped and protect in-use deletes' => function(TestRunner $t): void {
		$trait = $t->read('lib/Controller/WorkspaceAwareTrait.php');
		$t->assertContains("preg_replace('/\\s+/u'", $trait, 'Name normalization should collapse duplicate whitespace before storing and comparing');
		$t->assertContains('normalizeVisibleName($name)', $trait, 'Visible name comparisons should use normalized names');
		$t->assertContains('findGlobalNameMatches', $trait, 'Global category/paymentPartner duplicate detection should be centralized');
		$t->assertContains('firstVisibleGlobalNameMatch', $trait, 'Global duplicate detection should distinguish visible rows');
		$t->assertContains('firstHiddenGlobalNameMatch', $trait, 'Global duplicate detection should reuse hidden rows instead of inserting duplicates');

		$categorySource = $t->read('lib/Controller/CategoryController.php');
		$l10nDe = $t->read('l10n/de.js');
		$t->assertContains('DEFAULT_GLOBAL_CATEGORIES', $categorySource, 'CategoryController should define global starter categories');
		foreach ([
			'Salary' => 'Gehalt',
			'Refunds' => 'Rückerstattungen',
			'Groceries' => 'Lebensmittel',
			'Rent and utilities' => 'Miete & Nebenkosten',
			'Other expenses' => 'Sonstige Ausgaben',
			'Other income' => 'Sonstige Einnahmen',
		] as $key => $translation) {
			$t->assertContains("'" . $key . "'", $categorySource, 'Global starter categories should include l10n key ' . $key);
			$t->assertContains('"' . $key . '": "' . $translation . '"', $l10nDe, 'German l10n should translate starter category ' . $key);
		}
		$t->assertContains('DEFAULT_CATEGORIES_SEEDED_KEY', $categorySource, 'Global starter category seeding should be remembered app-wide');
		$t->assertContains('seedGlobalCategory', $categorySource, 'Global starter category seeding should use a dedicated insert helper');
		$t->assertContains("findGlobalNameMatches('cobudget_categories', \$name, \$type)", $categorySource, 'Global starter category seeding should avoid duplicate visible names');
		foreach (['index', 'settingsData', 'adminIndex'] as $method) {
			$body = $t->methodBody('lib/Controller/CategoryController.php', $method);
			$t->assertContains('$this->ensureDefaultGlobalCategories();', $body, 'Category ' . $method . ' should seed starter categories before listing rows');
		}

		$paymentPartnerSource = $t->read('lib/Controller/PaymentPartnerController.php');
		$t->assertContains('DEFAULT_GLOBAL_PAYMENT_PARTNERS', $paymentPartnerSource, 'PaymentPartnerController should define global starter payment partners');
		foreach ([
			'Employer' => 'Arbeitgeber',
			'Family and friends' => 'Familie & Freunde',
			'Supermarket and bakery' => 'Supermarkt & Bäcker',
			'Landlord and property management' => 'Vermieter & Hausverwaltung',
			'Online shops' => 'Online-Shops',
			'Pharmacy and doctor' => 'Apotheke & Arzt',
		] as $key => $translation) {
			$t->assertContains("'" . $key . "'", $paymentPartnerSource, 'Global starter payment partners should include l10n key ' . $key);
			$t->assertContains('"' . $key . '": "' . $translation . '"', $l10nDe, 'German l10n should translate starter payment partner ' . $key);
		}
		$t->assertContains('DEFAULT_PAYMENT_PARTNERS_SEEDED_KEY', $paymentPartnerSource, 'Global starter payment partner seeding should be remembered app-wide');
		$t->assertContains('seedGlobalPaymentPartner', $paymentPartnerSource, 'Global starter payment partner seeding should use a dedicated insert helper');
		$t->assertContains("findGlobalNameMatches('cobudget_payment_partners', \$name, \$type)", $paymentPartnerSource, 'Global starter payment partner seeding should avoid duplicate visible names');
		foreach (['index', 'settingsData', 'adminIndex'] as $method) {
			$body = $t->methodBody('lib/Controller/PaymentPartnerController.php', $method);
			$t->assertContains('$this->ensureDefaultGlobalPaymentPartners();', $body, 'PaymentPartner ' . $method . ' should seed starter payment partners before listing rows');
		}

		foreach ([
			'CategoryController.php' => [
				'entity' => 'Category',
				'editableGuard' => 'editableCategoryInActiveWorkspace($id)',
				'availableGuard' => 'categoryAvailableInActiveWorkspace($id)',
				'table' => 'cobudget_categories',
				'globalError' => 'A global category with this name already exists.',
			],
			'PaymentPartnerController.php' => [
				'entity' => 'PaymentPartner',
				'editableGuard' => 'editablePaymentPartnerInActiveWorkspace($id)',
				'availableGuard' => 'paymentPartnerAvailableInActiveWorkspace($id)',
				'table' => 'cobudget_payment_partners',
				'globalError' => 'A global payment partner with this name already exists.',
			],
			] as $file => $expectation) {
				$source = $t->read('lib/Controller/' . $file);
				$t->assertNotContains('adminGlobalNameExists', $source, $expectation['entity'] . ' should not keep a second admin-only duplicate checker');

				$create = $t->methodBody('lib/Controller/' . $file, 'create');
				$t->assertContains('validateTypedNamePayload($name, $type)', $create, $expectation['entity'] . ' create should validate name and type centrally');
				$t->assertContains('findVisibleScopedNameMatch(', $create, $expectation['entity'] . ' create should check duplicates centrally');
				$t->assertContains("'workspace_id'", $create, $expectation['entity'] . ' create should write active workspace_id');
				$t->assertContains("'project_id'", $create, $expectation['entity'] . ' create should support project-scoped rows');
				$t->assertContains('requireProjectOwnerForScopedMutation($projectId)', $create, $expectation['entity'] . ' create should require the area creator for project-scoped rows');

				$update = $t->methodBody('lib/Controller/' . $file, 'update');
				$t->assertContains('validateRequiredName($name)', $update, $expectation['entity'] . ' update should validate name centrally');
				$t->assertContains('findVisibleScopedNameMatch(', $update, $expectation['entity'] . ' update should check duplicates centrally');
				$t->assertContains('requireProjectOwnerForScopedMutation($projectId)', $update, $expectation['entity'] . ' update should require the area creator for project-scoped rows');
				$t->assertContains("update('" . $expectation['table'] . "')", $update, $expectation['entity'] . ' update should update own table');
				$t->assertContains("eq('user_id'", $update, $expectation['entity'] . ' update should scope by user_id');
				$t->assertContains("eq('workspace_id'", $update, $expectation['entity'] . ' update should scope by workspace_id');
				$t->assertContains("eq('project_id'", $update, $expectation['entity'] . ' update should scope project rows by project_id');
			$t->assertContains("eq('is_global'", $update, $expectation['entity'] . ' update should not edit global rows');
			$t->assertContains('STATUS_CONFLICT', $update, $expectation['entity'] . ' update should reject duplicates');

			$destroy = $t->methodBody('lib/Controller/' . $file, 'destroy');
			$t->assertContains($expectation['editableGuard'], $destroy, $expectation['entity'] . ' delete should only allow personal or project-member active-workspace rows');
			$t->assertContains('requireProjectOwnerForScopedMutation($projectId)', $destroy, $expectation['entity'] . ' delete should require the area creator for project-scoped rows');
			$t->assertContains("from('cobudget_entries')", $destroy, $expectation['entity'] . ' delete should detect entry usage');
			$t->assertContains("from('cobudget_templates')", $destroy, $expectation['entity'] . ' delete should detect template usage');
			$t->assertContains('STATUS_CONFLICT', $destroy, $expectation['entity'] . ' delete should block rows still in use');
			$t->assertContains("delete('" . $expectation['table'] . "')", $destroy, $expectation['entity'] . ' delete should delete own table');
			$t->assertContains("eq('user_id'", $destroy, $expectation['entity'] . ' delete should scope by user_id');
			$t->assertContains("eq('workspace_id'", $destroy, $expectation['entity'] . ' delete should scope by workspace_id');
			$t->assertContains("eq('project_id'", $destroy, $expectation['entity'] . ' delete should scope project rows by project_id');

			$adminCreate = $t->methodBody('lib/Controller/' . $file, 'adminCreate');
			$t->assertContains("findGlobalNameMatches('" . $expectation['table'] . "'", $adminCreate, $expectation['entity'] . ' admin create should use centralized normalized duplicate detection');
			$t->assertContains('firstVisibleGlobalNameMatch($matches)', $adminCreate, $expectation['entity'] . ' admin create should reject visible global duplicates');
			$t->assertContains('firstHiddenGlobalNameMatch($matches)', $adminCreate, $expectation['entity'] . ' admin create should reactivate hidden duplicates');
			$t->assertContains("set('is_hidden'", $adminCreate, $expectation['entity'] . ' admin create should unhide existing hidden duplicate rows');
			$t->assertContains($expectation['globalError'], $adminCreate, $expectation['entity'] . ' admin create should return a duplicate conflict');

			$adminUpdate = $t->methodBody('lib/Controller/' . $file, 'adminUpdate');
			$t->assertContains("findGlobalNameMatches('" . $expectation['table'] . "'", $adminUpdate, $expectation['entity'] . ' admin update should use centralized normalized duplicate detection');
			$t->assertContains('STATUS_CONFLICT', $adminUpdate, $expectation['entity'] . ' admin update should reject visible or hidden duplicates');

			$adminUnhide = $t->methodBody('lib/Controller/' . $file, 'adminUnhide');
			$t->assertContains("findGlobalNameMatches('" . $expectation['table'] . "'", $adminUnhide, $expectation['entity'] . ' admin unhide should verify no visible duplicate exists');
			$t->assertContains('firstVisibleGlobalNameMatch($matches)', $adminUnhide, $expectation['entity'] . ' admin unhide should reject visible duplicates');
			$t->assertContains('STATUS_CONFLICT', $adminUnhide, $expectation['entity'] . ' admin unhide should fail clearly when a duplicate is visible');

			foreach (['hide', 'unhide'] as $method) {
				$body = $t->methodBody('lib/Controller/' . $file, $method);
				$t->assertContains($expectation['availableGuard'], $body, $expectation['entity'] . ' ' . $method . ' should only allow visible active-workspace rows');
			}
		}
	},

	'Templates validate reachable references and persist amount_cents in active workspace' => function(TestRunner $t): void {
		$routes = require $t->path('appinfo/routes.php');
		$templateRoutes = [];
		foreach ($routes['routes'] as $route) {
			if (str_starts_with((string)$route['name'], 'template#')) {
				$templateRoutes[] = $route['verb'] . ' ' . $route['url'] . ' ' . $route['name'];
			}
		}
		sort($templateRoutes);
		$t->assertSame([
			'DELETE /api/templates/{id} template#destroy',
			'GET /api/templates template#index',
			'POST /api/templates template#create',
			'POST /api/templates/{id}/use template#markUsed',
		], $templateRoutes, 'Template API should expose list, create, usage marker and delete');

		$source = $t->read('lib/Controller/TemplateController.php');
		$t->assertNotContains('function update(', $source, 'TemplateController should not expose an untested update endpoint');
		$t->assertNotContains('recurrenceInterval', $source, 'Templates should not persist recurrence settings');
		$t->assertNotContains('reminderDate', $source, 'Templates should not persist reminder settings');

		$migration = $t->read('lib/Migration/Version000001Date20260713000000.php');
		$t->assertContains('usage_count', $migration, 'Template usage migration should add usage_count');

		$index = $t->methodBody('lib/Controller/TemplateController.php', 'index');
		$t->assertContains('templatesEnabled()', $index, 'Template index should respect the user setting');
		$t->assertContains("eq('t.user_id'", $index, 'Template index should scope by user_id');
		$t->assertContains("eq('t.workspace_id'", $index, 'Template index should scope by workspace_id');
		$t->assertContains("orderBy('t.usage_count', 'DESC')", $index, 'Template index should sort most-used templates first');
		$t->assertContains("addOrderBy('t.name', 'ASC')", $index, 'Template index should use name as the tie-breaker');
		$t->assertContains('usage_count', $index, 'Template index should expose usage_count');
		$t->assertContains('normalizeAmountRow($t)', $index, 'Template index should normalize amount rows');

		$validation = $t->methodBody('lib/Controller/WorkspaceAwareTrait.php', 'validateTemplatePayload');
		foreach ([
			'validateTypedNamePayload($name, $type)',
			'validateAmountCents($amount, $amountCents, true)',
			'validateEntryReferences($projectId, $categoryId, $paymentPartnerId)',
		] as $needle) {
			$t->assertContains($needle, $validation, 'Template validation should contain ' . $needle);
		}

		$create = $t->methodBody('lib/Controller/TemplateController.php', 'create');
		$t->assertContains('templatesEnabled()', $create, 'Template create should respect the user setting');
		$t->assertContains("if (\$type !== 'expense')", $create, 'Template create should keep subscription/fixed-cost expense-only');
		foreach ([
			'validateTemplatePayload($name, $description, $type, $amount, $amountCents, $categoryId, $paymentPartnerId, $projectId)',
			"'amount_cents'",
			"'workspace_id'",
			"'is_subscription'",
			"'is_fixed_cost'",
			"'is_child_related'",
			"'is_important'",
			"'needs_review'",
			"'is_tax_relevant'",
		] as $needle) {
			$t->assertContains($needle, $create, 'Template create should contain ' . $needle);
		}
		$t->assertNotContains("'date'", $create, 'Template create should not write a payment date');
		$t->assertNotContains('recurrence', $create, 'Template create should not write recurrence fields');
		$t->assertNotContains('reminder', $create, 'Template create should not write reminder fields');

		$destroy = $t->methodBody('lib/Controller/TemplateController.php', 'destroy');
		$t->assertContains('templatesEnabled()', $destroy, 'Template delete should respect the user setting');
		$t->assertContains('templateOwnedInActiveWorkspace($id)', $destroy, 'Template delete should only delete owned active-workspace templates');
		$t->assertContains("delete('cobudget_templates')", $destroy, 'Template delete should delete templates table');
		$t->assertContains("eq('user_id'", $destroy, 'Template delete should scope by user_id');
		$t->assertNotContains("eq('workspace_id'", $destroy, 'Template delete relies on the active-workspace ownership guard instead of duplicating the workspace predicate');

		$markUsed = $t->methodBody('lib/Controller/TemplateController.php', 'markUsed');
		$t->assertContains('templatesEnabled()', $markUsed, 'Template usage marker should respect the user setting');
		$t->assertContains('templateOwnedInActiveWorkspace($id)', $markUsed, 'Template usage marker should only update owned active-workspace templates');
		$t->assertContains("update('cobudget_templates')", $markUsed, 'Template usage marker should update templates table');
		$t->assertContains('usage_count', $markUsed, 'Template usage marker should increment usage_count');
		$t->assertContains("eq('user_id'", $markUsed, 'Template usage marker should scope by user_id');
		$t->assertNotContains("eq('workspace_id'", $markUsed, 'Template usage marker relies on the active-workspace ownership guard instead of duplicating the workspace predicate');

		$infoXml = $t->read('appinfo/info.xml');
		if (preg_match('/<version>([^<]+)<\/version>/', $infoXml, $versionMatch) !== 1 || $versionMatch[1] !== '0.2.0') {
			throw new \RuntimeException('The clean initial baseline should keep appinfo/info.xml at version 0.2.0');
		}
	},

	'Workspace delete removes personal workspace data and blocks shared areas' => function(TestRunner $t): void {
		$source = $t->read('lib/Controller/WorkspaceController.php');
		$destroy = $t->methodBody('lib/Controller/WorkspaceController.php', 'destroy');
		$t->assertContains("from('cobudget_workspaces')", $destroy, 'Workspace delete should verify workspace row first');
		$t->assertContains("eq('user_id'", $destroy, 'Workspace delete should verify workspace belongs to user');
		$t->assertContains('Cannot delete the default workspace', $destroy, 'Workspace delete should protect default workspace');
		$t->assertContains('beginTransaction()', $destroy, 'Workspace delete should start transaction');
		$t->assertContains('commit()', $destroy, 'Workspace delete should commit transaction');
		$t->assertContains('rollBack()', $destroy, 'Workspace delete should roll back transaction');

		foreach (['create', 'update'] as $method) {
			$body = $t->methodBody('lib/Controller/WorkspaceController.php', $method);
			$t->assertContains('validateRequiredName($name', $body, 'Workspace ' . $method . ' should validate name centrally');
			$t->assertContains('workspaceNameExists(', $body, 'Workspace ' . $method . ' should reject duplicate names');
			$t->assertContains('STATUS_CONFLICT', $body, 'Workspace ' . $method . ' should report duplicate names as conflict');
		}

		foreach (['cobudget_entries', 'cobudget_categories', 'cobudget_payment_partners', 'cobudget_templates', 'cobudget_budget_goals', 'cobudget_budget_snapshots', 'cobudget_projects', 'cobudget_workspaces'] as $table) {
			$t->assertContains($table, $source, 'Workspace delete should handle ' . $table);
		}
		foreach (['cobudget_entry_attachments', 'cobudget_settlements', 'cobudget_settlement_balances', 'cobudget_settlement_transfers', 'cobudget_members'] as $table) {
			$t->assertContains($table, $source, 'Workspace delete should handle dependent area rows from ' . $table);
		}
		$t->assertContains('projectIdsInWorkspace($id)', $destroy, 'Workspace delete should inspect every area attached to the workspace');
		$t->assertContains('projectIdsOwnedByOtherUsers($projectIds)', $destroy, 'Workspace delete should block areas whose ownership has moved to another user');
		$t->assertContains('workspaceHasExternalMemberReferences($id, $projectIds)', $destroy, 'Workspace delete should preserve member workspaces used by areas outside the deletion graph');
		$t->assertContains('workspaceDeleteHasSharedProjectData($projectIds)', $destroy, 'Workspace delete should block shared areas before deleting project data');
		$t->assertContains('STATUS_CONFLICT', $destroy, 'Workspace delete should report shared areas as a conflict instead of deleting them');
		$t->assertContains('projectIdsWithOtherMembers', $source, 'Workspace delete should detect owned areas with other members');
		$t->assertContains('projectIdsWithOtherUserEntries', $source, 'Workspace delete should detect stale entries from other users in owned areas');
		$t->assertContains("neq('user_id'", $source, 'Workspace delete should identify other users before deleting area rows');
		$t->assertContains('entryProjectionService->prepareEntryDeletion(', $destroy, 'Workspace delete should expand entry removal to the complete projection graph');
		$t->assertContains('entryIdsForWorkspaceDelete($id, $projectIds)', $destroy, 'Workspace delete should collect current-user entries only after shared-area checks pass');
		$t->assertContains('dataIntegrityService->assertProjectionIntegrity()', $destroy, 'Workspace delete should verify the remaining projection graph before commit');
		$t->assertContains("deleteRowsByColumnValues('cobudget_entry_attachments', 'entry_id', \$entryIds)", $destroy, 'Workspace delete should remove attachments before entries');
		$t->assertContains('deleteHashtagsForEntries($entryIds)', $destroy, 'Workspace delete should remove hashtag links for deleted entries');
		$t->assertContains('deleteWorkspaceHashtags($id)', $destroy, 'Workspace delete should remove remaining hashtags through the hashtag service');
		$t->assertContains("deleteRowsByColumnValues('cobudget_settlement_balances', 'settlement_id', \$settlementIds)", $destroy, 'Workspace delete should remove settlement balances');
		$t->assertContains("deleteRowsByColumnValues('cobudget_settlement_transfers', 'settlement_id', \$settlementIds)", $destroy, 'Workspace delete should remove settlement transfers');
		$t->assertContains("deleteRowsByColumnValues(\$table, 'project_id', \$projectIds)", $destroy, 'Workspace delete should remove project-scoped settings regardless of row user_id');
		$t->assertContains("deleteRowsByWorkspaceAndUser(\$table, 'user_id', \$id)", $destroy, 'Workspace delete should keep personal settings scoped to the current user');
		$t->assertContains("deleteRowsByColumnValues('cobudget_members', 'project_id', \$projectIds)", $destroy, 'Workspace delete should remove project members before deleting projects');
		$t->assertContains("deleteRowsByIds('cobudget_projects', \$projectIds)", $destroy, 'Workspace delete should delete only projects that passed all ownership and sharing guards');
		$t->assertContains('owner_id', $source, 'Workspace delete should scope projects by owner_id');
		$t->assertContains('user_id', $source, 'Workspace delete should scope user-owned rows by user_id');
		$t->assertContains('IQueryBuilder::PARAM_INT_ARRAY', $source, 'Workspace delete should use typed array parameters for id lists');
	},

		'Recurring job transfers the active recurrence series head atomically' => function(TestRunner $t): void {
		$source = $t->read('lib/Cron/RecurringEntriesJob.php');
		$t->assertContains('private const JOB_INTERVAL_SECONDS = 5 * 60', $source, 'Recurring job should match the 5-minute web cron cadence');
		$t->assertContains('setInterval(self::JOB_INTERVAL_SECONDS)', $source, 'Recurring job should use the shared 5-minute interval constant');

		$run = $t->methodBody('lib/Cron/RecurringEntriesJob.php', 'run');
		$t->assertContains('beginTransaction()', $run, 'Recurring job should start transaction for new entry and series-head handoff');
		$t->assertContains('commit()', $run, 'Recurring job should commit transaction');
		$t->assertContains('rollBack()', $run, 'Recurring job should roll back transaction');
		$t->assertContains("'amount_cents'", $run, 'Recurring job should preserve amount_cents');
		$t->assertContains("'split_mode'", $run, 'Recurring job should preserve split_mode');
		$t->assertContains("'split_user_id'", $run, 'Recurring job should preserve split target user');
		$t->assertContains("'workspace_id'", $run, 'Recurring job should preserve workspace_id');
		foreach (["'is_child_related'", "'is_important'", "'needs_review'", "'is_tax_relevant'"] as $column) {
			$t->assertContains($column, $run, 'Recurring job should preserve Kennzeichen column ' . $column);
		}
		$t->assertContains("'recurrence_parent_id'", $run, 'Recurring job should link generated child entry to parent');
		$t->assertContains("'recurrence_series_id'", $run, 'Recurring job should preserve the recurrence series id');
		$t->assertContains('recurrenceSeriesIdFromRow($entry)', $run, 'Recurring job should determine the series id before inserting the next entry');
		$t->assertContains('deactivateCurrentSeriesHead($entry)', $run, 'Recurring job should deactivate the previous active series head');
		$t->assertContains('$hasNextRun', $run, 'Recurring job should only transfer active recurrence fields while the series still has a next run');
		$t->assertContains('recurrenceDueCutoff($now)', $run, 'Recurring job should use the current due cutoff');
		$t->assertContains('normalizeToRecurrenceTime((int)$entry', $run, 'Recurring job should generate child entries at the fixed recurrence time');

			$deactivate = $t->methodBody('lib/Cron/RecurringEntriesJob.php', 'deactivateCurrentSeriesHead');
			$t->assertContains("update('cobudget_entries')", $deactivate, 'Recurring job should update the previous series head');
			$t->assertContains("set('recurrence_interval'", $deactivate, 'Recurring job should clear the previous active interval');
			$t->assertContains("set('recurrence_next_date'", $deactivate, 'Recurring job should clear the previous next recurrence date');
			$t->assertContains("set('recurrence_end_date'", $deactivate, 'Recurring job should clear the previous end date from the old head');
			$t->assertContains("set('recurrence_series_id'", $deactivate, 'Recurring job should keep the previous head linked to its series');

			$dueCutoff = $t->methodBody('lib/Cron/RecurringEntriesJob.php', 'recurrenceDueCutoff');
			$t->assertContains('return $now;', $dueCutoff, 'Recurring job should process entries whose next recurrence timestamp is due now or in the past');

			$normalize = $t->methodBody('lib/Cron/RecurringEntriesJob.php', 'normalizeToRecurrenceTime');
			$t->assertContains('setTime(self::RECURRENCE_HOUR, self::RECURRENCE_MINUTE, 0)', $normalize, 'Recurring job should normalize recurrence timestamps to 09:00');
		},

		'Reminder job runs on the same 5-minute cadence as the web cron' => function(TestRunner $t): void {
			$source = $t->read('lib/Cron/RemindersJob.php');
			$t->assertContains('private const JOB_INTERVAL_SECONDS = 5 * 60', $source, 'Reminder job should match the 5-minute web cron cadence');
			$t->assertContains('setInterval(self::JOB_INTERVAL_SECONDS)', $source, 'Reminder job should use the shared 5-minute interval constant');

			$run = $t->methodBody('lib/Cron/RemindersJob.php', 'run');
			foreach ([
				"'title'",
				"'amount'",
				"'currency'",
				"'category'",
				"'paymentPartner'",
				"'entryDate'",
				"'reminderDate'",
			] as $notificationParam) {
				$t->assertContains($notificationParam, $run, 'Reminder notification should include ' . $notificationParam);
			}
			$t->assertContains("eq('user_id'", $run, 'Reminder update should scope by user_id');
			$t->assertContains("eq('workspace_id'", $run, 'Reminder update should scope by workspace_id');

			$notifier = $t->read('lib/Notification/Notifier.php');
			$l10nDe = $t->read('l10n/de.js');
			$t->assertContains('CoBudget reminder: %s', $notifier, 'Notifier should use a clear reminder subject l10n key');
			$t->assertContains('"CoBudget reminder: %s": "CoBudget-Erinnerung: %s"', $l10nDe, 'German l10n should translate the reminder subject');
			$t->assertContains('buildReminderMessage', $notifier, 'Notifier should build a useful reminder message');
			$t->assertContains('Reminder due since %s', $notifier, 'Reminder message should include the due timestamp l10n key');
			$t->assertContains('"Reminder due since %s": "Erinnerung fällig seit %s"', $l10nDe, 'German l10n should translate the reminder due timestamp');
			$t->assertContains('UnknownNotificationException', $notifier, 'Notifier should use the current Nextcloud exception for notifications from other apps');
			$t->assertContains('setParsedMessageWhenPresent', $notifier, 'Notifier should not submit empty parsed messages to Nextcloud');
			$t->assertContains('CoBudget notification', $notifier, 'Notifier should render obsolete CoBudget notifications with a safe fallback');
			$t->assertNotContains('\\OC::$server', $notifier, 'Notifier should use injected public Nextcloud APIs');
		},

		'Backup job runs after 03:00 and prevents overlapping user backups' => function(TestRunner $t): void {
			$source = $t->read('lib/Cron/BackupJob.php');
			$t->assertContains('private const JOB_INTERVAL_SECONDS = 5 * 60', $source, 'Backup job should check frequently enough for WebCron catch-up');
			$t->assertContains('private const BACKUP_HOUR = 3', $source, 'Backup job should use the fixed 03:00 backup hour');
			$t->assertContains('private const BACKUP_MINUTE = 0', $source, 'Backup job should use the fixed 03:00 backup minute');
			$t->assertContains('private const BACKUP_LOCK_KEY', $source, 'Backup job should define a per-user running lock key');
			$t->assertContains('private const BACKUP_LOCK_TTL_SECONDS = 6 * 60 * 60', $source, 'Backup job should recover stale locks after a generous TTL');
			$t->assertContains('setInterval(self::JOB_INTERVAL_SECONDS)', $source, 'Backup job should use the shared interval constant');

			$run = $t->methodBody('lib/Cron/BackupJob.php', 'run');
			$t->assertContains('isDue($schedule, $lastRun, $now)', $run, 'Backup job should only create due backups');
			$t->assertContains('acquireUserBackupLock($userId, time())', $run, 'Backup job should acquire a per-user backup lock before creating files');
			$t->assertContains('createBackup($userId)', $run, 'Backup job should create the automatic backup');
			$t->assertContains('LAST_AUTO_BACKUP_KEY', $run, 'Backup job should record successful automatic backup time');
			$t->assertContains('finally', $run, 'Backup job should release locks in a finally block');
			$t->assertContains('releaseUserBackupLock($userId, $backupLock)', $run, 'Backup job should release the matching backup lock');

			$isDue = $t->methodBody('lib/Cron/BackupJob.php', 'isDue');
			$t->assertContains('currentScheduleSlot($now)', $isDue, 'Backup due check should use the fixed daily 03:00 slot');
			$t->assertContains('$lastRun >= $currentSlot', $isDue, 'Backup due check should avoid duplicate backups in the same slot');
			$t->assertContains('nextDueSlot($schedule, $lastRun)', $isDue, 'Backup due check should respect daily, weekly, and monthly intervals');

			$currentSlot = $t->methodBody('lib/Cron/BackupJob.php', 'currentScheduleSlot');
			$t->assertContains('setTime(self::BACKUP_HOUR, self::BACKUP_MINUTE, 0)', $currentSlot, 'Backup slot should be normalized to 03:00');
			$t->assertContains('return null;', $currentSlot, 'Backup job should wait until 03:00 before the daily window opens');

			$lock = $t->methodBody('lib/Cron/BackupJob.php', 'acquireUserBackupLock');
			$t->assertContains('deleteStaleUserBackupLock($userId, $now)', $lock, 'Backup lock should clear stale locks before acquiring');
			$t->assertContains('acquireBackupLock($userId, self::BACKUP_LOCK_KEY, $now)', $lock, 'User backup lock should use the shared atomic lock helper');

			$sharedLock = $t->methodBody('lib/Cron/BackupJob.php', 'acquireBackupLock');
			$t->assertContains("insert('preferences')", $sharedLock, 'Backup lock should be acquired atomically through the preferences unique key');
			$t->assertContains('return null;', $sharedLock, 'Backup lock should skip when another backup is already running');

			$release = $t->methodBody('lib/Cron/BackupJob.php', 'releaseUserBackupLock');
			$t->assertContains('releaseBackupLock($userId, self::BACKUP_LOCK_KEY, $lockValue)', $release, 'User backup lock release should use the shared lock helper');

			$sharedRelease = $t->methodBody('lib/Cron/BackupJob.php', 'releaseBackupLock');
			$t->assertContains("delete('preferences')", $sharedRelease, 'Backup lock release should remove the preferences lock row');
			$t->assertContains("eq('configvalue'", $sharedRelease, 'Backup lock release should only remove the matching lock token');
		},

		'Backup service and OCC commands distinguish backup and restore scopes' => function(TestRunner $t): void {
			$service = $t->read('lib/Service/BackupService.php');
			$t->assertContains('private const USER_EXPORT_FILE_PATTERN', $service, 'Backup service should keep a personal-export filename family');
			$t->assertContains('private const FULL_BACKUP_FILE_PATTERN', $service, 'Backup service should keep a full-backup filename family');
			$t->assertNotContains('LEGACY_BACKUP_FOLDER', $service, 'Technical reset should not keep old backup folder compatibility');
			$t->assertContains("private const USER_EXPORT_FILE_PATTERN = '/^(?:cobudget-personal-export|cobudget-backup)-", $service, 'Backup service should accept new personal-export names and legacy user-backup names for download/list compatibility');
			$t->assertContains('private const BACKUP_TABLES', $service, 'Backup service should keep an explicit list of exported CoBudget tables');
			$t->assertContains('private const USER_COLUMNS', $service, 'Backup service should know all user reference columns for restore mapping');
			$t->assertContains('private const RESTORE_LOCK_KEY', $service, 'Backup service should define a restore lock key');
			$t->assertContains('private const RESTORE_LOCK_TTL_SECONDS', $service, 'Backup service should recover stale restore locks');
			$t->assertContains('private const SETTINGS_DEFAULTS', $service, 'Backup service should export effective settings, not only stored preferences');
			$t->assertContains("'enable_workspaces' => 'no'", $service, 'Backup settings defaults should include the workspace feature flag');
			$t->assertContains("'show_workspace_switcher' => 'yes'", $service, 'Backup settings defaults should include the workspace switcher flag');
			$t->assertContains("'hidden_workspaces' => '[]'", $service, 'Backup settings defaults should include hidden workspace ids');
			$t->assertContains('settingsDefaultForUser($userId, $key)', $service, 'Personal export should export effective per-user settings defaults');
			$t->assertContains("if (\$key === 'enable_workspaces' && \$this->userHasManagedWorkspaces(\$userId))", $service, 'Workspace backup default should stay enabled when extra workspaces exist');
			$t->assertContains('backupContainsUserManagedWorkspaces($tables, $userId)', $service, 'Restore should recover workspace-enabled state when extra workspaces exist even if older backups stored the default no');
			$t->assertContains('public function createBackup(string $userId', $service, 'Backup service should expose personal exports');
			$t->assertContains('public function createFullBackup(string $storageUserId', $service, 'Backup service should expose system-scoped backups');
			$t->assertContains('public function createConfiguredFullBackup(): array', $service, 'Backup service should create full backups from admin settings');
			$t->assertContains('private IGroupManager $groupManager', $service, 'Backup service should resolve Nextcloud administrators for full-backup storage');
			$t->assertContains('assertFullBackupStorageAdmin($storageUserId)', $service, 'All full-backup paths should enforce an administrator Files owner');
			$t->assertContains("'storage_user_is_admin' =>", $service, 'Full-backup settings should expose whether the configured Files owner remains an administrator');
			$t->assertContains('public function listConfiguredFullBackups(): array', $service, 'Backup service should list configured full backups for the admin UI');
			$t->assertContains('public function restoreConfiguredFullBackup(string $fileName, string $confirmation): array', $service, 'Backup service should restore configured full backups from the admin UI');
			$t->assertContains("if (\$confirmation !== 'RESTORE')", $service, 'Admin full backup restore should require explicit RESTORE confirmation');
			$t->assertContains('preg_match(self::FULL_BACKUP_FILE_PATTERN, $fileName)', $service, 'Admin full backup restore should only accept full-backup filenames');
			$t->assertContains('public function listBackups(string $userId', $service, 'Backup service should expose backup listing');
			$t->assertContains('backupLookupFolders($userId)', $service, 'Backup listing should use the configured CoBudget backup folder');
			$t->assertContains('public function deleteBackup(string $userId', $service, 'Backup service should expose personal export deletion');
			$t->assertContains('public function inspectBackup(string $userId', $service, 'Backup service should expose restore inspection');
			$t->assertContains('public function restoreBackup(string $userId', $service, 'Backup service should expose guarded personal export restore');
			$t->assertContains('public function restoreFullBackup(string $storageUserId', $service, 'Backup service should expose system-scoped restores');
			$t->assertContains("'scope' => 'user'", $service, 'Personal export manifest should declare scope user');
			$t->assertContains("'type' => 'personal_export'", $service, 'Personal export manifest should declare the export type');
			$t->assertContains("'export_mode' => 'personal_share'", $service, 'Personal export manifest should clarify its reduced user perspective');
			$t->assertContains("'restore_supported' => true", $service, 'Personal export manifest should mark guarded restore as supported');
			$t->assertContains("'restore_mode' => 'empty_personal_import'", $service, 'Personal export manifest should document the empty-state restore mode');
			$t->assertContains("'scope' => 'system'", $service, 'Full backup manifest should declare scope system');
			$t->assertContains("'storage_user_id' => \$storageUserId", $service, 'Full backup manifest should record the storage user');
			$t->assertContains('collectFullBackupData()', $service, 'Full backup should collect all app data');
			$t->assertContains('fetchAllSettings($userIds)', $service, 'Full backup should export all effective CoBudget user settings');
			$t->assertContains('self::SETTINGS_DEFAULTS[$key]', $service, 'Personal export should include defaulted settings values');
			$t->assertContains('fetchAllSettings($userIds)', $service, 'Full backup should include effective settings for every exported user');
			$t->assertContains('backupContainsUserManagedWorkspaces(', $service, 'Restore should infer workspace-enabled state only for older backups that omit the setting and contain managed workspaces');
			$t->assertContains('normalizeFullSettings($archive[\'settings\'], $tables)', $service, 'Full restore should normalize workspace settings with table context');
			$t->assertContains('deleteAllBackupTables()', $service, 'Full restore should wipe existing app tables before import');
			$t->assertContains('deleteAllCoBudgetSettings()', $service, 'Full restore should wipe existing app settings before import');
			$t->assertContains('applyUserMapToTables(', $service, 'Full restore should support user ID mapping for moved servers');
			$t->assertContains('buildBackupUserRows(', $service, 'Restore inspection should expose user mapping rows');
			$t->assertContains('buildRestoreReport(', $service, 'Restore should build a visible import protocol');
			$t->assertContains('restoreReportUserMappings(', $service, 'Restore report should list applied user mappings');
			$t->assertContains('restoreReportTableRows(', $service, 'Restore report should list imported table rows');
			$t->assertContains('restoreReportSettingsRows(', $service, 'Restore report should list imported settings');
			$t->assertContains('backupTableLabel(', $service, 'Restore report should expose readable table labels');
			$t->assertContains('assertPersonalImportTargetIsEmpty($userId)', $service, 'Personal export restore should reject non-empty target users');
			$t->assertContains('preparePersonalImportTables(', $service, 'Personal export restore should sanitize the reduced personal export before import');
			$t->assertContains('personalImportEntryShareCents(', $service, 'Personal export restore should calculate the importing user share for shared entries');
			$t->assertContains('roundPersonalImportShareCents(', $service, 'Personal export restore should round the importing user share directly');
			$t->assertContains('intdiv(($amountCents * $share * 2) + $totalShare, $totalShare * 2)', $service, 'Personal export restore should round half cents up, e.g. 52.87 at 50% to 26.44');
			$t->assertContains("'shared_data_restore_mode' => \$containsOtherUsers ? 'personal_share' : 'direct'", $service, 'Personal export inspect should announce personal-share restore mode for shared data');
			$t->assertContains('insertTablesWithGeneratedIds($tables)', $service, 'Personal export restore should insert imported rows with generated local IDs');
			$t->assertNotContains('filterUserRestoreTables(', $service, 'Personal export restore should not keep partial user restore filtering');
			$t->assertNotContains('assertUserRestoreScope(', $service, 'Personal export restore should not keep partial user restore scope checks');
			$t->assertNotContains('assertRowsBelongToUser(', $service, 'Personal export restore should not keep partial user restore row checks');
			$t->assertContains("'report' => \$this->buildRestoreReport('user'", $service, 'Personal export restore should return an import protocol');
			$t->assertContains('assertProjectMemberConsistency($tables)', $service, 'Full restore should reject manipulated shared-area user assignments before deleting current data');
			$t->assertContains("'report' => \$this->buildRestoreReport('system'", $service, 'Full restore response should include the restore report');
			$t->assertContains("'cobudget_entry_attachments' => 'Beleg-Pfade'", $service, 'Restore report should identify attachment rows as receipt paths');
			$t->assertContains("'files_copied' => false", $service, 'Restore report should clarify that receipt files are not copied');
			$t->assertContains("'workspaces' => [", $service, 'Restore report should expose imported workspace counts');
			$t->assertContains('assertReferencedUsersExist(', $service, 'Restore should fail clearly when referenced users are missing');
			$t->assertContains('assertBackupZipEntries(', $service, 'Restore should reject unexpected files inside backup ZIPs');
			$t->assertContains('assertManifestTableCounts(', $service, 'Restore should verify manifest table counts before importing');
			$t->assertContains('private const BACKUP_TABLE_COLUMNS', $service, 'Backup restore should keep a strict per-table column allowlist');
			$t->assertContains('normalizeTableRows($table', $service, 'Backup restore should validate imported row columns per table while reading the archive');
			$t->assertContains('filterBackupRowColumns($table, $row)', $service, 'Backup restore should not insert raw backup JSON columns');
			$t->assertContains('assertBackupArchive($archive, \'user\')', $service, 'Personal export inspection should validate the whole export archive');
			$t->assertContains('assertBackupArchive($archive, \'system\')', $service, 'Full restore should validate the whole backup archive');
			$t->assertContains('synchronizeAutoincrementSequences(', $service, 'Restore should synchronize PostgreSQL sequences after explicit IDs');
			$t->assertContains('$safetyBackup = $this->createBackup($userId, null, $this->getSafetyBackupRetentionCount($userId))', $service, 'Personal export restore should create a personal safety export before importing');
			$t->assertContains('$safetyBackup = $this->createFullBackup(', $service, 'Full restore should create a safety backup before replacing data');
			$t->assertContains("'safety_backup' => \$safetyBackup", $service, 'Restore responses should expose the safety backup');
			$t->assertContains('acquireRestoreLock()', $service, 'Restore should acquire a lock before replacing data');
			$t->assertContains('releaseRestoreLock($restoreLock)', $service, 'Restore should release the lock in a finally block');
			$columnFilter = $t->methodBody('lib/Service/BackupService.php', 'filterBackupRowColumns');
			$t->assertContains('self::BACKUP_TABLE_COLUMNS[$table]', $columnFilter, 'Backup row filtering should resolve the allowlist by table');
			$t->assertContains('nicht erlaubte Spalte', $columnFilter, 'Backup row filtering should reject unknown columns explicitly');
			$insertRow = $t->methodBody('lib/Service/BackupService.php', 'insertRow');
			$t->assertContains('$row = $this->filterBackupRowColumns($table, $row);', $insertRow, 'Backup insert should re-check the allowlist before writing rows');
			$userProjectScope = $t->methodBody('lib/Service/BackupService.php', 'fetchProjectIdsForUser');
			$t->assertContains("eq('owner_id'", $userProjectScope, 'Personal exports should include areas owned by the user');
			$t->assertContains('cobudget_members', $userProjectScope, 'Personal exports should include shared areas where the user is a member');
			$t->assertNotContains('workspace_id', $userProjectScope, 'Personal exports must not include foreign-owned areas through workspace scope');
					$userEntriesScope = $t->methodBody('lib/Service/BackupService.php', 'fetchEntries');
					$t->assertContains("eq('user_id'", $userEntriesScope, 'Personal exports should include only payments owned by the exporting user');
					$t->assertContains("eq('entry_kind'", $userEntriesScope, 'Personal exports should include only materialized personal payments');
					$t->assertNotContains("in('project_id'", $userEntriesScope, 'Personal exports must not include shared source payments through area membership');
					$collectPersonalExport = $t->methodBody('lib/Service/BackupService.php', 'collectBackupData');
					$t->assertContains('$tables = $this->preparePersonalExportTables($tables, $userId, $settingIdAliases)', $collectPersonalExport, 'Personal exports must reduce shared-area raw rows before the archive is written');
					$preparePersonalExport = $t->methodBody('lib/Service/BackupService.php', 'preparePersonalExportTables');
					$t->assertContains('preparePersonalImportTables($tables, $userId, $userId, $settingIdAliases)', $preparePersonalExport, 'Personal exports should use the same personal-share sanitizer as restore, with the exporting user as source');
					foreach (['fetchCategories', 'fetchPaymentPartners', 'fetchTemplates', 'fetchBudgetGoals', 'fetchBudgetSnapshots'] as $method) {
						$methodScope = $t->methodBody('lib/Service/BackupService.php', $method);
						$t->assertNotContains("in('workspace_id'", $methodScope, 'Personal exports must not include ' . $method . ' rows through workspace scope');
					}
					foreach (['fetchBudgetGoals', 'fetchBudgetSnapshots'] as $method) {
						$methodScope = $t->methodBody('lib/Service/BackupService.php', $method);
						$t->assertContains("eq('user_id'", $methodScope, 'Personal export ' . $method . ' rows should stay user-scoped');
					}
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
				'cobudget_hashtags',
				'cobudget_entry_hashtags',
			] as $table) {
				$t->assertContains("'" . $table . "'", $service, 'Full backup should export table ' . $table);
			}

			$userCommand = $t->read('lib/Command/CreateBackupCommand.php');
			$t->assertContains("setName('cobudget:backup:create')", $userCommand, 'Personal export command should keep its OCC name');
			$t->assertContains('addArgument(\'user\'', $userCommand, 'Personal export command should require a user argument');
			$t->assertContains('createBackup(', $userCommand, 'Personal export command should call createBackup');

			$fullCommand = $t->read('lib/Command/CreateFullBackupCommand.php');
			$t->assertContains("setName('cobudget:backup:create-full')", $fullCommand, 'Full backup command should expose the requested OCC name');
			$t->assertContains("addOption('user'", $fullCommand, 'Full backup command should require a storage user option');
			$t->assertContains('Bitte Speicher-Benutzer mit --user angeben.', $fullCommand, 'Full backup command should fail clearly without storage user');
			$t->assertContains('createFullBackup(', $fullCommand, 'Full backup command should call createFullBackup');

			$userRestoreCommand = $t->read('lib/Command/RestoreBackupCommand.php');
			$t->assertContains("setName('cobudget:backup:restore')", $userRestoreCommand, 'Personal restore command should keep the expected OCC name');
			$t->assertContains('addArgument(\'user\'', $userRestoreCommand, 'Personal restore command should require the target user argument');
			$t->assertContains('addArgument(\'file\'', $userRestoreCommand, 'Personal restore command should require the export file argument');
			$t->assertContains("addOption('folder'", $userRestoreCommand, 'Personal restore command should support a custom export folder');
			$t->assertContains("addOption('force'", $userRestoreCommand, 'Personal restore command should require explicit confirmation');
			$t->assertContains('Bestehende CoBudget-Daten blockieren den Import.', $userRestoreCommand, 'Personal restore command should explain that imports require an empty target');
			$t->assertContains('restoreBackup(', $userRestoreCommand, 'Personal restore command should call the guarded restore service');
			$t->assertContains('Sicherheitsexport:', $userRestoreCommand, 'Personal restore command should print the safety export');
			$t->assertContains('Importierte Daten:', $userRestoreCommand, 'Personal restore command should print imported table counts');
			$t->assertContains('User-Mapping:', $userRestoreCommand, 'Personal restore command should print source-to-target account mapping');
			$t->assertContains('Beleg-Pfade:', $userRestoreCommand, 'Personal restore command should print receipt path import notes');

			$fullRestoreCommand = $t->read('lib/Command/RestoreFullBackupCommand.php');
			$t->assertContains("setName('cobudget:backup:restore-full')", $fullRestoreCommand, 'Full restore command should expose the expected OCC name');
			$t->assertContains("addOption('file'", $fullRestoreCommand, 'Full restore command should require a backup file option');
			$t->assertContains("addOption('map-user'", $fullRestoreCommand, 'Full restore command should support user mapping');
			$t->assertContains("addOption('force'", $fullRestoreCommand, 'Full restore command should require an explicit force option');
			$t->assertContains('restoreFullBackup(', $fullRestoreCommand, 'Full restore command should call restoreFullBackup');
			$t->assertContains('Sicherheitsbackup:', $fullRestoreCommand, 'Full restore command should print the safety backup file');
			$t->assertContains('Restore-Protokoll:', $fullRestoreCommand, 'Full restore command should print the restore report');
			$t->assertContains('User-Mapping:', $fullRestoreCommand, 'Full restore command should print mapped users');
			$t->assertContains('Beleg-Pfade:', $fullRestoreCommand, 'Full restore command should print receipt path import notes');

			$routes = $t->read('appinfo/routes.php');
			$t->assertContains("'backup#inspect'", $routes, 'Backup inspect API route should be registered');
			$t->assertContains("'/api/backups/{fileName}/inspect'", $routes, 'Backup inspect API route should target a selected backup file');
			$t->assertContains("'backup#restore'", $routes, 'Backup restore API route should be registered');
			$t->assertContains("'/api/backups/{fileName}/restore'", $routes, 'Backup restore API route should target a selected backup file');
			$t->assertContains("'backup#destroy'", $routes, 'Backup delete API route should be registered');
			$t->assertContains("'verb' => 'DELETE'", $routes, 'Backup delete API route should use DELETE');

			$backupController = $t->read('lib/Controller/BackupController.php');
			$backupDownload = $t->methodBody('lib/Controller/BackupController.php', 'download');
			$t->assertContains('@NoCSRFRequired', $backupController, 'Backup download may be opened directly by the browser');
			$t->assertContains('authErrorResponse()', $backupDownload, 'Backup download should still require an authenticated user');
			$t->assertContains('getBackupFile((string)$this->userId, $fileName)', $backupDownload, 'Backup download should be scoped to the current user backup folder');
			$t->assertContains('Persönlicher Export konnte nicht heruntergeladen werden.', $backupDownload, 'Personal export download should return a generic validation error');
			$t->assertContains('Persönlicher Export wurde nicht gefunden.', $backupDownload, 'Personal export download should return a generic not-found error');
			$t->assertContains("getParam('confirmation', '')", $backupController, 'Personal export restore API should require explicit RESTORE confirmation');
			$t->assertContains("!== 'RESTORE'", $backupController, 'Personal export restore API should validate the RESTORE confirmation');
			$t->assertContains('restoreBackup((string)$this->userId, $fileName)', $backupController, 'Personal export restore API should call the guarded restore service');
			$t->assertContains('Persönlicher Export konnte nicht wiederhergestellt werden.', $backupController, 'Personal export restore API should return generic restore errors');
			$t->assertContains('use Psr\\Log\\LoggerInterface;', $backupController, 'Backup controller should use the Nextcloud logger abstraction');
			$t->assertContains('private LoggerInterface $logger', $backupController, 'Backup controller should inject a logger for internal exception details');
			$t->assertContains('loggedErrorResponse(', $backupController, 'Backup controller should centralize logged generic API errors');
			$t->assertContains('$this->logger->error(', $backupController, 'Backup controller should log internal exception details');
			$t->assertContains('Persönlicher Export konnte nicht erstellt werden.', $backupController, 'Personal export create errors should return a generic client message');
			$t->assertNotContains('errorResponse($e->getMessage()', $backupController, 'Backup controller should not expose raw exception messages to clients');
			$t->assertNotContains("['error' => \$e->getMessage()", $backupController, 'Backup controller should not expose raw exception messages in JSON responses');

			$adminBackupController = $t->read('lib/Controller/AdminBackupController.php');
			$t->assertContains('private LoggerInterface $logger', $adminBackupController, 'Admin backup controller should inject a logger for internal exception details');
			$t->assertContains('loggedErrorResponse(', $adminBackupController, 'Admin backup controller should centralize logged generic API errors');
			$t->assertContains('int $status = Http::STATUS_INTERNAL_SERVER_ERROR', $adminBackupController, 'Admin backup controller should preserve validation status codes while returning generic messages');
			$t->assertContains('Http::STATUS_BAD_REQUEST)', $adminBackupController, 'Admin backup validation errors should keep HTTP 400');
			$t->assertNotContains('errorResponse($e->getMessage()', $adminBackupController, 'Admin backup controller should not expose raw exception messages to clients');
			$t->assertNotContains("['error' => \$e->getMessage()", $adminBackupController, 'Admin backup controller should not expose raw exception messages in JSON responses');

			$backupService = $t->read('lib/Service/BackupService.php');
			$t->assertContains('private const BACKUP_TABLE_COLUMNS', $backupService, 'Backup restore should define strict table-column allow-lists');
			$t->assertContains('filterBackupRowColumns($table, $row)', $backupService, 'Backup restore should filter every imported row through the allow-list');
			$t->assertContains('Backup enthält eine nicht erlaubte Spalte', $backupService, 'Backup restore should reject unexpected backup columns');
			$t->assertContains('$row = $this->filterBackupRowColumns($table, $row)', $backupService, 'Backup restore should re-check columns before inserting rows');
			$t->assertContains('public function personalRestoreState(string $userId)', $backupService, 'Personal export list should expose whether restore is possible before opening the modal');
			$t->assertContains("'can_restore' => false", $backupService, 'Personal export list should disable restore for non-empty target users');
			$t->assertContains('$tables = $this->preparePersonalImportTables($archive[\'tables\'], $userId, $sourceUserId)', $backupService, 'Personal restore should normalize multi-user exports before importing rows');
			$t->assertContains('personalImportEntryShareCents(', $backupService, 'Personal restore should import shared entries only with the source user share');
			$t->assertContains('roundPersonalImportShareCents(', $backupService, 'Personal restore should use direct half-up rounding for personal shares');
			$t->assertContains('personalArchiveRestoreInfo($archive, $sourceUserId, $userId)', $backupService, 'Personal export inspect should expose shared-data restore warnings');

			$settingsView = $t->read('src/views/SettingsView.vue');
			$texts = $t->read('src/l10n/texts.js');
			$t->assertContains("confirmation: 'RESTORE'", $settingsView, 'Personal export settings should send explicit restore confirmation');
			$t->assertContains('backup.can_restore === false', $settingsView, 'Personal export settings should disable restore when the target user is not empty');
			$t->assertContains("/inspect`),", $settingsView, 'Personal export settings should inspect the archive before confirming restore');
			$t->assertContains('restoreBackupSharedDataImportMode', $texts, 'Personal export settings should explain personal-share restore mode for shared data');
			$t->assertNotContains('RESTORE_REPORT_STORAGE_KEY', $settingsView, 'Personal export settings should not keep a restore report state');
			$t->assertNotContains('restoreReport = response.data?.restore?.report', $settingsView, 'Personal export settings should not read user restore reports');
			$t->assertNotContains('storeRestoreReport(this.restoreReport)', $settingsView, 'Personal export settings should not store user restore reports');
			$t->assertNotContains('restoreUserMappings', $settingsView, 'Personal export settings should not show user mappings');
			$t->assertNotContains('restoreSkippedRows', $settingsView, 'Personal export settings should not show skipped rows');
			$t->assertContains('$texts.settings.createBackupNow()', $settingsView, 'Settings should expose manual personal export creation');
			$t->assertContains('$texts.settings.latestBackups()', $settingsView, 'Settings should list personal exports');
			$t->assertContains("backups: () => tx('Personal export')", $texts, 'Settings personal export title should be centralized in l10n texts');
		},

		'User reset creates a safety backup and protects shared areas' => function(TestRunner $t): void {
			$routes = require $t->path('appinfo/routes.php');
			$routeNames = array_column($routes['routes'], 'url', 'name');
			$t->assertSame('/api/settings/reset-preview', $routeNames['user#resetPreview'] ?? null, 'User reset preview route should exist');
			$t->assertSame('/api/settings/reset', $routeNames['user#resetAll'] ?? null, 'User reset route should exist');

			$controller = $t->read('lib/Controller/UserController.php');
			$t->assertContains('UserResetService', $controller, 'User controller should use the reset service');
			$t->assertContains('resetPreview(): DataResponse', $controller, 'User controller should expose a reset preview');
			$t->assertContains('resetAll(): DataResponse', $controller, 'User controller should expose the destructive reset endpoint');
			$t->assertContains("getParam('confirmation', '')", $controller, 'User reset should require a server-side confirmation token');
			$t->assertContains('ResetBlockedException', $controller, 'User reset should return structured blockers for open shared areas');

			$resetService = $t->read('lib/Service/UserResetService.php');
			$t->assertContains("CONFIRMATION_TEXT = 'RESET'", $resetService, 'User reset should require the exact RESET confirmation text');
			$t->assertContains("SAFETY_BACKUP_FOLDER = 'CoBudget/Export'", $resetService, 'User reset should write safety exports to the personal export folder');
			$t->assertContains('SAFETY_BACKUP_RETENTION = 8', $resetService, 'User reset should keep a conservative safety-backup retention');
			$t->assertContains('createBackup($userId, self::SAFETY_BACKUP_FOLDER', $resetService, 'User reset should create a safety backup before deleting data');
			$t->assertContains('blocking_shared_projects', $resetService, 'User reset preview should expose shared areas that block the reset');
			$t->assertContains('countUnsettledProjectEntries', $resetService, 'User reset should block shared areas with open entries');
			$t->assertContains('transferable_shared_projects', $resetService, 'User reset preview should report owned settled shared areas whose member shares will be preserved');
				$t->assertContains('entryProjectionService->detachSettledMember($projectId', $resetService, 'User reset should detach already-materialized personal payments before deleting an owned shared area');
				$t->assertContains('transferred_personal_entries', $resetService, 'User reset should report preserved personal payments for other members');
				$t->assertContains('transferred_attachments', $resetService, 'User reset should report preserved personal receipt copies for other members');
			$t->assertContains('leaveSettledSharedProject', $resetService, 'User reset should leave settled shared areas created by another member');
			$t->assertContains('$fileOwnerUserId !== null', $resetService, 'User reset should never delete another members receipt file');
			$t->assertContains("getUserValue(\$userId, self::APP_ID, 'delete_receipts_with_entry'", $resetService, 'User reset should respect the receipt file deletion setting');
			$t->assertContains('resetUserSettings', $resetService, 'User reset should reset all user settings to defaults');
			$t->assertContains('createDefaultWorkspaceForUser', $resetService, 'User reset should recreate a default main workspace');

			$settingsView = $t->read('src/views/SettingsView.vue');
			$texts = $t->read('src/l10n/texts.js');
			$t->assertContains('resetTitle()', $settingsView, 'Settings should expose the reset section as the final danger action');
			$t->assertContains("resetTitle: () => tx('Delete everything and reset')", $texts, 'Reset section title should be centralized in l10n texts');
			$t->assertContains('/apps/cobudget/api/settings/reset-preview', $settingsView, 'Settings reset UI should load the reset preview');
			$t->assertContains('/apps/cobudget/api/settings/reset', $settingsView, 'Settings reset UI should call the reset endpoint');
			$t->assertContains("requiredText: 'RESET'", $settingsView, 'Settings reset UI should require explicit RESET entry');
			$t->assertContains('clearWorkspaceId()', $settingsView, 'Settings reset UI should clear stale active workspace selection through the shared storage helper');
		},

		'Data integrity command reports orphan references and duplicate visible names' => function(TestRunner $t): void {
			$command = $t->read('lib/Command/CheckDataIntegrityCommand.php');
			$t->assertContains("setName('cobudget:integrity:check')", $command, 'Data integrity command should expose a stable OCC name');
			$t->assertContains("addOption('repair'", $command, 'Data integrity command should offer an explicit repair option');
			$t->assertContains("addOption('merge-category'", $command, 'Data integrity command should offer an explicit category merge option');
			$t->assertContains("addOption('merge-payment-partner'", $command, 'Data integrity command should offer an explicit payment partner merge option');
			$t->assertContains('dataIntegrityService->inspect()', $command, 'Data integrity command should inspect without modifying data by default');
			$t->assertContains('dataIntegrityService->repair($report)', $command, 'Data integrity command should repair only after inspection');
			$t->assertContains("mergeDuplicate('category'", $command, 'Data integrity command should merge category duplicates only when requested');
			$t->assertContains("mergeDuplicate('payment_partner'", $command, 'Data integrity command should merge payment partner duplicates only when requested');
			$t->assertContains('Zum Reparieren: occ cobudget:integrity:check --repair', $command, 'Data integrity command should print the repair command');
			$t->assertContains('Merge-Vorschlag: occ cobudget:integrity:check --%s=%d:%s', $command, 'Data integrity command should print duplicate merge suggestions');
			$t->assertContains('Sichtbare Namens-Dubletten', $command, 'Data integrity command should report visible duplicate names');
			$t->assertContains('Dubletten werden nur mit explizitem Merge-Befehl zusammengefuehrt', $command, 'Data integrity command should avoid risky automatic merges');

			$service = $t->read('lib/Service/DataIntegrityService.php');
			$t->assertContains('orphanReferences', $service, 'Data integrity service should report orphan references');
			$t->assertContains('duplicateVisibleNames', $service, 'Data integrity service should report duplicate visible names');
			$t->assertContains('MERGE_TARGETS', $service, 'Data integrity service should define safe merge targets');
			$t->assertContains('public function mergeDuplicate', $service, 'Data integrity service should support explicit duplicate merges');
			$t->assertContains('assertRowsCanBeMerged', $service, 'Data integrity service should validate duplicate rows before merging');
			$t->assertContains('replaceReferences(\'cobudget_entries\'', $service, 'Data integrity service should update entry references while merging');
			$t->assertContains('replaceReferences(\'cobudget_templates\'', $service, 'Data integrity service should update template references while merging');
			$t->assertContains('replaceBudgetCriteriaReferences', $service, 'Data integrity service should update budget criteria for category merges');
			$t->assertContains('deleteRows($table, $mergeIds)', $service, 'Data integrity service should remove merged duplicate rows');
			foreach ([
				'cobudget_entries',
				'cobudget_templates',
				'cobudget_categories',
				'cobudget_payment_partners',
				'cobudget_projects',
			] as $table) {
				$t->assertContains("'" . $table . "'", $service, 'Data integrity service should inspect table ' . $table);
			}

			$repair = $t->methodBody('lib/Service/DataIntegrityService.php', 'repair');
			$t->assertContains('beginTransaction()', $repair, 'Data integrity repair should run in a transaction');
			$t->assertContains('commit()', $repair, 'Data integrity repair should commit successful repairs');
			$t->assertContains('rollBack()', $repair, 'Data integrity repair should roll back failed repairs');
			$t->assertContains('clearReference(', $repair, 'Data integrity repair should clear orphan references');

			$clearReference = $t->methodBody('lib/Service/DataIntegrityService.php', 'clearReference');
			$t->assertContains("set(\$column, \$qb->createNamedParameter(null, \\PDO::PARAM_NULL))", $clearReference, 'Data integrity repair should null invalid references');

				$duplicates = $t->methodBody('lib/Service/DataIntegrityService.php', 'duplicateVisibleNames');
				$t->assertContains('normalizeVisibleName($name)', $duplicates, 'Data integrity duplicate detection should normalize names');
				$t->assertContains('duplicateScopeKey($row)', $duplicates, 'Data integrity duplicate detection should include user/workspace/project/global scope');
				$t->assertContains('isVisibleNameRow($row)', $duplicates, 'Data integrity duplicate detection should skip hidden rows');
				$t->assertContains("'repairable' => false", $duplicates, 'Visible name duplicates should be reported but not auto-repaired');

				$duplicateScopeKey = $t->methodBody('lib/Service/DataIntegrityService.php', 'duplicateScopeKey');
				foreach (['user_id', 'workspace_id', 'project_id', 'is_global'] as $scopeColumn) {
					$t->assertContains("'" . $scopeColumn . "'", $duplicateScopeKey, 'Duplicate merge scope should include ' . $scopeColumn);
				}

				$mergeGuard = $t->methodBody('lib/Service/DataIntegrityService.php', 'assertRowsCanBeMerged');
				$t->assertContains('rowsHaveSameDuplicateScope($keepRow, $mergeRow)', $mergeGuard, 'Explicit duplicate merges should reject rows from different scopes');

			$routes = require $t->path('appinfo/routes.php');
			$routeNames = array_column($routes['routes'], 'url', 'name');
			$t->assertTrue(($routeNames['integrity#inspect'] ?? null) === '/api/admin/integrity', 'Integrity inspect route should exist in the admin API');
			$t->assertTrue(($routeNames['integrity#repair'] ?? null) === '/api/admin/integrity/repair', 'Integrity repair route should exist in the admin API');
			$t->assertTrue(($routeNames['integrity#merge'] ?? null) === '/api/admin/integrity/merge', 'Integrity merge route should exist in the admin API');

			$controller = $t->read('lib/Controller/IntegrityController.php');
			$t->assertContains('DataIntegrityService', $controller, 'Integrity controller should use the shared integrity service');
			$t->assertContains('IGroupManager', $controller, 'Integrity controller should inject the Nextcloud admin group manager');
			$t->assertContains('IUserSession', $controller, 'Integrity controller should check the current user');
			$t->assertContains('requireAdmin()', $controller, 'Integrity controller should use an explicit admin guard');
			$t->assertContains('groupManager->isAdmin', $controller, 'Integrity controller should check Nextcloud admin status');
			$t->assertContains('dataIntegrityService->inspect()', $controller, 'Integrity controller should expose inspect without modifying data');
			$t->assertContains('dataIntegrityService->repair($report)', $controller, 'Integrity controller should repair from a fresh report');
			$t->assertContains('dataIntegrityService->mergeDuplicate($type, $keepId, $mergeIds)', $controller, 'Integrity controller should expose explicit duplicate merges');
			$t->assertContains("['error' => \$message]", $controller, 'Integrity controller should return JSON errors');

			$adminSettings = $t->read('src/components/AdminSettings.vue');
			$texts = $t->read('src/l10n/texts.js');
			$t->assertContains('/apps/cobudget/api/admin/integrity', $adminSettings, 'Admin settings should load the integrity report');
			$t->assertContains('/apps/cobudget/api/admin/integrity/repair', $adminSettings, 'Admin settings should trigger orphan-reference repair');
			$t->assertContains('/apps/cobudget/api/admin/integrity/merge', $adminSettings, 'Admin settings should trigger explicit duplicate merges');
			$t->assertContains('dataQuality()', $adminSettings, 'Admin settings should show a data quality section');
			$t->assertContains("dataQuality: () => tx('Data quality')", $texts, 'Admin data quality title should be centralized in l10n texts');
			$t->assertContains('keepId(id)', $adminSettings, 'Admin settings should require choosing the duplicate row to keep');
		},

		'Budget goals are workspace-scoped and evaluated with personal shares' => function(TestRunner $t): void {
			$routes = require $t->path('appinfo/routes.php');
			$routeNames = array_column($routes['routes'], 'url', 'name');
			$t->assertTrue(($routeNames['budget#index'] ?? null) === '/api/budgets', 'Budget list route should exist');
			$t->assertTrue(($routeNames['budget#create'] ?? null) === '/api/budgets', 'Budget create route should exist');
			$t->assertTrue(($routeNames['budget#update'] ?? null) === '/api/budgets/{id}', 'Budget update route should exist');
			$t->assertTrue(($routeNames['budget#destroy'] ?? null) === '/api/budgets/{id}', 'Budget delete route should exist');

			$migration = $t->read('lib/Migration/Version000001Date20260713000000.php');
			$t->assertContains('cobudget_budget_goals', $migration, 'Budget migration should create the goals table');
			$t->assertContains("'amount_cents'", $migration, 'Budget migration should store amounts as integer cents');
			$t->assertContains("'criteria_json'", $migration, 'Budget migration should store flexible criteria JSON');
			$t->assertContains("['user_id', 'workspace_id']", $migration, 'Budget migration should index user/workspace lookups');

			$snapshotMigration = $t->read('lib/Migration/Version000001Date20260713000000.php');
			$t->assertContains('cobudget_budget_snapshots', $snapshotMigration, 'Budget snapshot migration should create the history table');
			foreach (["'snapshot_reason'", "'goal_name'", "'spent_cents'", "'planned_cents'", "'buffer_cents'", "'forecast_cents'", "'progress_tenths'", "'status'"] as $column) {
				$t->assertContains($column, $snapshotMigration, 'Budget snapshot migration should include ' . $column);
			}

			$create = $t->methodBody('lib/Controller/BudgetController.php', 'create');
			$t->assertContains('validateBudgetPayload($name, $amount, $period, $mode, $criteria, $workspaceId)', $create, 'Budget create should validate through one payload helper');
			$t->assertContains("insert('cobudget_budget_goals')", $create, 'Budget create should insert into the budget table');
			foreach (["'user_id'", "'workspace_id'", "'amount_cents'", "'criteria_json'"] as $column) {
				$t->assertContains($column, $create, 'Budget create should write ' . $column);
			}

			$update = $t->methodBody('lib/Controller/BudgetController.php', 'update');
			$t->assertContains('BudgetSnapshotService', $t->read('lib/Controller/BudgetController.php'), 'Budget controller should inject snapshot service');
			$t->assertContains('loadBudgetGoal($id, $workspaceId)', $update, 'Budget update should load only current user/workspace goals');
			$t->assertContains("snapshotGoalForCurrentPeriod((string)\$this->userId, \$currentGoal, 'changed')", $update, 'Budget update should snapshot the previous goal state');
			$t->assertContains("update('cobudget_budget_goals')", $update, 'Budget update should update the budget table');
			$t->assertContains("set('amount_cents'", $update, 'Budget update should preserve integer cents');
			$t->assertContains("set('criteria_json'", $update, 'Budget update should persist normalized criteria');
			$t->assertContains("eq('user_id'", $update, 'Budget update should scope by user');
			$t->assertContains("eq('workspace_id'", $update, 'Budget update should scope by workspace');

			$destroy = $t->methodBody('lib/Controller/BudgetController.php', 'destroy');
			$t->assertContains('loadBudgetGoal($id, $workspaceId)', $destroy, 'Budget delete should load the current goal before deleting');
			$t->assertContains("snapshotGoalForCurrentPeriod((string)\$this->userId, \$currentGoal, 'deleted')", $destroy, 'Budget delete should snapshot the final goal state');
			$t->assertContains("delete('cobudget_budget_goals')", $destroy, 'Budget delete should delete from the budget table');
			$t->assertContains("eq('user_id'", $destroy, 'Budget delete should scope by user');
			$t->assertContains("eq('workspace_id'", $destroy, 'Budget delete should scope by workspace');

			$validation = $t->methodBody('lib/Controller/BudgetController.php', 'validateBudgetPayload');
			$t->assertContains('validateRequiredName($name', $validation, 'Budget validation should reject empty names');
			$t->assertContains("validateAmountCents(\$amount, \$amountCents, false, 'Ungültiges Budget')", $validation, 'Budget validation should use the shared cents validator');
			$t->assertContains("in_array(\$period, ['month', 'year'], true)", $validation, 'Budget validation should allow only supported periods');
			$t->assertContains("in_array(\$mode, ['flexible', 'hard'], true)", $validation, 'Budget validation should allow only supported modes');
			$t->assertContains('normalizeCriteria($criteria)', $validation, 'Budget validation should normalize criteria');
			$t->assertContains('validateCriteria($criteria, $workspaceId)', $validation, 'Budget validation should verify referenced ids');

			$validateCriteria = $t->methodBody('lib/Controller/BudgetController.php', 'validateCriteria');
			$t->assertContains('projectMemberInActiveWorkspace($rule[\'projectId\'])', $validateCriteria, 'Budget project criteria should require membership in the active workspace');
			$t->assertContains('categorySelectableForBudget($rule[\'categoryId\'], $workspaceId)', $validateCriteria, 'Budget category criteria should be checked for selectable active-workspace categories');

			$entries = $t->methodBody('lib/Controller/BudgetController.php', 'loadVisibleExpenseEntries');
			$t->assertContains("eq('e.workspace_id'", $entries, 'Budget entries should be scoped to the active workspace');
			$t->assertContains("eq('e.type'", $entries, 'Budget entries should include only expenses');
			$t->assertContains("eq('e.user_id'", $entries, 'Budget personal entries should be scoped to the current user');
			$t->assertContains("eq('e.entry_kind'", $entries, 'Budget evaluation should count only materialized personal payments');
			$t->assertNotContains('cobudget_members', $entries, 'Budget evaluation must not count shared source payments a second time');
			$t->assertContains("'e.split_user_id'", $entries, 'Budget entries should expose explicit single-user split targets');

			$evaluateGoal = $t->methodBody('lib/Controller/BudgetController.php', 'evaluateGoal');
			$t->assertContains('$spentCents += max(0, $this->amountCentsFromRow($entry) ?? 0)', $evaluateGoal, 'Budget evaluation should sum the exact materialized personal cents');
			$t->assertNotContains('entryPersonalCents', $evaluateGoal, 'Budget evaluation must not recalculate a materialized share from current area defaults');

			$entryMatchesCriteria = $t->methodBody('lib/Controller/BudgetController.php', 'entryMatchesCriteria');
			$t->assertContains("if (\$rules === []) {\n\t\t\treturn true;", $entryMatchesCriteria, 'Budget goals without criteria should match all visible expenses');
			$t->assertContains("(\$rule['projectId'] ?? null) !== null", $entryMatchesCriteria, 'Budget criteria rows should support project matching');
			$t->assertContains("(\$rule['categoryId'] ?? null) !== null", $entryMatchesCriteria, 'Budget criteria rows should support category matching');
			$t->assertContains('self::TAG_COLUMNS[$tag]', $entryMatchesCriteria, 'Budget criteria rows should support Kennzeichen matching');
			$t->assertContains('return true;', $entryMatchesCriteria, 'Budget criteria rows should match when all chosen values in one row fit');

			$snapshotService = $t->read('lib/Service/BudgetSnapshotService.php');
			$t->assertContains('public function snapshotGoalForCurrentPeriod', $snapshotService, 'Budget snapshot service should snapshot changed goals');
			$t->assertContains('public function createDueSnapshots', $snapshotService, 'Budget snapshot service should create period close snapshots');
			$t->assertContains('public function history', $snapshotService, 'Budget snapshot service should expose history for analytics');
			$t->assertContains("cobudget_budget_snapshots", $snapshotService, 'Budget snapshot service should write the snapshot table');
			$t->assertContains("'period_closed'", $snapshotService, 'Budget snapshot service should distinguish closed periods');
			$t->assertContains('$spentCents += max(0, $this->amountCentsFromRow($entry) ?? 0)', $snapshotService, 'Budget snapshots should sum exact materialized personal cents');
			$t->assertNotContains('entryShareCentsForUser', $snapshotService, 'Budget snapshots must not recalculate materialized shares');
			$t->assertContains("'e.split_user_id'", $snapshotService, 'Budget snapshots should include explicit single-user split targets');
			$t->assertContains('snapshotExists', $snapshotService, 'Budget snapshot cron should avoid duplicate closed-period snapshots');

			$snapshotHistory = $t->methodBody('lib/Service/BudgetSnapshotService.php', 'history');
			$t->assertContains("innerJoin('s', 'cobudget_budget_goals'", $snapshotHistory, 'Budget snapshot history should only show snapshots for existing goals');
			$t->assertContains("eq('g.id', 's.budget_goal_id')", $snapshotHistory, 'Budget snapshot history should hide deleted budget goals');

			$snapshotJob = $t->read('lib/Cron/BudgetSnapshotJob.php');
			$t->assertContains('createDueSnapshots()', $snapshotJob, 'Budget snapshot job should create due snapshots');

			$infoXml = $t->read('appinfo/info.xml');
			$t->assertContains('OCA\\CoBudget\\Cron\\BudgetSnapshotJob', $infoXml, 'Budget snapshot job should be listed in app metadata');
			if (preg_match('/<version>([^<]+)<\/version>/', $infoXml, $versionMatch) !== 1 || $versionMatch[1] !== '0.2.0') {
				throw new \RuntimeException('The clean initial baseline should keep appinfo/info.xml at version 0.2.0');
			}
		},

		'Entry attachments are feature-gated and workspace-scoped' => function(TestRunner $t): void {
			$routes = require $t->path('appinfo/routes.php');
			$routeNames = array_column($routes['routes'], 'url', 'name');
			$t->assertTrue(($routeNames['entry#attachments'] ?? null) === '/api/entries/{id}/attachments', 'Entry attachment list route should exist');
			$t->assertTrue(($routeNames['entry#uploadAttachment'] ?? null) === '/api/entries/{id}/attachments', 'Entry attachment upload route should exist');
			$t->assertTrue(($routeNames['entry#downloadAttachment'] ?? null) === '/api/entries/{id}/attachments/{attachmentId}/download', 'Entry attachment display route should exist');
			$t->assertTrue(($routeNames['entry#destroyAttachment'] ?? null) === '/api/entries/{id}/attachments/{attachmentId}', 'Entry attachment delete route should exist');

			$migration = $t->read('lib/Migration/Version000001Date20260713000000.php');
			foreach (["'cobudget_entry_attachments'", "'entry_id'", "'workspace_id'", "'owner_user_id'", "'file_path'", "'file_name'"] as $needle) {
				$t->assertContains($needle, $migration, 'Attachment migration should include ' . $needle);
			}

			$entrySource = $t->read('lib/Controller/EntryController.php');
			$t->assertContains('FileDisplayResponse', $entrySource, 'Attachment downloads should render files inline where possible');
			$t->assertContains('@NoCSRFRequired', $entrySource, 'Attachment display route should allow direct browser opening without CSRF failure');

			foreach (['attachments', 'uploadAttachment', 'downloadAttachment', 'destroyAttachment'] as $method) {
				$body = $t->methodBody('lib/Controller/EntryController.php', $method);
				$t->assertContains('receiptsEnabled()', $body, 'Attachment ' . $method . ' should respect the receipts feature flag');
				$t->assertContains('validatePositiveId($id)', $body, 'Attachment ' . $method . ' should validate entry ids');
				$t->assertContains('entryVisibleInActiveWorkspace($id)', $body, 'Attachment ' . $method . ' should require a visible active-workspace entry');
			}

			$download = $t->methodBody('lib/Controller/EntryController.php', 'downloadAttachment');
			$t->assertContains('$workspaceId !== null && (int)$workspaceId !== $activeWorkspaceId', $download, 'Attachment display with explicit workspace should reject mismatched workspaces');
			$t->assertContains('fetchEntryAttachment($attachmentId, $id, (int)$activeWorkspaceId)', $download, 'Attachment display should load the exact entry/workspace attachment row');
			$t->assertContains('new FileDisplayResponse', $download, 'Attachment display should use inline file responses');

			$fetchList = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryAttachments');
			$t->assertContains("eq('entry_id'", $fetchList, 'Attachment lists should scope by entry id');
			$t->assertContains("eq('workspace_id'", $fetchList, 'Attachment lists should scope by workspace id');

			$fetchOne = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryAttachment');
			$t->assertContains("eq('id'", $fetchOne, 'Single attachment lookup should scope by attachment id');
			$t->assertContains("eq('entry_id'", $fetchOne, 'Single attachment lookup should scope by entry id');
			$t->assertContains("eq('workspace_id'", $fetchOne, 'Single attachment lookup should scope by workspace id');

			$destroyAttachment = $t->methodBody('lib/Controller/EntryController.php', 'destroyAttachment');
			$t->assertContains('attachmentOwnerUserId($attachment)', $destroyAttachment, 'Direct receipt deletion should resolve the Nextcloud Files owner');
			$t->assertContains('$ownerUserId !== (string)$this->userId', $destroyAttachment, 'Direct receipt deletion should reject other area members');
			$t->assertContains('Http::STATUS_FORBIDDEN', $destroyAttachment, 'Foreign receipt deletion should return forbidden');
			$t->assertContains('deleteAttachmentRow($attachmentId, $id, (int)$workspaceId, $ownerUserId)', $destroyAttachment, 'Direct receipt row deletion should carry the verified owner scope');

			$deleteAttachmentRow = $t->methodBody('lib/Controller/EntryController.php', 'deleteAttachmentRow');
			$t->assertContains("eq('owner_user_id'", $deleteAttachmentRow, 'Receipt row deletion should include the verified owner in the SQL scope');

			$folderPath = $t->methodBody('lib/Controller/EntryController.php', 'attachmentFolderPath');
			$t->assertContains("'receipt_storage_folder'", $folderPath, 'Attachment folders should use the configurable base path');
			$t->assertContains("'receipt_folder_grouping'", $folderPath, 'Attachment folders should use the configurable grouping');
			$t->assertContains("date('Y'", $folderPath, 'Attachment folders should support yearly grouping');
			$t->assertContains("date('m'", $folderPath, 'Attachment folders should support monthly grouping');

			$normalizeFolder = $t->methodBody('lib/Controller/EntryController.php', 'normalizedReceiptStorageFolder');
			$t->assertContains("str_contains(\$folder, '\\\\')", $normalizeFolder, 'Attachment folder normalization should reject backslashes');
			$t->assertContains("preg_match('~(^|/)\\.\\.(/|$)~'", $normalizeFolder, 'Attachment folder normalization should reject path traversal');
			$t->assertContains("return 'CoBudget/Belege';", $normalizeFolder, 'Attachment folder normalization should fall back to the default path');

			$deleteEntryRows = $t->methodBody('lib/Controller/EntryController.php', 'deleteEntryAttachmentRows');
			$deleteEntryFiles = $t->methodBody('lib/Controller/EntryController.php', 'deleteEntryAttachmentFilesAfterCommit');
			$t->assertContains("delete('cobudget_entry_attachments')", $deleteEntryRows, 'Entry deletion should remove attachment rows transactionally');
			$t->assertContains("eq('workspace_id'", $deleteEntryRows, 'Entry attachment row cleanup should stay workspace-scoped');
			$t->assertContains('ownerWantsAttachmentFileDeletedWithEntry($attachment)', $deleteEntryFiles, 'Entry deletion should evaluate automatic file cleanup per receipt owner after commit');
			$t->assertContains('deleteAttachmentFileAfterCommit($attachment)', $deleteEntryFiles, 'Entry deletion should defer physical receipt cleanup until after commit');

			$ownerPreference = $t->methodBody('lib/Controller/EntryController.php', 'ownerWantsAttachmentFileDeletedWithEntry');
			$t->assertContains('attachmentOwnerUserId($attachment)', $ownerPreference, 'Automatic file cleanup should resolve the stored file owner');
			$t->assertContains("getUserValue(\$ownerUserId, 'cobudget', 'delete_receipts_with_entry'", $ownerPreference, 'Automatic file cleanup should use the file owner setting rather than the deleting member setting');
		},

		'User settings validate values through shared helpers' => function(TestRunner $t): void {
			$userController = $t->read('lib/Controller/UserController.php');
			$t->assertContains('CURRENCY_BY_COUNTRY', $userController, 'User settings should map common locales to currencies');
			$t->assertContains("'AT' => 'EUR'", $userController, 'Austrian locale should default to EUR');
			$t->assertContains("'CH' => 'CHF'", $userController, 'Swiss locale should default to CHF');
			$t->assertContains("'US' => 'USD'", $userController, 'US locale should default to USD');

			$getSettings = $t->methodBody('lib/Controller/UserController.php', 'getSettings');
			foreach (['enable_child_related', 'enable_important_payments', 'enable_review_payments', 'enable_tax_relevant', 'enable_templates', 'enable_budget_goals', 'enable_projects', 'enable_shared_projects', 'notify_project_entries', 'notify_project_settlements', 'enable_receipts', 'receipt_storage_folder', 'receipt_folder_grouping', 'delete_receipts_with_entry'] as $setting) {
				$t->assertContains("'" . $setting . "'", $getSettings, 'Settings should expose ' . $setting);
			}
			$t->assertContains('effectiveCurrency()', $getSettings, 'Settings should expose an effective currency even before the user saves settings');

			$saveSettings = $t->methodBody('lib/Controller/UserController.php', 'saveSettings');
			$t->assertContains('validateCurrencySetting($currency)', $saveSettings, 'Settings should validate currency centrally');
			$t->assertContains('detectCurrencyFromLocale()', $saveSettings, 'Settings should auto-fill empty currency settings from the Nextcloud locale');
			$t->assertContains('validateDefaultStartPage($default_start_page)', $saveSettings, 'Settings should validate default start page centrally');
			$t->assertContains('validateEntriesPerPage($entries_per_page)', $saveSettings, 'Settings should validate entry page size centrally');
			$t->assertContains('validateReceiptStorageFolder($receipt_storage_folder)', $saveSettings, 'Settings should validate receipt storage folders');
			$t->assertContains('validateReceiptFolderGrouping($receipt_folder_grouping)', $saveSettings, 'Settings should validate receipt folder grouping');
			foreach (['enable_child_related', 'enable_important_payments', 'enable_review_payments', 'enable_tax_relevant', 'enable_templates', 'enable_budget_goals', 'enable_projects', 'enable_shared_projects', 'notify_project_entries', 'notify_project_settlements', 'enable_receipts', 'receipt_storage_folder', 'receipt_folder_grouping', 'delete_receipts_with_entry'] as $setting) {
				$t->assertContains($setting, $saveSettings, 'Settings should persist ' . $setting);
			}

			$projectCreate = $t->methodBody('lib/Controller/ProjectController.php', 'create');
			$projectAddMember = $t->methodBody('lib/Controller/ProjectController.php', 'addMember');
			$t->assertContains('sharedProjectsEnabled()', $projectCreate, 'Project creation should honor the shared areas setting');
			$t->assertContains("'share_basis_points'", $projectCreate, 'Project creation should persist member shares');
			$t->assertContains('Gemeinsame Bereiche sind deaktiviert.', $projectAddMember, 'Adding members should be blocked when shared areas are disabled');
			$t->assertContains('userSearchAllowed()', $projectAddMember, 'Adding members should honor the Nextcloud user enumeration policy');
			$t->assertNotContains("'User not found'", $projectAddMember, 'Adding members should not disclose guessed account existence');
		},

		'Shared area notifications are user-scoped and opt-out aware' => function(TestRunner $t): void {
			$entryCreate = $t->methodBody('lib/Controller/EntryController.php', 'create');
			$t->assertContains('ProjectNotificationService', $t->read('lib/Controller/EntryController.php'), 'EntryController should inject project notifications');
			$t->assertContains('notifyEntryCreated', $entryCreate, 'Entry creation should notify other shared area members');

			$settle = $t->methodBody('lib/Controller/ProjectController.php', 'settle');
			$t->assertContains('prepareSettlementNotifications', $settle, 'Settlement should prepare notifications before DB changes');
			$t->assertContains('sendPreparedNotifications', $settle, 'Settlement should notify only after the transaction commits');

			$service = $t->read('lib/Service/ProjectNotificationService.php');
			$t->assertContains('SETTING_NOTIFY_ENTRIES', $service, 'Project notification service should use an entry setting');
			$t->assertContains('SETTING_NOTIFY_SETTLEMENTS', $service, 'Project notification service should use a settlement setting');
			$t->assertContains('$recipientUserId === $actorUserId', $service, 'Project notification service should skip the acting user');
			$t->assertContains('count($members) <= 1', $service, 'Project notification service should skip solo areas');
		},

		'Project details expose dashboard metrics' => function(TestRunner $t): void {
			$show = $t->methodBody('lib/Controller/ProjectController.php', 'show');
			$t->assertContains("\$project['dashboard']", $show, 'Project detail response should include project dashboard metrics');
			$t->assertContains('calculateProjectDashboard($id, $members, $workspaceId)', $show, 'Project detail should calculate dashboard metrics with configured member shares');

			$dashboard = $t->methodBody('lib/Controller/ProjectController.php', 'calculateProjectDashboard');
			$t->assertContains('storedOrCalculatedShareCents', $dashboard, 'Project dashboard should prefer stored payment allocations');
			$t->assertContains('is_settled', $dashboard, 'Project dashboard should split open and settled entries');
			$t->assertContains('activePersonal', $t->methodBody('lib/Controller/ProjectController.php', 'normalizeProjectDashboard'), 'Project dashboard should expose personal open totals');
			$t->assertContains('allPersonal', $t->methodBody('lib/Controller/ProjectController.php', 'normalizeProjectDashboard'), 'Project dashboard should expose personal totals including settled entries');
		},

		'Flexible area shares are persisted and used by entries' => function(TestRunner $t): void {
			$routes = require $t->path('appinfo/routes.php');
			$routeNames = array_column($routes['routes'], 'url', 'name');
			$t->assertTrue(($routeNames['project#updateShares'] ?? null) === '/api/projects/{id}/shares', 'Project share update route should exist');

			$migration = $t->read('lib/Migration/Version000001Date20260713000000.php');
			$t->assertContains("'share_basis_points'", $migration, 'Flexible shares migration should add member share_basis_points');
			$t->assertContains("'split_mode'", $migration, 'Flexible shares migration should add entry/template split_mode');
			$t->assertContains("'split_user_id'", $migration, 'Flexible shares migration should add entry/template split target users');

			$trait = $t->read('lib/Controller/WorkspaceAwareTrait.php');
			$t->assertContains('normalizeSplitMode', $trait, 'Workspace trait should normalize split mode');
			$t->assertContains('validateSplitMode', $trait, 'Workspace trait should validate split mode');
			$t->assertContains('entrySplitTargetUserId', $trait, 'Workspace trait should resolve single-user split targets');
			$t->assertContains('validateProjectSplitUser', $trait, 'Workspace trait should validate single-user split targets');
			$t->assertContains('memberShareBasisPoints', $trait, 'Workspace trait should normalize member shares');
			$t->assertContains('distributeAmountCents', $trait, 'Workspace trait should distribute cents without losing remainders');
			$t->assertContains('entryShareCentsForUser', $trait, 'Workspace trait should calculate one users entry share');

				$updateShares = $t->methodBody('lib/Controller/ProjectController.php', 'updateShares');
				$t->assertContains('requireProjectOwner($id)', $updateShares, 'Project share updates should require the area creator');
				$t->assertContains('$shareBasisPoints % 100 !== 0', $updateShares, 'Project share updates should accept whole percentages only');
				$t->assertContains('array_sum($normalizedShares) !== 10000', $updateShares, 'Project share updates should require exactly 100 percent');
				$t->assertContains("update('cobudget_members')", $updateShares, 'Project share updates should write members');
				$t->assertContains("set('share_basis_points'", $updateShares, 'Project share updates should write share_basis_points');

			$entryCreate = $t->methodBody('lib/Controller/EntryController.php', 'create');
			$t->assertContains('validateSplitMode($splitMode)', $entryCreate, 'Entry create should accept and validate splitMode');
			$t->assertContains('validateProjectSplitUser($projectId, $splitMode, $splitUserId, $entryUserId)', $entryCreate, 'Entry create should validate single-user split targets');
			$t->assertContains("'split_mode' => \$qb->createNamedParameter(\$splitMode)", $entryCreate, 'Entry create should persist split mode');
			$t->assertContains("'split_user_id' => \$qb->createNamedParameter(\$splitUserId", $entryCreate, 'Entry create should persist split target');

			$entryUpdate = $t->methodBody('lib/Controller/EntryController.php', 'update');
			$t->assertContains('validateProjectSplitUser($projectId, $splitMode, $splitUserId, $entryUserId)', $entryUpdate, 'Entry update should validate single-user split targets');
			$t->assertContains("set('split_user_id'", $entryUpdate, 'Entry update should persist split target');

			$entryPersonalAmount = $t->methodBody('lib/Controller/EntryController.php', 'entryPersonalAmountCents');
			$t->assertContains("normalizeSplitMode(\$entry['split_mode']", $entryPersonalAmount, 'Dashboard personal amount should inspect split mode');
			$t->assertContains('entrySplitTargetUserId($entry)', $entryPersonalAmount, 'Dashboard personal amount should honor explicit split target user');
			$t->assertContains('$projectShareBasisPoints[$projectId]', $entryPersonalAmount, 'Dashboard personal amount should use fetched share map');

			$entryListPayload = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryListPayload');
			$t->assertContains('$projectShareBasisPoints === null', $entryListPayload, 'Entry list payload should resolve current-user project shares when not preloaded');
			$t->assertContains('$this->fetchEntryRows($workspaceId', $entryListPayload, 'Entry list payload should fetch table rows through the row helper');
			$t->assertContains('$projectShareBasisPoints);', $entryListPayload, 'Entry list payload should pass current-user shares to table rows');

			$entryRows = $t->methodBody('lib/Controller/EntryController.php', 'fetchEntryRows');
			$t->assertContains('entryPersonalAmountCents($entry, $projectShareBasisPoints)', $entryRows, 'Entry table rows should expose personal amount using split rules');
			$t->assertContains("'personal_amount_cents'", $entryRows, 'Entry table rows should include personal amount cents for the frontend');
			$t->assertContains("'personal_amount'", $entryRows, 'Entry table rows should include personal amount for the frontend');

			$templateCreate = $t->methodBody('lib/Controller/TemplateController.php', 'create');
			$t->assertContains('validateSplitMode($splitMode)', $templateCreate, 'Template create should accept and validate splitMode');
			$t->assertContains('validateProjectSplitUser($projectId, $splitMode, $splitUserId, (string)$this->userId)', $templateCreate, 'Template create should validate single-user split targets');
			$t->assertContains("'split_mode' => \$qb->createNamedParameter(\$splitMode)", $templateCreate, 'Template create should persist split mode');
			$t->assertContains("'split_user_id' => \$qb->createNamedParameter(\$splitUserId", $templateCreate, 'Template create should persist split target');

			$infoXml = $t->read('appinfo/info.xml');
			if (preg_match('/<version>([^<]+)<\/version>/', $infoXml, $versionMatch) !== 1 || $versionMatch[1] !== '0.2.0') {
				throw new \RuntimeException('The clean initial baseline should keep appinfo/info.xml at version 0.2.0');
			}
		},
	];
