import assert from 'node:assert/strict'
import fs from 'node:fs'
import { createRouter, createMemoryHistory } from 'vue-router'

const read = file => fs.readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')
const load = file => import(`data:text/javascript;charset=utf-8,${encodeURIComponent(read(file))}`)
const { isReportView, reportLocation, legacyAnalyticsLocation } = await load('src/services/reportNavigation.js')
const { annualReportSelection } = await load('src/services/annualOverview.js')

assert.deepEqual(reportLocation({ name: 'personal' }), { name: 'annual-overview' })
assert.equal(isReportView('annual-overview'), true)
assert.equal(isReportView('analytics'), false, 'Statistics is now a redirect, not a second report view')
assert.equal(isReportView('payments'), false)
const query = Object.freeze({ year: '2025', projectId: '12', grouping: 'paymentPartners', detailId: '9', detailType: 'expense' })
assert.deepEqual(reportLocation({ name: 'annual-overview', query, hash: '#detail' }), { name: 'annual-overview', query, hash: '#detail' }, 'Active mobile navigation retains the open detail, filters and hash')
assert.deepEqual(reportLocation(), { name: 'annual-overview' })
globalThis.window = { localStorage: { getItem: () => 'analytics' } }
assert.deepEqual(reportLocation({ name: 'payments' }), { name: 'annual-overview' }, 'An old stored Statistics preference cannot restore the retired page')
Object.defineProperty(globalThis.window, 'localStorage', { get() { throw Error('Storage blocked') } })
assert.deepEqual(reportLocation({ name: 'projects' }), { name: 'annual-overview' }, 'Unified navigation does not require browser storage')
delete globalThis.window

// Use the production route table, rather than a separate test-only redirect.
const main = read('src/main.js')
const componentNames = [...main.matchAll(/^const (\w+) = \(\) => import/gm)].map(match => match[1])
const routeSource = main.match(/const routes = (\[[\s\S]*?\n\])/)[1]
const routes = new Function('legacyAnalyticsLocation', ...componentNames, `return ${routeSource}`)(legacyAnalyticsLocation, ...componentNames.map(() => ({ render: () => null })))
const router = createRouter({ history: createMemoryHistory(), routes })
await router.push('/analytics')
assert.equal(router.currentRoute.value.name, 'annual-overview')
assert.equal(router.currentRoute.value.redirectedFrom.path, '/analytics')
assert.equal(annualReportSelection(router.currentRoute.value.query).timeRange, 'current-year', 'A plain Statistics bookmark opens the default current-year report')

for (const period of ['current-year', 'last-year', 'current-month', 'last-month', 'last-12-months']) {
	const oldQuery = Object.freeze({ period, projectId: '7', entryScope: 'shared', categories: '4,5', labels: 'important', amountMin: '10,50', includeFuturePayments: 'true' })
	await router.push({ name: 'analytics', query: oldQuery })
	const route = router.currentRoute.value
	const { period: oldPeriod, ...filters } = oldQuery
	assert.equal(route.name, 'annual-overview', `${period}: redirects to Reports`)
	assert.deepEqual(route.query, { ...filters, timeRange: oldPeriod }, `${period}: translates the period while keeping all payment filters`)
	const selection = annualReportSelection(route.query)
	assert.equal(selection.timeRange, period)
	assert.equal(selection.period, ['current-month', 'last-month'].includes(period) ? 'day' : 'year', `${period}: retains the appropriate default breakdown`)
	assert.equal(oldQuery.period, period, 'Redirect never mutates the original route query')
}

await router.push('/analytics?period=year%3A2024&grouping=paymentPartners&projectId=7')
assert.deepEqual(router.currentRoute.value.query, { year: '2024', timeRange: 'year', grouping: 'paymentPartners', projectId: '7' })
const selection = annualReportSelection(router.currentRoute.value.query)
assert.equal(selection.dateFrom, '2024-01-01')
assert.equal(selection.dateTo, '2024-12-31')
for (const period of ['year:1969', 'year:10000', 'year:no', ['last-month', 'last-year'], 'https://example.test']) {
	const target = legacyAnalyticsLocation({ query: { period } })
	assert.equal(target.name, 'annual-overview', 'Unknown period input cannot choose another destination')
	assert.equal(annualReportSelection(target.query).timeRange, 'current-year', 'Invalid old periods use the current-year default')
}

const detailQuery = { timeRange: 'custom', dateFrom: '2025-10-04', dateTo: '2025-10-13', period: 'day', projectId: '7', entryScope: 'shared', detailType: 'expense', detailId: '4', reportFilters: '[{"dimension":"partner","id":8}]' }
await router.push({ name: 'analytics', query: detailQuery, hash: '#payments' })
assert.deepEqual(router.currentRoute.value.query, detailQuery, 'New report query syntax survives an old Statistics bookmark unchanged')
assert.equal(router.currentRoute.value.hash, '#payments')
assert.deepEqual(reportLocation({ name: 'analytics', query: detailQuery }), { name: 'annual-overview', query: detailQuery, hash: '' })
const explicitRange = { timeRange: 'custom', dateFrom: '2025-01-03', dateTo: '2025-01-12', period: 'last-month' }
assert.deepEqual(legacyAnalyticsLocation({ query: explicitRange }).query, { timeRange: 'custom', dateFrom: '2025-01-03', dateTo: '2025-01-12' }, 'An explicit modern date range takes precedence over a legacy period')

await router.push('/payments')
await router.push('/analytics?period=last-month')
const back = new Promise(resolve => { const remove = router.afterEach(() => { remove(); resolve() }) })
router.back()
await back
assert.equal(router.currentRoute.value.name, 'payments', 'Back skips the retired page after its redirect')
await router.push('/annual-overview?year=2020&period=week&projectId=7')
assert.equal(router.currentRoute.value.name, 'annual-overview', 'Existing annual report bookmarks stay on their original route')
assert.deepEqual(router.currentRoute.value.query, { year: '2020', period: 'week', projectId: '7' })

console.log('Unified report navigation passed: legacy links and periods, exact dates, filters, detail queries, mobile navigation, retired preferences and browser Back.')
