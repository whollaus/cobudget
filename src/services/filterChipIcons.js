import { mdiCalendarRange, mdiShapeOutline, mdiAccountOutline, mdiFolderOutline, mdiTagOutline, mdiPound, mdiMagnify, mdiCash, mdiFilterVariant, mdiSwapVertical, mdiBellOutline, mdiPaperclip, mdiFileTreeOutline, mdiCalendarClockOutline, mdiCheckAll, mdiStarOutline, mdiClipboardCheckOutline, mdiWalletOutline, mdiAccountChildOutline, mdiSync, mdiReceiptTextCheckOutline, mdiShieldCheckOutline } from '@mdi/js'

const icons = {
	period: mdiCalendarRange, timeRange: mdiCalendarRange,
	categories: mdiShapeOutline, categoryId: mdiShapeOutline,
	partners: mdiAccountOutline, paymentPartnerId: mdiAccountOutline,
	areas: mdiFolderOutline, projectId: mdiFolderOutline, withoutArea: mdiFolderOutline,
	labels: mdiTagOutline, hashtags: mdiPound, hashtagId: mdiPound,
	search: mdiMagnify, amount: mdiCash, type: mdiSwapVertical,
	hasReminder: mdiBellOutline, hasAttachment: mdiPaperclip,
	includeSubcategories: mdiFileTreeOutline, includeFuturePayments: mdiCalendarClockOutline,
	status: mdiCheckAll, reportFilters: mdiFilterVariant,
	'label:important': mdiStarOutline, 'label:review': mdiClipboardCheckOutline,
	'label:fixedCost': mdiWalletOutline, 'label:childRelated': mdiAccountChildOutline,
	'label:subscription': mdiSync, 'label:taxRelevant': mdiReceiptTextCheckOutline,
	'label:insurance': mdiShieldCheckOutline,
}

export function filterChipIcon(key) { return icons[key] || mdiFilterVariant }
