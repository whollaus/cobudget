<?php

namespace OCA\CoBudget\Cron;

use OCA\CoBudget\Service\BackupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class BackupJob extends TimedJob {
	private const JOB_INTERVAL_SECONDS = 5 * 60;
	private const BACKUP_HOUR = 3;
	private const BACKUP_MINUTE = 0;
	private const BACKUP_LOCK_TTL_SECONDS = 6 * 60 * 60;
	private const APP_ID = 'cobudget';
	private const LAST_AUTO_BACKUP_KEY = 'backup_last_auto_at';
	private const BACKUP_LOCK_KEY = 'backup_running_since';
	private const FULL_LAST_AUTO_BACKUP_KEY = 'full_backup_last_auto_at';
	private const FULL_BACKUP_LOCK_USER = '__cobudget_full_backup__';
	private const FULL_BACKUP_LOCK_KEY = 'full_backup_running_since';

	public function __construct(
		ITimeFactory $timeFactory,
		private IDBConnection $db,
		private IConfig $config,
		private BackupService $backupService,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(self::JOB_INTERVAL_SECONDS);
	}

	protected function run($argument): void {
		$now = time();
		$this->runFullBackup($now);

		foreach ($this->scheduledUserIds() as $userId) {
			$schedule = $this->backupService->getBackupSchedule($userId);
			if ($schedule === 'none') {
				continue;
			}

			$lastRun = (int)$this->config->getUserValue($userId, self::APP_ID, self::LAST_AUTO_BACKUP_KEY, '0');
			if (!$this->isDue($schedule, $lastRun, $now)) {
				continue;
			}

			$backupLock = $this->acquireUserBackupLock($userId, time());
			if ($backupLock === null) {
				continue;
			}

			try {
				$this->backupService->createBackup($userId);
				$this->config->setUserValue($userId, self::APP_ID, self::LAST_AUTO_BACKUP_KEY, (string)time());
			} catch (\Throwable $e) {
				$this->logger->error('Failed to create automatic CoBudget backup for user ' . $userId . ': ' . $e->getMessage(), ['app' => self::APP_ID]);
			} finally {
				$this->releaseUserBackupLock($userId, $backupLock);
			}
		}
	}

	private function runFullBackup(int $now): void {
		$settings = $this->backupService->getFullBackupSettings();
		$schedule = (string)$settings['schedule'];
		if ($schedule === 'none') {
			return;
		}

		$lastRun = (int)$this->config->getAppValue(self::APP_ID, self::FULL_LAST_AUTO_BACKUP_KEY, '0');
		if (!$this->isDue($schedule, $lastRun, $now)) {
			return;
		}

		$backupLock = $this->acquireFullBackupLock(time());
		if ($backupLock === null) {
			return;
		}

		try {
			$this->backupService->createConfiguredFullBackup();
			$this->config->setAppValue(self::APP_ID, self::FULL_LAST_AUTO_BACKUP_KEY, (string)time());
		} catch (\Throwable $e) {
			$this->logger->error('Failed to create automatic full CoBudget backup: ' . $e->getMessage(), ['app' => self::APP_ID]);
		} finally {
			$this->releaseFullBackupLock($backupLock);
		}
	}

	/**
	 * @return string[]
	 */
	private function scheduledUserIds(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('userid')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(self::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('backup_schedule')))
			->andWhere($qb->expr()->neq('configvalue', $qb->createNamedParameter('none')));

		$result = $qb->executeQuery();
		try {
			return array_values(array_filter(array_map('strval', $result->fetchAll(\PDO::FETCH_COLUMN))));
		} finally {
			$result->closeCursor();
		}
	}

	private function isDue(string $schedule, int $lastRun, int $now): bool {
		$currentSlot = $this->currentScheduleSlot($now);
		if ($currentSlot === null) {
			return false;
		}

		if ($lastRun <= 0) {
			return true;
		}

		if ($lastRun >= $currentSlot) {
			return false;
		}

		return $currentSlot >= $this->nextDueSlot($schedule, $lastRun);
	}

	private function currentScheduleSlot(int $now): ?int {
		$currentSlot = $this->localDateTime($now)
			->setTime(self::BACKUP_HOUR, self::BACKUP_MINUTE, 0)
			->getTimestamp();

		if ($now < $currentSlot) {
			return null;
		}

		return $currentSlot;
	}

	private function nextDueSlot(string $schedule, int $lastRun): int {
		$lastRunSlot = $this->lastRunScheduleSlot($lastRun);
		if ($schedule === 'weekly') {
			return $lastRunSlot->modify('+1 week')->getTimestamp();
		}

		if ($schedule === 'monthly') {
			return $lastRunSlot->modify('+1 month')->getTimestamp();
		}

		return $lastRunSlot->modify('+1 day')->getTimestamp();
	}

	private function lastRunScheduleSlot(int $lastRun): \DateTimeImmutable {
		$lastRunDate = $this->localDateTime($lastRun);
		$slot = $lastRunDate->setTime(self::BACKUP_HOUR, self::BACKUP_MINUTE, 0);
		if ($lastRun < $slot->getTimestamp()) {
			return $slot->modify('-1 day');
		}

		return $slot;
	}

	private function localDateTime(int $timestamp): \DateTimeImmutable {
		return (new \DateTimeImmutable('@' . $timestamp))
			->setTimezone(new \DateTimeZone(date_default_timezone_get()));
	}

	private function acquireUserBackupLock(string $userId, int $now): ?string {
		$this->deleteStaleUserBackupLock($userId, $now);
		return $this->acquireBackupLock($userId, self::BACKUP_LOCK_KEY, $now);
	}

	private function acquireFullBackupLock(int $now): ?string {
		$this->deleteStaleFullBackupLock($now);
		return $this->acquireBackupLock(self::FULL_BACKUP_LOCK_USER, self::FULL_BACKUP_LOCK_KEY, $now);
	}

	private function acquireBackupLock(string $userId, string $configKey, int $now): ?string {
		$lockValue = (string)$now;

		$qb = $this->db->getQueryBuilder();
		try {
			$qb->insert('preferences')
				->values([
					'userid' => $qb->createNamedParameter($userId),
					'appid' => $qb->createNamedParameter(self::APP_ID),
					'configkey' => $qb->createNamedParameter($configKey),
					'configvalue' => $qb->createNamedParameter($lockValue),
				]);
			$qb->executeStatement();

			return $lockValue;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function deleteStaleUserBackupLock(string $userId, int $now): void {
		$this->deleteStaleBackupLock($userId, self::BACKUP_LOCK_KEY, $now);
	}

	private function deleteStaleFullBackupLock(int $now): void {
		$this->deleteStaleBackupLock(self::FULL_BACKUP_LOCK_USER, self::FULL_BACKUP_LOCK_KEY, $now);
	}

	private function deleteStaleBackupLock(string $userId, string $configKey, int $now): void {
		$staleBefore = (string)($now - self::BACKUP_LOCK_TTL_SECONDS);
		$qb = $this->db->getQueryBuilder();
		$qb->delete('preferences')
			->where($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('appid', $qb->createNamedParameter(self::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($configKey)))
			->andWhere($qb->expr()->lt('configvalue', $qb->createNamedParameter($staleBefore)));
		$qb->executeStatement();
	}

	private function releaseUserBackupLock(string $userId, string $lockValue): void {
		$this->releaseBackupLock($userId, self::BACKUP_LOCK_KEY, $lockValue);
	}

	private function releaseFullBackupLock(string $lockValue): void {
		$this->releaseBackupLock(self::FULL_BACKUP_LOCK_USER, self::FULL_BACKUP_LOCK_KEY, $lockValue);
	}

	private function releaseBackupLock(string $userId, string $configKey, string $lockValue): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('preferences')
			->where($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('appid', $qb->createNamedParameter(self::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($configKey)))
			->andWhere($qb->expr()->eq('configvalue', $qb->createNamedParameter($lockValue)));
		$qb->executeStatement();
	}
}
