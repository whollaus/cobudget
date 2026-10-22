import assert from 'node:assert/strict'
import fs from 'node:fs'
import * as Vue from 'vue'
import { parse } from '@vue/compiler-sfc'
import { compile } from '@vue/compiler-dom'
import { renderToString } from '@vue/server-renderer'

const source = path => fs.readFileSync(new URL(`../${path}`, import.meta.url), 'utf8')
const charts = await import(`data:text/javascript;charset=utf-8,${encodeURIComponent(source('src/services/annualOverviewChart.js'))}`)
const annual = await import(`data:text/javascript;charset=utf-8,${encodeURIComponent(source('src/services/annualOverview.js'))}`)
const { annualChartSeries, annualChartScale, annualChartGeometry, annualChartTicks, annualChartPointerIndex } = charts
const report = {
	totals: {
		income: { months: [300000, 320000, 10000] },
		expense: { months: [200000, 210000, 50000] },
		balance: { months: [100000, 110000, -40000] },
	},
	// A payment in two tag rows must still occur once in the chart.
	groups: { expense: [{ months: [200000, 210000, 50000] }, { months: [200000, 210000, 50000] }] },
	periods: [{ dateFrom: 100 }, { dateFrom: 200 }, { dateFrom: 300 }, { dateFrom: 100 }],
	cutoff: 350,
}
const original = structuredClone(report)
const period = annualChartSeries(report, 3)
assert.deepEqual(period.map(item => item.id), ['income', 'expense', 'balance'])
assert.deepEqual(period[1].values, [200000, 210000, 50000], 'Use the canonical totals rather than summing overlapping tag rows')
assert.deepEqual(period[2].values, [100000, 110000, -40000], 'A negative period balance remains negative')
const cumulative = annualChartSeries(report, 3, { cumulative: true })
assert.deepEqual(cumulative[0].values, [300000, 620000, 630000])
assert.deepEqual(cumulative[1].values, [200000, 410000, 460000], 'February includes January and February expenses')
assert.deepEqual(cumulative[2].values, [100000, 210000, 170000], 'Cumulative balance follows income minus expenses')
assert.deepEqual(report, original, 'Switching chart mode does not change report amounts or rows')
const expenseOnly = annualChartSeries(report, 3, { incomeEnabled: false, cumulative: true })
assert.deepEqual(expenseOnly, [cumulative[1]], 'Disabling incomes removes both incomes and balance')
const untilToday = annualChartSeries({ ...report, cutoff: 250 }, 3, { cumulative: true })
assert.deepEqual(untilToday[1].values, [200000, 410000, null], 'Periods beyond the cutoff do not become misleading flat forecasts')
assert.deepEqual(annualChartSeries({ ...report, cutoff: null }, 3)[1].values, period[1].values, 'An absent cutoff does not discard valid amounts')
const refunds = { totals: { expense: { months: [1031, -1, 2] } } }
assert.deepEqual(annualChartSeries(refunds, 3, { incomeEnabled: false, cumulative: true })[0].values, [1031, 1030, 1032], 'Accumulate exact cents and preserve refunds')

for (const series of [period, cumulative, expenseOnly, annualChartSeries({}, 4), [{ id: 'expense', values: [-100, -200, null] }]]) {
	const scale = annualChartScale(series)
	const amounts = series.flatMap(item => item.values.filter(value => value !== null))
	assert.ok(scale.min <= Math.min(0, ...amounts) && scale.max >= Math.max(0, ...amounts), 'The scale includes every amount and zero')
	assert.ok(scale.max > scale.min && scale.ticks.every(Number.isFinite), 'Empty and zero reports have a finite, nonzero scale')
	const geometry = annualChartGeometry(series, series[0].values.length, 320)
	assert.ok(geometry.zero >= geometry.top && geometry.zero <= geometry.bottom)
	for (const item of geometry.series) {
		assert.ok(!/NaN|Infinity/.test(item.line + item.area))
		for (const point of item.points.filter(point => point.value !== null)) {
			assert.ok(point.y >= geometry.top - 0.001 && point.y <= geometry.bottom + 0.001)
		}
	}
}
for (const count of [4, 12, 28, 29, 31, 52, 53, 365, 366]) {
	const dense = annualChartSeries({ totals: { expense: { months: Array.from({ length: count }, (_, index) => index + 1) } } }, count, { incomeEnabled: false })
	const geometry = annualChartGeometry(dense, count, 800)
	assert.equal(geometry.series[0].points.length, count, 'Dense weekly and daily plots retain every value')
	assert.equal((geometry.series[0].line.match(/[ML]/g) || []).length, count)
	const ticks = annualChartTicks(count, 220)
	assert.ok(ticks.length <= 3, 'Small screens thin axis labels without thinning values')
	assert.equal(ticks[0], 0)
	assert.equal(ticks.at(-1), count - 1)
	for (const width of [180, 220, 500, 800, 1050]) {
		const positions = annualChartTicks(count, width).map(index => index * width / (count - 1))
		for (let index = 1; index < positions.length; index++) {
			assert.ok(positions[index] - positions[index - 1] >= 76, 'Centered labels keep their spacing after snapping to actual daily or weekly points, including both edges')
		}
	}
	assert.equal(annualChartPointerIndex(100 + geometry.left, { left: 100, width: 800 }, geometry, count), 0)
	assert.equal(annualChartPointerIndex(100 + geometry.right, { left: 100, width: 800 }, geometry, count), count - 1)
}
const single = annualChartGeometry([{ id: 'expense', values: [100] }], 1, 320)
assert.equal(single.series[0].points[0].x, (single.left + single.right) / 2, 'A single period is centered')
const gap = annualChartGeometry([{ id: 'expense', values: [100, null, 200] }], 3, 320)
assert.equal((gap.series[0].line.match(/M/g) || []).length, 2, 'Unknown values do not get an interpolated line')
const padded = annualChartGeometry([{ id: 'expense', values: [100, 200] }], 2, 320, { rightInset: 40 })
assert.equal(padded.series[0].points.at(-1).x, 280, 'Reserve space for half of the centered final label plus its edge gap')
assert.deepEqual(annualChartTicks(31, 40), [0], 'Extremely narrow plots can omit labels instead of overlapping them')

const { descriptor } = parse(source('src/components/AnnualOverviewChart.vue'))
const bindings = { ...charts, ...annual, ChevronDownIcon: { render: () => Vue.h('span') }, ChevronRightIcon: { render: () => Vue.h('span') } }
const script = descriptor.script.content.replace(/^import .*$/gm, '').replace('export default', 'return')
const Chart = new Function(...Object.keys(bindings), script)(...Object.values(bindings))
Chart.render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue)
const texts = {
	analytics: { development: () => 'Development', income: () => 'Income', expenses: () => 'Expenses', balance: () => 'Balance' },
	annual: { income: () => 'Income', chartDisplay: () => 'Chart display', chartPerPeriod: () => 'Individual amounts', chartCumulative: () => 'Running total', chartPerPeriodHint: () => 'Each period', chartCumulativeHint: () => 'Running total', chartKeyboardHint: () => 'Arrow keys, Home, End and Escape' },
}
Object.assign(texts.annual, { chartHideSeries: series => `Hide ${series}`, chartShowSeries: series => `Show ${series}`, chartKeepOneSeries: () => 'At least one curve must remain visible.' })
const renderChart = async (props = {}, state = {}) => {
	const app = Vue.createSSRApp({ ...Chart, data() { return { ...Chart.data.call(this), ...state } } }, { report, labels: ['January', 'February', 'March'], formatCents: cents => `${(cents / 100).toFixed(2)} EUR`, ...props })
	app.config.globalProperties.$texts = texts
	return renderToString(app)
}
const markup = await renderChart()
assert.equal((markup.match(/class="annual-chart-line"/g) || []).length, 3, 'All three financial series share one plot')
assert.ok(markup.includes('aria-label="Chart display"') && markup.includes('value="cumulative"'))
assert.ok(markup.includes('tabindex="0"') && markup.includes('aria-live="polite"'))
assert.ok(markup.includes('aria-label="Development – Running total"'), 'The chart starts with running totals')
assert.ok(markup.includes('class="annual-chart-toggle" aria-expanded="true" aria-controls='), 'Development is a collapsible section with an accessible content association')
assert.ok(markup.includes('text-anchor="middle"'), 'Time labels are centered on their data points')
const expensesMarkup = await renderChart({ incomeEnabled: false })
assert.equal((expensesMarkup.match(/class="annual-chart-line"/g) || []).length, 1)
assert.equal(expensesMarkup.includes('Income'), false, 'Income amounts and their legend are hidden with the feature')
assert.equal(expensesMarkup.includes('Balance'), false)
const legendButtons = html => [...html.match(/<div class="annual-chart-legend">([\s\S]*?)<\/div>/)[1].matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/g)].map(match => ({ attributes: match[1], label: match[2].replace(/<[^>]+>/g, '').trim() }))
assert.equal(legendButtons(markup).filter(button => button.attributes.includes('aria-pressed="true"')).length, 3, 'Each enabled curve has a pressed legend button')
const expenseFocus = await renderChart({ hiddenSeries: ['income', 'balance'] }, { activeIndex: 1, hasFocus: true })
assert.equal((expenseFocus.match(/class="annual-chart-line"/g) || []).length, 1, 'Hidden curves leave both lines and areas out of the plot')
assert.equal((expenseFocus.match(/class="annual-chart-area"/g) || []).length, 1)
assert.equal((expenseFocus.match(/class="annual-chart-tooltip-row /g) || []).length, 1, 'Value inspection only shows the visible curves')
const focusLegend = legendButtons(expenseFocus)
assert.equal(focusLegend.length, 3, 'Hidden curves remain available in the legend')
assert.deepEqual(focusLegend.map(button => button.label), ['Income', 'Expenses', 'Balance'])
for (const button of focusLegend.filter(button => button.label !== 'Expenses')) {
	assert.ok(button.attributes.includes('is-hidden') && button.attributes.includes('aria-pressed="false"'), 'Hidden curves have both a visual and accessible unselected state')
	assert.ok(!button.attributes.includes(' disabled'), 'Hidden curves can be restored')
}
assert.ok(focusLegend.find(button => button.label === 'Expenses').attributes.includes(' disabled'), 'The final visible curve cannot be hidden')
const disabledIncomes = await renderChart({ incomeEnabled: false, hiddenSeries: ['expense'] })
assert.equal((disabledIncomes.match(/class="annual-chart-line"/g) || []).length, 1, 'Turning income management off still shows expenses when they were previously hidden')
assert.equal(legendButtons(disabledIncomes).length, 1, 'Income management controls which legend entries exist')
assert.ok(legendButtons(disabledIncomes)[0].attributes.includes('aria-pressed="true"'))

const uneven = annualChartSeries({ totals: { income: { months: [150000, 0, 0] }, expense: { months: [20000, 5000, 2000] }, balance: { months: [130000, -5000, -2000] } } }, 3)
const initialHidden = Object.freeze([])
const changes = []
const visibility = { series: uneven, hiddenSeries: initialHidden, $emit(event, value) { assert.equal(event, 'update:hiddenSeries'); this.hiddenSeries = value; changes.push(value) } }
Object.defineProperty(visibility, 'visibleSeries', { get() { return Chart.computed.visibleSeries.call(this) } })
for (const method of ['isSeriesVisible', 'isLastSeries', 'toggleSeries']) visibility[method] = Chart.methods[method].bind(visibility)
const initialScale = Chart.computed.scale.call(visibility)
visibility.toggleSeries('income')
visibility.toggleSeries('balance')
assert.deepEqual(visibility.visibleSeries.map(item => item.id), ['expense'])
const focusedScale = Chart.computed.scale.call(visibility)
assert.equal(focusedScale.max, 20000, 'The expense-only scale follows the largest expense instead of the much larger income')
assert.ok(focusedScale.max < initialScale.max)
assert.equal(focusedScale.min, 0, 'A hidden negative balance no longer enlarges the scale')
assert.deepEqual(visibility.visibleSeries[0].values, [20000, 5000, 2000], 'Toggling curves does not change their exact-cent amounts')
assert.deepEqual(initialHidden, [], 'The parent visibility array is never mutated')
visibility.toggleSeries('expense')
assert.equal(changes.length, 2, 'Trying to hide the final visible curve has no effect')
visibility.toggleSeries('balance')
assert.deepEqual(visibility.hiddenSeries, ['income'], 'Restoring one curve preserves the other hidden choice')
assert.ok(Chart.computed.scale.call(visibility).min < 0, 'Restored negative values are included in the scale again')
visibility.toggleSeries('unknown')
assert.equal(changes.length, 3, 'Unknown series cannot alter the selection')
assert.deepEqual(report, original, 'Legend interaction leaves canonical report totals untouched')
const previousWindow = globalThis.window
const preferences = new Map()
globalThis.window = {
	localStorage: { getItem: key => preferences.get(key) ?? null, setItem: (key, value) => preferences.set(key, value) },
	OC: { getCurrentUser: () => ({ uid: 'ada' }) },
}
const collapse = { open: true, activeIndex: 2, hasFocus: true, $nextTick(callback) { callback.call(this) }, measurePlot() { this.measured = true } }
Chart.methods.toggleSection.call(collapse)
assert.equal(collapse.open, false)
assert.equal(collapse.activeIndex, -1, 'Collapsing dismisses the inspected value')
assert.equal(collapse.hasFocus, false)
assert.equal(Chart.data().open, false, 'The same user reopens the annual view with the chart collapsed')
const closedMarkup = await renderChart()
const bodyId = closedMarkup.match(/aria-controls="([^"]+)"/)[1]
assert.ok(closedMarkup.includes('aria-expanded="false"'))
assert.match(closedMarkup.match(new RegExp(`<div[^>]*id="${bodyId}"[^>]*>`))?.[0] || '', /style="[^"]*display:\s*none(?:;|")/, 'The entire chart body is hidden when collapsed')
globalThis.window.OC.getCurrentUser = () => ({ uid: 'grace' })
assert.equal(Chart.data().open, true, 'Another account keeps its own collapse preference')
globalThis.window.OC.getCurrentUser = () => ({ uid: 'ada' })
Chart.methods.toggleSection.call(collapse)
assert.equal(collapse.open, true)
assert.equal(collapse.measured, true, 'Expanding measures the currently available plot width')
assert.equal(Chart.data().open, true)
if (previousWindow === undefined) delete globalThis.window
else globalThis.window = previousWindow
const state = { labels: Array(366).fill('Day'), activeIndex: 0 }
const key = value => ({ key: value, preventDefault() { this.prevented = true }, stopPropagation() {} })
for (const [pressed, expected] of [['ArrowRight', 1], ['End', 365], ['ArrowRight', 365], ['ArrowLeft', 364], ['Home', 0], ['ArrowLeft', 0], ['Escape', -1]]) {
	const event = key(pressed)
	Chart.methods.inspectKey.call(state, event)
	assert.equal(state.activeIndex, expected, 'Keyboard inspection reaches exact periods without extra Tab stops')
	assert.equal(event.prevented, true)
}
const tab = key('Tab')
Chart.methods.inspectKey.call(state, tab)
assert.equal(tab.prevented, undefined, 'Tab can leave the chart normally')
console.log('Annual chart tests passed: exact cents, running totals, feature flags, cutoff, dense periods, label spacing, collapse preferences, interactive legend, automatic scaling, rendering and keyboard inspection.')
