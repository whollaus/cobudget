import assert from 'node:assert/strict'
import fs from 'node:fs'

const source = fs.readFileSync(new URL('../src/components/AddEntryModal.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const defaults = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import .*$/gm, '').replace('export default', 'return')
const component = new Function('categoryParentId', ...defaults, script)(() => null, ...defaults.map(() => ({})))
const active = id => ({ id, displayName: id, isActive: true, isFormer: false })
const former = { id: 'former:history', displayName: 'Former member', isActive: false, isFormer: true }
const inactive = { id: 'disabled', displayName: 'Disabled user', isActive: false, isFormer: false }
const vm = {
 entry: { projectId: 4, userId: 'alice', splitMode: 'project_shares', splitUserId: null },
 originalProjectId: 4, isEditing: false, loading: false,
 normalizedProjectMembers: [active('alice'), active('bob:account'), former, inactive],
 $texts: { entry: { projectShares: () => 'Use area split', singleUserSplitTarget: name => `Charge amount to ${name}` } },
 currentUserId: () => 'alice',
}
for (const name of ['isSelectableProjectMember', 'entrySplitModeForSave', 'entrySplitUserIdForSave']) vm[name] = component.methods[name].bind(vm)
for (const name of ['projectPayerOptions', 'projectSplitOptions', 'projectPayerChoice', 'projectSplitChoices', 'projectSplitChoice', 'splitModeChoice']) {
 const definition = component.computed[name]
 Object.defineProperty(vm, name, typeof definition === 'function' ? { get: definition.bind(vm) } : { get: definition.get.bind(vm), set: definition.set.bind(vm) })
}
assert.deepEqual(vm.projectPayerOptions.map(member => member.id), ['alice', 'bob:account'])
assert.equal(vm.projectPayerChoice.id, 'alice')
vm.projectPayerChoice = vm.projectPayerOptions[1]
assert.equal(vm.entry.userId, 'bob:account')
assert.equal(vm.entrySplitModeForSave(), 'project_shares')
assert.equal(vm.entrySplitUserIdForSave(), null)
vm.projectSplitChoice = vm.projectSplitChoices.find(option => option.id === 'bob:account')
assert.equal(vm.entrySplitModeForSave(), 'single_user')
assert.equal(vm.entrySplitUserIdForSave(), 'bob:account', 'Member IDs containing a colon must stay intact')
vm.projectPayerChoice = vm.projectPayerOptions[0]
assert.equal(vm.entrySplitUserIdForSave(), 'bob:account', 'Changing payer must not change a separately selected allocation target')
vm.projectSplitChoice = vm.projectSplitChoices[0]
assert.equal(vm.entry.splitUserId, null)
for (const choice of [null, { id: 'unknown', value: 'single_user:unknown' }, former, inactive]) {
 vm.projectPayerChoice = choice
 vm.projectSplitChoice = choice
}
assert.equal(vm.entry.userId, 'alice')
assert.equal(vm.entry.splitMode, 'project_shares')
// Existing historical selections are shown without allowing a new selection of those members.
vm.isEditing = true
vm.entry.userId = former.id
vm.entry.splitMode = 'single_user'
vm.entry.splitUserId = inactive.id
assert.equal(vm.projectPayerChoice.id, former.id)
assert.equal(vm.projectSplitChoice.id, inactive.id)
assert.equal(vm.isSelectableProjectMember(vm.projectPayerChoice), false)
assert.equal(vm.isSelectableProjectMember(vm.projectSplitChoice), false)
assert.equal(vm.entrySplitUserIdForSave(), inactive.id)
vm.projectPayerChoice = vm.projectPayerOptions.find(member => member.id === 'alice')
vm.projectSplitChoice = vm.projectSplitChoices[0]
vm.projectPayerChoice = former
vm.projectSplitChoice = { value: 'single_user:disabled' }
assert.equal(vm.entry.userId, 'alice')
assert.equal(vm.entry.splitUserId, null)
// A stale choice from another area and selections during submission cannot alter a draft.
const stalePayer = vm.projectPayerOptions[1]
const staleSplit = vm.projectSplitChoices.find(option => option.id === stalePayer.id)
vm.normalizedProjectMembers = [active('alice'), active('charlie')]
vm.projectPayerChoice = stalePayer
vm.projectSplitChoice = staleSplit
assert.equal(vm.entry.userId, 'alice')
assert.equal(vm.entry.splitMode, 'project_shares')
vm.loading = true
vm.projectPayerChoice = vm.projectPayerOptions[1]
vm.projectSplitChoice = vm.projectSplitChoices[2]
assert.equal(vm.entry.userId, 'alice')
assert.equal(vm.entry.splitUserId, null)
console.log('Payment member selection passed: native choice mapping, independent payer/target, exact IDs, area split, historical/disabled members, stale choices and pending guards; no writes.')

// Area-context creation preselects the route area but permits another destination.
const areaVm = {
 entry: { projectId: '4', amountDisplay: '25,00', description: 'Draft' },
 preferAreaDropdown: true, loading: false, isInitializingEntry: false,
 activeProjects: [{ id: 4, name: 'House' }, { id: 7, name: 'Apartment' }],
 $texts: { entry: { personalAssignment: () => 'My payments' } },
}
for (const name of ['isAreaSelected', 'selectArea']) areaVm[name] = component.methods[name].bind(areaVm)
for (const name of ['areaSelectionOptions', 'useDirectAreaSelection', 'areaSelectionChoice']) {
 const definition = component.computed[name]
 Object.defineProperty(areaVm, name, typeof definition === 'function' ? { get: definition.bind(areaVm) } : { get: definition.get.bind(areaVm), set: definition.set.bind(areaVm) })
}
assert.equal(areaVm.areaSelectionChoice.name, 'House')
assert.equal(areaVm.useDirectAreaSelection, false)
areaVm.areaSelectionChoice = areaVm.areaSelectionOptions.find(option => option.id === 7)
assert.equal(areaVm.entry.projectId, 7)
areaVm.areaSelectionChoice = areaVm.areaSelectionOptions[0]
assert.equal(areaVm.entry.projectId, null)
assert.equal(areaVm.entry.amountDisplay, '25,00')
assert.equal(areaVm.entry.description, 'Draft')
for (const blocked of ['loading', 'isInitializingEntry']) {
 areaVm[blocked] = true
 areaVm.areaSelectionChoice = areaVm.areaSelectionOptions[1]
 assert.equal(areaVm.entry.projectId, null)
 areaVm[blocked] = false
}
areaVm.areaSelectionChoice = { key: 'area-99', id: 99 }
assert.equal(areaVm.entry.projectId, null)
areaVm.preferAreaDropdown = false
assert.equal(areaVm.useDirectAreaSelection, true, 'Personal short lists retain direct area choices')
console.log('Area selection passed: current area, alternate/personal destinations, retained draft fields and invalid/pending choice guards.')

// Planning selectors must keep the existing API's string and boolean values.
const planning = {
 entry: { recurrenceInterval: 'month', recurrenceMultiplier: 1, hasReminder: false },
 loading: false, isInitializingEntry: false,
 $texts: { entry: Object.fromEntries(['neverOnce', 'days', 'weeks', 'months', 'noReminder', 'remindMeAt'].map(key => [key, () => key])) },
}
for (const name of ['recurrenceIntervalOptions', 'recurrenceIntervalChoice', 'recurrenceMultiplierValue']) {
 const definition = component.computed[name]
 Object.defineProperty(planning, name, typeof definition === 'function' ? { get: definition.bind(planning) } : { get: definition.get.bind(planning), set: definition.set.bind(planning) })
}
assert.equal(planning.recurrenceIntervalChoice.value, 'month', 'Editing restores the existing recurrence selection')
for (const value of ['day', 'week', 'month']) {
 planning.recurrenceIntervalChoice = planning.recurrenceIntervalOptions.find(option => option.value === value)
 assert.equal(planning.entry.recurrenceInterval, value)
}
planning.entry.recurrenceEndDate = new Date(2027,0,1)
planning.showRecurrenceDetails = false
planning.revealPlanningDetails = () => {}
const recurrenceBefore = JSON.stringify(planning.entry)
component.methods.toggleRecurrenceDetails.call(planning)
assert.equal(planning.showRecurrenceDetails,true)
component.methods.toggleRecurrenceDetails.call(planning)
assert.equal(planning.showRecurrenceDetails,false)
assert.equal(JSON.stringify(planning.entry),recurrenceBefore,'Disclosure never changes the recurrence interval, multiplier or end date')
planning.recurrenceIntervalChoice = planning.recurrenceIntervalOptions.find(option=>option.value==='none')
assert.equal(planning.entry.recurrenceInterval,'none','Once explicitly disables recurrence')
component.methods.toggleRecurrenceDetails.call(planning)
assert.equal(planning.entry.recurrenceInterval,'none','Expanding alone does not create a recurrence')
component.methods.toggleRecurrenceDetails.call(planning)
assert.equal(planning.entry.recurrenceEndDate.getFullYear(),2027,'Disabling retains the end-date draft')
planning.showReminderDetails = false
planning.revealPlanningDetails = () => {}
planning.entry.hasReminder = true
planning.entry.reminderText = 'Call supplier'
planning.entry.reminderDate = new Date(2027, 0, 1, 9)
const reminderBefore = JSON.stringify(planning.entry)
component.methods.toggleReminderDetails.call(planning)
assert.equal(planning.showReminderDetails, true)
component.methods.toggleReminderDetails.call(planning)
assert.equal(planning.showReminderDetails, false)
assert.equal(JSON.stringify(planning.entry), reminderBefore, 'Collapsing preserves all reminder fields')
planning.recurrenceIntervalChoice={value:'year'}
planning.recurrenceIntervalChoice=null
assert.equal(planning.entry.recurrenceInterval,'none')
for (const guard of ['loading','isInitializingEntry']) {
 planning[guard]=true
 component.methods.toggleReminderDetails.call(planning)
 component.methods.toggleRecurrenceDetails.call(planning)
 planning.recurrenceIntervalChoice=planning.recurrenceIntervalOptions[1]
 assert.equal(planning.showReminderDetails,false)
 assert.equal(planning.showRecurrenceDetails,false)
 assert.equal(planning.entry.recurrenceInterval,'none')
 planning[guard]=false
}
planning.recurrenceMultiplierValue = '3'
assert.equal(planning.entry.recurrenceMultiplier, 3)
planning.recurrenceMultiplierValue = ''
assert.equal(planning.entry.recurrenceMultiplier, '', 'Clearing the required multiplier does not store NaN')
planning.recurrenceMultiplierValue = '2'
assert.equal(planning.entry.recurrenceMultiplier, 2)

// An unavailable area list must not be mistaken for a successfully empty list.
const noAreaDraft = {isEditing:false,projectsError:'',activeProjects:[],entry:{projectId:4,userId:'bob',splitMode:'single_user',splitUserId:'bob'},currentUserId:()=> 'alice'}
component.methods.normalizeNewEntryArea.call(noAreaDraft)
assert.deepEqual(noAreaDraft.entry,{projectId:null,userId:'alice',splitMode:'project_shares',splitUserId:null})
for (const overrides of [{isEditing:true},{projectsError:'Unavailable'},{activeProjects:[{id:4}]}]) {
 const state={...noAreaDraft,...overrides,entry:{projectId:4,userId:'bob',splitMode:'single_user',splitUserId:'bob'}}
 component.methods.normalizeNewEntryArea.call(state)
 assert.equal(state.entry.projectId,4,'Existing assignments and failed/available area loads must not be cleared')
}
console.log('New payment area defaults passed: empty active lists use personal assignment; edits, errors and available areas retain their selection.')

// Settlement makes each personal copy independent; its original area is provenance.
const released = {
 isEditing: true, internalEditingEntry: { entry_kind: 'personal', user_id: 'alice', project_id: 4, project_name: 'Archived house', settlement_id: 12, settled_at: 42, is_locked: false },
 entry: { projectId: 4, userId: 'bob', splitMode: 'project_shares', splitUserId: 'bob' },
 projects: [{ id: 4, name: 'Archived house', is_archived: true }], activeProjects: [],
 normalizedProjectMembers: [active('alice'), active('bob')], originalProjectId: 4,
 $enableSharedProjects: true, loading: false, isInitializingEntry: false,
 $texts: vm.$texts, currentUserId: () => 'alice',
}
for (const name of ['isReleasedPersonal', 'isAreaAssignmentLocked', 'areaSelectionOptions', 'projectPayerOptions', 'projectSplitOptions', 'showProjectPayerSelect', 'showProjectSplitMode', 'projectPayerChoice', 'projectSplitChoice', 'projectSplitChoices', 'splitModeChoice']) {
 const definition = component.computed[name]
 Object.defineProperty(released, name, typeof definition === 'function' ? { get: definition.bind(released) } : { get: definition.get.bind(released), set: definition.set.bind(released) })
}
for (const name of ['selectArea', 'isAreaSelected', 'isSelectableProjectMember', 'syncEntryUserWithProject', 'entryUserIdForSave', 'entrySplitModeForSave', 'entrySplitUserIdForSave']) released[name] = component.methods[name].bind(released)
assert.equal(released.isReleasedPersonal, true)
assert.equal(released.isAreaAssignmentLocked, true)
assert.deepEqual(released.areaSelectionOptions.map(p => p.id), [4], 'Archived original area remains visible; no other destination is offered')
assert.equal(released.showProjectPayerSelect, false)
assert.equal(released.showProjectSplitMode, false)
released.selectArea(null)
released.selectArea(7)
assert.equal(released.entry.projectId, 4, 'Events cannot move a released payment')
released.syncEntryUserWithProject('bob')
released.projectPayerChoice = active('bob')
released.projectSplitChoice = { ...active('bob'), value: 'single_user:bob' }
assert.equal(released.entry.userId, 'alice')
assert.equal(released.entryUserIdForSave(), 'alice')
assert.equal(released.entrySplitModeForSave(), 'single_user')
assert.equal(released.entrySplitUserIdForSave(), 'alice')
released.$enableSharedProjects = false
released.syncEntryUserWithProject()
assert.equal(released.entry.splitUserId, 'alice', 'Disabling shared features does not reset a released share')
released.internalEditingEntry = { entry_kind: 'shared', project_id: 4 }
assert.equal(released.isReleasedPersonal, false)
assert.equal(released.isAreaAssignmentLocked, true, 'An open shared source also retains its area')
released.$enableSharedProjects = true
assert.equal(released.showProjectPayerSelect, true, 'Open shared payments still allow payer changes')
assert.equal(released.showProjectSplitMode, true, 'Open shared payments still allow allocation changes')
released.isEditing = false
assert.equal(released.isAreaAssignmentLocked, false, 'New/duplicated payments can choose a new destination')
console.log('Settlement editing passed: original area, archived provenance, personal ownership, hidden reassignment controls and new/shared behavior.')
