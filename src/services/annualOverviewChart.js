// Work with the report's integer cents, including its deduplicated tag totals.
export function annualChartSeries(report, count, { incomeEnabled = true, cumulative = false } = {}) {
	const types = incomeEnabled ? ['income', 'expense', 'balance'] : ['expense']
	return types.map(id => {
		let running = 0
		const values = Array.from({ length: count }, (_, index) => {
			const start = Number(report.periods?.[index]?.dateFrom)
			const cutoff = Number(report.cutoff)
			if (report.cutoff !== null && Number.isFinite(start) && Number.isFinite(cutoff) && start > cutoff) return null
			const amount = Number(report.totals?.[id]?.months?.[index] || 0)
			const cents = Number.isFinite(amount) ? amount : 0
			running += cents
			return cumulative ? running : cents
		})
		return { id, values }
	})
}

export function annualChartScale(series) {
	const values = series.flatMap(item => item.values.filter(value => value !== null))
	const low = Math.min(0, ...values)
	const high = Math.max(0, ...values)
	const rawStep = (high - low || 100) / 4
	const magnitude = 10 ** Math.floor(Math.log10(rawStep))
	const fraction = rawStep / magnitude
	const step = Math.max(1, (fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10) * magnitude)
	const min = Math.floor(low / step) * step
	const max = high === low ? min + step * 4 : Math.ceil(high / step) * step
	const ticks = Array.from({ length: Math.round((max - min) / step) + 1 }, (_, index) => min + index * step)
	return { min, max, ticks }
}

// Thin labels, never financial data: even a leap year keeps every daily point.
export function annualChartTicks(count, availableWidth, spacing = 76) {
	if (count < 1) return []
	if (count === 1) return [0]
	const length = Math.min(count, Math.max(2, Math.floor(availableWidth / spacing) + 1))
	for (let size = length; size >= 2; size--) {
		const ticks = Array.from({ length: size }, (_, index) => Math.round(index * (count - 1) / (size - 1)))
		// Rounding to real data points can bring adjacent labels too close together.
		if (ticks.every((tick, index) => !index || (tick - ticks[index - 1]) * availableWidth / (count - 1) >= spacing)) return ticks
	}
	return [0]
}

export function annualChartGeometry(series, count, width, { left = 80, rightInset = 16, height = 220, scale = annualChartScale(series) } = {}) {
	const right = Math.max(left + 1, width - rightInset)
	const top = 16
	const bottom = height - 32
	const x = index => count > 1 ? left + index * (right - left) / (count - 1) : (left + right) / 2
	const y = amount => bottom - (amount - scale.min) * (bottom - top) / (scale.max - scale.min)
	const zero = y(0)
	const plotted = series.map(item => {
		const points = item.values.map((value, index) => ({ x: x(index), y: value === null ? null : y(value), value }))
		const segments = []
		let segment = []
		for (const point of points) {
			if (point.y !== null) segment.push(point)
			else if (segment.length) { segments.push(segment); segment = [] }
		}
		if (segment.length) segments.push(segment)
		const path = points => points.map((point, index) => `${index ? 'L' : 'M'}${point.x.toFixed(2)},${point.y.toFixed(2)}`).join(' ')
		return {
			...item,
			points,
			line: segments.map(path).join(' '),
			area: segments.map(points => `${path(points)} L${points.at(-1).x.toFixed(2)},${zero.toFixed(2)} L${points[0].x.toFixed(2)},${zero.toFixed(2)} Z`).join(' '),
		}
	})
	return { width, height, left, right, top, bottom, zero, series: plotted, ticks: scale.ticks.map(value => ({ value, y: y(value) })), x }
}

export function annualChartPointerIndex(clientX, rect, geometry, count) {
	if (count < 1 || !rect.width) return -1
	const local = (clientX - rect.left) * geometry.width / rect.width
	return Math.max(0, Math.min(count - 1, Math.round((local - geometry.left) * (count - 1) / (geometry.right - geometry.left))))
}
