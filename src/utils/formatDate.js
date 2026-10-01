import { getLanguage } from '@nextcloud/l10n'

export function getAppLanguage() {
	return (getLanguage() || 'en').replaceAll('_', '-')
}

// Calendar headings are interface text; numeric dates and amounts use the separate locale.
export function formatCalendarLabel(date, options) {
	return new Intl.DateTimeFormat(getAppLanguage(), options).format(date)
}

// Calendar bucket keys are local dates, not UTC timestamps.
export function formatPeriodLabel(key, fallback = '', options = {}) {
	const match = /^(\d{4})-(\d{2})(?:-(\d{2}))?$/.exec(String(key || ''))
	if (!match) return fallback
	const year = Number(match[1])
	const month = Number(match[2]) - 1
	const day = Number(match[3] || 1)
	const date = new Date(year, month, day)
	if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) return fallback
	return formatCalendarLabel(date, {
		month: 'short',
		...(match[3] ? { day: 'numeric' } : { year: 'numeric' }),
		...options,
	})
}
