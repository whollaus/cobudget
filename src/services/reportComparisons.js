const roundCents = value => Math.sign(value) * Math.round(Math.abs(value))

// This is the average already shown in the detail metrics, based on all started chart buckets.
// It remains useful in the first year and does not require a payment in every bucket.
export function reportSelectionAverage(report, type, breakdown) {
	const row = report?.totals?.[type === 'all' ? 'balance' : type]
	const periods = report?.periods?.slice(0, -1) || []
	const cutoff = Number(report?.cutoff)
	const started = periods.map((period, index) => index < (row?.months?.length || 0) && (report.cutoff === null || !Number.isFinite(cutoff) || period.dateFrom <= cutoff))
	const count = started.filter(Boolean).length
	if (!count || row.totalCount === 0) return null
	const total = row.months.reduce((amount, value, index) => amount + (started[index] ? value : 0), 0)
	const fullAverage = roundCents(total / count)
	const values = started.map(eligible => eligible ? fullAverage : null)
	return {
		id: 'average-selection', kind: 'selection-average', available: true,
		unit: ({ year: 'month', month: 'day', quarter: 'quarter', week: 'week', day: 'day' })[breakdown] || 'month',
		fullAverage, count, values, periods,
	}
}

// All annual means use the same exact subject total. Empty and current buckets
// count just as they do in the existing per-unit annual detail metric.
export function reportAnnualAverages(report, type, defaultUnit) {
	const range = report?.periods?.at(-1)
	if (!range) return []
	const from = calendarDate(range.dateFrom), to = calendarDate(range.dateTo)
	if (from.getMonth() !== 0 || from.getDate() !== 1 || to.getMonth() !== 11 || to.getDate() !== 31 || from.getFullYear() !== to.getFullYear()) return []
	const row = report.totals[type === 'all' ? 'balance' : type]
	const cutoff = report.cutoff === null || !Number.isFinite(report.cutoff) ? range.dateTo : Math.min(range.dateTo, report.cutoff)
	if (!row || row.totalCount === 0 || cutoff < range.dateFrom) return []
	const units = ['quarter', 'month', 'week', ...(defaultUnit === 'day' ? ['day'] : [])]
	const means = units.map(unit => {
		let count = 0
		for (let cursor = new Date(from); cursor.getTime() / 1000 <= cutoff;) {
			count++
			if (unit === 'month' || unit === 'quarter') cursor = new Date(cursor.getFullYear(), cursor.getMonth() + (unit === 'quarter' ? 3 : 1), 1)
			else cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + (unit === 'week' ? (cursor.getDay() === 1 ? 7 : (8 - cursor.getDay()) % 7) : 1))
		}
		return { id: unit === defaultUnit ? 'average-selection' : `average-year-${unit}`, kind: 'selection-average', available: true,
			unit, fullAverage: roundCents(row.total / count), count, referenceUnit: 'year', referenceYear: from.getFullYear(),
			referenceFrom: dateKey(from), referenceTo: dateKey(to), values: [], periods: [] }
	})
	return means.sort((a, b) => Number(b.id === 'average-selection') - Number(a.id === 'average-selection'))
}

function calendarDate(timestamp) { return new Date(timestamp * 1000) }
function dateKey(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` }
function calendarDay(date) { return Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()) / 86400000 }

// The opened overview bucket determines the comparison unit, independently of the finer chart.
export function reportAnnualAverageBasis(detail, report, selection) {
	const period = report.periods[detail.index]
	if (!period) return null
	const from = calendarDate(period.dateFrom), to = calendarDate(period.dateTo)
	const next = new Date(to.getFullYear(), to.getMonth(), to.getDate() + 1)
	const whole = detail.index === report.periods.length - 1
	let unit = ({ year: 'month', quarter: 'quarter', week: 'week', day: 'day', month: 'day' })[selection.period] || 'month'
	if (whole) {
		// A whole year or free range retains its existing average per displayed bucket.
		if (from.getMonth() === 0 && from.getDate() === 1 && dateKey(next) === `${from.getFullYear() + 1}-01-01`) return null
		if (from.getDate() === 1 && dateKey(next) === dateKey(new Date(from.getFullYear(), from.getMonth() + 1, 1))) unit = 'month'
		else if (from.getDate() === 1 && from.getMonth() % 3 === 0 && dateKey(next) === dateKey(new Date(from.getFullYear(), from.getMonth() + 3, 1))) unit = 'quarter'
		else if (from.getDay() === 1 && calendarDay(next) - calendarDay(from) === 7) unit = 'week'
		else if (dateKey(from) === dateKey(to)) unit = 'day'
		else return null
	}
	const year = from.getFullYear()
	return { year, unit, period: unit === 'month' ? 'year' : unit, dateFrom: `${year}-01-01`, dateTo: `${year}-12-31` }
}

export function reportMatchesAverageBasis(report, selection, basis) {
	const range = report.periods.at(-1)
	return selection.period === basis.period && range
		&& dateKey(calendarDate(range.dateFrom)) === basis.dateFrom
		&& dateKey(calendarDate(range.dateTo)) === basis.dateTo
}

// Keep the same reference amount at every included position, regardless of the chart's finer resolution.
export function reportAverageForPeriod(average, report, selectedPeriod) {
	if (!average || !selectedPeriod) return null
	const periods = report.periods.slice(0, -1)
	const values = periods.map(period => {
		if (period.dateTo < selectedPeriod.dateFrom || period.dateFrom > selectedPeriod.dateTo || (report.cutoff !== null && Number.isFinite(report.cutoff) && period.dateFrom > report.cutoff)) return null
		return average.fullAverage
	})
	return { ...average, values, periods }
}

export function reportComparisonLabel(value, texts, format = (date, options) => new Intl.DateTimeFormat(undefined, options).format(date)) {
	if (['selection-average', 'reference-average'].includes(value.kind)) {
		const label = ({ month: texts.annual.averagePerMonth, quarter: texts.annual.averagePerQuarter, week: texts.annual.averagePerWeek, day: texts.annual.averagePerDay }[value.unit])()
		let reference = ''
		if (value.referenceUnit === 'year') reference = String(value.referenceYear)
		if (value.referenceUnit === 'month') reference = format(new Date(`${value.referenceFrom}T12:00:00`), { month: 'long', year: 'numeric' })
		if (value.referenceUnit === 'quarter') reference = `${texts.annual.quarter(value.referenceNumber)} ${value.referenceYear}`
		if (value.referenceUnit === 'week') reference = `${texts.annual.week(value.referenceNumber)} ${value.referenceYear}`
		return reference ? `${label} · ${reference}` : label
	}
	if (value.id === 'year') return texts.annual.comparisonLastYear()
	return ({ month: texts.annual.comparisonPreviousMonth, year: texts.annual.comparisonPreviousYear, quarter: texts.annual.comparisonPreviousQuarter, week: texts.annual.comparisonPreviousWeek, day: texts.annual.comparisonPreviousDay, range: texts.annual.comparisonPreviousRange }[value.unit])()
}

export function reportComparisonRange(value, format) {
	if (!value.previousFrom || !value.previousTo) return ''
	const date = day => format(new Date(`${day}T12:00:00`), { day: 'numeric', month: 'short', year: 'numeric' })
	return value.previousFrom === value.previousTo ? date(value.previousFrom) : `${date(value.previousFrom)} – ${date(value.previousTo)}`
}

export function reportComparisonSeries(comparisons, selected, count, cumulative, texts, format) {
	return comparisons.filter(value => value.available && ['period', 'selection-average', 'reference-average'].includes(value.kind) && selected.includes(value.id)).map(value => {
		let running = 0
		return {
			id: `comparison-${value.id}`,
			label: reportComparisonLabel(value, texts, format),
			comparison: true,
			values: Array.from({ length: count }, (_, index) => {
				const amount = value.values[index]
				if (amount === null || amount === undefined) return null
				if (value.kind !== 'period') return value.fullAverage
				running += amount
				return cumulative ? running : amount
			}),
			titles: Array.from({ length: count }, (_, index) => {
				const period = value.periods[index]
				if (!period || value.values[index] === null) return texts.annual.comparisonNoData()
				if (value.kind !== 'period') return ''
				const date = timestamp => format(new Date(timestamp * 1000), { day: 'numeric', month: 'short', year: 'numeric' })
				const to = Math.min(period.dateTo, new Date(`${value.previousTo}T23:59:59`).getTime() / 1000)
				return `${date(period.dateFrom)} – ${date(to)}`
			}),
		}
	})
}
