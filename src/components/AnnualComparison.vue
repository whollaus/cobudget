<template>
	<div v-if="value" class="annual-comparison" :class="tone" :title="tooltipText">
		<span v-if="value.direction" class="annual-comparison-icon" aria-hidden="true">
			<TrendingUpIcon v-if="value.direction === 'up'" :size="20" />
			<TrendingDownIcon v-else :size="20" />
		</span>
		<span class="annual-comparison-value"><template v-if="value.percent !== null">{{ percentage + ' ' }}</template><span>{{ value.percent !== null ? `(${difference})` : difference }}</span></span>
	</div>
</template>

<script>
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import TrendingDownIcon from 'vue-material-design-icons/TrendingDown.vue'
import { formatCalendarLabel } from '../utils/formatDate'
import { getAppLocale } from '../utils/formatMoney'

export default {
	name: 'AnnualComparison',
	components: { TrendingUpIcon, TrendingDownIcon },
	props: { value: { type: Object, default: null }, type: { type: String, default: 'expense' } },
	computed: {
		difference() { return `${this.value.delta > 0 ? '+' : this.value.delta < 0 ? '−' : ''}${this.$formatMoneyFromCents(Math.abs(this.value.delta))}` },
		percentage() {
			const percent = this.value.percent
			return `${percent > 0 ? '+' : percent < 0 ? '−' : ''}${new Intl.NumberFormat(getAppLocale(), { maximumFractionDigits: 2 }).format(Math.abs(percent))} %`
		},
		tone() {
			if (!this.value.direction) return ''
			return (this.value.delta > 0) === (this.type !== 'expense') ? 'favorable' : 'unfavorable'
		},
		tooltipText() {
			const value = this.value
			return [
				`${this.period(value.currentFrom, value.currentTo)}: ${this.$formatMoneyFromCents(value.current)}`,
				`${this.period(value.previousFrom, value.previousTo)}: ${this.$formatMoneyFromCents(value.previous)}`,
				`${this.$texts.annual.difference()}: ${this.difference}${value.percent !== null ? ` · ${this.percentage}` : ''}`,
				...(value.percent === null ? [this.$texts.annual.zeroBaseline()] : []),
			].join('\n')
		},
	},
	methods: {
		period(from, to) {
			const date = value => new Date(`${value}T12:00:00`)
			return `${formatCalendarLabel(date(from), { day: 'numeric' })}–${formatCalendarLabel(date(to), { day: 'numeric', month: 'short', year: 'numeric' })}`
		},
	},
}
</script>

<style scoped>
.annual-comparison { display: flex; align-items: center; gap: 8px; font-weight: 400; line-height: 1.4; color: var(--cobudget-text-muted); }
.annual-comparison-icon { display: inline-flex; width: 32px; height: 32px; flex: 0 0 auto; align-items: center; justify-content: center; border-radius: 50%; background: var(--cobudget-surface-muted, var(--color-background-hover)); }
.annual-comparison-value { margin-inline-start: auto; white-space: normal; }
.annual-comparison-value > span { white-space: nowrap; }
.favorable { color: var(--cobudget-success); }
.unfavorable { color: var(--cobudget-error); }
</style>
