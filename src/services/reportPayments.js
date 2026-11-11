import axios from './http'
import { generateUrl } from '@nextcloud/router'
import { showRequestError, showToast } from './notifications'

// Read every matching saved payment without changing the visible list page.
export async function loadReportPayments(params, isCurrent = () => true) {
	if (!isCurrent()) throw new Error('Report selection changed')
	if (params.dateFrom != null && params.dateTo != null && Number(params.dateFrom) > Number(params.dateTo)) return []
	const entries = [], seen = new Set()
	let total = null
	while (total === null || entries.length < total) {
		const { data } = await axios.get(generateUrl('/apps/cobudget/api/entries'), { params: { ...params, limit: 500, offset: entries.length } })
		if (!isCurrent()) throw new Error('Report selection changed')
		if (total !== null && total !== Number(data.total)) throw new Error('Saved payments changed during export')
		total = Number(data.total)
		if (!Number.isSafeInteger(total) || total < 0 || !Array.isArray(data.entries) || (!data.entries.length && entries.length < total)) throw new Error('Incomplete saved-payment response')
		for (const entry of data.entries) { if (seen.has(entry.id)) throw new Error('Duplicate saved-payment page'); seen.add(entry.id); entries.push(entry) }
		if (entries.length > total) throw new Error('Saved-payment count changed')
	}
	return entries
}

// Saved-payment lists share the same edit permissions and source resolution.
export async function openReportPayment(entry, selection, texts, emit) {
	if (entry.is_settled && selection.entryScope === 'shared') { showToast(texts.areaDetail.entrySettledInfo(), 'info'); return }
	if (entry.is_locked && entry.can_delete === false) { showToast(texts.annual.readOnlyPayment(), 'info'); return }
	let editable = entry
	if (entry.is_locked && (entry.editable_entry_id || entry.source_entry_id)) {
		try { const { data } = await axios.get(generateUrl(`/apps/cobudget/api/entries/${Number(entry.editable_entry_id || entry.source_entry_id)}`)); editable = data.entry || data }
		catch (error) { showRequestError(error, texts.dashboard.paymentsLoadError()); return }
	}
	emit({ entry: editable, selectedEntryId: entry.id, projectId: editable.project_id || null, isFuture: false })
}
