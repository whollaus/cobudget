<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class HashtagService {
	private const MAX_HASHTAG_LENGTH = 64;
	private const CHUNK_SIZE = 500;

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function extractFromText(?string $text): array {
		$text = trim((string)$text);
		if ($text === '') {
			return [];
		}

		$matchCount = preg_match_all('/(?<![\p{L}\p{N}_])#([\p{L}\p{N}_][\p{L}\p{N}_-]{0,63})/u', $text, $matches);
		if ($matchCount === false || $matchCount === 0) {
			return [];
		}

		$hashtags = [];
		foreach ($matches[1] ?? [] as $name) {
			$rawName = trim((string)$name);
			$displayName = function_exists('mb_substr')
				? mb_substr($rawName, 0, self::MAX_HASHTAG_LENGTH)
				: substr($rawName, 0, self::MAX_HASHTAG_LENGTH);
			if ($displayName === '') {
				continue;
			}
			$normalizedName = $this->normalizeName($displayName);
			$hashtags[$normalizedName] = [
				'normalizedName' => $normalizedName,
				'displayName' => $displayName,
			];
		}

		return array_values($hashtags);
	}

	public function syncEntryHashtags(int $entryId, int $workspaceId, ?string $description): void {
		if ($entryId <= 0 || $workspaceId <= 0) {
			return;
		}

		$oldIds = $this->hashtagIdsForEntry($entryId);
		$this->deleteEntryHashtagLinks($entryId);

		$newIds = [];
		foreach ($this->extractFromText($description) as $hashtag) {
			$hashtagId = $this->findOrCreateHashtag($workspaceId, (string)$hashtag['normalizedName'], (string)$hashtag['displayName']);
			$this->insertEntryHashtagLink($entryId, $hashtagId, $workspaceId);
			$newIds[] = $hashtagId;
		}

		$this->cleanupUnusedHashtags(array_diff($oldIds, $newIds));
	}

	public function deleteEntryHashtags(int $entryId): void {
		if ($entryId <= 0) {
			return;
		}

		$oldIds = $this->hashtagIdsForEntry($entryId);
		$this->deleteEntryHashtagLinks($entryId);
		$this->cleanupUnusedHashtags($oldIds);
	}

	public function deleteHashtagsForEntries(array $entryIds): void {
		$entryIds = $this->positiveUniqueIds($entryIds);
		if ($entryIds === []) {
			return;
		}

		$hashtagIds = [];
		foreach (array_chunk($entryIds, self::CHUNK_SIZE) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('hashtag_id')
				->from('cobudget_entry_hashtags')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$hashtagIds[] = (int)$row['hashtag_id'];
			}

			$delete = $this->db->getQueryBuilder();
			$delete->delete('cobudget_entry_hashtags')
				->where($delete->expr()->in('entry_id', $delete->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$delete->executeStatement();
		}

		$this->cleanupUnusedHashtags($hashtagIds);
	}

	public function deleteWorkspaceHashtags(int $workspaceId): void {
		if ($workspaceId <= 0) {
			return;
		}

		$deleteLinks = $this->db->getQueryBuilder();
		$deleteLinks->delete('cobudget_entry_hashtags')
			->where($deleteLinks->expr()->eq('workspace_id', $deleteLinks->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		$deleteLinks->executeStatement();

		$deleteTags = $this->db->getQueryBuilder();
		$deleteTags->delete('cobudget_hashtags')
			->where($deleteTags->expr()->eq('workspace_id', $deleteTags->createNamedParameter($workspaceId, \PDO::PARAM_INT)));
		$deleteTags->executeStatement();
	}

	public function attachHashtagsToEntries(array $entries): array {
		if ($entries === []) {
			return $entries;
		}

		$entriesById = [];
		foreach ($entries as $index => $entry) {
			$entryId = (int)($entry['id'] ?? 0);
			$entries[$index]['hashtags'] = [];
			if ($entryId > 0) {
				$entriesById[$entryId] = $index;
			}
		}

		if ($entriesById === []) {
			return $entries;
		}

		foreach (array_chunk(array_keys($entriesById), self::CHUNK_SIZE) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('eh.entry_id', 'h.id', 'h.normalized_name', 'h.display_name')
				->from('cobudget_entry_hashtags', 'eh')
				->innerJoin('eh', 'cobudget_hashtags', 'h', $qb->expr()->eq('eh.hashtag_id', 'h.id'))
				->where($qb->expr()->in('eh.entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->orderBy('h.display_name', 'ASC');
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$entryId = (int)$row['entry_id'];
				if (!isset($entriesById[$entryId])) {
					continue;
				}
				$entries[$entriesById[$entryId]]['hashtags'][] = $this->normalizeHashtagRow($row);
			}
		}

		return $entries;
	}

	public function fetchVisibleHashtagsForUser(int $workspaceId, string $userId): array {
		if ($workspaceId <= 0 || $userId === '') {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('h.id', 'id')
			->addSelect('h.normalized_name', 'h.display_name')
			->from('cobudget_hashtags', 'h')
			->innerJoin('h', 'cobudget_entry_hashtags', 'eh', $qb->expr()->eq('h.id', 'eh.hashtag_id'))
			->innerJoin('eh', 'cobudget_entries', 'e', $qb->expr()->eq('eh.entry_id', 'e.id'))
			->leftJoin('e', 'cobudget_members', 'm', $qb->expr()->eq('e.project_id', 'm.project_id'))
			->where($qb->expr()->eq('h.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('eh.workspace_id', 'h.workspace_id'))
			->andWhere($qb->expr()->eq('e.workspace_id', 'h.workspace_id'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->andX(
					$qb->expr()->isNull('e.project_id'),
					$qb->expr()->eq('e.user_id', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('e.workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT))
				),
				$qb->expr()->andX(
					$qb->expr()->isNotNull('e.project_id'),
					$qb->expr()->eq('m.user_id', $qb->createNamedParameter($userId))
				)
			))
			->groupBy('h.id')
			->addGroupBy('h.normalized_name')
			->addGroupBy('h.display_name')
			->orderBy('h.display_name', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(fn (array $row): array => $this->normalizeHashtagRow($row), $rows);
	}

	private function findOrCreateHashtag(int $workspaceId, string $normalizedName, string $displayName): int {
		$existingId = $this->findHashtagId($workspaceId, $normalizedName);
		if ($existingId !== null) {
			$this->updateHashtagDisplayName($existingId, $displayName);
			return $existingId;
		}

		$now = time();
		$qb = $this->db->getQueryBuilder();
		$qb->insert('cobudget_hashtags')
			->values([
				'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
				'normalized_name' => $qb->createNamedParameter($normalizedName),
				'display_name' => $qb->createNamedParameter($displayName),
				'created_at' => $qb->createNamedParameter($now, \PDO::PARAM_INT),
				'updated_at' => $qb->createNamedParameter($now, \PDO::PARAM_INT),
			]);

		try {
			$qb->executeStatement();
			return (int)$this->db->lastInsertId('*PREFIX*cobudget_hashtags');
		} catch (\Throwable $e) {
			$existingId = $this->findHashtagId($workspaceId, $normalizedName);
			if ($existingId !== null) {
				return $existingId;
			}
			throw $e;
		}
	}

	private function findHashtagId(int $workspaceId, string $normalizedName): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('cobudget_hashtags')
			->where($qb->expr()->eq('workspace_id', $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('normalized_name', $qb->createNamedParameter($normalizedName)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row ? (int)$row['id'] : null;
	}

	private function updateHashtagDisplayName(int $id, string $displayName): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_hashtags')
			->set('display_name', $qb->createNamedParameter($displayName))
			->set('updated_at', $qb->createNamedParameter(time(), \PDO::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	private function insertEntryHashtagLink(int $entryId, int $hashtagId, int $workspaceId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('cobudget_entry_hashtags')
			->values([
				'entry_id' => $qb->createNamedParameter($entryId, \PDO::PARAM_INT),
				'hashtag_id' => $qb->createNamedParameter($hashtagId, \PDO::PARAM_INT),
				'workspace_id' => $qb->createNamedParameter($workspaceId, \PDO::PARAM_INT),
				'created_at' => $qb->createNamedParameter(time(), \PDO::PARAM_INT),
			]);
		$qb->executeStatement();
	}

	private function hashtagIdsForEntry(int $entryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('hashtag_id')
			->from('cobudget_entry_hashtags')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $this->positiveUniqueIds(array_map(static fn (array $row): int => (int)$row['hashtag_id'], $rows));
	}

	private function deleteEntryHashtagLinks(int $entryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('cobudget_entry_hashtags')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	private function cleanupUnusedHashtags(array $ids): void {
		foreach ($this->positiveUniqueIds($ids) as $id) {
			$count = $this->entryHashtagUsageCount($id);
			if ($count > 0) {
				continue;
			}
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cobudget_hashtags')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));
			$qb->executeStatement();
		}
	}

	private function entryHashtagUsageCount(int $hashtagId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->count('*'), 'count')
			->from('cobudget_entry_hashtags')
			->where($qb->expr()->eq('hashtag_id', $qb->createNamedParameter($hashtagId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int)($row['count'] ?? 0);
	}

	private function normalizeName(string $name): string {
		return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
	}

	private function normalizeHashtagRow(array $row): array {
		return [
			'id' => (int)($row['id'] ?? 0),
			'name' => (string)($row['display_name'] ?? ''),
			'displayName' => (string)($row['display_name'] ?? ''),
			'normalizedName' => (string)($row['normalized_name'] ?? ''),
		];
	}

	private function positiveUniqueIds(array $ids): array {
		return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
	}
}
