import assert from 'node:assert/strict'
import fs from 'node:fs'
const source = fs.readFileSync(new URL('../src/components/AddEntryModal.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const imports = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .*$/gm, '').replace('export default', 'return')
const RealDate = Date
class ClockDate extends RealDate {
 constructor(...args) { super(...(args.length ? args : [new RealDate(2026, 10, 4, 8).getTime()])) }
 static now() { return new ClockDate().getTime() }
}
const component = new Function('getAppLocale','categoryParentId','Date',...imports,script)(()=> 'de-AT',()=>null,ClockDate,...imports.map(()=>({})))
const oldZone = process.env.TZ
try {
 for (const zone of ['Europe/Vienna','UTC','America/Los_Angeles','Pacific/Auckland']) {
  process.env.TZ = zone
  const vm = {entry:{recurrenceInterval:'month',recurrenceMultiplier:1,recurrenceEndDate:null},$texts:{entry:{nextRecurrenceAt:(date,time)=>`${date} ${time}`}}}
  vm.fromDateInputValue = component.methods.fromDateInputValue.bind(vm)
  vm.toDateInputValue = component.methods.toDateInputValue.bind(vm)
  for (const [field, hour] of [['dateString', 12], ['reminderDateString', 9], ['recurrenceEndDateString', 23]]) {
   for (const year of ['0002', '0020', '0202', '2026']) {
    const input = `${year}-11-29`
    component.computed[field].set.call(vm, input)
    assert.equal(component.computed[field].get.call(vm), input, `${zone}: ${field} preserves each typed year`)
   }
   const parsed = vm.fromDateInputValue('2026-11-29', hour)
   assert.equal(parsed.getFullYear(), 2026)
   assert.equal(parsed.getHours(), hour)
  }
  assert.equal(vm.toDateInputValue(vm.fromDateInputValue('0004-02-29')), '0004-02-29', 'Early leap years keep their day')
  component.computed.recurrenceEndDateString.set.call(vm, '')
  assert.equal(vm.entry.recurrenceEndDate, null)
  component.computed.reminderDateString.set.call(vm, '')
  assert.equal(vm.entry.reminderDate, null)

  component.computed.dateString.set.call(vm,'2026-12-01')
  assert.equal(vm.entry.date.getDate(),1,`${zone}: date input never shifts to the preceding day`)
  assert.equal(vm.entry.date.getMonth(),11)
  assert.match(component.computed.nextRecurrence.call(vm),/01\.12\.2026.*06:00/,`${zone}: preview starts with the future first payment`)
  const saved = new RealDate(component.methods.paymentDateForSave.call(vm)*1000)
  assert.equal(saved.getDate(),1)
  assert.equal(saved.getHours(),6,`${zone}: future first occurrence is available in the morning`)
  vm.entry.date = new RealDate(2026,9,1,12)
  assert.match(component.computed.nextRecurrence.call(vm),/01\.11\.2026.*06:00/)
  assert.equal(component.methods.paymentDateForSave.call(vm),vm.entry.date.getTime()/1000,'Editing never retimes an already recorded payment')
  vm.entry.recurrenceEndDate = new RealDate(2026,10,1)
  assert.match(component.computed.nextRecurrence.call(vm),/01\.11\.2026/,'Final day is inclusive')
  vm.entry.recurrenceEndDate = new RealDate(2026,9,31)
  assert.equal(component.computed.nextRecurrence.call(vm),null)
  vm.entry.recurrenceInterval='none'
  assert.equal(component.computed.nextRecurrence.call(vm),null)
 }
} finally {
 if(oldZone===undefined) delete process.env.TZ
 else process.env.TZ=oldZone
}
console.log('Recurrence editor passed: future first payment, 06:00, inclusive end date, unchanged recorded dates and local calendar inputs in four timezones.')

// Payment reminder metadata must participate in dirty detection and explicit removal.
const { snapshotEntryForm, hasEntryFormChanges } = await import('data:text/javascript;base64,' + Buffer.from(fs.readFileSync(new URL('../src/utils/entryFormState.js', import.meta.url))).toString('base64'))
const reminderVm = { entry: { hasReminder: false, reminderDate: null, reminderText: '', reminderDetails: { id: null, revision: 0, description: '', labels: [], completed: false, archived: false } }, loading: false, isInitializingEntry: false }
assert.equal(component.computed.invalidPaymentReminder.call(reminderVm), false)
reminderVm.entry.reminderText = 'Cancel subscription'
assert.equal(component.computed.invalidPaymentReminder.call(reminderVm), false, 'Without a date the reminder is disabled')
reminderVm.entry.reminderDate = new Date(2027,0,1,9)
reminderVm.entry.hasReminder = true
assert.equal(component.computed.invalidPaymentReminder.call(reminderVm), false)
const baseline = snapshotEntryForm(reminderVm.entry)
for (const [field,value] of [['description','Private notes'],['labels',['cancel']],['completed',true],['archived',true]]) {
 const saved = reminderVm.entry.reminderDetails[field]
 reminderVm.entry.reminderDetails[field] = value
 assert.equal(hasEntryFormChanges(baseline,reminderVm.entry),true,`${field} is protected by discard confirmation`)
 reminderVm.entry.reminderDetails[field] = saved
}
assert.equal(hasEntryFormChanges(baseline,reminderVm.entry),false)
Object.assign(reminderVm.entry.reminderDetails,{id:42,revision:7,completed:true,archived:true})
reminderVm.fromDateInputValue=component.methods.fromDateInputValue.bind(reminderVm)
component.computed.reminderDateString.set.call(reminderVm,'2027-02-01')
assert.equal(reminderVm.entry.reminderDetails.completed,false,'Rescheduling resets completion')
assert.equal(reminderVm.entry.reminderDetails.archived,false,'Rescheduling resets archival')
component.computed.reminderDateString.set.call(reminderVm,'')
assert.equal(reminderVm.entry.hasReminder,false)
assert.equal(reminderVm.entry.reminderDate,null)
assert.equal(reminderVm.entry.reminderText,'Cancel subscription','Clearing the date retains text locally for re-entry')
assert.equal(reminderVm.entry.reminderDetails.id,42,'Removal retains the revision identity for conflict protection')
assert.equal(reminderVm.entry.reminderDetails.revision,7)
assert.equal(component.computed.invalidPaymentReminder.call(reminderVm),false,'Clearing the date leaves a saveable payment draft')
console.log('Payment reminder drafts passed: required date/title, private metadata dirty guards, rescheduling and removal by clearing the date.')
const reminderApi = {}
const editor = new Function('categoryParentId','generateUrl','extractError',...imports,script)(()=>null,url=>url,(error,fallback)=>error.message||fallback,...imports.map(name=>name==='axios'?reminderApi:{}))
const loaded = {entry:{id:101,reminderText:'Draft'},reminderLoadError:'',$texts:{reminders:{loadError:()=> 'Load failed'}}}
let resolveReminder
reminderApi.get=()=>new Promise(resolve=>{resolveReminder=resolve})
let loadingReminder=editor.methods.loadPaymentReminder.call(loaded)
loaded.entry={id:102,reminderText:'New draft'}
resolveReminder({data:{reminders:[{entry_id:101,id:1,title:'Old request',remind_at:1,labels:[]}]}})
await loadingReminder
assert.equal(loaded.entry.reminderText,'New draft','Late reminder loads cannot overwrite another payment draft')
reminderApi.get=async()=>{throw new Error('Unavailable')}
await editor.methods.loadPaymentReminder.call(loaded)
assert.equal(loaded.reminderLoadError,'Unavailable','Loading failure stays visible and blocks saving')
assert.equal(editor.computed.isSaveDisabled.call({...loaded,isValid:true,isEditing:false}),true)
reminderApi.get=async(url,options)=>{assert.equal(options.params.entryId,102);assert.equal(options.skipWorkspaceHeader,true);return {data:{reminders:[{id:7,entry_id:102,revision:3,title:'Renew',description:'Personal note',remind_at:1800000000,labels:['renew','subscription'],completed_at:123,status:'archived'}]}}}
await editor.methods.loadPaymentReminder.call(loaded)
assert.equal(loaded.reminderLoadError,'')
assert.deepEqual(loaded.entry.reminderDetails,{id:7,revision:3,description:'Personal note',labels:['renew'],completed:true,archived:true},'Loading restores personal fields but never editable payment labels')
reminderApi.get=async()=>({data:{reminders:[]}})
await editor.methods.loadPaymentReminder.call(loaded)
assert.equal(loaded.entry.reminderDetails.id,null,'A removed reminder cannot leave a stale identity after reload')
console.log('Payment reminder loading passed: stale responses, errors, retry, private metadata and removed reminders.')
