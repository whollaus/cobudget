<?php

declare(strict_types=1);

namespace OCA\CoBudget\Controller;

use OCA\CoBudget\Service\BackupService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class AdminBackupController extends Controller {
	private const APP_ID = 'cobudget';

	public function __construct(
		string $appName,
		IRequest $request,
		private BackupService $backupService,
		private LoggerInterface $logger,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
	) {
		parent::__construct($appName, $request);
	}

	public function settings(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			return new DataResponse([
				'settings' => $this->backupService->getFullBackupSettings(),
				'backups' => $this->backupService->listConfiguredFullBackups(),
			]);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup-Einstellungen konnten nicht geladen werden.', 'Failed to load full backup settings');
		}
	}

	public function saveSettings(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			$currentSettings = $this->backupService->getFullBackupSettings();
			$settings = $this->backupService->saveFullBackupSettings(
				(string)$this->request->getParam('storage_user_id', $currentSettings['storage_user_id']),
				(string)$this->request->getParam('storage_folder', $currentSettings['storage_folder']),
				$this->request->getParam('retention_count', $currentSettings['retention_count']),
				(string)$this->request->getParam('schedule', $currentSettings['schedule'])
			);

			return new DataResponse([
				'settings' => $settings,
				'backups' => $this->backupService->listConfiguredFullBackups(),
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup-Einstellungen konnten nicht gespeichert werden.', 'Invalid full backup settings', Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup-Einstellungen konnten nicht gespeichert werden.', 'Failed to save full backup settings');
		}
	}

	public function create(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			return new DataResponse([
				'backup' => $this->backupService->createConfiguredFullBackup(),
				'settings' => $this->backupService->getFullBackupSettings(),
				'backups' => $this->backupService->listConfiguredFullBackups(),
			], Http::STATUS_CREATED);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup konnte nicht erstellt werden.', 'Invalid full backup create request', Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup konnte nicht erstellt werden.', 'Failed to create full backup');
		}
	}

	public function destroy(string $fileName): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			$this->backupService->deleteConfiguredFullBackup($fileName);

			return new DataResponse([
				'settings' => $this->backupService->getFullBackupSettings(),
				'backups' => $this->backupService->listConfiguredFullBackups(),
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup konnte nicht gelöscht werden.', 'Invalid full backup delete request', Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup konnte nicht gelöscht werden.', 'Failed to delete full backup');
		}
	}

	/**
	 * @NoCSRFRequired
	 */
	public function download(string $fileName): FileDisplayResponse|DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			$file = $this->backupService->getConfiguredFullBackupFile($fileName);

			return new FileDisplayResponse($file, Http::STATUS_OK, [
				'Content-Type' => 'application/zip',
				'Content-Disposition' => 'attachment; filename="' . addslashes($file->getName()) . '"',
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup konnte nicht heruntergeladen werden.', 'Invalid full backup download request', Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup konnte nicht heruntergeladen werden.', 'Failed to download full backup');
		}
	}

	public function restore(): DataResponse {
		try {
			if ($adminError = $this->requireAdmin()) {
				return $adminError;
			}

			$restore = $this->backupService->restoreConfiguredFullBackup(
				(string)$this->request->getParam('file_name', ''),
				(string)$this->request->getParam('confirmation', '')
			);

			return new DataResponse([
				'restore' => $restore,
				'settings' => $this->backupService->getFullBackupSettings(),
				'backups' => $this->backupService->listConfiguredFullBackups(),
			]);
		} catch (\InvalidArgumentException $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup konnte nicht wiederhergestellt werden.', 'Invalid full backup restore request', Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return $this->loggedErrorResponse($e, 'Vollbackup konnte nicht wiederhergestellt werden.', 'Failed to restore full backup');
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
		return new DataResponse(['error' => $message], $status);
	}

	private function loggedErrorResponse(\Throwable $e, string $message, string $logMessage, int $status = Http::STATUS_INTERNAL_SERVER_ERROR): DataResponse {
		$this->logger->error($logMessage . ': ' . $e->getMessage(), [
			'app' => self::APP_ID,
			'exception' => $e,
		]);

		return $this->errorResponse($message, $status);
	}
}
