<template>
	<div v-if="project" class="cobudget-project-settings settings-section">
		<AppPageHeader>
			<template #title>
				<span class="cobudget-page-title-with-back">
					<NcButton
						variant="tertiary"
						class="cobudget-header-back-button"
						:aria-label="$texts.areaSettings.backToArea()"
						:title="$texts.areaSettings.backToArea()"
						@click="goBackToProject">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
					</NcButton>
					<span>{{ $texts.areaSettings.title() }}</span>
				</span>
			</template>
			<template #actions>
				<div v-if="canManageProject" class="danger-actions header-actions">
					<CbButton
						type="button"
						class="project-delete-button"
						variant="danger-outline"
						icon-only
						:aria-label="$texts.common.delete()"
						:title="hasAnyEntries ? $texts.areaSettings.deleteBlockedWithEntries() : $texts.common.delete()"
						:disabled="hasAnyEntries"
						@click="deleteProject">
						<template #icon>
							<DeleteIcon :size="20" />
						</template>
					</CbButton>
					<CbButton
						type="button"
						class="project-archive-button"
						variant="soft"
						icon-only
						:aria-label="project.is_archived ? $texts.areaSettings.unarchive() : $texts.areaSettings.archive()"
						:title="hasActiveEntries ? $texts.areaSettings.archiveBlockedWithOpenEntries() : (project.is_archived ? $texts.areaSettings.unarchive() : $texts.areaSettings.archive())"
						:disabled="hasActiveEntries"
						@click="toggleArchiveProject">
						<template #icon>
							<ArchiveIcon :size="20" />
						</template>
					</CbButton>
				</div>
			</template>
		</AppPageHeader>

		<div class="settings-content">
			<div class="settings-header">
				<p class="settings-hint">
					{{ $texts.areaSettings.hint() }}
				</p>
			</div>

			<section class="settings-block">
				<h3>{{ $texts.areaSettings.general() }}</h3>
				<form class="project-edit-form" @submit.prevent="updateProject">
					<div class="form-group project-name-field">
						<label for="project-settings-name">{{ $texts.areaSettings.areaName() }}</label>
						<input
							id="project-settings-name"
							ref="projectNameInput"
							v-model="editProjectData.name"
							class="form-control"
							type="text"
							required
							:disabled="!canManageProject">
					</div>

					<div class="form-group project-color-field">
						<label>{{ $texts.areaSettings.areaColor() }}</label>
						<div class="color-picker-wrapper">
							<label
								class="color-preview"
								:style="{ backgroundColor: editProjectData.color || 'transparent', backgroundImage: editProjectData.color ? 'none' : '' }"
								:title="$texts.areaSettings.selectAreaColor()">
								<input
								class="sr-only"
								type="color"
								:value="editProjectData.color || '#0082c9'"
								:disabled="!canManageProject"
								@input="editColorChanged">
							</label>
							<CbButton
								type="button"
								variant="ghost"
								size="compact"
								icon-only
								class="color-clear-btn"
								:disabled="!canManageProject || !editProjectData.color"
								:aria-label="$texts.areaSettings.removeColor()"
								:title="$texts.areaSettings.removeColor()"
								@click="editProjectData.color = ''">
								<template #icon>
									<BackspaceIcon :size="20" />
								</template>
							</CbButton>
						</div>
					</div>

					<div class="project-save-field">
						<CbButton type="submit" class="project-save-button" variant="primary" :disabled="!canSaveProject">
							{{ $texts.common.save() }}
						</CbButton>
					</div>
				</form>
			</section>

			<section v-if="canManageProject && transferableOwnerMembers.length > 0" class="settings-block">
				<h3>{{ $texts.areaSettings.areaAdmin() }}</h3>
				<p class="settings-hint">
					{{ $texts.areaSettings.areaAdminHint() }}
				</p>
				<div class="ownership-transfer-form">
					<select v-model="selectedOwnerId" class="form-control" :disabled="!!transferringOwnerId">
						<option value="" disabled>{{ $texts.areaSettings.selectAreaAdmin() }}</option>
						<option v-for="member in transferableOwnerMembers" :key="member.id" :value="member.id">
							{{ member.displayName }}
						</option>
					</select>
					<CbButton
						type="button"
						variant="soft"
						:disabled="!selectedOwnerId || !!transferringOwnerId"
						@click="transferSelectedOwnership">
						<template #icon>
							<AccountSwitchIcon :size="20" />
						</template>
						{{ $texts.areaSettings.transfer() }}
					</CbButton>
				</div>
			</section>

			<section v-if="!memberManagementLocked" class="settings-block">
				<h3>{{ $texts.areaDetail.membersTitle() }}</h3>
				<p class="settings-hint">
					{{ $texts.areaSettings.sharesHint() }}
				</p>
				<p v-if="hasFormerMembers" class="settings-hint former-member-note" role="status">
					{{ $texts.areaSettings.resolveFormerMember() }}
				</p>
				<div v-if="canAddProjectMembers" class="member-management">
					<MemberSearch
						v-model="newMembers"
						class="member-search-field"
						:placeholder="$texts.areas.searchUsers()"
						:exclude-ids="currentMemberIds" />
					<CbButton
						type="button"
						variant="soft"
						class="member-add-button"
						:disabled="newMembers.length === 0 || addingMembers"
						@click="addMembers">
						{{ $texts.areaDetail.addMember() }}
					</CbButton>
				</div>
				<div class="share-toolbar">
					<div class="share-tools">
						<button type="button" class="btn-secondary" :disabled="!canManageProject || hasFormerMembers" @click="setEqualShares">
							{{ $texts.areaSettings.splitEqually() }}
						</button>
						<button type="button" class="btn-secondary" :disabled="!canManageProject || !canDistributeShareRemainder" @click="distributeShareRemainder">
							{{ $texts.areaSettings.distributeRemainder() }}
						</button>
					</div>
					<div class="settings-actions share-actions">
						<button type="button" class="btn-primary" :disabled="!canSaveShares || savingShares" @click="saveProjectShares">
							{{ $texts.areaSettings.saveShares() }}
						</button>
					</div>
				</div>
				<div class="share-table" :class="{ 'has-member-actions': canRemoveProjectMembers }" role="table" :aria-label="$texts.areaSettings.areaShareTable()">
					<div class="share-row share-row-header" role="row">
						<div role="columnheader">{{ $texts.areaSettings.member() }}</div>
						<div role="columnheader" class="share-percent-header">{{ $texts.areaSettings.share() }}</div>
						<div v-if="canRemoveProjectMembers" role="columnheader" class="member-action-header"></div>
					</div>
					<div v-for="member in shareRows" :key="member.id" class="share-row" role="row">
						<div class="share-member" role="cell">
							<span class="member-avatar">{{ initials(member.displayName) }}</span>
							<span>{{ member.displayName }}</span>
							<span v-if="member.isFormer" class="former-member-badge">{{ $texts.areaSettings.formerMember() }}</span>
						</div>
						<div class="share-input-wrap" role="cell">
							<input
								v-model.number="member.sharePercent"
								class="form-control share-input"
								type="number"
								min="0"
								max="100"
								step="1"
								inputmode="numeric"
								:disabled="!canManageProject || hasFormerMembers"
								@input="markShareEdited(member.id)"
								@change="normalizeShareInput(member)"
								@blur="normalizeShareInput(member)">
							<span>%</span>
						</div>
						<div v-if="canRemoveProjectMembers" class="member-row-actions" role="cell">
							<AreaAdminIndicator
								v-if="member.id === project.owner_id"
								:label="$texts.areaSettings.areaAdmin()" />
							<CbButton
								v-if="member.id !== project.owner_id"
								type="button"
								variant="ghost"
								size="compact"
								icon-only
								:disabled="hasActiveEntries || removingMemberId === member.id"
								:aria-label="$texts.areaDetail.removeMember()"
								:title="hasActiveEntries ? $texts.areaDetail.deleteBlockedWithOpenEntries() : $texts.areaDetail.removeMember()"
								@click="removeMember(member.id)">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
							</CbButton>
						</div>
					</div>
				</div>
				<div class="share-summary" :class="{ invalid: !sharesSumValid }">
					{{ $texts.areaSettings.sum() }}: {{ formatSharePercent(sharesSumBasisPoints) }}%
				</div>
			</section>
			<p v-else-if="$enableSharedProjects && canManageProject" class="settings-hint member-management-lock-note" role="status">
				{{ memberManagementLockHint }}
			</p>

			<section v-if="canManageProject" class="settings-block">
				<ProjectScopedSettings :project-id="projectId" @changed="onProjectScopedSettingsChanged" />
			</section>
		</div>

		<ConfirmModal
			:show="!!confirmDialog"
			:title="confirmDialog ? confirmDialog.title : ''"
			:message="confirmDialog ? confirmDialog.message : ''"
			:confirm-label="confirmDialog ? confirmDialog.confirmLabel : ''"
			:confirm-variant="confirmDialog ? confirmDialog.confirmVariant : 'primary'"
			@confirm="resolveConfirm(true)"
			@cancel="resolveConfirm(false)" />
	</div>
	<div v-else class="cobudget-project-settings settings-section loading">{{ $texts.areaSettings.loading() }}</div>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import CbButton from '../components/CbButton.vue'
import ConfirmModal from '../components/ConfirmModal.vue'
import MemberSearch from '../components/MemberSearch.vue'
import ProjectScopedSettings from '../components/ProjectScopedSettings.vue'
import AreaAdminIndicator from '../components/AreaAdminIndicator.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import BackspaceIcon from 'vue-material-design-icons/Backspace.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import AccountSwitchIcon from 'vue-material-design-icons/AccountSwitch.vue'
import { showRequestError, showToast } from '../services/notifications'

export default {
	name: 'ProjectSettingsView',
	components: {
		AppPageHeader,
		NcButton,
		CbButton,
		ConfirmModal,
		MemberSearch,
		ProjectScopedSettings,
		AreaAdminIndicator,
		ArrowLeftIcon,
		BackspaceIcon,
		DeleteIcon,
		ArchiveIcon,
		AccountSwitchIcon
	},
	props: ['id'],
	emits: ['refresh-projects'],
	data() {
		return {
			project: null,
			hasAnyEntries: false,
			hasActiveEntries: false,
			editProjectData: {
				name: '',
				color: ''
			},
			shareRows: [],
			originalShareSignature: '',
			editedShareUserIds: [],
			newMembers: [],
			addingMembers: false,
			removingMemberId: null,
			transferringOwnerId: null,
			selectedOwnerId: '',
			currentUserId: null,
			savingShares: false,
			confirmDialog: null
		}
	},
	computed: {
		projectId() {
			return this.id;
		},
		canSaveProject() {
			if (!this.project || !this.canManageProject) {
				return false;
			}
			const name = this.editProjectData.name.trim();
			return !!name
				&& (name !== this.project.name || (this.editProjectData.color || '') !== (this.project.color || ''));
		},
		sharesSumBasisPoints() {
			return this.shareRows.reduce((sum, member) => sum + this.percentToBasisPoints(member.sharePercent), 0);
		},
		sharesSumValid() {
			return this.sharesSumBasisPoints === 10000;
		},
		currentShareSignature() {
			return this.shareRows
				.map(member => `${member.id}:${this.percentToBasisPoints(member.sharePercent)}`)
				.join('|');
		},
		canSaveShares() {
			return this.canManageProject
				&& !this.hasFormerMembers
				&& this.shareRows.length > 0
				&& this.sharesSumValid
				&& this.currentShareSignature !== this.originalShareSignature;
		},
		canDistributeShareRemainder() {
			return !this.hasFormerMembers
				&& this.shareRows.some(member => !this.editedShareUserIds.includes(member.id));
		},
		hasFormerMembers() {
			return this.shareRows.some(member => member.isFormer);
		},
		memberManagementLocked() {
			if (!this.project) {
				return false;
			}
			const serverLocked = this.project.member_management_locked === true
				|| this.project.member_management_locked === 1
				|| this.project.member_management_locked === '1'
				|| this.project.member_management_locked === 'true';
			if (serverLocked) {
				return true;
			}

			return this.projectMemberCount <= 1
				? this.hasAnyEntries
				: this.hasActiveEntries;
		},
		memberManagementLockHint() {
			return this.project?.member_management_lock_reason === 'solo_payments' || this.projectMemberCount <= 1
				? this.$texts.areaSettings.soloMemberManagementLocked()
				: this.$texts.areaSettings.sharedMemberManagementLocked();
		},
		projectMemberCount() {
			const serverCount = Number(this.project?.member_count);
			if (Number.isFinite(serverCount)) {
				return serverCount;
			}
			return Array.isArray(this.project?.members) ? this.project.members.length : 0;
		},
		transferableOwnerMembers() {
			return this.shareRows.filter(member => (
				member.id !== this.project?.owner_id
				&& member.isActive
				&& !member.isFormer
			));
		},
		currentMemberIds() {
			return this.shareRows.map(member => member.id);
		},
		canAddProjectMembers() {
			return !!this.project && this.$enableSharedProjects && this.canManageProject && !this.hasFormerMembers;
		},
		canRemoveProjectMembers() {
			return !!this.project && this.$enableSharedProjects && this.canManageProject;
		},
		canManageProject() {
			return !!this.project && (
				this.project.is_owner === true ||
				this.project.is_owner === 1 ||
				this.project.is_owner === '1' ||
				this.project.is_owner === 'true' ||
				this.project.owner_id === this.currentUserId
			);
		}
	},
	watch: {
		projectId: {
			immediate: true,
			handler() {
				this.fetchProjectSettings();
			}
		}
	},
	methods: {
		goBackToProject() {
			this.$router.push({ name: 'project-detail', params: { id: this.projectId } });
		},
		openConfirm({ title, message, confirmLabel, confirmVariant = 'primary' }) {
			return new Promise(resolve => {
				this.confirmDialog = { title, message, confirmLabel, confirmVariant, resolve };
			});
		},
		resolveConfirm(confirmed) {
			const resolver = this.confirmDialog?.resolve;
			this.confirmDialog = null;
			if (resolver) {
				resolver(confirmed);
			}
		},
		getCurrentUserId() {
			if (typeof window.OC?.getCurrentUser === 'function') {
				const user = window.OC.getCurrentUser();
				return user?.uid || user?.id || user?.userId || null;
			}
			if (typeof window.OC?.currentUser === 'string') {
				return window.OC.currentUser;
			}
			if (window.OC?.currentUser?.uid || window.OC?.currentUser?.id) {
				return window.OC.currentUser.uid || window.OC.currentUser.id;
			}
			return null;
		},
		resetProjectForm() {
			if (!this.project) {
				return;
			}
			this.editProjectData.name = this.project.name;
			this.editProjectData.color = this.project.color || '';
		},
		resetShareRows() {
			const members = Array.isArray(this.project?.members) ? this.project.members : [];
			this.shareRows = members.map(member => ({
				id: member.id,
				displayName: member.displayName || member.id,
				isFormer: member.isFormer === true || member.is_former === true,
				isActive: member.isActive !== false && member.is_active !== false,
				sharePercent: this.basisPointsToPercent(member.shareBasisPoints ?? member.share_basis_points ?? 0)
			}));
			this.originalShareSignature = this.currentShareSignature;
			this.editedShareUserIds = [];
		},
		editColorChanged(event) {
			if (!this.canManageProject) {
				return;
			}
			this.editProjectData.color = event.target.value;
		},
		async fetchProjectSettings() {
			if (!this.projectId) {
				return;
			}
			try {
				const [projectRes, anyEntriesRes, activeEntriesRes] = await Promise.all([
					axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}`)),
					axios.get(generateUrl('/apps/cobudget/api/entries'), { params: { projectId: this.projectId, limit: 1, offset: 0 } }),
					axios.get(generateUrl('/apps/cobudget/api/entries'), { params: { projectId: this.projectId, isSettled: false, limit: 1, offset: 0 } })
				]);
				this.project = projectRes.data;
				this.currentUserId = this.getCurrentUserId();
				this.hasAnyEntries = (anyEntriesRes.data?.total || 0) > 0;
				this.hasActiveEntries = (activeEntriesRes.data?.total || 0) > 0;
				this.newMembers = [];
				this.selectedOwnerId = '';
				this.resetProjectForm();
				this.resetShareRows();
				this.$nextTick(() => {
					this.$refs.projectNameInput?.focus();
				});
			} catch (e) {
				showRequestError(e, this.$texts.areaSettings.loadError(), 'Failed to fetch project settings');
			}
		},
		async updateProject() {
			if (!this.canManageProject) {
				return;
			}
			if (!this.editProjectData.name.trim()) {
				showToast(this.$texts.areaSettings.nameRequired(), 'error');
				return;
			}
			try {
				const response = await axios.put(generateUrl(`/apps/cobudget/api/projects/${this.projectId}`), {
					name: this.editProjectData.name.trim(),
					color: this.editProjectData.color
				});
				this.project.name = response.data.name;
				this.project.color = response.data.color;
				this.resetProjectForm();
				this.$emit('refresh-projects');
				showToast(this.$texts.areaSettings.saved());
			} catch (e) {
				showRequestError(e, this.$texts.areaSettings.saveError(), 'Failed to update project');
			}
		},
			percentToBasisPoints(value) {
				return this.normalizeSharePercent(value) * 100;
			},
			normalizeSharePercent(value) {
				const percent = Number(value);
				if (!Number.isFinite(percent)) {
					return 0;
				}
				return Math.max(0, Math.min(100, Math.round(percent)));
			},
			basisPointsToPercent(value) {
				return this.normalizeSharePercent((parseInt(value, 10) || 0) / 100);
			},
			formatSharePercent(basisPoints) {
				const value = this.basisPointsToPercent(basisPoints);
				return value.toLocaleString(undefined, { maximumFractionDigits: 0 });
			},
			normalizeShareInput(member) {
				member.sharePercent = this.normalizeSharePercent(member.sharePercent);
			},
			markShareEdited(userId) {
				if (!this.editedShareUserIds.includes(userId)) {
					this.editedShareUserIds = [...this.editedShareUserIds, userId];
			}
		},
		setEqualShares() {
			if (!this.canManageProject) {
				return;
			}
			const count = this.shareRows.length;
				if (count === 0) {
					return;
				}
				const base = Math.floor(100 / count);
				let remainder = 100 - base * count;
				this.shareRows = this.shareRows.map(member => {
					const sharePercent = base + (remainder > 0 ? 1 : 0);
					remainder -= remainder > 0 ? 1 : 0;
					return {
						...member,
						sharePercent
					};
				});
				this.editedShareUserIds = [];
			},
		distributeShareRemainder() {
			if (!this.canManageProject) {
				return;
			}
			const untouched = this.shareRows.filter(member => !this.editedShareUserIds.includes(member.id));
			if (untouched.length === 0) {
				return;
			}

			const used = this.shareRows
				.filter(member => this.editedShareUserIds.includes(member.id))
				.reduce((sum, member) => sum + this.percentToBasisPoints(member.sharePercent), 0);
				const remaining = Math.max(0, 10000 - used);
				const remainingPercent = Math.round(remaining / 100);
				const base = Math.floor(remainingPercent / untouched.length);
				let remainder = remainingPercent - base * untouched.length;
				const untouchedIds = new Set(untouched.map(member => member.id));
				this.shareRows = this.shareRows.map(member => {
					if (!untouchedIds.has(member.id)) {
						return member;
					}
					const sharePercent = base + (remainder > 0 ? 1 : 0);
					remainder -= remainder > 0 ? 1 : 0;
					return {
						...member,
						sharePercent
					};
				});
			},
		initials(name) {
			return String(name || '?')
				.split(/\s+/)
				.filter(Boolean)
				.slice(0, 2)
				.map(part => part.charAt(0).toUpperCase())
				.join('') || '?';
		},
		async addMembers() {
			if (!this.canManageProject || this.newMembers.length === 0 || this.addingMembers) {
				return;
			}
			this.addingMembers = true;
			let addedCount = 0;
			const failedMembers = [];

			for (const member of this.newMembers) {
				try {
					await axios.post(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/members`), {
						userId: member.id
					});
					addedCount++;
				} catch (e) {
					failedMembers.push(member);
					showRequestError(e, this.$texts.areaDetail.memberAddError(member.displayName || member.id), 'Failed to add member');
				}
			}

			this.newMembers = failedMembers;
			this.addingMembers = false;

			if (addedCount > 0) {
				showToast(this.$texts.areaDetail.memberAdded());
				await this.fetchProjectSettings();
				this.$emit('refresh-projects');
			}
		},
		async removeMember(userId) {
			if (!this.canManageProject || this.hasActiveEntries || this.removingMemberId) {
				return;
			}
			const member = this.shareRows.find(candidate => candidate.id === userId);
			const confirmed = await this.openConfirm({
				title: this.$texts.areaDetail.removeMember(),
				message: member?.isFormer
					? this.$texts.areaDetail.removeFormerMemberMessage()
					: this.$texts.areaDetail.removeMemberMessage(),
				confirmLabel: this.$texts.areaDetail.removeMember(),
				confirmVariant: 'danger'
			});
			if (!confirmed) {
				return;
			}

			this.removingMemberId = userId;
			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/members/${userId}`));
				await this.fetchProjectSettings();
				this.$emit('refresh-projects');
				showToast(this.$texts.areaDetail.memberRemoved());
			} catch (e) {
				showRequestError(e, this.$texts.areaDetail.memberRemoveError(), 'Failed to remove member');
			}
			this.removingMemberId = null;
		},
		async transferOwnership(member) {
			if (!this.canManageProject || !member?.isActive || this.transferringOwnerId) {
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.areaSettings.transferOwnership(),
				message: this.$texts.areaSettings.transferOwnershipMessage(member.displayName),
				confirmLabel: this.$texts.areaSettings.transferOwnership(),
			});
			if (!confirmed) {
				return;
			}

			this.transferringOwnerId = member.id;
			try {
				const response = await axios.put(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/owner`), {
					userId: member.id
				});
				this.project.owner_id = response.data.owner_id;
				this.project.is_owner = false;
				this.project.members = response.data.members || this.project.members;
				this.resetShareRows();
				this.selectedOwnerId = '';
				this.$emit('refresh-projects');
				showToast(this.$texts.areaSettings.ownershipTransferred(member.displayName));
				this.goBackToProject();
			} catch (e) {
				showRequestError(e, this.$texts.areaSettings.ownershipTransferError(), 'Failed to transfer project ownership');
			}
			this.transferringOwnerId = null;
		},
		transferSelectedOwnership() {
			const member = this.transferableOwnerMembers.find(candidate => candidate.id === this.selectedOwnerId);
			if (member) {
				this.transferOwnership(member);
			}
		},
		async saveProjectShares() {
			if (!this.canManageProject) {
				return;
			}
			if (!this.sharesSumValid) {
				showToast(this.$texts.areaSettings.shareSumInvalid(), 'error');
				return;
			}
			this.savingShares = true;
			try {
				const response = await axios.put(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/shares`), {
					shares: this.shareRows.map(member => ({
						userId: member.id,
						shareBasisPoints: this.percentToBasisPoints(member.sharePercent)
					}))
				});
				this.project.members = response.data.members || this.project.members;
				this.resetShareRows();
				this.$emit('refresh-projects');
				showToast(this.$texts.areaSettings.sharesSaved());
			} catch (e) {
				showRequestError(e, this.$texts.areaSettings.sharesSaveError(), 'Failed to update project shares');
			}
			this.savingShares = false;
		},
		async toggleArchiveProject() {
			if (!this.canManageProject) {
				return;
			}
			const action = this.project.is_archived ? 'unarchive' : 'archive';
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/${action}`));
				this.project.is_archived = !this.project.is_archived;
				this.$emit('refresh-projects');
				showToast(this.project.is_archived ? this.$texts.areaSettings.archived() : this.$texts.areaSettings.unarchived());
			} catch (e) {
				showRequestError(e, this.$texts.areaSettings.statusSaveError(), 'Failed to toggle archive project');
			}
		},
		async deleteProject() {
			if (!this.canManageProject) {
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.areaSettings.deleteTitle(),
				message: this.$texts.areaSettings.deleteMessage(),
				confirmLabel: this.$texts.areaSettings.deleteConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) {
				return;
			}

			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/projects/${this.projectId}`));
				this.$emit('refresh-projects');
				showToast(this.$texts.areaSettings.deleted());
				this.$router.push({ name: 'projects' });
			} catch (e) {
				showRequestError(e, this.$texts.areaSettings.deleteError(), 'Failed to delete project');
			}
		},
		onProjectScopedSettingsChanged() {
			this.$emit('refresh-projects');
		}
	}
}
</script>

<style scoped>
.settings-section {
	display: block;
	margin: 0;
	width: 100%;
	box-sizing: border-box;
}

.settings-content {
	box-sizing: border-box;
	margin: 0 calc(var(--default-grid-baseline, 4px) * 7);
	max-width: 900px;
	width: calc(100% - var(--default-grid-baseline, 4px) * 7 * 2);
}

.settings-header {
	margin-bottom: 28px;
}

.settings-hint {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	margin: 0;
}

.member-management-lock-note {
	margin: 28px 0;
	padding: 12px 16px;
	background-color: var(--color-background-hover, rgba(127, 127, 127, 0.08));
	border-radius: var(--border-radius-large, 8px);
}

.former-member-note {
	margin-top: 12px;
	padding: 12px 16px;
	background-color: var(--color-background-hover, rgba(127, 127, 127, 0.08));
	border-radius: var(--border-radius-large, 8px);
}

.settings-block {
	margin-top: 30px;
}

.settings-block h3 {
	margin: 0 0 16px;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.project-edit-form {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto auto;
	gap: 16px;
	align-items: end;
}

.project-edit-form .form-group {
	margin-bottom: 0;
}

.project-edit-form .form-control,
.project-save-button {
	//min-height: 44px !important;
	//height: 44px !important;
}

.project-color-field {
	min-width: 112px;
}

.project-save-field {
	display: flex;
	align-items: flex-end;
	justify-content: flex-end;
	min-width: 140px;
}

.ownership-transfer-form {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: 12px;
	align-items: center;
	margin-top: 16px;
}

.ownership-transfer-form .form-control,
.ownership-transfer-form .cobudget-button {
	min-height: var(--default-clickable-area, 44px);
	margin: 0;
}

.member-management {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: 12px;
	align-items: start;
	margin: 16px 0;
}

.member-search-field {
	min-width: 0;
}

.member-add-button {
	min-height: var(--default-clickable-area, 44px) !important;
	height: var(--default-clickable-area, 44px) !important;
	align-self: start;
}

.member-add-button :deep(.cobudget-button__label) {
	line-height: 1;
	color: inherit;
}

.member-add-button.cobudget-button,
:deep(.member-add-button.cobudget-button) {
	min-height: var(--default-clickable-area, 44px) !important;
	height: var(--default-clickable-area, 44px) !important;
	padding-top: 0 !important;
	padding-bottom: 0 !important;
  margin: 0;
}

.share-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
  margin-bottom: 8px;
}

.share-tools {
	display: flex;
	gap: 10px;
	margin: 0;
	flex-wrap: wrap;
}

.share-table {
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	overflow: hidden;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.share-row {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 160px;
	gap: 14px;
	align-items: center;
	padding: 12px 14px;
	border-top: 1px solid var(--cobudget-border, #ddd);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.share-row > div {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.share-table.has-member-actions .share-row {
	grid-template-columns: minmax(0, 1fr) 160px 96px;
}

.share-row:first-child {
	border-top: 0;
}

.share-row-header {
  background: var(--cobudget-surface-muted, #f5f5f5);
  color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
  text-align: left;
  padding: 4px 10px;
}

.share-row-header > div {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.share-percent-header {
	text-align: right;
}

.member-action-header {
	text-align: right;
}

.share-member {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
	font-weight: 600;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.share-member span:not(.member-avatar) {
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.member-avatar {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: var(--color-primary-element-light, #e5f3fb);
	color: var(--color-primary, #0082c9);
	font-size: var(--cobudget-font-sm);
	font-weight: 700;
	flex: 0 0 auto;
}

.share-input-wrap {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
}

.share-input {
	max-width: 120px;
	text-align: right;
}

.member-row-actions {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	min-width: 0;
}

.former-member-badge {
	display: inline-flex;
	align-items: center;
	padding: 3px 8px;
	border-radius: 999px;
	background: var(--cobudget-surface-strong);
	color: var(--cobudget-text-muted) !important;
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
}

.share-summary {
	margin-top: 10px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-weight: 600;
}

.share-summary.invalid {
	color: var(--cobudget-error);
}

.share-actions {
	justify-content: flex-end;
	margin-top: 0;
}

.form-group {
	margin-bottom: 22px;
}

.form-group label {
	display: block;
	font-weight: 600;
	margin-bottom: 8px;
	color: var(--cobudget-text, var(--color-main-text, #222));
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
}

.form-control:focus {
	border-color: var(--color-primary, #0082c9);
	outline: none;
}

.color-picker-wrapper {
	display: flex;
	align-items: center;
	gap: 12px;
	height: 38px;
}

.color-preview {
	display: inline-flex;
	align-items: center;
	justify-content: center;
  width: 38px;
  height: 38px;
  margin-bottom:0!important;
	border: 1px solid var(--cobudget-border, #ccc);
	border-radius: var(--border-radius, 4px);
	cursor: pointer;
	background-color: transparent;
	background-image: linear-gradient(45deg, #e0e0e0 25%, transparent 25%), linear-gradient(-45deg, #e0e0e0 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e0e0e0 75%), linear-gradient(-45deg, transparent 75%, #e0e0e0 75%);
	background-size: 10px 10px;
	background-position: 0 0, 0 5px, 5px -5px, -5px 0;
	flex-shrink: 0;
}

.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}

.settings-actions {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: 16px;
}

.danger-actions {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
}

.header-actions {
	justify-content: flex-end;
	margin-left: auto;
}

.btn-primary,
.btn-secondary {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	border-radius: var(--border-radius-large, 6px);
	cursor: pointer;
	font-size: var(--cobudget-font-base);
	font-weight: 600;
	min-height: 36px;
	padding: 8px 16px;
}

.btn-primary {
	background: var(--color-primary, #0082c9);
	color: var(--cobudget-primary-text, #fff);
	border: none;
}

.btn-primary:hover:not(:disabled) {
	background: var(--color-primary-hover, #006aa3);
}

.btn-secondary {
	background: var(--cobudget-surface-muted, var(--color-background-dark, #eee));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border: none;
}

.btn-primary:disabled,
.btn-secondary:disabled {
	opacity: 0.45;
	cursor: default;
}

.color-clear-btn {
  margin-top:0px!important;
	min-width: 38px !important;
	width: 38px !important;
	min-height: 38px !important;
	height: 38px !important;
	padding: 0 !important;
	border-color: transparent !important;
	background: transparent !important;
	box-shadow: none !important;
	color: var(--cobudget-text, #222) !important;
}

.color-clear-btn:hover:not(:disabled),
.color-clear-btn:focus-visible:not(:disabled) {
	border-color: transparent !important;
	background: var(--cobudget-surface-muted, #f5f5f5) !important;
	box-shadow: none !important;
}

.loading {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

@media (max-width: 768px) {
	.settings-section {
		margin: 0;
		width: 100%;
	}

	.settings-content {
		margin: 16px;
		width: calc(100% - 32px);
	}

	.settings-actions {
		align-items: stretch;
		flex-direction: column;
	}

	.header-actions {
		margin-left: 0;
	}

	.header-actions.danger-actions {
		flex-direction: row;
		width: auto;
	}

	.ownership-transfer-form {
		grid-template-columns: 1fr;
	}

	.member-management {
		grid-template-columns: 1fr;
	}

	.member-add-button {
		width: 100%;
	}

	.project-edit-form {
		grid-template-columns: 1fr;
	}

	.project-save-field {
		width: 100%;
		min-width: 0;
	}

	.share-row {
		grid-template-columns: 1fr;
		gap: 8px;
	}

	.share-table.has-member-actions .share-row {
		grid-template-columns: 1fr;
	}

	.share-percent-header {
		text-align: left;
	}

	.share-input-wrap {
		justify-content: flex-start;
	}

	.member-row-actions {
		justify-content: flex-start;
	}

	.share-toolbar {
		align-items: stretch;
		flex-direction: column;
	}

	.danger-actions {
		flex-direction: column;
		width: 100%;
	}

	.header-actions.danger-actions {
		flex-direction: row;
		width: auto;
	}

	.btn-primary,
	.btn-secondary,
	.project-save-button {
		width: 100%;
	}

	.header-actions .project-delete-button,
	.header-actions .project-archive-button {
		min-width: var(--cobudget-icon-button-size, 44px);
		width: var(--cobudget-icon-button-size, 44px);
	}
}
</style>
