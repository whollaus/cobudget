import { translate as t } from '@nextcloud/l10n'

const APP_ID = 'cobudget'
let toastModule
const loadToastModule = () => {
	toastModule ??= import(/* webpackChunkName: "cobudget-toast" */ './toast').catch(error => {
		toastModule = null
		throw error
	})
	return toastModule
}

export const extractError = (error, fallback = t(APP_ID, 'Action could not be completed.')) => {
	const data = error?.response?.data

	if (data && typeof data === 'object') {
		if (data.error) {
			return String(data.error)
		}
		if (data.message) {
			return String(data.message)
		}
	}

	if (typeof data === 'string' && data.trim() !== '' && !data.trim().startsWith('<')) {
		if (data.trim().startsWith('{')) {
			try {
				const parsed = JSON.parse(data)
				if (parsed && typeof parsed === 'object') {
					if (parsed.error) {
						return String(parsed.error)
					}
					if (parsed.message) {
						return String(parsed.message)
					}
				}
			} catch (e) {
				// Ignore JSON parse errors and fall back to plain string
			}
		}
		return data.trim()
	}

	if (error?.message === 'Network Error') {
		return t(APP_ID, 'Server not reachable. Please check your connection and try again.')
	}

	if (error?.response?.status) {
		return `${fallback} (HTTP ${error.response.status})`
	}

	return fallback
}

// Load translations, native toast code and its CSS only when a message is shown.
// A failed chunk must not turn a handled API error into an unhandled rejection.
export const showToast = async (message, type = 'success', options = {}) => {
	try {
		const toast = await loadToastModule()
		toast.showToast(message, type, options)
	} catch (error) {
		console.error('Could not load notification', error)
		window.OC?.Notification?.showTemporary?.(message)
	}
}

export const showRequestError = (error, fallback, context = '') => {
	if (context) {
		console.error(t(APP_ID, context), error)
	}
	showToast(extractError(error, fallback), 'error')
}
