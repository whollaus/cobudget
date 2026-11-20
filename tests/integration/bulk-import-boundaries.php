<?php
declare(strict_types=1);

use OCA\CoBudget\AppInfo\Application;
use OCA\CoBudget\Controller\EntryController;
use OCA\CoBudget\Controller\UserController;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;

$options = getopt('', ['nextcloud:', 'user:']);
$root = realpath($options['nextcloud'] ?? '');
if (PHP_SAPI !== 'cli' || !isset($options['user']) || !$root || !is_file($root . '/lib/base.php')) {
	throw new RuntimeException('Pass --nextcloud=<test server> and --user=<test user>.');
}
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['HTTP_X_COBUDGET_TIMEZONE'] = 'UTC';
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR, (string)$e . "\n"); exit(1); });

$db = Server::get(IDBConnection::class);
$config = Server::get(IConfig::class);
$session = Server::get(IUserSession::class);
$user = Server::get(IUserManager::class)->get($options['user']);
if (!$user) throw new RuntimeException('Existing test user required.');
$previousUser = $session->getUser();
$check = static function(bool $condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
};
$db->beginTransaction();
try {
	$session->setUser($user);
	$uid = $user->getUID();
	$config->deleteUserValue($uid, 'cobudget', 'currency');
	$config->setUserValue($uid, 'core', 'locale', 'de_CH');
	$config->setUserValue($uid, 'cobudget', 'enable_future_payments', 'yes');
	$app = (new Application())->getContainer();
	$settings = $app->get(UserController::class)->getSettings();
	$check(($settings->getData()['currency'] ?? null) === 'CHF', 'Payment form should default to CHF.');
	$controller = $app->get(EntryController::class);
	$id = bin2hex(random_bytes(32));
	$row = ['date' => '2038-01-18', 'amount' => -12.50, 'description' => $id];
	$preview = $controller->bulkImport(null, [$row], true, $id);
	$check($preview->getStatus() === 200 && $preview->getData()['summary']['currency'] === 'CHF', 'Expected valid CHF preview.');
	$scope = $preview->getData()['scope'];
	$later = ['date' => '2038-01-19', 'amount' => -1, 'description' => $id];
	$stored = static function() use ($db, $uid, $id): array {
		$q = $db->getQueryBuilder();
		$q->select('date', 'currency')->from('cobudget_entries')
			->where($q->expr()->eq('description', $q->createNamedParameter($id)))
			->andWhere($q->expr()->eq('user_id', $q->createNamedParameter($uid)));
		return $q->executeQuery()->fetchAll();
	};
	foreach ([true, false] as $dryRun) {
		$invalid = $controller->bulkImport(null, [$row, $later], $dryRun, $id, 50, $scope);
		$message = $invalid->getData()['errors'][0] ?? '';
		$check($invalid->getStatus() === 400 && str_contains($message, '52') && str_contains($message, '2038-01-19'), 'Expected a date error for file row 52.');
	}
	$check($stored() === [], 'Invalid packet must leave the database untouched.');
	$commit = $controller->bulkImport(null, [$row], false, $id, 0, $scope);
	$check($commit->getStatus() === 200 && $commit->getData()['importedCount'] === 1, 'Expected one committed payment.');
	$entries = $stored();
	$expected = (new DateTimeImmutable('2038-01-18 12:00:00 UTC'))->getTimestamp();
	$check(count($entries) === 1 && (int)$entries[0]['date'] === $expected && $entries[0]['currency'] === 'CHF', 'Stored timestamp and currency must match the preview.');
	echo "Bulk import passed on " . $config->getSystemValue('dbtype') . ": CHF, UTC 2038-01-18 stored, 2038-01-19 rejected in row 52.\n";
} finally {
	$db->rollBack();
	$session->setUser($previousUser);
}
