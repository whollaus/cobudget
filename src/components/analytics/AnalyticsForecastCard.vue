<template>
	<section class="analytics-card available-forecast-card">
		<div class="card-header">
			<div>
				<h3>{{ title }}</h3>
				<p>{{ forecast.basisLabel }}</p>
			</div>
			<TableTooltip :text="tooltip">
				<span class="forecast-confidence">{{ forecast.confidenceLabel }}</span>
			</TableTooltip>
		</div>
		<div class="available-forecast-grid">
			<div class="available-forecast-main">
				<span>{{ $texts.analytics.availableForecast() }}</span>
				<strong :class="amountClass(forecast.forecastCents)">
					{{ mainLabel }}
				</strong>
				<small>{{ remainingLabel }}</small>
			</div>
			<div class="available-forecast-metrics">
				<div>
					<span>{{ $texts.analytics.expectedIncome() }}</span>
					<strong class="positive">{{ formatCents(forecast.expectedIncomeCents) }}</strong>
				</div>
				<div>
					<span>{{ $texts.analytics.expectedExpenses() }}</span>
					<strong class="negative">{{ formatCents(forecast.expectedExpenseCents) }}</strong>
				</div>
				<div>
					<span>{{ $texts.analytics.fromToday() }}</span>
					<strong :class="amountClass(forecast.remainingChangeCents)">
						{{ formatSignedCents(forecast.remainingChangeCents) }}
					</strong>
				</div>
			</div>
		</div>
		<div class="available-forecast-range">
			<span>{{ $texts.analytics.possibleRange() }}</span>
			<strong>{{ rangeLabel }}</strong>
		</div>
	</section>
</template>

<script>
import TableTooltip from '../TableTooltip.vue'

export default {
	name: 'AnalyticsForecastCard',
	components: {
		TableTooltip
	},
	props: {
		forecast: {
			type: Object,
			required: true
		},
		title: {
			type: String,
			required: true
		},
		tooltip: {
			type: String,
			required: true
		},
		mainLabel: {
			type: String,
			required: true
		},
		remainingLabel: {
			type: String,
			required: true
		},
		rangeLabel: {
			type: String,
			required: true
		},
		amountClass: {
			type: Function,
			required: true
		},
		formatCents: {
			type: Function,
			required: true
		},
		formatSignedCents: {
			type: Function,
			required: true
		}
	}
}
</script>

<style scoped>
.analytics-card {
	padding: 18px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.card-header {
	display: flex;
	justify-content: space-between;
	gap: 16px;
	align-items: flex-start;
	margin-bottom: 14px;
}

.card-header h3 {
	margin: 0;
	font-size: var(--cobudget-font-xl);
	line-height: 1.25;
}

.card-header p {
	margin: 4px 0 0;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.forecast-confidence {
	display: inline-flex;
	align-items: center;
	padding: 6px 8px;
	border-radius: 8px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
	text-decoration: underline dotted;
	text-underline-offset: 3px;
}

.available-forecast-grid {
	display: grid;
	grid-template-columns: minmax(220px, 1fr) 2fr;
	gap: 12px;
	align-items: stretch;
}

.available-forecast-main,
.available-forecast-metrics div {
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 14px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
}

.available-forecast-main span,
.available-forecast-main small,
.available-forecast-metrics span,
.available-forecast-range span {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
}

.available-forecast-main strong {
	font-size: var(--cobudget-font-title);
	line-height: 1.15;
}

.available-forecast-metrics {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
}

.available-forecast-metrics strong {
	font-size: var(--cobudget-font-lg-plus);
}

.available-forecast-range {
	display: flex;
	justify-content: space-between;
	gap: 16px;
	align-items: center;
	margin-top: 12px;
	padding-top: 12px;
	border-top: 1px solid var(--cobudget-border, #e5e5e5);
}

.available-forecast-range strong {
	text-align: right;
}

@media (max-width: 900px) {
	.card-header {
		flex-direction: column;
		align-items: stretch;
	}

	.available-forecast-grid,
	.available-forecast-metrics {
		grid-template-columns: 1fr;
	}

	.available-forecast-range {
		align-items: flex-start;
		flex-direction: column;
	}

	.available-forecast-range strong {
		text-align: left;
	}
}
</style>
