import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

const defaultTimeout = 3000
const errorTimeout = 5000
const APP_ID = 'cobudget'

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

export const showToast = (message, type = 'success', options = {}) => {
	const timeout = options.timeout ?? (type === 'error' ? errorTimeout : defaultTimeout)

	if (type === 'error' && typeof showError === 'function') {
		showError(message, { timeout })
		return
	}

	if (type === 'success' && typeof showSuccess === 'function') {
		showSuccess(message, { timeout })
		return
	}

	if (typeof showInfo === 'function') {
		showInfo(message, { timeout })
		return
	}

	if (window.OC?.Notification?.showTemporary) {
		window.OC.Notification.showTemporary(message)
		return
	}

	if (window.OC?.Notification?.show) {
		window.OC.Notification.show(message, { type })
	}
}

export const showRequestError = (error, fallback, context = '') => {
	if (context) {
		console.error(t(APP_ID, context), error)
	}
	showToast(extractError(error, fallback), 'error')
}
