import assert from 'node:assert/strict'
import fs from 'node:fs'

const source = fs.readFileSync(new URL('../src/views/SettingsView.vue', import.meta.url), 'utf8')
let selection, pickCount = 0, canPick, buttons
const builder = {
	setMultiSelect(value) { assert.equal(value, false); return this },
	allowDirectories(value) { assert.equal(value, true); return this },
	setMimeTypeFilter(value) { assert.deepEqual(value, ['httpd/unix-directory']); return this },
	startAt() { return this },
	setCanPick(value) { canPick = value; return this },
	setButtonFactory(value) { buttons = value; return this },
	build() { return this },
	async pick() { pickCount++; return selection() },
}
function method(name) {
	const start = source.search(new RegExp(`\t\t(?:async )?${name}\\(`))
	const end = start + /\n\t\t}(?=,|\n)/.exec(source.slice(start)).index + 4
	return new Function('getFilePickerBuilder', 'Permission', 'pickFolder', `return ({${source.slice(start, end).trim()}}).${name}`)(() => builder, { CREATE: 4 }, picker => picker.pick())
}
const writes = [], errors = []
const vm = {
	loading: false, folderPickerOpen: false, backupSettingsDisabled: false, backupSettingsValid: true,
	receiptStorageFolder: 'CoBudget/Belege', savedReceiptStorageFolder: 'CoBudget/Belege', backupStorageFolder: 'CoBudget/Export',
	$texts: { settings: { storageFolder: () => 'Receipts', backupFolder: () => 'Exports', selectFolder: () => 'Select folder' }, common: { saveError: () => 'Save failed' } },
	folderDisplayPath: folder => '/' + String(folder || '').replace(/^\/+/, ''),
	extractError: error => error.message,
	showError: error => errors.push(error),
	async saveGeneralSettings(options) { writes.push(['receipt', this.receiptStorageFolder, options]); return true },
	async autoSaveBackupSettings() { writes.push(['backup', this.backupStorageFolder]); return true },
}
for (const name of ['chooseSettingsFolder', 'saveReceiptStorageFolder']) vm[name] = method(name).bind(vm)
selection = () => { throw Object.assign(new Error('Closed'), { name: 'FilePickerClosed' }) }
await vm.chooseSettingsFolder('receipt')
assert.equal(writes.length, 0)
assert.equal(errors.length, 0)
assert.equal(vm.folderPickerOpen, false)
const folder = { type: 'folder', path: '/Receipts', permissions: 4 }
assert.equal(canPick(folder), true)
assert.equal(canPick({ ...folder, path: '/' }), false)
assert.equal(canPick({ ...folder, permissions: 1 }), false)
assert.equal(canPick({ ...folder, type: 'file' }), false)
assert.equal(buttons([])[0].disabled, true)
assert.equal(buttons([folder])[0].disabled, false)
selection = () => '/CoBudget/Belege'
await vm.chooseSettingsFolder('receipt')
assert.equal(writes.length, 0, 'Same folder does not save')
selection = () => '/Receipts/2026/'
await vm.chooseSettingsFolder('receipt')
assert.deepEqual(writes, [['receipt', 'Receipts/2026', { reload: false }]])
assert.equal(vm.savedReceiptStorageFolder, 'Receipts/2026')
selection = () => '/Exports'
await vm.chooseSettingsFolder('backup')
assert.deepEqual(writes.at(-1), ['backup', 'Exports'])
vm.autoSaveBackupSettings = async () => false
selection = () => '/Failed'
await vm.chooseSettingsFolder('backup')
assert.equal(vm.backupStorageFolder, 'Exports', 'Failed saving restores the selected folder')
vm.saveGeneralSettings = async () => false
await vm.chooseSettingsFolder('receipt')
assert.equal(vm.receiptStorageFolder, 'Receipts/2026')
assert.equal(vm.savedReceiptStorageFolder, 'Receipts/2026')
selection = () => { throw new Error('Picker failed') }
await vm.chooseSettingsFolder('receipt')
assert.deepEqual(errors, ['Picker failed'])
assert.equal(vm.folderPickerOpen, false)
let release
selection = () => new Promise(resolve => { release = resolve })
const pending = vm.chooseSettingsFolder('receipt')
const before = pickCount
await vm.chooseSettingsFolder('backup')
assert.equal(pickCount, before, 'Only one picker can open at a time')
release('/Receipts/2026'); await pending
assert.equal(vm.folderPickerOpen, false)
vm.backupSettingsValid = false
await vm.chooseSettingsFolder('backup')
assert.equal(pickCount, before, 'Invalid backup preferences do not open the picker')
console.log('Settings folders passed: native selection, cancellation, unchanged paths, automatic saving, rollback and pending-picker guards.')
