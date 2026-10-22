<template>
	<section class="annual-chart">
		<header class="annual-chart-header">
			<h3><button type="button" class="annual-chart-toggle" :aria-expanded="open" :aria-controls="`${hintId}-body`" @mousedown.prevent @click="toggleSection">
				{{ $texts.analytics.development() }}
				<ChevronDownIcon v-if="open" :size="18" /><ChevronRightIcon v-else :size="18" />
			</button></h3>
			<label v-show="open" class="annual-chart-mode" :title="modeHint">
				<span aria-hidden="true">{{ modeLabel }}<ChevronDownIcon :size="18" /></span>
				<select :value="mode" :aria-label="$texts.annual.chartDisplay()" @change="$emit('update:mode', $event.target.value)">
					<option value="period">{{ $texts.annual.chartPerPeriod() }}</option>
					<option value="cumulative">{{ $texts.annual.chartCumulative() }}</option>
				</select>
			</label>
		</header>
		<div v-show="open" :id="`${hintId}-body`">
			<div
				ref="plot"
				class="annual-chart-plot"
				role="group"
				tabindex="0"
				:aria-label="`${$texts.analytics.development()} – ${modeLabel}`"
				:aria-describedby="hintId"
				@pointermove="inspectPointer"
				@pointerdown="inspectPointer"
				@pointerleave="leavePointer"
				@focus="focusPlot"
				@blur="blurPlot"
				@keydown="inspectKey">
				<svg :viewBox="`0 0 ${geometry.width} ${geometry.height}`" preserveAspectRatio="none" aria-hidden="true">
					<g v-for="tick in geometry.ticks" :key="tick.value">
						<line :x1="geometry.left" :x2="geometry.right" :y1="tick.y" :y2="tick.y" class="annual-chart-grid" :class="{ 'annual-chart-zero': tick.value === 0 }" />
						<text :x="geometry.left - 8" :y="tick.y + 4" text-anchor="end" class="annual-chart-axis">{{ formatCents(tick.value) }}</text>
					</g>
					<g v-for="item in geometry.series" :key="item.id" :class="`annual-chart-series--${item.id}`">
						<path :d="item.area" class="annual-chart-area" />
						<path :d="item.line" class="annual-chart-line" />
						<template v-if="labels.length <= 31">
							<circle v-for="(point, index) in item.points.filter(point => point.value !== null)" :key="index" :cx="point.x" :cy="point.y" r="2.5" class="annual-chart-dot" />
						</template>
					</g>
					<g v-if="activeIndex >= 0">
						<line :x1="geometry.x(activeIndex)" :x2="geometry.x(activeIndex)" :y1="geometry.top" :y2="geometry.bottom" class="annual-chart-guide" />
						<template v-for="item in geometry.series" :key="item.id">
							<circle v-if="item.points[activeIndex] && item.points[activeIndex].value !== null" :cx="item.points[activeIndex].x" :cy="item.points[activeIndex].y" r="4" class="annual-chart-active" :class="`annual-chart-series--${item.id}`" />
						</template>
					</g>
					<text v-for="index in axisTicks" :key="index" :x="geometry.x(index)" :y="geometry.height - 10" text-anchor="middle" class="annual-chart-axis">{{ axisLabels[index] || labels[index] }}</text>
				</svg>
				<div v-if="activeIndex >= 0" class="annual-chart-tooltip" :style="tooltipStyle" aria-hidden="true">
					<strong>{{ labelTitles[activeIndex] || labels[activeIndex] }}</strong>
					<div v-for="item in visibleSeries" :key="item.id" class="annual-chart-tooltip-row" :class="`annual-chart-series--${item.id}`">
						<span><i class="annual-chart-swatch" />{{ seriesLabel(item.id) }}</span>
						<b>{{ item.values[activeIndex] === null ? '–' : formatCents(item.values[activeIndex]) }}</b>
					</div>
				</div>
			</div>
			<div class="annual-chart-legend">
				<button v-for="item in series" :key="item.id" type="button" :class="[`annual-chart-series--${item.id}`, { 'is-hidden': !isSeriesVisible(item.id) }]"
					:aria-pressed="isSeriesVisible(item.id)" :disabled="isLastSeries(item.id)" :title="legendTitle(item.id)" @click="toggleSeries(item.id)">
					<i class="annual-chart-swatch" aria-hidden="true" />{{ seriesLabel(item.id) }}
				</button>
			</div>
			<p :id="hintId" class="visually-hidden">{{ modeHint }} {{ $texts.annual.chartKeyboardHint() }}</p>
			<p class="visually-hidden" aria-live="polite" aria-atomic="true">{{ hasFocus ? activeDescription : '' }}</p>
		</div>
	</section>
</template>

<script>
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import { readAnnualSectionOpen, rememberAnnualSection } from '../services/annualOverview'
import { annualChartGeometry, annualChartPointerIndex, annualChartScale, annualChartSeries, annualChartTicks } from '../services/annualOverviewChart'

let chartSequence = 0

export default {
	name: 'AnnualOverviewChart',
	components: { ChevronDownIcon, ChevronRightIcon },
	emits: ['update:mode', 'update:hiddenSeries'],
	props: {
		report: { type: Object, required: true },
		labels: { type: Array, required: true },
		labelTitles: { type: Array, default: () => [] },
		axisLabels: { type: Array, default: () => [] },
		incomeEnabled: { type: Boolean, default: true },
		mode: { type: String, default: 'cumulative' },
		hiddenSeries: { type: Array, default: () => [] },
		formatCents: { type: Function, required: true },
	},
	data() {
		return { open: readAnnualSectionOpen('chart'), chartWidth: 800, activeIndex: -1, hasFocus: false, resizeObserver: null, hintId: `annual-chart-hint-${++chartSequence}` }
	},
	computed: {
		series() { return annualChartSeries(this.report, this.labels.length, { incomeEnabled: this.incomeEnabled, cumulative: this.mode === 'cumulative' }) },
		visibleSeries() {
			const visible = this.series.filter(item => !this.hiddenSeries.includes(item.id))
			// Keep the chart useful if income management is disabled while expenses are hidden.
			return visible.length ? visible : this.series
		},
		scale() { return annualChartScale(this.visibleSeries) },
		axisLabelWidth() { return Math.max(0, ...this.labels.map((label, index) => String(this.axisLabels[index] || label).length * 7)) },
		geometry() {
			const left = Math.min(118, Math.max(64, ...this.scale.ticks.map(value => this.formatCents(value).length * 6 + 8)))
			return annualChartGeometry(this.visibleSeries, this.labels.length, this.chartWidth, { left, rightInset: Math.max(16, this.axisLabelWidth / 2 + 8), scale: this.scale })
		},
		axisTicks() { return annualChartTicks(this.labels.length, this.geometry.right - this.geometry.left, Math.max(60, this.axisLabelWidth + 16)) },
		modeLabel() { return this.mode === 'cumulative' ? this.$texts.annual.chartCumulative() : this.$texts.annual.chartPerPeriod() },
		modeHint() { return this.mode === 'cumulative' ? this.$texts.annual.chartCumulativeHint() : this.$texts.annual.chartPerPeriodHint() },
		tooltipStyle() {
			const width = Math.min(260, this.chartWidth - 16)
			const left = Math.max(8, Math.min(this.chartWidth - width - 8, this.geometry.x(this.activeIndex) + 12))
			return { width: `${width}px`, left: `${left}px` }
		},
		activeDescription() {
			if (this.activeIndex < 0) return ''
			return [this.labelTitles[this.activeIndex] || this.labels[this.activeIndex], ...this.visibleSeries.map(item => `${this.seriesLabel(item.id)}: ${item.values[this.activeIndex] === null ? '–' : this.formatCents(item.values[this.activeIndex])}`)].join(', ')
		},
	},
	watch: {
		report() { this.activeIndex = this.hasFocus && this.labels.length ? 0 : -1 },
	},
	mounted() {
		this.measurePlot()
		if (typeof ResizeObserver === 'function') {
			this.resizeObserver = new ResizeObserver(this.measurePlot)
			this.resizeObserver.observe(this.$refs.plot)
		}
	},
	beforeUnmount() { this.resizeObserver?.disconnect() },
	methods: {
		toggleSection() {
			this.open = !this.open
			rememberAnnualSection('chart', this.open)
			if (this.open) this.$nextTick(this.measurePlot)
			else { this.hasFocus = false; this.activeIndex = -1 }
		},
		measurePlot() { if (this.open) this.chartWidth = Math.max(180, Math.round(this.$refs.plot?.clientWidth || 800)) },
		seriesLabel(id) {
			if (id === 'income') return this.$texts.annual.income()
			if (id === 'expense') return this.$texts.analytics.expenses()
			return this.$texts.analytics.balance()
		},
		isSeriesVisible(id) { return this.visibleSeries.some(item => item.id === id) },
		isLastSeries(id) { return this.visibleSeries.length === 1 && this.isSeriesVisible(id) },
		legendTitle(id) {
			if (this.isLastSeries(id)) return this.$texts.annual.chartKeepOneSeries()
			return this.isSeriesVisible(id) ? this.$texts.annual.chartHideSeries(this.seriesLabel(id)) : this.$texts.annual.chartShowSeries(this.seriesLabel(id))
		},
		toggleSeries(id) {
			if (!this.series.some(item => item.id === id) || this.isLastSeries(id)) return
			const hidden = this.hiddenSeries.filter(value => value !== id)
			if (this.isSeriesVisible(id)) hidden.push(id)
			this.$emit('update:hiddenSeries', hidden)
		},
		inspectPointer(event) {
			this.activeIndex = annualChartPointerIndex(event.clientX, this.$refs.plot.getBoundingClientRect(), this.geometry, this.labels.length)
		},
		leavePointer() { if (!this.hasFocus) this.activeIndex = -1 },
		focusPlot() { this.hasFocus = true; if (this.activeIndex < 0 && this.labels.length) this.activeIndex = 0 },
		blurPlot() { this.hasFocus = false; this.activeIndex = -1 },
		inspectKey(event) {
			if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return
			if (!['ArrowLeft', 'ArrowRight', 'Home', 'End', 'Escape'].includes(event.key)) return
			event.preventDefault()
			event.stopPropagation()
			if (event.key === 'Escape') { this.activeIndex = -1; return }
			if (!this.labels.length) return
			if (event.key === 'Home') this.activeIndex = 0
			else if (event.key === 'End') this.activeIndex = this.labels.length - 1
			else this.activeIndex = Math.max(0, Math.min(this.labels.length - 1, this.activeIndex + (event.key === 'ArrowLeft' ? -1 : 1)))
		},
	},
}
</script>

<style scoped>
.annual-chart {
	box-sizing: border-box;
	min-width: 0;
	margin: 16px 0 24px;
	padding: 14px 0;
	background: var(--cobudget-surface);
}
.annual-chart-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
.annual-chart-header h3 { margin: 0; font-size: var(--cobudget-font-lg); font-weight: 600; color: var(--cobudget-text); }
.annual-chart-toggle { display: inline-flex; align-items: center; gap: 2px; max-width: 100%; margin: 0; padding: 2px 4px; border: 0; border-radius: var(--border-radius-element, 8px); background: transparent; color: inherit; font: inherit; font-weight: 600; text-align: start; cursor: pointer; }
.annual-chart-toggle:hover { background: var(--cobudget-surface-muted, var(--color-background-hover)); }
.annual-chart-toggle:focus,
.annual-chart-toggle:active { background: transparent; }
.annual-chart-toggle:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: 2px; }
.annual-chart-mode { position: relative; display: inline-flex; border-radius: var(--border-radius-element, 8px); color: var(--cobudget-text-muted); }
.annual-chart-mode > span { display: flex; align-items: center; gap: 4px; min-height: 36px; padding-inline: 8px; }
.annual-chart-mode select { position: absolute; inset: 0; width: 100%; height: 100%; min-height: 0; margin: 0; opacity: 0; cursor: pointer; }
.annual-chart-mode:hover { background: var(--cobudget-surface-muted); }
.annual-chart-mode:focus-within,
.annual-chart-plot:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: 2px; }
.annual-chart-plot { position: relative; min-width: 0; border-radius: var(--border-radius, 6px); touch-action: pan-y; }
.annual-chart-plot svg { display: block; width: 100%; height: 220px; overflow: visible; }
.annual-chart-grid { stroke: var(--cobudget-border); stroke-width: 1; }
.annual-chart-zero { stroke: var(--cobudget-text-muted); stroke-dasharray: 3 3; }
.annual-chart-axis { fill: var(--cobudget-text-muted); font: 11px var(--font-face, sans-serif); }
.annual-chart-series--income { --series-color: #46853d; --series-dash: none; --series-border: solid; }
.annual-chart-series--expense { --series-color: #da7079; --series-dash: 6 3; --series-border: dashed; }
.annual-chart-series--balance { --series-color: #5476a6; --series-dash: 2 3; --series-border: dotted; }
.annual-chart-line { fill: none; stroke: var(--series-color); stroke-width: 2.5; stroke-dasharray: var(--series-dash); stroke-linejoin: round; stroke-linecap: round; vector-effect: non-scaling-stroke; }
.annual-chart-area { fill: var(--series-color); opacity: 0.06; }
.annual-chart-dot { fill: var(--series-color); }
.annual-chart-active { fill: var(--cobudget-surface); stroke: var(--series-color); stroke-width: 2.5; }
.annual-chart-guide { stroke: var(--cobudget-text-muted); stroke-dasharray: 2 3; }
.annual-chart-legend { display: flex; flex-wrap: wrap; gap: 4px 12px; margin: 8px 0 0; font-size: var(--cobudget-font-sm); color: var(--cobudget-text-muted); }
.annual-chart-legend > button { min-height: 32px; margin: 0; padding: 4px 6px; border: 0; border-radius: var(--border-radius-element, 8px); background: transparent; color: inherit; font: inherit; cursor: pointer; }
.annual-chart-legend > button:hover:enabled { background: var(--cobudget-surface-muted); }
.annual-chart-legend > button:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: 2px; }
.annual-chart-legend > button.is-hidden { text-decoration: line-through; }
.annual-chart-legend > button.is-hidden .annual-chart-swatch { opacity: 0.45; }
.annual-chart-legend > button:disabled { opacity: 1; cursor: default; }
.annual-chart-legend > button,
.annual-chart-tooltip-row > span { display: inline-flex; align-items: center; gap: 6px; }
.annual-chart-swatch { display: inline-block; flex: 0 0 auto; width: 16px; height: 0; border-top: 3px var(--series-border) var(--series-color); }
.annual-chart-tooltip { position: absolute; top: 8px; z-index: 2; box-sizing: border-box; padding: 10px 12px; pointer-events: none; border: 1px solid var(--cobudget-border); border-radius: var(--border-radius-large, 8px); background: var(--cobudget-surface); color: var(--cobudget-text); box-shadow: var(--box-shadow); font-size: var(--cobudget-font-sm); }
.annual-chart-tooltip > strong { display: block; margin-bottom: 8px; overflow-wrap: anywhere; }
.annual-chart-tooltip-row { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 4px 12px; margin-top: 4px; }
.annual-chart-tooltip-row b { margin-inline-start: auto; font-variant-numeric: tabular-nums; }
.visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 768px) { .annual-chart { padding: 12px 0; } .annual-chart-mode > span, .annual-chart-legend > button { min-height: 44px; } }
</style>
