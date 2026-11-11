import assert from 'node:assert/strict'
import { pickFolder } from '../src/services/folderPicker.js'
const listeners = new Map()
let masks = [], closeCount = 0, complete
const doc = {
 querySelectorAll: () => masks,
 addEventListener: (name, callback) => listeners.set(name, callback),
 removeEventListener: name => listeners.delete(name),
}
const wrapper = {}, inside = {}, outsideHeader = {}
const container = { contains: node => node === inside }
const close = { disabled: false, click() { closeCount++; complete?.() } }
const mask = {
 querySelector: selector => ({
  '.file-picker': {},
  '.modal-container': container,
  '.modal-wrapper': wrapper,
  '.modal-container__close': close,
 })[selector],
}
const old = { querySelector: mask.querySelector }
masks = [old]
const cancelled = Object.assign(new Error('Cancelled'), { name: 'FilePickerClosed' })
const pending = pickFolder({ pick: () => new Promise((resolve, reject) => { complete = () => reject(cancelled) }) }, doc)
masks.push(mask)
const press = (target, button = 0) => (listeners.get('pointerdown') || listeners.get('mousedown'))({ target, button })
press(inside); press(old); press(wrapper, 2)
assert.equal(closeCount, 0, 'Ignore inner fields, pre-existing dialogs and secondary clicks')
close.disabled = true; press(outsideHeader); assert.equal(closeCount, 0)
close.disabled = false; press(outsideHeader)
await assert.rejects(pending, error => error === cancelled)
assert.equal(closeCount, 1)
assert.equal(listeners.size, 0, 'Remove the listener after native cancellation')
// Test Escape dispatch when no close button is found
let dispatchedKey = null
const maskWithoutClose = {
 querySelector: selector => selector === '.modal-container' ? container : undefined,
 dispatchEvent: event => { dispatchedKey = event.key; complete?.() },
}
const pendingNoClose = pickFolder({ pick: () => new Promise((resolve, reject) => { complete = () => reject(cancelled) }) }, doc)
masks.push(maskWithoutClose)
listeners.get('pointerdown')({ target: outsideHeader, button: 0 })
await assert.rejects(pendingNoClose, error => error === cancelled)
assert.equal(dispatchedKey, 'Escape')
assert.equal(listeners.size, 0)

assert.equal(await pickFolder({ pick: async () => '/Exports' }, doc), '/Exports')
assert.equal(listeners.size, 0)
await assert.rejects(pickFolder({ pick: async () => { throw new Error('Load failed') } }, doc), /Load failed/)
assert.equal(listeners.size, 0, 'Clean up failures as well as successful selection')
console.log('Folder picker passed: native backdrop cancellation, scope, guards and listener cleanup.')

// Builder-owned containers isolate theming and must disappear on every exit path.
const hosts = []
const themedDoc = {
 ...doc,
 body: { appendChild: node => { hosts.push(node); return node } },
 createElement: () => ({ remove() { hosts.splice(hosts.indexOf(this), 1) } }),
}
for (const result of ['selected', 'cancelled', 'failed', 'build-failed']) {
 const builder = {
  setContainer(selector) { this.selector = selector; return this },
  build() {
   assert.equal(hosts.length, 1)
   assert.equal(this.selector, `#${hosts[0].id}`)
   assert.equal(hosts[0].className, 'cobudget-native-dialog cobudget-folder-picker')
   if (result === 'build-failed') throw new Error('Build failed')
   return { pick: async () => {
    if (result === 'cancelled') throw cancelled
    if (result === 'failed') throw new Error('Load failed')
    return '/Receipts'
   } }
  },
 }
 if (result === 'selected') assert.equal(await pickFolder(builder, themedDoc), '/Receipts')
 else await assert.rejects(pickFolder(builder, themedDoc))
 assert.equal(hosts.length, 0, `Remove theme container after ${result}`)
 assert.equal(listeners.size, 0, `Remove dismiss listeners after ${result}`)
}
console.log('Folder picker theme isolation passed: public builder container and cleanup on selection, cancellation and failures.')
