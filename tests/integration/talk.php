<?php
declare(strict_types=1);

// Run only against a disposable/local Nextcloud with CoBudget and Talk enabled.
// Uses real controllers, Talk services, delivery and permission checks.
$options = getopt('', ['nextcloud:', 'create-test-users']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['create-test-users'])) {
	fwrite(STDERR, "Usage: php tests/integration/talk.php --nextcloud=/path/to/server --create-test-users\n");
	exit(1);
}
$root = realpath($options['nextcloud']);
if (!$root || !is_file($root . '/lib/base.php')) {
	throw new RuntimeException('Nextcloud installation missing');
}
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();

use OCA\CoBudget\Controller\EntryController;
use OCA\CoBudget\Controller\ProjectController;
use OCA\CoBudget\Controller\TalkController;
use OCA\CoBudget\Service\TalkBackend;
use OCA\CoBudget\Service\TalkNotificationService;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Server;

$assertions = 0;
$check = static function (bool $condition, string $message) use (&$assertions): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	$assertions++;
};
$fresh = static function (string $class): object {
	$reflection = new ReflectionClass($class);
	return $reflection->newInstanceArgs(array_map(static function (ReflectionParameter $parameter): mixed {
		if ($parameter->getName() === 'appName') {
			return 'cobudget';
		}
		$type = $parameter->getType()->getName();
		return $type === IL10N::class ? Server::get(IFactory::class)->get('cobudget', 'en') : Server::get($type);
	}, $reflection->getConstructor()->getParameters()));
};
$users = Server::get(IUserManager::class);
$session = Server::get(IUserSession::class);
$config = Server::get(IConfig::class);
$db = Server::get(IDBConnection::class);
$talk = Server::get(TalkNotificationService::class);
$backend = Server::get(TalkBackend::class);
$manager = Server::get(Manager::class);
$accounts = [];
$tokens = [];
$entryIds = [];
$projectId = null;
$entries = $projects = null;
$messages = static function (string $token) use ($db, $manager): array {
	$room = $manager->getRoomByToken($token);
	$qb = $db->getQueryBuilder();
	$qb->select('message', 'actor_type')->from('comments')
		->where($qb->expr()->eq('object_type', $qb->createNamedParameter('chat')))
		->andWhere($qb->expr()->eq('object_id', $qb->createNamedParameter((string)$room->getId())))
		->andWhere($qb->expr()->eq('verb', $qb->createNamedParameter('comment')))
		->orderBy('id', 'ASC');
	$result = $qb->executeQuery();
	$rows = $result->fetchAll();
	$result->closeCursor();
	return $rows;
};

try {
	foreach (['actor', 'recipient'] as $role) {
		$uid = 'cb-talk-test-' . $role . '-' . bin2hex(random_bytes(4));
		$accounts[$role] = $users->createUser($uid, bin2hex(random_bytes(24)) . '!aA9');
		$check($accounts[$role] !== false, 'Fixture user creation failed');
		$config->setUserValue($uid, 'core', 'lang', 'en');
	}
	$actor = $accounts['actor']->getUID();
	$recipient = $accounts['recipient']->getUID();
	$session->setUser($accounts['recipient']);
	$controller = $fresh(TalkController::class);
	$check($controller->state()->getData()['enabled'] === false, 'Talk must default to off');
	foreach ([$actor, $recipient] as $uid) {
		$state = $talk->setEnabled($uid, true);
		$check($state['available'] && $state['ready'] && $state['enabled'], 'Opt-in must create a private destination');
		$tokens[$uid] = $config->getUserValue($uid, 'cobudget', TalkNotificationService::TOKEN);
		$room = $manager->getRoomByToken($tokens[$uid]);
		$check($room->getReadOnly() === Room::READ_ONLY && $room->getListable() === 0, 'Conversation must be read-only and unlisted');
	}
	$talk->setEnabled($recipient, true);
	$check($tokens[$recipient] === $config->getUserValue($recipient, 'cobudget', TalkNotificationService::TOKEN), 'Repeated opt-in must reuse the personal chat');
	$session->setUser($accounts['actor']);
	$projects = $fresh(ProjectController::class);
	$response = $projects->create('Talk integration test', [$recipient]);
	$check($response->getStatus() < 300, 'Shared-area creation failed: ' . json_encode($response->getData()));
	$projectId = (int)$response->getData()['id'];
	$entries = $fresh(EntryController::class);
	$create = static function () use ($entries, $projectId, &$entryIds, $check): void {
		$response = $entries->create(amount: 12.34, description: 'SECRET DESCRIPTION @all', projectId: $projectId);
		$check($response->getStatus() < 300, 'Payment must save regardless of notification status: ' . json_encode($response->getData()));
		$entryIds[] = (int)$response->getData()['id'];
	};
	$config->setUserValue($recipient, 'cobudget', 'notify_project_entries', 'no');
	$create();
	$rows = $messages($tokens[$recipient]);
	$check(count($rows) === 1, 'Talk must send even with bell notifications disabled');
	$check($rows[0]['actor_type'] === Attendee::ACTOR_BOTS, 'Messages must be attributed to the bot');
	$check(str_contains($rows[0]['message'], '#/projects/' . $projectId), 'Message must link to the area');
	$check(!str_contains($rows[0]['message'], 'SECRET DESCRIPTION'), 'Descriptions must not leak into Talk');
	$check(count($messages($tokens[$actor])) === 0, 'The actor must not receive their own payment notification');

	$talk->setEnabled($recipient, false);
	$config->setUserValue($recipient, 'cobudget', 'notify_project_entries', 'yes');
	$create();
	$check(count($messages($tokens[$recipient])) === 1, 'Opt-out must stop Talk while retaining history');
	$talk->setEnabled($recipient, true);
	$config->setUserValue($recipient, 'cobudget', TalkNotificationService::TOKEN, $tokens[$actor]);
	$check(!$talk->state($recipient)['ready'], 'Another account’s room must not become a valid destination');
	$create();
	$check(count($messages($tokens[$actor])) === 0, 'Foreign token must never receive financial details');
	$config->setUserValue($recipient, 'cobudget', TalkNotificationService::TOKEN, $tokens[$recipient]);

	$room = $manager->getRoomByToken($tokens[$recipient]);
	Server::get(ParticipantService::class)->addUsers($room, [[
		'actorType' => Attendee::ACTOR_USERS, 'actorId' => $actor, 'participantType' => Participant::USER,
	]]);
	$check(!$backend->ready($tokens[$recipient], $recipient), 'Extra participants must invalidate personal delivery');
	$create();
	$check(count($messages($tokens[$recipient])) === 1, 'Changed room must not receive any further payment message');
	$talk->setEnabled($recipient, true);
	$newToken = $config->getUserValue($recipient, 'cobudget', TalkNotificationService::TOKEN);
	$check($newToken !== $tokens[$recipient] && $backend->ready($newToken, $recipient), 'Explicit reconnect must create a new private chat');
	$tokens['replacement'] = $newToken;
	$create();
	$check(count($messages($newToken)) === 1, 'Delivery must resume after explicit reconnect');
	Server::get(RoomService::class)->deleteRoom($manager->getRoomByToken($newToken));
	unset($tokens['replacement']);
	$check(!$talk->state($recipient)['ready'], 'Deleted chats must require explicit reconnect');
	$create();
	$check($config->getUserValue($recipient, 'cobudget', TalkNotificationService::TOKEN) === $newToken, 'Payment creation must not silently recreate a chat');

	$session->setUser(null);
	$check($controller->state()->getStatus() === 401 && $controller->update(true)->getStatus() === 401, 'Talk endpoints must reject unauthenticated requests');
	echo "Talk integration passed: {$assertions} assertions.\n";
} finally {
	if (isset($accounts['actor']) && $accounts['actor']) {
		$session->setUser($accounts['actor']);
	}
	foreach ($entryIds as $id) {
		$entries->destroy($id);
	}
	if ($projectId !== null) {
		$projects->destroy($projectId);
	}
	foreach ($tokens as $token) {
		try {
			Server::get(RoomService::class)->deleteRoom($manager->getRoomByToken($token));
		} catch (OCA\Talk\Exceptions\RoomNotFoundException $e) {
		}
	}
	$session->setUser(null);
	foreach ($accounts as $account) {
		if ($account) {
			$account->delete();
		}
	}
}
