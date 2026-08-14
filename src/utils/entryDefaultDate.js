const normalizedDate = value => {
	const date = value instanceof Date ? value : new Date(value)
	return Number.isFinite(date.getTime()) ? date : null
}

export const localDateKey = value => {
	const date = normalizedDate(value)
	if (!date) {
		return ''
	}

	const year = String(date.getFullYear()).padStart(4, '0')
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${year}-${month}-${day}`
}

export const millisecondsUntilNextLocalDay = (value = new Date()) => {
	const date = normalizedDate(value)
	if (!date) {
		return 0
	}

	const nextDay = new Date(date.getFullYear(), date.getMonth(), date.getDate() + 1)
	return Math.max(0, nextDay.getTime() - date.getTime())
}

export const shouldRefreshDefaultEntryDate = ({
	isCreatingNewEntry = false,
	hasUnsavedChanges = false,
	entryDate = null,
	now = new Date(),
} = {}) => isCreatingNewEntry
	&& !hasUnsavedChanges
	&& localDateKey(entryDate) !== localDateKey(now)
