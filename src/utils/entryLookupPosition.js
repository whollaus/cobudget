import { getLookupMenuLayout } from './lookupMenuLayout'

// NcSelect's public positioning hook: keep its native menu and keyboard behavior,
// but constrain the list to the sidebar's visible area, including a mobile keyboard.
export function positionEntryLookup(menu, select, { width }) {
	const anchor = select.$refs.toggle
	const container = select.$el.closest('.modal-body')
	const viewport = window.visualViewport
	menu.classList.add('vs__dropdown-menu--floating')
	menu.style.width = width
	let frame = null
	let disposed = false
	const update = () => {
		frame = null
		if (disposed || !anchor.isConnected || !menu.isConnected) return
		const rect = anchor.getBoundingClientRect()
		const viewportTop = viewport?.offsetTop || 0
		const viewportBottom = viewportTop + (viewport?.height || window.innerHeight)
		const layout = getLookupMenuLayout(rect, container?.getBoundingClientRect() || { top: viewportTop, bottom: viewportBottom }, {
			viewportTop, viewportBottom, preferredHeight: Math.min(menu.scrollHeight, 350), minimumHeight: 72, gap: -1,
		})
		const height = Math.min(layout.maxHeight, 350)
		menu.style.maxHeight = `${height}px`
		menu.style.width = `${rect.width}px`
		const top = layout.opensAbove
			? rect.top + 1 - layout.offset - Math.min(menu.scrollHeight, height)
			: rect.bottom - 1 + layout.offset
		menu.style.left = `${rect.left + window.scrollX}px`
		menu.style.top = `${top + window.scrollY}px`
		select.$el.classList.toggle('select--drop-up', layout.opensAbove)
		menu.classList.toggle('vs__dropdown-menu--floating-placement-top', layout.opensAbove)
	}
	const schedule = () => {
		if (frame === null && !disposed) frame = window.requestAnimationFrame(update)
	}
	const observer = new ResizeObserver(schedule)
	observer.observe(anchor)
	observer.observe(menu)
	if (container) observer.observe(container)
	window.addEventListener('resize', schedule)
	window.addEventListener('scroll', schedule, true)
	viewport?.addEventListener('resize', schedule)
	viewport?.addEventListener('scroll', schedule)
	schedule()
	return () => {
		disposed = true
		if (frame !== null) window.cancelAnimationFrame(frame)
		observer.disconnect()
		window.removeEventListener('resize', schedule)
		window.removeEventListener('scroll', schedule, true)
		viewport?.removeEventListener('resize', schedule)
		viewport?.removeEventListener('scroll', schedule)
		select.$el.classList.remove('select--drop-up')
	}
}
