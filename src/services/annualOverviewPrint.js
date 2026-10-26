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
export function createAnnualPrintDocument(source, { title, details = [], metadata = [], months, groups, payments }) {
	if ([...source.querySelectorAll('[role="status"]')].some(node => reportNodeVisible(node, source) && !(payments && node.closest('.report-payments')))) throw Object.assign(new Error('Report data is still loading'), { code: 'REPORT_LOADING' })
	const doc = source.ownerDocument.implementation.createHTMLDocument(title)
	doc.documentElement.lang = source.ownerDocument.documentElement.lang
	const style = doc.createElement('style')
	style.textContent = `
		@page { size: A4 landscape; margin: 10mm; }
		* { box-sizing: border-box; }
		body { margin: 0; color: #222; background: #fff; font: 9pt/1.4 Arial, sans-serif; }
		h1 { margin: 0 0 3mm; font-size: 17pt; line-height: 1.25; }
		h2 { margin: 4mm 0 2mm; font-size: 12pt; break-after: avoid; }
		.report-metadata { display: grid; grid-template-columns: max-content minmax(0, 1fr) max-content minmax(0, 1fr); gap: 1mm 4mm; margin: 0 0 4mm; padding: 0 0 3mm; border-bottom: 0.3mm solid #ccc; font-size: 8pt; }
		.report-metadata dt, .report-metadata dd { margin: 0; padding: 0; overflow-wrap: anywhere; }
		.report-metadata dt { color: #555; }
		.report-details { margin: 0 0 5mm; font-size: 9pt; overflow-wrap: anywhere; }
		table { width: 100%; table-layout: fixed; border-collapse: collapse; margin: 0 0 4mm; font: inherit; font-size: 8.5pt; }
		.report-period-table { font-size: 8pt; }
		.print-block + .print-block { break-before: page; page-break-before: always; }
		.label-column { width: 40mm; }
		.total-column { width: 24mm; }
		caption { text-align: start; font-size: 11pt; font-weight: bold; padding: 1.5mm 0; break-after: avoid; white-space: normal; }
		thead { display: table-header-group; break-inside: avoid; break-after: avoid; }
		tfoot { display: table-row-group; }
		.report-compact-table { break-inside: avoid; }
		tr { break-inside: avoid; }
		th, td { padding: 0.9mm 0.5mm; border-bottom: 0.2mm solid #ddd; text-align: end; vertical-align: middle; white-space: nowrap; }
		th:first-child, tbody th { text-align: start; white-space: normal; overflow-wrap: anywhere; }
		tbody th { font-weight: normal; }
		thead th { background: #eee; font-size: 7pt; }
		td { font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
		.report-period-table td { white-space: normal; }
		.annual-year-total, .annual-total th, .annual-total td { font-weight: bold; }
		.annual-total { background: #f3f3f3; }
		.annual-child th { padding-inline-start: 4mm; }
		.negative-balance { color: #a00; }
		.annual-month-empty { color: #888; }
		.annual-empty-message { padding: 2mm; text-align: center; }
		.report-top-payments-table th, .report-top-payments-table td { text-align: start; white-space: normal; overflow-wrap: anywhere; }
		.report-top-payments-table .numeric { text-align: end; white-space: nowrap; }
		.report-top-payments-table thead th:first-child { width: 22mm; }
		.report-top-payments-table thead th:nth-child(2) { width: 45%; }
		.report-top-payments-table thead th:last-child { width: 29mm; }
		.report-top-payments-table .report-top-date { white-space: nowrap; }
		.report-summary { display: flex; gap: 4mm; margin: 0 0 4mm; break-inside: avoid; }
		.report-summary-item { display: flex; align-items: baseline; justify-content: space-between; gap: 3mm; flex: 1; padding: 2mm 3mm; border: 0.2mm solid #ddd; border-radius: 2mm; }
		.report-summary-label { color: #555; font-size: 8.5pt; }
		.report-summary-amount { font-size: 14pt; white-space: nowrap; }
		.report-chart { margin: 0 0 5mm; break-inside: avoid; }
		.report-chart svg { display: block; width: 100%; height: 48mm; overflow: visible; }
		.report-chart-legend { display: flex; flex-wrap: wrap; gap: 2mm 5mm; margin-top: 2mm; font-size: 8pt; }
		.report-chart-legend span { display: inline-flex; align-items: center; gap: 1.5mm; }
		.report-chart-legend i { display: inline-block; width: 5mm; height: 0; }
		.report-chart-mode { margin-inline-start: 4mm; color: #555; font-size: 8pt; font-weight: 400; }
		.report-section { min-width: 0; }
		.report-forecast-table th:first-child { width: 45%; }
		.annual-details-table th { width: 64%; }
		.annual-reference-amount, .annual-comparison-value, .annual-comparison-period { display: block; }
		.annual-comparison-period, .annual-comparison-value { color: #555; font-size: 8pt; }
		.report-breakdown-table th:first-child { width: 55%; }
		.report-breakdown-table .count { text-align: center; }
		.report-breakdown-table tfoot .is-negative { color: #a00; }
		.report-payment-list th, .report-payment-list td { white-space: normal; text-align: start; }
		.report-payment-list th:first-child { width: 22mm; }
		.report-payment-list th:last-child, .report-payment-list td:last-child { width: 29mm; text-align: end; }
		.report-payment-list th:nth-child(2) { width: 30%; }
		.report-section p { margin: 2mm 0 4mm; }
		@media print { body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
	`
	doc.head.appendChild(style)
	const heading = doc.createElement('h1')
	heading.textContent = title
	doc.body.appendChild(heading)
	if (metadata.length) {
		const list = doc.createElement('dl'); list.className = 'report-metadata'
		for (const [label, value] of metadata) { const term = doc.createElement('dt'), description = doc.createElement('dd'); term.textContent = label; description.textContent = value; list.append(term, description) }
		doc.body.appendChild(list)
	} else {
		const description = doc.createElement('p'); description.className = 'report-details'; description.textContent = details.join(' · '); doc.body.appendChild(description)
	}
	const pieces = planAnnualPrintPieces(groups?.length ? groups : [{ columns: months || [] }])
	const breakBetween = pieces.length > 1
	const appendTable = (sourceTable, parent = doc.body, caption = '') => {
		const master = cleanReportTable(doc, sourceTable)
		master.querySelectorAll('.no-print, .report-top-date-short').forEach(element => element.remove())
		if (sourceTable.dataset.printTitle && master.querySelector('caption')) master.querySelector('caption').textContent = sourceTable.dataset.printTitle
		// Plain text avoids printing navigation URLs or interactive controls.
		master.querySelectorAll('a').forEach(link => link.replaceWith(doc.createTextNode(link.textContent)))
		master.querySelectorAll('[tabindex]').forEach(element => element.removeAttribute('tabindex'))
		if (caption && !master.querySelector('caption')) { const label = doc.createElement('caption'); label.textContent = caption; master.prepend(label) }
		if (!sourceTable.classList.contains('annual-table')) { parent.appendChild(master); return }
		master.classList.add('report-period-table')
		for (const piece of pieces) {
			const table = master.cloneNode(true)
			applyPrintPiece(table, piece)
			if (breakBetween) table.classList.add('print-block')
			parent.appendChild(table)
		}
	}
	for (const section of source.querySelectorAll('.report-development, .report-payment-section, .annual-table:not(.is-embedded), .report-detail-section')) {
		if (section.classList.contains('is-collapsed') || !reportNodeVisible(section, source)) continue
		if (section.classList.contains('report-development')) {
			const panel = [...section.querySelectorAll('[role="tabpanel"]')].find(node => reportNodeVisible(node, section))
			if (!panel) continue
			const heading = doc.createElement('h2'); heading.textContent = panel.getAttribute('aria-label'); doc.body.appendChild(heading)
			const mode = section.querySelector('.report-development-mode > span')?.textContent?.trim()
			if (mode) { const hint = doc.createElement('span'); hint.className = 'report-chart-mode'; hint.textContent = mode; heading.appendChild(hint) }
			appendReportChart(doc, panel)
			for (const table of panel.querySelectorAll('.report-forecast-table')) appendTable(table)
			for (const note of panel.querySelectorAll('.report-forecast-note, [role="alert"]')) if (reportNodeVisible(note, panel)) appendReportNote(doc, note)
		} else if (section.classList.contains('report-payment-section')) {
			const table = [...section.querySelectorAll('.annual-table, .report-top-payments-table')].find(node => !node.classList.contains('is-collapsed') && reportNodeVisible(node, section))
			if (table) appendTable(table)
		} else if (section.classList.contains('annual-table')) {
			appendTable(section)
		} else {
			const block = doc.createElement('section'); block.className = 'report-section'; doc.body.appendChild(block)
			const title = section.getAttribute('aria-label') || ''
			if (section.classList.contains('report-payments') && payments) { appendReportDataTable(doc, block, payments, title); for (const note of section.querySelectorAll('.report-payments-note')) appendReportNote(doc, note, block); continue }
			const choices = [...section.querySelectorAll('select')].map(select => select.selectedOptions?.[0]?.textContent || '').filter(Boolean)
			for (const table of section.querySelectorAll('table')) if (reportNodeVisible(table, section)) appendTable(table, block, [title, ...choices].filter(Boolean).join(' · '))
			for (const note of section.querySelectorAll('p, [role="alert"]')) if (reportNodeVisible(note, section) && !note.closest('table')) appendReportNote(doc, note, block)
		}
	}
	return doc
}

export function reportNodeVisible(node, root) {
	for (let current = node; current; current = current.parentElement) {
		if (current.hidden || current.style?.display === 'none' || current.style?.visibility === 'hidden') return false
		if (current === root) return true
	}
	return false
}

function cleanReportTable(doc, source) {
	const table = doc.importNode(source, true)
	if (table.querySelectorAll('tbody tr').length <= 12) table.classList.add('report-compact-table')
	for (const node of [table, ...table.querySelectorAll('*')]) { node.removeAttribute('style'); node.removeAttribute('id'); node.removeAttribute('tabindex'); node.removeAttribute('aria-sort'); node.removeAttribute('aria-selected') }
	table.querySelectorAll('.annual-comparison-icon, .sort-icon, svg, .report-payments-pagination').forEach(node => node.remove())
	// Keep the text of sortable headings and payment buttons in the printed table.
	table.querySelectorAll('button').forEach(button => button.replaceWith(doc.createTextNode(button.textContent)))
	return table
}

function appendReportNote(doc, source, parent = doc.body) {
	const note = doc.createElement('p'); note.textContent = source.textContent; parent.appendChild(note)
}

function appendReportDataTable(doc, parent, data, title) {
	const table = doc.createElement('table'); table.className = 'report-payment-list'
	if (data.rows.length <= 12) table.classList.add('report-compact-table')
	const caption = doc.createElement('caption'); caption.textContent = title; table.appendChild(caption)
	const head = doc.createElement('thead'), heading = doc.createElement('tr')
	for (const label of data.columns) { const cell = doc.createElement('th'); cell.scope = 'col'; cell.textContent = label; heading.appendChild(cell) }
	head.appendChild(heading); table.appendChild(head)
	const body = doc.createElement('tbody')
	for (const row of data.rows) { const line = doc.createElement('tr'); for (const text of row) { const cell = doc.createElement('td'); cell.textContent = text; line.appendChild(cell) } body.appendChild(line) }
	table.appendChild(body); parent.appendChild(table)
}

function appendReportChart(doc, panel) {
	const sourceSummary = panel.querySelector('.report-summary')
	if (sourceSummary) {
		const summary = doc.createElement('div'); summary.className = 'report-summary'
		for (const item of sourceSummary.querySelectorAll('.report-summary-item')) { const card = doc.createElement('div'); card.className = 'report-summary-item'; for (const selector of ['.report-summary-label', '.report-summary-amount']) { const source = item.querySelector(selector); if (source) { const text = doc.createElement(selector.endsWith('amount') ? 'strong' : 'span'); text.className = selector.slice(1); text.textContent = source.textContent; card.appendChild(text) } } summary.appendChild(card) }
		doc.body.appendChild(summary)
	}
	const sourceSvg = panel.querySelector('.annual-chart-plot > svg')
	if (!sourceSvg) return
	const figure = doc.createElement('figure'); figure.className = 'report-chart'
	const svg = doc.importNode(sourceSvg, true), originals = [sourceSvg, ...sourceSvg.querySelectorAll('*')], copies = [svg, ...svg.querySelectorAll('*')]
	const window = sourceSvg.ownerDocument.defaultView
	for (let index = 0; index < originals.length; index++) {
		const style = window?.getComputedStyle(originals[index])
		if (!style) continue
		for (const key of ['display', 'fill', 'stroke', 'stroke-width', 'stroke-dasharray', 'stroke-linecap', 'stroke-linejoin', 'vector-effect', 'opacity', 'font-size']) copies[index].style.setProperty(key, style.getPropertyValue(key))
	}
	svg.querySelectorAll('text').forEach(node => { node.style.fontFamily = 'Arial, sans-serif'; node.style.fill = '#555' })
	svg.querySelectorAll('.annual-chart-grid').forEach(node => { node.style.stroke = node.classList.contains('annual-chart-zero') ? '#888' : '#ddd' })
	svg.querySelectorAll('.annual-chart-forecast-shade').forEach(node => { node.style.fill = '#888' })
	svg.querySelectorAll('.annual-chart-active, .annual-chart-guide').forEach(node => node.remove())
	resizePrintChart(svg)
	figure.appendChild(svg)
	const legend = doc.createElement('div'); legend.className = 'report-chart-legend'
	for (const button of panel.querySelectorAll('.annual-chart-legend button:not(.is-hidden)')) { const item = doc.createElement('span'), swatch = doc.createElement('i'); const source = button.querySelector('.annual-chart-swatch'); if (source && window) { const style = window.getComputedStyle(source); swatch.style.borderTop = `2px ${style.borderTopStyle} ${style.borderTopColor}` } item.append(swatch, doc.createTextNode(button.textContent)); legend.appendChild(item) }
	figure.appendChild(legend); doc.body.appendChild(figure)
}

// Widen geometry rather than stretching text when a report is printed on mobile.
function resizePrintChart(svg) {
	const box = (svg.getAttribute('viewBox') || '').split(/\s+/).map(Number), grid = svg.querySelector('.annual-chart-grid')
	if (box.length !== 4 || !grid) return
	const left = Number(grid.getAttribute('x1')), right = Number(grid.getAttribute('x2'))
	const width = 1000, height = 184, scale = (width - left - (box[2] - right)) / (right - left)
	if (!Number.isFinite(scale) || scale <= 0) return
	const x = value => Math.round((left + (Number(value) - left) * scale) * 100) / 100
	const top = Math.min(...[...svg.querySelectorAll('.annual-chart-grid')].map(node => Number(node.getAttribute('y1'))))
	const bottom = Math.max(...[...svg.querySelectorAll('.annual-chart-grid')].map(node => Number(node.getAttribute('y1'))))
	const scaleY = (bottom - top - (box[3] - height)) / (bottom - top)
	const y = value => Math.round((top + (Number(value) - top) * scaleY) * 100) / 100
	const axes = [...svg.querySelectorAll('.annual-chart-axis')].filter(node => Number(node.getAttribute('y')) > box[3] - 18)
	for (const node of svg.querySelectorAll('*')) {
		for (const attribute of ['x', 'x1', 'x2', 'cx']) if (node.hasAttribute(attribute)) node.setAttribute(attribute, String(x(node.getAttribute(attribute))))
		for (const attribute of ['y', 'y1', 'y2', 'cy']) if (node.hasAttribute(attribute)) node.setAttribute(attribute, String(y(node.getAttribute(attribute))))
		if (node.tagName.toLowerCase() === 'rect' && node.hasAttribute('width')) node.setAttribute('width', String(Number(node.getAttribute('width')) * scale))
		if (node.tagName.toLowerCase() === 'rect' && node.hasAttribute('height')) node.setAttribute('height', String(Number(node.getAttribute('height')) * scaleY))
		if (node.tagName.toLowerCase() === 'path') node.setAttribute('d', (node.getAttribute('d') || '').replace(/([ML])\s*(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)/g, (_, command, value, valueY) => `${command}${x(value)} ${y(valueY)}`))
	}
	const example = axes[0]
	let labels = []
	try { labels = JSON.parse(svg.getAttribute('data-print-labels') || '[]') } catch { /* Older charts retain their original labels. */ }
	if (example && Array.isArray(labels) && labels.length) {
		axes.forEach(node => node.remove())
		const step = Math.max(1, Math.ceil(labels.length / 12)), indexes = new Set([0, labels.length - 1])
		for (let index = 0; index < labels.length; index += step) indexes.add(index)
		for (const index of [...indexes].sort((a, b) => a - b)) { const label = example.cloneNode(true); label.textContent = labels[index]; label.setAttribute('x', String(labels.length === 1 ? (left + x(right)) / 2 : left + index * (x(right) - left) / (labels.length - 1))); label.setAttribute('y', String(height - 10)); svg.appendChild(label) }
	}
	svg.setAttribute('viewBox', `0 0 ${width} ${height}`)
	svg.removeAttribute('data-print-labels')
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
