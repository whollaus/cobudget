<template>
	<div class="annual-overview">
		<AppPageHeader :title="$texts.annual.title()">
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
					</NcActions>
				</span>
				<NcButton variant="tertiary" class="cobudget-toolbar-icon-button" :disabled="loading || error || !report || printing"
					:aria-label="$texts.analytics.print()" :title="$texts.analytics.print()" @click="printReport">
					<template #icon><PrinterIcon :size="20" /></template>
				</NcButton>
			</template>
		</AppPageHeader>
		<MobileReportSwitch />
		<div class="annual-controls">
			<label class="annual-select" :title="$texts.annual.breakdown()">
				<span aria-hidden="true"><span class="annual-select-label">{{ breakdownLabel }}</span><ChevronDownIcon :size="18" /></span>
				<select :value="selection.period" :aria-label="$texts.annual.breakdown()" @change="selectPeriod">
					<option value="year">{{ $texts.annual.months() }}</option>
					<option value="quarter">{{ $texts.annual.quarters() }}</option>
					<option value="week">{{ $texts.annual.weeks() }}</option>
					<option value="day">{{ $texts.annual.days() }}</option>
				</select>
			</label>
			<label class="annual-select" :title="$texts.annual.year()">
				<span aria-hidden="true">{{ selection.year }}<ChevronDownIcon :size="18" /></span>
				<select :value="selection.year" :disabled="loading || !yearOptions.length" :aria-label="$texts.annual.year()" @change="selectYear">
					<option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
				</select>
			</label>
			<label v-if="selection.period === 'day'" class="annual-select" :title="$texts.budgetGoals.month()">
				<span aria-hidden="true"><span class="annual-select-label">{{ selectedMonthLabel }}</span><ChevronDownIcon :size="18" /></span>
				<select :value="Number(selection.month) >= 1 ? selection.month : 'all'" :aria-label="$texts.budgetGoals.month()" @change="selectMonth">
					<option value="all">{{ $texts.annual.allDays() }}</option>
					<option v-for="month in monthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
				</select>
			</label>
			<label v-if="$enableProjects" class="annual-select" :title="scopeLabel">
				<span aria-hidden="true"><span class="annual-select-label">{{ scopeLabel }}</span><ChevronDownIcon :size="18" /></span>
				<select v-model="selection.projectId" :aria-label="$texts.annual.scope()" @change="updateSelection">
					<option :value="null">{{ $texts.settings.myFinances() }}</option>
					<option v-for="project in projects" :key="project.id" :value="Number(project.id)">{{ project.name }}</option>
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
		<AnnualOverviewChart v-if="report && !loading && !error"
			v-model:mode="chartMode"
			v-model:hidden-series="chartHiddenSeries"
			:report="report"
			:labels="columns.slice(0, -1)"
			:label-titles="columnTitles.slice(0, -1)"
			:axis-labels="chartAxisLabels"
			:income-enabled="$enableIncomes"
			:format-cents="$formatMoneyFromCents" />
		<div v-if="error" class="annual-error" role="alert"><p>{{ $texts.annual.loadError() }}</p><NcButton @click="load">{{ $texts.annual.retry() }}</NcButton></div>
		<p v-else-if="loading" role="status">{{ $texts.common.loading() }}</p>
		<div v-else-if="report" ref="printSource" class="annual-scroll" tabindex="0" role="region" :aria-label="periodTitle" :style="bucketStyle">
			<AnnualOverviewTable v-for="type in paymentTypes" :key="`${selection.grouping}-${selection.projectId}-${selection.period}-${selection.month}-${selection.quarter}-${type}`" :section="type"
				:title="type === 'income' ? $texts.annual.income() : $texts.annual.expenses()" :first-heading="firstHeading"
				:columns="columns" :column-titles="columnTitles" :rows="groupRows(type)" :total-row="totalRows.find(row => row.type === type)" :selection="selection" :periods="report.periods" :empty-message="emptyMessage" @details="detail = $event" />
			<AnnualOverviewTable v-if="$enableIncomes && hasPayments" section="total" :title="$texts.analytics.total()" :first-heading="$texts.analytics.total()" :columns="columns" :column-titles="columnTitles" :rows="totalRows" :selection="selection" :periods="report.periods" summary @details="detail = $event" />
		</div>
		<AnnualOverviewDetails v-if="detail && report && !loading" :detail="detail" :report="report" :selection="selection" :scope-label="scopeLabel" @close="detail = null" />
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
import AppPageHeader from '../components/AppPageHeader.vue'
import MobileReportSwitch from '../components/MobileReportSwitch.vue'
import AnnualOverviewTable from '../components/AnnualOverviewTable.vue'
import AnnualOverviewDetails from '../components/AnnualOverviewDetails.vue'
import AnnualOverviewFilters from '../components/AnnualOverviewFilters.vue'
import AnnualOverviewChart from '../components/AnnualOverviewChart.vue'
import axios from '../services/http'
import { getCurrentUser } from '@nextcloud/auth'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import { annualFilterChips, annualFilterNameMaps, annualPeriodContainsToday, annualRequestParams, annualRouteQuery, annualSelection, buildAnnualOverviewCsv, calendarDayCount, downloadTextFile, encodeNextcloudPath, isoWeekCount, isoWeekStart, nextcloudCopyName, nextcloudFilePath } from '../services/annualOverview'
import { printAnnualOverview } from '../services/annualOverviewPrint'
import { formatCalendarLabel, getAppLanguage } from '../utils/formatDate'
import { showRequestError, showToast } from '../services/notifications'
import { REMOTE_DATA_CHANGED_EVENT } from '../services/dataSync'

export default {
	name: 'AnnualOverviewView',
	components: { NcButton, NcActions, NcActionButton, ChevronDownIcon, CloudUploadOutlineIcon, DownloadIcon, PrinterIcon, AppPageHeader, MobileReportSwitch, AnnualOverviewTable, AnnualOverviewDetails, AnnualOverviewFilters, AnnualOverviewChart },
	data() { return { selection: annualSelection(this.$route.query), chartMode: 'cumulative', chartHiddenSeries: [], projects: [], categoryChoices: [], partnerChoices: [], hashtagChoices: [], filterChoices: { categoryIds: [], partnerIds: [], labels: [] }, report: null, detail: null, loading: true, printing: false, exporting: false, error: false, requestId: 0, filterChoiceRequest: 0 } },
	computed: {
		currentYear() { return new Date().getFullYear() },
		paymentTypes() { return this.$enableIncomes ? ['income', 'expense'] : ['expense'] },
		hasPayments() {
			if (!this.report) return false
			return Boolean(this.report.groups.income.length || this.report.groups.expense.length)
		},
		yearOptions() { return this.report?.years || [] },
		scopeLabel() { return this.selection.projectId ? this.projects.find(project => Number(project.id) === this.selection.projectId)?.name || this.$texts.annual.scope() : this.$texts.settings.myFinances() },
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
			return annualPeriodContainsToday(this.selection.year, this.selection.period, this.selection.month)
		},
		dayCount() { return new Date(this.selection.year, this.selection.month, 0).getDate() },
		monthOptions() { return Array.from({ length: 12 }, (_, month) => ({ value: month + 1, label: formatCalendarLabel(new Date(this.selection.year, month, 1), { month: 'long' }) })) },
		selectedMonthLabel() {
			const month = Number(this.selection.month)
			if (!Number.isInteger(month) || month < 1 || month > 12) return this.$texts.annual.allDays()
			return formatCalendarLabel(new Date(this.selection.year, month - 1, 1), { month: 'long' })
		},
		columns() {
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
		'$route.query': { handler() { this.selection = annualSelection(this.$route.query); this.load() }, deep: true },
		'selection.projectId'() {
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
	},
	beforeUnmount() {
		this.requestId++
		this.filterChoiceRequest++
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.load)
		window.removeEventListener('cobudget-data-changed', this.load)
	},
	methods: {
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
				const details = [this.scopeLabel, this.groupingLabel]
				if (annualPeriodContainsToday(this.selection.year, this.selection.period, this.selection.month)) {
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
				await printAnnualOverview(this.$refs.printSource, {
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
			} finally {
				this.printing = false
			}
		},
		selectPeriod(event) {
			const requested = event.target.value
			if (requested === 'day' && this.selection.period !== 'day') this.selection.month = 1
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
		updateSelection(event) {
			// Keep keyboard focus on the selection control after changing its value.
			this.selection = annualSelection(this.selection)
			if (!this.$enableProjects) this.selection.projectId = null
			return this.$router.replace({ name: 'annual-overview', query: annualRouteQuery(this.selection) })
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
			const sections = this.paymentTypes.map(type => {
				const rows = []
				for (const row of this.sortedExportRows(this.groupRows(type))) {
					rows.push(this.exportAmountRow(row.label, row))
					for (const child of row.children || []) {
						const name = child.direct ? this.$texts.common.mainCategory() : child.name
						rows.push(this.exportAmountRow(`${row.label} · ${name}`, child))
					}
				}
				const total = this.totalRows.find(item => item.type === type)
				if (total) rows.push(this.exportAmountRow(this.$texts.entry.groupTotal(total.label), total))
				return { title: type === 'income' ? this.$texts.annual.income() : this.$texts.annual.expenses(), rows }
			})
			if (this.$enableIncomes !== false && this.hasPayments) {
				sections.push({
					title: this.$texts.analytics.total(),
					rows: this.totalRows.map(row => this.exportAmountRow(row.label, row, row.type === 'all')),
				})
			}
			return sections
		},
		exportReport() {
			if (this.loading || this.error || !this.report || this.exporting) return
			this.exporting = true
			try {
				downloadTextFile(this.annualExportFilename(), buildAnnualOverviewCsv({
					labelHeading: this.firstHeading,
					columns: this.columns,
					sections: this.annualExportSections(),
				}))
				showToast(this.$texts.common.exportCsvCreated())
			} catch (error) {
				showRequestError(error, this.$texts.common.exportCsvError(), 'Failed to export annual overview')
			} finally {
				this.exporting = false
			}
		},
		async saveReportInNextcloud() {
			if (this.loading || this.error || !this.report || this.exporting) return
			this.exporting = true
			try {
				const filename = this.annualExportFilename()
				const contents = buildAnnualOverviewCsv({
					labelHeading: this.firstHeading,
					columns: this.columns,
					sections: this.annualExportSections(),
				})
				let folder
				try {
					folder = await getFilePickerBuilder(this.$texts.annual.saveInNextcloud())
						.setMultiSelect(false)
						.allowDirectories(true)
						.setMimeTypeFilter(['httpd/unix-directory'])
						.addButton({ label: this.$texts.annual.saveHere(), variant: 'primary', callback() {} })
						.build()
						.pick()
				} catch (error) {
					if (error?.name === 'FilePickerClosed') return
					throw error
				}
				const uid = getCurrentUser()?.uid || ''
				if (!uid) throw new Error('Missing Nextcloud user')
				let storedName = filename
				for (let copy = 0; copy < 100; copy++) {
					storedName = nextcloudCopyName(filename, copy)
					const path = nextcloudFilePath(folder, storedName)
					try {
						await axios.put(generateRemoteUrl(`dav/files/${encodeURIComponent(uid)}${encodeNextcloudPath(path)}`), contents, {
							headers: { 'Content-Type': 'text/csv; charset=utf-8', 'If-None-Match': '*' },
						})
						showToast(this.$texts.annual.savedInNextcloud(storedName))
						return
					} catch (error) {
						if (error?.response?.status !== 412) throw error
					}
				}
				throw new Error('No free Nextcloud file name')
			} catch (error) {
				showRequestError(error, this.$texts.annual.saveInNextcloudError(), 'Failed to save annual overview in Nextcloud')
			} finally {
				this.exporting = false
			}
		},
		annualExportFilename() {
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
		sortedExportRows(rows) {
			const compare = (a, b) => String(a.label ?? a.name ?? '').localeCompare(String(b.label ?? b.name ?? ''), getAppLanguage(), { sensitivity: 'base' })
			return [...rows].sort(compare).map(row => ({
				...row,
				children: [...(row.children || [])].sort((a, b) => Number(Boolean(b.direct)) - Number(Boolean(a.direct)) || compare(a, b)),
			}))
		},
		exportAmountRow(label, row, balance = row.type === 'all') {
			return { label, amounts: [...(row.months || []), row.total], counts: row.counts || [], balance }
		},
		groupRows(type) {
			return this.report.groups[type].map(row => ({
				...row, key: `${type}-${row.id}`, type,
				label: row.id === 0 ? (this.selection.grouping === 'categories' ? this.$texts.annual.noCategory() : this.$texts.annual.noPartner()) : row.name,
			}))
		},
		async load() {
			this.detail = null
			if (this.$route?.query) {
				const next = annualRouteQuery(this.selection)
				const stale = ['categories', 'partners', 'labels', 'hashtags', 'amountMin', 'amountMax', 'period', 'month', 'quarter', 'week', 'day'].some(key => String(this.$route.query[key] || '') !== String(next[key] || ''))
				if (stale) {
					await this.updateSelection()
					return
				}
			}
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
				this.projects = [...projects.data].sort((a, b) => a.name.localeCompare(b.name))
				const years = report.data.years
				if (years.length && !years.includes(this.selection.year)) {
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
				const [categories, partners] = await Promise.all([
					axios.get(generateUrl('/apps/cobudget/api/categories'), { params }),
					axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params }),
				])
				if (requestId !== this.filterChoiceRequest) return
				this.categoryChoices = Array.isArray(categories.data) ? categories.data : []
				this.partnerChoices = Array.isArray(partners.data) ? partners.data : []
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
.annual-select:focus-within { outline: 2px solid var(--cobudget-primary); outline-offset: -2px; }
.annual-select select:disabled { cursor: default; }
.annual-select select { position: absolute; inset: 0; width: 100%; height: 100%; min-height: 0; max-width: 100%; margin: 0; opacity: 0; cursor: pointer; }
.annual-scroll {
	--annual-label-min: 180px;
	--annual-bucket-width: 108px;
	--annual-total-width: 108px;
	container-type: inline-size;
	overflow: auto;
	max-width: 100%;
	margin-top: 20px;
	background: var(--cobudget-surface, #fff);
	scroll-padding-inline: var(--annual-label-min) var(--annual-total-width);
}
.annual-scroll:focus-visible { outline: 2px solid var(--color-primary-element); }
.annual-error { padding: 20px; }
@media (max-width: 768px) { .annual-controls { gap: 4px 8px; } .annual-controls .annual-select > span { min-height: 44px; } }
</style>
