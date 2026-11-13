<?php

declare(strict_types=1);

// Included only after upgrade-fixture.php has verified the disposable installation.
function seedUpgradeSharedData(Closure $insert, string $uid, int $workspace): array {
	$users = \OCP\Server::get(\OCP\IUserManager::class);
	$workspaces = [$uid => $workspace];
	foreach (['upgrade-member', 'upgrade-departed', 'upgrade-renamed'] as $id) {
		$users->createUser($id, bin2hex(random_bytes(24)));
		if ($id !== 'upgrade-renamed') $workspaces[$id] = $insert('workspaces', ['user_id' => $id, 'name' => $id, 'is_default' => true]);
	}
	$session = \OCP\Server::get(\OCP\IUserSession::class);
	$session->setUser($users->get($uid));
	$notifications = new class extends \OCA\CoBudget\Service\ProjectNotificationService {
		public function __construct() {}
		public function notifyEntryCreated(int $projectId, int $workspaceId, int $entryId, string $actorUserId, string $entryUserId, string $type, int $amountCents, string $currency, string $description = ''): void {}
		public function prepareSettlementNotifications(int $projectId, int $workspaceId, string $actorUserId): array { return []; }
		public function sendPreparedNotifications(array $payloads): void {}
	};
	$changes = \OCP\Server::get(\OCA\CoBudget\Service\DataChangeService::class);
	(new ReflectionProperty($changes, 'calendarReminders'))->setValue($changes, new class extends \OCA\CoBudget\Service\CalendarReminderService {
		public function __construct() {}
		public function syncUser(string $userId): void {}
	});
	(new ReflectionProperty($changes, 'pushQueueResolved'))->setValue($changes, true);
	(new ReflectionProperty($changes, 'pushQueue'))->setValue($changes, null);
	$fresh = static function(string $class) use ($workspace, $notifications): object {
		$request = new \OC\AppFramework\Http\Request(['server' => ['HTTP_X_WORKSPACE_ID' => (string)$workspace]], \OCP\Server::get(\OCP\IRequestId::class), \OCP\Server::get(\OCP\IConfig::class));
		$r = new ReflectionClass($class);
		return $r->newInstanceArgs(array_map(static function(ReflectionParameter $p) use ($request, $notifications): mixed {
			if ($p->getName() === 'appName') return 'cobudget';
			return match ($p->getType()->getName()) {
				\OCP\IRequest::class => $request,
				\OCA\CoBudget\Service\ProjectNotificationService::class => $notifications,
				\OCP\IL10N::class => \OCP\Server::get(\OCP\L10N\IFactory::class)->get('cobudget', 'en'),
				default => \OCP\Server::get($p->getType()->getName()),
			};
		}, $r->getConstructor()->getParameters()));
	};
	$data = static function($response): array {
		if (!in_array($response->getStatus(), [200, 201], true)) throw new RuntimeException('Fixture API failed: ' . json_encode($response->getData()));
		return $response->getData();
	};
	$projects = [];
	foreach (['Settled holiday', 'Open household'] as $name) {
		$project = $insert('projects', ['owner_id' => $uid, 'workspace_id' => $workspace, 'name' => $name, 'created_at' => time()]);
		foreach ($workspaces as $id => $ws) $insert('members', ['project_id' => $project, 'user_id' => $id, 'personal_workspace_id' => $ws, 'share_basis_points' => $id === $uid ? 3334 : 3333]);
		$data($fresh(\OCA\CoBudget\Controller\EntryController::class)->create(amount: 100.01, description: $name . ' ä €', date: time() - 86400, projectId: $project, userId: 'upgrade-departed', splitMode: 'project_shares'));
		if ($name === 'Settled holiday') $data($fresh(\OCA\CoBudget\Controller\ProjectController::class)->settle($project));
		$projects[] = $project;
	}
	$data($fresh(\OCA\CoBudget\Controller\EntryController::class)->create(amount: 12.37, description: 'Monthly shared subscription', date: time() - 3600, projectId: $projects[1], recurrenceInterval: 'month', recurrenceMultiplier: 1, recurrenceNextDate: time() + 86400 * 30, splitMode: 'project_shares'));
	// Exercise the released app's actual account-deletion preservation path.
	if (!$users->get('upgrade-departed')->delete()) throw new RuntimeException('Fixture account deletion failed');
	\OCP\Server::get(\OCP\IConfig::class)->setUserValue('upgrade-member', 'cobudget', 'currency', 'USD');
	\OCP\Server::get(\OCA\CoBudget\Service\DataIntegrityService::class)->assertProjectionIntegrity();
	return $projects;
}
