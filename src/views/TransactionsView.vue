<template>
	<div
		class="personal-dashboard"
		:class="{ 'personal-dashboard--table-only': isPaymentsView }">
		<AppPageHeader class="payments-page-header cobudget-filter-toolbar">
			<template v-if="!isPaymentsView" #creation>
				<NcButton
					variant="primary"
					class="new-payment-main-button"
					:aria-label="newPaymentActionLabel"
					:title="newPaymentActionLabel"
					@click="$emit('open-entry-sidebar', { forceNew: true, isFuture: filters.tags === 'future', defaultType: defaultEntryType })">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					<span class="creation-label-desktop">{{ $texts.common.newItem() }}</span>
					<span class="btn-text">{{ newPaymentActionLabel }}</span>
				</NcButton>
			</template>
			<template #title>
				<span v-if="isPaymentsView" class="cobudget-page-title-with-back">
					<NcButton
						variant="tertiary"
						class="cobudget-native-button cobudget-header-back-button"
						:aria-label="(annualReturnRoute($route.query.annualReturn) ? $texts.annual.back() : $texts.payments.backToFinances())"
						:title="(annualReturnRoute($route.query.annualReturn) ? $texts.annual.back() : $texts.payments.backToFinances())"
						@click="goBackToFinances">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
					</NcButton>
					<ReportPeriodPicker navigation-only :period-enabled="false" :title="$texts.payments.title()" label="" />
				</span>
				<ReportPeriodPicker v-else navigation-only force-navigation :period-enabled="false" :title="pageTitle" :selection="paymentPeriodSelection" label="" :quick-labels="quickLabels" @quick-label="selectQuickLabel" />
			</template>
			<template #actions>
				<ReportPeriodPicker class="payment-toolbar-period" calendar-multiple :selection="paymentPeriodSelection" :years="paymentYears" :label="paymentPeriodLabel" @change="selectPaymentPeriod" />
				<PaymentFilterBar :filters="filters" :reset-time-range="isPaymentsView ? 'all' : 'currentYear'" :period-selection="paymentPeriodSelection" :period-years="paymentYears" :period-chip-label="paymentPeriodChipLabel" :default-time-range="defaultFilterTimeRange" :categories="categories" :projects="projects"
					:payment-partners="paymentPartners" :hashtags="hashtags" :label-counts="navigationTagCounts || tagCounts" :review-count="tagCounts.review"
					:filter-availability="filterAvailability" @update:filters="onFiltersUpdate" />
				<NcActions class="cobudget-action-menu" variant="tertiary" force-menu placement="bottom-end" :aria-label="$texts.filters.listActions()" @close="csvMenuOpen = false">
					<template v-if="csvMenuOpen && canExportEntries">
						<NcActionButton key="csv-back" ref="csvBack" class="cobudget-action-menu-option" :close-after-click="false" @click="showCsvMenu(false)">
							<template #icon><ArrowLeftIcon :size="20" /></template>
							{{ $texts.common.exportCsv() }}
						</NcActionButton>
						<NcActionButton key="csv-download" class="cobudget-action-menu-option" close-after-click :disabled="isExporting" :description="$texts.annual.downloadCsvHint()" @click="exportEntries">
							<template #icon><DownloadIcon :size="20" /></template>
							{{ $texts.common.download() }}
						</NcActionButton>
						<NcActionButton key="csv-save" class="cobudget-action-menu-option" close-after-click :disabled="isExporting" :description="$texts.annual.saveInNextcloudHint()" @click="saveEntriesInNextcloud">
							<template #icon><CloudUploadOutlineIcon :size="20" /></template>
							{{ $texts.annual.saveInNextcloud() }}
						</NcActionButton>
						<NcActionButton key="csv-copy" class="cobudget-action-menu-option" close-after-click :disabled="isExporting || !canCopyEntriesToClipboard" :description="clipboardExportDescription" @click="copyEntriesToClipboard">
							<template #icon><ClipboardTextOutlineIcon :size="20" /></template>
							{{ $texts.common.copyToClipboard() }}
						</NcActionButton>
					</template>
					<template v-else>
						<NcActionButton v-if="canExportEntries" key="csv-trigger" ref="csvTrigger" class="cobudget-action-menu-option" is-menu :close-after-click="false" :disabled="isExporting" @click="showCsvMenu(true)">
							<template #icon><DownloadIcon :size="20" /></template>
							{{ $texts.common.exportCsv() }}
						</NcActionButton>
						<NcActionButton v-if="canExportEntries" key="print" class="cobudget-action-menu-option" close-after-click :disabled="isPrinting" @click="printEntries">
							<template #icon><PrinterIcon :size="20" /></template>
							{{ $texts.analytics.print() }}
						</NcActionButton>
						<NcActionButton class="cobudget-action-menu-option" close-after-click @click="openMasterDataSettings('categories')">
							<template #icon><ShapeOutlineIcon :size="20" /></template>
							{{ $texts.settings.manageCategories() }}
						</NcActionButton>
						<NcActionButton class="cobudget-action-menu-option" close-after-click @click="openMasterDataSettings('payment-partners')">
							<template #icon><AccountOutlineIcon :size="20" /></template>
							{{ $texts.settings.managePaymentPartners() }}
						</NcActionButton>
					</template>
				</NcActions>
			</template>
		</AppPageHeader>

		<MobileFinanceSummary
			v-if="!isPaymentsView"
			:periods="reminderSummaryCard ? [reminderSummaryCard, ...mobilePeriodCards] : mobilePeriodCards"
			:metrics="mobileAdditionalMetrics" />

		<div v-if="!isPaymentsView" class="summary-strip desktop-summary-cards">
			<DraggableScroller allow-link-drag class="stats-row desktop-summary-cards">
			<RouterLink v-if="reminderSummaryCard" class="stat-card reminder-summary-card" data-cobudget-card="compact" :to="reminderSummaryCard.route">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><BellOutlineIcon :size="20" class="period-card-icon" /></div>
						<span class="stat-label">{{ reminderSummaryCard.label }}</span>
					</div>
					<span class="stat-value">{{ reminderSummaryCard.value }}</span>
				</div>
				<div class="stat-sub-info">
					<div v-for="detail in reminderSummaryCard.details" :key="detail.key" class="stat-sub-line">
						<span class="reminder-summary-title" :title="detail.label">{{ detail.label }}</span>
						<span>{{ detail.value }}</span>
					</div>
				</div>
			</RouterLink>
			<RouterLink class="stat-card current-month-card" data-cobudget-card="compact" :to="summaryLocation('current-month')" v-if="showCurrentMonthCard">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><CalendarMonthIcon :size="20" class="period-card-icon" /></div>
						<span class="stat-label">{{ currentMonthName }}</span>
					</div>
					<div class="stat-value-group">
						<span class="stat-value-label">{{ $texts.dashboard.balance() }}</span>
						<span class="stat-value" :class="currentMonthBalance >= 0 ? 'positive' : 'negative'">
							{{ formatSignedMetric(currentMonthBalance) }}
						</span>
					</div>
				</div>
				<div class="stat-sub-info">
					<div v-if="$enableIncomes" class="stat-sub-line">
						<span>{{ $texts.dashboard.incomes() }}</span>
						<span>{{ formatPositiveMetric(currentMonthIncome) }}</span>
					</div>
					<div class="stat-sub-line">
						<span>{{ $texts.dashboard.expenses() }}</span>
						<span>{{ formatNegativeMetric(currentMonthExpense) }}</span>
					</div>
				</div>
			</RouterLink>

			<RouterLink v-if="showBudgetCard" class="stat-card budget-card" data-cobudget-card="compact" :to="summaryLocation('budget-goals')">
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
			</RouterLink>

			<RouterLink class="stat-card total-card" data-cobudget-card="compact" v-if="$enableIncomes" :to="summaryLocation('balance')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><WalletIcon :size="20" fillColor="#0082c9" /></div>
						<span class="stat-label">{{ $texts.dashboard.balance() }}</span>
					</div>
					<span class="stat-value" :class="balance >= 0 ? 'positive' : 'negative'">
						{{ formatSignedMetric(balance) }}
					</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageBalance) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthBalance) }}</span>
					</div>
				</div>
			</RouterLink>

			<RouterLink class="stat-card income-card" data-cobudget-card="compact" v-if="showIncomeCard" :to="summaryLocation('income')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><TrendingUpIcon :size="20" fillColor="var(--cobudget-success)" /></div>
						<span class="stat-label">{{ $texts.dashboard.income() }}</span>
					</div>
					<span class="stat-value positive">{{ formatPositiveMetric(totalIncome) }}</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatPositiveMetric(averageIncome) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatPositiveMetric(currentMonthIncome) }}</span>
					</div>
				</div>
			</RouterLink>

			<RouterLink class="stat-card expense-card" data-cobudget-card="compact" :to="summaryLocation('expenses')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><TrendingDownIcon :size="20" fillColor="var(--cobudget-error)" /></div>
						<span class="stat-label">{{ $texts.dashboard.expenses() }}</span>
					</div>
					<span class="stat-value negative">{{ formatNegativeMetric(totalExpense) }}</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatNegativeMetric(averageExpense) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatNegativeMetric(currentMonthExpense) }}</span>
					</div>
				</div>
			</RouterLink>

			<RouterLink class="stat-card important-card" data-cobudget-card="compact" v-if="showImportantCard" :to="summaryLocation('important')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><StarIcon :size="20" fillColor="#ffc92b" /></div>
						<span class="stat-label">{{ $texts.labels.important() }}</span>
					</div>
					<span class="stat-value" :class="signedMetricClass(totalImportantPayments)">{{ formatSignedMetric(totalImportantPayments) }}</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageImportantPayments) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthImportantPayments) }}</span>
					</div>
				</div>
			</RouterLink>

			<RouterLink class="stat-card review-card" data-cobudget-card="compact" v-if="showReviewCard" :to="summaryLocation('review')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><ClipboardCheckIcon :size="20" fillColor="var(--cobudget-error)" /></div>
						<span class="stat-label">{{ $texts.labels.review() }}</span>
					</div>
					<span class="stat-value" :class="signedMetricClass(totalReviewPayments)">{{ formatSignedMetric(totalReviewPayments) }}</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageReviewPayments) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthReviewPayments) }}</span>
					</div>
				</div>
			</RouterLink>

			<RouterLink class="stat-card fixed-costs-card" data-cobudget-card="compact" v-if="showFixedCostsCard" :to="summaryLocation('fixed-costs')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><BankOutlineIcon :size="20" fillColor="var(--cobudget-label-fixed-cost-color)" /></div>
						<span class="stat-label">{{ $texts.labels.fixedCosts() }}</span>
					</div>
					<span class="stat-value negative">{{ formatNegativeMetric(totalFixedCosts) }}</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatNegativeMetric(averageFixedCosts) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatNegativeMetric(currentMonthFixedCosts) }}</span>
					</div>
				</div>
			</RouterLink>

			<RouterLink class="stat-card child-card" data-cobudget-card="compact" v-if="showChildRelatedCard" :to="summaryLocation('children')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><AccountChildIcon :size="20" fillColor="var(--cobudget-label-children-color)" /></div>
						<span class="stat-label">{{ $texts.labels.children() }}</span>
					</div>
					<span class="stat-value" :class="signedMetricClass(totalChildRelated)">{{ formatSignedMetric(totalChildRelated) }}</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageChildRelated) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthChildRelated) }}</span>
					</div>
				</div>
			</RouterLink>

			<RouterLink class="stat-card abos-card" data-cobudget-card="compact" v-if="showSubscriptionsCard" :to="summaryLocation('subscriptions')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><SyncIcon :size="20" fillColor="var(--cobudget-label-subscription-color)" /></div>
						<span class="stat-label">{{ $texts.labels.subscription() }}</span>
					</div>
					<span class="stat-value negative">{{ formatNegativeMetric(totalSubscriptions) }}</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatNegativeMetric(averageSubscriptions) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatNegativeMetric(currentMonthSubscriptions) }}</span>
					</div>
				</div>
			</RouterLink>

			<RouterLink class="stat-card tax-card" data-cobudget-card="compact" v-if="showTaxRelevantCard" :to="summaryLocation('tax-relevant')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><ReceiptTextCheckOutlineIcon :size="20" fillColor="#0082c9" /></div>
						<span class="stat-label">{{ $texts.labels.taxRelevant() }}</span>
					</div>
					<span class="stat-value" :class="signedMetricClass(totalTaxRelevant)">{{ formatSignedMetric(totalTaxRelevant) }}</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageTaxRelevant) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthTaxRelevant) }}</span>
					</div>
				</div>
			</RouterLink>
			<RouterLink class="stat-card insurance-card" data-cobudget-card="compact" v-if="showInsuranceCard" :to="summaryLocation('insurance')">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon"><ShieldCheckOutlineIcon :size="20" fillColor="var(--cobudget-label-insurance-color)" /></div>
						<span class="stat-label">{{ $texts.labels.insurance() }}</span>
					</div>
					<span class="stat-value" :class="signedMetricClass(totalInsurance)">{{ formatSignedMetric(totalInsurance) }}</span>
				</div>
				<div v-if="showMonthlyAverage || showCurrentMonthSummary" class="stat-sub-info">
					<div v-if="showMonthlyAverage" class="stat-sub-line">
						<span>{{ $texts.dashboard.averagePerMonth() }}</span>
						<span>{{ formatSignedMetric(averageInsurance) }}</span>
					</div>
					<div v-if="showCurrentMonthSummary" class="stat-sub-line">
						<span>{{ $texts.dashboard.currentMonth() }}</span>
						<span>{{ formatSignedMetric(currentMonthInsurance) }}</span>
					</div>
				</div>
			</RouterLink>

		</DraggableScroller>
		</div>

		<PaymentFilterBar ref="filterChips" chips-only :filters="filters" :period-chip-label="paymentPeriodChipLabel" :default-time-range="defaultFilterTimeRange" :categories="categories" :projects="projects" :payment-partners="paymentPartners"
			:hashtags="hashtags" :label-counts="navigationTagCounts || tagCounts"
			:filter-availability="filterAvailability" @update:filters="onFiltersUpdate" />

		<div class="recent-activities">

			<EntryTable
				v-if="entries.length > 0"
				ref="entryTable"
				mode="personal"
				:entries="entries"
				:date-groups="dateGroups"
				:group-report-resolver="groupReportLocation"
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
				:enable-insurance="$enableInsurance"
				:selected-entry-id="selectedEntryId"
				:amount-resolver="getEntryPersonalAmount"
				:project-name-resolver="getProjectName"
				:project-style-resolver="getProjectTagStyle"
				:project-color-resolver="getProjectColor"
				:is-shared-project-resolver="isSharedProject"
				@sort="toggleSort"
				@row-click="onRowClick"
				@edit="editEntry"
				@duplicate="duplicateEntry"
				@history="openEntryHistory"
				@delete="deleteEntry">
					<template #pagination>
						<div
							v-if="!isPaymentsView || totalPages > 1"
							class="pagination-footer"
							:class="{
								'pagination-footer--single': pagination.total <= pagination.limit,
								'pagination-footer--dashboard': !isPaymentsView,
							}">
							<NcButton :aria-label="$texts.common.previous()" v-if="pagination.total > pagination.limit" variant="tertiary" class="btn-page" :style="{ visibility: pagination.offset > 0 ? 'visible' : 'hidden' }" @click="prevPage">
								<template #icon><ArrowLeftIcon :size="20" /></template>
								<span class="pagination-label">{{ $texts.common.previous() }}</span>
							</NcButton>
							<span class="page-info">{{ paginationInfo }}</span>
							<NcButton :aria-label="$texts.common.next()" alignment="center-reverse" v-if="pagination.total > pagination.limit" variant="tertiary" class="btn-page" :style="{ visibility: pagination.offset + pagination.limit < pagination.total ? 'visible' : 'hidden' }" @click="nextPage">
								<span class="pagination-label">{{ $texts.common.next() }}</span>
								<template #icon><ArrowRightIcon :size="20" /></template>
							</NcButton>
						</div>
						<div
							v-if="!isPaymentsView && pagination.total > pagination.limit"
							class="mobile-more-payments-footer">
							<NcButton
								variant="tertiary-no-background"
								class="mobile-more-payments-button"
								@click="openPaymentsView">
								{{ $texts.payments.showMore() }}
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
						{{ $texts.filters.resetAll() }}
					</NcButton>
					<NcButton v-else-if="!isPaymentsView" variant="primary" class="new-payment-main-button" @click="$emit('open-entry-sidebar', { forceNew: true, isFuture: filters.tags === 'future', defaultType: defaultEntryType })">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ newPaymentActionLabel }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</div>
		<DialogTransition :show="!!(entryHistoryOpen)" v-slot="{ returnFocus }">
			<EntryHistoryModal :container="null" :set-return-focus="returnFocus"
				:history="entryHistoryRows"
				:loading="entryHistoryLoading"
				:error="entryHistoryError"
				@retry="openEntryHistory(entryHistoryEntry)"
				@close="closeEntryHistory" />
		</DialogTransition>
		<DialogTransition :show="!!(confirmDialog)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog payment-delete-dialog"
				:name="confirmDialog.title" :no-close="confirmDialog.busy" :close-on-click-outside="!confirmDialog.busy"
				@closing="closeActionConfirm">
				<p class="payment-delete-message">{{ confirmDialog.message }}</p>
				<NcNoteCard v-if="confirmDialog.error" type="error" :text="confirmDialog.error" />
				<template #actions>
					<NcButton variant="tertiary" class="cobudget-native-button" :disabled="confirmDialog.busy" @click="closeActionConfirm">{{ $texts.common.cancel() }}</NcButton>
					<NcButton variant="error" :disabled="confirmDialog.busy" @click="confirmAction">
						<template v-if="confirmDialog.busy" #icon><NcLoadingIcon :size="20" /></template>
						{{ confirmDialog.busy ? $texts.common.waitBusy() : confirmDialog.confirmLabel }}
					</NcButton>
				</template>
			</NcDialog>
		</DialogTransition>
	</div>
</template>

<script>
import DialogTransition from '../components/DialogTransition.vue'
import { annualReturnRoute } from '../services/annualOverview'
import { getAppLocale } from '../utils/formatMoney'
import axios from '../services/http'
import { REMOTE_DATA_CHANGED_EVENT, changeTouches } from '../services/dataSync'
import { generateUrl } from '@nextcloud/router'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import TrendingDownIcon from 'vue-material-design-icons/TrendingDown.vue'
import WalletIcon from 'vue-material-design-icons/WalletOutline.vue'
import SyncIcon from 'vue-material-design-icons/Sync.vue'
import BankOutlineIcon from 'vue-material-design-icons/BankOutline.vue'
import StarIcon from 'vue-material-design-icons/StarOutline.vue'
import ClipboardCheckIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import AccountChildIcon from 'vue-material-design-icons/AccountChildOutline.vue'
import ReceiptTextCheckOutlineIcon from 'vue-material-design-icons/ReceiptTextCheckOutline.vue'
import ShieldCheckOutlineIcon from 'vue-material-design-icons/ShieldCheckOutline.vue'
import CalendarIcon from 'vue-material-design-icons/CalendarOutline.vue'
import CalendarMonthIcon from 'vue-material-design-icons/CalendarMonthOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import CloudUploadOutlineIcon from 'vue-material-design-icons/CloudUploadOutline.vue'
import ClipboardTextOutlineIcon from 'vue-material-design-icons/ClipboardTextOutline.vue'
import PrinterIcon from 'vue-material-design-icons/Printer.vue'
import ShapeOutlineIcon from 'vue-material-design-icons/ShapeOutline.vue'
import AccountOutlineIcon from 'vue-material-design-icons/AccountOutline.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import PaymentFilterBar from '../components/PaymentFilterBar.vue'
import DraggableScroller from '../components/DraggableScroller.vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import EntryTable from '../components/EntryTable.vue'
import EntryHistoryModal from '../components/EntryHistoryModal.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import BellOutlineIcon from 'vue-material-design-icons/BellOutline.vue'
import { upcomingOpenReminders } from '../services/reminderSummary'
import MobileFinanceSummary from '../components/MobileFinanceSummary.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import { reportPeriodLabel } from '../services/annualOverview'
import { formatCalendarLabel } from '../utils/formatDate'
import { RouterLink } from 'vue-router'
import { paymentCalendarIncludesMonth, paymentPeriodBounds, paymentPeriodBudgetGoals, paymentPeriodIsCurrentMonth, paymentPeriodIsSingleMonth, paymentPickerFilters, paymentPickerSelection, paymentPeriodYear, defaultPaymentPeriod, paymentSummaryTarget, paymentGroupReportTarget } from '../services/paymentSummary'
import { normalizeEntryPageSize, shouldIgnorePaginationKeydown } from '../services/pagination'
import {
	createPersonalEntryFilters,
	entryNarrowingQueryParams,
	entryOffsetFromPage,
	entryPageFromOffset,
	parsePersonalEntryListRouteQuery,
	personalEntryListQueriesEqual,
	serializePersonalEntryListRouteQuery,
	withPaymentLabels,
} from '../services/entryListRoute'
import { extractError, showRequestError, showToast } from '../services/notifications'
import { ENTRY_CLIPBOARD_EXPORT_LIMIT, csvToClipboardText, downloadBlobResponse, filenameFromDisposition } from '../services/downloads'
import { copyReportText, saveReportExport } from '../services/reportExportActions'
import { loadReportPayments } from '../services/reportPayments'
import { printPaymentsOverview } from '../services/paymentPrint'
import { PRINT_REPORT_EVENT } from '../services/keyboardShortcuts'
import { getAreaColorStyle } from '../utils/areaColor'

const calendarMonthKeys = [
	'january',
	'february',
	'march',
	'april',
	'may',
	'june',
	'july',
	'august',
	'september',
	'october',
	'november',
	'december',
]

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
	insurance: 0,
})

const emptyDashboardMetrics = () => ({
	total: emptyMetricGroup(),
	average: emptyMetricGroup(),
	currentMonth: emptyMetricGroup(),
	currentYear: emptyMetricGroup(),
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
	insurance: 0,
})

const normalizeMetricGroup = group => ({
	...emptyMetricGroup(),
	...(group || {}),
})

const normalizeDashboardMetrics = metrics => ({
	total: normalizeMetricGroup(metrics?.total),
	average: normalizeMetricGroup(metrics?.average),
	currentMonth: normalizeMetricGroup(metrics?.currentMonth),
	currentYear: normalizeMetricGroup(metrics?.currentYear),
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
		insurance: parseInt(values.insurance || 0, 10),
	}
}

export default {
	name: 'TransactionsView',
	props: {
		navigationTagCounts: { type: Object, default: null },
		selectedEntryId: {
			type: [Number, String],
			default: null
		}
	},
	emits: ['open-entry-sidebar'],
	components: { DialogTransition,
		NcButton,
		NcEmptyContent,
		PaymentFilterBar,
		PlusIcon,
		TrendingUpIcon,
		TrendingDownIcon,
		WalletIcon,
		SyncIcon,
		BankOutlineIcon,
		StarIcon,
		ClipboardCheckIcon,
		AccountChildIcon,
		ReceiptTextCheckOutlineIcon,
		ShieldCheckOutlineIcon,
		CalendarIcon,
		CalendarMonthIcon, BellOutlineIcon,
		CloseIcon,
		DownloadIcon,
		CloudUploadOutlineIcon,
		ClipboardTextOutlineIcon,
		PrinterIcon,
		ShapeOutlineIcon,
		AccountOutlineIcon,
		NcActions,
		NcActionButton,
		ArrowLeftIcon,
		ArrowRightIcon,
		DraggableScroller,
		NcDialog, NcNoteCard, NcLoadingIcon,
		EntryTable,
		EntryHistoryModal,
		AppPageHeader,
		MobileFinanceSummary, ReportPeriodPicker, RouterLink,
	},
	data() {
		const currentPeriodDate = new Date()
		return {
			isExporting: false,
			isPrinting: false,
			printRequestId: 0,
			csvMenuOpen: false,
			userName: '',
			entries: [],
			filterAvailability: null,
			filterAvailabilityKey: '',
			dateGroups: null,
			dashboardMetrics: emptyDashboardMetrics(),
			dashboardTagCounts: emptyTagCounts(),
			budgetGoals: [],
			summaryReminders: [],
			reminderSummaryNow: Date.now(),
			reminderSummaryTimer: null,
			reminderSummaryRequest: 0,
			paymentYears: [],
			projects: [],
			categories: [],
			paymentPartners: [],
			hashtags: [],
			entryHistoryOpen: false,
			entryHistoryError: '',
			entryHistoryEntry: null,
			entryHistoryRequestId: 0,
			entryHistoryLoading: false,
			entryHistoryRows: [],
			filters: createPersonalEntryFilters(),
			sortBy: 'date',
			sortDir: 'desc',
			pagination: {
				limit: 25,
				offset: 0,
				total: 0
			},
			fetchRequestId: 0,
			confirmDialog: null,
			currentPeriodDate,
			currentPeriodRefreshTimer: null,
			mobilePaginationMedia: null,
		}
	},
	computed: {
		quickLabels() {
			const counts = this.navigationTagCounts || this.tagCounts
			return [
				{ id: 'important', label: this.$texts.labels.important(), enabled: this.$enableImportantPayments },
				{ id: 'review', label: this.$texts.labels.review(), enabled: this.$enableReviewPayments },
				{ id: 'fixedCost', countKey: 'fixedCosts', label: this.$texts.labels.fixedCosts(), enabled: this.$enableFixedCosts },
				{ id: 'childRelated', label: this.$texts.labels.children(), enabled: this.$enableChildRelated },
				{ id: 'subscription', countKey: 'subscriptions', label: this.$texts.labels.subscriptionPayments(), enabled: this.$enableSubscriptions },
				{ id: 'taxRelevant', label: this.$texts.labels.taxRelevant(), enabled: this.$enableTaxRelevant },
				{ id: 'insurance', label: this.$texts.labels.insurance(), enabled: this.$enableInsurance },
			].filter(item => item.enabled && counts[item.countKey || item.id] > 0)
		},
		defaultFilterTimeRange() { return this.isPaymentsView || this.filters.tags === 'future' ? 'all' : 'currentYear' },
		selectedPaymentYear() { return paymentPeriodYear(this.filters, this.currentPeriodDate) },
		paymentPeriodSelection() { return paymentPickerSelection(this.filters, this.currentPeriodDate) },
		paymentPeriodLabel() {
			if (this.filters.timeRange === 'all') return this.$texts.filters.allTime()
			if (this.filters.timeRange === 'calendarSelection') {
				const { periodYears, periodMonths } = this.paymentPeriodSelection
				const years = periodYears.join(', ')
				const formatter = new Intl.DateTimeFormat(getAppLocale(), { month: 'short' })
				const months = periodMonths.map(month => formatter.format(new Date(2020, month - 1, 1))).join(', ')
				return [years, months].filter(Boolean).join(' · ')
			}
			const dateFormat = new Intl.DateTimeFormat(getAppLocale(), { day: '2-digit', month: '2-digit', year: 'numeric' })
			return reportPeriodLabel(this.paymentPeriodSelection, formatCalendarLabel, date => dateFormat.format(date)) || this.$texts.annual.selectedPeriod()
		},
		paymentPeriodChipLabel() {
			const { timeRange, periodYears, periodMonths } = this.filters
			if (timeRange === this.defaultFilterTimeRange) return ''
			if (this.defaultFilterTimeRange === 'currentYear') {
				const currentYear = this.currentPeriodDate.getFullYear()
				const isCurrentCalendarYear = timeRange === 'calendarSelection' && !periodMonths && periodYears === String(currentYear)
				if (isCurrentCalendarYear || this.selectedPaymentYear === currentYear) return ''
			}
			return this.paymentPeriodLabel
		},
		showCurrentMonthCard() {
			return this.showCurrentMonthSummary
		},
		showMonthlyAverage() {
			return !paymentPeriodIsSingleMonth(this.filters, this.currentPeriodDate)
		},
		showCurrentMonthSummary() {
			const range = paymentPeriodBounds(this.filters, this.currentPeriodDate), today = this.currentPeriodDate.getTime() / 1000
			return !paymentPeriodIsCurrentMonth(this.filters, this.currentPeriodDate) && this.filters.tags !== 'future' && paymentCalendarIncludesMonth(this.filters, this.currentPeriodDate) && (range.dateFrom == null || range.dateFrom <= today) && (range.dateTo == null || range.dateTo >= today)
		},
		isPaymentsView() {
			return this.$route.name === 'payments'
		},
		currentPage() {
			return entryPageFromOffset(this.pagination.offset, this.pagination.limit)
		},
		totalPages() {
			return Math.max(1, Math.ceil(Number(this.pagination.total || 0) / this.pagination.limit))
		},
		paginationInfo() {
			if (this.isPaymentsView) {
				return this.$texts.common.pageNumber(this.currentPage, this.totalPages)
			}
			return this.$texts.common.pageInfo(
				this.pagination.offset + 1,
				Math.min(this.pagination.offset + this.pagination.limit, this.pagination.total),
				this.pagination.total,
			)
		},
		hasActiveFilters() {
			return this.filters.search !== '' || 
				   this.filters.status !== 'all' ||
				   this.filters.categoryId !== null ||
				   this.filters.projectId !== null ||
				   this.filters.paymentPartnerId !== null ||
				   this.filters.hashtagId !== null ||
				   this.filters.timeRange !== this.defaultFilterTimeRange || this.filters.type !== 'all' ||
				   (this.filters.tags !== 'all' && this.filters.tags !== 'future') ||
				   (this.$enableReminders !== false && this.filters.hasReminder !== 'all') ||
				   this.filters.hasAttachment !== 'all' ||
				   Boolean(this.filters.narrowCategories) ||
				   Boolean(this.filters.narrowPartners) ||
				   Boolean(this.filters.narrowProjects) ||
				   Boolean(this.filters.narrowLabels) ||
				   Boolean(this.filters.narrowHashtags) ||
				   Boolean(this.filters.amountMin) ||
				   Boolean(this.filters.amountMax);
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
		dashboardCurrentYearMetrics() {
			return normalizeMetricGroup(this.dashboardMetrics.currentYear)
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
		totalInsurance() {
			return this.dashboardTotalMetrics.insurance
		},
		currentMonthName() {
			const key = calendarMonthKeys[this.currentPeriodDate.getMonth()]
			return key ? this.$texts.calendar[key]() : ''
		},
		currentYearLabel() {
			return String(this.currentPeriodDate.getFullYear())
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
		currentMonthInsurance() {
			return this.dashboardCurrentMonthMetrics.insurance
		},
		currentYearIncome() {
			return this.dashboardCurrentYearMetrics.income
		},
		currentYearExpense() {
			return this.dashboardCurrentYearMetrics.expense
		},
		currentYearBalance() {
			return this.dashboardCurrentYearMetrics.balance
		},
		reminderSummaryCard() {
			if (!this.$enableReminders) return null
			const rows = upcomingOpenReminders(this.summaryReminders, new Date(this.reminderSummaryNow))
			if (!rows.length) return null
			return {
				key: 'reminders', route: { name: 'reminders' }, icon: BellOutlineIcon,
				iconColor: 'var(--cobudget-primary, var(--color-primary-element))',
				label: this.$texts.reminders.sidebar(), value: rows.length, valueClass: 'muted',
				details: rows.slice(0, 2).map(row => ({ key: row.id, label: row.title || this.$texts.reminders.sidebar(), value: this.formatDate(row.remind_at) })),
			}
		},
		mobilePeriodCards() {
			if (!this.showCurrentMonthCard) return []
			const details = (income, expense) => [
				...(this.$enableIncomes ? [{
					key: 'income',
					label: this.$texts.dashboard.incomes(),
					value: this.formatPositiveMetric(income),
					valueClass: 'positive',
				}] : []),
				{
					key: 'expense',
					label: this.$texts.dashboard.expenses(),
					value: this.formatNegativeMetric(expense),
					valueClass: 'negative',
				},
			]

			return [
				{
					key: 'current-month',
					route: this.summaryLocation('current-month'),
					icon: CalendarMonthIcon,
					iconColor: 'var(--cobudget-primary, var(--color-primary-element))',
					label: this.currentMonthName,
					value: this.formatSignedMetric(this.currentMonthBalance),
					valueClass: this.signedMetricClass(this.currentMonthBalance),
					details: details(this.currentMonthIncome, this.currentMonthExpense),
				},
			]
		},
		mobileAdditionalMetrics() {
			const metrics = []
			const monthlyDetails = (average, currentMonth, formatter, valueClass) => [
				{
					key: 'average',
					label: this.$texts.dashboard.averagePerMonth(),
					value: formatter(average),
					valueClass: typeof valueClass === 'function' ? valueClass(average) : valueClass,
				},
				{
					key: 'current-month',
					label: this.$texts.dashboard.currentMonth(),
					value: formatter(currentMonth),
					valueClass: typeof valueClass === 'function' ? valueClass(currentMonth) : valueClass,
				},
			]

			if (this.showBudgetCard) {
				metrics.push({
					key: 'budget-goals',
					icon: WalletIcon,
					iconColor: '#2563eb',
					label: this.$texts.dashboard.budgetGoals(),
					value: this.budgetStatusLabel,
					valueClass: this.budgetStatusClass,
					route: { name: 'budgets' },
					details: this.visibleBudgetGoals.map(goal => ({
						key: `goal-${goal.id}`,
						label: goal.name,
						value: this.budgetBufferLabel(goal),
						valueClass: this.signedMetricClass(parseInt(goal.evaluation?.buffer_cents || 0, 10)),
					})),
				})
			}

			if (this.showIncomeCard) {
				metrics.push({
					key: 'income',
					icon: TrendingUpIcon,
					iconColor: 'var(--cobudget-success, #107C41)',
					label: this.$texts.dashboard.income(),
					value: this.formatPositiveMetric(this.totalIncome),
					valueClass: 'positive',
					details: monthlyDetails(this.averageIncome, this.currentMonthIncome, this.formatPositiveMetric, 'positive'),
				})
			}

			metrics.push({
				key: 'expenses',
				icon: TrendingDownIcon,
				iconColor: 'var(--cobudget-error)',
				label: this.$texts.dashboard.expenses(),
				value: this.formatNegativeMetric(this.totalExpense),
				valueClass: 'negative',
				details: monthlyDetails(this.averageExpense, this.currentMonthExpense, this.formatNegativeMetric, 'negative'),
			})

			if (this.$enableIncomes) {
				metrics.push({
					key: 'balance',
					icon: WalletIcon,
					iconColor: 'var(--cobudget-primary, var(--color-primary-element))',
					label: this.$texts.dashboard.balance(),
					value: this.formatSignedMetric(this.balance),
					valueClass: this.signedMetricClass(this.balance),
					details: monthlyDetails(this.averageBalance, this.currentMonthBalance, this.formatSignedMetric, this.signedMetricClass),
				})
			}

			if (this.showImportantCard) {
				metrics.push({
					key: 'important',
					icon: StarIcon,
					iconColor: 'var(--cobudget-warning, #ffc92b)',
					label: this.$texts.labels.important(),
					value: this.formatSignedMetric(this.totalImportantPayments),
					valueClass: this.signedMetricClass(this.totalImportantPayments),
					details: monthlyDetails(this.averageImportantPayments, this.currentMonthImportantPayments, this.formatSignedMetric, this.signedMetricClass),
				})
			}

			if (this.showReviewCard) {
				metrics.push({
					key: 'review',
					icon: ClipboardCheckIcon,
					iconColor: 'var(--cobudget-error)',
					label: this.$texts.labels.review(),
					value: this.formatSignedMetric(this.totalReviewPayments),
					valueClass: this.signedMetricClass(this.totalReviewPayments),
					details: monthlyDetails(this.averageReviewPayments, this.currentMonthReviewPayments, this.formatSignedMetric, this.signedMetricClass),
				})
			}

			if (this.showFixedCostsCard) {
				metrics.push({
					key: 'fixed-costs',
					icon: BankOutlineIcon,
					iconColor: 'var(--cobudget-label-fixed-cost-color)',
					label: this.$texts.labels.fixedCosts(),
					value: this.formatNegativeMetric(this.totalFixedCosts),
					valueClass: 'negative',
					details: monthlyDetails(this.averageFixedCosts, this.currentMonthFixedCosts, this.formatNegativeMetric, 'negative'),
				})
			}

			if (this.showChildRelatedCard) {
				metrics.push({
					key: 'children',
					icon: AccountChildIcon,
					iconColor: 'var(--cobudget-label-children-color)',
					label: this.$texts.labels.children(),
					value: this.formatSignedMetric(this.totalChildRelated),
					valueClass: this.signedMetricClass(this.totalChildRelated),
					details: monthlyDetails(this.averageChildRelated, this.currentMonthChildRelated, this.formatSignedMetric, this.signedMetricClass),
				})
			}

			if (this.showSubscriptionsCard) {
				metrics.push({
					key: 'subscriptions',
					icon: SyncIcon,
					iconColor: 'var(--cobudget-label-subscription-color)',
					label: this.$texts.labels.subscription(),
					value: this.formatNegativeMetric(this.totalSubscriptions),
					valueClass: 'negative',
					details: monthlyDetails(this.averageSubscriptions, this.currentMonthSubscriptions, this.formatNegativeMetric, 'negative'),
				})
			}

			if (this.showTaxRelevantCard) {
				metrics.push({
					key: 'tax-relevant',
					icon: ReceiptTextCheckOutlineIcon,
					iconColor: 'var(--cobudget-primary, var(--color-primary-element))',
					label: this.$texts.labels.taxRelevant(),
					value: this.formatSignedMetric(this.totalTaxRelevant),
					valueClass: this.signedMetricClass(this.totalTaxRelevant),
					details: monthlyDetails(this.averageTaxRelevant, this.currentMonthTaxRelevant, this.formatSignedMetric, this.signedMetricClass),
				})
			}
			if (this.showInsuranceCard) {
				metrics.push({
					key: 'insurance',
					icon: ShieldCheckOutlineIcon,
					iconColor: 'var(--cobudget-label-insurance-color)',
					label: this.$texts.labels.insurance(),
					value: this.formatSignedMetric(this.totalInsurance),
					valueClass: this.signedMetricClass(this.totalInsurance),
					details: monthlyDetails(this.averageInsurance, this.currentMonthInsurance, this.formatSignedMetric, this.signedMetricClass),
				})
			}

			const balanceIndex = metrics.findIndex(metric => metric.key === 'balance')
			if (balanceIndex >= 0) { const [balance] = metrics.splice(balanceIndex, 1); metrics.splice(this.showBudgetCard ? 1 : 0, 0, balance) }
			return metrics.map(metric => ({ ...metric, route: this.summaryLocation(metric.key), details: metric.details.filter(detail => (detail.key !== 'current-month' || this.showCurrentMonthSummary) && (detail.key !== 'average' || this.showMonthlyAverage)) }))
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
		averageInsurance() {
			return this.averageStats.insurance;
		},
		showIncomeCard() {
			return this.$enableIncomes && this.tagCounts.income > 0;
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
		showInsuranceCard() {
			return this.$enableInsurance && this.tagCounts.insurance > 0;
		},
		periodBudgetGoals() {
			return paymentPeriodBudgetGoals(this.budgetGoals, this.filters, this.currentPeriodDate)
		},
		showBudgetCard() {
			return this.$enableBudgetGoals && this.periodBudgetGoals.length > 0;
		},
		visibleBudgetGoals() {
			const statusRank = { exceeded: 0, warning: 1, ok: 2 };
			return [...this.periodBudgetGoals]
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
			return this.periodBudgetGoals.filter(goal => ['warning', 'exceeded'].includes(goal.evaluation?.status)).length;
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
			if (this.filters.tags === 'future') return this.$texts.dashboard.planned()
			if (this.filters.type === 'income') return this.$texts.labels.incomePayments()
			if (this.filters.type === 'expense') return this.$texts.dashboard.expenses()
			return this.$texts.labels.allPayments()
		},
		newPaymentActionLabel() {
			if (this.filters.tags === 'future') return this.$texts.entry.planPayment();
			return this.$enableIncomes && this.defaultEntryType === 'income'
				? this.$texts.entry.newIncome()
				: this.$texts.areaDetail.newPayment();
		},
		defaultEntryType() {
			return this.filters.type === 'income' || this.$route.query.filter === 'income'
				? 'income'
				: 'expense';
		},
		canExportEntries() {
			return Number(this.pagination.total || 0) > 0;
		},
		canCopyEntriesToClipboard() {
			const total = Number(this.pagination.total || 0);
			return total > 0 && total <= ENTRY_CLIPBOARD_EXPORT_LIMIT;
		},
		clipboardExportLimitLabel() {
			return new Intl.NumberFormat(getAppLocale()).format(ENTRY_CLIPBOARD_EXPORT_LIMIT);
		},
		clipboardExportDescription() {
			if (!this.canExportEntries || this.canCopyEntriesToClipboard) {
				return undefined;
			}
			return this.$texts.payments.clipboardExportLimitHint(this.clipboardExportLimitLabel);
		}
	},
	mounted() {
		this.fetchReminderSummary()
		this.reminderSummaryTimer = window.setInterval(() => { this.reminderSummaryNow = Date.now() }, 60000)
		window.addEventListener('cobudget-reminders-changed', this.fetchReminderSummary)
		this.handleRouteQueryChange()
		if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
			this.mobilePaginationMedia = window.matchMedia('(max-width: 768px)')
			this.mobilePaginationMedia.addEventListener?.('change', this.onMobilePaginationViewportChange)
		}
		window.addEventListener('entry-saved', this.onEntrySaved)
		window.addEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
		window.addEventListener('settings-closed', this.onSettingsClosed);
		window.addEventListener('keydown', this.onPaginationKeydown)
		window.addEventListener(PRINT_REPORT_EVENT, this.printEntries)
		window.addEventListener('focus', this.handleCurrentPeriodResume)
		document.addEventListener('visibilitychange', this.handleCurrentPeriodVisibilityChange)
		this.scheduleCurrentPeriodRefresh()
	},
	beforeUnmount() {
		this.reminderSummaryRequest++
		this.printRequestId++
		this.isPrinting = false
		window.clearInterval(this.reminderSummaryTimer)
		window.removeEventListener('cobudget-reminders-changed', this.fetchReminderSummary)
		this.confirmDialog = null
		this.closeEntryHistory()
		window.removeEventListener('entry-saved', this.onEntrySaved)
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
		window.removeEventListener('settings-closed', this.onSettingsClosed);
		window.removeEventListener('keydown', this.onPaginationKeydown)
		window.removeEventListener(PRINT_REPORT_EVENT, this.printEntries)
		window.removeEventListener('focus', this.handleCurrentPeriodResume)
		document.removeEventListener('visibilitychange', this.handleCurrentPeriodVisibilityChange)
		this.mobilePaginationMedia?.removeEventListener?.('change', this.onMobilePaginationViewportChange)
		this.clearCurrentPeriodRefreshTimer()
	},
	watch: {
		'$enableReminders': 'fetchReminderSummary',
		'$route.fullPath': 'handleRouteQueryChange'
	},
	methods: {
		async fetchReminderSummary() {
			const request = ++this.reminderSummaryRequest
			this.reminderSummaryNow = Date.now()
			if (!this.$enableReminders) { this.summaryReminders = []; return }
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/reminders'), { skipWorkspaceHeader: true })
				if (request === this.reminderSummaryRequest) this.summaryReminders = response.data.reminders || []
			} catch (error) {
				if (request !== this.reminderSummaryRequest) return
				this.summaryReminders = []
				showRequestError(error, this.$texts.reminders.loadError(), 'Failed to fetch reminder summary')
			}
		},
		selectQuickLabel(label) {
			this.onFiltersUpdate(withPaymentLabels(this.filters, [label]))
		},
		selectPaymentPeriod(selection) {
			this.onFiltersUpdate(paymentPickerFilters(this.filters, selection))
		},
		groupReportLocation(group) {
			return paymentGroupReportTarget(group, this.filters, {}, this.currentPeriodDate)
		},
		summaryLocation(key) {
			const target = paymentSummaryTarget(key, this.filters, this.currentPeriodDate)
			if (!target.filters) return target
			return { name: target.name, query: { ...serializePersonalEntryListRouteQuery({ filters: target.filters, sortBy: this.sortBy, sortDir: this.sortDir }), timeRange: target.filters.timeRange } }
		},
		annualReturnRoute,
		currentPeriodKey(date) {
			const year = String(date.getFullYear()).padStart(4, '0')
			const month = String(date.getMonth() + 1).padStart(2, '0')
			return `${year}-${month}`
		},
		handleCurrentPeriodResume() {
			this.refreshCurrentPeriod()
		},
		handleCurrentPeriodVisibilityChange() {
			if (!document.hidden) {
				this.refreshCurrentPeriod()
			}
		},
		clearCurrentPeriodRefreshTimer() {
			if (this.currentPeriodRefreshTimer) {
				window.clearTimeout(this.currentPeriodRefreshTimer)
				this.currentPeriodRefreshTimer = null
			}
		},
		scheduleCurrentPeriodRefresh(now = new Date()) {
			this.clearCurrentPeriodRefreshTimer()
			const nextDay = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1)
			const delay = Math.max(0, nextDay.getTime() - now.getTime()) + 100
			this.currentPeriodRefreshTimer = window.setTimeout(() => {
				this.currentPeriodRefreshTimer = null
				this.refreshCurrentPeriod()
			}, delay)
		},
		refreshCurrentPeriod(now = new Date()) {
			this.fetchReminderSummary()
			const periodChanged = this.currentPeriodKey(now) !== this.currentPeriodKey(this.currentPeriodDate)
			this.currentPeriodDate = now
			this.scheduleCurrentPeriodRefresh(now)
			if (periodChanged) {
				this.fetchData()
			}
		},
		async openEntryHistory(entry) {
			if (!entry) return
			const requestId = ++this.entryHistoryRequestId
			this.entryHistoryEntry = entry
			this.entryHistoryError = ''
			this.entryHistoryOpen = true
			this.entryHistoryLoading = true
			this.entryHistoryRows = []
			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/entries/${entry.id}/history`))
				if (requestId !== this.entryHistoryRequestId) return
				this.entryHistoryRows = Array.isArray(response.data?.history) ? response.data.history : []
			} catch (error) {
				if (requestId === this.entryHistoryRequestId) this.entryHistoryError = extractError(error, this.$texts.entry.historyFetchError())
			} finally {
				if (requestId === this.entryHistoryRequestId) this.entryHistoryLoading = false
			}
		},
		closeEntryHistory() {
			this.entryHistoryRequestId++
			this.entryHistoryError = ''
			this.entryHistoryEntry = null
			this.entryHistoryOpen = false
			this.entryHistoryLoading = false
			this.entryHistoryRows = []
		},
		openConfirm({ title, message, confirmLabel, action, errorMessage }) {
			if (this.confirmDialog) return
			this.confirmDialog = { title, message, confirmLabel, action, errorMessage, route: this.$route.fullPath, busy: false, error: '' }
		},
		closeActionConfirm() {
			if (!this.confirmDialog?.busy) this.confirmDialog = null
		},
		async confirmAction() {
			const dialog = this.confirmDialog
			if (!dialog || dialog.busy) return
			if (dialog.route !== this.$route.fullPath) {
				this.confirmDialog = null
				return
			}
			dialog.busy = true
			dialog.error = ''
			try {
				await dialog.action(dialog)
				if (this.confirmDialog === dialog) this.confirmDialog = null
			} catch (error) {
				if (this.confirmDialog === dialog) dialog.error = extractError(error, dialog.errorMessage)
			} finally { dialog.busy = false }
		},
		openBudgetGoals() {
			this.$router.push({ name: 'budgets' })
		},
		applyEntryPageSize(value = this.$entriesPerPage) {
			this.pagination.limit = normalizeEntryPageSize(value)
			this.pagination.offset = 0
		},
		isMobileViewport() {
			if (this.mobilePaginationMedia) {
				return this.mobilePaginationMedia.matches
			}
			return typeof window !== 'undefined'
				&& typeof window.matchMedia === 'function'
				&& window.matchMedia('(max-width: 768px)').matches
		},
		onMobilePaginationViewportChange(event) {
			if (event.matches && !this.isPaymentsView && this.pagination.offset > 0) {
				this.pagination.offset = 0
				this.scrollEntriesToTop()
				this.fetchData()
			}
		},
		onPaginationKeydown(event) {
			if ((!this.isPaymentsView && this.isMobileViewport())
				|| shouldIgnorePaginationKeydown(event)
				|| this.pagination.total <= this.pagination.limit) {
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
			this.printRequestId++
			this.isPrinting = false
			this.confirmDialog = null
			this.closeEntryHistory()
			const routeState = parsePersonalEntryListRouteQuery(this.$route.query, this.$entriesPerPage)
			this.filters = defaultPaymentPeriod(this.$route.query, routeState.filters, this.isPaymentsView)
			this.sortBy = routeState.sortBy
			this.sortDir = routeState.sortDir
			this.applyEntryPageSize(this.isPaymentsView ? routeState.limit : this.$entriesPerPage)
			if (this.isPaymentsView) {
				this.pagination.offset = entryOffsetFromPage(routeState.page, this.pagination.limit)
			}
			this.fetchData()
		},
		updateDateRangeFilters() {
			if (this.filters.timeRange === 'custom') return
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
			let isInsurance = null;
			let hasReminder = this.$enableReminders !== false && this.filters.hasReminder === 'true' ? true : null;
			let hasAttachment = null;
			if (this.filters.hasAttachment === 'true') hasAttachment = true;
			if (this.filters.hasAttachment === 'false') hasAttachment = false;
			if (this.filters.tags === 'subscription') isSubscription = true;
			if (this.filters.tags === 'taxRelevant') isTaxRelevant = true;
			if (this.filters.tags === 'insurance') isInsurance = true;
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
				includeFuturePayments: this.filters.includeFuturePayments,
				includeSubcategories: this.filters.includeSubcategories,
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
				isInsurance: isInsurance,
				hasReminder: hasReminder,
				hasAttachment: hasAttachment,
				hashtagId: this.filters.hashtagId,
				isFuturePayments: this.filters.tags === 'future',
				...entryNarrowingQueryParams(this.filters),
			};

			if (includePagination) {
				params.limit = this.pagination.limit;
				params.offset = this.pagination.offset;
			}

			return params;
		},
		async fetchData() {
			const requestId = ++this.fetchRequestId
			try {
				this.userName = window.OC && window.OC.currentUser ? window.OC.currentUser.displayName || window.OC.currentUser.uid : 'User'
				const params = this.buildEntryQueryParams(true);
				const availabilityKey = JSON.stringify({ ...params, limit: null, offset: null, sortBy: null, sortDir: null, hasReminder: null, hasAttachment: null })
				if (this.filterAvailabilityKey !== availabilityKey) {
					this.filterAvailability = null
					this.filterAvailabilityKey = availabilityKey
				}

				const dashboardRes = await axios.get(generateUrl('/apps/cobudget/api/dashboard'), { params });
				if (requestId !== this.fetchRequestId) {
					return
				}
				const dashboardData = dashboardRes.data || {};
				const lookups = dashboardData.lookups || {};

				this.entries = dashboardData.entries || [];
				this.filterAvailability = dashboardData.filterAvailability || null;
				this.dateGroups = dashboardData.dateGroups || null;
				this.pagination.total = dashboardData.total || 0;
				if (this.isPaymentsView && this.currentPage > this.totalPages) {
					this.goToPaymentsPage(this.totalPages)
					return
				}
				this.dashboardMetrics = normalizeDashboardMetrics(dashboardData.metrics || {});
				this.paymentYears = Array.isArray(dashboardData.years) ? dashboardData.years : [];
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
				if (!this.isPaymentsView) {
					await this.fetchBudgetGoals()
				}
			} catch (e) {
				if (requestId === this.fetchRequestId) {
					showRequestError(e, this.$texts.dashboard.paymentsLoadError(), 'Failed to fetch dashboard data')
				}
			}
		},
		showCsvMenu(open) {
			this.csvMenuOpen = open
			this.$nextTick(() => this.$refs[open ? 'csvBack' : 'csvTrigger']?.$el?.querySelector('button')?.focus())
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
		async saveEntriesInNextcloud() {
			if (this.isExporting) {
				return
			}
			const exportParams = { ...this.buildEntryQueryParams(false) }
			this.isExporting = true
			try {
				await saveReportExport(
					'cobudget-zahlungen.csv',
					async () => {
						const response = await axios.get(generateUrl('/apps/cobudget/api/entries/export'), {
							params: exportParams,
							responseType: 'text',
						})
						return {
							filename: filenameFromDisposition(response?.headers?.['content-disposition'], 'cobudget-zahlungen.csv'),
							contents: String(response?.data ?? ''),
						}
					},
					this.$texts,
				)
			} catch (e) {
				showRequestError(e, this.$texts.payments.saveInNextcloudError(), 'Failed to export entries')
			} finally {
				this.isExporting = false
			}
		},
		async copyEntriesToClipboard() {
			if (this.isExporting) {
				return
			}
			if (!this.canCopyEntriesToClipboard) {
				showToast(this.$texts.payments.clipboardExportLimitError(this.clipboardExportLimitLabel), 'error')
				return
			}
			const exportParams = {
				...this.buildEntryQueryParams(false),
				clipboard: true,
			}
			this.isExporting = true
			try {
				const contents = axios.get(generateUrl('/apps/cobudget/api/entries/export'), {
					params: exportParams,
					responseType: 'text',
				}).then(response => csvToClipboardText(response?.data))
				await copyReportText(contents)
				showToast(this.$texts.common.copiedToClipboard())
			} catch (e) {
				showRequestError(e, this.$texts.payments.copyToClipboardError(), 'Failed to export entries')
			} finally {
				this.isExporting = false
			}
		},
		openMasterDataSettings(section) {
			this.$router.push({ name: 'settings', query: { section } })
		},
		printFilterLabels() {
			const chips = this.$refs.filterChips?.chips
			if (!Array.isArray(chips)) {
				return []
			}
			return chips
				.filter(chip => chip && chip.key !== 'timeRange' && chip.label)
				.map(chip => chip.label)
		},
		async printEntries() {
			if (this.isPrinting || !this.canExportEntries) {
				return
			}
			const requestId = ++this.printRequestId
			const printParams = { ...this.buildEntryQueryParams(false) }
			const title = this.headerTitle
			const periodLabel = this.paymentPeriodLabel
			const filterLabels = this.printFilterLabels()
			const typeFilter = this.filters.type
			const isFutureFilter = this.filters.tags === 'future'
			const sortBy = this.sortBy
			this.isPrinting = true
			try {
				const entries = await loadReportPayments(printParams, () => requestId === this.printRequestId)
				if (!Array.isArray(entries) || requestId !== this.printRequestId) {
					return
				}
				await printPaymentsOverview(document, {
					title,
					periodLabel,
					filterLabels,
					entries,
					currency: this.$currency,
					enableIncomes: this.$enableIncomes,
					enableProjects: this.$enableProjects,
					typeFilter,
					isFutureFilter,
					sortBy,
					projects: this.projects,
					labelSettings: {
						important: this.$enableImportantPayments,
						review: this.$enableReviewPayments,
						fixedCosts: this.$enableFixedCosts,
						childRelated: this.$enableChildRelated,
						subscriptions: this.$enableSubscriptions,
						taxRelevant: this.$enableTaxRelevant,
						insurance: this.$enableInsurance,
					},
					amountResolver: entry => this.getEntryPersonalAmount(entry),
					formatMoney: amount => this.$formatMoney(amount),
					formatSignedMoney: amount => this.$formatSignedMoney(amount),
					texts: this.$texts,
					locale: getAppLocale(),
				})
			} catch (e) {
				if (requestId === this.printRequestId) {
					showRequestError(e, this.$texts.payments.printError(), 'Failed to print entries')
				}
			} finally {
				if (requestId === this.printRequestId) {
					this.isPrinting = false
				}
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
		onEntrySaved() {
			this.fetchReminderSummary()
			this.fetchData();
		},
		onRemoteDataChanged(event) {
			if (changeTouches(event?.detail, ['reminders', 'entries', 'projects'])) this.fetchReminderSummary()
			if (changeTouches(event?.detail, ['entries', 'projects', 'budgets'])) {
				this.fetchData()
			}
		},
		onFiltersUpdate(newFilters) {
			this.printRequestId++
			this.isPrinting = false
			this.filters = {
				...createPersonalEntryFilters(),
				...newFilters,
			}
			this.pagination.offset = 0
			this.scrollEntriesToTop()
			this.replaceEntryListRoute({ page: 1 })
		},
		resetFilters() {
			this.onFiltersUpdate({ ...createPersonalEntryFilters(), annualReturn: this.filters.annualReturn,
				timeRange: this.isPaymentsView ? 'all' : 'currentYear',
			})
		},
		toggleSort(col) {
			this.printRequestId++
			this.isPrinting = false
			if (this.sortBy === col) {
				this.sortDir = this.sortDir === 'desc' ? 'asc' : 'desc'
			} else {
				this.sortBy = col
				this.sortDir = 'desc'
			}
			this.pagination.offset = 0
			this.scrollEntriesToTop()
			this.replaceEntryListRoute({ page: 1 })
		},
		prevPage() {
			if (this.pagination.offset > 0) {
				if (this.isPaymentsView) {
					this.goToPaymentsPage(this.currentPage - 1)
					return
				}
				this.pagination.offset = Math.max(0, this.pagination.offset - this.pagination.limit)
				this.scrollEntriesToTop()
				this.fetchData()
			}
		},
		nextPage() {
			if (this.pagination.offset + this.pagination.limit < this.pagination.total) {
				if (this.isPaymentsView) {
					this.goToPaymentsPage(this.currentPage + 1)
					return
				}
				this.pagination.offset += this.pagination.limit
				this.scrollEntriesToTop()
				this.fetchData()
			}
		},
		replaceEntryListRoute({ page = this.currentPage } = {}) {
			const query = serializePersonalEntryListRouteQuery({
				filters: this.filters,
				sortBy: this.sortBy,
				sortDir: this.sortDir,
				page,
				limit: this.pagination.limit,
				includePagination: this.isPaymentsView,
			})
			query.timeRange = this.filters.timeRange
			if (personalEntryListQueriesEqual(this.$route.query, query) && this.$route.query.timeRange === query.timeRange) {
				this.fetchData()
				return
			}
			this.$router.replace({
				name: this.$route.name,
				params: this.$route.params,
				query,
			}).catch(() => this.fetchData())
		},
		goToPaymentsPage(page) {
			const targetPage = Math.min(this.totalPages, Math.max(1, page))
			this.pagination.offset = entryOffsetFromPage(targetPage, this.pagination.limit)
			this.scrollEntriesToTop()
			this.replaceEntryListRoute({ page: targetPage })
		},
		openPaymentsView() {
			const query = serializePersonalEntryListRouteQuery({
				filters: this.filters,
				sortBy: this.sortBy,
				sortDir: this.sortDir,
				page: this.currentPage + 1,
				limit: this.pagination.limit,
				includePagination: true,
			})
			this.$router.push({ name: 'payments', query: { ...query, timeRange: this.filters.timeRange } })
				.then(() => this.$nextTick(this.scrollPageToTop))
		},
		goBackToFinances() {
			const annual = annualReturnRoute(this.$route.query.annualReturn)
			if (annual) {
				this.$router.push(annual)
				return
			}
			const query = serializePersonalEntryListRouteQuery({
				filters: this.filters,
				sortBy: this.sortBy,
				sortDir: this.sortDir,
			})
			this.$router.push({ name: 'personal', query: { ...query, timeRange: this.filters.timeRange } })
				.then(() => this.$nextTick(this.scrollPageToTop))
		},
		scrollEntriesToTop() {
			this.$refs.entryTable?.scrollToTop?.()
			if (this.isMobileViewport()) {
				this.scrollPageToTop()
			}
		},
		scrollPageToTop() {
			const scrollTarget = this.$el?.closest?.('.app-content')
			if (!scrollTarget) {
				return
			}
			if (typeof scrollTarget.scrollTo === 'function') {
				scrollTarget.scrollTo({ top: 0, left: 0, behavior: 'auto' })
			} else {
				scrollTarget.scrollTop = 0
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
			return getAreaColorStyle(this.getProjectColor(id));
		},
		isSharedProject(id) {
			const p = this.projects.find(p => String(p.id) === String(id));
			return p && parseInt(p.member_count) > 1;
		},
		formatDate(timestamp) {
			if (!timestamp) return '-'
			return new Date(timestamp * 1000).toLocaleDateString(getAppLocale())
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

		async resolveEntrySource(entry) {
			if (!entry.is_locked || !(entry.editable_entry_id || entry.source_entry_id)) {
				return entry
			}

			try {
				const sourceId = Number(entry.editable_entry_id || entry.source_entry_id)
				const response = await axios.get(generateUrl(`/apps/cobudget/api/entries/${sourceId}`))
				return response.data?.entry || response.data
			} catch (error) {
				showRequestError(error, this.$texts.dashboard.paymentsLoadError(), 'Failed to fetch shared payment source')
				return null
			}
		},
		async editEntry(entry) {
			const editableEntry = await this.resolveEntrySource(entry)
			if (!editableEntry) return
			this.$emit('open-entry-sidebar', {
				entry: editableEntry,
				selectedEntryId: entry.id,
				projectId: editableEntry.project_id || null,
				isFuture: this.filters.tags === 'future'
			})
		},
		async duplicateEntry(entry) {
			const entryToDuplicate = await this.resolveEntrySource(entry)
			if (!entryToDuplicate) return
			this.$emit('open-entry-sidebar', { entryToDuplicate, projectId: entryToDuplicate.project_id || null, isFuture: this.filters.tags === 'future' })
		},
		deleteEntry(entry) {
			const isFuture = this.filters.tags === 'future' && Number(entry.original_date ?? entry.date ?? 0) <= Date.now() / 1000
			const entryId = entry.id
			const deleteId = Number(entry.editable_entry_id || entry.source_entry_id || entryId)
			this.openConfirm({
				title: isFuture ? this.$texts.entry.disableFuturePaymentTitle() : this.$texts.entry.deleteEntryTitle(),
				message: isFuture ? this.$texts.entry.disableFuturePaymentMessage() : this.$texts.entry.deleteEntryMessage(),
				confirmLabel: isFuture ? this.$texts.entry.disablePayment() : this.$texts.entry.deleteEntry(),
				errorMessage: isFuture ? this.$texts.entry.futurePaymentDisableError() : this.$texts.entry.entryDeleteError(),
				action: async (dialog) => {
					if (isFuture) {
						await axios.post(generateUrl(`/apps/cobudget/api/entries/${entryId}/stop-recurrence`))
						showToast(this.$texts.entry.futurePaymentDisabled())
					} else {
						await axios.delete(generateUrl(`/apps/cobudget/api/entries/${deleteId}`))
						showToast(this.$texts.entry.entryDeleted())
					}
					window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
					if (this.confirmDialog === dialog && dialog.route === this.$route.fullPath) {
						this.entries = this.entries.filter(e => e.id !== entryId)
						this.fetchData()
					}
				}
			})
		}
	}
}
</script>

<style scoped>
.payment-delete-message { margin: 0; white-space: pre-line; }
.personal-dashboard {
	min-width: 0;
	width: 100%;
}

@media (min-width: 769px) {
	.personal-dashboard {
		display: flex;
		flex-direction: column;
		height: 100%;
		min-height: 0;
		overflow: hidden;
	}

	.payments-page-header,
	.stats-row {
		flex: 0 0 auto;
	}

	.recent-activities {
		--payment-table-scrollbar-size: calc(var(--default-grid-baseline, 4px) * 2);

		display: flex;
		flex: 1 1 auto;
		flex-direction: column;
		min-width: 0;
		min-height: 0;
		overflow: hidden;
	}

	.recent-activities :deep(.entry-table-container) {
		flex: 1 1 auto;
		width: 100%;
		max-width: 100%;
		box-sizing: border-box;
		min-width: 0;
		min-height: 0;
		overflow: auto;
		overscroll-behavior: contain;
		scrollbar-color: transparent transparent;
		scrollbar-gutter: stable;
		scrollbar-width: thin;
	}

	.recent-activities :deep(.entry-table-container:hover),
	.recent-activities :deep(.entry-table-container:focus-within) {
		scrollbar-color: var(--color-border-dark, var(--color-border)) transparent;
	}

	.recent-activities :deep(.entry-table-container::-webkit-scrollbar) {
		width: var(--payment-table-scrollbar-size);
		height: var(--payment-table-scrollbar-size);
	}

	.recent-activities :deep(.entry-table-container::-webkit-scrollbar-track) {
		background: transparent;
	}

	.recent-activities :deep(.entry-table-container::-webkit-scrollbar-thumb) {
		border-radius: var(--border-radius-pill, 999px);
		background: transparent;
	}

	.recent-activities :deep(.entry-table-container:hover::-webkit-scrollbar-thumb),
	.recent-activities :deep(.entry-table-container:focus-within::-webkit-scrollbar-thumb) {
		background: var(--color-border-dark, var(--color-border));
	}

	.recent-activities :deep(.data-table thead) {
		position: sticky;
		top: 0;
		z-index: 2;
		background: var(--cobudget-surface);
	}

	.recent-activities :deep(.pagination-footer) {
		flex: 0 0 auto;
	}

	.empty-content-state {
		flex: 1 1 auto;
		min-height: 0;
	}
}

@media (min-width: 769px) {
	.personal-dashboard {
		box-sizing: border-box;
		width: calc(100% + var(--cobudget-content-inset-start) + var(--cobudget-content-inset-end));
		margin-inline-start: calc(0px - var(--cobudget-content-inset-start));
		margin-inline-end: calc(0px - var(--cobudget-content-inset-end));
		padding-inline-start: var(--cobudget-content-inset-start);
		padding-inline-end: var(--cobudget-content-inset-end);
	}
	.recent-activities {
		width: calc(100% + var(--cobudget-content-inset-start) + var(--cobudget-content-inset-end));
		max-width: none;
		margin-inline-start: calc(0px - var(--cobudget-content-inset-start));
		margin-inline-end: calc(0px - var(--cobudget-content-inset-end));
	}
	.recent-activities :deep(.pagination-footer) {
		box-sizing: border-box;
		padding-inline: calc(var(--default-grid-baseline, 4px) * 2);
	}
}

.filter-toggle-btn {
	margin-right: 4px;
}

.payments-page-header {
	--payment-toolbar-font-weight: var(--font-weight-element, 500);
	container: payments-toolbar / inline-size;
}
.payment-toolbar-period { max-inline-size: 10rem; font-weight: var(--payment-toolbar-font-weight); }
.payment-toolbar-period :deep(.report-period-caption) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
@container payments-toolbar (max-width: 840px) {
	.payments-page-header :deep(.payment-partner-shortcut) { display: none; }
}
@media (min-width: 769px) {
	@container payments-toolbar (max-width: 940px) {
		.payments-page-header.app-page-header--has-creation :deep(.payment-partner-shortcut) { display: none; }
	}
}
@container payments-toolbar (max-width: 620px) {
	.payments-page-header :deep(.payment-category-shortcut) { display: none; }
}
@container payments-toolbar (max-width: 460px) {
	.payment-toolbar-period { display: none; }
}
@container payments-toolbar (max-width: 400px) {
	.payments-page-header :deep(.payment-filter-caption) { display: none; }
}
@media (min-width: 769px) {
	@container payments-toolbar (max-width: 720px) {
		.payments-page-header.app-page-header--has-creation :deep(.payment-category-shortcut) { display: none; }
	}
	@container payments-toolbar (max-width: 560px) {
		.payments-page-header.app-page-header--has-creation .payment-toolbar-period { display: none; }
	}
	@container payments-toolbar (max-width: 500px) {
		.payments-page-header.app-page-header--has-creation :deep(.payment-filter-caption) { display: none; }
	}
}

@container payments-toolbar (max-width: 720px) {
	.payments-page-header:has(.payment-review-shortcut) :deep(.payment-category-shortcut) { display: none; }
}
@container payments-toolbar (max-width: 600px) {
	.payments-page-header :deep(.payment-review-caption) { display: none; }
}
@container payments-toolbar (max-width: 510px) {
	.payments-page-header:has(.payment-review-shortcut) .payment-toolbar-period { display: none; }
}
@container payments-toolbar (max-width: 450px) {
	.payments-page-header:has(.payment-review-shortcut) :deep(.payment-filter-caption) { display: none; }
}
@container payments-toolbar (max-width: 350px) {
	.payments-page-header :deep(.payment-review-shortcut) { display: none; }
}
@media (min-width: 769px) {
	@container payments-toolbar (max-width: 820px) {
		.payments-page-header.app-page-header--has-creation:has(.payment-review-shortcut) :deep(.payment-category-shortcut) { display: none; }
	}
	@container payments-toolbar (max-width: 700px) {
		.payments-page-header.app-page-header--has-creation :deep(.payment-review-caption) { display: none; }
	}
	@container payments-toolbar (max-width: 610px) {
		.payments-page-header.app-page-header--has-creation:has(.payment-review-shortcut) .payment-toolbar-period { display: none; }
	}
	@container payments-toolbar (max-width: 550px) {
		.payments-page-header.app-page-header--has-creation:has(.payment-review-shortcut) :deep(.payment-filter-caption) { display: none; }
	}
	@container payments-toolbar (max-width: 450px) {
		.payments-page-header.app-page-header--has-creation :deep(.payment-review-shortcut) { display: none; }
	}
}


.mobile-more-payments-button,
.mobile-more-payments-button.button-vue {
	display: none !important;
}

.mobile-more-payments-footer {
	display: none;
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

.payments-page-header :deep(.app-page-header__title) { white-space: nowrap; overflow: visible; }
.payments-page-header :deep(.app-page-header__title :is(.report-period-caption, .report-period-static)) {
	min-inline-size: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.summary-strip { display: flex; flex: 0 0 auto; align-items: center; min-width: 0; margin-inline: calc(-2 * var(--default-grid-baseline, 4px)); margin-bottom: var(--cobudget-content-gap); }
.personal-dashboard:has(> .payment-filter-bar--chips) > :is(.summary-strip, .mobile-finance-summary) { margin-block-end: 0; }
.stats-row { display: flex; flex: 1 1 auto; min-width: 0; gap: var(--cobudget-summary-card-gap); flex-wrap: nowrap; padding: 2px 0 8px; }
.stat-card[data-cobudget-card] {
	box-sizing: border-box;
	flex: 0 0 var(--cobudget-summary-card-width);
	width: var(--cobudget-summary-card-width);
	min-width: var(--cobudget-summary-card-width);
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: calc(var(--default-grid-baseline, 4px) * 2);
	border: 0;
	border-radius: var(--border-radius-element);
	background: transparent;
	box-shadow: none;
	color: inherit;
	text-decoration: none;
	cursor: pointer;
}
.stat-card:hover, .stat-card:focus-visible { background: var(--color-background-hover); }
.stat-card:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: -2px; }
.stat-card * { cursor: inherit; }

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

.stat-value-group {
	display: flex;
	flex: 0 0 auto;
	flex-direction: column;
	align-items: flex-end;
}

.stat-value-label {
	display: none;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast));
	font-size: var(--cobudget-font-xs);
	font-weight: 600;
	line-height: 1.2;
}

.stat-icon {
	position: relative;
	inset-inline-start: calc(var(--default-grid-baseline, 4px) * -0.5);
	width: calc(var(--default-grid-baseline, 4px) * 5);
	height: calc(var(--default-grid-baseline, 4px) * 5);
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
}

.period-card-icon {
	color: var(--color-primary, #01679e);
}

.stat-label {
	font-size: var(--cobudget-font-caption);
	color: var(--color-text-maxcontrast, #888);
	text-transform: uppercase;
	letter-spacing: 0.5px;
	font-weight: 600;
}

.stat-value {
	font-size: var(--cobudget-font-metric-compact);
	font-weight: 700;
	font-variant-numeric: tabular-nums;
	text-align: right;
	white-space: nowrap;
}

.stat-value.positive {
	color: var(--cobudget-success);
}

.stat-value.negative {
	color: var(--cobudget-error);
}

.stat-sub-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.stat-sub-line {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	font-size: var(--cobudget-font-caption);
	color: var(--cobudget-text, var(--color-main-text));
}

.reminder-summary-card .stat-value { color: var(--color-text-maxcontrast, #888); }

.reminder-summary-title { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.stat-sub-line span:last-child {
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
	font-weight: 600;
}

.budget-summary-list {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
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
	transition: background-color var(--cobudget-hover-duration) linear;
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
	background: var(--cobudget-table-sort-hover);
}

.sort-icon {
	display: inline-block;
	margin-left: 4px;
	font-size: var(--cobudget-font-sm);
}

.pagination-footer {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
	align-items: center;
	padding: calc(var(--default-grid-baseline, 4px) * 2);
	background: var(--cobudget-page-background, #fff);
}

.pagination-footer--single { grid-template-columns: 1fr; }
.pagination-footer > .btn-page:first-child { justify-self: start; }
.pagination-footer > .btn-page:last-child { justify-self: end; }
.pagination-footer > .page-info { text-align: center; }

.pagination-label { white-space: nowrap; }

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
	transition: background-color var(--cobudget-hover-duration) linear;
}

.clickable-row:hover {
	background: var(--cobudget-table-row-hover);
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
	background: var(--cobudget-table-row-hover);
	border-radius: var(--border-radius-large, 8px);
	border: 1px dashed var(--cobudget-border-strong, #ccc);
}

.mobile-only {
	display: none !important;
}

@media (max-width: 768px) {
	.payments-page-header :deep(.app-page-header__title:not(:has(.cobudget-page-title-with-back))) { padding-block: 0; }

	.payment-toolbar-period { max-inline-size: min(27vw, 10rem); }

	.pagination-footer--dashboard {
		display: none;
	}

	.mobile-more-payments-footer {
		display: flex;
		min-height: var(--cobudget-mobile-fab-pagination-lane, calc(var(--default-grid-baseline, 4px) * 19));
		align-items: center;
		justify-content: center;
	}

	.mobile-more-payments-button,
	.mobile-more-payments-button.button-vue {
		display: inline-flex !important;
		width: auto !important;
		min-height: var(--default-clickable-area, 44px) !important;
		margin: 0;
		padding-inline: calc(var(--default-grid-baseline, 4px) * 3) !important;
		justify-content: center;
		background-color: transparent !important;
		font-weight: 600;
	}

	.table-container {
		border: none;
		background: transparent;
		box-shadow: none;
	}

	.desktop-summary-cards {
		display: none !important;
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

	.pagination-footer:not(.pagination-footer--single) {
		padding-block-start: var(--cobudget-mobile-fab-pagination-lane, calc(var(--default-grid-baseline, 4px) * 19));
	}

	.pagination-footer:not(.pagination-footer--single) > .page-info {
		text-align: center;
		white-space: nowrap;
	}

	.btn-page {
		--default-clickable-area: var(--cobudget-mobile-touch-size, 44px);
	}

	.personal-dashboard--table-only .pagination-footer {
		gap: calc(var(--default-grid-baseline, 4px) * 2);
		margin-block-start: calc(var(--default-grid-baseline, 4px) * 2);
		padding: calc(var(--default-grid-baseline, 4px) * 2);
		border: 1px solid var(--cobudget-border, var(--color-border));
		border-radius: var(--border-radius-large, 8px);
		background: var(--cobudget-surface-muted, var(--color-background-hover));
	}

	.personal-dashboard--table-only .pagination-footer:not(.pagination-footer--single) {
		padding-block-start: calc(var(--default-grid-baseline, 4px) * 2);
	}

	.personal-dashboard--table-only .pagination-label {
		display: inline-flex;
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

.clickable-row:is(:focus-within, .is-selected) { transition: none; }
</style>
