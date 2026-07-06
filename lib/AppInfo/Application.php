<?php
namespace OCA\CoBudget\AppInfo;

use OCA\CoBudget\Command\CreateBackupCommand;
use OCA\CoBudget\Command\CreateFullBackupCommand;
use OCA\CoBudget\Command\CheckDataIntegrityCommand;
use OCA\CoBudget\Command\ResetAllCommand;
use OCA\CoBudget\Command\RestoreBackupCommand;
use OCA\CoBudget\Command\RestoreFullBackupCommand;
use OCA\CoBudget\Cron\BackupJob;
use OCA\CoBudget\Cron\BudgetSnapshotJob;
use OCA\CoBudget\Cron\RecurringEntriesJob;
use OCA\CoBudget\Cron\RemindersJob;
use OCA\CoBudget\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IConfig;

class Application extends App implements IBootstrap {
	public const APP_ID = 'cobudget';
	private const ICON_CACHE_VERSION = 'app-svg-white-20260706';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		$context->registerCommand(CreateBackupCommand::class);
		$context->registerCommand(CreateFullBackupCommand::class);
		$context->registerCommand(CheckDataIntegrityCommand::class);
		$context->registerCommand(ResetAllCommand::class);
		$context->registerCommand(RestoreBackupCommand::class);
		$context->registerCommand(RestoreFullBackupCommand::class);
	}

	public function boot(IBootContext $context): void {
		$container = $context->getServerContainer();
		$jobList = $container->get(IJobList::class);
		$db = $container->get(IDBConnection::class);
		$config = $container->get(IConfig::class);
		foreach ([RecurringEntriesJob::class, RemindersJob::class, BackupJob::class, BudgetSnapshotJob::class] as $jobClass) {
			$this->ensureBackgroundJob($jobList, $jobClass);
			$this->prioritizeUnrunWebCronJob($db, $jobClass);
		}
		$this->refreshThemingIconCache($config);
	}

	private function ensureBackgroundJob(IJobList $jobList, string $jobClass): void {
		if (method_exists($jobList, 'has') && ($jobList->has($jobClass, null) || $jobList->has($jobClass, []))) {
			return;
		}

		$jobList->add($jobClass, []);
	}

	private function prioritizeUnrunWebCronJob(IDBConnection $db, string $jobClass): void {
		try {
			$qb = $db->getQueryBuilder();
			$qb->update('jobs')
				->set('last_checked', $qb->createNamedParameter(0, \PDO::PARAM_INT))
				->where($qb->expr()->eq('class', $qb->createNamedParameter($jobClass)))
				->andWhere($qb->expr()->eq('last_run', $qb->createNamedParameter(0, \PDO::PARAM_INT)))
				->andWhere($qb->expr()->neq('last_checked', $qb->createNamedParameter(0, \PDO::PARAM_INT)));
			$qb->executeStatement();
		} catch (\Throwable $e) {
			// Keep app boot resilient if a future Nextcloud version changes the jobs table internals.
		}
	}

	private function refreshThemingIconCache(IConfig $config): void {
		try {
			if ($config->getAppValue(self::APP_ID, 'theming_icon_cache_version', '') === self::ICON_CACHE_VERSION) {
				return;
			}

			$current = $config->getAppValue('theming', 'cachebuster', '0');
			$next = (string)max(time(), ((int)$current) + 1);
			if ($next === $current) {
				$next = (string)(((int)$next) + 1);
			}

			$config->setAppValue('theming', 'cachebuster', $next);
			$config->setAppValue(self::APP_ID, 'theming_icon_cache_version', self::ICON_CACHE_VERSION);
		} catch (\Throwable $e) {
			// Icon cache refresh must never prevent the app from booting.
		}
	}
}
