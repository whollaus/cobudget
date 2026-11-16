import assert from 'node:assert/strict'
import fs from 'node:fs'

const deferred = () => { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); return { promise, resolve, reject } }
const source = fs.readFileSync(new URL('../src/views/TransactionsView.vue', import.meta.url), 'utf8')
const api = { get: () => Promise.resolve({ data: {} }) }, errors = []
const methods = new Function('axios', 'generateUrl', 'normalizeDashboardMetrics', 'normalizeTagCounts', 'showRequestError', 'window', `return ({${source.slice(source.indexOf('\t\tasync fetchData()'), source.indexOf('\t\tshowCsvMenu('))}})`)(api, x => x, x => x, x => x, e => errors.push(e), {})
let measured = 0
const vm = {
 fetchRequestId: 0, initialDataReady: false, isPaymentsView: false, pagination: {},
 buildEntryQueryParams: () => ({}), fetchBudgetGoals: async () => {}, fetchReminderSummary: async () => {},
 $nextTick: async () => {}, $refs: { entryTable: { updateColumnWidths: () => measured++ } },
 $texts: { dashboard: { paymentsLoadError: () => 'Could not load payments' } },
}
vm.fetchData = methods.fetchData.bind(vm)
const payments = deferred(), budgets = deferred(), reminders = deferred()
api.get = () => payments.promise
vm.fetchBudgetGoals = () => budgets.promise
vm.fetchReminderSummary = () => reminders.promise
const first = vm.fetchData()
payments.resolve({ data: { entries: [{ id: 1 }], total: 1 } })
await Promise.resolve()
assert.equal(vm.initialDataReady, false)
assert.equal(vm.entries, undefined, 'Do not paint partial dashboard before its cards are known')
budgets.resolve(); await Promise.resolve()
assert.equal(vm.initialDataReady, false)
reminders.resolve(); await first
assert.equal(vm.initialDataReady, true)
assert.equal(vm.entries[0].id, 1)
assert.equal(measured, 1, 'Measure table columns before revealing initial data')

const old = deferred(), fresh = deferred()
api.get = () => old.promise
const older = vm.fetchData()
api.get = () => fresh.promise
const newer = vm.fetchData()
fresh.resolve({ data: { entries: [{ id: 3 }] } }); await newer
old.resolve({ data: { entries: [{ id: 2 }] } }); await older
assert.equal(vm.entries[0].id, 3, 'Late route replies cannot replace the current dashboard')
assert.equal(measured, 1, 'Refreshes do not hide existing data')
vm.initialDataReady = false
api.get = async () => { throw new Error('Offline') }
await vm.fetchData()
assert.equal(vm.initialDataReady, true, 'Failed initial requests must release the loading state')
assert.equal(errors.length, 1)

const notificationSource = fs.readFileSync(new URL('../src/services/notifications.js', import.meta.url), 'utf8')
 .replace(/^import .*$/gm, '').replaceAll('export ', '')
 .replace(/import\(\/\* webpackChunkName: "cobudget-toast" \*\/ '\.\/toast'\)/, 'loadChunk()')
let loads = 0, chunk = deferred()
const messages = [], fallbacks = []
const notify = new Function('loadChunk', 't', 'window', 'console', notificationSource + '\nreturn showToast')(
 () => { loads++; return chunk.promise }, (_, text) => text,
 { OC: { Notification: { showTemporary: m => fallbacks.push(m) } } }, { error() {} })
assert.equal(loads, 0, 'Importing notification helpers does not download native dialogs')
const a = notify('A'), b = notify('B', 'error')
assert.equal(loads, 1, 'Concurrent notifications share one chunk download')
chunk.resolve({ showToast: (...args) => messages.push(args) }); await Promise.all([a, b])
assert.deepEqual(messages.map(x => x.slice(0, 2)), [['A', 'success'], ['B', 'error']])
// A separate loader exercises a rejected chunk and successful retry.
chunk = deferred()
const retry = new Function('loadChunk', 't', 'window', 'console', notificationSource + '\nreturn showToast')(
 () => chunk.promise, (_, text) => text,
 { OC: { Notification: { showTemporary: m => fallbacks.push(m) } } }, { error() {} })
const failed = retry('Offline'); chunk.reject(new Error('Chunk unavailable')); await failed
assert.deepEqual(fallbacks, ['Offline'])
chunk = deferred(); const recovered = retry('Recovered'); chunk.resolve({ showToast: m => messages.push([m]) }); await recovered
assert.equal(messages.at(-1)[0], 'Recovered')
console.log('Startup loading passed: complete initial data, column measurement, stale requests, errors, lazy notification sharing and retry.')
