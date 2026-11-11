<template>
	<div class="project-create-view settings-section">
		<AppPageHeader>
			<template #title><span class="cobudget-page-title-with-back">
				<NcButton variant="tertiary" class="cobudget-native-button cobudget-header-back-button" :aria-label="$texts.areas.backToAreas()" :title="$texts.areas.backToAreas()" :disabled="creatingProject" @click="$router.push({ name: 'projects' })"><template #icon><ArrowLeftIcon :size="20" /></template></NcButton>
				<ReportPeriodPicker navigation-only force-navigation area-navigation :period-enabled="false" :title="$texts.areas.newArea()" label="" :disabled="creatingProject" />
			</span></template>
		</AppPageHeader>
		<form class="project-create-form" :aria-busy="creatingProject" @submit.prevent="createProject">
			<div class="project-identity-fields">
				<NcFormGroup class="cobudget-form-group">
					<template #label><label class="cobudget-form-label" for="project-create-name">{{ $texts.areaSettings.areaName() }}</label></template>
					<NcInputField id="project-create-name" v-model="newProject.name" label-outside :label="$texts.areaSettings.areaName()" :placeholder="$texts.areas.namePlaceholder()" maxlength="128" required :disabled="creatingProject" class="cobudget-native-field" />
				</NcFormGroup>
				<NcFormGroup class="cobudget-form-group" :label="$texts.areaSettings.areaColor()">
					<NcColorPicker popover-base-class="cobudget-menu-popover" :model-value="newProject.color || undefined" clearable @update:model-value="colorChanged">
						<NcButton class="cobudget-native-button" variant="secondary" type="button" :disabled="creatingProject" :aria-label="$texts.areaSettings.selectAreaColor()"><template #icon><span class="project-create-color" :style="{ backgroundColor: newProject.color || 'transparent' }" /></template>{{ newProject.color || $texts.areaSettings.selectAreaColor() }}</NcButton>
					</NcColorPicker>
				</NcFormGroup>
			</div>
			<section class="project-members-section">
				<h3 class="cobudget-section-heading">{{ $texts.areaDetail.membersTitle() }}</h3>
				<div class="member-section-header">
					<p class="settings-hint">{{ $texts.areas.createMembersHint() }}</p>
					<NcButton v-if="$enableSharedProjects" type="button" class="cobudget-native-button" variant="secondary" :disabled="creatingProject" @click="openMemberDialog"><template #icon><PlusIcon :size="20" /></template>{{ $texts.common.add() }}</NcButton>
				</div>
				<NcNoteCard v-if="memberRows.length <= 1" type="info" :text="$texts.areaSettings.personalAreaMembersHint()" />
				<div v-else class="member-table-wrapper">
					<table class="member-table" :aria-label="$texts.areaSettings.areaShareTable()">
						<colgroup><col /><col class="share-column" /><col class="actions-column" /></colgroup>
						<thead><tr><th scope="col">{{ $texts.areaSettings.member() }}</th><th scope="col" class="member-share">{{ $texts.areaSettings.share() }} (%)</th><th scope="col"><span class="hidden-visually">{{ $texts.common.edit() }}</span></th></tr></thead>
						<tbody><tr v-for="member in memberRows" :key="member.id">
							<th scope="row"><div class="member-info"><MemberIdentity :user-id="member.id" :display-name="member.displayName" :size="32" /><NcChip v-if="member.id === currentUser.uid" :text="$texts.areaSettings.areaAdmin()" no-close variant="secondary" /></div></th>
							<td class="member-share">{{ member.share }}%</td>
							<td><NcButton v-if="member.id !== currentUser.uid" variant="tertiary" type="button" :disabled="creatingProject" :aria-label="$texts.memberSearch.removeMember(member.displayName)" :title="$texts.memberSearch.removeMember(member.displayName)" @click="removeMember(member.id)"><template #icon><DeleteIcon :size="20" /></template></NcButton></td>
						</tr></tbody>
					</table>
				</div>
			</section>
			<NcNoteCard v-if="createError" type="error" :text="createError" />
			<div class="project-create-actions">
				<NcButton variant="tertiary" type="button" :disabled="creatingProject" @click="cancelCreate">{{ $texts.common.cancel() }}</NcButton>
				<NcButton type="submit" variant="primary" :disabled="!newProject.name.trim() || creatingProject"><template v-if="creatingProject" #icon><NcLoadingIcon :size="20" /></template>{{ creatingProject ? $texts.common.saveBusy() : $texts.common.save() }}</NcButton>
			</div>
		</form>
		<DialogTransition :show="!!(memberDialogOpen)" v-slot="{ returnFocus }">
			<NcModal :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog project-create-modal" :show="true" label-id="project-create-members-title" :no-close="confirmDiscard" :close-on-click-outside="!confirmDiscard" @update:show="closeMemberDialog" @keydown.esc.stop.prevent="closeMemberDialog">
				<div class="member-editor">
					<h2 id="project-create-members-title">{{ $texts.areaDetail.addMember() }}</h2>
					<NcFormGroup class="cobudget-form-group">
						<template #label><label class="cobudget-form-label" for="project-create-members">{{ $texts.areaDetail.membersTitle() }}</label></template>
						<NcSelect v-model="selectedMembers" input-id="project-create-members" class="cobudget-native-select" label-outside :input-label="$texts.areaDetail.membersTitle()" :options="availableMembers" label="displayName" multiple :filterable="false" :loading="memberSearchLoading" :placeholder="$texts.areas.searchUsers()" @search="searchMembers">
							<template #option="member"><MemberIdentity :user-id="member.id" :display-name="member.displayName" :size="32" /></template>
							<template #selected-option="member"><MemberIdentity :user-id="member.id" :display-name="member.displayName" :size="24" /></template>
							<template #no-options>{{ memberSearchLoading ? $texts.common.loading() : memberSearchTerm ? $texts.memberSearch.noResults() : $texts.areas.searchUsers() }}</template>
						</NcSelect>
						<NcNoteCard v-if="memberSearchError" type="error"><p>{{ memberSearchError }}</p><NcButton @click="fetchMemberOptions">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
					</NcFormGroup>
					<div class="member-editor-actions"><NcButton variant="tertiary" @click="cancelMemberDialog">{{ $texts.common.cancel() }}</NcButton><NcButton variant="primary" :disabled="!selectedMembers.length" @click="addMembers">{{ $texts.common.save() }}</NcButton></div>
				</div>
			</NcModal>
		</DialogTransition>
		<DialogTransition :show="!!(confirmDiscard)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog project-create-confirmation" :name="$texts.entry.discardChangesTitle()" :message="$texts.entry.discardChangesMessage()" close-on-click-outside @closing="resolveDiscard(false)" @keydown.esc.stop.prevent="resolveDiscard(false)">
				<template #actions><NcButton variant="tertiary" @click="resolveDiscard(false)">{{ $texts.common.cancel() }}</NcButton><NcButton variant="error" @click="resolveDiscard(true)">{{ $texts.entry.discardChangesConfirm() }}</NcButton></template>
			</NcDialog>
		</DialogTransition>
	</div>
</template>

<script>
import DialogTransition from '../components/DialogTransition.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { extractError, showToast } from '../services/notifications'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import MemberIdentity from '../components/MemberIdentity.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'ProjectCreateView',
	components: { ReportPeriodPicker, DialogTransition, AppPageHeader, MemberIdentity, NcButton, NcChip, NcModal, NcDialog, NcInputField, NcFormGroup, NcColorPicker, NcSelect, NcNoteCard, NcLoadingIcon, PlusIcon, ArrowLeftIcon, DeleteIcon },
	emits: ['refresh-projects'],
	data() {
		return {
			currentUser: getCurrentUser() || { uid: '', displayName: '' },
			newProject: { name: '', color: '', members: [] },
			creatingProject: false, createError: '', leaveAllowed: false,
			memberDialogOpen: false, selectedMembers: [],
			confirmDiscard: false, discardResolver: null,
			memberSearchTerm: '', memberSearchOptions: [], memberSearchLoading: false,
			memberSearchError: '', memberSearchSequence: 0, memberSearchTimer: null,
		}
	},
	computed: {
		hasDraft() { return !!(this.newProject.name || this.newProject.color || this.newProject.members.length || this.selectedMembers.length) },
		availableMembers() {
			const excluded = new Set([this.currentUser.uid, ...this.newProject.members.map(m => m.id), ...this.selectedMembers.map(m => m.id)])
			return this.memberSearchOptions.filter(member => !excluded.has(member.id))
		},
		memberRows() {
			// The server appends the creator after the selected members and assigns
			// remaining whole percentages in that order (WorkspaceAwareTrait).
			const members = this.$enableSharedProjects ? this.newProject.members.filter(m => m.id !== this.currentUser.uid) : []
			const ordered = [...members, { id: this.currentUser.uid, displayName: this.currentUser.displayName || this.currentUser.uid }]
			const base = Math.floor(100 / ordered.length)
			const remainder = 100 % ordered.length
			const rows = ordered.map((member, index) => ({ ...member, share: base + (index < remainder ? 1 : 0) }))
			return [rows.at(-1), ...rows.slice(0, -1)]
		},
	},
	mounted() { window.addEventListener('beforeunload', this.onBeforeUnload) },
	beforeUnmount() {
		window.removeEventListener('beforeunload', this.onBeforeUnload)
		this.searchMembers('')
		this.resolveDiscard(false)
	},
	beforeRouteLeave() {
		if (this.creatingProject) return false
		if (this.leaveAllowed || !this.hasDraft) return true
		return this.askDiscard()
	},
	methods: {
		onBeforeUnload(event) {
			if (!this.leaveAllowed && (this.hasDraft || this.creatingProject)) { event.preventDefault(); event.returnValue = '' }
		},
		askDiscard() {
			if (this.confirmDiscard) return false
			this.confirmDiscard = true
			return new Promise(resolve => { this.discardResolver = resolve })
		},
		resolveDiscard(discard) {
			const resolve = this.discardResolver
			this.discardResolver = null
			this.confirmDiscard = false
			resolve?.(discard)
		},
		cancelCreate() {
			if (this.creatingProject || this.confirmDiscard) return
			this.leaveAllowed = true
			this.$router.push({ name: 'projects' })
		},
		openMemberDialog() {
			if (this.creatingProject || !this.$enableSharedProjects) return
			this.selectedMembers = []
			this.memberDialogOpen = true
		},
		async closeMemberDialog() {
			if (this.confirmDiscard) return
			if (!this.selectedMembers.length || await this.askDiscard()) this.cancelMemberDialog()
		},
		cancelMemberDialog() {
			this.memberDialogOpen = false
			this.selectedMembers = []
			this.searchMembers('')
		},
		addMembers() {
			if (!this.memberDialogOpen || this.confirmDiscard || !this.$enableSharedProjects) return
			const ids = new Set([this.currentUser.uid, ...this.newProject.members.map(m => m.id)])
			for (const member of this.selectedMembers) {
				if (member.id && !ids.has(member.id)) { this.newProject.members.push(member); ids.add(member.id) }
			}
			this.cancelMemberDialog()
		},
		removeMember(id) {
			if (!this.creatingProject) this.newProject.members = this.newProject.members.filter(m => m.id !== id)
		},
		searchMembers(term) {
			clearTimeout(this.memberSearchTimer)
			this.memberSearchSequence++
			this.memberSearchTerm = term.trim()
			this.memberSearchOptions = []
			this.memberSearchError = ''
			this.memberSearchLoading = false
			if (this.memberSearchTerm) this.memberSearchTimer = setTimeout(() => this.fetchMemberOptions(), 300)
		},
		async fetchMemberOptions() {
			if (!this.memberDialogOpen || !this.$enableSharedProjects || !this.memberSearchTerm || this.creatingProject) return
			const sequence = ++this.memberSearchSequence
			this.memberSearchLoading = true
			this.memberSearchError = ''
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/users/search'), { params: { term: this.memberSearchTerm } })
				if (sequence === this.memberSearchSequence) this.memberSearchOptions = response.data || []
			} catch (e) {
				if (sequence === this.memberSearchSequence) this.memberSearchError = extractError(e, this.$texts.memberSearch.searchError())
			} finally { if (sequence === this.memberSearchSequence) this.memberSearchLoading = false }
		},
		colorChanged(color) { if (!this.creatingProject) this.newProject.color = color || '' },
		async createProject() {
			const projectName = this.newProject.name.trim()
			if (!projectName || this.creatingProject || this.confirmDiscard || this.memberDialogOpen || this.leaveAllowed) return
			this.creatingProject = true
			this.createError = ''
			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/projects'), {
					name: projectName, color: this.newProject.color || '',
					members: this.$enableSharedProjects ? this.newProject.members.map(m => m.id) : [],
				})
				this.leaveAllowed = true
				this.$emit('refresh-projects')
				showToast(this.$texts.areas.created())
				this.creatingProject = false
				await this.$router.replace({ name: 'project-detail', params: { id: String(response.data.id) } })
			} catch (e) {
				this.createError = extractError(e, this.$texts.areas.createError())
			} finally { this.creatingProject = false }
		},
	},
}
</script>

<style scoped>
.project-create-view { display: block; margin: 0; width: 100%; box-sizing: border-box; }
.project-create-form { box-sizing: border-box; margin: 0 var(--cobudget-form-inset); max-width: var(--cobudget-form-max-width); width: calc(100% - var(--cobudget-form-inset) * 2); padding-block-end: var(--cobudget-settings-heading-gap); }
.project-identity-fields { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: var(--cobudget-content-gap); align-items: end; }
.project-create-color { display: inline-block; width: 20px; height: 20px; border: 1px solid var(--color-border); border-radius: var(--border-radius-element); }
.project-members-section { padding-block-start: var(--cobudget-settings-heading-gap); }
.project-members-section > h3 { margin-block-start: 0; }
.member-section-header { display: flex; flex-wrap: wrap; align-items: flex-start; gap: var(--cobudget-card-gap); margin-block-end: var(--cobudget-section-gap); }
.settings-hint { flex: 1 1 320px; margin: 0; font-size: var(--font-size, 15px); color: var(--color-text-maxcontrast); }
.member-section-header > .button-vue { margin-inline-start: auto; }
.member-table-wrapper { overflow-x: auto; }
.member-table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
.member-table .share-column { width: 130px; }
.member-table .actions-column { width: calc(var(--default-clickable-area, 44px) + var(--default-grid-baseline, 4px) * 5); }
.member-table th, .member-table td { padding: var(--cobudget-table-cell-padding); height: auto; vertical-align: middle; text-align: start; white-space: normal; font-weight: 400; border-bottom: 1px solid var(--cobudget-border); }
.member-table thead th { background: var(--cobudget-table-header-background); color: var(--cobudget-text-muted); font-size: var(--cobudget-table-header-font-size); letter-spacing: normal; font-weight: 400; height: var(--cobudget-table-header-height); }
.member-table tbody tr:is(:hover, :focus, :active) { background-color: transparent; }
.member-table tbody tr:last-child > * { border-bottom: 0; }
.member-table .member-share, .member-table td:last-child { text-align: end; font-variant-numeric: tabular-nums; }
.member-info { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; min-width: 0; font-weight: var(--font-weight-element, 500); }
.project-create-actions, .member-editor-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-block-start: var(--cobudget-section-gap); }
.member-editor { padding: 12px var(--cobudget-dialog-padding) var(--cobudget-dialog-padding); }
.member-editor h2 { margin: 0 0 16px; padding-inline-end: var(--cobudget-mobile-touch-size); font-size: var(--cobudget-dialog-title-size); text-align: start; }
.cobudget-native-select { width: 100%; min-width: 0; margin-bottom: 0; }
@media (max-width: 768px) {
	.project-create-view, .project-create-modal, .project-create-confirmation { --default-clickable-area: var(--cobudget-mobile-touch-size); }
	.project-create-form { margin: 16px; width: calc(100% - 32px); }
	.project-identity-fields { grid-template-columns: minmax(0, 1fr); }
	.member-table .share-column { width: 100px; }
}
</style>
