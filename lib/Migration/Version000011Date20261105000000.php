<?php
declare(strict_types = 1);

namespace OCA\CoBudget\Migration;
use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
class Version000011Date20261105000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('cobudget_reminders')) {
			$t = $schema->createTable('cobudget_reminders');
			$t->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
			$t->addColumn('entry_id', 'bigint', ['notnull' => false]);
			$t->addColumn('title', 'string', ['length' => 255, 'notnull' => true]);
			$t->addColumn('description', 'text', ['notnull' => false]);
			$t->addColumn('remind_at', 'bigint', ['notnull' => true]);
			$t->addColumn('due_date', 'string', ['length' => 10, 'notnull' => false]);
			$t->addColumn('labels', 'string', ['length' => 255, 'notnull' => true, 'default' => '[]']);
			$t->addColumn('keep_open', 'boolean', ['notnull' => true, 'default' => false]);
			$t->addColumn('completed_at', 'bigint', ['notnull' => false]);
			$t->addColumn('archived_at', 'bigint', ['notnull' => false]);
			$t->addColumn('dismissed', 'boolean', ['notnull' => true, 'default' => false]);
			$t->addColumn('notified', 'boolean', ['notnull' => true, 'default' => false]);
			$t->addColumn('entry_signature', 'string', ['length' => 64, 'notnull' => true, 'default' => '']);
			$t->addColumn('revision', 'integer', ['notnull' => true, 'default' => 1]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['user_id', 'entry_id'], 'cb_reminder_user_entry');
			$t->addIndex(['user_id', 'remind_at'], 'cb_reminder_user_date');
		}
		$links = $schema->getTable('cobudget_calendar_links');
		if (!$links->hasColumn('reminder_id')) {
			$links->addColumn('reminder_id', 'bigint', ['notnull' => false]);
			$links->getColumn('entry_id')->setNotnull(false);
			$links->addUniqueIndex(['user_id', 'reminder_id', 'calendar_id'], 'cb_calendar_user_reminder');
		}
		return $schema;
	}
}
