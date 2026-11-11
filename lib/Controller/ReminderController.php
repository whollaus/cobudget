<?php
declare(strict_types = 1);

namespace OCA\CoBudget\Controller;
use OCA\CoBudget\Service\ReminderService;
use OCA\CoBudget\Service\CalendarReminderService;
use OCA\CoBudget\Service\DataChangeService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IL10N;
class ReminderController extends Controller {

	public function __construct(string $appName, IRequest $request, private IUserSession $userSession, private ReminderService $reminders, private CalendarReminderService $calendar, private DataChangeService $changes, private IL10N $l10n) {
		parent::__construct($appName, $request);
	}
	#[NoAdminRequired] public function index(?int $entryId = null): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null)return new DataResponse(['error' => 'Not authenticated'], 401);
		return $this->run(fn() => ['reminders' => array_values(array_filter($this->calendar->withSyncState($user->getUID(), $this->reminders->rows($user->getUID(), $entryId !== null)), fn($r) => $entryId === null || $r['entry_id'] === $entryId))]);
	}
	#[NoAdminRequired] #[UserRateLimit(limit:10, period:60)] public function refreshCalendar(): DataResponse {
		$user=$this->userSession->getUser();
		if ($user === null) return new DataResponse(['error'=>'Not authenticated'],401);
		return $this->run(function()use($user) {
			$this->calendar->syncUser($user->getUID());
			return ['reminders'=>array_values($this->calendar->withSyncState($user->getUID(),$this->reminders->rows($user->getUID())))];
		});
	}
	#[NoAdminRequired] #[UserRateLimit(limit:10, period:60)] public function resolveCalendar(int $id, string $token, array $choices): DataResponse {
		$user=$this->userSession->getUser();
		if ($user === null) return new DataResponse(['error'=>'Not authenticated'],401);
		return $this->run(function()use($user,$id,$token,$choices) {
			$this->calendar->resolve($user->getUID(),$id,$token,$choices);
			return ['reminders'=>array_values($this->calendar->withSyncState($user->getUID(),$this->reminders->rows($user->getUID())))];
		});
	}
	#[NoAdminRequired] #[UserRateLimit(limit:10, period:60)] public function retryCalendar(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null)return new DataResponse(['error' => 'Not authenticated'], 401);
		return $this->run(function()use($user, $id) {
			$uid = $user->getUID();
			if (!isset($this->reminders->rows($uid)[$id])) throw new \OutOfBoundsException();
			// Retry current account settings; never overwrite a newer calendar selection.
			$this->calendar->syncUser($uid);
			$rows = $this->calendar->withSyncState($uid, $this->reminders->rows($uid));
			if (!isset($rows[$id])) throw new \OutOfBoundsException();
			return ['reminders' => array_values($rows)];
		});
	}
	#[NoAdminRequired] #[UserRateLimit(limit:120, period:60)] public function payments(string $search = ''): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null)return new DataResponse(['error' => 'Not authenticated'], 401);
		return $this->run(fn() => array_map(fn($e) => ['id' => (int)$e['id'], 'title' => (string)($e['description'] ?? ''), 'date' => (int)$e['date'], 'project_name' => $e['project_name'], 'has_reminder' => !empty($e['reminder_date'])], $this->reminders->paymentChoices($user->getUID(), $search)));
	}
	#[NoAdminRequired] #[UserRateLimit(limit:60, period:60)] public function save(array $data, ?int $id = null): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null)return new DataResponse(['error' => 'Not authenticated'], 401);
		return $this->run(function()use($user, $id, $data) {
			$row = $this->reminders->save($user->getUID(), $id, $data);
			$this->refresh($user->getUID(), $row);
			return $row;
		});
	}
	#[NoAdminRequired] #[UserRateLimit(limit:60, period:60)] public function postpone(int $id, int $revision, int $remindAt): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null)return new DataResponse(['error' => 'Not authenticated'], 401);
		return $this->run(function()use($user, $id, $revision, $remindAt) {
			$row = $this->reminders->postpone($user->getUID(), $id, $revision, $remindAt);
			$this->refresh($user->getUID(), $row);
			return $row;
		});
	}
	#[NoAdminRequired] #[UserRateLimit(limit:60, period:60)] public function status(int $id, string $status, int $revision): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null)return new DataResponse(['error' => 'Not authenticated'], 401);
		return $this->run(function()use($user, $id, $status, $revision) {
			$this->reminders->setStatus($user->getUID(), $id, $status, $revision);
			$this->refresh($user->getUID());
			return ['status' => 'success'];
		});
	}
	#[NoAdminRequired] #[UserRateLimit(limit:60, period:60)] public function destroy(int $id, int $revision): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null)return new DataResponse(['error' => 'Not authenticated'], 401);
		return $this->run(function()use($user, $id, $revision) {
			$this->reminders->remove($user->getUID(), $id, $revision);
			$this->refresh($user->getUID());
			return ['status' => 'success'];
		});
	}

	private function refresh(string $uid, ?array $row = null): void {
		$this->calendar->syncUser($uid);
		if ($row && $row['entry_id'])$this->changes->publishForEntry(['user_id' => $uid, 'entry_kind' => $row['entry_kind'], 'project_id' => $row['project_id']], ['entries']);
		else $this->changes->publishForUsers([$uid], ['reminders']);
	}

	private function run(\Closure $fn): DataResponse {
		try {
			return new DataResponse($fn());
		}
		catch (\OutOfBoundsException $e) {
			return new DataResponse(['error' => $this->l10n->t('Reminder or payment not found.')], 404);
		}
		catch (\DomainException $e) {
			return new DataResponse(['error' => $this->l10n->t('The reminder has changed or already exists. Reload the overview.')], 409);
		}
		catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $this->l10n->t('Check the reminder fields. To reactivate, choose a future reminder time.')], 400);
		}
		catch (\Throwable $e) {
			$this->calendar->logFailure($e, 'organizer');
			return new DataResponse(['error' => $this->l10n->t('Reminders could not be loaded or saved.')], 503);
		}
	}
}
