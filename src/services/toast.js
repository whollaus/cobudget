import { showError, showInfo, showSuccess } from '@nextcloud/dialogs/toast'
import '@nextcloud/dialogs/style.css'

const defaultTimeout = 3000
const errorTimeout = 5000

let toastId = 0

// Give native notifications their app palette before they render. Use the public
// selector/onRemove API instead of modifying the returned Toastify instance.
const showThemedToast = (notify, message, timeout) => {
	const doc = typeof document !== 'undefined' ? document : null
	const container = doc?.body ? doc.createElement('div') : null
	if (container) {
		container.id = `cobudget-toast-${++toastId}`
		container.className = 'cobudget-toast-container'
		doc.body.appendChild(container)
	}
	try {
		notify(message, {
			timeout,
			...(container ? { selector: container.id, onRemove: () => container.remove() } : {}),
		})
	} catch (error) {
		container?.remove()
		throw error
	}
}

export const showToast = (message, type = 'success', options = {}) => {
	const timeout = options.timeout ?? (type === 'error' ? errorTimeout : defaultTimeout)

	if (type === 'error' && typeof showError === 'function') {
		showThemedToast(showError, message, timeout)
		return
	}

	if (type === 'success' && typeof showSuccess === 'function') {
		showThemedToast(showSuccess, message, timeout)
		return
	}

	if (typeof showInfo === 'function') {
		showThemedToast(showInfo, message, timeout)
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
