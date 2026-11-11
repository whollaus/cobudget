export const PRINT_REPORT_EVENT = 'cobudget-print-report'

export function shortcutAction(event) {
	if (event.isComposing || event.keyCode === 229 || event.getModifierState?.('AltGraph')) return null
	if (event.shiftKey) return null
	const key = String(event.key || '').toLowerCase()
	if (event.altKey && !event.ctrlKey && !event.metaKey) return key === 'n' || event.code === 'KeyN' ? 'new' : null
	if (event.altKey || event.ctrlKey === event.metaKey) return null
	return { s: 'save', n: 'new', p: 'print' }[key] || null
}

export function hasBlockingDialog(doc) {
	return [...doc.querySelectorAll('[role="dialog"], [role="alertdialog"], [aria-modal="true"]')]
		.some(element => !element.closest('[inert], [aria-hidden="true"]') && element.getClientRects().length > 0)
}

// Only consume a browser shortcut when CoBudget owns the focused surface and action.
export function createShortcutHandler({ root, available, blocked, run, doc = document }) {
	return event => {
		if (event.defaultPrevented) return
		const action = shortcutAction(event)
		if (!action || !available(action)) return
		const target = event.target
		// Native dialogs are teleported outside NcContent but still belong to the app.
		if (target !== doc.body && !root()?.contains(target) && !target?.closest?.('.cobudget-dialog-layer')) return
		event.preventDefault()
		if (event.repeat || hasBlockingDialog(doc) || blocked(action)) return
		event.stopPropagation()
		run(action)
	}
}
