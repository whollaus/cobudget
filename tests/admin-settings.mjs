import assert from 'node:assert/strict'
import fs from 'node:fs'
import parseMarkdownGuide from '../src/utils/markdownGuide.js'

const apiLimitGuideMarkdown = fs.readFileSync(new URL('../docs/api-request-limits.md', import.meta.url), 'utf8')
const source = fs.readFileSync(new URL('../src/components/AdminSettings.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const imports = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .+$/gm, '').replace('export default', 'return')
const writes = []
const api = { post: async (...args) => { writes.push(args); throw new Error('Unavailable') } }
let selection, pickCount = 0, canPick
const builder = { setMultiSelect() { return this }, allowDirectories() { return this }, setMimeTypeFilter() { return this }, startAt() { return this }, setCanPick(fn) { canPick = fn; return this }, setButtonFactory() { return this }, build() { return this }, async pick() { pickCount++; return selection() } }
const component = new Function('getCurrentUser', 'getFilePickerBuilder', 'Permission', 'generateOcsUrl', 'generateUrl', 'extractError', 'showToast', ...imports, script)(() => ({uid: 'admin'}), () => builder, {CREATE: 4}, url => url, url => url, (e, fallback) => e?.message || fallback, () => {}, ...imports.map(name => name === 'axios' ? api : name === 'pickFolder' ? picker => picker.pick() : name === 'parseMarkdownGuide' ? parseMarkdownGuide : name === 'apiLimitGuide' ? apiLimitGuideMarkdown : {}))
const vm = { ...component.data(), $texts: Object.fromEntries(['admin', 'apiTokens', 'common', 'memberSearch', 'settings'].map(group => [group, new Proxy({}, { get: (_, key) => () => key })])) }
for (const [name, method] of Object.entries(component.methods)) vm[name] = method.bind(vm)
for (const [name, getter] of Object.entries(component.computed)) Object.defineProperty(vm, name, typeof getter === 'function' ? { get: getter.bind(vm) } : { get: getter.get.bind(vm), set: getter.set.bind(vm) })

assert.deepEqual(vm.settingsTopics.map(topic => topic.id), ['categories', 'payment-partners', 'data-quality', 'backups', 'api'])
vm.$route = { query: {}, hash: '' }
assert.equal(vm.requestedTopic, undefined)
assert.equal(vm.activeTopicId, 'categories', 'Desktop defaults to categories and phones show the topic list')
vm.$route.query.section = 'payment-partners'
assert.equal(vm.activeTopicId, 'payment-partners')
for (const hash of ['#cobudget-api-settings', '#cobudget-api-request-limit']) {
 vm.$route.hash = hash
 assert.equal(vm.activeTopicId, 'api', 'Existing API anchors open the API topic')
 assert.equal(vm.apiAnchor, hash, 'The exact legacy anchor is retained as the scroll target')
}
vm.$route = { query: { section: 'unknown' }, hash: '#unrelated' }
assert.equal(vm.requestedTopic, undefined, 'Unknown topics fall back to the settings entry point')

// Restore must still require an exact acknowledgement before any write.
const backup = { file_name: 'test.zip' }
vm.openFullBackupRestore(backup)
for (const value of ['', 'restore', ' RESTORE', 'RESTORE ']) {
 vm.fullBackupRestoreConfirmation = value
 assert.equal(vm.fullBackupRestoreHint, 'fullBackupRestoreConfirmHint')
 await vm.confirmFullBackupRestore()
 assert.equal(writes.length, 0)
}
vm.closeFullBackupRestoreModal()
assert.equal(vm.fullBackupRestoreCandidate, null)
assert.equal(writes.length, 0)
vm.openFullBackupRestore(backup)
vm.fullBackupRestoreConfirmation = 'RESTORE'
await vm.confirmFullBackupRestore()
assert.equal(writes.length, 1)
assert.deepEqual(writes[0].slice(1), [{ file_name: 'test.zip', confirmation: 'RESTORE' }, { skipWorkspaceHeader: true }])
assert.equal(vm.fullBackupRestoreError, 'Unavailable')
assert.equal(vm.fullBackupRestoreCandidate, backup, 'Failure retains the dialog and selected backup')
assert.equal(vm.fullBackupRestoreConfirmation, 'RESTORE')
assert.equal(vm.fullBackupRestoringFileName, '')
let finish
api.post = async (...args) => { writes.push(args); return new Promise(resolve => { finish = resolve }) }
const restoring = vm.confirmFullBackupRestore()
assert.equal(vm.fullBackupRestoreError, '', 'Retry clears the earlier error')
vm.closeFullBackupRestoreModal()
await vm.confirmFullBackupRestore()
assert.equal(writes.length, 2, 'Pending restore cannot be submitted twice')
assert.equal(vm.fullBackupRestoreCandidate, backup, 'Pending restore cannot close')
finish({ data: { settings: {}, backups: [] } })
await restoring
assert.equal(vm.fullBackupRestoreCandidate, null)

// Backup settings retain drafts on failure and cannot overwrite unknown settings.
api.get = async () => { throw new Error('Load unavailable') }
await vm.fetchFullBackupSettings()
assert.equal(vm.fullBackupLoadError, 'Load unavailable')
await vm.saveFullBackupSettings()
assert.equal(writes.length, 2)
api.get = async () => ({ data: { settings: { storage_user_id: 'admin' }, backups: [] } })
await vm.fetchFullBackupSettings()
assert.equal(vm.fullBackupLoadError, '')
vm.fullBackupStorageFolder = 'Draft folder'
api.post = async (...args) => { writes.push(args); throw new Error('Save unavailable') }
await vm.saveFullBackupSettings()
assert.equal(vm.fullBackupSettingsError, 'Save unavailable')
assert.equal(vm.fullBackupStorageFolder, 'Draft folder')
assert.equal(vm.fullBackupBusy, false)
assert.equal(writes.at(-1)[2].skipWorkspaceHeader, true)

const count = writes.length
const deletion = vm.deleteFullBackup(backup)
assert.equal(vm.confirmDialog.confirmVariant, 'danger')
vm.resolveConfirm(false)
await deletion
assert.equal(writes.length, count, 'Cancelling backup deletion never writes')
assert.ok(!/<(?:input|select|button|ConfirmModal)\b/.test(source.split('<script>')[0]))
assert.ok(source.includes(':no-close="!!fullBackupRestoringFileName"'))
console.log('Admin forms passed: exact RESTORE, cancellation, retained errors/drafts, retry, pending protection and workspace-independent settings; APIs mocked.')

vm.fullBackupSettingsError = ''
// Creation uses saved settings; incomplete or unsaved drafts must never create a backup elsewhere.
vm.applyFullBackupSettings({ storage_user_id: 'admin', storage_folder: 'Backups', retention_count: 7, schedule: 'none', storage_user_exists: true, storage_user_is_admin: true })
assert.equal(vm.fullBackupCreateHint, '')
vm.fullBackupStorageFolder = ''
assert.equal(vm.fullBackupCreateHint, 'fullBackupSelectFolder')
await vm.createFullBackupNow()
assert.equal(writes.length, count)
vm.fullBackupStorageFolder = 'Other'
assert.equal(vm.fullBackupCreateHint, 'fullBackupUnsaved')
await vm.createFullBackupNow()
assert.equal(writes.length, count)
vm.fullBackupStorageFolder = 'Backups'
vm.savedFullBackupSettings.storage_user_is_admin = false
assert.equal(vm.fullBackupCreateHint, 'fullBackupSelectUser')
vm.savedFullBackupSettings.storage_user_is_admin = true
vm.fullBackupStorageUser = ''
assert.equal(vm.fullBackupCreateHint, 'fullBackupSelectUser')
vm.fullBackupStorageUser = 'admin'
assert.equal(vm.fullBackupStorageUser, 'admin')
assert.equal(vm.canBrowseBackupFolder, true)

// Files selection is limited to the signed-in storage user; cancellation preserves the draft.
selection = () => { throw Object.assign(new Error('Closed'), {name: 'FilePickerClosed'}) }
await vm.chooseFullBackupFolder()
assert.equal(vm.fullBackupStorageFolder, 'Backups')
assert.equal(vm.fullBackupError, '')
assert.equal(vm.folderPickerOpen, false)
assert.equal(canPick({type: 'folder', path: '/Backups', permissions: 4}), true)
assert.equal(canPick({type: 'folder', path: '/', permissions: 4}), false)
assert.equal(canPick({type: 'folder', path: '/ReadOnly', permissions: 1}), false)
selection = () => '/New Backups/'
await vm.chooseFullBackupFolder()
assert.equal(vm.fullBackupStorageFolder, 'New Backups')
assert.equal(writes.length, count + 1, 'Choosing a folder saves automatically')
assert.equal(vm.fullBackupSettingsError, 'Save unavailable')
assert.equal(vm.fullBackupStorageFolder, 'New Backups', 'Failed folder saves retain the selected draft for retry')
vm.fullBackupStorageUser = 'another-admin'
const picks = pickCount
await vm.chooseFullBackupFolder()
assert.equal(pickCount, picks, 'Never browse the signed-in user’s files for another storage user')

// Native administrator lookup uses the global admin group and ignores stale responses.
let finishOld
api.get = async (url, options) => {
 assert.equal(url, '/cloud/groups/admin/users/details')
 assert.equal(options.skipWorkspaceHeader, true)
 if (options.params.search === 'old') return new Promise(resolve => { finishOld = resolve })
 return {data: {ocs: {data: {users: {admin: {displayname: 'Administrator'}}}}}}
}
vm.storageUserSearch = 'old'
const oldSearch = vm.fetchStorageUsers()
vm.storageUserSearch = 'admin'
await vm.fetchStorageUsers()
finishOld({data: {ocs: {data: {users: {old: {displayname: 'Old'}}}}}})
await oldSearch
assert.deepEqual(vm.storageUsers, [{id: 'admin', displayName: 'Administrator'}])
assert.equal(vm.storageUsersLoading, false)
console.log('Admin backup selection passed: account-scoped folder picker, cancellation, administrator search, stale results, missing/unsaved settings and create guards; no real writes.')

vm.fullBackupSettingsError = ''
// Once valid persisted settings are loaded, creating posts only once and reports failures inline.
vm.applyFullBackupSettings({storage_user_id: 'admin', storage_folder: 'Backups', retention_count: 7, schedule: 'none', storage_user_exists: true, storage_user_is_admin: true})
let finishCreate
api.post = async (...args) => { writes.push(args); return new Promise((resolve, reject) => { finishCreate = reject }) }
const creating = vm.createFullBackupNow()
assert.equal(vm.fullBackupCreateHint, 'waitBusy')
await vm.createFullBackupNow()
assert.equal(writes.length, count + 2)
assert.deepEqual(writes.at(-1), ['/apps/cobudget/api/admin/full-backup', {}, {skipWorkspaceHeader: true}])
finishCreate(new Error('Creation unavailable'))
await creating
assert.equal(vm.fullBackupError, 'Creation unavailable')
assert.equal(vm.fullBackupCreating, false)
assert.equal(vm.fullBackupCreateHint, '')

// Initialization and unchanged blur do not write. Invalid values stay local.
const validSettings = {storage_user_id: 'admin', storage_folder: 'Backups', retention_count: 7, schedule: 'none', storage_user_exists: true, storage_user_is_admin: true}
vm.applyFullBackupSettings(validSettings)
let before = writes.length
await vm.saveFullBackupSettings()
assert.equal(writes.length, before)
for (const value of ['', 0, 101, 2.5]) {
 vm.fullBackupRetentionCount = value
 await vm.saveFullBackupSettings()
 assert.equal(writes.length, before)
 assert.equal(vm.fullBackupValidationError, 'invalidRetentionCount')
}
vm.fullBackupRetentionCount = 7
for (const folder of ['', '/', '../Backups', 'Backups/../Other', 'a\\b', 'x'.repeat(181)]) {
 vm.fullBackupStorageFolder = folder
 await vm.saveFullBackupSettings()
 assert.equal(writes.length, before)
 assert.equal(vm.fullBackupValidationError, 'fullBackupInvalidFolder')
}
vm.fullBackupStorageFolder = 'Backups'
vm.fullBackupStorageUser = ''
vm.fullBackupSchedule = 'daily'
await vm.saveFullBackupSettings()
assert.equal(writes.length, before)
assert.equal(vm.fullBackupValidationError, 'fullBackupSelectUser')
vm.applyFullBackupSettings(validSettings)

// Serialize edits: stale responses cannot overwrite newer fields; the last queued selection wins.
const pending = []
api.post = async (...args) => { writes.push(args); return new Promise((resolve, reject) => pending.push({resolve, reject, args})) }
vm.fullBackupRetentionCount = 8
const saving = vm.saveFullBackupSettings()
assert.equal(vm.fullBackupSettingsSaving, true)
vm.fullBackupRetentionCount = 9
await vm.saveFullBackupSettings()
vm.setFullBackupSchedule('weekly')
assert.equal(writes.length, before + 1, 'Only one settings request is in flight')
pending[0].resolve({data: {settings: {...validSettings, ...pending[0].args[1]}, backups: []}})
await new Promise(resolve => setTimeout(resolve, 0))
assert.equal(vm.fullBackupRetentionCount, 9, 'Old response does not replace new retention')
assert.equal(vm.fullBackupSchedule, 'weekly')
assert.equal(pending.length, 2)
assert.deepEqual(pending[1].args[1], {...vm.fullBackupSettingsPayload})
pending[1].resolve({data: {settings: {...validSettings, ...pending[1].args[1]}, backups: []}})
await saving
assert.equal(vm.fullBackupSettingsDirty, false)
assert.equal(vm.fullBackupCreateHint, '')

// A typed but uncommitted edit survives a response and waits until blur.
vm.fullBackupRetentionCount = 10
const typed = vm.saveFullBackupSettings()
vm.fullBackupStorageFolder = 'Typing'
pending[2].resolve({data: {settings: {...validSettings, ...pending[2].args[1]}, backups: []}})
await typed
assert.equal(vm.fullBackupStorageFolder, 'Typing')
assert.equal(vm.fullBackupSettingsDirty, true)
assert.equal(pending.length, 3)
const failed = vm.saveFullBackupSettings()
pending[3].reject(new Error('Settings unavailable'))
await failed
assert.equal(vm.fullBackupSettingsError, 'Settings unavailable')
assert.equal(vm.fullBackupStorageFolder, 'Typing')
assert.equal(vm.fullBackupCreateHint, 'Settings unavailable')
const retry = vm.saveFullBackupSettings()
pending[4].resolve({data: {settings: {...validSettings, ...pending[4].args[1]}, backups: []}})
await retry
assert.equal(vm.fullBackupSettingsError, '')
assert.equal(vm.fullBackupSettingsDirty, false)

// Clear selection with scheduling off is a valid automatic save.
vm.setFullBackupSchedule('none')
pending[5].resolve({data: {settings: {...validSettings, ...pending[5].args[1]}, backups: []}})
await new Promise(resolve => setTimeout(resolve, 0))
vm.selectedStorageUser = null
assert.equal(pending[6].args[1].storage_user_id, '')
pending[6].resolve({data: {settings: {...validSettings, ...pending[6].args[1], storage_user_exists: false, storage_user_is_admin: false}, backups: []}})
await new Promise(resolve => setTimeout(resolve, 0))
assert.equal(vm.fullBackupCreateHint, 'fullBackupSelectUser')
assert.ok(!source.includes('admin-backup-save-action'))
console.log('Admin autosave passed: native selection triggers, folder selection, unchanged/invalid edits, serialized latest changes, preserved text drafts, inline retry and backup creation guards.')

// Global API access admin toggle loads, saves, guards load errors, rolls back failures, and prevents concurrent saves.
const apiGets = []
api.get = async (...args) => {
 apiGets.push(args)
 return { data: { enabled: true } }
}
await vm.fetchApiSettings()
assert.deepEqual(apiGets.at(-1), ['/apps/cobudget/api/admin/api/settings', { skipWorkspaceHeader: true }])
assert.equal(vm.apiAccessEnabled, true)
assert.equal(vm.apiSettingsLoadError, '')
assert.equal(vm.apiSettingsLoading, false)

api.get = async () => { throw new Error('API settings load failed') }
await vm.fetchApiSettings()
assert.equal(vm.apiSettingsLoadError, 'API settings load failed')
const beforeBlockedApiSave = writes.length
assert.equal(await vm.saveApiAccessEnabled(false), false)
assert.equal(writes.length, beforeBlockedApiSave, 'Blocked when API settings failed to load')

api.get = async () => ({ data: { enabled: false } })
await vm.fetchApiSettings()
assert.equal(vm.apiAccessEnabled, false)
assert.equal(vm.apiSettingsLoadError, '')

let resolveApiSave
api.post = async (...args) => { writes.push(args); return new Promise((resolve, reject) => { resolveApiSave = { resolve, reject } }) }
const savingApi = vm.saveApiAccessEnabled(true)
assert.equal(vm.apiSettingsSaving, true)
assert.equal(vm.apiAccessEnabled, true)
assert.equal(await vm.saveApiAccessEnabled(false), false, 'Concurrent API settings save is ignored while saving')
assert.equal(writes.length, beforeBlockedApiSave + 1)
assert.deepEqual(writes.at(-1), ['/apps/cobudget/api/admin/api/settings', { enabled: true }, { skipWorkspaceHeader: true }])
resolveApiSave.reject(new Error('Save rejected'))
assert.equal(await savingApi, false)
assert.equal(vm.apiAccessEnabled, false, 'Failed save rolls back admin API toggle')
assert.equal(vm.apiSettingsSaveError, 'Save rejected')
assert.equal(vm.apiSettingsSaving, false)

api.post = async (...args) => { writes.push(args); return { data: { enabled: true } } }
assert.equal(await vm.saveApiAccessEnabled(true), true)
assert.equal(vm.apiAccessEnabled, true)
assert.equal(vm.apiSettingsSaveError, '')

// Both enabling and disabling global API access handle 403 X-NC-Auth-NotConfirmed via /login/confirm, and rollback cleanly on cancel.
let adminNeedsConfirm = true
api.post = async (url, body, config) => {
 writes.push([url, body, config])
 if (url === '/login/confirm') {
  if (body?.password !== 'admin-pass') {
   throw Object.assign(new Error('Forbidden'), { response: { status: 403, headers: {} } })
  }
  adminNeedsConfirm = false
  return { data: { lastLogin: 1700001234 } }
 }
 if (adminNeedsConfirm) {
  throw Object.assign(new Error('Password confirmation required'), {
   response: { status: 403, headers: { 'X-NC-Auth-NotConfirmed': 'true' } },
  })
 }
 return { data: { enabled: !!body?.enabled } }
}

const cancelDisablePromise = vm.saveApiAccessEnabled(false)
await Promise.resolve()
await Promise.resolve()
assert.equal(vm.passwordConfirm.open, true, 'Disabling global API access prompts for password confirmation on 403 challenge')
vm.cancelPasswordConfirmation()
assert.equal(await cancelDisablePromise, false)
assert.equal(vm.apiAccessEnabled, true, 'Cancelling password confirmation rolls back admin API toggle to true')
assert.equal(vm.apiSettingsSaveError, '', 'Cancelling password confirmation sets no save error')

const confirmDisablePromise = vm.saveApiAccessEnabled(false)
await Promise.resolve()
await Promise.resolve()
assert.equal(vm.passwordConfirm.open, true)
vm.passwordConfirm.password = 'wrong-pass'
await vm.submitPasswordConfirmation()
assert.equal(vm.passwordConfirm.open, true, 'Wrong admin password keeps confirmation dialog open')
assert.equal(vm.passwordConfirm.password, '', 'Wrong admin password clears password input immediately')
assert.equal(vm.passwordConfirm.error, 'passwordConfirmInvalid')
vm.passwordConfirm.password = 'admin-pass'
await vm.submitPasswordConfirmation()
assert.equal(await confirmDisablePromise, true)
assert.equal(vm.passwordConfirm.open, false)
assert.equal(vm.apiAccessEnabled, false, 'Confirmed password retries and disables global API access')

adminNeedsConfirm = true
const confirmEnablePromise = vm.saveApiAccessEnabled(true)
await Promise.resolve()
await Promise.resolve()
assert.equal(vm.passwordConfirm.open, true, 'Enabling global API access also prompts for password confirmation')
vm.passwordConfirm.password = 'admin-pass'
await vm.submitPasswordConfirmation()
assert.equal(await confirmEnablePromise, true)
assert.equal(vm.apiAccessEnabled, true, 'Confirmed password retries and enables global API access')

vm.passwordConfirm.password = 'ephemeral-secret'
vm.passwordConfirm.open = true
component.beforeUnmount.call(vm)
assert.equal(vm.passwordConfirm.password, '', 'beforeUnmount clears admin password confirmation input')
assert.equal(vm.passwordConfirm.open, false, 'beforeUnmount closes admin password confirmation dialog')
console.log('Admin API settings passed: load, load-error guard, concurrent save guard, password confirmation on enable/disable, cancel rollback, and save.')

// Web server guide opens in an interactive modal rather than downloading a data URI file.
assert.ok(!source.includes('download="cobudget-api-request-limits.md"'))
assert.ok(!source.includes('data:text/plain'))
assert.equal(vm.apiLimitGuideOpen, false)
vm.openApiLimitGuide()
assert.equal(vm.apiLimitGuideOpen, true)
const guideBlocks = vm.apiLimitGuideBlocks
assert.ok(guideBlocks.some(block => block.type === 'heading' && block.level === 2 && block.segments.some(seg => seg.text.includes('nginx'))))
const codeBlocks = guideBlocks.filter(block => block.type === 'code')
assert.equal(codeBlocks.length, 2)
assert.deepEqual(codeBlocks.map(block => block.lang), ['nginx', 'apache'])
assert.ok(codeBlocks[0].code.includes('client_max_body_size 65536;'))
assert.ok(codeBlocks[0].code.includes('proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;'))
assert.ok(codeBlocks[0].code.includes('location ~ ^/+(?:index\\.php/+)?apps/+cobudget/+api/+v1(?:/+|$)'))
assert.ok(codeBlocks[1].code.includes('LimitRequestBody 65536'))
const tableBlock = guideBlocks.find(block => block.type === 'table')
assert.ok(tableBlock)
assert.equal(tableBlock.headers.length, 3)
assert.equal(tableBlock.rows.length, 2)
assert.ok(guideBlocks.some(block => block.type === 'paragraph' && block.segments.some(seg => seg.type === 'link' && seg.href.startsWith('https://'))))
const unsafeParsed = parseMarkdownGuide('[Click](javascript:alert(1)) and [Safe](https://nginx.org/)')
assert.equal(unsafeParsed[0].segments.some(seg => seg.type === 'link' && seg.href.startsWith('javascript:')), false)
assert.equal(unsafeParsed[0].segments.some(seg => seg.type === 'link' && seg.href === 'https://nginx.org/'), true)
vm.closeApiLimitGuide()
assert.equal(vm.apiLimitGuideOpen, false)
const guideStyleSection = source.slice(source.indexOf('.admin-api-guide-dialog {'))
assert.ok(guideStyleSection.includes('padding:'), 'Guide modal defines internal dialog padding')
assert.ok(!/\.admin-api-guide-dialog\s*\{[^}]*max-height:/s.test(guideStyleSection), 'Guide modal scrolls as one flow without fixed header/footer height clamp')
assert.ok(!/\.admin-api-guide-body\s*\{[^}]*overflow-y:\s*auto/s.test(guideStyleSection), 'Guide modal avoids nested body scroll container')
assert.ok(/\.admin-api-guide-dialog a\s*\{[^}]*text-decoration:\s*underline/s.test(guideStyleSection), 'Guide modal styles links visibly')
assert.ok(!guideStyleSection.includes('--color-primary-element-text-dark'), 'Guide links do not use light foreground token on white modal background')
assert.ok(source.includes('<style scoped src="../styles/bulk-import-tables.css"></style>'), 'AdminSettings reuses standard Nextcloud bulk-import-tables.css')
assert.ok(source.includes('class="bulk-import-table admin-api-guide-table"'), 'Guide table uses Nextcloud bulk-import-table styling')
assert.ok(/\.admin-api-limit-actions\s*\{[^}]*grid-template-columns:\s*repeat\(2,/s.test(source), 'Guide and overview actions render side by side')
assert.ok(source.includes('<NcFormBoxButton :href="overviewUrl" :label="$texts.admin.openOverview()" />'), 'Overview action renders as native NcFormBoxButton link')
assert.ok(source.includes('@click="openApiLimitGuide"'), 'Guide action renders as NcFormBoxButton trigger')
console.log('Admin web server guide modal passed: inline modal open/close, nginx/apache code blocks, Nextcloud table styling, safe link filtering, and NcFormBoxButton actions.')
