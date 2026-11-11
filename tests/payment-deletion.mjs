import assert from 'node:assert/strict'
import fs from 'node:fs'
const source=fs.readFileSync(new URL('../src/views/TransactionsView.vue',import.meta.url),'utf8')
const begin=source.indexOf('\t\topenConfirm('), end=source.indexOf('\t\topenBudgetGoals()',begin)
const deletion=source.indexOf('\t\tdeleteEntry(entry)'), deletionEnd=source.indexOf('\n\t}\n}\n</script>',deletion)
const api={},writes=[],events=[],toasts=[]
const methods=new Function('axios','generateUrl','extractError','showToast','window','CustomEvent',`return {${source.slice(begin,end)}${source.slice(deletion,deletionEnd)}}`)(api,url=>url,error=>error.message,text=>toasts.push(text),{dispatchEvent:event=>events.push(event.type)},class {constructor(type){this.type=type}})
const strings=new Proxy({},{get:(_,key)=>()=>key})
const vm={confirmDialog:null,filters:{tags:''},entries:[{id:99},{id:12}],$route:{fullPath:'/'},$texts:{entry:strings}}
for(const [name,fn]of Object.entries(methods))vm[name]=fn.bind(vm)
let refreshes=0;vm.fetchData=()=>{refreshes++}
api.delete=async(...args)=>{writes.push(['delete',...args]);return {data:{}}}
api.post=async(...args)=>{writes.push(['post',...args]);return {data:{}}}
await vm.confirmAction();assert.equal(writes.length,0)
const entry={id:99,source_entry_id:20,editable_entry_id:10}
vm.deleteEntry(entry)
assert.equal(vm.confirmDialog.title,'deleteEntryTitle');assert.equal(vm.confirmDialog.message,'deleteEntryMessage')
assert.equal(writes.length,0,'Opening the safety question never deletes')
vm.closeActionConfirm();await vm.confirmAction();assert.equal(writes.length,0)
vm.deleteEntry(entry)
api.delete=async()=>{throw Error('Deletion refused')}
await vm.confirmAction()
assert.equal(vm.confirmDialog.error,'Deletion refused');assert.equal(vm.confirmDialog.busy,false)
assert.equal(vm.entries.length,2);assert.equal(refreshes,0);assert.equal(events.length,0)
let finish
api.delete=(...args)=>{writes.push(['delete',...args]);return new Promise(resolve=>{finish=resolve})}
const pending=vm.confirmAction();entry.editable_entry_id=77
vm.closeActionConfirm();vm.deleteEntry({id:88});await vm.confirmAction()
assert.equal(vm.confirmDialog.busy,true);assert.equal(writes.length,1)
assert.equal(writes[0][1],'/apps/cobudget/api/entries/10','Retry retains the originally confirmed canonical source')
finish({data:{}});await pending
assert.equal(vm.confirmDialog,null);assert.deepEqual(vm.entries,[{id:12}]);assert.equal(refreshes,1)
assert.deepEqual(events,['cobudget-data-changed'])
// Planned payments keep their separate stop-recurrence action and confirmation.
vm.filters.tags='future';vm.deleteEntry({id:55,source_entry_id:7})
assert.equal(vm.confirmDialog.title,'disableFuturePaymentTitle')
assert.equal(vm.confirmDialog.message,'disableFuturePaymentMessage')
assert.equal(vm.confirmDialog.confirmLabel,'disablePayment')
assert.equal(vm.confirmDialog.errorMessage,'futurePaymentDisableError')
await vm.confirmAction()
assert.deepEqual(writes[1],['post','/apps/cobudget/api/entries/55/stop-recurrence'])
// Route changes cancel an unanswered question instead of writing under another view.
vm.filters.tags='';vm.deleteEntry({id:12});vm.$route.fullPath='/payments'
await vm.confirmAction();assert.equal(vm.confirmDialog,null);assert.equal(writes.length,2)
// A delayed rejection cannot replace another question's state.
let fail
api.delete=()=>new Promise((_,reject)=>{fail=reject})
vm.deleteEntry({id:12});const old=vm.confirmAction()
vm.confirmDialog=null;vm.$route.fullPath='/other';vm.deleteEntry({id:15});const current=vm.confirmDialog
fail(Error('Old failure'));await old
assert.equal(vm.confirmDialog,current);assert.equal(current.error,'')
// An action that succeeds after navigation must not remove entries in the new view.
api.delete=()=>new Promise(resolve=>{finish=resolve})
const late=vm.confirmAction();vm.confirmDialog=null;vm.$route.fullPath='/new';vm.entries=[{id:15}]
finish({data:{}});await late
assert.deepEqual(vm.entries,[{id:15}]);assert.equal(refreshes,2)
// Removing a future first payment must delete it, not merely disable its next recurrence.
vm.confirmDialog=null;vm.filters.tags='future'
api.delete=async url=>{writes.push(['delete',url]);return {data:{}}}
vm.deleteEntry({id:71,date:Date.now()/1000+86400,original_date:Date.now()/1000+86400})
assert.equal(vm.confirmDialog.title,'deleteEntryTitle')
await vm.confirmAction()
assert.deepEqual(writes.at(-1),['delete','/apps/cobudget/api/entries/71'])
console.log('Payment deletion passed: explicit safety confirmation, cancel, canonical source IDs, retained failures/retry, pending guards, planned-payment stop semantics and stale route/results; writes mocked.')

// The reused sidebar footer must react to a conditional slot appearing/disappearing.
const Vue = await import('vue')
const { compile } = await import('@vue/compiler-dom')
const footerSource = fs.readFileSync(new URL('../src/components/ModalActions.vue', import.meta.url), 'utf8')
const footerScript = footerSource.split('<script>')[1].split('</script>')[0].replace(/^import .*$/gm, '').replace('export default', 'return')
const button = { render() { return Vue.h('button', this.$slots.default?.()) } }
const footer = new Function('NcButton', 'CbButton', 'CbSplitButton', 'texts', footerScript)(button, button, button, { common: strings })
footer.render = new Function('Vue', compile(footerSource.split('<template>')[1].split('</template>')[0], { mode: 'function', prefixIdentifiers: true }).code)(Vue)
const node = (type, text = '') => ({ type, text, children: [], props: {}, parent: null })
const renderer = Vue.createRenderer({
 createElement: node, createText: text => node('text', text), createComment: text => node('comment', text),
 setText: (el, text) => { el.text = text }, setElementText: (el, text) => { el.text = text },
 patchProp: (el, key, old, value) => { el.props[key] = value },
 parentNode: el => el.parent, nextSibling: el => el.parent?.children[el.parent.children.indexOf(el) + 1] || null,
 insert(el, parent, anchor) { if (el.parent) this.remove(el); el.parent = parent; const index = parent.children.indexOf(anchor); parent.children.splice(index < 0 ? parent.children.length : index, 0, el) },
 remove(el) { const parent = el.parent; if (parent) parent.children.splice(parent.children.indexOf(el), 1); el.parent = null },
})
const showDelete = Vue.ref(false), container = node('root')
const app = renderer.createApp({ render: () => Vue.h(footer, { primaryLabel: 'Save' }, showDelete.value ? { left: () => Vue.h('button', 'Delete') } : {}) })
app.mount(container)
const hasDelete = el => el.text === 'Delete' || el.children.some(hasDelete)
assert.equal(hasDelete(container), false)
showDelete.value = true; await Vue.nextTick()
assert.equal(hasDelete(container), true, 'Delete appears when the same footer switches from creation to editing')
showDelete.value = false; await Vue.nextTick()
assert.equal(hasDelete(container), false, 'Delete disappears again for a new or non-deletable payment')
app.unmount()

const sidebarSource = fs.readFileSync(new URL('../src/components/AddEntryModal.vue', import.meta.url), 'utf8')
const permission = new Function(`return ({${sidebarSource.slice(sidebarSource.indexOf('\t\tcanDeleteEntry()'), sidebarSource.indexOf('\t\tisCreatingNewEntry()'))}}).canDeleteEntry`)()
assert.equal(permission.call({ isEditing: false, entry: {} }), false)
assert.equal(permission.call({ isEditing: true, entry: { id: 10 }, internalEditingEntry: { can_delete: false } }), false)
assert.equal(permission.call({ isEditing: true, entry: { id: 10 }, internalEditingEntry: { can_delete: true } }), true)
const sidebarDelete = new Function('axios', 'generateUrl', 'showToast', 'showRequestError', `return ({${sidebarSource.slice(sidebarSource.indexOf('\t\tasync deleteEntry()'), sidebarSource.indexOf('\t\tevaluateAmount()'))}}).deleteEntry`)(api, url => url, () => {}, () => {})
const sidebarWrites = []
api.delete = async url => sidebarWrites.push(url)
api.post = async url => sidebarWrites.push(url)
const confirmationMethods = new Function('extractError', `return ({${sidebarSource.slice(sidebarSource.indexOf('\t\topenConfirm('), sidebarSource.indexOf('\t\tasync confirmDiscardChanges()'))}})`)(error => error.message)
const editor = { canDeleteEntry: true, isEditing: true, loading: false, confirmDialog: null, entry: { id: 10 }, internalEditingEntry: { id: 10 }, $texts: { entry: strings }, $emit() {}, usesSidebarHistory: () => true, closeSidebar() {} }
for (const [name, fn] of Object.entries(confirmationMethods)) editor[name] = fn.bind(editor)
let pendingDelete = sidebarDelete.call(editor)
assert.equal(editor.confirmDialog.title, 'deleteEntryTitle'); assert.deepEqual(sidebarWrites, [])
await editor.resolveConfirm(false); await pendingDelete; assert.deepEqual(sidebarWrites, [], 'Cancelling never writes')
pendingDelete = sidebarDelete.call(editor)
api.delete = async () => { throw Error('Deletion refused') }
await editor.resolveConfirm(true)
assert.equal(editor.confirmDialog.error, 'Deletion refused'); assert.equal(editor.confirmDialog.busy, false); assert.equal(editor.loading, false)
let finishSidebar
api.delete = url => { sidebarWrites.push(url); return new Promise(resolve => { finishSidebar = resolve }) }
const deleting = editor.resolveConfirm(true)
await editor.resolveConfirm(false); await editor.resolveConfirm(true); await sidebarDelete.call(editor)
assert.equal(editor.confirmDialog.busy, true); assert.equal(sidebarWrites.length, 1, 'Pending writes cannot be duplicated or dismissed')
finishSidebar(); await deleting; await pendingDelete
assert.equal(editor.confirmDialog, null)
assert.deepEqual(sidebarWrites, ['/apps/cobudget/api/entries/10'])
editor.isFutureContext = true
pendingDelete = sidebarDelete.call(editor); assert.equal(editor.confirmDialog.title, 'disableFuturePaymentTitle'); await editor.resolveConfirm(true); await pendingDelete
assert.equal(sidebarWrites[1], '/apps/cobudget/api/entries/10/stop-recurrence')
editor.canDeleteEntry = false; await sidebarDelete.call(editor); assert.equal(editor.confirmDialog, null)
editor.canDeleteEntry = true; editor.loading = true; await sidebarDelete.call(editor); assert.equal(editor.confirmDialog, null)
console.log('Sidebar deletion passed: dynamic footer slot, permissions, native confirmation/cancel, retained failure/retry, pending guards and planned-payment semantics; writes mocked.')

// Discard uses the same native dialog, but never invokes a payment API action.
const discardMethods = new Function(`return ({${sidebarSource.slice(sidebarSource.indexOf('\t\tasync confirmDiscardChanges()'), sidebarSource.indexOf('\t\tclearMobileFocusTimer()'))}})`)()
for (const [name, fn] of Object.entries(discardMethods)) editor[name] = fn.bind(editor)
editor.loading = false; editor.hasUnsavedChanges = true
let closes = 0
editor.closeSidebar = () => { closes++ }
const writesBeforeDiscard = sidebarWrites.length
let closing = editor.requestCloseSidebar()
assert.equal(editor.confirmDialog.title, 'discardChangesTitle')
assert.equal(editor.confirmDialog.confirmLabel, 'discardChangesConfirm')
assert.equal(editor.confirmDialog.action, null)
assert.equal(closes, 0)
await editor.resolveConfirm(false); assert.equal(await closing, false)
assert.equal(closes, 0, 'Cancel, Escape and outside-close keep the editor and its draft')
closing = editor.requestCloseSidebar()
await editor.resolveConfirm(true); assert.equal(await closing, true)
assert.equal(closes, 1, 'Only explicit discard closes the changed editor')
editor.hasUnsavedChanges = false
assert.equal(await editor.requestCloseSidebar(), true); assert.equal(editor.confirmDialog, null)
editor.hasUnsavedChanges = true; editor.loading = true
assert.equal(await editor.requestCloseSidebar(), false)
assert.equal(sidebarWrites.length, writesBeforeDiscard, 'Discard never saves or deletes payments')
console.log('Sidebar discard passed: native confirmation, retained drafts on cancel, explicit discard, unchanged forms and pending guards.')

// Every sidebar safety question now shares NcDialog; no custom dialog fallback.
assert.ok(!sidebarSource.includes('ConfirmModal'))
assert.ok(sidebarSource.includes('<DialogTransition :show="!!(confirmDialog)" v-slot="{ returnFocus }">'))
assert.ok(sidebarSource.includes('<NcDialog :container="null"'))
assert.ok(sidebarSource.includes('confirmDialog.confirmVariant === \'danger\' ? \'error\' : \'primary\''))
const removeAttachment = new Function('axios', 'generateUrl', 'showToast', `return ({${sidebarSource.slice(sidebarSource.indexOf('\t\tasync deleteAttachment('), sidebarSource.indexOf('\t\tasync fetchProjects()'))}}).deleteAttachment`)(api, url => url, () => {})
editor.loading = false; editor.consumeHistoryClose = () => {}; editor.attachments = [{ id: 1 }, { id: 2 }]
let removal = removeAttachment.call(editor, { id: 1 })
assert.equal(editor.confirmDialog.title, 'receiptRemoveTitle'); assert.equal(editor.confirmDialog.confirmVariant, 'danger')
await editor.resolveConfirm(false); await removal
assert.deepEqual(editor.attachments, [{ id: 1 }, { id: 2 }])
removal = removeAttachment.call(editor, { id: 1 })
api.delete = async () => { throw Error('Receipt removal refused') }
await editor.resolveConfirm(true)
assert.equal(editor.confirmDialog.error, 'Receipt removal refused')
assert.equal(editor.attachments.length, 2)
api.delete = async url => sidebarWrites.push(url)
await editor.resolveConfirm(true); await removal
assert.equal(sidebarWrites.at(-1), '/apps/cobudget/api/entries/10/attachments/1')
assert.deepEqual(editor.attachments, [{ id: 2 }])
// Exercise the future-date gate without performing the payment persistence below it.
const saveStart = sidebarSource.indexOf('\t\tasync saveEntry()')
const futureEnd = sidebarSource.indexOf('\n\t\t\tif (this.isFutureContext && !this.isEditing)', saveStart)
const futureGate = new Function('showToast', `return ({${sidebarSource.slice(saveStart, futureEnd)}return 'confirmed';}}).saveEntry`)(() => {})
Object.assign(editor, { isInitializingEntry: false, isEditing: false, isFutureContext: false, isValid: true, evaluateAmount() {}, entry: { date: new Date(Date.now() + 172800000) } })
const writesBeforePlanning = sidebarWrites.length
let planning = futureGate.call(editor)
assert.equal(editor.confirmDialog.title, 'futurePaymentTitle')
assert.equal(editor.confirmDialog.confirmVariant, 'primary')
const questionBeforeDuplicate = editor.confirmDialog
await futureGate.call(editor); assert.equal(editor.confirmDialog, questionBeforeDuplicate)
await editor.resolveConfirm(false); assert.equal(await planning, undefined)
planning = futureGate.call(editor); await editor.resolveConfirm(true); assert.equal(await planning, 'confirmed')
assert.equal(sidebarWrites.length, writesBeforePlanning)
console.log('Remaining sidebar confirmations passed: receipt cancel/error/retry and future-date primary confirmation; writes mocked.')

// Validation and request failures stay visible in the sidebar without losing drafts.
const sidebarSave = new Function('extractError', 'showToast', `return ({${sidebarSource.slice(sidebarSource.indexOf('\t\tasync saveEntry()'), sidebarSource.indexOf('\t\tasync deleteEntry()'))}}).saveEntry`)(error => error.message, () => {})
Object.assign(editor, { entry: { id: 10, description: 'Keep this draft' }, internalEditingEntry: { id: 10 }, isEditing: true, hasUnsavedChanges: true, isValid: false, formError: '' })
await sidebarSave.call(editor)
assert.equal(editor.formError, 'validAmountRequired')
editor.isValid = true
editor.ensureProjectMembers = async () => { throw Error('Save request refused') }
await sidebarSave.call(editor)
assert.equal(editor.formError, 'Save request refused')
assert.equal(editor.entry.description, 'Keep this draft')
assert.equal(editor.loading, false)
const receiptLoad = new Function('axios', 'generateUrl', 'extractError', `return ({${sidebarSource.slice(sidebarSource.indexOf('\t\tasync fetchAttachments('), sidebarSource.indexOf('\t\tattachmentDownloadUrl('))}}).fetchAttachments`)(api, url => url, error => error.message)
editor.$enableReceipts = true
api.get = async () => { throw Error('Receipts unavailable') }
await receiptLoad.call(editor, 10)
assert.equal(editor.receiptsError, 'Receipts unavailable'); assert.equal(editor.attachmentsLoading, false)
api.get = async () => ({ data: { attachments: [{ id: 3 }] } })
await receiptLoad.call(editor, 10)
assert.equal(editor.receiptsError, ''); assert.deepEqual(editor.attachments, [{ id: 3 }])
console.log('Sidebar errors passed: persistent validation/save errors, retained drafts and receipt error recovery; requests mocked.')
