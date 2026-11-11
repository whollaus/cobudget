<?php
declare(strict_types=1);
// Real DAV integration, all database writes rolled back; future alarms, no attendees/invitations.
$options = getopt('', ['nextcloud:', 'user:', 'other-user:']);
if (!isset($options['nextcloud'], $options['user'], $options['other-user'])) exit("Usage: --nextcloud=PATH --user=admin --other-user=testuser\n");
$root=realpath($options['nextcloud']); define('OC_CONSOLE',true); $_SERVER['SCRIPT_FILENAME']=__FILE__; chdir($root); require $root.'/lib/base.php'; OC_App::loadApps();
set_exception_handler(static function(Throwable $e):void {fwrite(STDERR,$e."\n");exit(1);});
use OCP\Server;
use OCA\CoBudget\Service\CalendarReminderService;
use OCA\CoBudget\Service\ReminderCalendarBackend;
$db=Server::get(OCP\IDBConnection::class);$config=Server::get(OCP\IConfig::class);$session=Server::get(OCP\IUserSession::class);$original=$session->getUser();$users=Server::get(OCP\IUserManager::class);
$uid=$options['user'];$other=$options['other-user'];$user=$users->get($uid);$peer=$users->get($other);if(!$user||!$peer||$uid===$other)throw new RuntimeException('Two different existing users required');
$service=Server::get(CalendarReminderService::class);$backend=Server::get(ReminderCalendarBackend::class);$dav=Server::get(OCA\DAV\CalDAV\CalDavBackend::class);
$n=0;$check=static function($ok,$msg)use(&$n){if(!$ok)throw new RuntimeException($msg);$n++;};
$insert=static function($table,$data)use($db){$q=$db->getQueryBuilder();$q->insert('cobudget_'.$table);foreach($data as $k=>$v)$q->setValue($k,$q->createNamedParameter($v));$q->executeStatement();return(int)$q->getLastInsertId();};
$update=static function($id,$data)use($db){$q=$db->getQueryBuilder();$q->update('cobudget_entries');foreach($data as $k=>$v)$q->set($k,$q->createNamedParameter($v));$q->where($q->expr()->eq('id',$q->createNamedParameter($id)))->executeStatement();};
$links=static function($id)use($db){$q=$db->getQueryBuilder();$r=$q->select('*')->from('cobudget_calendar_links')->where($q->expr()->eq('entry_id',$q->createNamedParameter($id)))->executeQuery();$rows=$r->fetchAll();$r->closeCursor();return$rows;};
$object=static fn($link)=>$dav->getCalendarObject((int)$link['calendar_id'],$link['object_uri']);
$db->beginTransaction();try{
 require_once __DIR__ . '/strict-lock-keys.php';
 enforceNativeLockKeyLength($db, $config);
 $session->setUser($user);
 $q=$db->getQueryBuilder();$q->delete('cobudget_reminders')->executeStatement();
 // Isolate existing reminders and operational links inside the rollback transaction.
 $q=$db->getQueryBuilder();$q->update('cobudget_entries')->set('reminder_date',$q->createNamedParameter(null))->executeStatement();
 $q=$db->getQueryBuilder();$q->delete('cobudget_calendar_links')->executeStatement();
 foreach([$uid,$other]as$id){$config->deleteUserValue($id,'cobudget',CalendarReminderService::ENABLED);$config->deleteUserValue($id,'cobudget',CalendarReminderService::TARGET);}
 $createCal=static fn($id)=>(string)$dav->createCalendar('principals/users/'.$id,'cobudget-test-'.bin2hex(random_bytes(6)),['{DAV:}displayname'=>'CoBudget rollback test']);
 $cal=$createCal($uid);$cal2=$createCal($uid);$peerCal=$createCal($other);
 $check(in_array($cal,array_column($backend->choices($uid),'id'),true),'Native calendar is writable choice');
 $check(!in_array($peerCal,array_column($backend->choices($uid),'id'),true),'Foreign calendar excluded');
 $check(!$service->state($uid)['enabled'],'Calendar default off');
 try{$service->configure($uid,true,$peerCal);throw new RuntimeException('Foreign target accepted');}catch(InvalidArgumentException $e){$n++;}
 $ws=$insert('workspaces',['user_id'=>$uid,'name'=>'Calendar rollback test']);
 $date=(new DateTimeImmutable('2030-03-31 08:00:00',new DateTimeZone('Europe/Vienna')))->getTimestamp();
 $make=static fn($extra=[])=>$insert('entries',$extra+['workspace_id'=>$ws,'user_id'=>$uid,'entry_kind'=>'personal','type'=>'expense','date'=>time(),'amount'=>'100.00','amount_cents'=>10000,'currency'=>'EUR','description'=>'SECRET PAYMENT','reminder_text'=>'Cancel subscription','reminder_date'=>$date]);
 $id=$make();$past=$make(['reminder_date'=>time()-86400]);
 $state=$service->configure($uid,true,$cal);$check(!$state['sync_pending'],'Native creation succeeds');$rows=$links($id);$check(count($rows)===1,'One link created');$link=$rows[0];
 $v=Sabre\VObject\Reader::read($object($link)['calendardata']);
 $check((string)$v->VEVENT->SUMMARY==='Cancel subscription','Reminder text used');
 $check($v->VEVENT->DTSTART->getDateTime()->getTimestamp()===$date,'Timezone / DST instant preserved');
 $check((string)$v->VEVENT->VALARM->TRIGGER==='PT0S','Alert at reminder time');
 $check(!str_contains($v->serialize(),'SECRET PAYMENT')&&!isset($v->VEVENT->ATTENDEE),'No financial details or attendees');
 $check($links($past)===[],'Historical reminders not backfilled');
 $service->syncUser($uid);$check(count($links($id))===1&&$links($id)[0]['event_uid']===$link['event_uid'],'Idempotent synchronization');
 $update($id,['reminder_text'=>'Updated reminder','reminder_date'=>$date+3600]);$service->syncUser($uid);$v=Sabre\VObject\Reader::read($object($link)['calendardata']);
 $check((string)$v->VEVENT->SUMMARY==='Updated reminder'&&$v->VEVENT->DTSTART->getDateTime()->getTimestamp()===$date+3600,'Existing event updated');
 $service->configure($uid,true,$cal2);$check(count($links($id))===1&&$links($id)[0]['calendar_id']===$cal2,'Target move creates new event');
 $old=$object($link);$check(!$old||!empty($old['{http://nextcloud.com/ns}deleted-at']),'Old target event removed');
 $update($id,['reminder_date'=>null]);$service->syncUser($uid);$check($links($id)===[],'Clearing reminder removes event');
 $update($id,['reminder_date'=>$date]);$service->syncUser($uid);$check(count($links($id))===1,'Restoring reminder creates event');
 $service->configure($uid,false,$cal2);$check($links($id)===[],'Disabling removes managed events');
 $project=$insert('projects',['owner_id'=>$uid,'name'=>'Calendar shared fixture','workspace_id'=>$ws,'created_at'=>time()]);
 foreach([$uid,$other]as$u)$insert('members',['project_id'=>$project,'user_id'=>$u,'share_basis_points'=>5000,'personal_workspace_id'=>$ws]);
 $shared=$make(['entry_kind'=>'shared','project_id'=>$project]);
 $projection=$make(['source_entry_id'=>$shared]);
 $service->configure($uid,true,$cal);$session->setUser($peer);$service->configure($other,true,$peerCal);
 $check(count($links($shared))===2,'Shared reminder uses each member target');$check($links($projection)===[],'Projection never duplicates reminder');
 $check(count($links($id))===1,'Peer cannot export personal payment');
 $q=$db->getQueryBuilder();$q->delete('cobudget_members')->where($q->expr()->eq('project_id',$q->createNamedParameter($project)))->andWhere($q->expr()->eq('user_id',$q->createNamedParameter($other)))->executeStatement();
 $service->syncUser($other);$check(count($links($shared))===1,'Membership removal removes personal calendar copy');
 $q=$db->getQueryBuilder();$q->delete('cobudget_entries')->where($q->expr()->eq('id',$q->createNamedParameter($id)))->executeStatement();$service->syncUser($uid);$check($links($id)===[],'Deleted payment event cleaned up');

 // A failed native write remains retryable without a duplicate or a partial mapping.
 $session->setUser($user);
 $flaky=new class($backend) extends ReminderCalendarBackend {
  public bool $fail=true; public bool $none=false; public ?int $failEntry=null; public int $writes=0;
  public function __construct(private ReminderCalendarBackend $real) {}
  public function choices(string $uid):array{return $this->none?[]:$this->real->choices($uid);}
  public function read(string $uid,array $link):array{return $this->real->read($uid,$link);}
  public function put(string $uid,array $link,string $ics,bool $changed):void{$this->writes++;if($this->fail&&($this->failEntry===null||$this->failEntry===(int)$link['entry_id']))throw new RuntimeException('Simulated write failure');$this->real->put($uid,$link,$ics,$changed);}
  public function remove(string $uid,array $link):void{if($this->fail)throw new RuntimeException('Simulated removal failure');$this->real->remove($uid,$link);}
 };
 $r=new ReflectionClass(CalendarReminderService::class);
 $retry=$r->newInstanceArgs(array_map(static fn($p)=>$p->getType()->getName()===ReminderCalendarBackend::class?$flaky:Server::get($p->getType()->getName()),$r->getConstructor()->getParameters()));
 $failed=$make();$flaky->failEntry=$failed;$retry->syncUser($uid);$check(count($links($failed))===1&&$retry->state($uid)['sync_pending'],'Failure reserves retry identity and reports pending');
 $organizer=Server::get(\OCA\CoBudget\Service\ReminderService::class);
 $pendingRows=static fn($sync,$userId)=>array_column($sync->withSyncState($userId,$organizer->rows($userId)),null,'entry_id');
 $writes=$flaky->writes;
 $pending=$pendingRows($retry,$uid);
 $check($pending[$failed]['calendar_sync_pending'],'Only the failed reminder has a pending calendar warning');
 $check(!$pending[$shared]['calendar_sync_pending'],'Successful sibling reminder is not marked by the global error');
 $check(!$pending[$past]['calendar_sync_pending'],'Historical opt-in skip has no warning');
 $check($flaky->writes===$writes,'Reading row status does not call DAV');
 $failedReminder=$pending[$failed]['id'];
 $retryController=new \OCA\CoBudget\Controller\ReminderController('cobudget',Server::get(OCP\IRequest::class),$session,$organizer,$retry,Server::get(\OCA\CoBudget\Service\DataChangeService::class),Server::get(OCP\L10N\IFactory::class)->get('cobudget','en'));
 $revision=$pending[$failed]['revision'];
 $result=$retryController->retryCalendar($failedReminder);
 $check($result->getStatus()===200&&array_column($result->getData()['reminders'],'calendar_sync_pending','id')[$failedReminder],'Failed manual retry retains the warning');
 $check($organizer->rows($uid)[$failedReminder]['revision']===$revision,'Retry does not modify the reminder draft revision');
 $session->setUser($peer);$writes=$flaky->writes;
 $check($retryController->retryCalendar($failedReminder)->getStatus()===404&&$flaky->writes===$writes,'Another user cannot retry or inspect this reminder');
 $session->setUser(null);$check($retryController->retryCalendar($failedReminder)->getStatus()===401,'Anonymous retry rejected');$session->setUser($user);
 $update($failed,['reminder_date'=>time()-60]);$flaky->fail=false;$retry->syncUser($uid);$check(count($links($failed))===1&&!$retry->state($uid)['sync_pending'],'Retry recovers exactly once');
 $check(!$pendingRows($retry,$uid)[$failed]['calendar_sync_pending'],'Successful retry clears the row warning');
 // Even unchanged content becomes pending if a later native write/access check fails.
 $flaky->fail=true;$retry->syncUser($uid);
 $check($pendingRows($retry,$uid)[$failed]['calendar_sync_pending'],'Unchanged fingerprint cannot mask a failed native operation');
 $flaky->fail=false;$result=$retryController->retryCalendar($failedReminder);
 $check($result->getStatus()===200&&!array_column($result->getData()['reminders'],'calendar_sync_pending','id')[$failedReminder],'Row retry clears the warning after native recovery');
 $check($retry->state($uid)['enabled']&&$retry->state($uid)['calendar_id']===$cal,'Retry preserves calendar preferences');
 $flaky->fail=true;$retry->configure($uid,false,$cal);$check(count($links($failed))===1&&$retry->state($uid)['sync_pending'],'Failed removal retains retry mapping');
 $check($pendingRows($retry,$uid)[$failed]['calendar_sync_pending'],'Failed cleanup remains visible even with calendar channel disabled');
 $flaky->fail=false;$retry->syncUser($uid);$check($links($failed)===[]&&!$retry->state($uid)['sync_pending'],'Removal retry succeeds');
 $check(!$pendingRows($retry,$uid)[$failed]['calendar_sync_pending'],'Disabled channel with completed cleanup shows no warning');
 $flaky->none=true;$check(!$retry->state($uid)['available'],'No writable calendars hides channel');
 try{$retry->configure($uid,true,$cal);throw new RuntimeException('Unavailable calendar accepted');}catch(InvalidArgumentException $e){$n++;}
 $controller=new OCA\CoBudget\Controller\CalendarController('cobudget',Server::get(OCP\IRequest::class),$session,$service,Server::get(OCP\L10N\IFactory::class)->get('cobudget','en'));
 $session->setUser(null);$check($controller->state()->getStatus()===401&&$controller->update(true,$cal)->getStatus()===401,'Unauthenticated requests rejected');$session->setUser($user);
 $check($controller->update(true,$peerCal)->getStatus()===400,'Controller rejects foreign target');
 $update($failed,['reminder_date'=>$date]);$service->configure($uid,true,$cal);

 // Native DAV ACLs: direct fixture rows avoid sending calendar-share invitations.
 $sharedCal=$createCal($other);
 $q=$db->getQueryBuilder();$q->insert('dav_shares');
 foreach(['principaluri'=>'principals/users/'.$uid,'type'=>'calendar','resourceid'=>(int)$sharedCal,'access'=>3]as$k=>$v)$q->setValue($k,$q->createNamedParameter($v));$q->executeStatement();
 $check(!in_array($sharedCal,array_column($backend->choices($uid),'id'),true),'Read-only shared calendar excluded');
 $access=static function(int $access)use($db,$uid,$sharedCal):void{$q=$db->getQueryBuilder();$q->update('dav_shares')->set('access',$q->createNamedParameter($access))->where($q->expr()->eq('resourceid',$q->createNamedParameter((int)$sharedCal)))->andWhere($q->expr()->eq('principaluri',$q->createNamedParameter('principals/users/'.$uid)))->executeStatement();};
 $access(2);$check(in_array($sharedCal,array_column($backend->choices($uid),'id'),true),'Writable shared calendar offered');
 $service->configure($uid,true,$sharedCal);$check(!$service->state($uid)['sync_pending']&&$links($failed)[0]['calendar_id']===$sharedCal,'Native writable share accepts reminder');
 $access(3);$update($failed,['reminder_text'=>'Changed after revocation']);$service->syncUser($uid);$check($service->state($uid)['sync_pending'],'Revoked write access blocks updates');
 $service->configure($uid,true,$cal);$check(count($links($failed))===2,'New target works while old cleanup awaits permission');
 $check($pendingRows($service,$uid)[$failed]['calendar_sync_pending'],'Old inaccessible calendar keeps a warning after the new target succeeds');
 $access(2);$service->syncUser($uid);$check(count($links($failed))===1&&!$service->state($uid)['sync_pending'],'Restored permission cleans old target');
 $service->resetConnections($uid);$check(!$service->state($uid)['enabled']&&$links($shared)===[],'Reset clears cached connection and external copies');

 $diagnostics=new class extends Psr\Log\AbstractLogger {
  public array $records=[];
  public function log($level,string|Stringable $message,array $context=[]):void{$this->records[]=[$message,$context];}
 };
 $broken=new class extends CalendarReminderService {
  public function __construct(){}
  public function state(string $uid):array{throw new RuntimeException('PRIVATE CALENDAR TITLE',0,new LogicException('PRIVATE SQL VALUES'));}
  public function configure(string $uid,bool $enabled,string $calendarId):array{return $this->state($uid);}
 };
 (new ReflectionProperty(CalendarReminderService::class,'logger'))->setValue($broken,$diagnostics);
 $brokenController=new OCA\CoBudget\Controller\CalendarController('cobudget',Server::get(OCP\IRequest::class),$session,$broken,Server::get(OCP\L10N\IFactory::class)->get('cobudget','en'));
 $check($brokenController->state()->getStatus()===503&&$brokenController->update(true,$cal)->getStatus()===503,'Controller keeps safe failure responses');
 $check(array_column(array_column($diagnostics->records,1),'operation')===['settings-read','settings-save'],'Both load and retry failures are logged');
 $record=$diagnostics->records[1][1];
 $check(count($record['causes'])===2&&str_contains($record['causes'][0]['source'],'calendar-reminders.php:'),'Diagnostic includes exception chain and source locations');
 $check(!str_contains(json_encode($diagnostics->records),'PRIVATE')&&!str_contains(json_encode($brokenController->state()->getData()),'PRIVATE'),'Logs and responses omit exception messages and private values');
 $organizer=Server::get(\OCA\CoBudget\Service\ReminderService::class);
 $session->setUser($user);
 $service->configure($uid,true,$cal);
 $draft=['title'=>'Cancel contract','description'=>"Customer 123\nPlease cancel before the deadline.",'remind_at'=>$date,'due_date'=>'2030-04-30','labels'=>['cancel'],'keep_open'=>true];
 $free=$organizer->save($uid,null,$draft);$service->syncUser($uid);
 $reminderLinks=static function(int $id)use($db):array{$q=$db->getQueryBuilder();$r=$q->select('*')->from('cobudget_calendar_links')->where($q->expr()->eq('reminder_id',$q->createNamedParameter($id)))->executeQuery();$rows=$r->fetchAll();$r->closeCursor();return $rows;};
 $link=$reminderLinks($free['id'])[0];$ics=$object($link)['calendardata'];$event=\Sabre\VObject\Reader::read($ics)->VEVENT;
 $check($link['entry_id']===null&&(string)$event->SUMMARY===$draft['title'],'Standalone event has its own identity');
 $check(str_contains((string)$event->DESCRIPTION,$draft['description'])&&str_contains((string)$event->DESCRIPTION,$draft['due_date']),'Calendar contains explicit notes and deadline');
 $check(isset($event->VALARM)&&str_contains((string)$event->URL,'#/reminders?reminder='.$free['id']),'Alarm and deep link target organizer');
 $organizer->setStatus($uid,$free['id'],'completed',$free['revision']);$service->syncUser($uid);
 $check(!isset(\Sabre\VObject\Reader::read($object($link)['calendardata'])->VEVENT->VALARM),'Completion removes calendar alarm');
 $check(count($reminderLinks($free['id']))===1,'Completion keeps calendar event history');
 $completed=$organizer->rows($uid)[$free['id']];$organizer->setStatus($uid,$free['id'],'active',$completed['revision']);$service->syncUser($uid);
 $check(isset(\Sabre\VObject\Reader::read($object($link)['calendardata'])->VEVENT->VALARM),'Reopening future reminder restores alarm');
 $active=$organizer->rows($uid)[$free['id']];$organizer->setStatus($uid,$free['id'],'archived',$active['revision']);$service->syncUser($uid);
 $check(!isset(\Sabre\VObject\Reader::read($object($link)['calendardata'])->VEVENT->VALARM),'Manual archive cancels alarm without deleting event');
 $archived=$organizer->rows($uid)[$free['id']];$free=$organizer->save($uid,$free['id'],$draft+['revision'=>$archived['revision']]);$service->syncUser($uid);
 $check($reminderLinks($free['id'])[0]['event_uid']===$link['event_uid'],'Edits preserve native event UID');
 $organizer->remove($uid,$free['id'],$free['revision']);$service->syncUser($uid);
 $check($reminderLinks($free['id'])===[]&&!$object($link),'Deleting reminder removes its native event');
 // Legacy mapping adoption must update the same native event, including past reminders.
 $legacy=$make();$service->syncUser($uid);$legacyLink=$links($legacy)[0];
 $q=$db->getQueryBuilder();$q->update('cobudget_calendar_links')->set('reminder_id',$q->createNamedParameter(null))->where($q->expr()->eq('id',$q->createNamedParameter($legacyLink['id'])))->executeStatement();
 $service->syncUser($uid);$check($links($legacy)[0]['event_uid']===$legacyLink['event_uid']&&$links($legacy)[0]['reminder_id']!==null,'Upgrade adopts legacy calendar identity');
 $update($legacy,['reminder_date'=>time()-15*86400]);$service->syncUser($uid);
 $check(count($links($legacy))===1&&!isset(\Sabre\VObject\Reader::read($object($legacyLink)['calendardata'])->VEVENT->VALARM),'Auto-archive retains past event without an alarm');
 // Shared source produces separate events and personal completion.
 $insert('members',['project_id'=>$project,'user_id'=>$other,'share_basis_points'=>5000,'personal_workspace_id'=>$ws]);
 $sharedNew=$make(['project_id'=>$project,'entry_kind'=>'shared']);
 $service->configure($other,true,$peerCal);$service->syncUser($uid);
 $mine=array_values(array_filter($organizer->rows($uid),fn($r)=>$r['entry_id']===$sharedNew))[0];
 $peerReminder=array_values(array_filter($organizer->rows($other),fn($r)=>$r['entry_id']===$sharedNew))[0];
 $organizer->setStatus($uid,$mine['id'],'completed',$mine['revision']);$service->syncUser($uid);$service->syncUser($other);
 $myLink=$reminderLinks($mine['id'])[0];$theirLink=$reminderLinks($peerReminder['id'])[0];
 $check(!isset(\Sabre\VObject\Reader::read($object($myLink)['calendardata'])->VEVENT->VALARM)&&isset(\Sabre\VObject\Reader::read($object($theirLink)['calendardata'])->VEVENT->VALARM),'Completion removes only this member’s alarm');

 // Exercise the actual payment-save endpoint and its post-commit calendar hook.
 // No manual sync is allowed between saving the cleared date and assertions.
 $changes=Server::get(\OCA\CoBudget\Service\DataChangeService::class);
 (new ReflectionProperty($changes,'pushQueueResolved'))->setValue($changes,true);
 (new ReflectionProperty($changes,'pushQueue'))->setValue($changes,null);
 $notifications=new class extends \OCA\CoBudget\Service\ProjectNotificationService {
  public function __construct(){}
  public function notifyEntryCreated(int $projectId,int $workspaceId,int $entryId,string $actorUserId,string $entryUserId,string $type,int $amountCents,string $currency,string $description=''):void{}
 };
 $controllerFor=static function(string $actor,int $workspace,string $class=\OCA\CoBudget\Controller\EntryController::class)use($session,$users,$notifications){
  $session->setUser($users->get($actor));
  $request=new \OC\AppFramework\Http\Request(['server'=>['HTTP_X_WORKSPACE_ID'=>(string)$workspace,'HTTP_X_COBUDGET_TIMEZONE'=>'Europe/Vienna']],Server::get(\OCP\IRequestId::class),Server::get(\OCP\IConfig::class));
  $reflection=new ReflectionClass($class);
  return $reflection->newInstanceArgs(array_map(static function($p)use($request,$notifications){
   if($p->getName()==='appName')return 'cobudget';
   return match($p->getType()->getName()){
    \OCP\IRequest::class=>$request,
    \OCP\IL10N::class=>Server::get(\OCP\L10N\IFactory::class)->get('cobudget','en'),
    \OCA\CoBudget\Service\ProjectNotificationService::class=>$notifications,
    default=>Server::get($p->getType()->getName()),
   };
  },$reflection->getConstructor()->getParameters()));
 };
 $peerWorkspace=$insert('workspaces',['user_id'=>$other,'name'=>'Calendar payment deletion test']);
 $testArea=$insert('projects',['owner_id'=>$uid,'name'=>'Calendar deletion area','workspace_id'=>$ws,'created_at'=>time()]);
 foreach([$uid=>$ws,$other=>$peerWorkspace] as $member=>$workspace)$insert('members',['project_id'=>$testArea,'user_id'=>$member,'share_basis_points'=>5000,'personal_workspace_id'=>$workspace]);
 foreach(['personal','completed','archived','shared-owner','shared-peer'] as $case){
  $sharedCase=str_starts_with($case,'shared-');
  $details=['id'=>null,'revision'=>0,'description'=>'Personal reminder note','labels'=>['cancel'],'completed'=>false,'archived'=>false];
  $payload=['amount'=>17.25,'description'=>'__Clear reminder fixture__','date'=>time()-3600,'projectId'=>$sharedCase?$testArea:null,'reminderDate'=>$date,'reminderText'=>'Cancel test contract','reminderDetails'=>$details];
  $response=$controllerFor($uid,$ws)->create(...$payload);
  $check($response->getStatus()===200,'Payment creation succeeds: '.$case.' '.json_encode($response->getData()));
  $paymentId=(int)$response->getData()['id'];
  $eventLinks=$links($paymentId);
  $check(count($eventLinks)===($sharedCase?2:1),'Payment save creates native events automatically: '.$case);
  foreach($eventLinks as $eventLink)$check((bool)$object($eventLink),'Native event exists before clearing: '.$case);
  $actor=$case==='shared-peer'?$other:$uid;
  $actorWorkspace=$actor===$uid?$ws:$peerWorkspace;
  $personal=array_values(array_filter($organizer->rows($actor),fn($r)=>$r['entry_id']===$paymentId))[0];
  if(in_array($case,['completed','archived'],true)){
   $organizer->setStatus($actor,$personal['id'],$case,$personal['revision']);
   $personal=$organizer->rows($actor)[$personal['id']];
  }
  // Same payload as the form: cleared date, blank shared title, retained private draft.
  $details=array_replace($details,['id'=>$personal['id'],'revision'=>$personal['revision']]);
  $response=$controllerFor($actor,$actorWorkspace)->update(...array_replace($payload,['id'=>$paymentId,'reminderDate'=>null,'reminderText'=>'','reminderDetails'=>$details]));
  $check($response->getStatus()===200,'Cleared reminder saves: '.$case.' '.json_encode($response->getData()));
  $check($links($paymentId)===[],'Calendar mapping removed during save, without a manual sync: '.$case);
  foreach($eventLinks as $eventLink){
   $remaining=$object($eventLink);
   $check(!$remaining||!empty($remaining['{http://nextcloud.com/ns}deleted-at']),'Native event removed, not merely its mapping: '.$case);
  }
  foreach(($sharedCase?[$uid=>$ws,$other=>$peerWorkspace]:[$uid=>$ws]) as $recipient=>$workspace){
   $overview=$controllerFor($recipient,$workspace,\OCA\CoBudget\Controller\ReminderController::class)->index();
   $check($overview->getStatus()===200&&!array_filter($overview->getData()['reminders'],fn($r)=>$r['entry_id']===$paymentId),'Reminder overview removes the entry for recipient: '.$case);
  }
  $q=$db->getQueryBuilder();$result=$q->select('amount_cents','reminder_date','reminder_text')->from('cobudget_entries')->where($q->expr()->eq('id',$q->createNamedParameter($paymentId)))->executeQuery();$saved=$result->fetch();$result->closeCursor();
  $check((int)$saved['amount_cents']===1725&&$saved['reminder_date']===null&&empty($saved['reminder_text']),'Payment survives with its amount and cleared reminder: '.$case);
 }

 // Edit each field through payment saves; inspect overview + native DAV before any manual sync.
 $overviewReminder=static function(string $recipient,int $workspace,int $paymentId)use($controllerFor,$check):array{
  $response=$controllerFor($recipient,$workspace,\OCA\CoBudget\Controller\ReminderController::class)->index();
  $check($response->getStatus()===200,'Reminder overview loads after payment update');
  $matches=array_values(array_filter($response->getData()['reminders'],fn($r)=>$r['entry_id']===$paymentId));
  $check(count($matches)===1,'Exactly one reminder remains for this payment and recipient');
  return $matches[0];
 };
 $formDetails=static fn(array $row):array=>['id'=>$row['id'],'revision'=>$row['revision'],'description'=>$row['description']??'','labels'=>$row['labels'],'completed'=>(bool)$row['completed_at'],'archived'=>$row['status']==='archived'];
 foreach(['personal','shared-owner','shared-peer'] as $case){
  $sharedCase=$case!=='personal';
  $recipients=$sharedCase?[$uid=>$ws,$other=>$peerWorkspace]:[$uid=>$ws];
  $expectedDescriptions=[$uid=>'Private owner note',$other=>'Private peer note'];
  $expectedTitle='Initial contract reminder';
  $expectedTime=(new DateTimeImmutable('2030-03-30 09:00:00',new DateTimeZone('Europe/Vienna')))->getTimestamp();
  $payload=['amount'=>23.45,'description'=>'PRIVATE FINANCIAL DESCRIPTION','date'=>time()-3600,'projectId'=>$sharedCase?$testArea:null,'reminderDate'=>$expectedTime,'reminderText'=>$expectedTitle,'reminderDetails'=>['id'=>null,'revision'=>0,'description'=>$expectedDescriptions[$uid],'labels'=>['cancel'],'completed'=>false,'archived'=>false]];
  $response=$controllerFor($uid,$ws)->create(...$payload);
  $check($response->getStatus()===200,'Create payment reminder for field updates: '.$case);
  $paymentId=(int)$response->getData()['id'];
  if($sharedCase){
   $peerRow=$overviewReminder($other,$peerWorkspace,$paymentId);
   $peerDetails=array_replace($formDetails($peerRow),['description'=>$expectedDescriptions[$other]]);
   $response=$controllerFor($other,$peerWorkspace)->update(...array_replace($payload,['id'=>$paymentId,'reminderDetails'=>$peerDetails]));
   $check($response->getStatus()===200,'Initialize independent peer description');
  }
  $originalLinks=array_column($links($paymentId),null,'user_id');
  $check(count($originalLinks)===count($recipients),'Every recipient starts with one event');
  $actor=$case==='shared-peer'?$other:$uid;
  $workspace=$actor===$uid?$ws:$peerWorkspace;
  $initial=$overviewReminder($actor,$workspace,$paymentId);
  $check(!$initial['keep_open'],'New payment reminders default to automatic archival: '.$case);
  // Hidden payment fields must not remove or rewrite either member's reminder or native event.
  $config->setUserValue($actor,'cobudget','enable_reminders','no');
  $hiddenPayload=$payload;unset($hiddenPayload['reminderDate'],$hiddenPayload['reminderText'],$hiddenPayload['reminderDetails']);
  $response=$controllerFor($actor,$workspace)->update(...array_replace($hiddenPayload,['id'=>$paymentId,'description'=>'Edited with reminders hidden']));
  $check($response->getStatus()===200,'Payment saves with hidden reminder fields: '.$case);
  $afterHidden=$overviewReminder($actor,$workspace,$paymentId);
  foreach(['id','title','remind_at','description','labels','keep_open','completed_at','archived_at','notified','revision'] as $field) {
   $check($afterHidden[$field]===$initial[$field],'Hidden payment save preserves '.$field.': '.$case);
  }
  foreach($originalLinks as $recipient=>$link) {
   $currentLinks=array_column($links($paymentId),null,'user_id');
   $check($currentLinks[$recipient]['event_uid']===$link['event_uid'],'Hidden payment save retains calendar identity');
   $check(isset(\Sabre\VObject\Reader::read($object($link)['calendardata'])->VEVENT->VALARM),'Hidden reminders retain native calendar alerts');
  }
  $response=$controllerFor($actor,$workspace)->create(...array_replace($payload,['projectId'=>null]));
  $check($response->getStatus()===200,'New payment saves when reminders hidden');
  $hiddenPaymentId=(int)$response->getData()['id'];
  $check(!array_filter($organizer->rows($actor),fn($r)=>$r['entry_id']===$hiddenPaymentId),'Hidden new payment does not copy a reminder from a stale payload');
  $config->setUserValue($actor,'cobudget','enable_reminders','yes');
  // The user's choice is made in the organizer, not in the payment payload.
  $organizer->save($actor,$initial['id'],array_replace($initial,['keep_open'=>true]));
  foreach(['date','title','description','clear-description','combined'] as $field){
   $before=$overviewReminder($actor,$workspace,$paymentId);
   if(in_array($field,['date','combined'],true))$expectedTime=(new DateTimeImmutable($field==='date'?'2030-03-31 09:00:00':'2030-11-04 09:00:00',new DateTimeZone('Europe/Vienna')))->getTimestamp();
   if(in_array($field,['title','combined'],true))$expectedTitle=$field==='title'?'Kündigen: Abo & Vertrag':'Verlängern – neuer Termin';
   if(in_array($field,['description','clear-description','combined'],true))$expectedDescriptions[$actor]=$field==='clear-description'?'':"Persönliche Notiz: ".$field."\nKündigung bis Monatsende; Referenz 123, Grüße.";
   $details=array_replace($formDetails($before),['description'=>$expectedDescriptions[$actor]]);
   if((int)$before['remind_at']!==$expectedTime){$details['completed']=false;$details['archived']=false;}
   $response=$controllerFor($actor,$workspace)->update(...array_replace($payload,['id'=>$paymentId,'reminderDate'=>$expectedTime,'reminderText'=>$expectedTitle,'reminderDetails'=>$details]));
   $check($response->getStatus()===200,'Payment saves '.$field.' for '.$case.' '.json_encode($response->getData()));
   $currentLinks=array_column($links($paymentId),null,'user_id');
   $check(count($currentLinks)===count($recipients),'Updating '.$field.' does not duplicate calendar events');
   foreach($recipients as $recipient=>$recipientWorkspace){
    $current=$currentLinks[$recipient];
    $check($current['event_uid']===$originalLinks[$recipient]['event_uid']&&$current['object_uri']===$originalLinks[$recipient]['object_uri'],'Existing native calendar identity survives '.$field);
    $native=$object($current);
    $check((bool)$native,'Updated native event exists');
    $event=\Sabre\VObject\Reader::read($native['calendardata'])->VEVENT;
    $check($event->DTSTART->getDateTime()->getTimestamp()===$expectedTime,'Calendar date/time follows payment save across DST');
    $check((string)$event->SUMMARY===$expectedTitle,'Calendar title follows payment save');
    $check((string)$event->DESCRIPTION===$expectedDescriptions[$recipient],'Calendar receives only this recipient’s reminder description');
    $check(isset($event->VALARM)&&(string)$event->VALARM->TRIGGER==='PT0S'&&(string)$event->VALARM->DESCRIPTION===$expectedTitle,'Alarm stays at the updated reminder time with the updated title');
    $overview=$overviewReminder($recipient,$recipientWorkspace,$paymentId);
    $check($overview['remind_at']===$expectedTime&&$overview['title']===$expectedTitle,'Overview receives canonical date and title');
    $check((string)($overview['description']??'')===$expectedDescriptions[$recipient],'Overview preserves personal descriptions');
    $check($overview['keep_open']===($recipient===$actor),'Payment edits preserve each participant’s own automatic-archive choice');
   }
   $q=$db->getQueryBuilder();$result=$q->select('amount_cents','description')->from('cobudget_entries')->where($q->expr()->eq('id',$q->createNamedParameter($paymentId)))->executeQuery();$payment=$result->fetch();$result->closeCursor();
   $check((int)$payment['amount_cents']===2345&&$payment['description']==='PRIVATE FINANCIAL DESCRIPTION','Reminder edits leave financial amount and payment description unchanged');
  }
 }

 $before=$overviewReminder($actor,$workspace,$paymentId);
 $organizer->save($actor,$before['id'],array_replace($before,['keep_open'=>false]));
 $before=$overviewReminder($actor,$workspace,$paymentId);
 $response=$controllerFor($actor,$workspace)->update(...array_replace($payload,['id'=>$paymentId,'reminderDate'=>$expectedTime,'reminderText'=>$expectedTitle,'reminderDetails'=>$formDetails($before)]));
 $check($response->getStatus()===200,'Payment saves after explicitly re-enabling automatic archival');
 $check(!$overviewReminder($actor,$workspace,$paymentId)['keep_open'],'Explicitly enabled automatic archival also survives payment saves');

 // Quick postponement uses the real controller, payment propagation and native calendar.
 foreach (['independent','personal','shared-owner','shared-peer','legacy-empty-title'] as $case) {
  $start=(new DateTimeImmutable('2031-03-29 09:00:00',new DateTimeZone('Europe/Vienna')))->getTimestamp();
  $later=(new DateTimeImmutable('2031-03-30 09:00:00',new DateTimeZone('Europe/Vienna')))->getTimestamp();
  $sharedCase=str_starts_with($case,'shared-');
  $actor=$case==='shared-peer'?$other:$uid;
  $workspace=$actor===$uid?$ws:$peerWorkspace;
  $paymentId=null;
  if($case==='independent') {
   $response=$controllerFor($actor,$workspace,\OCA\CoBudget\Controller\ReminderController::class)->save(['title'=>'Postpone fixture','remind_at'=>$start,'description'=>'Private notes','labels'=>['cancel'],'keep_open'=>true]);
   $check($response->getStatus()===200,'Create independent reminder for postponement');$row=$response->getData();
  } else {
   $response=$controllerFor($uid,$ws)->create(amount:42.50,description:'Unchanged payment',date:time(),projectId:$sharedCase?$testArea:null,reminderDate:$start,reminderText:$case==='legacy-empty-title'?'':'Postpone fixture');
   $check($response->getStatus()===200,'Create linked reminder for postponement: '.$case);
   $paymentId=(int)$response->getData()['id'];
   $row=$overviewReminder($actor,$workspace,$paymentId);
   $row=$organizer->save($actor,$row['id'],array_replace($row,['description'=>'Private notes','labels'=>['cancel'],'keep_open'=>true]));
  }
  $reminderId=$row['id'];
  $q=$db->getQueryBuilder();$q->update('cobudget_reminders')->set('notified',$q->createNamedParameter(true))->where($q->expr()->eq('id',$q->createNamedParameter($reminderId)))->executeStatement();
  if($paymentId!==null)$update($paymentId,['reminder_notified'=>true]);
  $row=$organizer->rows($actor)[$reminderId];
  $oldLinks=$reminderLinks($reminderId);
  $controller=$controllerFor($actor,$workspace,\OCA\CoBudget\Controller\ReminderController::class);
  $check($controller->postpone($reminderId,$row['revision'],$start)->getStatus()===400,'Same-time postponement rejected');
  $check($controller->postpone($reminderId,$row['revision'],time()-1)->getStatus()===400,'Past postponement rejected');
  $check($controller->postpone($reminderId,$row['revision']-1,$later)->getStatus()===409,'Stale postponement rejected');
  $response=$controller->postpone($reminderId,$row['revision'],$later);
  $check($response->getStatus()===200,'Quick postponement succeeds: '.$case.' '.json_encode($response->getData()));
  $saved=$organizer->rows($actor)[$reminderId];
  $check($saved['remind_at']===$later&&!$saved['notified']&&$saved['status']==='active','New time re-arms delivery and remains open');
  foreach(['title','description','labels','keep_open','due_date'] as $field)$check($saved[$field]===$row[$field],'Postponement preserves '.$field);
  $newLinks=$reminderLinks($reminderId);
  $check(count($newLinks)===1,'Postponement has one calendar event');
  if($oldLinks)$check($newLinks[0]['event_uid']===$oldLinks[0]['event_uid'],'Postponement retains event identity');
  $event=\Sabre\VObject\Reader::read($object($newLinks[0])['calendardata'])->VEVENT;
  $check($event->DTSTART->getDateTime()->getTimestamp()===$later&&isset($event->VALARM),'Native calendar and alarm use the new time');
  if($paymentId!==null){
   $q=$db->getQueryBuilder();$result=$q->select('reminder_date','reminder_notified','amount_cents','description')->from('cobudget_entries')->where($q->expr()->eq('id',$q->createNamedParameter($paymentId)))->executeQuery();$payment=$result->fetch();$result->closeCursor();
   $check((int)$payment['reminder_date']===$later&&!$payment['reminder_notified'],'Canonical payment reminder follows quick action');
   $check((int)$payment['amount_cents']===4250&&$payment['description']==='Unchanged payment','Financial payment fields remain unchanged');
   if($sharedCase){
    foreach([$uid=>$ws,$other=>$peerWorkspace] as $recipient=>$recipientWorkspace){
     $peerReminder=$overviewReminder($recipient,$recipientWorkspace,$paymentId);
     $check($peerReminder['remind_at']===$later,'Shared member overview follows canonical reminder');
     $peerEvent=\Sabre\VObject\Reader::read($object($reminderLinks($peerReminder['id'])[0])['calendardata'])->VEVENT;
     $check($peerEvent->DTSTART->getDateTime()->getTimestamp()===$later,'Shared member calendar follows canonical reminder');
    }
   }
  }
  $controller=$controllerFor($actor,$workspace,\OCA\CoBudget\Controller\ReminderController::class);
  $organizer->setStatus($actor,$reminderId,'completed',$saved['revision']);$closed=$organizer->rows($actor)[$reminderId];
  $check($controller->postpone($reminderId,$closed['revision'],$later+86400)->getStatus()===400,'Completed reminders cannot be accidentally reactivated');
  $organizer->setStatus($actor,$reminderId,'archived',$closed['revision']);$closed=$organizer->rows($actor)[$reminderId];
  $check($controller->postpone($reminderId,$closed['revision'],$later+86400)->getStatus()===400,'Archived reminders cannot be accidentally reactivated');
  if($case==='personal'){
   $session->setUser($peer);$check($controller->postpone($reminderId,$closed['revision'],$later+86400)->getStatus()===404,'Foreign reminder postponement rejected');
   $session->setUser(null);$check($controller->postpone($reminderId,$closed['revision'],$later+86400)->getStatus()===401,'Anonymous postponement rejected');
  }
 }

 // Bidirectional sync against native DAV; private, future fixtures only.
 $session->setUser($user); $service->configure($uid,true,$cal);
 $future=time()+90*86400;
 $newReminder=static function(array $extra=[])use($organizer,$service,$uid,$future){
  $row=$organizer->save($uid,null,$extra+['title'=>'Round trip','description'=>'Personal notes','remind_at'=>$future,'keep_open'=>true,'labels'=>['cancel']]);
  $service->syncUser($uid);return $organizer->rows($uid)[$row['id']];
 };
 $calendarEdit=static function(array $link,array $fields)use($dav,$object):void{
  $v=\Sabre\VObject\Reader::read($object($link)['calendardata']);
  foreach($fields as $key=>$value)$v->VEVENT->$key=$value;
  if(isset($fields['DTSTART']))$v->VEVENT->DTEND=gmdate('Ymd\THis\Z',strtotime($fields['DTSTART'])+60);
  $dav->updateCalendarObject((int)$link['calendar_id'],$link['object_uri'],$v->serialize());
 };
 $row=$newReminder();$rid=$row['id'];$link=$reminderLinks($rid)[0];
 $calendarEdit($link,['SUMMARY'=>'Calendar title','DESCRIPTION'=>"Calendar notes\nSecond line",'DTSTART'=>gmdate('Ymd\THis\Z',$future+3600)]);
 $service->syncUser($uid);$row=$organizer->rows($uid)[$rid];
 $check($row['title']==='Calendar title'&&$row['description']==="Calendar notes\nSecond line"&&$row['remind_at']===$future+3600,'All three calendar fields import');
 $check($row['labels']===['cancel']&&$row['keep_open'],'Import preserves personal labels and archive preference');
 $revision=$row['revision'];$service->syncUser($uid);$check($organizer->rows($uid)[$rid]['revision']===$revision,'Second synchronization is idempotent');
 // Different fields merge, same-field edits require a decision.
 $calendarEdit($link,['DESCRIPTION'=>'Remote description']);
 $row=$organizer->save($uid,$rid,array_replace($row,['title'=>'Local title','completed'=>false,'archived'=>false]));
 $service->syncUser($uid);$row=$organizer->rows($uid)[$rid];
 $check($row['title']==='Local title'&&$row['description']==='Remote description','Different fields merge without loss');
 $calendarEdit($link,['SUMMARY'=>'Calendar conflicting title']);
 $row=$organizer->save($uid,$rid,array_replace($row,['title'=>'Local conflicting title','completed'=>false,'archived'=>false]));
 $service->syncUser($uid);$conflict=$service->withSyncState($uid,$organizer->rows($uid))[$rid]['calendar_conflict'];
 $check(isset($conflict['fields']['title'])&&$organizer->rows($uid)[$rid]['title']==='Local conflicting title','Conflict preserves local value');
 $check((string)\Sabre\VObject\Reader::read($object($link)['calendardata'])->VEVENT->SUMMARY==='Calendar conflicting title','Conflict preserves calendar value');
 $calendarEdit($link,['SUMMARY'=>'Newer calendar title']);
 try{$service->resolve($uid,$rid,$conflict['token'],['title'=>'calendar']);throw new RuntimeException('Stale conflict accepted');}catch(DomainException){$n++;}
 $service->syncUser($uid);$conflict=$service->withSyncState($uid,$organizer->rows($uid))[$rid]['calendar_conflict'];
 $service->resolve($uid,$rid,$conflict['token'],['title'=>'calendar']);
 $check($organizer->rows($uid)[$rid]['title']==='Newer calendar title'&&!$service->withSyncState($uid,$organizer->rows($uid))[$rid]['calendar_sync_pending'],'Conflict resolution uses explicitly chosen current value');
 // Calendar deletion archives only this reminder, keeps its data, and never recreates the event.
 $before=$organizer->rows($uid)[$rid];
 $dav->deleteCalendarObject((int)$link['calendar_id'],$link['object_uri']);
 $service->syncUser($uid);$row=$organizer->rows($uid)[$rid];
 $check($row['status']==='archived'&&!$row['completed_at']&&$row['title']===$before['title'],'Deleted event archives without inventing completion');
 $service->syncUser($uid);$check(!$object($link)||!empty($object($link)['{http://nextcloud.com/ns}deleted-at']),'Deleted event is not recreated');
 $check(!$service->withSyncState($uid,$organizer->rows($uid))[$rid]['calendar_sync_pending'],'Intentional deletion is not a sync error');
 $row=$organizer->save($uid,$rid,array_replace($row,['remind_at'=>$future+86400,'completed'=>false,'archived'=>false]));
 $service->syncUser($uid);$restored=$object($link);
 $check($restored&&empty($restored['{http://nextcloud.com/ns}deleted-at']),'Explicit future reactivation recreates one event');
 $check(count($reminderLinks($rid))===1,'Reactivation keeps one identity');
 // Legacy links have no baseline: differences are offered, not silently overwritten.
 $calendarEdit($link,['SUMMARY'=>'Before upgrade edit']);
 $q=$db->getQueryBuilder();$q->update('cobudget_calendar_links')->set('sync_state',$q->createNamedParameter(null))->where($q->expr()->eq('id',$q->createNamedParameter((int)$link['id'])))->executeStatement();
 $service->syncUser($uid);$legacy=$service->withSyncState($uid,$organizer->rows($uid))[$rid]['calendar_conflict'];
 $check(isset($legacy['fields']['title']),'Upgrade preserves pre-existing calendar changes as a conflict');
 $service->resolve($uid,$rid,$legacy['token'],['title'=>'local']);
 $check((string)\Sabre\VObject\Reader::read($object($link)['calendardata'])->VEVENT->SUMMARY===$row['title'],'Local conflict choice updates calendar');
 // Date changes do not reopen closed entries, including completed+archived states.
 $row=$organizer->rows($uid)[$rid];$organizer->setStatus($uid,$rid,'completed',$row['revision']);$service->syncUser($uid);
 $calendarEdit($link,['DTSTART'=>gmdate('Ymd\THis\Z',$future+2*86400)]);$service->syncUser($uid);
 $row=$organizer->rows($uid)[$rid];$check($row['status']==='completed'&&$row['completed_at'],'Calendar rescheduling preserves completion');
 $check(!isset(\Sabre\VObject\Reader::read($object($link)['calendardata'])->VEVENT->VALARM),'Completed event has no alarm after import');
 // An all-day date becomes 09:00 in the account timezone, across the DST boundary.
 $config->setUserValue($uid,'core','timezone','Europe/Vienna');
 $row=$newReminder();$allDay=$reminderLinks($row['id'])[0];
 $v=\Sabre\VObject\Reader::read($object($allDay)['calendardata']);unset($v->VEVENT->DTSTART,$v->VEVENT->DTEND);
 $v->VEVENT->add('DTSTART','20300331',['VALUE'=>'DATE']);$v->VEVENT->add('DTEND','20300401',['VALUE'=>'DATE']);
 $dav->updateCalendarObject((int)$allDay['calendar_id'],$allDay['object_uri'],$v->serialize());$service->syncUser($uid);
 $check($organizer->rows($uid)[$row['id']]['remind_at']===(new DateTimeImmutable('2030-03-31 09:00:00',new DateTimeZone('Europe/Vienna')))->getTimestamp(),'All-day import uses local 09:00 with DST');
 // Even when the date is unchanged, a converted all-day event must regain a 09:00 alarm.
 $v=\Sabre\VObject\Reader::read($object($allDay)['calendardata']);unset($v->VEVENT->DTSTART,$v->VEVENT->DTEND);
 $v->VEVENT->add('DTSTART','20300331',['VALUE'=>'DATE']);$v->VEVENT->add('DTEND','20300401',['VALUE'=>'DATE']);
 $dav->updateCalendarObject((int)$allDay['calendar_id'],$allDay['object_uri'],$v->serialize());$service->syncUser($uid);
 $v=\Sabre\VObject\Reader::read($object($allDay)['calendardata']);
 $check($v->VEVENT->DTSTART->getValueType()==='DATE-TIME'&&(string)$v->VEVENT->VALARM->TRIGGER==='PT0S','Unchanged all-day date normalizes to the reminder time and matching alarm');
 // The DAV write guard must reject an event changed since the comparison.
 $row=$newReminder();$raceLink=$reminderLinks($row['id'])[0];$read=$backend->read($uid,$raceLink);
 $calendarEdit($raceLink,['SUMMARY'=>'Concurrent calendar change']);
 try{$backend->put($uid,$raceLink+['expected_etag'=>$read['etag']],$object($raceLink)['calendardata'],true);throw new RuntimeException('Stale DAV ETag accepted');}catch(DomainException){$n++;}
 $check((string)\Sabre\VObject\Reader::read($object($raceLink)['calendardata'])->VEVENT->SUMMARY==='Concurrent calendar change','Stale outgoing write preserves the concurrent calendar value');
 $service->syncUser($uid);$check($organizer->rows($uid)[$row['id']]['title']==='Concurrent calendar change','Retry imports the concurrent calendar value');
 // A deleted calendar is not interpreted as deletion of all reminders.
 $isolatedCal=$createCal($uid);$service->configure($uid,true,$isolatedCal);$row=$newReminder();
 $dav->deleteCalendar((int)$isolatedCal);$service->syncUser($uid);
 $check($organizer->rows($uid)[$row['id']]['status']==='active'&&$service->state($uid)['sync_pending'],'Calendar deletion preserves reminders and reports unavailable access');
 $service->configure($uid,true,$cal);
 // Shared calendar edits cannot act as a payment-edit delegation.
 $access(2);$service->configure($uid,true,$sharedCal);$row=$newReminder();$sharedCalendarLink=$reminderLinks($row['id'])[0];
 $calendarEdit($sharedCalendarLink,['SUMMARY'=>'Untrusted shared-calendar edit']);$service->syncUser($uid);
 $check($organizer->rows($uid)[$row['id']]['title']==='Round trip','Shared calendar remains export-only');
 $service->configure($uid,true,$cal);

 // Shared payment imports preserve every recipient's personal fields and closed state.
 $syncProject=$insert('projects',['owner_id'=>$uid,'name'=>'Two-way fixture','workspace_id'=>$ws,'created_at'=>time()]);
 foreach([$uid,$other]as$u)$insert('members',['project_id'=>$syncProject,'user_id'=>$u,'share_basis_points'=>5000,'personal_workspace_id'=>$ws]);
 $sharedPayment=$make(['entry_kind'=>'shared','project_id'=>$syncProject,'reminder_date'=>$future]);
 $service->configure($uid,true,$cal);$service->configure($other,true,$peerCal);
 $mine=array_values(array_filter($organizer->rows($uid),fn($r)=>$r['entry_id']===$sharedPayment))[0];
 $peerRow=array_values(array_filter($organizer->rows($other),fn($r)=>$r['entry_id']===$sharedPayment))[0];
 $peerRow=$organizer->save($other,$peerRow['id'],array_replace($peerRow,['description'=>'Private peer description','completed'=>true,'archived'=>false]));
 $service->syncUser($other);
 $mineLink=$reminderLinks($mine['id'])[0];
 $calendarEdit($mineLink,['SUMMARY'=>'Shared calendar title','DESCRIPTION'=>'Private actor description','DTSTART'=>gmdate('Ymd\THis\Z',$future+7200)]);
 $service->syncUser($uid);$service->syncUser($other);
 $mine=$organizer->rows($uid)[$mine['id']];$peerAfter=$organizer->rows($other)[$peerRow['id']];
 $check($mine['title']==='Shared calendar title'&&$peerAfter['title']===$mine['title']&&$peerAfter['remind_at']===$future+7200,'Calendar import updates canonical shared title/time and both recipients');
 $check($mine['description']==='Private actor description'&&$peerAfter['description']==='Private peer description','Shared import keeps descriptions personal');
 $check($peerAfter['status']==='completed'&&$peerAfter['completed_at']===$peerRow['completed_at'],'Shared import does not reactivate another member');
 $dav->deleteCalendarObject((int)$mineLink['calendar_id'],$mineLink['object_uri']);$service->syncUser($uid);
 $check($organizer->rows($uid)[$mine['id']]['status']==='archived'&&$organizer->rows($other)[$peerRow['id']]['status']==='completed','Deleting one member event archives only that member reminder');
 $q=$db->getQueryBuilder();$r=$q->select('amount_cents','reminder_date','reminder_text')->from('cobudget_entries')->where($q->expr()->eq('id',$q->createNamedParameter($sharedPayment)))->executeQuery();$sharedData=$r->fetchAssociative();$r->closeCursor();
 $check((int)$sharedData['amount_cents']===10000&&(int)$sharedData['reminder_date']===$future+7200&&$sharedData['reminder_text']==='Shared calendar title','Deletion preserves shared payment and its canonical reminder');
 // Stale editor drafts fail after a calendar import; importing past dates does not replay delivery.
 $row=$newReminder();$editLink=$reminderLinks($row['id'])[0];
 $calendarEdit($editLink,['DESCRIPTION'=>'From calendar']);$service->syncUser($uid);
 try{$organizer->save($uid,$row['id'],array_replace($row,['description'=>'Stale form']));throw new RuntimeException('Stale editor accepted');}catch(DomainException){$n++;}
 $calendarEdit($editLink,['DTSTART'=>gmdate('Ymd\THis\Z',time()-3600)]);$service->syncUser($uid);
 $check($organizer->rows($uid)[$row['id']]['notified'],'Past imports do not replay notifications');
 // Revocation is an access failure, never an instruction to archive.
 $access(2);$service->configure($uid,true,$sharedCal);$row=$newReminder();$access(3);$service->syncUser($uid);
 $check($organizer->rows($uid)[$row['id']]['status']==='active'&&$service->state($uid)['sync_pending'],'Revoked permissions preserve active reminder');
 $access(2);$service->configure($uid,true,$cal);

 echo "Calendar integration: $n assertions passed\n";
}finally{$db->rollBack();$session->setUser($original);}
