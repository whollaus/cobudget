export const ENTRY_CLIPBOARD_EXPORT_LIMIT = 1000

export const filenameFromDisposition = (disposition, fallback) => {
	if (!disposition) {
		return fallback
	}

	const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i)
	if (utf8Match) {
		try {
			return decodeURIComponent(utf8Match[1])
		} catch (e) {
			return utf8Match[1]
		}
	}

	const plainMatch = disposition.match(/filename="?([^";]+)"?/i)
	return plainMatch ? plainMatch[1] : fallback
}

export const csvToClipboardText = (csv, delimiter = ';') => {
	const input = String(csv || '').replace(/^\uFEFF/, '')
	if (!input) {
		return ''
	}
	const rows = []
	let row = []
	let cell = ''
	let inQuotes = false
	for (let i = 0; i < input.length; i++) {
		const ch = input[i]
		if (inQuotes) {
			if (ch === '"') {
				if (input[i + 1] === '"') {
					cell += '"'
					i++
				} else {
					inQuotes = false
				}
			} else {
				cell += ch
			}
		} else if (ch === '"' && cell === '') {
			inQuotes = true
		} else if (ch === delimiter) {
			row.push(cell)
			cell = ''
		} else if (ch === '\r' || ch === '\n') {
			if (ch === '\r' && input[i + 1] === '\n') {
				i++
			}
			row.push(cell)
			rows.push(row)
			row = []
			cell = ''
		} else {
			cell += ch
		}
	}
	if (cell !== '' || row.length > 0) {
		row.push(cell)
		rows.push(row)
	}
	return rows.map(cells => cells.map(value => {
		const text = String(value ?? '')
		return /["\t\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text
	}).join('\t')).join('\r\n') + '\r\n'
}

export const downloadBlobResponse = (response, fallbackFileName) => {
	const headers = response?.headers || {}
	const contentType = headers['content-type'] || 'application/octet-stream'
	const fileName = filenameFromDisposition(headers['content-disposition'], fallbackFileName)
	const blob = response.data instanceof Blob
		? response.data
		: new Blob([response.data], { type: contentType })
	const url = URL.createObjectURL(blob)
	const link = document.createElement('a')
	link.href = url
	link.download = fileName
	document.body.appendChild(link)
	link.click()
	document.body.removeChild(link)
	URL.revokeObjectURL(url)
}
