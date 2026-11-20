<?php

declare(strict_types=1);

namespace OCA\CoBudget\Cron;

use OCA\CoBudget\Exception\AppBusyException;
use OCA\CoBudget\Service\AppDataLock;
use OCA\CoBudget\Service\AutoImportRules;
use OCA\CoBudget\Service\AutoImportRunner;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

final class AutoImportJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private AppDataLock $access,
		private AutoImportRules $rules,
		private AutoImportRunner $runner,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(300);
		$this->setAllowParallelRuns(false);
	}

	protected function run($argument): void {
		try {
			$this->access->enter();
		} catch (AppBusyException) {
			return;
		}
		try {
			$deadline = microtime(true) + 20;
			foreach ($this->rules->due(time()) as $rule) {
				if (microtime(true) >= $deadline) break;
				try {
					$this->runner->runRule((int)$rule['id']);
				} catch (LockedException) {
					// The rule is currently being edited or an earlier run still owns it.
				} catch (\Throwable $e) {
					$this->logger->warning('CoBudget automatic import job deferred a rule', ['app' => 'cobudget', 'rule' => $rule['id'], 'exceptionType' => get_class($e)]);
				}
			}
		} finally {
			$this->access->leave();
		}
	}
}
