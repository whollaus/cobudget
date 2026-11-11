<template>
	<div class="annual-overview">
		<div v-show="!detailRequested" ref="overview">
		<AppPageHeader class="annual-report-header cobudget-filter-toolbar" :title="$texts.annual.title()">
			<template #title>
				<ReportPeriodPicker navigation-only force-navigation :period-enabled="false" :title="$texts.annual.title()" :selection="selection" label="" />
			</template>
			<template #actions>
				<ReportPeriodPicker class="annual-toolbar-period" calendar-multiple report-calendar-selection :selection="selection" :years="yearOptions" :label="titleRangeLabel || String(selection.year)" @change="selectReportPeriod" />
				<AnnualOverviewFilters v-for="section in ['areas', 'categories', 'partners', 'labels', '']" :key="section"
					:class="section ? `annual-shortcut annual-shortcut--${section}` : 'annual-main-filter'"
					:section="section" hide-chips v-bind="filterProps" @period-change="selectReportPeriod"
					@areas-change="selectAreas" @scope-change="selection.entryScope = $event; updateSelection()"
					@change="onFilterChange" @clear="clearFilter" @reset="resetFilters" />
				<NcActions class="annual-actions-menu cobudget-report-action cobudget-action-menu" variant="tertiary" force-menu placement="bottom-end"
					:disabled="loading || error || !report"
					:aria-label="$texts.annual.exportAndPrint()" @close="csvMenuOpen = false">
					<template v-if="csvMenuOpen">
						<NcActionButton key="csv-back" ref="csvBack" class="cobudget-action-menu-option" :close-after-click="false" @click="showCsvMenu(false)">
							<template #icon><ArrowLeftIcon :size="20" /></template>
							{{ $texts.common.exportCsv() }}
						</NcActionButton>
						<NcActionButton key="csv-download" class="cobudget-action-menu-option" close-after-click :disabled="exporting" :description="$texts.annual.downloadCsvHint()" @click="exportReport">
							<template #icon><DownloadIcon :size="20" /></template>
							{{ $texts.common.download() }}
						</NcActionButton>
						<NcActionButton key="csv-save" class="cobudget-action-menu-option" close-after-click :disabled="exporting" :description="$texts.annual.saveInNextcloudHint()" @click="saveReportInNextcloud">
							<template #icon><CloudUploadOutlineIcon :size="20" /></template>
							{{ $texts.annual.saveInNextcloud() }}
						</NcActionButton>
						<NcActionButton key="csv-copy" class="cobudget-action-menu-option" close-after-click :disabled="exporting" @click="copyReport"><template #icon><ClipboardTextOutlineIcon :size="20" /></template>{{ $texts.common.copyToClipboard() }}</NcActionButton>
					</template>
					<template v-else>
						<NcActionButton key="csv-trigger" ref="csvTrigger" class="cobudget-action-menu-option" is-menu :close-after-click="false" :disabled="exporting" @click="showCsvMenu(true)">
							<template #icon><DownloadIcon :size="20" /></template>
							{{ $texts.common.exportCsv() }}
						</NcActionButton>
						<NcActionButton key="print" class="cobudget-action-menu-option" close-after-click :disabled="printing" @click="printReport">
							<template #icon><PrinterIcon :size="20" /></template>
							{{ $texts.analytics.print() }}
						</NcActionButton>
					</template>
				</NcActions>
			</template>
		</AppPageHeader>
		<div class="annual-controls">
			<ReportSelect :model-value="selection.period" :label="$texts.annual.breakdown()"
				:options="[{ value: 'quarter', label: $texts.annual.quarters() }, { value: 'year', label: $texts.annual.months() }, { value: 'week', label: $texts.annual.weeks() }, { value: 'day', label: $texts.annual.days() }]" @update:model-value="selectPeriod" />
			<ReportSelect v-if="!selection.timeRange" :disabled="loading || !yearOptions.length" :model-value="selection.year" :label="$texts.annual.year()"
				:options="yearOptions.map(value => ({ value, label: String(value) }))" @update:model-value="selectYear" />
			<ReportSelect v-if="!selection.timeRange && selection.period === 'day'" :model-value="Number(selection.month) >= 1 ? Number(selection.month) : 'all'" :label="$texts.budgetGoals.month()"
				:options="[{ value: 'all', label: $texts.annual.allDays() }, ...monthOptions]" @update:model-value="selectMonth" />
			<ReportSelect :model-value="selection.grouping" :label="$texts.annual.grouping()"
				:options="[{ value: 'categories', label: $texts.annual.categories() }, { value: 'paymentPartners', label: $texts.annual.partners() }, { value: 'labelsAndTags', label: $texts.annual.labelsAndTags() }]" @update:model-value="selection.grouping = $event; updateSelection()" />
			<ReportSelect v-if="showsCutoff" :title="$texts.annual.futureHint()" :model-value="selection.includeFuturePayments" :label="$texts.annual.selectedPeriod()"
				:options="[{ value: false, label: $texts.annual.paymentsUntilToday() }, { value: true, label: $texts.annual.includeFuture() }]" @update:model-value="selection.includeFuturePayments = $event; updateSelection()" />
		</div>
		<AnnualOverviewFilters chips-only v-bind="filterProps" @clear="clearFilter" />
		<ReportDevelopment v-if="report && !loading && !error" ref="development" v-model:mode="chartMode" v-model:hidden-series="chartHiddenSeries"
			:report="report" :selection="selection" :labels="columns.slice(0, -1)" :label-titles="columnTitles.slice(0, -1)" :axis-labels="chartAxisLabels"
			:chart-period="selection.period" :summary-rows="totalRows" :period-label="titleRangeLabel" @details="openSummaryDetails" />
		<div v-if="error" class="annual-error" role="alert"><p>{{ $texts.annual.loadError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
		<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
		<DraggableScroller v-if="report" v-show="!loading && !error" ref="printSource" id="cobudget-report-periods" class="annual-scroll" :class="{ 'has-horizontal-overflow': scrollState.overflow }"
			allow-link-drag interactive-drag-selector=".annual-amount" show-scrollbar native-touch role="region" :aria-label="periodTitle" :style="bucketStyle" @scroll.passive="updateReportScroll">
			<div class="annual-scroll-content">
			<ReportPaymentSection v-for="type in paymentTypes" ref="paymentSections" :key="type" :section="type" :report="report" :projects="projects" :selected-entry-id="selectedEntryId"
				:title="type === 'income' ? $texts.annual.income() : $texts.annual.expenses()" :first-heading="firstHeading" :scroll-navigation="scrollNavigation"
				:columns="columns" :column-titles="columnTitles" :rows="groupRows(type)" :total-row="totalRows.find(row => row.type === type)" :selection="selection" :periods="report.periods" :empty-message="emptyMessage" @details="openDetails" @open-entry-sidebar="$emit('open-entry-sidebar', $event)" />
			<AnnualOverviewTable v-if="$enableIncomes && hasPayments" ref="totalSummary" section="total" :title="$texts.analytics.total()" :first-heading="$texts.analytics.total()" :columns="columns" :column-titles="columnTitles" :rows="totalRows" :selection="selection" :periods="report.periods" :scroll-navigation="scrollNavigation" summary @details="openDetails" />
			<ReportAreaOverview v-if="$enableProjects && !selection.projectId && !selection.withoutArea" ref="areaOverview"
				:selection="selection" :report="report" :columns="columns" :column-titles="columnTitles" :amount-view="areaAmountView" :metric="areaMetric" :scroll-navigation="scrollNavigation"
				@update:amount-view="updateAreaControls($event, areaMetric)" @update:metric="updateAreaControls(areaAmountView, $event)" />
			</div>
		</DraggableScroller>
		</div>
		<template v-if="detailRequested">
			<ReportAreaDetailView v-if="$enableProjects && $route.query.detailArea !== undefined && report && !loading && !error" :query="$route.query" :selection="selection"
				:export-metadata="exportMetadata()"
				:projects="projects" :labels="columns.slice(0, -1)" :label-titles="columnTitles.slice(0, -1)" :axis-labels="chartAxisLabels" :selected-entry-id="selectedEntryId"
				@back="closeDetails" @open-entry-sidebar="$emit('open-entry-sidebar', $event)" @selected-entry-missing="$emit('selected-entry-missing', $event)" />
			<ReportDetailView v-else-if="focusedDetail && !loading && !error" :detail="focusedDetail" :report="report" :selection="selection" :scope-label="reportScopeLabel"
				:export-metadata="exportMetadata()"
				:projects="projects" :labels="columns.slice(0, -1)" :label-titles="columnTitles.slice(0, -1)" :axis-labels="chartAxisLabels" :selected-entry-id="selectedEntryId"
				@back="closeDetails" @open-entry-sidebar="$emit('open-entry-sidebar', $event)" @selected-entry-missing="$emit('selected-entry-missing', $event)" />
			<div v-else class="annual-detail-status">
				<NcButton class="cobudget-native-button" variant="tertiary" @click="closeDetails">{{ $texts.annual.back() }}</NcButton>
				<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
				<div v-else-if="error" role="alert"><p>{{ $texts.annual.loadError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
				<p v-else role="status">{{ $texts.annual.detailUnavailable() }}</p>
			</div>
		</template>
	</div>
</template>

<script>
import { PRINT_REPORT_EVENT } from '../services/keyboardShortcuts'
import ReportSelect from '../components/ReportSelect.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import CloudUploadOutlineIcon from 'vue-material-design-icons/CloudUploadOutline.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import PrinterIcon from 'vue-material-design-icons/Printer.vue'
import ClipboardTextOutlineIcon from 'vue-material-design-icons/ClipboardTextOutline.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import AnnualOverviewTable from '../components/AnnualOverviewTable.vue'
import ReportPaymentSection from '../components/ReportPaymentSection.vue'
import ReportDetailView from '../components/ReportDetailPage.vue'
import AnnualOverviewFilters from '../components/AnnualOverviewFilters.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import ReportAreaOverview from '../components/ReportAreaOverview.vue'
import ReportAreaDetailView from '../components/ReportAreaDetailView.vue'
import ReportDevelopment from '../components/ReportDevelopment.vue'
import DraggableScroller from '../components/DraggableScroller.vue'
import { getAppLocale } from '../utils/formatMoney'
import { sortAreasByName } from '../utils/areaSorting'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { resetAnnualChartViews, annualFilterChips, annualFilterNameMaps, annualPaymentRoute, annualPeriodContainsToday, annualReportSelection, reportPeriodLabel, reportRangeError, reportContainsToday, parseReportDate, isoWeekNumber, isoWeekYear, annualRequestParams, annualRouteQuery, annualSelection, calendarDayCount, isoWeekCount, isoWeekStart } from '../services/annualOverview'
import { printAnnualOverview } from '../services/annualOverviewPrint'
import { reportExportMetadata } from '../services/reportExport'
import { deliverReportExport } from '../services/reportExportActions'
import { formatCalendarLabel } from '../utils/formatDate'
import { showRequestError, showToast } from '../services/notifications'
import { REMOTE_DATA_CHANGED_EVENT } from '../services/dataSync'
import { reportDetailNavigation, reportDetailQuery, reportDetailParentQuery, reportDetailParentMatches, resolveReportDetail, areaOverviewOptions, areaOverviewQuery } from '../services/reportDetails'

export default {
	name: 'AnnualOverviewView',
	components: { ArrowLeftIcon, ReportSelect, NcButton, NcActions, NcActionButton, CloudUploadOutlineIcon, DownloadIcon, PrinterIcon, ClipboardTextOutlineIcon, AppPageHeader, AnnualOverviewTable, ReportPaymentSection, ReportDetailView, AnnualOverviewFilters, ReportPeriodPicker, ReportAreaOverview, ReportAreaDetailView, ReportDevelopment, DraggableScroller },
	provide() { return { reportExportContext: () => ({ metadata: this.exportMetadata(), filename: this.annualExportFilename() }) } },
	props: { selectedEntryId: { type: [Number, String], default: null }, hideNewPaymentAction: { type: Boolean, default: false } },
	emits: ['open-entry-sidebar', 'selected-entry-missing'],
	data() { const selection = annualReportSelection(this.$route.query); return { selection, ...areaOverviewOptions(this.$route.query), chartMode: 'cumulative', chartHiddenSeries: [], projects: [], categoryChoices: [], partnerChoices: [], hashtagChoices: [], filterChoices: { categoryIds: [], partnerIds: [], labels: [] }, report: null, loading: true, printing: false, exporting: false, csvMenuOpen: false, error: false, requestId: 0, filterChoiceRequest: 0, overviewPosition: null, openedDetailFromOverview: false, initialPeriodCentered: false, scrollState: { overflow: false, canScrollLeft: false, canScrollRight: false }, reportScrollObserver: null, reportScrollDisposed: false } },
	computed: {
		filterProps() {
			return {
				selection: this.selection, years: this.yearOptions, periodLabel: this.titleRangeLabel, categories: this.categoryChoices, partners: this.partnerChoices, hashtags: this.hashtagChoices,
				categoryIds: this.filterChoices.categoryIds, partnerIds: this.filterChoices.partnerIds, labelKeys: this.filterChoices.labels,
				areaIds: this.selectedAreaIds, showAmountScope: this.$enableProjects && this.isSharedArea,
				areaOptions: this.$enableProjects && this.projects.some(project => !project.is_archived) ? [{ value: null, label: this.$texts.settings.myFinances() }, { value: 0, label: this.$texts.annual.withoutArea(), icon: 'without-area' },
					...this.projects.map(project => ({ value: Number(project.id), label: project.name, icon: 'folder', color: project.color }))] : [],
			}
		},
		scrollNavigation() { return { ...this.scrollState, controls: 'cobudget-report-periods', scroll: this.scrollReportColumns } },
		areaControlQuery() { return areaOverviewQuery(this.areaAmountView, this.areaMetric) },
		detailRequested() { return this.$route.query.detailType !== undefined },
		detailIdentity() {
			const { detailType, detailId, detailDirect, detailPeriod, detailArea } = this.$route.query
			return JSON.stringify({ detailType, detailId, detailDirect, detailPeriod, detailArea })
		},
		focusedDetail() {
			const identity = JSON.parse(this.detailIdentity)
			if (!this.detailRequested || !this.report || identity.detailArea !== undefined) return null
			const detail = resolveReportDetail(identity, this.report, this.paymentTypes.flatMap(type => this.groupRows(type)), this.totalRows, this.$texts.common.mainCategory())
			return detail ? { ...detail, route: annualPaymentRoute(this.selection, this.report.periods[detail.index], detail.row.type, detail.total ? null : detail.row) } : null
		},
		titleRangeLabel() {
			if (this.selection.timeRange === 'all') return this.$texts.filters.allTime()
			const dateFormat = new Intl.DateTimeFormat(getAppLocale(), { day: '2-digit', month: '2-digit', year: 'numeric' })
			return reportPeriodLabel(this.selection, formatCalendarLabel, date => dateFormat.format(date))
		},
		selectedRangeLabel() {
			if (this.selection.timeRange === 'calendar-selection') return this.titleRangeLabel
			const from = parseReportDate(this.selection.dateFrom), to = parseReportDate(this.selection.dateTo)
			const format = date => formatCalendarLabel(date, { day: 'numeric', month: 'short', year: 'numeric' })
			return from && to ? `${format(from)} – ${format(to)}` : ''
		},
		rangePeriods() { return this.selection.timeRange ? (this.report?.periods || []).slice(0, -1) : [] },
		currentYear() { return new Date().getFullYear() },
		paymentTypes() { return this.$enableIncomes ? ['income', 'expense'] : ['expense'] },
		hasPayments() {
			if (!this.report) return false
			return Boolean(this.report.groups.income.length || this.report.groups.expense.length)
		},
		yearOptions() {
			const years = this.report?.years || []
			return this.selection?.timeRange === 'year' && !years.includes(this.selection.year) ? [...years, this.selection.year].sort((a, b) => b - a) : years
		},
		selectedAreaIds() { return this.selection.projects?.length ? this.selection.projects : this.selection.withoutArea ? [0] : this.selection.projectId ? [this.selection.projectId] : [] },
		scopeLabel() { if (this.selection.projects?.length) return this.selection.projects.map(id => id === 0 ? this.$texts.annual.withoutArea() : this.projects.find(project => Number(project.id) === id)?.name || String(id)).join(', '); return this.selection.withoutArea ? this.$texts.annual.withoutArea() : this.selection.projectId ? this.projects.find(project => Number(project.id) === this.selection.projectId)?.name || this.$texts.annual.scope() : this.$texts.settings.myFinances() },
		isSharedArea() { return Number(this.projects.find(project => Number(project.id) === this.selection.projectId)?.member_count || 0) > 1 },
		amountScopeLabel() { return this.selection.entryScope === 'shared' ? this.$texts.annual.wholeArea() : this.$texts.annual.personalShare() },
		reportScopeLabel() { return this.isSharedArea ? `${this.scopeLabel} · ${this.amountScopeLabel}` : this.scopeLabel },
		filterCatalogKey() { return `${this.selection.projectId || ''}:${this.selection.entryScope}:${(this.selection.projects || []).join(',')}` },
		periodTitle() {
			if (this.selection.period === 'day') return this.$texts.annual.dailyTitle()
			if (this.selection.period === 'month') return this.$texts.annual.monthlyTitle()
			if (this.selection.period === 'week') return this.$texts.annual.weeklyTitle()
			if (this.selection.period === 'quarter') return this.$texts.annual.quarterlyTitle()
			return this.$texts.annual.title()
		},
		breakdownLabel() {
			if (this.selection.period === 'day') return this.$texts.annual.days()
			if (this.selection.period === 'week') return this.$texts.annual.weeks()
			if (this.selection.period === 'quarter') return this.$texts.annual.quarters()
			return this.$texts.annual.months()
		},
		showsCutoff() {
			return reportContainsToday(this.selection)
		},
		dayCount() { return new Date(this.selection.year, this.selection.month, 0).getDate() },
		monthOptions() { return Array.from({ length: 12 }, (_, month) => ({ value: month + 1, label: formatCalendarLabel(new Date(this.selection.year, month, 1), { month: 'long' }) })) },
		selectedMonthLabel() {
			const month = Number(this.selection.month)
			if (!Number.isInteger(month) || month < 1 || month > 12) return this.$texts.annual.allDays()
			return formatCalendarLabel(new Date(this.selection.year, month - 1, 1), { month: 'long' })
		},
		columns() {
			if (this.selection.timeRange) return [...this.rangePeriods.map(period => this.rangeBucketLabel(period)), this.$texts.analytics.total()]
			if (this.selection.period === 'day') return [...this.dailyDates().map(date => formatCalendarLabel(date, { day: 'numeric', month: 'short' })), this.$texts.analytics.total()]
			if (this.selection.period === 'month') {
				const monthIndex = (this.selection.month || 1) - 1
				return [...Array.from({ length: this.dayCount }, (_, day) => formatCalendarLabel(new Date(this.selection.year, monthIndex, day + 1), { day: 'numeric', month: 'short' })), this.$texts.analytics.total()]
			}
			if (this.selection.period === 'week') return [...Array.from({ length: isoWeekCount(this.selection.year) }, (_, week) => this.$texts.annual.week(week + 1)), this.$texts.analytics.total()]
			if (this.selection.period === 'quarter') return [...[1, 2, 3, 4].map(quarter => this.$texts.annual.quarter(quarter)), this.$texts.analytics.total()]
			return [...Array.from({ length: 12 }, (_, month) => formatCalendarLabel(new Date(this.selection.year, month, 1), { month: 'long' })), this.$texts.analytics.total()]
		},
		columnTitles() {
			if (this.selection.timeRange) {
				const format = timestamp => formatCalendarLabel(new Date(timestamp * 1000), { day: 'numeric', month: 'long', year: 'numeric' })
				return [...this.rangePeriods.map(period => `${format(period.dateFrom)} – ${format(period.dateTo)}`), this.$texts.analytics.total()]
			}
			if (this.selection.period === 'day') {
				return [...this.dailyDates().map(date => formatCalendarLabel(date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })), this.$texts.analytics.total()]
			}
			if (this.selection.period === 'week') {
				const span = date => formatCalendarLabel(date, { day: 'numeric', month: 'short', year: 'numeric' })
				return [...Array.from({ length: isoWeekCount(this.selection.year) }, (_, week) => {
					const start = isoWeekStart(this.selection.year, week + 1)
					const end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6)
					return `${span(start)} – ${span(end)}`
				}), this.$texts.analytics.total()]
			}
			if (this.selection.period !== 'month') return this.columns
			const monthIndex = (this.selection.month || 1) - 1
			return [...Array.from({ length: this.dayCount }, (_, day) => formatCalendarLabel(new Date(this.selection.year, monthIndex, day + 1), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })), this.$texts.analytics.total()]
		},
		bucketStyle() {
			return { '--annual-bucket-count': String(Math.max(this.columns.length - 1, 1)), '--annual-bucket-width': '108px' }
		},
		chartAxisLabels() {
			if (this.selection.timeRange) return this.rangePeriods.map(period => this.rangeBucketLabel(period, true))
			if (this.selection.period === 'year') return Array.from({ length: 12 }, (_, month) => formatCalendarLabel(new Date(this.selection.year, month, 1), { month: 'short' }))
			return this.columns.slice(0, -1)
		},
		groupingLabel() {
			if (this.selection.grouping === 'paymentPartners') return this.$texts.annual.partners()
			if (this.selection.grouping === 'labelsAndTags') return this.$texts.annual.labelsAndTags()
			return this.$texts.annual.categories()
		},
		firstHeading() {
			if (this.selection.grouping === 'paymentPartners') return this.$texts.analytics.paymentPartner()
			if (this.selection.grouping === 'labelsAndTags') return this.$texts.annual.labelsAndTags()
			return this.$texts.analytics.category()
		},
		emptyMessage() { return this.selection.grouping === 'labelsAndTags' ? this.$texts.annual.noLabelsOrTags() : '' },
		totalRows() {
			return [...this.paymentTypes, ...(this.$enableIncomes ? ['balance'] : [])].map(type => ({
				...this.report.totals[type], key: type, type: type === 'balance' ? 'all' : type,
				label: type === 'income' ? this.$texts.annual.income() : type === 'expense' ? this.$texts.annual.expenses() : this.$texts.analytics.balance(),
			}))
		},
	},
	watch: {
		report() { this.$nextTick(this.observeReportScroll) },
		'$route.query': { handler(query, previous = {}) {
			Object.assign(this, areaOverviewOptions(query))
			const next = annualReportSelection(query)
			const changed = JSON.stringify(annualRouteQuery(next)) !== JSON.stringify(annualRouteQuery(this.selection))
			const contextChanged = JSON.stringify(annualRouteQuery(next)) !== JSON.stringify(annualRouteQuery(annualReportSelection(previous)))
			const entering = query.detailType !== undefined && previous.detailType === undefined
			const leaving = query.detailType === undefined && previous.detailType !== undefined
			if (entering) { this.rememberOverviewPosition(); this.openedDetailFromOverview = true }
			if (changed) this.selection = next
			if (contextChanged || changed || !this.report) this.load()
			this.$nextTick(() => {
				const target = this.$el?.closest?.('.app-content')
				if (entering && target) target.scrollTop = 0
				if (leaving) this.restoreOverviewPosition()
			})
		}, deep: true },
		filterCatalogKey() {
			this.hashtagChoices = []
			this.categoryChoices = []
			this.partnerChoices = []
			this.filterChoices = { categoryIds: [], partnerIds: [], labels: [] }
			return this.loadFilterChoices()
		},
	},
	created() { resetAnnualChartViews() },
	mounted() {
		window.addEventListener(PRINT_REPORT_EVENT, this.printReport)
		this.load()
		this.loadFilterChoices()
		window.addEventListener(REMOTE_DATA_CHANGED_EVENT, this.load)
		window.addEventListener('cobudget-data-changed', this.load)
		window.addEventListener('entry-saved', this.load)
		window.addEventListener('resize', this.updateReportScroll)
		this.$nextTick(this.observeReportScroll)
	},
	beforeUnmount() {
		window.removeEventListener(PRINT_REPORT_EVENT, this.printReport)
		this.reportScrollDisposed = true
		this.reportScrollObserver?.disconnect()
		this.requestId++
		this.filterChoiceRequest++
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.load)
		window.removeEventListener('cobudget-data-changed', this.load)
		window.removeEventListener('entry-saved', this.load)
		window.removeEventListener('resize', this.updateReportScroll)
	},
	methods: {
		observeReportScroll() {
			this.reportScrollObserver?.disconnect()
			this.reportScrollObserver = null
			if (this.reportScrollDisposed) return
			const scroller = this.$refs.printSource?.$el
			if (scroller && typeof ResizeObserver !== 'undefined') {
				this.reportScrollObserver = new ResizeObserver(this.updateReportScroll)
				this.reportScrollObserver.observe(scroller)
				if (scroller.firstElementChild) this.reportScrollObserver.observe(scroller.firstElementChild)
			}
			this.updateReportScroll()
		},
		updateReportScroll() {
			if (this.reportScrollDisposed) return
			const scroller = this.$refs.printSource?.$el
			const distance = scroller ? Math.max(0, scroller.scrollWidth - scroller.clientWidth) : 0
			const overflow = Boolean(scroller?.clientWidth && distance > 1)
			const rtl = scroller && window.getComputedStyle(scroller).direction === 'rtl'
			const left = scroller?.scrollLeft || 0
			Object.assign(this.scrollState, {
				overflow,
				canScrollLeft: overflow && (rtl ? left + distance : left) > 1,
				canScrollRight: overflow && (rtl ? -left : distance - left) > 1,
			})
			if (!this.initialPeriodCentered && overflow && (!this.overviewPosition || !this.openedDetailFromOverview)) {
				this.scrollToCurrentPeriod()
			}
		},
		scrollToCurrentPeriod(options = {}) {
			if (this.reportScrollDisposed) return
			const scroller = this.$refs.printSource?.$el ?? this.$refs.printSource
			if (!scroller) return
			if (!this.report?.periods || this.report.periods.length < 2) return

			const now = options.now ?? Math.floor(Date.now() / 1000)
			const periods = this.report.periods.slice(0, -1)
			const targetIndex = periods.findIndex(period => period.dateFrom <= now && now <= period.dateTo)
			if (targetIndex === -1) {
				this.initialPeriodCentered = true
				return
			}

			const cells = scroller.querySelector?.('.annual-table:not(.is-collapsed) thead tr')?.cells
			if (!cells || cells.length < 3) return
			const targetCell = cells[targetIndex + 1]
			if (!targetCell) return

			const maxScroll = Math.max(0, scroller.scrollWidth - scroller.clientWidth)
			if (maxScroll <= 0) {
				this.initialPeriodCentered = true
				return
			}

			const firstRect = cells[0].getBoundingClientRect?.() || {}
			const lastRect = cells[cells.length - 1].getBoundingClientRect?.() || {}
			const targetRect = targetCell.getBoundingClientRect?.() || {}

			const columnWidth = targetRect.width || cells[1]?.getBoundingClientRect?.()?.width || 108
			const startPinnedWidth = firstRect.width || 0
			const endPinnedWidth = lastRect.width || 0
			const visiblePeriodWidth = Math.max(0, scroller.clientWidth - startPinnedWidth - endPinnedWidth)
			const visibleCenter = startPinnedWidth + visiblePeriodWidth / 2
			const targetCenter = startPinnedWidth + targetIndex * columnWidth + columnWidth / 2
			const rtl = typeof window !== 'undefined' && window.getComputedStyle?.(scroller)?.direction === 'rtl'

			const offset = targetCenter - visibleCenter
			const targetScrollLeft = rtl
				? Math.max(-maxScroll, Math.min(0, Math.round(-offset)))
				: Math.max(0, Math.min(maxScroll, Math.round(offset)))

			if (options.behavior === 'smooth' && typeof scroller.scrollTo === 'function') {
				scroller.scrollTo({ left: targetScrollLeft, behavior: 'smooth' })
			} else {
				scroller.scrollLeft = targetScrollLeft
			}
			this.initialPeriodCentered = true
			this.updateReportScroll()
		},
		scrollReportColumns(direction) {
			const scroller = this.$refs.printSource?.$el
			const cells = scroller?.querySelector('.annual-table:not(.is-collapsed) thead tr')?.cells
			if (!cells || cells.length < 3) return
			const columnWidth = cells[1].getBoundingClientRect().width
			if (!columnWidth) return
			const visibleWidth = scroller.clientWidth - cells[0].getBoundingClientRect().width - cells[cells.length - 1].getBoundingClientRect().width
			// Leave one time column in view as context, and move at least one.
			const columns = Math.max(1, Math.floor(visibleWidth / columnWidth) - 1)
			scroller.scrollBy({ left: direction * columns * columnWidth, behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })
		},
		rememberOverviewPosition() {
			const target = this.$el?.closest?.('.app-content')
			const scroller = this.$refs.printSource?.$el ?? this.$refs.printSource
			this.overviewPosition = { top: target?.scrollTop || 0, left: scroller?.scrollLeft || 0 }
		},
		restoreOverviewPosition() {
			const target = this.$el?.closest?.('.app-content')
			if (target && this.overviewPosition) target.scrollTop = this.overviewPosition.top
			const scroller = this.$refs.printSource?.$el ?? this.$refs.printSource
			if (scroller && this.overviewPosition) scroller.scrollLeft = this.overviewPosition.left
			this.initialPeriodCentered = true
		},
		openDetails(detail) {
			if (!this.report || this.loading || this.error) return
			return this.$router.push({ name: 'annual-overview', query: { ...annualRouteQuery(this.selection), ...this.areaControlQuery, ...reportDetailQuery(detail, this.report.periods) } })
		},
		closeDetails() {
			const parent = reportDetailParentQuery(this.$route?.query || {})
			if (parent) {
				const route = { name: 'annual-overview', query: parent }
				const previousPath = this.$router.options?.history?.state?.back
				const previous = previousPath ? this.$router.resolve(previousPath) : null
				if (previous?.name === 'annual-overview' && reportDetailParentMatches(this.$route.query, previous.query)) this.$router.back()
				else this.$router.replace(route)
				return
			}
			if (this.openedDetailFromOverview) this.$router.back()
			else this.$router.replace({ name: 'annual-overview', query: { ...annualRouteQuery(this.selection), ...this.areaControlQuery } })
		},
		updateAreaControls(amountView, metric) {
			Object.assign(this, areaOverviewOptions({ areasView: amountView, areasMetric: metric }))
			return this.$router.replace({ name: 'annual-overview', query: { ...annualRouteQuery(this.selection), ...areaOverviewQuery(this.areaAmountView, this.areaMetric) } })
		},
		openSummaryDetails(row) {
			if (!this.report || this.loading || this.error) return
			const index = this.report.periods.length - 1
			return this.openDetails({ row, index, total: true, label: row.label })
		},
		rangeBucketLabel(period, short = false) {
			const date = new Date(period.dateFrom * 1000)
			const crossYear = this.selection.dateFrom?.slice(0, 4) !== this.selection.dateTo?.slice(0, 4)
			if (this.selection.period === 'day') return formatCalendarLabel(date, { day: 'numeric', month: 'short', ...(crossYear ? { year: '2-digit' } : {}) })
			if (this.selection.period === 'week') return `${this.$texts.annual.week(isoWeekNumber(date))}${crossYear ? ` ${isoWeekYear(date)}` : ''}`
			if (this.selection.timeRange === 'calendar-selection' && this.selection.period === 'quarter' && this.selection.periodMonths.length) return `${formatCalendarLabel(date, { day: 'numeric', month: 'short', year: 'numeric' })} – ${formatCalendarLabel(new Date(period.dateTo * 1000), { day: 'numeric', month: 'short' })}`
			if (this.selection.period === 'quarter') return `${this.$texts.annual.quarter(Math.floor(date.getMonth() / 3) + 1)}${crossYear ? ` ${date.getFullYear()}` : ''}`
			return formatCalendarLabel(date, { month: short ? 'short' : 'long', ...(crossYear ? { year: 'numeric' } : {}) })
		},
		selectReportPeriod(selection) {
			this.selection = annualSelection(selection)
			return this.updateSelection()
		},
		dailyDates() {
			const month = Number(this.selection.month)
			if (month >= 1 && month <= 12) {
				return Array.from({ length: new Date(this.selection.year, month, 0).getDate() }, (_, day) => new Date(this.selection.year, month - 1, day + 1))
			}
			return Array.from({ length: calendarDayCount(this.selection.year) }, (_, day) => new Date(this.selection.year, 0, day + 1))
		},
		showCsvMenu(open) {
			this.csvMenuOpen = open
			this.$nextTick(() => this.$refs[open ? 'csvBack' : 'csvTrigger']?.$el?.querySelector('button')?.focus())
		},
		async printReport() {
			if (this.loading || this.error || !this.report || this.printing || !this.$refs.printSource) return
			this.printing = true
			try {
				const printSource = this.$refs.overview || this.$refs.printSource?.$el || this.$refs.printSource
				const metadata = this.exportMetadata?.() || []
				const details = [this.reportScopeLabel, this.groupingLabel]
				if (reportContainsToday(this.selection)) {
					details.push(this.selection.includeFuturePayments ? this.$texts.annual.includeFuture() : `${this.$texts.annual.paymentsUntilToday()} (${formatCalendarLabel(new Date(), { dateStyle: 'medium' })})`)
				}
				const text = (fn, fallback = '') => typeof fn === 'function' ? fn() : fallback
				const incomesOn = this.$enableIncomes !== false
				const categoryMaps = annualFilterNameMaps(this.categoryChoices, [...(this.filterChoices?.categoryIds || []), ...(this.selection.categories || [])], { incomes: incomesOn })
				const partnerMaps = annualFilterNameMaps(this.partnerChoices, [...(this.filterChoices?.partnerIds || []), ...(this.selection.partners || [])], { incomes: incomesOn })
				details.push(...annualFilterChips(this.selection, {
					categories: this.categoryChoices || [],
					categoryNames: categoryMaps.names,
					categoryTypes: categoryMaps.types,
					partnerNames: partnerMaps.names,
					partnerTypes: partnerMaps.types,
					categoryGroupLabels: { income: text(this.$texts.filters?.allIncomeCategories), expense: text(this.$texts.filters?.allExpenseCategories) },
					partnerGroupLabels: { income: text(this.$texts.filters?.allIncomePartners), expense: text(this.$texts.filters?.allExpensePartners) },
					hashtagNames: new Map((this.hashtagChoices || []).map(hashtag => [Number(hashtag.id), hashtag.displayName || hashtag.name])),
					labelNames: {
						important: text(this.$texts.labels?.important),
						review: text(this.$texts.labels?.review),
						fixedCost: text(this.$texts.labels?.fixedCosts),
						childRelated: text(this.$texts.labels?.children),
						subscription: text(this.$texts.labels?.subscription),
						taxRelevant: text(this.$texts.labels?.taxRelevant),
						insurance: text(this.$texts.labels?.insurance),
					},
					noCategory: text(this.$texts.annual?.noCategory),
					noPartner: text(this.$texts.annual?.noPartner),
					or: text(this.$texts.annual?.or, 'or'),
					atLeast: text(this.$texts.annual?.atLeast, 'At least'),
					atMost: text(this.$texts.annual?.atMost, 'At most'),
					formatMoney: cents => typeof this.$formatMoneyFromCents === 'function' ? this.$formatMoneyFromCents(cents) : String(cents),
				}).map(chip => chip.label))
				if (this.selection.timeRange) {
					const buckets = this.report.periods.slice(0, -1)
					const groups = []
					buckets.forEach((period, index) => {
						const date = new Date(period.dateFrom * 1000)
						const label = this.selection.period === 'day' ? formatCalendarLabel(date, { month: 'long', year: 'numeric' }) : ''
						if (!groups.length || groups.at(-1).label !== label) groups.push({ label, columns: [] })
						groups.at(-1).columns.push(this.columns[index])
					})
					await printAnnualOverview(printSource, { title: `CoBudget – ${this.$texts.annual.title()} ${this.titleRangeLabel || this.selectedRangeLabel}`, details, metadata, months: this.columns.slice(0, -1), groups })
					return
				}
				const monthly = this.selection.period === 'month'
				const quarterly = this.selection.period === 'quarter'
				const weekly = this.selection.period === 'week'
				const daily = this.selection.period === 'day'
				const dailyMonth = daily && this.selection.month >= 1 && this.selection.month <= 12
				const monthDate = new Date(this.selection.year, (this.selection.month || 1) - 1, 1)
				const monthName = monthly || dailyMonth ? formatCalendarLabel(monthDate, { month: 'long' }) : ''
				const shortDay = date => formatCalendarLabel(date, { day: 'numeric', month: 'short' })
				// A whole-year daily print stays inside one calendar month, then splits into blocks of 12 days.
				const dayGroups = daily && !dailyMonth
					? Array.from({ length: 12 }, (_, month) => ({
						label: formatCalendarLabel(new Date(this.selection.year, month, 1), { month: 'long' }),
						columns: Array.from({ length: new Date(this.selection.year, month + 1, 0).getDate() }, (_, day) => shortDay(new Date(this.selection.year, month, day + 1))),
					}))
					: null
				const dailyDates = dailyMonth
					? Array.from({ length: new Date(this.selection.year, this.selection.month, 0).getDate() }, (_, day) => new Date(this.selection.year, this.selection.month - 1, day + 1))
					: []
				await printAnnualOverview(printSource, {
					title: daily
						? `CoBudget – ${this.$texts.annual.dailyTitle()} ${dailyMonth ? `${monthName} ` : ''}${this.selection.year}`
						: monthly
							? `CoBudget – ${this.$texts.annual.monthlyTitle()} ${monthName} ${this.selection.year}`
							: weekly
								? `CoBudget – ${this.$texts.annual.weeklyTitle()} ${this.selection.year}`
								: quarterly
									? `CoBudget – ${this.$texts.annual.quarterlyTitle()} ${this.selection.year}`
									: `CoBudget – ${this.$texts.annual.title()} ${this.selection.year}`,
					details,
					metadata,
					months: dayGroups
						? dayGroups.flatMap(group => group.columns)
						: daily
							? dailyDates.map(date => shortDay(date))
							: monthly
								? Array.from({ length: new Date(this.selection.year, this.selection.month, 0).getDate() }, (_, day) => shortDay(new Date(this.selection.year, this.selection.month - 1, day + 1)))
								: weekly
									? Array.from({ length: isoWeekCount(this.selection.year) }, (_, week) => this.$texts.annual.week(week + 1))
									: quarterly
										? [1, 2, 3, 4].map(quarter => this.$texts.annual.quarter(quarter))
										: Array.from({ length: 12 }, (_, month) => formatCalendarLabel(new Date(this.selection.year, month, 1), { month: 'short' })),
					...(dayGroups ? { groups: dayGroups } : {}),
				})
			} catch (error) {
				if (error?.code === 'REPORT_LOADING') showToast(this.$texts.common.loading(), 'info')
				else showRequestError(error, this.$texts.annual.printError())
			} finally {
				this.printing = false
			}
		},
		selectPeriod(requested) {
			if (!this.selection.timeRange && requested === 'day' && this.selection.period !== 'day') this.selection.month = 1
			this.selection.period = requested === 'quarter' || requested === 'week' || requested === 'day' ? requested : 'year'
			return this.updateSelection()
		},
		selectMonth(value) {
			if (value === 'all') {
				this.selection.month = 0
				return this.updateSelection()
			}
			const month = Number(value)
			if (!Number.isInteger(month) || month < 1 || month > 12) {
				return
			}
			this.selection.month = month
			return this.updateSelection()
		},
		selectYear(value) {
			const year = Number(value)
			if (!this.yearOptions.includes(year)) {
				return
			}
			if (year !== this.selection.year) this.selection.includeFuturePayments = false
			this.selection.year = year
			this.updateSelection()
		},
		selectAreas(values) {
			if (!Array.isArray(values) || values.some(id => id !== 0 && !this.projects.some(project => Number(project.id) === id))) return
			const ids = [...new Set(values)]
			this.selection.projects = ids.length > 1 ? ids : undefined
			return this.selectProject(ids.length === 1 ? ids[0] : null)
		},
		selectProject(value) {
			if (value !== null && value !== 0 && !this.projects.some(project => Number(project.id) === value)) return
			this.selection.projectId = value || null
			this.selection.withoutArea = value === 0
			this.selection.entryScope = 'personal'
			return this.updateSelection()
		},
		updateSelection() {
			// Keep keyboard focus on the selection control after changing its value.
			this.selection = annualSelection(this.selection)
			if (!this.$enableProjects) { this.selection.projectId = null; this.selection.withoutArea = false; this.selection.projects = undefined; this.selection.entryScope = 'personal' }
			return this.$router.replace({ name: 'annual-overview', query: { ...annualRouteQuery(this.selection), ...this.areaControlQuery } })
		},
		onFilterChange(filters) {
			this.selection = {
				...this.selection,
				search: filters.search,
			categories: filters.categories,
				partners: filters.partners,
				labels: filters.labels,
				hashtags: filters.hashtags,
				amountMin: filters.amountMin,
				amountMax: filters.amountMax,
			}
			return this.updateSelection()
		},
		resetFilters() {
			this.selection = {
				...this.selection,
				search: '', categories: [], partners: [], labels: [], hashtags: [],
				amountMin: null, amountMax: null, reportFilters: undefined, withoutArea: false,
			}
			return this.updateSelection()
		},
		clearFilter(key) {
			if (key === 'period') return this.selectReportPeriod({ ...this.selection, timeRange: 'current-year' })
			if (key === 'areas') return this.selectAreas([])
			const next = { ...this.selection }
			if (key === 'search') {
			next.search = ''
			} else if (key === 'withoutArea') {
				next.withoutArea = false
			} else if (key === 'amount') {
				next.amountMin = null
				next.amountMax = null
			} else {
				next[key] = []
			}
			this.selection = next
			return this.updateSelection()
		},
		annualExportSections() {
			return [
				...this.$refs.development.exportData(),
				...this.paymentTypes.flatMap(type => this.$refs.paymentSections.find(section => section.section === type).exportData()),
				...(this.$refs.totalSummary?.exportData() || []),
				...(this.$refs.areaOverview?.exportData() || []),
			]
		},
		exportMetadata() {
			return reportExportMetadata(this.selection, { report: this.report, scopeLabel: this.reportScopeLabel, groupingLabel: this.groupingLabel, breakdownLabel: this.breakdownLabel, currency: this.$currency,
				texts: this.$texts, categoryChoices: this.categoryChoices, partnerChoices: this.partnerChoices, hashtagChoices: this.hashtagChoices, filterChoices: this.filterChoices, incomes: this.$enableIncomes !== false,
				formatDate: timestamp => formatCalendarLabel(new Date(Number(timestamp) * 1000), { day: 'numeric', month: 'short', year: 'numeric' }), formatCents: this.$formatMoneyFromCents })
		},
		exportReport() { return this.runExport('download') },
		saveReportInNextcloud() { return this.runExport('save') },
		copyReport() { return this.runExport('copy') },
		runExport(action) {
			if (this.loading || this.error || !this.report || this.exporting) return
			this.exporting = true
			const onError = error => {
				if (error?.code === 'REPORT_LOADING') showToast(this.$texts.common.loading(), 'info')
				else showRequestError(error, action === 'copy' ? this.$texts.common.copyToClipboardError() : action === 'save' ? this.$texts.annual.saveInNextcloudError() : this.$texts.common.exportCsvError())
			}
			try {
				const context = { metadata: this.exportMetadata(), filename: this.annualExportFilename() }
				return deliverReportExport(action, this.annualExportSections(), context, this.$texts).catch(onError).finally(() => { this.exporting = false })
			} catch (error) { onError(error); this.exporting = false }
		},
		annualExportFilename() {
			const scope = this.selection.projectId ? `-area-${this.selection.projectId}-${this.selection.entryScope === 'shared' ? 'total' : 'personal'}` : ''
			if (this.selection.timeRange) return `cobudget-auswertung-${this.selection.dateFrom}-${this.selection.dateTo}-${this.selection.period}${scope}.csv`
			const year = this.selection.year
			const month = Number(this.selection.month)
			const monthText = String(month).padStart(2, '0')
			if (this.selection.period === 'month') return `cobudget-uebersicht-${year}-${monthText}.csv`
			if (this.selection.period === 'day' && month >= 1) return `cobudget-uebersicht-tage-${year}-${monthText}.csv`
			if (this.selection.period === 'day') return `cobudget-uebersicht-tage-${year}.csv`
			if (this.selection.period === 'week') return `cobudget-uebersicht-wochen-${year}.csv`
			if (this.selection.period === 'quarter') return `cobudget-uebersicht-quartale-${year}.csv`
			return `cobudget-uebersicht-${year}.csv`
		},
		groupRows(type) {
			const decorate = row => ({
				...row, key: `${type}-${row.reportKey ?? row.id}-${Boolean(row.direct)}`, type,
				label: row.areaId !== undefined ? (row.areaId === 0 ? this.$texts.annual.withoutArea() : row.name)
					: row.direct ? this.$texts.common.mainCategory()
						: row.id === 0 ? (this.selection.grouping === 'categories' ? this.$texts.annual.noCategory() : this.$texts.annual.noPartner()) : row.name,
				children: (row.children || []).map(decorate), areas: (row.areas || []).map(decorate),
			})
			return this.report.groups[type].map(decorate)
		},
		async load() {
			if (this.$route?.query) {
				const next = annualRouteQuery(this.selection)
				const stale = ['search', 'categories', 'partners', 'labels', 'hashtags', 'amountMin', 'amountMax', 'period', 'month', 'quarter', 'week', 'day', 'timeRange', 'periodYears', 'periodMonths', 'dateFrom', 'dateTo', 'entryScope'].some(key => String(this.$route.query[key] || '') !== String(next[key] || ''))
				if (stale) {
					if (this.$route.query.detailType !== undefined) await this.$router.replace({ name: 'annual-overview', query: { ...next, ...this.areaControlQuery, ...reportDetailNavigation(this.$route.query) } })
					else await this.updateSelection()
					return
				}
			}
			if (this.selection.timeRange && reportRangeError(this.selection)) { this.error = true; this.loading = false; return }
			const requestId = ++this.requestId
			this.loading = true
			this.error = false
			this.report = null
			this.initialPeriodCentered = false
			try {
				if (!this.$enableProjects && (this.selection.projectId || this.selection.withoutArea || this.selection.projects?.length)) {
					await this.updateSelection()
					if (requestId !== this.requestId) return
				}
				const [report, projects] = await Promise.all([
					axios.get(generateUrl('/apps/cobudget/api/annual-overview'), { params: annualRequestParams(this.selection) }),
					this.$enableProjects ? axios.get(generateUrl('/apps/cobudget/api/projects')) : Promise.resolve({ data: [] }),
				])
				if (requestId !== this.requestId) return
				if (this.selection.timeRange === 'all' && report.data.range) {
					this.selection = annualSelection({ ...this.selection, ...report.data.range })
				}
				this.report = report.data
				this.hashtagChoices = Array.isArray(report.data.hashtags) ? report.data.hashtags : []
				const choices = report.data.filterChoices || {}
				this.filterChoices = {
					categoryIds: Array.isArray(choices.categoryIds) ? choices.categoryIds : [],
					partnerIds: Array.isArray(choices.partnerIds) ? choices.partnerIds : [],
					labels: Array.isArray(choices.labels) ? choices.labels : [],
				}
				this.projects = sortAreasByName(projects.data)
				const years = report.data.years
				if (!this.selection.timeRange && years.length && !years.includes(this.selection.year)) {
					this.selection.year = years.find(year => year <= this.currentYear) ?? years[years.length - 1]
					this.selection.includeFuturePayments = false
					await this.updateSelection()
				}
			} catch {
				if (requestId === this.requestId) this.error = true
			} finally {
				if (requestId === this.requestId) {
					this.loading = false
					const center = () => {
						if (!this.overviewPosition || !this.openedDetailFromOverview) {
							this.scrollToCurrentPeriod?.()
						}
					}
					const schedule = typeof this.$nextTick === 'function' ? this.$nextTick.bind(this) : fn => fn()
					schedule(() => {
						if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
							window.requestAnimationFrame(center)
						} else {
							center()
						}
					})
				}
			}
		},
		async loadFilterChoices() {
			const requestId = ++this.filterChoiceRequest
			const params = this.selection.projectId ? { projectId: this.selection.projectId } : {}
			try {
				// Released personal payments can use the member's own categories and
				// partners as well as the area's original catalog.
				const scopes = [params, ...(this.selection.projectId && this.selection.entryScope !== 'shared' ? [{}] : [])]
				const catalogs = await Promise.all(scopes.map(params => Promise.all([
					axios.get(generateUrl('/apps/cobudget/api/categories'), { params }),
					axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params }),
				])))
				if (requestId !== this.filterChoiceRequest) return
				const choices = index => [...new Map(catalogs.flatMap(catalog => Array.isArray(catalog[index].data) ? catalog[index].data : []).map(item => [Number(item.id), item])).values()]
				this.categoryChoices = choices(0)
				this.partnerChoices = choices(1)
			} catch {
				if (requestId === this.filterChoiceRequest) {
					this.categoryChoices = []
					this.partnerChoices = []
				}
			}
		},
	},
}
</script>

<style scoped>
.annual-overview { min-width: 0; }
.annual-report-header { margin-block-end: 0; container: reports-toolbar / inline-size; }
.annual-toolbar-period { max-inline-size: 10rem; font-weight: var(--cobudget-filter-toolbar-font-weight); }
.annual-toolbar-period :deep(.report-period-caption) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
@container reports-toolbar (max-width: 1200px) {
	.annual-shortcut--labels { display: none; }
}
@container reports-toolbar (max-width: 1000px) {
	.annual-shortcut--partners { display: none; }
}
@container reports-toolbar (max-width: 820px) {
	.annual-shortcut--categories { display: none; }
}
@container reports-toolbar (max-width: 660px) {
	.annual-shortcut--areas { display: none; }
}
@container reports-toolbar (max-width: 460px) {
	.annual-toolbar-period { display: none; }
}
@container reports-toolbar (max-width: 400px) {
	.annual-main-filter :deep(.annual-filter-caption) { display: none; }
}
.annual-controls { display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: center; margin-block: calc(var(--default-grid-baseline, 4px) * 4); }
/* The native trigger border accounts for one pixel of the heading inset. */
.annual-controls > :first-child :deep(.action-item__menutoggle.button-vue) { padding-inline-start: calc(var(--default-grid-baseline, 4px) - 1px); }
.annual-actions-menu { flex-shrink: 0; }
.annual-report-header :deep(.app-page-header__title) { white-space: normal; overflow: visible; }
.annual-scroll {
	--annual-label-min: 180px;
	--annual-bucket-width: 108px;
	--annual-total-width: 108px;
	--annual-table-inline-borders: 0px;
	container-type: inline-size;
	overflow: auto;
	max-width: 100%;
	margin-top: 0;
	padding-block: var(--default-grid-baseline, 4px);
	background: var(--cobudget-surface, #fff);
	scroll-padding-inline: var(--annual-label-min) var(--annual-total-width);
	cursor: auto;
}
.annual-scroll.has-horizontal-overflow { cursor: grab; }
.annual-scroll.is-dragging { cursor: grabbing; }
.annual-scroll-content { width: 100%; min-width: 100%; }
.annual-scroll-content:has(.annual-table:not(.is-collapsed)) { min-width: calc(var(--annual-label-min, 180px) + (var(--annual-bucket-count, 12) + 1) * var(--annual-bucket-width, 108px) + var(--annual-table-inline-borders, 2px)); }
.annual-scroll:focus-visible { outline: var(--cobudget-focus-outline); outline-offset: -2px; }
.annual-error { padding: 20px; }
@media (min-width: 769px) {
	/* Match payment tables while keeping section headings at the content inset. */
	.annual-scroll,
	.annual-overview :deep(.annual-details-table),
	.annual-overview :deep(.report-breakdown-scroll),
	.annual-overview :deep(.report-forecast-scroll),
	.annual-overview :deep(.report-payments .entry-table-container) {
		width: calc(100% + var(--cobudget-content-inset-start) + var(--cobudget-content-inset-end));
		max-width: none;
		margin-inline-start: calc(0px - var(--cobudget-content-inset-start));
		margin-inline-end: calc(0px - var(--cobudget-content-inset-end));
	}
	.annual-scroll :deep(.report-payment-header),
	.annual-scroll :deep(.annual-section-header) {
		padding-inline-start: var(--cobudget-content-inset-start);
		padding-inline-end: var(--cobudget-content-inset-end);
	}
}
@media (max-width: 768px) {
	.annual-overview { --report-mobile-gutter: var(--cobudget-mobile-content-padding, calc(var(--default-grid-baseline, 4px) * 2.5)); }
	/* Tables use the available screen width; headings retain the page gutter. */
	.annual-scroll,
	.annual-overview :deep(.annual-details-table),
	.annual-overview :deep(.report-breakdown-scroll),
	.annual-overview :deep(.report-forecast-scroll) {
		width: calc(100% + var(--report-mobile-gutter) * 2);
		max-width: none;
		margin-inline: calc(-1 * var(--report-mobile-gutter));
		border-radius: 0;
	}
	.annual-scroll { --annual-label-min: 140px; --annual-table-inline-borders: 0px; }
	.annual-scroll-content { --annual-label-min: clamp(112px, calc(100cqi - var(--annual-total-width) - var(--annual-bucket-width) - var(--annual-table-inline-borders)), 140px); }
	.annual-scroll :deep(.report-payment-header),
	.annual-scroll :deep(.annual-section-header) { padding-inline: var(--report-mobile-gutter); }
	.annual-overview :deep(.report-development-controls),
	.annual-overview :deep(.annual-chart-controls),
	.annual-overview :deep(.annual-section-controls),
	.annual-overview :deep(.report-breakdown-type) { flex-basis: 100%; margin-inline-start: 0; justify-content: flex-start; }
	.annual-overview :deep(.report-area-controls) { justify-content: flex-start; }
	.annual-overview :deep(table) {
		/* Cell corners inherit the same square shape, including pinned columns. */
		--border-radius-large: 0px;
		border-radius: 0;
		border-inline-width: 0;
	}
	.annual-overview :deep(.report-payment-tab),
	.annual-overview :deep(.report-development-tab),
	.annual-overview :deep(.report-breakdown-tab) { background: transparent !important; overflow: visible; }
	/* Keep compact text tabs, with a full touch target and native keyboard focus. */
	.annual-overview :deep(.report-payment-tab::before),
	.annual-overview :deep(.report-development-tab::before),
	.annual-overview :deep(.report-breakdown-tab::before) {
		content: '';
		position: absolute;
		inset-inline: 0;
		inset-block-start: 50%;
		height: 100%;
		min-height: var(--default-clickable-area, 44px);
		transform: translateY(-50%);
	}
	/* Dropdowns already include vertical space in their 44px touch targets. */
	.annual-report-header { padding-block-end: 0; }
	.annual-report-header :deep(.app-page-header__title) { padding-block: 0; }
	.annual-controls { gap: 4px 8px; }
}
</style>
