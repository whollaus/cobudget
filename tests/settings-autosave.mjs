import assert from 'node:assert/strict'
import fs from 'node:fs'
import { watchEffect, nextTick } from 'vue'

const read = file => fs.readFileSync(new URL('../' + file, import.meta.url), 'utf8')
const stateSource = read('src/services/settingsState.js').replace("from 'vue'", `from '${import.meta.resolve('vue')}'`)
const { generalSettingsFields, installReactiveSettings, applySavedSettings } = await import('data:text/javascript,' + encodeURIComponent(stateSource))
const app = { config: { globalProperties: {} } }
installReactiveSettings(app)
const globals = app.config.globalProperties
let renderedIncome
const stop = watchEffect(() => { renderedIncome = globals.$enableIncomes })
globals.$enableIncomes = true
await nextTick()
assert.equal(renderedIncome, true, 'Initial global assignments remain reactive')
applySavedSettings({ enableIncomes: false, entryPageSize: 50 })
await nextTick()
assert.equal(renderedIncome, false, 'Existing global consumers update without remounting')
assert.equal(globals.$entriesPerPage, 50)
stop()

const source = read('src/views/SettingsView.vue')
let script = source.split('<script>')[1].split('</script>')[0]
const imports = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .+$/gm, '').replace('export default', 'return')
const requests = [], errors = [], events = [], stored = []
let reloads = 0
const api = { post: async (...args) => { requests.push(args); return { data: { status: 'success' } } } }
const bindings = {
	...Object.fromEntries(imports.map(name => [name, {}])),
	axios: api, generalSettingsFields, applySavedSettings,
	ENTRY_PAGE_SIZE_OPTIONS: [10, 25, 50, 100, 250],
	normalizeEntryPageSize: value => [10, 25, 50, 100, 250].includes(Number(value)) ? Number(value) : 25,
	normalizeThemeMode: value => ['auto', 'light', 'dark'].includes(value) ? value : 'auto',
	generateUrl: url => url,
	window: { location: { href: 'https://cloud.test/#/settings', reload: () => { reloads++ } }, dispatchEvent: event => events.push(event.type) },
	CustomEvent: class { constructor(type) { this.type = type } },
	sessionStorage: { setItem: (...args) => stored.push(args) },
	getAppLocale: () => 'de-AT',
}
const component = new Function(...Object.keys(bindings), script)(...Object.values(bindings))
const vm = {
	...component.data(),
	$texts: { settings: new Proxy({}, { get: (_, key) => () => key }), common: { saveError: () => 'Save failed' } },
}
for (const [name, method] of Object.entries(component.methods)) vm[name] = method.bind(vm)
for (const [name, getter] of Object.entries(component.computed)) Object.defineProperty(vm, name, { get: getter.bind(vm) })
vm.showError = message => errors.push(message)
vm.extractError = error => error.message
vm.applyLocalThemeMode = () => {}
vm.applyLocalNextcloudHeaderVisibility = () => {}
vm.currency = vm.savedCurrency = 'EUR'
vm.savedGeneralSettings = vm.generalSettingsSnapshot()
applySavedSettings(vm.savedGeneralSettings)
vm.backupRetentionCount = 0 // an unrelated unfinished export preference must not be saved
vm.notifyProjectEntries = false
assert.equal(await vm.saveGeneralSettings(), true)
assert.deepEqual(requests.at(-1), ['/apps/cobudget/api/settings', { currency: 'EUR', notify_project_entries: false }, { skipWorkspaceHeader: true }])
assert.equal(reloads, 0)
assert.equal(globals.$notifyProjectEntries, false)
assert.equal(events.at(-1), 'cobudget-settings-changed')
vm.enableReminders = false
await vm.saveGeneralSettings()
assert.deepEqual(requests.at(-1)[1], { currency: 'EUR', enable_reminders: false })
assert.equal(globals.$enableReminders, false, 'Hiding reminders propagates to existing navigation and payment components')
assert.equal(globals.$notifyReminders, true, 'Visibility does not change delivery preferences')
vm.enableReminders = true
await vm.saveGeneralSettings()
assert.equal(globals.$enableReminders, true)
const count = requests.length
await vm.saveGeneralSettings()
assert.equal(requests.length, count, 'Unchanged preferences do not write')

const post = api.post
api.post = async () => { throw new Error('Offline') }
vm.enableIncomes = false
vm.enableProjects = false
vm.themeMode = 'dark'
vm.defaultStartPage = 'projects'
assert.equal(await vm.saveGeneralSettings(), false)
assert.equal(vm.enableIncomes, true)
assert.equal(vm.enableProjects, true)
assert.equal(vm.themeMode, 'auto')
assert.equal(vm.defaultStartPage, 'personal', 'Failure also restores normalized dependent settings')
assert.equal(globals.$enableIncomes, true, 'Failure never publishes unpersisted values')
assert.equal(vm.loading, false)
assert.deepEqual(errors, ['Offline'])
api.post = post
vm.enableIncomes = false
await vm.saveGeneralSettings()
assert.equal(globals.$enableIncomes, false, 'Retry commits successfully')
assert.equal(reloads, 0)

let release
api.post = (...args) => { requests.push(args); return new Promise(resolve => { release = resolve }) }
vm.notifyProjectSettlements = false
const pending = vm.saveGeneralSettings()
const pendingCount = requests.length
assert.equal(vm.loading, true)
await vm.saveGeneralSettings()
assert.equal(requests.length, pendingCount, 'Only one general save runs at a time')
release({ data: {} }); await pending
api.post = post
vm.backupSettingsSaving = true
assert.equal(await vm.saveGeneralSettings(), false, 'Export and general writes cannot race')
vm.backupSettingsSaving = false
vm.showWorkspaceSwitcher = false
await vm.saveGeneralSettings()
assert.equal(reloads, 0, 'Changing the sidebar preference does not reload')
vm.enableWorkspaces = true
await vm.saveGeneralSettings()
assert.equal(reloads, 1, 'Only a workspace mode change reinitializes the data context')
assert.deepEqual(stored, [['cobudget-settings-return-to-workspaces', 'https://cloud.test/#/settings']])

api.get = async () => { throw new Error('Export list unavailable') }
const oldConsoleError = console.error
console.error = () => {}
try {
	await vm.fetchBackups()
	assert.equal(vm.backupsError, 'Export list unavailable')
	assert.equal(vm.backupsLoading, false)
	api.get = async () => ({ data: { backups: [{ file_name: 'export.zip' }] } })
	await vm.fetchBackups()
	assert.equal(vm.backupsError, '')
	assert.equal(vm.backups.length, 1)
	api.get = async () => { throw new Error('Talk unavailable') }
	await vm.fetchTalkSettings()
	assert.equal(vm.talkError, 'Talk unavailable')
	assert.equal(vm.talkLoaded, true)
} finally { console.error = oldConsoleError }
assert.equal(vm.formatFileSize(6451), '6,3 KB')
assert.ok(source.includes('v-else-if="backupsError"'), 'Load failure has its own state before the empty export list')
assert.ok(source.includes(':helper-text="!backupRetentionValid'), 'Invalid retention explains the accepted range')
console.log('Settings autosave passed: reactive consumers, partial writes, rollback, retry, duplicate/race guards, workspace reload and inline load errors.')

vm.notifyReminders = false
await vm.saveGeneralSettings()
assert.equal(requests.at(-1)[1].notify_reminders, false, 'Reminder bell preference saves independently')
vm.talk = { available: true, ready: false, enabled: false, reminders_enabled: false }
vm.talkLoaded = true
vm.talkError = ''
api.post = async (...args) => {
 requests.push(args)
 const { enabled, channel } = args[1]
 return { data: { ...vm.talk, ready: true, [channel === 'reminders' ? 'reminders_enabled' : 'enabled']: enabled } }
}
await vm.saveTalkSettings(true, 'reminders')
assert.deepEqual(requests.at(-1), ['/apps/cobudget/api/settings/talk', { enabled: true, channel: 'reminders' }, { skipWorkspaceHeader: true }])
assert.equal(vm.talk.reminders_enabled, true)
assert.equal(vm.talk.enabled, false, 'Reminder opt-in does not enable area messages')
await vm.saveTalkSettings(true)
await vm.saveTalkSettings(false, 'reminders')
assert.equal(vm.talk.enabled, true, 'Reminder opt-out preserves the shared-area preference')
assert.equal(vm.talk.reminders_enabled, false)
api.post = async () => { throw new Error('Talk unavailable') }
await vm.saveTalkSettings(true, 'reminders')
assert.equal(vm.talk.reminders_enabled, false, 'Failed opt-in keeps the saved model')
assert.equal(vm.talkError, 'Talk unavailable')
assert.equal(vm.talkBusy, false)
assert.ok(source.includes("talk.planned_enabled ? 'planned' : 'payments'"), 'Reconnect preserves reminder-only opt-in')
console.log('Reminder settings passed: independent saves, shared-chat opt-in/out, reconnect and failed-write preservation.')

vm.enableProjects = false
assert.deepEqual(vm.notificationRows.map(row => row.field), ['notifyReminders', 'notifyPlannedPayments'], 'Personal notification channels stay visible without areas')
vm.enableProjects = vm.enableSharedProjects = true
assert.equal(vm.notificationRows.length, 4)
assert.equal(vm.notificationRows.at(-1).channel, undefined, 'Unsupported settlement Talk is not a toggle')
vm.talkError = ''
vm.notifyPlannedPayments = false
vm.savedGeneralSettings = vm.generalSettingsSnapshot()
api.post = post
await vm.saveNotificationPreference('notifyPlannedPayments', true)
assert.equal(requests.at(-1)[1].notify_planned_payments, true)
assert.equal(vm.notifyReminders, false, 'Planned preference leaves reminder choice unchanged')
const beforeInvalid = requests.length
await vm.saveNotificationPreference('currency', 'USD')
assert.equal(requests.length, beforeInvalid, 'Only notification rows can be changed through the table')

// Calendar opt-in waits for an explicit target and never alters other channels.
vm.calendar = { available: true, enabled: false, calendar_id: '', calendars: [{ id: '12', label: 'Personal' }], sync_pending: false }
vm.calendarError = ''
const beforeCalendar = requests.length
await vm.toggleCalendarReminders(true)
assert.equal(vm.calendarSetupOpen, true)
assert.equal(vm.calendar.enabled, false)
assert.equal(requests.length, beforeCalendar, 'Opening target selection makes no server change')
await vm.toggleCalendarReminders(false)
assert.equal(vm.calendarSetupOpen, false)
api.post = async (...args) => { requests.push(args); return { data: { ...vm.calendar, enabled: args[1].enabled, calendar_id: args[1].calendarId } } }
await vm.saveCalendarSettings(true, '12')
assert.equal(vm.calendar.enabled, true)
assert.equal(vm.calendar.calendar_id, '12')
assert.deepEqual(requests.at(-1)[1], { enabled: true, calendarId: '12' })
assert.equal(requests.at(-1)[2].skipWorkspaceHeader, true)
api.post = async () => { throw new Error('Calendar offline') }
await vm.toggleCalendarReminders(false)
assert.equal(vm.calendar.enabled, true, 'Failed save preserves persisted state')
assert.ok(vm.calendarError)
api.get = async () => ({ data: { ...vm.calendar, available: false, calendars: [] } })
await vm.fetchCalendarSettings()
assert.equal(vm.calendar.available, false)
assert.equal(vm.calendarError, '')
assert.equal(vm.calendarBusy, false)
console.log('Calendar settings passed: explicit target, isolated preferences, failed save and unavailable calendar.')

// Deep-linking to master data sections waits for settings, Talk and master data to settle.
const scrolledSections = [], replacedRoutes = []
vm.$nextTick = fn => fn()
vm.$refs = {
	masterDataSettings: { scrollToSection: section => { scrolledSections.push(section); return true } },
	workspaceOptions: { scrollIntoView: () => scrolledSections.push('workspaces') },
}
vm.$route = { name: 'settings', query: { section: 'categories', keep: '1' } }
vm.$router = { replace: route => replacedRoutes.push(route) }
vm.loading = true
vm.masterDataLoaded = false
vm.talkLoaded = false
vm.consumeRequestedSection(vm.$route.query.section)
assert.deepEqual(replacedRoutes, [{ name: 'settings', query: { keep: '1' } }], 'Consuming a section removes only the one-shot section query')
assert.equal(scrolledSections.length, 0, 'Section scroll waits until loading completes')
vm.loading = false
vm.restoreWorkspacePosition()
assert.equal(scrolledSections.length, 0, 'Section scroll waits for master data and Talk')
vm.talkLoaded = true
vm.onMasterDataLoaded()
assert.deepEqual(scrolledSections, ['categories'])
vm.restoreWorkspacePosition()
assert.deepEqual(scrolledSections, ['categories'], 'Section scroll is one-shot')
vm.$route = { name: 'settings', query: { section: 'payment-partners' } }
vm.consumeRequestedSection('payment-partners')
assert.deepEqual(scrolledSections, ['categories', 'payment-partners'])
console.log('Master-data section deep links passed: one-shot query cleanup and post-load scrolling.')

// Email preferences share serialized saving and rollback without enabling other channels.
api.post = post
vm.emailAddressAvailable = false
let emailRequests = requests.length
await vm.saveEmailPreference('notifyRemindersEmail', true)
assert.equal(requests.length, emailRequests, 'No enable action without a profile address')
vm.emailAddressAvailable = true
await vm.saveEmailPreference('notifyRemindersEmail', true)
assert.equal(requests.at(-1)[1].notify_reminders_email, true)
assert.equal(vm.notifyPlannedPaymentsEmail, false)
assert.equal(vm.notifyProjectEntriesEmail, false)
assert.equal(vm.notifyProjectSettlementsEmail, false)
vm.emailAddressAvailable = false
await vm.saveEmailPreference('notifyRemindersEmail', false)
assert.equal(requests.at(-1)[1].notify_reminders_email, false, 'Opt-out remains possible after removing the address')
vm.emailAddressAvailable = true
api.post = async () => { throw new Error('Email save failed') }
await vm.saveEmailPreference('notifyRemindersEmail', true)
assert.equal(vm.notifyRemindersEmail, false, 'Failed save restores persisted opt-out')
assert.equal(errors.at(-1), 'Email save failed')
console.log('Email settings passed: default-off, address guard and independent saving.')
