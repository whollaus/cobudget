<template>
	<AnnualOverviewTable ref="table" class="report-area-table" section="areas" :title="$texts.analytics.areas()" :print-title="exportTitle" :first-heading="$texts.analytics.area()" :export-disabled="loading || error"
		:columns="columns" :column-titles="columnTitles" :rows="rows" :total-row="totalRow" :total-interactive="false" area-order
		:selection="selection" :periods="report.periods" :detail-location-resolver="detailLocation" :scroll-navigation="scrollNavigation">
		<template #controls>
			<div class="report-area-controls no-print">
				<ReportSelect v-if="$enableIncomes !== false" :model-value="effectiveMetric" :label="$texts.annual.areaMetric()"
				:options="[{ value: 'expense', label: $texts.annual.expenses() }, { value: 'income', label: $texts.annual.income() }, { value: 'balance', label: $texts.analytics.balance() }]" @update:model-value="$emit('update:metric', $event)" />
				<ReportSelect :model-value="amountView" :label="$texts.annual.amountScope()"
				:options="[{ value: 'personal', label: $texts.annual.personalShare() }, { value: 'area', label: $texts.annual.areaTotals() }]" @update:model-value="$emit('update:amountView', $event)" />
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
import ReportSelect from './ReportSelect.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import AnnualOverviewTable from './AnnualOverviewTable.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualRequestParams, annualRouteQuery, reportDate } from '../services/annualOverview'
import { areaOverviewQuery } from '../services/reportDetails'
import { sortAreasByName } from '../utils/areaSorting'

export default {
	name: 'ReportAreaOverview',
	components: { ReportSelect, NcButton, AnnualOverviewTable },
	props: {
		selection: { type: Object, required: true }, report: { type: Object, required: true }, columns: { type: Array, required: true }, columnTitles: { type: Array, default: () => [] },
		amountView: { type: String, default: 'personal' }, metric: { type: String, default: 'expense' },
		scrollNavigation: { type: Object, default: null },
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

</style>
