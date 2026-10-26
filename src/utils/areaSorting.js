import { getAppLanguage } from './formatDate'

// Keep navigation and every area picker in the same natural name order.
export function sortAreasByName(areas, language = getAppLanguage()) {
	const collator = new Intl.Collator(language, { numeric: true, sensitivity: 'base', ignorePunctuation: true })
	return [...(Array.isArray(areas) ? areas : [])].sort((a, b) =>
		collator.compare(String(a.name || ''), String(b.name || '')) || Number(a.id) - Number(b.id))
}
