<template>
	<section class="report-payment-section" :data-report-section="section">
		<header class="report-payment-header">
			<div class="report-payment-heading">
				<h3 class="cobudget-section-heading report-payment-tabs" role="tablist" :aria-label="title" @keydown="onTabKey">
					<NcButton ref="overviewTab" variant="tertiary" alignment="start" role="tab" class="report-payment-tab" :class="{ 'is-selected': view === 'overview' }" :aria-selected="view === 'overview'" :tabindex="view === 'overview' ? 0 : -1" :aria-controls="`${bodyId}-overview`" @click="choose('overview')">{{ title }}</NcButton>
					<NcButton ref="topTab" variant="tertiary" alignment="start" role="tab" class="report-payment-tab" :class="{ 'is-selected': view === 'top' }" :aria-selected="view === 'top'" :tabindex="view === 'top' ? 0 : -1" :aria-controls="`${bodyId}-top`" @click="choose('top')">{{ $texts.annual.topTen() }}</NcButton>
				</h3>
				<ReportSectionMenu class="report-payment-menu" :title="title" :export-data="exportData" />
			</div>
			<ReportScrollControls v-if="view === 'overview'" class="report-payment-scroll-controls" :navigation="scrollNavigation" />
		</header>
		<div :id="bodyId">
			<div v-show="view === 'overview'" :id="`${bodyId}-overview`" role="tabpanel" :aria-label="title">
				<AnnualOverviewTable ref="overviewTable" :key="tableKey" v-bind="$attrs" embedded :section="section" :title="title" :selection="selection" :class="{ 'is-collapsed': view !== 'overview' }" @details="$emit('details', $event)" />
			</div>
			<div v-show="view === 'top'" :id="`${bodyId}-top`" class="report-payment-top-panel" role="tabpanel" :aria-label="$texts.annual.topTen()">
				<div class="report-payment-top-scroll" data-drag-scroll-ignore tabindex="0" role="region" :aria-label="`${title} · ${$texts.annual.topTen()}`">
				<ReportTopPayments v-if="topVisited" ref="topPayments" :selection="selection" :report="report" :projects="projects" :type="section" :title="title" :active="view === 'top'" :selected-entry-id="selectedEntryId" @open-entry-sidebar="$emit('open-entry-sidebar', $event)" />
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ReportSectionMenu from './ReportSectionMenu.vue'
import ReportScrollControls from './ReportScrollControls.vue'
import AnnualOverviewTable from './AnnualOverviewTable.vue'
import ReportTopPayments from './ReportTopPayments.vue'

let paymentSectionSequence = 0

export default {
	name: 'ReportPaymentSection',
	inheritAttrs: false,
	components: { NcButton, ReportSectionMenu, ReportScrollControls, AnnualOverviewTable, ReportTopPayments },
	props: { projects: { type: Array, default: () => [] }, section: { type: String, required: true }, title: { type: String, required: true }, selection: { type: Object, required: true }, report: { type: Object, required: true }, selectedEntryId: { type: [Number, String], default: null }, scrollNavigation: { type: Object, default: null } },
	data() { return { view: 'overview', topVisited: false, bodyId: `report-payment-section-${++paymentSectionSequence}` } },
	computed: { tableKey() { return `${this.selection.grouping}-${this.selection.projectId}-${this.selection.period}-${this.selection.month}-${this.selection.quarter}` } },
	methods: {
		exportData(options) { return this.$refs[this.view === 'top' ? 'topPayments' : 'overviewTable'].exportData(options) },
		choose(view) { this.view = view; if (view === 'top') this.topVisited = true },
		onTabKey(event) {
			if (event.altKey || event.ctrlKey || event.metaKey || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
			event.preventDefault()
			const view = event.key === 'Home' ? 'overview' : event.key === 'End' ? 'top' : this.view === 'overview' ? 'top' : 'overview'
			this.choose(view); this.$nextTick(() => this.$refs[view === 'overview' ? 'overviewTab' : 'topTab']?.$el?.focus({ preventScroll: true }))
		},
	},
	emits: ['details', 'open-entry-sidebar'],
}
</script>

<style scoped>
.report-payment-section { min-width: 0; margin: 0 0 var(--cobudget-report-section-gap, 48px); }
.report-payment-top-panel { position: sticky; inset-inline-start: 0; width: 100cqi; max-width: 100%; }
.report-payment-top-scroll { width: 100%; max-width: 100%; overflow: visible; border-radius: 0; }
.report-payment-top-scroll:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: -2px; }
.report-payment-header { position: sticky; inset-inline-start: 0; display: flex; flex-wrap: wrap; align-items: center; gap: var(--default-grid-baseline, 4px); box-sizing: border-box; width: calc(100cqi - var(--annual-table-inline-borders, 2px)); max-width: 100%; min-height: var(--cobudget-report-header-height, 44px); margin-block-end: var(--cobudget-content-gap); }
.report-payment-heading { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); min-width: 0; }
.report-payment-tabs { display: inline-flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 3); margin: 0;  }
.report-payment-scroll-controls { margin-inline-start: auto; }
.report-payment-section .report-payment-tab { min-height: 0; height: auto; margin: 0; padding: 2px 4px; border: 0; font: inherit; color: var(--cobudget-text-muted); --font-weight-element: 400; }
.report-payment-section .report-payment-tab.is-selected { color: var(--cobudget-text); --font-weight-element: 600; }
.report-payment-tab :deep(.button-vue__text) { padding: 0; padding-inline: 0 !important; white-space: normal; }
@media (max-width: 768px) { .report-payment-section .report-payment-tab { min-height: var(--cobudget-mobile-touch-size); } .report-payment-top-scroll { border-radius: 0; } }
</style>
