<template>
	<div
		ref="root"
		class="cobudget-split-button"
		:class="{
			'is-block': block,
			'is-open': menuOpen,
			'is-disabled': disabled || busy
		}"
		@keydown.esc.stop.prevent="closeMenu">
		<button
			:type="type"
			class="cobudget-split-button__main"
			:class="`cobudget-split-button__main--${variant}`"
			:disabled="disabled || busy"
			@click="$emit('click', $event)">
			{{ busy ? busyLabel : label }}
		</button>
		<button
			type="button"
			class="cobudget-split-button__toggle"
			:class="`cobudget-split-button__toggle--${variant}`"
			:disabled="disabled || busy"
			:aria-expanded="menuOpen ? 'true' : 'false'"
			:aria-label="menuAriaLabel"
			@click.stop="toggleMenu">
			<ChevronDownIcon :size="20" aria-hidden="true" />
		</button>
		<div
			v-if="menuOpen"
			class="cobudget-split-button__menu"
			:class="`cobudget-split-button__menu--${direction}`"
			role="menu">
			<template v-for="(item, index) in items" :key="item.key || index">
				<div v-if="item.separator" class="cobudget-split-button__separator" role="separator"></div>
				<button
					v-else
					type="button"
					class="cobudget-split-button__item"
					:disabled="item.disabled"
					:title="item.title || ''"
					role="menuitem"
					@click="selectItem(item)">
					{{ item.label }}
				</button>
			</template>
		</div>
	</div>
</template>

<script>
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'

export default {
	name: 'CbSplitButton',
	components: {
		ChevronDownIcon
	},
	props: {
		label: {
			type: String,
			required: true
		},
		busyLabel: {
			type: String,
			default: ''
		},
		type: {
			type: String,
			default: 'button'
		},
		variant: {
			type: String,
			default: 'primary',
			validator: value => ['primary', 'danger'].includes(value)
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
		direction: {
			type: String,
			default: 'up',
			validator: value => ['up', 'down'].includes(value)
		},
		items: {
			type: Array,
			default: () => []
		},
		menuAriaLabel: {
			type: String,
			required: true
		}
	},
	emits: ['click', 'select'],
	data() {
		return {
			menuOpen: false
		}
	},
	mounted() {
		document.addEventListener('pointerdown', this.handleDocumentPointerDown, true)
	},
	beforeUnmount() {
		document.removeEventListener('pointerdown', this.handleDocumentPointerDown, true)
	},
	methods: {
		toggleMenu() {
			this.menuOpen = !this.menuOpen
		},
		closeMenu() {
			this.menuOpen = false
		},
		handleDocumentPointerDown(event) {
			if (!this.menuOpen) {
				return
			}

			const root = this.$refs.root
			if (root && root.contains(event.target)) {
				return
			}

			this.closeMenu()
		},
		selectItem(item) {
			if (item.disabled || item.separator) {
				return
			}

			this.menuOpen = false
			this.$emit('select', item)
		}
	}
}
</script>

<style scoped>
.cobudget-split-button {
	position: relative;
	display: inline-flex;
	align-items: stretch;
	flex: 0 0 auto;
	max-width: 100%;
	overflow: visible;
}

.cobudget-split-button.is-block {
	width: 100%;
}

.cobudget-split-button__main,
.cobudget-split-button__toggle {
	appearance: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: var(--cobudget-button-height);
	height: var(--cobudget-button-height);
	border: 0;
	background: var(--cobudget-primary);
	background-color: var(--cobudget-primary);
	color: var(--cobudget-primary-text) !important;
	cursor: pointer;
	font-size: var(--cobudget-font-md);
	font-weight: var(--cobudget-font-weight-action);
	line-height: 1.2;
	letter-spacing: 0;
	transition: background-color 0.2s, box-shadow 0.2s, opacity 0.2s;
	transform: none !important;
}

.cobudget-split-button__main {
	min-width: 120px;
	padding: 0 24px;
	border-top-left-radius: var(--cobudget-radius-md);
	border-bottom-left-radius: var(--cobudget-radius-md);
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
	box-shadow: none;
}

.cobudget-split-button__toggle {
	align-self: stretch;
	flex: 0 0 var(--cobudget-button-height);
	min-width: var(--cobudget-button-height);
	padding: 0;
	border-left: 1px solid rgba(255, 255, 255, 0.72);
	border-top-right-radius: var(--cobudget-radius-md);
	border-bottom-right-radius: var(--cobudget-radius-md);
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
	box-shadow: none;
}

.cobudget-split-button__main--primary,
.cobudget-split-button__toggle--primary {
	background: var(--cobudget-primary);
	background-color: var(--cobudget-primary);
	color: var(--cobudget-primary-text) !important;
  margin: 0 !important;
}

.cobudget-split-button__main--danger,
.cobudget-split-button__toggle--danger {
	background: var(--cobudget-error);
	background-color: var(--cobudget-error);
}

.cobudget-split-button:hover .cobudget-split-button__main--primary:not(:disabled),
.cobudget-split-button:hover .cobudget-split-button__toggle--primary:not(:disabled),
.cobudget-split-button__main--primary:focus-visible,
.cobudget-split-button__toggle--primary:focus-visible {
	background: var(--cobudget-primary-hover);
	background-color: var(--cobudget-primary-hover);
}

.cobudget-split-button:hover .cobudget-split-button__main--danger:not(:disabled),
.cobudget-split-button:hover .cobudget-split-button__toggle--danger:not(:disabled),
.cobudget-split-button__main--danger:focus-visible,
.cobudget-split-button__toggle--danger:focus-visible {
	background: var(--cobudget-error-dark);
	background-color: var(--cobudget-error-dark);
}

.cobudget-split-button__main:focus-visible,
.cobudget-split-button__toggle:focus-visible {
	outline: 0;
	box-shadow: var(--cobudget-focus-ring);
	z-index: 1;
}

.cobudget-split-button__main:disabled,
.cobudget-split-button__toggle:disabled {
	cursor: not-allowed;
	opacity: 0.55;
}

.cobudget-split-button.is-block .cobudget-split-button__main {
	flex: 1 1 auto;
}

.cobudget-split-button__toggle :deep(.material-design-icon) {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	color: currentColor !important;
}

.cobudget-split-button__toggle :deep(.material-design-icon__svg) {
	display: block;
	color: currentColor !important;
	fill: currentColor !important;
}

.cobudget-split-button__menu {
	position: absolute;
	right: 0;
	z-index: 10001;
	min-width: min(280px, calc(100vw - 32px));
	max-width: min(360px, calc(100vw - 32px));
	padding: 6px;
	border: 1px solid var(--cobudget-border);
	border-radius: var(--cobudget-radius-lg);
	background: var(--cobudget-surface);
	box-shadow: var(--cobudget-shadow-md);
}

.cobudget-split-button__menu--up {
	bottom: calc(100% + 8px);
}

.cobudget-split-button__menu--down {
	top: calc(100% + 8px);
}

.cobudget-split-button__item {
	display: block;
	width: 100%;
	border: 0;
	border-radius: var(--cobudget-radius-sm);
	background: transparent;
	color: var(--cobudget-text);
	cursor: pointer;
	font: inherit;
	font-size: var(--cobudget-font-base);
	font-weight: 600;
	padding: 9px 10px;
	text-align: left;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.cobudget-split-button__item:hover:not(:disabled),
.cobudget-split-button__item:focus-visible {
	background: var(--cobudget-surface-muted);
	outline: none;
}

.cobudget-split-button__item:disabled {
	color: var(--cobudget-text-muted);
	cursor: not-allowed;
}

.cobudget-split-button__separator {
	height: 1px;
	margin: 6px 4px;
	background: var(--cobudget-border);
}
</style>
