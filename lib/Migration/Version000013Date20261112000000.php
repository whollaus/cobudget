<?php
declare(strict_types=1);
namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000013Date20261112000000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('cobudget_reminders');
		if (!$table->hasColumn('email_notified')) {
			$table->addColumn('email_notified', 'boolean', ['notnull' => true, 'default' => false]);
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Never replay already delivered reminders when introducing the new channel.
		$q = $this->db->getQueryBuilder();
		$q->update('cobudget_reminders')->set('email_notified', $q->createNamedParameter(true, \PDO::PARAM_BOOL))
			->where($q->expr()->eq('notified', $q->createNamedParameter(true, \PDO::PARAM_BOOL)))->executeStatement();
	}
}
