import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { createInterface } from 'node:readline'
import { fileURLToPath } from 'node:url'
import { sha256 } from '@noble/hashes/sha256'
import { bytesToHex } from '@noble/hashes/utils'

// Exercise the view's real file reader, digest, packet planner and confirmation flow.
export function loadView(put) {
	const source = readFileSync(new URL('../src/views/BulkImportView.vue', import.meta.url), 'utf8')
	const script = source.split('<script>')[1].split('</script>')[0]
	const bindings = Object.fromEntries([...script.matchAll(/^import (\w+) from /gm)].map(([, name]) => [name, {}]))
	Object.assign(bindings, {
		axios: { put }, generateUrl: path => path, sha256, bytesToHex,
		extractError: error => error.message, showToast() {}, window: new EventTarget(),
	})
	return new Function(...Object.keys(bindings), script.replace(/^import .+$/gm, '').replace('export default', 'return'))(...Object.values(bindings))
}

export function newImport(view, file) {
	const strings = new Proxy({}, { get: (_, name) => () => name })
	const vm = {
		...view.data(), ...view.methods, selectedProjectId: 10, selectedFile: file, loadingContext: false,
		$texts: { bulkImport: strings }, $refs: {}, $nextTick: async () => {}, $router: { push() {} },
	}
	for (const [name, getter] of Object.entries(view.computed)) {
		Object.defineProperty(vm, name, { get: getter.bind(vm) })
	}
	return vm
}

export function startImportSession(user) {
	const fixture = fileURLToPath(new URL('./php/Support/bulk-import-session.php', import.meta.url))
	const php = spawn('php', [fixture, user], { stdio: ['pipe', 'pipe', 'inherit'] })
	const replies = createInterface({ input: php.stdout })[Symbol.asyncIterator]()
	return {
		async request(line) {
			php.stdin.write(line + '\n')
			const reply = await replies.next()
			assert.equal(reply.done, false, 'PHP import fixture terminated before returning a response')
			const response = JSON.parse(reply.value)
			if (response.status !== 200) {
				throw Object.assign(new Error(response.data.error), { response })
			}
			return response.data
		},
		close() {
			php.stdin.end()
			php.kill()
		},
	}
}
