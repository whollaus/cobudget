<template>
	<div class="repayment-table-wrapper">
		<table class="repayment-list">
			<thead>
				<tr>
					<th scope="col" class="repayment-from-col">{{ $texts.repayments.from() }}</th>
					<th scope="col" class="repayment-to-col">{{ $texts.repayments.to() }}</th>
					<th scope="col" class="repayment-amount repayment-amount-col">{{ $texts.repayments.amount() }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="{ transfer, amount } in rows" :key="`${transfer.fromUserId}-${transfer.toUserId}-${transfer.amountCents}`" class="repayment-row">
					<td class="repayment-from-cell"><MemberIdentity :user-id="transfer.fromUserId" :display-name="transfer.fromDisplayName" /></td>
					<td class="repayment-to-cell"><MemberIdentity :user-id="transfer.toUserId" :display-name="transfer.toDisplayName" /></td>
					<td class="repayment-amount repayment-amount-cell">
						<strong class="repayment-full-amount">{{ amount.formatted }}</strong>
						<strong class="repayment-mobile-amount" :class="{ 'repayment-mobile-amount--suffix': amount.currencyAfter }">
							<bdi v-if="amount.currency" class="repayment-currency">{{ amount.currency }}</bdi>
							<bdi class="repayment-number">{{ amount.number }}</bdi>
						</strong>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
import MemberIdentity from './MemberIdentity.vue'
import { formatMoneyToParts } from '../utils/formatMoney'

export default {
	name: 'RepaymentList',
	components: { MemberIdentity },
	props: {
		transfers: { type: Array, required: true },
		// The caller supplies the current currency or the recorded settlement currency.
		currency: { type: String, required: true },
	},
	computed: {
		rows() {
			return this.transfers.map(transfer => {
				const parts = formatMoneyToParts(transfer.amount, this.currency)
				const amount = {
					formatted: parts.map(part => part.value).join(''),
					currency: parts.find(part => part.type === 'currency')?.value ?? '',
					number: parts.filter(part => part.type !== 'currency' && part.type !== 'literal').map(part => part.value).join(''),
					currencyAfter: parts.findIndex(part => part.type === 'currency') > parts.findIndex(part => part.type === 'integer'),
				}
				return { transfer, amount }
			})
		},
	},
}
</script>

<style scoped>
.repayment-table-wrapper {
	overflow-x: auto;
}
.repayment-list {
	width: 100%;
	min-width: 100%;
	table-layout: fixed;
	border-collapse: separate;
	border-spacing: 0;
	border: 0;
	border-radius: 0;
	background: var(--cobudget-surface);
}
.repayment-list th,
.repayment-list td {
	box-sizing: border-box;
	min-width: 0;
	padding: var(--cobudget-table-payment-padding);
	border-bottom: 1px solid var(--payment-table-separator, var(--cobudget-table-separator));
	vertical-align: middle;
	font-size: var(--cobudget-font-base);
	text-align: start;
	color: var(--cobudget-text);
}
.repayment-list thead th {
	height: var(--cobudget-payment-table-header-height);
	background: var(--cobudget-table-header-background, var(--cobudget-surface));
	color: var(--cobudget-text-muted);
	font-size: var(--cobudget-font-base);
	font-weight: 400;
	letter-spacing: normal;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
@media (min-width: 769px) {
	.repayment-list tbody tr {
		height: var(--cobudget-payment-table-row-height);
	}
	.repayment-list tr > :first-child {
		padding-inline-start: var(--cobudget-content-inset-start, var(--cobudget-dialog-padding, calc(var(--default-grid-baseline, 4px) * 3)));
	}
	.repayment-list tr > :last-child {
		padding-inline-end: var(--cobudget-content-inset-end, var(--cobudget-dialog-padding, calc(var(--default-grid-baseline, 4px) * 3)));
	}
}
.repayment-list tbody tr {
	transition: background-color var(--cobudget-hover-duration) linear;
}
.repayment-list tbody tr:hover,
.repayment-list tbody tr:focus-within {
	background: var(--color-background-hover, var(--cobudget-surface-muted));
}
.repayment-list .repayment-from-col,
.repayment-list .repayment-from-cell,
.repayment-list .repayment-to-col,
.repayment-list .repayment-to-cell {
	width: auto;
}
.repayment-list .repayment-amount {
	width: 150px;
	text-align: end;
	font-variant-numeric: tabular-nums;
	overflow-wrap: anywhere;
}
.repayment-list th.repayment-amount {
	font-weight: 400;
}
.repayment-list td.repayment-amount strong {
	font-weight: 600;
}
.repayment-mobile-amount { display: none; }

@media (max-width: 768px) {
	.repayment-list {
		table-layout: auto;
	}
	.repayment-list :is(th, td) {
		padding-inline: calc(var(--default-grid-baseline, 4px) * 2);
	}
	.repayment-list :is(.repayment-from-col, .repayment-to-col) {
		width: 50%;
	}
	.repayment-list .repayment-amount {
		width: 6.5rem;
		min-width: 6.5rem;
	}
	.repayment-list td.repayment-amount {
		white-space: normal;
	}
	.repayment-full-amount { display: none; }
	.repayment-mobile-amount {
		display: flex;
		flex-wrap: wrap;
		justify-content: flex-end;
		column-gap: 0.25em;
	}
	.repayment-mobile-amount--suffix { flex-wrap: wrap-reverse; }
	.repayment-mobile-amount--suffix .repayment-currency { order: 1; }
	.repayment-number { white-space: nowrap; }
	.repayment-list :deep(.member-identity) {
		gap: var(--default-grid-baseline, 4px);
	}
}

@media (max-width: 400px) {
	.repayment-list :deep(.member-identity-avatar) {
		display: none;
	}
}
</style>
