<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000004Date20260726020000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('cobudget_projects')) {
			return null;
		}

		$table = $schema->getTable('cobudget_projects');
		if (!$table->hasColumn('hidden_category_ids')) {
			$table->addColumn('hidden_category_ids', 'text', [
				'notnull' => true,
				'default' => '[]',
			]);
		}

		return $schema;
	}
}
