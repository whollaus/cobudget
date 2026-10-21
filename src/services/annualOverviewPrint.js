// Twelve amount columns plus the label and the total fill one landscape page.
export const ANNUAL_PRINT_COLUMNS = 12

// Describe the printed blocks. A whole-year daily overview passes one group per
// calendar month so a block never crosses into the next month.
export function planAnnualPrintPieces(groups = []) {
	const pieces = []
	let start = 0
	for (const group of groups) {
		const columns = Array.isArray(group?.columns) ? group.columns : []
		if (!columns.length) continue
		const chunks = []
		for (let index = 0; index < columns.length; index += ANNUAL_PRINT_COLUMNS) chunks.push(columns.slice(index, index + ANNUAL_PRINT_COLUMNS))
		const split = chunks.length > 1
		for (let index = 0; index < chunks.length; index++) {
			const slice = chunks[index]
			const span = slice.length > 1 ? `${slice[0]} – ${slice[slice.length - 1]}` : slice[0]
			pieces.push({
				columns: slice,
				start: start + index * ANNUAL_PRINT_COLUMNS,
				note: [group.label, split ? span : ''].filter(Boolean).join(' · '),
			})
		}
		start += columns.length
	}
	return pieces
}

export function annualPrintCellIndexes(cellCount, start, count) {
	const middle = []
	for (let index = 0; index < count; index++) middle.push(1 + start + index)
	return [0, ...middle, cellCount - 1]
}

// Keep printing outside Nextcloud's scroll containers and theme styles so all
// months and all rows remain visible, including across multiple paper pages.
export function createAnnualPrintDocument(source, { title, details, months, groups }) {
	const doc = source.ownerDocument.implementation.createHTMLDocument(title)
	doc.documentElement.lang = source.ownerDocument.documentElement.lang
	const style = doc.createElement('style')
	style.textContent = `
		@page { size: A4 landscape; margin: 10mm; }
		* { box-sizing: border-box; }
		body { margin: 0; color: #222; background: #fff; font: 7.5pt/1.35 Arial, sans-serif; }
		h1 { margin: 0 0 2mm; font-size: 15pt; }
		.report-details { margin: 0 0 5mm; font-size: 9pt; overflow-wrap: anywhere; }
		table { width: 100%; table-layout: fixed; border-collapse: collapse; margin: 0 0 5mm; font: inherit; }
		.print-block + .print-block { break-before: page; page-break-before: always; }
		.label-column { width: 32mm; }
		.total-column { width: 24mm; }
		caption { text-align: start; font-size: 11pt; font-weight: bold; padding: 2mm 0; break-after: avoid; white-space: normal; }
		thead { display: table-header-group; }
		tfoot { display: table-row-group; }
		tr { break-inside: avoid; }
		th, td { padding: 1.2mm 0.5mm; border-bottom: 0.2mm solid #ddd; text-align: end; vertical-align: middle; white-space: nowrap; }
		th:first-child, tbody th { text-align: start; white-space: normal; overflow-wrap: anywhere; }
		tbody th { font-weight: normal; }
		thead th { background: #eee; font-size: 7pt; }
		td { font-size: 7pt; font-variant-numeric: tabular-nums; overflow: hidden; }
		.annual-year-total, .annual-total th, .annual-total td { font-weight: bold; }
		.annual-total { background: #f3f3f3; }
		.annual-child th { padding-inline-start: 4mm; }
		.negative-balance { color: #a00; }
		.annual-month-empty { color: #888; }
		.annual-empty-message { padding: 5mm; text-align: center; }
		@media print { body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
	`
	doc.head.appendChild(style)
	const heading = doc.createElement('h1')
	heading.textContent = title
	const metadata = doc.createElement('p')
	metadata.className = 'report-details'
	metadata.textContent = details.join(' · ')
	doc.body.append(heading, metadata)
	const pieces = planAnnualPrintPieces(groups?.length ? groups : [{ columns: months || [] }])
	const breakBetween = pieces.length > 1
	for (const sourceTable of source.querySelectorAll('.annual-table:not(.is-collapsed)')) {
		const master = doc.importNode(sourceTable, true)
		master.querySelectorAll('.annual-amount').forEach(button => button.replaceWith(doc.createTextNode(button.textContent)))
		master.querySelectorAll('.annual-section-toggle').forEach(button => button.replaceWith(doc.createTextNode(button.querySelector('.annual-table-title')?.textContent || button.textContent)))
		master.querySelectorAll('button, .sort-icon').forEach(element => element.remove())
		// Plain text avoids printing navigation URLs or interactive controls.
		master.querySelectorAll('a').forEach(link => link.replaceWith(doc.createTextNode(link.textContent)))
		master.querySelectorAll('[tabindex]').forEach(element => element.removeAttribute('tabindex'))
		for (const piece of pieces) {
			const table = master.cloneNode(true)
			applyPrintPiece(table, piece)
			if (breakBetween) table.classList.add('print-block')
			doc.body.appendChild(table)
		}
	}
	return doc
}

function applyPrintPiece(table, piece) {
	const count = piece.columns.length
	table.querySelectorAll('colgroup, tr').forEach(row => {
		const cells = [...row.children]
		if (cells.length === 1) {
			cells[0].colSpan = count + 2
			return
		}
		const kept = annualPrintCellIndexes(cells.length, piece.start, count).map(index => cells[index]).filter(Boolean)
		row.replaceChildren(...kept)
	})
	const headers = table.querySelectorAll('thead th')
	piece.columns.forEach((label, index) => {
		if (headers[index + 1]) headers[index + 1].textContent = label
	})
	if (!piece.note) return
	const caption = table.querySelector('caption')
	if (caption) caption.textContent = `${caption.textContent} · ${piece.note}`
}

export async function printAnnualOverview(source, options) {
	const document = source.ownerDocument
	const window = document.defaultView
	document.getElementById('cobudget-annual-print-frame')?.remove()
	const report = createAnnualPrintDocument(source, options)
	const frame = document.createElement('iframe')
	frame.id = 'cobudget-annual-print-frame'
	frame.title = options.title
	frame.setAttribute('aria-hidden', 'true')
	Object.assign(frame.style, { position: 'fixed', left: '-10000px', top: '0', width: '277mm', height: '1600px', border: '0', pointerEvents: 'none' })
	document.body.appendChild(frame)
	try {
		const printDocument = frame.contentDocument
		printDocument.open()
		printDocument.write('<!doctype html><html><head></head><body></body></html>')
		printDocument.close()
		printDocument.replaceChild(printDocument.importNode(report.documentElement, true), printDocument.documentElement)
		await printDocument.fonts?.ready
		await new Promise(resolve => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)))
		frame.style.height = `${Math.max(printDocument.body.scrollHeight + 80, 1600)}px`
		frame.contentWindow.addEventListener('afterprint', () => window.setTimeout(() => frame.remove(), 0), { once: true })
		frame.contentWindow.focus()
		frame.contentWindow.print()
		// Browsers without afterprint retain one offscreen frame until the next print.
	} catch (error) {
		frame.remove()
		throw error
	}
}
