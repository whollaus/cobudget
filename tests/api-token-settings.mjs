import assert from 'node:assert/strict'
import fs from 'node:fs'
import { createRequire } from 'node:module'
import { parse } from '@vue/compiler-sfc'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'

globalThis.window ??= {
	t: (_app, text) => text,
	n: (_app, singular, plural, count) => (count === 1 ? singular : plural),
}
const { texts } = await import('../src/l10n/texts.js')
const { buildApiExportFilename, buildCurlExample, buildHttpFile, buildOpenApiSpec } = await import('../src/utils/apiExamples.js')

const require = createRequire(import.meta.url)
const root = new URL('../', import.meta.url)
const read = file => fs.readFileSync(new URL(file, root), 'utf8')

const rawSource = read('src/components/ApiTokenSettings.vue')
const textRefs = new Set()
for (const match of rawSource.matchAll(/\$texts\.(\w+)\.(\w+)\s*\(/g)) textRefs.add(`${match[1]}.${match[2]}`)
for (const match of rawSource.matchAll(/\bt\.(\w+)\s*\(/g)) textRefs.add(`apiTokens.${match[1]}`)
for (const match of rawSource.matchAll(/\bbi\.(\w+)\s*\(/g)) textRefs.add(`bulkImport.${match[1]}`)
for (const match of rawSource.matchAll(/\blabels\.(\w+)\s*\(/g)) textRefs.add(`labels.${match[1]}`)
for (const ref of textRefs) {
	const [section, key] = ref.split('.')
	assert.equal(typeof texts[section]?.[key], 'function', `ApiTokenSettings references existing text helper ${ref}()`)
}

const { descriptor } = parse(rawSource)
let script = descriptor.script.content
script = script.replace(/^import .+$/gm, '').replace('export default', 'return')
const styleContent = descriptor.styles.map(style => style.content).join('\n')
assert.match(styleContent, /\.settings-hint\s*\{[^}]*margin-bottom:/s, 'ApiTokenSettings defines scoped spacing below its intro hint')
assert.match(descriptor.template.content, /\$texts\.apiTokens\.tokenColumn\(\)/, 'API key table renders the API key column header')
assert.match(descriptor.template.content, /formatTokenPreview\(token\)/, 'API key table renders the masked token preview')
assert.match(descriptor.template.content, /\$texts\.apiTokens\.expiryLabel\(\)/, 'API key table renders dedicated Expiration column')
assert.match(descriptor.template.content, /\$texts\.autoImports\.active\(\)/, 'API key table renders dedicated Active column')
assert.match(descriptor.template.content, /\$texts\.autoImports\.lastRun\(\)/, 'API key table renders dedicated Executed/Last run column')
assert.match(descriptor.template.content, /toggleTokenActive\(token,\s*\$event\)/, 'API key table Active switch calls inline toggle handler')
assert.ok(!descriptor.template.content.includes('scopeColumn'), 'API key table omits the Scope column')
assert.ok(!descriptor.template.content.includes('formatScopes'), 'API key table omits the Permissions column')
assert.match(styleContent, /--color-background-hover/, 'API key table uses neutral row hover matching AutomaticImportSettings')
assert.ok(!styleContent.includes('--cobudget-table-row-hover'), 'API key table avoids tinted blue row hover')
assert.match(descriptor.template.content, /\$texts\.apiTokens\.paymentsHeading\(\)/, 'API key modal renders payment matrix heading')
assert.match(descriptor.template.content, /\$texts\.apiTokens\.generalHeading\(\)/, 'API key modal renders general matrix heading')
assert.match(descriptor.template.content, /toggleTargetAction\(row\.key,\s*col\.key,\s*\$event\)/, 'API key modal renders per-area action checkboxes')
assert.match(descriptor.template.content, /api-token-matrix-unavailable/, 'API key modal renders accessible dash for unsupported matrix actions')
assert.ok(!descriptor.template.content.includes('globalScopeDefinitions'), 'API key modal no longer renders globalScopeDefinitions switches')
assert.ok(!styleContent.includes('.api-token-scopes'), 'API key modal removes obsolete switch grid styles')
assert.ok(!descriptor.template.content.includes('$texts.apiTokens.dataAccess()'), 'API key modal no longer renders a separate Data access switch grid')
assert.ok(
	descriptor.template.content.indexOf('v-model="editor.isActive"') !== -1
	&& descriptor.template.content.indexOf('v-model="editor.isActive"') < descriptor.template.content.indexOf('id="api-token-name"'),
	'API key active switch appears directly below the modal title before the Name field',
)
assert.match(descriptor.template.content, /\$texts\.common\.delete\(\)/, 'API key modal uses common Delete label')
assert.match(descriptor.template.content, /\$texts\.apiTokens\.deleteMessage\(/, 'API key delete confirmation uses deleteMessage')
assert.ok(!descriptor.template.content.includes('revokeTitle') && !descriptor.template.content.includes('revokeButton'), 'API key modal no longer uses Revoke labels')
assert.match(styleContent, /\.api-token-validity\s*\{[^}]*grid-template-columns:\s*repeat\(2,/s, 'API key modal renders two-column Expiration + Date layout')
assert.match(descriptor.template.content, /uid="cobudget-api-token-expiry"/, 'API key Expiration select exposes uid for scoped template group listbox styling')
assert.match(descriptor.template.content, /\$texts\.apiTokens\.expiryDateColumn\(\)/, 'API key modal renders second Date column alongside Expiration')
assert.ok(!descriptor.template.content.includes('expiryCustom') && !descriptor.template.content.includes('expiryKeep'), 'API key modal removes legacy custom/keep expiry keys')
assert.ok(!descriptor.template.content.includes('api-token-copy-status') && !styleContent.includes('api-token-copy-status'), 'Secret reveal dialog omits duplicate inline copy status beside the copy button')
assert.ok(
	descriptor.template.content.indexOf('@click.stop="openEndpointsPreview(token)"') !== -1
	&& descriptor.template.content.indexOf('@click.stop="openEndpointsPreview(token)"') < descriptor.template.content.indexOf('@click.stop="openEditor(token)"'),
	'API key table renders the endpoints preview button directly to the left of the Edit button',
)
assert.match(descriptor.template.content, /id="api-token-endpoints-title"/, 'API key settings renders the endpoints preview modal')
assert.match(descriptor.template.content, /v-for="group in previewEndpointGroups"/, 'Endpoints preview modal renders permission-filtered endpoint groups')
assert.match(descriptor.template.content, /<ApiIcon\b/, 'API key table renders the API icon for the endpoints preview button')
assert.ok(!descriptor.template.content.includes('HelpCircleOutlineIcon'), 'API key table replaces HelpCircleOutlineIcon with ApiIcon')
assert.match(descriptor.template.content, /<h3 class="cobudget-section-heading">\{\{ group\.title \}\}<\/h3>/, 'Endpoint groups use cobudget-section-heading like settings sections')
assert.match(descriptor.template.content, /<NcFormGroup v-for="endpoint in group\.endpoints" :key="endpoint\.key" class="cobudget-form-group api-token-endpoint-item">/, 'Each endpoint uses NcFormGroup with cobudget-form-group')
assert.ok(
	descriptor.template.content.indexOf('<span class="cobudget-form-label">{{ endpoint.description }}</span>') < descriptor.template.content.indexOf('api-token-endpoint-header-row'),
	'Endpoint form group orders field title and endpoint field like settings forms',
)
assert.ok(
	descriptor.template.content.indexOf('endpoint.url') < descriptor.template.content.indexOf('toggleEndpointParams(endpoint.key)')
	&& descriptor.template.content.indexOf('toggleEndpointParams(endpoint.key)') < descriptor.template.content.indexOf('toggleEndpointExample(endpoint.key)')
	&& descriptor.template.content.indexOf('toggleEndpointExample(endpoint.key)') < descriptor.template.content.indexOf('copyEndpointUrl(endpoint.url)'),
	'Endpoint header renders Parameter and curl icon toggles between URL and copy button',
)
assert.match(descriptor.template.content, /@click="toggleEndpointExample\(endpoint\.key\)"[\s\S]*<ConsoleIcon\b[\s\S]*<\/NcButton>/, 'Curl toggle renders ConsoleIcon button with endpointCurlToggle label')
assert.match(descriptor.template.content, /endpointCurlToggle\(\)/, 'Curl icon button uses endpointCurlToggle accessible title/aria-label')
assert.match(descriptor.template.content, /api-token-auth-card[\s\S]*api-token-auth-table[\s\S]*copyAuthHeader\(row\.copyValue\)/, 'Authentication section renders structured header table with copy buttons')
assert.ok(
	!descriptor.template.content.includes('toggleEndpointTargets')
	&& !descriptor.template.content.includes('api-token-targets-panel')
	&& !descriptor.template.content.includes('endpointsTargetsToggle')
	&& !descriptor.template.content.includes('endpointsTargetIdColumn'),
	'Endpoint box removes the separate Allowed-for toggle and table in favor of the parameter table Allowed values column',
)
assert.match(descriptor.template.content, /<div class="api-token-endpoint-field">[\s\S]*api-token-endpoint-header-row[\s\S]*api-token-params-panel[\s\S]*api-token-example-panel/, 'Parameter and curl example panels expand inside the same endpoint field box')
assert.ok(
	descriptor.template.content.indexOf('downloadOpenApiSpec') !== -1
	&& descriptor.template.content.indexOf('downloadHttpFile') !== -1
	&& descriptor.template.content.indexOf('downloadOpenApiSpec') < descriptor.template.content.indexOf('api-token-endpoints-auth')
	&& descriptor.template.content.indexOf('downloadHttpFile') < descriptor.template.content.indexOf('api-token-endpoints-auth'),
	'Endpoints modal displays OpenAPI JSON and .http export buttons at the top before the Authentication section',
)
assert.match(descriptor.template.content, /<table[^>]*class="bulk-import-table api-token-params-table"/, 'Parameter table reuses bulk-import-table design')
assert.match(descriptor.template.content, /<th scope="col" class="col-field">\{\{ \$texts\.bulkImport\.fieldColumnName\(\) \}\}<\/th>\s*<th scope="col" class="col-type">\{\{ \$texts\.apiTokens\.endpointFieldTypeColumn\(\) \}\}<\/th>\s*<th scope="col" class="col-required"><span class="hidden-visually">\{\{ \$texts\.bulkImport\.fieldColumnRequired\(\) \}\}<\/span><\/th>\s*<th v-if="hasParamValues\(endpoint\)" scope="col" class="col-values">\{\{ \$texts\.apiTokens\.endpointAllowedValuesColumn\(\) \}\}<\/th>\s*<th scope="col" class="col-notes">\{\{ \$texts\.bulkImport\.columnDescription\(\) \}\}<\/th>/s, 'Parameter table separates field name, field type, required/optional badge, allowed values, and description columns')
assert.match(descriptor.template.content, /<ul[^>]*class="api-token-param-values"[\s\S]*<code class="bulk-import-field-code">\{\{ item\.value \}\}<\/code>[\s\S]*\{\{ item\.label \}\}/s, 'Parameter Allowed values column renders value pills and optional area names')
assert.match(descriptor.template.content, /<code class="bulk-import-field-code">\{\{ param\.name \}\}<\/code>/, 'Parameter names render as bulk-import-field-code pills')
assert.doesNotMatch(descriptor.template.content, /formatParamType/, 'Parameter table no longer prepends location prefixes via formatParamType')
assert.match(styleContent, /\.api-token-endpoint-header,\s*\.api-token-endpoint-url\s*\{[^}]*font-family:\s*inherit;\s*font-size:\s*var\(--cobudget-font-body,\s*15px\);/s, 'Endpoint fields use standard CoBudget form typography')
assert.match(styleContent, /\.api-token-params-panel\s*\{[^}]*overflow:\s*hidden;/s, 'Parameter panel avoids horizontal scrollbars inside the endpoint box')
assert.match(styleContent, /\.api-token-params-table\s*\{[^}]*width:\s*100%;[^}]*table-layout:\s*fixed;/s, 'Parameter table uses fixed table layout to fit 100% width')
assert.match(styleContent, /\.api-token-params-table :is\(th, td\),[^}]*white-space:\s*normal;[^}]*overflow-wrap:\s*anywhere;/s, 'Parameter table wraps cells cleanly without overflowing')
assert.match(styleContent, /\.api-token-params-table td\s*\{[^}]*vertical-align:\s*top;[^}]*border-bottom:\s*1px solid var\(--cobudget-border\);/s, 'Parameter table aligns cells to the top with fine row dividers')
assert.match(styleContent, /\.api-token-param-values--stacked \.api-token-param-value-item\s*\{[^}]*border-bottom:\s*1px solid var\(--cobudget-border\);/s, 'Stacked allowed values show fine dividers between rows')
assert.match(styleContent, /\.api-token-param-values--stacked \.bulk-import-field-code\s*\{[^}]*white-space:\s*nowrap;/s, 'Stacked allowed value IDs stay on one line')
assert.match(styleContent, /\.api-token-endpoints-body\s*\{\s*display:\s*contents;/s, 'Endpoints modal scrolls as one flow on desktop like MasterDataSettings')
assert.match(styleContent, /@media\s*\(max-width:\s*768px\)\s*\{[\s\S]*\.api-token-endpoints-body\s*\{[^}]*overflow:\s*auto;\s*overscroll-behavior:\s*none;\s*transform:\s*translateZ\(0\);/s, 'Endpoints modal keeps a single opaque composited body scroller on mobile')

const dialogTransition = parse(read('src/components/DialogTransition.vue')).descriptor
const dialogStyles = dialogTransition.styles.map(style => style.content).join('\n')
assert.match(dialogTransition.template.content, /class="cobudget-dialog-layer"\s+@wheel="containWheelScroll"/, 'DialogTransition intercepts wheel events on the dialog layer so background scroll cannot chain under modals')
assert.match(dialogTransition.script.content, /containWheelScroll\s*\(event\)/, 'DialogTransition defines containWheelScroll handler')
assert.match(dialogStyles, /\.cobudget-dialog-layer\s+\.modal-mask,\s*\.cobudget-dialog-layer\s+\.confirm-modal-backdrop\s*\{[^}]*overflow:\s*hidden;[^}]*overscroll-behavior:\s*none;/s, 'DialogTransition turns modal-mask and confirm-modal-backdrop into scroll-contained barriers against background flicker')
assert.doesNotMatch(dialogStyles, /\.cobudget-dialog-layer\s+\.modal-mask,\s*\.cobudget-dialog-layer\s+\.confirm-modal-backdrop\s*\{[^}]*will-change:\s*opacity;/s, 'DialogTransition avoids permanent will-change layer promotion on modal-mask')
assert.match(dialogStyles, /\.cobudget-dialog-layer\s+\.modal-container__content,\s*\.cobudget-dialog-layer\s+\.dialog__content,\s*\.cobudget-dialog-layer\s+\.dialog__wrapper:not\(\.dialog__wrapper--collapsed\)\s+\.dialog__navigation\s*\{[^}]*background-color:\s*inherit;[^}]*overscroll-behavior:\s*none;[^}]*transform:\s*translateZ\(0\);[^}]*backface-visibility:\s*hidden;/s, 'DialogTransition isolates the scrolling modal container onto an opaque composited layer')

let storedWorkspaceId = 2

const deriveEntryScopesFromMatrix = targetPermissions => {
	const map = { read: 'entries:read', create: 'entries:create', write: 'entries:write', delete: 'entries:delete' }
	const set = new Set()
	for (const actions of Object.values(targetPermissions || {})) {
		for (const action of actions || []) if (map[action]) set.add(map[action])
	}
	return [...set]
}

const requests = []
const toasts = []
let nextMutationNeedsPasswordConfirm = false
let catalogTokens = []
let nextDeleteError = null
const api = {
	get: async (...args) => {
		requests.push(['GET', ...args])
		return {
			data: {
				enabled: true,
				workspaces: [
					{ id: 1, name: 'Basis', is_default: true },
					{ id: 2, name: 'Business', is_default: false },
				],
				areas_by_workspace: {
					1: [
						{ id: 10, name: 'Household', color: '#0082c9', is_archived: false },
						{ id: 11, name: 'Old Trip', color: '#999999', is_archived: true },
					],
					2: [{ id: 20, name: 'Office', color: '#2b8a3e', is_archived: false }],
				},
				tokens: catalogTokens,
			},
		}
	},
	post: async (...args) => {
		requests.push(['POST', ...args])
		if (args[0] === '/login/confirm') {
			if (args[1]?.password !== 'correct-pass') {
				const err = new Error('Forbidden')
				err.response = { status: 403, headers: {} }
				throw err
			}
			return { data: { lastLogin: 1700000000 } }
		}
		if (nextMutationNeedsPasswordConfirm) {
			nextMutationNeedsPasswordConfirm = false
			const err = new Error('Password confirmation required')
			err.response = { status: 403, headers: { 'x-nc-auth-notconfirmed': 'true' } }
			throw err
		}
		const matrix = args[1].targetPermissions || {}
		const areaIds = Object.keys(matrix).filter(key => key !== 'personal').map(Number)
		return {
			data: {
				token: {
					id: 5,
					workspace_id: args[1].workspaceId,
					name: args[1].name,
					token_prefix: 'cb_12345678',
					scopes: [...deriveEntryScopesFromMatrix(matrix), ...(args[1].scopes || [])],
					allow_personal: !!matrix.personal?.length,
					project_mode: 'matrix',
					project_ids: areaIds,
					target_permissions: matrix,
					is_active: true,
					is_expired: false,
					expires_at: args[1].expiresAt,
					last_used_at: null,
				},
				plain_token: 'cb_' + 'a'.repeat(64),
			},
		}
	},
	put: async (...args) => {
		requests.push(['PUT', ...args])
		const matrix = args[1].targetPermissions || {}
		const areaIds = Object.keys(matrix).filter(key => key !== 'personal').map(Number)
		return {
			data: {
				token: {
					id: 5,
					workspace_id: 1,
					name: args[1].name,
					token_prefix: 'cb_12345678',
					scopes: [...deriveEntryScopesFromMatrix(matrix), ...(args[1].scopes || [])],
					allow_personal: !!matrix.personal?.length,
					project_mode: 'matrix',
					project_ids: areaIds,
					target_permissions: matrix,
					is_active: args[1].isActive,
					is_expired: false,
					expires_at: args[1].expiresAt,
					last_used_at: null,
				},
			},
		}
	},
	delete: async (...args) => {
		requests.push(['DELETE', ...args])
		if (nextDeleteError) {
			const error = nextDeleteError
			nextDeleteError = null
			throw error
		}
		return { data: { status: 'success' } }
	},
}

const sortAreasByName = areas => [...(Array.isArray(areas) ? areas : [])].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'en') || Number(a.id) - Number(b.id))
const downloadedFiles = []
const downloadTextFile = (filename, contents, type) => {
	downloadedFiles.push({ filename, contents, type })
}

const component = new Function(
	'generateUrl',
	'downloadTextFile',
	'axios',
	'extractError',
	'showToast',
	'readWorkspaceId',
	'buildApiExportFilename',
	'buildCurlExample',
	'buildHttpFile',
	'buildOpenApiSpec',
	'sortAreasByName',
	'getAppLocale',
	'useLocalDate',
	'AreaIcon',
	'document',
	'DialogTransition',
	...['ApiIcon', 'CheckIcon', 'ChevronDownIcon', 'ConsoleIcon', 'ContentCopyIcon', 'DeleteOutlineIcon', 'DownloadIcon', 'NcButton', 'NcCheckboxRadioSwitch', 'NcDialog', 'NcFormBoxSwitch', 'NcFormGroup', 'NcInputField', 'NcLoadingIcon', 'NcModal', 'NcNoteCard', 'NcSelect', 'PencilIcon', 'PlusIcon'],
	script,
)(
	url => url,
	downloadTextFile,
	api,
	(err, fallback) => err?.message || fallback,
	(msg, type) => toasts.push([type, msg]),
	() => storedWorkspaceId,
	buildApiExportFilename,
	buildCurlExample,
	buildHttpFile,
	buildOpenApiSpec,
	sortAreasByName,
	() => 'de-AT',
	() => '2026-11-26',
	{},
	{ activeElement: null, createElement: () => ({ setAttribute() {}, style: {}, select() {}, remove() {} }), body: { append() {} }, execCommand: () => true },
	{},
	...Array(19).fill({}),
)

const textsProxy = new Proxy({}, {
	get: (_, section) => new Proxy({}, {
		get: (__, key) => {
			assert.equal(typeof texts[section]?.[key], 'function', `Unknown $texts.${String(section)}.${String(key)} accessed at runtime`)
			return (...args) => `${String(section)}.${String(key)}(${args.join(',')})`
		},
	}),
})
const vm = {
	...component.setup(),
	...component.data(),
	enabled: true,
	adminEnabled: true,
	adminLoaded: true,
	workspacesEnabled: false,
	areasEnabled: true,
	settingsBusy: false,
	$texts: textsProxy,
}
for (const [name, fn] of Object.entries(component.methods)) vm[name] = fn.bind(vm)
for (const [name, fn] of Object.entries(component.computed)) Object.defineProperty(vm, name, { get: fn.bind(vm) })

await vm.load()
assert.equal(vm.workspaces.length, 2)
assert.equal(requests[0][0], 'GET')
assert.deepEqual(requests[0][2], { skipWorkspaceHeader: true })

// When workspaces are disabled in settings, hide workspace selector/column and always use the default workspace.
assert.equal(vm.showWorkspaceColumn, false, 'Workspace column stays hidden when workspaces are disabled')
vm.openEditor()
assert.equal(vm.showWorkspaceField, false, 'Workspace selector stays hidden when workspaces are disabled')
assert.equal(vm.editor.workspaceId, 1, 'Disabled workspace mode must fall back to the default workspace even if storage held workspace 2')

// When workspaces are enabled, readWorkspaceId() is used for new keys.
vm.workspacesEnabled = true
assert.equal(vm.showWorkspaceColumn, true)
storedWorkspaceId = 1
vm.openEditor()
assert.equal(vm.showWorkspaceField, true)
assert.equal(vm.editor.workspaceId, 1, 'Default workspace matches readWorkspaceId() when workspaces are enabled')

// Matrix columns use Read, Edit, Create, Delete order; rows list "Without area" first + active areas only (no archived area 11).
assert.deepEqual(vm.matrixColumns.map(col => col.key), ['read', 'write', 'create', 'delete'])
assert.deepEqual(
	vm.dataTargetRows.map(row => ({ key: row.key, areaId: row.areaId, name: row.name })),
	[
		{ key: 'personal', areaId: null, name: 'masterData.noArea()' },
		{ key: '10', areaId: 10, name: 'Household' },
	],
)
assert.equal(vm.canUseReportsScope, false, 'reports:read is blocked until Read is enabled for Without area and all active areas')
assert.deepEqual(vm.generalMatrixRows, [
	{
		key: 'reports',
		label: 'apiTokens.reportsAndBudgetsRow()',
		hint: 'apiTokens.scopeReportsReadBlocked()',
		scopes: { read: 'reports:read' },
	},
	{
		key: 'settings',
		label: 'settings.settings()',
		hint: '',
		scopes: { read: 'settings:read', write: 'settings:write' },
	},
])

vm.editor.name = 'Phone Shortcut'
vm.toggleTargetAction('personal', 'create', true)
vm.toggleTargetAction('10', 'read', true)
assert.equal(vm.canUseReportsScope, true, 'reports:read unlocks when Without area and all active areas allow Read')
assert.equal(vm.generalMatrixRows[0].hint, '', 'Reports hint clears once reports:read is unlocked')
vm.toggleGlobalScope('reports:read', true)
assert.ok(vm.editor.globalScopes.includes('reports:read'))

// Toggling Read off for area 10 automatically clears reports:read while keeping other permissions
vm.toggleTargetAction('10', 'read', false)
assert.equal(vm.canUseReportsScope, false)
assert.ok(!vm.editor.globalScopes.includes('reports:read'), 'reports:read must clear when Read on an active area is unchecked')

// Clearing every matrix cell disables saving, while enabling a general matrix scope (e.g. settings:read) allows saving
vm.toggleTargetAction('personal', 'read', false)
vm.toggleTargetAction('personal', 'create', false)
assert.equal(vm.canSave, false, 'Saving requires at least one matrix permission or global scope')
vm.toggleGlobalScope('settings:read', true)
assert.equal(vm.canSave, true, 'Enabling Settings Read allows saving without payment scopes')
vm.toggleGlobalScope('settings:write', true)
assert.deepEqual(vm.editor.globalScopes, ['settings:read', 'settings:write'])
vm.toggleGlobalScope('settings:read', false)
vm.toggleGlobalScope('settings:write', false)
assert.equal(vm.canSave, false)
vm.toggleTargetAction('personal', 'read', true)
vm.toggleTargetAction('10', 'read', true)
assert.equal(vm.canSave, true)

const formatLocalIsoOffset = days => {
	const date = new Date()
	date.setDate(date.getDate() + days)
	const y = date.getFullYear()
	const m = String(date.getMonth() + 1).padStart(2, '0')
	const d = String(date.getDate()).padStart(2, '0')
	return `${y}-${m}-${d}`
}

// Verify Expiration dropdown includes No expiration, Expires on, and a non-selectable Templates group
assert.deepEqual(
	vm.expiryOptions.map(option => ({ value: option.value, heading: !!option.heading, template: !!option.template })),
	[
		{ value: 'none', heading: false, template: false },
		{ value: 'date', heading: false, template: false },
		{ value: 'templates-heading', heading: true, template: false },
		{ value: '30d', heading: false, template: true },
		{ value: '90d', heading: false, template: true },
		{ value: '365d', heading: false, template: true },
	],
)

// Selecting 90d calculates today + 90d in Date column; clicking non-selectable heading does nothing; manual date edit switches select to 'date'
vm.changeExpiryPreset(vm.expiryOptions.find(option => option.value === '90d'))
assert.equal(vm.editor.expiryPreset, '90d')
assert.equal(vm.editor.expiryDate, formatLocalIsoOffset(90))
vm.changeExpiryPreset(vm.expiryOptions.find(option => option.heading))
assert.equal(vm.editor.expiryPreset, '90d', 'Selecting Templates group heading must be ignored')
const manualDate = formatLocalIsoOffset(120)
vm.changeExpiryDate(manualDate)
assert.equal(vm.editor.expiryPreset, 'date', 'Editing the date switches Expiration dropdown to Expires on')
assert.equal(vm.editor.expiryDate, manualDate)
vm.changeExpiryPreset(vm.expiryOptions.find(option => option.value === '90d'))
assert.equal(vm.editor.expiryDate, formatLocalIsoOffset(90))

await vm.save()
assert.equal(requests.at(-1)[0], 'POST')
assert.deepEqual(requests.at(-1)[2].targetPermissions, { personal: ['read'], 10: ['read'] })
assert.ok(typeof requests.at(-1)[2].expiresAt === 'number' && requests.at(-1)[2].expiresAt > Math.floor(Date.now() / 1000))
assert.equal(vm.tokens.length, 1)
assert.equal(vm.formatTokenPreview({ token_prefix: 'cb_12345678' }), 'cb_12345678••••••••')
assert.equal(vm.revealedToken.plainToken, 'cb_' + 'a'.repeat(64))
vm.closeRevealedToken()
assert.equal(vm.revealedToken, null, 'Plain secret must be cleared when closing modal')

vm.tokens[0].revision = 'revision-on-open'
// Edit existing token: shows Expires on ('date') + saved date (never template preset) + dirty guard
vm.openEditor(vm.tokens[0])
assert.equal(vm.editor.expiryPreset, 'date', 'Editing an expiring key displays Expires on instead of a relative template')
assert.equal(vm.editor.expiryDate, formatLocalIsoOffset(90))
assert.equal(vm.hasChanges, false)
assert.equal(vm.canSave, false, 'Saving unchanged existing token is disabled')
vm.editor.isActive = false
assert.equal(vm.hasChanges, true)
assert.equal(vm.canSave, true)
await vm.save()
assert.equal(requests.at(-1)[0], 'PUT')
assert.equal(requests.at(-1)[2].revision, 'revision-on-open')
assert.equal(requests.at(-1)[2].expiresAt, vm.tokens[0].expires_at)
assert.equal(vm.tokens[0].is_active, false)

// Selecting 365d template while editing updates Date to 365 days from today; switching to none clears expiryDate and saves null
vm.openEditor(vm.tokens[0])
vm.changeExpiryPreset(vm.expiryOptions.find(option => option.value === '365d'))
assert.equal(vm.editor.expiryDate, formatLocalIsoOffset(365))
assert.equal(vm.hasChanges, true)
vm.changeExpiryPreset(vm.expiryOptions.find(option => option.value === 'none'))
assert.equal(vm.editor.expiryDate, '')
await vm.save()
assert.equal(requests.at(-1)[2].expiresAt, null)
assert.equal(vm.tokens[0].expires_at, null)

// Reopening key without expiration shows No expiration ('none') and empty Date; editing an expired token keeps save working for non-expiry fields
vm.openEditor(vm.tokens[0])
assert.equal(vm.editor.expiryPreset, 'none')
assert.equal(vm.editor.expiryDate, '')

const expiredToken = {
	...vm.tokens[0],
	expires_at: Math.floor(Date.now() / 1000) - 86400 * 3,
	is_expired: true,
}
vm.openEditor(expiredToken)
assert.equal(vm.editor.expiryPreset, 'date')
assert.equal(vm.hasChanges, false)
assert.equal(vm.minExpiryDate, vm.editor.initialExpiryDate)
vm.editor.name = 'Renamed Expired Key'
assert.equal(vm.canSave, true, 'Saving non-expiry changes preserves existing past expiration date')

vm.tokens[0].revision = 'revision-in-list'
// Inline Active switch in table row toggles directly via PUT
await vm.toggleTokenActive(vm.tokens[0], true)
assert.equal(requests.at(-1)[0], 'PUT')
assert.equal(requests.at(-1)[2].isActive, true)
assert.equal(requests.at(-1)[2].revision, 'revision-in-list')
assert.equal(vm.tokens[0].is_active, true)
await vm.toggleTokenActive(vm.tokens[0], false)
assert.equal(requests.at(-1)[0], 'PUT')
assert.equal(requests.at(-1)[2].isActive, false)
assert.equal(vm.tokens[0].is_active, false)

// The same confirmation protects deletion from the editor and the disabled API's table.
vm.openEditor(vm.tokens[0])
vm.tokenToDelete = vm.editor.token
await vm.revokeToken()
assert.equal(requests.at(-1)[0], 'DELETE')
assert.equal(vm.tokens.length, 0)
assert.equal(vm.tokenToDelete, null)

async function renderDisabledTable(tokens, tokenToDelete = null, { adminEnabled = true, adminLoaded = true, enabled = false } = {}) {
	const controls = {
		NcButton: { template: '<button><slot /></button>' },
		NcCheckboxRadioSwitch: { template: '<input />' },
		NcFormBoxSwitch: { props: ['label'], template: '<div data-master-switch="true">{{ label }}</div>' },
		NcNoteCard: { props: ['type', 'text'], template: '<aside :data-note-type="type">{{ text }}<slot /></aside>' },
		DialogTransition: { props: ['show'], template: '<slot v-if="show" :return-focus="null" />' },
		NcDialog: { props: ['message'], template: '<section>{{ message }}<slot name="actions" /></section>' },
	}
	const app = createSSRApp({
		template: descriptor.template.content,
		data: () => ({ ...component.data(), enabled, adminEnabled, adminLoaded, tokens, tokenToDelete, workspacesEnabled: false, settingsBusy: false }),
		computed: component.computed,
		methods: component.methods,
	})
	app.config.globalProperties.$texts = textsProxy
	for (const name of Object.keys(component.components)) {
		app.component(name, controls[name] || { render: () => null })
	}
	return renderToString(app)
}

vm.openEditor()
vm.editor.name = 'Unsaved'
assert.equal(vm.canSave, true)
vm.enabled = false
assert.equal(vm.canSave, false)
const disabledRequestStart = requests.length
await vm.save()
assert.equal(requests.length, disabledRequestStart, 'A stale editor cannot create or change a key while the API is off')

catalogTokens = [{ id: 5, name: 'Existing key', revision: 'current-off-revision', workspace_id: 1, is_active: true, project_mode: 'matrix', target_permissions: { personal: ['read'] } }]
assert.equal(component.watch.enabled.immediate, true)
await component.watch.enabled.handler.call(vm, false)
assert.deepEqual(requests.slice(disabledRequestStart).map(([method, url]) => [method, url]), [['GET', '/apps/cobudget/api/settings/api-tokens']])
assert.equal(vm.editor, null)
assert.equal(vm.tokens[0].name, 'Existing key')

const activeKeyTable = await renderDisabledTable(vm.tokens)
assert.match(activeKeyTable, /Existing key/)
assert.match(activeKeyTable, /aria-label="common.delete\(\): Existing key"/)
assert.doesNotMatch(activeKeyTable, /apiTokens\.addKey\(|common\.edit\(|apiTokens\.endpointsPreviewTitle\(/)
assert.match(activeKeyTable, /<input [^>]*aria-label="autoImports\.active\(\) · Existing key"[^>]*>/)
assert.doesNotMatch(activeKeyTable, /<input [^>]* disabled/)

vm.openEditor()
vm.openEditor(vm.tokens[0])
assert.equal(vm.editor, null, 'The disabled API has no create or edit entry point')
await vm.toggleTokenActive(vm.tokens[0], false)
assert.equal(vm.tokens[0].is_active, false)
const pausedKeyTable = await renderDisabledTable(vm.tokens)
assert.match(pausedKeyTable, /<input [^>]* disabled/)
await vm.toggleTokenActive(vm.tokens[0], true)
assert.equal(vm.tokens[0].is_active, false, 'An inactive key stays inactive while the API is off')

vm.tokenToDelete = vm.tokens[0]
assert.match(await renderDisabledTable(vm.tokens, vm.tokenToDelete), /apiTokens\.deleteMessage\(Existing key\)/, 'Confirmation names the chosen key without opening an editor')
vm.closeDeleteConfirmation()
await vm.revokeToken()
assert.equal(vm.tokens.length, 1, 'Cancelling the confirmation keeps the key')

vm.tokenToDelete = vm.tokens[0]
nextDeleteError = new Error('Could not delete')
await vm.revokeToken()
assert.ok(vm.tableError, 'A failed deletion is reported alongside the retained key')
assert.equal(vm.tokens.length, 1)
vm.tokenToDelete = vm.tokens[0]
await vm.revokeToken()
assert.equal(vm.tokens.length, 0)
assert.equal(vm.tableError, '')
assert.doesNotMatch(await renderDisabledTable(vm.tokens), /api-token-management/)
assert.deepEqual(requests.slice(disabledRequestStart).map(([method, url]) => [method, url]), [
	['GET', '/apps/cobudget/api/settings/api-tokens'],
	['PUT', '/apps/cobudget/api/settings/api-tokens/5'],
	['DELETE', '/apps/cobudget/api/settings/api-tokens/5'],
	['DELETE', '/apps/cobudget/api/settings/api-tokens/5'],
], 'Listing, pausing and revoking leave the master switch untouched')
assert.equal(requests[disabledRequestStart + 1][2].isActive, false)
assert.equal(vm.enabled, false)

const legacyKey = {
	...catalogTokens[0],
	allow_personal: true,
	project_mode: 'all',
	scopes: ['entries:read'],
	target_permissions: { personal: ['read'], 10: ['read'] },
}
vm.tokens = [legacyKey]
await vm.toggleTokenActive(legacyKey, false)
assert.deepEqual(requests.at(-1)[2], {
	name: 'Existing key', scopes: ['entries:read'], allowPersonal: true,
	projectMode: 'all', projectIds: [], expiresAt: null, isActive: false, revision: legacyKey.revision,
}, 'Pausing an older key keeps its stored access mode and scopes')
vm.tokens = []
vm.enabled = true
catalogTokens = []

// Endpoints preview strictly filters full endpoint URLs to each key's granted permissions
globalThis.window = { location: { origin: 'https://cloud.example.test' } }
const flattenEndpoints = token => vm.endpointsForToken(token).flatMap(group => group.endpoints.map(ep => `${ep.method} ${ep.url}`))

const personalReadToken = {
	id: 41,
	name: 'Read Only',
	workspace_id: 1,
	token_prefix: 'cb_11112222',
	scopes: ['entries:read'],
	target_permissions: { personal: ['read'] },
	is_active: true,
	is_expired: false,
}
assert.deepEqual(flattenEndpoints(personalReadToken), [
	'GET https://cloud.example.test/apps/cobudget/api/v1/me',
	'GET https://cloud.example.test/apps/cobudget/api/v1/categories',
	'GET https://cloud.example.test/apps/cobudget/api/v1/payment-partners',
	'GET https://cloud.example.test/apps/cobudget/api/v1/entries',
	'GET https://cloud.example.test/apps/cobudget/api/v1/entries/{id}',
], 'Personal read-only token exposes only /me, personal lookups, and GET /entries endpoints')

const areaCreateDeleteToken = {
	id: 42,
	name: 'Area Create & Delete',
	workspace_id: 1,
	token_prefix: 'cb_33334444',
	scopes: ['entries:create', 'entries:delete'],
	target_permissions: { 10: ['create', 'delete'] },
	is_active: true,
	is_expired: false,
}
assert.deepEqual(flattenEndpoints(areaCreateDeleteToken), [
	'GET https://cloud.example.test/apps/cobudget/api/v1/me',
	'GET https://cloud.example.test/apps/cobudget/api/v1/areas',
	'GET https://cloud.example.test/apps/cobudget/api/v1/categories?project_id={id}',
	'GET https://cloud.example.test/apps/cobudget/api/v1/payment-partners?project_id={id}',
	'POST https://cloud.example.test/apps/cobudget/api/v1/entries',
	'DELETE https://cloud.example.test/apps/cobudget/api/v1/entries/{id}',
], 'Area create+delete token shows /areas, ?project_id={id} lookups, POST, and DELETE while omitting GET /entries, PATCH, reports, and settings')

const fullReportsSettingsWriteToken = {
	id: 43,
	name: 'Reports & Settings Write',
	workspace_id: 1,
	token_prefix: 'cb_55556666',
	scopes: ['entries:read', 'reports:read', 'settings:write'],
	target_permissions: { personal: ['read'], 10: ['read'] },
	is_active: true,
	is_expired: false,
}
assert.deepEqual(flattenEndpoints(fullReportsSettingsWriteToken), [
	'GET https://cloud.example.test/apps/cobudget/api/v1/me',
	'GET https://cloud.example.test/apps/cobudget/api/v1/areas',
	'GET https://cloud.example.test/apps/cobudget/api/v1/categories',
	'GET https://cloud.example.test/apps/cobudget/api/v1/payment-partners',
	'GET https://cloud.example.test/apps/cobudget/api/v1/entries',
	'GET https://cloud.example.test/apps/cobudget/api/v1/entries/{id}',
	'GET https://cloud.example.test/apps/cobudget/api/v1/reports/summary',
	'GET https://cloud.example.test/apps/cobudget/api/v1/reports/annual',
	'GET https://cloud.example.test/apps/cobudget/api/v1/budgets',
	'PATCH https://cloud.example.test/apps/cobudget/api/v1/settings',
], 'Token with full read + reports:read + settings:write exposes reports, budgets, and PATCH /settings while hiding GET /settings and DELETE')

// Opening/closing preview tracks token selection cleanly and drops reports if a new ungranted active area exists
vm.areasByWorkspace[1].push({ id: 12, name: 'Travel', color: '#e67700', is_archived: false })
assert.ok(
	!flattenEndpoints(fullReportsSettingsWriteToken).some(line => line.includes('/reports/') || line.includes('/budgets')),
	'Reports and budgets disappear from preview when workspace gains an active area without read permission',
)
vm.tokens = [areaCreateDeleteToken]
vm.workspacesEnabled = false
vm.openEndpointsPreview(areaCreateDeleteToken)
assert.equal(vm.previewToken.id, 42)
assert.ok(vm.previewEndpointGroups.some(group => group.key === 'payments'))

// Parameter metadata accurately describes supported query/path/JSON-body fields and toggles cleanly
const areaEndpoints = vm.endpointsForToken(areaCreateDeleteToken).flatMap(group => group.endpoints)
const areaCategoriesEp = areaEndpoints.find(ep => ep.url.includes('/categories'))
assert.equal(areaCategoriesEp.parameters[0].name, 'project_id')
assert.equal(areaCategoriesEp.parameters[0].type, 'int', 'Lookup project_id documents integer type only')
assert.equal(areaCategoriesEp.parameters[0].required, true, 'Area-only token marks project_id as required on /categories')
assert.equal(areaCategoriesEp.parameters[0].valuesStacked, true, 'Lookup project_id stacks area IDs cleanly')
assert.deepEqual(areaCategoriesEp.parameters[0].values, [{ value: '10', label: 'Household' }], 'Lookup project_id lists permitted area ID and name in Allowed values')
const areaPostEp = areaEndpoints.find(ep => ep.method === 'POST')
assert.equal(areaPostEp.paramToggleLabel, 'apiTokens.endpointBodyToggle()', 'POST /entries labels toggle as JSON body')
assert.equal(areaPostEp.parameters.find(p => p.name === 'date').required, true)
assert.equal(areaPostEp.parameters.find(p => p.name === 'date').type, 'string | int')
assert.equal(areaPostEp.parameters.find(p => p.name === 'amount').required, true)
assert.equal(areaPostEp.parameters.find(p => p.name === 'project_id').required, true, 'Area-only create token marks JSON project_id as required')
assert.equal(areaPostEp.parameters.find(p => p.name === 'project_id').type, 'int', 'Area-only create token marks JSON project_id type as int')
assert.deepEqual(areaPostEp.parameters.find(p => p.name === 'project_id').values, [{ value: '10', label: 'Household' }], 'POST /entries project_id lists only create-permitted areas')
assert.deepEqual(areaPostEp.parameters.find(p => p.name === 'important').values, [
	{ value: '0', label: '' },
	{ value: '1', label: '' },
	{ value: 'true', label: '' },
	{ value: 'false', label: '' },
], 'Label fields list allowed boolean/binary values')
assert.equal(vm.hasParamValues(areaPostEp), true)
assert.equal(vm.hasParamValues(areaEndpoints.find(ep => ep.method === 'DELETE')), false)
for (const name of ['category', 'payment_partner', 'description', 'important', 'review', 'fixed_cost', 'children', 'subscription', 'tax_relevant', 'insurance', 'recurrence_interval']) {
	assert.ok(areaPostEp.parameters.some(p => p.name === name), `POST /entries exposes JSON-import-aligned parameter ${name}`)
}
assert.equal(areaPostEp.parameters.some(p => p.name.includes('is_subscription')), false, 'Legacy combined label string is replaced by individual JSON-import label fields')

const reportsSettingsEndpoints = vm.endpointsForToken(fullReportsSettingsWriteToken).flatMap(group => group.endpoints)
assert.equal(reportsSettingsEndpoints.find(ep => ep.url.endsWith('/me')).parameters.length, 0, '/me has no request parameters')
const fullEntriesEp = reportsSettingsEndpoints.find(ep => ep.url.endsWith('/entries'))
assert.equal(vm.hasParamValues(fullEntriesEp), true)
assert.deepEqual(
	fullEntriesEp.parameters.find(p => p.name === 'project_id').values,
	[{ value: '0', label: 'masterData.noArea()' }, { value: '10', label: 'Household' }],
	'GET /entries project_id Allowed values lists 0 (no area) plus active area names and IDs',
)
assert.deepEqual(fullEntriesEp.parameters.find(p => p.name === 'scope').values, [
	{ value: 'canonical', label: '' },
	{ value: 'personal', label: '' },
], 'GET /entries scope Allowed values lists canonical and personal')
assert.ok(fullEntriesEp.parameters.some(p => p.name === 'scope'))
assert.equal(fullEntriesEp.parameters.find(p => p.name === 'project_id').type, 'int', 'GET /entries project_id documents int only (0 or area ID)')
assert.equal(reportsSettingsEndpoints.find(ep => ep.method === 'PATCH' && ep.url.endsWith('/settings')).paramToggleLabel, 'apiTokens.endpointBodyToggle()')

const postKey = areaPostEp.key
assert.equal(vm.isEndpointExpanded(postKey), false)
assert.equal(vm.isEndpointExampleExpanded(postKey), false)
vm.toggleEndpointParams(postKey)
assert.equal(vm.isEndpointExpanded(postKey), true)
assert.equal(vm.isEndpointExampleExpanded(postKey), false)
vm.toggleEndpointExample(postKey)
assert.equal(vm.isEndpointExpanded(postKey), false, 'Opening curl example collapses parameters in the same box')
assert.equal(vm.isEndpointExampleExpanded(postKey), true)
vm.toggleEndpointParams(postKey)
assert.equal(vm.isEndpointExpanded(postKey), true, 'Opening parameters collapses curl example in the same box')
assert.equal(vm.isEndpointExampleExpanded(postKey), false)
vm.toggleEndpointParams(postKey)
assert.equal(vm.isEndpointExpanded(postKey), false)

vm.workspacesEnabled = false
vm.openEndpointsPreview(areaCreateDeleteToken)
assert.deepEqual(
	component.computed.previewAuthHeaderRows.call(vm).map(row => [row.name, row.recommended]),
	[['Authorization', true], ['X-CoBudget-Token', false]],
	'Authentication table lists Authorization (recommended) and X-CoBudget-Token (alternative) in single-workspace mode',
)
const postCurl = vm.curlExampleForEndpoint(areaPostEp)
assert.match(postCurl, /^curl -X POST 'https:\/\/cloud\.example\.test\/apps\/cobudget\/api\/v1\/entries'/, 'POST curl targets /entries')
assert.match(postCurl, /Authorization: Bearer <API_KEY>/, 'curl example uses placeholder <API_KEY> rather than a real secret')
assert.match(postCurl, /"project_id": 10/, 'POST curl picks permitted area project_id=10')
assert.doesNotMatch(postCurl, /X-Workspace-Id/, 'curl omits workspace header when single-workspace mode is active')

const categoriesEp = areaEndpoints.find(ep => ep.url.includes('/categories'))
assert.match(vm.curlExampleForEndpoint(categoriesEp), /\/categories\?project_id=10'/, 'Categories curl resolves required project_id placeholder to permitted area ID 10')

downloadedFiles.length = 0
vm.downloadOpenApiSpec()
vm.downloadHttpFile()
assert.equal(downloadedFiles.length, 2, 'Export actions download both OpenAPI JSON and .http files')

const [openApiDownload, httpDownload] = downloadedFiles
assert.equal(openApiDownload.filename, 'cobudget-api-area-create-delete.json')
assert.equal(openApiDownload.type, 'application/json;charset=utf-8')
const spec = JSON.parse(openApiDownload.contents)
assert.equal(spec.openapi, '3.0.3')
assert.deepEqual(spec.components.securitySchemes.bearerAuth, {
	type: 'http',
	scheme: 'bearer',
	description: 'Bearer token authentication (<API_KEY>). Never commit real API keys into exported files.',
})
assert.ok(spec.paths['/areas']?.get, 'OpenAPI includes GET /areas for area-scoped token')
assert.ok(spec.paths['/categories']?.get, 'OpenAPI exposes /categories path without raw query suffix')
assert.deepEqual(spec.paths['/categories'].get.parameters.find(p => p.name === 'project_id').schema.enum, [10])
assert.ok(spec.paths['/entries']?.post, 'OpenAPI includes POST /entries for area-scoped create permission')
assert.equal(spec.paths['/entries']?.get, undefined, 'OpenAPI excludes GET /entries when token lacks read permission')
assert.deepEqual(
	spec.paths['/entries'].post.requestBody.content['application/json'].schema.properties.project_id.enum,
	[10],
	'OpenAPI POST /entries schema limits project_id enum to permitted targets',
)
assert.ok(spec.paths['/entries/{id}']?.delete, 'OpenAPI includes DELETE /entries/{id} for delete permission')
assert.equal(spec.paths['/entries/{id}']?.patch, undefined, 'OpenAPI excludes PATCH /entries/{id} when token lacks write permission')
assert.equal(spec.paths['/reports/summary'], undefined, 'OpenAPI excludes /reports/summary when token lacks reports:read')
assert.equal(spec.paths['/settings'], undefined, 'OpenAPI excludes /settings when token lacks settings scopes')

for (const method of ['PATCH', 'DELETE']) {
	const example = buildCurlExample({ method, url: 'https://cloud.example.test/apps/cobudget/api/v1/entries/{id}' })
	assert.match(example, /entries\/REPLACE_WITH_ENTRY_ID/, `${method} curl example requires an explicit payment ID`)
}
assert.match(httpDownload.contents, /DELETE \{\{baseUrl\}\}\/entries\/REPLACE_WITH_ENTRY_ID/, 'The .http file requires an explicit payment ID for deletion')
assert.equal(httpDownload.filename, 'cobudget-api-area-create-delete.http')
assert.match(httpDownload.contents, /@apiKey = <API_KEY>/, '.http export uses placeholder <API_KEY>')
assert.match(httpDownload.contents, /GET \{\{baseUrl\}\}\/categories\?project_id=10/, '.http resolves required lookup query parameter to permitted target')
assert.match(httpDownload.contents, /POST \{\{baseUrl\}\}\/entries[\s\S]*"project_id": 10/, '.http includes valid POST /entries JSON payload')
assert.doesNotMatch(httpDownload.contents, /\/reports\/summary|\/settings|\bPATCH\b/, '.http export excludes endpoints not permitted for this token')

vm.workspacesEnabled = true
assert.deepEqual(
	component.computed.previewAuthHeaderRows.call(vm).map(row => row.name),
	['Authorization', 'X-CoBudget-Token', 'X-Workspace-Id'],
	'Authentication table appends optional X-Workspace-Id header in multi-workspace mode',
)
const multiWsSpec = buildOpenApiSpec({
	...vm.apiExampleContext(),
	version: 'v1',
	groups: vm.previewEndpointGroups,
})
assert.ok(multiWsSpec.paths['/me'].get.parameters.some(p => p.name === 'X-Workspace-Id' && p.in === 'header' && p.schema.default === 1))
assert.match(vm.curlExampleForEndpoint(areaPostEp), /X-Workspace-Id: 1/)
assert.match(buildHttpFile({ ...vm.apiExampleContext(), groups: vm.previewEndpointGroups }), /@workspaceId = 1[\s\S]*X-Workspace-Id: \{\{workspaceId\}\}/)
vm.workspacesEnabled = false

vm.closeEndpointsPreview()
assert.equal(vm.previewToken, null)
assert.equal(vm.isEndpointExpanded(postKey), false, 'Closing preview resets expanded parameter dropdown state')
assert.equal(vm.isEndpointExampleExpanded(postKey), false, 'Closing preview resets expanded curl example dropdown state')

// Password confirmation prompt intercepts 403 X-NC-Auth-NotConfirmed, submits /login/confirm, and retries create
nextMutationNeedsPasswordConfirm = true
vm.openEditor()
vm.editor.name = 'Sudo Confirmed Key'
const savePromise = vm.save()
await Promise.resolve()
await Promise.resolve()
assert.equal(vm.passwordConfirm.open, true, 'Password confirmation dialog opens on 403 X-NC-Auth-NotConfirmed')
vm.passwordConfirm.password = 'wrong-pass'
await vm.submitPasswordConfirmation()
assert.equal(vm.passwordConfirm.open, true, 'Incorrect password keeps confirmation dialog open')
assert.equal(vm.passwordConfirm.password, '', 'Incorrect password input is cleared immediately')
assert.equal(vm.passwordConfirm.error, 'apiTokens.passwordConfirmInvalid()')
vm.passwordConfirm.password = 'correct-pass'
await vm.submitPasswordConfirmation()
await savePromise
assert.equal(vm.passwordConfirm.open, false)
assert.equal(vm.tokens[0].name, 'Sudo Confirmed Key', 'Save retries automatically after sudo password confirmation')
assert.ok(requests.some(([method, url]) => method === 'POST' && url === '/login/confirm'), 'Submits password to /login/confirm')

// beforeUnmount clears plaintext secret and password state
globalThis.window = { ...globalThis.window, removeEventListener() {} }
vm.passwordConfirm.password = 'temp-secret'
component.beforeUnmount.call(vm)
assert.equal(vm.revealedToken, null, 'beforeUnmount clears plaintext revealedToken')
assert.equal(vm.passwordConfirm.password, '', 'beforeUnmount clears passwordConfirm password')

// Admin-disabled API renders an info note instead of the activation switch and locks key creation/activation.
const adminDisabledEmptyHtml = await renderDisabledTable([], null, { adminEnabled: false, adminLoaded: true, enabled: false })
assert.match(adminDisabledEmptyHtml, /data-note-type="info"[^>]*>apiTokens\.adminDisabledNotice\(\)/, 'Shows info notice when administrator disabled API access')
assert.doesNotMatch(adminDisabledEmptyHtml, /data-master-switch="true"/, 'Hides user activation switch when administrator disabled API access')
assert.doesNotMatch(adminDisabledEmptyHtml, /api-token-management/, 'Hides key management section when admin disabled API and no keys exist')

const existingKey = { id: 9, name: 'Legacy Key', revision: 'rev-1', workspace_id: 1, is_active: true, is_expired: false, project_mode: 'matrix', target_permissions: { personal: ['read'] } }
const adminDisabledWithKeyHtml = await renderDisabledTable([existingKey], null, { adminEnabled: false, adminLoaded: true, enabled: true })
assert.match(adminDisabledWithKeyHtml, /data-note-type="info"[^>]*>apiTokens\.adminDisabledNotice\(\)/)
assert.doesNotMatch(adminDisabledWithKeyHtml, /data-master-switch="true"/)
assert.match(adminDisabledWithKeyHtml, /Legacy Key/, 'Existing keys remain visible for pausing or deleting')
assert.doesNotMatch(adminDisabledWithKeyHtml, /apiTokens\.addKey\(|common\.edit\(|apiTokens\.endpointsPreviewTitle\(/, 'Disallows adding, editing, or previewing endpoints when admin disabled API')
assert.match(adminDisabledWithKeyHtml, /aria-label="common\.delete\(\): Legacy Key"/, 'Allows deleting existing key when admin disabled API')

vm.enabled = true
vm.adminEnabled = true
vm.adminLoaded = true
vm.tokens = [existingKey]
vm.openEditor(existingKey)
vm.openEndpointsPreview(existingKey)
assert.ok(vm.editor)
assert.ok(vm.previewToken)
vm.adminEnabled = false
component.watch.adminEnabled.call(vm, false)
assert.equal(vm.effectiveEnabled, false)
assert.equal(vm.editor, null, 'Turning adminEnabled off closes any open key editor')
assert.equal(vm.previewTokenId, null, 'Turning adminEnabled off closes any open endpoint preview')
vm.openEditor()
assert.equal(vm.editor, null, 'Cannot open key editor when adminEnabled is false')
const reqsBeforeAdminBlockedToggle = requests.length
await vm.toggleTokenActive({ ...existingKey, is_active: false }, true)
assert.equal(requests.length, reqsBeforeAdminBlockedToggle, 'Cannot reactivate a paused key while adminEnabled is false')
vm.adminEnabled = true

console.log('ApiTokenSettings tests passed: spacing CSS, workspace fallback, Category-style data access switches, reports:read restriction, secret clearing, password confirmation, edit, disabled-mode pause and revoke, admin-disabled notice, endpoint preview, curl examples, OpenAPI export, and .http export.')
