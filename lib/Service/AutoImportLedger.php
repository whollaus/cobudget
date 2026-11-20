<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** One indexed filename reservation per account and destination, independent of a rule's lifetime. */
final class AutoImportLedger {
	private const TABLE = 'cobudget_import_files';

	public function __construct(private IDBConnection $db) {
	}

	public static function scopeKey(array $rule): string {
		return hash('sha256', json_encode([$rule['user_id'], (int)$rule['workspace_id'], isset($rule['project_id']) ? (int)$rule['project_id'] : null], JSON_THROW_ON_ERROR));
	}

	/** Rule execution owns the destination lock while inspecting or reserving a delivery. */
	public function open(array $rule, string $name, AutoImportFile $input, int $nodeId, string $etag): array {
		$scope = self::scopeKey($rule);
		// This independent reservation takes precedence even over a draft with the same hash or name.
		$conflictKey = hash('sha256', "\0conflict:" . $input->contentHash);
		$conflict = $this->find($scope, 'name_key', $conflictKey);
		if ($conflict) throw new AutoImportConflict((int)$conflict['detail'], $conflict['source_name']);
		$nameKey = hash('sha256', $name);
		$row = $this->find($scope, 'name_key', $nameKey);
		if ($row && ($row['content_hash'] === $input->contentHash || $row['digest'] === $input->digest)) {
			if ($row['state'] === 'waiting') return $row;
			// Keep the original import key when resuming a delivery started on an older version.
			$fields = ['content_hash' => $input->contentHash, 'source_name' => $name, 'rule_id' => $rule['id']];
			if ($row['state'] === 'writing' && !$row['detail']) $fields['detail'] = (string)$nodeId;
			if (!$row['content_hash'] || !$row['source_name'] || isset($fields['detail']) || (int)$row['rule_id'] !== $rule['id']) {
				$this->update((int)$row['id'], $fields);
			}
			return $fields + $row;
		}

		if ($row && $row['state'] !== 'checking') {
			throw $this->bookedConflict($rule, $name, $input, (int)$row['imported_count']);
		}
		$known = $this->find($scope, 'content_hash', $input->contentHash)
			?? $this->find($scope, 'digest', $input->digest);
		if ($known && $known['state'] === 'conflict') {
			throw new AutoImportConflict((int)$known['detail'], $known['source_name']);
		}
		// A second filename can arrive while the first one is still in preview. Share its
		// original import key so packet checkpoints also cover reordered copies in flight.
		if ($known && in_array($known['state'], ['checking', 'writing', 'waiting', 'reporting'], true)) return $known;
		$values = [
			'rule_id' => $rule['id'], 'digest' => $input->digest, 'content_hash' => $input->contentHash,
			'currency' => null, 'source_name' => $name, 'source_etag' => hash('sha256', $etag), 'state' => in_array($known['state'] ?? '', ['done', 'archiving'], true) ? 'done' : 'checking',
			'next_row' => 0, 'commit_limit' => 1, 'imported_count' => (int)($known['imported_count'] ?? 0),
			'detail' => ($known['state'] ?? '') === 'done' ? '' : (string)$nodeId,
		];
		return $this->saveName($rule, $nameKey, $row, $values);
	}

	/** Remember a conflicting version even if a sync client uploads it under a different name. */
	public function bookedConflict(array $rule, string $name, AutoImportFile $input, int $count): AutoImportConflict {
		$scope = self::scopeKey($rule);
		$key = hash('sha256', "\0conflict:" . $input->contentHash);
		$conflict = $this->find($scope, 'name_key', $key);
		if ($conflict) return new AutoImportConflict((int)$conflict['detail'], $conflict['source_name']);
		$known = $this->find($scope, 'content_hash', $input->contentHash) ?? $this->find($scope, 'digest', $input->digest);
		if (($known['state'] ?? '') === 'conflict') return new AutoImportConflict((int)$known['detail'], $known['source_name']);
		if (!$known || $known['state'] === 'checking') {
			$this->insert($rule, $key, ['digest' => $input->digest, 'content_hash' => $input->contentHash,
				'state' => 'conflict', 'detail' => (string)$count, 'source_name' => $name]);
		}
		return new AutoImportConflict($count, $name);
	}

	/** Bind a second inbox name to the finished delivery before Files moves it out of reach. */
	public function reserveBookedName(array $rule, string $name, array $delivery): void {
		$nameKey = hash('sha256', $name);
		$row = $this->find(self::scopeKey($rule), 'name_key', $nameKey);
		// A rename cannot displace another delivery that already owns this filename.
		if ($row && $row['state'] !== 'checking') return;
		$this->saveName($rule, $nameKey, $row, [
			'rule_id' => $rule['id'], 'source_name' => $name, 'digest' => $delivery['digest'],
			'content_hash' => $delivery['content_hash'], 'state' => 'done', 'detail' => '',
			'next_row' => 0, 'commit_limit' => 1, 'imported_count' => (int)$delivery['imported_count'],
		]);
	}

	private function saveName(array $rule, string $nameKey, ?array $row, array $values): array {
		if ($row) {
			$this->update((int)$row['id'], $values);
			return $values + ['id' => (int)$row['id']];
		}
		return $this->insert($rule, $nameKey, $values);
	}

	private function find(string $scope, string $column, string $value): ?array {
		$q = $this->db->getQueryBuilder();
		$result = $q->select('*')->from(self::TABLE)
			->where($q->expr()->eq('scope_key', $q->createNamedParameter($scope)))
			->andWhere($q->expr()->eq($column, $q->createNamedParameter($value)))
			->orderBy('id')->setMaxResults(1)->executeQuery();
		try {
			return $result->fetch() ?: null;
		} finally {
			$result->closeCursor();
		}
	}

	/** Match waiting inbox nodes without reading their payloads or database snapshots during enumeration. */
	public function waitingSources(int $ruleId): array {
		$q = $this->db->getQueryBuilder();
		$result = $q->select('detail', 'source_etag', 'source_name', 'content_hash', 'state')->from(self::TABLE)
			->where($q->expr()->eq('rule_id', $q->createNamedParameter($ruleId, \PDO::PARAM_INT)))
			->andWhere($q->expr()->eq('state', $q->createNamedParameter('waiting')))->executeQuery();
		try {
			return array_column($result->fetchAll(), null, 'detail');
		} finally {
			$result->closeCursor();
		}
	}

	public function pendingSourcesAfter(int $ruleId, int $after, int $limit): array {
		$q = $this->db->getQueryBuilder();
		$result = $q->select('id', 'digest', 'detail', 'content_hash', 'imported_count', 'state', 'source_name', 'source_etag', 'archive_name', 'archive_hash')
			->from(self::TABLE)->where($q->expr()->eq('rule_id', $q->createNamedParameter($ruleId, \PDO::PARAM_INT)))
			->andWhere($q->expr()->in('state', $q->createNamedParameter(['waiting', 'reporting', 'archiving'], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($q->expr()->gt('id', $q->createNamedParameter($after, \PDO::PARAM_INT)))
			->orderBy('id')->setMaxResults($limit)->executeQuery();
		try {
			return $result->fetchAll();
		} finally {
			$result->closeCursor();
		}
	}

	/** Fetch at most one saved input at a time. Completed history is outside this indexed scan. */
	public function pending(int $ruleId, string $state): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('*')->from(self::TABLE)
			->where($q->expr()->eq('rule_id', $q->createNamedParameter($ruleId, \PDO::PARAM_INT)))
			->andWhere($q->expr()->eq('state', $q->createNamedParameter($state)));
		if ($state === 'writing') $q->andWhere($q->expr()->isNotNull('snapshot'));
		if ($state === 'waiting') $q->orderBy('last_retry');
		$result = $q->addOrderBy('id')->setMaxResults(1)->executeQuery();
		try {
			return $result->fetch() ?: null;
		} finally {
			$result->closeCursor();
		}
	}

	private function insert(array $rule, string $nameKey, array $values): array {
		$q = $this->db->getQueryBuilder();
		$q->insert(self::TABLE);
		foreach ($values + [
			'user_id' => $rule['user_id'], 'workspace_id' => $rule['workspace_id'], 'project_id' => $rule['project_id'],
			'rule_id' => $rule['id'], 'scope_key' => self::scopeKey($rule), 'name_key' => $nameKey,
		] as $column => $value) {
			$q->setValue($column, $q->createNamedParameter($value, $value === null ? \PDO::PARAM_NULL : (is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR)));
		}
		$q->executeStatement();
		return $values + ['id' => (int)$q->getLastInsertId()];
	}

	public function update(int $id, array $values): void {
		$q = $this->db->getQueryBuilder();
		$q->update(self::TABLE)->where($q->expr()->eq('id', $q->createNamedParameter($id, \PDO::PARAM_INT)));
		foreach ($values as $column => $value) {
			$q->set($column, $q->createNamedParameter($value, $value === null ? \PDO::PARAM_NULL : (is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR)));
		}
		$q->executeStatement();
	}

	/** A corrected, rejected file with the same name must start validation at row zero. */
	public function reject(array $row): void {
		if ($row['state'] === 'checking') {
			$this->update((int)$row['id'], ['state' => 'checking', 'next_row' => 0]);
		}
	}
}
