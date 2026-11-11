<?php
declare(strict_types=1);
namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000010Date20261104010000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('cobudget_calendar_links')) {
			$table = $schema->createTable('cobudget_calendar_links');
			$table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('entry_id', 'bigint', ['notnull' => true]);
			$table->addColumn('calendar_id', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('object_uri', 'string', ['length' => 128, 'notnull' => true]);
			$table->addColumn('event_uid', 'string', ['length' => 128, 'notnull' => true]);
			$table->addColumn('fingerprint', 'string', ['length' => 64, 'notnull' => true, 'default' => '']);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'entry_id', 'calendar_id'], 'cb_calendar_user_entry');
		}
		return $schema;
	}
}
