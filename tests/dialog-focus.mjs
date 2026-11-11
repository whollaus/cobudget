import assert from 'node:assert/strict'
import fs from 'node:fs'
const { captureDialogFocus, trackDialogTriggers } = await import(`data:text/javascript;base64,${Buffer.from(fs.readFileSync(new URL('../src/services/dialogFocus.js', import.meta.url))).toString('base64')}`)
const listeners = new Map()
const doc = {
 location: { href: '/settings' }, activeElement: null, layers: [],
 addEventListener: (name, fn) => listeners.set(name, fn),
 removeEventListener: name => listeners.delete(name),
 querySelectorAll() { return this.layers },
}
function element(parent = null) {
 return {
  parentElement: parent, isConnected: true, disabled: false, hidden: false,
  scrollTop: 0, scrollLeft: 0, calls: [],
  matches() { return this.disabled },
  closest(selector) { return selector.startsWith('[inert]') ? this.hidden : null },
  getClientRects() { return this.hidden ? [] : [{}] },
  focus(options) { this.calls.push(options); doc.activeElement = this },
  querySelector() { return null },
 }
}
const page = element(), button = element(page)
page.scrollTop = 640; page.scrollLeft = 25; doc.activeElement = button
const restore = captureDialogFocus(doc)
doc.activeElement = element(); page.scrollTop = 0; page.scrollLeft = 0
assert.equal(restore(), false, 'Native trap must not repeat the focus with scrolling')
assert.equal(doc.activeElement, button)
assert.deepEqual(button.calls, [{ preventScroll: true }])
assert.equal(page.scrollTop, 640); assert.equal(page.scrollLeft, 25)
restore(); assert.equal(button.calls.length, 1, 'Native callback and leave hook restore only once')

const stop1 = trackDialogTriggers(doc), stop2 = trackDialogTriggers(doc)
assert.equal(listeners.size, 2, 'Dialog hosts share document listeners')
const edit = element(page), row = { querySelector: () => edit }
const clickedText = { closest: selector => selector.startsWith('tr,') ? row : null }
listeners.get('click')({ target: clickedText })
doc.activeElement = element()
const restoreRow = captureDialogFocus(doc)
restoreRow(); assert.equal(doc.activeElement, edit, 'Pointer-clicked rows return to their action button')

const next = element(page)
row.nextElementSibling = { querySelector: () => next }
listeners.get('click')({ target: clickedText })
const restoreDeleted = captureDialogFocus(doc)
edit.isConnected = false
restoreDeleted(); assert.equal(doc.activeElement, next, 'Deleted rows fall back to a surviving neighbour')
edit.isConnected = true
const restoreBusy = captureDialogFocus(doc)
edit.disabled = true; next.disabled = true
const oldFocus = doc.activeElement
restoreBusy(); assert.equal(doc.activeElement, oldFocus)
edit.disabled = false
restoreBusy(); assert.equal(doc.activeElement, edit, 'Leave completion can retry a temporarily disabled trigger')

const restoreNested = captureDialogFocus(doc)
const otherDialog = { contains: () => false }
doc.layers = [otherDialog]; doc.activeElement = element()
const nestedFocus = doc.activeElement
restoreNested(); assert.equal(doc.activeElement, nestedFocus, 'Do not steal focus from another open dialog')
doc.layers = [{ contains: candidate => candidate === edit }]
restoreNested(); assert.equal(doc.activeElement, edit, 'Nested close returns inside the remaining parent dialog')
doc.layers = []
const restoreRoute = captureDialogFocus(doc)
doc.location.href = '/another-view'; doc.activeElement = element()
const routeFocus = doc.activeElement
restoreRoute(); assert.equal(doc.activeElement, routeFocus, 'Navigation must not restore stale page focus or scroll')
doc.location.href = '/settings'
const parentDialog = element(page), oldClose = element(parentDialog), newClose = element(parentDialog)
parentDialog.querySelectorAll = () => [newClose]
oldClose.getAttribute = newClose.getAttribute = attr => attr === 'aria-label' ? 'Close' : null
oldClose.closest = selector => selector === '.cobudget-dialog-layer' ? parentDialog : selector.startsWith('button,') ? oldClose : null
listeners.get('click')({ target: oldClose })
oldClose.isConnected = false; oldClose.parentElement = null
const restoreRecreated = captureDialogFocus(doc)
restoreRecreated(); assert.equal(doc.activeElement, newClose, 'Nested confirmation returns to the recreated native close control')
// A delete request may finish after the confirmation's close animation.
let observer
class Observer {
 constructor(callback) { this.callback = callback; observer = this; this.connected = true }
 observe() {}
 disconnect() { this.connected = false }
}
doc.defaultView = { MutationObserver: Observer }; doc.body = element()
const list = element(page), doomed = element(list), neighbour = element(list)
list.querySelectorAll = () => []
list.querySelector = () => neighbour
doomed.closest = selector => selector.startsWith('section,') ? list : selector.startsWith('button,') ? doomed : null
listeners.get('click')({ target: doomed })
captureDialogFocus(doc)(); assert.equal(doc.activeElement, doomed)
doomed.isConnected = false; doc.activeElement = doc.body
observer.callback(); assert.equal(doc.activeElement, neighbour, 'Late row removal recovers focus after the request completes')
listeners.get('click')({ target: neighbour })
assert.equal(observer.connected, false, 'Further user interaction cancels deferred focus recovery')
stop1(); assert.equal(listeners.size, 2)
stop2(); assert.equal(listeners.size, 0, 'Unmount cleans up shared listeners')
console.log('Dialog focus passed: pointer/keyboard origins, scroll, nested dialogs, deleted/disabled triggers, navigation and listener cleanup.')
