<template>
	<div class="drilldown-development" :class="`drilldown-development--${type}`">
		<AnalyticsDevelopmentChart
			embedded
			:title="$texts.analytics.developmentFor(title)"
			:development-label="developmentLabel"
			:zero-line-y="0"
			:series="series"
			:bar-height="barHeight"
			:format-cents="formatCents"
			:income-enabled="type === 'income'"
			:series-type="type"
			:series-aria-label="developmentLabel"
			:show-series-label="showSeriesLabel" />

		<div class="drilldown-development-actions">
			<button
				type="button"
				class="drilldown-values-toggle"
				:aria-controls="valuesTableId"
				:aria-expanded="valuesExpanded"
				@click="valuesExpanded = !valuesExpanded">
				{{ valuesExpanded ? $texts.analytics.hideValues() : $texts.analytics.showValues() }}
			</button>
		</div>

		<div v-show="valuesExpanded" :id="valuesTableId" class="drilldown-values-wrap">
			<table class="drilldown-values-table">
				<caption class="visually-hidden">
					{{ $texts.analytics.periodValuesFor(title) }}
				</caption>
				<thead>
					<tr>
						<th>{{ $texts.analytics.period() }}</th>
						<th class="count-cell">{{ $texts.analytics.bookingsColumn() }}</th>
						<th class="amount-cell">{{ $texts.analytics.amount() }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="item in series" :key="item.key">
						<td>{{ item.label }}</td>
						<td class="count-cell">{{ Number(item.count || 0) }}</td>
						<td class="amount-cell">{{ formatCents(seriesValue(item)) }}</td>
					</tr>
				</tbody>
				<tfoot>
					<tr>
						<td>{{ $texts.analytics.total() }}</td>
						<td class="count-cell">{{ totalCount }}</td>
						<td class="amount-cell">{{ formatCents(totalCents) }}</td>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>
</template>

<script>
import AnalyticsDevelopmentChart from './AnalyticsDevelopmentChart.vue'

let valuesTableSequence = 0

export default {
	name: 'AnalyticsDrilldownDevelopment',
	components: {
		AnalyticsDevelopmentChart
	},
	props: {
		title: {
			type: String,
			required: true
		},
		type: {
			type: String,
			required: true,
			validator: value => ['income', 'expense'].includes(value)
		},
		series: {
			type: Array,
			required: true
		},
		periodLabel: {
			type: String,
			required: true
		},
		formatCents: {
			type: Function,
			required: true
		}
	},
	data() {
		valuesTableSequence += 1
		return {
			valuesExpanded: false,
			valuesTableId: `analytics-drilldown-values-${valuesTableSequence}`
		}
	},
	computed: {
		developmentLabel() {
			const typeLabel = this.type === 'income'
				? this.$texts.analytics.income()
				: this.$texts.analytics.expenses()
			return this.$texts.analytics.focusDevelopmentHint(typeLabel, this.periodLabel)
		},
		seriesMaxAmount() {
			return Math.max(1, ...this.series.map(item => this.seriesValue(item)))
		},
		totalCount() {
			return this.series.reduce((sum, item) => sum + Number(item.count || 0), 0)
		},
		totalCents() {
			return this.series.reduce((sum, item) => sum + this.seriesValue(item), 0)
		}
	},
	methods: {
		seriesValue(item) {
			const key = this.type === 'income' ? 'incomeCents' : 'expenseCents'
			return Math.abs(Number(item?.[key] || 0))
		},
		barHeight(cents) {
			const amount = Math.abs(Number(cents || 0))
			if (amount === 0) {
				return '0%'
			}
			return `${Math.max(4, Math.round((amount / this.seriesMaxAmount) * 100))}%`
		},
		showSeriesLabel(index) {
			const length = this.series.length
			return length <= 12 || index === 0 || index === length - 1 || index % 5 === 0
		}
	}
}
</script>

<style scoped>
.drilldown-development {
	margin: 4px 0 22px;
	padding: 18px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface-muted, #f5f5f5);
}

.drilldown-development-actions {
	display: flex;
	justify-content: flex-end;
	margin-top: 12px;
}

.drilldown-values-toggle {
	min-height: 38px;
	padding: 0 14px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface, #fff);
	color: var(--color-primary-element, #0082c9);
	font-weight: 700;
	cursor: pointer;
}

.drilldown-values-toggle:hover,
.drilldown-values-toggle:focus-visible {
	border-color: var(--color-primary-element, #0082c9);
	outline: 2px solid var(--color-primary-element, #0082c9);
	outline-offset: 1px;
}

.drilldown-values-wrap {
	margin-top: 12px;
	overflow-x: auto;
}

.drilldown-values-table {
	width: 100%;
	border-collapse: collapse;
	background: var(--cobudget-surface, #fff);
}

.drilldown-values-table th,
.drilldown-values-table td {
	padding: 8px 10px;
	border-bottom: 1px solid var(--cobudget-border, #e5e5e5);
	text-align: left;
}

.drilldown-values-table th {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-xs);
	text-transform: uppercase;
}

.drilldown-values-table .count-cell,
.drilldown-values-table .amount-cell {
	text-align: right;
	white-space: nowrap;
}

.drilldown-values-table .amount-cell {
	font-weight: 700;
}

.drilldown-development--expense .drilldown-values-table tbody .amount-cell {
	color: var(--cobudget-error);
}

.drilldown-development--income .drilldown-values-table tbody .amount-cell {
	color: var(--cobudget-success, #10b981);
}

.drilldown-values-table tfoot td {
	border-top: 2px solid var(--cobudget-border, #e5e5e5);
	border-bottom: 0;
	font-weight: 800;
}

.visually-hidden {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}

@media (max-width: 600px) {
	.drilldown-development {
		padding: 14px;
	}

	.drilldown-development-actions,
	.drilldown-values-toggle {
		width: 100%;
	}
}
</style>
