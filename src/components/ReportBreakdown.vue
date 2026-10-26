<template>
	<ReportDetailSection v-if="loading || error || dimensions.length" class="report-breakdown" section-key="detail-breakdown" :title="activeDimension.tabLabel || activeDimension.label || $texts.annual.detailBreakdown()" :export-data="exportData" :export-disabled="loading || error">
		<template #heading="{ bodyId, expand }">
			<h3 v-if="dimensions.length" class="report-breakdown-tabs" role="tablist" :aria-label="$texts.annual.detailBreakdown()" @keydown="onTabKey($event, expand)">
				<NcButton v-for="item in dimensions" :id="`${bodyId}-${item.key}`" :key="item.key" variant="tertiary" alignment="start" role="tab" class="report-breakdown-tab"
					:class="{ 'is-selected': activeDimension.key === item.key }" :aria-selected="activeDimension.key === item.key" :tabindex="activeDimension.key === item.key ? 0 : -1"
					:aria-controls="`${bodyId}-table`" :disabled="loading || error" @click="chooseDimension(item.key, expand)">{{ item.tabLabel || item.label }}</NcButton>
			</h3>
			<h3 v-else>{{ $texts.annual.detailBreakdown() }}</h3>
		</template>
		<template #controls="{ open }">
			<label v-if="availableTypes.length > 1" v-show="open" class="report-breakdown-select report-breakdown-type">
				<span aria-hidden="true">{{ type === 'income' ? $texts.annual.income() : $texts.annual.expenses() }}<ChevronDownIcon :size="18" /></span>
				<select :value="type" :aria-label="$texts.filters.incomeAndExpense()" :disabled="loading || error" @change="selectedType = $event.target.value">
					<option v-for="item in availableTypes" :key="item" :value="item">{{ item === 'income' ? $texts.annual.income() : $texts.annual.expenses() }}</option>
				</select>
			</label>
		</template>
		<template #default="{ bodyId }">
			<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
			<div v-else-if="error" role="alert"><p>{{ $texts.annual.breakdownError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
			<template v-else>
				<template v-if="rows.length">
					<div :id="`${bodyId}-table`" class="report-breakdown-scroll" tabindex="0" role="tabpanel" :aria-labelledby="`${bodyId}-${activeDimension.key}`">
						<table class="report-breakdown-table" :aria-label="activeDimension.label">
							<thead><tr>
								<th scope="col" class="sortable" :aria-sort="sortAria('name')"><button type="button" @click="sort('name')">{{ activeDimension.label }}<span v-if="sortBy === 'name'" class="sort-icon" aria-hidden="true">{{ sortIcon }}</span></button></th>
								<th scope="col" class="count sortable" :aria-sort="sortAria('count')"><button type="button" @click="sort('count')">{{ $texts.analytics.bookingsColumn() }}<span v-if="sortBy === 'count'" class="sort-icon" aria-hidden="true">{{ sortIcon }}</span></button></th>
								<th scope="col" class="numeric">{{ $texts.analytics.share() }}</th>
								<th scope="col" class="numeric amount sortable" :aria-sort="sortAria('amountCents')"><button type="button" @click="sort('amountCents')">{{ $texts.entry.tableAmount($currency) }}<span v-if="sortBy === 'amountCents'" class="sort-icon" aria-hidden="true">{{ sortIcon }}</span></button></th>
							</tr></thead>
							<tbody><tr v-for="row in rows" :key="row.id" @click="openRow(row, $event)">
								<th scope="row"><RouterLink :to="rowRoute(row)">{{ row.label }}</RouterLink></th>
								<td class="count">{{ count(row.count) }}</td>
								<td class="numeric">{{ share(row.amountCents) }}</td>
								<td class="numeric amount"><RouterLink :to="rowRoute(row)">{{ formatAmount(row.amountCents) }}</RouterLink></td>
							</tr></tbody>
							<tfoot><tr><th scope="row">{{ $texts.analytics.total() }}</th><td class="count">{{ count(total.count) }}</td><td class="numeric">{{ share(total.amountCents) }}</td><td class="numeric amount" :class="{ 'is-negative': signedCents(total.amountCents) < 0 }">{{ formatAmount(total.amountCents) }}</td></tr></tfoot>
						</table>
					</div>
					<p v-if="overlapping" class="report-breakdown-note">{{ $texts.annual.breakdownOverlap() }}</p>
				</template>
			</template>
		</template>
	</ReportDetailSection>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ReportDetailSection from './ReportDetailSection.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualRequestParams, reportDate } from '../services/annualOverview'
import { getAppLocale } from '../utils/formatMoney'
import { reportExportMoney, reportDisplayedTableExport, reportExportNotReady } from '../services/reportExport'
import { RouterLink } from 'vue-router'
import { reportBreakdownRoute, reportBreakdownLabel } from '../services/reportDetails'

export default {
	name: 'ReportBreakdown',
	components: { NcButton, ChevronDownIcon, ReportDetailSection, RouterLink },
	props: { detail: { type: Object, required: true }, selection: { type: Object, required: true }, report: { type: Object, required: true } },
	data: () => ({ result: null, loading: true, error: false, request: 0, dimension: '', selectedType: 'expense', sortBy: 'amountCents', sortDir: 'desc' }),
	computed: {
		params() {
			const period = this.report.periods[this.detail.index]
			const params = annualRequestParams(this.selection)
			for (const key of ['year', 'month', 'period']) delete params[key]
			return { ...params, dateFrom: reportDate(new Date(period.dateFrom * 1000)), dateTo: reportDate(new Date(period.dateTo * 1000)),
				detailType: this.detail.row.type, detailId: this.detail.total ? 'total' : String(this.detail.row.id), detailDirect: Boolean(this.detail.row.direct) }
		},
		requestContext() { return { params: this.params, report: this.report } },
		availableTypes() { return (this.detail.row.type === 'all' ? (this.$enableIncomes === false ? ['expense'] : ['expense', 'income']) : [this.detail.row.type]).filter(type => this.dimensionChoices(type).length) },
		type() { return this.detail.row.type === 'all' ? (this.availableTypes.includes(this.selectedType) ? this.selectedType : this.availableTypes[0] || 'expense') : this.detail.row.type },
		total() { return this.result?.totals?.[this.type] || { amountCents: 0, count: 0 } },
		dimensions() { return this.dimensionChoices(this.type) },
		activeDimension() { return this.dimensions.find(item => item.key === this.dimension) || ['categories', 'paymentPartners', 'labels', 'hashtags', 'projects'].map(key => this.dimensions.find(item => item.key === key)).find(Boolean) || { key: '', label: '' } },
		rows() {
			const dimension = this.activeDimension.key
			const collator = new Intl.Collator(getAppLocale(), { numeric: true, sensitivity: 'base' })
			return (this.result?.groups?.[dimension]?.[this.type] || []).map(row => ({ ...row, label: this.rowLabel(row, dimension) })).sort((a, b) => {
				const difference = this.sortBy === 'name' ? collator.compare(a.label, b.label) : a[this.sortBy] - b[this.sortBy]
				return difference * (this.sortDir === 'asc' ? 1 : -1) || collator.compare(a.label, b.label)
			})
		},
		overlapping() { return Boolean(this.result?.overlaps?.[this.activeDimension.key]?.[this.type]) },
		sortIcon() { return this.sortDir === 'asc' ? '↑' : '↓' },
	},
	watch: { requestContext: { handler: 'load', immediate: true } },
	mounted() { window.addEventListener('entry-saved', this.load) },
	beforeUnmount() { this.request++; window.removeEventListener('entry-saved', this.load) },
	methods: {
		chooseDimension(key, expand) {
			if (!this.dimensions.some(item => item.key === key)) return
			this.dimension = key
			expand?.()
		},
		onTabKey(event, expand) {
			if (event.altKey || event.ctrlKey || event.metaKey || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key) || !this.dimensions.length) return
			event.preventDefault()
			const tabs = [...event.currentTarget.querySelectorAll('[role="tab"]')]
			const current = this.dimensions.findIndex(item => item.key === this.activeDimension.key)
			const index = event.key === 'Home' ? 0 : event.key === 'End' ? this.dimensions.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + this.dimensions.length) % this.dimensions.length
			this.chooseDimension(this.dimensions[index].key, expand)
			this.$nextTick(() => tabs[index]?.focus({ preventScroll: true }))
		},
		dimensionChoices(type) {
			const subject = this.detail.total ? '' : this.selection.grouping === 'categories' ? 'categories' : this.selection.grouping === 'paymentPartners' ? 'paymentPartners' : String(this.detail.row.id).startsWith('label:') ? 'labels' : 'hashtags'
			return [
				{ key: 'paymentPartners', label: this.$texts.entry.tablePaymentPartner(), tabLabel: this.$texts.analytics.paymentPartners() },
				{ key: 'categories', label: this.$texts.analytics.categories() },
				{ key: 'projects', label: this.$texts.analytics.areas() },
				{ key: 'labels', label: this.$texts.analytics.labels() },
				{ key: 'hashtags', label: this.$texts.analytics.hashtags(), tabLabel: this.$texts.annual.tags() },
			].filter(item => {
				const rows = this.result?.groups?.[item.key]?.[type] || []
				if (item.key === subject || this.detail.scopeDimensions?.includes(item.key) || !rows.length) return false
				if (['labels', 'hashtags'].includes(item.key) && !rows.some(row => row.id !== '' && row.id !== 0)) return false
				if (item.key === 'projects' && (this.$enableProjects === false || this.selection.projectId || rows.length < 2)) return false
				return true
			})
		},
		exportData({ display = false } = {}) {
			if (this.loading || this.error) throw reportExportNotReady()
			if (!this.dimensions.length) return []
			if (display) return reportDisplayedTableExport(this.$el.querySelector('.report-breakdown-table'), this.$texts.annual.detailBreakdown())
			const row = value => [value.label, value.count, this.share(value.amountCents), reportExportMoney(this.signedCents(value.amountCents))]
			return [{ title: `${this.$texts.annual.detailBreakdown()} · ${this.type === 'income' ? this.$texts.annual.income() : this.$texts.annual.expenses()} · ${this.activeDimension.label}`,
				columns: [this.activeDimension.label, this.$texts.analytics.bookingsColumn(), this.$texts.analytics.share(), this.$texts.entry.tableAmount(this.$currency)],
				rows: [...this.rows.map(row), row({ ...this.total, label: this.$texts.analytics.total() })] }]
		},
		async load() {
			const request = ++this.request
			this.loading = true; this.error = false
			if (!this.result && this.detail?.breakdown) { this.result = this.detail.breakdown; this.loading = false; return }
			try {
				const { data } = await axios.get(generateUrl('/apps/cobudget/api/report-breakdown'), { params: this.params })
				if (request === this.request) this.result = data
			} catch (error) { if (request === this.request) this.error = true }
			finally { if (request === this.request) this.loading = false }
		},
		rowLabel(row, dimension) { return reportBreakdownLabel(row, dimension, this.$texts) },
		signedCents(cents) { return this.type === 'expense' ? -cents : cents },
		formatAmount(cents) { return this.$formatSignedMoney(this.signedCents(cents) / 100) },
		rowRoute(row) { return reportBreakdownRoute(this.$route?.query || {}, this.activeDimension.key, row, this.type) },
		openRow(row, event) {
			if (event.target.closest('a, button')) return
			const route = this.rowRoute(row)
			if (route) this.$router.push(route)
		},
		share(cents) { return this.total.amountCents === 0 ? '–' : new Intl.NumberFormat(getAppLocale(), { style: 'percent', maximumFractionDigits: 1 }).format(cents / this.total.amountCents) },
		count(value) { return new Intl.NumberFormat(getAppLocale()).format(value) },
		sort(column) { this.sortDir = this.sortBy === column && this.sortDir === 'desc' ? 'asc' : column === 'name' && this.sortBy !== column ? 'asc' : 'desc'; this.sortBy = column },
		sortAria(column) { return this.sortBy === column ? this.sortDir === 'asc' ? 'ascending' : 'descending' : 'none' },
	},
}
</script>

<style scoped>
.report-breakdown-tabs { display: contents; margin: 0; font-size: var(--cobudget-font-lg, 18px); font-weight: 600; }
.report-breakdown .report-breakdown-tab { min-height: 0; height: auto; margin: 0; padding: 2px 4px; border: 0; font: inherit; color: var(--cobudget-text-muted); --font-weight-element: 400; }
.report-breakdown .report-breakdown-tab:not(:last-child) { margin-inline-end: calc(var(--default-grid-baseline, 4px) * 2); }
.report-breakdown .report-breakdown-tab.is-selected { color: var(--cobudget-text); --font-weight-element: 600; }
.report-breakdown-tab :deep(.button-vue__text) { padding: 0; padding-inline: 0 !important; white-space: normal; }
.report-breakdown-type { margin-inline-start: auto; }
.report-breakdown-select { position: relative; display: inline-flex; max-width: 100%; min-width: 0; border-radius: var(--border-radius-element, 8px); color: var(--cobudget-text-muted); font-weight: 400; }
.report-breakdown-select > span { display: inline-flex; align-items: center; gap: var(--default-grid-baseline, 4px); min-height: 36px; padding-inline: calc(var(--default-grid-baseline, 4px) * 2); }
.report-breakdown-select:hover { background: var(--cobudget-surface-muted); }
.report-breakdown-select:focus-within { outline: var(--cobudget-focus-outline); outline-offset: -2px; }
.report-breakdown-select select { position: absolute; inset: 0; width: 100%; height: 100%; min-height: 0; margin: 0; opacity: 0; cursor: pointer; }
.report-breakdown-scroll { overflow-x: auto; border-radius: var(--cobudget-table-radius); }
.report-breakdown-scroll:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: 2px; }
.report-breakdown-table { width: 100%; margin: 0; border-collapse: separate; border-spacing: 0; border: 1px solid var(--cobudget-border, #ddd); border-radius: var(--cobudget-table-radius); font-size: var(--cobudget-font-sm); }
.report-breakdown-table th, .report-breakdown-table td { box-sizing: border-box; min-width: 0; padding: var(--cobudget-table-cell-padding); height: auto; border-bottom: 1px solid var(--cobudget-border, #ddd); color: var(--cobudget-text, #222); background: var(--cobudget-surface, #fff); font-size: var(--cobudget-font-sm); font-weight: 400; text-align: start; vertical-align: middle; }
.report-breakdown-table th:first-child { min-width: 120px; }
.report-breakdown-table tbody th { white-space: normal; overflow-wrap: anywhere; }
.report-breakdown-table thead th { color: var(--cobudget-text-muted, #888); letter-spacing: 0.5px; white-space: nowrap; background: var(--cobudget-table-header-background); }
.report-breakdown-table thead th:first-child { border-start-start-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-breakdown-table thead th:last-child { border-start-end-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-breakdown-table .numeric, .report-breakdown-table .count { width: 156px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.report-breakdown-table .numeric { text-align: end; }
.report-breakdown-table .count { text-align: center; }
.report-breakdown-table tbody .amount { font-weight: 600; }
.report-breakdown-table .sortable { cursor: pointer; user-select: none; }
.report-breakdown-table .sortable:hover { background: var(--cobudget-table-sort-hover); }
.report-breakdown-table .sort-icon { display: inline-block; margin-inline-start: 4px; font-size: var(--cobudget-font-sm); }
.report-breakdown-table th button { display: block; width: 100%; min-width: 0; min-height: 0; height: auto; margin: 0; padding: 0; border: 0; border-radius: 0; background: transparent; color: inherit; font: inherit; letter-spacing: inherit; text-align: inherit; white-space: inherit; cursor: pointer; }
.report-breakdown-table th button:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: 2px; }
.report-breakdown-table tbody tr:hover th, .report-breakdown-table tbody tr:hover td { background: var(--cobudget-table-row-hover); }
.report-breakdown-table tbody tr, .report-breakdown-table tbody th, .report-breakdown-table tbody td, .report-breakdown-table tbody a { cursor: pointer; }
.report-breakdown-table tbody a { color: inherit; text-decoration: none; }
.report-breakdown-table tbody a:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: 2px; }
.report-breakdown-table tfoot th, .report-breakdown-table tfoot td { border-bottom: 0; font-weight: 600; background: var(--cobudget-surface-muted); }
.report-breakdown-table tfoot .is-negative { color: var(--cobudget-error); }
.report-breakdown-table tfoot th:first-child { border-end-start-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-breakdown-table tfoot td:last-child { border-end-end-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.report-breakdown-note { margin-block: calc(var(--default-grid-baseline, 4px) * 2) 0; color: var(--cobudget-text-muted); font-size: var(--cobudget-font-sm); }
@media (max-width: 768px) {
	/* Mobile reports meet the viewport edge, including individual corner cells. */
	.report-breakdown { --cobudget-table-radius: 0px; } .report-breakdown-select > span, .report-breakdown .report-breakdown-tab { min-height: 44px; } }
</style>
