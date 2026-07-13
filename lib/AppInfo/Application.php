<?php
namespace OCA\CoBudget\AppInfo;

use OCA\CoBudget\Cron\BackupJob;
use OCA\CoBudget\Cron\BudgetSnapshotJob;
use OCA\CoBudget\Cron\RecurringEntriesJob;
use OCA\CoBudget\Cron\RemindersJob;
use OCA\CoBudget\Notification\Notifier;
use OCA\CoBudget\Listener\BeforeUserDeletedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\User\Events\BeforeUserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'cobudget';
	private const ICON_CACHE_VERSION = 'app-svg-white-20260706';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeUserDeletedEvent::class, BeforeUserDeletedListener::class);
		if (method_exists($context, 'registerNotifierService')) {
			$context->registerNotifierService(Notifier::class);
		}
		// OCC commands are registered in appinfo/register_command.php for compatibility with Nextcloud 34+.
	}

	public function boot(IBootContext $context): void {
		if (!method_exists($context, 'getServerContainer')) {
			return;
		}

		$container = null;
		try {
			$container = $context->getServerContainer();
			$jobList = $container->get(IJobList::class);
			$db = $container->get(IDBConnection::class);
			foreach ([RecurringEntriesJob::class, RemindersJob::class, BackupJob::class, BudgetSnapshotJob::class] as $jobClass) {
				try {
					$this->ensureBackgroundJob($jobList, $jobClass);
					$this->prioritizeUnrunWebCronJob($db, $jobClass);
				} catch (\Throwable $e) {
					// Background job registration must not block unrelated Nextcloud pages.
				}
			}
		} catch (\Throwable $e) {
			// Keep app boot resilient if a future Nextcloud version changes bootstrap services.
		}

		try {
			$this->refreshThemingIconCache($container->get(IConfig::class));
		} catch (\Throwable $e) {
			// Icon cache refresh must never prevent the app from booting.
		}
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
