<template>
	<div class="cobudget-project-settings settings-section">
		<AppPageHeader>
			<template #title><span class="cobudget-page-title-with-back">
				<NcButton variant="tertiary" class="cobudget-native-button cobudget-header-back-button" :aria-label="$texts.areaSettings.backToArea()" :title="$texts.areaSettings.backToArea()" @click="goBackToProject"><template #icon><ArrowLeftIcon :size="20" /></template></NcButton>
				<ReportPeriodPicker navigation-only force-navigation area-navigation :period-enabled="false" :title="$texts.areaSettings.title()" label="" :disabled="busy" />
			</span></template>
		</AppPageHeader>
		<div class="settings-content">
			<p v-if="loading" class="settings-loading" role="status"><NcLoadingIcon :size="20" /> {{ $texts.areaSettings.loading() }}</p>
			<NcNoteCard v-else-if="loadError" type="error"><p>{{ loadError }}</p><NcButton @click="fetchProjectSettings">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
			<template v-else-if="project">
				<NcNoteCard v-if="!canManageProject" type="info" :text="$texts.areaSettings.ownerOnly()" />
				<section class="settings-block settings-general-block">
					<form class="project-edit-form" :aria-busy="savingProject" @submit.prevent="updateProject('name')">
						<NcFormGroup class="cobudget-form-group project-name-field">
							<template #label><label class="cobudget-form-label" for="project-settings-name">{{ $texts.areaSettings.areaName() }}</label></template>
							<NcInputField id="project-settings-name" ref="projectNameInput" v-model="editProjectData.name" label-outside :label="$texts.areaSettings.areaName()" required maxlength="128" :disabled="identityDisabled" :error="projectNameInvalid" :helper-text="projectNameInvalid ? $texts.areaSettings.nameRequired() : ''" @blur="updateProject('name')" @keydown.enter.prevent="$event.target.blur()" class="cobudget-native-field" />
						</NcFormGroup>
						<NcFormGroup class="cobudget-form-group project-color-field" :label="$texts.areaSettings.areaColor()">
							<NcColorPicker popover-base-class="cobudget-menu-popover" :model-value="editProjectData.color || undefined" clearable @update:model-value="editColorChanged">
								<NcButton class="cobudget-native-button" variant="secondary" :disabled="identityDisabled" :aria-label="$texts.areaSettings.selectAreaColor()"><template #icon><span class="area-color-preview" :style="{ backgroundColor: editProjectData.color || 'transparent' }" /></template>{{ editProjectData.color || $texts.areaSettings.selectAreaColor() }}</NcButton>
							</NcColorPicker>
						</NcFormGroup>
					</form>
					<p v-if="savingProject" class="settings-loading" role="status"><NcLoadingIcon :size="20" /> {{ $texts.common.saveBusy() }}</p>
					<NcNoteCard v-if="projectError" type="error"><p>{{ projectError }}</p><NcButton :disabled="identityDisabled || savingProject" @click="flushProjectChanges">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
				</section>

				<section class="settings-block">
					<h3 class="cobudget-section-heading">{{ $texts.areaDetail.membersTitle() }}</h3>
					<div class="member-section-header">
						<p class="settings-hint">{{ $texts.areaSettings.membersSummary() }}</p>
						<div v-if="canManageProject" class="member-list-actions">
							<NcButton v-if="projectMemberCount > 1" class="cobudget-native-button" variant="secondary" :disabled="memberManagementLocked || hasFormerMembers || busy" @click="openShareDialog"><template #icon><PencilIcon :size="20" /></template>{{ $texts.areaSettings.changeShares() }}</NcButton>
							<NcButton v-if="canAddProjectMembers" class="cobudget-native-button" variant="secondary" :disabled="memberManagementLocked || busy" @click="openMemberDialog"><template #icon><PlusIcon :size="20" /></template>{{ $texts.common.add() }}</NcButton>
						</div>
					</div>
					<NcNoteCard v-if="projectMemberCount === 1" type="info" :text="$texts.areaSettings.personalAreaMembersHint()" />
					<NcNoteCard v-if="memberManagementLocked" type="info" :text="memberManagementLockHint" />
					<NcNoteCard v-if="hasFormerMembers" type="warning" :text="$texts.areaSettings.resolveFormerMember()" />
					<div v-if="projectMemberCount > 1" class="share-editor-table-wrapper member-list">
						<table class="share-editor-table member-table" :aria-label="$texts.areaSettings.areaShareTable()">
							<colgroup><col /><col class="share-column" /><col v-if="canRemoveProjectMembers || canChangeAreaAdmin" class="member-actions-column" /></colgroup>
							<thead><tr><th scope="col">{{ $texts.areaSettings.member() }}</th><th scope="col" class="member-share-cell">{{ $texts.areaSettings.share() }} (%)</th><th v-if="canRemoveProjectMembers || canChangeAreaAdmin" scope="col"><span class="hidden-visually">{{ $texts.common.edit() }}</span></th></tr></thead>
							<tbody>
								<tr v-for="member in savedShareRows" :key="member.id">
									<th scope="row">
										<div class="member-identity">
											<NcAvatar class="member-avatar" :user="member.isFormer ? undefined : member.id" :display-name="member.displayName" :is-no-user="member.isFormer" :size="32" hide-status disable-menu />
											<div class="share-member-info member-info">
												<span class="member-name">{{ member.displayName }}</span>
												<NcChip v-if="member.id === project.owner_id" :text="$texts.areaSettings.areaAdmin()" no-close variant="secondary" />
												<NcChip v-if="member.isFormer" :text="$texts.areaSettings.formerMember()" no-close variant="secondary" />
											</div>
										</div>
									</th>
									<td class="member-share-cell"><span class="member-share-value" :aria-label="`${$texts.areaSettings.share()}: ${formatSharePercent(percentToBasisPoints(member.sharePercent))}%`">{{ formatSharePercent(percentToBasisPoints(member.sharePercent)) }}%</span></td>
									<td v-if="canRemoveProjectMembers || canChangeAreaAdmin">
										<div class="member-row-actions">
											<NcButton v-if="member.id === project.owner_id && canChangeAreaAdmin" variant="tertiary" :disabled="busy" :aria-label="$texts.areaSettings.changeAreaAdmin()" :title="$texts.areaSettings.changeAreaAdmin()" @click="openOwnerDialog"><template #icon><AccountSwitchIcon :size="20" /></template></NcButton>
											<DisabledActionHint :reason="memberManagementLocked ? memberManagementLockHint : busy ? $texts.common.waitBusy() : ''" :label="$texts.areaDetail.removeMember()" v-else-if="member.id !== project.owner_id && canRemoveProjectMembers"><NcButton variant="tertiary" :disabled="memberManagementLocked || busy" :aria-label="`${$texts.areaDetail.removeMember()}: ${member.displayName}`" :title="$texts.areaDetail.removeMember()" @click="removeMember(member.id)"><template #icon><DeleteIcon :size="20" /></template></NcButton></DisabledActionHint>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<NcNoteCard v-if="memberError" type="error" :text="memberError" />
				</section>
				<section v-if="canManageProject" class="settings-block settings-master-data-block"><ProjectScopedSettings class="area-master-data" :project-id="projectId" @changed="onProjectScopedSettingsChanged" /></section>

				<template v-if="canManageProject">
					<section class="settings-block">
						<h3 class="cobudget-section-heading">{{ isArchived ? $texts.areaSettings.unarchiveTitle() : $texts.areaSettings.archiveTitle() }}</h3>
						<p class="settings-hint">{{ isArchived ? $texts.areaSettings.unarchiveHint() : $texts.areaSettings.archiveHint() }}</p>
						<NcNoteCard v-if="availabilityError" id="area-action-availability" type="error"><p>{{ availabilityError }}</p><NcButton :disabled="actionLoading || busy" @click="refreshActionAvailability">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
						<NcNoteCard v-if="archiveError" type="error" :text="archiveError" />
						<DisabledActionHint :reason="archiveActionHint" :label="isArchived ? $texts.areaSettings.unarchive() : $texts.areaSettings.archive()"><NcButton class="area-action-button cobudget-native-button" variant="secondary" :disabled="busy || actionLoading || !!availabilityError || !!archiveBlockedReason" :aria-describedby="availabilityError ? 'area-action-availability' : undefined" @click="toggleArchiveProject"><template #icon><ArchiveIcon :size="20" /></template>{{ isArchived ? $texts.areaSettings.unarchive() : $texts.areaSettings.archive() }}</NcButton></DisabledActionHint>
					</section>
					<section class="settings-block">
						<h3 class="cobudget-section-heading">{{ $texts.areaSettings.deleteTitle() }}</h3>
						<p class="settings-hint">{{ $texts.areaSettings.deleteHint() }}</p>
						<NcNoteCard v-if="deleteError" type="error" :text="deleteError" />
						<DisabledActionHint :reason="deleteActionHint" :label="$texts.areaSettings.deleteConfirm()" ><NcButton class="area-action-button" variant="error" :disabled="busy || actionLoading || !!availabilityError || !!deleteBlockedReason" :aria-describedby="availabilityError ? 'area-action-availability' : undefined" @click="deleteProject"><template #icon><DeleteIcon :size="20" /></template>{{ $texts.areaSettings.deleteConfirm() }}</NcButton></DisabledActionHint>
					</section>
				</template>
			</template>
		</div>
		<DialogTransition :show="!!(memberDialogOpen)" v-slot="{ returnFocus }">
			<NcModal :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog area-settings-modal" :show="true" label-id="member-editor-title" :no-close="addingMembers || loading || !!confirmDialog" :close-on-click-outside="!addingMembers && !loading && !confirmDialog" @update:show="closeMemberDialog" @keydown.esc.stop.prevent="closeMemberDialog">
				<div class="area-settings-editor">
					<h2 id="member-editor-title">{{ $texts.areaDetail.addMember() }}</h2>
					<NcFormGroup class="cobudget-form-group">
						<template #label><label class="cobudget-form-label" for="project-settings-members">{{ $texts.areaDetail.membersTitle() }}</label></template>
						<NcSelect v-model="newMembers" input-id="project-settings-members" class="cobudget-native-select member-search-field" label-outside :input-label="$texts.areaDetail.membersTitle()" :options="availableMembers" label="displayName" multiple :filterable="false" :loading="memberSearchLoading" :disabled="busy || !canAddProjectMembers || memberManagementLocked" :placeholder="$texts.areas.searchUsers()" @search="searchMembers">
							<template #option="member">
								<span class="member-identity member-select-identity"><NcAvatar class="member-avatar" :user="member.id" :display-name="member.displayName" :size="32" hide-status disable-menu /><span class="member-info">{{ member.displayName }}</span></span>
							</template>
							<template #selected-option="member">
								<span class="member-identity member-select-identity"><NcAvatar class="member-avatar" :user="member.id" :display-name="member.displayName" :size="24" hide-status disable-menu /><span class="member-info">{{ member.displayName }}</span></span>
							</template>
							<template #no-options>{{ memberSearchTerm ? $texts.memberSearch.noResults() : $texts.areas.searchUsers() }}</template>
						</NcSelect>
					</NcFormGroup>
					<NcNoteCard v-if="memberManagementLocked" type="info" :text="memberManagementLockHint" />
					<NcNoteCard v-if="memberSearchError" type="error"><p>{{ memberSearchError }}</p><NcButton :disabled="memberSearchLoading || busy" @click="fetchMemberOptions">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
					<NcNoteCard v-if="memberAddError" type="error" :text="memberAddError" />
					<div class="area-settings-editor-actions">
						<NcButton variant="tertiary" :disabled="addingMembers || loading || !!confirmDialog" @click="cancelMemberDialog">{{ $texts.common.cancel() }}</NcButton>
						<NcButton variant="primary" :disabled="!canSubmitMembers" @click="addMembers"><template v-if="addingMembers" #icon><NcLoadingIcon :size="20" /></template>{{ addingMembers ? $texts.common.saveBusy() : $texts.common.save() }}</NcButton>
					</div>
				</div>
			</NcModal>
		</DialogTransition>
		<DialogTransition :show="!!(ownerDialogOpen)" v-slot="{ returnFocus }">
			<NcModal :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog area-settings-modal" :show="true" label-id="owner-editor-title" :no-close="!!transferringOwnerId || loading || !!confirmDialog" :close-on-click-outside="!transferringOwnerId && !loading && !confirmDialog" @update:show="closeOwnerDialog" @keydown.esc.stop.prevent="closeOwnerDialog">
				<div class="area-settings-editor">
					<h2 id="owner-editor-title">{{ $texts.areaSettings.changeAreaAdmin() }}</h2>
					<NcFormGroup class="cobudget-form-group" :description="$texts.areaSettings.areaAdminHint()">
						<template #label><label class="cobudget-form-label" for="project-settings-owner">{{ $texts.areaSettings.selectAreaAdmin() }}</label></template>
						<NcSelect input-id="project-settings-owner" class="cobudget-native-select" label-outside :input-label="$texts.areaSettings.selectAreaAdmin()" :model-value="transferableOwnerMembers.find(member => member.id === selectedOwnerId) || null" :options="transferableOwnerMembers" label="displayName" :disabled="busy || !canManageProject" @update:model-value="selectedOwnerId = $event?.id || ''">
							<template #option="member">
								<span class="member-identity member-select-identity"><NcAvatar class="member-avatar" :user="member.id" :display-name="member.displayName" :size="32" hide-status disable-menu /><span class="member-info">{{ member.displayName }}</span></span>
							</template>
							<template #selected-option="member">
								<span class="member-identity member-select-identity"><NcAvatar class="member-avatar" :user="member.id" :display-name="member.displayName" :size="24" hide-status disable-menu /><span class="member-info">{{ member.displayName }}</span></span>
							</template>
						</NcSelect>
					</NcFormGroup>
					<NcNoteCard v-if="ownerError" type="error" :text="ownerError" />
					<div class="area-settings-editor-actions">
						<NcButton variant="tertiary" :disabled="!!transferringOwnerId || loading || !!confirmDialog" @click="cancelOwnerDialog">{{ $texts.common.cancel() }}</NcButton>
						<NcButton variant="primary" :disabled="!canSubmitOwner" @click="transferSelectedOwnership"><template #icon><NcLoadingIcon v-if="transferringOwnerId" :size="20" /><AccountSwitchIcon v-else :size="20" /></template>{{ transferringOwnerId ? $texts.common.saveBusy() : $texts.areaSettings.transfer() }}</NcButton>
					</div>
				</div>
			</NcModal>
		</DialogTransition>
		<DialogTransition :show="!!(shareDialogOpen)" v-slot="{ returnFocus }">
			<NcModal :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog area-settings-modal" :show="true" label-id="share-editor-title" :no-close="savingShares || loading || !!confirmDialog" :close-on-click-outside="!savingShares && !loading && !confirmDialog" @update:show="closeShareDialog" @keydown.esc.stop.prevent="closeShareDialog">
				<div class="area-settings-editor">
					<h2 id="share-editor-title">{{ $texts.areaSettings.changeShares() }}</h2>
					<p class="settings-hint">{{ $texts.areaSettings.sharesHint() }}</p>
					<div class="share-toolbar share-tools">
						<NcButton variant="tertiary" :disabled="!canEditShares || busy" @click="setEqualShares">{{ $texts.areaSettings.splitEqually() }}</NcButton>
						<NcButton variant="tertiary" :disabled="!canEditShares || !canDistributeShareRemainder || busy" @click="distributeShareRemainder">{{ $texts.areaSettings.distributeRemainder() }}</NcButton>
					</div>
					<div class="share-editor-table-wrapper">
						<table class="share-editor-table" :aria-label="$texts.areaSettings.areaShareTable()">
							<colgroup><col /><col class="share-column" /></colgroup>
							<thead><tr><th scope="col">{{ $texts.areaSettings.member() }}</th><th scope="col">{{ $texts.areaSettings.share() }} (%)</th></tr></thead>
							<tbody>
								<tr v-for="member in shareRows" :key="member.id">
									<th scope="row">
										<div class="member-identity">
											<NcAvatar class="member-avatar" :user="member.isFormer ? undefined : member.id" :display-name="member.displayName" :is-no-user="member.isFormer" :size="32" hide-status disable-menu />
											<div class="share-member-info member-info"><span class="member-name">{{ member.displayName }}</span><NcChip v-if="member.id === project?.owner_id" :text="$texts.areaSettings.areaAdmin()" no-close variant="secondary" /></div>
										</div>
									</th>
									<td>
										<NcInputField :id="`member-share-${member.id}`" :model-value="String(member.sharePercent)" class="share-input cobudget-native-field" type="number" min="0" max="100" step="1" inputmode="numeric" :label="`${$texts.areaSettings.share()}: ${member.displayName} (%)`" :aria-label="`${$texts.areaSettings.share()}: ${member.displayName} (%)`" label-outside :disabled="!canEditShares || busy" @update:model-value="member.sharePercent = $event; markShareEdited(member.id)" @blur="normalizeShareInput(member)" />
									</td>
								</tr>
							</tbody>
							<tfoot><tr><th scope="row">{{ $texts.areaSettings.sum() }}</th><td>{{ formatSharePercent(sharesSumBasisPoints) }}%</td></tr></tfoot>
						</table>
					</div>
					<NcNoteCard v-if="memberManagementLocked" type="info" :text="memberManagementLockHint" />
					<NcNoteCard v-if="!sharesSumValid" type="warning" :text="$texts.areaSettings.shareSumInvalid()" />
					<NcNoteCard v-if="sharesError" type="error" :text="sharesError" />
					<div class="area-settings-editor-actions">
						<NcButton variant="tertiary" :disabled="savingShares || loading || !!confirmDialog" @click="cancelShareDialog">{{ $texts.common.cancel() }}</NcButton>
						<NcButton variant="primary" :disabled="!canSaveShares" @click="saveProjectShares">{{ savingShares ? $texts.common.saveBusy() : $texts.areaSettings.saveShares() }}</NcButton>
					</div>
				</div>
			</NcModal>
		</DialogTransition>
		<DialogTransition :show="!!(confirmDialog)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog area-settings-confirmation cobudget-standard-dialog" :name="confirmDialog.title" :message="confirmDialog.message" close-on-click-outside @closing="resolveConfirm(false)">
				<template #actions><NcButton variant="tertiary" @click="resolveConfirm(false)">{{ $texts.common.cancel() }}</NcButton><NcButton :variant="confirmDialog.confirmVariant === 'danger' ? 'error' : 'primary'" @click="resolveConfirm(true)">{{ confirmDialog.confirmLabel }}</NcButton></template>
			</NcDialog>
		</DialogTransition>
	</div>
</template>

<script>
import DialogTransition from '../components/DialogTransition.vue'
import DisabledActionHint from '../components/DisabledActionHint.vue'
import { getAppLocale } from '../utils/formatMoney'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ProjectScopedSettings from '../components/ProjectScopedSettings.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import AccountSwitchIcon from 'vue-material-design-icons/AccountSwitch.vue'
import { extractError, showToast } from '../services/notifications'

export default {
	name: 'ProjectSettingsView',
	components: { ReportPeriodPicker, DialogTransition, DisabledActionHint,
		AppPageHeader,
		NcButton,
		NcInputField,
		NcSelect,
		NcFormGroup,
		NcColorPicker,
		NcDialog,
		NcModal,
		NcNoteCard,
		NcLoadingIcon,
		NcChip,
		NcAvatar,
		PlusIcon,
		PencilIcon,
		ProjectScopedSettings,
		ArrowLeftIcon,
		DeleteIcon,
		ArchiveIcon,
		AccountSwitchIcon
	},
	props: ['id'],
	emits: ['refresh-projects'],
	data() {
		return {
			project: null,
			loading: false, loadError: '', projectError: '', memberError: '', ownerError: '', sharesError: '', archiveError: '', deleteError: '', availabilityError: '',
			memberDialogOpen: false, memberAddError: '', ownerDialogOpen: false, shareDialogOpen: false,
			projectSaveQueue: {}, projectSaveSequence: 0, projectNameInvalid: false,
			loadSequence: 0, actionLoading: false, savingProject: false, archiving: false, deleting: false,
			memberSearchTerm: '', memberSearchOptions: [], memberSearchLoading: false, memberSearchError: '', memberSearchSequence: 0, memberSearchTimer: null,
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
		savedShareRows() {
			const members = Array.isArray(this.project?.members) ? this.project.members : [];
			return members.map(member => ({
				id: member.id,
				displayName: member.displayName || member.id,
				isFormer: member.isFormer === true || member.is_former === true,
				isActive: member.isActive !== false && member.is_active !== false,
				sharePercent: this.basisPointsToPercent(member.shareBasisPoints ?? member.share_basis_points ?? 0)
			}));
		},
		canEditShares() { return this.shareDialogOpen && this.projectMemberCount > 1 && this.canManageProject && !this.memberManagementLocked && !this.hasFormerMembers; },
		sharesChanged() { return this.currentShareSignature !== this.originalShareSignature; },
		identityDisabled() { return !this.canManageProject || this.loading || this.savingShares || this.addingMembers || !!this.removingMemberId || !!this.transferringOwnerId || this.archiving || this.deleting || !!this.confirmDialog; },
		busy() { return this.loading || this.savingProject || this.savingShares || this.addingMembers || !!this.removingMemberId || !!this.transferringOwnerId || this.archiving || this.deleting || !!this.confirmDialog; },
		isArchived() { return [true, 1, '1', 'true'].includes(this.project?.is_archived); },
		availableMembers() { return this.memberSearchOptions.filter(member => !this.currentMemberIds.includes(member.id)); },
		deleteActionHint() {
			if (this.availabilityError) return this.availabilityError;
			if (this.deleteBlockedReason) return this.deleteBlockedReason;
			if (this.deleting) return this.$texts.common.deleteBusy();
			if (this.actionLoading || this.loading) return this.$texts.common.loading();
			return this.busy ? this.$texts.common.waitBusy() : '';
		},
		deleteBlockedReason() {
			if (!this.canManageProject) return this.$texts.areaSettings.ownerOnly();
			const reason = this.project?.settings_actions?.delete_block_reason;
			if (reason === null) return '';
			if (reason === 'payments_or_history') return this.$texts.areaSettings.deleteBlockedHistory();
			if (reason === 'budget_goals') return this.$texts.areaSettings.deleteBlockedBudget();
			return this.$texts.areaSettings.actionsUnavailable();
		},
		archiveActionHint() {
			if (this.availabilityError) return this.availabilityError;
			if (this.archiveBlockedReason) return this.archiveBlockedReason;
			if (this.actionLoading || this.loading) return this.$texts.common.loading();
			return this.busy ? this.$texts.common.waitBusy() : '';
		},
		archiveBlockedReason() {
			if (!this.canManageProject) return this.$texts.areaSettings.ownerOnly();
			if (this.isArchived) return '';
			const reason = this.project?.settings_actions?.archive_block_reason;
			if (reason === null) return '';
			if (reason === 'open_shared_payments') return this.$texts.areaSettings.archiveBlockedWithOpenEntries();
			return this.$texts.areaSettings.actionsUnavailable();
		},
		projectId() {
			return this.id;
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
			return this.canEditShares
				&& !this.busy && !this.memberManagementLocked
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
		canSubmitOwner() { return this.ownerDialogOpen && this.canManageProject && !this.busy && this.transferableOwnerMembers.some(member => member.id === this.selectedOwnerId); },
		canSubmitMembers() { return this.memberDialogOpen && this.canAddProjectMembers && !this.memberManagementLocked && this.newMembers.length > 0 && !this.busy; },
		canAddProjectMembers() {
			return !!this.project && this.$enableSharedProjects && this.canManageProject && !this.hasFormerMembers;
		},
		canChangeAreaAdmin() {
			return this.canManageProject && this.transferableOwnerMembers.length > 0;
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
				this.resolveConfirm(false);
				this.resetMemberDialog();
				this.resetOwnerDialog();
				this.resetShareDialog();
				this.projectSaveSequence++;
				this.projectSaveQueue = {};
				this.savingProject = false;
				this.projectNameInvalid = false;
				this.project = null;
				for (const field of ['projectError', 'memberError', 'ownerError', 'sharesError', 'archiveError', 'deleteError', 'availabilityError']) this[field] = '';
				this.searchMembers('');
				this.fetchProjectSettings();
			}
		}
	},
	mounted() { window.addEventListener('focus', this.refreshActionAvailability); },
	beforeUnmount() {
		this.projectSaveSequence++;
		this.projectSaveQueue = {};
		window.removeEventListener('focus', this.refreshActionAvailability);
		clearTimeout(this.memberSearchTimer);
		this.loadSequence++;
		this.memberSearchSequence++;
		this.resolveConfirm(false);
	},
	methods: {
		openShareDialog() {
			if (this.projectMemberCount <= 1 || !this.canManageProject || this.memberManagementLocked || this.hasFormerMembers || this.busy) return;
			this.resetShareDialog();
			this.shareDialogOpen = true;
		},
		resetShareDialog() {
			this.shareDialogOpen = false;
			this.sharesError = '';
			this.resetShareRows();
		},
		cancelShareDialog() {
			if (this.savingShares || this.loading || this.confirmDialog) return;
			this.resetShareDialog();
		},
		async closeShareDialog() {
			if (this.savingShares || this.loading || this.confirmDialog) return;
			const requestProjectId = this.projectId;
			if (this.sharesChanged && !await this.openConfirm({
				title: this.$texts.entry.discardChangesTitle(),
				message: this.$texts.entry.discardChangesMessage(),
				confirmLabel: this.$texts.entry.discardChangesConfirm()
			})) return;
			if (requestProjectId === this.projectId) this.resetShareDialog();
		},
		openOwnerDialog() {
			if (!this.canManageProject || !this.transferableOwnerMembers.length || this.busy) return;
			this.resetOwnerDialog();
			this.ownerDialogOpen = true;
		},
		resetOwnerDialog() {
			this.ownerDialogOpen = false;
			this.selectedOwnerId = '';
			this.ownerError = '';
		},
		cancelOwnerDialog() {
			if (this.transferringOwnerId || this.loading || this.confirmDialog) return;
			this.resetOwnerDialog();
		},
		async closeOwnerDialog() {
			if (this.transferringOwnerId || this.loading || this.confirmDialog) return;
			const requestProjectId = this.projectId;
			if (this.selectedOwnerId && !await this.openConfirm({
				title: this.$texts.entry.discardChangesTitle(),
				message: this.$texts.entry.discardChangesMessage(),
				confirmLabel: this.$texts.entry.discardChangesConfirm()
			})) return;
			if (requestProjectId === this.projectId) this.resetOwnerDialog();
		},
		openMemberDialog() {
			if (!this.canAddProjectMembers || this.memberManagementLocked || this.busy) return;
			this.resetMemberDialog();
			this.memberDialogOpen = true;
		},
		resetMemberDialog() {
			this.memberDialogOpen = false;
			this.newMembers = [];
			this.memberAddError = '';
			this.searchMembers('');
		},
		cancelMemberDialog() {
			if (this.addingMembers || this.loading || this.confirmDialog) return;
			this.resetMemberDialog();
		},
		async closeMemberDialog() {
			if (this.addingMembers || this.loading || this.confirmDialog) return;
			const requestProjectId = this.projectId;
			if (this.newMembers.length && !await this.openConfirm({
				title: this.$texts.entry.discardChangesTitle(),
				message: this.$texts.entry.discardChangesMessage(),
				confirmLabel: this.$texts.entry.discardChangesConfirm()
			})) return;
			if (requestProjectId === this.projectId) this.resetMemberDialog();
		},
		searchMembers(term) {
			clearTimeout(this.memberSearchTimer);
			this.memberSearchTerm = term.trim();
			this.memberSearchSequence++;
			this.memberSearchOptions = [];
			this.memberSearchError = '';
			this.memberSearchLoading = false;
			if (this.memberSearchTerm) this.memberSearchTimer = setTimeout(() => this.fetchMemberOptions(), 300);
		},
		async fetchMemberOptions() {
			if (!this.memberDialogOpen || !this.memberSearchTerm || !this.canAddProjectMembers || this.busy) return;
			const sequence = ++this.memberSearchSequence;
			this.memberSearchLoading = true;
			this.memberSearchError = '';
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/users/search'), { params: { term: this.memberSearchTerm } });
				if (sequence === this.memberSearchSequence) this.memberSearchOptions = response.data || [];
			} catch (error) {
				if (sequence === this.memberSearchSequence) this.memberSearchError = extractError(error, this.$texts.memberSearch.searchError());
			} finally { if (sequence === this.memberSearchSequence) this.memberSearchLoading = false; }
		},
		async refreshActionAvailability() {
			if (this.busy || this.actionLoading || !this.project) return false;
			return this.loadActionAvailability();
		},
		async loadActionAvailability() {
			const id = this.projectId;
			this.actionLoading = true;
			this.availabilityError = '';
			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/projects/${id}`));
				if (id !== this.projectId || !this.project) return false;
				Object.assign(this.project, {
					settings_actions: response.data.settings_actions,
					is_archived: response.data.is_archived,
					is_owner: response.data.is_owner,
					owner_id: response.data.owner_id,
					member_management_locked: response.data.member_management_locked,
					member_management_lock_reason: response.data.member_management_lock_reason,
				});
				if (!response.data.settings_actions) throw new Error(this.$texts.areaSettings.actionsUnavailable());
				return true;
			} catch (error) {
				if (id === this.projectId) this.availabilityError = extractError(error, this.$texts.areaSettings.actionsUnavailable());
				return false;
			} finally { this.actionLoading = false; }
		},
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
			this.shareRows = this.savedShareRows.map(member => ({ ...member }));
			this.originalShareSignature = this.currentShareSignature;
			this.editedShareUserIds = [];
		},
		editColorChanged(color) {
			if (this.identityDisabled) return;
			this.editProjectData.color = color || '';
			return this.updateProject('color');
		},
		async fetchProjectSettings({ quiet = false } = {}) {
			if (!this.projectId) {
				return;
			}
			const sequence = ++this.loadSequence;
			if (!quiet) this.loading = true;
			this.loadError = '';
			try {
				const [projectRes, anyEntriesRes, activeEntriesRes] = await Promise.all([
					axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}`)),
					axios.get(generateUrl('/apps/cobudget/api/entries'), { params: { projectId: this.projectId, limit: 1, offset: 0 } }),
					axios.get(generateUrl('/apps/cobudget/api/entries'), { params: { projectId: this.projectId, isSettled: false, limit: 1, offset: 0 } })
				]);
				if (sequence !== this.loadSequence) return;
				const preserveIdentityDraft = this.project && String(this.project.id) === String(projectRes.data.id)
					&& (this.editProjectData.name !== this.project.name || this.editProjectData.color !== (this.project.color || ''));
				this.project = projectRes.data;
				this.availabilityError = '';
				this.currentUserId = this.getCurrentUserId();
				this.hasAnyEntries = (anyEntriesRes.data?.total || 0) > 0;
				this.hasActiveEntries = (activeEntriesRes.data?.total || 0) > 0;
				this.newMembers = [];
				this.selectedOwnerId = '';
				if (!preserveIdentityDraft) this.resetProjectForm();
				this.resetShareRows();

			} catch (e) {
				if (sequence === this.loadSequence) {
					const message = extractError(e, this.$texts.areaSettings.loadError());
					if (quiet) this.memberError = message;
					else this.loadError = message;
				}
			}
			finally { if (sequence === this.loadSequence) this.loading = false; }
		},
		async updateProject(field = 'name') {
			if (this.identityDisabled || !this.project || !['name', 'color'].includes(field)) return;
			const value = field === 'name' ? this.editProjectData.name.trim() : (this.editProjectData.color || '');
			if (field === 'name') {
				this.projectNameInvalid = !value;
				if (!value) {
					delete this.projectSaveQueue.name;
					return;
				}
			}
			if (!this.savingProject && value === (this.project[field] || '')) {
				delete this.projectSaveQueue[field];
				if (field === 'name') this.editProjectData.name = value;
				if (!Object.keys(this.projectSaveQueue).length) this.projectError = '';
				return;
			}
			this.projectSaveQueue[field] = value;
			return this.flushProjectChanges();
		},
		async flushProjectChanges() {
			if (this.identityDisabled || this.savingProject || !Object.keys(this.projectSaveQueue).length) return;
			const requestProjectId = this.projectId;
			const project = this.project;
			const sequence = ++this.projectSaveSequence;
			this.savingProject = true;
			this.projectError = '';
			try {
				while (Object.keys(this.projectSaveQueue).length) {
					const changes = Object.fromEntries(Object.entries(this.projectSaveQueue).filter(([field, value]) => value !== (project[field] || '')));
					this.projectSaveQueue = {};
					if (!Object.keys(changes).length) continue;
					const values = { name: project.name, color: project.color || '', ...changes };
					try {
						const response = await axios.put(generateUrl(`/apps/cobudget/api/projects/${requestProjectId}`), values);
						if (sequence !== this.projectSaveSequence || project !== this.project || requestProjectId !== this.projectId) return;
						project.name = response.data.name;
						project.color = response.data.color;
						// Only normalize submitted fields whose draft has not changed meanwhile.
						for (const field of Object.keys(changes)) {
							const draft = field === 'name' ? this.editProjectData.name.trim() : (this.editProjectData.color || '');
							if (draft === changes[field]) this.editProjectData[field] = project[field] || '';
						}
						this.$emit('refresh-projects');
					} catch (error) {
						if (sequence !== this.projectSaveSequence || project !== this.project || requestProjectId !== this.projectId) return;
						// Keep the newest committed choices for an explicit retry; do not loop on errors.
						this.projectSaveQueue = { ...changes, ...this.projectSaveQueue };
						this.projectError = extractError(error, this.$texts.areaSettings.saveError());
						return;
					}
				}
			} finally { if (sequence === this.projectSaveSequence) this.savingProject = false; }
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
				const value = Number(basisPoints) / 100;
				return value.toLocaleString(getAppLocale(), { maximumFractionDigits: 0 });
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
			if (!this.canEditShares || this.busy) return;
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
			if (!this.canEditShares || this.busy) return;
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
			const requestProjectId = this.projectId;
			if (!this.canSubmitMembers) {
				return;
			}
			this.addingMembers = true;
			this.memberAddError = '';
			let addedCount = 0;
			const failedMembers = [];

			for (const member of [...this.newMembers]) {
				if (requestProjectId !== this.projectId) break;
				try {
					await axios.post(generateUrl(`/apps/cobudget/api/projects/${requestProjectId}/members`), {
						userId: member.id
					});
					addedCount++;
				} catch (e) {
					failedMembers.push(member);
					if (requestProjectId === this.projectId) this.memberAddError = extractError(e, this.$texts.areaDetail.memberAddError(member.displayName || member.id));
				}
			}


			if (addedCount > 0 && requestProjectId === this.projectId) {
				showToast(this.$texts.areaDetail.memberAdded());
				if (requestProjectId === this.projectId) await this.fetchProjectSettings({ quiet: true });
				this.$emit('refresh-projects');
			}
			if (requestProjectId === this.projectId) {
				this.newMembers = failedMembers;
				if (!failedMembers.length) this.resetMemberDialog();
			}
			this.addingMembers = false;
		},
		async removeMember(userId) {
			const requestProjectId = this.projectId;
			if (!this.canRemoveProjectMembers || this.memberManagementLocked || this.busy || userId === this.project.owner_id) {
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
			if (!confirmed || requestProjectId !== this.projectId) {
				return;
			}

			this.removingMemberId = userId;
			this.memberError = '';
			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/projects/${requestProjectId}/members/${userId}`));
				if (requestProjectId === this.projectId) await this.fetchProjectSettings({ quiet: true });
				this.$emit('refresh-projects');
				showToast(this.$texts.areaDetail.memberRemoved());
			} catch (e) {
				if (requestProjectId === this.projectId) this.memberError = extractError(e, this.$texts.areaDetail.memberRemoveError());
			} finally { this.removingMemberId = null; }
		},
		async transferOwnership(member) {
			const requestProjectId = this.projectId;
			if (!this.canSubmitOwner || !member?.isActive || member.isFormer || member.id !== this.selectedOwnerId) {
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.areaSettings.transferOwnership(),
				message: this.$texts.areaSettings.transferOwnershipMessage(member.displayName),
				confirmLabel: this.$texts.areaSettings.transferOwnership(),
			});
			if (!confirmed || requestProjectId !== this.projectId) {
				return;
			}

			this.transferringOwnerId = member.id;
			this.ownerError = '';
			try {
				const response = await axios.put(generateUrl(`/apps/cobudget/api/projects/${requestProjectId}/owner`), {
					userId: member.id
				});
				if (requestProjectId !== this.projectId) return;
				this.project.owner_id = response.data.owner_id;
				this.project.is_owner = false;
				if (requestProjectId !== this.projectId) return;
				this.project.members = response.data.members || this.project.members;
				this.resetShareRows();
				this.resetOwnerDialog();
				this.$emit('refresh-projects');
				showToast(this.$texts.areaSettings.ownershipTransferred(member.displayName));
				this.goBackToProject();
			} catch (e) {
				if (requestProjectId === this.projectId) this.ownerError = extractError(e, this.$texts.areaSettings.ownershipTransferError());
			} finally { this.transferringOwnerId = null; }
		},
		transferSelectedOwnership() {
			const member = this.transferableOwnerMembers.find(candidate => candidate.id === this.selectedOwnerId);
			if (member) {
				return this.transferOwnership(member);
			}
		},
		async saveProjectShares() {
			const requestProjectId = this.projectId;
			if (!this.canSaveShares) {
				return;
			}
			if (!this.sharesSumValid) {
				this.sharesError = this.$texts.areaSettings.shareSumInvalid();
				return;
			}
			this.savingShares = true;
			this.sharesError = '';
			try {
				const response = await axios.put(generateUrl(`/apps/cobudget/api/projects/${requestProjectId}/shares`), {
					shares: this.shareRows.map(member => ({
						userId: member.id,
						shareBasisPoints: this.percentToBasisPoints(member.sharePercent)
					}))
				});
				if (requestProjectId !== this.projectId) return;
				this.project.members = response.data.members || this.project.members;
				this.resetShareDialog();
				this.$emit('refresh-projects');
				showToast(this.$texts.areaSettings.sharesSaved());
			} catch (e) {
				if (requestProjectId === this.projectId) this.sharesError = extractError(e, this.$texts.areaSettings.sharesSaveError());
			} finally { this.savingShares = false; }
		},
		async toggleArchiveProject() {
			if (!this.canManageProject || this.busy || this.actionLoading || this.archiveBlockedReason || this.availabilityError) return;
			const id = this.projectId;
			this.archiving = true;
			this.archiveError = '';
			try {
				if (!await this.loadActionAvailability() || this.archiveBlockedReason || id !== this.projectId) return;
				const archived = !this.isArchived;
				const confirmed = await this.openConfirm({
					title: archived ? this.$texts.areaSettings.archiveTitle() : this.$texts.areaSettings.unarchiveTitle(),
					message: archived ? this.$texts.areaSettings.archiveConfirmMessage() : this.$texts.areaSettings.unarchiveConfirmMessage(),
					confirmLabel: archived ? this.$texts.areaSettings.archive() : this.$texts.areaSettings.unarchive(),
				});
				if (!confirmed || id !== this.projectId) return;
				if (!await this.loadActionAvailability() || !this.canManageProject || (archived && this.archiveBlockedReason) || id !== this.projectId) return;
				await axios.post(generateUrl(`/apps/cobudget/api/projects/${id}/${archived ? 'archive' : 'unarchive'}`));
				if (id !== this.projectId) return;
				this.project.is_archived = archived;
				this.$emit('refresh-projects');
				showToast(archived ? this.$texts.areaSettings.archived() : this.$texts.areaSettings.unarchived());
			} catch (e) {
				if (id === this.projectId) {
					this.archiveError = extractError(e, this.$texts.areaSettings.statusSaveError());
					await this.loadActionAvailability();
				}
			} finally { this.archiving = false; }
		},
		async deleteProject() {
			if (!this.canManageProject || this.busy || this.actionLoading || this.deleteBlockedReason || this.availabilityError) return;
			const id = this.projectId;
			this.deleting = true;
			this.deleteError = '';
			try {
				if (!await this.loadActionAvailability() || this.deleteBlockedReason || id !== this.projectId) return;
				const confirmed = await this.openConfirm({
					title: this.$texts.areaSettings.deleteTitle(),
					message: this.$texts.areaSettings.deleteMessage(),
					confirmLabel: this.$texts.areaSettings.deleteConfirm(),
					confirmVariant: 'danger'
				});
				if (!confirmed || id !== this.projectId) return;
				await axios.delete(generateUrl(`/apps/cobudget/api/projects/${id}`));
				this.$emit('refresh-projects');
				showToast(this.$texts.areaSettings.deleted());
				if (id === this.projectId) this.$router.push({ name: 'projects' });
			} catch (e) {
				if (id === this.projectId) {
					this.deleteError = extractError(e, this.$texts.areaSettings.deleteError());
					await this.loadActionAvailability();
				}
			} finally { this.deleting = false; }
		},
		onProjectScopedSettingsChanged() {
			this.$emit('refresh-projects');
		}
	}
}
</script>

<style scoped>
@media (max-width: 768px) {
	.member-list { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); }
	.area-settings-modal { --default-clickable-area: var(--cobudget-mobile-touch-size); }
}
.settings-section { display: block; margin: 0; width: 100%; box-sizing: border-box; }
.settings-content { box-sizing: border-box; margin: 0 var(--cobudget-form-inset); max-width: var(--cobudget-form-max-width); width: calc(100% - var(--cobudget-form-inset) * 2); }
.settings-hint { color: var(--color-text-maxcontrast); font-size: var(--font-size, 15px); margin: 0; }
.settings-loading { display: flex; align-items: center; gap: 8px; color: var(--color-text-maxcontrast); }
.settings-block { margin-block-start: 0; padding-block-start: var(--cobudget-settings-heading-gap); }
.settings-block > h3 { margin-block-start: 0; }
.settings-general-block { padding-block-start: 0; }
.settings-master-data-block { padding-block-start: 0; }
.area-master-data { margin-block: 0; }
.area-master-data :deep(.master-section) { padding-block-start: var(--cobudget-settings-heading-gap); }
.area-master-data :deep(.master-section + .master-section) { margin-block-start: 0; }
.area-master-data :deep(.master-section > h3) { margin-block-start: 0; }
.project-edit-form { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: var(--cobudget-content-gap, 16px); align-items: end; }
.project-name-field, .member-search-field { min-width: 0; }
.area-color-preview { display: inline-block; width: 20px; height: 20px; border: 1px solid var(--color-border); border-radius: var(--border-radius-element, 4px); }
.member-section-header { display: flex; flex-wrap: wrap; align-items: flex-start; gap: var(--cobudget-card-gap); margin-block-end: var(--cobudget-section-gap); }
.member-section-header > .settings-hint { flex: 1 1 320px; min-width: 0; }
.member-list-actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: calc(var(--default-grid-baseline, 4px) * 2); margin-inline-start: auto; }
.member-identity { display: flex; align-items: center; gap: var(--cobudget-content-gap); min-width: 0; }
.member-avatar { flex-shrink: 0; }
.member-select-identity { gap: calc(var(--default-grid-baseline, 4px) * 2); max-width: 100%; }
.member-select-identity .member-info { min-width: 0; white-space: normal; }
.member-info { overflow-wrap: anywhere; }
.area-settings-editor { padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding) var(--cobudget-dialog-padding); }
.area-settings-editor h2 { padding-inline-end: var(--cobudget-mobile-touch-size, 44px); margin: 0 0 calc(var(--default-grid-baseline, 4px) * 4); font-size: var(--cobudget-dialog-title-size, 20px); text-align: start; }
.area-settings-editor-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: var(--cobudget-section-gap); }
.cobudget-native-select { width: 100%; min-width: 0; margin-bottom: 0; }
.share-toolbar { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin: var(--cobudget-section-gap, 24px) 0 8px; }
.share-tools { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
.member-name { overflow-wrap: anywhere; font-size: var(--font-size, 15px); font-weight: var(--font-weight-element, 500); color: var(--color-main-text); }
.member-share-value { text-align: end; font-variant-numeric: tabular-nums; color: var(--color-main-text); }
.share-input { min-width: 0; }
.member-row-actions { display: flex; justify-content: flex-end; }
/* Keep the split aligned as a table, including on narrow dialogs. */
.share-editor-table-wrapper { overflow-x: auto; }
.share-editor-table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
/* Rows are not actions; native controls retain their own interaction states. */
.share-editor-table tbody tr:is(:hover, :focus, :active) { background-color: transparent; }
.share-editor-table .share-column { width: 130px; }
.share-editor-table th, .share-editor-table td { padding: var(--cobudget-table-cell-padding); height: auto; text-align: start; vertical-align: middle; white-space: normal; font-weight: 400; border-bottom: 1px solid var(--cobudget-border); }
.share-editor-table thead th { background: var(--cobudget-table-header-background); color: var(--cobudget-text-muted); font-size: var(--cobudget-table-header-font-size); letter-spacing: normal; font-weight: 400; height: var(--cobudget-table-header-height); }
.share-editor-table thead th:first-child { border-start-start-radius: var(--cobudget-table-radius); }
.share-editor-table thead th:last-child { border-start-end-radius: var(--cobudget-table-radius); }
.share-editor-table th:last-child, .share-editor-table td:last-child { text-align: end; font-variant-numeric: tabular-nums; }
.share-editor-table tfoot th, .share-editor-table tfoot td { border-bottom: 0; font-size: var(--cobudget-font-sm); font-weight: 600; background: var(--cobudget-surface-muted); }
.member-table .member-actions-column { width: calc(var(--default-clickable-area, 44px) + var(--default-grid-baseline, 4px) * 5); }
.member-table .member-share-cell { text-align: end; }
.member-table tbody tr:last-child > :is(th, td) { border-bottom: 0; }
.share-member-info { display: flex; flex-wrap: wrap; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 2); min-width: 0; }
.share-input :deep(input) { text-align: end; font-variant-numeric: tabular-nums; }
.area-action-button { margin-top: var(--cobudget-section-gap); }
.settings-block:last-child { padding-bottom: var(--cobudget-settings-heading-gap); }
@media (max-width: 768px) {
	.settings-content { margin: 0; width: 100%; }
	.settings-block { padding-block-start: var(--cobudget-section-gap); }
	.settings-general-block, .settings-master-data-block { padding-block-start: 0; }
	.area-master-data :deep(.master-section) { padding-block-start: var(--cobudget-section-gap); }
	.member-section-header { gap: var(--cobudget-content-gap); margin-block-end: var(--cobudget-content-gap); }
	.area-action-button { margin-top: var(--cobudget-card-gap); }
	.settings-block:last-child { padding-bottom: var(--cobudget-section-gap); }
	.project-edit-form { grid-template-columns: minmax(0, 1fr); }
	.share-editor-table .share-column { width: 100px; }
	.member-table .share-column { width: calc(var(--default-grid-baseline, 4px) * 20); }
	.member-table .member-actions-column { width: calc(var(--cobudget-mobile-touch-size, 44px) + var(--default-grid-baseline, 4px) * 3); }
	.member-table .member-identity { display: grid; grid-template-columns: 32px minmax(0, 1fr); gap: calc(var(--default-grid-baseline, 4px) * 2); }
	.member-table .member-info { display: contents; }
	.member-table .member-name { grid-column: 2; }
	.member-table .member-info :deep(.nc-chip) { grid-column: 1 / -1; justify-self: start; max-width: 100%; }
}
</style>
