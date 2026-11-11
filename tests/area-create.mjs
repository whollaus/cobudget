import assert from 'node:assert/strict'
import fs from 'node:fs'
const source = fs.readFileSync(new URL('../src/views/ProjectCreateView.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const imports = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .+$/gm, '').replace('export default', 'return')
const api = {}, writes = [], events = [], routes = []
const component = new Function('generateUrl', 'getCurrentUser', 'extractError', 'showToast', 'window', ...imports, script)(url => url, () => ({uid:'owner',displayName:'Owner'}), error => error.message, () => {}, {removeEventListener(){}}, ...imports.map(name => name === 'axios' ? api : {}))
const strings = new Proxy({}, { get: (_, key) => () => key })
function instance() {
 const vm = {...component.data(), $enableSharedProjects:true, $texts:{areas:strings, memberSearch:strings}, $emit:event=>events.push(event), $router:{push:route=>routes.push(route),replace:route=>routes.push(route)}}
 for (const [name, fn] of Object.entries(component.methods)) vm[name] = fn.bind(vm)
 for (const [name, fn] of Object.entries(component.computed)) Object.defineProperty(vm, name, {get:fn.bind(vm)})
 return vm
}
let vm=instance()
assert.equal(component.beforeRouteLeave.call(vm),true)
assert.deepEqual(vm.memberRows.map(m=>[m.id,m.share]),[['owner',100]])
api.get=async()=>({data:[]})
api.post=async(...args)=>{writes.push(args);throw new Error('Creation refused')}
vm.newProject.name='  '
await vm.createProject()
assert.equal(writes.length,0)
vm.newProject={name:' Home ',color:'#0082c9',members:[{id:'friend',displayName:'Friend'}]}
assert.equal(vm.hasDraft,true)
assert.deepEqual(vm.memberRows.map(m=>m.share),[50,50])
vm.openMemberDialog();vm.selectedMembers=[{id:'friend'},{id:'other',displayName:'Other'},{id:'owner'}];vm.addMembers()
assert.deepEqual(vm.newProject.members.map(m=>m.id),['friend','other'],'Deduplicate selected members and never add the creator twice')
assert.deepEqual(vm.memberRows.map(m=>[m.id,m.share]),[['owner',33],['friend',34],['other',33]],'Preview matches server whole-percent remainder order')
assert.equal(writes.length,0,'Adding draft members must not persist the area')
vm.removeMember('other')
await vm.createProject()
assert.equal(vm.createError,'Creation refused')
assert.equal(vm.newProject.name,' Home ')
assert.equal(vm.newProject.members.length,1)
assert.equal(vm.creatingProject,false)
assert.equal(events.length,0)
assert.equal(routes.length,0,'Failed creation stays on the form')
const stay=component.beforeRouteLeave.call(vm)
assert.equal(vm.confirmDiscard,true)
await vm.createProject()
assert.equal(writes.length,1,'Cannot submit under a discard confirmation')
vm.resolveDiscard(false)
assert.equal(await stay,false)
assert.equal(vm.newProject.name,' Home ')
let finish
api.post=(...args)=>{writes.push(args);return new Promise(resolve=>{finish=resolve})}
const pending=vm.createProject()
vm.cancelCreate();await vm.createProject();vm.colorChanged('#ffffff');vm.removeMember('friend')
assert.equal(component.beforeRouteLeave.call(vm),false,'Pending creation blocks route navigation')
assert.equal(routes.length,0)
assert.equal(writes.length,2,'Duplicate clicks submit once')
assert.equal(vm.newProject.color,'#0082c9')
assert.equal(vm.newProject.members.length,1)
finish({data:{id:123}});await pending
assert.deepEqual(writes.at(-1)[1],{name:'Home',color:'#0082c9',members:['friend']})
assert.deepEqual(routes.at(-1),{name:'project-detail',params:{id:'123'}},'Successful creation opens exactly the returned area')
assert.deepEqual(events,['refresh-projects'])
assert.equal(component.beforeRouteLeave.call(vm),true,'Success bypasses unsaved confirmation')
await vm.createProject();assert.equal(writes.length,2,'Do not recreate after success')
vm=instance();vm.$enableSharedProjects=false
vm.newProject={name:'Personal',color:'',members:[{id:'friend'}]}
assert.deepEqual(vm.memberRows.map(m=>m.share),[100])
api.post=async(...args)=>{writes.push(args);return {data:{id:124}}}
await vm.createProject()
assert.deepEqual(writes.at(-1)[1].members,[],'Shared-area toggle also guards stale selections in the payload')
// Cancellation is local, even with a changed draft.
vm=instance();vm.newProject.name='Cancelled';const previousWrites=writes.length
vm.cancelCreate()
assert.deepEqual(routes.at(-1),{name:'projects'})
assert.equal(component.beforeRouteLeave.call(vm),true)
assert.equal(writes.length,previousWrites)
vm=instance();vm.newProject.color='#ffffff'
const leave=component.beforeRouteLeave.call(vm);vm.resolveDiscard(true)
assert.equal(await leave,true)
// Member selection cancellation, stale responses and retry.
vm=instance();vm.openMemberDialog();vm.selectedMembers=[{id:'friend'}]
const close=vm.closeMemberDialog();assert.equal(vm.confirmDiscard,true)
vm.resolveDiscard(false);await close
assert.equal(vm.memberDialogOpen,true)
assert.equal(vm.selectedMembers.length,1)
vm.cancelMemberDialog();assert.equal(vm.newProject.members.length,0)
vm.openMemberDialog();vm.memberSearchTerm='old'
api.get=()=>new Promise(resolve=>{finish=resolve})
const old=vm.fetchMemberOptions();vm.searchMembers('')
finish({data:[{id:'old'}]});await old
assert.deepEqual(vm.memberSearchOptions,[])
vm.memberSearchTerm='friend';api.get=async()=>{throw new Error('Search unavailable')}
await vm.fetchMemberOptions();assert.equal(vm.memberSearchError,'Search unavailable')
api.get=async()=>({data:[{id:'friend',displayName:'Friend'},{id:'owner'}]})
await vm.fetchMemberOptions();assert.equal(vm.memberSearchError,'')
assert.equal(vm.availableMembers.length,1,'Creator is not selectable')
vm.selectedMembers=[{id:'friend'}];assert.equal(vm.availableMembers.length,0)
vm.addMembers();assert.equal(vm.newProject.members.length,1)
vm.openMemberDialog();vm.memberSearchTerm='late'
api.get=()=>new Promise(resolve=>{finish=resolve})
const late=vm.fetchMemberOptions();component.beforeUnmount.call(vm)
finish({data:[{id:'late'}]});await late
assert.deepEqual(vm.memberSearchOptions,[])
console.log('Area creation passed: draft membership/share preview, discard/navigation guards, failures/retry, duplicate-write protection, success detail routing, disabled sharing and stale searches; APIs mocked.')
