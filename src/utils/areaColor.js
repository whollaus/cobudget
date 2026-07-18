const DEFAULT_AREA_COLOR = '#0082c9'

function normalizeHexColor(color, fallback = DEFAULT_AREA_COLOR) {
	const normalized = String(color || '').trim().replace(/^#/, '')
	const fallbackNormalized = String(fallback || DEFAULT_AREA_COLOR).trim().replace(/^#/, '')
	const candidate = /^[0-9a-f]{3}$/i.test(normalized)
		? normalized.split('').map(character => character + character).join('')
		: normalized

	if (/^[0-9a-f]{6}$/i.test(candidate)) {
		return candidate
	}

	return /^[0-9a-f]{6}$/i.test(fallbackNormalized) ? fallbackNormalized : DEFAULT_AREA_COLOR.slice(1)
}

export function getAreaColorPalette(color, fallback = DEFAULT_AREA_COLOR) {
	const hex = normalizeHexColor(color, fallback)
	const red = parseInt(hex.slice(0, 2), 16)
	const green = parseInt(hex.slice(2, 4), 16)
	const blue = parseInt(hex.slice(4, 6), 16)
	const brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000
	const textFactor = brightness > 180 ? 0.55 : 1
	const textRed = Math.floor(red * textFactor)
	const textGreen = Math.floor(green * textFactor)
	const textBlue = Math.floor(blue * textFactor)

	return {
		base: `rgb(${red}, ${green}, ${blue})`,
		background: `rgba(${red}, ${green}, ${blue}, 0.12)`,
		foreground: `rgb(${textRed}, ${textGreen}, ${textBlue})`,
	}
}

export function getAreaColorStyle(color, fallback = DEFAULT_AREA_COLOR) {
	const palette = getAreaColorPalette(color, fallback)

	return {
		backgroundColor: palette.background,
		color: palette.foreground,
		border: `1px solid ${palette.foreground}`,
	}
}
