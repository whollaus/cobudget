import assert from 'node:assert/strict'
import fs from 'node:fs'

// Execute the real Axios interceptor with only its library/storage boundaries
// replaced. Verify both plain headers and the AxiosHeaders API.
let interceptor
globalThis.cobudgetHttpTestAxios = {
	interceptors: { request: { use: callback => { interceptor = callback } } },
}
const source = fs.readFileSync(new URL('../src/services/http.js', import.meta.url), 'utf8')
	.replace("import axios from '@nextcloud/axios'", 'const axios = globalThis.cobudgetHttpTestAxios')
	.replace("import { readWorkspaceId } from './workspaceStorage'", 'const readWorkspaceId = () => 42')
await import(`data:text/javascript;charset=utf-8,${encodeURIComponent(source)}`)
delete globalThis.cobudgetHttpTestAxios

const originalDateTimeFormat = Intl.DateTimeFormat
try {
	Intl.DateTimeFormat = () => ({ resolvedOptions: () => ({ timeZone: 'Europe/Vienna' }) })
	const request = interceptor({ headers: { 'x-cobudget-timezone': 'UTC' } })
	assert.equal(request.headers['X-CoBudget-Timezone'], 'Europe/Vienna')
	assert.equal(request.headers['x-cobudget-timezone'], undefined)
	assert.equal(request.headers['X-Workspace-Id'], 42)

	const headers = new Headers({ 'X-CoBudget-Timezone': 'UTC', 'X-Workspace-Id': '99' })
	interceptor({ headers, skipWorkspaceHeader: true })
	assert.equal(headers.get('X-CoBudget-Timezone'), 'Europe/Vienna')
	assert.equal(headers.get('X-Workspace-Id'), null)

	Intl.DateTimeFormat = () => { throw new Error('Timezone unavailable') }
	const fallback = interceptor({ headers: { 'X-CoBudget-Timezone': 'UTC' } })
	assert.equal(fallback.headers['X-CoBudget-Timezone'], undefined)
	assert.equal(fallback.headers['X-Workspace-Id'], 42)
} finally {
	Intl.DateTimeFormat = originalDateTimeFormat
}

console.log('HTTP timezone tests passed: browser timezone, header replacement, workspace scope and fallback.')
