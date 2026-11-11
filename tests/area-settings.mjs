import assert from 'node:assert/strict'
import fs from 'node:fs'
const source = fs.readFileSync(new URL('../src/views/ProjectSettingsView.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const imports = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .+$/gm, '').replace('export default', 'return')
const api = {}, requests = [], routes = []
const component = new Function('generateUrl', 'extractError', 'showToast', 'getAppLocale', ...imports, script)(url => url, error => error.message, () => {}, () => 'de', ...imports.map(name => name === 'axios' ? api : {}))
const strings = new Proxy({}, { get: (_, key) => () => key })
const vm = { ...component.data(), id: '1', currentUserId: 'owner', $enableSharedProjects: true, $texts: { areaSettings: strings, areaDetail: strings, memberSearch: strings }, $emit() {}, $router: { push: route => routes.push(route) } }
for (const [name, fn] of Object.entries(component.methods)) vm[name] = fn.bind(vm)
for (const [name, fn] of Object.entries(component.computed)) Object.defineProperty(vm, name, { get: fn.bind(vm) })
const project = () => ({ id: 1, name: 'Home', color: '#0082c9', owner_id: 'owner', is_owner: true, is_archived: false, member_count: 1, members: [{id: 'owner',displayName:'Owner',shareBasisPoints:10000}], member_management_locked: false, settings_actions: { delete_block_reason: null, archive_block_reason: null } })
let state = project()
vm.$texts.common = strings
vm.project = project()
assert.equal(vm.deleteActionHint, '')
vm.project.settings_actions.delete_block_reason = 'payments_or_history'
assert.equal(vm.deleteActionHint, 'deleteBlockedHistory')
vm.project.settings_actions.delete_block_reason = 'budget_goals'
assert.equal(vm.deleteActionHint, 'deleteBlockedBudget')
vm.project.settings_actions.archive_block_reason = 'open_shared_payments'
assert.equal(vm.archiveActionHint, 'archiveBlockedWithOpenEntries')
vm.project.is_archived = true
assert.equal(vm.archiveActionHint, '', 'Restoring an archived area does not inherit the archive restriction')
vm.project.is_archived = false
vm.project.settings_actions.archive_block_reason = null
vm.archiving = true
assert.equal(vm.archiveActionHint, 'waitBusy')
vm.archiving = false
vm.availabilityError = 'Cannot verify area actions'
assert.equal(vm.archiveActionHint, 'Cannot verify area actions')
vm.availabilityError = ''
vm.project.settings_actions.delete_block_reason = null
vm.actionLoading = true
assert.equal(vm.deleteActionHint, 'loading')
vm.availabilityError = 'Cannot check permissions'
assert.equal(vm.deleteActionHint, 'Cannot check permissions')
vm.actionLoading = false; vm.availabilityError = ''
vm.savingProject = true
assert.equal(vm.deleteActionHint, 'waitBusy')
vm.savingProject = false

vm.getCurrentUserId = () => 'owner'
api.get = async url => ({ data: url.includes('/entries') ? { total: 0 } : structuredClone(state) })
api.post = async (...args) => { requests.push(['post',...args]); return { data: {} } }
api.delete = async (...args) => { requests.push(['delete',...args]); return { data: {} } }
api.put = async (url, body) => { requests.push(['put',url,body]); return { data: { ...state,...body } } }
await vm.fetchProjectSettings()
assert.equal(vm.loadError,'')
assert.equal(vm.projectMemberCount,1)
vm.openShareDialog()
assert.equal(vm.shareDialogOpen,false,'Personal areas have no split editor')
vm.shareDialogOpen=true
assert.equal(vm.canEditShares,false,'A personal area cannot edit its fixed 100% share even if the dialog state is forced')
vm.shareDialogOpen=false
assert.equal(vm.deleteBlockedReason,'')
vm.project.settings_actions.delete_block_reason='payments_or_history'
vm.project.settings_actions.archive_block_reason='open_shared_payments'
await vm.deleteProject(); await vm.toggleArchiveProject()
assert.equal(requests.length,0,'Blocked actions never send writes or show confirmation')
assert.equal(vm.deleteBlockedReason,'deleteBlockedHistory')
vm.project.settings_actions.delete_block_reason='budget_goals'
assert.equal(vm.deleteBlockedReason,'deleteBlockedBudget')
vm.project.settings_actions=undefined
assert.equal(vm.deleteBlockedReason,'actionsUnavailable','Missing eligibility fails closed')
await vm.fetchProjectSettings()
// Fresh preflight finds a new blocker before confirmation.
state.settings_actions.delete_block_reason='budget_goals'
await vm.deleteProject()
assert.equal(vm.confirmDialog,null)
assert.equal(vm.deleteBlockedReason,'deleteBlockedBudget')
assert.equal(requests.length,0)
state=project(); await vm.fetchProjectSettings()
const flush=()=>new Promise(resolve=>setImmediate(resolve))
let action=vm.deleteProject(); await flush()
assert.equal(vm.confirmDialog.confirmVariant,'danger')
await vm.deleteProject()
vm.resolveConfirm(false); await action
assert.equal(requests.length,0,'Cancellation and duplicate activation do not delete')
action=vm.deleteProject(); await flush();vm.resolveConfirm(true);await action
assert.equal(requests.filter(r=>r[0]==='delete').length,1)
assert.deepEqual(routes.at(-1),{name:'projects'})
// Archive and restoration remain separate from delete; solo payments are not an archive blocker.
await vm.fetchProjectSettings();vm.hasAnyEntries=true;vm.hasActiveEntries=true
action=vm.toggleArchiveProject();await flush();vm.resolveConfirm(false);await action
assert.equal(vm.isArchived,false,'Cancelling archival keeps the area active')
action=vm.toggleArchiveProject();await flush();vm.resolveConfirm(true);await action
assert.equal(vm.isArchived,true)
assert.equal(requests.at(-1)[1],'/apps/cobudget/api/projects/1/archive')
state.is_archived=true; action=vm.toggleArchiveProject();await flush();vm.resolveConfirm(true);await action
assert.equal(requests.at(-1)[1],'/apps/cobudget/api/projects/1/unarchive')
// A concurrent server refusal is shown inline and updates eligibility.
state=project();await vm.fetchProjectSettings()
api.post=async()=>{ state.settings_actions.archive_block_reason='open_shared_payments';throw new Error('Settle first') }
action=vm.toggleArchiveProject();await flush();vm.resolveConfirm(true);await action
assert.equal(vm.archiveError,'Settle first')
assert.equal(vm.archiveBlockedReason,'archiveBlockedWithOpenEntries')
assert.equal(vm.isArchived,false)
assert.equal(vm.busy,false)
// Failed reads offer retry; no stale actions remain visible.
const get=api.get;api.get=async()=>{throw new Error('Offline')}
await vm.fetchProjectSettings()
assert.equal(vm.loadError,'Offline');assert.equal(vm.loading,false)
api.get=get;state=project();await vm.fetchProjectSettings();assert.equal(vm.loadError,'')
vm.editProjectData.name='Changed'
api.put=async()=>{throw new Error('Save refused')}
await vm.updateProject()
assert.equal(vm.projectError,'Save refused')
assert.equal(vm.editProjectData.name,'Changed','Failed saves retain the editable draft')
assert.equal(vm.project.name,'Home')
assert.equal(vm.savingProject,false)
// Pending saves are not duplicated; navigating away cannot overwrite the next area.
let finish
api.put=()=>new Promise(resolve=>{finish=resolve})
action=vm.updateProject();assert.equal(vm.busy,true);vm.id='2';vm.project={...project(),id:2,name:'Other'}
finish({data:{name:'Changed',color:'#fff'}});await action
assert.equal(vm.project.name,'Other');assert.equal(vm.busy,false)
// Invalid totals stay visible, not capped at 100 in the summary.
assert.equal(vm.formatSharePercent(12000),'120')
vm.project=project();vm.shareRows=[{id:'owner',sharePercent:60},{id:'other',sharePercent:60}]
assert.equal(vm.sharesSumValid,false);assert.equal(vm.canSaveShares,false)
vm.project.member_management_locked=true
await vm.saveProjectShares()
vm.project.is_owner=false;vm.project.owner_id='other'
assert.equal(vm.canManageProject,false)
assert.equal(vm.deleteBlockedReason,'ownerOnly')
console.log('Area settings passed: eligibility, permissions, preflight, cancellation, native confirmation, archive/restore, inline errors, failed drafts, pending saves and route identity.')

// Name/color autosave is independent of the unsaved percentage draft.
vm.id='1';vm.project=project();vm.projectSaveQueue={};vm.projectError='';vm.resetProjectForm()
const autosaves=[]
api.put=async(url,values)=>{autosaves.push({...values});return{data:{...values}}}
await vm.updateProject('name');await vm.editColorChanged(vm.project.color)
assert.equal(autosaves.length,0,'Unchanged identity values do not write')
vm.editProjectData.name='   ';await vm.updateProject('name')
assert.equal(vm.projectNameInvalid,true);assert.equal(autosaves.length,0)
await vm.editColorChanged('#123456')
assert.equal(autosaves.at(-1).name,'Home','Color saves use the persisted name, not an invalid name draft')
assert.equal(vm.editProjectData.name,'   ')
vm.editProjectData.name=' Renamed ';await vm.updateProject('name')
assert.equal(vm.project.name,'Renamed');assert.equal(vm.editProjectData.name,'Renamed')
assert.equal(vm.projectNameInvalid,false)
assert.equal(vm.shareRows[0].sharePercent,60,'Autosave does not change unsaved member percentages')
// Serialize changes, collapse duplicate blur/Enter, preserve newer uncommitted typing.
const completions=[]
api.put=(url,values)=>{autosaves.push({...values});return new Promise(resolve=>completions.push(()=>resolve({data:{...values}})))}
vm.editProjectData.name='First';action=vm.updateProject('name')
await vm.updateProject('name')
await vm.editColorChanged('#abcdef')
vm.editProjectData.name='Still typing'
completions.shift()();await flush()
assert.equal(vm.editProjectData.name,'Still typing','Earlier response cannot replace newer typing')
assert.deepEqual(autosaves.at(-1),{name:'First',color:'#abcdef'})
completions.shift()();await action
assert.equal(vm.project.name,'First');assert.equal(vm.editProjectData.name,'Still typing')
assert.equal(completions.length,0,'Duplicate blur and Enter are not replayed')
// Returning to the original color while the first change is pending is still saved.
const originalColor=vm.project.color
const colorAction=vm.editColorChanged('#ffffff');await vm.editColorChanged(originalColor)
completions.shift()();await flush();assert.equal(autosaves.at(-1).color,originalColor)
completions.shift()();await colorAction;assert.equal(vm.project.color,originalColor)
// Error retry saves only committed fields, and does not submit half-entered names.
api.put=async()=>{throw new Error('Offline')}
await vm.editColorChanged('#000000')
assert.equal(vm.projectError,'Offline');assert.equal(vm.editProjectData.color,'#000000')
api.put=async(url,values)=>{autosaves.push({...values});return{data:{...values}}}
await vm.flushProjectChanges()
assert.equal(autosaves.at(-1).name,'First');assert.equal(vm.projectError,'')
assert.equal(vm.editProjectData.name,'Still typing')
vm.project.is_owner=false;vm.project.owner_id='someone-else'
const count=autosaves.length
await vm.updateProject('name');await vm.editColorChanged('#111111')
assert.equal(autosaves.length,count,'Read-only users cannot autosave')
console.log('Area identity autosave passed: validation, no-op detection, independent fields, serialization, newer drafts, error retry and permissions.')

// Member creation stays inside its dialog; cancellation never writes.
vm.$texts.entry = strings
vm.id='1';state=project();await vm.fetchProjectSettings()
vm.openMemberDialog();assert.equal(vm.memberDialogOpen,true)
vm.newMembers=[{id:'first',displayName:'First'}]
let memberWrites=[]
api.post=async(url,body)=>{memberWrites.push(body.userId);throw new Error('Add failed')}
action=vm.closeMemberDialog();await flush();vm.resolveConfirm(false);await action
assert.equal(vm.newMembers.length,1,'Rejecting discard retains the selected members')
vm.cancelMemberDialog();assert.equal(vm.memberDialogOpen,false);assert.equal(memberWrites.length,0)
await vm.addMembers();assert.equal(memberWrites.length,0,'Closed dialogs cannot submit')
vm.openMemberDialog();vm.newMembers=[{id:'first',displayName:'First'}]
await vm.addMembers();assert.equal(vm.memberAddError,'Add failed');assert.equal(vm.memberDialogOpen,true)
assert.equal(vm.newMembers[0].id,'first','Failed members stay selected for retry')
api.post=()=>new Promise(resolve=>{finish=resolve})
action=vm.addMembers();assert.equal(vm.addingMembers,true)
vm.cancelMemberDialog();await vm.closeMemberDialog();await vm.addMembers()
assert.equal(vm.memberDialogOpen,true,'Pending requests prevent dismissal and duplicate submission')
finish({data:{}});await action;assert.equal(vm.memberDialogOpen,false);assert.equal(vm.newMembers.length,0)
vm.openMemberDialog();vm.newMembers=[{id:'first',displayName:'First'},{id:'second',displayName:'Second'}]
memberWrites=[]
api.post=async(url,body)=>{memberWrites.push(body.userId);if(body.userId==='second')throw new Error('Second failed');return{data:{}}}
await vm.addMembers();assert.deepEqual(memberWrites,['first','second']);assert.equal(vm.memberDialogOpen,true)
assert.deepEqual(vm.newMembers.map(m=>m.id),['second'],'Partial success retries only failed members')
assert.equal(vm.memberAddError,'Second failed')
vm.cancelMemberDialog();vm.project.member_management_locked=true;vm.openMemberDialog()
assert.equal(vm.memberDialogOpen,false,'Locked areas cannot open member creation')
console.log('Area member dialog passed: cancellation, discard confirmation, failed drafts, partial success, pending guards and permissions.')

// Ownership selection is a draft until the existing safety question is confirmed.
const sharedProject=()=>({...project(),member_count:2,members:[...project().members,{id:'next-owner',displayName:'Next owner',shareBasisPoints:0,isActive:true}]})
state=sharedProject();await vm.fetchProjectSettings()
let ownerWrites=0
api.put=async()=>{ownerWrites++;throw new Error('Transfer refused')}
vm.openOwnerDialog();assert.equal(vm.ownerDialogOpen,true);assert.equal(vm.canSubmitOwner,false)
vm.selectedOwnerId='next-owner';vm.cancelOwnerDialog()
assert.equal(vm.ownerDialogOpen,false);assert.equal(ownerWrites,0)
vm.openOwnerDialog();vm.selectedOwnerId='next-owner'
action=vm.closeOwnerDialog();await flush();vm.resolveConfirm(false);await action
assert.equal(vm.selectedOwnerId,'next-owner','Rejecting discard preserves the selection')
action=vm.transferSelectedOwnership();await flush()
assert.equal(vm.confirmDialog.title,'transferOwnership','Admin changes retain the existing safety question')
vm.resolveConfirm(false);await action;assert.equal(ownerWrites,0)
assert.equal(vm.ownerDialogOpen,true)
action=vm.transferSelectedOwnership();await flush();vm.resolveConfirm(true);await action
assert.equal(vm.ownerError,'Transfer refused');assert.equal(vm.selectedOwnerId,'next-owner')
assert.equal(vm.ownerDialogOpen,true,'Server errors stay in the editor with its draft')
api.put=()=>{ownerWrites++;return new Promise(resolve=>{finish=resolve})}
action=vm.transferSelectedOwnership();await flush();vm.resolveConfirm(true);await flush()
vm.cancelOwnerDialog();await vm.closeOwnerDialog();await vm.transferSelectedOwnership()
assert.equal(vm.ownerDialogOpen,true);assert.equal(ownerWrites,2,'Pending transfer cannot be closed or duplicated')
finish({data:{owner_id:'next-owner',members:state.members}});await action
assert.equal(vm.ownerDialogOpen,false);assert.equal(vm.canManageProject,false)
vm.openOwnerDialog();assert.equal(vm.ownerDialogOpen,false,'Non-owners cannot open the editor')
state=sharedProject();await vm.fetchProjectSettings();vm.openOwnerDialog();vm.selectedOwnerId='next-owner'
action=vm.transferSelectedOwnership();await flush();vm.id='2';vm.resolveConfirm(true);await action
assert.equal(ownerWrites,2,'A route change during confirmation never transfers the old area')
console.log('Area admin dialog passed: draft isolation, discard, safety confirmation, errors, pending guards, success and route permissions.')

// Split editing uses an isolated dialog draft; the list retains persisted values.
vm.id='1';state=sharedProject();vm.resetOwnerDialog();await vm.fetchProjectSettings()
vm.openShareDialog();assert.equal(vm.shareDialogOpen,true);assert.equal(vm.canSaveShares,false)
vm.setEqualShares();assert.deepEqual(vm.shareRows.map(m=>m.sharePercent),[50,50])
assert.deepEqual(vm.savedShareRows.map(m=>m.sharePercent),[100,0],'List values never follow unsaved edits')
action=vm.closeShareDialog();await flush();vm.resolveConfirm(false);await action
assert.equal(vm.shareDialogOpen,true);assert.equal(vm.shareRows[0].sharePercent,50)
vm.cancelShareDialog();assert.equal(vm.shareDialogOpen,false)
vm.openShareDialog();assert.deepEqual(vm.shareRows.map(m=>m.sharePercent),[100,0],'Cancel discards edits')
vm.shareRows[0].sharePercent=70;vm.markShareEdited('owner');vm.distributeShareRemainder()
assert.deepEqual(vm.shareRows.map(m=>m.sharePercent),[70,30])
vm.shareRows[1].sharePercent=40;assert.equal(vm.canSaveShares,false,'Invalid totals cannot be saved')
let splitWrites=0
api.put=async()=>{splitWrites++;throw new Error('Split refused')}
await vm.saveProjectShares();assert.equal(splitWrites,0)
vm.shareRows[1].sharePercent=30;await vm.saveProjectShares()
assert.equal(vm.sharesError,'Split refused');assert.equal(vm.shareDialogOpen,true)
assert.equal(vm.shareRows[0].sharePercent,70);assert.equal(vm.savedShareRows[0].sharePercent,100)
api.put=(url,body)=>{splitWrites++;return new Promise(resolve=>{finish=()=>resolve({data:{members:state.members.map(member=>({...member,shareBasisPoints:body.shares.find(s=>s.userId===member.id).shareBasisPoints}))}})})}
action=vm.saveProjectShares();vm.cancelShareDialog();await vm.closeShareDialog();await vm.saveProjectShares()
assert.equal(vm.shareDialogOpen,true);assert.equal(splitWrites,2,'Pending saves prevent dismissal and duplicates')
finish();await action;assert.equal(vm.shareDialogOpen,false)
assert.deepEqual(vm.savedShareRows.map(m=>m.sharePercent),[70,30],'List updates only after a successful save')
vm.project.member_management_locked=true;vm.openShareDialog();assert.equal(vm.shareDialogOpen,false)
assert.equal(vm.savedShareRows.length,2,'Locked shared areas retain their readable saved member list')
vm.openMemberDialog();assert.equal(vm.memberDialogOpen,false,'Locked areas still cannot add members')
const requestsBeforeRemoval=requests.length
await vm.removeMember('next-owner')
assert.equal(requests.length,requestsBeforeRemoval,'Showing locked member rows never enables removal')
console.log('Area split dialog passed: persisted list values, cancel/discard, distribution, validation, failed drafts, pending guards and explicit saving.')

// Refresh after an action must keep the settings tree mounted, including drafts.
vm.id = '1'; state = project(); await vm.fetchProjectSettings()
vm.editProjectData.name = 'Uncommitted name'
const quietGet = api.get
let finishRefresh
api.get = async url => {
 if (url.includes('/entries')) return { data: { total: 0 } }
 return new Promise(resolve => { finishRefresh = resolve })
}
const quietRefresh = vm.fetchProjectSettings({ quiet: true })
assert.equal(vm.loading, false, 'Background refresh cannot replace settings with a spinner')
finishRefresh({ data: state }); await quietRefresh
assert.equal(vm.editProjectData.name, 'Uncommitted name', 'Background refresh preserves identity input')
api.get = async () => { throw new Error('Refresh offline') }
await vm.fetchProjectSettings({ quiet: true })
assert.equal(vm.loadError, '', 'Background failure must not hide the entire settings tree')
assert.equal(vm.memberError, 'Refresh offline')
assert.equal(vm.editProjectData.name, 'Uncommitted name')
api.get = quietGet
console.log('Area background refresh passed: mounted controls, unchanged drafts and inline failures.')
