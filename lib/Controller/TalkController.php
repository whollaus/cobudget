<?php
declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\TalkNotificationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class TalkController extends Controller {
	public function __construct(
		string $appName, IRequest $request,
		private IUserSession $userSession,
		private TalkNotificationService $talk,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function state(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => $this->l10n->t('Not authenticated')], 401);
		}
		return new DataResponse($this->talk->state($user->getUID()));
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 60)]
	public function update(bool $enabled): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => $this->l10n->t('Not authenticated')], 401);
		}
		try {
			return new DataResponse($this->talk->setEnabled($user->getUID(), $enabled));
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $this->l10n->t('Personal Talk notifications could not be configured. Check that Talk is available or contact your administrator.')], 503);
		}
	}
}
