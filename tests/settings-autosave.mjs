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
	axios: api, generalSettingsFields, applySavedSettings, applyThemeMode: value => value,
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
	$texts: { shortcuts: { title: () => 'Keyboard shortcuts' }, settings: new Proxy({}, { get: (_, key) => () => key }), common: { saveError: () => 'Save failed' } },
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

let confirmed = false
vm.$refs = { apiTokenSettings: { runWithPasswordConfirmation: async action => {
	if (confirmed) await action()
	return { cancelled: !confirmed }
} } }
vm.apiAccessAdminEnabled = false
await vm.toggleApiAccess(true)
assert.equal(vm.enableApiAccess, false, 'Admin-disabled API access cannot be enabled by user')
assert.equal(requests.length, count, 'Admin-disabled API access makes no enable request')
vm.apiAccessAdminEnabled = true
await vm.toggleApiAccess(true)
assert.equal(vm.enableApiAccess, false, 'Cancelling confirmation keeps API access off')
assert.equal(requests.length, count, 'Cancelling does not persist a change')
confirmed = true
await vm.toggleApiAccess(true)
assert.deepEqual(requests.at(-1), ['/apps/cobudget/api/settings/api-access/enable', {}, { skipWorkspaceHeader: true }])
assert.equal(vm.enableApiAccess, true)
assert.equal(vm.savedGeneralSettings.enableApiAccess, true)
confirmed = false
await vm.toggleApiAccess(false)
assert.equal(vm.enableApiAccess, false, 'Emergency disable does not require password confirmation')
assert.deepEqual(requests.at(-1)[1], { currency: 'EUR', enable_api_access: false })

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
vm.talk = { ...vm.talk, enabled: false, reminders_enabled: true }
assert.equal(vm.reconnectTalkChannel, 'reminders', 'Reconnect preserves reminder-only opt-in')
vm.talk = { ...vm.talk, reminders_enabled: false, imports_enabled: true }
assert.equal(vm.reconnectTalkChannel, null)
vm.hasActiveImports = true
assert.equal(vm.reconnectTalkChannel, null, 'A configured folder cannot expose imports while the feature is off')
assert(!vm.notificationRows.some(row => row.field === 'notifyAutomaticImports'))
vm.enablePaymentImports = true
assert.equal(vm.reconnectTalkChannel, 'imports', 'Import-only reconnect keeps the original channel')
vm.$texts.autoImports = { title: () => 'Automatic imports' }
assert.equal(vm.notificationRows.at(-1).talkField, 'imports_enabled')
vm.hasActiveImports = false
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

// Direct settings destinations survive loading, browser history and feature gates.
const scrolledSections = []
vm.$nextTick = fn => fn()
vm.$refs = {
	topicContent: {
		querySelector: id => id === '#automatic-imports'
			? null
			: { scrollIntoView: () => scrolledSections.push(id) },
	},
}
assert.deepEqual(vm.settingsTopics.map(topic => topic.id), [
	'general', 'payments', 'categories', 'payment-partners', 'workspaces', 'notifications', 'integrations', 'exports', 'keyboard-shortcuts',
])
for (const [section, topic] of [['master-data', 'categories'], ['categories', 'categories'], ['payment-partners', 'payment-partners']]) {
	vm.$route = { name: 'settings', query: { section } }
	assert.equal(vm.activeTopic.id, topic, 'Master-data bookmarks open the relevant topic')
	assert.equal(vm.$route.query.section, section, 'The selected destination remains bookmarkable')
}
vm.$route = { name: 'settings', query: { section: 'automatic-imports' } }
vm.loading = true
vm.consumeRequestedSection(vm.requestedSettingsSection)
assert.deepEqual(scrolledSections, [], 'The import link waits for settings content')
vm.loading = false
vm.scrollToSettingsTarget()
vm.scrollToSettingsTarget()
assert.deepEqual(scrolledSections, ['#settings-imports'], 'The import link scrolls once and falls back to the enable switch')
vm.$route = { name: 'settings', query: { section: 'api-tokens' } }
vm.consumeRequestedSection(vm.requestedSettingsSection)
assert.equal(vm.activeTopic.id, 'integrations')
assert.deepEqual(scrolledSections, ['#settings-imports', '#api-tokens'])
vm.$route = { name: 'settings', hash: '#api-tokens', query: {} }
assert.equal(vm.activeTopic.id, 'integrations', 'A fragment can target the API key section')
vm.$route = { name: 'settings', query: { section: 'unknown' } }
assert.equal(vm.requestedTopic, undefined, 'Unknown topics go to the settings entry point')
assert.equal(vm.activeTopic.id, 'general', 'Desktop settings open on General by default')
console.log('Settings topics and direct links passed: routing, post-load scrolling and fallback targets.')

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

// Personal notification rows are available from the first settings request while workspace and Files are still loading.
vm.applyLocalNextcloudHeaderVisibility = () => ({ showDesktop: true, showMobile: true })
vm.fetchBackups = async () => {}
vm.ensureValidWorkspaceContext = async () => {}
vm.scrollToSettingsTarget = () => {}
vm.fetchWorkspaceScopedWithRetry = async () => ({ data: [] })
for (const active of [true, false]) {
	api.get = async () => ({ data: { enable_payment_imports: true, has_active_automatic_imports: active, api_access_admin_enabled: active, enable_api_access: true } })
	await vm.fetchData()
	assert.equal(vm.settingsLoadError, '')
	assert.equal(vm.apiAccessAdminEnabled, active)
	assert.equal(vm.enableApiAccess, active, 'User enableApiAccess stays false while admin toggle is disabled')
	assert.equal(vm.notificationRows.some(row => row.field === 'notifyAutomaticImports'), active)
}
