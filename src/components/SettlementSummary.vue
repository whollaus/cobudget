<template>
	<dl class="settlement-summary">
		<dt>{{ $texts.settlements.area() }}:</dt>
		<dd>{{ areaName }}</dd>
		<dt>{{ $texts.settlements.author() }}:</dt>
		<dd><MemberIdentity :user-id="createdBy" :display-name="createdByDisplayName" /></dd>
		<dt>{{ $texts.settlements.paymentCount() }}:</dt>
		<dd>{{ paymentCount }}</dd>
		<dt>{{ $texts.settlements.total() }}:</dt>
		<dd class="settlement-summary-total">{{ total }}</dd>
	</dl>
</template>

<script>
import MemberIdentity from './MemberIdentity.vue'

export default {
	name: 'SettlementSummary',
	components: { MemberIdentity },
	props: {
		areaName: { type: String, required: true },
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
	display: grid;
	grid-template-columns: minmax(0, max-content) minmax(0, 1fr);
	align-items: center;
	gap: 8px 16px;
	margin: 0;
	font-size: var(--font-size, 15px);
	font-weight: normal;
}
.settlement-summary dt,
.settlement-summary dd {
	margin: 0;
	padding: 0;
	width: auto;
	min-width: 0;
	text-align: start;
	white-space: normal;
	overflow-wrap: anywhere;
}
.settlement-summary dt {
	color: var(--color-main-text);
	font-weight: var(--font-weight-element, 500);
}
.settlement-summary dd { color: var(--color-main-text); }
.settlement-summary-total { font-variant-numeric: tabular-nums; }
</style>
