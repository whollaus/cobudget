let folderPickerId = 0

/** Mount app-owned Files pickers in an isolated theme scope via the public builder API. */
export async function pickFolder(picker, doc = typeof document !== 'undefined' ? document : null) {
	let container
	try {
		if (typeof picker.setContainer === 'function') {
			if (doc?.body) {
				container = doc.createElement('div')
				container.id = `cobudget-folder-picker-${++folderPickerId}`
				container.className = 'cobudget-native-dialog cobudget-folder-picker'
				doc.body.appendChild(container)
				picker.setContainer(`#${container.id}`)
			}
			picker = picker.build()
		}
		return await pickFolderWithDismiss(picker, doc)
	} finally {
		container?.remove()
	}
}

/**
 * @nextcloud/dialogs 7's builder does not expose NcDialog's
 * closeOnClickOutside prop. Forward outside presses to this picker's native
 * close button or Escape hotkey, retaining its cancellation promise, focus trap
 * and close guard. Do not dismiss other dialogs, nested menus or clicks inside
 * the picker.
 */
async function pickFolderWithDismiss(picker, doc) {
	if (!doc) return await picker.pick()
	const existing = new Set(doc.querySelectorAll('.modal-mask'))
	let closing = false
	const dismiss = event => {
		if (closing || (event.button !== undefined && event.button !== 0)) return
		const mask = [...doc.querySelectorAll('.modal-mask')].reverse().find(node => !existing.has(node) && (node.querySelector?.('.file-picker') || node.classList?.contains?.('file-picker') || !existing.has(node)))
		if (!mask) return

		// Ignore clicks inside pre-existing modals
		if ([...existing].some(old => old === event.target || old.contains?.(event.target))) return

		// Ignore clicks inside open popovers, dropdowns or tooltips
		if (event.target?.closest?.('.v-popper, .v-popper__popper, .popover, [data-popper]')) return

		const container = mask.querySelector?.('.modal-container')
		if (container) {
			if (container === event.target || container.contains?.(event.target)) return
		} else if (event.target !== mask && event.target !== mask.querySelector?.('.modal-wrapper')) {
			return
		}

		const close = mask.querySelector?.('.modal-container__close')
			|| mask.querySelector?.('.header-close')
			|| mask.querySelector?.('button[aria-label="Close"]')
			|| mask.querySelector?.('button[aria-label="Schließen"]')
		if (close) {
			if (!close.disabled) {
				closing = true
				close.click()
			}
			return
		}

		if (mask.dispatchEvent) {
			closing = true
			const EventCtor = doc.defaultView?.KeyboardEvent || globalThis.KeyboardEvent
			const escEvent = EventCtor
				? new EventCtor('keydown', { key: 'Escape', code: 'Escape', bubbles: true, cancelable: true })
				: { type: 'keydown', key: 'Escape', code: 'Escape', bubbles: true, cancelable: true }
			mask.dispatchEvent(escEvent)
		}
	}
	const containPickerWheel = event => {
		if (event.ctrlKey) return
		const mask = [...doc.querySelectorAll('.modal-mask')].reverse().find(node => !existing.has(node) && (node.querySelector?.('.file-picker') || node.classList?.contains?.('file-picker') || !existing.has(node)))
		if (!mask) return
		let el = event.target
		if (!el || (typeof Element !== 'undefined' && !(el instanceof Element)) || !mask.contains?.(el)) return
		const getStyle = doc.defaultView?.getComputedStyle || globalThis.getComputedStyle
		if (typeof getStyle !== 'function') return
		const useVertical = Math.abs(event.deltaY) >= Math.abs(event.deltaX)
		while (el && el !== mask) {
			const style = getStyle(el)
			if (useVertical) {
				if ((style.overflowY === 'auto' || style.overflowY === 'scroll') && el.scrollHeight > el.clientHeight + 1) {
					if ((event.deltaY < 0 && el.scrollTop > 0) || (event.deltaY > 0 && el.scrollTop + el.clientHeight < el.scrollHeight - 1)) {
						return
					}
				}
			} else if ((style.overflowX === 'auto' || style.overflowX === 'scroll') && el.scrollWidth > el.clientWidth + 1) {
				if ((event.deltaX < 0 && el.scrollLeft > 0) || (event.deltaX > 0 && el.scrollLeft + el.clientWidth < el.scrollWidth - 1)) {
					return
				}
			}
			el = el.parentElement
		}
		event.preventDefault?.()
	}
	doc.addEventListener('pointerdown', dismiss)
	doc.addEventListener('mousedown', dismiss)
	doc.addEventListener('wheel', containPickerWheel, { passive: false })
	try {
		return await picker.pick()
	} finally {
		doc.removeEventListener('pointerdown', dismiss)
		doc.removeEventListener('mousedown', dismiss)
		doc.removeEventListener('wheel', containPickerWheel)
	}
}

export default pickFolder
