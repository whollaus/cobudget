// Only pointer hover waits. Keyboard focus and explicit activation stay immediate.
export function createTooltipVisibility({ setVisible, isVisible, schedule = setTimeout, cancel = clearTimeout }) {
	let showTimer, hideTimer
	let focused = false
	let disposed = false
	function clearTimers() {
		if (showTimer !== undefined) cancel(showTimer)
		if (hideTimer !== undefined) cancel(hideTimer)
		showTimer = hideTimer = undefined
	}
	function show() {
		clearTimers()
		if (!disposed) setVisible(true)
	}
	function hide() {
		clearTimers()
		if (!disposed) setVisible(false)
	}
	return {
		enter() {
			clearTimers()
			if (!disposed && !isVisible()) showTimer = schedule(show, 400)
		},
		leave() {
			clearTimers()
			if (!disposed && !focused) hideTimer = schedule(hide, 100)
		},
		focus() { focused = true; show() },
		blur() { focused = false; hide() },
		show,
		hide,
		toggle() { if (isVisible()) hide(); else show() },
		dispose() { clearTimers(); disposed = true },
	}
}
