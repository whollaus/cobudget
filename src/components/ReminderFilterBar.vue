<template>
	<div v-if="!chipsOnly" class="cobudget-filter-controls" role="group" :aria-label="$texts.filters.filter()">
		<AnchoredPopover v-for="shortcut in shortcuts" :key="shortcut.key" placement="bottom-end" :class="`reminder-${shortcut.key}-shortcut`"
			:set-return-focus="() => trigger(shortcut.key)" @after-show="focusPanel(shortcut.key)">
			<template #trigger><NcButton :ref="shortcut.key + 'Trigger'" class="cobudget-filter-control" variant="tertiary" :class="{ 'cobudget-filter-active': active(shortcut.key) }">
				<span class="cobudget-filter-trigger"><component :is="shortcut.icon" :size="20" aria-hidden="true" />{{ shortcut.label }}<ChevronDown :size="18" /></span>
			</NcButton></template>
			<ReminderFilters :ref="shortcut.key + 'Panel'" :section="shortcut.key" v-bind="panelProps" @update:filters="$emit('update:filters', $event)" />
		</AnchoredPopover>
		<AnchoredPopover placement="bottom-end" :set-return-focus="() => trigger('filters')" @after-show="focusPanel('filters')">
			<template #trigger><NcButton ref="filtersTrigger" class="cobudget-filter-control" variant="tertiary" :class="{ 'cobudget-filter-active': chips.length }" :aria-label="$texts.filters.filter()">
				<template #icon><FilterVariant :size="20" /></template><span class="reminder-filter-caption">{{ $texts.filters.filter() }}</span>
			</NcButton></template>
			<ReminderFilters ref="filtersPanel" v-bind="panelProps" @update:filters="$emit('update:filters', $event)" @reset="$emit('update:filters', defaults())" />
		</AnchoredPopover>
	</div>
	<div v-else-if="chips.length" class="cobudget-filter-chips">
		<NcChip v-for="chip in chips" :key="chip.key" class="cobudget-filter-chip" :text="chip.label" :title="chip.label" :icon-path="filterChipIcon(chip.icon)" :aria-label-close="$texts.annual.removeFilter()" @close="remove(chip.key)" />
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import AnchoredPopover from './AnchoredPopover.vue'
import ReminderFilters from './ReminderFilters.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'
import CheckAll from 'vue-material-design-icons/CheckAll.vue'
import TagOutline from 'vue-material-design-icons/TagOutline.vue'
import { filterChipIcon } from '../services/filterChipIcons'
import { formatCalendarLabel } from '../utils/formatDate'
export default {
	components: { NcButton, NcChip, AnchoredPopover, ReminderFilters, ChevronDown, FilterVariant },
	props: { filters: { type: Object, required: true }, statuses: Array, labels: Array, references: Array, chipsOnly: Boolean },
	emits: ['update:filters'],
	computed: {
		panelProps() { return { filters: this.filters, statuses: this.statuses, labels: this.labels, references: this.references } },
		shortcuts() { return [
			{ key: 'status', label: this.$texts.reminders.status(), icon: CheckAll },
			{ key: 'label', label: this.$texts.reminders.labels(), icon: TagOutline },
		] },
		chips() {
			const chips = []
			for (const [key, rows, icon] of [['status', this.statuses, 'status'], ['label', this.labels, 'labels'], ['reference', this.references, 'reportFilters']]) {
				if (this.active(key)) chips.push({ key, icon, label: ['status', 'reference', 'label'].includes(key) ? (rows.filter(row => this.filters[key].includes(row.id)).map(row => row.label).join(', ') || `${this.$texts.reminders[key]()} (0)`) : rows.find(row => row.id === this.filters[key])?.label || this.filters[key] })
			}
			if (this.filters.search) chips.push({ key: 'search', icon: 'search', label: this.filters.search })
			if (this.active('period')) chips.push({ key: 'period', icon: 'period', label: [['from', this.$texts.reminders.from()], ['to', this.$texts.reminders.to()]].filter(([key]) => this.filters[key]).map(([key, label]) => `${label}: ${formatCalendarLabel(new Date(this.filters[key] + 'T12:00:00'), { dateStyle: 'medium' })}`).join(' · ') })
			return chips
		},
	},
	methods: {
		filterChipIcon,
		defaults() { return { status: ['active', 'completed'], search: '', label: [], reference: ['independent', 'payment', 'area'], from: '', to: '' } },
		active(key) { if (key === 'label') return this.filters.label.length > 0; if (key === 'reference') return this.filters.reference.length !== 3; if (key === 'status') return this.filters.status.length !== 2 || !this.filters.status.includes('active') || !this.filters.status.includes('completed'); return key === 'period' ? Boolean(this.filters.from || this.filters.to) : this.filters[key] !== this.defaults()[key] },
		remove(key) { this.$emit('update:filters', { ...this.filters, ...(key === 'period' ? { from: '', to: '' } : { [key]: this.defaults()[key] }) }) },
		ref(key) { const ref = this.$refs[key]; return Array.isArray(ref) ? ref[0] : ref },
		trigger(key) { return this.ref(key + 'Trigger')?.$el || false },
		focusPanel(key) { this.$nextTick(() => { const controls = [...(this.ref(key + 'Panel')?.$el?.querySelectorAll('input:not(:disabled), button:not(:disabled)') || [])].filter(el => el.getClientRects().length); (controls.find(el => el.getAttribute('aria-pressed') === 'true') || controls[0])?.focus({ preventScroll: true }) }) },
	},
}
</script>
