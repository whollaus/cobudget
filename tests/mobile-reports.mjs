import assert from 'node:assert/strict'
import fs from 'node:fs'
import * as Vue from 'vue'
import { parse } from '@vue/compiler-sfc'
import { compile } from '@vue/compiler-dom'
import { renderToString } from '@vue/server-renderer'
import { createRouter, createMemoryHistory } from 'vue-router'

const read = file => fs.readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')
let userId = 'alice'
let mobile = true
const storage = new Map()
const window = {
	matchMedia: () => ({ matches: mobile }),
	localStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) },
}
const service = new Function('getCurrentUser', 'window', `${read('src/services/mobileReports.js').replace(/^import .*$/gm, '').replaceAll('export function', 'function')}
	return { isReportView, mobileReportLocation, readMobileReportView, rememberMobileReportView }`)(() => userId ? { uid: userId } : null, window)
const { isReportView, mobileReportLocation, readMobileReportView, rememberMobileReportView } = service
assert.equal(readMobileReportView(), 'annual-overview', 'First visit defaults to annual overview')
assert.deepEqual(mobileReportLocation({ name: 'personal' }, readMobileReportView()), { name: 'annual-overview' })
assert.equal(rememberMobileReportView('analytics'), 'analytics')
assert.equal(readMobileReportView(), 'analytics', 'The next visit restores statistics from browser storage')
assert.deepEqual(mobileReportLocation({ name: 'projects' }, readMobileReportView()), { name: 'analytics' })
userId = 'bob'
assert.equal(readMobileReportView(), 'annual-overview', 'Another account starts with its own preference')
rememberMobileReportView('annual-overview')
userId = 'alice'
assert.equal(readMobileReportView(), 'analytics')
mobile = false
assert.equal(rememberMobileReportView('annual-overview'), null)
assert.equal(readMobileReportView(), 'analytics', 'Desktop navigation does not overwrite the mobile choice')
mobile = true
assert.equal(rememberMobileReportView('payments'), null)
assert.equal(readMobileReportView(), 'analytics', 'Drilldowns do not replace the preferred report')
for (const name of ['annual-overview', 'analytics']) {
	const query = { year: '2025', projectId: '12', grouping: 'paymentPartners' }
	assert.equal(isReportView(name), true, 'Both reports activate the mobile navigation item')
	assert.deepEqual(mobileReportLocation({ name, query }, 'annual-overview'), { name, query }, 'Active report navigation preserves filters')
}
assert.equal(isReportView('payments'), false)
storage.set('cobudget_mobile_report_view:alice', 'https://example.test')
assert.equal(readMobileReportView(), 'annual-overview', 'Unrecognized stored destinations are ignored')
userId = null
const count = storage.size
rememberMobileReportView('analytics')
assert.equal(storage.size, count, 'No preference is written without an authenticated user')
userId = 'alice'
Object.defineProperty(window, 'localStorage', { get() { throw Error('Storage blocked') } })
assert.equal(readMobileReportView(), 'annual-overview')
assert.equal(rememberMobileReportView('analytics'), 'analytics', 'Blocked storage still allows remembering the view in App state')

const { descriptor } = parse(read('src/components/MobileReportSwitch.vue'))
const component = new Function(descriptor.script.content.replace('export default', 'return'))()
component.render = new Function('Vue', compile(descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue)
for (const selected of ['annual-overview', 'analytics']) {
	const router = createRouter({ history: createMemoryHistory(), routes: [
		{ path: '/annual-overview', name: 'annual-overview', component: {} },
		{ path: '/analytics', name: 'analytics', component: {} },
	] })
	const app = Vue.createSSRApp(component)
	app.use(router)
	app.config.globalProperties.$texts = { annual: { title: () => 'Jahresübersicht' }, mobileNavigation: { analytics: () => 'Auswertungen', statistics: () => 'Statistik' } }
	await router.push({ name: selected, query: { year: '2025' } })
	await router.isReady()
	const html = await renderToString(app)
	assert.equal((html.match(/aria-current="page"/g) || []).length, 1, 'Only the current report is marked active')
	assert.ok(html.includes(`href="/${selected}?year=2025"`), 'The active switch preserves its route query')
	assert.ok(html.includes(`href="/${selected === 'analytics' ? 'annual-overview' : 'analytics'}"`), 'The other switch opens the other report')
	assert.ok(html.includes('Jahresübersicht') && html.includes('Statistik'))
}
console.log('Mobile report navigation passed: default view, remembered choice, account isolation, desktop behavior, blocked storage, filter preservation and both switch routes.')
