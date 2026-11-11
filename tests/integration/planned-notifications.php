<?php
declare(strict_types=1);

// Rollback-only planned payment notification integration. Both transports are in-memory fakes:
// no notification, push, Talk message, room or account is created.
$options = getopt('', ['nextcloud:', 'user:', 'other-user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'], $options['other-user'])) {
 fwrite(STDERR, "Usage: php tests/integration/planned-notifications.php --nextcloud=/path/to/server --user=admin --other-user=testuser\n"); exit(1);
}
$root = realpath($options['nextcloud']);
if (!$root || !is_file($root . '/lib/base.php')) throw new RuntimeException('Nextcloud installation missing');
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n"); exit(1); });

use OCP\Server;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IDBConnection;
use OCP\Notification\INotification;
use OCA\CoBudget\Service\TalkBackend;
use OCA\CoBudget\Service\TalkNotificationService;
use OCA\CoBudget\Service\BackupService;
use OCA\CoBudget\Service\UserResetService;
use OCA\CoBudget\Service\ParticipantService;
use OCA\CoBudget\Controller\TalkController;
use OCA\CoBudget\Controller\UserController;
use OCA\CoBudget\Cron\RemindersJob;

final class ReminderTestNotifications extends OC\Notification\Manager {
 public array $sent = [];
 public bool $fail = false;
 public function __construct(private OCP\Notification\IManager $factory) {}
 public function createNotification(): INotification { return $this->factory->createNotification(); }
 public function notify(INotification $notification): void {
  if ($this->fail) throw new RuntimeException('PRIVATE TRANSPORT ERROR');
  $this->sent[] = $notification;
 }
}
final class ReminderTestTalk extends TalkBackend {
 public array $sent = [];
 public int $created = 0;
 public bool $enabled = true;
 public bool $privateRoom = true;
 public bool $fail = false;
 public function __construct() {}
 public function available(string $userId): bool { return $this->enabled; }
 public function ready(string $token, string $userId): bool { return $this->privateRoom && $token === substr(hash('sha256', $userId), 0, 20); }
 public function createPersonalConversation(string $userId): string { $this->created++; return substr(hash('sha256', $userId), 0, 20); }
 public function send(string $token, string $userId, string $message): void {
  if ($this->fail || !$this->ready($token, $userId)) throw new RuntimeException('PRIVATE TRANSPORT ERROR');
  $this->sent[] = ['user' => $userId, 'message' => $message];
 }
}
final class ReminderTestLogger extends Psr\Log\AbstractLogger {
 public array $records = [];
 public function log($level, string|Stringable $message, array $context = []): void { $this->records[] = [$level, $message, $context]; }
}
$db = Server::get(IDBConnection::class); $config = Server::get(IConfig::class);
$users = Server::get(IUserManager::class); $session = Server::get(IUserSession::class);
$original = $session->getUser();
$user = $users->get($options['user']); $peer = $users->get($options['other-user']);
if (!$user || !$peer || $user->getUID() === $peer->getUID()) throw new RuntimeException('Two different existing local users required');
$uid = $user->getUID(); $other = $peer->getUID();
$bell = new ReminderTestNotifications(Server::get(OCP\Notification\IManager::class));
$backend = new ReminderTestTalk(); $logger = new ReminderTestLogger();
$talk = new TalkNotificationService($backend, $config, Server::get(OCP\IURLGenerator::class), $users, Server::get(OCP\L10N\IFactory::class), Server::get(OCP\Lock\ILockingProvider::class), $logger);
$planned = new \OCA\CoBudget\Service\PlannedPaymentNotificationService($db, $config, Server::get(ParticipantService::class), $bell, $talk, Server::get(OCP\Lock\ILockingProvider::class), $logger);
$job = new RemindersJob(Server::get(OCP\AppFramework\Utility\ITimeFactory::class), $db, $logger, $bell, Server::get(ParticipantService::class), $config, $talk, $planned, new class extends \OCA\CoBudget\Service\CalendarReminderService { public function __construct() {} public function syncAll(): void {} }, Server::get(\OCA\CoBudget\Service\ReminderService::class));
$run = static fn() => (new ReflectionMethod($job, 'run'))->invoke($job, null);
$assertions = 0;
$check = static function(bool $ok, string $why) use (&$assertions): void { if (!$ok) throw new RuntimeException($why); $assertions++; };
$insert = static function(string $table, array $values) use ($db): int {
 $qb=$db->getQueryBuilder();$qb->insert('cobudget_'.$table);
 foreach ($values as $key=>$value) $qb->setValue($key,$qb->createNamedParameter($value));
 $qb->executeStatement();return (int)$qb->getLastInsertId();
};
$update = static function(int $id, array $values) use ($db): void {
 $qb=$db->getQueryBuilder();$qb->update('cobudget_entries');
 foreach ($values as $key=>$value) $qb->set($key,$qb->createNamedParameter($value));
 $qb->where($qb->expr()->eq('id',$qb->createNamedParameter($id)))->executeStatement();
};
$notified = static function(int $id) use ($db): bool {
 $qb=$db->getQueryBuilder();$qb->select('reminder_notified')->from('cobudget_entries')->where($qb->expr()->eq('id',$qb->createNamedParameter($id)));
 $r=$qb->executeQuery();$value=$r->fetchOne();$r->closeCursor();return (bool)$value;
};
$fresh = static function(string $class) use ($talk): object {
 $r=new ReflectionClass($class);
 return $r->newInstanceArgs(array_map(static function(ReflectionParameter $p) use ($talk) {
  if ($p->getName()==='appName') return 'cobudget';
  if ($p->getType()->getName()===OCP\IL10N::class) return Server::get(OCP\L10N\IFactory::class)->get('cobudget','en');
  return $p->getType()->getName()===TalkNotificationService::class ? $talk : Server::get($p->getType()->getName());
 },$r->getConstructor()->getParameters()));
};
$db->beginTransaction();
try {
 require_once __DIR__ . '/strict-lock-keys.php';
 enforceNativeLockKeyLength($db, $config);
 $now=time();
 $qb=$db->getQueryBuilder();$qb->update('cobudget_entries')->set('planned_notified',$qb->createNamedParameter(true))->executeStatement();
 foreach ([$uid,$other] as $id) {
  foreach (['notify_planned_payments','notify_planned_payments_talk',TalkNotificationService::TOKEN] as $key) $config->deleteUserValue($id,'cobudget',$key);
  $config->setUserValue($id,'cobudget','enable_projects','no');
  $config->setUserValue($id,'cobudget','enable_shared_projects','no');
 }
 $ws=$insert('workspaces',['user_id'=>$uid,'name'=>'__Planned notification fixture__']);
 $make=static fn(array $extra=[])=>$insert('entries',$extra+['workspace_id'=>$ws,'user_id'=>$uid,'created_by'=>$uid,'entry_kind'=>'personal','type'=>'expense','date'=>$now-60,'amount'=>'42.10','amount_cents'=>4210,'currency'=>'EUR','description'=>'SECRET DESCRIPTION','planned_notified'=>0]);
 $done=static function(int $id) use($db):bool {
  $q=$db->getQueryBuilder();$q->select('planned_notified')->from('cobudget_entries')->where($q->expr()->eq('id',$q->createNamedParameter($id)));
  $r=$q->executeQuery();$v=$r->fetchOne();$r->closeCursor();return(bool)$v;
 };
 $session->setUser($user);$controller=$fresh(UserController::class);$talkController=$fresh(TalkController::class);
 $check($controller->getSettings()->getData()['notify_planned_payments']===false && !$talkController->state()->getData()['planned_enabled'],'Both planned channels default off');
 $first=$make();$planned->processDue($now);$check($done($first)&&!$bell->sent&&!$backend->sent,'Default off completes silently');
 $controller->saveSettings(notify_planned_payments:true);
 $planned->processDue($now);$check(!$bell->sent,'Enabling later does not replay');
 $future=$make(['date'=>$now+120]);$planned->processDue($now);$check(!$done($future)&&!$bell->sent,'Future payment does not announce early');
 $planned->processDue($now+120);$check($done($future)&&count($bell->sent)===1,'First future payment announces at due instant');
 $n=$bell->sent[0];$check($n->getSubject()==='planned_payment_due'&&$n->getUser()===$uid,'Correct event and recipient');
 $check($n->getSubjectParameters()['amount']==='42,10'&&!str_contains(json_encode($n->getSubjectParameters()),'SECRET'),'Canonical amount without private description');
 $prepared=Server::get(OCA\CoBudget\Notification\Notifier::class)->prepare($n,'de');
 $check($prepared->getParsedSubject()==='Geplante Zahlung fällig'&&str_contains($prepared->getParsedMessage(),'42,10'),'Localized native notification');
 $planned->processDue($now+121);$check(count($bell->sent)===1,'Repeated cron does not duplicate');
 $check($talkController->update(true,'planned')->getStatus()===200,'Planned Talk enabled without area features');
 $check($talk->state($uid)['planned_enabled']&&!$talk->state($uid)['enabled'],'Talk opt-in independent from area event');
 $both=$make();$planned->processDue($now);$check($done($both)&&count($bell->sent)===2&&count($backend->sent)===1,'Both channels');
 $check(!str_contains($backend->sent[0]['message'],'SECRET')&&str_contains($backend->sent[0]['message'],'42,10'),'Talk privacy and amount');
 $controller->saveSettings(notify_planned_payments:false);
 $only=$make();$planned->processDue($now);$check($done($only)&&count($bell->sent)===2&&count($backend->sent)===2,'Talk only');
 $controller->saveSettings(notify_planned_payments:true);
 $backend->fail=true;$retry=$make();$planned->processDue($now);
 $check(!$done($retry)&&count($bell->sent)===3,'Talk failure leaves retry pending after bell success');
 $backend->fail=false;$planned->processDue($now);
 $check($done($retry)&&count($bell->sent)===3&&count($backend->sent)===3,'Retry sends only failed channel');
 $bell->fail=true;$reverse=$make();$planned->processDue($now);
 $check(!$done($reverse)&&count($backend->sent)===4,'Bell failure does not suppress Talk');
 $bell->fail=false;$planned->processDue($now);
 $check($done($reverse)&&count($bell->sent)===4&&count($backend->sent)===4,'Bell retry does not duplicate Talk');
 $backend->privateRoom=false;$missing=$make();$planned->processDue($now);$check(!$done($missing),'Changed chat stays pending');
 $talkController->update(false,'planned');$planned->processDue($now);$check($done($missing),'Opt-out stops pending retry');
 $backend->privateRoom=true;$talkController->update(true,'planned');
 $deleted=$make();$q=$db->getQueryBuilder();$q->delete('cobudget_entries')->where($q->expr()->eq('id',$q->createNamedParameter($deleted)))->executeStatement();
 $settled=$make(['is_settled'=>1]);$projection=$make(['source_entry_id'=>$first]);
 $before=[count($bell->sent),count($backend->sent)];$planned->processDue($now);
 $check($before===[count($bell->sent),count($backend->sent)]&&$done($settled)&&$done($projection),'Deleted, settled and projection rows do not notify');
 $project=$insert('projects',['owner_id'=>$uid,'workspace_id'=>$ws,'name'=>'__Shared planned fixture__','created_at'=>$now]);
 foreach([$uid,$other,'former:planned-fixture','missing-planned-fixture'] as $member) $insert('members',['project_id'=>$project,'user_id'=>$member,'share_basis_points'=>2500,'personal_workspace_id'=>$ws]);
 $config->setUserValue($other,'cobudget','notify_planned_payments','yes');
 $shared=$make(['entry_kind'=>'shared','project_id'=>$project]);$planned->processDue($now);
 $check(count($bell->sent)===$before[0]+2&&count($backend->sent)===$before[1]+1,'Shared source honors both active members preferences including creator');
 $check($bell->sent[array_key_last($bell->sent)]->getSubjectParameters()['projectId']===$project,'Shared context link');
 $planned->processDue($now);$check(count($bell->sent)===$before[0]+2,'Shared source repeat does not duplicate');
 // Force only the second member to need retry; successful creator channels remain completed.
 $backend->fail=true;$partial=$make(['entry_kind'=>'shared','project_id'=>$project]);$planned->processDue($now);
 $count=count($bell->sent);$backend->fail=false;$planned->processDue($now);
 $check($done($partial)&&count($bell->sent)===$count,'Partial shared retry preserves all successful recipients');
 // The real recurring job arms a newly committed occurrence; its current head must not notify again.
 $q=$db->getQueryBuilder();$q->update('cobudget_entries')->set('recurrence_interval',$q->createNamedParameter(null, \PDO::PARAM_NULL))->executeStatement();
 $clock=new class extends \OC\AppFramework\Utility\TimeFactory { public int $timestamp=0; public function getTime():int{return $this->timestamp;} };
 $due=(new DateTimeImmutable('tomorrow'))->setTime(6,0)->getTimestamp();$clock->timestamp=$due;
 $head=$make(['date'=>$now-86400,'planned_notified'=>1,'recurrence_interval'=>'month','recurrence_multiplier'=>1,'recurrence_next_date'=>$due]);
 $changes=Server::get(\OCA\CoBudget\Service\DataChangeService::class);
 (new ReflectionProperty($changes,'pushQueueResolved'))->setValue($changes,true);
 (new ReflectionProperty($changes,'pushQueue'))->setValue($changes,null);
 $recurring=new \OCA\CoBudget\Cron\RecurringEntriesJob($clock,$db,$logger,Server::get(\OCA\CoBudget\Service\HashtagService::class),Server::get(\OCA\CoBudget\Service\EntryProjectionService::class),$changes);
 (new ReflectionMethod($recurring,'run'))->invoke($recurring,null);
 $q=$db->getQueryBuilder();$q->select('id','planned_notified')->from('cobudget_entries')->where($q->expr()->eq('recurrence_parent_id',$q->createNamedParameter($head)));
 $r=$q->executeQuery();$child=$r->fetch();$r->closeCursor();
 $check($child&&!(bool)$child['planned_notified'],'Real generation arms new occurrence');
 $before=count($bell->sent);$planned->processDue($due-1);$check(count($bell->sent)===$before,'Generated occurrence respects saved due time');
 $planned->processDue($due);$check(count($bell->sent)===$before+1&&$done((int)$child['id']),'Generated repetition notifies exactly once');
 (new ReflectionMethod($recurring,'run'))->invoke($recurring,null);$planned->processDue($due);
 $check(count($bell->sent)===$before+1,'Repeated generation and notification jobs are idempotent');
 // Upgrade existing rows: historical payments and projections remain silent, future original becomes eligible.
 $old=$make(['planned_notified'=>1]);$oldFuture=$make(['planned_notified'=>1,'date'=>$now+864000]);
 $oldProjection=$make(['planned_notified'=>1,'date'=>$now+864000,'source_entry_id'=>$old]);
 $migration=new \OCA\CoBudget\Migration\Version000009Date20261104000000($db);
 $migration->postSchemaChange(new \OC\Migration\NullOutput(),static fn()=>null,[]);
 $check($done($old)&&!$done($oldFuture)&&$done($oldProjection),'Upgrade arms future originals only');
 $prefs=(new ReflectionClass(BackupService::class))->getConstant('SETTINGS_DEFAULTS');
 $check($prefs['notify_planned_payments']==='no'&&$prefs['notify_planned_payments_talk']==='no','Old backup defaults remain opt-out');
 $backup=Server::get(BackupService::class);
 $mapped=(new ReflectionMethod($backup,'applyUserMapToTables'))->invoke($backup,['cobudget_entries'=>[['user_id'=>$uid,'planned_delivery'=>json_encode([hash('sha256',$uid)=>['nextcloud'=>false,'talk'=>true]])]]],[$uid=>$other]);
 $check(isset(json_decode($mapped['cobudget_entries'][0]['planned_delivery'],true)[hash('sha256',$other)]),'Backup user remapping preserves channel progress');
 $progress=json_encode([hash('sha256',$uid)=>['nextcloud'=>false,'talk'=>true],hash('sha256','unrelated')=>['talk'=>true]]);
 $personal=(new ReflectionMethod($backup,'preparePersonalImportTables'))->invoke($backup,[
  'cobudget_workspaces'=>[['id'=>$ws,'user_id'=>$uid,'name'=>'Fixture']],
  'cobudget_entries'=>[['id'=>$first,'user_id'=>$uid,'created_by'=>$uid,'workspace_id'=>$ws,'entry_kind'=>'personal','type'=>'expense','amount_cents'=>4210,'amount'=>'42.10','date'=>$now,'currency'=>'EUR','planned_notified'=>0,'planned_delivery'=>$progress]],
 ],$other,$uid);
 $progress=json_decode($personal['cobudget_entries'][0]['planned_delivery'],true);
 $check(array_keys($progress)===[hash('sha256',$other)]&&$progress[hash('sha256',$other)]['talk'],'Personal import remaps only own pending channel progress');
 $oldArchive=['user_id'=>$uid,'created_by'=>$uid,'workspace_id'=>$ws,'entry_kind'=>'personal','type'=>'expense','amount_cents'=>100,'amount'=>'1.00','date'=>$now+864000,'currency'=>'EUR'];
 $restoredId=(new ReflectionMethod($backup,'insertRowGenerated'))->invoke($backup,'cobudget_entries',$oldArchive);
 $check(!$done($restoredId),'Old personal archive future payment is armed');
 $oldArchive['date']=$now-86400;
 $restoredPast=(new ReflectionMethod($backup,'insertRowGenerated'))->invoke($backup,'cobudget_entries',$oldArchive);
 $check($done($restoredPast),'Old personal archive past payment stays silent');
 $check(!str_contains(json_encode($logger->records),'PRIVATE TRANSPORT ERROR'),'Logs omit private transport details');
 echo "Planned notifications: $assertions assertions passed (all writes rolled back, no real messages).\n";
} finally {
 $db->rollBack();$session->setUser($original);
}
