<template>
 <NcAppSidebar class="reminder-sidebar" :name="sidebarTitle" :aria-label="sidebarTitle" :open="isOpen" :no-toggle="true" @update:open="requestClose" @opened="focusFirstField" @closed="restoreFocus">
  <template #info>
   <div v-if="!isWideViewport" class="reminder-mobile-header app-sidebar-header__desc app-sidebar-header__desc--without-actions">
    <EntrySidebarHeading id="reminder-sidebar-title">{{ sidebarTitle }}</EntrySidebarHeading>
   </div>
   <div v-else class="reminder-header-field reminder-field">
    <label for="reminder-time">{{ r.remindAt() }}</label>
    <NcInputField id="reminder-time" v-model="draft.localDate" type="date" class="cobudget-native-field" label-outside :aria-label="r.remindAt()" required :disabled="busy" />
   </div>
  </template>
  <form class="reminder-form" @submit.prevent="save">
   <div class="reminder-form-body">
    <NcNoteCard v-if="error" type="error">{{ error }}</NcNoteCard>
    <div v-if="!isWideViewport" class="reminder-field">
     <label for="reminder-time">{{ r.remindAt() }}</label>
     <NcInputField id="reminder-time" v-model="draft.localDate" type="date" class="cobudget-native-field" label-outside :aria-label="r.remindAt()" required :disabled="busy" />
    </div>
    <div v-if="hasCustomReminderTime" class="reminder-field">
     <label for="reminder-clock">{{ r.timeField() }}</label>
     <NcInputField id="reminder-clock" v-model="draft.localTime" type="time" class="cobudget-native-field" label-outside :aria-label="r.timeField()" :disabled="busy" />
    </div>
    <div class="reminder-field">
    <label for="reminder-title">{{ r.titleField() }}</label>
    <NcInputField class="cobudget-native-field" id="reminder-title" v-model="draft.title" :aria-label="r.titleField()" label-outside required maxlength="255" :disabled="busy" @keydown.enter.prevent="save" />
    </div>
    <div class="reminder-field"><label for="reminder-description">{{ r.description() }}</label><NcTextArea class="cobudget-native-field" id="reminder-description" v-model="draft.description" :label="r.description()" label-outside :rows="4" maxlength="10000" :disabled="busy" /></div>
    <div class="reminder-field">
     <div id="reminder-labels-heading" class="reminder-field-label">{{ r.labels() }}</div>
     <div class="tags-toggles" role="group" aria-labelledby="reminder-labels-heading">
      <label v-for="option in labelOptions" :key="option.id" class="tag-toggle">
       <input v-model="draft.labels" type="checkbox" :value="option.id" :disabled="busy" />
       <span class="tag-btn"><NavigationIcon :name="option.id" :size="16" />{{ option.label }}</span>
      </label>
     </div>
    </div>
    <div class="reminder-switches">
     <NcFormBoxSwitch class="reminder-switch" :model-value="!draft.keep_open" :disabled="busy" @update:model-value="draft.keep_open = !$event">
      <span class="reminder-switch-label"><ArchiveClockOutline :size="20" aria-hidden="true" /><span>{{ r.autoArchive() }}</span></span>
     </NcFormBoxSwitch>
     <NcFormBoxSwitch v-if="draft.id" class="reminder-switch" :model-value="draft.completed" :disabled="busy" @update:model-value="setCompleted">
      <span class="reminder-switch-label"><CheckCircleOutline :size="20" aria-hidden="true" /><span>{{ r.completedState() }}</span></span>
     </NcFormBoxSwitch>
     <NcFormBoxSwitch v-if="draft.id" class="reminder-switch" :model-value="draft.archived" :disabled="busy" @update:model-value="setArchived">
      <span class="reminder-switch-label"><ArchiveOutline :size="20" aria-hidden="true" /><span>{{ r.archivedState() }}</span></span>
     </NcFormBoxSwitch>
    </div>
    <div v-if="draft.entry_id" class="reminder-payment-link">
     <NcButton variant="tertiary" alignment="center-reverse" :disabled="busy" @click="openPayment"><template #icon><ArrowRightIcon :size="20" /></template>{{ r.showPayment() }}</NcButton>
    </div>
   </div>
   <div class="reminder-footer">
    <ModalActions flush inline-mobile :cancel-label="$texts.common.cancel()" :cancel-disabled="busy" :primary-label="$texts.common.save()" :primary-disabled="!draft.title.trim() || !draft.localDate" :primary-busy="busy" @cancel="requestClose(false)">
     <template v-if="draft.id" #left><NcButton variant="error" :disabled="busy" :aria-label="r.delete()" @click="confirmation = 'delete'"><template #icon><TrashCanOutline :size="20" /></template></NcButton></template>
    </ModalActions>
   </div>
  </form>
 </NcAppSidebar>
 <DialogTransition :show="!!confirmation" v-slot="{ returnFocus }">
  <NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog"
   :name="confirmation === 'delete' ? r.delete() : r.discard()" :no-close="busy" :close-on-click-outside="!busy" @closing="cancelConfirmation">
   <p class="reminder-confirm-message">{{ confirmation === 'delete' ? r.deleteHint() : r.discardHint() }}</p>
   <template #actions>
    <NcButton variant="tertiary" :disabled="busy" @click="cancelConfirmation">{{ $texts.common.cancel() }}</NcButton>
    <NcButton :variant="confirmation === 'delete' ? 'error' : 'primary'" :disabled="busy" @click="confirmAction">
     <template v-if="busy" #icon><NcLoadingIcon :size="20" /></template>
     {{ busy ? $texts.common.waitBusy() : confirmation === 'delete' ? r.delete() : r.discard() }}
    </NcButton>
   </template>
  </NcDialog>
 </DialogTransition>
</template>
<script>
import { showToast } from '../services/notifications'
import EntrySidebarHeading from './EntrySidebarHeading.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NavigationIcon from './NavigationIcon.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import ArchiveClockOutline from 'vue-material-design-icons/ArchiveClockOutline.vue'
import ArchiveOutline from 'vue-material-design-icons/ArchiveOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import DialogTransition from './DialogTransition.vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ModalActions from './ModalActions.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { captureDialogFocus, trackDialogTriggers } from '../services/dialogFocus'
const config = { skipWorkspaceHeader: true }
const localDate = timestamp => { const d = new Date(timestamp * 1000); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10) }
export default {
 components: { EntrySidebarHeading, ArrowRightIcon, ModalActions, NcAppSidebar, NcInputField, NcTextArea, NavigationIcon, NcButton, NcNoteCard, NcFormBoxSwitch, ArchiveClockOutline, ArchiveOutline, CheckCircleOutline, TrashCanOutline, DialogTransition, NcDialog, NcLoadingIcon },
 emits: ['open-changed'],
 data: () => ({ isWideViewport: false, viewportQuery: null, isOpen: false, busy: false, draft: { title: '', labels: [] }, original: '', error: '', confirmation: '', confirmResolve: null, focusContext: null, removeGuard: null, stopTracking: null }),
 computed: {
  r() { return this.$texts.reminders },
  hasCustomReminderTime() { return this.draft.originalReminderTime && new Date(this.draft.originalReminderTime * 1000).toTimeString().slice(0, 5) !== '09:00' },
  sidebarTitle() { return this.draft.id ? this.r.editReminder() : this.r.newReminder() },
  paymentLocation() { return { name: this.draft.entry_kind === 'shared' ? 'project-detail' : 'personal', params: this.draft.entry_kind === 'shared' ? { id: this.draft.project_id } : {}, query: { openPayment: String(this.draft.entry_id), workspace: String(this.draft.payment_workspace_id) } } },
  dirty() { return this.isOpen && JSON.stringify(this.draft) !== this.original },
  labelOptions() { return ['cancel', 'renew', 'review', 'pay'].map(id => ({ id, label: this.r[id]() })) },
 },
 mounted() {
  this.viewportQuery = window.matchMedia('(min-width: 1025px)')
  this.updateViewport(this.viewportQuery)
  this.viewportQuery.addEventListener('change', this.updateViewport)
  this.stopTracking = trackDialogTriggers(document)
  this.removeGuard = this.$router.beforeEach(async to => { if (this.isOpen && to.name !== 'reminders') return this.requestClose(false); return true })
  window.addEventListener('beforeunload', this.beforeUnload)
 },
 beforeUnmount() { this.viewportQuery?.removeEventListener('change', this.updateViewport); this.removeGuard?.(); this.stopTracking?.(); window.removeEventListener('beforeunload', this.beforeUnload); this.confirmResolve?.(false) },
 methods: {
  updateViewport(event) { this.isWideViewport = !!event.matches },
  openPayment() { if (!this.busy && !this.confirmation) return this.$router.push(this.paymentLocation) },
  beforeUnload(event) { if (this.dirty || this.busy) { event.preventDefault(); event.returnValue = '' } },
  async open(row = null, { confirmDelete = false, focusDate = false } = {}) {
   if (this.busy || this.confirmation || (this.dirty && !(await this.askDiscard()))) return
   this.focusContext = captureDialogFocus(document)
   const next = new Date(); next.setHours(9, 0, 0, 0)
   if (next.getTime() <= Date.now()) next.setDate(next.getDate() + 1)
   const at = row?.remind_at || next.getTime() / 1000
   this.draft = { id: row?.id || null, archived: row?.status === 'archived', revision: row?.revision || 0, title: row?.title || '', description: row?.description || '', localDate: localDate(at), localTime: new Date(at * 1000).toTimeString().slice(0, 5), originalReminderTime: row?.remind_at || null, due_date: row?.due_date || '', labels: [...(row?.labels || [])], keep_open: !!row?.keep_open, completed: !!row?.completed_at, entry_id: row?.entry_id || null, payment_title: row?.payment_title, project_name: row?.project_name, project_id: row?.project_id, entry_kind: row?.entry_kind, payment_workspace_id: row?.payment_workspace_id }
   this.original = JSON.stringify(this.draft); this.error = ''; this.isOpen = true; this.$emit('open-changed', true, this.draft.id)
   if (confirmDelete && this.draft.id) this.confirmation = 'delete'
   if (focusDate) { await this.$nextTick(); this.focusFirstField() }
  },
  async postpone(row, remindAt) {
   if (this.busy || this.confirmation) return
   const discardDraft = this.dirty
   if (discardDraft && !(await this.askDiscard())) return
   this.busy = true
   try {
    await axios.post(generateUrl(`/apps/cobudget/api/reminders/${row.id}/postpone`), { revision: row.revision, remindAt }, config)
    if (discardDraft || (this.isOpen && this.draft.id === row.id)) this.saved()
    else window.dispatchEvent(new CustomEvent('cobudget-reminders-changed'))
    showToast(this.r.postponed())
   } catch (error) { showToast(error.response?.data?.error || this.r.postponeError(), 'error') }
   finally { this.busy = false }
  },
  focusFirstField() { if (this.confirmation) return; document.getElementById('reminder-time')?.focus({ preventScroll: true }) },
  restoreFocus() { this.focusContext?.() },
  askDiscard() { if (this.confirmation) return Promise.resolve(false); this.confirmation = 'discard'; return new Promise(resolve => { this.confirmResolve = resolve }) },
  async requestClose(open) { if (open || this.busy || this.confirmation) return false; if (this.dirty && !(await this.askDiscard())) return false; this.isOpen = false; this.$emit('open-changed', false); return true },
  cancelConfirmation() { if (this.busy) return; this.confirmation = ''; this.confirmResolve?.(false); this.confirmResolve = null },
  async confirmAction() { if (this.busy) return; if (this.confirmation === 'discard') { this.confirmation = ''; this.confirmResolve?.(true); this.confirmResolve = null } else { await this.remove() } },
  async save() {
   if (this.busy || this.confirmation || !this.draft.title.trim() || !this.draft.localDate) return
   this.busy = true; this.error = ''
   try { const data = { ...this.draft, remind_at: this.draft.originalReminderTime && this.draft.localDate === localDate(this.draft.originalReminderTime) && this.draft.localTime === new Date(this.draft.originalReminderTime * 1000).toTimeString().slice(0, 5) ? this.draft.originalReminderTime : Math.floor(new Date(this.draft.localDate + 'T' + (this.draft.localTime || '09:00') + ':00').getTime() / 1000) }; await axios.post(generateUrl('/apps/cobudget/api/reminders'), { id: this.draft.id, data }, config); this.saved() } catch (e) { this.error = e.response?.data?.error || this.r.saveError() } finally { this.busy = false }
  },
  setCompleted(value) { this.draft.completed = value },
  setArchived(value) { this.draft.archived = value },
  async remove() { if (this.busy) return; this.busy = true; this.error = ''; try { await axios.delete(generateUrl(`/apps/cobudget/api/reminders/${this.draft.id}`), { ...config, data: { revision: this.draft.revision } }); this.confirmation = ''; this.saved() } catch (e) { this.error = e.response?.data?.error || this.r.saveError(); this.confirmation = '' } finally { this.busy = false } },
  saved() { this.original = JSON.stringify(this.draft); this.isOpen = false; this.$emit('open-changed', false); window.dispatchEvent(new CustomEvent('cobudget-reminders-changed')) },
 },
}
</script>
<style scoped>
.reminder-confirm-message { margin: 0; white-space: pre-line; }
.reminder-sidebar {
 --reminder-content-padding: 4px;
 --reminder-switch-hover: color-mix(in srgb, var(--color-primary-element) 8%, transparent);
 --reminder-field-gap: calc(var(--default-grid-baseline, 4px) * 4);
 --app-sidebar-width: 375px !important;
 overflow: hidden;
 background: var(--color-main-background);
 color: var(--color-main-text);
}
.reminder-sidebar :deep(.app-sidebar-tabs), .reminder-sidebar :deep(.app-sidebar-tabs__content) { min-height: 0; overflow: hidden; }
.reminder-sidebar :deep(.app-sidebar-header) { border-bottom: none !important; background: var(--cobudget-surface-muted, var(--color-background-dark)); }
.reminder-mobile-header { min-width: 0; padding-block: var(--app-sidebar-padding, 8px); padding-inline-start: var(--reminder-content-padding); padding-inline-end: calc(var(--app-sidebar-padding, 8px) + var(--default-clickable-area) + 8px); }
.reminder-mobile-header :deep(.app-sidebar-header__mainname) { display: flex; align-items: center; min-height: var(--default-clickable-area, 44px); margin: 0; font-size: var(--default-font-size, 15px) !important; font-weight: var(--font-weight-element, 500); line-height: 1.5 !important; white-space: nowrap !important; overflow: hidden; text-overflow: ellipsis; }
.reminder-header-field { padding: var(--app-sidebar-padding, 8px) var(--reminder-content-padding) 0; }
.reminder-field { min-width: 0; }
.reminder-field > label, .reminder-field-label { display: block; margin: 0 0 var(--default-grid-baseline, 4px); font-size: var(--font-size, 15px); font-weight: var(--font-weight-element, 500); line-height: 1.5; color: var(--color-main-text); }
.reminder-header-field > label { display: flex; align-items: center; min-height: var(--default-clickable-area); padding-inline-end: calc(var(--default-clickable-area) + var(--default-grid-baseline, 4px)); }
.reminder-field :deep(.input-field__main-wrapper), .reminder-field :deep(.textarea__main-wrapper) { padding: 0; }
.reminder-field :deep(.textarea) { margin-block: 0; }
.reminder-field :deep(.textarea__input) { display: block; }
.reminder-form { display: flex; flex: 1 1 auto; flex-direction: column; width: 100%; height: 100%; min-height: 0; min-width: 0; margin: 0; padding: 0; overflow: hidden; }
.reminder-form-body { box-sizing: border-box; display: flex; flex-direction: column; gap: var(--reminder-field-gap); padding: var(--reminder-field-gap) var(--reminder-content-padding); min-height: 0; min-width: 0; overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; flex: 1; }
.reminder-switches { display: flex; flex-direction: column; }
.reminder-switches .reminder-switch {
 --nc-form-box-item-border-width: 0px;
 border: none;
 background: transparent;
 padding-inline: calc(var(--default-grid-baseline, 4px) * 2 - 2px);
 transition-duration: var(--cobudget-hover-duration);
 transition-timing-function: linear;
}
.reminder-switches .reminder-switch:hover:not(:has(:disabled)) { background: var(--reminder-switch-hover); color: var(--color-main-text); }
.reminder-sidebar:has(.button-vue--legacy34) { --reminder-switch-hover: var(--color-background-hover); }
.reminder-switch-label { display: flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 1.5 - 2px); }
.reminder-switch-label > :first-child { flex-shrink: 0; }
.reminder-footer { flex-shrink: 0; padding: 10px var(--reminder-content-padding); border-top: 1px solid var(--cobudget-border, var(--color-border)); background: var(--cobudget-surface-muted, var(--color-background-dark)); }
@media (min-width: 769px) {
 .reminder-sidebar { --app-sidebar-width: clamp(225px, 20.25vw, 375px) !important; width: var(--app-sidebar-width) !important; max-width: 375px; }
}
@media (min-width: 1025px) {
 .reminder-sidebar { border-inline-start: 0 !important; background: transparent !important; }
 .reminder-sidebar :deep(.app-sidebar-header) { position: relative; flex: 0 0 auto; height: auto; min-height: var(--default-clickable-area); margin: var(--app-sidebar-padding, 8px) calc(var(--app-sidebar-padding, 8px) + var(--reminder-content-padding)) 0 0; padding: 0; background: transparent !important; }
 .reminder-sidebar :deep(.app-sidebar-header > .app-sidebar__close) { top: 0; inset-inline-end: 0; }
 .reminder-header-field { padding: 0; }
 .reminder-form-body { padding-inline: 0; scrollbar-gutter: stable; scrollbar-width: thin; scrollbar-color: transparent transparent; }
 .reminder-form-body:hover, .reminder-form-body:focus-within { scrollbar-color: var(--color-border-dark, var(--color-border)) transparent; }
 .reminder-footer { padding: 8px 0 12px; overflow-y: hidden; scrollbar-gutter: stable; scrollbar-width: thin; border-top: 0; background: transparent; }
}
@media (max-width: 768px) {
 .reminder-switches .reminder-switch { min-height: var(--cobudget-mobile-touch-size, 44px); }
 .reminder-sidebar { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); --reminder-content-padding: var(--cobudget-content-gap); --app-sidebar-width: 100vw !important; width: 100vw !important; }
 .reminder-header-field { padding-inline-end: var(--reminder-content-padding); }
 .reminder-footer { padding-bottom: max(10px, env(safe-area-inset-bottom)); }
}
</style>

<style scoped src="../styles/label-toggles.css"></style>
