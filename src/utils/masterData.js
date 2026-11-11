const normalized = value => String(value || '').trim().replace(/\s+/gu, ' ').toLowerCase()
export const recordFlag = value => value === true || value === 1 || value === '1'

/** Group identities for display only; retain the real record of every scope. */
export function groupMasterData(scopes, kind) {
	const groups = new Map()
	for (const scope of scopes) {
		const records = scope[kind] || []
		const byId = new Map(records.map(record => [Number(record.id), record]))
		const path = (record, seen = new Set()) => {
			if (!record || seen.has(Number(record.id))) return []
			seen.add(Number(record.id))
			return [...path(byId.get(Number(record.parent_category_id)), seen), normalized(record.name)]
		}
		for (const record of records) {
			const parts = kind === 'categories' ? path(record) : [normalized(record.name)]
			const base = JSON.stringify([record.type, ...parts])
			// Legacy duplicates within one scope remain individually reachable.
			let key = base
			let duplicate = 1
			while (groups.get(key)?.records[scope.key]) key = `${base}:${++duplicate}`
			if (!groups.has(key)) groups.set(key, {
				key, name: record.name, type: record.type, icon: record.icon || 'Shape',
				parentKey: parts.length > 1 ? JSON.stringify([record.type, ...parts.slice(0, -1)]) : '',
				depth: parts.length - 1, records: {},
			})
			groups.get(key).records[scope.key] = record
		}
	}
	const sorted = [...groups.values()].sort((a, b) => a.name.localeCompare(b.name))
	const result = []
	const visit = row => {
		result.push(row)
		for (const child of sorted.filter(item => item.parentKey === row.key)) visit(child)
	}
	for (const row of sorted.filter(item => !item.parentKey || !groups.has(item.parentKey))) visit(row)
	return result
}
