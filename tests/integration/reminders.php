<?php
declare(strict_types=1);

// Rollback-only reminder/settings integration. Both transports are in-memory fakes:
// no notification, push, Talk message, room or account is created.
$options = getopt('', ['nextcloud:', 'user:', 'other-user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'], $options['other-user'])) {
 fwrite(STDERR, "Usage: php tests/integration/reminders.php --nextcloud=/path/to/server --user=admin --other-user=testuser\n"); exit(1);
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
 public ?Closure $onNotify = null;
 public function __construct(private OCP\Notification\IManager $factory) {}
 public function createNotification(): INotification { return $this->factory->createNotification(); }
 public function notify(INotification $notification): void {
  if ($this->fail) throw new RuntimeException('PRIVATE TRANSPORT ERROR');
  $this->sent[] = $notification;
  if ($this->onNotify) { $hook=$this->onNotify; $this->onNotify=null; $hook(); }
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
 $qb=$db->getQueryBuilder();$qb->delete('cobudget_reminders')->executeStatement();
 // Suppress existing due rows only inside this transaction; never send their contents.
 $qb=$db->getQueryBuilder();$qb->update('cobudget_entries')->set('planned_notified',$qb->createNamedParameter(true))->executeStatement();
 $qb=$db->getQueryBuilder();$qb->update('cobudget_entries')->set('reminder_notified',$qb->createNamedParameter(true))->executeStatement();
 foreach ([$uid,$other] as $id) {
  foreach (['notify_reminders','notify_reminders_talk','notify_project_entries_talk',TalkNotificationService::TOKEN] as $key) $config->deleteUserValue($id,'cobudget',$key);
  $config->setUserValue($id,'cobudget','enable_projects','no');
  $config->setUserValue($id,'cobudget','enable_shared_projects','no');
 }
 $session->setUser($user);$controller=$fresh(UserController::class);$talkController=$fresh(TalkController::class);
 $check($controller->getSettings()->getData()['notify_reminders']===true,'Nextcloud reminders default on');
 $config->deleteUserValue($uid,'cobudget','enable_reminders');
 $check($controller->getSettings()->getData()['enable_reminders']===true,'Reminder UI defaults on');
 $check($controller->saveSettings(enable_reminders:false)->getStatus()===200,'Reminder visibility can be disabled');
 $check($controller->getSettings()->getData()['enable_reminders']===false,'Reminder visibility persists');
 $check($controller->getSettings()->getData()['notify_reminders']===true,'Hiding reminder UI preserves notification preferences');
 $state=$talkController->state()->getData();
 $check(!$state['enabled'] && !$state['reminders_enabled'],'Both Talk opt-ins default off');
 $ws=$insert('workspaces',['user_id'=>$uid,'name'=>'__Reminder fixture__']);
 $make = static fn(array $extra=[]) => $insert('entries', $extra + ['workspace_id'=>$ws,'user_id'=>$uid,'entry_kind'=>'personal','type'=>'expense','date'=>time()-120,'amount'=>'12.34','amount_cents'=>1234,'currency'=>'EUR','description'=>'SECRET DESCRIPTION','reminder_text'=>'Pay @all **rent**','reminder_date'=>time()-60,'reminder_notified'=>0]);
 $entry=$make();$run();
 $check(count($bell->sent)===1 && count($backend->sent)===0 && $notified($entry),'Hidden reminder UI still sends a Nextcloud reminder');
 $run();$check(count($bell->sent)===1,'Normal repeat cron does not resend');
 $check($talkController->update(true,'reminders')->getStatus()===200,'Reminder opt-in succeeds without areas');
 $check($talk->state($uid)['reminders_enabled'] && !$talk->state($uid)['enabled'],'Reminder opt-in does not enable area notifications');
 $talkController->update(true,'reminders');$check($backend->created===1,'Repeated opt-in reuses the private chat');
 $check($talkController->update(true,'unknown')->getStatus()===400,'Unknown channels are rejected');
 $controller->saveSettings(notify_reminders:false);
 $entry=$make();$run();
 $check(count($bell->sent)===1 && count($backend->sent)===1 && $notified($entry),'Talk-only reminders work without areas');
 $message=$backend->sent[0]['message'];
 $check(!str_contains($message,'SECRET DESCRIPTION') && !str_contains($message,'@all') && str_contains($message,'\\*\\*rent\\*\\*'),'Talk includes escaped reminder text, never description/mentions');
 $check(str_contains($message,'12,34') && str_contains($message,'EUR') && str_contains($message,'cobudget'),'Talk contains amount, currency and app link');
 $controller->saveSettings(notify_reminders:true);$entry=$make();$run();
 $check(count($bell->sent)===2 && count($backend->sent)===2,'Both channels can be enabled');
 $controller->saveSettings(notify_reminders:false);$talkController->update(false,'reminders');$entry=$make();$run();
 $check(count($bell->sent)===2 && count($backend->sent)===2 && $notified($entry),'Both disabled consumes the due reminder without delivery');
 $talkController->update(true,'reminders');$run();$check(count($backend->sent)===2,'Opt-in does not replay previously processed reminders');
 $future=$make(['reminder_date'=>time()+86400]);$run();$check(!$notified($future) && count($backend->sent)===2,'Future reminders are not sent early');
 $update($future,['reminder_date'=>time()-1]);$run();$check($notified($future) && count($backend->sent)===3,'Due timestamp is respected');
 $update($future,['reminder_date'=>time()-120,'reminder_notified'=>0]);$run();$check(count($backend->sent)===4,'Explicit rescheduling allows a new reminder');
 $controller->saveSettings(notify_reminders:true);$backend->fail=true;$entry=$make();$run();
 $check(count($bell->sent)===3 && count($backend->sent)===4 && $notified($entry),'Talk transport failure does not suppress the bell or repeat it on later runs');
 $backend->fail=false;$backend->privateRoom=false;$entry=$make();$run();
 $check(count($bell->sent)===4 && count($backend->sent)===4 && $backend->created===1,'Changed chat is not recreated during delivery');
 $backend->privateRoom=true;$backend->enabled=false;$entry=$make();$run();
 $check(count($bell->sent)===5 && count($backend->sent)===4,'Missing Talk leaves Nextcloud reminders working');
 $backend->enabled=true;$bell->fail=true;$entry=$make();$run();
 $check(!$notified($entry) && count($backend->sent)===4,'Bell failure retains existing cron retry semantics without sending this Talk reminder ahead of retry');
 $bell->fail=false;$run();$check($notified($entry) && count($bell->sent)===6 && count($backend->sent)===5,'Bell retry delivers both channels');
 $check(!str_contains(json_encode($logger->records),'PRIVATE TRANSPORT ERROR'),'Logs omit transport messages');
 // Shared reminder: each active member's own preference, including the creator.
 $project=$insert('projects',['name'=>'__Reminder shared__','owner_id'=>$uid,'workspace_id'=>$ws,'created_at'=>time()]);
 $peerWs=$insert('workspaces',['user_id'=>$other,'name'=>'__Reminder peer fixture__']);
 foreach ([$uid,$other,'former:reminder-test','__missing_reminder_user__'] as $id) $insert('members',['project_id'=>$project,'user_id'=>$id,'share_basis_points'=>2500,'personal_workspace_id'=>$id===$other?$peerWs:$ws]);
 $controller->saveSettings(notify_reminders:false);$talk->setEnabled($uid,true,'reminders');
 $config->setUserValue($other,'cobudget','notify_reminders','yes');
 $entry=$make(['entry_kind'=>'shared','project_id'=>$project]);$run();
 $check(count($bell->sent)===7 && $bell->sent[6]->getUser()===$other,'Shared bell targets the active member with bell enabled');
 $check(count($backend->sent)===6 && $backend->sent[5]['user']===$uid && str_contains($backend->sent[5]['message'],'#/reminders?reminder='),'Shared Talk uses the creator’s own preference and personal reminder link');
 $check($notified($entry),'Shared source is processed once; missing/former users excluded');
 // Existing project Talk preference still remains separate.
 $talk->setEnabled($uid,true);$talk->setEnabled($uid,false,'reminders');
 $check($talk->state($uid)['enabled'] && !$talk->state($uid)['reminders_enabled'],'Disabling reminders retains shared-payment Talk');
 $created=$backend->created;$backend->enabled=false;
 $check($talkController->update(true,'reminders')->getStatus()===503 && !$talk->state($uid)['reminders_enabled'],'Failed opt-in retains saved preference');
 $check($backend->created===$created,'Unavailable Talk creates no room');
 $backup=Server::get(BackupService::class);$normalize=new ReflectionMethod($backup,'normalizeUserSettings');
 $defaults=$normalize->invoke($backup,[]);$custom=$normalize->invoke($backup,['notify_reminders'=>'no','notify_reminders_talk'=>'yes']);
 $check($defaults['notify_reminders']==='yes' && $defaults['notify_reminders_talk']==='no','Old backups receive safe defaults');
 $check($custom['notify_reminders']==='no' && $custom['notify_reminders_talk']==='yes','Backups retain separate channel choices');
 $keys=(new ReflectionClass(UserResetService::class))->getConstant('SETTINGS_KEYS');
 $check(in_array('notify_reminders',$keys,true) && in_array('notify_reminders_talk',$keys,true),'Reset clears both preferences');
 $backend->enabled=true;
 $config->setUserValue($uid,'cobudget','enable_projects','yes');
 $config->setUserValue($uid,'cobudget','enable_shared_projects','yes');
 $before=count($backend->sent);
 $createdParams=['actorName'=>'Someone','amount'=>'42,00','currency'=>'EUR','projectName'=>'Area','type'=>'expense'];
 $talk->notifyEntryCreated($uid,$createdParams,'https://cloud.test/area');
 $check(count($backend->sent)===$before+1,'Existing shared-payment delivery still works with reminders disabled');
 $config->setUserValue($uid,'cobudget','enable_projects','no');
 $talk->notifyEntryCreated($uid,$createdParams,'https://cloud.test/area');
 $check(count($backend->sent)===$before+1,'Existing shared-payment delivery retains its feature gates');
 $session->setUser(null);
 $check($fresh(UserController::class)->saveSettings(notify_reminders:false)->getStatus()===401,'Unauthenticated bell setting rejected');
 $check($fresh(TalkController::class)->update(true,'reminders')->getStatus()===401,'Unauthenticated Talk setting rejected');
 // Personal organizer: real database, fake transports, no external delivery.
 $session->setUser($user);
 $organizer=Server::get(\OCA\CoBudget\Service\ReminderService::class);
 $expect=static function(string $type, callable $fn, string $message)use($check):void {try{$fn();}catch(Throwable $e){$check($e instanceof $type,$message.' ('.get_class($e).')');return;}throw new RuntimeException($message);};
 $draft=['title'=>'Cancel test subscription','description'=>"Contract 123\nCancel by December",'remind_at'=>time()+86400,'due_date'=>'2026-12-31','labels'=>['cancel'],'keep_open'=>true];
 $free=$organizer->save($uid,null,$draft);
 $check(count($organizer->paymentChoices($uid,'SECRET'))<=100,'Payment search is bounded and available');
 $check($free['entry_id']===null&&$free['description']===$draft['description']&&$free['labels']===['cancel'],'Independent reminder preserves description and labels');
 $expect(OutOfBoundsException::class,fn()=>$organizer->save($other,$free['id'],$draft+['revision'=>$free['revision']]),'Foreign reminder edit denied');
 $expect(OutOfBoundsException::class,fn()=>$organizer->remove($other,$free['id'],$free['revision']),'Foreign reminder deletion denied');
 $expect(OutOfBoundsException::class,fn()=>$organizer->setStatus($other,$free['id'],'completed',$free['revision']),'Foreign completion denied');
 $expect(InvalidArgumentException::class,fn()=>$organizer->save($uid,null,array_replace($draft,['labels'=>[['bad']]])),'Nested label rejected');
 $expect(InvalidArgumentException::class,fn()=>$organizer->save($uid,null,array_replace($draft,['due_date'=>'2027-02-30'])),'Invalid deadline rejected');
 $expect(InvalidArgumentException::class,fn()=>$organizer->save($uid,null,array_replace($draft,['title'=>[]])),'Non-string title rejected');
 $typed=$organizer->save($uid,null,array_replace($draft,['labels'=>['cancel','subscription']]));
 $check($typed['labels']===['cancel','subscription'],'Independent reminders support subscription and action labels together');
 $organizer->remove($uid,$typed['id'],$typed['revision']);
 $edited=$organizer->save($uid,$free['id'],$draft+['revision'=>$free['revision']]);
 $expect(DomainException::class,fn()=>$organizer->save($uid,$free['id'],$draft+['revision'=>$free['revision']]),'Stale draft cannot overwrite a newer edit');
 $organizer->setStatus($uid,$free['id'],'completed',$edited['revision']);$done=$organizer->rows($uid)[$free['id']];
 $check($done['status']==='completed','Completion persists');
 $organizer->setStatus($uid,$free['id'],'active',$done['revision']);$active=$organizer->rows($uid)[$free['id']];
 $check($active['status']==='active','Future completed reminder can reopen');
 $old=$organizer->save($uid,null,array_replace($draft,['remind_at'=>time()-15*86400,'keep_open'=>false]));
 $check($old['status']==='archived','Ordinary reminders auto-archive after 14 days');
 $old=$organizer->save($uid,$old['id'],array_replace($draft,['remind_at'=>$old['remind_at'],'keep_open'=>false,'revision'=>$old['revision'],'description'=>'Updated archived note']));
 $check($old['status']==='archived'&&$old['description']==='Updated archived note','Archived notes can be edited without reactivation');
 $task=$organizer->save($uid,null,array_replace($draft,['remind_at'=>time()-15*86400]));
 $check($task['status']==='active','Keep-open tasks remain active after 14 days');
 $expect(InvalidArgumentException::class,fn()=>$organizer->setStatus($uid,$old['id'],'active',$old['revision']),'Past archived reminder needs a new time');
 $reopened=$organizer->save($uid,$old['id'],$draft+['revision'=>$old['revision']]);$check($reopened['status']==='active','New future date reactivates archived reminder');
 $organizer->setStatus($uid,$free['id'],'archived',$active['revision']);$check($organizer->rows($uid)[$free['id']]['status']==='archived','Manual archive persists');
 // Sidebar archive switch: atomic notes/status save, reversible with a future date.
 $toggle=$organizer->save($uid,null,$draft);
 $toggle=$organizer->save($uid,$toggle['id'],array_replace($draft,['revision'=>$toggle['revision'],'archived'=>true,'description'=>'Archived notes']));
 $check($toggle['status']==='archived'&&$toggle['description']==='Archived notes','Archive switch saves status and notes together');
 $toggle=$organizer->save($uid,$toggle['id'],array_replace($draft,['revision'=>$toggle['revision'],'archived'=>false]));
 $check($toggle['status']==='active','Archive switch can restore a future reminder');
 $toggle=$organizer->save($uid,$toggle['id'],array_replace($draft,['revision'=>$toggle['revision'],'archived'=>true,'remind_at'=>time()-86400]));
 $check($toggle['status']==='archived','Archiving a past reminder requires no future date');
 $expect(InvalidArgumentException::class,fn()=>$organizer->save($uid,$toggle['id'],array_replace($draft,['revision'=>$toggle['revision'],'archived'=>false,'remind_at'=>$toggle['remind_at']])),'Unarchiving a past reminder requires a future date');
 $toggle=$organizer->save($uid,$toggle['id'],array_replace($draft,['revision'=>$toggle['revision'],'archived'=>true,'remind_at'=>$toggle['remind_at'],'description'=>'Updated archived notes']));
 $check($toggle['status']==='archived'&&$toggle['description']==='Updated archived notes','Editing archived notes keeps archive status');
 $toggle=$organizer->save($uid,$toggle['id'],array_replace($draft,['revision'=>$toggle['revision'],'archived'=>false,'completed'=>true,'remind_at'=>$toggle['remind_at']]));
 $check($toggle['status']==='completed'&&empty($toggle['archived_at']),'Completion replaces manual archive status');
 $completedAt=$toggle['completed_at'];
 $organizer->setStatus($uid,$toggle['id'],'archived',$toggle['revision']);
 $toggle=$organizer->rows($uid)[$toggle['id']];
 $check($toggle['status']==='archived'&&$toggle['completed_at']===$completedAt&&!empty($toggle['archived_at']),'Archiving preserves completion and exposes both timestamps');
 $archivedAt=$toggle['archived_at'];
 $organizer->setStatus($uid,$toggle['id'],'incomplete',$toggle['revision']);
 $toggle=$organizer->rows($uid)[$toggle['id']];
 $check($toggle['status']==='archived'&&!$toggle['completed_at']&&$toggle['archived_at']===$archivedAt,'Unchecking a past archived reminder preserves archival');
 $organizer->setStatus($uid,$toggle['id'],'completed',$toggle['revision']);
 $toggle=$organizer->rows($uid)[$toggle['id']];
 $check($toggle['completed_at']&&$toggle['archived_at']===$archivedAt,'Completing an archived reminder preserves archival');
 $toggle=$organizer->save($uid,$toggle['id'],array_replace($draft,['revision'=>$toggle['revision'],'archived'=>true,'completed'=>true,'remind_at'=>$toggle['remind_at']]));
 $check($toggle['status']==='archived'&&$toggle['completed_at']&&$toggle['archived_at']===$archivedAt,'Sidebar saves both states together without resetting dates');
 $organizer->remove($uid,$toggle['id'],$toggle['revision']);
 $automatic=$organizer->save($uid,null,array_replace($draft,['remind_at'=>time()-15*86400,'keep_open'=>false]));
 $automaticAt=$automatic['remind_at']+14*86400;
 $check($automatic['effective_archived_at']===$automaticAt,'Automatic archive tooltip uses the actual threshold date');
 $organizer->setStatus($uid,$automatic['id'],'completed',$automatic['revision']);
 $automatic=$organizer->rows($uid)[$automatic['id']];
 $check($automatic['status']==='archived'&&$automatic['completed_at']&&(int)$automatic['archived_at']===$automaticAt,'Completing an automatically archived item preserves its archive date');
 $organizer->remove($uid,$automatic['id'],$automatic['revision']);
 // Completion starts its own 14-day clock; reads do not persist archive state or revisions.
 $archiveNow=time();
 foreach ([
  ['completed_at'=>$archiveNow-13*86400, 'remind_at'=>$archiveNow-30*86400, 'keep_open'=>false, 'status'=>'completed'],
  ['completed_at'=>$archiveNow-15*86400, 'remind_at'=>$archiveNow+30*86400, 'keep_open'=>false, 'status'=>'archived'],
  ['completed_at'=>$archiveNow-15*86400, 'remind_at'=>$archiveNow-30*86400, 'keep_open'=>true, 'status'=>'completed'],
 ] as $case) {
  $completed=$organizer->save($uid,null,array_replace($draft,['remind_at'=>$case['remind_at'],'keep_open'=>$case['keep_open'],'completed'=>true]));
  $qb=$db->getQueryBuilder();
  $qb->update('cobudget_reminders')->set('completed_at',$qb->createNamedParameter($case['completed_at']))
   ->where($qb->expr()->eq('id',$qb->createNamedParameter($completed['id'])))->executeStatement();
  $row=$organizer->rows($uid)[$completed['id']];
  $check($row['status']===$case['status'],'Completed archival uses completion age and respects opt-out');
  $check((int)$row['completed_at']===$case['completed_at']&&$row['revision']===$completed['revision']&&!$row['archived_at'],'Derived archival preserves completion and does not mutate records');
  $expected=$case['status']==='archived'?$case['completed_at']+14*86400:null;
  $check($row['effective_archived_at']===$expected,'Completed archive tooltip shows completion plus 14 days');
  if ($expected!==null) {
   $organizer->setStatus($uid,$row['id'],'incomplete',$row['revision']);
   $row=$organizer->rows($uid)[$row['id']];
   $check($row['status']==='archived'&&!$row['completed_at']&&(int)$row['archived_at']===$expected,'Unchecking automatically archived completion preserves its original archive date');
  }
  $organizer->remove($uid,$row['id'],$row['revision']);
 }
 // Same shared source, separate notes/completion; overview writes never alter money.
 $shared=$make(['entry_kind'=>'shared','project_id'=>$project,'reminder_date'=>time()+172800,'is_subscription'=>1]);
 $my=array_values(array_filter($organizer->rows($uid),fn($r)=>$r['entry_id']===$shared))[0];
 $theirs=array_values(array_filter($organizer->rows($other),fn($r)=>$r['entry_id']===$shared))[0];
 $check(in_array('subscription',$my['payment_labels'],true),'Payment labels are inherited');
 $organizer->setStatus($uid,$my['id'],'completed',$my['revision']);
 $check($organizer->rows($other)[$theirs['id']]['status']==='active','Shared completion is personal');
 $peerDraft=$draft+['revision'=>$theirs['revision']];$peerDraft['description']='Private peer note';$peerDraft['remind_at']=$theirs['remind_at'];
 $peerReminder=$organizer->save($other,$theirs['id'],$peerDraft);
 $check($peerReminder['payment_workspace_id']===$peerWs,'Shared payment link uses the recipient workspace, not the source owner workspace');
 $check($peerReminder['description']==='Private peer note'&&($organizer->rows($uid)[$my['id']]['description']??'')==='','Personal notes are not shared');
 $q=$db->getQueryBuilder();$result=$q->select('amount_cents','user_id','reminder_text')->from('cobudget_entries')->where($q->expr()->eq('id',$q->createNamedParameter($shared)))->executeQuery();$source=$result->fetch();$result->closeCursor();
 $q=$db->getQueryBuilder();$result=$q->select('*')->from('cobudget_entry_history')->where($q->expr()->eq('entry_id',$q->createNamedParameter($shared)))->executeQuery();$history=$result->fetchAll();$result->closeCursor();
 $check(count($history)===1&&$history[0]['changed_by']===$other&&$history[0]['field']==='reminder_text'&&!str_contains(json_encode($history),'Private peer note'),'Payment history records reminder changes without private notes');
 $check((int)$source['amount_cents']===1234&&$source['user_id']===$uid&&$source['reminder_text']===$draft['title'],'Shared reminder edit changes title only, preserves owner and money');
 $mine=$organizer->rows($uid)[$my['id']];$organizer->remove($uid,$mine['id'],$mine['revision']);
 $check(!isset($organizer->rows($uid)[$my['id']])&&isset($organizer->rows($other)[$theirs['id']]),'Deleting shared reminder hides it only for this person');
 $check(!isset($organizer->rows($uid)[$my['id']]),'Dismissed reminder is not recreated on refresh');
 $update($shared,['reminder_date'=>time()+259200]);$check(isset($organizer->rows($uid)[$my['id']]),'A newly scheduled shared reminder is visible again');
 $q=$db->getQueryBuilder();$q->delete('cobudget_members')->where($q->expr()->eq('project_id',$q->createNamedParameter($project)))->andWhere($q->expr()->eq('user_id',$q->createNamedParameter($other)))->executeStatement();
 $check(!isset($organizer->rows($other)[$theirs['id']]),'Revoked membership removes personal reminder access');
 $expect(OutOfBoundsException::class,fn()=>$organizer->save($other,null,$draft+['entry_id'=>$shared]),'Cannot link a payment outside membership');
 $update($shared,['reminder_date'=>null]);$check(!isset($organizer->rows($uid)[$my['id']]),'Clearing payment reminder clears personal state');
 // New linked reminder, immutability of its reference, duplicate protection.
 $bare=$make(['reminder_date'=>null]);$linked=$organizer->save($uid,null,$draft+['entry_id'=>$bare]);
 $expect(DomainException::class,fn()=>$organizer->save($uid,null,$draft+['entry_id'=>$bare]),'Only one reminder per payment and person');
 $check($linked['payment_workspace_id']===$ws,'Personal payment link uses its own workspace');
 $linked=$organizer->save($uid,$linked['id'],$draft+['entry_id'=>$shared,'revision'=>$linked['revision']]);
 $check($linked['entry_id']===$bare,'Existing reference cannot be silently changed');
 $q=$db->getQueryBuilder();$q->delete('cobudget_entries')->where($q->expr()->eq('id',$q->createNamedParameter($bare)))->executeStatement();
 $check(!isset($organizer->rows($uid)[$linked['id']]),'Deleted payment removes linked reminder');
 $tables=(new ReflectionMethod($backup,'collectBackupData'))->invoke($backup,$uid)['tables'];
 $saved=array_column($tables['cobudget_reminders'],null,'id');
 $check(isset($saved[$task['id']])&&$saved[$task['id']]['description']===$draft['description'],'Personal export contains standalone notes');
 $mapped=(new ReflectionMethod($backup,'remapGeneratedRowReferences'))->invoke($backup,'cobudget_reminders',['id'=>999,'user_id'=>$uid,'entry_id'=>123,'title'=>'x'],['cobudget_entries'=>[123=>456]]);
 $check($mapped['entry_id']===456&&!isset($mapped['id']),'Personal restore remaps reminder payment IDs');
 $snapshot=$saved[$task['id']];$snapshot['completed_at']=time();
 $idMaps=(new ReflectionMethod($backup,'insertTablesWithGeneratedIds'))->invoke($backup,['cobudget_reminders'=>[$snapshot]]);
 $restoredId=$idMaps['cobudget_reminders'][$task['id']];$restored=$organizer->rows($uid)[$restoredId];
 $check($restored['description']===$draft['description']&&$restored['status']==='completed'&&$restored['labels']===['cancel'],'Personal restore round-trip preserves reminder data and completion');
 $organizer->remove($uid,$restoredId,$restored['revision']);
 $snapshot['id']=$restoredId;(new ReflectionMethod($backup,'insertTables'))->invoke($backup,['cobudget_reminders'=>[$snapshot]]);
 $restored=$organizer->rows($uid)[$restoredId];$check($restored['id']===$restoredId&&$restored['description']===$draft['description'],'Full restore round-trip preserves reminder IDs and notes');
 $organizer->remove($uid,$restoredId,$restored['revision']);
 $expect(InvalidArgumentException::class,fn()=>(new ReflectionMethod($backup,'assertBackupInternalReferences'))->invoke($backup,['cobudget_entries'=>[['id'=>987,'entry_kind'=>'personal','user_id'=>$other]],'cobudget_reminders'=>[array_replace($snapshot,['entry_id'=>987])]]),'Restore rejects foreign personal payment references');
 $check((new ReflectionMethod($backup,'personalImportBlockingTable'))->invoke($backup,$uid)!==null,'Existing organizer data blocks unsafe personal restore');
 // Send independent reminder only once, and never send an already completed one.
 $controller->saveSettings(notify_reminders:true);$talk->setEnabled($uid,false,'reminders');
 $organizer->setStatus($uid,$task['id'],'completed',$task['revision']);
 $pending=$organizer->save($uid,null,array_replace($draft,['remind_at'=>time()-60]));
 $before=count($bell->sent);$run();
 $check(count($bell->sent)===$before+1&&$bell->sent[$before]->getSubject()==='organizer_reminder','Independent reminder uses existing bell preference');
 $run();$check(count($bell->sent)===$before+1,'Independent reminders are not repeated on another cron run');
 $reminderController=$fresh(\OCA\CoBudget\Controller\ReminderController::class);
 $session->setUser(null);
 $check($reminderController->index()->getStatus()===401&&$reminderController->save($draft)->getStatus()===401&&$reminderController->destroy($free['id'],1)->getStatus()===401,'Organizer API requires authentication');
 $session->setUser($user);
 $organizer->remove($uid,$pending['id'],$pending['revision']);$check(!isset($organizer->rows($uid)[$pending['id']]),'Standalone deletion is permanent');

 $raced=$make();$later=time()+86400;
 $bell->onNotify=static function()use($update,$raced,$later):void{$update($raced,['reminder_date'=>$later,'reminder_notified'=>false]);};
 $run();$check(!$notified($raced),'Concurrent rescheduling is not consumed by the old delivery');
 $racedReminder=array_values(array_filter($organizer->rows($uid),fn($r)=>$r['entry_id']===$raced))[0];
 $check($racedReminder['remind_at']===$later&&!$racedReminder['notified'],'New schedule retains its pending personal delivery');
 echo "Reminder integration passed: {$assertions} assertions; no notifications sent; fixtures rolled back.\n";
} finally {
 $db->rollBack();$session->setUser($original);
}
