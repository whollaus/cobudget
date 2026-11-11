// Secondary columns use their content width; Description receives all remaining space.
export function fitPaymentColumns({ availableWidth, categoryWidth, partnerWidth, categoryMinimum, partnerMinimum }) {
	const available = Math.max(0, availableWidth)
	const minimums = [Math.max(0, categoryMinimum), Math.max(0, partnerMinimum)]
	const preferred = [
		Math.max(minimums[0], Math.min(240, categoryWidth)),
		Math.max(minimums[1], Math.min(220, partnerWidth)),
	]
	const budget = available - Math.min(240, available * 0.45)
	const minimumTotal = minimums[0] + minimums[1]
	const preferredTotal = preferred[0] + preferred[1]
	let widths = preferred
	if (minimumTotal > budget) {
		// Very narrow desktop containers must still retain Description and the actions.
		widths = minimums.map(width => minimumTotal ? width * budget / minimumTotal : 0)
	} else if (preferredTotal > budget) {
		const extra = preferredTotal - minimumTotal
		widths = preferred.map((width, index) => minimums[index] + (width - minimums[index]) * (budget - minimumTotal) / extra)
	}
	return { category: Math.floor(widths[0]), partner: Math.floor(widths[1]) }
}

export function fitPaymentLabels(widths, availableWidth, overflowWidth, gap = 4) {
	for (let count = widths.length; count >= 0; count--) {
		const hidden = widths.length - count
		const slots = count + Number(hidden > 0)
		const width = widths.slice(0, count).reduce((sum, value) => sum + value, 0)
			+ (hidden ? overflowWidth(hidden) : 0) + Math.max(0, slots - 1) * gap
		if (width <= availableWidth) return count
	}
	return 0
}
