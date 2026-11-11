<template>
	<div ref="list" class="payment-label-list" :class="{ 'payment-label-list--collapsed': collapse, 'payment-label-list--summary': summary }">
		<TableTooltip v-if="summary && labels.length" :text="labels.map(label => label.text).join('\n')">
			<span class="payment-label-summary" :data-report-export-label="labels.map(label => label.text).join(', ')">
				<span class="payment-label payment-label--icon" :class="`badge-${labels[0].key}`" :data-cobudget-badge="labels[0].tone" data-payment-label>
					<NavigationIcon :name="labels[0].icon" :size="16" />
				</span>
				<span v-if="labels.length > 1" class="payment-label-count" aria-hidden="true">{{ labels.length }}</span>
			</span>
		</TableTooltip>
		<template v-else>
			<template v-for="label in visibleLabels" :key="label.key">
				<TableTooltip v-if="label.icon" :text="label.text">
					<span class="payment-label payment-label--icon" :class="`badge-${label.key}`" :data-cobudget-badge="label.tone" :data-report-export-label="label.text" data-payment-label>
						<NavigationIcon :name="label.icon" :size="16" />
					</span>
				</TableTooltip>
				<span v-else class="payment-label" :class="`badge-${label.key}`" data-cobudget-badge="neutral" data-payment-label>{{ label.text }}</span>
			</template>
			<TableTooltip v-if="hiddenLabels.length" :text="hiddenLabels.map(label => label.text).join('\n')">
				<span class="payment-label payment-label--more" data-cobudget-badge="neutral" :data-report-export-label="hiddenLabels.map(label => label.text).join(', ')" data-payment-label>+{{ hiddenLabels.length }}</span>
			</TableTooltip>
		</template>
	</div>
</template>

<script>
import NavigationIcon from './NavigationIcon.vue'
import TableTooltip from './TableTooltip.vue'
import { fitPaymentLabels } from '../utils/paymentColumnWidths'

export default {
	name: 'PaymentLabelList',
	components: { NavigationIcon, TableTooltip },
	props: {
		labels: { type: Array, required: true },
		collapse: { type: Boolean, default: false },
		descriptionPresent: { type: Boolean, default: false },
		statusCount: { type: Number, default: 0 },
		maxVisible: { type: Number, default: Infinity },
		summary: { type: Boolean, default: false },
	},
	data: () => ({ fittingCount: 0, labelResizeObserver: null, labelFrame: null, labelsMounted: false }),
	computed: {
		visibleCount() { return Math.max(0, Math.min(this.labels.length, this.maxVisible, this.collapse ? this.fittingCount : Infinity)) },
		visibleLabels() { return this.labels.slice(0, this.visibleCount) },
		hiddenLabels() { return this.labels.slice(this.visibleCount) },
	},
	watch: {
		labels: { handler() { this.scheduleFit() }, deep: true },
		descriptionPresent() { this.scheduleFit() },
		statusCount() { this.scheduleFit() },
	},
	mounted() {
		if (!this.collapse || typeof window === 'undefined') return
		this.labelsMounted = true
		if (typeof ResizeObserver !== 'undefined') {
			this.labelResizeObserver = new ResizeObserver(this.scheduleFit)
			this.labelResizeObserver.observe(this.$refs.list.parentElement)
		}
		window.addEventListener('resize', this.scheduleFit)
		document.fonts?.ready.then(() => this.scheduleFit())
		this.scheduleFit()
	},
	beforeUnmount() {
		this.labelsMounted = false
		this.labelResizeObserver?.disconnect()
		if (this.labelFrame !== null) window.cancelAnimationFrame(this.labelFrame)
		if (typeof window !== 'undefined') window.removeEventListener('resize', this.scheduleFit)
	},
	methods: {
		scheduleFit() {
			if (!this.labelsMounted || this.labelFrame !== null) return
			this.labelFrame = window.requestAnimationFrame(() => {
				this.labelFrame = null
				this.fitLabels()
			})
		},
		fitLabels() {
			const list = this.$refs.list, row = list?.parentElement
			if (!row?.clientWidth) return
			const style = window.getComputedStyle(list)
			const context = document.createElement('canvas').getContext('2d')
			if (!context) return
			context.font = `500 ${style.fontSize} ${style.fontFamily}`
			// Chips use 8 px padding per side and a 1 px border, shared in status.css.
			const measure = text => Math.ceil(context.measureText(text).width) + 18
			const statusWidth = row.querySelector('.desktop-status-labels')?.getBoundingClientRect().width || 0
			const remaining = Math.max(0, row.clientWidth - statusWidth - (statusWidth ? 8 : 0) - (this.descriptionPresent ? 8 : 0))
			const budget = this.descriptionPresent ? Math.min(remaining, Math.max(32, row.clientWidth * 0.45)) : remaining
			this.fittingCount = fitPaymentLabels(this.labels.map(label => label.icon ? 24 : measure(label.text)), budget, count => measure(`+${count}`), parseFloat(style.columnGap) || 0)
		},
	},
}
</script>

<style scoped>
.payment-label-list {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline, 4px);
	min-width: 0;
	font-size: var(--cobudget-font-caption, 12px);
	font-weight: 500;
}
.payment-label-list--collapsed { flex-wrap: nowrap; flex: 0 0 auto; }
.payment-label-list :deep(.table-tooltip-trigger) { flex: 0 0 auto; }
.payment-label-list--summary { flex-wrap: nowrap; }
.payment-label-summary { position: relative; display: inline-flex; margin-inline-end: 6px; }

</style>
