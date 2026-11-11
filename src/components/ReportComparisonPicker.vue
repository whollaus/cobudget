<template>
	<NcActions v-if="options.length" v-model:open="shown" class="report-comparison-trigger cobudget-report-menu cobudget-action-menu"
		variant="tertiary" force-menu placement="bottom-end"
		:menu-name="`${$texts.annual.compare()}${selectedCount ? ` (${selectedCount})` : ''}`">
		<template #icon><ChevronDownIcon :size="18" /></template>
		<template v-for="group in optionGroups" :key="group.id">
			<NcActionCaption :name="group.label" />
			<NcActionButton v-for="value in group.options" :key="value.id" class="cobudget-report-menu-option cobudget-action-menu-option"
				type="checkbox" :model-value="modelValue.includes(value.id)" :close-after-click="false"
				@update:model-value="toggle(value.id, $event)">
				{{ label(value) }}
			</NcActionButton>
		</template>
	</NcActions>
</template>

<script>
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCaption from '@nextcloud/vue/components/NcActionCaption'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import { reportComparisonLabel } from '../services/reportComparisons'
import { formatCalendarLabel } from '../utils/formatDate'

export default {
	name: 'ReportComparisonPicker',
	components: { NcActions, NcActionButton, NcActionCaption, ChevronDownIcon },
	props: { comparisons: { type: Array, required: true }, modelValue: { type: Array, default: () => [] } },
	emits: ['update:modelValue'],
	data: () => ({ shown: false }),
	computed: {
		options() { return this.comparisons.filter(value => value.available && ['period', 'selection-average', 'reference-average'].includes(value.kind)) },
		optionGroups() {
			return [
				{ id: 'year', label: this.$texts.annual.yearAverages(), options: this.options.filter(value => value.referenceUnit === 'year') },
				{ id: 'selection', label: this.$texts.annual.selectedPeriod(), options: this.options.filter(value => value.kind === 'selection-average' && !value.referenceUnit) },
				{ id: 'earlier', label: this.$texts.annual.previousPeriodAverages(), options: this.options.filter(value => value.kind === 'reference-average') },
				{ id: 'periods', label: this.$texts.annual.comparison(), options: this.options.filter(value => value.kind === 'period') },
			].filter(group => group.options.length)
		},
		selectedCount() { return this.options.filter(value => this.modelValue.includes(value.id)).length },
	},
	methods: {
		label(value) { return reportComparisonLabel(value, this.$texts, formatCalendarLabel) },
		toggle(id, selected) { this.$emit('update:modelValue', selected ? [...new Set([...this.modelValue, id])] : this.modelValue.filter(value => value !== id)) },
	},
}
</script>
