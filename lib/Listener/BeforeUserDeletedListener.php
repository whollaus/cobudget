<?php

declare(strict_types=1);

namespace OCA\CoBudget\Listener;

use OCA\CoBudget\Service\UserDeletionService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\BeforeUserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<BeforeUserDeletedEvent> */
class BeforeUserDeletedListener implements IEventListener {
	public function __construct(
		private UserDeletionService $userDeletionService,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeUserDeletedEvent) {
			return;
		}

		$user = $event->getUser();
		try {
			$report = $this->userDeletionService->deleteUser($user->getUID(), $user->getDisplayName());
			$this->logger->info('CoBudget prepared data for a deleted Nextcloud user', [
				'app' => 'cobudget',
				'report' => $report,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('CoBudget could not safely prepare a Nextcloud user deletion', [
				'app' => 'cobudget',
				'user_id' => $user->getUID(),
				'exception_class' => $e::class,
				'exception_message' => $e->getMessage(),
				'exception' => $e,
			]);
			throw $e;
		}
	}
}
