export const API_KEY_PLACEHOLDER = '<API_KEY>'

/**
 * Sanitize a token name into a safe export filename like `cobudget-api-ci-bot.json`.
 */
export function buildApiExportFilename(tokenName, extension) {
	const cleanExt = String(extension || 'txt').replace(/^\.+/, '').toLowerCase() || 'txt'
	const normalized = String(tokenName || '')
		.normalize('NFKD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '')
		.slice(0, 48)
	return normalized ? `cobudget-api-${normalized}.${cleanExt}` : `cobudget-api.${cleanExt}`
}

function splitUrl(rawUrl) {
	const value = String(rawUrl || '')
	const queryIndex = value.indexOf('?')
	if (queryIndex === -1) {
		return { baseUrl: value, query: '' }
	}
	return {
		baseUrl: value.slice(0, queryIndex),
		query: value.slice(queryIndex + 1),
	}
}

function pickFirstProjectId(endpoint) {
	const projectParam = (endpoint?.parameters || []).find(param => param?.name === 'project_id')
	const firstValue = projectParam?.values?.[0]?.value
	return firstValue !== undefined && firstValue !== null && String(firstValue) !== ''
		? String(firstValue)
		: '0'
}

/**
 * Resolve URL placeholders (`{id}` path IDs and required `?project_id={id}` query filters)
 * for copyable/importable `curl` and `.http` examples.
 */
export function resolveExampleUrl(endpoint) {
	const { baseUrl, query } = splitUrl(endpoint?.url)
	const method = String(endpoint?.method || 'GET').toUpperCase()
	const entryId = method === 'GET' ? '1' : 'REPLACE_WITH_ENTRY_ID'
	const resolvedBase = baseUrl.replace(/\{id\}/g, entryId)
	if (!query) return resolvedBase
	const firstProjectId = pickFirstProjectId(endpoint)
	const resolvedQuery = query.replace(/\{id\}/g, firstProjectId)
	return `${resolvedBase}?${resolvedQuery}`
}

/**
 * Build a minimal valid JSON request payload for writable endpoints using only allowed values.
 */
export function buildExampleRequestBody(endpoint, options = {}) {
	const method = String(endpoint?.method || '').toUpperCase()
	if (method !== 'POST' && method !== 'PATCH' && method !== 'PUT') return null

	const bodyParams = (endpoint?.parameters || []).filter(param => param?.location === 'body')
	if (!bodyParams.length) return null

	const { baseUrl } = splitUrl(endpoint?.url)
	const today = String(options.today || '2026-11-26')
	const exampleDescription = String(options.exampleDescription || 'Weekly shopping')
	const exampleCategory = String(options.exampleCategory || 'Groceries')
	const examplePartner = String(options.examplePartner || 'Supermarket')

	if (baseUrl.endsWith('/settings')) {
		return {
			entries_per_page: 50,
		}
	}

	if (method === 'POST' && baseUrl.endsWith('/entries')) {
		const payload = {
			date: today,
			amount: -12.5,
		}
		const projectParam = bodyParams.find(param => param.name === 'project_id')
		if (projectParam && Array.isArray(projectParam.values) && projectParam.values.length > 0) {
			const firstProjectId = Number(projectParam.values[0].value)
			if (Number.isInteger(firstProjectId)) {
				payload.project_id = firstProjectId
			}
		}
		payload.category = exampleCategory
		payload.payment_partner = examplePartner
		payload.description = exampleDescription
		return payload
	}

	if (method === 'PATCH' && /\/entries\/\{id\}$/.test(baseUrl)) {
		return {
			amount: -15.0,
			description: exampleDescription,
		}
	}

	return {}
}

function shellEscapeSingleQuoted(text) {
	return String(text).replace(/'/g, `'\\''`)
}

/**
 * Render a ready-to-paste `curl` command using `<API_KEY>` (never a real secret).
 */
export function buildCurlExample(endpoint, options = {}) {
	if (!endpoint) return ''
	const method = String(endpoint.method || 'GET').toUpperCase()
	const url = resolveExampleUrl(endpoint)
	const lines = [
		`curl -X ${method} '${shellEscapeSingleQuoted(url)}'`,
		`  -H 'Authorization: Bearer ${API_KEY_PLACEHOLDER}'`,
		`  -H 'Accept: application/json'`,
	]
	if (options.workspacesEnabled && options.workspaceId != null) {
		lines.push(`  -H 'X-Workspace-Id: ${Number(options.workspaceId)}'`)
	}
	const body = buildExampleRequestBody(endpoint, options)
	if (body !== null) {
		lines.push(`  -H 'Content-Type: application/json'`)
		lines.push(`  -d '${shellEscapeSingleQuoted(JSON.stringify(body, null, 2))}'`)
	}
	return lines.join(' \\\n')
}

function extractServerBaseUrl(groups) {
	for (const group of groups || []) {
		for (const endpoint of group?.endpoints || []) {
			const { baseUrl } = splitUrl(endpoint?.url)
			const marker = '/api/v1'
			const idx = baseUrl.indexOf(marker)
			if (idx !== -1) {
				return baseUrl.slice(0, idx + marker.length)
			}
		}
	}
	return '/apps/cobudget/api/v1'
}

function toRelativeApiPath(rawUrl, serverBaseUrl) {
	const { baseUrl, query } = splitUrl(rawUrl)
	const path = baseUrl.startsWith(serverBaseUrl)
		? (baseUrl.slice(serverBaseUrl.length) || '/')
		: baseUrl
	return { path, query }
}

function buildOpenApiOperationId(method, path) {
	const clean = path
		.replace(/^\/+/, '')
		.replace(/\{([^}]+)\}/g, 'by_$1')
		.split(/[^a-zA-Z0-9]+/)
		.filter(Boolean)
		.map((part, index) => (index === 0 ? part.toLowerCase() : part.charAt(0).toUpperCase() + part.slice(1).toLowerCase()))
		.join('')
	return `${String(method || 'get').toLowerCase()}${clean ? clean.charAt(0).toUpperCase() + clean.slice(1) : 'Root'}`
}

function buildOpenApiSchemaForParam(param) {
	const rawType = String(param?.type || 'string').trim()
	const nullable = rawType.startsWith('?')
	const baseType = rawType.replace(/^\?/, '')

	let schema
	if (baseType === 'int') {
		schema = { type: 'integer' }
	} else if (baseType === 'number') {
		schema = { type: 'number' }
	} else if (baseType === 'bool') {
		schema = { type: 'boolean' }
	} else if (baseType === '0 / 1 / bool') {
		schema = { oneOf: [{ type: 'boolean' }, { type: 'integer', enum: [0, 1] }] }
	} else if (rawType.includes('|')) {
		const parts = rawType.split('|').map(part => part.trim().replace(/^\?/, ''))
		schema = {
			oneOf: parts.map(part => ({
				type: part === 'int' ? 'integer' : (part === 'number' ? 'number' : (part === 'bool' ? 'boolean' : 'string')),
			})),
		}
	} else {
		schema = { type: 'string' }
	}

	if (nullable && !schema.oneOf) {
		schema.nullable = true
	}

	const values = Array.isArray(param?.values) ? param.values.map(item => String(item?.value ?? '')).filter(Boolean) : []
	if (values.length === 1 && /^\d+[–-]\d+$/.test(values[0])) {
		const [minStr, maxStr] = values[0].split(/[–-]/)
		if (schema.type === 'integer' || schema.type === 'number') {
			schema.minimum = Number(minStr)
			schema.maximum = Number(maxStr)
		}
	} else if (values.length > 0 && baseType !== '0 / 1 / bool' && baseType !== 'bool' && !values.some(val => val.includes('<id>'))) {
		if (schema.type === 'integer') {
			const intValues = values.map(val => Number(val)).filter(Number.isInteger)
			if (intValues.length) schema.enum = intValues
		} else if (schema.type === 'string') {
			const stringValues = values.filter(val => val !== 'null')
			if (stringValues.length) schema.enum = stringValues
		}
	}

	return schema
}

/**
 * Generate a permission-scoped OpenAPI 3.0.3 JSON specification for the inspected token.
 */
export function buildOpenApiSpec(options = {}) {
	const groups = Array.isArray(options.groups) ? options.groups : []
	const tokenName = String(options.tokenName || '').trim()
	const serverBaseUrl = extractServerBaseUrl(groups)
	const paths = {}

	for (const group of groups) {
		for (const endpoint of group?.endpoints || []) {
			const methodLower = String(endpoint?.method || 'get').toLowerCase()
			const { path } = toRelativeApiPath(endpoint?.url, serverBaseUrl)
			if (!paths[path]) paths[path] = {}

			const nonBodyParams = (endpoint?.parameters || [])
				.filter(param => param?.location === 'path' || param?.location === 'query')
				.map(param => ({
					name: param.name,
					in: param.location,
					required: !!param.required,
					description: param.description || '',
					schema: buildOpenApiSchemaForParam(param),
				}))

			if (options.workspacesEnabled && options.workspaceId != null) {
				nonBodyParams.push({
					name: 'X-Workspace-Id',
					in: 'header',
					required: false,
					description: 'Target CoBudget workspace ID.',
					schema: {
						type: 'integer',
						default: Number(options.workspaceId),
					},
				})
			}

			const operation = {
				tags: [group?.title || group?.key || 'CoBudget'],
				operationId: buildOpenApiOperationId(methodLower, path),
				summary: endpoint?.description || `${methodLower.toUpperCase()} ${path}`,
				security: [{ bearerAuth: [] }],
				...(nonBodyParams.length ? { parameters: nonBodyParams } : {}),
				responses: {
					...(methodLower === 'post' ? { 201: { description: 'Created' } } : { 200: { description: 'Successful response' } }),
					401: { description: 'Unauthorized (missing or invalid API token)' },
					403: { description: 'Forbidden for this API token scope' },
				},
			}

			if (endpoint?.note) {
				operation.description = endpoint.note
			}

			const bodyParams = (endpoint?.parameters || []).filter(param => param?.location === 'body')
			if (bodyParams.length) {
				const properties = {}
				const requiredProps = []
				let hasWildcardSettings = false
				for (const param of bodyParams) {
					if (String(param.name).includes('*')) {
						hasWildcardSettings = true
						continue
					}
					properties[param.name] = {
						...buildOpenApiSchemaForParam(param),
						description: param.description || '',
					}
					if (param.required) {
						requiredProps.push(param.name)
					}
				}
				const schema = {
					type: 'object',
					properties,
					...(requiredProps.length ? { required: requiredProps } : {}),
					...(hasWildcardSettings ? { additionalProperties: { type: 'boolean' } } : {}),
				}
				const exampleBody = buildExampleRequestBody(endpoint, options)
				operation.requestBody = {
					required: true,
					content: {
						'application/json': {
							schema,
							...(exampleBody !== null ? { example: exampleBody } : {}),
						},
					},
				}
			}

			paths[path][methodLower] = operation
		}
	}

	return {
		openapi: '3.0.3',
		info: {
			title: tokenName ? `CoBudget API (${tokenName})` : 'CoBudget API',
			version: String(options.version || '1.0.0'),
			description: 'Permission-scoped CoBudget API specification generated for this token. Replace <API_KEY> with your bearer token before importing requests.',
		},
		servers: [
			{
				url: serverBaseUrl,
			},
		],
		security: [
			{
				bearerAuth: [],
			},
		],
		components: {
			securitySchemes: {
				bearerAuth: {
					type: 'http',
					scheme: 'bearer',
					description: `Bearer token authentication (${API_KEY_PLACEHOLDER}). Never commit real API keys into exported files.`,
				},
			},
		},
		paths,
	}
}

/**
 * Generate a permission-scoped `.http` file compatible with VS Code REST Client and JetBrains HTTP Client.
 */
export function buildHttpFile(options = {}) {
	const groups = Array.isArray(options.groups) ? options.groups : []
	const tokenName = String(options.tokenName || '').trim()
	const serverBaseUrl = extractServerBaseUrl(groups)
	const lines = [
		tokenName ? `# CoBudget API (${tokenName})` : '# CoBudget API',
		`# Replace ${API_KEY_PLACEHOLDER} with your bearer token before running requests.`,
		`@baseUrl = ${serverBaseUrl}`,
		`@apiKey = ${API_KEY_PLACEHOLDER}`,
	]
	if (options.workspacesEnabled && options.workspaceId != null) {
		lines.push(`@workspaceId = ${Number(options.workspaceId)}`)
	}
	lines.push('')

	for (const group of groups) {
		for (const endpoint of group?.endpoints || []) {
			const method = String(endpoint?.method || 'GET').toUpperCase()
			const resolvedFullUrl = resolveExampleUrl(endpoint)
			const { path, query } = toRelativeApiPath(resolvedFullUrl, serverBaseUrl)
			const relTarget = query ? `${path}?${query}` : path

			lines.push(`### ${group?.title || group?.key || 'API'} - ${method} ${path}`)
			if (endpoint?.description) {
				lines.push(`# ${endpoint.description}`)
			}
			lines.push(`${method} {{baseUrl}}${relTarget}`)
			lines.push('Authorization: Bearer {{apiKey}}')
			lines.push('Accept: application/json')
			if (options.workspacesEnabled && options.workspaceId != null) {
				lines.push('X-Workspace-Id: {{workspaceId}}')
			}
			const body = buildExampleRequestBody(endpoint, options)
			if (body !== null) {
				lines.push('Content-Type: application/json')
				lines.push('')
				lines.push(JSON.stringify(body, null, 2))
			}
			lines.push('')
		}
	}

	return lines.join('\n')
}
