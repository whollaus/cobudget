<template>
	<div class="project-settlements settings-section">
		<AppPageHeader>
			<template #title>
				<span class="cobudget-page-title-with-back">
					<NcButton
						variant="tertiary"
						class="cobudget-native-button cobudget-header-back-button"
						:aria-label="$texts.settlements.backToArea()"
						:title="$texts.settlements.backToArea()"
						@click="goBackToProject">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
					</NcButton>
					<ReportPeriodPicker navigation-only force-navigation area-navigation :period-enabled="false" :title="$texts.settlements.title()" label="" />
				</span>
			</template>
		</AppPageHeader>
		<div class="settings-header">
			<p v-if="project" class="settlement-project-name">{{ project.name }}</p>
			<p v-if="project" class="settings-hint cobudget-form-hint">
				{{ $texts.settlements.hint() }}
			</p>
		</div>

		<div v-if="loading" class="settlement-feedback settlement-loading cobudget-form-hint" role="status"><NcLoadingIcon :size="32" /><span>{{ $texts.settlements.loading() }}</span></div>
		<NcNoteCard v-else-if="error" class="settlement-feedback" type="error">
			<p>{{ error }}</p>
			<NcButton variant="secondary" class="cobudget-native-button" @click="fetchSettlements">{{ $texts.common.retry() }}</NcButton>
		</NcNoteCard>
		<NcEmptyContent v-else-if="pagination.total === 0" class="settlement-feedback" :name="$texts.settlements.empty()">
			<template #icon><HistoryIcon :size="64" /></template>
		</NcEmptyContent>
		<template v-else>
		<div class="settlement-list">
			<table class="settlement-table">
				<thead><tr>
					<th scope="col" class="settlement-date">{{ $texts.settlements.date() }}</th>
					<th scope="col" class="settlement-author">{{ $texts.settlements.author() }}</th>
					<th scope="col" class="settlement-count">{{ $texts.settlements.paymentCount() }}</th>
					<th scope="col" class="settlement-total" :title="$texts.settlements.totalHint()">{{ $texts.settlements.total() }}</th>
					<th scope="col" class="settlement-action"><span class="hidden-visually">{{ $texts.settlements.details() }}</span></th>
				</tr></thead>
				<tbody>
					<tr v-for="settlement in settlements" :key="settlement.id" tabindex="0" :aria-label="$texts.settlements.settlementFrom(formatDateTime(settlement.createdAt))" @click="openSettlement(settlement, $event)" @keydown="onSettlementRowKeydown($event, settlement)">
						<td class="settlement-date">{{ formatDateTime(settlement.createdAt) }}</td>
						<td class="settlement-created-by" :data-label="$texts.settlements.author()"><MemberIdentity :user-id="settlement.createdBy" :display-name="settlement.createdByDisplayName" /></td>
						<td class="settlement-count" :data-label="$texts.settlements.paymentCount()">{{ settlement.entryCount }}</td>
						<td class="settlement-total" :data-label="$texts.settlements.total()" :title="$texts.settlements.totalHint()">{{ formatCurrency(settlementTotal(settlement), settlement.currency || $currency) }}</td>
						<td class="settlement-action" @click.stop @keydown.stop>
							<NcActions v-if="canExportSettlementEntries(settlement)" class="cobudget-action-menu" variant="tertiary" force-menu placement="bottom-end"
								:aria-label="`${$texts.filters.listActions()}: ${$texts.settlements.settlementFrom(formatDateTime(settlement.createdAt))}`"
								@close="rowCsvMenuId = null">
								<template v-if="rowCsvMenuId === settlement.id">
									<NcActionButton :key="`row-csv-back-${settlement.id}`" :ref="`rowCsvBack-${settlement.id}`" class="cobudget-action-menu-option cobudget-menu-back" :close-after-click="false" @click="showRowCsvMenu(settlement, false)">
										<template #icon><ArrowLeftIcon :size="20" /></template>
										{{ $texts.common.exportCsv() }}
									</NcActionButton>
									<NcActionButton :key="`row-csv-download-${settlement.id}`" class="cobudget-action-menu-option" close-after-click :disabled="isExporting" :description="$texts.annual.downloadCsvHint()" @click="exportEntries(settlement)">
										<template #icon><DownloadIcon :size="20" /></template>
										{{ $texts.common.download() }}
									</NcActionButton>
									<NcActionButton :key="`row-csv-save-${settlement.id}`" class="cobudget-action-menu-option" close-after-click :disabled="isExporting" :description="$texts.annual.saveInNextcloudHint()" @click="saveEntriesInNextcloud(settlement)">
										<template #icon><CloudUploadOutlineIcon :size="20" /></template>
										{{ $texts.annual.saveInNextcloud() }}
									</NcActionButton>
									<NcActionButton :key="`row-csv-copy-${settlement.id}`" class="cobudget-action-menu-option" close-after-click :disabled="isExporting || !canCopySettlementEntriesToClipboard(settlement)" :description="settlementClipboardExportDescription(settlement)" @click="copyEntriesToClipboard(settlement)">
										<template #icon><ClipboardTextOutlineIcon :size="20" /></template>
										{{ $texts.common.copyToClipboard() }}
									</NcActionButton>
								</template>
								<template v-else>
									<NcActionButton :key="`row-csv-trigger-${settlement.id}`" :ref="`rowCsvTrigger-${settlement.id}`" class="cobudget-action-menu-option" is-menu :close-after-click="false" :disabled="isExporting" @click="showRowCsvMenu(settlement, true)">
										<template #icon><DownloadIcon :size="20" /></template>
										{{ $texts.common.exportCsv() }}
									</NcActionButton>
									<NcActionButton :key="`row-print-${settlement.id}`" class="cobudget-action-menu-option" close-after-click :disabled="isPrinting" @click="printEntries(settlement)">
										<template #icon><PrinterIcon :size="20" /></template>
										{{ $texts.analytics.print() }}
									</NcActionButton>
								</template>
							</NcActions>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<nav
			v-if="pagination.total > pagination.limit"
			class="settlement-pagination"
			:aria-label="$texts.settlements.title()">
			<NcButton :aria-label="$texts.common.previous()"
				variant="secondary"
				class="btn-page cobudget-native-button"
				:style="{ visibility: pagination.offset > 0 ? 'visible' : 'hidden' }"
				@click="previousPage">
				<template #icon><ArrowLeftIcon :size="20" /></template>
				<span>{{ $texts.common.previous() }}</span>
			</NcButton>
			<span class="page-info">{{ paginationInfo }}</span>
			<NcButton :aria-label="$texts.common.next()" alignment="center-reverse"
				variant="secondary"
				class="btn-page cobudget-native-button"
				:style="{ visibility: pagination.offset + pagination.limit < pagination.total ? 'visible' : 'hidden' }"
				@click="nextPage">
				<span>{{ $texts.common.next() }}</span>
				<template #icon><ArrowRightIcon :size="20" /></template>
			</NcButton>
		</nav>
		</template>

		<DialogTransition :show="!!(activeSettlement)" :return-target="settlementReturnFocus" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="settlement-detail-dialog cobudget-native-dialog cobudget-standard-dialog"
				:name="$texts.settlements.settlementFrom(formatDateTime(activeSettlement.createdAt))"
				size="large" close-on-click-outside @closing="closeSettlement">
				<div ref="settlementSummary" class="settlement-detail-meta" tabindex="-1">
					<SettlementSummary :area-name="project?.name || ''"
						:created-by="activeSettlement.createdBy" :created-by-display-name="activeSettlement.createdByDisplayName"
						:payment-count="activeSettlement.entryCount"
						:total="formatCurrency(settlementTotal(activeSettlement), activeSettlement.currency || $currency)" />
					<NcButton
						variant="tertiary"
						class="cobudget-native-button settlement-detail-print-button"
						:disabled="isPrinting"
						@click="printEntries(activeSettlement)">
						<template #icon><PrinterIcon :size="20" /></template>
						{{ $texts.analytics.print() }}
					</NcButton>
				</div>
				<div class="settlement-detail-content">
					<section class="settlement-subsection">
						<h3 class="cobudget-subheading">{{ $texts.settlements.balanceBeforeSettlement() }}</h3>
						<MemberBalanceList :balances="activeSettlement.balances || []" :format-amount="value => formatCurrency(value, activeSettlement.currency || $currency)" />
					</section>

					<section class="settlement-subsection">
						<h3 class="cobudget-subheading">{{ $texts.settlements.repayments() }}</h3>
						<RepaymentList v-if="activeSettlement.transfers && activeSettlement.transfers.length > 0" :transfers="activeSettlement.transfers" :format-amount="value => formatCurrency(value, activeSettlement.currency || $currency)" />
						<p v-else class="cobudget-form-hint">{{ $texts.settlements.noRepaymentNeeded() }}</p>
					</section>

					<section ref="settlementEntries" class="settlement-subsection settlement-entries-section">
						<div class="settlement-subsection-header">
							<h3 class="cobudget-subheading">{{ $texts.settlements.entries() }}</h3>
							<NcActions v-if="canExportSettlementEntries(activeSettlement)" class="cobudget-action-menu" variant="tertiary" force-menu placement="bottom-end" :aria-label="$texts.filters.listActions()" @close="detailCsvMenuOpen = false">
								<template v-if="detailCsvMenuOpen">
									<NcActionButton key="detail-csv-back" ref="detailCsvBack" class="cobudget-action-menu-option cobudget-menu-back" :close-after-click="false" @click="showDetailCsvMenu(false)">
										<template #icon><ArrowLeftIcon :size="20" /></template>
										{{ $texts.common.exportCsv() }}
									</NcActionButton>
									<NcActionButton key="detail-csv-download" class="cobudget-action-menu-option" close-after-click :disabled="isExporting" :description="$texts.annual.downloadCsvHint()" @click="exportEntries(activeSettlement)">
										<template #icon><DownloadIcon :size="20" /></template>
										{{ $texts.common.download() }}
									</NcActionButton>
									<NcActionButton key="detail-csv-save" class="cobudget-action-menu-option" close-after-click :disabled="isExporting" :description="$texts.annual.saveInNextcloudHint()" @click="saveEntriesInNextcloud(activeSettlement)">
										<template #icon><CloudUploadOutlineIcon :size="20" /></template>
										{{ $texts.annual.saveInNextcloud() }}
									</NcActionButton>
									<NcActionButton key="detail-csv-copy" class="cobudget-action-menu-option" close-after-click :disabled="isExporting || !canCopySettlementEntriesToClipboard(activeSettlement)" :description="settlementClipboardExportDescription(activeSettlement)" @click="copyEntriesToClipboard(activeSettlement)">
										<template #icon><ClipboardTextOutlineIcon :size="20" /></template>
										{{ $texts.common.copyToClipboard() }}
									</NcActionButton>
								</template>
								<template v-else>
									<NcActionButton key="detail-csv-trigger" ref="detailCsvTrigger" class="cobudget-action-menu-option" is-menu :close-after-click="false" :disabled="isExporting" @click="showDetailCsvMenu(true)">
										<template #icon><DownloadIcon :size="20" /></template>
										{{ $texts.common.exportCsv() }}
									</NcActionButton>
									<NcActionButton key="detail-print" class="cobudget-action-menu-option" close-after-click :disabled="isPrinting" @click="printEntries(activeSettlement)">
										<template #icon><PrinterIcon :size="20" /></template>
										{{ $texts.analytics.print() }}
									</NcActionButton>
								</template>
							</NcActions>
						</div>
						<p v-if="settlementEntryState(activeSettlement).loading" class="settlement-loading cobudget-form-hint" role="status">
							<NcLoadingIcon :size="20" /> {{ $texts.common.loading() }}
						</p>
						<NcNoteCard v-else-if="settlementEntryState(activeSettlement).error" type="error">
							<p>{{ settlementEntryState(activeSettlement).error }}</p>
							<NcButton variant="secondary" class="cobudget-native-button" @click="fetchSettlementEntries(activeSettlement, settlementEntryState(activeSettlement).offset)">{{ $texts.common.retry() }}</NcButton>
						</NcNoteCard>
						<template v-else>
							<div
								v-if="settlementEntryState(activeSettlement).entries.length > 0"
								class="settlement-entry-table"
								:aria-busy="settlementEntryState(activeSettlement).loading ? 'true' : 'false'">
								<EntryTable
									mode="project"
									:entries="settlementEntryState(activeSettlement).entries"
									:currency="activeSettlement.currency || $currency"
									:date-label="$texts.entry.tableDate()"
									sort-by="date"
									sort-dir="desc"
									:enable-fixed-costs="$enableFixedCosts"
									:enable-subscriptions="$enableSubscriptions"
									:enable-child-related="$enableChildRelated"
									:enable-important-payments="$enableImportantPayments"
									:enable-review-payments="$enableReviewPayments"
									:enable-tax-relevant="$enableTaxRelevant"
									:enable-insurance="$enableInsurance"
									:actions-enabled="false"
									:sort-enabled="false"
									:row-clickable="false"
									:archived="false"
									:group-by-date="false"
									:project-name-resolver="getProjectName"
									:project-color-resolver="getProjectColor"
									:member-name-resolver="getMemberName"
									@history="openEntryHistory" />
							</div>
							<p v-else class="cobudget-form-hint">{{ $texts.settlements.noEntries() }}</p>

							<nav
								v-if="settlementEntryState(activeSettlement).total > settlementEntryState(activeSettlement).limit"
								class="settlement-entry-pagination"
								:aria-label="$texts.settlements.entries()">
								<NcButton :aria-label="$texts.common.previous()"
									variant="secondary"
									class="btn-page cobudget-native-button"
									:style="{ visibility: settlementEntryState(activeSettlement).offset > 0 ? 'visible' : 'hidden' }"
									@click="previousSettlementEntryPage(activeSettlement)">
									<template #icon><ArrowLeftIcon :size="20" /></template>
									<span>{{ $texts.common.previous() }}</span>
								</NcButton>
								<span class="page-info">{{ settlementEntryPaginationInfo(activeSettlement) }}</span>
								<NcButton :aria-label="$texts.common.next()" alignment="center-reverse"
									variant="secondary"
									class="btn-page cobudget-native-button"
									:style="{ visibility: settlementEntryHasNextPage(activeSettlement) ? 'visible' : 'hidden' }"
									@click="nextSettlementEntryPage(activeSettlement)">
									<span>{{ $texts.common.next() }}</span>
									<template #icon><ArrowRightIcon :size="20" /></template>
								</NcButton>
							</nav>
						</template>
					</section>
				</div>
				<template #actions>
					<NcButton variant="tertiary" class="cobudget-native-button" @click="closeSettlement">{{ $texts.common.close() }}</NcButton>
				</template>
			</NcDialog>
		</DialogTransition>

		<DialogTransition :show="!!(entryHistoryOpen)" v-slot="{ returnFocus }">
			<EntryHistoryModal :container="null" :set-return-focus="returnFocus"
				:history="entryHistoryRows"
				:loading="entryHistoryLoading"
				:error="entryHistoryError"
				@retry="openEntryHistory(entryHistoryEntry)"
				@close="closeEntryHistory" />
		</DialogTransition>
	</div>
</template>

<script>
import DialogTransition from '../components/DialogTransition.vue'
import { getAppLocale } from '../utils/formatMoney'
import axios from '../services/http'
import { REMOTE_DATA_CHANGED_EVENT, changeAffectsProject, changeTouches } from '../services/dataSync'
import { generateUrl } from '@nextcloud/router'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import CloudUploadOutlineIcon from 'vue-material-design-icons/CloudUploadOutline.vue'
import ClipboardTextOutlineIcon from 'vue-material-design-icons/ClipboardTextOutline.vue'
import PrinterIcon from 'vue-material-design-icons/Printer.vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import EntryTable from '../components/EntryTable.vue'
import MemberIdentity from '../components/MemberIdentity.vue'
import SettlementSummary from '../components/SettlementSummary.vue'
import RepaymentList from '../components/RepaymentList.vue'
import MemberBalanceList from '../components/MemberBalanceList.vue'
import EntryHistoryModal from '../components/EntryHistoryModal.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import { extractError, showRequestError, showToast } from '../services/notifications'
import { ENTRY_CLIPBOARD_EXPORT_LIMIT, csvToClipboardText, downloadBlobResponse, filenameFromDisposition } from '../services/downloads'
import { printPaymentsOverview } from '../services/paymentPrint'
import { copyReportText, saveReportExport } from '../services/reportExportActions'
import { normalizeEntryPageSize, shouldIgnorePaginationKeydown } from '../services/pagination'

const SETTLEMENT_PAGE_SIZE = 10
const MAX_SETTLEMENT_PAGE = 10001

export default {
	name: 'ProjectSettlementsView',
	components: { ReportPeriodPicker, DialogTransition,
		RepaymentList,
		MemberBalanceList,
		MemberIdentity,
		SettlementSummary,
		NcLoadingIcon, NcEmptyContent, NcNoteCard, HistoryIcon, NcDialog, ChevronRightIcon,
		DownloadIcon,
		CloudUploadOutlineIcon,
		ClipboardTextOutlineIcon,
		PrinterIcon,
		NcActions,
		NcActionButton,
		AppPageHeader,
		ArrowLeftIcon,
		ArrowRightIcon,
		EntryTable,
		EntryHistoryModal,
		NcButton,
	},
	props: ['id'],
	data() {
		return {
			isExporting: false,
			isPrinting: false,
			printRequestId: 0,
			rowCsvMenuId: null,
			detailCsvMenuOpen: false,
			project: null,
			settlements: [],
			pagination: {
				limit: SETTLEMENT_PAGE_SIZE,
				offset: 0,
				total: 0,
			},
			loading: true,
			error: '',
			settlementFetchRequestId: 0,
			settlementEntryStates: {},
			openSettlementId: null,
			settlementReturnFocus: false,
			entryHistoryOpen: false,
			entryHistoryError: '',
			entryHistoryEntry: null,
			entryHistoryRequestId: 0,
			entryHistoryLoading: false,
			entryHistoryRows: [],
		}
	},
	computed: {
		activeSettlement() {
			return this.settlements.find(settlement => settlement.id === this.openSettlementId) || null
		},
		projectId() {
			return this.id
		},
		currentPage() {
			return Math.floor(this.pagination.offset / this.pagination.limit) + 1
		},
		totalPages() {
			return Math.max(1, Math.ceil(Number(this.pagination.total || 0) / this.pagination.limit))
		},
		paginationInfo() {
			return this.$texts.common.pageNumber(this.currentPage, this.totalPages)
		},
		clipboardExportLimitLabel() {
			return new Intl.NumberFormat(getAppLocale()).format(ENTRY_CLIPBOARD_EXPORT_LIMIT)
		},
		memberNameMap() {
			const map = {}
			for (const member of this.project?.members || []) {
				map[member.id] = member.displayName
			}
			for (const settlement of this.settlements) {
				for (const balance of settlement.balances || []) {
					map[balance.userId] = balance.displayName
				}
			}
			for (const state of Object.values(this.settlementEntryStates)) {
				for (const entry of state.entries || []) {
					if (entry.user_id && entry.user_display_name) {
						map[entry.user_id] = entry.user_display_name
					}
				}
			}
			return map
		},
	},
	mounted() {
		this.handleSettlementRouteChange()
		window.addEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
		window.addEventListener('keydown', this.onPaginationKeydown)
	},
	beforeUnmount() {
		this.settlementFetchRequestId++
		this.printRequestId++
		this.isPrinting = false
		this.settlementEntryStates = {}
		this.closeEntryHistory()
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
		window.removeEventListener('keydown', this.onPaginationKeydown)
	},
	watch: {
		'$route.fullPath': 'handleSettlementRouteChange',
	},
	methods: {
		routePage() {
			const routeValue = Array.isArray(this.$route.query.page)
				? this.$route.query.page[0]
				: this.$route.query.page
			const parsedPage = Number.parseInt(routeValue, 10)
			return Number.isSafeInteger(parsedPage) && parsedPage > 0 ? Math.min(parsedPage, MAX_SETTLEMENT_PAGE) : 1
		},
		handleSettlementRouteChange() {
			this.closeEntryHistory()
			this.printRequestId++
			this.isPrinting = false
			this.rowCsvMenuId = null
			this.detailCsvMenuOpen = false
			this.project = null
			this.pagination.offset = (this.routePage() - 1) * this.pagination.limit
			this.openSettlementId = null
			this.fetchSettlements()
		},
		onSettlementRowKeydown(event, settlement) {
			if (event.target !== event.currentTarget) {
				return
			}
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault()
				this.openSettlement(settlement, event)
			}
		},
		showRowCsvMenu(settlement, open) {
			if (!settlement) {
				this.rowCsvMenuId = null
				return
			}
			this.rowCsvMenuId = open ? settlement.id : null
			this.$nextTick(() => {
				const ref = this.$refs[`${open ? 'rowCsvBack' : 'rowCsvTrigger'}-${settlement.id}`]
				;(Array.isArray(ref) ? ref[0] : ref)?.$el?.querySelector('button')?.focus()
			})
		},
		showDetailCsvMenu(open) {
			this.detailCsvMenuOpen = open
			this.$nextTick(() => this.$refs[open ? 'detailCsvBack' : 'detailCsvTrigger']?.$el?.querySelector('button')?.focus())
		},
		settlementEntryCountForExport(settlement) {
			if (!settlement) {
				return 0
			}
			return Math.max(0, Number(this.settlementEntryStates[String(settlement.id)]?.total ?? settlement.entryCount ?? 0))
		},
		canExportSettlementEntries(settlement) {
			return this.settlementEntryCountForExport(settlement) > 0
		},
		canCopySettlementEntriesToClipboard(settlement) {
			const total = this.settlementEntryCountForExport(settlement)
			return total > 0 && total <= ENTRY_CLIPBOARD_EXPORT_LIMIT
		},
		settlementClipboardExportDescription(settlement) {
			if (!this.canExportSettlementEntries(settlement) || this.canCopySettlementEntriesToClipboard(settlement)) {
				return undefined
			}
			return this.$texts.payments.clipboardExportLimitHint(this.clipboardExportLimitLabel)
		},
		buildExportQueryParams(settlement = this.activeSettlement) {
			if (!settlement || settlement.id == null) {
				return null
			}
			return {
				projectId: this.projectId,
				entryScope: 'shared',
				isSettled: 1,
				includeFuturePayments: true,
				settlementId: settlement.id,
			}
		},
		async exportEntries(settlement = this.activeSettlement) {
			const exportParams = this.buildExportQueryParams(settlement)
			if (this.isExporting || !exportParams) {
				return
			}
			this.isExporting = true
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/entries/export'), {
					params: exportParams,
					responseType: 'blob',
				})
				downloadBlobResponse(response, 'cobudget-zahlungen.csv')
				showToast(this.$texts.common.exportCsvCreated())
			} catch (e) {
				showRequestError(e, this.$texts.common.exportCsvError(), 'Failed to export settlement entries')
			} finally {
				this.isExporting = false
			}
		},
		async saveEntriesInNextcloud(settlement = this.activeSettlement) {
			const exportParams = this.buildExportQueryParams(settlement)
			if (this.isExporting || !exportParams) {
				return
			}
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
				showRequestError(e, this.$texts.payments.saveInNextcloudError(), 'Failed to export settlement entries')
			} finally {
				this.isExporting = false
			}
		},
		async copyEntriesToClipboard(settlement = this.activeSettlement) {
			const targetSettlement = settlement || this.activeSettlement
			const exportParams = this.buildExportQueryParams(targetSettlement)
			if (this.isExporting || !exportParams) {
				return
			}
			if (!this.canCopySettlementEntriesToClipboard(targetSettlement)) {
				showToast(this.$texts.payments.clipboardExportLimitError(this.clipboardExportLimitLabel), 'error')
				return
			}
			this.isExporting = true
			try {
				const contents = axios.get(generateUrl('/apps/cobudget/api/entries/export'), {
					params: {
						...exportParams,
						clipboard: true,
					},
					responseType: 'text',
				}).then(response => csvToClipboardText(response?.data))
				await copyReportText(contents)
				showToast(this.$texts.common.copiedToClipboard())
			} catch (e) {
				showRequestError(e, this.$texts.payments.copyToClipboardError(), 'Failed to export settlement entries')
			} finally {
				this.isExporting = false
			}
		},
		async loadSettlementPrintEntries(settlement, isCurrent = () => true) {
			const limit = 250
			const entries = []
			let total = null
			while (total === null || entries.length < total) {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/settlements/${settlement.id}/entries`), {
					params: {
						limit,
						offset: entries.length,
					},
				})
				if (!isCurrent()) {
					return null
				}
				const pageEntries = Array.isArray(response?.data?.entries) ? response.data.entries : []
				const reportedTotal = Number(response?.data?.total)
				total = Number.isFinite(reportedTotal) && reportedTotal >= 0 ? reportedTotal : pageEntries.length
				if (pageEntries.length === 0) {
					break
				}
				entries.push(...pageEntries)
				if (pageEntries.length < limit) {
					break
				}
			}
			return entries
		},
		async printEntries(settlement = this.activeSettlement) {
			const targetSettlement = settlement || this.activeSettlement
			if (this.isPrinting || !targetSettlement || targetSettlement.id == null) {
				return
			}
			const requestId = ++this.printRequestId
			const isCurrent = () => requestId === this.printRequestId
			const settlementCurrency = targetSettlement.currency || this.$currency
			const settlementTitle = this.$texts.settlements.settlementFrom(this.formatDateTime(targetSettlement.createdAt))
			const areaName = this.project?.name || ''
			const authorName = targetSettlement.createdByDisplayName || targetSettlement.createdBy || ''
			const totalFormatted = this.formatCurrency(this.settlementTotal(targetSettlement), settlementCurrency)
			this.isPrinting = true
			try {
				const entries = await this.loadSettlementPrintEntries(targetSettlement, isCurrent)
				if (!entries || !isCurrent()) {
					return
				}
				const paymentCount = Math.max(entries.length, Number(targetSettlement.entryCount) || 0)
				await printPaymentsOverview(document, {
					title: settlementTitle,
					periodLabel: areaName,
					settlement: targetSettlement,
					metadataRows: [
						[this.$texts.settlements.area(), areaName],
						[this.$texts.settlements.author(), authorName],
						[this.$texts.settlements.paymentCount(), paymentCount.toLocaleString(getAppLocale())],
						[this.$texts.settlements.total(), totalFormatted],
					],
					entries,
					currency: settlementCurrency,
					enableIncomes: Boolean(this.$enableIncomes ?? true),
					enableProjects: false,
					showSummaryCards: false,
					groupByDate: false,
					showPaidBy: true,
					payerNameResolver: entry => (
						entry?.paid_by_display_name
						|| (entry?.paid_by_user_id ? this.getMemberName(entry.paid_by_user_id) : '')
						|| entry?.user_display_name
						|| (entry?.user_id ? this.getMemberName(entry.user_id) : '')
					),
					typeFilter: 'all',
					isFutureFilter: false,
					sortBy: 'date',
					projects: this.project ? [this.project] : [],
					labelSettings: {
						important: this.$enableImportantPayments,
						review: this.$enableReviewPayments,
						fixedCosts: this.$enableFixedCosts,
						childRelated: this.$enableChildRelated,
						subscriptions: this.$enableSubscriptions,
						taxRelevant: this.$enableTaxRelevant,
						insurance: this.$enableInsurance,
					},
					amountResolver: entry => entry?.amount,
					formatMoney: value => this.formatCurrency(value, settlementCurrency),
					formatSignedMoney: value => (
						typeof this.$formatSignedMoney === 'function'
							? this.$formatSignedMoney(value, settlementCurrency)
							: this.formatCurrency(value, settlementCurrency)
					),
					texts: this.$texts,
					locale: getAppLocale(),
				})
			} catch (e) {
				if (!isCurrent()) {
					return
				}
				showRequestError(e, this.$texts.payments.printError(), 'Failed to print settlement entries')
			} finally {
				if (isCurrent()) {
					this.isPrinting = false
				}
			}
		},
		settlementEntryState(settlement) {
			return this.settlementEntryStates[String(settlement.id)] || {
				entries: [],
				loaded: false,
				loading: false,
				error: '',
				limit: normalizeEntryPageSize(this.$entriesPerPage),
				offset: 0,
				total: Math.max(0, Number(settlement.entryCount) || 0),
				requestId: 0,
			}
		},
		ensureSettlementEntryState(settlement) {
			const key = String(settlement.id)
			if (!this.settlementEntryStates[key]) {
				this.settlementEntryStates[key] = this.settlementEntryState(settlement)
			}
			return this.settlementEntryStates[key]
		},
		settlementEntryCurrentPage(settlement) {
			const state = this.settlementEntryState(settlement)
			return Math.floor(state.offset / state.limit) + 1
		},
		settlementEntryTotalPages(settlement) {
			const state = this.settlementEntryState(settlement)
			return Math.max(1, Math.ceil(Number(state.total || 0) / state.limit))
		},
		settlementEntryPaginationInfo(settlement) {
			const state = this.settlementEntryState(settlement)
			return this.$texts.common.pageInfo(
				state.offset + 1,
				Math.min(state.offset + state.limit, state.total),
				state.total,
			)
		},
		settlementEntryHasNextPage(settlement) {
			const state = this.settlementEntryState(settlement)
			return state.offset + state.limit < state.total
		},
		onPaginationKeydown(event) {
			if (this.loading || this.error || this.entryHistoryOpen || shouldIgnorePaginationKeydown(event)) {
				return
			}

			const openSettlement = this.settlements.find(settlement => String(settlement.id) === String(this.openSettlementId))
			if (openSettlement) {
				const entryState = this.settlementEntryState(openSettlement)
				if (entryState.total > entryState.limit) {
					if (event.key === 'ArrowLeft' && entryState.offset > 0) {
						event.preventDefault()
						this.previousSettlementEntryPage(openSettlement)
					} else if (event.key === 'ArrowRight' && this.settlementEntryHasNextPage(openSettlement)) {
						event.preventDefault()
						this.nextSettlementEntryPage(openSettlement)
					}
					return
				}
				return
			}

			if (this.pagination.total <= this.pagination.limit) {
				return
			}

			if (event.key === 'ArrowLeft' && this.pagination.offset > 0) {
				event.preventDefault()
				this.previousPage()
			} else if (event.key === 'ArrowRight' && this.pagination.offset + this.pagination.limit < this.pagination.total) {
				event.preventDefault()
				this.nextPage()
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
		async fetchSettlementEntries(settlement, offset = 0) {
			const key = String(settlement.id)
			const state = this.ensureSettlementEntryState(settlement)
			const requestId = state.requestId + 1
			const requestedProjectId = String(this.projectId)
			state.requestId = requestId
			state.offset = Math.max(0, Number(offset) || 0)
			state.loading = true
			state.error = ''

			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/settlements/${settlement.id}/entries`), {
					params: {
						limit: state.limit,
						offset: state.offset,
					},
				})
				if (this.settlementEntryStates[key] !== state
					|| state.requestId !== requestId
					|| requestedProjectId !== String(this.projectId)) {
					return
				}

				state.entries = Array.isArray(response.data?.entries) ? response.data.entries : []
				state.limit = Math.max(1, Number(response.data?.limit) || state.limit)
				state.offset = Math.max(0, Number(response.data?.offset) || 0)
				state.total = Math.max(0, Number(response.data?.total) || 0)
				state.loaded = true

				const currentPage = this.settlementEntryCurrentPage(settlement)
				const totalPages = this.settlementEntryTotalPages(settlement)
				if (currentPage > totalPages) {
					this.fetchSettlementEntries(settlement, (totalPages - 1) * state.limit)
				}
			} catch (error) {
				if (this.settlementEntryStates[key] !== state || state.requestId !== requestId || requestedProjectId !== String(this.projectId)) {
					return
				}
				state.error = extractError(error, this.$texts.areaDetail.entriesLoadError())
			} finally {
				if (this.settlementEntryStates[key] === state && state.requestId === requestId) {
					state.loading = false
				}
			}
		},
		async fetchSettlements() {
			const requestId = ++this.settlementFetchRequestId
			const requestedProjectId = String(this.projectId)
			this.printRequestId++
			this.isPrinting = false
			this.loading = true
			this.error = ''
			this.closeSettlement()
			this.settlementEntryStates = {}
			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/settlements`), {
					params: {
						limit: this.pagination.limit,
						offset: this.pagination.offset,
					},
				})
				if (requestId !== this.settlementFetchRequestId || requestedProjectId !== String(this.projectId)) {
					return
				}
				this.project = response.data?.project || null
				this.settlements = Array.isArray(response.data?.settlements) ? response.data.settlements : []
				this.pagination.limit = Number(response.data?.limit) || this.pagination.limit
				this.pagination.offset = Math.max(0, Number(response.data?.offset) || 0)
				this.pagination.total = Math.max(0, Number(response.data?.total) || 0)
				if (this.currentPage > this.totalPages) {
					this.replaceSettlementPage(this.totalPages)
				}
			} catch (error) {
				if (requestId !== this.settlementFetchRequestId || requestedProjectId !== String(this.projectId)) {
					return
				}
				this.error = extractError(error, this.$texts.settlements.loadError())
			} finally {
				if (requestId === this.settlementFetchRequestId) {
					this.loading = false
				}
			}
		},
		onRemoteDataChanged(event) {
			const change = event?.detail
			if (changeAffectsProject(change, this.projectId) && changeTouches(change, ['projects', 'settlements'])) {
				this.fetchSettlements()
			}
		},
		goBackToProject() {
			this.$router.push({ name: 'project-detail', params: { id: this.projectId } })
		},
		previousPage() {
			if (!this.loading && this.pagination.offset > 0) {
				this.goToSettlementPage(this.currentPage - 1)
			}
		},
		nextPage() {
			if (!this.loading && this.pagination.offset + this.pagination.limit < this.pagination.total) {
				this.goToSettlementPage(this.currentPage + 1)
			}
		},
		previousSettlementEntryPage(settlement) {
			const state = this.ensureSettlementEntryState(settlement)
			if (state.offset > 0 && !state.loading) {
				this.goToSettlementEntryPage(settlement, this.settlementEntryCurrentPage(settlement) - 1)
			}
		},
		nextSettlementEntryPage(settlement) {
			const state = this.ensureSettlementEntryState(settlement)
			if (this.settlementEntryHasNextPage(settlement) && !state.loading) {
				this.goToSettlementEntryPage(settlement, this.settlementEntryCurrentPage(settlement) + 1)
			}
		},
		goToSettlementEntryPage(settlement, page) {
			const state = this.ensureSettlementEntryState(settlement)
			const targetPage = Math.min(
				this.settlementEntryTotalPages(settlement),
				Math.max(1, Number(page) || 1),
			)
			this.scrollSettlementEntrySectionToTop()
			this.fetchSettlementEntries(settlement, (targetPage - 1) * state.limit)
		},
		scrollSettlementEntrySectionToTop() {
			this.$nextTick(() => {
				this.$refs.settlementEntries?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
			})
		},
		goToSettlementPage(page) {
			const targetPage = Math.min(this.totalPages, Math.max(1, Number(page) || 1))
			const query = { ...this.$route.query }
			if (targetPage > 1) {
				query.page = String(targetPage)
			} else {
				delete query.page
			}
			this.scrollToPageTop()
			this.$router.push({
				name: 'project-settlements',
				params: { id: this.projectId },
				query,
			}).catch(() => {})
		},
		replaceSettlementPage(page) {
			const targetPage = Math.max(1, Number(page) || 1)
			const query = { ...this.$route.query }
			if (targetPage > 1) {
				query.page = String(targetPage)
			} else {
				delete query.page
			}
			this.$router.replace({
				name: 'project-settlements',
				params: { id: this.projectId },
				query,
			}).catch(() => {})
		},
		scrollToPageTop() {
			this.$nextTick(() => {
				const scroller = this.$el?.closest?.('.app-content')
				if (scroller && typeof scroller.scrollTo === 'function') {
					scroller.scrollTo({ top: 0, behavior: 'smooth' })
				}
			})
		},
		closeSettlement() {
			this.closeEntryHistory()
			this.detailCsvMenuOpen = false
			this.openSettlementId = null
		},
		openSettlement(settlement, event) {
			const trigger = event?.currentTarget
			this.settlementReturnFocus = trigger?.matches('button') ? trigger : (trigger?.querySelector('button') || false)
			this.rowCsvMenuId = null
			this.detailCsvMenuOpen = false
			this.openSettlementId = settlement.id
			// Start at the summary rather than a payment tooltip far down the dialog.
			// NcModal's native focus trap retains this in-dialog focus on entry.
			this.$nextTick(() => this.$refs.settlementSummary?.focus({ preventScroll: true }))
			const state = this.ensureSettlementEntryState(settlement)
			if (state.total === 0) {
				state.loaded = true
			} else if ((!state.loaded || state.error) && !state.loading) {
				this.fetchSettlementEntries(settlement, state.offset)
			}
		},
		formatDateTime(timestamp) {
			if (!timestamp) return '-'
			return new Date(timestamp * 1000).toLocaleString(getAppLocale(), {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
			})
		},
		formatCurrency(value, currency = null) {
			return this.$formatMoney(value, currency || this.$currency)
		},
		settlementTotal(settlement) {
			// Immutable payer/recipient totals cover every settled payment exactly once.
			// Do not add allocations or repayments, or sum the paginated payment rows.
			const cents = (settlement.balances || []).reduce((total, balance) =>
				total + Number(balance.paidCents || 0) + Number(balance.receivedCents || 0), 0)
			return cents / 100
		},
		settlementEntryCountLabel(count) {
			const normalizedCount = parseInt(count || 0, 10)
			return this.$texts.settlements.entryCount(normalizedCount)
		},

		getMemberName(userId) {
			return this.memberNameMap[userId] || userId
		},
		getProjectName(id) {
			if (this.project && String(this.project.id) === String(id)) {
				return this.project.name
			}
			return id ? `ID: ${id}` : ''
		},
		getProjectColor(id) {
			return this.project && String(this.project.id) === String(id) ? this.project.color || '' : ''
		},
	},
}
</script>

<style scoped>
.project-settlements {
	display: block;
	width: 100%;
	margin: 0;
	padding: 0 0 var(--cobudget-settings-heading-gap);
	box-sizing: border-box;
}

.settings-header {
	width: min(var(--cobudget-form-max-width), calc(100% - var(--cobudget-form-inset) * 2));
	margin: 0 var(--cobudget-form-inset) var(--cobudget-section-gap);
	box-sizing: border-box;
}

.settings-hint {
	margin: 0;
}
.settlement-project-name {
	margin: 0 0 var(--cobudget-content-gap);
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	color: var(--color-main-text);
	overflow-wrap: anywhere;
}

.settlement-list {
	box-sizing: border-box;
	width: 100%;
	margin-inline: 0;
	overflow-x: auto;
	background: var(--cobudget-surface, #fff);
}

@media (min-width: 769px) {
	.settlement-list {
		width: calc(100% + var(--cobudget-content-inset-start, 0px) + var(--cobudget-content-inset-end, 0px));
		max-width: none;
		margin-inline-start: calc(0px - var(--cobudget-content-inset-start, 0px));
		margin-inline-end: calc(0px - var(--cobudget-content-inset-end, 0px));
	}
}

.settlement-table {
	width: 100%;
	min-width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	border: 0;
	border-radius: 0;
	table-layout: fixed;
	background: var(--cobudget-surface);
}

.settlement-table th,
.settlement-table td {
	box-sizing: border-box;
	min-width: 0;
	padding: var(--cobudget-table-payment-padding);
	border-bottom: 1px solid var(--payment-table-separator, var(--cobudget-table-separator));
	vertical-align: middle;
	font-size: var(--cobudget-font-base);
	text-align: start;
}

.settlement-table th {
	height: var(--cobudget-payment-table-header-height);
	background: var(--cobudget-table-header-background, var(--cobudget-surface));
	color: var(--cobudget-text-muted);
	font-size: var(--cobudget-font-base);
	font-weight: 400;
	letter-spacing: normal;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.settlement-table td {
	color: var(--cobudget-text);
}

@media (min-width: 769px) {
	.settlement-table tbody tr {
		height: var(--cobudget-payment-table-row-height);
	}

	.settlement-table tr > :first-child {
		padding-inline-start: var(--cobudget-content-inset-start, var(--cobudget-content-gap));
	}
}

.settlement-table tbody tr,
.settlement-table tbody td {
	cursor: pointer;
}

.settlement-table tbody td :deep(*) {
	cursor: inherit;
}

.settlement-table tbody tr {
	transition: background-color var(--cobudget-hover-duration) linear;
}

.settlement-table tbody tr:hover,
.settlement-table tbody tr:focus-within {
	background: var(--color-background-hover, var(--cobudget-surface-muted));
}

.settlement-table tbody tr:last-child td {
	border-bottom: 1px solid var(--payment-table-separator, var(--cobudget-table-separator));
}

.settlement-table .settlement-date {
	width: 180px;
	white-space: nowrap;
}

.settlement-table td.settlement-date {
	font-weight: var(--font-weight-element, 500);
}

.settlement-table .settlement-author,
.settlement-table .settlement-created-by {
	width: auto;
}

.settlement-table .settlement-count {
	width: 110px;
	text-align: end;
	font-variant-numeric: tabular-nums;
}

.settlement-table .settlement-total {
	width: 150px;
	text-align: end;
	font-variant-numeric: tabular-nums;
	overflow-wrap: anywhere;
}

.settlement-table td.settlement-total {
	font-weight: 600;
}

.settlement-table .settlement-action {
	width: 44px;
	padding-inline: 0 !important;
	text-align: center;
}

.settlement-table .settlement-action :deep(.cobudget-action-menu) {
	display: inline-flex;
	justify-content: center;
}

.settlement-detail-meta {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: var(--cobudget-content-gap);
	margin-bottom: var(--cobudget-section-gap);
	padding-inline: var(--cobudget-dialog-padding, 24px);
}
.settlement-detail-meta:focus { outline: none; }
.settlement-detail-meta :deep(.settlement-summary) {
	flex: 1 1 auto;
	min-width: 0;
}
.settlement-detail-print-button {
	flex: 0 0 auto;
}

.settlement-pagination {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	width: 100%;
	margin: calc(var(--default-grid-baseline, 4px) * 4) 0 0;
	padding-inline: calc(var(--default-grid-baseline, 4px) * 3);
	box-sizing: border-box;
}

@media (min-width: 769px) {
	.settlement-pagination {
		width: calc(100% + var(--cobudget-content-inset-start, 0px) + var(--cobudget-content-inset-end, 0px));
		max-width: none;
		margin-inline-start: calc(0px - var(--cobudget-content-inset-start, 0px));
		margin-inline-end: calc(0px - var(--cobudget-content-inset-end, 0px));
		padding-inline: calc(var(--cobudget-content-inset-start, 0px) + var(--default-grid-baseline, 4px) * 3) calc(var(--cobudget-content-inset-end, 0px) + var(--default-grid-baseline, 4px) * 3);
	}
}

.settlement-pagination .btn-page:first-child {
	justify-self: start;
}

.settlement-pagination .btn-page:last-child {
	justify-self: end;
}

.pagination-label { white-space: nowrap; }

.page-info {
	color: var(--color-text-maxcontrast);
	font-size: var(--cobudget-font-compact);
	text-align: center;
	white-space: nowrap;
}

.settlement-detail-content {
	display: flex;
	flex-direction: column;
	gap: var(--cobudget-settings-heading-gap);
	padding-block-end: var(--cobudget-dialog-padding, 24px);
}

.settlement-subsection h3 {
	margin: 0 0 var(--cobudget-content-gap);
	padding-inline: var(--cobudget-dialog-padding, 24px);
	font-size: var(--cobudget-font-subheading);
	font-weight: var(--cobudget-font-weight-heading);
	color: var(--color-main-text);
}

.settlement-subsection-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--cobudget-content-gap);
	margin-bottom: var(--cobudget-content-gap);
	padding-inline: var(--cobudget-dialog-padding, 24px);
}

.settlement-subsection-header h3 {
	margin: 0;
	padding-inline: 0;
}

.settlement-subsection { margin: 0; min-width: 0; }
.settlement-subsection > .cobudget-form-hint {
	margin: 0;
	padding-inline: var(--cobudget-dialog-padding, 24px);
}

.settlement-entries-section {
	min-width: 0;
	scroll-margin-block-start: calc(var(--default-grid-baseline, 4px) * 18);
}

.settlement-entry-table {
	--cobudget-payment-row-bleed: 0px;
	--cobudget-content-inset-start: var(--cobudget-dialog-padding, 24px);
	--cobudget-content-inset-end: var(--cobudget-dialog-padding, 24px);
	--payment-amount-inset: var(--cobudget-dialog-padding, 24px);
	min-width: 0;
	overflow: visible;
}

.settlement-entry-pagination {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	padding: 10px var(--cobudget-dialog-padding, 24px) 0;
}

.settlement-entry-pagination .btn-page:first-child {
	justify-self: start;
}

.settlement-entry-pagination .btn-page:last-child {
	justify-self: end;
}

.settlement-feedback {
	width: calc(100% - var(--default-grid-baseline, 4px) * 14);
	margin: 0 calc(var(--default-grid-baseline, 4px) * 7);
	box-sizing: border-box;
}
.settlement-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 12px;
	padding-block: 24px;
	color: var(--color-text-maxcontrast);
}

@media (max-width: 768px) {
	.settlement-table thead {
		position: absolute;
		width: 1px;
		height: 1px;
		overflow: hidden;
		clip-path: inset(50%);
	}
	.settlement-table tbody { display: block; }
	.settlement-table tbody tr {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		align-items: center;
		padding: 8px 12px;
		gap: 4px 12px;
		border-bottom: 1px solid var(--color-border);
	}
	.settlement-table tbody tr:last-child { border-bottom: 0; }
	.settlement-table td { display: block; padding: 0; border: 0; min-width: 0; }
	.settlement-table .settlement-date { grid-column: 1; grid-row: 1; width: auto; }
	.settlement-table .settlement-action { grid-column: 2; grid-row: 1; justify-self: end; width: auto; }
	.settlement-table .settlement-created-by { grid-row: 2; width: auto; }
	.settlement-table .settlement-count { grid-row: 3; width: auto; }
	.settlement-table .settlement-total { grid-row: 4; width: auto; }
	.settlement-table td[data-label] {
		grid-column: 1 / -1;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: var(--cobudget-content-gap);
		text-align: end;
		overflow-wrap: anywhere;
	}
	.settlement-created-by :deep(.member-identity) { min-width: 0; max-width: 60%; }
	.settlement-table td[data-label]::before {
		content: attr(data-label);
		display: block;
		color: var(--color-main-text);
		font-size: var(--font-size, 15px);
		font-weight: var(--font-weight-element, 500);
		margin-bottom: 0;
		text-align: start;
	}

	.project-settlements {
		--default-clickable-area: var(--cobudget-mobile-touch-size);
		--settlement-mobile-content-gutter: var(--cobudget-mobile-content-padding, calc(var(--default-grid-baseline, 4px) * 2.5));

		width: 100%;
		margin: 0;
		padding: 0 0 var(--settlement-mobile-content-gutter);
		box-sizing: border-box;
	}

	.settings-header,
	.settlement-list,
	.settlement-pagination,
	.settlement-feedback {
		width: 100%;
		margin-inline: 0;
	}
	.settings-header { margin-block-end: var(--cobudget-card-gap); }
	.settlement-project-name { margin-block-end: var(--default-grid-baseline, 4px); }
	.settlement-detail-content { gap: var(--cobudget-section-gap); }
	.settlement-detail-content :deep(.member-balance-list .balance-details) { padding-block-start: 0; }

	.btn-page { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); }

	.settlement-pagination, .settlement-entry-pagination { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
	.page-info { grid-row: 1; grid-column: 1 / -1; }



}
@media (max-width: 360px) {
	.settlement-detail-content :deep(.member-balance-row) { grid-template-columns: 1fr; }
	.settlement-detail-content :deep(.member-balance-row .balance-amount) {
		display: flex;
		flex-wrap: wrap;
		justify-content: flex-end;
		gap: 0 var(--default-grid-baseline, 4px);
		padding-block: var(--default-grid-baseline, 4px);
	}
	.settlement-detail-content :deep(.member-balance-row .balance-amount::before) {
		margin-block-end: 0;
		margin-inline-end: auto;
	}
}
</style>
