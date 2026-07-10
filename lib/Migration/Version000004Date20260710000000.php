<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000004Date20260710000000 extends SimpleMigrationStep {

	public function __construct(private IDBConnection $db) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('cobudget_members')) {
			return;
		}

		$seen = [];
		$lastId = 0;
		do {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'project_id', 'user_id')
				->from('cobudget_members')
				->where($qb->expr()->gt('id', $qb->createNamedParameter($lastId, \PDO::PARAM_INT)))
				->orderBy('id', 'ASC')
				->setMaxResults(500);
			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			$duplicates = [];
			foreach ($rows as $row) {
				$id = (int)$row['id'];
				$lastId = max($lastId, $id);
				$key = (int)$row['project_id'] . "\0" . (string)$row['user_id'];
				if (isset($seen[$key])) {
					$duplicates[] = $id;
					continue;
				}
				$seen[$key] = true;
			}

			foreach (array_chunk($duplicates, 500) as $chunk) {
				$delete = $this->db->getQueryBuilder();
				$delete->delete('cobudget_members')
					->where($delete->expr()->in('id', $delete->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
				$delete->executeStatement();
			}
		} while (count($rows) === 500);
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('cobudget_members')) {
			return $schema;
		}

		$table = $schema->getTable('cobudget_members');
		if ($table->hasIndex('cb_mem_proj_user')) {
			if ($table->getIndex('cb_mem_proj_user')->isUnique()) {
				return $schema;
			}
			$table->dropIndex('cb_mem_proj_user');
		}
		$table->addUniqueIndex(['project_id', 'user_id'], 'cb_mem_proj_user');

		return $schema;
	}
}
