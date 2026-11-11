import assert from 'node:assert/strict'
import fs from 'node:fs'
const source=fs.readFileSync(new URL('../src/views/ProjectList.vue',import.meta.url),'utf8')
let script=source.split('<script>')[1].split('</script>')[0]
const defaults=[...script.matchAll(/^import (\w+) from .+$/gm)].map(m=>m[1])
script=script.replace(/^import [\s\S]*? from ['"][^'"\n]+['"];?\s*$/gm,'').replace('export default','return')
const api={}
const component=new Function('getAppLocale','generateUrl','extractError','showToast','sortAreasByName','REMOTE_DATA_CHANGED_EVENT','changeTouches','window',...defaults,script)(()=> 'de-DE',url=>url,error=>error.message,()=>{},rows=>rows.slice().sort((a,b)=>a.name.localeCompare(b.name)),'remote-change',(detail,domains)=>detail?.domains?.some(domain=>domains.includes(domain)),{removeEventListener(){}},...defaults.map(name=>name==='axios'?api:{}))
const strings=new Proxy({},{get:(_,key)=>()=>key})
const vm={...component.data(),$texts:{areas:strings}}
for(const [name,fn] of Object.entries(component.methods))vm[name]=fn.bind(vm)
for(const [name,fn] of Object.entries(component.computed))Object.defineProperty(vm,name,{get:fn.bind(vm)})
assert.equal(vm.loadingProjects,true,'Do not flash an empty list before initial loading')
api.get=async()=>{throw new Error('Areas unavailable')}
await vm.fetchProjects()
assert.equal(vm.projectsError,'Areas unavailable')
assert.equal(vm.loadingProjects,false)
api.get=async()=>({data:[]})
await vm.fetchProjects()
assert.equal(vm.projectsError,'')
assert.equal(vm.activeProjects.length,0)
assert.equal(vm.archivedProjects.length,0)
api.get=async()=>({data:[{id:1,name:'Old',is_archived:true},{id:2,name:'Shared',is_shared:true,member_count:2,personal_balance:-10},{id:3,name:'Home',is_shared:false,member_count:1}]})
await vm.fetchProjects()
assert.deepEqual(vm.activeProjects.map(p=>p.name),['Home','Shared'])
assert.equal(vm.archivedProjects[0].id,1)
assert.equal(vm.isSharedProject(vm.activeProjects[0]),false)
assert.equal(vm.isSharedProject(vm.activeProjects[1]),true)
assert.equal(vm.activeProjects[1].personal_balance,-10)
assert.equal(vm.defaultShareLabel({my_share_basis_points:3333}), '33,33\u00a0%')
assert.equal(vm.defaultShareLabel({my_share_basis_points:'0'}), '0\u00a0%', 'A zero share is meaningful')
assert.equal(vm.defaultShareLabel({my_share_basis_points:10000}), '100\u00a0%')
for (const raw of [null, undefined, '', -1, 10001, 'invalid']) {
 assert.equal(vm.defaultShareLabel({my_share_basis_points:raw}), null, 'Missing or invalid shares are not invented')
}
for (const value of [true,1,'1','true']) assert.equal(vm.isAreaAdmin({is_owner:value}),true)
for (const value of [false,0,'0','false',undefined]) assert.equal(vm.isAreaAdmin({is_owner:value}),false)
vm.archivesOpen=true
const pending=[]
api.get=()=>new Promise((resolve,reject)=>pending.push({resolve,reject}))
const old=vm.fetchProjects(),newer=vm.fetchProjects()
pending[1].resolve({data:[{id:4,name:'Archived only',is_archived:true}]});await newer
pending[0].reject(new Error('Old failure'));await old
assert.equal(vm.projectsError,'','A stale request cannot hide a newer result')
assert.equal(vm.activeProjects.length,0)
assert.equal(vm.archivedProjects.length,1)
assert.equal(vm.archivesOpen,true,'Reloading does not reset the archive disclosure')
const last=vm.fetchProjects()
component.beforeUnmount.call(vm)
pending[2].resolve({data:[]});await last
assert.equal(vm.archivedProjects.length,1,'Late responses after unmount are ignored')
let refreshes=0;vm.fetchProjects=()=>{refreshes++}
vm.onRemoteDataChanged({detail:{domains:['projects']}})
vm.onRemoteDataChanged({detail:{domains:['entries']}})
vm.onRemoteDataChanged({detail:{domains:['budgets']}})
assert.equal(refreshes,2)
console.log('Area overview passed: initial loading, errors/retry, empty/active/archived-only lists, shared balances, stale/unmounted responses and live refresh; APIs mocked.')
