// The dashboard is account-wide like the organizer, independent of payment filters.
export function upcomingOpenReminders(rows, now = new Date()) {
	const end = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 11)
	const timestamp = now.getTime() / 1000
	return rows.filter(row => {
		const due = Number(row.remind_at)
		if (!Number.isFinite(due) || due <= 0 || due >= end.getTime() / 1000) return false
		if (row.completed_at || row.archived_at || row.effective_archived_at || row.status !== 'active') return false
		// Keep a long-open page consistent with the organizer's automatic archival.
		return row.keep_open || due + 14 * 86400 > timestamp
	}).sort((a, b) => Number(a.remind_at) - Number(b.remind_at) || Number(a.id) - Number(b.id))
}
