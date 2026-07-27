<template>
	<div class="payment-partner-edit-fields">
		<div class="payment-partner-primary-fields">
			<div class="form-group payment-partner-field">
				<label :for="`${idPrefix}-number`">{{ $texts.common.number() }}</label>
				<input
					:id="`${idPrefix}-number`"
					class="form-control"
					type="text"
					maxlength="128"
					:value="detailValue('number')"
					autocomplete="off"
					spellcheck="false"
					@input="updateDetail('number', $event.target.value)">
			</div>
			<div class="form-group payment-partner-field">
				<label :for="`${idPrefix}-display-name`">{{ $texts.paymentPartnerDetails.displayName() }}</label>
				<input
					:id="`${idPrefix}-display-name`"
					ref="displayNameInput"
					class="form-control"
					type="text"
					maxlength="128"
					:value="name"
					required
					@input="$emit('update:name', $event.target.value)">
			</div>
		</div>

		<details
			v-for="group in groups"
			:key="group.key"
			class="payment-partner-group"
			:open="initialOpenGroups[group.key]">
			<summary>
				<span>{{ groupLabel(group) }}</span>
			</summary>
			<div class="payment-partner-group-fields">
				<div
					v-for="field in group.fields"
					:key="field.key"
					class="form-group payment-partner-field"
					:class="{ 'payment-partner-field--wide': field.wide }">
					<label :for="fieldId(field)">{{ fieldLabel(field) }}</label>
					<textarea
						v-if="field.multiline"
						:id="fieldId(field)"
						class="form-control payment-partner-textarea"
						:maxlength="field.maxLength"
						:value="detailValue(field.key)"
						rows="3"
						@input="updateDetail(field.key, $event.target.value)"></textarea>
					<input
						v-else
						:id="fieldId(field)"
						class="form-control"
						:class="{ 'is-invalid': field.key === 'email' && emailInvalid }"
						:type="field.type || 'text'"
						:maxlength="field.maxLength"
						:pattern="field.pattern"
						:inputmode="field.inputmode"
						:value="detailValue(field.key)"
						autocomplete="off"
						:aria-invalid="field.key === 'email' && emailInvalid ? 'true' : 'false'"
						:aria-describedby="field.key === 'email' && emailInvalid ? `${fieldId(field)}-error` : undefined"
						@input="updateDetail(field.key, $event.target.value)">
					<p
						v-if="field.key === 'email' && emailInvalid"
						:id="`${fieldId(field)}-error`"
						class="payment-partner-field-error"
						role="alert">
						{{ $texts.paymentPartnerDetails.invalidEmail() }}
					</p>
				</div>
			</div>
		</details>
	</div>
</template>

<script>
import { isValidPaymentPartnerEmail } from '../utils/paymentPartnerDetails'

const PAYMENT_PARTNER_GROUPS = [
	{
		key: 'personAndCompany',
		fields: [
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
	props: {
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
			initialOpenGroups: Object.fromEntries(
				PAYMENT_PARTNER_GROUPS.map(group => [group.key, groupHasValues(this.details, group)])
			),
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
			return this.$texts.paymentPartnerDetails[field.key]()
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
	gap: 10px;
}

.payment-partner-primary-fields,
.payment-partner-group-fields {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
	gap: 12px;
}

.payment-partner-group-fields {
	grid-template-columns: repeat(2, minmax(0, 1fr));
	padding: 14px;
}

.form-group {
	min-width: 0;
	margin-bottom: 0;
}

.form-group label {
	display: block;
	color: var(--cobudget-text-muted, var(--color-text-light));
	font-size: var(--cobudget-font-sm);
	letter-spacing: 0.5px;
}

.form-control {
	box-sizing: border-box;
	width: 100%;
	height: 34px;
	padding: 0 12px;
	border: 1px solid var(--cobudget-border-strong, var(--color-border-dark));
	border-radius: var(--border-radius, 6px);
	background: var(--cobudget-surface, var(--color-main-background));
	color: var(--cobudget-text, var(--color-main-text));
	font-size: var(--cobudget-font-ui);
	transition: border-color 0.2s;
}

.form-control:focus {
	border-color: var(--color-primary);
	outline: none;
}

.form-control.is-invalid {
	border-color: var(--cobudget-error, var(--color-error));
}

.payment-partner-textarea {
	height: auto;
	min-height: 84px;
	padding: 8px 12px;
	line-height: 1.4;
	resize: vertical;
}

.payment-partner-field--wide {
	grid-column: 1 / -1;
}

.payment-partner-field-error {
	margin: 4px 0 0;
	color: var(--cobudget-error, var(--color-error));
	font-size: var(--cobudget-font-sm);
	line-height: 1.35;
}

.payment-partner-group {
	overflow: clip;
	border: 1px solid var(--cobudget-border, var(--color-border));
	border-radius: var(--border-radius-large);
	background: var(--cobudget-surface, var(--color-main-background));
	color: var(--cobudget-text, var(--color-main-text));
}

.payment-partner-group summary {
	display: flex;
	align-items: center;
	gap: 8px;
	list-style: none;
	padding: 11px 14px;
	background: var(--cobudget-surface-muted, var(--color-background-hover));
	color: var(--cobudget-text, var(--color-main-text));
	cursor: pointer;
	font-weight: 600;
	line-height: 1.35;
}

.payment-partner-group summary::-webkit-details-marker {
	display: none;
}

.payment-partner-group summary::before {
	content: '▶';
	flex: 0 0 auto;
	font-size: 0.75em;
	line-height: 1;
	transition: transform 120ms ease;
}

.payment-partner-group[open] summary::before {
	transform: rotate(90deg);
}

.payment-partner-group summary:hover {
	background: var(--color-background-dark);
}

.payment-partner-group summary:focus-visible {
	outline: 2px solid var(--color-primary);
	outline-offset: -2px;
}

.payment-partner-group[open] summary {
	border-bottom: 1px solid var(--cobudget-border, var(--color-border));
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
