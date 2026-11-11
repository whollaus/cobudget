<template>
	<div class="form-group entry-type-field" :class="{ 'entry-type-field--header': header }">
		<label for="entry-type">{{ $texts.entry.paymentType() }}</label>
		<NcSelect uid="cobudget-payment-type" input-id="entry-type" class="cobudget-native-select entry-type-select"
			:class="`entry-type-select--${modelValue?.value || 'expense'}`"
			label-outside :input-label="$texts.entry.paymentType()" :model-value="modelValue"
			:options="options" label="label" :clearable="false" :searchable="false"
			:disabled="disabled" @update:model-value="$emit('update:modelValue', $event)">
			<template #selected-option="option">
				<span class="entry-type-value">
					<MinusIcon v-if="option.value === 'expense'" :size="20" aria-hidden="true" /><PlusIcon v-else :size="20" aria-hidden="true" />
					<span>{{ option.label }}</span>
				</span>
			</template>
			<template #option="option">
				<span class="entry-type-option" :class="`entry-type-option--${option.value}`">
					<MinusIcon v-if="option.value === 'expense'" :size="20" aria-hidden="true" /><PlusIcon v-else :size="20" aria-hidden="true" />
					<span>{{ option.label }}</span>
				</span>
			</template>
		</NcSelect>
	</div>
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'
import MinusIcon from 'vue-material-design-icons/Minus.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'EntryTypeField',
	components: { NcSelect, MinusIcon, PlusIcon },
	props: {
		modelValue: { type: Object, default: null },
		options: { type: Array, required: true },
		disabled: { type: Boolean, default: false },
		header: { type: Boolean, default: false },
	},
	emits: ['update:modelValue'],
}
</script>

<style scoped>
.entry-type-field { min-width: 0; margin-bottom: var(--entry-sidebar-field-gap); }
.entry-type-field--header { margin-bottom: 0; }
.entry-type-field > label {
	display: block;
	margin: 0 0 var(--default-grid-baseline, 4px);
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	line-height: 1.5;
	color: var(--color-main-text);
}
.entry-type-field--header > label {
	display: flex;
	align-items: center;
	min-height: var(--default-clickable-area);
	padding-inline-end: calc(var(--default-clickable-area) + var(--entry-sidebar-header-action-gap));
}
.entry-type-select { width: 100%; min-width: 0; }
.entry-type-value { min-width: 0; }
.entry-type-value > span:last-child {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.entry-type-select.v-select.select :deep(.vs__selected) {
	padding-inline: 0;
	color: var(--entry-type-color);
}
.entry-type-value,
.entry-type-option { display: flex; align-items: center; gap: var(--cobudget-space-2, 8px); }
.entry-type-value :deep(.material-design-icon),
.entry-type-option :deep(.material-design-icon) { flex-shrink: 0; }
.entry-type-select--expense {
	--entry-type-color: var(--cobudget-error-dark);
	--entry-type-surface: var(--cobudget-error-light);
}
.entry-type-select--income {
	--entry-type-color: var(--cobudget-success);
	--entry-type-surface: var(--cobudget-success-light);
}
.entry-type-select.v-select.select {
	--vs-selected-color: var(--entry-type-color);
	--vs-controls-color: var(--entry-type-color);
}
.entry-type-select :deep(.vs__dropdown-toggle) {
	background: var(--entry-type-surface);
}
/* NcSelect teleports its native listbox to body; scope these colors to this field. */
:global(#vs-cobudget-payment-type__listbox .vs__dropdown-option:has(.entry-type-option--expense)) {
	--entry-type-color: var(--cobudget-error-dark);
	--entry-type-surface: var(--cobudget-error-light);
}
:global(#vs-cobudget-payment-type__listbox .vs__dropdown-option:has(.entry-type-option--income)) {
	--entry-type-color: var(--cobudget-success);
	--entry-type-surface: var(--cobudget-success-light);
}
:global(#vs-cobudget-payment-type__listbox .vs__dropdown-option) {
	color: var(--entry-type-color);
	background: var(--entry-type-surface);
}
:global(#vs-cobudget-payment-type__listbox .vs__dropdown-option--highlight),
:global(#vs-cobudget-payment-type__listbox .vs__dropdown-option--kb-focus) {
	background: color-mix(in srgb, var(--entry-type-color) 12%, var(--entry-type-surface));
}
</style>
