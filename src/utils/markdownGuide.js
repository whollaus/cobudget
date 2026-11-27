const SAFE_LINK_RE = /^https?:\/\/[^\s<>()]+$/i

/**
 * Parse inline markdown tokens (code spans, bold, and http/https links)
 * into plain data segments without generating raw HTML.
 */
export function parseInlineMarkdown(text) {
	if (typeof text !== 'string' || !text) {
		return []
	}

	const segments = []
	const pattern = /(`[^`]+`|\*\*[^*]+\*\*|\[[^\]]+\]\([^)]+\))/g
	let lastIndex = 0
	let match

	while ((match = pattern.exec(text)) !== null) {
		if (match.index > lastIndex) {
			segments.push({ type: 'text', text: text.slice(lastIndex, match.index) })
		}

		const token = match[0]
		if (token.startsWith('`') && token.endsWith('`')) {
			segments.push({ type: 'code', text: token.slice(1, -1) })
		} else if (token.startsWith('**') && token.endsWith('**')) {
			segments.push({ type: 'strong', text: token.slice(2, -2) })
		} else if (token.startsWith('[')) {
			const linkMatch = /^\[([^\]]+)\]\(([^)]+)\)$/.exec(token)
			const label = linkMatch?.[1] || ''
			const href = String(linkMatch?.[2] || '').trim()
			if (label && SAFE_LINK_RE.test(href)) {
				segments.push({ type: 'link', text: label, href })
			} else {
				segments.push({ type: 'text', text: label || token })
			}
		}

		lastIndex = pattern.lastIndex
	}

	if (lastIndex < text.length) {
		segments.push({ type: 'text', text: text.slice(lastIndex) })
	}

	return segments
}

function splitTableRow(line) {
	return line
		.trim()
		.replace(/^\|/, '')
		.replace(/\|$/, '')
		.split('|')
		.map(cell => cell.trim())
}

function isTableSeparatorRow(line) {
	const cells = splitTableRow(line)
	return cells.length > 0 && cells.every(cell => /^:?-{3,}:?$/.test(cell))
}

/**
 * Parse documentation markdown (headings, paragraphs, fenced code blocks,
 * and pipe tables) into safe structured blocks for Vue rendering.
 */
export default function parseMarkdownGuide(markdown) {
	if (typeof markdown !== 'string' || !markdown.trim()) {
		return []
	}

	const lines = markdown.replace(/\r\n?/g, '\n').split('\n')
	const blocks = []
	let index = 0

	while (index < lines.length) {
		const rawLine = lines[index]
		const trimmed = rawLine.trim()

		if (!trimmed) {
			index += 1
			continue
		}

		const fenceMatch = /^```([a-zA-Z0-9_-]*)\s*$/.exec(trimmed)
		if (fenceMatch) {
			const lang = fenceMatch[1] || ''
			const codeLines = []
			index += 1
			while (index < lines.length && !/^```\s*$/.test(lines[index].trim())) {
				codeLines.push(lines[index])
				index += 1
			}
			if (index < lines.length) {
				index += 1
			}
			blocks.push({
				type: 'code',
				lang,
				code: codeLines.join('\n'),
			})
			continue
		}

		const headingMatch = /^(#{1,3})\s+(.+)$/.exec(trimmed)
		if (headingMatch) {
			blocks.push({
				type: 'heading',
				level: headingMatch[1].length,
				segments: parseInlineMarkdown(headingMatch[2].trim()),
			})
			index += 1
			continue
		}

		if (
			trimmed.includes('|')
			&& index + 1 < lines.length
			&& isTableSeparatorRow(lines[index + 1])
		) {
			const headers = splitTableRow(trimmed).map(cell => parseInlineMarkdown(cell))
			const rows = []
			index += 2
			while (index < lines.length) {
				const rowLine = lines[index].trim()
				if (!rowLine || !rowLine.includes('|')) {
					break
				}
				rows.push(splitTableRow(rowLine).map(cell => parseInlineMarkdown(cell)))
				index += 1
			}
			blocks.push({
				type: 'table',
				headers,
				rows,
			})
			continue
		}

		const paragraphLines = [trimmed]
		index += 1
		while (index < lines.length) {
			const nextTrimmed = lines[index].trim()
			if (
				!nextTrimmed
				|| /^```/.test(nextTrimmed)
				|| /^#{1,3}\s+/.test(nextTrimmed)
				|| (nextTrimmed.includes('|') && index + 1 < lines.length && isTableSeparatorRow(lines[index + 1]))
			) {
				break
			}
			paragraphLines.push(nextTrimmed)
			index += 1
		}
		blocks.push({
			type: 'paragraph',
			segments: parseInlineMarkdown(paragraphLines.join(' ')),
		})
	}

	return blocks
}
