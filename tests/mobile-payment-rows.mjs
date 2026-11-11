import assert from 'node:assert/strict'
import fs from 'node:fs'
import * as Vue from 'vue'
import { parse, compileTemplate, compileStyle } from '@vue/compiler-sfc'
import { renderToString } from '@vue/server-renderer'

const {fitPaymentColumns,fitPaymentLabels}=await import(`data:text/javascript,${encodeURIComponent(fs.readFileSync(new URL('../src/utils/paymentColumnWidths.js',import.meta.url),'utf8'))}`)
const {isAreaSettled}=await import(`data:text/javascript,${encodeURIComponent(fs.readFileSync(new URL('../src/utils/areaSettlementStatus.js',import.meta.url),'utf8'))}`)
const styles=[]
function component(file, dependencies={}) {
 const source=fs.readFileSync(new URL(`../${file}`,import.meta.url),'utf8'), descriptor=parse(source).descriptor
 const imports=[...descriptor.script.content.matchAll(/^import (.*?) from .*$/gm)].flatMap(match=>match[1].replace(/[{}]/g,'').split(',').map(name=>name.trim()))
 for(const name of imports) assert.ok(name in dependencies,`Missing fixture dependency ${name} in ${file}`)
 const result=new Function(...imports,descriptor.script.content.replace(/^import .*$/gm,'').replace('export default','return'))(...imports.map(name=>dependencies[name]))
 const id=`data-v-preview-${styles.length}`
 const template=compileTemplate({source:descriptor.template.content,filename:file,id,compilerOptions:{mode:'function'}})
 assert.deepEqual(template.errors,[])
 result.render=new Function('Vue',template.code)(Vue);result.__scopeId=id
 for(const style of descriptor.styles) {
  const css=compileStyle({source:style.content,filename:file,id,scoped:style.scoped})
  assert.deepEqual(css.errors,[]);styles.push(css.code)
 }
 // Even components without styles need distinct scope IDs in this fixture.
 styles.push('')
 return result
}
const icon=name=>component(`node_modules/vue-material-design-icons/${name}.vue`)
const category=component('src/components/CategoryIcon.vue',{categoryIcons:{Home:icon('HomeOutline'),Shape:icon('ShapeOutline')},categoryIconNames:['Home','Shape']})
const avatar={props:['user','displayName'],render(){return Vue.h('span',{class:'fixture-avatar','data-user':this.user},this.displayName?.slice(0,1))}}
const identity=component('src/components/MemberIdentity.vue',{NcAvatar:avatar})
const tooltip={props:['text','passive'],render(){return Vue.h('span',{class:'table-tooltip-trigger','aria-label':this.text,tabindex:this.passive?undefined:0},this.$slots.default?.())}}
const navImports=[...parse(fs.readFileSync(new URL('../src/components/NavigationIcon.vue',import.meta.url),'utf8')).descriptor.script.content.matchAll(/^import (\w+) from .*$/gm)].map(match=>match[1])
const navigation=component('src/components/NavigationIcon.vue',Object.fromEntries(navImports.map(name=>[name,name==='CoBudgetIcon'?{render:()=>null}:icon(name)])))
const paymentLabels=component('src/components/PaymentLabelList.vue',{NavigationIcon:navigation,TableTooltip:tooltip,fitPaymentLabels})
const areaIcon=component('src/components/AreaIcon.vue',{FolderIcon:icon('Folder'),FolderOutlineIcon:icon('FolderOutline'),WalletIcon:icon('WalletOutline')})
const area=component('src/components/EntryAreaCell.vue',{FolderCheckIcon:icon('FolderCheck'),AreaIcon:areaIcon,TableTooltip:tooltip})
const description=component('src/components/EntryDescriptionCell.vue',{NcAvatar:avatar,CategoryIcon:category,TrendingUpIcon:icon('TrendingUp'),TrendingDownIcon:icon('TrendingDown'),PaymentLabelList:paymentLabels,TableTooltip:tooltip})
const formatMoney=(value,currency='EUR',options={})=>new Intl.NumberFormat('de-AT',{style:'currency',currency,...options}).format(value)
const amount=component('src/components/EntryAmountCell.vue',{
 AccountMultipleCheckIcon:icon('AccountMultipleCheck'),AccountMultipleIcon:icon('AccountMultiple'),BellRingIcon:icon('BellRing'),CheckCircleOutlineIcon:icon('CheckCircleOutline'),HistoryIcon:icon('History'),PaperclipIcon:icon('Paperclip'),SyncIcon:icon('Sync'),TableTooltip:tooltip,getAppLocale:()=> 'de-AT',formatMoney,
})
const proxy=new Proxy({},{get:(_,key)=>(...args)=>`${key}${args.length?': '+args.join(', '):''}`})
const texts={common:{...proxy,income:()=> 'Einnahme',expense:()=> 'Ausgabe'},labels:proxy,entry:new Proxy({groupClosingTitle:label=>`${label} gesamt`,paidByPerson:name=>`Bezahlt von ${name}`},{get:(target,key)=>target[key]??proxy[key]})}
async function render(target,props,receipts=true,projects=true) {
 const app=Vue.createSSRApp(target,props)
 app.config.globalProperties.$texts=texts;app.config.globalProperties.$enableReceipts=receipts;app.config.globalProperties.$enableProjects=projects
 return renderToString(app)
}
const mobilePart=html=>html.split('class="desc-text')[0]
const detailed={id:1,type:'expense',category_name:'Strom',category_icon:'Home',paymentPartner:'Stadtwerke',description:'Abschlag #Oktober <safe>',is_fixed_cost:true}
const detailedHtml=mobilePart(await render(description,{entry:detailed,dateText:'01.10.2026',showMobileDate:false}))
assert.match(detailedHtml,/class="mobile-primary-title"[^>]*>Strom</)
assert.match(detailedHtml,/home-outline-icon/)
for(const [before,after] of [['mobile-primary-title','mobile-payment-partner'],['mobile-payment-partner','mobile-description'],['mobile-badges','mobile-description']]) assert.ok(detailedHtml.indexOf(before)<detailedHtml.indexOf(after),'Mobile information keeps its fixed hierarchy')
assert.match(detailedHtml,/description-hashtag[^>]*>#Oktober/)
assert.match(detailedHtml,/&lt;safe&gt;/)
assert.doesNotMatch(detailedHtml,/mobile-area-badge|mobile-project-badge/,'Category and description do not repeat the mobile area')
assert.doesNotMatch(detailedHtml,/01\.10\.2026|mobile-category/,'Grouped rows do not repeat dates or move the category to secondary text')
for(const [type,label,glyph] of [['expense','Ausgabe','trending-down-icon'],['income','Einnahme','trending-up-icon']]) {
 const html=mobilePart(await render(description,{entry:{type},dateText:'01.10.2026',showMobileDate:false}))
 assert.match(html,new RegExp(`class="mobile-primary-title"[^>]*>${label}<`));assert.ok(html.includes(glyph))
 assert.doesNotMatch(html,/class="mobile-payment-partner"|class="mobile-description"|class="mobile-badges"/,'Missing fields leave no empty metadata rows')
}
const unknown=mobilePart(await render(description,{entry:{type:'income',category_name:'Sonstiges',category_icon:'missing',paymentPartner:'Employer',description:'Salary'},dateText:'01.10.2026'}))
assert.match(unknown,/shape-outline-icon/);assert.match(unknown,/class="mobile-primary-title"[^>]*>Sonstiges</);assert.match(unknown,/mobile-date/)
const shared=mobilePart(await render(description,{entry:{...detailed,user_id:'a'},paidByName:'Alex <safe>',dateText:'date',enableFixedCosts:false}))
assert.match(shared,/mobile-paid-by/);assert.match(shared,/Alex &lt;safe&gt;/);assert.doesNotMatch(shared,/badge-fixed/)
assert.doesNotMatch(await render(amount,{entry:{type:'income'},amount:25,currency:'EUR'}),/class="amount-metadata"/)
const sharedAmountHtml=await render(amount,{entry:{type:'expense'},amount:50,currency:'EUR',sharedProjectTooltip:'Shared total 100, own share 50'})
assert.doesNotMatch(sharedAmountHtml,/class="amount-metadata"/,'A shared indicator alone does not create a second line')
assert.ok(sharedAmountHtml.indexOf('class="amount-main"') < sharedAmountHtml.indexOf('account-multiple-icon'))
assert.ok(sharedAmountHtml.indexOf('account-multiple-icon') < sharedAmountHtml.indexOf('class="amount-value'))

for (const entry of [{type:'expense',recurrence_interval:'month'}, {type:'expense',recurrence_series_id:7,recurrence_parent_id:1}]) {
 const recurringAmount=await render(amount,{entry,amount:10,currency:'EUR'})
 assert.match(recurringAmount,/class="amount-recurrence"/)
 assert.ok(recurringAmount.indexOf('class="amount-recurrence"') < recurringAmount.indexOf('class="amount-value'))
 assert.doesNotMatch(recurringAmount,/class="amount-metadata"/,'Recurrence alone does not leave an empty metadata lane')
}

const metadataHtml=await render(amount,{entry:{type:'expense',has_history:true,reminder_date:1793232000,attachments_count:1},amount:1000,currency:'CHF',sharedProjectTooltip:'Shared',showSettledIcon:true})
assert.match(metadataHtml,/amount-metadata/);assert.doesNotMatch(metadataHtml,/history-icon/);assert.match(metadataHtml,/attachment-icon/);assert.match(metadataHtml,/CHF/)
assert.doesNotMatch(await render(amount,{entry:{type:'income',attachments_count:1},amount:25,currency:'EUR'},false),/attachment-icon/)
// A desktop assignment is a neutral folder/name with the complete sharing tooltip.
const assignedArea=await render(area,{name:'Haushalt <safe>',color:'#b5489b',statusTooltip:'Bezahlt von Alex\nNoch nicht abgerechnet'})
assert.match(assignedArea,/folder-icon/);assert.match(assignedArea,/#b5489b/);assert.match(assignedArea,/Haushalt &lt;safe&gt;/)
assert.doesNotMatch(assignedArea,/account-multiple|entry-area-settled/);assert.match(assignedArea,/Noch nicht abgerechnet/)
assert.doesNotMatch(assignedArea,/entry-badge|project-chip/)
const noArea=await render(area,{name:'Ohne Bereich',withoutArea:true})
assert.doesNotMatch(noArea,/folder|Ohne Bereich|entry-area/,'Unassigned desktop payments leave the area cell empty')
assert.match(await render(area,{name:'Haushalt',settled:true,statusTooltip:'Abgerechnet'}),/folder-check-icon/)
const columnInputs={availableWidth:900,categoryMinimum:100,partnerMinimum:95,categoryWidth:180,partnerWidth:125}
const shortPartner=fitPaymentColumns(columnInputs)
assert.deepEqual(shortPartner,{category:180,partner:125},'Short contents release their unused width to Description')
const longPartner=fitPaymentColumns({...columnInputs,partnerWidth:2000,categoryWidth:2000})
assert.deepEqual(longPartner,{category:240,partner:220},'Unusually long names cannot consume the whole table')
const emptyColumns=fitPaymentColumns({...columnInputs,categoryWidth:0,partnerWidth:0})
assert.deepEqual(emptyColumns,{category:100,partner:95},'Empty columns still preserve their headings')
for(const availableWidth of [0,80,220,400,600,900]) {
 const widths=fitPaymentColumns({...columnInputs,availableWidth,categoryWidth:300,partnerWidth:300})
 assert.ok(widths.category>=0 && widths.partner>=0)
 assert.ok(widths.category+widths.partner<=availableWidth-Math.min(240,availableWidth*.45),'Narrow containers retain description space without overflowing')
 if(availableWidth===400) assert.ok(widths.category>=100 && widths.partner>=95,'Headings fit when the available space permits')
}
assert.equal(fitPaymentLabels([80,60,40],200,()=>30),3)
assert.equal(fitPaymentLabels([80,60,40],120,()=>30),1,'Reserve room for the overflow chip')
assert.equal(fitPaymentLabels([200],80,()=>30),0,'Long labels stay whole in the overflow tooltip')
assert.equal(fitPaymentLabels([],80,()=>30),0)
const labelHtml=await render(paymentLabels,{labels:[
 {key:'important',icon:'important',tone:'warning',text:'Wichtig'},
 {key:'review',icon:'review',tone:'error',text:'Prüfen'},
 {key:'tax',icon:'taxRelevant',tone:'tax',text:'Steuerrelevant'},
 {key:'fixed',icon:'fixedCost',tone:'neutral',text:'Fixkosten'},
 {key:'child',icon:'childRelated',tone:'neutral',text:'Kinder'},
 {key:'abo',icon:'subscription',tone:'neutral',text:'Abo'},
 {key:'insurance',icon:'insurance',tone:'neutral',text:'Versicherung'},
]})
for(const glyph of ['star-outline-icon','clipboard-check-outline-icon','receipt-text-check-outline-icon','bank-outline-icon','account-child-outline-icon','sync-icon','shield-check-outline-icon'])assert.ok(labelHtml.includes(glyph))
for(const label of ['Wichtig','Prüfen','Steuerrelevant','Fixkosten','Kinder','Abo','Versicherung'])assert.ok(labelHtml.includes(`aria-label="${label}"`))
assert.equal((labelHtml.match(/data-cobudget-badge="neutral"/g)||[]).length,4)
assert.doesNotMatch(labelHtml,/>Fixkosten<|>Kinder<|>Abo<|>Versicherung</,'All metadata chips use icons with accessible names')
assert.doesNotMatch(labelHtml,/>Wichtig<|>Prüfen<|>Steuerrelevant</,'Status chips expose names through their tooltip, without visible text')
const collapsedLabels=await render(paymentLabels,{labels:[{key:'fixed',text:'Fixkosten'},{key:'child',text:'Kinder'}],collapse:true})
assert.match(collapsedLabels,/>\+2</);assert.match(collapsedLabels,/aria-label="Fixkosten[^"]*Kinder/)
const allFlags={...detailed,is_important:true,needs_review:true,is_tax_relevant:true,is_child_related:true,is_subscription:true,is_insurance:true}
const allFlagHtml=mobilePart(await render(description,{entry:allFlags,dateText:'date'}))
assert.match(allFlagHtml,/star-outline-icon/,'Important retains first-label priority')
for(const glyph of ['clipboard-check-outline-icon','receipt-text-check-outline-icon','bank-outline-icon','account-child-outline-icon','sync-icon','shield-check-outline-icon'])assert.ok(!allFlagHtml.includes(glyph))
assert.equal((allFlagHtml.match(/data-payment-label/g)||[]).length,1,'One mobile label stays visible regardless of the text length')
assert.match(allFlagHtml,/class="payment-label-count"[^>]*>7</,'The small overlay counts all active labels')
assert.match(allFlagHtml,/aria-label="important[^"]*review[^"]*taxRelevant[^"]*fixedCosts[^"]*children[^"]*subscription[^"]*insurance/,'The shared tooltip retains all label names')
assert.match(allFlagHtml,/data-report-export-label="important, review, taxRelevant, fixedCosts, children, subscription, insurance"/)
assert.doesNotMatch(detailedHtml,/payment-label-count/,'One label does not need a count overlay')
const metadataOnly=mobilePart(await render(description,{entry:{type:'expense',is_fixed_cost:true,is_child_related:true,is_subscription:true,is_insurance:true},dateText:'date'}))
assert.match(metadataOnly,/bank-outline-icon/);assert.match(metadataOnly,/class="payment-label-count"[^>]*>4</)
assert.doesNotMatch(metadataOnly,/account-child-outline-icon|sync-icon|shield-check-outline-icon|mobile-area-badge/)
const settledMobile=mobilePart(await render(description,{entry:{type:'expense'},dateText:'date',showMobileDate:false,mobileArea:{name:'Area',settled:true,statusTooltip:'Settled'}}))
assert.doesNotMatch(settledMobile,/folder-icon|folder-check-icon|mobile-area-badge/);assert.doesNotMatch(settledMobile,/mobile-badges/)

assert.match(settledMobile,/aria-label="Area[^"]*Settled/)
assert.match(settledMobile,/data-report-export-label="Area"/)


const flagsOff=mobilePart(await render(description,{entry:allFlags,dateText:'date',enableImportantPayments:false,enableReviewPayments:false,enableTaxRelevant:false,enableFixedCosts:false,enableChildRelated:false,enableSubscriptions:false,enableInsurance:false}))
assert.doesNotMatch(flagsOff,/payment-label-list|badge-/,'Feature switches remove labels and status icons together')
console.log('Payment label chips passed: shared navigation glyphs, localized status tooltips, neutral metadata, complete overflow names and feature gates.')
console.log('Adaptive payment columns passed: content-sized secondary columns, heading minimums, outlier caps and narrow-container description space.')
console.log('Mobile payment hierarchy passed: fixed category/type title and icon, optional partner/description/labels/area/payer, safe tags, dates and retained financial metadata.')

// Shared table integration and optional visual fixture use synthetic payments only.
{
 const actions={render:()=>Vue.h('button',{class:'fixture-actions','aria-label':'Aktionen'},'⋯')}
 const noop={render:()=>null}
 const table=component('src/components/EntryTable.vue',{
  NcButton:{render(){return Vue.h('button',this.$attrs,[this.$slots.icon?.(),this.$slots.default?.()])}},NcActionButton:noop,NcActions:actions,HistoryIcon:icon('History'),PencilIcon:icon('Pencil'),PlusIcon:icon('Plus'),DeleteOutlineIcon:icon('DeleteOutline'),FolderIcon:icon('Folder'),MenuUpIcon:icon('MenuUp'),MenuDownIcon:icon('MenuDown'),MemberIdentity:identity,CategoryIcon:category,EntryAmountCell:amount,EntryDescriptionCell:description,TableTooltip:tooltip,texts,fitPaymentColumns,isAreaSettled,getAppLocale:()=> 'de-AT',getAppLanguage:()=> 'de',formatMoney,formatSignedMoney:(value,currency)=>formatMoney(value,currency,{signDisplay:'always'}),formatCalendarLabel:(date,options)=>new Intl.DateTimeFormat('de',options).format(date),
 })
 assert.equal(table.methods.canEditEntry({is_locked:true,source_entry_id:9,can_delete:false}), true, 'Members can edit the shared source without acquiring deletion rights')
 assert.equal(table.methods.canDeleteEntry({is_locked:true,source_entry_id:9,can_delete:false}), false)
 assert.equal(table.methods.canEditEntry({is_locked:true}), false, 'A locked row without a source is not directly editable')
 // Alignment follows the first-two-line icon band, not the full description or metadata height.
 for(const [band,amountHeight,actionsHeight] of [[40.8,22,44],[24,22,44],[56,30,48],[40.8,22,0]]) {
  const values=new Map(),row={style:{getPropertyValue:n=>values.get(n)||'',setProperty:(n,v)=>values.set(n,v)},querySelector:selector=>selector==='.mobile-entry-icons'?{getBoundingClientRect:()=>({height:band})}:selector==='.amount-main'?{getBoundingClientRect:()=>({height:amountHeight})}:actionsHeight?{getBoundingClientRect:()=>({height:actionsHeight})}:null}
  table.methods.updateMobileRowAlignment.call({$refs:{scrollContainer:{querySelectorAll:()=>[row]}}})
  assert.equal(Number.parseFloat(values.get('--payment-mobile-amount-offset'))+amountHeight/2,band/2)
  assert.equal(Number.parseFloat(values.get('--payment-mobile-actions-offset')),actionsHeight?band/2-actionsHeight/2:0)
 }
 const accentContext={$enableProjects:true,projectColorResolver:()=> '#b5489b',enableImportantPayments:true,enableReviewPayments:true,enableTaxRelevant:true,enableFixedCosts:true,enableChildRelated:true,enableSubscriptions:true,enableInsurance:true}
 const accent=(entry,overrides={})=>table.methods.entryRowAccent.call({...accentContext,...overrides},entry)
 assert.equal(accent({project_id:1,is_important:true}),'#b5489b','Area color takes priority over labels')
 assert.equal(accent({project_id:1},{projectColorResolver:()=> ''}),'var(--cobudget-primary)','Default areas use Nextcloud blue')
 assert.equal(accent({}),'transparent','Unassigned payments continue the row background')
 for(const [flag,,feature] of [
  ['is_important','--cobudget-warning','enableImportantPayments'],['needs_review','--cobudget-error','enableReviewPayments'],['is_tax_relevant','--cobudget-tax','enableTaxRelevant'],['is_fixed_cost','--cobudget-label-fixed-cost-color','enableFixedCosts'],['is_child_related','--cobudget-label-children-color','enableChildRelated'],['is_subscription','--cobudget-label-subscription-color','enableSubscriptions'],['is_insurance','--cobudget-label-insurance-color','enableInsurance'],
 ]) {
  assert.equal(accent({[flag]:true}),'transparent','Labels do not add an area marker')
  assert.equal(accent({[flag]:true},{[feature]:false}),accent({}),'Disabled labels do not color the row border')
 }
 assert.equal(accent({is_important:true,needs_review:true}),'transparent','Unassigned rows remain unmarked with multiple labels')
 assert.equal(accent({project_id:1,is_fixed_cost:true},{$enableProjects:false}),'transparent')
 const date=(month,day)=>new Date(2026,month-1,day,12).getTime()/1000
 const entries=[
  {...detailed,date:date(10,26),amount:100,has_history:true,recurrence_interval:'month'},
  {id:2,type:'expense',date:date(10,26),amount:35},
  {id:3,type:'income',date:date(10,25),amount:2500,paymentPartner:'Arbeitgeber',description:'Gehalt #Oktober'},
  {id:4,type:'expense',date:date(10,25),amount:1234567.89,needs_review:true,is_tax_relevant:true,is_insurance:true,category_name:'Haushalt und Einrichtung mit einem langen Kategorienamen',category_icon:'Home',paymentPartner:'Ein sehr langer Empfängername',description:'Eine längere Beschreibung mit #Tags und weiteren Informationen zur Zahlung.',is_important:true,is_child_related:true,is_subscription:true,is_fixed_cost:true},
  {id:5,type:'expense',date:date(10,24),amount:15,category_name:'Gesundheit',category_icon:'missing',has_history:true,reminder_date:date(10,25),attachments_count:2},
  {id:6,type:'expense',date:date(10,23),amount:25,description:'Beschreibung',is_fixed_cost:true},
  {id:7,type:'expense',date:date(10,23),amount:56,description:'Beschreibung'},
 ]
 const areaProps={projectNameResolver:()=> 'Haushalt',projectColorResolver:()=> '#b5489b',isSharedProjectResolver:()=> true}
 const desktopTable=(desktop=true)=>({...table,data:()=>({...table.data(),desktopLayout:desktop})})
 entries[0].project_id=1;entries[0].paid_by_display_name='Alex'

 const sharedEntry={...entries[0],amount:50.01,personal_amount:50.01,is_locked:true,source_entry_id:99,source_amount_cents:10001,project_name:'Haushalt & Freizeit'}
 for (const desktop of [true,false]) {
  const sharedHtml=await render(desktopTable(desktop),{entries:[sharedEntry],currency:'EUR',...areaProps})
  assert.match(sharedHtml,/account-multiple-icon/,'Open shares have a visible group icon on desktop and mobile')
  assert.match(sharedHtml,/sharedPayment[\s\S]*paymentArea: Haushalt &amp; Freizeit[\s\S]*totalAmount: [^\n]*100,01[\s\S]*yourShare: [^\n]*50,01[\s\S]*amountNotSettled/,'Tooltip distinguishes exact total, share, area and open status')
  const releasedHtml=await render(desktopTable(desktop),{entries:[{...sharedEntry,is_locked:false,source_entry_id:null,source_amount_cents:null,settlement_id:42}],currency:'EUR',...areaProps})
  assert.doesNotMatch(releasedHtml,/sharedPayment|totalAmount: [^\n]*100,01|account-multiple-icon/,'Released copies do not retain an open-share marker')
  const missingTotal=await render(desktopTable(desktop),{entries:[{...sharedEntry,source_amount_cents:null}],currency:'EUR',...areaProps})
  assert.doesNotMatch(missingTotal,/totalAmount:/,'Never present the personal share as a missing source total')
 }
 const desktopHtml=await render(desktopTable(),{entries,currency:'EUR',...areaProps})
 assert.doesNotMatch(desktopHtml,/class="col-area"|class="area-cell"|class="entry-area"|project-chip|mobile-project-badge|folder-outline-icon|personalAssignment/)
 assert.match(desktopHtml,/colspan="7"/);assert.match(desktopHtml,/colspan="4" class="date-group-label"/)
 const firstRow=desktopHtml.split('class="entry-row')[1]
 assert.match(firstRow,/--payment-row-accent:#b5489b/)
 const descriptionCell=firstRow.split('class="desc-cell"')[1].split('class="category-cell"')[0]
 assert.doesNotMatch(descriptionCell,/entry-area|Haushalt/,'Area assignment does not consume Description space')
 const dateCell=firstRow.split('class="date-cell"')[1].split('class="desc-cell"')[0]
 assert.match(dateCell,/entry-area-marker/)
 assert.match(dateCell,/aria-label="Haushalt[^"]*Bezahlt von Alex/)
 assert.doesNotMatch(dateCell,/>Haushalt<|folder-icon|folder-check-icon/,'The area name is in the marker tooltip, without a visible folder or name')
 assert.match(firstRow,/<td[^>]*data-report-export-label="26.10.2026"[^>]*class="date-cell"/,'Displayed-table export keeps the Date cell free of tooltip text')
 const secondRow=desktopHtml.split('class="entry-row')[2]
 assert.doesNotMatch(secondRow.split('class="date-cell"')[1].split('class="desc-cell"')[0],/entry-area-marker/,'Unassigned rows do not expose an area tooltip')
 assert.doesNotMatch(desktopHtml,/account-multiple/)
 assert.doesNotMatch(firstRow,/history-icon/,'Payment history does not occupy the amount column')
 const desktopSettled=await render(desktopTable(),{entries:[{...entries[0],settlement_id:42}],currency:'EUR',...areaProps})
 assert.match(desktopSettled,/entry-area-marker/);assert.match(desktopSettled,/amountAlreadySettled/)
 const desktopDisabled=await render(desktopTable(),{entries,currency:'EUR',...areaProps},true,false)
 assert.doesNotMatch(desktopDisabled,/entry-area-marker/,'Disabled areas do not retain desktop area targets')
 const mobileHtml=await render(desktopTable(false),{entries,currency:'EUR',...areaProps})
 assert.doesNotMatch(mobileHtml,/inline-entry-area|class="col-area"/);assert.match(mobileHtml,/mobile-area-details/);assert.doesNotMatch(mobileHtml,/mobile-project-badge|account-multiple/)
 assert.doesNotMatch(mobileHtml,/fixture-actions|class="actions-cell"|date-group-actions/,'Editable mobile rows and totals reserve no action column')
 const readonlyHtml=await render(desktopTable(false),{entries:[{...entries[0],is_settled:true,has_history:true}],currency:'EUR',...areaProps})
 assert.match(readonlyHtml,/mobile-history-action/,'Read-only payments retain direct history access')
 assert.doesNotMatch(readonlyHtml,/fixture-actions|class="actions-cell"/,'Read-only history needs no menu column')
 const mobileFirst=mobileHtml.split('class="entry-row')[1]
 assert.match(mobileFirst,/--payment-row-accent:#b5489b/);assert.doesNotMatch(mobileFirst,/folder-icon|folder-check-icon|mobile-area-badge/);assert.match(mobileFirst,/aria-label="Haushalt[^"]*Bezahlt von Alex/);assert.match(mobileFirst,/recurring-icon/)
 assert.match(mobileFirst.split('class="desc-cell"')[1].split('class="amount-cell"')[0],/mobile-area-details/)
 assert.doesNotMatch(mobileFirst.split('class="amount-cell"')[1],/mobile-area-badge/)
 const mobileSettled=await render(desktopTable(false),{entries:[{...entries[0],settlement_id:42}],currency:'EUR',...areaProps})
 assert.match(mobileSettled,/amountAlreadySettled/);assert.doesNotMatch(mobileSettled,/folder-icon|folder-check-icon/);assert.doesNotMatch(mobileSettled,/account-multiple/)
 const disabledAreas=await render(desktopTable(false),{entries,currency:'EUR',...areaProps},true,false)
 assert.doesNotMatch(disabledAreas,/mobile-area-details/,'Disabled area management omits mobile area badges')
 const mobileProject=await render(desktopTable(false),{entries,mode:'project',currency:'EUR',...areaProps})
 assert.doesNotMatch(mobileProject,/mobile-area-details/,'Mobile area detail tables do not repeat their known area')
 const projectHtml=await render(desktopTable(),{entries,mode:'project',currency:'EUR',...areaProps})
 assert.doesNotMatch(projectHtml,/mobile-area-badge|inline-entry-area|class="col-area"/,'Area detail tables do not repeat the known area')
 assert.match(projectHtml,/colspan="7"/);assert.match(projectHtml,/colspan="4" class="date-group-label"/,'Area payer columns keep their closing labels aligned')
 assert.doesNotMatch(projectHtml,/entry-area-marker/)
 const sourcePayer={...entries[0],user_id:'owner',user_display_name:'Owner',paid_by_user_id:'payer',paid_by_display_name:'Actual payer'}
 const payerHtml=await render(desktopTable(),{entries:[sourcePayer],currency:'EUR',...areaProps})
 const payerRow=payerHtml.split('class="entry-row')[1]
 assert.ok(payerRow.indexOf('class="amount-cell"')<payerRow.indexOf('class="user-cell"'),'Payer follows Amount in personal payments')
 assert.ok(payerRow.indexOf('class="user-cell"')<payerRow.indexOf('class="actions-cell"'),'Payer precedes the action menu')
 assert.match(payerRow,/data-user="payer"/,'Personal projections show the original payer avatar, not their owner')
 assert.match(payerRow,/data-report-export-label="Actual payer"/,'Displayed exports retain the payer name')
 const formerHtml=await render(desktopTable(),{entries:[{...sourcePayer,paid_by_user_id:'former:example',paid_by_is_former:true}],currency:'EUR',...areaProps})
 assert.doesNotMatch(formerHtml,/data-user="former:/,'Former payers use safe placeholder avatars')
 const fallbackHtml=await render(desktopTable(),{entries:[{...entries[0],user_id:'owner',user_display_name:'Owner',paid_by_display_name:undefined}],currency:'EUR',...areaProps})
 assert.match(fallbackHtml,/data-user="owner"/,'Ordinary payments fall back to their own payer identity')
 const hiddenPayer=await render(desktopTable(),{entries:[sourcePayer],currency:'EUR',showPaidBy:false,...areaProps})
 assert.doesNotMatch(hiddenPayer,/class="user-cell"/,'Specialized payment lists can omit the payer column')

 console.log('Payment area borders passed: area colors and transparent unassigned edges, removed desktop column, accessible marker details, clean date export and aligned personal/area totals.')
 if(!process.env.COBUDGET_ROW_PREVIEW) process.exit(0)
 const originalLabelData=paymentLabels.data
 paymentLabels.data=()=>({...originalLabelData(),fittingCount:1})
 const layout=process.env.COBUDGET_ROW_LAYOUT
 const html=await render(desktopTable(!!layout),{entries,currency:'EUR',enableImportantPayments:true,...areaProps})
 const base=['src/styles/tokens.css','src/styles/status.css'].map(file=>fs.readFileSync(new URL(`../${file}`,import.meta.url),'utf8')).join('\n')
 fs.writeFileSync(process.env.COBUDGET_ROW_PREVIEW,`<!doctype html><html lang="de" class="cobudget-theme-light"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mobile payment fixture</title><style>:root{--color-main-background:#fff;--color-main-text:#222;--color-text-maxcontrast:#6b6b6b;--color-border:#ededed;--color-border-maxcontrast:#7d7d7d;--color-primary:#00679e;--color-primary-element:#00679e;--default-clickable-area:34px;--default-grid-baseline:4px;--font-weight-element:500}body{margin:0;font-family:system-ui,sans-serif;font-size:15px}*{box-sizing:border-box}svg{display:block}#content{padding:10px;background:var(--cobudget-page-background);min-height:100vh}.fixture-actions{border:0;background:transparent;color:inherit;width:34px;height:34px;font-size:24px}.table-tooltip-trigger{display:inline-flex;align-items:center;min-width:0}${base}\n${styles.join('\n')}</style><body><div id="content" class="app-cobudget">${html}</div><script>const update = function ${table.methods.updateMobileRowAlignment.toString()};const align = () => update.call({$refs:{scrollContainer:document.querySelector('.entry-table-container')}});document.fonts.ready.then(align);window.addEventListener('resize',align);</script></body></html>`)
}
