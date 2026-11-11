<template>
	<ReportDetailSection class="report-payments" section-key="detail-payments" :title="$texts.annual.savedPayments()" :export-data="exportData" :export-disabled="loading || error">
		<p v-if="selection.includeFuturePayments" class="report-payments-note">{{ $texts.annual.projectedPaymentsNote() }}</p>
		<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
		<div v-else-if="error" role="alert"><p>{{ $texts.dashboard.paymentsLoadError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
		<p v-else-if="!entries.length">{{ $texts.common.noFilteredEntries() }}</p>
		<EntryTable v-else :entries="entries" :mode="selection.entryScope === 'shared' ? 'project' : 'personal'" :currency="$currency" :date-groups="dateGroups"
			:sort-by="sortBy" :sort-dir="sortDir" edit-only :selected-entry-id="selectedEntryId"
			:enable-fixed-costs="$enableFixedCosts" :enable-subscriptions="$enableSubscriptions" :enable-child-related="$enableChildRelated"
			:enable-important-payments="$enableImportantPayments" :enable-review-payments="$enableReviewPayments"
			:enable-tax-relevant="$enableTaxRelevant" :enable-insurance="$enableInsurance"
			:amount-resolver="amount" :project-name-resolver="projectName" :project-color-resolver="projectColor" :is-shared-project-resolver="isSharedProject"
			:show-paid-by="selection.entryScope !== 'shared' || isSharedProject(selection.projectId)" @row-click="editEntry" @edit="editEntry" @history="openHistory" @sort="sort">
			<template #pagination>
				<nav class="report-payments-pagination" :class="{ 'is-single': total <= limit }" :aria-label="$texts.annual.savedPayments()">
					<NcButton v-if="total > limit" variant="tertiary" :disabled="page <= 1" @click="setPage(page - 1)">{{ $texts.common.previous() }}</NcButton>
					<span>{{ $texts.common.pageInfo(offset + 1, Math.min(offset + limit, total), total) }}</span>
					<NcButton v-if="total > limit" variant="tertiary" :disabled="offset + limit >= total" @click="setPage(page + 1)">{{ $texts.common.next() }}</NcButton>
				</nav>
			</template>
		</EntryTable>
		<DialogTransition :show="!!(historyOpen)" v-slot="{ returnFocus }">
			<EntryHistoryModal :container="null" :set-return-focus="returnFocus" :loading="historyLoading" :history="history" :error="historyError" @retry="openHistory(historyEntry)" @close="closeHistory" />
		</DialogTransition>
	</ReportDetailSection>
</template>

<script>
import DialogTransition from './DialogTransition.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import EntryTable from './EntryTable.vue'
import EntryHistoryModal from './EntryHistoryModal.vue'
import ReportDetailSection from './ReportDetailSection.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { normalizeEntryPageSize } from '../services/pagination'
import { reportPaymentParams } from '../services/reportDetails'
import { openReportPayment, loadReportPayments } from '../services/reportPayments'
import { extractError } from '../services/notifications'
import { reportPaymentExportSection, reportDisplayedTableExport, reportExportNotReady } from '../services/reportExport'
import { formatCalendarLabel } from '../utils/formatDate'

export default {
	name: 'ReportPayments',
	components: { DialogTransition, NcButton, EntryTable, EntryHistoryModal, ReportDetailSection },
	props: {
		detail: { type: Object, required: true }, selection: { type: Object, required: true }, report: { type: Object, required: true },
		projects: { type: Array, default: () => [] }, selectedEntryId: { type: [Number, String], default: null },
	},
	emits: ['open-entry-sidebar', 'selected-entry-missing'],
	data: () => ({ entries: [], dateGroups: null, total: 0, loading: true, error: false, request: 0, historyOpen: false, historyLoading: false, history: [], historyRequest: 0, historyError: '', historyEntry: null }),
	computed: {
		limit() { return normalizeEntryPageSize(this.$entriesPerPage) },
		page() { const value = Number(this.$route.query.detailPage); return Number.isInteger(value) && value > 0 ? value : 1 },
		offset() { return (this.page - 1) * this.limit },
		sortBy() { return ['date', 'amount', 'description', 'category_name', 'paymentPartner'].includes(this.$route.query.detailSort) ? this.$route.query.detailSort : 'date' },
		sortDir() { return this.$route.query.detailDir === 'asc' ? 'asc' : 'desc' },
		params() { return reportPaymentParams(this.detail.route, this.selection, this.report.cutoff, { limit: this.limit, offset: this.offset, sortBy: this.sortBy, sortDir: this.sortDir }) },
		requestKey() { return JSON.stringify(this.params) },
	},
	watch: { requestKey: { handler() { this.closeHistory(); this.load() }, immediate: true } },
	mounted() { window.addEventListener('entry-saved', this.load) },
	beforeUnmount() { this.request++; this.historyRequest++; window.removeEventListener('entry-saved', this.load) },
	methods: {
		async exportData({ display = false } = {}) {
			if (this.loading || this.error) throw reportExportNotReady()
			if (display) return reportDisplayedTableExport(this.$el.querySelector('.data-table'), this.$texts.annual.savedPayments())
			const options = { title: this.$texts.annual.savedPayments(), selection: { ...this.selection }, texts: this.$texts, projects: [...this.projects], detailType: this.detail.row.type,
				formatDate: timestamp => formatCalendarLabel(new Date(Number(timestamp) * 1000), { day: 'numeric', month: 'short', year: 'numeric' }) }
			return [reportPaymentExportSection(await this.loadPrintRows(), options)]
		},
		loadPrintRows() { const context = this.requestKey, request = this.request; return loadReportPayments({ ...this.params }, () => context === this.requestKey && request === this.request) },
		async load() {
			const request = ++this.request
			this.loading = true; this.error = false
			try {
				if (this.params.dateFrom > this.params.dateTo) { this.entries = []; this.dateGroups = null; this.total = 0; return }
				const { data } = await axios.get(generateUrl('/apps/cobudget/api/entries'), { params: this.params })
				if (request !== this.request) return
				this.entries = data.entries || []; this.dateGroups = data.dateGroups || null; this.total = Number(data.total || 0)
				const lastPage = Math.max(1, Math.ceil(this.total / this.limit))
				if (this.page > lastPage) { this.setPage(lastPage); return }
				if (this.selectedEntryId !== null && !this.entries.some(entry => String(entry.id) === String(this.selectedEntryId))) this.$emit('selected-entry-missing', this.selectedEntryId)
			} catch (error) { if (request === this.request) this.error = true }
			finally { if (request === this.request) this.loading = false }
		},
		setPage(page) { return this.$router.replace({ name: 'annual-overview', query: { ...this.$route.query, detailPage: page > 1 ? String(page) : undefined } }) },
		sort(column) {
			return this.$router.replace({ name: 'annual-overview', query: { ...this.$route.query, detailPage: undefined, detailSort: column, detailDir: this.sortBy === column && this.sortDir === 'desc' ? 'asc' : 'desc' } })
		},
		amount(entry) {
			const cents = this.selection.entryScope === 'shared' ? entry.amount_cents : entry.personal_amount_cents ?? entry.amount_cents
			return cents !== undefined && cents !== null ? Number(cents) / 100 : Number(entry.amount || 0)
		},
		projectColor(id) { return this.projects.find(project => Number(project.id) === Number(id))?.color || '' },
		projectName(id) { return this.projects.find(project => Number(project.id) === Number(id))?.name || '' },
		isSharedProject(id) { return Number(this.projects.find(project => Number(project.id) === Number(id))?.member_count || 0) > 1 },
		editEntry(entry) { return openReportPayment(entry, this.selection, this.$texts, payload => this.$emit('open-entry-sidebar', payload)) },
		async openHistory(entry) {
			if (!entry) return
			this.historyEntry = entry; this.historyError = ''
			const request = ++this.historyRequest
			this.historyOpen = true; this.historyLoading = true; this.history = []
			try { const { data } = await axios.get(generateUrl(`/apps/cobudget/api/entries/${entry.id}/history`)); if (request === this.historyRequest) this.history = data.history || [] }
			catch (error) { if (request === this.historyRequest) this.historyError = extractError(error, this.$texts.entry.historyFetchError()) }
			finally { if (request === this.historyRequest) this.historyLoading = false }
		},
		closeHistory() { this.historyRequest++; this.historyError = ''; this.historyEntry = null; this.historyOpen = false; this.history = []; this.historyLoading = false },
	},
}
</script>

<style scoped>
.report-payments { min-width: 0; }
.report-payments-note { color: var(--cobudget-text-muted); margin-bottom: calc(var(--default-grid-baseline, 4px) * 3); }
.report-payments-pagination { display: flex; justify-content: space-between; align-items: center; gap: var(--default-grid-baseline, 4px); margin-top: calc(var(--default-grid-baseline, 4px) * 3); }
.report-payments-pagination span { text-align: center; font-size: var(--default-font-size, 15px); }
.report-payments-pagination.is-single { justify-content: center; }
@media (max-width: 768px) { .report-payments-pagination { flex-wrap: wrap; } }
</style>
