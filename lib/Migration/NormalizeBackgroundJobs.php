<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use OCA\CoBudget\Cron\BackupJob;
use OCA\CoBudget\Cron\BudgetSnapshotJob;
use OCA\CoBudget\Cron\RecurringEntriesJob;
use OCA\CoBudget\Cron\RemindersJob;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

final class NormalizeBackgroundJobs implements IRepairStep {
	private const APP_ID = 'cobudget';

	/** @var list<class-string> */
	private const JOB_CLASSES = [
		RecurringEntriesJob::class,
		RemindersJob::class,
		BackupJob::class,
		BudgetSnapshotJob::class,
	];

	public function __construct(
		private IJobList $jobList,
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Normalize CoBudget background jobs';
	}

	public function run(IOutput $output): void {
		$ownsTransaction = !$this->db->inTransaction();
		$currentJobClass = null;

		try {
			if ($ownsTransaction) {
				$this->db->beginTransaction();
			}

			foreach (self::JOB_CLASSES as $jobClass) {
				$currentJobClass = $jobClass;
				if (!$this->jobList->has($jobClass, null)) {
					$this->jobList->add($jobClass);
				}

				// Older CoBudget releases registered a second job with [] arguments.
				$this->jobList->remove($jobClass, []);
			}

			if ($ownsTransaction) {
				$this->db->commit();
			}
		} catch (\Throwable $e) {
			if ($ownsTransaction && $this->db->inTransaction()) {
				try {
					$this->db->rollBack();
				} catch (\Throwable $rollbackError) {
					$this->logger->critical('Failed to roll back CoBudget background job normalization.', [
						'app' => self::APP_ID,
						'exception' => $rollbackError,
					]);
				}
			}

			$this->logger->error('Failed to normalize CoBudget background jobs.', [
				'app' => self::APP_ID,
				'jobClass' => $currentJobClass,
				'exception' => $e,
			]);
			$output->warning('Could not normalize CoBudget background jobs. See the Nextcloud log for details.');
			throw $e;
		}

		$output->info('CoBudget background jobs use the canonical null arguments; legacy [] jobs were removed.');
	}
}
