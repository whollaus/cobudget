import { getCanonicalLocale } from '@nextcloud/l10n'

const CURRENCY_SYMBOL_CODES = {
	'€': 'EUR',
	$: 'USD',
	'£': 'GBP',
	'¥': 'JPY',
	'₣': 'CHF',
}

const formatterCache = new Map()

export function getAppLocale() {
	try {
		return getCanonicalLocale() || navigator.language || 'en'
	} catch (error) {
		return navigator.language || 'en'
	}
}

export function normalizeCurrency(currency) {
	const value = String(currency || '').trim()
	if (!value) {
		return { code: 'EUR', suffix: '' }
	}

	const upper = value.toUpperCase()
	if (/^[A-Z]{3}$/.test(upper)) {
		return { code: upper, suffix: '' }
	}

	if (CURRENCY_SYMBOL_CODES[value]) {
		return { code: CURRENCY_SYMBOL_CODES[value], suffix: '' }
	}

	return { code: '', suffix: value }
}

function numericValue(value) {
	const number = Number(value)
	return Number.isFinite(number) ? number : 0
}

function cachedFormatter(key, factory) {
	if (!formatterCache.has(key)) {
		formatterCache.set(key, factory())
	}
	return formatterCache.get(key)
}

function formatNumberValue(value, options, method) {
	const amount = options.absolute ? Math.abs(numericValue(value)) : numericValue(value)
	const locale = options.locale || getAppLocale()
	const minimumFractionDigits = options.minimumFractionDigits ?? 2
	const maximumFractionDigits = options.maximumFractionDigits ?? minimumFractionDigits
	const signDisplay = options.signDisplay || 'auto'
	const useGrouping = options.useGrouping ?? true
	const key = JSON.stringify(['number', locale, minimumFractionDigits, maximumFractionDigits, signDisplay, useGrouping])
	const formatter = cachedFormatter(key, () => new Intl.NumberFormat(locale, {
		minimumFractionDigits,
		maximumFractionDigits,
		signDisplay,
		useGrouping,
	}))

	return formatter[method](amount)
}

export function formatNumber(value, options = {}) {
	return formatNumberValue(value, options, 'format')
}

function formatMoneyValue(value, currency, options, method) {
	const amount = options.absolute ? Math.abs(numericValue(value)) : numericValue(value)
	const locale = options.locale || getAppLocale()
	const minimumFractionDigits = options.minimumFractionDigits ?? 2
	const maximumFractionDigits = options.maximumFractionDigits ?? minimumFractionDigits
	const signDisplay = options.signDisplay || 'auto'
	const { code, suffix } = normalizeCurrency(currency)

	if (code) {
		const key = JSON.stringify(['currency', locale, code, minimumFractionDigits, maximumFractionDigits, signDisplay])
		try {
			const formatter = cachedFormatter(key, () => new Intl.NumberFormat(locale, {
				style: 'currency',
				currency: code,
				minimumFractionDigits,
				maximumFractionDigits,
				signDisplay,
			}))
			return formatter[method](amount)
		} catch (error) {
			// Fall through to a neutral number plus suffix for custom or unsupported currency values.
		}
	}

	const number = formatNumberValue(amount, {
		locale,
		minimumFractionDigits,
		maximumFractionDigits,
		signDisplay,
	}, method)

	if (method === 'formatToParts') {
		return suffix ? [...number, { type: 'literal', value: ' ' }, { type: 'currency', value: suffix }] : number
	}
	return suffix ? `${number} ${suffix}` : number
}

export function formatMoney(value, currency = 'EUR', options = {}) {
	return formatMoneyValue(value, currency, options, 'format')
}

export function formatMoneyToParts(value, currency = 'EUR', options = {}) {
	return formatMoneyValue(value, currency, options, 'formatToParts')
}

export function formatSignedMoney(value, currency = 'EUR', options = {}) {
	return formatMoney(value, currency, {
		...options,
		signDisplay: options.signDisplay || 'always',
	})
}

export function formatMoneyFromCents(cents, currency = 'EUR', options = {}) {
	return formatMoney(numericValue(cents) / 100, currency, options)
}

export function formatInputAmount(value, options = {}) {
	return formatNumber(value, {
		...options,
		useGrouping: false,
	})
}

export function parseAmount(value) {
	if (typeof value === 'number') {
		return Number.isFinite(value) ? value : 0
	}

	let normalized = String(value || '')
		.trim()
		.replace(/\s/g, '')
		.replace(/[^\d.,+\-*/()]/g, '')

	if (!normalized) {
		return 0
	}

	const lastComma = normalized.lastIndexOf(',')
	const lastDot = normalized.lastIndexOf('.')
	const decimalSeparator = lastComma > lastDot ? ',' : '.'
	const otherSeparator = decimalSeparator === ',' ? '.' : ','

	if (lastComma !== -1 || lastDot !== -1) {
		const separatorIndex = normalized.lastIndexOf(decimalSeparator)
		const decimals = normalized.slice(separatorIndex + 1)
		const separatorLooksDecimal = decimals.length > 0 && decimals.length <= 2

		if (separatorLooksDecimal) {
			normalized = normalized
				.replace(new RegExp(`\\${otherSeparator}`, 'g'), '')
				.replace(decimalSeparator, '.')
		} else {
			normalized = normalized.replace(/[,.]/g, '')
		}
	}

	const amount = Number.parseFloat(normalized)
	return Number.isFinite(amount) ? amount : 0
}
