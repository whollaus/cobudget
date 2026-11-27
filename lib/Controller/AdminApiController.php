<?php

declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\ApiTokenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class AdminApiController extends Controller {
	private const APP_ID = 'cobudget';

	public function __construct(
		string $appName,
		IRequest $request,
		private ApiTokenService $apiTokenService,
		private LoggerInterface $logger,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request);
	}

	#[UserRateLimit(limit: 60, period: 60)]
	public function settings(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			return $this->applyNoStoreHeaders(new DataResponse([
				'enabled' => $this->apiTokenService->isGloballyEnabled(),
			]));
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'API settings could not be loaded.', 'Failed to load admin API settings');
		}
	}

	#[PasswordConfirmationRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function saveSettings(?bool $enabled = null): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}
			if ($enabled === null) {
				return $this->errorResponse('API settings could not be saved.', Http::STATUS_BAD_REQUEST);
			}

			$actorUserId = $this->userSession->getUser()?->getUID();
			$this->apiTokenService->setGloballyEnabled($enabled, $actorUserId);

			return $this->applyNoStoreHeaders(new DataResponse([
				'enabled' => $this->apiTokenService->isGloballyEnabled(),
			]));
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'API settings could not be saved.', 'Failed to save admin API settings');
		}
	}

	private function requireAdmin(): ?DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return $this->errorResponse('Authentication required', Http::STATUS_UNAUTHORIZED);
		}

		if (!$this->groupManager->isAdmin($user->getUID())) {
			return $this->errorResponse('Administrator permissions required', Http::STATUS_FORBIDDEN);
		}

		return null;
	}

	private function errorResponse(string $message, int $status): DataResponse {
		return $this->applyNoStoreHeaders(new DataResponse(['error' => $this->l10n->t($message)], $status));
	}

	private function loggedErrorResponse(\Throwable $e, string $message, string $logMessage, int $status = Http::STATUS_INTERNAL_SERVER_ERROR): DataResponse {
		$this->logger->error($logMessage . ': ' . $e->getMessage(), [
			'app' => self::APP_ID,
			'exception' => $e,
		]);

		return $this->errorResponse($message, $status);
	}

	private function applyNoStoreHeaders(DataResponse $response): DataResponse {
		$response->addHeader('Cache-Control', 'no-store, private');
		$response->addHeader('Pragma', 'no-cache');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		return $response;
	}
}
