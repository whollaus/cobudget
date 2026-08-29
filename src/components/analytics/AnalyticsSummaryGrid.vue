<template>
	<section class="summary-grid" :aria-label="ariaLabel">
		<article v-for="card in cards" :key="card.key" class="summary-card">
			<div class="summary-card__header">
				<div class="summary-card__title">
					<span class="summary-card__icon" :class="`summary-card__icon--${card.icon}`" aria-hidden="true">
						<TrendingUpIcon v-if="card.icon === 'income'" :size="20" />
						<TrendingDownIcon v-else-if="card.icon === 'expense'" :size="20" />
						<WalletIcon v-else :size="20" />
					</span>
					<span class="summary-card__label">{{ card.label }}</span>
				</div>
				<strong class="summary-card__value" :class="card.className">{{ card.value }}</strong>
			</div>

			<div class="summary-card__details">
				<div v-for="detail in card.details" :key="detail.key" class="summary-card__detail">
					<TableTooltip v-if="detail.tooltip" :text="detail.tooltip">
						<span class="summary-card__detail-label summary-detail-tooltip">{{ detail.label }}</span>
					</TableTooltip>
					<span v-else class="summary-card__detail-label">{{ detail.label }}</span>
					<strong :class="detail.className">{{ detail.value }}</strong>
				</div>
			</div>
		</article>
	</section>
</template>

<script>
import TrendingDownIcon from 'vue-material-design-icons/TrendingDown.vue'
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import WalletIcon from 'vue-material-design-icons/Wallet.vue'
import TableTooltip from '../TableTooltip.vue'

export default {
	name: 'AnalyticsSummaryGrid',
	components: {
		TableTooltip,
		TrendingDownIcon,
		TrendingUpIcon,
		WalletIcon
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
	grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
	gap: calc(var(--default-grid-baseline, 4px) * 3);
}

.summary-card {
	display: flex;
	min-width: 0;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 2.5);
	padding: calc(var(--default-grid-baseline, 4px) * 4);
	border: 1px solid var(--cobudget-border, var(--color-border));
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface, var(--color-main-background));
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.summary-card__header,
.summary-card__title,
.summary-card__detail {
	display: flex;
	align-items: center;
}

.summary-card__header,
.summary-card__detail {
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline, 4px) * 3);
}

.summary-card__title {
	min-width: 0;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}

.summary-card__icon {
	display: inline-flex;
	width: calc(var(--default-grid-baseline, 4px) * 8);
	height: calc(var(--default-grid-baseline, 4px) * 8);
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	background: var(--cobudget-surface-muted, var(--color-background-hover));
}

.summary-card__icon--income,
.positive {
	color: var(--cobudget-success, var(--color-success-text));
}

.summary-card__icon--expense,
.negative {
	color: var(--cobudget-error, var(--color-error-text));
}

.summary-card__icon--balance {
	color: var(--color-primary-element, var(--color-primary));
}

.summary-card__label {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
	font-weight: 600;
	letter-spacing: 0.5px;
	text-transform: uppercase;
}

.summary-card__value {
	min-width: 0;
	font-size: var(--cobudget-font-lg);
	line-height: 1.2;
	text-align: end;
	white-space: nowrap;
}

.summary-card__details {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline, 4px);
	padding-top: calc(var(--default-grid-baseline, 4px) * 2.5);
	border-top: 1px solid var(--cobudget-border, var(--color-border));
}

.summary-card__detail {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
}

.summary-card__detail > strong {
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: inherit;
	white-space: nowrap;
}

.summary-card__detail > strong.positive {
	color: var(--cobudget-success, var(--color-success-text));
}

.summary-card__detail > strong.negative {
	color: var(--cobudget-error, var(--color-error-text));
}

.summary-detail-tooltip {
	cursor: help;
	text-decoration: underline dotted;
	text-underline-offset: 3px;
}

@media (max-width: 768px) {
	.summary-grid {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
