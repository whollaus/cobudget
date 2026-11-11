<template>
	<div class="master-data-settings" :aria-busy="loading">
		<p v-if="loading" class="master-loading" role="status"><NcLoadingIcon :size="20" /> {{ $texts.common.loading() }}</p>
		<NcNoteCard v-if="loadError" type="error">
			<p>{{ loadError }}</p>
			<NcButton @click="load">{{ $texts.masterData.retry() }}</NcButton>
		</NcNoteCard>
		<template v-if="!loading && (!loadError || scopes.length)">
			<section v-for="kind in kinds" :key="kind" :data-master-section="kind" class="master-section">
				<h3 class="cobudget-section-heading">{{ heading(kind) }}</h3>
				<div v-for="type in types" :key="type" class="master-type">
					<div class="master-heading">
						<h4 class="cobudget-subheading">{{ type === 'income' ? $texts.annual.income() : $texts.annual.expenses() }}</h4>
						<NcButton class="cobudget-native-button" variant="secondary" :disabled="!scopes.some(scope => scope.editable)" @click="openEditor(kind, type)"><template #icon><Plus :size="20" /></template>{{ $texts.common.add() }}</NcButton>
					</div>
					<div class="master-table-scroll" tabindex="0" :aria-label="heading(kind)">
						<table class="master-table" :class="{ 'master-table-hierarchy': hasHierarchy(kind, type), 'master-table--personal-scope': scopes.some(scope => scope.key === 'personal') }" :style="{ '--master-scope-count': scopes.length }">
							<thead><tr><th scope="col">{{ $texts.masterData.entryLabel() }}</th><th v-for="scope in scopes" :key="scope.key" scope="col" :class="{ 'master-personal-scope': scope.key === 'personal' }">{{ scope.name }}<small v-if="!scope.editable">{{ $texts.masterData.readOnly() }}</small></th><th scope="col"><span class="hidden-visually">{{ $texts.common.edit() }}</span></th></tr></thead>
							<tbody>
								<tr v-for="row in visibleRows(kind, type)" :key="row.key" @click="openEditor(kind, type, row)">
									<th scope="row"><div class="master-name" :style="{ paddingInlineStart: `calc(var(--default-grid-baseline, 4px) * ${row.depth * 6})` }">
										<span v-if="hasHierarchy(kind, type)" class="master-tree-toggle" :class="{ 'master-tree-branch': row.depth > 0 }">
										</span>
										<CategoryIcon v-if="kind === 'categories'" :icon="row.icon" :size="16" />
										<span class="master-label">{{ row.name }}<span v-if="isGlobalRow(row)" class="master-global" role="img" :aria-label="$texts.common.global()" :title="$texts.common.global()">*</span></span>
									</div></th>
									<td v-for="scope in scopes" :key="scope.key"><span role="img" :aria-label="active(row.records[scope.key]) ? $texts.masterData.active() : $texts.masterData.inactive()" :title="active(row.records[scope.key]) ? $texts.masterData.active() : $texts.masterData.inactive()"><CheckboxMarked v-if="active(row.records[scope.key])" :size="22" class="master-active" /><CheckboxBlankOutline v-else :size="22" /></span></td>
									<td><NcButton variant="tertiary" :aria-label="`${$texts.common.edit()}: ${row.name}`" @click.stop="openEditor(kind, type, row)"><template #icon><Pencil :size="20" /></template></NcButton></td>
								</tr>
								<tr v-if="!rows[kind].some(row => row.type === type)"><td :colspan="scopes.length + 2">{{ $texts.masterData.empty() }}</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</section>
		</template>
		<DialogTransition :show="!!(editor)" v-slot="{ returnFocus }">
			<NcModal :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-settings-modal master-editor-modal" :show="true" :label-id="editorTitleId" size="large" :no-close="saving || confirmDiscard || confirmDelete || !!advancedDrafts" :close-on-click-outside="!saving && !confirmDiscard && !confirmDelete && !advancedDrafts" @update:show="closeEditor" @keydown.esc.stop.prevent="closeEditor">
				<form class="master-editor" :aria-busy="saving" @submit.prevent="save">
					<h2 :id="editorTitleId">{{ editorHeading }}</h2>
					<div class="master-editor-body">
						<div class="master-identity" :class="{ 'master-identity-category': editor.kind === 'categories' }">
							<NcFormGroup class="master-identity-field cobudget-form-group" :aria-describedby="identityReadOnlyHint ? `${editorTitleId}-identity-hint` : undefined">
								<template #label><label class="cobudget-form-label" :for="`${editorTitleId}-name`">{{ $texts.masterData.entryLabel() }}</label></template>
								<div class="master-name-input">
									<NcInputField :id="`${editorTitleId}-name`" v-model="editor.name" label-outside :label="$texts.masterData.entryLabel()" maxlength="128" required :disabled="!canEditName || saving || refreshRequired" class="cobudget-native-field" />
									<IconPicker v-if="editor.kind === 'categories'" button-trigger :value="editor.icon" @input="changeIcon" :disabled="!canEditName || saving || refreshRequired" />
								</div>
							</NcFormGroup>
							<NcFormGroup v-if="editor.kind === 'categories'" class="master-identity-field master-parent-field cobudget-form-group" :description="parentReadOnlyHint || undefined" :aria-describedby="identityReadOnlyHint ? `${editorTitleId}-identity-hint` : undefined">
								<template #label><label class="cobudget-form-label" :for="`${editorTitleId}-parent`">{{ $texts.common.parentCategoryOptional() }}</label></template>
								<NcSelect class="cobudget-native-select" :input-id="`${editorTitleId}-parent`" label-outside :model-value="selectedParent" :options="parentChoices" label="name" :clearable="false" :disabled="!canEditName || saving || refreshRequired || editorHasChildren" @update:model-value="changeParent" />
							</NcFormGroup>
						</div>
						<NcNoteCard v-if="identityReadOnlyHint" :id="`${editorTitleId}-identity-hint`" type="warning">{{ identityReadOnlyHint }}</NcNoteCard>
						<NcFormGroup class="master-editor-section cobudget-form-group" :label="$texts.masterData.availability()">
							<div class="master-scopes" :class="{ 'master-scopes-multiple': scopes.length > 1 }">
								<NcFormBoxSwitch v-for="scope in scopes" :key="scope.key" v-model="editor.drafts[scope.key].enabled" :label="scope.name" :description="!scope.editable ? $texts.masterData.readOnly() : undefined" :disabled="!scope.editable || saving || refreshRequired" />
							</div>
						</NcFormGroup>
						<div v-if="advanced && advancedScopes.length" class="master-editor-section">
							<NcButton variant="secondary" :disabled="saving || refreshRequired" @click="openAdvanced"><template #icon><Tune :size="20" /></template>{{ $texts.masterData.advancedSettings() }}</NcButton>
						</div>
	
						<NcNoteCard v-if="saveError" type="error"><p>{{ saveError }}</p><NcButton :disabled="saving" @click="retrySave">{{ $texts.masterData.retry() }}</NcButton></NcNoteCard>
						<p v-if="editor.row && saving" class="hidden-visually" role="status">{{ $texts.common.saveBusy() }}</p>
					</div>
					<div class="master-actions">
						<DisabledActionHint v-if="editor.row" :reason="deleteDisabledReason" :label="$texts.common.delete()" class="master-delete-popover">
							<NcButton class="master-delete-button" variant="error" :aria-label="$texts.common.delete()" :title="deleteDisabledReason ? undefined : $texts.common.delete()" :disabled="!canDelete || saving || refreshRequired || hasChanges" @click="confirmDelete = true"><template #icon><Delete class="master-delete-icon" :size="20" /></template><span class="master-delete-label">{{ $texts.common.delete() }}</span></NcButton>
						</DisabledActionHint>
						<div class="master-confirm-actions">
							<NcButton variant="tertiary" :disabled="saving" @click="discardAndClose">{{ $texts.common.cancel() }}</NcButton>
							<NcButton type="submit" variant="primary" :disabled="saving || refreshRequired || !hasChanges || !canSave">{{ saving ? $texts.common.saveBusy() : $texts.common.save() }}</NcButton>
						</div>
					</div>
				</form>
			</NcModal>
		</DialogTransition>
		<DialogTransition :show="!!(editor && advancedDrafts)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog master-advanced-dialog" size="large" :name="$texts.masterData.advancedSettings()" :navigation-aria-label="$texts.masterData.detailArea()" :no-close="true" :close-on-click-outside="false" @keydown.esc.stop.prevent="closeAdvanced">
				<template #navigation="{ isCollapsed }">
					<div v-if="!isCollapsed" class="master-detail-sidebar">
						<h2 class="master-detail-title">{{ $texts.masterData.advancedSettings() }}</h2>
						<ul class="master-detail-navigation">
						<NcAppNavigationItem v-for="scope in advancedScopes" :key="scope.key" :name="scope.name" :title="advancedScopeDirty(scope) ? `${scope.name}: ${$texts.masterData.unsavedDetails()}` : scope.name" :active="scope.key === editor.detailScope" :aria-disabled="saving" @click.prevent="changeDetailScope(scope)">
							<template #icon><AreaIcon :without-area="scope.key === 'personal'" :global-scope="scope.key === 'global'" :color="scope.color" /></template>
							<template v-if="advancedScopeDirty(scope)" #counter><span class="master-detail-dirty" role="img" :aria-label="$texts.masterData.unsavedDetails()" :title="$texts.masterData.unsavedDetails()"><Circle :size="10" /></span></template>
						</NcAppNavigationItem>
						</ul>
					</div>
					<NcSelect v-else class="cobudget-native-select master-detail-mobile-select" :input-id="`${editorTitleId}-detail-scope`" :input-label="$texts.masterData.detailArea()" :disabled="saving" :model-value="selectedDetailScope" :options="advancedScopes" label="name" :clearable="false" @update:model-value="changeDetailScope">
						<template #option="scope"><span class="master-detail-area"><AreaIcon :without-area="scope.key === 'personal'" :global-scope="scope.key === 'global'" :color="scope.color" /><span class="master-detail-area-name">{{ scope.name }}</span><span v-if="advancedScopeDirty(scope)" class="master-detail-dirty" role="img" :aria-label="$texts.masterData.unsavedDetails()" :title="$texts.masterData.unsavedDetails()"><Circle :size="10" /></span></span></template>
						<template #selected-option="scope"><span class="master-detail-area master-detail-area--selected" :title="scope.name"><AreaIcon :without-area="scope.key === 'personal'" :global-scope="scope.key === 'global'" :color="scope.color" /><span class="master-detail-area-name">{{ scope.name }}</span><span v-if="advancedScopeDirty(scope)" class="master-detail-dirty" role="img" :aria-label="$texts.masterData.unsavedDetails()" :title="$texts.masterData.unsavedDetails()"><Circle :size="10" /></span></span></template>
					</NcSelect>
				</template>
				<NcButton class="master-advanced-close" variant="tertiary" :disabled="saving" :aria-label="$texts.common.close()" @click="closeAdvanced"><template #icon><Close :size="24" /></template></NcButton>
				<section ref="advancedDetails" class="master-details" :aria-label="selectedDetailScope?.name" :aria-busy="saving">
					<NcNoteCard v-if="detailReadOnlyHint" type="warning" role="status">{{ detailReadOnlyHint }}</NcNoteCard>
					<PaymentPartnerEditFields v-if="editor.kind === 'payment-partners'" :key="editor.detailScope" hide-name :name="editor.name" :details="detailDraft.details" :disabled="saving || !detailEditable" id-prefix="master-partner" @update:details="detailDraft.details = $event" />
					<NcFormGroup v-else class="cobudget-form-group">
						<template #label><label class="cobudget-form-label" :for="`${editorTitleId}-number`">{{ $texts.common.number() }}</label></template>
						<NcInputField :id="`${editorTitleId}-number`" v-model="detailDraft.code" label-outside :label="$texts.common.number()" maxlength="128" :disabled="saving || !detailEditable" class="cobudget-native-field" />
					</NcFormGroup>
					<NcNoteCard v-if="advancedError" type="error">{{ advancedError }}</NcNoteCard>
				</section>
				<template #actions><NcButton variant="tertiary" :disabled="saving" @click="cancelAdvanced">{{ $texts.common.cancel() }}</NcButton><NcButton variant="primary" :disabled="saving || !advancedHasChanges" @click="saveAdvanced">{{ saving ? $texts.common.saveBusy() : $texts.common.save() }}</NcButton></template>
			</NcDialog>
		</DialogTransition>
		<DialogTransition :show="!!(advancedDiscard)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="$texts.entry.discardChangesTitle()" :message="$texts.entry.discardChangesMessage()" @closing="advancedDiscard = false" @keydown.esc.stop.prevent="advancedDiscard = false">
				<template #actions><NcButton variant="tertiary" @click="advancedDiscard = false">{{ $texts.common.cancel() }}</NcButton><NcButton variant="error" @click="cancelAdvanced">{{ $texts.entry.discardChangesConfirm() }}</NcButton></template>
			</NcDialog>
		</DialogTransition>
		<DialogTransition :show="!!(confirmDiscard)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="$texts.entry.discardChangesTitle()" :message="$texts.entry.discardChangesMessage()" close-on-click-outside @keydown.esc.stop.prevent="confirmDiscard = false" @closing="confirmDiscard = false">
				<template #actions><NcButton variant="tertiary" @click="confirmDiscard = false">{{ $texts.common.cancel() }}</NcButton><NcButton variant="error" @click="discardAndClose">{{ $texts.entry.discardChangesConfirm() }}</NcButton></template>
			</NcDialog>
		</DialogTransition>
		<DialogTransition :show="!!(confirmDelete)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="$texts.common.delete()" :message="admin ? $texts.masterData.deleteGlobalHint(editor.name) : $texts.masterData.deleteRowHint(editor.name)" :no-close="saving" :close-on-click-outside="!saving" @keydown.esc.stop.prevent="closeDeleteConfirmation" @closing="closeDeleteConfirmation">
				<template #actions><NcButton variant="tertiary" :disabled="saving" @click="confirmDelete = false">{{ $texts.common.cancel() }}</NcButton><DisabledActionHint :reason="deleteDisabledReason" :label="$texts.common.delete()" ><NcButton variant="error" :disabled="!canDelete || saving || refreshRequired || hasChanges" @click="deleteRecord">{{ $texts.common.delete() }}</NcButton></DisabledActionHint></template>
			</NcDialog>
		</DialogTransition>
	</div>
</template>

<script>
import DialogTransition from './DialogTransition.vue'
import DisabledActionHint from './DisabledActionHint.vue'
import { defineAsyncComponent } from 'vue'
import { generateUrl } from '@nextcloud/router'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import CheckboxMarked from 'vue-material-design-icons/CheckboxMarked.vue'
import CheckboxBlankOutline from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import AreaIcon from './AreaIcon.vue'
import Tune from 'vue-material-design-icons/Tune.vue'
import Circle from 'vue-material-design-icons/Circle.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Delete from 'vue-material-design-icons/TrashCanOutline.vue'
import CategoryIcon from './CategoryIcon.vue'
import PaymentPartnerEditFields from './PaymentPartnerEditFields.vue'
import axios from '../services/http'
import { extractError } from '../services/notifications'
import { groupMasterData, recordFlag } from '../utils/masterData'
import { PAYMENT_PARTNER_DETAIL_FIELDS, createPaymentPartnerDetails, isValidPaymentPartnerEmail, normalizePaymentPartnerDetails } from '../utils/paymentPartnerDetails'
const IconPicker = defineAsyncComponent(() => import(/* webpackChunkName: "cobudget-icon-picker" */ './IconPicker.vue'))
export default {
	name: 'MasterDataSettings',
	components: { DialogTransition, DisabledActionHint, Delete, Circle, NcAppNavigationItem, AreaIcon, Tune, Close, NcNoteCard, NcLoadingIcon, NcFormGroup, NcInputField, NcSelect, NcButton, NcDialog, NcModal, NcFormBoxSwitch, CheckboxMarked, CheckboxBlankOutline, Pencil, Plus, CategoryIcon, IconPicker, PaymentPartnerEditFields },
	props: { projectId: { type: [Number, String], default: null }, admin: Boolean, advanced: { type: Boolean, default: true }, incomes: { type: Boolean, default: true } },
	emits: ['changed', 'loaded'],
	data: () => ({ advancedDrafts: null, advancedError: '', advancedDiscard: false, kinds: ['categories', 'payment-partners'], scopes: [], loading: true, loadError: '', saving: false, saveError: '', editor: null, confirmDelete: false, confirmDiscard: false, refreshRequired: false, closeRequested: false }),
	computed: {
		types() { return this.incomes ? ['income', 'expense'] : ['expense'] },
		rows() { return Object.fromEntries(this.kinds.map(kind => [kind, groupMasterData(this.scopes, kind)])) },
		editorTitleId() { return `master-editor-${this.admin ? 'global' : this.projectId || 'personal'}` },
		editorHeading() { return `${this.editor?.kind === 'categories' ? this.$texts.masterData.categoryTitle() : this.$texts.masterData.partnerTitle()} · ${this.editor?.type === 'income' ? this.$texts.annual.income() : this.$texts.annual.expenses()}` },
		hasChanges() { return !!this.editor && this.editor.baseline !== this.draftFingerprint() },
		canEditName() { return this.editor && !Object.values(this.editor.row?.records || {}).some(record => this.flag(record.is_global) && !this.admin) && this.scopes.some(scope => scope.editable) },
		identityReadOnlyHint() {
			if (!this.editor || this.canEditName) return ''
			if (!this.admin && this.isGlobalRow(this.editor.row)) return this.$texts.masterData.identityGlobalReadOnly()
			return this.$texts.masterData.identityPermissionReadOnly()
		},
		parentReadOnlyHint() {
			return this.canEditName && this.editorHasChildren ? this.$texts.masterData.parentChildrenReadOnly() : ''
		},
		detailDraft() { return (this.advancedDrafts || this.editor?.drafts)?.[this.editor?.detailScope] },
		advancedScopes() { return this.scopes.filter(scope => this.editor?.drafts[scope.key]?.enabled && this.editor.drafts[scope.key].record) },
		advancedHasChanges() { return !!this.advancedDrafts && this.scopes.some(scope => this.advancedScopeDirty(scope)) },
		detailEditable() { return !!this.scopes.find(scope => scope.key === this.editor?.detailScope)?.editable && (this.admin || !this.flag(this.detailDraft?.record?.is_global)) && (this.advancedDrafts ? !!this.detailDraft?.record : this.detailDraft?.enabled || !!this.detailDraft?.record) },
		detailReadOnlyHint() {
			if (!this.detailDraft || this.detailEditable) return ''
			if (!this.admin && this.flag(this.detailDraft.record?.is_global)) return this.$texts.masterData.detailsGlobalReadOnly()
			if (!this.selectedDetailScope?.editable) return this.$texts.masterData.detailsPermissionReadOnly()
			return this.$texts.masterData.detailsEnableArea(this.selectedDetailScope.name)
		},
		editorHasChildren() { return this.editor?.kind === 'categories' && this.editor?.row && this.hasChildren('categories', this.editor.row) },
		parentOptions() { return this.rows.categories.filter(row => row.type === this.editor?.type && !row.parentKey && row.key !== this.editor?.row?.key) },
		parentChoices() { return [{ key: '', name: this.$texts.common.noParentMainCategory() }, ...this.parentOptions] },
		selectedParent() { return this.parentChoices.find(row => row.key === this.editor?.parentKey) || this.parentChoices[0] },
		selectedDetailScope() { return this.scopes.find(scope => scope.key === this.editor?.detailScope) },
		deleteTargets() { return this.editor?.row ? this.scopes.filter(scope => this.editor.drafts[scope.key]?.record).map(scope => ({ scope, draft: this.editor.drafts[scope.key] })) : [] },
		deleteBlockedReason() {
			if (!this.deleteTargets.length) return ''
			if (!this.admin && this.isGlobalRow(this.editor.row)) return this.$texts.masterData.deleteGlobalBlocked()
			if (this.deleteTargets.some(({ scope }) => !scope.editable)) return this.$texts.masterData.deletePermissionBlocked()
			if (this.editorHasChildren || this.deleteTargets.some(({ draft }) => this.flag(draft.record.has_children))) return this.$texts.masterData.deleteChildrenBlocked()
			if (this.deleteTargets.some(({ draft }) => this.flag(draft.record.in_use))) return this.$texts.masterData.deleteUsedBlocked()
			return ''
		},
		deleteDisabledReason() {
			if (this.deleteBlockedReason) return this.deleteBlockedReason
			if (this.saving) return this.$texts.common.saveBusy()
			if (this.refreshRequired) return this.$texts.masterData.refreshError()
			if (this.hasChanges) return this.$texts.masterData.deleteUnsavedBlocked()
			return ''
		},
		canDelete() { return this.deleteTargets.length > 0 && !this.deleteBlockedReason },
		canSave() {
			if (!this.editor?.name.trim()) return false
			return this.scopes.some(scope => {
				if (!scope.editable) return false
				const draft = this.editor.drafts[scope.key], record = draft.record
				return draft.enabled !== this.active(record) || (record && (this.admin || !this.flag(record.is_global)) && (
					this.editor.name.trim() !== this.editor.row.name || this.editor.icon !== this.editor.row.icon
					|| this.editor.parentKey !== this.editor.row.parentKey || draft.code !== (record.code || '')
					|| JSON.stringify(draft.details) !== JSON.stringify(createPaymentPartnerDetails(record))))
			}) && this.scopes.every(scope => {
				const draft = this.editor.drafts[scope.key]
				return !scope.editable || draft.details.email === createPaymentPartnerDetails(draft.record).email || isValidPaymentPartnerEmail(draft.details.email)
			})
		},
	},
	watch: { projectId() { this.load() } },
	mounted() { this.load() },
	methods: {

		flag: recordFlag,
		active(record) { return !!record && !this.flag(record.is_hidden) },
		heading(kind) { return this.admin ? (kind === 'categories' ? this.$texts.admin.globalCategories() : this.$texts.admin.globalPaymentPartners()) : (kind === 'categories' ? this.$texts.settings.categories() : this.$texts.settings.paymentPartners()) },
		hasChildren(kind, row) { return this.rows[kind].some(child => child.parentKey === row.key) },
		isGlobalRow(row) { return Object.values(row?.records || {}).some(record => this.flag(record.is_global)) },
		hasHierarchy(kind, type) { return kind === 'categories' && this.rows[kind].some(row => row.type === type && row.depth > 0) },
		visibleRows(kind, type) { return this.rows[kind].filter(row => row.type === type) },
		scrollToSection(kind) {
			if (!this.kinds.includes(kind)) return false
			const section = this.$el?.querySelector?.(`[data-master-section="${kind}"]`)
			section?.scrollIntoView?.({ block: 'start', inline: 'nearest', behavior: 'instant' })
			return !!section
		},
		async load({ quiet = false } = {}) {
			if (!quiet) this.loading = true; this.loadError = ''
			try {
				const projects = this.admin ? [] : (await axios.get(generateUrl('/apps/cobudget/api/projects'))).data
				const scopes = this.admin ? [{ key: 'global', name: this.$texts.common.global(), editable: true }] : [
					{ key: 'personal', name: this.$texts.masterData.noArea(), editable: true, projectId: null },
					...projects.map(project => ({ key: String(project.id), name: project.name, color: project.color, projectId: Number(project.id), editable: this.flag(project.is_owner) })),
				]
				await Promise.all(scopes.map(async scope => {
					await Promise.all(this.kinds.map(async kind => {
						const url = `/apps/cobudget/api/${this.admin ? 'admin/' : ''}${kind}${this.admin ? '' : '/settings'}`
						scope[kind] = (await axios.get(generateUrl(url), { params: { projectId: scope.projectId } })).data
					}))
				}))
				this.scopes = scopes
				return true
			} catch (error) { this.loadError = extractError(error, this.$texts.settings.loadError()); return false }
			finally { this.loading = false; this.$emit('loaded') }
		},
		openEditor(kind, type, row = null) {
			this.advancedDrafts = null; this.advancedError = ''; this.advancedDiscard = false
			this.saveError = ''; this.refreshRequired = false; this.closeRequested = false; this.confirmDiscard = false
			const preferredScope = this.admin ? 'global' : this.projectId ? String(this.projectId) : 'personal'
			const defaultScope = row && !row.records[preferredScope]
				? this.scopes.find(scope => row.records[scope.key])?.key || preferredScope
				: preferredScope
			const drafts = Object.fromEntries(this.scopes.map(scope => {
				const record = row?.records[scope.key]
				return [scope.key, { record, enabled: row ? this.active(record) : scope.key === defaultScope, details: createPaymentPartnerDetails(record), code: record?.code || '' }]
			}))
			this.editor = { kind, type, row, drafts, name: row?.name || '', icon: row?.icon || 'Shape', parentKey: row?.parentKey || '', detailScope: defaultScope }
			this.editor.baseline = this.draftFingerprint()
		},
		draftFingerprint() {
			if (!this.editor) return ''
			const { name, icon, parentKey, drafts } = this.editor
			return JSON.stringify([name, icon, parentKey, Object.entries(drafts).map(([key, draft]) => [key, draft.enabled, draft.code, draft.details])])
		},
		closeEditor() {
			if (this.saving || this.confirmDiscard || this.confirmDelete || this.advancedDrafts) return
			if (this.hasChanges && !this.refreshRequired) { this.confirmDiscard = true; return }
			this.discardAndClose()
		},
		discardAndClose() { if (!this.saving) { this.advancedDrafts = null; this.advancedDiscard = false; this.editor = null; this.confirmDelete = false; this.confirmDiscard = false; this.closeRequested = false; this.refreshRequired = false; this.saveError = '' } },
		advancedScopeDirty(scope) {
			const draft = this.advancedDrafts?.[scope.key], record = this.editor?.drafts[scope.key]?.record
			if (!draft || !record || !this.editor.drafts[scope.key].enabled || !scope.editable || (!this.admin && this.flag(record.is_global))) return false
			if (this.editor.kind === 'categories') return draft.code.trim() !== (record.code || '')
			return JSON.stringify(normalizePaymentPartnerDetails(draft.details)) !== JSON.stringify(normalizePaymentPartnerDetails(createPaymentPartnerDetails(record)))
		},
		openAdvanced() {
			if (!this.editor || this.saving || this.refreshRequired || !this.advancedScopes.length) return
			if (!this.advancedScopes.some(scope => scope.key === this.editor.detailScope)) this.editor.detailScope = this.advancedScopes[0].key
			this.advancedError = ''; this.advancedDiscard = false
			this.advancedDrafts = Object.fromEntries(Object.entries(this.editor.drafts).map(([key, draft]) => [key, { ...draft, details: { ...draft.details } }]))
		},
		closeAdvanced() {
			if (this.advancedDiscard || this.saving) return
			if (this.advancedHasChanges) { this.advancedDiscard = true; return }
			this.cancelAdvanced()
		},
		cancelAdvanced() { if (this.saving) return; this.advancedDrafts = null; this.advancedDiscard = false; this.advancedError = '' },
		async saveAdvanced() {
			if (!this.editor || !this.advancedDrafts || this.saving) return false
			const editable = this.advancedScopes.filter(scope => scope.editable && this.editor.drafts[scope.key].record && (this.admin || !this.flag(this.editor.drafts[scope.key].record.is_global)))
			const invalid = editable.find(({ key }) => this.advancedDrafts[key].details.email !== this.editor.drafts[key].details.email && !isValidPaymentPartnerEmail(this.advancedDrafts[key].details.email))
			if (invalid) {
				this.changeDetailScope(invalid)
				this.advancedError = this.$texts.paymentPartnerDetails.invalidEmail()
				return false
			}
			const changes = editable.flatMap(scope => {
				const draft = this.advancedDrafts[scope.key], record = this.editor.drafts[scope.key].record
				const operation = { kind: this.editor.kind, projectId: scope.projectId, action: 'update', id: record.id, name: record.name }
				if (this.editor.kind === 'categories') {
					if (draft.code.trim() === (record.code || '')) return []
					operation.code = draft.code.trim()
				} else {
					const original = normalizePaymentPartnerDetails(createPaymentPartnerDetails(record))
					operation.details = Object.fromEntries(Object.entries(normalizePaymentPartnerDetails(draft.details)).filter(([key, value]) => value !== original[key]))
					if (!Object.keys(operation.details).length) return []
				}
				return [{ scope, operation }]
			})
			if (!changes.length) { this.cancelAdvanced(); return true }
			this.saving = true; this.advancedError = ''
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/${this.admin ? 'admin/' : ''}master-data`), { operations: changes.map(change => change.operation) })
				// Only advance the saved details in the parent baseline. Its pending
				// name, icon, parent and availability edits must remain unsaved.
				const baseline = JSON.parse(this.editor.baseline)
				for (const { scope, operation } of changes) {
					const draft = this.editor.drafts[scope.key]
					if (this.editor.kind === 'categories') {
						draft.record.code = operation.code
						draft.code = operation.code
					} else {
						for (const [field, column] of PAYMENT_PARTNER_DETAIL_FIELDS) {
							if (Object.hasOwn(operation.details, field)) draft.record[column] = operation.details[field]
						}
						draft.details = createPaymentPartnerDetails(draft.record)
					}
					const saved = baseline[3].find(([key]) => key === scope.key)
					saved[2] = draft.code; saved[3] = draft.details
				}
				this.editor.baseline = JSON.stringify(baseline)
				this.saving = false
				this.cancelAdvanced()
				this.$emit('changed')
				return true
			} catch (error) {
				this.advancedError = extractError(error, this.$texts.masterData.saveError())
				return false
			} finally { this.saving = false }
		},
		changeParent(option) {
			if (!option || !this.canEditName || this.editorHasChildren || this.saving || this.refreshRequired) return
			this.editor.parentKey = option.key
		},
		changeDetailScope(scope) {
			if (!scope || this.saving || this.refreshRequired || (this.advancedDrafts && !this.advancedScopes.some(item => item.key === scope.key))) return
			this.editor.detailScope = scope.key
			// The next area's first field must be visible even after a long details form.
			this.$nextTick?.(() => this.$refs.advancedDetails?.closest('.dialog__content')?.scrollTo({ top: 0 }))
		},
		changeIcon(icon) { if (this.canEditName && !this.saving && !this.refreshRequired) this.editor.icon = icon },
		async retrySave() {
			if (!this.refreshRequired) return this.save()
			this.saving = true
			try { return await this.refreshEditor(this.editor) } finally { this.saving = false }
		},
		closeDeleteConfirmation() { if (!this.saving) this.confirmDelete = false },
		buildOperations() {
			const e = this.editor, operations = [], ensured = new Map()
			const push = (scope, values) => operations.push({ kind: e.kind, projectId: scope.projectId, ...values })
			const ensureParent = (scope, key) => {
				if (!key) return 0
				const cacheKey = `${scope.key}:${key}`
				if (ensured.has(cacheKey)) return ensured.get(cacheKey)
				const row = this.rows.categories.find(item => item.key === key)
				if (!row) throw new Error(this.$texts.masterData.parentMissing())
				let record = row.records[scope.key], id = record?.id
				if (!record) {
					id = `@parent${operations.length}`
					push(scope, { action: 'create', reuse: true, ref: id, name: row.name, type: row.type, icon: row.icon })
				}
				if (!record || !this.active(record)) push(scope, { action: 'unhide', id })
				ensured.set(cacheKey, id)
				return id
			}
			for (const scope of this.scopes.filter(scope => scope.editable)) {
				const draft = e.drafts[scope.key], record = draft.record
				if (!record && !draft.enabled) continue
				let id = record?.id
				const editable = this.admin || !this.flag(record?.is_global)
				const parentId = e.kind === 'categories' && draft.enabled && editable ? ensureParent(scope, e.parentKey) : undefined
				if (!record) {
					id = `@entry${operations.length}`
					push(scope, { action: 'create', ref: id, name: e.name.trim(), type: e.type, icon: e.icon })
				}
				if (editable) {
					const name = !record || e.name.trim() !== e.row?.name ? e.name.trim() : record.name
					const payload = { action: 'update', id, name }
					let changed = !record || name !== record.name
					if (e.kind === 'categories') {
						if (!record || draft.code !== (record.code || '')) { payload.code = draft.code; changed = true }
						if (parentId !== undefined && (!record || parentId !== Number(record.parent_category_id || 0))) { payload.parentCategoryId = parentId; changed = true }
					} else {
						const original = normalizePaymentPartnerDetails(createPaymentPartnerDetails(record))
						payload.details = Object.fromEntries(Object.entries(normalizePaymentPartnerDetails(draft.details)).filter(([key, value]) => value !== original[key]))
						changed ||= Object.keys(payload.details).length > 0
					}
					if (changed) push(scope, payload)
					if (e.kind === 'categories' && (!record || e.icon !== e.row.icon)) push(scope, { action: 'updateIcon', id, icon: e.icon })
				}
				if (!record || draft.enabled !== this.active(record)) push(scope, { action: draft.enabled ? 'unhide' : 'hide', id })
				if (record && !draft.enabled && this.active(record) && e.kind === 'categories') {
					for (const child of this.rows.categories.filter(row => row.parentKey === e.row.key)) {
						const childRecord = child.records[scope.key]
						if (this.active(childRecord)) push(scope, { action: 'hide', id: childRecord.id })
					}
				}
			}
			return operations
		},
		async refreshEditor(previous) {
			if (!await this.load({ quiet: true })) {
				this.refreshRequired = true
				this.saveError = this.$texts.masterData.refreshError()
				return false
			}
			// Names and parent paths can change group keys; follow persisted record IDs.
			const records = Object.entries(previous.row?.records || {}).sort(([a], [b]) => Number(!!this.scopes.find(s => s.key === b)?.editable) - Number(!!this.scopes.find(s => s.key === a)?.editable))
			const row = records.map(([scope, record]) => this.rows[previous.kind].find(item => Number(item.records[scope]?.id) === Number(record.id))).find(Boolean)
			if (!row) { this.refreshRequired = true; this.saveError = this.$texts.masterData.refreshError(); return false }
			const close = this.closeRequested
			this.openEditor(previous.kind, previous.type, row)
			if (this.editor.drafts[previous.detailScope]) this.editor.detailScope = previous.detailScope
			if (close) this.editor = null
			this.$emit('changed')
			return true
		},
		async submitOperations(operations, keepOpen = false) {
			this.saving = true; this.saveError = ''
			const previous = this.editor
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/${this.admin ? 'admin/' : ''}master-data`), { operations })
				if (keepOpen) { previous.baseline = this.draftFingerprint(); return await this.refreshEditor(previous) }
				this.editor = null; this.confirmDelete = false
				await this.load({ quiet: true }); this.$emit('changed')
				return true
			} catch (error) { this.saveError = extractError(error, this.$texts.masterData.saveError()); this.confirmDelete = false; this.closeRequested = false; return false }
			finally { this.saving = false }
		},
		async save() {
			if (!this.editor || this.saving || this.refreshRequired || this.advancedDrafts) return false
			if (this.editor.row && !this.hasChanges) return true
			if (!this.editor.name.trim() || !this.scopes.every(scope => { const draft = this.editor.drafts[scope.key]; return !scope.editable || draft.details.email === createPaymentPartnerDetails(draft.record).email || isValidPaymentPartnerEmail(draft.details.email) })) { this.saveError = this.$texts.masterData.validationError(); return false }
			try {
				const operations = this.buildOperations()
				if (!operations.length) { this.discardAndClose(); return true }
				this.closeRequested = true
				return await this.submitOperations(operations, !!this.editor.row)
			} catch (error) { this.saveError = error.message; return false }
		},
		async deleteRecord() {
			if (!this.canDelete || this.saving || this.refreshRequired || this.hasChanges) return
			// One transaction: never leave a row partially deleted if any scope rejects deletion.
			const ids = [...new Set(this.deleteTargets.map(({ draft }) => Number(draft.record.id)))]
			return this.submitOperations(ids.map(id => ({ kind: this.editor.kind, action: 'destroy', id })))
		},
	},
}
</script>

<style scoped>
.master-detail-sidebar { width: 200px; }
.master-detail-title { margin: 0; padding: calc(var(--default-grid-baseline, 4px) * 3); font-size: var(--cobudget-dialog-title-size, 20px); line-height: 1.5; overflow-wrap: anywhere; }
.master-detail-navigation { display: flex; flex-direction: column; gap: var(--default-grid-baseline, 4px); margin: 0; padding: var(--default-grid-baseline, 4px); list-style: none; }
.master-detail-dirty { display: inline-flex; flex-shrink: 0; align-items: center; color: var(--color-primary-element); }
/* Mirror NcAppSettingsDialog's Files layout while keeping only one scope mounted. */
@media (min-width: 1024px) {
	.master-advanced-dialog { --master-nav-tint: hsl(from var(--color-primary-element-light) h s calc(l * 1.045)); }
	.master-advanced-dialog :deep(.modal-wrapper .modal-container) { padding: 0 !important; background: var(--master-nav-tint, var(--color-main-background)); overflow: hidden; }
	.master-advanced-dialog :deep(.dialog__name) { position: absolute; width: 1px; height: 1px; min-height: 0; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(100%); white-space: nowrap; }
	.master-advanced-dialog :deep(.dialog__wrapper:not(.dialog__wrapper--collapsed) .dialog__navigation) { flex: 0 0 200px; min-width: 200px; max-width: 200px; margin: 0; height: auto; }
	.master-advanced-dialog :deep(.dialog__content) { padding: var(--cobudget-dialog-padding); padding-block-start: calc(var(--cobudget-dialog-padding) + var(--default-grid-baseline, 4px) * 4); background: var(--color-main-background); border-inline-start: 1px solid var(--color-border-dark); border-start-start-radius: var(--border-radius-element); }
	.master-advanced-dialog :deep(.dialog__actions) { width: calc(100% - 200px); margin-block: 0 !important; margin-inline: 200px 0 !important; padding: var(--cobudget-dialog-padding); background: var(--color-main-background); border-inline-start: 1px solid var(--color-border-dark); box-sizing: border-box; }
	.master-detail-navigation :deep(.app-navigation-entry:not(.app-navigation-entry--legacy).active) { background: var(--color-primary-element-light) !important; }
	.master-detail-navigation :deep(.app-navigation-entry:not(.app-navigation-entry--legacy).active:hover) { background: var(--color-primary-element-light-hover) !important; }
}
.master-detail-area { display: flex; align-items: center; gap: var(--cobudget-space-2, 8px); min-width: 0; overflow-wrap: anywhere; }
.master-detail-area-name { min-width: 0; overflow-wrap: anywhere; }
.master-detail-area--selected .master-detail-area-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.master-detail-area > :deep(.area-icon) { flex-shrink: 0; }
.master-advanced-close { position: absolute; inset-block-start: var(--default-grid-baseline, 4px); inset-inline-end: var(--default-grid-baseline, 4px); }
.master-advanced-dialog :deep(.dialog__navigation) { min-width: 0; }
.master-advanced-dialog :deep(.dialog__wrapper--collapsed .dialog__navigation) { overflow: visible; padding-inline-end: var(--cobudget-dialog-padding); margin-block-end: var(--cobudget-space-4, 16px); box-sizing: border-box; }
.master-detail-mobile-select { width: 100%; min-width: 0; }
.master-details { min-width: 0; padding-block-end: var(--default-grid-baseline, 4px); }
.master-details p { margin: 0; }
.master-details > :not(:first-child) { margin-block-start: calc(var(--default-grid-baseline, 4px) * 3); }
/* Keep the native fixed navigation/actions and scroll only the details on mobile. */
@media (max-width: 768px) {
	.master-advanced-dialog { --cobudget-dialog-padding: var(--cobudget-space-4, 16px); }
	.master-advanced-dialog :deep(.dialog__name) { margin-block-end: var(--cobudget-space-2, 8px); margin-inline-end: 0; }
	.master-advanced-dialog :deep(.dialog__wrapper--collapsed .dialog__navigation) { margin-block-end: calc(var(--default-grid-baseline, 4px) * 3); }
	.master-detail-mobile-select :deep(.select__label) { font-weight: var(--font-weight-element, 500); color: var(--color-main-text); }
	.master-detail-mobile-select .master-detail-area { width: 100%; }
	.master-advanced-dialog :deep(.payment-partner-group-fields) { padding: var(--cobudget-space-2, 8px) var(--default-grid-baseline, 4px); gap: var(--cobudget-space-2, 8px); }
	.master-advanced-dialog :deep(.dialog__actions) { box-sizing: border-box; margin-block: calc(var(--default-grid-baseline, 4px) * 3) max(var(--cobudget-dialog-padding), env(safe-area-inset-bottom)) !important; }
	.master-advanced-dialog :deep(.dialog__actions > .button-vue) { flex: 1 1 0; min-width: 0; }
}

.master-loading { display: flex; align-items: center; gap: var(--cobudget-space-2, 8px); color: var(--color-text-maxcontrast); }
.master-data-settings { min-width: 0; margin-block: calc(var(--default-grid-baseline, 4px) * 6); }
.master-section + .master-section { margin-block-start: calc(var(--default-grid-baseline, 4px) * 8); }
.master-heading, .master-name { display: flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 2); }
.master-heading { justify-content: space-between; margin-block: calc(var(--default-grid-baseline, 4px) * 4) calc(var(--default-grid-baseline, 4px) * 2); }
.master-heading h4 { margin: 0; font-size: var(--cobudget-font-subheading); font-weight: var(--cobudget-font-weight-heading); }
.master-table-scroll { overflow-x: auto; }
.master-table { --master-personal-extra: 0px; table-layout: fixed; width: max(100%, calc(var(--master-scope-count) * 112px + 284px + var(--master-personal-extra))); border-collapse: separate; border-spacing: 0; font-size: var(--cobudget-font-body); }
.master-table--personal-scope { --master-personal-extra: 32px; }
.master-table th.master-personal-scope { width: 144px; min-width: 144px; white-space: nowrap; }
.master-table th, .master-table td { box-sizing: border-box; padding: var(--cobudget-table-payment-padding); height: auto; border-bottom: 1px solid var(--cobudget-border); text-align: center; vertical-align: middle; min-width: 100px; font-size: var(--cobudget-font-body); font-weight: 400; color: var(--cobudget-text); }
.master-table th:first-child { text-align: start; width: 240px; position: sticky; inset-inline-start: 0; z-index: 1; background: var(--cobudget-surface); }
.master-name { white-space: normal; min-height: var(--cobudget-button-height, 34px); }
.master-table-hierarchy tbody th:first-child { padding-inline-start: var(--default-grid-baseline, 4px); }
.master-label { min-width: 0; overflow-wrap: anywhere; }
.master-global { margin-inline-start: var(--default-grid-baseline, 4px); color: var(--cobudget-text-muted); }
.master-name > :deep(.category-icon) { margin-inline-end: 0; padding: calc(var(--default-grid-baseline, 4px) * 0.75); }
.master-tree-toggle { position: relative; display: flex; align-items: center; justify-content: center; align-self: stretch; flex: 0 0 var(--cobudget-button-height, 34px); margin-inline-end: calc(var(--default-grid-baseline, 4px) * -1); }
/* Start beneath the parent icon's center and end just before the child icon. */
.master-tree-branch { --branch-start: calc(var(--cobudget-button-height, 34px) - var(--default-grid-baseline, 4px) * 2.25); }
.master-tree-branch::before { content: ''; position: absolute; inset-inline-start: var(--branch-start); inset-inline-end: 0; inset-block-start: 0; height: 50%; border-inline-start: 1px solid var(--cobudget-text-muted); border-block-end: 1px solid var(--cobudget-text-muted); }
.master-table tr:has(+ tr .master-tree-branch) .master-tree-branch::after { content: ''; position: absolute; inset-inline-start: var(--branch-start); inset-block-start: 50%; bottom: -1px; border-inline-start: 1px solid var(--cobudget-text-muted); }
.master-table th { white-space: normal; overflow-wrap: anywhere; }
.master-name > :deep(.category-icon), .master-name > :deep(.button-vue) { flex-shrink: 0; }
.master-table th:last-child, .master-table td:last-child { min-width: auto; width: calc(var(--cobudget-mobile-touch-size, 44px) + var(--default-grid-baseline, 4px) * 2); padding-inline: var(--default-grid-baseline, 4px); position: sticky; inset-inline-end: 0; z-index: 1; background: var(--cobudget-surface); }
.master-table thead th, .master-table thead th:first-child, .master-table thead th:last-child { padding: var(--cobudget-table-cell-padding); background: var(--cobudget-table-header-background); color: var(--cobudget-text-muted); letter-spacing: normal; font-weight: 400; font-size: var(--cobudget-table-header-font-size); height: var(--cobudget-table-header-height); }
.master-table tbody tr:hover > *, .master-table tbody tr:focus-within > * { background: var(--cobudget-table-row-hover); }
.master-table tbody tr:has(.master-name), .master-table tbody tr:has(.master-name) > *, .master-name, .master-label, .master-global, .master-table tbody :deep(.material-design-icon) { cursor: pointer; }
.master-table small { display: block; font-weight: normal; color: var(--color-text-maxcontrast); }
.master-active { color: var(--color-primary-element); }
.master-editor-body { display: contents; }
.master-delete-button :deep(.button-vue__icon) { display: none; }
/* NcButton reserves less start padding for its icon slot, even when hidden. */
@media (min-width: 769px) {
	.master-editor .master-delete-button { padding-inline: var(--button-padding-default); }
	.master-delete-button :deep(.button-vue__text) { padding-inline: var(--default-grid-baseline, 4px); }
}

.master-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 100%; margin-top: 24px; }
.master-confirm-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-inline-start: auto; }
.master-editor { display: grid; gap: calc(var(--default-grid-baseline, 4px) * 2); padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding, 24px) var(--cobudget-dialog-padding, 24px); }
.master-editor h2 { margin: 0 0 calc(var(--default-grid-baseline, 4px) * 2); padding-inline-end: var(--cobudget-mobile-touch-size, 44px); font-size: var(--cobudget-dialog-title-size, 20px); text-align: start; }
.master-editor p, .master-editor h3 { margin: 0; }
.master-identity { display: grid; gap: calc(var(--default-grid-baseline, 4px) * 3); align-items: end; min-width: 0; }
.master-identity-category { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.master-identity > *, .master-editor-section > * { min-width: 0; }
.master-identity-field { min-width: 0; }
/* Use external labels and the same native clickable height for the identity row.
   NcInputField otherwise reserves an inset for focus; NcSelect reserves it outside. */
.master-identity-field :deep(.input-field__main-wrapper) { padding: 0; }
.master-editor :deep(.v-select.select) { min-width: 0; margin: 0; }
.master-name-input { display: flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 2); min-width: 0; }
.master-name-input > .input-field { flex: 1; min-width: 0; }
.master-editor-section { margin-block-start: calc(var(--default-grid-baseline, 4px) * 6); }
.master-scopes { display: grid; grid-template-columns: minmax(0, 1fr); gap: calc(var(--default-grid-baseline, 4px) * 2); }
.master-scopes-multiple { grid-template-columns: repeat(2, minmax(0, 1fr)); }
@media (max-width: 768px) {
	.master-editor-modal { --cobudget-dialog-padding: var(--cobudget-space-4, 16px); }
	.master-editor-modal :deep(.modal-container) { height: calc(100% - var(--header-height)); overflow: hidden; }
	.master-editor-modal :deep(.modal-container__content) { display: flex; min-height: 0; overflow: hidden; }
	.master-editor { display: flex; flex-direction: column; width: 100%; min-width: 0; height: 100%; min-height: 0; padding: 0; gap: 0; }
	.master-editor h2 { flex-shrink: 0; margin: 0; padding: calc(var(--default-grid-baseline, 4px) * 3) calc(var(--cobudget-mobile-touch-size, 44px) + var(--cobudget-dialog-padding)) var(--cobudget-space-2, 8px) var(--cobudget-dialog-padding); line-height: 1.5; }
	.master-editor-body { display: grid; align-content: start; flex: 1; min-height: 0; overflow: auto; gap: var(--cobudget-space-2, 8px); padding: var(--default-grid-baseline, 4px) var(--cobudget-dialog-padding) calc(var(--default-grid-baseline, 4px) * 3); }
	.master-editor .cobudget-form-hint { font-size: var(--cobudget-font-sm, 13px); line-height: 1.4; }
	.master-editor-section { margin-block-start: var(--cobudget-space-4, 16px); }
	.master-identity { gap: var(--cobudget-space-2, 8px); }
	.master-actions { flex-shrink: 0; flex-wrap: nowrap; width: auto; margin: 0; padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding) max(var(--cobudget-dialog-padding), env(safe-area-inset-bottom)); }
	.master-confirm-actions { flex: 1; min-width: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.master-confirm-actions > :deep(.button-vue) { min-width: 0; width: 100%; }
	.master-delete-popover { flex: 0 0 var(--cobudget-mobile-touch-size, 44px); }
	.master-delete-button { flex: 0 0 var(--cobudget-mobile-touch-size, 44px); width: var(--cobudget-mobile-touch-size, 44px); min-width: 0; padding-inline: 0; }
	.master-delete-button :deep(.button-vue__icon) { display: flex; }
	.master-delete-label { display: none; }
}
@media (max-width: 768px) { .master-identity-category { grid-template-columns: minmax(0, 1fr); } .master-parent-field { grid-column: 1 / -1; } .master-table.master-table-hierarchy { width: calc(var(--master-scope-count) * 112px + 264px + var(--master-personal-extra)); } .master-table.master-table-hierarchy th:first-child { width: 220px; min-width: 220px; } .master-scopes-multiple { grid-template-columns: minmax(0, 1fr); } .master-table :deep(.button-vue) { min-width: var(--cobudget-mobile-touch-size, 44px); min-height: var(--cobudget-mobile-touch-size, 44px); } .master-table { width: calc(var(--master-scope-count) * 112px + 224px + var(--master-personal-extra)); } .master-table th:first-child { width: 180px; min-width: 180px; } }
.master-table :is(th, td) { transition: background-color var(--cobudget-hover-duration) linear; }
.master-table tr:focus-within > * { transition: none; }
</style>
