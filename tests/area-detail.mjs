import assert from 'node:assert/strict'
import fs from 'node:fs'
const source = fs.readFileSync(new URL('../src/views/ProjectDetail.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const defaults = [...script.matchAll(/^import (\w+) from .+$/gm)].map(m => m[1])
script = script.replace(/^import [\s\S]*? from ['"][^'"\n]+['"];?\s*$/gm, '').replace('export default', 'return')
const api = {}, downloads = []
const component = new Function('annualReturnRoute', 'generateUrl', 'extractError', 'showToast', 'createProjectEntryFilters', 'downloadBlobResponse', 'REMOTE_DATA_CHANGED_EVENT', 'window', ...defaults, script)(()=>null, url=>url, error=>error.message, ()=>{}, ()=>({}), response=>downloads.push(response), 'remote-change', {removeEventListener(){}}, ...defaults.map(name=>name==='axios'?api:{}))
const strings = new Proxy({}, {get:(_, key)=>()=>key})
function setup() {
 const vm = {...component.data(), projectId:'4', isProjectPaymentsView:false, $texts:{areaDetail:strings, common:strings, entry:strings}}
 for(const [name, fn] of Object.entries(component.methods)) vm[name] = fn.bind(vm)
 vm.buildEntryQueryParams = paginated=>({projectId:vm.projectId, ...(paginated ? {limit:25, offset:0} : {})})
 return vm
}
for (const [project, expected] of [[null, false], [{}, false], [{settlementCount: 0}, false], [{settlementCount: 2}, true]]) {
 assert.equal(component.computed.hasSettlementHistory.call({project}), expected)
}
const historyNavigation = setup()
let historyRoute
historyNavigation.$router = {push: route => {historyRoute = route}}
historyNavigation.openProjectSettlements()
assert.deepEqual(historyRoute, {name: 'project-settlements', params: {id: '4'}})
const vm = setup()
assert.equal(vm.loadingProject, true)
api.get = async()=>{throw new Error('Area unavailable')}
await vm.fetchProjectData()
assert.equal(vm.project, null)
assert.equal(vm.loadingProject, false)
assert.equal(vm.projectError, 'Area unavailable')
api.get = async url=>({data:url.includes('/projects/')?{id:4,name:'Shared'}:url.endsWith('/entries')?{entries:[{id:7}],total:1,dateGroups:[{date:'2026-10-28'}],lookups:{hashtags:[]}}:[]})
await vm.fetchProjectData()
assert.equal(vm.projectError, '')
assert.deepEqual(vm.activeEntries, [{id:7}])
assert.equal(vm.activePagination.total, 1)
assert.equal(vm.loadingEntries, false)
api.get = async()=>{throw new Error('Payments unavailable')}
await vm.fetchActiveEntries()
assert.equal(vm.entriesError, 'Payments unavailable')
assert.deepEqual(vm.activeEntries, [{id:7}], 'Failure must not replace previously loaded payments with empty data')
api.get = async()=>({data:{entries:[],total:0}})
await vm.fetchActiveEntries()
assert.equal(vm.entriesError, '')
assert.deepEqual(vm.activeEntries, [])
// Lookup failures remain visible separately from a successful payment-list response.
api.get = async url=>{if(url.includes('/projects/'))return {data:{id:4}};throw new Error('Lookup unavailable')}
await vm.fetchProjectData()
assert.equal(vm.projectError, 'Lookup unavailable')
assert.equal(vm.loadingEntries, false)
// Overlapping payment requests: an older failure cannot erase newer results.
const pending=[]
api.get=()=>new Promise((resolve,reject)=>pending.push({resolve,reject}))
const old=vm.fetchActiveEntries(), newer=vm.fetchActiveEntries()
pending[1].resolve({data:{entries:[{id:8}],total:1}});await newer
pending[0].reject(new Error('Old failure'));await old
assert.equal(vm.entriesError,'')
assert.equal(vm.activeEntries[0].id,8)
// History failure/retry and closing while a request is pending.
api.get=async()=>{throw new Error('History unavailable')}
await vm.openEntryHistory({id:8})
assert.equal(vm.entryHistoryError,'History unavailable')
assert.equal(vm.entryHistoryLoading,false)
api.get=async()=>({data:{history:[{id:1}]}})
await vm.openEntryHistory(vm.entryHistoryEntry)
assert.equal(vm.entryHistoryError,'')
assert.equal(vm.entryHistoryRows.length,1)
api.get=()=>new Promise(resolve=>pending.push({resolve}))
const history=vm.openEntryHistory({id:9});vm.closeEntryHistory()
pending[2].resolve({data:{history:[{id:2}]}});await history
assert.equal(vm.entryHistoryOpen,false)
assert.deepEqual(vm.entryHistoryRows,[])
// CSV feedback and pending guard; downloads only after a successful response.
api.get=async()=>{throw new Error('Export unavailable')}
await vm.exportEntries()
assert.equal(vm.exportError,'Export unavailable')
api.get=()=>new Promise(resolve=>pending.push({resolve}))
const exporting=vm.exportEntries();await vm.exportEntries()
assert.equal(pending.length,4)
pending[3].resolve({data:'csv'});await exporting
assert.equal(vm.exportError,'')
assert.equal(downloads.length,1)
// Leaving the view invalidates pending area and payment responses.
const unmounted=setup();unmounted.categories=[{id:1}];unmounted.paymentPartners=[{id:1}]
const area=unmounted.fetchProjectData(), entries=unmounted.fetchActiveEntries()
component.beforeUnmount.call(unmounted)
pending[4].resolve({data:{id:99}});pending[5].resolve({data:{entries:[{id:99}]}})
await Promise.all([area,entries])
assert.equal(unmounted.project,null)
assert.deepEqual(unmounted.activeEntries,[])
// Out-of-order area failures cannot replace the newest successful project.
const newest=setup();newest.categories=[{id:1}];newest.paymentPartners=[{id:1}]
newest.fetchActiveEntries=async()=>{}
api.get=()=>new Promise((resolve,reject)=>pending.push({resolve,reject}))
const first=newest.fetchProjectData(),second=newest.fetchProjectData()
pending[7].resolve({data:{id:4,name:'Newest'}});await second
pending[6].reject(new Error('Old area error'));await first
assert.equal(newest.project.name,'Newest')
assert.equal(newest.projectError,'')
console.log('Area detail passed: loading, retries, lookup/payment/history/export failures, pending guards and stale/unmounted results; APIs mocked.')

for (const shared of [false,true]) {
 const target=component.computed.projectReportLocation.call({projectId:'8',hasAdditionalProjectMembers:shared})
 assert.deepEqual(target,{name:'annual-overview',query:{projectId:'8',entryScope:shared?'shared':'personal',timeRange:'all'}},'Statistic links keep the area and its amount perspective, including payments from older years')
}
console.log('Area statistics links passed: shared totals, solo personal rows, selected area and all-time scope.')
