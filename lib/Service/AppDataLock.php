<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCA\CoBudget\Exception\AppBusyException;
use OCP\IConfig;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/** App-wide admission lock, shared by HTTP, jobs, OCC and user deletion. */
class AppDataLock {
	private int $depth = 0;
	private int $mode = 0;
	private bool $locked = false;

	public function __construct(private ILockingProvider $locks, private IConfig $config) {
	}

	public static function key(): string {
		return hash('sha256', 'cobudget:app-data:v1');
	}

	public function enter(bool $exclusive = false): void {
		$mode = $exclusive ? ILockingProvider::LOCK_EXCLUSIVE : ILockingProvider::LOCK_SHARED;
		if ($this->depth > 0) {
			// Callers needing exclusivity must take it at their outermost boundary.
			// Never drop a shared lock to upgrade: that would open a race window.
			if ($exclusive && $this->mode !== ILockingProvider::LOCK_EXCLUSIVE) {
				throw new AppBusyException();
			}
			$this->depth++;
			return;
		}

		$enabled = $this->config->getSystemValueBool('filelocking.enabled', true);
		if (!$enabled && $exclusive) {
			throw new \RuntimeException('CoBudget backup and restore require Nextcloud file locking (filelocking.enabled).');
		}
		if ($enabled) {
			$shared = false;
			try {
				$this->locks->acquireLock(self::key(), ILockingProvider::LOCK_SHARED);
				$shared = true;
				if ($exclusive) {
					// changeLock also invalidates the DB provider's cached shared lock.
					// A direct exclusive acquisition after a previous shared operation
					// would leave that cache stale in long-running cron/OCC processes.
					$this->locks->changeLock(self::key(), ILockingProvider::LOCK_EXCLUSIVE);
				}
			} catch (\Throwable $e) {
				if ($shared) {
					$this->locks->releaseLock(self::key(), ILockingProvider::LOCK_SHARED);
				}
				if ($e instanceof LockedException) {
					throw new AppBusyException($e);
				}
				throw $e;
			}
		}
		$this->locked = $enabled;
		$this->mode = $mode;
		$this->depth = 1;
	}

	public function leave(): void {
		if ($this->depth === 0 || --$this->depth > 0) {
			return;
		}
		$mode = $this->mode;
		$this->mode = 0;
		if ($this->locked) {
			$this->locked = false;
			$this->locks->releaseLock(self::key(), $mode);
		}
	}
}
