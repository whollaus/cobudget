<?php

declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\BackupService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class BackupController extends Controller {
	private const APP_ID = 'cobudget';

	private ?string $userId;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		private BackupService $backupService,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
	}

	/**
	 * @NoAdminRequired
	 */
	#[UserRateLimit(limit: 30, period: 60)]
	public function index(): DataResponse {
		if ($error = $this->authErrorResponse()) {
			return $error;
		}

		try {
			return new DataResponse([
				'backups' => $this->backupService->listBackups((string)$this->userId),
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Persönliche Exporte konnten nicht geladen werden.', Http::STATUS_INTERNAL_SERVER_ERROR, 'Failed to list personal exports');
		}
	}

	/**
	 * @NoAdminRequired
	 */
	#[UserRateLimit(limit: 3, period: 300)]
	public function create(): DataResponse {
		if ($error = $this->authErrorResponse()) {
			return $error;
		}

		try {
			$backup = $this->backupService->createBackup((string)$this->userId);
			return new DataResponse([
				'status' => 'success',
				'backup' => $backup,
				'backups' => $this->backupService->listBackups((string)$this->userId),
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export konnte nicht erstellt werden.', Http::STATUS_BAD_REQUEST, 'Failed to create personal export');
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export konnte nicht erstellt werden.', Http::STATUS_INTERNAL_SERVER_ERROR, 'Failed to create personal export');
		}
	}

	/**
	 * @NoAdminRequired
	 */
	#[UserRateLimit(limit: 10, period: 60)]
	public function inspect(string $fileName): DataResponse {
		if ($error = $this->authErrorResponse()) {
			return $error;
		}

		try {
			return new DataResponse([
				'backup' => $this->backupService->inspectBackup((string)$this->userId, $fileName),
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export konnte nicht geprüft werden.', Http::STATUS_BAD_REQUEST, 'Failed to inspect personal export');
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export konnte nicht geprüft werden.', Http::STATUS_INTERNAL_SERVER_ERROR, 'Failed to inspect personal export');
		}
	}

	/**
	 * @NoAdminRequired
	 */
	#[UserRateLimit(limit: 2, period: 300)]
	public function restore(string $fileName): DataResponse {
		if ($error = $this->authErrorResponse()) {
			return $error;
		}

		$confirmation = (string)$this->request->getParam('confirmation', '');
		if ($confirmation !== 'RESTORE') {
			return $this->errorResponse('Bitte Wiederherstellung mit RESTORE bestätigen.', Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->backupService->restoreBackup((string)$this->userId, $fileName);
			return new DataResponse([
				'status' => 'success',
				'result' => $result,
				'backups' => $this->backupService->listBackups((string)$this->userId),
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export konnte nicht wiederhergestellt werden.', Http::STATUS_BAD_REQUEST, 'Failed to restore personal export');
		} catch (\RuntimeException $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export konnte nicht wiederhergestellt werden.', Http::STATUS_CONFLICT, 'Failed to restore personal export');
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export konnte nicht wiederhergestellt werden.', Http::STATUS_INTERNAL_SERVER_ERROR, 'Failed to restore personal export');
		}
	}

	/**
	 * @NoAdminRequired
	 */
	#[UserRateLimit(limit: 10, period: 60)]
	public function destroy(string $fileName): DataResponse {
		if ($error = $this->authErrorResponse()) {
			return $error;
		}

		try {
			$this->backupService->deleteBackup((string)$this->userId, $fileName);
			return new DataResponse([
				'status' => 'success',
				'backups' => $this->backupService->listBackups((string)$this->userId),
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export konnte nicht gelöscht werden.', Http::STATUS_BAD_REQUEST, 'Failed to delete personal export');
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export wurde nicht gefunden.', Http::STATUS_NOT_FOUND, 'Failed to delete personal export');
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function download(string $fileName): FileDisplayResponse|DataResponse {
		if ($error = $this->authErrorResponse()) {
			return $error;
		}

		try {
			$file = $this->backupService->getBackupFile((string)$this->userId, $fileName);
			return new FileDisplayResponse($file, Http::STATUS_OK, [
				'Content-Type' => 'application/zip',
				'Content-Disposition' => 'attachment; filename="' . addslashes($file->getName()) . '"',
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export konnte nicht heruntergeladen werden.', Http::STATUS_BAD_REQUEST, 'Failed to download personal export');
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Persönlicher Export wurde nicht gefunden.', Http::STATUS_NOT_FOUND, 'Failed to download personal export');
		}
	}

	private function authErrorResponse(): ?DataResponse {
		if (!$this->userId) {
			return $this->errorResponse('Not authenticated', Http::STATUS_UNAUTHORIZED);
		}

		return null;
	}

	private function errorResponse(string $message, int $status): DataResponse {
		$message = $this->l10n->t($message);
		return new DataResponse(['error' => $message], $status);
	}

	private function loggedErrorResponse(\Throwable $e, string $message, int $status, string $logMessage): DataResponse {
		$this->logger->error($logMessage . ': ' . $e->getMessage(), [
			'app' => self::APP_ID,
			'exception' => $e,
			'userId' => $this->userId,
		]);

		return $this->errorResponse($message, $status);
	}
}
