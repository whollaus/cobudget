export function isReportView(name) {
	return name === 'annual-overview'
}

// Keep existing bookmarks, including the former Statistics period names.
export function legacyAnalyticsLocation(route) {
	const query = { ...route?.query }
	if (['current-year', 'last-year', 'current-month', 'last-month', 'last-12-months'].includes(query.period)) {
		if (!query.timeRange) query.timeRange = query.period
		delete query.period
	} else if (typeof query.period === 'string' && /^year:\d{4}$/.test(query.period)) {
		const year = Number(query.period.slice(5))
		if (year >= 1970 && year <= 9999) {
			if (!query.timeRange) {
				query.timeRange = 'year'
				query.year = String(year)
			}
			delete query.period
		}
	}
	return { name: 'annual-overview', query, hash: route?.hash || '' }
}

export function reportLocation(route) {
	// Tapping the active navigation item must retain the open detail and filters.
	if (isReportView(route?.name)) return { name: route.name, query: route.query, hash: route.hash || '' }
	if (route?.name === 'analytics') return legacyAnalyticsLocation(route)
	return { name: 'annual-overview' }
}
