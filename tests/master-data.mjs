import assert from 'node:assert/strict'
import fs from 'node:fs'
const read = path => fs.readFileSync(new URL(`../${path}`, import.meta.url), 'utf8')
const load = path => import(`data:text/javascript,${encodeURIComponent(read(path))}`)
const { groupMasterData, recordFlag } = await load('src/utils/masterData.js')
const { PAYMENT_PARTNER_DETAIL_FIELDS, createPaymentPartnerDetails, isValidPaymentPartnerEmail, normalizePaymentPartnerDetails } = await load('src/utils/paymentPartnerDetails.js')
let script = read('src/components/MasterDataSettings.vue').split('<script>')[1].split('</script>')[0]
const imports = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .+$/gm, '').replace('export default', 'return')
const api = { post: async () => ({ data: {} }) }
const importBindings = { axios: api, generateUrl: url => url, extractError: (error, fallback) => error.message || fallback }
const component = new Function('PAYMENT_PARTNER_DETAIL_FIELDS', 'defineAsyncComponent', 'recordFlag', 'groupMasterData', 'createPaymentPartnerDetails', 'isValidPaymentPartnerEmail', 'normalizePaymentPartnerDetails', 'generateUrl', 'extractError', ...imports, script)(PAYMENT_PARTNER_DETAIL_FIELDS, () => ({}), recordFlag, groupMasterData, createPaymentPartnerDetails, isValidPaymentPartnerEmail, normalizePaymentPartnerDetails, importBindings.generateUrl, importBindings.extractError, ...imports.map(name => importBindings[name] || {}))
const scopes = [
 { key: 'personal', editable: true, categories: [{ id: 1, name: 'Energy', type: 'expense' }, { id: 2, name: ' Electricity ', type: 'expense', parent_category_id: 1 }], 'payment-partners': [{ id: 10, name: 'Supplier', type: 'expense', iban: 'personal', email: 'one@example.test' }] },
 { key: 'house', projectId: 5, editable: true, categories: [{ id: 3, name: 'energy', type: 'expense' }, { id: 4, name: 'electricity', type: 'expense', parent_category_id: 3, is_hidden: true }, { id: 8, name: 'electricity', type: 'income' }], 'payment-partners': [{ id: 11, name: 'supplier', type: 'expense', iban: 'house', email: 'two@example.test' }] },
 { key: 'flat', projectId: 6, editable: true, categories: [], 'payment-partners': [] },
 { key: 'readonly', projectId: 7, editable: false, categories: [], 'payment-partners': [] },
]
const rows = Object.fromEntries(['categories', 'payment-partners'].map(kind => [kind, groupMasterData(scopes, kind)]))
assert.equal(rows.categories.length, 3)
assert.equal(rows.categories.find(row => row.depth === 1).records.house.id, 4)
assert.notEqual(rows.categories.find(row => row.depth === 1).key, rows.categories.find(row => row.type === 'income').key)
const vm = { scopes, rows, admin: false, projectId: null, $texts: { masterData: { parentMissing: () => 'Missing parent' } } }
for (const [name, method] of Object.entries(component.methods)) vm[name] = method.bind(vm)
assert.equal(vm.isGlobalRow(null), false, 'New entries have no global rename warning')
assert.equal(vm.isGlobalRow(rows['payment-partners'][0]), false, 'Local entries have no global marker or warning')
assert.equal(vm.isGlobalRow({ records: { personal: { is_global: '1' } } }), true, 'Global records are recognized regardless of numeric flag encoding')
vm.openEditor('payment-partners', 'expense', rows['payment-partners'][0])
assert.equal(vm.editor.drafts.personal.details.iban, 'personal')
assert.equal(vm.editor.drafts.house.details.iban, 'house')
assert.equal(vm.editor.drafts.flat.details.iban, '')
vm.editor.drafts.house.details.iban = 'changed-house'
assert.equal(vm.editor.drafts.personal.details.iban, 'personal')
vm.editor.drafts.flat.enabled = true
let operations = vm.buildOperations()
assert.equal(operations.find(op => op.action === 'update' && op.projectId === 5).details.iban, 'changed-house')
assert.equal(operations.find(op => op.action === 'update' && op.projectId === 6).details.iban, undefined)
assert.ok(!operations.some(op => op.projectId === 7))
vm.openEditor('categories', 'expense', rows.categories.find(row => row.depth === 1))
vm.editor.drafts.flat.enabled = true
operations = vm.buildOperations()
const creates = operations.filter(op => op.action === 'create')
assert.equal(creates.length, 2, 'Missing parent and child are created in order')
assert.equal(creates[0].name, 'Energy')
assert.equal(operations.find(op => op.action === 'update' && op.projectId === 6).parentCategoryId, creates[0].ref)
vm.openEditor('categories', 'expense', rows.categories.find(row => row.name === 'Energy'))
vm.editor.drafts.personal.enabled = false
operations = vm.buildOperations()
assert.ok(operations.some(op => op.action === 'hide' && op.id === 1))
assert.ok(operations.some(op => op.action === 'hide' && op.id === 2), 'Hiding parent includes the active child')
assert.ok(!operations.some(op => op.action === 'destroy'), 'Disabling never deletes historical identities')
console.log('Master-data grouping, separate drafts, hierarchy and permission checks passed')

// Exercise explicit saving and discard guards with controlled network boundaries.
function editorVm() {
 const state = { ...component.data(), admin: false, projectId: null, scopes: structuredClone(scopes), $emit() {}, $texts: { masterData: { parentMissing: () => 'Missing parent', saveError: () => 'Save failed', refreshError: () => 'Reload saved entries', validationError: () => 'Invalid fields' } } }
 for (const [name, method] of Object.entries(component.methods)) state[name] = method.bind(state)
 for (const [name, getter] of Object.entries(component.computed)) Object.defineProperty(state, name, { get: () => getter.call(state) })
 state.load = async () => true
 state.openEditor('payment-partners', 'expense', state.rows['payment-partners'][0])
 return state
}
let editor = editorVm(), requests = []
api.post = async (_url, body) => { requests.push(body); return { data: {} } }
assert.equal(editor.hasChanges, false)
assert.equal(editor.canSave, false, 'Save is disabled until an editable value changes')
editor.changeDetailScope(editor.scopes.find(scope => scope.key === 'house'))
assert.equal(editor.hasChanges, false, 'Changing the displayed detail scope is not an edit')
editor.editor.name = 'Utility'
editor.editor.drafts.house.details.iban = 'new-house'
editor.editor.drafts.flat.enabled = true
assert.equal(requests.length, 0, 'Name, details and availability remain drafts')
editor.closeEditor()
assert.equal(editor.confirmDiscard, true)
assert.ok(editor.editor, 'Closing a dirty editor asks before discarding')
assert.equal(requests.length, 0, 'Closing never saves')
editor.confirmDiscard = false // Cancel the discard question and continue editing.
assert.equal(editor.editor.drafts.house.details.iban, 'new-house')
editor.load = async () => { for (const scope of editor.scopes) for (const record of scope['payment-partners']) record.name = 'Utility'; return true }
assert.equal(await editor.save(), true)
assert.equal(editor.editor, null, 'Explicit saving closes after a successful refresh')
assert.equal(requests.length, 1)
assert.ok(requests[0].operations.some(op => op.projectId === 5 && op.details?.iban === 'new-house'))
assert.ok(!requests[0].operations.some(op => op.projectId == null && op.details?.iban), 'Scoped details are never copied to personal records')
assert.ok(requests[0].operations.filter(op => op.action === 'update' && typeof op.id === 'number').every(op => [10, 11].includes(op.id)), 'Renames retain stable IDs')
await editor.save()
assert.equal(requests.length, 1, 'A closed editor cannot submit twice')

editor = editorVm(); requests = []
editor.editor.drafts.house.details.iban = 'new-house'
api.post = async (_url, body) => { requests.push(body); throw new Error('Network unavailable') }
assert.equal(await editor.save(), false)
assert.ok(editor.editor, 'A failed save keeps the draft open')
assert.equal(editor.editor.drafts.house.details.iban, 'new-house')
assert.equal(editor.saveError, 'Network unavailable')
assert.equal(editor.saving, false)
assert.equal(editor.hasChanges, true)
editor.editor.name = ''
assert.equal(await editor.save(), false)
assert.equal(requests.length, 1, 'Invalid drafts do not reach the server')
editor.discardAndClose()
assert.equal(editor.editor, null, 'Cancel discards without another request')
assert.equal(requests.length, 1)

editor = editorVm(); requests = []
editor.editor.name = 'Utility'
api.post = async (_url, body) => { requests.push(body); return { data: {} } }
editor.load = async () => false
assert.equal(await editor.save(), false)
assert.equal(editor.refreshRequired, true, 'A committed write with a failed refresh cannot be submitted again')
assert.equal(editor.hasChanges, false, 'Already committed changes are not labelled unsaved')
await editor.save()
assert.equal(requests.length, 1)
editor.load = async () => { for (const scope of editor.scopes) for (const record of scope['payment-partners']) record.name = 'Utility'; return true }
assert.equal(await editor.retrySave(), true)
assert.equal(requests.length, 1, 'Retry after a committed write only reloads, avoiding duplicate creates')
assert.equal(editor.editor, null)
assert.equal(editor.refreshRequired, false)

editor = editorVm(); requests = []
let finishSave
api.post = async (_url, body) => { requests.push(body); await new Promise(resolve => { finishSave = resolve }); return { data: {} } }
editor.editor.drafts.house.details.iban = 'pending'
const pending = editor.save()
editor.closeEditor(); editor.discardAndClose(); await editor.save()
assert.ok(editor.editor, 'Closing and Cancel cannot interrupt an active save')
assert.equal(editor.confirmDiscard, false)
assert.equal(requests.length, 1, 'Repeated save does not duplicate requests')
finishSave(); await pending
assert.equal(editor.editor, null)

for (const kind of ['categories', 'payment-partners']) {
 editor = editorVm(); requests = []
 editor.openEditor(kind, 'expense')
 editor.closeEditor()
 assert.equal(editor.editor, null, 'Pristine creation closes directly')
 editor.openEditor(kind, 'expense')
 editor.editor.name = 'Draft'
 editor.closeEditor()
 assert.equal(editor.confirmDiscard, true, 'New drafts also require confirmation on X/Escape/outside')
 assert.equal(requests.length, 0)
 editor.discardAndClose()
 assert.equal(editor.editor, null)
 assert.equal(editor.confirmDiscard, false)
 editor = editorVm()
 editor.closeEditor()
 assert.equal(editor.editor, null, 'Unchanged edits close directly')
}
const template = read('src/components/MasterDataSettings.vue').split('<script>')[0]
assert.ok(!template.includes('@change="autoSave"'), 'Form changes never trigger writes')
assert.ok(template.includes('@submit.prevent="save"'))
assert.ok(template.includes('@click="discardAndClose">{{ $texts.common.cancel() }}'))
console.log('Master-data explicit saving passed: draft isolation, close confirmation, cancellation, scoped details, failed writes, pending saves and refresh-only retry')

// Deletion belongs to the complete displayed row, independently of detail selection.
function deleteVm() {
 const state = editorVm()
 for (const key of ['deleteGlobalBlocked', 'deletePermissionBlocked', 'deleteChildrenBlocked', 'deleteUsedBlocked', 'deleteActiveBlocked', 'deleteUnsavedBlocked']) state.$texts.masterData[key] = () => key
 return state
}
let deletion = deleteVm()
assert.equal(deletion.canDelete, true, 'Active unused occurrences can be deleted without manual deactivation')
assert.equal(deletion.deleteBlockedReason, '')
assert.equal(deletion.deleteDisabledReason, '', 'Active unused records have no misleading deactivation tooltip')
assert.equal(deletion.canDelete, true)
deletion.editor.detailScope = 'flat' // No record here; deletion still targets personal and house.
requests = []; api.post = async (_url, body) => { requests.push(body); return { data: {} } }
await deletion.deleteRecord()
assert.deepEqual(requests[0].operations, [10, 11].map(id => ({ kind: 'payment-partners', action: 'destroy', id })))
assert.equal(deletion.editor, null)
for (const [mutate, reason] of [
 [state => { state.editor.drafts.house.record.in_use = '1' }, 'deleteUsedBlocked'],
 [state => { state.editor.drafts.house.record.has_children = true }, 'deleteChildrenBlocked'],
 [state => { state.editor.drafts.personal.record.is_global = 1 }, 'deleteGlobalBlocked'],
 [state => { state.scopes.find(scope => scope.key === 'house').editable = false }, 'deletePermissionBlocked'],
]) {
 deletion = deleteVm(); mutate(deletion)
 assert.equal(deletion.canDelete, false)
 assert.equal(deletion.deleteBlockedReason, reason)
 assert.equal(deletion.deleteDisabledReason, reason, 'The disabled action explains the actual deletion guard')
 requests = []; await deletion.deleteRecord()
 assert.equal(requests.length, 0, 'A blocked row never sends a partial deletion')
}
deletion = deleteVm()
deletion.editor.name = 'Changed'
assert.equal(deletion.deleteDisabledReason, 'deleteUnsavedBlocked')
deletion.refreshRequired = true
assert.equal(deletion.deleteDisabledReason, 'Reload saved entries')
deletion.$texts.common = { saveBusy: () => 'Saving' }
deletion.saving = true
assert.equal(deletion.deleteDisabledReason, 'Saving')
// A stale usage/permission result must preserve the modal and surface the server rejection.
deletion = deleteVm()
api.post = async () => { throw new Error('Entry is now in use') }
assert.equal(await deletion.deleteRecord(), false)
assert.ok(deletion.editor)
assert.equal(deletion.saveError, 'Entry is now in use')
assert.equal(deletion.editor.drafts.house.enabled, true, 'A failed delete does not deactivate any draft')
// Native icon/parent selection only changes drafts until explicit saving.
let selection = editorVm(), saves = 0
selection.openEditor('categories', 'expense', selection.rows.categories.find(row => row.depth === 1))
selection.save = () => { saves++ }
selection.changeParent({ key: '' })
assert.equal(selection.editor.parentKey, '')
selection.changeIcon('Home')
assert.equal(selection.editor.icon, 'Home')
assert.equal(selection.hasChanges, true)
assert.equal(saves, 0)
selection.changeDetailScope(selection.scopes.find(scope => scope.key === 'house'))
assert.equal(selection.editor.detailScope, 'house')
assert.equal(saves, 0, 'Selecting details does not save or copy them')
console.log('Master-data native selection and whole-row deletion guards passed')

// Moving the number into the identity group preserves strings and area-local details.
let detailsScript = read('src/components/PaymentPartnerEditFields.vue').split('<script>')[1].split('</script>')[0]
const detailsImports = [...detailsScript.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
detailsScript = detailsScript.replace(/^import .+$/gm, '').replace('export default', 'return')
const detailsComponent = new Function('isValidPaymentPartnerEmail', ...detailsImports, detailsScript)(isValidPaymentPartnerEmail, ...detailsImports.map(() => ({})))
let emitted
const detailVm = { details: { number: '00123', companyName: 'Supplier', iban: 'area-only' }, $emit: (event, value) => { emitted = value } }
Object.assign(detailVm, detailsComponent.data.call(detailVm))
for (const [name, method] of Object.entries(detailsComponent.methods)) detailVm[name] = method.bind(detailVm)
assert.equal(detailVm.openGroup, 'personAndCompany')
assert.equal(detailVm.groupSummary(detailVm.groups[0]), '00123 · Supplier')
assert.equal(detailVm.groupSummary(detailVm.groups.find(group => group.key === 'contact')), undefined)
detailVm.updateDetail('number', '00456')
assert.equal(emitted.number, '00456')
assert.equal(emitted.iban, 'area-only')
assert.equal(detailVm.details.number, '00123', 'Field edits emit a separate draft rather than mutating scope data')
console.log('Partner details passed: grouped number, populated summaries and preserved scope-local values.')

// Explain the actual blocker in either editor, without changing edit permissions.
for (const kind of ['categories', 'payment-partners']) {
 const state = editorVm()
 Object.assign(state.$texts.masterData, {
  detailsGlobalReadOnly: () => 'Global default',
  detailsPermissionReadOnly: () => 'Area permissions',
  detailsEnableArea: name => `Enable ${name}`,
 })
 state.scopes.find(scope => scope.key === 'flat').name = 'House & garden'
 state.openEditor(kind, 'expense', state.rows[kind].find(row => row.type === 'expense' && row.records.personal))
 assert.equal(state.detailReadOnlyHint, '')
 state.editor.drafts.personal.record.is_global = '1'
 assert.equal(state.detailEditable, false)
 assert.equal(state.detailReadOnlyHint, 'Global default')
 state.scopes[0].editable = false
 assert.equal(state.detailReadOnlyHint, 'Global default', 'Global restriction takes precedence over area access')
 state.editor.drafts.personal.record.is_global = false
 assert.equal(state.detailReadOnlyHint, 'Area permissions')
 state.scopes[0].editable = true
 state.editor.drafts.personal.enabled = false
 assert.equal(state.detailEditable, true, 'Existing inactive records remain editable')
 assert.equal(state.detailReadOnlyHint, '')
 state.changeDetailScope(state.scopes.find(scope => scope.key === 'flat'))
 assert.equal(state.detailEditable, false)
 assert.equal(state.detailReadOnlyHint, 'Enable House & garden')
 state.editor.drafts.flat.enabled = true
 assert.equal(state.detailReadOnlyHint, '')
 state.changeDetailScope(state.scopes.find(scope => scope.key === 'readonly'))
 assert.equal(state.detailReadOnlyHint, 'Area permissions', 'No-permission scopes never suggest enabling')
 state.changeDetailScope(state.scopes[0])
 state.editor.drafts.personal.record.is_global = 1
 state.admin = true
 assert.equal(state.detailEditable, true)
 assert.equal(state.detailReadOnlyHint, '', 'Administration can edit global details')
}
console.log('Read-only detail hints passed for categories and partners: global, permission and activation causes; existing inactive records and admin access preserved.')

// Shared identity restrictions are independent of the selected details scope.
for (const kind of ['categories', 'payment-partners']) {
 const state = editorVm()
 Object.assign(state.$texts.masterData, {
  identityGlobalReadOnly: () => 'Global identity',
  identityPermissionReadOnly: () => 'Identity permissions',
  parentChildrenReadOnly: () => 'Has subcategories',
 })
 state.openEditor(kind, 'expense', state.rows[kind].find(row => row.type === 'expense' && row.records.personal))
 assert.equal(state.identityReadOnlyHint, '')
 state.changeDetailScope(state.scopes.find(scope => scope.key === 'readonly'))
 assert.equal(state.identityReadOnlyHint, '', 'Read-only details scope must not imply shared identity is locked')
 state.editor.row.records.personal.is_global = '1'
 assert.equal(state.canEditName, false)
 assert.equal(state.identityReadOnlyHint, 'Global identity')
 assert.equal(state.parentReadOnlyHint, '', 'A shared lock explains all identity fields without a competing hierarchy hint')
 state.admin = true
 assert.equal(state.canEditName, true)
 assert.equal(state.identityReadOnlyHint, '', 'Global administration must not be told it lacks editing rights')
 state.admin = false
 state.editor.row.records.personal.is_global = false
 for (const scope of state.scopes) scope.editable = false
 assert.equal(state.identityReadOnlyHint, 'Identity permissions')
 state.scopes[0].editable = true
 assert.equal(state.identityReadOnlyHint, '')
 if (kind === 'categories') {
  assert.equal(state.editorHasChildren, true)
  assert.equal(state.parentReadOnlyHint, 'Has subcategories')
  const parent = state.editor.parentKey
  state.changeParent({ key: 'different-parent' })
  assert.equal(state.editor.parentKey, parent)
  state.changeIcon('Home')
  assert.equal(state.editor.icon, 'Home', 'A hierarchy lock does not lock the symbol')
  state.openEditor(kind, 'expense', state.rows.categories.find(row => row.depth === 1))
  assert.equal(state.parentReadOnlyHint, '', 'Leaf categories can change their parent')
 } else assert.equal(state.parentReadOnlyHint, '')
}
console.log('Identity hints passed: global administration, shared permissions, scope independence and parent-only hierarchy restrictions.')

// Advanced details save independently; the parent keeps only its own pending changes.
for (const kind of ['categories', 'payment-partners']) {
 const state = editorVm()
 state.$texts.paymentPartnerDetails = { invalidEmail: () => 'Invalid email' }
 state.openEditor(kind, 'expense', state.rows[kind].find(row => row.type === 'expense' && row.records.personal))
 const originalName = state.editor.name
 const originalIcon = state.editor.icon
 state.editor.name = 'Unsaved rename'
 state.editor.icon = 'Home'
 state.editor.drafts.flat.enabled = true
 const parentBefore = state.draftFingerprint()
 state.openAdvanced()
 state.detailDraft.code = '00123'
 state.detailDraft.details.iban = 'personal draft'
 state.changeDetailScope(state.scopes.find(scope => scope.key === 'house'))
 state.detailDraft.code = '00456'
 state.detailDraft.details.iban = 'house draft'
 assert.equal(state.draftFingerprint(), parentBefore)
 assert.equal(await state.save(), false)
 state.closeEditor()
 assert.equal(state.confirmDiscard, false)
 state.closeAdvanced()
 assert.equal(state.advancedDiscard, true)
 state.advancedDiscard = false
 state.cancelAdvanced()
 assert.equal(state.draftFingerprint(), parentBefore, 'Cancel leaves the main draft untouched')
 state.openAdvanced()
 state.detailDraft.code = '00789'
 state.detailDraft.details.iban = 'house-only'
 state.changeDetailScope(state.scopes[0])
 requests = []
 api.post = async (_url, body) => { requests.push(body); return { data: {} } }
 assert.equal(await state.saveAdvanced(), true)
 assert.equal(requests.length, 1)
 assert.equal(requests[0].operations.length, 1)
 const op = requests[0].operations[0]
 assert.equal(op.name, state.editor.drafts.house.record.name, 'Send the stored name, never the pending rename')
 assert.equal(op.action, 'update')
 assert.equal(op.projectId, 5)
 assert.equal(op.parentCategoryId, undefined)
 assert.equal(op.icon, undefined)
 if (kind === 'categories') assert.equal(op.code, '00789')
 else assert.deepEqual(op.details, { iban: 'house-only' })
 assert.equal(state.editor.name, 'Unsaved rename')
 assert.equal(state.editor.drafts.flat.enabled, true)
 assert.equal(state.hasChanges, true, 'Independent save must not mark identity/availability drafts as saved')
 const stored = state.editor.drafts.house.record
 if (kind === 'categories') assert.equal(stored.code, '00789')
 else assert.equal(stored.iban, 'house-only')
 const applied = state.draftFingerprint()
 state.openAdvanced()
 state.advancedDrafts.house.details.email = 'not-an-email'
 assert.equal(await state.saveAdvanced(), false)
 assert.equal(state.editor.detailScope, 'house')
 assert.equal(state.advancedError, 'Invalid email')
 assert.equal(state.draftFingerprint(), applied)
 state.cancelAdvanced()
 // A clean parent remains clean after an independent save; its later cancellation
 // cannot revert saved details or send stale detail payloads.
 state.editor.name = originalName
 state.editor.icon = originalIcon
 state.editor.drafts.flat.enabled = false
 assert.equal(state.hasChanges, false)
 assert.equal(state.canSave, false)
 assert.ok(state.buildOperations().every(operation => operation.code === undefined && !Object.keys(operation.details || {}).length))
 state.openAdvanced()
 const selectedBefore = state.editor.detailScope
 state.changeDetailScope(state.scopes.find(scope => scope.key === 'flat'))
 assert.equal(state.editor.detailScope, selectedBefore, 'An unavailable area cannot be selected')
 state.advancedDrafts.flat.code = 'cannot-create'
 state.advancedDrafts.readonly.code = 'forbidden'
 requests = []
 await state.saveAdvanced()
 assert.equal(requests.length, 0, 'Details save cannot create, enable, or modify unauthorized occurrences')
 state.openAdvanced()
 state.changeDetailScope(state.scopes.find(scope => scope.key === 'house'))
 if (kind === 'categories') state.detailDraft.code = '00999'
 else state.detailDraft.details.iban = 'retry-value'
 const savedBeforeFailure = state.draftFingerprint()
 api.post = async () => { throw new Error('Permission changed') }
 assert.equal(await state.saveAdvanced(), false)
 assert.equal(state.advancedError, 'Permission changed')
 assert.equal(state.draftFingerprint(), savedBeforeFailure)
 assert.ok(state.advancedDrafts)
 let release
 api.post = () => new Promise(resolve => { release = resolve })
 const pending = state.saveAdvanced()
 assert.equal(state.saving, true)
 state.cancelAdvanced(); state.closeAdvanced(); state.discardAndClose()
 assert.ok(state.advancedDrafts)
 assert.ok(state.editor)
 assert.equal(await state.saveAdvanced(), false, 'Duplicate requests are blocked')
 release({ data: {} }); assert.equal(await pending, true)
 assert.equal(state.hasChanges, false)
 state.discardAndClose()
 if (kind === 'categories') assert.equal(stored.code, '00999')
 else assert.equal(stored.iban, 'retry-value')
}
console.log('Independent advanced saves passed: isolated details, preserved parent drafts, save/cancel baselines, validation, permissions, failures and pending guards.')

for (const admin of [false, true]) {
 const state = editorVm()
 state.editor.drafts.personal.record.is_global = 1
 state.admin = admin
 state.openAdvanced()
 state.advancedDrafts.personal.details.number = ' 0007 '
 requests = []
 api.post = async (url, body) => { requests.push({ url, ...body }); return { data: {} } }
 await state.saveAdvanced()
 assert.equal(requests.length, admin ? 1 : 0)
 if (admin) {
  assert.ok(requests[0].url.includes('/admin/master-data'))
  assert.equal(state.editor.drafts.personal.record.number, '0007')
  assert.equal(state.hasChanges, false)
 }
}
{
 const state = editorVm()
 state.editor.name = 'Not saved'
 const record = state.editor.drafts.personal.record
 state.openAdvanced()
 state.detailDraft.details.number = '0088'
 api.post = async () => ({ data: {} })
 assert.equal(await state.saveAdvanced(), true)
 assert.equal(state.hasChanges, true)
 state.discardAndClose()
 assert.equal(record.name, 'Supplier')
 assert.equal(record.number, '0088', 'Cancelling a dirty parent preserves independently saved details')
 state.openEditor('payment-partners', 'expense')
 state.openAdvanced()
 assert.equal(state.advancedDrafts, null, 'No empty advanced dialog for a new entry')
 requests = []
 api.post = async (_url, body) => { requests.push(body); return { data: {} } }
 state.detailDraft.details.number = 'cannot-create'
 await state.saveAdvanced()
 assert.equal(requests.length, 0)
}
console.log('Independent details also passed global/admin access, normalized values, dirty-parent cancellation and unsaved entry guards.')

for (const kind of ['categories', 'payment-partners']) {
 const state = editorVm()
 state.openEditor(kind, 'expense', state.rows[kind].find(row => row.type === 'expense' && row.records.personal))
 const personal = state.scopes[0], house = state.scopes.find(scope => scope.key === 'house')
 state.openAdvanced()
 const change = (key, value) => { if (kind === 'categories') state.advancedDrafts[key].code = value; else state.advancedDrafts[key].details.number = value }
 assert.equal(state.advancedHasChanges, false)
 change(personal.key, '  ')
 assert.equal(state.advancedScopeDirty(personal), false, 'Whitespace-only edits are not marked')
 change(personal.key, '001')
 change(house.key, '002')
 state.changeDetailScope(house)
 assert.equal(state.advancedScopeDirty(personal), true, 'Leaving an area retains its marker')
 assert.equal(state.advancedScopeDirty(house), true)
 change(personal.key, '')
 assert.equal(state.advancedScopeDirty(personal), false, 'Reverting removes only that marker')
 change(personal.key, '001')
 api.post = async () => { throw new Error('Network failed') }
 assert.equal(await state.saveAdvanced(), false)
 assert.equal(state.advancedScopeDirty(personal), true)
 assert.equal(state.advancedScopeDirty(house), true)
 requests = []
 api.post = async (_url, body) => { requests.push(body); return { data: {} } }
 assert.equal(await state.saveAdvanced(), true)
 assert.equal(requests[0].operations.length, 2, 'Save includes both marked areas, not only the selected area')
 state.openAdvanced()
 assert.equal(state.advancedHasChanges, false)
 assert.equal(state.advancedScopeDirty(personal), false)
 assert.equal(state.advancedScopeDirty(house), false)
 state.editor.name = 'Unrelated identity draft'
 assert.equal(state.advancedHasChanges, false)
}
console.log('Advanced scope markers passed: normalization, switching, revert, failure, multi-area save and independent identity drafts.')

// Advanced navigation shows only enabled occurrences that can be saved independently.
for (const kind of ['categories', 'payment-partners']) {
 const state = editorVm()
 state.openEditor(kind, 'expense', state.rows[kind].find(row => row.type === 'expense' && row.records.personal))
 assert.deepEqual(state.advancedScopes.map(scope => scope.key), ['personal', 'house'])
 state.editor.drafts.personal.enabled = false
 state.editor.drafts.flat.enabled = true
 state.openAdvanced()
 assert.deepEqual(state.advancedScopes.map(scope => scope.key), ['house'], 'Inactive and not-yet-created occurrences are omitted')
 assert.equal(state.editor.detailScope, 'house', 'Opening selects the first available area')
 state.advancedDrafts.personal.code = 'ignored'
 state.advancedDrafts.personal.details.number = 'ignored'
 requests = []
 api.post = async (_url, body) => { requests.push(body); return { data: {} } }
 assert.equal(state.advancedHasChanges, false)
 await state.saveAdvanced()
 assert.equal(requests.length, 0, 'Hidden areas cannot submit details')
 state.editor.drafts.house.enabled = false
 state.openAdvanced()
 assert.equal(state.advancedDrafts, null, 'No empty dialog when all existing areas are disabled')
 state.editor.drafts.personal.enabled = true
 state.openAdvanced()
 assert.equal(state.editor.detailScope, 'personal')
 assert.equal(state.detailDraft.code, '', 'Opening/cancelling never overwrites persisted details')
 state.cancelAdvanced()
}
console.log('Advanced area visibility passed: enabled persisted scopes, selection fallback, no empty dialogs or hidden-area writes.')

{
 const state = editorVm()
 const scrolled = []
 state.$el = {
  querySelector: selector => selector === '[data-master-section="payment-partners"]'
   ? { scrollIntoView: options => scrolled.push(options) }
   : null,
 }
 assert.equal(state.scrollToSection('unknown'), false)
 assert.equal(state.scrollToSection('categories'), false)
 assert.equal(state.scrollToSection('payment-partners'), true)
 assert.deepEqual(scrolled, [{ block: 'start', inline: 'nearest', behavior: 'instant' }])
}
console.log('Master-data section scrolling passed.')
