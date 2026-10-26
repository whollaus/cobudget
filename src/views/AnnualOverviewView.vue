<template>
	<div class="annual-overview">
		<div v-show="!detailRequested" ref="overview">
		<AppPageHeader class="annual-report-header" :class="{ 'annual-report-header--stacked-period': titleRangeLabel.length > 4 }" :title="$texts.annual.title()">
			<template #title>
				<span class="annual-report-title"><span class="annual-report-name">{{ $texts.annual.title() }}</span><ReportPeriodPicker v-if="selection.timeRange" :selection="selection" :years="yearOptions" :label="titleRangeLabel || $texts.annual.selectedPeriod()" @change="selectReportPeriod" /></span>
			</template>
			<template #actions>
				<span class="annual-export-wrap" :title="exporting ? $texts.common.exportCsvBusy() : $texts.common.exportCsv()">
					<NcActions class="annual-export-menu" variant="tertiary" force-menu placement="bottom-end"
						:disabled="loading || error || !report || exporting"
						:aria-label="exporting ? $texts.common.exportCsvBusy() : $texts.common.exportCsv()">
						<template #icon><DownloadIcon :size="20" /></template>
						<NcActionButton close-after-click :disabled="exporting" :description="$texts.annual.downloadCsvHint()" @click="exportReport">
							<template #icon><DownloadIcon :size="20" /></template>
							{{ $texts.common.download() }}
						</NcActionButton>
						<NcActionButton close-after-click :disabled="exporting" :description="$texts.annual.saveInNextcloudHint()" @click="saveReportInNextcloud">
							<template #icon><CloudUploadOutlineIcon :size="20" /></template>
							{{ $texts.annual.saveInNextcloud() }}
						</NcActionButton>
						<NcActionButton close-after-click :disabled="exporting" @click="copyReport"><template #icon><ClipboardTextOutlineIcon :size="20" /></template>{{ $texts.common.copyToClipboard() }}</NcActionButton>
					</NcActions>
				</span>
				<NcButton variant="tertiary" class="cobudget-toolbar-icon-button" :disabled="loading || error || !report || printing"
					:aria-label="$texts.analytics.print()" :title="$texts.analytics.print()" @click="printReport">
					<template #icon><PrinterIcon :size="20" /></template>
				</NcButton>
			</template>
		</AppPageHeader>
		<div class="annual-controls">
			<label class="annual-select" :title="$texts.annual.breakdown()">
				<span aria-hidden="true"><span class="annual-select-label">{{ breakdownLabel }}</span><ChevronDownIcon :size="18" /></span>
				<select :value="selection.period" :aria-label="$texts.annual.breakdown()" @change="selectPeriod">
					<option value="quarter">{{ $texts.annual.quarters() }}</option>
					<option value="year">{{ $texts.annual.months() }}</option>
					<option value="week">{{ $texts.annual.weeks() }}</option>
					<option value="day">{{ $texts.annual.days() }}</option>
				</select>
			</label>
			<label v-if="!selection.timeRange" class="annual-select" :title="$texts.annual.year()">
				<span aria-hidden="true">{{ selection.year }}<ChevronDownIcon :size="18" /></span>
				<select :value="selection.year" :disabled="loading || !yearOptions.length" :aria-label="$texts.annual.year()" @change="selectYear">
					<option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
				</select>
			</label>
			<label v-if="!selection.timeRange && selection.period === 'day'" class="annual-select" :title="$texts.budgetGoals.month()">
				<span aria-hidden="true"><span class="annual-select-label">{{ selectedMonthLabel }}</span><ChevronDownIcon :size="18" /></span>
				<select :value="Number(selection.month) >= 1 ? selection.month : 'all'" :aria-label="$texts.budgetGoals.month()" @change="selectMonth">
					<option value="all">{{ $texts.annual.allDays() }}</option>
					<option v-for="month in monthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
				</select>
			</label>
			<label v-if="$enableProjects" class="annual-select" :title="scopeLabel">
				<span aria-hidden="true"><span class="annual-select-label">{{ scopeLabel }}</span><ChevronDownIcon :size="18" /></span>
				<select v-model="selection.projectId" :aria-label="$texts.annual.scope()" @change="selectProject">
					<option :value="null">{{ $texts.settings.myFinances() }}</option>
					<option v-for="project in projects" :key="project.id" :value="Number(project.id)">{{ project.name }}</option>
				</select>
			</label>
			<label v-if="$enableProjects && isSharedArea" class="annual-select" :title="$texts.annual.amountScope()">
				<span aria-hidden="true"><span class="annual-select-label">{{ amountScopeLabel }}</span><ChevronDownIcon :size="18" /></span>
				<select v-model="selection.entryScope" :aria-label="$texts.annual.amountScope()" @change="updateSelection">
					<option value="personal">{{ $texts.annual.personalShare() }}</option>
					<option value="shared">{{ $texts.annual.wholeArea() }}</option>
				</select>
			</label>
			<label class="annual-select" :title="$texts.annual.grouping()">
				<span aria-hidden="true"><span class="annual-select-label">{{ groupingLabel }}</span><ChevronDownIcon :size="18" /></span>
				<select v-model="selection.grouping" :aria-label="$texts.annual.grouping()" @change="updateSelection">
					<option value="categories">{{ $texts.annual.categories() }}</option>
					<option value="paymentPartners">{{ $texts.annual.partners() }}</option>
					<option value="labelsAndTags">{{ $texts.annual.labelsAndTags() }}</option>
				</select>
			</label>
			<label v-if="showsCutoff" class="annual-select" :title="$texts.annual.futureHint()">
				<span aria-hidden="true"><span class="annual-select-label">{{ selection.includeFuturePayments ? $texts.annual.includeFuture() : $texts.annual.paymentsUntilToday() }}</span><ChevronDownIcon :size="18" /></span>
				<select v-model="selection.includeFuturePayments" :aria-label="$texts.annual.selectedPeriod()" @change="updateSelection">
					<option :value="false">{{ $texts.annual.paymentsUntilToday() }}</option><option :value="true">{{ $texts.annual.includeFuture() }}</option>
				</select>
			</label>
			<AnnualOverviewFilters :selection="selection" :categories="categoryChoices" :partners="partnerChoices" :hashtags="hashtagChoices" :category-ids="filterChoices.categoryIds" :partner-ids="filterChoices.partnerIds" :label-keys="filterChoices.labels" @change="onFilterChange" @clear="clearFilter" />
		</div>
		<ReportDevelopment v-if="report && !loading && !error" ref="development" v-model:mode="chartMode" v-model:hidden-series="chartHiddenSeries"
			:report="report" :selection="selection" :labels="columns.slice(0, -1)" :label-titles="columnTitles.slice(0, -1)" :axis-labels="chartAxisLabels"
			:chart-period="selection.period" :summary-rows="totalRows" :period-label="titleRangeLabel" @details="openSummaryDetails" />
		<div v-if="error" class="annual-error" role="alert"><p>{{ $texts.annual.loadError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
		<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
		<div v-if="report" v-show="!loading && !error" ref="printSource" class="annual-scroll" tabindex="0" role="region" :aria-label="periodTitle" :style="bucketStyle">
			<div class="annual-scroll-content">
			<ReportPaymentSection v-for="type in paymentTypes" ref="paymentSections" :key="type" :section="type" :report="report" :selected-entry-id="selectedEntryId"
				:title="type === 'income' ? $texts.annual.income() : $texts.annual.expenses()" :first-heading="firstHeading"
				:columns="columns" :column-titles="columnTitles" :rows="groupRows(type)" :total-row="totalRows.find(row => row.type === type)" :selection="selection" :periods="report.periods" :empty-message="emptyMessage" @details="openDetails" @open-entry-sidebar="$emit('open-entry-sidebar', $event)" />
			<AnnualOverviewTable v-if="$enableIncomes && hasPayments" ref="totalSummary" section="total" :title="$texts.analytics.total()" :first-heading="$texts.analytics.total()" :columns="columns" :column-titles="columnTitles" :rows="totalRows" :selection="selection" :periods="report.periods" summary @details="openDetails" />
			<ReportAreaOverview v-if="$enableProjects && !selection.projectId && !selection.withoutArea" ref="areaOverview"
				:selection="selection" :report="report" :columns="columns" :column-titles="columnTitles" :amount-view="areaAmountView" :metric="areaMetric"
				@update:amount-view="updateAreaControls($event, areaMetric)" @update:metric="updateAreaControls(areaAmountView, $event)" />
			</div>
		</div>
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
				<NcButton variant="tertiary" @click="closeDetails">{{ $texts.annual.back() }}</NcButton>
				<p v-if="loading" role="status">{{ $texts.common.loading() }}</p>
				<div v-else-if="error" role="alert"><p>{{ $texts.annual.loadError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
				<p v-else role="status">{{ $texts.annual.detailUnavailable() }}</p>
			</div>
		</template>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import CloudUploadOutlineIcon from 'vue-material-design-icons/CloudUploadOutline.vue'
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
import { getAppLocale } from '../utils/formatMoney'
import { sortAreasByName } from '../utils/areaSorting'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import { annualFilterChips, annualFilterNameMaps, annualPaymentRoute, annualPeriodContainsToday, annualReportSelection, reportPeriodLabel, reportRangeError, reportContainsToday, parseReportDate, isoWeekNumber, isoWeekYear, annualRequestParams, annualRouteQuery, annualSelection, calendarDayCount, isoWeekCount, isoWeekStart } from '../services/annualOverview'
import { printAnnualOverview } from '../services/annualOverviewPrint'
import { reportExportMetadata } from '../services/reportExport'
import { deliverReportExport } from '../services/reportExportActions'
import { formatCalendarLabel } from '../utils/formatDate'
import { showRequestError, showToast } from '../services/notifications'
import { REMOTE_DATA_CHANGED_EVENT } from '../services/dataSync'
import { reportDetailNavigation, reportDetailQuery, reportDetailParentQuery, reportDetailParentMatches, resolveReportDetail, areaOverviewOptions, areaOverviewQuery } from '../services/reportDetails'

export default {
	name: 'AnnualOverviewView',
	components: { NcButton, NcActions, NcActionButton, ChevronDownIcon, CloudUploadOutlineIcon, DownloadIcon, PrinterIcon, ClipboardTextOutlineIcon, AppPageHeader, AnnualOverviewTable, ReportPaymentSection, ReportDetailView, AnnualOverviewFilters, ReportPeriodPicker, ReportAreaOverview, ReportAreaDetailView, ReportDevelopment },
	provide() { return { reportExportContext: () => ({ metadata: this.exportMetadata(), filename: this.annualExportFilename() }) } },
	props: { selectedEntryId: { type: [Number, String], default: null }, hideNewPaymentAction: { type: Boolean, default: false } },
	emits: ['open-entry-sidebar', 'selected-entry-missing'],
	data() { const selection = annualReportSelection(this.$route.query); return { selection, ...areaOverviewOptions(this.$route.query), chartMode: 'cumulative', chartHiddenSeries: [], projects: [], categoryChoices: [], partnerChoices: [], hashtagChoices: [], filterChoices: { categoryIds: [], partnerIds: [], labels: [] }, report: null, loading: true, printing: false, exporting: false, error: false, requestId: 0, filterChoiceRequest: 0, overviewPosition: null, openedDetailFromOverview: false } },
	computed: {
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
			const dateFormat = new Intl.DateTimeFormat(getAppLocale(), { day: '2-digit', month: '2-digit', year: 'numeric' })
			return reportPeriodLabel(this.selection, formatCalendarLabel, date => dateFormat.format(date))
		},
		selectedRangeLabel() {
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
		scopeLabel() { return this.selection.projectId ? this.projects.find(project => Number(project.id) === this.selection.projectId)?.name || this.$texts.annual.scope() : this.$texts.settings.myFinances() },
		isSharedArea() { return Number(this.projects.find(project => Number(project.id) === this.selection.projectId)?.member_count || 0) > 1 },
		amountScopeLabel() { return this.selection.entryScope === 'shared' ? this.$texts.annual.wholeArea() : this.$texts.annual.personalShare() },
		reportScopeLabel() { return this.isSharedArea ? `${this.scopeLabel} · ${this.amountScopeLabel}` : this.scopeLabel },
		filterCatalogKey() { return `${this.selection.projectId || ''}:${this.selection.entryScope}` },
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
	mounted() {
		this.load()
		this.loadFilterChoices()
		window.addEventListener(REMOTE_DATA_CHANGED_EVENT, this.load)
		window.addEventListener('cobudget-data-changed', this.load)
		window.addEventListener('entry-saved', this.load)
	},
	beforeUnmount() {
		this.requestId++
		this.filterChoiceRequest++
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.load)
		window.removeEventListener('cobudget-data-changed', this.load)
		window.removeEventListener('entry-saved', this.load)
	},
	methods: {
		rememberOverviewPosition() {
			const target = this.$el?.closest?.('.app-content')
			this.overviewPosition = { top: target?.scrollTop || 0, left: this.$refs.printSource?.scrollLeft || 0 }
		},
		restoreOverviewPosition() {
			const target = this.$el?.closest?.('.app-content')
			if (target && this.overviewPosition) target.scrollTop = this.overviewPosition.top
			if (this.$refs.printSource && this.overviewPosition) this.$refs.printSource.scrollLeft = this.overviewPosition.left
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
		async printReport() {
			if (this.loading || this.error || !this.report || this.printing || !this.$refs.printSource) return
			this.printing = true
			try {
				const printSource = this.$refs.overview || this.$refs.printSource
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
		selectPeriod(event) {
			const requested = event.target.value
			if (!this.selection.timeRange && requested === 'day' && this.selection.period !== 'day') this.selection.month = 1
			this.selection.period = requested === 'quarter' || requested === 'week' || requested === 'day' ? requested : 'year'
			return this.updateSelection(event)
		},
		selectMonth(event) {
			if (event.target.value === 'all') {
				this.selection.month = 0
				return this.updateSelection(event)
			}
			const month = Number(event.target.value)
			if (!Number.isInteger(month) || month < 1 || month > 12) {
				event.target.blur()
				return
			}
			this.selection.month = month
			return this.updateSelection(event)
		},
		selectYear(event) {
			const year = Number(event.target.value)
			if (!this.yearOptions.includes(year)) {
				event.target.blur()
				return
			}
			if (year !== this.selection.year) this.selection.includeFuturePayments = false
			this.selection.year = year
			this.updateSelection(event)
		},
		selectProject() {
			this.selection.entryScope = 'personal'
			return this.updateSelection()
		},
		updateSelection(event) {
			// Keep keyboard focus on the selection control after changing its value.
			this.selection = annualSelection(this.selection)
			if (!this.$enableProjects) { this.selection.projectId = null; this.selection.entryScope = 'personal' }
			return this.$router.replace({ name: 'annual-overview', query: { ...annualRouteQuery(this.selection), ...this.areaControlQuery } })
		},
		onFilterChange(filters) {
			this.selection = {
				...this.selection,
				categories: filters.categories,
				partners: filters.partners,
				labels: filters.labels,
				hashtags: filters.hashtags,
				amountMin: filters.amountMin,
				amountMax: filters.amountMax,
			}
			return this.updateSelection()
		},
		clearFilter(key) {
			const next = { ...this.selection }
			if (key === 'amount') {
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
			return this.report.groups[type].map(row => ({
				...row, key: `${type}-${row.id}`, type,
				label: row.id === 0 ? (this.selection.grouping === 'categories' ? this.$texts.annual.noCategory() : this.$texts.annual.noPartner()) : row.name,
			}))
		},
		async load() {
			if (this.$route?.query) {
				const next = annualRouteQuery(this.selection)
				const stale = ['categories', 'partners', 'labels', 'hashtags', 'amountMin', 'amountMax', 'period', 'month', 'quarter', 'week', 'day', 'timeRange', 'dateFrom', 'dateTo', 'entryScope'].some(key => String(this.$route.query[key] || '') !== String(next[key] || ''))
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
			try {
				if (!this.$enableProjects && this.selection.projectId) {
					await this.updateSelection()
					if (requestId !== this.requestId) return
				}
				const [report, projects] = await Promise.all([
					axios.get(generateUrl('/apps/cobudget/api/annual-overview'), { params: annualRequestParams(this.selection) }),
					this.$enableProjects ? axios.get(generateUrl('/apps/cobudget/api/projects')) : Promise.resolve({ data: [] }),
				])
				if (requestId !== this.requestId) return
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
				if (requestId === this.requestId) this.loading = false
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
.annual-export-wrap { display: inline-flex; }
.annual-export-menu { display: inline-flex; }
.annual-controls { display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: center; margin-block: 12px 4px; }
.annual-select { position: relative; display: inline-flex; min-width: 0; max-width: 100%; border-radius: var(--border-radius-element, 8px); color: var(--cobudget-text-muted); font-weight: 400; }
.annual-select > span { display: flex; align-items: center; gap: 4px; min-width: 0; min-height: 36px; padding-inline: 8px; }
.annual-select-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.annual-select :deep(.material-design-icon) { flex-shrink: 0; }
.annual-select:hover { background: var(--cobudget-surface-muted); }
.annual-select:focus-within { outline: var(--cobudget-focus-outline); outline-offset: -2px; }
.annual-select select:disabled { cursor: default; }
.annual-select select { position: absolute; inset: 0; width: 100%; height: 100%; min-height: 0; max-width: 100%; margin: 0; opacity: 0; cursor: pointer; }
.annual-report-title { display: flex; flex-wrap: wrap; align-items: center; gap: var(--default-grid-baseline, 4px); min-width: 0; max-width: 100%; }
.annual-report-header :deep(.app-page-header__title) { white-space: normal; overflow: visible; }
.annual-report-header :deep(.app-page-header__title > .annual-report-title) { flex: 1 1 auto; }
.annual-scroll {
	--annual-label-min: 180px;
	--annual-bucket-width: 108px;
	--annual-total-width: 108px;
	--annual-table-inline-borders: 2px;
	container-type: inline-size;
	overflow: auto;
	max-width: 100%;
	margin-top: 0;
	background: var(--cobudget-surface, #fff);
	scroll-padding-inline: var(--annual-label-min) var(--annual-total-width);
}
.annual-scroll-content { width: 100%; min-width: 100%; }
.annual-scroll-content:has(.annual-table:not(.is-collapsed)) { min-width: calc(var(--annual-label-min, 180px) + (var(--annual-bucket-count, 12) + 1) * var(--annual-bucket-width, 108px) + var(--annual-table-inline-borders, 2px)); }
.annual-scroll:focus-visible { outline: var(--cobudget-focus-outline); }
.annual-error { padding: 20px; }
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
	.annual-controls { gap: 4px 8px; margin-block-start: 0; }
	.annual-controls .annual-select > span { min-height: 44px; }
	/* Only the first line reserves space for export and print. */
	.annual-report-header--stacked-period { display: block; }
	.annual-report-header--stacked-period .annual-report-title { flex-direction: column; align-items: flex-start; gap: 0; }
	.annual-report-header--stacked-period .annual-report-name { display: flex; align-items: center; min-height: var(--cobudget-mobile-touch-size, 44px); max-width: calc(100% - var(--cobudget-mobile-touch-size, 44px) * 2 - var(--default-grid-baseline, 4px) * 3); }
	.annual-report-header--stacked-period :deep(.app-page-header__actions) { position: absolute; inset-block-start: 3px; inset-inline-end: var(--app-page-header-mobile-gutter); }
}
</style>
