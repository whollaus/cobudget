const filenameFromDisposition = (disposition, fallback) => {
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
