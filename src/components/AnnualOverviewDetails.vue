<template>
	<NcModal :show="true" size="normal" :name="detail.label" :close-on-click-outside="true" @close="$emit('close')">
		<div class="annual-details">
			<table class="annual-details-table annual-details-basics">
				<tbody>
					<tr><th scope="row">{{ $texts.annual.year() }}</th><td>{{ selection.year }}</td></tr>
					<tr v-if="selection.period === 'quarter' && !metrics.yearly"><th scope="row">{{ $texts.annual.quarterLabel() }}</th><td>{{ $texts.annual.quarter(detail.index + 1) }}</td></tr>
					<tr v-if="selection.period === 'week' && !metrics.yearly"><th scope="row">{{ $texts.annual.weekLabel() }}</th><td>{{ $texts.annual.week(detail.index + 1) }}</td></tr>
					<tr v-if="selection.period === 'month' || dailyMonth || (selection.period !== 'quarter' && selection.period !== 'week' && selection.period !== 'day' && !metrics.yearly)"><th scope="row">{{ $texts.budgetGoals.month() }}</th><td>{{ monthLabel }}</td></tr>
					<tr v-if="(selection.period === 'month' || selection.period === 'day') && !metrics.yearly"><th scope="row">{{ $texts.annual.day() }}</th><td>{{ dayLabel }}</td></tr>
					<tr><th scope="row">{{ $texts.analytics.area() }}</th><td>{{ scopeLabel }}</td></tr>
					<tr v-if="!detail.total"><th scope="row">{{ dimensionLabel }}</th><td>{{ detail.label }}</td></tr>
					<tr><th scope="row">{{ $texts.analytics.bookingsColumn() }}</th><td>{{ paymentsLabel }}</td></tr>
				</tbody>
			</table>
			<table class="annual-details-table">
				<tbody>
					<tr class="annual-details-total"><th scope="row">{{ $texts.analytics.total() }}</th><td :class="{ 'negative-balance': metrics.amount < 0 }">{{ $formatMoneyFromCents(metrics.amount) }}</td></tr>
					<template v-if="metrics.balance">
						<tr><th scope="row">{{ $texts.annual.income() }}</th><td>{{ $formatMoneyFromCents(metrics.income) }}</td></tr>
						<tr><th scope="row">{{ $texts.annual.expenses() }}</th><td>{{ $formatMoneyFromCents(metrics.expense) }}</td></tr>
					</template>
					<tr v-if="metrics.yearly && selection.period === 'quarter'"><th scope="row">{{ $texts.annual.averagePerQuarter() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerQuarter) }}</td></tr>
					<tr v-if="metrics.yearly && selection.period === 'week'"><th scope="row">{{ $texts.annual.averagePerWeek() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerWeek) }}</td></tr>
					<tr v-if="metrics.yearly && selection.period !== 'month' && selection.period !== 'quarter' && selection.period !== 'week' && selection.period !== 'day'"><th scope="row">{{ $texts.annual.averagePerMonth() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerMonth) }}</td></tr>
					<tr v-if="metrics.yearly && (selection.period === 'month' || selection.period === 'day')"><th scope="row">{{ $texts.annual.averagePerDay() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerDay) }}</td></tr>
					<tr v-if="metrics.share !== null"><th scope="row">{{ shareLabel }}</th><td>{{ percentage }}</td></tr>
					<tr><th scope="row">{{ $texts.annual.paymentCount() }}</th><td>{{ metrics.count }}</td></tr>
					<tr v-if="!metrics.yearly && metrics.averagePerPayment !== null"><th scope="row">{{ $texts.annual.averagePerPayment() }}</th><td>{{ $formatMoneyFromCents(metrics.averagePerPayment) }}</td></tr>
					<tr v-for="comparison in comparisons" :key="comparison.mode" class="annual-details-comparison">
						<th scope="row">{{ comparisonLabel(comparison.mode) }}<span class="annual-comparison-period">{{ comparisonPeriod(comparison.value) }}</span></th>
						<td><AnnualComparison :value="comparison.value" :type="detail.row.type" /></td>
					</tr>
					<tr v-if="comparisonLoading"><td colspan="2" class="annual-comparison-status" role="status">{{ $texts.annual.comparisonsLoading() }}</td></tr>
					<tr v-else-if="comparisonError"><td colspan="2" class="annual-comparison-status" role="alert">{{ $texts.annual.comparisonsError() }} <NcButton variant="tertiary" @click="loadComparisons">{{ $texts.annual.retry() }}</NcButton></td></tr>
				</tbody>
			</table>
			<div class="annual-details-actions">
				<NcButton class="annual-details-payments" variant="tertiary" @click="showPayments">{{ $texts.analytics.showPayments() }}</NcButton>
				<NcButton variant="secondary" @click="$emit('close')">{{ $texts.common.close() }}</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import AnnualComparison from './AnnualComparison.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualRequestParams, isoWeekNumber, isoWeekYear } from '../services/annualOverview'
import { annualCellDetails, annualDetailComparison } from '../services/annualOverviewDetails'
import { formatCalendarLabel } from '../utils/formatDate'
import { getAppLocale } from '../utils/formatMoney'

export default {
	name: 'AnnualOverviewDetails',
	components: { NcModal, NcButton, AnnualComparison },
	props: {
		detail: { type: Object, required: true },
		report: { type: Object, required: true },
		selection: { type: Object, required: true },
		scopeLabel: { type: String, required: true },
	},
	emits: ['close'],
	data: () => ({ comparisonReports: {}, comparisonLoading: false, comparisonError: false, comparisonRequest: 0 }),
	watch: {
		detail: { handler: 'loadComparisons', deep: true },
		selection: { handler: 'loadComparisons', deep: true },
	},
	mounted() { this.loadComparisons() },
	beforeUnmount() { this.comparisonRequest++ },
	methods: {
		comparisonPeriod(value) {
			const formatDay = date => formatCalendarLabel(new Date(`${date}T12:00:00`), { day: 'numeric', month: 'short', year: 'numeric' })
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
		comparisons() {
			return ['month', 'year'].map(mode => ({ mode, value: annualDetailComparison(this.comparisonReports[mode], this.detail) })).filter(comparison => comparison.value)
		},
		dimensionLabel() {
			if (this.selection.grouping === 'paymentPartners') return this.$texts.analytics.paymentPartner()
			if (this.selection.grouping === 'labelsAndTags') return this.detail.row?.kind === 'hashtag' ? this.$texts.analytics.hashtag() : this.$texts.annual.label()
			return this.$texts.analytics.category()
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
.annual-details-table { width: 100%; table-layout: fixed; margin: 0; border-collapse: separate; border-spacing: 0; border: 1px solid var(--cobudget-border, #ddd); border-radius: var(--border-radius-large, 8px); font-size: var(--cobudget-font-sm); }
.annual-details-table th, .annual-details-table td { height: auto; padding: 4px 10px; border-bottom: 1px solid var(--cobudget-border, #ddd); font-weight: 400; line-height: 1.5; }
.annual-details-table th { width: 48%; text-align: start; white-space: normal; overflow-wrap: anywhere; color: var(--cobudget-text-muted, #888); letter-spacing: 0.5px; background: var(--cobudget-surface-muted, #f9f9f9); }
.annual-details-table td { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
.annual-details-table tr:last-child > * { border-bottom: 0; }
.annual-details-table tr:hover { background: transparent; }
.annual-details-basics { margin-bottom: 20px; }
.annual-details-basics td { white-space: normal; }
.annual-details-table tr:first-child th { border-start-start-radius: calc(var(--border-radius-large, 8px) - 1px); }
.annual-details-table tr:first-child td { border-start-end-radius: calc(var(--border-radius-large, 8px) - 1px); }
.annual-details-table tr:last-child th { border-end-start-radius: calc(var(--border-radius-large, 8px) - 1px); }
.annual-details-table tr:last-child td { border-end-end-radius: calc(var(--border-radius-large, 8px) - 1px); }
.annual-details-table .annual-details-total > * { color: var(--cobudget-text); font-weight: 600; }
.annual-details-table .annual-details-total td.negative-balance { color: var(--cobudget-error); }
.annual-comparison-period { display: block; font-size: var(--cobudget-font-xs, 12px); letter-spacing: normal; }
.annual-details-table .annual-comparison-status { white-space: normal; text-align: start; color: var(--cobudget-text-muted); }
.annual-details-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.annual-details-payments { color: var(--cobudget-text-muted); background-color: var(--cobudget-surface-muted) !important; }
.annual-details-payments:hover { background-color: var(--cobudget-surface-strong) !important; }
.annual-details-payments :deep(.button-vue__text) { font-weight: 400; }
@media (max-width: 480px) { .annual-details { padding: 52px 20px 20px; } }
</style>
