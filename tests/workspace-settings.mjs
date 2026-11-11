import assert from 'node:assert/strict'
import fs from 'node:fs'

const source = fs.readFileSync(new URL('../src/components/WorkspaceSettings.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const imports = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .+$/gm, '').replace('export default', 'return')
const calls = []
const api = {}
const bindings = { axios: api }
const component = new Function('generateUrl', 'readWorkspaceId', 'extractError', ...imports, script)(url => url, () => 42, (error, fallback) => error?.message || fallback, ...imports.map(name => bindings[name] || {}))
const vm = {
	...component.data(),
	$texts: { workspaces: new Proxy({}, { get: (_, key) => () => key }) },
	$nextTick: callback => callback(), $refs: {},
}
for (const [name, method] of Object.entries(component.methods)) vm[name] = method.bind(vm)
for (const [name, getter] of Object.entries(component.computed)) Object.defineProperty(vm, name, { get: getter.bind(vm) })
vm.broadcastWorkspaces = () => {}
vm.showSuccessMessage = () => {}
vm.showError = () => {}
const workspace = { id: 42, name: 'Original', is_default: true }
vm.workspaces = [workspace]
vm.$texts.common = new Proxy({}, { get: (_, key) => () => key })
vm.workspaceToRename = workspace
assert.equal(vm.workspaceDeleteReason, 'baseCannotBeDeleted')
vm.workspaceToRename = { ...workspace, is_default: false }
assert.equal(vm.workspaceDeleteReason, '')
vm.renamingWorkspace = true
assert.equal(vm.workspaceDeleteReason, 'saveBusy')
vm.renamingWorkspace = false
vm.workspaceToDelete = vm.workspaceToRename
assert.equal(vm.workspaceConfirmationReason, 'deleteConfirmation')
vm.deleteConfirmationText = 'delete'
assert.equal(vm.canDeleteWorkspace, false)
assert.equal(vm.workspaceConfirmationReason, 'deleteConfirmation')
vm.deleteConfirmationText = 'DELETE'
assert.equal(vm.workspaceConfirmationReason, '')
vm.deletingWorkspace = true
assert.equal(vm.workspaceConfirmationReason, 'deleteBusy')
vm.deletingWorkspace = false
vm.resetDeleteWorkspaceModal()

assert.ok(!source.includes('@blur="confirmRenameWorkspace"'), 'Blur never saves workspace drafts')
assert.ok(source.includes('@submit.prevent="saveWorkspace"'), 'The editor submits explicitly')

vm.openRenameWorkspaceModal(workspace)
assert.equal(vm.canSaveWorkspace, false)
await vm.saveWorkspace()
vm.closeRenameWorkspaceModal()
assert.equal(vm.workspaceEditorOpen, false, 'Pristine editor closes directly')
vm.openRenameWorkspaceModal(workspace)
vm.renameWorkspaceName = 'Draft'
vm.closeRenameWorkspaceModal()
assert.equal(vm.confirmDiscard, true)
assert.equal(vm.workspaceEditorOpen, true)
assert.equal(workspace.name, 'Original', 'Closing never mutates the saved name')
vm.confirmDiscard = false
assert.equal(vm.renameWorkspaceName, 'Draft', 'Declining discard keeps the draft')
vm.discardWorkspaceEditor()
assert.equal(vm.workspaceEditorOpen, false)
assert.equal(workspace.name, 'Original')

let resolveSave
api.put = async (url, payload, config) => {
	calls.push({ url, payload, config })
	assert.equal(config.skipWorkspaceHeader, true)
	return new Promise(resolve => { resolveSave = resolve })
}
vm.openRenameWorkspaceModal(workspace)
vm.renameWorkspaceName = '  Renamed  '
const saving = vm.saveWorkspace()
await vm.saveWorkspace()
vm.closeRenameWorkspaceModal()
vm.discardWorkspaceEditor()
assert.equal(calls.length, 1, 'Pending save blocks duplicate submissions and close')
assert.equal(vm.workspaceEditorOpen, true)
assert.equal(workspace.name, 'Original')
assert.equal(calls[0].payload.name, 'Renamed')
resolveSave({ data: {} })
await saving
assert.equal(workspace.name, 'Renamed')
assert.equal(vm.workspaceEditorOpen, false)

vm.openRenameWorkspaceModal(workspace)
vm.renameWorkspaceName = 'Duplicate'
api.put = async () => { throw new Error('Name exists') }
await vm.saveWorkspace()
assert.equal(vm.workspaceEditorOpen, true)
assert.equal(vm.renameWorkspaceName, 'Duplicate')
assert.equal(vm.renameWorkspaceError, 'Name exists')
assert.equal(workspace.name, 'Renamed')
assert.equal(vm.workspaceEditorBusy, false)
vm.renameWorkspaceName = '   '
assert.equal(vm.canSaveWorkspace, false)
await vm.saveWorkspace()
assert.equal(workspace.name, 'Renamed')
vm.renameWorkspaceName = 'Corrected'
api.put = async () => ({ data: {} })
await vm.saveWorkspace()
assert.equal(workspace.name, 'Corrected')
assert.equal(vm.workspaceEditorOpen, false)

vm.openCreateWorkspaceModal()
assert.equal(vm.workspaceToRename, null)
assert.equal(vm.canSaveWorkspace, false)
vm.renameWorkspaceName = 'New draft'
vm.closeRenameWorkspaceModal()
assert.equal(vm.confirmDiscard, true, 'New entries use the same discard guard')
vm.discardWorkspaceEditor()
assert.equal(vm.workspaces.length, 1)
vm.openCreateWorkspaceModal()
vm.renameWorkspaceName = 'Business'
api.post = async (url, payload, config) => {
	calls.push({ url, payload, config })
	assert.equal(config.skipWorkspaceHeader, true)
	return new Promise(resolve => { resolveSave = resolve })
}
const creating = vm.saveWorkspace()
await vm.saveWorkspace()
vm.discardWorkspaceEditor()
assert.equal(calls.length, 2, 'Creating is explicit and cannot be submitted twice')
assert.equal(vm.workspaceEditorOpen, true)
resolveSave({ data: { workspace: { id: 43, name: 'Business' } } })
await creating
assert.equal(vm.workspaces.length, 2)
assert.equal(vm.workspaceEditorOpen, false)
assert.equal(vm.workspaceEditorBusy, false)

const originalConsoleError = console.error
console.error = () => {}
try {
	vm.openCreateWorkspaceModal()
	vm.renameWorkspaceName = 'Duplicate'
	api.post = async () => { throw new Error('Already exists') }
	await vm.saveWorkspace()
	assert.equal(vm.workspaceEditorOpen, true)
	assert.equal(vm.renameWorkspaceName, 'Duplicate')
	assert.equal(vm.renameWorkspaceError, 'Already exists')
	vm.discardWorkspaceEditor()

	vm.openRenameWorkspaceModal(workspace)
	vm.openDeleteWorkspaceModal(workspace)
	assert.equal(vm.workspaceToDelete, null, 'The base workspace cannot be deleted')
	const deletable = vm.workspaces.find(item => item.id === 43)
	vm.openRenameWorkspaceModal(deletable)
	vm.renameWorkspaceName = 'Unsaved'
	vm.openDeleteWorkspaceModal(deletable)
	vm.closeRenameWorkspaceModal()
	assert.equal(vm.workspaceEditorOpen, true, 'Delete confirmation keeps the editor mounted')
	vm.closeDeleteWorkspaceModal()
	assert.equal(vm.renameWorkspaceName, 'Unsaved', 'Cancelling deletion preserves the draft')
	vm.openDeleteWorkspaceModal(deletable)
	let deletes = 0
	api.delete = async (url, config) => { deletes++; assert.equal(config.skipWorkspaceHeader, true); throw new Error('Shared data blocks deletion') }
	vm.deleteConfirmationText = 'delete'
	await vm.confirmDeleteWorkspace()
	assert.equal(deletes, 0, 'Deletion still requires the exact DELETE token')
	vm.deleteConfirmationText = 'DELETE'
	await vm.confirmDeleteWorkspace()
	assert.equal(deletes, 1)
	assert.equal(vm.deleteError, 'Shared data blocks deletion')
	assert.equal(vm.workspaceEditorOpen, true)
	assert.equal(vm.workspaces.length, 2, 'A rejected deletion preserves all workspaces')
	api.delete = async () => ({ data: {} })
	await vm.confirmDeleteWorkspace()
	assert.equal(vm.workspaceToDelete, null)
	assert.equal(vm.workspaceEditorOpen, false)
	assert.equal(vm.workspaces.length, 1)
} finally { console.error = originalConsoleError }
console.log('Workspace dialogs passed: explicit creation/save, dirty-close confirmation, cancellation, failed drafts, pending guards, exact DELETE confirmation and base/server deletion protections; APIs mocked.')

// Visibility failures belong to the table and must survive a transient toast.
vm.showError = component.methods.showError.bind(vm)
api.post = async () => { throw new Error('Visibility unavailable') }
const savedConsoleError = console.error
console.error = () => {}
try {
 await vm.updateWorkspaceVisibility(workspace, true)
 assert.equal(vm.actionError, 'Visibility unavailable')
 assert.equal(workspace.is_hidden, undefined)
 api.post = async () => ({ data: {} })
 await vm.updateWorkspaceVisibility(workspace, true)
 assert.equal(vm.actionError, '')
 assert.equal(workspace.is_hidden, true)
} finally { console.error = savedConsoleError }
console.log('Workspace visibility passed: persistent errors and retry without optimistic state changes; APIs mocked.')

vm.loading = false; vm.loadError = ''; vm.actionError = ''
vm.workspaces = [workspace]
let finishRefresh
api.get = () => new Promise(resolve => { finishRefresh = resolve })
const quietRefresh = vm.fetchWorkspaces({ quiet: true })
assert.equal(vm.loading, false, 'Refreshing workspaces does not unmount the list or its focus trigger')
finishRefresh({ data: [workspace], headers: { 'content-type': 'application/json' } }); await quietRefresh
api.get = async () => { throw new Error('Refresh offline') }
await vm.fetchWorkspaces({ quiet: true })
assert.equal(vm.loadError, '', 'A background failure leaves existing workspace controls visible')
assert.equal(vm.actionError, 'Refresh offline')
assert.equal(vm.workspaces[0].name, workspace.name)
console.log('Workspace background refresh passed: retained list and inline failure.')
