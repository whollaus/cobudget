<template>
	<section class="summary-grid" :aria-label="ariaLabel">
		<article v-for="card in cards" :key="card.key" class="summary-card">
			<span>{{ card.label }}</span>
			<strong :class="card.className">{{ card.value }}</strong>
			<TableTooltip v-if="card.tooltip" :text="card.tooltip">
				<small class="summary-detail-tooltip">{{ card.detail }}</small>
			</TableTooltip>
			<small v-else>{{ card.detail }}</small>
		</article>
	</section>
</template>

<script>
import TableTooltip from '../TableTooltip.vue'

export default {
	name: 'AnalyticsSummaryGrid',
	components: {
		TableTooltip
	},
	props: {
		cards: {
			type: Array,
			required: true
		},
		ariaLabel: {
			type: String,
			required: true
		}
	}
}
</script>

<style scoped>
.summary-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: 12px;
}

.summary-card {
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 16px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.summary-card span {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
}

.summary-card strong {
	font-size: var(--cobudget-font-section);
	line-height: 1.2;
}

.summary-card small {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.summary-detail-tooltip {
	cursor: help;
	text-decoration: underline dotted;
	text-underline-offset: 3px;
}
</style>
