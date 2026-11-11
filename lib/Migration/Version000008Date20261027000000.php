<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000008Date20261027000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('cobudget_projects');
		if (!$table->hasColumn('hidden_payment_partner_ids')) {
			$table->addColumn('hidden_payment_partner_ids', 'text', ['notnull' => false]);
		}
		return $schema;
	}
}
