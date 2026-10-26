<template>
	<section class="report-detail-section" :class="{ 'is-collapsed': !open }" :aria-label="title" :data-report-section="sectionKey">
		<header class="report-detail-section-header">
			<div class="report-detail-section-heading">
				<slot name="heading" :body-id="bodyId" :expand="expandSection"><h3>{{ title }}</h3></slot>
				<ReportSectionMenu :open="open" :title="title" :controls="bodyId" :export-data="sectionExportData" :export-disabled="exportDisabled" @toggle="toggleSection" />
			</div>
			<slot name="controls" :open="open" />
		</header>
		<div v-show="open" :id="bodyId" class="report-detail-section-body"><slot :body-id="bodyId" /></div>
	</section>
</template>

<script>
import ReportSectionMenu from './ReportSectionMenu.vue'
import { readAnnualSectionOpen, rememberAnnualSection } from '../services/annualOverview'
import { reportDetailTableExport, reportExportNotReady } from '../services/reportExport'

let sectionSequence = 0

export default {
	name: 'ReportDetailSection',
	components: { ReportSectionMenu },
	props: { title: { type: String, required: true }, sectionKey: { type: String, required: true }, exportData: { type: Function, default: null }, exportDisabled: { type: Boolean, default: false } },
	data() { return { open: readAnnualSectionOpen(this.sectionKey), bodyId: `report-detail-section-${++sectionSequence}` } },
	methods: {
		sectionExportData(options) {
			if (this.exportDisabled) throw reportExportNotReady()
			return this.exportData ? this.exportData(options) : reportDetailTableExport(this.$el.querySelector('.report-detail-section-body'), this.title)
		},
		toggleSection() { this.open = !this.open; rememberAnnualSection(this.sectionKey, this.open) },
		expandSection() { if (!this.open) { this.open = true; rememberAnnualSection(this.sectionKey, true) } },
	},
}
</script>

<style scoped>
.report-detail-section { min-width: 0; margin: 0 0 var(--cobudget-report-section-gap, 24px); padding: 0; }
.report-detail-section.is-collapsed { margin-bottom: 0; }
.report-detail-section-header { display: flex; flex-wrap: wrap; align-items: center; gap: var(--default-grid-baseline, 4px); min-height: var(--cobudget-report-header-height, 64px); }
.report-detail-section-heading { display: flex; flex-wrap: wrap; align-items: center; gap: var(--default-grid-baseline, 4px); min-width: 0; max-width: 100%; }
.report-detail-section h3 { margin: 0; padding: 2px 4px; font-size: var(--cobudget-font-lg, 18px); font-weight: 600; color: var(--cobudget-text); }
.report-detail-section-body { min-width: 0; }
</style>
