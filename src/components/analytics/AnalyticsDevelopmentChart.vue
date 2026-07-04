<template>
	<section class="analytics-card development-card">
		<div class="card-header">
			<div>
				<h3>{{ $texts.analytics.development() }}</h3>
				<p>{{ developmentLabel }}</p>
			</div>
			<div class="chart-legend">
				<span><i class="legend-income"></i>{{ $texts.analytics.income() }}</span>
				<span><i class="legend-expense"></i>{{ $texts.analytics.expenses() }}</span>
				<span><i class="legend-balance"></i>{{ $texts.analytics.balance() }}</span>
			</div>
		</div>

		<div class="line-chart" :aria-label="$texts.analytics.balanceDevelopment()">
			<svg viewBox="0 0 1000 260" preserveAspectRatio="none" role="img">
				<line x1="0" x2="1000" :y1="zeroLineY" :y2="zeroLineY" class="zero-line" />
				<polyline v-if="cumulativeChartPoints" :points="cumulativeChartPoints" class="balance-line" />
				<circle
					v-if="lastChartPoint"
					:cx="lastChartPoint.x"
					:cy="lastChartPoint.y"
					r="8"
					class="balance-dot" />
			</svg>
		</div>

		<div class="series-bars" :aria-label="$texts.analytics.incomeAndExpensesPerPeriod()">
			<div v-for="(item, index) in series" :key="item.key" class="series-item">
				<div class="series-bar-pair">
					<span class="series-bar income" :style="{ height: barHeight(item.incomeCents) }"></span>
					<span class="series-bar expense" :style="{ height: barHeight(item.expenseCents) }"></span>
				</div>
				<small :class="{ muted: !showSeriesLabel(index) }">
					{{ showSeriesLabel(index) ? item.label : '' }}
				</small>
			</div>
		</div>
	</section>
</template>

<script>
export default {
	name: 'AnalyticsDevelopmentChart',
	props: {
		developmentLabel: {
			type: String,
			required: true
		},
		zeroLineY: {
			type: Number,
			required: true
		},
		cumulativeChartPoints: {
			type: String,
			default: ''
		},
		lastChartPoint: {
			type: Object,
			default: null
		},
		series: {
			type: Array,
			required: true
		},
		barHeight: {
			type: Function,
			required: true
		},
		showSeriesLabel: {
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

.chart-legend {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	justify-content: flex-end;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
	font-weight: 600;
}

.chart-legend span {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.chart-legend i {
	display: inline-block;
	width: 10px;
	height: 10px;
	border-radius: 50%;
}

.legend-income {
	background: var(--cobudget-success, #10b981);
}

.legend-expense {
	background: var(--cobudget-error);
}

.legend-balance {
	background: var(--color-primary-element, #0082c9);
}

.line-chart {
	height: 210px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: linear-gradient(180deg, var(--cobudget-surface-muted, #f5f5f5), var(--cobudget-page-background, #fff));
	overflow: hidden;
}

.line-chart svg {
	width: 100%;
	height: 100%;
}

.zero-line {
	stroke: var(--cobudget-border, #e5e5e5);
	stroke-width: 2;
}

.balance-line {
	fill: none;
	stroke: var(--color-primary-element, #0082c9);
	stroke-width: 6;
	stroke-linecap: round;
	stroke-linejoin: round;
}

.balance-dot {
	fill: var(--color-primary-element, #0082c9);
	stroke: var(--cobudget-page-background, #fff);
	stroke-width: 4;
}

.series-bars {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(14px, 1fr));
	gap: 4px;
	align-items: end;
	min-height: 120px;
	margin-top: 14px;
}

.series-item {
	display: flex;
	min-width: 0;
	flex-direction: column;
	align-items: center;
	gap: 6px;
}

.series-bar-pair {
	display: flex;
	align-items: end;
	justify-content: center;
	gap: 3px;
	width: 100%;
	height: 82px;
}

.series-bar {
	display: block;
	width: min(14px, 40%);
	border-radius: 999px 999px 0 0;
}

.series-bar.income {
	background: var(--cobudget-success, #10b981);
}

.series-bar.expense {
	background: var(--cobudget-error);
}

.series-item small {
	min-height: 14px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-xs);
	white-space: nowrap;
}

.series-item small.muted {
	opacity: 0;
}

@media (max-width: 900px) {
	.card-header {
		flex-direction: column;
		align-items: stretch;
	}

	.chart-legend {
		justify-content: flex-start;
	}
}
</style>
