<template>
	<section class="mobile-finance-summary" :aria-label="$texts.dashboard.keyFigures()">
		<div
			ref="cards"
			class="mobile-finance-summary__cards"
			tabindex="0"
			@touchstart.stop
			@touchmove.stop
			@touchend.stop
			@touchcancel.stop
			@keydown="onKeydown">
			<article v-for="period in periods" :key="period.key" class="mobile-summary-card">
				<div class="mobile-summary-card__header">
					<div class="mobile-summary-card__title">
						<span class="mobile-summary-card__icon" :style="{ color: period.iconColor }" aria-hidden="true">
							<component :is="period.icon" :size="20" />
						</span>
						<h3>{{ period.label }}</h3>
					</div>
					<div class="mobile-summary-card__value-group">
						<strong class="mobile-summary-card__value" :class="period.valueClass">
							{{ period.value }}
						</strong>
					</div>
				</div>

				<div class="mobile-summary-card__details">
					<div v-for="detail in period.details" :key="detail.key" class="mobile-summary-card__detail">
						<span>{{ detail.label }}</span>
						<strong :class="detail.valueClass">{{ detail.value }}</strong>
					</div>
				</div>
			</article>

			<component
				:is="metric.route ? 'RouterLink' : 'article'"
				v-for="metric in metrics"
				:key="metric.key"
				:to="metric.route || undefined"
				class="mobile-summary-card"
				:class="{ 'mobile-summary-card--link': metric.route }">
				<div class="mobile-summary-card__header">
					<div class="mobile-summary-card__title">
						<span class="mobile-summary-card__icon" :style="{ color: metric.iconColor }" aria-hidden="true">
							<component :is="metric.icon" :size="20" />
						</span>
						<h3>{{ metric.label }}</h3>
					</div>
					<div class="mobile-summary-card__metric-value">
						<strong class="mobile-summary-card__value" :class="metric.valueClass">
							{{ metric.value }}
						</strong>
						<ChevronRightIcon v-if="metric.route" :size="18" aria-hidden="true" />
					</div>
				</div>

				<div v-if="metric.details.length > 0" class="mobile-summary-card__details">
					<div v-for="detail in metric.details" :key="detail.key" class="mobile-summary-card__detail">
						<span>{{ detail.label }}</span>
						<strong :class="detail.valueClass">{{ detail.value }}</strong>
					</div>
				</div>
			</component>
		</div>
	</section>
</template>

<script>
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import { RouterLink } from 'vue-router'

export default {
	name: 'MobileFinanceSummary',
	components: {
		ChevronRightIcon,
		RouterLink,
	},
	props: {
		periods: {
			type: Array,
			required: true,
		},
		metrics: {
			type: Array,
			required: true,
		},
	},
	methods: {
		onKeydown(event) {
			if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
				return
			}

			event.preventDefault()
			this.$refs.cards?.scrollBy({
				left: (event.key === 'ArrowLeft' ? -1 : 1) * Math.max(this.$refs.cards.clientWidth * 0.8, 216),
				behavior: 'smooth',
			})
		},
	},
}
</script>

<style scoped>
.mobile-finance-summary {
	display: none;
}

@media (max-width: 768px) {
	.mobile-finance-summary {
		display: block;
		min-width: 0;
		margin-bottom: calc(var(--default-grid-baseline, 4px) * 5);
		color: var(--cobudget-text, var(--color-main-text, #222));
	}

	.mobile-finance-summary__cards {
		display: flex;
		box-sizing: border-box;
		width: 100%;
		min-width: 0;
		overflow-x: auto;
		overflow-y: hidden;
		gap: calc(var(--default-grid-baseline, 4px) * 2);
		padding: 2px 2px calc(var(--default-grid-baseline, 4px) * 2);
		overscroll-behavior-inline: contain;
		scrollbar-width: none;
		touch-action: pan-x pan-y;
		-webkit-overflow-scrolling: touch;
	}

	.mobile-finance-summary__cards::-webkit-scrollbar {
		display: none;
	}

	.mobile-finance-summary__cards:focus {
		outline: none;
	}

	.mobile-finance-summary__cards:focus-visible {
		border-radius: var(--cobudget-radius-lg, var(--border-radius-large, 8px));
		box-shadow: var(--cobudget-focus-ring, 0 0 0 2px var(--cobudget-primary));
	}

	.mobile-summary-card {
		display: flex;
		box-sizing: border-box;
		inline-size: max-content;
		min-inline-size: calc(var(--default-grid-baseline, 4px) * 54);
		flex: 0 0 max-content;
		flex-direction: column;
		gap: calc(var(--default-grid-baseline, 4px) * 2.5);
		padding: calc(var(--default-grid-baseline, 4px) * 3);
		border: 1px solid var(--cobudget-border, var(--color-border, #ddd));
		border-radius: var(--cobudget-radius-lg, var(--border-radius-large, 8px));
		background: var(--cobudget-surface, var(--color-main-background, #fff));
		color: inherit;
		text-decoration: none;
	}

	.mobile-summary-card--link {
		cursor: pointer;
		transition: background-color 0.15s ease, border-color 0.15s ease;
	}

	.mobile-summary-card--link:hover,
	.mobile-summary-card--link:focus-visible {
		border-color: var(--cobudget-primary, var(--color-primary-element));
		background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5));
	}

	.mobile-summary-card--link:focus-visible {
		outline: none;
		box-shadow: var(--cobudget-focus-ring, 0 0 0 2px var(--cobudget-primary));
	}

	.mobile-summary-card__header {
		display: flex;
		min-width: 0;
		align-items: center;
		justify-content: space-between;
		gap: calc(var(--default-grid-baseline, 4px) * 3);
		white-space: nowrap;
	}

	.mobile-summary-card__title {
		display: flex;
		min-width: 0;
		max-inline-size: calc(var(--default-grid-baseline, 4px) * 48);
		flex: 0 1 auto;
		align-items: center;
		gap: calc(var(--default-grid-baseline, 4px) * 2);
	}

	.mobile-summary-card__icon {
		display: inline-flex;
		width: calc(var(--default-grid-baseline, 4px) * 8);
		height: calc(var(--default-grid-baseline, 4px) * 8);
		flex: 0 0 auto;
		align-items: center;
		justify-content: center;
		border-radius: 50%;
		background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5));
	}

	.mobile-summary-card__title h3 {
		min-width: 0;
		max-inline-size: calc(var(--default-grid-baseline, 4px) * 38);
		margin: 0;
		overflow: hidden;
		color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
		font-size: var(--cobudget-font-sm, 12px);
		font-weight: 600;
		letter-spacing: 0.4px;
		line-height: 1.3;
		text-overflow: ellipsis;
		text-transform: uppercase;
		white-space: nowrap;
	}

	.mobile-summary-card__value-group {
		display: flex;
		min-width: 0;
		flex: 0 0 auto;
		flex-direction: column;
		align-items: flex-end;
		margin-inline-start: auto;
	}

	.mobile-summary-card__metric-value {
		display: inline-flex;
		min-width: 0;
		flex: 0 0 auto;
		align-items: center;
		gap: var(--default-grid-baseline, 4px);
		margin-inline-start: auto;
	}

	.mobile-summary-card__value,
	.mobile-summary-card__detail strong {
		font-variant-numeric: tabular-nums;
		white-space: nowrap;
	}

	.mobile-summary-card__value {
		font-size: var(--cobudget-font-lg, 18px);
		line-height: 1.2;
	}

	.mobile-summary-card__details {
		display: flex;
		min-width: 0;
		flex-direction: column;
		gap: var(--default-grid-baseline, 4px);
		padding-top: calc(var(--default-grid-baseline, 4px) * 2);
		border-top: 1px solid var(--cobudget-border, var(--color-border, #ddd));
	}

	.mobile-summary-card__detail {
		display: flex;
		min-width: 0;
		align-items: baseline;
		justify-content: space-between;
		gap: calc(var(--default-grid-baseline, 4px) * 3);
		color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
		font-size: var(--cobudget-font-sm, 12px);
	}

	.mobile-summary-card__detail > span {
		min-width: 0;
		max-inline-size: calc(var(--default-grid-baseline, 4px) * 45);
		flex: 0 1 auto;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.mobile-summary-card__detail strong {
		margin-inline-start: auto;
		color: var(--cobudget-text, var(--color-main-text, #222));
		font-size: inherit;
	}

	.positive {
		color: var(--cobudget-success, var(--color-success-text, #008800)) !important;
	}

	.negative {
		color: var(--cobudget-error, var(--color-error-text, #d13438)) !important;
	}
}

@media (prefers-reduced-motion: reduce) {
	.mobile-summary-card--link {
		transition: none;
	}
}
</style>
