<template>
 <div class="reminders-view">
  <AppPageHeader class="reminders-page-header cobudget-filter-toolbar">
   <template #creation><NcButton variant="primary" class="cobudget-native-button mobile-create-fab" :aria-label="r.newReminder()" @click="open()"><template #icon><Plus :size="20" /></template><span class="creation-label-desktop">{{ $texts.common.newItem() }}</span><span class="btn-text">{{ r.newReminder() }}</span></NcButton></template>
   <template #title><ReportPeriodPicker navigation-only force-navigation :title="r.overview()" :period-enabled="false" label="" /></template>
   <template #actions><ReminderFilterBar v-bind="filterProps" @update:filters="updateFilters" /></template>
  </AppPageHeader>
  <ReminderFilterBar chips-only v-bind="filterProps" @update:filters="updateFilters" />
  <NcNoteCard v-if="error" type="error">{{ error }}<NcButton @click="load">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
  <NcLoadingIcon v-if="loading && !rows.length" :size="32" />
  <NcEmptyContent v-else-if="!filtered.length && !error" class="empty-content-state" :name="hasActiveFilters || rows.length ? r.empty() : r.emptyTitle()" :description="hasActiveFilters || rows.length ? r.emptyFilteredDescription() : r.emptyDescription()">
   <template #icon><BellOutline :size="64" /></template>
   <template #action>
    <NcButton v-if="hasActiveFilters" variant="secondary" @click="resetFilters">{{ $texts.filters.resetAll() }}</NcButton>
    <NcButton v-else variant="primary" @click="open()"><template #icon><Plus :size="20" /></template>{{ r.newReminder() }}</NcButton>
   </template>
  </NcEmptyContent>
  <div v-else-if="filtered.length" class="reminder-table-container">
   <table class="reminder-table"><thead><tr>
    <th scope="col" class="reminder-check">{{ r.completed() }}</th>
    <th scope="col" class="reminder-status">{{ r.status() }}</th>
    <th scope="col" class="reminder-date">{{ r.remindAt() }}</th>
    <th scope="col">{{ r.titleField() }}</th>
    <th scope="col">{{ r.labels() }}</th>
    <th scope="col" class="reminder-actions"><span class="hidden-visually">{{ r.rowActions() }}</span></th>
   </tr></thead>
    <tbody v-for="group in monthGroups" :key="group.key">
    <tr v-if="showMonthHeadings" class="reminder-month-row"><th scope="rowgroup" colspan="6"><h3>{{ group.label }}</h3></th></tr>
    <template v-for="(item, index) in group.items" :key="item.id">
    <tr v-if="startsDayGroup(group.items, index)" class="reminder-day-row"><th colspan="6" scope="rowgroup"><time :datetime="dayKey(item)">{{ dayHeading(item) }}</time><span v-if="relativeDateLabel(item)" class="reminder-day-relative"> · {{ relativeDateLabel(item) }}</span></th></tr>
    <tr :class="{ 'is-selected': isReminderSelected(item), 'is-overdue': item.status === 'active' && item.daysAgo > 0 }" :aria-selected="isReminderSelected(item) ? 'true' : 'false'" tabindex="0" @click="open(item)" @keydown.enter.self.prevent="open(item)" @keydown.space.self.prevent="open(item)">
     <td class="reminder-check" @click.stop><NcCheckboxRadioSwitch :model-value="!!item.completed_at" :aria-label="`${r.completed()}: ${item.title || r.sidebar()}`" :disabled="busyId !== null" @update:model-value="complete(item, $event)" /></td>
     <td class="reminder-status">
      <TableTooltip v-if="statusTooltip(item)" :text="statusTooltip(item)">
       <div class="reminder-status-badges">
        <NcChip v-if="item.completed_at" class="reminder-table-tag reminder-status-tag" data-cobudget-badge="success" variant="tertiary" :text="r.completed()" no-close><template #icon><CheckCircleOutline :size="16" /></template></NcChip>
        <NcChip v-if="item.status === 'archived'" class="reminder-table-tag reminder-status-tag" data-cobudget-badge="neutral" variant="tertiary" :text="r.archived()" no-close><template #icon><ArchiveOutline :size="16" /></template></NcChip>
       </div>
      </TableTooltip>
      <NcChip v-else class="reminder-table-tag reminder-status-tag" data-cobudget-badge="info" variant="tertiary" :text="r.openStatus()" no-close><template #icon><ClockOutline :size="16" /></template></NcChip>
     </td>
     <td class="reminder-date" :class="{ 'has-calendar-warning': item.calendar_sync_pending }"><div class="reminder-date-content"><div class="reminder-date-text"><time :datetime="new Date(item.remind_at * 1000).toISOString()">{{ formatDate(item.remind_at) }}</time><span v-if="relativeDateLabel(item)" class="reminder-relative-date">{{ relativeDateLabel(item) }}</span></div>
      <span v-if="item.calendar_sync_pending" class="reminder-calendar-action" @click.stop @keydown.stop>
       <TableTooltip :text="item.calendar_conflict ? r.calendarConflict() : r.calendarPendingRetry()" passive>
        <NcButton variant="tertiary" :aria-label="item.calendar_conflict ? r.calendarConflict() : r.calendarPendingRetry()" :aria-busy="calendarRetryId === item.id" :disabled="calendarRetryId !== null" @click="retryCalendar(item, $event)">
         <template #icon><NcLoadingIcon v-if="calendarRetryId === item.id" :size="20" /><CalendarAlertOutline v-else :size="20" /></template>
        </NcButton>
       </TableTooltip>
      </span>
     </div></td>
     <td class="reminder-summary"><span class="reminder-title">{{ item.title || r.sidebar() }}</span><p v-if="item.description" class="reminder-description">{{ item.description }}</p><span v-if="item.due_date" class="reminder-secondary">{{ r.dueDate() }}: {{ item.due_date }}</span></td>
     <td class="reminder-labels" :class="{ 'has-labels': item.labels.length || item.payment_labels.length }"><div class="reminder-tags">
      <PaymentLabelList v-if="item.payment_labels.length" :labels="paymentBadges(item.payment_labels)" />
      <NcChip v-for="tag in item.labels.filter(tag => ['cancel', 'renew', 'review', 'pay'].includes(tag))" :key="tag" class="reminder-table-tag reminder-action-tag" variant="tertiary" :text="labelText(tag)" no-close>
       <template #icon><NavigationIcon :name="tag" :size="16" /></template>
      </NcChip>
     </div></td>
     <td class="reminder-actions" @click.stop @keydown.stop>
      <NcActions :key="`${actionsResetKey}-${item.id}`" class="cobudget-action-menu" :aria-label="`${r.rowActions()}: ${item.title || r.sidebar()}`" :disabled="busyId !== null" @close="snoozeMenuId = null">
       <template v-if="snoozeMenuId === item.id">
        <NcActionButton :ref="`snoozeBack-${item.id}`" key="snooze-back" class="cobudget-action-menu-option" :close-after-click="false" @click="showSnoozeMenu(item, false)"><template #icon><ArrowLeftIcon :size="20" /></template>{{ r.snooze() }}</NcActionButton>
        <NcActionButton v-for="days in [1, 7]" :key="days" class="cobudget-action-menu-option" close-after-click :disabled="postponeTime(days) <= item.remind_at" :description="formatDate(postponeTime(days))" @click="postpone(item, days)"><template #icon><CalendarArrowRight :size="20" /></template>{{ days === 1 ? r.tomorrow() : r.inOneWeek() }}</NcActionButton>
        <NcActionButton key="snooze-date" class="cobudget-action-menu-option" close-after-click @click="chooseReminderDate(item)"><template #icon><CalendarEditOutline :size="20" /></template>{{ r.chooseDate() }}</NcActionButton>
       </template>
       <template v-else>
       <NcActionButton class="cobudget-action-menu-option" :close-after-click="true" @click="rowAction('edit', item)"><template #icon><PencilIcon :size="20" /></template>{{ r.editReminder() }}</NcActionButton>
       <NcActionButton v-if="item.status === 'active'" :ref="`snoozeTrigger-${item.id}`" key="snooze-trigger" class="cobudget-action-menu-option" is-menu :close-after-click="false" @click="showSnoozeMenu(item, true)"><template #icon><BellSleepOutline :size="20" /></template>{{ r.snooze() }}</NcActionButton>
       <NcActionButton class="cobudget-action-menu-option" :close-after-click="true" @click="rowAction('delete', item)"><template #icon><DeleteOutlineIcon :size="20" /></template>{{ r.delete() }}</NcActionButton>
       </template>
      </NcActions>
     </td>
    </tr></template></tbody>
   </table>
  </div>
  <div v-if="filtered.length > size" class="reminder-pagination"><NcButton :disabled="page === 1" @click="page--">{{ r.previous() }}</NcButton><span>{{ page }} / {{ Math.ceil(filtered.length / size) }}</span><NcButton :disabled="page * size >= filtered.length" @click="page++">{{ r.next() }}</NcButton></div>
  <ReminderCalendarConflict :row="calendarConflictRow" @close="calendarConflictRow = null" @resolved="calendarResolved" />
 </div>
</template>
<script>
import { showToast } from '../services/notifications'
import ReminderCalendarConflict from '../components/ReminderCalendarConflict.vue'
import CalendarAlertOutline from 'vue-material-design-icons/CalendarAlertOutline.vue'
import BellSleepOutline from 'vue-material-design-icons/BellSleepOutline.vue'
import CalendarArrowRight from 'vue-material-design-icons/CalendarArrowRight.vue'
import CalendarEditOutline from 'vue-material-design-icons/CalendarEditOutline.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import ArchiveOutline from 'vue-material-design-icons/ArchiveOutline.vue'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import ReminderFilterBar from '../components/ReminderFilterBar.vue'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NavigationIcon from '../components/NavigationIcon.vue'
import PaymentLabelList from '../components/PaymentLabelList.vue'
import paymentLabelBadges from '../services/reminderLabels'
import NcChip from '@nextcloud/vue/components/NcChip'
import Plus from 'vue-material-design-icons/Plus.vue'
import BellOutline from 'vue-material-design-icons/BellOutline.vue'
import TableTooltip from '../components/TableTooltip.vue'
import { formatCalendarLabel } from '../utils/formatDate'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
const config = { skipWorkspaceHeader: true }
export default {
 props: { selectedReminderId: { type: [Number, String], default: null } },
 components: { ReminderCalendarConflict, BellSleepOutline, CalendarArrowRight, CalendarEditOutline, ArrowLeftIcon, CalendarAlertOutline, ClockOutline, CheckCircleOutline, ArchiveOutline, NavigationIcon, PaymentLabelList, NcActions, NcActionButton, PencilIcon, DeleteOutlineIcon, TableTooltip, AppPageHeader, NcButton, ReportPeriodPicker, ReminderFilterBar, NcNoteCard, NcEmptyContent, NcLoadingIcon, NcCheckboxRadioSwitch, NcChip, Plus, BellOutline },
 data: () => ({ calendarConflictRow: null, calendarRefreshing: false, snoozeMenuId: null, calendarRetryId: null, actionsResetKey: 0, autoOpenHandled: false, rows: [], loading: false, error: '', status: ['active', 'completed'], search: '', label: [], reference: ['independent', 'payment', 'area'], from: '', to: '', page: 1, size: 25, busyId: null, now: Date.now(), timer: null, request: 0, alive: true }),
 computed: {
  r() { return this.$texts.reminders },
  statuses() { return ['active', 'completed', 'archived'].map(id => ({ id, label: id === 'active' ? this.r.openStatus() : this.r[id]() })) },
  references() { return ['independent', 'payment', 'area'].map(id => ({ id, label: this.r[({ independent: 'withoutPayment', payment: 'paymentWithoutArea', area: 'paymentFromArea' })[id]]() })) },
  labelOptions() { return ['cancel', 'renew', 'review', 'pay'].map(id => ({ id, label: this.labelText(id) })) },
  filterProps() { return { filters: { status: this.status, search: this.search, label: this.label, reference: this.reference, from: this.from, to: this.to }, statuses: this.statuses, labels: this.labelOptions, references: this.references } },
  hasActiveFilters() { return (this.status.length !== 2 || !this.status.includes('active') || !this.status.includes('completed')) || Boolean(this.search || this.label.length || this.from || this.to) || this.reference.length !== 3 },
  currentRows() {
   return this.rows.map(row => {
    const automaticArchiveAt = Number(row.completed_at || row.remind_at) + 14 * 86400
    const archivedAt = Number(row.archived_at || row.effective_archived_at || (!row.keep_open && automaticArchiveAt * 1000 <= this.now ? automaticArchiveAt : 0))
    return { ...row, daysAgo: this.calendarDaysAgo(row.remind_at), effective_archived_at: archivedAt || null, status: archivedAt ? 'archived' : row.completed_at ? 'completed' : 'active' }
   })
  },
  filtered() {
   const query = this.search.toLocaleLowerCase(); const from = this.from ? new Date(this.from + 'T00:00:00').getTime() : -Infinity; const to = this.to ? new Date(this.to + 'T23:59:59').getTime() : Infinity
   return this.currentRows.filter(row => this.status.includes(row.status) && (!query || `${row.title} ${row.description || ''} ${row.payment_title || ''}`.toLocaleLowerCase().includes(query)) && (!this.label.length || this.label.some(label => row.labels.includes(label))) && this.reference.includes(!row.entry_id ? 'independent' : row.project_id ? 'area' : 'payment') && row.remind_at * 1000 >= from && row.remind_at * 1000 <= to)
  },
  sorted() {
   const rank = { active: 0, completed: 1, archived: 2 }
   return [...this.filtered].sort((a, b) => rank[a.status] - rank[b.status] || a.remind_at - b.remind_at || a.id - b.id)
  },
  paged() { return this.sorted.slice((this.page - 1) * this.size, this.page * this.size) },
  showMonthHeadings() {
   return new Set(this.paged.map(item => {
    const date = new Date(item.remind_at * 1000)
    return `${date.getFullYear()}-${date.getMonth()}`
   })).size > 1
  },
  monthGroups() {
   const groups = []
   for (const item of this.paged) {
    const date = new Date(item.remind_at * 1000)
    const key = `${item.status}-${date.getFullYear()}-${date.getMonth()}`
    if (groups.at(-1)?.key !== key) groups.push({ key, label: formatCalendarLabel(date, { month: 'long', year: 'numeric' }), items: [] })
    groups.at(-1).items.push(item)
   }
   return groups
  },
 },
 watch: { '$route.query.reminder'() { this.openedDeepLink = false; this.load() }, '$route.query.entry'() { this.openedDeepLink = false; this.load() } },
 mounted() { this.refreshCalendar(); window.addEventListener('cobudget-reminders-changed', this.load); window.addEventListener('cobudget-remote-data-changed', this.load); window.addEventListener('focus', this.refreshCalendar); this.timer = setInterval(() => { this.now = Date.now() }, 60000) },
 beforeUnmount() { this.alive = false; clearInterval(this.timer); window.removeEventListener('cobudget-reminders-changed', this.load); window.removeEventListener('cobudget-remote-data-changed', this.load); window.removeEventListener('focus', this.refreshCalendar) },
 methods: {
  dayKey(row) {
   const date = new Date(row.remind_at * 1000)
   return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  },
  startsDayGroup(items, index) { return index === 0 || this.dayKey(items[index]) !== this.dayKey(items[index - 1]) },
  dayHeading(row) {
   const date = new Date(row.remind_at * 1000)
   return formatCalendarLabel(date, { weekday: 'long', day: '2-digit', month: 'short', ...(date.getFullYear() !== new Date(this.now).getFullYear() ? { year: 'numeric' } : {}) })
  },
  postponeTime(days) {
   const date = new Date(this.now)
   date.setDate(date.getDate() + days)
   date.setHours(9, 0, 0, 0)
   return Math.floor(date.getTime() / 1000)
  },
  showSnoozeMenu(row, open) {
   this.now = Date.now()
   this.snoozeMenuId = open ? row.id : null
   this.$nextTick(() => {
    const ref = this.$refs[`${open ? 'snoozeBack' : 'snoozeTrigger'}-${row.id}`]
    ;(Array.isArray(ref) ? ref[0] : ref)?.$el?.querySelector('button')?.focus()
   })
  },
  postpone(row, days) {
   this.now = Date.now()
   const remindAt = this.postponeTime(days)
   if (this.busyId !== null || row.status !== 'active' || remindAt <= row.remind_at) return
   this.actionsResetKey++; this.snoozeMenuId = null; this.autoOpenHandled = true
   window.dispatchEvent(new CustomEvent('cobudget-postpone-reminder', { detail: { row, remindAt } }))
  },
  chooseReminderDate(row) {
   if (this.busyId !== null) return
   this.actionsResetKey++; this.snoozeMenuId = null; this.autoOpenHandled = true
   window.dispatchEvent(new CustomEvent('cobudget-reminder-choose-date', { detail: row }))
  },
  async refreshCalendar() {
   if (this.calendarRefreshing) return
   this.calendarRefreshing = true
   try { await axios.post(generateUrl('/apps/cobudget/api/reminders/calendar-refresh'), {}, config) }
   catch (_) { /* Overview remains available when synchronization is unavailable. */ }
   finally { this.calendarRefreshing = false; if (this.alive) await this.load() }
  },
  calendarResolved(rows) { this.rows = rows; this.calendarConflictRow = null; window.dispatchEvent(new CustomEvent('cobudget-reminders-changed')) },
  async retryCalendar(row, event) {
   if (row.calendar_conflict) { this.calendarConflictRow = row; return }
   if (this.calendarRetryId !== null || !row.calendar_sync_pending) return
   const focusedRow = event?.currentTarget === document.activeElement ? event?.currentTarget?.closest('tr') : null
   this.calendarRetryId = row.id
   const request = ++this.request
   this.loading = false
   try {
    const response = await axios.post(generateUrl(`/apps/cobudget/api/reminders/${row.id}/calendar-sync`), {}, config)
    if (!this.alive) return
    if (request === this.request) {
     this.rows = response.data.reminders
     this.page = Math.max(1, Math.min(this.page, Math.ceil(this.filtered.length / this.size)))
    }
    const updated = response.data.reminders.find(item => item.id === row.id)
    if (updated?.calendar_conflict) { this.calendarConflictRow = updated; return }
    const pending = updated?.calendar_sync_pending
    showToast(pending ? this.$texts.settings.calendarSyncPending() : this.r.calendarSynced(), pending ? 'info' : 'success')
    if (!pending && focusedRow && request === this.request) {
     await this.$nextTick()
     if (this.alive && focusedRow.isConnected && document.activeElement === document.body) focusedRow.focus({ preventScroll: true })
    }
   } catch (error) {
    if (this.alive) showToast(this.r.calendarRetryError(), 'error')
   } finally { this.calendarRetryId = null }
  },
  calendarDaysAgo(timestamp) {
   // Compare local calendar dates, not elapsed 24-hour periods (DST and midnight).
   const day = date => Date.UTC(date.getFullYear(), date.getMonth(), date.getDate())
   return (day(new Date(this.now)) - day(new Date(timestamp * 1000))) / 86400000
  },
  relativeDateLabel(row) {
   if (row.status !== 'active' || row.daysAgo < 0) return ''
   return row.daysAgo === 0 ? this.r.today() : this.r.daysAgo(row.daysAgo)
  },
  isReminderSelected(row) { return this.selectedReminderId != null && String(row.id) === String(this.selectedReminderId) },
  paymentBadges(ids) { return paymentLabelBadges(ids, this.$texts) },
  rowAction(action, row) {
   if (this.busyId !== null) return
   this.actionsResetKey++
   if (action === 'delete') {
    this.autoOpenHandled = true
    window.dispatchEvent(new CustomEvent('cobudget-delete-reminder', { detail: row }))
   } else if (action === 'edit') this.open(row)
  },
  statusTooltip(row) { return [[this.r.completed(), row.completed_at], [this.r.archived(), row.effective_archived_at]].filter(([, at]) => at).map(([label, at]) => `${label}: ${this.formatDate(at)}`).join('\n') },
  openInitialReminder() {
   if (this.autoOpenHandled) return
   this.autoOpenHandled = true
   if (!this.$route.query.reminder && !this.$route.query.entry && window.matchMedia?.('(min-width: 1025px)').matches) this.open()
  },
  resetFilters() { this.updateFilters({ status: ['active', 'completed'], search: '', label: [], reference: ['independent', 'payment', 'area'], from: '', to: '' }) },
  updateFilters(filters) { for (const key of ['status', 'search', 'label', 'reference', 'from', 'to']) this[key] = filters[key]; this.page = 1 },
  labelText(id) { return this.r[id]?.() || this.paymentLabel(id) },
  paymentLabel(id) { return this.$texts.labels[({fixedCost: 'fixedCosts', childRelated: 'children'})[id] || id]?.() || id },
  formatDate(time) { return formatCalendarLabel(new Date(time * 1000), { day: 'numeric', month: 'numeric', year: 'numeric' }) },
  open(row = null) { this.autoOpenHandled = true; window.dispatchEvent(new CustomEvent('cobudget-open-reminder', { detail: row })) },
  async load() { this.now = Date.now(); const request = ++this.request; this.loading = true; this.error = ''; try { const response = await axios.get(generateUrl('/apps/cobudget/api/reminders'), config); if (!this.alive || request !== this.request) return; this.rows = response.data.reminders; this.page = Math.max(1, Math.min(this.page, Math.ceil(this.filtered.length / this.size))); const row = this.rows.find(r => this.$route.query.reminder ? r.id === Number(this.$route.query.reminder) : this.$route.query.entry && r.entry_id === Number(this.$route.query.entry)); if (row && !this.openedDeepLink) { this.openedDeepLink = true; this.open(row) } this.openInitialReminder() } catch (e) { if (this.alive && request === this.request) this.error = e.response?.data?.error || this.r.loadError() } finally { if (this.alive && request === this.request) this.loading = false } },
  async complete(row, completed) { if (this.busyId !== null) return; if (!completed && row.status !== 'archived' && row.remind_at * 1000 <= Date.now()) { this.open(row); return } this.busyId = row.id; try { await axios.put(generateUrl(`/apps/cobudget/api/reminders/${row.id}/status`), { status: completed ? 'completed' : 'incomplete', revision: row.revision }, config); await this.load() } catch (e) { this.error = e.response?.data?.error || this.r.saveError() } finally { this.busyId = null } },
 },
}
</script>
<style scoped>
.reminders-view { width: 100%; min-width: 0; container-type: inline-size; }
.reminders-page-header { container: reminders-toolbar / inline-size; }
@container reminders-toolbar (max-width: 720px) { .reminders-page-header :deep(.reminder-label-shortcut) { display: none; } }
@container reminders-toolbar (max-width: 560px) { .reminders-page-header :deep(.reminder-status-shortcut) { display: none; } }
@container reminders-toolbar (max-width: 400px) { .reminders-page-header :deep(.reminder-filter-caption) { display: none; } }
.reminder-table-container { width: calc(100% + var(--cobudget-content-inset-start, 12px) + var(--cobudget-content-inset-end, 12px)); margin-inline-start: calc(0px - var(--cobudget-content-inset-start, 12px)); background: var(--cobudget-surface); }
.reminder-table { width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed; }
.reminder-table th, .reminder-table td { box-sizing: border-box; padding: var(--cobudget-table-payment-padding); border-bottom: 1px solid var(--cobudget-table-separator); font-size: var(--cobudget-font-base); font-weight: 400; vertical-align: middle; overflow-wrap: anywhere; }
.reminder-table th { height: var(--cobudget-payment-table-header-height); text-align: start; color: var(--cobudget-text-muted); background: var(--cobudget-surface); }
.reminder-table td { color: var(--cobudget-text); }
.reminder-table tr > :first-child { padding-inline-start: var(--cobudget-content-inset-start, 12px); }
.reminder-table tr > :last-child { padding-inline-end: var(--cobudget-content-inset-end, 12px); }
.reminder-table tbody tr { height: var(--cobudget-payment-table-row-height); cursor: pointer; }
.reminder-table tbody td:not(.reminder-check),
.reminder-table tbody td:not(.reminder-check) :deep(*) { cursor: pointer; }
.reminder-table th.reminder-check, .reminder-table td.reminder-check { padding-inline: 0; text-align: center; }
.reminder-check :deep(.checkbox-radio-switch) { justify-content: center; }
.reminder-table .reminder-month-row th { padding-block: 20px 8px; height: auto; border-bottom: 0; color: var(--cobudget-text); text-align: start; }
.reminder-month-row h3 { margin: 0; font-size: var(--cobudget-font-base); font-weight: 600; }
.reminder-table tbody tr.reminder-month-row { height: auto; cursor: default; background: var(--cobudget-surface); }
.reminder-table tbody tr:hover { background: var(--color-background-hover); }
.reminder-table tbody tr.is-overdue { background: color-mix(in srgb, var(--cobudget-warning-light) 50%, var(--cobudget-surface)); }
.reminder-table tbody tr.is-overdue:hover { background: var(--cobudget-warning-light); }
.reminder-relative-date { display: block; font-size: var(--cobudget-font-caption); color: var(--cobudget-text-muted); }
.is-overdue .reminder-relative-date { color: var(--cobudget-warning-dark); }
.reminder-table tbody tr.is-selected,
.reminder-table tbody tr.is-selected:hover { background: var(--cobudget-table-row-selected) !important; }
@media (min-width: 769px) {
 .reminder-table tr.is-selected td:first-child { box-shadow: inset 3px 0 0 var(--color-primary-element); }
 .reminder-table tr.is-selected:dir(rtl) td:first-child { box-shadow: inset -3px 0 0 var(--color-primary-element); }
}
.reminder-table tbody tr:focus-visible { outline: 2px solid var(--color-primary-element); outline-offset: -2px; }
.reminder-check { width: calc(100px + var(--cobudget-content-inset-start, 12px)); }
.reminder-date { width: 170px; }
.reminder-date-content { display: flex; align-items: center; gap: 4px; }
.reminder-calendar-action { flex: 0 0 auto; color: var(--cobudget-warning-dark); }
.reminder-calendar-action :deep(button) { color: inherit; }
.reminder-status { width: 150px; }
.reminder-actions { width: calc(var(--default-clickable-area, 34px) + var(--cobudget-content-inset-end, 12px)); text-align: center; }
.reminder-table td.reminder-actions { padding-inline-start: 0; }
.reminder-status-badges { display: inline-flex; flex-wrap: wrap; align-items: center; gap: var(--default-grid-baseline, 4px); }
.reminder-title { font-weight: 400; }
.reminder-description { margin: 0; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; color: var(--color-text-maxcontrast); white-space: pre-wrap; }
.reminder-tags { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
/* Match Files system tags: transparent, outlined 32 px pills. */
.reminder-table-tag.nc-chip {
 --chip-size: 32px;
 --chip-radius: var(--border-radius-pill, 100px);
 box-sizing: border-box;
 width: fit-content;
 max-width: 100%;
 padding: 5px 10px;
 border: 1px solid var(--color-border);
 background: transparent;
 color: var(--color-text-maxcontrast);
 font-size: var(--cobudget-font-base);
 font-weight: 400;
 line-height: 20px;
}
.reminder-table-tag :deep(.nc-chip__icon) { flex: 0 0 16px; width: 16px; height: 20px; margin-inline-end: 6px; }
.reminder-table-tag :deep(.nc-chip__text) { padding: 0; }
.reminder-status-tag.nc-chip { border-color: currentColor; }
.reminder-status-tag[data-cobudget-badge="info"] { background: var(--cobudget-info-background); color: var(--cobudget-info-text); color: light-dark(var(--cobudget-primary), var(--cobudget-info-text)); }
.reminder-status-tag[data-cobudget-badge="success"] { background: var(--cobudget-success-light); color: var(--cobudget-success); }
.reminder-status-tag[data-cobudget-badge="neutral"] { background: var(--cobudget-surface-muted); color: var(--cobudget-text-muted); }
.reminder-secondary { color: var(--color-text-maxcontrast); }
.reminder-table time, .reminder-due, .reminder-table td > .reminder-secondary { display: block; }
.reminder-due { color: var(--color-warning-text); }
.reminder-pagination { display: flex; justify-content: space-between; align-items: center; margin-block: 16px; }
.reminder-day-row { display: none; }
@container (max-width: 700px) {
 .reminder-table, .reminder-table tbody { display: block; width: 100%; }
 .reminder-table thead { display: none; }
 .reminder-table tbody tr.reminder-month-row { display: none; }
 .reminder-table tbody tr.reminder-day-row { display: block; width: 100%; height: auto; cursor: default; background: var(--cobudget-surface); }
 .reminder-day-row th { display: block; width: 100%; height: auto; padding: 12px 12px 6px; border: 0; color: var(--cobudget-text-muted); font-size: var(--cobudget-font-compact); font-weight: var(--cobudget-font-weight-action, 700); letter-spacing: 0.02em; line-height: 1.3; white-space: normal; }
 .reminder-day-row time { display: inline; }
 .reminder-day-relative { font-weight: 400; }
 .reminder-table tbody tr[tabindex] {
  box-sizing: border-box;
  width: 100%;
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr) auto;
  align-items: center;
  gap: 6px 8px;
  height: auto;
  min-height: 64px;
  padding: 12px 12px 12px 4px;
  border-bottom: 1px solid var(--cobudget-table-separator);
 }
 .reminder-table tbody tr[tabindex]:not(.is-overdue):not(.is-selected):not(:hover) { background: color-mix(in srgb, var(--cobudget-surface-muted) 65%, var(--cobudget-surface)); }
 .reminder-table td { display: block; min-width: 0; width: auto; padding: 0; border: 0; }
 .reminder-table td.reminder-summary { grid-column: 2; grid-row: 1; }
 .reminder-title { white-space: normal; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; line-height: 1.4; }
 .reminder-description { display: block; margin-top: 2px; white-space: nowrap; text-overflow: ellipsis; line-height: 1.4; }
 .reminder-table td.reminder-status { grid-column: 3; grid-row: 1; justify-self: end; max-width: 110px; text-align: end; }
 .reminder-status-badges { justify-content: flex-end; }
 .reminder-status-tag.nc-chip { --chip-size: 28px; padding: 3px 8px; font-size: var(--cobudget-font-caption); }
 .reminder-table td.reminder-labels { grid-column: 2; grid-row: 2; }
 .reminder-table td.reminder-labels:not(.has-labels),
 .reminder-table td.reminder-actions,
 .reminder-table td.reminder-date:not(.has-calendar-warning),
 .reminder-date-text { display: none; }
 .reminder-table td.reminder-date.has-calendar-warning { grid-column: 3; grid-row: 2; justify-self: end; }
 .reminder-table td.reminder-check { grid-column: 1; grid-row: 1; width: 40px; padding: 0; align-self: start; }
 .reminder-table tr.is-selected td:first-child,
 .reminder-table tr.is-selected:dir(rtl) td:first-child { box-shadow: none; }
}

@media (max-width: 768px) {
 .reminders-view { padding-block-end: calc(var(--cobudget-content-gap) * 7); }
}
</style>
