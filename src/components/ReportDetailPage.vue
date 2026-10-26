<template>
	<div class="report-detail-page">
		<KeepAlive :max="8">
			<ReportDetailView v-if="activeDetail && !loading && !error" :key="pageKey" v-bind="$attrs" :detail="activeDetail" :report="activeReport" :selection="activeSelection" />
		</KeepAlive>
		<div v-if="loading || error || !activeDetail" class="report-detail-status">
			<NcButton variant="tertiary" @click="$attrs.onBack?.()">{{ $texts.annual.back() }}</NcButton>
			<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
			<div v-else-if="error" role="alert"><p>{{ $texts.annual.loadError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
			<p v-else role="status">{{ $texts.annual.detailUnavailable() }}</p>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ReportDetailView from './ReportDetailView.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualSelection, annualRequestParams, annualPaymentRoute, reportDate } from '../services/annualOverview'
import { reportDrilldownPath, reportDrilldownFilters, reportDrilldownLabels } from '../services/reportDetails'

export default {
	name: 'ReportDetailPage',
	components: { NcButton, ReportDetailView },
	inheritAttrs: false,
	props: { detail: { type: Object, required: true }, report: { type: Object, required: true }, selection: { type: Object, required: true } },
	data: () => ({ nestedReport: null, breakdown: null, loading: false, error: false, request: 0, pages: new Map(), positions: new Map(), sourceReport: null, previousKey: null }),
	computed: {
		// Pagination and sorting also replace route.query; keep the selection
		// identity stable so they do not reset an open detail's chart or tabs.
		pathText() { return this.$route.query.detailPath },
		path() { return reportDrilldownPath({ detailPath: this.pathText }) },
		filters() { return reportDrilldownFilters(this.detail, this.selection, this.path) },
		pageKey() { return JSON.stringify([this.detail.row.type, this.detail.row.id, this.detail.index, this.path]) },
		activeSelection() { return this.path?.length && this.filters ? annualSelection({ ...this.selection, reportFilters: JSON.stringify(this.filters) }) : this.selection },
		activeReport() { return this.path?.length ? this.nestedReport : this.report },
		activeDetail() {
			if (!this.path) return null
			if (!this.path.length) return this.detail
			if (!this.filters || !this.nestedReport || !this.breakdown) return null
			const labels = reportDrilldownLabels(this.breakdown, this.path, this.$texts)
			const period = this.nestedReport.periods[this.detail.index]
			if (!labels || !period) return null
			const type = this.path.at(-1).type
			const label = [this.detail.label, ...labels].join(' → ')
			return { total: true, index: this.detail.index, label, row: { ...this.nestedReport.totals[type], type, label },
				scopeDimensions: this.filters.map(filter => filter.dimension), breakdown: this.breakdown,
				route: annualPaymentRoute(this.activeSelection, period, type) }
		},
		requestContext() { return { path: this.path, detail: this.detail, report: this.report, selection: this.selection } },
	},
	watch: { requestContext: { handler: 'load', immediate: true } },
	beforeUnmount() { this.request++ },
	methods: {
		async load() {
			const request = ++this.request
			const scroll = this.$el?.closest?.('.app-content')
			if (scroll && this.previousKey !== null) this.positions.set(this.previousKey, scroll.scrollTop)
			this.previousKey = this.pageKey
			if (this.sourceReport !== this.report) { this.pages.clear(); this.sourceReport = this.report }
			this.nestedReport = null; this.breakdown = null; this.loading = false; this.error = false
			if (this.path?.length && this.filters) {
				const key = JSON.stringify([this.detail.index, annualRequestParams(this.activeSelection)])
				const cached = this.pages.get(key)
				if (cached) { this.nestedReport = cached.report; this.breakdown = cached.breakdown }
				else {
					this.loading = true
					const period = this.report.periods[this.detail.index]
					const params = annualRequestParams(this.activeSelection)
					const breakdownParams = { ...params, dateFrom: reportDate(new Date(period.dateFrom * 1000)), dateTo: reportDate(new Date(period.dateTo * 1000)), detailType: this.path.at(-1).type, detailId: 'total' }
					for (const field of ['year', 'period', 'month']) delete breakdownParams[field]
					try {
						const [report, breakdown] = await Promise.all([
							axios.get(generateUrl('/apps/cobudget/api/annual-overview'), { params }),
							axios.get(generateUrl('/apps/cobudget/api/report-breakdown'), { params: breakdownParams }),
						])
						if (request !== this.request) return
						this.nestedReport = report.data; this.breakdown = breakdown.data
						if (this.pages.size >= 8) this.pages.delete(this.pages.keys().next().value)
						this.pages.set(key, { report: report.data, breakdown: breakdown.data })
					} catch (error) { if (request === this.request) this.error = true }
					finally { if (request === this.request) this.loading = false }
				}
			}
			await this.$nextTick()
			if (request === this.request && scroll) scroll.scrollTop = this.positions.get(this.pageKey) || 0
		},
	},
}
</script>
