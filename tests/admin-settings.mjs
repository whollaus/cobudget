import assert from 'node:assert/strict'
import fs from 'node:fs'

const source = fs.readFileSync(new URL('../src/components/AdminSettings.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const imports = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .+$/gm, '').replace('export default', 'return')
const writes = []
const api = { post: async (...args) => { writes.push(args); throw new Error('Unavailable') } }
let selection, pickCount = 0, canPick
const builder = { setMultiSelect() { return this }, allowDirectories() { return this }, setMimeTypeFilter() { return this }, startAt() { return this }, setCanPick(fn) { canPick = fn; return this }, setButtonFactory() { return this }, build() { return this }, async pick() { pickCount++; return selection() } }
const component = new Function('getCurrentUser', 'getFilePickerBuilder', 'Permission', 'generateOcsUrl', 'generateUrl', 'extractError', 'showToast', ...imports, script)(() => ({uid: 'admin'}), () => builder, {CREATE: 4}, url => url, url => url, (e, fallback) => e?.message || fallback, () => {}, ...imports.map(name => name === 'axios' ? api : name === 'pickFolder' ? picker => picker.pick() : {}))
const vm = { ...component.data(), $texts: Object.fromEntries(['admin', 'common', 'memberSearch', 'settings'].map(group => [group, new Proxy({}, { get: (_, key) => () => key })])) }
for (const [name, method] of Object.entries(component.methods)) vm[name] = method.bind(vm)
for (const [name, getter] of Object.entries(component.computed)) Object.defineProperty(vm, name, typeof getter === 'function' ? { get: getter.bind(vm) } : { get: getter.get.bind(vm), set: getter.set.bind(vm) })

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

// Clearing the account with scheduling off is a valid automatic save.
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
