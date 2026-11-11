<template>
		<div class="annual-details" :class="{ 'annual-details--embedded': embedded }">
			<table v-if="!embedded" class="annual-details-table annual-details-basics">
				<tbody>
					<tr v-if="report.range"><th scope="row">{{ $texts.annual.selectedPeriod() }}</th><td>{{ rangeLabel }}</td></tr>
					<tr v-if="!report.range"><th scope="row">{{ $texts.annual.year() }}</th><td>{{ selection.year }}</td></tr>
					<tr v-if="!report.range && (selection.period === 'quarter' && !metrics.yearly)"><th scope="row">{{ $texts.annual.quarterLabel() }}</th><td>{{ $texts.annual.quarter(detail.index + 1) }}</td></tr>
					<tr v-if="!report.range && (selection.period === 'week' && !metrics.yearly)"><th scope="row">{{ $texts.annual.weekLabel() }}</th><td>{{ $texts.annual.week(detail.index + 1) }}</td></tr>
					<tr v-if="!report.range && (selection.period === 'month' || dailyMonth || (selection.period !== 'quarter' && selection.period !== 'week' && selection.period !== 'day' && !metrics.yearly))"><th scope="row">{{ $texts.budgetGoals.month() }}</th><td>{{ monthLabel }}</td></tr>
					<tr v-if="!report.range && ((selection.period === 'month' || selection.period === 'day') && !metrics.yearly)"><th scope="row">{{ $texts.annual.day() }}</th><td>{{ dayLabel }}</td></tr>
					<tr><th scope="row">{{ $texts.analytics.area() }}</th><td>{{ scopeLabel }}</td></tr>
					<tr v-if="!detail.total"><th scope="row">{{ dimensionLabel }}</th><td>{{ detail.label }}</td></tr>
					<tr><th scope="row">{{ $texts.analytics.bookingsColumn() }}</th><td>{{ paymentsLabel }}</td></tr>
				</tbody>
			</table>
			<ReportDetailSection class="report-detail-metrics" section-key="detail-metrics" :title="$texts.analytics.keyFigures()">
				<table class="annual-details-table">
					<tbody>
						<tr class="annual-details-total"><th scope="row">{{ totalLabel }}</th><td :class="{ 'negative-balance': metrics.amount < 0 }">{{ $formatMoneyFromCents(metrics.amount) }}</td></tr>
						<template v-if="metrics.balance">
							<tr><th scope="row">{{ $texts.annual.income() }}</th><td>{{ $formatMoneyFromCents(metrics.income) }}</td></tr>
							<tr><th scope="row">{{ $texts.annual.expenses() }}</th><td>{{ $formatMoneyFromCents(metrics.expense) }}</td></tr>
						</template>
						<tr v-if="annualTotal" class="annual-details-year-total"><th scope="row">{{ $texts.annual.yearTotal(annualTotal.year) }}</th><td :class="{ 'negative-balance': annualTotal.amount < 0 }">{{ $formatMoneyFromCents(annualTotal.amount) }}</td></tr>
						<tr v-if="metrics.share !== null"><th scope="row">{{ shareLabel }}</th><td>{{ percentage }}</td></tr>
						<tr><th scope="row">{{ $texts.annual.paymentCount() }}</th><td>{{ metrics.count }}</td></tr>
					</tbody>
				</table>
			</ReportDetailSection>
			<ReportDetailSection v-if="hasAverages" class="report-detail-averages" section-key="detail-averages" :title="$texts.annual.averages()">
				<table class="annual-details-table">
					<tbody>
						<tr v-for="average in averages" :key="average.id" class="annual-details-average" :data-average-id="average.id"><th scope="row">{{ comparisonLabel(average.id) }}</th><td :class="{ 'negative-balance': average.fullAverage < 0 }">{{ $formatMoneyFromCents(average.fullAverage) }}</td></tr>
						<template v-if="comparisonData === null">
							<tr v-if="metrics.yearly && selection.period === 'quarter'"><th scope="row">{{ $texts.annual.averagePerQuarter() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerQuarter) }}</td></tr>
							<tr v-if="metrics.yearly && selection.period === 'week'"><th scope="row">{{ $texts.annual.averagePerWeek() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerWeek) }}</td></tr>
							<tr v-if="metrics.yearly && selection.period !== 'month' && selection.period !== 'quarter' && selection.period !== 'week' && selection.period !== 'day'"><th scope="row">{{ $texts.annual.averagePerMonth() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerMonth) }}</td></tr>
							<tr v-if="metrics.yearly && (selection.period === 'month' || selection.period === 'day')"><th scope="row">{{ $texts.annual.averagePerDay() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerDay) }}</td></tr>
						</template>
						<tr v-if="!metrics.yearly && metrics.averagePerPayment !== null"><th scope="row">{{ $texts.annual.averagePerPayment() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerPayment) }}</td></tr>
					</tbody>
				</table>
			</ReportDetailSection>
			<ReportDetailSection v-if="comparisons.length || comparisonLoading || comparisonsLoading || comparisonError || comparisonsError" class="report-detail-comparisons" section-key="detail-comparisons" :title="$texts.annual.comparisons()">
				<table class="annual-details-table">
					<tbody>
						<tr v-for="comparison in comparisons" :key="comparison.mode" class="annual-details-comparison">
							<th scope="row">{{ comparisonLabel(comparison.mode) }}<span class="annual-comparison-period">{{ comparisonPeriod(comparison.value) }}</span></th>
							<td class="annual-details-reference">
								<span v-if="comparison.value.kind" class="annual-reference-amount">{{ $formatMoneyFromCents(comparison.value.previous) }}</span><AnnualComparison :value="comparison.value" :type="detail.row.type" />
							</td>
						</tr>
						<tr v-if="comparisonLoading || comparisonsLoading"><td colspan="2" class="annual-comparison-status" role="status">{{ $texts.annual.comparisonsLoading() }}</td></tr>
						<tr v-else-if="comparisonError || comparisonsError"><td colspan="2" class="annual-comparison-status" role="alert">{{ $texts.annual.comparisonsError() }} <NcButton variant="tertiary" @click="comparisonData === null ? loadComparisons() : $emit('retry')">{{ $texts.annual.retry() }}</NcButton></td></tr>
					</tbody>
				</table>
			</ReportDetailSection>
			<div v-if="!embedded" class="annual-details-actions">
				<NcButton class="annual-details-payments" variant="tertiary" @click="showPayments">{{ $texts.analytics.showPayments() }}</NcButton>
				<NcButton variant="tertiary" @click="$emit('close')">{{ $texts.common.close() }}</NcButton>
			</div>
		</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import AnnualComparison from './AnnualComparison.vue'
import ReportDetailSection from './ReportDetailSection.vue'
import { reportComparisonLabel, reportComparisonRange } from '../services/reportComparisons'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualRequestParams, isoWeekNumber, isoWeekYear, reportPeriodLabel } from '../services/annualOverview'
import { annualCellDetails, annualDetailComparison } from '../services/annualOverviewDetails'
import { formatCalendarLabel } from '../utils/formatDate'
import { getAppLocale } from '../utils/formatMoney'

export default {
	name: 'AnnualOverviewDetails',
	components: { NcButton, AnnualComparison, ReportDetailSection },
	props: {
		detail: { type: Object, required: true },
		report: { type: Object, required: true },
		selection: { type: Object, required: true },
		scopeLabel: { type: String, required: true },
		embedded: { type: Boolean, default: false },
		annualTotal: { type: Object, default: null },
		comparisonData: { type: Array, default: null }, comparisonsLoading: { type: Boolean, default: false }, comparisonsError: { type: Boolean, default: false },
	},
	emits: ['close', 'retry'],
	data: () => ({ comparisonReports: {}, comparisonLoading: false, comparisonError: false, comparisonRequest: 0 }),
	watch: {
		detail: { handler: 'loadComparisons', deep: true },
		selection: { handler: 'loadComparisons', deep: true },
	},
	mounted() { this.loadComparisons() },
	beforeUnmount() { this.comparisonRequest++ },
	methods: {
		comparisonPeriod(value) {
			if (value.kind) return reportComparisonRange(value, formatCalendarLabel)
			const formatDay = date => formatCalendarLabel(new Date(`${date}T12:00:00`), { day: 'numeric', month: 'short', year: 'numeric' })
			if (this.report?.range) return value.previousFrom === value.previousTo ? formatDay(value.previousFrom) : `${formatDay(value.previousFrom)} – ${formatDay(value.previousTo)}`
			if (this?.selection?.period === 'week') {
				const start = new Date(`${value.previousFrom}T12:00:00`)
				const end = new Date(`${value.previousTo}T12:00:00`)
				const sunday = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6, 12)
				if (end.getFullYear() === sunday.getFullYear() && end.getMonth() === sunday.getMonth() && end.getDate() === sunday.getDate()) {
					return `${this.$texts.annual.week(isoWeekNumber(start))} ${isoWeekYear(start)}`
				}
				return `${formatDay(value.previousFrom)} – ${formatDay(value.previousTo)}`
			}
			if (this?.selection?.period === 'quarter') {
				const start = new Date(`${value.previousFrom}T12:00:00`)
				const end = new Date(`${value.previousTo}T12:00:00`)
				const quarter = Math.floor(start.getMonth() / 3) + 1
				const last = new Date(start.getFullYear(), quarter * 3, 0)
				if (end.getFullYear() === last.getFullYear() && end.getMonth() === last.getMonth() && end.getDate() === last.getDate()) {
					return `${this.$texts.annual.quarter(quarter)} ${start.getFullYear()}`
				}
				return `${formatDay(value.previousFrom)} – ${formatDay(value.previousTo)}`
			}
			if (this?.selection?.period === 'month' || this?.selection?.period === 'day' || value.previousFrom === value.previousTo) {
				return formatDay(value.previousFrom)
			}
			// The selected month determines whether the reference period is partial,
			// including on the last day of an ongoing month.
			const selectedStart = new Date(`${value.currentFrom}T12:00:00`)
			const nextMonth = new Date(selectedStart.getFullYear(), selectedStart.getMonth() + 1, 1)
			if (nextMonth.getTime() <= Date.now()) {
				return formatCalendarLabel(new Date(`${value.previousFrom}T12:00:00`), { month: 'long', year: 'numeric' })
			}
			return `${formatDay(value.previousFrom)} – ${formatDay(value.previousTo)}`
		},
		comparisonLabel(mode) {
			const value = this.comparisonData?.find(item => item.id === mode)
			if (value) return reportComparisonLabel(value, this.$texts, formatCalendarLabel)
			const monthly = this.selection.period === 'month'
			const quarterly = this.selection.period === 'quarter'
			const weekly = this.selection.period === 'week'
			const daily = this.selection.period === 'day'
			if (mode === 'month') {
				if (monthly || daily) return this.$texts.annual.changeFromPreviousDay()
				if (weekly) return this.$texts.annual.changeFromPreviousWeek()
				if (quarterly) return this.$texts.annual.changeFromPreviousQuarter()
				return this.$texts.annual.changeFromPreviousMonth()
			}
			if (monthly || daily) return this.$texts.annual.changeFromSameDayLastYear()
			if (weekly) return this.$texts.annual.changeFromSameWeekLastYear()
			if (quarterly) return this.$texts.annual.changeFromSameQuarterLastYear()
			return this.$texts.annual.changeFromPreviousYear()
		},
		async loadComparisons() {
			if (this.comparisonData !== null && this.comparisonData !== undefined) return
			const request = ++this.comparisonRequest
			this.comparisonReports = {}
			this.comparisonError = false
			this.comparisonLoading = false
			const lastIndex = this.report.periods.length - 1
			if (this.detail.index === lastIndex || this.report.periods[this.detail.index].dateFrom > Date.now() / 1000) return
			this.comparisonLoading = true
			try {
				const reports = await Promise.all(['month', 'year'].map(async comparison => {
					const { data } = await axios.get(generateUrl('/apps/cobudget/api/annual-overview'), { params: annualRequestParams(this.selection, { comparison }) })
					return [comparison, data]
				}))
				if (request === this.comparisonRequest) this.comparisonReports = Object.fromEntries(reports)
			} catch {
				if (request === this.comparisonRequest) this.comparisonError = true
			} finally {
				if (request === this.comparisonRequest) this.comparisonLoading = false
			}
		},
		async showPayments() {
			// Use the app router: NcButton's bundled router can have a different injection key.
			const failure = await this.$router.push(this.detail.route)
			if (!failure) this.$emit('close')
		},
	},
	computed: {
		totalLabel() {
			if (this.selection.timeRange === 'calendar-selection' && this.detail.index === this.report.periods.length - 1) return this.$texts.annual.periodTotal(reportPeriodLabel(this.selection, formatCalendarLabel))
			const period = this.report.periods[this.detail.index]
			const from = new Date(period.dateFrom * 1000), to = new Date(period.dateTo * 1000)
			const next = new Date(to.getFullYear(), to.getMonth(), to.getDate() + 1)
			const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
			if (from.getMonth() === 0 && from.getDate() === 1 && sameDay(next, new Date(from.getFullYear() + 1, 0, 1))) return this.$texts.annual.yearTotal(from.getFullYear())
			if (from.getDate() === 1 && sameDay(next, new Date(from.getFullYear(), from.getMonth() + 1, 1))) return this.$texts.annual.periodTotal(formatCalendarLabel(from, { month: 'long', year: 'numeric' }))
			if (from.getDate() === 1 && from.getMonth() % 3 === 0 && sameDay(next, new Date(from.getFullYear(), from.getMonth() + 3, 1))) return this.$texts.annual.periodTotal(`${this.$texts.annual.quarter(Math.floor(from.getMonth() / 3) + 1)} ${from.getFullYear()}`)
			if (from.getDay() === 1 && sameDay(next, new Date(from.getFullYear(), from.getMonth(), from.getDate() + 7))) return this.$texts.annual.weekTotal(isoWeekNumber(from), isoWeekYear(from))
			const format = date => formatCalendarLabel(date, { day: 'numeric', month: 'short', year: 'numeric' })
			return this.$texts.annual.periodTotal(sameDay(from, to) ? format(from) : `${format(from)} – ${format(to)}`)
		},
		averages() { return this.comparisonData?.filter(value => value.available && ['selection-average', 'reference-average'].includes(value.kind)) ?? [] },
		hasAverages() { return this.averages.length > 0 || (this.comparisonData === null && this.metrics.yearly) || (!this.metrics.yearly && this.metrics.averagePerPayment !== null) },
		comparisons() {
			if (this.comparisonData !== null) return this.comparisonData.filter(value => value.available && value.kind === 'period').map(value => ({ mode: value.id, value }))
			return ['month', 'year'].map(mode => ({ mode, value: annualDetailComparison(this.comparisonReports[mode], this.detail) })).filter(comparison => comparison.value)
		},
		dimensionLabel() {
			if (this.selection.grouping === 'paymentPartners') return this.$texts.analytics.paymentPartner()
			if (this.selection.grouping === 'labelsAndTags') return this.detail.row?.kind === 'hashtag' ? this.$texts.analytics.hashtag() : this.$texts.annual.label()
			return this.$texts.analytics.category()
		},
		rangeLabel() {
			const period = this.report.periods[this.detail.index]
			if (!period) return ''
			if (this.selection.timeRange === 'calendar-selection' && this.detail.index === this.report.periods.length - 1) return reportPeriodLabel(this.selection, formatCalendarLabel)
			const format = timestamp => formatCalendarLabel(new Date(timestamp * 1000), { day: 'numeric', month: 'long', year: 'numeric' })
			return `${format(period.dateFrom)} – ${format(period.dateTo)}`
		},
		metrics() { return annualCellDetails(this.detail, this.report, this.selection) },
		dailyMonth() { return this.selection.period === 'day' && this.selection.month >= 1 && this.selection.month <= 12 },
		monthLabel() {
			const monthIndex = this.selection.period === 'month' || this.dailyMonth ? this.selection.month - 1 : this.detail.index
			return formatCalendarLabel(new Date(this.selection.year, monthIndex, 1), { month: 'long' })
		},
		dayLabel() {
			if (this.dailyMonth) return formatCalendarLabel(new Date(this.selection.year, this.selection.month - 1, this.detail.index + 1), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
			if (this.selection.period === 'day') return formatCalendarLabel(new Date(this.selection.year, 0, this.detail.index + 1), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
			return formatCalendarLabel(new Date(this.selection.year, this.selection.month - 1, this.detail.index + 1), { weekday: 'long', day: 'numeric' })
		},
		cutoffLabel() { return formatCalendarLabel(new Date(this.report.cutoff * 1000), { day: '2-digit', month: '2-digit', year: 'numeric' }) },
		typeLabel() { return this.metrics.balance ? this.$texts.filters.incomeAndExpense() : this.detail.row.type === 'income' ? this.$texts.annual.income() : this.$texts.annual.expenses() },
		shareLabel() {
			const monthly = this.selection.period === 'month'
			const quarterly = this.selection.period === 'quarter'
			const weekly = this.selection.period === 'week'
			const daily = this.selection.period === 'day'
			const monthShare = monthly || (daily && this.selection.month >= 1 && this.selection.month <= 12)
			const income = this.detail.row.type === 'income'
			if (this.report?.range && this.metrics.yearly) return income ? this.$texts.annual.periodIncomeShare() : this.$texts.annual.periodExpenseShare()
			if (income) {
				if (this.metrics.yearly) return monthShare ? this.$texts.annual.monthIncomeShare() : this.$texts.annual.yearIncomeShare()
				if (weekly) return this.$texts.annual.weekIncomeShare()
				if (quarterly) return this.$texts.annual.quarterIncomeShare()
				if (daily) return this.$texts.annual.dayIncomeShare()
				return monthly ? this.$texts.annual.dayIncomeShare() : this.$texts.annual.monthIncomeShare()
			}
			if (this.metrics.yearly) return monthShare ? this.$texts.annual.monthExpenseShare() : this.$texts.annual.yearExpenseShare()
			if (weekly) return this.$texts.annual.weekExpenseShare()
			if (quarterly) return this.$texts.annual.quarterExpenseShare()
			if (daily) return this.$texts.annual.dayExpenseShare()
			return monthly ? this.$texts.annual.dayExpenseShare() : this.$texts.annual.monthExpenseShare()
		},
		percentage() { return new Intl.NumberFormat(getAppLocale(), { style: 'percent', maximumFractionDigits: 1 }).format(this.metrics.share) },
		paymentsLabel() {
			if (this.metrics.partialYear) {
				if (this.metrics.balance) return this.$texts.annual.incomeAndExpensesThrough(this.cutoffLabel)
				return this.detail.row.type === 'income' ? this.$texts.annual.incomeThrough(this.cutoffLabel) : this.$texts.annual.expensesThrough(this.cutoffLabel)
			}
			return this.selection.includeFuturePayments ? `${this.typeLabel} · ${this.$texts.annual.includeFuture()}` : this.typeLabel
		},
	},
}
</script>

<style scoped>
.annual-details { padding: 52px 24px 24px; overflow-wrap: anywhere; }
.annual-details.annual-details--embedded { padding: 0; }
.annual-details-table { width: 100%; table-layout: fixed; margin: 0; border-collapse: separate; border-spacing: 0; border: 0; border-radius: 0; font-size: var(--cobudget-font-base); }
.annual-details-table th, .annual-details-table td { box-sizing: border-box; height: var(--cobudget-payment-table-row-height); padding: var(--cobudget-table-payment-padding); border-bottom: 1px solid var(--cobudget-table-separator); vertical-align: middle; font-weight: 400; line-height: 1.5; }
.annual-details-table th { width: 48%; text-align: start; white-space: normal; overflow-wrap: anywhere; color: var(--cobudget-text-muted, #888); letter-spacing: normal; background: var(--cobudget-surface); }
.annual-details-table td { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
.annual-details-table tr:last-child > * { border-bottom: 0; }
.annual-details-table tr:hover { background: transparent; }
.annual-details-basics { margin-bottom: 20px; }
.annual-details-basics td { white-space: normal; }
.annual-details-table .annual-details-total > * { color: var(--cobudget-text); font-weight: 600; }
.annual-details-table .annual-details-total td.negative-balance,
.annual-details-table .annual-details-average td.negative-balance,
.annual-details-table .annual-details-year-total td.negative-balance { color: var(--cobudget-error); }
.annual-reference-amount { display: block; margin-bottom: var(--default-grid-baseline, 4px); color: var(--cobudget-text); }
.annual-details-table .annual-details-reference { white-space: normal; }
.annual-comparison-period { display: block; font-size: var(--cobudget-font-xs, 12px); letter-spacing: normal; }
.annual-details-table .annual-comparison-status { white-space: normal; text-align: start; color: var(--cobudget-text-muted); }
.annual-details-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.annual-details-payments :deep(.button-vue__text) { font-weight: 400; }
@media (max-width: 480px) { .annual-details { padding: 52px 20px 20px; } }
</style>
