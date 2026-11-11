<template>
	<NcPopover popover-base-class="cobudget-menu-popover" placement="bottom-start" :close-on-click-outside="true" :disabled="disabled" @after-show="focusSelectedIcon">
		<template #trigger>
			<NcButton v-if="buttonTrigger" class="icon-picker-action" variant="secondary" type="button"
				:aria-label="disabled ? $texts.iconPicker.adminManaged() : $texts.iconPicker.selectIcon()"
				:title="disabled ? $texts.iconPicker.adminManaged() : $texts.iconPicker.selectIcon()" :disabled="disabled">
				<template #icon><component :is="selectedIcon" :size="20" /></template>
			</NcButton>
			<button v-else class="icon-picker-btn cobudget-focusable" :class="{ 'has-icon': value, 'is-disabled': disabled }" type="button" @click.prevent
				:aria-label="disabled ? $texts.iconPicker.adminManaged() : $texts.iconPicker.selectIcon()"
				:title="disabled ? $texts.iconPicker.adminManaged() : $texts.iconPicker.selectIcon()" :disabled="disabled">
				<component :is="selectedIcon" :size="20" class="current-icon" />
			</button>
		</template>

		<div ref="iconGrid" class="icon-picker-grid">
			<button v-for="icon in icons" :key="icon" class="icon-option-btn cobudget-focusable" :class="{ active: icon === value }"
				type="button" @click="selectIcon(icon)" :title="icon" :aria-label="icon" :aria-pressed="icon === value">
				<component :is="icon" :size="24" />
			</button>
		</div>
	</NcPopover>
</template>

<script>
import { categoryIcons, categoryIconNames } from './categoryIcons'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'

export default {
	name: 'IconPicker',
	components: { NcPopover, NcButton, ...categoryIcons },
	props: {
		buttonTrigger: Boolean,
		value: {
			type: String,
			default: 'Shape'
		},
		disabled: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			icons: categoryIconNames
		}
	},
	computed: {
		selectedIcon() { return categoryIconNames.includes(this.value) ? this.value : 'Shape' },
	},
	methods: {
		focusSelectedIcon() {
			const grid = this.$refs.iconGrid;
			const selected = grid?.querySelector('.icon-option-btn.active') || grid?.querySelector('.icon-option-btn');
			selected?.focus({ preventScroll: true });
		},
		selectIcon(icon) {
			this.$emit('input', icon);
		}
	}
}
</script>

<style scoped>
.icon-picker-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	vertical-align: middle;
	color: var(--color-main-text);
	border-radius: 999px;
	background-color: transparent;
	padding: 5px;
	cursor: pointer;
	border: none;
	width: 36px;
	height: 36px;
	transition: background-color var(--cobudget-hover-duration) linear;
	margin-right: 0 !important;
}

.icon-picker-btn:hover:not(.is-disabled) {
	background-color: var(--cobudget-interaction-hover);
}

.icon-picker-btn:focus:not(:hover) {
	background-color: transparent;
}

.icon-picker-btn.is-disabled,
.icon-picker-btn.is-disabled * {
	cursor: not-allowed !important;
}

.icon-picker-btn.is-disabled {
	opacity: 0.7;
}

.icon-picker-grid {
	display: grid;
	grid-template-columns: repeat(6, 1fr);
	gap: 8px;
	padding: var(--cobudget-menu-padding);
	background: var(--cobudget-page-background);
}

.icon-option-btn {
	background: transparent;
	border: 1px solid transparent;
	border-radius: var(--cobudget-menu-item-radius);
	padding: 6px;
	min-width: var(--cobudget-menu-item-height);
	min-height: var(--cobudget-menu-item-height);
	margin: 0;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--cobudget-text-muted);
	transition: background-color var(--cobudget-hover-duration) linear;
}

.icon-option-btn:hover {
	background: var(--cobudget-interaction-hover);
}

.icon-option-btn.active {
	background: var(--cobudget-interaction-selected);
	color: var(--color-main-text);
	border-color: var(--cobudget-primary);
}

.icon-option-btn.active:hover { background: var(--cobudget-interaction-selected-hover); }

/* Keep one inset focus ring instead of stacking the core border, halo and outline. */
.icon-picker-grid .icon-option-btn:focus-visible {
	outline: var(--cobudget-focus-outline) !important;
	outline-offset: -2px;
	box-shadow: none !important;
}
@media (max-width: 768px) {
	.icon-picker-btn,
	.icon-picker-action,
	.icon-option-btn {
		min-width: var(--cobudget-mobile-touch-size);
		min-height: var(--cobudget-mobile-touch-size);
	}
	.icon-picker-grid { gap: 4px; }
}
.icon-picker-btn:is(:focus-visible, :active, .is-disabled), .icon-option-btn:is(:focus-visible, :active, .active) { transition: none; }
</style>
