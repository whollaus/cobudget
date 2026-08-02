const normalizedDateTime = value => {
	if (!value) {
		return null
	}

	const date = value instanceof Date ? value : new Date(value)
	const timestamp = date.getTime()
	return Number.isFinite(timestamp) ? timestamp : null
}

const normalizedId = value => {
	const id = Number(value || 0)
	return Number.isInteger(id) && id > 0 ? id : null
}

const normalizedText = value => String(value ?? '')

export const snapshotEntryForm = (entry = {}) => JSON.stringify({
	type: entry.type === 'income' ? 'income' : 'expense',
	amountDisplay: normalizedText(entry.amountDisplay).trim(),
	description: normalizedText(entry.description),
	categoryId: normalizedId(entry.categoryId),
	categoryName: normalizedText(entry.categoryName),
	paymentPartnerName: normalizedText(entry.paymentPartnerName),
	projectId: normalizedId(entry.projectId),
	userId: normalizedText(entry.userId),
	splitMode: normalizedText(entry.splitMode),
	splitUserId: normalizedText(entry.splitUserId),
	date: normalizedDateTime(entry.date),
	recurrenceInterval: normalizedText(entry.recurrenceInterval),
	recurrenceMultiplier: Number(entry.recurrenceMultiplier) || 1,
	recurrenceEndDate: normalizedDateTime(entry.recurrenceEndDate),
	isSubscription: Boolean(entry.isSubscription),
	isFixedCost: Boolean(entry.isFixedCost),
	isChildRelated: Boolean(entry.isChildRelated),
	isImportant: Boolean(entry.isImportant),
	needsReview: Boolean(entry.needsReview),
	isTaxRelevant: Boolean(entry.isTaxRelevant),
	hasReminder: Boolean(entry.hasReminder),
	reminderDate: normalizedDateTime(entry.reminderDate),
	reminderText: normalizedText(entry.reminderText),
})

export const hasEntryFormChanges = (baseline, entry, pendingAttachmentCount = 0) => {
	if (!baseline) {
		return false
	}

	return Number(pendingAttachmentCount) > 0 || snapshotEntryForm(entry) !== baseline
}
