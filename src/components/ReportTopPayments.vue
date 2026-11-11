<template>
	<div class="report-top-payments" :class="{ 'is-collapsed': !active }">
		<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
		<p v-else-if="error" role="alert">{{ $texts.dashboard.paymentsLoadError() }} <NcButton variant="tertiary" @click="load">{{ $texts.annual.retry() }}</NcButton></p>
		<p v-else-if="!entries.length">{{ $texts.common.noFilteredEntries() }}</p>
		<EntryTable v-else :entries="visibleEntries" :mode="selection.entryScope === 'shared' ? 'project' : 'personal'" :currency="$currency"
			:table-class="`report-top-payments-table${active ? '' : ' is-collapsed'}`" :table-title="tableTitle" row-class="report-top-payment-row"
			:sort-enabled="false" sort-by="amount" sort-dir="desc" :group-by-date="false" :show-paid-by="false" :show-actions-column="false"
			:selected-entry-id="selectedEntryId" :amount-resolver="paymentAmount"
			:enable-fixed-costs="$enableFixedCosts" :enable-subscriptions="$enableSubscriptions" :enable-child-related="$enableChildRelated"
			:enable-important-payments="$enableImportantPayments" :enable-review-payments="$enableReviewPayments" :enable-tax-relevant="$enableTaxRelevant" :enable-insurance="$enableInsurance"
			:project-name-resolver="projectName" :project-color-resolver="projectColor" :is-shared-project-resolver="isSharedProject" @row-click="editEntry" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import EntryTable from './EntryTable.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualPaymentRoute } from '../services/annualOverview'
import { reportPaymentParams } from '../services/reportDetails'
import { openReportPayment } from '../services/reportPayments'
import { getAppLocale } from '../utils/formatMoney'
import { reportPaymentExportSection, reportDisplayedTableExport, reportExportNotReady } from '../services/reportExport'

export default {
	name: 'ReportTopPayments',
	components: { NcButton, EntryTable },
	props: { projects: { type: Array, default: () => [] }, selection: { type: Object, required: true }, report: { type: Object, required: true }, type: { type: String, required: true }, title: { type: String, required: true }, active: { type: Boolean, default: false }, selectedEntryId: { type: [Number, String], default: null } },
	data: () => ({ entries: [], loading: false, loaded: false, error: false, request: 0 }),
	computed: {
		tableTitle() { return `${this.$props.title} · ${this.$texts.annual.topTen()}` },
		params() { return reportPaymentParams(annualPaymentRoute(this.selection, this.report.periods.at(-1), this.type, null), this.selection, this.report.cutoff, { limit: 10, offset: 0, sortBy: 'amount', sortDir: 'desc' }) },
		requestContext() { return { params: this.params, report: this.report } },
		visibleEntries() { return this.loading || this.error ? [] : this.entries },
	},
	watch: {
		requestContext: { handler: 'invalidate', immediate: true },
		active(value) { if (value && !this.loaded) this.load() },
	},
	beforeUnmount() { this.request++ },
	methods: {
		exportData({ display = false } = {}) {
			if (this.loading || this.error || !this.loaded) throw reportExportNotReady()
			if (display) return reportDisplayedTableExport(this.$el.querySelector('.data-table'), this.tableTitle)
			return [reportPaymentExportSection(this.entries, { title: this.tableTitle, selection: this.selection, texts: this.$texts, projects: this.projects, formatDate: this.date, detailType: this.type })]
		},
		invalidate() { this.request++; this.entries = []; this.loaded = false; this.loading = false; this.error = false; if (this.active) return this.load() },
		async load() {
			const request = ++this.request
			this.loading = true; this.error = false
			try {
				if (this.params.dateFrom > this.params.dateTo) { this.entries = []; this.loaded = true; return }
				const { data } = await axios.get(generateUrl('/apps/cobudget/api/entries'), { params: this.params })
				if (request !== this.request) return
				this.entries = data.entries || []; this.loaded = true
			} catch (error) { if (request === this.request) { this.entries = []; this.error = true } }
			finally { if (request === this.request) this.loading = false }
		},
		amount(entry) { const cents = this.selection.entryScope === 'shared' ? entry.amount_cents : entry.personal_amount_cents ?? entry.amount_cents; return cents === undefined || cents === null ? Math.round(Number(entry.amount || 0) * 100) : Number(cents) },
		paymentAmount(entry) { return this.amount(entry) / 100 },
		projectColor(id) { return this.projects.find(project => Number(project.id) === Number(id))?.color || '' },
		projectName(id) { return this.projects.find(project => Number(project.id) === Number(id))?.name || '' },
		isSharedProject(id) { return Number(this.projects.find(project => Number(project.id) === Number(id))?.member_count || 0) > 1 },
		date(value, compact = false) { const text = new Intl.DateTimeFormat(getAppLocale(), { day: '2-digit', month: '2-digit', ...(compact ? {} : { year: 'numeric' }) }).format(new Date(Number(value) * 1000)); return compact ? text.replace(/\.$/, '') : text },
		editEntry(entry) { return openReportPayment(entry, this.selection, this.$texts, payload => this.$emit('open-entry-sidebar', payload)) },
	},
	emits: ['open-entry-sidebar'],
}
</script>

<style scoped>
.report-top-payments { min-width: 0; --cobudget-payment-row-bleed: 0px; }
.report-top-payments > p { padding: var(--cobudget-content-gap); }
</style>
