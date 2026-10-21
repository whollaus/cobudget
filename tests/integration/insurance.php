<?php
declare(strict_types=1);

// Local integration check: all fixture writes are rolled back, including on failure.
$options = getopt('', ['nextcloud:', 'user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'])) {
	fwrite(STDERR, "Usage: php tests/integration/insurance.php --nextcloud=/path/to/local/server --user=admin\n");
	exit(1);
}
$root = realpath($options['nextcloud']);
if (!$root || !is_file($root . '/lib/base.php')) throw new RuntimeException('Nextcloud installation missing');
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function(Throwable $error): void {
	fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
	exit(1);
});

use OCA\CoBudget\Controller\EntryController;
use OCP\Server;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IRequest;
use OCP\IRequestId;
use OCP\IConfig;
use OCP\IL10N;
use OCP\L10N\IFactory;

$db = Server::get(IDBConnection::class);
$session = Server::get(IUserSession::class);
$originalUser = $session->getUser();
$user = Server::get(IUserManager::class)->get($options['user']);
if (!$user) throw new RuntimeException('Existing local test user required');
$session->setUser($user);
$uid = $user->getUID();
$assertions = 0;
$check = static function(bool $condition, string $message) use (&$assertions): void {
	if (!$condition) throw new RuntimeException($message);
	$assertions++;
};
$insert = static function(string $table, array $data) use ($db): int {
	$qb = $db->getQueryBuilder();
	$qb->insert('cobudget_' . $table);
	foreach ($data as $key => $value) $qb->setValue($key, $qb->createNamedParameter($value));
	$qb->executeStatement();
	return (int)$qb->getLastInsertId();
};
$fresh = static function(int $workspaceId, string $class = EntryController::class): object {
	$request = new OC\AppFramework\Http\Request(['server' => [
		'HTTP_X_WORKSPACE_ID' => (string)$workspaceId, 'HTTP_X_COBUDGET_TIMEZONE' => 'Europe/Vienna',
	]], Server::get(IRequestId::class), Server::get(IConfig::class));
	$reflection = new ReflectionClass($class);
	return $reflection->newInstanceArgs(array_map(static function(ReflectionParameter $parameter) use ($request): mixed {
		if ($parameter->getName() === 'appName') return 'cobudget';
		$type = $parameter->getType()->getName();
		return match ($type) {
			IRequest::class => $request,
			IL10N::class => Server::get(IFactory::class)->get('cobudget', 'en'),
			default => Server::get($type),
		};
	}, $reflection->getConstructor()->getParameters()));
};
$data = static function($response): array {
	if (!in_array($response->getStatus(), [200, 201], true)) throw new RuntimeException('API failure: ' . json_encode($response->getData()));
	return $response->getData();
};

$db->beginTransaction();
try {
	$workspace = $insert('workspaces', ['name' => 'Insurance integration fixture', 'user_id' => $uid]);
	$foreign = $insert('workspaces', ['name' => 'Foreign insurance fixture', 'user_id' => '__insurance_foreign__']);
	$insert('entries', ['user_id' => '__insurance_foreign__', 'workspace_id' => $foreign, 'type' => 'expense', 'date' => time() - 60, 'amount' => '999', 'amount_cents' => 99900, 'is_insurance' => true]);
	$created = $data($fresh($workspace)->create(amount: 42.15, description: 'Insurance fixture', date: time() - 60, isInsurance: true));
	$id = (int)$created['id'];
	$data($fresh($workspace)->create(amount: 10, description: 'Unlabelled fixture', date: time() - 60));
	$data($fresh($workspace)->create(type: 'income', amount: 2.15, description: 'Insurance refund', date: time() - 60, isInsurance: true));
	$list = $data($fresh($workspace)->index(isInsurance: true));
	$check($list['total'] === 2, 'Insurance filter includes labelled payments only in the active workspace');
	$check($list['entries'][0]['is_insurance'] === true, 'Insurance is returned as a boolean');
	$plain = $data($fresh($workspace)->index(isInsurance: false));
	$check($plain['total'] === 1 && !$plain['entries'][0]['is_insurance'], 'Existing/default unlabelled payments remain false');
	$dashboard = $data($fresh($workspace)->dashboard(isInsurance: true));
	$check($dashboard['total'] === 2, 'Dashboard forwards the insurance filter');
	$check($dashboard['tagCounts']['insurance'] === 2, 'Sidebar counts both insurance payments');
	$summary = $data($fresh($workspace)->dashboard(summaryOnly: true));
	$check($summary['tagCounts']['insurance'] === 2, 'Navigation summary includes insurance');
	$csv = $fresh($workspace)->exportCsv(isInsurance: true)->render();
	$check(str_contains($csv, 'Insurance fixture') && !str_contains($csv, 'Unlabelled fixture'), 'CSV export uses the same insurance filter');
	$rows = array_map(static fn(string $line): array => str_getcsv($line, ';', '"', ''), explode("\n", trim($csv)));
	$check(count($rows[0]) === count($rows[1]), 'CSV headers and fields remain aligned');
	$data($fresh($workspace)->update(id: $id, amount: 42.15, description: 'Insurance removed', date: time() - 60, isInsurance: false));
	$check($data($fresh($workspace)->index(isInsurance: true))['total'] === 1, 'Editing can remove insurance');
	$check($data($fresh($workspace)->dashboard(summaryOnly: true))['tagCounts']['insurance'] === 1, 'Removing insurance updates navigation counts');
	// Settings only hide the label; saved financial data remains available.
	$userController = $fresh($workspace, \OCA\CoBudget\Controller\UserController::class);
	$data($userController->saveSettings(enable_insurance: false));
	$check(!$data($userController->getSettings())['enable_insurance'], 'Insurance can be disabled');
	$check($data($fresh($workspace)->index(isInsurance: true))['total'] === 1, 'Disabling does not erase stored insurance labels');
	$data($userController->saveSettings(enable_insurance: true));
	$check($data($userController->getSettings())['enable_insurance'], 'Insurance can be enabled again');
	// Exercise the real backup allowlist with current and older rows.
	$backup = Server::get(\OCA\CoBudget\Service\BackupService::class);
	$filter = new ReflectionMethod($backup, 'filterBackupRowColumns');
	$check($filter->invoke($backup, 'cobudget_entries', ['is_insurance' => true])['is_insurance'] === true, 'Backups preserve insurance');
	$check($filter->invoke($backup, 'cobudget_entries', ['id' => 1]) === ['id' => 1], 'Older backup rows without insurance are accepted');
	$session->setUser(null);
	$check($fresh($workspace)->index(isInsurance: true)->getStatus() === 401, 'Insurance filtering requires authentication');
	echo "Insurance integration passed: {$assertions} assertions; fixtures rolled back.\n";
} finally {
	$db->rollBack();
	$session->setUser($originalUser);
}
