import assert from 'node:assert/strict'
import fs from 'node:fs'
const source = fs.readFileSync(new URL('../src/views/BudgetGoalFormView.vue', import.meta.url), 'utf8')
let script = source.split('<script>')[1].split('</script>')[0]
const defaults = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
script = script.replace(/^import [\s\S]*? from ['"][^'"\n]+['"];?\s*$/gm, '').replace('export default', 'return')
const api = {}, writes = [], navigation = []
const component = new Function('generateUrl', 'extractError', 'showToast', 'categoryChoiceLabel', 'sortCategoriesHierarchically', 'sortAreasByName', 'window', 'CustomEvent', ...defaults, script)(url => url, error => error.message, () => {}, c => c.name, c => c, p => p, {dispatchEvent() {}}, class {}, ...defaults.map(name => name === 'axios' ? api : {}))
const strings = new Proxy({}, {get: (_,key) => () => key})
const vm = {...component.data(), id:null, $enableProjects:true, $texts:{budgetGoals:strings, labels:strings, annual:strings}, $parseAmount:value=>Number(String(value).replace(',','.')), $formatInputAmount:value=>String(value), $router:{push:route=>navigation.push(route), replace:route=>navigation.push(route)}}
for(const [name,fn] of Object.entries(component.methods)) vm[name]=fn.bind(vm)
for(const [name,fn] of Object.entries(component.computed)) Object.defineProperty(vm,name,{get:fn.bind(vm)})
vm.$texts.common = strings
assert.equal(vm.deleteActionHint, 'loading')
vm.loading = false
assert.equal(vm.deleteActionHint, '')
vm.saving = true
assert.equal(vm.deleteActionHint, 'saveBusy')
vm.saving = false; vm.savingCriteria = true
assert.equal(vm.deleteActionHint, 'saveBusy')
vm.savingCriteria = false; vm.deleting = true
assert.equal(vm.deleteActionHint, 'deleteBusy')
vm.deleting = false; vm.loading = true
assert.equal(vm.deleteActionHint, 'loading')
vm.loading = false
api.get=async url=>{ if(url.endsWith('/projects'))return {data:[{id:1,name:'Home'}]};throw new Error('Categories unavailable') }
await vm.fetchData()
assert.equal(vm.loadError,'Categories unavailable')
assert.equal(vm.loading,false)
vm.form={name:'Home budget',amount:'20,50',period:'month',mode:'hard'}
api.post=async (...args)=>{writes.push(args);throw new Error('Save refused')}
await vm.blurDetail('name');await vm.selectDetail('period', {value:'month'})
assert.equal(writes.length,0,'Creation fields remain local until explicit submission')
await vm.saveGoalDetails()
assert.equal(writes.length,0,'Incomplete option loading blocks submission')
api.get=async (url,config)=>({data:url.endsWith('/projects')?[{id:1,name:'Home'}]:config?.params?.projectId?[{id:3,name:'Electricity',type:'expense',project_id:1}]:[{id:2,name:'Food',type:'expense'}]})
await vm.fetchData()
assert.equal(vm.loadError,'')
assert.equal(vm.categoryOptions.length,2)
const noAreaOption = vm.projectOptions.find(option => option.value === '0')
assert.equal(noAreaOption.label, 'withoutArea')
vm.newRule.categoryId = '3'
vm.selectRuleProject(noAreaOption)
assert.equal(vm.newRule.projectId, '0')
assert.equal(vm.newRule.categoryId, '', 'Without area clears a category from an assigned area')
assert.equal(vm.categoryOptions.some(option => option.value === '3'), false)
assert.equal(vm.isEmptyRule(vm.newRule), false, 'Without area alone is a complete criterion')
assert.equal(vm.isEmptyRule({ projectId: 0 }), false, 'A numeric zero cannot become a wildcard')
assert.equal(vm.projectLabelForRule({ projectId: '0' }), 'withoutArea')
assert.equal(vm.projectLabelForRule({ projectId: 0 }), 'withoutArea')
await vm.addRule()
assert.equal(writes.length, 0, 'Creation retains the unassigned criterion locally until Save')
assert.deepEqual(vm.criteriaPayload().rules, [{ projectId: 0, categoryId: null, tag: '' }])
const reloadedUnassigned = vm.rulesFromGoal({ criteria: { rules: vm.criteriaPayload().rules } })
assert.equal(reloadedUnassigned[0].projectId, '0', 'Reopening an existing goal restores the exact unassigned choice')
assert.deepEqual(vm.criteriaPayloadFromRules(reloadedUnassigned).rules, [{ projectId: 0, categoryId: null, tag: '' }])
assert.deepEqual(vm.criteriaPayloadFromRules([{ projectId: 0, categoryId: '2', tag: 'important' }]).rules, [{ projectId: 0, categoryId: 2, tag: 'important' }])
vm.rules = []
vm.selectRuleProject({ value: '' })
vm.selectRuleProject({value:'1'})
assert.equal(vm.categoryOptions.length,3)
vm.newRule.categoryId='3';vm.selectRuleProject({value:''})
assert.equal(vm.newRule.categoryId,'','Changing area clears an unavailable category')
vm.newRule.categoryId='2';await vm.addRule()
assert.equal(writes.length,0,'New goal criteria are local until Save')
assert.equal(vm.criteriaPayload().rules[0].categoryId,2)
await vm.saveGoalDetails()
assert.equal(vm.saveError,'Save refused')
assert.equal(vm.form.name,'Home budget')
assert.equal(vm.form.amount,'20,50')
assert.equal(vm.rules.length,1)
assert.equal(writes.at(-1)[1].amount,20.5)
let finish
api.post=(...args)=>{writes.push(args);return new Promise(resolve=>{finish=resolve})}
const saving=vm.saveGoalDetails()
assert.equal(vm.saveError,'')
await vm.saveGoalDetails();await vm.removeRule(0);vm.goBack()
assert.equal(writes.length,2,'No duplicate submission while saving')
assert.equal(vm.rules.length,1)
assert.equal(navigation.length,0,'Back waits for the active write')
const goal={id:9,name:'Home budget',amount_cents:2050,period:'month',mode:'hard',criteria:{rules:[{categoryId:2}]}}
finish({data:goal});await saving
assert.equal(navigation.at(-1).name,'budget-edit')
vm.id=9;vm.form.name='Uncommitted label';vm.newRule.projectId='1'
api.put=async (...args)=>{writes.push(args);throw new Error('Criteria refused')}
await vm.addRule()
assert.equal(vm.criteriaError,'Criteria refused')
assert.equal(vm.rules.length,1,'Failed criterion write rolls back the visible rules')
assert.equal(vm.newRule.projectId,'1','Failed addition preserves the selection for retry')
assert.equal(vm.form.name,'Uncommitted label')
assert.equal(writes.at(-1)[1].name,'Home budget','Criteria autosave does not commit edited identity fields')
api.put=async (...args)=>{writes.push(args);return {data:{...goal,criteria:args[1].criteria}}}
await vm.addRule()
assert.equal(vm.criteriaError,'')
assert.equal(vm.rules.length,2)
assert.equal(vm.newRule.projectId,'')
// Existing goals save each valid field without committing unrelated drafts.
const savedRules=vm.criteriaPayload()
const persisted={...goal,criteria:savedRules}
api.put=async (...args)=>{
 writes.push(args)
 Object.assign(persisted,args[1],{amount_cents:Math.round(args[1].amount*100)})
 return {data:{...persisted}}
}
let count=writes.length
await vm.saveGoalDetails()
assert.equal(writes.length,count,'Editing cannot submit the whole draft through the creation handler')
vm.form.amount='invalid'
await vm.blurDetail('amount')
assert.equal(vm.amountTouched,true)
assert.equal(writes.length,count,'Invalid amounts do not reach the API')
vm.form.name='  Updated budget  '
await vm.blurDetail('name')
assert.equal(vm.form.name,'Updated budget')
assert.equal(writes.at(-1)[1].amount,20.5,'Name autosave uses the committed amount')
assert.equal(vm.form.amount,'invalid','Saving another field preserves an incomplete amount')
assert.deepEqual(writes.at(-1)[1].criteria,savedRules,'Detail autosave preserves criteria')
count=writes.length
await vm.blurDetail('name')
assert.equal(writes.length,count,'Unchanged values do not write')
vm.form.amount='31,75'
await vm.submitDetail('amount')
assert.equal(vm.savedGoalDetails.amount,31.75)
assert.equal(vm.form.amount,'31.75')
await vm.selectDetail('period',{value:'year'})
assert.equal(vm.savedGoalDetails.period,'year')
await vm.selectDetail('mode',{value:'flexible'})
assert.equal(vm.savedGoalDetails.mode,'flexible')
count=writes.length
await vm.selectDetail('period',null);await vm.selectDetail('mode',{value:'unknown'})
assert.equal(writes.length,count,'Invalid native selection events are ignored')
vm.form.name='Failed label'
const successfulPut=api.put
api.put=async (...args)=>{writes.push(args);throw new Error('Name refused')}
await vm.blurDetail('name')
assert.equal(vm.detailSaveErrors.name,'Name refused')
assert.equal(vm.form.name,'Failed label','A failed write retains its draft')
api.put=successfulPut
await vm.selectDetail('period',{value:'month'})
assert.equal(writes.at(-1)[1].name,'Updated budget','A failed name is not committed by another control')
assert.equal(vm.detailSaveErrors.name,'Name refused','An unrelated success retains the field error')
await vm.saveDetailField('name')
assert.equal(vm.savedGoalDetails.name,'Failed label')
assert.equal(vm.detailSaveErrors.name,'','Retry clears the resolved error')
vm.form.name='Pending name'
api.put=(...args)=>{writes.push(args);return new Promise(resolve=>{finish=resolve})}
const detailSaving=vm.submitDetail('name')
count=writes.length
const navigationCount=navigation.length
await vm.blurDetail('name');await vm.selectDetail('mode',{value:'hard'});await vm.removeRule(0)
vm.askDeleteGoal();vm.goBack()
assert.equal(writes.length,count,'Autosave blocks overlapping writes and duplicate blur/Enter')
assert.equal(vm.deleteConfirmOpen,false)
assert.equal(navigation.length,navigationCount)
vm.form.name='Newer draft'
finish({data:{...persisted,name:'Pending name'}});await detailSaving
assert.equal(vm.form.name,'Newer draft','Late responses do not overwrite newer drafts')
assert.equal(vm.savedGoalDetails.name,'Pending name')
api.delete=async (...args)=>{writes.push(args);throw new Error('Delete refused')}
const before=writes.length
await vm.deleteGoal();vm.askDeleteGoal();vm.closeDeleteConfirm();await vm.deleteGoal()
assert.equal(writes.length,before,'Deletion requires the explicit open safety question')
vm.askDeleteGoal();await vm.deleteGoal()
assert.equal(vm.deleteError,'Delete refused')
assert.equal(vm.deleteConfirmOpen,true,'Failed deletion remains visible for retry or cancel')
api.delete=(...args)=>{writes.push(args);return new Promise(resolve=>{finish=resolve})}
const deleting=vm.deleteGoal()
vm.closeDeleteConfirm();await vm.deleteGoal()
assert.equal(vm.deleteConfirmOpen,true)
assert.equal(writes.length,before+2)
finish({data:{}});await deleting
assert.equal(vm.deleteConfirmOpen,false)
assert.equal(navigation.at(-1).name,'budgets')
vm.form.amount='0';assert.equal(vm.canSave,false)
vm.form.amount='-1';assert.equal(vm.canSave,false)
vm.form.amount='invalid';assert.equal(vm.canSave,false)
vm.form.amount='12';vm.form.name=' ';assert.equal(vm.canSave,false)
console.log('Budget form passed: failed loading, native selection mapping, local creation criteria, failed drafts, retry, independent field/criteria saves, field retry, pending guards and deletion confirmation; writes mocked.')
