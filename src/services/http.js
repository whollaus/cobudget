import axios from '@nextcloud/axios'
import { readWorkspaceId } from './workspaceStorage'

function removeHeader(headers, name) {
	if (!headers) {
		return;
	}

	if (typeof headers.delete === 'function') {
		headers.delete(name);
	}

	delete headers[name];
	delete headers[name.toLowerCase()];
}

function setHeader(headers, name, value) {
	if (typeof headers.set === 'function') {
		headers.set(name, value);
		return;
	}

	headers[name] = value;
}

axios.interceptors.request.use(config => {
	const skipWorkspaceHeader = config.skipWorkspaceHeader === true;
	delete config.skipWorkspaceHeader;

	config.headers = config.headers || {};
	removeHeader(config.headers, 'X-Workspace-Id');
	removeHeader(config.headers, 'x-workspace-id');

	const workspaceId = readWorkspaceId();
	if (!skipWorkspaceHeader && workspaceId) {
		setHeader(config.headers, 'X-Workspace-Id', workspaceId);
	}

	// Server-side calendar totals must use the same timezone as displayed dates.
	removeHeader(config.headers, 'X-CoBudget-Timezone');
	try {
		const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
		if (timezone) {
			setHeader(config.headers, 'X-CoBudget-Timezone', timezone);
		}
	} catch {
		// Clients without timezone support retain the server's default timezone.
	}

	return config;
});

export default axios
