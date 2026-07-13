<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;

/**
 * Stores one physical receipt copy for every locked personal payment.
 *
 * The database transaction is owned by the caller. File operations cannot be
 * rolled back by the database, so every failed copy removes its new file
 * before the exception is propagated.
 */
final class EntryAttachmentProjectionService {
	public function __construct(
		private IDBConnection $db,
		private IRootFolder $rootFolder,
		private IConfig $config,
		private ParticipantService $participantService,
	) {
	}

	public function syncSharedEntry(int $sourceEntryId): void {
		$sourceAttachments = $this->sourceAttachments($sourceEntryId);
		$personalEntries = $this->lockedPersonalEntries($sourceEntryId);
		$wanted = [];
		$createdCopies = [];

		try {
			foreach ($sourceAttachments as $sourceAttachment) {
				foreach ($personalEntries as $personalEntry) {
					if (!$this->participantService->isActive((string)$personalEntry['user_id'])) {
						continue;
					}
					$key = (int)$personalEntry['id'] . ':' . (int)$sourceAttachment['id'];
					$wanted[$key] = true;
					if (!$this->copyExists((int)$personalEntry['id'], (int)$sourceAttachment['id'])) {
						$createdCopies[] = $this->createCopy($sourceAttachment, $personalEntry);
					}
				}
			}

			foreach ($this->projectedAttachmentsForSourceEntry($sourceEntryId) as $copy) {
				$key = (int)$copy['entry_id'] . ':' . (int)$copy['source_attachment_id'];
				if (!isset($wanted[$key])) {
					$this->deleteAttachment($copy);
				}
			}
		} catch (\Throwable $e) {
			foreach (array_reverse($createdCopies) as $createdCopy) {
				try {
					$this->deleteAttachment($createdCopy);
				} catch (\Throwable) {
				}
			}
			throw $e;
		}
	}

	public function deleteSourceAttachmentCopies(int $sourceAttachmentId): void {
		if ($sourceAttachmentId <= 0) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_entry_attachments')
			->where($qb->expr()->eq('source_attachment_id', $qb->createNamedParameter($sourceAttachmentId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as $row) {
			$this->deleteAttachment($row);
		}
	}

	/** @param int[] $entryIds */
	public function deleteAttachmentsForEntries(array $entryIds): void {
		$entryIds = $this->positiveIds($entryIds);
		if ($entryIds === []) {
			return;
		}

		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from('cobudget_entry_attachments')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$this->deleteAttachment($row);
			}
		}
	}

	/** @return array<int, array<string, mixed>> */
	private function sourceAttachments(int $sourceEntryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('cobudget_entry_attachments')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->isNull('source_attachment_id'));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/** @return array<int, array<string, mixed>> */
	private function lockedPersonalEntries(int $sourceEntryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'workspace_id', 'date')
			->from('cobudget_entries')
			->where($qb->expr()->eq('source_entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('entry_kind', $qb->createNamedParameter('personal')))
			->andWhere($qb->expr()->eq('is_locked', $qb->createNamedParameter(true, \PDO::PARAM_BOOL)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	/** @return array<int, array<string, mixed>> */
	private function projectedAttachmentsForSourceEntry(int $sourceEntryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('a.*')
			->from('cobudget_entry_attachments', 'a')
			->innerJoin('a', 'cobudget_entry_attachments', 'source_a', $qb->expr()->eq('a.source_attachment_id', 'source_a.id'))
			->where($qb->expr()->eq('source_a.entry_id', $qb->createNamedParameter($sourceEntryId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	private function copyExists(int $entryId, int $sourceAttachmentId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_entry_attachments')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('source_attachment_id', $qb->createNamedParameter($sourceAttachmentId, \PDO::PARAM_INT)))
			->setMaxResults(1);

		return $qb->executeQuery()->fetchOne() !== false;
	}

	/** @return array<string, mixed> */
	private function createCopy(array $sourceAttachment, array $personalEntry): array {
		$sourceFile = $this->attachmentFile($sourceAttachment);
		if ($sourceFile === null) {
			throw new \RuntimeException('Shared receipt file could not be loaded for its personal copies.');
		}

		$content = $sourceFile->getContent();
		$targetUserId = (string)$personalEntry['user_id'];
		$folderPath = $this->attachmentFolderPath($targetUserId, (int)$personalEntry['date']);
		$targetFolder = $this->ensureFolderPath($this->rootFolder->getUserFolder($targetUserId), $folderPath);
		$fileName = $this->copyFileName((string)$sourceAttachment['file_name'], (int)$personalEntry['id']);
		$fileName = $this->resolveUniqueNameInFolder($targetFolder, $fileName);
		$createdFile = $targetFolder->newFile($fileName);

		try {
			$createdFile->putContent($content);
			$relativePath = trim($folderPath . '/' . $fileName, '/');
			$createdAt = time();
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_entry_attachments')
				->values([
					'entry_id' => $qb->createNamedParameter((int)$personalEntry['id'], \PDO::PARAM_INT),
					'source_attachment_id' => $qb->createNamedParameter((int)$sourceAttachment['id'], \PDO::PARAM_INT),
					'workspace_id' => $qb->createNamedParameter((int)$personalEntry['workspace_id'], \PDO::PARAM_INT),
					'owner_user_id' => $qb->createNamedParameter($targetUserId),
					'file_id' => $qb->createNamedParameter($createdFile->getId(), \PDO::PARAM_INT),
					'file_path' => $qb->createNamedParameter($relativePath),
					'file_name' => $qb->createNamedParameter((string)$sourceAttachment['file_name']),
					'mime_type' => $qb->createNamedParameter($sourceAttachment['mime_type'] ?? null, empty($sourceAttachment['mime_type']) ? \PDO::PARAM_NULL : \PDO::PARAM_STR),
					'file_size' => $qb->createNamedParameter((int)$createdFile->getSize(), \PDO::PARAM_INT),
					'created_at' => $qb->createNamedParameter($createdAt, \PDO::PARAM_INT),
				]);
			$qb->executeStatement();

			return [
				'id' => (int)$this->db->lastInsertId('*PREFIX*cobudget_entry_attachments'),
				'entry_id' => (int)$personalEntry['id'],
				'source_attachment_id' => (int)$sourceAttachment['id'],
				'workspace_id' => (int)$personalEntry['workspace_id'],
				'owner_user_id' => $targetUserId,
				'file_id' => $createdFile->getId(),
				'file_path' => $relativePath,
				'file_name' => (string)$sourceAttachment['file_name'],
				'mime_type' => $sourceAttachment['mime_type'] ?? null,
				'file_size' => (int)$createdFile->getSize(),
				'created_at' => $createdAt,
			];
		} catch (\Throwable $e) {
			try {
				$createdFile->delete();
			} catch (\Throwable) {
			}
			throw $e;
		}
	}

	private function deleteAttachment(array $attachment): void {
		$file = $this->attachmentFile($attachment);
		$qb = $this->db->getQueryBuilder();
		$qb->delete('cobudget_entry_attachments')
			->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$attachment['id'], \PDO::PARAM_INT)));
		$qb->executeStatement();
		if ($file !== null) {
			$file->delete();
		}
	}

	private function attachmentFile(array $attachment): ?File {
		try {
			$userFolder = $this->rootFolder->getUserFolder((string)$attachment['owner_user_id']);
			$path = trim((string)$attachment['file_path'], '/');
			if ($path === '' || !$userFolder->nodeExists($path)) {
				return null;
			}
			$node = $userFolder->get($path);

			return $node instanceof File ? $node : null;
		} catch (\Throwable) {
			return null;
		}
	}

	private function attachmentFolderPath(string $userId, int $entryDate): string {
		$base = $this->normalizedFolder($this->config->getUserValue($userId, 'cobudget', 'receipt_storage_folder', 'CoBudget/Belege'));
		$grouping = $this->config->getUserValue($userId, 'cobudget', 'receipt_folder_grouping', 'year');
		$timestamp = $entryDate > 0 ? $entryDate : time();
		if ($grouping === 'year_month') {
			return trim($base . '/' . date('Y', $timestamp) . '/' . date('m', $timestamp), '/');
		}
		if ($grouping === 'year') {
			return trim($base . '/' . date('Y', $timestamp), '/');
		}

		return $base;
	}

	private function normalizedFolder(string $folder): string {
		$folder = trim($folder, " /\t\n\r\0\x0B");
		if ($folder === '' || str_contains($folder, '\\') || preg_match('~(^|/)\.\.(/|$)~', $folder) === 1) {
			return 'CoBudget/Belege';
		}

		return $folder;
	}

	private function ensureFolderPath(Folder $root, string $relativePath): Folder {
		$current = $root;
		foreach (array_values(array_filter(explode('/', trim($relativePath, '/')))) as $segment) {
			if (!$current->nodeExists($segment)) {
				$current = $current->newFolder($segment);
				continue;
			}
			$node = $current->get($segment);
			if (!$node instanceof Folder) {
				throw new \RuntimeException('Receipt folder could not be created.');
			}
			$current = $node;
		}

		return $current;
	}

	private function copyFileName(string $fileName, int $entryId): string {
		$fileName = trim($fileName) !== '' ? trim($fileName) : 'beleg';
		return 'eintrag-' . $entryId . '-' . date('Ymd-His') . '-' . $fileName;
	}

	private function resolveUniqueNameInFolder(Folder $folder, string $fileName): string {
		if (!$folder->nodeExists($fileName)) {
			return $fileName;
		}
		$dot = strrpos($fileName, '.');
		$base = $dot === false ? $fileName : substr($fileName, 0, $dot);
		$extension = $dot === false ? '' : substr($fileName, $dot);
		for ($i = 2; $i < 1000; $i++) {
			$candidate = $base . '-' . $i . $extension;
			if (!$folder->nodeExists($candidate)) {
				return $candidate;
			}
		}

		return $base . '-' . time() . $extension;
	}

	/** @return int[] */
	private function positiveIds(array $ids): array {
		return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
	}
}
