<template>
	<div class="workspace-settings">
		<div v-if="loading" class="loading-state">
			<NcLoadingIcon /> {{ $texts.workspaces.loading() }}
		</div>
		<div v-else>
			<form class="add-form" @submit.prevent="createWorkspace">
				<input type="text" class="form-control" v-model="newWorkspaceName" :placeholder="$texts.workspaces.newWorkspace()" required />
				<NcButton type="primary" native-type="submit" :disabled="!newWorkspaceName.trim()">{{ $texts.workspaces.add() }}</NcButton>
			</form>

			<SettingsList :items="workspaces" :empty-text="$texts.workspaces.empty()">
				<template #item="{ item: workspace }">
					<div class="settings-list-info workspace-info">
						<strong>{{ workspace.name }}</strong>
						<TableTooltip
							v-if="workspace.is_default"
							:text="$texts.workspaces.baseTooltip()">
							<span class="default-badge">{{ $texts.workspaces.base() }}</span>
						</TableTooltip>
						<span v-if="isActiveWorkspace(workspace)" class="active-badge">{{ $texts.workspaces.active() }}</span>
					</div>
					<button
						v-if="!isActiveWorkspace(workspace)"
						type="button"
						class="select-workspace-button"
						@click="selectWorkspace(workspace)">
						{{ $texts.workspaces.select() }}
					</button>
					<SettingsItemActions
						class="settings-list-actions"
						:edit-label="$texts.workspaces.rename()"
						:hide-label="$texts.workspaces.hideFromQuickSwitcher()"
						:unhide-label="$texts.workspaces.showInQuickSwitcher()"
						:can-edit="true"
						:can-delete="!workspace.is_default"
						:can-hide="!workspace.is_hidden"
						:can-unhide="workspace.is_hidden"
						@edit="openRenameWorkspaceModal(workspace)"
						@delete="openDeleteWorkspaceModal(workspace)"
						@hide="hideWorkspace(workspace)"
						@unhide="unhideWorkspace(workspace)" />
				</template>
			</SettingsList>
		</div>

		<Teleport to="body">
			<div
				v-if="workspaceToRename"
				class="workspace-modal-backdrop"
				tabindex="-1"
				@click.self="closeRenameWorkspaceModal"
				@keydown.esc.stop.prevent="closeRenameWorkspaceModal">
				<div class="workspace-modal" role="dialog" aria-modal="true" aria-labelledby="workspace-rename-title">
					<div class="modal-header">
						<h2 id="workspace-rename-title">{{ $texts.workspaces.renameTitle() }}</h2>
					</div>

					<form @submit.prevent="confirmRenameWorkspace">
						<div class="form-group">
							<label for="workspace-rename-name">{{ $texts.common.name() }}</label>
							<input id="workspace-rename-name" ref="renameWorkspaceInput"
								v-model="renameWorkspaceName" class="form-control" type="text" required />
						</div>
						<p v-if="renameWorkspaceError" class="modal-error">{{ renameWorkspaceError }}</p>
						<ModalActions
							:cancel-disabled="renamingWorkspace"
							:primary-disabled="!canRenameWorkspace"
							:primary-busy="renamingWorkspace"
							:primary-label="$texts.common.save()"
							:primary-busy-label="$texts.common.saveBusy()"
							@cancel="closeRenameWorkspaceModal" />
					</form>
				</div>
			</div>

			<div
				v-if="workspaceToDelete"
				class="workspace-delete-backdrop"
				tabindex="-1"
				@click.self="closeDeleteWorkspaceModal"
				@keydown.esc.stop.prevent="closeDeleteWorkspaceModal">
				<div class="workspace-delete-modal" role="dialog" aria-modal="true" aria-labelledby="workspace-delete-title">
					<div class="modal-header">
						<h2 id="workspace-delete-title">{{ $texts.workspaces.deleteTitle() }}</h2>
					</div>
					<p class="delete-warning">{{ $texts.workspaces.deleteWarning(workspaceToDelete.name) }}</p>
					<p class="delete-impact">{{ $texts.workspaces.deleteImpact() }}</p>

					<form @submit.prevent="confirmDeleteWorkspace">
						<div class="form-group">
							<label for="workspace-delete-confirmation">{{ $texts.workspaces.deleteConfirmation() }}</label>
							<input id="workspace-delete-confirmation" ref="deleteConfirmationInput"
								v-model="deleteConfirmationText" class="form-control" type="text"
								autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="DELETE" />
						</div>
						<p v-if="deleteError" class="modal-error">{{ deleteError }}</p>
						<ModalActions
							:cancel-disabled="deletingWorkspace"
							:primary-disabled="!canDeleteWorkspace"
							:primary-busy="deletingWorkspace"
							:primary-label="$texts.workspaces.deleteConfirm()"
							:primary-busy-label="$texts.common.deleteBusy()"
							primary-variant="danger"
							@cancel="closeDeleteWorkspaceModal" />
					</form>
				</div>
			</div>
		</Teleport>
	</div>
</template>

<script>
import axios from '../services/http'
import { clearWorkspaceId, readWorkspaceId, writeWorkspaceId } from '../services/workspaceStorage'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { showSuccess, showInfo } from '@nextcloud/dialogs'
import ModalActions from './ModalActions.vue'
import SettingsItemActions from './SettingsItemActions.vue'
import SettingsList from './SettingsList.vue'
import TableTooltip from './TableTooltip.vue'

export default {
	name: 'WorkspaceSettings',
	components: {
		NcButton,
		NcLoadingIcon,
		ModalActions,
		SettingsItemActions,
		SettingsList,
		TableTooltip
	},
	data() {
		return {
			loading: true,
			workspaces: [],
			activeWorkspaceId: readWorkspaceId(),
			newWorkspaceName: '',
			workspaceToRename: null,
			renameWorkspaceName: '',
			renameWorkspaceError: '',
			renamingWorkspace: false,
			workspaceToDelete: null,
			deleteConfirmationText: '',
			deleteError: '',
			deletingWorkspace: false
		}
	},
	computed: {
		canRenameWorkspace() {
			return this.workspaceToRename
				&& this.renameWorkspaceName.trim()
				&& this.renameWorkspaceName.trim() !== this.workspaceToRename.name
				&& !this.renamingWorkspace;
		},
		canDeleteWorkspace() {
			return this.deleteConfirmationText === 'DELETE' && !this.deletingWorkspace;
		}
	},
	mounted() {
		this.fetchWorkspaces()
	},
	methods: {
		extractError(error, fallback) {
			return error?.response?.data?.error || error?.message || fallback;
		},
		readActiveWorkspaceId() {
			return readWorkspaceId();
		},
		isActiveWorkspace(workspace) {
			return workspace && workspace.id === this.activeWorkspaceId;
		},
		selectWorkspace(workspace) {
			if (!workspace || this.isActiveWorkspace(workspace)) {
				return;
			}
			writeWorkspaceId(workspace.id);
			this.activeWorkspaceId = workspace.id;
			window.setTimeout(() => window.location.reload(), 100);
		},
		showError(message) {
			if (window.OC && window.OC.Notification) {
				window.OC.Notification.showTemporary(message);
				return;
			}
			alert(message);
		},
		showSuccessMessage(message) {
			if (typeof showSuccess === 'function') {
				showSuccess(message, { timeout: 3000 });
			} else if (typeof showInfo === 'function') {
				showInfo(message, { timeout: 3000 });
			} else if (window.OC && window.OC.Notification) {
				window.OC.Notification.showTemporary(message);
			}
		},
		async fetchWorkspaces() {
			this.loading = true;
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/workspaces'), {
					headers: { Accept: 'application/json' },
					params: { _t: Date.now() },
					skipWorkspaceHeader: true
				});
				this.assertJsonResponse(response, this.$texts.workspaces.listAction());
				if (response.data && typeof response.data === 'object' && response.data.error) {
					throw new Error(response.data.error);
				}
				this.workspaces = this.normalizeWorkspaces(response.data);
				this.syncActiveWorkspace();
				this.broadcastWorkspaces();
			} catch (error) {
				console.error(error);
				this.showError(this.extractError(error, this.$texts.workspaces.loadError()));
			} finally {
				this.loading = false;
			}
		},
		async createWorkspace() {
			const workspaceName = this.newWorkspaceName.trim();
			if (!workspaceName) {
				return;
			}

			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/workspaces'), { name: workspaceName }, {
					headers: { Accept: 'application/json' },
					skipWorkspaceHeader: true
				});
				this.assertJsonResponse(response, this.$texts.workspaces.createAction());
				if (response.data && typeof response.data === 'object' && response.data.error) {
					throw new Error(response.data.error);
				}

				const responseWorkspaces = this.extractWorkspacesFromCreateResponse(response.data);
				if (responseWorkspaces.length > 0) {
					this.workspaces = responseWorkspaces;
					this.newWorkspaceName = '';
					this.broadcastWorkspaces();
					this.showSuccessMessage(this.$texts.workspaces.created());
					return;
				}

				this.newWorkspaceName = '';
				await this.fetchWorkspaces();
				const createdWorkspace = this.workspaces.find(workspace => workspace.name === workspaceName);
				if (!createdWorkspace) {
					console.error('Workspace create response did not include the new workspace:', response.data);
					throw new Error(this.$texts.workspaces.notInServerList());
				}
				this.broadcastWorkspaces();
				this.showSuccessMessage(this.$texts.workspaces.created());
			} catch (error) {
				console.error(error);
				this.showError(this.extractError(error, this.$texts.workspaces.createError()));
			}
		},
		openRenameWorkspaceModal(workspace) {
			this.workspaceToRename = workspace;
			this.renameWorkspaceName = workspace.name;
			this.renameWorkspaceError = '';
			this.renamingWorkspace = false;
			this.$nextTick(() => {
				if (this.$refs.renameWorkspaceInput) {
					this.$refs.renameWorkspaceInput.focus();
					this.$refs.renameWorkspaceInput.select();
				}
			});
		},
		closeRenameWorkspaceModal() {
			if (this.renamingWorkspace) {
				return;
			}
			this.resetRenameWorkspaceModal();
		},
		resetRenameWorkspaceModal() {
			this.workspaceToRename = null;
			this.renameWorkspaceName = '';
			this.renameWorkspaceError = '';
			this.renamingWorkspace = false;
		},
		async confirmRenameWorkspace() {
			const workspace = this.workspaceToRename;
			const newName = this.renameWorkspaceName.trim();
			if (!workspace || !this.canRenameWorkspace) {
				return;
			}

			this.renamingWorkspace = true;
			this.renameWorkspaceError = '';
			try {
				const response = await axios.put(generateUrl(`/apps/cobudget/api/workspaces/${workspace.id}`), { name: newName }, {
					headers: { Accept: 'application/json' },
					skipWorkspaceHeader: true
				});
				this.assertJsonResponse(response, this.$texts.workspaces.renameAction());
				if (response.data && typeof response.data === 'object' && response.data.error) {
					throw new Error(response.data.error);
				}
				workspace.name = newName;
				this.resetRenameWorkspaceModal();
				this.broadcastWorkspaces();
				this.showSuccessMessage(this.$texts.workspaces.saved());
			} catch (error) {
				console.error(error);
				this.renameWorkspaceError = this.extractError(error, this.$texts.workspaces.renameError());
			} finally {
				this.renamingWorkspace = false;
			}
		},
		openDeleteWorkspaceModal(workspace) {
			if (workspace.is_default) {
				this.showError(this.$texts.workspaces.baseCannotBeDeleted());
				return;
			}
			this.workspaceToDelete = workspace;
			this.deleteConfirmationText = '';
			this.deleteError = '';
			this.deletingWorkspace = false;
			this.$nextTick(() => {
				if (this.$refs.deleteConfirmationInput) {
					this.$refs.deleteConfirmationInput.focus();
				}
			});
		},
		closeDeleteWorkspaceModal() {
			if (this.deletingWorkspace) {
				return;
			}
			this.resetDeleteWorkspaceModal();
		},
		resetDeleteWorkspaceModal() {
			this.workspaceToDelete = null;
			this.deleteConfirmationText = '';
			this.deleteError = '';
			this.deletingWorkspace = false;
		},
		async confirmDeleteWorkspace() {
			const workspace = this.workspaceToDelete;
			if (!workspace || !this.canDeleteWorkspace) {
				return;
			}

			this.deletingWorkspace = true;
			this.deleteError = '';
			try {
				const response = await axios.delete(generateUrl(`/apps/cobudget/api/workspaces/${workspace.id}`), {
					headers: { Accept: 'application/json' },
					skipWorkspaceHeader: true
				});
				this.assertJsonResponse(response, this.$texts.workspaces.deleteAction());
				if (response.data && typeof response.data === 'object' && response.data.error) {
					throw new Error(response.data.error);
				}

				this.workspaces = this.normalizeWorkspaces(this.workspaces).filter(item => item.id !== workspace.id);
			const activeId = readWorkspaceId();
			if (activeId === workspace.id) {
				clearWorkspaceId();
					this.showSuccessMessage(this.$texts.workspaces.deleted());
					window.setTimeout(() => window.location.reload(), 400);
					return;
				}
				this.resetDeleteWorkspaceModal();
				this.broadcastWorkspaces();
				this.showSuccessMessage(this.$texts.workspaces.deleted());
			} catch (error) {
				console.error(error);
				this.deleteError = this.extractError(error, this.$texts.workspaces.deleteError());
			} finally {
				this.deletingWorkspace = false;
			}
		},
		async hideWorkspace(workspace) {
			await this.updateWorkspaceVisibility(workspace, true);
		},
		async unhideWorkspace(workspace) {
			await this.updateWorkspaceVisibility(workspace, false);
		},
		async updateWorkspaceVisibility(workspace, hidden) {
			if (!workspace) {
				return;
			}

			try {
				const action = hidden ? 'hide' : 'unhide';
				const response = await axios.post(generateUrl(`/apps/cobudget/api/workspaces/${workspace.id}/${action}`), {}, {
					headers: { Accept: 'application/json' },
					skipWorkspaceHeader: true
				});
				this.assertJsonResponse(response, hidden ? this.$texts.workspaces.hideAction() : this.$texts.workspaces.unhideAction());
				if (response.data && typeof response.data === 'object' && response.data.error) {
					throw new Error(response.data.error);
				}

				if (response.data && Array.isArray(response.data.workspaces)) {
					this.workspaces = this.normalizeWorkspaces(response.data.workspaces);
				} else {
					workspace.is_hidden = hidden;
				}
				this.broadcastWorkspaces();
				this.showSuccessMessage(hidden ? this.$texts.workspaces.hidden() : this.$texts.workspaces.shown());
			} catch (error) {
				console.error(error);
				this.showError(this.extractError(error, hidden ? this.$texts.workspaces.hideError() : this.$texts.workspaces.showError()));
			}
		},
		broadcastWorkspaces() {
			window.dispatchEvent(new CustomEvent('workspaces-updated', { detail: this.workspaces }));
		},
		syncActiveWorkspace() {
			if (this.workspaces.length === 0) {
				this.activeWorkspaceId = null;
				return;
			}

			if (this.activeWorkspaceId && this.workspaces.some(workspace => workspace.id === this.activeWorkspaceId)) {
				return;
			}

			const storedId = this.readActiveWorkspaceId();
			if (storedId && this.workspaces.some(workspace => workspace.id === storedId)) {
				this.activeWorkspaceId = storedId;
				return;
			}

			const defaultWorkspace = this.workspaces.find(workspace => workspace.is_default) || this.workspaces[0];
			this.activeWorkspaceId = defaultWorkspace.id;
		},
		normalizeWorkspaces(payload) {
			if (Array.isArray(payload)) {
				return payload.map(this.normalizeWorkspace).filter(Boolean);
			}
			if (payload && typeof payload === 'object') {
				if (Array.isArray(payload.workspaces)) {
					return payload.workspaces.map(this.normalizeWorkspace).filter(Boolean);
				}
				return Object.values(payload).map(this.normalizeWorkspace).filter(Boolean);
			}
			return [];
		},
		extractWorkspacesFromCreateResponse(payload) {
			if (!payload || typeof payload !== 'object') {
				return [];
			}
			const responseWorkspaces = this.normalizeWorkspaces(payload.workspaces);
			if (responseWorkspaces.length > 0) {
				return responseWorkspaces;
			}
			const createdWorkspace = this.normalizeWorkspace(payload.workspace || payload);
			if (createdWorkspace) {
				return [
					...this.normalizeWorkspaces(this.workspaces).filter(workspace => workspace.id !== createdWorkspace.id),
					createdWorkspace
				];
			}
			return [];
		},
		normalizeWorkspace(workspace) {
			if (!workspace || typeof workspace !== 'object' || workspace.error) {
				return null;
			}
			if (workspace.id === undefined || workspace.id === null || !workspace.name) {
				return null;
			}
			return {
				...workspace,
				id: parseInt(workspace.id, 10),
				is_default: workspace.is_default === true || workspace.is_default === 1 || workspace.is_default === '1',
				is_hidden: workspace.is_hidden === true || workspace.is_hidden === 1 || workspace.is_hidden === '1' || workspace.is_hidden === 'true',
				created_at: parseInt(workspace.created_at || 0, 10)
			};
		},
		assertJsonResponse(response, action) {
			if (typeof response.data === 'string') {
				const contentType = response.headers?.['content-type'] || 'unknown content type';
				const preview = response.data.slice(0, 160);
				console.error(`${action} returned a non-JSON response`, {
					status: response.status,
					contentType,
					url: response.config?.url,
					preview
				});
				throw new Error(this.$texts.workspaces.nonJson(action));
			}
		}
	}
}
</script>

<style scoped>
.workspace-settings {
	margin-top: 16px;
}

.loading-state {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 32px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.default-badge {
	background: var(--color-primary-light, #0082c933);
	color: var(--color-primary, #0082c9);
	padding: 2px 6px;
	border-radius: var(--border-radius, 6px);
	font-size: var(--cobudget-font-xs);
	font-weight: bold;
	white-space: nowrap;
}

.active-badge {
	background: var(--color-success-light, #e6f4ea);
	padding: 2px 6px;
	border-radius: var(--border-radius, 6px);
	font-size: var(--cobudget-font-xs);
	font-weight: bold;
	white-space: nowrap;
}

.select-workspace-button {
	border: 1px solid var(--cobudget-border-strong, #b8b8b8);
	border-radius: var(--border-radius, 6px);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	cursor: pointer;
	flex: 0 0 auto;
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
	min-height: 34px;
	padding: 0 12px;
}

.select-workspace-button:hover,
.select-workspace-button:focus-visible {
	border-color: var(--color-primary, #0082c9);
	color: var(--color-primary, #0082c9);
	outline: none;
}

.add-form {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-top: 0;
	margin-bottom: 20px;
}

.form-control {
	width: 100%;
	height: 34px;
	padding: 0 12px;
	border: 1px solid var(--cobudget-border-strong, #888);
	border-radius: var(--border-radius, 6px);
	font-size: var(--cobudget-font-ui);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	box-sizing: border-box;
	transition: border-color 0.2s;
	flex: 1;
	min-width: 0;
}

.form-control:focus {
	border-color: var(--color-primary, #0082c9);
	outline: none;
}

@media (max-width: 768px) {
	.add-form {
		align-items: stretch;
		flex-direction: column;
	}

	.select-workspace-button {
		width: 100%;
	}
}

.workspace-modal-backdrop,
.workspace-delete-backdrop {
	--color-main-text: var(--cobudget-text);
	--color-text-maxcontrast: var(--cobudget-text-muted);
	--color-main-background: var(--cobudget-surface);
	--color-background-hover: var(--cobudget-surface-muted);
	--color-background-dark: var(--cobudget-surface-muted);
	--color-border: var(--cobudget-border);
	--color-border-dark: var(--cobudget-border-strong);

	position: fixed;
	top: 0;
	left: 0;
	width: 100vw;
	height: 100vh;
	background: rgba(0, 0, 0, 0.4);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10000;
}

.workspace-modal,
.workspace-delete-modal {
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	border-radius: var(--border-radius-large, 8px);
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
	color: var(--cobudget-text, var(--color-main-text, #222));
	max-width: 520px;
	padding: 24px;
	width: 90%;
}

.modal-header h2 {
	font-size: var(--cobudget-font-xl);
	margin: 0 0 20px 0;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.delete-warning,
.delete-impact {
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--cobudget-font-ui);
	line-height: 1.5;
	margin: 0 0 12px 0;
}

.delete-impact {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	margin-bottom: 20px;
}

.form-group {
	margin-bottom: 16px;
}

.form-group label {
	display: block;
  color: var(--cobudget-text-muted, #888);
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;

}

.modal-error {
	color: var(--cobudget-error);
	margin: 0 0 16px 0;
}

@media (max-width: 768px) {
	.workspace-modal,
	.workspace-delete-modal {
		padding: 20px;
		width: calc(100% - 32px);
	}
}
</style>
