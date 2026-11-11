<template>
	<NcPopover v-model:shown="shown" :set-return-focus="returnTrigger" popover-base-class="cobudget-menu-popover" placement="bottom-start" @after-show="focusChoice">
		<template #trigger>
			<NcButton ref="trigger" variant="tertiary" class="report-area-trigger" :aria-label="label" :aria-expanded="shown" :title="selectedNames || label">
				<template #icon><ChevronDownIcon :size="18" /></template>{{ selectionLabel }}
			</NcButton>
		</template>
		<div ref="choices" class="report-area-choices" role="group" :aria-label="label" @keydown.esc.stop.prevent="close">
			<NcCheckboxRadioSwitch v-for="option in options" :key="String(option.value)" class="report-area-choice" type="checkbox"
				:model-value="option.value === null ? modelValue.length === 0 : modelValue.includes(option.value)"
				@update:model-value="toggle(option.value)">
				<span class="report-area-choice-content">
					<AreaIcon v-if="option.icon" :without-area="option.icon === 'without-area'" :color="option.color" aria-hidden="true" />
					<span>{{ option.label }}</span>
				</span>
			</NcCheckboxRadioSwitch>
		</div>
	</NcPopover>
</template>

<script>
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import AreaIcon from './AreaIcon.vue'

export default {
	name: 'ReportAreaSelect',
	components: { NcPopover, NcButton, NcCheckboxRadioSwitch, ChevronDownIcon, AreaIcon },
	props: { modelValue: { type: Array, default: () => [] }, options: { type: Array, required: true }, label: { type: String, required: true } },
	emits: ['update:modelValue'],
	data: () => ({ shown: false }),
	computed: {
		selectedNames() { return this.options.filter(option => this.modelValue.includes(option.value)).map(option => option.label).join(', ') },
		selectionLabel() { return this.modelValue.length > 1 ? `${this.$texts.analytics.areas()} (${this.modelValue.length})` : this.selectedNames || this.options.find(option => option.value === null)?.label || this.label },
	},
	methods: {
		toggle(value) {
			if (!this.options.some(option => option.value === value)) return
			this.$emit('update:modelValue', value === null ? [] : this.modelValue.includes(value) ? this.modelValue.filter(id => id !== value) : [...this.modelValue, value])
		},
		focusChoice() { const choices = this.$refs.choices; (choices?.querySelector('input:checked') || choices?.querySelector('input'))?.focus({ preventScroll: true }) },
		returnTrigger() { return this.$refs.trigger?.$el || false },
		close() { this.shown = false; this.$nextTick(() => this.$refs.trigger?.$el?.focus({ preventScroll: true })) },
	},
}
</script>

<style scoped>
.report-area-trigger :deep(.button-vue__wrapper) { flex-direction: row-reverse; gap: var(--default-grid-baseline, 4px); }
.report-area-choices { box-sizing: border-box; max-height: min(440px, calc(100dvh - 100px)); max-width: calc(100vw - 24px); overflow-y: auto; padding: var(--cobudget-content-gap, 12px); }
.report-area-choice { width: 100%; min-width: 0; --color-primary-element-light-hover: var(--cobudget-interaction-hover); }
.report-area-choice :deep(.checkbox-radio-switch__label) { width: 100%; min-width: 0; }
.report-area-choice :deep(.checkbox-radio-switch__content) { max-width: none; box-sizing: border-box; }
.report-area-choice-content { display: flex; align-items: center; gap: 8px; min-width: 0; }
.report-area-choice-content > :first-child:not(:last-child) { flex-shrink: 0; }
.report-area-choice-content > span { overflow-wrap: anywhere; }
@media (max-width: 768px) { .report-area-trigger, .report-area-choices { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); } }
</style>
