import { getCurrentUser } from '@nextcloud/auth'

export function isReportView(name) {
	return ['annual-overview', 'analytics'].includes(name)
}

function storageKey() {
	const userId = getCurrentUser()?.uid
	return userId ? `cobudget_mobile_report_view:${userId}` : null
}

export function readMobileReportView() {
	try {
		const key = storageKey()
		const saved = key ? window.localStorage.getItem(key) : null
		return isReportView(saved) ? saved : 'annual-overview'
	} catch {
		return 'annual-overview'
	}
}

export function rememberMobileReportView(name) {
	if (!isReportView(name) || typeof window === 'undefined' || !window.matchMedia?.('(max-width: 768px)').matches) return null
	try {
		const key = storageKey()
		if (key) window.localStorage.setItem(key, name)
	} catch {
		// Navigation still remembers the choice for this session if storage is blocked.
	}
	return name
}

export function mobileReportLocation(route, preferred) {
	// Tapping the active navigation item must not reset the displayed filters.
	return isReportView(route?.name)
		? { name: route.name, query: route.query }
		: { name: isReportView(preferred) ? preferred : 'annual-overview' }
}
