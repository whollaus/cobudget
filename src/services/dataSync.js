import { listen } from '@nextcloud/notify_push'
import { generateUrl } from '@nextcloud/router'
import axios from './http'

export const REMOTE_DATA_CHANGED_EVENT = 'cobudget-remote-data-changed'

const PUSH_EVENT = 'cobudget_data_changed'
const REFRESH_DELAY_MS = 300
const POLL_INTERVAL_MS = 30_000
const FALLBACK_DOMAINS = ['entries', 'projects', 'settlements', 'budgets', 'analytics']

let started = false
let initialRevisionKnown = false
let initialRevisionRequestFailed = false
let revision = ''
let stateRequest = null
let queuedChange = null
let refreshTimer = null
let deferredChange = null
let pollTimer = null
let pushSequence = 0

function normalizeDomains(domains) {
	if (!Array.isArray(domains)) {
		return []
	}

	return [...new Set(domains.filter(domain => FALLBACK_DOMAINS.includes(domain)))]
}

function normalizeProjectId(projectId) {
	if (typeof projectId === 'string' && !/^[1-9]\d*$/.test(projectId.trim())) {
		return null
	}
	if (typeof projectId !== 'string' && typeof projectId !== 'number') {
		return null
	}

	const value = Number(projectId)
	return Number.isSafeInteger(value) && value > 0 ? value : null
}

function normalizeChange(change = {}) {
	return {
		domains: normalizeDomains(change.domains),
		projectId: normalizeProjectId(change.projectId),
		revision: typeof change.revision === 'string' ? change.revision : '',
	}
}

function mergeChanges(first, second) {
	const left = normalizeChange(first)
	const right = normalizeChange(second)
	return {
		domains: [...new Set([...left.domains, ...right.domains])],
		projectId: left.projectId === right.projectId ? left.projectId : null,
		revision: right.revision || left.revision,
	}
}

function fallbackChange() {
	return {
		domains: FALLBACK_DOMAINS,
		projectId: null,
		revision: '',
	}
}

function eventChange(change) {
	const normalized = normalizeChange(change)
	if (normalized.domains.length > 0) {
		return normalized
	}

	return {
		...fallbackChange(),
		projectId: normalized.projectId,
		revision: normalized.revision,
	}
}

function isVisible() {
	return typeof document === 'undefined' || document.visibilityState === 'visible'
}

async function requestRevision() {
	const response = await axios.get(generateUrl('/apps/cobudget/api/sync/state'), {
		params: { _t: Date.now() },
		skipWorkspaceHeader: true,
	})
	return typeof response.data?.revision === 'string' ? response.data.revision : ''
}

function scheduleRefresh(change) {
	const normalized = eventChange(change)
	queuedChange = queuedChange === null ? normalized : mergeChanges(queuedChange, normalized)
	if (refreshTimer !== null) {
		return
	}

	refreshTimer = window.setTimeout(() => {
		const nextChange = queuedChange || fallbackChange()
		queuedChange = null
		refreshTimer = null
		window.dispatchEvent(new CustomEvent(REMOTE_DATA_CHANGED_EVENT, { detail: nextChange }))
	}, REFRESH_DELAY_MS)
}

async function checkForChanges() {
	if (stateRequest !== null) {
		return stateRequest
	}

	const requestPushSequence = pushSequence
	stateRequest = requestRevision()
		.then(nextRevision => {
			if (requestPushSequence !== pushSequence) {
				return
			}
			if (!initialRevisionKnown) {
				const shouldRefresh = initialRevisionRequestFailed || nextRevision !== ''
				revision = nextRevision
				initialRevisionKnown = true
				initialRevisionRequestFailed = false
				if (shouldRefresh) {
					scheduleRefresh(fallbackChange())
				}
				return
			}
			if (nextRevision === revision) {
				return
			}

			revision = nextRevision
			scheduleRefresh(fallbackChange())
		})
		.catch(() => {
			if (!initialRevisionKnown) {
				initialRevisionRequestFailed = true
			}
		})
		.finally(() => {
			stateRequest = null
		})

	return stateRequest
}

function receivePush(_eventName, body) {
	const change = eventChange(body)
	pushSequence += 1
	if (change.revision !== '') {
		revision = change.revision
		initialRevisionKnown = true
	}
	if (!isVisible()) {
		deferredChange = deferredChange === null ? change : mergeChanges(deferredChange, change)
		return
	}

	scheduleRefresh(change)
}

function resumeSync() {
	if (!isVisible()) {
		return
	}

	if (deferredChange !== null) {
		const change = deferredChange
		deferredChange = null
		scheduleRefresh(change)
	}
	void checkForChanges()
}

function startPolling() {
	if (pollTimer !== null) {
		return
	}

	pollTimer = window.setInterval(() => {
		if (isVisible()) {
			void checkForChanges()
		}
	}, POLL_INTERVAL_MS)
}

export function startDataSync() {
	if (started || typeof window === 'undefined') {
		return
	}

	started = true
	window.addEventListener('focus', resumeSync)
	window.addEventListener('online', resumeSync)
	document.addEventListener('visibilitychange', resumeSync)
	startPolling()
	try {
		listen(PUSH_EVENT, receivePush)
	} catch (error) {
		console.debug('CoBudget live data synchronization is unavailable', error)
	}
	void checkForChanges()
}

export function changeTouches(change, domains) {
	const changedDomains = normalizeDomains(change?.domains)
	return domains.some(domain => changedDomains.includes(domain))
}

export function changeAffectsProject(change, projectId) {
	const changedProjectId = normalizeProjectId(change?.projectId)
	if (changedProjectId === null) {
		return true
	}

	return changedProjectId === normalizeProjectId(projectId)
}
