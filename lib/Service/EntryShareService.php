<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final class EntryShareService {

	public function __construct(private IDBConnection $db) {
	}

	public function syncEntry(int $entryId): void {
		$entry = $this->entryRow($entryId);
		if ($entry === null) {
			throw new \RuntimeException('Payment not found while storing its share snapshot.');
		}

		$this->deleteForEntry($entryId);
		$projectId = isset($entry['project_id']) ? (int)$entry['project_id'] : 0;
		if ($projectId <= 0) {
			return;
		}

		$shares = $this->projectMemberShares($projectId);
		$allocations = EntryShareCalculator::calculate(
			$this->amountCents($entry),
			(string)($entry['split_mode'] ?? 'project_shares'),
			isset($entry['split_user_id']) ? (string)$entry['split_user_id'] : null,
			(string)($entry['user_id'] ?? ''),
			$shares,
		);

		foreach ($allocations as $userId => $allocation) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('cobudget_entry_shares')
				->values([
					'entry_id' => $qb->createNamedParameter($entryId, \PDO::PARAM_INT),
					'user_id' => $qb->createNamedParameter($userId),
					'share_basis_points' => $qb->createNamedParameter($allocation['share_basis_points'], \PDO::PARAM_INT),
					'amount_cents' => $qb->createNamedParameter($allocation['amount_cents'], \PDO::PARAM_INT),
				]);
			$qb->executeStatement();
		}
	}

	public function hasShares(int $entryId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('entry_id')
			->from('cobudget_entry_shares')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->setMaxResults(1);

		return (bool)$qb->executeQuery()->fetch();
	}

	/**
	 * Adds snapshot_share_cents to shared entries that have a stored allocation.
	 * Personal entries deliberately have no share rows and retain their owner-based fallback.
	 */
	public function attachPersonalShares(array $entries, string $userId): array {
		$entryIds = [];
		foreach ($entries as $entry) {
			$entryId = (int)($entry['id'] ?? 0);
			if ($entryId > 0 && !empty($entry['project_id'])) {
				$entryIds[] = $entryId;
			}
		}
		$shares = $this->personalSharesForEntries($entryIds, $userId);

		foreach ($entries as &$entry) {
			$entryId = (int)($entry['id'] ?? 0);
			if ($entryId > 0 && array_key_exists($entryId, $shares)) {
				$entry['snapshot_share_cents'] = $shares[$entryId];
			}
		}
		unset($entry);

		return $entries;
	}

	/** @return array<int, int> */
	public function personalSharesForEntries(array $entryIds, string $userId): array {
		$entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn (int $id): bool => $id > 0)));
		$userId = trim($userId);
		if ($entryIds === [] || $userId === '') {
			return [];
		}

		$shares = [];
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('entry_id')
				->from('cobudget_entry_shares')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->groupBy('entry_id');
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$entryId = (int)$row['entry_id'];
				$shares[$entryId] = 0;
			}
			$result->closeCursor();

			$qb = $this->db->getQueryBuilder();
			$qb->select('entry_id', 'amount_cents')
				->from('cobudget_entry_shares')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$shares[(int)$row['entry_id']] = max(0, (int)$row['amount_cents']);
			}
			$result->closeCursor();
		}

		return $shares;
	}

	/** @return array<int, array<string, array{share_basis_points: int, amount_cents: int}>> */
	public function sharesForEntries(array $entryIds): array {
		$entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn (int $id): bool => $id > 0)));
		if ($entryIds === []) {
			return [];
		}

		$shares = [];
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('entry_id', 'user_id', 'share_basis_points', 'amount_cents')
				->from('cobudget_entry_shares')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->orderBy('id', 'ASC');
			$result = $qb->executeQuery();
			foreach ($result->fetchAll() as $row) {
				$shares[(int)$row['entry_id']][(string)$row['user_id']] = [
					'share_basis_points' => max(0, (int)$row['share_basis_points']),
					'amount_cents' => max(0, (int)$row['amount_cents']),
				];
			}
			$result->closeCursor();
		}

		return $shares;
	}

	public function deleteForEntry(int $entryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('cobudget_entry_shares')
			->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteForEntries(array $entryIds): void {
		$entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn (int $id): bool => $id > 0)));
		foreach (array_chunk($entryIds, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('cobudget_entry_shares')
				->where($qb->expr()->in('entry_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();
		}
	}

	private function entryRow(int $entryId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'project_id', 'amount', 'amount_cents', 'split_mode', 'split_user_id')
			->from('cobudget_entries')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($entryId, \PDO::PARAM_INT)))
			->setMaxResults(1);
		$row = $qb->executeQuery()->fetch();

		return $row ?: null;
	}

	/** @return array<string, int> */
	private function projectMemberShares(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'share_basis_points')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$shares = [];
		foreach ($result->fetchAll() as $row) {
			$userId = trim((string)($row['user_id'] ?? ''));
			if ($userId !== '') {
				$shares[$userId] = max(0, (int)($row['share_basis_points'] ?? 0));
			}
		}
		$result->closeCursor();

		return $shares;
	}

	private function amountCents(array $entry): int {
		if (isset($entry['amount_cents']) && is_numeric($entry['amount_cents'])) {
			return abs((int)$entry['amount_cents']);
		}

		return (int)round(abs((float)($entry['amount'] ?? 0)) * 100, 0, PHP_ROUND_HALF_UP);
	}
}
