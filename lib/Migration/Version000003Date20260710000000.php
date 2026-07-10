<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCA\CoBudget\Service\EntryShareCalculator;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date20260710000000 extends SimpleMigrationStep {

	public function __construct(private IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('cobudget_entry_shares')) {
			return $schema;
		}

		$table = $schema->createTable('cobudget_entry_shares');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('entry_id', 'integer', ['notnull' => true]);
		$table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('share_basis_points', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('amount_cents', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['entry_id', 'user_id'], 'cb_esh_entry_user');
		$table->addIndex(['user_id', 'entry_id'], 'cb_esh_user_entry');

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$projectShares = [];
		$settlementShares = [];
		$lastEntryId = 0;
		do {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'user_id', 'project_id', 'amount', 'amount_cents', 'split_mode', 'split_user_id', 'settlement_id')
				->from('cobudget_entries')
				->where($qb->expr()->isNotNull('project_id'))
				->andWhere($qb->expr()->gt('id', $qb->createNamedParameter($lastEntryId, \PDO::PARAM_INT)))
				->orderBy('id', 'ASC')
				->setMaxResults(500);
			$result = $qb->executeQuery();
			$entries = $result->fetchAll();
			$result->closeCursor();

			foreach ($entries as $entry) {
				$entryId = (int)($entry['id'] ?? 0);
				$lastEntryId = max($lastEntryId, $entryId);
				$projectId = (int)($entry['project_id'] ?? 0);
				$settlementId = (int)($entry['settlement_id'] ?? 0);
				if ($entryId <= 0 || $projectId <= 0) {
					continue;
				}

				if ($settlementId > 0) {
					$settlementShares[$settlementId] ??= $this->loadSettlementShares($settlementId);
				}
				$shares = $settlementId > 0 ? ($settlementShares[$settlementId] ?? []) : [];
				if ($shares === []) {
					$projectShares[$projectId] ??= $this->loadProjectShares($projectId);
					$shares = $projectShares[$projectId];
				}

				$allocations = EntryShareCalculator::calculate(
					$this->amountCents($entry),
					(string)($entry['split_mode'] ?? 'project_shares'),
					isset($entry['split_user_id']) ? (string)$entry['split_user_id'] : null,
					(string)($entry['user_id'] ?? ''),
					$shares,
				);
				foreach ($allocations as $userId => $allocation) {
					$insert = $this->db->getQueryBuilder();
					$insert->insert('cobudget_entry_shares')
						->values([
							'entry_id' => $insert->createNamedParameter($entryId, \PDO::PARAM_INT),
							'user_id' => $insert->createNamedParameter($userId),
							'share_basis_points' => $insert->createNamedParameter($allocation['share_basis_points'], \PDO::PARAM_INT),
							'amount_cents' => $insert->createNamedParameter($allocation['amount_cents'], \PDO::PARAM_INT),
						]);
					$insert->executeStatement();
				}
			}
		} while (count($entries) === 500);
	}

	/** @return array<string, int> */
	private function loadProjectShares(int $projectId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'share_basis_points')
			->from('cobudget_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');

		return $this->shareMap($qb->executeQuery());
	}

	/** @return array<string, int> */
	private function loadSettlementShares(int $settlementId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'share_basis_points')
			->from('cobudget_settlement_balances')
			->where($qb->expr()->eq('settlement_id', $qb->createNamedParameter($settlementId, \PDO::PARAM_INT)))
			->orderBy('id', 'ASC');

		return $this->shareMap($qb->executeQuery());
	}

	/** @return array<string, int> */
	private function shareMap($result): array {
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
