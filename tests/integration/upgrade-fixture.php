<?php

declare(strict_types=1);

// Persistent fixture ONLY for an isolated temporary upgrade installation.
$options = getopt('', ['nextcloud:', 'phase:', 'expected-lock-provider:']);
$root = realpath($options['nextcloud'] ?? '');
if (PHP_SAPI !== 'cli' || !$root || !str_starts_with(basename(dirname($root)), 'cobudget-upgrade-') || !in_array($options['phase'] ?? '', ['seed', 'verify', 'rollback', 'prepare-crash', 'crash-restore', 'check-crash', 'recover-crash'], true)) {
	throw new RuntimeException('Use --nextcloud=<temporary cobudget-upgrade-*/server> --phase=seed|verify|rollback|prepare-crash|crash-restore|check-crash|recover-crash');
}
$base = dirname($root);
$markerPath = $base . '/test-instance.json';
if (!is_file($markerPath)) throw new RuntimeException('Use run-upgrade.py to create a disposable fixture');
$marker = json_decode(file_get_contents($markerPath), true, flags: JSON_THROW_ON_ERROR);
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR, (string)$e . "\n"); exit(1); });
use OCP\Server;
use OCA\CoBudget\Service\BackupService;

$db = Server::get(OCP\IDBConnection::class);
$config = Server::get(OCP\IConfig::class);
$uid = 'upgrade-admin';
if (($marker['data'] ?? '') !== realpath($config->getSystemValue('datadirectory'))
	|| ($marker['database'] === 'sqlite' ? 'sqlite3' : $marker['database']) !== $config->getSystemValue('dbtype')
	|| ($marker['database'] !== 'sqlite' && ($marker['dbname'] ?? '') !== $config->getSystemValue('dbname'))) {
	throw new RuntimeException('Fixture marker does not match the isolated database/data directory');
}
if (!str_starts_with(realpath($config->getSystemValue('datadirectory')), $base . '/') || !Server::get(OCP\IUserManager::class)->userExists($uid)) {
	throw new RuntimeException('This is not the isolated upgrade fixture');
}
$insert = static function(string $table, array $row) use ($db): int {
	$q = $db->getQueryBuilder(); $q->insert('cobudget_' . $table);
	foreach ($row as $k => $v) $q->setValue($k, $q->createNamedParameter($v, $v === null ? PDO::PARAM_NULL : (is_bool($v) ? PDO::PARAM_BOOL : (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR))));
	$q->executeStatement(); return isset($row['id']) ? (int)$row['id'] : (int)$q->getLastInsertId();
};
$rows = static function(string $table) use ($db): array {
	$q = $db->getQueryBuilder();$q->select('*')->from('cobudget_' . $table)->orderBy('id');
	$r = $q->executeQuery();try { return $r->fetchAll(); } finally { $r->closeCursor(); }
};
if (isset($options['expected-lock-provider'])) {
	$provider = Server::get(OCP\Lock\ILockingProvider::class);
	$expected = match ($options['expected-lock-provider']) {
		'database' => OC\Lock\DBLockingProvider::class,
		'redis' => OC\Lock\MemcacheLockingProvider::class,
		default => throw new RuntimeException('Unknown expected lock provider'),
	};
	if (!$provider instanceof $expected || ($options['expected-lock-provider'] === 'redis' && ltrim($config->getSystemValue('memcache.locking', ''), '\\') !== OC\Memcache\Redis::class)) {
		throw new RuntimeException('Configured lock provider does not match the requested test');
	}
	echo 'Verified lock provider: ' . $provider::class . "\n";
}
$backup = Server::get(BackupService::class);
$graphTables = ['entries', 'workspaces', 'projects', 'members', 'entry_shares', 'entry_history', 'settlements', 'settlement_balances', 'settlement_transfers', 'deleted_users'];
$graph = static function() use ($rows, $graphTables): array {
	$result = []; foreach ($graphTables as $table) $result[$table] = $rows($table); return $result;
};
if ($options['phase'] === 'seed') {
	if ($config->getAppValue('cobudget', 'installed_version') !== '0.8.5' || $rows('entries') !== []) throw new RuntimeException('Seed requires a fresh 0.8.5 installation');
	$workspace = $insert('workspaces', ['user_id' => $uid, 'name' => 'Upgrade household', 'is_default' => 1]);
	$entry = $insert('entries', ['id' => 5000, 'user_id' => $uid, 'created_by' => $uid, 'workspace_id' => $workspace, 'entry_kind' => 'personal', 'type' => 'expense', 'amount' => '12.34', 'amount_cents' => 1234, 'currency' => 'EUR', 'description' => 'Upgrade ä €', 'date' => time() - 3600]);
	$insert('reminders', ['user_id' => $uid, 'title' => 'Already delivered', 'remind_at' => time() - 3600, 'notified' => 1, 'labels' => '[]']);
	$goal = $insert('budget_goals', ['user_id' => $uid, 'workspace_id' => $workspace, 'name' => 'Deleted upgrade goal', 'amount_cents' => 1499]);
	$insert('budget_snapshots', ['user_id' => $uid, 'workspace_id' => $workspace, 'budget_goal_id' => $goal, 'goal_name' => 'Deleted upgrade goal', 'amount_cents' => 1499, 'spent_cents' => 1234, 'snapshot_reason' => 'deleted']);
	$q = $db->getQueryBuilder();$q->delete('cobudget_budget_goals')->where($q->expr()->eq('id', $q->createNamedParameter($goal)))->executeStatement();
	require __DIR__ . '/upgrade-shared-data.php';
	seedUpgradeSharedData($insert, $uid, $workspace);
	$config->setUserValue($uid, 'cobudget', 'currency', 'EUR');
	$archive = $backup->createFullBackup($uid, 'CoBudgetUpgrade', 10);
	file_put_contents($base . '/fixture.json', json_encode(['entry' => $entry, 'archive' => $archive, 'entries' => $rows('entries'), 'graph' => $graph()], JSON_THROW_ON_ERROR));
	echo "Seeded released 0.8.5: shared splits, settlement, deleted participant, recurrence, delivered reminder, retained goal history and real Files archive.\n";
	exit(0);
}

$checks = 0;
$check = static function(bool $ok, string $why) use (&$checks): void { if (!$ok) throw new RuntimeException($why);$checks++; };
if ($options['phase'] === 'rollback') {
	$fixture = json_decode(file_get_contents($base . '/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
	$check($config->getAppValue('cobudget', 'installed_version') === '0.8.5', 'Recovery restored the old installed version');
	$check($graph() === $fixture['graph'], 'Recovery restored the entire original financial graph');
	$check(!array_key_exists('email_notified', $rows('reminders')[0]), 'Recovery restored the pre-upgrade schema');
	$file = Server::get(OCP\Files\IRootFolder::class)->getUserFolder($uid)->get($fixture['archive']['file_path']);
	$check($file->getSize() === $fixture['archive']['file_size'], 'Original archive is still accessible after database/config recovery');
	echo "Released-beta recovery: $checks assertions passed from the saved database/configuration and matching old app code.\n";
	exit(0);
}
$fixture = json_decode(file_get_contents($base . '/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$collect = static fn(): array => (new ReflectionMethod($backup, 'collectFullBackupData'))->invoke($backup);
if ($options['phase'] === 'prepare-crash') {
	$archive = $backup->createFullBackup($uid, 'CoBudgetCrash', 10);
	$q = $db->getQueryBuilder();
	$q->update('cobudget_entries')->set('description', $q->createNamedParameter('Committed before interrupted restore'))->where($q->expr()->eq('id', $q->createNamedParameter($fixture['entry'])))->executeStatement();
	$config->setUserValue($uid, 'cobudget', 'currency', 'CHF');
	$insert('entries', ['user_id' => $uid, 'workspace_id' => $fixture['entries'][0]['workspace_id'], 'type' => 'expense', 'amount' => '0.02', 'amount_cents' => 2, 'description' => 'Newer than backup', 'date' => time()]);
	file_put_contents($base . '/crash.json', json_encode(['archive' => $archive, 'before' => $collect()], JSON_THROW_ON_ERROR));
	echo "Prepared distinct committed data/settings for process-kill restore.\n"; exit(0);
}
if ($options['phase'] === 'crash-restore') {
	$crash = json_decode(file_get_contents($base . '/crash.json'), true, flags: JSON_THROW_ON_ERROR);
	(new ReflectionProperty($backup, 'dataIntegrityService'))->setValue($backup, new class extends OCA\CoBudget\Service\DataIntegrityService {
		public function __construct() {}
		public function assertProjectionIntegrity(): void {
			// Called by the real restore after replacement inserts/settings, before commit.
			fwrite(STDOUT, "COBUDGET_FAULT_READY\n"); fflush(STDOUT);
			fgets(STDIN); throw new RuntimeException('Parent must kill this process at the barrier');
		}
	});
	$backup->restoreFullBackup($uid, $crash['archive']['file_name'], 'CoBudgetCrash');
	throw new RuntimeException('Restore did not reach the fault barrier');
}
if (in_array($options['phase'], ['check-crash', 'recover-crash'], true)) {
	$crash = json_decode(file_get_contents($base . '/crash.json'), true, flags: JSON_THROW_ON_ERROR);
	$check($collect() === $crash['before'], 'SIGKILL rolls back all financial tables and portable settings');
	$gate = Server::get(OCA\CoBudget\Service\AppDataLock::class);
	if ($options['phase'] === 'check-crash') {
		try { $gate->enter(true); $gate->leave(); throw new RuntimeException('Killed owner lock unexpectedly disappeared before TTL'); }
		catch (OCA\CoBudget\Exception\AppBusyException) { $check(true, 'Killed owner remains fenced until expiry'); }
	} else {
		// Only in this guarded disposable fixture, after the parent reaped the worker:
		// simulate six elapsed hours for the secondary restore marker (never delete it).
		$q = $db->getQueryBuilder(); $q->update('preferences')->set('configvalue', $q->createNamedParameter('0'))
			->where($q->expr()->eq('appid', $q->createNamedParameter('cobudget')))
			->andWhere($q->expr()->eq('configkey', $q->createNamedParameter('restore_running_since')))->executeStatement();
		// Native DB locks need scheduled cleanup; Redis expires them automatically.
		$provider = Server::get(OCP\Lock\ILockingProvider::class);
		if (method_exists($provider, 'cleanExpiredLocks')) $provider->cleanExpiredLocks();
		$gate->enter(true); $gate->leave();
		$maxId = max(array_map('intval', array_column($rows('entries'), 'id')));
		$id = $insert('entries', ['user_id' => $uid, 'workspace_id' => $fixture['entries'][0]['workspace_id'], 'type' => 'expense', 'amount' => '0.01', 'amount_cents' => 1, 'date' => time()]);
		$check($id > $maxId, 'Killed restore never rewinds IDs below the committed live rows');
		$q = $db->getQueryBuilder(); $q->delete('cobudget_entries')->where($q->expr()->eq('id', $q->createNamedParameter($id)))->executeStatement();
		$check(true, 'Native admission recovers after actual test TTL and cleanup');
		$backup->restoreFullBackup($uid, $crash['archive']['file_name'], 'CoBudgetCrash');
		$check($graph() === $fixture['graph'], 'Retry restores the complete original financial graph');
		$check($config->getUserValue($uid, 'cobudget', 'currency') === 'EUR', 'Retry restores original portable preferences');
	}
	echo $options['phase'] . ": $checks assertions passed.\n"; exit(0);
}
$integrity = Server::get(OCA\CoBudget\Service\DataIntegrityService::class);
$check($integrity->inspect()['orphanReferenceCount'] === 0, 'Deleted-goal history is not offered for destructive integrity repair');
$fixture = json_decode(file_get_contents($base . '/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$check(version_compare($config->getAppValue('cobudget', 'installed_version'), '0.8.8', '>='), 'Upgrade installed the target app version');
$check($graph() === $fixture['graph'], 'Upgrade preserved shared financial graph exactly');
$check((bool)$rows('reminders')[0]['email_notified'], 'Upgrade prevents replaying an already delivered reminder by email');
foreach (['notify_reminders_email', 'notify_planned_payments_email', 'notify_project_entries_email', 'notify_project_settlements_email'] as $key) {
	$check($config->getUserValue($uid, 'cobudget', $key, 'no') === 'no', 'New mail channel remains opt-in');
}
$q = $db->getQueryBuilder();$q->update('cobudget_entries')->set('amount_cents', $q->createNamedParameter(999))->where($q->expr()->eq('id', $q->createNamedParameter($fixture['entry'])))->executeStatement();
$restore = $backup->restoreFullBackup($uid, $fixture['archive']['file_name'], 'CoBudgetUpgrade');
$check($graph() === $fixture['graph'], 'Actual pre-upgrade archive restores entire shared financial graph');
$check((bool)$rows('reminders')[0]['email_notified'], 'Old archive does not replay delivered reminders');
$history = $rows('budget_snapshots');
$check(count($history) === 1 && (int)$history[0]['budget_goal_id'] === 0 && (int)$history[0]['amount_cents'] === 1499, 'Old archive retains detached goal history');
$file = Server::get(OCP\Files\IRootFolder::class)->getUserFolder($uid)->get($restore['safety_backup']['file_path']);
$check($file->getSize() > 0, 'Safety backup is present in actual Nextcloud Files');
Server::get(OCA\CoBudget\Service\DataIntegrityService::class)->assertProjectionIntegrity();
$check(true, 'Restored financial graph is valid');
$check($integrity->inspect()['orphanReferenceCount'] === 0, 'Detached restored history passes integrity checks');

// A server transfer maps active users throughout the graph/settings, not tombstones.
$backup->restoreFullBackup($uid, $fixture['archive']['file_name'], 'CoBudgetUpgrade', ['upgrade-member' => 'upgrade-renamed']);
$mapped = $fixture['graph'];
foreach ($mapped as &$tableRows) foreach ($tableRows as &$row) foreach ($row as $column => &$value) {
	if (in_array($column, ['user_id', 'owner_id', 'created_by', 'split_user_id', 'changed_by', 'from_user_id', 'to_user_id'], true) && $value === 'upgrade-member') $value = 'upgrade-renamed';
}
unset($tableRows, $row, $value);
$check($graph() === $mapped, 'Account mapping preserves every share, settlement, recurrence and former participant');
$check($config->getUserValue('upgrade-renamed', 'cobudget', 'currency') === 'USD', 'Portable settings follow the mapped account');
$check($config->getUserValue('upgrade-member', 'cobudget', 'currency', '') === '', 'Old account settings do not survive replacement');
$integrity->assertProjectionIntegrity();
$backup->restoreFullBackup($uid, $fixture['archive']['file_name'], 'CoBudgetUpgrade');
// Explicit high archive IDs must advance the generator on PostgreSQL too.
$maxId = max(array_map('intval', array_column($rows('entries'), 'id')));
$id = $insert('entries', ['user_id' => $uid, 'workspace_id' => $fixture['entries'][0]['workspace_id'], 'type' => 'expense', 'amount' => '0.01', 'amount_cents' => 1, 'date' => time()]);
$check($id > $maxId, 'Generated ID after restore exceeds all imported IDs');
$q = $db->getQueryBuilder(); $q->delete('cobudget_entries')->where($q->expr()->eq('id', $q->createNamedParameter($id)))->executeStatement();
$check($graph() === $fixture['graph'], 'Sequence probe leaves the restored graph unchanged');
echo "Released-beta upgrade: $checks assertions passed; actual database/Files, no receipt binaries or external messages.\n";
