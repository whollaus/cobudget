import assert from 'node:assert/strict'
import fs from 'node:fs'
const source=fs.readFileSync(new URL('../src/views/ProjectSettlementsView.vue',import.meta.url),'utf8')
let script=source.split('<script>')[1].split('</script>')[0]
const defaults=[...script.matchAll(/^import (\w+) from .+$/gm)].map(m=>m[1])
script=script.replace(/^import [\s\S]*? from ['"][^'"\n]+['"];?\s*$/gm,'').replace('export default','return')
const api={}
const exportCalls = { downloads: [], saves: [], copies: [], prints: [], toasts: [], errors: [] }
const fakeDoc = { body: {} }
const component=new Function('generateUrl','extractError','showRequestError','showToast','ENTRY_CLIPBOARD_EXPORT_LIMIT','csvToClipboardText','downloadBlobResponse','filenameFromDisposition','printPaymentsOverview','copyReportText','saveReportExport','normalizeEntryPageSize','REMOTE_DATA_CHANGED_EVENT','shouldIgnorePaginationKeydown','getAppLocale','document','window',...defaults,script)(
 url=>url,
 e=>e.message,
 (e,fallback)=>exportCalls.errors.push(e?.message || fallback),
 (msg,type='success')=>exportCalls.toasts.push({msg,type}),
 1000,
 text=>`tsv:${text}`,
 (res,fallback)=>exportCalls.downloads.push({res,fallback}),
 (_header,fallback)=>fallback,
 async (doc,options)=>exportCalls.prints.push({doc,options}),
 async textPromise=>exportCalls.copies.push(await textPromise),
 async (fallback,factory)=>exportCalls.saves.push(await factory()),
 ()=>25,
 'remote',
 ()=>false,
 ()=>'en',
 fakeDoc,
 {removeEventListener(){}},
 ...defaults.map(n=>n==='axios'?api:{})
)
const strings=new Proxy({},{get:(_,key)=>(...args)=>`${key}:${args.join(',')}`})
function setup(){
 const vm={...component.data(),$nextTick: fn => fn(),$refs:{},id:'4',$entriesPerPage:25,$texts:{settlements:strings,areaDetail:strings,entry:strings,common:strings,annual:strings,analytics:strings,dashboard:strings,labels:strings,payments:strings,filters:strings},$route:{query:{}},$formatMoney:(value,currency)=>`${currency} ${value}`,$currency:'EUR'}
 for(const [name,fn] of Object.entries(component.methods))vm[name]=fn.bind(vm)
 for(const [name,fn] of Object.entries(component.computed))Object.defineProperty(vm,name,{get:fn.bind(vm)})
 return vm
}
const vm=setup(), settlement={id:10,entryCount:26,currency:'CHF',balances:[{userId:'a',displayName:'Alex',balance:10}],transfers:[]}
api.get=async()=>{throw Error('List unavailable')}
await vm.fetchSettlements();assert.equal(vm.error,'List unavailable');assert.equal(vm.loading,false)
api.get=async()=>({data:{project:{id:4},settlements:[settlement],limit:10,offset:0,total:1}})
await vm.fetchSettlements();assert.equal(vm.error,'');assert.equal(vm.settlements.length,1)
let entryCalls=0
api.get=async(url,{params})=>{entryCalls++;return {data:{entries:[{id:1,amount:12}],total:26,limit:25,offset:params.offset}}}
const detailTrigger = { matches: selector => selector === 'button' }
let focusedSummary = false
vm.$refs.settlementSummary = { focus: () => { focusedSummary = true } }
vm.openSettlement(settlement, {currentTarget: detailTrigger});await new Promise(r=>setImmediate(r))
assert.equal(vm.settlementReturnFocus, detailTrigger, 'Native focus return points to the overview action')
assert.equal(focusedSummary, true, 'Initial focus stays at the dialog summary')
assert.equal(vm.openSettlementId,10);assert.equal(entryCalls,1)
assert.equal(vm.activeSettlement, settlement)
const listOffset = vm.pagination.offset
vm.closeSettlement()
assert.equal(vm.activeSettlement, null)
assert.equal(vm.pagination.offset, listOffset, 'Closing details retains the overview page')
vm.openSettlement(settlement)
assert.equal(vm.activeSettlement, settlement)
assert.equal(entryCalls, 1, 'Reopening details preserves the cached payment page')
vm.closeSettlement();assert.equal(vm.openSettlementId,null)
vm.openSettlement(settlement);assert.equal(entryCalls,1,'Cached entries do not reload when reopening')
vm.openSettlement({id:11,entryCount:0});assert.equal(vm.openSettlementId,11);assert.equal(entryCalls,1,'Only the selected dialog is active; empty snapshots do not request entries')
vm.settlements.push({id:11,entryCount:0})
vm.pagination.total = 100
let listNavigated = false
const goToPage = vm.goToSettlementPage
vm.goToSettlementPage = () => { listNavigated = true }
vm.onPaginationKeydown({key:'ArrowRight',preventDefault(){}})
assert.equal(listNavigated, false, 'An open detail dialog cannot paginate the background list')
vm.goToSettlementPage = goToPage
api.get=async()=>{throw Error('Payment page unavailable')}
await vm.fetchSettlementEntries(settlement,25)
assert.equal(vm.settlementEntryState(settlement).error,'Payment page unavailable')
assert.equal(vm.settlementEntryState(settlement).offset,25)
let retriedOffset
api.get=async(url,{params})=>{retriedOffset=params.offset;return {data:{entries:[{id:26}],total:26,offset:params.offset,limit:25}}}
await vm.fetchSettlementEntries(settlement,vm.settlementEntryState(settlement).offset)
assert.equal(retriedOffset,25,'Retry keeps the failed payment page')
assert.equal(vm.settlementEntryState(settlement).error,'')
assert.equal(vm.settlementEntryCurrentPage(settlement),2)
assert.equal(vm.settlementEntryPaginationInfo(settlement), 'pageInfo:26,26,26')
assert.equal(vm.memberNameMap.a,'Alex')
assert.equal(vm.formatCurrency(10,'CHF'),'CHF 10','Historic currency is retained')
// CSV export per settlement from overview row and settlement detail (download, Nextcloud save, clipboard and 1,000-entry limit).
const exportRequests = []
api.get = async (url, config) => {
 exportRequests.push({ url, params: config?.params, responseType: config?.responseType })
 return { data: 'ID;Betrag\n1;-12.00', headers: { 'content-disposition': 'attachment; filename="cobudget-zahlungen-test.csv"' } }
}
assert.equal(vm.canExportSettlementEntries(settlement), true)
assert.equal(vm.canCopySettlementEntriesToClipboard(settlement), true)
vm.showRowCsvMenu(settlement, true); assert.equal(vm.rowCsvMenuId, 10)
vm.showDetailCsvMenu(true); assert.equal(vm.detailCsvMenuOpen, true)
await vm.exportEntries(settlement)
await vm.saveEntriesInNextcloud(settlement)
await vm.copyEntriesToClipboard(settlement)
assert.equal(exportRequests.length, 3)
assert.deepEqual(exportRequests[0].params, { projectId: '4', entryScope: 'shared', isSettled: 1, includeFuturePayments: true, settlementId: 10 })
assert.deepEqual(exportRequests[1].params, { projectId: '4', entryScope: 'shared', isSettled: 1, includeFuturePayments: true, settlementId: 10 })
assert.deepEqual(exportRequests[2].params, { projectId: '4', entryScope: 'shared', isSettled: 1, includeFuturePayments: true, settlementId: 10, clipboard: true })
assert.equal(exportCalls.downloads.length, 1)
assert.equal(exportCalls.saves.length, 1)
assert.deepEqual(exportCalls.copies, ['tsv:ID;Betrag\n1;-12.00'])
const largeSettlement = { id: 12, entryCount: 1001 }
assert.equal(vm.canCopySettlementEntriesToClipboard(largeSettlement), false)
assert.match(vm.settlementClipboardExportDescription(largeSettlement), /^clipboardExportLimitHint:1,000$/)
await vm.copyEntriesToClipboard(largeSettlement)
assert.equal(exportRequests.length, 3, 'Clipboard export above 1,000 entries is blocked client-side')
assert.equal(exportCalls.toasts.at(-1)?.type, 'error')
// Print per settlement loads all settled payment pages and prints with historical currency and settlement metadata.
vm.project = { id: 4, name: 'Haushalt' }
const printRequests = []
api.get = async (url, { params }) => {
 printRequests.push({ url, params })
 if (params.offset === 0) {
  return {
   data: {
    entries: Array.from({ length: 250 }, (_, index) => ({ id: index + 1, amount: 10, personal_amount: 5 })),
    total: 252,
    limit: 250,
    offset: 0,
   },
  }
 }
 return {
  data: {
   entries: [{ id: 251, amount: 20, personal_amount: 10 }, { id: 252, amount: 30, personal_amount: 15 }],
   total: 252,
   limit: 250,
   offset: 250,
  },
 }
}
await vm.printEntries({ ...settlement, createdAt: 1793232000, createdByDisplayName: 'Alex' })
assert.equal(printRequests.length, 2, 'Printing a settlement fetches all pages of settled payments')
assert.deepEqual(printRequests.map(req => req.params), [{ limit: 250, offset: 0 }, { limit: 250, offset: 250 }])
assert.ok(printRequests.every(req => req.url === '/apps/cobudget/api/projects/4/settlements/10/entries'))
assert.equal(exportCalls.prints.length, 1)
assert.equal(exportCalls.prints[0].doc, fakeDoc)
assert.equal(exportCalls.prints[0].options.entries.length, 252)
assert.equal(exportCalls.prints[0].options.currency, 'CHF')
assert.equal(exportCalls.prints[0].options.enableProjects, false)
assert.equal(exportCalls.prints[0].options.showSummaryCards, false)
assert.equal(exportCalls.prints[0].options.groupByDate, false)
assert.equal(exportCalls.prints[0].options.showPaidBy, true)
assert.equal(exportCalls.prints[0].options.settlement?.id, 10)
assert.equal(exportCalls.prints[0].options.payerNameResolver({ user_id: 'a' }), 'Alex')
assert.equal(exportCalls.prints[0].options.amountResolver({ amount: 30, personal_amount: 15 }), 30, 'Settlement print shows the full area payment amount')
assert.deepEqual(exportCalls.prints[0].options.metadataRows, [
 ['area:', 'Haushalt'],
 ['author:', 'Alex'],
 ['paymentCount:', '252'],
 ['total:', 'CHF 0'],
])
// Server totals shrinking clamp the payment page.
const requested=[]
api.get=async(url,{params})=>{requested.push(params.offset);return {data:{entries:[],total:1,offset:params.offset,limit:25}}}
await vm.fetchSettlementEntries(settlement,25);await new Promise(r=>setImmediate(r))
assert.deepEqual(requested,[25,0])
// New list responses and navigation invalidate old nested requests.
const pending=[]
api.get=()=>new Promise((resolve,reject)=>pending.push({resolve,reject}))
const entries=vm.fetchSettlementEntries(settlement), list=vm.fetchSettlements()
pending[1].resolve({data:{project:{id:4},settlements:[],total:0}});await list
pending[0].reject(Error('Old entry failure'));await entries
assert.deepEqual(vm.settlementEntryStates,{})
assert.equal(vm.pagination.total,0)
const old=vm.fetchSettlements(),newer=vm.fetchSettlements()
pending[3].resolve({data:{settlements:[settlement],total:1}});await newer
pending[2].reject(Error('Old list failure'));await old
assert.equal(vm.error,'');assert.equal(vm.settlements.length,1)
// History error and retry, then close before a delayed answer.
api.get=async()=>{throw Error('History unavailable')}
await vm.openEntryHistory({id:26});assert.equal(vm.entryHistoryError,'History unavailable')
api.get=async()=>({data:{history:[{id:1}]}})
await vm.openEntryHistory(vm.entryHistoryEntry);assert.equal(vm.entryHistoryError,'');assert.equal(vm.entryHistoryRows.length,1)
api.get=()=>new Promise((resolve,reject)=>pending.push({resolve,reject}))
const history=vm.openEntryHistory({id:27});vm.closeEntryHistory()
pending[4].resolve({data:{history:[{id:2}]}});await history
assert.deepEqual(vm.entryHistoryRows,[])
const finalEntries=vm.fetchSettlementEntries(settlement),finalList=vm.fetchSettlements()
component.beforeUnmount.call(vm)
pending[5].resolve({data:{entries:[{id:99}]}});pending[6].reject(Error('Late list failure'))
await Promise.all([finalEntries,finalList])
assert.equal(vm.error,'');assert.deepEqual(vm.settlementEntryStates,{})
console.log('Settlement overview passed: list/payment/history errors and retry, single detail dialog, lazy caching, payment pagination/clamping, historical currency and stale/unmounted requests; read APIs mocked.')

// Shared balances preserve signed amounts, historical currency and optional details.
const Vue = await import('vue')
const { parse, compileTemplate } = await import('@vue/compiler-sfc')
const { renderToString } = await import('@vue/server-renderer')
const balanceSource = fs.readFileSync(new URL('../src/components/MemberBalanceList.vue', import.meta.url), 'utf8')
const { descriptor } = parse(balanceSource)
const identity = { props: ['userId', 'displayName', 'isFormer'], setup: props => () => Vue.h('span', { 'data-user': props.userId, 'data-former': props.isFormer }, props.displayName) }
const button = { setup: (_, { slots }) => () => Vue.h('button', slots.icon?.()) }
const icon = { render: () => Vue.h('i') }
const balanceComponent = new Function('MemberIdentity', 'NcButton', 'ChevronDownIcon', 'ChevronRightIcon', 'useId', 'getAppLocale', descriptor.script.content.replace(/^import .*$/gm, '').replace('export default', 'return'))(identity, button, icon, icon, Vue.useId, () => 'en')
const compiled = compileTemplate({ source: descriptor.template.content, filename: 'MemberBalanceList.vue', id: 'balance-test', compilerOptions: { mode: 'function' } })
assert.deepEqual(compiled.errors, [])
balanceComponent.render = new Function('Vue', compiled.code)(Vue)
const balances = [
 { userId: 'a', displayName: 'Alex', balance: 10, paid: 30, fairShare: 20, shareBasisPoints: 5000, receivedCents: 100, received: 1, incomeShareCents: 100, incomeShare: 1 },
 { userId: 'former:b', displayName: 'Former <member>', isFormer: true, balance: -10, paid: 10, fairShare: 20, shareBasisPoints: 5000 },
 { userId: 'c', displayName: 'Chris', balance: 0, paid: 0, fairShare: 0 },
]
async function renderBalances(expandedMembers = []) {
 const app = Vue.createSSRApp({ ...balanceComponent, data: () => ({ expandedMembers }) }, { balances, formatAmount: value => vm.formatCurrency(value, settlement.currency) })
 app.config.globalProperties.$texts = { memberBalances: { member: () => 'Member', paid: () => 'Paid', ownShare: () => 'Own share', balance: () => 'Balance', defaultShare: () => 'Default share', detailsFor: name => `Details for ${name}` }, areaDetail: { received: () => 'Received', incomeShare: () => 'Income share' } }
 return renderToString(app)
}
const html = await renderBalances()
assert.equal((html.match(/scope="col"/g) || []).length, 4)
for (const label of ['Member', 'Paid', 'Own share', 'Balance']) assert.ok(html.includes(`>${label}</th>`))
assert.match(html, /\+CHF 10/)
assert.match(html, /CHF -10/)
assert.match(html, /CHF 0/)
assert.doesNotMatch(html, /EUR|undefined|NaN/)
assert.match(html, /data-former="true"/)
assert.match(html, /Former &lt;member&gt;/)
assert.match(html, /aria-expanded="false"/)
assert.match(html, /display:none/)
assert.equal((html.match(/\(50%\)/g) || []).length, 2, 'Default shares appear in both closed member rows')
assert.doesNotMatch(html, /Positive: gets money back/)
assert.doesNotMatch(html, /Details for Former/, 'A percentage alone does not create an empty disclosure')
const state = { ...balanceComponent.data() }
for (const [key, fn] of Object.entries(balanceComponent.methods)) state[key] = fn.bind(state)
state.toggleDetails(balances[0]); assert.equal(state.isExpanded(balances[0]), true)
assert.equal(state.isExpanded(balances[1]), false)
const expanded = await renderBalances(state.expandedMembers)
assert.match(expanded, /aria-expanded="true"/)
assert.match(expanded, /50%/)
assert.match(expanded, /Income share/)
assert.match(expanded, /CHF 1/)
state.toggleDetails(balances[0]); assert.equal(state.isExpanded(balances[0]), false)
assert.equal(state.hasDetails(balances[1]), false)
assert.equal(state.hasDetails(balances[2]), false)
console.log('Member balances passed: paid/share columns, signed and zero amounts, saved currency, former members and independent disclosure state; native rendering shells mocked.')

// Repayments keep payer/recipient direction and saved currency in the shared table.
const repaymentSource = fs.readFileSync(new URL('../src/components/RepaymentList.vue', import.meta.url), 'utf8')
const repaymentDescriptor = parse(repaymentSource).descriptor
const repaymentComponent = new Function('MemberIdentity', repaymentDescriptor.script.content.replace(/^import .*$/gm, '').replace('export default', 'return'))(identity)
const repaymentTemplate = compileTemplate({ source: repaymentDescriptor.template.content, filename: 'RepaymentList.vue', id: 'repayment-test', compilerOptions: { mode: 'function' } })
assert.deepEqual(repaymentTemplate.errors, [])
repaymentComponent.render = new Function('Vue', repaymentTemplate.code)(Vue)
const repaymentApp = Vue.createSSRApp(repaymentComponent, {
 transfers: [{ fromUserId: 'former:a', fromDisplayName: 'Former <payer>', toUserId: 'b', toDisplayName: 'Recipient', amountCents: 1000, amount: 10 }],
 formatAmount: value => vm.formatCurrency(value, 'CHF'),
})
repaymentApp.config.globalProperties.$texts = { repayments: { from: () => 'From', to: () => 'To', amount: () => 'Amount' } }
const repaymentHtml = await renderToString(repaymentApp)
assert.equal((repaymentHtml.match(/scope="col"/g) || []).length, 3)
for (const label of ['From', 'To', 'Amount']) assert.ok(repaymentHtml.includes(`>${label}</th>`))
assert.match(repaymentHtml, /Former &lt;payer&gt;/)
assert.ok(repaymentHtml.indexOf('data-user="former:a"') < repaymentHtml.indexOf('data-user="b"'))
assert.match(repaymentHtml, /CHF 10/)
assert.doesNotMatch(repaymentHtml, /EUR|undefined|NaN/)
console.log('Repayment table passed: column headers, payer/recipient order, escaped names, former IDs and caller currency; native identity rendering stubbed.')

// Exercise the real table and amount-cell templates, with only native/decorative shells stubbed.
const textSection = new Proxy({}, { get: (_, key) => () => key })
const tableTexts = { entry: new Proxy({ groupClosingTitle: label => `${label} total`, groupReport: label => `Report ${label}`, groupBalance: value => `Balance: ${value}`, groupIncome: value => `Income: ${value}`, groupExpenses: value => `Expenses: ${value}` }, { get: (target, key) => target[key] ?? (() => key) }), common: textSection, annual: textSection, analytics: textSection }
const shell = { setup: (_, { slots }) => () => Vue.h('span', slots.default?.()) }
function loadTableComponent(file, dependencies) {
 const descriptor = parse(fs.readFileSync(new URL(`../src/components/${file}.vue`, import.meta.url), 'utf8')).descriptor
 const imported = [...descriptor.script.content.matchAll(/^import (.*?) from .*$/gm)].flatMap(match => match[1].replace(/[{}]/g, '').split(',').map(name => name.trim()))
 const component = new Function(...imported, descriptor.script.content.replace(/^import .*$/gm, '').replace('export default', 'return'))(...imported.map(name => dependencies[name] ?? shell))
 const result = compileTemplate({ source: descriptor.template.content, filename: `${file}.vue`, id: file, compilerOptions: { mode: 'function' } })
 assert.deepEqual(result.errors, [])
 component.render = new Function('Vue', result.code)(Vue)
 return component
}
const formatMoney = (value, currency) => `${currency} ${value}`
const amountComponent = loadTableComponent('EntryAmountCell', { getAppLocale: () => 'en', formatMoney })
const tableComponent = loadTableComponent('EntryTable', { MemberIdentity: identity, EntryAmountCell: amountComponent, texts: tableTexts, isAreaSettled: () => false, getAppLocale: () => 'en', getAppLanguage: () => 'en', formatCalendarLabel: date => date.toISOString().slice(0, 10), formatMoney, formatSignedMoney: formatMoney })
const historicalEntry = { id: 7, user_id: 'a', date: 1793232000, amount: 12, type: 'expense', description: 'Payment', has_history: true }
async function renderEntryTable(interactive, desktop = true) {
 const app = Vue.createSSRApp({...tableComponent,data:()=>({...tableComponent.data(),desktopLayout:desktop})}, { entries: [historicalEntry], mode: 'project', currency: 'CHF', groupByDate: false, actionsEnabled: false, sortEnabled: interactive, rowClickable: interactive })
 app.config.globalProperties.$texts = tableTexts
 app.config.globalProperties.$enableReceipts = false
 return renderToString(app)
}
const readonlyTable = await renderEntryTable(false)
assert.match(await renderEntryTable(false, false), /mobile-history-action/, 'Read-only mobile payments offer direct history access without a menu')
assert.doesNotMatch(readonlyTable, /class="[^"]*(?:sortable|clickable-row|sort-icon)[^"]*"/)
assert.doesNotMatch(readonlyTable, /<tr[^>]*tabindex=/)
assert.match(readonlyTable, /entry-history-action/ , 'Read-only payments retain history in the actions menu')
assert.doesNotMatch(readonlyTable, /history-icon|editPayment|copyPayment|deletePaymentAction/ , 'History-only menus do not enable mutations')
assert.match(readonlyTable, /amount-value--expense/, 'Expense amounts use their semantic text color')
assert.doesNotMatch(readonlyTable, /payment-type-indicator/, 'Payment type icons are removed')
assert.match(readonlyTable, /CHF -12/, 'Colored amounts retain their signed value and saved currency')
const incomeAmountApp = Vue.createSSRApp(amountComponent, { entry: { type: 'income' }, amount: 12.34, currency: 'CHF' })
incomeAmountApp.config.globalProperties.$texts = tableTexts
incomeAmountApp.config.globalProperties.$enableReceipts = false
const incomeAmountHtml = await renderToString(incomeAmountApp)
assert.match(incomeAmountHtml, /amount-value--income/)
assert.doesNotMatch(incomeAmountHtml, /payment-type-indicator/)
assert.match(incomeAmountHtml, /CHF 12.34/)
const groupDate = new Date(historicalEntry.date * 1000)
const groupKeys = [tableComponent.methods.monthGroupKey(groupDate), tableComponent.methods.yearGroupKey(groupDate)]
const fullPeriodSummary = { income: 1234.56, expense: 567.89, balance: 666.67, count: 99 }
async function renderPeriodSummary(enableIncomes = true, summary = fullPeriodSummary, visibleKeys = groupKeys, groupReportResolver = null) {
 const app = Vue.createSSRApp({...tableComponent,data:()=>({...tableComponent.data(),desktopLayout:true})}, { entries: [historicalEntry], currency: 'CHF', actionsEnabled: false,
  dateGroups: { summaries: Object.fromEntries(groupKeys.map(key => [key, summary])), visibleKeys }, groupReportResolver })
 app.config.globalProperties.$texts = tableTexts
 app.config.globalProperties.$enableIncomes = enableIncomes
 app.config.globalProperties.$enableReceipts = false
 return renderToString(app)
}
const periodSummaryHtml = await renderPeriodSummary()
assert.equal((periodSummaryHtml.match(/class="date-group-balance"/g) || []).length, 2, 'Month and year both show a balance')
assert.doesNotMatch(periodSummaryHtml, /date-group-statistics|date-group-balance-label/, 'Only the period label and balance remain visible')
assert.match(periodSummaryHtml, /month-heading-row/, 'A month heading introduces the entries')
for (const amount of ['CHF 1234.56', 'CHF -567.89']) assert.equal((periodSummaryHtml.split(amount).length - 1), 2, 'The tooltip retains full filtered income and expense totals')
assert.equal(periodSummaryHtml.split('CHF 666.67').length - 1, 4, 'Both balances appear visibly and in their detail tooltips')
const expenseOnlySummaryHtml = await renderPeriodSummary(false)
assert.match(expenseOnlySummaryHtml, /CHF 1234.56/, 'Feature visibility cannot change the complete financial tooltip')
assert.match(expenseOnlySummaryHtml, /CHF -567.89/)
assert.match(expenseOnlySummaryHtml, /CHF 666.67/, 'Income visibility cannot change the supplied balance')
const zeroSummaryHtml = await renderPeriodSummary(true, { income: 0, expense: 0, balance: 0, count: 0 })
assert.match(zeroSummaryHtml, /date-group-amount is-neutral/)
assert.equal((zeroSummaryHtml.match(/CHF 0/g) || []).length, 8, 'Zero balances remain visible and all zero totals remain available in tooltips')
assert.equal((await renderPeriodSummary(true, fullPeriodSummary, [groupKeys[0]])).split('class="date-group-balance"').length - 1, 1, 'Pagination still controls which period totals belong on the current page')
const datedEntries = [new Date(2025,11,12,12),new Date(2026,0,12,12),new Date(2026,9,12,12)].map((date,id) => ({...historicalEntry,id,date:date.getTime()/1000}))
for (const entries of [datedEntries, [...datedEntries].reverse()]) {
 const context = { entries, sortDir: entries[0].date < entries[1].date ? 'asc' : 'desc', shouldGroupEntries:true, hasExternalDateGroups:false, dateGroupSummaries:{}, $texts:tableTexts }
 for (const [name,method] of Object.entries(tableComponent.methods)) context[name] = method.bind(context)
 const rows = tableComponent.computed.tableRows.call(context)
 assert.equal(rows.filter(row => row.type === 'month-heading').length, 3)
 assert.equal(rows.filter(row => row.type === 'group' && row.level === 'year').length, 2)
 assert.deepEqual(rows.filter(row => row.type === 'entry').map(row=>row.entry.id), entries.map(entry=>entry.id))
 for (const entry of entries) {
  const index = rows.findIndex(row => row.type === 'entry' && row.entry.id === entry.id)
  assert.equal(rows[index-2].type,'month-heading', 'Month headings precede daily sections and payments in both date directions')
  assert.equal(rows[index+1].level,'month', 'The correct month closes after its final payment')
 }
 const monthRows = rows.filter(row => row.type === 'group' && row.level === 'month')
 assert.ok(monthRows.every(row => row.dateTo >= row.dateFrom && new Date(row.dateFrom*1000).getDate() === 1))
 context.shouldGroupEntries = false
 assert.deepEqual(tableComponent.computed.tableRows.call(context).map(row=>row.type), ['entry','entry','entry'], 'Other sorting keeps one continuous ungrouped list')
}
// Reproduce a column switch while the previous API response is still displayed.
// Exercise Vue's keyed DOM patching, not just a fresh SSR rendering.
const hostNode = (type, text = '') => ({ type, text, children: [], props: {}, parent: null })
const sortingRenderer = Vue.createRenderer({
 createElement: hostNode, createText: text => hostNode('text',text), createComment: text => hostNode('comment',text),
 setText: (node,text) => {node.text=text},
 setElementText: (node,text) => {node.children.forEach(child=>child.parent=null);node.children=[];node.text=text},
 patchProp: (node,key,old,value) => {node.props[key]=value},
 parentNode: node => node.parent,
 nextSibling: node => node.parent?.children[node.parent.children.indexOf(node)+1] || null,
 insert(node,parent,anchor) {
  if (node.parent) {const old=node.parent;old.children.splice(old.children.indexOf(node),1)}
  node.parent=parent
  const index=parent.children.indexOf(anchor)
  parent.children.splice(index<0?parent.children.length:index,0,node)
 },
 remove(node) {if(node.parent){node.parent.children.splice(node.parent.children.indexOf(node),1);node.parent=null}},
})
const categoryOrder = [[9,8],[10,1],[9,22],[7,12],[9,1]].map(([month,day],index)=>({...historicalEntry,id:100+index,date:new Date(2026,month,day,12).getTime()/1000}))
const sortingState = Vue.reactive({entries:categoryOrder,sortBy:'category_name',sortDir:'desc'})
const sortingHost = hostNode('root'), sortWarnings=[]
const sortingApp = sortingRenderer.createApp({render:()=>Vue.h(tableComponent,{...sortingState,currency:'CHF',actionsEnabled:false})})
sortingApp.config.globalProperties.$texts=tableTexts
sortingApp.config.globalProperties.$enableReceipts=false
sortingApp.config.warnHandler=warning=>sortWarnings.push(warning)
sortingApp.mount(sortingHost)
const findHostNodes = (node, className) => [...(String(node.props.class||'').split(' ').includes(className)?[node]:[]), ...node.children.flatMap(child=>findHostNodes(child,className))]
for (const direction of ['desc','asc','desc']) {
 sortingState.sortBy='category_name';sortingState.entries=categoryOrder
 await Vue.nextTick()
 assert.equal(findHostNodes(sortingHost,'month-heading-row').length,0)
 sortingState.sortBy='date';sortingState.sortDir=direction
 await Vue.nextTick()
 assert.equal(findHostNodes(sortingHost,'month-heading-row').length,3,'Pending category-ordered data must never create duplicate month headers')
 sortingState.entries=[...categoryOrder].sort((a,b)=>(a.date-b.date)*(direction==='asc'?1:-1))
 await Vue.nextTick()
 assert.equal(findHostNodes(sortingHost,'month-heading-row').length,3,'The settled response must leave exactly one heading per month')
 assert.equal(findHostNodes(sortingHost,'date-group-row--month').length,3)
 assert.equal(findHostNodes(sortingHost,'date-group-row--year').length,1)
 assert.equal(findHostNodes(sortingHost,'entry-row').length,categoryOrder.length,'Column switching must neither lose nor duplicate payment rows')
 const context={entries:categoryOrder,sortDir:direction,shouldGroupEntries:true,hasExternalDateGroups:false,dateGroupSummaries:{},$texts:tableTexts}
 for(const [name,method] of Object.entries(tableComponent.methods)) context[name]=method.bind(context)
 const rows=tableComponent.computed.tableRows.call(context)
 assert.equal(new Set(rows.map(row=>row.key)).size,rows.length,'Intermediate rows have unique Vue keys')
 assert.deepEqual(rows.filter(row=>row.type==='entry').map(row=>row.entry.id),sortingState.entries.map(row=>row.id),'The temporary page follows the selected date direction')
 assert.deepEqual(categoryOrder.map(row=>row.id),[100,101,102,103,104],'Grouping must not mutate the caller’s array')
}
assert.equal(sortWarnings.filter(warning=>/Duplicate keys/.test(warning)).length,0)
sortingApp.unmount()
console.log('Payment sort transitions passed: category/date switches, delayed responses, both date directions and keyed Vue updates without duplicate headings, totals or payments.')
let reportNavigation
const location = {name:'annual-overview',query:{dateFrom:'2026-10-01'}}
tableComponent.methods.openGroupReport.call({groupReportResolver:()=>location,$router:{push:route=>reportNavigation=route}}, {})
assert.equal(reportNavigation,location)
reportNavigation = null
tableComponent.methods.openGroupReport.call({groupReportResolver:()=>null,$router:{push:route=>reportNavigation=route}}, {})
assert.equal(reportNavigation,null,'Unsupported reports cannot navigate')
const linkedSummaryHtml = await renderPeriodSummary(true,fullPeriodSummary,groupKeys,()=>location)
assert.equal((linkedSummaryHtml.match(/is-report-link/g)||[]).length,2,'Month and year closing rows become report links')
assert.match(linkedSummaryHtml, /<tr[^>]*is-report-link[^>]*tabindex="0"[^>]*aria-label="Report /, 'Closing rows are named keyboard targets')
assert.match(linkedSummaryHtml, /aria-description="Balance: CHF 666.67/, 'Keyboard users can read the detail totals on the row')
assert.doesNotMatch(linkedSummaryHtml,/group-report-button|ChartLineIcon/,'Closing rows have no separate report buttons')
assert.doesNotMatch(periodSummaryHtml, /is-report-link[^>]*tabindex="0"/, 'Historical rows without a report resolver remain inert')
const unavailableSummaryHtml = await renderPeriodSummary(true,fullPeriodSummary,groupKeys,()=>null)
assert.doesNotMatch(unavailableSummaryHtml,/is-report-link/,'Unsupported filters retain noninteractive rows')
const rowKeyTarget = {}, keyboardCalls = []
const keyboardContext = {groupReportResolver:()=>location,openGroupReport:row=>keyboardCalls.push(row)}
const closing = {key:'month-2026-10'}
for (const key of ['Enter',' ']) {
 let prevented = false
 tableComponent.methods.onGroupReportKeydown.call(keyboardContext,{key,target:rowKeyTarget,currentTarget:rowKeyTarget,preventDefault:()=>prevented=true},closing)
 assert.equal(prevented,true)
}
assert.deepEqual(keyboardCalls,[closing,closing])
for (const event of [{key:'Escape',target:rowKeyTarget},{key:'Enter',target:{}}]) {
 tableComponent.methods.onGroupReportKeydown.call(keyboardContext,{...event,currentTarget:rowKeyTarget,preventDefault:()=>assert.fail('Unrelated keyboard events must remain untouched')},closing)
}
keyboardContext.groupReportResolver = ()=>null
tableComponent.methods.onGroupReportKeydown.call(keyboardContext,{key:'Enter',target:rowKeyTarget,currentTarget:rowKeyTarget,preventDefault:()=>assert.fail('Unsupported reports must remain inert')},closing)
assert.equal(keyboardCalls.length,2)

console.log('Payment period balances passed: signed amounts with semantic text colors, saved currency, external full-period totals, month/year visibility, zero values and feature flags.')
const interactiveTable = await renderEntryTable(true)
assert.match(interactiveTable, /clickable-row/)
assert.match(interactiveTable, /sortable/)
assert.match(interactiveTable, /<th[^>]*tabindex="0"[^>]*aria-sort="descending"/, 'The active header exposes its sort direction and remains keyboard reachable')
assert.doesNotMatch(readonlyTable, /<th[^>]*tabindex="0"/, 'Historical table headers do not expose inert keyboard actions')
assert.equal(tableComponent.methods.sortAria.call({ sortEnabled: true, sortBy: 'amount', sortDir: 'asc' }, 'amount'), 'ascending')
assert.equal(tableComponent.methods.sortAria.call({ sortEnabled: false, sortBy: 'amount', sortDir: 'asc' }, 'amount'), undefined)
assert.match(interactiveTable, /<tr[^>]*tabindex="0"/)
const events = [], row = {}, childButton = {}
const tableVm = { rowClickable: false, sortEnabled: false, $emit: (...args) => events.push(args), scrollToTop: () => events.push(['scroll']) }
for (const method of ['onRowClick', 'onRowKeydown', 'emitSort']) tableVm[method] = tableComponent.methods[method].bind(tableVm)
let prevented = false
const keyEvent = key => ({ key, target: row, currentTarget: row, preventDefault: () => { prevented = true } })
tableVm.onRowClick(historicalEntry); tableVm.emitSort('date'); tableVm.onRowKeydown(keyEvent('Enter'), historicalEntry)
assert.deepEqual(events, []); assert.equal(prevented, false)
tableVm.rowClickable = true; tableVm.sortEnabled = true
for (const key of ['Enter', ' ']) { prevented = false; tableVm.onRowKeydown(keyEvent(key), historicalEntry); assert.equal(prevented, true) }
assert.equal(events.filter(event => event[0] === 'row-click').length, 2)
prevented = false
const beforeNestedKey = events.length
tableVm.onRowKeydown({ ...keyEvent(' '), target: childButton }, historicalEntry)
assert.equal(prevented, false, 'A nested history button keeps its native Space activation')
assert.equal(events.length, beforeNestedKey, 'Nested controls do not open the payment row')
tableVm.emitSort('amount'); assert.deepEqual(events.at(-1), ['sort', 'amount'])
assert.ok(source.indexOf('$texts.settlements.balanceBeforeSettlement()') < source.indexOf('$texts.settlements.repayments()'))
assert.match(source, /:sort-enabled="false"/); assert.match(source, /:row-clickable="false"/)
assert.match(source, /@history="openEntryHistory"/)
console.log('Historical payment table passed: no inert sorting/row tab stops, history menu retained, normal row/sort controls preserved and nested keyboard activation unobstructed.')

// Render the overview and selected detail independently: list rows never duplicate snapshots.
const viewDescriptor = parse(source).descriptor
const viewTemplate = compileTemplate({ source: viewDescriptor.template.content, filename: 'ProjectSettlementsView.vue', id: 'settlement-view-test', compilerOptions: { mode: 'function' } })
assert.deepEqual(viewTemplate.errors, [])
const dialogShell = { props: ['name'], setup: (props, {slots}) => () => Vue.h('div', {role: 'dialog', 'aria-label': props.name}, [slots.default?.(), slots.actions?.()]) }
const detailSnapshot = { ...settlement, createdAt: 1793232000, createdBy: 'a', createdByDisplayName: 'Alex', transfers: [{fromUserId:'a',toUserId:'b',amount:10}], balances: balances.map((balance, index) => ({...balance, paidCents: index === 0 ? 20000 : 0, receivedCents: 0})) }
const otherSnapshot = { ...detailSnapshot, id: 11, createdByDisplayName: 'Other creator', currency: 'USD' }
const summaryComponent = loadTableComponent('SettlementSummary', {MemberIdentity: identity})
async function renderOverview(openSettlementId = null) {
 const view = {
  ...component,
  components: Object.fromEntries(Object.keys(component.components).map(key => [key, key === 'DialogTransition' ? { props: ['show'], setup: (props, { slots }) => () => props.show ? slots.default?.({ returnFocus: () => false }) : null } : key === 'SettlementSummary' ? summaryComponent : key === 'NcDialog' ? dialogShell : key === 'MemberIdentity' ? identity : key === 'NcButton' ? { setup: (_, {slots}) => () => Vue.h('button', [slots.default?.(), slots.icon?.()]) } : shell])),
  render: new Function('Vue', viewTemplate.code)(Vue),
  data: () => ({ ...component.data(), loading: false, project: {id:4,name:'Area <name>'}, settlements: [detailSnapshot,otherSnapshot], openSettlementId, pagination: {limit:10,offset:0,total:2} }),
 }
 const app = Vue.createSSRApp(view, {id:'4'})
 const props = app.config.globalProperties
 props.$texts = new Proxy({}, {get: () => strings})
 props.$formatMoney = (value, currency) => `${currency} ${value}`; props.$currency = 'EUR'; props.$entriesPerPage = 25
 for (const key of ['FixedCosts','Subscriptions','ChildRelated','ImportantPayments','ReviewPayments','TaxRelevant','Insurance']) props['$enable'+key] = false
 return renderToString(app)
}
const overviewHtml = await renderOverview()
assert.equal((overviewHtml.match(/scope="col"/g) || []).length, 5)
assert.match(overviewHtml, /CHF 200/); assert.match(overviewHtml, /USD 200/);
assert.match(overviewHtml, /Alex/); assert.match(overviewHtml, /Other creator/)
assert.doesNotMatch(overviewHtml, /role="dialog"|settlement-detail-content/)
const detailHtml = await renderOverview(10)
assert.equal((detailHtml.match(/role="dialog"/g) || []).length, 1)
assert.match(detailHtml, /aria-label="settlementFrom:/)
assert.match(detailHtml, /Area &lt;name&gt;/)
assert.equal((detailHtml.match(/settlement-detail-content/g) || []).length, 1)
assert.match(detailHtml, /balanceBeforeSettlement/)
assert.match(detailHtml, /<dt>area::<\/dt>/)
assert.match(detailHtml, /<dt>author::<\/dt>/)
assert.match(detailHtml, /<dt>paymentCount::<\/dt>/)
assert.match(detailHtml, /class="settlement-summary"[\s\S]*?class="settlement-summary-total">CHF 200<\/dd>/, 'Dialog summary uses the same total and saved currency as its overview row')
assert.match(source, /<NcButton\s+variant="tertiary"\s+class="cobudget-native-button settlement-detail-print-button"/, 'Settlement detail header print button uses tertiary variant')
console.log('Settlement table/dialog rendering passed: compact overview, one selected snapshot, escaped area context and native detail action shells.')

// Totals use immutable integer-cent snapshots, never payment pages or repayment amounts.
const mixedSettlement = {id:91, currency:'CHF', balances:[
 {userId:'a', paidCents:10001, receivedCents:2002, fairShareCents:5000, balanceCents:5001},
 {userId:'former:b', paidCents:10002, receivedCents:0, fairShareCents:15003, balanceCents:-5001},
], transfers:[{amountCents:5001}]}
assert.equal(vm.settlementTotal(mixedSettlement), 220.05)
vm.settlementEntryStates['91'] = {entries:[{amount:9999}], total:100, limit:25, offset:25}
assert.equal(vm.settlementTotal(mixedSettlement), 220.05, 'Changing the loaded payment page never changes the settlement total')
assert.equal(vm.settlementTotal({balances:[{paidCents:20000, receivedCents:0}]}),200)
assert.equal(vm.settlementTotal({balances:[{paidCents:0, receivedCents:3025}]}),30.25)
assert.equal(vm.settlementTotal({balances:[]}),0)
assert.equal(vm.settlementTotal({balances:[{paidCents:1},{paidCents:2}]}),0.03, 'Legacy snapshots without income totals retain exact cents')
console.log('Settlement totals passed: all payments, mixed income/expenses, cent precision, former members, saved currencies and independence from payment pagination/repayments.')
