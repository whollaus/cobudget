<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCA\CoBudget\Cron\BackupJob;
use OCA\CoBudget\Cron\BudgetSnapshotJob;
use OCA\CoBudget\Cron\RecurringEntriesJob;
use OCA\CoBudget\Cron\RemindersJob;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\Server;

final class BackgroundJobService {
	private const JOB_CLASSES = [
		RecurringEntriesJob::class,
		RemindersJob::class,
		BackupJob::class,
		BudgetSnapshotJob::class,
	];

	public function __construct(
		private IJobList $jobList,
		private IConfig $config,
	) {
	}

	/**
	 * Checks construction and registration without executing or reserving jobs.
	 * Repair only restores missing registrations after every job can be loaded.
	 */
	public function check(bool $repair = false): array {
		$report = [
			'backgroundMode' => $this->config->getAppValue('core', 'backgroundjobs_mode', 'ajax'),
			'lastCron' => (int)$this->config->getAppValue('core', 'lastcron', '0'),
			'jobs' => [],
			'healthy' => false,
			'repairBlocked' => false,
		];
		$canRepair = true;

		foreach (self::JOB_CLASSES as $jobClass) {
			$status = [
				'class' => $jobClass,
				'file' => null,
				'interval' => null,
				'loadable' => false,
				'registered' => false,
				'restored' => false,
				'errors' => [],
			];

			try {
				// getJobsIterator()/getById() would delete jobs whose classes cannot be loaded.
				$status['registered'] = $this->isRegistered($jobClass);
				$job = Server::get($jobClass);
				if (!$job instanceof TimedJob) {
					throw new \UnexpectedValueException($jobClass . ' is not a timed background job.');
				}
				$status['file'] = (new \ReflectionClass($job))->getFileName() ?: null;
				// Access the interval to force the job constructor for PHP 8.4 lazy services.
				$status['interval'] = $job->getInterval();
				$status['loadable'] = true;
			} catch (\Throwable $e) {
				$status['errors'] = $this->exceptionMessages($e);
				$canRepair = false;
			}

			$report['jobs'][] = $status;
		}

		$report['repairBlocked'] = $repair && !$canRepair;
		if ($repair && $canRepair) {
			foreach ($report['jobs'] as &$status) {
				if ($status['registered']) {
					continue;
				}
				try {
					// Preserve existing jobs, their arguments, last-run timestamps and reservations.
					if (!$this->isRegistered($status['class'])) {
						$this->jobList->add($status['class']);
						$status['restored'] = true;
					}
					$status['registered'] = $this->isRegistered($status['class']);
				} catch (\Throwable $e) {
					$status['errors'] = $this->exceptionMessages($e);
				}
			}
			unset($status);
		}

		$report['healthy'] = array_reduce($report['jobs'], static fn (bool $healthy, array $status): bool =>
			$healthy && $status['loadable'] && $status['registered'] && $status['errors'] === [], true);

		return $report;
	}

	private function isRegistered(string $jobClass): bool {
		// Older versions used [] instead of null. Either registration can run these jobs.
		return $this->jobList->has($jobClass, null) || $this->jobList->has($jobClass, []);
	}

	/** @return list<string> */
	private function exceptionMessages(\Throwable $exception): array {
		$messages = [];
		do {
			$messages[] = get_class($exception) . ': ' . $exception->getMessage();
			$exception = $exception->getPrevious();
		} while ($exception !== null);

		return $messages;
	}
}
