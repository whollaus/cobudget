<?php
declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000015Date20261119010000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('cobudget_import_rules')) {
			$table = $schema->createTable('cobudget_import_rules');
			$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('workspace_id', 'integer', ['notnull' => true]);
			$table->addColumn('project_id', 'integer', ['notnull' => false]);
			$table->addColumn('folder', 'text', ['notnull' => true]);
			$table->addColumn('folder_id', 'bigint', ['notnull' => true]);
			// NULL when paused; only one active rule may consume a given Files node.
			$table->addColumn('active_folder_id', 'bigint', ['notnull' => false]);
			$table->addColumn('timezone', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('interval_minutes', 'integer', ['notnull' => true]);
			$table->addColumn('notify_mode', 'string', ['length' => 8, 'notnull' => true]);
			$table->addColumn('enabled', 'boolean', ['notnull' => true]);
			$table->addColumn('last_message', 'text', ['notnull' => false]);
			$table->addColumn('last_run', 'bigint', ['notnull' => true, 'default' => 0]);
			$table->addColumn('next_run', 'bigint', ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['active_folder_id'], 'cb_imp_active_folder');
			$table->addIndex(['enabled', 'next_run'], 'cb_imp_due');
			$table->addIndex(['user_id', 'workspace_id'], 'cb_imp_rules_user_ws');
		}
		if (!$schema->hasTable('cobudget_import_files')) {
			$table = $schema->createTable('cobudget_import_files');
			$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('workspace_id', 'integer', ['notnull' => true]);
			$table->addColumn('project_id', 'integer', ['notnull' => false]);
			$table->addColumn('rule_id', 'integer', ['notnull' => true]);
			$table->addColumn('commit_limit', 'integer', ['notnull' => true, 'default' => 1]);
			$table->addColumn('scope_key', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('name_key', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('digest', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('state', 'string', ['length' => 16, 'notnull' => true]);
			$table->addColumn('imported_count', 'integer', ['notnull' => true, 'default' => 0]);
			$table->addColumn('next_row', 'integer', ['notnull' => true, 'default' => 0]);
			$table->addColumn('detail', 'text', ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['rule_id', 'state'], 'cb_imp_file_progress');
			$table->addUniqueIndex(['scope_key', 'name_key'], 'cb_imp_file_name');
			$table->addIndex(['scope_key', 'digest', 'state'], 'cb_imp_file_digest');
			$table->addIndex(['user_id', 'workspace_id'], 'cb_imp_files_user_ws');
		}
		return $schema;
	}
}
