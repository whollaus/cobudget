import assert from 'node:assert/strict'
import fs from 'node:fs'
const hierarchySource = fs.readFileSync(new URL('../src/utils/categoryHierarchy.js', import.meta.url), 'utf8')
const hierarchy = await import(`data:text/javascript,${encodeURIComponent(hierarchySource)}`)
function component(file) {
 let script = fs.readFileSync(new URL(file, import.meta.url), 'utf8').split('<script>')[1].split('</script>')[0]
 const defaults = [...script.matchAll(/^import (\w+) from .+$/gm)].map(match => match[1])
 script = script.replace(/^import .*$/gm, '').replace('export default', 'return')
 return new Function('categoryParentId', 'sortCategoriesHierarchically', 'positionEntryLookup', ...defaults, script)(hierarchy.categoryParentId, hierarchy.sortCategoriesHierarchically, () => {}, ...defaults.map(() => ({})))
}
const editor = component('../src/components/AddEntryModal.vue')
const lookup = component('../src/components/EntryLookupSelect.vue')
const records = [
 { id: 1, name: 'Household', code: '100', type: 'expense' },
 { id: 2, name: 'Electricity', code: '110', parent_category_id: 1, parent_name: 'Household', type: 'expense' },
 { id: 3, name: 'Electricity', code: '210', parent_category_id: 4, parent_name: 'Business', type: 'expense', recent_usage_count: 3 },
 { id: 4, name: 'Business', type: 'expense' },
 { id: 5, name: 'Salary', type: 'income' },
]
const vm = {
 categories: records, paymentPartners: [{ id: 10, name: 'Utility', number: 'A-9', type: 'expense' }],
 entry: { type: 'expense', categoryId: 3, categoryName: 'Electricity', paymentPartnerName: '' },
 loading: false, isInitializingEntry: false, categorySelectionSource: null, categorySuggestionRequestId: 0,
 lookupDraft: { category: '', paymentPartner: '' },
 $texts: { entry: { frequentlyUsed: () => 'Frequent', allCategories: () => 'All categories', allPaymentPartners: () => 'All partners' } },
}
for (const [name, method] of Object.entries(editor.methods)) vm[name] = method.bind(vm)
for (const name of ['filteredCategories','filteredPaymentPartners','selectedCategory','selectedCategoryId','hasCategorySelection','categoryLookupSelection','paymentPartnerLookupSelection']) {
 Object.defineProperty(vm, name, { get: editor.computed[name].bind(vm) })
}
vm.closeLookup = () => {}
vm.blurLookupTrigger = () => {}
vm.advanceToCategoryLookup = () => {}
assert.equal(vm.categoryLookupSelection.id, 3, 'Same-name child categories preserve the selected ID')
assert.deepEqual(vm.lookupSelectSections('category', '110').flatMap(s => s.items).map(c => c.id), [2])
assert.deepEqual(vm.lookupSelectSections('category', 'Household').flatMap(s => s.items).map(c => c.id), [1,2], 'Parent-name search includes children')
assert.deepEqual(vm.lookupSelectSections('paymentPartner', 'a-9').flatMap(s => s.items).map(c => c.id), [10])
assert.deepEqual(vm.lookupSelectSections('category', '').map(s => s.label), ['Frequent', 'All categories'])
assert.equal(vm.lookupSelectSections('category', '').flatMap(s => s.items).some(c => c.id === 5), false, 'Income categories stay out of expense choices')
vm.selectNativeLookup('category', records[1])
assert.equal(vm.entry.categoryId, 2)
assert.equal(vm.categorySelectionSource, 'manual')
vm.createNativeLookup('category', ' 210 ')
assert.equal(vm.entry.categoryId, 3, 'Exact code reuses an existing category')
vm.createNativeLookup('category', ' New category ')
assert.equal(vm.entry.categoryId, null)
assert.equal(vm.entry.categoryName, 'New category')
assert.equal(vm.categories.length, 5, 'Creating a draft must not add a persistent record')
vm.createNativeLookup('paymentPartner', 'A-9')
assert.equal(vm.entry.paymentPartnerName, 'Utility')
vm.createNativeLookup('paymentPartner', 'New partner')
assert.equal(vm.entry.paymentPartnerName, 'New partner')
vm.loading = true
vm.selectNativeLookup('category', records[1])
assert.equal(vm.entry.categoryName, 'New category', 'Busy editor rejects selection updates')
vm.loading = false
vm.openLookup = () => {}
vm.$nextTick = () => {}
vm.selectNativeLookup('category', null)
assert.equal(vm.entry.categoryId, null)
assert.equal(vm.entry.categoryName, '')
const events = []
const field = {
 ...lookup.data(), disabled: false, isCategory: true,
 getSections: query => vm.lookupSelectSections('category', query), exactMatch: name => vm.exactLookupMatch('category', name),
 $texts: { entry: { addLookupValue: name => `Add “${name}”`, newCategory: () => 'New category', newPaymentPartner: () => 'New payment partner' } },
 $emit: (...args) => events.push(args), $refs: {},
}
for (const [name, method] of Object.entries(lookup.methods)) field[name] = method.bind(field)
Object.defineProperty(field, 'options', { get: lookup.computed.options.bind(field) })
assert.equal(field.isSelectable(field.options[0]), false, 'Group heading cannot be chosen')
Object.defineProperty(field, 'nestedOptions', { get: lookup.computed.nestedOptions.bind(field) })
{
 const byId = id => field.options.find(o => o.id === id && !o.lookupKind)
 assert.equal(field.isNested(byId(2)), true, 'A subcategory directly below its main category is nested without repeating the parent')
 assert.equal(field.isNested(byId(3)), false, 'A detached frequent subcategory keeps its main category as context')
 assert.equal(field.isNested(byId(1)), false)
}
assert.equal(field.options.at(-1).lookupKind, 'new', 'Empty search exposes a compact creation entry point')
assert.equal(field.options.at(-1).name, 'New category')
let focused = false
field.focus = () => { focused = true }
field.selectOption(field.options.at(-1))
assert.equal(focused, true, 'New returns focus directly to the search')
assert.equal(field.enteringName, true)
assert.equal(events.length, 0, 'Entering a new name does not modify the selection')
assert.equal(field.options.some(o => o.lookupKind === 'new'), false)
field.close()
assert.equal(field.enteringName, false, 'Closing cancels name entry')
events.length = 0
for (const query of ['', '  ', ' electricity ', '110']) {
 field.search = query
 assert.equal(field.options.some(o => o.lookupKind === 'create'), false, 'Blank searches and exact matches never offer creation')
}
field.search = ' New & different '
assert.equal(field.options.some(o => o.lookupKind === 'new'), false, 'Typed queries replace the generic New action')
const createOption = field.options.at(-1)
assert.equal(createOption.lookupKind, 'create')
assert.equal(createOption.name, 'Add “New & different”')
assert.equal(events.length, 0, 'Searching never changes payment data')
field.close()
assert.deepEqual(events, [['close']], 'Dismissing a search never emits a selection or creation')
assert.equal(field.search, '')
field.selectOption(createOption)
assert.deepEqual(events.at(-1), ['create', 'New & different'], 'Selection uses the captured name even after native search cleanup')
field.selectOption({ lookupKind: 'create', lookupName: ' 110 ' })
assert.deepEqual(events.at(-1), ['select', records[1]], 'Recheck existing records when selecting a creation option')
field.selectOption(records[2])
assert.deepEqual(events.at(-1), ['select', records[2]], 'Preserve existing category IDs')
const before = events.length
field.selectOption({ lookupKind: 'create', lookupName: ' ' })
field.selectOption({ lookupKind: 'heading', name: 'All categories' })
assert.equal(events.length, before)
field.disabled = true
field.selectOption({ lookupKind: 'new' })
assert.equal(field.enteringName, false)
field.selectOption(createOption)
field.selectOption(records[1])
assert.equal(events.length, before, 'Busy selectors reject both new and existing selections')
field.disabled = false
field.isCategory = false
field.getSections = query => vm.lookupSelectSections('paymentPartner', query)
field.exactMatch = name => vm.exactLookupMatch('paymentPartner', name)
field.search = ''
assert.equal(field.options.at(-1).name, 'New payment partner')
field.search = 'a-9'
assert.equal(field.options.some(o => o.lookupKind === 'create'), false, 'Existing partner numbers prevent duplicates')
field.search = 'New partner'
field.selectOption(field.options.at(-1))
assert.deepEqual(events.at(-1), ['create', 'New partner'])
field.reset()
assert.equal(field.search, '')
console.log('Native payment lookups passed: scoped search, hierarchy, frequent groups, duplicate IDs, clear, guarded selection, exact matches and deferred creation.')

// The native positioning hook must keep long lists visible and release observers.
const layoutSource = fs.readFileSync(new URL('../src/utils/lookupMenuLayout.js', import.meta.url), 'utf8')
const { getLookupMenuLayout } = await import(`data:text/javascript,${encodeURIComponent(layoutSource)}`)
const positionSource = fs.readFileSync(new URL('../src/utils/entryLookupPosition.js', import.meta.url), 'utf8')
const listeners = new Set()
let queued, disconnected = false, cancelled = false
const fakeWindow = {
 innerHeight: 500, scrollX: 0, scrollY: 0,
 requestAnimationFrame: callback => { queued = callback; return 1 },
 cancelAnimationFrame: () => { cancelled = true },
 addEventListener: (event, callback) => listeners.add(callback),
 removeEventListener: (event, callback) => listeners.delete(callback),
}
const Observer = class { observe() {} disconnect() { disconnected = true } }
const position = new Function('getLookupMenuLayout', 'window', 'ResizeObserver', positionSource.replace(/^import .*$/gm, '').replace('export function', 'function') + '\nreturn positionEntryLookup')(getLookupMenuLayout, fakeWindow, Observer)
const classes = { add() {}, remove() {}, toggle() {} }
const menu = { isConnected: true, scrollHeight: 700, classList: classes, style: {} }
const anchor = { isConnected: true, getBoundingClientRect: () => ({ top: 300, bottom: 334, left: 12, width: 366 }) }
const select = { $refs: { toggle: anchor }, $el: { classList: classes, closest: () => ({ getBoundingClientRect: () => ({ top: 48, bottom: 450 }) }) } }
const cleanup = position(menu, select, { width: '366px' })
queued()
assert.ok(parseFloat(menu.style.top) >= 48)
assert.ok(parseFloat(menu.style.top) + parseFloat(menu.style.maxHeight) <= 450)
assert.equal(menu.style.width, '366px')
cleanup()
assert.equal(disconnected, true)
assert.equal(listeners.size, 0)
const pendingCleanup = position(menu, select, { width: '366px' })
pendingCleanup()
assert.equal(cancelled, true, 'Closing before the first frame cancels scheduled positioning')
