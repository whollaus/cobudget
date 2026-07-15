<template>
	<section ref="chartSurface" class="analytics-card development-card">
		<div class="card-header">
			<div>
				<h3>{{ $texts.analytics.development() }}</h3>
				<p>{{ developmentLabel }}</p>
			</div>
			<div class="chart-legend">
				<span v-if="incomeEnabled"><i class="legend-income"></i>{{ $texts.analytics.income() }}</span>
				<span><i class="legend-expense"></i>{{ $texts.analytics.expenses() }}</span>
				<span v-if="hasBalanceChart"><i class="legend-balance"></i>{{ $texts.analytics.balance() }}</span>
			</div>
		</div>

		<div v-if="hasBalanceChart" class="line-chart" :aria-label="$texts.analytics.balanceDevelopment()">
			<svg viewBox="0 0 1000 260" preserveAspectRatio="none" role="img">
				<line x1="0" x2="1000" :y1="zeroLineY" :y2="zeroLineY" class="zero-line" />
				<polyline :points="cumulativeChartPoints" class="balance-line" />
				<circle
					:cx="lastChartPoint.x"
					:cy="lastChartPoint.y"
					r="8"
					class="balance-dot" />
			</svg>
		</div>

		<div class="series-bars" :aria-label="$texts.analytics.incomeAndExpensesPerPeriod()">
			<div v-for="(item, index) in series" :key="item.key" class="series-item">
				<div class="series-bar-pair">
					<span
						v-if="incomeEnabled"
						class="series-bar income"
						:aria-label="barTooltip($texts.analytics.income(), item.incomeCents)"
						:style="{ height: barHeight(item.incomeCents) }"
						tabindex="0"
						@blur="hideTooltip"
						@focus="showBarTooltip($event, item.label, $texts.analytics.income(), item.incomeCents)"
						@mouseenter="showBarTooltip($event, item.label, $texts.analytics.income(), item.incomeCents)"
						@mouseleave="hideTooltip"></span>
					<span
						class="series-bar expense"
						:aria-label="barTooltip($texts.analytics.expenses(), item.expenseCents)"
						:style="{ height: barHeight(item.expenseCents) }"
						tabindex="0"
						@blur="hideTooltip"
						@focus="showBarTooltip($event, item.label, $texts.analytics.expenses(), item.expenseCents)"
						@mouseenter="showBarTooltip($event, item.label, $texts.analytics.expenses(), item.expenseCents)"
						@mouseleave="hideTooltip"></span>
				</div>
				<small :class="{ muted: !showSeriesLabel(index) }">
					{{ showSeriesLabel(index) ? item.label : '' }}
				</small>
			</div>
		</div>

		<div
			v-if="tooltip.visible"
			class="development-tooltip"
			:style="{ left: `${tooltip.x}px`, top: `${tooltip.y}px` }"
			role="tooltip">
			<strong>{{ tooltip.period }}</strong>
			<span>{{ tooltip.label }}</span>
			<b>{{ tooltip.amount }}</b>
		</div>
	</section>
</template>

<script>
export default {
	name: 'AnalyticsDevelopmentChart',
	data() {
		return {
			tooltip: {
				visible: false,
				x: 0,
				y: 0,
				period: '',
				label: '',
				amount: ''
			}
		}
	},
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
		formatCents: {
			type: Function,
			required: true
		},
		incomeEnabled: {
			type: Boolean,
			default: true
		},
		showSeriesLabel: {
			type: Function,
			required: true
		}
	},
	computed: {
		hasBalanceChart() {
			if (!this.incomeEnabled || !this.cumulativeChartPoints || !this.lastChartPoint) {
				return false
			}

			const yValues = this.cumulativeChartPoints
				.split(' ')
				.map(point => Number(point.split(',')[1]))
				.filter(Number.isFinite)

			return yValues.some(y => Math.abs(y - this.zeroLineY) > 0.5)
		}
	},
	methods: {
		barTooltip(label, cents) {
			return `${label}: ${this.formatCents(Math.abs(Number(cents || 0)))}`
		},
		showBarTooltip(event, period, label, cents) {
			const position = this.getTooltipPosition(event.currentTarget)
			this.tooltip = {
				visible: true,
				x: position.x,
				y: position.y,
				period,
				label,
				amount: this.formatCents(Math.abs(Number(cents || 0)))
			}
		},
		hideTooltip() {
			this.tooltip = {
				...this.tooltip,
				visible: false
			}
		},
		getTooltipPosition(target) {
			const surface = this.$refs.chartSurface?.getBoundingClientRect()
			const rect = target?.getBoundingClientRect()

			if (!surface || !rect) {
				return {
					x: 0,
					y: 0
				}
			}

			const x = rect.left + rect.width / 2 - surface.left
			const y = rect.top - surface.top
			const maxX = Math.max(72, surface.width - 72)

			return {
				x: Math.min(Math.max(x, 72), maxX),
				y: Math.max(y, 12)
			}
		}
	}
}
</script>

<style scoped>
.analytics-card {
	position: relative;
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
	cursor: help;
	transition: opacity 0.12s ease, transform 0.12s ease;
}

.series-bar:hover,
.series-bar:focus-visible {
	opacity: 0.9;
	transform: translateY(-2px);
}

.series-bar:focus-visible {
	outline: 2px solid var(--color-primary-element, #0082c9);
	outline-offset: 3px;
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

.development-tooltip {
	position: absolute;
	z-index: 10;
	display: flex;
	min-width: 150px;
	flex-direction: column;
	gap: 2px;
	padding: 10px 12px;
	border: 1px solid var(--cobudget-border, rgba(255, 255, 255, 0.16));
	border-radius: var(--border-radius-large, 8px);
	background: var(--color-main-text, #222);
	box-shadow: 0 10px 28px rgba(0, 0, 0, 0.24);
	color: var(--color-main-background, #fff);
	pointer-events: none;
	transform: translate(-50%, calc(-100% - 10px));
}

.development-tooltip::after {
	position: absolute;
	bottom: -6px;
	left: 50%;
	width: 12px;
	height: 12px;
	background: inherit;
	border-right: 1px solid var(--cobudget-border, rgba(255, 255, 255, 0.16));
	border-bottom: 1px solid var(--cobudget-border, rgba(255, 255, 255, 0.16));
	content: '';
	transform: translateX(-50%) rotate(45deg);
}

.development-tooltip strong {
	font-size: var(--cobudget-font-sm);
	line-height: 1.25;
}

.development-tooltip span {
	color: currentColor;
	font-size: var(--cobudget-font-xs);
	opacity: 0.75;
}

.development-tooltip b {
	font-size: var(--cobudget-font-base);
	line-height: 1.25;
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
