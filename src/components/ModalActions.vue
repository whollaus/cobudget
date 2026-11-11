<template>
	<div
		class="modal-actions"
		:class="{
			'has-left-actions': dangerLabel || $slots.left,
			'is-flush': flush,
			'is-equal-width': equalWidth,
			'is-danger-row': dangerRow,
			'is-inline-mobile': inlineMobile
		}">
		<div v-if="dangerLabel || $slots.left" class="modal-actions-left">
			<slot name="left">
				<CbButton
					v-if="dangerLabel"
					type="button"
					variant="danger-outline"
					:disabled="dangerDisabled || dangerBusy"
					:title="dangerTitle"
					@click="$emit('danger')">
					{{ dangerBusy ? dangerBusyLabel : dangerLabel }}
				</CbButton>
			</slot>
		</div>
		<div class="modal-actions-right">
			<CbButton
				v-if="extraLabel"
				type="button"
				variant="secondary"
				:disabled="extraDisabled || extraBusy"
				:title="extraTitle"
				@click="$emit('extra')">
				{{ extraBusy ? extraBusyLabel : extraLabel }}
			</CbButton>
			<NcButton
				v-if="showCancel"
				type="button"
				variant="tertiary"
				class="modal-cancel-button cobudget-native-button"
				:disabled="cancelDisabled"
				@click="$emit('cancel')">
				{{ cancelLabel }}
			</NcButton>
			<CbSplitButton
				v-if="primaryLabel && hasPrimaryMenu"
				:type="primaryType"
				:label="primaryLabel"
				:busy-label="primaryBusyLabel"
				:disabled="primaryDisabled"
				:busy="primaryBusy"
				:variant="primaryVariant"
				:items="primaryMenuItems"
				:menu-aria-label="$texts.actions.moreSaveOptions()"
				@click="$emit('primary')"
				@select="$emit('primary-menu', $event)" />
			<NcButton
				v-else-if="primaryLabel"
				class="modal-primary-button cobudget-native-button"
				:type="primaryType"
				:variant="primaryVariant === 'danger' ? 'error' : 'primary'"
				:aria-busy="primaryBusy || undefined"
				:disabled="primaryDisabled || primaryBusy"
				@click="$emit('primary')">
				{{ primaryBusy ? primaryBusyLabel : primaryLabel }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import CbButton from './CbButton.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import CbSplitButton from './CbSplitButton.vue'
import { texts } from '../l10n/texts'

export default {
	name: 'ModalActions',
	components: {
		NcButton,
		CbButton,
		CbSplitButton
	},
	props: {
		cancelLabel: {
			type: String,
			default: () => texts.common.cancel()
		},
		cancelDisabled: {
			type: Boolean,
			default: false
		},
		dangerLabel: {
			type: String,
			default: ''
		},
		dangerBusyLabel: {
			type: String,
			default: () => texts.common.deleteBusy()
		},
		dangerDisabled: {
			type: Boolean,
			default: false
		},
		dangerBusy: {
			type: Boolean,
			default: false
		},
		dangerTitle: {
			type: String,
			default: ''
		},
		extraLabel: {
			type: String,
			default: ''
		},
		extraBusyLabel: {
			type: String,
			default: () => texts.common.saveBusy()
		},
		extraDisabled: {
			type: Boolean,
			default: false
		},
		extraBusy: {
			type: Boolean,
			default: false
		},
		extraTitle: {
			type: String,
			default: ''
		},
		primaryLabel: {
			type: String,
			default: ''
		},
		primaryBusyLabel: {
			type: String,
			default: () => texts.common.saveBusy()
		},
		primaryDisabled: {
			type: Boolean,
			default: false
		},
		primaryBusy: {
			type: Boolean,
			default: false
		},
		primaryType: {
			type: String,
			default: 'submit'
		},
		primaryVariant: {
			type: String,
			default: 'primary',
			validator: value => ['primary', 'danger'].includes(value)
		},
		showCancel: {
			type: Boolean,
			default: true
		},
		flush: {
			type: Boolean,
			default: false
		},
		equalWidth: {
			type: Boolean,
			default: false
		},
		dangerRow: {
			type: Boolean,
			default: false
		},
		inlineMobile: {
			type: Boolean,
			default: false
		},
		primaryMenuItems: {
			type: Array,
			default: () => []
		}
	},
	emits: ['cancel', 'danger', 'extra', 'primary', 'primary-menu'],
	computed: {
		hasPrimaryMenu() {
			return this.primaryMenuItems.length > 0;
		}
	}
}
</script>

<style scoped>
	.modal-actions {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		justify-content: flex-end;
		gap: 12px;
		margin-top: 24px;
		max-width: 100%;
	}

.modal-actions.has-left-actions {
	justify-content: space-between;
}

.modal-actions.is-flush {
	margin-top: 0;
	width: 100%;
}

	.modal-actions-left,
	.modal-actions-right {
		display: flex;
		align-items: center;
		gap: 12px;
		flex-wrap: wrap;
		min-width: 0;
	}

	.modal-actions-right {
		flex: 1 1 auto;
		margin-left: auto;
		justify-content: flex-end;
	}

@media (max-width: 768px) {
	.modal-actions { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); }
	.modal-actions,
	.modal-actions.has-left-actions {
		align-items: stretch;
		flex-direction: column;
	}

	.modal-actions-left,
	.modal-actions-right {
		align-items: stretch;
		flex-direction: column;
		width: 100%;
	}

	.modal-actions-left {
		order: 2;
	}

	.modal-actions-right {
		order: 1;
	}

	.modal-actions.is-danger-row .modal-actions-left {
		order: 1;
	}

	.modal-actions.is-danger-row .modal-actions-right {
		order: 2;
	}

	.modal-actions.is-equal-width .modal-actions-right {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		margin-left: 0;
	}

	.modal-actions.is-equal-width :deep(.cobudget-button),
	.modal-actions.is-equal-width .modal-cancel-button,
	.modal-actions.is-equal-width .modal-primary-button {
		min-width: 0;
		width: 100%;
	}

	.modal-actions.is-equal-width :deep(.cobudget-split-button) {
		width: 100%;
	}

	.modal-actions :deep(.cobudget-button),
	.modal-actions .modal-cancel-button,
	.modal-actions .modal-primary-button {
		width: 100%;
	}

	.modal-actions.is-inline-mobile,
	.modal-actions.is-inline-mobile.has-left-actions {
		align-items: center;
		flex-direction: row;
	}

	.modal-actions.is-inline-mobile .modal-actions-left,
	.modal-actions.is-inline-mobile .modal-actions-right {
		align-items: center;
		flex-direction: row;
		width: auto;
	}

	.modal-actions.is-inline-mobile .modal-actions-left {
		order: 1;
		flex: 0 0 auto;
		min-width: var(--cobudget-mobile-touch-size, 44px);
	}

	.modal-actions.is-inline-mobile .modal-actions-right {
		order: 2;
		flex: 1 1 auto;
		justify-content: flex-end;
		margin-left: auto;
	}

	.modal-actions.is-inline-mobile .modal-cancel-button,
	.modal-actions.is-inline-mobile .modal-primary-button,
	.modal-actions.is-inline-mobile :deep(.cobudget-button),
	.modal-actions.is-inline-mobile :deep(.cobudget-split-button) {
		width: auto;
	}
}
</style>
