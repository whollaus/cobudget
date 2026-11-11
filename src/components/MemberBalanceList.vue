<template>
	<div class="member-balances">
	<div class="member-balance-table-wrapper">
		<table class="member-balance-list">
			<thead>
				<tr>
					<th scope="col" class="member-name-col">{{ $texts.memberBalances.member() }}</th>
					<th scope="col" class="balance-amount balance-paid-col">{{ $texts.memberBalances.paid() }}</th>
					<th scope="col" class="balance-amount balance-share-col">{{ $texts.memberBalances.ownShare() }}</th>
					<th scope="col" class="balance-amount balance-total-col">{{ $texts.memberBalances.balance() }}</th>
				</tr>
			</thead>
			<tbody v-for="balance in balances" :key="balance.userId" :class="{ expanded: isExpanded(balance) }">
				<tr class="member-balance-row">
					<td class="member-name-cell">
						<div class="balance-member">
							<MemberIdentity :user-id="balance.userId" :display-name="balance.displayName" :is-former="balance.isFormer" />
							<slot name="member-extra" :balance="balance" />
							<NcButton v-if="hasDetails(balance)" variant="tertiary" :aria-label="$texts.memberBalances.detailsFor(balance.displayName)" :aria-expanded="isExpanded(balance)" :aria-controls="`${detailsId}-${balance.userId}`" @click="toggleDetails(balance)">
								<template #icon><ChevronDownIcon v-if="isExpanded(balance)" :size="20" /><ChevronRightIcon v-else :size="20" /></template>
							</NcButton>
						</div>
					</td>
					<td class="balance-amount balance-paid-cell" :data-label="$texts.memberBalances.paid()">{{ formatAmount(balance.paid) }}</td>
					<td class="balance-amount balance-share-cell" :data-label="$texts.memberBalances.ownShare()">
						{{ formatAmount(balance.fairShare) }}
						<span v-if="balance.shareBasisPoints != null" class="balance-share-percent" :title="$texts.memberBalances.defaultShare()" :aria-label="`${$texts.memberBalances.defaultShare()}: ${formatPercent(balance.shareBasisPoints)}%`">({{ formatPercent(balance.shareBasisPoints) }}%)</span>
					</td>
					<td class="balance-amount balance-total-cell" :data-label="$texts.memberBalances.balance()"><strong :class="{ positive: balance.balance > 0, negative: balance.balance < 0 }">{{ signedAmount(balance.balance) }}</strong></td>
				</tr>
				<tr v-if="hasDetails(balance)" v-show="isExpanded(balance)" :id="`${detailsId}-${balance.userId}`" class="balance-details-row">
					<td colspan="4" class="balance-details">
						<dl>
							<div v-if="balance.receivedCents || balance.incomeShareCents"><dt>{{ $texts.areaDetail.received() }}</dt><dd>{{ formatAmount(balance.received) }}</dd></div>
							<div v-if="balance.receivedCents || balance.incomeShareCents"><dt>{{ $texts.areaDetail.incomeShare() }}</dt><dd>{{ formatAmount(balance.incomeShare) }}</dd></div>
						</dl>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	</div>
</template>

<script>
import MemberIdentity from './MemberIdentity.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import { useId } from 'vue'
import { getAppLocale } from '../utils/formatMoney'

export default {
	name: 'MemberBalanceList',
	components: { MemberIdentity, NcButton, ChevronDownIcon, ChevronRightIcon },
	setup() { return { detailsId: useId() } },
	data() { return { expandedMembers: [] } },
	props: {
		balances: { type: Array, required: true },
		// Keep the caller's currency, including the currency of historical snapshots.
		formatAmount: { type: Function, required: true },
	},
	methods: {
		signedAmount(amount) {
			return `${amount > 0 ? '+' : ''}${this.formatAmount(amount)}`
		},
		formatPercent(basisPoints) {
			return (Number(basisPoints) / 100).toLocaleString(getAppLocale(), { maximumFractionDigits: 2 })
		},
		hasDetails(balance) {
			return Boolean(balance.receivedCents || balance.incomeShareCents)
		},
		isExpanded(balance) { return this.expandedMembers.includes(balance.userId) },
		toggleDetails(balance) {
			this.expandedMembers = this.isExpanded(balance)
				? this.expandedMembers.filter(id => id !== balance.userId)
				: [...this.expandedMembers, balance.userId]
		},
	},
}
</script>

<style scoped>
.member-balance-table-wrapper {
	overflow-x: auto;
}
.member-balance-list {
	width: 100%;
	min-width: 100%;
	table-layout: fixed;
	border-collapse: separate;
	border-spacing: 0;
	border: 0;
	border-radius: 0;
	background: var(--cobudget-surface);
}
.member-balance-list th,
.member-balance-list td {
	box-sizing: border-box;
	min-width: 0;
	padding: var(--cobudget-table-payment-padding);
	border-bottom: 1px solid var(--payment-table-separator, var(--cobudget-table-separator));
	vertical-align: middle;
	font-size: var(--cobudget-font-base);
	text-align: start;
	color: var(--cobudget-text);
}
.member-balance-list thead th {
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
	.member-balance-list tbody tr.member-balance-row {
		height: var(--cobudget-payment-table-row-height);
	}
	.member-balance-list tr > :first-child {
		padding-inline-start: var(--cobudget-content-inset-start, var(--cobudget-dialog-padding, calc(var(--default-grid-baseline, 4px) * 3)));
	}
	.member-balance-list tr > :last-child {
		padding-inline-end: var(--cobudget-content-inset-end, var(--cobudget-dialog-padding, calc(var(--default-grid-baseline, 4px) * 3)));
	}
}
.member-balance-list tbody tr.member-balance-row {
	transition: background-color var(--cobudget-hover-duration) linear;
}
.member-balance-list tbody tr.member-balance-row:hover,
.member-balance-list tbody tr.member-balance-row:focus-within {
	background: var(--color-background-hover, var(--cobudget-surface-muted));
}
.member-balance-list tbody.expanded .member-balance-row td {
	border-bottom: 0;
}
.member-balance-list .member-name-col,
.member-balance-list .member-name-cell {
	width: auto;
}
.member-balance-list .balance-paid-col,
.member-balance-list .balance-paid-cell {
	width: 130px;
}
.member-balance-list .balance-share-col,
.member-balance-list .balance-share-cell {
	width: 150px;
}
.member-balance-list .balance-total-col,
.member-balance-list .balance-total-cell {
	width: 130px;
}
.member-balance-list .balance-amount {
	text-align: end;
	font-variant-numeric: tabular-nums;
	overflow-wrap: anywhere;
}
.member-balance-list th.balance-amount {
	font-weight: 400;
}
.member-balance-list td.balance-total-cell strong {
	font-weight: 600;
}
.balance-amount .positive { color: var(--cobudget-success); }
.balance-amount .negative { color: var(--cobudget-error); }
.balance-member {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
	font-weight: var(--font-weight-element, 500);
}
.balance-member > .button-vue { flex-shrink: 0; margin-inline-start: auto; }
.member-balance-list .balance-details {
	padding-inline: var(--cobudget-content-inset-start, var(--cobudget-dialog-padding, calc(var(--default-grid-baseline, 4px) * 3))) var(--cobudget-content-inset-end, var(--cobudget-dialog-padding, calc(var(--default-grid-baseline, 4px) * 3)));
	padding-block: 8px 12px;
	overflow-wrap: anywhere;
	background: var(--cobudget-surface);
	border-bottom: 1px solid var(--payment-table-separator, var(--cobudget-table-separator));
}
.balance-details dl { display: flex; flex-wrap: wrap; gap: 8px 24px; margin: 0; }
.balance-details dl > div { display: flex; flex-wrap: wrap; gap: 4px 8px; }
.balance-details dt,
.balance-details dd { margin: 0; padding: 0; width: auto; min-width: 0; text-align: start; white-space: normal; font-size: var(--font-size, 15px); color: var(--color-main-text); }
.balance-details dt { font-weight: var(--font-weight-element, 500); }
.balance-details dd { font-weight: var(--font-weight-default, normal); font-variant-numeric: tabular-nums; }
.balance-share-percent { color: var(--color-text-maxcontrast); white-space: nowrap; }
@media (max-width: 600px) {
	.member-balance-list, .member-balance-list tbody { display: block; }
	.member-balance-list thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
	.member-balance-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); height: auto !important; padding: 8px 12px; }
	.member-balance-row > td:first-child { grid-column: 1 / -1; padding-inline-start: 0; }
	.member-balance-row > td:last-child { padding-inline-end: 0; }
	.member-balance-list tbody .member-balance-row > td { border-bottom: 0; padding: 0; width: auto; }
	.member-balance-list tbody:not(:last-child) { border-bottom: 1px solid var(--payment-table-separator, var(--cobudget-table-separator)); }
	.member-balance-row .balance-amount::before { content: attr(data-label); display: block; margin-bottom: 4px; color: var(--color-main-text); font-size: var(--font-size, 15px); font-weight: var(--font-weight-element, 500); }
	.balance-details-row, .member-balance-list .balance-details { display: block; }
	.member-balance-list tbody .balance-details-row td { border-bottom: 0; padding: 0 12px 8px; }
}
</style>
