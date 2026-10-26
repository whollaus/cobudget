// Actual, known and estimated cents come from the same backend calculation.
export function reportForecastSeries(result, { incomeEnabled = true, cumulative = true, seriesIds = null } = {}) {
	const chart = result?.chart
	if (!chart) return []
	const count = chart.periods.length
	const firstFuture = chart.periods.findIndex(period => period.dateTo > result.asOf)
	return (incomeEnabled ? ['income', 'expense', 'balance'] : ['expense']).filter(id => !seriesIds || seriesIds.includes(id)).map(id => {
		const components = Object.fromEntries(['actual', 'planned', 'estimated'].map(key => {
			let running = 0
			return [key, Array.from({ length: count }, (_, index) => {
				const cents = chart[key][id][index]
				running += cents
				return cumulative ? running : cents
			})]
		}))
		const values = Array.from({ length: count }, (_, index) => components.actual[index] + components.planned[index] + components.estimated[index])
		return { id, values, components, forecastFrom: firstFuture,
			actualValues: components.actual.map((cents, index) => firstFuture < 0 || index <= firstFuture ? cents : null),
			futureValues: values.map((cents, index) => firstFuture >= 0 && index >= Math.max(0, firstFuture - 1) ? cents : null),
		}
	})
}
