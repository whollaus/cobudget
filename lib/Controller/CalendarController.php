<?php
declare(strict_types=1);
namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\CalendarReminderService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class CalendarController extends Controller {
	public function __construct(string $appName, IRequest $request, private IUserSession $userSession, private CalendarReminderService $calendar, private IL10N $l10n) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function state(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) return new DataResponse(['error' => $this->l10n->t('Not authenticated')], 401);
		try { return new DataResponse($this->calendar->state($user->getUID())); }
		catch (\Throwable $e) {
			$this->calendar->logFailure($e, 'settings-read');
			return new DataResponse(['error' => $this->l10n->t('Calendar settings could not be loaded or saved.')], 503);
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function update(bool $enabled, string $calendarId = ''): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) return new DataResponse(['error' => $this->l10n->t('Not authenticated')], 401);
		try { return new DataResponse($this->calendar->configure($user->getUID(), $enabled, $calendarId)); }
		catch (\InvalidArgumentException $e) { return new DataResponse(['error' => $this->l10n->t('Choose a writable calendar.')], 400); }
		catch (\Throwable $e) {
			$this->calendar->logFailure($e, 'settings-save');
			return new DataResponse(['error' => $this->l10n->t('Calendar settings could not be loaded or saved.')], 503);
		}
	}
}
