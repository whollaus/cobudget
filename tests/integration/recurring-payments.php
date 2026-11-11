<?php
declare(strict_types=1);

// Local integration check: all fixture writes are rolled back, including on failure.
$options = getopt('', ['nextcloud:', 'user:', 'other-user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'])) {
	fwrite(STDERR, "Usage: php tests/integration/recurring-payments.php --nextcloud=/path/to/local/server --user=admin\n");
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
// No notifications or live push events may escape a rollback-only fixture.
$notifications = new class extends \OCA\CoBudget\Service\ProjectNotificationService {
 public function __construct() {}
 public function notifyEntryCreated(int $projectId, int $workspaceId, int $entryId, string $actorUserId, string $entryUserId, string $type, int $amountCents, string $currency, string $description = ''): void {}
 public function prepareSettlementNotifications(int $projectId, int $workspaceId, string $actorUserId): array { return []; }
 public function sendPreparedNotifications(array $payloads): void {}
};
$changes = Server::get(\OCA\CoBudget\Service\DataChangeService::class);
(new ReflectionProperty($changes, 'pushQueueResolved'))->setValue($changes, true);
(new ReflectionProperty($changes, 'pushQueue'))->setValue($changes, null);
$fresh = static function(int $workspaceId, string $class = EntryController::class) use ($notifications): object {
	$request = new OC\AppFramework\Http\Request(['server' => [
		'HTTP_X_WORKSPACE_ID' => (string)$workspaceId, 'HTTP_X_COBUDGET_TIMEZONE' => 'Europe/Vienna',
	]], Server::get(IRequestId::class), Server::get(IConfig::class));
	$reflection = new ReflectionClass($class);
	return $reflection->newInstanceArgs(array_map(static function(ReflectionParameter $parameter) use ($request, $notifications): mixed {
		if ($parameter->getName() === 'appName') return 'cobudget';
		$type = $parameter->getType()->getName();
		return match ($type) {
			IRequest::class => $request,
            \OCA\CoBudget\Service\ProjectNotificationService::class => $notifications,
			IL10N::class => Server::get(IFactory::class)->get('cobudget', 'en'),
			default => Server::get($type),
		};
	}, $reflection->getConstructor()->getParameters()));
};
$data = static function($response): array {
	if (!in_array($response->getStatus(), [200, 201], true)) throw new RuntimeException('API failure: ' . json_encode($response->getData()));
	return $response->getData();
};



$originalTimezone = date_default_timezone_get();
$clock = new class extends \OC\AppFramework\Utility\TimeFactory {
 public int $timestamp = 0;
 public function getTime(): int { return $this->timestamp; }
};
$job = new \OCA\CoBudget\Cron\RecurringEntriesJob($clock, $db, Server::get(\Psr\Log\LoggerInterface::class), Server::get(\OCA\CoBudget\Service\HashtagService::class), Server::get(\OCA\CoBudget\Service\EntryProjectionService::class), $changes);
$run = new ReflectionMethod($job, 'run');
$rows = static function(int $workspace) use ($db): array {
 $q = $db->getQueryBuilder();
 return $q->select('*')->from('cobudget_entries')->where($q->expr()->eq('workspace_id', $q->createNamedParameter($workspace)))->orderBy('id')->executeQuery()->fetchAll();
};
$db->beginTransaction();
try {
 // Isolate the real job from all existing series for this transaction only.
 $q = $db->getQueryBuilder();
 $q->update('cobudget_entries')->set('recurrence_interval', $q->createNamedParameter(null, \PDO::PARAM_NULL))->executeStatement();
 date_default_timezone_set('Europe/Vienna');
 $workspace = $insert('workspaces', ['name' => '__Recurrence fixture__', 'user_id' => $uid]);
 $controller = $fresh($workspace);
 $first = (new DateTimeImmutable('first day of next month'))->setTime(0, 0);
 $next = $first->modify('+1 month')->setTime(9, 0);
 $id = (int)$data($controller->create(type:'income', amount:1234.56, description:'__Salary__', date:$first->getTimestamp(), recurrenceInterval:'month', recurrenceMultiplier:1, recurrenceNextDate:$next->getTimestamp()))['id'];
 $check(!(bool)$rows($workspace)[0]['planned_notified'], 'Future original is armed for activation notification');
 $planned = $data($fresh($workspace)->index(isFuturePayments:true, sortDir:'asc'));
 $check(count($planned['entries']) === 1, 'One planned series, not duplicate first and next rows');
 $check((int)$planned['entries'][0]['date'] === $first->getTimestamp(), 'Future first payment appears before the next recurrence');
 $check((int)$planned['entries'][0]['original_date'] === $first->getTimestamp(), 'Editor receives the original saved date');
 $check((int)$planned['entries'][0]['recurrence_due_date'] === $next->setTime(6,0)->getTimestamp(), 'Legacy series tooltip receives the effective 06:00 server timestamp');
 $check((int)$planned['entries'][0]['recurrence_next_date'] === $next->getTimestamp(), 'Displaying the effective time preserves the stored series timestamp');
 $check($data($fresh($workspace)->index())['total'] === 0, 'Future first payment is excluded from ordinary payments');
 $filtered = $data($fresh($workspace)->index(isFuturePayments:true, dateFrom:$first->getTimestamp(), dateTo:$first->modify('last day of this month')->setTime(23,59,59)->getTimestamp()));
 $check((int)$filtered['total'] === 1, 'Filtering the first month finds the first payment');
 $filtered = $data($fresh($workspace)->index(isFuturePayments:true, dateFrom:$next->getTimestamp(), dateTo:$next->modify('last day of this month')->setTime(23,59,59)->getTimestamp()));
 $check((int)$filtered['total'] === 0, 'The planned list does not incorrectly move the first payment into the next month');
 $q=$db->getQueryBuilder();
 $q->update('cobudget_entries')->set('date',$q->createNamedParameter(strtotime('-2 days')))->where($q->expr()->eq('id',$q->createNamedParameter($id)))->executeStatement();
 $planned=$data($fresh($workspace)->index(isFuturePayments:true))['entries'][0];
 $check((int)$planned['date'] === $next->getTimestamp() && (int)$planned['original_date'] < time(), 'Once the first date has passed the list shows the next recurrence while retaining its edit date');
 $data($fresh($workspace)->stopRecurrence($id));
 $check((int)$data($fresh($workspace)->index(isFuturePayments:true))['total'] === 0, 'Stopping a series removes its future recurrence and preserves its payment');
 $check((int)$data($fresh($workspace)->index())['total'] === 1, 'Stopping a recurrence does not delete the original payment');

 foreach (['Europe/Vienna','UTC','America/New_York'] as $zoneName) {
  date_default_timezone_set($zoneName);
  $at=static fn(string $text):int=>(new DateTimeImmutable($text))->getTimestamp();
  foreach ([['month',1,'2026-12-01','2027-01-01'],['day',1,'2026-03-29','2026-03-30'],['week',2,'2026-10-25','2026-11-08'],['month',1,'2028-02-01','2028-03-01']] as [$interval,$multiplier,$due,$following]) {
   $ws=$insert('workspaces',['name'=>'__Cron fixture__','user_id'=>$uid]);
   $head=(int)$data($fresh($ws)->create(amount:100.01,description:'__Recurring #fixture__',date:$at($due.' -1 month'),recurrenceInterval:$interval,recurrenceMultiplier:$multiplier,recurrenceNextDate:$at($due.' 09:00')))['id'];
   $clock->timestamp=$at($due.' 05:59:59'); $run->invoke($job,null);
   $check(count($rows($ws))===1, $zoneName.' does not run before 06:00');
   $clock->timestamp=$at($due.' 06:00:00'); $run->invoke($job,null);
   $series=$rows($ws);
   $check(count($series)===2, $zoneName.' processes an existing 09:00 series at 06:00');
   $child=$series[1];
   $check((int)$child['date']===$at($due.' 06:00') && (int)$child['recurrence_next_date']===$at($following.' 06:00'), $zoneName.' keeps calendar date and 06:00 across month/year/DST boundaries');
   $check(!(bool)$child['planned_notified'], 'Each generated recurrence is armed for activation notification');
   $check((int)$child['amount_cents']===10001 && (int)$child['recurrence_parent_id']===$head && (int)$child['recurrence_series_id']===$head, 'Exact cents and series linkage survive');
   $check($series[0]['recurrence_next_date']===null && $series[0]['recurrence_interval']===null,'Only one active series head');
   $run->invoke($job,null);$check(count($rows($ws))===2,'Repeated job run does not duplicate the occurrence');
   $data($fresh($ws)->stopRecurrence((int)$child['id']));
  }
  // The selected end day includes its last payment, including legacy midnight values.
  $ws=$insert('workspaces',['name'=>'__End fixture__','user_id'=>$uid]);
  $data($fresh($ws)->create(amount:17,date:$at('2026-11-01'),recurrenceInterval:'month',recurrenceMultiplier:1,recurrenceNextDate:$at('2026-12-01 09:00'),recurrenceEndDate:$at('2026-12-01 00:00')));
  $clock->timestamp=$at('2026-12-01 08:00');$run->invoke($job,null);
  $series=$rows($ws);$check(count($series)===2 && $series[1]['recurrence_next_date']===null,'Late cron includes the final end day and stops the series');
  $run->invoke($job,null);$check(count($rows($ws))===2,'Finished series stays stopped');
 }

 // A missed cron catches up in order, with exactly one current head and no duplicates.
 date_default_timezone_set('Europe/Vienna');
 $at=static fn(string $text):int=>(new DateTimeImmutable($text))->getTimestamp();
 $ws=$insert('workspaces',['name'=>'__Catchup fixture__','user_id'=>$uid]);
 $data($fresh($ws)->create(amount:1.01,date:$at('2026-11-30'),recurrenceInterval:'day',recurrenceMultiplier:1,recurrenceNextDate:$at('2026-12-01 09:00')));
 $clock->timestamp=$at('2026-12-03 08:00');
 for($i=0;$i<5;$i++) $run->invoke($job,null);
 $series=$rows($ws);
 $check(count($series)===4 && count(array_unique(array_column($series,'date')))===4,'Three missed daily occurrences catch up exactly once');
 $heads=array_values(array_filter($series,static fn($e)=>$e['recurrence_next_date']!==null));
 $check(count($heads)===1 && (int)$heads[0]['recurrence_next_date']===$at('2026-12-04 06:00'),'Catch-up leaves just the next future occurrence active');
 $data($fresh($ws)->destroy((int)$heads[0]['id']));
 $clock->timestamp=$at('2026-12-05 08:00');$run->invoke($job,null);
 $check(count($rows($ws))===3,'Deleting the active head cannot resurrect a stopped series');
 if (isset($options['other-user'])) {
  $peer=Server::get(IUserManager::class)->get($options['other-user']);
  if (!$peer || $peer->getUID()===$uid) throw new RuntimeException('Different existing peer required');
  $ws=$insert('workspaces',['name'=>'__Shared cron fixture__','user_id'=>$uid]);
  $peerWorkspace=$insert('workspaces',['name'=>'__Shared cron peer__','user_id'=>$peer->getUID()]);
  $project=$insert('projects',['name'=>'__Shared recurrence__','owner_id'=>$uid,'workspace_id'=>$ws,'created_at'=>time()]);
  foreach ([$uid=>$ws,$peer->getUID()=>$peerWorkspace] as $member=>$memberWorkspace) $insert('members',['project_id'=>$project,'user_id'=>$member,'share_basis_points'=>5000,'personal_workspace_id'=>$memberWorkspace]);
  $data($fresh($ws)->create(amount:100.01,date:$at('2026-11-01'),projectId:$project,recurrenceInterval:'month',recurrenceMultiplier:1,recurrenceNextDate:$at('2026-12-01 09:00')));
  $clock->timestamp=$at('2026-12-01 08:00');$run->invoke($job,null);$run->invoke($job,null);
  $sources=array_values(array_filter($rows($ws),static fn($e)=>$e['entry_kind']==='shared'));
  $check(count($sources)===2,'Shared recurrence creates one canonical source');
  $child=$sources[1];
  $q=$db->getQueryBuilder();$projections=$q->select('*')->from('cobudget_entries')->where($q->expr()->eq('source_entry_id',$q->createNamedParameter((int)$child['id'])))->executeQuery()->fetchAll();
  $check(count($projections)===2 && array_sum(array_column($projections,'amount_cents'))===10001,'Shared recurrence allocates exact cents into two personal rows');
  foreach($projections as $projection) $check((bool)$projection['planned_notified'] && (bool)$projection['is_locked'] && $projection['recurrence_next_date']===null && (int)$projection['date']===$at('2026-12-01 06:00'),'Personal projections are locked, early and never generate their own recurrences');
 }
 echo 'PASS '.$assertions." recurrence assertions; all fixture writes rolled back.\n";
} finally {
 $db->rollBack();
 date_default_timezone_set($originalTimezone);
 $session->setUser($originalUser);
}
