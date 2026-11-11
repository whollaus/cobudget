<template>
	<section class="report-detail-section" :aria-label="title" :data-report-section="sectionKey">
		<header class="report-detail-section-header">
			<div class="report-detail-section-heading">
				<slot name="heading" :body-id="bodyId"><h3 class="cobudget-section-heading">{{ title }}</h3></slot>
				<ReportSectionMenu :title="title" :export-data="sectionExportData" :export-disabled="exportDisabled" />
			</div>
			<slot name="controls" />
		</header>
		<div :id="bodyId" class="report-detail-section-body"><slot :body-id="bodyId" /></div>
	</section>
</template>

<script>
import ReportSectionMenu from './ReportSectionMenu.vue'
import { reportDetailTableExport, reportExportNotReady } from '../services/reportExport'

let sectionSequence = 0

export default {
	name: 'ReportDetailSection',
	components: { ReportSectionMenu },
	props: { title: { type: String, required: true }, sectionKey: { type: String, required: true }, exportData: { type: Function, default: null }, exportDisabled: { type: Boolean, default: false } },
	data() { return { bodyId: `report-detail-section-${++sectionSequence}` } },
	methods: {
		sectionExportData(options) {
			if (this.exportDisabled) throw reportExportNotReady()
			return this.exportData ? this.exportData(options) : reportDetailTableExport(this.$el.querySelector('.report-detail-section-body'), this.title)
		},
	},
}
</script>

<style scoped>
.report-detail-section { min-width: 0; margin: 0 0 var(--cobudget-report-section-gap, 48px); padding: 0; }
.report-detail-section-header { display: flex; flex-wrap: wrap; align-items: center; gap: var(--default-grid-baseline, 4px); min-height: var(--cobudget-report-header-height, 44px); margin-block-end: var(--cobudget-content-gap); }
.report-detail-section-heading { display: flex; flex-wrap: wrap; align-items: center; gap: var(--default-grid-baseline, 4px); min-width: 0; max-width: 100%; }
.report-detail-section h3 { margin: 0;  color: var(--cobudget-text); }
.report-detail-section-body { min-width: 0; }
</style>
