<?php
declare(strict_types=1);
namespace OCA\CoBudget\Service;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\CalendarImpl;
use OCP\App\IAppManager;
use OCP\Calendar\IManager;
use OCP\IUserManager;
use OCP\Server;
use Sabre\VObject\Reader;

/** Version-specific DAV writes are isolated here; all targets come from the user's public calendar manager. */
class ReminderCalendarBackend {
	public function __construct(private IManager $calendars, private IAppManager $apps, private IUserManager $users) {}

	/** @return array<string, CalendarImpl> */
	public function writableCalendars(string $userId): array {
		$user = $this->users->get($userId);
		if (!$user || !$user->isEnabled() || !$this->apps->isEnabledForUser('dav', $user) || !class_exists(CalendarImpl::class)) return [];
		$result = [];
		foreach ($this->calendars->getCalendarsForPrincipal('principals/users/' . $userId) as $calendar) {
			if (!$calendar instanceof CalendarImpl || $calendar->isDeleted() || !$calendar->isWritable()) continue;
			$info = $this->backend()->getCalendarById((int)$calendar->getKey());
			$components = $info['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set'] ?? null;
			if (!$components || !in_array('VEVENT', $components->getValue(), true)) continue;
			$result[$calendar->getKey()] = $calendar;
		}
		return $result;
	}

	public function choices(string $userId): array {
		$result = [];
		foreach ($this->writableCalendars($userId) as $id => $calendar) {
			$result[] = ['id' => (string)$id, 'label' => $calendar->getDisplayName() ?: $calendar->getUri(), 'shared' => $calendar->isShared()];
		}
		return $result;
	}

	/** Read fresh DAV data under a row lock; callers hold a database transaction.
	 * Avoid CalDavBackend's request-local object cache for concurrent edits.
	 */
	public function read(string $userId, array $link): array {
		$calendar = $this->writableCalendars($userId)[$link['calendar_id']] ?? null;
		if (!$calendar) throw new \RuntimeException('Calendar unavailable');
		$info=$this->backend()->getCalendarById((int)$calendar->getKey());
		$personal = ($info['principaluri'] ?? null) === 'principals/users/'.$userId
			&& !$calendar->isShared() && $this->backend()->getShares((int)$calendar->getKey()) === [];
		$db = Server::get(\OCP\IDBConnection::class);
		$q = $db->getQueryBuilder();
		$q->select('calendardata', 'etag', 'deleted_at')->from('calendarobjects')
			->where($q->expr()->eq('calendarid', $q->createNamedParameter((int)$calendar->getKey())))
			->andWhere($q->expr()->eq('calendartype', $q->createNamedParameter(0)))
			->andWhere($q->expr()->eq('uri', $q->createNamedParameter($link['object_uri'])));
		if ($db->getDatabaseProvider() !== 'sqlite') $q->forUpdate();
		$result=$q->executeQuery();
		$object = $result->fetchAssociative(); $result->closeCursor();
		if (!$object) return ['missing'=>true, 'personal'=>$personal, 'etag'=>null, 'fields'=>null];
		if (is_resource($object['calendardata'])) $object['calendardata']=stream_get_contents($object['calendardata']);
		$this->assertOwned($object, $link);
		$base=['missing'=>$object['deleted_at'] !== null, 'personal'=>$personal, 'etag'=>$object['etag'], 'fields'=>null];
		if ($base['missing']) return $base;
		$v=Reader::read($object['calendardata']); $event=$v->VEVENT;
		if (count($v->select('VEVENT')) !== 1 || isset($event->RRULE) || isset($event->RDATE) || isset($event->RECURRENCE_ID) || !isset($event->DTSTART) || isset($event->ATTENDEE)) throw new \RuntimeException('Unsupported reminder event');
		$config=Server::get(\OCP\IConfig::class);
		try { $zone=new \DateTimeZone($config->getUserValue($userId,'core','timezone',date_default_timezone_get())); }
		catch (\Throwable) { $zone=new \DateTimeZone('UTC'); }
		$start=$event->DTSTART;
		if ($start->getValueType() === 'DATE') $date=\DateTimeImmutable::createFromFormat('!Ymd', (string)$start, $zone)->setTime(9,0)->getTimestamp();
		elseif ($start->isFloating()) $date=\DateTimeImmutable::createFromFormat('!Ymd\THis', (string)$start, $zone)->getTimestamp();
		else $date=$start->getDateTime()->getTimestamp();
		$fields=['title'=>trim((string)$event->SUMMARY), 'description'=>trim((string)$event->DESCRIPTION), 'remind_at'=>$date];
		if (mb_strlen($fields['title'])>255 || mb_strlen($fields['description'])>10000 || $date<1 || $date>4102444800) throw new \RuntimeException('Invalid reminder event fields');
		$base['fields']=$fields;
		$base['normalize']=$start->getValueType() === 'DATE' || $start->isFloating();
		return $base;
	}

	public function put(string $userId, array $link, string $ics, bool $changed): void {
		$calendar = $this->writableCalendars($userId)[$link['calendar_id']] ?? null;
		if (!$calendar) throw new \RuntimeException('Calendar unavailable');
		if (array_key_exists('expected_etag', $link)) {
			$fresh=$this->read($userId,$link);
			if ($fresh['etag'] !== $link['expected_etag']) throw new \DomainException('Calendar changed during synchronization');
		}
		$backend = $this->backend();
		$existing = $backend->getCalendarObject((int)$calendar->getKey(), $link['object_uri']);
		if ($existing) $this->assertOwned($existing, $link);
		if (!$existing || !empty($existing['{http://nextcloud.com/ns}deleted-at'])) {
			// No attendees: this creates no invitations or email messages.
			$calendar->createFromStringMinimal($link['object_uri'], $ics);
		} elseif ($changed) {
			$backend->updateCalendarObject((int)$calendar->getKey(), $link['object_uri'], $ics);
		}
	}

	public function remove(string $userId, array $link): void {
		$calendar = $this->writableCalendars($userId)[$link['calendar_id']] ?? null;
		if (!$calendar) {
			// A deleted calendar needs no cleanup; revoked permissions must never be bypassed.
			if ($this->backend()->getCalendarById((int)$link['calendar_id']) === null) return;
			throw new \RuntimeException('Calendar unavailable');
		}
		$existing = $this->backend()->getCalendarObject((int)$calendar->getKey(), $link['object_uri']);
		if (!$existing) return;
		$this->assertOwned($existing, $link);
		if (empty($existing['{http://nextcloud.com/ns}deleted-at'])) $this->backend()->deleteCalendarObject((int)$calendar->getKey(), $link['object_uri']);
	}

	private function assertOwned(array $object, array $link): void {
		$calendar = Reader::read($object['calendardata']);
		if ((string)$calendar->VEVENT->UID !== $link['event_uid'] || (string)$calendar->VEVENT->{'X-COBUDGET-REMINDER'} !== $link['event_uid']) {
			throw new \RuntimeException('Calendar object ownership changed');
		}
	}

	private function backend(): CalDavBackend {
		return Server::get(CalDavBackend::class);
	}
}
