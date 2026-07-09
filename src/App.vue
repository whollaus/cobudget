<template>
	<NcContent app-name="cobudget">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem :name="$texts.settings.myFinances()" :active="$route.name === 'personal' && !$route.query.filter"
					@click.prevent="navigateTo('/')" :allow-collapse="true" :open="true">
					<template #icon>
						<WalletIcon :size="20" />
					</template>
					<template #default>
						<NcAppNavigationItem v-if="hasIncomes" :name="$texts.labels.incomePayments()" :active="$route.name === 'personal' && $route.query.filter === 'income'"
							@click.prevent="navigateTo('/?filter=income')">
							<template #icon>
								<TrendingUpIcon :size="20" />
							</template>
						</NcAppNavigationItem>
						<NcAppNavigationItem :name="$texts.settings.currentYear()" :active="$route.name === 'personal' && $route.query.filter === 'currentYear'"
							@click.prevent="navigateTo('/?filter=currentYear')">
							<template #icon>
								<CalendarIcon :size="20" />
							</template>
						</NcAppNavigationItem>
						<NcAppNavigationItem v-if="hasFuturePayments" :name="$texts.filters.futurePayments()" :active="$route.name === 'personal' && $route.query.filter === 'future'"
							@click.prevent="navigateTo('/?filter=future')">
							<template #icon>
								<CalendarSyncIcon :size="20" />
							</template>
						</NcAppNavigationItem>
						<NcAppNavigationItem v-if="hasImportantPayments" :name="$texts.labels.importantPayments()" :active="$route.name === 'personal' && $route.query.filter === 'important'"
							@click.prevent="navigateTo('/?filter=important')">
							<template #icon>
								<StarIcon :size="20" />
							</template>
						</NcAppNavigationItem>
						<NcAppNavigationItem v-if="hasReviewPayments" :name="$texts.labels.reviewPayments()" :active="$route.name === 'personal' && $route.query.filter === 'review'"
							@click.prevent="navigateTo('/?filter=review')">
							<template #icon>
								<ClipboardCheckIcon :size="20" />
							</template>
							<template #counter>
								<div class="project-balance-dot negative review-alert-dot" :aria-label="$texts.labels.reviewPayments()"></div>
							</template>
						</NcAppNavigationItem>
						<NcAppNavigationItem v-if="hasFixedCosts" :name="$texts.labels.fixedCostPayments()" :active="$route.name === 'personal' && $route.query.filter === 'fixedCost'"
							@click.prevent="navigateTo('/?filter=fixedCost')">
							<template #icon>
								<BankIcon :size="20" />
							</template>
						</NcAppNavigationItem>
						<NcAppNavigationItem v-if="hasChildRelatedPayments" :name="$texts.labels.childPayments()" :active="$route.name === 'personal' && $route.query.filter === 'childRelated'"
							@click.prevent="navigateTo('/?filter=childRelated')">
							<template #icon>
								<AccountChildIcon :size="20" />
							</template>
						</NcAppNavigationItem>
						<NcAppNavigationItem v-if="hasSubscriptions" :name="$texts.labels.subscriptionPayments()" :active="$route.name === 'personal' && $route.query.filter === 'subscription'"
							@click.prevent="navigateTo('/?filter=subscription')">
							<template #icon>
								<SyncIcon :size="20" />
							</template>
						</NcAppNavigationItem>
						<NcAppNavigationItem v-if="hasTaxRelevantPayments" :name="$texts.labels.taxRelevantPayments()" :active="$route.name === 'personal' && $route.query.filter === 'taxRelevant'"
							@click.prevent="navigateTo('/?filter=taxRelevant')">
							<template #icon>
								<ReceiptTextCheckOutlineIcon :size="20" />
							</template>
						</NcAppNavigationItem>
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem v-if="$enableProjects" :name="$texts.settings.areas()" :active="$route.name === 'projects'" :allow-collapse="true"
					:open="true" @click.prevent="navigateTo('/projects')">
					<template #icon>
						<ViewGridIcon :size="20" />
					</template>
					<template #default>
						<NcAppNavigationItem v-for="project in activeProjects" :key="project.id" :name="project.name"
							:active="isProjectItemActive(project)"
							@click.prevent="navigateTo('/projects/' + project.id)">
							<template #icon>
								<FolderIcon :size="20" :fillColor="project.color || 'currentColor'" />
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

				<NcAppNavigationItem v-if="$enableBudgetGoals" :name="$texts.budgetGoals.title()"
					:active="['budgets', 'budget-new', 'budget-edit'].includes($route.name)"
					@click.prevent="navigateTo('/budgets')">
					<template #icon>
						<WalletIcon :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem :name="$texts.analytics.title()" :active="$route.name === 'analytics'"
					@click.prevent="navigateTo('/analytics')">
					<template #icon>
						<ChartLineIcon :size="20" />
					</template>
				</NcAppNavigationItem>

				<div v-if="showWorkspaceSwitcher" class="workspace-switcher" style="margin-top: auto;">
					<div class="workspace-current" @click.stop="showWorkspaceMenu = !showWorkspaceMenu">
						<SwapHorizontalIcon :size="20" />
						<span class="workspace-label">{{ workspaceSwitcherLabel }}</span>
						<ChevronDownIcon :size="16" />
					</div>
					<ul v-if="showWorkspaceMenu" class="workspace-menu">
						<li v-for="w in visibleWorkspaces" :key="w.id"
							:class="{ active: w.id === activeWorkspaceId }"
							@click="switchWorkspace(w)">
							{{ w.name }}
							<span v-if="w.is_default" style="font-size: var(--cobudget-font-xxs); opacity: 0.6;">({{ $texts.workspaces.base() }})</span>
						</li>
					</ul>
				</div>

				<NcAppNavigationItem :name="$texts.settings.settings()" :active="$route.name === 'settings'"
					@click.stop.prevent="navigateTo('/settings')" :style="showWorkspaceSwitcher ? '' : 'margin-top: auto'">
					<template #icon>
						<CogIcon :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent>
			<div class="content-wrapper">
				<router-view @open-project="openProject" @refresh-projects="fetchProjects"
					@open-add-modal="openAddModal" />
			</div>
		</NcAppContent>

		<AddEntryModal v-if="addModalReady" ref="globalAddModal" :projects="projects" @saved="onEntrySaved" />
		<TableFilters ref="globalTableFilters" style="display: none;" />
	</NcContent>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import axios from './services/http'
import { clearWorkspaceId, readWorkspaceId, writeWorkspaceId } from './services/workspaceStorage'
import { generateUrl } from '@nextcloud/router'
import { emit as emitNextcloudEvent } from '@nextcloud/event-bus'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import WalletIcon from 'vue-material-design-icons/Wallet.vue'
import ViewGridIcon from 'vue-material-design-icons/ViewGrid.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import SyncIcon from 'vue-material-design-icons/Sync.vue'
import BankIcon from 'vue-material-design-icons/Bank.vue'
import AccountChildIcon from 'vue-material-design-icons/AccountChild.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import ClipboardCheckIcon from 'vue-material-design-icons/ClipboardCheck.vue'
import ReceiptTextCheckOutlineIcon from 'vue-material-design-icons/ReceiptTextCheckOutline.vue'
import CalendarSyncIcon from 'vue-material-design-icons/CalendarSync.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import SwapHorizontalIcon from 'vue-material-design-icons/SwapHorizontal.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChartLineIcon from 'vue-material-design-icons/ChartLine.vue'

const AddEntryModal = defineAsyncComponent(() => import(/* webpackChunkName: "cobudget-add-entry-modal" */ './components/AddEntryModal.vue'))

export default {
	name: 'App',
	components: {
		AddEntryModal,
		NcContent,
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationItem,
		WalletIcon,
		ViewGridIcon,
		FolderIcon,
		CogIcon,
		SyncIcon,
		BankIcon,
		AccountChildIcon,
		StarIcon,
		ClipboardCheckIcon,
		ReceiptTextCheckOutlineIcon,
		CalendarSyncIcon,
		CalendarIcon,
		TrendingUpIcon,
		SwapHorizontalIcon,
		ChevronDownIcon,
		ChartLineIcon
	},
	data() {
		return {
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
				taxRelevant: 0
			},
			activeWorkspaceId: readWorkspaceId(),
			showWorkspaceMenu: false,
			addModalReady: false
		}
	},
	computed: {
		activeProjects() {
			return this.projects.filter(p => !p.is_archived);
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
		hasIncomes() {
			return this.$enableIncomes && this.tagCounts.income > 0;
		},
		hasFuturePayments() {
			return this.$enableFuturePayments && this.tagCounts.future > 0;
		},
		hasImportantPayments() {
			return this.$enableImportantPayments && this.tagCounts.important > 0;
		},
		hasReviewPayments() {
			return this.$enableReviewPayments && this.tagCounts.review > 0;
		},
		hasFixedCosts() {
			return this.$enableFixedCosts && this.tagCounts.fixedCosts > 0;
		},
		hasChildRelatedPayments() {
			return this.$enableChildRelated && this.tagCounts.childRelated > 0;
		},
		hasSubscriptions() {
			return this.$enableSubscriptions && this.tagCounts.subscriptions > 0;
		},
		hasTaxRelevantPayments() {
			return this.$enableTaxRelevant && this.tagCounts.taxRelevant > 0;
		}
	},
	watch: {
		$route() {
			this.showWorkspaceMenu = false;
		}
	},
	created() {
	},
	mounted() {
		if (this.$enableProjects) {
			this.fetchProjects()
		}
		this.fetchNavigationSummary()
		if (this.$enableWorkspaces) {
			this.fetchWorkspaces()
		}
		window.addEventListener('open-add-modal', this.openAddModal)
		window.addEventListener('workspaces-updated', this.onWorkspacesUpdated)
		window.addEventListener('cobudget-data-changed', this.refreshNavigationData)
		window.addEventListener('click', this.closeWorkspaceMenu)
	},
	beforeUnmount() {
		window.removeEventListener('open-add-modal', this.openAddModal)
		window.removeEventListener('workspaces-updated', this.onWorkspacesUpdated)
		window.removeEventListener('cobudget-data-changed', this.refreshNavigationData)
		window.removeEventListener('click', this.closeWorkspaceMenu)
	},
	methods: {
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
				taxRelevant: parseInt(counts.taxRelevant || 0, 10)
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
			this.activeWorkspaceId = w.id;
			writeWorkspaceId(w.id);
			this.showWorkspaceMenu = false;
			// Reload the whole page to refetch all data with the new workspace
			window.location.hash = '#/';
			window.location.reload();
		},
		closeWorkspaceMenu(e) {
			if (!e.target.closest('.workspace-switcher')) {
				this.showWorkspaceMenu = false;
			}
		},
		async waitForAddModalRef() {
			for (let i = 0; i < 30; i++) {
				await this.$nextTick();
				if (this.$refs.globalAddModal && typeof this.$refs.globalAddModal.openModal === 'function') {
					return this.$refs.globalAddModal;
				}
				await new Promise(resolve => window.setTimeout(resolve, 20));
			}

			return null;
		},
		async openAddModal(eventOrPayload) {
			let projectId = null;
			let entryToEdit = null;
			let editingFuture = false;
			let templateToLoad = null;
			let isTemplateMode = false;
			let entryToDuplicate = null;
			let defaultType = this.routeDefaultEntryType();

			if (eventOrPayload && eventOrPayload.detail) {
				projectId = eventOrPayload.detail.projectId;
				entryToEdit = eventOrPayload.detail.entry;
				editingFuture = eventOrPayload.detail.isEditingFuture || false;
				templateToLoad = eventOrPayload.detail.templateToLoad || null;
				isTemplateMode = eventOrPayload.detail.isTemplateMode || false;
				entryToDuplicate = eventOrPayload.detail.entryToDuplicate || null;
				defaultType = this.sanitizeEntryType(eventOrPayload.detail.defaultType || defaultType);
			} else if (eventOrPayload) {
				projectId = eventOrPayload.projectId;
				entryToEdit = eventOrPayload.entry;
				editingFuture = eventOrPayload.isFuture || false;
				templateToLoad = eventOrPayload.templateToLoad || null;
				isTemplateMode = eventOrPayload.isTemplateMode || false;
				entryToDuplicate = eventOrPayload.entryToDuplicate || null;
				defaultType = this.sanitizeEntryType(eventOrPayload.defaultType || defaultType);
			}

			if (!this.$enableTemplates) {
				templateToLoad = null;
				isTemplateMode = false;
			}

			this.addModalReady = true;
			const modal = await this.waitForAddModalRef();
			if (modal) {
				modal.openModal(entryToEdit, projectId, editingFuture, templateToLoad, isTemplateMode, entryToDuplicate, defaultType);
			}
		},
		routeDefaultEntryType() {
			return this.$enableIncomes && this.$route?.name === 'personal' && this.$route?.query?.filter === 'income'
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
			return ['project-detail', 'project-settings', 'project-settlements'].includes(this.$route.name)
				&& String(this.$route.params.id) === String(project.id);
		},
		onEntrySaved() {
			// Broadcast a global event so the current view knows to refresh
			this.refreshNavigationData();
			window.dispatchEvent(new CustomEvent('entry-saved'));
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

:global(#content.app-cobudget #app-content),
:global(#content.app-cobudget .app-content-vue),
:global(#content.app-cobudget .app-content) {
	background: var(--cobudget-page-background);
}

.content-wrapper {
	max-width: 100%;
	margin: 0;
	padding: 5px 10px 10px 15px;
	background: transparent;
	border-radius: 0;
	box-shadow: none;
	min-height: 80vh;
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

:global(#content.app-cobudget #app-navigation .app-navigation-entry),
:global(#content.app-cobudget .app-navigation-entry),
:global(#content.app-cobudget #app-navigation .app-navigation-entry-link),
:global(#content.app-cobudget #app-navigation .app-navigation-entry-button),
:global(#content.app-cobudget .app-navigation-entry-link),
:global(#content.app-cobudget .app-navigation-entry-button) {
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
	position: relative;
}

:global(#content.app-cobudget #app-navigation .app-navigation-entry__title),
:global(#content.app-cobudget #app-navigation .app-navigation-entry__name),
:global(#content.app-cobudget #app-navigation .app-navigation-entry__utils),
:global(#content.app-cobudget .app-navigation-entry__title),
:global(#content.app-cobudget .app-navigation-entry__name),
:global(#content.app-cobudget .app-navigation-entry__utils) {
	color: inherit !important;
}

:global(#content.app-cobudget #app-navigation .app-navigation-entry:hover),
:global(#content.app-cobudget #app-navigation .app-navigation-entry:focus-visible),
:global(#content.app-cobudget .app-navigation-entry:hover),
:global(#content.app-cobudget .app-navigation-entry:focus-visible) {
	background-color: var(--cobudget-navigation-hover-background, var(--color-background-hover, #f5f5f5)) !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
	opacity: 1 !important;
}

:global(#content.app-cobudget #app-navigation .app-navigation-entry--active),
:global(#content.app-cobudget #app-navigation .app-navigation-entry.active),
:global(#content.app-cobudget .app-navigation-entry--active),
:global(#content.app-cobudget .app-navigation-entry.active) {
	background-color: var(--cobudget-navigation-active-background, var(--color-primary-element-light, var(--color-background-hover, #e6f4fb))) !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
	opacity: 1 !important;
	box-shadow: none !important;
	position: relative;
}

:global(#content.app-cobudget #app-navigation .app-navigation-entry--active::before),
:global(#content.app-cobudget #app-navigation .app-navigation-entry.active::before),
:global(#content.app-cobudget .app-navigation-entry--active::before),
:global(#content.app-cobudget .app-navigation-entry.active::before) {
	content: '';
	position: absolute;
	top: 8px;
	bottom: 8px;
	left: 0;
	width: 4px;
	border-radius: 0 var(--border-radius-pill, 999px) var(--border-radius-pill, 999px) 0;
	background-color: var(--color-primary-element, var(--cobudget-primary, #0082c9));
	pointer-events: none;
}

:global(#content.app-cobudget #app-navigation .app-navigation-entry--active .app-navigation-entry-link),
:global(#content.app-cobudget #app-navigation .app-navigation-entry--active .app-navigation-entry-button),
:global(#content.app-cobudget #app-navigation .app-navigation-entry.active .app-navigation-entry-link),
:global(#content.app-cobudget #app-navigation .app-navigation-entry.active .app-navigation-entry-button),
:global(#content.app-cobudget .app-navigation-entry--active .app-navigation-entry-link),
:global(#content.app-cobudget .app-navigation-entry--active .app-navigation-entry-button),
:global(#content.app-cobudget .app-navigation-entry.active .app-navigation-entry-link),
:global(#content.app-cobudget .app-navigation-entry.active .app-navigation-entry-button) {
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

@media (max-width: 768px) {
	.content-wrapper {
		padding: 10px;
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

	:global(#content.app-cobudget #app-navigation .app-navigation-entry:hover),
	:global(#content.app-cobudget #app-navigation .app-navigation-entry:focus-visible),
	:global(#content.app-cobudget .app-navigation-entry:hover),
	:global(#content.app-cobudget .app-navigation-entry:focus-visible) {
		background-color: var(--cobudget-navigation-hover-background, var(--color-background-hover, #f5f5f5)) !important;
		opacity: 1 !important;
	}

	:global(#content.app-cobudget #app-navigation .app-navigation-entry--active),
	:global(#content.app-cobudget #app-navigation .app-navigation-entry.active),
	:global(#content.app-cobudget .app-navigation-entry--active),
	:global(#content.app-cobudget .app-navigation-entry.active) {
		background-color: var(--cobudget-navigation-active-background, var(--color-primary-element-light, var(--color-background-hover, #e6f4fb))) !important;
		color: var(--cobudget-text, var(--color-main-text, #222)) !important;
		opacity: 1 !important;
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

.review-alert-dot {
	margin-right: 2px;
}
</style>

<style>
/* Globale Styles für den einheitlichen Header */
.v-popper__arrow-container {
	display: none !important;
}

.app-navigation-toggle {
	position: absolute !important;
	top: -1px !important;
	left: -35px !important;
	z-index: 100 !important;
	margin: 0 !important;
}

@media (max-width: 768px) {
	.app-navigation-toggle {
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

.entry-badge {
	display: inline-block;
	padding: 2px 6px;
	border-radius: 4px;
	font-size: var(--cobudget-font-xs);
	font-weight: 600;
	vertical-align: middle;
}

.badge-abo, .badge-fixed {
	background: var(--color-primary-light, #e0f2fe);
	color: var(--color-primary, #0082c9);
	border: 1px solid var(--color-primary, #0082c9);
}

/* Workspace Switcher */
.workspace-switcher {
	position: relative;
	box-sizing: border-box;
	padding: 8px 0;
	width: 100%;
	border-top: 1px solid var(--cobudget-border, #ddd);
}
.workspace-current {
	display: flex;
	align-items: center;
	gap: 8px;
	box-sizing: border-box;
	min-height: var(--default-clickable-area, 44px);
	padding: 0 12px;
	width: 100%;
	cursor: pointer;
	border-radius: var(--border-radius-element, var(--border-radius, 6px));
	user-select: none;
}
.workspace-current:hover {
	background: var(--cobudget-surface-muted, #f0f0f0);
}
.workspace-label {
	flex: 1;
	font-weight: 600;
	font-size: var(--cobudget-font-base);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.workspace-menu {
	position: absolute;
	bottom: 100%;
	left: 0;
	right: 0;
	background: var(--cobudget-page-background, #fff);
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	box-shadow: 0 4px 14px rgba(0,0,0,0.15);
	list-style: none;
	padding: 6px 0;
	margin: 0 0 4px 0;
	z-index: 1000;
}
.workspace-menu li {
	padding: 10px 14px;
	cursor: pointer;
	font-size: var(--cobudget-font-base);
}
.workspace-menu li:hover {
	background: var(--cobudget-surface-muted, #f0f0f0);
}
.workspace-menu li.active {
	font-weight: bold;
	color: var(--color-primary, #0082c9);
}
</style>
