<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000023Date20261120040000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$files = $schema->getTable('cobudget_import_files');
		if ($files->hasColumn('archive_name')) return null;
		$files->addColumn('archive_name', 'string', ['length' => 255, 'notnull' => false]);
		return $schema;
	}
}
