<template>
	<Teleport to="body">
		<div v-if="show" class="confirm-modal-backdrop" @click.self="cancel">
			<div
				ref="modal"
				class="confirm-modal"
				:class="{ 'confirm-modal-wide': wide }"
				role="dialog"
				aria-modal="true"
				:aria-labelledby="titleId"
				tabindex="-1"
				@keydown.esc.stop.prevent="cancel">
				<div class="confirm-modal-header">
					<h2 :id="titleId">{{ title }}</h2>
					<button
						type="button"
						class="confirm-modal-close-button"
						:aria-label="closeLabel"
						:title="closeLabel"
						@click="cancel">
						<CloseIcon :size="22" aria-hidden="true" />
					</button>
				</div>

				<p v-if="message" class="confirm-modal-message">
					{{ message }}
				</p>
				<slot />
				<p v-if="error" class="confirm-modal-error">
					{{ error }}
				</p>

				<ModalActions
					:cancel-label="cancelLabel"
					:cancel-disabled="busy"
					:primary-label="confirmLabel"
					:primary-busy-label="busyLabel"
					:primary-disabled="confirmDisabled"
					:primary-busy="busy"
					:primary-variant="confirmVariant"
					primary-type="button"
					@cancel="cancel"
					@primary="$emit('confirm')" />
			</div>
		</div>
	</Teleport>
</template>

<script>
import ModalActions from './ModalActions.vue'
import { texts } from '../l10n/texts'
import CloseIcon from 'vue-material-design-icons/Close.vue'

let nextConfirmModalId = 0

export default {
	name: 'ConfirmModal',
	components: {
		ModalActions,
		CloseIcon
	},
	props: {
		show: {
			type: Boolean,
			default: false
		},
		title: {
			type: String,
			required: true
		},
		message: {
			type: String,
			default: ''
		},
		error: {
			type: String,
			default: ''
		},
		cancelLabel: {
			type: String,
			default: () => texts.common.cancel()
		},
		confirmLabel: {
			type: String,
			required: true
		},
		busyLabel: {
			type: String,
			default: () => texts.common.waitBusy()
		},
		confirmVariant: {
			type: String,
			default: 'primary',
			validator: value => ['primary', 'danger'].includes(value)
		},
		confirmDisabled: {
			type: Boolean,
			default: false
		},
		busy: {
			type: Boolean,
			default: false
		},
		wide: {
			type: Boolean,
			default: false
		}
	},
	emits: ['cancel', 'confirm'],
	data() {
		return {
			titleId: `confirm-modal-title-${++nextConfirmModalId}`
		}
	},
	computed: {
		closeLabel() {
			return texts.common.close()
		}
	},
	watch: {
		show(newValue) {
			if (newValue) {
				this.focusInitialElement()
			}
		}
	},
	mounted() {
		if (this.show) {
			this.focusInitialElement()
		}
	},
	methods: {
		focusInitialElement() {
			this.$nextTick(() => {
				const modal = this.$refs.modal
				if (!modal) {
					return
				}
				const target = modal.querySelector('[data-autofocus]:not(:disabled)')
					|| modal.querySelector('.cobudget-button--secondary:not(:disabled)')
					|| modal.querySelector('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [href], [tabindex]:not([tabindex="-1"])')
					|| modal
				target.focus({ preventScroll: true })
			})
		},
		cancel() {
			if (this.busy) {
				return
			}
			this.$emit('cancel')
		}
	}
}
</script>

<style scoped>
.confirm-modal-backdrop {
	--cobudget-text: #222;
	--cobudget-text-muted: #666;
	--cobudget-surface: #fff;
	--cobudget-surface-muted: #f5f5f5;
	--cobudget-surface-strong: #e5e5e5;
	--cobudget-border: #ddd;
	--cobudget-border-strong: #ccc;
	--color-main-text: var(--cobudget-text);
	--color-text-maxcontrast: var(--cobudget-text-muted);
	--color-main-background: var(--cobudget-surface);
	--color-background-hover: var(--cobudget-surface-muted);
	--color-background-dark: var(--cobudget-surface-muted);
	--color-border: var(--cobudget-border);
	--color-border-dark: var(--cobudget-border-strong);

	position: fixed;
	top: 0;
	left: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100vw;
	height: 100vh;
	background: rgba(0, 0, 0, 0.45);
	z-index: 10000;
	backdrop-filter: blur(2px);
}

:global(html.cobudget-theme-dark) .confirm-modal-backdrop,
:global(body.cobudget-theme-dark) .confirm-modal-backdrop,
:global(html[data-cobudget-theme="dark"]) .confirm-modal-backdrop,
:global(body[data-cobudget-theme="dark"]) .confirm-modal-backdrop,
:global(html[data-themes*="dark"]:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(body[data-themes*="dark"]:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(html[data-theme*="dark"]:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(body[data-theme*="dark"]:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(html[data-theme-default*="dark"]:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(body[data-theme-default*="dark"]:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(html[data-color-scheme*="dark"]:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(body[data-color-scheme*="dark"]:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(html.dark:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(body.dark:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(html.theme-dark:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(body.theme-dark:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(html.theme--dark:not(.cobudget-theme-light)) .confirm-modal-backdrop,
:global(body.theme--dark:not(.cobudget-theme-light)) .confirm-modal-backdrop {
	--cobudget-text: #f5f5f5;
	--cobudget-text-muted: #b3b3b3;
	--cobudget-surface: #181818;
	--cobudget-surface-muted: #242424;
	--cobudget-surface-strong: #303030;
	--cobudget-border: #3a3a3a;
	--cobudget-border-strong: #555;
	--color-main-text: var(--cobudget-text);
	--color-text-maxcontrast: var(--cobudget-text-muted);
	--color-main-background: var(--cobudget-surface);
	--color-background-hover: var(--cobudget-surface-muted);
	--color-background-dark: var(--cobudget-surface-muted);
	--color-border: var(--cobudget-border);
	--color-border-dark: var(--cobudget-border-strong);
}

@media (prefers-color-scheme: dark) {
	:global(html.cobudget-theme-auto) .confirm-modal-backdrop,
	:global(body.cobudget-theme-auto) .confirm-modal-backdrop {
		--cobudget-text: #f5f5f5;
		--cobudget-text-muted: #b3b3b3;
		--cobudget-surface: #181818;
		--cobudget-surface-muted: #242424;
		--cobudget-surface-strong: #303030;
		--cobudget-border: #3a3a3a;
		--cobudget-border-strong: #555;
		--color-main-text: var(--cobudget-text);
		--color-text-maxcontrast: var(--cobudget-text-muted);
		--color-main-background: var(--cobudget-surface);
		--color-background-hover: var(--cobudget-surface-muted);
		--color-background-dark: var(--cobudget-surface-muted);
		--color-border: var(--cobudget-border);
		--color-border-dark: var(--cobudget-border-strong);
	}
}

.confirm-modal {
	width: min(520px, calc(100vw - 32px));
	padding: 24px;
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	box-shadow: 0 10px 40px rgba(0, 0, 0, 0.22);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.confirm-modal-wide {
	width: min(760px, calc(100vw - 32px));
}

.confirm-modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	margin: -4px 0 20px;
	padding-bottom: 14px;
	border-bottom: 1px solid var(--cobudget-border, var(--color-border, #ddd));
}

.confirm-modal-header h2 {
	margin: 0;
	font-size: var(--cobudget-font-title-sm);
	font-weight: 700;
	line-height: 1.25;
	color: var(--cobudget-text, var(--color-main-text, #222));
	text-align: left;
}

.confirm-modal-close-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
	width: var(--cobudget-icon-button-size, 44px);
	height: var(--cobudget-icon-button-size, 44px);
	min-width: var(--cobudget-icon-button-size, 44px);
	padding: 0;
	border: 0;
	border-radius: var(--cobudget-radius-sm, 8px);
	background: transparent;
	color: var(--cobudget-text, var(--color-main-text, #222));
	cursor: pointer;
}

.confirm-modal-close-button:hover,
.confirm-modal-close-button:focus-visible {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5));
	outline: none;
}

.confirm-modal-close-button:focus-visible {
	box-shadow: var(--cobudget-focus-ring, 0 0 0 2px var(--color-primary, #0082c9));
}

.confirm-modal-close-button :deep(.material-design-icon),
.confirm-modal-close-button :deep(.material-design-icon__svg) {
	display: block;
}

.confirm-modal-message {
	margin: 0;
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--cobudget-font-md);
	line-height: 1.5;
	text-align: left;
}

.confirm-modal :deep(label),
.confirm-modal :deep(h3),
.confirm-modal :deep(h4),
.confirm-modal :deep(strong) {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.confirm-modal :deep(p),
.confirm-modal :deep(span) {
	color: inherit;
}

.confirm-modal :deep(input),
.confirm-modal :deep(select),
.confirm-modal :deep(textarea) {
	background-color: var(--cobudget-surface) !important;
	border-color: var(--cobudget-border-strong) !important;
	color: var(--cobudget-text) !important;
}

.confirm-modal :deep(input::placeholder),
.confirm-modal :deep(textarea::placeholder) {
	color: var(--cobudget-text-muted) !important;
	opacity: 1;
}

.confirm-modal :deep(.cobudget-button--primary) {
	background: var(--cobudget-primary, var(--color-primary, #0082c9)) !important;
	background-color: var(--cobudget-primary, var(--color-primary, #0082c9)) !important;
	border-color: var(--cobudget-primary, var(--color-primary, #0082c9)) !important;
	color: var(--cobudget-primary-text, var(--color-primary-text, #fff)) !important;
}

.confirm-modal :deep(.cobudget-button--primary:hover:not(:disabled)),
.confirm-modal :deep(.cobudget-button--primary:focus-visible:not(:disabled)) {
	background: var(--cobudget-primary-hover, var(--color-primary-hover, #006a92)) !important;
	background-color: var(--cobudget-primary-hover, var(--color-primary-hover, #006a92)) !important;
	border-color: var(--cobudget-primary-hover, var(--color-primary-hover, #006a92)) !important;
	color: var(--cobudget-primary-text, var(--color-primary-text, #fff)) !important;
}

.confirm-modal :deep(.cobudget-button--secondary) {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5)) !important;
	border-color: transparent !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.confirm-modal :deep(.cobudget-button--secondary:hover:not(:disabled)),
.confirm-modal :deep(.cobudget-button--secondary:focus-visible:not(:disabled)) {
	background: var(--cobudget-surface-strong, var(--color-background-dark, #e5e5e5)) !important;
	border-color: transparent !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.confirm-modal :deep(.cobudget-button--secondary:disabled) {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5)) !important;
	border-color: transparent !important;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666)) !important;
	opacity: 1 !important;
}

.confirm-modal-error {
	margin: 16px 0 0;
	color: var(--cobudget-error);
	font-weight: 600;
}

@media (max-width: 768px) {
	.confirm-modal {
		padding: 20px;
	}

	.confirm-modal-header h2 {
		font-size: var(--cobudget-font-section);
	}
}
</style>
