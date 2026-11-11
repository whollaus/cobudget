// Summary links only enter reports when every active payment filter is representable.
// Otherwise the saved-payment list remains the authoritative drilldown.
export function paymentCalendarIncludesMonth(filters, date) {
	if (filters.timeRange !== 'calendarSelection') return true
	return filters.periodYears.split(',').includes(String(date.getFullYear()))
		&& (!filters.periodMonths || filters.periodMonths.split(',').includes(String(date.getMonth() + 1)))
}

export function paymentPeriodBounds(filters, now = new Date()) {
	let from, to
	const year = now.getFullYear(), month = now.getMonth()
	switch (filters.timeRange) {
		case 'currentYear': from = new Date(year, 0, 1); to = new Date(year + 1, 0, 1); break
		case 'lastYear': from = new Date(year - 1, 0, 1); to = new Date(year, 0, 1); break
		case 'currentMonth': from = new Date(year, month, 1); to = new Date(year, month + 1, 1); break
		case 'lastMonth': from = new Date(year, month - 1, 1); to = new Date(year, month, 1); break
		case 'last30Days': from = new Date(year, month, now.getDate() - 30); to = new Date(year, month, now.getDate() + 1); break
		case 'custom': return { dateFrom: filters.dateFrom, dateTo: filters.dateTo }
		default: return { dateFrom: null, dateTo: null }
	}
	return { dateFrom: from.getTime() / 1000, dateTo: to.getTime() / 1000 - 1 }
}

export function paymentYearFilters(filters, year, now = new Date()) {
	if (year === 'all') return { ...filters, timeRange: 'all', dateFrom: null, dateTo: null }
	const value = Number(year)
	if (!Number.isInteger(value) || value < 1970 || value > 9999) return filters
	if (value === now.getFullYear()) return { ...filters, timeRange: 'currentYear', ...paymentPeriodBounds({ timeRange: 'currentYear' }, now) }
	return { ...filters, timeRange: 'custom', dateFrom: new Date(value, 0, 1).getTime() / 1000, dateTo: new Date(value + 1, 0, 1).getTime() / 1000 - 1 }
}

export function paymentPeriodYear(filters, now = new Date()) {
	if (filters.timeRange === 'all') return 'all'
	const { dateFrom, dateTo } = paymentPeriodBounds(filters, now)
	if (dateFrom == null || dateTo == null) return 'custom'
	const from = new Date(dateFrom * 1000), to = new Date(dateTo * 1000)
	return from.getMonth() === 0 && from.getDate() === 1 && to.getMonth() === 11 && to.getDate() === 31 && from.getFullYear() === to.getFullYear() ? from.getFullYear() : 'custom'
}

export function defaultPaymentPeriod(query, filters, tableOnly = false) {
	// Explicit all/custom ranges and report drilldowns must never acquire a default year.
	return !tableOnly && query.timeRange === undefined && query.dateFrom === undefined && query.dateTo === undefined && filters.timeRange === 'all' && filters.tags !== 'future'
		? { ...filters, timeRange: 'currentYear' } : filters
}

const dateText = seconds => {
	const date = new Date(seconds * 1000)
	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function paymentSummaryTarget(key, filters, now = new Date()) {
	if (key === 'budget-goals') return { name: 'budgets' }
	let next = { ...filters, ...paymentPeriodBounds(filters, now) }
	if (key === 'current-month' && paymentCalendarIncludesMonth(filters, now)) {
		const month = paymentPeriodBounds({ timeRange: 'currentMonth' }, now)
		next = { ...next, timeRange: 'custom', dateFrom: Math.max(next.dateFrom ?? month.dateFrom, month.dateFrom), dateTo: Math.min(next.dateTo ?? month.dateTo, month.dateTo) }
	}
	if (key === 'income' || key === 'expenses') next.type = key === 'income' ? 'income' : 'expense'
	const labels = { important: 'important', review: 'review', 'fixed-costs': 'fixedCost', children: 'childRelated', subscriptions: 'subscription', 'tax-relevant': 'taxRelevant', insurance: 'insurance' }
	if (labels[key]) {
		// Preserve an existing label filter as an AND condition when drilling into another label.
		if (next.tags !== 'all' && next.tags !== labels[key] && next.tags !== 'future') {
			next.narrowLabels = next.narrowLabels || next.tags
			// A pre-existing multi-label constraint needs a separate report-scope conjunction.
			if (filters.narrowLabels) {
				let scope
				try { scope = JSON.parse(next.reportFilters || '[]') } catch { scope = [] }
				next.reportFilters = JSON.stringify([...scope, { dimension: 'labels', id: next.tags, direct: true }])
			}
		}
		next.tags = labels[key]
	}
	if (key === 'planned') {
		if (Object.values(labels).includes(next.tags)) {
			let scope
			try { scope = JSON.parse(next.reportFilters || '[]') } catch { scope = [] }
			next.reportFilters = JSON.stringify([...scope, { dimension: 'labels', id: next.tags, direct: true }])
		}
		next.tags = 'future'
	}
	const reportCompatible = next.timeRange !== 'calendarSelection' && ['balance', 'current-month'].includes(key)
		&& next.type === 'all' && next.status === 'all' && next.tags === 'all'
		&& next.hasReminder === 'all' && next.hasAttachment === 'all'
		&& next.categoryId == null && !next.narrowCategories && !next.includeSubcategories
		&& !(next.narrowPartners || '').split(',').some(kind => ['income', 'expense'].includes(kind))
		&& next.hashtagId == null && next.paymentPartnerId == null && !next.narrowProjects
		&& !next.includeFuturePayments && next.dateFrom != null && next.dateTo != null
		&& next.dateFrom <= now.getTime() / 1000 && next.dateTo >= next.dateFrom
		&& (next.dateTo - next.dateFrom) / 86400 < 366 * 5
	if (reportCompatible) return { name: 'annual-overview', query: {
		timeRange: 'custom', dateFrom: dateText(next.dateFrom), dateTo: dateText(next.dateTo),
		year: String(new Date(next.dateFrom * 1000).getFullYear()), period: key === 'current-month' ? 'day' : 'year',
		grouping: 'categories', ...(next.projectId ? { projectId: String(next.projectId) } : {}),
		...(next.search ? { search: next.search } : {}),
		...(next.narrowPartners ? { partners: next.narrowPartners } : {}),
		...(next.narrowLabels ? { labels: next.narrowLabels } : {}),
		...(next.narrowHashtags ? { hashtags: next.narrowHashtags } : {}),
		...(next.amountMin ? { amountMin: next.amountMin } : {}),
		...(next.amountMax ? { amountMax: next.amountMax } : {}),
		...(next.reportFilters ? { reportFilters: next.reportFilters } : {}),
	} }
	return { name: 'payments', filters: next }
}

// Bridge the report picker's calendar dates and the payment list's local timestamps.
export function paymentPickerSelection(filters, now = new Date()) {
	if (filters.timeRange === 'calendarSelection') {
		const periodYears = filters.periodYears.split(',').map(Number)
		const periodMonths = filters.periodMonths ? filters.periodMonths.split(',').map(Number) : []
		return { timeRange: 'calendar-selection', year: periodYears.at(-1), periodYears, periodMonths }
	}
	const bounds = paymentPeriodBounds(filters, now)
	const dateFrom = bounds.dateFrom == null ? '' : dateText(bounds.dateFrom)
	const dateTo = bounds.dateTo == null ? '' : dateText(bounds.dateTo)
	const ranges = { all: 'all', currentYear: 'current-year', lastYear: 'last-year', currentMonth: 'current-month', lastMonth: 'last-month' }
	const fullYear = paymentPeriodYear(filters, now)
	let timeRange = ranges[filters.timeRange] || (typeof fullYear === 'number' ? 'year' : 'custom')
	// A rolling twelve-month choice is stored as an exact custom range in payment routes.
	if (timeRange === 'custom' && dateFrom === dateText(new Date(now.getFullYear(), now.getMonth() - 11, 1).getTime() / 1000)
		&& dateTo === dateText(new Date(now.getFullYear(), now.getMonth() + 1, 0).getTime() / 1000)) timeRange = 'last-12-months'
	return { timeRange, dateFrom, dateTo, year: dateFrom ? new Date(bounds.dateFrom * 1000).getFullYear() : now.getFullYear(), period: 'year' }
}

export function paymentPickerFilters(filters, selection) {
	if (selection.timeRange === 'calendar-selection') {
		const periodYears = [...selection.periodYears].sort((a, b) => a - b).join(',')
		const periodMonths = selection.periodMonths.length === 12 ? '' : [...selection.periodMonths].sort((a, b) => a - b).join(',')
		return { ...filters, timeRange: 'calendarSelection', periodYears, periodMonths, dateFrom: null, dateTo: null }
	}
	const { periodYears, periodMonths, ...otherFilters } = filters
	if (selection.timeRange === 'all') return { ...otherFilters, timeRange: 'all', dateFrom: null, dateTo: null }
	const parse = value => {
		if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null
		const [year, month, day] = value.split('-').map(Number)
		const date = new Date(year, month - 1, day)
		return year >= 1970 && year <= 9999 && dateText(date.getTime() / 1000) === value ? date : null
	}
	const from = parse(selection.dateFrom), to = parse(selection.dateTo)
	if (!from || !to || from > to) return filters
	to.setDate(to.getDate() + 1)
	const ranges = { 'current-year': 'currentYear', 'last-year': 'lastYear', 'current-month': 'currentMonth', 'last-month': 'lastMonth' }
	return { ...otherFilters, timeRange: ranges[selection.timeRange] || 'custom', dateFrom: from.getTime() / 1000, dateTo: to.getTime() / 1000 - 1 }
}

export function paymentPeriodIsCurrentMonth(filters, now = new Date()) {
	if (filters.timeRange === 'calendarSelection') return paymentPeriodIsSingleMonth(filters, now) && paymentCalendarIncludesMonth(filters, now)
	const selected = paymentPeriodBounds(filters, now)
	const current = paymentPeriodBounds({ timeRange: 'currentMonth' }, now)
	return selected.dateFrom === current.dateFrom && selected.dateTo === current.dateTo
}

export function paymentPeriodIsSingleMonth(filters, now = new Date()) {
	if (filters.timeRange === 'calendarSelection') return Boolean(filters.periodMonths && !filters.periodMonths.includes(',') && !filters.periodYears.includes(','))
	const { dateFrom, dateTo } = paymentPeriodBounds(filters, now)
	if (dateFrom == null || dateTo == null || dateFrom > dateTo) return false
	const from = new Date(dateFrom * 1000), to = new Date(dateTo * 1000)
	return from.getFullYear() === to.getFullYear() && from.getMonth() === to.getMonth()
}

// Budgets keep their own calendar evaluation period; never present current values
// as a historical or partial-period evaluation. The midpoint identifies the
// calendar month/year independently of server/browser midnight offsets.
export function paymentPeriodBudgetGoals(goals, filters, now = new Date()) {
	const selected = paymentPeriodBounds(filters, now)
	return goals.filter(goal => {
		const start = Number(goal.evaluation?.period_start), end = Number(goal.evaluation?.period_end)
		if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start || !['month', 'year'].includes(goal.period)) return false
		const middle = new Date((start + (end - start) / 2) * 1000)
		if (filters.timeRange === 'calendarSelection') {
			return paymentCalendarIncludesMonth(filters, middle) && (goal.period === 'month' || !filters.periodMonths)
		}
		const month = goal.period === 'month' ? middle.getMonth() : 0
		const from = new Date(middle.getFullYear(), month, 1).getTime() / 1000
		const to = new Date(middle.getFullYear(), month + (goal.period === 'month' ? 1 : 12), 1).getTime() / 1000 - 1
		return (selected.dateFrom == null || selected.dateFrom <= from)
			&& (selected.dateTo == null || selected.dateTo >= to)
	})
}

// A period closing row must not open a broader report than its displayed total.
export function paymentGroupReportTarget(group, filters, { projectId = null, entryScope = 'personal' } = {}, now = new Date()) {
	if (filters.timeRange === 'calendarSelection' && filters.periodMonths && group.level !== 'month') return null
	const bounds = paymentPeriodBounds(filters, now)
	const dateFrom = Math.max(group.dateFrom, bounds.dateFrom ?? group.dateFrom)
	const dateTo = Math.min(group.dateTo, bounds.dateTo ?? group.dateTo)
	if (!Number.isFinite(dateFrom) || !Number.isFinite(dateTo) || dateFrom > dateTo
		|| (filters.recurring && filters.recurring !== 'all')) return null
	let predicates
	try { predicates = JSON.parse(filters.reportFilters || '[]') } catch { return null }
	if (!Array.isArray(predicates)) return null
	const next = { ...filters, timeRange: 'custom', dateFrom, dateTo }
	const append = (dimension, id, direct = true) => predicates.push({ dimension, id: String(id), direct })
	if (next.type !== 'all') { append('type', next.type); next.type = 'all' }
	if (next.tags && next.tags !== 'all' && next.tags !== 'future') { append('labels', next.tags); next.tags = 'all' }
	if (next.categoryId != null) { append('categories', next.categoryId, !next.includeSubcategories); next.categoryId = null; next.includeSubcategories = false }
	if (next.paymentPartnerId != null) { append('paymentPartners', next.paymentPartnerId); next.paymentPartnerId = null }
	if (next.hashtagId != null) { append('hashtags', next.hashtagId); next.hashtagId = null }
	if (next.narrowProjects) {
		const ids = String(next.narrowProjects).split(',')
		if (ids.length !== 1 || !/^\d+$/.test(ids[0])) return null
		append('projects', ids[0]); next.narrowProjects = ''
	}
	// Report category filters include descendants, while list multi-selects are exact.
	if (next.narrowCategories) return null
	if (predicates.length > 8) return null
	next.reportFilters = predicates.length ? JSON.stringify(predicates) : ''
	const target = paymentSummaryTarget('balance', next, now)
	if (target.name !== 'annual-overview') return null
	return { ...target, query: { ...target.query, period: group.level === 'month' ? 'day' : 'year',
		...(projectId ? { projectId: String(projectId), entryScope } : {}) } }
}
