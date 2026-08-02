const sameEntryId = (left, right) => left !== null
	&& left !== undefined
	&& right !== null
	&& right !== undefined
	&& String(left) === String(right)

export const shouldCloseEntrySidebarForRequest = ({
	sidebar,
	entryToEdit = null,
	entryToDuplicate = null,
	requestedSelectedEntryId = null,
	selectedEntryId = null,
	editingFuture = false,
	defaultType = 'expense'
} = {}) => {
	if (!sidebar?.isOpen || entryToDuplicate) {
		return false
	}

	if (entryToEdit) {
		return !!sidebar.isEditing
			&& sameEntryId(requestedSelectedEntryId, selectedEntryId)
	}

	return !!sidebar.isCreatingNewEntry
		&& Boolean(sidebar.isFutureContext) === Boolean(editingFuture)
		&& sidebar.newEntryDefaultType === defaultType
}
