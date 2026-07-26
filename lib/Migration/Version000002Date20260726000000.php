<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000002Date20260726000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('cobudget_categories')) {
			return null;
		}

		$table = $schema->getTable('cobudget_categories');
		if (!$table->hasColumn('code')) {
			$table->addColumn('code', 'string', [
				'notnull' => false,
				'length' => 128,
			]);
		}

		return $schema;
	}
}
