<template>
	<div class="period-switch no-print" role="tablist" :aria-label="ariaLabel">
		<button
			v-for="option in options"
			:key="option.key"
			type="button"
			:class="{ active: selected === option.key }"
			:aria-selected="selected === option.key"
			@click="$emit('select', option.key)">
			{{ option.label }}
		</button>
	</div>
</template>

<script>
export default {
	name: 'AnalyticsPeriodSwitch',
	props: {
		options: {
			type: Array,
			required: true
		},
		selected: {
			type: String,
			required: true
		},
		ariaLabel: {
			type: String,
			required: true
		}
	},
	emits: ['select']
}
</script>

<style scoped>
.period-switch {
	display: inline-flex;
	flex-wrap: wrap;
	gap: 2px;
	margin-bottom: 16px;
	padding: 4px;
	border-radius: 8px;
	background: var(--cobudget-surface-muted, #f5f5f5);
}

.period-switch button {
	min-height: 38px;
	padding: 0 14px;
	border: none;
	border-radius: 6px;
	background: transparent;
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-weight: 600;
	cursor: pointer;
}

.period-switch button:hover,
.period-switch button:focus-visible {
	background: var(--cobudget-surface, #fff);
	outline: 2px solid var(--color-primary-element, var(--color-primary, #0082c9));
	outline-offset: 1px;
}

.period-switch button.active {
	background: var(--cobudget-surface, #fff);
	color: var(--color-primary-element, var(--color-primary, #0082c9));
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}
</style>
