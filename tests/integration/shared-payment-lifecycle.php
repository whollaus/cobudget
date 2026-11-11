<?php
declare(strict_types=1);

// Local integration check: all fixture writes are rolled back, including on failure.
$options = getopt('', ['nextcloud:', 'user:', 'other-user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'], $options['other-user'])) {
	fwrite(STDERR, "Usage: php tests/integration/shared-payment-lifecycle.php --nextcloud=/path/to/local/server --user=admin --other-user=testuser\n");
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
$calendarProperty = new ReflectionProperty($changes, 'calendarReminders');
$originalCalendar = $calendarProperty->getValue($changes);
$calendarProperty->setValue($changes, new class extends \OCA\CoBudget\Service\CalendarReminderService {
 public function __construct() {}
 public function syncUser(string $userId): void {}
});
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


$other = Server::get(IUserManager::class)->get($options['other-user']);
if (!$other || $other->getUID() === $uid) throw new RuntimeException('Two different existing local test accounts required');
$users = [$uid => $user, $other->getUID() => $other];
$rows = static function(string $table, string $column, int $id) use ($db): array {
 $q = $db->getQueryBuilder();
 return $q->select('*')->from('cobudget_' . $table)->where($q->expr()->eq($column, $q->createNamedParameter($id, \PDO::PARAM_INT)))->orderBy('id')->executeQuery()->fetchAll();
};
$row = static fn(int $id): array => $rows('entries', 'id', $id)[0] ?? [];
$expect = static function($response, int $status, string $message) use ($check): void {
 $check($response->getStatus() === $status, $message . ': got ' . $response->getStatus() . ' ' . json_encode($response->getData()));
};
$db->beginTransaction();
try {
 $workspaces = [];
 foreach ($users as $userId => $account) $workspaces[$userId] = $insert('workspaces', ['name' => '__Shared lifecycle fixture__', 'user_id' => $userId]);
 $actor = static function(string $userId, string $class = EntryController::class) use ($session, $users, $workspaces, $fresh): object {
  $session->setUser($users[$userId]);
  return $fresh($workspaces[$userId], $class);
 };
 $area = static function(string $owner, array $members) use ($insert, $workspaces): int {
  $id = $insert('projects', ['name' => '__Shared lifecycle area__', 'owner_id' => $owner, 'workspace_id' => $workspaces[$owner], 'created_at' => time()]);
  foreach ($members as $member) $insert('members', ['project_id' => $id, 'user_id' => $member, 'share_basis_points' => intdiv(10000, count($members)), 'personal_workspace_id' => $workspaces[$member]]);
  return $id;
 };
 $date = time() - 86400;
 foreach ([array_keys($users), array_reverse(array_keys($users))] as [$u1, $u2]) {
  $project = $area($u1, [$u1, $u2]);
  $otherProject = $area($u1, [$u1, $u2]);
  $create = static function(string $creator, float $amount = 100.01) use ($actor, $data, $date, $project): int {
   return (int)$data($actor($creator)->create(amount: $amount, description: '__Lifecycle #original__', date: $date, projectId: $project, splitMode: 'project_shares'))['id'];
  };
  $update = static function(string $editor, int $id, array $overrides = []) use ($actor, $date, $project) {
   return $actor($editor)->update(...array_replace(['id' => $id, 'amount' => 120.03, 'description' => '__Changed #edited__', 'date' => $date, 'projectId' => $project, 'splitMode' => 'project_shares'], $overrides));
  };
  $id = $create($u1);
  $check($row($id)['entry_kind'] === 'shared' && $row($id)['created_by'] === $u1, 'Canonical source retains original creator');
  $personal = $rows('entries', 'source_entry_id', $id);
  $check(count($personal) === 2 && array_sum(array_column($personal, 'amount_cents')) === 10001, 'Two exact-cent projections total 100.01');
  foreach ($personal as $p) {
   $check((bool)$p['is_locked'] && (int)$p['workspace_id'] === $workspaces[$p['user_id']], 'Open projection locked in its owner workspace');
   $shown = $data($actor($p['user_id'])->show((int)$p['id']))['entry'];
   $check($shown['source_amount_cents'] === 10001 && (int)round($shown['amount'] * 100) === (int)$p['amount_cents'], 'Detail separates exact source total and personal share: ' . json_encode(array_intersect_key($shown, array_flip(['amount_cents','source_amount_cents','is_locked','source_entry_id','area_is_settled']))));
   $listed = $data($actor($p['user_id'])->index())['entries'];
   $listed = current(array_filter($listed, fn($e) => (int)$e['id'] === (int)$p['id']));
   $check($listed && $listed['source_amount_cents'] === 10001 && (int)round($listed['amount'] * 100) === (int)$p['amount_cents'], 'Personal list exposes source total without changing counted amount');

   $expect($update($p['user_id'], (int)$p['id']), 409, 'Direct locked projection edit denied');
  }
  $data($update($u2, $id, ['userId' => $u2]));
  $check($row($id)['created_by'] === $u1 && $row($id)['user_id'] === $u2, 'Member can change payer without becoming creator');
  $personal = $rows('entries', 'source_entry_id', $id);
  $check(array_sum(array_column($personal, 'amount_cents')) === 12003, 'Member edit updates both exact-cent projections');
  foreach ($personal as $p) {
   $check($p['description'] === '__Changed #edited__', 'Description synchronized');
   $check($data($actor($p['user_id'])->show((int)$p['id']))['entry']['source_amount_cents'] === 12003, 'Tooltip total follows source edits');
   $history = $rows('entry_history', 'entry_id', (int)$p['id']);
   $check(count($history) > 0 && count(array_filter($history, fn($h) => $h['changed_by'] !== $u2)) === 0, 'Member edit recorded with actual editor in each personal history');
   $amountHistory = array_values(array_filter($history, fn($h) => $h['field'] === 'amount_cents'));
   $check(count($amountHistory) === 1 && (int)$amountHistory[0]['new_value'] === (int)$p['amount_cents'], 'Personal history records personal cents rather than shared total');
  }
  $memberProjection = current(array_filter($personal, fn($p) => $p['user_id'] === $u2));
  $expect($actor($u2)->destroy((int)$memberProjection['id']), 403, 'Locked projection cannot bypass creator deletion permission');
  $session->setUser($users[$u1]);
  $expect($fresh($workspaces[$u2])->destroy($id), 403, 'Foreign workspace header rejected');
  $expect($actor($u2)->destroy($id), 403, 'Noncreator member cannot delete despite changed payer');
  $expect($update($u2, $id, ['projectId' => $otherProject]), 409, 'Open source cannot move areas');
  $expect($update($u1, $id, ['projectId' => null]), 409, 'Open source cannot remove area');
  $expect($update($u2, $id, ['userId' => '__not_a_member__']), 403, 'Nonmember payer rejected');
  $expect($update($u2, $id, ['splitMode' => 'single_user', 'splitUserId' => '__not_a_member__']), 403, 'Nonmember allocation rejected');
  $data($update($u2, $id, ['splitMode' => 'single_user', 'splitUserId' => $u1]));
  $single = $rows('entries', 'source_entry_id', $id);
  $check(count($single) === 1 && $single[0]['user_id'] === $u1 && (int)$single[0]['amount_cents'] === 12003, '100 percent allocation removes obsolete projection');
  $data($update($u1, $id));
  $personal = $rows('entries', 'source_entry_id', $id);
  $check(count($personal) === 2 && array_sum(array_column($personal, 'amount_cents')) === 12003, 'Restoring split recreates exact projections');
  $income = $create($u2, 3.03);
  $data($update($u1, $income, ['type' => 'income', 'amount' => 6.05, 'userId' => $u2]));
  $incomeCopies = $rows('entries', 'source_entry_id', $income);
  $check(count($incomeCopies) === 2 && array_sum(array_column($incomeCopies, 'amount_cents')) === 605 && count(array_filter($incomeCopies, fn($p) => $p['type'] !== 'income')) === 0, 'Type changes keep income projections and rounding consistent');
  $data($actor($u2)->destroy((int)current(array_filter($incomeCopies, fn($p) => $p['user_id'] === $u2))['id']));
  $check($row($income) === [] && $rows('entries', 'source_entry_id', $income) === [], 'Creator deletion through personal projection resolves complete source graph');
  $expect($actor($u2, \OCA\CoBudget\Controller\ProjectController::class)->settle($project), 403, 'Only area owner can settle');
  $settled = $data($actor($u1, \OCA\CoBudget\Controller\ProjectController::class)->settle($project));
  $settlement = (int)$settled['settlement']['id'];
  $check(array_sum(array_column($settled['settlement']['transfers'], 'amountCents')) === 6001 || array_sum(array_column($settled['settlement']['transfers'], 'amountCents')) === 6002, 'Settlement preserves the exact odd-cent allocation');
  $data($actor($u1, \OCA\CoBudget\Controller\ProjectController::class)->archive($project));
  $snapshot = static fn(): array => [$row($id), $rows('entry_shares', 'entry_id', $id), $rows('settlement_balances', 'settlement_id', $settlement), $rows('settlement_transfers', 'settlement_id', $settlement)];
  $before = $snapshot();
  foreach ([$u1, $u2] as $editor) {
   $expect($update($editor, $id), 403, 'Settled source edit denied');
   $expect($actor($editor)->destroy($id), 403, 'Settled source deletion denied');
  }
  foreach ($personal as $p) {
   $pid = (int)$p['id']; $owner = $p['user_id']; $stranger = $owner === $u1 ? $u2 : $u1;
   $released = $row($pid);
   $check($data($actor($owner)->show($pid))['entry']['source_amount_cents'] === null, 'Released personal copies do not expose an open shared total');
   $check(!$released['is_locked'] && !$released['is_settled'] && $released['source_entry_id'] === null && (int)$released['settlement_id'] === $settlement, 'Settlement releases independent personal entry with origin');
   $otherBefore = $row((int)current(array_filter($personal, fn($x) => $x['user_id'] === $stranger))['id']);
   $expect($update($stranger, $pid), 404, 'Other member cannot edit personal copy');
   $expect($actor($stranger)->destroy($pid), 404, 'Other member cannot delete personal copy');
   $expect($update($owner, $pid, ['projectId' => $otherProject]), 409, 'Released copy cannot change original area');
   $expect($update($owner, $pid, ['projectId' => null]), 409, 'Released copy cannot clear original area');
   $data($update($owner, $pid, ['amount' => 7.23, 'type' => 'income', 'description' => '__Private changed__', 'date' => $date - 3600, 'isInsurance' => true, 'userId' => $stranger, 'splitMode' => 'single_user', 'splitUserId' => $stranger]));
   $edited = $row($pid);
   $check($edited['user_id'] === $owner && $edited['split_user_id'] === $owner && $edited['split_mode'] === 'single_user' && $edited['entry_kind'] === 'personal' && (int)$edited['amount_cents'] === 723, 'Released edit remains entirely personal despite forged payer/allocation');
   $check($edited['type'] === 'income' && $edited['description'] === '__Private changed__' && (int)$edited['date'] === $date - 3600 && (bool)$edited['is_insurance'], 'Personal date, type, description and labels remain independently editable');
   $check($snapshot() === $before && $row((int)$otherBefore['id']) === $otherBefore, 'Personal edit leaves historical settlement and other member unchanged');
  }
  foreach ($personal as $p) {
   $peer = current(array_filter($personal, fn($otherCopy) => $otherCopy['id'] !== $p['id']));
   $peerBefore = $row((int)$peer['id']);
   $data($actor($p['user_id'])->destroy((int)$p['id']));
   $check($row((int)$peer['id']) === $peerBefore, 'Personal deletion leaves the other personal copy unchanged');
   $check($row((int)$p['id']) === [], 'Owner can delete released personal copy');
   $check($snapshot() === $before, 'Personal deletion leaves settled source, shares and balances unchanged');
  }
  $data($actor($u1, \OCA\CoBudget\Controller\ProjectController::class)->unarchive($project));
  foreach ([$u1, $u2] as $creator) {
   $open = $create($creator, 9.99);
   $ids = array_column($rows('entries', 'source_entry_id', $open), 'id');
   $data($actor($creator)->destroy($open));
   $check($row($open) === [] && $rows('entry_shares', 'entry_id', $open) === [], 'Creator deletes open source and shares');
   foreach ($ids as $pid) $check($row((int)$pid) === [], 'Open deletion removes both projections');
  }
  $open = $create($u2, 5.01);
  $data($actor($u1)->destroy($open));
  $check($row($open) === [] && $rows('entries', 'source_entry_id', $open) === [], 'Area owner can delete member-created source and projections');
  echo "PASS lifecycle with owner {$u1}, member {$u2}\n";
 }
 // Payment editor organizer fields: atomic creation, private updates and stale-draft rollback.
 $organizer = Server::get(\OCA\CoBudget\Service\ReminderService::class);
 [$u1, $u2] = array_keys($users);
 $project = $area($u1, [$u1, $u2]);
 $at = time()+86400*7;
 $details = ['id'=>null, 'revision'=>0, 'description'=>'Private creator note', 'labels'=>['cancel'], 'completed'=>false, 'archived'=>false];
 $base = ['amount'=>42.50, 'date'=>$date, 'description'=>'__Reminder payment__', 'projectId'=>$project, 'reminderDate'=>$at, 'reminderText'=>'Cancel contract', 'reminderDetails'=>$details];
 $id = (int)$data($actor($u1)->create(...$base))['id'];
 $mine = array_values(array_filter($organizer->rows($u1), fn($r)=>(int)$r['entry_id']===$id))[0];
 $theirs = array_values(array_filter($organizer->rows($u2), fn($r)=>(int)$r['entry_id']===$id))[0];
 $check($mine['description']==='Private creator note' && $mine['labels']===['cancel'] && !$mine['keep_open'], 'Payment creation saves private details with automatic archival');
 $check(empty($theirs['description']) && $theirs['labels']===[], 'Creator private details never copy to other members');
 $peer = array_replace($details, ['id'=>$theirs['id'],'revision'=>$theirs['revision'],'description'=>'Private peer note','labels'=>['renew','pay'],'completed'=>true]);
 $data($actor($u2)->update(...array_replace($base, ['id'=>$id, 'reminderDetails'=>$peer])));
 $peerRow = $organizer->rows($u2)[$theirs['id']];
 $check($peerRow['description']==='Private peer note' && $peerRow['completed_at'] && $peerRow['labels']===['renew','pay'], 'Peer can save their own completion and labels via payment editor');
 $check($organizer->rows($u1)[$mine['id']]['description']==='Private creator note', 'Peer payment save preserves creator notes');
 $snapshot = $row($id);
 $expect($actor($u2)->update(...array_replace($base,['id'=>$id,'amount'=>99,'reminderDetails'=>$peer])),409,'Stale organizer revision rejects entire payment edit');
 $check($row($id)===$snapshot,'Stale reminder leaves financial payment unchanged');
 $peer['revision']=$peerRow['revision'];$peer['labels']=['forged'];
 $expect($actor($u2)->update(...array_replace($base,['id'=>$id,'amount'=>99,'reminderDetails'=>$peer])),400,'Invalid label rejects payment edit');
 $check($row($id)===$snapshot && $organizer->rows($u2)[$theirs['id']]['revision']===$peerRow['revision'],'Invalid details roll back payment and reminder');
 $count=count($rows('entries','workspace_id',$workspaces[$u1]));
 $expect($actor($u1)->create(...array_replace($base,['reminderDetails'=>array_replace($details,['labels'=>['forged']])])),400,'Invalid creation details rejected');
 $check(count($rows('entries','workspace_id',$workspaces[$u1]))===$count,'Invalid new reminder leaves no payment or projections');
 $peer=array_replace($peer,['revision'=>$peerRow['revision'],'labels'=>['review'],'archived'=>true]);
 $data($actor($u2)->update(...array_replace($base,['id'=>$id,'reminderDetails'=>$peer])));
 $peerRow=$organizer->rows($u2)[$theirs['id']];
 $check($peerRow['completed_at'] && $peerRow['archived_at'],'Payment editor preserves simultaneous completion and archive');
 $peer=array_replace($peer,['revision'=>$peerRow['revision'],'completed'=>false,'archived'=>false]);
 $data($actor($u2)->update(...array_replace($base,['id'=>$id,'reminderDate'=>$at+86400,'reminderDetails'=>$peer])));
 $peerRow=$organizer->rows($u2)[$theirs['id']];
 $check($peerRow['status']==='active' && !$peerRow['notified'],'Rescheduling reactivates delivery');
 $staleMine=array_replace($details,['id'=>$mine['id'],'revision'=>$mine['revision']]);
 $expect($actor($u1)->update(...array_replace($base,['id'=>$id,'reminderDetails'=>$staleMine])),409,'Changed shared source invalidates another member draft');
 $mine=$organizer->rows($u1)[$mine['id']];
 $check($mine['description']==='Private creator note' && $mine['remind_at']===$at+86400,'Source time sync preserves other recipient notes');
 $peer['revision']=$peerRow['revision'];
 $data($actor($u2)->update(...array_replace($base,['id'=>$id,'reminderDate'=>null,'reminderText'=>'','reminderDetails'=>$peer])));
 $check(!isset($organizer->rows($u1)[$mine['id']]) && !isset($organizer->rows($u2)[$theirs['id']]),'Explicit payment reminder removal clears linked reminders');
 $check((int)$row($id)['amount_cents']===4250,'Removing reminder preserves financial amount');
 echo "PASS payment reminder fields and atomic rollback\n";
 echo "PASS {$assertions} assertions; all fixture writes rolled back.\n";
} finally {
 $db->rollBack();
 $calendarProperty->setValue($changes, $originalCalendar);
 $session->setUser($originalUser);
}
