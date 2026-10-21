import { normalizeEntryPageSize } from './pagination'

export const PERSONAL_ENTRY_FILTER_DEFAULTS = Object.freeze({
	search: '',
	type: 'all',
	status: 'all',
	categoryId: null,
	projectId: null,
	paymentPartnerId: null,
	dateFrom: null,
	dateTo: null,
	includeFuturePayments: false,
	includeSubcategories: false,
	annualReturn: '',
	timeRange: 'all',
	tags: 'all',
	hashtagId: null,
	hasReminder: 'all',
	hasAttachment: 'all',
	narrowCategories: '',
	narrowPartners: '',
	narrowLabels: '',
	narrowHashtags: '',
	amountMin: '',
	amountMax: '',
})

export const PROJECT_ENTRY_FILTER_DEFAULTS = Object.freeze({
	search: '',
	type: 'all',
	status: 'active',
	categoryId: null,
	paymentPartnerId: null,
	dateFrom: null,
	dateTo: null,
	includeFuturePayments: false,
	includeSubcategories: false,
	annualReturn: '',
	timeRange: 'all',
	recurring: 'all',
	tags: 'all',
	hashtagId: null,
	hasReminder: 'all',
	hasAttachment: 'all',
	narrowCategories: '',
	narrowPartners: '',
	narrowLabels: '',
	narrowHashtags: '',
	amountMin: '',
	amountMax: '',
})

const FILTER_SHORTCUTS = Object.freeze({
	subscription: { tags: 'subscription' },
	taxRelevant: { tags: 'taxRelevant' },
	insurance: { tags: 'insurance' },
	fixedCost: { tags: 'fixedCost' },
	childRelated: { tags: 'childRelated' },
	important: { tags: 'important' },
	review: { tags: 'review' },
	future: { tags: 'future' },
	reminder: { hasReminder: 'true' },
	currentYear: { timeRange: 'currentYear' },
	income: { type: 'income' },
})

const TYPE_VALUES = new Set(['all', 'income', 'expense'])
const STATUS_VALUES = new Set(['all', 'active', 'settled'])
const TIME_RANGE_VALUES = new Set(['all', 'currentMonth', 'lastMonth', 'last30Days', 'currentYear', 'lastYear', 'custom'])
const TAG_VALUES = new Set(['all', 'important', 'review', 'fixedCost', 'childRelated', 'subscription', 'taxRelevant', 'insurance', 'future'])
const NARROW_LABEL_VALUES = ['important', 'review', 'fixedCost', 'childRelated', 'subscription', 'taxRelevant', 'insurance']
const REMINDER_VALUES = new Set(['all', 'true'])
const ATTACHMENT_VALUES = new Set(['all', 'true', 'false'])
const RECURRING_VALUES = new Set(['all', 'true', 'false'])
const SORT_BY_VALUES = new Set(['date', 'description', 'category_name', 'paymentPartner', 'amount'])
const SORT_DIR_VALUES = new Set(['asc', 'desc'])

export const PERSONAL_ENTRY_ROUTE_QUERY_KEYS = Object.freeze([
	'filter',
	'search',
	'type',
	'status',
	'categoryId',
	'projectId',
	'paymentPartnerId',
	'timeRange',
	'dateFrom',
	'dateTo',
	'includeFuturePayments',
	'includeSubcategories',
	'annualReturn',
	'tags',
	'hashtagId',
	'hasReminder',
	'hasAttachment',
	'categories',
	'partners',
	'labels',
	'hashtags',
	'amountMin',
	'amountMax',
	'sortBy',
	'sortDir',
	'page',
	'limit',
])

export const PROJECT_ENTRY_ROUTE_QUERY_KEYS = Object.freeze([
	'filter',
	'search',
	'type',
	'status',
	'categoryId',
	'paymentPartnerId',
	'timeRange',
	'dateFrom',
	'dateTo',
	'includeFuturePayments',
	'includeSubcategories',
	'annualReturn',
	'recurring',
	'tags',
	'hashtagId',
	'hasReminder',
	'hasAttachment',
	'categories',
	'partners',
	'labels',
	'hashtags',
	'amountMin',
	'amountMax',
	'sortBy',
	'sortDir',
	'page',
	'limit',
])

const firstQueryValue = value => Array.isArray(value) ? value[0] : value

const queryString = value => {
	const normalized = firstQueryValue(value)
	return typeof normalized === 'string' ? normalized : ''
}

const queryChoice = (value, allowedValues, fallback) => {
	const normalized = queryString(value)
	return allowedValues.has(normalized) ? normalized : fallback
}

const queryPositiveInteger = (value, fallback = null) => {
	const parsed = Number.parseInt(firstQueryValue(value), 10)
	return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : fallback
}

const queryNonnegativeInteger = value => {
	const raw = firstQueryValue(value)
	if (raw === null || raw === undefined || !/^\d+$/.test(String(raw))) return null
	const parsed = Number(raw)
	return Number.isSafeInteger(parsed) && parsed >= 0 ? parsed : null
}

const queryTimestamp = value => {
	const raw = firstQueryValue(value)
	if (raw === null || raw === undefined || !/^-?\d+$/.test(String(raw))) return null
	const parsed = Number(raw)
	return Number.isSafeInteger(parsed) && parsed >= -86400 && parsed <= 253402387199 ? parsed : null
}

const queryListText = value => Array.isArray(value) ? value.map(item => String(item ?? '')).join(',') : (value === null || value === undefined ? '' : String(value))

const queryIdList = (value, allowZero) => {
	const ids = []
	const seen = new Set()
	for (const part of queryListText(value).split(',')) {
		const token = part.trim()
		if (!/^\d+$/.test(token)) continue
		const id = Number(token)
		if (!Number.isSafeInteger(id) || id < 0 || (id === 0 && !allowZero) || seen.has(id)) continue
		seen.add(id)
		ids.push(String(id))
		if (ids.length >= 50) break
	}
	return ids.join(',')
}

const queryLabelList = value => {
	const keys = []
	const seen = new Set()
	for (const part of queryListText(value).split(',')) {
		const token = part.trim()
		if (!NARROW_LABEL_VALUES.includes(token) || seen.has(token)) continue
		seen.add(token)
		keys.push(token)
		if (keys.length >= 50) break
	}
	return keys.join(',')
}

const queryAmount = value => {
	const text = String(firstQueryValue(value) ?? '').trim().replace(',', '.')
	if (!/^\d+(?:\.\d{1,2})?$/.test(text) || text.split('.')[0].length > 12) return ''
	return text
}

const amountCents = text => {
	const [whole, fraction = ''] = text.split('.')
	return Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
}

const assignNarrowingFilters = (filters, query) => {
	filters.narrowCategories = queryIdList(query.categories, true)
	filters.narrowPartners = queryIdList(query.partners, true)
	filters.narrowLabels = queryLabelList(query.labels)
	filters.narrowHashtags = queryIdList(query.hashtags, false)
	filters.amountMin = queryAmount(query.amountMin)
	filters.amountMax = queryAmount(query.amountMax)
	if (filters.amountMin && filters.amountMax && amountCents(filters.amountMin) > amountCents(filters.amountMax)) {
		const swapped = filters.amountMin
		filters.amountMin = filters.amountMax
		filters.amountMax = swapped
	}
}

export const entryNarrowingQueryParams = (filters = {}) => ({
	...(filters.narrowCategories ? { categories: filters.narrowCategories } : {}),
	...(filters.narrowPartners ? { partners: filters.narrowPartners } : {}),
	...(filters.narrowLabels ? { labels: filters.narrowLabels } : {}),
	...(filters.narrowHashtags ? { hashtags: filters.narrowHashtags } : {}),
	...(filters.amountMin ? { amountMin: filters.amountMin } : {}),
	...(filters.amountMax ? { amountMax: filters.amountMax } : {}),
})

const appendNarrowingQuery = (query, filters) => {
	Object.assign(query, entryNarrowingQueryParams(filters))
}

const normalizedFilters = filters => ({
	...PERSONAL_ENTRY_FILTER_DEFAULTS,
	...(filters || {}),
})

const normalizedProjectFilters = filters => ({
	...PROJECT_ENTRY_FILTER_DEFAULTS,
	...(filters || {}),
})

const shortcutForFilters = filters => {
	if (filters.tags !== 'all' && FILTER_SHORTCUTS[filters.tags]) {
		return filters.tags
	}
	if (filters.hasReminder === 'true') {
		return 'reminder'
	}
	if (filters.timeRange === 'currentYear') {
		return 'currentYear'
	}
	if (filters.type === 'income') {
		return 'income'
	}
	return ''
}

export const createPersonalEntryFilters = () => ({ ...PERSONAL_ENTRY_FILTER_DEFAULTS })

export const createProjectEntryFilters = () => ({ ...PROJECT_ENTRY_FILTER_DEFAULTS })

export const parsePersonalEntryListRouteQuery = (query = {}, defaultPageSize = 25) => {
	const filters = createPersonalEntryFilters()
	const shortcut = FILTER_SHORTCUTS[queryString(query.filter)]
	if (shortcut) {
		Object.assign(filters, shortcut)
	}

	filters.search = queryString(query.search)
	filters.type = queryChoice(query.type, TYPE_VALUES, filters.type)
	filters.status = queryChoice(query.status, STATUS_VALUES, filters.status)
	filters.categoryId = queryNonnegativeInteger(query.categoryId)
	filters.projectId = queryPositiveInteger(query.projectId)
	filters.paymentPartnerId = queryNonnegativeInteger(query.paymentPartnerId)
	filters.timeRange = queryChoice(query.timeRange, TIME_RANGE_VALUES, filters.timeRange)
	filters.dateFrom = filters.timeRange === 'custom' ? queryTimestamp(query.dateFrom) : null
	filters.dateTo = filters.timeRange === 'custom' ? queryTimestamp(query.dateTo) : null
	filters.includeFuturePayments = queryString(query.includeFuturePayments) === 'true'
	filters.includeSubcategories = queryString(query.includeSubcategories) === 'true'
	filters.annualReturn = queryString(query.annualReturn)
	filters.tags = queryChoice(query.tags, TAG_VALUES, filters.tags)
	filters.hashtagId = queryPositiveInteger(query.hashtagId)
	filters.hasReminder = queryChoice(query.hasReminder, REMINDER_VALUES, filters.hasReminder)
	filters.hasAttachment = queryChoice(query.hasAttachment, ATTACHMENT_VALUES, filters.hasAttachment)
	assignNarrowingFilters(filters, query)

	return {
		filters,
		sortBy: queryChoice(query.sortBy, SORT_BY_VALUES, 'date'),
		sortDir: queryChoice(query.sortDir, SORT_DIR_VALUES, 'desc'),
		page: queryPositiveInteger(query.page, 1),
		limit: normalizeEntryPageSize(queryPositiveInteger(query.limit, defaultPageSize)),
	}
}

export const parseProjectEntryListRouteQuery = (query = {}, defaultPageSize = 25) => {
	const filters = createProjectEntryFilters()
	const shortcut = FILTER_SHORTCUTS[queryString(query.filter)]
	if (shortcut) {
		Object.assign(filters, shortcut)
	}

	filters.search = queryString(query.search)
	filters.type = queryChoice(query.type, TYPE_VALUES, filters.type)
	filters.status = queryChoice(query.status, STATUS_VALUES, filters.status)
	filters.categoryId = queryNonnegativeInteger(query.categoryId)
	filters.paymentPartnerId = queryNonnegativeInteger(query.paymentPartnerId)
	filters.timeRange = queryChoice(query.timeRange, TIME_RANGE_VALUES, filters.timeRange)
	filters.dateFrom = filters.timeRange === 'custom' ? queryTimestamp(query.dateFrom) : null
	filters.dateTo = filters.timeRange === 'custom' ? queryTimestamp(query.dateTo) : null
	filters.includeFuturePayments = queryString(query.includeFuturePayments) === 'true'
	filters.includeSubcategories = queryString(query.includeSubcategories) === 'true'
	filters.annualReturn = queryString(query.annualReturn)
	filters.recurring = queryChoice(query.recurring, RECURRING_VALUES, filters.recurring)
	filters.tags = queryChoice(query.tags, TAG_VALUES, filters.tags)
	filters.hashtagId = queryPositiveInteger(query.hashtagId)
	filters.hasReminder = queryChoice(query.hasReminder, REMINDER_VALUES, filters.hasReminder)
	filters.hasAttachment = queryChoice(query.hasAttachment, ATTACHMENT_VALUES, filters.hasAttachment)
	assignNarrowingFilters(filters, query)

	return {
		filters,
		sortBy: queryChoice(query.sortBy, SORT_BY_VALUES, 'date'),
		sortDir: queryChoice(query.sortDir, SORT_DIR_VALUES, 'desc'),
		page: queryPositiveInteger(query.page, 1),
		limit: normalizeEntryPageSize(queryPositiveInteger(query.limit, defaultPageSize)),
	}
}

export const serializePersonalEntryListRouteQuery = ({
	filters: filterValues,
	sortBy = 'date',
	sortDir = 'desc',
	page = 1,
	limit = 25,
	includePagination = false,
} = {}) => {
	const filters = normalizedFilters(filterValues)
	const query = {}
	const shortcut = shortcutForFilters(filters)
	if (shortcut) query.filter = shortcut
	if (filters.search) query.search = String(filters.search)
	if (TYPE_VALUES.has(filters.type) && filters.type !== 'all') query.type = filters.type
	if (STATUS_VALUES.has(filters.status) && filters.status !== 'all') query.status = filters.status
	if (queryNonnegativeInteger(filters.categoryId) !== null) query.categoryId = String(filters.categoryId)
	if (queryPositiveInteger(filters.projectId)) query.projectId = String(filters.projectId)
	if (queryNonnegativeInteger(filters.paymentPartnerId) !== null) query.paymentPartnerId = String(filters.paymentPartnerId)
	if (TIME_RANGE_VALUES.has(filters.timeRange) && filters.timeRange !== 'all') query.timeRange = filters.timeRange
	if (filters.timeRange === 'custom') {
		if (queryTimestamp(filters.dateFrom) !== null) query.dateFrom = String(filters.dateFrom)
		if (queryTimestamp(filters.dateTo) !== null) query.dateTo = String(filters.dateTo)
	}
	if (filters.includeFuturePayments) query.includeFuturePayments = 'true'
	if (filters.includeSubcategories) query.includeSubcategories = 'true'
	if (filters.annualReturn) query.annualReturn = filters.annualReturn
	if (TAG_VALUES.has(filters.tags) && filters.tags !== 'all') query.tags = filters.tags
	if (queryPositiveInteger(filters.hashtagId)) query.hashtagId = String(filters.hashtagId)
	if (REMINDER_VALUES.has(filters.hasReminder) && filters.hasReminder !== 'all') query.hasReminder = filters.hasReminder
	if (ATTACHMENT_VALUES.has(filters.hasAttachment) && filters.hasAttachment !== 'all') query.hasAttachment = filters.hasAttachment
	appendNarrowingQuery(query, filters)
	if (SORT_BY_VALUES.has(sortBy) && sortBy !== 'date') query.sortBy = sortBy
	if (SORT_DIR_VALUES.has(sortDir) && sortDir !== 'desc') query.sortDir = sortDir

	if (includePagination) {
		const normalizedPage = queryPositiveInteger(page, 1)
		if (normalizedPage > 1) query.page = String(normalizedPage)
		query.limit = String(normalizeEntryPageSize(limit))
	}

	return query
}

export const serializeProjectEntryListRouteQuery = ({
	filters: filterValues,
	sortBy = 'date',
	sortDir = 'desc',
	page = 1,
	limit = 25,
	includePagination = false,
} = {}) => {
	const filters = normalizedProjectFilters(filterValues)
	const query = {}
	const shortcut = shortcutForFilters(filters)
	if (shortcut) query.filter = shortcut
	if (filters.search) query.search = String(filters.search)
	if (TYPE_VALUES.has(filters.type) && filters.type !== 'all') query.type = filters.type
	if (STATUS_VALUES.has(filters.status) && filters.status !== 'active') query.status = filters.status
	if (queryNonnegativeInteger(filters.categoryId) !== null) query.categoryId = String(filters.categoryId)
	if (queryNonnegativeInteger(filters.paymentPartnerId) !== null) query.paymentPartnerId = String(filters.paymentPartnerId)
	if (TIME_RANGE_VALUES.has(filters.timeRange) && filters.timeRange !== 'all') query.timeRange = filters.timeRange
	if (filters.timeRange === 'custom') {
		if (queryTimestamp(filters.dateFrom) !== null) query.dateFrom = String(filters.dateFrom)
		if (queryTimestamp(filters.dateTo) !== null) query.dateTo = String(filters.dateTo)
	}
	if (filters.includeFuturePayments) query.includeFuturePayments = 'true'
	if (filters.includeSubcategories) query.includeSubcategories = 'true'
	if (filters.annualReturn) query.annualReturn = filters.annualReturn
	if (RECURRING_VALUES.has(filters.recurring) && filters.recurring !== 'all') query.recurring = filters.recurring
	if (TAG_VALUES.has(filters.tags) && filters.tags !== 'all') query.tags = filters.tags
	if (queryPositiveInteger(filters.hashtagId)) query.hashtagId = String(filters.hashtagId)
	if (REMINDER_VALUES.has(filters.hasReminder) && filters.hasReminder !== 'all') query.hasReminder = filters.hasReminder
	if (ATTACHMENT_VALUES.has(filters.hasAttachment) && filters.hasAttachment !== 'all') query.hasAttachment = filters.hasAttachment
	appendNarrowingQuery(query, filters)
	if (SORT_BY_VALUES.has(sortBy) && sortBy !== 'date') query.sortBy = sortBy
	if (SORT_DIR_VALUES.has(sortDir) && sortDir !== 'desc') query.sortDir = sortDir

	if (includePagination) {
		const normalizedPage = queryPositiveInteger(page, 1)
		if (normalizedPage > 1) query.page = String(normalizedPage)
		query.limit = String(normalizeEntryPageSize(limit))
	}

	return query
}

export const personalEntryListQueriesEqual = (left = {}, right = {}) => {
	const leftKeys = Object.keys(left).sort()
	const rightKeys = Object.keys(right).sort()
	if (leftKeys.length !== rightKeys.length || leftKeys.some((key, index) => key !== rightKeys[index])) {
		return false
	}
	return leftKeys.every(key => queryString(left[key]) === queryString(right[key]))
}

export const hasPersonalEntryListRouteState = (query = {}) => PERSONAL_ENTRY_ROUTE_QUERY_KEYS
	.some(key => queryString(query[key]) !== '')

export const hasProjectEntryListRouteState = (query = {}) => PROJECT_ENTRY_ROUTE_QUERY_KEYS
	.some(key => queryString(query[key]) !== '')

export const entryPageFromOffset = (offset, limit) => Math.floor(Math.max(0, Number(offset) || 0) / normalizeEntryPageSize(limit)) + 1

export const entryOffsetFromPage = (page, limit) => (Math.max(1, queryPositiveInteger(page, 1)) - 1) * normalizeEntryPageSize(limit)
