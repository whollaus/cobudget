<?php

declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\DataIntegrityService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class IntegrityController extends Controller {
	private const APP_ID = 'cobudget';

	public function __construct(
		string $appName,
		IRequest $request,
		private DataIntegrityService $dataIntegrityService,
		private LoggerInterface $logger,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
	) {
		parent::__construct($appName, $request);
	}

	public function inspect(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			return new DataResponse($this->dataIntegrityService->inspect());
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Datenqualitaet konnte nicht geprueft werden.', 'Failed to inspect data integrity');
		}
	}

	public function repair(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			$report = $this->dataIntegrityService->inspect();
			return new DataResponse($this->dataIntegrityService->repair($report));
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Datenqualitaet konnte nicht repariert werden.', 'Failed to repair data integrity');
		}
	}

	public function merge(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			$type = (string)$this->request->getParam('type', '');
			$keepId = (int)$this->request->getParam('keepId', 0);
			$mergeIds = $this->normalizeIdList($this->request->getParam('mergeIds', []));

			$result = $this->dataIntegrityService->mergeDuplicate($type, $keepId, $mergeIds);

			return new DataResponse([
				'merge' => $result,
				'report' => $this->dataIntegrityService->inspect(),
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->errorResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Dubletten konnten nicht zusammengefuehrt werden.', 'Failed to merge duplicate data');
		}
	}

	private function normalizeIdList(mixed $value): array {
		if (is_string($value)) {
			$value = explode(',', $value);
		}
		if (!is_array($value)) {
			return [];
		}

		return array_values(array_unique(array_filter(array_map(
			static fn (mixed $id): int => (int)$id,
			$value
		), static fn (int $id): bool => $id > 0)));
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
		return new DataResponse(['error' => $message], $status);
	}

	private function loggedErrorResponse(\Throwable $e, string $message, string $logMessage): DataResponse {
		$this->logger->error($logMessage . ': ' . $e->getMessage(), [
			'app' => self::APP_ID,
			'exception' => $e,
		]);

		return $this->errorResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
	}
}
