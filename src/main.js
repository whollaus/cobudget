import { createApp } from 'vue'
import App from './App.vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import axios from './services/http'
import { generateUrl } from '@nextcloud/router'
import { normalizeEntryPageSize } from './services/pagination'
import { applyThemeMode, normalizeThemeMode } from './services/themeMode'
import { formatInputAmount, formatMoney, formatMoneyFromCents, formatSignedMoney, parseAmount } from './utils/formatMoney'
import { installTexts } from './l10n/texts'
import './styles/tokens.css'

const TransactionsView = () => import(/* webpackChunkName: "cobudget-view-transactions" */ './views/TransactionsView.vue')
const ProjectList = () => import(/* webpackChunkName: "cobudget-view-project-list" */ './views/ProjectList.vue')
const ProjectDetail = () => import(/* webpackChunkName: "cobudget-view-project-detail" */ './views/ProjectDetail.vue')
const ProjectSettingsView = () => import(/* webpackChunkName: "cobudget-view-project-settings" */ './views/ProjectSettingsView.vue')
const ProjectSettlementsView = () => import(/* webpackChunkName: "cobudget-view-project-settlements" */ './views/ProjectSettlementsView.vue')
const BudgetGoalsView = () => import(/* webpackChunkName: "cobudget-view-budgets" */ './views/BudgetGoalsView.vue')
const BudgetGoalFormView = () => import(/* webpackChunkName: "cobudget-view-budget-form" */ './views/BudgetGoalFormView.vue')
const AnalyticsView = () => import(/* webpackChunkName: "cobudget-view-analytics" */ './views/AnalyticsView.vue')
const SettingsView = () => import(/* webpackChunkName: "cobudget-view-settings" */ './views/SettingsView.vue')

const routes = [
	{ path: '/', name: 'personal', component: TransactionsView },
	{ path: '/projects', name: 'projects', component: ProjectList },
	{ path: '/projects/:id/settings', name: 'project-settings', component: ProjectSettingsView, props: true },
	{ path: '/projects/:id/settlements', name: 'project-settlements', component: ProjectSettlementsView, props: true },
	{ path: '/projects/:id', name: 'project-detail', component: ProjectDetail, props: true },
	{ path: '/budgets', name: 'budgets', component: BudgetGoalsView },
	{ path: '/budgets/new', name: 'budget-new', component: BudgetGoalFormView },
	{ path: '/budgets/:id', name: 'budget-edit', component: BudgetGoalFormView, props: true },
	{ path: '/analytics', name: 'analytics', component: AnalyticsView },
	{ path: '/settings', name: 'settings', component: SettingsView },
	{ path: '/workspaces', redirect: '/settings' }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

const projectStartPagePattern = /^project:(\d+)$/
const projectRouteNames = ['projects', 'project-detail', 'project-settings', 'project-settlements']
const budgetRouteNames = ['budgets', 'budget-new', 'budget-edit']

const isRootHash = hash => hash === '' || hash === '#/'

const isArchivedProject = project => project?.is_archived === true || project?.is_archived === 1 || project?.is_archived === '1'

async function resolveProjectStartRoute(defaultStartPage) {
	const match = projectStartPagePattern.exec(defaultStartPage || '')
	if (!match) {
		return null
	}

	try {
		const response = await axios.get(generateUrl('/apps/cobudget/api/projects'))
		const projects = Array.isArray(response.data) ? response.data : []
		const project = projects.find(item => Number(item.id) === Number(match[1]) && !isArchivedProject(item))

		return project ? { name: 'project-detail', params: { id: String(project.id) } } : null
	} catch (e) {
		return null
	}
}

async function resolveDefaultStartRoute(defaultStartPage, enableProjects = true) {
	if (defaultStartPage === 'currentYear') {
		return { path: '/', query: { filter: 'currentYear' } }
	}

	if (!enableProjects && (defaultStartPage === 'projects' || projectStartPagePattern.test(defaultStartPage || ''))) {
		return null
	}

	if (defaultStartPage === 'projects') {
		return { name: 'projects' }
	}

	return resolveProjectStartRoute(defaultStartPage)
}

async function init() {
	const app = createApp(App)
	app.use(router)
	installTexts(app)
	app.config.globalProperties.$formatMoney = function(amount, currency = null, options = {}) {
		return formatMoney(amount, currency ?? this.$currency, options)
	}
	app.config.globalProperties.$formatSignedMoney = function(amount, currency = null, options = {}) {
		return formatSignedMoney(amount, currency ?? this.$currency, options)
	}
	app.config.globalProperties.$formatMoneyFromCents = function(cents, currency = null, options = {}) {
		return formatMoneyFromCents(cents, currency ?? this.$currency, options)
	}
	app.config.globalProperties.$formatInputAmount = formatInputAmount
	app.config.globalProperties.$parseAmount = parseAmount

	try {
		const res = await axios.get(generateUrl('/apps/cobudget/api/settings'), { skipWorkspaceHeader: true })
		app.config.globalProperties.$currency = res.data.currency || ''
		app.config.globalProperties.$enableSubscriptions = res.data.enable_subscriptions ?? true
		app.config.globalProperties.$enableFixedCosts = res.data.enable_fixed_costs ?? true
		app.config.globalProperties.$enableChildRelated = res.data.enable_child_related ?? true
		app.config.globalProperties.$enableImportantPayments = res.data.enable_important_payments ?? true
		app.config.globalProperties.$enableReviewPayments = res.data.enable_review_payments ?? true
		app.config.globalProperties.$enableTaxRelevant = res.data.enable_tax_relevant ?? true
		app.config.globalProperties.$enableFuturePayments = res.data.enable_future_payments ?? true
		app.config.globalProperties.$enableTemplates = res.data.enable_templates ?? true
		app.config.globalProperties.$enableBudgetGoals = res.data.enable_budget_goals ?? true
		app.config.globalProperties.$enableIncomes = res.data.enable_incomes ?? true
		app.config.globalProperties.$enableProjects = res.data.enable_projects ?? true
		app.config.globalProperties.$enableSharedProjects = res.data.enable_shared_projects ?? true
		app.config.globalProperties.$enableWorkspaces = res.data.enable_workspaces ?? false
		app.config.globalProperties.$showWorkspaceSwitcher = res.data.show_workspace_switcher ?? true
		app.config.globalProperties.$enableReceipts = res.data.enable_receipts ?? true
		app.config.globalProperties.$defaultStartPage = res.data.default_start_page || 'personal'
		app.config.globalProperties.$entriesPerPage = normalizeEntryPageSize(res.data.entries_per_page)
		app.config.globalProperties.$themeMode = applyThemeMode(res.data.theme_mode)

		router.beforeEach(to => {
			if (!app.config.globalProperties.$enableProjects && projectRouteNames.includes(to.name)) {
				return { name: 'personal' }
			}
			if (!app.config.globalProperties.$enableBudgetGoals && budgetRouteNames.includes(to.name)) {
				return { name: 'personal' }
			}
		})

		const hash = window.location.hash;
		if (isRootHash(hash)) {
			const defaultRoute = await resolveDefaultStartRoute(app.config.globalProperties.$defaultStartPage, app.config.globalProperties.$enableProjects)
			if (defaultRoute) {
				router.replace(defaultRoute)
			}
		}
	} catch (e) {
		app.config.globalProperties.$currency = ''
		app.config.globalProperties.$enableSubscriptions = true
		app.config.globalProperties.$enableFixedCosts = true
		app.config.globalProperties.$enableChildRelated = true
		app.config.globalProperties.$enableImportantPayments = true
		app.config.globalProperties.$enableReviewPayments = true
		app.config.globalProperties.$enableTaxRelevant = true
		app.config.globalProperties.$enableFuturePayments = true
		app.config.globalProperties.$enableTemplates = true
		app.config.globalProperties.$enableBudgetGoals = true
		app.config.globalProperties.$enableIncomes = true
		app.config.globalProperties.$enableProjects = true
		app.config.globalProperties.$enableSharedProjects = true
		app.config.globalProperties.$enableWorkspaces = false
		app.config.globalProperties.$showWorkspaceSwitcher = true
		app.config.globalProperties.$enableReceipts = true
		app.config.globalProperties.$defaultStartPage = 'personal'
		app.config.globalProperties.$entriesPerPage = 25
		app.config.globalProperties.$themeMode = applyThemeMode(normalizeThemeMode())
	}

	app.mount('#cobudget-app')
}

init()
