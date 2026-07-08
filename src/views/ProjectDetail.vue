<template>
	<div v-if="project" class="project-detail">
		<AppPageHeader class="detail-header">
			<template #title>
				<span v-if="project.color" class="project-color-dot"
					:style="{ backgroundColor: project.color }"></span>
				{{ project.name }}
			</template>
			<template #actions>
					<NcActions>
						<NcActionButton v-if="canSettleProject && hasBalancesToSettle" :close-after-click="true" @click="settleUp" icon="icon-checkmark">
							{{ $texts.areaDetail.settleArea() }}
						</NcActionButton>
						<NcActionButton v-if="canManageProject" :close-after-click="true" @click="openProjectSettings" icon="icon-rename">
							{{ $texts.areaDetail.areaSettings() }}
						</NcActionButton>
						<NcActionButton v-if="showSettlementControls" :close-after-click="true" @click="openProjectSettlements" icon="icon-history">
							{{ $texts.areaDetail.settlements() }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="showFilterPanel = true" icon="icon-search">
							{{ $texts.filters.search() }}
						</NcActionButton>
						<NcActionButton v-if="canExportEntries" :close-after-click="true" @click="exportEntries" icon="icon-download" :disabled="isExporting">
							{{ isExporting ? $texts.common.exportCsvBusy() : $texts.common.exportCsv() }}
						</NcActionButton>
						<NcActionButton v-if="canManageProject" :close-after-click="true" @click="toggleArchiveProject"
							:icon="project.is_archived ? 'icon-history' : 'icon-toggle-pictures'"
							:disabled="activeEntries.length > 0"
							:title="activeEntries.length > 0 ? $texts.areaDetail.archiveBlocked() : ''">
							{{ project.is_archived ? $texts.areaSettings.unarchive() : $texts.areaSettings.archive() }}
						</NcActionButton>
						<NcActionButton v-if="canManageProject" :close-after-click="true" @click="deleteProject" icon="icon-delete" :disabled="hasEntries"
							:title="hasEntries ? $texts.areaDetail.deleteBlockedWithEntries() : ''">
							{{ $texts.common.delete() }}
						</NcActionButton>
					</NcActions>
					<NcButton v-if="canExportEntries" :aria-label="$texts.common.exportCsv()" :title="isExporting ? $texts.common.exportCsvBusy() : $texts.common.exportCsv()" variant="tertiary" class="filter-toggle-btn cobudget-toolbar-icon-button project-export-header-btn" :disabled="isExporting" @click="exportEntries">
						<template #icon>
							<DownloadIcon :size="20" />
						</template>
					</NcButton>
					<NcPopover placement="bottom-end" class="project-filter-header-btn">
						<template #trigger>
							<NcButton :aria-label="$texts.filters.search()" :title="$texts.filters.search()" variant="tertiary" class="filter-toggle-btn cobudget-toolbar-icon-button" :class="{ 'is-active': hasActiveFilters }">
								<template #icon>
									<MagnifyIcon :size="20" />
								</template>
							</NcButton>
						</template>
						<div class="filter-popover-content">
							<TableFilters 
								ref="tableFilters"
								:showStatusFilter="false"
								:categories="categories" 
								:paymentPartners="paymentPartners"
								:hashtags="hashtags"
								:initialFilters="filters"
								@update:filters="onFiltersUpdate"
							/>
						</div>
					</NcPopover>
					<NcButton v-if="canManageProject" variant="secondary" class="edit-project-btn cobudget-toolbar-text-button project-settings-header-btn" @click="openProjectSettings"
						:aria-label="$texts.areaDetail.areaSettings()" :title="$texts.areaDetail.areaSettings()">
						<template #icon>
							<PencilIcon :size="20" />
						</template>
						<span class="btn-text">{{ $texts.areaDetail.areaSettings() }}</span>
					</NcButton>
          <NcButton v-if="canSettleProject && hasBalancesToSettle" @click="settleUp" class="btn-settle-header project-settle-header-btn"
                    :title="$texts.areaDetail.settleArea()" variant="warning">
            <template #icon>
              <CheckAllIcon :size="20" fillColor="#000" />
            </template>
            <span class="btn-text">{{ $texts.areaDetail.settleArea() }}</span>
          </NcButton>
					<div class="add-button-group" style="display: flex; border-radius: var(--border-radius, 3px); overflow: hidden;">
						<NcButton variant="primary" class="new-payment-main-button" @click="openAddModal" :aria-label="$texts.areaDetail.newPayment()" :title="$texts.areaDetail.newPayment()" style="border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: 1px solid rgba(255, 255, 255, 0.72); margin-right: 0;">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
							<span class="btn-text">{{ $texts.areaDetail.newPayment() }}</span>
						</NcButton>
						<NcPopover v-if="$enableTemplates" placement="bottom-end" :key="templatePopoverKey">
							<template #trigger>
								<NcButton variant="primary" class="new-payment-toggle-button" style="border-top-left-radius: 0; border-bottom-left-radius: 0; padding: 0 8px;">
									<template #icon>
										<ChevronDownIcon :size="20" />
									</template>
								</NcButton>
							</template>
							<div class="template-list-popover" style="padding: 8px; min-width: 200px;">
								<div v-for="t in templates" :key="t.id" class="template-item action-item" @click="openAddModal({ templateToLoad: t }); closeTemplatePopover()" style="display: flex; justify-content: space-between; align-items: center;">
									<div class="template-title">{{ t.name }}</div>
									<NcButton variant="tertiary" @click.stop="deleteTemplate(t, $event)" :aria-label="$texts.entry.deleteTemplateTitle()" :title="$texts.entry.deleteTemplateTitle()" style="margin-left: 10px; padding: 4px;">
										<template #icon>
											<DeleteIcon :size="16" />
										</template>
									</NcButton>
								</div>
								<hr v-if="templates.length > 0" style="margin: 8px 0; border: none; border-top: 1px solid var(--cobudget-border);">
								<div class="template-item action-item" @click="openAddModal({ isTemplateMode: true }); closeTemplatePopover()">
									<strong>+ {{ $texts.entry.newTemplate() }}</strong>
								</div>
							</div>
						</NcPopover>
					</div>
			</template>
		</AppPageHeader>

		<Teleport to="body">
			<div
				v-if="showFilterPanel"
				class="cobudget-filter-sheet-backdrop"
				role="presentation"
				@keydown.esc.stop.prevent="showFilterPanel = false"
				@click.self="showFilterPanel = false">
				<section class="cobudget-filter-sheet" role="dialog" aria-modal="true" :aria-label="$texts.filters.search()">
					<div class="cobudget-filter-sheet__header">
						<h3>{{ $texts.filters.search() }}</h3>
						<NcButton :aria-label="$texts.common.close()" :title="$texts.common.close()" variant="tertiary" class="cobudget-toolbar-icon-button" @click="showFilterPanel = false">
							<template #icon>
								<CloseIcon :size="22" />
							</template>
						</NcButton>
					</div>
					<TableFilters
						:showStatusFilter="false"
						:categories="categories"
						:paymentPartners="paymentPartners"
						:hashtags="hashtags"
						:initialFilters="filters"
						@update:filters="onFiltersUpdate"
					/>
				</section>
			</div>
		</Teleport>

		<DraggableScroller v-if="projectDashboardCards.length > 0" class="stats-row project-stats-row">
			<div v-for="card in projectDashboardCards" :key="card.key" class="stat-card" :class="card.cardClass">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon">
							<component :is="card.icon" :size="20" :fillColor="card.iconColor" />
						</div>
						<span class="stat-label">{{ card.label }}</span>
					</div>
					<TableTooltip :text="card.tooltip">
						<span class="stat-value" :class="card.valueClass">{{ card.value }}</span>
					</TableTooltip>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.areaDetail.notSettled() }}</span>
						<span>{{ $texts.areaDetail.entryCount(card.metric.activeCount) }}</span>
					</div>
				</div>
			</div>
		</DraggableScroller>

		<!-- Members / Balances Section -->
		<div class="section members-section balances-section" v-if="showMemberBalances && project.balances && project.balances.length > 0">
			<div class="section-header">
				<h3 style="display: flex; align-items: center; gap: 8px;">
					<AccountMultipleIcon :size="20" /> {{ $texts.areaDetail.members(project.members ? project.members.length : 0) }}
				</h3>
			</div>

			<DraggableScroller class="balances-grid">
				<div v-for="b in project.balances" :key="b.userId" class="balance-card"
					:class="{ 'positive': b.balance > 0, 'negative': b.balance < 0, 'neutral': b.balance === 0 }">
					<div class="balance-card-header">
						<div class="balance-name">
							<NcAvatar :user="b.userId" :display-name="b.displayName" :size="24" class="inline-avatar" />
							<span>{{ b.displayName }}</span>
						</div>
						<div class="balance-actions">
							<span v-if="b.userId === project.owner_id" class="owner-badge">{{ $texts.areaDetail.creator() }}</span>
						</div>
					</div>
					<div class="balance-detail">{{ $texts.areaDetail.paid() }}: {{ formatCurrency(b.paid) }}</div>
					<div class="balance-detail">{{ $texts.areaDetail.share() }}: {{ formatCurrency(b.fairShare) }} ({{ formatSharePercent(b.shareBasisPoints) }}%)</div>
					<div class="balance-amount">
						<template v-if="b.balance > 0">{{ $texts.areaDetail.getsBack(formatCurrency(b.balance)) }}</template>
						<template v-else-if="b.balance < 0">{{ $texts.areaDetail.owes(formatCurrency(Math.abs(b.balance))) }}</template>
						<template v-else>{{ $texts.areaDetail.balanced() }} ✓</template>
					</div>
				</div>
			</DraggableScroller>
		</div>

		<div v-if="showSettlementControls && project.repaymentTransfers && project.repaymentTransfers.length > 0" class="section repayments-section">
			<div class="section-header">
				<h3>{{ $texts.areaDetail.repayments() }}</h3>
			</div>
			<div class="repayment-list">
				<div v-for="transfer in project.repaymentTransfers" :key="`${transfer.fromUserId}-${transfer.toUserId}-${transfer.amountCents}`" class="repayment-row">
					<span class="repayment-person">{{ transfer.fromDisplayName }}</span>
					<span class="repayment-arrow">{{ $texts.areaDetail.paysTo() }}</span>
					<span class="repayment-person">{{ transfer.toDisplayName }}</span>
					<strong class="repayment-amount">{{ formatCurrency(transfer.amount) }}</strong>
				</div>
			</div>
		</div>

		<!-- Active Entries -->
		<div class="section">
			
			<EntryTable
				v-if="activeEntries.length > 0"
				mode="project"
				:entries="activeEntries"
				:date-groups="activeDateGroups"
				:currency="$currency"
				:date-label="$texts.entry.tableDate()"
				:sort-by="sortBy"
				:sort-dir="sortDir"
				:enable-fixed-costs="$enableFixedCosts"
				:enable-subscriptions="$enableSubscriptions"
				:enable-child-related="$enableChildRelated"
				:enable-important-payments="$enableImportantPayments"
				:enable-review-payments="$enableReviewPayments"
				:enable-tax-relevant="$enableTaxRelevant"
				:actions-enabled="project.status !== 'archived'"
				:archived="project.status === 'archived'"
				:project-name-resolver="getProjectName"
				:project-style-resolver="getProjectTagStyle"
				:member-name-resolver="getMemberName"
				@sort="toggleSort"
				@row-click="onRowClick"
				@edit="editEntry"
				@duplicate="duplicateEntry"
				@delete="deleteEntry"
				@history="openEntryHistory">
				<template #pagination>
					<div class="pagination-footer" :class="{ 'pagination-footer--single': activePagination.total <= activePagination.limit }">
						<NcButton v-if="activePagination.total > activePagination.limit" variant="secondary" class="btn-page cobudget-toolbar-text-button" :style="{ visibility: activePagination.offset > 0 ? 'visible' : 'hidden' }" @click="prevActivePage">
							<template #icon>
								<ArrowLeftIcon :size="20" />
							</template>
							<span class="pagination-label">{{ $texts.common.previous() }}</span>
						</NcButton>
						<span class="page-info">{{ $texts.common.pageInfo(activePagination.offset + 1, Math.min(activePagination.offset + activePagination.limit, activePagination.total), activePagination.total) }}</span>
						<NcButton v-if="activePagination.total > activePagination.limit" variant="secondary" class="btn-page btn-page-next cobudget-toolbar-text-button" :style="{ visibility: activePagination.offset + activePagination.limit < activePagination.total ? 'visible' : 'hidden' }" @click="nextActivePage">
							<span class="pagination-label">{{ $texts.common.next() }}</span>
							<ArrowRightIcon class="pagination-icon-right" :size="20" />
						</NcButton>
					</div>
				</template>
			</EntryTable>
			<NcEmptyContent
				v-else
				class="empty-content-state"
				:name="hasActiveFilters ? $texts.common.noFilteredEntriesTitle() : $texts.areaDetail.noOpenEntriesTitle()"
				:description="hasActiveFilters ? $texts.common.noFilteredEntriesDescription() : $texts.areaDetail.noOpenEntriesDescription()">
				<template #icon>
					<WalletIcon :size="64" />
				</template>
				<template #action>
					<NcButton v-if="hasActiveFilters" variant="secondary" @click="resetFilters">
						{{ $texts.common.resetFilters() }}
					</NcButton>
					<NcButton v-else-if="project.status !== 'archived'" variant="primary" @click="openAddModal">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ $texts.areaDetail.newPayment() }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</div>

		<EntryHistoryModal
			v-if="entryHistoryOpen"
			:history="entryHistoryRows"
			:loading="entryHistoryLoading"
			@close="closeEntryHistory" />

		<ConfirmModal
			:show="showSettleConfirm"
			:title="$texts.areaDetail.settleArea()"
			:message="$texts.areaDetail.settleMessage()"
			:confirm-label="$texts.areaDetail.settleArea()"
			@confirm="confirmSettleUp"
			@cancel="showSettleConfirm = false">
			<div class="settlement-preview">
				<h3>{{ $texts.areaDetail.repayments() }}</h3>
				<div v-if="project.repaymentTransfers && project.repaymentTransfers.length > 0" class="repayment-list compact">
					<div v-for="transfer in project.repaymentTransfers" :key="`preview-${transfer.fromUserId}-${transfer.toUserId}-${transfer.amountCents}`" class="repayment-row">
						<span class="repayment-person">{{ transfer.fromDisplayName }}</span>
						<span class="repayment-arrow">{{ $texts.areaDetail.paysTo() }}</span>
						<span class="repayment-person">{{ transfer.toDisplayName }}</span>
						<strong class="repayment-amount">{{ formatCurrency(transfer.amount) }}</strong>
					</div>
				</div>
				<p v-else class="settlement-empty">{{ $texts.areaDetail.noRepaymentNeeded() }}</p>
				<p class="settlement-preview-note">{{ $texts.areaDetail.settlementSuggestionSaved() }}</p>
			</div>
		</ConfirmModal>

		<ConfirmModal
			:show="!!confirmDialog"
			:title="confirmDialog ? confirmDialog.title : ''"
			:message="confirmDialog ? confirmDialog.message : ''"
			:confirm-label="confirmDialog ? confirmDialog.confirmLabel : ''"
			:confirm-variant="confirmDialog ? confirmDialog.confirmVariant : 'primary'"
			@confirm="resolveConfirm(true)"
			@cancel="resolveConfirm(false)" />

	</div>
	<div v-else class="loading">{{ $texts.common.loading() }}</div>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import TableFilters from '../components/TableFilters.vue'
import ConfirmModal from '../components/ConfirmModal.vue'
import EntryTable from '../components/EntryTable.vue'
import EntryHistoryModal from '../components/EntryHistoryModal.vue'
import TableTooltip from '../components/TableTooltip.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import CheckAllIcon from 'vue-material-design-icons/CheckAll.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import ChartBarIcon from 'vue-material-design-icons/ChartBar.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import WalletIcon from 'vue-material-design-icons/Wallet.vue'
import SyncIcon from 'vue-material-design-icons/Sync.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import ClipboardCheckIcon from 'vue-material-design-icons/ClipboardCheck.vue'
import AccountChildIcon from 'vue-material-design-icons/AccountChild.vue'
import ReceiptTextCheckOutlineIcon from 'vue-material-design-icons/ReceiptTextCheckOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import TrendingDownIcon from 'vue-material-design-icons/TrendingDown.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import DraggableScroller from '../components/DraggableScroller.vue'
import { normalizeEntryPageSize, shouldIgnorePaginationKeydown } from '../services/pagination'
import { showRequestError, showToast } from '../services/notifications'
import { downloadBlobResponse } from '../services/downloads'

export default {
	name: 'ProjectDetail',
	components: { AppPageHeader, NcButton, NcEmptyContent, NcAvatar, NcActions, NcActionButton, NcPopover, ConfirmModal, EntryTable, EntryHistoryModal, TableTooltip, MagnifyIcon, AccountMultipleIcon, ChartBarIcon, ArchiveIcon, CheckAllIcon, PencilIcon, TrendingUpIcon, TrendingDownIcon, WalletIcon, SyncIcon, LockIcon, StarIcon, ClipboardCheckIcon, AccountChildIcon, ReceiptTextCheckOutlineIcon, ArrowLeftIcon, ArrowRightIcon, PlusIcon, ChevronDownIcon, ChevronUpIcon, CloseIcon, DeleteIcon, DownloadIcon, TableFilters, DraggableScroller },
	props: ['id'],
	data() {
		return {
			showFilterPanel: false,
			project: null,
			activeEntries: [],
			activeDateGroups: null,
			templates: [],
			categories: [],
			paymentPartners: [],
			hashtags: [],
			isExporting: false,
			showSettleConfirm: false,
			filters: {
				search: '',
				type: 'all',
				status: 'active',
				categoryId: null,
				paymentPartnerId: null,
				dateFrom: null,
				dateTo: null,
				timeRange: 'all',
				recurring: 'all',
				tags: 'all',
				hashtagId: null,
				hasReminder: 'all',
				hasAttachment: 'all'
			},
			sortBy: 'date',
			sortDir: 'desc',
			activePagination: {
				limit: 25,
				offset: 0,
				total: 0
			},
			entryHistoryOpen: false,
			entryHistoryLoading: false,
			entryHistoryRows: [],
			templatePopoverKey: 0,
			confirmDialog: null
		}
	},
	computed: {
		hasActiveFilters() {
			return this.filters.search !== '' || 
				   this.filters.type !== 'all' || 
				   this.filters.status !== 'active' ||
				   this.filters.categoryId !== null ||
				   this.filters.paymentPartnerId !== null ||
				   this.filters.hashtagId !== null ||
				   this.filters.timeRange !== 'all' ||
				   this.filters.recurring !== 'all' ||
				   this.filters.tags !== 'all' ||
				   this.filters.hasReminder !== 'all' ||
				   this.filters.hasAttachment !== 'all';
		},
		projectId() {
			return this.id;
		},
		hasEntries() {
			return this.activeEntries.length > 0;
		},
		hasBalancesToSettle() {
			return this.activeEntries.length > 0 || this.activePagination.total > 0;
		},
		canExportEntries() {
			return Number(this.activePagination.total || 0) > 0;
		},
		projectMemberCount() {
			if (this.project && Array.isArray(this.project.members)) {
				return this.project.members.length;
			}
			if (this.project && Array.isArray(this.project.balances)) {
				return this.project.balances.length;
			}
			return parseInt(this.project?.member_count || 0, 10) || 0;
		},
		hasAdditionalProjectMembers() {
			return this.projectMemberCount > 1;
		},
		showMemberBalances() {
			return this.$enableSharedProjects || this.hasAdditionalProjectMembers;
		},
		showSettlementControls() {
			return this.$enableSharedProjects || this.hasAdditionalProjectMembers;
		},
		canManageProject() {
			return this.isTruthy(this.project?.is_owner);
		},
		canSettleProject() {
			return this.canManageProject && this.showSettlementControls;
		},
		projectDashboardCards() {
			const definitions = [
				{ key: 'income', label: this.$texts.common.income(), icon: TrendingUpIcon, iconColor: '#107C41', mode: 'positive', cardClass: 'income-card', enabled: this.$enableIncomes },
				{ key: 'expense', label: this.$texts.common.expense(), icon: TrendingDownIcon, iconColor: 'var(--cobudget-error)', mode: 'negative', cardClass: 'expense-card', enabled: true },
				{ key: 'balance', label: 'Saldo', icon: WalletIcon, iconColor: '#0082c9', mode: 'signed', cardClass: 'total-card', enabled: this.$enableIncomes },
				{ key: 'important', label: this.$texts.labels.important(), icon: StarIcon, iconColor: '#ffc92b', mode: 'signed', cardClass: 'important-card', enabled: this.$enableImportantPayments },
				{ key: 'review', label: this.$texts.labels.review(), icon: ClipboardCheckIcon, iconColor: 'var(--cobudget-error)', mode: 'signed', cardClass: 'review-card', enabled: this.$enableReviewPayments },
				{ key: 'fixedCosts', label: this.$texts.labels.fixedCosts(), icon: LockIcon, iconColor: '#e67e22', mode: 'negative', cardClass: 'fixed-costs-card', enabled: this.$enableFixedCosts },
				{ key: 'childRelated', label: this.$texts.labels.children(), icon: AccountChildIcon, iconColor: '#0f766e', mode: 'signed', cardClass: 'child-card', enabled: this.$enableChildRelated },
				{ key: 'subscriptions', label: this.$texts.labels.subscription(), icon: SyncIcon, iconColor: '#8e44ad', mode: 'negative', cardClass: 'abos-card', enabled: this.$enableSubscriptions },
				{ key: 'taxRelevant', label: this.$texts.labels.taxRelevant(), icon: ReceiptTextCheckOutlineIcon, iconColor: '#0082c9', mode: 'signed', cardClass: 'tax-card', enabled: this.$enableTaxRelevant },
			];

			return definitions
				.filter(definition => definition.enabled && this.hasProjectDashboardMetric(definition.key))
				.map(definition => {
					const metric = this.projectDashboardMetric(definition.key);
					return {
						...definition,
						metric,
						value: this.formatProjectDashboardAmount(metric.activeTotal, definition.mode),
						valueClass: this.projectDashboardValueClass(metric.activeTotal, definition.mode),
						tooltip: this.projectDashboardTooltip(metric, definition.mode),
					};
				});
		}
	},
	mounted() {
			this.applyEntryPageSize()
			this.fetchProjectData()
			if (this.$enableTemplates) {
				this.fetchTemplates()
			}
		window.addEventListener('entry-saved', this.onEntrySaved)
		window.addEventListener('settings-closed', this.onSettingsClosed)
		window.addEventListener('keydown', this.onPaginationKeydown)
	},
	beforeUnmount() {
		window.removeEventListener('entry-saved', this.onEntrySaved)
		window.removeEventListener('settings-closed', this.onSettingsClosed)
		window.removeEventListener('keydown', this.onPaginationKeydown)
	},
	watch: {
		projectId() {
			this.resetProjectTableState()
			this.fetchProjectData()
		}
	},
	methods: {
		async openEntryHistory(entry) {
			this.entryHistoryOpen = true
			this.entryHistoryLoading = true
			this.entryHistoryRows = []
			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/entries/${entry.id}/history`))
				this.entryHistoryRows = Array.isArray(response.data?.history) ? response.data.history : []
			} catch (error) {
				showRequestError(error, this.$texts.entry.historyFetchError(), 'Failed to fetch entry history')
			} finally {
				this.entryHistoryLoading = false
			}
		},
		closeEntryHistory() {
			this.entryHistoryOpen = false
			this.entryHistoryLoading = false
			this.entryHistoryRows = []
		},
		openConfirm({ title, message, confirmLabel, confirmVariant = 'primary' }) {
			return new Promise(resolve => {
				this.confirmDialog = {
					title,
					message,
					confirmLabel,
					confirmVariant,
					resolve
				};
			});
		},
		resolveConfirm(confirmed) {
			const resolver = this.confirmDialog?.resolve;
			this.confirmDialog = null;
			if (resolver) {
				resolver(confirmed);
			}
		},
		applyEntryPageSize() {
			this.activePagination.limit = normalizeEntryPageSize(this.$entriesPerPage)
			this.activePagination.offset = 0
		},
		onPaginationKeydown(event) {
			if (shouldIgnorePaginationKeydown(event) || this.activePagination.total <= this.activePagination.limit) {
				return
			}

			if (event.key === 'ArrowLeft' && this.activePagination.offset > 0) {
				event.preventDefault()
				this.prevActivePage()
			} else if (event.key === 'ArrowRight' && this.activePagination.offset + this.activePagination.limit < this.activePagination.total) {
				event.preventDefault()
				this.nextActivePage()
			}
		},
		onSettingsClosed() {
			this.categories = [];
			this.paymentPartners = [];
			this.hashtags = [];
			this.fetchProjectData();
		},
		isTruthy(val) {
			return val === true || val === 1 || val === '1' || val === 'true'
		},
		openProjectSettings() {
			if (!this.canManageProject) {
				return;
			}
			this.$router.push({ name: 'project-settings', params: { id: this.projectId } });
		},
		openProjectSettlements() {
			this.$router.push({ name: 'project-settlements', params: { id: this.projectId } });
		},
		resetProjectTableState() {
			this.filters = {
				search: '',
				type: 'all',
				status: 'active',
				categoryId: null,
				paymentPartnerId: null,
				dateFrom: null,
				dateTo: null,
				timeRange: 'all',
				recurring: 'all',
				tags: 'all',
				hashtagId: null,
				hasReminder: 'all',
				hasAttachment: 'all'
			}
			this.activePagination.offset = 0
			if (this.$refs.tableFilters) {
				this.$refs.tableFilters.localFilters = { ...this.filters }
			}
		},
		async deleteProject() {
			if (!this.canManageProject) {
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.areaDetail.deleteArea(),
				message: this.$texts.areaDetail.deleteAreaMessage(),
				confirmLabel: this.$texts.areaDetail.deleteArea(),
				confirmVariant: 'danger'
			});
			if (!confirmed) return;

			try {
				await axios.delete(generateUrl('/apps/cobudget/api/projects/' + this.project.id));
				this.$emit('refresh-projects');
				this.$router.push('/');
			} catch (e) {
				showRequestError(e, this.$texts.areaDetail.deleteError(), 'Failed to delete project');
			}
		},
		async toggleArchiveProject() {
			if (!this.canManageProject) {
				return;
			}
			const action = this.project.is_archived ? 'unarchive' : 'archive';
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/projects/${this.project.id}/${action}`));
				this.project.is_archived = !this.project.is_archived;
				this.$emit('refresh-projects');
			} catch (e) {
				showRequestError(e, this.$texts.areaDetail.statusSaveError(), 'Failed to toggle archive project');
			}
		},
		async fetchProjectData() {
			try {
				const projRes = await axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}`))
				this.project = projRes.data

				if (this.categories.length === 0) {
					const catRes = await axios.get(generateUrl('/apps/cobudget/api/categories'), { params: { projectId: this.projectId } })
					this.categories = (catRes.data || []).sort((a, b) => a.name.localeCompare(b.name))
				}

				if (this.paymentPartners.length === 0) {
					const paymentPartnerRes = await axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params: { projectId: this.projectId } })
					this.paymentPartners = (paymentPartnerRes.data || []).sort((a, b) => a.name.localeCompare(b.name))
				}

				await this.fetchActiveEntries();
			} catch (e) {
				showRequestError(e, this.$texts.areaDetail.loadError(), 'Failed to fetch project details')
			}
		},
		async fetchTemplates() {
			if (!this.$enableTemplates) {
				this.templates = []
				return
			}
			try {
				const res = await axios.get(generateUrl('/apps/cobudget/api/templates'))
				this.templates = this.sortTemplates(res.data || [])
			} catch (e) {
				showRequestError(e, this.$texts.entry.templatesLoadError(), 'Failed to fetch templates')
				this.templates = []
			}
		},
		sortTemplates(templates) {
			return [...templates].sort((a, b) => {
				const usageDiff = (parseInt(b.usage_count, 10) || 0) - (parseInt(a.usage_count, 10) || 0)
				if (usageDiff !== 0) {
					return usageDiff
				}
				return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' })
			})
		},
		async deleteTemplate(t, event) {
			event.stopPropagation();
			const confirmed = await this.openConfirm({
				title: this.$texts.entry.deleteTemplateTitle(),
				message: this.$texts.entry.deleteTemplateMessage(),
				confirmLabel: this.$texts.entry.deleteTemplateConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) return;
			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/templates/${t.id}`));
				showToast(this.$texts.entry.templateDeleted());
				this.fetchTemplates();
			} catch (e) {
				showRequestError(e, this.$texts.entry.templateDeleteError(), 'Failed to delete template');
			}
		},
		closeTemplatePopover() {
			this.templatePopoverKey++;
			document.body.click();
		},
		onEntrySaved() {
			this.fetchProjectData();
			this.fetchTemplates();
		},
		updateDateRangeFilters() {
			const now = new Date();
			if (this.filters.timeRange === 'currentMonth') {
				const start = new Date(now.getFullYear(), now.getMonth(), 1);
				this.filters.dateFrom = Math.floor(start.getTime() / 1000);
				this.filters.dateTo = null;
			} else if (this.filters.timeRange === 'lastMonth') {
				const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
				const end = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59);
				this.filters.dateFrom = Math.floor(start.getTime() / 1000);
				this.filters.dateTo = Math.floor(end.getTime() / 1000);
			} else if (this.filters.timeRange === 'last30Days') {
				const start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 30);
				this.filters.dateFrom = Math.floor(start.getTime() / 1000);
				this.filters.dateTo = null;
			} else if (this.filters.timeRange === 'currentYear') {
				const start = new Date(now.getFullYear(), 0, 1);
				this.filters.dateFrom = Math.floor(start.getTime() / 1000);
				this.filters.dateTo = null;
			} else if (this.filters.timeRange === 'lastYear') {
				const start = new Date(now.getFullYear() - 1, 0, 1);
				const end = new Date(now.getFullYear() - 1, 11, 31, 23, 59, 59);
				this.filters.dateFrom = Math.floor(start.getTime() / 1000);
				this.filters.dateTo = Math.floor(end.getTime() / 1000);
			} else {
				this.filters.dateFrom = null;
				this.filters.dateTo = null;
			}
		},
		buildEntryQueryParams(includePagination = true) {
			this.updateDateRangeFilters();

			let isSettled = 0;
			if (this.filters.status === 'settled') isSettled = 1;
			if (this.filters.status === 'all') isSettled = null;
			let isRecurring = null;
			if (this.filters.recurring === 'true') isRecurring = true;
			if (this.filters.recurring === 'false') isRecurring = false;

			let isSubscription = null;
			let isFixedCost = null;
			let isChildRelated = null;
			let isImportant = null;
			let needsReview = null;
			let isTaxRelevant = null;
			let hasReminder = this.filters.hasReminder === 'true' ? true : null;
			let hasAttachment = null;
			if (this.filters.hasAttachment === 'true') hasAttachment = true;
			if (this.filters.hasAttachment === 'false') hasAttachment = false;
			if (this.filters.tags === 'subscription') isSubscription = true;
			if (this.filters.tags === 'taxRelevant') isTaxRelevant = true;
			if (this.filters.tags === 'fixedCost') isFixedCost = true;
			if (this.filters.tags === 'childRelated') isChildRelated = true;
			if (this.filters.tags === 'important') isImportant = true;
			if (this.filters.tags === 'review') needsReview = true;
			if (this.filters.tags === 'reminder') hasReminder = true;

			const params = {
				projectId: this.projectId,
				isSettled: isSettled,
				isRecurring: isRecurring,
				isSubscription: isSubscription,
				isFixedCost: isFixedCost,
				isChildRelated: isChildRelated,
				isImportant: isImportant,
				needsReview: needsReview,
				isTaxRelevant: isTaxRelevant,
				hasReminder: hasReminder,
				hasAttachment: hasAttachment,
				hashtagId: this.filters.hashtagId,
				search: this.filters.search,
				type: this.filters.type,
				categoryId: this.filters.categoryId,
				paymentPartnerId: this.filters.paymentPartnerId,
				dateFrom: this.filters.dateFrom,
				dateTo: this.filters.dateTo,
				sortBy: this.sortBy,
				sortDir: this.sortDir
			};

			if (includePagination) {
				params.limit = this.activePagination.limit;
				params.offset = this.activePagination.offset;
			}

			return params;
		},
		async fetchActiveEntries() {
			const params = this.buildEntryQueryParams(true);
			try {
				const res = await axios.get(generateUrl('/apps/cobudget/api/entries'), { params });
				this.activeEntries = res.data.entries || [];
				this.activeDateGroups = res.data.dateGroups || null;
				this.activePagination.total = res.data.total || 0;
				if (Array.isArray(res.data.lookups?.hashtags)) {
					this.hashtags = res.data.lookups.hashtags.sort((a, b) => String(a.displayName || a.name || '').localeCompare(String(b.displayName || b.name || ''), undefined, { sensitivity: 'base' }))
				}
			} catch (e) {
				showRequestError(e, this.$texts.areaDetail.entriesLoadError(), 'Failed to fetch project entries')
			}
		},
		async exportEntries() {
			if (this.isExporting) {
				return
			}
			this.isExporting = true
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/entries/export'), {
					params: this.buildEntryQueryParams(false),
					responseType: 'blob',
				})
				downloadBlobResponse(response, 'cobudget-zahlungen.csv')
				showToast(this.$texts.common.exportCsvCreated())
			} catch (e) {
				showRequestError(e, this.$texts.common.exportCsvError(), 'Failed to export project entries')
			} finally {
				this.isExporting = false
			}
		},
		onFiltersUpdate(newFilters) {
			this.filters = newFilters;
			this.activePagination.offset = 0;
			this.fetchActiveEntries();
		},
		resetFilters() {
			this.filters = {
				search: '',
				type: 'all',
				status: 'active',
				categoryId: null,
				paymentPartnerId: null,
				dateFrom: null,
				dateTo: null,
				timeRange: 'all',
				recurring: 'all',
				tags: 'all',
				hashtagId: null,
				hasReminder: 'all',
				hasAttachment: 'all'
			};
			this.activePagination.offset = 0;
			this.fetchActiveEntries();
		},
		toggleSort(col) {
			if (this.sortBy === col) {
				this.sortDir = this.sortDir === 'desc' ? 'asc' : 'desc';
			} else {
				this.sortBy = col;
				this.sortDir = 'desc';
			}
			this.activePagination.offset = 0;
			this.fetchActiveEntries();
		},
		prevActivePage() {
			if (this.activePagination.offset > 0) {
				this.activePagination.offset = Math.max(0, this.activePagination.offset - this.activePagination.limit);
				this.fetchActiveEntries();
			}
		},
		nextActivePage() {
			if (this.activePagination.offset + this.activePagination.limit < this.activePagination.total) {
				this.activePagination.offset += this.activePagination.limit;
				this.fetchActiveEntries();
			}
		},
		getMemberName(userId) {
			if (!this.project || !this.project.members) return userId
			const member = this.project.members.find(m => m.id === userId)
			return member ? member.displayName : userId
		},
		getProjectName(id) {
			if (this.project && String(this.project.id) === String(id)) {
				return this.project.name
			}
			return id ? `ID: ${id}` : ''
		},
		getProjectTagStyle(id) {
			if (!id || !this.project || String(this.project.id) !== String(id)) {
				return {}
			}

			let hexcolor = this.project.color || '#0082c9'
			hexcolor = hexcolor.replace('#', '')
			if (hexcolor.length === 3) {
				hexcolor = hexcolor.split('').map(c => c + c).join('')
			}
			const r = parseInt(hexcolor.substr(0, 2), 16)
			const g = parseInt(hexcolor.substr(2, 2), 16)
			const b = parseInt(hexcolor.substr(4, 2), 16)
			const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000
			let textR = r
			let textG = g
			let textB = b

			if (yiq > 180) {
				textR = Math.floor(r * 0.55)
				textG = Math.floor(g * 0.55)
				textB = Math.floor(b * 0.55)
			}

			return {
				backgroundColor: `rgba(${r}, ${g}, ${b}, 0.12)`,
				color: `rgb(${textR}, ${textG}, ${textB})`,
				border: `1px solid rgb(${textR}, ${textG}, ${textB})`
			}
		},
		formatDate(timestamp) {
			if (!timestamp) return '-'
			return new Date(timestamp * 1000).toLocaleDateString()
		},
		formatDateTime(timestamp) {
			if (!timestamp) return '-'
			return new Date(timestamp * 1000).toLocaleString(undefined, {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit'
			})
		},
		formatCurrency(val) {
			return this.$formatMoney(val)
		},
		formatSharePercent(shareBasisPoints) {
			const value = Math.round((parseInt(shareBasisPoints, 10) || 0) / 100)
			return value.toLocaleString(undefined, { maximumFractionDigits: 0 })
		},
		settlementEntryCountLabel(count) {
			const normalizedCount = parseInt(count || 0, 10)
			return this.$texts.areaDetail.entryCount(normalizedCount)
		},
		balanceStatusText(balance, currency) {
			const amount = parseFloat(balance || 0)
			if (amount > 0) {
				return this.$texts.areaDetail.getsBack(this.$formatMoney(amount, currency))
			}
			if (amount < 0) {
				return this.$texts.areaDetail.owes(this.$formatMoney(Math.abs(amount), currency))
			}
			return this.$texts.areaDetail.balanced()
		},
		projectDashboardMetric(key) {
			const fallback = {
				activeTotal: 0,
				activePersonal: 0,
				allTotal: 0,
				allPersonal: 0,
				activeCount: 0,
				allCount: 0
			}

			return {
				...fallback,
				...(this.project?.dashboard?.[key] || {})
			}
		},
		hasProjectDashboardMetric(key) {
			const metric = this.projectDashboardMetric(key)
			return parseInt(metric.activeCount || 0, 10) > 0
		},
		projectDashboardCountLabel(count) {
			const normalizedCount = parseInt(count || 0, 10)
			return this.$texts.areaDetail.entryCount(normalizedCount)
		},
		projectDashboardValueClass(value, mode) {
			if (mode === 'positive') {
				return 'positive'
			}
			if (mode === 'negative') {
				return 'negative'
			}

			return parseFloat(value || 0) >= 0 ? 'positive' : 'negative'
		},
		formatProjectDashboardAmount(value, mode) {
			const amount = parseFloat(value || 0)
			if (mode === 'positive') {
				return this.$formatSignedMoney(Math.abs(amount))
			}
			if (mode === 'negative') {
				return this.$formatMoney(-Math.abs(amount))
			}

			return this.$formatSignedMoney(amount)
		},
		projectDashboardTooltip(metric, mode) {
			return [
				this.$texts.areaDetail.personalOpenShare(this.formatProjectDashboardAmount(metric.activePersonal, mode)),
				this.$texts.areaDetail.areaTotalIncludingSettled(this.formatProjectDashboardAmount(metric.allTotal, mode)),
				this.$texts.areaDetail.personalShareIncludingSettled(this.formatProjectDashboardAmount(metric.allPersonal, mode))
			].join('\n')
		},
		onRowClick(entry) {
			if (entry.is_settled) {
				showToast(this.$texts.areaDetail.entrySettledInfo(), 'info');
			} else {
				this.editEntry(entry);
			}
		},
		openAddModal(options = {}) {
			const safeOptions = (options instanceof Event) ? {} : options;
			const defaultType = safeOptions.defaultType || (this.filters.type === 'income' ? 'income' : 'expense');
			this.$emit('open-add-modal', { projectId: this.projectId, defaultType, ...safeOptions });
		},
		editEntry(entry) {
			this.$emit('open-add-modal', { entry, projectId: this.projectId, isFuture: false })
		},
		duplicateEntry(entry) {
			this.$emit('open-add-modal', { entryToDuplicate: entry, projectId: this.projectId, isFuture: false })
		},
		async deleteEntry(entry) {
			const confirmed = await this.openConfirm({
				title: this.$texts.entry.deleteEntryTitle(),
				message: this.$texts.entry.deleteEntryMessage(),
				confirmLabel: this.$texts.entry.deleteEntry(),
				confirmVariant: 'danger'
			});
			if (confirmed) {
				try {
					await axios.delete(generateUrl(`/apps/cobudget/api/entries/${entry.id}`))
					showToast(this.$texts.entry.entryDeleted());
					window.dispatchEvent(new CustomEvent('cobudget-data-changed'));
					this.fetchProjectData()
				} catch (error) {
					showRequestError(error, this.$texts.entry.entryDeleteError(), 'Failed to delete entry')
				}
			}
		},
		settleUp() {
			if (!this.canSettleProject) {
				return;
			}
			this.showSettleConfirm = true;
		},
		async confirmSettleUp() {
			if (!this.canSettleProject) {
				this.showSettleConfirm = false;
				return;
			}
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/settle`))
				this.showSettleConfirm = false;
				showToast(this.$texts.areaDetail.areaSettled());
				this.$emit('refresh-projects');
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'));
				this.fetchProjectData()
			} catch (e) {
				showRequestError(e, this.$texts.areaDetail.settleError(), 'Failed to settle up')
			}
		},
		toggleShowSettled() {
			this.filters.status = this.filters.status === 'settled' ? 'active' : 'settled';
			if (this.$refs.tableFilters) {
				this.$refs.tableFilters.localFilters.status = this.filters.status;
			}
			this.activePagination.offset = 0;
			this.fetchActiveEntries();
		}
	}
}
</script>

<style scoped>
.recurring-icon,
.reminder-icon {
	color: var(--color-text-maxcontrast, #888);
	margin-right: 4px;
	vertical-align: middle;
	display: inline-block;
}

.project-detail {
	width: 100%;
}

.detail-header {
	margin-bottom: 0px;
}

.filter-toggle-btn {
	margin-right: 4px;
}

.filter-popover-content {
	padding: 8px;
}

:deep(.v-popper__arrow-container),
:deep(.v-popper__arrow),
:deep(.popover__arrow) {
	display: none !important;
}

.section {
	padding: 0;
	margin-bottom: 30px;
}

.section h3 {
	font-size: var(--cobudget-font-md);
	font-weight: 600;
	margin: 0 0 12px 0;
	color: var(--color-main-text, #222);
}

.section-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 12px;
}

.section-header h3 {
	margin: 0;
}

/* Dashboard cards */
.stats-row {
	display: flex;
	gap: 20px;
	margin-bottom: 24px;
	flex-wrap: nowrap;
	padding: 2px 2px 12px;
}

.stat-card {
	box-sizing: border-box;
	flex: 0 0 300px;
	width: 300px;
	min-width: 300px;
	background: var(--cobudget-page-background, #fff);
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.stat-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 12px;
}

.stat-title-group {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
}

.stat-icon {
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--cobudget-surface-muted, #f5f5f5);
	border-radius: 50%;
}

.stat-label {
	font-size: var(--cobudget-font-sm);
	color: var(--color-text-maxcontrast, #888);
	text-transform: uppercase;
	letter-spacing: 0.5px;
	font-weight: 600;
}

.stat-value {
	font-size: var(--cobudget-font-lg);
	font-weight: 700;
	text-align: right;
	white-space: nowrap;
	cursor: help;
}

.stat-value.positive {
	color: #107C41;
}

.stat-value.negative {
	color: var(--cobudget-error);
}

.stat-sub-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
	border-top: 1px solid var(--cobudget-border, #eee);
	padding-top: 10px;
}

.stat-sub-line {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	font-size: var(--cobudget-font-sm);
	color: var(--color-text-maxcontrast, #777);
}

.stat-sub-line span:last-child {
	white-space: nowrap;
}

.owner-badge {
	font-size: var(--cobudget-font-xs);
	padding: 2px 8px;
	border-radius: 10px;
	background: var(--color-primary-element-light, #e0f0ff);
	color: var(--color-primary, #0082c9);
	font-weight: 600;
}

/* Balances */
.balances-grid {
	display: flex;
	flex-wrap: nowrap;
	gap: 12px;
	padding: 2px 2px 12px;
}

.balance-card {
	box-sizing: border-box;
	flex: 0 0 280px;
	width: 280px;
	min-width: 280px;
	padding: 14px;
	border-radius: var(--border-radius-large, 8px);
	border: 1px solid var(--cobudget-border, #ddd);
	background: var(--cobudget-page-background, #fff);
}

.balance-card-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 10px;
	margin-bottom: 6px;
}

.balance-card.positive {

}

.balance-card.negative {

}

.balance-card.neutral {

}

.balance-name {
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
	min-width: 0;
}

.balance-name span {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.balance-actions {
	display: inline-flex;
	align-items: center;
	justify-content: flex-end;
	min-width: 32px;
}
.balance-detail {
	font-size: var(--cobudget-font-sm);
	color: var(--color-text-maxcontrast, #888);
}

.balance-amount {
	margin-top: 8px;
	font-weight: 600;
	font-size: var(--cobudget-font-base);
}

.positive .balance-amount {
	color: #107C41;
}

.negative .balance-amount {
	color: var(--cobudget-error);
}

.repayments-section,
.settlements-section {
	max-width: 900px;
}

.repayment-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.repayment-list.compact {
	gap: 6px;
}

.repayment-row {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr) auto;
	align-items: center;
	gap: 10px;
	padding: 10px 12px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.repayment-person {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: 600;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.repayment-arrow,
.settlement-summary-meta,
.settlement-meta,
.settlement-empty,
.settlement-preview-note {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #777)) !important;
}

.repayment-amount {
	white-space: nowrap;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.settlement-history-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.settlement-history-card {
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	overflow: hidden;
}

.settlement-history-card summary {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 12px;
	padding: 12px 14px;
	cursor: pointer;
	font-weight: 600;
}

.settlement-history-content {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 0 14px 14px;
}

.settlement-subsection h4,
.settlement-preview h3 {
	margin: 0 0 8px;
	font-size: var(--cobudget-font-base);
	font-weight: 700;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.settlement-balance-grid {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.settlement-balance-row {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	padding: 8px 0;
	border-bottom: 1px solid var(--cobudget-border, #eee);
}

.settlement-balance-row:last-child {
	border-bottom: none;
}

.settlement-balance-row span:last-child {
	text-align: right;
	white-space: nowrap;
}

.settlement-preview {
	margin-top: 14px;
	text-align: left;
}

.settlement-preview-note,
.settlement-empty {
	margin: 10px 0 0;
}

/* Buttons */
.btn-primary {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 10px 18px;
	background: var(--color-primary, #0082c9);
	color: #fff;
	border: none;
	border-radius: var(--border-radius-large, 6px);
	font-size: var(--cobudget-font-base);
	font-weight: 600;
	cursor: pointer;
}

.btn-primary:hover:not(:disabled) {
	background: var(--color-primary-hover, #006aa3);
}

.btn-primary:disabled {
	opacity: 0.5;
	cursor: default;
}

.btn-secondary {
	padding: 8px 14px;
	background: var(--cobudget-surface-muted, var(--color-background-dark, #eee));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border: none;
	border-radius: var(--border-radius, 4px);
	cursor: pointer;
	font-size: var(--cobudget-font-compact);
}

.btn-small {
	padding: 6px 12px;
	font-size: var(--cobudget-font-compact);
}

.btn-settle {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	justify-content: center;
	padding: 10px 18px;
	background: var(--color-warning, #e69d00);
	color: #000;
	border: none;
	border-radius: var(--border-radius-large, 6px);
	font-size: var(--cobudget-font-base);
	font-weight: 700;
	cursor: pointer;
	margin-left: 10px;
}

.btn-settle:hover {
	opacity: 0.9;
}

/* Table */
.table-container {
	background: var(--cobudget-page-background, #fff);
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	overflow-x: auto;
}

.data-table {
	width: 100%;
	min-width: 800px;
	border-collapse: collapse;
	table-layout: fixed;
}

.data-table th {
	text-align: left;
	padding: 12px 10px;
	background: var(--cobudget-surface-muted, #f9f9f9);
	font-weight: 600;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	font-size: var(--cobudget-font-compact);
	text-transform: uppercase;
	letter-spacing: 0.5px;
	border-bottom: 2px solid var(--cobudget-border, #ddd);
}

th.col-date {
	width: 100px;
}

th.col-actions {
	width: 50px;
}

th.col-user {
	width: 200px;
}

th.col-category {
	width: 200px;
}

th.col-paymentPartner {
	width: 200px;
}

th.col-amount {
	width: 180px;
	text-align: right;
	padding-right: 1px;
}

th.col-desc {
	width: auto;
}

/* takes remaining space */

.data-table td {
	padding-top: 0px;
	padding-bottom: 0px;
	padding-left: 10px;
	padding-right: 5px;
	border-bottom: 1px solid var(--cobudget-border, #ddd);
	color: var(--cobudget-text, var(--color-main-text, #222));
	vertical-align: middle;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.data-table tbody tr.clickable-row td {
	cursor: pointer;
}

.sortable {
	cursor: pointer;
	user-select: none;
}

.sortable:hover {
	background: #eee;
}

.sort-icon {
	display: inline-block;
	margin-left: 4px;
	font-size: var(--cobudget-font-sm);
}

.pagination-footer {
	display: flex;
	justify-content: space-between;
	align-items: center;
  padding-top: 10px;
	background: var(--cobudget-page-background, #fff);
}

.pagination-footer--single {
	justify-content: center;
}

.btn-page {
	min-width: 0 !important;
	border-color: transparent !important;
  background: var(--cobudget-surface-muted) !important;
	box-shadow: none !important;
	color: var(--cobudget-text) !important;
}

.btn-page:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.btn-page:hover:not(:disabled),
.btn-page:focus {
	background: var(--cobudget-surface-strong) !important;
	border-color: transparent !important;
	box-shadow: none !important;
	outline: none !important;
}

.btn-page-next {
	gap: 6px;
}

.btn-page :deep(.button-vue__wrapper),
.btn-page :deep(.button-vue__text) {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	gap: 8px;
	line-height: 1 !important;
}

.pagination-label {
	display: inline-flex;
	align-items: center;
	white-space: nowrap;
}

.pagination-icon-right {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
	width: 20px;
	height: 20px;
	line-height: 0;
	margin-left: 4px;
}

.pagination-icon-right :deep(.material-design-icon),
.pagination-icon-right :deep(.material-design-icon__svg) {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	line-height: 1;
	bottom: auto;
}

.page-info {
	font-size: var(--cobudget-font-compact);
	color: var(--color-text-maxcontrast, #888);
}

.desc-text {
	font-weight: 500;
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.category-tag {
	display: inline-block;
	margin-top: 6px;
	padding: 2px 8px;
	font-size: var(--cobudget-font-xs);
	background: var(--color-background-dark, #eee);
	color: var(--color-text-maxcontrast, #666);
	border-radius: 10px;
}

.data-table tbody tr:last-child td {
	border-bottom: none;
}

.clickable-row {
	cursor: pointer;
	transition: background-color 0.15s ease;
}

.clickable-row:hover {
	background: var(--cobudget-surface-muted, #f6f6f6);
}

.date-cell {
	color: var(--color-text-maxcontrast, #888);
	white-space: nowrap;
}

.desc-cell {
	font-weight: 500;
}

.amount-cell {
	text-align: right;
	font-weight: 600;
	white-space: nowrap;
	padding-right: 0px !important;
}

.amount-wrapper {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 6px;
}

.shared-icon {
	display: inline-flex;
	align-items: center;
	color: var(--color-text-maxcontrast, #888);
}

.amount-text {
	display: inline-block;
	padding: 4px 10px;
	border-radius: 6px;
	font-weight: 600;
	font-family: monospace;
	font-size: var(--cobudget-font-ui);
}

.bg-expense .amount-text {
	background: var(--cobudget-error-light);
	color: var(--cobudget-error);
}

.bg-income .amount-text {
	background: #eeffee;
	color: #008800;
}

.data-table.archived {
	opacity: 0.6;
}

.empty-state {
	text-align: center;
	padding: 40px 20px;
	color: var(--color-text-maxcontrast, #888);
	background: var(--cobudget-page-background, #fff);
	border-radius: var(--border-radius-large, 8px);
	border: 1px solid var(--cobudget-border, #ddd);
}

.loading {
	padding: 40px;
	text-align: center;
	color: var(--color-text-maxcontrast, #888);
}

.edit-btn {
	background: transparent;
	border: 1px solid var(--cobudget-border, #ccc);
	color: var(--color-text-maxcontrast, #888);
	cursor: pointer;
	padding: 8px;
	border-radius: var(--border-radius-large, 6px);
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.edit-btn:hover {
	background: var(--cobudget-surface-muted, #eee);
	color: var(--color-main-text, #222);
}

.member-avatar {
	margin-right: 12px;
}

.inline-avatar {
	margin-right: 6px;
}

.balance-name {
	display: flex;
	align-items: center;
}

.modal-form-content {
	padding: 20px;
}

.project-color-dot {}

.mobile-only {
	display: none !important;
}

@media (max-width: 768px) {

  .stats-row {
    gap: 10px;
    padding-bottom: 12px;
    margin-bottom: 20px;
    margin-top: 20px;
  }

	.back-btn {
		display: none !important;
	}

	.project-export-header-btn,
	.project-filter-header-btn,
	.project-settings-header-btn,
	.project-settle-header-btn {
		display: none !important;
	}

	.btn-settle-header .btn-text,
	.edit-project-btn .btn-text {
		display: none !important;
	}

	.header-actions {
		gap: 4px;
	}

	.table-container {
		border: none;
		background: transparent;
		box-shadow: none;
	}

	.balances-grid {
		gap: 10px;
		padding-bottom: 12px;
	}

	.balance-card {
		flex-basis: 280px;
		padding: 10px;
	}

	.balance-name {
		font-size: var(--cobudget-font-compact);
	}

	.balance-detail {
		font-size: var(--cobudget-font-xs);
	}

	.balance-amount {
		font-size: var(--cobudget-font-compact);
		margin-top: 6px;
	}

	.data-table {
		min-width: 100% !important;
		border: none;
	}

	.data-table thead {
		display: none;
	}

	.data-table,
	.data-table tbody,
	.data-table tr,
	.data-table td {
		display: block;
		width: 100%;
	}

	.data-table tr {
		display: grid;
		grid-template-columns: 1fr auto;
		grid-template-areas: 
			"desc amount"
			"desc actions";
		margin-bottom: 12px;
		border: 1px solid var(--cobudget-border, #ddd);
		border-radius: 8px;
		background: var(--cobudget-page-background, #fff);
		padding: 12px;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
		gap: 8px 12px;
	}

	.data-table td {
		display: block;
		padding: 0;
		border: none;
		text-align: left;
	}

	.data-table td::before {
		display: none !important;
	}

	.date-cell, .category-cell, .paymentPartner-cell, .user-cell {
		display: none !important;
	}

	.desc-cell {
		grid-area: desc;
		display: flex !important;
		flex-direction: column;
		gap: 4px;
	}

	.amount-cell {
		grid-area: amount;
		text-align: right;
		display: flex;
		justify-content: flex-end;
		align-items: flex-start;
		width: auto !important;
	}

	.actions-cell {
		grid-area: actions;
		display: flex;
		justify-content: flex-end;
		align-items: flex-end;
		justify-self: end;
		width: auto !important;
	}

	.mobile-only {
		display: flex !important;
	}
	
	.desktop-only {
		display: none !important;
	}

	.pagination-label {
		display: none;
	}

	.btn-page {
		min-width: var(--cobudget-icon-button-size) !important;
		width: var(--cobudget-icon-button-size) !important;
		padding-inline: 0 !important;
		justify-content: center;
	}

	.pagination-icon-right {
		margin-left: 0;
	}

	.repayment-row {
		grid-template-columns: minmax(0, 1fr) auto;
		gap: 4px 10px;
		padding: 10px;
	}

	.repayment-person {
		min-width: 0;
		max-width: 100%;
	}

	.repayment-arrow {
		grid-column: 1;
		font-size: var(--cobudget-font-xs);
	}

	.repayment-amount {
		grid-column: 2;
		grid-row: 1 / span 3;
		align-self: center;
		text-align: right;
	}

}

.desc-text .main-title {
	font-size: var(--cobudget-font-ui);
	font-weight: 600;
	color: var(--color-main-text, #222);
	word-break: break-word;
	white-space: normal;
}

.mobile-meta {
	flex-direction: column;
	gap: 6px;
}

.mobile-date {
	font-size: var(--cobudget-font-compact);
	color: var(--color-text-maxcontrast, #888);
}

.mobile-tags {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.mobile-tag {
	background: var(--color-background-dark, #eee);
	color: var(--color-text-maxcontrast, #666);
	padding: 2px 8px;
	border-radius: 12px;
	font-size: var(--cobudget-font-xs);
	font-weight: 600;
}
</style>

<style>
/* Unscoped Modal styling so it applies to Teleported elements */
.modal-backdrop {
	position: fixed;
	top: 0;
	left: 0;
	width: 100vw;
	height: 100vh;
	background: rgba(0, 0, 0, 0.4);
	display: flex;
	justify-content: center;
	align-items: center;
	z-index: 10000;
}

	.modal-content {
		background: var(--cobudget-page-background, #fff);
		border-radius: var(--border-radius-large, 8px);
		box-sizing: border-box;
		padding: 24px;
		width: 90%;
		max-width: 500px;
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
	}

.modal-header h2 {
	margin-top: 0;
	margin-bottom: 20px;
	font-size: var(--cobudget-font-xl);
}

.form-group {
	margin-bottom: 16px;
}

.form-group label {
	display: block;
	margin-bottom: 6px;
	font-weight: 600;
	font-size: var(--cobudget-font-compact);
	color: var(--color-main-text, #222);
}

.form-control {
	width: 100%;
	padding: 10px;
	border: 1px solid var(--cobudget-border, #ccc);
	border-radius: var(--border-radius, 4px);
	font-size: var(--cobudget-font-md);
	box-sizing: border-box;
}

.form-control:focus {
	border-color: var(--color-primary, #0082c9);
	outline: none;
}

.template-item {
	cursor: pointer;
	padding: 8px;
	border-radius: var(--border-radius);
	transition: background-color 0.1s ease-in-out;
}
.template-item:hover {
	background-color: var(--cobudget-surface-muted);
}
.template-title {
	font-weight: bold;
	cursor: pointer;
}
</style>
