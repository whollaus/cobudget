<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\IDBConnection;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000017Date20261119030000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('cobudget_import_files');
		if ($table->hasColumn('content_hash')) return null;
		$table->addColumn('content_hash', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('source_name', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('snapshot', 'text', ['length' => 5242880, 'notnull' => false]);
		$table->addIndex(['scope_key', 'content_hash'], 'cb_imp_content');
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$q = $this->db->getQueryBuilder();
		$q->update('cobudget_import_files')->set('state', $q->createNamedParameter('reporting'))
			->where($q->expr()->eq('state', $q->createNamedParameter('done')))
			->andWhere($q->expr()->eq('detail', $q->createNamedParameter('pending_archive')))
			->executeStatement();
	}
}
