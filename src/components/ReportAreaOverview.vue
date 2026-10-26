<template>
	<AnnualOverviewTable ref="table" class="report-area-table" section="areas" :title="$texts.analytics.areas()" :print-title="exportTitle" :first-heading="$texts.analytics.area()" :export-disabled="loading || error"
		:columns="columns" :column-titles="columnTitles" :rows="rows" :total-row="totalRow" :total-interactive="false" area-order
		:selection="selection" :periods="report.periods" :detail-location-resolver="detailLocation">
		<template #controls>
			<div class="report-area-controls no-print">
				<label v-if="$enableIncomes !== false" class="report-area-select">
					<span aria-hidden="true">{{ metricLabel }}<ChevronDownIcon :size="18" /></span>
					<select :value="effectiveMetric" :aria-label="$texts.annual.areaMetric()" @change="$emit('update:metric', $event.target.value)">
						<option value="expense">{{ $texts.annual.expenses() }}</option><option value="income">{{ $texts.annual.income() }}</option><option value="balance">{{ $texts.analytics.balance() }}</option>
					</select>
				</label>
				<label class="report-area-select">
					<span aria-hidden="true">{{ amountLabel }}<ChevronDownIcon :size="18" /></span>
					<select :value="amountView" :aria-label="$texts.annual.amountScope()" @change="$emit('update:amountView', $event.target.value)">
						<option value="personal">{{ $texts.annual.personalShare() }}</option><option value="area">{{ $texts.annual.areaTotals() }}</option>
					</select>
				</label>
			</div>
		</template>
		<template #empty>
			<span v-if="loading" role="status">{{ $texts.common.loading() }}</span>
			<div v-else-if="error" role="alert">{{ $texts.annual.areaOverviewError() }} <NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
			<span v-else>{{ $texts.common.noFilteredEntries() }}</span>
		</template>
	</AnnualOverviewTable>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import AnnualOverviewTable from './AnnualOverviewTable.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualRequestParams, annualRouteQuery, reportDate } from '../services/annualOverview'
import { areaOverviewQuery } from '../services/reportDetails'
import { sortAreasByName } from '../utils/areaSorting'

export default {
	name: 'ReportAreaOverview',
	components: { NcButton, ChevronDownIcon, AnnualOverviewTable },
	props: {
		selection: { type: Object, required: true }, report: { type: Object, required: true }, columns: { type: Array, required: true }, columnTitles: { type: Array, default: () => [] },
		amountView: { type: String, default: 'personal' }, metric: { type: String, default: 'expense' },
	},
	emits: ['update:amountView', 'update:metric'],
	data: () => ({ result: null, loading: true, error: false, request: 0 }),
	computed: {
		effectiveMetric() { return this.$enableIncomes === false ? 'expense' : this.metric },
		rowType() { return this.effectiveMetric === 'balance' ? 'all' : this.effectiveMetric },
		metricLabel() { return this.effectiveMetric === 'income' ? this.$texts.annual.income() : this.effectiveMetric === 'balance' ? this.$texts.analytics.balance() : this.$texts.annual.expenses() },
		amountLabel() { return this.amountView === 'area' ? this.$texts.annual.areaTotals() : this.$texts.annual.personalShare() },
		exportTitle() { return `${this.$texts.analytics.areas()} · ${this.metricLabel} · ${this.amountLabel}` },
		params() {
			const range = this.report.periods.at(-1)
			const params = annualRequestParams(this.selection)
			for (const key of ['year', 'month', 'projectId', 'entryScope', 'withoutArea']) delete params[key]
			return { ...params, period: this.selection.period, dateFrom: reportDate(new Date(range.dateFrom * 1000)), dateTo: reportDate(new Date(range.dateTo * 1000)), amountView: this.amountView }
		},
		requestContext() { return { params: this.params, report: this.report } },
		rows() {
			if (this.loading || this.error) return []
			return (this.result?.rows || []).filter(row => row[this.effectiveMetric].totalCount > 0).map(row => ({
				...row, ...row[this.effectiveMetric], key: `area-${row.id}`, id: row.id, type: this.rowType,
				label: row.id === 0 ? this.$texts.annual.withoutArea() : row.name,
			}))
		},
		totalRow() {
			const total = this.result?.totals?.[this.effectiveMetric]
			return !this.loading && !this.error && total?.totalCount > 0 ? { ...total, type: this.rowType, label: `${this.$texts.analytics.total()} · ${this.metricLabel} · ${this.amountLabel}` } : null
		},
	},
	watch: { requestContext: { handler: 'load', immediate: true } },
	beforeUnmount() { this.request++ },
	methods: {
		exportData(options) { return this.$refs.table.exportData(options) },
		exportSection() {
			if (this.loading || this.error || !this.result) return null
			const rows = sortAreasByName(this.rows).map(row => ({ label: row.label, amounts: [...row.months, row.total], counts: row.counts, balance: this.rowType === 'all' }))
			if (this.totalRow) rows.push({ label: this.totalRow.label, amounts: [...this.totalRow.months, this.totalRow.total], counts: this.totalRow.counts, balance: this.rowType === 'all' })
			return { title: this.exportTitle, rows }
		},
		async load() {
			const request = ++this.request
			this.loading = true; this.error = false
			try {
				const { data } = await axios.get(generateUrl('/apps/cobudget/api/area-overview'), { params: this.params })
				if (request === this.request) this.result = data
			} catch (error) { if (request === this.request) this.error = true }
			finally { if (request === this.request) this.loading = false }
		},
		detailLocation(row, index) {
			return { name: 'annual-overview', query: {
				...annualRouteQuery(this.selection), ...areaOverviewQuery(this.amountView, this.effectiveMetric),
				detailArea: String(row.id), detailAreaScope: row.entryScope, detailType: this.rowType, detailId: 'total',
				detailPeriod: index === this.report.periods.length - 1 ? 'total' : String(index),
				...(row.entryScope === 'shared' && row.filterHashtags?.length ? { detailAreaTags: row.filterHashtags.join(',') } : {}),
			} }
		},
	},
}
</script>

<style scoped>
.report-area-controls { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: var(--default-grid-baseline, 4px); max-width: 100%; font-size: var(--cobudget-font-base); font-weight: 400; }
.report-area-select { position: relative; display: inline-flex; min-width: 0; max-width: 100%; border-radius: var(--border-radius-element, 8px); color: var(--cobudget-text-muted); font-weight: 400; }
.report-area-select > span { display: flex; align-items: center; gap: 4px; min-height: 36px; padding-inline: 8px; white-space: nowrap; }
.report-area-select:hover { background: var(--cobudget-surface-muted); }
.report-area-select:focus-within { outline: var(--cobudget-focus-outline); outline-offset: -2px; }
.report-area-select select { position: absolute; inset: 0; width: 100%; height: 100%; min-height: 0; margin: 0; opacity: 0; cursor: pointer; }
@media (max-width: 768px) { .report-area-select > span { min-height: 44px; } }
</style>
