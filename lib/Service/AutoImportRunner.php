<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCA\CoBudget\Controller\EntryController;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\AlreadyExistsException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorage;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

final class AutoImportRunner {
	public function __construct(
		private AutoImportRules $rules,
		private AutoImportLedger $ledger,
		private EntryController $entries,
		private IDBConnection $db,
		private IUserManager $users,
		private IFactory $languages,
		private IManager $notifications,
		private LoggerInterface $logger,
		private IConfig $config,
		private EmailNotificationService $email,
		private TalkNotificationService $talk,
		private IRootFolder $root,
	) {
	}

	public function runRule(int $id, int $packetBudget = 12, ?callable $beforeRun = null): string {
		return $this->rules->withLockedRule($id, function (?array $rule) use ($packetBudget, $beforeRun): string {
			if ($beforeRun !== null) $beforeRun($rule);
			if (!$rule || !$rule['enabled'] || $packetBudget < 1) return 'skipped';
			if (!$this->config->getSystemValueBool('filelocking.enabled', true)) return 'locking_disabled';
			return $this->rules->withDestinationLock($rule, fn () => $this->runLocked($rule, $packetBudget));
		});
	}

	private function runLocked(array $rule, int $packetBudget): string {
		$now = time();
		if ($this->config->getUserValue($rule['user_id'], 'cobudget', 'enable_payment_imports', 'no') !== 'yes') {
			return 'skipped';
		}
		$nextCheck = $now + $rule['interval_minutes'] * 60;
		// An early manual check preserves the next scheduled check.
		if ($rule['next_run'] > $now) $nextCheck = min($nextCheck, $rule['next_run']);
		$this->rules->schedule($rule['id'], $nextCheck);
		$user = $this->users->get($rule['user_id']);
		if (!$user || !$user->isEnabled()) return 'skipped';
		$l = $this->languages->get('cobudget', $this->languages->getUserLanguage($user));
		try {
			$folder = $this->rules->folderForRule($rule);
			$controller = $this->entries->forScheduledImport($rule['user_id'], $rule['workspace_id'], $rule['timezone']);
			// A hard stop during a daily or hourly delivery should resume on the next cron tick.
			$this->rules->schedule($rule['id'], min($now + 300, $nextCheck));
			$blockedFile = null;
			$finished = $this->scanInbox($rule, $folder, $controller, $l, $packetBudget, $blockedFile);
			if ($blockedFile !== null) {
				['name' => $name, 'id' => $id] = $blockedFile;
				$message = $blockedFile['message'] ?? $l->t('Cannot read or move %s. Check the file permissions.', [$name]);
				if ($rule['last_message'] !== $message || ($rule['last_file']['id'] ?? null) !== $id) {
					$this->report($rule, $message, true, $name, $id);
				}
			}
			if (!$finished) return 'more';
			$this->rules->schedule($rule['id'], $nextCheck);
			return 'checked';
		} catch (\DomainException $e) {
			$this->report($rule, $l->t($e->getMessage()), true);
		} catch (\Throwable $e) {
			$this->logger->warning('CoBudget automatic import deferred', ['app' => 'cobudget', 'rule' => $rule['id'], 'exceptionType' => get_class($e)]);
			$this->report($rule, $l->t('Automatic import could not continue. Check the folder and area permissions.'), true);
		}
		return 'error';
	}

	/** Leave the first file needing permission changes available for a single result after the scan. */
	private function scanInbox(array &$rule, Folder $folder, EntryController $controller, IL10N $l, int $packetBudget, ?array &$blockedFile): bool {
		$deadline = microtime(true) + 15;
		// Committed payments and their pending result survive removal of the inbox file.
		// With only one packet, give fresh work a turn before retrying a blocked delivery.
		$retryAfterScan = $packetBudget === 1;
		if (!$retryAfterScan && !$this->resumePending('waiting', $rule, $folder, $controller, $l, $packetBudget, $deadline, $blockedFile)) return false;
		if (!$this->resumePending('writing', $rule, $folder, $controller, $l, $packetBudget, $deadline, $blockedFile)) return false;
		// Only the versions selected before this pass may enter the payment journal.
		// Later uploads get their own complete pass over the outstanding sources.
		if ($rule['scan_batch'] === null) {
			[$candidates, $next] = $this->selectCandidates($folder, $rule, $l, $deadline, $blockedFile);
			$after = 0;
		} else {
			$candidates = json_decode($rule['scan_batch'], true, flags: JSON_THROW_ON_ERROR);
			$next = $rule['scan_from'];
			$after = (int)$rule['report_after'];
		}
		$sources = $this->ledger->pendingSourcesAfter($rule['id'], $after, 13);
		foreach ($sources as $index => $source) {
			if ($index === 12 || microtime(true) >= $deadline
				|| !$this->reconcileSource($rule, $folder, $source, $controller, $l, $packetBudget, $deadline, $blockedFile)) {
				$this->rules->checkpoint($rule['id'], $next, $after, $candidates);
				return false;
			}
			$after = (int)$source['id'];
		}

		foreach ($candidates as $candidate) {
			$name = $candidate['name'];
			if ($packetBudget < 1 || microtime(true) >= $deadline) {
				$this->rules->checkpoint($rule['id'], $name, 0);
				return false;
			}
			try {
				$file = $folder->get($name);
			} catch (NotFoundException|NotPermittedException) {
				continue;
			}
			if (!$file instanceof File) continue;
			if ($this->processFile($rule, $folder, $file, $controller, $l, $packetBudget, $deadline, $blockedFile, expectedVersion: $candidate) === false) {
				$this->rules->checkpoint($rule['id'], $name, 0);
				return false;
			}
		}
		if ($next !== null || $rule['scan_from'] !== null || $rule['scan_batch'] !== null || (int)$rule['report_after'] !== 0) {
			$this->rules->checkpoint($rule['id'], $next, 0);
		}
		if ($next !== null) return false;
		if ($retryAfterScan && $packetBudget > 0 && !$this->resumePending('waiting', $rule, $folder, $controller, $l, $packetBudget, $deadline, $blockedFile)) return false;
		return true;
	}

	/** Capture at most twelve eligible file versions and the next filename in storage order. */
	private function selectCandidates(Folder $folder, array $rule, IL10N $l, float $deadline, ?array &$blockedFile): array {
		$candidates = [];
		$waiting = $this->ledger->waitingSources($rule['id']);
		foreach ($this->deliveries($folder, $rule['scan_from']) as $file) {
			$name = $file->getName();
			if (count($candidates) === 12 || microtime(true) >= $deadline) return [$candidates, $name];
			$id = $file->getId();
			$etag = $file->getEtag();
			if ($etag !== '' && isset($waiting[$id]) && $waiting[$id]['source_etag'] === hash('sha256', $etag)) continue;
			if (!$file->isReadable() || !$file->isDeletable()) {
				$blockedFile ??= ['name' => $name, 'id' => $file->isReadable() ? $id : null];
				continue;
			}
			$candidate = ['name' => $name, 'id' => $id, 'etag' => $etag];
			try {
				// Files metadata may be cached across a concurrent sync. Pin the bytes before checking sources.
				$candidate['hash'] = AutoImportFile::fingerprintAndClose($file->fopen('r'));
			} catch (LockedException) {
				$blockedFile ??= ['name' => $name, 'id' => $id, 'message' => $l->t('%s is locked. CoBudget will retry it on the next check.', [$name])];
				continue;
			} catch (NotFoundException|NotPermittedException) {
				$blockedFile ??= ['name' => $name, 'id' => $id];
				continue;
			}
			$candidates[] = $candidate;
		}
		return [$candidates, null];
	}

	private function resumePending(string $state, array &$rule, Folder $folder, EntryController $controller, IL10N $l, int &$packetBudget, float $deadline, ?array &$blockedFile): bool {
		$row = $this->ledger->pending($rule['id'], $state);
		if ($row) {
			$input = AutoImportFile::fromSnapshot($row['snapshot']);
			// Reserve capacity for other deliveries when a dependency is still missing.
			$allowance = $state === 'waiting' ? min(1, $packetBudget) : $packetBudget;
			$remaining = $allowance;
			$finished = $this->advanceOrWait($rule, $folder, $row, $input, $controller, $remaining, $deadline, $l);
			$packetBudget -= $allowance - $remaining;
			if (!$finished && ($state === 'writing' || $packetBudget < 1)) return false;
			if ($row['state'] === 'reporting' || $row['state'] === 'waiting') {
				unset($input);
				return $this->reconcileSource($rule, $folder, $row, $controller, $l, $packetBudget, $deadline, $blockedFile);
			}
		}
		return true;
	}

	private function reconcileSource(array &$rule, Folder $folder, array $row, EntryController $controller, IL10N $l, int &$packetBudget, float $deadline, ?array &$blockedFile): bool {
		$name = $row['source_name'];
		$file = $name && $folder->nodeExists($name) ? $folder->get($name) : null;
		// A sync rename can keep the Files node ID while changing both filename and contents.
		$sourceId = (int)$row['detail'];
		$archived = null;
		if ($sourceId > 0 && $file?->getId() !== $sourceId) {
			$renamed = $folder->getFirstNodeById($sourceId);
			if ($renamed instanceof File) {
				$parent = $renamed->getParent()->getPath();
				if ($parent === $folder->getPath()) {
					$file = $renamed;
					if ($row['state'] === 'reporting' && $file->getName() !== $name) $this->ledger->reserveBookedName($rule, $file->getName(), $row);
				} elseif ($row['state'] !== 'waiting' && $parent === $folder->getPath() . '/Verarbeitet') {
					$archived = $renamed;
				}
			} elseif ($renamed === null && !empty($row['archive_name']) && !empty($row['archive_hash'])
				&& $this->root->getUserFolder($rule['user_id'])->getFirstNodeById($sourceId) === null) {
				// A move across mounts may change the ID. Verify the saved bytes at the intended path.
				$nameInArchive = $row['archive_name'];
				if (basename($nameInArchive) !== $nameInArchive || in_array($nameInArchive, ['.', '..'], true)) {
					throw new \UnexpectedValueException('Invalid archive filename');
				}
				try {
					$archived = $folder->get('Verarbeitet/' . $nameInArchive);
				} catch (NotFoundException) {
					// The move may still be pending or the archived file may already have been removed.
				}
			}
		}
		if ($archived instanceof File) {
			$this->verifyBookedArchive($rule, $folder, $archived, $row, $l);
			if (!$file instanceof File) return true;
			// A new upload can already occupy the original name. Check it before other inbox copies.
			$row['state'] = 'done';
		}
		if ($file instanceof File) {
			// Reserve a changed source before looking at any renamed copies elsewhere in the inbox.
			return $this->processFile($rule, $folder, $file, $controller, $l, $packetBudget, $deadline, $blockedFile, $row) !== false;
		}
		if (in_array($row['state'], ['reporting', 'archiving'], true)) $this->finishReport($rule, $row, $l);
		return true;
	}

	/** Include directly mounted children, which the parent storage's directory handle cannot see. */
	private function childNames(Folder $folder): \Generator {
		$mounts = [];
		foreach ($this->root->getMountsIn($folder->getPath()) as $mount) {
			$path = rtrim($mount->getMountPoint(), '/');
			if (dirname($path) === $folder->getPath()) {
				$name = basename($path);
				$mounts[$name] = true;
				yield $name;
			}
		}
		$handle = $folder->getStorage()->opendir($folder->getInternalPath());
		if (!is_resource($handle)) throw new \RuntimeException('Cannot list the import folder');
		try {
			while (($name = readdir($handle)) !== false) {
				if (!isset($mounts[$name])) yield $name;
			}
		} finally {
			closedir($handle);
		}
	}

	private function deliveries(Folder $folder, ?string $startAt): \Generator {
		foreach ($this->childNames($folder) as $name) {
			if ($startAt !== null && $name !== $startAt) continue;
			$startAt = null;
			if (str_starts_with($name, '.') || !str_ends_with(strtolower($name), '.json')
				|| stripos($name, ' (conflicted copy ') !== false) continue;
			try {
				$file = $folder->get($name);
			} catch (NotFoundException|NotPermittedException) {
				continue;
			}
			if ($file instanceof File) yield $file;
		}
		// The saved file may already have been moved by sync or by snapshot recovery.
		if ($startAt !== null) yield from $this->deliveries($folder, null);
	}

	/** False requests another tick before scanning further. Null skips only this file. */
	private function processFile(array &$rule, Folder $folder, File $file, EntryController $controller, IL10N $l, int &$budget, float $deadline, ?array &$blockedFile, ?array $source = null, ?array $expectedVersion = null): ?bool {
		$id = $file->getId();
		$name = $file->getName();
		$etag = $file->getEtag();
		// Sync may preserve an ETag while changing the bytes. Check every outstanding
		// source before admitting its batch, even if Files cannot move the source yet.
		$blockedResult = $source === null ? null : false;
		$readable = $file->isReadable();
		$canMove = $file->isDeletable();
		if (!$readable || !$canMove) {
			$blockedFile ??= ['name' => $name, 'id' => $readable ? $id : null];
			if (!$readable || $source === null) return $blockedResult;
		}
		$archive = 'Verarbeitet';
		$rejection = null;
		$row = null;
		$input = null;

		// Read one stable version. Nextcloud locks prevent a sync upload replacing it while payments are being written.
		try {
			$file->lock(ILockingProvider::LOCK_SHARED);
		} catch (NotFoundException) {
			return $blockedResult;
		} catch (NotPermittedException) {
			$blockedFile ??= ['name' => $name, 'id' => null];
			return $blockedResult;
		} catch (LockedException) {
			$blockedFile ??= ['name' => $name, 'id' => $id, 'message' => $l->t('%s is locked. CoBudget will retry it on the next check.', [$name])];
			return $blockedResult;
		}
		try {
			$current = $folder->get($name);
			if ($expectedVersion !== null && ($current->getId() !== $expectedVersion['id'] || $current->getEtag() !== $expectedVersion['etag'])) return null;
			if ($current->getId() !== $id || $current->getEtag() !== $etag) return false;
			try {
				$input = $this->readStreamAndClose($current->fopen('r'), $expectedVersion['hash'] ?? null, $rawHash);
				if ($input === null) return null;
			} catch (NotFoundException) {
				return $blockedResult;
			} catch (NotPermittedException) {
				$blockedFile ??= ['name' => $name, 'id' => null];
				return $blockedResult;
			}
			if (isset($source['source_name']) && $source['content_hash'] !== $input->contentHash) {
				$this->ledger->open($rule, $source['source_name'], $input, $id, $etag);
			}
			if (!$canMove) return null;
			$row = $this->ledger->open($rule, $name, $input, $id, $etag);
			if ($row['state'] === 'waiting') return null;
			if (in_array($row['state'], ['checking', 'writing'], true)) {
				if ($row['state'] === 'writing' && empty($row['snapshot'])) {
					$this->ledger->update((int)$row['id'], ['snapshot' => $input->snapshot()]);
				}
				if (!$this->advanceOrWait($rule, $folder, $row, $input, $controller, $budget, $deadline, $l)) return false;
				if ($row['state'] === 'waiting') return null;
			}
			if ($row['state'] === 'reporting' && $row['source_name'] !== $name) {
				$this->ledger->reserveBookedName($rule, $name, $row);
				// A separate copy has its own archive move. Keep following the original Files node.
				if ((int)$row['detail'] !== $id) $row = $this->ledger->open($rule, $name, $input, $id, $etag);
			} elseif ($row['state'] === 'done' && ($source['state'] ?? null) === 'reporting'
				&& $source['content_hash'] === $input->contentHash) {
				// Recover a stop between reserving the renamed file and archiving it.
				$row = $source;
			}
		} catch (\DomainException $e) {
			// A readable, unmovable source can reserve a conflicting payload under the shared lock.
			// Leave the report open until Files can archive the source.
			if (!$canMove) return null;
			// Keep the renamed node until the original finishes, so its alias gets the final booked count.
			if ($e instanceof AutoImportConflict && ($source['state'] ?? null) === 'waiting'
				&& $source['source_name'] !== $name && (int)$source['detail'] === $id) return null;
			if ($row !== null) $this->ledger->reject($row);
			if (($source['state'] ?? null) === 'reporting') $this->finishReport($rule, $source, $l);
			$archive = 'Fehler';
			$rejection = $this->rejectionMessage($l, $name, $e);
		} finally {
			$file->unlock(ILockingProvider::LOCK_SHARED);
		}

		unset($input);

		// A committed delivery stays in the ledger even if Files is temporarily unable to move it.
		// Always resolve the node again: the listing's cached file info may refer to an earlier upload.
		try {
			$target = $this->archiveFolder($folder, $archive);
			if (!$folder->nodeExists($name)) return true;
			$current = $folder->get($name);
			if (!$current instanceof File || $current->getId() !== $id || $current->getEtag() !== $etag) return false;
			$archiveName = $target->getNonExistingName($name);
			$beforeMove = function (?string $archiveHash, IStorage $storage, string $path) use (&$rule, &$row, $archiveName, $rejection, $name, $id, $rawHash, $l): void {
				if ($rejection !== null) {
					$this->report($rule, $rejection, true, $name, $id);
					return;
				}
				// An oversized replacement has no complete fingerprint. Recheck it in the inbox.
				if ($archiveHash === null) throw new NotFoundException('The booked file was replaced');
				// The archive may be moved away before recovery. Reserve changed payments while Files still locks the source.
				if ($archiveHash !== $rawHash) {
					try {
						$actual = $this->readStreamAndClose($storage->fopen($path, 'r'), $archiveHash);
						if ($actual === null) throw new NotFoundException('Archive source changed during verification');
						$error = $this->archiveConflict($rule, $row, $name, $actual);
					} catch (\DomainException $e) {
						$error = $e;
					}
					if ($error !== null) {
						$this->report($rule, $this->rejectionMessage($l, $name, $error), true, $name, $id);
						$row['state'] = 'archiving';
					}
				}
				if ($row['state'] === 'done') $row['state'] = 'archiving';
				$row['archive_hash'] = $archiveHash;
				$this->ledger->update((int)$row['id'], ['state' => $row['state'], 'archive_name' => $archiveName, 'archive_hash' => $archiveHash, 'detail' => (string)$id]);
			};
			$moved = AutoImportArchiveMove::move($current, $target, $archiveName, $rejection !== null ? $rawHash : null, $beforeMove);
		} catch (AlreadyExistsException) {
			// Sync took the chosen name. Select another name on the next scan.
			return false;
		} catch (NotPermittedException|LockedException) {
			$blockedFile ??= ['name' => $name, 'id' => $id, 'message' => $l->t('Cannot move %1$s into %2$s. Check the archive folder.', [$name, $archive])];
			return null;
		} catch (NotFoundException) {
			// The file or archive changed during the move. Recheck the journal on the next scan.
			return null;
		}
		if ($archive === 'Verarbeitet') {
			$this->verifyBookedArchive($rule, $folder, $moved, $row, $l);
			return true;
		}
		if ($rejection !== null && $moved->getId() !== $id) {
			$this->rules->record($rule['id'], AutoImportResult::pack($rejection, $name, $moved->getId()), true, time());
		}
		return true;
	}

	/** The move and the journal update cannot share a transaction. Use the same verification after a restart. */
	private function verifyBookedArchive(array &$rule, Folder $folder, File $file, array $row, IL10N $l): void {
		$name = $row['source_name'] ?: $file->getName();
		$file->lock(ILockingProvider::LOCK_SHARED);
		try {
			try {
				$actual = $this->readStreamAndClose($file->fopen('r'), $row['archive_hash'] ?? null, $rawHash);
				if ($actual === null) {
					// A different file took the intended path. Complete the booking without linking that file.
					$this->finishReport($rule, $row, $l);
					return;
				}
				$error = $this->archiveConflict($rule, $row, $name, $actual);
				if ($error === null) {
					$this->finishReport($rule, $row, $l, $file->getId());
					return;
				}
			} catch (\DomainException $e) {
				$error = $e;
			}
			$message = $this->rejectionMessage($l, $name, $error);
			$this->report($rule, $message, true, $name, $file->getId());
			// Recovery must retain the error if the worker dies during the move to Fehler.
			$this->ledger->update((int)$row['id'], ['state' => 'archiving']);
		} finally {
			$file->unlock(ILockingProvider::LOCK_SHARED);
		}
		$id = $file->getId();
		try {
			$errors = $this->archiveFolder($folder, 'Fehler');
			$moved = AutoImportArchiveMove::move($file, $errors, $errors->getNonExistingName($file->getName()), $rawHash);
		} catch (AlreadyExistsException|NotPermittedException|LockedException|NotFoundException) {
			// The conflict is durable. Retry the move on a later check and let unrelated files proceed.
			return;
		}
		if ($moved->getId() !== $id) $this->rules->record($rule['id'], AutoImportResult::pack($message, $name, $moved->getId()), true, time());
		$this->ledger->update((int)$row['id'], ['state' => 'done', 'detail' => '', 'archive_name' => null, 'archive_hash' => null]);
	}

	private function archiveConflict(array $rule, array $row, string $name, AutoImportFile $actual): ?AutoImportConflict {
		if ($actual->contentHash === $row['content_hash'] || $actual->digest === $row['digest']) return null;
		return $this->ledger->bookedConflict($rule, $row['source_name'] ?: $name, $actual, (int)$row['imported_count']);
	}

	private function rejectionMessage(IL10N $l, string $name, \DomainException $e): string {
		if ($e instanceof AutoImportConflict) {
			// Older conflict records only contain the booked count.
			$reason = $e->originalName === null
				? $l->t('The original filename was already used for an import with different payment data. Payments already booked from the original file: %s.', [$e->booked])
				: $l->t('The filename “%1$s” was already used for an import with different payment data. Payments already booked from the original file: %2$s.', [$e->originalName, $e->booked]);
		} else {
			$reason = $l->t($e->getMessage());
		}
		return $l->t('%1$s — %2$s', [$name, $reason]);
	}

	private function archiveFolder(Folder $folder, string $name): Folder {
		$archive = $folder->nodeExists($name) ? $folder->get($name) : $folder->newFolder($name);
		if (!$archive instanceof Folder || !$archive->isCreatable()) {
			throw new NotPermittedException('Archive folder unavailable');
		}
		return $archive;
	}

	private function readStreamAndClose($stream, ?string $expectedHash = null, ?string &$rawHash = null): ?AutoImportFile {
		if (!is_resource($stream)) throw new NotPermittedException('Unable to open import stream');
		try {
			return AutoImportFile::fromStream($stream, $expectedHash, $rawHash);
		} finally {
			fclose($stream);
		}
	}

	/** Preview errors reject the file. Once writing starts, retain its validated snapshot and exact cursor. */
	private function advanceOrWait(array &$rule, Folder $folder, array &$row, AutoImportFile $input, EntryController $controller, int &$budget, float $deadline, IL10N $l): bool {
		try {
			return $this->advance($rule, $folder, $row, $input, $controller, $budget, $deadline);
		} catch (\DomainException $e) {
			if ($row['state'] === 'checking') throw $e;
			$message = $l->t('%1$s — %2$s of %3$s booked. %4$s', [
				$row['source_name'], (int)$row['next_row'], $input->count, $e->getMessage(),
			]);
			$fileId = null;
			try {
				$source = ctype_digit((string)$row['detail']) ? $folder->getFirstNodeById((int)$row['detail']) : null;
				if ($source instanceof File && $source->getParent()->getPath() === $folder->getPath()
					&& $source->getName() === $row['source_name'] && $source->isReadable()) {
					$fileId = $source->getId();
				}
			} catch (\Exception) {
				// An unavailable source must not prevent saving the waiting state and its booking progress.
			}
			$stored = AutoImportResult::pack($message, $row['source_name'], $fileId);
			$this->ledger->update((int)$row['id'], [
				'state' => 'waiting', 'wait_message' => $stored, 'last_retry' => (int)(microtime(true) * 1000),
			]);
			$row['state'] = 'waiting';
			if (AutoImportResult::unpack($row['wait_message'] ?? null)['message'] !== $message) {
				$this->report($rule, $message, true, $row['source_name'], $fileId);
			}
			return true;
		}
	}

	private function advance(array $rule, Folder $folder, array &$row, AutoImportFile $input, EntryController $controller, int &$budget, float $deadline): bool {
		$project = $rule['project_id'];
		$currency = $row['currency'] ?? null;
		if ($row['state'] === 'checking') {
			foreach ($input->packets() as $offset => $payments) {
				if ($offset < (int)$row['next_row']) continue;
				if ($budget < 1 || microtime(true) >= $deadline) return false;
				$budget--;
				$preview = $controller->bulkImport($project, $payments, true, $row['digest'], $offset, deliveryCurrency: $currency);
				$data = $this->checked($preview);
				$currency = $data['summary']['currency'];
				$row['next_row'] = $offset + count($payments);
				$row['commit_limit'] = (int)$data['commitLimit'];
				if ($row['next_row'] === $input->count) {
					$row['state'] = 'writing';
					$row['next_row'] = 0;
				}
				$this->ledger->update((int)$row['id'], ['currency' => $currency, 'next_row' => $row['next_row'], 'state' => $row['state'], 'commit_limit' => $row['commit_limit']]
					+ ($row['state'] === 'writing' ? ['snapshot' => $input->snapshot()] : []));
			}
		}
		$this->archiveFolder($folder, 'Verarbeitet');
		foreach ($input->packets() as $offset => $payments) {
			$end = $offset + count($payments);
			while ((int)$row['next_row'] < $end) {
				if ($budget < 1 || microtime(true) >= $deadline) return false;
				$budget--;
				$start = (int)$row['next_row'];
				$packet = array_slice($payments, $start - $offset, (int)$row['commit_limit']);
				// The import's own transaction nests in this one: entries, allocations and cursor advance together.
				$this->db->beginTransaction();
				try {
					$response = $controller->bulkImport($project, $packet, false, $row['digest'], $start, deliveryCurrency: $currency, previousRows: $start);
					if ($response->getStatus() !== 409) {
						$data = $this->checked($response);
						$currency = $data['currency'];
						$row['next_row'] = $start + count($packet);
						$row['imported_count'] = (int)$row['imported_count'] + (int)$data['importedCount'];
						$row['state'] = $row['next_row'] === $input->count ? 'reporting' : 'writing';
						$this->ledger->update((int)$row['id'], ['currency' => $currency, 'state' => $row['state'], 'next_row' => $row['next_row'],
							'imported_count' => $row['imported_count'], 'wait_message' => null] + ($row['state'] === 'reporting' ? ['snapshot' => null] : []));
					}
				} catch (\Throwable $e) {
					$this->db->rollBack();
					throw $e;
				}
				if ($response->getStatus() === 409) {
					$this->db->rollBack();
					// Membership can change the permitted write size after preview.
					$preview = $this->checked($controller->bulkImport($project, $packet, true, $row['digest'], $start, deliveryCurrency: $currency, previousRows: $start));
					$this->ledger->update((int)$row['id'], ['commit_limit' => (int)$preview['commitLimit']]);
					return false;
				}
				$this->db->commit();
			}
		}
		return true;
	}

	private function checked(DataResponse $response): array {
		$data = $response->getData();
		if ($response->getStatus() >= 500) throw new \RuntimeException('Import temporarily unavailable');
		if ($response->getStatus() !== 200) throw new \DomainException((string)($data['error'] ?? 'Invalid import packet.'));
		return $data;
	}

	private function finishReport(array &$rule, array $row, IL10N $l, ?int $fileId = null): void {
		$count = (int)$row['imported_count'];
		$message = $row['source_name'] ? $l->t('%1$s — imported payments: %2$s', [$row['source_name'], $count])
			: $l->t('Imported payments: %s.', [$count]);
		if ($row['state'] === 'reporting') $this->report($rule, $message, false, $row['source_name'], $fileId);
		$this->ledger->update((int)$row['id'], ['state' => 'done', 'detail' => '', 'archive_name' => null, 'archive_hash' => null]);
	}

	private function report(array &$rule, string $message, bool $error, ?string $name = null, ?int $fileId = null): void {
		$repeat = $rule['last_message'] === $message;
		$this->rules->record($rule['id'], AutoImportResult::pack($message, $name, $fileId), $error, time());
		$rule['last_message'] = $message;
		if (!$error || !$repeat) $this->notify($rule, $message, $error);
	}

	private function notify(array $rule, string $message, bool $error): void {
		if ($rule['notify_mode'] === 'none' || (!$error && $rule['notify_mode'] !== 'all')) return;
		$uid = $rule['user_id'];
		$this->email->send($uid, 'import', ['error' => $error, 'result' => $message]);
		$this->talk->notifyAutomaticImport($uid, $error, $message);
		if ($this->config->getUserValue($uid, 'cobudget', 'notify_automatic_imports', 'yes') !== 'yes') return;
		try {
			$notification = $this->notifications->createNotification()
				->setApp('cobudget')->setUser($rule['user_id'])->setDateTime(new \DateTime())
				->setObject('automatic_import', (string)$rule['id'])
				->setSubject($error ? 'automatic_import_error' : 'automatic_import_success', ['result' => $message]);
			$this->notifications->notify($notification);
		} catch (\Throwable $e) {
			$this->logger->warning('CoBudget automatic import notification failed', ['app' => 'cobudget', 'rule' => $rule['id'], 'exceptionType' => get_class($e)]);
		}
	}
}
