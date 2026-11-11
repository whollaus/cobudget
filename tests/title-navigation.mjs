import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'

const root = new URL('../', import.meta.url).pathname
const source = file => fs.readFileSync(path.join(root, file), 'utf8')
const moduleUrl = file => {
 const code = source(file).replace(/from ['"]([.][^'"]+)['"]/g, (_, relative) => {
  const dependency = path.normalize(path.join(path.dirname(file), relative))
  return 'from ' + JSON.stringify(moduleUrl(dependency.endsWith('.js') ? dependency : `${dependency}.js`))
 })
 return `data:text/javascript;charset=utf-8,${encodeURIComponent(code)}`
}
const { titleNavigationLocation } = await import(moduleUrl('src/services/titleNavigation.js'))
const annual = await import(moduleUrl('src/services/annualOverview.js'))
const { parsePersonalEntryListRouteQuery, personalPaymentView } = await import(moduleUrl('src/services/entryListRoute.js'))
const period = annual.annualSelection({ timeRange: 'custom', dateFrom: '2024-02-02', dateTo: '2024-02-29', search: 'private', projectId: 8 })
for (const id of ['', 'income', 'expense', 'future', 'important', 'review', 'fixedCost', 'childRelated', 'subscription', 'taxRelevant', 'insurance']) {
 const target = titleNavigationLocation({ id, kind: 'payment' }, period)
 const parsed = parsePersonalEntryListRouteQuery(target.query)
 assert.equal(target.name, 'personal')
 assert.equal(target.query.filter, id || undefined)
 assert.equal(target.query.search, undefined)
 assert.equal(target.query.projectId, undefined)
 assert.equal(parsed.filters.timeRange, 'custom')
 assert.equal(new Date(parsed.filters.dateFrom * 1000).getDate(), 2)
 assert.equal(new Date(parsed.filters.dateTo * 1000).getDate(), 29)
}
for (const id of ['income', 'important']) {
 const target = titleNavigationLocation({ id, kind: 'payment' }, annual.annualSelection({timeRange:'current-year'}))
 assert.equal(target.query.filter, id, 'Current year must not overwrite the selected view')
}
const all = titleNavigationLocation({ id: '', kind: 'payment' }, annual.annualSelection({timeRange:'all'}))
assert.deepEqual(all, {name:'personal',query:{timeRange:'all'}}, 'All time must survive the overview default year')
const report = titleNavigationLocation({ id: 'annual-overview', kind: 'report' }, period)
assert.equal(report.query.dateFrom, '2024-02-02')
assert.equal(report.query.search, undefined)
assert.equal(report.query.projectId, undefined)
const calendarReport = titleNavigationLocation({ id: 'annual-overview', kind: 'report' }, annual.annualSelection({timeRange:'calendar-selection',periodYears:[2024,2026],periodMonths:[2,8],categories:[7]}))
assert.equal(calendarReport.query.periodYears,'2024,2026')
assert.equal(calendarReport.query.periodMonths,'2,8')
assert.equal(calendarReport.query.categories,undefined,'Report navigation keeps the selected period but starts a fresh overview')
for (const id of ['projects', 'budgets', 'reminders', 'settings']) assert.deepEqual(titleNavigationLocation({id}, period), {name:id})

const script = source('src/components/ReportPeriodPicker.vue').split('<script>')[1].split('</script>')[0].replace(/^import .*$/gm, '').replace('export default', 'return')
const bindings = { CalendarIcon: {}, useId: () => Vue.useId(), ...annual, NcInputField:{}, NcButton:{}, NcCheckboxRadioSwitch:{}, NcFormBoxSwitch:{}, NcPopover:{}, ChevronDownIcon:{}, ArrowLeftIcon:{}, ChevronRightIcon:{}, AreaIcon:{}, NavigationIcon:{} }
const picker = new Function(...Object.keys(bindings), script)(...Object.values(bindings))
const navigated = [], changed = []
const state = {
 title: 'All payments', label: '2024', periodEnabled: true, selection: period, years: [2024], allowAll:true,
 appNavigation: {open:false, years:[2021], navigate:(...args)=>navigated.push(args)},
 $refs: {panel: {querySelector:()=>null,querySelectorAll:()=>[]}}, $nextTick: fn=>fn(), $emit:(...args)=>changed.push(args),
}
Object.assign(state, picker.data.call(state))
for (const [key, method] of Object.entries(picker.methods)) state[key] = method.bind(state)
for (const [key, getter] of Object.entries(picker.computed)) Object.defineProperty(state, key, {get:()=>getter.call(state)})
assert.equal(state.navigationEnabled, true)
assert.ok(state.yearOptions.includes(2021), 'Non-payment pages retain access to authorized historical years')
state.onShown(true)
assert.equal(state.navigationLevel, true)
state.openPeriod({id:'income',kind:'payment',label:'Income'})
assert.equal(state.navigationLevel, false)
assert.equal(navigated.length, 0, 'Entering a period submenu does not navigate')
state.escapeLevel()
assert.equal(state.navigationLevel, true)
assert.equal(navigated.length, 0, 'Back/Escape cancels the pending navigation')
state.openPeriod({id:'income',kind:'payment',label:'Income'})
state.choose('custom')
state.dates = {dateFrom:'2024-03-02',dateTo:'2024-03-01'}
state.apply()
assert.equal(navigated.length, 0, 'Invalid dates never navigate')
state.dates.dateTo = '2024-03-04'
state.apply()
assert.equal(navigated.length, 1)
assert.equal(navigated[0][0].id, 'income')
assert.equal(navigated[0][1].dateTo, '2024-03-04')
assert.equal(changed.length, 0, 'Selecting another view must not also change the old view')
state.onShown(true)
assert.equal(state.pendingNavigation, null)
state.openPeriod({id:'',current:true,label:'All payments'})
state.choose('all')
assert.equal(changed.length, 1, 'The active view uses its own filter-preserving period handler')
state.appNavigation.open = true
assert.equal(state.navigationEnabled, false)
state.onShown(true)
assert.equal(state.navigationLevel, false, 'Visible sidebar exposes periods directly')
console.log('Adaptive title navigation passed: period/view routing, all-time intent, private-filter isolation, deferred navigation, back/cancel, validation and sidebar state.')

assert.deepEqual(titleNavigationLocation({kind:'area',areaId:10}), {name:'project-detail',params:{id:'10'}}, 'Area switching targets the selected authorized area without transferring payment filters')
state.area = {id:8,name:'Demo',color:'#123456'}
state.appNavigation.areas = [{id:'area:8',areaId:8,kind:'area',label:'Demo',current:true},{id:'area:10',areaId:10,kind:'area',label:'Demo 2'}]
state.appNavigation.entries = [{id:'',kind:'payment',label:'Payments'},{id:'projects',label:'Areas',section:true},{id:'budgets',label:'Budget goals'},{id:'settings',label:'Settings'}]
assert.equal(state.navigationEnabled,true,'Area title navigation stays available beside a visible sidebar')
assert.deepEqual(state.navigationEntries.map(item=>item.id),['','projects','area:8','area:10','budgets','settings'])
assert.equal(state.navigationEntries[4].section,true,'Separate destinations after the area list')
state.appNavigation.open=false
assert.deepEqual(state.navigationEntries.map(item=>item.id),['','projects','area:8','area:10','budgets','settings'],'Sidebar state does not change area navigation')
state.area={id:12,name:'Archived current area'}
assert.ok(state.navigationEntries.some(item=>item.areaId===12 && item.current),'A loaded current area remains selectable even when absent from the active sidebar list')
state.area=null
state.areaNavigation=true
state.appNavigation.open=true
assert.equal(state.navigationEnabled,true,'The area overview always exposes navigation')
state.appNavigation.areas=[]
assert.deepEqual(state.navigationEntries.map(item=>item.id),['','projects','budgets','settings'])
assert.ok(!state.navigationEntries.find(item=>item.id==='budgets').section,'No extra separator for an empty area list')
state.areaNavigation=false
console.log('Area title navigation passed: destination order, area routes, sidebar independence, empty lists and current-area fallback.')

assert.deepEqual(titleNavigationLocation({kind:'budget',budgetId:7}, period), {name:'budget-edit',params:{id:'7'}}, 'Budget navigation targets the chosen goal without transferring payment filters')
state.budgetNavigation=true
state.currentBudgetId=7
state.budgetGoals=[{id:7,name:'Food'},{id:9,name:'Travel'}]
state.appNavigation.entries=[{id:'',kind:'payment'},{id:'projects',section:true},{id:'budgets'},{id:'annual-overview'},{id:'settings'}]
for (const open of [true,false]) {
 state.appNavigation.open=open
 assert.equal(state.navigationEnabled,true)
 assert.deepEqual(state.navigationEntries.map(item=>item.id),['','projects','budgets','budget:7','budget:9','annual-overview','settings'])
 assert.equal(state.navigationEntries.find(item=>item.id==='budget:7').current,true)
 assert.equal(state.navigationEntries.find(item=>item.id==='budget:9').current,false)
 assert.equal(state.navigationEntries.find(item=>item.id==='annual-overview').section,true)
}
state.budgetGoals=[]
assert.ok(!state.navigationEntries.find(item=>item.id==='annual-overview').section,'An empty budget list adds no separator')
state.budgetNavigation=false
console.log('Budget title navigation passed: direct routes, sidebar independence, current selection and conditional separator.')
// Every title-menu variant retains the personal reminder destination.
state.appNavigation.entries.push({ id: 'reminders', label: 'Reminders', current: true })
for (const mode of ['plain', 'area', 'budget']) {
 state.areaNavigation = mode === 'area'
 state.budgetNavigation = mode === 'budget'
 const reminder = state.navigationEntries.find(item => item.id === 'reminders')
 assert.equal(reminder?.current, true, `Reminders remains available in ${mode} navigation`)
}
state.areaNavigation = false
state.budgetNavigation = false


// Render the shared control in both sidebar states so every subpage gets the
// actual trigger/fallback behavior, rather than only checking source strings.
const Vue = await import('vue')
const {compile} = await import('@vue/compiler-dom')
const {renderToString} = await import('@vue/server-renderer')
const {parse} = await import('@vue/compiler-sfc')
const {descriptor} = parse(source('src/components/ReportPeriodPicker.vue'))
const render = new Function('Vue', compile(descriptor.template.content, {mode:'function',prefixIdentifiers:true}).code)(Vue)
const Button = {props:['disabled'], setup:(props,{slots})=>()=>Vue.h('button',{disabled:props.disabled},slots.default?.())}
const Popover = {setup:(_, {slots})=>()=>Vue.h('div',slots.trigger?.())}
const RenderedPicker = {...picker, render, components:{NavigationIcon:{render:()=>Vue.h('span')},AreaIcon:{render:()=>Vue.h('span')},NcButton:Button,NcPopover:Popover,ChevronDownIcon:{render:()=>Vue.h('span')},ChevronRightIcon:{render:()=>Vue.h('span')},ArrowLeftIcon:{render:()=>Vue.h('span')}}}
for (const title of ['Area settings','Edit budget goal','New area','Settlements','Payments','Report detail']) {
 for (const open of [false,true]) {
  const app = Vue.createSSRApp(RenderedPicker, {title,label:'',navigationOnly:true,periodEnabled:false})
  app.provide('appNavigation',{open,entries:[],years:[]})
  const html = await renderToString(app)
  assert.equal(html.includes('<button'),!open,`${title}: only collapsed navigation gets a title trigger`)
  assert.ok(html.includes(title))
  assert.equal(html.includes('report-period-static'),open)
 }
}
const disabledApp = Vue.createSSRApp(RenderedPicker,{title:'Edit budget goal',label:'',navigationOnly:true,periodEnabled:false,disabled:true})
disabledApp.provide('appNavigation',{open:false,entries:[]})
assert.ok((await renderToString(disabledApp)).includes('disabled'), 'Pending form writes disable title navigation like the Back action')
console.log('Subpage title rendering passed: collapsed-only triggers, visible-sidebar text and busy state.')

// Render actual payment navigation, including empty views and combined filters.
const appSource = source('src/App.vue')
const start = appSource.indexOf('<NcAppNavigationItem :name="$texts.labels.allPayments()"')
const end = appSource.indexOf('<NcAppNavigationItem v-if="$enableReminders" :name="$texts.reminders.sidebar()"', start)
const sidebarRender = new Function('Vue', compile(`<ul>${appSource.slice(start, end)}</ul>`, {mode:'function',prefixIdentifiers:true}).code)(Vue)
const item = {props:['name','active'],setup:(props,{slots})=>()=>Vue.h('li',{'data-active':props.active},[props.name,slots.icon?.()])}
async function renderNavigation(query, flags = {}) {
 const icon = {render:()=>Vue.h('i')}
 const app = Vue.createSSRApp({render:sidebarRender,computed:{activePaymentView:()=>personalPaymentView(query)},methods:{navigateTo(){}},components:{NcAppNavigationItem:item,CoBudgetIcon:icon,PlusIcon:icon,MinusIcon:icon,CalendarSyncOutlineIcon:icon}})
 app.config.globalProperties.$enableIncomes=flags.income ?? true
 app.config.globalProperties.$enableFuturePayments=flags.future ?? true
 app.config.globalProperties.$texts={labels:{allPayments:()=> 'All payments',incomePayments:()=> 'Income'},dashboard:{expenses:()=> 'Expenses',planned:()=> 'Planned'}}
 return renderToString(app)
}
for (const query of [{},{filter:'review'},{filter:'income',labels:'review,important'},{filter:'expense'},{filter:'future',type:'income',labels:'review'}]) {
 const html=await renderNavigation(query)
 assert.equal((html.match(/<li /g)||[]).length,4,'Empty payment views stay accessible')
 assert.equal((html.match(/data-active="true"/g)||[]).length,1,'Exactly one payment view stays active with label combinations')
 assert.doesNotMatch(html,/Important|Review|Tax-relevant/)
}
assert.equal((await renderNavigation({}, {income:false,future:false})).match(/<li /g).length,2,'Feature gates still hide disabled income/planning')
assert.equal(personalPaymentView({filter:'review',type:'expense'}),'expense')
assert.equal(personalPaymentView({filter:'future',type:'income'}),'future')
console.log('Four-view payment navigation passed: feature gates, empty views, legacy links and combined labels/type/planning identity.')

// The shared header owns one creation slot before the title, with trailing tools separate.
const headerScript=source('src/components/AppPageHeader.vue').split('<script>')[1].split('</script>')[0].replace(/^import .*$/gm,'').replace('export default','return')
const pageHeader=new Function('ReportPeriodPicker',headerScript)({props:['title'],setup:props=>()=>Vue.h('span',props.title)})
pageHeader.render=new Function('Vue',compile(source('src/components/AppPageHeader.vue').split('<template>')[1].split('</template>')[0],{mode:'function',prefixIdentifiers:true}).code)(Vue)
for(const create of [true,false]) {
 const app=Vue.createSSRApp({setup:()=>()=>Vue.h(pageHeader,{title:'Payments'},{
  ...(create ? {creation:()=>Vue.h('button',{'data-create':'income'},'Income')} : {}),
  title:()=>Vue.h('span',{'data-title':true},'Payments 2026'),
  actions:()=>Vue.h('button',{'data-export':true},'Export'),
 })})
 const html=await renderToString(app)
 assert.equal(html.includes('class="app-page-header__creation app-page-header__actions"'),create)
 assert.equal(html.includes('app-page-header--has-creation'),create)
 if(create)assert.ok(html.indexOf('data-create')<html.indexOf('data-title'))
 assert.ok(html.indexOf('data-title')<html.indexOf('data-export'))
 assert.equal((html.match(/data-create/g)||[]).length,create?1:0,'Creation is a single control shared with the mobile FAB')
}
console.log('Shared creation placement passed: optional leading action, one control, unchanged title fallback and trailing tools.')

// Use the actual app menu builder to verify separators with feature gates.
const entriesBody = appSource.split('titleNavigationEntries() {')[1].split('\n\t\tentryViewProps()')[0].replace(/},\s*$/, '')
const entriesGetter = new Function(entriesBody)
const labels = new Proxy({}, {get: (_, key) => () => key})
for (const projects of [true, false]) for (const reminders of [true, false]) {
 const entries = entriesGetter.call({$texts: {labels, dashboard: labels, reminders: labels, settings: labels, budgetGoals: labels, annual: labels}, $route: {name: 'reminders', query: {}}, $enableProjects: projects, $enableReminders: reminders, $enableBudgetGoals: true, $enableIncomes: true, $enableFuturePayments: true})
 const index = entries.findIndex(item => item.id === 'reminders')
 assert.equal(index >= 0, reminders)
 const last = reminders ? index : entries.findIndex(item => item.id === 'future')
 if (reminders) assert.equal(entries[index - 1].id, 'future')
 assert.equal(entries[last].section, false)
 assert.equal(entries[last + 1].section, true, 'Module separator remains when reminders are hidden')
}
