<?php
declare(strict_types=1);

use OCA\CoBudget\Controller\AutoImportController;
use OCA\CoBudget\Service\AutoImportRules;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequestId;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Server;

$options = getopt('', ['nextcloud:', 'user:', 'race:', 'phase:']);
$root = realpath($options['nextcloud'] ?? '');
if (PHP_SAPI !== 'cli' || !$root || !is_file(dirname($root) . '/test-instance.json')) {
	throw new RuntimeException('Use the disposable Nextcloud upgrade test installation.');
}
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function (Throwable $e): void { fwrite(STDERR, (string)$e . "\n"); exit(1); });

$db = Server::get(IDBConnection::class);
$config = Server::get(IConfig::class);
$session = Server::get(IUserSession::class);
$users = Server::get(IUserManager::class);
$owner = $users->get($options['user']);
$member = $users->get('upgrade-member');
$previousUser = $session->getUser();
$rules = Server::get(AutoImportRules::class);
$controller = static function ($user, int $workspace) use ($session, $db, $config, $rules): AutoImportController {
	$session->setUser($user);
	$request = new OC\AppFramework\Http\Request(['server' => ['HTTP_X_WORKSPACE_ID' => (string)$workspace]], Server::get(IRequestId::class), $config);
	return new AutoImportController('cobudget', $request, $session, $db, $config, $rules, Server::get(IFactory::class)->get('cobudget', 'en'), Server::get(\OCA\CoBudget\Service\AutoImportRunner::class));
};
$insert = static function (string $table, array $values) use ($db): int {
	$q = $db->getQueryBuilder();
	$q->insert('cobudget_' . $table);
	foreach ($values as $key => $value) {
		$q->setValue($key, $q->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
	}
	$q->executeStatement();
	return $q->getLastInsertId();
};
$assert = static function (bool $condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
};
$response = static function ($value, int $code) use ($assert) {
	$assert($value->getStatus() === $code, 'Expected ' . $code . ', got ' . $value->getStatus() . ' ' . json_encode($value->getData()));
	return $value->getData();
};
$session->setUser($member);
$home = Server::get(IRootFolder::class)->getUserFolder($member->getUID());
if (isset($options['race'])) {
	$config->setUserValue($member->getUID(), 'cobudget', 'enable_payment_imports', 'yes');
	require __DIR__ . '/auto-import-folder-race.php';
	return;
}
$folderName = 'AutoImport-' . bin2hex(random_bytes(8));
$inbox = $home->newFolder($folderName);
$db->beginTransaction();
try {
	$ow = $insert('workspaces', ['name' => 'Automatic import owner', 'user_id' => $owner->getUID()]);
	$mw = $insert('workspaces', ['name' => 'Automatic import member', 'user_id' => $member->getUID()]);
	$other = $insert('workspaces', ['name' => 'Another member workspace', 'user_id' => $member->getUID()]);
	$area = $insert('projects', ['name' => 'Joint import', 'owner_id' => $owner->getUID(), 'workspace_id' => $ow]);
	$insert('members', ['project_id' => $area, 'user_id' => $owner->getUID(), 'personal_workspace_id' => $ow, 'share_basis_points' => 6000]);
	$insert('members', ['project_id' => $area, 'user_id' => $member->getUID(), 'personal_workspace_id' => $mw, 'share_basis_points' => 4000]);
	$config->setUserValue($member->getUID(), 'cobudget', 'enable_projects', 'yes');

	$settings = static function ($user, ?bool $paymentImports = null) use ($session, $response): array {
		$session->setUser($user);
		$controller = new \OCA\CoBudget\Controller\UserController('cobudget', Server::get(\OCP\IRequest::class),
			Server::get(IDBConnection::class), Server::get(IUserManager::class), $session, Server::get(IConfig::class),
			Server::get(\OCA\CoBudget\Service\BackupService::class), Server::get(\OCA\CoBudget\Service\UserResetService::class),
			Server::get(IFactory::class)->get('cobudget', 'en'));
		if ($paymentImports !== null) $response($controller->saveSettings('EUR', enable_payment_imports: $paymentImports), 200);
		return $response($controller->getSettings(), 200);
	};
	$api = $controller($member, $mw);
	$response($api->index(), 403);
	$assert(!$settings($member)['enable_payment_imports'], 'Payment imports default to off.');
	$assert($settings($member, true)['enable_payment_imports'] && !$settings($owner)['enable_payment_imports'], 'Opt-in applies only to this account.');
	$session->setUser($member);
	$config->setUserValue($owner->getUID(), 'cobudget', 'enable_payment_imports', 'yes');
	$preferences = $response($api->create('', 15, 'all', false, 'Europe/Vienna', $area), 201);
	$assert(!$preferences['enabled'] && $preferences['folder'] === '' && $preferences['interval_minutes'] === 15, 'Save area preferences before a folder is chosen.');
	$response($api->update($preferences['id'], '', 15, 'all', true, 'Europe/Vienna', $area), 400);
	$response($api->destroy($preferences['id']), 204);
	$row = $response($api->create('/' . $folderName, 5, 'error', true, 'Europe/Vienna', $area), 201);
	$id = $row['id'];
	$assert($row['user_id'] === $member->getUID() && $row['project_id'] === $area && $row['workspace_id'] === $mw, 'A member creates a shared-area rule in their own workspace.');
	$assert($row['folder_id'] === (int)$inbox->getId() && $row['folder'] === $folderName && $row['timezone'] === 'Europe/Vienna', 'Saved folder and timezone must match the selected inputs.');
	$assert($response($api->index(), 200) === [$row], 'Owner lists the new rule.');
	$assert($settings($member)['has_active_automatic_imports'], 'Account settings expose an active import before the folder UI loads.');
	$assert(!$response($api->index(true), 200)['activeElsewhere'], 'Current workspace is excluded from other-workspace activity.');
	$assert($response($controller($member, $other)->index(true), 200)['activeElsewhere'], 'Personal channels also appear for active imports in another workspace.');
	$assert(!$response($controller($owner, $ow)->index(true), 200)['activeElsewhere'], 'Another member’s active rule never enables personal channels.');
	$api = $controller($member, $mw);
	$response($api->create($folderName, 60, 'none', true, 'UTC'), 409);
	$response($api->create('../' . $folderName, 60, 'none', true, 'UTC'), 400);
	$response($api->create($folderName . '/Verarbeitet', 60, 'none', true, 'UTC'), 400);
	$response($api->create($folderName, 10, 'none', false, 'UTC'), 400);

	$setArchived = static function (bool $archived) use ($db, $area): void {
		$q = $db->getQueryBuilder();
		$q->update('cobudget_projects')->set('is_archived', $q->createNamedParameter($archived, IQueryBuilder::PARAM_BOOL))
			->where($q->expr()->eq('id', $q->createNamedParameter($area, IQueryBuilder::PARAM_INT)))->executeStatement();
	};
	$setArchived(true);
	$assert($response($api->index(), 200) === [] && !$settings($member)['has_active_automatic_imports'], 'An archived rule is hidden and does not count as active.');
	$assert(!$response($controller($member, $other)->index(true), 200)['activeElsewhere'], 'Archived rules in another workspace do not enable notifications.');
	$api = $controller($member, $mw);
	$response($api->runNow($id), 404);
	$response($api->update($id, $folderName, 5, 'all', false, 'UTC', $area), 404);
	$response($api->create('', 15, 'all', false, 'UTC', $area), 409);
	$inbox->newFile('archived.json', '{"payments": []}');
	$assert(!in_array($id, array_column($rules->due(time() + 3600), 'id'), true)
		&& Server::get(\OCA\CoBudget\Service\AutoImportRunner::class)->runRule($id) === 'skipped'
		&& $inbox->nodeExists('archived.json') && !$inbox->nodeExists('Fehler'), 'Even a previously queued rule must leave the archived inbox untouched.');
	$setArchived(false);
	$assert($response($api->index(), 200) === [$row] && in_array($id, array_column($rules->due(time() + 3600), 'id'), true),
		'Restoring the area restores the original rule, folder and schedule.');

	$foreign = $controller($owner, $ow);
	$assert($response($foreign->index(), 200) === [], 'Area admin may only list their own rules.');
	$response($foreign->update($id, $folderName, 15, 'all', false, 'UTC'), 404);
	$response($foreign->destroy($id), 404);
	$response($foreign->runNow($id), 404);
	$api = $controller($member, $other);
	$response($api->create($folderName, 15, 'none', false, 'UTC', $area), 403);
	$response($api->destroy($id), 404);
	$response($api->runNow($id), 404);
	$response($controller($member, $ow)->index(), 403);

	$api = $controller($member, $mw);
	$row = $response($api->update($id, $folderName, 1440, 'all', false, 'UTC', $area), 200);
	$response($api->runNow($id), 409);
	$assert(!$settings($member)['has_active_automatic_imports'] && !$settings($owner)['has_active_automatic_imports'], 'Paused and other users’ rules do not enable personal channels.');
	$assert(!$response($controller($member, $other)->index(true), 200)['activeElsewhere'], 'Pausing the last import clears activity across workspaces.');
	$api = $controller($member, $mw);
	$assert(!$row['enabled'] && $row['notify_mode'] === 'all' && $row['interval_minutes'] === 1440, 'Paused rule saves its individual interval and notification mode.');
	$response($api->create($folderName, 15, 'none', true, 'UTC'), 409);
	$response($api->create($folderName, 15, 'none', false, 'UTC'), 409);
	$second = $response($api->create('', 15, 'none', false, 'UTC'), 201);
	$response($api->update($second['id'], $folderName, 15, 'none', false, 'UTC'), 409);
	$response($api->destroy($second['id']), 204);
	$response($api->update($id, $folderName, 5, 'all', true, 'UTC', $area), 200);

	$q = $db->getQueryBuilder();
	$q->delete('cobudget_members')
		->where($q->expr()->eq('project_id', $q->createNamedParameter($area, IQueryBuilder::PARAM_INT)))
		->andWhere($q->expr()->eq('user_id', $q->createNamedParameter($member->getUID())))
		->executeStatement();
	$response($api->runNow($id), 403);
	$response($api->update($id, $folderName, 5, 'error', false, 'UTC', $area), 200);
	$response($api->update($id, $folderName, 5, 'error', true, 'UTC', $area), 403);
	$response($api->destroy($id), 204);
	$assert($response($api->index(), 200) === [], 'Removed rule is no longer listed.');
	$reused = $response($api->create($folderName, 15, 'none', false, 'UTC'), 201);
	// Before the reservation upgrade, a paused rule could point to the same node as an active rule.
	$legacy = $response($api->create('', 60, 'error', false, 'UTC'), 201);
	$q = $db->getQueryBuilder();
	$q->update('cobudget_import_rules')
		->set('folder', $q->createNamedParameter($folderName))
		->set('folder_id', $q->createNamedParameter($inbox->getId(), IQueryBuilder::PARAM_INT))
		->where($q->expr()->eq('id', $q->createNamedParameter($legacy['id'], IQueryBuilder::PARAM_INT)))->executeStatement();
	AutoImportRules::reservePausedFolders($db);
	$rows = array_column($response($api->index(), 200), null, 'id');
	$assert($rows[$reused['id']]['folder'] === $folderName && $rows[$legacy['id']]['folder'] === '', 'Upgrade retains the reserved folder and detaches the conflicting assignment.');
	$response($api->destroy($legacy['id']), 204);
	// Old backups restore unreserved paused folders. Claim their nodes before leaving the restore transaction.
	$q = $db->getQueryBuilder();
	$q->update('cobudget_import_rules')->set('active_folder_id', $q->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
		->where($q->expr()->eq('id', $q->createNamedParameter($reused['id'], IQueryBuilder::PARAM_INT)))->executeStatement();
	AutoImportRules::reservePausedFolders($db);
	$response($api->create($folderName, 15, 'none', false, 'UTC'), 409);
	$response($api->destroy($reused['id']), 204);
	echo "Automatic import rules passed: shared membership, folder reservation, personal rule isolation, update, pause and deletion.\n";
} finally {
	$db->rollBack();
	try {
		$session->setUser($member);
		$inbox->delete();
	} finally {
		$session->setUser($previousUser);
	}
}
