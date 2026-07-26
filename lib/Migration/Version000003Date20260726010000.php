<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date20260726010000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('cobudget_categories')) {
			return null;
		}

		$table = $schema->getTable('cobudget_categories');
		if (!$table->hasColumn('parent_category_id')) {
			$table->addColumn('parent_category_id', 'integer', [
				'notnull' => false,
			]);
		}
		if (!$table->hasIndex('cb_cat_parent')) {
			$table->addIndex(['parent_category_id'], 'cb_cat_parent');
		}

		return $schema;
	}
}
