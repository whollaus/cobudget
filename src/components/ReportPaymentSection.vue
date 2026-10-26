<template>
	<section class="report-payment-section" :class="{ 'is-collapsed': !open }" :data-report-section="section">
		<header class="report-payment-header">
			<h3 class="report-payment-tabs" role="tablist" :aria-label="title" @keydown="onTabKey">
				<NcButton ref="overviewTab" variant="tertiary" alignment="start" role="tab" class="report-payment-tab" :class="{ 'is-selected': view === 'overview' }" :aria-selected="view === 'overview'" :tabindex="view === 'overview' ? 0 : -1" :aria-controls="`${bodyId}-overview`" @click="choose('overview')">{{ title }}</NcButton>
				<NcButton ref="topTab" variant="tertiary" alignment="start" role="tab" class="report-payment-tab" :class="{ 'is-selected': view === 'top' }" :aria-selected="view === 'top'" :tabindex="view === 'top' ? 0 : -1" :aria-controls="`${bodyId}-top`" @click="choose('top')">{{ $texts.annual.topTen() }}</NcButton>
			</h3>
			<ReportSectionMenu class="report-payment-menu" :open="open" :title="title" :controls="bodyId" :export-data="exportData" @toggle="toggleSection" />
		</header>
		<div v-show="open" :id="bodyId">
			<div v-show="view === 'overview'" :id="`${bodyId}-overview`" role="tabpanel" :aria-label="title">
				<AnnualOverviewTable ref="overviewTable" :key="tableKey" v-bind="$attrs" embedded :section="section" :title="title" :selection="selection" :class="{ 'is-collapsed': !open || view !== 'overview' }" @details="$emit('details', $event)" />
			</div>
			<div v-show="view === 'top'" :id="`${bodyId}-top`" class="report-payment-top-panel" role="tabpanel" :aria-label="$texts.annual.topTen()">
				<div class="report-payment-top-scroll" tabindex="0" role="region" :aria-label="`${title} · ${$texts.annual.topTen()}`">
				<ReportTopPayments v-if="topVisited" ref="topPayments" :selection="selection" :report="report" :type="section" :title="title" :active="open && view === 'top'" :selected-entry-id="selectedEntryId" @open-entry-sidebar="$emit('open-entry-sidebar', $event)" />
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ReportSectionMenu from './ReportSectionMenu.vue'
import AnnualOverviewTable from './AnnualOverviewTable.vue'
import ReportTopPayments from './ReportTopPayments.vue'
import { readAnnualSectionOpen, rememberAnnualSection } from '../services/annualOverview'

let paymentSectionSequence = 0

export default {
	name: 'ReportPaymentSection',
	inheritAttrs: false,
	components: { NcButton, ReportSectionMenu, AnnualOverviewTable, ReportTopPayments },
	props: { section: { type: String, required: true }, title: { type: String, required: true }, selection: { type: Object, required: true }, report: { type: Object, required: true }, selectedEntryId: { type: [Number, String], default: null } },
	data() { return { open: readAnnualSectionOpen(this.section), view: 'overview', topVisited: false, bodyId: `report-payment-section-${++paymentSectionSequence}` } },
	computed: { tableKey() { return `${this.selection.grouping}-${this.selection.projectId}-${this.selection.period}-${this.selection.month}-${this.selection.quarter}` } },
	methods: {
		exportData(options) { return this.$refs[this.view === 'top' ? 'topPayments' : 'overviewTable'].exportData(options) },
		choose(view) { this.view = view; if (view === 'top') this.topVisited = true; this.open = true; rememberAnnualSection(this.section, true) },
		toggleSection() { this.open = !this.open; rememberAnnualSection(this.section, this.open) },
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
.report-payment-section { min-width: 0; margin: 0 0 var(--cobudget-report-section-gap, 24px); }
.report-payment-section.is-collapsed { margin-bottom: 0; }
.report-payment-top-panel { position: sticky; inset-inline-start: 0; width: 100cqi; max-width: 100%; }
.report-payment-top-scroll { width: 100%; max-width: 100%; overflow-x: auto; overscroll-behavior-x: contain; border-radius: var(--border-radius-large, 8px); }
.report-payment-top-scroll:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: -2px; }
.report-payment-header { position: sticky; inset-inline-start: 0; display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); width: fit-content; max-width: 100cqi; min-height: var(--cobudget-report-header-height, 64px); }
.report-payment-tabs { display: inline-flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 3); margin: 0; font-size: var(--cobudget-font-lg, 18px); font-weight: 600; }
.report-payment-section .report-payment-tab { min-height: 0; height: auto; margin: 0; padding: 2px 4px; border: 0; font: inherit; color: var(--cobudget-text-muted); --font-weight-element: 400; }
.report-payment-section .report-payment-tab.is-selected { color: var(--cobudget-text); --font-weight-element: 600; }
.report-payment-tab :deep(.button-vue__text) { padding: 0; padding-inline: 0 !important; white-space: normal; }
@media (max-width: 768px) { .report-payment-section .report-payment-tab { min-height: var(--cobudget-mobile-touch-size); } .report-payment-top-scroll { border-radius: 0; } }
</style>
