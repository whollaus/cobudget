<template>
	<div class="workspace-settings">
		<div v-if="loading" class="loading-state">
			<NcLoadingIcon /> {{ $texts.workspaces.loading() }}
		</div>
		<NcNoteCard v-else-if="loadError" type="error"><p>{{ loadError }}</p><NcButton @click="fetchWorkspaces">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
		<div v-else>
			<NcNoteCard v-if="actionError" type="error" :text="actionError" />
			<SettingsList class="workspace-list" borderless :items="workspaces" :empty-text="$texts.workspaces.empty()">
				<template #item="{ item: workspace }">
					<div class="settings-list-info workspace-info">
						<span class="workspace-name">{{ workspace.name }}</span>
						<div class="workspace-status">
							<TableTooltip v-if="workspace.is_default" :text="$texts.workspaces.baseTooltip()">
								<NcChip class="default-badge" :text="$texts.workspaces.base()" no-close variant="secondary" />
							</TableTooltip>
							<NcChip v-if="isActiveWorkspace(workspace)" class="active-badge" :text="$texts.workspaces.active()" no-close variant="success" />
						</div>
					</div>
					<div class="settings-list-actions workspace-actions">
						<NcButton v-if="!isActiveWorkspace(workspace)" class="select-workspace-button" variant="tertiary" @click="selectWorkspace(workspace)">
							{{ $texts.workspaces.select() }}
						</NcButton>
						<NcButton variant="tertiary" :disabled="visibilityUpdatingId === workspace.id" :aria-label="workspace.is_hidden ? $texts.workspaces.showInQuickSwitcher() : $texts.workspaces.hideFromQuickSwitcher()" :title="workspace.is_hidden ? $texts.workspaces.showInQuickSwitcher() : $texts.workspaces.hideFromQuickSwitcher()" @click="updateWorkspaceVisibility(workspace, !workspace.is_hidden)">
							<template #icon><EyeIcon v-if="workspace.is_hidden" :size="20" /><EyeOffIcon v-else :size="20" /></template>
						</NcButton>
						<NcButton variant="tertiary" :aria-label="$texts.common.edit()" :title="$texts.common.edit()" @click="openRenameWorkspaceModal(workspace)">
							<template #icon><PencilIcon :size="20" /></template>
						</NcButton>
					</div>
				</template>
			</SettingsList>
		</div>

		<DialogTransition :show="!!(workspaceEditorOpen)" v-slot="{ returnFocus }">
			<NcModal :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-settings-modal workspace-editor-modal" :show="true" label-id="workspace-editor-title" :no-close="workspaceEditorBusy || confirmDiscard || !!workspaceToDelete" :close-on-click-outside="!workspaceEditorBusy && !confirmDiscard && !workspaceToDelete" @update:show="closeRenameWorkspaceModal" @keydown.esc.stop.prevent="closeRenameWorkspaceModal">
				<form class="workspace-editor" :aria-busy="workspaceEditorBusy" @submit.prevent="saveWorkspace">
					<h2 id="workspace-editor-title">{{ workspaceToRename ? $texts.workspaces.editTitle() : $texts.workspaces.newWorkspace() }}</h2>
					<div class="workspace-editor-body">
						<NcFormGroup class="cobudget-form-group">
							<template #label><label class="cobudget-form-label" for="workspace-editor-name">{{ $texts.masterData.entryLabel() }}</label></template>
							<NcInputField id="workspace-editor-name" ref="renameWorkspaceInput" v-model="renameWorkspaceName" label-outside :label="$texts.masterData.entryLabel()" :disabled="workspaceEditorBusy" required maxlength="128" :error="!!renameWorkspaceError" :helper-text="renameWorkspaceError" class="cobudget-native-field" />
						</NcFormGroup>
						<p v-if="workspaceToRename?.is_default" id="workspace-editor-delete-hint" class="workspace-editor-hint cobudget-form-hint">{{ $texts.workspaces.baseCannotBeDeleted() }}</p>
					</div>
					<div class="workspace-editor-actions">
						<DisabledActionHint :reason="workspaceDeleteReason" :label="$texts.common.delete()" v-if="workspaceToRename" class="workspace-delete-hint"><NcButton class="workspace-editor-delete" variant="error" :aria-label="$texts.common.delete()" :title="$texts.common.delete()" :aria-describedby="workspaceToRename.is_default ? 'workspace-editor-delete-hint' : undefined" :disabled="workspaceEditorBusy || workspaceToRename.is_default" @click="openDeleteWorkspaceModal(workspaceToRename)"><template #icon><DeleteIcon :size="20" /></template><span class="workspace-editor-delete-label">{{ $texts.common.delete() }}</span></NcButton></DisabledActionHint>
						<div class="workspace-confirm-actions">
							<NcButton variant="tertiary" :disabled="workspaceEditorBusy" @click="discardWorkspaceEditor">{{ $texts.common.cancel() }}</NcButton>
							<NcButton variant="primary" type="submit" :disabled="!canSaveWorkspace">
								{{ workspaceEditorBusy ? $texts.common.saveBusy() : $texts.common.save() }}
							</NcButton>
						</div>
					</div>
				</form>
			</NcModal>
		</DialogTransition>

		<DialogTransition :show="!!(confirmDiscard)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="$texts.entry.discardChangesTitle()" :message="$texts.entry.discardChangesMessage()" close-on-click-outside @keydown.esc.stop.prevent="confirmDiscard = false" @closing="confirmDiscard = false">
				<template #actions><NcButton variant="tertiary" @click="confirmDiscard = false">{{ $texts.common.cancel() }}</NcButton><NcButton variant="error" @click="discardWorkspaceEditor">{{ $texts.entry.discardChangesConfirm() }}</NcButton></template>
			</NcDialog>
		</DialogTransition>

		<DialogTransition :show="!!(workspaceToDelete)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="$texts.workspaces.deleteTitle()" :no-close="deletingWorkspace" :close-on-click-outside="!deletingWorkspace" is-form @submit="confirmDeleteWorkspace" @closing="closeDeleteWorkspaceModal" @keydown.esc.stop.prevent="closeDeleteWorkspaceModal">
				<p class="delete-warning">{{ $texts.workspaces.deleteWarning(workspaceToDelete.name) }}</p>
				<p class="delete-impact">{{ $texts.workspaces.deleteImpact() }}</p>
				<NcInputField id="workspace-delete-confirmation" ref="deleteConfirmationInput" v-model="deleteConfirmationText" :label="$texts.workspaces.deleteConfirmation()" :disabled="deletingWorkspace" :error="!!deleteError" :helper-text="deleteError" autocomplete="off" autocapitalize="off" :spellcheck="false" placeholder="DELETE" class="cobudget-native-field" />
				<template #actions>
					<NcButton variant="tertiary" :disabled="deletingWorkspace" @click="closeDeleteWorkspaceModal">{{ $texts.common.cancel() }}</NcButton>
					<DisabledActionHint :reason="workspaceConfirmationReason" :label="$texts.workspaces.deleteConfirm()" ><NcButton variant="error" type="submit" :disabled="!canDeleteWorkspace">{{ deletingWorkspace ? $texts.common.deleteBusy() : $texts.workspaces.deleteConfirm() }}</NcButton></DisabledActionHint>
				</template>
			</NcDialog>
		</DialogTransition>
	</div>
</template>

<script>
import DialogTransition from './DialogTransition.vue'
import DisabledActionHint from './DisabledActionHint.vue'
import axios from '../services/http'
import { clearWorkspaceId, readWorkspaceId, writeWorkspaceId } from '../services/workspaceStorage'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { showToast, extractError } from '../services/notifications'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import EyeIcon from 'vue-material-design-icons/Eye.vue'
import EyeOffIcon from 'vue-material-design-icons/EyeOff.vue'
import SettingsList from './SettingsList.vue'
import TableTooltip from './TableTooltip.vue'

export default {
	name: 'WorkspaceSettings',
	emits: ['loading'],
	components: { DialogTransition, DisabledActionHint,
		NcInputField,
		NcModal,
		NcDialog,
		PencilIcon,
		PlusIcon,
		DeleteIcon,
		NcFormGroup,
		EyeIcon,
		EyeOffIcon,
		NcButton,
		NcChip,
		NcLoadingIcon,
		NcNoteCard,
		SettingsList,
		TableTooltip
	},
	data() {
		return {
			loading: true,
			loadError: '',
			actionError: '',
			workspaces: [],
			activeWorkspaceId: readWorkspaceId(),
			workspaceEditorOpen: false,
			confirmDiscard: false,
			creatingWorkspace: false,
			visibilityUpdatingId: null,
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
		workspaceDeleteReason() {
			if (this.workspaceToRename?.is_default) return this.$texts.workspaces.baseCannotBeDeleted();
			if (this.deletingWorkspace) return this.$texts.common.deleteBusy();
			return this.workspaceEditorBusy ? this.$texts.common.saveBusy() : '';
		},
		workspaceConfirmationReason() {
			if (this.workspaceToDelete?.is_default) return this.$texts.workspaces.baseCannotBeDeleted();
			if (this.deletingWorkspace) return this.$texts.common.deleteBusy();
			return this.canDeleteWorkspace ? '' : this.$texts.workspaces.deleteConfirmation();
		},
		canDeleteWorkspace() {
			return !!this.workspaceToDelete && !this.workspaceToDelete.is_default && this.deleteConfirmationText === 'DELETE' && !this.deletingWorkspace;
		},
		workspaceEditorBusy() { return this.creatingWorkspace || this.renamingWorkspace || this.deletingWorkspace; },
		workspaceHasChanges() { return this.renameWorkspaceName.trim() !== (this.workspaceToRename?.name || ''); },
		canSaveWorkspace() { return this.workspaceEditorOpen && !this.workspaceEditorBusy && !this.workspaceToDelete && !this.confirmDiscard && !!this.renameWorkspaceName.trim() && this.workspaceHasChanges; }
	},
	watch: {
		loading: { immediate: true, handler(value) { this.$emit('loading', value); } }
	},
	mounted() {
		this.fetchWorkspaces()
	},
	methods: {
		extractError(error, fallback) {
			return extractError(error, fallback);
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
		showError(message) { this.actionError = message; },
		showSuccessMessage(message) { showToast(message, 'success'); },
		async fetchWorkspaces({ quiet = false } = {}) {
			if (!quiet) this.loading = true;
			this.loadError = '';
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
				const message = this.extractError(error, this.$texts.workspaces.loadError());
				if (quiet) this.actionError = message;
				else this.loadError = message;
			} finally {
				this.loading = false;
			}
		},
		async createWorkspace() {
			const workspaceName = this.renameWorkspaceName.trim();
			if (!this.canSaveWorkspace || this.workspaceToRename) {
				return;
			}

			this.creatingWorkspace = true;
			this.renameWorkspaceError = '';
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
					this.resetRenameWorkspaceModal();
					this.broadcastWorkspaces();
					this.showSuccessMessage(this.$texts.workspaces.created());
					return;
				}

				this.resetRenameWorkspaceModal();
				await this.fetchWorkspaces({ quiet: true });
				const createdWorkspace = this.workspaces.find(workspace => workspace.name === workspaceName);
				if (!createdWorkspace) {
					console.error('Workspace create response did not include the new workspace:', response.data);
					throw new Error(this.$texts.workspaces.notInServerList());
				}
				this.broadcastWorkspaces();
				this.showSuccessMessage(this.$texts.workspaces.created());
			} catch (error) {
				console.error(error);
				const message = this.extractError(error, this.$texts.workspaces.createError());
				if (this.workspaceEditorOpen) this.renameWorkspaceError = message;
				else this.showError(message);
			} finally {
				this.creatingWorkspace = false;
			}
		},
		openCreateWorkspaceModal() { this.openRenameWorkspaceModal(null); },
		openRenameWorkspaceModal(workspace) {
			if (this.workspaceEditorBusy) return;
			this.workspaceEditorOpen = true;
			this.confirmDiscard = false;
			this.workspaceToRename = workspace;
			this.renameWorkspaceName = workspace?.name || '';
			this.renameWorkspaceError = '';
			this.renamingWorkspace = false;
			this.$nextTick(() => {
				if (this.$refs.renameWorkspaceInput) {
					this.$refs.renameWorkspaceInput.focus({ preventScroll: true });
					this.$refs.renameWorkspaceInput.select();
				}
			});
		},
		closeRenameWorkspaceModal() {
			if (this.workspaceEditorBusy || this.confirmDiscard || this.workspaceToDelete) return;
			if (this.workspaceHasChanges) { this.confirmDiscard = true; return; }
			this.resetRenameWorkspaceModal();
		},
		discardWorkspaceEditor() {
			if (this.workspaceEditorBusy || this.workspaceToDelete) return;
			this.resetRenameWorkspaceModal();
		},
		async saveWorkspace() {
			if (!this.canSaveWorkspace) return;
			if (!this.workspaceToRename) return this.createWorkspace();
			if (await this.confirmRenameWorkspace()) this.resetRenameWorkspaceModal();
		},
		resetRenameWorkspaceModal() {
			this.workspaceEditorOpen = false;
			this.confirmDiscard = false;
			this.workspaceToRename = null;
			this.renameWorkspaceName = '';
			this.renameWorkspaceError = '';
			this.renamingWorkspace = false;
		},
		async confirmRenameWorkspace() {
			if (this._renameSavePromise) return this._renameSavePromise;
			const workspace = this.workspaceToRename;
			const newName = this.renameWorkspaceName.trim();
			if (!workspace) return true;
			if (!newName) return false;
			if (newName === workspace.name) {
				this.renameWorkspaceError = '';
				return true;
			}
			this._renameSavePromise = this.persistWorkspaceRename(workspace, newName);
			try {
				return await this._renameSavePromise;
			} finally {
				this._renameSavePromise = null;
			}
		},
		async persistWorkspaceRename(workspace, newName) {
			this.renamingWorkspace = true;
			this.renameWorkspaceError = '';
			try {
				const response = await axios.put(generateUrl(`/apps/cobudget/api/workspaces/${workspace.id}`), { name: newName }, {
					headers: { Accept: 'application/json' },
					skipWorkspaceHeader: true
				});
				this.assertJsonResponse(response, this.$texts.workspaces.renameAction());
				if (response.data && typeof response.data === 'object' && response.data.error) throw new Error(response.data.error);
				workspace.name = newName;
				this.renameWorkspaceName = newName;
				this.broadcastWorkspaces();
				return true;
			} catch (error) {
				this.renameWorkspaceError = this.extractError(error, this.$texts.workspaces.renameError());
				return false;
			} finally {
				this.renamingWorkspace = false;
			}
		},
		openDeleteWorkspaceModal(workspace) {
			if (!workspace || this.workspaceEditorBusy) return;
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
					this.$refs.deleteConfirmationInput.focus({ preventScroll: true });
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
				this.resetRenameWorkspaceModal();
				this.resetDeleteWorkspaceModal();
				const activeId = readWorkspaceId();
				if (activeId === workspace.id) {
					clearWorkspaceId();
					this.showSuccessMessage(this.$texts.workspaces.deleted());
					window.setTimeout(() => window.location.reload(), 400);
					return;
				}
				this.broadcastWorkspaces();
				this.showSuccessMessage(this.$texts.workspaces.deleted());
			} catch (error) {
				console.error(error);
				this.deleteError = this.extractError(error, this.$texts.workspaces.deleteError());
			} finally {
				this.deletingWorkspace = false;
			}
		},
		async updateWorkspaceVisibility(workspace, hidden) {
			if (!workspace || this.visibilityUpdatingId !== null) {
				return;
			}

			this.actionError = '';
			this.visibilityUpdatingId = workspace.id;
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
			} finally {
				this.visibilityUpdatingId = null;
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


.workspace-name {
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	color: var(--color-main-text);
}
.workspace-status { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); flex-shrink: 0; }
.workspace-actions { display: flex; gap: 4px; flex-wrap: wrap; }
.workspace-list :deep(.workspace-actions) { justify-content: flex-end; }

.workspace-editor { padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding) var(--cobudget-dialog-padding); }
.workspace-editor h2 { padding-inline-end: var(--cobudget-mobile-touch-size, 44px); margin: 0 0 calc(var(--default-grid-baseline, 4px) * 4); font-size: var(--cobudget-dialog-title-size, 20px); text-align: start; }
.workspace-editor-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: var(--cobudget-section-gap); }
.workspace-confirm-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-inline-start: auto; }
.workspace-editor-hint { margin-top: 12px; }
.workspace-editor-delete :deep(.button-vue__icon) { display: none; }
@media (min-width: 769px) {
	.workspace-editor .workspace-editor-delete { padding-inline: var(--button-padding-default); }
	.workspace-editor-delete :deep(.button-vue__text) { padding-inline: var(--default-grid-baseline, 4px); }
}


/* Match the mobile category/partner editor while keeping native modal guards. */
@media (max-width: 768px) {
	.workspace-editor-modal { --cobudget-dialog-padding: var(--cobudget-space-4, 16px); }
	.workspace-editor-modal :deep(.modal-container) { height: calc(100% - var(--header-height)); overflow: hidden; }
	.workspace-editor-modal :deep(.modal-container__content) { display: flex; min-height: 0; overflow: hidden; }
	.workspace-editor { display: flex; flex-direction: column; width: 100%; min-width: 0; height: 100%; min-height: 0; padding: 0; }
	.workspace-editor h2 { flex-shrink: 0; margin: 0; padding: calc(var(--default-grid-baseline, 4px) * 3) calc(var(--cobudget-mobile-touch-size, 44px) + var(--cobudget-dialog-padding)) var(--cobudget-space-2, 8px) var(--cobudget-dialog-padding); line-height: 1.5; overflow-wrap: anywhere; }
	.workspace-editor-body { display: grid; align-content: start; flex: 1; min-height: 0; overflow: auto; gap: var(--cobudget-space-2, 8px); padding: var(--default-grid-baseline, 4px) var(--cobudget-dialog-padding) calc(var(--default-grid-baseline, 4px) * 3); }
	.workspace-editor-hint { margin: 0; font-size: var(--cobudget-font-sm, 13px); line-height: 1.4; }
	.workspace-editor-actions { flex-shrink: 0; flex-wrap: nowrap; margin: 0; padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding) max(var(--cobudget-dialog-padding), env(safe-area-inset-bottom)); }
	.workspace-confirm-actions { flex: 1; min-width: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.workspace-confirm-actions > :deep(.button-vue) { min-width: 0; width: 100%; }
	.workspace-delete-hint { flex: 0 0 var(--cobudget-mobile-touch-size, 44px); }
	.workspace-editor-delete { flex: 0 0 var(--cobudget-mobile-touch-size, 44px); width: var(--cobudget-mobile-touch-size, 44px); min-width: 0; padding-inline: 0; }
	.workspace-editor-delete :deep(.button-vue__icon) { display: flex; }
	.workspace-editor-delete-label { display: none; }
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

</style>
