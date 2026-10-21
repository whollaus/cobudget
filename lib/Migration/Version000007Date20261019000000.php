<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000007Date20261019000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('cobudget_entries')) {
			return null;
		}
		$table = $schema->getTable('cobudget_entries');
		if (!$table->hasColumn('is_insurance')) {
			$table->addColumn('is_insurance', 'boolean', [
				'notnull' => true,
				'default' => false,
			]);
		}
		return $schema;
	}
}
