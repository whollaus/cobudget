<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000024Date20261120050000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$files = $schema->getTable('cobudget_import_files');
		if ($files->hasColumn('archive_hash')) return null;
		$files->addColumn('archive_hash', 'string', ['length' => 64, 'notnull' => false]);
		return $schema;
	}
}
