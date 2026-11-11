const LABEL_KEYS = ['important', 'review', 'fixedCost', 'childRelated', 'subscription', 'taxRelevant', 'insurance']
const FILTER_LIMIT = 50

function annualChartAccountKey() {
	let userId = ''
	try {
		if (typeof window !== 'undefined' && typeof window.OC?.getCurrentUser === 'function') {
			const user = window.OC.getCurrentUser()
			userId = typeof user === 'string' ? user : (user?.uid || user?.id || '')
		}
	} catch {
		userId = ''
	}
	return userId ? `cobudget_annual_sections:${userId}` : 'cobudget_annual_sections'
}

// Keep tab choices through report reloads, but never across visits to Reports.
const annualChartViews = new Map()

export function resetAnnualChartViews() {
	annualChartViews.clear()
}

export function readAnnualChartView(section = 'chart') {
	return annualChartViews.get(annualChartAccountKey())?.[section] || 'development'
}

export function rememberAnnualChartView(section, view) {
	if (!['chart', 'detail-chart'].includes(section)) return
	const key = annualChartAccountKey()
	const views = annualChartViews.get(key) || {}
	views[section] = view === 'forecast' ? 'forecast' : 'development'
	annualChartViews.set(key, views)
}

function queryText(value) {
	if (Array.isArray(value)) return value.map(item => String(item ?? '')).join(',')
	if (value === null || value === undefined) return ''
	return String(value)
}

function firstText(value) {
	const raw = Array.isArray(value) ? value[0] : value
	if (raw === null || raw === undefined) return ''
	return String(raw).trim()
}

function parseIdList(value, allowZero) {
	const ids = []
	const seen = new Set()
	for (const part of queryText(value).split(',')) {
		const token = part.trim()
		if (!/^\d+$/.test(token)) continue
		const id = Number(token)
		if (!Number.isSafeInteger(id) || id < 0 || (id === 0 && !allowZero) || seen.has(id)) continue
		seen.add(id)
		ids.push(id)
		if (ids.length >= FILTER_LIMIT) break
	}
	return ids
}

function parseLookupList(value) {
	const tokens = queryText(value).split(',').map(token => token.trim())
	return [...parseIdList(value, true), ...['income', 'expense'].filter(kind => tokens.includes(kind))]
}

function parseLabelList(value) {
	const keys = []
	const seen = new Set()
	for (const part of queryText(value).split(',')) {
		const token = part.trim()
		if (!LABEL_KEYS.includes(token) || seen.has(token)) continue
		seen.add(token)
		keys.push(token)
		if (keys.length >= FILTER_LIMIT) break
	}
	return keys
}

export function parseAnnualAmountInput(value) {
	let text = firstText(value).replace(/\s/g, '')
	if (text === '') return null
	text = text.replace(/[^\d.,]/g, '')
	if (!/\d/.test(text)) return null
	const lastComma = text.lastIndexOf(',')
	const lastDot = text.lastIndexOf('.')
	if (lastComma !== -1 && lastDot !== -1) {
		const decimal = lastComma > lastDot ? ',' : '.'
		const group = decimal === ',' ? '.' : ','
		text = text.replaceAll(group, '').replace(decimal, '.')
	} else if (lastComma !== -1 || lastDot !== -1) {
		const separator = lastComma !== -1 ? ',' : '.'
		const fraction = text.slice(text.lastIndexOf(separator) + 1)
		if (fraction.length === 3 && text.indexOf(separator) === text.lastIndexOf(separator)) {
			text = text.replaceAll(separator, '')
		} else if (fraction.length <= 2) {
			text = text.replace(separator, '.')
		} else {
			return null
		}
	}
	if (!/^\d+(?:\.\d{1,2})?$/.test(text) || text.split('.')[0].length > 12) return null
	const [whole, fraction = ''] = text.split('.')
	return Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
}

export function formatAnnualAmountParam(cents) {
	if (cents === null || cents === undefined) return ''
	const whole = Math.trunc(Number(cents) / 100)
	const fraction = Math.abs(Number(cents) % 100)
	return fraction === 0 ? String(whole) : `${whole}.${String(fraction).padStart(2, '0')}`
}

export function annualFilterSections(grouping) {
	return {
		categories: grouping !== 'categories',
		partners: grouping !== 'paymentPartners',
		labels: grouping !== 'labelsAndTags',
		hashtags: true,
		amount: true,
	}
}

// Cap the open filter menu to the space left on the chosen side of the trigger.
// An empty result leaves the menu uncapped until the popover placement is known.
export function annualFilterPanelMaxHeight({ triggerTop, triggerBottom, viewportHeight, placement, margin = 12, gap = 12 } = {}) {
	const side = String(placement || '')
	const above = side.startsWith('top')
	const below = side.startsWith('bottom')
	if (!above && !below) return ''
	const available = above
		? Number(triggerTop) - gap - margin
		: Number(viewportHeight) - Number(triggerBottom) - gap - margin
	const height = Math.floor(Math.min(available, Number(viewportHeight) - margin * 2))
	if (!Number.isFinite(height) || height < 1) return ''
	return `${height}px`
}

function storedAmountCents(value) {
	// Route text is a euro amount. The selection already stores whole cents.
	if (typeof value === 'number' && Number.isSafeInteger(value) && value >= 0) return value
	return parseAnnualAmountInput(value)
}

function calendarToday(now = new Date()) {
	return { year: now.getFullYear(), month: now.getMonth() + 1 }
}

export function reportDate(date) {
	return `${String(date.getFullYear()).padStart(4, '0')}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function parseReportDate(value) {
	if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value))) return null
	const [year, month, day] = String(value).split('-').map(Number)
	if (year < 1970 || year > 9999) return null
	const date = new Date(year, month - 1, day)
	return reportDate(date) === value ? date : null
}

const REPORT_RANGES = ['all', 'year', 'current-year', 'last-year', 'current-month', 'last-month', 'last-12-months', 'custom', 'calendar-selection']

function calendarSelection(query) {
	const years = parseIdList(query.periodYears, false).filter(year => year >= 1970 && year <= 9999).sort((a, b) => a - b)
	const months = parseIdList(query.periodMonths, false).filter(month => month <= 12).sort((a, b) => a - b)
	return { periodYears: years.length ? years : [Number(query.year) || new Date().getFullYear()], periodMonths: months.length === 12 ? [] : months }
}

// Merge adjacent selected months, preserving gaps between years/months.
export function reportCalendarRanges(selection) {
	if (selection.timeRange !== 'calendar-selection') return []
	const { periodYears, periodMonths } = calendarSelection(selection)
	const ranges = []
	for (const year of periodYears) for (const month of periodMonths.length ? periodMonths : Array.from({ length: 12 }, (_, index) => index + 1)) {
		const from = new Date(year, month - 1, 1), next = new Date(year, month, 1)
		const last = ranges.at(-1)
		if (last && last.next === reportDate(from)) { last.dateTo = reportDate(new Date(year, month, 0)); last.next = reportDate(next) }
		else ranges.push({ dateFrom: reportDate(from), dateTo: reportDate(new Date(year, month, 0)), next: reportDate(next) })
	}
	return ranges.map(({ dateFrom, dateTo }) => ({ dateFrom, dateTo }))
}

export function reportRange(query, now = new Date()) {
	const year = Number(query.year) || now.getFullYear()
	let from, to
	switch (query.timeRange) {
		case 'calendar-selection': {
			const ranges = reportCalendarRanges(query)
			return { dateFrom: ranges[0].dateFrom, dateTo: ranges.at(-1).dateTo }
		}
		case 'current-month': from = new Date(now.getFullYear(), now.getMonth(), 1); to = new Date(now.getFullYear(), now.getMonth() + 1, 0); break
		case 'last-month': from = new Date(now.getFullYear(), now.getMonth() - 1, 1); to = new Date(now.getFullYear(), now.getMonth(), 0); break
		case 'last-12-months': from = new Date(now.getFullYear(), now.getMonth() - 11, 1); to = new Date(now.getFullYear(), now.getMonth() + 1, 0); break
		case 'all': return { dateFrom: firstText(query.dateFrom) || `${year}-01-01`, dateTo: firstText(query.dateTo) || `${year}-12-31` }
		case 'custom': return { dateFrom: firstText(query.dateFrom), dateTo: firstText(query.dateTo) }
		default: {
			const selected = query.timeRange === 'last-year' ? now.getFullYear() - 1 : query.timeRange === 'current-year' ? now.getFullYear() : year
			from = new Date(selected, 0, 1); to = new Date(selected, 11, 31)
		}
	}
	return { dateFrom: reportDate(from), dateTo: reportDate(to) }
}

export function reportRangeError(selection) {
	if (selection.timeRange === 'calendar-selection') {
		let columns = 0
		for (const range of reportCalendarRanges(selection)) {
			columns += reportRangeColumns({ ...selection, ...range })
		}
		return columns > 3660 ? 'columns' : ''
	}
	return reportRangeColumns(selection) > 3660 ? 'columns' : reportRangeColumns(selection) < 1 ? 'dates' : ''
}

function reportRangeColumns(selection) {
	const from = parseReportDate(selection.dateFrom), to = parseReportDate(selection.dateTo)
	if (!from || !to || from > to) return 0
	const days = Math.round((Date.UTC(to.getFullYear(), to.getMonth(), to.getDate()) - Date.UTC(from.getFullYear(), from.getMonth(), from.getDate())) / 86400000) + 1
	const months = (to.getFullYear() - from.getFullYear()) * 12 + to.getMonth() - from.getMonth() + 1
	const columns = selection.period === 'day' ? days : selection.period === 'week' ? Math.ceil((days + (from.getDay() + 6) % 7) / 7) : selection.period === 'quarter' ? Math.floor((to.getFullYear() * 12 + to.getMonth()) / 3) - Math.floor((from.getFullYear() * 12 + from.getMonth()) / 3) + 1 : months
	return columns
}

export function reportContainsToday(selection, now = new Date()) {
	if (!selection.timeRange) return annualPeriodContainsToday(selection.year, selection.period, selection.month, now)
	const today = reportDate(now)
	return selection.dateFrom <= today && today <= selection.dateTo
}

// Old bookmarks retain their exact dates; new visits use independent range and breakdown controls.
export function annualReportSelection(query = {}) {
	if (REPORT_RANGES.includes(query.timeRange)) return annualSelection(query)
	const legacy = annualSelection(query)
	if (legacy.period === 'day') {
		const from = new Date(legacy.year, legacy.month >= 1 ? legacy.month - 1 : 0, 1)
		const to = legacy.month >= 1 ? new Date(legacy.year, legacy.month, 0) : new Date(legacy.year, 11, 31)
		return annualSelection({ ...query, timeRange: 'custom', period: 'day', dateFrom: reportDate(from), dateTo: reportDate(to) })
	}
	if (legacy.period === 'week') {
		const from = isoWeekStart(legacy.year, 1), next = isoWeekStart(legacy.year + 1, 1)
		return annualSelection({ ...query, timeRange: 'custom', dateFrom: reportDate(from), dateTo: reportDate(new Date(next.getFullYear(), next.getMonth(), next.getDate() - 1)) })
	}
	return annualSelection({ ...query, timeRange: query.year ? 'year' : 'current-year' })
}

export function reportSelectionWithDates(selection, dates) {
	const changed = dates.dateFrom !== selection.dateFrom || dates.dateTo !== selection.dateTo
	return annualSelection({ ...selection, ...dates, period: changed ? undefined : selection.period })
}

// The heading describes the applied dates, including custom full months or years.
export function reportPeriodLabel(selection, formatMonth, formatDate) {
	if (selection.timeRange === 'calendar-selection') {
		const { periodYears, periodMonths } = calendarSelection(selection)
		const months = periodMonths.map(month => formatMonth(new Date(2000, month - 1, 1), { month: 'short' })).join(', ')
		return [months, periodYears.join(', ')].filter(Boolean).join(' · ')
	}
	const { dateFrom, dateTo } = selection.timeRange ? selection : annualReportSelection(selection)
	const from = parseReportDate(dateFrom), to = parseReportDate(dateTo)
	if (!from || !to || from > to) return ''
	const sameYear = from.getFullYear() === to.getFullYear()
	if (sameYear && from.getMonth() === 0 && from.getDate() === 1 && to.getMonth() === 11 && to.getDate() === 31) return String(from.getFullYear())
	const monthEnd = new Date(to.getFullYear(), to.getMonth() + 1, 0).getDate()
	if (sameYear && from.getMonth() === to.getMonth() && from.getDate() === 1 && to.getDate() === monthEnd) return formatMonth(from, { month: 'long', year: 'numeric' })
	return `${formatDate(from)} – ${formatDate(to)}`
}

function isoWeekParts(date) {
	const utc = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
	const day = utc.getUTCDay() || 7
	utc.setUTCDate(utc.getUTCDate() + 4 - day)
	const weekYear = utc.getUTCFullYear()
	const yearStart = new Date(Date.UTC(weekYear, 0, 1))
	const week = Math.ceil((((utc - yearStart) / 86400000) + 1) / 7)
	return { weekYear, week }
}

export function isoWeekYear(date) {
	return isoWeekParts(date).weekYear
}

export function isoWeekNumber(date) {
	return isoWeekParts(date).week
}

export function isoWeekStart(year, week) {
	const fourth = new Date(Number(year), 0, 4)
	const day = fourth.getDay() || 7
	const monday = new Date(Number(year), 0, 4 - day + 1 + (Number(week) - 1) * 7)
	monday.setHours(0, 0, 0, 0)
	return monday
}

export function isoWeekCount(year) {
	return isoWeekYear(isoWeekStart(year, 53)) === Number(year) ? 53 : 52
}

export function calendarDayCount(year) {
	return new Date(Number(year), 1, 29).getMonth() === 1 ? 366 : 365
}

function limitsToCalendarMonth(period, subperiod) {
	const month = Number(subperiod)
	return period === 'month' || (period === 'day' && Number.isInteger(month) && month >= 1 && month <= 12)
}

export function annualPeriodContainsToday(year, period, subperiod, now = new Date()) {
	if (period === 'week') return isoWeekYear(now) === Number(year)
	const today = calendarToday(now)
	if (year !== today.year) return false
	if (limitsToCalendarMonth(period, subperiod)) return Number(subperiod) === today.month
	return true
}

export function annualPeriodIsFuture(year, period, subperiod, now = new Date()) {
	if (period === 'week') return isoWeekYear(now) < Number(year)
	const today = calendarToday(now)
	if (year > today.year) return true
	if (year < today.year) return false
	if (limitsToCalendarMonth(period, subperiod)) return Number(subperiod) > today.month
	return false
}

export function annualSelection(query = {}) {
	const year = Number(query.year)
	const currentYear = new Date().getFullYear()
	const selectedYear = Number.isInteger(year) && year >= 1970 && year <= 9999 ? year : currentYear
	const projectId = Number(query.projectId)
	const grouping = ['paymentPartners', 'labelsAndTags'].includes(query.grouping) ? query.grouping : 'categories'
	// Old month links represent the same daily report with a selected month.
	const period = query.period === 'month' || query.period === 'day' ? 'day' : query.period === 'quarter' ? 'quarter' : query.period === 'week' ? 'week' : 'year'
	const requestedMonth = Number(query.month)
	const validMonth = Number.isInteger(requestedMonth) && requestedMonth >= 1 && requestedMonth <= 12
	const allDays = query.month === 'all' || query.month === 0 || query.month === '0'
	const month = period === 'day' ? (validMonth ? requestedMonth : allDays ? 0 : 1) : 0
	const quarter = 0
	let amountMin = storedAmountCents(query.amountMin)
	let amountMax = storedAmountCents(query.amountMax)
	if (amountMin !== null && amountMax !== null && amountMin > amountMax) {
		const swapped = amountMin
		amountMin = amountMax
		amountMax = swapped
	}
	const sections = annualFilterSections(grouping)
	const containsToday = annualPeriodContainsToday(selectedYear, period, month)
	const range = REPORT_RANGES.includes(query.timeRange) ? reportRange({ ...query, year: selectedYear }) : null
	let rangePeriod = ['year', 'quarter', 'week', 'day'].includes(query.period) ? query.period : null
	if (range && !rangePeriod) {
		const from = parseReportDate(range.dateFrom), to = parseReportDate(range.dateTo)
		const days = from && to ? Math.round((to - from) / 86400000) + 1 : 1
		rangePeriod = query.timeRange === 'current-month' || query.timeRange === 'last-month' || (query.timeRange === 'custom' && days <= 45) ? 'day' : query.timeRange === 'custom' && days <= 120 ? 'week' : 'year'
	}
	return {
		year: selectedYear,
		...(firstText(query.search) ? { search: firstText(query.search) } : {}),
		period,
		month,
		quarter,
		grouping,
		...(parseIdList(query.projects, true).length ? { projects: parseIdList(query.projects, true) } : {}),
		projectId: Number.isSafeInteger(projectId) && projectId > 0 ? projectId : null,
		...(query.withoutArea === true || query.withoutArea === 'true' ? { withoutArea: true } : {}),
		entryScope: Number.isSafeInteger(projectId) && projectId > 0 && query.entryScope === 'shared' ? 'shared' : 'personal',
		includeFuturePayments: annualPeriodIsFuture(selectedYear, period, month) || (containsToday && (query.includeFuturePayments === true || query.includeFuturePayments === 'true')),
		categories: sections.categories ? parseLookupList(query.categories) : [],
		partners: sections.partners ? parseLookupList(query.partners) : [],
		labels: sections.labels ? parseLabelList(query.labels) : [],
		hashtags: parseIdList(query.hashtags, false),
		amountMin,
		amountMax,
		...(typeof query.reportFilters === 'string' && query.reportFilters ? { reportFilters: query.reportFilters } : {}),
		...(range ? {
			timeRange: query.timeRange,
			...(query.timeRange === 'calendar-selection' ? calendarSelection({ ...query, year: selectedYear }) : {}),
			...range,
			period: rangePeriod,
			month: 0,
			includeFuturePayments: range.dateFrom > reportDate(new Date()) || (reportContainsToday({ ...range, timeRange: query.timeRange }) && (query.includeFuturePayments === true || query.includeFuturePayments === 'true')),
		} : {}),
	}
}

function filterQuery(value) {
	return {
		...(value.timeRange === 'calendar-selection' ? { periodYears: value.periodYears.join(','), ...(value.periodMonths.length ? { periodMonths: value.periodMonths.join(',') } : {}) } : {}),
		...(value.search ? { search: value.search } : {}),
		...(value.reportFilters ? { reportFilters: value.reportFilters } : {}),
		...(value.withoutArea ? { withoutArea: 'true' } : {}),
		...(value.projects?.length ? { projects: value.projects.join(',') } : {}),
		...(value.categories.length ? { categories: value.categories.join(',') } : {}),
		...(value.partners.length ? { partners: value.partners.join(',') } : {}),
		...(value.labels.length ? { labels: value.labels.join(',') } : {}),
		...(value.hashtags.length ? { hashtags: value.hashtags.join(',') } : {}),
		...(value.amountMin !== null ? { amountMin: formatAnnualAmountParam(value.amountMin) } : {}),
		...(value.amountMax !== null ? { amountMax: formatAnnualAmountParam(value.amountMax) } : {}),
	}
}

export function annualRouteQuery(selection) {
	const value = annualSelection(selection)
	if (value.timeRange) return {
		year: String(value.year), timeRange: value.timeRange, grouping: value.grouping,
		period: value.period,
		...(value.timeRange === 'custom' ? { dateFrom: value.dateFrom, dateTo: value.dateTo } : {}),
		...(value.projectId ? { projectId: String(value.projectId) } : {}),
		...(value.entryScope === 'shared' ? { entryScope: 'shared' } : {}),
		...(value.includeFuturePayments ? { includeFuturePayments: 'true' } : {}),
		...filterQuery(value),
	}
	return {
		year: String(value.year),
		grouping: value.grouping,
		...(value.period === 'quarter' ? { period: 'quarter' } : {}),
		...(value.period === 'week' ? { period: 'week' } : {}),
		...(value.period === 'day' ? { period: 'day', month: value.month >= 1 ? String(value.month) : 'all' } : {}),
		...(value.projectId ? { projectId: String(value.projectId) } : {}),
		...(value.entryScope === 'shared' ? { entryScope: 'shared' } : {}),
		...(value.includeFuturePayments ? { includeFuturePayments: 'true' } : {}),
		...filterQuery(value),
	}
}

export function annualRequestParams(selection, extra = {}) {
	const value = annualSelection({ ...selection, ...extra })
	if (value.timeRange) return {
		year: value.year, period: value.period, dateFrom: value.dateFrom, dateTo: value.dateTo,
		...(value.timeRange === 'all' ? { allTime: true } : {}),
		grouping: value.grouping, projectId: value.projectId, entryScope: value.entryScope, includeFuturePayments: value.includeFuturePayments,
		...(extra.comparison && extra.comparison !== 'none' ? { comparison: extra.comparison } : {}),
		...filterQuery(value),
	}
	return {
		year: value.year,
		grouping: value.grouping,
		...(value.period === 'quarter' ? { period: 'quarter' } : {}),
		...(value.period === 'week' ? { period: 'week' } : {}),
		...(value.period === 'day' ? { period: 'day', ...(value.month >= 1 ? { month: value.month } : {}) } : {}),
		projectId: value.projectId,
		entryScope: value.entryScope,
		includeFuturePayments: value.includeFuturePayments,
		...(extra.comparison && extra.comparison !== 'none' ? { comparison: extra.comparison } : {}),
		...filterQuery(value),
	}
}

export function annualReturnRoute(value) {
	try {
		const query = JSON.parse(value)
		if (!query || typeof query !== 'object' || !query.year) return null
		return { name: 'annual-overview', query: annualRouteQuery(query) }
	} catch {
		return null
	}
}

function appendNarrowing(query, value) {
	if (value.search) query.search = value.search
	if (value.projects?.length) query.projects = value.projects.join(',')
	else if (value.withoutArea) query.projects = '0'
	if (value.categories.length === 1 && typeof value.categories[0] === 'number' && query.categoryId === undefined) query.categoryId = String(value.categories[0])
	else if (value.categories.length > 1 || (value.categories.length === 1 && String(query.categoryId) !== String(value.categories[0]))) query.categories = value.categories.join(',')
	if (value.partners.length === 1 && typeof value.partners[0] === 'number' && query.paymentPartnerId === undefined) query.paymentPartnerId = String(value.partners[0])
	else if (value.partners.length > 1 || (value.partners.length === 1 && String(query.paymentPartnerId) !== String(value.partners[0]))) query.partners = value.partners.join(',')
	if (value.labels.length === 1 && query.tags === undefined) query.tags = value.labels[0]
	else if (value.labels.length > 1 || (value.labels.length === 1 && query.tags !== value.labels[0])) query.labels = value.labels.join(',')
	if (value.hashtags.length === 1 && query.hashtagId === undefined) query.hashtagId = String(value.hashtags[0])
	else if (value.hashtags.length > 1 || (value.hashtags.length === 1 && String(query.hashtagId) !== String(value.hashtags[0]))) query.hashtags = value.hashtags.join(',')
	if (value.amountMin !== null) query.amountMin = formatAnnualAmountParam(value.amountMin)
	if (value.amountMax !== null) query.amountMax = formatAnnualAmountParam(value.amountMax)
	return query
}

export function annualPaymentRoute(selection, period = null, type = 'all', row = null) {
	const value = annualSelection(selection)
	if (!period && value.timeRange && !reportRangeError(value)) {
		const end = parseReportDate(value.dateTo)
		end.setHours(23, 59, 59, 0)
		period = { dateFrom: parseReportDate(value.dateFrom).getTime() / 1000, dateTo: end.getTime() / 1000 }
	}
	const query = {
		timeRange: period ? 'custom' : 'all',
		...(period ? { dateFrom: String(period.dateFrom), dateTo: String(period.dateTo) } : {}),
		type,
		status: 'all',
		includeFuturePayments: String(value.includeFuturePayments),
		annualReturn: JSON.stringify(annualRouteQuery(value)),
	}
	if (value.timeRange === 'calendar-selection' && reportCalendarRanges(value).filter(range => !period || (parseReportDate(range.dateFrom).getTime() / 1000 <= period.dateTo && parseReportDate(range.dateTo).getTime() / 1000 + 86399 >= period.dateFrom)).length > 1) {
		query.timeRange = 'calendarSelection'
		query.periodYears = value.periodYears.join(',')
		if (value.periodMonths.length) query.periodMonths = value.periodMonths.join(',')
	}
	if (row && value.grouping === 'labelsAndTags') {
		if (row.kind === 'hashtag') query.hashtagId = String(row.filter)
		else if (row.kind === 'label') query.tags = String(row.filter)
	} else if (row?.reportKey) {
		const filters = value.reportFilters ? JSON.parse(value.reportFilters) : []
		query.reportFilters = JSON.stringify([...filters, { dimension: value.grouping, id: row.reportKey, direct: Boolean(row.direct) }])
	} else if (row) {
		query[value.grouping === 'categories' ? 'categoryId' : 'paymentPartnerId'] = String(row.id)
		if (value.grouping === 'categories' && row.children?.length) query.includeSubcategories = 'true'
	}
	if (!query.reportFilters && value.reportFilters) query.reportFilters = value.reportFilters
	return value.projectId && value.entryScope === 'shared'
		? { name: 'project-payments', params: { id: String(value.projectId) }, query: appendNarrowing(query, value) }
		: { name: 'payments', query: appendNarrowing({ ...query, ...(value.projectId ? { projectId: String(value.projectId) } : {}) }, value) }
}

function choiceName(map, id, fallback) {
	if (map instanceof Map) return map.get(id) || fallback
	return map?.[id] || fallback
}

function choiceType(types, id) {
	if (!(types instanceof Map)) return ''
	const type = types.get(id) || types.get(Number(id)) || ''
	return type === 'income' || type === 'expense' ? type : ''
}

function choiceEntries(map) {
	if (map instanceof Map) return [...map.entries()]
	if (map && typeof map === 'object') return Object.entries(map)
	return []
}

function markedChoiceName(map, types, id, fallback) {
	const name = choiceName(map, id, fallback)
	const type = choiceType(types, id)
	if (!type) return name
	const key = String(name).trim().toLocaleLowerCase()
	const conflict = choiceEntries(map).some(([otherId, otherName]) => (
		Number(otherId) !== Number(id)
		&& String(otherName ?? '').trim().toLocaleLowerCase() === key
		&& choiceType(types, otherId)
		&& choiceType(types, otherId) !== type
	))
	return conflict ? `${name} (${type === 'income' ? '+' : '−'})` : name
}

/** Names and types for the catalog currently loaded for Meine Finanzen or one area. */
export function annualFilterNameMaps(items, usedIds, { incomes = true } = {}) {
	const used = new Set((Array.isArray(usedIds) ? usedIds : []).map(id => Number(id)).filter(id => Number.isSafeInteger(id) && id > 0))
	const names = new Map()
	const types = new Map()
	for (const item of Array.isArray(items) ? items : []) {
		const id = Number(item?.id)
		const name = String(item?.name || '').trim()
		if (!used.has(id) || !name) continue
		names.set(id, name)
		const type = item?.type === 'income' || item?.type === 'expense' ? item.type : ''
		if (incomes && type) types.set(id, type)
	}
	return { names, types }
}

export function annualFilterChips(selection, context = {}) {
	const value = annualSelection(selection)
	const or = context.or || 'or'
	const join = names => names.join(` ${or} `)
	const chips = []
	if (value.withoutArea) chips.push({ key: 'withoutArea', label: context.withoutArea || 'Without area' })
	if (value.search) chips.push({ key: 'search', label: `“${value.search}”` })
	if (value.categories.length) {
		chips.push({
			key: 'categories',
			label: join(value.categories.map(id => id === 0 ? (context.noCategory || String(id)) : (context.categoryGroupLabels?.[id] || markedChoiceName(context.categoryNames, context.categoryTypes, id, String(id))))),
		})
	}
	if (value.partners.length) {
		chips.push({
			key: 'partners',
			label: join(value.partners.map(id => id === 0 ? (context.noPartner || String(id)) : (context.partnerGroupLabels?.[id] || markedChoiceName(context.partnerNames, context.partnerTypes, id, String(id))))),
		})
	}
	if (value.amountMin !== null || value.amountMax !== null) {
		const format = typeof context.formatMoney === 'function' ? context.formatMoney : cents => formatAnnualAmountParam(cents)
		const min = value.amountMin === null ? '' : format(value.amountMin)
		const max = value.amountMax === null ? '' : format(value.amountMax)
		chips.push({
			key: 'amount',
			label: value.amountMin !== null && value.amountMax !== null ? `${min}–${max}` : value.amountMin !== null ? `${context.atLeast || 'At least'} ${min}` : `${context.atMost || 'At most'} ${max}`,
		})
	}
	if (value.labels.length) {
		chips.push({
			key: 'labels',
			label: join(value.labels.map(key => context.labelNames?.[key] || key)),
		})
	}
	if (value.hashtags.length) {
		chips.push({
			key: 'hashtags',
			label: join(value.hashtags.map(id => {
				const name = choiceName(context.hashtagNames, id, '')
				if (!name) return `#${id}`
				return name.startsWith('#') ? name : `#${name}`
			})),
		})
	}
	return chips
}

function csvFormulaSafe(value) {
	const text = String(value ?? '')
	if (text === '') return ''
	return /^(?:[\t\r\n]|[\u0000-\u0020]*[=+\-@])/.test(text) ? `'${text}` : text
}

function csvField(value, textual) {
	const text = textual ? csvFormulaSafe(value) : String(value ?? '')
	return /[;"\r\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text
}

function csvAmount(cents) {
	const value = Number(cents) || 0
	const negative = value < 0
	const absolute = Math.abs(Math.trunc(value))
	return `${negative ? '-' : ''}${Math.trunc(absolute / 100)},${String(absolute % 100).padStart(2, '0')}`
}

// Semicolon and a decimal comma match German Excel. Empty payment cells stay blank.
export function buildAnnualOverviewCsv({ labelHeading, columns, sections, metadata = [] }) {
	const width = columns.length + 1
	const pad = cells => {
		const line = cells.slice(0, width)
		while (line.length < width) line.push('')
		return line
	}
	const lines = [pad([labelHeading, ...columns])]
	for (const section of sections) {
		lines.push(pad([section.title]))
		for (const row of section.rows || []) {
			const amounts = Array.isArray(row.amounts) ? row.amounts : []
			const counts = Array.isArray(row.counts) ? row.counts : []
			lines.push(pad([row.label, ...amounts.map((amount, index) => {
				const totalColumn = index === amounts.length - 1
				const empty = !row.balance && !totalColumn && Number(counts[index] ?? (amount === 0 ? 0 : 1)) === 0
				return empty ? '' : csvAmount(amount)
			})]))
		}
	}
	const prefix = metadata.length ? [...metadata.map(row => pad(row).map(value => csvField(value, true)).join(';')), pad([]).join(';')] : []
	return `\uFEFF${[...prefix, ...lines.map(line => line.map((value, index) => csvField(value, index === 0)).join(';'))].join('\r\n')}\r\n`
}

export function downloadTextFile(filename, contents, type = 'text/csv;charset=utf-8') {
	const blob = new Blob([contents], { type })
	const url = URL.createObjectURL(blob)
	const link = document.createElement('a')
	link.href = url
	link.download = filename
	document.body.appendChild(link)
	link.click()
	link.remove()
	URL.revokeObjectURL(url)
}

export function nextcloudCopyName(filename, copy) {
	const name = String(filename ?? '')
	const number = Number(copy) || 0
	if (number < 1) return name
	const dot = name.lastIndexOf('.')
	const suffix = `-${number}`
	if (dot <= 0) return `${name}${suffix}`
	return `${name.slice(0, dot)}${suffix}${name.slice(dot)}`
}

export function nextcloudFilePath(folder, filename) {
	const name = String(filename ?? '').replace(/[\\/]/g, '').trim()
	const parts = String(folder ?? '').replace(/\\/g, '/').split('/').filter(part => part && part !== '.' && part !== '..')
	return `/${[...parts, name].filter(Boolean).join('/')}`
}

export function encodeNextcloudPath(path) {
	return String(path ?? '').split('/').map(segment => encodeURIComponent(segment)).join('/')
}
