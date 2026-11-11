<?php
declare(strict_types=1);
namespace OCA\CoBudget\Service;

use OCP\IDBConnection;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Component\VCalendar;

class CalendarReminderService {
	public const ENABLED = 'notify_reminders_calendar';
	public const TARGET = 'reminder_calendar_id';
	private const ERROR = 'reminder_calendar_sync_error';

	public function __construct(
		private IDBConnection $db, private IConfig $config, private ReminderCalendarBackend $backend,
		private ParticipantService $participants, private IURLGenerator $urls, private IFactory $l10n,
		private ILockingProvider $locks, private LoggerInterface $logger, private ReminderService $reminders,
	) {}

	public function state(string $userId): array {
		$choices = $this->backend->choices($userId);
		return [
			'available' => $choices !== [], 'calendars' => $choices,
			'enabled' => $this->config->getUserValue($userId, 'cobudget', self::ENABLED, 'no') === 'yes',
			'calendar_id' => $this->config->getUserValue($userId, 'cobudget', self::TARGET, ''),
			'sync_pending' => $this->config->getUserValue($userId, 'cobudget', self::ERROR, 'no') === 'yes',
		];
	}

	/** Read-only per-reminder state. Never contact DAV while rendering the overview. */
	public function withSyncState(string $userId, array $reminders): array {
		$enabled = $this->config->getUserValue($userId, 'cobudget', self::ENABLED, 'no') === 'yes';
		$target = $this->config->getUserValue($userId, 'cobudget', self::TARGET, '');
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('cobudget_calendar_links')->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))->executeQuery();
		$byReminder = $byEntry = [];
		foreach ($result->fetchAll() as $link) {
			if ($link['reminder_id'] !== null) $byReminder[(int)$link['reminder_id']][] = $link;
			elseif ($link['entry_id'] !== null) $byEntry[(int)$link['entry_id']][] = $link;
		}
		$result->closeCursor();
		foreach ($reminders as &$reminder) {
			$links = array_merge($byReminder[(int)$reminder['id']] ?? [], $byEntry[(int)$reminder['entry_id']] ?? []);
			$pending = false;
			$reminder['calendar_conflict'] = null;
			$current = false;
			foreach ($links as $link) {
				if (!$enabled || $link['calendar_id'] !== $target || !empty($reminder['dismissed'])) {
					$pending = true; // An old managed event still needs cleanup.
				} else {
					$current = true;
					if ($link['fingerprint'] !== $this->fingerprint($reminder)) $pending = true;
					$sync=json_decode($link['sync_state'] ?? 'null',true) ?: [];
					if (!empty($sync['conflict'])) { $reminder['calendar_conflict']=$sync['conflict']; $pending=true; }
				}
			}
			// Match reconciliation's opt-in rule: never flag intentionally skipped historical items.
			$eligible = $links !== [] || ($reminder['status'] === 'active' && (int)$reminder['remind_at'] >= time());
			$reminder['calendar_sync_pending'] = $pending || ($enabled && empty($reminder['dismissed']) && $eligible && !$current);
		}
		unset($reminder);
		return $reminders;
	}

	public function configure(string $userId, bool $enabled, string $calendarId): array {
		$this->locked($userId, function () use ($userId, $enabled, $calendarId): void {
			if ($enabled && !in_array($calendarId, array_column($this->backend->choices($userId), 'id'), true)) {
				throw new \InvalidArgumentException('Choose a writable calendar');
			}
			$this->config->setUserValue($userId, 'cobudget', self::ENABLED, $enabled ? 'yes' : 'no');
			if ($enabled) $this->config->setUserValue($userId, 'cobudget', self::TARGET, $calendarId);
			$this->reconcile($userId);
		});
		return $this->state($userId);
	}

	/** Called after committed financial changes; calendar failures must not fail the payment. */
	public function syncUser(string $userId): void {
		if ($userId === '' || ParticipantService::isReservedFormerId($userId)) return;
		try {
			$this->locked($userId, fn() => $this->reconcile($userId));
		} catch (\Throwable $e) {
			$this->failure($userId, $e);
		}
	}

	public function syncAll(): void {
		$users = $this->config->getUsersForUserValue('cobudget', self::ENABLED, 'yes');
		$qb = $this->db->getQueryBuilder();
		$result = $qb->selectDistinct('user_id')->from('cobudget_calendar_links')->executeQuery();
		$users = array_unique(array_merge($users, array_column($result->fetchAll(), 'user_id')));
		$result->closeCursor();
		foreach ($users as $uid) $this->syncUser((string)$uid);
	}

	/** Restores/resets discard server-local connections, including cached preferences. */
	public function resetConnections(?string $userId = null): void {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->selectDistinct('user_id')->from('cobudget_calendar_links')->executeQuery();
		$users = $userId !== null ? [$userId] : array_column($result->fetchAll(), 'user_id');
		$result->closeCursor();
		foreach ($users as $uid) {
			foreach ([self::ENABLED, self::TARGET, self::ERROR] as $key) $this->config->deleteUserValue($uid, 'cobudget', $key);
			$this->syncUser($uid);
		}
	}

	/** Account deletion must not retain the original account ID in operational mappings. */
	public function forgetUser(string $userId): void {
		$this->locked($userId, function () use ($userId): void {
			$this->config->setUserValue($userId, 'cobudget', self::ENABLED, 'no');
			$this->reconcile($userId);
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cobudget_calendar_links')->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))->executeStatement();
		});
	}

	private function reconcile(string $userId, ?array $resolution = null): void {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('cobudget_calendar_links')->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))->executeQuery();
		$links = array_column($result->fetchAll(), null, 'id');
		$previouslyLinked = array_fill_keys(array_column($links, 'entry_id'), true);
		$result->closeCursor();
		$enabled = $this->config->getUserValue($userId, 'cobudget', self::ENABLED, 'no') === 'yes';
		if (!$enabled && $links === []) return;
		if (!$this->participants->isActive($userId)) return;
		$target = $this->config->getUserValue($userId, 'cobudget', self::TARGET, '');
		$entries = $enabled ? $this->reminders->rows($userId) : [];
		// Adopt old calendar links without replacing their native event identities.
		$byEntry = array_column(array_filter($entries, fn($r) => $r['entry_id'] !== null), 'id', 'entry_id');
		foreach ($links as &$link) {
			if ($link['reminder_id'] === null && isset($byEntry[$link['entry_id']])) {
				$link['reminder_id'] = $byEntry[$link['entry_id']];
				$qb = $this->db->getQueryBuilder();
				$qb->update('cobudget_calendar_links')->set('reminder_id', $qb->createNamedParameter($link['reminder_id']))->where($qb->expr()->eq('id', $qb->createNamedParameter($link['id'])))->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))->executeStatement();
			}
		} unset($link);
		$previouslyLinked = array_fill_keys(array_column($links, 'reminder_id'), true);
		$failed = false;
		// Keep old links until native deletion succeeds, including calendar moves and revoked permissions.
		foreach ($links as $linkId => $link) {
			$entryId = (int)$link['reminder_id'];
			if (isset($entries[$entryId]) && $link['calendar_id'] === $target) continue;
			try {
				$this->backend->remove($userId, $link);
				$qb = $this->db->getQueryBuilder();
				$qb->delete('cobudget_calendar_links')->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$link['id'], \PDO::PARAM_INT)))
					->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))->executeStatement();
				unset($links[$linkId]);
			} catch (\Throwable $e) { $failed = true; $this->failure($userId, $e); }
		}
		$currentLinks = array_column(array_filter($links, fn($link) => $link['calendar_id'] === $target), null, 'reminder_id');
		foreach ($entries as $entryId => $entry) {
			$link = $currentLinks[$entryId] ?? null;
			if (!$link && !isset($previouslyLinked[$entryId]) && ((int)$entry['remind_at'] < time() || $entry['status'] !== 'active')) continue; // No historical flood when opting in.
			try {
				if (!$link) {
					$uid = bin2hex(random_bytes(24)) . '@cobudget';
					$link = ['user_id'=>$userId, 'entry_id'=>$entry['entry_id'], 'reminder_id'=>$entryId, 'calendar_id'=>$target, 'object_uri'=>'cobudget-'.bin2hex(random_bytes(24)).'.ics', 'event_uid'=>$uid, 'fingerprint'=>'', 'sync_state'=>null];
					$qb=$this->db->getQueryBuilder(); $qb->insert('cobudget_calendar_links');
					foreach ($link as $key=>$value) $qb->setValue($key,$qb->createNamedParameter($value));
					$qb->executeStatement(); $link['id']=(int)$qb->getLastInsertId();
				}
				if (!$this->syncEntry($userId,$entryId,$link,$resolution)) $failed=true;
			} catch (\Throwable $e) {
				if ($resolution !== null && $resolution['id'] === $entryId) throw $e;
				// Retain the baseline and event identity across transient failures.
				if (isset($link['id'])) $this->saveLink($userId,$link,['fingerprint'=>'']);
				$failed=true; $this->failure($userId,$e);
			}
		}
		$this->config->setUserValue($userId, 'cobudget', self::ERROR, $failed ? 'yes' : 'no');
	}

	/** Resolve against the current values, not a stale dialog snapshot. */
	public function resolve(string $uid, int $id, string $token, array $choices): void {
		$this->locked($uid,function()use($uid,$id,$token,$choices) {
			if (!isset($this->reminders->rows($uid)[$id])) throw new \OutOfBoundsException();
			if ($this->config->getUserValue($uid,'cobudget',self::ENABLED,'no') !== 'yes') throw new \DomainException();
			$q=$this->db->getQueryBuilder();
			$r=$q->select('id')->from('cobudget_calendar_links')->where($q->expr()->eq('user_id',$q->createNamedParameter($uid)))
				->andWhere($q->expr()->eq('reminder_id',$q->createNamedParameter($id)))
				->andWhere($q->expr()->eq('calendar_id',$q->createNamedParameter($this->config->getUserValue($uid,'cobudget',self::TARGET,''))))->executeQuery();
			$exists=$r->fetchOne(); $r->closeCursor();
			if (!$exists) throw new \DomainException();
			$this->reconcile($uid,['id'=>$id,'token'=>$token,'choices'=>$choices]);
		});
	}

	private function saveLink(string $uid, array $link, array $values): void {
		$q=$this->db->getQueryBuilder(); $q->update('cobudget_calendar_links');
		foreach ($values as $k=>$v) $q->set($k,$q->createNamedParameter($v));
		$q->where($q->expr()->eq('id',$q->createNamedParameter((int)$link['id'])))
			->andWhere($q->expr()->eq('user_id',$q->createNamedParameter($uid)))->executeStatement();
	}

	private function syncEntry(string $uid, int $id, array $link, ?array $resolution): bool {
		$this->reminders->lockPaymentEdit($uid);
		$changed=null;
		$this->db->beginTransaction();
		try {
			$entry=$this->reminders->rows($uid)[$id] ?? null;
			if (!$entry) throw new \DomainException('Reminder changed');
			$remote=$this->backend->read($uid,$link);
			$state=json_decode($link['sync_state'] ?? 'null',true) ?: [];
			$local=ReminderCalendarMerge::fields($entry);
			$published=isset($state['base']) || $link['fingerprint'] !== '';
			$resolve=$resolution !== null && $resolution['id'] === $id;
			if ($resolve && (empty($state['conflict']) || !$remote['personal'] || $remote['missing'])) throw new \DomainException('Calendar conflict changed');
			// Deleted events are tombstones. Only an explicit future reactivation can recreate them.
			if (!empty($state['deleted']) && !($entry['status']==='active' && $entry['remind_at']>time())) {
				$this->saveLink($uid,$link,['fingerprint'=>$this->fingerprint($entry)]);
				$this->db->commit(); return true;
			}
			$reactivated=!empty($state['deleted']);
			if ($remote['personal'] && $remote['missing'] && $published && !$reactivated) {
				$this->reminders->setStatus($uid,$id,'archived',(int)$entry['revision']);
				$entry=$this->reminders->rows($uid)[$id]; $changed=$entry;
				$state=['base'=>ReminderCalendarMerge::fields($entry),'deleted'=>true];
				$this->saveLink($uid,$link,['sync_state'=>json_encode($state,JSON_THROW_ON_ERROR),'fingerprint'=>$this->fingerprint($entry)]);
			} else {
				if ($remote['personal'] && !$remote['missing'] && !$reactivated) {
					$fields=$remote['fields'];
					// Strip only the legacy generated deadline suffix; never duplicate it into personal notes.
					$l=$this->l10n->get('cobudget',$this->config->getUserValue($uid,'core','lang','en'));
					if ($entry['due_date']) {
						$suffix=$l->t('Due date').': '.$entry['due_date'];
						if ($fields['description']===$suffix) $fields['description']='';
						elseif (str_ends_with($fields['description'],"\n\n".$suffix)) $fields['description']=substr($fields['description'],0,-strlen("\n\n".$suffix));
					}
					if (($state['base']['title'] ?? $local['title'])==='' && $fields['title']===$l->t('Reminders')) $fields['title']='';
					$merge=ReminderCalendarMerge::merge($state['base'] ?? null,$local,$fields);
					$token=hash('sha256',json_encode([$local,$fields,$entry['revision'],$remote['etag']],JSON_THROW_ON_ERROR));
					if ($resolve) {
						if (!hash_equals($token,$resolution['token']) || array_diff(array_keys($merge['fields']),array_keys($resolution['choices'])) || array_diff(array_keys($resolution['choices']),array_keys($merge['fields']))) throw new \DomainException('Calendar conflict changed');
						foreach ($resolution['choices'] as $field=>$choice) {
							if (!in_array($choice,['local','calendar'],true)) throw new \InvalidArgumentException();
							$merge['merged'][$field]=$merge['fields'][$field][$choice];
						}
					} elseif ($merge['fields'] !== []) {
						$state['conflict']=['token'=>$token,'fields'=>$merge['fields']];
						$this->saveLink($uid,$link,['sync_state'=>json_encode($state,JSON_THROW_ON_ERROR)]);
						$this->db->commit(); return false;
					}
					if ($merge['merged'] !== $local) {
						$entry=$this->reminders->importCalendar($uid,$entry,$merge['merged']); $changed=$entry;
					}
				}
				$ics=$this->event($uid,$entry,$link['event_uid']);
				$fingerprint=$this->fingerprint($entry);
				$link['expected_etag']=$remote['etag'];
				// Shared calendars remain export-only. Personal imports normalize the event (including 09:00 for all-day dates).
				$this->backend->put($uid,$link,$ics,$fingerprint!==$link['fingerprint'] || $reactivated || $changed!==null || $resolve || ($remote['personal'] && !empty($remote['normalize'])));
				$this->saveLink($uid,$link,['fingerprint'=>$fingerprint,'sync_state'=>json_encode(['base'=>ReminderCalendarMerge::fields($entry)],JSON_THROW_ON_ERROR)]);
			}
			$this->db->commit();
		} catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
		finally { $this->reminders->unlockPaymentEdit($uid); }
		if ($changed !== null) {
			// Reminder-domain notifications cannot recursively invoke calendar sync.
			$changes=\OCP\Server::get(DataChangeService::class);
			$changes->publishForEntry(['user_id'=>$uid,'entry_kind'=>$changed['entry_kind'],'project_id'=>$changed['project_id']],['reminders']);
		}
		return true;
	}

	private function fingerprint(array $entry): string {
		return hash('sha256', json_encode([$entry['remind_at'], $entry['title'], $entry['description'], $entry['due_date'], $entry['status'], $entry['id']], JSON_THROW_ON_ERROR));
	}

	private function event(string $userId, array $entry, string $uid): string {
		$l = $this->l10n->get('cobudget', $this->config->getUserValue($userId, 'core', 'lang', 'en'));
		$title = trim((string)($entry['title'] ?? '')) ?: $l->t('Reminders');
		$link = $this->urls->linkToRouteAbsolute('cobudget.page.index');
		$link .= '#/reminders?reminder=' . (int)$entry['id'];
		$calendar = new VCalendar();
		$event = $calendar->add('VEVENT', [
			'UID' => $uid, 'DTSTAMP' => gmdate('Ymd\THis\Z'),
			'DTSTART' => gmdate('Ymd\THis\Z', (int)$entry['remind_at']),
			'DTEND' => gmdate('Ymd\THis\Z', (int)$entry['remind_at'] + 60),
			'DESCRIPTION' => trim((string)($entry['description'] ?? '') . ($entry['due_date'] ? "\n\n" . $l->t('Due date') . ': ' . $entry['due_date'] : '')),
			'SUMMARY' => $title, 'URL' => $link, 'TRANSP' => 'TRANSPARENT', 'CLASS' => 'PRIVATE',
			'X-COBUDGET-REMINDER' => $uid,
		]);
		if ($entry['status'] === 'active') $event->add('VALARM', ['ACTION' => 'DISPLAY', 'TRIGGER' => 'PT0S', 'DESCRIPTION' => $title]);
		return $calendar->serialize();
	}

	private function locked(string $uid, \Closure $operation): mixed {
		// Nextcloud's database lock key column is VARCHAR(64). Hash namespace and identity together.
		$key = hash('sha256', 'cobudget:calendar-reminders:' . $uid);
		$this->locks->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		try { return $operation(); } finally { $this->locks->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE); }
	}

	/** Diagnostic locations/types only: exception messages and traces can contain private calendar data or SQL values. */
	public function logFailure(\Throwable $error, string $operation): void {
		$causes = [];
		for ($cause = $error; $cause !== null && count($causes) < 5; $cause = $cause->getPrevious()) {
			$causes[] = ['type' => get_class($cause), 'source' => basename($cause->getFile()) . ':' . $cause->getLine()];
		}
		$this->logger->warning('CoBudget calendar synchronization pending', [
			'app' => 'cobudget', 'operation' => $operation, 'exceptionType' => get_class($error), 'causes' => $causes,
		]);
	}

	private function failure(string $uid, \Throwable $e): void {
		$this->config->setUserValue($uid, 'cobudget', self::ERROR, 'yes');
		$this->logFailure($e, 'synchronize');
	}
}
