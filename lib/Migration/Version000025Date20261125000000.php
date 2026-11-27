<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000025Date20261125000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('cobudget_api_tokens')) {
			return null;
		}

		$table = $schema->createTable('cobudget_api_tokens');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('workspace_id', 'integer', ['notnull' => true]);
		$table->addColumn('name', 'string', ['length' => 128, 'notnull' => true]);
		$table->addColumn('token_prefix', 'string', ['length' => 24, 'notnull' => true]);
		$table->addColumn('token_hash', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('scopes_json', 'text', ['notnull' => true]);
		$table->addColumn('allow_personal', 'boolean', ['notnull' => true, 'default' => true]);
		$table->addColumn('project_mode', 'string', ['length' => 16, 'notnull' => true, 'default' => 'none']);
		$table->addColumn('project_ids_json', 'text', ['notnull' => true]);
		$table->addColumn('is_active', 'boolean', ['notnull' => true, 'default' => true]);
		$table->addColumn('expires_at', 'bigint', ['notnull' => false]);
		$table->addColumn('last_used_at', 'bigint', ['notnull' => false]);
		$table->addColumn('created_at', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', 'bigint', ['notnull' => true, 'default' => 0]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['token_hash'], 'cb_apitok_hash');
		$table->addIndex(['user_id', 'workspace_id'], 'cb_apitok_user_ws');
		$table->addIndex(['workspace_id'], 'cb_apitok_ws');

		return $schema;
	}
}
