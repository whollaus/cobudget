import assert from 'node:assert/strict'
import fs from 'node:fs'
const source=fs.readFileSync(new URL('../src/views/ProjectDetail.vue',import.meta.url),'utf8')
let script=source.split('<script>')[1].split('</script>')[0]
const defaults=[...script.matchAll(/^import (\w+) from .+$/gm)].map(m=>m[1])
script=script.replace(/^import [\s\S]*? from ['"][^'"\n]+['"];?\s*$/gm,'').replace('export default','return')
const api={},writes=[],events=[],routes=[]
const component=new Function('annualReturnRoute','generateUrl','extractError','showToast','createProjectEntryFilters','window','CustomEvent',...defaults,script)(()=>({}),url=>url,error=>error.message,()=>{},()=>({}),{dispatchEvent:e=>events.push(e.type)},class{constructor(type){this.type=type}},...defaults.map(n=>n==='axios'?api:{}))
const strings=new Proxy({}, {get:(_,key)=>()=>key})
const vm={...component.data(),projectId:'1',$texts:{areaSettings:strings,areaDetail:strings,entry:strings},$emit:e=>events.push(e),$router:{push:r=>routes.push(r)}}
for(const name of ['isTruthy','openConfirm','closeActionConfirm','confirmAction','refreshActionProject','deleteProject','toggleArchiveProject','deleteEntry']) vm[name]=component.methods[name].bind(vm)
for(const name of ['canManageProject','deleteBlockedReason','archiveBlockedReason'])Object.defineProperty(vm,name,{get:component.computed[name].bind(vm)})
vm.fetchProjectData=()=>{}
const clean=()=>({id:1,is_owner:true,is_archived:false,settings_actions:{delete_block_reason:null,archive_block_reason:null}})
let state=clean();vm.project=structuredClone(state)
api.get=async()=>({data:structuredClone(state)})
api.post=async(...args)=>{writes.push(['post',...args]);return{data:{}}}
api.delete=async(...args)=>{writes.push(['delete',...args]);return{data:{}}}
vm.toggleArchiveProject();assert.equal(writes.length,0);assert.equal(vm.confirmDialog.title,'archiveTitle')
vm.closeActionConfirm();await vm.confirmAction();assert.equal(writes.length,0)
vm.toggleArchiveProject();await vm.confirmAction()
assert.equal(writes.at(-1)[1],'/apps/cobudget/api/projects/1/archive');assert.equal(vm.project.is_archived,true)
state.is_archived=true;vm.toggleArchiveProject();assert.equal(vm.confirmDialog.title,'unarchiveTitle')
await vm.confirmAction();assert.equal(writes.at(-1)[1],'/apps/cobudget/api/projects/1/unarchive')
// Blockers are not bypassed by a stale menu or a newly added payment/budget reference.
state=clean();vm.project=clean();vm.deleteProject();state.settings_actions.delete_block_reason='budget_goals'
await vm.confirmAction();assert.equal(writes.length,2);assert.equal(vm.confirmDialog.error,'deleteBlockedBudget')
assert.equal(vm.confirmDialog.busy,false);vm.closeActionConfirm()
vm.project=clean();vm.deleteProject();state=clean()
api.delete=async()=>{throw new Error('Delete refused')}
await vm.confirmAction();assert.equal(vm.confirmDialog.error,'Delete refused')
assert.equal(vm.confirmDialog.confirmVariant,'danger')
let finish
api.delete=(...args)=>{writes.push(['delete',...args]);return new Promise(resolve=>{finish=resolve})}
const pending=vm.confirmAction();await new Promise(r=>setImmediate(r))
vm.closeActionConfirm();vm.toggleArchiveProject();await vm.confirmAction()
assert.equal(writes.length,3);assert.equal(vm.confirmDialog.busy,true)
finish({data:{}});await pending
assert.equal(vm.confirmDialog,null);assert.deepEqual(routes.at(-1),{name:'projects'})
// Deleting a payment retains the canonical editable/source ID and the safety question.
api.delete=async(...args)=>{writes.push(['delete',...args]);return{data:{}}}
vm.deleteEntry({id:99,source_entry_id:20,editable_entry_id:10})
assert.equal(vm.confirmDialog.title,'deleteEntryTitle');assert.equal(writes.length,3)
await vm.confirmAction();assert.equal(writes.at(-1)[1],'/apps/cobudget/api/entries/10')
// Navigation/lost permissions invalidate confirmations without sending a write.
vm.project=clean();vm.toggleArchiveProject();vm.projectId='2';await vm.confirmAction()
assert.equal(writes.length,4);assert.equal(vm.confirmDialog,null)
vm.projectId='1';vm.toggleArchiveProject();vm.project.is_owner=false;await vm.confirmAction()
assert.equal(writes.length,4)
vm.project=clean();vm.project.settings_actions.archive_block_reason='open_shared_payments';vm.toggleArchiveProject()
assert.equal(vm.confirmDialog,null)
vm.project.settings_actions=undefined;vm.deleteProject();assert.equal(vm.confirmDialog,null)
// Leaving the component during the read-only preflight cancels the pending write.
vm.project=clean();vm.deleteProject()
let completeRead
api.get=()=>new Promise(resolve=>{completeRead=resolve})
const preflight=vm.confirmAction();vm.confirmDialog=null
completeRead({data:clean()});await preflight
assert.equal(writes.length,4,'Unmounted confirmations cannot start a delayed mutation')
// A delayed failure from an abandoned dialog must not replace the new area's message.
vm.project=clean();vm.deleteEntry({id:10})
let fail
api.delete=()=>new Promise((_,reject)=>{fail=reject})
const late=vm.confirmAction();vm.projectId='2';vm.confirmDialog=null
vm.deleteEntry({id:11});const next=vm.confirmDialog
fail(new Error('Old failure'));await late
assert.equal(vm.confirmDialog,next);assert.equal(next.error,'')
console.log('Area confirmations passed: explicit archive/restore/delete, cancellation, eligibility, errors/retry, pending and route guards; writes mocked.')
