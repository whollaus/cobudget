import assert from 'node:assert/strict'
import fs from 'node:fs'
import * as Vue from 'vue'
import { parse } from '@vue/compiler-sfc'
import { compile } from '@vue/compiler-dom'
import { renderToString } from '@vue/server-renderer'
import { createRouter, createMemoryHistory } from 'vue-router'

const source = path => fs.readFileSync(new URL(`../${path}`, import.meta.url), 'utf8')
const moduleUrl = text => `data:text/javascript;charset=utf-8,${encodeURIComponent(text)}`
const { sortAreasByName } = await import(moduleUrl(source('src/utils/areaSorting.js').replace("'./formatDate'", JSON.stringify(moduleUrl("export const getAppLanguage = () => 'de-AT'")))))
const annual = await import(moduleUrl(source('src/services/annualOverview.js')))
const annualChart = await import(moduleUrl(source('src/services/annualOverviewChart.js')))
const reportDetails = await import(moduleUrl(source('src/services/reportDetails.js')))
const exportHelpers = await import(moduleUrl(source('src/services/reportExport.js').replace("'./annualOverview'", JSON.stringify(moduleUrl(source('src/services/annualOverview.js'))))))
const forecastHelpers = await import(moduleUrl(source('src/services/reportForecast.js')))
const paginationHelpers = await import(moduleUrl(source('src/services/pagination.js')))
const { planAnnualPrintPieces, annualPrintCellIndexes } = await import(moduleUrl(source('src/services/annualOverviewPrint.js')))
const { annualCellDetails, annualDetailComparison } = await import(moduleUrl(source('src/services/annualOverviewDetails.js')))
const routes = await import(moduleUrl(source('src/services/entryListRoute.js').replace("'./pagination'", JSON.stringify(moduleUrl(source('src/services/pagination.js'))))))
const currentYear = new Date().getFullYear()
assert.equal(annual.annualSelection({ year: currentYear }).includeFuturePayments, false, 'Current year defaults to payments up to today')
assert.equal(annual.annualSelection({ year: currentYear, includeFuturePayments: true }).includeFuturePayments, true)
assert.equal(annual.annualSelection({ year: currentYear - 1, includeFuturePayments: true }).includeFuturePayments, false, 'Past years have no hidden future-payment option')
assert.equal(annual.annualSelection({ year: currentYear + 1 }).includeFuturePayments, true, 'Future years include their entered payments without a hidden cutoff')
assert.equal(annual.annualSelection({ year: currentYear }).period, 'year')
assert.equal(annual.annualRouteQuery({ year: currentYear }).period, undefined, 'Year links stay free of a period parameter')
assert.equal(annual.annualRequestParams({ year: currentYear, period: 'month', month: 2 }).month, 2)
assert.equal(annual.annualSelection({ year: currentYear - 1, period: 'month' }).month, 1, 'A past year opens on January')
assert.equal(annual.annualSelection({ year: currentYear, period: 'month' }).month, 1, 'A legacy month link without a selected month opens January')
assert.equal(annual.annualSelection({ year: currentYear, period: 'month', month: 10 }).period, 'day', 'Legacy month links open the daily breakdown')
assert.equal(annual.annualSelection({ year: currentYear, period: 'month', month: 1, includeFuturePayments: true }).includeFuturePayments, new Date().getMonth() === 0, 'A past month shows the whole month')
assert.equal(annual.annualSelection({ year: currentYear, period: 'month', month: new Date().getMonth() + 1, includeFuturePayments: true }).includeFuturePayments, true)
if (new Date().getMonth() < 11) assert.equal(annual.annualSelection({ year: currentYear, period: 'month', month: new Date().getMonth() + 2 }).includeFuturePayments, true, 'A later month includes entered payments')
assert.deepEqual(annual.annualRouteQuery({ year: 2020, period: 'month', month: 2, grouping: 'categories' }), { year: '2020', grouping: 'categories', period: 'day', month: '2' })
assert.equal(annual.annualSelection({ year: currentYear }).quarter, 0, 'The yearly overview has no quarter')
assert.equal(annual.annualSelection({ year: currentYear, period: 'quarter' }).quarter, 0, 'The quarterly overview has no selected quarter')
assert.equal(annual.annualSelection({ year: currentYear, period: 'quarter' }).includeFuturePayments, false, 'The current year still defaults to payments up to today')
assert.equal(annual.annualSelection({ year: currentYear - 1, period: 'quarter', includeFuturePayments: true }).includeFuturePayments, false, 'A past year stays complete')
assert.equal(annual.annualSelection({ year: currentYear, period: 'quarter', quarter: 1, includeFuturePayments: true }).includeFuturePayments, true, 'A leftover quarter parameter does not hide an explicit cutoff')
assert.equal(annual.annualSelection({ year: currentYear + 1, period: 'quarter' }).includeFuturePayments, true, 'A future year includes entered payments')
assert.deepEqual(annual.annualRouteQuery({ year: 2020, period: 'quarter', quarter: 4, grouping: 'categories' }), { year: '2020', grouping: 'categories', period: 'quarter' })
assert.equal(annual.annualRouteQuery({ year: 2020, quarter: 2 }).quarter, undefined, 'Year links omit the quarter')
assert.equal(annual.annualRouteQuery({ year: 2020, period: 'month', month: 2, quarter: 1 }).quarter, undefined, 'Month links omit the quarter')
assert.equal(annual.annualRequestParams({ year: 2020, period: 'quarter', quarter: 2 }).quarter, undefined)
assert.equal(annual.annualRequestParams({ year: 2020, period: 'month', month: 2 }).quarter, undefined)
assert.equal(annual.isoWeekCount(2026), 53, '2026 has 53 calendar weeks')
assert.equal(annual.isoWeekCount(2020), 53, '2020 has 53 calendar weeks')
assert.equal(annual.isoWeekCount(2019), 52, '2019 has 52 calendar weeks')
assert.equal(annual.isoWeekCount(2025), 52, '2025 has 52 calendar weeks')
const weekOne = annual.isoWeekStart(2026, 1)
assert.equal(weekOne.getFullYear(), 2025)
assert.equal(weekOne.getMonth(), 11)
assert.equal(weekOne.getDate(), 29, 'Week 1 of 2026 starts on Monday 29 December 2025')
assert.equal(annual.isoWeekYear(new Date(2025, 11, 30, 12)), 2026)
assert.equal(annual.isoWeekNumber(new Date(2026, 2, 19, 15, 30)), 12, '19 March 2026 is calendar week 12')
assert.equal(annual.annualPeriodContainsToday(2026, 'week', 0, new Date(2025, 11, 30, 12)), true, '30 December 2025 belongs to week 1 of 2026')
assert.equal(annual.annualPeriodContainsToday(2025, 'week', 0, new Date(2025, 11, 30, 12)), false, 'That day is no longer inside the 2025 week-year')
assert.equal(annual.annualPeriodIsFuture(2026, 'week', 0, new Date(2025, 11, 30, 12)), false)
assert.equal(annual.annualPeriodIsFuture(2027, 'week', 0, new Date(2025, 11, 30, 12)), true)
assert.equal(annual.annualSelection({ year: currentYear, period: 'week' }).period, 'week')
assert.equal(annual.annualSelection({ year: currentYear, period: 'week' }).month, 0, 'The weekly overview has no month')
assert.equal(annual.annualSelection({ year: currentYear, period: 'week' }).quarter, 0)
assert.equal(annual.annualSelection({ year: currentYear, period: 'week' }).includeFuturePayments, false, 'The current week-year defaults to payments up to today')
assert.equal(annual.annualSelection({ year: currentYear - 1, period: 'week', includeFuturePayments: true }).includeFuturePayments, false, 'A past week-year stays complete')
assert.equal(annual.annualSelection({ year: currentYear + 1, period: 'week' }).includeFuturePayments, true, 'A future week-year includes entered payments')
assert.deepEqual(annual.annualRouteQuery({ year: 2020, period: 'week', week: 12, grouping: 'categories' }), { year: '2020', grouping: 'categories', period: 'week' })
assert.equal(annual.annualRequestParams({ year: 2020, period: 'week', week: 3 }).period, 'week')
assert.equal(annual.annualRequestParams({ year: 2020, period: 'week', week: 3 }).week, undefined, 'Week links omit the week number')
assert.equal(annual.annualRouteQuery({ year: 2020, week: 2 }).period, undefined, 'Year links omit the week')
assert.equal(annual.calendarDayCount(2024), 366, '2024 keeps 29 February')
assert.equal(annual.calendarDayCount(2020), 366, '2020 keeps 29 February')
assert.equal(annual.calendarDayCount(2026), 365, '2026 has 365 calendar days')
assert.equal(annual.calendarDayCount(2025), 365, '2025 has 365 calendar days')
assert.equal(annual.annualPeriodContainsToday(2026, 'day', 0, new Date(2026, 9, 21, 12)), true, 'The daily overview follows the calendar year')
assert.equal(annual.annualPeriodContainsToday(2025, 'day', 0, new Date(2026, 9, 21, 12)), false, 'A past calendar year is complete')
assert.equal(annual.annualPeriodIsFuture(2026, 'day', 0, new Date(2026, 9, 21, 12)), false, 'The current calendar year is not a future period')
assert.equal(annual.annualPeriodIsFuture(2027, 'day', 0, new Date(2026, 9, 21, 12)), true, 'A later calendar year includes entered payments')
assert.equal(annual.annualPeriodContainsToday(2026, 'day', 10, new Date(2026, 9, 21, 12)), true, 'The current month of the daily overview contains today')
assert.equal(annual.annualPeriodContainsToday(2026, 'day', 3, new Date(2026, 9, 21, 12)), false, 'A past month of the daily overview is complete')
assert.equal(annual.annualPeriodIsFuture(2026, 'day', 11, new Date(2026, 9, 21, 12)), true, 'A later month of the daily overview includes entered payments')
assert.equal(annual.annualPeriodIsFuture(2026, 'day', 10, new Date(2026, 9, 21, 12)), false, 'The current month of the daily overview is not a future period')
assert.equal(annual.annualPeriodIsFuture(2026, 'day', 3, new Date(2026, 9, 21, 12)), false, 'A past month of the daily overview is not a future period')
assert.equal(annual.annualSelection({ year: currentYear, period: 'day' }).period, 'day')
assert.equal(annual.annualSelection({ year: currentYear, period: 'day' }).month, 1, 'The daily breakdown defaults to January')
assert.equal(annual.annualSelection({ year: currentYear, period: 'day', month: 3 }).month, 3, 'A chosen month stays on the daily overview')
assert.equal(annual.annualSelection({ year: currentYear, period: 'day', month: 13 }).month, 1, 'An invalid daily month falls back to January')
for (const month of [0, '0', 'all']) {
	assert.equal(annual.annualSelection({ year: currentYear, period: 'day', month }).month, 0, 'An explicit All days choice keeps every calendar day')
}
assert.equal(annual.annualSelection({ year: currentYear, period: 'day' }).quarter, 0)
assert.equal(annual.annualSelection({ year: currentYear, period: 'day' }).includeFuturePayments, false, 'The current year defaults to payments up to today')
assert.equal(annual.annualSelection({ year: currentYear - 1, period: 'day', includeFuturePayments: true }).includeFuturePayments, false, 'A past year stays complete')
assert.equal(annual.annualSelection({ year: currentYear + 1, period: 'day' }).includeFuturePayments, true, 'A future year includes entered payments')
assert.equal(annual.annualPeriodContainsToday(currentYear, 'day', new Date().getMonth() + 1), true, 'The current month of the current daily overview contains today')
if (new Date().getMonth() > 0) {
	assert.equal(annual.annualPeriodContainsToday(currentYear, 'day', new Date().getMonth()), false, 'A past month of the current daily overview is complete')
	assert.equal(annual.annualSelection({ year: currentYear, period: 'day', month: new Date().getMonth(), includeFuturePayments: true }).includeFuturePayments, false, 'A past month of the current year stays complete')
}
if (new Date().getMonth() < 11) {
	assert.equal(annual.annualPeriodIsFuture(currentYear, 'day', new Date().getMonth() + 2), true, 'A later month of the current daily overview is in the future')
	assert.equal(annual.annualSelection({ year: currentYear, period: 'day', month: new Date().getMonth() + 2 }).includeFuturePayments, true, 'A later month includes entered payments')
}
assert.deepEqual(annual.annualRouteQuery({ year: 2020, period: 'day', day: 12, grouping: 'categories' }), { year: '2020', grouping: 'categories', period: 'day', month: '1' })
assert.deepEqual(annual.annualRouteQuery({ year: 2020, period: 'day', month: 3, grouping: 'categories' }), { year: '2020', grouping: 'categories', period: 'day', month: '3' })
assert.equal(annual.annualRequestParams({ year: 2020, period: 'day', day: 3 }).period, 'day')
assert.equal(annual.annualRequestParams({ year: 2020, period: 'day', day: 3 }).day, undefined, 'Day links omit the day number')
assert.equal(annual.annualRequestParams({ year: 2020, period: 'day', month: 3 }).month, 3, 'A chosen month is requested with the daily overview')
assert.equal(annual.annualRequestParams({ year: 2020, period: 'day' }).month, 1, 'The default request loads January')
assert.equal(annual.annualRequestParams({ year: 2020, period: 'day', month: 'all' }).month, undefined, 'All days requests the whole year')
assert.deepEqual(annual.annualRouteQuery({ year: 2020, period: 'day', month: 0 }), { year: '2020', grouping: 'categories', period: 'day', month: 'all' }, 'All days is explicit in the address')
const allDayFilters = annual.annualSelection({ year: currentYear, period: 'day', month: 'all', projectId: 12, grouping: 'labelsAndTags', categories: '7', partners: '2', hashtags: '9', amountMin: '10', includeFuturePayments: true })
assert.deepEqual(annual.annualSelection(annual.annualRouteQuery(allDayFilters)), allDayFilters, 'All days and combined filters survive reloading the address')
const allDayPayments = annual.annualPaymentRoute(allDayFilters, null, 'expense', { id: 'tag:9', kind: 'hashtag', filter: '9' })
assert.deepEqual(annual.annualReturnRoute(allDayPayments.query.annualReturn), { name: 'annual-overview', query: annual.annualRouteQuery(allDayFilters) }, 'Returning from matching payments preserves All days and its filters')
assert.equal(annual.annualRouteQuery({ year: 2020, day: 2 }).period, undefined, 'Year links omit the day')

for (const projectId of [null, 12]) {
	for (const grouping of ['categories', 'paymentPartners']) {
		for (const includeFuturePayments of [false, true]) {
			const selection = { year: currentYear, projectId, entryScope: 'shared', grouping, includeFuturePayments }
			const period = { dateFrom: 1767222000, dateTo: 1769900399 }
			for (const row of [null, { id: 0 }, { id: 42, children: [{ id: 43 }] }]) {
				const route = annual.annualPaymentRoute(selection, period, 'expense', row)
				assert.equal(route.name, projectId ? 'project-payments' : 'payments')
				const parse = projectId ? routes.parseProjectEntryListRouteQuery : routes.parsePersonalEntryListRouteQuery
				const serialize = projectId ? routes.serializeProjectEntryListRouteQuery : routes.serializePersonalEntryListRouteQuery
				const parsed = parse(route.query)
				assert.equal(parsed.filters.status, 'all', 'Settled payments must remain visible')
				assert.equal(parsed.filters.dateFrom, period.dateFrom)
				assert.equal(parsed.filters.dateTo, period.dateTo)
				assert.equal(parsed.filters.includeFuturePayments, includeFuturePayments)
				assert.equal(parsed.filters[grouping === 'categories' ? 'categoryId' : 'paymentPartnerId'], row?.id ?? null)
				assert.equal(parsed.filters.includeSubcategories, grouping === 'categories' && Boolean(row?.children?.length))
				const nextPage = serialize({ ...parsed, page: 2, includePagination: true })
				assert.deepEqual(parse(nextPage).filters, parsed.filters, 'Paging retains all drilldown filters')
				assert.deepEqual(annual.annualReturnRoute(nextPage.annualReturn), { name: 'annual-overview', query: annual.annualRouteQuery(selection) })
				const undated = annual.annualPaymentRoute(selection, null, 'expense', row)
				assert.equal(undated.query.timeRange, 'all')
				assert.equal(undated.query.dateFrom, undefined)
				assert.equal(undated.query.dateTo, undefined)
				assert.deepEqual(parse(undated.query).filters, { ...parsed.filters, timeRange: 'all', dateFrom: null, dateTo: null }, 'Label links preserve scope and filters without a date range')
			}
		}
	}
}
for (const projectId of [null, 12]) {
	const selection = { year: currentYear, projectId, entryScope: 'shared', grouping: 'labelsAndTags', includeFuturePayments: false }
	const period = { dateFrom: 1767222000, dateTo: 1769900399 }
	const parse = projectId ? routes.parseProjectEntryListRouteQuery : routes.parsePersonalEntryListRouteQuery
	for (const [row, tags, hashtagId] of [
		[{ id: 'label:important', kind: 'label', filter: 'important' }, 'important', null],
		[{ id: 'tag:9', kind: 'hashtag', filter: '9' }, 'all', 9],
	]) {
		const route = annual.annualPaymentRoute(selection, period, 'expense', row)
		const parsed = parse(route.query)
		assert.equal(route.name, projectId ? 'project-payments' : 'payments')
		assert.equal(parsed.filters.categoryId, null, 'Label rows do not filter by category')
		assert.equal(parsed.filters.paymentPartnerId, null, 'Label rows do not filter by payment partner')
		assert.equal(parsed.filters.includeSubcategories, false)
		assert.equal(parsed.filters.tags, tags)
		assert.equal(parsed.filters.hashtagId, hashtagId)
		assert.equal(annual.annualReturnRoute(route.query.annualReturn).query.grouping, 'labelsAndTags')
	}
	const total = parse(annual.annualPaymentRoute(selection, period, 'expense', null).query)
	assert.equal(total.filters.tags, 'all', 'Totals keep every label and tag')
	assert.equal(total.filters.hashtagId, null)
}
assert.equal(annual.annualSelection({ grouping: 'labelsAndTags' }).grouping, 'labelsAndTags')
assert.equal(annual.annualSelection({ grouping: 'unknown' }).grouping, 'categories', 'Unknown groupings stay on categories')
const filteredSelection = annual.annualSelection({
	year: currentYear,
	grouping: 'categories',
	partners: '4,4,8',
	labels: 'important,childRelated',
	hashtags: '3',
	categories: '9',
	amountMin: '100,5',
	amountMax: '20',
})
assert.deepEqual(filteredSelection.categories, [], 'Category filters disappear while grouping by category')
assert.deepEqual(filteredSelection.partners, [4, 8], 'Repeated partners stay one choice each')
assert.deepEqual(filteredSelection.labels, ['important', 'childRelated'])
assert.deepEqual(filteredSelection.hashtags, [3])
assert.equal(filteredSelection.amountMin, 2000, 'Reversed amount bounds are ordered before the request')
assert.equal(filteredSelection.amountMax, 10050)
assert.equal(annual.annualRouteQuery(filteredSelection).categories, undefined)
assert.equal(annual.annualRouteQuery(filteredSelection).amountMin, '20')
assert.equal(annual.annualRouteQuery(filteredSelection).amountMax, '100.50')
const labelGrouped = annual.annualSelection({ grouping: 'labelsAndTags', labels: 'important', hashtags: '9', categories: '3', partners: '4' })
assert.deepEqual(labelGrouped.labels, [], 'Label filters disappear while grouping by labels and tags')
assert.deepEqual(labelGrouped.hashtags, [9], 'Description tags still narrow a label and tag overview')
assert.deepEqual(labelGrouped.categories, [3])
assert.deepEqual(labelGrouped.partners, [4])
assert.equal(annual.annualFilterSections('categories').categories, false)
assert.equal(annual.annualFilterSections('paymentPartners').partners, false)
assert.equal(annual.annualFilterSections('labelsAndTags').labels, false)
assert.equal(annual.annualFilterSections('labelsAndTags').hashtags, true)
assert.equal(annual.annualFilterPanelMaxHeight({ triggerTop: 80, triggerBottom: 120, viewportHeight: 800, placement: 'bottom-start' }), '656px', 'The open filter menu uses the space under the trigger')
assert.equal(annual.annualFilterPanelMaxHeight({ triggerTop: 640, triggerBottom: 680, viewportHeight: 800, placement: 'top-start' }), '616px', 'A flipped filter menu uses the space above the trigger')
assert.equal(annual.annualFilterPanelMaxHeight({ triggerTop: 760, triggerBottom: 790, viewportHeight: 800, placement: 'bottom-start' }), '', 'No cap is applied before the menu can fit on that side')
assert.equal(annual.annualFilterPanelMaxHeight({ triggerTop: 80, triggerBottom: 120, viewportHeight: 800, placement: '' }), '', 'The menu stays uncapped until its placement is known')
assert.deepEqual(annual.annualFilterChips(filteredSelection, {
	partnerNames: new Map([[4, 'Amazon'], [8, 'MediaMarkt']]),
	labelNames: { important: 'Wichtig', childRelated: 'Kinder' },
	hashtagNames: new Map([[3, 'Reise']]),
	or: 'oder',
	atLeast: 'ab',
	atMost: 'bis',
	formatMoney: cents => `${cents / 100} €`,
}).map(chip => chip.label), ['Amazon oder MediaMarkt', '20 €–100.5 €', 'Wichtig oder Kinder', '#Reise'])
const twinSelection = annual.annualSelection({ grouping: 'labelsAndTags', categories: '1,11', partners: '4,6' })
assert.deepEqual(annual.annualFilterChips(twinSelection, {
	categoryNames: new Map([[1, 'Gehalt'], [11, 'Gehalt']]),
	categoryTypes: new Map([[1, 'expense'], [11, 'income']]),
	partnerNames: new Map([[4, 'Amazon'], [6, 'Client']]),
	partnerTypes: new Map([[4, 'expense'], [6, 'income']]),
	or: 'oder',
}).map(chip => chip.label), ['Gehalt (−) oder Gehalt (+)', 'Amazon oder Client'], 'Only a repeated name on both sides carries the income or expense mark')
assert.deepEqual(annual.annualFilterChips(annual.annualSelection({ grouping: 'labelsAndTags', categories: '1,11' }), {
	categoryNames: new Map([[1, 'Gehalt'], [11, 'Gehalt']]),
	or: 'oder',
}).map(chip => chip.label), ['Gehalt oder Gehalt'], 'Disabled income leaves chip names unmarked')
const areaCatalog = [{ id: 30, name: 'Bonus', type: 'income' }]
assert.deepEqual([...annual.annualFilterNameMaps(areaCatalog, [30]).types], [[30, 'income']], 'A mark uses the catalog loaded for the current area')
assert.equal(annual.annualFilterNameMaps([{ id: 1, name: 'Groceries', type: 'expense' }], [30]).names.size, 0, 'A personal category is not labeled from another area')
assert.equal(annual.annualFilterNameMaps(areaCatalog, [30], { incomes: false }).types.size, 0, 'Disabled income adds no type marks')
const filteredRoute = annual.annualPaymentRoute(filteredSelection, { dateFrom: 1767222000, dateTo: 1769900399 }, 'expense', { id: 1 })
assert.equal(filteredRoute.query.categoryId, '1')
assert.equal(filteredRoute.query.partners, '4,8')
assert.equal(filteredRoute.query.labels, 'important,childRelated')
assert.equal(filteredRoute.query.hashtagId, '3', 'One tag uses the payment list control')
assert.equal(filteredRoute.query.amountMin, '20')
assert.equal(filteredRoute.query.amountMax, '100.50')
const filteredParsed = routes.parsePersonalEntryListRouteQuery(filteredRoute.query)
assert.equal(filteredParsed.filters.narrowPartners, '4,8')
assert.equal(filteredParsed.filters.narrowLabels, 'important,childRelated')
assert.equal(filteredParsed.filters.hashtagId, 3)
assert.equal(filteredParsed.filters.amountMin, '20')
assert.equal(filteredParsed.filters.amountMax, '100.50')
assert.deepEqual(routes.parsePersonalEntryListRouteQuery(routes.serializePersonalEntryListRouteQuery({ ...filteredParsed, includePagination: true })).filters, filteredParsed.filters, 'Paging retains amount and multi-value filters')
assert.equal(annual.annualReturnRoute(filteredRoute.query.annualReturn).query.partners, '4,8', 'Returning restores the annual filters')
assert.equal(annual.annualPaymentRoute({ year: 2026 }, { dateFrom: 0, dateTo: 100 }, 'all').query.type, 'all', 'Balance opens both payment types')
assert.equal(routes.parsePersonalEntryListRouteQuery({ timeRange: 'custom', dateFrom: '-3600', dateTo: '0' }).filters.dateFrom, -3600, '1970 local boundary survives routing')
assert.equal(routes.parsePersonalEntryListRouteQuery({ categoryId: '42wrong' }).filters.categoryId, null)
for (const invalid of ['', 'invalid', 'null', '"https://example.com"']) assert.equal(annual.annualReturnRoute(invalid), null)
console.log('Annual overview route tests passed: scopes, grouping, unassigned rows, hierarchy, exact dates, pagination and return context.')

// Render the production view and table without a browser. Only external UI and
// network boundaries are replaced; computed values, loops and links stay real.
const slotComponent = (tag, slot = 'default') => ({ setup: (_, { slots }) => () => Vue.h(tag, slots[slot]?.()) })
const printCalls = []
const csvDownloads = []
const exportToasts = []
const exportErrors = []
const savedFiles = []
const nextcloudPicker = { folder: '/Dokumente', error: null }
const nextcloudUser = { uid: 'ada' }
const comparisonHelpers = await import(moduleUrl(source('src/services/reportComparisons.js')))
const { filterChipIcon } = await import(`data:text/javascript;charset=utf-8,${encodeURIComponent(fs.readFileSync(new URL('../src/services/filterChipIcons.js', import.meta.url), 'utf8').replace("'@mdi/js'", JSON.stringify(new URL('../node_modules/@mdi/js/mdi.js', import.meta.url).href)))}`)
const bindings = { filterChipIcon, CalendarOutlineIcon: slotComponent('span'), ShapeOutlineIcon: slotComponent('span'), AccountOutlineIcon: slotComponent('span'), FolderIcon: slotComponent('span'), TagOutlineIcon: slotComponent('span'), PoundIcon: slotComponent('span'), BellOutlineIcon: slotComponent('span'), PaperclipIcon: slotComponent('span'), CashIcon: slotComponent('span'), CalendarClockOutlineIcon: slotComponent('span'),  CalendarIcon: slotComponent('span'), ReportPeriodPicker: {}, PlusIcon: slotComponent('span'), MinusIcon: slotComponent('span'), useId: Vue.useId, ...routes,
	...exportHelpers,
	...forecastHelpers,
	...comparisonHelpers,
	sortAreasByName,
	...annual,
	...annualChart,
	...reportDetails,
	...paginationHelpers,
	downloadTextFile: (filename, contents) => { csvDownloads.push({ filename, contents }) },
	showToast: message => { exportToasts.push(message) },
	showRequestError: (_error, fallback) => { exportErrors.push(fallback) },
	getCurrentUser: () => nextcloudUser.uid ? { uid: nextcloudUser.uid } : null,
	generateRemoteUrl: service => `https://cloud.example.test/remote.php/${service}`,
	pickFolder: picker => picker.build().pick(),
	getFilePickerBuilder: () => ({
		setMultiSelect() { return this },
		allowDirectories() { return this },
		setMimeTypeFilter() { return this },
		addButton() { return this },
		build() { return this },
		pick() {
			if (nextcloudPicker.error) return Promise.reject(nextcloudPicker.error)
			return Promise.resolve(nextcloudPicker.folder)
		},
	}),
	AreaIcon: slotComponent('span'),
	CoBudgetIcon: slotComponent('span'),
 NavigationIcon: slotComponent('span'),
	NcActions: slotComponent('div'),
	NcActionButton: slotComponent('button'), NcActionSeparator: { render: () => Vue.h('li', { role: 'separator' }) },
	CloudUploadOutlineIcon: slotComponent('span'),
	DownloadIcon: slotComponent('span'), ClipboardTextOutlineIcon: slotComponent('span'),
	NcChip: { props: ['text'], setup: props => () => Vue.h('span', { class: 'native-filter-chip' }, props.text) },
	NcInputField: { props: ['modelValue', 'label'], setup: props => () => Vue.h('input', { value: props.modelValue, 'aria-label': props.label }) },
	NcCheckboxRadioSwitch: slotComponent('label'), NcFormBoxSwitch: slotComponent('label'), NcRadioGroup: slotComponent('div'), NcRadioGroupButton: slotComponent('button'),
	NcButton: slotComponent('button'),
	RouterLink: { props: ['to'], setup: (props, { slots }) => () => Vue.h('a', { href: JSON.stringify(props.to) }, slots.default?.()) },
	CategoryIcon: slotComponent('span'),
	TableTooltip: slotComponent('span'), TrendingUpIcon: slotComponent('span'), TrendingDownIcon: slotComponent('span'),
	DialogTransition: { props: ['show'], setup: (props, { slots }) => () => props.show ? slots.default?.({ returnFocus: () => false }) : null },
	NcModal: slotComponent('section'), annualCellDetails, annualDetailComparison, getAppLocale: () => 'de-AT',
	ChevronLeftIcon: slotComponent('span'), ChevronRightIcon: slotComponent('span'), ChevronDownIcon: slotComponent('span'), ChevronUpIcon: slotComponent('span'),
	PrinterIcon: slotComponent('span'), printAnnualOverview: (...args) => { printCalls.push(args) },
	ArrowLeftIcon: slotComponent('span'), EntryTable: slotComponent('table'), EntryHistoryModal: slotComponent('section'),
	AppPageHeader: { props: ['title'], setup: (props, { slots }) => () => Vue.h('header', [slots.creation?.(), Vue.h('h2', slots.title?.() || props.title), slots.actions?.()]) },
	axios: {}, generateUrl: value => value, REMOTE_DATA_CHANGED_EVENT: 'change', PRINT_REPORT_EVENT: 'cobudget-print-report',
	formatCalendarLabel: (date, options) => new Intl.DateTimeFormat('de-AT', options).format(date),
	getAppLanguage: () => 'de-AT',
}
Object.assign(bindings, new Function(...Object.keys(bindings), source('src/services/reportExportActions.js').replace(/^import .*$/gm, '').replaceAll('export async function', 'async function') + '\nreturn { deliverReportExport, saveReportExport, copyReportText }')(...Object.values(bindings)))

function component(file) {
	const { descriptor } = parse(source(file))
	const script = descriptor.script.content.replace(/^import .*$/gm, '').replace('export default', 'return')
	const options = new Function(...Object.keys(bindings), script)(...Object.values(bindings))
	options.render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue)
	return options
}
const hierarchy = await import(moduleUrl(source('src/utils/categoryHierarchy.js')))
Object.assign(bindings, hierarchy)
bindings.CheckIcon = slotComponent('span')
bindings.PaymentFilterSelect = component('src/components/PaymentFilterSelect.vue')
bindings.NcActionCaption = slotComponent('li')
bindings.ReportSelect = component('src/components/ReportSelect.vue')
bindings.ReportScrollControls = component('src/components/ReportScrollControls.vue')
bindings.DraggableScroller = component('src/components/DraggableScroller.vue')
// Native selections must retain typed IDs, null scope and boolean future filters.
const selectOptions = [null, false, true, 12, 'year'].map(value => ({ value, label: String(value) }))
for (const value of [null, false, true, 12, 'year']) {
	const changes = []
	const state = { options: selectOptions, modelValue: 'different', disabled: false, $emit: (event, value) => changes.push([event, value]) }
	bindings.ReportSelect.methods.select.call(state, { value })
	assert.deepEqual(changes, [['update:modelValue', value]], 'Native report selections preserve primitive types')
	state.modelValue = value
	assert.equal(bindings.ReportSelect.computed.selectedOption.call(state), selectOptions.find(item => item.value === value))
	bindings.ReportSelect.methods.select.call(state, { value })
	bindings.ReportSelect.methods.select.call(state, null)
	bindings.ReportSelect.methods.select.call(state, { value: 'unknown' })
	state.disabled = true
	bindings.ReportSelect.methods.select.call(state, { value: 'year' })
	assert.equal(changes.length, 1, 'Same, cleared, unknown and disabled choices do not update the report')
}
// Drill-in navigation returns focus and never changes a filter merely by opening/backing out.
{
 const changes = [], focus = []
 const state = { options: selectOptions, groupKinds: [], modelValue: null, open: false,
  $emit: (event, value) => { changes.push([event, value]); if (event === 'update:open') state.open = value },
  $nextTick: callback => callback(),
  $refs: Object.fromEntries(['back', 'trigger'].map(key => [key, { $el: { focus: () => focus.push(key) } }])),
 }
 for (const [key, method] of Object.entries(bindings.PaymentFilterSelect.methods)) state[key] = method.bind(state)
 state.setOpen(true)
 assert.deepEqual(focus, ['back'])
 let prevented = 0, stopped = 0
 const escape = { preventDefault: () => prevented++, stopPropagation: () => stopped++ }
 state.onEscape(escape)
 assert.equal(state.open, false)
 assert.deepEqual(focus, ['back', 'trigger'])
 assert.equal(prevented, 1); assert.equal(stopped, 1)
 state.onEscapeKeyup(escape)
 assert.equal(stopped, 2, 'Native popover keyup must not dismiss the panel after going back')
 state.onEscape(escape)
 state.onEscapeKeyup(escape)
 assert.equal(stopped, 2, 'Escape from the main panel can still close its popover')
 assert.ok(changes.every(([event]) => event === 'update:open'))
 for (const value of [false, true, 12, 'year']) {
  state.select({ value })
  assert.deepEqual(changes.at(-2), ['update:modelValue', value])
  assert.deepEqual(changes.at(-1), ['update:open', false])
 }
 const beforeInvalid = changes.length
 state.select({ value: 'unknown' })
 assert.equal(changes.length, beforeInvalid)
}
// Multiple choices use checkbox toggles without dismissing their submenu.
{
 const changes = []
 const state = { groupKinds: [], options: [{ value: null, label: 'All' }, { value: 5, label: '#first' }, { value: 6, label: '#second' }],
  modelValue: [], multiple: true, label: 'Tags', open: true,
  $emit: (event, value) => { changes.push([event, value]); if (event === 'update:modelValue') state.modelValue = value },
  setOpen: () => assert.fail('Checkbox selection must leave its submenu open'),
 }
 const select = bindings.PaymentFilterSelect.methods.select.bind(state)
 select({ value: 5 }); select({ value: 6 })
 assert.deepEqual(state.modelValue, [5, 6])
 assert.equal(bindings.PaymentFilterSelect.computed.selectionLabel.call(state), 'Tags (2)')
 select({ value: 5 })
 assert.deepEqual(state.modelValue, [6])
 select({ value: null })
 assert.deepEqual(state.modelValue, [])
 assert.equal(bindings.PaymentFilterSelect.computed.selectionLabel.call(state), 'All')
 select({ value: 999 })
 assert.equal(changes.length, 4)
}
// Personal filters keep the same API values and report-return/reset semantics.
{
 const filters = component('src/components/TableFilters.vue')
 const changes = []
 const texts = new Proxy({}, { get: (_, key) => () => String(key) })
 const initial = { ...filters.props.initialFilters.default(), annualReturn: '/reports', status: 'settled', recurring: 'active', includeSubcategories: true, narrowCategories: '1,2', amountMin: '1200' }
 const state = {
  initialFilters: initial, showTimeRange: true, ...filters.data.call({ initialFilters: initial }),
  categories: [{ id: 12, name: 'Power', type: 'expense' }, { id: 13, name: 'Salary', type: 'income' }],
  paymentPartners: [{ id: 21, name: 'Supplier', type: 'expense' }],
  projects: [{ id: 9, name: 'Home' }], hashtags: [{ id: 5, name: 'tag' }],
  $texts: { filters: texts, annual: texts, labels: texts }, $enableImportantPayments: true,
  $emit: (event, value) => changes.push({ event, value }),
 }
 for (const [name, method] of Object.entries(filters.methods)) state[name] = method.bind(state)
 for (const [name, getter] of Object.entries(filters.computed)) Object.defineProperty(state, name, { get: () => getter.call(state) })
 for (const [key, values] of Object.entries({ categoryId: [12, 0, null], paymentPartnerId: [21, 0, null], projectId: [9, null], hashtagId: [5, null], hasAttachment: ['true', 'false', 'all'], hasReminder: ['true', 'all'], status: ['all', 'active', 'settled'] })) {
  for (const value of values) {
   state.setSelection(key, value)
   assert.equal(changes.at(-1).value[key], value, `Personal ${key} preserves its exact value and type`)
   assert.equal(changes.at(-1).event, 'update:filters')
  }
 }
 const originalCategories = state.categories
 state.categories = [
  { id: 3, name: 'Power', type: 'expense', icon: 'Flash', parent_category_id: 2 },
  { id: 1, name: 'Salary', type: 'income', icon: 'Briefcase' },
  { id: 2, name: 'Home', type: 'expense', icon: 'Home' },
  { id: 4, name: 'Insurance', type: 'expense', parent_category_id: 99, parent_name: 'Car' },
 ]
 assert.deepEqual(state.categoryIdChoices.map(option => option.value), [null, 0, 1, 2, 3, 4])
 const power = state.categoryIdChoices.find(option => option.value === 3)
 assert.equal(power.child, true)
 assert.equal(power.categoryIcon, 'Flash')
 assert.equal(power.accessibleLabel, 'Home › Power · expenses')
 assert.equal(state.categoryIdChoices.find(option => option.value === 1).heading, 'income')
 assert.equal(state.categoryIdChoices.find(option => option.value === 2).heading, 'expenses')
 assert.equal(state.categoryIdChoices.find(option => option.value === 4).context, 'Car')
 state.categories = originalCategories
 state.filterAvailability = { hasReminders: false, hasAttachments: false }
 assert.equal(state.showReminderFilter, false)
 assert.equal(state.showAttachmentFilter, false)
 state.filterAvailability = { hasReminders: true, hasAttachments: false }
 assert.equal(state.showReminderFilter, true)
 assert.equal(state.showAttachmentFilter, false)
 state.filterAvailability = { hasReminders: false, hasAttachments: true }
 assert.equal(state.showReminderFilter, false)
 assert.equal(state.showAttachmentFilter, true)
 state.filterAvailability = null
 state.localFilters.hasReminder = 'true'; state.localFilters.hasAttachment = 'false'
 assert.equal(state.showReminderFilter, true, 'Active filters remain removable even for empty periods')
 assert.equal(state.showAttachmentFilter, true, 'Without-receipt filters also remain removable')
 state.localFilters.hasReminder = 'all'; state.localFilters.hasAttachment = 'all'
 state.activeSubmenu = 'hasReminder'
 assert.equal(state.showReminderFilter, true, 'An open submenu keeps its Back action while availability reloads')
 state.activeSubmenu = null
 assert.equal(state.localFilters.includeSubcategories, false, 'Changing a category resets child inclusion')
 assert.equal(initial.includeSubcategories, true, 'Editing never mutates the incoming filter object')
 state.setSelection('type', 'expense')
 assert.deepEqual(state.categoryIdChoices.map(option => option.value), [null, 0, 12, 1, 2], 'Selected categories remain removable even when absent from the current catalog')
 assert.deepEqual(state.tagsChoices.map(option => option.value), [null, 'important'], 'Disabled label features stay hidden')
 // Category, partner and area checkbox changes round-trip without stale single predicates.
 state.localFilters.narrowCategories = ''
 state.projects.push({ id: 10, name: 'Office', color: '#123456' })
 for (const [key, multiKey, values] of [
  ['categoryId', 'narrowCategories', [12, 0]],
  ['paymentPartnerId', 'narrowPartners', [21, 0]],
  ['projectId', 'narrowProjects', [9, 10]],
 ]) {
  state.localFilters[key] = values[0]
  state.setIdSelection(key, values)
  assert.equal(state.localFilters[key], null)
  assert.equal(state.localFilters[multiKey], values.join(','))
 }
 state.localFilters.includeSubcategories = true
 state.setIdSelection('categoryId', [12, 0])
 assert.equal(state.localFilters.includeSubcategories, false, 'Manual category choices match only the selected categories')
 for (const scope of ['Personal', 'Project']) {
  const query = routes[`serialize${scope}EntryListRouteQuery`]({ filters: state.localFilters })
  const parsed = routes[`parse${scope}EntryListRouteQuery`](query).filters
  assert.equal(parsed.narrowCategories, '12,0')
  assert.equal(parsed.narrowPartners, '21,0')
  assert.equal(parsed.narrowProjects, '9,10')
  assert.equal(parsed.categoryId, null)
  assert.equal(parsed.paymentPartnerId, null)
  assert.equal(parsed.includeSubcategories, false)
  assert.equal(routes.entryNarrowingQueryParams(parsed).projects, '9,10')
 }
 state.setIdSelection('categoryId', [0])
 assert.equal(state.localFilters.categoryId, 0, 'Without-category remains distinct from all categories')
 assert.equal(state.localFilters.narrowCategories, '')
 assert.equal(state.localFilters.includeSubcategories, false)
 state.setIdSelection('paymentPartnerId', [21])
 assert.equal(state.localFilters.paymentPartnerId, 21)
 assert.equal(state.localFilters.narrowPartners, '')
 for (const values of [[0], [0, 9]]) {
  state.setIdSelection('projectId', values)
  assert.equal(state.localFilters.projectId, null, 'The zero sentinel never becomes a concrete area ID')
  assert.deepEqual(state.selectedProjects, values)
  const query = routes.serializePersonalEntryListRouteQuery({ filters: state.localFilters })
  const restored = routes.parsePersonalEntryListRouteQuery(query).filters
  assert.equal(restored.narrowProjects, values.join(','), 'Unassigned-only and mixed OR selections survive reload')
  assert.equal(routes.entryNarrowingQueryParams(restored).projects, values.join(','))
  assert.equal(state.projectIdChoices.find(option => option.value === 0).label, 'withoutArea')
 }
 assert.equal(routes.parsePersonalEntryListRouteQuery({ withoutArea: 'true' }).filters.narrowProjects, '0', 'Existing without-area deep links acquire the visible area filter')
 state.setIdSelection('projectId', [10])
 assert.equal(state.localFilters.projectId, 10)
 assert.equal(state.localFilters.narrowProjects, '')
 for (const key of ['categoryId', 'paymentPartnerId', 'projectId']) state.setIdSelection(key, [])
 assert.deepEqual(state.selectedCategories, [])
 assert.deepEqual(state.selectedPartners, [])
 assert.deepEqual(state.selectedProjects, [])
 state.localFilters.narrowProjects = '9,10'
 // Legacy sidebar/deep-link selections participate in checkbox OR selection.
 state.$enableReviewPayments = true
 state.localFilters.tags = 'important'
 assert.deepEqual(state.selectedLabels, ['important'])
 state.setLabels(['important', 'review'])
 assert.equal(state.localFilters.tags, 'all', 'Do not intersect the old single-label filter with the multi-selection')
 assert.equal(state.localFilters.narrowLabels, 'important,review')
 state.hashtags.push({ id: 6, name: 'second' })
 state.localFilters.hashtagId = 5
 assert.deepEqual(state.selectedHashtags, [5])
 state.setHashtags([5, 6])
 assert.equal(state.localFilters.hashtagId, null)
 assert.equal(state.localFilters.narrowHashtags, '5,6')
 for (const scope of ['Personal', 'Project']) {
  const query = routes[`serialize${scope}EntryListRouteQuery`]({ filters: state.localFilters })
  const parsed = routes[`parse${scope}EntryListRouteQuery`](query).filters
  assert.equal(parsed.narrowLabels, 'important,review')
  assert.equal(parsed.narrowHashtags, '5,6')
  assert.equal(parsed.tags, 'all')
  assert.equal(parsed.hashtagId, null)
  const params = routes.entryNarrowingQueryParams(parsed)
  assert.equal(params.labels, 'important,review')
  assert.equal(params.hashtags, '5,6')
 }
 state.setFutureOnly(true)
 assert.equal(state.localFilters.tags, 'future')
 assert.equal(state.localFilters.narrowLabels, 'important,review', 'Planned payments can retain multiple labels')
 state.setLabels(['review'])
 assert.equal(state.localFilters.tags, 'future')
 assert.equal(state.localFilters.narrowLabels, 'review')
 state.setFutureOnly(false)
 assert.equal(state.localFilters.tags, 'review')
 assert.equal(state.localFilters.narrowLabels, '')
 state.setHashtags([6])
 assert.equal(state.localFilters.hashtagId, 6)
 assert.equal(state.localFilters.narrowHashtags, '')
 state.setLabels([]); state.setHashtags([])
 assert.equal(state.localFilters.tags, 'all')
 assert.equal(state.localFilters.hashtagId, null)
 assert.equal(state.localFilters.narrowLabels, '')
 assert.equal(state.localFilters.narrowHashtags, '')
 assert.equal(state.localFilters.amountMin, '1200', 'Checkbox changes retain unrelated filters')
 const beforeInvalid = changes.length
 state.setSelection('categoryId', 13)
 state.setSelection('hasAttachment', false)
 assert.equal(changes.length, beforeInvalid, 'Unavailable options and boolean receipt values are rejected')
 state.localFilters.timeRange = 'custom'
 assert.ok(state.timeRangeChoices.some(option => option.value === 'custom'))
 state.setSelection('timeRange', 'lastYear')
 assert.ok(!state.timeRangeChoices.some(option => option.value === 'custom'))
 state.localFilters.search = 'Pow'
 state.debouncedEmit()
 state.localFilters.search = 'Power'
 state.debouncedEmit()
 const beforeSearch = changes.length
 await new Promise(resolve => setTimeout(resolve, 320))
 assert.equal(changes.length, beforeSearch + 1, 'Search emits once after its debounce')
 assert.equal(changes.at(-1).value.search, 'Power')
 assert.equal(changes.at(-1).value.amountMin, '1200', 'Searching preserves report amount restrictions')
 state.clearFilters()
 assert.equal(changes.at(-1).value.timeRange, 'all', 'Filters without a title picker still reset their period')
 state.showTimeRange = false
 state.localFilters = { ...filters.props.initialFilters.default(), timeRange: 'custom', dateFrom: 1000, dateTo: 2000 }
 assert.equal(state.hasActiveFilters, false, 'A title period alone does not activate the filter panel')
 state.localFilters.search = 'Power'
 assert.equal(state.hasActiveFilters, true)
 state.clearFilters()
 assert.equal(changes.at(-1).value.search, '')
 assert.equal(changes.at(-1).value.timeRange, 'custom', 'Reset keeps the title period')
 assert.equal(changes.at(-1).value.dateFrom, 1000)
 assert.equal(changes.at(-1).value.dateTo, 2000)
 assert.equal(changes.at(-1).value.annualReturn, '/reports')
 assert.equal(changes.at(-1).value.status, 'settled')
 assert.equal(changes.at(-1).value.recurring, 'active')
 assert.equal(changes.at(-1).value.narrowCategories, '')
 assert.equal(changes.at(-1).value.narrowProjects, '')
 assert.equal(changes.at(-1).value.amountMin, '')
 assert.equal(changes.at(-1).value.categoryId, null)
 state.debouncedEmit()
 const beforeUnmount = changes.length
 filters.beforeUnmount.call(state)
 await new Promise(resolve => setTimeout(resolve, 320))
 assert.equal(changes.length, beforeUnmount, 'Closing filters cancels pending search callbacks')
}
// An open filter must follow layout movement, not only window/popup resizes.
{
 const frames = new Map()
 let nextFrame = 0, recomputes = 0
 const script = parse(source('src/components/AnchoredPopover.vue')).descriptor.script.content.replace(/^import .*$/gm, '').replace('export default', 'return')
 const options = new Function('NcPopover', 'recomputeAllPoppers', 'requestAnimationFrame', 'cancelAnimationFrame', script)(
  {}, () => recomputes++, callback => { frames.set(++nextFrame, callback); return nextFrame }, id => frames.delete(id),
 )
 let rect = { x: 700, y: 50, width: 80, height: 34 }
 const state = { ...options.data(), $refs: { popover: { $el: { isConnected: true, getBoundingClientRect: () => rect } } } }
 for (const [key, fn] of Object.entries(options.methods)) state[key] = fn.bind(state)
 const tick = () => { const pending = [...frames.values()]; frames.clear(); pending.forEach(fn => fn()) }
 state.shown = true
 options.watch.shown.call(state, true)
 tick()
 assert.equal(recomputes, 1)
 tick()
 assert.equal(recomputes, 1, 'Stationary filters do not recompute their position every frame')
 rect = { ...rect, x: 900 }
 tick()
 assert.equal(recomputes, 2, 'Closing the payment sidebar moves the trigger without resizing it')
 rect = { ...rect, x: 700, y: 80 }
 tick()
 assert.equal(recomputes, 3, 'Reset/reopening and header movement update the native popup position')
 state.shown = false
 options.watch.shown.call(state, false)
 assert.equal(frames.size, 0, 'Closed filters do not run an animation-frame loop')
 state.shown = true
 options.watch.shown.call(state, true)
 tick()
 assert.equal(recomputes, 4, 'Reopening measures a fresh anchor')
 options.beforeUnmount.call(state)
 assert.equal(frames.size, 0, 'Navigating away cancels pending tracking')
}
Object.assign(bindings, hierarchy, {
	NcPopover: { setup: (_, { slots }) => () => Vue.h('div', [slots.trigger?.(), slots.default?.()]) },
	CloseIcon: slotComponent('span'),
	FilterVariantIcon: slotComponent('span'),
	MenuUpIcon: slotComponent('span'), MenuDownIcon: slotComponent('span'),
})
bindings.ReportAreaSelect = component('src/components/ReportAreaSelect.vue')
bindings.AnnualComparison = component('src/components/AnnualComparison.vue')
bindings.ReportSectionMenu = component('src/components/ReportSectionMenu.vue')
bindings.AnnualOverviewTable = component('src/components/AnnualOverviewTable.vue')
bindings.ReportDetailSection = component('src/components/ReportDetailSection.vue')
bindings.AnnualOverviewDetails = component('src/components/AnnualOverviewDetails.vue')
bindings.AnchoredPopover = bindings.NcPopover
bindings.AnnualOverviewFilters = component('src/components/AnnualOverviewFilters.vue')
// Report submenus retain multiple IDs, unrelated filters and parent context.
{
 const filters = bindings.AnnualOverviewFilters
 const events = [], focus = []
 const texts = new Proxy({}, { get: (_, key) => () => String(key) })
 const state = {
  activeSubmenu: null, consumedEscape: false, categoryIds: [0, 1, 2, 3],
  categories: [
   { id: 2, name: 'Power', type: 'expense', parent_category_id: 1, icon: 'Flash' },
   { id: 1, name: 'Home', type: 'expense', icon: 'Home' },
   { id: 3, name: 'Salary', type: 'income', icon: 'Briefcase' },
   { id: 4, name: 'Unused', type: 'expense' },
  ],
  normalized: annual.annualSelection({ grouping: 'labelsAndTags', categories: [0], partners: [7], search: 'bill', amountMin: 1234 }),
  $texts: { annual: texts }, $refs: { panel: { scrollTop: 123 }, back: { $el: { focus: () => focus.push('back') } }, categoriesTrigger: { $el: { focus: () => focus.push('categories') } } },
  $nextTick: callback => callback(),
  $emit: (event, value) => { events.push([event, value]); state.normalized = annual.annualSelection(value) },
 }
 for (const [name, fn] of Object.entries(filters.methods)) state[name] = fn.bind(state)
 state.scheduleFit = () => {}
 Object.defineProperty(state, 'categoryOptions', { get: () => filters.computed.categoryOptions.call(state) })
 state.toggleSection('categories')
 state.toggle('categories', 1); state.toggle('categories', 2)
 assert.equal(state.activeSubmenu, 'categories', 'Multi-selection keeps the submenu open')
 assert.deepEqual(state.normalized.categories, [0, 1, 2])
 assert.deepEqual(state.normalized.partners, [7])
 assert.equal(state.normalized.search, 'bill'); assert.equal(state.normalized.amountMin, 1234)
 state.toggle('categories', 1)
 assert.deepEqual(state.normalized.categories, [0, 2], 'Unchecking a parent does not silently clear its child')
 const power = state.categoryOptions.find(option => option.id === 2)
 assert.equal(power.icon, 'Flash'); assert.equal(power.child, true); assert.equal(power.pathLabel, 'Home › Power')
 const choices = filters.computed.categoryChoices.call(state)
 assert.equal(choices.find(option => option.value === 2).label, 'Power', 'The shared selector owns child indentation')
 assert.equal(choices.find(option => option.value === 2).categoryIcon, 'Flash')
 assert.equal(choices.find(option => option.value === 2).accessibleLabel, 'Home › Power · expenses')
 state.categoryIds = [0, 2, 3]
 assert.equal(state.categoryOptions.find(option => option.id === 2).context, 'Home', 'An unavailable parent is context only, not a new filter choice')
 assert.equal(state.categoryOptions.some(option => option.id === 1), false)
 let stopped = 0
 const escape = { preventDefault() {}, stopPropagation() { stopped++ } }
 state.onEscape(escape); state.onEscapeKeyup(escape)
 assert.equal(state.activeSubmenu, null); assert.equal(stopped, 2)
 assert.deepEqual(focus, ['back', 'categories'])
 assert.equal(events.length, 3, 'Navigation never emits a filter change')
 state.onEscape(escape); state.onEscapeKeyup(escape)
 assert.equal(stopped, 2, 'Escape from the root remains available to NcPopover')
}
// Native input refs wrap the actual input: retain unfinished drafts while focused.
{
	const methods = bindings.AnnualOverviewFilters.methods
	const previousDocument = globalThis.document
	const activeInput = {}
	globalThis.document = { activeElement: activeInput }
	const changes = []
	const context = {
		$refs: { amountMin: { $el: { contains: element => element === activeInput } } },
		normalized: annual.annualSelection({ year: 2026, partners: [2], amountMin: 1250 }),
		amountMinText: '12,', amountMaxText: '', disposed: false,
		$emit: (event, value) => changes.push({ event, value }),
	}
	try {
		methods.syncAmount.call(context, 'min')
		assert.equal(context.amountMinText, '12,', 'Focused native fields preserve unfinished decimal input')
		globalThis.document.activeElement = null
		methods.syncAmount.call(context, 'min')
		assert.equal(context.amountMinText, '12.50')
		context.amountMinText = '12,34'
		context.amountMaxText = '90,12'
		methods.applyAmount.call(context)
		assert.equal(changes.at(-1).value.amountMin, 1234)
		assert.equal(changes.at(-1).value.amountMax, 9012)
		assert.deepEqual(changes.at(-1).value.partners, [2], 'Amount edits retain other filters')
		context.amountMinText = 'invalid'
		methods.applyAmount.call(context)
		assert.equal(changes.length, 1, 'Invalid input does not change the applied filter')
		methods.toggle.call(context, 'partners', 5)
		assert.deepEqual(changes.at(-1).value.partners, [2, 5])
		assert.deepEqual(context.normalized.partners, [2], 'Checkbox changes do not mutate the selection prop')
	} finally {
		if (previousDocument === undefined) delete globalThis.document
		else globalThis.document = previousDocument
	}
}

bindings.AnnualOverviewChart = component('src/components/AnnualOverviewChart.vue')
bindings.ReportPeriodPicker = component('src/components/ReportPeriodPicker.vue')
bindings.ReportSummary = component('src/components/ReportSummary.vue')
Object.assign(bindings, new Function('axios', 'generateUrl', 'showToast', 'showRequestError', source('src/services/reportPayments.js').replace(/^import .*$/gm, '').replaceAll('export async function', 'async function') + '\nreturn { openReportPayment, loadReportPayments }')(bindings.axios, bindings.generateUrl, bindings.showToast, bindings.showRequestError))
// Render report payment variants through the same real table components as the overview.
Object.assign(bindings, await import(moduleUrl(source('src/utils/paymentColumnWidths.js'))), await import(moduleUrl(source('src/utils/areaSettlementStatus.js'))), {
 texts: {entry:{tableDate:()=> 'tableDate'}}, getAppLanguage:()=> 'de',
 formatMoney: (value,currency='EUR',options={})=>new Intl.NumberFormat('de-AT',{style:'currency',currency,...options}).format(value),
 formatSignedMoney:(value,currency='EUR')=>new Intl.NumberFormat('de-AT',{style:'currency',currency,signDisplay:'always'}).format(value),
 NcAvatar:slotComponent('span'), MemberIdentity:slotComponent('span'), MenuUpIcon:slotComponent('span'), MenuDownIcon:slotComponent('span'), HistoryIcon:slotComponent('span'), PencilIcon:slotComponent('span'), PlusIcon:slotComponent('span'), DeleteOutlineIcon:slotComponent('span'), AccountMultipleCheckIcon:slotComponent('span'), AccountMultipleIcon:slotComponent('span'), BellRingIcon:slotComponent('span'), CheckCircleOutlineIcon:slotComponent('span'), PaperclipIcon:slotComponent('span'), SyncIcon:slotComponent('span'),
})
bindings.PaymentLabelList=component('src/components/PaymentLabelList.vue')
bindings.EntryDescriptionCell=component('src/components/EntryDescriptionCell.vue')
bindings.EntryAmountCell=component('src/components/EntryAmountCell.vue')
bindings.EntryTable=component('src/components/EntryTable.vue')
const topPaymentsComponent = component('src/components/ReportTopPayments.vue')
bindings.ReportTopPayments = { ...topPaymentsComponent, watch: {} }
const paymentSectionComponent = component('src/components/ReportPaymentSection.vue')
bindings.ReportPaymentSection = paymentSectionComponent
bindings.ReportPayments = component('src/components/ReportPayments.vue')
const reportBreakdownComponent = component('src/components/ReportBreakdown.vue')
bindings.ReportBreakdown = { ...reportBreakdownComponent, watch: {} }
bindings.ReportComparisonPicker = component('src/components/ReportComparisonPicker.vue')
const reportForecastComponent = component('src/components/ReportForecast.vue')
bindings.ReportForecast = { render: () => null }
const developmentComponent = component('src/components/ReportDevelopment.vue')
bindings.ReportDevelopment = developmentComponent
bindings.ReportDetailView = component('src/components/ReportDetailView.vue')
const areaOverviewComponent = component('src/components/ReportAreaOverview.vue')
bindings.ReportAreaOverview = { render: () => null }
const areaDetailComponent = component('src/components/ReportAreaDetailView.vue')
bindings.ReportAreaDetailView = { ...areaDetailComponent, watch: {} }
bindings.ReportPeriodPicker.components.NcPopover = { props: ['shown'], setup: (props, { slots }) => () => Vue.h('div', [slots.trigger?.(), props.shown ? slots.default?.() : null]) }
const table = bindings.AnnualOverviewTable
const sortRows = [
	{ key: 'z', label: 'Zebra', total: 20, children: [{ name: 'Zoo', total: 1 }, { name: 'Äpfel', total: 5 }, { name: 'Direct', total: 0, direct: true }] },
	{ key: 'a', label: 'Äpfel', total: 100 },
	{ key: 'b', label: 'Bücher', total: 20 },
]
const originalRows = JSON.stringify(sortRows)
const sortState = { ...table.data(), rows: sortRows, summary: false }
const sorted = () => table.computed.sortedRows.call(sortState)
assert.deepEqual(sorted().map(row => row.key), ['a', 'b', 'z'], 'Default title sorting uses the display labels and app locale')
table.methods.toggleSort.call(sortState, 'title')
assert.deepEqual(sorted().map(row => row.key), ['z', 'b', 'a'], 'Clicking title reverses alphabetical order')
table.methods.toggleSort.call(sortState, 'amount')
assert.deepEqual(sorted().map(row => row.key), ['a', 'b', 'z'], 'Amount sorts numerically, largest first, with alphabetical ties')
assert.deepEqual(sorted().find(row => row.key === 'z').children.map(row => row.name), ['Direct', 'Äpfel', 'Zoo'], 'Children sort within their parent; direct payments stay first')
table.methods.toggleSort.call(sortState, 'amount')
assert.deepEqual(sorted().map(row => row.key), ['b', 'z', 'a'], 'Clicking amount reverses numeric order')
table.methods.toggleSort.call(sortState, 'title')
assert.deepEqual(sorted().map(row => row.key), ['a', 'b', 'z'], 'Returning to title starts at A–Z')
assert.equal(JSON.stringify(sortRows), originalRows, 'Sorting never mutates the report or child arrays')
sortState.summary = true
table.methods.toggleSort.call(sortState, 'amount')
assert.equal(sortState.sortBy, 'title', 'Summary sorting is disabled')
assert.deepEqual(sorted().map(row => row.key), ['z', 'a', 'b'], 'Summary row order is preserved')
assert.equal(table.methods.isMonthEmpty.call({ summary: false }, { counts: [0] }, 0, 0), true)
assert.equal(table.methods.isMonthEmpty.call({ summary: false }, { counts: [2] }, 0, 0), false, 'Months with countervailing entries are not empty')
assert.equal(table.methods.isMonthEmpty.call({ summary: false }, { counts: [0] }, 12, 0), false, 'Year total is never marked as an empty month')
assert.equal(table.methods.isMonthEmpty.call({ summary: true }, { counts: [0], type: 'income' }, 0, 0), true, 'Summary income rows use dashes for empty months')
assert.equal(table.methods.isMonthEmpty.call({ summary: true }, { counts: [0], type: 'expense' }, 0, 0), true, 'Summary expense rows use dashes for empty months')
assert.equal(table.methods.isMonthEmpty.call({ summary: true }, { counts: [0], type: 'all' }, 0, 0), false, 'Balance row keeps numeric amounts')
assert.equal(table.methods.cellAmount.call({ summary: false, isMonthEmpty: table.methods.isMonthEmpty, formatAmount: () => '0.00' }, { counts: [0] }, 0, 0), '–')
assert.equal(table.methods.cellAmount.call({ summary: false, isMonthEmpty: table.methods.isMonthEmpty, formatAmount: () => '0.00' }, { counts: [2] }, 0, 0), '0.00')
assert.equal(table.methods.cellAmount.call({ summary: false, isMonthEmpty: table.methods.isMonthEmpty, formatAmount: () => '10.00' }, { counts: [1] }, 0, 1000), '10.00')
const view = component('src/views/AnnualOverviewView.vue')
{
	const previousWindow = globalThis.window
	const previousObserver = globalThis.ResizeObserver
	let direction = 'ltr', reducedMotion = false, labelWidth = 180
	const observers = [], moves = []
	globalThis.window = { getComputedStyle: () => ({ direction }), matchMedia: () => ({ matches: reducedMotion }), removeEventListener() {} }
	globalThis.ResizeObserver = class {
		constructor(callback) { this.callback = callback; this.targets = []; observers.push(this) }
		observe(target) { this.targets.push(target) }
		disconnect() { this.disconnected = true }
	}
	try {
		const cells = [() => labelWidth, () => 108, () => 108].map(width => ({ getBoundingClientRect: () => ({ width: width() }) }))
		const scroller = { clientWidth: 936, scrollWidth: 1584, scrollLeft: 0, firstElementChild: {}, querySelector: () => ({ cells }) }
		const state = { $refs: { printSource: { $el: scroller } }, scrollState: {}, reportScrollObserver: null, reportScrollDisposed: false, requestId: 0, filterChoiceRequest: 0 }
		for (const [name, method] of Object.entries(view.methods)) state[name] = method.bind(state)
		scroller.scrollBy = options => { moves.push(options); scroller.scrollLeft = Math.max(0, Math.min(scroller.scrollWidth - scroller.clientWidth, scroller.scrollLeft + options.left)); state.updateReportScroll() }
		state.observeReportScroll()
		assert.deepEqual(observers[0].targets, [scroller, scroller.firstElementChild], 'Viewport and table-content changes both refresh the controls')
		assert.deepEqual(state.scrollState, { overflow: true, canScrollLeft: false, canScrollRight: true })
		const navigation = view.computed.scrollNavigation.call(state)
		const controlsApp = Vue.createSSRApp(bindings.ReportScrollControls, { navigation })
		controlsApp.config.globalProperties.$texts = { annual: { scrollTables: () => 'Scroll tables', scrollTablesLeft: () => 'Scroll left', scrollTablesRight: () => 'Scroll right' } }
		const controls = await renderToString(controlsApp)
		assert.match(controls, /<button(?=[^>]*aria-label="Scroll left")(?=[^>]*disabled)[^>]*>/, 'The starting edge has a disabled, named Left action')
		assert.match(controls, /aria-controls="cobudget-report-periods"/, 'Every section controls the same scroll region')
		navigation.scroll(1)
		assert.deepEqual(moves.at(-1), { left: 540, behavior: 'smooth' }, 'Page steps exclude both pinned columns and retain one period for context')
		assert.deepEqual(state.scrollState, { overflow: true, canScrollLeft: true, canScrollRight: true })
		navigation.scroll(1)
		assert.equal(scroller.scrollLeft, 648)
		assert.equal(state.scrollState.canScrollRight, false, 'The final edge disables Right for every section')
		scroller.clientWidth = 390; scroller.scrollLeft = 0; labelWidth = 140; reducedMotion = true
		state.scrollReportColumns(1)
		assert.deepEqual(moves.at(-1), { left: 108, behavior: 'auto' }, 'Narrow screens move at least one period and respect reduced motion')
		direction = 'rtl'; scroller.scrollLeft = 0; state.updateReportScroll()
		assert.equal(state.scrollState.canScrollLeft, true)
		assert.equal(state.scrollState.canScrollRight, false)
		scroller.scrollLeft = -(scroller.scrollWidth - scroller.clientWidth); state.updateReportScroll()
		assert.equal(state.scrollState.canScrollLeft, false, 'RTL boundaries use the native negative scroll offset')
		assert.equal(state.scrollState.canScrollRight, true)
		direction = 'ltr'; scroller.clientWidth = 1600; observers[0].callback()
		assert.deepEqual(state.scrollState, { overflow: false, canScrollLeft: false, canScrollRight: false })
		assert.doesNotMatch(await renderToString(Vue.createSSRApp(bindings.ReportScrollControls, { navigation: view.computed.scrollNavigation.call(state) })), /<(?:div|button)\b/, 'Controls disappear when the periods fit')
		scroller.clientWidth = 0; observers[0].callback()
		assert.equal(state.scrollState.overflow, false, 'Hidden overview content has no active navigation')
		state.$refs.printSource = null; state.observeReportScroll()
		assert.equal(observers[0].disconnected, true, 'Replacing a report releases the old observer')
		state.$refs.printSource = { $el: scroller }; state.observeReportScroll()
		view.beforeUnmount.call(state)
		assert.equal(observers.at(-1).disconnected, true)
		const observerCount = observers.length
		state.observeReportScroll()
		assert.equal(observers.length, observerCount, 'A queued update cannot attach observers after leaving the page')

		const yearPeriods = [
			{ dateFrom: 1767222000, dateTo: 1769900399 },
			{ dateFrom: 1769900400, dateTo: 1772319599 },
			{ dateFrom: 1772319600, dateTo: 1774994399 },
			{ dateFrom: 1774994400, dateTo: 1777586399 },
			{ dateFrom: 1777586400, dateTo: 1780264799 },
			{ dateFrom: 1780264800, dateTo: 1782856799 },
			{ dateFrom: 1782856800, dateTo: 1785535199 },
			{ dateFrom: 1785535200, dateTo: 1788213599 },
			{ dateFrom: 1788213600, dateTo: 1790805599 },
			{ dateFrom: 1790805600, dateTo: 1793487599 },
			{ dateFrom: 1793487600, dateTo: 1796079599 },
			{ dateFrom: 1796079600, dateTo: 1798757999 },
			{ dateFrom: 1767222000, dateTo: 1798757999 },
		]
		const monthCells = [180, ...Array(12).fill(108), 108].map(width => ({ getBoundingClientRect: () => ({ width }) }))
		const centerScroller = { clientWidth: 936, scrollWidth: 1584, scrollLeft: 0, querySelector: () => ({ cells: monthCells }) }
		const centerState = { $refs: { printSource: { $el: centerScroller } }, report: { periods: yearPeriods }, scrollState: {}, reportScrollDisposed: false, initialPeriodCentered: false }
		for (const [name, method] of Object.entries(view.methods)) centerState[name] = method.bind(centerState)

		centerState.scrollToCurrentPeriod({ now: 1792000000 })
		assert.equal(centerScroller.scrollLeft, 648, 'Centering October reaches maximum horizontal scroll so late months are visible')
		assert.equal(centerState.initialPeriodCentered, true)

		centerState.initialPeriodCentered = false
		centerScroller.scrollLeft = 0
		centerState.scrollToCurrentPeriod({ now: 1781000000 })
		assert.equal(centerScroller.scrollLeft, 270, 'June is symmetrically centered in the visible unpinned period window')

		centerState.initialPeriodCentered = false
		centerState.scrollToCurrentPeriod({ now: 1768000000 })
		assert.equal(centerScroller.scrollLeft, 0, 'January remains aligned at the start')

		direction = 'rtl'
		centerState.initialPeriodCentered = false
		centerScroller.scrollLeft = 0
		centerState.scrollToCurrentPeriod({ now: 1781000000 })
		assert.equal(centerScroller.scrollLeft, -270, 'RTL centers with a negative scroll offset')
		direction = 'ltr'

		centerState.initialPeriodCentered = false
		centerScroller.scrollLeft = 100
		centerState.scrollToCurrentPeriod({ now: 1700000000 })
		assert.equal(centerScroller.scrollLeft, 100, 'A date outside the report periods leaves the scroll position unchanged')

		const drag = bindings.DraggableScroller
		const captures = []
		const dragElement = { clientWidth: 400, scrollWidth: 1400, scrollLeft: 0, setPointerCapture: id => captures.push(id), releasePointerCapture() {}, scrollBy: options => moves.push(options) }
		const dragState = { ...drag.data(), allowLinkDrag: true, interactiveDragSelector: '.annual-amount', nativeTouch: true, $refs: { scroller: dragElement } }
		for (const [name, method] of Object.entries(drag.methods)) dragState[name] = method.bind(dragState)
		const target = (allowed = true, ignored = false) => ({ closest: selector => selector === '[data-drag-scroll-ignore]' ? (ignored ? {} : null) : ({ matches: () => allowed }) })
		const pointer = (clientX, extra = {}) => ({ clientX, pointerId: 1, pointerType: 'mouse', button: 0, target: target(), preventDefault() {}, ...extra })
		dragState.onPointerDown(pointer(300))
		dragState.onPointerMove(pointer(297))
		assert.equal(dragElement.scrollLeft, 0, 'Small click jitter does not move a financial cell')
		assert.equal(captures.length, 0, 'Ordinary amount clicks retain their native target')
		dragState.endDrag()
		let cancelled = false
		const click = { detail: 1, preventDefault() { cancelled = true }, stopPropagation() {} }
		dragState.onClickCapture(click)
		assert.equal(cancelled, false, 'An ordinary amount click still opens its detail')
		dragState.onPointerDown(pointer(300)); dragState.onPointerMove(pointer(150)); dragState.endDrag()
		assert.equal(dragElement.scrollLeft, 150)
		assert.deepEqual(captures, [1])
		dragState.onClickCapture(click)
		assert.equal(cancelled, true, 'Dragging over an amount must never open its detail')
		cancelled = false
		dragState.onPointerDown(pointer(300)); dragState.onPointerMove(pointer(200)); dragState.endDrag()
		dragState.onClickCapture({ ...click, detail: 0 })
		assert.equal(cancelled, false, 'Keyboard activation still works after a drag')
		dragState.onPointerDown(pointer(300, { target: target(false) })); dragState.onClickCapture(click)
		assert.equal(dragState.isDragging, false, 'Section menus and arrow buttons are not drag handles')
		assert.equal(cancelled, false, 'The next independent click is not swallowed')
		dragState.onPointerDown(pointer(300, { pointerType: 'touch' }))
		assert.equal(dragState.isDragging, false, 'Native touch scrolling remains available in both axes')
		dragState.onPointerDown(pointer(300, { target: target(true, true) }))
		assert.equal(dragState.isDragging, false, 'Nested Top 10 scrollers do not move the shared period tables')
		const beforeKeys = moves.length
		dragState.onKeydown({ key: 'ArrowRight', target: {}, currentTarget: dragElement, preventDefault() {} })
		assert.equal(moves.length, beforeKeys, 'Arrow keys on child tabs or controls keep their own behavior')
		dragState.onKeydown({ key: 'ArrowRight', target: dragElement, currentTarget: dragElement, preventDefault() {} })
		assert.deepEqual(moves.at(-1), { left: 280, behavior: 'auto' })
	} finally {
		globalThis.window = previousWindow
		if (previousObserver === undefined) delete globalThis.ResizeObserver
		else globalThis.ResizeObserver = previousObserver
	}
	console.log('Report scrolling passed: shared arrows, pinned-column steps, resize/RTL bounds, observer cleanup, reduced motion, safe amount clicks, mouse dragging and native touch.')
}
const yearState = { selection: { year: currentYear - 1, includeFuturePayments: true }, yearOptions: [currentYear + 1, currentYear], $router: { replace: () => Promise.resolve() }, updateSelection: view.methods.updateSelection }
const yearEvent = value => String(value)
view.methods.selectYear.call(yearState, yearEvent(currentYear))
assert.equal(yearState.selection.includeFuturePayments, false, 'Changing to the current year defaults to payments up to today')
view.methods.selectYear.call(yearState, yearEvent(currentYear + 1))
assert.equal(yearState.selection.includeFuturePayments, true, 'Switching to a future year includes its entered payments')
view.methods.selectYear.call(yearState, yearEvent(1969))
assert.equal(yearState.selection.year, currentYear + 1, 'Years without entries cannot replace the selection')
view.methods.updateSelection.call(yearState, yearEvent('categories'))
const periodState = {
	selection: {},
	$enableProjects: true,
	$router: { replace(route) { periodState.route = route; return Promise.resolve() } },
	updateSelection: view.methods.updateSelection,
}
for (const year of [2019, currentYear, currentYear + 1]) {
	periodState.selection = annual.annualSelection({ year, grouping: 'paymentPartners', projectId: 12, categories: '7', hashtags: '9' })
	await view.methods.selectPeriod.call(periodState, 'day')
	assert.equal(periodState.selection.year, year, 'Choosing Days retains the selected year')
	assert.equal(periodState.selection.month, 1, 'Choosing Days starts in January for past, current and future years')
	assert.equal(periodState.route.query.period, 'day')
	assert.equal(periodState.route.query.month, '1')
	assert.equal(periodState.route.query.projectId, '12', 'The financial view survives a breakdown change')
	assert.equal(periodState.route.query.categories, '7', 'Payment filters survive a breakdown change')
	assert.equal(periodState.route.query.hashtags, '9')
}
periodState.selection = annual.annualSelection({ year: 2019, period: 'day', month: 3 })
await view.methods.selectMonth.call(periodState, 'all')
assert.equal(periodState.selection.period, 'day', 'All days stays on the daily breakdown')
assert.equal(periodState.selection.month, 0)
assert.equal(periodState.route.query.month, 'all', 'All days survives the next route normalization')
assert.equal(annual.annualSelection(periodState.route.query).month, 0)
await view.methods.selectMonth.call(periodState, '10')
assert.equal(periodState.selection.month, 10, 'A different month replaces All days')
assert.equal(periodState.route.query.month, '10')
await view.methods.selectPeriod.call(periodState, 'day')
assert.equal(periodState.selection.month, 10, 'Reselecting the active daily breakdown keeps the chosen month')
for (const period of ['year', 'quarter', 'week']) {
	await view.methods.selectPeriod.call(periodState, period)
	assert.equal(periodState.selection.year, 2019, 'Changing breakdown keeps the selected year')
	assert.equal(periodState.selection.period, period)
	assert.equal(periodState.route.query.month, undefined, 'Whole-year breakdowns have no month restriction')
	assert.equal(periodState.route.query.period, period === 'year' ? undefined : period)
	await view.methods.selectPeriod.call(periodState, 'day')
	assert.equal(periodState.selection.month, 1, 'Returning to Days starts in January')
}
const dayYearState = {
	selection: annual.annualSelection({ year: 2019, period: 'day', month: 'all' }),
	yearOptions: [2019, 2020],
	$router: { replace(route) { dayYearState.route = route; return Promise.resolve() } },
	updateSelection: view.methods.updateSelection,
}
view.methods.selectYear.call(dayYearState, '2020')
assert.equal(dayYearState.selection.year, 2020, 'Changing the year keeps the daily breakdown')
assert.equal(dayYearState.selection.period, 'day')
assert.equal(dayYearState.route.query.month, 'all', 'Changing the year keeps an explicit All days choice')
dayYearState.selection = annual.annualSelection({ year: 2019, period: 'day', month: 2 })
view.methods.selectYear.call(dayYearState, '2020')
assert.equal(dayYearState.route.query.month, '2', 'Changing the year keeps the selected month, including leap-year February')
assert.deepEqual(view.computed.yearOptions.call({ report: { years: [currentYear, currentYear - 2] } }), [currentYear, currentYear - 2], 'Year dropdown lists only reported years, without filling gaps')
assert.deepEqual(view.computed.yearOptions.call({ report: null }), [], 'No invented years while data is unavailable')
const selection = { year: currentYear, grouping: 'categories', projectId: null, includeFuturePayments: false }
const amounts = { months: [303, ...Array(11).fill(0)], total: 303 }
const report = {
	years: [currentYear + 1, currentYear, currentYear - 2],
	groups: { income: [], expense: [{ ...amounts, id: 1, name: 'Home & family', children: [{ ...amounts, id: 2, name: 'Rent' }] }] },
	totals: { income: { months: Array(12).fill(0), total: 0 }, expense: amounts, balance: { months: [-303, ...Array(11).fill(0)], total: -303 } },
	periods: Array.from({ length: 13 }, () => ({ dateFrom: 1767222000, dateTo: 1769900399 })),
}
const printState = {
	selection, currentYear, report, loading: false, error: false, printing: false,
	scopeLabel: 'Haushalt & Familie', reportScopeLabel: 'Haushalt & Familie', $refs: { printSource: { fixture: true } },
	$texts: { annual: { title: () => 'Jahresübersicht', categories: () => 'Kategorien', partners: () => 'Zahlungsempfänger', labelsAndTags: () => 'Labels & Tags', includeFuture: () => 'Zukünftige Zahlungen einbeziehen', paymentsUntilToday: () => 'Zahlungen bis heute' } },
	get groupingLabel() {
		if (this.selection.grouping === 'paymentPartners') return this.$texts.annual.partners()
		if (this.selection.grouping === 'labelsAndTags') return this.$texts.annual.labelsAndTags()
		return this.$texts.annual.categories()
	},
}
await view.methods.printReport.call(printState)
assert.equal(printState.printing, false, 'Print action becomes available again after preparation')
assert.equal(printCalls[0][0], printState.$refs.printSource, 'Print uses the displayed tables, preserving sorting and expanded rows')
assert.equal(printCalls[0][1].title, `CoBudget – Jahresübersicht ${currentYear}`)
assert.deepEqual(printCalls[0][1].details.slice(0, 2), ['Haushalt & Familie', 'Kategorien'])
assert.ok(printCalls[0][1].details[2].startsWith('Zahlungen bis heute ('), 'Current cutoff is dated in the print header')
assert.equal(printCalls[0][1].months.length, 12)
assert.equal(printCalls[0][1].groups, undefined, 'The yearly print stays one block')
printState.selection = { ...selection, grouping: 'paymentPartners', includeFuturePayments: true }
await view.methods.printReport.call(printState)
assert.deepEqual(printCalls[1][1].details, ['Haushalt & Familie', 'Zahlungsempfänger', 'Zukünftige Zahlungen einbeziehen'])
printState.selection = { ...selection, year: currentYear - 1 }
await view.methods.printReport.call(printState)
assert.equal(printCalls[2][1].details.length, 2, 'Past years have no current-year cutoff in the print header')
printState.selection = { ...selection, year: currentYear - 1, period: 'quarter' }
printState.$texts.annual.quarterlyTitle = () => 'Quartalsübersicht'
printState.$texts.annual.quarter = number => `${number}. Quartal`
await view.methods.printReport.call(printState)
assert.equal(printCalls[3][1].title, `CoBudget – Quartalsübersicht ${currentYear - 1}`)
assert.deepEqual(printCalls[3][1].months, ['1. Quartal', '2. Quartal', '3. Quartal', '4. Quartal'], 'The quarterly print lists the four quarters')
assert.equal(printCalls[3][1].details.length, 2, 'A past year has no cutoff in the print header')
printState.selection = { ...selection, year: currentYear - 1, period: 'week' }
printState.$texts.annual.weeklyTitle = () => 'Wochenübersicht'
printState.$texts.annual.week = number => `KW ${number}`
await view.methods.printReport.call(printState)
assert.equal(printCalls[4][1].title, `CoBudget – Wochenübersicht ${currentYear - 1}`)
assert.equal(printCalls[4][1].months.length, annual.isoWeekCount(currentYear - 1), 'The weekly print lists every calendar week')
assert.equal(printCalls[4][1].months[0], 'KW 1')
assert.equal(printCalls[4][1].months.at(-1), `KW ${annual.isoWeekCount(currentYear - 1)}`)
assert.equal(printCalls[4][1].details.length, 2, 'A past week-year has no cutoff in the print header')
printState.selection = { ...selection, year: currentYear - 1, period: 'day', month: 0 }
printState.$texts.annual.dailyTitle = () => 'Tagesübersicht'
await view.methods.printReport.call(printState)
assert.equal(printCalls[5][1].title, `CoBudget – Tagesübersicht ${currentYear - 1}`)
assert.equal(printCalls[5][1].months.length, annual.calendarDayCount(currentYear - 1), 'The daily print lists every calendar day')
assert.equal(printCalls[5][1].months[0], new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(currentYear - 1, 0, 1)))
assert.equal(printCalls[5][1].months.at(-1), new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(currentYear - 1, 11, 31)))
assert.equal(printCalls[5][1].details.length, 2, 'A past year has no cutoff in the print header')
const dayGroups = printCalls[5][1].groups
const shortDate = (year, month, day) => new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(year, month, day))
const longMonth = (year, month) => new Intl.DateTimeFormat('de-AT', { month: 'long' }).format(new Date(year, month, 1))
assert.equal(dayGroups.length, 12, 'The whole-year daily print groups days by calendar month')
assert.equal(dayGroups[0].columns.length, 31)
assert.equal(dayGroups[1].columns.length, new Date(currentYear - 1, 2, 0).getDate())
assert.equal(dayGroups.reduce((sum, group) => sum + group.columns.length, 0), printCalls[5][1].months.length)
assert.equal(dayGroups[0].columns[0], printCalls[5][1].months[0])
assert.equal(dayGroups[0].label, longMonth(currentYear - 1, 0))
assert.equal(dayGroups[1].columns[0], printCalls[5][1].months[31])
assert.equal(dayGroups[1].columns[0], shortDate(currentYear - 1, 1, 1))
printState.selection = { ...selection, year: currentYear - 1, period: 'day', month: 2 }
await view.methods.printReport.call(printState)
const februaryDays = new Date(currentYear - 1, 2, 0).getDate()
const februaryLabel = new Intl.DateTimeFormat('de-AT', { month: 'long' }).format(new Date(currentYear - 1, 1, 1))
assert.equal(printCalls[6][1].title, `CoBudget – Tagesübersicht ${februaryLabel} ${currentYear - 1}`)
assert.equal(printCalls[6][1].months.length, februaryDays, 'A chosen month lists only the days of that month')
assert.equal(printCalls[6][1].months[0], new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(currentYear - 1, 1, 1)))
assert.equal(printCalls[6][1].months.at(-1), new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(currentYear - 1, 1, februaryDays)))
assert.equal(printCalls[6][1].details.length, 2, 'A past month has no cutoff in the print header')
assert.equal(printCalls[6][1].groups, undefined, 'A chosen month uses the flat day list')
printState.selection = { ...selection, year: currentYear - 1, period: 'month', month: 10 }
printState.$texts.annual.monthlyTitle = () => 'Monatsübersicht'
await view.methods.printReport.call(printState)
assert.equal(printCalls[7][1].title, `CoBudget – Monatsübersicht ${new Intl.DateTimeFormat('de-AT', { month: 'long' }).format(new Date(currentYear - 1, 9, 1))} ${currentYear - 1}`)
assert.equal(printCalls[7][1].months.length, 31, 'The monthly print lists every day of the selected month')
assert.equal(printCalls[7][1].months[1], new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(currentYear - 1, 9, 2)), 'Monthly print headings use the short date')
assert.equal(printCalls[7][1].months.at(-1), new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(currentYear - 1, 9, 31)))
assert.equal(printCalls[7][1].details.length, 2, 'A past month has no cutoff in the print header')
printState.loading = true
await view.methods.printReport.call(printState)
assert.equal(printCalls.length, 8, 'Loading data cannot produce a stale report')
const csv = annual.buildAnnualOverviewCsv({
	labelHeading: 'Kategorie',
	columns: ['Jänner', 'Februar', 'Gesamt'],
	sections: [{
		title: 'Ausgaben',
		rows: [
			{ label: '=Miete', amounts: [1250, 0, 1250], counts: [1, 0] },
			{ label: 'Haus; "Alt"', amounts: [0, 0, 0], counts: [0, 0] },
			{ label: 'Saldo', amounts: [-100, 250, 150], counts: [1, 1], balance: true },
		],
	}],
})
assert.equal(csv.charCodeAt(0), 0xfeff, 'The CSV starts with a UTF-8 BOM for Excel')
assert.equal(csv.includes('Kategorie;Jänner;Februar;Gesamt'), true)
assert.equal(csv.includes("'=Miete;12,50;;12,50"), true, 'An empty month stays blank and a formula label is disarmed')
assert.equal(csv.includes('"Haus; ""Alt""";'), true, 'Separators and quotes stay inside one cell')
assert.equal(csv.includes('Saldo;-1,00;2,50;1,50'), true, 'Balance keeps its sign and a decimal comma')
const exportState = {
	selection: { ...selection, period: 'year' },
	currentYear,
	report,
	loading: false,
	error: false,
	exporting: false,
	$enableIncomes: true,
	columns: ['Jänner', 'Gesamt'],
	paymentTypes: ['expense'],
	hasPayments: true,
	firstHeading: 'Kategorie',
	$texts: {
		annual: { income: () => 'Einnahmen', expenses: () => 'Ausgaben' },
		common: { mainCategory: () => 'Hauptkategorie', exportCsvCreated: () => 'CSV exportiert', exportCsvError: () => 'Fehler' },
		entry: { groupTotal: label => `Gesamt ${label}` },
		analytics: { total: () => 'Gesamt' },
	},
	get totalRows() {
		return [{ ...report.totals.expense, key: 'expense', type: 'expense', label: 'Ausgaben' }]
	},
	groupRows: view.methods.groupRows,
	runExport: view.methods.runExport,
	exportMetadata: () => [],
	$refs: {
		development: { exportData: () => [] },
		paymentSections: [{ section: 'expense', exportData: () => [exportHelpers.reportAmountExportSection({ title: 'Ausgaben', firstHeading: 'Kategorie', columns: ['Jänner', 'Gesamt'], rows: view.methods.groupRows.call({ report, selection }, 'expense'), texts: { common: { mainCategory: () => 'Hauptkategorie' } } })] }],
	},
	annualExportFilename: view.methods.annualExportFilename,
	annualExportSections: view.methods.annualExportSections,
}
await view.methods.exportReport.call(exportState)
assert.equal(exportState.exporting, false, 'The export action becomes available again')
assert.equal(csvDownloads.at(-1).filename, `cobudget-uebersicht-${currentYear}.csv`)
assert.equal(csvDownloads.at(-1).contents.includes('Home & family'), true, 'The export uses the loaded overview rows')
assert.equal(csvDownloads.at(-1).contents.includes('3,03'), true, 'Overview cents become a decimal comma')
assert.equal(annual.nextcloudFilePath('/Dokumente/', `cobudget-uebersicht-${currentYear}.csv`), `/Dokumente/cobudget-uebersicht-${currentYear}.csv`)
assert.equal(annual.nextcloudFilePath('/', 'a.csv'), '/a.csv')
assert.equal(annual.nextcloudFilePath('/A/../../etc', 'a/b.csv'), '/A/etc/ab.csv', 'Folder traversal and a slash in the file name stay inside the chosen folder')
assert.equal(annual.encodeNextcloudPath('/Meine Dateien/Ä.csv'), '/Meine%20Dateien/%C3%84.csv')
assert.equal(annual.nextcloudCopyName(`cobudget-uebersicht-${currentYear}.csv`, 0), `cobudget-uebersicht-${currentYear}.csv`)
assert.equal(annual.nextcloudCopyName(`cobudget-uebersicht-${currentYear}.csv`, 1), `cobudget-uebersicht-${currentYear}-1.csv`)
assert.equal(annual.nextcloudCopyName(`cobudget-uebersicht-${currentYear}.csv`, 3), `cobudget-uebersicht-${currentYear}-3.csv`)
assert.equal(annual.nextcloudCopyName('readme', 2), 'readme-2')
const takenNames = new Set()
let nextcloudPutStatus = 0
bindings.axios.put = async (url, body, config) => {
	savedFiles.push({ url, body, type: config.headers['Content-Type'], match: config.headers['If-None-Match'] })
	if (nextcloudPutStatus >= 400 && nextcloudPutStatus !== 412) {
		const error = new Error('failed')
		error.response = { status: nextcloudPutStatus }
		throw error
	}
	const name = decodeURIComponent(url.split('/').pop())
	if (takenNames.has(name)) {
		const error = new Error('exists')
		error.response = { status: 412 }
		throw error
	}
}
const saveState = {
	...exportState,
	exporting: false,
	$texts: {
		...exportState.$texts,
		annual: {
			...exportState.$texts.annual,
			saveInNextcloud: () => 'In Nextcloud speichern',
			saveHere: () => 'Hier speichern',
			savedInNextcloud: label => `In Nextcloud gespeichert als ${label}`,
			saveInNextcloudError: () => 'Die Übersicht konnte nicht in Nextcloud gespeichert werden.',
		},
	},
}
const baseName = `cobudget-uebersicht-${currentYear}.csv`
await view.methods.saveReportInNextcloud.call(saveState)
assert.equal(saveState.exporting, false, 'Saving in Nextcloud becomes available again')
assert.equal(savedFiles.length, 1)
assert.equal(savedFiles[0].url, `https://cloud.example.test/remote.php/dav/files/ada/Dokumente/${baseName}`)
assert.equal(savedFiles[0].match, '*', 'An existing file is left unchanged')
assert.equal(savedFiles[0].type, 'text/csv; charset=utf-8')
assert.equal(savedFiles[0].body.includes('Home & family'), true, 'The Nextcloud file uses the same overview rows')
assert.equal(savedFiles[0].body.charCodeAt(0), 0xfeff)
assert.equal(exportToasts.at(-1), `In Nextcloud gespeichert als ${baseName}`)
takenNames.add(baseName)
takenNames.add(`cobudget-uebersicht-${currentYear}-1.csv`)
takenNames.add(`cobudget-uebersicht-${currentYear}-2.csv`)
await view.methods.saveReportInNextcloud.call(saveState)
assert.equal(savedFiles.at(-1).url.endsWith(`/${baseName.replace('.csv', '-3.csv')}`), true, 'Taken names continue at the next free number')
assert.equal(exportToasts.at(-1), `In Nextcloud gespeichert als ${baseName.replace('.csv', '-3.csv')}`)
const savedCount = savedFiles.length
const toastCount = exportToasts.length
nextcloudPutStatus = 500
await view.methods.saveReportInNextcloud.call(saveState)
assert.equal(savedFiles.length, savedCount + 1, 'A storage error stops instead of trying another name')
assert.equal(exportToasts.length, toastCount, 'A storage error creates no success message')
assert.equal(exportErrors.at(-1), 'Die Übersicht konnte nicht in Nextcloud gespeichert werden.')
nextcloudPutStatus = 0
const storedCount = savedFiles.length
const errorCount = exportErrors.length
nextcloudPicker.error = Object.assign(new Error('closed'), { name: 'FilePickerClosed' })
await view.methods.saveReportInNextcloud.call(saveState)
assert.equal(savedFiles.length, storedCount, 'Closing the folder picker stores nothing')
assert.equal(exportErrors.length, errorCount, 'Closing the folder picker is not an error')
nextcloudPicker.error = null
nextcloudUser.uid = ''
await view.methods.saveReportInNextcloud.call(saveState)
assert.equal(savedFiles.length, storedCount, 'A missing Nextcloud user stores nothing')
assert.equal(exportErrors.at(-1), 'Die Übersicht konnte nicht in Nextcloud gespeichert werden.')
nextcloudUser.uid = 'ada'
assert.equal(printCalls[3][1].groups, undefined, 'The quarterly print stays one block')
assert.equal(printCalls[4][1].groups, undefined, 'The weekly print stays one flat list')
assert.equal(printCalls[7][1].groups, undefined, 'The monthly print stays one flat list')
const numbered = length => Array.from({ length }, (_, index) => String(index + 1))
const yearPieces = planAnnualPrintPieces([{ columns: numbered(12) }])
assert.equal(yearPieces.length, 1)
assert.equal(yearPieces[0].start, 0)
assert.equal(yearPieces[0].note, '')
assert.equal(yearPieces[0].columns.length, 12)
assert.equal(planAnnualPrintPieces([{ columns: ['Q1', 'Q2', 'Q3', 'Q4'] }])[0].note, '')
const monthPieces = planAnnualPrintPieces([{ columns: numbered(31) }])
assert.deepEqual(monthPieces.map(piece => piece.columns.length), [12, 12, 7])
assert.deepEqual(monthPieces.map(piece => piece.start), [0, 12, 24])
assert.equal(monthPieces[0].note, '1 – 12')
assert.equal(monthPieces[2].note, '25 – 31')
assert.deepEqual(planAnnualPrintPieces([{ columns: numbered(28) }]).map(piece => piece.columns.length), [12, 12, 4])
assert.equal(planAnnualPrintPieces([{ columns: numbered(29) }]).at(-1).columns.length, 5)
assert.equal(planAnnualPrintPieces([{ columns: numbered(30) }]).at(-1).columns.length, 6)
const weekLabels = length => Array.from({ length }, (_, index) => `KW ${index + 1}`)
const weeks53 = planAnnualPrintPieces([{ columns: weekLabels(53) }])
assert.equal(weeks53.length, 5)
assert.equal(weeks53[0].note, 'KW 1 – KW 12')
assert.equal(weeks53.at(-1).columns.length, 5)
assert.equal(weeks53.at(-1).start, 48)
assert.equal(weeks53.at(-1).note, 'KW 49 – KW 53')
const weeks52 = planAnnualPrintPieces([{ columns: weekLabels(52) }])
assert.equal(weeks52.length, 5)
assert.equal(weeks52.at(-1).columns.length, 4)
assert.equal(weeks52.at(-1).note, 'KW 49 – KW 52')
const january = numbered(31).map(day => `${day}. Jän.`)
const february = numbered(28).map(day => `${day}. Feb.`)
const grouped = planAnnualPrintPieces([{ label: 'Jänner', columns: january }, { label: 'Februar', columns: february }])
assert.equal(grouped.length, 6)
assert.equal(grouped[0].start, 0)
assert.equal(grouped[0].note, `Jänner · ${january[0]} – ${january[11]}`)
assert.equal(grouped[3].start, 31, 'February starts after the 31 days of January')
assert.equal(grouped[3].columns.length, 12)
assert.equal(grouped[3].note, `Februar · ${february[0]} – ${february[11]}`)
assert.equal(grouped[5].start, 55)
assert.equal(grouped[5].columns.length, 4)
assert.equal(grouped[5].note, `Februar · ${february[24]} – ${february[27]}`)
assert.deepEqual(annualPrintCellIndexes(14, 0, 12), [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13])
assert.deepEqual(annualPrintCellIndexes(33, 24, 7), [0, 25, 26, 27, 28, 29, 30, 31, 32])
for (const [years, selectedYear, expectedYear] of [
	[[currentYear + 2, currentYear, currentYear - 2], currentYear, currentYear],
	[[currentYear + 2, currentYear - 2, currentYear - 4], currentYear, currentYear - 2],
	[[currentYear + 3, currentYear + 1], currentYear, currentYear + 1],
	[[], currentYear, currentYear],
]) {
	let replacements = 0
	bindings.axios.get = async url => ({ data: url.endsWith('/projects') ? [] : { ...report, years } })
	const state = { selection: { ...selection, year: selectedYear }, currentYear, requestId: 0, $enableProjects: true, $router: { replace: async () => { replacements++ } }, updateSelection: view.methods.updateSelection }
	await view.methods.load.call(state)
	assert.equal(state.selection.year, expectedYear, 'Scope changes choose an available year, preferring the latest past/current year')
	assert.equal(replacements, selectedYear === expectedYear ? 0 : 1, 'Empty scopes do not cause redirect loops')
	assert.equal(state.error, false)
}
function headingText(markup) { return (markup.match(/<h2[^>]*>(.*?)<\/h2>/s)?.[1] || '').replace(/<[^>]*>/g, '') }
function mainFilterMarkup(markup) { return markup.slice(markup.indexOf('annual-main-filter'), markup.indexOf('</header>')) }
function reportControlCount(markup) {
	const controls = markup.slice(markup.indexOf('</h2>'), markup.indexOf('class="report-development'))
	return (controls.match(/class="report-select /g) || []).length + Number(controls.includes('annual-shortcut--areas'))
}
function createReportApp(selection, reportData = report, { incomes = true, projects = true, areas = [{ id: 7, name: 'Area' }], chartHiddenSeries = [], loading = false, error = false, query = {} } = {}) {
	const app = Vue.createSSRApp({ ...view, data: () => ({ ...view.data.call({ $route: { query } }), ...reportDetails.areaOverviewOptions(query), selection: annual.annualSelection(selection), dateDraft: { dateFrom: selection.dateFrom, dateTo: selection.dateTo }, chartMode: 'cumulative', chartHiddenSeries, projects: areas, categoryChoices: [{ id: 1, name: 'Groceries', type: 'expense' }, { id: 2, name: 'Rent', parent_category_id: 1, type: 'expense' }, { id: 3, name: 'Salary', type: 'income' }, { id: 9, name: 'Archive', type: 'expense' }], partnerChoices: [{ id: 4, name: 'Amazon', type: 'expense' }, { id: 6, name: 'Client', type: 'income' }, { id: 8, name: 'OldShop', type: 'expense' }], hashtagChoices: [{ id: 9, displayName: 'Alpen' }], filterChoices: { categoryIds: [1, 2, 3, 0], partnerIds: [4, 6, 0], labels: ['important', 'childRelated'] }, loading, error, detail: null, report: reportData, printing: false, exporting: false, requestId: 0, filterChoiceRequest: 0 }) })
	app.component('RouterLink', { props: ['to'], setup: (props, { slots }) => () => Vue.h('a', { href: JSON.stringify(props.to) }, slots.default?.()) })
	app.config.globalProperties.$texts = new Proxy({}, { get: () => new Proxy({}, { get: (_, key) => (...args) => args.length ? args.join(', ') : key }) })
	app.config.globalProperties.$route = { query }
	app.config.globalProperties.$enableProjects = projects
	app.config.globalProperties.$enableIncomes = incomes
	app.config.globalProperties.$currency = 'EUR'
	for (const flag of ['$enableImportantPayments', '$enableReviewPayments', '$enableFixedCosts', '$enableChildRelated', '$enableSubscriptions', '$enableTaxRelevant', '$enableInsurance']) app.config.globalProperties[flag] = true
	app.config.globalProperties.$formatMoneyFromCents = cents => (cents / 100).toFixed(2)
	return app
}
const emptyAreaMarkup = await renderToString(createReportApp(selection, report, { areas: [] }))
assert.equal(reportControlCount(emptyAreaMarkup), 4, 'An empty area catalog hides the area shortcut')
assert.equal(reportControlCount(await renderToString(createReportApp(selection, report, { areas: [{ id: 7, name: 'Archive', is_archived: true }] }))), 4, 'Archived-only areas hide the area shortcut')
const markup = await renderToString(createReportApp(selection))
assert.ok(markup.indexOf('class="annual-controls"') < markup.indexOf('class="annual-chart'), 'The chart follows the report controls')
assert.ok(markup.indexOf('class="annual-chart') < markup.indexOf('id="cobudget-report-periods"'), 'The chart precedes the report tables')
assert.equal((markup.match(/class="annual-chart-line"/g) || []).length, 3, 'The annual view shows all enabled financial series')
const focusedChartMarkup = await renderToString(createReportApp(selection, report, { chartHiddenSeries: ['income', 'balance'] }))
assert.equal((focusedChartMarkup.match(/class="annual-chart-line"/g) || []).length, 1, 'The annual view passes its retained curve selection into the chart')
assert.equal((focusedChartMarkup.match(/<table /g) || []).length, 3, 'Hiding chart curves does not hide or change the report tables')

for (const incomes of [false, true]) {
	for (const projects of [false, true]) {
		const settingsMarkup = await renderToString(createReportApp(selection, report, { incomes, projects }))
		assert.equal((settingsMarkup.match(/<table /g) || []).length, incomes ? 3 : 1, 'Disabled incomes leave only the expense table and its total')
		assert.equal((settingsMarkup.match(/class="annual-chart-line"/g) || []).length, incomes ? 3 : 1, 'Chart series follow the income feature setting')
		assert.equal((settingsMarkup.match(/class="report-summary-item"/g) || []).length, incomes ? 3 : 1, 'The summary hides income and balance when income management is disabled')
		assert.equal((settingsMarkup.match(/annual-section-menu|report-payment-menu/g) || []).length, incomes ? 3 : 1, 'Income, expenses and the total have their own section menus')
		assert.equal((mainFilterMarkup(settingsMarkup).match(/class="annual-filter-kind(?:\s|")/g) || []).length, 0, 'Payment partners use grouped switches instead of per-partner badges')
		assert.equal(reportControlCount(settingsMarkup), projects ? 5 : 4, 'Disabled areas remove the scope dropdown')
		const links = [...settingsMarkup.matchAll(/href="([^"]+)"/g)].map(match => JSON.parse(match[1].replaceAll('&quot;', '"').replaceAll('&amp;', '&')))
		assert.ok(links.length > 0)
		if (!incomes) assert.ok(links.every(link => link.query.detailType === 'expense'), 'No hidden income or balance drilldowns remain')
	}
}
const disabledAreaRequests = []
const disabledAreaReplacements = []
bindings.axios.get = async (url, options) => {
	disabledAreaRequests.push({ url, params: { ...options?.params } })
	return { data: report }
}
const disabledAreaState = {
	selection: { ...selection, projectId: 12 }, currentYear, requestId: 0, $enableProjects: false,
	$router: { replace: async route => { disabledAreaReplacements.push(route) } }, updateSelection: view.methods.updateSelection,
}
await view.methods.load.call(disabledAreaState)
assert.equal(disabledAreaState.error, false)
assert.equal(disabledAreaRequests.length, 1, 'Disabled areas never fetch the area list')
assert.equal(disabledAreaRequests[0].params.projectId, null, 'An old area URL loads personal figures when areas are disabled')
assert.equal(disabledAreaReplacements[0].query.projectId, undefined, 'The stale area filter is removed from the URL')
assert.equal(annual.annualPaymentRoute(disabledAreaState.selection).name, 'payments', 'Drilldowns also use personal payments after scope reset')
const previousCatalogGet = bindings.axios.get
const catalogRequests = []
bindings.axios.get = async (url, options) => {
	const projectId = options?.params?.projectId ?? null
	catalogRequests.push({ url: String(url), projectId })
	if (String(url).includes('categories')) return { data: projectId === 12 ? [{ id: 30, name: 'Area bonus', type: 'income' }] : [{ id: 1, name: 'Groceries', type: 'expense' }] }
	if (String(url).includes('payment-partners')) return { data: projectId === 12 ? [{ id: 40, name: 'Area client', type: 'income' }] : [{ id: 4, name: 'Amazon', type: 'expense' }] }
	return { data: [] }
}
const catalogState = {
	selection: { projectId: 12, entryScope: 'shared' }, filterChoiceRequest: 0,
	categoryChoices: [{ id: 1, name: 'Groceries', type: 'expense' }],
	partnerChoices: [{ id: 4, name: 'Amazon', type: 'expense' }],
	hashtagChoices: [{ id: 9, displayName: 'Alpen' }],
	filterChoices: { categoryIds: [1], partnerIds: [4], labels: ['important'] },
	loadFilterChoices: view.methods.loadFilterChoices,
}
await view.watch.filterCatalogKey.call(catalogState)
assert.deepEqual(catalogState.categoryChoices, [{ id: 30, name: 'Area bonus', type: 'income' }], 'An area loads its own categories')
assert.deepEqual(catalogState.partnerChoices, [{ id: 40, name: 'Area client', type: 'income' }], 'An area loads its own payment partners')
assert.deepEqual(catalogState.filterChoices.categoryIds, [], 'The previous scope does not keep its filter choices')
assert.equal(catalogRequests.filter(request => request.url.includes('categories')).every(request => request.projectId === 12), true, 'Area catalogs are requested with the area id')
catalogState.selection = { projectId: null }
await view.watch.filterCatalogKey.call(catalogState)
assert.deepEqual(catalogState.categoryChoices, [{ id: 1, name: 'Groceries', type: 'expense' }], 'Meine Finanzen loads the personal categories again')
assert.equal(catalogRequests.at(-2).projectId, null, 'Personal catalogs are requested without an area id')
catalogState.selection = { projectId: 12, entryScope: 'personal' }
await view.watch.filterCatalogKey.call(catalogState)
assert.deepEqual(catalogState.categoryChoices.map(item => item.id), [30, 1], 'A personal area report can name original area and independently edited personal categories')
assert.deepEqual(catalogState.partnerChoices.map(item => item.id), [40, 4], 'A personal area report also includes the member’s own payment partners')
bindings.axios.get = previousCatalogGet
const emptyReport = { ...report, groups: { income: [], expense: [] } }
const emptyMarkup = await renderToString(createReportApp(selection, emptyReport))
assert.equal((emptyMarkup.match(/<table /g) || []).length, 2, 'No overall Total section when both payment groups are empty')
assert.equal((emptyMarkup.match(/class="annual-empty-message"/g) || []).length, 2, 'Both empty tables have a dedicated centered message')
assert.equal(view.computed.hasPayments.call({ report: { groups: { income: [{ total: 0 }], expense: [] } } }), true, 'Actual payments with zero totals still show the Total section')
assert.equal(view.computed.hasPayments.call({ selection: { grouping: 'labelsAndTags' }, report: { groups: { income: [], expense: [] }, totals: { income: { totalCount: 0 }, expense: { totalCount: 2 } } } }), false, 'Payments without labels or tags hide the Total section')
assert.equal(view.computed.hasPayments.call({ selection: { grouping: 'categories' }, report: { groups: { income: [], expense: [] }, totals: { expense: { totalCount: 2 } } } }), false, 'Empty category groups still hide the total section')
const labelEmpty = await renderToString(createReportApp({ ...selection, grouping: 'labelsAndTags' }, emptyReport))
assert.equal((labelEmpty.match(/<table /g) || []).length, 2, 'Empty labels and tags hide the overall Total section')
assert.equal((labelEmpty.match(/noLabelsOrTags/g) || []).length, 2, 'Empty label tables say that no labels or tags exist')
assert.ok(markup.includes('>labelsAndTags</button>'), 'Grouping offers labels and tags')
assert.ok(markup.includes('filterPayments'), 'The filter control stays at the end of the toolbar')
assert.equal(markup.includes('annual-filter-heading">category'), false, 'Category grouping does not offer another category filter')
assert.equal(markup.includes('annual-filter-heading">paymentPartner'), true, 'Payment partners remain available while grouping by category')
assert.equal(markup.includes('annual-filter-heading">labels'), true, 'Labels remain available while grouping by category')
const labelFilterMarkup = await renderToString(createReportApp({ ...selection, grouping: 'labelsAndTags', hashtags: [9] }, emptyReport))
assert.equal(labelFilterMarkup.includes('annual-filter-heading">labels'), false, 'Label grouping does not offer another label filter')
assert.equal(labelFilterMarkup.includes('annual-filter-heading">category'), true, 'Categories remain available while grouping by labels and tags')
assert.equal(labelFilterMarkup.includes('annual-filter-heading">tags'), true, 'Description tags remain available while grouping by labels and tags')
async function renderFilterSection(section, selection = {}, incomes = true) {
 const filters = bindings.AnnualOverviewFilters
 const app = Vue.createSSRApp({ ...filters, data() { return { ...filters.data.call(this), activeSubmenu: section } } }, {
  section, selection: annual.annualSelection(selection), categoryIds: [0, 1, 2, 3],
  categories: [{ id: 1, name: 'Home', type: 'expense', icon: 'Home' }, { id: 2, name: 'Power', type: 'expense', parent_category_id: 1, icon: 'Flash' }, { id: 3, name: 'Salary', type: 'income' }, { id: 9, name: 'Archive', type: 'expense' }],
  partnerIds: [0, 4, 6],
  partners: [{ id: 4, name: 'Amazon', type: 'expense' }, { id: 6, name: 'Client', type: 'income' }, { id: 8, name: 'OldShop', type: 'expense' }],
 })
 Object.assign(app.config.globalProperties, createReportApp(selection, report, { incomes }).config.globalProperties)
 return renderToString(app)
}
const categoryFilter = await renderFilterSection('categories', { grouping: 'paymentPartners' })
const categoryOrder = ['noCategory', 'allIncomeCategories', 'allExpenseCategories'].map(name => categoryFilter.indexOf(name))
assert.ok(categoryOrder.every((position, index) => position > (categoryOrder[index - 1] ?? -1)), 'Report category groups follow Payments order')
assert.equal(categoryFilter.includes('Power'), false, 'Individual categories are collapsed by default')
const selectedCategoryFilter = await renderFilterSection('categories', { grouping: 'paymentPartners', categories: [2] })
assert.ok(selectedCategoryFilter.includes('Home') && selectedCategoryFilter.includes('Power'), 'Opening expands the selected category group with parent context')
assert.ok(selectedCategoryFilter.includes('filter-choice-child'), 'Children use the shared Payments indentation')
assert.equal(selectedCategoryFilter.includes('Archive'), false, 'Unused categories remain hidden')
const expenseCategoryFilter = await renderFilterSection('categories', { grouping: 'paymentPartners' }, false)
assert.equal(expenseCategoryFilter.includes('allIncomeCategories'), false, 'Disabled incomes hide their category group')
assert.ok(expenseCategoryFilter.includes('allExpenseCategories'))
assert.equal(expenseCategoryFilter.includes('Salary'), false, 'Disabled income categories cannot fall through as individual choices')
{
 const original = annual.annualSelection({ grouping: 'labelsAndTags', categories: [0, 2, 'income', 'income', 'invalid'], partners: [4], search: 'rent', amountMin: 1250 })
 assert.deepEqual(original.categories, [0, 2, 'income'])
 assert.deepEqual(annual.annualSelection(annual.annualRouteQuery(original)), original, 'Category IDs and groups survive bookmarks')
 assert.equal(annual.annualRequestParams(original).categories, '0,2,income')
 assert.equal(annual.annualPaymentRoute(original).query.categories, '0,2,income')
 const groupOnly = annual.annualPaymentRoute({ ...original, categories: ['expense'] })
 assert.equal(groupOnly.query.categoryId, undefined, 'Category groups never become scalar IDs')
 assert.equal(groupOnly.query.categories, 'expense')
 assert.equal(annual.annualFilterChips({ ...original, categories: ['income'] }, { categoryGroupLabels: { income: 'All income categories' } }).find(chip => chip.key === 'categories').label, 'All income categories')
 assert.deepEqual(annual.annualSelection({ ...original, grouping: 'categories' }).categories, [], 'Category grouping still clears its own filter')
 let emitted
 bindings.AnnualOverviewFilters.methods.setSelection.call({ normalized: original, $emit: (_, value) => { emitted = value }, scheduleFit() {} }, 'categories', ['expense'])
 assert.deepEqual(emitted.categories, ['expense'])
 assert.deepEqual(emitted.partners, [4]); assert.equal(emitted.search, 'rent'); assert.equal(emitted.amountMin, 1250)
}
const partnerFilter = await renderFilterSection('partners')
const partnerOrder = ['noPartner', 'allIncomePartners', 'allExpensePartners'].map(name => partnerFilter.indexOf(name))
assert.ok(partnerOrder.every((position, index) => position > (partnerOrder[index - 1] ?? -1)), 'Report partner groups follow the Payments order')
assert.equal(partnerFilter.includes('Amazon'), false, 'Individual partners stay collapsed initially')
const selectedPartnerFilter = await renderFilterSection('partners', { partners: [4] })
assert.ok(selectedPartnerFilter.includes('Amazon'), 'The selected partner group expands on opening')
assert.equal(selectedPartnerFilter.includes('OldShop'), false, 'Unused partners stay hidden')
const reportLabels = await renderFilterSection('labels')
assert.ok(reportLabels.indexOf('allLabels') < reportLabels.indexOf('important'), 'All labels is the first default choice')
assert.ok(reportLabels.includes('subscription'), 'All enabled labels remain selectable, like Payments')
{
 const filters = bindings.AnnualOverviewFilters
 const original = annual.annualSelection({ partners: [4], labels: ['important'], search: 'rent', amountMin: 1250 })
 let emitted
 filters.methods.setSelection.call({ normalized: original, $emit: (_, value) => { emitted = value }, scheduleFit() {} }, 'labels', [])
 assert.deepEqual(emitted.labels, [], 'All labels clears label restrictions')
 assert.deepEqual(emitted.partners, [4], 'All labels retains the partner and other filters')
 assert.equal(emitted.amountMin, 1250)
 const selected = annual.annualSelection({ ...original, partners: [0, 4, 'income', 'income', 'invalid'] })
 assert.deepEqual(selected.partners, [0, 4, 'income'])
 assert.deepEqual(annual.annualSelection(annual.annualRouteQuery(selected)), selected, 'Mixed partner groups and IDs survive bookmarks')
 assert.equal(annual.annualRequestParams(selected).partners, '0,4,income')
 assert.equal(annual.annualPaymentRoute(selected).query.partners, '0,4,income')
 const groupOnly = annual.annualPaymentRoute({ ...selected, partners: ['expense'] })
 assert.equal(groupOnly.query.paymentPartnerId, undefined, 'Group tokens never become scalar partner IDs')
 assert.equal(groupOnly.query.partners, 'expense')
 assert.equal(annual.annualFilterChips({ ...selected, partners: ['income'] }, { partnerGroupLabels: { income: 'All income partners' } }).find(chip => chip.key === 'partners').label, 'All income partners')
 assert.deepEqual(annual.annualSelection({ ...selected, grouping: 'paymentPartners' }).partners, [], 'Partner grouping still clears its own filter')
}
assert.ok(labelFilterMarkup.includes('>Alpen</span>') || labelFilterMarkup.includes('>#Alpen</span>'), 'Tags used in the year stay selectable')
const chipMarkup = await renderToString(createReportApp(annual.annualSelection({ year: currentYear, grouping: 'categories', partners: '2,5', labels: 'important,childRelated' })))
assert.ok(chipMarkup.includes('2 or 5'), 'Several values of one filter are shown together')
assert.ok(chipMarkup.includes('important or children'))
assert.equal(chipMarkup.includes('anyOfThese'), false, 'A multi-value filter does not add an extra hint')
assert.ok(chipMarkup.includes('native-filter-chip'))
assert.equal(reportControlCount(markup), 5, 'Current year keeps the title, year, scope, grouping and cutoff dropdowns')
const breakdownOptions = markup.match(/<div(?=[^>]*class="report-select )(?=[^>]*aria-label="breakdown")[^>]*>(.*?)<\/div>/s)?.[1]
assert.deepEqual([...breakdownOptions.matchAll(/<button[^>]*>(.*?)<\/button>/g)].map(match => match[1]), ['quarters', 'months', 'weeks', 'days'], 'The breakdown runs from quarters through months and weeks to days')
assert.ok(headingText(markup).startsWith('title'), 'Annual overview remains the page title')
assert.equal(breakdownOptions.includes('>month</button>'), false, 'The duplicate monthly overview is absent')
assert.equal(markup.includes('>Februar</button>'), false, 'The year overview has no month dropdown')
assert.equal(markup.includes('>allDays</button>'), false, 'The year overview has no All choice')
assert.ok(markup.includes('paymentsUntilToday'), 'Payment cutoff is visible in the toolbar')
assert.ok(!markup.includes('annual-hint'), 'Explanatory paragraphs are removed')
assert.ok(!markup.includes('annual-year-buttons'), 'Year navigation arrows are removed')
assert.ok(!markup.includes('>other</button>'), 'Arbitrary year selection is removed')
assert.ok(!markup.includes(`>${currentYear - 1}</button>`), 'Empty years are not offered')
assert.equal(reportControlCount((await renderToString(createReportApp({ ...selection, year: currentYear - 1 })))), 4, 'Past years hide the cutoff dropdown')
assert.equal(reportControlCount((await renderToString(createReportApp({ ...selection, year: currentYear + 1 })))), 4, 'Future years hide the cutoff dropdown')
const viewedMonth = new Date().getMonth() + 1
const monthMarkup = await renderToString(createReportApp({ ...selection, period: 'month', month: viewedMonth }))
assert.equal(reportControlCount(monthMarkup), 6, 'The current month adds the month dropdown beside the year and keeps the cutoff')
assert.ok(headingText(monthMarkup).startsWith('title') && monthMarkup.includes('>Februar<'), 'February is offered with the other months of the selected year')
assert.ok(monthMarkup.includes('>allDays</button>') && monthMarkup.indexOf('>allDays</button>') < monthMarkup.indexOf('>Februar<'), 'All days is the first month choice and retains the daily breakdown')
assert.ok(monthMarkup.includes(new Intl.DateTimeFormat('de-AT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(currentYear, viewedMonth - 1, 1))), 'Day headings expose the weekday and full date')
const headingMarkup = html => html.replace(/(<th\b[^>]*>)([\s\S]*?)(<\/th>)/g, (_, open, content, close) => open + content.replace(/<[^>]*>/g, '') + close)
const monthDayHeading = day => new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(currentYear, viewedMonth - 1, day))
const monthHeadings = [...monthMarkup.matchAll(/<thead[^>]*>([\s\S]*?)<\/thead>/g)].flatMap(head => [...head[1].matchAll(/<th\b[^>]*>([\s\S]*?)<\/th>/g)].map(cell => cell[1].replace(/<[^>]*>/g, '').trim()))
const viewedMonthLength = new Date(currentYear, viewedMonth, 0).getDate()
assert.equal(monthHeadings.filter(text => text === monthDayHeading(1)).length, 3, 'Income, expenses and the total list the short date of day 1')
assert.equal(monthHeadings.filter(text => text === monthDayHeading(viewedMonthLength)).length, 3, 'Income, expenses and the total list the short date of the last day')
const pastMonthMarkup = await renderToString(createReportApp({ ...selection, period: 'month', month: 1 }))
assert.equal(reportControlCount(pastMonthMarkup), new Date().getMonth() === 0 ? 6 : 5, 'A past month hides payments up to today')
if (new Date().getMonth() < 11) {
	const laterMonthMarkup = await renderToString(createReportApp({ ...selection, period: 'month', month: new Date().getMonth() + 2 }))
	assert.equal(reportControlCount(laterMonthMarkup), 5, 'A future month hides the cutoff dropdown')
}
const quarterMarkup = await renderToString(createReportApp({ ...selection, period: 'quarter' }))
assert.equal(reportControlCount(quarterMarkup), 5, 'The current year keeps the cutoff and adds no quarter dropdown')
assert.ok(headingText(quarterMarkup).startsWith('title'), 'Quarters retains the annual page title')
assert.equal(quarterMarkup.includes('aria-label="quarterLabel"'), false, 'The quarterly overview has no quarter dropdown')
assert.equal(quarterMarkup.includes('aria-label="month"'), false, 'The month dropdown stays hidden in the quarterly overview')
assert.equal(quarterMarkup.includes('>allDays</button>'), false, 'The quarterly overview has no All choice')
assert.equal((headingMarkup(quarterMarkup).match(/<th\b[^>]*>\s*1\D/g) || []).length, 3, 'Income, expenses and the total list the first quarter')
assert.equal(quarterMarkup.includes('Jänner'), false, 'The quarterly overview lists quarters')
const pastQuarterMarkup = await renderToString(createReportApp({ ...selection, year: currentYear - 1, period: 'quarter' }))
assert.equal(reportControlCount(pastQuarterMarkup), 4, 'A past year hides payments up to today')
const futureQuarterMarkup = await renderToString(createReportApp({ ...selection, year: currentYear + 1, period: 'quarter' }))
assert.equal(reportControlCount(futureQuarterMarkup), 4, 'A future year hides the cutoff dropdown')
const weekCountForYear = annual.isoWeekCount(currentYear)
const weekMarkup = await renderToString(createReportApp({ ...selection, period: 'week' }))
assert.equal(reportControlCount(weekMarkup), 5, 'The current week-year keeps the cutoff and adds no week dropdown')
assert.ok(headingText(weekMarkup).startsWith('title'), 'Weeks retains the annual page title')
assert.equal(weekMarkup.includes('aria-label="month"'), false, 'The month dropdown stays hidden in the weekly overview')
assert.equal(weekMarkup.includes('>allDays</button>'), false, 'The weekly overview has no All choice')
assert.equal((headingMarkup(weekMarkup).match(new RegExp(`<th\\b[^>]*>\\s*${weekCountForYear}\\D`, 'g')) || []).length, 3, 'Income, expenses and the total list the last calendar week')
assert.equal(weekMarkup.includes('Jänner'), false, 'The weekly overview lists calendar weeks')
const pastWeekMarkup = await renderToString(createReportApp({ ...selection, year: currentYear - 1, period: 'week' }))
assert.equal(reportControlCount(pastWeekMarkup), 4, 'A past week-year hides payments up to today')
const futureWeekMarkup = await renderToString(createReportApp({ ...selection, year: currentYear + 1, period: 'week' }))
assert.equal(reportControlCount(futureWeekMarkup), 4, 'A future week-year hides the cutoff dropdown')
const escapePattern = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
const dayHeading = (year, month, day) => new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(year, month, day))
const defaultDayMarkup = await renderToString(createReportApp({ ...selection, period: 'day' }))
assert.ok(headingText(defaultDayMarkup).startsWith('title'), 'The default daily breakdown keeps the annual page title')
assert.equal((headingMarkup(defaultDayMarkup).match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 0, 31))}`, 'g')) || []).length, 3, 'The default daily report includes all 31 January days')
assert.equal((headingMarkup(defaultDayMarkup).match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 1, 1))}`, 'g')) || []).length, 0, 'The default daily report does not include February')
const dayMarkup = await renderToString(createReportApp({ ...selection, period: 'day', month: 'all' }))
assert.equal(reportControlCount(dayMarkup), 6, 'The current daily overview adds the month dropdown and keeps the cutoff')
assert.ok(headingText(dayMarkup).startsWith('title'), 'Days retains the annual page title')
assert.ok(dayMarkup.includes('aria-label="month"'), 'The daily overview shows the month dropdown beside the year')
assert.ok(dayMarkup.includes('>allDays</button>'), 'The whole-year choice is explicitly labelled All days')
assert.equal((headingMarkup(dayMarkup).match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 0, 1))}`, 'g')) || []).length, 3, 'Income, expenses and the total list 1 January')
assert.equal((headingMarkup(dayMarkup).match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 11, 31))}`, 'g')) || []).length, 3, 'Income, expenses and the total list 31 December')
assert.ok(dayMarkup.includes(new Intl.DateTimeFormat('de-AT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(currentYear, 0, 1))), 'Day headings expose the weekday and full date')
const currentDayMonthMarkup = await renderToString(createReportApp({ ...selection, period: 'day', month: viewedMonth }))
assert.equal(reportControlCount(currentDayMonthMarkup), 6, 'The current month of the daily overview keeps the cutoff')
assert.equal((headingMarkup(currentDayMonthMarkup).match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, viewedMonth - 1, 1))}`, 'g')) || []).length, 3, 'Income, expenses and the total list the first day of the selected month')
if (viewedMonth !== 12) assert.equal((headingMarkup(currentDayMonthMarkup).match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 11, 31))}`, 'g')) || []).length, 0, 'A selected month hides the other days of the year')
const februaryDayMarkup = await renderToString(createReportApp({ ...selection, period: 'day', month: 2 }))
assert.equal(reportControlCount(februaryDayMarkup), new Date().getMonth() === 1 ? 6 : 5, 'A past month of the daily overview hides payments up to today')
assert.equal((headingMarkup(februaryDayMarkup).match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 1, 1))}`, 'g')) || []).length, 3, 'February lists its first day')
assert.equal((headingMarkup(februaryDayMarkup).match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 11, 31))}`, 'g')) || []).length, 0, 'February hides 31 December')
if (new Date().getMonth() < 11) {
	const laterDayMarkup = await renderToString(createReportApp({ ...selection, period: 'day', month: new Date().getMonth() + 2 }))
	assert.equal(reportControlCount(laterDayMarkup), 5, 'A later month of the daily overview hides the cutoff dropdown')
}
const pastDayMarkup = await renderToString(createReportApp({ ...selection, year: currentYear - 1, period: 'day', month: 0 }))
assert.equal(reportControlCount(pastDayMarkup), 5, 'A past year hides payments up to today')
const futureDayMarkup = await renderToString(createReportApp({ ...selection, year: currentYear + 1, period: 'day' }))
assert.equal(reportControlCount(futureDayMarkup), 5, 'A future year hides the cutoff dropdown')
assert.equal((markup.match(/<table /g) || []).length, 3, 'Income, expenses and summary all render')
const collapsedWindow = globalThis.window
globalThis.window = {
 localStorage: { getItem: () => JSON.stringify({ income: false, total: false }) },
}
const legacyPreferencesMarkup = await renderToString(createReportApp(selection))
assert.doesNotMatch(legacyPreferencesMarkup, /is-collapsed|collapseSection|expandSection/)
assert.match(legacyPreferencesMarkup, /class="annual-amount"/)
if (collapsedWindow === undefined) delete globalThis.window
else globalThis.window = collapsedWindow
assert.equal((markup.match(/aria-sort="descending"/g) || []).length, 2, 'Income and expense headers indicate highest-first total sorting by default')
assert.equal((markup.match(/class="sortable(?: annual-year-total)?"/g) || []).length, 4, 'Only title and yearly total headers in income and expenses are sortable')
assert.equal((markup.match(/<a /g) || []).length, 6, 'Row labels open report details')
assert.equal((markup.match(/class="annual-amount"/g) || []).length, 78, 'Every monthly and yearly amount opens details')
const labelLinks = [...markup.matchAll(/<th scope="row">(.*?)<\/th>/g)].map(match => JSON.parse(match[1].match(/href="([^"]+)"/)[1].replaceAll('&quot;', '"').replaceAll('&amp;', '&')))
assert.equal(labelLinks.length, 6)
for (const link of labelLinks) {
	assert.equal(link.name, 'annual-overview', 'First-column links stay within the report')
	assert.equal(link.query.detailPeriod, 'total', 'First-column links open the whole selected period')
	assert.equal(link.query.year, String(currentYear))
}
assert.equal(labelLinks.find(link => link.query.detailId === '1')?.query.detailDirect, undefined, 'Parent category details include their children')
const footers = [...markup.matchAll(/<tfoot>(.*?)<\/tfoot>/g)].map(match => match[1])
assert.equal(footers.length, 2, 'Income and expenses each have a total row, even with no payments')
for (const [index, footer] of footers.entries()) {
	const links = [...footer.matchAll(/href="([^"]+)"/g)].map(match => JSON.parse(match[1].replaceAll('&quot;', '"').replaceAll('&amp;', '&')))
	assert.equal(links.length, 1, 'Each total row retains its undated label link')
	assert.equal((footer.match(/class="annual-amount"/g) || []).length, 13, 'Footer amounts open details')
	for (const link of links) {
		assert.equal(link.query.detailType, index === 0 ? 'income' : 'expense')
		assert.equal(link.query.detailId, 'total')
		assert.equal(link.query.categoryId, undefined, 'Totals include every category')
		assert.equal(link.query.paymentPartnerId, undefined, 'Totals include every payment partner')
	}
	assert.ok(footer.includes(index === 0 ? '0.00' : '3.03'), 'Footer uses the report total')
}
assert.ok(markup.includes('detailId'), 'Row identities are preserved in shareable detail links')
assert.ok(markup.includes('Home &amp; family'), 'User labels are escaped once')
assert.ok(markup.includes('-3.03'), 'Negative balance renders correctly')
assert.ok(markup.includes('Jänner'), 'All calendar headings render')
assert.equal((markup.match(/annual-month-empty/g) || []).length, 57, 'Empty months in category, footer and summary rows receive the muted empty class')
assert.ok(markup.includes('>–</button>'), 'Empty category months display an en-dash')
console.log('Annual overview rendering passed: three tables, all payment links, parent grouping and signed balance.')

const statsRow = { id: 5, label: 'Rent', type: 'expense', months: [12000, ...Array(11).fill(0)], total: 12000, counts: [3, ...Array(11).fill(0)], totalCount: 3 }
const statsReport = {
	cutoff: new Date(2026, 9, 19).getTime() / 1000,
	periods: Array.from({ length: 13 }, () => ({ dateFrom: new Date(2026, 0, 1).getTime() / 1000, dateTo: new Date(2027, 0, 1).getTime() / 1000 - 1 })),
	totals: { income: { months: [18000, ...Array(11).fill(0)], total: 18000 }, expense: { months: [24000, ...Array(11).fill(0)], total: 24000 } },
}
const statsSelection = { year: 2026, grouping: 'categories', projectId: 12, includeFuturePayments: false }
const monthly = annualCellDetails({ row: statsRow, index: 0 }, statsReport, statsSelection)
assert.equal(monthly.amount, 12000)
assert.equal(monthly.count, 3)
assert.equal(monthly.averagePerPayment, 4000)
assert.equal(monthly.share, 0.5)
const yearly = annualCellDetails({ row: statsRow, index: 12 }, statsReport, statsSelection)
assert.equal(yearly.monthCount, 10)
assert.equal(yearly.averagePerMonth, 1200, 'Elapsed calendar months include zero-payment months')
assert.equal(annualCellDetails({ row: statsRow, index: 12 }, statsReport, { ...statsSelection, includeFuturePayments: true }).averagePerMonth, 1000)
const completeReport = { ...statsReport, cutoff: statsReport.periods[12].dateTo }
assert.equal(annualCellDetails({ row: statsRow, index: 12 }, completeReport, statsSelection).partialYear, false, 'Completed years do not claim to include a partial current month')
assert.equal(annualCellDetails({ row: statsRow, index: 12 }, completeReport, statsSelection).averagePerMonth, 1000)
assert.equal(annualCellDetails({ row: statsRow, index: 11 }, statsReport, statsSelection).share, null, 'Empty periods never divide by zero')
assert.equal(annualCellDetails({ row: statsRow, index: 11 }, statsReport, statsSelection).averagePerPayment, null)
assert.equal(annualCellDetails({ row: statsRow, index: 0, total: true }, statsReport, statsSelection).share, null, 'Totals do not show a redundant 100% share')
const balanceMetrics = annualCellDetails({ row: { ...statsRow, type: 'all', total: -101 }, index: 12, total: true }, { ...statsReport, cutoff: new Date(2026, 1, 15).getTime() / 1000 }, statsSelection)
assert.equal(balanceMetrics.averagePerMonth, -51, 'Signed averages round to whole cents symmetrically')
assert.equal(balanceMetrics.share, null)
assert.equal(balanceMetrics.averagePerPayment, null, 'Balance does not imply an average expense')
assert.equal(balanceMetrics.income, 18000)
assert.equal(balanceMetrics.expense, 24000)
const dayCount = 31
const dayReport = {
	cutoff: new Date(2026, 9, 19, 15, 30).getTime() / 1000,
	periods: Array.from({ length: dayCount + 1 }, () => ({ dateFrom: new Date(2026, 9, 1).getTime() / 1000, dateTo: new Date(2026, 10, 1).getTime() / 1000 - 1 })),
	totals: { income: { months: Array(dayCount).fill(0), total: 3100 }, expense: { months: [6200, ...Array(dayCount - 1).fill(0)], total: 6200 } },
}
const daySelection = { ...statsSelection, period: 'month', month: 10 }
const dayRow = { ...statsRow, months: [6200, ...Array(dayCount - 1).fill(0)], total: 3100, counts: [2, ...Array(dayCount - 1).fill(0)], totalCount: 2 }
const dayTotal = annualCellDetails({ row: dayRow, index: dayCount }, dayReport, daySelection)
assert.equal(dayTotal.yearly, true, 'The last monthly column is the month total')
assert.equal(dayTotal.averagePerMonth, null)
assert.equal(dayTotal.averagePerDay, 163, 'A partial month averages the elapsed days, including today')
assert.equal(dayTotal.partialYear, true)
const finishedMonth = annualCellDetails({ row: dayRow, index: dayCount }, { ...dayReport, cutoff: dayReport.periods[dayCount].dateTo }, daySelection)
assert.equal(finishedMonth.averagePerDay, 100, 'A finished month averages every day')
assert.equal(finishedMonth.partialYear, false)
const dayMonthSelection = { ...daySelection, period: 'day' }
const dayMonthTotal = annualCellDetails({ row: dayRow, index: dayCount }, dayReport, dayMonthSelection)
assert.equal(dayMonthTotal.averagePerDay, 163, 'A chosen month in the daily overview averages the elapsed days of that month')
assert.equal(dayMonthTotal.averagePerMonth, null)
assert.equal(dayMonthTotal.partialYear, true)
const dayCell = annualCellDetails({ row: dayRow, index: 0 }, dayReport, daySelection)
assert.equal(dayCell.yearly, false)
assert.equal(dayCell.amount, 6200)
assert.equal(dayCell.share, 1)
assert.equal(annualDetailComparison({ ...dayReport, groups: { expense: [], income: [] } }, { row: dayRow, index: dayCount }), null, 'The month total does not load a day comparison')
const quarterReport = {
	cutoff: new Date(2026, 9, 19).getTime() / 1000,
	periods: [
		...[0, 3, 6, 9].map(month => ({ dateFrom: new Date(2026, month, 1).getTime() / 1000, dateTo: new Date(2026, month + 3, 1).getTime() / 1000 - 1 })),
		{ dateFrom: new Date(2026, 0, 1).getTime() / 1000, dateTo: new Date(2027, 0, 1).getTime() / 1000 - 1 },
	],
	totals: { income: { months: [0, 0, 0, 18000], total: 18000 }, expense: { months: [0, 0, 0, 24000], total: 24000 } },
}
const quarterSelection = { ...statsSelection, period: 'quarter', quarter: 0 }
const quarterRow = { ...statsRow, months: [0, 0, 0, 12000], total: 12000, counts: [0, 0, 0, 3], totalCount: 3 }
const quarterTotal = annualCellDetails({ row: quarterRow, index: 4 }, quarterReport, quarterSelection)
assert.equal(quarterTotal.yearly, true, 'The last quarterly column is the year total')
assert.equal(quarterTotal.quarterCount, 4, 'October counts the current quarter')
assert.equal(quarterTotal.averagePerQuarter, 3000)
assert.equal(quarterTotal.averagePerMonth, null)
assert.equal(quarterTotal.averagePerDay, null)
assert.equal(quarterTotal.partialYear, true)
assert.equal(annualCellDetails({ row: quarterRow, index: 4 }, { ...quarterReport, cutoff: new Date(2026, 2, 19).getTime() / 1000 }, quarterSelection).quarterCount, 1, 'March counts the first quarter')
assert.equal(annualCellDetails({ row: quarterRow, index: 4 }, { ...quarterReport, cutoff: new Date(2026, 5, 15).getTime() / 1000 }, quarterSelection).quarterCount, 2, 'June counts the second quarter')
assert.equal(annualCellDetails({ row: quarterRow, index: 4 }, { ...quarterReport, cutoff: new Date(2026, 8, 15).getTime() / 1000 }, quarterSelection).quarterCount, 3, 'September counts the third quarter')
const finishedQuarter = annualCellDetails({ row: quarterRow, index: 4 }, { ...quarterReport, cutoff: quarterReport.periods[4].dateTo }, quarterSelection)
assert.equal(finishedQuarter.quarterCount, 4, 'A finished year averages all four quarters')
assert.equal(finishedQuarter.partialYear, false)
const quarterCell = annualCellDetails({ row: quarterRow, index: 3 }, quarterReport, quarterSelection)
assert.equal(quarterCell.yearly, false)
assert.equal(quarterCell.amount, 12000)
assert.equal(quarterCell.share, 0.5)
assert.equal(quarterCell.averagePerQuarter, null)
assert.equal(quarterCell.averagePerMonth, null)
const pastQuarter = annualCellDetails({ row: quarterRow, index: 4 }, quarterReport, { ...quarterSelection, year: 2025 })
assert.equal(pastQuarter.quarterCount, 4, 'A past year uses all four quarters')
assert.equal(pastQuarter.partialYear, false)
assert.equal(annualDetailComparison(quarterReport, { row: quarterRow, index: 4 }), null, 'The year total does not load a quarter comparison')
const countedWeeks = annual.isoWeekCount(2026)
const weekPeriods = Array.from({ length: countedWeeks }, (_, index) => {
	const start = annual.isoWeekStart(2026, index + 1)
	const end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 7)
	end.setSeconds(end.getSeconds() - 1)
	return { dateFrom: Math.floor(start.getTime() / 1000), dateTo: Math.floor(end.getTime() / 1000) }
})
const weekCutoff = new Date(2026, 9, 19)
const weekReport = {
	cutoff: Math.floor(weekCutoff.getTime() / 1000),
	periods: [...weekPeriods, { dateFrom: weekPeriods[0].dateFrom, dateTo: weekPeriods.at(-1).dateTo }],
	totals: { income: { months: Array(countedWeeks).fill(0), total: 18000 }, expense: { months: [24000, ...Array(countedWeeks - 1).fill(0)], total: 24000 } },
}
const weekSelection = { ...statsSelection, period: 'week' }
const weekRow = { ...statsRow, months: [12000, ...Array(countedWeeks - 1).fill(0)], total: 12000, counts: [3, ...Array(countedWeeks - 1).fill(0)], totalCount: 3 }
const weekTotal = annualCellDetails({ row: weekRow, index: countedWeeks }, weekReport, weekSelection)
assert.equal(weekTotal.yearly, true, 'The last weekly column is the week-year total')
assert.equal(weekTotal.weekCount, annual.isoWeekNumber(weekCutoff), 'October counts the current week')
assert.equal(weekTotal.weekCount, 43)
assert.equal(weekTotal.averagePerWeek, 279, 'A partial week-year averages the elapsed weeks, including the current week')
assert.equal(weekTotal.averagePerMonth, null)
assert.equal(weekTotal.averagePerQuarter, null)
assert.equal(weekTotal.averagePerDay, null)
assert.equal(weekTotal.partialYear, true)
const finishedWeeks = annualCellDetails({ row: weekRow, index: countedWeeks }, { ...weekReport, cutoff: weekReport.periods.at(-1).dateTo }, weekSelection)
assert.equal(finishedWeeks.weekCount, countedWeeks, 'A finished week-year averages every calendar week')
assert.equal(finishedWeeks.partialYear, false)
assert.equal(finishedWeeks.averagePerWeek, Math.round(12000 / countedWeeks))
const weekCell = annualCellDetails({ row: weekRow, index: 0 }, weekReport, weekSelection)
assert.equal(weekCell.yearly, false)
assert.equal(weekCell.amount, 12000)
assert.equal(weekCell.share, 0.5)
assert.equal(weekCell.averagePerWeek, null)
assert.equal(weekCell.averagePerMonth, null)
assert.equal(annualDetailComparison(weekReport, { row: weekRow, index: countedWeeks }), null, 'The week-year total does not load a week comparison')
const countedDays = annual.calendarDayCount(2026)
const dayYearPeriods = Array.from({ length: countedDays }, (_, index) => {
	const start = new Date(2026, 0, index + 1)
	const end = new Date(2026, 0, index + 2)
	end.setSeconds(end.getSeconds() - 1)
	return { dateFrom: Math.floor(start.getTime() / 1000), dateTo: Math.floor(end.getTime() / 1000) }
})
const dayYearCutoff = new Date(2026, 9, 19, 15, 30)
const dayYearReport = {
	cutoff: Math.floor(dayYearCutoff.getTime() / 1000),
	periods: [...dayYearPeriods, { dateFrom: dayYearPeriods[0].dateFrom, dateTo: Math.floor(new Date(2027, 0, 1).getTime() / 1000) - 1 }],
	totals: { income: { months: Array(countedDays).fill(0), total: 18000 }, expense: { months: [24000, ...Array(countedDays - 1).fill(0)], total: 24000 } },
}
const dayYearSelection = { ...statsSelection, period: 'day', month: 0 }
const dayYearRow = { ...statsRow, months: [12000, ...Array(countedDays - 1).fill(0)], total: 12000, counts: [3, ...Array(countedDays - 1).fill(0)], totalCount: 3 }
const dayYearTotal = annualCellDetails({ row: dayYearRow, index: countedDays }, dayYearReport, dayYearSelection)
assert.equal(dayYearTotal.yearly, true, 'The last daily column is the calendar-year total')
assert.equal(dayYearTotal.averagePerMonth, null)
assert.equal(dayYearTotal.averagePerQuarter, null)
assert.equal(dayYearTotal.averagePerWeek, null)
assert.equal(dayYearTotal.averagePerDay, 41, 'A partial year averages the elapsed days, including today')
assert.equal(dayYearTotal.partialYear, true)
const finishedDays = annualCellDetails({ row: dayYearRow, index: countedDays }, { ...dayYearReport, cutoff: dayYearReport.periods.at(-1).dateTo }, dayYearSelection)
assert.equal(finishedDays.averagePerDay, Math.round(12000 / countedDays), 'A finished year averages every calendar day')
assert.equal(finishedDays.partialYear, false)
const dayYearCell = annualCellDetails({ row: dayYearRow, index: 0 }, dayYearReport, dayYearSelection)
assert.equal(dayYearCell.yearly, false)
assert.equal(dayYearCell.amount, 12000)
assert.equal(dayYearCell.share, 0.5)
assert.equal(dayYearCell.averagePerDay, null)
assert.equal(annualDetailComparison(dayYearReport, { row: dayYearRow, index: countedDays }), null, 'The year total does not load a day comparison')
for (const row of [statsRow, { ...statsRow, children: [{ id: 6 }] }, { ...statsRow, direct: true }, { ...statsRow, id: 0 }]) {
	for (const index of [0, 12]) {
		let opened
		const state = { selection: statsSelection, periods: statsReport.periods, summary: false, $emit: (event, detail) => { assert.equal(event, 'details'); opened = detail } }
		state.paymentRoute = table.methods.paymentRoute.bind(state)
		table.methods.openDetails.call(state, row, index)
		assert.deepEqual(opened.route, annual.annualPaymentRoute(statsSelection, statsReport.periods[index], 'expense', row), 'Modal payment action retains the exact drilldown, including child/unassigned filters')
		assert.equal(opened.row, row)
	}
}
const detail = { row: statsRow, index: 0, total: false, label: 'Home & family · Rent', route: annual.annualPaymentRoute(statsSelection, statsReport.periods[0], 'expense', statsRow) }
const modalApp = Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail, report: statsReport, selection: statsSelection, scopeLabel: 'Shared home' })
modalApp.config.globalProperties.$texts = createReportApp(selection).config.globalProperties.$texts
modalApp.config.globalProperties.$formatMoneyFromCents = cents => (cents / 100).toFixed(2)
const modalMarkup = await renderToString(modalApp)
assert.ok(modalMarkup.includes('Home &amp; family · Rent'))
assert.ok(modalMarkup.includes('Shared home') && modalMarkup.includes('2026'))
assert.ok(modalMarkup.includes('120.00') && modalMarkup.includes('40.00'))
assert.ok(modalMarkup.includes('50') && modalMarkup.includes('showPayments'))

// Exercise the rendered payment button with the app router. The external button
// intentionally has no routing support, matching the separate Nextcloud router context.
for (const projectId of [null, 12]) {
	const target = annual.annualPaymentRoute({ ...statsSelection, projectId }, statsReport.periods[0], 'expense', statsRow)
	const router = createRouter({ history: createMemoryHistory(), routes: [
		{ path: '/annual', name: 'annual-overview', component: {} },
		{ path: '/payments', name: 'payments', component: {} },
		{ path: '/projects/:id/payments', name: 'project-payments', component: {} },
	] })
	await router.push({ name: 'annual-overview' })
	let paymentClick
	const closes = []
	const dialog = { ...bindings.AnnualOverviewDetails, components: {
		...bindings.AnnualOverviewDetails.components,
		NcButton: { setup: (_, { attrs, slots }) => {
			if (attrs.class === 'annual-details-payments') paymentClick = attrs.onClick
			return () => Vue.h('button', slots.default?.())
		} },
	} }
	const app = Vue.createSSRApp(dialog, { detail: { ...detail, route: target }, report: statsReport, selection: { ...statsSelection, projectId }, scopeLabel: 'Test', onClose: () => closes.push(router.currentRoute.value.fullPath) })
	app.use(router)
	app.config.globalProperties.$texts = modalApp.config.globalProperties.$texts
	app.config.globalProperties.$formatMoneyFromCents = modalApp.config.globalProperties.$formatMoneyFromCents
	await renderToString(app)
	assert.equal(typeof paymentClick, 'function')
	const unblock = router.beforeEach(() => false)
	await paymentClick()
	assert.equal(router.currentRoute.value.name, 'annual-overview')
	assert.equal(closes.length, 0, 'Blocked navigation leaves the detail dialog open')
	unblock()
	await paymentClick()
	assert.equal(router.currentRoute.value.fullPath, router.resolve(target).fullPath, 'Click reaches the payment list with all filters')
	assert.deepEqual(closes, [router.resolve(target).fullPath], 'Dialog closes only after reaching the payment list')
}
console.log('Annual detail tests passed: counts, shares, calendar averages, empty periods, signed balances and exact drilldowns.')

assert.equal(annual.annualRouteQuery({ year: currentYear, comparison: 'month' }).comparison, undefined, 'Comparisons belong to the modal, not overview routing')
const change = { current: 12000, previous: 10000, delta: 2000, percent: 20, direction: 'up', currentFrom: '2026-10-01', currentTo: '2026-10-19', previousFrom: '2026-09-01', previousTo: '2026-09-19' }
for (const type of ['income', 'expense', 'all']) {
 const app = Vue.createSSRApp(bindings.AnnualComparison, { value: change, type })
 app.config.globalProperties.$texts = createReportApp(selection).config.globalProperties.$texts
 app.config.globalProperties.$formatMoneyFromCents = cents => (cents / 100).toFixed(2) + ' €'
 const html = await renderToString(app)
 assert.match(html, /class="annual-comparison-value">.*\+20 %.*<span>\(\+20.00 €\)<\/span>/, 'Percentage precedes the euro difference in parentheses')
 assert.ok(html.includes('annual-comparison-icon'), 'Trend uses the circular icon slot')
 assert.ok(html.includes(type === 'expense' ? 'unfavorable' : 'favorable'))
 assert.ok(html.includes('19. Sep. 2026') && html.includes('19. Okt. 2026'), 'Tooltip identifies both actual date ranges')
}
const comparisonPeriod = bindings.AnnualOverviewDetails.methods.comparisonPeriod
const realNow = Date.now
try {
 Date.now = () => new Date('2026-10-19T12:00:00').getTime()
 assert.equal(comparisonPeriod(change), '1. Sep. 2026 – 19. Sep. 2026', 'Ongoing month shows actual reference dates')
 assert.equal(comparisonPeriod({ ...change, currentFrom: '2026-09-01', currentTo: '2026-09-30', previousFrom: '2026-08-01', previousTo: '2026-08-31' }), 'August 2026', 'Completed month uses month and year only')
 Date.now = () => new Date('2026-10-31T12:00:00').getTime()
 assert.equal(comparisonPeriod({ ...change, currentTo: '2026-10-31', previousTo: '2026-09-30' }), '1. Sep. 2026 – 30. Sep. 2026', 'Last day is still ongoing, including a shorter reference month')
 Date.now = () => new Date('2026-11-01T00:00:00').getTime()
 assert.equal(comparisonPeriod(change), 'September 2026', 'Completed selected month gets a concise reference caption')
 const quarterContext = { selection: { period: 'quarter' }, $texts: { annual: { quarter: number => `${number}. Quartal` } } }
 const formatQuarterDay = date => new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${date}T12:00:00`))
 assert.equal(comparisonPeriod.call(quarterContext, { previousFrom: '2025-01-01', previousTo: '2025-03-31', currentFrom: '2026-01-01', currentTo: '2026-03-31' }), '1. Quartal 2025', 'A finished quarter names the quarter and year')
 assert.equal(comparisonPeriod.call(quarterContext, { previousFrom: '2025-10-01', previousTo: '2025-12-19', currentFrom: '2026-01-01', currentTo: '2026-03-19' }), `${formatQuarterDay('2025-10-01')} – ${formatQuarterDay('2025-12-19')}`, 'An ongoing quarter shows the elapsed reference dates')
 const weekContext = { selection: { period: 'week' }, $texts: { annual: { week: number => `KW ${number}` } } }
 const finishedMonday = new Date('2026-03-09T12:00:00')
 assert.equal(comparisonPeriod.call(weekContext, { previousFrom: '2026-03-09', previousTo: '2026-03-15', currentFrom: '2026-03-16', currentTo: '2026-03-22' }), `KW ${annual.isoWeekNumber(finishedMonday)} ${annual.isoWeekYear(finishedMonday)}`, 'A finished week names the calendar week and its week-year')
 const formatWeekDay = date => new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${date}T12:00:00`))
 assert.equal(comparisonPeriod.call(weekContext, { previousFrom: '2026-03-09', previousTo: '2026-03-12', currentFrom: '2026-03-16', currentTo: '2026-03-19' }), `${formatWeekDay('2026-03-09')} – ${formatWeekDay('2026-03-12')}`, 'An ongoing week shows the elapsed reference dates')
 const labelOf = bindings.AnnualOverviewDetails.methods.comparisonLabel
 const textProxy = createReportApp(selection).config.globalProperties.$texts
 assert.equal(labelOf.call({ selection: { period: 'week' }, $texts: textProxy }, 'month'), 'changeFromPreviousWeek')
 assert.equal(labelOf.call({ selection: { period: 'week' }, $texts: textProxy }, 'year'), 'changeFromSameWeekLastYear')
 assert.equal(labelOf.call({ selection: { period: 'quarter' }, $texts: textProxy }, 'month'), 'changeFromPreviousQuarter')
 assert.equal(labelOf.call({ selection: { period: 'month' }, $texts: textProxy }, 'month'), 'changeFromPreviousDay')
 const dayContext = { selection: { period: 'day' } }
 assert.equal(comparisonPeriod.call(dayContext, { previousFrom: '2026-03-18', previousTo: '2026-03-19', currentFrom: '2026-03-19', currentTo: '2026-03-19' }), formatWeekDay('2026-03-18'), 'A day reference names that day')
 assert.equal(labelOf.call({ selection: { period: 'day' }, $texts: textProxy }, 'month'), 'changeFromPreviousDay')
 assert.equal(labelOf.call({ selection: { period: 'day' }, $texts: textProxy }, 'year'), 'changeFromSameDayLastYear')
 const shareOf = bindings.AnnualOverviewDetails.computed.shareLabel
 const shareContext = (period, yearly, type = 'expense') => ({ selection: { period, year: 2026, month: period === 'month' ? 3 : 0 }, detail: { row: { type }, index: 0 }, metrics: { yearly }, $texts: textProxy })
 assert.equal(shareOf.call(shareContext('day', false)), 'dayExpenseShare')
 assert.equal(shareOf.call(shareContext('day', true)), 'yearExpenseShare')
 assert.equal(shareOf.call({ ...shareContext('day', true), selection: { period: 'day', year: 2026, month: 3 } }), 'monthExpenseShare')
 assert.equal(shareOf.call({ ...shareContext('day', true, 'income'), selection: { period: 'day', year: 2026, month: 3 } }), 'monthIncomeShare')
 assert.equal(shareOf.call(shareContext('day', false, 'income')), 'dayIncomeShare')
 assert.equal(shareOf.call(shareContext('month', false)), 'dayExpenseShare')
 assert.equal(shareOf.call(shareContext('year', false)), 'monthExpenseShare')
 const dayDialog = Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: { row: dayYearRow, index: 0, total: false, label: 'Rent', route: {} }, report: dayYearReport, selection: dayYearSelection, scopeLabel: 'Shared home' })
 dayDialog.config.globalProperties.$texts = textProxy
 dayDialog.config.globalProperties.$formatMoneyFromCents = cents => (cents / 100).toFixed(2)
 const dayDialogMarkup = await renderToString(dayDialog)
 assert.ok(dayDialogMarkup.includes('>day</th>'), 'A day cell names the calendar day')
 assert.equal(dayDialogMarkup.includes('>month</th>'), false, 'A day cell hides the month row')
 assert.ok(dayDialogMarkup.includes(new Intl.DateTimeFormat('de-AT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(2026, 0, 1))), 'The day row shows the full date')
 const dayTotalDialog = Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: { row: dayYearRow, index: countedDays, total: true, label: 'total', route: {} }, report: dayYearReport, selection: dayYearSelection, scopeLabel: 'Shared home' })
 dayTotalDialog.config.globalProperties.$texts = textProxy
 dayTotalDialog.config.globalProperties.$formatMoneyFromCents = cents => (cents / 100).toFixed(2)
 const dayTotalMarkup = await renderToString(dayTotalDialog)
 assert.ok(dayTotalMarkup.includes('averagePerDay'), 'The year total shows the average per elapsed day')
 assert.equal(dayTotalMarkup.includes('averagePerMonth'), false, 'The daily year total hides the monthly average')
 const marchSelection = { ...dayYearSelection, month: 3 }
 const marchDialog = Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: { row: dayYearRow, index: 0, total: false, label: 'Rent', route: {} }, report: dayYearReport, selection: marchSelection, scopeLabel: 'Shared home' })
 marchDialog.config.globalProperties.$texts = textProxy
 marchDialog.config.globalProperties.$formatMoneyFromCents = cents => (cents / 100).toFixed(2)
 const marchDialogMarkup = await renderToString(marchDialog)
 assert.ok(marchDialogMarkup.includes('>month</th>'), 'A day of a chosen month shows the month')
 assert.ok(marchDialogMarkup.includes('>day</th>'), 'A day of a chosen month names the calendar day')
 assert.ok(marchDialogMarkup.includes(new Intl.DateTimeFormat('de-AT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(2026, 2, 1))), 'The day row shows that day of the selected month')
} finally { Date.now = realNow }
for (const value of [{ ...change, delta: -400, percent: -40, direction: 'down' }, { ...change, delta: 500, percent: 5, direction: null }, { ...change, previous: 0, percent: null, direction: null }]) {
 const app = Vue.createSSRApp(bindings.AnnualComparison, { value })
 app.config.globalProperties.$texts = createReportApp(selection).config.globalProperties.$texts
 app.config.globalProperties.$formatMoneyFromCents = cents => '€ ' + (cents / 100).toFixed(2)
 const html = await renderToString(app)
 if (value.percent === -40) assert.match(html, /−40 %.*<span>\(−€ 4.00\)<\/span>/)
 if (!value.direction) assert.ok(!html.includes('class="annual-comparison-icon"'), 'No decorative arrow disk within threshold or for zero baseline')
 if (value.percent === null) assert.match(html, /class="annual-comparison-value">.*<span>\+€ 20.00<\/span>/, 'Zero baseline shows only the difference')
}
console.log('Annual comparison UI passed: localized values, directions, expense/income colors and precise dates.')

const comparisonRow = { ...statsRow, comparisons: [change, ...Array(11).fill(null)] }
const comparisonReport = { ...statsReport, groups: { expense: [comparisonRow], income: [] }, totals: { ...statsReport.totals, expense: comparisonRow } }
assert.equal(annualDetailComparison(comparisonReport, detail), change)
assert.equal(annualDetailComparison(comparisonReport, { ...detail, total: true }), change)
assert.equal(annualDetailComparison(comparisonReport, { ...detail, index: 12 }), null)
assert.equal(annualDetailComparison(null, detail), null)
assert.equal(annualDetailComparison(comparisonReport, { ...detail, row: { ...statsRow, id: 999 } }), null)
const directChange = { ...change, delta: 100 }
const nestedReport = { ...comparisonReport, groups: { expense: [{ ...comparisonRow, children: [{ ...comparisonRow, direct: true, comparisons: [directChange] }] }] } }
assert.equal(annualDetailComparison(nestedReport, { ...detail, row: { ...statsRow, direct: true } }), directChange, 'Direct parent payments never receive aggregated parent comparisons')
const comparisonDialog = { ...bindings.AnnualOverviewDetails, data: () => ({ comparisonReports: { month: comparisonReport, year: comparisonReport }, comparisonLoading: false, comparisonError: false }) }
const comparisonApp = Vue.createSSRApp(comparisonDialog, { detail, report: statsReport, selection: statsSelection, scopeLabel: 'Test' })
comparisonApp.config.globalProperties.$texts = modalApp.config.globalProperties.$texts
comparisonApp.config.globalProperties.$formatMoneyFromCents = modalApp.config.globalProperties.$formatMoneyFromCents
const comparisonMarkup = await renderToString(comparisonApp)
assert.equal((comparisonMarkup.match(/class="annual-details-comparison"/g) || []).length, 2, 'Both comparisons render inside the modal metrics table')
assert.equal((comparisonMarkup.match(/<table /g) || []).length, 4, 'Basics, key figures, averages and comparisons have separate tables')
assert.ok(comparisonMarkup.includes('changeFromPreviousMonth') && comparisonMarkup.includes('changeFromPreviousYear'))
assert.ok(!markup.includes('annual-comparison'), 'Overview contains no difference rows')
// Delayed replies must not update another detail or a closed modal.
const resolvers = []
bindings.axios.get = () => new Promise(resolve => resolvers.push(resolve))
const state = { detail, report: statsReport, selection: statsSelection, comparisonRequest: 0 }
const pending = bindings.AnnualOverviewDetails.methods.loadComparisons.call(state)
assert.equal(resolvers.length, 2)
state.comparisonRequest++
resolvers.forEach(resolve => resolve({ data: comparisonReport }))
await pending
assert.deepEqual(state.comparisonReports, {}, 'Stale comparison replies cannot replace current modal state')
let requests = 0
bindings.axios.get = async () => { requests++; return { data: comparisonReport } }
await bindings.AnnualOverviewDetails.methods.loadComparisons.call({ ...state, detail: { ...detail, index: 12 } })
assert.equal(requests, 0, 'Year totals do not load month comparisons')
bindings.axios.get = async () => { throw Error('offline') }
await bindings.AnnualOverviewDetails.methods.loadComparisons.call(state)
assert.equal(state.comparisonError, true)
assert.equal(state.comparisonLoading, false)
console.log('Modal comparisons passed: placement, absent history, direct-category mapping, cancelled replies, annual totals and error handling.')

// Independent report dates, breakdowns and useful preset defaults.
const presetNow = new Date(2026, 0, 22, 12)
assert.deepEqual(annual.reportRange({ timeRange: 'last-month' }, presetNow), { dateFrom: '2025-12-01', dateTo: '2025-12-31' })
assert.deepEqual(annual.reportRange({ timeRange: 'last-12-months' }, presetNow), { dateFrom: '2025-02-01', dateTo: '2026-01-31' })
assert.equal(annual.annualSelection({ timeRange: 'current-month' }).period, 'day')
assert.equal(annual.annualSelection({ timeRange: 'last-month' }).period, 'day')
assert.equal(annual.annualSelection({ timeRange: 'last-12-months' }).period, 'year')
const holiday = annual.annualSelection({ timeRange: 'custom', dateFrom: '2026-03-25', dateTo: '2026-04-03', grouping: 'paymentPartners', categories: '7', projectId: 12, entryScope: 'shared' })
assert.equal(holiday.period, 'day', 'Ten-day vacations start with individual days')
assert.equal(annual.annualSelection({ ...holiday, dateTo: '2026-06-01', period: undefined }).period, 'week', 'Medium custom periods start with weeks')
for (const period of ['year', 'quarter', 'week', 'day']) {
 const selected = annual.annualSelection({ ...holiday, period })
 const query = annual.annualRouteQuery(selected)
 assert.equal(query.period, period, 'Explicit months must survive normalization even for short ranges')
 assert.deepEqual(annual.annualSelection(query), selected)
 const params = annual.annualRequestParams(selected)
 assert.equal(params.dateFrom, holiday.dateFrom)
 assert.equal(params.dateTo, holiday.dateTo)
 assert.equal(params.month, undefined, 'A breakdown must never reapply the old month restriction')
 const linked = annual.annualPaymentRoute(selected, null, 'expense', { id: 4 })
 assert.equal(linked.query.timeRange, 'custom', 'Row labels retain the selected report dates')
 assert.equal(linked.query.dateFrom, String(new Date(2026, 2, 25).getTime() / 1000))
 assert.equal(linked.query.dateTo, String(new Date(2026, 3, 3, 23, 59, 59).getTime() / 1000))
 assert.deepEqual(annual.annualReturnRoute(linked.query.annualReturn)?.query, query, 'Returning retains dates, granularity, area and filters')
}
assert.equal(annual.reportRangeError({ ...holiday, dateTo: '2026-03-24' }), 'dates')
assert.equal(annual.reportRangeError({ ...holiday, dateTo: '2026-02-30' }), 'dates')
assert.equal(annual.reportRangeError({ ...holiday, dateTo: '2046-01-01' }), 'columns')
assert.equal(annual.reportRangeError({ ...holiday, dateTo: '2046-01-01', period: 'year' }), '')
const oldFebruary = annual.annualReportSelection({ year: 2024, period: 'month', month: 2 })
assert.equal(oldFebruary.dateFrom, '2024-02-01')
assert.equal(oldFebruary.dateTo, '2024-02-29')
const oldWeekYear = annual.annualReportSelection({ year: 2020, period: 'week' })
assert.equal(oldWeekYear.dateFrom, '2019-12-30', 'Old week-year bookmarks retain their boundary days')
assert.equal(oldWeekYear.dateTo, '2021-01-03')
const currentMonthOnMonths = annual.annualSelection({ timeRange: 'current-month', period: 'year' })
assert.equal(annual.annualSelection(annual.annualRouteQuery(currentMonthOnMonths)).period, 'year', 'Manual month grouping is stable for a monthly preset')
console.log('Report ranges passed: presets, calendar boundaries, free dates, independent breakdowns, validation, legacy bookmarks and exact return links.')

const holidayPeriods = Array.from({ length: 10 }, (_, day) => ({ dateFrom: new Date(2026, 2, 25 + day).getTime() / 1000, dateTo: new Date(2026, 2, 26 + day).getTime() / 1000 - 1 }))
holidayPeriods.push({ dateFrom: holidayPeriods[0].dateFrom, dateTo: holidayPeriods.at(-1).dateTo })
const holidayAmounts = { months: [101, 0, 0, 0, 202, 0, 0, 0, 0, 303], counts: [1, 0, 0, 0, 1, 0, 0, 0, 0, 1], total: 606, totalCount: 3 }
const holidayReport = { ...report, range: { dateFrom: holiday.dateFrom, dateTo: holiday.dateTo }, periods: holidayPeriods, cutoff: holidayPeriods.at(-1).dateTo,
 groups: { income: [], expense: [{ ...holidayAmounts, id: 1, name: 'Holiday', children: [] }] },
 totals: { income: { ...holidayAmounts, months: Array(10).fill(0), counts: Array(10).fill(0), total: 0, totalCount: 0 }, expense: holidayAmounts, balance: { ...holidayAmounts, months: holidayAmounts.months.map(value => -value), total: -606 } },
}
const holidayMarkup = await renderToString(createReportApp(holiday, holidayReport))
const summaryAmounts = html => [...(html.match(/<section class="report-summary"[^>]*>(.*?)<\/section>/s)?.[1] || '').matchAll(/<strong class="report-summary-amount[^\"]*">([^<]+)<\/strong>/g)].map(match => match[1])
assert.deepEqual(summaryAmounts(holidayMarkup), ['0.00', '6.06', '-6.06'], 'A custom period immediately shows its exact income, expense and signed balance totals')
assert.ok(holidayMarkup.indexOf('class="report-summary"') < holidayMarkup.indexOf('class="annual-chart'), 'Totals are visible before the chart')
const repeatedLabelReport = { ...holidayReport, groups: { income: [], expense: [{ ...holidayAmounts, id: 'important', name: 'Important' }, { ...holidayAmounts, id: 'tax', name: 'Tax relevant' }] } }
assert.deepEqual(summaryAmounts(await renderToString(createReportApp({ ...holiday, grouping: 'labelsAndTags' }, repeatedLabelReport))), ['0.00', '6.06', '-6.06'], 'Overlapping label rows never inflate the summary totals')
for (const status of [{ loading: true }, { error: true }]) {
	assert.deepEqual(summaryAmounts(await renderToString(createReportApp(holiday, holidayReport, status))), [], 'A pending or failed report never displays stale summary figures')
}
const summaryState = { selection: holiday, report: holidayReport, loading: false, error: false, detail: null }
summaryState.openDetails = view.methods.openDetails
summaryState.$router = { push(location) { summaryState.location = location } }
const summaryRows = view.computed.totalRows.call({ ...summaryState, paymentTypes: ['income', 'expense'], $enableIncomes: true, $texts: createReportApp(holiday).config.globalProperties.$texts })
for (const row of summaryRows) {
	view.methods.openSummaryDetails.call(summaryState, row)
	assert.equal(summaryState.location.name, 'annual-overview', 'Summary details remain inside the report')
	summaryState.detail = reportDetails.resolveReportDetail(summaryState.location.query, holidayReport, [], summaryRows, 'Main category')
	summaryState.detail.route = annual.annualPaymentRoute(holiday, holidayPeriods[summaryState.detail.index], row.type)
	assert.equal(summaryState.detail.total, true)
	assert.equal(summaryState.detail.index, 10, 'A summary opens the whole custom period rather than one displayed day')
	assert.equal(summaryState.detail.route.name, 'project-payments')
	assert.deepEqual(summaryState.detail.route.params, { id: '12' })
	assert.equal(summaryState.detail.route.query.type, row.type)
	assert.deepEqual(annual.annualReturnRoute(summaryState.detail.route.query.annualReturn)?.query, annual.annualRouteQuery(holiday), 'Summary drilldowns retain dates, breakdown, area and category filters')
	const metrics = annualCellDetails(summaryState.detail, holidayReport, holiday)
	assert.equal(metrics.amount, row.total)
	assert.equal(metrics.count, row.totalCount)
	if (row.type === 'expense') assert.equal(metrics.averagePerDay, 61, 'Summary details use the actual ten-day vacation for their average')
}
summaryState.loading = true
summaryState.detail = null
view.methods.openSummaryDetails.call(summaryState, summaryRows[1])
assert.equal(summaryState.detail, null, 'A stale summary click cannot open details while the report is changing')
console.log('Report summary passed: custom totals, signed balance, enabled features, duplicate labels, loading state and exact detail drilldowns.')
assert.ok(!holidayMarkup.includes('annual-date-range') && !holidayMarkup.includes('annual-range-caption'), 'Dates no longer consume a separate control row or caption')
assert.ok(holidayMarkup.includes('25.03.2026 – 03.04.2026') && !holidayMarkup.includes('type="date"'), 'The title shows the exact applied range while the date editor is closed')
assert.ok(holidayMarkup.includes('25. März') && holidayMarkup.includes('3. Apr.'), 'Headers come from the actual selected dates')
assert.equal((holidayMarkup.match(/class="annual-chart-line"/g) || []).length, 3)
const rangeDetail = { row: { ...holidayAmounts, type: 'expense' }, index: 10, total: true }
assert.equal(annualCellDetails(rangeDetail, holidayReport, holiday).averagePerDay, 61, 'A ten-day total averages over exactly ten selected days')
const holidayDetailApp = Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: rangeDetail, report: holidayReport, selection: holiday, scopeLabel: 'Holiday area' })
holidayDetailApp.config.globalProperties.$texts = createReportApp(holiday, holidayReport).config.globalProperties.$texts
holidayDetailApp.config.globalProperties.$formatMoneyFromCents = cents => (cents / 100).toFixed(2)
const holidayDetailMarkup = await renderToString(holidayDetailApp)
assert.ok(holidayDetailMarkup.includes('25. März 2026') && holidayDetailMarkup.includes('3. April 2026'), 'The detail dialog names the actual range, including month boundaries')
assert.ok(!holidayDetailMarkup.includes('yearExpenseShare'), 'Range totals never claim to be a calendar-year share')
assert.equal(view.methods.annualExportFilename.call({ selection: holiday }), 'cobudget-auswertung-2026-03-25-2026-04-03-day-area-12-total.csv')
const rangePrintState = { ...printState, loading: false, error: false, printing: false, selection: holiday, report: holidayReport, columns: holidayPeriods.slice(0, -1).map(period => String(period.dateFrom)), selectedRangeLabel: '25.03.2026 – 03.04.2026' }
await view.methods.printReport.call(rangePrintState)
const rangePrint = printCalls.at(-1)[1]
assert.equal(rangePrint.groups.length, 2, 'Daily printing groups the actual range into its two calendar months')
assert.equal(rangePrint.groups[0].columns.length, 7)
assert.equal(rangePrint.groups[1].columns.length, 3)
assert.ok(rangePrint.title.includes('25.03.2026 – 03.04.2026'))
console.log('Report range rendering passed: date controls, exact headings, chart, averages, modal dates, filenames and month-aware printing.')
const longDraft = annual.reportSelectionWithDates(holiday, { dateFrom: '2000-01-01', dateTo: '2026-01-01' })
assert.equal(longDraft.period, 'year', 'Editing a long date span chooses months before validating its column count')
assert.equal(annual.reportRangeError(longDraft), '', 'A long custom span is not blocked by the previously selected daily breakdown')
const unchangedDraft = annual.reportSelectionWithDates({ ...holiday, period: 'week' }, { dateFrom: holiday.dateFrom, dateTo: holiday.dateTo })
assert.equal(unchangedDraft.period, 'week', 'Applying unchanged dates preserves a manual breakdown')

// The title uses the actual boundaries rather than the name of the shortcut.
const headingMonth = (date, options) => new Intl.DateTimeFormat('de-AT', options).format(date)
const headingDate = date => new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
const titleOf = value => annual.reportPeriodLabel(annual.annualSelection({ timeRange: 'custom', ...value }), headingMonth, headingDate)
assert.equal(titleOf({ dateFrom: '2026-01-01', dateTo: '2026-12-31' }), '2026')
assert.equal(titleOf({ dateFrom: '2026-10-01', dateTo: '2026-10-31' }), 'Oktober 2026')
assert.equal(titleOf({ dateFrom: '2024-02-01', dateTo: '2024-02-29' }), 'Februar 2024')
assert.equal(titleOf({ dateFrom: '2026-10-02', dateTo: '2026-10-31' }), '02.10.2026 – 31.10.2026')
assert.equal(titleOf({ dateFrom: '2025-11-01', dateTo: '2026-10-31' }), '01.11.2025 – 31.10.2026')
assert.equal(titleOf({ dateFrom: '2026-02-30', dateTo: '2026-03-02' }), '')
const yearHeading = await renderToString(createReportApp({ year: 2026, timeRange: 'year' }))
assert.ok(yearHeading.includes('report-period-trigger') && yearHeading.includes('2026'))
const monthHeading = await renderToString(createReportApp({ timeRange: 'custom', dateFrom: '2026-10-01', dateTo: '2026-10-31' }))
assert.ok(monthHeading.includes('Oktober 2026'))
assert.ok(!monthHeading.includes('annual-range-caption'))

const picker = bindings.ReportPeriodPicker
const emittedPeriods = []
const pickerState = { selection: holiday, years: [2026, 2024], allowAll: true, $refs: {}, $nextTick: callback => callback(), $emit: (event, value) => emittedPeriods.push({ event, value }) }
Object.assign(pickerState, picker.data.call(pickerState))
for (const [key, method] of Object.entries(picker.methods)) pickerState[key] = method.bind(pickerState)
Object.defineProperty(pickerState, 'yearOptions', { get: () => picker.computed.yearOptions.call(pickerState) })
Object.defineProperty(pickerState, 'proposed', { get: () => picker.computed.proposed.call(pickerState) })
pickerState.shown = true
picker.methods.choose.call(pickerState, 'last-month')
assert.equal(emittedPeriods.length, 1, 'Quick periods apply immediately')
assert.equal(pickerState.shown, false)
assert.equal(emittedPeriods[0].value.period, 'day')
assert.deepEqual(emittedPeriods[0].value.categories, holiday.categories)
assert.equal(emittedPeriods[0].value.projectId, holiday.projectId, 'Changing the title period retains area and filters')
pickerState.shown = true
picker.methods.onShown.call(pickerState, true)
picker.methods.choose.call(pickerState, 'custom')
assert.equal(pickerState.submenu, 'custom')
picker.methods.backToOptions.call(pickerState)
assert.equal(pickerState.submenu, null, 'Returning to the main menu keeps the applied selection unchanged')
assert.equal(emittedPeriods.length, 1)
picker.methods.choose.call(pickerState, 'custom')
pickerState.dates = { dateFrom: '2026-07-11', dateTo: '2026-07-20' }
assert.equal(emittedPeriods.length, 1, 'Editing free dates does not change the applied report')
pickerState.shown = false
picker.methods.onShown.call(pickerState, true)
assert.equal(pickerState.dates.dateFrom, holiday.dateFrom, 'Reopening discards cancelled date edits')
pickerState.dates = { dateFrom: '2026-07-11', dateTo: '2026-07-20' }
picker.methods.apply.call(pickerState)
assert.equal(emittedPeriods.at(-1).value.dateFrom, '2026-07-11')
assert.equal(emittedPeriods.at(-1).value.period, 'day')
pickerState.dates = { dateFrom: '2026-07-21', dateTo: '2026-07-20' }
picker.methods.apply.call(pickerState)
assert.equal(emittedPeriods.length, 2, 'Invalid dates cannot replace the applied range')
picker.methods.choose.call(pickerState, 'year')
picker.methods.chooseYear.call(pickerState, '2024')
assert.equal(emittedPeriods.length, 2, 'A year is applied only on confirmation')
picker.methods.apply.call(pickerState)
assert.equal(emittedPeriods.at(-1).value.dateFrom, '2024-01-01')
assert.equal(emittedPeriods.at(-1).value.dateTo, '2024-12-31')
picker.methods.choose.call(pickerState, 'all')
assert.equal(emittedPeriods.at(-1).value.timeRange, 'all')
assert.deepEqual(emittedPeriods.at(-1).value.categories, holiday.categories)
const allSelection = annual.annualSelection({ ...holiday, timeRange: 'all', dateFrom: '2023-01-01', dateTo: '2026-12-31', search: 'Rent' })
assert.equal(allSelection.timeRange, 'all')
assert.equal(allSelection.dateFrom, '2023-01-01', 'Resolved all-time bounds remain available for drilldowns')
assert.equal(annual.annualRequestParams(allSelection).allTime, true)
assert.equal(annual.annualRequestParams(allSelection).search, 'Rent')
const allRoute = annual.annualRouteQuery(allSelection)
assert.equal(allRoute.dateFrom, undefined, 'All-time bookmarks resolve newly added years on reload')
assert.equal(annual.annualReportSelection(allRoute).timeRange, 'all')
assert.equal(annual.annualRequestParams(annual.annualSelection({ ...allSelection, timeRange: 'custom', dateFrom: '2025-01-01', dateTo: '2025-12-31' })).allTime, undefined, 'A focused detail keeps its explicit dates')
console.log('Title period picker passed: calendar labels, closed editor, preset defaults, scoped filters, draft cancellation, validation and year application.')

// Report calendar selections retain filters and exclude gaps in every request and export.
{
 const selection = annual.annualSelection({ timeRange: 'calendar-selection', periodYears: [2026, 2024, 2024], periodMonths: [3, 1], categories: [2], grouping: 'paymentPartners', projectId: 8, entryScope: 'shared', search: 'rent' })
 assert.deepEqual(selection.periodYears, [2024, 2026]); assert.deepEqual(selection.periodMonths, [1, 3])
 assert.equal(selection.dateFrom, '2024-01-01'); assert.equal(selection.dateTo, '2026-03-31')
 assert.deepEqual(annual.annualReportSelection(annual.annualRouteQuery(selection)), selection)
 assert.equal(annual.annualRequestParams(selection).periodYears, '2024,2026')
 assert.equal(annual.annualRequestParams(selection).periodMonths, '1,3')
 const ranges = annual.reportCalendarRanges(selection)
 assert.deepEqual(ranges.map(range => range.dateFrom), ['2024-01-01', '2024-03-01', '2026-01-01', '2026-03-01'])
 assert.equal(annual.reportCalendarRanges({ ...selection, periodYears: [2024], periodMonths: [1, 2, 3] }).length, 1, 'Adjacent months merge')
 assert.equal(annual.reportRangeError({ ...selection, period: 'day', periodYears: [1970, 9999], periodMonths: [1] }), '', 'Column limits exclude skipped years')
 assert.equal(annual.reportRangeError({ ...selection, period: 'day', periodYears: Array.from({ length: 11 }, (_, i) => 2000 + i), periodMonths: [] }), 'columns')
 const label = annual.reportPeriodLabel(selection, (date, opts) => new Intl.DateTimeFormat('en', opts).format(date), String)
 assert.equal(label, 'Jan, Mar · 2024, 2026')
 const route = annual.annualPaymentRoute(selection)
 assert.equal(route.query.timeRange, 'calendarSelection'); assert.equal(route.query.periodYears, '2024,2026'); assert.equal(route.query.periodMonths, '1,3')
 const paymentParams = reportDetails.reportPaymentParams(route, selection, Number(route.query.dateTo))
 assert.equal(paymentParams.periodYears, '2024,2026'); assert.equal(paymentParams.periodMonths, '1,3')
 const focusedPeriod = { dateFrom: new Date(2024, 2, 1).getTime() / 1000, dateTo: new Date(2024, 3, 1).getTime() / 1000 - 1 }
 const focusedRoute = annual.annualPaymentRoute(selection, focusedPeriod)
 assert.equal(focusedRoute.query.timeRange, 'custom'); assert.equal(focusedRoute.query.periodYears, undefined, 'A single column retains its exact continuous dates')
 const periods = ranges.map(range => ({ dateFrom: annual.parseReportDate(range.dateFrom).getTime() / 1000, dateTo: annual.parseReportDate(range.dateTo).getTime() / 1000 + 86399 }))
 const report = { periods: [...periods, { dateFrom: periods[0].dateFrom, dateTo: periods.at(-1).dateTo }], cutoff: periods.at(-1).dateTo }
 const texts = new Proxy({}, { get: () => new Proxy({}, { get: (_, key) => () => key }) })
 const metadata = exportHelpers.reportExportMetadata(selection, { report, texts, formatDate: stamp => annual.reportDate(new Date(stamp * 1000)), filterLabels: [], scopeLabel: 'Area' })
 assert.equal(metadata[0][1], '2024-01-01 – 2024-01-31; 2024-03-01 – 2024-03-31; 2026-01-01 – 2026-01-31; 2026-03-01 – 2026-03-31')
 const events = []
 const state = { ...pickerState, selection, calendarMultiple: true, singleCalendarPeriod: false, reportCalendarSelection: true, inline: true, calendarYearOptions: [2026, 2025, 2024],
  $emit: (_, value) => { state.selection = annual.annualSelection(value); events.push(state.selection) } }
 for (const [key, method] of Object.entries(picker.methods)) state[key] = method.bind(state)
 Object.defineProperty(state, 'proposed', { get: () => picker.computed.proposed.call(state) })
 state.onShown(true)
 assert.deepEqual(state.calendarYears, ['2024', '2026']); assert.deepEqual(state.calendarMonths, ['1', '3'])
 state.toggleCalendarChoice('calendarYears', 2025, true); state.toggleCalendarChoice('calendarMonths', 5, true)
 assert.deepEqual(events.at(-1).periodYears, [2024, 2025, 2026]); assert.deepEqual(events.at(-1).periodMonths, [1, 3, 5])
 assert.deepEqual(events.at(-1).categories, [2]); assert.equal(events.at(-1).projectId, 8); assert.equal(events.at(-1).entryScope, 'shared')
 state.shown = true
 picker.watch.$route.call(state, { path: '/annual-overview', fullPath: 'new', query: annual.annualRouteQuery(state.selection) }, { path: '/annual-overview', fullPath: 'old' })
 assert.equal(state.shown, true)
 state.selectAllCalendarMonths(true)
 assert.deepEqual(events.at(-1).periodMonths, [], 'All months retains all selected years')
 state.choose('custom'); state.dates = { dateFrom: '2024-02-01', dateTo: '2024-02-29' }; state.apply()
 assert.equal(events.at(-1).timeRange, 'custom'); assert.equal(events.at(-1).periodYears, undefined, 'Custom dates replace calendar restrictions')
}

// Reports share the calendar controls while retaining one exact date interval.
{
 const events = []
 const state = { ...pickerState, selection: annual.annualSelection({ timeRange: 'custom', dateFrom: '2024-02-01', dateTo: '2024-02-29', period: 'week', partners: [4], projectId: 8 }),
  calendarMultiple: true, singleCalendarPeriod: true, inline: true, calendarYearOptions: [2026, 2025, 2024],
  $emit: (_event, value) => { events.push(value); state.selection = value } }
 for (const [key, method] of Object.entries(picker.methods)) state[key] = method.bind(state)
 Object.defineProperty(state, 'proposed', { get: () => picker.computed.proposed.call(state) })
 state.onShown(true)
 assert.deepEqual(state.calendarYears, ['2024']); assert.deepEqual(state.calendarMonths, ['2'])
 assert.equal(state.submenu, null, 'A full-month bookmark reopens its month selection')
 state.toggleCalendarChoice('calendarYears', 2025, true)
 assert.equal(events.at(-1).dateFrom, '2025-02-01'); assert.equal(events.at(-1).dateTo, '2025-02-28')
 assert.deepEqual(events.at(-1).partners, [4]); assert.equal(events.at(-1).projectId, 8); assert.equal(events.at(-1).period, 'week')
 state.toggleCalendarChoice('calendarMonths', 4, true)
 assert.deepEqual(state.calendarMonths, ['4'], 'Another month replaces the previous contiguous report period')
 assert.equal(events.at(-1).dateFrom, '2025-04-01'); assert.equal(events.at(-1).dateTo, '2025-04-30')
 state.selectAllCalendarMonths(true)
 assert.equal(events.at(-1).dateFrom, '2025-01-01'); assert.equal(events.at(-1).dateTo, '2025-12-31')
 state.shown = true
 picker.watch.$route.call(state, { path: '/annual-overview', fullPath: 'new', query: annual.annualRouteQuery(state.selection) }, { path: '/annual-overview', fullPath: 'old' })
 assert.equal(state.shown, true, 'Immediate report period updates leave the picker open')
 state.singleCalendarPeriod = false
 state.toggleCalendarChoice('calendarYears', 2024, true)
 assert.deepEqual(events.at(-1).periodYears, [2024, 2025], 'Payments retain multiple year selection')
 assert.equal(events.at(-1).timeRange, 'calendar-selection')
}


// Detail addresses resolve authorized report rows, never labels or amounts from a link.
const detailRows = [{ ...holidayAmounts, id: 7, type: 'expense', label: 'Home', children: [
	{ ...holidayAmounts, id: 7, name: 'Home', direct: true }, { ...holidayAmounts, id: 8, name: 'Rent', direct: false },
] }, { ...holidayAmounts, id: 0, type: 'expense', label: 'Unassigned', children: [] }]
for (const selectedRow of [detailRows[0], { ...detailRows[0].children[0], type: 'expense' }, { ...detailRows[0].children[1], type: 'expense' }, detailRows[1]]) {
	const query = reportDetails.reportDetailQuery({ row: selectedRow, index: 10, total: false }, holidayPeriods)
	const resolved = reportDetails.resolveReportDetail(query, holidayReport, detailRows, summaryRows, 'Main category')
	assert.equal(resolved.row.id, selectedRow.id)
	assert.equal(Boolean(resolved.row.direct), Boolean(selectedRow.direct))
	assert.equal(resolved.index, 10)
	assert.equal(resolved.row.total, 606)
	if (selectedRow.direct) assert.equal(resolved.label, 'Home · Main category')
	const route = annual.annualPaymentRoute(holiday, holidayPeriods[10], resolved.row.type, resolved.row)
	if (selectedRow.children?.length) assert.equal(route.query.includeSubcategories, undefined, 'Partner grouping never treats a parent-looking row as a category')
}
for (const invalid of [{ detailId: '999999' }, { detailPeriod: '-1' }, { detailPeriod: '9999' }, { detailType: 'all', detailId: '7' }]) {
	assert.equal(reportDetails.resolveReportDetail({ detailType: 'expense', detailId: '7', detailPeriod: 'total', ...invalid }, holidayReport, detailRows, summaryRows, 'Main'), null)
}
assert.equal(reportDetails.resolveReportDetail({ detailType: 'income', detailId: 'total' }, holidayReport, detailRows, summaryRows.filter(row => row.type === 'expense'), 'Main'), null, 'Hidden income management cannot be reopened through a detail address')
const listRoute = annual.annualPaymentRoute(holiday, holidayPeriods[10], 'expense', { id: 4 })
const listParams = reportDetails.reportPaymentParams(listRoute, holiday, holidayPeriods[6].dateTo, { limit: 25, offset: 25, sortBy: 'amount', sortDir: 'asc' })
assert.equal(listParams.entryScope, 'shared')
assert.equal(listParams.projectId, 12)
assert.equal(listParams.categoryId, '7')
assert.equal(listParams.paymentPartnerId, '4')
assert.equal(listParams.dateFrom, holidayPeriods[0].dateFrom)
assert.equal(listParams.dateTo, holidayPeriods[6].dateTo, 'Saved payments stop at the same cutoff as the report')
assert.equal(listParams.offset, 25)
const labelSelection = annual.annualSelection({ ...holiday, projectId: null, grouping: 'labelsAndTags' })
const labelRoute = annual.annualPaymentRoute(labelSelection, holidayPeriods[10], 'expense', { kind: 'label', filter: 'important' })
const labelParams = reportDetails.reportPaymentParams(labelRoute, labelSelection, holidayPeriods[10].dateTo)
assert.equal(labelParams.entryScope, 'personal')
assert.equal(labelParams.isImportant, true)
assert.equal(labelParams.reportGrouping, 'labelsAndTags', 'Saved label totals exclude unlabelled payments on the server')

const viewport = { scrollTop: 420 }
const horizontalTable = { scrollLeft: 120 }
const navigation = { selection: holiday, report: holidayReport, $refs: { printSource: horizontalTable }, $el: { closest: () => viewport }, $nextTick: callback => callback(), load() { throw Error('Detail navigation must not reload the overview') } }
navigation.rememberOverviewPosition = view.methods.rememberOverviewPosition
navigation.restoreOverviewPosition = view.methods.restoreOverviewPosition
const overviewQuery = annual.annualRouteQuery(holiday)
const focusQuery = { ...overviewQuery, detailType: 'expense', detailId: '7', detailPeriod: 'total' }
const overviewSelection = navigation.selection
view.watch['$route.query'].handler.call(navigation, focusQuery, overviewQuery)
assert.equal(viewport.scrollTop, 0)
assert.equal(navigation.openedDetailFromOverview, true)
viewport.scrollTop = 100
view.watch['$route.query'].handler.call(navigation, { ...focusQuery, detailPage: '2' }, focusQuery)
assert.equal(navigation.selection, overviewSelection, 'Paging retains the selection object so charts and comparisons do not restart')
assert.equal(viewport.scrollTop, 100, 'Paging payments does not reset the detail page scroll')
horizontalTable.scrollLeft = 0
view.watch['$route.query'].handler.call(navigation, overviewQuery, focusQuery)
assert.equal(viewport.scrollTop, 420, 'Browser Back restores the overview scroll')
assert.equal(horizontalTable.scrollLeft, 120, 'Browser Back restores wide-table scrolling')
let historyBack = 0, replacedOverview = null
view.methods.closeDetails.call({ ...navigation, $router: { back() { historyBack++ } } })
view.methods.closeDetails.call({ selection: holiday, openedDetailFromOverview: false, $router: { replace(route) { replacedOverview = route } } })
assert.equal(historyBack, 1)
assert.deepEqual(replacedOverview, { name: 'annual-overview', query: overviewQuery }, 'A directly opened detail has a working fallback back link')

const detailComponent = bindings.ReportDetailView
const focused = { ...rangeDetail, label: 'Holiday', row: { ...holidayAmounts, id: 1, type: 'expense', label: 'Holiday' }, route: listRoute }
const chartContext = { detail: focused, report: holidayReport, focusedReport: null }
assert.deepEqual(detailComponent.computed.chartData.call(chartContext).totals.expense.months, holidayAmounts.months, 'An individual chart uses the selected row rather than other categories')
let cellChartParams
const savedDetailGet = bindings.axios.get
bindings.axios.get = async (_url, options) => { if (options.params.comparison === 'detail') cellChartParams = options.params; return { data: holidayReport } }
const cellChart = { ...detailComponent.data(), detail: { ...focused, index: 0 }, report: holidayReport, selection: holiday, wholePeriod: false }
await detailComponent.methods.loadChart.call(cellChart)
assert.equal(cellChartParams.dateFrom, '2026-03-25')
assert.equal(cellChartParams.dateTo, '2026-03-25')
assert.equal(cellChartParams.period, 'day', 'A selected cell gets a finer chart within exactly its own dates')
assert.equal(cellChart.focusedReport, holidayReport)
bindings.axios.get = async (_url, { params }) => {
 assert.equal(params.comparison, undefined, 'A future cell only loads its annual reference, without requesting a forecast')
 assert.equal(params.includeFuturePayments, false)
 return { data: report }
}
const emptyFutureChart = { ...detailComponent.data(), detail: { ...focused, index: 2 }, report: { ...holidayReport, cutoff: holidayPeriods[0].dateTo }, selection: { ...holiday, includeFuturePayments: false }, wholePeriod: false }
await detailComponent.methods.loadChart.call(emptyFutureChart)
assert.equal(emptyFutureChart.chartError, false)
assert.equal(emptyFutureChart.focusedReport.totals.expense.total, 0, 'Future columns respect the overview cutoff instead of silently enabling forecasts')
bindings.axios.get = async () => ({ data: { entries: [], total: 0 } })
const detailMarkup = await renderToString(createReportApp(holiday, holidayReport, { query: { ...overviewQuery, detailType: 'expense', detailId: '1', detailPeriod: 'total' } }))
const visibleDetail = detailMarkup.slice(detailMarkup.indexOf('class="report-detail"'))
assert.ok(visibleDetail.indexOf('class="annual-chart') < visibleDetail.indexOf('annual-details--embedded'), 'The expanded chart precedes detail metrics')
assert.ok(visibleDetail.indexOf('annual-details--embedded') < visibleDetail.indexOf('data-report-section="detail-payments"'), 'The payment list follows the metrics')
assert.match(visibleDetail, /class="report-development"[^>]*data-report-section="detail-chart"/, 'A detail chart starts expanded independently of the overview preference')
assert.ok(!/\srole="dialog"/.test(visibleDetail), 'Report details use a page rather than a modal')
bindings.axios.get = savedDetailGet
console.log('Report detail pages passed: parent/child identities, deep links, feature limits, cutoffs, scoped payments, history, scroll restoration and expanded charts.')

// Financial perspective is independent of the selected area and survives every
// request, detail address and return link. Personal amounts are the default.
assert.equal(annual.annualSelection({ projectId: 12 }).entryScope, 'personal')
assert.equal(annual.annualSelection({ entryScope: 'shared' }).entryScope, 'personal', 'My finances cannot display shared totals')
assert.equal(annual.annualSelection({ projectId: 12, entryScope: 'invalid' }).entryScope, 'personal')
for (const entryScope of ['personal', 'shared']) {
	const chosen = annual.annualSelection({ ...holiday, entryScope })
	const chosenQuery = annual.annualRouteQuery(chosen)
	assert.deepEqual(annual.annualReportSelection(chosenQuery), chosen)
	assert.equal(annual.annualRequestParams(chosen).entryScope, entryScope)
	assert.equal(annual.annualRequestParams(chosen, { comparison: 'year' }).entryScope, entryScope)
	const paymentRoute = annual.annualPaymentRoute(chosen, holidayPeriods[10], 'expense', { id: 4 })
	assert.equal(paymentRoute.name, entryScope === 'shared' ? 'project-payments' : 'payments')
	if (entryScope === 'personal') assert.equal(routes.parsePersonalEntryListRouteQuery(paymentRoute.query).filters.projectId, 12)
	assert.deepEqual(annual.annualReturnRoute(paymentRoute.query.annualReturn)?.query, chosenQuery)
	assert.equal(reportDetails.reportPaymentParams(paymentRoute, chosen, holidayPeriods[10].dateTo).entryScope, entryScope)
	assert.equal(bindings.ReportPayments.methods.amount.call({ selection: chosen }, { amount_cents: 10001, personal_amount_cents: 4001 }), entryScope === 'shared' ? 100.01 : 40.01)
	assert.equal(view.methods.annualExportFilename.call({ selection: chosen }), `cobudget-auswertung-2026-03-25-2026-04-03-day-area-12-${entryScope === 'shared' ? 'total' : 'personal'}.csv`)
}
const scopeBase = annual.annualReportSelection({ year: currentYear, timeRange: 'year' })
for (const change of [{ projectId: 12 }, { grouping: 'paymentPartners' }, { period: 'week' }, { includeFuturePayments: true }, { partners: [4] }, { amountMin: 123 }]) {
	let loads = 0
	const state = { selection: annual.annualSelection({ ...scopeBase, ...change }), report, load() { loads++ }, $nextTick: callback => callback() }
	// v-model has already assigned the new value before the URL watcher runs.
	view.watch['$route.query'].handler.call(state, annual.annualRouteQuery(state.selection), annual.annualRouteQuery(scopeBase))
	assert.equal(loads, 1, `Changing ${Object.keys(change)[0]} actually reloads financial data`)
}
let amountScopeLoads = 0
const amountScopeState = { selection: annual.annualSelection({ ...scopeBase, projectId: 12, entryScope: 'shared' }), report, load() { amountScopeLoads++ }, $nextTick: callback => callback() }
view.watch['$route.query'].handler.call(amountScopeState, annual.annualRouteQuery(amountScopeState.selection), annual.annualRouteQuery({ ...scopeBase, projectId: 12 }))
assert.equal(amountScopeLoads, 1, 'The personal/entire-area switch reloads data even after v-model changed selection')
let selectedAreaQuery
const selectedArea = { selection: { ...scopeBase, projectId: 13, entryScope: 'shared' }, projects: [{ id: 13, name: 'Home' }], $enableProjects: true, updateSelection: view.methods.updateSelection, $router: { replace(route) { selectedAreaQuery = route.query } } }
view.methods.selectProject.call(selectedArea, 13)
assert.equal(selectedArea.selection.entryScope, 'personal', 'Choosing another area starts with the personal share')
assert.equal(selectedAreaQuery.entryScope, undefined)
view.methods.selectProject.call(selectedArea, 0)
assert.equal(selectedArea.selection.projectId, null)
assert.equal(selectedArea.selection.withoutArea, true, 'Without area is distinct from the all-payments aggregate')
assert.equal(selectedAreaQuery.withoutArea, 'true', 'Bookmarks preserve the without-area report scope')
assert.equal(annual.annualRequestParams(selectedArea.selection).withoutArea, 'true')
const unassignedDrilldown = annual.annualPaymentRoute(selectedArea.selection)
assert.equal(unassignedDrilldown.query.projects, '0', 'Report-to-payment links retain the without-area population')
assert.equal(routes.parsePersonalEntryListRouteQuery(unassignedDrilldown.query).filters.narrowProjects, '0')
assert.equal(annual.annualFilterChips(selectedArea.selection, { withoutArea: 'Ohne Bereich' })[0].label, 'Ohne Bereich')
const clearedUnassigned = { ...selectedArea, updateSelection() {} }
view.methods.clearFilter.call(clearedUnassigned, 'withoutArea')
assert.equal(clearedUnassigned.selection.withoutArea, false, 'Closing the chip clears only the unassigned predicate')
assert.equal(clearedUnassigned.selection.projectId, null)
view.methods.selectProject.call(selectedArea, 13)
assert.equal(Boolean(selectedArea.selection.withoutArea), false, 'Choosing an area clears the unassigned predicate')
view.methods.selectProject.call(selectedArea, 0)
view.methods.selectProject.call(selectedArea, null)
assert.equal(selectedArea.selection.projectId, null)
assert.equal(Boolean(selectedArea.selection.withoutArea), false, 'All payments includes every authorized area again')
const validScope = selectedArea.selection
view.methods.selectProject.call(selectedArea, 999)
assert.equal(selectedArea.selection, validScope, 'Unknown area options cannot change report scope')
selectedArea.selectProject = view.methods.selectProject
view.methods.selectAreas.call(selectedArea, [0, 13])
assert.equal(selectedArea.selection.projectId, null, 'Multiple areas use personal payments instead of one shared source scope')
assert.equal(Boolean(selectedArea.selection.withoutArea), false, 'Unassigned payments are an OR choice alongside named areas')
assert.equal(selectedAreaQuery.projects, '0,13', 'Area checkboxes survive bookmarks and reloads')
assert.equal(annual.annualRequestParams(selectedArea.selection).projects, '0,13')
const multiAreaRoute = annual.annualPaymentRoute(selectedArea.selection, holidayPeriods[0], 'expense')
assert.equal(multiAreaRoute.query.projects, '0,13', 'Payment drill-downs retain all selected areas')
assert.equal(reportDetails.reportPaymentParams(multiAreaRoute, selectedArea.selection, holidayPeriods[0].dateTo).projects, '0,13', 'Saved payments and Top 10 use the same population as report totals')
assert.deepEqual(annual.annualReturnRoute(multiAreaRoute.query.annualReturn).query, selectedAreaQuery, 'Returning from payments retains the area union')
assert.deepEqual(annual.annualSelection({ ...scopeBase, projects: '0,13,13,-1,bad' }).projects, [0, 13], 'Area IDs normalize without duplicating amounts')
view.methods.selectAreas.call(selectedArea, [13])
assert.equal(selectedArea.selection.projectId, 13, 'A single area retains its personal/shared perspective control')
assert.equal(selectedAreaQuery.projects, undefined, 'Returning to one area removes the multi-area constraint')
view.methods.selectAreas.call(selectedArea, [])
assert.equal(selectedAreaQuery.projectId, undefined)
assert.equal(selectedAreaQuery.projects, undefined, 'All payments removes every area constraint')
{
	let next
	const state = { options: [{ value: null }, { value: 0 }, { value: 13 }], modelValue: [13], $emit: (_event, value) => { next = value } }
	bindings.ReportAreaSelect.methods.toggle.call(state, 0)
	assert.deepEqual(next, [13, 0], 'Without area may be checked alongside a named area')
	state.modelValue = next
	bindings.ReportAreaSelect.methods.toggle.call(state, 13)
	assert.deepEqual(next, [0], 'Unchecking one area retains the others')
	bindings.ReportAreaSelect.methods.toggle.call(state, null)
	assert.deepEqual(next, [], 'All payments clears the complete selection')
	let focused = false
	const keyboard = { shown: true, $nextTick: callback => callback(), $refs: { trigger: { $el: { focus: () => { focused = true } } } } }
	bindings.ReportAreaSelect.methods.close.call(keyboard)
	assert.equal(keyboard.shown, false)
	assert.equal(focused, true, 'Escape returns keyboard focus to the area trigger')
}
for (const memberCount of [1, 2]) {
	const html = await renderToString(createReportApp({ ...scopeBase, projectId: 12 }, report, { areas: [{ id: 12, name: 'Home', member_count: memberCount }] }))
	assert.equal(html.includes('aria-label="amountScope"'), memberCount > 1, 'Only shared areas need a second financial-perspective dropdown')
}
const wholeAreaLabel = view.computed.reportScopeLabel.call({ isSharedArea: true, scopeLabel: 'Home', amountScopeLabel: 'Entire area' })
assert.equal(wholeAreaLabel, 'Home · Entire area')
await view.methods.printReport.call({ ...rangePrintState, reportScopeLabel: wholeAreaLabel })
assert.equal(printCalls.at(-1)[1].details[0], wholeAreaLabel, 'Print output identifies the chosen amounts')
console.log('Report amount perspectives passed: personal default, area totals, actual filter reloads, exact payment amounts, comparisons, links, catalogs, mobile controls and export context.')

// Detail comparisons share a single data source with the chart, including total rows.
const reference = { ...change, id: 'previous', kind: 'period', unit: 'month', available: true, values: [300, 200, null], periods: statsReport.periods.slice(0, 3) }
const meanReference = { ...reference, id: 'average-selection', kind: 'selection-average', count: 2, fullAverage: 101, values: [100, 101, null] }
const noReference = { ...reference, id: 'year', available: false, previous: null, delta: null, values: [] }
const refTexts = modalApp.config.globalProperties.$texts
const comparisons = [reference, noReference, meanReference]
assert.deepEqual(comparisonHelpers.reportComparisonSeries(comparisons, [], 3, false, refTexts, bindings.formatCalendarLabel), [], 'No comparison lines are selected by default')
const individually = comparisonHelpers.reportComparisonSeries(comparisons, ['previous', 'year', 'average-selection'], 4, false, refTexts, bindings.formatCalendarLabel)
assert.equal(individually.length, 2, 'Unavailable references cannot become curves')
assert.deepEqual(individually[0].values, [300, 200, null, null], 'A shorter reference has no invented extra day')
assert.ok(individually[0].titles[0].includes('2026'), 'Reference tooltip identifies its actual dates')
assert.equal(individually[1].titles[0], '', 'A fixed average reference needs no distribution caption')
assert.deepEqual(individually[1].values, [101, 101, null, null], 'Every plotted average value equals the table’s rounded average')
const accumulated = comparisonHelpers.reportComparisonSeries(comparisons, ['previous', 'average-selection'], 4, true, refTexts, bindings.formatCalendarLabel)
assert.deepEqual(accumulated[0].values, [300, 500, null, null])
assert.deepEqual(accumulated[1].values, [101, 101, null, null], 'Running total mode keeps the average as a fixed benchmark')
const totalComparisonApp = Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: { ...detail, total: true }, report: statsReport, selection: statsSelection, scopeLabel: 'Home', embedded: true, comparisonData: comparisons })
totalComparisonApp.config.globalProperties.$texts = refTexts
totalComparisonApp.config.globalProperties.$formatMoneyFromCents = modalApp.config.globalProperties.$formatMoneyFromCents
const totalComparisonHtml = await renderToString(totalComparisonApp)
assert.equal((totalComparisonHtml.match(/class="annual-details-comparison"/g) || []).length, 1, 'Totals render available period comparisons alongside one simple average metric')
assert.equal((totalComparisonHtml.match(/class="annual-reference-amount"/g) || []).length, 1, 'Period reference amounts are visible in addition to their differences')
assert.ok(!/comparisonNoData|comparisonClosedPeriods|comparisonFullAverage|comparisonElapsed/.test(totalComparisonHtml), 'The table contains no unavailable rows or average-progress explanations')
let modelUpdate
bindings.ReportComparisonPicker.methods.toggle.call({ modelValue: ['previous'], $emit: (_, value) => { modelUpdate = value } }, 'average-selection', true)
assert.deepEqual(modelUpdate, ['previous', 'average-selection'])
bindings.ReportComparisonPicker.methods.toggle.call({ modelValue: modelUpdate, $emit: (_, value) => { modelUpdate = value } }, 'previous', false)
assert.deepEqual(modelUpdate, ['average-selection'], 'Each checkbox is independently selectable')
assert.deepEqual(bindings.ReportComparisonPicker.computed.options.call({ comparisons }).map(value => value.id), ['previous', 'average-selection'], 'The picker contains only available references')
const overlayApp = Vue.createSSRApp(bindings.AnnualOverviewChart, { report: statsReport, labels: ['1', '2', '3', '4'], comparisonSeries: individually, initialOpen: true, rememberState: false, formatCents: cents => `€ ${cents}` })
overlayApp.config.globalProperties.$texts = refTexts
const overlayHtml = await renderToString(overlayApp)
assert.ok(overlayHtml.includes('annual-chart-series--comparison-previous') && overlayHtml.includes('annual-chart-series--comparison-average-selection'), 'References appear as distinguishable curves and in the legend')
const padded = annualChart.annualChartSeries({ ...statsReport, totals: { ...statsReport.totals, expense: { months: [10, 20] } } }, 3, { incomeEnabled: false })
assert.deepEqual(padded[0].values, [10, 20, null], 'When a reference is longer than the target month, the target has no fictitious payment at the extra position')
const savedComparisonGet = bindings.axios.get
const comparisonResolvers = []
bindings.axios.get = (_url, options) => { comparisonResolvers.push({ resolve: null, params: options.params }); return new Promise(resolve => { comparisonResolvers.at(-1).resolve = resolve }) }
const monthState = { ...detailComponent.data(), detail: { ...focused, total: true, row: { ...focused.row, type: 'expense' } }, wholePeriod: true, report: holidayReport, selection: holiday }
const pendingComparisons = detailComponent.methods.loadChart.call(monthState)
assert.equal(comparisonResolvers.length, 1, 'Chart and comparison rows use one request rather than separate requests per reference')
assert.equal(comparisonResolvers[0].params.comparison, 'detail')
assert.equal(comparisonResolvers[0].params.detailId, 'total')
assert.equal(comparisonResolvers[0].params.entryScope, 'shared')
monthState.chartRequest++
comparisonResolvers[0].resolve({ data: { ...holidayReport, detailComparisons: comparisons } })
await pendingComparisons
assert.equal(monthState.focusedReport, null, 'A delayed response cannot populate a different detail page')
bindings.axios.get = async () => { throw Error('offline') }
await detailComponent.methods.loadChart.call(monthState)
assert.equal(monthState.chartError, true)
assert.equal(monthState.chartLoading, false)
bindings.axios.get = savedComparisonGet
console.log('Expanded detail comparisons passed: total references, explicit empty history, multi-selection, real dates, fixed average references, unequal month lengths, scoped requests, stale replies and errors.')

// A first-year report still has a useful average: 324 euros across January to October = 32.40.
const samplePeriods = Array.from({ length: 12 }, (_, month) => ({ dateFrom: new Date(2026, month, 1).getTime() / 1000, dateTo: new Date(2026, month + 1, 1).getTime() / 1000 - 1 }))
const sampleRow = { months: Array.from({ length: 12 }, (_, month) => month === 7 ? 32400 : 0), total: 32400, totalCount: 1, counts: Array.from({ length: 12 }, (_, month) => month === 7 ? 1 : 0) }
const sampleReport = { totals: { income: { ...sampleRow, months: Array(12).fill(0), total: 0, totalCount: 0, counts: Array(12).fill(0) }, expense: sampleRow, balance: { ...sampleRow, months: sampleRow.months.map(value => -value), total: -32400 } }, periods: [...samplePeriods, { dateFrom: samplePeriods[0].dateFrom, dateTo: samplePeriods.at(-1).dateTo }], cutoff: new Date(2026, 9, 22, 12).getTime() / 1000 }
const sampleAverage = comparisonHelpers.reportSelectionAverage(sampleReport, 'expense', 'year')
assert.equal(sampleAverage.fullAverage, 3240)
assert.equal(sampleAverage.count, 10, 'All started months count, including those without this category')
assert.deepEqual(sampleAverage.values, [...Array(10).fill(3240), null, null], 'The existing monthly average becomes a flat line and does not extend into future months')
assert.equal(comparisonHelpers.reportComparisonLabel(sampleAverage, refTexts), refTexts.annual.averagePerMonth())
assert.equal(annualCellDetails({ row: { ...sampleRow, type: 'expense' }, index: 12 }, sampleReport, { year: 2026, period: 'year' }).averagePerMonth, sampleAverage.fullAverage, 'Metric and chart use the same 32.40-euro average')
const sampleBenchmark = comparisonHelpers.reportComparisonSeries([sampleAverage], ['average-selection'], 12, true, refTexts, bindings.formatCalendarLabel)[0]
assert.equal(sampleBenchmark.values[9], 3240, 'The average stays at 32.40 euros in Running total mode')
assert.equal(sampleBenchmark.values[10], null)
assert.equal(sampleBenchmark.titles[7], '', 'An average tooltip does not invent dates or describe a daily distribution')
assert.equal(comparisonHelpers.reportSelectionAverage(sampleReport, 'all', 'year').fullAverage, -3240, 'Balance averages preserve their sign')
const firstYearOptions = detailComponent.computed.comparisons.call({ chartData: sampleReport, detail: { row: { type: 'expense' } }, selection: { period: 'year' }, focusedReport: { detailComparisons: [noReference] } })
assert.deepEqual(firstYearOptions.map(value => value.id), ['average-selection'], 'The first year has a selectable average even without previous-year data')
for (const [period, unit] of [['day', 'day'], ['week', 'week'], ['quarter', 'quarter']]) {
 assert.equal(comparisonHelpers.reportSelectionAverage(sampleReport, 'expense', period).unit, unit, 'The average follows the chart breakdown')
}
const centAverage = comparisonHelpers.reportSelectionAverage({ ...sampleReport, totals: { expense: { ...sampleRow, months: [101, ...Array(11).fill(0)], total: 101 } } }, 'expense', 'year')
assert.ok(centAverage.values.every(value => value === null || value === 10), 'Cent rounding produces the same ten-cent average at every chart position')
assert.equal(centAverage.fullAverage, 10)
assert.equal(comparisonHelpers.reportSelectionAverage({ ...sampleReport, cutoff: samplePeriods[0].dateFrom - 1 }, 'expense', 'year'), null, 'No average is created before the selected period begins')
const basicGlobals = app => { app.config.globalProperties.$route ||= {query:{}}; Object.assign(app.config.globalProperties,Object.fromEntries(['Projects','Receipts','FixedCosts','Subscriptions','ChildRelated','ImportantPayments','ReviewPayments','TaxRelevant','Insurance'].map(name=>['$enable'+name,true]))); app.config.globalProperties.$texts = refTexts; app.config.globalProperties.$currency = 'EUR'; app.config.globalProperties.$formatMoneyFromCents = modalApp.config.globalProperties.$formatMoneyFromCents; app.config.globalProperties.$formatSignedMoney = amount => new Intl.NumberFormat('de-AT', { style: 'currency', currency: 'EUR', signDisplay: 'always' }).format(amount); return app }
const simplePicker = { ...bindings.ReportComparisonPicker, data: () => ({ shown: true }) }
const pickerMarkup = await renderToString(basicGlobals(Vue.createSSRApp(simplePicker, { comparisons: [sampleAverage, noReference] })))
assert.ok(pickerMarkup.includes('averagePerMonth') && !pickerMarkup.includes('comparisonLastYear'), 'Only the usable monthly average is listed')
assert.ok(!/comparisonNoData|comparisonSelectAll|comparisonClear|comparisonAverageHint/.test(pickerMarkup), 'The compact picker has no unavailable choices, bulk actions or explanatory paragraph')
const emptyPickerMarkup = await renderToString(basicGlobals(Vue.createSSRApp(simplePicker, { comparisons: [noReference] })))
assert.ok(!emptyPickerMarkup.includes('report-comparison-trigger'), 'An empty comparison menu is not displayed')
const onlyCurrentTable = await renderToString(basicGlobals(Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: { row: { ...sampleRow, type: 'expense' }, index: 12, label: 'Category' }, report: sampleReport, selection: { year: 2026, period: 'year' }, scopeLabel: 'Home', embedded: true, comparisonData: [sampleAverage, noReference] })))
assert.ok(!onlyCurrentTable.includes('annual-details-comparison'), 'The existing monthly metric is not duplicated as a comparison between a monthly average and an annual total')
assert.ok(onlyCurrentTable.includes('32.40'), 'The original monthly metric remains visible')
console.log('Current detail averages passed: first-year 324/10 example, sparse categories, signs, cutoff, cents, constant references in both chart modes, compact menus and omitted unavailable rows.')

// One average follows the selected range and payment cutoff in the chart and table.
const fullAverageReport = { ...sampleReport, cutoff: samplePeriods.at(-1).dateTo }
const fullAverage = comparisonHelpers.reportSelectionAverage(fullAverageReport, 'expense', 'year')
assert.equal(fullAverage.count, 12)
assert.equal(fullAverage.fullAverage, 2700, 'The full year divides 324 euros by all twelve months')
assert.deepEqual(fullAverage.values, Array(12).fill(2700))
const currentOnlyRow = { ...sampleRow, months: Array.from({ length: 12 }, (_, index) => index === 9 ? 32400 : 0) }
assert.equal(comparisonHelpers.reportSelectionAverage({ ...sampleReport, totals: { ...sampleReport.totals, expense: currentOnlyRow } }, 'expense', 'year').fullAverage, 3240, 'The ongoing month and its payments are included in the simple average')
const historicalMean = { ...meanReference, id: 'average-year', kind: 'average' }
const historicMean = { ...historicalMean, id: 'average-history' }
const singleMeanOptions = detailComponent.computed.comparisons.call({ chartData: sampleReport, detail: { row: { type: 'expense' } }, selection: { period: 'year' }, focusedReport: { detailComparisons: [reference, historicalMean, historicMean] } })
assert.deepEqual(singleMeanOptions.map(value => value.id), ['average-selection', 'previous'], 'The detail has exactly one average even if a stale response contains historical variants')
assert.deepEqual(comparisonHelpers.reportComparisonSeries([historicalMean, historicMean], ['average-year', 'average-history'], 12, false, refTexts, bindings.formatCalendarLabel), [], 'Historical-average curves are not plotted')
const simplifiedMenu = await renderToString(basicGlobals(Vue.createSSRApp(simplePicker, { comparisons: [sampleAverage, historicalMean, historicMean] })))
assert.equal((simplifiedMenu.match(/averagePerMonth/g) || []).length, 1, 'The menu offers one unqualified monthly average')
assert.ok(!/comparisonHistory|comparisonClosedPeriods|comparisonFullAverage|comparisonElapsed/.test(simplifiedMenu))
const simplifiedTable = async (report, average, detailIndex = 12) => renderToString(basicGlobals(Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: { row: { ...sampleRow, type: 'expense' }, index: detailIndex, label: 'Category' }, report, selection: { year: 2026, period: 'year' }, scopeLabel: 'Home', embedded: true, comparisonData: [average, historicalMean, historicMean] })))
const fullAverageHtml = await simplifiedTable(fullAverageReport, fullAverage)
assert.ok(fullAverageHtml.includes('27.00'), 'The full-year table matches its 27-euro chart average')
assert.equal((fullAverageHtml.match(/annual-details-average/g) || []).length, 1)
assert.ok(!/comparisonClosedPeriods|comparisonFullAverage|comparisonElapsed|comparisonHistory/.test(fullAverageHtml), 'The table omits historical and progress captions')
const cellAverageHtml = await simplifiedTable(sampleReport, sampleAverage, 9)
assert.equal((cellAverageHtml.match(/annual-details-average/g) || []).length, 1, 'A focused detail uses its chart average even when opened from a period cell')
console.log('Simplified report averages passed: one shared metric/curve, current month included, full-year denominator, hidden legacy variants and removed progress notes.')

// A month/week detail compares its amount with the corresponding annual mean,
// independently of the chart's finer daily resolution.
const dayPeriods = (from, to) => {
 const result = []
 for (let date = new Date(from); date <= to; date.setDate(date.getDate() + 1)) {
  const next = new Date(date.getFullYear(), date.getMonth(), date.getDate() + 1)
  result.push({ dateFrom: date.getTime() / 1000, dateTo: next.getTime() / 1000 - 1 })
 }
 return result
}
const reportForRow = (periods, row, cutoff, id = 1) => ({
 periods: [...periods, { dateFrom: periods[0].dateFrom, dateTo: periods.at(-1).dateTo }], cutoff,
 groups: { income: [], expense: [{ ...row, id, children: [] }] },
 totals: { income: { months: periods.map(() => 0), total: 0, totalCount: 0 }, expense: row, balance: { ...row, months: row.months.map(value => -value), total: -row.total } },
})
const annualCategoryRow = { ...sampleRow, months: sampleRow.months.map((_, index) => index === 7 ? 22400 : index === 9 ? 10000 : 0), counts: sampleRow.counts.map((count, index) => index === 9 ? 1 : count), totalCount: 2 }
const annualCategory = reportForRow(samplePeriods, annualCategoryRow, sampleReport.cutoff)
const octoberDays = dayPeriods(new Date(2026, 9, 1), new Date(2026, 9, 31))
const octoberAmounts = octoberDays.map((_, index) => index === 9 ? 10000 : 0)
const octoberCategoryRow = { months: octoberAmounts, counts: octoberAmounts.map(value => value ? 1 : 0), total: 10000, totalCount: 1 }
const octoberCategory = reportForRow(octoberDays, octoberCategoryRow, sampleReport.cutoff)
const categoryDetail = { row: { ...annualCategoryRow, id: 1, type: 'expense' }, total: false, index: 9, label: 'Home', route: listRoute }
const annualCategorySelection = { ...scopeBase, year: 2026, period: 'year', timeRange: 'year', dateFrom: '2026-01-01', dateTo: '2026-12-31' }
const octoberSelection = { ...annualCategorySelection, timeRange: 'custom', period: 'day', dateFrom: '2026-10-01', dateTo: '2026-10-31' }
const annualBasis = comparisonHelpers.reportAnnualAverageBasis(categoryDetail, annualCategory, annualCategorySelection)
assert.deepEqual(annualBasis, { year: 2026, unit: 'month', period: 'year', dateFrom: '2026-01-01', dateTo: '2026-12-31' })
assert.equal(comparisonHelpers.reportMatchesAverageBasis(annualCategory, annualCategorySelection, annualBasis), true, 'The annual overview already supplies the monthly mean without another year request')
const octoberComparisonsState = { chartData: octoberCategory, detail: categoryDetail, selection: annualCategorySelection, focusedSelection: octoberSelection, focusedReport: octoberCategory, averageBasis: annualBasis, averageReport: annualCategory, report: annualCategory }
const octoberReferences = detailComponent.computed.comparisons.call(octoberComparisonsState)
assert.equal(octoberReferences.length, 3, 'The category offers annual monthly, quarterly and weekly means')
const annualMonthlyMean = octoberReferences[0]
assert.equal(annualMonthlyMean.fullAverage, 3240, 'October compares with 324 euros / ten months rather than its own daily mean')
assert.equal(annualMonthlyMean.unit, 'month')
assert.equal(annualMonthlyMean.count, 10, 'The current month and empty months count toward the year mean')
assert.equal(comparisonHelpers.reportComparisonLabel(annualMonthlyMean, refTexts), `${refTexts.annual.averagePerMonth()} · 2026`)
assert.equal(annualMonthlyMean.values[22], null, 'The projected reference respects the detail cutoff')
const fullOctoberMean = comparisonHelpers.reportAverageForPeriod(annualMonthlyMean, { ...octoberCategory, cutoff: octoberDays.at(-1).dateTo }, samplePeriods[9])
assert.deepEqual(fullOctoberMean.values, Array(31).fill(3240), 'All 31 daily positions show the full 32.40-euro monthly average')
assert.ok(fullOctoberMean.values.every(value => value === annualMonthlyMean.fullAverage), 'The reference is neither divided by 31 days nor altered by DST')
const octoberRowHtml = await renderToString(basicGlobals(Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: categoryDetail, report: annualCategory, selection: annualCategorySelection, scopeLabel: 'Home', embedded: true, comparisonData: octoberReferences })))
assert.ok(octoberRowHtml.includes('32.40') && octoberRowHtml.includes('averagePerMonth') && !octoberRowHtml.includes('averagePerDay'), 'The table shares the annual monthly amount and unit with the diagram')
assert.deepEqual(detailComponent.data().selectedComparisons, ['average-selection'], 'The mean is enabled when a detail is first opened')
let chartReloads = 0
const resetMeanState = { selectedComparisons: [], hiddenSeries: ['expense'], loadChart() { chartReloads++ } }
detailComponent.methods.resetChart.call(resetMeanState)
assert.deepEqual(resetMeanState.selectedComparisons, ['average-selection'])
assert.equal(chartReloads, 1)
const initialReferences = comparisonHelpers.reportComparisonSeries(octoberReferences, detailComponent.data().selectedComparisons, 31, false, refTexts, bindings.formatCalendarLabel)
assert.equal(initialReferences.length, 1, 'The annual mean is plotted by default')
assert.ok(initialReferences[0].values.some(value => value !== null))
assert.deepEqual(comparisonHelpers.reportComparisonSeries(octoberReferences, [], 31, false, refTexts, bindings.formatCalendarLabel), [], 'The default mean can still be deselected')
assert.equal(bindings.ReportComparisonPicker.computed.selectedCount.call({ options: [reference], modelValue: ['average-selection'] }), 0, 'Unavailable averages do not inflate the selected count')

const savedAnnualMeanGet = bindings.axios.get
const yearReuseRequests = []
bindings.axios.get = async (_url, { params }) => { yearReuseRequests.push(params); assert.equal(params.comparison, 'detail'); return { data: octoberCategory } }
const reuseYearState = { ...detailComponent.data(), detail: categoryDetail, report: annualCategory, selection: annualCategorySelection, wholePeriod: false }
await detailComponent.methods.loadChart.call(reuseYearState)
assert.equal(yearReuseRequests.length, 1, 'Opening October from the year overview reuses its already scoped annual data')
assert.equal(reuseYearState.averageReport, annualCategory)
assert.equal(reuseYearState.averageBasis.unit, 'month')

// A monthly preset needs an annual reference too, with the same financial perspective and filters.
const monthlyPresetDetail = { ...categoryDetail, row: { ...octoberCategoryRow, id: 1, type: 'expense' }, index: octoberDays.length }
const presetScope = { ...octoberSelection, projectId: 12, entryScope: 'shared', partners: [4], labels: ['important'], hashtags: [9], amountMin: 100, includeFuturePayments: false }
const presetRequests = []
bindings.axios.get = async (_url, { params }) => { presetRequests.push(params); return { data: params.comparison === 'detail' ? octoberCategory : annualCategory } }
const presetYearState = { ...detailComponent.data(), detail: monthlyPresetDetail, report: octoberCategory, selection: presetScope, wholePeriod: true }
await detailComponent.methods.loadChart.call(presetYearState)
assert.equal(presetRequests.length, 2)
const yearlyParams = presetRequests.find(params => !params.comparison)
assert.equal(yearlyParams.period, 'year')
assert.equal(yearlyParams.dateFrom, '2026-01-01')
assert.equal(yearlyParams.dateTo, '2026-12-31')
for (const [key, value] of Object.entries({ projectId: 12, entryScope: 'shared', partners: '4', labels: 'important', hashtags: '9', amountMin: '1', includeFuturePayments: false })) assert.equal(yearlyParams[key], value, `Annual mean retains ${key}`)
assert.equal(presetYearState.averageReport, annualCategory)
assert.equal(presetYearState.averageBasis.unit, 'month', 'A month preset compares against the annual monthly mean rather than changing to a daily average')
const futureYearMean = reportForRow(samplePeriods, annualCategoryRow, samplePeriods.at(-1).dateTo)
const withFullYear = detailComponent.computed.comparisons.call({ ...octoberComparisonsState, averageReport: futureYearMean })[0]
assert.equal(withFullYear.fullAverage, 2700, 'Full-period mode uses all twelve months for the annual monthly mean')

// Weekly mean: 430 euros in one of 43 started calendar-week buckets = ten euros per week.
const weeklyBuckets = []
for (let date = new Date(2026, 0, 1); date.getFullYear() === 2026;) {
 const next = new Date(date.getFullYear(), date.getMonth(), date.getDate() + (date.getDay() === 1 ? 7 : (8 - date.getDay()) % 7))
 const stop = Math.min(next.getTime() / 1000 - 1, samplePeriods.at(-1).dateTo)
 weeklyBuckets.push({ dateFrom: date.getTime() / 1000, dateTo: stop })
 date = next
}
const weekIndex = weeklyBuckets.findIndex(period => period.dateFrom === octoberDays[18].dateFrom)
const weeklyRow = { total: 43000, totalCount: 1, months: weeklyBuckets.map((_, index) => index === weekIndex ? 43000 : 0) }
const weekYear = reportForRow(weeklyBuckets, weeklyRow, sampleReport.cutoff)
const weekDays = dayPeriods(new Date(2026, 9, 19), new Date(2026, 9, 25))
const weekDetail = { ...categoryDetail, row: { ...weeklyRow, id: 1, type: 'expense' }, index: weekIndex }
const weekBasis = comparisonHelpers.reportAnnualAverageBasis(weekDetail, weekYear, { period: 'week' })
assert.equal(weekBasis.period, 'week')
const weeklyMean = comparisonHelpers.reportSelectionAverage(weekYear, 'expense', 'week')
assert.equal(weeklyMean.count, 43)
assert.equal(weeklyMean.fullAverage, 1000)
const weekChart = reportForRow(weekDays, { total: 43000, totalCount: 1, months: [0, 43000, 0, 0, 0, 0, 0] }, weekDays.at(-1).dateTo)
const weekCurve = comparisonHelpers.reportAverageForPeriod(weeklyMean, weekChart, weeklyBuckets[weekIndex])
assert.equal(weekCurve.unit, 'week')
assert.deepEqual(weekCurve.values, Array(7).fill(1000), 'Every day shows the full weekly reference even across a 25-hour DST Sunday')
assert.equal(comparisonHelpers.reportComparisonLabel(weekCurve, refTexts), refTexts.annual.averagePerWeek())
assert.equal(comparisonHelpers.reportAnnualAverageBasis({ ...categoryDetail, index: 31 }, octoberCategory, octoberSelection).unit, 'month')
assert.equal(comparisonHelpers.reportAnnualAverageBasis({ ...categoryDetail, index: 12 }, annualCategory, annualCategorySelection), null, 'Whole-year details keep their existing per-unit average')
assert.equal(reportDetails.reportDetailRow(annualCategory, { ...categoryDetail, row: { ...categoryDetail.row, direct: true } }).total, 32400, 'Direct main-category data remains available when a reference year has no children')

const averageReplyResolvers = []
bindings.axios.get = (_url, { params }) => new Promise(resolve => averageReplyResolvers.push({ resolve, params }))
const delayedYearState = { ...detailComponent.data(), detail: monthlyPresetDetail, report: octoberCategory, selection: presetScope, wholePeriod: true }
const delayedYearLoad = detailComponent.methods.loadChart.call(delayedYearState)
assert.equal(averageReplyResolvers.length, 2)
delayedYearState.chartRequest++
for (const reply of averageReplyResolvers) reply.resolve({ data: reply.params.comparison ? octoberCategory : annualCategory })
await delayedYearLoad
assert.equal(delayedYearState.averageReport, null, 'Delayed year responses cannot populate a different detail')
assert.equal(delayedYearState.focusedReport, null)
bindings.axios.get = async (_url, { params }) => { if (!params.comparison) throw Error('Year reference unavailable'); return { data: octoberCategory } }
const failedYearState = { ...detailComponent.data(), detail: monthlyPresetDetail, report: octoberCategory, selection: presetScope, wholePeriod: true }
await detailComponent.methods.loadChart.call(failedYearState)
assert.equal(failedYearState.chartError, true)
assert.equal(failedYearState.averageReport, null, 'A failed reference cannot silently fall back to the month’s own daily average')
assert.equal(failedYearState.chartLoading, false)
bindings.axios.get = savedAnnualMeanGet
console.log('Annual detail means passed: October vs Jan–Oct, weekly means, monthly presets, default curves, scoped filters, source reuse, DST, exact cents, stale responses and retryable failures.')

const emptyOctober = reportForRow(octoberDays, { total: 0, totalCount: 0, months: octoberDays.map(() => 0) }, sampleReport.cutoff)
const emptyMonthReference = detailComponent.computed.comparisons.call({ ...octoberComparisonsState, chartData: emptyOctober, focusedReport: emptyOctober })[0]
assert.equal(emptyMonthReference.fullAverage, 3240, 'An empty opened month still has the category’s annual monthly reference')
assert.equal(emptyMonthReference.available, true)
const zeroAnnualCategory = reportForRow(samplePeriods, { ...sampleRow, months: samplePeriods.map(() => 0), total: 0, totalCount: 1 }, sampleReport.cutoff)
const zeroAnnualMean = detailComponent.computed.comparisons.call({ ...octoberComparisonsState, averageReport: zeroAnnualCategory })[0]
assert.equal(zeroAnnualMean.fullAverage, 0, 'A recorded zero-valued category has a valid annual mean')
const directAnnualMean = detailComponent.computed.comparisons.call({ ...octoberComparisonsState, detail: { ...categoryDetail, row: { ...categoryDetail.row, direct: true } } })[0]
assert.equal(directAnnualMean.fullAverage, 3240, 'A direct category is resolved from its annual values even when the year row has no children')
const quarterPeriod = { dateFrom: samplePeriods[9].dateFrom, dateTo: samplePeriods[11].dateTo }
const quarterMeanTestReport = reportForRow([quarterPeriod], { months: [1], total: 1, totalCount: 1 }, quarterPeriod.dateTo)
assert.equal(comparisonHelpers.reportAnnualAverageBasis({ ...categoryDetail, index: 1 }, quarterMeanTestReport, { period: 'week' }).unit, 'quarter', 'A complete quarter remains a quarterly annual comparison even if its chart is weekly')
const quarterDays = dayPeriods(new Date(2026, 9, 1), new Date(2026, 11, 31))
const quarterCurve = comparisonHelpers.reportAverageForPeriod({ ...annualMonthlyMean, unit: 'quarter', fullAverage: 8100 }, reportForRow(quarterDays, { months: quarterDays.map(() => 0), total: 0, totalCount: 0 }, quarterPeriod.dateTo), quarterPeriod)
assert.ok(quarterCurve.values.every(value => value === 8100), 'Every point shows the full quarterly reference amount')
const partialMonthWindow = { dateFrom: octoberDays[9].dateFrom, dateTo: octoberDays[19].dateTo }
const partialMonthReport = reportForRow(octoberDays.slice(9, 20), { months: Array(11).fill(0), total: 0, totalCount: 0 }, partialMonthWindow.dateTo)
const partialMonthMean = comparisonHelpers.reportAverageForPeriod(annualMonthlyMean, partialMonthReport, partialMonthWindow)
assert.deepEqual(partialMonthMean.values, Array(11).fill(3240), 'A clipped month also retains the full reference at every included date')
console.log('Annual mean edge cases passed: empty month, genuine zero, direct categories, quarters and clipped calendar columns.')

// Regression: the 32.40-euro monthly mean is a full-amount benchmark at every daily position.
for (const mode of ['period', 'cumulative']) {
 const benchmark = comparisonHelpers.reportComparisonSeries(octoberReferences, ['average-selection'], 31, mode === 'cumulative', refTexts, bindings.formatCalendarLabel)[0]
 assert.equal(benchmark.values[9], 3240, 'The daily tooltip receives the exact monthly amount shown in the table')
 assert.ok(benchmark.values.every(value => value === null || value === 3240), 'Switching modes cannot divide or accumulate the benchmark')
 const benchmarkGeometry = annualChart.annualChartGeometry([benchmark], 31, 800)
 assert.equal(new Set(benchmarkGeometry.series[0].points.filter(point => point.y !== null).map(point => point.y)).size, 1, 'The SVG reference is a horizontal line')
 const inspectedBenchmarkChart = { ...bindings.AnnualOverviewChart, data() { return { ...bindings.AnnualOverviewChart.data.call(this), activeIndex: 9 } } }
 const benchmarkApp = Vue.createSSRApp(inspectedBenchmarkChart, { report: octoberCategory, labels: octoberDays.map((_, index) => String(index + 1)), comparisonSeries: [benchmark], incomeEnabled: false, mode, initialOpen: true, rememberState: false, formatCents: cents => `€ ${(cents / 100).toFixed(2)}` })
 benchmarkApp.config.globalProperties.$texts = refTexts
 const benchmarkHtml = await renderToString(benchmarkApp)
 assert.match(benchmarkHtml, /annual-chart-tooltip-row[^>]*comparison-average-selection[\s\S]*?<b>€ 32\.40<\/b>/, 'The rendered chart tooltip agrees with the 32.40-euro table value')
 assert.ok(!benchmarkHtml.includes('comparisonDistributedAverage'), 'The chart no longer claims that the average is distributed over days')
}
const signedFlatMean = comparisonHelpers.reportComparisonSeries([{ ...annualMonthlyMean, fullAverage: -3240 }], ['average-selection'], 31, true, refTexts, bindings.formatCalendarLabel)[0]
assert.ok(signedFlatMean.values.every(value => value === null || value === -3240), 'Balance benchmarks retain the signed full amount at every position')
const zeroFlatMean = comparisonHelpers.reportComparisonSeries([zeroAnnualMean], ['average-selection'], 31, true, refTexts, bindings.formatCalendarLabel)[0]
assert.ok(zeroFlatMean.values.every(value => value === null || value === 0), 'A valid zero average stays a horizontal zero reference')
console.log('Fixed average benchmark passed: 32.40-euro table/tooltip agreement, horizontal SVG lines, both display modes, signed amounts and genuine zero.')

// Calendar-year totals use the same scoped source as the annual mean, without rounding it back up.
const annualTotalOf = state => detailComponent.computed.annualTotal.call(state)
assert.deepEqual(annualTotalOf(octoberComparisonsState), { year: 2026, amount: 32400 }, 'October shows the full scoped annual total alongside its own 100-euro amount')
assert.deepEqual(annualTotalOf(reuseYearState), { year: 2026, amount: 32400 }, 'A month opened from the annual overview reuses its annual figures')
assert.deepEqual(annualTotalOf(presetYearState), { year: 2026, amount: 32400 }, 'Monthly presets use their already loaded, filtered area reference')
assert.deepEqual(annualTotalOf({ ...octoberComparisonsState, averageReport: null }), { year: 2026, amount: 32400 }, 'A focused monthly chart cannot hide a valid annual parent report')
assert.deepEqual(annualTotalOf({ ...octoberComparisonsState, averageReport: null, focusedReport: null, detail: { ...categoryDetail, index: 12 } }), { year: 2026, amount: 32400 }, 'Whole-year details show their calendar-year total without an annual-average request')
assert.equal(annualTotalOf({ ...presetYearState, averageReport: null }), null, 'An opened month is never mistaken for the annual total while its reference is missing')
assert.equal(annualTotalOf(delayedYearState), null, 'A stale response cannot add a total to another detail')
assert.equal(annualTotalOf(failedYearState), null, 'A failed annual request never presents a monthly amount as a year total')
assert.deepEqual(annualTotalOf({ ...octoberComparisonsState, averageReport: weekYear, detail: weekDetail }), { year: 2026, amount: 43000 }, 'Weekly details use the whole calendar year')
const quarterYearPeriodsForTotal = Array.from({ length: 4 }, (_, index) => ({ dateFrom: samplePeriods[index * 3].dateFrom, dateTo: samplePeriods[index * 3 + 2].dateTo }))
const quarterYearForTotal = reportForRow(quarterYearPeriodsForTotal, { total: 32400, totalCount: 2, months: [0, 0, 22400, 10000] }, sampleReport.cutoff)
assert.deepEqual(annualTotalOf({ ...octoberComparisonsState, averageReport: quarterYearForTotal, report: quarterMeanTestReport, focusedReport: quarterMeanTestReport }), { year: 2026, amount: 32400 }, 'Quarter details show the annual sum independently of their finer chart')
const centYearForTotal = reportForRow(samplePeriods, { ...annualCategoryRow, total: 101, months: [101, ...Array(11).fill(0)] }, sampleReport.cutoff)
assert.equal(reportDetails.reportAnnualTotal(centYearForTotal, categoryDetail).amount, 101, 'The total retains all 101 cents rather than multiplying a rounded ten-cent average')
assert.deepEqual(reportDetails.reportAnnualTotal(zeroAnnualCategory, categoryDetail), { year: 2026, amount: 0 }, 'A genuine zero annual total is available')
const otherCategoryYear = { ...annualCategory, totals: { ...annualCategory.totals, expense: { ...annualCategoryRow, total: 99999 } } }
assert.equal(reportDetails.reportAnnualTotal(otherCategoryYear, categoryDetail).amount, 32400, 'Category details never use the total of other categories')
for (const [type, amount] of [['income', 0], ['expense', 32400], ['all', -32400]]) {
 assert.deepEqual(reportDetails.reportAnnualTotal(annualCategory, { ...categoryDetail, total: true, row: { type } }), { year: 2026, amount }, `Annual ${type} totals preserve their scope and sign`)
}
const childYearForTotal = { ...annualCategory, groups: { ...annualCategory.groups, expense: [{ ...annualCategory.groups.expense[0], children: [{ id: 1, direct: true, total: 22400 }, { id: 2, total: 10000 }] }] } }
assert.equal(reportDetails.reportAnnualTotal(childYearForTotal, { ...categoryDetail, row: { ...categoryDetail.row, direct: true } }).amount, 22400, 'Direct category details exclude annual child payments')
assert.equal(reportDetails.reportAnnualTotal(childYearForTotal, { ...categoryDetail, row: { ...categoryDetail.row, id: 2 } }).amount, 10000, 'A child category retains its own annual total')
const pastYearPeriodsForTotal = [{ dateFrom: new Date(2025, 0, 1).getTime() / 1000, dateTo: new Date(2026, 0, 1).getTime() / 1000 - 1 }]
assert.deepEqual(reportDetails.reportAnnualTotal(reportForRow(pastYearPeriodsForTotal, { total: 12345, months: [12345] }, pastYearPeriodsForTotal[0].dateTo), categoryDetail), { year: 2025, amount: 12345 }, 'The label follows the evaluated year rather than the current year')
assert.equal(reportDetails.reportAnnualTotal(octoberCategory, categoryDetail), null)
assert.equal(reportDetails.reportAnnualTotal(holidayReport, categoryDetail), null, 'A free ten-day period is not labelled as a year total')
const futureYearTotalRow = { ...annualCategoryRow, total: 37401, months: annualCategoryRow.months.map((amount, index) => amount + (index === 11 ? 5001 : 0)) }
const futureYearTotalReport = reportForRow(samplePeriods, futureYearTotalRow, samplePeriods.at(-1).dateTo)
assert.equal(annualTotalOf({ ...octoberComparisonsState, averageReport: futureYearTotalReport }).amount, 37401, 'Full-year mode uses the annual source including its future payments')

const savedYearTotalGet = bindings.axios.get
const futureMonthTotalRequests = []
bindings.axios.get = async (_url, { params }) => { futureMonthTotalRequests.push(params); return { data: annualCategory } }
const novemberDaysForTotal = dayPeriods(new Date(2026, 10, 1), new Date(2026, 10, 30))
const novemberReportForTotal = reportForRow(novemberDaysForTotal, { total: 0, totalCount: 0, months: Array(30).fill(0) }, sampleReport.cutoff)
const futureMonthTotalState = { ...detailComponent.data(), detail: { ...monthlyPresetDetail, index: 30 }, report: novemberReportForTotal, selection: { ...presetScope, dateFrom: '2026-11-01', dateTo: '2026-11-30' }, wholePeriod: true }
await detailComponent.methods.loadChart.call(futureMonthTotalState)
assert.equal(futureMonthTotalState.focusedReport.totals.expense.total, 0, 'An unopened future month remains empty with payments up to today')
assert.deepEqual(annualTotalOf(futureMonthTotalState), { year: 2026, amount: 32400 }, 'An empty future month still shows the current annual total')
assert.equal(futureMonthTotalRequests.length, 1)
assert.equal(futureMonthTotalRequests[0].comparison, undefined, 'Only the scoped year reference is loaded; no future forecast is requested')
assert.equal(futureMonthTotalRequests[0].includeFuturePayments, false)
bindings.axios.get = savedYearTotalGet

const preparedAnnualTotalDetail = { ...detailComponent, watch: {}, components: { ...detailComponent.components, ReportPayments: { render: () => null } }, data: () => ({ ...detailComponent.data(), focusedReport: octoberCategory, focusedSelection: octoberSelection, averageBasis: annualBasis, averageReport: annualCategory }) }
const annualTotalDetailApp = basicGlobals(Vue.createSSRApp(preparedAnnualTotalDetail, { detail: categoryDetail, report: annualCategory, selection: annualCategorySelection, scopeLabel: 'Home', labels: [], labelTitles: [], axisLabels: [] }))
annualTotalDetailApp.config.globalProperties.$enableIncomes = true
const annualTotalDetailHtml = await renderToString(annualTotalDetailApp)
assert.match(annualTotalDetailHtml, /annual-details-total[^>]*>[\s\S]*?<td[^>]*>100\.00<\/td>/, 'The rendered page retains the opened month amount')
assert.match(annualTotalDetailHtml, /annual-details-year-total[^>]*>[\s\S]*?2026[\s\S]*?<td[^>]*>324\.00<\/td>/, 'The actual detail component passes the labelled annual total to its metrics table')
assert.match(annualTotalDetailHtml, /annual-details-average[^>]*>[\s\S]*?<td[^>]*>32\.40<\/td>/, 'The annual monthly average remains unchanged beside the sum')
assert.ok(annualTotalDetailHtml.indexOf('annual-details-year-total') < annualTotalDetailHtml.indexOf('annual-details-average'), 'The year total precedes the average')
const zeroYearTotalHtml = await renderToString(basicGlobals(Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: categoryDetail, report: annualCategory, selection: annualCategorySelection, scopeLabel: 'Home', embedded: true, comparisonData: [], annualTotal: { year: 2026, amount: 0 } })))
assert.match(zeroYearTotalHtml, /annual-details-year-total[^>]*>[\s\S]*?<td[^>]*>0\.00<\/td>/, 'A genuine zero annual total is rendered rather than hidden')
const negativeYearTotalHtml = await renderToString(basicGlobals(Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: categoryDetail, report: annualCategory, selection: annualCategorySelection, scopeLabel: 'Home', embedded: true, comparisonData: [], annualTotal: { year: 2026, amount: -32400 } })))
assert.match(negativeYearTotalHtml, /annual-details-year-total[^>]*>[\s\S]*?<td class="negative-balance"[^>]*>-324\.00<\/td>/, 'Negative annual balances retain the established amount styling')
console.log('Annual detail totals passed: years, months, weeks, quarters, filtered references, direct/child scope, exact cents, cutoff/future payments, loading/errors and rendered sum/average agreement.')

// Cross-unit benchmarks retain the selected year and their full amount at every finer chart position.
const expandedAnnualMeans = comparisonHelpers.reportAnnualAverages(annualCategory, 'expense', 'month')
assert.deepEqual(expandedAnnualMeans.map(value => [value.unit, value.count, value.fullAverage]), [['month', 10, 3240], ['quarter', 4, 8100], ['week', 43, 753]], 'All annual references share the exact total and include started empty units')
assert.equal(expandedAnnualMeans[0].id, 'average-selection', 'The existing monthly mean remains the only default selection')
const completeAnnualMeans = comparisonHelpers.reportAnnualAverages(futureYearMean, 'expense', 'month')
assert.deepEqual(completeAnnualMeans.map(value => [value.unit, value.count, value.fullAverage]), [['month', 12, 2700], ['quarter', 4, 8100], ['week', 53, 611]], 'Full-period mode includes every calendar bucket, including clipped year-boundary weeks')
assert.deepEqual(comparisonHelpers.reportAnnualAverages(octoberCategory, 'expense', 'month'), [], 'A month is never treated as an entire annual reference')
const annualDailyMeans = comparisonHelpers.reportAnnualAverages(annualCategory, 'expense', 'day')
assert.equal(annualDailyMeans[0].unit, 'day', 'A day detail retains its default daily reference alongside the larger annual means')
assert.equal(annualDailyMeans[0].count, 295, 'Calendar day counts ignore the spring daylight-saving hour')
assert.ok(comparisonHelpers.reportAnnualAverages(zeroAnnualCategory, 'expense', 'week').every(value => value.fullAverage === 0), 'Genuine annual zeroes remain available in all units')
assert.deepEqual(comparisonHelpers.reportAnnualAverages({ ...annualCategory, cutoff: samplePeriods[0].dateFrom - 1 }, 'expense', 'week'), [], 'A future year has no started annual mean')
const weeklyCrossReferences = detailComponent.computed.comparisons.call({ chartData: { ...weekChart, cutoff: sampleReport.cutoff }, detail: weekDetail, selection: { ...annualCategorySelection, period: 'week' }, focusedSelection: { ...octoberSelection, dateFrom: '2026-10-19', dateTo: '2026-10-25' }, focusedReport: weekChart, averageBasis: weekBasis, averageReport: weekYear, report: weekYear })
const monthlyThreshold = weeklyCrossReferences.find(value => value.unit === 'month')
assert.equal(monthlyThreshold.fullAverage, 4300, 'A week can be measured against the full annual monthly mean')
assert.ok(weekChart.totals.expense.total > monthlyThreshold.fullAverage, 'The selected week can already exceed an average month')
assert.equal(yearReuseRequests[0].detailAverageUnit, 'month', 'A finer daily chart keeps the opened month as its historical averaging unit')

const priorMonthWeeklyMean = { id: 'average-previous-month', kind: 'reference-average', available: true, unit: 'week', fullAverage: 7000, referenceUnit: 'month', referenceFrom: '2026-09-01', referenceTo: '2026-09-30', referenceYear: 2026, values: [7000, 7000, 7000, 7000, null, null, null], periods: weekDays }
const priorQuarterWeeklyMean = { ...priorMonthWeeklyMean, id: 'average-previous-quarter', referenceUnit: 'quarter', referenceFrom: '2026-07-01', referenceNumber: 3 }
const priorWeekDailyMean = { ...priorMonthWeeklyMean, id: 'average-previous-week', unit: 'day', referenceUnit: 'week', referenceNumber: 42, referenceFrom: '2026-10-12', referenceTo: '2026-10-18', fullAverage: 1000 }
assert.equal(comparisonHelpers.reportComparisonLabel(priorMonthWeeklyMean, refTexts, bindings.formatCalendarLabel), `${refTexts.annual.averagePerWeek()} · September 2026`, 'Prior-month averages name the actual source month in the interface language')
assert.equal(comparisonHelpers.reportComparisonLabel(priorQuarterWeeklyMean, refTexts, bindings.formatCalendarLabel), `${refTexts.annual.averagePerWeek()} · ${refTexts.annual.quarter(3)} 2026`)
assert.equal(comparisonHelpers.reportComparisonLabel(priorWeekDailyMean, refTexts, bindings.formatCalendarLabel), `${refTexts.annual.averagePerDay()} · ${refTexts.annual.week(42)} 2026`)
const expandedCrossReferences = [...weeklyCrossReferences, priorMonthWeeklyMean, priorQuarterWeeklyMean, reference, noReference]
const selectedCrossIds = expandedCrossReferences.map(value => value.id)
for (const cumulative of [false, true]) {
 const curves = comparisonHelpers.reportComparisonSeries(expandedCrossReferences, selectedCrossIds, 7, cumulative, refTexts, bindings.formatCalendarLabel)
 for (const average of expandedCrossReferences.filter(value => value.available && value.kind !== 'period')) {
  const curve = curves.find(value => value.id === `comparison-${average.id}`)
  assert.ok(curve.values.every(value => value === null || value === average.fullAverage), 'Each mean stays at its full table amount in both modes')
  assert.ok(new Set(annualChart.annualChartGeometry([curve], 7, 800).series[0].points.filter(point => point.value !== null).map(point => point.y)).size <= 1, 'Each selected average renders as a horizontal reference')
 }
}
const allCrossPickerHtml = await renderToString(basicGlobals(Vue.createSSRApp(simplePicker, { comparisons: expandedCrossReferences, modelValue: ['average-selection', priorMonthWeeklyMean.id] })))
assert.ok(allCrossPickerHtml.includes('yearAverages') && allCrossPickerHtml.includes('previousPeriodAverages'), 'The compact menu groups annual means and earlier-period means')
assert.ok(allCrossPickerHtml.includes('September 2026') && !allCrossPickerHtml.includes('comparisonNoData'), 'Usable source labels are visible without unavailable placeholders')
assert.ok(!/comparisonSelectAll|comparisonClear|comparisonAverageHint/.test(allCrossPickerHtml), 'Grouped choices do not restore bulk actions or explanatory text')
const groupedCrossOptions = bindings.ReportComparisonPicker.computed.optionGroups.call({ options: bindings.ReportComparisonPicker.computed.options.call({ comparisons: expandedCrossReferences }), $texts: refTexts })
assert.deepEqual(groupedCrossOptions.map(group => [group.id, group.options.length]), [['year', 3], ['earlier', 2], ['periods', 1]], 'Every available reference appears exactly once, without empty groups')
const crossTableHtml = await renderToString(basicGlobals(Vue.createSSRApp(bindings.AnnualOverviewDetails, { detail: weekDetail, report: weekYear, selection: { ...annualCategorySelection, period: 'week' }, scopeLabel: 'Home', embedded: true, comparisonData: expandedCrossReferences })))
assert.equal((crossTableHtml.match(/class="annual-details-average"/g) || []).length, 5, 'The table lists all available annual and earlier-period means')
assert.match(crossTableHtml, /data-average-id="average-previous-month"[^>]*>[\s\S]*?September 2026[\s\S]*?<td[^>]*>70\.00<\/td>/, 'The historical weekly table amount agrees with its diagram reference')
console.log('Expanded average comparisons passed: annual cross-unit thresholds, prior month/quarter/week labels, one default, grouped menus, exact full-amount lines, sparse data, cutoffs and both chart modes.')

const reportDetailPage = { ...preparedAnnualTotalDetail, components: {
 ...detailComponent.components,
 ReportDevelopment: developmentComponent,
 ReportPayments: { ...bindings.ReportPayments, watch: {}, data: () => ({ ...bindings.ReportPayments.data(), loading: false }) },
}, data: () => ({ ...preparedAnnualTotalDetail.data(), focusedReport: { ...octoberCategory, detailComparisons: [reference] } }) }
const renderDetailSections = async (detail = categoryDetail) => {
 const app = basicGlobals(Vue.createSSRApp(reportDetailPage, { detail, report: annualCategory, selection: annualCategorySelection, scopeLabel: 'Home', labels: [], labelTitles: [], axisLabels: [] }))
 app.config.globalProperties.$enableIncomes = true
 return renderToString(app)
}
const detailPanel = (html, key) => html.match(new RegExp(`<section[^>]*data-report-section="${key}"[\\s\\S]*?</section>`))?.[0] || ''
const initialDetailSections = await renderDetailSections()
assert.ok(!detailPanel(initialDetailSections, 'detail-metrics').includes('data-average-id'), 'Key figures exclude the averages now grouped in their own section')
assert.ok(detailPanel(initialDetailSections, 'detail-averages').includes('data-average-id="average-selection"'), 'The averages section contains the existing exact reference amounts')
assert.ok(detailPanel(initialDetailSections, 'detail-averages').includes('averagePerPayment'), 'The average payment amount belongs with the other averages')
assert.ok(detailPanel(initialDetailSections, 'detail-comparisons').includes('annual-details-comparison'), 'Period references form their own section')
assert.doesNotMatch(initialDetailSections, /is-collapsed|collapseSection|expandSection/)

// Composition uses the opened subject and all matching payments, separately from list pagination.
const compositionData = {
 totals: { expense: { amountCents: 303, count: 3 }, income: { amountCents: 900, count: 1 } },
 groups: {
  categories: { expense: [{ id: 10, name: 'Home', amountCents: 101, count: 1 }, { id: 11, name: 'Rent', parentName: 'Home', amountCents: 202, count: 2 }], income: [{ id: 12, name: 'Salary', amountCents: 900, count: 1 }] },
  paymentPartners: { expense: [{ id: 20, name: 'Shop & Co', amountCents: 101, count: 1 }, { id: 0, name: '', amountCents: 202, count: 2 }], income: [{ id: 21, name: 'Employer', amountCents: 900, count: 1 }] },
  labels: { expense: [{ id: 'important', name: 'Important', amountCents: 101, count: 1 }, { id: '', name: '', amountCents: 202, count: 2 }], income: [] },
  hashtags: { expense: [{ id: 5, name: '#Trip', amountCents: 101, count: 1 }, { id: 6, name: 'Other', amountCents: 101, count: 1 }, { id: 0, name: '', amountCents: 202, count: 2 }], income: [] },
  projects: { expense: [{ id: 7, name: 'Area', amountCents: 101, count: 1 }, { id: 0, name: '', amountCents: 202, count: 2 }], income: [] },
 },
 overlaps: { labels: { expense: false }, hashtags: { expense: true } },
}
let compositionVm
const compositionProps = { detail: categoryDetail, report: annualCategory, selection: annual.annualSelection({ ...annualCategorySelection, projectId: null }) }
const renderComposition = async ({ detail = compositionProps.detail, selection = compositionProps.selection, dimension = '', incomes = true, projects = true, error = false, result = compositionData } = {}) => {
 const app = basicGlobals(Vue.createSSRApp({ ...bindings.ReportBreakdown, data: () => ({ ...bindings.ReportBreakdown.data(), loading: false, result, dimension, error }), created() { compositionVm = this } }, { ...compositionProps, detail, selection }))
 app.config.globalProperties.$enableIncomes = incomes
 app.config.globalProperties.$enableProjects = projects
 return renderToString(app)
}
const compositionHtml = await renderComposition()
assert.deepEqual(compositionVm.dimensions.map(value => value.key), ['paymentPartners', 'projects', 'labels', 'hashtags'], 'Tabs follow partner, category, area, label and tag order while omitting the subject dimension')
assert.equal(compositionVm.activeDimension.key, 'paymentPartners')

const compositionTabs = [...compositionHtml.matchAll(/<button[^>]*role="tab"[^>]*>([\s\S]*?)<\/button>/g)].map(match => match[1].replace(/<[^>]*>/g, '').trim())
assert.deepEqual(compositionTabs, [refTexts.analytics.paymentPartners(), refTexts.analytics.areas(), refTexts.analytics.labels(), refTexts.annual.tags()], 'Available dimensions are visible heading tabs')
assert.ok(!compositionHtml.includes('class="report-select '), 'A category composition needs no hidden dimension dropdown')
assert.equal((compositionHtml.match(/aria-selected="true"/g) || []).length, 1, 'Exactly one dimension tab is selected')
assert.ok(compositionHtml.includes('role="tabpanel"'), 'The selected table has a matching accessible tab panel')
compositionVm.chooseDimension('hashtags')
assert.equal(compositionVm.activeDimension.key, 'hashtags')
assert.equal(compositionVm.exportData()[0].columns[0], refTexts.analytics.hashtags(), 'Section exports follow the active dimension tab')
const focusedDimensionTabs = []
const tabNodes = compositionVm.dimensions.map((item, index) => ({ focus: () => focusedDimensionTabs.push(index) }))
compositionVm.onTabKey({ key: 'Home', preventDefault() {}, currentTarget: { querySelectorAll: () => tabNodes } })
await Vue.nextTick()
assert.equal(compositionVm.activeDimension.key, 'paymentPartners')
assert.deepEqual(focusedDimensionTabs, [0], 'Home selects and focuses the first available tab')
compositionVm.onTabKey({ key: 'ArrowLeft', preventDefault() {}, currentTarget: { querySelectorAll: () => tabNodes } })
await Vue.nextTick()
assert.equal(compositionVm.activeDimension.key, 'hashtags', 'Left arrow wraps to the last available tab')
compositionVm.chooseDimension('paymentPartners')


const compositionHeaders = [...compositionHtml.matchAll(/<th scope="col"[^>]*>([\s\S]*?)<\/th>/g)].map(match => match[1].replace(/<[^>]*>/g, '').replace(/[↑↓]/g, '').trim())
assert.deepEqual(compositionHeaders, [refTexts.entry.tablePaymentPartner(), refTexts.analytics.bookingsColumn(), refTexts.analytics.share(), refTexts.entry.tableAmount('EUR')], 'Composition follows From/to, Payments, Share and Amount (currency) order')
assert.equal(compositionVm.signedCents(101), -101, 'Expense magnitudes are shown and exported with the cash-flow sign')
assert.equal(compositionVm.formatAmount(101), new Intl.NumberFormat('de-AT', { style: 'currency', currency: 'EUR', signDisplay: 'always' }).format(-1.01))
assert.match(compositionHtml, /<tfoot>[\s\S]*?<td class="numeric amount is-negative">[^<]*3,03<\/td>/, 'Negative expense totals receive their signed currency amount and red styling')
const compositionExport = compositionVm.exportData()[0]
assert.deepEqual(compositionExport.columns, compositionHeaders)
assert.equal(compositionExport.rows[0][1], 2)
assert.deepEqual(compositionExport.rows[0][3], exportHelpers.reportExportMoney(-202), 'The last export column retains exact signed cents')

assert.deepEqual(compositionVm.rows.map(value => value.amountCents), [202, 101], 'The largest amounts appear first')
assert.ok(compositionHtml.includes('Shop &amp; Co') && compositionHtml.includes('noPartner'), 'Names are safely escaped and unassigned payments remain visible')
assert.equal(compositionVm.share(101), new Intl.NumberFormat('de-AT', { style: 'percent', maximumFractionDigits: 1 }).format(1 / 3), 'Shares use the selected subject total')
compositionVm.sort('name')
assert.equal(compositionVm.sortDir, 'asc')
compositionVm.sort('name')
assert.equal(compositionVm.sortDir, 'desc', 'Name sorting toggles in both directions')
const tagCompositionHtml = await renderComposition({ dimension: 'hashtags' })
assert.ok(tagCompositionHtml.includes('#Trip') && !tagCompositionHtml.includes('##Trip'), 'An existing tag prefix is not duplicated')
assert.ok(tagCompositionHtml.includes('breakdownOverlap') && tagCompositionHtml.includes('withoutHashtag'), 'Overlapping tags show the unique footer and an explanation alongside unassigned payments')
assert.equal(compositionVm.total.amountCents, 303, 'The footer uses the unique total, not the overlapping row sum')
const partnerCompositionDetail = { ...categoryDetail, row: { id: 20, type: 'expense' } }
await renderComposition({ detail: partnerCompositionDetail, selection: annual.annualSelection({ ...compositionProps.selection, grouping: 'paymentPartners' }) })
assert.equal(compositionVm.activeDimension.key, 'categories', 'A partner opens with categories')
assert.ok(compositionVm.rows.some(row => row.label === 'Home · Rent'), 'Subcategories show their parent context without duplicate group totals')
await renderComposition({ selection: { ...compositionProps.selection, projectId: 7 } })
assert.ok(!compositionVm.dimensions.some(item => item.key === 'projects'), 'An already selected area does not offer its own trivial breakdown')
await renderComposition({ projects: false })
assert.ok(!compositionVm.dimensions.some(item => item.key === 'projects'), 'Disabled area features stay hidden')
await renderComposition({ result: { ...compositionData, groups: { ...compositionData.groups, labels: { expense: [{ id: '', name: '', amountCents: 303, count: 3 }] }, hashtags: { expense: [] } } } })
assert.ok(!compositionVm.dimensions.some(item => ['labels', 'hashtags'].includes(item.key)), 'Unused label and tag dimensions stay hidden')
const balanceCompositionDetail = { ...categoryDetail, total: true, row: { type: 'all' } }

const allCompositionHtml = await renderComposition({ detail: balanceCompositionDetail })
assert.deepEqual(compositionVm.dimensions.map(item => item.key), ['paymentPartners', 'categories', 'projects', 'labels', 'hashtags'])
assert.equal(compositionVm.activeDimension.key, 'categories', 'Totals retain their previous contextual default rather than always choosing the first visible tab')
assert.equal((allCompositionHtml.match(/class="report-select /g) || []).length, 1, 'Balance details retain only the income/expense control')
assert.ok(allCompositionHtml.indexOf('report-breakdown-type') < allCompositionHtml.indexOf('report-detail-section-body'), 'Income/expense sits in the header')

await renderComposition({ detail: balanceCompositionDetail })
compositionVm.selectedType = 'income'
assert.equal(compositionVm.type, 'income')
assert.equal(compositionVm.total.amountCents, 900, 'A balance detail switches to the full income denominator instead of its net balance')

assert.equal(compositionVm.signedCents(900), 900, 'Income retains its positive sign')
assert.ok(compositionVm.formatAmount(900).startsWith('+'), 'Income formatting agrees with saved payments')
const incomeOnlyComposition = { ...compositionData, groups: Object.fromEntries(Object.entries(compositionData.groups).map(([key, values]) => [key, { ...values, expense: [] }])) }
const incomeOnlyCompositionHtml = await renderComposition({ detail: balanceCompositionDetail, result: incomeOnlyComposition })
assert.ok(incomeOnlyCompositionHtml.includes('report-breakdown-table'))
assert.equal(compositionVm.type, 'income', 'A balance detail with only income still shows its useful composition')
assert.deepEqual(compositionVm.availableTypes, ['income'])
assert.ok(!/is-negative/.test(incomeOnlyCompositionHtml), 'A positive income total is not styled as a negative balance')
const leafCompositionHtml = await renderComposition({ detail: { ...balanceCompositionDetail, scopeDimensions: ['categories', 'paymentPartners', 'labels', 'hashtags', 'projects'] } })
assert.ok(!leafCompositionHtml.includes('report-breakdown'), 'After the final drill-down dimension there is no empty composition section')

await renderComposition({ detail: balanceCompositionDetail, incomes: false })
assert.equal(compositionVm.type, 'expense', 'Disabling income retains the expense view')
const failedCompositionHtml = await renderComposition({ error: true })
assert.ok(failedCompositionHtml.includes('role="alert"') && failedCompositionHtml.includes('breakdownError') && failedCompositionHtml.includes('retry'), 'Request failures offer retry without rendering stale figures')
assert.ok(!failedCompositionHtml.includes('report-breakdown-table'))
const emptyCompositionHtml = await renderComposition({ result: { groups: {}, totals: {} } })
assert.ok(!/noFilteredEntries|report-breakdown|detailBreakdown/.test(emptyCompositionHtml), 'An empty composition omits the entire section, its heading and empty-state message')
assert.deepEqual(compositionVm.exportData(), [], 'Hidden compositions add no placeholder section to an export')
const compositionParams = reportBreakdownComponent.computed.params.call({ detail: { ...categoryDetail, row: { ...categoryDetail.row, direct: true } }, report: annualCategory, selection: annual.annualSelection({ ...compositionProps.selection, projectId: 7, entryScope: 'shared', partners: [20], labels: ['important'], hashtags: [5], amountMin: 101, includeFuturePayments: true }) })
assert.equal(compositionParams.dateFrom, annual.reportDate(new Date(annualCategory.periods[9].dateFrom * 1000)))
assert.equal(compositionParams.dateTo, annual.reportDate(new Date(annualCategory.periods[9].dateTo * 1000)))
assert.equal(compositionParams.detailDirect, true)
assert.equal(compositionParams.entryScope, 'shared')
assert.equal(compositionParams.partners, '20')
assert.equal(compositionParams.labels, 'important')
assert.equal(compositionParams.hashtags, '5')
assert.equal(compositionParams.amountMin, '1.01', 'Amount filters retain exact cents at the API boundary')
const previousCompositionGet = bindings.axios.get
try {
 const pendingCompositions = []
 bindings.axios.get = (_url, options) => new Promise(resolve => pendingCompositions.push({ resolve, params: options.params }))
 const instance = { ...reportBreakdownComponent.data(), params: compositionParams }
 const firstLoad = reportBreakdownComponent.methods.load.call(instance)
 instance.params = { ...compositionParams, detailId: 'another' }
 const secondLoad = reportBreakdownComponent.methods.load.call(instance)
 pendingCompositions[1].resolve({ data: compositionData }); await secondLoad
 pendingCompositions[0].resolve({ data: { stale: true } }); await firstLoad
 assert.equal(instance.result, compositionData, 'An older detail request cannot overwrite the current composition')
 bindings.axios.get = async () => { throw Error('offline') }
 await reportBreakdownComponent.methods.load.call(instance)
 assert.equal(instance.error, true)
 bindings.axios.get = async () => ({ data: compositionData })
 await reportBreakdownComponent.methods.load.call(instance)
 assert.equal(instance.error, false, 'Retry restores a failed composition')
} finally { bindings.axios.get = previousCompositionGet }
console.log('Report composition passed: contextual groupings, exact request scope, shares, sorting, balance views, safe names, unassigned values, overlapping tags, feature flags, errors, retry and stale replies.')


// Area comparisons are an independent view of the same report dates and filters.
const areaAmount = (value, count = 1) => ({ months: [value, ...Array(11).fill(0)], counts: [count, ...Array(11).fill(0)], total: value, totalCount: count })
const areaData = {
 rows: [
  { id: 10, name: 'Area 10', entryScope: 'shared', income: areaAmount(300), expense: areaAmount(200), balance: areaAmount(100, 2), filterHashtags: [55] },
  { id: 2, name: 'Area 2', entryScope: 'personal', income: areaAmount(0, 0), expense: areaAmount(400), balance: areaAmount(-400) },
  { id: 0, name: '', entryScope: 'personal', income: areaAmount(0, 0), expense: areaAmount(100), balance: areaAmount(-100) },
 ],
 totals: { income: areaAmount(300), expense: areaAmount(700, 3), balance: areaAmount(-400, 4) },
}
let areaVm
const makeAreaComponent = (changes = {}) => ({ ...areaOverviewComponent, watch: {}, data: () => ({ ...areaOverviewComponent.data(), result: areaData, loading: false, ...changes }), created() { areaVm = this } })
const areaProps = { selection: annual.annualSelection({ year: currentYear, timeRange: 'year' }), report, columns: Array.from({ length: 12 }, (_, index) => String(index + 1)).concat('Total'), columnTitles: [] }
const areaGlobals = app => {
 basicGlobals(app)
 app.config.globalProperties.$enableIncomes = true
 app.config.globalProperties.$enableProjects = true
 app.component('RouterLink', { props: ['to'], setup: (props, { slots }) => () => Vue.h('a', { href: JSON.stringify(props.to) }, slots.default?.()) })
 return app
}
const areaHtml = await renderToString(areaGlobals(Vue.createSSRApp(makeAreaComponent(), areaProps)))
assert.ok(areaHtml.includes('personalShare') && areaHtml.includes('expenses'), 'The area table starts with personal expenses')
assert.ok(areaHtml.indexOf('withoutArea') < areaHtml.indexOf('Area 2') && areaHtml.indexOf('Area 2') < areaHtml.indexOf('Area 10'), 'Private payments stay first and area names use natural A–Z ordering')
assert.equal(areaVm.params.amountView, 'personal')
assert.equal(areaVm.params.dateFrom, annual.reportDate(new Date(report.periods.at(-1).dateFrom * 1000)))
assert.equal(areaVm.params.dateTo, annual.reportDate(new Date(report.periods.at(-1).dateTo * 1000)))
const areaFooter = areaHtml.match(/<tfoot[^>]*>([\s\S]*?)<\/tfoot>/)[1]
assert.ok(areaFooter.includes('7.00') && areaFooter.includes('personalShare') && !/href=|<button/.test(areaFooter), 'The labelled footer is a sum of this view, without a misleading personal drilldown')
const fullAreaHtml = await renderToString(areaGlobals(Vue.createSSRApp(makeAreaComponent(), { ...areaProps, amountView: 'area', metric: 'balance' })))
assert.ok(fullAreaHtml.includes('areaTotals') && fullAreaHtml.includes('negative-balance'), 'Full-area balances are explicit and retain negative values')
const areaCellRoute = areaVm.detailLocation(areaVm.rows.find(row => row.id === 10), 3)
assert.equal(areaCellRoute.query.projectId, undefined, 'Opening an area leaves the overview’s financial scope in My finances')
assert.equal(areaCellRoute.query.detailArea, '10')
assert.equal(areaCellRoute.query.detailAreaScope, 'shared')
assert.equal(areaCellRoute.query.detailPeriod, '3')
assert.equal(areaCellRoute.query.detailType, 'all')
assert.equal(areaCellRoute.query.detailAreaTags, '55', 'Equivalent full-area tags follow the cell into details')
assert.deepEqual(reportDetails.areaOverviewOptions(areaCellRoute.query), { areaAmountView: 'area', areaMetric: 'balance' })
assert.equal(areaVm.detailLocation(areaVm.rows.find(row => row.id === 0), 12).query.detailAreaScope, 'personal', 'Without area always opens personal payments')
const areaExport = areaVm.exportSection()
assert.ok(areaExport.title.includes('areaTotals') && areaExport.title.includes('balance'))
assert.deepEqual(areaExport.rows.map(row => row.amounts.at(-1)), [-100, -400, 100, -400], 'CSV follows the visible metric, natural area order and exact signed sum')
const onlyExpenseApp = areaGlobals(Vue.createSSRApp(makeAreaComponent(), { ...areaProps, metric: 'income' }))
onlyExpenseApp.config.globalProperties.$enableIncomes = false
const onlyExpenseAreas = await renderToString(onlyExpenseApp)
assert.equal(areaVm.effectiveMetric, 'expense')
assert.ok(!onlyExpenseAreas.includes('>balance</button>') && !onlyExpenseAreas.includes('>income</button>'), 'Disabled income management removes both unavailable metrics')
const areaErrorHtml = await renderToString(areaGlobals(Vue.createSSRApp(makeAreaComponent({ error: true }), areaProps)))
assert.ok(areaErrorHtml.includes('role="alert"') && areaErrorHtml.includes('areaOverviewError') && areaErrorHtml.includes('retry') && !areaErrorHtml.includes('<tfoot'), 'Area errors hide stale amounts and offer retry')
const areaPage = createReportApp(selection)
areaPage._component.components = { ...view.components, ReportAreaOverview: makeAreaComponent() }
const areaPageHtml = await renderToString(areaPage)
assert.equal((areaPageHtml.match(/<table /g) || []).length, 4, 'My finances adds the area comparison below the three existing tables')
const scopedAreaPage = createReportApp({ ...selection, projectId: 10 })
scopedAreaPage._component.components = { ...view.components, ReportAreaOverview: makeAreaComponent() }
assert.ok(!(await renderToString(scopedAreaPage)).includes('report-area-table'), 'Selecting a single area hides the cross-area comparison')
const disabledAreaPage = createReportApp(selection, report, { projects: false })
disabledAreaPage._component.components = { ...view.components, ReportAreaOverview: makeAreaComponent() }
assert.ok(!(await renderToString(disabledAreaPage)).includes('report-area-table'), 'Disabled areas do not render comparison controls or data')
let areaDetailVm
const areaDetailFixture = { ...areaDetailComponent, watch: {}, components: { ...areaDetailComponent.components, ReportDetailView: { render: () => Vue.h('div', { class: 'area-detail-fixture' }) } }, data: () => ({ ...areaDetailComponent.data(), report, loading: false }), created() { areaDetailVm = this } }
await renderToString(areaGlobals(Vue.createSSRApp(areaDetailFixture, { ...areaProps, query: areaCellRoute.query, projects: [{ id: 10, name: 'Area 10' }], labels: [], labelTitles: [], axisLabels: [] })))
assert.equal(areaDetailVm.areaSelection.projectId, 10)
assert.equal(areaDetailVm.areaSelection.entryScope, 'shared')
assert.deepEqual(areaDetailVm.areaSelection.hashtags, [55])
assert.equal(areaDetailVm.detail.index, 3)
assert.equal(areaDetailVm.detail.row.type, 'all')
assert.equal(areaDetailVm.detail.route.name, 'project-payments')
const withoutAreaRoute = { ...areaCellRoute.query, detailArea: '0', detailAreaScope: 'personal', detailType: 'expense' }
await renderToString(areaGlobals(Vue.createSSRApp(areaDetailFixture, { ...areaProps, query: withoutAreaRoute, labels: [], labelTitles: [], axisLabels: [] })))
assert.equal(areaDetailVm.areaSelection.withoutArea, true)
assert.equal(annual.annualRequestParams(areaDetailVm.areaSelection).withoutArea, 'true')
assert.equal(reportDetails.reportPaymentParams(areaDetailVm.detail.route, areaDetailVm.areaSelection, report.cutoff).withoutArea, true, 'Without-area details narrow charts, composition and saved payments alike')
const allAreaSelection = areaDetailComponent.computed.areaSelection.call({ selection: allSelection, areaId: 10, scopeIdentity: JSON.stringify({ scope: 'shared', tags: '' }) })
assert.equal(allAreaSelection.timeRange, 'custom', 'Area drilldowns retain the overview bounds even when their own saved years differ')
assert.equal(allAreaSelection.dateFrom, '2023-01-01')
assert.equal(allAreaSelection.dateTo, '2026-12-31')
assert.equal(annual.annualRequestParams(allAreaSelection).allTime, undefined)
assert.equal(areaDetailComponent.computed.areaId.call({ query: { detailArea: '-1' } }), null)
const routeChanges = []
const areaControlsState = { selection: areaProps.selection, $router: { replace: route => { routeChanges.push(route); return Promise.resolve() } } }
await view.methods.updateAreaControls.call(areaControlsState, 'area', 'income')
assert.equal(areaControlsState.selection.projectId, null)
assert.equal(routeChanges[0].query.areasView, 'area')
assert.equal(routeChanges[0].query.areasMetric, 'income')
let areaNavigationLoads = 0
const areaNavigation = { selection: areaProps.selection, report, $el: { closest: () => null }, $refs: {}, $nextTick: callback => callback(), load: () => { areaNavigationLoads++ }, rememberOverviewPosition() {}, restoreOverviewPosition() {} }
view.watch['$route.query'].handler.call(areaNavigation, areaCellRoute.query, annual.annualRouteQuery(areaProps.selection))
assert.equal(areaNavigationLoads, 0, 'Entering area details preserves the original report and its table state')
assert.equal(areaNavigation.areaAmountView, 'area')
view.watch['$route.query'].handler.call(areaNavigation, { ...annual.annualRouteQuery(areaProps.selection), areasView: 'area', areasMetric: 'balance' }, areaCellRoute.query)
assert.equal(areaNavigationLoads, 0, 'Returning from area details reuses the overview and its scroll context')
const getBeforeAreas = bindings.axios.get
try {
 const pending = []
 bindings.axios.get = () => new Promise(resolve => pending.push(resolve))
 const state = { ...areaOverviewComponent.data(), params: { amountView: 'personal' } }
 const first = areaOverviewComponent.methods.load.call(state)
 state.params = { amountView: 'area' }
 const second = areaOverviewComponent.methods.load.call(state)
 pending[1]({ data: areaData }); await second
 pending[0]({ data: { stale: true } }); await first
 assert.equal(state.result, areaData, 'An obsolete personal response cannot overwrite full-area amounts')
 bindings.axios.get = async () => { throw Error('offline') }
 await areaOverviewComponent.methods.load.call(state)
 assert.equal(state.error, true)
 bindings.axios.get = async () => ({ data: areaData })
 await areaOverviewComponent.methods.load.call(state)
 assert.equal(state.error, false)
} finally { bindings.axios.get = getBeforeAreas }
console.log('Area comparison passed: scoped dates, local views, natural ordering, signed balances, unique footers, CSV, feature flags, detail/return navigation, unassigned payments, tag identities, errors and stale replies.')

// Forecasts remain independent of the normal report's future-payment cutoff.
const forecastDay = new Date()
forecastDay.setHours(0, 0, 0, 0)
const forecastStart = new Date(forecastDay); forecastStart.setDate(forecastStart.getDate() - 9)
const forecastEnd = new Date(forecastDay); forecastEnd.setDate(forecastEnd.getDate() + 10); forecastEnd.setHours(23, 59, 59)
const forecastPeriod = { dateFrom: forecastStart.getTime() / 1000, dateTo: forecastEnd.getTime() / 1000 }
const forecastReport = { periods: [forecastPeriod, forecastPeriod], cutoff: Date.now() / 1000 }
const forecastSelection = annual.annualSelection({ timeRange: 'custom', dateFrom: annual.reportDate(forecastStart), dateTo: annual.reportDate(forecastEnd), grouping: 'categories', period: 'day' })
const forecastData = {
 status: 'current', ...forecastPeriod, asOf: Date.now() / 1000, elapsedDays: 10, remainingDays: 10, variableCount: 2, estimateAvailable: true, expenseEstimateAvailable: true, incomePatternCount: 0,
 actual: { incomeCents: 300000, expenseCents: 180000, balanceCents: 120000 },
 planned: { incomeCents: 0, expenseCents: 40000, balanceCents: -40000 },
 estimated: { incomeCents: 0, expenseCents: 30000, balanceCents: -30000 },
 forecast: { incomeCents: 300000, expenseCents: 250000, balanceCents: 50000 },
}
forecastData.chart = {
 periods: [{ dateFrom: forecastPeriod.dateFrom, dateTo: forecastDay.getTime() / 1000 + 86399 }, { dateFrom: forecastDay.getTime() / 1000 + 86400, dateTo: forecastPeriod.dateTo }],
 actual: { income: [300000, 0], expense: [180000, 0], balance: [120000, 0] },
 planned: { income: [0, 0], expense: [0, 40000], balance: [0, -40000] },
 estimated: { income: [0, 0], expense: [0, 30000], balance: [0, -30000] },
}
const unchangedForecast = JSON.stringify(forecastData)
const forecastPerPeriod = forecastHelpers.reportForecastSeries(forecastData, { cumulative: false })
assert.deepEqual(forecastPerPeriod.map(item => item.values), [[300000, 0], [180000, 70000], [120000, -70000]], 'Individual amounts contain the exact actual, planned and estimated cents of each period')
const forecastRunning = forecastHelpers.reportForecastSeries(forecastData)
assert.deepEqual(forecastRunning.map(item => item.values), [[300000, 300000], [180000, 250000], [120000, 50000]])
assert.deepEqual(forecastHelpers.reportForecastSeries(forecastData, { incomeEnabled: false }).map(item => item.id), ['expense'])
assert.deepEqual(forecastHelpers.reportForecastSeries(forecastData, { seriesIds: ['income'] }).map(item => item.id), ['income'])
assert.equal(JSON.stringify(forecastData), unchangedForecast, 'Chart controls never change financial data')
const forecastGeometry = annualChart.annualChartGeometry(forecastRunning, 2, 320)
assert.ok(forecastGeometry.series.every(item => item.actualLine && item.line && item.actualPoints.length))
assert.ok(forecastGeometry.series.every(item => !/NaN|Infinity/.test(item.line + item.actualLine)))
const contrastedBalance = { id: 'balance', values: [-1000, -2000], actualValues: [5000, null] }
assert.ok(annualChart.annualChartScale([contrastedBalance]).max >= 5000, 'The scale includes current actual balance even if the expected balance is lower')
let forecastVm, developmentVm
const forecastComponent = { ...reportForecastComponent, watch: {} }
const renderForecast = async ({ detail = null, result = forecastData, report = forecastReport, selection = forecastSelection, incomes = true, error = false, loading = false, view = 'forecast', sectionKey = detail ? 'detail-chart' : 'chart' } = {}) => {
 const child = { ...forecastComponent, data: () => ({ ...forecastComponent.data(), result, error, loading }), created() { forecastVm = this } }
 const wrapper = { ...developmentComponent, components: { ...developmentComponent.components, ReportForecast: child }, data() { return { ...developmentComponent.data.call(this), ...(view === null ? {} : { view }) } }, created() { developmentVm = this } }
 const app = basicGlobals(Vue.createSSRApp(wrapper, { report, selection, detail, labels: ['Start', 'End'], labelTitles: ['First period', 'Last period'], chartPeriod: 'day', sectionKey, seriesIds: detail && detail.row.type !== 'all' ? [detail.row.type] : null }))
 app.config.globalProperties.$enableIncomes = incomes
 return renderToString(app)
}
const forecastHtml = await renderForecast()
assert.ok(forecastHtml.includes('data-report-section="chart"') && forecastHtml.includes('role="tablist"'))
assert.ok(forecastHtml.includes('forecastIncome') && forecastHtml.includes('forecastExpenses') && forecastHtml.includes('forecastBalance'), 'Forecast mode has income, expense and balance boxes')
assert.ok(forecastHtml.includes('2500.00') && forecastHtml.includes('500.00'), 'End totals agree between the summary and forecast table')
assert.ok(forecastHtml.includes('forecastKnownFuture') && forecastHtml.includes('forecastAdditional') && forecastHtml.includes('forecastTotal'))
assert.ok(!forecastHtml.includes('forecastFutureNote') && !forecastHtml.includes('forecastMethod'), 'Calculation notes are kept out of the report UI')
assert.ok(!forecastHtml.includes('forecastUntil') && !forecastHtml.includes('forecastInformation'), 'The Forecast tab has no date suffix or info trigger')

assert.ok(forecastHtml.includes('annual-chart-forecast-line') && forecastHtml.includes('annual-chart-actual-line') && forecastHtml.includes('annual-chart-forecast-shade'), 'The chart separates actual development from its projected continuation')
const expectedSeries = developmentVm.forecastSeries(forecastData)
assert.deepEqual(expectedSeries.map(item => item.values.at(-1)), [300000, 250000, 50000], 'Running-total chart endpoints match every period-end box')
assert.equal(forecastVm.params.includeFuturePayments, undefined)
assert.equal(forecastVm.params.period, 'day', 'The forecast chart uses the detail’s actual breakdown')
const withoutIncomesForecast = await renderForecast({ incomes: false })
assert.ok(!withoutIncomesForecast.includes('>forecastBalance<') && !withoutIncomesForecast.includes('>forecastIncome<') && withoutIncomesForecast.includes('forecastExpenses'))
assert.equal(forecastVm.params.detailType, 'expense')
const knownForecastHtml = await renderForecast({ result: { ...forecastData, estimateAvailable: false, expenseEstimateAvailable: false, estimated: null } })
assert.ok(knownForecastHtml.includes('forecastUnavailable') && knownForecastHtml.includes('forecastKnownExpenses') && knownForecastHtml.includes('forecastKnownBalance'))
const futureForecastHtml = await renderForecast({ result: { ...forecastData, status: 'future', elapsedDays: 0, estimated: null, estimateAvailable: false, expenseEstimateAvailable: false } })
assert.ok(futureForecastHtml.includes('forecastKnownFuture') && !futureForecastHtml.includes('forecastAdditional') && !futureForecastHtml.includes('forecastUnavailable'))
const finishedPeriod = { ...forecastPeriod, dateTo: forecastStart.getTime() / 1000 }
const pastHtml = await renderForecast({ report: { periods: [finishedPeriod] } })
assert.equal((pastHtml.match(/role="tab"/g) || []).length, 1, 'Completed periods have a single Development tab')
assert.equal(developmentVm.activeView, 'development', 'Past periods fall back to Development without changing the saved choice')
assert.ok(!pastHtml.includes('report-forecast-table'))
const failedForecastHtml = await renderForecast({ error: true })
assert.ok(failedForecastHtml.includes('role="alert"') && failedForecastHtml.includes('retry') && !failedForecastHtml.includes('report-forecast-table'))
const expenseForecastDetail = { index: 0, total: false, row: { type: 'expense', id: 7, direct: true } }
const expenseForecastHtml = await renderForecast({ detail: expenseForecastDetail, selection: annual.annualSelection({ ...forecastSelection, projectId: 7, entryScope: 'shared', partners: [20], labels: ['important'], hashtags: [5], amountMin: 101 }) })
assert.ok(expenseForecastHtml.includes('data-report-section="detail-chart"') && !expenseForecastHtml.includes('>forecastBalance<') && !expenseForecastHtml.includes('>forecastIncome<'))
assert.equal(forecastVm.params.dateFrom, annual.reportDate(forecastStart))
assert.equal(forecastVm.params.dateTo, annual.reportDate(forecastEnd))
assert.equal(forecastVm.params.detailId, '7')
assert.equal(forecastVm.params.detailDirect, true)
assert.equal(forecastVm.params.projectId, 7)
assert.equal(forecastVm.params.entryScope, 'shared')
assert.equal(forecastVm.params.partners, '20')
assert.equal(forecastVm.params.labels, 'important')
assert.equal(forecastVm.params.hashtags, '5')
assert.equal(forecastVm.params.amountMin, '1.01')
const personalForecastParams = { ...forecastVm.params }
const incomeForecastHtml = await renderForecast({ detail: { ...expenseForecastDetail, row: { type: 'income', id: 8 } } })
assert.ok(incomeForecastHtml.includes('forecastIncome') && !incomeForecastHtml.includes('forecastExpenses'))
await renderForecast({ detail: expenseForecastDetail, selection: annual.annualSelection({ ...forecastSelection, withoutArea: true }) })
assert.equal(forecastVm.params.withoutArea, 'true')
const previousForecastWindow = globalThis.window
try {
 const legacyPreferences = JSON.stringify({ 'chart-view': 'forecast', 'detail-chart-view': 'forecast', chart: false })
 const forecastStorage = new Map([['cobudget_annual_sections:forecast-reader', legacyPreferences]])
 globalThis.window = { OC: { getCurrentUser: () => ({ uid: 'forecast-reader' }) }, localStorage: { getItem: key => forecastStorage.get(key), setItem: (key, value) => forecastStorage.set(key, value) } }
 view.created()
 assert.equal(annual.readAnnualChartView('chart'), 'development', 'Opening Reports ignores old persistent Forecast preferences')
 assert.equal(annual.readAnnualChartView('detail-chart'), 'development')
 await renderForecast({ view: null })
 assert.equal(developmentVm.activeView, 'development')
 developmentVm.choose('forecast')
 await renderForecast({ view: null, selection: annual.annualSelection({ ...forecastSelection, labels: ['important'] }) })
 assert.equal(developmentVm.activeView, 'forecast', 'Reloading the chart after a filter change retains the choice within Reports')
 view.created()
 await renderForecast({ view: null })
 assert.equal(developmentVm.activeView, 'development', 'Returning to Reports starts with Development')
 await renderForecast({ detail: expenseForecastDetail })
 developmentVm.choose('forecast')
 assert.equal(annual.readAnnualChartView('detail-chart'), 'forecast')
 assert.equal(annual.readAnnualChartView('chart'), 'development', 'Overview and detail tab preferences are separate')
 await renderForecast({ detail: { ...expenseForecastDetail, row: { type: 'expense', id: 99 } } })
 developmentVm.choose('development')
 assert.equal(annual.readAnnualChartView('detail-chart'), 'development')
 developmentVm.setMode('period')
 assert.equal(developmentVm.forecastMode, 'cumulative', 'Development and Forecast retain independent amount display modes')
 const keyEvent = { key: 'End', preventDefault() {} }
 developmentVm.onTabKey(keyEvent)
 assert.equal(developmentVm.activeView, 'forecast', 'The tab strip supports keyboard navigation')
 await Vue.nextTick()
 globalThis.window = { OC: { getCurrentUser: () => ({ uid: 'another-reader' }) }, localStorage: { getItem: key => forecastStorage.get(key), setItem: (key, value) => forecastStorage.set(key, value) } }
 assert.equal(annual.readAnnualChartView('detail-chart'), 'development', 'Preferences are isolated between accounts')
 view.created()
 globalThis.window.OC.getCurrentUser = () => ({ uid: 'forecast-reader' })
 assert.equal(annual.readAnnualChartView('detail-chart'), 'development', 'A new Reports visit resets detail tabs too')
 assert.equal(forecastStorage.get('cobudget_annual_sections:forecast-reader'), legacyPreferences, 'Tab choices leave unrelated persisted section settings untouched')
} finally { globalThis.window = previousForecastWindow }

const getBeforeForecasts = bindings.axios.get
try {
 const pending = []
 bindings.axios.get = (_url, options) => new Promise((resolve, reject) => pending.push({ resolve, reject, params: options.params }))
 const state = { ...reportForecastComponent.data(), eligible: true, params: personalForecastParams }
 const first = reportForecastComponent.methods.load.call(state)
 state.params = { ...personalForecastParams, detailId: '99' }
 const second = reportForecastComponent.methods.load.call(state)
 pending[1].resolve({ data: forecastData }); await second
 pending[0].reject(Error('old request')); await first
 assert.equal(state.result, forecastData, 'An old request failure cannot replace the newest forecast')
 assert.equal(state.error, false)
 const third = reportForecastComponent.methods.load.call(state)
 assert.equal(state.result, null, 'Changing context removes figures from the previous subject while loading')
 state.eligible = false
 await reportForecastComponent.methods.load.call(state)
 pending[2].resolve({ data: forecastData }); await third
 assert.equal(state.result, null, 'A reply cannot restore a forecast after switching to a past period')
 assert.equal(state.loading, false)
 state.eligible = true
 bindings.axios.get = async () => { throw Error('offline') }
 await reportForecastComponent.methods.load.call(state)
 assert.equal(state.error, true)
 bindings.axios.get = async () => ({ data: forecastData })
 await reportForecastComponent.methods.load.call(state)
 assert.equal(state.error, false, 'Retry clears a previous failure')
 assert.equal(state.result, forecastData)
} finally { bindings.axios.get = getBeforeForecasts }
console.log('Report forecasts passed: shared tabs, three summary boxes, compact headers, exact chart endpoints, visible projected continuation, scoped detail requests, feature flags, remembered account preferences, keyboard navigation and request races.')

// Top 10 uses the server's ranked saved-payment list with the exact report scope.
let topVm, paymentSectionVm
const topRows = [
 { id: 101, type:'expense', date: holidayPeriods[0].dateFrom, description: 'Hotel <script>', paymentPartner: 'Hotel & Spa', category_name: 'Travel', amount: 100.01, personal_amount_cents: 4001 },
 { id: 102, type:'expense', date: holidayPeriods[1].dateFrom, description: '', paymentPartner: 'Restaurant', category_name: 'Food', amount: 25, personal_amount_cents: 1000 },
]
const topProps = { selection: annual.annualSelection({ ...holiday, projectId: 12, entryScope: 'personal', categories: [7], partners: [20], labels: ['important'], hashtags: [5], amountMin: 101 }), report: holidayReport, type: 'expense', title: 'Expenses', active: true }
const renderTop = async (props = {}, state = {}) => {
 const fixture = { ...topPaymentsComponent, watch: {}, data: () => ({ ...topPaymentsComponent.data(), entries: topRows, loaded: true, ...state }), created() { topVm = this } }
 return renderToString(basicGlobals(Vue.createSSRApp(fixture, { ...topProps, ...props })))
}
const topHtml = await renderTop()
assert.equal((topHtml.match(/report-top-payment-row/g) || []).length, 2, 'Fewer than ten saved payments leave a short list, without filler rows')
assert.equal(topVm.paymentAmount(topRows[0]),40.01,'Top 10 passes exact personal cents to the shared amount renderer')
assert.match(topHtml,/class="amount-text">[^<]*40,01</,'Personal Top 10 displays localized personal amounts')
assert.doesNotMatch(topHtml,/class="amount-text">[^<]*100,01</,'Visible payment amounts do not show source totals')
assert.ok(topHtml.includes('Hotel &lt;script&gt;') && topHtml.includes('Hotel &amp; Spa') && !topHtml.includes('<script>'), 'Names and descriptions remain escaped')
assert.ok(topHtml.includes('tableDescription') && topHtml.includes('tablePaymentPartner'), 'Top 10 uses the payment overview headings for separate description and from/to columns')
assert.ok(topHtml.includes('class="paymentPartner-cell">Restaurant</td>'), 'Payment partners retain their own desktop column')
assert.equal((topHtml.split('<thead>')[1].split('</thead>')[0].match(/<th /g)||[]).length,5,'Top 10 preserves five exported fields through the shared table')
assert.ok(!topHtml.includes('class="actions-cell"'),'Fixed rankings do not add a second action column')
assert.ok(!topHtml.includes('<button'), 'Payment rows do not add a nested button with a second cell hover')
assert.equal((topHtml.match(/tabindex="0"/g) || []).length, 2, 'Both payment rows remain keyboard reachable')
const fullTopDate = topVm.date(topRows[0].date), compactTopDate = topVm.date(topRows[0].date, true)
assert.ok(fullTopDate.includes(String(new Date(topRows[0].date * 1000).getFullYear())) && compactTopDate.length < fullTopDate.length, 'Compact mobile dates omit the year while canonical dates keep it')
assert.ok(topHtml.includes('data-report-export-label=') && topHtml.includes(String(new Date(topRows[0].date*1000).getFullYear())), 'Shared date cells retain full cross-year dates and export labels')
assert.deepEqual([topVm.params.limit, topVm.params.offset, topVm.params.sortBy, topVm.params.sortDir, topVm.params.type], [10, 0, 'amount', 'desc', 'expense'])
assert.equal(topVm.params.dateFrom, holidayPeriods.at(-1).dateFrom, 'Ranking covers the whole selected free date range')
assert.equal(topVm.params.dateTo, Math.min(holidayPeriods.at(-1).dateTo, holidayReport.cutoff))
assert.equal(topVm.params.categoryId, '7'); assert.equal(topVm.params.paymentPartnerId, undefined, 'The active grouping has no hidden partner filter'); assert.equal(topVm.params.isImportant, true); assert.equal(topVm.params.hashtagId, '5'); assert.equal(topVm.params.amountMin, '1.01')
assert.equal(topVm.params.entryScope, 'personal'); assert.equal(topVm.params.projectId, 12)
const sharedTopHtml = await renderTop({ selection: annual.annualSelection({ ...topProps.selection, entryScope: 'shared' }), type: 'income' })
assert.equal(topVm.paymentAmount(topRows[0]),100.01)
assert.match(sharedTopHtml,/class="amount-text">[^<]*100,01</)
assert.doesNotMatch(sharedTopHtml,/class="amount-text">[^<]*40,01</)
assert.equal(topVm.params.type, 'income'); assert.equal(topVm.params.entryScope, 'shared')
await renderTop({ selection: annual.annualSelection({ ...topProps.selection, grouping: 'labelsAndTags', categories: [7, 8], partners: [20, 21], hashtags: [5, 6] }) })
assert.equal(topVm.params.categories, '7,8'); assert.equal(topVm.params.partners, '20,21'); assert.equal(topVm.params.hashtags, '5,6')
await renderTop({ selection: annual.annualSelection({ ...holiday, projectId: null, withoutArea: true, grouping: 'labelsAndTags' }) })
assert.equal(topVm.params.withoutArea, true); assert.equal(topVm.params.reportGrouping, 'labelsAndTags')
assert.ok(!(await renderTop({}, { error: true })).includes('Hotel'), 'A failed ranking never displays stale rows')
assert.ok(!(await renderTop({}, { loading: true })).includes('Hotel'), 'Loading hides amounts from an obsolete context')
const topGetBefore = bindings.axios.get
try {
 const requests = []
 bindings.axios.get = (url, options) => new Promise(resolve => requests.push({ url, params: options.params, resolve }))
 const state = { ...topPaymentsComponent.data(), params: { ...topVm.params }, active: false, load() { return topPaymentsComponent.methods.load.call(this) } }
 topPaymentsComponent.methods.invalidate.call(state)
 assert.equal(requests.length, 0, 'The default overview makes no ranking request')
 const first = state.load(); state.params = { ...state.params, entryScope: 'shared' }; const second = state.load()
 assert.equal(requests[1].url, '/apps/cobudget/api/entries')
 requests[1].resolve({ data: { entries: [topRows[1]] } }); await second
 requests[0].resolve({ data: { entries: topRows } }); await first
 assert.deepEqual(state.entries, [topRows[1]], 'An obsolete response cannot replace the newly selected scope')
 bindings.axios.get = async () => { throw Error('offline') }
 await state.load(); assert.equal(state.error, true); assert.deepEqual(state.entries, [])
 bindings.axios.get = async () => ({ data: { entries: topRows } })
 await state.load(); assert.equal(state.error, false); assert.equal(state.loaded, true)
 const emitted = []
 const edit = (entry, scope = 'personal') => bindings.openReportPayment(entry, { entryScope: scope }, refTexts, payload => emitted.push(payload))
 await edit({ ...topRows[0], is_settled: true }, 'shared')
 await edit({ ...topRows[0], is_locked: true, can_delete: false })
 assert.equal(emitted.length, 0, 'Settlement and ownership restrictions also protect ranking rows')
 bindings.axios.get = async url => { assert.equal(url, '/apps/cobudget/api/entries/999'); return { data: { entry: { id: 999, project_id: 12 } } } }
 await edit({ ...topRows[0], is_locked: true, can_delete: true, editable_entry_id: 999 })
 assert.deepEqual(emitted[0], { entry: { id: 999, project_id: 12 }, selectedEntryId: 101, projectId: 12, isFuture: false }, 'An editable personal projection opens its authorized source and preserves the selected row')
 await edit(topRows[1])
 assert.equal(emitted[1].entry, topRows[1], 'Released or standalone payments retain their own editable representation')
} finally { bindings.axios.get = topGetBefore }
const sectionProps = { section: 'expense', title: 'Expenses', firstHeading: 'Category', selection: holiday, report: holidayReport, columns: ['March', 'Total'], rows: [], periods: holidayReport.periods }
const renderPaymentSection = async (state = {}) => {
 const fixture = { ...paymentSectionComponent, data() { return { ...paymentSectionComponent.data.call(this), ...state } }, created() { paymentSectionVm = this } }
 const app = basicGlobals(Vue.createSSRApp(fixture, sectionProps))
 app.component('RouterLink', { setup: (_, { slots }) => () => Vue.h('a', slots.default?.()) })
 return renderToString(app)
}
const defaultPaymentSection = await renderPaymentSection()
assert.equal(paymentSectionVm.view, 'overview')
assert.ok(!defaultPaymentSection.includes('report-top-payments-table'), 'Rankings are created only after their tab is opened')
paymentSectionVm.choose('top'); assert.equal(paymentSectionVm.topVisited, true)
const topSectionHtml = await renderPaymentSection({ view: 'top', topVisited: true })
assert.match(topSectionHtml, /class="annual-table[^\"]*is-collapsed/, 'The hidden period table is excluded from printing while Top 10 is selected')
assert.ok(topSectionHtml.includes('class="report-top-payments') && topSectionHtml.includes('role="tabpanel"'))
paymentSectionVm.onTabKey({ key: 'Home', preventDefault() {} }); assert.equal(paymentSectionVm.view, 'overview')
console.log('Report Top 10 passed: lazy tabs, full date ranges, exact personal/shared cents, payment filters, income/expense separation, safe text, source editing, settlement restrictions, print selection, stale replies, loading and retry.')

const exportMetadata = exportHelpers.reportExportMetadata(topProps.selection, { report: holidayReport, scopeLabel: '=Shared area', currency: 'EUR', texts: refTexts, formatDate: value => String(value), filterLabels: ['Food; "Travel"'] })
assert.equal(exportMetadata[0][1], `${holidayPeriods.at(-1).dateFrom} – ${holidayPeriods.at(-1).dateTo}`)
assert.ok(exportMetadata.some(([, value]) => value === 'personalShare · EUR'), 'The export context names the selected amounts and currency')
const contextualCsv = annual.buildAnnualOverviewCsv({ labelHeading: 'Category', columns: ['March', 'Total'], sections: [], metadata: exportMetadata })
assert.ok(contextualCsv.includes("area;'=Shared area"), 'CSV formula protection also covers user-supplied values in metadata')
assert.ok(contextualCsv.includes('"Food; ""Travel"""'), 'CSV metadata quotes separators and embedded quotes')
const paymentPrintContext = { selection: { entryScope: 'personal' }, texts: refTexts, formatDate: String, formatCents: String, projects: [{ id: 12, name: 'Family & friends' }] }
const printPayment = { ...topRows[0], project_id: 12, type: 'expense', user_display_name: 'Alex' }
const personalPrintData = exportHelpers.reportPaymentPrintData([printPayment, { ...printPayment, project_id: null, personal_amount_cents: 0 }], paymentPrintContext)
assert.equal(personalPrintData.rows[0].at(-1), '4001', 'Printed personal payments retain their stored exact-cent shares')
assert.equal(personalPrintData.rows[1].at(-1), '0', 'A zero personal share does not fall back to the full amount')
assert.ok(personalPrintData.rows[0].includes('Family & friends') && personalPrintData.rows[1].includes(refTexts.annual.withoutArea()), 'Personal reports identify payment origins across areas')
const sharedPrintData = exportHelpers.reportPaymentPrintData([{ ...printPayment, amount_cents: 10002 }, printPayment], { ...paymentPrintContext, selection: { entryScope: 'shared', projectId: 12 }, detailType: 'expense' })
assert.deepEqual(sharedPrintData.rows.map(row => row.at(-1)), ['10002', '10001'], 'Entire-area printing prefers canonical cents and supports the existing decimal-only list payload')
assert.ok(sharedPrintData.rows[0].includes('Alex'), 'Shared reports name the payer')
assert.ok(!sharedPrintData.columns.includes(refTexts.annual.groupingType()), 'Single-type reports avoid a redundant payment-type column')
const savedPrintGet = bindings.axios.get
try {
 const offsets = []
 bindings.axios.get = async (_url, { params }) => { offsets.push(params.offset); return { data: { total: 3, entries: params.offset === 0 ? [{ id: 1 }, { id: 2 }] : [{ id: 3 }] } } }
 assert.deepEqual((await bindings.loadReportPayments({ type: 'expense', dateFrom: 100, dateTo: 200 })).map(row => row.id), [1, 2, 3])
 assert.deepEqual(offsets, [0, 2], 'Printing loads every matching page using the actual returned page size')
 assert.deepEqual(await bindings.loadReportPayments({ dateFrom: 200, dateTo: 100 }), [], 'An entirely future period with payments only through today prints an empty saved list')
 assert.equal(offsets.length, 2, 'An empty effective date range does not make an invalid API request')
 await assert.rejects(bindings.loadReportPayments({}, () => false), /selection changed/, 'Changing filters cancels an obsolete payment export')
 bindings.axios.get = async () => ({ data: { total: 3, entries: [{ id: 1 }] } })
 await assert.rejects(bindings.loadReportPayments({}), /Duplicate/, 'Changing data cannot silently duplicate rows in a multi-page export')
 bindings.axios.get = async () => ({ data: { total: 2, entries: [] } })
 await assert.rejects(bindings.loadReportPayments({}), /Incomplete/, 'A partial response cannot produce a complete-looking payment report')
} finally { bindings.axios.get = savedPrintGet }
const detailPrintState = {
 ...bindings.ReportDetailView.data(), report: holidayReport, selection: topProps.selection, chartKey: 'expense:7:0',
 detail: { row: { type: 'expense' }, index: 0, label: 'Travel' }, scopeLabel: 'Family', projects: paymentPrintContext.projects,
 $texts: refTexts, $currency: 'EUR', $formatMoneyFromCents: String, exportMetadata,
 $refs: { printSource: { querySelector: () => ({}) }, payments: { loadPrintRows: async () => [printPayment] } },
}
const detailPrintCount = printCalls.length
await bindings.ReportDetailView.methods.printReport.call(detailPrintState)
assert.equal(printCalls.length, detailPrintCount + 1, 'Detail printing includes its complete saved-payment list')
assert.equal(printCalls.at(-1)[1].payments.rows[0].at(-1), '4001')
assert.equal(printCalls.at(-1)[1].metadata[0][1], `${bindings.formatCalendarLabel(new Date(holidayPeriods[0].dateFrom * 1000), { day: 'numeric', month: 'short', year: 'numeric' })} – ${bindings.formatCalendarLabel(new Date(holidayPeriods[0].dateTo * 1000), { day: 'numeric', month: 'short', year: 'numeric' })}`, 'Detail headers name the opened period instead of the full overview dates')
let finishPrintRows
detailPrintState.$refs.payments.loadPrintRows = () => new Promise(resolve => { finishPrintRows = resolve })
const obsoleteDetailPrint = bindings.ReportDetailView.methods.printReport.call(detailPrintState)
detailPrintState.selection = { ...detailPrintState.selection, entryScope: 'shared' }
finishPrintRows([printPayment]); await obsoleteDetailPrint
assert.equal(printCalls.length, detailPrintCount + 1, 'Changing financial perspective while payments load cannot print mixed report data')
assert.equal(detailPrintState.printing, false)
console.log('Report export context passed: exact dates, personal/shared cents, payer and area columns, safe CSV metadata and complete/cancelled multi-page payment reads.')

if (process.env.COBUDGET_REPORT_FIXTURES_DIR) {
 fs.mkdirSync(process.env.COBUDGET_REPORT_FIXTURES_DIR, { recursive: true })
 fs.writeFileSync(`${process.env.COBUDGET_REPORT_FIXTURES_DIR}/fixtures.json`, JSON.stringify({ overview: focusedChartMarkup, forecast: forecastHtml, detail: annualTotalDetailHtml + compositionHtml + '<section class="report-detail-section report-payments" aria-label="Gespeicherte Zahlungen"><p>Aktuelle Zahlungsseite</p></section>', topSection: topSectionHtml, topTable: topHtml }))
}

// All export targets consume the same active-view snapshot, with heterogeneous
// table shapes and exact cents rather than parsed, rounded screen text.
const exportTexts = createReportApp(selection).config.globalProperties.$texts
const money = exportHelpers.reportExportMoney
const specialExport = {
 metadata: [['Bereich', '=SUM(A1:A2)']],
 sections: [{ title: 'Top 10', columns: ['Beschreibung', 'Betrag'], rows: [['=1+1', money(3240)], [' leading; "quotes"\nand tabs\t', money(-105)], ['@remote', money(0)]] }],
}
const csvSnapshot = exportHelpers.buildReportExport(specialExport)
const tabSnapshot = exportHelpers.buildReportExport(specialExport, '\t')
assert.ok(csvSnapshot.startsWith('\uFEFF'))
assert.ok(csvSnapshot.includes("'=SUM(A1:A2)"))
assert.ok(csvSnapshot.includes("'=1+1;32,40"))
assert.ok(csvSnapshot.includes(';-1,05'), 'Negative monetary cells stay numbers, not protected text')
assert.ok(csvSnapshot.includes('" leading; ""quotes""\nand tabs\t"'))
assert.ok(tabSnapshot.includes("'=1+1\t32,40"))
assert.ok(!tabSnapshot.startsWith('\uFEFF'), 'Clipboard text has no file BOM')
assert.throws(() => exportHelpers.buildReportExport({ sections: [{ rows: [[money(NaN)]] }] }), /Invalid export amount/)
const topExportState = { loading: false, error: false, loaded: true, entries: [{ id: 1, date: 1700000000, description: 'TOP_ONLY', type: 'expense', amount_cents: 10000, personal_amount_cents: 3240 }], selection: { entryScope: 'personal' }, type: 'expense', tableTitle: 'Ausgaben · Top 10', $texts: exportTexts, date: value => `date:${value}` }
const topExportData = topPaymentsComponent.methods.exportData.call(topExportState)
assert.equal(topExportData[0].rows[0].at(-1).cents, 3240)
assert.equal(topPaymentsComponent.methods.exportData.call({ ...topExportState, selection: { entryScope: 'shared' } })[0].rows[0].at(-1).cents, 10000)
assert.throws(() => topPaymentsComponent.methods.exportData.call({ ...topExportState, loaded: false }), error => error.code === 'REPORT_LOADING')
const groupedExportData = [{ title: 'Ausgaben', columns: ['Kategorie', 'Gesamt'], rows: [['GROUP_ONLY', money(9999)]] }]
const exportTabs = { view: 'top', $refs: { topPayments: { exportData: () => topExportData }, overviewTable: { exportData: () => groupedExportData } } }
assert.deepEqual(paymentSectionComponent.methods.exportData.call(exportTabs), topExportData)
exportTabs.view = 'overview'
assert.deepEqual(paymentSectionComponent.methods.exportData.call(exportTabs), groupedExportData)
const activeDevelopment = { activeView: 'forecast', summaryRows: [], $texts: exportTexts, forecastRows: () => [], $refs: {
 forecast: { result: {}, loading: false, error: false, exportData: () => [{ title: 'BREAKDOWN_ONLY', rows: [] }] },
 forecastChart: { exportData: () => [{ title: 'FORECAST_ONLY', rows: [] }] }, developmentChart: { exportData: () => [{ title: 'DEVELOPMENT_ONLY', rows: [] }] },
} }
assert.deepEqual(developmentComponent.methods.exportData.call(activeDevelopment).map(item => item.title), ['FORECAST_ONLY', 'BREAKDOWN_ONLY'])
activeDevelopment.activeView = 'development'
assert.deepEqual(developmentComponent.methods.exportData.call(activeDevelopment).map(item => item.title), ['DEVELOPMENT_ONLY'])
const wholeExport = { paymentTypes: ['expense'], $refs: { development: { exportData: () => [{ title: 'DEVELOPMENT_ONLY', rows: [] }] }, paymentSections: [{ section: 'expense', exportData: () => paymentSectionComponent.methods.exportData.call({ ...exportTabs, view: 'top' }) }], areaOverview: { exportData: () => [{ title: 'Bereiche · Ausgaben · Gesamt pro Bereich', rows: [['Area total', money(10000)]] }] } } }
const wholeExportCsv = exportHelpers.buildReportExport({ sections: view.methods.annualExportSections.call(wholeExport) })
assert.ok(wholeExportCsv.includes('TOP_ONLY'))
assert.ok(!wholeExportCsv.includes('GROUP_ONLY'), 'Whole-report export must not silently fall back to the inactive group table')
assert.ok(wholeExportCsv.includes('Gesamt pro Bereich'))
const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator')
const originalClipboardItem = globalThis.ClipboardItem
let clipboardText = ''
Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { clipboard: { writeText: async value => { clipboardText = value } } } })
await bindings.deliverReportExport('copy', topExportData, { metadata: [['REPORT_SETTINGS_ONLY', 'FILTERS_ONLY']], filename: 'top.csv', section: true }, { common: { copiedToClipboard: () => 'Copied' } })
assert.ok(clipboardText.includes('TOP_ONLY'))
assert.ok(clipboardText.includes('\t32,40'))
assert.ok(clipboardText.startsWith(topExportData[0].columns.join('\t') + '\r\n'), 'Section clipboard starts directly with column headers')
assert.ok(!clipboardText.includes(topExportData[0].title), 'Section titles are excluded from clipboard contents')
assert.equal(clipboardText, exportHelpers.buildReportExport({ sections: topExportData, includeSectionTitles: false }, '\t'), 'Section clipboard contains only its active data and no report settings')
const originalTopDescription = topExportState.entries[0].description
topExportState.entries[0].description = 'CHANGED_AFTER_CLICK'
assert.ok(clipboardText.includes(originalTopDescription), 'An export snapshot remains independent of later entry changes')
let clipboardStarted = false, finishRows
const delayedRows = new Promise(resolve => { finishRows = resolve })
globalThis.ClipboardItem = class { constructor(data) { this.data = data } }
navigator.clipboard = { write: async items => { clipboardStarted = true; clipboardText = await (await items[0].data['text/plain']).text() } }
const pendingCopy = bindings.deliverReportExport('copy', delayedRows, { metadata: [], filename: 'all.csv' }, { common: { copiedToClipboard: () => 'Copied' } })
assert.equal(clipboardStarted, true, 'Clipboard writing starts within the click before asynchronous payment reads complete')
finishRows(topExportData)
await pendingCopy
assert.ok(clipboardText.includes('TOP_ONLY'))
navigator.clipboard = { writeText: async () => { throw new Error('Permission denied') } }
await assert.rejects(bindings.deliverReportExport('copy', topExportData, { metadata: [] }, {}), /Permission denied/)
if (originalNavigator) Object.defineProperty(globalThis, 'navigator', originalNavigator)
else delete globalThis.navigator
if (originalClipboardItem === undefined) delete globalThis.ClipboardItem
else globalThis.ClipboardItem = originalClipboardItem
console.log('Section exports passed: active Top 10/overview/forecast, whole-report composition, personal/full-area cents, safe CSV/TSV, clipboard activation and denied access.')
const exportRead = { loading: false, error: false, selection: { entryScope: 'personal' }, projects: [], detail: { row: { type: 'expense' } }, $texts: exportTexts, loadPrintRows: async () => topExportState.entries }
const capturedPayments = bindings.ReportPayments.methods.exportData.call(exportRead)
exportRead.selection = { entryScope: 'shared' }
assert.equal((await capturedPayments)[0].rows[0].at(-1).cents, 3240, 'An asynchronous full-payment export keeps the amount perspective from its click')
const filesBeforeSection = savedFiles.length
await bindings.deliverReportExport('save', topExportData, { metadata: [['REPORT_SETTINGS_ONLY', 'FILTERS_ONLY']], filename: 'report.csv', section: true }, exportTexts)
assert.equal(savedFiles.length, filesBeforeSection + 1)
assert.ok(savedFiles.at(-1).body.includes('TOP_ONLY') && !savedFiles.at(-1).body.includes('GROUP_ONLY'))
assert.ok(savedFiles.at(-1).url.includes('Top-10.csv'), 'Nextcloud section filenames identify the active tab')
console.log('Section storage passed: the same active payload goes to Nextcloud and asynchronous payment reads retain the captured perspective.')

assert.equal(savedFiles.at(-1).body, exportHelpers.buildReportExport({ sections: topExportData, includeSectionTitles: false }), 'Saving a section omits report context just like copying')
const reportContext = { filename: 'top.csv', metadata: [['REPORT_SETTINGS_ONLY', 'FILTERS_ONLY']] }
await bindings.deliverReportExport('download', topExportData, { ...reportContext, section: true }, exportTexts)
assert.equal(csvDownloads.at(-1).contents, exportHelpers.buildReportExport({ sections: topExportData, includeSectionTitles: false }), 'Section downloads contain only their active data')
await bindings.deliverReportExport('download', topExportData, reportContext, exportTexts)
assert.equal(csvDownloads.at(-1).contents, exportHelpers.buildReportExport({ metadata: reportContext.metadata, sections: topExportData }), 'Whole-report downloads retain the context block')
console.log('Section-only exports passed: download, Nextcloud and clipboard omit report settings; whole-report export retains them.')

assert.equal(exportHelpers.buildReportExport({ includeSectionTitles: false, sections: [{ title: 'TITLE_ONLY', columns: [], rows: [['Metric', money(3240)]] }] }), '\uFEFFMetric;32,40\r\n\r\n', 'Headerless detail sections start with their first data row, without a title or empty header line')
assert.ok(!savedFiles.at(-1).body.includes(topExportData[0].title), 'Stored section files omit the title as well')
console.log('Section contents passed: all export targets omit section titles and start directly with columns or data.')

const displayValue = exportHelpers.reportExportDisplayedValue
const formattedSection = [{ title: 'Einnahmen', columns: ['Kategorie', 'Januar', 'Gesamt'], rows: [['Gehalt', displayValue('€\u00a01.000,00'), displayValue('€\u00a01.000,00')], ['Korrektur', displayValue('-€\u00a012,34'), '–'], ['=1+1', displayValue('0,00 €'), displayValue('-0,01 €')]] }]
const formattedCsv = exportHelpers.buildReportExport({ sections: formattedSection, includeSectionTitles: false })
assert.ok(formattedCsv.startsWith('\uFEFFKategorie;Januar;Gesamt\r\nGehalt;€\u00a01.000,00;€\u00a01.000,00'))
assert.ok(formattedCsv.includes('Korrektur;-€\u00a012,34;–'), 'Displayed negative amounts and empty dashes are preserved')
assert.ok(formattedCsv.includes("'=1+1;0,00 €;-0,01 €"), 'Currency formatting does not weaken formula protection for descriptions')
const maliciousDisplay = exportHelpers.buildReportExport({ sections: [{ columns: [], rows: [[displayValue('-1+HYPERLINK("bad")')], [{ display: '=1+1' }]] }], includeSectionTitles: false })
assert.ok(maliciousDisplay.includes("'-1+HYPERLINK") && maliciousDisplay.includes("'=1+1"))
let forwardedDisplay
paymentSectionComponent.methods.exportData.call({ view: 'top', $refs: { topPayments: { exportData: options => { forwardedDisplay = options } } } }, { display: true })
assert.deepEqual(forwardedDisplay, { display: true }, 'Section menus can request the displayed active tab instead of canonical export values')
console.log('Displayed section formatting passed: compact headings, currency symbols, locale spaces, negative amounts, empty dashes and formula-safe descriptions.')

await bindings.deliverReportExport('save', formattedSection, { section: true, filename: 'formatted.csv', metadata: [['SETTINGS_ONLY', 'not visible']] }, exportTexts)
assert.equal(savedFiles.at(-1).body, formattedCsv, 'Nextcloud receives the same formatted section cells as a download')
assert.equal(exportHelpers.buildReportExport({ sections: [{ rows: [[displayValue('+10 % (−€\u00a012,34)')]] }], includeSectionTitles: false }), '\uFEFF+10 % (−€\u00a012,34)\r\n\r\n', 'Displayed comparison percentages and signed differences stay intact')

// Nested composition details keep independent AND predicates and identity-only URLs.
const drillQuery = { ...annual.annualRouteQuery(annual.annualSelection({ year: 2026, timeRange: 'year', projectId: 7, entryScope: 'shared', partners: [20, 21], labels: ['important'], hashtags: [5], amountMin: 101 })), detailType: 'expense', detailId: '10', detailPeriod: '9', detailPage: '3', detailArea: '7', detailAreaScope: 'shared', detailAreaTags: '5' }
const firstDrill = reportDetails.reportBreakdownRoute(drillQuery, 'paymentPartners', { id: 20, name: 'URL_TEXT_MUST_NOT_APPEAR' }, 'expense')
assert.deepEqual(reportDetails.reportDrilldownPath(firstDrill.query), [{ dimension: 'paymentPartners', id: '20', type: 'expense' }])
assert.equal(firstDrill.query.detailPage, undefined)
for (const field of ['timeRange', 'year', 'projectId', 'entryScope', 'partners', 'labels', 'hashtags', 'amountMin', 'detailPeriod', 'detailArea', 'detailAreaScope', 'detailAreaTags']) assert.equal(firstDrill.query[field], drillQuery[field], `Nested navigation retains ${field}`)
assert.ok(!JSON.stringify(firstDrill).includes('URL_TEXT_MUST_NOT_APPEAR'), 'Names and amounts are never trusted URL state')
const nextDrill = reportDetails.reportBreakdownRoute(firstDrill.query, 'hashtags', { id: 0 }, 'expense')
assert.deepEqual(reportDetails.reportDetailParentQuery(nextDrill.query), firstDrill.query)
const firstParent = { ...drillQuery }; delete firstParent.detailPage
assert.deepEqual(reportDetails.reportDetailParentQuery(firstDrill.query), firstParent)
assert.equal(reportDetails.reportBreakdownRoute(firstDrill.query, 'paymentPartners', { id: 21 }, 'expense'), null, 'Repeated dimensions cannot loop or replace a preceding choice')
for (const detailPath of ['null', '{}', '[]', '[null]', JSON.stringify([{ dimension: 'categories', id: '-1', type: 'expense' }]), JSON.stringify([{ dimension: 'labels', id: 'unknown', type: 'expense' }]), JSON.stringify([{ dimension: 'categories', id: '9007199254740992', type: 'expense' }])]) assert.equal(reportDetails.reportDrilldownPath({ detailPath }), null, 'Malformed paths fail closed')
assert.deepEqual(reportDetails.reportDrilldownPath({}), [])
const drillSubject = { total: false, index: 9, row: { id: 10, type: 'expense' }, label: 'Food' }
const drillFilters = reportDetails.reportDrilldownFilters(drillSubject, { grouping: 'categories' }, reportDetails.reportDrilldownPath(nextDrill.query))
assert.deepEqual(drillFilters, [{ dimension: 'type', id: 'expense' }, { dimension: 'categories', id: '10', direct: false }, { dimension: 'paymentPartners', id: '20' }, { dimension: 'hashtags', id: '0' }], 'The original category including children intersects partner and no-tag choices')
const tagSubject = { ...drillSubject, row: { id: 'tag:5', type: 'expense' } }
assert.deepEqual(reportDetails.reportDrilldownFilters(tagSubject, { grouping: 'labelsAndTags' }, reportDetails.reportDrilldownPath(firstDrill.query))[1], { dimension: 'hashtags', id: '5' })
assert.equal(reportDetails.reportDrilldownFilters({ ...drillSubject, row: { id: 10, type: 'income' } }, { grouping: 'categories' }, reportDetails.reportDrilldownPath(firstDrill.query)), null, 'A nested URL cannot change the original payment type')
const categoryPath = [{ dimension: 'categories', id: '11', type: 'expense' }]
assert.deepEqual(reportDetails.reportDrilldownFilters({ ...drillSubject, total: true }, { grouping: 'categories' }, categoryPath)[1], { dimension: 'categories', id: '11', direct: true }, 'Flat composition category rows keep exact direct assignments')
const narrowedSelection = annual.annualSelection({ ...compositionProps.selection, reportFilters: JSON.stringify(drillFilters) })
assert.equal(annual.annualRequestParams(narrowedSelection).reportFilters, JSON.stringify(drillFilters))
assert.equal(annual.annualRequestParams({ ...narrowedSelection, timeRange: 'custom', dateFrom: '2025-01-01', dateTo: '2025-12-31' }, { comparison: 'detail' }).reportFilters, JSON.stringify(drillFilters), 'References inherit the combination even outside the selected dates')
assert.equal(reportDetails.reportPaymentParams({ query: { type: 'expense', dateFrom: '1', dateTo: '100' } }, narrowedSelection, 100).reportFilters, JSON.stringify(drillFilters))
await renderComposition({ detail: { ...balanceCompositionDetail, scopeDimensions: ['categories', 'paymentPartners'] } })
assert.ok(!compositionVm.dimensions.some(item => ['categories', 'paymentPartners'].includes(item.key)), 'Already selected path dimensions do not reappear')

const drillPage = component('src/components/ReportDetailPage.vue')
const drillVm = { ...drillPage.data(), detail: drillSubject, report: annualCategory, selection: annual.annualSelection({ ...compositionProps.selection }), $route: { query: firstDrill.query }, $texts: compositionVm.$texts, $nextTick: async () => {} }
for (const [key, getter] of Object.entries(drillPage.computed)) Object.defineProperty(drillVm, key, { get: () => getter.call(drillVm) })
const previousDrillGet = bindings.axios.get
const drillRequests = []
const narrowedBreakdown = { ...compositionData, groups: { ...compositionData.groups, paymentPartners: { expense: [{ id: 20, name: 'Supermarket', amountCents: 202, count: 1 }] } } }
bindings.axios.get = async (url, { params }) => { drillRequests.push({ url, params }); return { data: url.endsWith('/report-breakdown') ? narrowedBreakdown : annualCategory } }
await drillPage.methods.load.call(drillVm)
assert.equal(drillVm.error, false)
assert.equal(drillRequests.length, 2, 'The nested report and authorized path labels are loaded together')
assert.equal(drillVm.activeDetail.label, 'Food → Supermarket')
assert.equal(drillVm.activeDetail.total, true, 'The combined subject uses unique filtered totals')
assert.ok(drillVm.activeDetail.scopeDimensions.includes('paymentPartners'))
assert.equal(drillRequests[1].params.dateFrom, annual.reportDate(new Date(annualCategory.periods[9].dateFrom * 1000)))
for (const request of drillRequests) assert.deepEqual(JSON.parse(request.params.reportFilters), [{ dimension: 'type', id: 'expense' }, { dimension: 'categories', id: '10', direct: false }, { dimension: 'paymentPartners', id: '20' }])
await drillPage.methods.load.call(drillVm)
assert.equal(drillRequests.length, 2, 'Back to a loaded detail reuses its report')
drillVm.$route.query = { ...firstDrill.query, detailPath: '{bad' }
await drillPage.methods.load.call(drillVm)
assert.equal(drillVm.activeDetail, null)
assert.equal(drillRequests.length, 2, 'Malformed paths never request a wider population')
drillVm.$route.query = drillQuery
await drillPage.methods.load.call(drillVm)
assert.equal(drillVm.activeDetail, drillSubject, 'The last Back step restores the original detail')
const missingTrail = reportDetails.reportDrilldownLabels({ groups: {} }, reportDetails.reportDrilldownPath(firstDrill.query), drillVm.$texts)
assert.equal(missingTrail, null, 'Missing/deleted/foreign identities cannot reuse names from URL state')

let nestedBacks = 0, nestedFallback
const nestedParentRoute = { name: 'annual-overview', query: reportDetails.reportDetailParentQuery(firstDrill.query) }
view.methods.closeDetails.call({ $route: { query: firstDrill.query }, $router: { options: { history: { state: { back: '/parent' } } }, resolve: () => ({ name: 'annual-overview', query: { ...nestedParentRoute.query, detailSort: 'amount', detailDir: 'asc', detailPage: '2' } }), back() { nestedBacks++ } } })
assert.equal(nestedBacks, 1, 'The section Back button uses browser history when it is the preceding detail')
view.methods.closeDetails.call({ $route: { query: firstDrill.query }, $router: { resolve: () => ({ fullPath: '/parent' }), replace(route) { nestedFallback = route } } })
assert.deepEqual(nestedFallback, nestedParentRoute, 'Reloaded/shared nested links still return one detail level without leaving Report')
bindings.axios.get = previousDrillGet
console.log('Nested Report details passed: exact intersections, identity-only links, retained scope, authorized titles, bounded paths and one-level Back.')

// Equal-name report groups keep stable identity and payment-origin details across routes and exports.
{
 const key = `name:${'a'.repeat(64)}`
 const texts = { annual: { withoutArea: () => 'Ohne Bereich' }, common: { mainCategory: () => 'Hauptkategorie' } }
 const group = { id: 11, reportKey: key, name: 'Strom', label: 'Strom', type: 'expense', key: `expense-${key}-false`, months: [606], counts: [3], total: 606, totalCount: 3, children: [], areas: [
  { id: 11, reportKey: `${key}:area:7`, areaId: 7, name: 'Haus', label: 'Haus', months: [505], counts: [2], total: 505, totalCount: 2 },
  { id: 11, reportKey: `${key}:area:0`, areaId: 0, name: '', label: 'Ohne Bereich', months: [101], counts: [1], total: 101, totalCount: 1 },
 ] }
 const periods = [{ dateFrom: 1, dateTo: 10 }, { dateFrom: 1, dateTo: 10 }]
 const report = { periods, groups: { expense: [group] }, totals: { expense: { total: 606, months: [606] } } }
 const totals = [{ ...report.totals.expense, type: 'expense', label: 'Ausgaben' }]
 const query = reportDetails.reportDetailQuery({ row: { ...group.areas[0], type: 'expense' }, index: 1 }, periods)
 assert.equal(query.detailId, `${key}:area:7`)
 const detail = reportDetails.resolveReportDetail(query, report, [group], totals, 'Hauptkategorie')
 assert.equal(detail.label, 'Strom · Haus')
 assert.equal(detail.row.total, 505)
 assert.equal(reportDetails.reportDetailRow(report, detail).total, 505)
 const reordered = structuredClone(report)
 reordered.groups.expense[0].id = 99
 assert.equal(reportDetails.reportDetailRow(reordered, detail).total, 505, 'Changes to representative master IDs cannot change the subject')
 const state = { summary: false, sortedRows: [group], expanded: { [group.key]: true }, $texts: texts, descendants: table.methods.descendants }
 const visible = table.computed.visibleRows.call(state)
 assert.deepEqual(visible.map(row => [row.label, row.depth, row.total]), [['Strom', 0, 606], ['Haus', 1, 505], ['Ohne Bereich', 1, 101]])
 assert.deepEqual(visible.map(row => [row.depth, row.treeLast, row.treeGuides]), [[0, true, []], [1, false, []], [1, true, []]], 'Area connectors end at the final sibling')
 const parent = { ...group, key: 'parent', name: 'Haushalt', label: 'Haushalt', areas: [], children: [group, { ...group, key: 'sibling', reportKey: 'sibling', areas: [] }] }
 const nestedTree = table.computed.visibleRows.call({ ...state, sortedRows: [parent], expanded: { parent: true, [group.key]: true } })
 assert.deepEqual(nestedTree.map(row => [row.depth, row.treeGuides]), [[0, []], [1, []], [2, [true]], [2, [true]], [1, []]], 'A child branch continues through expanded area rows to its next sibling')
 assert.equal(table.computed.hasHierarchy.call(state), true)

 state.expanded = {}
 assert.equal(table.computed.visibleRows.call(state).length, 1)
 const withoutAreaOnly = { ...group, areas: [{ ...group.areas[1], total: group.total, months: group.months }] }
 assert.deepEqual(table.methods.descendants(withoutAreaOnly), [], 'A lone Without area subtotal has no disclosure')
 assert.equal(table.computed.hasHierarchy.call({ ...state, sortedRows: [withoutAreaOnly] }), false, 'No empty disclosure gutter is reserved for a flat table')
 assert.equal(table.computed.visibleRows.call({ ...state, sortedRows: [withoutAreaOnly], expanded: { [group.key]: true } }).length, 1, 'A stale expanded state cannot reveal the redundant subtotal')
 assert.deepEqual(table.methods.descendants({ ...group, areas: [group.areas[0]] }), [group.areas[0]], 'A single real area still explains the payment origin')
 assert.deepEqual(table.methods.descendants({ ...withoutAreaOnly, children: [group] }), [group], 'Category hierarchy remains expandable')
 assert.deepEqual(reportDetails.reportAreaRows({}), [], 'Rows without area breakdown have no disclosure')
 const breakdownState = { rows: [withoutAreaOnly], expanded: { [group.reportKey]: true }, $texts: texts }
 assert.equal(reportBreakdownComponent.computed.visibleRows.call(breakdownState).length, 1, 'Detail composition also omits a lone Without area subtotal')
 breakdownState.rows = [group]
 assert.deepEqual(reportBreakdownComponent.computed.visibleRows.call(breakdownState).map(row => row.label), ['Strom', 'Haus', 'Ohne Bereich'], 'Mixed payment origins remain available in detail composition')

 const scopedSelection = annual.annualSelection({ year: 2026, grouping: 'categories', partners: [20] })
 const paymentRoute = annual.annualPaymentRoute(scopedSelection, periods[0], 'expense', group.areas[0])
 assert.equal(paymentRoute.query.categoryId, undefined, 'Grouped links never narrow to a representative category ID')
 assert.deepEqual(JSON.parse(paymentRoute.query.reportFilters), [{ dimension: 'categories', id: `${key}:area:7`, direct: false }])
 const params = reportDetails.reportPaymentParams(paymentRoute, scopedSelection, 10)
 assert.equal(params.reportFilters, paymentRoute.query.reportFilters)
 assert.equal(params.paymentPartnerId, '20', 'Other report filters still intersect the group')
 const drill = reportDetails.reportBreakdownRoute({ detailType: 'expense', detailId: 'total' }, 'categories', group.areas[0], 'expense')
 assert.equal(reportDetails.reportDrilldownPath(drill.query)[0].id, `${key}:area:7`)
 assert.deepEqual(reportDetails.reportDrilldownLabels({ groups: { categories: { expense: [group] } } }, reportDetails.reportDrilldownPath(drill.query), texts), ['Haus'])
 const exported = exportHelpers.reportAmountExportSection({ title: 'Ausgaben', firstHeading: 'Kategorie', columns: ['Januar', 'Gesamt'], rows: [group], texts })
 assert.deepEqual(exported.rows.map(row => [row[0], row.at(-1)]), [['Strom', { cents: 606 }], ['Strom · Haus', { cents: 505 }], ['Strom · Ohne Bereich', { cents: 101 }]])
 console.log('Grouped lookup reports passed: stable name identity, area expansion, exact payment filters, nested details and exports.')
}

// Section markers follow the effective predicates, including zero-valued IDs,
// multi-select routes and the Planned toggle nested in Labels.
{
 const table=component('src/components/TableFilters.vue')
 const base=table.props.initialFilters.default()
 const vm={localFilters:{...base}}
 for(const [key,method] of Object.entries(table.methods)) vm[key]=method.bind(vm)
 for(const [key,getter] of Object.entries(table.computed)) Object.defineProperty(vm,key,{get:()=>getter.call(vm)})
 const active=()=>Object.entries(vm.activeSections).filter(([,value])=>value).map(([key])=>key)
 assert.deepEqual(active(),[])
 for(const [patch,expected] of [
  [{type:'income'},['type']], [{timeRange:'custom'},['timeRange']],
  [{categoryId:0},['categoryId']], [{narrowCategories:'0,12'},['categoryId']],
  [{paymentPartnerId:0},['paymentPartnerId']], [{narrowPartners:'21,22'},['paymentPartnerId']],
  [{projectId:9},['projectId']], [{narrowProjects:'9,10'},['projectId']],
  [{hashtagId:5},['hashtagId']], [{narrowHashtags:'5,6'},['hashtagId']],
  [{tags:'future'},['tags']], [{narrowLabels:'important,review'},['tags']],
  [{hasReminder:'true'},['hasReminder']], [{hasAttachment:'false'},['hasAttachment']],
  [{status:'settled'},['status']], [{status:'all'},[]],
 ]) {
  vm.localFilters={...base,...patch}
  assert.deepEqual(active(),expected,`Payment markers: ${JSON.stringify(patch)}`)
 }
 vm.localFilters={...base}
 assert.deepEqual(active(),[],'Cleared payment filters remove all section dots')
 const filters=bindings.AnnualOverviewFilters
 const reportActive=selection=>{
  const normalized=annual.annualSelection(selection)
  return Object.entries(filters.computed.activeSections.call({normalized, areaIds: []})).filter(([,value])=>value).map(([key])=>key)
 }
 for(const [selection,expected] of [
  [{},[]], [{grouping:'paymentPartners',categories:'0'},['categories']],
  [{partners:'0,21'},['partners']], [{labels:'important,review'},['labels']],
  [{hashtags:'5,6'},['hashtags']], [{amountMin:'0'},['amount']], [{amountMax:'10.50'},['amount']],
  [{amountMin:'invalid'},[]], [{grouping:'categories',categories:'12'},[]],
 ]) assert.deepEqual(reportActive(selection),expected,`Report markers: ${JSON.stringify(selection)}`)
 assert.deepEqual(reportActive({}),[],'Cleared report predicates remove all section dots')
 console.log('Filter section markers passed: neutral/default states, zero IDs, multiple selections, Planned, receipts, amounts, hidden report groups and reset.')
}

// Reset the filter menu atomically while preserving the report's explicit context.
{
 const original={...annual.annualSelection({timeRange:'custom',dateFrom:'2024-02-02',dateTo:'2024-02-29',projectId:12,entryScope:'shared',grouping:'paymentPartners',search:'Rent',categories:'0,7',labels:'important',hashtags:'5',amountMin:'0',amountMax:'100'}),reportFilters:'[{"dimension":"categories","id":7}]',withoutArea:true}
 let updates=0
 const ctx={selection:original,updateSelection(){updates++}}
 view.methods.resetFilters.call(ctx)
 assert.equal(updates,1,'Reset schedules one report update')
 for(const key of ['categories','partners','labels','hashtags']) assert.deepEqual(ctx.selection[key],[])
 assert.equal(ctx.selection.search,'')
 assert.equal(ctx.selection.amountMin,null);assert.equal(ctx.selection.amountMax,null)
 assert.equal(ctx.selection.reportFilters,undefined);assert.equal(ctx.selection.withoutArea,false)
 for(const key of ['projectId','entryScope','timeRange','dateFrom','dateTo','year','period','grouping','includeFuturePayments']) assert.deepEqual(ctx.selection[key],original[key],`${key}: report context survives reset`)
 assert.equal(original.search,'Rent','Reset does not mutate the previous selection')
 assert.deepEqual(original.categories,[0,7])
 const query=annual.annualRouteQuery(ctx.selection)
 for(const key of ['search','categories','partners','labels','hashtags','amountMin','amountMax','reportFilters','withoutArea']) assert.equal(query[key],undefined,`${key}: bookmark drops the cleared predicate`)
 const events=[]
 let focused=false
 const menu={...bindings.AnnualOverviewFilters.data.call({selection:original}),
  $refs:{search:{$el:{querySelector:()=>({focus:()=>{focused=true}})}}},$nextTick:fn=>fn(),scheduleFit(){},$emit:(...args)=>events.push(args)}
 menu.searchText='Pending search';menu.amountMinText='12,'
 menu.searchTimer=setTimeout(()=>events.push(['stale-search']),10)
 bindings.AnnualOverviewFilters.methods.resetFilters.call(menu)
 await new Promise(resolve=>setTimeout(resolve,30))
 assert.deepEqual(events,[['reset']],'Cancelled debounces cannot reapply stale filters after reset')
 for(const key of ['searchText','amountMinText','amountMaxText','hashtagQuery']) assert.equal(menu[key],'')
 assert.equal(focused,true,'Reset returns keyboard focus to the menu search field')
 console.log('Report filter reset passed: atomic clearing, preserved area/period/grouping, clean bookmarks, cancelled drafts and focus restoration.')
}

{
 const stamp = day => new Date(`${day}T00:00:00`).getTime() / 1000
 const periods = [{ dateFrom: stamp('2026-01-01'), dateTo: stamp('2026-02-01') - 1 }, { dateFrom: stamp('2026-03-01'), dateTo: stamp('2026-04-01') - 1 }]
 const value = { currentFrom: '2026-01-01', currentTo: '2026-03-15', previousFrom: '2025-01-01', previousTo: '2025-03-15', currentPeriods: periods, periods: periods.map(period => ({ dateFrom: stamp(new Date(period.dateFrom * 1000).getMonth() === 0 ? '2025-01-01' : '2025-03-01'), dateTo: stamp(new Date(period.dateFrom * 1000).getMonth() === 0 ? '2025-02-01' : '2025-04-01') - 1 })) }
 const format = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
 assert.equal(comparisonHelpers.reportComparisonRange(value, format, 'current'), '2026-01-01 – 2026-01-31; 2026-03-01 – 2026-03-15')
 assert.equal(comparisonHelpers.reportComparisonRange(value, format), '2025-01-01 – 2025-01-31; 2025-03-01 – 2025-03-15', 'Reference labels show the selected intervals and cutoff without gaps')
}

// Payments CSV export helpers: lazy Nextcloud save after folder selection,
// CSV-to-TSV clipboard conversion, and clipboard source error preservation.
{
 const downloads = await import(moduleUrl(source('src/services/downloads.js')))
 assert.equal(downloads.ENTRY_CLIPBOARD_EXPORT_LIMIT, 1000, 'Payments clipboard export limit is capped at 1,000 entries')
 assert.equal(downloads.filenameFromDisposition('attachment; filename="cobudget-zahlungen-20261106-180000.csv"', 'fallback.csv'), 'cobudget-zahlungen-20261106-180000.csv')
 const tsv = downloads.csvToClipboardText('\uFEFFID;Beschreibung;Betrag\r\n1;"Miete; ""Nov""";12.34\r\n2;"Zeile 1\nZeile 2\tTab";56.78\r\n')
 assert.equal(tsv, 'ID\tBeschreibung\tBetrag\r\n1\t"Miete; ""Nov"""\t12.34\r\n2\t"Zeile 1\nZeile 2\tTab"\t56.78\r\n', 'Clipboard conversion strips BOM, converts semicolons to tabs and preserves embedded newlines/tabs/quotes')
 let lazyCalls = 0
 const beforeFiles = savedFiles.length
 await bindings.saveReportExport('fallback.csv', async () => {
  lazyCalls++
  return { filename: 'cobudget-zahlungen-20261106-180000.csv', contents: '\uFEFFID;Betrag\r\n1;10.00\r\n' }
 }, exportTexts)
 assert.equal(lazyCalls, 1, 'Choosing a Nextcloud folder invokes the lazy CSV loader once')
 assert.equal(savedFiles.length, beforeFiles + 1)
 assert.ok(savedFiles.at(-1).url.includes('cobudget-zahlungen-20261106-180000.csv'), 'Nextcloud save uses the server-provided filename when returned by the lazy loader')
 assert.equal(savedFiles.at(-1).body, '\uFEFFID;Betrag\r\n1;10.00\r\n')
}

// Payments print view: print-optimized document generation, month/year subtotals,
// non-date sorting, and off-screen iframe printing across all filtered pages.
{
 const formatDateUrl = moduleUrl("export const formatCalendarLabel = (date, options) => date.toLocaleDateString('de-DE', options)")
 const formatMoneyUrl = moduleUrl([
  "export const getAppLocale = () => 'de-DE'",
  "export const formatMoney = (amount, currency = 'EUR') => `${Number(amount).toFixed(2)} ${currency}`",
  "export const formatSignedMoney = (amount, currency = 'EUR') => `${Number(amount) >= 0 ? '+' : ''}${Number(amount).toFixed(2)} ${currency}`",
 ].join('\n'))
 const paymentPrint = await import(moduleUrl(
  source('src/services/paymentPrint.js')
   .replace("'../utils/formatDate'", JSON.stringify(formatDateUrl))
   .replace("'../utils/formatMoney'", JSON.stringify(formatMoneyUrl)),
 ))

 const entries = [
  {
   id: 101,
   date: Math.floor(new Date('2026-11-05T12:00:00Z').getTime() / 1000),
   type: 'income',
   amount: 2500,
   amount_cents: 250000,
   personal_amount_cents: 250000,
   description: 'Gehalt November',
   category_name: 'Gehalt',
   paymentPartner: 'Arbeitgeber GmbH',
  },
  {
   id: 102,
   date: Math.floor(new Date('2026-11-02T12:00:00Z').getTime() / 1000),
   type: 'expense',
   amount: 1200,
   amount_cents: 120000,
   personal_amount_cents: 60000,
   description: 'Warmmiete',
   category_name: 'Wohnen',
   paymentPartner: 'Hausverwaltung',
   project_id: 7,
   is_fixed_cost: true,
   is_subscription: true,
   recurrence_interval: 'months',
   is_settled: true,
  },
  {
   id: 103,
   date: Math.floor(new Date('2025-12-20T12:00:00Z').getTime() / 1000),
   type: 'expense',
   amount: 80,
   amount_cents: 8000,
   personal_amount_cents: 8000,
   description: 'Geschenke',
   category_name: 'Familie',
   paymentPartner: 'Kaufhaus',
   is_child_related: true,
   is_important: true,
  },
 ]

 const html = paymentPrint.createPaymentsPrintDocument({
  title: 'Zahlungen',
  periodLabel: '2025–2026',
  filterLabels: ['Kategorie: Wohnen, Familie', 'Suche: Miete'],
  entries,
  currency: 'EUR',
  enableIncomes: true,
  enableProjects: true,
  typeFilter: 'all',
  sortBy: 'date',
  projects: [{ id: 7, name: 'Haushalt' }],
  texts: exportTexts,
  locale: 'de-DE',
 })

 assert.ok(html.includes('@page { size: A4 landscape; margin: 10mm; }'), 'Payments print view uses A4 landscape with compact margins')
 assert.ok(html.includes('body { margin: 0; color: #222; background: #fff; font: 9pt/1.4 Arial, sans-serif; }'), 'Payments print view matches the Auswertungen body typography and colors')
 assert.ok(html.includes('th, td { padding: 0.9mm 0.5mm; border-bottom: 0.2mm solid #ddd;'), 'Payments print view matches the Auswertungen cell padding and horizontal border design')
 assert.ok(html.includes('thead th { background: #eee; font-size: 7pt; }'), 'Payments print view matches the Auswertungen table header background and font size')
 assert.ok(html.includes('thead { display: table-header-group;'), 'Payments print view repeats table headers on each page')
 assert.ok(html.includes('tfoot { display: table-row-group;'), 'Payments print view keeps the grand total footer at the end of the table')
 assert.ok(html.includes('<h1>CoBudget – Zahlungen – 2025–2026</h1>'), 'Payments print view renders the full report heading')
 assert.ok(html.includes('Kategorie: Wohnen, Familie · Suche: Miete'), 'Payments print view includes active filter chips in the metadata header')
 assert.ok(html.includes('+2500.00 EUR') && html.includes('-680.00 EUR') && html.includes('+1820.00 EUR'), 'Payments print view summarizes income, personal expenses and balance across all printed entries')
 assert.ok(html.includes('Haushalt') && html.includes('1200.00 EUR'), 'Payments print view shows the area and full shared amount when personal share differs')
 assert.ok(html.includes('fixedCosts · subscription · recurrenceActive · settled'), 'Payments print view includes enabled payment labels and status in the description metadata')
 assert.ok(html.includes('class="group-section-row"') && html.includes('class="subtotal-row month-subtotal-row"') && html.includes('class="subtotal-row annual-total"'), 'Chronological payments print view groups by month and year with subtotals')
 assert.ok(html.includes('class="annual-total grand-total-row"'), 'Payments print view renders a closing grand total row')

 const sortedByAmountHtml = paymentPrint.createPaymentsPrintDocument({
  title: 'Zahlungen',
  periodLabel: '2026',
  entries,
  currency: 'EUR',
  enableIncomes: true,
  enableProjects: false,
  typeFilter: 'all',
  sortBy: 'amount',
  texts: exportTexts,
  locale: 'de-DE',
 })
 assert.ok(!sortedByAmountHtml.includes('class="group-section-row"'), 'Non-date sorting prints entries in the chosen sort order without date group headers')
 assert.ok(!sortedByAmountHtml.includes('class="col-area"'), 'Payments print view omits the area column when areas are disabled')

 const settlementPrintHtml = paymentPrint.createPaymentsPrintDocument({
  title: 'Abrechnung vom 07.11.2026, 21:24',
  periodLabel: 'Urlaub Wien',
  settlement: {
   id: 10,
   balances: [
    { userId: 'test', displayName: 'Testperson', paid: 0, fairShare: 25, shareBasisPoints: 5000, balance: -25 },
    { userId: 'admin', displayName: 'admin', paid: 50, fairShare: 25, shareBasisPoints: 5000, balance: 25 },
   ],
   transfers: [
    { fromUserId: 'test', fromDisplayName: 'Testperson', toUserId: 'admin', toDisplayName: 'admin', amount: 25, amountCents: 2500 },
   ],
  },
  metadataRows: [
   ['Bereich', 'Urlaub Wien'],
   ['Erstellt von', 'admin'],
   ['Anzahl der Zahlungen', '1'],
   ['Gesamtsumme', '50.00 EUR'],
  ],
  entries: [{ ...entries[1], amount: 50, amount_cents: 5000, description: '   ', paid_by_display_name: 'admin' }],
  currency: 'EUR',
  enableIncomes: true,
  enableProjects: false,
  showSummaryCards: false,
  groupByDate: false,
  showPaidBy: true,
  payerNameResolver: entry => entry?.paid_by_display_name || '',
  amountResolver: entry => entry?.amount,
  texts: exportTexts,
  locale: 'de-DE',
 })
 assert.ok(settlementPrintHtml.includes('<h1>Abrechnung vom 07.11.2026, 21:24</h1>'), 'Settlement print view renders the settlement heading')
 assert.ok(settlementPrintHtml.includes('<dt>Erstellt von</dt>') && settlementPrintHtml.includes('<dd>admin</dd>') && settlementPrintHtml.includes('<dt>Gesamtsumme</dt>') && settlementPrintHtml.includes('<dd>50.00 EUR</dd>'), 'Settlement print view renders settlement summary metadata')
 assert.ok(settlementPrintHtml.includes('class="report-settlement-balances report-compact-table"') && settlementPrintHtml.includes('Testperson') && settlementPrintHtml.includes('25.00 EUR (50%)') && settlementPrintHtml.includes('-25.00 EUR') && settlementPrintHtml.includes('+25.00 EUR'), 'Settlement print view renders member balances before settlement')
 assert.ok(settlementPrintHtml.includes('class="report-settlement-repayments report-compact-table"') && settlementPrintHtml.includes('class="col-from">Testperson</td>') && settlementPrintHtml.includes('class="col-to">admin</td>'), 'Settlement print view renders suggested repayments')
 assert.ok(settlementPrintHtml.includes('class="col-paid-by">admin</td>') && settlementPrintHtml.includes('-50.00 EUR'), 'Settlement print view renders the payment amount and payer column')
 assert.ok(!settlementPrintHtml.includes('class="group-section-row"') && !settlementPrintHtml.includes('class="report-summary"'), 'Settlement print view omits date group headers and dashboard summary cards')
 assert.ok(!settlementPrintHtml.includes('withoutDescription') && !settlementPrintHtml.includes('Keine Beschreibung') && !settlementPrintHtml.includes('Ohne Beschreibung') && !settlementPrintHtml.includes('payment-description-main'), 'Entries without a description stay empty in the print view instead of rendering a placeholder')

 let printedHtml = ''
 let printTriggered = 0
 let frameRemoved = false
 const fakePrintWindow = {
  requestAnimationFrame: cb => cb(),
  addEventListener: (event, cb) => {
   if (event === 'afterprint') fakePrintWindow.afterPrint = cb
  },
  removeEventListener: () => {},
  focus: () => {},
  print: () => {
   printTriggered++
   fakePrintWindow.afterPrint?.()
  },
  setTimeout: cb => cb(),
 }
 const fakePrintDocument = {
  fonts: { ready: Promise.resolve() },
  documentElement: { scrollHeight: 1400 },
  body: { scrollHeight: 1400 },
  open: () => { printedHtml = '' },
  write: chunk => { printedHtml += chunk },
  close: () => {},
 }
 const fakeFrame = {
  style: {},
  setAttribute: () => {},
  remove: () => { frameRemoved = true },
  contentWindow: fakePrintWindow,
  contentDocument: fakePrintDocument,
 }
 const fakeDoc = {
  defaultView: fakePrintWindow,
  body: { appendChild: () => {} },
  getElementById: () => null,
  createElement: tag => {
   assert.equal(tag, 'iframe')
   return fakeFrame
  },
 }
 await paymentPrint.printPaymentsOverview(fakeDoc, {
  title: 'Zahlungen',
  periodLabel: '2026',
  entries,
  currency: 'EUR',
  texts: exportTexts,
  locale: 'de-DE',
 })
 assert.equal(printTriggered, 1, 'printPaymentsOverview triggers the browser print dialog after layout readiness')
 assert.equal(frameRemoved, true, 'printPaymentsOverview cleans up its off-screen iframe on afterprint')
 assert.ok(printedHtml.includes('Gehalt November') && printedHtml.includes('Warmmiete') && printedHtml.includes('Geschenke'), 'printPaymentsOverview writes all entries into the print document')

 const savedGet = bindings.axios.get
 try {
  bindings.axios.get = async (_url, { params }) => {
   assert.equal(params.dateFrom, 1767222000)
   assert.equal(params.dateTo, null)
   return { data: { total: 4, entries: [{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }] } }
  }
  const openEndedRows = await bindings.loadReportPayments({ dateFrom: 1767222000, dateTo: null })
  assert.equal(openEndedRows.length, 4, 'Open-ended payment periods (dateTo: null) load all matching entries instead of treating null as 0')
 } finally {
  bindings.axios.get = savedGet
 }
}
