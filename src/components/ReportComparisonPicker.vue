<template>
	<NcPopover v-if="options.length" v-model:shown="shown" placement="bottom-end" popup-role="dialog" popover-base-class="report-comparison-popover">
		<template #trigger>
			<NcButton variant="tertiary" class="report-comparison-trigger" :class="{ 'is-open': shown }" :aria-label="$texts.annual.compare()">
				{{ $texts.annual.compare() }}<span v-if="selectedCount"> ({{ selectedCount }})</span><ChevronDownIcon :size="18" />
			</NcButton>
		</template>
		<div class="report-comparison-panel" role="dialog" :aria-label="$texts.annual.compare()">
			<div v-for="group in optionGroups" :key="group.id" class="report-comparison-group" role="group" :aria-label="group.label">
				<p class="report-comparison-group-title">{{ group.label }}</p>
				<NcCheckboxRadioSwitch v-for="value in group.options" :key="value.id" type="checkbox" :model-value="modelValue.includes(value.id)"
					@update:model-value="toggle(value.id, $event)">
					{{ label(value) }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
	</NcPopover>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import { reportComparisonLabel } from '../services/reportComparisons'
import { formatCalendarLabel } from '../utils/formatDate'

export default {
	name: 'ReportComparisonPicker',
	components: { NcButton, NcPopover, NcCheckboxRadioSwitch, ChevronDownIcon },
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

<style scoped>
.report-comparison-trigger.button-vue { min-width: 0; min-height: 36px; height: 36px; margin: 0; padding: 0 calc(var(--default-grid-baseline, 4px) * 2); border: 0; border-radius: var(--border-radius-element, 8px); background: transparent; color: var(--cobudget-text-muted); font: inherit; --font-weight-element: 400; }
.report-comparison-trigger :deep(.button-vue__text) { display: inline-flex; align-items: center; gap: var(--default-grid-baseline, 4px); margin: 0; padding: 0 !important; }
.report-comparison-trigger.button-vue:hover:not(:disabled),
.report-comparison-trigger.button-vue:active:not(:disabled),
.report-comparison-trigger.button-vue:focus-visible,
.report-comparison-trigger.button-vue.is-open { background: var(--cobudget-surface-muted) !important; }
.report-comparison-trigger.button-vue:focus-visible { outline: var(--cobudget-focus-outline) !important; outline-offset: 2px; box-shadow: none !important; }
.report-comparison-panel { box-sizing: border-box; width: min(300px, calc(100vw - 32px)); max-height: min(70vh, 560px); overflow-y: auto; padding: calc(var(--default-grid-baseline, 4px) * 2); color: var(--cobudget-text, var(--color-main-text)); }
.report-comparison-group + .report-comparison-group { margin-block-start: calc(var(--default-grid-baseline, 4px) * 2); padding-block-start: calc(var(--default-grid-baseline, 4px) * 2); border-top: 1px solid var(--color-border); }
.report-comparison-group-title { margin: 0; padding-inline: calc(var(--default-grid-baseline, 4px) * 2); color: var(--cobudget-text-muted); font-size: var(--cobudget-font-caption); }
@media (max-width: 768px) {
	.report-comparison-trigger.button-vue { min-height: 44px; height: 44px; padding-inline: var(--default-grid-baseline, 4px); }
	:global(.report-comparison-popover) { z-index: 10000 !important; }
}
</style>
