<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000005Date20260727000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('cobudget_payment_partners')) {
			return null;
		}

		$table = $schema->getTable('cobudget_payment_partners');
		$stringColumns = [
			'number' => 128,
			'salutation' => 64,
			'title' => 128,
			'company_name' => 255,
			'additional' => 255,
			'vat_id' => 64,
			'first_name' => 128,
			'last_name' => 128,
			'street' => 255,
			'postal_code' => 32,
			'city' => 128,
			'country' => 128,
			'email' => 254,
			'phone' => 64,
			'mobile' => 64,
			'fax' => 64,
			'web' => 512,
			'account_holder' => 255,
			'iban' => 64,
			'bic' => 32,
			'bank' => 255,
			'bank_code' => 64,
			'account_number' => 64,
		];
		foreach ($stringColumns as $column => $length) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, 'string', [
					'notnull' => false,
					'length' => $length,
				]);
			}
		}

		foreach (['address_note', 'note'] as $column) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, 'text', [
					'notnull' => false,
				]);
			}
		}

		return $schema;
	}
}
