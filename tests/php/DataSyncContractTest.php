<?php

declare(strict_types=1);

use CoBudget\Tests\Support\TestRunner;

return [
	'Data synchronization exposes an authenticated revision and optional custom push event' => function(TestRunner $t): void {
		$routes = require $t->path('appinfo/routes.php');
		$routeUrls = array_column($routes['routes'], 'url', 'name');
		$t->assertSame('/api/sync/state', $routeUrls['sync#state'] ?? null, 'Data synchronization should expose its revision endpoint');

		$state = $t->methodBody('lib/Controller/SyncController.php', 'state');
		$t->assertContains('authErrorResponse()', $state, 'The revision endpoint should enforce authentication and workspace-header validation');
		$t->assertContains('revisionForUser($this->userId)', $state, 'The revision endpoint should return only the current user revision');
		$t->assertContains('loggedErrorResponse($e)', $state, 'The revision endpoint should preserve JSON error responses');

		$service = $t->read('lib/Service/DataChangeService.php');
		$publish = $t->methodBody('lib/Service/DataChangeService.php', 'publishForUsers');
		$t->assertContains("bin2hex(random_bytes(16))", $publish, 'Every committed data change should receive an opaque revision');
		$t->assertContains('setUserValue($userId, self::APP_ID, self::REVISION_CONFIG_KEY, $revision)', $publish, 'Revisions should be stored per affected user');
		$t->assertContains('$queue->push(\'notify_custom\'', $publish, 'Available notify_push installations should receive a custom event');
		$t->assertContains("'message' => self::PUSH_EVENT", $publish, 'Custom push events should use the CoBudget event name');
		$t->assertContains("interface_exists('OCA\\\\NotifyPush\\\\IQueue')", $service, 'notify_push should remain an optional server dependency');
		$t->assertContains('ParticipantService::FORMER_PREFIX', $service, 'Former-member tombstones should never be treated as push recipients');
		$t->assertTrue(
			strpos($publish, 'setUserValue(') < strpos($publish, '$this->pushQueue()'),
			'The polling revision should be persisted even when notify_push is unavailable'
		);
	},

	'Committed payment, area and budget changes publish the domains consumed by open views' => function(TestRunner $t): void {
		foreach (['create', 'stopRecurrence', 'uploadAttachment', 'destroyAttachment', 'destroy'] as $method) {
			$body = $t->methodBody('lib/Controller/EntryController.php', $method);
			$t->assertContains('dataChangeService->publishForEntry(', $body, 'Entry mutation should publish a data revision: ' . $method);
		}
		$entryUpdate = $t->methodBody('lib/Controller/EntryController.php', 'update');
		$t->assertContains('dataChangeService->publishForEntryChange($entry, $updatedEntry', $entryUpdate, 'Entry moves should refresh both their previous and new scopes');
		$entryTransition = $t->methodBody('lib/Service/DataChangeService.php', 'publishForEntryChange');
		$t->assertContains('$beforeProjectId === $afterProjectId ? $afterProjectId : null', $entryTransition, 'Cross-area payment moves should not be limited to only one area view');
		$t->assertContains('$this->userIdsForEntry($before)', $entryTransition, 'Payment transitions should retain their previous recipients');
		$t->assertContains('$this->userIdsForEntry($after)', $entryTransition, 'Payment transitions should include their new recipients');

		foreach (['create', 'update', 'destroy', 'archive', 'unarchive', 'addMember', 'removeMember', 'updateShares', 'transferOwnership', 'settle'] as $method) {
			$body = $t->methodBody('lib/Controller/ProjectController.php', $method);
			$t->assertContains('dataChangeService->publishFor', $body, 'Area mutation should publish a data revision: ' . $method);
		}

		foreach (['create', 'update', 'destroy'] as $method) {
			$body = $t->methodBody('lib/Controller/BudgetController.php', $method);
			$t->assertContains("['budgets', 'analytics']", $body, 'Budget mutation should refresh budget and analytics views: ' . $method);
		}

		$recurrences = $t->methodBody('lib/Cron/RecurringEntriesJob.php', 'run');
		$t->assertContains('dataChangeService->publishForEntry(', $recurrences, 'Background recurrence creation should publish a data revision');
		$t->assertTrue(
			strpos($recurrences, '$this->db->commit()') < strpos($recurrences, '$this->dataChangeService->publishForEntry('),
			'Recurrence changes should only be announced after their transaction commits'
		);
	},

	'Frontend synchronization uses push, resume checks and bounded polling with scoped view refreshes' => function(TestRunner $t): void {
		$sync = $t->read('src/services/dataSync.js');
		$t->assertContains("listen(PUSH_EVENT, receivePush)", $sync, 'Frontend synchronization should subscribe to the custom push event');
		$t->assertContains("generateUrl('/apps/cobudget/api/sync/state')", $sync, 'Frontend synchronization should query the revision fallback endpoint');
		$t->assertContains('skipWorkspaceHeader: true', $sync, 'The user-global revision request should not inherit an active workspace header');
		$t->assertContains('window.setInterval(', $sync, 'Visible clients should poll as a fallback when push is absent or lost');
		$t->assertContains("window.addEventListener('focus', resumeSync)", $sync, 'Focused tabs should check for missed revisions');
		$t->assertContains("document.addEventListener('visibilitychange', resumeSync)", $sync, 'Resumed tabs should check for missed revisions');
		$t->assertContains('requestPushSequence !== pushSequence', $sync, 'A stale polling response should not overwrite a newer push revision');
		$t->assertContains('initialRevisionRequestFailed', $sync, 'The first successful revision check should recover from an unavailable initial baseline');
		$t->assertContains("initialRevisionRequestFailed || nextRevision !== ''", $sync, 'An existing initial revision should close the startup race with view loading');
		$t->assertContains('scheduleRefresh(change)', $t->methodBody('src/services/dataSync.js', 'receivePush'), 'Push events should refresh views without an extra request race');

		$app = $t->read('src/App.vue');
		$t->assertContains('startDataSync()', $app, 'The main app should start synchronization once');
		$t->assertTrue(strpos($app, 'startDataSync()') < strpos($app, 'async mounted()'), 'Synchronization should establish its initial revision before child views finish mounting');
		$t->assertContains('REMOTE_DATA_CHANGED_EVENT, this.refreshNavigationData', $app, 'The navigation should update after remote changes');

		foreach ([
			'src/views/TransactionsView.vue',
			'src/views/ProjectList.vue',
			'src/views/ProjectDetail.vue',
			'src/views/ProjectSettlementsView.vue',
			'src/views/BudgetGoalsView.vue',
			'src/views/AnalyticsView.vue',
		] as $view) {
			$source = $t->read($view);
			$t->assertContains('addEventListener(REMOTE_DATA_CHANGED_EVENT', $source, $view . ' should subscribe to relevant remote changes');
			$t->assertContains('removeEventListener(REMOTE_DATA_CHANGED_EVENT', $source, $view . ' should remove its remote-change listener on unmount');
		}
	},
];
