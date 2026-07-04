<template>
	<button
		:type="type"
		class="cobudget-button"
		:class="[
			`cobudget-button--${variant}`,
			`cobudget-button--${size}`,
			{
				'is-block': block,
				'is-icon-only': iconOnly
			}
		]"
		:disabled="disabled || busy"
		:aria-label="ariaLabel || null"
		:title="title || null"
		@click="$emit('click', $event)">
		<span v-if="$slots.icon" class="cobudget-button__icon">
			<slot name="icon" />
		</span>
		<span v-if="!iconOnly || $slots.default" class="cobudget-button__label">
			<slot />
		</span>
	</button>
</template>

<script>
export default {
	name: 'CbButton',
	props: {
		type: {
			type: String,
			default: 'button'
		},
		variant: {
			type: String,
			default: 'secondary',
			validator: value => ['primary', 'secondary', 'soft', 'danger', 'danger-outline', 'ghost'].includes(value)
		},
		size: {
			type: String,
			default: 'normal',
			validator: value => ['compact', 'normal', 'large'].includes(value)
		},
		disabled: {
			type: Boolean,
			default: false
		},
		busy: {
			type: Boolean,
			default: false
		},
		block: {
			type: Boolean,
			default: false
		},
		iconOnly: {
			type: Boolean,
			default: false
		},
		ariaLabel: {
			type: String,
			default: ''
		},
		title: {
			type: String,
			default: ''
		}
	},
	emits: ['click']
}
</script>

<style scoped>
.cobudget-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	flex: 0 0 auto;
	min-width: 120px;
	max-width: 100%;
	min-height: var(--cobudget-button-height);
	padding: 0 24px;
	border: 1px solid transparent;
	border-radius: var(--cobudget-radius-md);
	background: var(--cobudget-surface);
	color: var(--cobudget-text);
	cursor: pointer;
	font-size: var(--cobudget-font-base);
	font-weight: var(--cobudget-font-weight-action);
	line-height: 1.2;
	letter-spacing: 0;
	white-space: nowrap;
	transition: background-color 0.2s, border-color 0.2s, box-shadow 0.2s, color 0.2s, opacity 0.2s;
	transform: none !important;
}

.cobudget-button:hover:not(:disabled) {
  background: var(--cobudget-surface-muted);
  border: 1px solid transparent;
	transform: none !important;
}

.cobudget-button:focus-visible {
	outline: 0;
	box-shadow: var(--cobudget-focus-ring);
}

.cobudget-button:disabled {
	cursor: not-allowed;
	opacity: 0.55;
	color: var(--cobudget-text-muted);
	transform: none !important;
}

.cobudget-button.is-block {
	width: 100%;
}

.cobudget-button.is-icon-only {
	min-width: var(--cobudget-icon-button-size);
	width: var(--cobudget-icon-button-size);
	min-height: var(--cobudget-icon-button-size);
	padding: 0;
}

.cobudget-button--compact {
	min-width: 0;
	min-height: 36px;
	padding: 0 14px;
	font-size: var(--cobudget-font-compact);
}

.cobudget-button--large {
	min-height: var(--cobudget-button-height-large);
	padding: 0 28px;
	font-size: var(--cobudget-font-md);
}

.cobudget-button--primary {
	background: var(--cobudget-primary);
	background-color: var(--cobudget-primary);
	border-color: var(--cobudget-primary);
	box-shadow: var(--cobudget-shadow-sm);
	color: var(--cobudget-primary-text) !important;
}

.cobudget-button--primary:hover:not(:disabled) {
	background: var(--cobudget-primary-hover);
	background-color: var(--cobudget-primary-hover);
	border-color: var(--cobudget-primary-hover);
	color: var(--cobudget-primary-text) !important;
}

.cobudget-button--secondary {
	background: var(--cobudget-surface-muted);
	border-color: transparent;
	color: var(--cobudget-text);
}

.cobudget-button--secondary:hover:not(:disabled) {
	background: var(--cobudget-surface-strong);
	border-color: transparent;
	color: var(--cobudget-text);
}

.cobudget-button--soft {
  background-color: var(--cobudget-primary-light);
	color: var(--cobudget-primary);
  border:none;
}

.cobudget-button--soft:hover:not(:disabled) {
  background-color: var(--color-primary-element-light-hover);
	color: var(--cobudget-primary);
  border:none;
}

.cobudget-button--danger {
	background: var(--cobudget-error);
	color: var(--cobudget-primary-text);
  border:none;
}

.cobudget-button--danger:hover:not(:disabled) {
	background: var(--cobudget-error-dark);
	color: var(--cobudget-primary-text);
  border:none;
}

.cobudget-button--danger-outline {
	border: 1px solid var(--cobudget-error);
	background: var(--cobudget-surface);
	color: var(--cobudget-error);
}

.cobudget-button--danger-outline:hover:not(:disabled) {
  border: 1px solid var(--cobudget-error)!important;
	background: var(--cobudget-error);
	color: var(--cobudget-primary-text);
}

.cobudget-button--ghost {
	min-width: 0;
	background: transparent;
	color: var(--cobudget-text);
}

.cobudget-button--ghost:hover:not(:disabled) {
	background: var(--cobudget-surface-muted);
}

.cobudget-button__icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
}

.cobudget-button__icon :deep(.material-design-icon),
.cobudget-button__icon :deep(.material-design-icon__svg) {
	display: block;
	color: currentColor !important;
	fill: currentColor !important;
}
</style>
