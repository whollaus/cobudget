<template>
	<div class="cobudget-filter-panel reminder-filter-panel" role="group" :aria-label="$texts.filters.filter()">
		<NcInputField v-if="!section" v-show="!submenu" class="cobudget-native-field" type="search" :model-value="filters.search" :label="$texts.reminders.search()" @update:model-value="update('search', $event)" />
		<PaymentFilterSelect v-for="choice in choices" v-show="!submenu || submenu === choice.key" :key="choice.key"
			fixed-label :show-back="!section" :label="choice.label" :trigger-icon="choice.icon" :options="choice.options"
			:model-value="filters[choice.key]" :active="choice.key === 'status' ? statusActive : choice.key === 'reference' ? filters.reference.length !== 3 : filters.label.length > 0" multiple native-switches :open="submenu === choice.key"
			@update:open="submenu = $event ? choice.key : null" @update:model-value="update(choice.key, $event)" />
		<PaymentFilterSelect v-if="!section || section === 'period'" v-show="!submenu || submenu === 'period'" fixed-label
			:show-back="!section" :label="$texts.analytics.period()" :trigger-icon="CalendarRange" :options="[]" :active="Boolean(filters.from || filters.to)"
			:open="submenu === 'period'" @update:open="submenu = $event ? 'period' : null">
			<template #options><div class="reminder-date-bounds">
				<label>{{ $texts.reminders.from() }}<input type="date" class="form-control" data-cobudget-field :value="filters.from" :max="filters.to || undefined" @change="update('from', $event.target.value)" /></label>
				<label>{{ $texts.reminders.to() }}<input type="date" class="form-control" data-cobudget-field :value="filters.to" :min="filters.from || undefined" @change="update('to', $event.target.value)" /></label>
			</div></template>
		</PaymentFilterSelect>
		<NcButton v-if="!section && !submenu" variant="tertiary" @click="$emit('reset')">{{ $texts.filters.resetAll() }}</NcButton>
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import PaymentFilterSelect from './PaymentFilterSelect.vue'
import CalendarRange from 'vue-material-design-icons/CalendarRange.vue'
import CheckAll from 'vue-material-design-icons/CheckAll.vue'
import TagOutline from 'vue-material-design-icons/TagOutline.vue'
import LinkVariant from 'vue-material-design-icons/LinkVariant.vue'
export default {
	components: { NcButton, NcInputField, PaymentFilterSelect },
	props: { filters: { type: Object, required: true }, statuses: Array, labels: Array, references: Array, section: { type: String, default: '' } },
	emits: ['update:filters', 'reset'],
	data() { return { submenu: this.section || null, CalendarRange } },
	computed: {
		statusActive() { return this.filters.status.length !== 2 || !this.filters.status.includes('active') || !this.filters.status.includes('completed') },
		choices() {
			const options = rows => rows.map(row => ({ value: row.id, label: row.label }))
			return [
				{ key: 'status', label: this.$texts.reminders.status(), icon: CheckAll, defaultValue: ['active', 'completed'], options: options(this.statuses) },
				{ key: 'label', label: this.$texts.reminders.labels(), icon: TagOutline, defaultValue: [], options: [{ value: null, label: this.$texts.filters.allLabels() }, ...options(this.labels)] },
				{ key: 'reference', label: this.$texts.reminders.reference(), icon: LinkVariant, defaultValue: ['independent', 'payment', 'area'], options: options(this.references) },
			].filter(choice => !this.section || choice.key === this.section)
		},
	},
	methods: { update(key, value) { this.$emit('update:filters', { ...this.filters, [key]: value }) } },
}
</script>
<style scoped>
.reminder-date-bounds { display: grid; gap: 8px; padding: 8px; }
.reminder-date-bounds input { display: block; width: 100%; min-width: 0; box-sizing: border-box; }
</style>
