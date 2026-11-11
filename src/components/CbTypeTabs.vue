<template>
	<div class="cobudget-type-tabs" role="group" :aria-label="label">
		<button
			v-for="type in ['expense', 'income']"
			:key="type"
			type="button"
			class="cobudget-type-tabs__button cobudget-focusable"
			:aria-pressed="modelValue === type"
			@click="$emit('update:modelValue', type)">
			{{ type === 'expense' ? $texts.common.expense() : $texts.common.income() }}
		</button>
	</div>
</template>

<script>
export default {
	name: 'CbTypeTabs',
	props: {
		modelValue: { type: String, required: true },
		label: { type: String, required: true }
	},
	emits: ['update:modelValue']
}
</script>

<style scoped>
.cobudget-type-tabs {
	display: flex;
	gap: 4px;
	box-sizing: border-box;
	width: 100%;
	margin: 18px 0;
	padding: 4px;
	border: 1px solid var(--cobudget-border);
	border-radius: var(--cobudget-control-radius);
	background: var(--cobudget-surface-muted);
}

.cobudget-type-tabs__button {
	flex: 1;
	min-width: 0;
	min-height: var(--cobudget-menu-item-height);
	margin: 0;
	padding: 8px 12px;
	border: 0;
	border-radius: calc(var(--cobudget-control-radius) - 3px);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font: inherit;
	font-size: var(--cobudget-font-base);
	font-weight: 600;
	line-height: 1.3;
	text-align: center;
	cursor: pointer;
}

.cobudget-type-tabs__button:hover {
	background: var(--color-primary-element-light-hover);
	color: var(--cobudget-text);
}

.cobudget-type-tabs__button[aria-pressed="true"] {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}
</style>
