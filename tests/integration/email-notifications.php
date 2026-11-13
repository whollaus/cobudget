<?php
declare(strict_types=1);

// Real Nextcloud APIs/database, rollback-only fixtures, intercepted mail transport.
$options = getopt('', ['nextcloud:', 'user:']);
if (!isset($options['nextcloud'], $options['user'])) throw new RuntimeException('Pass --nextcloud and --user');
$root = realpath($options['nextcloud']);
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($root);
require $root . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR, $e . "\n"); exit(1); });

use OCP\Server;
use OCA\CoBudget\Service\EmailNotificationService;
use OCA\CoBudget\Service\ReminderService;
use OCA\CoBudget\Service\BackupService;

$mailer = new class(Server::get(OCP\Mail\IMailer::class)) extends OC\Mail\Mailer {
 public array $sent = [];
 public string $failure = '';
 public function __construct(private OCP\Mail\IMailer $factory) {}
 public function createMessage(): OC\Mail\Message { return $this->factory->createMessage(); }
 public function send(OCP\Mail\IMessage $message): array {
  if ($this->failure === 'throw') throw new RuntimeException('PRIVATE SMTP ERROR');
  if ($this->failure === 'reject') return ['rejected@example.invalid'];
  $this->sent[] = $message;
  return [];
 }
};
$user = new class($options['user']) extends OC\User\User {
 public ?string $address = 'recipient@example.invalid';
 public bool $enabled = true;
 public function __construct(private string $id) {}
 public function getUID(): string { return $this->id; }
 public function getEMailAddress(): ?string { return $this->address; }
 public function isEnabled(): bool { return $this->enabled; }
};
$users = new class($user) extends OC\User\Manager {
 public function __construct(private OCP\IUser $user) {}
 public function get($uid, array $excludeBackends = []): ?OCP\IUser { return $uid === $this->user->getUID() ? $this->user : null; }
};
$logger = new class extends Psr\Log\AbstractLogger {
 public array $records = [];
 public function log($level, string|Stringable $message, array $context = []): void { $this->records[] = [$message, $context]; }
};
$config = Server::get(OCP\IConfig::class); $db = Server::get(OCP\IDBConnection::class); $uid = $user->getUID();
$email = new EmailNotificationService($mailer, $users, $config, Server::get(OCP\L10N\IFactory::class), Server::get(OCP\IURLGenerator::class), $logger);
$reminders = Server::get(ReminderService::class);
$n = 0;
$check = static function(bool $ok, string $why) use (&$n): void { if (!$ok) throw new RuntimeException($why); $n++; };
$db->beginTransaction();
try {
 foreach (EmailNotificationService::SETTINGS as $key) $config->setUserValue($uid, 'cobudget', $key, 'no');
 $params = ['id'=>123,'title'=>'<script>not HTML</script>','description'=>'Personal reminder notes'];
 $check($email->send($uid,'reminder',$params) && count($mailer->sent) === 0, 'Opt-out suppresses delivery');
 $config->setUserValue($uid,'cobudget','notify_reminders_email','yes');
 $check($email->send($uid,'reminder',$params) && count($mailer->sent) === 1, 'Opt-in delivers');
 $check($mailer->sent[0]->getTo() === ['recipient@example.invalid'], 'Only current profile address');
 $check(str_contains($mailer->sent[0]->getPlainBody(), $params['title']) && str_contains($mailer->sent[0]->getPlainBody(),'#/reminders?reminder=123'), 'Plain reminder text and deep link');
 $user->address='changed@example.invalid'; $email->send($uid,'reminder',$params);
 $check($mailer->sent[1]->getTo() === ['changed@example.invalid'], 'Profile address changes take effect');
 foreach ([null,'invalid',"bad@example.invalid\r\nBcc: other@example.invalid"] as $address) { $user->address=$address; $email->send($uid,'reminder',$params); }
 $user->address='recipient@example.invalid';$user->enabled=false;$email->send($uid,'reminder',$params);$user->enabled=true;
 $email->send('former:fixture','reminder',$params);$email->send('missing-user','reminder',$params);
 $check(count($mailer->sent)===2,'Missing, invalid, disabled, former and absent recipients suppressed');
 foreach (['throw','reject'] as $failure) { $mailer->failure=$failure; $check(!$email->send($uid,'reminder',$params),'Failed transport returns retryable status'); }
 $check(!str_contains(json_encode($logger->records), 'PRIVATE SMTP ERROR'), 'Transport exception content omitted from logs');
 $mailer->failure='';
 foreach (['planned','entry','settlement'] as $i=>$event) {
  $config->setUserValue($uid,'cobudget',EmailNotificationService::SETTINGS[$i+1],'yes');
  $config->setUserValue($uid,'cobudget','enable_projects','yes');$config->setUserValue($uid,'cobudget','enable_shared_projects','yes');
  $check($email->send($uid,$event,['type'=>'Expense','amount'=>'12.34','currency'=>'EUR','entryDate'=>'2026-11-12','actorName'=>'Actor','projectName'=>'Area','projectId'=>12,'description'=>'SECRET PAYMENT DESCRIPTION']), 'Other event delivery');
  $check(!str_contains(end($mailer->sent)->getPlainBody(),'SECRET PAYMENT DESCRIPTION'),'No payment description copied');
 }
 // Real organizer rows and persisted status: failed mail retries even after push is already done.
 $q=$db->getQueryBuilder();$q->update('cobudget_reminders')->set('email_notified',$q->createNamedParameter(true))->executeStatement();
 $q=$db->getQueryBuilder();$q->insert('cobudget_reminders');
 foreach (['user_id'=>$uid,'title'=>'Email test','remind_at'=>time()-60,'notified'=>true,'keep_open'=>true] as $k=>$v) $q->setValue($k,$q->createNamedParameter($v));
 $q->executeStatement();$id=(int)$q->getLastInsertId();
 $before=count($mailer->sent);$mailer->failure='throw';$reminders->deliverDueEmails($uid,time(),$email);
 $check(!$reminders->rows($uid)[$id]['email_notified'],'Failure retains pending mail');
 $mailer->failure='';$reminders->deliverDueEmails($uid,time(),$email);$reminders->deliverDueEmails($uid,time(),$email);
 $check(count($mailer->sent)===$before+1 && $reminders->rows($uid)[$id]['notified'],'Retry sends once without resetting push status');
 $row=$reminders->rows($uid)[$id];$reminders->save($uid,$id,array_replace($row,['remind_at'=>time()+3600]));
 $check(!$reminders->rows($uid)[$id]['email_notified'],'Rescheduling rearms email');
 $q=$db->getQueryBuilder();$q->update('cobudget_reminders')->set('remind_at',$q->createNamedParameter(time()-60))->set('completed_at',$q->createNamedParameter(time()))->where($q->expr()->eq('id',$q->createNamedParameter($id)))->executeStatement();
 $reminders->deliverDueEmails($uid,time(),$email);$check(count($mailer->sent)===$before+1,'Completed reminders suppressed');
 $backup=Server::get(BackupService::class);
 $method=new ReflectionMethod($backup,'remapGeneratedRowReferences');
 $restored=$method->invoke($backup,'cobudget_reminders',['id'=>1,'notified'=>true],[]);
 $check($restored['email_notified']===true,'Old backup does not replay delivered reminders');

 // Planned payment channels persist success separately while mail retries.
 $bell = new class(Server::get(OCP\Notification\IManager::class)) extends OC\Notification\Manager {
  public int $count=0;
  public function __construct(private OCP\Notification\IManager $factory) {}
  public function createNotification(): OCP\Notification\INotification { return $this->factory->createNotification(); }
  public function notify(OCP\Notification\INotification $notification): void { $this->count++; }
 };
 $talk = new class extends OCA\CoBudget\Service\TalkNotificationService {
  public function __construct() {}
  public function notifyPlannedPayment(string $userId, array $params): bool { return true; }
 };
 $planned = new OCA\CoBudget\Service\PlannedPaymentNotificationService($db,$config,Server::get(OCA\CoBudget\Service\ParticipantService::class),$bell,$talk,Server::get(OCP\Lock\ILockingProvider::class),$logger,$email);
 $q=$db->getQueryBuilder();$q->update('cobudget_entries')->set('planned_notified',$q->createNamedParameter(true))->executeStatement();
 $q=$db->getQueryBuilder();$q->insert('cobudget_workspaces')->setValue('user_id',$q->createNamedParameter($uid))->setValue('name',$q->createNamedParameter('Email fixture'))->executeStatement();$ws=(int)$q->getLastInsertId();
 $q=$db->getQueryBuilder();$q->insert('cobudget_entries');
 foreach (['workspace_id'=>$ws,'user_id'=>$uid,'created_by'=>$uid,'entry_kind'=>'personal','type'=>'expense','date'=>time()-60,'amount'=>'12.34','amount_cents'=>1234,'currency'=>'EUR','planned_notified'=>0] as $k=>$v) $q->setValue($k,$q->createNamedParameter($v));
 $q->executeStatement();
 $config->setUserValue($uid,'cobudget','notify_planned_payments','yes');
 $config->setUserValue($uid,'cobudget','notify_planned_payments_talk','no');
 $before=count($mailer->sent);$mailer->failure='throw';$planned->processDue(time());
 $check($bell->count===1 && count($mailer->sent)===$before,'Push succeeds while email fails: '.json_encode([$bell->count,count($mailer->sent),$before,$logger->records]));
 $mailer->failure='';$planned->processDue(time());$planned->processDue(time());
 $check($bell->count===1 && count($mailer->sent)===$before+1,'Planned mail retries independently and stops after success');
 // Read/write all four authenticated settings, and verify backup/reset registration.
 $session=Server::get(OCP\IUserSession::class);$original=$session->getUser();$session->setUser(Server::get(OCP\IUserManager::class)->get($uid));
 try {
  $ref=new ReflectionClass(OCA\CoBudget\Controller\UserController::class);
  $controller=$ref->newInstanceArgs(array_map(fn($p)=>$p->getName()==='appName'?'cobudget':($p->getType()->getName()===OCP\IL10N::class?Server::get(OCP\L10N\IFactory::class)->get('cobudget','en'):Server::get($p->getType()->getName())),$ref->getConstructor()->getParameters()));
  $response=$controller->saveSettings(notify_reminders_email:true,notify_planned_payments_email:false,notify_project_entries_email:true,notify_project_settlements_email:false);
  $check($response->getStatus()===200,'Settings save succeeds');
  $settings=$controller->getSettings()->getData();
  $check($settings['notify_reminders_email'] && !$settings['notify_planned_payments_email'] && $settings['notify_project_entries_email'] && !$settings['notify_project_settlements_email'],'All email settings independently roundtrip');
 } finally { $session->setUser($original); }
 echo "Email integration passed: $n assertions; no external messages sent.\n";
} finally { $db->rollBack(); }
