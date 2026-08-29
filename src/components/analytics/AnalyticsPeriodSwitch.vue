<template>
	<div class="period-control no-print">
		<div class="period-switch" role="tablist" :aria-label="ariaLabel">
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

		<div class="period-select">
			<select
				class="period-select__control"
				:value="selected"
				:aria-label="ariaLabel"
				@change="onMobileSelect">
				<option
					v-for="option in options"
					:key="option.key"
					:value="option.key">
					{{ option.label }}
				</option>
			</select>
			<ChevronDownIcon
				class="period-select__icon"
				:size="22"
				aria-hidden="true" />
		</div>
	</div>
</template>

<script>
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'

export default {
	name: 'AnalyticsPeriodSwitch',
	components: {
		ChevronDownIcon
	},
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
	emits: ['select'],
	methods: {
		onMobileSelect(event) {
			this.$emit('select', event.target.value)
		}
	}
}
</script>

<style scoped>
.period-control {
	width: 100%;
}

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

.period-select {
	display: none;
	position: relative;
	width: 100%;
	margin-bottom: calc(var(--default-grid-baseline, 4px) * 4);
}

.period-select__control {
	width: 100%;
	min-height: var(--default-clickable-area, 44px);
	box-sizing: border-box;
	margin: 0;
	padding-block: 0;
	padding-inline: calc(var(--default-grid-baseline, 4px) * 3) calc(var(--default-clickable-area, 44px) + var(--default-grid-baseline, 4px));
	appearance: none;
	border: 1px solid var(--color-border-maxcontrast, #949494);
	border-radius: var(--border-radius-large, 10px);
	background: var(--color-main-background, #fff);
	color: var(--color-main-text, #222);
	font-size: 16px;
	font-weight: 600;
	cursor: pointer;
}

.period-select__control:focus-visible {
	border-color: var(--color-primary-element, var(--color-primary, #0082c9));
	outline: 2px solid var(--color-primary-element, var(--color-primary, #0082c9));
	outline-offset: 2px;
}

.period-select__icon {
	position: absolute;
	top: 50%;
	inset-inline-end: calc(var(--default-grid-baseline, 4px) * 3);
	transform: translateY(-50%);
	color: var(--color-text-maxcontrast, #6b6b6b);
	pointer-events: none;
}

@media (max-width: 768px) {
	.period-switch {
		display: none;
	}

	.period-select {
		display: block;
	}
}
</style>
