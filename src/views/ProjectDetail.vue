<template>
	<div
		v-if="project"
		class="project-detail"
		:class="{ 'project-detail--table-only': isProjectPaymentsView }">
		<AppPageHeader class="detail-header">
			<template v-if="!isProjectPaymentsView && (!hideNewPaymentAction || (canSettleProject && hasBalancesToSettle))" #creation>
					<NcButton
						v-if="!hideNewPaymentAction"
						variant="primary"
						class="new-payment-main-button"
						:aria-label="$texts.areaDetail.newPayment()"
						:title="$texts.areaDetail.newPayment()"
						@click="openEntrySidebar">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						<span class="creation-label-desktop">{{ $texts.common.newItem() }}</span>
						<span class="btn-text">{{ $texts.areaDetail.newPayment() }}</span>
					</NcButton>
				<NcButton v-if="canSettleProject && hasBalancesToSettle" variant="primary"
					class="cobudget-native-button project-settle-header-btn" :title="$texts.areaDetail.settleArea()" @click="settleUp">
					<template #icon><CheckAllIcon :size="20" /></template>
					{{ $texts.areaDetail.settleArea() }}
				</NcButton>
			</template>
			<template #title>
				<span v-if="isProjectPaymentsView" class="cobudget-page-title-with-back">
					<NcButton
						variant="tertiary"
						class="cobudget-native-button cobudget-header-back-button"
						:aria-label="(annualReturnRoute($route.query.annualReturn) ? $texts.annual.back() : $texts.areaSettings.backToArea())"
						:title="(annualReturnRoute($route.query.annualReturn) ? $texts.annual.back() : $texts.areaSettings.backToArea())"
						@click="goBackToProjectOverview">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
					</NcButton>
					<ReportPeriodPicker navigation-only force-navigation area-navigation :period-enabled="false" :title="$texts.payments.title()" label="" />
				</span>
				<ReportPeriodPicker v-else navigation-only force-navigation area-navigation :title="project.name" :area="project" :period-enabled="false" label="" />
			</template>
			<template #actions>
				<NcButton v-if="!isProjectPaymentsView && canManageProject" variant="tertiary" class="cobudget-native-button edit-project-btn cobudget-toolbar-text-button project-settings-header-btn" @click="openProjectSettings"
					:aria-label="$texts.areaDetail.areaSettings()" :title="$texts.areaDetail.areaSettings()">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					<span class="btn-text">{{ $texts.areaDetail.areaSettings() }}</span>
				</NcButton>
				<AnchoredPopover placement="bottom-end" class="project-filter-header-btn">
					<template #trigger>
						<NcButton :aria-label="filterActionLabel" :title="filterActionLabel" :aria-description="hasActiveFilters ? $texts.filters.active() : undefined" variant="tertiary" class="cobudget-native-button" :class="{ 'cobudget-filter-active': hasActiveFilters }">
							<template #icon>
								<FilterVariantIcon :size="20" />
							</template>
							<span class="project-filter-caption">{{ $texts.filters.filter() }}</span>
						</NcButton>
					</template>
					<TableFilters :showStatusFilter="false" group-lookup-filters show-type-buttons show-amount :show-time-range="false" reset-time-range="all"
						:excluded-sections="['projectId']" :period-selection="areaPeriodSelection" :period-years="paymentYears"
						:period-active="filters.timeRange !== 'all'" :categories="categories" :payment-partners="paymentPartners"
						:hashtags="hashtags" :initial-filters="filters" :filter-availability="filterAvailability"
						@update:filters="onFiltersUpdate" />
				</AnchoredPopover>
				<NcActions class="cobudget-action-menu" variant="tertiary" force-menu placement="bottom-end" :aria-label="$texts.filters.listActions()">
					<NcActionButton class="cobudget-action-menu-option" v-if="!isProjectPaymentsView && canSettleProject && hasBalancesToSettle" :close-after-click="true" @click="settleUp">
						<template #icon><CheckAllIcon :size="20" /></template>
						{{ $texts.areaDetail.settleArea() }}
					</NcActionButton>
					<NcActionButton class="cobudget-action-menu-option" v-if="!isProjectPaymentsView && canManageProject" :close-after-click="true" @click="openProjectSettings">
						<template #icon><PencilIcon :size="20" /></template>
						{{ $texts.areaDetail.areaSettings() }}
					</NcActionButton>
					<NcActionButton class="cobudget-action-menu-option" v-if="!isProjectPaymentsView && showSettlementControls" :close-after-click="true" @click="openProjectSettlements">
						<template #icon><HistoryIcon :size="20" /></template>
						{{ $texts.areaDetail.settlements() }}
					</NcActionButton>
					<NcActionButton class="cobudget-action-menu-option" v-if="canExportEntries" :close-after-click="true" @click="exportEntries" :disabled="isExporting">
						<template #icon><DownloadIcon :size="20" /></template>
						{{ isExporting ? $texts.common.exportCsvBusy() : $texts.common.exportCsv() }}
					</NcActionButton>
					<NcActionButton class="cobudget-action-menu-option" v-if="!isProjectPaymentsView && canManageProject" :close-after-click="true" @click="toggleArchiveProject"
						:disabled="!!archiveBlockedReason || !!confirmDialog || settlingProject"
						:title="archiveBlockedReason">
						<template #icon><component :is="project.is_archived ? 'HistoryIcon' : 'ArchiveIcon'" :size="20" /></template>
						{{ project.is_archived ? $texts.areaSettings.unarchive() : $texts.areaSettings.archive() }}
					</NcActionButton>
					<NcActionButton class="cobudget-action-menu-option" v-if="!isProjectPaymentsView && canManageProject" :close-after-click="true" @click="deleteProject" :disabled="!!deleteBlockedReason || !!confirmDialog || settlingProject"
						:title="deleteBlockedReason">
						<template #icon><DeleteIcon :size="20" /></template>
						{{ $texts.common.delete() }}
					</NcActionButton>
				</NcActions>
			</template>
		</AppPageHeader>

		<div class="project-detail-scroll">
			<NcNoteCard v-if="projectError" type="error">
				<p>{{ projectError }}</p>
				<NcButton class="cobudget-native-button" variant="secondary" :disabled="loadingProject" @click="fetchProjectData">{{ $texts.common.retry() }}</NcButton>
			</NcNoteCard>
			<NcNoteCard v-if="exportError" type="error" :text="exportError" />
			<DraggableScroller v-if="!isProjectPaymentsView && projectDashboardCards.length > 0" class="stats-row project-stats-row" allow-link-drag>
			<router-link v-for="card in projectDashboardCards" :key="card.key" :to="projectReportLocation" :aria-description="card.tooltip" class="stat-card" data-cobudget-card="compact" :class="card.cardClass">
				<div class="stat-header">
					<div class="stat-title-group">
						<div class="stat-icon">
							<component :is="card.icon" :size="20" :fillColor="card.iconColor" />
						</div>
						<span class="stat-label">{{ card.label }}</span>
					</div>
					<TableTooltip :text="card.tooltip" passive>
						<span class="stat-value" :class="card.valueClass">{{ card.value }}</span>
					</TableTooltip>
				</div>
				<div v-if="hasAdditionalProjectMembers" class="stat-sub-info">
					<div class="stat-sub-line cobudget-form-hint">
						<span>{{ $texts.areaDetail.notSettled() }}</span>
						<span>{{ $texts.areaDetail.entryCount(card.metric.activeCount) }}</span>
					</div>
				</div>
			</router-link>
		</DraggableScroller>

		<!-- Members / Balances Section -->
			<div class="section members-section balances-section" v-if="!isProjectPaymentsView && showMemberBalances && project.balances && project.balances.length > 0">
			<div class="section-header">
				<h3 class="cobudget-section-heading members-heading">
					<AccountMultipleIcon :size="20" /> {{ $texts.memberBalances.title() }}
				</h3>
				<NcButton v-if="hasSettlementHistory" class="member-settlements-button cobudget-native-button" variant="tertiary" :aria-label="$texts.areaDetail.settlements()" :title="$texts.areaDetail.settlements()" @click="openProjectSettlements">
					<template #icon><HistoryIcon :size="20" /></template>
					{{ $texts.areaDetail.settlements() }}
				</NcButton>
			</div>

			<MemberBalanceList :balances="project.balances" :format-amount="formatCurrency">
				<template #member-extra="{ balance }">
					<AreaAdminIndicator v-if="balance.userId === project.owner_id" :label="$texts.areaSettings.areaAdmin()" />
				</template>
			</MemberBalanceList>
		</div>

			<div v-if="!isProjectPaymentsView && showSettlementControls && project.repaymentTransfers && project.repaymentTransfers.length > 0" class="section repayments-section">
			<div class="section-header">
				<h3 class="cobudget-section-heading">{{ $texts.areaDetail.repayments() }}</h3>
			</div>
			<RepaymentList :transfers="project.repaymentTransfers" :format-amount="formatCurrency" />
		</div>

		<!-- Active Entries -->
		<div class="section project-payments-section" :aria-busy="loadingEntries">
			<div v-if="!isProjectPaymentsView && (loadingEntries || entriesError || activeEntries.length > 0)" class="section-header"><h3 class="cobudget-section-heading">{{ $texts.payments.title() }}</h3></div>
			<PaymentFilterBar chips-only :filters="filters" default-time-range="all" :period-chip-label="areaPeriodChipLabel"
				:categories="categories" :payment-partners="paymentPartners" :hashtags="hashtags"
				@update:filters="onFiltersUpdate" />
			<div v-if="loadingEntries && activeEntries.length === 0" class="detail-loading cobudget-form-hint" role="status"><NcLoadingIcon :size="32" /><span>{{ $texts.common.loading() }}</span></div>
			<NcNoteCard v-else-if="entriesError" type="error">
				<p>{{ entriesError }}</p>
				<NcButton class="cobudget-native-button" variant="secondary" @click="fetchActiveEntries">{{ $texts.common.retry() }}</NcButton>
			</NcNoteCard>
			
				<EntryTable
					v-else-if="activeEntries.length > 0"
					ref="entryTable"
				mode="project"
				:entries="activeEntries"
				:date-groups="activeDateGroups"
				:group-report-resolver="groupReportLocation"
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
				:enable-insurance="$enableInsurance"
				:selected-entry-id="selectedEntryId"
				:actions-enabled="project.status !== 'archived'"
				:archived="project.status === 'archived'"
				:project-name-resolver="getProjectName"
				:project-style-resolver="getProjectTagStyle"
				:member-name-resolver="getMemberName"
				:show-paid-by="hasAdditionalProjectMembers"
				@sort="toggleSort"
				@row-click="onRowClick"
				@edit="editEntry"
				@duplicate="duplicateEntry"
				@delete="deleteEntry"
				@history="openEntryHistory">
				<template #pagination>
						<div
							v-if="!isProjectPaymentsView || totalActivePages > 1"
							class="pagination-footer"
							:class="{
								'pagination-footer--single': activePagination.total <= activePagination.limit,
								'pagination-footer--dashboard': !isProjectPaymentsView,
							}">
						<NcButton :aria-label="$texts.common.previous()" v-if="activePagination.total > activePagination.limit" variant="tertiary" class="cobudget-native-button btn-page" :style="{ visibility: activePagination.offset > 0 ? 'visible' : 'hidden' }" @click="prevActivePage">
							<template #icon><ArrowLeftIcon :size="20" /></template>
							<span class="pagination-label">{{ $texts.common.previous() }}</span>
						</NcButton>
							<span class="page-info">{{ activePaginationInfo }}</span>
						<NcButton :aria-label="$texts.common.next()" alignment="center-reverse" v-if="activePagination.total > activePagination.limit" variant="tertiary" class="cobudget-native-button btn-page" :style="{ visibility: activePagination.offset + activePagination.limit < activePagination.total ? 'visible' : 'hidden' }" @click="nextActivePage">
							<span class="pagination-label">{{ $texts.common.next() }}</span>
							<template #icon><ArrowRightIcon :size="20" /></template>
						</NcButton>
						</div>
						<div
							v-if="!isProjectPaymentsView && activePagination.total > activePagination.limit"
							class="mobile-more-payments-footer">
							<NcButton
								variant="tertiary-no-background"
								class="mobile-more-payments-button"
								@click="openProjectPaymentsView">
								{{ $texts.payments.showMore() }}
							</NcButton>
						</div>
					</template>
			</EntryTable>
			<NcEmptyContent
				v-else-if="!projectError"
				class="empty-content-state"
				:name="hasActiveFilters ? $texts.common.noFilteredEntriesTitle() : $texts.areaDetail.noOpenEntriesTitle()"
				:description="hasActiveFilters ? $texts.common.noFilteredEntriesDescription() : $texts.areaDetail.noOpenEntriesDescription()">
				<template #icon>
					<WalletIcon :size="64" />
				</template>
				<template #action>
					<NcButton v-if="hasActiveFilters" class="cobudget-native-button" variant="secondary" @click="resetFilters">
						{{ $texts.common.resetFilters() }}
					</NcButton>
					<NcButton v-else-if="!isProjectPaymentsView && project.status !== 'archived' && !hideNewPaymentAction" variant="primary" class="new-payment-main-button" @click="openEntrySidebar">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ $texts.areaDetail.newPayment() }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</div>
		</div>

		<DialogTransition :show="!!(entryHistoryOpen)" v-slot="{ returnFocus }">
			<EntryHistoryModal :container="null" :set-return-focus="returnFocus"
				:history="entryHistoryRows"
				:loading="entryHistoryLoading"
				:error="entryHistoryError"
				@retry="openEntryHistory(entryHistoryEntry)"
				@close="closeEntryHistory" />
		</DialogTransition>

		<DialogTransition :show="!!(showSettleConfirm)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog area-settlement-dialog cobudget-standard-dialog"
				size="normal"
				:name="$texts.areaDetail.settleArea()"
				:no-close="settlingProject" :close-on-click-outside="!settlingProject"
				@closing="closeSettleConfirm">
				<div :aria-busy="settlingProject">
					<p class="area-settlement-question">{{ $texts.areaDetail.settleMessage() }}</p>
					<SettlementSummary v-if="settlementPreview" class="area-settlement-summary" :area-name="project.name"
						:created-by="settlementAuthor.uid" :created-by-display-name="settlementAuthor.displayName || settlementAuthor.uid"
						:payment-count="settlementPreview.entryCount" :total="formatCurrency(settlementPreview.totalCents / 100)" />
					<NcFormGroup class="cobudget-form-group" :label="$texts.areaDetail.repayments()" aria-describedby="area-settlement-hint">
						<RepaymentList v-if="project.repaymentTransfers?.length" :transfers="project.repaymentTransfers" :format-amount="formatCurrency" />
						<p v-else class="cobudget-form-hint">{{ $texts.areaDetail.noRepaymentNeeded() }}</p>
						<NcNoteCard id="area-settlement-hint" class="area-settlement-hint" type="info" :text="$texts.areaDetail.settlementSuggestionSaved()" />
					</NcFormGroup>
					<NcNoteCard v-if="settleError" type="error" :text="settleError" />
				</div>
				<template #actions>
					<NcButton variant="tertiary" :disabled="settlingProject" @click="closeSettleConfirm">{{ $texts.common.cancel() }}</NcButton>
					<NcButton variant="primary" :disabled="settlingProject || !canSettleProject" @click="confirmSettleUp"><template #icon><NcLoadingIcon v-if="settlingProject" :size="20" /><CheckAllIcon v-else :size="20" /></template>{{ settlingProject ? $texts.common.waitBusy() : $texts.areaDetail.settleArea() }}</NcButton>
				</template>
			</NcDialog>
		</DialogTransition>

		<DialogTransition :show="!!(confirmDialog)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog area-action-dialog cobudget-standard-dialog"
				:name="confirmDialog.title" :no-close="confirmDialog.busy" :close-on-click-outside="!confirmDialog.busy"
				@closing="closeActionConfirm">
				<p class="area-action-message">{{ confirmDialog.message }}</p>
				<NcNoteCard v-if="confirmDialog.error" type="error" :text="confirmDialog.error" />
				<template #actions>
					<NcButton variant="tertiary" class="cobudget-native-button" :disabled="confirmDialog.busy" @click="closeActionConfirm">{{ $texts.common.cancel() }}</NcButton>
					<NcButton :variant="confirmDialog.confirmVariant === 'danger' ? 'error' : 'primary'" :disabled="confirmDialog.busy" @click="confirmAction">
						<template v-if="confirmDialog.busy" #icon><NcLoadingIcon :size="20" /></template>
						{{ confirmDialog.busy ? $texts.common.waitBusy() : confirmDialog.confirmLabel }}
					</NcButton>
				</template>
			</NcDialog>
		</DialogTransition>

	</div>
	<div v-else class="project-detail-state">
		<div v-if="loadingProject" class="detail-loading cobudget-form-hint" role="status"><NcLoadingIcon :size="32" /><span>{{ $texts.common.loading() }}</span></div>
		<NcNoteCard v-else-if="projectError" type="error">
			<p>{{ projectError }}</p>
			<NcButton class="cobudget-native-button" variant="secondary" @click="fetchProjectData">{{ $texts.common.retry() }}</NcButton>
		</NcNoteCard>
	</div>
</template>

<script>
import DialogTransition from '../components/DialogTransition.vue'
import { annualReturnRoute, reportPeriodLabel } from '../services/annualOverview'
import { paymentGroupReportTarget, paymentPickerSelection } from '../services/paymentSummary'
import { getAppLocale } from '../utils/formatMoney'
import { formatCalendarLabel } from '../utils/formatDate'
import PaymentFilterBar from '../components/PaymentFilterBar.vue'
import axios from '../services/http'
import { REMOTE_DATA_CHANGED_EVENT, changeAffectsProject, changeTouches } from '../services/dataSync'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import SettlementSummary from '../components/SettlementSummary.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import MemberBalanceList from '../components/MemberBalanceList.vue'
import RepaymentList from '../components/RepaymentList.vue'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import AnchoredPopover from '../components/AnchoredPopover.vue'
import TableFilters from '../components/TableFilters.vue'
import EntryTable from '../components/EntryTable.vue'
import EntryHistoryModal from '../components/EntryHistoryModal.vue'
import TableTooltip from '../components/TableTooltip.vue'
import AreaAdminIndicator from '../components/AreaAdminIndicator.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import CheckAllIcon from 'vue-material-design-icons/CheckAll.vue'
import FilterVariantIcon from 'vue-material-design-icons/FilterVariant.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import ChartBarIcon from 'vue-material-design-icons/ChartBar.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import WalletIcon from 'vue-material-design-icons/WalletOutline.vue'
import SyncIcon from 'vue-material-design-icons/Sync.vue'
import LockIcon from 'vue-material-design-icons/LockOutline.vue'
import StarIcon from 'vue-material-design-icons/StarOutline.vue'
import ClipboardCheckIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import AccountChildIcon from 'vue-material-design-icons/AccountChildOutline.vue'
import ReceiptTextCheckOutlineIcon from 'vue-material-design-icons/ReceiptTextCheckOutline.vue'
import ShieldCheckOutlineIcon from 'vue-material-design-icons/ShieldCheckOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import TrendingDownIcon from 'vue-material-design-icons/TrendingDown.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import DraggableScroller from '../components/DraggableScroller.vue'
import { normalizeEntryPageSize, shouldIgnorePaginationKeydown } from '../services/pagination'
import {
	createProjectEntryFilters,
	entryNarrowingQueryParams,
	entryOffsetFromPage,
	entryPageFromOffset,
	parseProjectEntryListRouteQuery,
	personalEntryListQueriesEqual,
	serializeProjectEntryListRouteQuery,
} from '../services/entryListRoute'
import { extractError, showToast } from '../services/notifications'
import { downloadBlobResponse } from '../services/downloads'
import { getAreaColorStyle } from '../utils/areaColor'

export default {
	name: 'ProjectDetail',
	components: { PaymentFilterBar, ReportPeriodPicker, DialogTransition, SettlementSummary, RepaymentList, HistoryIcon, DeleteIcon, NcDialog, NcFormGroup, NcNoteCard, NcLoadingIcon, AppPageHeader, NcButton, NcEmptyContent, MemberBalanceList, NcActions, NcActionButton, AnchoredPopover, EntryTable, EntryHistoryModal, TableTooltip, AreaAdminIndicator, FilterVariantIcon, AccountMultipleIcon, ChartBarIcon, ArchiveIcon, CheckAllIcon, PencilIcon, TrendingUpIcon, TrendingDownIcon, WalletIcon, SyncIcon, LockIcon, StarIcon, ClipboardCheckIcon, AccountChildIcon, ReceiptTextCheckOutlineIcon, ArrowLeftIcon, ArrowRightIcon, PlusIcon, ChevronUpIcon, CloseIcon, DownloadIcon, TableFilters, DraggableScroller },
	props: {
		id: {
			type: [Number, String],
			required: true
		},
		selectedEntryId: {
			type: [Number, String],
			default: null
		},
		hideNewPaymentAction: {
			type: Boolean,
			default: false
		}
	},
	emits: ['open-entry-sidebar', 'open-project', 'refresh-projects'],
	data() {
		return {
			project: null,
			loadingProject: true,
			projectError: '',
			loadingEntries: true,
			entriesError: '',
			exportError: '',
			activeEntries: [],
			filterAvailability: null,
			filterAvailabilityKey: '',
			activeDateGroups: null,
			categories: [],
			paymentPartners: [],
			hashtags: [],
			paymentYears: [],
			isExporting: false,
			showSettleConfirm: false,
			settlingProject: false,
			settleError: '',
			settleProjectId: null,
			filters: createProjectEntryFilters(),
			sortBy: 'date',
			sortDir: 'desc',
			activePagination: {
				limit: 25,
				offset: 0,
				total: 0
			},
			loadedProjectId: null,
			projectFetchRequestId: 0,
			entryFetchRequestId: 0,
			mobilePaginationMedia: null,
			entryHistoryOpen: false,
			entryHistoryError: '',
			entryHistoryEntry: null,
			entryHistoryRequestId: 0,
			entryHistoryLoading: false,
			entryHistoryRows: [],
			confirmDialog: null
		}
	},
	computed: {
		deleteBlockedReason() {
			if (!this.canManageProject) return this.$texts.areaSettings.ownerOnly();
			const reason = this.project?.settings_actions?.delete_block_reason;
			if (reason === null) return '';
			if (reason === 'payments_or_history') return this.$texts.areaSettings.deleteBlockedHistory();
			if (reason === 'budget_goals') return this.$texts.areaSettings.deleteBlockedBudget();
			return this.$texts.areaSettings.actionsUnavailable();
		},
		archiveBlockedReason() {
			if (!this.canManageProject) return this.$texts.areaSettings.ownerOnly();
			if (this.isTruthy(this.project?.is_archived)) return '';
			const reason = this.project?.settings_actions?.archive_block_reason;
			if (reason === null) return '';
			if (reason === 'open_shared_payments') return this.$texts.areaSettings.archiveBlockedWithOpenEntries();
			return this.$texts.areaSettings.actionsUnavailable();
		},

		isProjectPaymentsView() {
			return this.$route.name === 'project-payments'
		},
		currentActivePage() {
			return entryPageFromOffset(this.activePagination.offset, this.activePagination.limit)
		},
		totalActivePages() {
			return Math.max(1, Math.ceil(Number(this.activePagination.total || 0) / this.activePagination.limit))
		},
		activePaginationInfo() {
			if (this.isProjectPaymentsView) {
				return this.$texts.common.pageNumber(this.currentActivePage, this.totalActivePages)
			}
			return this.$texts.common.pageInfo(
				this.activePagination.offset + 1,
				Math.min(this.activePagination.offset + this.activePagination.limit, this.activePagination.total),
				this.activePagination.total,
			)
		},
		filterActionLabel() {
			return this.$texts.annual.filterPayments()
		},
		areaPeriodSelection() {
			const selection = paymentPickerSelection(this.filters)
			if (selection.timeRange !== 'all') return selection
			const current = new Date().getFullYear()
			return { ...selection, periodYears: [...new Set([current, current - 1, ...this.paymentYears])].sort((a, b) => a - b) }
		},
		areaPeriodChipLabel() {
			if (this.filters.timeRange === 'all') return ''
			return reportPeriodLabel(this.areaPeriodSelection, formatCalendarLabel,
				date => formatCalendarLabel(date, { day: 'numeric', month: 'short', year: 'numeric' }))
		},
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
		projectId() {
			return this.id;
		},
		hasEntries() {
			return this.activeEntries.length > 0;
		},
		settlementAuthor() {
			return getCurrentUser() || { uid: '', displayName: '' }
		},
		settlementPreview() {
			return this.project?.settlementPreview || null
		},
		hasBalancesToSettle() {
			return this.activeEntries.length > 0 || this.activePagination.total > 0;
		},
		canExportEntries() {
			return Number(this.activePagination.total || 0) > 0;
		},
		projectMemberCount() {
			if (this.project && Array.isArray(this.project.members)) {
				return new Set(this.project.members
					.map(member => String(member?.id || member?.user_id || member?.userId || '').trim())
					.filter(Boolean)).size;
			}
			if (this.project && Array.isArray(this.project.balances)) {
				return this.project.balances.length;
			}
			return parseInt(this.project?.member_count || 0, 10) || 0;
		},
		hasAdditionalProjectMembers() {
			if (this.project?.is_shared !== undefined) {
				return this.isTruthy(this.project.is_shared);
			}
			return this.projectMemberCount > 1;
		},
		showMemberBalances() {
			return this.hasAdditionalProjectMembers;
		},
		showSettlementControls() {
			return this.hasAdditionalProjectMembers;
		},
		hasSettlementHistory() {
			return Number(this.project?.settlementCount || 0) > 0;
		},
		canManageProject() {
			return this.isTruthy(this.project?.is_owner);
		},
		canSettleProject() {
			return this.canManageProject && this.showSettlementControls;
		},
		projectReportLocation() {
			return {
				name: 'annual-overview',
				query: {
					projectId: String(this.projectId),
					entryScope: this.hasAdditionalProjectMembers ? 'shared' : 'personal',
					timeRange: 'all',
				},
			}
		},
		projectDashboardCards() {
			const definitions = [
				{ key: 'income', label: this.$texts.common.income(), icon: TrendingUpIcon, iconColor: 'var(--cobudget-success)', mode: 'positive', cardClass: 'income-card', enabled: this.$enableIncomes },
				{ key: 'expense', label: this.$texts.common.expense(), icon: TrendingDownIcon, iconColor: 'var(--cobudget-error)', mode: 'negative', cardClass: 'expense-card', enabled: true },
				{ key: 'balance', label: 'Saldo', icon: WalletIcon, iconColor: '#0082c9', mode: 'signed', cardClass: 'total-card', enabled: this.$enableIncomes },
				{ key: 'important', label: this.$texts.labels.important(), icon: StarIcon, iconColor: '#ffc92b', mode: 'signed', cardClass: 'important-card', enabled: this.$enableImportantPayments },
				{ key: 'review', label: this.$texts.labels.review(), icon: ClipboardCheckIcon, iconColor: 'var(--cobudget-error)', mode: 'signed', cardClass: 'review-card', enabled: this.$enableReviewPayments },
				{ key: 'fixedCosts', label: this.$texts.labels.fixedCosts(), icon: LockIcon, iconColor: '#e67e22', mode: 'negative', cardClass: 'fixed-costs-card', enabled: this.$enableFixedCosts },
				{ key: 'childRelated', label: this.$texts.labels.children(), icon: AccountChildIcon, iconColor: '#0f766e', mode: 'signed', cardClass: 'child-card', enabled: this.$enableChildRelated },
				{ key: 'subscriptions', label: this.$texts.labels.subscription(), icon: SyncIcon, iconColor: '#8e44ad', mode: 'negative', cardClass: 'abos-card', enabled: this.$enableSubscriptions },
				{ key: 'taxRelevant', label: this.$texts.labels.taxRelevant(), icon: ReceiptTextCheckOutlineIcon, iconColor: '#0082c9', mode: 'signed', cardClass: 'tax-card', enabled: this.$enableTaxRelevant },
				{ key: 'insurance', label: this.$texts.labels.insurance(), icon: ShieldCheckOutlineIcon, iconColor: 'var(--color-primary-element)', mode: 'signed', cardClass: 'insurance-card', enabled: this.$enableInsurance },
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
		this.handleProjectRouteChange()
		if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
			this.mobilePaginationMedia = window.matchMedia('(max-width: 768px)')
			this.mobilePaginationMedia.addEventListener?.('change', this.onMobilePaginationViewportChange)
		}
		window.addEventListener('entry-saved', this.onEntrySaved)
		window.addEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
		window.addEventListener('settings-closed', this.onSettingsClosed)
		window.addEventListener('keydown', this.onPaginationKeydown)
	},
	beforeUnmount() {
		this.projectFetchRequestId++
		this.entryFetchRequestId++
		this.entryHistoryRequestId++
		this.confirmDialog = null
		window.removeEventListener('entry-saved', this.onEntrySaved)
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
		window.removeEventListener('settings-closed', this.onSettingsClosed)
		window.removeEventListener('keydown', this.onPaginationKeydown)
		this.mobilePaginationMedia?.removeEventListener?.('change', this.onMobilePaginationViewportChange)
	},
	watch: {
		'$route.fullPath': 'handleProjectRouteChange'
	},
	methods: {
		groupReportLocation(group) {
			const filters = { ...this.filters }
			if (!this.hasAdditionalProjectMembers && filters.status === 'active') filters.status = 'all'
			return paymentGroupReportTarget(group, filters, {
				projectId: this.projectId, entryScope: this.hasAdditionalProjectMembers ? 'shared' : 'personal',
			})
		},
		annualReturnRoute,
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
		openConfirm({ title, message, confirmLabel, confirmVariant = 'primary', action, errorMessage, requiresOwner = false }) {
			if (this.confirmDialog || this.settlingProject || this.showSettleConfirm) return
			this.confirmDialog = { title, message, confirmLabel, confirmVariant, action, errorMessage, requiresOwner, projectId: String(this.projectId), busy: false, error: '' }
		},
		closeActionConfirm() {
			if (!this.confirmDialog?.busy) this.confirmDialog = null
		},
		async confirmAction() {
			const dialog = this.confirmDialog
			if (!dialog || dialog.busy) return
			if (dialog.projectId !== String(this.projectId) || (dialog.requiresOwner && !this.canManageProject)) {
				this.confirmDialog = null
				return
			}
			dialog.busy = true
			dialog.error = ''
			try {
				await dialog.action()
				if (this.confirmDialog === dialog) this.confirmDialog = null
			} catch (error) {
				if (this.confirmDialog === dialog) dialog.error = extractError(error, dialog.errorMessage)
			} finally { dialog.busy = false }
		},
		async refreshActionProject(projectId) {
			const response = await axios.get(generateUrl(`/apps/cobudget/api/projects/${projectId}`))
			if (projectId !== String(this.projectId) || !this.project || this.confirmDialog?.projectId !== projectId) return false
			Object.assign(this.project, {
				settings_actions: response.data.settings_actions,
				is_owner: response.data.is_owner,
				is_archived: response.data.is_archived,
			})
			return true
		},
		applyEntryPageSize(value = this.$entriesPerPage) {
			this.activePagination.limit = normalizeEntryPageSize(value)
			this.activePagination.offset = 0
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
			if (event.matches && !this.isProjectPaymentsView && this.activePagination.offset > 0) {
				this.activePagination.offset = 0
				this.scrollEntriesToTop()
				this.fetchActiveEntries()
			}
		},
		onPaginationKeydown(event) {
			if ((!this.isProjectPaymentsView && this.isMobileViewport())
				|| shouldIgnorePaginationKeydown(event)
				|| this.activePagination.total <= this.activePagination.limit) {
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
		handleProjectRouteChange() {
			const routeState = parseProjectEntryListRouteQuery(this.$route.query, this.$entriesPerPage)
			const nextProjectId = String(this.projectId)
			const projectChanged = this.loadedProjectId !== nextProjectId

			if (projectChanged) {
				this.entryFetchRequestId++
				this.activeEntries = []
				this.activeDateGroups = null
				this.entriesError = ''
				this.loadingEntries = true
				this.exportError = ''
				this.closeEntryHistory()
				this.confirmDialog = null
				this.showSettleConfirm = false
				this.settleError = ''
				this.settleProjectId = null
				this.resetProjectTableState()
				this.project = null
				this.categories = []
				this.paymentPartners = []
				this.hashtags = []
				this.paymentYears = []
				this.loadedProjectId = nextProjectId
			}

			this.filters = routeState.filters
			this.sortBy = routeState.sortBy
			this.sortDir = routeState.sortDir
			this.applyEntryPageSize(this.isProjectPaymentsView ? routeState.limit : this.$entriesPerPage)
			if (this.isProjectPaymentsView) {
				this.activePagination.offset = entryOffsetFromPage(routeState.page, this.activePagination.limit)
			}

			this.$nextTick(() => {
				if (this.$refs.tableFilters) {
					this.$refs.tableFilters.localFilters = { ...this.filters }
				}
			})

			if (projectChanged || !this.project) {
				this.fetchProjectData()
			} else {
				this.fetchActiveEntries()
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
			this.filters = createProjectEntryFilters()
			this.sortBy = 'date'
			this.sortDir = 'desc'
			this.activePagination.offset = 0
			if (this.$refs.tableFilters) {
				this.$refs.tableFilters.localFilters = { ...this.filters }
			}
		},
		deleteProject() {
			if (!this.canManageProject || this.deleteBlockedReason) return
			const id = String(this.projectId)
			this.openConfirm({
				title: this.$texts.areaDetail.deleteArea(),
				message: this.$texts.areaDetail.deleteAreaMessage(),
				confirmLabel: this.$texts.areaDetail.deleteArea(),
				confirmVariant: 'danger',
				requiresOwner: true,
				errorMessage: this.$texts.areaDetail.deleteError(),
				action: async () => {
					if (!await this.refreshActionProject(id)) return
					if (this.deleteBlockedReason) throw new Error(this.deleteBlockedReason)
					await axios.delete(generateUrl(`/apps/cobudget/api/projects/${id}`))
					this.$emit('refresh-projects')
					if (id === String(this.projectId)) this.$router.push({ name: 'projects' })
				}
			})
		},
		toggleArchiveProject() {
			if (!this.canManageProject || this.archiveBlockedReason) return
			const id = String(this.projectId)
			const archived = !this.isTruthy(this.project.is_archived)
			this.openConfirm({
				title: archived ? this.$texts.areaSettings.archiveTitle() : this.$texts.areaSettings.unarchiveTitle(),
				message: archived ? this.$texts.areaSettings.archiveConfirmMessage() : this.$texts.areaSettings.unarchiveConfirmMessage(),
				confirmLabel: archived ? this.$texts.areaSettings.archive() : this.$texts.areaSettings.unarchive(),
				requiresOwner: true,
				errorMessage: this.$texts.areaDetail.statusSaveError(),
				action: async () => {
					if (!await this.refreshActionProject(id)) return
					if (!this.canManageProject) throw new Error(this.$texts.areaSettings.ownerOnly())
					if (archived && this.archiveBlockedReason) throw new Error(this.archiveBlockedReason)
					await axios.post(generateUrl(`/apps/cobudget/api/projects/${id}/${archived ? 'archive' : 'unarchive'}`))
					if (id === String(this.projectId)) this.project.is_archived = archived
					this.$emit('refresh-projects')
				}
			})
		},
		async fetchProjectData() {
			const requestId = ++this.projectFetchRequestId
			const requestedProjectId = String(this.projectId)
			this.loadingProject = true
			this.projectError = ''
			try {
				const projRes = await axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}`))
				if (requestId !== this.projectFetchRequestId || requestedProjectId !== String(this.projectId)) {
					return
				}
				this.project = projRes.data

				if (this.categories.length === 0) {
					const catRes = await axios.get(generateUrl('/apps/cobudget/api/categories'), { params: { projectId: requestedProjectId } })
					if (requestId !== this.projectFetchRequestId || requestedProjectId !== String(this.projectId)) {
						return
					}
					this.categories = (catRes.data || []).sort((a, b) => a.name.localeCompare(b.name))
				}

				if (this.paymentPartners.length === 0) {
					const paymentPartnerRes = await axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params: { projectId: requestedProjectId } })
					if (requestId !== this.projectFetchRequestId || requestedProjectId !== String(this.projectId)) {
						return
					}
					this.paymentPartners = (paymentPartnerRes.data || []).sort((a, b) => a.name.localeCompare(b.name))
				}

				await this.fetchActiveEntries();
			} catch (e) {
				if (requestId === this.projectFetchRequestId && requestedProjectId === String(this.projectId)) {
					this.projectError = extractError(e, this.$texts.areaDetail.loadError())
					this.loadingEntries = false
				}
			} finally {
				if (requestId === this.projectFetchRequestId && requestedProjectId === String(this.projectId)) this.loadingProject = false
			}
		},
		onEntrySaved() {
			this.fetchProjectData();
		},
		onRemoteDataChanged(event) {
			const change = event?.detail
			if (!changeAffectsProject(change, this.projectId) || !changeTouches(change, ['entries', 'projects', 'settlements'])) {
				return
			}
			this.fetchProjectData()
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
			let isRecurring = null;
			if (this.filters.recurring === 'true') isRecurring = true;
			if (this.filters.recurring === 'false') isRecurring = false;

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
			if (this.$enableReminders !== false && this.filters.tags === 'reminder') hasReminder = true;

			const params = {
				projectId: this.projectId,
				entryScope: this.hasAdditionalProjectMembers ? 'shared' : 'personal',
				isSettled: isSettled,
				isRecurring: isRecurring,
				isSubscription: isSubscription,
				isFixedCost: isFixedCost,
				isChildRelated: isChildRelated,
				isImportant: isImportant,
				needsReview: needsReview,
				isTaxRelevant: isTaxRelevant,
				isInsurance: isInsurance,
				isFuturePayments: this.filters.tags === 'future',
				hasReminder: hasReminder,
				hasAttachment: hasAttachment,
				hashtagId: this.filters.hashtagId,
				search: this.filters.search,
				type: this.filters.type,
				categoryId: this.filters.categoryId,
				paymentPartnerId: this.filters.paymentPartnerId,
				includeFuturePayments: this.filters.includeFuturePayments,
				includeSubcategories: this.filters.includeSubcategories,
				dateFrom: this.filters.dateFrom,
				dateTo: this.filters.dateTo,
				sortBy: this.sortBy,
				sortDir: this.sortDir,
				...entryNarrowingQueryParams(this.filters),
			};

			if (includePagination) {
				params.limit = this.activePagination.limit;
				params.offset = this.activePagination.offset;
			}

			return params;
		},
		async fetchActiveEntries() {
			const requestId = ++this.entryFetchRequestId
			this.loadingEntries = true
			this.entriesError = ''
			const params = this.buildEntryQueryParams(true);
			const availabilityKey = JSON.stringify({ ...params, limit: null, offset: null, sortBy: null, sortDir: null, hasReminder: null, hasAttachment: null })
			if (this.filterAvailabilityKey !== availabilityKey) {
				this.filterAvailability = null
				this.filterAvailabilityKey = availabilityKey
			}
			try {
				const res = await axios.get(generateUrl('/apps/cobudget/api/entries'), { params });
				if (requestId !== this.entryFetchRequestId) {
					return
				}
				this.activeEntries = res.data.entries || [];
				this.filterAvailability = res.data.filterAvailability || null;
				this.activeDateGroups = res.data.dateGroups || null;
				this.paymentYears = res.data.years || [];
				this.activePagination.total = res.data.total || 0;
				if (this.isProjectPaymentsView && this.currentActivePage > this.totalActivePages) {
					this.goToProjectPaymentsPage(this.totalActivePages)
					return
				}
				if (Array.isArray(res.data.lookups?.hashtags)) {
					this.hashtags = res.data.lookups.hashtags.sort((a, b) => String(a.displayName || a.name || '').localeCompare(String(b.displayName || b.name || ''), undefined, { sensitivity: 'base' }))
					}
				} catch (e) {
					if (requestId === this.entryFetchRequestId) {
						this.entriesError = extractError(e, this.$texts.areaDetail.entriesLoadError())
					}
				} finally {
					if (requestId === this.entryFetchRequestId) this.loadingEntries = false
				}
		},
		async exportEntries() {
			if (this.isExporting) {
				return
			}
			this.isExporting = true
			this.exportError = ''
			const requestedProjectId = String(this.projectId)
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/entries/export'), {
					params: this.buildEntryQueryParams(false),
					responseType: 'blob',
				})
				downloadBlobResponse(response, 'cobudget-zahlungen.csv')
				showToast(this.$texts.common.exportCsvCreated())
			} catch (e) {
				if (requestedProjectId === String(this.projectId)) this.exportError = extractError(e, this.$texts.common.exportCsvError())
			} finally {
				this.isExporting = false
			}
		},
		onFiltersUpdate(newFilters) {
			this.filters = {
				...createProjectEntryFilters(),
				...newFilters,
			};
			this.activePagination.offset = 0;
			this.scrollEntriesToTop()
			this.replaceProjectEntryListRoute({ page: 1 })
		},
		resetFilters() {
			this.onFiltersUpdate({ ...createProjectEntryFilters(), annualReturn: this.filters.annualReturn })
		},
		toggleSort(col) {
			if (this.sortBy === col) {
				this.sortDir = this.sortDir === 'desc' ? 'asc' : 'desc';
			} else {
				this.sortBy = col;
				this.sortDir = 'desc';
			}
			this.activePagination.offset = 0;
			this.scrollEntriesToTop()
			this.replaceProjectEntryListRoute({ page: 1 })
		},
		prevActivePage() {
			if (this.activePagination.offset > 0) {
				if (this.isProjectPaymentsView) {
					this.goToProjectPaymentsPage(this.currentActivePage - 1)
					return
				}
				this.activePagination.offset = Math.max(0, this.activePagination.offset - this.activePagination.limit);
				this.scrollEntriesToTop()
				this.fetchActiveEntries();
			}
		},
		nextActivePage() {
			if (this.activePagination.offset + this.activePagination.limit < this.activePagination.total) {
				if (this.isProjectPaymentsView) {
					this.goToProjectPaymentsPage(this.currentActivePage + 1)
					return
				}
				this.activePagination.offset += this.activePagination.limit;
				this.scrollEntriesToTop()
				this.fetchActiveEntries();
			}
		},
		replaceProjectEntryListRoute({ page = this.currentActivePage } = {}) {
			const query = serializeProjectEntryListRouteQuery({
				filters: this.filters,
				sortBy: this.sortBy,
				sortDir: this.sortDir,
				page,
				limit: this.activePagination.limit,
				includePagination: this.isProjectPaymentsView,
			})
			if (personalEntryListQueriesEqual(this.$route.query, query)) {
				this.fetchActiveEntries()
				return
			}
			this.$router.replace({
				name: this.$route.name,
				params: this.$route.params,
				query,
			}).catch(() => this.fetchActiveEntries())
		},
		goToProjectPaymentsPage(page) {
			const targetPage = Math.min(this.totalActivePages, Math.max(1, page))
			this.activePagination.offset = entryOffsetFromPage(targetPage, this.activePagination.limit)
			this.scrollEntriesToTop()
			this.replaceProjectEntryListRoute({ page: targetPage })
		},
		openProjectPaymentsView() {
			const query = serializeProjectEntryListRouteQuery({
				filters: this.filters,
				sortBy: this.sortBy,
				sortDir: this.sortDir,
				page: this.currentActivePage + 1,
				limit: this.activePagination.limit,
				includePagination: true,
			})
			this.$router.push({
				name: 'project-payments',
				params: { id: this.projectId },
				query,
			}).then(() => this.$nextTick(this.scrollProjectPageToTop))
		},
		goBackToProjectOverview() {
			const annual = annualReturnRoute(this.$route.query.annualReturn)
			if (annual) {
				this.$router.push(annual)
				return
			}
			const query = serializeProjectEntryListRouteQuery({
				filters: this.filters,
				sortBy: this.sortBy,
				sortDir: this.sortDir,
			})
			this.$router.push({
				name: 'project-detail',
				params: { id: this.projectId },
				query,
			}).then(() => this.$nextTick(this.scrollProjectPageToTop))
		},
		scrollEntriesToTop() {
			this.$refs.entryTable?.scrollToTop?.()
			if (this.isProjectPaymentsView && this.isMobileViewport()) {
				this.scrollProjectPageToTop()
			}
		},
		scrollProjectPageToTop() {
			const projectScroller = this.$el?.querySelector?.('.project-detail-scroll')
			if (projectScroller) {
				projectScroller.scrollTop = 0
			}
			const appScroller = this.$el?.closest?.('.app-content')
			if (!appScroller) {
				return
			}
			if (typeof appScroller.scrollTo === 'function') {
				appScroller.scrollTo({ top: 0, left: 0, behavior: 'auto' })
			} else {
				appScroller.scrollTop = 0
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

			return getAreaColorStyle(this.project.color)
		},
		formatDate(timestamp) {
			if (!timestamp) return '-'
			return new Date(timestamp * 1000).toLocaleDateString(getAppLocale())
		},
		formatDateTime(timestamp) {
			if (!timestamp) return '-'
			return new Date(timestamp * 1000).toLocaleString(getAppLocale(), {
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

		settlementEntryCountLabel(count) {
			const normalizedCount = parseInt(count || 0, 10)
			return this.$texts.areaDetail.entryCount(normalizedCount)
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

		openEntrySidebar(options = {}) {
			const safeOptions = (options instanceof Event) ? {} : options;
			const defaultType = safeOptions.defaultType || (this.filters.type === 'income' ? 'income' : 'expense');
			this.$emit('open-entry-sidebar', { projectId: this.projectId, defaultType, ...safeOptions });
		},
		editEntry(entry) {
			this.$emit('open-entry-sidebar', { entry, selectedEntryId: entry.id, projectId: this.projectId, isFuture: false })
		},
		duplicateEntry(entry) {
			this.$emit('open-entry-sidebar', { entryToDuplicate: entry, projectId: this.projectId, isFuture: false })
		},
		deleteEntry(entry) {
			const id = String(this.projectId)
			const deleteId = Number(entry.editable_entry_id || entry.source_entry_id || entry.id)
			this.openConfirm({
				title: this.$texts.entry.deleteEntryTitle(),
				message: this.$texts.entry.deleteEntryMessage(),
				confirmLabel: this.$texts.entry.deleteEntry(),
				confirmVariant: 'danger',
				errorMessage: this.$texts.entry.entryDeleteError(),
				action: async () => {
					await axios.delete(generateUrl(`/apps/cobudget/api/entries/${deleteId}`))
					showToast(this.$texts.entry.entryDeleted())
					window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
					if (id === String(this.projectId)) this.fetchProjectData()
				}
			})
		},
		settleUp() {
			if (!this.canSettleProject || this.settlingProject || this.showSettleConfirm || this.confirmDialog) return
			this.settleError = ''
			this.settleProjectId = String(this.projectId)
			this.showSettleConfirm = true
		},
		closeSettleConfirm() {
			if (this.settlingProject) return
			this.showSettleConfirm = false
			this.settleError = ''
			this.settleProjectId = null
		},
		async confirmSettleUp() {
			if (this.settlingProject || !this.showSettleConfirm) return
			const projectId = this.settleProjectId
			if (!this.canSettleProject || projectId !== String(this.projectId)) {
				this.closeSettleConfirm()
				return
			}
			this.settlingProject = true
			this.settleError = ''
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/projects/${projectId}/settle`))
				showToast(this.$texts.areaDetail.areaSettled())
				this.$emit('refresh-projects')
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
				if (projectId === String(this.projectId)) {
					this.showSettleConfirm = false
					this.settleProjectId = null
					this.fetchProjectData()
				}
			} catch (e) {
				if (projectId === String(this.projectId)) this.settleError = extractError(e, this.$texts.areaDetail.settleError())
			} finally {
				this.settlingProject = false
			}
		},
		toggleShowSettled() {
			this.filters.status = this.filters.status === 'settled' ? 'active' : 'settled';
			if (this.$refs.tableFilters) {
				this.$refs.tableFilters.localFilters.status = this.filters.status;
			}
			this.activePagination.offset = 0;
			this.replaceProjectEntryListRoute({ page: 1 })
		}
	}
}
</script>

<style scoped>
.members-heading { display: flex; align-items: center; gap: 8px; }
.members-section .section-header { flex-wrap: wrap; gap: var(--cobudget-content-gap); }
.member-settlements-button { margin-inline-start: auto; }
.detail-loading { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 32px 0; }
@media (max-width: 768px) {
	.project-detail, .project-detail-state { --default-clickable-area: var(--cobudget-mobile-touch-size); }
	.member-settlements-button {
		width: var(--cobudget-mobile-touch-size);
		min-width: var(--cobudget-mobile-touch-size);
		padding-inline: 0;
		flex-shrink: 0;
	}
	.member-settlements-button :deep(.button-vue__text) { display: none; }
}

.area-action-message { margin: 0; white-space: pre-line; }
.area-settlement-question { margin: 0 0 var(--cobudget-section-gap); }
.area-settlement-summary { margin-bottom: var(--cobudget-section-gap); }
.area-settlement-hint { margin-top: var(--cobudget-section-gap); }

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
	container-name: area-detail-header;
	container-type: inline-size;
	margin-bottom: calc(var(--default-grid-baseline, 4px) * 2);
}

@container area-detail-header (max-width: 650px) {
	.project-filter-caption { display: none; }
}

@media (min-width: 769px) {
	@container area-detail-header (max-width: 1050px) {
		.project-settings-header-btn {
			display: none !important;
		}
	}

	.project-detail {
		display: flex;
		flex-direction: column;
		height: 100%;
		min-height: 0;
	}

	.detail-header {
		flex: 0 0 auto;
	}

	.project-detail-scroll {
		--project-detail-scrollbar-size: calc(var(--default-grid-baseline, 4px) * 2);

		box-sizing: border-box;
		flex: 1 1 auto;
		width: calc(100% + var(--cobudget-content-inset-start) + var(--cobudget-content-inset-end));
		min-height: 0;
		margin-inline-start: calc(0px - var(--cobudget-content-inset-start));
		margin-inline-end: calc(0px - var(--cobudget-content-inset-end));
		padding-inline-start: var(--cobudget-content-inset-start);
		padding-inline-end: var(--cobudget-content-inset-end);
		overflow-x: hidden;
		overflow-y: auto;
		overscroll-behavior: contain;
		scrollbar-color: transparent transparent;
		scrollbar-gutter: stable;
		scrollbar-width: thin;
	}

	.project-detail-scroll:hover,
	.project-detail-scroll:focus-within {
		scrollbar-color: var(--color-border-dark, var(--color-border)) transparent;
	}

	.project-detail-scroll::-webkit-scrollbar {
		width: var(--project-detail-scrollbar-size);
		height: var(--project-detail-scrollbar-size);
	}

	.project-detail-scroll::-webkit-scrollbar-track {
		background: transparent;
	}

	.project-detail-scroll::-webkit-scrollbar-thumb {
		border-radius: var(--border-radius-pill, 999px);
		background: transparent;
	}

	.project-detail-scroll:hover::-webkit-scrollbar-thumb,
	.project-detail-scroll:focus-within::-webkit-scrollbar-thumb {
		background: var(--color-border-dark, var(--color-border));
	}
}

@media (min-width: 769px) {
	.project-payments-section :deep(.entry-table-container),
	.project-payments-section :deep(.pagination-footer),
	.members-section :deep(.member-balance-table-wrapper),
	.repayments-section :deep(.repayment-table-wrapper) {
		width: calc(100% + var(--cobudget-content-inset-start) + var(--cobudget-content-inset-end));
		max-width: none;
		margin-inline-start: calc(0px - var(--cobudget-content-inset-start));
		margin-inline-end: calc(0px - var(--cobudget-content-inset-end));
	}
}

:deep(.v-popper__arrow-container),
:deep(.v-popper__arrow),
:deep(.popover__arrow) {
	display: none !important;
}

.section {
	padding: 0;
	margin-block: 0 var(--cobudget-settings-heading-gap);
}

.section h3 {
	margin: 0;
	color: var(--color-main-text);
}

.section-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 12px;
}

.project-payments-section > .section-header { margin-block-end: calc(var(--default-grid-baseline, 4px) * 5); }

.section-header h3 {
	margin: 0;
}

/* Dashboard cards */
.stats-row {
	display: flex;
	gap: var(--cobudget-summary-card-gap);
	margin-inline: calc(0px - var(--default-grid-baseline, 4px));
	margin-bottom: var(--cobudget-content-gap);
	flex-wrap: nowrap;
	padding: 2px 0 8px;
}

.stat-card[data-cobudget-card] {
	flex: 0 0 var(--cobudget-summary-card-width);
	width: var(--cobudget-summary-card-width);
	min-width: var(--cobudget-summary-card-width);
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	padding: calc(var(--default-grid-baseline, 4px) * 2) calc(var(--default-grid-baseline, 4px) * 4);
	border: 0;
	border-radius: var(--border-radius-element);
	background: transparent;
	box-shadow: none;
	color: inherit;
	text-decoration: none;
	cursor: pointer;
	transition: background-color var(--cobudget-hover-duration) linear;
}

.stat-card:hover, .stat-card:focus-visible { background: var(--color-background-hover); }
.stat-card:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: -2px; }
.stat-card * { cursor: inherit; }

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
	position: relative;
	inset-inline-start: calc(var(--default-grid-baseline, 4px) * -0.5);
	width: calc(var(--default-grid-baseline, 4px) * 5);
	height: calc(var(--default-grid-baseline, 4px) * 5);
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
}

.stat-label {
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	color: var(--color-main-text);
}

.stat-value {
	font-size: var(--cobudget-font-metric-compact);
	font-weight: 700;
	text-align: right;
	white-space: nowrap;
	cursor: inherit;
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
}

.stat-sub-line span:last-child {
	white-space: nowrap;
}

.settlements-section {
	max-width: 900px;
}

.settlement-summary-meta,
.settlement-meta,
.settlement-empty,
.settlement-preview-note {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #777)) !important;
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

.settlement-subsection h4 {
	margin: 0 0 8px;
	font-size: var(--cobudget-font-base);
	font-weight: var(--cobudget-font-weight-heading);
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
	background: var(--cobudget-interaction-hover);
	color: var(--color-main-text, #222);
}


.modal-form-content {
	padding: 20px;
}

.project-color-dot {}

.mobile-only {
	display: none !important;
}

.mobile-more-payments-footer,
.mobile-more-payments-button,
.mobile-more-payments-button.button-vue {
	display: none !important;
}

@media (max-width: 768px) {

  .project-detail-scroll > .section {
    margin-block-end: var(--cobudget-section-gap);
  }

  .project-detail-scroll :deep(.member-balance-list .balance-details) {
    padding-block-start: 0;
  }

  .stats-row {
    gap: var(--cobudget-card-gap);
    padding-bottom: var(--default-grid-baseline, 4px);
    margin-bottom: var(--cobudget-content-gap);
    margin-top: 0;
  }

  .stat-card { min-width: 0; max-width: 100%; }

	.back-btn {
		display: none !important;
	}

	.project-settings-header-btn,
	.project-settle-header-btn {
		display: none !important;
	}

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

	.pagination-footer--dashboard {
		display: none;
	}

	.mobile-more-payments-footer {
		display: flex !important;
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

	}

	.pagination-footer:not(.pagination-footer--single) > .page-info {
		text-align: center;
		white-space: nowrap;
	}

	.btn-page {
		--default-clickable-area: var(--cobudget-mobile-touch-size, 44px);
	}

	.project-detail--table-only .pagination-footer {
		gap: calc(var(--default-grid-baseline, 4px) * 2);
		margin-block-start: calc(var(--default-grid-baseline, 4px) * 2);
		padding: calc(var(--default-grid-baseline, 4px) * 2);
		border: 1px solid var(--cobudget-border, var(--color-border));
		border-radius: var(--border-radius-large, 8px);
		background: var(--cobudget-surface-muted, var(--color-background-hover));
	}

	.project-detail--table-only .pagination-label {
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
@media (max-width: 360px) {
	.project-detail-scroll :deep(.member-balance-row) { grid-template-columns: 1fr; }
	.project-detail-scroll :deep(.member-balance-row .balance-amount) {
		display: flex;
		flex-wrap: wrap;
		justify-content: flex-end;
		gap: 0 var(--default-grid-baseline, 4px);
		padding-block: var(--default-grid-baseline, 4px);
	}
	.project-detail-scroll :deep(.member-balance-row .balance-amount::before) {
		margin-block-end: 0;
		margin-inline-end: auto;
	}
}
.clickable-row:focus-within { transition: none; }
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
  color: var(--cobudget-text-muted, #888);
  font-size: var(--cobudget-font-label);
  letter-spacing: normal;
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

</style>
