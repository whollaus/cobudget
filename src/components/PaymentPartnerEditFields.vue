<template>
	<div class="payment-partner-edit-fields">
		<div v-if="!hideName" class="payment-partner-primary-fields">
			<NcFormGroup class="form-group payment-partner-field cobudget-form-group">
				<template #label><label class="cobudget-form-label" :for="`${idPrefix}-display-name`">{{ $texts.paymentPartnerDetails.displayName() }}</label></template>
				<NcInputField
					:id="`${idPrefix}-display-name`"
					ref="displayNameInput"
					label-outside
					:label="$texts.paymentPartnerDetails.displayName()"
					:disabled="disabled"
					type="text"
					maxlength="128"
					:model-value="name"
					required
					@update:model-value="$emit('update:name', $event)" class="cobudget-native-field" />
			</NcFormGroup>
		</div>

		<div
			v-for="group in groups"
			:key="group.key"
			class="payment-partner-group">
			<NcFormBoxButton
				:label="groupLabel(group)"
				:description="openGroup === group.key ? undefined : groupSummary(group)"
				:aria-expanded="openGroup === group.key"
				:aria-controls="`${idPrefix}-${group.key}-fields`"
				@click="openGroup = openGroup === group.key ? null : group.key">
				<template #icon>
					<ChevronDown v-if="openGroup === group.key" :size="20" aria-hidden="true" />
					<ChevronRight v-else :size="20" aria-hidden="true" />
				</template>
			</NcFormBoxButton>
			<div v-show="openGroup === group.key" :id="`${idPrefix}-${group.key}-fields`" class="payment-partner-group-fields">
				<NcFormGroup
					v-for="field in group.fields"
					:key="field.key"
					class="form-group payment-partner-field cobudget-form-group"
					:class="{ 'payment-partner-field--wide': field.wide }">
					<template #label><label class="cobudget-form-label" :for="fieldId(field)">{{ fieldLabel(field) }}</label></template>
					<NcTextArea
						v-if="field.multiline"
						:id="fieldId(field)"
						label-outside
						:label="fieldLabel(field)"
						:disabled="disabled"
						:maxlength="field.maxLength"
						:model-value="detailValue(field.key)"
						rows="3"
						resize="vertical"
						@update:model-value="updateDetail(field.key, $event)" class="cobudget-native-field" />
					<NcInputField
						v-else
						:id="fieldId(field)"
						label-outside
						:label="fieldLabel(field)"
						:disabled="disabled"
						:error="field.key === 'email' && emailInvalid"
						:helper-text="field.key === 'email' && emailInvalid ? $texts.paymentPartnerDetails.invalidEmail() : ''"
						:type="field.type || 'text'"
						:maxlength="field.maxLength"
						:pattern="field.pattern"
						:inputmode="field.inputmode"
						:model-value="detailValue(field.key)"
						autocomplete="off"
						:aria-invalid="field.key === 'email' && emailInvalid ? 'true' : 'false'"
						@update:model-value="updateDetail(field.key, $event)" class="cobudget-native-field" />
				</NcFormGroup>
			</div>
		</div>
	</div>
</template>

<script>
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { isValidPaymentPartnerEmail } from '../utils/paymentPartnerDetails'

const PAYMENT_PARTNER_GROUPS = [
	{
		key: 'personAndCompany',
		fields: [
			{ key: 'number', maxLength: 128 },
			{ key: 'salutation', maxLength: 64 },
			{ key: 'title', maxLength: 128 },
			{ key: 'companyName', maxLength: 255, wide: true },
			{ key: 'additional', maxLength: 255, wide: true },
			{ key: 'vatId', maxLength: 64, wide: true },
			{ key: 'firstName', maxLength: 128 },
			{ key: 'lastName', maxLength: 128 },
		],
	},
	{
		key: 'address',
		fields: [
			{ key: 'street', maxLength: 255, wide: true },
			{ key: 'postalCode', maxLength: 32 },
			{ key: 'city', maxLength: 128 },
			{ key: 'country', maxLength: 128, wide: true },
			{ key: 'addressNote', maxLength: 10000, multiline: true, wide: true },
		],
	},
	{
		key: 'contact',
		fields: [
			{
				key: 'email',
				maxLength: 254,
				type: 'email',
				inputmode: 'email',
				pattern: '[^\\s@]+@[^\\s@]+\\.[^\\s@]+',
				wide: true,
			},
			{ key: 'phone', maxLength: 64 },
			{ key: 'mobile', maxLength: 64 },
			{ key: 'fax', maxLength: 64 },
			{ key: 'web', maxLength: 512 },
		],
	},
	{
		key: 'account',
		fields: [
			{ key: 'accountHolder', maxLength: 255, wide: true },
			{ key: 'iban', maxLength: 64 },
			{ key: 'bic', maxLength: 32 },
			{ key: 'bank', maxLength: 255, wide: true },
			{ key: 'bankCode', maxLength: 64 },
			{ key: 'accountNumber', maxLength: 64 },
		],
	},
	{
		key: 'note',
		fields: [
			{ key: 'note', maxLength: 10000, multiline: true, wide: true },
		],
	},
]

const groupHasValues = (details, group) => group.fields.some(field => {
	const value = details?.[field.key]
	return value !== null && value !== undefined && String(value).trim() !== ''
})

export default {
	name: 'PaymentPartnerEditFields',
	components: { NcFormGroup, NcFormBoxButton, ChevronDown, ChevronRight, NcInputField, NcTextArea },
	props: {
		disabled: Boolean,
		hideName: Boolean,
		name: {
			type: String,
			default: ''
		},
		details: {
			type: Object,
			default: () => ({})
		},
		idPrefix: {
			type: String,
			default: 'payment-partner'
		}
	},
	emits: ['update:name', 'update:details'],
	data() {
		return {
			groups: PAYMENT_PARTNER_GROUPS,
			openGroup: PAYMENT_PARTNER_GROUPS.find(group => groupHasValues(this.details, group))?.key ?? null,
		}
	},
	computed: {
		emailInvalid() {
			return !isValidPaymentPartnerEmail(this.details?.email)
		}
	},
	methods: {
		detailValue(field) {
			const value = this.details?.[field]
			return value === null || value === undefined ? '' : String(value)
		},
		fieldId(field) {
			const suffix = field.key.replace(/[A-Z]/g, letter => `-${letter.toLowerCase()}`)
			return `${this.idPrefix}-${suffix}`
		},
		fieldLabel(field) {
			return field.key === 'number' ? this.$texts.common.number() : this.$texts.paymentPartnerDetails[field.key]()
		},
		groupSummary(group) {
			const summary = group.fields.map(field => this.detailValue(field.key).trim()).filter(Boolean).slice(0, 2).join(' · ').replace(/\s+/g, ' ')
			return summary.length > 100 ? `${summary.slice(0, 100)}…` : summary || undefined
		},
		groupLabel(group) {
			return this.$texts.paymentPartnerDetails[group.key]()
		},
		updateDetail(field, value) {
			this.$emit('update:details', {
				...this.details,
				[field]: String(value ?? '')
			})
		},
		focusName() {
			this.$refs.displayNameInput?.focus()
			this.$refs.displayNameInput?.select()
		}
	}
}
</script>

<style scoped>
.payment-partner-edit-fields {
	display: grid;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}

.payment-partner-primary-fields,
.payment-partner-group-fields {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
	gap: 12px;
}

.payment-partner-group-fields {
	grid-template-columns: repeat(2, minmax(0, 1fr));
	padding: calc(var(--default-grid-baseline, 4px) * 3);
}

.form-group {
	min-width: 0;
	margin-bottom: 0;
}



.payment-partner-field--wide {
	grid-column: 1 / -1;
}

@media (max-width: 768px) {
	.payment-partner-primary-fields,
	.payment-partner-group-fields {
		grid-template-columns: minmax(0, 1fr);
	}

	.payment-partner-field--wide {
		grid-column: auto;
	}
}
</style>
