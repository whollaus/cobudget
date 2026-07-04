export const THEME_MODE_AUTO = 'auto'
export const THEME_MODE_LIGHT = 'light'
export const THEME_MODE_DARK = 'dark'

export const THEME_MODES = [
	THEME_MODE_AUTO,
	THEME_MODE_LIGHT,
	THEME_MODE_DARK,
]

const THEME_CLASSES = THEME_MODES.map(mode => `cobudget-theme-${mode}`)

export const normalizeThemeMode = mode => THEME_MODES.includes(mode) ? mode : THEME_MODE_AUTO

export const applyThemeMode = mode => {
	const normalized = normalizeThemeMode(mode)
	const targets = [
		typeof document !== 'undefined' ? document.documentElement : null,
		typeof document !== 'undefined' ? document.body : null,
	].filter(Boolean)

	targets.forEach(target => {
		target.classList.remove(...THEME_CLASSES)
		target.classList.add(`cobudget-theme-${normalized}`)
		target.dataset.cobudgetTheme = normalized
	})

	return normalized
}
