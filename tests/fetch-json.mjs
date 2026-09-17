import assert from 'node:assert/strict'
import fs from 'node:fs'

// Exercise the installed Nextcloud libraries without the removed global token
// aliases. Only the DOM, storage and network boundary are simulated.
const storage = new Map([['cobudget_workspace_id:alice', '42']])
globalThis.window = {
	location: new URL('https://cloud.example.test/cloud/index.php/apps/cobudget/'),
	_oc_webroot: '/cloud',
	OC: { getCurrentUser: () => ({ uid: 'alice' }) },
	localStorage: {
		getItem: key => storage.get(key) ?? null,
		removeItem: key => storage.delete(key),
	},
}
globalThis.document = {
	head: { dataset: { requesttoken: 'initial-token' } },
	documentElement: { dataset: { locale: 'en_US' }, lang: 'en' },
}
for (const [target, property] of [[window, 'oc_requesttoken'], [window.OC, 'requestToken']]) {
	Object.defineProperty(target, property, {
		get() { throw new Error(`Private token alias ${property} must not be read`) },
	})
}

const requests = []
globalThis.fetch = async (url, options) => {
	requests.push({ url, ...options })
	return {
		ok: true,
		headers: new Headers({ 'content-type': 'application/json' }),
		json: async () => ({ success: true }),
	}
}

// Resolve imports so the actual services can run as ES modules without changing
// the CommonJS package configuration used by Webpack.
const workspaceSource = fs.readFileSync(new URL('../src/services/workspaceStorage.js', import.meta.url), 'utf8')
const workspaceModuleUrl = `data:text/javascript;charset=utf-8,${encodeURIComponent(workspaceSource)}`
const source = fs.readFileSync(new URL('../src/services/fetchJson.js', import.meta.url), 'utf8')
	.replace(/from '(@nextcloud\/[^']+)'/g, (_, name) => `from '${import.meta.resolve(name)}'`)
	.replace("from './workspaceStorage'", `from ${JSON.stringify(workspaceModuleUrl)}`)
const { fetchJson } = await import(`data:text/javascript;charset=utf-8,${encodeURIComponent(source)}`)
const { setRequestToken } = await import('@nextcloud/auth')

assert.deepEqual(await fetchJson('/apps/cobudget/entries', { params: { search: 'rent & bills' } }), { success: true })
assert.equal(requests[0].headers.requesttoken, 'initial-token')
assert.equal(requests[0].headers['X-Workspace-Id'], 42)
assert.equal(requests[0].credentials, 'same-origin')
assert.equal(new URL(requests[0].url).pathname, '/cloud/index.php/apps/cobudget/entries')
assert.equal(new URL(requests[0].url).searchParams.get('search'), 'rent & bills')

// A long-lived tab must use the latest CSRF token on its next request.
setRequestToken('rotated-token')
await fetchJson('/apps/cobudget/entries')
assert.equal(requests[1].headers.requesttoken, 'rotated-token')

await fetchJson('/apps/cobudget/entries', { headers: { requesttoken: 'explicit-token' } })
assert.equal(requests[2].headers.requesttoken, 'explicit-token')

await fetchJson('/apps/cobudget/workspaces', { skipWorkspaceHeader: true })
assert.equal(requests[3].headers['X-Workspace-Id'], undefined)
assert.equal(requests[3].headers.requesttoken, 'rotated-token')

delete globalThis._nc_auth_requestToken
delete document.head.dataset.requesttoken
await fetchJson('/apps/cobudget/entries')
assert.equal(Object.hasOwn(requests[4].headers, 'requesttoken'), false)

console.log('Fetch JSON tests passed: token lookup, rotation, override, workspace scope and missing token.')
