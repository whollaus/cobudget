function isTrueFlag(value) {
	return value === true || value === 1 || value === '1'
}

function isPositiveNumber(value) {
	const normalized = Number(value)
	return Number.isFinite(normalized) && normalized > 0
}

export function isAreaSettled(entry) {
	if (!entry || typeof entry !== 'object') {
		return false
	}

	return isTrueFlag(entry.area_is_settled)
		|| isTrueFlag(entry.is_settled)
		|| isTrueFlag(entry.source_is_settled)
		|| isPositiveNumber(entry.settlement_id)
		|| isPositiveNumber(entry.settled_at)
}
