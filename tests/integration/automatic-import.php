<?php
declare(strict_types=1);

use OCA\CoBudget\Controller\AutoImportController;
use OCA\CoBudget\Cron\AutoImportJob;
use OCP\BackgroundJob\IJobList;
use OCP\Notification\IApp;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCA\CoBudget\Service\AutoImportRules;
use OCA\CoBudget\Service\AutoImportRunner;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequestId;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Server;

$options = getopt('', ['nextcloud:', 'phase:', 'rule:', 'expected:']);
$root = realpath($options['nextcloud'] ?? '');
$phase = $options['phase'] ?? '';
if (PHP_SAPI !== 'cli' || !$root || !in_array($phase, ['interrupt', 'manual-busy', 'file-lock', 'changed-source-lock', 'sync-worker', 'sync-race', 'locking-off', 'resume', 'archive-kill', 'archive-check', 'archive-worker', 'archive-races', 'ownership-prepare', 'ownership-check', 'load', 'load-check'], true) || !is_file(dirname($root) . '/test-instance.json')) {
	throw new RuntimeException('Use run-upgrade.py to run the interrupted automatic import fixture.');
}
$base = dirname($root);
$marker = json_decode(file_get_contents($base . '/test-instance.json'), true, flags: JSON_THROW_ON_ERROR);
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function (Throwable $e): void { fwrite(STDERR, (string)$e . "\n"); exit(1); });

function expect(bool $ok, string $reason): void {
	if (!$ok) throw new RuntimeException($reason);
}
$config = Server::get(IConfig::class);
$dataDir = realpath($config->getSystemValue('datadirectory'));
expect(($marker['data'] ?? '') === $dataDir && str_starts_with($dataDir, $base . '/')
	&& ($marker['database'] === 'sqlite' ? 'sqlite3' : $marker['database']) === $config->getSystemValue('dbtype')
	&& ($marker['database'] === 'sqlite' || $marker['dbname'] === $config->getSystemValue('dbname')), 'Fixture must run on its disposable database and data directory.');
final class AutoImportNotificationProbe implements IApp {
	public static array $received = [];
	public function notify(INotification $notification): void {
		if ($notification->getApp() === 'cobudget') {
			self::$received[] = [$notification->getUser(), $notification->getSubject()];
		}
	}
	public function markProcessed(INotification $notification): void {}
	public function getCount(INotification $notification): int { return 0; }
}
Server::get(IManager::class)->registerApp(AutoImportNotificationProbe::class);
$db = Server::get(IDBConnection::class);
$rules = Server::get(AutoImportRules::class);
$runner = Server::get(AutoImportRunner::class);
$session = Server::get(IUserSession::class);
$actor = 'upgrade-member';
$shares = ['upgrade-admin' => 5000, $actor => 3000, 'upgrade-renamed' => 2000];
$users = Server::get(IUserManager::class);
foreach (array_keys($shares) as $user) expect($users->userExists($user), 'Required fixture user missing: ' . $user);
$files = Server::get(IRootFolder::class)->getUserFolder($actor);

$insert = static function (string $table, array $values) use ($db): int {
	$q = $db->getQueryBuilder();
	$q->insert('cobudget_' . $table);
	foreach ($values as $column => $value) {
		$q->setValue($column, $q->createNamedParameter($value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
	}
	$q->executeStatement();
	return (int)$q->getLastInsertId();
};
$read = static function (string $table, array $criteria) use ($db): array {
	$q = $db->getQueryBuilder();
	$q->select('*')->from('cobudget_' . $table)->orderBy('id');
	foreach ($criteria as $column => $value) {
		$q->andWhere($q->expr()->eq($column, $q->createNamedParameter($value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR)));
	}
	$result = $q->executeQuery();
	try { return $result->fetchAll(); } finally { $result->closeCursor(); }
};
$asMember = static function (int $workspace, callable $action) use ($session, $actor, $users, $config, $db, $rules, $runner) {
	$session->setUser($users->get($actor));
	try {
		$request = new OC\AppFramework\Http\Request(['server' => ['HTTP_X_WORKSPACE_ID' => (string)$workspace]], Server::get(IRequestId::class), $config);
		$api = new AutoImportController('cobudget', $request, $session, $db, $config, $rules, Server::get(IFactory::class)->get('cobudget', 'en'), $runner);
		return $action($api);
	} finally {
		$session->setUser(null);
	}
};
$createRule = static function (int $workspace, string $folder, ?int $project, string $notify = 'none') use ($asMember): int {
	$row = $asMember($workspace, fn ($api) => $api->create($folder, 5, $notify, true, 'Europe/Vienna', $project));
	expect($row->getStatus() === 201, 'Creating member rule failed: ' . json_encode($row->getData()));
	return $row->getData()['id'];
};
$payload = static function (string $prefix, int $count, array $lastFields = []): string {
	$payments = [];
	for ($i = 1; $i <= $count; $i++) {
		$payments[] = ['date' => '2026-01-01', 'amount' => -10, 'description' => $prefix . '-' . $i] + ($i === $count ? $lastFields : []);
	}
	return json_encode(['payments' => $payments], JSON_THROW_ON_ERROR);
};

// Return stable database identities as well as checking every individual allocation and projection.
$graph = static function (array $fixture, int $count, bool $onlyDelivery = false) use ($read, $actor, $shares): array {
	$entries = $read('entries', ['project_id' => $fixture['project']]);
	if ($onlyDelivery) {
		$entries = array_filter($entries, static fn (array $entry): bool => str_starts_with($entry['description'], $fixture['folder'] . '-'));
	}
	expect(count($entries) === 4 * $count, "Expected $count shared payments plus three personal entries each, got " . count($entries) . ' total.');
	$byId = array_column($entries, null, 'id');
	$originals = array_values(array_filter($entries, static fn(array $e): bool => $e['entry_kind'] === 'shared'));
	expect(count($originals) === $count, 'Unexpected shared payment count.');
	$identities = [];
	foreach ($originals as $idx => $entry) {
		expect($entry['description'] === $fixture['folder'] . '-' . ($idx + 1) && $entry['user_id'] === $actor
			&& $entry['created_by'] === $actor && $entry['type'] === 'expense' && (int)$entry['amount_cents'] === 1000
			&& !empty($entry['import_key']), 'Incorrect original, creator or payer on row ' . ($idx + 1));
		$id = (int)$entry['id'];
		$rows = $read('entry_shares', ['entry_id' => $id]);
		expect(count($rows) === count($shares), 'Unexpected allocation count for payment ' . $id);
		$members = [];
		foreach ($rows as $row) {
			$uid = $row['user_id'];
			$cents = intdiv($shares[$uid] ?? 0, 10);
			$personalId = (int)$row['personal_entry_id'];
			$personal = $byId[$personalId] ?? [];
			expect(isset($shares[$uid]) && !isset($members[$uid]) && (int)$row['share_basis_points'] === $shares[$uid]
				&& (int)$row['amount_cents'] === $cents && ($personal['user_id'] ?? '') === $uid
				&& $personal['entry_kind'] === 'personal' && !empty($personal['is_locked']) && (int)$personal['source_entry_id'] === $id
				&& (int)$personal['workspace_id'] === $fixture['workspaces'][$uid] && (int)$personal['amount_cents'] === $cents,
				'Incorrect or duplicate allocation/projection for ' . $uid . ' on source ' . $id);
			$members[$uid] = [(int)$row['id'], $personalId];
		}
		$identities[] = [$id, $entry['import_key'], $members];
	}
	return $identities;
};

if ($phase === 'archive-worker') {
	define('CB_TEST_ARCHIVE_MOVE', true);
	$outcome = $runner->runRule((int)$options['rule']);
	expect($outcome === ($options['expected'] ?? 'checked'), 'Unexpected archive worker result: ' . $outcome);
	return;
}
if ($phase === 'sync-worker') {
	define('CB_TEST_RECONCILED', true);
	expect($runner->runRule((int)$options['rule']) === 'checked', 'Concurrent scan did not finish.');
	return;
}

if ($phase === 'file-lock' || $phase === 'changed-source-lock') {
	$expected = $phase === 'changed-source-lock' ? 'more' : 'checked';
	expect($runner->runRule((int)$options['rule']) === $expected, 'Unexpected worker result for ' . $phase);
	return;
}

$statePath = $base . '/automatic-import-fixture.json';
if ($phase === 'interrupt') {
	$folder = 'AutoImport-' . bin2hex(random_bytes(6));
	$workspaces = [];
	foreach (array_keys($shares) as $uid) {
		$workspaces[$uid] = $insert('workspaces', ['user_id' => $uid, 'name' => $folder]);
	}
	$project = $insert('projects', ['owner_id' => 'upgrade-admin', 'name' => $folder, 'workspace_id' => $workspaces['upgrade-admin']]);
	foreach ($shares as $uid => $basisPoints) {
		$insert('members', ['project_id' => $project, 'user_id' => $uid, 'personal_workspace_id' => $workspaces[$uid], 'share_basis_points' => $basisPoints]);
	}
	$config->setUserValue($actor, 'cobudget', 'enable_payment_imports', 'yes');
	$config->setUserValue($actor, 'cobudget', 'enable_projects', 'yes');
	$inbox = $files->newFolder($folder);
	$inbox->newFile('delivery.json', $payload($folder, 50));
	$rule = $createRule($workspaces[$actor], $folder, $project);
	$fixture = compact('folder', 'workspaces', 'project', 'rule');
	expect(file_put_contents($statePath, json_encode($fixture, JSON_THROW_ON_ERROR)) !== false, 'Could not persist fixture identities.');
	$runner->runRule($rule);
	throw new RuntimeException('Expected SIGKILL inside the worker after its first packet commit.');
}

$fixture = json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
$folder = $fixture['folder'];
$rule = $fixture['rule'];
$inbox = $files->get($folder);
$manualStart = static fn () => $asMember($fixture['workspaces'][$actor], fn ($api) => $api->runNow($rule));
if ($phase === 'sync-race') {
	require __DIR__ . '/automatic-import-sync.php';
	return;
}
if ($phase === 'manual-busy') {
	$archiveBlocked = false;
	try {
		$rules->withAreaLock($fixture['project'], true, static fn () => null);
	} catch (\OCP\Lock\LockedException) {
		$archiveBlocked = true;
	}
	expect($archiveBlocked, 'Archiving must wait while a member is importing into the area.');
	expect($manualStart()->getStatus() === 409, 'A manual start while the cron worker holds the rule lock must report a conflict.');
	$graph($fixture, 33);
	echo "Manual start during cron returned 409 and preserved all 33 payments and 99 shares.\n";
	return;
}
if ($phase === 'locking-off') {
	expect(Server::get(\OCP\Lock\ILockingProvider::class) instanceof \OC\Lock\NoopLockingProvider, 'Fixture must boot with real file locking disabled.');
	$nextRun = (int)$read('import_rules', ['id' => $rule])[0]['next_run'];
	$rules->schedule($rule, time() - 1);
	expect(in_array($rule, array_column($rules->due(time()), 'id'), true), 'Interrupted rule must be due for cron.');
	$schedule = $read('import_rules', ['id' => $rule]);
	$journal = $read('import_files', ['rule_id' => $rule]);
	$originals = $graph($fixture, 33);
	$inbox->newFile('fresh.json', $payload('without-file-locks', 1));
	expect($manualStart()->getStatus() === 503, 'Manual start requires file locking.');
	$jobs = Server::get(IJobList::class);
	$job = $jobs->getJobsIterator(AutoImportJob::class, 1, 0)->current();
	expect($job instanceof AutoImportJob, 'Automatic import cron job must be registered.');
	$job->setLastRun(0);
	$job->start($jobs);
	expect($schedule === $read('import_rules', ['id' => $rule]), 'Disabled locking must preserve the due schedule and last result.');
	expect($journal === $read('import_files', ['rule_id' => $rule]), 'Disabled locking must preserve the complete interrupted journal and snapshot.');
	expect($graph($fixture, 33) === $originals && $read('entries', ['description' => 'without-file-locks-1']) === [],
		'Disabled locking must preserve the first 33 payments and shares and leave the new delivery unbooked.');
	expect($inbox->nodeExists('delivery.json') && $inbox->nodeExists('fresh.json')
		&& !$inbox->nodeExists('Verarbeitet/delivery.json'), 'Disabled locking must leave the files in the inbox.');
	expect(AutoImportNotificationProbe::$received === [], 'Disabled locking must not dispatch an import result.');
	$inbox->get('fresh.json')->delete();
	$rules->schedule($rule, $nextRun);
	echo "Disabled file locking passed: manual 503, due cron preserved 33 payments and 99 shares, new file untouched.\n";
	return;
}
if ($phase === 'ownership-prepare' || $phase === 'ownership-check') {
	require __DIR__ . '/automatic-import-archive-ownership.php';
	return;
}
if ($phase === 'archive-races') {
	require __DIR__ . '/automatic-import-archive.php';
	return;
}
if (str_starts_with($phase, 'archive-')) {
	$archive = json_decode(file_get_contents($base . '/archive-rule.json'), true, flags: JSON_THROW_ON_ERROR);
	$locks = Server::get(OCP\Lock\ILockingProvider::class);
	if (method_exists($locks, 'cleanExpiredLocks')) $locks->cleanExpiredLocks();
	$runner->runRule($archive['id']);
	expect($phase !== 'archive-kill', 'Worker failed to stop immediately after the archive move.');
	$runner->runRule($archive['id']);
	$journal = $read('import_files', ['rule_id' => $archive['id']])[0];
	expect($rules->forOwner($archive['id'], $actor, $fixture['workspaces'][$actor])['last_file']['id']
		=== $files->get($archive['folder'] . '/Verarbeitet/archive.json')->getId(), 'Restored report must open the file moved before the crash.');
	expect($journal['state'] === 'done' && !$journal['detail'] && (int)$journal['imported_count'] === 1
		&& str_contains($read('import_rules', ['id' => $archive['id']])[0]['last_message'], 'archive.json')
		&& $files->nodeExists($archive['folder'] . '/Verarbeitet/archive.json')
		&& count($read('entries', ['description' => 'archive-recovery-1'])) === 1
		&& AutoImportNotificationProbe::$received === [[$actor, 'automatic_import_success']],
		'An empty inbox must finish the archived delivery and report its result once.');
	echo "Archive recovery passed after SIGKILL, including one success notification across two restarts.\n";
	return;
}
if ($phase === 'load' || $phase === 'load-check') {
	require __DIR__ . '/automatic-import-backlog.php';
	return;
}
$progress = $read('import_files', ['rule_id' => $rule])[0] ?? [];
expect(($progress['state'] ?? '') === 'writing' && (int)$progress['next_row'] === 33
	&& (int)$progress['commit_limit'] === 33 && (int)$progress['imported_count'] === 33
	&& $inbox->nodeExists('delivery.json'), 'The killed worker must leave the first 33 payments committed and the delivery in the inbox.');
$originals = $graph($fixture, 33);
// Nextcloud cron clears expired DB locks. The fixture uses a short TTL and waits for the killed owner to expire.
$provider = Server::get(OCP\Lock\ILockingProvider::class);
if (method_exists($provider, 'cleanExpiredLocks')) $provider->cleanExpiredLocks();
$scheduleBefore = (int)$read('import_rules', ['id' => $rule])[0]['next_run'];
$result = $manualStart();
expect($result->getStatus() === 200 && $result->getData()['outcome'] === 'checked'
	&& $result->getData()['rule']['next_run'] === $scheduleBefore && $result->getData()['rule']['last_error'] === false,
	'Manual start must finish the saved delivery immediately, return its result and preserve the next scheduled check.');
$completed = $graph($fixture, 50);
expect(array_slice($completed, 0, 33) === $originals, 'Resuming must preserve IDs of the first packet.');
$progress = $read('import_files', ['rule_id' => $rule])[0];
expect($progress['state'] === 'done' && (int)$progress['next_row'] === 50 && (int)$progress['imported_count'] === 50, 'Final durable checkpoint must cover all 50 rows.');
expect($result->getData()['rule']['last_file'] === ['name' => 'delivery.json', 'id' => $inbox->get('Verarbeitet/delivery.json')->getId()],
	'The result must open the archived successful delivery.');
expect(!$inbox->nodeExists('delivery.json') && $inbox->nodeExists('Verarbeitet/delivery.json'), 'Completed original must be archived.');

foreach (['delivery.json', 'renamed.json'] as $name) {
	$inbox->newFile($name, $payload($folder, 50));
	$runner->runRule($rule);
	expect(!$inbox->nodeExists($name) && $completed === $graph($fixture, 50), 'Reupload changed a payment/allocation: ' . $name);
}
expect($manualStart()->getStatus() === 200 && $graph($fixture, 50) === $completed, 'A repeated manual start preserves payment and allocation IDs.');
expect(count($inbox->get('Verarbeitet')->getDirectoryListing()) === 3, 'Original and both duplicate deliveries must be in the successful archive.');
$newDates = str_replace('2026-01-01', '2026-01-02', $payload($folder, 50));
foreach (['delivery.json', 'next-day.json'] as $name) {
	$inbox->newFile($name, $newDates);
	$runner->runRule($rule);
	$status = $rules->forOwner($rule, $actor, $fixture['workspaces'][$actor]);
	$message = $status['last_message'];
	expect($status['last_file'] === ['name' => $name, 'id' => $inbox->get('Fehler/' . $name)->getId()],
		'A conflict must link the rejected upload, even when the message also names the original delivery.');
	expect($message === $name . ' — The filename “delivery.json” was already used for an import with different payment data. Payments already booked from the original file: 50.',
		'A date change must identify the original filename and booked count, including after a rename.');
	expect($inbox->nodeExists('Fehler/' . $name) && $completed === $graph($fixture, 50), 'A filename conflict must preserve all original payments and shares.');
}
// An archive can already contain an older rejection with the same name.
$duplicate = $inbox->newFile('delivery.json', $newDates);
$secondFileId = $duplicate->getId();
$runner->runRule($rule);
$status = $rules->forOwner($rule, $actor, $fixture['workspaces'][$actor]);
$rejected = $inbox->get('Fehler')->getFirstNodeById($status['last_file']['id']);
expect($rejected && $rejected->getId() === $secondFileId && $rejected->getName() !== 'delivery.json',
	'The result must open the new error archive node even when Files adds a filename suffix.');

// Conflict records from earlier versions do not retain the original name.
$legacy = $read('import_files', ['rule_id' => $rule, 'state' => 'conflict', 'source_name' => 'delivery.json'])[0];
Server::get(\OCA\CoBudget\Service\AutoImportLedger::class)->update((int)$legacy['id'], ['source_name' => null]);
$inbox->newFile('legacy-copy.json', $newDates);
$runner->runRule($rule);
expect($rules->forOwner($rule, $actor, $fixture['workspaces'][$actor])['last_message'] === 'legacy-copy.json — The original filename was already used for an import with different payment data. Payments already booked from the original file: 50.'
	&& $inbox->nodeExists('Fehler/legacy-copy.json') && $completed === $graph($fixture, 50), 'An old conflict must still explain filename reuse without blaming the new filename.');
foreach (['category' => 'categories', 'payment_partner' => 'payment_partners'] as $field => $table) {
	$name = $field . '.json';
	$unknown = $folder . '-' . $field;
	$inbox->newFile($name, $payload($unknown, 51, [$field => $unknown]));
	$runner->runRule($rule);
	expect($inbox->nodeExists('Fehler/' . $name) && $completed === $graph($fixture, 50)
		&& $read($table, ['project_id' => $fixture['project'], 'name' => $unknown]) === [], 'An unknown ' . $field . ' in packet two must reject all 51 payments.');
}
$personalName = $folder . '-personal';
$personalInbox = $files->newFolder($personalName);
expect(AutoImportNotificationProbe::$received === [], 'Silent shared rule must suppress file and per-payment notifications.');
$personalRule = $createRule($fixture['workspaces'][$actor], $personalName, null, 'all');
$personalInbox->newFile('personal.json', $payload($personalName, 1));
$jobs = Server::get(IJobList::class);
foreach ($jobs->getJobsIterator(AutoImportJob::class, 1, 0) as $job) {
	$job->setLastRun(0);
	$job->start($jobs);
}
expect(AutoImportNotificationProbe::$received === [[$actor, 'automatic_import_success']], 'A due rule should send one file result to its owner through the registered cron job.');
$personal = $read('entries', ['description' => $personalName . '-1']);
expect(count($personal) === 1 && $personal[0]['user_id'] === $actor && $personal[0]['created_by'] === $actor
	&& $personal[0]['project_id'] === null && $personal[0]['entry_kind'] === 'personal' && (int)$personal[0]['amount_cents'] === 1000
	&& (int)$personal[0]['workspace_id'] === $fixture['workspaces'][$actor] && $read('entry_shares', ['entry_id' => (int)$personal[0]['id']]) === []
	&& $personalInbox->nodeExists('Verarbeitet/personal.json'), 'Personal rule must book once to the member without an area or shares.');
$errorFolder = $files->newFolder($folder . '-errors');
$errorRule = $createRule($fixture['workspaces'][$actor], $errorFolder->getName(), null, 'error');
$lastError = static fn () => array_column($rules->forWorkspace($actor, $fixture['workspaces'][$actor]), 'last_error', 'id')[$errorRule];
expect($lastError() === null, 'An import without a result has no error status.');
$errorFolder->newFile('valid.json', $payload('error-only', 1));
$runner->runRule($errorRule);
expect($lastError() === false, 'A successful import stores its result independently of the notification mode.');
$errorFolder->newFile('invalid.json', '{');
$runner->runRule($errorRule);
expect($lastError() === true, 'A rejected file marks the last result as an error.');
expect(AutoImportNotificationProbe::$received === [[$actor, 'automatic_import_success'], [$actor, 'automatic_import_error']],
	'Error-only mode should send a single error without announcing the preceding successful file.');
// Capture the handoff to the optional transports in this cron process. Their own integration tests cover opt-in and delivery.
$mail = new class extends \OCA\CoBudget\Service\EmailNotificationService {
	public array $received = [];
	public function __construct() {}
	public function send(string $uid, string $event, array $params): bool {
		$this->received[] = [$uid, $event, $params['error'], $params['result']];
		return false; // A mail failure must not suppress Talk or interfere with archiving.
	}
};
$talk = new class extends \OCA\CoBudget\Service\TalkNotificationService {
	public array $received = [];
	public function __construct() {}
	public function notifyAutomaticImport(string $userId, bool $error, string $result): bool {
		$this->received[] = [$userId, $error, $result];
		return true;
	}
};
$channels = new AutoImportRunner($rules, Server::get(\OCA\CoBudget\Service\AutoImportLedger::class),
	Server::get(\OCA\CoBudget\Controller\EntryController::class), $db, $users, Server::get(IFactory::class),
	Server::get(IManager::class), Server::get(\Psr\Log\LoggerInterface::class), $config, $mail, $talk, Server::get(IRootFolder::class));
$config->setUserValue($actor, 'cobudget', 'notify_automatic_imports', 'no');
$bellBefore = AutoImportNotificationProbe::$received;
$errorFolder->newFile('quiet.json', $payload('quiet-success', 1));
$channels->runRule($errorRule);
$errorFolder->newFile('broken.json', '{');
$channels->runRule($errorRule);
$personalInbox->newFile('reported.json', $payload('reported-success', 1));
$channels->runRule($personalRule);
expect(AutoImportNotificationProbe::$received === $bellBefore, 'Nextcloud opt-out must leave other channels available.');
expect(count($mail->received) === 2 && count($talk->received) === 2, 'Expected one error and one success on both optional channels.');
foreach ([true, false] as $i => $failed) {
	[$uid, $event, $error, $result] = $mail->received[$i];
	expect($uid === $actor && $event === 'import' && $error === $failed
		&& str_contains($result, $failed ? 'broken.json' : 'reported.json')
		&& $talk->received[$i] === [$uid, $error, $result], 'Channel result, recipient or per-rule filter differs.');
}
expect($personalInbox->nodeExists('Verarbeitet/reported.json'), 'A transport failure must not undo a completed import.');
echo "Automatic import passed: SIGKILL after 33/99; resumed all 50 payments, 150 shares and 150 personal entries; reuploads, name conflict, missing lookups and personal rule verified.\n";

// A producer replaces a file after 33 commits. The snapshot still contains the validated last payment.
$name = 'replacement.json';
$prefix = $folder . '-replace';
$original = str_pad($payload($prefix, 50), 5 * 1024 * 1024);
$file = $inbox->newFile($name, $original);
$runner->runRule($rule, 2);
$partial = $read('import_files', ['rule_id' => $rule, 'name_key' => hash('sha256', $name)])[0];
expect((int)$partial['next_row'] === 33 && $partial['snapshot'] === $original, 'Expected a durable snapshot at row 33.');
$changed = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
$changed['payments'][49]['amount'] = -11;
$replacement = json_encode($changed, JSON_THROW_ON_ERROR);
$file->putContent($replacement);
$runner->runRule($rule);
expect($inbox->nodeExists('Fehler/' . $name) && str_contains($read('import_rules', ['id' => $rule])[0]['last_message'], '50'),
	'The replacement should report the booked count and go to the error folder.');
$inbox->newFile('renamed-correction.json', $replacement);
$runner->runRule($rule);
expect($inbox->nodeExists('Fehler/renamed-correction.json'), 'Renaming the rejected correction must not permit a new import.');
for ($i = 1; $i <= 50; $i++) {
	$entries = $read('entries', ['description' => $prefix . '-' . $i, 'entry_kind' => 'shared']);
	expect(count($entries) === 1 && (int)$entries[0]['amount_cents'] === 1000
		&& count($read('entry_shares', ['entry_id' => $entries[0]['id']])) === 3, 'Wrong payment or allocations after replacing row ' . $i);
}
// A sync client may rename the existing node before the runner sees its modified contents.
$prefix = $folder . '-sync-rename';
$file = $inbox->newFile('sync.json', $payload($prefix, 50));
$runner->runRule($rule, 2);
$renamed = $file->move($inbox->getPath() . '/sync-changed.json');
$changed = json_decode($payload($prefix, 50), true, flags: JSON_THROW_ON_ERROR);
$changed['payments'][49]['amount'] = -25;
$renamed->putContent(json_encode($changed, JSON_THROW_ON_ERROR));
$runner->runRule($rule);
expect($inbox->nodeExists('Fehler/sync-changed.json') && str_contains($read('import_rules', ['id' => $rule])[0]['last_message'], '50'),
	'A renamed source must still be reconciled against its immutable snapshot.');
for ($i = 1; $i <= 50; $i++) {
	expect(count($read('entries', ['description' => $prefix . '-' . $i, 'entry_kind' => 'shared'])) === 1,
		'Renaming the in-flight Files node duplicated row ' . $i);
}
$original = $payload($folder . '-order', 2);
$inbox->newFile('ordered.json', $original);
$runner->runRule($rule);
$copy = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
$copy['payments'] = array_map(static fn ($payment) => array_reverse($payment, true), $copy['payments']);
$inbox->newFile('other-order.json', json_encode($copy, JSON_THROW_ON_ERROR));
$runner->runRule($rule);
expect($inbox->nodeExists('Verarbeitet/other-order.json'), 'Reordered fields must be recognized as a successful replay.');
foreach ([1, 2] as $i) {
	expect(count($read('entries', ['description' => $folder . '-order-' . $i, 'entry_kind' => 'shared'])) === 1, 'Reordered fields duplicated a payment.');
}
$config->setUserValue($actor, 'cobudget', 'notify_automatic_imports', 'yes');
$archive = $files->newFolder($folder . '-archive');
$archiveRule = $createRule($fixture['workspaces'][$actor], $archive->getName(), null, 'all');
$archive->newFile('archive.json', $payload('archive-recovery', 1));
file_put_contents($base . '/archive-rule.json', json_encode(['id' => $archiveRule, 'folder' => $archive->getName()], JSON_THROW_ON_ERROR));
echo "Replacement and reordered-field regressions passed: 50 originals and 150 shares; two originals after re-export.\n";

require __DIR__ . '/automatic-import-waiting.php';
require __DIR__ . '/automatic-import-recovery.php';

require __DIR__ . '/automatic-import-permissions.php';
require __DIR__ . '/automatic-import-queue.php';
