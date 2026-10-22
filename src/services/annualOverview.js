const LABEL_KEYS = ['important', 'review', 'fixedCost', 'childRelated', 'subscription', 'taxRelevant', 'insurance']
const FILTER_LIMIT = 50
const ANNUAL_SECTIONS = ['income', 'expense', 'total', 'chart']

function annualSectionStorageKey() {
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

function readAnnualSectionPreference() {
	try {
		if (typeof window === 'undefined' || !window.localStorage) return {}
		const saved = JSON.parse(window.localStorage.getItem(annualSectionStorageKey()) || '{}')
		return saved && typeof saved === 'object' ? saved : {}
	} catch {
		return {}
	}
}

export function readAnnualSectionOpen(section) {
	if (!ANNUAL_SECTIONS.includes(section)) return true
	return readAnnualSectionPreference()[section] !== false
}

export function rememberAnnualSection(section, open) {
	if (!ANNUAL_SECTIONS.includes(section) || typeof window === 'undefined' || !window.localStorage) return
	try {
		const saved = readAnnualSectionPreference()
		saved[section] = open === true
		window.localStorage.setItem(annualSectionStorageKey(), JSON.stringify(saved))
	} catch {
		// The choice still applies until the page is left.
	}
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
	return {
		year: selectedYear,
		period,
		month,
		quarter,
		grouping,
		projectId: Number.isSafeInteger(projectId) && projectId > 0 ? projectId : null,
		includeFuturePayments: annualPeriodIsFuture(selectedYear, period, month) || (containsToday && (query.includeFuturePayments === true || query.includeFuturePayments === 'true')),
		categories: sections.categories ? parseIdList(query.categories, true) : [],
		partners: sections.partners ? parseIdList(query.partners, true) : [],
		labels: sections.labels ? parseLabelList(query.labels) : [],
		hashtags: parseIdList(query.hashtags, false),
		amountMin,
		amountMax,
	}
}

function filterQuery(value) {
	return {
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
	return {
		year: String(value.year),
		grouping: value.grouping,
		...(value.period === 'quarter' ? { period: 'quarter' } : {}),
		...(value.period === 'week' ? { period: 'week' } : {}),
		...(value.period === 'day' ? { period: 'day', month: value.month >= 1 ? String(value.month) : 'all' } : {}),
		...(value.projectId ? { projectId: String(value.projectId) } : {}),
		...(value.includeFuturePayments ? { includeFuturePayments: 'true' } : {}),
		...filterQuery(value),
	}
}

export function annualRequestParams(selection, extra = {}) {
	const value = annualSelection({ ...selection, ...extra })
	return {
		year: value.year,
		grouping: value.grouping,
		...(value.period === 'quarter' ? { period: 'quarter' } : {}),
		...(value.period === 'week' ? { period: 'week' } : {}),
		...(value.period === 'day' ? { period: 'day', ...(value.month >= 1 ? { month: value.month } : {}) } : {}),
		projectId: value.projectId,
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
	if (value.categories.length === 1 && query.categoryId === undefined) query.categoryId = String(value.categories[0])
	else if (value.categories.length > 1 || (value.categories.length === 1 && String(query.categoryId) !== String(value.categories[0]))) query.categories = value.categories.join(',')
	if (value.partners.length === 1 && query.paymentPartnerId === undefined) query.paymentPartnerId = String(value.partners[0])
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
	const query = {
		timeRange: period ? 'custom' : 'all',
		...(period ? { dateFrom: String(period.dateFrom), dateTo: String(period.dateTo) } : {}),
		type,
		status: 'all',
		includeFuturePayments: String(value.includeFuturePayments),
		annualReturn: JSON.stringify(annualRouteQuery(value)),
	}
	if (row && value.grouping === 'labelsAndTags') {
		if (row.kind === 'hashtag') query.hashtagId = String(row.filter)
		else if (row.kind === 'label') query.tags = String(row.filter)
	} else if (row) {
		query[value.grouping === 'categories' ? 'categoryId' : 'paymentPartnerId'] = String(row.id)
		if (value.grouping === 'categories' && row.children?.length) query.includeSubcategories = 'true'
	}
	return value.projectId
		? { name: 'project-payments', params: { id: String(value.projectId) }, query: appendNarrowing(query, value) }
		: { name: 'payments', query: appendNarrowing(query, value) }
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
	if (value.categories.length) {
		chips.push({
			key: 'categories',
			label: join(value.categories.map(id => id === 0 ? (context.noCategory || String(id)) : markedChoiceName(context.categoryNames, context.categoryTypes, id, String(id)))),
		})
	}
	if (value.partners.length) {
		chips.push({
			key: 'partners',
			label: join(value.partners.map(id => id === 0 ? (context.noPartner || String(id)) : markedChoiceName(context.partnerNames, context.partnerTypes, id, String(id)))),
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
export function buildAnnualOverviewCsv({ labelHeading, columns, sections }) {
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
	return `\uFEFF${lines.map(line => line.map((value, index) => csvField(value, index === 0)).join(';')).join('\r\n')}\r\n`
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
