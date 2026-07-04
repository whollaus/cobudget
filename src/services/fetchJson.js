import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

const APP_ID = 'cobudget'

const buildUrl = (path, params = null) => {
	const url = new URL(generateUrl(path), window.location.origin);
	if (params && typeof params === 'object') {
		Object.entries(params).forEach(([key, value]) => {
			if (value !== undefined && value !== null) {
				url.searchParams.set(key, value);
			}
		});
	}
	return url.toString();
}

export async function fetchJson(path, options = {}) {
	const {
		params = null,
		skipWorkspaceHeader = false,
		headers = {},
		...fetchOptions
	} = options;

	const requestHeaders = {
		Accept: 'application/json',
		'X-Requested-With': 'XMLHttpRequest',
		...headers
	};

	const requestToken = window.OC?.requestToken || window.oc_requesttoken;
	if (requestToken && !requestHeaders.requesttoken) {
		requestHeaders.requesttoken = requestToken;
	}

	const workspaceId = localStorage.getItem('cobudget_workspace_id');
	if (!skipWorkspaceHeader && workspaceId) {
		requestHeaders['X-Workspace-Id'] = workspaceId;
	}

	const response = await fetch(buildUrl(path, params), {
		credentials: 'same-origin',
		...fetchOptions,
		headers: requestHeaders
	});

	if (!response.ok) {
		throw new Error(t(APP_ID, 'Request failed with status {status}', { status: response.status }));
	}

	const contentType = response.headers.get('content-type') || '';
	if (!contentType.includes('application/json')) {
		throw new Error(t(APP_ID, 'Request did not return JSON'));
	}

	return response.json();
}
