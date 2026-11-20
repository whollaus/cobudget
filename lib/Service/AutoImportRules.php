<?php
declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\AppFramework\Http;
use OCP\DB\Exception as DatabaseException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;

class AutoImportRules {
	private const TABLE = 'cobudget_import_rules';

	public function __construct(
		private IDBConnection $db,
		private IRootFolder $rootFolder,
		private ILockingProvider $locks,
	) {}

	/** Missing areas remain available for rule cleanup. Archived areas keep their configuration dormant. */
	private static function outsideArchive(IDBConnection $db): IQueryBuilder {
		$q = $db->getQueryBuilder();
		return $q->select('r.*')->from(self::TABLE, 'r')
			->leftJoin('r', 'cobudget_projects', 'area', $q->expr()->eq('area.id', 'r.project_id'))
			->where($q->expr()->orX($q->expr()->isNull('area.id'),
				$q->expr()->eq('area.is_archived', $q->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))));
	}

	public function forWorkspace(string $userId, int $workspaceId): array {
		$q = self::outsideArchive($this->db)->orderBy('r.id');
		$this->restrictOwner($q, $userId, $workspaceId, 'r.');
		return $this->withWaitingMessages($this->rows($q));
	}

	public function forOwner(int $id, string $userId, int $workspaceId): array {
		$q = self::outsideArchive($this->db);
		$q->andWhere($q->expr()->eq('r.id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$this->restrictOwner($q, $userId, $workspaceId, 'r.');
		$row = $this->rows($q)[0] ?? null;
		$this->assertOwner($row, $userId, $workspaceId);
		return $this->withWaitingMessages([$row])[0];
	}

	public static function hasActiveImports(IDBConnection $db, string $userId, ?int $exceptWorkspace = null): bool {
		$q = self::outsideArchive($db);
		$q->select('r.id')
			->andWhere($q->expr()->eq('r.user_id', $q->createNamedParameter($userId)))
			->andWhere($q->expr()->eq('enabled', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		if ($exceptWorkspace !== null) $q->andWhere($q->expr()->neq('r.workspace_id', $q->createNamedParameter($exceptWorkspace, IQueryBuilder::PARAM_INT)));
		$result = $q->setMaxResults(1)->executeQuery();
		try {
			return $result->fetchOne() !== false;
		} finally {
			$result->closeCursor();
		}
	}

	public function due(int $now): array {
		$q = self::outsideArchive($this->db);
		$q->innerJoin('r', 'preferences', 'p', $q->expr()->eq('p.userid', 'r.user_id'))
			->andWhere($q->expr()->eq('p.appid', $q->createNamedParameter('cobudget')))
			->andWhere($q->expr()->eq('p.configkey', $q->createNamedParameter('enable_payment_imports')))
			->andWhere($q->expr()->eq('p.configvalue', $q->createNamedParameter('yes')))
			->andWhere($q->expr()->eq('r.enabled', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($q->expr()->lte('r.next_run', $q->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->orderBy('r.next_run')->addOrderBy('r.id')->setMaxResults(20);
		return $this->rows($q);
	}

	/** Re-read under the same mutex used by the runner, edit and removal. */
	public function withLockedRule(int $id, callable $callback): mixed {
		$key = hash('sha256', 'cobudget:auto-import-rule:' . $id);
		return $this->withLock($key, fn () => $callback($this->byId($id)));
	}

	public function withDestinationLock(array $rule, callable $callback): mixed {
		$run = fn () => $this->withLock(hash('sha256', 'cobudget:auto-import-destination:' . AutoImportLedger::scopeKey($rule)), $callback);
		if ($rule['project_id'] === null) return $run();
		return $this->withAreaLock($rule['project_id'], false, function () use ($rule, $run) {
			// Archiving may have finished since the worker selected this rule.
			return $this->byId($rule['id']) === null ? 'skipped' : $run();
		});
	}

	public function withAreaLock(int $projectId, bool $exclusive, callable $callback): mixed {
		return $this->withLock(hash('sha256', 'cobudget:auto-import-area:' . $projectId), $callback, $exclusive);
	}

	private function withLock(string $key, callable $callback, bool $exclusive = true): mixed {
		$this->locks->acquireLock($key, ILockingProvider::LOCK_SHARED);
		$type = ILockingProvider::LOCK_SHARED;
		try {
			if ($exclusive) {
				$this->locks->changeLock($key, ILockingProvider::LOCK_EXCLUSIVE);
				$type = ILockingProvider::LOCK_EXCLUSIVE;
			}
			return $callback();
		} finally {
			$this->locks->releaseLock($key, $type);
		}
	}

	public function record(int $id, string $message, bool $error, int $now): void {
		$q = $this->db->getQueryBuilder();
		$q->update(self::TABLE)
			->set('last_error', $q->createNamedParameter($error, IQueryBuilder::PARAM_BOOL))
			->set('last_message', $q->createNamedParameter($message))
			->set('last_run', $q->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** Both cursors are private to a rule and are updated under its execution lock. */
	public function checkpoint(int $id, ?string $scanFrom, int $reportAfter, ?array $candidates = null): void {
		$q = $this->db->getQueryBuilder();
		$q->update(self::TABLE)->set('scan_from', $q->createNamedParameter($scanFrom, $scanFrom === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('report_after', $q->createNamedParameter($reportAfter, IQueryBuilder::PARAM_INT))
			->set('scan_batch', $q->createNamedParameter($candidates === null ? null : json_encode($candidates, JSON_THROW_ON_ERROR), $candidates === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)))->executeStatement();
	}

	public function schedule(int $id, int $next): void {
		$q = $this->db->getQueryBuilder();
		$q->update(self::TABLE)->set('next_run', $q->createNamedParameter($next, IQueryBuilder::PARAM_INT))
			->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function folderForRule(array $rule): Folder {
		$folder = $this->resolveFolder($rule['user_id'], $rule['folder']);
		if ((int)$folder->getId() !== $rule['folder_id']) {
			throw new \DomainException('The selected import folder has changed. Select it again in the rule.', Http::STATUS_CONFLICT);
		}
		return $folder;
	}

	public function save(?int $id, string $userId, int $workspaceId, ?int $projectId, string $folder, int $interval, string $mode, bool $enabled, string $timezone, callable $validateProject, ?int $selectedFolderId = null): array {
		if (!in_array($interval, [5, 15, 60, 1440], true) || !in_array($mode, ['none', 'error', 'all'], true)
			|| !in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
			throw new \DomainException('Invalid automatic import settings.', Http::STATUS_BAD_REQUEST);
		}
		$path = trim($folder, '/');
		$write = function (?array $existing) use ($id, $userId, $workspaceId, $projectId, $folder, $path, $interval, $mode, $enabled, $timezone, $validateProject, $selectedFolderId): array {
			if ($id !== null) {
				$this->assertOwner($existing, $userId, $workspaceId);
			}
			if ($projectId !== null && ($enabled || $existing === null || $existing['project_id'] !== $projectId)) {
				$validateProject($projectId);
			}
			// An unconfigured row can store preferences before an inbox is selected.
			if (!$enabled && $folder === '') {
				$folderId = 0;
			} elseif (!$enabled && $selectedFolderId === null && $existing !== null && $existing['folder'] === $path && $existing['folder_id'] > 0) {
				// Allow pausing even when access to the selected inbox has since been lost.
				$folderId = $existing['folder_id'];
			} else {
				$folderId = (int)$this->resolveFolder($userId, $path)->getId();
			}
			$replaced = $existing !== null && $existing['folder'] === $path && $existing['folder_id'] > 0 && $existing['folder_id'] !== $folderId;
			if (($selectedFolderId !== null && $selectedFolderId !== $folderId) || ($replaced && $selectedFolderId === null)) {
				throw new \DomainException('The selected import folder has changed. Select it again in the rule.', Http::STATUS_CONFLICT);
			}
			if ($existing !== null && ($existing['project_id'] !== $projectId || (!$replaced && $existing['folder_id'] > 0 && $existing['folder_id'] !== $folderId))
				&& $this->hasWritingFile($id)) {
				throw new \DomainException('Finish the current file before changing the import folder or area.', Http::STATUS_CONFLICT);
			}

			if ($folderId > 0 && self::isFolderAssigned($this->db, $folderId, $id)) {
				throw new \DomainException('Another automatic import already uses this folder.', Http::STATUS_CONFLICT);
			}
			$q = $this->db->getQueryBuilder();
			$fields = [
				'project_id' => $q->createNamedParameter($projectId, $projectId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
				'folder' => $q->createNamedParameter($path),
				'folder_id' => $q->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'active_folder_id' => $q->createNamedParameter($folderId ?: null, $folderId > 0 ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_NULL),
				'interval_minutes' => $q->createNamedParameter($interval, IQueryBuilder::PARAM_INT),
				'notify_mode' => $q->createNamedParameter($mode),
				'enabled' => $q->createNamedParameter($enabled, IQueryBuilder::PARAM_BOOL),
				'timezone' => $q->createNamedParameter($timezone),
				'next_run' => $q->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
			];
			if ($existing && ($existing['folder_id'] !== $folderId || $existing['project_id'] !== $projectId)) {
				$fields['scan_from'] = $q->createNamedParameter(null, IQueryBuilder::PARAM_NULL);
				$fields['report_after'] = $q->createNamedParameter(0, IQueryBuilder::PARAM_INT);
				$fields['scan_batch'] = $q->createNamedParameter(null, IQueryBuilder::PARAM_NULL);
			}
			if ($id === null) {
				$fields['user_id'] = $q->createNamedParameter($userId);
				$fields['workspace_id'] = $q->createNamedParameter($workspaceId, IQueryBuilder::PARAM_INT);
				$q->insert(self::TABLE)->values($fields);
			} else {
				$q->update(self::TABLE)->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
				$this->restrictOwner($q, $userId, $workspaceId);
				foreach ($fields as $key => $value) {
					$q->set($key, $value);
				}
			}
			try {
				$q->executeStatement();
			} catch (DatabaseException $e) {
				if ($e->getReason() !== DatabaseException::REASON_UNIQUE_CONSTRAINT_VIOLATION) throw $e;
				throw new \DomainException('Another automatic import already uses this folder.', Http::STATUS_CONFLICT, $e);
			}
			$row = $this->byId($id ?? $q->getLastInsertId());
			$this->assertOwner($row, $userId, $workspaceId);
			return $this->withWaitingMessages([$row])[0];
		};
		return $id === null ? $write(null) : $this->withLockedRule($id, $write);
	}

	public function remove(int $id, string $userId, int $workspaceId): void {
		$this->withLockedRule($id, function (?array $row) use ($id, $userId, $workspaceId): void {
			$this->assertOwner($row, $userId, $workspaceId);
			if ($this->hasWritingFile($id)) {
				throw new \DomainException('Finish the current file before changing the import folder or area.', Http::STATUS_CONFLICT);
			}
			$q = $this->db->getQueryBuilder();
			$q->delete(self::TABLE)->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
			$this->restrictOwner($q, $userId, $workspaceId);
			$q->executeStatement();
		});
	}

	private function resolveFolder(string $userId, string $path): Folder {
		$parts = explode('/', $path);
		if (strlen($path) > 4000 || str_contains($path, '\\') || preg_match('/[\x00-\x1f\x7f]/', $path)
			|| array_intersect(array_map('strtolower', $parts), ['', '.', '..', 'verarbeitet', 'fehler'])) {
			throw new \DomainException('Choose an input folder outside the processed and error folders.', Http::STATUS_BAD_REQUEST);
		}
		try {
			$home = $this->rootFolder->getUserFolder($userId);
			$node = $home->get($path);
			if ($node instanceof Folder && $home->isSubNode($node) && $node->getId() > 0 && $node->isReadable() && $node->isCreatable()) {
				return $node;
			}
		} catch (NotFoundException|NotPermittedException) {
			// Missing and inaccessible paths have the same user-facing error.
		}
		throw new \DomainException('Choose an existing folder where you can read and move files.', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Upgrade or restore older rules. Existing reservations win, then the oldest paused rule.
	 * The existing unique active_folder_id column reserves every configured Files node.
	 */
	public static function reservePausedFolders(IDBConnection $db): void {
		do {
			$q = $db->getQueryBuilder();
			$result = $q->select('id', 'folder_id')->from(self::TABLE)
				->where($q->expr()->isNull('active_folder_id'))
				->andWhere($q->expr()->gt('folder_id', $q->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
				->orderBy('id')->setMaxResults(200)->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();
			foreach ($rows as $row) {
				$folderId = (int)$row['folder_id'];
				$update = $db->getQueryBuilder();
				$update->update(self::TABLE)->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
				if (self::isFolderAssigned($db, $folderId, null)) {
					$update->set('folder', $update->createNamedParameter(''))
						->set('folder_id', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))
						->set('enabled', $update->createNamedParameter(false, IQueryBuilder::PARAM_BOOL));
				} else {
					$update->set('active_folder_id', $update->createNamedParameter($folderId, IQueryBuilder::PARAM_INT));
				}
				$update->executeStatement();
			}
		} while (count($rows) === 200);
	}

	private static function isFolderAssigned(IDBConnection $db, int $folderId, ?int $except): bool {
		$q = $db->getQueryBuilder();
		$q->select('id')->from(self::TABLE)
			->where($q->expr()->eq('active_folder_id', $q->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		if ($except !== null) {
			$q->andWhere($q->expr()->neq('id', $q->createNamedParameter($except, IQueryBuilder::PARAM_INT)));
		}
		$result = $q->setMaxResults(1)->executeQuery();
		try {
			return $result->fetchOne() !== false;
		} finally {
			$result->closeCursor();
		}
	}

	private function hasWritingFile(int $id): bool {
		$q = $this->db->getQueryBuilder();
		$result = $q->select('id')->from('cobudget_import_files')
			->where($q->expr()->eq('rule_id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->in('state', $q->createNamedParameter(['writing', 'waiting', 'reporting', 'archiving'], IQueryBuilder::PARAM_STR_ARRAY)))
			->setMaxResults(1)->executeQuery();
		try {
			return $result->fetchOne() !== false;
		} finally {
			$result->closeCursor();
		}
	}

	public function assertOwner(?array $row, string $userId, int $workspaceId): void {
		if ($row === null || $row['user_id'] !== $userId || $row['workspace_id'] !== $workspaceId) {
			throw new \DomainException('Automatic import not found.', Http::STATUS_NOT_FOUND);
		}
	}

	private function restrictOwner(IQueryBuilder $q, string $userId, int $workspaceId, string $prefix = ''): void {
		$q->andWhere($q->expr()->eq($prefix . 'user_id', $q->createNamedParameter($userId)))
			->andWhere($q->expr()->eq($prefix . 'workspace_id', $q->createNamedParameter($workspaceId, IQueryBuilder::PARAM_INT)));
	}

	private function byId(int $id): ?array {
		$q = self::outsideArchive($this->db);
		$q->andWhere($q->expr()->eq('r.id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->rows($q)[0] ?? null;
	}

	/** Return outstanding deliveries alongside the rule's latest result, without fetching snapshots. */
	private function withWaitingMessages(array $rules): array {
		if (!$rules) return [];
		$q = $this->db->getQueryBuilder();
		$result = $q->select('rule_id', 'wait_message')->from('cobudget_import_files')
			->where($q->expr()->in('rule_id', $q->createNamedParameter(array_column($rules, 'id'), IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($q->expr()->eq('state', $q->createNamedParameter('waiting')))
			->orderBy('id')->executeQuery();
		$messages = [];
		try {
			while ($file = $result->fetch()) {
				$messages[(int)$file['rule_id']][] = AutoImportResult::unpack($file['wait_message']);
			}
		} finally {
			$result->closeCursor();
		}
		foreach ($rules as &$rule) {
			unset($rule['scan_batch'], $rule['scan_from'], $rule['report_after'], $rule['report_etag']);
			$rule['waiting_results'] = $messages[$rule['id']] ?? [];
			$rule['waiting_messages'] = array_column($rule['waiting_results'], 'message');
		}
		return $rules;
	}

	private function rows(IQueryBuilder $q): array {
		$result = $q->executeQuery();
		try {
			$rows = $result->fetchAll();
		} finally {
			$result->closeCursor();
		}
		foreach ($rows as &$row) {
			foreach (['id', 'workspace_id', 'folder_id', 'interval_minutes', 'last_run', 'next_run'] as $key) {
				$row[$key] = (int)$row[$key];
			}
			$row['project_id'] = $row['project_id'] === null ? null : (int)$row['project_id'];
			$row['enabled'] = in_array($row['enabled'], [true, 1, '1', 't'], true);
			$row['last_error'] = $row['last_error'] === null ? null : in_array($row['last_error'], [true, 1, '1', 't'], true);
			$last = AutoImportResult::unpack($row['last_message']);
			$row['last_message'] = $last['message'];
			$row['last_file'] = $last['file'];
			unset($row['active_folder_id']);
		}
		return $rows;
	}
}
