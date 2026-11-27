<?php

declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\ApiTokenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class ApiTokenController extends Controller {
	use WorkspaceAwareTrait;

	private ?string $userId;

	public function __construct(
		string $appName,
		IRequest $request,
		private IDBConnection $db,
		IUserSession $userSession,
		private IConfig $config,
		private IL10N $l10n,
		private ApiTokenService $apiTokenService,
	) {
		parent::__construct($appName, $request);
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
		$this->initWorkspace();
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function enableAccess(): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $this->applyNoStoreHeaders($error);
			}
			$this->apiTokenService->setAccessEnabled((string)$this->userId, true);
			return $this->applyNoStoreHeaders(new DataResponse(['enabled' => true]));
		} catch (\DomainException $e) {
			return $this->applyNoStoreHeaders(new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN));
		} catch (\Throwable $e) {
			return $this->applyNoStoreHeaders($this->loggedErrorResponse($e));
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function index(): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $this->applyNoStoreHeaders($error);
			}

			$this->getWorkspaceId();
			return $this->applyNoStoreHeaders(new DataResponse($this->apiTokenService->catalogForUser((string)$this->userId)));
		} catch (\Throwable $e) {
			return $this->applyNoStoreHeaders($this->loggedErrorResponse($e));
		}
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function create(
		string $name = '',
		int $workspaceId = 0,
		array $scopes = [],
		bool $allowPersonal = true,
		string $projectMode = 'none',
		array $projectIds = [],
		?int $expiresAt = null,
	): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $this->applyNoStoreHeaders($error);
			}

			$rawTargetPermissions = $this->request->getParam('targetPermissions', null);
			if ($rawTargetPermissions !== null && !is_array($rawTargetPermissions)) {
				return $this->applyNoStoreHeaders(new DataResponse(['error' => $this->l10n->t('Invalid permission selected.')], Http::STATUS_BAD_REQUEST));
			}

			$workspacesEnabled = $this->config->getUserValue((string)$this->userId, 'cobudget', 'enable_workspaces', 'no') === 'yes';
			if (!$workspacesEnabled || $workspaceId <= 0) {
				$targetWorkspaceId = $this->findDefaultWorkspaceId() ?? $this->createDefaultWorkspace() ?? 0;
			} else {
				$targetWorkspaceId = $workspaceId;
			}
			$result = $this->apiTokenService->create(
				(string)$this->userId,
				$name,
				$targetWorkspaceId,
				$scopes,
				$allowPersonal,
				$projectMode,
				$projectIds,
				$expiresAt,
				$rawTargetPermissions
			);

			return $this->applyNoStoreHeaders(new DataResponse($result, Http::STATUS_CREATED));
		} catch (\DomainException $e) {
			return $this->applyNoStoreHeaders(new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN));
		} catch (\InvalidArgumentException $e) {
			return $this->applyNoStoreHeaders(new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST));
		} catch (\Throwable $e) {
			return $this->applyNoStoreHeaders($this->loggedErrorResponse($e));
		}
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function update(
		int $id,
		string $name = '',
		array $scopes = [],
		bool $allowPersonal = true,
		string $projectMode = 'none',
		array $projectIds = [],
		?int $expiresAt = null,
		bool $isActive = true,
		string $revision = '',
	): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $this->applyNoStoreHeaders($error);
			}
			if ($validationError = $this->validatePositiveId($id)) {
				return $this->applyNoStoreHeaders($validationError);
			}

			$rawTargetPermissions = $this->request->getParam('targetPermissions', null);
			if ($rawTargetPermissions !== null && !is_array($rawTargetPermissions)) {
				return $this->applyNoStoreHeaders(new DataResponse(['error' => $this->l10n->t('Invalid permission selected.')], Http::STATUS_BAD_REQUEST));
			}

			$token = $this->apiTokenService->update(
				(string)$this->userId,
				$id,
				$name,
				$scopes,
				$allowPersonal,
				$projectMode,
				$projectIds,
				$expiresAt,
				$isActive,
				$rawTargetPermissions,
				$revision
			);

			return $this->applyNoStoreHeaders(new DataResponse(['token' => $token]));
		} catch (\UnexpectedValueException $e) {
			return $this->applyNoStoreHeaders(new DataResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT));
		} catch (\OutOfBoundsException) {
			return $this->applyNoStoreHeaders($this->errorResponse('API key not found.', Http::STATUS_NOT_FOUND));
		} catch (\DomainException $e) {
			return $this->applyNoStoreHeaders(new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN));
		} catch (\InvalidArgumentException $e) {
			return $this->applyNoStoreHeaders(new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST));
		} catch (\Throwable $e) {
			return $this->applyNoStoreHeaders($this->loggedErrorResponse($e));
		}
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function destroy(int $id): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $this->applyNoStoreHeaders($error);
			}
			if ($validationError = $this->validatePositiveId($id)) {
				return $this->applyNoStoreHeaders($validationError);
			}

			$this->apiTokenService->delete((string)$this->userId, $id);
			return $this->applyNoStoreHeaders(new DataResponse(['status' => 'success']));
		} catch (\OutOfBoundsException) {
			return $this->applyNoStoreHeaders($this->errorResponse('API key not found.', Http::STATUS_NOT_FOUND));
		} catch (\Throwable $e) {
			return $this->applyNoStoreHeaders($this->loggedErrorResponse($e));
		}
	}

	private function applyNoStoreHeaders(DataResponse $response): DataResponse {
		$response->addHeader('Cache-Control', 'no-store, private');
		$response->addHeader('Pragma', 'no-cache');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		return $response;
	}
}
