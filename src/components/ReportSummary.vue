<template>
	<section class="report-summary" :aria-label="$texts.analytics.keyFigures()">
		<template v-for="row in rows" :key="row.key">
		<NcButton v-if="interactive" variant="tertiary" alignment="start" class="report-summary-item"
			:aria-label="$texts.annual.showDetails(row.label, periodLabel, $formatMoneyFromCents(row.total))" @click="$emit('details', row)">
			<span class="report-summary-label">{{ row.label }}</span>
			<strong class="report-summary-amount" :class="{ 'is-positive': row.type === 'income' || (row.type === 'all' && row.total > 0), 'is-negative': row.type === 'expense' || (row.type === 'all' && row.total < 0) }">{{ $formatMoneyFromCents(row.total) }}</strong>
		</NcButton>
		<div v-else class="report-summary-item report-summary-item--static">
			<span class="report-summary-label">{{ row.label }}</span>
			<strong class="report-summary-amount" :class="{ 'is-positive': row.type === 'income' || (row.type === 'all' && row.total > 0), 'is-negative': row.type === 'expense' || (row.type === 'all' && row.total < 0) }">{{ $formatMoneyFromCents(row.total) }}</strong>
		</div>
		</template>
	</section>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'ReportSummary',
	components: { NcButton },
	props: {
		rows: { type: Array, required: true },
		periodLabel: { type: String, required: true },
		interactive: { type: Boolean, default: true },
	},
	emits: ['details'],
}
</script>

<style scoped>
.report-summary { display: flex; flex-wrap: wrap; gap: calc(var(--default-grid-baseline, 4px) * 2); margin-block: calc(var(--default-grid-baseline, 4px) * 2) calc(var(--default-grid-baseline, 4px) * 3); }
.report-summary .report-summary-item { box-sizing: border-box; flex: 1 1 0; width: auto; min-width: max-content; min-height: var(--cobudget-mobile-touch-size, 44px); margin: 0; padding: calc(var(--default-grid-baseline, 4px) * 2); border: 0; color: var(--cobudget-text, var(--color-main-text)); }
.report-summary-item--static { display: flex; flex-direction: column; align-items: flex-start; gap: var(--default-grid-baseline, 4px); }
.report-summary .report-summary-item :deep(.button-vue__wrapper > .button-vue__text) { display: flex; flex-direction: column; align-items: flex-start; gap: var(--default-grid-baseline, 4px); width: 100%; padding: 0; overflow: visible; white-space: nowrap; text-align: start; }
.report-summary-label { color: var(--cobudget-text-muted, var(--color-text-maxcontrast)); font-size: var(--default-font-size, 15px); font-weight: 400; line-height: 1.3; }
.report-summary-amount { font-size: var(--cobudget-font-metric); font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; }
.report-summary-amount.is-positive { color: var(--cobudget-success, var(--color-success-text)); }
.report-summary-amount.is-negative { color: var(--cobudget-error, var(--color-error-text)); }
@media (max-width: 768px) {
	.report-summary .report-summary-item { padding-inline: var(--default-grid-baseline, 4px); }
	.report-summary-amount { font-size: var(--cobudget-font-metric-compact); }
}
</style>
