<template>
	<ReportDetailView v-if="detail && !loading && !error" :key="detailKey" :detail="detail" :report="report" :selection="areaSelection" :scope-label="scopeLabel"
		:export-metadata="exportMetadata"
		:projects="projects" :labels="labels" :label-titles="labelTitles" :axis-labels="axisLabels" :selected-entry-id="selectedEntryId"
		@back="$emit('back')" @open-entry-sidebar="$emit('open-entry-sidebar', $event)" @selected-entry-missing="$emit('selected-entry-missing', $event)" />
	<div v-else class="report-area-detail-status">
		<NcButton variant="tertiary" @click="$emit('back')">{{ $texts.annual.back() }}</NcButton>
		<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
		<div v-else-if="error" role="alert"><p>{{ $texts.annual.loadError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
		<p v-else>{{ $texts.annual.detailUnavailable() }}</p>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ReportDetailView from './ReportDetailPage.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualSelection, annualRequestParams, annualPaymentRoute } from '../services/annualOverview'

export default {
	name: 'ReportAreaDetailView',
	components: { NcButton, ReportDetailView },
	props: {
		exportMetadata: { type: Array, default: () => [] },
		query: { type: Object, required: true }, selection: { type: Object, required: true }, projects: { type: Array, default: () => [] }, selectedEntryId: { type: [Number, String], default: null },
		labels: { type: Array, required: true }, labelTitles: { type: Array, required: true }, axisLabels: { type: Array, required: true },
	},
	emits: ['back', 'open-entry-sidebar', 'selected-entry-missing'],
	data: () => ({ report: null, loading: true, error: false, request: 0 }),
	computed: {
		areaId() { return /^(0|[1-9]\d*)$/.test(String(this.query.detailArea)) && Number.isSafeInteger(Number(this.query.detailArea)) ? Number(this.query.detailArea) : null },
		scopeIdentity() { return JSON.stringify({ scope: this.query.detailAreaScope, tags: this.query.detailAreaTags }) },
		detailIdentity() { return JSON.stringify({ type: this.query.detailType, id: this.query.detailId, period: this.query.detailPeriod }) },
		areaSelection() {
			const { scope, tags } = JSON.parse(this.scopeIdentity)
			return annualSelection({ ...this.selection, projectId: this.areaId || null, withoutArea: this.areaId === 0, entryScope: scope === 'shared' ? 'shared' : 'personal',
				...(this.areaId > 0 && scope === 'shared' && tags ? { hashtags: tags } : {}) })
		},
		requestKey() { return JSON.stringify([this.areaId, annualRequestParams(this.areaSelection)]) },
		detailKey() { return `${this.requestKey}:${this.query.detailType}:${this.query.detailPeriod}` },
		scopeLabel() {
			if (this.areaId === 0) return this.$texts.annual.withoutArea()
			const label = this.projects.find(project => Number(project.id) === this.areaId)?.name || this.$texts.analytics.area()
			return `${label} · ${this.areaSelection.entryScope === 'shared' ? this.$texts.annual.wholeArea() : this.$texts.annual.personalShare()}`
		},
		detail() {
			const identity = JSON.parse(this.detailIdentity)
			if (!this.report || identity.id !== 'total' || !['income', 'expense', 'all'].includes(identity.type) || (this.$enableIncomes === false && identity.type !== 'expense')) return null
			const index = identity.period === 'total' || identity.period === undefined ? this.report.periods.length - 1 : /^\d+$/.test(String(identity.period)) ? Number(identity.period) : -1
			if (index < 0 || index >= this.report.periods.length) return null
			const type = identity.type
			const label = this.areaId === 0 ? this.$texts.annual.withoutArea() : this.projects.find(project => Number(project.id) === this.areaId)?.name || this.$texts.analytics.area()
			return { row: { ...this.report.totals[type === 'all' ? 'balance' : type], type, label }, index, total: true, label, route: annualPaymentRoute(this.areaSelection, this.report.periods[index], type) }
		},
	},
	watch: { requestKey: { handler: 'load', immediate: true } },
	beforeUnmount() { this.request++ },
	methods: {
		async load() {
			const request = ++this.request
			this.report = null; this.error = false; this.loading = this.areaId !== null
			if (this.areaId === null) return
			try {
				const { data } = await axios.get(generateUrl('/apps/cobudget/api/annual-overview'), { params: annualRequestParams(this.areaSelection) })
				if (request === this.request) this.report = data
			} catch (error) { if (request === this.request) this.error = true }
			finally { if (request === this.request) this.loading = false }
		},
	},
}
</script>
