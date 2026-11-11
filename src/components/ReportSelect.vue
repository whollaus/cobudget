<template>
	<NcActions class="report-select cobudget-report-menu cobudget-action-menu" :class="{ 'report-select--with-icon': selectedOption?.icon }" variant="tertiary" force-menu :placement="placement" :boundaries-element="boundariesElement"
		:container="container" :menu-name="selectedOption?.label || label" :aria-label="label" :title="label" :disabled="disabled">
		<template #icon>
			<CoBudgetIcon v-if="selectedOption?.icon === 'all-payments'" class="report-select-area-icon" />
			<AreaIcon v-else-if="selectedOption?.icon" class="report-select-area-icon" :without-area="selectedOption.icon === 'without-area'" :color="selectedOption.color" />
			<ChevronDownIcon class="report-select-chevron" :size="18" />
		</template>
		<NcActionButton v-for="(option, index) in options" :key="index" class="cobudget-report-menu-option cobudget-action-menu-option"
			type="radio" :model-value="option.value === modelValue" :disabled="disabled" close-after-click
			@click="select(option)">
			<template v-if="option.icon" #icon><CoBudgetIcon v-if="option.icon === 'all-payments'" /><AreaIcon v-else :without-area="option.icon === 'without-area'" :color="option.color" /></template>
			<template #default>{{ option.label }}</template>
		</NcActionButton>
	</NcActions>
</template>

<script>
import AreaIcon from './AreaIcon.vue'
import CoBudgetIcon from './CoBudgetIcon.vue'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'

export default {
	name: 'ReportSelect',
	components: { CoBudgetIcon, AreaIcon, NcActions, NcActionButton, ChevronDownIcon },
	props: {
		modelValue: { type: [String, Number, Boolean], default: null },
		options: { type: Array, required: true },
		label: { type: String, required: true },
		disabled: { type: Boolean, default: false },
		container: { type: [String, Boolean], default: 'body' },
		placement: { type: String, default: 'bottom-start' },
		boundariesElement: { type: Object, default: undefined },
	},
	emits: ['update:modelValue'],
	computed: {
		selectedOption() { return this.options.find(option => option.value === this.modelValue) || null },
	},
	methods: {
		select(option) {
			if (this.disabled || !option || !this.options.some(item => item.value === option.value) || option.value === this.modelValue) return
			this.$emit('update:modelValue', option.value)
		},
	},
}
</script>

<style scoped>
/* Keep the native trigger while placing its optional area icon before the text. */
.report-select--with-icon :deep(.action-item__menutoggle .button-vue__wrapper) { flex-direction: row; gap: 8px; }
.report-select--with-icon :deep(.action-item__menutoggle .button-vue__icon) { display: contents; }
.report-select--with-icon :deep(.action-item__menutoggle .button-vue__text) { order: 1; min-width: 0; }
.report-select-area-icon { order: 0; }
.report-select-chevron { order: 2; flex-shrink: 0; }
</style>
