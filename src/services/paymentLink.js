// Reminders are user-global; payment forms still use the active workspace.
export async function openPaymentLink(query, context) {
	try {
		const id = Number(query.openPayment)
		const workspace = Number(query.workspace)
		if (!Number.isSafeInteger(id) || id <= 0 || !Number.isSafeInteger(workspace) || workspace <= 0) throw new Error('Invalid payment link')
		if (Number(context.activeWorkspace()) !== workspace) {
			const workspaces = await context.workspaces()
			if (!context.current()) return
			if (!workspaces.some(item => Number(item.id) === workspace)) throw new Error('Workspace unavailable')
			// Reload catalogues and navigation in the target workspace before editing.
			context.switchWorkspace(workspace)
			return
		}
		const entry = await context.getEntry(id)
		if (!context.current()) return
		if (!entry || Number(entry.id) !== id) throw new Error('Payment unavailable')
		if (entry.is_settled) context.settled()
		else await context.open(entry)
	} catch (error) {
		if (context.current()) context.error(error)
	}
}
