import axios from '@nextcloud/axios'

axios.interceptors.request.use(config => {
	const skipWorkspaceHeader = config.skipWorkspaceHeader === true;
	delete config.skipWorkspaceHeader;

	config.headers = config.headers || {};
	const workspaceId = localStorage.getItem('cobudget_workspace_id');
	if (!skipWorkspaceHeader && workspaceId) {
		config.headers['X-Workspace-Id'] = workspaceId;
	}

	return config;
});

export default axios
