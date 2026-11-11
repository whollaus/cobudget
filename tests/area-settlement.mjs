import assert from 'node:assert/strict'
import fs from 'node:fs'
const source = fs.readFileSync(new URL('../src/views/ProjectDetail.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const defaults = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import [\s\S]*? from ['"][^'"\n]+['"];?\s*$/gm, '').replace('export default', 'return')
const api = {}, writes = [], events = []
const component = new Function('annualReturnRoute', 'generateUrl', 'extractError', 'showToast', 'createProjectEntryFilters', 'window', 'CustomEvent', ...defaults, script)(() => ({}), url => url, error => error.message, () => {}, () => ({}), {dispatchEvent:event=>events.push(event.type)}, class { constructor(type) {this.type=type} }, ...defaults.map(name => name === 'axios' ? api : {}))
const strings = new Proxy({}, {get:(_,key)=>()=>key})
const vm = {...component.data(), projectId:'4', canSettleProject:true, $texts:{areaDetail:strings}, $emit:event=>events.push(event)}
for(const name of ['settleUp','closeSettleConfirm','confirmSettleUp']) vm[name]=component.methods[name].bind(vm)
let refreshes=0
vm.fetchProjectData=()=>{refreshes++}
api.post=async (...args)=>{writes.push(args);throw new Error('Settlement refused')}
await vm.confirmSettleUp()
assert.equal(writes.length,0,'No write without the safety dialog')
vm.canSettleProject=false;vm.settleUp()
assert.equal(vm.showSettleConfirm,false,'Only area admins may start settlement')
vm.canSettleProject=true;vm.settleUp()
assert.equal(vm.showSettleConfirm,true)
assert.equal(writes.length,0,'Opening the safety question never settles')
vm.closeSettleConfirm();await vm.confirmSettleUp()
assert.equal(writes.length,0,'Cancel never settles')
vm.settleUp();await vm.confirmSettleUp()
assert.equal(vm.showSettleConfirm,true)
assert.equal(vm.settleError,'Settlement refused')
assert.equal(vm.settlingProject,false)
assert.equal(events.length,0,'Failed settlement does not announce success')
let finish
api.post=(...args)=>{writes.push(args);return new Promise(resolve=>{finish=resolve})}
const pending=vm.confirmSettleUp()
await vm.confirmSettleUp();vm.closeSettleConfirm();vm.settleUp()
assert.equal(writes.length,2,'Repeated confirm while pending does not send another request')
assert.equal(vm.showSettleConfirm,true,'Pending settlement cannot be dismissed')
assert.equal(vm.settleError,'')
finish({data:{}});await pending
assert.equal(vm.showSettleConfirm,false)
assert.equal(vm.settlingProject,false)
assert.equal(refreshes,1)
assert.deepEqual(events,['refresh-projects','cobudget-data-changed'])
// A confirmation for one area cannot settle another after navigation.
vm.settleUp();vm.projectId='5';await vm.confirmSettleUp()
assert.equal(writes.length,2)
assert.equal(vm.showSettleConfirm,false)
vm.settleUp();vm.canSettleProject=false;await vm.confirmSettleUp()
assert.equal(writes.length,2,'Lost permissions prevent submission')
// A delayed error must not be attached to a different area.
vm.canSettleProject=true;vm.settleUp()
let fail
api.post=()=>new Promise((resolve,reject)=>{fail=reject})
const late=vm.confirmSettleUp();vm.projectId='6';vm.showSettleConfirm=false;vm.settleProjectId=null
fail(new Error('Old error'));await late
assert.equal(vm.settleError,'')
assert.equal(vm.settlingProject,false)
console.log('Area settlement passed: safety confirmation, cancellation, pending guards, errors, retry and route identity; writes mocked.')

const previewVm = {
 project: {settlementPreview: {entryCount:32,totalCents:12033}},
 activeEntries: [{amount:999}], activePagination: {total:1},
 filters: {type:'income',search:'filtered'},
}
assert.deepEqual(component.computed.settlementPreview.call(previewVm), {entryCount:32,totalCents:12033}, 'Confirmation uses the complete server summary, not filtered/page totals')
previewVm.project = {settlementPreview:{entryCount:0,totalCents:0}}
assert.deepEqual(component.computed.settlementPreview.call(previewVm), {entryCount:0,totalCents:0})
console.log('Settlement confirmation summary passed: server count and cents independent of payment filters and pagination.')
