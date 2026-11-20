<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000021Date20261120020000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$rules = $schema->getTable('cobudget_import_rules');
		if ($rules->hasColumn('report_etag')) return null;
		$rules->addColumn('report_etag', 'string', ['length' => 64, 'notnull' => false]);
		return $schema;
	}
}
