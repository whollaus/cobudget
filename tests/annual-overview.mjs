import assert from 'node:assert/strict'
import fs from 'node:fs'
import * as Vue from 'vue'
import { parse } from '@vue/compiler-sfc'
import { compile } from '@vue/compiler-dom'
import { renderToString } from '@vue/server-renderer'
import { createRouter, createMemoryHistory } from 'vue-router'

const source = path => fs.readFileSync(new URL(`../${path}`, import.meta.url), 'utf8')
const moduleUrl = text => `data:text/javascript;charset=utf-8,${encodeURIComponent(text)}`
const annual = await import(moduleUrl(source('src/services/annualOverview.js')))
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
assert.equal(annual.annualSelection({ year: currentYear, period: 'month' }).month, new Date().getMonth() + 1, 'The current year opens on the current month')
assert.equal(annual.annualSelection({ year: currentYear, period: 'month', month: 1, includeFuturePayments: true }).includeFuturePayments, new Date().getMonth() === 0, 'A past month shows the whole month')
assert.equal(annual.annualSelection({ year: currentYear, period: 'month', month: new Date().getMonth() + 1, includeFuturePayments: true }).includeFuturePayments, true)
if (new Date().getMonth() < 11) assert.equal(annual.annualSelection({ year: currentYear, period: 'month', month: new Date().getMonth() + 2 }).includeFuturePayments, true, 'A later month includes entered payments')
assert.deepEqual(annual.annualRouteQuery({ year: 2020, period: 'month', month: 2, grouping: 'categories' }), { year: '2020', grouping: 'categories', period: 'month', month: '2' })
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
assert.equal(annual.annualSelection({ year: currentYear, period: 'day' }).month, 0, 'All keeps every calendar day')
assert.equal(annual.annualSelection({ year: currentYear, period: 'day', month: 3 }).month, 3, 'A chosen month stays on the daily overview')
assert.equal(annual.annualSelection({ year: currentYear, period: 'day', month: 13 }).month, 0, 'An invalid daily month falls back to All')
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
assert.deepEqual(annual.annualRouteQuery({ year: 2020, period: 'day', day: 12, grouping: 'categories' }), { year: '2020', grouping: 'categories', period: 'day' })
assert.deepEqual(annual.annualRouteQuery({ year: 2020, period: 'day', month: 3, grouping: 'categories' }), { year: '2020', grouping: 'categories', period: 'day', month: '3' })
assert.equal(annual.annualRequestParams({ year: 2020, period: 'day', day: 3 }).period, 'day')
assert.equal(annual.annualRequestParams({ year: 2020, period: 'day', day: 3 }).day, undefined, 'Day links omit the day number')
assert.equal(annual.annualRequestParams({ year: 2020, period: 'day', month: 3 }).month, 3, 'A chosen month is requested with the daily overview')
assert.equal(annual.annualRequestParams({ year: 2020, period: 'day' }).month, undefined, 'All omits the month')
assert.equal(annual.annualRouteQuery({ year: 2020, day: 2 }).period, undefined, 'Year links omit the day')

for (const projectId of [null, 12]) {
	for (const grouping of ['categories', 'paymentPartners']) {
		for (const includeFuturePayments of [false, true]) {
			const selection = { year: currentYear, projectId, grouping, includeFuturePayments }
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
	const selection = { year: currentYear, projectId, grouping: 'labelsAndTags', includeFuturePayments: false }
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
const previousAnnualWindow = globalThis.window
const annualSectionValues = new Map()
const annualSectionStorage = {
	getItem: key => annualSectionValues.has(key) ? annualSectionValues.get(key) : null,
	setItem: (key, value) => annualSectionValues.set(key, String(value)),
}
globalThis.window = { localStorage: annualSectionStorage, OC: { getCurrentUser: () => ({ uid: 'ada' }) } }
assert.equal(annual.readAnnualSectionOpen('income'), true, 'Income, expenses and the total start open')
assert.equal(annual.readAnnualSectionOpen('expense'), true)
assert.equal(annual.readAnnualSectionOpen('total'), true)
annual.rememberAnnualSection('expense', false)
assert.equal(annual.readAnnualSectionOpen('expense'), false, 'A closed section stays closed for this user')
assert.equal(annual.readAnnualSectionOpen('income'), true, 'Closing expenses leaves income open')
annual.rememberAnnualSection('total', false)
assert.equal(annual.readAnnualSectionOpen('total'), false, 'The overall total can stay closed')
assert.equal(annual.readAnnualSectionOpen('income'), true, 'Closing the total leaves income open')
globalThis.window = { localStorage: annualSectionStorage, OC: { getCurrentUser: () => ({ uid: 'ben' }) } }
assert.equal(annual.readAnnualSectionOpen('expense'), true, 'Another user keeps the default open sections')
globalThis.window = { localStorage: { getItem: () => '{', setItem() {} }, OC: { getCurrentUser: () => ({ uid: 'ada' }) } }
assert.equal(annual.readAnnualSectionOpen('income'), true, 'Unreadable storage stays open')
if (previousAnnualWindow === undefined) delete globalThis.window
else globalThis.window = previousAnnualWindow
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
const bindings = {
	...annual,
	downloadTextFile: (filename, contents) => { csvDownloads.push({ filename, contents }) },
	showToast: message => { exportToasts.push(message) },
	showRequestError: (_error, fallback) => { exportErrors.push(fallback) },
	getCurrentUser: () => nextcloudUser.uid ? { uid: nextcloudUser.uid } : null,
	generateRemoteUrl: service => `https://cloud.example.test/remote.php/${service}`,
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
	NcActions: slotComponent('div'),
	NcActionButton: slotComponent('button'),
	CloudUploadOutlineIcon: slotComponent('span'),
	DownloadIcon: slotComponent('span'),
	NcButton: slotComponent('button'),
	TableTooltip: slotComponent('span'), TrendingUpIcon: slotComponent('span'), TrendingDownIcon: slotComponent('span'),
	NcModal: slotComponent('section'), annualCellDetails, annualDetailComparison, getAppLocale: () => 'de-AT',
	ChevronLeftIcon: slotComponent('span'), ChevronRightIcon: slotComponent('span'), ChevronDownIcon: slotComponent('span'),
	PrinterIcon: slotComponent('span'), printAnnualOverview: (...args) => { printCalls.push(args) },
	AppPageHeader: slotComponent('header', 'title'),
	MobileReportSwitch: slotComponent('nav'),
	axios: {}, generateUrl: value => value, REMOTE_DATA_CHANGED_EVENT: 'change',
	formatCalendarLabel: (date, options) => new Intl.DateTimeFormat('de-AT', options).format(date),
	getAppLanguage: () => 'de-AT',
}
function component(file) {
	const { descriptor } = parse(source(file))
	const script = descriptor.script.content.replace(/^import .*$/gm, '').replace('export default', 'return')
	const options = new Function(...Object.keys(bindings), script)(...Object.values(bindings))
	options.render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue)
	return options
}
const hierarchy = await import(moduleUrl(source('src/utils/categoryHierarchy.js')))
Object.assign(bindings, hierarchy, {
	NcPopover: { setup: (_, { slots }) => () => Vue.h('div', [slots.trigger?.(), slots.default?.()]) },
	CloseIcon: slotComponent('span'),
	FilterVariantIcon: slotComponent('span'),
})
bindings.AnnualComparison = component('src/components/AnnualComparison.vue')
bindings.AnnualOverviewTable = component('src/components/AnnualOverviewTable.vue')
bindings.AnnualOverviewDetails = component('src/components/AnnualOverviewDetails.vue')
bindings.AnnualOverviewFilters = component('src/components/AnnualOverviewFilters.vue')
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
let blurCount = 0
const yearState = { selection: { year: currentYear - 1, includeFuturePayments: true }, yearOptions: [currentYear + 1, currentYear], $router: { replace: () => Promise.resolve() }, updateSelection: view.methods.updateSelection }
const yearEvent = value => ({ target: { value: String(value), blur() { blurCount++ } } })
view.methods.selectYear.call(yearState, yearEvent(currentYear))
assert.equal(yearState.selection.includeFuturePayments, false, 'Changing to the current year defaults to payments up to today')
assert.equal(blurCount, 0, 'Changing year preserves keyboard focus')
view.methods.selectYear.call(yearState, yearEvent(currentYear + 1))
assert.equal(yearState.selection.includeFuturePayments, true, 'Switching to a future year includes its entered payments')
view.methods.selectYear.call(yearState, yearEvent(1969))
assert.equal(yearState.selection.year, currentYear + 1, 'Years without entries cannot replace the selection')
view.methods.updateSelection.call(yearState, yearEvent('categories'))
assert.equal(blurCount, 1, 'Only invalid year selection releases focus; ordinary changes remain keyboard accessible')
const periodState = {
	selection: { year: 2019, period: 'year', month: 0, grouping: 'categories', includeFuturePayments: false },
	$enableProjects: true,
	$router: { replace(route) { periodState.route = route; return Promise.resolve() } },
	updateSelection: view.methods.updateSelection,
}
await view.methods.selectPeriod.call(periodState, { target: { value: 'month' } })
assert.equal(periodState.selection.year, 2019, 'Switching to the monthly overview keeps the selected year')
assert.equal(periodState.selection.period, 'month')
assert.equal(periodState.route.query.year, '2019')
assert.equal(periodState.route.query.period, 'month')
await view.methods.selectMonth.call(periodState, { target: { value: 'all', blur() {} } })
assert.equal(periodState.selection.year, 2019, 'All returns to the yearly overview of the selected year')
assert.equal(periodState.selection.period, 'year')
assert.equal(periodState.route.query.year, '2019')
assert.equal(periodState.route.query.period, undefined)
assert.equal(periodState.route.query.month, undefined)
periodState.selection = { year: 2019, period: 'year', month: 0, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectPeriod.call(periodState, { target: { value: 'quarter' } })
assert.equal(periodState.selection.year, 2019, 'Switching to the quarterly overview keeps the selected year')
assert.equal(periodState.selection.period, 'quarter')
assert.equal(periodState.selection.quarter, 0)
assert.equal(periodState.route.query.year, '2019')
assert.equal(periodState.route.query.period, 'quarter')
assert.equal(periodState.route.query.quarter, undefined)
assert.equal(periodState.route.query.month, undefined)
periodState.selection = { year: 2019, period: 'month', month: 10, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectPeriod.call(periodState, { target: { value: 'quarter' } })
assert.equal(periodState.selection.year, 2019, 'Switching from a month keeps the selected year')
assert.equal(periodState.selection.quarter, 0)
assert.equal(periodState.route.query.month, undefined)
periodState.selection = { year: 2019, period: 'quarter', month: 0, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectPeriod.call(periodState, { target: { value: 'month' } })
assert.equal(periodState.selection.year, 2019)
assert.equal(periodState.selection.month, 1, 'A past year opens on January')
assert.equal(periodState.route.query.quarter, undefined)
periodState.selection = { year: currentYear, period: 'quarter', month: 0, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectPeriod.call(periodState, { target: { value: 'month' } })
assert.equal(periodState.selection.month, new Date().getMonth() + 1, 'The current year opens on the current month')
periodState.selection = { year: 2019, period: 'quarter', month: 11, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectPeriod.call(periodState, { target: { value: 'month' } })
assert.equal(periodState.selection.month, 11, 'A remembered month stays selected')
periodState.selection = { year: 2019, period: 'year', month: 0, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectPeriod.call(periodState, { target: { value: 'week' } })
assert.equal(periodState.selection.year, 2019, 'Switching to the weekly overview keeps the selected year')
assert.equal(periodState.selection.period, 'week')
assert.equal(periodState.selection.month, 0)
assert.equal(periodState.route.query.year, '2019')
assert.equal(periodState.route.query.period, 'week')
assert.equal(periodState.route.query.week, undefined)
assert.equal(periodState.route.query.month, undefined)
periodState.selection = { year: 2019, period: 'week', month: 11, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectPeriod.call(periodState, { target: { value: 'month' } })
assert.equal(periodState.selection.month, 11, 'A remembered month stays selected after the weekly overview')
const quarterYearState = {
	selection: { year: 2019, period: 'quarter', month: 0, quarter: 0, grouping: 'categories', includeFuturePayments: false },
	yearOptions: [2019, 2020],
	$router: { replace(route) { quarterYearState.route = route; return Promise.resolve() } },
	updateSelection: view.methods.updateSelection,
}
view.methods.selectYear.call(quarterYearState, { target: { value: '2020', blur() {} } })
assert.equal(quarterYearState.selection.year, 2020, 'Changing the year keeps the quarterly overview')
assert.equal(quarterYearState.selection.period, 'quarter')
assert.equal(quarterYearState.route.query.period, 'quarter')
assert.equal(quarterYearState.route.query.quarter, undefined)
const weekYearState = {
	selection: { year: 2019, period: 'week', month: 0, quarter: 0, grouping: 'categories', includeFuturePayments: false },
	yearOptions: [2019, 2020],
	$router: { replace(route) { weekYearState.route = route; return Promise.resolve() } },
	updateSelection: view.methods.updateSelection,
}
view.methods.selectYear.call(weekYearState, { target: { value: '2020', blur() {} } })
assert.equal(weekYearState.selection.year, 2020, 'Changing the year keeps the weekly overview')
assert.equal(weekYearState.selection.period, 'week')
assert.equal(weekYearState.route.query.period, 'week')
assert.equal(weekYearState.route.query.week, undefined)
periodState.selection = { year: 2019, period: 'year', month: 0, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectPeriod.call(periodState, { target: { value: 'day' } })
assert.equal(periodState.selection.year, 2019, 'Switching to the daily overview keeps the selected year')
assert.equal(periodState.selection.period, 'day')
assert.equal(periodState.selection.month, 0)
assert.equal(periodState.route.query.year, '2019')
assert.equal(periodState.route.query.period, 'day')
assert.equal(periodState.route.query.day, undefined)
assert.equal(periodState.route.query.month, undefined)
periodState.selection = { year: 2019, period: 'day', month: 3, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectMonth.call(periodState, { target: { value: 'all', blur() {} } })
assert.equal(periodState.selection.period, 'day', 'All stays on the daily overview')
assert.equal(periodState.selection.month, 0)
assert.equal(periodState.route.query.period, 'day')
assert.equal(periodState.route.query.month, undefined)
periodState.selection = { year: 2019, period: 'day', month: 0, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectMonth.call(periodState, { target: { value: '3', blur() {} } })
assert.equal(periodState.selection.period, 'day', 'Choosing a month stays on the daily overview')
assert.equal(periodState.selection.month, 3)
assert.equal(periodState.selection.year, 2019)
assert.equal(periodState.route.query.period, 'day')
assert.equal(periodState.route.query.month, '3')
periodState.selection = { year: 2019, period: 'day', month: 11, quarter: 0, grouping: 'categories', includeFuturePayments: false }
await view.methods.selectPeriod.call(periodState, { target: { value: 'month' } })
assert.equal(periodState.selection.month, 11, 'A remembered month stays selected after the daily overview')
const dayYearState = {
	selection: { year: 2019, period: 'day', month: 0, quarter: 0, grouping: 'categories', includeFuturePayments: false },
	yearOptions: [2019, 2020],
	$router: { replace(route) { dayYearState.route = route; return Promise.resolve() } },
	updateSelection: view.methods.updateSelection,
}
view.methods.selectYear.call(dayYearState, { target: { value: '2020', blur() {} } })
assert.equal(dayYearState.selection.year, 2020, 'Changing the year keeps the daily overview')
assert.equal(dayYearState.selection.period, 'day')
assert.equal(dayYearState.route.query.period, 'day')
assert.equal(dayYearState.route.query.day, undefined)
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
	scopeLabel: 'Haushalt & Familie', $refs: { printSource: { fixture: true } },
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
printState.selection = { ...selection, year: currentYear - 1, period: 'day' }
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
	sortedExportRows: view.methods.sortedExportRows,
	exportAmountRow: view.methods.exportAmountRow,
	annualExportFilename: view.methods.annualExportFilename,
	annualExportSections: view.methods.annualExportSections,
}
view.methods.exportReport.call(exportState)
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
function createReportApp(selection, reportData = report, { incomes = true, projects = true } = {}) {
	const app = Vue.createSSRApp({ ...view, data: () => ({ selection, projects: [], categoryChoices: [{ id: 1, name: 'Groceries', type: 'expense' }, { id: 2, name: 'Rent', parent_category_id: 1, type: 'expense' }, { id: 3, name: 'Salary', type: 'income' }, { id: 9, name: 'Archive', type: 'expense' }], partnerChoices: [{ id: 4, name: 'Amazon', type: 'expense' }, { id: 6, name: 'Client', type: 'income' }, { id: 8, name: 'OldShop', type: 'expense' }], hashtagChoices: [{ id: 9, displayName: 'Alpen' }], filterChoices: { categoryIds: [1, 2, 3, 0], partnerIds: [4, 6, 0], labels: ['important', 'childRelated'] }, loading: false, error: false, detail: null, report: reportData, printing: false, requestId: 0, filterChoiceRequest: 0 }) })
	app.component('RouterLink', { props: ['to'], setup: (props, { slots }) => () => Vue.h('a', { href: JSON.stringify(props.to) }, slots.default?.()) })
	app.config.globalProperties.$texts = new Proxy({}, { get: () => new Proxy({}, { get: (_, key) => (...args) => args.length ? args.join(', ') : key }) })
	app.config.globalProperties.$route = { query: {} }
	app.config.globalProperties.$enableProjects = projects
	app.config.globalProperties.$enableIncomes = incomes
	for (const flag of ['$enableImportantPayments', '$enableReviewPayments', '$enableFixedCosts', '$enableChildRelated', '$enableSubscriptions', '$enableTaxRelevant', '$enableInsurance']) app.config.globalProperties[flag] = true
	app.config.globalProperties.$formatMoneyFromCents = cents => (cents / 100).toFixed(2)
	return app
}
const markup = await renderToString(createReportApp(selection))
for (const incomes of [false, true]) {
	for (const projects of [false, true]) {
		const settingsMarkup = await renderToString(createReportApp(selection, report, { incomes, projects }))
		assert.equal((settingsMarkup.match(/<table /g) || []).length, incomes ? 3 : 1, 'Disabled incomes leave only the expense table and its total')
		assert.equal((settingsMarkup.match(/annual-section-toggle/g) || []).length, incomes ? 3 : 1, 'Income, expenses and the total collapse on their own')
		assert.equal((settingsMarkup.match(/annual-filter-kind/g) || []).length, incomes ? 2 : 0, 'Income and expense marks disappear when income is disabled')
		assert.equal((settingsMarkup.match(/<select /g) || []).length, projects ? 5 : 4, 'Disabled areas remove the scope dropdown')
		const links = [...settingsMarkup.matchAll(/href="([^"]+)"/g)].map(match => JSON.parse(match[1].replaceAll('&quot;', '"').replaceAll('&amp;', '&')))
		assert.ok(links.length > 0)
		if (!incomes) assert.ok(links.every(link => link.query.type === 'expense'), 'No hidden income or balance drilldowns remain')
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
	selection: { projectId: 12 }, filterChoiceRequest: 0,
	categoryChoices: [{ id: 1, name: 'Groceries', type: 'expense' }],
	partnerChoices: [{ id: 4, name: 'Amazon', type: 'expense' }],
	hashtagChoices: [{ id: 9, displayName: 'Alpen' }],
	filterChoices: { categoryIds: [1], partnerIds: [4], labels: ['important'] },
	loadFilterChoices: view.methods.loadFilterChoices,
}
await view.watch['selection.projectId'].call(catalogState)
assert.deepEqual(catalogState.categoryChoices, [{ id: 30, name: 'Area bonus', type: 'income' }], 'An area loads its own categories')
assert.deepEqual(catalogState.partnerChoices, [{ id: 40, name: 'Area client', type: 'income' }], 'An area loads its own payment partners')
assert.deepEqual(catalogState.filterChoices.categoryIds, [], 'The previous scope does not keep its filter choices')
assert.equal(catalogRequests.filter(request => request.url.includes('categories')).every(request => request.projectId === 12), true, 'Area catalogs are requested with the area id')
catalogState.selection = { projectId: null }
await view.watch['selection.projectId'].call(catalogState)
assert.deepEqual(catalogState.categoryChoices, [{ id: 1, name: 'Groceries', type: 'expense' }], 'Meine Finanzen loads the personal categories again')
assert.equal(catalogRequests.at(-2).projectId, null, 'Personal catalogs are requested without an area id')
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
assert.ok(markup.includes('value="labelsAndTags"'), 'Grouping offers labels and tags')
assert.ok(markup.includes('filterPayments'), 'The filter control stays at the end of the toolbar')
assert.equal(markup.includes('annual-filter-heading">category'), false, 'Category grouping does not offer another category filter')
assert.equal(markup.includes('annual-filter-heading">paymentPartner'), true, 'Payment partners remain available while grouping by category')
assert.equal(markup.includes('annual-filter-heading">labels'), true, 'Labels remain available while grouping by category')
const labelFilterMarkup = await renderToString(createReportApp({ ...selection, grouping: 'labelsAndTags', hashtags: [9] }, emptyReport))
assert.equal(labelFilterMarkup.includes('annual-filter-heading">labels'), false, 'Label grouping does not offer another label filter')
assert.equal(labelFilterMarkup.includes('annual-filter-heading">category'), true, 'Categories remain available while grouping by labels and tags')
assert.equal(labelFilterMarkup.includes('annual-filter-heading">tags'), true, 'Description tags remain available while grouping by labels and tags')
assert.ok(labelFilterMarkup.includes('Groceries'), 'Categories used in the year stay selectable')
assert.equal(labelFilterMarkup.includes('Archive'), false, 'Categories without a payment in the year are hidden')
const categoryFilter = labelFilterMarkup.slice(labelFilterMarkup.indexOf('annual-filter-heading">category'), labelFilterMarkup.indexOf('annual-filter-heading">paymentPartner'))
const categoryOrder = ['noCategory', 'Groceries', 'Rent', 'Salary'].map(name => categoryFilter.indexOf(name))
assert.ok(categoryOrder.every((position, index) => position > (categoryOrder[index - 1] ?? -1)), 'Unassigned stays first, then expenses and their children, then income')
assert.ok(categoryFilter.indexOf('noCategory') < categoryFilter.indexOf('annual-filter-kind'), 'Unassigned categories have no income or expense mark')
assert.ok(categoryFilter.indexOf('is-expense') < categoryFilter.indexOf('is-income'), 'Expense categories are marked before income categories')
assert.ok(categoryFilter.includes('−'), 'Expense categories show a minus mark')
assert.ok(categoryFilter.includes('>+<') || categoryFilter.includes('>+</span>'), 'Income categories show a plus mark')
assert.equal((labelFilterMarkup.match(/annual-filter-kind/g) || []).length, 5, 'Each used category and payment partner carries one mark')
const partnerFilter = markup.slice(markup.indexOf('annual-filter-heading">paymentPartner'), markup.indexOf('annual-filter-heading">amount'))
const partnerOrder = ['noPartner', 'Amazon', 'Client'].map(name => partnerFilter.indexOf(name))
assert.ok(partnerOrder.every((position, index) => position > (partnerOrder[index - 1] ?? -1)), 'Unassigned payment partners stay first, then expenses, then income')
assert.ok(markup.includes('Amazon'), 'Payment partners used in the year stay selectable')
assert.equal(markup.includes('OldShop'), false, 'Payment partners without a payment in the year are hidden')
assert.ok(markup.includes('>important</span>'), 'Labels used in the year stay selectable')
assert.equal(markup.includes('>subscription</span>'), false, 'Enabled labels without a payment in the year are hidden')
assert.ok(labelFilterMarkup.includes('>Alpen</span>') || labelFilterMarkup.includes('>#Alpen</span>'), 'Tags used in the year stay selectable')
const chipMarkup = await renderToString(createReportApp(annual.annualSelection({ year: currentYear, grouping: 'categories', partners: '2,5', labels: 'important,childRelated' })))
assert.ok(chipMarkup.includes('2 or 5'), 'Several values of one filter are shown together')
assert.ok(chipMarkup.includes('important or children'))
assert.equal(chipMarkup.includes('anyOfThese'), false, 'A multi-value filter does not add an extra hint')
assert.ok(chipMarkup.includes('annual-filter-remove'))
assert.equal((markup.match(/<select /g) || []).length, 5, 'Current year keeps the title, year, scope, grouping and cutoff dropdowns')
assert.ok(markup.includes('value="year"') && markup.includes('value="quarter"') && markup.includes('value="week"') && markup.includes('value="month"') && markup.includes('value="day"'), 'The title switches between the year, quarter, week, month and day overview')
assert.ok(markup.indexOf('value="year"') < markup.indexOf('value="quarter"') && markup.indexOf('value="quarter"') < markup.indexOf('value="week"') && markup.indexOf('value="week"') < markup.indexOf('value="month"') && markup.indexOf('value="month"') < markup.indexOf('value="day"'), 'The title lists the year, then the quarter, then the week, then the month, then the day')
assert.equal(markup.includes('value="2"'), false, 'The year overview has no month dropdown')
assert.equal(markup.includes('value="all"'), false, 'The year overview has no All choice')
assert.ok(markup.includes('paymentsUntilToday'), 'Payment cutoff is visible in the toolbar')
assert.ok(!markup.includes('annual-hint'), 'Explanatory paragraphs are removed')
assert.ok(!markup.includes('annual-year-buttons'), 'Year navigation arrows are removed')
assert.ok(!markup.includes('value="other"'), 'Arbitrary year selection is removed')
assert.ok(!markup.includes(`value="${currentYear - 1}"`), 'Empty years are not offered')
assert.equal(((await renderToString(createReportApp({ ...selection, year: currentYear - 1 }))).match(/<select /g) || []).length, 4, 'Past years hide the cutoff dropdown')
assert.equal(((await renderToString(createReportApp({ ...selection, year: currentYear + 1 }))).match(/<select /g) || []).length, 4, 'Future years hide the cutoff dropdown')
const viewedMonth = new Date().getMonth() + 1
const monthMarkup = await renderToString(createReportApp({ ...selection, period: 'month', month: viewedMonth }))
assert.equal((monthMarkup.match(/<select /g) || []).length, 6, 'The current month adds the month dropdown beside the year and keeps the cutoff')
assert.ok(monthMarkup.includes('monthlyTitle') && monthMarkup.includes('>Februar<'), 'February is offered with the other months of the selected year')
assert.ok(monthMarkup.includes('value="all"') && monthMarkup.indexOf('value="all"') < monthMarkup.indexOf('>Februar<'), 'All is the first month choice and opens the year overview')
assert.ok(monthMarkup.includes(new Intl.DateTimeFormat('de-AT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(currentYear, viewedMonth - 1, 1))), 'Day headings expose the weekday and full date')
const monthDayHeading = (day) => new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(currentYear, viewedMonth - 1, day)).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
const viewedMonthLength = new Date(currentYear, viewedMonth, 0).getDate()
assert.equal((monthMarkup.match(new RegExp(`<th\\b[^>]*>\\s*${monthDayHeading(1)}`, 'g')) || []).length, 3, 'Income, expenses and the total list the short date of day 1')
assert.equal((monthMarkup.match(new RegExp(`<th\\b[^>]*>\\s*${monthDayHeading(viewedMonthLength)}`, 'g')) || []).length, 3, 'Income, expenses and the total list the short date of the last day')
const pastMonthMarkup = await renderToString(createReportApp({ ...selection, period: 'month', month: 1 }))
assert.equal((pastMonthMarkup.match(/<select /g) || []).length, new Date().getMonth() === 0 ? 6 : 5, 'A past month hides payments up to today')
if (new Date().getMonth() < 11) {
	const laterMonthMarkup = await renderToString(createReportApp({ ...selection, period: 'month', month: new Date().getMonth() + 2 }))
	assert.equal((laterMonthMarkup.match(/<select /g) || []).length, 5, 'A future month hides the cutoff dropdown')
}
const quarterMarkup = await renderToString(createReportApp({ ...selection, period: 'quarter' }))
assert.equal((quarterMarkup.match(/<select /g) || []).length, 5, 'The current year keeps the cutoff and adds no quarter dropdown')
assert.ok(quarterMarkup.includes('quarterlyTitle'), 'The title shows the quarterly overview')
assert.equal(quarterMarkup.includes('aria-label="quarterLabel"'), false, 'The quarterly overview has no quarter dropdown')
assert.equal(quarterMarkup.includes('aria-label="month"'), false, 'The month dropdown stays hidden in the quarterly overview')
assert.equal(quarterMarkup.includes('value="all"'), false, 'The quarterly overview has no All choice')
assert.equal((quarterMarkup.match(/<th\b[^>]*>\s*1\D/g) || []).length, 3, 'Income, expenses and the total list the first quarter')
assert.equal(quarterMarkup.includes('Jänner'), false, 'The quarterly overview lists quarters')
const pastQuarterMarkup = await renderToString(createReportApp({ ...selection, year: currentYear - 1, period: 'quarter' }))
assert.equal((pastQuarterMarkup.match(/<select /g) || []).length, 4, 'A past year hides payments up to today')
const futureQuarterMarkup = await renderToString(createReportApp({ ...selection, year: currentYear + 1, period: 'quarter' }))
assert.equal((futureQuarterMarkup.match(/<select /g) || []).length, 4, 'A future year hides the cutoff dropdown')
const weekCountForYear = annual.isoWeekCount(currentYear)
const weekMarkup = await renderToString(createReportApp({ ...selection, period: 'week' }))
assert.equal((weekMarkup.match(/<select /g) || []).length, 5, 'The current week-year keeps the cutoff and adds no week dropdown')
assert.ok(weekMarkup.includes('weeklyTitle'), 'The title shows the weekly overview')
assert.equal(weekMarkup.includes('aria-label="month"'), false, 'The month dropdown stays hidden in the weekly overview')
assert.equal(weekMarkup.includes('value="all"'), false, 'The weekly overview has no All choice')
assert.equal((weekMarkup.match(new RegExp(`<th\\b[^>]*>\\s*${weekCountForYear}\\D`, 'g')) || []).length, 3, 'Income, expenses and the total list the last calendar week')
assert.equal(weekMarkup.includes('Jänner'), false, 'The weekly overview lists calendar weeks')
const pastWeekMarkup = await renderToString(createReportApp({ ...selection, year: currentYear - 1, period: 'week' }))
assert.equal((pastWeekMarkup.match(/<select /g) || []).length, 4, 'A past week-year hides payments up to today')
const futureWeekMarkup = await renderToString(createReportApp({ ...selection, year: currentYear + 1, period: 'week' }))
assert.equal((futureWeekMarkup.match(/<select /g) || []).length, 4, 'A future week-year hides the cutoff dropdown')
const escapePattern = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
const dayHeading = (year, month, day) => new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(new Date(year, month, day))
const dayMarkup = await renderToString(createReportApp({ ...selection, period: 'day' }))
assert.equal((dayMarkup.match(/<select /g) || []).length, 6, 'The current daily overview adds the month dropdown and keeps the cutoff')
assert.ok(dayMarkup.includes('dailyTitle'), 'The title shows the daily overview')
assert.ok(dayMarkup.includes('aria-label="month"'), 'The daily overview shows the month dropdown beside the year')
assert.ok(dayMarkup.includes('value="all"') && dayMarkup.indexOf('value="all"') < dayMarkup.indexOf('>Februar<'), 'All is the first month choice and keeps every calendar day')
assert.equal((dayMarkup.match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 0, 1))}`, 'g')) || []).length, 3, 'Income, expenses and the total list 1 January')
assert.equal((dayMarkup.match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 11, 31))}`, 'g')) || []).length, 3, 'Income, expenses and the total list 31 December')
assert.ok(dayMarkup.includes(new Intl.DateTimeFormat('de-AT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(currentYear, 0, 1))), 'Day headings expose the weekday and full date')
const currentDayMonthMarkup = await renderToString(createReportApp({ ...selection, period: 'day', month: viewedMonth }))
assert.equal((currentDayMonthMarkup.match(/<select /g) || []).length, 6, 'The current month of the daily overview keeps the cutoff')
assert.equal((currentDayMonthMarkup.match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, viewedMonth - 1, 1))}`, 'g')) || []).length, 3, 'Income, expenses and the total list the first day of the selected month')
if (viewedMonth !== 12) assert.equal((currentDayMonthMarkup.match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 11, 31))}`, 'g')) || []).length, 0, 'A selected month hides the other days of the year')
const februaryDayMarkup = await renderToString(createReportApp({ ...selection, period: 'day', month: 2 }))
assert.equal((februaryDayMarkup.match(/<select /g) || []).length, new Date().getMonth() === 1 ? 6 : 5, 'A past month of the daily overview hides payments up to today')
assert.equal((februaryDayMarkup.match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 1, 1))}`, 'g')) || []).length, 3, 'February lists its first day')
assert.equal((februaryDayMarkup.match(new RegExp(`<th\\b[^>]*>\\s*${escapePattern(dayHeading(currentYear, 11, 31))}`, 'g')) || []).length, 0, 'February hides 31 December')
if (new Date().getMonth() < 11) {
	const laterDayMarkup = await renderToString(createReportApp({ ...selection, period: 'day', month: new Date().getMonth() + 2 }))
	assert.equal((laterDayMarkup.match(/<select /g) || []).length, 5, 'A later month of the daily overview hides the cutoff dropdown')
}
const pastDayMarkup = await renderToString(createReportApp({ ...selection, year: currentYear - 1, period: 'day' }))
assert.equal((pastDayMarkup.match(/<select /g) || []).length, 5, 'A past year hides payments up to today')
const futureDayMarkup = await renderToString(createReportApp({ ...selection, year: currentYear + 1, period: 'day' }))
assert.equal((futureDayMarkup.match(/<select /g) || []).length, 5, 'A future year hides the cutoff dropdown')
assert.equal((markup.match(/<table /g) || []).length, 3, 'Income, expenses and summary all render')
const sectionToggles = markup => [...markup.matchAll(/<button[^>]*annual-section-toggle[^>]*>/g)].map(match => match[0])
assert.equal(sectionToggles(markup).filter(button => button.includes('aria-expanded="true"')).length, 3, 'Income, expenses and the total start open')
const collapsedWindow = globalThis.window
const collapsedValues = new Map()
globalThis.window = {
	localStorage: {
		getItem: key => collapsedValues.has(key) ? collapsedValues.get(key) : null,
		setItem: (key, value) => collapsedValues.set(key, String(value)),
	},
	OC: { getCurrentUser: () => ({ uid: 'ada' }) },
}
annual.rememberAnnualSection('income', false)
const collapsedMarkup = await renderToString(createReportApp(selection))
const collapsedToggles = sectionToggles(collapsedMarkup)
assert.equal((collapsedMarkup.match(/is-collapsed/g) || []).length, 1, 'A remembered closed section hides its whole table')
assert.equal(collapsedToggles.filter(button => button.includes('aria-expanded="false"')).length, 1)
assert.equal(collapsedToggles.filter(button => button.includes('aria-expanded="true"')).length, 2, 'The other sections stay open')
assert.equal((collapsedMarkup.match(/<table /g) || []).length, 3, 'The total section remains available while a payment section is closed')
if (collapsedWindow === undefined) delete globalThis.window
else globalThis.window = collapsedWindow
assert.equal((markup.match(/aria-sort="ascending"/g) || []).length, 2, 'Income and expense headers indicate default ascending title order')
assert.equal((markup.match(/class="sortable(?: annual-year-total)?"/g) || []).length, 4, 'Only title and yearly total headers in income and expenses are sortable')
assert.equal((markup.match(/<a /g) || []).length, 6, 'Row labels still link directly to payments')
assert.equal((markup.match(/class="annual-amount"/g) || []).length, 78, 'Every monthly and yearly amount opens details')
const labelLinks = [...markup.matchAll(/<th scope="row">(.*?)<\/th>/g)].map(match => JSON.parse(match[1].match(/href="([^"]+)"/)[1].replaceAll('&quot;', '"').replaceAll('&amp;', '&')))
assert.equal(labelLinks.length, 6)
for (const link of labelLinks) {
	assert.equal(link.query.timeRange, 'all', 'First-column links have no date restriction')
	assert.equal(link.query.dateFrom, undefined)
	assert.equal(link.query.dateTo, undefined)
}
assert.equal(labelLinks.find(link => link.query.categoryId === '1')?.query.includeSubcategories, 'true', 'Parent category label includes its children')
const footers = [...markup.matchAll(/<tfoot>(.*?)<\/tfoot>/g)].map(match => match[1])
assert.equal(footers.length, 2, 'Income and expenses each have a total row, even with no payments')
for (const [index, footer] of footers.entries()) {
	const links = [...footer.matchAll(/href="([^"]+)"/g)].map(match => JSON.parse(match[1].replaceAll('&quot;', '"').replaceAll('&amp;', '&')))
	assert.equal(links.length, 1, 'Each total row retains its undated label link')
	assert.equal((footer.match(/class="annual-amount"/g) || []).length, 13, 'Footer amounts open details')
	for (const link of links) {
		assert.equal(link.query.type, index === 0 ? 'income' : 'expense')
		assert.equal(link.query.categoryId, undefined, 'Totals include every category')
		assert.equal(link.query.paymentPartnerId, undefined, 'Totals include every payment partner')
	}
	assert.ok(footer.includes(index === 0 ? '0.00' : '3.03'), 'Footer uses the report total')
}
assert.ok(markup.includes('includeSubcategories'), 'Parent links include child payments')
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
const dayYearSelection = { ...statsSelection, period: 'day' }
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
assert.equal((comparisonMarkup.match(/<table /g) || []).length, 2, 'No additional table or selection UI is needed')
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
