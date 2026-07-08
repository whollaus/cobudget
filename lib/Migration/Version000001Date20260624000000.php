<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20260624000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createWorkspaces($schema);
		$this->createProjects($schema);
		$this->createMembers($schema);
		$this->createCategories($schema);
		$this->createPaymentPartners($schema);
		$this->createEntries($schema);
		$this->createTemplates($schema);
		$this->createEntryAttachments($schema);
		$this->createSettlements($schema);
		$this->createBudgetGoals($schema);
		$this->createBudgetSnapshots($schema);
		$this->createHashtags($schema);
		$this->createEntryHistory($schema);
		$this->addPerformanceIndexes($schema);

		return $schema;
	}

	private function createWorkspaces(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_workspaces')) {
			return;
		}

		$table = $schema->createTable('cobudget_workspaces');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', 'string', ['notnull' => true, 'length' => 128]);
		$table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('is_default', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('created_at', 'integer', ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'cb_ws_user');
	}

	private function createProjects(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_projects')) {
			return;
		}

		$table = $schema->createTable('cobudget_projects');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
		$table->addColumn('owner_id', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('created_at', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('color', 'string', ['notnull' => false, 'length' => 7, 'default' => '#0082c9']);
		$table->addColumn('is_archived', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('workspace_id', 'integer', ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['owner_id'], 'cb_proj_owner');
		$table->addIndex(['workspace_id'], 'cb_proj_ws');
	}

	private function createMembers(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_members')) {
			return;
		}

		$table = $schema->createTable('cobudget_members');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('project_id', 'integer', ['notnull' => true]);
		$table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('share_basis_points', 'integer', ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['project_id'], 'cb_mem_proj');
		$table->addIndex(['user_id'], 'cb_mem_user');
	}

	private function createCategories(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_categories')) {
			return;
		}

		$table = $schema->createTable('cobudget_categories');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', 'string', ['notnull' => true, 'length' => 128]);
		$table->addColumn('is_global', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('user_id', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('workspace_id', 'integer', ['notnull' => false]);
		$table->addColumn('icon', 'string', ['notnull' => false, 'length' => 64, 'default' => 'Shape']);
		$table->addColumn('type', 'string', ['notnull' => true, 'length' => 32, 'default' => 'expense']);
		$table->addColumn('project_id', 'integer', ['notnull' => false]);
		$table->addColumn('is_hidden', 'boolean', ['notnull' => true, 'default' => false]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['workspace_id'], 'cb_cat_ws');
		$table->addIndex(['project_id'], 'cb_cat_project');
	}

	private function createPaymentPartners(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_payment_partners')) {
			return;
		}

		$table = $schema->createTable('cobudget_payment_partners');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', 'string', ['notnull' => true, 'length' => 128]);
		$table->addColumn('is_global', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('user_id', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('workspace_id', 'integer', ['notnull' => false]);
		$table->addColumn('type', 'string', ['notnull' => true, 'length' => 32, 'default' => 'expense']);
		$table->addColumn('project_id', 'integer', ['notnull' => false]);
		$table->addColumn('is_hidden', 'boolean', ['notnull' => true, 'default' => false]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['workspace_id'], 'cb_partner_ws');
		$table->addIndex(['project_id'], 'cb_partner_project');
	}

	private function createEntries(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_entries')) {
			return;
		}

		$table = $schema->createTable('cobudget_entries');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('project_id', 'integer', ['notnull' => false]);
		$table->addColumn('type', 'string', ['notnull' => true, 'length' => 32, 'default' => 'expense']);
		$table->addColumn('amount', 'decimal', ['notnull' => true, 'precision' => 10, 'scale' => 2]);
		$table->addColumn('amount_cents', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addColumn('currency', 'string', ['notnull' => true, 'length' => 10, 'default' => 'EUR']);
		$table->addColumn('date', 'integer', ['notnull' => true]);
		$table->addColumn('category_id', 'integer', ['notnull' => false]);
		$table->addColumn('payment_partner_id', 'integer', ['notnull' => false]);
		$table->addColumn('description', 'string', ['notnull' => true, 'length' => 512, 'default' => '']);
		$table->addColumn('split_mode', 'string', ['notnull' => true, 'length' => 32, 'default' => 'project_shares']);
		$table->addColumn('split_user_id', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('is_settled', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('settled_at', 'integer', ['notnull' => false]);
		$table->addColumn('settlement_id', 'integer', ['notnull' => false]);
		$table->addColumn('recurrence_interval', 'string', ['notnull' => false, 'length' => 16]);
		$table->addColumn('recurrence_multiplier', 'integer', ['notnull' => false]);
		$table->addColumn('recurrence_next_date', 'integer', ['notnull' => false]);
		$table->addColumn('recurrence_end_date', 'integer', ['notnull' => false]);
		$table->addColumn('recurrence_parent_id', 'integer', ['notnull' => false]);
		$table->addColumn('recurrence_series_id', 'integer', ['notnull' => false]);
		$table->addColumn('is_subscription', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('is_fixed_cost', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('is_child_related', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('is_important', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('needs_review', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('is_tax_relevant', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('reminder_date', 'integer', ['notnull' => false]);
		$table->addColumn('reminder_notified', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('reminder_text', 'string', ['notnull' => false, 'length' => 255]);
		$table->addColumn('workspace_id', 'integer', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['project_id'], 'cb_ent_project');
		$table->addIndex(['user_id'], 'cb_ent_user');
		$table->addIndex(['workspace_id'], 'cb_ent_ws');
		$table->addIndex(['category_id'], 'cb_ent_category');
		$table->addIndex(['payment_partner_id'], 'cb_ent_partner');
		$table->addIndex(['recurrence_series_id'], 'cb_ent_rec_series');
		$table->addIndex(['settlement_id'], 'cb_ent_settlement');
	}

	private function createTemplates(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_templates')) {
			return;
		}

		$table = $schema->createTable('cobudget_templates');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('name', 'string', ['notnull' => true, 'length' => 128]);
		$table->addColumn('description', 'string', ['notnull' => false, 'length' => 512]);
		$table->addColumn('type', 'string', ['notnull' => true, 'length' => 32, 'default' => 'expense']);
		$table->addColumn('amount', 'decimal', ['notnull' => false, 'precision' => 10, 'scale' => 2]);
		$table->addColumn('amount_cents', 'bigint', ['notnull' => false]);
		$table->addColumn('category_id', 'integer', ['notnull' => false]);
		$table->addColumn('payment_partner_id', 'integer', ['notnull' => false]);
		$table->addColumn('project_id', 'integer', ['notnull' => false]);
		$table->addColumn('split_mode', 'string', ['notnull' => true, 'length' => 32, 'default' => 'project_shares']);
		$table->addColumn('split_user_id', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('is_subscription', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('is_fixed_cost', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('is_child_related', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('is_important', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('needs_review', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('is_tax_relevant', 'boolean', ['notnull' => true, 'default' => false]);
		$table->addColumn('workspace_id', 'integer', ['notnull' => true]);
		$table->addColumn('usage_count', 'integer', ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'cb_tpl_user');
		$table->addIndex(['workspace_id'], 'cb_tpl_ws');
		$table->addIndex(['project_id'], 'cb_tpl_project');
		$table->addIndex(['payment_partner_id'], 'cb_tpl_partner');
	}

	private function createEntryAttachments(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_entry_attachments')) {
			return;
		}

		$table = $schema->createTable('cobudget_entry_attachments');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('entry_id', 'integer', ['notnull' => true]);
		$table->addColumn('workspace_id', 'integer', ['notnull' => true]);
		$table->addColumn('owner_user_id', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('file_id', 'integer', ['notnull' => false]);
		$table->addColumn('file_path', 'string', ['notnull' => true, 'length' => 1024]);
		$table->addColumn('file_name', 'string', ['notnull' => true, 'length' => 255]);
		$table->addColumn('mime_type', 'string', ['notnull' => false, 'length' => 128]);
		$table->addColumn('file_size', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addColumn('created_at', 'integer', ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['entry_id'], 'cb_att_entry');
		$table->addIndex(['workspace_id'], 'cb_att_ws');
		$table->addIndex(['owner_user_id'], 'cb_att_owner');
		$table->addIndex(['file_id'], 'cb_att_file');
	}

	private function createSettlements(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('cobudget_settlements')) {
			$table = $schema->createTable('cobudget_settlements');
			$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('project_id', 'integer', ['notnull' => true]);
			$table->addColumn('workspace_id', 'integer', ['notnull' => true]);
			$table->addColumn('created_by', 'string', ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', 'integer', ['notnull' => true, 'default' => 0]);
			$table->addColumn('currency', 'string', ['notnull' => true, 'length' => 10, 'default' => 'EUR']);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['project_id'], 'cb_set_project');
			$table->addIndex(['workspace_id'], 'cb_set_ws');
		}

		if (!$schema->hasTable('cobudget_settlement_balances')) {
			$table = $schema->createTable('cobudget_settlement_balances');
			$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('settlement_id', 'integer', ['notnull' => true]);
			$table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
			$table->addColumn('display_name', 'string', ['notnull' => true, 'length' => 255]);
			$table->addColumn('paid_cents', 'bigint', ['notnull' => true, 'default' => 0]);
			$table->addColumn('share_cents', 'bigint', ['notnull' => true, 'default' => 0]);
			$table->addColumn('balance_cents', 'bigint', ['notnull' => true, 'default' => 0]);
			$table->addColumn('share_basis_points', 'integer', ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['settlement_id'], 'cb_setbal_settlement');
		}

		if (!$schema->hasTable('cobudget_settlement_transfers')) {
			$table = $schema->createTable('cobudget_settlement_transfers');
			$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('settlement_id', 'integer', ['notnull' => true]);
			$table->addColumn('from_user_id', 'string', ['notnull' => true, 'length' => 64]);
			$table->addColumn('from_display_name', 'string', ['notnull' => true, 'length' => 255]);
			$table->addColumn('to_user_id', 'string', ['notnull' => true, 'length' => 64]);
			$table->addColumn('to_display_name', 'string', ['notnull' => true, 'length' => 255]);
			$table->addColumn('amount_cents', 'bigint', ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['settlement_id'], 'cb_settr_settlement');
		}
	}

	private function createBudgetGoals(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_budget_goals')) {
			return;
		}

		$table = $schema->createTable('cobudget_budget_goals');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('workspace_id', 'integer', ['notnull' => true]);
		$table->addColumn('name', 'string', ['notnull' => true, 'length' => 128]);
		$table->addColumn('amount_cents', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addColumn('period', 'string', ['notnull' => true, 'length' => 16, 'default' => 'year']);
		$table->addColumn('mode', 'string', ['notnull' => true, 'length' => 16, 'default' => 'flexible']);
		$table->addColumn('criteria_json', 'text', ['notnull' => false]);
		$table->addColumn('created_at', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', 'integer', ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id', 'workspace_id'], 'cb_budget_user_ws');
		$table->addIndex(['workspace_id'], 'cb_budget_ws');
	}

	private function createBudgetSnapshots(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_budget_snapshots')) {
			return;
		}

		$table = $schema->createTable('cobudget_budget_snapshots');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('budget_goal_id', 'integer', ['notnull' => true]);
		$table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('workspace_id', 'integer', ['notnull' => true]);
		$table->addColumn('snapshot_reason', 'string', ['notnull' => true, 'length' => 32, 'default' => 'period_closed']);
		$table->addColumn('goal_name', 'string', ['notnull' => true, 'length' => 128]);
		$table->addColumn('amount_cents', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addColumn('period', 'string', ['notnull' => true, 'length' => 16, 'default' => 'year']);
		$table->addColumn('mode', 'string', ['notnull' => true, 'length' => 16, 'default' => 'flexible']);
		$table->addColumn('criteria_json', 'text', ['notnull' => false]);
		$table->addColumn('period_start', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('period_end', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('spent_cents', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addColumn('planned_cents', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addColumn('buffer_cents', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addColumn('forecast_cents', 'bigint', ['notnull' => true, 'default' => 0]);
		$table->addColumn('progress_tenths', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('status', 'string', ['notnull' => true, 'length' => 16, 'default' => 'ok']);
		$table->addColumn('created_at', 'integer', ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id', 'workspace_id'], 'cb_bsnap_user_ws');
		$table->addIndex(['budget_goal_id', 'period_start', 'period_end'], 'cb_bsnap_goal_period');
		$table->addIndex(['workspace_id', 'period_start'], 'cb_bsnap_ws_period');
	}

	private function createHashtags(ISchemaWrapper $schema): void {
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
	}

	private function createEntryHistory(ISchemaWrapper $schema): void {
		if ($schema->hasTable('cobudget_entry_history')) {
			return;
		}

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

	private function addPerformanceIndexes(ISchemaWrapper $schema): void {
		$this->addEntryIndexes($schema);
		$this->addProjectIndexes($schema);
		$this->addLookupIndexes($schema);
		$this->addAttachmentAndSettlementIndexes($schema);
		$this->addBudgetIndexes($schema);
	}

	private function addEntryIndexes(ISchemaWrapper $schema): void {
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'date', 'id'], 'cb_ent_ws_date_id');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'type', 'date'], 'cb_ent_ws_type_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'project_id', 'is_settled', 'date'], 'cb_ent_ws_pr_set_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'user_id', 'date'], 'cb_ent_ws_user_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'category_id', 'date'], 'cb_ent_ws_cat_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'payment_partner_id', 'date'], 'cb_ent_ws_pp_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'recurrence_next_date'], 'cb_ent_ws_recur');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'reminder_date', 'reminder_notified'], 'cb_ent_ws_remind');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'is_important', 'date'], 'cb_ent_ws_imp_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'needs_review', 'date'], 'cb_ent_ws_review_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'is_fixed_cost', 'date'], 'cb_ent_ws_fix_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'is_subscription', 'date'], 'cb_ent_ws_sub_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'is_child_related', 'date'], 'cb_ent_ws_child_dt');
		$this->addIndexIfMissing($schema, 'cobudget_entries', ['workspace_id', 'is_tax_relevant', 'date'], 'cb_ent_ws_tax_dt');
	}

	private function addProjectIndexes(ISchemaWrapper $schema): void {
		$this->addIndexIfMissing($schema, 'cobudget_projects', ['workspace_id', 'is_archived'], 'cb_proj_ws_arch');
		$this->addIndexIfMissing($schema, 'cobudget_members', ['user_id', 'project_id'], 'cb_mem_user_proj');
		$this->addIndexIfMissing($schema, 'cobudget_members', ['project_id', 'user_id'], 'cb_mem_proj_user');
	}

	private function addLookupIndexes(ISchemaWrapper $schema): void {
		$this->addIndexIfMissing($schema, 'cobudget_categories', ['workspace_id', 'type', 'user_id', 'is_hidden'], 'cb_cat_ws_type_user');
		$this->addIndexIfMissing($schema, 'cobudget_categories', ['workspace_id', 'project_id', 'type'], 'cb_cat_ws_proj_type');
		$this->addIndexIfMissing($schema, 'cobudget_payment_partners', ['workspace_id', 'type', 'user_id', 'is_hidden'], 'cb_pp_ws_type_user');
		$this->addIndexIfMissing($schema, 'cobudget_payment_partners', ['workspace_id', 'project_id', 'type'], 'cb_pp_ws_proj_type');
		$this->addIndexIfMissing($schema, 'cobudget_templates', ['workspace_id', 'user_id', 'usage_count'], 'cb_tpl_ws_user_use');
	}

	private function addAttachmentAndSettlementIndexes(ISchemaWrapper $schema): void {
		$this->addIndexIfMissing($schema, 'cobudget_entry_attachments', ['workspace_id', 'entry_id'], 'cb_att_ws_entry');
		$this->addIndexIfMissing($schema, 'cobudget_settlements', ['workspace_id', 'project_id', 'created_at'], 'cb_set_ws_proj_time');
		$this->addIndexIfMissing($schema, 'cobudget_settlement_balances', ['settlement_id', 'user_id'], 'cb_setbal_set_user');
		$this->addIndexIfMissing($schema, 'cobudget_settlement_transfers', ['settlement_id', 'from_user_id'], 'cb_settr_set_from');
		$this->addIndexIfMissing($schema, 'cobudget_settlement_transfers', ['settlement_id', 'to_user_id'], 'cb_settr_set_to');
	}

	private function addBudgetIndexes(ISchemaWrapper $schema): void {
		$this->addIndexIfMissing($schema, 'cobudget_budget_goals', ['workspace_id', 'user_id', 'updated_at'], 'cb_budget_ws_user_upd');
		$this->addIndexIfMissing($schema, 'cobudget_budget_snapshots', ['workspace_id', 'user_id', 'period_start'], 'cb_bsnap_ws_user_per');
		$this->addIndexIfMissing($schema, 'cobudget_budget_snapshots', ['budget_goal_id', 'created_at'], 'cb_bsnap_goal_time');
	}

	private function addIndexIfMissing(ISchemaWrapper $schema, string $tableName, array $columns, string $indexName): void {
		if (!$schema->hasTable($tableName)) {
			return;
		}

		$table = $schema->getTable($tableName);
		if ($table->hasIndex($indexName)) {
			return;
		}

		$table->addIndex($columns, $indexName);
	}
}
