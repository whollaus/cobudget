import { reactive } from 'vue'

// Local settings names and their API keys; export preferences save separately.
export const generalSettingsFields = {
	currency: 'currency',
	enableSubscriptions: 'enable_subscriptions',
	enableFixedCosts: 'enable_fixed_costs',
	enableChildRelated: 'enable_child_related',
	enableImportantPayments: 'enable_important_payments',
	enableReviewPayments: 'enable_review_payments',
	enableTaxRelevant: 'enable_tax_relevant',
	enableInsurance: 'enable_insurance',
	enableFuturePayments: 'enable_future_payments',
	enableReminders: 'enable_reminders',
	enableBudgetGoals: 'enable_budget_goals',
	enableAdvancedMasterData: 'enable_advanced_master_data',
	enableIncomes: 'enable_incomes',
	enableProjects: 'enable_projects',
	enableSharedProjects: 'enable_shared_projects',
	notifyReminders: 'notify_reminders',
	notifyPlannedPayments: 'notify_planned_payments',
	notifyProjectEntries: 'notify_project_entries',
	notifyProjectSettlements: 'notify_project_settlements',
	enableWorkspaces: 'enable_workspaces',
	showWorkspaceSwitcher: 'show_workspace_switcher',
	enableReceipts: 'enable_receipts',
	defaultStartPage: 'default_start_page',
	entryPageSize: 'entries_per_page',
	themeMode: 'theme_mode',
	showNextcloudHeaderDesktop: 'show_nextcloud_header_desktop',
	showNextcloudHeaderMobile: 'show_nextcloud_header_mobile',
	receiptStorageFolder: 'receipt_storage_folder',
	receiptFolderGrouping: 'receipt_folder_grouping',
	deleteReceiptsWithEntry: 'delete_receipts_with_entry',
}

const settings = reactive({})
const globalName = field => '$' + (field === 'entryPageSize' ? 'entriesPerPage' : field)

export function installReactiveSettings(app) {
	for (const field of Object.keys(generalSettingsFields)) {
		Object.defineProperty(app.config.globalProperties, globalName(field), {
			configurable: true,
			get: () => settings[field],
			set: value => { settings[field] = value },
		})
	}
}

// Only publish preferences after persistence succeeded. All consumers, including
// navigation and route guards, read the same reactive values.
export function applySavedSettings(saved) {
	for (const field of Object.keys(generalSettingsFields)) {
		if (Object.hasOwn(saved, field)) settings[field] = saved[field]
	}
}
