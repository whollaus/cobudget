<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000014Date20261119000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$tableSchema = $schemaClosure();
		$entries = $tableSchema->getTable('cobudget_entries');
		if (!$entries->hasColumn('import_key')) {
			$entries->addColumn('import_key', 'string', ['length' => 64, 'notnull' => false]);
		}
		if (!$entries->hasIndex('cb_entry_import_key')) {
			$entries->addUniqueIndex(['import_key'], 'cb_entry_import_key');
		}
		return $tableSchema;
	}
}
