export const ENTRY_PAGE_SIZE_OPTIONS = [10, 25, 50, 100, 250]

export const normalizeEntryPageSize = value => {
	const parsed = Number.parseInt(value, 10)
	return ENTRY_PAGE_SIZE_OPTIONS.includes(parsed) ? parsed : 25
}

export const shouldIgnorePaginationKeydown = event => {
	if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
		return true
	}

	const target = event.target
	if (!target || typeof target.closest !== 'function') {
		return false
	}

	return !!target.closest([
		'input',
		'select',
		'textarea',
		'button',
		'a',
		'[contenteditable="true"]',
		'[role="button"]',
		'.modal-backdrop',
		'.confirm-modal-backdrop',
		'.settings-modal-backdrop',
		'.workspace-modal-backdrop',
		'.v-popper__popper'
	].join(','))
}
