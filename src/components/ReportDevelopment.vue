<template>
	<section class="report-development" :class="{ 'is-collapsed': !open }" :data-report-section="sectionKey">
		<header class="report-development-header">
			<div class="report-development-heading">
				<h3 class="report-development-tabs" role="tablist" :aria-label="$texts.annual.chartView()" @keydown="onTabKey">
					<NcButton ref="developmentTab" variant="tertiary" alignment="start" role="tab" class="report-development-tab" :class="{ 'is-selected': activeView === 'development' }"
						:aria-selected="activeView === 'development'" :tabindex="activeView === 'development' ? 0 : -1" :aria-controls="`${bodyId}-development`" @click="choose('development')">{{ $texts.analytics.development() }}</NcButton>
					<NcButton v-if="eligible" ref="forecastTab" variant="tertiary" alignment="start" role="tab" class="report-development-tab" :class="{ 'is-selected': activeView === 'forecast' }"
						:aria-selected="activeView === 'forecast'" :tabindex="activeView === 'forecast' ? 0 : -1" :aria-controls="`${bodyId}-forecast`" @click="choose('forecast')">{{ $texts.analytics.forecast() }}</NcButton>
				</h3>
				<ReportSectionMenu class="report-development-menu" :open="open" :title="$texts.analytics.development()" :controls="bodyId" :export-data="exportData" @toggle="toggleSection" />
			</div>
			<div v-show="open" class="report-development-controls">
				<slot v-if="activeView === 'development'" name="controls" />
				<label class="report-development-mode">
					<span aria-hidden="true">{{ effectiveMode === 'cumulative' ? $texts.annual.chartCumulative() : $texts.annual.chartPerPeriod() }}<ChevronDownIcon :size="18" /></span>
					<select :value="effectiveMode" :aria-label="$texts.annual.chartDisplay()" @change="setMode($event.target.value)"><option value="period">{{ $texts.annual.chartPerPeriod() }}</option><option value="cumulative">{{ $texts.annual.chartCumulative() }}</option></select>
				</label>
			</div>
		</header>
		<div v-show="open" :id="bodyId">
			<div v-show="activeView === 'development'" :id="`${bodyId}-development`" role="tabpanel" :aria-label="$texts.analytics.development()">
				<ReportSummary v-if="summaryRows.length" :rows="summaryRows" :period-label="periodLabel" :interactive="!detail" @details="$emit('details', $event)" />
				<AnnualOverviewChart ref="developmentChart" embedded :report="chartReport || report" :labels="labels" :label-titles="labelTitles" :axis-labels="axisLabels" :income-enabled="$enableIncomes !== false"
					:mode="mode" :hidden-series="hiddenSeries" :series-ids="seriesIds" :comparison-series="comparisonSeries" :format-cents="$formatMoneyFromCents" :section-key="sectionKey"
					@update:hidden-series="$emit('update:hiddenSeries', $event)" />
			</div>
			<div v-show="activeView === 'forecast'" :id="`${bodyId}-forecast`" role="tabpanel" :aria-label="$texts.analytics.forecast()">
				<ReportForecast v-if="eligible" ref="forecast" :report="report" :selection="selection" :detail="detail" :chart-period="chartPeriod">
					<template #default="{ result }">
						<ReportSummary :rows="forecastRows(result)" :period-label="periodLabel" :interactive="false" />
						<AnnualOverviewChart ref="forecastChart" embedded :report="report" :labels="forecastLabels(result).labels" :label-titles="forecastLabels(result).titles" :axis-labels="forecastLabels(result).labels"
							:income-enabled="$enableIncomes !== false" :custom-series="forecastSeries(result)" :mode="forecastMode" :hidden-series="forecastHidden" :format-cents="$formatMoneyFromCents"
							@update:hidden-series="forecastHidden = $event" />
					</template>
				</ReportForecast>
			</div>
		</div>
	</section>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ReportSectionMenu from './ReportSectionMenu.vue'
import AnnualOverviewChart from './AnnualOverviewChart.vue'
import ReportSummary from './ReportSummary.vue'
import ReportForecast from './ReportForecast.vue'
import { readAnnualSectionOpen, rememberAnnualSection, readAnnualChartView, rememberAnnualChartView } from '../services/annualOverview'
import { reportForecastSeries } from '../services/reportForecast'
import { reportChartLabels } from '../services/reportDetails'
import { formatCalendarLabel } from '../utils/formatDate'
import { reportExportMoney, reportExportDisplayedValue, reportExportNotReady } from '../services/reportExport'

let developmentSequence = 0

export default {
	name: 'ReportDevelopment',
	components: { NcButton, ChevronDownIcon, ReportSectionMenu, AnnualOverviewChart, ReportSummary, ReportForecast },
	props: {
		report: { type: Object, required: true }, selection: { type: Object, required: true }, detail: { type: Object, default: null },
		chartReport: { type: Object, default: null }, chartPeriod: { type: String, default: 'year' },
		labels: { type: Array, required: true }, labelTitles: { type: Array, default: () => [] }, axisLabels: { type: Array, default: () => [] },
		summaryRows: { type: Array, default: () => [] }, periodLabel: { type: String, default: '' }, sectionKey: { type: String, default: 'chart' },
		mode: { type: String, default: 'cumulative' }, hiddenSeries: { type: Array, default: () => [] }, seriesIds: { type: Array, default: null }, comparisonSeries: { type: Array, default: () => [] },
	},
	emits: ['details', 'update:mode', 'update:hiddenSeries'],
	data() { return { open: readAnnualSectionOpen(this.sectionKey), view: readAnnualChartView(this.sectionKey), forecastMode: 'cumulative', forecastHidden: [], bodyId: `report-development-${++developmentSequence}` } },
	computed: {
		period() { return this.report.periods[this.detail ? this.detail.index : this.report.periods.length - 1] },
		eligible() { return Boolean(this.period && this.period.dateTo >= Date.now() / 1000) },
		activeView() { return this.eligible ? this.view : 'development' },
		effectiveMode() { return this.activeView === 'forecast' ? this.forecastMode : this.mode },
	},
	watch: { activeView() { this.measurePlot() } },
	methods: {
		exportData({ display = false } = {}) {
			const forecast = this.activeView === 'forecast', result = this.$refs.forecast?.result
			if (forecast && (!result || this.$refs.forecast.loading || this.$refs.forecast.error)) throw reportExportNotReady()
			const summaries = forecast ? this.forecastRows(result) : this.summaryRows
			return [
				...(summaries.length ? [{ title: forecast ? this.$texts.analytics.forecast() : this.$texts.analytics.development(), columns: [this.$texts.common.name(), this.$texts.analytics.amount()], rows: summaries.map(row => [row.label, display ? reportExportDisplayedValue(this.$formatMoneyFromCents(row.total)) : reportExportMoney(row.total)]) }] : []),
				...this.$refs[forecast ? 'forecastChart' : 'developmentChart'].exportData({ display }),
				...(forecast ? this.$refs.forecast.exportData({ display }) : []),
			]
		},
		choose(view) { this.view = view; this.open = true; rememberAnnualChartView(this.sectionKey, view); rememberAnnualSection(this.sectionKey, true); this.measurePlot() },
		toggleSection() { this.open = !this.open; rememberAnnualSection(this.sectionKey, this.open); if (this.open) this.measurePlot() },
		measurePlot() { this.$nextTick(() => { this.$refs.developmentChart?.measurePlot(); this.$refs.forecastChart?.measurePlot() }) },
		setMode(value) { if (this.activeView === 'forecast') this.forecastMode = value; else this.$emit('update:mode', value) },
		onTabKey(event) {
			if (!this.eligible || event.altKey || event.ctrlKey || event.metaKey || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
			event.preventDefault()
			const view = event.key === 'Home' ? 'development' : event.key === 'End' ? 'forecast' : this.activeView === 'development' ? 'forecast' : 'development'
			this.choose(view); this.$nextTick(() => this.$refs[`${view}Tab`]?.$el?.focus({ preventScroll: true }))
		},
		forecastSeries(result) { return reportForecastSeries(result, { incomeEnabled: this.$enableIncomes !== false, cumulative: this.forecastMode === 'cumulative', seriesIds: this.seriesIds }) },
		forecastLabels(result) {
			const periods = result.chart?.periods || []
			return periods.length <= this.labels.length ? { labels: this.labels.slice(0, periods.length), titles: this.labelTitles.slice(0, periods.length) } : reportChartLabels(periods, { period: this.chartPeriod }, formatCalendarLabel)
		},
		forecastRows(result) {
			const income = this.$enableIncomes !== false && (!this.seriesIds || this.seriesIds.includes('income'))
			const expense = !this.seriesIds || this.seriesIds.includes('expense')
			const balance = this.$enableIncomes !== false && (!this.seriesIds || this.seriesIds.includes('balance'))
			return [
				...(income ? [{ key: 'income', type: 'income', label: this.$texts.annual.forecastIncome(), total: result.forecast.incomeCents }] : []),
				...(expense ? [{ key: 'expense', type: 'expense', label: result.expenseEstimateAvailable ? this.$texts.annual.forecastExpenses() : this.$texts.annual.forecastKnownExpenses(), total: result.forecast.expenseCents }] : []),
				...(balance ? [{ key: 'balance', type: 'all', label: result.estimateAvailable ? this.$texts.annual.forecastBalance() : this.$texts.annual.forecastKnownBalance(), total: result.forecast.balanceCents }] : []),
			]
		},
	},
}
</script>

<style scoped>
.report-development { min-width: 0; margin: 0 0 var(--cobudget-report-section-gap, 24px); }
.report-development.is-collapsed { margin-bottom: 0; }
.report-development-header { display: flex; flex-wrap: wrap; align-items: center; gap: var(--default-grid-baseline, 4px); min-height: var(--cobudget-report-header-height, 64px); }
.report-development-heading { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); min-width: 0; }
.report-development-tabs { display: inline-flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 3); margin: 0; font-size: var(--cobudget-font-lg, 18px); font-weight: 600; }
.report-development .report-development-tab { min-height: 0; height: auto; margin: 0; padding: 2px 4px; border: 0; font: inherit; color: var(--cobudget-text-muted); --font-weight-element: 400; }
.report-development .report-development-tab.is-selected { color: var(--cobudget-text); --font-weight-element: 600; }
/* Text-only NcButton adds inline padding through a more specific :has() rule. */
.report-development-tab :deep(.button-vue__text) { padding: 0; padding-inline: 0 !important; white-space: normal; }
.report-development-controls { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: var(--default-grid-baseline, 4px); min-width: 0; max-width: 100%; margin-inline-start: auto; }
.report-development-mode { position: relative; display: inline-flex; border-radius: var(--border-radius-element, 8px); color: var(--cobudget-text-muted); }
.report-development-mode > span { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); min-height: 36px; padding-inline: calc(var(--default-grid-baseline, 4px) * 2); }
.report-development-mode select { position: absolute; inset: 0; width: 100%; height: 100%; min-height: 0; margin: 0; opacity: 0; cursor: pointer; }
.report-development-mode:hover { background: var(--cobudget-surface-muted); }
.report-development-mode:focus-within { outline: var(--cobudget-focus-outline); outline-offset: 2px; background: var(--cobudget-surface-muted); }
@media (max-width: 768px) {
	.report-development .report-development-tab { min-height: var(--cobudget-mobile-touch-size); }
	.report-development-controls { gap: calc(var(--default-grid-baseline, 4px) * 2); }
	.report-development-mode > span { min-height: 44px; padding-inline: var(--default-grid-baseline, 4px); }
}
</style>
