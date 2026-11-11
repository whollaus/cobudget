import assert from 'node:assert/strict'
import fs from 'node:fs'
const { default: paymentLabelBadges } = await import('data:text/javascript;base64,' + Buffer.from(fs.readFileSync(new URL('../src/services/reminderLabels.js', import.meta.url))).toString('base64'))
const api = {}, events = []
const strings = new Proxy({}, { get: (_, k) => () => k })
const windowStub = { dispatchEvent: e => events.push(e), removeEventListener() {} }
function load(file) {
 let script = fs.readFileSync(new URL('../'+file, import.meta.url),'utf8').split('<script>')[1].split('</script>')[0]
 const names = [...script.matchAll(/^import (\w+) from .+$/gm)].map(m=>m[1])
 script = script.replace(/^import .+$/gm,'').replace('export default','return')
 return new Function('showToast','getAppLanguage','generateUrl','formatCalendarLabel','captureDialogFocus','trackDialogTriggers','filterChipIcon','window','document', ...names,script)((message,type)=>events.push({toast:message,type}),()=> 'de',u=>u,d=>d.toISOString(),()=>()=>{},()=>()=>{},key=>key,windowStub,{}, ...names.map(n=>n==='axios'?api:n==='paymentLabelBadges'?paymentLabelBadges:{}))
}
const sidebar = load('src/components/ReminderSidebar.vue')
const view = load('src/views/RemindersView.vue')
function instance(component) {
 const vm = {...component.data(), $texts:{reminders:strings, common:strings, labels:strings}, $emit:(...args)=>events.push(args),$route:{query:{}},$router:{}}
 for(const [name,fn] of Object.entries(component.methods))vm[name]=fn.bind(vm)
 for(const [name,fn] of Object.entries(component.computed))Object.defineProperty(vm,name,{get:fn.bind(vm)})
 return vm
}
api.get=async()=>({data:[]})
let vm=instance(sidebar)
await vm.open()
assert.equal(vm.isOpen,true)
assert.equal(vm.dirty,false)
vm.draft.title='Cancel contract'
assert.equal(vm.dirty,true)
let close=vm.requestClose(false)
assert.equal(vm.confirmation,'discard')
vm.cancelConfirmation()
assert.equal(await close,false)
assert.equal(vm.draft.title,'Cancel contract')
api.post=async()=>{throw {response:{data:{error:'Conflict'}}}}
await vm.save()
assert.equal(vm.error,'Conflict')
assert.equal(vm.isOpen,true)
assert.equal(vm.dirty,true)
let resolveWrite, writes=0
api.post=()=>{writes++;return new Promise(resolve=>{resolveWrite=resolve})}
let pending=vm.save()
assert.equal(await vm.requestClose(false),false,'Closing blocked during save')
await vm.save()
assert.equal(writes,1,'Double save is prevented')
resolveWrite({data:{id:1}});await pending
assert.equal(vm.isOpen,false)
assert.ok(events.some(e=>e.type==='cobudget-reminders-changed'))
vm=instance(sidebar);await vm.open();vm.draft.title='Discard me'
close=vm.requestClose(false);await vm.confirmAction();assert.equal(await close,true)
assert.equal(vm.isOpen,false)
// Standalone creation never fetches payment choices; existing associations are fixed.
let paymentReads = 0
api.get = async () => { paymentReads++; return {data: []} }
vm = instance(sidebar); await vm.open()
assert.equal(paymentReads, 0)
assert.equal(vm.draft.entry_id, null)
await vm.open({id: 7, entry_id: 25, entry_kind: 'shared', project_id: 4, payment_workspace_id: 8, due_date: '2027-04-30'})
assert.deepEqual(vm.paymentLocation, {name: 'project-detail', params: {id: 4}, query: {openPayment: '25', workspace: '8'}})
vm.draft.title = 'Update notes'
let associationPayload
api.post = async (_, body) => { associationPayload = body.data }
await vm.save()
assert.equal(associationPayload.entry_id, 25)
assert.equal(associationPayload.due_date, '2027-04-30', 'Editing preserves hidden legacy deadlines')
const list=instance(view)
list.rows=[
 {id:1,title:'Cancel mobile',description:'Contract details',status:'active',remind_at:1893456000,entry_id:null,labels:['cancel'],payment_labels:[]},
 {id:2,title:'Renew plan',description:'',status:'completed',completed_at:1800000000,remind_at:1893456000,entry_id:3,project_id:4,labels:['renew'],payment_labels:['subscription']},
]
assert.deepEqual(list.filtered.map(r=>r.id),[1,2], 'Completed reminders remain visible by default')
list.status=['active','completed','archived'];list.label=['renew'];assert.deepEqual(list.filtered.map(r=>r.id),[2])
list.label=[];list.reference=['independent'];assert.deepEqual(list.filtered.map(r=>r.id),[1])
list.reference=['independent', 'payment', 'area'];list.search='contract';assert.deepEqual(list.filtered.map(r=>r.id),[1])
list.search='';list.from='2031-01-01';assert.equal(list.filtered.length,0)
let opens=[];list.open=row=>opens.push(row.id)
await list.complete({id:5,remind_at:1},false);assert.deepEqual(opens,[5],'Reactivating a past reminder opens date editor')
let resolveStatus;api.put=()=>new Promise(resolve=>{resolveStatus=resolve});api.get=async()=>({data:{reminders:[]}})
pending=list.complete({id:1,revision:1,remind_at:1893456000},true)
assert.equal(list.busyId,1);resolveStatus();await pending;assert.equal(list.busyId,null)
console.log('Reminder organizer passed: draft guards, retained failures, save serialization, fixed payment links, status/date/reference filters and reactivation.')

// Toolbar changes preserve independent dimensions and reset pagination.
list.page = 3
list.updateFilters({ status: ['active', 'completed'], search: 'contract', label: ['cancel'], reference: ['independent'], from: '', to: '' })
assert.equal(list.page, 1)
assert.equal(list.filterProps.filters.search, 'contract')
const bar = load('src/components/ReminderFilterBar.vue')
const toolbar = { filters: { status: ['completed'], search: 'contract', label: ['cancel'], reference: ['payment'], from: '2026-11-01', to: '2026-11-30' }, $emit: (...args) => events.push(args) }
for (const [name, fn] of Object.entries(bar.methods)) toolbar[name] = fn.bind(toolbar)
assert.equal(toolbar.active('period'), true)
toolbar.remove('period')
assert.deepEqual(events.at(-1), ['update:filters', { ...toolbar.filters, from: '', to: '' }])
toolbar.remove('status')
assert.deepEqual(events.at(-1)[1].status, ['active', 'completed'])
assert.equal(events.at(-1)[1].search, 'contract')
assert.deepEqual(toolbar.defaults(), { status: ['active', 'completed'], search: '', label: [], reference: ['independent', 'payment', 'area'], from: '', to: '' })

// Date-only editing schedules the chosen local day at 09:00, including DST dates.
vm = instance(sidebar)
api.get = async () => ({data: []})
let savedReminder
api.post = async (_, body) => { savedReminder = body.data; return {data: {id: 99}} }
for (const day of ['2027-03-28', '2027-10-31']) {
 await vm.open()
 vm.draft.title = 'Date-only reminder'
 vm.draft.localDate = day
 await vm.save()
 const at = new Date(savedReminder.remind_at * 1000)
 assert.equal(at.getHours(), 9)
 assert.equal(at.getMinutes(), 0)
 assert.equal(at.getDate(), Number(day.slice(8)))
}
const legacyTime = new Date('2027-04-01T14:27:00').getTime() / 1000
await vm.open({id: 99, remind_at: legacyTime, title: 'Existing', labels: [], revision: 1})
vm.draft.description = 'Notes only'
await vm.save()
assert.equal(savedReminder.remind_at, legacyTime, 'Notes-only edits must not rearm an already delivered reminder')
list.status = ['completed']; assert.equal(list.hasActiveFilters, true)
list.resetFilters(); assert.equal(list.hasActiveFilters, false)

// Sort the full filtered set before pagination; never mutate the source rows.
const sorting = instance(view)
sorting.status = ['active', 'completed', 'archived']; sorting.size = 2
sorting.rows = [
 { id: 3, title: 'Zahlung 10', status: 'completed', completed_at: 1800000000, remind_at: 2000000300, keep_open: true, labels: [], payment_labels: [] },
 { id: 2, title: 'Änderung', status: 'active', remind_at: 2000000200, keep_open: true, labels: [], payment_labels: [] },
 { id: 1, title: 'Zahlung 2', status: 'archived', archived_at: 1800000100, remind_at: 2000000100, keep_open: true, labels: [], payment_labels: [] },
 { id: 4, title: 'Zahlung 2', status: 'active', remind_at: 2000000400, keep_open: true, labels: [], payment_labels: [] },
 { id: 5, title: 'Zahlung 2', status: 'completed', completed_at: 1800000000, remind_at: 2000000050, keep_open: true, labels: [], payment_labels: [] },
 { id: 6, title: 'Archiv', status: 'archived', archived_at: 1800000100, remind_at: 2000000010, keep_open: true, labels: [], payment_labels: [] },
]
assert.deepEqual(sorting.sorted.map(row => row.id), [2, 4, 5, 3, 6, 1], 'Status groups precede chronological order')
assert.deepEqual(sorting.paged.map(row => row.id), [2, 4], 'Group the entire result before pagination')
sorting.search = 'Änderung'; assert.deepEqual(sorting.sorted.map(row => row.id), [2])
assert.deepEqual(sorting.rows.map(row => row.id), [3, 2, 1, 4, 5, 6])
// Completing through the actual API/refresh path moves the row rather than hiding it.
sorting.search = ''; sorting.autoOpenHandled = true
const refreshed = sorting.rows.map(row => row.id === 2 ? {...row, status: 'completed', completed_at: 1800000000} : row)
api.put = async () => ({data: {status: 'success'}})
api.get = async () => ({data: {reminders: refreshed}})
await sorting.complete(sorting.rows.find(row => row.id === 2), true)
assert.deepEqual(sorting.sorted.map(row => row.id), [4, 5, 2, 3, 6, 1])
assert.equal(sorting.filtered.length, 6, 'Completing keeps the item in the default overview')
sorting.status = ['active']; assert.deepEqual(sorting.filtered.map(row => row.id), [4], 'Explicit active-only filtering still works')
sorting.resetFilters(); assert.equal(sorting.filtered.length, 4, 'Reset hides all archived rows'); sorting.status = ['active', 'completed', 'archived']
sorting.rows.push({id: 7, title: 'Automatic archive', status: 'active', remind_at: 1, keep_open: false, labels: [], payment_labels: []})
assert.equal(sorting.sorted.find(row => row.id === 7).status, 'archived')
assert.deepEqual(sorting.sorted.slice(0, 4).map(row => row.id), [4, 5, 2, 3], 'Automatic archival also sorts after open and completed reminders')

// Auto-create once per visit on desktop, with notification deep links taking priority.
windowStub.matchMedia = () => ({matches: true})
const auto = instance(view)
let autoOpens = []
auto.open = row => { auto.autoOpenHandled = true; autoOpens.push(row) }
auto.openInitialReminder(); auto.openInitialReminder()
assert.equal(autoOpens.length, 1, 'Refreshes never reopen a manually closed form')
for (const query of [{reminder: '12'}, {entry: '34'}]) {
 auto.autoOpenHandled = false; auto.$route.query = query; auto.openInitialReminder()
 assert.equal(autoOpens.length, 1, 'Deep links must not open a blank draft')
}
auto.autoOpenHandled = false; auto.$route.query = {}; windowStub.matchMedia = () => ({matches: false})
auto.openInitialReminder(); assert.equal(autoOpens.length, 1, 'Mobile starts with the overview')
const explicit = instance(view)
explicit.open({id: 42}); explicit.openInitialReminder()
assert.equal(explicit.autoOpenHandled, true, 'Opening a row before initial loading finishes suppresses auto-create')

// Links preserve workspace isolation, settled read-only rules and navigation races.
const linkSource = fs.readFileSync(new URL('../src/services/paymentLink.js', import.meta.url), 'utf8')
const {openPaymentLink} = await import('data:text/javascript;base64,' + Buffer.from(linkSource).toString('base64'))
let linkEvents = [], current = true
const context = {
 current: () => current, activeWorkspace: () => 8,
 workspaces: async () => [{id: 8}, {id: 9}],
 switchWorkspace: id => linkEvents.push(['switch', id]),
 getEntry: async id => ({id, is_settled: false}),
 open: async entry => linkEvents.push(['open', entry.id]),
 settled: () => linkEvents.push(['settled']), error: () => linkEvents.push(['error']),
}
await openPaymentLink({openPayment: '25', workspace: '8'}, context)
assert.deepEqual(linkEvents, [['open', 25]])
linkEvents = []; await openPaymentLink({openPayment: '25', workspace: '9'}, context)
assert.deepEqual(linkEvents, [['switch', 9]], 'Fetch only after all workspace catalogues reload')
linkEvents = []; await openPaymentLink({openPayment: '25', workspace: '666'}, context)
assert.deepEqual(linkEvents, [['error']], 'Never switch to an unowned workspace')
linkEvents = []; await openPaymentLink({openPayment: '0', workspace: '8'}, context)
assert.deepEqual(linkEvents, [['error']])
linkEvents = []; context.getEntry = async id => ({id, is_settled: true})
await openPaymentLink({openPayment: '25', workspace: '8'}, context)
assert.deepEqual(linkEvents, [['settled']], 'Settled sources must not open an edit form')
linkEvents = []; context.getEntry = async () => {throw new Error('Not found')}
await openPaymentLink({openPayment: '25', workspace: '8'}, context)
assert.deepEqual(linkEvents, [['error']], 'Removed payments or membership cannot open a stale editor')
linkEvents = []; current = false
await openPaymentLink({openPayment: '25', workspace: '8'}, context)
assert.deepEqual(linkEvents, [], 'Late responses after navigation have no effect')

// Status switches change only the draft; Save commits them together.
vm = instance(sidebar)
await vm.open({id: 80, title: 'Existing', remind_at: 2000000000, revision: 2})
assert.equal(vm.draft.keep_open, false, 'Automatic archive is on by default')
vm.setArchived(true)
assert.equal(vm.draft.archived, true)
assert.equal(vm.draft.completed, false)
assert.equal(vm.dirty, true)
vm.setCompleted(true)
assert.equal(vm.draft.archived, true, 'Completion preserves archival')
assert.equal(vm.draft.completed, true)
vm.setArchived(true)
assert.equal(vm.draft.completed, true, 'Archiving preserves completion')
vm.draft.description = 'Archive with these notes'
api.post = async (_, body) => { savedReminder = body.data }
await vm.save()
assert.equal(savedReminder.archived, true)
assert.equal(savedReminder.description, 'Archive with these notes')
await vm.open({id: 80, title: 'Archived', status: 'archived', archived_at: 1800000100, remind_at: 2000000000})
vm.setArchived(false)
assert.equal(vm.draft.archived, false)

const combined = {...sorting.rows[0], completed_at: 1800000000, archived_at: 1800000100}
sorting.rows = [combined]
assert.equal(sorting.currentRows[0].status, 'archived')
assert.match(sorting.statusTooltip(sorting.currentRows[0]), /completed: .*\narchived: /)
sorting.status = ['completed']; assert.equal(sorting.filtered.length, 0, 'Completed selection excludes archived reminders even when completed')
sorting.status = ['archived']; assert.equal(sorting.filtered.length, 1)
let incompleteStatus
api.put = async (_, body) => {incompleteStatus = body.status}
api.get = async () => ({data: {reminders: []}})
await sorting.complete({...combined, status: 'archived', remind_at: 1}, false)
assert.equal(incompleteStatus, 'incomplete', 'Unchecking completion preserves archive state')

// Independent status switches: empty means none, order is irrelevant, archive wins.
const selection = instance(view)
selection.rows = [
 {id: 1, remind_at: 2000000000, title: 'Open', keep_open: true, labels: [], payment_labels: []},
 {id: 2, remind_at: 2000000000, title: 'Done', completed_at: 1800000000, keep_open: true, labels: [], payment_labels: []},
 {id: 3, remind_at: 2000000000, title: 'Both', completed_at: 1800000000, archived_at: 1800000100, keep_open: true, labels: [], payment_labels: []},
]
assert.deepEqual(selection.filtered.map(r => r.id), [1, 2])
selection.status = ['archived']; assert.deepEqual(selection.filtered.map(r => r.id), [3])
selection.status = []; assert.equal(selection.filtered.length, 0)
assert.equal(selection.hasActiveFilters, true)
selection.status = ['completed', 'active']; assert.equal(selection.hasActiveFilters, false)
toolbar.filters.status = ['completed', 'active']; assert.equal(toolbar.active('status'), false)
toolbar.filters.status = []; assert.equal(toolbar.active('status'), true)
selection.resetFilters(); assert.deepEqual(selection.status, ['active', 'completed'])

// Month sections preserve status priority, local month/year boundaries and page limits.
const months = instance(view)
const reminderRow = (id, day, done = false) => ({id, title: String(id), remind_at: new Date(day + 'T09:00:00').getTime() / 1000, keep_open: true, labels: [], payment_labels: [], completed_at: done ? 1800000000 : null})
months.rows = [reminderRow(4, '2026-11-08', true), reminderRow(3, '2027-11-01'), reminderRow(2, '2026-12-01'), reminderRow(1, '2026-11-05'), reminderRow(5, '2026-11-06')]
assert.deepEqual(months.monthGroups.map(g => g.items.map(r => r.id)), [[1,5],[2],[3],[4]])
assert.equal(new Set(months.monthGroups.map(g => g.key)).size, 4, 'Same month in different years or statuses stays distinct')
assert.equal(months.showMonthHeadings, true)
months.size = 1; months.page = 2
assert.deepEqual(months.monthGroups.map(g => g.items.map(r => r.id)), [[5]], 'A page continuing a month retains its group')
assert.equal(months.showMonthHeadings, false, 'Only months on the visible page count')
months.size = 25; months.page = 1
months.rows = [reminderRow(1, '2026-11-05'), reminderRow(2, '2026-11-06', true)]
assert.equal(months.showMonthHeadings, false, 'Different statuses in one month do not need headings')
months.rows.push(reminderRow(3, '2027-11-05'))
assert.equal(months.showMonthHeadings, true, 'Same month in different years needs headings')
months.status = []; assert.deepEqual(months.monthGroups, [])
assert.equal(months.showMonthHeadings, false)

// Row menus route through the shared editor and never delete without confirmation.
const menu = instance(view)
menu.rowAction('edit', {id: 71})
assert.equal(events.at(-1).type, 'cobudget-open-reminder')
assert.equal(events.at(-1).detail.id, 71)
menu.rowAction('delete', {id: 72, revision: 3})
assert.equal(events.at(-1).type, 'cobudget-delete-reminder')
assert.equal(events.at(-1).detail.id, 72)
const eventCount = events.length
menu.busyId = 71; menu.rowAction('delete', {id: 72})
assert.equal(events.length, eventCount, 'Pending row mutations block duplicate menu actions')
let deletes = []
api.delete = async (url, cfg) => { deletes.push({url, cfg}) }
vm = instance(sidebar)
await vm.open({id: 72, title: 'Delete me', revision: 3}, {confirmDelete: true})
assert.equal(vm.confirmation, 'delete')
assert.equal(deletes.length, 0)
vm.cancelConfirmation(); assert.equal(deletes.length, 0)
await vm.open({id: 72, title: 'Delete me', revision: 3}, {confirmDelete: true})
await vm.confirmAction()
assert.equal(deletes.length, 1)
assert.equal(deletes[0].url, '/apps/cobudget/api/reminders/72')
assert.deepEqual(deletes[0].cfg, {skipWorkspaceHeader: true, data: {revision: 3}})
assert.equal(vm.isOpen, false)
await vm.open(); vm.draft.title = 'Unsaved draft'
const rejectedDelete = vm.open({id: 73, title: 'Other reminder'}, {confirmDelete: true})
assert.equal(vm.confirmation, 'discard')
vm.cancelConfirmation(); await rejectedDelete
assert.equal(vm.draft.title, 'Unsaved draft')
assert.equal(vm.confirmation, '')
assert.equal(deletes.length, 1)
vm.busy = true; await vm.open({id: 73}, {confirmDelete: true})
assert.equal(vm.draft.title, 'Unsaved draft', 'Saving prevents menu-driven replacement')

// Payment-link choices are disjoint and combine independently of status.
const links = instance(view)
const future = Math.floor(Date.now() / 1000) + 86400
links.rows = [
 { id: 1, title: 'Standalone', remind_at: future, entry_id: null, labels: [], payment_labels: [] },
 { id: 2, title: 'Personal', remind_at: future, entry_id: 20, project_id: null, labels: [], payment_labels: [] },
 { id: 3, title: 'Area', remind_at: future, entry_id: 30, project_id: 4, labels: [], payment_labels: [] },
 { id: 4, title: 'Settled area', remind_at: future, entry_id: 40, project_id: 4, entry_kind: 'personal', labels: [], payment_labels: [] },
]
assert.deepEqual(links.filtered.map(row => row.id), [1, 2, 3, 4])
for (const [selection, expected] of [
 [['independent'], [1]], [['payment'], [2]], [['area'], [3, 4]],
 [['independent', 'area'], [1, 3, 4]], [[], []],
]) {
 links.reference = selection
 assert.deepEqual(links.filtered.map(row => row.id), expected)
 assert.equal(links.hasActiveFilters, true)
}
links.resetFilters()
assert.equal(links.hasActiveFilters, false)
assert.deepEqual(links.filtered.map(row => row.id), [1, 2, 3, 4])
toolbar.filters.status = ['completed']
toolbar.remove('reference')
assert.deepEqual(events.at(-1)[1].reference, ['independent', 'payment', 'area'])
assert.deepEqual(events.at(-1)[1].status, ['completed'])
const linkChips = { ...toolbar, $texts: { reminders: strings }, statuses: [], labels: [], references: links.references }
linkChips.active = bar.methods.active.bind(linkChips)
linkChips.filters = { ...toolbar.defaults(), reference: ['independent', 'area'] }
assert.equal(bar.computed.chips.call(linkChips)[0].label, 'withoutPayment, paymentFromArea')
linkChips.filters.reference = []
assert.equal(bar.computed.chips.call(linkChips)[0].label, 'reference (0)')
linkChips.filters.reference = ['area', 'payment', 'independent']
assert.equal(bar.computed.chips.call(linkChips).length, 0)
console.log('Reminder payment-link filters passed: disjoint choices, combinations, empty/default/reset and chips.')

// Only action labels are editable; inherited payment labels stay outside the form and writes.
vm = instance(sidebar)
await vm.open({ id: 81, title: 'Subscription', entry_id: 18, remind_at: future, labels: ['cancel'], payment_labels: ['subscription', 'important', 'review'] })
assert.equal(vm.dirty, false)
assert.equal('payment_labels' in vm.draft, false)
assert.deepEqual(paymentLabelBadges(['subscription', 'important', 'review'], vm.$texts).map(label => [label.key, label.icon, label.tone]), [['abo', 'subscription', 'neutral'], ['important', 'important', 'warning'], ['review', 'review', 'error']])
assert.deepEqual(vm.labelOptions.map(option => option.id), ['cancel', 'renew', 'review', 'pay'])
assert.ok(vm.labelOptions.some(option => option.id === 'renew'))
vm.draft.labels.push('renew')
let labelPayload
api.post = async (_, body) => { labelPayload = body.data; return { data: { id: 81 } } }
await vm.save()
assert.deepEqual(labelPayload.labels, ['cancel', 'renew'])
assert.equal('payment_labels' in labelPayload, false)
assert.equal(labelPayload.entry_id, 18)
await vm.open()
assert.equal('payment_labels' in vm.draft, false)
assert.deepEqual(vm.labelOptions.map(option => option.id), ['cancel', 'renew', 'review', 'pay'])
assert.deepEqual(paymentLabelBadges(['subscription', 'subscription', 'unknown'], { labels: strings }).map(label => label.key), ['abo'])
console.log('Reminder labels passed: inherited payment icons, editable choices and separate save payloads.')

// Native navigation buttons use the router and retain the registered draft guard.
vm = instance(sidebar)
await vm.open({ id: 91, entry_id: 25, entry_kind: 'shared', project_id: 4, payment_workspace_id: 8 })
const paymentRoutes = []
vm.$router.push = route => paymentRoutes.push(route)
vm.openPayment()
assert.deepEqual(paymentRoutes, [{name: 'project-detail', params: {id: 4}, query: {openPayment: '25', workspace: '8'}}])
vm.busy = true; vm.openPayment()
vm.busy = false; vm.confirmation = 'discard'; vm.openPayment()
assert.equal(paymentRoutes.length, 1, 'Pending writes and confirmations block navigation')

// Reminder-label filters match any selected action label, never inherited labels.
const labelList = instance(view)
labelList.rows = [
 { id: 1, title: 'No labels', remind_at: future, labels: [], payment_labels: ['review'] },
 { id: 2, title: 'Cancel', remind_at: future, labels: ['cancel'], payment_labels: [] },
 { id: 3, title: 'Renew and review', remind_at: future, labels: ['renew', 'review'], payment_labels: [] },
]
assert.deepEqual(labelList.labelOptions.map(row => row.id), ['cancel', 'renew', 'review', 'pay'])
assert.deepEqual(labelList.filtered.map(row => row.id), [1, 2, 3])
labelList.label = ['review']; assert.deepEqual(labelList.filtered.map(row => row.id), [3])
labelList.label = ['cancel', 'renew']; assert.deepEqual(labelList.filtered.map(row => row.id), [2, 3])
labelList.label = ['pay']; assert.deepEqual(labelList.filtered, [])
labelList.resetFilters(); assert.deepEqual(labelList.filtered.map(row => row.id), [1, 2, 3])
toolbar.remove('label'); assert.deepEqual(events.at(-1)[1].label, [])
assert.deepEqual(bar.computed.shortcuts.call({ $texts: { reminders: strings } }).map(row => row.key), ['status', 'label'])
const labelChips = { ...linkChips, labels: labelList.labelOptions, filters: { ...toolbar.defaults(), label: ['cancel', 'renew'] } }
labelChips.active = bar.methods.active.bind(labelChips)
assert.equal(bar.computed.chips.call(labelChips)[0].label, 'cancel, renew')
console.log('Reminder action-label filtering passed: all labels, union selection, inherited exclusion and reset.')

// Selection follows accepted editor transitions, never an unconfirmed row click.
vm = instance(sidebar)
const editingSelection = instance(view)
editingSelection.selectedReminderId = null
vm.$emit = (name, open, id = null) => { if (name === 'open-changed') editingSelection.selectedReminderId = open ? id : null }
await vm.open({ id: 101, title: 'First reminder' })
assert.equal(editingSelection.isReminderSelected({ id: '101' }), true)
vm.draft.title = 'Unsaved edit'
let switchRow = vm.open({ id: 102, title: 'Second reminder' })
vm.cancelConfirmation(); await switchRow
assert.equal(editingSelection.isReminderSelected({ id: 101 }), true, 'Cancelled switch retains the edited row')
assert.equal(editingSelection.isReminderSelected({ id: 102 }), false)
switchRow = vm.open({ id: 102, title: 'Second reminder' })
await vm.confirmAction(); await switchRow
assert.equal(editingSelection.isReminderSelected({ id: 102 }), true)
assert.equal(editingSelection.isReminderSelected({ id: 101 }), false)
await vm.open()
assert.equal(editingSelection.selectedReminderId, null, 'New drafts have no selected row')
await vm.open({ id: 101, title: 'First reminder' })
await vm.requestClose(false)
assert.equal(editingSelection.selectedReminderId, null, 'Closing clears editingSelection')
await vm.open({ id: 101, title: 'First reminder' })
vm.saved()
assert.equal(editingSelection.selectedReminderId, null, 'Successful save/delete clears editingSelection')
console.log('Reminder selection passed: accepted switches, cancelled discard, new drafts and closing.')

// Relative urgency uses local calendar days and never reclassifies closed rows.
const originalTimezone = process.env.TZ
try {
 for (const timezone of ['Europe/Vienna', 'America/New_York', 'Pacific/Auckland', 'UTC']) {
  process.env.TZ = timezone
  const dueView = instance(view)
  dueView.$texts.reminders = { ...strings, today: () => 'Today', daysAgo: days => `${days} days ago` }
  const timestamp = (year, month, day, hour = 9) => new Date(year, month - 1, day, hour).getTime() / 1000
  dueView.now = timestamp(2026, 11, 5, 0) * 1000
  dueView.rows = [
   { id: 1, remind_at: timestamp(2026, 11, 5), keep_open: true },
   { id: 2, remind_at: timestamp(2026, 11, 2), keep_open: true },
   { id: 3, remind_at: timestamp(2026, 11, 6), keep_open: true },
   { id: 4, remind_at: timestamp(2026, 11, 2), completed_at: timestamp(2026, 11, 3) },
   { id: 5, remind_at: timestamp(2026, 11, 2), archived_at: timestamp(2026, 11, 3) },
   { id: 6, remind_at: timestamp(2026, 10, 1), keep_open: false },
  ]
  assert.deepEqual(dueView.currentRows.map(row => dueView.relativeDateLabel(row)), ['Today', '3 days ago', '', '', '', ''], timezone)
  assert.deepEqual(dueView.currentRows.filter(row => row.status === 'active' && row.daysAgo > 0).map(row => row.id), [2], 'Only overdue open reminders are highlighted')
  dueView.now = timestamp(2026, 11, 6, 0) * 1000
  assert.equal(dueView.currentRows[0].daysAgo, 1, 'Midnight advances the label without a reload')
  // Crossing spring/fall DST (23/25-hour days), month ends and New Year.
  for (const [year, month, day] of [[2026, 3, 30], [2026, 10, 26], [2026, 3, 9], [2026, 11, 2], [2026, 9, 28], [2026, 4, 6], [2027, 1, 1]]) {
   dueView.now = timestamp(year, month, day, 0) * 1000
   assert.equal(dueView.calendarDaysAgo(timestamp(year, month, day - 1, 23)), 1, `${timezone}: yesterday remains one day ago across DST/month/year boundaries`)
  }
 }
} finally {
 if (originalTimezone === undefined) delete process.env.TZ
 else process.env.TZ = originalTimezone
}
console.log('Reminder urgency passed: today, past/future, closed statuses, midnight and DST in four time zones.')

// Calendar retries are account-scoped, serialized and never open/save the editor.
const calendarView = instance(view)
calendarView.$texts.settings = { calendarSyncPending: () => 'Still pending' }
const pendingRow = { id: 201, calendar_sync_pending: true, remind_at: 2000000000, labels: [], title: 'Retry', keep_open: true }
calendarView.rows = [pendingRow]
let resolveRetry, retryCalls = 0
api.post = async (url, body, options) => {
 retryCalls++
 assert.equal(url, '/apps/cobudget/api/reminders/201/calendar-sync')
 assert.deepEqual(body, {})
 assert.deepEqual(options, { skipWorkspaceHeader: true })
 return new Promise(resolve => { resolveRetry = resolve })
}
let retryPromise = calendarView.retryCalendar(pendingRow)
assert.equal(calendarView.calendarRetryId, 201)
await calendarView.retryCalendar(pendingRow)
assert.equal(retryCalls, 1, 'Double clicks cannot start concurrent syncs')
resolveRetry({ data: { reminders: [{ ...pendingRow, calendar_sync_pending: false }] } }); await retryPromise
assert.equal(calendarView.rows[0].calendar_sync_pending, false)
assert.equal(calendarView.calendarRetryId, null)
assert.equal(events.at(-1).type, 'success')
api.post = async () => ({ data: { reminders: [pendingRow] } })
await calendarView.retryCalendar(pendingRow)
assert.equal(calendarView.rows[0].calendar_sync_pending, true)
assert.equal(events.at(-1).toast, 'Still pending', 'Unresolved native failures are not reported as success')
api.post = async () => { throw new Error('Network unavailable') }
await calendarView.retryCalendar(pendingRow)
assert.equal(calendarView.calendarRetryId, null)
assert.equal(calendarView.rows[0].calendar_sync_pending, true)
assert.equal(events.at(-1).type, 'error')
api.post = () => new Promise(resolve => { resolveRetry = resolve })
retryPromise = calendarView.retryCalendar(pendingRow)
calendarView.alive = false
const toastCount = events.length
resolveRetry({ data: { reminders: [] } }); await retryPromise
assert.equal(events.length, toastCount, 'Leaving the view suppresses late retry feedback')
assert.equal(calendarView.rows.length, 1, 'Late retry responses do not change an unmounted overview')
console.log('Reminder calendar retries passed: serialization, scope, success/pending/network feedback and disposal.')

// Quick postponement respects unsaved drafts and never writes their unrelated fields.
vm = instance(sidebar)
await vm.open({ id: 301, title: 'Existing', revision: 4 })
vm.draft.description = 'Unsaved notes'
let postponeWrites = 0, postponePayload
api.post = async (url, payload, options) => { postponeWrites++; postponePayload = { url, payload, options } }
let postponing = vm.postpone({ id: 301, revision: 4 }, 2000000000)
assert.equal(vm.confirmation, 'discard')
vm.cancelConfirmation(); await postponing
assert.equal(postponeWrites, 0)
assert.equal(vm.draft.description, 'Unsaved notes')
postponing = vm.postpone({ id: 301, revision: 4 }, 2000000000)
await vm.confirmAction(); await postponing
assert.equal(vm.isOpen, false)
assert.deepEqual(postponePayload, {url:'/apps/cobudget/api/reminders/301/postpone',payload:{revision:4,remindAt:2000000000},options:{skipWorkspaceHeader:true}})
vm = instance(sidebar)
await vm.open({ id: 301, title: 'Existing', revision: 4 })
api.post = async () => { throw { response: { data: { error: 'Changed elsewhere' } } } }
await vm.postpone({id:301, revision:4},2000000000)
assert.equal(vm.isOpen, true, 'Failed postponement keeps the editor')
assert.equal(vm.busy, false)
assert.equal(events.at(-1).toast, 'Changed elsewhere')
let finishPostpone
api.post = () => new Promise(resolve => { finishPostpone = resolve; postponeWrites++ })
postponing = vm.postpone({id:301, revision:4},2000000000)
const pendingWrites = postponeWrites
await vm.postpone({id:301, revision:4},2000000000)
assert.equal(postponeWrites, pendingWrites, 'Concurrent quick actions are ignored')
finishPostpone({}); await postponing
const dateChoice = instance(view)
dateChoice.chooseReminderDate({id:301})
assert.equal(events.at(-1).type, 'cobudget-reminder-choose-date', 'Custom date opens the guarded editor without saving')
const timezoneBeforeSnooze = process.env.TZ
try {
 for (const timezone of ['Europe/Vienna','America/New_York','Pacific/Auckland','UTC']) {
  process.env.TZ = timezone
  for (const [year,month,day] of [[2026,3,28],[2026,10,24],[2026,12,31],[2028,2,28]]) {
   const menu = instance(view)
   menu.now = new Date(year,month-1,day,23,59).getTime()
   for (const days of [1,7]) {
    const expected = new Date(year,month-1,day+days,9)
    assert.equal(menu.postponeTime(days),expected.getTime()/1000, `${timezone}: local 9am across DST/year/leap-day boundaries`)
   }
  }
 }
} finally {
 if (timezoneBeforeSnooze === undefined) delete process.env.TZ
 else process.env.TZ = timezoneBeforeSnooze
}
console.log('Reminder postponement passed: draft guards, conflicts, serialization, custom-date routing and local 9am across DST.')

// Mobile date headers preserve local dates and restart on page/status boundaries.
const daily = instance(view)
const timezoneBeforeDaily = process.env.TZ
try {
 process.env.TZ = 'Europe/Vienna'
 const entries = ['2026-03-28T23:30:00','2026-03-29T00:15:00','2026-03-29T09:00:00','2027-03-29T09:00:00'].map(value=>({remind_at:new Date(value).getTime()/1000}))
 assert.deepEqual(entries.map(row=>daily.dayKey(row)),['2026-03-28','2026-03-29','2026-03-29','2027-03-29'])
 assert.deepEqual(entries.map((row,index)=>daily.startsDayGroup(entries,index)),[true,true,false,true])
 assert.equal(daily.startsDayGroup(entries.slice(2),0),true,'Continuing a day on a new page still has a heading')
 assert.equal(daily.startsDayGroup([entries[2]],0),true,'A separate status group starts its own heading')
} finally {
 if(timezoneBeforeDaily===undefined)delete process.env.TZ
 else process.env.TZ=timezoneBeforeDaily
}
console.log('Mobile reminder date groups passed: local midnight, DST, years and page/status boundaries.')

// An open overview crosses the same inclusive 14-day boundary without a reload.
const archival = instance(view)
const completedAt = Date.UTC(2026, 9, 20, 14, 30) / 1000
const archiveAt = completedAt + 14 * 86400
archival.rows = [
 {id:1, remind_at:completedAt - 30 * 86400, completed_at:completedAt, keep_open:false},
 {id:2, remind_at:completedAt, completed_at:completedAt, keep_open:true},
 {id:3, remind_at:completedAt, completed_at:null, keep_open:false},
 {id:4, remind_at:completedAt, completed_at:completedAt, keep_open:false, archived_at:completedAt + 1},
]
archival.now = archiveAt * 1000 - 1
assert.equal(archival.currentRows[0].status, 'completed', 'An old reminder date does not shorten the completion grace period')
assert.equal(archival.currentRows[2].status, 'active')
archival.now += 1
assert.deepEqual(archival.currentRows.map(r=>r.status), ['archived','completed','archived','archived'])
assert.equal(archival.currentRows[0].effective_archived_at, archiveAt)
assert.equal(archival.currentRows[0].completed_at, completedAt)
assert.equal(archival.currentRows[3].effective_archived_at, completedAt + 1, 'Manual archive date takes precedence')
archival.rows[0].remind_at = archiveAt + 30 * 86400
assert.equal(archival.currentRows[0].status, 'archived', 'Completion age applies even when the reminder date is in the future')
console.log('Reminder automatic archival passed: completion clock, exact threshold, opt-out, manual dates and live overview refresh.')

// Conflict-only actions never trigger the write/retry endpoint before an explicit choice.
const conflictView = instance(view)
let calendarRequests = 0
api.post = async () => { calendarRequests++; return {data:{reminders:[]}} }
const conflicted = {id:42, calendar_sync_pending:true, calendar_conflict:{token:'snapshot',fields:{title:{local:'Local',calendar:'Remote'}}}}
await conflictView.retryCalendar(conflicted)
assert.equal(calendarRequests,0)
assert.equal(conflictView.calendarConflictRow,conflicted)
const conflictDialog = instance(load('src/components/ReminderCalendarConflict.vue'))
conflictDialog.row = conflicted
assert.equal(conflictDialog.ready,false)
await conflictDialog.save();assert.equal(calendarRequests,0)
conflictDialog.choices={title:'calendar'}
assert.equal(conflictDialog.ready,true)
api.post=async(url,body)=>{calendarRequests++;assert.equal(body.token,'snapshot');assert.deepEqual(body.choices,{title:'calendar'});throw {response:{data:{error:'Stale conflict'}}}}
await conflictDialog.save()
assert.equal(conflictDialog.error,'Stale conflict')
assert.equal(conflictDialog.row,conflicted)
assert.equal(conflictDialog.busy,false)
// Imported non-default hours remain visible and survive a date-only edit.
const timedSidebar=instance(sidebar)
const customAt=new Date(2030,4,10,14,30).getTime()/1000
await timedSidebar.open({id:7,title:'Calendar time',remind_at:customAt,labels:[],status:'active'})
assert.equal(timedSidebar.hasCustomReminderTime,true)
assert.equal(timedSidebar.draft.localTime,'14:30')
timedSidebar.draft.localDate='2030-05-11'
api.post=async(url,body)=>{assert.equal(body.data.remind_at,new Date(2030,4,11,14,30).getTime()/1000);return {data:{}}}
await timedSidebar.save()
console.log('Calendar UI passed: explicit conflict choices, no implicit retry, retained stale errors and imported clock preservation.')
