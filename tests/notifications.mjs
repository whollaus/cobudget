import assert from 'node:assert/strict'
import fs from 'node:fs'

const source = fs.readFileSync(new URL('../src/services/notifications.js', import.meta.url), 'utf8').replace(/^import .*$/gm, '').replaceAll('export ', '')
const hosts = new Map(), calls = []
const document = {
 createElement: () => ({ remove() { hosts.delete(this.id) } }),
 body: { appendChild: node => hosts.set(node.id, node) },
}
let fail = false
const native = type => (message, options) => {
 assert.equal(hosts.get(options.selector)?.className, 'cobudget-toast-container', 'Theme scope exists before native rendering')
 if (fail) throw new Error('Native rendering failed')
 calls.push({ type, message, options })
 // Deliberately return no Toastify instance: only public options are needed.
}
const { showToast } = new Function('showError', 'showInfo', 'showSuccess', 't', 'document', source + '\nreturn { showToast }')(native('error'), native('info'), native('success'), (_, text) => text, document)
for (const type of ['success', 'error', 'info']) showToast('Neutral test message', type)
assert.deepEqual(calls.map(call => call.type), ['success', 'error', 'info'])
assert.deepEqual(calls.map(call => call.options.timeout), [3000, 5000, 3000])
assert.equal(hosts.size, 3)
assert.equal(new Set(calls.map(call => call.options.selector)).size, 3, 'Simultaneous messages use independent containers')
calls[1].options.onRemove()
assert.equal(hosts.size, 2, 'Removing one toast preserves the other theme scopes')
calls[0].options.onRemove(); calls[2].options.onRemove()
assert.equal(hosts.size, 0)
showToast('Custom timeout', 'error', { timeout: 1234 })
assert.equal(calls.at(-1).options.timeout, 1234)
calls.at(-1).options.onRemove()
fail = true
assert.throws(() => showToast('Failure'), /Native rendering failed/)
assert.equal(hosts.size, 0, 'Failed rendering leaves no theme container')
console.log('Notifications passed: theme scope before rendering, native severity dispatch, timeouts, concurrent cleanup and failure cleanup.')
