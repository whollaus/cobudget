function periodTotalIndex(report, row) {
	if (Array.isArray(report?.periods) && report.periods.length) return report.periods.length - 1
	if (Array.isArray(row?.months)) return row.months.length
	return 12
}

export function annualCellDetails({ row, index, total = false }, report, selection) {
	const yearly = index === periodTotalIndex(report, row)
	const monthly = selection?.period === 'month'
	const quarterly = selection?.period === 'quarter'
	const weekly = selection?.period === 'week'
	const daily = selection?.period === 'day'
	const dailyMonth = daily && Number(selection?.month) >= 1 && Number(selection?.month) <= 12
	const amountOf = value => yearly ? value.total : value.months[index]
	const amount = amountOf(row)
	const count = yearly ? row.totalCount : row.counts?.[index]
	const balance = row.type === 'all'
	const cutoff = new Date(report.cutoff * 1000)
	const periods = Array.isArray(report?.periods) ? report.periods : []
	const periodEnd = periods[periodTotalIndex(report, row)]?.dateTo
	const spanStart = periods[0]?.dateFrom
	const containsToday = weekly
		? spanStart !== undefined && periodEnd !== undefined && spanStart <= report.cutoff && report.cutoff < periodEnd
		: monthly || dailyMonth
			? selection.year === cutoff.getFullYear() && Number(selection.month) === cutoff.getMonth() + 1
			: selection.year === cutoff.getFullYear()
	const partialYear = !selection.includeFuturePayments && containsToday && periodEnd !== undefined && report.cutoff < periodEnd
	const monthCount = partialYear ? cutoff.getMonth() + 1 : 12
	const quarterCount = partialYear ? Math.ceil((cutoff.getMonth() + 1) / 3) : 4
	const dayCount = partialYear ? cutoff.getDate() : Math.max(periodTotalIndex(report, row), 1)
	const weekBuckets = periods.length > 1 ? periods.slice(0, -1) : []
	const completeWeekCount = weekBuckets.length || (Array.isArray(row?.months) ? row.months.length : 52)
	const startedWeeks = weekBuckets.filter(period => period?.dateFrom <= report.cutoff).length
	const weekCount = weekly ? (partialYear ? Math.max(startedWeeks, 1) : Math.max(completeWeekCount, 1)) : null
	const dayBuckets = periods.length > 1 ? periods.slice(0, -1) : []
	const startedDays = dayBuckets.filter(period => period?.dateFrom <= report.cutoff).length
	const yearDayCount = partialYear ? Math.max(startedDays, 1) : Math.max(dayBuckets.length || (Array.isArray(row?.months) ? row.months.length : 1), 1)
	const denominator = balance ? 0 : amountOf(report.totals[row.type])
	const roundCents = value => Math.sign(value) * Math.round(Math.abs(value))
	return {
		amount, count: count ?? 0, yearly, balance, monthCount, quarterCount, weekCount, partialYear,
		averagePerMonth: !monthly && !quarterly && !weekly && !daily && yearly ? roundCents(amount / monthCount) : null,
		averagePerQuarter: quarterly && yearly ? roundCents(amount / quarterCount) : null,
		averagePerWeek: weekly && yearly ? roundCents(amount / weekCount) : null,
		averagePerDay: yearly && (monthly || dailyMonth) ? roundCents(amount / dayCount) : yearly && daily ? roundCents(amount / yearDayCount) : null,
		averagePerPayment: !balance && count > 0 ? roundCents(amount / count) : null,
		share: !total && !balance && denominator > 0 ? amount / denominator : null,
		income: amountOf(report.totals.income),
		expense: amountOf(report.totals.expense),
	}
}

// Find the same category, partner, label, tag or total in a separately loaded comparison report.
export function annualDetailComparison(report, detail) {
	if (!report || detail.index === periodTotalIndex(report, detail.row)) return null
	const { row, index, total } = detail
	if (total) return report.totals[row.type === 'all' ? 'balance' : row.type]?.comparisons?.[index] ?? null
	const groups = report.groups[row.type] || []
	const match = !row.direct && groups.find(group => group.id === row.id)
	const source = match || groups.flatMap(group => group.children || []).find(child => child.id === row.id)
	return source?.comparisons?.[index] ?? null
}
