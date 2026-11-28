<?php

declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\DataChangeService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;

final class SyncController extends Controller {
	use WorkspaceAwareTrait;

	private IDBConnection $db;
	private ?string $userId;

	public function __construct(
		string $appName,
		IRequest $request,
		IDBConnection $db,
		IUserSession $userSession,
		private DataChangeService $dataChangeService,
		private IConfig $config,
	) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$user = $userSession->getUser();
		$this->userId = $user?->getUID();
		$this->initWorkspace();
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 120, period: 60)]
	public function state(bool $checkAppVersion = false): DataResponse {
		try {
			if ($error = $this->authErrorResponse()) {
				return $error;
			}

			$state = [
				'revision' => $this->dataChangeService->revisionForUser($this->userId),
			];
			if ($checkAppVersion) {
				$state['appVersion'] = $this->config->getAppValue($this->appName, 'installed_version', '');
			}
			$response = new DataResponse($state);
			$response->addHeader('Cache-Control', 'no-store, private');
			return $response;
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e);
		}
	}
}
