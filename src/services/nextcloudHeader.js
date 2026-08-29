export const SHOW_NEXTCLOUD_HEADER_DESKTOP_DEFAULT = true
export const SHOW_NEXTCLOUD_HEADER_MOBILE_DEFAULT = false

export const NEXTCLOUD_HEADER_DESKTOP_HIDDEN_CLASS = 'cobudget-nextcloud-header-desktop-hidden'
export const NEXTCLOUD_HEADER_MOBILE_HIDDEN_CLASS = 'cobudget-nextcloud-header-mobile-hidden'

const normalizeBoolean = (value, fallback) => typeof value === 'boolean' ? value : fallback

export const normalizeNextcloudHeaderVisibility = ({ showDesktop, showMobile } = {}) => ({
	showDesktop: normalizeBoolean(showDesktop, SHOW_NEXTCLOUD_HEADER_DESKTOP_DEFAULT),
	showMobile: normalizeBoolean(showMobile, SHOW_NEXTCLOUD_HEADER_MOBILE_DEFAULT),
})

export const applyNextcloudHeaderVisibility = preferences => {
	const normalized = normalizeNextcloudHeaderVisibility(preferences)
	const root = typeof document !== 'undefined' ? document.documentElement : null

	if (root) {
		root.classList.toggle(NEXTCLOUD_HEADER_DESKTOP_HIDDEN_CLASS, !normalized.showDesktop)
		root.classList.toggle(NEXTCLOUD_HEADER_MOBILE_HIDDEN_CLASS, !normalized.showMobile)
	}

	return normalized
}
