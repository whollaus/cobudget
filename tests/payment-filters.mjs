import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import * as Vue from 'vue'
import { parse } from '@vue/compiler-sfc'
import { compile } from '@vue/compiler-dom'
import { renderToString } from '@vue/server-renderer'

const root = new URL('../', import.meta.url).pathname
const source = file => fs.readFileSync(path.join(root, file), 'utf8')
function moduleUrl(file) {
 const code = source(file).replace(/from ['"]([.][^'"]+)['"]/g, (_, relative) => {
  const dependency = path.normalize(path.join(path.dirname(file), relative))
  return 'from ' + JSON.stringify(moduleUrl(dependency.endsWith('.js') ? dependency : dependency + '.js'))
 })
 return `data:text/javascript;charset=utf-8,${encodeURIComponent(code)}`
}
const routes = await import(moduleUrl('src/services/entryListRoute.js'))
const { createPersonalEntryFilters, selectedPaymentLabels, withPaymentLabels, personalPaymentView, parsePersonalEntryListRouteQuery, serializePersonalEntryListRouteQuery } = routes
const features = {$enableProjects:true,$enableIncomes:true,$enableImportantPayments:true,$enableReviewPayments:true,$enableFixedCosts:true,$enableChildRelated:true,$enableSubscriptions:true,$enableTaxRelevant:true,$enableInsurance:true,$enableReceipts:true,$enableReminders:true}
const texts = new Proxy({}, { get: (_, section) => new Proxy({}, { get: (_, key) => () => `${section}.${key}` }) })
const shell = tag => ({ setup: (_, { slots, attrs }) => () => Vue.h(tag, attrs, [slots.trigger?.(), slots.icon?.(), slots.default?.()]) })
const amounts = await import(moduleUrl('src/services/annualOverview.js'))
const { filterChipIcon } = await import(`data:text/javascript;charset=utf-8,${encodeURIComponent(fs.readFileSync(new URL('../src/services/filterChipIcons.js', import.meta.url), 'utf8').replace("'@mdi/js'", JSON.stringify(new URL('../node_modules/@mdi/js/mdi.js', import.meta.url).href)))}`)
const bindings = { filterChipIcon, ClipboardCheckIcon: shell('i'), CalendarOutlineIcon: shell('i'), ShapeOutlineIcon: shell('i'), AccountOutlineIcon: shell('i'), FolderIcon: shell('i'), TagOutlineIcon: shell('i'), PoundIcon: shell('i'), BellOutlineIcon: shell('i'), PaperclipIcon: shell('i'), CashIcon: shell('i'), CalendarClockOutlineIcon: shell('i'),  ReportPeriodPicker: {}, PlusIcon: shell('i'), MinusIcon: shell('i'), useId: Vue.useId, ...routes, ...amounts, NcButton:shell('button'), NcChip:{props:['text'],setup:props=>()=>Vue.h('span',{class:'chip'},props.text)},
 ArrowLeftIcon:shell('i'), ChevronRightIcon:shell('i'), CheckIcon:shell('i'), CategoryIcon:shell('i'), AreaIcon:shell('i'), ChevronDownIcon:shell('i'), MagnifyIcon:shell('i'), FilterVariantIcon:shell('i'), NavigationIcon:shell('i'), AnchoredPopover:shell('section'),
 TableFilters:{props:['section'],setup:props=>()=>Vue.h('div',{'data-filter-section':props.section || 'all'})},
 NcFormBoxSwitch:shell('label'), NcInputField:shell('input'), NcCheckboxRadioSwitch:shell('label'), NcRadioGroup:shell('div'), NcRadioGroupButton:shell('button'), PaymentFilterSelect:shell('section'),
 categoryParentId:() => null, categoryPathLabel:category=>category.name, sortCategoriesHierarchically:rows=>rows, sortAreasByName:rows=>rows, getAppLocale:()=> 'en-US',
}
function component(file) {
 const {descriptor}=parse(source(file))
 const options=new Function(...Object.keys(bindings),descriptor.script.content.replace(/^import .*$/gm,'').replace('export default','return'))(...Object.values(bindings))
 options.render=new Function('Vue',compile(descriptor.template.content,{mode:'function',prefixIdentifiers:true}).code)(Vue)
 return options
}
const bar=component('src/components/PaymentFilterBar.vue')
function state(filters={}) {
 const value={filters:{...createPersonalEntryFilters(),...filters},categories:[],projects:[],paymentPartners:[],hashtags:[],labelCounts:{important:12,review:3,taxRelevant:8},filterAvailability:null,defaultTimeRange:filters.tags==='future'?'all':'currentYear',
 $texts:texts,$enableProjects:true,$enableReviewPayments:true,$emit:(event,filters)=>{assert.equal(event,'update:filters');value.filters=filters},$nextTick:fn=>fn()}
 value.$refs={}
 for(const [name,method] of Object.entries(bar.methods))value[name]=method.bind(value)
 for(const [name,getter] of Object.entries(bar.computed))Object.defineProperty(value,name,{get:()=>getter.call(value)})
 return value
}
for(const view of ['all','income','expense','future']) {
 const selected={...createPersonalEntryFilters(),type:['income','expense'].includes(view)?view:'all',tags:view==='future'?'future':'important',narrowLabels:view==='future'?'important':'',timeRange:'custom',dateFrom:1700000000,dateTo:1705000000,projectId:7,search:'needle',annualReturn:'return-context'}
 const value=state(selected)
 value.update(withPaymentLabels(value.filters, ['important','review']))
 assert.deepEqual(value.labels,['important','review'])
 for(const key of ['type','projectId','dateFrom','dateTo','search','annualReturn'])assert.equal(value.filters[key],selected[key])
 if(view==='future')assert.equal(value.filters.tags,'future')
 const restored=parsePersonalEntryListRouteQuery(serializePersonalEntryListRouteQuery({filters:value.filters})).filters
 assert.deepEqual(selectedPaymentLabels(restored).sort(),['important','review'])
 assert.equal(personalPaymentView(serializePersonalEntryListRouteQuery({filters:value.filters})),view==='all'?'':view)
 value.removeChip(value.chips.find(chip=>chip.key==='label:review'))
 assert.deepEqual(value.labels,['important'])
 assert.equal(value.labelCounts.review,3,'Counts remain independent of filters and resets')
}
const reviewShortcut = state({ tags:'important' })
assert.equal(reviewShortcut.reviewActive, false)
reviewShortcut.toggleReview()
assert.deepEqual(reviewShortcut.labels, ['review'])
assert.equal(reviewShortcut.reviewActive, true)
reviewShortcut.toggleReview()
assert.deepEqual(reviewShortcut.labels, [])
const periodOnly=state({timeRange:'lastMonth'})
assert.equal(periodOnly.chips.length,0)
assert.equal(periodOnly.hasActiveFilters,true)
const calendarChip = state({ timeRange:'calendarSelection', periodYears:'2025,2026', periodMonths:'1,2', search:'needle' })
calendarChip.periodChipLabel = '2025, 2026 · Jan., Feb.'
const activePeriodChip = calendarChip.chips.find(chip => chip.key === 'timeRange')
assert.equal(activePeriodChip.label, calendarChip.periodChipLabel)
calendarChip.removeChip(activePeriodChip)
assert.equal(calendarChip.filters.timeRange, 'currentYear')
assert.equal(calendarChip.filters.periodYears, '')
assert.equal(calendarChip.filters.periodMonths, '')
assert.equal(calendarChip.filters.search, 'needle')
const focusState=state()
const trigger={}
focusState.$refs.tagsTrigger={$el:trigger}
assert.equal(focusState.triggerElement('tags'),trigger,'Native popover returns to the actual trigger')
assert.equal(focusState.triggerElement('missing'),false)
const legacy=state(parsePersonalEntryListRouteQuery({filter:'review',type:'expense',projects:'0,7',categories:'0,2',labels:'important',timeRange:'all'}).filters)
assert.deepEqual(legacy.labels,['review','important'])
legacy.removeChip(legacy.chips.find(chip=>chip.key==='label:review'))
assert.deepEqual(legacy.labels,['important'])
assert.equal(legacy.filters.type,'expense')
legacy.removeChip(legacy.chips.find(chip=>chip.key==='projectId'))
assert.equal(legacy.filters.projectId,null);assert.equal(legacy.filters.narrowProjects,'')
assert.equal(legacy.filters.narrowCategories,'0,2','Removing one dimension preserves other filters')
const hashtag=state({hashtagId:3})
hashtag.hashtags=[{id:3,displayName:'Example tag'}]
assert.equal(hashtag.chips.find(chip=>chip.key==='hashtagId').label, '#Example tag')
const unassigned=state({narrowProjects:'0'})
assert.ok(unassigned.chips[0].label.includes('annual.withoutArea'))
unassigned.removeChip(unassigned.chips[0]);assert.equal(unassigned.chips.length,0)
assert.deepEqual(selectedPaymentLabels(withPaymentLabels({tags:'future',type:'income'},['review','review','invalid'])),['review'])
assert.equal(withPaymentLabels({tags:'future'},[]).tags,'future')

// Render the actual toolbar template, retaining native-control boundaries.
const app=Vue.createSSRApp(bar,{filters:{...createPersonalEntryFilters(),tags:'review'},labelCounts:{review:3}})
Object.assign(app.config.globalProperties,{...features,$texts:texts,$enableProjects:true,$enableReviewPayments:true})
const html=await renderToString(app)
assert.match(html,/data-filter-section="categoryId"/)
assert.match(html,/data-filter-section="paymentPartnerId"/)
assert.match(html,/data-filter-section="all"/,'Full filter menu is always available')
const chipsApp=Vue.createSSRApp(bar,{filters:{...createPersonalEntryFilters(),tags:'review'},chipsOnly:true})
Object.assign(chipsApp.config.globalProperties,{...features,$texts:texts})
const chipsHtml=await renderToString(chipsApp)
assert.match(chipsHtml,/labels.review/)
assert.doesNotMatch(chipsHtml,/data-filter-section/)

// Direct dropdowns reuse the real checkbox selections and preserve the view on reset.
bindings.PaymentFilterSelect=component('src/components/PaymentFilterSelect.vue')
const categoryChoices = bindings.PaymentFilterSelect
const categorySelections = []
const categoryMenu = {
 groupKinds: ['income','expense'], modelValue: [], options: [{value:4,type:'income'},{value:7,type:'expense'}],
 $emit: (_, values) => { categorySelections.push(values); categoryMenu.modelValue = values },
}
for (const [name, method] of Object.entries(categoryChoices.methods)) categoryMenu[name] = method.bind(categoryMenu)
Object.defineProperty(categoryMenu, 'groupSelection', { get: () => categoryChoices.computed.groupSelection.call(categoryMenu) })
assert.deepEqual(categoryMenu.groupSelection, [0,'income','expense'])
categoryMenu.toggleGroupedOption(categoryMenu.options[0])
assert.deepEqual(categorySelections.at(-1), [0,'expense',4])
categoryMenu.toggleGroupedOption({value:0,groupSwitch:true})
assert.deepEqual(categorySelections.at(-1), ['expense',4])
categoryMenu.toggleGroupedOption({value:'income',groupSwitch:true})
assert.deepEqual(categorySelections.at(-1), ['expense','income'])
categoryMenu.toggleGroupedOption({value:0,groupSwitch:true})
assert.deepEqual(categorySelections.at(-1), [])
assert.equal(parsePersonalEntryListRouteQuery({ categories: '4,expense' }).filters.narrowCategories, '4,expense')
assert.equal(parsePersonalEntryListRouteQuery({ partners: '4,expense' }).filters.narrowPartners, '4,expense')
assert.equal(parsePersonalEntryListRouteQuery({ projects: 'assigned' }).filters.narrowProjects, 'assigned')
const table=component('src/components/TableFilters.vue')
let emitted=[]
const filterState={section:'tags',excludedSections:[],hashtags:[{id:9,name:'Example'},{id:10,name:'Other'}],categories:[],paymentPartners:[],projects:[],labelCounts:{important:12,review:3,taxRelevant:8},preservePaymentView:true,showTimeRange:false,
 initialFilters:{...createPersonalEntryFilters(),type:'expense',tags:'review',timeRange:'currentYear'},$texts:texts,$enableImportantPayments:true,$enableReviewPayments:true,$enableFixedCosts:true,$enableChildRelated:true,$enableSubscriptions:true,$enableTaxRelevant:true,$enableInsurance:true,$emit:(...args)=>emitted.push(args)}
Object.assign(filterState,table.data.call(filterState))
for(const [name,method]of Object.entries(table.methods))filterState[name]=method.bind(filterState)
for(const [name,getter]of Object.entries(table.computed))Object.defineProperty(filterState,name,{get:()=>getter.call(filterState)})
assert.equal(filterState.activeSubmenu,'tags')
for(const key of ['important','review','taxRelevant'])assert.equal(filterState.tagsChoices.find(option=>option.value===key).count,filterState.labelCounts[key])
assert.equal(filterState.tagsChoices.find(option=>option.value==='fixedCost').count,undefined)
filterState.setHashtags([9,10])
assert.equal(filterState.localFilters.narrowHashtags,'9,10')
assert.deepEqual(filterState.selectedLabels,['review'],'Tags preserve selected labels')
filterState.setLabels(['review','important'])
assert.deepEqual(filterState.selectedLabels,['review','important'])
assert.equal(filterState.localFilters.narrowHashtags,'9,10','Labels preserve selected tags')
filterState.amountMinDraft='100,50'
filterState.amountMaxDraft='200'
filterState.applyAmount()
assert.equal(emitted.at(-1)[1].amountMin,'100.50')
assert.equal(emitted.at(-1)[1].amountMax,'200')
const beforeInvalid=emitted.length
filterState.amountMaxDraft='50'
filterState.applyAmount()
assert.equal(filterState.amountError,true)
assert.equal(emitted.length,beforeInvalid)
filterState.amountMaxDraft=''
filterState.applyAmount()
assert.equal(emitted.at(-1)[1].amountMax,'')
const roundTrip=parsePersonalEntryListRouteQuery(serializePersonalEntryListRouteQuery({filters:filterState.localFilters})).filters
assert.equal(roundTrip.type,'expense')
assert.equal(roundTrip.narrowHashtags,'9,10')
assert.deepEqual(selectedPaymentLabels(roundTrip).sort(),['important','review'])
for (const [section, expected, other] of [['tags','analytics.labels','annual.tags'], ['hashtagId','annual.tags','analytics.labels']]) {
 const menu = Vue.createSSRApp(table,{section,groupLookupFilters:true,initialFilters:filterState.localFilters,hashtags:filterState.hashtags})
 Object.assign(menu.config.globalProperties,{...features,$texts:texts,$enableFuturePayments:true})
 const html = await renderToString(menu)
 assert.ok(html.includes(`aria-label="${expected}" role="group"`))
 assert.ok(!html.includes(`aria-label="${other}" role="group"`))
 assert.doesNotMatch(html,/filters.futurePayments/)
}
const areaDefaults = Vue.createSSRApp(table, { groupLookupFilters:true, showTypeButtons:true, showTimeRange:false, resetTimeRange:'all', excludedSections:['projectId'], initialFilters:routes.createProjectEntryFilters() })
Object.assign(areaDefaults.config.globalProperties,{...features,$texts:texts})
assert.doesNotMatch(await renderToString(areaDefaults), /filters.resetAll/)
const noAreas = Vue.createSSRApp(table, { groupLookupFilters:true, initialFilters:createPersonalEntryFilters(), projects:[] })
Object.assign(noAreas.config.globalProperties,{...features,$texts:texts})
assert.doesNotMatch(await renderToString(noAreas), /analytics.areas/)
const disabledFeatures = Vue.createSSRApp(table, {
 groupLookupFilters:true, showTypeButtons:true, initialFilters:createPersonalEntryFilters(),
 projects:[{id:7,name:'Area'}], categories:[{id:4,type:'income',name:'Income category'},{id:5,type:'expense',name:'Expense category'}],
})
Object.assign(disabledFeatures.config.globalProperties,{...Object.fromEntries(Object.keys(features).map(key=>[key,false])), $texts:texts, $enableFuturePayments:false})
assert.doesNotMatch(await renderToString(disabledFeatures), /entry.paymentType|analytics.areas|analytics.labels|filters.futurePayments/)
assert.deepEqual(table.computed.filteredCategories.call({
 $enableIncomes:false, groupLookupFilters:true, categories:[{id:4,type:'income'},{id:5,type:'expense'}],
}).map(item=>item.id), [5])
assert.deepEqual(table.computed.filteredPaymentPartners.call({
 $enableIncomes:false, groupLookupFilters:true, paymentPartners:[{id:4,type:'income'},{id:5,type:'expense'}],
}).map(item=>item.id), [5])
const moreApp=Vue.createSSRApp(table,{initialFilters:filterState.localFilters,excludedSections:['search','type','projectId','categoryId','paymentPartnerId','tags','hashtagId'],hashtags:filterState.hashtags,paymentPartners:[{id:4,name:'Example partner'}],filterAvailability:{hasReminders:true}})
Object.assign(moreApp.config.globalProperties,{...features,$texts:texts,$enableIncomes:true,$enableProjects:true,$enableReviewPayments:true})
const moreHtml=await renderToString(moreApp)
assert.doesNotMatch(moreHtml,/analytics\.(areas|categories|labels|paymentPartners)|filters\.(incomeAndExpense|search)|annual.tags/)
assert.match(moreHtml,/filters.allReminders/)
moreApp.config.globalProperties.$enableReminders=false
assert.doesNotMatch(await renderToString(moreApp),/filters.allReminders/, 'Reminder filters are hidden while delivery remains enabled')

filterState.localFilters.search='apply before another filter'
filterState.debouncedEmit()
filterState.flushSearch()
assert.equal(emitted.at(-1)[1].search,'apply before another filter','Blur/Enter commits the search draft before switching filters')
assert.equal(filterState.debounceTimer,null)
filterState.localFilters.search='stale'
filterState.debouncedEmit()
filterState.clearFilters()
const count=emitted.length
await new Promise(resolve=>setTimeout(resolve,340))
assert.equal(emitted.length,count,'Reset cancels pending search work')
assert.equal(filterState.localFilters.type,'expense')
assert.equal(filterState.localFilters.tags,'all')
assert.equal(filterState.localFilters.timeRange,'currentYear')
assert.equal(filterState.hasActiveFilters,false,'View context alone does not leave a redundant Reset action')
filterState.showTypeButtons = true
filterState.resetTimeRange = 'currentYear'
filterState.localFilters.tags = 'future'
assert.equal(filterState.hasActiveFilters, true)
filterState.clearFilters()
assert.equal(filterState.localFilters.tags, 'all')
assert.equal(filterState.localFilters.timeRange, 'currentYear')
console.log('Payment filters passed: view identity, legacy links, label union/toggle, counts, chips, unassigned scope, native toolbar rendering and debounce-safe reset.')
