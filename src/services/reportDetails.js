// Detail links contain identities only. Resolve labels, amounts and permission-scoped
// rows from the current report after navigation or a reload.
export function reportDetailQuery(detail, periods) {
	return {
		detailType: detail.row.type,
		detailId: detail.total ? 'total' : String(detail.row.id),
		...(detail.row.direct ? { detailDirect: '1' } : {}),
		detailPeriod: detail.index === periods.length - 1 ? 'total' : String(detail.index),
	}
}

export function reportDetailNavigation(query) {
	return Object.fromEntries(['detailType', 'detailId', 'detailDirect', 'detailPeriod', 'detailPage', 'detailSort', 'detailDir', 'detailArea', 'detailAreaScope', 'detailAreaTags', 'detailPath'].filter(key => query[key] !== undefined).map(key => [key, query[key]]))
}

export function areaOverviewOptions(query = {}) {
	return { areaAmountView: query.areasView === 'area' ? 'area' : 'personal', areaMetric: ['income', 'balance'].includes(query.areasMetric) ? query.areasMetric : 'expense' }
}

export function areaOverviewQuery(amountView, metric) {
	return { ...(amountView === 'area' ? { areasView: 'area' } : {}), ...(['income', 'balance'].includes(metric) ? { areasMetric: metric } : {}) }
}

export function resolveReportDetail(query, report, rows, totals, directLabel) {
	const type = query.detailType
	if (!['income', 'expense', 'all'].includes(type) || !totals.some(row => row.type === type)) return null
	const total = query.detailId === 'total'
	let row = total ? totals.find(row => row.type === type) : null
	if (!total) {
		for (const parent of rows.filter(row => row.type === type)) {
			if (String(parent.id) === query.detailId && query.detailDirect !== '1') { row = parent; break }
			const child = (parent.children || []).find(child => String(child.id) === query.detailId && Boolean(child.direct) === (query.detailDirect === '1'))
			if (child) { row = { ...child, type, label: `${parent.label} · ${child.direct ? directLabel : child.name}` }; break }
		}
	}
	const index = query.detailPeriod === 'total' || query.detailPeriod === undefined
		? report.periods.length - 1
		: /^\d+$/.test(String(query.detailPeriod)) ? Number(query.detailPeriod) : -1
	if (!row || index < 0 || index >= report.periods.length) return null
	return { row, index, total, label: row.label }
}

export function reportDetailRow(report, detail) {
	if (detail.total) return report.totals[detail.row.type === 'all' ? 'balance' : detail.row.type]
	const rows = report.groups[detail.row.type] || []
	const parent = rows.find(row => String(row.id) === String(detail.row.id))
	return (parent && (!detail.row.direct || !parent.children?.length) && parent)
		|| rows.flatMap(row => row.children || []).find(row => String(row.id) === String(detail.row.id) && Boolean(row.direct) === Boolean(detail.row.direct))
		|| { months: Array(report.periods.length - 1).fill(0), counts: Array(report.periods.length - 1).fill(0), total: 0, totalCount: 0 }
}

// Annual references already carry the selected filters, amount perspective and cutoff.
// Use their exact total rather than multiplying a rounded per-period average.
export function reportAnnualTotal(report, detail) {
	const period = report?.periods?.at(-1)
	if (!period) return null
	const from = new Date(period.dateFrom * 1000), to = new Date(period.dateTo * 1000)
	if (from.getMonth() !== 0 || from.getDate() !== 1 || to.getMonth() !== 11 || to.getDate() !== 31 || from.getFullYear() !== to.getFullYear()) return null
	return { year: from.getFullYear(), amount: reportDetailRow(report, detail).total }
}

export function reportChartLabels(periods, selection, format) {
	const crossYear = periods.length > 1 && new Date(periods[0].dateFrom * 1000).getFullYear() !== new Date(periods.at(-1).dateTo * 1000).getFullYear()
	const date = timestamp => new Date(timestamp * 1000)
	const fullDate = timestamp => format(date(timestamp), { day: 'numeric', month: 'long', year: 'numeric' })
	const labels = periods.map(period => {
		if (['day', 'month', 'week'].includes(selection.period)) return format(date(period.dateFrom), { day: 'numeric', month: 'short', ...(crossYear ? { year: '2-digit' } : {}) })
		return format(date(period.dateFrom), { month: 'short', ...(crossYear ? { year: 'numeric' } : {}) })
	})
	return { labels, titles: periods.map(period => `${fullDate(period.dateFrom)} – ${fullDate(period.dateTo)}`) }
}

export function reportPaymentParams(route, selection, cutoff, { limit = 25, offset = 0, sortBy = 'date', sortDir = 'desc' } = {}) {
	const query = route.query
	const params = { limit, offset, sortBy, sortDir, type: query.type, projectId: selection.projectId,
		...(selection.reportFilters ? { reportFilters: selection.reportFilters } : {}),
		...(selection.withoutArea ? { withoutArea: true } : {}),
		entryScope: selection.projectId && selection.entryScope === 'shared' ? 'shared' : 'personal', reportGrouping: selection.grouping,
		includeFuturePayments: selection.includeFuturePayments, includeSubcategories: query.includeSubcategories === 'true',
		dateFrom: Number(query.dateFrom), dateTo: Math.min(Number(query.dateTo), Number(cutoff)),
	}
	for (const key of ['categoryId', 'paymentPartnerId', 'hashtagId', 'categories', 'partners', 'labels', 'hashtags', 'amountMin', 'amountMax']) {
		if (query[key] !== undefined) params[key] = query[key]
	}
	const flags = { important: 'isImportant', review: 'needsReview', fixedCost: 'isFixedCost', childRelated: 'isChildRelated', subscription: 'isSubscription', taxRelevant: 'isTaxRelevant', insurance: 'isInsurance' }
	if (flags[query.tags]) params[flags[query.tags]] = true
	return params
}

const BREAKDOWN_DIMENSIONS = ['categories', 'paymentPartners', 'labels', 'hashtags', 'projects']
const BREAKDOWN_LABELS = ['important', 'review', 'fixedCost', 'childRelated', 'subscription', 'taxRelevant', 'insurance']

/** URL state contains bounded identities only; invalid paths never fall back to wider data. */
export function reportDrilldownPath(query) {
	if (query.detailPath === undefined) return []
	try {
		if (typeof query.detailPath !== 'string' || query.detailPath.length > 2048) return null
		const path = JSON.parse(query.detailPath)
		if (!Array.isArray(path) || !path.length || path.length > BREAKDOWN_DIMENSIONS.length) return null
		const seen = new Set()
		for (const step of path) {
			if (!step || !BREAKDOWN_DIMENSIONS.includes(step.dimension) || seen.has(step.dimension) || !['income', 'expense'].includes(step.type)) return null
			if (typeof step.id !== 'string' || (step.dimension === 'labels'
				? step.id !== '' && !BREAKDOWN_LABELS.includes(step.id)
				: !/^(0|[1-9]\d*)$/.test(step.id) || !Number.isSafeInteger(Number(step.id)))) return null
			seen.add(step.dimension)
		}
		if (path.some(step => step.type !== path[0].type)) return null
		return path.map(({ dimension, id, type }) => ({ dimension, id, type }))
	} catch { return null }
}

export function reportBreakdownRoute(query, dimension, row, type) {
	const path = reportDrilldownPath(query)
	if (!path) return null
	const next = { ...query, detailPath: JSON.stringify([...path, { dimension, id: String(row.id), type }]) }
	if (!reportDrilldownPath(next)) return null
	for (const key of ['detailPage', 'detailSort', 'detailDir']) delete next[key]
	return { name: 'annual-overview', query: next }
}

export function reportDetailParentQuery(query) {
	if (query.detailPath === undefined) return null
	const path = reportDrilldownPath(query)
	const previous = { ...query }
	if (path?.length > 1) previous.detailPath = JSON.stringify(path.slice(0, -1))
	else delete previous.detailPath
	for (const key of ['detailPage', 'detailSort', 'detailDir']) delete previous[key]
	return previous
}

export function reportDetailParentMatches(query, previous) {
	const parent = reportDetailParentQuery(query)
	if (!parent || !previous) return false
	const identity = value => JSON.stringify(Object.entries(value)
		.filter(([key, item]) => item !== undefined && !['detailPage', 'detailSort', 'detailDir'].includes(key))
		.sort(([a], [b]) => a.localeCompare(b)))
	return identity(parent) === identity(previous)
}

export function reportDrilldownFilters(detail, selection, path) {
	if (!path?.length || (detail.row.type !== 'all' && path[0].type !== detail.row.type)) return null
	const filters = [{ dimension: 'type', id: path[0].type }]
	if (!detail.total) {
		let dimension = selection.grouping, id = String(detail.row.id)
		if (dimension === 'labelsAndTags') {
			dimension = id.startsWith('label:') ? 'labels' : 'hashtags'
			id = id.slice(dimension === 'labels' ? 6 : 4)
		}
		if (path.some(step => step.dimension === dimension)) return null
		filters.push({ dimension, id, ...(dimension === 'categories' ? { direct: Boolean(detail.row.direct) } : {}) })
	}
	filters.push(...path.map(({ dimension, id }) => ({ dimension, id, ...(dimension === 'categories' ? { direct: true } : {}) })))
	return filters
}

export function reportBreakdownLabel(row, dimension, texts) {
	if (dimension === 'categories') return Number(row.id) === 0 ? texts.annual.noCategory() : [row.parentName, row.name].filter(Boolean).join(' · ')
	if (dimension === 'paymentPartners') return Number(row.id) === 0 ? texts.annual.noPartner() : row.name
	if (dimension === 'labels') return row.id === '' ? texts.annual.withoutLabel() : row.name
	if (dimension === 'hashtags') return Number(row.id) === 0 ? texts.annual.withoutHashtag() : `#${row.name.replace(/^#/, '')}`
	return Number(row.id) === 0 ? texts.annual.personalPayments() : row.name
}

export function reportDrilldownLabels(breakdown, path, texts) {
	const labels = []
	for (const step of path) {
		const row = breakdown.groups?.[step.dimension]?.[step.type]?.find(row => String(row.id) === step.id)
		if (!row) return null
		labels.push(reportBreakdownLabel(row, step.dimension, texts))
	}
	return labels
}
