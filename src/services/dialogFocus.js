const focusable = 'button, a[href], input, select, textarea, [tabindex]'
const trackers = new WeakMap()

// Capture pointer triggers too: Safari does not focus buttons on a mouse click.
// One listener per document, shared by all mounted dialog hosts.
export function trackDialogTriggers(doc) {
	let tracker = trackers.get(doc)
	if (!tracker) {
		tracker = { count: 0, context: null, time: 0 }
		tracker.remember = event => {
			tracker.stopRecovery?.()
			tracker.context = describeTrigger(event.target, doc)
			tracker.time = Date.now()
		}
		doc.addEventListener('click', tracker.remember, true)
		doc.addEventListener('keydown', tracker.remember, true)
		trackers.set(doc, tracker)
	}
	tracker.count++
	return () => {
		if (--tracker.count) return
		tracker.stopRecovery?.()
		doc.removeEventListener('click', tracker.remember, true)
		doc.removeEventListener('keydown', tracker.remember, true)
		trackers.delete(doc)
	}
}

function available(element) {
	return element?.isConnected && !element.matches(':disabled, [aria-disabled="true"]')
		&& !element.closest('[inert], [aria-hidden="true"]') && element.getClientRects().length > 0
}

function describeTrigger(interaction, doc, preferredTarget = null) {
	// A clickable table row returns to its edit/details button, not to body.
	const row = (preferredTarget || interaction || doc.activeElement)?.closest?.('tr, .settings-list-item')
	const trigger = preferredTarget || interaction?.closest?.('button, a[href], input, select, textarea')
		|| (row?.querySelector('td:last-child button') || row?.querySelector('button')) || interaction?.closest?.('[tabindex]') || doc.activeElement
	const section = trigger?.closest?.('section, .settings-block, .settings-section, .workspace-settings, .app-content')
	const root = trigger?.closest?.('.cobudget-dialog-layer') || section
	const identity = ['id', 'aria-label', 'name'].map(attribute => [attribute, trigger?.getAttribute?.(attribute)])
	const ancestors = []
	for (let node = trigger?.parentElement; node; node = node.parentElement) {
		ancestors.push({ node, top: node.scrollTop, left: node.scrollLeft })
	}
	return { trigger, section, root, identity, nextRow: row?.nextElementSibling, previousRow: row?.previousElementSibling, ancestors }
}

export function captureDialogFocus(doc, preferredTarget = null) {
	const tracker = trackers.get(doc)
	// Capture at the originating event, before no-close can remove the parent's
	// close button. Remember its stable identity for the recreated native button.
	const context = !preferredTarget && tracker && Date.now() - tracker.time < 1000
		? tracker.context : describeTrigger(null, doc, preferredTarget)
	const { trigger, section, root, identity, nextRow, previousRow, ancestors } = context

	const url = doc.location.href
	let restoredTarget = null
	const restore = (restoreScroll = true) => {
		if (doc.location.href !== url) return false
		if (restoredTarget && (available(restoredTarget) || doc.activeElement !== doc.body)) return false
		const layers = [...doc.querySelectorAll('.cobudget-dialog-layer:not([inert])')]
		const activeLayer = layers.at(-1)
		const replacement = root?.isConnected && [...root.querySelectorAll(focusable)].find(element =>
			identity.some(([attribute, value]) => value && element.getAttribute(attribute) === value))
		const candidates = [trigger, replacement, nextRow?.querySelector(focusable), previousRow?.querySelector(focusable), section?.querySelector(focusable)]
		const target = candidates.find(candidate => available(candidate) && (!activeLayer || activeLayer.contains(candidate)))
		if (!target) return false
		target.focus({ preventScroll: true })
		if (doc.activeElement !== target) return false
		restoredTarget = target
		// Keep both page and nested table/dialog scroll positions. This also
		// corrects scrolling caused by a native parent trap being reactivated.
		for (const { node, top, left } of ancestors) {
			if (restoreScroll && node.isConnected) { node.scrollTop = top; node.scrollLeft = left }
		}
		// Confirmation may close before its request finishes. If the successful
		// action later removes the focused row, recover once without stealing
		// focus after the user has already clicked/typed somewhere else.
		tracker?.stopRecovery?.()
		if (tracker && root?.isConnected && doc.defaultView?.MutationObserver) {
			const observer = new doc.defaultView.MutationObserver(() => {
				if (target.isConnected) return
				observer.disconnect()
				if (doc.activeElement === doc.body) restore(false)
			})
			observer.observe(root, { childList: true, subtree: true })
			tracker.stopRecovery = () => { observer.disconnect(); tracker.stopRecovery = null }
		}
		// NcModal's setReturnFocus callback must return false: focus was restored
		// without scrolling above; the trap must not focus it a second time.
		return false
	}
	return restore
}
