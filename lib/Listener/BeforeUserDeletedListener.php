<?php

declare(strict_types=1);

namespace OCA\CoBudget\Listener;

use OCA\CoBudget\Service\AppDataLock;
use OCA\CoBudget\Service\UserDeletionService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Server;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<BeforeUserDeletedEvent|UserDeletedEvent> */
class BeforeUserDeletedListener implements IEventListener {
	private array $pendingUsers = [];

	public function __construct(
		private UserDeletionService $userDeletionService,
		private LoggerInterface $logger,
		private \OCA\CoBudget\Service\CalendarReminderService $calendarReminders,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$userId = $event->getUser()->getUID();
			if (isset($this->pendingUsers[$userId])) {
				unset($this->pendingUsers[$userId]);
				Server::get(AppDataLock::class)->leave();
			}
			return;
		}
		if (!$event instanceof BeforeUserDeletedEvent) {
			return;
		}

		$access = Server::get(AppDataLock::class);
		$access->enter();
		$user = $event->getUser();
		try {
			$report = $this->userDeletionService->deleteUser($user->getUID(), $user->getDisplayName());
			$this->calendarReminders->forgetUser($user->getUID());
			$this->logger->info('CoBudget prepared data for a deleted Nextcloud user', [
				'app' => 'cobudget',
				'report' => $report,
			]);
			// Cover Nextcloud's actual account deletion too, not only our cleanup.
			// If core deletion aborts, native request-end cleanup releases the lock.
			$this->pendingUsers[$user->getUID()] = true;
		} catch (\Throwable $e) {
			$access->leave();
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
