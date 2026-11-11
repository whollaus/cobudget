<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000009Date20261104000000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('cobudget_entries');
		if (!$table->hasColumn('planned_notified')) {
			$table->addColumn('planned_notified', 'boolean', ['notnull' => true, 'default' => true]);
			$table->addColumn('planned_delivery', 'text', ['notnull' => false]);
			$table->addIndex(['planned_notified', 'date'], 'cb_entry_planned_due');
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Arm existing future originals only. Never announce historical payments or projections on upgrade.
		$qb = $this->db->getQueryBuilder();
		$qb->update('cobudget_entries')
			->set('planned_notified', $qb->createNamedParameter(false, \PDO::PARAM_BOOL))
			->where($qb->expr()->gt('date', $qb->createNamedParameter(time(), \PDO::PARAM_INT)))
			->andWhere($qb->expr()->isNull('source_entry_id'))
			->andWhere($qb->expr()->eq('is_settled', $qb->createNamedParameter(false, \PDO::PARAM_BOOL)))
			->executeStatement();
	}
}
