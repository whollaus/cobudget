<template>
	<NcContent
		app-name="cobudget"
		:class="{
			'entry-sidebar-is-open': entrySidebarOpen || reminderSidebarOpen,
		}">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem :name="$texts.labels.allPayments()" :active="activePaymentView === ''" @click.prevent="navigateTo('/')">
					<template #icon><CoBudgetIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem v-if="$enableIncomes" :name="$texts.labels.incomePayments()" :active="activePaymentView === 'income'" @click.prevent="navigateTo('/?filter=income')">
					<template #icon><PlusIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="$texts.dashboard.expenses()" :active="activePaymentView === 'expense'" @click.prevent="navigateTo('/?filter=expense')">
					<template #icon><MinusIcon :size="20" /></template>
				</NcAppNavigationItem>
				<NcAppNavigationItem v-if="$enableFuturePayments" :name="$texts.dashboard.planned()" :active="activePaymentView === 'future'" @click.prevent="navigateTo('/?filter=future')">
					<template #icon><CalendarSyncOutlineIcon :size="20" /></template>
				</NcAppNavigationItem>

				<NcAppNavigationItem v-if="$enableReminders" :name="$texts.reminders.sidebar()" :active="$route.name === 'reminders'" @click.prevent="navigateTo('/reminders')"><template #icon><BellOutlineIcon :size="20" /></template></NcAppNavigationItem>
                <NcAppNavigationSpacer v-if="$enableProjects" class="navigation-section-divider" role="separator" />
				<NcAppNavigationItem v-if="$enableProjects" :name="$texts.settings.areas()" :active="$route.name === 'projects'" :allow-collapse="activeProjects.length > 0"
					:open="true" @click.prevent="navigateTo('/projects')">
					<template #icon>
						<ViewGridOutlineIcon :size="20" />
					</template>
					<template v-if="activeProjects.length > 0" #default>
						<NcAppNavigationItem v-for="project in activeProjects" :key="project.id" :name="project.name"
							:active="isProjectItemActive(project)"
							@click.prevent="navigateTo('/projects/' + project.id)">
							<template #icon>
								<AreaIcon :size="20" :color="project.color" />
							</template>
							<template #counter>
								<div v-if="project.personal_balance && project.personal_balance !== 0"
									class="project-balance-dot"
									:class="project.personal_balance > 0 ? 'positive' : 'negative'"
									:title="formatBalance(project.personal_balance)">
								</div>
							</template>
						</NcAppNavigationItem>
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationSpacer v-if="$enableProjects && activeProjects.length > 0" class="navigation-section-divider" role="separator" />

				<NcAppNavigationItem v-if="$enableBudgetGoals" :name="$texts.budgetGoals.title()"
					:active="['budgets', 'budget-new', 'budget-edit'].includes($route.name)"
					@click.prevent="navigateTo('/budgets')">
					<template #icon>
						<WalletOutlineIcon :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem :name="$texts.annual.title()" :active="$route.name === 'annual-overview'"
					@click.prevent="navigateTo('/annual-overview')">
					<template #icon><ChartLineIcon :size="20" /></template>
				</NcAppNavigationItem>

				<div v-if="showWorkspaceSwitcher" class="workspace-switcher" style="margin-top: auto;">
					<NcPopover popover-base-class="cobudget-menu-popover" v-model:shown="showWorkspaceMenu" class="workspace-popover" placement="top-start" :auto-size="true" popup-role="dialog" @after-show="focusActiveWorkspace">
						<template #trigger>
							<NcButton class="workspace-current" variant="tertiary" alignment="start" wide :title="workspaceSwitcherLabel">
								<template #icon><SwapHorizontalIcon :size="20" /></template>
								<span class="workspace-trigger-label"><span>{{ workspaceSwitcherLabel }}</span><ChevronDownIcon :size="16" /></span>
							</NcButton>
						</template>
						<div ref="workspaceMenu" class="workspace-menu" role="dialog" :aria-label="$texts.settings.workspaces()">
							<NcButton v-for="w in visibleWorkspaces" :key="w.id" class="workspace-menu-item cobudget-native-button" wide alignment="start"
								:variant="w.id === activeWorkspaceId ? 'secondary' : 'tertiary'"
								:aria-current="w.id === activeWorkspaceId ? 'true' : undefined" :title="w.name"
								@click="switchWorkspace(w)">
								<template #icon><CheckIcon v-if="w.id === activeWorkspaceId" :size="20" /><span v-else class="workspace-icon-placeholder" /></template>
								<span class="workspace-option-label"><span>{{ w.name }}</span><span v-if="w.is_default" class="workspace-base-label">({{ $texts.workspaces.base() }})</span></span>
							</NcButton>
						</div>
					</NcPopover>
				</div>

				<NcAppNavigationItem :name="$texts.settings.settings()" :active="$route.name === 'settings'"
					@click.stop.prevent="navigateTo('/settings')" :style="showWorkspaceSwitcher ? '' : 'margin-top: auto'">
					<template #icon>
						<CogOutlineIcon :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent
			class="cobudget-main-content"
			:class="{
				'payment-table-viewport-content': ['personal', 'payments'].includes($route.name),
				'project-detail-viewport-content': ['project-detail', 'project-payments'].includes($route.name),
			}">
			<div
				class="content-wrapper"
				:class="{
					'content-wrapper--payment-table-viewport': ['personal', 'payments'].includes($route.name),
					'content-wrapper--project-detail-viewport': ['project-detail', 'project-payments'].includes($route.name),
				}">
				<router-view
					:key="['budget-new', 'budget-edit'].includes($route.name) ? $route.path : undefined"
					v-bind="entryViewProps"
					@open-project="openProject"
					@refresh-projects="fetchProjects"
					@open-entry-sidebar="openEntrySidebar" />
			</div>
		</NcAppContent>

		<nav class="mobile-bottom-navigation" :aria-label="$texts.mobileNavigation.label()">
			<router-link
				:to="{ name: 'personal' }"
				class="mobile-bottom-navigation__item"
				:class="{ 'mobile-bottom-navigation__item--active': mobileFinancesActive }"
				:aria-current="mobileFinancesActive ? 'page' : undefined"
				@click="closeMobileNavigation">
				<span class="mobile-bottom-navigation__icon" aria-hidden="true">
					<WalletOutlineIcon :size="24" />
				</span>
				<span class="mobile-bottom-navigation__label">{{ $texts.mobileNavigation.finances() }}</span>
			</router-link>
			<router-link
				v-if="$enableProjects"
				:to="{ name: 'projects' }"
				class="mobile-bottom-navigation__item"
				:class="{ 'mobile-bottom-navigation__item--active': mobileAreasActive }"
				:aria-current="mobileAreasActive ? 'page' : undefined"
				@click="closeMobileNavigation">
				<span class="mobile-bottom-navigation__icon" aria-hidden="true">
					<ViewGridOutlineIcon :size="24" />
				</span>
				<span class="mobile-bottom-navigation__label">{{ $texts.mobileNavigation.areas() }}</span>
			</router-link>
			<router-link
				:to="mobileReportsRoute"
				class="mobile-bottom-navigation__item"
				:class="{ 'mobile-bottom-navigation__item--active': mobileReportsActive }"
				:aria-current="mobileReportsActive ? 'page' : undefined"
				@click="closeMobileNavigation">
				<span class="mobile-bottom-navigation__icon" aria-hidden="true">
					<ChartLineIcon :size="24" />
				</span>
				<span class="mobile-bottom-navigation__label">{{ $texts.annual.title() }}</span>
			</router-link>
		</nav>

		<EntrySidebar
			v-if="entrySidebarReady"
			ref="globalEntrySidebar"
			@mode-changed="onEntrySidebarModeChanged"
			@saved="onEntrySaved"
			@closed="onEntrySidebarClosed" />
		<ReminderSidebar v-if="$enableReminders && reminderSidebarReady" ref="reminderSidebar" @vue:mounted="onReminderSidebarReady" @open-changed="onReminderSidebarChanged" />
		<TableFilters ref="globalTableFilters" style="display: none;" />
	</NcContent>
</template>

<script>
import { createShortcutHandler, PRINT_REPORT_EVENT } from './services/keyboardShortcuts'
import { computed, defineAsyncComponent } from 'vue'
import axios from './services/http'
import { showRequestError, showToast } from './services/notifications'
import { openPaymentLink } from './services/paymentLink'
import { REMOTE_DATA_CHANGED_EVENT, startDataSync } from './services/dataSync'
import { clearWorkspaceId, readWorkspaceId, writeWorkspaceId } from './services/workspaceStorage'
import { isReportView, reportLocation } from './services/reportNavigation'
import { shouldCloseEntrySidebarForRequest } from './utils/entrySidebarToggle'
import { sortAreasByName } from './utils/areaSorting'
import { hasPersonalEntryListRouteState, hasProjectEntryListRouteState } from './services/entryListRoute'
import { generateUrl } from '@nextcloud/router'
import { subscribe, unsubscribe, emit as emitNextcloudEvent } from '@nextcloud/event-bus'
import CoBudgetIcon from './components/CoBudgetIcon.vue'
import { titleNavigationLocation } from './services/titleNavigation'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationSpacer from '@nextcloud/vue/components/NcAppNavigationSpacer'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import WalletOutlineIcon from 'vue-material-design-icons/WalletOutline.vue'
import ViewGridOutlineIcon from 'vue-material-design-icons/ViewGridOutline.vue'
import AreaIcon from './components/AreaIcon.vue'
import CogOutlineIcon from 'vue-material-design-icons/CogOutline.vue'
import CalendarSyncOutlineIcon from 'vue-material-design-icons/CalendarSyncOutline.vue'
import { personalPaymentView } from './services/entryListRoute'
import MinusIcon from 'vue-material-design-icons/Minus.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import SwapHorizontalIcon from 'vue-material-design-icons/SwapHorizontal.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'

import BellOutlineIcon from 'vue-material-design-icons/BellOutline.vue'
const ReminderSidebar = defineAsyncComponent(() => import(/* webpackChunkName: "cobudget-reminder-sidebar" */ './components/ReminderSidebar.vue'))
const EntrySidebar = defineAsyncComponent(() => import(/* webpackChunkName: "cobudget-entry-sidebar" */ './components/AddEntryModal.vue'))

export default {
	name: 'App',
	components: {
		EntrySidebar, ReminderSidebar, BellOutlineIcon,
		CoBudgetIcon,
		NcContent,
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationSpacer,
		NcButton,
		NcPopover,
		CheckIcon,
		WalletOutlineIcon,
		ViewGridOutlineIcon,
		AreaIcon,
		CogOutlineIcon,
		CalendarSyncOutlineIcon,
		PlusIcon, MinusIcon,
		SwapHorizontalIcon,
		ChevronDownIcon,
		ChartLineIcon
	},
	provide() {
		return { appNavigation: computed(() => ({ open: this.navigationOpen, entries: this.titleNavigationEntries, areas: this.titleAreaEntries, years: this.navigationYears, navigate: this.navigateTitleMenu })) }
	},
	data() {
		return {
			shortcutOpening: false, reminderSidebarReady: false, reminderSidebarOpen: false, pendingReminder: null,
			navigationOpen: window.matchMedia('(min-width: 1024px)').matches,
			navigationYears: [],
			projects: [],
			workspaces: [],
			tagCounts: {
				income: 0,
				future: 0,
				important: 0,
				review: 0,
				fixedCosts: 0,
				childRelated: 0,
				subscriptions: 0,
				taxRelevant: 0,
				insurance: 0
			},
			activeWorkspaceId: readWorkspaceId(),
			showWorkspaceMenu: false,
			entrySidebarReady: false,
			entrySidebarWideMedia: null,
			entrySidebarOpen: false,
			entrySidebarCreatingNew: false,
			entrySidebarManuallyHidden: false,
			selectedEntryId: null,
			selectedReminderId: null
		}
	},
	computed: {
		titleAreaEntries() {
			return sortAreasByName(this.projects.filter(project => !project.is_archived || String(project.id) === String(this.$route.params.id)))
				.map(project => ({ id: `area:${project.id}`, areaId: project.id, kind: 'area', label: project.name, color: project.color, current: this.$route.name === 'project-detail' && String(this.$route.params.id) === String(project.id) }))
		},
		activePaymentView() {
			return ['personal', 'payments'].includes(this.$route.name) ? personalPaymentView(this.$route.query) : null
		},
		titleNavigationEntries() {
			const payment = (id, label, visible = true) => ({ id, label, visible, period: true, kind: 'payment', current: this.activePaymentView === id })
			return [
				payment('', this.$texts.labels.allPayments()),
				payment('income', this.$texts.labels.incomePayments(), this.$enableIncomes),
				payment('expense', this.$texts.dashboard.expenses()),
				payment('future', this.$texts.dashboard.planned(), this.$enableFuturePayments),
				{ id: 'reminders', visible: this.$enableReminders, label: this.$texts.reminders.sidebar(), current: this.$route.name === 'reminders' },
				{ id: 'projects', label: this.$texts.settings.areas(), visible: this.$enableProjects, current: this.$route.name === 'projects' },
				{ id: 'budgets', label: this.$texts.budgetGoals.title(), visible: this.$enableBudgetGoals, current: this.$route.name === 'budgets' },
				{ id: 'annual-overview', label: this.$texts.annual.title(), period: true, kind: 'report', current: this.$route.name === 'annual-overview' && this.$route.query.detailType === undefined },
				{ id: 'settings', label: this.$texts.settings.settings(), current: this.$route.name === 'settings' },
			].filter(item => item.visible !== false).map((item, index, entries) => ({
				...item,
				section: index > 0 && item.kind !== 'payment' && item.id !== 'reminders' && (entries[index - 1].kind === 'payment' || entries[index - 1].id === 'reminders'),
			}))
		},
		entryViewProps() {
			if (this.$route?.name === 'reminders') return { selectedReminderId: this.selectedReminderId };
			return ['personal', 'payments', 'project-detail', 'project-payments', 'annual-overview'].includes(this.$route?.name)
				? {
					selectedEntryId: this.selectedEntryId,
					...(['project-detail', 'project-payments'].includes(this.$route.name) ? { hideNewPaymentAction: this.entrySidebarCreatingNew } : {}),
					...(['personal', 'payments'].includes(this.$route.name) ? { navigationTagCounts: this.tagCounts } : {})
				}
				: {};
		},
		activeProjects() {
			return sortAreasByName(this.projects.filter(p => !p.is_archived));
		},
		activeWorkspaceName() {
			const ws = this.workspaces.find(w => w.id === this.activeWorkspaceId);
			return ws ? ws.name : 'Workspace';
		},
		visibleWorkspaces() {
			return this.workspaces.filter(workspace => !workspace.is_hidden);
		},
		workspaceSwitcherLabel() {
			const ws = this.visibleWorkspaces.find(workspace => workspace.id === this.activeWorkspaceId);
			return ws ? ws.name : 'Workspace wechseln';
		},
		showWorkspaceSwitcher() {
			return this.$enableWorkspaces && this.$showWorkspaceSwitcher !== false && this.visibleWorkspaces.length > 1;
		},
		mobileFinancesActive() {
			return ['personal', 'payments'].includes(this.$route?.name);
		},
		mobileAreasActive() {
			return ['projects', 'project-new', 'project-detail', 'project-payments', 'project-settings', 'project-settlements'].includes(this.$route?.name);
		},
		mobileReportsActive() {
			return isReportView(this.$route?.name);
		},
		mobileReportsRoute() {
			return reportLocation(this.$route);
		}
	},
	watch: {
		async $route(to, from) {
			this.showWorkspaceMenu = false;
			// Filters, sorting and pagination change the list, not the payment draft.
			if (from && to.path === from.path && (!to.query?.openPayment || (to.query.openPayment === from.query?.openPayment && to.query.workspace === from.query?.workspace))) return;
			await this.syncEntrySidebarForRoute();
		}
	},
	created() {
		subscribe('navigation-toggled', this.onNavigationToggled)
		startDataSync()
	},
	async mounted() {
		this.keyboardShortcutHandler = createShortcutHandler({
			root: () => this.$el,
			available: action => action === 'new' || (action === 'save' ? (!!this.$refs.globalEntrySidebar?.isOpen || this.reminderSidebarOpen) : ['annual-overview', 'personal', 'payments'].includes(this.$route.name)),
			blocked: action => this.shortcutOpening || !!this.$refs.reminderSidebar?.busy || !!this.$refs.globalEntrySidebar?.loading || !!this.$refs.globalEntrySidebar?.isInitializingEntry || (action === 'print' && this.entrySidebarOpen),
			run: action => this.runKeyboardShortcut(action),
		});
		window.addEventListener('cobudget-open-reminder', this.openReminderSidebar);
		window.addEventListener('cobudget-delete-reminder', this.deleteReminderFromList);
		window.addEventListener('cobudget-postpone-reminder', this.postponeReminderFromList);
		window.addEventListener('cobudget-reminder-choose-date', this.chooseReminderDateFromList);
		window.addEventListener('keydown', this.keyboardShortcutHandler);
		if (this.$enableProjects) {
			await this.fetchProjects()
		}
		this.fetchNavigationSummary()
		if (this.$enableWorkspaces) {
			await this.fetchWorkspaces()
		}
		window.addEventListener('open-add-modal', this.openEntrySidebar)
		window.addEventListener('open-entry-sidebar', this.openEntrySidebar)
		window.addEventListener('workspaces-updated', this.onWorkspacesUpdated)
		window.addEventListener('cobudget-data-changed', this.refreshNavigationData)
		window.addEventListener('cobudget-settings-changed', this.refreshNavigationData)
		window.addEventListener(REMOTE_DATA_CHANGED_EVENT, this.refreshNavigationData)
		if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
			this.entrySidebarWideMedia = window.matchMedia('(min-width: 1025px)')
			this.entrySidebarWideMedia.addEventListener?.('change', this.onEntrySidebarViewportChange)
		}
		await this.syncEntrySidebarForRoute()
	},
	beforeUnmount() {
		window.removeEventListener('cobudget-open-reminder', this.openReminderSidebar);
		window.removeEventListener('cobudget-delete-reminder', this.deleteReminderFromList);
		window.removeEventListener('cobudget-postpone-reminder', this.postponeReminderFromList);
		window.removeEventListener('cobudget-reminder-choose-date', this.chooseReminderDateFromList);
		window.removeEventListener('keydown', this.keyboardShortcutHandler);
		unsubscribe('navigation-toggled', this.onNavigationToggled)
		window.removeEventListener('open-add-modal', this.openEntrySidebar)
		window.removeEventListener('open-entry-sidebar', this.openEntrySidebar)
		window.removeEventListener('workspaces-updated', this.onWorkspacesUpdated)
		window.removeEventListener('cobudget-data-changed', this.refreshNavigationData)
		window.removeEventListener('cobudget-settings-changed', this.refreshNavigationData)
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.refreshNavigationData)
		this.entrySidebarWideMedia?.removeEventListener?.('change', this.onEntrySidebarViewportChange)
	},
	methods: {
        onReminderSidebarChanged(open, id = null) {
            this.reminderSidebarOpen = open;
            this.selectedReminderId = open ? id : null;
        },
        onReminderSidebarReady() {
            if (this.pendingReminder && this.$route.name === 'reminders') {
                this.performReminderAction(this.pendingReminder.row, this.pendingReminder.options);
                this.pendingReminder = null;
            }
        },
        performReminderAction(row, options) {
            return options.postponeAt !== undefined
                ? this.$refs.reminderSidebar.postpone(row, options.postponeAt)
                : this.$refs.reminderSidebar.open(row, options);
        },
        postponeReminderFromList(event) { return this.openReminderSidebar({ detail: event.detail.row }, { postponeAt: event.detail.remindAt }); },
        chooseReminderDateFromList(event) { return this.openReminderSidebar(event, { focusDate: true }); },
        deleteReminderFromList(event) { return this.openReminderSidebar(event, { confirmDelete: true }); },
        async openReminderSidebar(event = null, options = {}) {
            if (this.$enableReminders === false) return;
            const row = event?.detail || null;
            if (this.$refs.reminderSidebar) return this.performReminderAction(row, options);
            this.pendingReminder = { row, options };
            this.reminderSidebarReady = true;
        },
        async runKeyboardShortcut(action) {
            if (action === 'save' && this.reminderSidebarOpen) { await this.$refs.reminderSidebar.save(); return; }
            if (action === 'new' && this.$route?.name === 'reminders') { await this.openReminderSidebar(); return; }
			if (action === 'save') {
				const sidebar = this.$refs.globalEntrySidebar;
				if (sidebar?.isOpen && !sidebar.isSaveDisabled) await sidebar.saveEntry();
			} else if (action === 'print') {
				window.dispatchEvent(new CustomEvent(PRINT_REPORT_EVENT));
			} else {
				this.shortcutOpening = true;
				try { await this.openEntrySidebar({ forceNew: true }); }
				finally { this.shortcutOpening = false; }
			}
		},
		onNavigationToggled({ open }) { this.navigationOpen = open },
		navigateTitleMenu(item, selection) {
			if (item.current && !selection) return
			this.navigateTo(titleNavigationLocation(item, selection))
		},
		normalizeTagCounts(payload) {
			const counts = payload && typeof payload === 'object' ? payload : {};
			return {
				income: parseInt(counts.income || 0, 10),
				future: parseInt(counts.future || 0, 10),
				important: parseInt(counts.important || 0, 10),
				review: parseInt(counts.review || 0, 10),
				fixedCosts: parseInt(counts.fixedCosts || 0, 10),
				childRelated: parseInt(counts.childRelated || 0, 10),
				subscriptions: parseInt(counts.subscriptions || 0, 10),
				taxRelevant: parseInt(counts.taxRelevant || 0, 10),
				insurance: parseInt(counts.insurance || 0, 10)
			};
		},
		async fetchNavigationSummary() {
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/dashboard'), {
					params: {
						limit: 1,
						offset: 0,
						summaryOnly: true
					}
				});
				this.tagCounts = this.normalizeTagCounts(response.data?.tagCounts || {});
				this.navigationYears = Array.isArray(response.data?.years) ? response.data.years : [];
			} catch (error) {
				console.error('Error fetching navigation summary:', error);
			}
		},
		refreshNavigationData() {
			this.fetchNavigationSummary();
			this.fetchProjects();
		},
		async fetchProjects() {
			if (!this.$enableProjects) {
				this.projects = [];
				return;
			}
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/projects'));
				this.projects = response.data;
			} catch (error) {
				console.error('Error fetching projects:', error);
			}
		},
		async fetchWorkspaces() {
			try {
				const res = await axios.get(generateUrl('/apps/cobudget/api/workspaces'), {
					headers: { Accept: 'application/json' },
					params: { _t: Date.now() },
					skipWorkspaceHeader: true
				});
				this.setWorkspaces(res.data || []);
			} catch (e) {
				console.error('Error fetching workspaces:', e);
			}
		},
		onWorkspacesUpdated(event) {
			this.setWorkspaces(event.detail || []);
		},
		setWorkspaces(workspaces) {
			this.workspaces = this.normalizeWorkspaces(workspaces);
			const visibleWorkspaces = this.visibleWorkspaces;
			if (visibleWorkspaces.length <= 1) {
				this.showWorkspaceMenu = false;
			}

			if (this.workspaces.length === 0) {
				this.activeWorkspaceId = null;
				clearWorkspaceId();
				return;
			}

			const activeStillExists = this.workspaces.some(w => w.id === this.activeWorkspaceId);
			if (!this.activeWorkspaceId || !activeStillExists) {
				const defaultWs = this.workspaces.find(w => w.is_default) || this.workspaces[0];
				this.activeWorkspaceId = defaultWs.id;
				writeWorkspaceId(defaultWs.id);
			}
		},
		normalizeWorkspaces(payload) {
			if (Array.isArray(payload)) {
				return payload.map(this.normalizeWorkspace).filter(Boolean);
			}
			if (payload && typeof payload === 'object') {
				if (Array.isArray(payload.workspaces)) {
					return payload.workspaces.map(this.normalizeWorkspace).filter(Boolean);
				}
				return Object.values(payload).map(this.normalizeWorkspace).filter(Boolean);
			}
			return [];
		},
		normalizeWorkspace(workspace) {
			if (!workspace || typeof workspace !== 'object' || workspace.error) {
				return null;
			}
			if (workspace.id === undefined || workspace.id === null || !workspace.name) {
				return null;
			}
			return {
				...workspace,
				id: parseInt(workspace.id, 10),
				is_default: workspace.is_default === true || workspace.is_default === 1 || workspace.is_default === '1',
				is_hidden: workspace.is_hidden === true || workspace.is_hidden === 1 || workspace.is_hidden === '1' || workspace.is_hidden === 'true',
				created_at: parseInt(workspace.created_at || 0, 10)
			};
		},
		switchWorkspace(w) {
			this.showWorkspaceMenu = false;
			if (!w || w.id === this.activeWorkspaceId) return;
			this.activeWorkspaceId = w.id;
			writeWorkspaceId(w.id);
			this.showWorkspaceMenu = false;
			// Reload the whole page to refetch all data with the new workspace
			window.location.hash = '#/';
			window.location.reload();
		},
		focusActiveWorkspace() {
			this.$refs.workspaceMenu?.querySelector('[aria-current="true"]')?.focus({ preventScroll: true });
		},
		async waitForEntrySidebarRef() {
			for (let i = 0; i < 30; i++) {
				await this.$nextTick();
				if (this.$refs.globalEntrySidebar && typeof this.$refs.globalEntrySidebar.openSidebar === 'function') {
					return this.$refs.globalEntrySidebar;
				}
				await new Promise(resolve => window.setTimeout(resolve, 20));
			}

			return null;
		},
		async openEntrySidebar(eventOrPayload = {}) {
			const eventDetail = eventOrPayload?.detail;
			const payload = eventDetail && typeof eventDetail === 'object'
				? eventDetail
				: eventOrPayload && typeof eventOrPayload === 'object' && !('target' in eventOrPayload)
					? eventOrPayload
					: {};
			const routeProjectId = this.routeProjectId();
			const projectId = payload.projectId ?? routeProjectId;
			const entryToEdit = payload.entry
				? { ...payload.entry, date: payload.entry.original_date ?? payload.entry.date }
				: null;
			const entryToDuplicate = payload.entryToDuplicate || null;
			const editingFuture = payload.isEditingFuture || payload.isFuture || false;
			const defaultType = this.sanitizeEntryType(payload.defaultType || this.routeDefaultEntryType());
			const preferAreaDropdown = this.$route?.name === 'project-detail' && projectId !== null;
			const requestedSelectedEntryId = entryToEdit
				? (payload.selectedEntryId ?? entryToEdit.id ?? null)
				: null;

			this.entrySidebarReady = true;
			const sidebar = await this.waitForEntrySidebarRef();
			if (!sidebar) {
				this.selectedEntryId = null;
				return;
			}

			const shouldCloseSidebar = !payload.forceNew && this.shouldCloseEntrySidebar(sidebar, {
				entryToEdit,
				entryToDuplicate,
				requestedSelectedEntryId,
				editingFuture,
				defaultType
			});
			if (sidebar.isOpen && !(await sidebar.confirmDiscardChanges())) {
				return;
			}

			if (shouldCloseSidebar) {
				sidebar.closeSidebar();
				return;
			}

			this.entrySidebarManuallyHidden = false;
			this.entrySidebarOpen = true;
			this.entrySidebarCreatingNew = !entryToEdit && !entryToDuplicate;
			this.selectedEntryId = requestedSelectedEntryId;
			await sidebar.openSidebar(
				entryToEdit,
				projectId,
				editingFuture,
				entryToDuplicate,
				defaultType,
				preferAreaDropdown,
				false,
				requestedSelectedEntryId
			);
		},
		shouldCloseEntrySidebar(sidebar, {
			entryToEdit,
			entryToDuplicate,
			requestedSelectedEntryId,
			editingFuture,
			defaultType
		}) {
			return shouldCloseEntrySidebarForRequest({
				sidebar,
				entryToEdit,
				entryToDuplicate,
				requestedSelectedEntryId,
				selectedEntryId: this.selectedEntryId,
				editingFuture,
				defaultType
			});
		},
		routeProjectId() {
			if (!['project-detail', 'project-payments'].includes(this.$route?.name)) {
				return null;
			}

			const id = Number(this.$route.params?.id || 0);
			return Number.isInteger(id) && id > 0 ? id : null;
		},
		isWideEntrySidebarViewport() {
			if (this.entrySidebarWideMedia) {
				return this.entrySidebarWideMedia.matches;
			}
			return typeof window !== 'undefined'
				&& typeof window.matchMedia === 'function'
				&& window.matchMedia('(min-width: 1025px)').matches;
		},
		routeSupportsOpenEntrySidebar() {
			if (this.$route?.name === 'personal') {
				return !hasPersonalEntryListRouteState(this.$route.query);
			}
			if (this.$route?.name === 'project-payments') {
				return false;
			}

			const projectId = this.routeProjectId();
			if (!projectId) {
				return false;
			}

			const project = this.projects.find(item => Number(item.id) === projectId);
			const isArchived = [true, 1, '1', 'true'].includes(project?.is_archived) || project?.status === 'archived';
			return !!project && !isArchived && !hasProjectEntryListRouteState(this.$route.query);
		},
		async syncEntrySidebarForRoute() {
			const sidebar = this.$refs.globalEntrySidebar;
			if (sidebar?.isOpen) {
				sidebar.closeSidebar({ userInitiated: false });
			}
			this.entrySidebarOpen = false;
			this.entrySidebarCreatingNew = false;
			this.selectedEntryId = null;

			if (this.$route.query.openPayment) {
				const route = this.$route;
				await openPaymentLink(route.query, {
					current: () => this.$route === route,
					activeWorkspace: readWorkspaceId,
					workspaces: async () => (await axios.get(generateUrl('/apps/cobudget/api/workspaces'), { skipWorkspaceHeader: true })).data,
					switchWorkspace: id => { writeWorkspaceId(id); window.location.reload(); },
					getEntry: async id => (await axios.get(generateUrl(`/apps/cobudget/api/entries/${id}`))).data.entry,
					open: entry => this.openEntrySidebar({ entry, projectId: entry.project_id || null }),
					settled: () => showToast(this.$texts.areaDetail.entrySettledInfo(), 'info'),
					error: error => showRequestError(error, this.$texts.dashboard.paymentsLoadError(), 'Failed to open linked payment'),
				});
				return;
			}

			if (this.entrySidebarManuallyHidden || !this.isWideEntrySidebarViewport() || !this.routeSupportsOpenEntrySidebar()) {
				return;
			}

			await this.openEntrySidebar({
				projectId: this.routeProjectId(),
				isFuture: false,
				defaultType: this.routeDefaultEntryType()
			});
		},
		async onEntrySidebarViewportChange(event) {
			if (!event.matches) {
				this.$refs.globalEntrySidebar?.closeSidebar({ userInitiated: false });
				this.entrySidebarOpen = false;
				this.entrySidebarCreatingNew = false;
				this.selectedEntryId = null;
				return;
			}

			await this.syncEntrySidebarForRoute();
		},
		onEntrySidebarClosed({ userInitiated = false } = {}) {
			this.entrySidebarOpen = false;
			this.entrySidebarCreatingNew = false;
			this.selectedEntryId = null;
			if (userInitiated && this.isWideEntrySidebarViewport()) {
				this.entrySidebarManuallyHidden = true;
			}
		},
		onEntrySidebarModeChanged({ isOpen = false, isCreatingNewEntry = false } = {}) {
			this.entrySidebarCreatingNew = Boolean(isOpen && isCreatingNewEntry);
		},
		routeDefaultEntryType() {
			return this.$enableIncomes && ['personal', 'payments'].includes(this.$route?.name) && this.$route?.query?.filter === 'income'
				? 'income'
				: 'expense';
		},
		sanitizeEntryType(type) {
			return this.$enableIncomes && type === 'income' ? 'income' : 'expense';
		},
		navigateTo(path) {
			this.$router.push(path);
			this.closeMobileNavigation();
		},
		closeMobileNavigation() {
			if (typeof window === 'undefined' || !window.matchMedia('(max-width: 768px)').matches) {
				return;
			}

			emitNextcloudEvent('toggle-navigation', { open: false });
		},
		isProjectItemActive(project) {
			return ['project-detail', 'project-payments', 'project-settings', 'project-settlements'].includes(this.$route.name)
				&& String(this.$route.params.id) === String(project.id);
		},
		onEntrySaved(payload = {}) {
			// Broadcast a global event so the current view knows to refresh
			if (payload.action === 'created' || payload.action === 'deleted') {
				this.selectedEntryId = null;
			}
			this.refreshNavigationData();
			window.dispatchEvent(new CustomEvent('entry-saved', { detail: payload }));
		},
		openProject(id) {
			if (!this.$enableProjects) {
				this.$router.push({ name: 'personal' });
				return;
			}
			if (id) {
				this.$router.push({ name: 'project-detail', params: { id } });
			} else {
				this.$router.push({ name: 'projects' });
			}
		},
		formatBalance(amount) {
			return this.$formatSignedMoney(parseFloat(amount || 0), this.$currency || 'EUR', {
				signDisplay: amount > 0 ? 'always' : 'auto',
			});
		}
	}
}
</script>

<style scoped>

.navigation-section-divider {
	position: relative;
}

.navigation-section-divider::after {
	content: '';
	position: absolute;
	inset-inline: var(--default-clickable-area, 44px) var(--default-grid-baseline, 4px);
	inset-block-start: 50%;
	border-block-start: 1px solid var(--color-border-dark);
}


:global(#content.app-cobudget #app-content),
:global(#content.app-cobudget .app-content-vue),
:global(#content.app-cobudget .app-content) {
	background: var(--cobudget-page-background);
}

.content-wrapper {
	--cobudget-content-inset-start: 15px;
	--cobudget-content-inset-end: 10px;
	max-width: 100%;
	margin: 0;
	padding: 5px var(--cobudget-content-inset-end) 10px var(--cobudget-content-inset-start);
	background: transparent;
	border-radius: 0;
	box-shadow: none;
	min-height: 80vh;
}

.mobile-bottom-navigation {
	display: none;
}

@media (min-width: 1025px) {
	:global(#content.app-cobudget) {
		--cobudget-shell-gap: calc(var(--default-grid-baseline, 4px) * 3);
		--cobudget-shell-radius: calc(var(--default-grid-baseline, 4px) * 4);
		--cobudget-content-inline-padding: calc(var(--default-grid-baseline, 4px) * 4);
	}

	:global(#content.app-cobudget .app-content.cobudget-main-content) {
		box-sizing: border-box;
		padding: 0;
		/* Paint the native scrollbar channel as part of the work surface. */
		background: var(--cobudget-page-background, var(--color-main-background));
		border-radius: var(--cobudget-shell-radius);
	}

	:global(#content.app-cobudget .content.entry-sidebar-is-open .app-content.cobudget-main-content) {
		padding-inline-end: var(--cobudget-shell-gap);
		background: transparent;
		border-radius: 0;
	}

	:global(#content.app-cobudget .app-navigation__content > ul) {
		padding-block-start: var(--cobudget-shell-gap);
	}

	.content-wrapper {
		--cobudget-content-inset-start: var(--cobudget-content-inline-padding);
		--cobudget-content-inset-end: var(--cobudget-content-inline-padding);
		box-sizing: border-box;
		min-height: 100%;
		padding-inline: var(--cobudget-content-inline-padding);
		background: var(--cobudget-page-background, var(--color-main-background));
		border-radius: var(--cobudget-shell-radius);
	}
}

@media (min-width: 1024px) {
	:global(#content.app-cobudget .content:not(.content--legacy) .app-navigation:not(.app-navigation--closed):not(.app-navigation--close) ~ .app-content.cobudget-main-content) {
		border-inline-start: 0 !important;
	}
}

@media (min-width: 769px) {
	:global(#content.app-cobudget .app-content.payment-table-viewport-content),
	:global(#content.app-cobudget .app-content.project-detail-viewport-content) {
		overflow: hidden;
	}

	.content-wrapper--payment-table-viewport,
	.content-wrapper--project-detail-viewport {
		box-sizing: border-box;
		height: 100%;
		min-height: 0;
		overflow: hidden;
	}
}

.mobile-sidebar-overlay {
	display: none;
}

.new-btn-wrapper {
	padding: 0 0 12px 0;
	box-sizing: border-box;
	width: 100%;
}

.mobile-header {
	display: none;
}

.app-navigation-new {
	padding: 0 !important;
}

:global(#content.app-cobudget #app-navigation),
:global(#content.app-cobudget #app-navigation-vue),
:global(#content.app-cobudget .app-navigation),
:global(#content.app-cobudget .app-navigation__body),
:global(#content.app-cobudget .app-navigation__content),
:global(#content.app-cobudget .app-navigation__content > ul),
:global(#content.app-cobudget .app-navigation-list),
:global(#content.app-cobudget .app-navigation__list),
:global(#content.app-cobudget .app-navigation__list-item),
:global(#content.app-cobudget .app-navigation-entry-wrapper),
:global(#content.app-cobudget .app-navigation-entry__children) {
	background-color: var(--cobudget-navigation-background, transparent) !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
	opacity: 1 !important;
	backdrop-filter: none !important;
}

@media (max-width: 768px) {
	:global(#content.app-cobudget) {
		--cobudget-mobile-content-padding: calc(var(--default-grid-baseline, 4px) * 2.5);
		--cobudget-mobile-bottom-navigation-height: calc(64px + env(safe-area-inset-bottom, 0px));
		--cobudget-mobile-fab-size: var(--cobudget-mobile-touch-size, 44px);
		--cobudget-mobile-fab-inline-offset: max(calc(var(--default-grid-baseline, 4px) * 4), env(safe-area-inset-right, 0px));
		--cobudget-mobile-fab-pagination-lift: calc(var(--default-grid-baseline, 4px) * 10);
		--cobudget-mobile-fab-pagination-lane: calc(var(--cobudget-mobile-fab-size) + var(--default-grid-baseline, 4px) * 3);
	}

	.content-wrapper {
		padding:
			var(--cobudget-mobile-content-padding)
			var(--cobudget-mobile-content-padding)
			calc(var(--cobudget-mobile-content-padding) + var(--cobudget-mobile-bottom-navigation-height));
	}

	.mobile-bottom-navigation {
		position: absolute;
		z-index: 1300;
		inset-inline: 0;
		inset-block-end: 0;
		display: grid;
		grid-auto-flow: column;
		grid-auto-columns: minmax(0, 1fr);
		align-items: stretch;
		height: var(--cobudget-mobile-bottom-navigation-height);
		padding-block-start: var(--default-grid-baseline, 4px);
		padding-block-end: calc(var(--default-grid-baseline, 4px) + env(safe-area-inset-bottom, 0px));
		padding-inline-start: max(calc(var(--default-grid-baseline, 4px) * 2), env(safe-area-inset-left, 0px));
		padding-inline-end: max(calc(var(--default-grid-baseline, 4px) * 2), env(safe-area-inset-right, 0px));
		border-block-start: 1px solid var(--cobudget-border, var(--color-border));
		background-color: var(--cobudget-surface, var(--color-main-background));
		box-shadow: var(--box-shadow-header, 0 -2px 8px rgba(0, 0, 0, 0.12));
		isolation: isolate;
	}

	.mobile-bottom-navigation__item,
	.mobile-bottom-navigation__item:visited {
		display: flex;
		min-width: 0;
		min-height: var(--default-clickable-area, 44px);
		padding: var(--default-grid-baseline, 4px);
		align-items: center;
		justify-content: center;
		flex-direction: column;
		gap: 1px;
		border-radius: var(--border-radius-element, var(--border-radius));
		color: var(--cobudget-text-muted, var(--color-text-maxcontrast));
		font-size: var(--cobudget-font-sm, 12px);
		line-height: 1.15;
		text-align: center;
		text-decoration: none !important;
		touch-action: manipulation;
		-webkit-tap-highlight-color: transparent;
	}

	.mobile-bottom-navigation__icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: calc(var(--default-grid-baseline, 4px) * 12);
		height: calc(var(--default-grid-baseline, 4px) * 7.5);
		padding-inline: calc(var(--default-grid-baseline, 4px) * 3);
		border-radius: var(--border-radius-pill, 999px);
		transition: background-color 0.15s ease, color 0.15s ease;
	}

	.mobile-bottom-navigation__item:focus-visible {
		outline: 2px solid var(--color-primary-element, var(--cobudget-primary));
		outline-offset: -2px;
		background-color: var(--cobudget-navigation-hover-background, var(--color-background-hover));
	}

	.mobile-bottom-navigation__item:active {
		background-color: var(--cobudget-navigation-hover-background, var(--color-background-hover));
	}

	.mobile-bottom-navigation__item--active,
	.mobile-bottom-navigation__item--active:visited {
		color: var(--color-primary-element, var(--cobudget-primary));
		font-weight: var(--cobudget-font-weight-action, 700);
	}

	.mobile-bottom-navigation__item--active .mobile-bottom-navigation__icon {
		background-color: var(--cobudget-navigation-active-background, var(--color-primary-element-light, var(--color-background-hover)));
	}

	.mobile-bottom-navigation__label {
		max-width: 100%;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	:global(#content.app-cobudget #app-navigation),
	:global(#content.app-cobudget #app-navigation-vue),
	:global(#content.app-cobudget .app-navigation),
	:global(#content.app-cobudget .app-navigation__body),
	:global(#content.app-cobudget .app-navigation__content),
	:global(#content.app-cobudget .app-navigation__content > ul),
	:global(#content.app-cobudget .app-navigation-list),
	:global(#content.app-cobudget .app-navigation__list),
	:global(#content.app-cobudget .app-navigation__list-item),
	:global(#content.app-cobudget .app-navigation-entry-wrapper),
	:global(#content.app-cobudget .app-navigation-entry__children) {
		background-color: var(--cobudget-mobile-navigation-background, var(--cobudget-navigation-background, transparent)) !important;
		opacity: 1 !important;
		backdrop-filter: none !important;
	}

}

@media (max-width: 768px) and (hover: hover) and (pointer: fine) {
	.mobile-bottom-navigation__item:hover {
		background-color: var(--cobudget-navigation-hover-background, var(--color-background-hover));
	}
}

@media print {
	.mobile-bottom-navigation {
		display: none !important;
	}
}

@media (max-width: 768px) and (prefers-color-scheme: dark) {
	.app-navigation {
		background-color: var(--cobudget-page-background, #181818) !important;
	}

	.mobile-header {
		background-color: var(--cobudget-page-background, #181818) !important;
	}
}

.project-color-dot {
	width: 12px;
	height: 12px;
	border-radius: 50%;
	display: inline-block;
	box-shadow: 0 0 0 1px var(--cobudget-page-background, #fff);
}

.project-balance-dot {
	width: 12px;
	height: 12px;
	border-radius: 50%;
	display: inline-block;
	box-shadow: 0 0 0 1px var(--cobudget-page-background, #fff);
}

.project-balance-dot.positive {
	background-color: #10b981;
}

.project-balance-dot.negative {
	background-color: var(--cobudget-error);
}

</style>

<style>
/* Globale Styles für den einheitlichen Header */
.v-popper__arrow-container {
	display: none !important;
}

#content.app-cobudget .app-navigation-toggle {
	position: absolute !important;
	top: -1px !important;
	left: -35px !important;
	z-index: 100 !important;
	margin: 0 !important;
}

/* NcAppNavigation has no icon slot: retain its native button and state handling,
 * and replace only the glyph with the DockLeft symbol used by Files. */
#content.app-cobudget .app-navigation-toggle .icon-vue {
	background-color: currentColor;
	mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M20 4H4A2 2 0 0 0 2 6V18A2 2 0 0 0 4 20H20A2 2 0 0 0 22 18V6A2 2 0 0 0 20 4M20 18H9V6H20Z'/%3E%3C/svg%3E") center / 20px 20px no-repeat;
}

#content.app-cobudget .app-navigation-toggle .icon-vue svg {
	visibility: hidden;
}

@media (min-width: 1025px) {
	#content.app-cobudget .app-navigation-toggle-wrapper {
		inset-inline-end: calc(0px - var(--app-navigation-padding));
	}

	#content.app-cobudget .app-navigation-toggle {
		position: static !important;
	}
}

@media (max-width: 768px) {
	#content.app-cobudget .app-navigation-toggle {
		margin-top: 5px !important;
	}
}

.toastify.dialogs.toastify-top.toastify-right {
	left: auto !important;
	right: 10px !important;
	max-width: min(420px, calc(100vw - 32px));
	margin-top: 0;
	transform: none !important;
}



/* Workspace Switcher */
.workspace-switcher {
	position: relative;
	box-sizing: border-box;
	padding: 8px 0;
	width: 100%;
	border-top: 1px solid var(--cobudget-border, #ddd);
}
.workspace-popover { width: 100%; }
.workspace-current .button-vue__text { flex: 1; }
.workspace-trigger-label,
.workspace-option-label {
	display: flex;
	text-align: start;
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	min-width: 0;
}
.workspace-trigger-label > span:first-child,
.workspace-option-label > span:first-child {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.workspace-trigger-label > .material-design-icon,
.workspace-base-label { flex-shrink: 0; }
.workspace-base-label {
	font-size: var(--cobudget-font-compact);
	color: var(--color-text-maxcontrast);
	font-weight: var(--font-weight-default, normal);
}
.workspace-menu {
	display: grid;
	gap: var(--default-grid-baseline, 4px);
	box-sizing: border-box;
	padding: calc(var(--default-grid-baseline, 4px) * 2);
	max-height: min(60vh, 400px);
	overflow-y: auto;
}
.workspace-icon-placeholder { width: 20px; height: 20px; }
</style>
