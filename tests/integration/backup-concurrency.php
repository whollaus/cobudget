<?php

declare(strict_types=1);

// Separate PHP processes exercise the actual Nextcloud locking provider.
$options = getopt('', ['nextcloud:', 'user:', 'worker:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'])) {
	throw new RuntimeException('Use --nextcloud=/path/to/test/server --user=admin');
}
$root = realpath($options['nextcloud']);
if (!$root || !is_file($root . '/lib/base.php')) throw new RuntimeException('Nextcloud installation missing');
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR, (string)$e . "\n"); exit(1); });

use OCA\CoBudget\Exception\AppBusyException;
use OCA\CoBudget\Service\AppDataLock;
use OCA\CoBudget\Service\BackupService;
use OCA\CoBudget\Middleware\AppDataLockMiddleware;
use OCP\Server;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;

$gate = Server::get(AppDataLock::class);
if (($options['worker'] ?? '') === 'probe') {
	try { $gate->enter(true); $gate->leave(); echo 'ACQUIRED'; }
	catch (AppBusyException) { echo 'BLOCKED'; }
	exit(0);
}
if (isset($options['worker'])) {
	$gate->enter($options['worker'] === 'exclusive');
	try {
		fwrite(STDOUT, "READY\n");
		stream_set_timeout(STDIN, 30);
		fgets(STDIN); // Parent explicitly releases us; no timing-dependent sleeps.
	} finally {
		$gate->leave();
	}
	exit(0); // Native shared-lock caching ends with the process.
}

$checks = 0;
$check = static function(bool $ok, string $message) use (&$checks): void {
	if (!$ok) throw new RuntimeException($message);
	$checks++;
};
$busy = static function(Closure $operation, string $message) use ($check): void {
	try { $operation(); } catch (AppBusyException) { $check(true, $message); return; }
	throw new RuntimeException($message);
};
$worker = static function(string $mode, Closure $test) use ($root, $options): void {
	$process = proc_open([PHP_BINARY, __FILE__, '--nextcloud=' . $root, '--user=' . $options['user'], '--worker=' . $mode],
		[['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
	if (!is_resource($process)) throw new RuntimeException('Could not start lock worker');
	try {
		stream_set_timeout($pipes[1], 10);
		if (trim((string)fgets($pipes[1])) !== 'READY') throw new RuntimeException('Worker failed to acquire lock: ' . stream_get_contents($pipes[2]));
		$test();
	} finally {
		fwrite($pipes[0], "RELEASE\n");
		fclose($pipes[0]);
		fclose($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$status = proc_close($process);
		if ($status !== 0) throw new RuntimeException('Lock worker failed: ' . $error);
	}
};
$controller = new class extends Controller {
	public bool $refreshed = false;
	public function __construct() {}
	public function refreshWorkspaceContext(): void { $this->refreshed = true; }
};
$middleware = Server::get(AppDataLockMiddleware::class);
$backup = Server::get(BackupService::class);
$check(strlen(AppDataLock::key()) === 64, 'Lock key fits every supported database');

$worker('exclusive', static function() use ($gate, $busy, $backup, $options, $middleware, $controller, $check): void {
	$busy(fn() => $gate->enter(), 'Restore blocks ordinary operations');
	$busy(fn() => $gate->enter(true), 'Restore blocks another exclusive operation');
	$busy(fn() => $backup->createBackup($options['user']), 'Public backup entry point is fenced before Files writes');
	$busy(fn() => $backup->restoreFullBackup($options['user'], 'unused.zip'), 'Public restore entry point is fenced before reading an archive');
	try {
		$middleware->beforeController($controller, 'create');
		throw new RuntimeException('HTTP admission was not blocked');
	} catch (AppBusyException $e) {
		$response = $middleware->afterException($controller, 'create', $e);
		$check($response->getStatus() === 503, 'HTTP returns temporary unavailability');
		$check(($response->getData()['code'] ?? '') === 'cobudget_busy', 'HTTP has a stable busy code');
		$check(($response->getHeaders()['Retry-After'] ?? '') === '5', 'HTTP suggests retrying without replaying a write');
		$check(!$controller->refreshed, 'No controller work before admission');
	}
	foreach (['RecurringEntriesJob', 'RemindersJob', 'BudgetSnapshotJob', 'BackupJob'] as $name) {
		$class = new ReflectionClass('OCA\\CoBudget\\Cron\\' . $name);
		// Uninitialized dependencies make any accidental job execution fail immediately.
		$class->getMethod('run')->invoke($class->newInstanceWithoutConstructor(), null);
		$check(true, $name . ' defers without reading or writing data');
	}
	$listener = (new ReflectionClass(OCA\CoBudget\Listener\BeforeUserDeletedListener::class))->newInstanceWithoutConstructor();
	$busy(fn() => $listener->handle(new OCP\User\Events\BeforeUserDeletedEvent(Server::get(OCP\IUserManager::class)->get($options['user']))), 'User-deletion entry point is fenced');
});

// Exercise nesting and failure cleanup before testing cached shared locks.
$gate->enter(true);
try {
	$gate->enter(true);
	$gate->leave();
	$gate->enter();
	$gate->leave();
	$check(true, 'Safety backup and nested operations reuse the exclusive owner');
} finally { $gate->leave(); }
$gate->enter(true);
$gate->leave();
$check(true, 'Exclusive operation can restart after release');

$worker('shared', static function() use ($gate, $busy, $middleware, $controller, $check): void {
	$busy(fn() => $gate->enter(true), 'An already running operation prevents restore startup');
	$middleware->beforeController($controller, 'index');
	$check($controller->refreshed, 'Workspace ownership is checked after admission');
	$middleware->afterController($controller, 'index', new DataResponse([]));
	$check(true, 'Ordinary operations can run concurrently');
});
$middleware->beforeController($controller, 'update');
try { $middleware->afterException($controller, 'update', new RuntimeException('fixture')); }
catch (RuntimeException $e) { $check($e->getMessage() === 'fixture', 'Original controller errors survive cleanup'); }
$gate->enter(true);
$gate->leave();
$check(true, 'Controller exception releases its shared admission');

// The outer middleware and service boundary must share one lock owner.
$backupController = (new ReflectionClass(OCA\CoBudget\Controller\BackupController::class))->newInstanceWithoutConstructor();
$middleware->beforeController($backupController, 'create');
try {
	try { $backup->createFullBackup('__cobudget_absent_fixture__'); }
	catch (InvalidArgumentException) { $check(true, 'Service validation runs inside the middleware exclusive owner'); }
	$check(Server::get(OCP\Lock\ILockingProvider::class)->isLocked(AppDataLock::key(), OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE), 'Inner failure keeps the outer maintenance lock');
} finally { $middleware->afterController($backupController, 'create', new DataResponse([])); }

$deletion = new class extends OCA\CoBudget\Service\UserDeletionService {
	public function __construct() {}
	public function deleteUser(string $userId, string $displayName = ''): array { return []; }
};
$calendar = new class extends OCA\CoBudget\Service\CalendarReminderService {
	public function __construct() {}
	public function forgetUser(string $userId): void {}
};
$listener = new OCA\CoBudget\Listener\BeforeUserDeletedListener($deletion, new Psr\Log\NullLogger(), $calendar);
$user = Server::get(OCP\IUserManager::class)->get($options['user']);
$listener->handle(new OCP\User\Events\BeforeUserDeletedEvent($user));
$busy(fn() => $gate->enter(true), 'Admission remains held between before/after user deletion events');
$listener->handle(new OCP\User\Events\UserDeletedEvent($user));
$gate->enter(true); $gate->leave();
$check(true, 'Completed account deletion releases admission');

// Regression: shared -> exclusive -> shared must remain visible to another process.
$gate->enter();
try {
	$process = proc_open([PHP_BINARY, __FILE__, '--nextcloud=' . $root, '--user=' . $options['user'], '--worker=probe'],
		[['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
	fclose($pipes[0]);
	$result = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$error = stream_get_contents($pipes[2]); fclose($pipes[2]);
	$check(proc_close($process) === 0 && $result === 'BLOCKED', 'Mode transitions never leave an invisible shared lock: ' . $error);
} finally { $gate->leave(); }

$disabled = new class extends OC\AllConfig {
	public function __construct() {}
	public function getSystemValueBool(string $key, bool $default = false): bool { return false; }
};
$disabledGate = new AppDataLock(Server::get(OCP\Lock\ILockingProvider::class), $disabled);
try { $disabledGate->enter(true); throw new LogicException('Disabled locking accepted'); }
catch (RuntimeException $e) { $check(str_contains($e->getMessage(), 'filelocking.enabled'), 'Backup/restore fail closed when server locking is disabled'); }

echo "Backup concurrency: $checks assertions passed; separate processes, native locks, no financial writes or messages.\n";
