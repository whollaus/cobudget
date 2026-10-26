import { annualFilterChips, annualFilterNameMaps } from './annualOverview'

export function reportFilterLabels(selection, context) {
	const { texts, categoryChoices = [], partnerChoices = [], hashtagChoices = [], filterChoices = {}, incomes = true, formatCents = String } = context
	const categories = annualFilterNameMaps(categoryChoices, [...(filterChoices.categoryIds || []), ...(selection.categories || [])], { incomes })
	const partners = annualFilterNameMaps(partnerChoices, [...(filterChoices.partnerIds || []), ...(selection.partners || [])], { incomes })
	return annualFilterChips(selection, {
		categoryNames: categories.names, categoryTypes: categories.types, partnerNames: partners.names, partnerTypes: partners.types,
		hashtagNames: new Map(hashtagChoices.map(tag => [Number(tag.id), tag.displayName || tag.name])),
		labelNames: { important: texts.labels?.important?.(), review: texts.labels?.review?.(), fixedCost: texts.labels?.fixedCosts?.(), childRelated: texts.labels?.children?.(), subscription: texts.labels?.subscription?.(), taxRelevant: texts.labels?.taxRelevant?.(), insurance: texts.labels?.insurance?.() },
		noCategory: texts.annual.noCategory(), noPartner: texts.annual.noPartner(), or: texts.annual.or(), atLeast: texts.annual.atLeast(), atMost: texts.annual.atMost(), formatMoney: formatCents,
	}).map(chip => chip.label)
}

export function reportExportMetadata(selection, context) {
	const { report, scopeLabel, groupingLabel, breakdownLabel, currency, texts, formatDate } = context
	const period = context.period || report.periods.at(-1)
	const metadata = [
		[texts.annual.selectedPeriod(), `${formatDate(period.dateFrom)} – ${formatDate(period.dateTo)}`],
		[texts.analytics.area(), scopeLabel],
		[texts.annual.amountScope(), [selection.entryScope === 'shared' ? texts.annual.wholeArea() : texts.annual.personalShare(), currency].filter(Boolean).join(' · ')],
		[texts.annual.dataCutoff(), formatDate(Math.min(Number(report.cutoff ?? period.dateTo), period.dateTo))],
	]
	if (groupingLabel) metadata.push([texts.annual.grouping(), groupingLabel])
	if (breakdownLabel) metadata.push([texts.annual.breakdown(), breakdownLabel])
	const filters = context.filterLabels || reportFilterLabels(selection, context)
	if (filters.length) metadata.push([texts.annual.filterSummary(), filters.join(' · ')])
	return metadata
}

export function reportPaymentPrintData(entries, { selection, texts, formatDate, formatCents, projects = [], detailType = 'all' }) {
	const columns = [
		{ label: texts.entry.tableDate(), value: entry => formatDate(entry.date) },
		{ label: texts.analytics.payment(), value: entry => entry.description || '' },
		{ label: texts.entry.tableCategory(), value: entry => entry.category_name || '–' },
		{ label: texts.entry.tablePaymentPartner(), value: entry => entry.paymentPartner || '–' },
		...(!selection.projectId ? [{ label: texts.analytics.area(), value: entry => projects.find(project => Number(project.id) === Number(entry.project_id))?.name || entry.project_name || texts.annual.withoutArea() }] : []),
		...(selection.entryScope === 'shared' ? [{ label: texts.entry.tablePaidBy(), value: entry => entry.user_display_name || entry.paid_by_display_name || entry.user_id || '–' }] : []),
		...(detailType === 'all' ? [{ label: texts.annual.groupingType(), value: entry => entry.type === 'income' ? texts.annual.income() : texts.annual.expenses() }] : []),
		{ label: texts.analytics.amount(), value: entry => {
			const cents = selection.entryScope === 'shared' ? entry.amount_cents : entry.personal_amount_cents ?? entry.amount_cents
			return formatCents(Number(cents ?? Math.round(Number(entry.amount || 0) * 100)))
		} },
	]
	return {
		columns: columns.map(column => column.label),
		rows: entries.map(entry => columns.map(column => column.value(entry))),
	}
}

// Keep money typed until serialization so negative amounts remain numbers while
// user-supplied labels and descriptions are protected against spreadsheet formulas.
export const reportExportMoney = cents => ({ cents: Number(cents) })

// These strings are already formatted by the UI. Keep currency symbols, locale
// spacing and numeric signs while still escaping formula-like user text.
const displayedNumber = /^[+\-−]?[\p{Sc}\p{L}.\u00a0\u202f ]{0,12}\p{Nd}[\p{Nd}.,'’\u066b\u066c\u00a0\u202f ]*[\p{Sc}\p{L}.%\u00a0\u202f ]{0,12}$/u
function isDisplayedNumber(text) {
	if (displayedNumber.test(text)) return true
	const comparison = /^(.*?) \((.*)\)$/.exec(text)
	return Boolean(comparison && displayedNumber.test(comparison[1]) && displayedNumber.test(comparison[2]))
}
export function reportExportDisplayedValue(text) { return isDisplayedNumber(text) ? { display: text } : text }

export function reportDisplayedTableExport(table, title) {
	if (!table) return [{ title, columns: [], rows: [] }]
	const window = table.ownerDocument.defaultView
	const visible = node => !node.hidden && node.style?.display !== 'none' && window?.getComputedStyle(node).display !== 'none'
	const text = cell => {
		const copy = cell.cloneNode(true)
		const originals = [...cell.querySelectorAll('*')], copies = [...copy.querySelectorAll('*')]
		originals.forEach((node, index) => { if (!visible(node)) copies[index].remove() })
		copy.querySelectorAll('.sort-icon, .annual-comparison-icon, svg, .material-design-icon, .no-print, .visually-hidden, .report-payments-pagination').forEach(node => node.remove())
		// Template whitespace is incidental; preserve nonbreaking currency spaces.
		return copy.textContent.replace(/[\t\r\n ]+/g, ' ').trim()
	}
	const cells = row => [...row.cells].filter(cell => !cell.matches('.col-actions, .actions-cell, .date-group-actions'))
	const columns = table.tHead?.rows[0] ? cells(table.tHead.rows[0]).map(text) : []
	const values = row => cells(row).flatMap(cell => [reportExportDisplayedValue(text(cell)), ...Array(Math.max(0, cell.colSpan - 1)).fill('')]).slice(0, columns.length || undefined)
	return [{ title,
		columns,
		rows: [...table.tBodies].flatMap(body => [...body.rows]).concat([...(table.tFoot?.rows || [])]).filter(visible).map(values),
	}]
}

export function reportAmountExportSection({ title, firstHeading, columns, rows, totalRow, totalLabel, texts }) {
	const amounts = row => [...(row.months || []), row.total].map((value, index, values) =>
		row.type !== 'all' && index < values.length - 1 && Number(row.counts?.[index] ?? (value === 0 ? 0 : 1)) === 0 ? '' : reportExportMoney(value))
	const result = []
	for (const row of rows) {
		result.push([row.label ?? row.name, ...amounts(row)])
		for (const child of row.children || []) result.push([`${row.label ?? row.name} · ${child.direct ? texts.common.mainCategory() : child.name}`, ...amounts(child)])
	}
	if (totalRow) result.push([totalLabel || texts.entry.groupTotal(totalRow.label), ...amounts(totalRow)])
	return { title, columns: [firstHeading, ...columns], rows: result }
}

export function reportPaymentExportSection(entries, options) {
	const data = reportPaymentPrintData(entries, { ...options, formatCents: reportExportMoney })
	data.columns[1] = options.texts.entry.tableDescription()
	return { title: options.title, ...data }
}

function exportCell(value) {
	if (value && typeof value === 'object' && 'display' in value) {
		const text = String(value.display)
		if (isDisplayedNumber(text)) return text
		value = text
	}
	if (value && typeof value === 'object' && 'cents' in value) {
		if (!Number.isSafeInteger(value.cents)) throw new Error('Invalid export amount')
		const absolute = Math.abs(value.cents)
		return `${value.cents < 0 ? '-' : ''}${Math.trunc(absolute / 100)},${String(absolute % 100).padStart(2, '0')}`
	}
	if (typeof value === 'number') {
		if (!Number.isFinite(value)) throw new Error('Invalid export number')
		return String(value).replace('.', ',')
	}
	const text = String(value ?? '')
	return /^(?:[\t\r\n]|[\u0000-\u0020]*[=+\-@])/.test(text) ? `'${text}` : text
}

export function buildReportExport({ metadata = [], sections, includeSectionTitles = true }, separator = ';') {
	const lines = [...metadata, ...(metadata.length ? [[]] : [])]
	for (const section of sections) {
		if (includeSectionTitles) lines.push([section.title])
		if (includeSectionTitles || section.columns?.length) lines.push(section.columns || [])
		lines.push(...(section.rows || []), [])
	}
	const encode = value => {
		const text = exportCell(value)
		return text.includes(separator) || /["\r\n\t]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text
	}
	return `${separator === ';' ? '\uFEFF' : ''}${lines.map(row => row.map(encode).join(separator)).join('\r\n')}\r\n`
}

export function reportExportNotReady() { return Object.assign(new Error('Report data is still loading or unavailable'), { code: 'REPORT_LOADING' }) }

// Detail metrics share the same displayed-cell extraction as the other tables.
export function reportDetailTableExport(root, title) {
	if (root.querySelector('[role="status"], [role="alert"]')) throw reportExportNotReady()
	const choices = [...root.querySelectorAll('select')].map(select => select.selectedOptions?.[0]?.textContent?.trim()).filter(Boolean)
	return [...root.querySelectorAll('table')].flatMap(table => reportDisplayedTableExport(table, [title, ...choices].join(' · ')))
}
