<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000019Date20261120000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->getTable('cobudget_import_rules');
		if ($table->hasColumn('last_error')) return null;
		$table->addColumn('last_error', 'boolean', ['notnull' => false]);
		return $schema;
	}
}
