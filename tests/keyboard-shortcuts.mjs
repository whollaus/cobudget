import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import vm from 'node:vm'

const source = readFileSync(new URL('../src/services/keyboardShortcuts.js', import.meta.url), 'utf8')
const { shortcutAction, createShortcutHandler } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)
const event = (key, modifiers = {}) => ({ key, ctrlKey: true, metaKey: false, altKey: false, shiftKey: false, ...modifiers })
assert.equal(shortcutAction(event('S')), 'save')
assert.equal(shortcutAction(event('p', { ctrlKey: false, metaKey: true })), 'print')
assert.equal(shortcutAction(event('n')), 'new')
assert.equal(shortcutAction(event('˜', { ctrlKey: false, altKey: true, code: 'KeyN' })), 'new', 'Mac Option+N follows physical key')
for (const extras of [{ shiftKey: true }, { altKey: true }, { isComposing: true }, { keyCode: 229 }, { getModifierState: () => true }, { ctrlKey: false }]) {
	assert.equal(shortcutAction(event('s', extras)), null)
}
const input = {}, outside = {}, body = {}
let dialogs = [], available = true, blocked = false, actions = []
const handler = createShortcutHandler({
	doc: { body, querySelectorAll: () => dialogs }, root: () => ({ contains: target => target === input }),
	available: () => available, blocked: () => blocked, run: action => actions.push(action),
})
const press = (extras = {}) => {
	const e = { ...event('s'), target: input, preventDefault() { this.prevented = true }, stopPropagation() { this.stopped = true }, ...extras }
	handler(e); return e
}
assert.equal(press().prevented, true)
assert.deepEqual(actions, ['save'], 'Save works from text inputs')
assert.equal(press({ target: outside }).prevented, undefined, 'Nextcloud header/search untouched')
assert.equal(press({ defaultPrevented: true }).prevented, undefined)
assert.equal(press({ repeat: true }).prevented, true)
blocked = true; assert.equal(press().prevented, true); blocked = false
dialogs = [{ closest: () => null, getClientRects: () => [1] }]
assert.equal(press().prevented, true)
assert.equal(press({ target: { closest: selector => selector === '.cobudget-dialog-layer' ? {} : null } }).prevented, true, 'Teleported app dialogs suppress browser Save without saving underneath')
assert.deepEqual(actions, ['save'], 'Nested dialogs, pending state and held keys never save')
dialogs = []; available = false
assert.equal(press({ key: 'p' }).prevented, undefined, 'Browser printing outside report context untouched')
available = true
press({ target: body, key: 'p' })
assert.deepEqual(actions, ['save', 'print'])

// Exercise application dispatch with real methods and mocked UI/API boundaries.
const appSource = readFileSync(new URL('../src/App.vue', import.meta.url), 'utf8')
const method = appSource.match(/async runKeyboardShortcut\(action\) \{([\s\S]*?)\n\t\t\},/)[1]
const run = vm.runInNewContext(`(async function(action) {${method}\n})`, {
	PRINT_REPORT_EVENT: 'cobudget-print-report', CustomEvent: class { constructor(type) { this.type = type } },
	window: { dispatchEvent: e => actions.push(e.type) },
})
let saves = 0, opens = 0
const sidebar = { isOpen: true, isSaveDisabled: false, saveEntry: async () => { saves++ } }
const ctx = { $refs: { globalEntrySidebar: sidebar }, openEntrySidebar: async payload => { assert.equal(payload.forceNew, true); opens++ } }
await run.call(ctx, 'save'); assert.equal(saves, 1)
sidebar.isSaveDisabled = true; await run.call(ctx, 'save'); assert.equal(saves, 1)
sidebar.isOpen = false; sidebar.isSaveDisabled = false; await run.call(ctx, 'save'); assert.equal(saves, 1)
await run.call(ctx, 'new'); assert.equal(opens, 1); assert.equal(ctx.shortcutOpening, false)
ctx.openEntrySidebar = async () => { throw Error('load failed') }
await assert.rejects(run.call(ctx, 'new')); assert.equal(ctx.shortcutOpening, false)
await run.call(ctx, 'print'); assert.equal(actions.at(-1), 'cobudget-print-report')
assert.match(appSource, /!payload.forceNew && this.shouldCloseEntrySidebar/)
assert.match(appSource, /await sidebar.confirmDiscardChanges\(\)/)
const report = readFileSync(new URL('../src/views/AnnualOverviewView.vue', import.meta.url), 'utf8')
assert.match(report, /addEventListener\(PRINT_REPORT_EVENT, this.printReport\)/)
assert.match(report, /removeEventListener\(PRINT_REPORT_EVENT, this.printReport\)/)
const transactions = readFileSync(new URL('../src/views/TransactionsView.vue', import.meta.url), 'utf8')
assert.match(transactions, /addEventListener\(PRINT_REPORT_EVENT, this.printEntries\)/)
assert.match(transactions, /removeEventListener\(PRINT_REPORT_EVENT, this.printEntries\)/)
const settings = readFileSync(new URL('../src/views/SettingsView.vue', import.meta.url), 'utf8')
assert.ok(settings.indexOf('<KeyboardShortcuts />') < settings.indexOf('class="settings-subsection reset-section"'))
console.log('Keyboard shortcuts passed: Ctrl/Command, Alt fallback, focus scope, dialogs, IME, repeats, pending/invalid saves, guarded creation and CoBudget report dispatch.')
