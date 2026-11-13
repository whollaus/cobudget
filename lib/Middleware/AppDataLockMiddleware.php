<?php

declare(strict_types=1);

namespace OCA\CoBudget\Middleware;

use OCA\CoBudget\Controller\AdminBackupController;
use OCA\CoBudget\Controller\BackupController;
use OCA\CoBudget\Exception\AppBusyException;
use OCA\CoBudget\Service\AppDataLock;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IL10N;

class AppDataLockMiddleware extends Middleware {
	private bool $entered = false;

	public function __construct(private AppDataLock $lock, private IL10N $l10n) {
	}

	public function beforeController(Controller $controller, string $methodName): void {
		$exclusive = ($controller instanceof BackupController || $controller instanceof AdminBackupController)
			&& in_array($methodName, ['create', 'restore'], true);
		// GETs can perform lazy normalization, so they participate as well.
		$this->lock->enter($exclusive);
		$this->entered = true;
		if (method_exists($controller, 'refreshWorkspaceContext')) {
			$controller->refreshWorkspaceContext();
		}
	}

	public function afterController(Controller $controller, string $methodName, Response $response): Response {
		$this->release();
		return $response;
	}

	public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
		$this->release();
		if ($exception instanceof AppBusyException) {
			return new DataResponse([
				'error' => $this->l10n->t('CoBudget is temporarily busy with a backup, restore or another operation. Please try again shortly.'),
				'code' => 'cobudget_busy',
			], 503, ['Retry-After' => '5']);
		}
		throw $exception;
	}

	private function release(): void {
		if ($this->entered) {
			$this->entered = false;
			$this->lock->leave();
		}
	}
}
