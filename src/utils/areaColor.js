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

// Solid selection controls need a different palette from translucent table badges.
export function getAreaSelectionPalette(color) {
	const hex = normalizeHexColor(color)
	const channels = [0, 2, 4].map(offset => parseInt(hex.slice(offset, offset + 2), 16))
	const linear = channels.map(channel => {
		const value = channel / 255
		return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
	})
	const luminance = linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722
	const useWhite = 1.05 / (luminance + 0.05) >= (luminance + 0.05) / 0.05
	// Move the hover fill away from the text luminance, preserving its contrast.
	const hover = channels.map(channel => Math.round(useWhite ? channel * 0.9 : channel + (255 - channel) * 0.1))
	return {
		background: `rgb(${channels.join(', ')})`,
		foreground: useWhite ? '#ffffff' : '#000000',
		hover: `rgb(${hover.join(', ')})`,
	}
}
