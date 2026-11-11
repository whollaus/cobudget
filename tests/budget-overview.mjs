import assert from 'node:assert/strict'
import fs from 'node:fs'
const source=fs.readFileSync(new URL('../src/views/BudgetGoalsView.vue',import.meta.url),'utf8')
let script=source.split('<script>')[1].split('</script>')[0]
const defaults=[...script.matchAll(/^import (\w+) from .+$/gm)].map(m=>m[1])
script=script.replace(/^import [\s\S]*? from ['"][^'"\n]+['"];?\s*$/gm,'').replace('export default','return')
const api={}, navigation=[]
const component=new Function('generateUrl','extractError','REMOTE_DATA_CHANGED_EVENT','changeTouches','window',...defaults,script)(url=>url,error=>error.message,'remote-change',(detail,domains)=>domains.includes(detail?.domain),{removeEventListener(){}},...defaults.map(name=>name==='axios'?api:{}))
const strings=new Proxy({},{get:(_,key)=>(value)=>`${key}:${value??''}`})
const vm={...component.data(),$texts:{budgetGoals:strings},$router:{push:route=>navigation.push(route)}}
for(const [name,fn] of Object.entries(component.methods))vm[name]=fn.bind(vm)
api.get=async()=>{throw new Error('Offline')}
await vm.fetchData()
assert.equal(vm.loadError,'Offline')
assert.equal(vm.loading,false)
assert.equal(vm.goals.length,0)
api.get=async()=>({data:[]})
await vm.fetchData()
assert.equal(vm.loadError,'','Retry clears the failure, allowing the genuine empty state')
api.get=async()=>({data:[{id:7,name:'House',evaluation:{progress_percent:140,status:'exceeded'}}]})
await vm.fetchData()
assert.equal(vm.goals[0].id,7)
assert.equal(vm.progressValue(vm.goals[0]),100)
assert.equal(vm.progressAriaLabel(vm.goals[0]),'percentUsed:140','Actual over-budget progress remains announced')
assert.equal(vm.progressValue({evaluation:{progress_percent:-5}}),0)
assert.equal(vm.progressValue({evaluation:{progress_percent:'bad'}}),0)
vm.editGoal(vm.goals[0]);vm.openNewGoal()
assert.deepEqual(navigation,[{name:'budget-edit',params:{id:'7'}},{name:'budget-new'}])
const pending=[]
api.get=()=>new Promise((resolve,reject)=>pending.push({resolve,reject}))
const old=vm.fetchData(),fresh=vm.fetchData()
pending[1].resolve({data:[{id:8,name:'Latest'}]});await fresh
pending[0].reject(new Error('Old request failed'));await old
assert.equal(vm.goals[0].id,8)
assert.equal(vm.loadError,'','Late failures cannot replace the latest result')
const next=vm.fetchData()
component.beforeUnmount.call(vm)
pending[2].resolve({data:[{id:9}]});await next
assert.equal(vm.goals[0].id,8,'Unmounted views ignore late responses')
let refreshes=0;vm.fetchData=()=>{refreshes++}
vm.onRemoteDataChanged({detail:{domain:'categories'}})
vm.onRemoteDataChanged({detail:{domain:'budgets'}})
vm.onRemoteDataChanged({detail:{domain:'entries'}})
assert.equal(refreshes,2)
console.log('Budget overview passed: load failure, retry, empty/populated results, stale-response and unmount guards, progress bounds and navigation; APIs mocked.')
