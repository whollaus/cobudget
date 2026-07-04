<?php

declare(strict_types=1);

namespace OCA\CoBudget\Cron;

use OCA\CoBudget\Service\BudgetSnapshotService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class BudgetSnapshotJob extends TimedJob {
	private const JOB_INTERVAL_SECONDS = 6 * 60 * 60;
	private const APP_ID = 'cobudget';

	public function __construct(
		ITimeFactory $timeFactory,
		private BudgetSnapshotService $budgetSnapshotService,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(self::JOB_INTERVAL_SECONDS);
	}

	protected function run($argument): void {
		try {
			$this->budgetSnapshotService->createDueSnapshots();
		} catch (\Throwable $e) {
			$this->logger->error('Failed to create CoBudget budget snapshots: ' . $e->getMessage(), ['app' => self::APP_ID]);
		}
	}
}
