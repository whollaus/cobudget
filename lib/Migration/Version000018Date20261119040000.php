<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000018Date20261119040000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('cobudget_import_files');
		if ($table->hasColumn('wait_message')) return null;
		$table->addColumn('wait_message', 'text', ['notnull' => false]);
		$table->addColumn('source_etag', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('last_retry', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addIndex(['rule_id', 'state', 'last_retry', 'id'], 'cb_imp_retry');
		return $schema;
	}
}
