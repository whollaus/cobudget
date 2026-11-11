import { annualRouteQuery } from './annualOverview'
import { paymentPickerFilters } from './paymentSummary'
import { PERSONAL_ENTRY_FILTER_DEFAULTS, serializePersonalEntryListRouteQuery } from './entryListRoute'

// A navigation choice starts a new view. Transfer only the chosen period;
// changing the period of the current view stays in that view's own handler.
export function titleNavigationLocation(item, selection) {
	if (item.kind === 'area') return { name: 'project-detail', params: { id: String(item.areaId) } }
	if (item.kind === 'budget') return { name: 'budget-edit', params: { id: String(item.budgetId) } }
	if (item.kind === 'payment') {
		const period = selection ? paymentPickerFilters(PERSONAL_ENTRY_FILTER_DEFAULTS, selection) : null
		const query = period ? serializePersonalEntryListRouteQuery({ filters: period }) : {}
		delete query.filter // The serializer may derive the legacy Current year shortcut.
		if (item.id) query.filter = item.id
		if (period) query.timeRange = period.timeRange // Preserve explicit All time too.
		return { name: 'personal', query }
	}
	if (item.kind === 'report' && selection) {
		const { timeRange, year, dateFrom, dateTo, periodYears, periodMonths } = selection
		return { name: item.id, query: annualRouteQuery({ timeRange, year, dateFrom, dateTo, periodYears, periodMonths }) }
	}
	return { name: item.id }
}
