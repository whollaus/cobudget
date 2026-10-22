// The sidebar body clips absolutely positioned menus before the viewport does.
export const getLookupMenuLayout = (anchor, container, {
	viewportTop = 0,
	viewportBottom,
	preferredHeight,
	minimumHeight = 0,
	gap = 6,
	inset = 6,
}) => {
	const top = Math.max(container.top, viewportTop) + inset
	const bottom = Math.min(container.bottom, viewportBottom) - inset
	const above = Math.max(0, Math.min(anchor.top - gap, bottom) - top)
	const below = Math.max(0, bottom - Math.max(anchor.bottom + gap, top))
	const opensAbove = below < preferredHeight && above > below
	const maxHeight = Math.floor(opensAbove ? above : below)

	// A short visual viewport (for example above the on-screen keyboard) may
	// require overlapping the trigger to keep the creation controls accessible.
	if (maxHeight < minimumHeight) {
		return {
			opensAbove: false,
			maxHeight: Math.max(0, Math.floor(bottom - top)),
			offset: top - anchor.bottom - gap,
		}
	}

	return {
		opensAbove,
		maxHeight,
		offset: opensAbove
			? Math.max(0, anchor.top - gap - bottom)
			: Math.max(0, top - anchor.bottom - gap),
	}
}
