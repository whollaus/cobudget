<template>
	<table role="table" class="report-top-payments-table" :class="{ 'is-collapsed': !active }" :aria-label="tableTitle" :data-print-title="tableTitle">
		<caption class="report-top-payments-caption">{{ tableTitle }}</caption>
		<thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col" class="report-top-date">{{ $texts.entry.tableDate() }}</th><th role="columnheader" scope="col" class="report-top-description-heading">{{ $texts.entry.tableDescription() }}</th><th role="columnheader" scope="col" class="report-top-category-cell">{{ $texts.entry.tableCategory() }}</th><th role="columnheader" scope="col" class="report-top-partner">{{ $texts.entry.tablePaymentPartner() }}</th><th role="columnheader" scope="col" class="numeric">{{ $texts.entry.tableAmount($currency) }}</th></tr></thead>
		<tbody role="rowgroup">
			<tr v-if="loading" role="row"><td colspan="5" role="status">{{ $texts.common.loading() }}</td></tr>
			<tr v-else-if="error" role="row"><td colspan="5" role="alert">{{ $texts.dashboard.paymentsLoadError() }} <NcButton variant="tertiary" @click="load">{{ $texts.annual.retry() }}</NcButton></td></tr>
			<tr v-else-if="!entries.length" role="row"><td role="cell" colspan="5">{{ $texts.common.noFilteredEntries() }}</td></tr>
			<tr v-for="entry in visibleEntries" :key="entry.id" role="row" class="report-top-payment-row" :class="{ 'is-selected': String(entry.id) === String(selectedEntryId) }"
				:aria-selected="String(entry.id) === String(selectedEntryId)" tabindex="0" @click="editEntry(entry)" @keydown.enter.prevent="editEntry(entry)" @keydown.space.prevent="editEntry(entry)">
				<td role="cell" class="report-top-date" :title="date(entry.date)" :aria-label="date(entry.date)"><span class="report-top-date-full">{{ date(entry.date) }}</span><span class="report-top-date-short" aria-hidden="true">{{ date(entry.date, true) }}</span></td>
				<td role="cell" class="report-top-description">{{ entry.description }}</td>
				<td role="cell" class="report-top-category-cell"><span v-if="entry.category_name" class="report-top-category"><CategoryIcon v-if="entry.category_icon" :icon="entry.category_icon" :size="16" /><span>{{ entry.category_name }}</span></span></td>
				<td role="cell" class="report-top-partner">{{ entry.paymentPartner }}</td>
				<td role="cell" class="numeric">{{ $formatMoneyFromCents(amount(entry)) }}</td>
			</tr>
		</tbody>
	</table>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import CategoryIcon from './CategoryIcon.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualPaymentRoute } from '../services/annualOverview'
import { reportPaymentParams } from '../services/reportDetails'
import { openReportPayment } from '../services/reportPayments'
import { getAppLocale } from '../utils/formatMoney'
import { reportPaymentExportSection, reportDisplayedTableExport, reportExportNotReady } from '../services/reportExport'

export default {
	name: 'ReportTopPayments',
	components: { NcButton, CategoryIcon },
	props: { selection: { type: Object, required: true }, report: { type: Object, required: true }, type: { type: String, required: true }, title: { type: String, required: true }, active: { type: Boolean, default: false }, selectedEntryId: { type: [Number, String], default: null } },
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
			if (display) return reportDisplayedTableExport(this.$el, this.tableTitle)
			return [reportPaymentExportSection(this.entries, { title: this.tableTitle, selection: this.selection, texts: this.$texts, formatDate: this.date, detailType: this.type })]
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
		date(value, compact = false) { const text = new Intl.DateTimeFormat(getAppLocale(), { day: '2-digit', month: '2-digit', ...(compact ? {} : { year: 'numeric' }) }).format(new Date(Number(value) * 1000)); return compact ? text.replace(/\.$/, '') : text },
		editEntry(entry) { return openReportPayment(entry, this.selection, this.$texts, payload => this.$emit('open-entry-sidebar', payload)) },
	},
	emits: ['open-entry-sidebar'],
}
</script>

<style scoped>
.report-top-payments-table { width: 100%; min-width: 560px; margin: 0; border-collapse: separate; border-spacing: 0; border: 1px solid var(--cobudget-border); border-radius: var(--cobudget-table-radius); font-size: var(--cobudget-font-sm); }
.report-top-payments-caption { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
.report-top-payments-table th, .report-top-payments-table td { padding: var(--cobudget-table-cell-padding); height: auto; border-bottom: 1px solid var(--cobudget-border); color: var(--cobudget-text); background: var(--cobudget-surface); font: inherit; text-align: start; vertical-align: middle; }
.report-top-payments-table thead th { color: var(--cobudget-text-muted); letter-spacing: 0.5px; background: var(--cobudget-table-header-background); white-space: nowrap; }
.report-top-payments-table thead th:first-child { border-start-start-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-top-payments-table thead th:last-child { border-start-end-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-top-payments-table tbody tr:last-child > * { border-bottom: 0; }
.report-top-payments-table tbody tr:last-child > :first-child { border-end-start-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-top-payments-table tbody tr:last-child > :last-child { border-end-end-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-top-payments-table .numeric { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
.report-top-payments-table .numeric { position: sticky; inset-inline-end: 0; z-index: 1; box-shadow: -1px 0 var(--cobudget-border); }
.report-top-payments-table thead th:first-child { width: 108px; }
.report-top-payments-table thead th:last-child { width: 156px; }
.report-top-payments-table .report-top-date { color: var(--cobudget-text-muted); white-space: nowrap; }
.report-top-date-short { display: none; }
.report-top-description { font-weight: 500; }
.report-top-category { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); }
.report-top-category :deep(.material-design-icon) { flex-shrink: 0; }
.report-top-payment-row .numeric { font-weight: 600; }
.report-top-payment-row, .report-top-payment-row :deep(*) { cursor: pointer; }
.report-top-payment-row:hover > * { background: var(--cobudget-surface-muted); }
.report-top-payment-row.is-selected > *, .report-top-payment-row.is-selected:hover > * { background: var(--color-primary-element-light, var(--color-background-dark)) !important; }
.report-top-payment-row:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: -2px; }
@media (min-width: 769px) {
	.report-top-payment-row.is-selected > td { box-shadow: inset 0 2px 0 var(--color-primary, #0082c9), inset 0 -2px 0 var(--color-primary, #0082c9); }
}
@media (max-width: 768px) {
	/* Mobile reports meet the viewport edge, including individual corner cells. */
	.report-top-payments { --cobudget-table-radius: 0px; }
	/* Reflow the same five cells so printing and exports retain separate fields. */
	.report-top-payments-table { display: block; min-width: 460px; white-space: normal; }
	.report-top-payments-table thead, .report-top-payments-table tbody { display: block; }
	.report-top-payments-table tr { display: grid; grid-template-columns: 64px minmax(144px, 1fr) minmax(144px, 1fr) 108px; border-bottom: 1px solid var(--cobudget-border); }
	.report-top-payments-table tr:last-child { border-bottom: 0; }
	.report-top-payments-table thead tr { border-bottom: 1px solid var(--cobudget-border); }
	.report-top-payments-table th, .report-top-payments-table td { min-width: 0; width: auto; padding-inline: calc(var(--default-grid-baseline, 4px) * 1.5); border-bottom: 0; overflow-wrap: anywhere; }
	.report-top-payments-table .report-top-description-heading { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
	.report-top-payments-table .report-top-date { grid-column: 1; position: sticky; inset-inline-start: 0; z-index: 1; box-shadow: 1px 0 var(--cobudget-border); justify-content: center; text-align: center; }
	.report-top-payments-table .report-top-category-cell { grid-column: 2; grid-row: 1; border-inline-end: 1px solid var(--cobudget-border); }
	.report-top-payments-table .report-top-partner { grid-column: 3; grid-row: 1; }
	.report-top-payments-table .report-top-description { grid-column: 2 / 4; grid-row: 2; color: var(--cobudget-text-muted); font-weight: 400; }
	.report-top-payments-table .report-top-category-cell, .report-top-payments-table .report-top-partner, .report-top-payments-table .report-top-description { white-space: normal; text-overflow: clip; overflow: visible; }
	.report-top-category { min-width: 0; align-items: flex-start; }
	.report-top-category > span { min-width: 0; max-width: 100%; }
	.report-top-category :deep(.category-icon) { display: none; }
	.report-top-payments-table .report-top-description:empty { display: none; }
	.report-top-payments-table .numeric { grid-column: 4; }
	.report-top-payments-table thead th:first-child, .report-top-payments-table thead th:last-child { width: auto; }
	.report-top-payments-table .report-top-date, .report-top-payments-table .numeric { display: flex; align-items: flex-start; }
	.report-top-payments-table .numeric { justify-content: flex-end; }
	.report-top-payment-row { min-height: var(--default-clickable-area, 44px); }
	.report-top-payment-row > .report-top-date, .report-top-payment-row > .numeric { grid-row: 1 / 3; }
	.report-top-payments-table td[colspan] { grid-column: 1 / -1; }
	.report-top-date-full { display: none; }
	.report-top-date-short { display: inline; }
}
</style>
