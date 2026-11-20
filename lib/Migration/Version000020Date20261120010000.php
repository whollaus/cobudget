<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000020Date20261120010000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$rules = $schema->getTable('cobudget_import_rules');
		$files = $schema->getTable('cobudget_import_files');
		if (!$rules->hasColumn('scan_from')) $rules->addColumn('scan_from', 'string', ['length' => 255, 'notnull' => false]);
		if (!$rules->hasColumn('report_after')) $rules->addColumn('report_after', 'bigint', ['notnull' => true, 'default' => 0]);
		if (!$files->hasColumn('currency')) $files->addColumn('currency', 'string', ['length' => 10, 'notnull' => false]);
		return $schema;
	}
}
