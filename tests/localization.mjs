import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'
import { parse } from '@vue/compiler-sfc'
import { parse as parseTemplate, NodeTypes } from '@vue/compiler-dom'

const root = new URL('../', import.meta.url)
const read = relative => fs.readFileSync(new URL(relative, root), 'utf8')
globalThis.window = { location: new URL('https://cloud.example.test/') }
globalThis.document = { documentElement: { dataset: { locale: 'en_US' }, lang: 'en' } }
const { register, unregister, setLanguage, setLocale } = await import('@nextcloud/l10n')
const moduleUrl = relative => {
	const source = read(relative).replace(/from '(@nextcloud\/[^']+)'/g,
		(_, name) => `from '${import.meta.resolve(name)}'`)
	return `data:text/javascript;charset=utf-8,${encodeURIComponent(source)}`
}
const loadModule = relative => import(moduleUrl(relative))
const { texts } = await loadModule('src/l10n/texts.js')
const { getAppLocale, formatMoney } = await loadModule('src/utils/formatMoney.js')
const { formatCalendarLabel, formatPeriodLabel, getAppLanguage } = await loadModule('src/utils/formatDate.js')
const catalog = JSON.parse(read('l10n/de.json')).translations
const frenchCatalog = JSON.parse(read('l10n/fr.json')).translations
const spanishCatalog = JSON.parse(read('l10n/es.json')).translations

// Run the actual Nextcloud translation library, including fallback, plural and interpolation paths.
setLanguage('en')
unregister('cobudget')
assert.equal(texts.analytics.currentYear(), 'Current year')
assert.equal(texts.dashboard.incomes(), 'Total income')
assert.equal(texts.analytics.usedOfBudget('$5.00', '$10.00'), '$5.00 of $10.00')
assert.equal(texts.analytics.daysRemaining(1), '1 day remaining')
assert.equal(texts.analytics.daysRemaining(2), '2 days remaining')
assert.equal(texts.analytics.breakdownInArea('Expenses', 'Home & family', 'Categories'), 'Expenses in area Home & family by Categories')

setLanguage('de')
register('cobudget', catalog)
assert.equal(texts.analytics.currentYear(), 'Aktuelles Jahr')
assert.equal(texts.analytics.usedOfBudget('5,00 €', '10,00 €'), '5,00 € von 10,00 €')
assert.equal(texts.analytics.daysRemaining(1), '1 Tag verbleibend')
assert.equal(texts.analytics.daysRemaining(2), '2 Tage verbleibend')

setLanguage('fr')
unregister('cobudget')
register('cobudget', frenchCatalog)
assert.equal(texts.analytics.currentYear(), 'Année en cours')
assert.equal(texts.dashboard.incomes(), 'Total des revenus')
assert.equal(texts.analytics.usedOfBudget('5,00 €', '10,00 €'), '5,00 € sur 10,00 €')
for (const [count, expected] of [[0, '0 jour restant'], [1, '1 jour restant'], [2, '2 jours restants'], [1000000, '1000000 jours restants']]) {
	assert.equal(texts.analytics.daysRemaining(count), expected)
}
assert.equal(texts.analytics.breakdownInArea('Dépenses', 'Maison & famille', 'Catégories'), 'Dépenses dans l’espace Maison & famille par Catégories')
setLanguage('fr-FR')
assert.equal(texts.analytics.currentYear(), 'Année en cours')
assert.equal(texts.analytics.daysRemaining(2), '2 jours restants')

setLanguage('es')
unregister('cobudget')
register('cobudget', spanishCatalog)
assert.equal(texts.analytics.currentYear(), 'Año actual')
assert.equal(texts.dashboard.incomes(), 'Total de ingresos')
assert.equal(texts.analytics.usedOfBudget('5,00 €', '10,00 €'), '5,00 € de 10,00 €')
for (const [count, expected] of [[0, '0 días restantes'], [1, '1 día restante'], [2, '2 días restantes'], [1000000, '1000000 días restantes']]) {
	assert.equal(texts.analytics.daysRemaining(count), expected)
}
assert.equal(texts.analytics.breakdownInArea('Gastos', 'Casa & familia', 'Categorías'), 'Gastos en el área Casa & familia por Categorías')
setLanguage('es-ES')
assert.equal(texts.analytics.currentYear(), 'Año actual')
assert.equal(texts.analytics.daysRemaining(2), '2 días restantes')

// Nextcloud locale is independent of the browser locale and the interface language.
setLanguage('en')
setLocale('en_US')
assert.equal(getAppLocale(), 'en-US')
assert.equal(formatMoney(1234.5, 'USD'), '$1,234.50')
assert.equal(formatPeriodLabel('2026-10-01'), 'Oct 1')
assert.equal(formatPeriodLabel('2026-10'), 'Oct 2026')
assert.equal(formatPeriodLabel('2026-02-30', 'invalid date'), 'invalid date')
assert.equal(formatPeriodLabel('unknown', 'fallback'), 'fallback')
setLocale('de_AT')
assert.equal(getAppLocale(), 'de-AT')
assert.equal(formatMoney(1234.5, 'EUR'), new Intl.NumberFormat('de-AT', { style: 'currency', currency: 'EUR' }).format(1234.5))
assert.equal(formatPeriodLabel('2026-10-01'), 'Oct 1')
setLanguage('en_GB')
assert.equal(getAppLanguage(), 'en-GB')
assert.equal(new Intl.RelativeTimeFormat(getAppLanguage(), { numeric: 'auto' }).format(0, 'day'), 'today')
assert.equal(getAppLocale(), 'de-AT')
setLanguage('fr')
setLocale('fr_FR')
assert.equal(getAppLocale(), 'fr-FR')
assert.equal(formatMoney(1234.5, 'EUR'), new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(1234.5))
assert.equal(formatPeriodLabel('2026-10'), new Intl.DateTimeFormat('fr-FR', { month: 'short', year: 'numeric' }).format(new Date(2026, 9, 1)))
assert.equal(new Intl.RelativeTimeFormat(getAppLanguage(), { numeric: 'auto' }).format(0, 'day'), 'aujourd’hui')

// Changing interface language must translate calendar headings even with an Austrian locale.
setLocale('de_AT')
for (const [language, translations, month, summary, dateHeading, descriptionHeading] of [
	['en', {}, 'October', 'Total October', 'Date', 'Description'],
	['de', catalog, 'Oktober', 'Gesamt Oktober', 'Datum', 'Beschreibung'],
	['fr', frenchCatalog, 'octobre', 'Total octobre', 'Date', 'Description'],
	['es', spanishCatalog, 'octubre', 'Total octubre', 'Fecha', 'Descripción'],
]) {
	setLanguage(language)
	unregister('cobudget')
	register('cobudget', translations)
	const label = formatCalendarLabel(new Date(2026, 9, 1), { month: 'long' })
	assert.equal(label, month)
	assert.equal(texts.entry.groupTotal(label), summary)
	assert.equal(texts.entry.tableDate(), dateHeading)
	assert.equal(texts.entry.tableDescription(), descriptionHeading)
	assert.equal(formatPeriodLabel('2026-10'), new Intl.DateTimeFormat(language, { month: 'short', year: 'numeric' }).format(new Date(2026, 9, 1)))
	assert.equal(getAppLocale(), 'de-AT')
	assert.equal(formatMoney(1234.5, 'EUR'), new Intl.NumberFormat('de-AT', { style: 'currency', currency: 'EUR' }).format(1234.5))
}

const placeholders = value => [...value.matchAll(/\{\w+\}|%(?:\d+\$)?[sdn]/g)]
	.map(match => match[0].replace(/%\d+\$/, '%')).sort()

for (const filename of fs.readdirSync(new URL('l10n/', root)).filter(file => file.endsWith('.json'))) {
	const language = filename.slice(0, -5)
	const bundle = JSON.parse(read(`l10n/${filename}`))
	assert.deepEqual(Object.keys(bundle.translations).sort(), Object.keys(catalog).sort(), `${language}: incomplete catalog`)
	let registeredBundle
	vm.runInNewContext(read(`l10n/${language}.js`), {
		OC: { L10N: { register(app, translations, pluralForm) {
			assert.equal(app, 'cobudget')
			registeredBundle = JSON.parse(JSON.stringify({ translations, pluralForm }))
		} } },
	})
	assert.deepEqual(registeredBundle, bundle, `${language}: JS and JSON catalogs must match`)
	const pluralCount = Number(bundle.pluralForm.match(/^nplurals=(\d+);/)[1])
	const pluralRule = bundle.pluralForm.match(/plural=(.*);$/)[1]
	for (const [key, value] of Object.entries(bundle.translations)) {
		if (Array.isArray(value)) {
			const forms = key.match(/^_(.*)_::_(.*)_$/)
			assert.ok(forms, `Invalid plural key: ${key}`)
			assert.equal(value.length, pluralCount, `${language}: missing plural forms for ${key}`)
			value.forEach((form, index) => {
				assert.ok(typeof form === 'string' && form.trim(), `${language}: empty plural translation for ${key}`)
				assert.deepEqual(placeholders(form), placeholders(forms[index === 0 ? 1 : 2]), `${language}: ${key}`)
			})
		} else {
			assert.ok(typeof value === 'string' && value.trim(), `${language}: empty translation for ${key}`)
			assert.deepEqual(placeholders(value), placeholders(key), `${language} translation placeholders: ${key}`)
		}
	}
	if (language === 'fr') {
		for (const [count, index] of [[0, 0], [1, 0], [2, 2], [1000000, 1], [1000001, 2]]) {
			assert.equal(vm.runInNewContext(pluralRule, { n: count }), index, `French plural index for ${count}`)
		}
	}
	if (language === 'es') {
		for (const [count, index] of [[0, 2], [1, 0], [2, 2], [1000000, 1], [1000001, 2]]) {
			assert.equal(vm.runInNewContext(pluralRule, { n: count }), index, `Spanish plural index for ${count}`)
		}
	}
}

for (const filename of fs.readdirSync(new URL('lib/Controller/', root))) {
	if (!filename.endsWith('.php')) continue
	const source = read(`lib/Controller/${filename}`)
	for (const match of source.matchAll(/(?:->(?:t|errorResponse)\(|->loggedErrorResponse\(\$e,\s*)'([^']*)'/g)) {
		assert.ok(Object.hasOwn(catalog, match[1]), `Missing German API translation in ${filename}: ${match[1]}`)
	}
}

// Parse rendered templates so a new untranslated text node or accessibility label fails CI.
const fixedLiterals = new Set(['· IDs:', 'DELETE', 'CoBudget/Export'])
function scan(directory) {
	for (const file of fs.readdirSync(directory, { withFileTypes: true })) {
		const filename = path.join(directory, file.name)
		if (file.isDirectory()) { scan(filename); continue }
		if (!filename.endsWith('.vue')) continue
		const { descriptor } = parse(fs.readFileSync(filename, 'utf8'))
		if (!descriptor.template) continue
		function walk(node) {
			if (node.type === NodeTypes.TEXT && /[A-Za-z]{2}/.test(node.content)) {
				assert.ok(fixedLiterals.has(node.content.trim()), `Untranslated text in ${filename}: ${node.content.trim()}`)
			}
			for (const prop of node.props || []) {
				if (prop.type === NodeTypes.ATTRIBUTE && ['title', 'placeholder', 'aria-label', 'label', 'alt'].includes(prop.name) && /[A-Za-z]/.test(prop.value?.content || '')) {
					assert.ok(fixedLiterals.has(prop.value.content), `Untranslated ${prop.name} in ${filename}: ${prop.value.content}`)
				}
			}
			for (const child of node.children || []) walk(child)
		}
		walk(parseTemplate(descriptor.template.content))
		assert.doesNotMatch(descriptor.script?.content || '', /(?:toLocale(?:DateString|TimeString|String)|Intl\.(?:DateTimeFormat|RelativeTimeFormat))\(undefined[,)]|toLocaleDateString\(\)|toLocaleString\('de-AT'/, `Unconfigured locale in ${filename}`)
	}
}
scan(fileURLToPath(new URL('src/', root)))
console.log('Localization tests passed: English/German/French/Spanish, catalog completeness, plurals, interpolation, locale, placeholders and rendered templates.')
