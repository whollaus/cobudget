import assert from 'node:assert/strict'
import fs from 'node:fs'

const source = fs.readFileSync(new URL('../src/views/SettingsView.vue', import.meta.url), 'utf8')
let previews = 0
let previewData = { blocking_shared_projects: [] }
const writes = []
let cleared = 0
const api = {
	get: async (url, config) => {
		assert.equal(url, '/apps/cobudget/api/settings/reset-preview')
		assert.equal(config.skipWorkspaceHeader, true)
		previews++
		return { data: { reset: previewData } }
	},
	post: async (...args) => { writes.push(args); return { data: { backups: [] } } },
}
const loadMethod = name => {
	const start = source.search(new RegExp(`\\t\\t(?:async )?${name}\\(`))
	assert.ok(start >= 0, `${name} exists`)
	const end = start + /\n\t\t}(?=,|\n)/.exec(source.slice(start)).index + 4
	const method = source.slice(start, end).trim().replace(/,$/, '')
	return new Function('axios', 'generateUrl', 'clearWorkspaceId', 'window', `return ({${method}}).${name}`)(api, url => url, () => { cleared++ }, { setTimeout: () => {} })
}
const vm = {
	resetRunning: false, loading: false, resetPreview: null, resetPreviewLoading: false, resetPreviewError: '',
	confirmDialog: null,
	$texts: { settings: { resetTitle: () => 'Reset', resetConfirmButton: () => 'Reset now', resetDone: () => 'Done', resetPreviewError: () => 'Preview failed', resetError: () => 'Reset failed' } },
	buildResetBlockerMessage: preview => preview?.blocking_shared_projects?.length ? 'Open shared payments' : '',
	extractError: error => error.message,
	buildResetPreviewMessage: () => 'Preview and safety export',
	showSuccessMessage: () => {},
	showError: () => { throw new Error('Reset blockers must not open an alert') },
}
for (const name of ['openConfirm', 'resolveConfirm', 'fetchResetPreview', 'refreshResetAvailability', 'resetAllData']) vm[name] = loadMethod(name).bind(vm)
for (const name of ['confirmDialogCanConfirm', 'resetBlockerMessage', 'resetButtonDisabled']) Object.defineProperty(vm, name, { get: loadMethod(name).bind(vm) })
const flush = () => new Promise(resolve => setImmediate(resolve))
assert.equal(vm.resetButtonDisabled, true, 'Reset stays disabled before availability is known')
await vm.resetAllData()
assert.equal(previews, 0)
const initialLoad = vm.refreshResetAvailability()
assert.equal(vm.resetButtonDisabled, true, 'Reset stays disabled during loading')
await initialLoad
assert.equal(vm.resetButtonDisabled, false)
previews = 0

const cancelled = vm.resetAllData()
await flush()
assert.equal(vm.confirmDialog.kind, 'reset')
assert.equal(vm.confirmDialog.requiredText, 'RESET')
await vm.resetAllData()
assert.equal(previews, 1, 'A pending confirmation prevents a second reset flow')
for (const text of ['', 'reset', ' RESET', 'RESET ', 'DELETE']) {
	vm.confirmDialog.confirmationText = text
	assert.equal(vm.confirmDialogCanConfirm, false)
	vm.resolveConfirm(true)
	assert.ok(vm.confirmDialog, 'Invalid confirmation cannot dismiss the safety dialog')
	assert.equal(writes.length, 0)
}
vm.resolveConfirm(false)
await cancelled
assert.equal(writes.length, 0, 'Cancelling never sends a reset request')
assert.equal(vm.resetRunning, false)

previewData = { blocking_shared_projects: [{ name: 'Shared area', open_entries: 2 }] }
await vm.refreshResetAvailability()
assert.equal(vm.resetButtonDisabled, true)
assert.equal(vm.resetBlockerMessage, 'Open shared payments')
await vm.resetAllData()
assert.equal(vm.confirmDialog, null, 'Shared-area blockers prevent confirmation')
assert.equal(writes.length, 0)
previewData = { blocking_shared_projects: [] }
await vm.refreshResetAvailability()
assert.equal(vm.resetButtonDisabled, false, 'Settled areas enable reset after a fresh preview')

const get = api.get
api.get = async () => { throw new Error('Network unavailable') }
await vm.refreshResetAvailability()
assert.equal(vm.resetButtonDisabled, true)
assert.equal(vm.resetPreviewError, 'Network unavailable')
api.get = get

previewData = null
await vm.refreshResetAvailability()
assert.equal(vm.resetButtonDisabled, true, 'Malformed previews cannot enable a destructive action')
assert.equal(vm.resetPreviewError, 'Preview failed')
previewData = { blocking_shared_projects: [] }
await vm.refreshResetAvailability()
assert.equal(vm.resetPreviewError, '')
assert.equal(vm.resetButtonDisabled, false)

previewData = { blocking_shared_projects: [{ name: 'New shared payments', open_entries: 1 }] }
await vm.resetAllData()
assert.equal(vm.confirmDialog, null, 'A new blocker found immediately before confirmation appears inline')
assert.equal(vm.resetButtonDisabled, true)
previewData = { blocking_shared_projects: [] }
await vm.refreshResetAvailability()

const confirmed = vm.resetAllData()
await flush()
vm.confirmDialog.confirmationText = 'RESET'
assert.equal(vm.confirmDialogCanConfirm, true)
assert.equal(writes.length, 0, 'Typing RESET alone does not start a reset')
vm.resolveConfirm(true)
vm.resolveConfirm(true)
await confirmed
assert.equal(writes.length, 1)
assert.deepEqual(writes[0], ['/apps/cobudget/api/settings/reset', { confirmation: 'RESET' }, { skipWorkspaceHeader: true }])
assert.equal(cleared, 1)
assert.equal(vm.resetRunning, false)
// A blocker created after the preview is still enforced by the server.
const post = api.post
api.post = async () => { throw { response: { data: { reset: { blocking_shared_projects: [{ name: 'Concurrent payment', open_entries: 1 }] } } } } }
const originalError = console.error
console.error = () => {}
try {
	const blockedAtWrite = vm.resetAllData()
	await flush()
	vm.confirmDialog.confirmationText = 'RESET'
	vm.resolveConfirm(true)
	await blockedAtWrite
	assert.equal(vm.resetBlockerMessage, 'Open shared payments')
	assert.equal(vm.resetButtonDisabled, true)
	assert.equal(cleared, 1, 'A rejected reset never clears the active workspace')
} finally { api.post = post; console.error = originalError }
console.log('Reset availability and confirmation passed: loading, blockers, malformed previews, retry, fresh preflight, concurrent server rejection, exact token and cancellation; requests mocked.')

vm.showError = () => {}
const deletions = []
api.delete = async (...args) => { deletions.push(args); return { data: { backups: [] } } }
Object.assign(vm, {
	backupDeletingFileName: '', backupRestoringFileName: '', backups: [],
	extractError: error => error.message,
	fetchData: async () => {},
})
Object.defineProperty(vm, 'backupActionsDisabled', { get: loadMethod('backupActionsDisabled').bind(vm) })
for (const name of ['deleteBackup', 'restoreBackup']) vm[name] = loadMethod(name).bind(vm)
for (const name of ['deleteBackupTitle', 'deleteBackupMessage', 'deleteBackupConfirm', 'backupDeleted', 'restoreBackupBlocked', 'restoreBackupSharedDataBlocked', 'restoreBackupMessage', 'restoreBackupSharedDataImportMode', 'restoreBackupUserMapping', 'restoreBackupTitle', 'restoreBackupConfirm', 'backupRestored']) {
	vm.$texts.settings[name] = (...args) => `${name}: ${args.join(', ')}`
}
const backup = { file_name: 'personal export.zip', can_restore: true }
const cancelDelete = vm.deleteBackup(backup)
assert.ok(vm.confirmDialog.message.includes(backup.file_name))
assert.equal(vm.confirmDialog.kind, 'deleteBackup')
assert.equal(deletions.length, 0, 'Opening a delete dialog does not delete an export')
vm.resolveConfirm(false)
await cancelDelete
assert.equal(deletions.length, 0)
const confirmDelete = vm.deleteBackup(backup)
vm.resolveConfirm(true)
await confirmDelete
assert.deepEqual(deletions, [['/apps/cobudget/api/backups/personal%20export.zip', { skipWorkspaceHeader: true }]])

let inspections = 0
let eligible = false
api.get = async (url, config) => {
	assert.equal(url, '/apps/cobudget/api/backups/personal%20export.zip/inspect')
	assert.equal(config.skipWorkspaceHeader, true)
	inspections++
	return { data: { backup: { restore: { can_restore_personally: eligible, shared_data_restore_mode: 'personal_share', will_map_source_user: true, source_user_id: 'source', target_user_id: 'target' } } } }
}
vm.backupRestoreHint = loadMethod('backupRestoreHint').bind(vm)
vm.$texts.common = { deleteBusy: () => 'Deleting' }
vm.$texts.settings.restoringBackup = () => 'Restoring'
assert.equal(vm.backupRestoreHint(backup), '')
assert.equal(vm.backupRestoreHint({ ...backup, can_restore: false }), vm.$texts.settings.restoreBackupBlocked())
vm.backupRestoringFileName = 'another.zip'
assert.equal(vm.backupRestoreHint(backup), 'Restoring')
assert.equal(vm.backupRestoreHint({ ...backup, can_restore: false }), vm.$texts.settings.restoreBackupBlocked(), 'The persistent restriction takes precedence over a temporary operation')
vm.backupRestoringFileName = ''
vm.backupDeletingFileName = 'another.zip'
assert.equal(vm.backupRestoreHint(backup), 'Deleting')
vm.backupDeletingFileName = ''
assert.ok(!source.includes('id="settings-restore-blocker"'), 'The blocker explanation lives at the disabled action')
assert.ok(!source.includes('v-if="resetBlockerMessage"'), 'Do not repeat the reset blocker above its tooltip')
const showError = vm.showError
vm.showError = () => { throw new Error('Known restore blocker must be available in the tooltip') }
await vm.restoreBackup({ ...backup, can_restore: false })
vm.showError = showError
assert.equal(inspections, 0, 'Ineligible exports cannot start inspection or restoration')
await vm.restoreBackup(backup)
assert.equal(vm.confirmDialog, null, 'Failed inspection eligibility prevents confirmation')
assert.equal(writes.length, 1)
eligible = true
const cancelRestore = vm.restoreBackup(backup)
await Promise.resolve()
assert.equal(vm.confirmDialog.kind, 'restoreBackup')
assert.ok(vm.confirmDialog.message.includes('restoreBackupSharedDataImportMode'))
assert.ok(vm.confirmDialog.message.includes('source, target'))
for (const text of ['', 'restore', 'RESTORE ', 'RESET']) {
	vm.confirmDialog.confirmationText = text
	vm.resolveConfirm(true)
	assert.ok(vm.confirmDialog, 'Incorrect RESTORE token cannot confirm restoration')
	assert.equal(writes.length, 1)
}
vm.resolveConfirm(false)
await cancelRestore
assert.equal(writes.length, 1, 'Cancelling restore never sends a restore request')
const confirmRestore = vm.restoreBackup(backup)
await Promise.resolve()
vm.confirmDialog.confirmationText = 'RESTORE'
assert.equal(vm.confirmDialogCanConfirm, true)
assert.equal(writes.length, 1, 'Typing RESTORE alone never restores data')
vm.resolveConfirm(true)
await confirmRestore
assert.deepEqual(writes[1], ['/apps/cobudget/api/backups/personal%20export.zip/restore', { confirmation: 'RESTORE' }, { skipWorkspaceHeader: true }])
assert.equal(vm.backupRestoringFileName, '')
console.log('Export confirmations passed: explicit deletion, cancellation, inspection eligibility, warnings and exact RESTORE token; requests mocked.')
