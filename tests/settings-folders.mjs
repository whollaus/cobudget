import assert from 'node:assert/strict'
import fs from 'node:fs'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'

const source = fs.readFileSync(new URL('../src/views/SettingsView.vue', import.meta.url), 'utf8')
let selection, pickCount = 0, canPick, buttons, pickerStart
const builder = {
	setMultiSelect(value) { assert.equal(value, false); return this },
	allowDirectories(value) { assert.equal(value, true); return this },
	setMimeTypeFilter(value) { assert.deepEqual(value, ['httpd/unix-directory']); return this },
	startAt(path) { pickerStart = path; return this },
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

// Drive the folder-import editor against the same request contract as the settings page.
const rulesSource = fs.readFileSync(new URL('../src/components/AutomaticImportSettings.vue', import.meta.url), 'utf8')
const rulesScript = rulesSource.split('<script>')[1].split('</script>')[0]
const components = [...rulesScript.matchAll(/^import (\w+) from /gm)].map(([, name]) => name)
const requests = []
const serverRules = []
const folders = {
	async createDirectory(path, options) {
		assert.equal(path, '/CoBudget/Import')
		assert.deepEqual(options, { recursive: true })
	},
}
let rejectWrite = false
const api = {
	async get(url, options) { requests.push(['get', url]); assert.equal(options.params.includeActivity, 1); return { data: { rules: [...serverRules], activeElsewhere: false } } },
	async post(url, fields) { requests.push(['post', url, fields]); return responseFor(9, fields) },
	async put(url, fields) {
		requests.push(['put', url, fields])
		if (rejectWrite) throw new Error('Folder already assigned')
		return responseFor(Number(url.split('/').pop()), fields)
	},
	async delete(url) { requests.push(['delete', url]) },
}
function responseFor(id, fields) {
	return { data: { id, project_id: fields.projectId, folder: fields.folder, interval_minutes: fields.intervalMinutes,
		notify_mode: fields.notifyMode, enabled: fields.enabled, timezone: fields.timezone } }
}
const bindings = { ...Object.fromEntries(components.map(name => [name, {}])), axios: api,
	generateRemoteUrl: service => '/remote.php/' + service,
	getClient: url => { assert.equal(url, '/remote.php/webdav'); return folders },
	showToast() {}, generateUrl: path => path, extractError: error => error.message,
	getFilePickerBuilder: () => builder, Permission: { CREATE: 4 }, pickFolder: picker => picker.pick() }
const rulesView = new Function(...Object.keys(bindings), rulesScript.replace(/^import .+$/gm, '').replace('export default', 'return'))(...Object.values(bindings))
const rules = { ...rulesView.data(), ...rulesView.methods, projects: [{ id: 32, name: 'Household' }],
	$texts: { autoImports: new Proxy({}, { get: (_, key) => () => key }), settings: { selectFolder: () => 'Select folder' },
		common: { delete: () => 'Delete' }, entry: { personalAssignment: () => 'Without area' } },
	$nextTick() {} }
for (const [key, getter] of Object.entries(rulesView.computed)) Object.defineProperty(rules, key, { get: () => getter.call(rules) })
assert.equal(rules.hasActiveImports, null, 'An unloaded folder list cannot hide the account-level notification row')
let finishDav
const prepare = rules.prepareImportFolder
rules.prepareImportFolder = () => new Promise(resolve => { finishDav = resolve })
serverRules.push(responseFor(55, { enabled: true }).data)
const loadingRules = rules.load()
await Promise.resolve()
assert.equal(rules.hasActiveImports, true, 'An active rule is visible even while Files is unavailable')
finishDav('/')
await loadingRules
rules.prepareImportFolder = prepare
serverRules.length = 0
requests.length = 0
await rules.load()
assert.equal(rules.pickerStart, '/CoBudget/Import')
assert.equal(rules.rules.length, 0, 'Preparing the suggested folder does not reserve it or activate a rule')
assert.deepEqual(rules.rows.map(row => row.area.name), ['Without area', 'Household'])
const household = () => rules.rows.find(row => row.area.id === 32)
await rules.save(household(), { enabled: true })
assert.equal(requests.length, 1, 'An unconfigured area cannot be activated')
await rules.save(household(), { intervalMinutes: 5 })
assert.equal(requests.at(-1)[0], 'post')
assert.equal(household().rule.enabled, false)
selection = () => '/Imports/Household'
await rules.chooseFolder(household())
assert.equal(pickerStart, '/CoBudget/Import')
assert.equal(household().rule.folder, 'Imports/Household')
assert.equal(household().rule.enabled, false, 'Folder selection leaves activation to the user')
household().rule.folder_id = 22
selection = () => null
await rules.chooseFolder(rules.rows[0])
assert.equal(canPick({ type: 'folder', path: '/Imports/Household', permissions: 4 }), false, 'The picker excludes another paused area’s path')
assert.equal(canPick({ type: 'folder', path: '/Shared alias', fileid: 22, permissions: 4 }), false, 'The picker also recognizes a shared node by ID')
await rules.chooseFolder(household())
assert.equal(canPick({ type: 'folder', path: '/Imports/Household', fileid: 22, permissions: 4 }), true, 'The current rule can keep its own folder')

selection = () => {
	const replacement = { type: 'folder', path: '/Imports/Household', fileid: 23, permissions: 4 }
	const [confirm] = buttons([replacement])
	assert(!confirm.disabled)
	confirm.callback([replacement])
	return replacement.path
}
await rules.chooseFolder(household())
assert.equal(requests.at(-1)[2].selectedFolderId, 23, 'Reselecting the same path confirms the chosen replacement node')
await rules.save(household(), { enabled: true })
assert(!('selectedFolderId' in requests.at(-1)[2]), 'Changing preferences cannot confirm a replacement folder')
assert.equal(household().rule.enabled, true)
assert.equal(rules.hasActiveImports, true)
const post = api.post
let finishRun, starts = 0
api.post = url => {
	assert.equal(url, '/apps/cobudget/api/auto-imports/9/run')
	starts++
	return new Promise(resolve => { finishRun = resolve })
}
const running = rules.runNow(household())
assert(!rules.canRun(household()))
await rules.runNow(household())
assert.equal(starts, 1, 'A second click while running cannot dispatch another import')
finishRun({ data: { outcome: 'checked', rule: { ...household().rule, last_message: 'delivery.json' } } })
await running
assert(rules.canRun(household()))
assert.equal(household().rule.last_message, 'delivery.json')
api.post = post
await rules.save(household(), { enabled: false })
assert(!rules.canRun(household()), 'Paused imports cannot be started')
assert.equal(rules.hasActiveImports, false, 'Pausing the last import hides the channel row')
rules.activeElsewhere = true
assert.equal(rules.hasActiveImports, true, 'An active import in a different workspace keeps channel settings available')
rules.activeElsewhere = false
await rules.save(household(), { enabled: true })
await rules.save(household(), { notifyMode: 'all' })
assert.equal(household().rule.interval_minutes, 5)
assert.equal(household().rule.notify_mode, 'all')
assert.equal(rules.rows[0].rule, null)
rejectWrite = true
await rules.save(household(), { folder: 'Taken' })
assert.equal(rules.error, 'Folder already assigned')
assert.equal(household().rule.folder, 'Imports/Household', 'Failed changes retain the saved folder')
rules.rules.push({ ...household().rule, id: 10, folder: 'Legacy second folder' },
	{ ...household().rule, id: 11, project_id: 99 })
assert.equal(rules.rows.length, 4, 'Keep all configured folders and rules for unavailable areas')
assert.equal(new Set(rules.rows.map(row => row.key)).size, 4)
assert.equal(rules.rows.at(-1).unavailable, true)
const beforeRemoval = requests.length
rules.confirmRemoval = async () => false
await rules.remove(household())
assert.equal(requests.length, beforeRemoval)
rules.confirmRemoval = async () => true
await rules.remove(household())
assert.deepEqual(requests.at(-1), ['delete', '/apps/cobudget/api/auto-imports/9'])
assert.equal(household().rule.id, 10)
await rules.remove(household())
assert.equal(household().rule, null, 'Removing the final folder keeps the area row')
folders.createDirectory = async () => { throw new Error('Could not create folder') }
folders.stat = async () => ({ type: 'directory' })
assert.equal(await rules.prepareImportFolder(), '/CoBudget/Import', 'Another request already created the folder')
folders.stat = async () => ({ type: 'file' })
await rules.load()
assert.equal(rules.pickerStart, '/', 'An existing file at the suggested path leaves the picker usable')
folders.stat = async () => { throw new Error('Access denied') }
await rules.load()
assert.equal(rules.pickerStart, '/')
assert.equal(rules.loadError, '')
rules.projects.push({ id: 44, name: 'Old area', color: '#aa3355', archived: true })
assert(!rules.rows.some(row => row.area.id === 44))
rules.rules = [{ id: 12, project_id: 44, enabled: true }]
assert(!rules.rows.some(row => row.area.id === 44), 'A stored rule cannot bring an archived area back as unavailable')
assert.equal(rules.hasActiveImports, false)
assert(!rules.formatTargets.some(area => area.id === 44))
console.log('Automatic import table passed: area rows, immediate saves, activation guard, failed changes and legacy rules.')

// Render the actual result cell with the same API fields returned by the worker fixture.
const resultCell = rulesSource.match(/<td :data-label="t\.lastRun\(\)">[\s\S]*?<\/td>/)[0]
const warning = 'monthly.json — 33 von 50 gebucht. Fehlende Kategorien: Lebensmittel.'
const success = 'independent.json — 1 Zahlung importiert'
async function renderResult(last_message, waiting_messages = [], last_error = null, last_run = 1234) {
	const context = { t: rules.t, formatDate: () => '19.11.26, 22:15' }
	return renderToString(createSSRApp({
		template: resultCell,
		components: {
			TableTooltip: { props: ['messageLines'], computed: { text() { return this.messageLines.map(line => line.message).join('\n') } },
				template: '<span :aria-label="text"><slot /></span>' },
			AlertCircleOutlineIcon: { template: '<svg />' },
		},
		setup: () => ({ ...context, resultDetails: rules.resultDetails.bind(context),
			row: { rule: { last_run, last_message, waiting_messages, last_error } } }),
	}))
}
const waitingResult = await renderResult(success, [warning], false)
assert.match(waitingResult, /aria-label="[^"]*independent\.json[^"]*monthly\.json/)
assert.match(waitingResult, /auto-import-result-alert/)
assert(!waitingResult.replace(/<[^>]*>/gs, '').includes('.json'), 'Long details belong in the tooltip only')
assert.equal((await renderResult(warning, [warning], true)).split(warning).length, 2)
assert.match(await renderResult('invalid.json', [], true), /auto-import-result-alert/)
for (const status of [false, null]) {
	const result = await renderResult(success, [], status)
	assert(!result.includes('auto-import-result-alert'))
	assert(result.includes('19.11.26, 22:15'))
}
assert.match(await renderResult('', [], null, 0), />—</)
console.log('Automatic import results show a timestamp and mark errors or outstanding files, with details in the tooltip.')

const fileName = 'A & B — (2).json'
const archived = rules.resultDetails({ last_message: fileName + ' — rejected: old.json', last_file: { name: fileName, id: 17 },
	waiting_results: [{ message: warning, file: { name: 'monthly.json', id: 42 } }] })
assert.deepEqual(archived.filter(line => line.href).map(({ label, href, after }) => [label, href, after]), [
	[fileName, '/f/17', ' — rejected: old.json'], ['monthly.json', '/f/42', warning.slice('monthly.json'.length)],
])
assert.ok(rules.resultDetails({ last_message: 'old.json — imported', waiting_messages: [] }).every(line => !line.href))
