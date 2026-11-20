<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000022Date20261120030000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$rules = $schema->getTable('cobudget_import_rules');
		if ($rules->hasColumn('scan_batch')) return null;
		$rules->addColumn('scan_batch', 'text', ['notnull' => false]);
		return $schema;
	}
}
