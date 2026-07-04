<?php

declare(strict_types=1);

namespace OCA\CoBudget\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000002Date20260703000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->addEntryIndexes($schema);
		$this->addProjectIndexes($schema);
		$this->addLookupIndexes($schema);
		$this->addAttachmentAndSettlementIndexes($schema);
		$this->addBudgetIndexes($schema);

		return $schema;
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
