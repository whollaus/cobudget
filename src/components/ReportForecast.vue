<template>
	<div v-if="eligible && result?.status !== 'past'" class="report-forecast">
		<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
		<div v-else-if="error" role="alert"><p>{{ $texts.annual.forecastError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
		<template v-else-if="result">
			<slot :result="result" />
			<div class="report-forecast-scroll" tabindex="0" role="region" :aria-label="$texts.annual.forecastBreakdown()">
				<table class="report-forecast-table" :aria-label="$texts.annual.forecastBreakdown()">
					<thead><tr><th scope="col">{{ $texts.annual.forecastBreakdown() }}</th><th v-for="column in columns" :key="column.key" scope="col">{{ column.label }}</th></tr></thead>
					<tbody>
						<tr v-for="row in rows" :key="row.key" :class="{ 'report-forecast-estimate': row.key === 'estimated' }">
							<th scope="row">{{ row.label }}</th><td v-for="column in columns" :key="column.key">{{ row.values && !(row.key === 'estimated' && ((column.key === 'incomeCents' && !result.incomePatternCount) || (column.key === 'expenseCents' && !result.expenseEstimateAvailable))) ? $formatMoneyFromCents(row.values[column.key]) : '–' }}</td>
						</tr>
					</tbody>
					<tfoot><tr><th scope="row">{{ result.estimateAvailable ? $texts.annual.forecastTotal() : $texts.annual.forecastKnownTotal() }}</th><td v-for="column in columns" :key="column.key">{{ $formatMoneyFromCents(result.forecast[column.key]) }}</td></tr></tfoot>
				</table>
			</div>
			<p v-if="showExpenses && result.status === 'current' && result.remainingDays > 0 && !result.expenseEstimateAvailable" class="report-forecast-note">{{ $texts.annual.forecastUnavailable() }}</p>
		</template>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualRequestParams, reportDate } from '../services/annualOverview'
import { reportExportMoney, reportDisplayedTableExport, reportExportNotReady } from '../services/reportExport'

export default {
	name: 'ReportForecast',
	components: { NcButton },
	props: { selection: { type: Object, required: true }, report: { type: Object, required: true }, detail: { type: Object, default: null }, chartPeriod: { type: String, default: 'year' } },
	data: () => ({ result: null, loading: true, error: false, request: 0 }),
	computed: {
		period() { return this.report.periods[this.detail ? this.detail.index : this.report.periods.length - 1] },
		eligible() { return Boolean(this.period && this.period.dateTo >= Date.now() / 1000) },
		params() {
			if (!this.period) return null
			const params = annualRequestParams(this.selection)
			for (const key of ['year', 'month', 'quarter', 'period', 'comparison', 'includeFuturePayments']) delete params[key]
			return { ...params, period: this.chartPeriod === 'month' ? 'day' : this.chartPeriod, dateFrom: reportDate(new Date(this.period.dateFrom * 1000)), dateTo: reportDate(new Date(this.period.dateTo * 1000)),
				detailType: this.detail?.row.type || (this.$enableIncomes === false ? 'expense' : 'all'), detailId: !this.detail || this.detail.total ? 'total' : String(this.detail.row.reportKey ?? this.detail.row.id), detailDirect: Boolean(this.detail?.row.direct) }
		},
		requestContext() { return { params: this.params, report: this.report } },
		showIncome() { return this.$enableIncomes !== false && (!this.detail || this.detail.row.type !== 'expense') },
		showExpenses() { return !this.detail || this.detail.row.type !== 'income' },
		columns() {
			return [
				...(this.showIncome ? [{ key: 'incomeCents', label: this.$texts.annual.income() }] : []),
				...(this.showExpenses ? [{ key: 'expenseCents', label: this.$texts.annual.expenses() }] : []),
				...(this.showIncome && this.showExpenses ? [{ key: 'balanceCents', label: this.$texts.analytics.balance() }] : []),
			]
		},
		rows() {
			if (!this.result) return []
			return [
				...(this.result.status === 'current' ? [{ key: 'actual', label: this.$texts.annual.paymentsUntilToday(), values: this.result.actual }] : []),
				{ key: 'planned', label: this.$texts.annual.forecastKnownFuture(), values: this.result.planned },
				...((this.showExpenses || this.result.incomePatternCount > 0) && this.result.status === 'current' && (this.result.remainingDays > 0 || this.result.incomePatternCount > 0) ? [{ key: 'estimated', label: this.$texts.annual.forecastAdditional(), values: this.result.estimated }] : []),
			]
		},
	},
	watch: { requestContext: { handler: 'load', immediate: true } },
	mounted() { window.addEventListener('entry-saved', this.load) },
	beforeUnmount() { this.request++; window.removeEventListener('entry-saved', this.load) },
	methods: {
		exportData({ display = false } = {}) {
			if (this.loading || this.error || !this.result) throw reportExportNotReady()
			if (display) return reportDisplayedTableExport(this.$el.querySelector('.report-forecast-table'), this.$texts.annual.forecastBreakdown())
			const values = row => this.columns.map(column => row.key === 'estimated' && ((column.key === 'incomeCents' && !this.result.incomePatternCount) || (column.key === 'expenseCents' && !this.result.expenseEstimateAvailable)) ? '' : reportExportMoney(row.values[column.key]))
			return [{ title: this.$texts.annual.forecastBreakdown(), columns: [this.$texts.common.name(), ...this.columns.map(column => column.label)],
				rows: [...this.rows.map(row => [row.label, ...values(row)]), [this.result.estimateAvailable ? this.$texts.annual.forecastTotal() : this.$texts.annual.forecastKnownTotal(), ...this.columns.map(column => reportExportMoney(this.result.forecast[column.key]))]] }]
		},
		async load() {
			const request = ++this.request
			this.result = null; this.loading = false; this.error = false
			if (!this.eligible) return
			this.loading = true
			try {
				const { data } = await axios.get(generateUrl('/apps/cobudget/api/report-forecast'), { params: this.params })
				if (request === this.request) this.result = data
			} catch (error) { if (request === this.request) this.error = true }
			finally { if (request === this.request) this.loading = false }
		},
	},
}
</script>

<style scoped>
.report-forecast { min-width: 0; }
.report-forecast-note { margin: 0 0 calc(var(--default-grid-baseline, 4px) * 2); color: var(--cobudget-text-muted); font-size: var(--cobudget-font-sm); }
.report-forecast-scroll { overflow-x: auto; border-radius: var(--cobudget-table-radius); }
.report-forecast-scroll:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: 2px; }
.report-forecast-table { width: 100%; margin: 0; border-collapse: separate; border-spacing: 0; border: 0; border-radius: var(--cobudget-table-radius); font-size: var(--cobudget-font-base); }
.report-forecast-table th, .report-forecast-table td { box-sizing: border-box; padding: var(--cobudget-table-payment-padding); height: var(--cobudget-payment-table-row-height); border-bottom: 1px solid var(--cobudget-table-separator); color: var(--cobudget-text); background: var(--cobudget-surface); font-size: inherit; font-weight: 400; vertical-align: middle; }
.report-forecast-table th { text-align: start; white-space: normal; }
.report-forecast-table td { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
.report-forecast-table thead th { padding: var(--cobudget-table-payment-padding); color: var(--cobudget-text-muted); letter-spacing: normal; font-size: var(--cobudget-table-header-font-size); font-weight: 400; height: var(--cobudget-table-header-height); background: var(--cobudget-table-header-background); }
.report-forecast-table thead th:not(:first-child) { text-align: end; }
.report-forecast-table thead th:first-child { border-start-start-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-forecast-table thead th:last-child { border-start-end-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-forecast-table tfoot th, .report-forecast-table tfoot td { font-weight: 600; border-top: 1px solid var(--color-border-maxcontrast, var(--cobudget-border-strong)); border-bottom: 0; background: var(--cobudget-surface); }
.report-forecast-table tbody tr:last-child > * { border-bottom: 0; }
.report-forecast-table tfoot th { border-end-start-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-forecast-table tfoot td:last-child { border-end-end-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-forecast-table .report-forecast-estimate th, .report-forecast-table .report-forecast-estimate td { border-top: 1px dashed var(--cobudget-border); }
@media (max-width: 768px) {
	/* Mobile reports meet the viewport edge, including individual corner cells. */
	.report-forecast { --cobudget-table-radius: 0px; }
}
</style>
