<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date20260705000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('cobudget_hashtags')) {
			$table = $schema->createTable('cobudget_hashtags');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('workspace_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('normalized_name', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('display_name', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('updated_at', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['workspace_id', 'normalized_name'], 'cb_hash_ws_name');
			$table->addIndex(['workspace_id', 'display_name'], 'cb_hash_ws_display');
		}

		if (!$schema->hasTable('cobudget_entry_hashtags')) {
			$table = $schema->createTable('cobudget_entry_hashtags');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('entry_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('hashtag_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('workspace_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['entry_id', 'hashtag_id'], 'cb_ehash_entry_hash');
			$table->addIndex(['workspace_id', 'hashtag_id'], 'cb_ehash_ws_hash');
			$table->addIndex(['entry_id'], 'cb_ehash_entry');
			$table->addIndex(['hashtag_id'], 'cb_ehash_hash');
		}

		return $schema;
	}
}
