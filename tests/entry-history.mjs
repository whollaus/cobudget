import assert from 'node:assert/strict'
import fs from 'node:fs'
const read=file=>fs.readFileSync(new URL(`../src/${file}`,import.meta.url),'utf8')
const texts={entry:{historyFetchError:()=> 'Could not load history'}}
// Exercise the personal-list and report callers that now expose inline error/retry.
for(const [file,open,close,prefix,counter,rows] of [
 ['views/TransactionsView.vue','openEntryHistory','closeEntryHistory','entryHistory','entryHistoryRequestId','entryHistoryRows'],
 ['components/ReportPayments.vue','openHistory','closeHistory','history','historyRequest','history'],
]) {
 const source=read(file),start=source.indexOf(`\t\tasync ${open}(`)
 const end=file.includes('Transactions')?source.indexOf('\t\topenConfirm(',start):source.indexOf('\n\t},\n}',start)
 const api={}
 const methods=new Function('axios','generateUrl','extractError',`return {${source.slice(start,end)}}`)(api,url=>url,error=>error.message)
 const vm={$texts:texts,[counter]:0,[`${prefix}Open`]:false,[`${prefix}Loading`]:false,[`${prefix}Error`]:'',[rows]:[]}
 for(const [name,fn] of Object.entries(methods))vm[name]=fn.bind(vm)
 const pending=[]
 api.get=async()=>{throw Error('Request failed')}
 await vm[open]({id:10})
 assert.equal(vm[`${prefix}Error`],'Request failed',file)
 assert.equal(vm[`${prefix}Open`],true)
 assert.equal(vm[`${prefix}Loading`],false)
 api.get=async url=>{assert.equal(url,'/apps/cobudget/api/entries/10/history');return {data:{history:[{id:1,old_display:'0',new_display:'12,34'}]}}}
 await vm[open](vm[`${prefix}Entry`])
 assert.equal(vm[`${prefix}Error`],'')
 assert.equal(vm[rows][0].new_display,'12,34')
 api.get=()=>new Promise((resolve,reject)=>pending.push({resolve,reject}))
 const old=vm[open]({id:10}),next=vm[open]({id:11})
 pending[1].resolve({data:{history:[{id:11}]}});await next
 pending[0].reject(Error('Outdated failure'));await old
 assert.equal(vm[`${prefix}Error`],'')
 assert.equal(vm[rows][0].id,11)
 const late=vm[open]({id:12});vm[close]()
 pending[2].resolve({data:{history:[{id:12}]}});await late
 assert.equal(vm[`${prefix}Open`],false)
 assert.deepEqual(vm[rows],[])
 assert.equal(vm[`${prefix}Entry`],null)
 api.get=async()=>({data:{history:[]}})
 await vm[open]({id:13})
 assert.equal(vm[`${prefix}Error`],'')
 assert.deepEqual(vm[rows],[])
}
// Verify state precedence and escaped values in the rendered shared dialog.
import * as Vue from 'vue'
import { parse, compileTemplate } from '@vue/compiler-sfc'
import { renderToString } from '@vue/server-renderer'
const {descriptor}=parse(read('components/EntryHistoryModal.vue'))
const names=[...descriptor.script.content.matchAll(/^import (\w+) from/gm)].map(m=>m[1])
const slot=(tag)=>({setup:(_, {slots})=>()=>Vue.h(tag,[slots.default?.(),slots.actions?.()])})
const stubs={NcDialog:slot('section'),NcNoteCard:slot('aside'),NcButton:slot('button'),NcLoadingIcon:slot('i'),NcEmptyContent:{props:['name'],setup:props=>()=>Vue.h('p',props.name)},HistoryIcon:slot('i')}
const options=new Function(...names,descriptor.script.content.replace(/^import .*$/gm,'').replace('export default','return'))(...names.map(n=>stubs[n]))
const compiled=compileTemplate({source:descriptor.template.content,filename:'EntryHistoryModal.vue',id:'history-test',compilerOptions:{mode:'function'}})
assert.deepEqual(compiled.errors,[])
options.render=new Function('Vue',compiled.code)(Vue)
const strings=new Proxy({},{get:(_,key)=>()=>key})
async function render(props){const app=Vue.createSSRApp(options,props);app.config.globalProperties.$texts={entry:strings,common:strings};return renderToString(app)}
let html=await render({loading:true,error:'hidden failure',history:[]})
assert.match(html,/historyLoading/);assert.doesNotMatch(html,/hidden failure|historyEmpty/)
html=await render({loading:false,error:'Failed read',history:[]})
assert.match(html,/Failed read/);assert.match(html,/retry/);assert.doesNotMatch(html,/historyEmpty/)
html=await render({history:[]});assert.match(html,/historyEmpty/)
html=await render({history:[{id:1,changed_at_display:'28.10.2026',changed_by_display_name:'A & B',field_label:'Name',old_display:'',new_display:'<script>example</script>'},{id:2,old_display:0,new_display:'line1\nline2'}]})
assert.match(html,/A &amp; B/);assert.match(html,/&lt;script&gt;/);assert.doesNotMatch(html,/<script>/)
assert.match(html,/>-<\/td>/);assert.match(html,/>0<\/td>/);assert.match(html,/scope="col"/)
console.log('Entry history passed: personal/report failure and retry, empty results, stale/closed requests, loading/error precedence, safe text and preserved zero values; read APIs and native rendering shells mocked.')

// The mobile editor must use the originally selected personal row, not its editable source.
{
 const source=read('components/AddEntryModal.vue')
 const start=source.indexOf('\t\tasync openPaymentHistory(')
 const end=source.indexOf('\n\t\t},',source.indexOf('\t\tclosePaymentHistory()',start))+6
 const api={}
 const methods=new Function('axios','generateUrl','extractError',`return {${source.slice(start,end)}}`)(api,url=>url,error=>error.message)
 const vm={$texts:texts,isEditing:true,isOpen:true,paymentHistoryEntryId:41,entry:{id:99},paymentHistoryRequest:0}
 for(const [name,fn] of Object.entries(methods)) vm[name]=fn.bind(vm)
 api.get=async url=>{assert.equal(url,'/apps/cobudget/api/entries/41/history');return {data:{history:[{id:7}]}}}
 await vm.openPaymentHistory()
 assert.deepEqual(vm.paymentHistoryRows,[{id:7}])
 api.get=async()=>{throw Error('History failed')}
 await vm.openPaymentHistory()
 assert.equal(vm.paymentHistoryError,'History failed')
 api.get=async()=>({data:{history:[]}})
 await vm.openPaymentHistory()
 assert.equal(vm.paymentHistoryError,'')
 let resolve
 api.get=()=>new Promise(done=>{resolve=done})
 const pending=vm.openPaymentHistory()
 vm.closePaymentHistory()
 resolve({data:{history:[{id:8}]}}); await pending
 assert.equal(vm.paymentHistoryOpen,false)
 assert.deepEqual(vm.paymentHistoryRows,[],'Closing ignores stale history responses without touching the payment draft')
 assert.equal(vm.entry.id,99)
 vm.isEditing=false
 api.get=()=>{throw Error('New payment must not request history')}
 await vm.openPaymentHistory()
 console.log('Mobile editor history passed: original personal row, retry, empty history, close races and draft preservation.')
}
