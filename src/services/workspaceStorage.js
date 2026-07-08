const LEGACY_WORKSPACE_KEY = 'cobudget_workspace_id'

function currentUserId() {
	if (typeof window === 'undefined' || typeof window.OC?.getCurrentUser !== 'function') {
		return ''
	}

	const user = window.OC.getCurrentUser()
	if (typeof user === 'string') {
		return user
	}

	return user?.uid || user?.id || ''
}

function workspaceStorageKey() {
	const userId = currentUserId()
	return userId ? `${LEGACY_WORKSPACE_KEY}:${userId}` : LEGACY_WORKSPACE_KEY
}

function removeLegacyWorkspaceId() {
	if (typeof window === 'undefined') {
		return
	}
	if (workspaceStorageKey() !== LEGACY_WORKSPACE_KEY) {
		window.localStorage?.removeItem(LEGACY_WORKSPACE_KEY)
	}
}

export function readWorkspaceId() {
	if (typeof window === 'undefined') {
		return null
	}
	removeLegacyWorkspaceId()
	const raw = window.localStorage?.getItem(workspaceStorageKey())
	const workspaceId = parseInt(raw, 10)
	return Number.isFinite(workspaceId) && workspaceId > 0 ? workspaceId : null
}

export function writeWorkspaceId(workspaceId) {
	if (typeof window === 'undefined') {
		return
	}
	removeLegacyWorkspaceId()
	if (!workspaceId) {
		clearWorkspaceId()
		return
	}
	window.localStorage?.setItem(workspaceStorageKey(), String(workspaceId))
}

export function clearWorkspaceId() {
	if (typeof window === 'undefined') {
		return
	}
	window.localStorage?.removeItem(workspaceStorageKey())
	window.localStorage?.removeItem(LEGACY_WORKSPACE_KEY)
}
