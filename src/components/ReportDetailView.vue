<template>
	<div ref="printSource" class="report-detail">
		<AppPageHeader class="report-detail-header" :title="detail.label" :subtitle="subtitle">
			<template #title><span class="report-detail-title"><NcButton variant="tertiary" class="cobudget-native-button report-detail-back" :aria-label="$texts.annual.back()" :title="$texts.annual.back()" @click="$emit('back')"><template #icon><ArrowLeftIcon :size="20" /></template></NcButton><ReportPeriodPicker navigation-only force-navigation :period-enabled="false" :title="detail.label" :selection="selection" label="" /></span></template>
			<template #subtitle><span v-for="(part, index) in subtitleParts" :key="index" class="report-detail-context"><span v-if="index" class="report-detail-context-separator" aria-hidden="true"> · </span>{{ part }}</span></template>
			<template #actions><NcButton variant="tertiary" class="cobudget-report-action" :disabled="printing || chartLoading || chartError" :aria-label="$texts.analytics.print()" :title="$texts.analytics.print()" @click="printReport"><template #icon><PrinterIcon :size="20" /></template>{{ $texts.analytics.print() }}</NcButton></template>
		</AppPageHeader>
		<p v-if="chartLoading && !wholePeriod" role="status">{{ $texts.common.loading() }}</p>
		<div v-else-if="chartError && !wholePeriod" role="alert"><p>{{ $texts.annual.loadError() }}</p><NcButton @click="loadChart">{{ $texts.annual.retry() }}</NcButton></div>
		<ReportDevelopment v-else :key="chartKey" v-model:mode="chartMode" v-model:hidden-series="hiddenSeries" :report="report" :chart-report="chartData" :selection="selection" :detail="detail" :summary-rows="summaryRows" :period-label="subtitle" :chart-period="(focusedSelection || selection).period"
			:labels="chartLabels.labels" :label-titles="chartLabels.titles" :axis-labels="chartLabels.labels"
			:series-ids="detail.row.type === 'all' ? null : [detail.row.type]" section-key="detail-chart" :comparison-series="comparisonSeries">
			<template #controls><ReportComparisonPicker v-model="selectedComparisons" :comparisons="comparisons" /></template>
		</ReportDevelopment>
		<AnnualOverviewDetails embedded :detail="detail" :report="report" :selection="selection" :scope-label="scopeLabel" :annual-total="annualTotal" :comparison-data="comparisons" :comparisons-loading="chartLoading" :comparisons-error="chartError" @retry="loadChart" />
		<ReportBreakdown :detail="detail" :report="report" :selection="selection" />
		<ReportPayments ref="payments" :detail="detail" :report="report" :selection="selection" :projects="projects" :selected-entry-id="selectedEntryId"
			@open-entry-sidebar="$emit('open-entry-sidebar', $event)" @selected-entry-missing="$emit('selected-entry-missing', $event)" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import PrinterIcon from 'vue-material-design-icons/Printer.vue'
import ReportPeriodPicker from './ReportPeriodPicker.vue'
import AppPageHeader from './AppPageHeader.vue'
import ReportDevelopment from './ReportDevelopment.vue'
import AnnualOverviewDetails from './AnnualOverviewDetails.vue'
import ReportPayments from './ReportPayments.vue'
import ReportBreakdown from './ReportBreakdown.vue'
import ReportComparisonPicker from './ReportComparisonPicker.vue'
import { reportComparisonSeries, reportSelectionAverage, reportAnnualAverages, reportAnnualAverageBasis, reportMatchesAverageBasis, reportAverageForPeriod } from '../services/reportComparisons'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualRequestParams, annualSelection, reportPeriodLabel } from '../services/annualOverview'
import { reportAnnualTotal, reportChartLabels, reportDetailRow } from '../services/reportDetails'
import { formatCalendarLabel } from '../utils/formatDate'
import { printAnnualOverview } from '../services/annualOverviewPrint'
import { reportExportMetadata, reportPaymentPrintData } from '../services/reportExport'
import { showRequestError, showToast } from '../services/notifications'

export default {
	name: 'ReportDetailView',
	components: { ReportPeriodPicker, NcButton, ArrowLeftIcon, PrinterIcon, AppPageHeader, ReportDevelopment, AnnualOverviewDetails, ReportPayments, ReportBreakdown, ReportComparisonPicker },
	props: {
		detail: { type: Object, required: true }, report: { type: Object, required: true }, selection: { type: Object, required: true },
		scopeLabel: { type: String, required: true }, projects: { type: Array, default: () => [] }, selectedEntryId: { type: [Number, String], default: null },
		labels: { type: Array, required: true }, labelTitles: { type: Array, required: true }, axisLabels: { type: Array, required: true },
		exportMetadata: { type: Array, default: () => [] },
	},
	emits: ['back', 'open-entry-sidebar', 'selected-entry-missing'],
	provide() { return { reportExportContext: () => ({
		filename: `cobudget-auswertung-${this.report.periods[this.detail.index].dateFrom}-${this.report.periods[this.detail.index].dateTo}-detail.csv`,
		metadata: [...reportExportMetadata(this.selection, { report: this.report, period: this.report.periods[this.detail.index], scopeLabel: this.scopeLabel, currency: this.$currency, texts: this.$texts,
			formatDate: timestamp => formatCalendarLabel(new Date(Number(timestamp) * 1000), { day: 'numeric', month: 'short', year: 'numeric' }),
			filterLabels: this.exportMetadata.filter(([label]) => label === this.$texts.annual.filterSummary()).map(([, value]) => value) }), [this.$texts.common.name(), this.detail.label]],
	}) } },
	data: () => ({ printing: false, printRequest: 0, chartMode: 'period', selectedComparisons: ['average-selection'], hiddenSeries: [], chartLoading: false, chartError: false, focusedReport: null, focusedSelection: null, averageBasis: null, averageReport: null, chartRequest: 0 }),
	computed: {
		wholePeriod() { return this.detail.index === this.report.periods.length - 1 },
		chartKey() { return `${this.detail.row.type}:${this.detail.row.reportKey ?? this.detail.row.id ?? 'total'}:${this.detail.row.direct || false}:${this.detail.index}` },
		subtitleParts() {
			const period = this.report.periods[this.detail.index]
			const format = timestamp => formatCalendarLabel(new Date(timestamp * 1000), { day: 'numeric', month: 'short', year: 'numeric' })
			const type = this.detail.row.type === 'income' ? this.$texts.annual.income() : this.detail.row.type === 'expense' ? this.$texts.annual.expenses() : this.$texts.analytics.balance()
			const cutoff = !this.selection.includeFuturePayments && this.report.cutoff < period.dateTo ? ` · ${this.$texts.annual.paymentsUntilToday()}` : ''
			const label = this.wholePeriod && this.selection.timeRange === 'calendar-selection' ? reportPeriodLabel(this.selection, formatCalendarLabel, date => format(date.getTime() / 1000)) : `${format(period.dateFrom)} – ${format(period.dateTo)}`
			return [`${this.detail.total ? '' : `${type} · `}${label}`, `${this.scopeLabel}${cutoff}`]
		},
		subtitle() { return this.subtitleParts.join(' · ') },
		chartData() {
			const source = this.focusedReport || this.report
			if (this.detail.row.type === 'all') return source
			const row = this.focusedReport ? reportDetailRow(source, this.detail) : this.detail.row
			return { ...source, totals: { ...source.totals, [this.detail.row.type]: row } }
		},
		summaryRows() {
			const types = this.detail.row.type === 'all' ? (this.$enableIncomes === false ? ['expense'] : ['income', 'expense', 'balance']) : [this.detail.row.type]
			return types.map(type => {
				const row = this.detail.row.type !== 'all' ? this.detail.row : this.report.totals[type]
				return { key: type, type: type === 'balance' ? 'all' : type, label: type === 'income' ? this.$texts.annual.income() : type === 'expense' ? this.$texts.annual.expenses() : this.$texts.analytics.balance(), total: this.wholePeriod ? row.total : (row.months[this.detail.index] || 0) }
			})
		},
		annualTotal() { return [this.averageReport, this.focusedReport, this.report].map(source => reportAnnualTotal(source, this.detail)).find(Boolean) || null },
		comparisons() {
			let averages = []
			const source = [this.averageReport, this.focusedReport, this.report].find(value => reportAnnualTotal(value, this.detail))
			if (source) {
				const type = this.detail.row.type === 'all' ? 'balance' : this.detail.row.type
				const subject = { ...source, totals: { ...source.totals, [type]: reportDetailRow(source, this.detail) } }
				const unit = this.averageBasis?.unit || ({ year: 'month', quarter: 'quarter', week: 'week', day: 'day', month: 'day' })[this.selection.period]
				averages = reportAnnualAverages(subject, this.detail.row.type, unit).map(value => reportAverageForPeriod(value, this.chartData, this.report.periods[this.detail.index]))
			} else if (!this.averageBasis) {
				const average = reportSelectionAverage(this.chartData, this.detail.row.type, (this.focusedSelection || this.selection).period)
				if (average) averages = [average]
			}
			const references = (this.focusedReport?.detailComparisons || []).filter(value => ['period', 'reference-average'].includes(value.kind))
			return [...averages, ...references].filter(value => value.available)
		},
		comparisonSeries() { return reportComparisonSeries(this.comparisons, this.selectedComparisons, this.chartLabels.labels.length, this.chartMode === 'cumulative', this.$texts, formatCalendarLabel) },
		chartLabels() {
			const base = this.focusedReport
				? reportChartLabels(this.focusedReport.periods.slice(0, -1), this.focusedSelection, formatCalendarLabel)
				: { labels: this.axisLabels, titles: this.labelTitles }
			const length = Math.max(base.labels.length, ...this.comparisons.filter(value => value.available && this.selectedComparisons.includes(value.id)).map(value => value.values.length))
			return { labels: Array.from({ length }, (_, index) => base.labels[index] ?? String(index + 1)), titles: Array.from({ length }, (_, index) => base.titles[index] ?? '–') }
		},
	},
	watch: { detail: { handler: 'resetChart', immediate: true, deep: true }, selection: { handler: 'resetChart', deep: true } },
	beforeUnmount() { this.chartRequest++; this.printRequest++ },
	methods: {
		async printReport() {
			if (this.printing || this.chartLoading || this.chartError || !this.$refs.printSource) return
			this.printing = true
			const request = ++this.printRequest
			const source = this.$refs.printSource, report = this.report, key = this.chartKey, selection = JSON.stringify(this.selection)
			try {
				const formatDate = timestamp => formatCalendarLabel(new Date(Number(timestamp) * 1000), { day: 'numeric', month: 'short', year: 'numeric' })
				let payments
				const paymentSection = source.querySelector('.report-payments:not(.is-collapsed)')
				if (paymentSection) payments = reportPaymentPrintData(await this.$refs.payments.loadPrintRows(), { selection: this.selection, texts: this.$texts, formatDate, formatCents: this.$formatMoneyFromCents, projects: this.projects, detailType: this.detail.row.type })
				if (this.printRequest !== request || this.report !== report || this.chartKey !== key || JSON.stringify(this.selection) !== selection) throw new Error('Report selection changed')
				const metadata = reportExportMetadata(this.selection, { report, period: report.periods[this.detail.index], scopeLabel: this.scopeLabel, currency: this.$currency, texts: this.$texts, formatDate,
					filterLabels: this.exportMetadata.filter(([label]) => label === this.$texts.annual.filterSummary()).map(([, value]) => value) })
				await printAnnualOverview(source, { title: `CoBudget – ${this.$texts.annual.title()} · ${this.detail.label}`, metadata, payments })
			} catch (error) {
				if (error?.code === 'REPORT_LOADING') showToast(this.$texts.common.loading(), 'info')
				else showRequestError(error, this.$texts.annual.printError())
			} finally { this.printing = false }
		},
		resetChart() { this.selectedComparisons = ['average-selection']; this.hiddenSeries = []; this.loadChart() },
		async loadChart() {
			const request = ++this.chartRequest
			this.focusedReport = null; this.chartError = false; this.chartLoading = false
			const period = this.report.periods[this.detail.index]
			this.averageBasis = reportAnnualAverageBasis(this.detail, this.report, this.selection)
			this.averageReport = this.averageBasis && reportMatchesAverageBasis(this.report, this.selection, this.averageBasis) ? this.report : null
			const day = timestamp => { const date = new Date(timestamp * 1000); return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` }
			this.focusedSelection = this.wholePeriod ? this.selection : annualSelection({ ...this.selection, timeRange: 'custom', dateFrom: day(period.dateFrom), dateTo: day(period.dateTo), period: undefined, month: 0 })
			const futurePeriod = !this.selection.includeFuturePayments && period.dateFrom > this.report.cutoff
			if (futurePeriod) {
				const empty = { months: [0], counts: [0], total: 0, totalCount: 0 }
				this.focusedReport = { ...this.report, periods: [period, period], groups: { income: [], expense: [] }, totals: { income: empty, expense: empty, balance: empty } }
				if (!this.averageBasis || this.averageReport) return
			}
			this.chartLoading = true
			try {
				const url = generateUrl('/apps/cobudget/api/annual-overview')
				const focused = futurePeriod ? Promise.resolve({ data: this.focusedReport })
					: axios.get(url, { params: { ...annualRequestParams(this.focusedSelection, { comparison: 'detail' }), detailType: this.detail.row.type, detailId: this.detail.total ? 'total' : String(this.detail.row.reportKey ?? this.detail.row.id), detailDirect: Boolean(this.detail.row.direct), detailAverageUnit: this.averageBasis?.unit || ({ year: 'month', quarter: 'quarter', week: 'week', day: 'day', month: 'day' })[this.selection.period] } })
				const average = this.averageBasis && !this.averageReport
					? axios.get(url, { params: annualRequestParams(annualSelection({ ...this.selection, ...this.averageBasis, timeRange: 'custom', month: 0 })) })
					: Promise.resolve({ data: this.averageReport })
				const [focusedResult, averageResult] = await Promise.all([focused, average])
				if (request === this.chartRequest) { this.focusedReport = focusedResult.data; this.averageReport = averageResult.data }
			} catch (error) { if (request === this.chartRequest) this.chartError = true }
			finally { if (request === this.chartRequest) this.chartLoading = false }
		},
	},
}
</script>

<style scoped>
.report-detail { min-width: 0; }
.report-detail-title { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); min-width: 0; }
.report-detail-title > span { min-width: 0; overflow-wrap: anywhere; }
.report-detail-header :deep(.app-page-header__title) { padding-inline-start: calc(var(--default-grid-baseline, 4px) * 7); white-space: normal; overflow: visible; }
.report-detail-header :deep(.app-page-header__subtitle) { padding-inline-start: calc(var(--default-grid-baseline, 4px) * 7); }
@media (max-width: 768px) {
	/* Keep navigation on the first row and give both context lines the full width. */
	.report-detail-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; row-gap: var(--default-grid-baseline, 4px); padding-block-end: calc(var(--default-grid-baseline, 4px) * 2); }
	.report-detail-header :deep(.app-page-header__content) { display: contents; }
	.report-detail-header :deep(.app-page-header__title) { grid-area: 1 / 1; padding-inline-start: calc(var(--default-grid-baseline, 4px) * 8.5); padding-block: 0; }
	.report-detail-header :deep(.app-page-header__title > *) { flex: 1 1 auto; }
	.report-detail-header :deep(.app-page-header__actions) { grid-area: 1 / 2; }
	.report-detail-header :deep(.app-page-header__subtitle) { grid-area: 2 / 1 / 3 / -1; display: flex; flex-direction: column; gap: var(--default-grid-baseline, 4px); margin: 0; padding-inline: var(--default-grid-baseline, 4px); white-space: normal; overflow-wrap: anywhere; }
	.report-detail-context-separator { display: none; }
	.report-detail-title { align-items: center; }
	.report-detail-title > span { padding-block: 0; line-height: 1.5; }
	.report-detail-back.button-vue { flex: 0 0 var(--cobudget-mobile-touch-size, 44px); min-width: var(--cobudget-mobile-touch-size, 44px); width: var(--cobudget-mobile-touch-size, 44px); min-height: var(--cobudget-mobile-touch-size, 44px); height: var(--cobudget-mobile-touch-size, 44px); margin: 0; padding: 0; }
}
</style>
