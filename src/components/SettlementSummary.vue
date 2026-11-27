<template>
	<table class="settlement-summary">
		<tbody>
			<tr>
				<th scope="row">{{ $texts.settlements.area() }}</th>
				<td>{{ areaName }}</td>
			</tr>
			<tr v-if="settlementDate">
				<th scope="row">{{ $texts.settlements.date() }}</th>
				<td>{{ settlementDate }}</td>
			</tr>
			<tr>
				<th scope="row">{{ $texts.settlements.author() }}</th>
				<td><MemberIdentity :user-id="createdBy" :display-name="createdByDisplayName" /></td>
			</tr>
			<tr>
				<th scope="row">{{ $texts.settlements.paymentCount() }}</th>
				<td>{{ paymentCount }}</td>
			</tr>
			<tr>
				<th scope="row">{{ $texts.settlements.total() }}</th>
				<td class="settlement-summary-total">{{ total }}</td>
			</tr>
		</tbody>
	</table>
</template>

<script>
import MemberIdentity from './MemberIdentity.vue'

export default {
	name: 'SettlementSummary',
	components: { MemberIdentity },
	props: {
		areaName: { type: String, required: true },
		settlementDate: { type: String, default: '' },
		createdBy: { type: String, default: '' },
		createdByDisplayName: { type: String, default: '' },
		paymentCount: { type: Number, required: true },
		// Formatted by the caller to retain a saved settlement's currency.
		total: { type: String, required: true },
	},
}
</script>

<style scoped>
.settlement-summary {
	width: 100%;
	margin: 0;
	table-layout: fixed;
	border-collapse: separate;
	border-spacing: 0;
	border: 0;
}

.settlement-summary :is(th, td) {
	box-sizing: border-box;
	padding: var(--cobudget-table-payment-padding);
	padding-block: var(--cobudget-space-2, 8px);
	vertical-align: middle;
	font-size: var(--cobudget-font-base);
	font-weight: 400;
	letter-spacing: normal;
	text-align: start;
	white-space: normal;
	overflow-wrap: anywhere;
}

.settlement-summary tr:not(:last-child) > :is(th, td) {
	border-block-end: 1px solid var(--payment-table-separator, var(--cobudget-table-separator));
}

.settlement-summary th {
	width: 40%;
	border-inline-end: 1px solid var(--payment-table-separator, var(--cobudget-table-separator));
	background: var(--cobudget-table-header-background, var(--cobudget-surface));
	color: var(--cobudget-text-muted);
}

.settlement-summary td {
	background: var(--cobudget-surface);
	color: var(--cobudget-text);
}

.settlement-summary-total { font-variant-numeric: tabular-nums; }
</style>
