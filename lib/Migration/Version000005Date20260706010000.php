<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000005Date20260706010000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('cobudget_entry_history')) {
			$table = $schema->createTable('cobudget_entry_history');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('entry_id', 'integer', [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('workspace_id', 'integer', [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('project_id', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('changed_by', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('changed_by_display_name', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('changed_at', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('change_group', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('field', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('old_value', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('new_value', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('old_display', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('new_display', 'text', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['entry_id', 'workspace_id'], 'cb_ehist_entry_ws');
			$table->addIndex(['workspace_id', 'changed_at'], 'cb_ehist_ws_time');
			$table->addIndex(['project_id'], 'cb_ehist_project');
		}

		return $schema;
	}
}
