import assert from 'node:assert/strict'
import fs from 'node:fs'
const source = fs.readFileSync(new URL('../src/components/AddEntryModal.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const imports = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .*$/gm, '').replace('export default', 'return')
const component = new Function('categoryParentId', ...imports, script)(() => null, ...imports.map(() => ({})))
const events = [], ticks = []
let focusReturns = 0, historyReleases = 0
const vm = {
 isOpen: true, pendingInitialFocus: false, internalEditingEntry: { id: 42 },
 isDuplicateMode: false, showAttachmentList: true, mobileFormFieldFocused: true,
 entry: { id: 42, description: 'Keep this visible during leave' },
 attachments: [{ id: 7 }], pendingAttachments: [{ name: 'draft.pdf' }],
 entryFormBaseline: 'baseline',
 restoreSidebarFocus: () => { focusReturns++ },
 releaseModalHistory: () => { historyReleases++ },
 closeTypeMenu() {}, closeLookup() {}, clearMobileFocusTimer() {}, emitSidebarMode() {},
 resetAttachments() { this.attachments = []; this.pendingAttachments = [] },
 $emit: (...args) => events.push(args), $nextTick: callback => ticks.push(callback),
}
for (const name of ['closeSidebar', 'handleSidebarClosed', 'requestCloseSidebar']) vm[name] = component.methods[name].bind(vm)
vm.confirmDiscardChanges = async () => false
assert.equal(await vm.requestCloseSidebar(), false)
assert.equal(vm.isOpen, true, 'Rejected discard does not start a slide or reset input')
vm.confirmDiscardChanges = async () => true
await vm.requestCloseSidebar()
assert.equal(vm.isOpen, false)
assert.equal(historyReleases, 1, 'Browser history is released immediately, not after an animation timer')
assert.equal(events.length, 1, 'The owner immediately receives one close action')
assert.equal(vm.internalEditingEntry.id, 42, 'Editing heading remains stable during leave')
assert.equal(vm.showAttachmentList, true)
assert.equal(vm.attachments.length, 1)
assert.equal(vm.pendingAttachments.length, 1)
assert.equal(vm.entry.description, 'Keep this visible during leave')
vm.handleSidebarClosed()
assert.equal(vm.internalEditingEntry, null)
assert.equal(vm.showAttachmentList, false)
assert.deepEqual(vm.attachments, [])
assert.deepEqual(vm.pendingAttachments, [])
assert.equal(focusReturns, 0, 'Wait for the native trap to finish restoring focus')
ticks.shift()(); assert.equal(focusReturns, 1)
assert.equal(events.length, 1, 'Native after-leave must not emit a second business close')

vm.isOpen = true; vm.internalEditingEntry = { id: 99 }; vm.showAttachmentList = true
vm.handleSidebarClosed()
assert.equal(vm.internalEditingEntry.id, 99, 'A cancelled old leave cannot reset a reopened editor')
assert.equal(vm.showAttachmentList, true)
vm.restoreSidebarFocus = () => { focusReturns++ }
vm.closeSidebar({ skipHistory: true }); vm.handleSidebarClosed()
vm.isOpen = true; ticks.shift()()
assert.equal(focusReturns, 1, 'A late return-focus callback cannot steal focus from a reopened sidebar')
console.log('Sidebar lifecycle passed: stable closing content, native after-leave cleanup, discard guards, history, focus and rapid reopen.')

let focused = 0
vm.focusInitialField = () => { focused++ }
vm.focusSidebarWhenReady = component.methods.focusSidebarWhenReady.bind(vm)
vm.handleSidebarOpened = component.methods.handleSidebarOpened.bind(vm)
vm.pendingInitialFocus = true; vm.sidebarHasEntered = false; vm.isInitializingEntry = true
vm.handleSidebarOpened()
assert.equal(focused, 0, 'Native slide finishing must not try to focus a still-disabled loading input')
vm.isInitializingEntry = false; vm.focusSidebarWhenReady()
assert.equal(focused, 1, 'Focus once both data initialization and the native slide are complete')
vm.focusSidebarWhenReady(); assert.equal(focused, 1)
vm.pendingInitialFocus = true; vm.sidebarHasEntered = false
vm.focusSidebarWhenReady(); assert.equal(focused, 1, 'Fast data reads cannot focus before native entry')
vm.handleSidebarOpened(); assert.equal(focused, 2)
vm.isOpen = false; vm.pendingInitialFocus = true
vm.handleSidebarOpened(); assert.equal(focused, 2, 'Cancelled entry cannot move focus back into a closed sidebar')
console.log('Sidebar autofocus passed: slow/fast data, native entry completion and cancelled opens.')
// The real focus callback works for creation/editing and desktop/mobile alike.
for (const editing of [false,true]) for (const mobile of [false,true]) {
 const calls=[],queued=[],body={scrollTop:500}
 const amount={disabled:false,readOnly:false,value:editing?'123,45':'',focus:options=>calls.push(['focus',options]),select:()=>calls.push(['select'])}
 const focusVm={isOpen:true,sidebarHasEntered:true,isInitializingEntry:false,loading:false,confirmDialog:null,isEditing:editing,isMobileViewport:()=>mobile,$refs:{amountInput:amount,modalBody:body},$nextTick:callback=>queued.push(callback)}
 component.methods.focusInitialField.call(focusVm)
 assert.deepEqual(calls,[],'Focus waits for the next rendered frame')
 queued.shift()()
 assert.deepEqual(calls,[['focus',{preventScroll:true}],['select']])
 assert.equal(amount.value,editing?'123,45':'','Focusing/selecting must not modify the draft amount')
 assert.equal(body.scrollTop,0,'Reused forms reveal the amount without scrolling the app shell')
 for (const blocked of [{isOpen:false},{sidebarHasEntered:false},{isInitializingEntry:true},{loading:true},{confirmDialog:{}}]) {
  const count=calls.length
  component.methods.focusInitialField.call(focusVm)
  Object.assign(focusVm,blocked)
  queued.shift()()
  assert.equal(calls.length,count,'A late focus callback cannot reach a closed, entering, loading or blocked form')
  Object.assign(focusVm,{isOpen:true,sidebarHasEntered:true,isInitializingEntry:false,loading:false,confirmDialog:null})
 }
 for (const field of [null,{...amount,disabled:true},{...amount,readOnly:true}]) {
  const count=calls.length
  focusVm.$refs.amountInput=field
  component.methods.focusInitialField.call(focusVm);queued.shift()()
  assert.equal(calls.length,count,'Missing or unavailable amount fields cannot receive focus')
 }
}
console.log('Amount autofocus passed: create/edit on desktop/mobile, selected values, local scrolling and late-callback guards.')


const titleTexts = new Proxy({}, { get: (_, key) => () => key })
for (const [mode, expected] of [['new', 'create'], ['edit', 'edit'], ['copy', 'copy'], ['plan', 'plan']]) {
 for (const type of ['expense', 'income']) {
  const titleVm = {
   entry: { type }, isEditing: mode === 'edit', isDuplicateMode: mode === 'copy',
   isFutureContext: mode === 'plan', $texts: { entry: titleTexts },
  }
  titleVm.entryTypeTitle = component.methods.entryTypeTitle.bind(titleVm)
  const title = component.computed.modalTitle.call(titleVm)
  const options = component.computed.entryTypeOptions.call(titleVm)
  assert.deepEqual(options.map(option => option.label), [expected + 'Expense', expected + 'Income'], 'Both menu options retain the current create/edit/copy/plan context')
  assert.equal(options.find(option => option.value === type).label, title, 'Selected type and accessible title use the same wording')
  assert.equal(title, expected + (type === 'income' ? 'Income' : 'Expense'), 'Title retains both mode and payment type')
 }
}
for (const [mode, expected] of [['new','newPayment'],['edit','editPayment'],['copy','copyPayment'],['plan','planPayment']]) {
 const narrow = {isWideViewport:false,isEditing:mode==='edit',isDuplicateMode:mode==='copy',isFutureContext:mode==='plan',$texts:{entry:titleTexts,areaDetail:titleTexts},modalTitle:'Desktop type title'}
 assert.equal(component.computed.sidebarTitle.call(narrow),expected,'Narrow header keeps a concise mode title')
 assert.equal(component.computed.sidebarTitle.call({...narrow,isWideViewport:true}),'Desktop type title','Desktop heading retains its type context')
}
const mobileSaveState={isOpen:true,mobileFormFieldFocused:false,confirmDialog:null,isMobileHeaderViewport:true}
assert.equal(component.computed.showMobileHeaderSave.call(mobileSaveState),true,'Mobile header Save is available without field focus')
assert.equal(component.computed.showMobileHeaderSave.call({...mobileSaveState,isOpen:false}),false)
assert.equal(component.computed.showMobileHeaderSave.call({...mobileSaveState,confirmDialog:{}}),false)
assert.equal(component.computed.showMobileHeaderSave.call({...mobileSaveState,isMobileHeaderViewport:false}),false)
const viewportState={...mobileSaveState,isMobileHeaderViewport:false}
component.methods.updateMobileHeaderViewport.call(viewportState,{matches:true})
assert.equal(component.computed.showMobileHeaderSave.call(viewportState),true)
component.methods.updateMobileHeaderViewport.call(viewportState,{matches:false})
assert.equal(component.computed.showMobileHeaderSave.call(viewportState),false)
component.methods.updateMobileHeaderViewport.call(viewportState,{matches:true})
assert.equal(component.computed.showMobileHeaderSave.call(viewportState),true,'Returning to phone width restores header Save without input focus')
const date = new Date(2026, 9, 14, 9)
let nativeBlurs = 0
let amountFocuses = 0
const typeFocusTicks = []
const typeVm = {
 entry: { type: 'expense', date, amountDisplay: '125,50', description: 'Keep this draft', projectId: 7,
  categoryId: 4, categoryName: 'Expense category', paymentPartnerName: 'Expense partner',
  isSubscription: true, isFixedCost: true, isImportant: true },
 categorySelectionSource: 'manual', loading: false, isInitializingEntry: false, isOpen: true,
 $nextTick: callback => typeFocusTicks.push(callback),
 focusAmountInput: () => { amountFocuses++ },
 entryTypeOptions: [{ value: 'expense' }, { value: 'income' }],
 invalidateCategorySuggestion() {}, closeLookup() {},
 $refs: { paymentTypeSelect: { $el: { querySelector: () => ({ blur: () => { nativeBlurs++ } }) } } },
}
for (const name of ['setEntryType', 'selectEntryType', 'closeTypeMenu']) typeVm[name] = component.methods[name].bind(typeVm)
assert.equal(component.computed.entryTypeChoice.call(typeVm).value, 'expense')
for (const option of [null, { value: 'unknown' }]) typeVm.selectEntryType(option)
assert.equal(typeVm.entry.type, 'expense', 'Invalid or cleared native values cannot change the payment')
typeVm.loading = true
typeVm.selectEntryType({ value: 'income' })
assert.equal(typeVm.entry.type, 'expense', 'Pending saves block type changes')
typeVm.loading = false
typeVm.isInitializingEntry = true
typeVm.selectEntryType({ value: 'income' })
assert.equal(typeVm.entry.type, 'expense', 'Initialization blocks type changes')
assert.equal(typeFocusTicks.length, 0, 'Invalid, unchanged and blocked selections cannot queue focus')
typeVm.isInitializingEntry = false
typeVm.selectEntryType({ value: 'income' })
assert.equal(typeVm.entry.type, 'income')
assert.equal(component.computed.entryTypeChoice.call(typeVm).value, 'income')
assert.equal(typeVm.entry.date, date)
assert.equal(typeVm.entry.amountDisplay, '125,50')
assert.equal(typeVm.entry.description, 'Keep this draft')
assert.equal(typeVm.entry.projectId, 7)
assert.equal(typeVm.entry.isImportant, true)
assert.equal(typeVm.entry.categoryId, null)
assert.equal(typeVm.entry.categoryName, '')
assert.equal(typeVm.entry.paymentPartnerName, '')
assert.equal(typeVm.entry.isSubscription, false)
assert.equal(typeVm.entry.isFixedCost, false)
assert.equal(amountFocuses, 0, 'Wait until native option selection has closed the dropdown')
typeFocusTicks.shift()()
assert.equal(amountFocuses, 1, 'An actual type change advances to the amount field')
typeVm.selectEntryType({ value: 'income' })
assert.equal(typeFocusTicks.length, 0, 'Re-selecting the current type does not move focus')
typeVm.selectEntryType({ value: 'expense' })
typeVm.isOpen = false
typeFocusTicks.shift()()
assert.equal(amountFocuses, 1, 'A late callback cannot refocus a closed sidebar')
typeVm.closeTypeMenu()
assert.equal(nativeBlurs, 1, 'Sidebar close dismisses the native teleported dropdown')
console.log('Payment type field passed: mode context, native option validation, pending guards, retained drafts, dependent lookup resets, amount focus and menu cleanup.')

// Render the actual responsive header and first field; native shells are stubbed.
const Vue = await import('vue')
const { parse, compileTemplate } = await import('@vue/compiler-sfc')
const { renderToString } = await import('@vue/server-renderer')
const nativeSelect = {
 props:['inputId','modelValue','options','disabled'],
 setup:(props,{slots})=>()=>Vue.h('div',{class:'native-select'},[
  Vue.h('input',{id:props.inputId,disabled:props.disabled}),slots['selected-option']?.(props.modelValue),
 ]),
}
const nativeIcon = {render:()=>Vue.h('span')}
const typeSource=fs.readFileSync(new URL('../src/components/EntryTypeField.vue',import.meta.url),'utf8')
const typeDescriptor=parse(typeSource).descriptor
const typeScript=typeDescriptor.script.content.replace(/^import .*$/gm,'').replace('export default','return')
const typeField=new Function('NcSelect','MinusIcon','PlusIcon',typeScript)(nativeSelect,nativeIcon,nativeIcon)
const typeTemplate=compileTemplate({source:typeDescriptor.template.content,filename:'EntryTypeField.vue',id:'type-field-test',compilerOptions:{mode:'function'}})
assert.deepEqual(typeTemplate.errors,[])
typeField.render=new Function('Vue',typeTemplate.code)(Vue)
const fullTemplate=parse(source).descriptor.template.content
const layoutTemplate=fullTemplate.slice(0,fullTemplate.indexOf('<div class="entry-required-panel">'))+'</div></form></NcAppSidebar>'
const layoutCompiled=compileTemplate({source:layoutTemplate,filename:'entry-header-layout.vue',id:'entry-layout-test',compilerOptions:{mode:'function'}})
assert.deepEqual(layoutCompiled.errors,[])
const sideShell={setup:(_,{slots})=>()=>Vue.h('aside',[Vue.h('header',slots.info?.()),Vue.h('main',slots.default?.())])}
const headingShell={setup:(_,{slots})=>()=>Vue.h('h2',{id:'entry-sidebar-title'},slots.default?.())}
const buttonShell={setup:(_,{slots})=>()=>Vue.h('button',slots.default?.())}
const layout={
 props:{wide:Boolean,mode:String},
 components:{NcAppSidebar:sideShell,EntrySidebarHeading:headingShell,EntryTypeField:typeField,NcButton:buttonShell,CloseIcon:nativeIcon,ContentSaveIcon:nativeIcon,NcNoteCard:nativeIcon},
 data(){return {isOpen:true,entry:{type:'expense'},isInitializingEntry:false,loading:false,confirmDialog:null,mobileFormFieldFocused:false,projectsError:'',lookupsError:'',membersError:''}},
 computed:{
  isWideViewport(){return this.wide},isMobileHeaderViewport(){return !this.wide},isEditing(){return this.mode==='edit'},isDuplicateMode(){return this.mode==='copy'},isFutureContext(){return this.mode==='plan'},
  sidebarTitle:component.computed.sidebarTitle,modalTitle:component.computed.modalTitle,entryTypeOptions:component.computed.entryTypeOptions,entryTypeChoice:component.computed.entryTypeChoice,
  showMobileHeaderSave:component.computed.showMobileHeaderSave,saveActionLabel:()=> 'Save',isSaveDisabled:()=>true,
 },
 methods:{entryTypeTitle:component.methods.entryTypeTitle,isMobileViewport(){return !this.wide},handleSidebarOpened(){},handleSidebarClosed(){},handleSidebarOpenUpdate(){},requestCloseSidebar(){},saveEntry(){},selectEntryType(){},handleEscape(){},handleModalFocusIn(){},handleModalFocusOut(){}},
 render:new Function('Vue',layoutCompiled.code)(Vue),
}
for(const wide of [false,true]) for(const enabled of [false,true]) for(const mode of ['new','edit','copy','plan']) {
 const app=Vue.createSSRApp(layout,{wide,mode})
 app.config.globalProperties.$enableIncomes=enabled
 app.config.globalProperties.$texts={entry:titleTexts,areaDetail:titleTexts,common:titleTexts}
 const html=await renderToString(app)
 const header=html.slice(html.indexOf('<header>'),html.indexOf('</header>'))
 const body=html.slice(html.indexOf('<main>'))
 assert.equal((html.match(/id="entry-type"/g)||[]).length,enabled?1:0,'Exactly one type input is rendered across breakpoints and feature settings')
 assert.equal(header.includes('id="entry-type"'),enabled&&wide,'Only the wide header contains the type field')
 assert.equal(body.includes('id="entry-type"'),enabled&&!wide,'The narrow type field belongs to the scrolling body')
 assert.equal(header.includes('entry-sidebar-header-save'),!wide,'Mobile Save remains available without input focus')
 if(!wide) assert.match(header,new RegExp({new:'newPayment',edit:'editPayment',copy:'copyPayment',plan:'planPayment'}[mode]),'The narrow title identifies the current mode')
}
console.log('Responsive payment header passed: single type field, header/body placement, all four modes, income feature gate and persistent mobile Save; native shells mocked.')
