<template>
	<div class="personal-dashboard">
		<AppPageHeader :title="pageTitle">
			<template #actions>
				<NcActions class="mobile-header-actions-menu">
					<NcActionButton :close-after-click="true" icon="icon-search" @click="showFilterPanel = true">
						{{ $texts.filters.search() }}
					</NcActionButton>
					<NcActionButton v-if="canExportEntries" :close-after-click="true" icon="icon-download" :disabled="isExporting" @click="exportEntries">
						{{ isExporting ? $texts.common.exportCsvBusy() : $texts.common.exportCsv() }}
					</NcActionButton>
				</NcActions>
				<NcButton v-if="canExportEntries" :aria-label="$texts.common.exportCsv()" :title="isExporting ? $texts.common.exportCsvBusy() : $texts.common.exportCsv()" variant="tertiary" class="filter-toggle-btn cobudget-toolbar-icon-button desktop-header-action" :disabled="isExporting" @click="exportEntries">
					<template #icon>
						<DownloadIcon :size="20" />
					</template>
				</NcButton>
				<NcPopover placement="bottom-end" class="desktop-header-action">
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
							:projects="projects"
							:paymentPartners="paymentPartners"
							:hashtags="hashtags"
							:initialFilters="filters"
							@update:filters="onFiltersUpdate"
						/>
					</div>
				</NcPopover>
				<div class="add-button-group" style="display: flex; border-radius: var(--border-radius, 3px); overflow: hidden;">
					<NcButton variant="primary" class="new-payment-main-button" @click="$emit('open-add-modal', { isFuture: filters.tags === 'future', defaultType: defaultEntryType })" :aria-label="filters.tags === 'future' ? $texts.entry.planPayment() : $texts.areaDetail.newPayment()"
						:title="filters.tags === 'future' ? $texts.entry.planPayment() : $texts.areaDetail.newPayment()" style="border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: 1px solid rgba(255, 255, 255, 0.72); margin-right: 0;">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						<span class="btn-text">{{ filters.tags === 'future' ? $texts.entry.planPayment() : $texts.areaDetail.newPayment() }}</span>
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
							<div v-for="t in templates" :key="t.id" class="template-item action-item" @click="$emit('open-add-modal', { templateToLoad: t }); closeTemplatePopover()" style="display: flex; justify-content: space-between; align-items: center;">
								<div class="template-title">{{ t.name }}</div>
								<NcButton variant="tertiary" @click.stop="deleteTemplate(t, $event)" :aria-label="$texts.entry.deleteTemplateTitle()" :title="$texts.entry.deleteTemplateTitle()" style="margin-left: 10px; padding: 4px;">
									<template #icon>
										<DeleteIcon :size="16" />
									</template>
								</NcButton>
							</div>
							<hr v-if="templates.length > 0" style="margin: 8px 0; border: none; border-top: 1px solid var(--cobudget-border);">
							<div class="template-item action-item" @click="$emit('open-add-modal', { isTemplateMode: true, defaultType: defaultEntryType }); closeTemplatePopover()">
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
						:projects="projects"
						:paymentPartners="paymentPartners"
						:hashtags="hashtags"
						:initialFilters="filters"
						@update:filters="onFiltersUpdate"
					/>
				</section>
			</div>
		</Teleport>

		<DraggableScroller class="stats-row">
			<div
				v-if="showBudgetCard"
				class="stat-card budget-card clickable-budget-card"
				role="link"
				tabindex="0"
				:aria-label="$texts.budgetGoals.title()"
				@click="openBudgetGoals"
				@keydown.enter.prevent="openBudgetGoals"
				@keydown.space.prevent="openBudgetGoals">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><WalletIcon :size="20" fillColor="#2563eb" /></div>
						<span class="stat-label">{{ $texts.dashboard.budgetGoals() }}</span>
					</div>
					<span class="stat-value" :class="budgetStatusClass">{{ budgetStatusLabel }}</span>
				</div>
				<div class="budget-summary-list">
					<div v-for="goal in visibleBudgetGoals" :key="goal.id" class="budget-summary-item">
						<div class="budget-summary-line">
							<span>{{ goal.name }}</span>
							<strong>{{ budgetBufferLabel(goal) }}</strong>
						</div>
						<div class="budget-summary-bar" :class="`status-${goal.evaluation.status}`">
							<span :style="{ width: budgetProgressWidth(goal) }"></span>
						</div>
					</div>
				</div>
			</div>

			<div class="stat-card income-card" v-if="showIncomeCard">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><TrendingUpIcon :size="20" fillColor="#107C41" /></div>
						<span class="stat-label">{{ $texts.dashboard.income() }}</span>
					</div>
					<span class="stat-value positive">{{ formatPositiveMetric(totalIncome) }}</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatPositiveMetric(averageIncome) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatPositiveMetric(currentMonthIncome) }}</span>
					</div>
					<div class="stat-sub-line" v-if="$enableFuturePayments">
						<span>{{ $texts.dashboard.planned30Days() }}</span>
						<span>{{ formatPositiveMetric(futureIncome30Days) }}</span>
					</div>
				</div>
			</div>
			
			<div class="stat-card expense-card">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><TrendingDownIcon :size="20" fillColor="var(--cobudget-error)" /></div>
						<span class="stat-label">{{ $texts.dashboard.expenses() }}</span>
					</div>
					<span class="stat-value negative">{{ formatNegativeMetric(totalExpense) }}</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatNegativeMetric(averageExpense) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatNegativeMetric(currentMonthExpense) }}</span>
					</div>
					<div class="stat-sub-line" v-if="$enableFuturePayments">
						<span>{{ $texts.dashboard.planned30Days() }}</span>
						<span>{{ formatNegativeMetric(futureExpense30Days) }}</span>
					</div>
				</div>
			</div>
			
			<div class="stat-card total-card" v-if="$enableIncomes">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><WalletIcon :size="20" fillColor="#0082c9" /></div>
						<span class="stat-label">{{ $texts.dashboard.balance() }}</span>
					</div>
					<span class="stat-value" :class="balance >= 0 ? 'positive' : 'negative'">
						{{ formatSignedMetric(balance) }}
					</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageBalance) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthBalance) }}</span>
					</div>
					<div class="stat-sub-line" v-if="$enableFuturePayments">
						<span>{{ $texts.dashboard.planned30Days() }}</span>
						<span>{{ formatSignedMetric(futureBalance30Days) }}</span>
					</div>
				</div>
			</div>

			<div class="stat-card future-card" v-if="showFutureCard">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><CalendarSyncIcon :size="20" fillColor="#2563eb" /></div>
						<span class="stat-label">{{ $texts.dashboard.planned() }}</span>
					</div>
					<span class="stat-value" :class="totalFutureBalance >= 0 ? 'positive' : 'negative'">
						{{ formatSignedMetric(totalFutureBalance) }}
					</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.incomeColon() }}</span>
						<span>{{ formatPositiveMetric(totalFutureIncome) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.expensesColon() }}</span>
						<span>{{ formatNegativeMetric(totalFutureExpense) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.next30Days() }}</span>
						<span>{{ formatSignedMetric(futureBalance30Days) }}</span>
					</div>
				</div>
			</div>

			<div class="stat-card important-card" v-if="showImportantCard">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><StarIcon :size="20" fillColor="#ffc92b" /></div>
						<span class="stat-label">{{ $texts.labels.important() }}</span>
					</div>
					<span class="stat-value" :class="signedMetricClass(totalImportantPayments)">{{ formatSignedMetric(totalImportantPayments) }}</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageImportantPayments) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthImportantPayments) }}</span>
					</div>
					<div class="stat-sub-line" v-if="$enableFuturePayments">
						<span>{{ $texts.dashboard.planned30Days() }}</span>
						<span>{{ formatSignedMetric(futureImportantPayments30Days) }}</span>
					</div>
				</div>
			</div>

			<div class="stat-card review-card" v-if="showReviewCard">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><ClipboardCheckIcon :size="20" fillColor="var(--cobudget-error)" /></div>
						<span class="stat-label">{{ $texts.labels.review() }}</span>
					</div>
					<span class="stat-value" :class="signedMetricClass(totalReviewPayments)">{{ formatSignedMetric(totalReviewPayments) }}</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageReviewPayments) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthReviewPayments) }}</span>
					</div>
					<div class="stat-sub-line" v-if="$enableFuturePayments">
						<span>{{ $texts.dashboard.planned30Days() }}</span>
						<span>{{ formatSignedMetric(futureReviewPayments30Days) }}</span>
					</div>
				</div>
			</div>
			
			<div class="stat-card fixed-costs-card" v-if="showFixedCostsCard">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><LockIcon :size="20" fillColor="#e67e22" /></div>
						<span class="stat-label">{{ $texts.labels.fixedCosts() }}</span>
					</div>
					<span class="stat-value negative">{{ formatNegativeMetric(totalFixedCosts) }}</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatNegativeMetric(averageFixedCosts) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatNegativeMetric(currentMonthFixedCosts) }}</span>
					</div>
					<div class="stat-sub-line" v-if="$enableFuturePayments">
						<span>{{ $texts.dashboard.planned30Days() }}</span>
						<span>{{ formatNegativeMetric(futureFixedCosts30Days) }}</span>
					</div>
				</div>
			</div>

			<div class="stat-card child-card" v-if="showChildRelatedCard">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><AccountChildIcon :size="20" fillColor="#0f766e" /></div>
						<span class="stat-label">{{ $texts.labels.children() }}</span>
					</div>
					<span class="stat-value" :class="signedMetricClass(totalChildRelated)">{{ formatSignedMetric(totalChildRelated) }}</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageChildRelated) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthChildRelated) }}</span>
					</div>
					<div class="stat-sub-line" v-if="$enableFuturePayments">
						<span>{{ $texts.dashboard.planned30Days() }}</span>
						<span>{{ formatSignedMetric(futureChildRelated30Days) }}</span>
					</div>
				</div>
			</div>

			<div class="stat-card abos-card" v-if="showSubscriptionsCard">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><SyncIcon :size="20" fillColor="#8e44ad" /></div>
						<span class="stat-label">{{ $texts.labels.subscription() }}</span>
					</div>
					<span class="stat-value negative">{{ formatNegativeMetric(totalSubscriptions) }}</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatNegativeMetric(averageSubscriptions) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatNegativeMetric(currentMonthSubscriptions) }}</span>
					</div>
					<div class="stat-sub-line" v-if="$enableFuturePayments">
						<span>{{ $texts.dashboard.planned30Days() }}</span>
						<span>{{ formatNegativeMetric(futureSubscriptions30Days) }}</span>
					</div>
				</div>
			</div>

			<div class="stat-card tax-card" v-if="showTaxRelevantCard">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><ReceiptTextCheckOutlineIcon :size="20" fillColor="#0082c9" /></div>
						<span class="stat-label">{{ $texts.labels.taxRelevant() }}</span>
					</div>
					<span class="stat-value" :class="signedMetricClass(totalTaxRelevant)">{{ formatSignedMetric(totalTaxRelevant) }}</span>
				</div>
				<div class="stat-sub-info">
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageTaxRelevant) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthTaxRelevant) }}</span>
					</div>
					<div class="stat-sub-line" v-if="$enableFuturePayments">
						<span>{{ $texts.dashboard.planned30Days() }}</span>
						<span>{{ formatSignedMetric(futureTaxRelevant30Days) }}</span>
					</div>
				</div>
			</div>
		</DraggableScroller>

		<div class="recent-activities">

			<EntryTable
				v-if="entries.length > 0"
				mode="personal"
				:entries="entries"
				:date-groups="dateGroups"
				:currency="$currency"
				:date-label="filters.tags === 'future' ? $texts.entry.tablePlannedPayment() : $texts.entry.tableDate()"
				:sort-by="sortBy"
				:sort-dir="sortDir"
				:enable-fixed-costs="$enableFixedCosts"
				:enable-subscriptions="$enableSubscriptions"
				:enable-child-related="$enableChildRelated"
				:enable-important-payments="$enableImportantPayments"
				:enable-review-payments="$enableReviewPayments"
				:enable-tax-relevant="$enableTaxRelevant"
				:amount-resolver="getEntryPersonalAmount"
				:project-name-resolver="getProjectName"
				:project-style-resolver="getProjectTagStyle"
				:is-shared-project-resolver="isSharedProject"
				@sort="toggleSort"
				@row-click="onRowClick"
				@edit="editEntry"
				@duplicate="duplicateEntry"
				@history="openEntryHistory"
				@delete="deleteEntry">
				<template #pagination>
					<div class="pagination-footer" :class="{ 'pagination-footer--single': pagination.total <= pagination.limit }">
						<NcButton v-if="pagination.total > pagination.limit" variant="secondary" class="btn-page cobudget-toolbar-text-button" :style="{ visibility: pagination.offset > 0 ? 'visible' : 'hidden' }" @click="prevPage">
							<template #icon>
								<ArrowLeftIcon :size="20" />
							</template>
							<span class="pagination-label">{{ $texts.common.previous() }}</span>
						</NcButton>
						<span class="page-info">{{ $texts.common.pageInfo(pagination.offset + 1, Math.min(pagination.offset + pagination.limit, pagination.total), pagination.total) }}</span>
						<NcButton v-if="pagination.total > pagination.limit" variant="secondary" class="btn-page btn-page-next cobudget-toolbar-text-button" :style="{ visibility: pagination.offset + pagination.limit < pagination.total ? 'visible' : 'hidden' }" @click="nextPage">
							<span class="pagination-label">{{ $texts.common.next() }}</span>
							<ArrowRightIcon class="pagination-icon-right" :size="20" />
						</NcButton>
					</div>
				</template>
			</EntryTable>
			<NcEmptyContent
				v-else
				class="empty-content-state"
				:name="hasActiveFilters ? $texts.common.noFilteredEntriesTitle() : $texts.common.noEntriesTitle()"
				:description="hasActiveFilters ? $texts.common.noFilteredEntriesDescription() : $texts.common.noEntriesDescription()">
				<template #icon>
					<WalletIcon :size="64" />
				</template>
				<template #action>
					<NcButton v-if="hasActiveFilters" variant="secondary" @click="resetFilters">
						{{ $texts.common.resetFilters() }}
					</NcButton>
					<NcButton v-else variant="primary" @click="$emit('open-add-modal', { isFuture: filters.tags === 'future', defaultType: defaultEntryType })">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ filters.tags === 'future' ? $texts.entry.planPayment() : $texts.areaDetail.newPayment() }}
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
			:show="!!confirmDialog"
			:title="confirmDialog ? confirmDialog.title : ''"
			:message="confirmDialog ? confirmDialog.message : ''"
			:confirm-label="confirmDialog ? confirmDialog.confirmLabel : ''"
			:confirm-variant="confirmDialog ? confirmDialog.confirmVariant : 'primary'"
			@confirm="resolveConfirm(true)"
			@cancel="resolveConfirm(false)" />
	</div>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import TrendingDownIcon from 'vue-material-design-icons/TrendingDown.vue'
import WalletIcon from 'vue-material-design-icons/Wallet.vue'
import SyncIcon from 'vue-material-design-icons/Sync.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import ClipboardCheckIcon from 'vue-material-design-icons/ClipboardCheck.vue'
import AccountChildIcon from 'vue-material-design-icons/AccountChild.vue'
import ReceiptTextCheckOutlineIcon from 'vue-material-design-icons/ReceiptTextCheckOutline.vue'
import CalendarSyncIcon from 'vue-material-design-icons/CalendarSync.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import TableFilters from '../components/TableFilters.vue'
import DraggableScroller from '../components/DraggableScroller.vue'
import ConfirmModal from '../components/ConfirmModal.vue'
import EntryTable from '../components/EntryTable.vue'
import EntryHistoryModal from '../components/EntryHistoryModal.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import { normalizeEntryPageSize, shouldIgnorePaginationKeydown } from '../services/pagination'
import { showRequestError, showToast } from '../services/notifications'
import { downloadBlobResponse } from '../services/downloads'

const emptyMetricGroup = () => ({
	income: 0,
	expense: 0,
	balance: 0,
	important: 0,
	review: 0,
	subscriptions: 0,
	fixedCosts: 0,
	childRelated: 0,
	taxRelevant: 0,
})

const emptyDashboardMetrics = () => ({
	total: emptyMetricGroup(),
	average: emptyMetricGroup(),
	currentMonth: emptyMetricGroup(),
	future: emptyMetricGroup(),
	future30Days: emptyMetricGroup(),
})

const emptyTagCounts = () => ({
	income: 0,
	future: 0,
	important: 0,
	review: 0,
	fixedCosts: 0,
	childRelated: 0,
	subscriptions: 0,
	taxRelevant: 0,
})

const normalizeMetricGroup = group => ({
	...emptyMetricGroup(),
	...(group || {}),
})

const normalizeDashboardMetrics = metrics => ({
	total: normalizeMetricGroup(metrics?.total),
	average: normalizeMetricGroup(metrics?.average),
	currentMonth: normalizeMetricGroup(metrics?.currentMonth),
	future: normalizeMetricGroup(metrics?.future),
	future30Days: normalizeMetricGroup(metrics?.future30Days),
})

const normalizeTagCounts = counts => {
	const values = counts && typeof counts === 'object' ? counts : {}
	return {
		income: parseInt(values.income || 0, 10),
		future: parseInt(values.future || 0, 10),
		important: parseInt(values.important || 0, 10),
		review: parseInt(values.review || 0, 10),
		fixedCosts: parseInt(values.fixedCosts || 0, 10),
		childRelated: parseInt(values.childRelated || 0, 10),
		subscriptions: parseInt(values.subscriptions || 0, 10),
		taxRelevant: parseInt(values.taxRelevant || 0, 10),
	}
}

export default {
	name: 'TransactionsView',
	components: {
		NcButton,
		NcActions,
		NcActionButton,
		NcEmptyContent,
		TableFilters,
		PlusIcon,
		MagnifyIcon,
		TrendingUpIcon,
		TrendingDownIcon,
		WalletIcon,
		SyncIcon,
		LockIcon,
		StarIcon,
		ClipboardCheckIcon,
		AccountChildIcon,
		ReceiptTextCheckOutlineIcon,
		CalendarSyncIcon,
		ChevronDownIcon,
		CloseIcon,
		DeleteIcon,
		DownloadIcon,
		ArrowLeftIcon,
		ArrowRightIcon,
		NcPopover,
		DraggableScroller,
		ConfirmModal,
		EntryTable,
		EntryHistoryModal,
		AppPageHeader,
	},
	data() {
		return {
			showFilterPanel: false,
			isExporting: false,
			userName: '',
			entries: [],
			dateGroups: null,
			dashboardMetrics: emptyDashboardMetrics(),
			dashboardTagCounts: emptyTagCounts(),
			budgetGoals: [],
			templates: [],
			projects: [],
			categories: [],
			paymentPartners: [],
			hashtags: [],
			entryHistoryOpen: false,
			entryHistoryLoading: false,
			entryHistoryRows: [],
			filters: {
				search: '',
				type: 'all',
				status: 'all',
				categoryId: null,
				projectId: null,
				paymentPartnerId: null,
				dateFrom: null,
				dateTo: null,
				timeRange: 'all',
				tags: 'all',
				hashtagId: null,
				hasReminder: 'all',
				hasAttachment: 'all'
			},
			sortBy: 'date',
			sortDir: 'desc',
			pagination: {
				limit: 25,
				offset: 0,
				total: 0
			},
			templatePopoverKey: 0,
			isInternalFilterChange: false,
			confirmDialog: null
		}
	},
	computed: {
		hasActiveFilters() {
			return this.filters.search !== '' || 
				   this.filters.type !== 'all' || 
				   this.filters.status !== 'all' ||
				   this.filters.categoryId !== null ||
				   this.filters.projectId !== null ||
				   this.filters.paymentPartnerId !== null ||
				   this.filters.hashtagId !== null ||
				   this.filters.timeRange !== 'all' ||
				   this.filters.tags !== 'all' ||
				   this.filters.hasReminder !== 'all' ||
				   this.filters.hasAttachment !== 'all';
		},
		dashboardTotalMetrics() {
			return normalizeMetricGroup(this.dashboardMetrics.total)
		},
		dashboardAverageMetrics() {
			return normalizeMetricGroup(this.dashboardMetrics.average)
		},
		dashboardCurrentMonthMetrics() {
			return normalizeMetricGroup(this.dashboardMetrics.currentMonth)
		},
		dashboardFutureMetrics() {
			return normalizeMetricGroup(this.dashboardMetrics.future)
		},
		dashboardFuture30DaysMetrics() {
			return normalizeMetricGroup(this.dashboardMetrics.future30Days)
		},
		tagCounts() {
			return normalizeTagCounts(this.dashboardTagCounts)
		},
		totalIncome() {
			return this.dashboardTotalMetrics.income
		},
		totalExpense() {
			return this.dashboardTotalMetrics.expense
		},
		balance() {
			return this.dashboardTotalMetrics.balance
		},
		totalSubscriptions() {
			return this.dashboardTotalMetrics.subscriptions
		},
		totalFixedCosts() {
			return this.dashboardTotalMetrics.fixedCosts
		},
		totalImportantPayments() {
			return this.dashboardTotalMetrics.important
		},
		totalReviewPayments() {
			return this.dashboardTotalMetrics.review
		},
		totalChildRelated() {
			return this.dashboardTotalMetrics.childRelated
		},
		totalTaxRelevant() {
			return this.dashboardTotalMetrics.taxRelevant
		},
		totalFutureIncome() {
			return this.dashboardFutureMetrics.income
		},
		totalFutureExpense() {
			return this.dashboardFutureMetrics.expense
		},
		totalFutureBalance() {
			return this.dashboardFutureMetrics.balance
		},
		currentMonthIncome() {
			return this.dashboardCurrentMonthMetrics.income
		},
		currentMonthExpense() {
			return this.dashboardCurrentMonthMetrics.expense
		},
		currentMonthBalance() {
			return this.dashboardCurrentMonthMetrics.balance
		},
		currentMonthSubscriptions() {
			return this.dashboardCurrentMonthMetrics.subscriptions
		},
		currentMonthFixedCosts() {
			return this.dashboardCurrentMonthMetrics.fixedCosts
		},
		currentMonthImportantPayments() {
			return this.dashboardCurrentMonthMetrics.important
		},
		currentMonthReviewPayments() {
			return this.dashboardCurrentMonthMetrics.review
		},
		currentMonthChildRelated() {
			return this.dashboardCurrentMonthMetrics.childRelated
		},
		currentMonthTaxRelevant() {
			return this.dashboardCurrentMonthMetrics.taxRelevant
		},
		futureIncome30Days() {
			return this.dashboardFuture30DaysMetrics.income
		},
		futureExpense30Days() {
			return this.dashboardFuture30DaysMetrics.expense
		},
		futureBalance30Days() {
			return this.dashboardFuture30DaysMetrics.balance
		},
		futureSubscriptions30Days() {
			return this.dashboardFuture30DaysMetrics.subscriptions
		},
		futureFixedCosts30Days() {
			return this.dashboardFuture30DaysMetrics.fixedCosts
		},
		futureImportantPayments30Days() {
			return this.dashboardFuture30DaysMetrics.important
		},
		futureReviewPayments30Days() {
			return this.dashboardFuture30DaysMetrics.review
		},
		futureChildRelated30Days() {
			return this.dashboardFuture30DaysMetrics.childRelated
		},
		futureTaxRelevant30Days() {
			return this.dashboardFuture30DaysMetrics.taxRelevant
		},
		averageStats() {
			return this.dashboardAverageMetrics
		},
		averageIncome() {
			return this.averageStats.income;
		},
		averageExpense() {
			return this.averageStats.expense;
		},
		averageBalance() {
			return this.averageStats.balance;
		},
		averageSubscriptions() {
			return this.averageStats.subscriptions;
		},
		averageFixedCosts() {
			return this.averageStats.fixedCosts;
		},
		averageImportantPayments() {
			return this.averageStats.important;
		},
		averageReviewPayments() {
			return this.averageStats.review;
		},
		averageChildRelated() {
			return this.averageStats.childRelated;
		},
		averageTaxRelevant() {
			return this.averageStats.taxRelevant;
		},
		showIncomeCard() {
			return this.$enableIncomes && this.tagCounts.income > 0;
		},
		showFutureCard() {
			return this.$enableFuturePayments && this.tagCounts.future > 0;
		},
		showImportantCard() {
			return this.$enableImportantPayments && this.tagCounts.important > 0;
		},
		showReviewCard() {
			return this.$enableReviewPayments && this.tagCounts.review > 0;
		},
		showFixedCostsCard() {
			return this.$enableFixedCosts && this.tagCounts.fixedCosts > 0;
		},
		showChildRelatedCard() {
			return this.$enableChildRelated && this.tagCounts.childRelated > 0;
		},
		showSubscriptionsCard() {
			return this.$enableSubscriptions && this.tagCounts.subscriptions > 0;
		},
		showTaxRelevantCard() {
			return this.$enableTaxRelevant && this.tagCounts.taxRelevant > 0;
		},
		showBudgetCard() {
			return this.$enableBudgetGoals && this.budgetGoals.length > 0;
		},
		visibleBudgetGoals() {
			const statusRank = { exceeded: 0, warning: 1, ok: 2 };
			return [...this.budgetGoals]
				.sort((a, b) => {
					const statusDiff = (statusRank[a.evaluation?.status] ?? 3) - (statusRank[b.evaluation?.status] ?? 3);
					if (statusDiff !== 0) return statusDiff;
					const bufferDiff = (a.evaluation?.buffer_cents ?? 0) - (b.evaluation?.buffer_cents ?? 0);
					if (bufferDiff !== 0) return bufferDiff;
					return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
				})
				.slice(0, 3);
		},
		budgetWarningCount() {
			return this.budgetGoals.filter(goal => ['warning', 'exceeded'].includes(goal.evaluation?.status)).length;
		},
		budgetStatusLabel() {
			if (this.budgetWarningCount === 0) {
				return 'OK';
			}
			return `${this.budgetWarningCount} im Blick`;
		},
		budgetStatusClass() {
			return this.budgetWarningCount === 0 ? 'positive' : 'negative';
		},
		pageTitle() {
			if (this.filters.type === 'income' || this.$route.query.filter === 'income') return this.$texts.labels.incomePayments();
			if (this.filters.tags === 'subscription') return this.$texts.labels.subscriptionPayments();
			if (this.filters.tags === 'taxRelevant' && this.filters.hasAttachment === 'false') return this.$texts.labels.taxRelevantWithoutReceipt();
			if (this.filters.tags === 'taxRelevant') return this.$texts.labels.taxRelevantPayments();
			if (this.filters.tags === 'fixedCost') return this.$texts.labels.fixedCostPayments();
			if (this.filters.tags === 'childRelated') return this.$texts.labels.childPayments();
			if (this.filters.tags === 'important') return this.$texts.labels.importantPayments();
			if (this.filters.tags === 'review' && this.filters.hasAttachment === 'false') return this.$texts.labels.reviewWithoutReceipt();
			if (this.filters.tags === 'review') return this.$texts.labels.reviewPayments();
			if (this.filters.tags === 'future') return this.$texts.filters.futurePayments();
			if (this.filters.hasReminder === 'true') return this.$texts.filters.activeReminders();
			if (this.filters.timeRange === 'currentYear') return this.$texts.settings.currentYear();
			return this.$texts.settings.myFinances();
		},
		defaultEntryType() {
			return this.filters.type === 'income' || this.$route.query.filter === 'income'
				? 'income'
				: 'expense';
		},
		canExportEntries() {
			return Number(this.pagination.total || 0) > 0;
		}
	},
	mounted() {
			this.applyEntryPageSize()
			this.applyQueryFilters()
			this.fetchData()
			if (this.$enableTemplates) {
				this.fetchTemplates()
			}
		window.addEventListener('entry-saved', this.onEntrySaved)
		window.addEventListener('settings-closed', this.onSettingsClosed);
		window.addEventListener('keydown', this.onPaginationKeydown)
	},
	beforeUnmount() {
		window.removeEventListener('entry-saved', this.onEntrySaved)
		window.removeEventListener('settings-closed', this.onSettingsClosed);
		window.removeEventListener('keydown', this.onPaginationKeydown)
	},
	watch: {
		'$route.query.filter': 'handleRouteQueryChange',
		'$route.query.hasAttachment': 'handleRouteQueryChange'
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
		openBudgetGoals() {
			this.$router.push({ name: 'budgets' })
		},
		applyEntryPageSize() {
			this.pagination.limit = normalizeEntryPageSize(this.$entriesPerPage)
			this.pagination.offset = 0
		},
		onPaginationKeydown(event) {
			if (shouldIgnorePaginationKeydown(event) || this.pagination.total <= this.pagination.limit) {
				return
			}

			if (event.key === 'ArrowLeft' && this.pagination.offset > 0) {
				event.preventDefault()
				this.prevPage()
			} else if (event.key === 'ArrowRight' && this.pagination.offset + this.pagination.limit < this.pagination.total) {
				event.preventDefault()
				this.nextPage()
			}
		},
		onSettingsClosed() {
			this.categories = [];
			this.paymentPartners = [];
			this.hashtags = [];
			this.fetchData();
		},
		handleRouteQueryChange() {
			if (this.isInternalFilterChange) {
				this.isInternalFilterChange = false;
				return;
			}
			// External change (e.g. sidebar or analytics click), so clear other filters.
			this.filters = {
				search: '',
				type: 'all',
				status: 'all',
				categoryId: null,
				projectId: null,
				paymentPartnerId: null,
				dateFrom: null,
				dateTo: null,
				timeRange: 'all',
				tags: 'all',
				hashtagId: null,
				hasReminder: 'all',
				hasAttachment: 'all'
			};
			this.pagination.offset = 0;

			this.applyQueryFilters();

			if (this.$refs.tableFilters) {
				this.$refs.tableFilters.localFilters = { ...this.filters };
			}

			this.fetchData();
		},
		applyQueryFilters() {
			// Reset filters
			this.filters.tags = 'all';
			this.filters.timeRange = 'all';
			this.filters.type = 'all';
			this.filters.hasReminder = 'all';
			this.filters.hasAttachment = 'all';

			if (this.$route.query.filter === 'subscription') {
				this.filters.tags = 'subscription'
			} else if (this.$route.query.filter === 'taxRelevant') {
				this.filters.tags = 'taxRelevant'
			} else if (this.$route.query.filter === 'fixedCost') {
				this.filters.tags = 'fixedCost'
			} else if (this.$route.query.filter === 'childRelated') {
				this.filters.tags = 'childRelated'
			} else if (this.$route.query.filter === 'important') {
				this.filters.tags = 'important'
			} else if (this.$route.query.filter === 'review') {
				this.filters.tags = 'review'
			} else if (this.$route.query.filter === 'future') {
				this.filters.tags = 'future'
			} else if (this.$route.query.filter === 'reminder') {
				this.filters.hasReminder = 'true'
			} else if (this.$route.query.filter === 'currentYear') {
				this.filters.timeRange = 'currentYear'
			} else if (this.$route.query.filter === 'income') {
				this.filters.type = 'income'
			}

			if (this.$route.query.hasAttachment === 'true' || this.$route.query.hasAttachment === 'false') {
				this.filters.hasAttachment = this.$route.query.hasAttachment
			}
			
			if (this.$refs.tableFilters) {
				this.$refs.tableFilters.localFilters.tags = this.filters.tags;
				this.$refs.tableFilters.localFilters.timeRange = this.filters.timeRange;
				this.$refs.tableFilters.localFilters.type = this.filters.type;
				this.$refs.tableFilters.localFilters.hasReminder = this.filters.hasReminder;
				this.$refs.tableFilters.localFilters.hasAttachment = this.filters.hasAttachment;
			}
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

			const params = {
				search: this.filters.search,
				type: this.filters.type,
				status: this.filters.status,
				categoryId: this.filters.categoryId,
				projectId: this.filters.projectId,
				paymentPartnerId: this.filters.paymentPartnerId,
				dateFrom: this.filters.dateFrom,
				dateTo: this.filters.dateTo,
				sortBy: this.sortBy,
				sortDir: this.sortDir,
				isSettled: isSettled,
				isSubscription: isSubscription,
				isFixedCost: isFixedCost,
				isChildRelated: isChildRelated,
				isImportant: isImportant,
				needsReview: needsReview,
				isTaxRelevant: isTaxRelevant,
				hasReminder: hasReminder,
				hasAttachment: hasAttachment,
				hashtagId: this.filters.hashtagId,
				isFuturePayments: this.filters.tags === 'future'
			};

			if (includePagination) {
				params.limit = this.pagination.limit;
				params.offset = this.pagination.offset;
			}

			return params;
		},
		async fetchData() {
			try {
				this.userName = window.OC && window.OC.currentUser ? window.OC.currentUser.displayName || window.OC.currentUser.uid : 'User'
				const params = this.buildEntryQueryParams(true);

				const dashboardRes = await axios.get(generateUrl('/apps/cobudget/api/dashboard'), { params });
				const dashboardData = dashboardRes.data || {};
				const lookups = dashboardData.lookups || {};

				this.entries = dashboardData.entries || [];
				this.dateGroups = dashboardData.dateGroups || null;
				this.pagination.total = dashboardData.total || 0;
				this.dashboardMetrics = normalizeDashboardMetrics(dashboardData.metrics || {});
				this.dashboardTagCounts = normalizeTagCounts(dashboardData.tagCounts || {});

				if (Array.isArray(lookups.categories)) {
					this.categories = lookups.categories.sort((a, b) => a.name.localeCompare(b.name));
				}
				if (Array.isArray(lookups.projects)) {
					this.projects = lookups.projects;
				}
				if (Array.isArray(lookups.paymentPartners)) {
					this.paymentPartners = lookups.paymentPartners.sort((a, b) => a.name.localeCompare(b.name));
				}
				if (Array.isArray(lookups.hashtags)) {
					this.hashtags = lookups.hashtags.sort((a, b) => String(a.displayName || a.name || '').localeCompare(String(b.displayName || b.name || ''), undefined, { sensitivity: 'base' }));
				}
				await this.fetchBudgetGoals()
			} catch (e) {
				showRequestError(e, this.$texts.dashboard.paymentsLoadError(), 'Failed to fetch dashboard data')
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
				showRequestError(e, this.$texts.common.exportCsvError(), 'Failed to export entries')
			} finally {
				this.isExporting = false
			}
		},
		async fetchBudgetGoals() {
			if (!this.$enableBudgetGoals) {
				this.budgetGoals = []
				return
			}
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/budgets'))
				this.budgetGoals = Array.isArray(response.data) ? response.data : []
			} catch (e) {
				this.budgetGoals = []
				showRequestError(e, this.$texts.dashboard.budgetGoalsLoadError(), 'Failed to fetch budget goals')
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
			this.fetchData();
			this.fetchTemplates();
		},
		onFiltersUpdate(newFilters) {
			this.filters = { ...newFilters };
			this.pagination.offset = 0; // Reset pagination on filter change
			
			// Sync tags filter to URL query so the sidebar matches
			let newQuery = { ...this.$route.query };
			if (this.filters.tags === 'subscription') {
				newQuery.filter = 'subscription';
			} else if (this.filters.tags === 'taxRelevant') {
				newQuery.filter = 'taxRelevant';
			} else if (this.filters.tags === 'fixedCost') {
				newQuery.filter = 'fixedCost';
			} else if (this.filters.tags === 'childRelated') {
				newQuery.filter = 'childRelated';
			} else if (this.filters.tags === 'important') {
				newQuery.filter = 'important';
			} else if (this.filters.tags === 'review') {
				newQuery.filter = 'review';
			} else if (this.filters.tags === 'future') {
				newQuery.filter = 'future';
			} else if (this.filters.hasReminder === 'true') {
				newQuery.filter = 'reminder';
			} else if (this.filters.timeRange === 'currentYear') {
				newQuery.filter = 'currentYear';
			} else {
				delete newQuery.filter;
			}

			if (this.filters.hasAttachment === 'true' || this.filters.hasAttachment === 'false') {
				newQuery.hasAttachment = this.filters.hasAttachment;
			} else {
				delete newQuery.hasAttachment;
			}
			
			// Only push if query actually changed to avoid infinite loop
			if (this.$route.query.filter !== newQuery.filter || this.$route.query.hasAttachment !== newQuery.hasAttachment) {
				this.isInternalFilterChange = true;
				this.$router.replace({ path: this.$route.path, query: newQuery }).catch(() => {});
			}
			
			this.fetchData();
		},
		resetFilters() {
			if (this.$refs.tableFilters) {
				this.$refs.tableFilters.clearFilters();
			} else {
				this.filters = {
					search: '',
					type: 'all',
					status: 'all',
					categoryId: null,
					projectId: null,
					paymentPartnerId: null,
					dateFrom: null,
					dateTo: null,
					timeRange: 'all',
					tags: 'all',
					hasReminder: 'all',
					hasAttachment: 'all'
				};
				this.pagination.offset = 0;
				
				let newQuery = { ...this.$route.query };
				delete newQuery.filter;
				delete newQuery.hasAttachment;
				if (this.$route.query.filter || this.$route.query.hasAttachment) {
					this.$router.replace({ path: this.$route.path, query: newQuery }).catch(() => {});
				}
				
				this.fetchData();
			}
		},
		toggleSort(col) {
			if (this.sortBy === col) {
				this.sortDir = this.sortDir === 'desc' ? 'asc' : 'desc';
			} else {
				this.sortBy = col;
				this.sortDir = 'desc'; // Default direction when switching columns
			}
			this.pagination.offset = 0;
			this.fetchData();
		},
		prevPage() {
			if (this.pagination.offset > 0) {
				this.pagination.offset = Math.max(0, this.pagination.offset - this.pagination.limit);
				this.fetchData();
			}
		},
		nextPage() {
			if (this.pagination.offset + this.pagination.limit < this.pagination.total) {
				this.pagination.offset += this.pagination.limit;
				this.fetchData();
			}
		},
		getProjectName(id) {
			const p = this.projects.find(p => String(p.id) === String(id));
			return p ? p.name : `ID: ${id}`;
		},
		getProjectColor(id) {
			const p = this.projects.find(p => String(p.id) === String(id));
			return p && p.color ? p.color : '#0082c9';
		},
		getProjectTagStyle(id) {
			let hexcolor = this.getProjectColor(id);
			if (!hexcolor) return {};
			hexcolor = hexcolor.replace('#', '');
			if (hexcolor.length === 3) {
				hexcolor = hexcolor.split('').map(c => c + c).join('');
			}
			const r = parseInt(hexcolor.substr(0, 2), 16);
			const g = parseInt(hexcolor.substr(2, 2), 16);
			const b = parseInt(hexcolor.substr(4, 2), 16);
			
			// Berechne Helligkeit, um Lesbarkeit auf hellem Hintergrund zu gewährleisten
			const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
			
			let textR = r, textG = g, textB = b;
			
			// Wenn die Farbe zu hell ist (wie gelb, hellgrün), dunkle sie für den Text und Rahmen ab
			if (yiq > 180) {
				// Abdunkeln um ca. 45% für besseren Kontrast auf hellem Hintergrund
				textR = Math.floor(r * 0.55);
				textG = Math.floor(g * 0.55);
				textB = Math.floor(b * 0.55);
			}

			return {
				backgroundColor: `rgba(${r}, ${g}, ${b}, 0.12)`,
				color: `rgb(${textR}, ${textG}, ${textB})`,
				border: `1px solid rgb(${textR}, ${textG}, ${textB})`
			};
		},
		isSharedProject(id) {
			const p = this.projects.find(p => String(p.id) === String(id));
			return p && parseInt(p.member_count) > 1;
		},
		formatDate(timestamp) {
			if (!timestamp) return '-'
			return new Date(timestamp * 1000).toLocaleDateString()
		},
		formatAmount(amount) {
			return this.$formatMoney(amount)
		},
		formatPositiveMetric(value) {
			return this.$formatSignedMoney(Math.abs(parseFloat(value || 0)))
		},
		formatNegativeMetric(value) {
			return this.$formatMoney(-Math.abs(parseFloat(value || 0)))
		},
		signedMetricClass(value) {
			return parseFloat(value || 0) >= 0 ? 'positive' : 'negative'
		},
		formatSignedMetric(value) {
			const amount = parseFloat(value || 0)
			return this.$formatSignedMoney(amount)
		},
		budgetProgressWidth(goal) {
			const percent = Math.max(0, Math.min(100, parseFloat(goal.evaluation?.progress_percent || 0)))
			return `${percent}%`
		},
		budgetBufferLabel(goal) {
			const cents = parseInt(goal.evaluation?.buffer_cents || 0, 10)
			return this.$formatSignedMoney(cents / 100)
		},
		getEntryPersonalAmount(entry) {
			if (entry.personal_amount_cents !== undefined && entry.personal_amount_cents !== null) {
				return parseInt(entry.personal_amount_cents, 10) / 100
			}
			if (entry.personal_amount !== undefined && entry.personal_amount !== null) {
				return parseFloat(entry.personal_amount)
			}
			if (!entry.project_id) return parseFloat(entry.amount);
			const p = this.projects.find(p => String(p.id) === String(entry.project_id));
			if (entry.split_mode === 'single_user') {
				const currentUserId = window.OC?.currentUser?.uid || '';
				const splitTargetUserId = entry.split_user_id || entry.user_id;
				return splitTargetUserId === currentUserId ? parseFloat(entry.amount) : 0;
			}
			const shareBasisPoints = p && p.my_share_basis_points !== undefined ? parseInt(p.my_share_basis_points, 10) : 10000;
			return parseFloat(entry.amount) * (shareBasisPoints / 10000);
		},
		onRowClick(entry) {
			if (entry.is_settled) {
				showToast(this.$texts.areaDetail.entrySettledInfo(), 'info');
			} else {
				this.editEntry(entry);
			}
		},
		editEntry(entry) {
			this.$emit('open-add-modal', { entry, projectId: null, isFuture: this.filters.tags === 'future' })
		},
		duplicateEntry(entry) {
			this.$emit('open-add-modal', { entryToDuplicate: entry, projectId: null, isFuture: this.filters.tags === 'future' })
		},
		async deleteEntry(entry) {
			const isFuture = this.filters.tags === 'future';
			const msg = isFuture 
				? this.$texts.entry.disableFuturePaymentMessage()
				: this.$texts.entry.deleteEntryMessage();
				
			const confirmed = await this.openConfirm({
				title: isFuture ? this.$texts.entry.disableFuturePaymentTitle() : this.$texts.entry.deleteEntryTitle(),
				message: msg,
				confirmLabel: isFuture ? this.$texts.entry.disablePayment() : this.$texts.entry.deleteEntry(),
				confirmVariant: 'danger'
			});
			if (confirmed) {
				try {
					if (isFuture) {
						await axios.post(generateUrl(`/apps/cobudget/api/entries/${entry.id}/stop-recurrence`));
						showToast(this.$texts.entry.futurePaymentDisabled());
					} else {
						await axios.delete(generateUrl(`/apps/cobudget/api/entries/${entry.id}`));
						showToast(this.$texts.entry.entryDeleted());
					}
					this.entries = this.entries.filter(e => e.id !== entry.id)
					window.dispatchEvent(new CustomEvent('cobudget-data-changed'));
					this.fetchData(); // Refresh totals
				} catch (error) {
					const fallback = isFuture
						? this.$texts.entry.futurePaymentDisableError()
						: this.$texts.entry.entryDeleteError();
					showRequestError(error, fallback, 'Failed to delete/stop entry')
				}
			}
		}
	}
}
</script>

<style scoped>
.personal-dashboard {
	width: 100%;
}

.filter-toggle-btn {
	margin-right: 4px;
}

.filter-popover-content {
	padding: 8px;
}

.mobile-header-actions-menu {
	display: none !important;
}

:deep(.v-popper__arrow-container),
:deep(.v-popper__arrow),
:deep(.popover__arrow) {
	display: none !important;
}

.subtitle {
	color: var(--color-text-maxcontrast, #888);
	margin: 0;
	font-size: var(--cobudget-font-base);
}

.stats-row {
	display: flex;
	gap: 10px;
	margin-bottom: 30px;
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
	gap: 10px;
}

.clickable-budget-card {
	cursor: pointer;
	transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}

.clickable-budget-card:hover,
.clickable-budget-card:focus-visible {
  background-color: #F5F9FB;
  box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
  border: 1px solid var(--color-primary-element, #0082c9);
  background: var(--cobudget-page-background, #fff);
  //background: var(--color-background-hover, #f5f5f5);
	outline: none;
}

.stat-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
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

.budget-summary-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
	border-top: 1px solid var(--cobudget-border, #eee);
	padding-top: 10px;
}

.budget-summary-line {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	font-size: var(--cobudget-font-sm);
	color: var(--color-text-maxcontrast, #777);
}

.budget-summary-line span {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.budget-summary-line strong {
	color: var(--color-main-text, #222);
	white-space: nowrap;
}

.budget-summary-bar {
	background: var(--color-background-dark, #eee);
	border-radius: 999px;
	height: 6px;
	overflow: hidden;
}

.budget-summary-bar span {
	background: var(--color-primary, #0082c9);
	display: block;
	height: 100%;
}

.budget-summary-bar.status-warning span {
	background: #f59e0b;
}

.budget-summary-bar.status-exceeded span {
	background: var(--cobudget-error);
}

.activities-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
}

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
	transition: background 0.2s;
	margin-bottom: 30px;
}

.btn-primary:hover {
	background: var(--color-primary-hover, #006aa3);
}

.dashboard-content h3 {
	font-size: var(--cobudget-font-md);
	font-weight: 600;
	margin: 0 0 16px 0;
	color: var(--color-main-text, #222);
}

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
	padding: 12px 10px;
	text-align: left;
	color: var(--color-text-maxcontrast, #888);
	background: var(--cobudget-surface-muted, #f9f9f9);
	font-weight: 600;
	font-size: var(--cobudget-font-compact);
	text-transform: uppercase;
	letter-spacing: 0.5px;
	border-bottom: 2px solid var(--cobudget-border, #ddd);
}

th.col-date {
	width: 140px;
	white-space: normal;
	word-wrap: break-word;
	line-height: 1.3;
}

th.col-actions {
	width: 50px;
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
	color: var(--color-main-text, #222);
	vertical-align: middle;
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

.project-chip {
	display: inline-flex;
	margin-left: 0;
	padding: 2px 6px;
	font-size: var(--cobudget-font-xs);
	font-weight: 600;
	align-items: center;
	border-radius: 4px;
	white-space: nowrap;
	vertical-align: middle;
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

.project-cell {
	text-align: center;
	overflow: visible;
	padding-left: 4px !important;
	padding-right: 4px !important;
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



.empty-state {
	padding: 40px;
	text-align: center;
	color: var(--color-text-maxcontrast, #888);
	background: var(--cobudget-surface-muted, #f6f6f6);
	border-radius: var(--border-radius-large, 8px);
	border: 1px dashed var(--cobudget-border-strong, #ccc);
}

.mobile-only {
	display: none !important;
}

@media (max-width: 768px) {
	.desktop-header-action {
		display: none !important;
	}

	.mobile-header-actions-menu {
		display: inline-flex !important;
	}

	.table-container {
		border: none;
		background: transparent;
		box-shadow: none;
	}

	.stats-row {
		gap: 10px;
		padding-bottom: 12px;
		margin-bottom: 20px;
	}

	.stat-card {
		flex-basis: 300px;
		padding: 12px;
		gap: 10px;
	}

	.stat-icon {
		font-size: var(--cobudget-font-xl);
		width: 36px;
		height: 36px;
	}

	.stat-label {
		font-size: var(--cobudget-font-xs);
		margin-bottom: 2px;
	}

	.stat-value {
		font-size: var(--cobudget-font-md);
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

	.date-cell, .category-cell, .paymentPartner-cell {
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
