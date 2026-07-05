<template>
	<div ref="printSource" class="analytics-view">
		<AppPageHeader
			class="analytics-page-header"
			:title="$texts.analytics.title()"
			:subtitle="$texts.analytics.subtitle()">
			<template #actions>
				<div class="no-print">
				<NcButton
					variant="tertiary"
					class="cobudget-toolbar-icon-button"
					:aria-label="$texts.analytics.print()"
					:title="$texts.analytics.print()"
					@click="printReport">
					<template #icon>
						<PrinterIcon :size="20" />
					</template>
				</NcButton>
				</div>
			</template>
		</AppPageHeader>

		<div class="analytics-section">
			<AnalyticsPeriodSwitch
				:options="periodOptions"
				:selected="selectedPeriod"
				:aria-label="$texts.analytics.selectPeriod()"
				@select="selectPeriod" />

			<div v-if="loading" class="analytics-empty">
				{{ $texts.analytics.loading() }}
			</div>

			<div v-else-if="!hasEntries" class="analytics-empty">
				{{ $texts.analytics.noBookings() }}
			</div>

			<div v-else class="analytics-report">
				<div class="print-meta">
					{{ $texts.analytics.printMeta(periodLabel) }}
				</div>

				<AnalyticsSummaryGrid
					class="summary-grid"
					:cards="summaryCards"
					:aria-label="$texts.analytics.keyFigures()" />

				<AnalyticsInsightsSection
					v-if="insightItems.length"
					class="analytics-card insights-card"
					:items="insightItems"
					@open="openInsight" />

				<section v-if="budgetHistoryItems.length" class="analytics-card budget-history-card">
					<div class="card-header">
						<div>
							<h3>{{ $texts.analytics.budgetHistory() }}</h3>
							<p>{{ $texts.analytics.budgetHistoryHint() }}</p>
						</div>
						<div class="budget-history-summary">
							<span><strong>{{ budgetHistorySummary.ok || 0 }}</strong> {{ $texts.analytics.inPlan() }}</span>
							<span><strong>{{ budgetHistoryCriticalCount }}</strong> {{ $texts.analytics.critical() }}</span>
							<span :class="amountClass(budgetHistorySummary.bufferCents || 0)">
								<strong>{{ formatSignedCents(budgetHistorySummary.bufferCents || 0) }}</strong> {{ $texts.analytics.buffer() }}
							</span>
						</div>
					</div>
					<table class="budget-history-table">
						<thead>
							<tr>
								<th>{{ $texts.analytics.budgetGoal() }}</th>
								<th>{{ $texts.analytics.used() }}</th>
								<th>{{ $texts.analytics.budget() }}</th>
								<th>{{ $texts.analytics.buffer() }}</th>
								<th>{{ $texts.analytics.status() }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="item in budgetHistoryItems" :key="item.id">
								<td>
									<strong>{{ item.name }}</strong>
									<span>{{ budgetHistoryPeriodLabel(item) }} · {{ budgetHistoryReasonLabel(item.reason) }}</span>
								</td>
								<td class="progress-cell">{{ Math.round(item.progressPercent || 0) }} %</td>
								<td>{{ formatCents(item.spentCents) }} von {{ formatCents(item.amountCents) }}</td>
								<td :class="['amount-cell', amountClass(item.bufferCents)]">{{ formatSignedCents(item.bufferCents) }}</td>
								<td>
									<span :class="['budget-history-status', `status-${item.status || 'ok'}`]">
										{{ budgetHistoryStatusLabel(item.status) }}
									</span>
								</td>
							</tr>
						</tbody>
					</table>
				</section>

				<AnalyticsDevelopmentChart
					class="analytics-card development-card"
					:development-label="developmentLabel"
					:zero-line-y="zeroLineY"
					:cumulative-chart-points="cumulativeChartPoints"
					:last-chart-point="lastChartPoint"
					:series="series"
					:bar-height="barHeight"
					:show-series-label="showSeriesLabel" />

				<AnalyticsForecastCard
					v-if="availableForecast"
					class="analytics-card available-forecast-card"
					:forecast="availableForecast"
					:title="availableForecastTitle"
					:tooltip="availableForecastTooltip"
					:main-label="availableForecastMainLabel"
					:remaining-label="availableForecastRemainingLabel"
					:range-label="availableForecastRangeLabel"
					:amount-class="amountClass"
					:format-cents="formatCents"
					:format-signed-cents="formatSignedCents" />

				<section v-if="comparison" class="analytics-card comparison-card">
					<div class="card-header">
						<div>
							<h3>{{ $texts.analytics.currentMonth() }}</h3>
							<p>{{ $texts.analytics.previousMonthComparison() }}</p>
						</div>
					</div>
					<div class="comparison-grid">
						<div v-for="item in comparisonCards" :key="item.key">
							<span>{{ item.label }}</span>
							<strong :class="item.className">{{ item.value }}</strong>
							<small>{{ item.detail }}</small>
						</div>
					</div>
				</section>

				<section class="analytics-card breakdown-card">
					<div class="card-header">
						<div>
							<h3>{{ $texts.analytics.focusAreas() }}</h3>
							<p>{{ $texts.analytics.focusAreasHint() }}</p>
						</div>
						<div class="mini-switch no-print">
							<button type="button" :class="{ active: breakdownType === 'expense' }" @click="breakdownType = 'expense'">{{ $texts.analytics.expenses() }}</button>
							<button type="button" :class="{ active: breakdownType === 'income' }" @click="breakdownType = 'income'">{{ $texts.analytics.income() }}</button>
						</div>
					</div>

					<div v-if="breakdownSections.length > 1 || activeCategoryDrilldownLabel || activePaymentPartnerDrilldownLabel || activeTagDrilldownLabel || activeHashtagDrilldownLabel || activeProjectDrilldownLabel" class="breakdown-toolbar no-print">
						<div v-if="breakdownSections.length > 1" class="dimension-switch" role="tablist" :aria-label="$texts.analytics.selectFocus()">
							<button
								v-for="section in breakdownSections"
								:key="section.key"
								type="button"
								:class="{ active: activeBreakdownSection?.key === section.key }"
								:aria-selected="activeBreakdownSection?.key === section.key"
								@click="selectedBreakdownDimension = section.key">
								{{ section.shortTitle }}
							</button>
						</div>
						<button v-if="activeCategoryDrilldownLabel" type="button" class="drilldown-clear-button" @click="clearCategoryDrilldown(true)">
							{{ $texts.analytics.showAllCategories() }}
						</button>
						<button v-if="activePaymentPartnerDrilldownLabel" type="button" class="drilldown-clear-button" @click="clearPaymentPartnerDrilldown(true)">
							{{ $texts.analytics.showAllPaymentPartners() }}
						</button>
						<button v-if="activeTagDrilldownLabel" type="button" class="drilldown-clear-button" @click="clearTagDrilldown(true)">
							{{ $texts.analytics.showAllLabels() }}
						</button>
						<button v-if="activeHashtagDrilldownLabel" type="button" class="drilldown-clear-button" @click="clearHashtagDrilldown(true)">
							{{ $texts.analytics.showAllHashtags() }}
						</button>
						<button v-if="activeProjectDrilldownLabel" type="button" class="drilldown-clear-button" @click="clearProjectDrilldown(true)">
							{{ $texts.analytics.showAllAreas() }}
						</button>
					</div>

					<div v-if="!activeBreakdownSection" class="mini-empty no-print">
						{{ $texts.analytics.noFocusData() }}
					</div>
					<div v-else class="breakdown-table-grid no-print" :class="breakdownGridClass([activeBreakdownSection])">
						<div class="breakdown-section" :class="`breakdown-section-${activeBreakdownSection.key}`">
							<h4>{{ activeBreakdownSection.title }}</h4>
							<table class="breakdown-table">
								<thead>
									<tr>
										<th>{{ $texts.analytics.name() }}</th>
										<th class="share-cell">{{ $texts.analytics.share() }}</th>
										<th class="average-cell">{{ breakdownAverageHeader }}</th>
										<th class="amount-cell">{{ $texts.analytics.amount() }}</th>
									</tr>
								</thead>
								<tbody>
									<tr
										v-for="item in activeBreakdownSection.items"
										:key="`${activeBreakdownSection.key}-${item.id || item.name}`"
										:class="{ 'is-clickable': isBreakdownDrilldownRow(activeBreakdownSection, item) }"
										:tabindex="isBreakdownDrilldownRow(activeBreakdownSection, item) ? 0 : null"
										@click="selectBreakdownDrilldown(activeBreakdownSection, item)"
										@keydown.enter.prevent="selectBreakdownDrilldown(activeBreakdownSection, item)"
										@keydown.space.prevent="selectBreakdownDrilldown(activeBreakdownSection, item)">
										<td>
											<TableTooltip :text="breakdownTooltip(item, activeBreakdownSection)">
												<span class="breakdown-name-line">
													<span v-if="isBreakdownDrilldownRow(activeBreakdownSection, item)" class="breakdown-link breakdown-name-tooltip">
														{{ item.name }}
													</span>
													<span v-else class="breakdown-name-tooltip">{{ item.name }}</span>
													<span
														v-if="item.trend"
														class="breakdown-trend-badge"
														:class="breakdownTrendClass(item.trend)"
														:aria-label="breakdownTrendLabel(item.trend, 'direction')">
														{{ breakdownTrendIcon(item.trend) }}
													</span>
												</span>
											</TableTooltip>
										</td>
										<td class="share-cell">{{ breakdownShareLabel(item, activeBreakdownSection) }}</td>
										<td class="average-cell">{{ breakdownAverageLabel(item) }}</td>
										<td class="amount-cell">{{ formatCents(item.amountCents) }}</td>
									</tr>
								</tbody>
								<tfoot>
									<tr>
										<td>
											<TableTooltip :text="breakdownSectionTooltip(activeBreakdownSection)">
												<span class="breakdown-name-tooltip">{{ $texts.analytics.total() }}</span>
											</TableTooltip>
										</td>
										<td class="share-cell">{{ breakdownSectionShareLabel(activeBreakdownSection) }}</td>
										<td class="average-cell">{{ breakdownSectionAverageLabel(activeBreakdownSection) }}</td>
										<td class="amount-cell">{{ formatCents(breakdownSectionTotalCents(activeBreakdownSection)) }}</td>
									</tr>
								</tfoot>
							</table>
						</div>
					</div>

					<div class="print-only breakdown-print-groups">
						<div v-for="group in printBreakdownGroups" :key="group.key" class="breakdown-print-group">
							<h4>{{ group.title }}</h4>
							<div v-if="group.sections.length === 0" class="mini-empty">{{ $texts.analytics.noData() }}</div>
							<div v-else class="breakdown-table-grid" :class="breakdownGridClass(group.sections)">
								<div v-for="section in group.sections" :key="`${group.key}-${section.key}`" class="breakdown-section" :class="`breakdown-section-${section.key}`">
									<h5>{{ section.shortTitle }}</h5>
									<table class="breakdown-table">
										<thead>
											<tr>
												<th>{{ $texts.analytics.name() }}</th>
												<th class="share-cell">{{ $texts.analytics.share() }}</th>
												<th class="average-cell">{{ breakdownAverageHeader }}</th>
												<th class="amount-cell">{{ $texts.analytics.amount() }}</th>
											</tr>
										</thead>
										<tbody>
											<tr v-for="item in section.items" :key="`${group.key}-${section.key}-${item.id || item.name}`">
												<td>
													<span class="breakdown-name-line">
														<span>{{ item.name }}</span>
														<span v-if="item.trend" class="breakdown-trend-badge" :class="breakdownTrendClass(item.trend)">
															{{ breakdownTrendIcon(item.trend) }}
														</span>
													</span>
												</td>
												<td class="share-cell">{{ breakdownShareLabel(item, section) }}</td>
												<td class="average-cell">{{ breakdownAverageLabel(item) }}</td>
												<td class="amount-cell">{{ formatCents(item.amountCents) }}</td>
											</tr>
										</tbody>
										<tfoot>
											<tr>
												<td>{{ $texts.analytics.total() }}</td>
												<td class="share-cell">{{ breakdownSectionShareLabel(section) }}</td>
												<td class="average-cell">{{ breakdownSectionAverageLabel(section) }}</td>
												<td class="amount-cell">{{ formatCents(breakdownSectionTotalCents(section)) }}</td>
											</tr>
										</tfoot>
									</table>
								</div>
							</div>
						</div>
					</div>
				</section>

				<section class="analytics-card outlier-card">
					<div class="card-header">
						<div>
							<h3>{{ $texts.analytics.highAmounts() }}</h3>
							<p>{{ $texts.analytics.highAmountsHint() }}</p>
						</div>
						<span v-if="outliers.baselineCents" class="baseline-label">
							{{ $texts.analytics.baseline(formatCents(outliers.baselineCents)) }}
						</span>
					</div>
					<div v-if="outlierItems.length === 0" class="mini-empty">{{ $texts.analytics.noExpenses() }}</div>
					<div v-else class="outlier-list">
						<div v-for="item in outlierItems" :key="item.id" class="outlier-row">
							<div>
								<strong>{{ outlierTitle(item) }}</strong>
								<span>{{ formatDate(item.date) }} · {{ outlierContext(item) }}</span>
							</div>
							<strong class="negative">{{ formatCents(item.amountCents) }}</strong>
						</div>
					</div>
				</section>

				<section v-if="sharedProjects.length" class="analytics-card shared-projects-card">
					<div class="card-header">
						<div>
							<h3>{{ $texts.analytics.sharedAreas() }}</h3>
							<p>{{ $texts.analytics.sharedAreasHint() }}</p>
						</div>
					</div>
					<div class="shared-projects-grid">
						<article v-for="project in sharedProjects" :key="project.id" class="shared-project-card">
							<div class="shared-project-header">
								<div>
									<h4>{{ project.name }}</h4>
									<span>{{ bookingCountLabel(project.entryCount) }}</span>
								</div>
								<div class="shared-project-total">
									<strong>{{ formatCents(project.totalPaidCents) }}</strong>
									<span>{{ $texts.analytics.totalPaid() }}</span>
								</div>
							</div>
							<div class="shared-project-metrics">
								<div>
									<span>{{ $texts.analytics.yourShare() }}</span>
									<strong :class="sharedProjectBalanceClass(project)">{{ formatCents(project.personalShareCents) }}</strong>
									<small :class="['shared-project-balance-label', sharedProjectBalanceClass(project)]">
										{{ sharedProjectBalanceLabel(project) }}
									</small>
								</div>
								<div>
									<span>{{ $texts.analytics.open() }}</span>
									<strong>{{ formatCents(project.openCents) }}</strong>
								</div>
								<div>
									<span>{{ $texts.analytics.settled() }}</span>
									<strong>{{ formatCents(project.settledCents) }}</strong>
								</div>
							</div>
							<div class="shared-project-members">
								<div v-for="member in project.members" :key="member.userId" class="shared-project-member">
									<span>{{ member.displayName }}</span>
									<strong>{{ formatCents(member.paidCents) }}</strong>
								</div>
							</div>
						</article>
					</div>
				</section>

				<section class="analytics-card upcoming-card">
					<div class="card-header">
						<div>
							<h3>{{ $texts.analytics.activeReminders() }}</h3>
							<p>{{ $texts.analytics.activeRemindersHint() }}</p>
						</div>
						<NcButton
							v-if="upcomingReminders.length"
							variant="secondary"
							class="no-print analytics-overview-action"
							@click="openReminderOverview">
							{{ $texts.analytics.openOverview() }}
						</NcButton>
					</div>
					<div v-if="upcomingReminders.length === 0" class="mini-empty">{{ $texts.analytics.noActiveReminders() }}</div>
					<table v-else class="upcoming-table">
						<thead>
							<tr>
								<th>{{ $texts.analytics.reminder() }}</th>
								<th>{{ $texts.analytics.payment() }}</th>
								<th>{{ $texts.analytics.amount() }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="item in upcomingReminders" :key="`reminder-${item.id}`">
								<td>{{ formatDate(item.date) }}</td>
								<td>
									<strong>{{ upcomingTitle(item) }}</strong>
									<span>{{ upcomingContext(item) }}</span>
									<small v-if="item.reminderText">{{ item.reminderText }}</small>
								</td>
								<td class="amount-cell" :class="item.type === 'income' ? 'positive' : 'negative'">
									{{ item.type === 'income' ? formatCents(item.amountCents) : formatSignedCents(-item.amountCents) }}
								</td>
							</tr>
						</tbody>
					</table>
				</section>

				<section class="analytics-card upcoming-card">
					<div class="card-header">
						<div>
							<h3>{{ $texts.analytics.plannedPayments() }}</h3>
							<p>{{ $texts.analytics.plannedPaymentsHint() }}</p>
						</div>
						<NcButton
							v-if="upcomingPlanned.length"
							variant="secondary"
							class="no-print analytics-overview-action"
							@click="openPlannedOverview">
							{{ $texts.analytics.openOverview() }}
						</NcButton>
					</div>
					<div v-if="upcomingPlanned.length === 0" class="mini-empty">{{ $texts.analytics.noPlannedPayments() }}</div>
					<table v-else class="upcoming-table">
						<thead>
							<tr>
								<th>{{ $texts.entry.tableDate() }}</th>
								<th>{{ $texts.analytics.payment() }}</th>
								<th>{{ $texts.analytics.amount() }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="item in upcomingPlanned" :key="`planned-${item.id}`">
								<td>
									{{ formatDate(item.date) }}
									<small v-if="item.isRecurring">{{ $texts.analytics.recurrence() }}</small>
								</td>
								<td>
									<strong>{{ upcomingTitle(item) }}</strong>
									<span>{{ upcomingContext(item) }}</span>
								</td>
								<td class="amount-cell" :class="item.type === 'income' ? 'positive' : 'negative'">
									{{ item.type === 'income' ? formatCents(item.amountCents) : formatSignedCents(-item.amountCents) }}
								</td>
							</tr>
						</tbody>
					</table>
				</section>

				<section v-if="receiptCheckItems.length" class="analytics-card receipt-checks-card">
					<div class="card-header">
						<div>
							<h3>{{ $texts.analytics.checkReceipts() }}</h3>
							<p>{{ $texts.analytics.checkReceiptsHint() }}</p>
						</div>
					</div>
					<div class="receipt-check-grid">
						<button
							v-for="item in receiptCheckItems"
							:key="item.key"
							type="button"
							class="receipt-check-card"
							@click="openReceiptCheck(item)">
							<span>{{ item.title }}</span>
							<strong>{{ bookingCountLabel(item.count) }}</strong>
							<small>{{ $texts.analytics.withoutReceipt(formatCents(item.amountCents)) }}</small>
							<em class="analytics-overview-action">{{ $texts.analytics.openOverview() }}</em>
						</button>
					</div>
				</section>
			</div>
		</div>
	</div>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import PrinterIcon from 'vue-material-design-icons/Printer.vue'
import TableTooltip from '../components/TableTooltip.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import AnalyticsDevelopmentChart from '../components/analytics/AnalyticsDevelopmentChart.vue'
import AnalyticsForecastCard from '../components/analytics/AnalyticsForecastCard.vue'
import AnalyticsInsightsSection from '../components/analytics/AnalyticsInsightsSection.vue'
import AnalyticsPeriodSwitch from '../components/analytics/AnalyticsPeriodSwitch.vue'
import AnalyticsSummaryGrid from '../components/analytics/AnalyticsSummaryGrid.vue'
import { showRequestError } from '../services/notifications'

const emptyAnalytics = () => ({
	period: null,
	periods: [],
	summary: {},
	comparison: null,
	projection: null,
	availableForecast: null,
	series: [],
	breakdowns: {
		categories: { expense: [], income: [] },
		paymentPartners: { expense: [], income: [] },
		tags: { expense: [], income: [] },
		hashtags: { expense: [], income: [] },
		projects: { expense: [], income: [] }
	},
	categoryDrilldowns: {
		expense: {},
		income: {}
	},
	paymentPartnerDrilldowns: {
		expense: {},
		income: {}
	},
	tagDrilldowns: {
		expense: {},
		income: {}
	},
	hashtagDrilldowns: {
		expense: {},
		income: {}
	},
	projectDrilldowns: {
		expense: {},
		income: {}
	},
	outliers: {
		baselineCents: 0,
		items: []
	},
	sharedProjects: [],
	budgetHistory: {
		summary: {
			total: 0,
			ok: 0,
			warning: 0,
			exceeded: 0,
			bufferCents: 0
		},
		items: []
	},
	upcoming: {
		reminders: [],
		planned: []
	},
	receiptChecks: []
})

export default {
	name: 'AnalyticsView',
	components: {
		AnalyticsDevelopmentChart,
		AnalyticsForecastCard,
		AnalyticsInsightsSection,
		AnalyticsPeriodSwitch,
		AnalyticsSummaryGrid,
		AppPageHeader,
		NcButton,
		PrinterIcon,
		TableTooltip
	},
	data() {
		return {
			loading: false,
			selectedPeriod: 'current-year',
			analytics: emptyAnalytics(),
			breakdownType: 'expense',
			selectedBreakdownDimension: 'categories',
			activeCategoryDrilldown: null,
			activePaymentPartnerDrilldown: null,
			activeTagDrilldown: null,
			activeHashtagDrilldown: null,
			activeProjectDrilldown: null
		}
	},
	computed: {
		periodOptions() {
			return this.analytics.periods?.length ? this.analytics.periods : [
				{ key: 'current-year', label: this.$texts.analytics.currentYear() },
				{ key: 'current-month', label: this.$texts.analytics.currentMonth() },
				{ key: 'last-12-months', label: this.$texts.analytics.last12Months() }
			]
		},
		periodLabel() {
			return this.analytics.period?.label || this.$texts.analytics.selectedPeriod()
		},
		hasEntries() {
			return Number(this.analytics.summary?.bookingCount || 0) > 0
				|| this.sharedProjects.length > 0
				|| this.budgetHistoryItems.length > 0
				|| this.upcomingReminders.length > 0
				|| this.upcomingPlanned.length > 0
				|| this.receiptCheckItems.length > 0
		},
		summaryCards() {
			const summary = this.analytics.summary || {}
			return [
				{
					key: 'income',
					label: this.$texts.analytics.income(),
					value: this.formatCents(summary.incomeCents || 0),
					detail: this.$texts.analytics.bookings(summary.incomeCount || 0),
					className: 'positive'
				},
				{
					key: 'expense',
					label: this.$texts.analytics.expenses(),
					value: this.formatCents(summary.expenseCents || 0),
					detail: this.$texts.analytics.bookings(summary.expenseCount || 0),
					className: 'negative'
				},
				{
					key: 'balance',
					label: this.$texts.analytics.balance(),
					value: this.formatSignedCents(summary.balanceCents || 0),
					detail: this.$texts.analytics.totalBookings(summary.bookingCount || 0),
					className: this.amountClass(summary.balanceCents || 0)
				},
				{
					key: 'average',
					label: this.$texts.analytics.averageExpenses(),
					value: this.formatCents(this.summaryAverageCents('expense', this.summaryAverageUnit)),
					detail: this.summaryAverageDetail,
					tooltip: this.summaryAverageTooltip('expense'),
					className: ''
				},
				{
					key: 'averageIncome',
					label: this.$texts.analytics.averageIncome(),
					value: this.formatCents(this.summaryAverageCents('income', this.summaryAverageUnit)),
					detail: this.summaryAverageDetail,
					tooltip: this.summaryAverageTooltip('income'),
					className: ''
				}
			]
		},
		summaryAverageUnit() {
			if (this.analytics.period?.kind === 'current-month') {
				return 'day'
			}
			return Number(this.analytics.summary?.averageMonthCount || 0) > 0 ? 'month' : 'day'
		},
		summaryAverageDetail() {
			return this.summaryAverageUnit === 'day' ? this.$texts.analytics.perDay() : this.$texts.analytics.perMonth()
		},
		developmentLabel() {
			return this.analytics.period?.granularity === 'day'
				? this.$texts.analytics.dailyDevelopmentCurrentMonth()
				: this.$texts.analytics.monthlyDevelopmentSelectedPeriod()
		},
		series() {
			return Array.isArray(this.analytics.series) ? this.analytics.series : []
		},
		seriesMaxAmount() {
			return Math.max(1, ...this.series.map(item => Math.max(Math.abs(item.incomeCents || 0), Math.abs(item.expenseCents || 0))))
		},
		cumulativeValues() {
			return this.series.map(item => Number(item.cumulativeBalanceCents || 0))
		},
		chartRange() {
			const values = this.cumulativeValues.concat([0])
			const min = Math.min(...values)
			const max = Math.max(...values)
			const span = Math.max(1, max - min)
			return { min, max, span }
		},
		zeroLineY() {
			return this.chartY(0)
		},
		chartPoints() {
			if (this.cumulativeValues.length === 0) {
				return []
			}
			const count = this.cumulativeValues.length
			return this.cumulativeValues.map((value, index) => ({
				x: count === 1 ? 500 : Math.round(index * (1000 / (count - 1))),
				y: this.chartY(value)
			}))
		},
		cumulativeChartPoints() {
			return this.chartPoints.map(point => `${point.x},${point.y}`).join(' ')
		},
		lastChartPoint() {
			return this.chartPoints.length ? this.chartPoints[this.chartPoints.length - 1] : null
		},
		comparison() {
			return this.analytics.comparison
		},
		comparisonCards() {
			if (!this.comparison) {
				return []
			}
			return [
				{
					key: 'income',
					label: this.$texts.analytics.income(),
					value: this.formatSignedCents(this.comparison.deltaIncomeCents || 0),
					detail: this.$texts.analytics.comparedToPreviousMonth(),
					className: this.amountClass(this.comparison.deltaIncomeCents || 0)
				},
				{
					key: 'expense',
					label: this.$texts.analytics.expenses(),
					value: this.formatSignedCents(this.comparison.deltaExpenseCents || 0),
					detail: this.expenseComparisonDetail(this.comparison.deltaExpenseCents || 0),
					className: this.comparison.deltaExpenseCents > 0 ? 'negative' : 'positive'
				},
				{
					key: 'balance',
					label: this.$texts.analytics.balance(),
					value: this.formatSignedCents(this.comparison.deltaBalanceCents || 0),
					detail: this.$texts.analytics.balanceChange(),
					className: this.amountClass(this.comparison.deltaBalanceCents || 0)
				}
			]
		},
		projection() {
			return this.analytics.projection
		},
		availableForecast() {
			return this.analytics.availableForecast
		},
			availableForecastTitle() {
				return this.projection?.label || this.availableForecast?.label || this.$texts.analytics.forecast()
			},
		availableForecastMainLabel() {
				const cents = Number(this.availableForecast?.forecastCents || 0)
				if (cents < 0) {
					return this.$texts.analytics.missingAmount(this.formatCents(Math.abs(cents)))
				}
			return this.formatCents(cents)
		},
			availableForecastRemainingLabel() {
				const days = Number(this.availableForecast?.remainingDays || 0)
				return this.$texts.analytics.daysRemaining(days)
			},
		availableForecastRangeLabel() {
			const forecast = this.availableForecast
			if (!forecast) {
				return ''
			}
				return this.$texts.analytics.rangeFromTo(
					this.formatSignedCents(forecast.rangeLowCents),
					this.formatSignedCents(forecast.rangeHighCents)
				)
		},
		availableForecastTooltip() {
			const forecast = this.availableForecast
			if (!forecast) {
				return ''
			}
				return [
					this.availableForecastTitle,
					this.$texts.analytics.availableForecast(),
					`${this.$texts.analytics.expectedIncome()}: ${this.formatCents(forecast.expectedIncomeCents)}`,
					`${this.$texts.analytics.expectedExpenses()}: ${this.formatCents(forecast.expectedExpenseCents)}`,
					this.$texts.analytics.currentBalance(this.formatSignedCents(forecast.currentBalanceCents)),
					this.$texts.analytics.remainingChangeFromToday(this.formatSignedCents(forecast.remainingChangeCents)),
					this.$texts.analytics.range(this.availableForecastRangeLabel),
				forecast.confidenceLabel
			].join('\n')
		},
			breakdownTypeLabel() {
				return this.formatBreakdownTypeLabel(this.breakdownType)
		},
		activeCategoryDrilldownData() {
			if (!this.activeCategoryDrilldown) {
				return null
			}
			return this.analytics.categoryDrilldowns?.[this.breakdownType]?.[this.activeCategoryDrilldown] || null
		},
		activeCategoryDrilldownLabel() {
			return this.activeCategoryDrilldownData?.label || ''
		},
		activePaymentPartnerDrilldownData() {
			if (!this.activePaymentPartnerDrilldown) {
				return null
			}
			return this.analytics.paymentPartnerDrilldowns?.[this.breakdownType]?.[this.activePaymentPartnerDrilldown] || null
		},
		activePaymentPartnerDrilldownLabel() {
			return this.activePaymentPartnerDrilldownData?.label || ''
		},
		activeTagDrilldownData() {
			if (!this.activeTagDrilldown) {
				return null
			}
			return this.analytics.tagDrilldowns?.[this.breakdownType]?.[this.activeTagDrilldown] || null
		},
		activeTagDrilldownLabel() {
			return this.activeTagDrilldownData?.label || ''
		},
		activeHashtagDrilldownData() {
			if (!this.activeHashtagDrilldown) {
				return null
			}
			return this.analytics.hashtagDrilldowns?.[this.breakdownType]?.[this.activeHashtagDrilldown] || null
		},
		activeHashtagDrilldownLabel() {
			return this.activeHashtagDrilldownData?.label || ''
		},
		activeProjectDrilldownData() {
			if (!this.activeProjectDrilldown) {
				return null
			}
			return this.analytics.projectDrilldowns?.[this.breakdownType]?.[this.activeProjectDrilldown] || null
		},
		activeProjectDrilldownLabel() {
			return this.activeProjectDrilldownData?.label || ''
		},
		breakdownSections() {
			if (this.activeCategoryDrilldownData) {
				return this.getCategoryDrilldownSections(this.breakdownType, this.activeCategoryDrilldownData)
			}
			if (this.activePaymentPartnerDrilldownData) {
				return this.getPaymentPartnerDrilldownSections(this.breakdownType, this.activePaymentPartnerDrilldownData)
			}
			if (this.activeTagDrilldownData) {
				return this.getTagDrilldownSections(this.breakdownType, this.activeTagDrilldownData)
			}
			if (this.activeHashtagDrilldownData) {
				return this.getHashtagDrilldownSections(this.breakdownType, this.activeHashtagDrilldownData)
			}
			if (this.activeProjectDrilldownData) {
				return this.getProjectDrilldownSections(this.breakdownType, this.activeProjectDrilldownData)
			}
			return this.getBreakdownSections(this.breakdownType)
		},
		activeBreakdownSection() {
			if (this.breakdownSections.length === 0) {
				return null
			}
			return this.breakdownSections.find(section => section.key === this.selectedBreakdownDimension) || this.breakdownSections[0]
		},
			breakdownAverageHeader() {
				return this.summaryAverageUnit === 'day'
					? this.$texts.analytics.averagePerDayShort()
					: this.$texts.analytics.averagePerMonthShort()
			},
		printBreakdownGroups() {
			return [
					{
						key: 'expense',
						title: this.$texts.analytics.expenses(),
						sections: this.getBreakdownSections('expense')
					},
					{
						key: 'income',
						title: this.$texts.analytics.income(),
					sections: this.getBreakdownSections('income')
				}
			]
		},
		outliers() {
			return this.analytics.outliers || { baselineCents: 0, items: [] }
		},
		outlierItems() {
			return Array.isArray(this.outliers.items) ? this.outliers.items : []
		},
		sharedProjects() {
			return Array.isArray(this.analytics.sharedProjects) ? this.analytics.sharedProjects : []
		},
		budgetHistory() {
			return this.analytics.budgetHistory || emptyAnalytics().budgetHistory
		},
		budgetHistoryItems() {
			return Array.isArray(this.budgetHistory.items) ? this.budgetHistory.items.slice(0, 6) : []
		},
		budgetHistorySummary() {
			return this.budgetHistory.summary || emptyAnalytics().budgetHistory.summary
		},
		budgetHistoryCriticalCount() {
			return Number(this.budgetHistorySummary.warning || 0) + Number(this.budgetHistorySummary.exceeded || 0)
		},
		upcomingReminders() {
			return Array.isArray(this.analytics.upcoming?.reminders) ? this.analytics.upcoming.reminders : []
		},
		upcomingPlanned() {
			return Array.isArray(this.analytics.upcoming?.planned) ? this.analytics.upcoming.planned : []
		},
		receiptCheckItems() {
			return Array.isArray(this.analytics.receiptChecks) ? this.analytics.receiptChecks : []
		},
		insightItems() {
			return [
				...this.buildBudgetInsightItems(),
				...this.buildReceiptInsightItems(),
				...this.buildTrendInsightItems(),
				...this.buildOutlierInsightItems()
			].slice(0, 6)
		}
	},
	watch: {
		selectedPeriod() {
			this.clearAllDrilldowns()
			this.fetchAnalytics()
		},
		breakdownType() {
			this.clearAllDrilldowns()
			this.normalizeBreakdownDimension()
		},
		breakdownSections() {
			this.normalizeBreakdownDimension()
		}
	},
	mounted() {
		this.fetchAnalytics()
	},
		methods: {
			formatBreakdownTypeLabel(type) {
				return type === 'expense' ? this.$texts.analytics.expenses() : this.$texts.analytics.income()
			},
			getBreakdownSections(type) {
				const breakdowns = this.analytics.breakdowns || emptyAnalytics().breakdowns
				const typeLabel = this.formatBreakdownTypeLabel(type)
				const sections = [
					{
						key: 'categories',
						title: this.$texts.analytics.breakdownBy(typeLabel, this.$texts.analytics.categories()),
						shortTitle: this.$texts.analytics.categories(),
						type,
						totalCents: this.breakdownTotalCents(type),
						items: breakdowns.categories?.[type] || []
					},
					{
						key: 'paymentPartners',
						title: this.$texts.analytics.breakdownBy(typeLabel, this.$texts.analytics.paymentPartners()),
						shortTitle: this.$texts.analytics.paymentPartners(),
						type,
						totalCents: this.breakdownTotalCents(type),
						items: breakdowns.paymentPartners?.[type] || []
					},
					{
						key: 'tags',
						title: this.$texts.analytics.breakdownBy(typeLabel, this.$texts.analytics.labels()),
						shortTitle: this.$texts.analytics.labels(),
						type,
						totalCents: this.breakdownTotalCents(type),
						items: breakdowns.tags?.[type] || []
					},
					{
						key: 'hashtags',
						title: this.$texts.analytics.breakdownBy(typeLabel, this.$texts.analytics.hashtags()),
						shortTitle: this.$texts.analytics.hashtags(),
						type,
						totalCents: this.breakdownTotalCents(type),
						items: breakdowns.hashtags?.[type] || []
					},
					{
						key: 'projects',
						title: this.$texts.analytics.breakdownBy(typeLabel, this.$texts.analytics.areas()),
						shortTitle: this.$texts.analytics.areas(),
						type,
						totalCents: this.breakdownTotalCents(type),
					hideIfSingle: true,
					items: breakdowns.projects?.[type] || []
				}
			]

			return sections
				.map(section => ({
					...section,
					items: Array.isArray(section.items) ? section.items : []
				}))
				.filter(section => section.items.length > 0)
				.filter(section => !section.hideIfSingle || section.items.length > 1)
			},
			getCategoryDrilldownSections(type, drilldown) {
				const typeLabel = this.formatBreakdownTypeLabel(type)
				const label = drilldown?.label || this.$texts.analytics.category()
				const totalCents = this.breakdownItemsTotal(drilldown?.paymentPartners || [])
				const sections = [
					{
						key: 'paymentPartners',
						title: this.$texts.analytics.breakdownInCategory(typeLabel, label, this.$texts.analytics.paymentPartners()),
						shortTitle: this.$texts.analytics.paymentPartners(),
						type,
						totalCents,
						items: drilldown?.paymentPartners || []
					},
					{
						key: 'tags',
						title: this.$texts.analytics.breakdownInCategory(typeLabel, label, this.$texts.analytics.labels()),
						shortTitle: this.$texts.analytics.labels(),
						type,
						totalCents,
						items: drilldown?.tags || []
					},
					{
						key: 'hashtags',
						title: this.$texts.analytics.breakdownInCategory(typeLabel, label, this.$texts.analytics.hashtags()),
						shortTitle: this.$texts.analytics.hashtags(),
						type,
						totalCents,
						items: drilldown?.hashtags || []
					},
					{
						key: 'projects',
						title: this.$texts.analytics.breakdownInCategory(typeLabel, label, this.$texts.analytics.areas()),
						shortTitle: this.$texts.analytics.areas(),
					type,
					totalCents,
					hideIfSingle: true,
					items: drilldown?.projects || []
				}
			]

			return sections
				.map(section => ({
					...section,
					items: Array.isArray(section.items) ? section.items : []
				}))
				.filter(section => section.items.length > 0)
				.filter(section => !section.hideIfSingle || section.items.length > 1)
			},
			getPaymentPartnerDrilldownSections(type, drilldown) {
				const typeLabel = this.formatBreakdownTypeLabel(type)
				const label = drilldown?.label || this.$texts.analytics.paymentPartner()
				const totalCents = this.breakdownItemsTotal(drilldown?.categories || [])
				const sections = [
					{
						key: 'categories',
						title: this.$texts.analytics.breakdownWithPaymentPartner(typeLabel, label, this.$texts.analytics.categories()),
						shortTitle: this.$texts.analytics.categories(),
						type,
						totalCents,
						items: drilldown?.categories || []
					},
					{
						key: 'tags',
						title: this.$texts.analytics.breakdownWithPaymentPartner(typeLabel, label, this.$texts.analytics.labels()),
						shortTitle: this.$texts.analytics.labels(),
						type,
						totalCents,
						items: drilldown?.tags || []
					},
					{
						key: 'hashtags',
						title: this.$texts.analytics.breakdownWithPaymentPartner(typeLabel, label, this.$texts.analytics.hashtags()),
						shortTitle: this.$texts.analytics.hashtags(),
						type,
						totalCents,
						items: drilldown?.hashtags || []
					},
					{
						key: 'projects',
						title: this.$texts.analytics.breakdownWithPaymentPartner(typeLabel, label, this.$texts.analytics.areas()),
						shortTitle: this.$texts.analytics.areas(),
					type,
					totalCents,
					hideIfSingle: true,
					items: drilldown?.projects || []
				}
			]

			return sections
				.map(section => ({
					...section,
					items: Array.isArray(section.items) ? section.items : []
				}))
				.filter(section => section.items.length > 0)
				.filter(section => !section.hideIfSingle || section.items.length > 1)
			},
			getTagDrilldownSections(type, drilldown) {
				const typeLabel = this.formatBreakdownTypeLabel(type)
				const label = drilldown?.label || this.$texts.analytics.labels()
				const totalCents = this.breakdownItemsTotal(drilldown?.categories || [])
				const sections = [
					{
						key: 'categories',
						title: this.$texts.analytics.breakdownWithLabel(typeLabel, label, this.$texts.analytics.categories()),
						shortTitle: this.$texts.analytics.categories(),
						type,
						totalCents,
						items: drilldown?.categories || []
					},
					{
						key: 'paymentPartners',
						title: this.$texts.analytics.breakdownWithLabel(typeLabel, label, this.$texts.analytics.paymentPartners()),
						shortTitle: this.$texts.analytics.paymentPartners(),
						type,
						totalCents,
						items: drilldown?.paymentPartners || []
					},
					{
						key: 'projects',
						title: this.$texts.analytics.breakdownWithLabel(typeLabel, label, this.$texts.analytics.areas()),
						shortTitle: this.$texts.analytics.areas(),
					type,
					totalCents,
					hideIfSingle: true,
					items: drilldown?.projects || []
				},
				{
					key: 'hashtags',
					title: this.$texts.analytics.breakdownWithLabel(typeLabel, label, this.$texts.analytics.hashtags()),
					shortTitle: this.$texts.analytics.hashtags(),
					type,
					totalCents,
					items: drilldown?.hashtags || []
				}
			]

			return sections
				.map(section => ({
					...section,
					items: Array.isArray(section.items) ? section.items : []
				}))
				.filter(section => section.items.length > 0)
				.filter(section => !section.hideIfSingle || section.items.length > 1)
			},
			getHashtagDrilldownSections(type, drilldown) {
				const typeLabel = this.formatBreakdownTypeLabel(type)
				const label = drilldown?.label || this.$texts.analytics.hashtag()
				const totalCents = this.breakdownItemsTotal(drilldown?.categories || [])
				const sections = [
					{
						key: 'categories',
						title: this.$texts.analytics.breakdownWithHashtag(typeLabel, label, this.$texts.analytics.categories()),
						shortTitle: this.$texts.analytics.categories(),
						type,
						totalCents,
						items: drilldown?.categories || []
					},
					{
						key: 'paymentPartners',
						title: this.$texts.analytics.breakdownWithHashtag(typeLabel, label, this.$texts.analytics.paymentPartners()),
						shortTitle: this.$texts.analytics.paymentPartners(),
						type,
						totalCents,
						items: drilldown?.paymentPartners || []
					},
					{
						key: 'tags',
						title: this.$texts.analytics.breakdownWithHashtag(typeLabel, label, this.$texts.analytics.labels()),
						shortTitle: this.$texts.analytics.labels(),
						type,
						totalCents,
						items: drilldown?.tags || []
					},
					{
						key: 'projects',
						title: this.$texts.analytics.breakdownWithHashtag(typeLabel, label, this.$texts.analytics.areas()),
						shortTitle: this.$texts.analytics.areas(),
						type,
						totalCents,
						hideIfSingle: true,
						items: drilldown?.projects || []
					}
				]

				return sections
					.map(section => ({
						...section,
						items: Array.isArray(section.items) ? section.items : []
					}))
					.filter(section => section.items.length > 0)
					.filter(section => !section.hideIfSingle || section.items.length > 1)
			},
			getProjectDrilldownSections(type, drilldown) {
				const typeLabel = this.formatBreakdownTypeLabel(type)
				const label = drilldown?.label || this.$texts.analytics.area()
				const totalCents = this.breakdownItemsTotal(drilldown?.categories || [])
				const sections = [
					{
						key: 'categories',
						title: this.$texts.analytics.breakdownInArea(typeLabel, label, this.$texts.analytics.categories()),
						shortTitle: this.$texts.analytics.categories(),
						type,
						totalCents,
						items: drilldown?.categories || []
					},
					{
						key: 'paymentPartners',
						title: this.$texts.analytics.breakdownInArea(typeLabel, label, this.$texts.analytics.paymentPartners()),
						shortTitle: this.$texts.analytics.paymentPartners(),
						type,
						totalCents,
						items: drilldown?.paymentPartners || []
					},
					{
						key: 'tags',
						title: this.$texts.analytics.breakdownInArea(typeLabel, label, this.$texts.analytics.labels()),
						shortTitle: this.$texts.analytics.labels(),
					type,
					totalCents,
					items: drilldown?.tags || []
				},
				{
					key: 'hashtags',
					title: this.$texts.analytics.breakdownInArea(typeLabel, label, this.$texts.analytics.hashtags()),
					shortTitle: this.$texts.analytics.hashtags(),
					type,
					totalCents,
					items: drilldown?.hashtags || []
				}
			]

			return sections
				.map(section => ({
					...section,
					items: Array.isArray(section.items) ? section.items : []
				}))
				.filter(section => section.items.length > 0)
		},
		breakdownItemsTotal(items) {
			return (Array.isArray(items) ? items : []).reduce((sum, item) => sum + Number(item.amountCents || 0), 0)
		},
		breakdownGridClass(sections) {
			const keys = new Set(sections.map(section => section.key))
			return {
				'has-tags-and-projects': this.hasStackedBreakdownSections(sections),
				'has-three-breakdown-columns': sections.length >= 3
			}
		},
		hasStackedBreakdownSections(sections) {
			const keys = new Set(sections.map(section => section.key))
			return ['tags', 'hashtags', 'projects'].filter(key => keys.has(key)).length >= 2
		},
		mainBreakdownSections(sections) {
			if (!this.hasStackedBreakdownSections(sections)) {
				return sections
			}

			return sections.filter(section => !['tags', 'hashtags', 'projects'].includes(section.key))
		},
		stackedBreakdownSections(sections) {
			if (!this.hasStackedBreakdownSections(sections)) {
				return []
			}

			return sections.filter(section => ['tags', 'hashtags', 'projects'].includes(section.key))
		},
		buildBudgetInsightItems() {
			return this.budgetHistoryItems
				.filter(item => ['warning', 'exceeded'].includes(item.status))
				.sort((a, b) => Number(b.progressPercent || 0) - Number(a.progressPercent || 0))
				.slice(0, 2)
					.map(item => ({
						key: `budget-${item.id}`,
						kicker: this.$texts.analytics.budgetCritical(),
						title: item.name || this.$texts.analytics.budgetGoal(),
						description: this.$texts.analytics.percentUsed(Math.round(Number(item.progressPercent || 0))),
						meta: this.$texts.analytics.bufferAmount(this.formatSignedCents(item.bufferCents || 0)),
					tone: item.status === 'exceeded' ? 'danger' : 'warning',
					action: { type: 'budget' }
				}))
		},
		buildReceiptInsightItems() {
			return this.receiptCheckItems
				.filter(item => Number(item.count || 0) > 0)
				.slice(0, 2)
					.map(item => ({
						key: `receipt-${item.key}`,
						kicker: this.$texts.analytics.receiptMissing(),
						title: item.title,
						description: this.bookingCountLabel(item.count),
						meta: this.$texts.analytics.withoutReceipt(this.formatCents(item.amountCents)),
					tone: item.filter === 'review' ? 'danger' : 'warning',
					action: { type: 'receipt', item }
				}))
		},
		buildTrendInsightItems() {
			const sections = this.getBreakdownSections('expense')
				.filter(section => ['categories', 'paymentPartners', 'tags', 'hashtags', 'projects'].includes(section.key))
			const candidates = []

			sections.forEach(section => {
				(section.items || []).forEach(item => {
					if ((item.key || '') === 'rest' || !item.trend) {
						return
					}
					if (item.trend.level !== 'strong' || item.trend.tone !== 'negative') {
						return
					}
					candidates.push({ section, item })
				})
			})

			const seen = new Set()
			return candidates
				.sort((a, b) => Math.abs(Number(b.item.trend?.deltaCents || 0)) - Math.abs(Number(a.item.trend?.deltaCents || 0)))
				.filter(({ item }) => {
					const key = String(item.name || '').trim().toLowerCase()
					if (!key || seen.has(key)) {
						return false
					}
					seen.add(key)
					return true
				})
				.slice(0, 2)
					.map(({ section, item }) => ({
						key: `trend-${section.key}-${item.key || item.id || item.name}`,
						kicker: this.$texts.analytics.stronglyIncreased(),
					title: item.name || section.shortTitle,
					description: `${section.shortTitle}: ${this.formatCents(item.amountCents || 0)}`,
					meta: this.breakdownTrendLabel(item.trend, 'direction'),
					tone: 'danger',
					action: { type: 'breakdown', sectionKey: section.key }
				}))
		},
		buildOutlierInsightItems() {
			const item = this.outlierItems[0]
			if (!item) {
				return []
			}
				const baselineCents = Number(this.outliers.baselineCents || 0)
				const baselineLabel = baselineCents > 0
					? this.$texts.analytics.baseline(this.formatCents(baselineCents))
				: this.outlierContext(item)

				return [{
					key: `outlier-${item.id}`,
					kicker: this.$texts.analytics.highAmount(),
				title: this.outlierTitle(item),
				description: `${this.formatDate(item.date)} · ${this.formatCents(item.amountCents)}`,
				meta: baselineLabel,
				tone: 'neutral',
				action: { type: 'outliers' }
			}]
		},
		openInsight(item) {
			const action = item?.action
			if (!action) {
				return
			}
			if (action.type === 'receipt') {
				this.openReceiptCheck(action.item)
				return
			}
			if (action.type === 'budget') {
				this.scrollToAnalyticsSection('.budget-history-card')
				return
			}
			if (action.type === 'breakdown') {
				this.clearAllDrilldowns()
				this.breakdownType = 'expense'
				this.selectedBreakdownDimension = action.sectionKey || 'categories'
				this.$nextTick(() => this.scrollToAnalyticsSection('.breakdown-card'))
				return
			}
			if (action.type === 'outliers') {
				this.scrollToAnalyticsSection('.outlier-card')
			}
		},
		scrollToAnalyticsSection(selector) {
			const element = this.$el?.querySelector(selector)
			if (!element) {
				return
			}
			element.scrollIntoView({ behavior: 'smooth', block: 'start' })
		},
		async fetchAnalytics() {
			this.loading = true
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/analytics/summary'), {
					params: { period: this.selectedPeriod }
				})
				this.analytics = response.data || emptyAnalytics()
				if (this.analytics.period?.key && this.analytics.period.key !== this.selectedPeriod) {
					this.selectedPeriod = this.analytics.period.key
				}
				if (this.activeCategoryDrilldown && !this.activeCategoryDrilldownData) {
					this.clearCategoryDrilldown()
				}
				if (this.activePaymentPartnerDrilldown && !this.activePaymentPartnerDrilldownData) {
					this.clearPaymentPartnerDrilldown()
				}
				if (this.activeTagDrilldown && !this.activeTagDrilldownData) {
					this.clearTagDrilldown()
				}
				if (this.activeHashtagDrilldown && !this.activeHashtagDrilldownData) {
					this.clearHashtagDrilldown()
				}
				if (this.activeProjectDrilldown && !this.activeProjectDrilldownData) {
					this.clearProjectDrilldown()
				}
				this.normalizeBreakdownDimension()
			} catch (error) {
					showRequestError(error, this.$texts.analytics.loadError(), 'Failed to fetch analytics')
			} finally {
				this.loading = false
			}
		},
		selectPeriod(period) {
			this.selectedPeriod = period
		},
		breakdownItemKey(item) {
			if (item?.key !== undefined && item?.key !== null) {
				return String(item.key)
			}
			return item?.id === null || item?.id === undefined ? 'none' : String(item.id)
		},
		isCategoryDrilldownRow(section, item) {
			const key = this.breakdownItemKey(item)
			return section.key === 'categories'
				&& !!this.analytics.categoryDrilldowns?.[this.breakdownType]?.[key]
		},
		isPaymentPartnerDrilldownRow(section, item) {
			const key = this.breakdownItemKey(item)
			return section.key === 'paymentPartners'
				&& !!this.analytics.paymentPartnerDrilldowns?.[this.breakdownType]?.[key]
		},
		isTagDrilldownRow(section, item) {
			return section.key === 'tags'
				&& !!item.id
				&& !!this.analytics.tagDrilldowns?.[this.breakdownType]?.[item.id]
		},
		isHashtagDrilldownRow(section, item) {
			const key = this.breakdownItemKey(item)
			return section.key === 'hashtags'
				&& !!this.analytics.hashtagDrilldowns?.[this.breakdownType]?.[key]
		},
		isProjectDrilldownRow(section, item) {
			const key = this.breakdownItemKey(item)
			return section.key === 'projects'
				&& !!this.analytics.projectDrilldowns?.[this.breakdownType]?.[key]
		},
		isBreakdownDrilldownRow(section, item) {
			return this.isCategoryDrilldownRow(section, item)
				|| this.isPaymentPartnerDrilldownRow(section, item)
				|| this.isTagDrilldownRow(section, item)
				|| this.isHashtagDrilldownRow(section, item)
				|| this.isProjectDrilldownRow(section, item)
		},
		selectBreakdownDrilldown(section, item) {
			if (this.isCategoryDrilldownRow(section, item)) {
				this.selectCategoryDrilldown(section, item)
				return
			}
			if (this.isPaymentPartnerDrilldownRow(section, item)) {
				this.selectPaymentPartnerDrilldown(section, item)
				return
			}
			if (this.isTagDrilldownRow(section, item)) {
				this.selectTagDrilldown(section, item)
				return
			}
			if (this.isHashtagDrilldownRow(section, item)) {
				this.selectHashtagDrilldown(section, item)
				return
			}
			if (this.isProjectDrilldownRow(section, item)) {
				this.selectProjectDrilldown(section, item)
			}
		},
		selectCategoryDrilldown(section, item) {
			if (!this.isCategoryDrilldownRow(section, item)) {
				return
			}
			this.clearAllDrilldowns()
			this.activeCategoryDrilldown = this.breakdownItemKey(item)
			this.selectedBreakdownDimension = 'paymentPartners'
		},
		selectPaymentPartnerDrilldown(section, item) {
			if (!this.isPaymentPartnerDrilldownRow(section, item)) {
				return
			}
			this.clearAllDrilldowns()
			this.activePaymentPartnerDrilldown = this.breakdownItemKey(item)
			this.selectedBreakdownDimension = 'categories'
		},
		selectTagDrilldown(section, item) {
			if (!this.isTagDrilldownRow(section, item)) {
				return
			}
			this.clearAllDrilldowns()
			this.activeTagDrilldown = item.id
			this.selectedBreakdownDimension = 'categories'
		},
		selectHashtagDrilldown(section, item) {
			if (!this.isHashtagDrilldownRow(section, item)) {
				return
			}
			this.clearAllDrilldowns()
			this.activeHashtagDrilldown = this.breakdownItemKey(item)
			this.selectedBreakdownDimension = 'categories'
		},
		selectProjectDrilldown(section, item) {
			if (!this.isProjectDrilldownRow(section, item)) {
				return
			}
			this.clearAllDrilldowns()
			this.activeProjectDrilldown = this.breakdownItemKey(item)
			this.selectedBreakdownDimension = 'categories'
		},
		clearAllDrilldowns() {
			this.activeCategoryDrilldown = null
			this.activePaymentPartnerDrilldown = null
			this.activeTagDrilldown = null
			this.activeHashtagDrilldown = null
			this.activeProjectDrilldown = null
		},
		normalizeBreakdownDimension() {
			const sections = this.breakdownSections
			if (!Array.isArray(sections) || sections.length === 0) {
				return
			}
			if (!sections.some(section => section.key === this.selectedBreakdownDimension)) {
				this.selectedBreakdownDimension = sections[0].key
			}
		},
		clearCategoryDrilldown(returnToCategories = false) {
			this.activeCategoryDrilldown = null
			if (returnToCategories) {
				this.selectedBreakdownDimension = 'categories'
			}
		},
		clearPaymentPartnerDrilldown(returnToPaymentPartners = false) {
			this.activePaymentPartnerDrilldown = null
			if (returnToPaymentPartners) {
				this.selectedBreakdownDimension = 'paymentPartners'
			}
		},
		clearTagDrilldown(returnToTags = false) {
			this.activeTagDrilldown = null
			if (returnToTags) {
				this.selectedBreakdownDimension = 'tags'
			}
		},
		clearHashtagDrilldown(returnToHashtags = false) {
			this.activeHashtagDrilldown = null
			if (returnToHashtags) {
				this.selectedBreakdownDimension = 'hashtags'
			}
		},
		clearProjectDrilldown(returnToProjects = false) {
			this.activeProjectDrilldown = null
			if (returnToProjects) {
				this.selectedBreakdownDimension = 'projects'
			}
		},
		openReminderOverview() {
			this.$router.push({ name: 'personal', query: { filter: 'reminder' } }).catch(() => {})
		},
		openPlannedOverview() {
			this.$router.push({ name: 'personal', query: { filter: 'future' } }).catch(() => {})
		},
		openReceiptCheck(item) {
			const filter = item?.filter || 'review'
			this.$router.push({ name: 'personal', query: { filter, hasAttachment: 'false' } }).catch(() => {})
		},
		sharedProjectBalanceClass(project) {
			const balanceCents = Number(project?.currentUserBalanceCents || 0)
			if (balanceCents > 0) {
				return 'shared-project-balance-positive'
			}
			if (balanceCents < 0) {
				return 'shared-project-balance-negative'
			}
			return 'shared-project-balance-neutral'
		},
		sharedProjectBalanceLabel(project) {
				const balanceCents = Number(project?.currentUserBalanceCents || 0)
				if (balanceCents > 0) {
					return this.$texts.analytics.inPlus()
				}
				if (balanceCents < 0) {
					return this.$texts.analytics.inMinus()
				}
				return this.$texts.analytics.balancedLower()
			},
		budgetHistoryPeriodLabel(item) {
			const start = this.formatDate(item?.periodStart)
			const periodEnd = Math.max(Number(item?.periodStart || 0), Number(item?.periodEnd || 0) - 86400)
			const end = this.formatDate(periodEnd)
			return start && end ? `${start} - ${end}` : ''
		},
			budgetHistoryReasonLabel(reason) {
				if (reason === 'period_closed') {
					return this.$texts.analytics.closed()
				}
				if (reason === 'deleted') {
					return this.$texts.analytics.deletedBefore()
				}
				return this.$texts.analytics.changedBefore()
			},
			budgetHistoryStatusLabel(status) {
				if (status === 'exceeded') {
					return this.$texts.analytics.exceeded()
				}
				if (status === 'warning') {
					return this.$texts.analytics.critical()
				}
				return this.$texts.analytics.inPlan()
			},
		async printReport() {
			const source = this.$refs.printSource
			if (!source) {
				window.print()
				return
			}

			const existingFrame = document.getElementById('cobudget-print-frame')
			if (existingFrame) {
				existingFrame.remove()
			}

				const printFrame = document.createElement('iframe')
				printFrame.id = 'cobudget-print-frame'
				printFrame.title = this.$texts.analytics.printTitle()
			printFrame.setAttribute('aria-hidden', 'true')
			Object.assign(printFrame.style, {
				position: 'fixed',
				left: '-10000px',
				top: '0',
				width: '1200px',
				height: '1600px',
				border: '0',
				opacity: '0',
				pointerEvents: 'none'
			})
			document.body.appendChild(printFrame)

			const printWindow = printFrame.contentWindow
			const printDocument = printFrame.contentDocument || printWindow?.document
			if (!printWindow || !printDocument) {
				printFrame.remove()
				window.print()
				return
			}

			const printContent = source.cloneNode(true)
			printContent.querySelectorAll('.no-print').forEach(element => element.remove())

			printDocument.open()
			printDocument.write(`
				<!doctype html>
				<html>
					<head>
						<meta charset="utf-8">
						<meta name="viewport" content="width=device-width, initial-scale=1">
						<base href="${this.escapeHtml(document.baseURI)}">
							<title>${this.escapeHtml(this.$texts.analytics.printTitle())}</title>
						<style>${this.printDocumentCss()}</style>
					</head>
					<body>
						<main id="cobudget-print-document"></main>
					</body>
				</html>
			`)
			printDocument.close()
			printDocument.getElementById('cobudget-print-document')?.appendChild(printContent)

			let cleanupTimer = null
			const cleanup = () => {
				if (cleanupTimer) {
					window.clearTimeout(cleanupTimer)
					cleanupTimer = null
				}
				printFrame.remove()
				printWindow.removeEventListener('afterprint', cleanup)
			}

			printWindow.addEventListener('afterprint', cleanup)
			await this.waitForPrintAssets(printDocument)
			await this.preparePrintFrame(printFrame, printDocument)
			window.setTimeout(() => {
				printWindow.focus()
				printWindow.print()
				cleanupTimer = window.setTimeout(cleanup, 60000)
			}, 100)
		},
		printDocumentCss() {
			return `
				@page { margin: 12mm; }

				:root {
					--color-main-background: #ffffff;
					--color-main-text: #222222;
					--color-text-maxcontrast: #666666;
					--color-border: #e5e5e5;
					--color-background-hover: #f5f5f5;
					--color-background-darker: #ededed;
					--color-primary-element: #0082c9;
				}

				html,
				body {
					width: auto !important;
					height: auto !important;
					min-height: 0 !important;
					margin: 0 !important;
					padding: 0 !important;
					overflow: visible !important;
					background: #fff !important;
					color: #222 !important;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif !important;
					font-size: var(--cobudget-font-base) !important;
					line-height: 1.4 !important;
				}

				* {
					box-sizing: border-box !important;
					-webkit-print-color-adjust: exact;
					print-color-adjust: exact;
				}

				#cobudget-print-document,
				.analytics-view,
				.analytics-section,
				.analytics-report {
					display: block !important;
					position: static !important;
					width: 100% !important;
					max-width: none !important;
					height: auto !important;
					min-height: 0 !important;
					max-height: none !important;
					margin: 0 !important;
					padding: 0 !important;
					overflow: visible !important;
					background: #fff !important;
				}

				.view-header {
					display: block !important;
					margin: 0 0 16px !important;
					padding: 0 !important;
				}

				.view-header-title {
					margin: 0 0 8px !important;
					padding: 0 !important;
					color: #222 !important;
					font-size: var(--cobudget-font-title-md) !important;
					line-height: 1.2 !important;
				}

				.view-header-subtitle {
					margin: 0 !important;
					padding: 0 !important;
					color: #666 !important;
					font-size: var(--cobudget-font-base) !important;
				}

				.no-print,
				.view-header-actions,
				.period-switch,
				.mini-switch,
				.dimension-switch {
					display: none !important;
				}

				.print-only {
					display: block !important;
				}

				.analytics-report {
					display: block !important;
				}

				.analytics-report > * {
					margin-bottom: 12px !important;
				}

				.print-meta {
					display: block !important;
					margin-bottom: 10px !important;
					color: #555 !important;
					font-size: var(--cobudget-font-sm) !important;
				}

				.summary-grid,
				.comparison-grid,
				.projection-grid,
				.available-forecast-grid,
				.insight-grid,
				.breakdown-grid,
				.breakdown-table-grid {
					display: grid !important;
					gap: 12px !important;
				}

				.summary-grid {
					grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)) !important;
				}

				.breakdown-grid,
				.breakdown-table-grid,
				.insight-grid,
				.comparison-grid,
				.projection-grid,
				.available-forecast-grid {
					grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)) !important;
				}

				.breakdown-grid.has-three-breakdown-columns,
				.breakdown-grid.has-tags-and-projects,
				.breakdown-table-grid.has-three-breakdown-columns,
				.breakdown-table-grid.has-tags-and-projects {
					grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
				}

				.breakdown-table-grid,
				.breakdown-table-grid.has-three-breakdown-columns,
				.breakdown-table-grid.has-tags-and-projects {
					grid-template-columns: 1fr !important;
				}

				.breakdown-side-stack {
					display: flex !important;
					min-width: 0 !important;
					flex-direction: column !important;
					gap: 12px !important;
				}

				.analytics-card,
				.summary-card,
				.insight-card,
				.comparison-grid > div,
				.projection-grid > div,
				.available-forecast-main,
				.available-forecast-metrics > div {
					border: 1px solid #e5e5e5 !important;
					border-radius: 8px !important;
					background: #fff !important;
					color: #222 !important;
					box-shadow: none !important;
					overflow: visible !important;
				}

				.summary-card,
				.insight-card,
				.comparison-grid > div,
				.projection-grid > div,
				.available-forecast-main,
				.available-forecast-metrics > div {
					display: flex !important;
					flex-direction: column !important;
					gap: 6px !important;
					padding: 14px !important;
				}

				.summary-card span,
				.insight-card span,
				.insight-card small,
				.comparison-grid span,
				.projection-grid span,
				.available-forecast-main span,
				.available-forecast-main small,
				.available-forecast-metrics span,
				.available-forecast-range span {
					color: #666 !important;
					font-size: var(--cobudget-font-compact) !important;
					font-weight: 600 !important;
				}

				.summary-card strong {
					font-size: var(--cobudget-font-section) !important;
					line-height: 1.2 !important;
				}

				.insight-card strong {
					font-size: var(--cobudget-font-lg) !important;
				}

				.summary-card small,
				.comparison-grid small {
					color: #666 !important;
				}

				.analytics-card {
					padding: 16px !important;
					break-inside: auto !important;
					page-break-inside: auto !important;
				}

				.summary-card,
				.insight-card,
				.comparison-grid > div,
				.projection-grid > div,
				.available-forecast-main,
				.available-forecast-metrics > div {
					break-inside: avoid !important;
					page-break-inside: avoid !important;
				}

				.card-header {
					display: flex !important;
					justify-content: space-between !important;
					gap: 16px !important;
					align-items: flex-start !important;
					margin-bottom: 14px !important;
				}

				.card-header h3 {
					margin: 0 !important;
					font-size: var(--cobudget-font-xl) !important;
					line-height: 1.25 !important;
				}

				.card-header p {
					margin: 4px 0 0 !important;
					color: #666 !important;
				}

				.chart-legend {
					display: flex !important;
					gap: 12px !important;
					flex-wrap: wrap !important;
					justify-content: flex-end !important;
					color: #666 !important;
					font-size: var(--cobudget-font-sm) !important;
					font-weight: 600 !important;
				}

				.chart-legend span {
					display: inline-flex !important;
					align-items: center !important;
					gap: 6px !important;
				}

				.chart-legend i {
					display: inline-block !important;
					width: 10px !important;
					height: 10px !important;
					border-radius: 50% !important;
				}

				.legend-income,
				.series-bar.income {
					background: #10b981 !important;
				}

				.legend-expense,
				.series-bar.expense {
					background: var(--cobudget-error) !important;
				}

				.legend-balance {
					background: #0082c9 !important;
				}

				.line-chart {
					border: 1px solid #e5e5e5 !important;
					border-radius: 8px !important;
					background: linear-gradient(180deg, #f5f5f5, #ffffff) !important;
					height: 170px !important;
					overflow: hidden !important;
				}

				.line-chart svg {
					width: 100% !important;
					height: 100% !important;
				}

				.zero-line {
					stroke: #e5e5e5 !important;
					stroke-width: 2 !important;
				}

				.balance-line {
					fill: none !important;
					stroke: #0082c9 !important;
					stroke-width: 6 !important;
					stroke-linecap: round !important;
					stroke-linejoin: round !important;
				}

				.balance-dot {
					fill: #0082c9 !important;
					stroke: #fff !important;
					stroke-width: 4 !important;
				}

				.series-bars {
					display: grid !important;
					grid-template-columns: repeat(auto-fit, minmax(14px, 1fr)) !important;
					gap: 4px !important;
					align-items: end !important;
					min-height: 120px !important;
					margin-top: 14px !important;
				}

				.series-item {
					display: flex !important;
					min-width: 0 !important;
					flex-direction: column !important;
					align-items: center !important;
					gap: 6px !important;
				}

				.series-bar-pair {
					display: flex !important;
					align-items: end !important;
					justify-content: center !important;
					gap: 3px !important;
					width: 100% !important;
					height: 82px !important;
				}

				.series-bar {
					display: block !important;
					width: min(14px, 40%) !important;
					border-radius: 999px 999px 0 0 !important;
				}

				.series-item small {
					min-height: 14px !important;
					color: #666 !important;
					font-size: var(--cobudget-font-xs) !important;
					white-space: nowrap !important;
				}

				.series-item small.muted {
					opacity: 0 !important;
				}

				.comparison-grid strong,
				.projection-grid strong {
					font-size: var(--cobudget-font-xl) !important;
				}

				.available-forecast-card {
					border-left: 4px solid #0082c9 !important;
				}

				.forecast-confidence {
					display: inline-flex !important;
					align-items: center !important;
					padding: 6px 8px !important;
					border: 1px solid #e5e5e5 !important;
					border-radius: 8px !important;
					color: #666 !important;
					font-size: var(--cobudget-font-sm) !important;
					font-weight: 700 !important;
					background: #fff !important;
				}

				.available-forecast-grid {
					grid-template-columns: minmax(220px, 1fr) 2fr !important;
				}

				.available-forecast-metrics {
					display: grid !important;
					grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
					gap: 12px !important;
				}

				.available-forecast-main strong {
					font-size: var(--cobudget-font-title-sm) !important;
					line-height: 1.15 !important;
				}

				.available-forecast-metrics strong {
					font-size: var(--cobudget-font-lg) !important;
				}

				.available-forecast-range {
					display: flex !important;
					justify-content: space-between !important;
					gap: 16px !important;
					align-items: center !important;
					margin-top: 12px !important;
					padding-top: 12px !important;
					border-top: 1px solid #e5e5e5 !important;
				}

				.breakdown-section h4 {
					margin: 0 0 12px !important;
					font-size: var(--cobudget-font-ui) !important;
				}

				.breakdown-print-groups {
					display: block !important;
				}

				.breakdown-print-group + .breakdown-print-group {
					margin-top: 18px !important;
					padding-top: 16px !important;
					border-top: 1px solid #e5e5e5 !important;
				}

				.breakdown-print-group > h4 {
					margin: 0 0 12px !important;
					font-size: var(--cobudget-font-md-plus) !important;
				}

				.breakdown-section h5 {
					margin: 0 0 12px !important;
					font-size: var(--cobudget-font-ui) !important;
				}

				.breakdown-table,
				.budget-history-table,
				.upcoming-table {
					width: 100% !important;
					border-collapse: collapse !important;
					border: 1px solid #e5e5e5 !important;
					border-radius: 8px !important;
					overflow: hidden !important;
					background: #fff !important;
				}

				.breakdown-table th,
				.breakdown-table td,
				.budget-history-table th,
				.budget-history-table td,
				.upcoming-table th,
				.upcoming-table td {
					padding: 8px 10px !important;
					border-bottom: 1px solid #e5e5e5 !important;
					text-align: left !important;
					vertical-align: top !important;
				}

				.breakdown-table th,
				.budget-history-table th,
				.upcoming-table th {
					background: #f5f5f5 !important;
					color: #666 !important;
					font-size: var(--cobudget-font-xs) !important;
					text-transform: uppercase !important;
				}

				.breakdown-table tfoot td {
					border-top: 1px solid #e5e5e5 !important;
					border-bottom: none !important;
					background: #f5f5f5 !important;
					font-weight: 800 !important;
				}

				.amount-cell,
				.average-cell,
				.progress-cell,
				.share-cell {
					text-align: right !important;
					white-space: nowrap !important;
				}

				.amount-cell {
					font-weight: 700 !important;
				}

				.budget-history-summary {
					display: flex !important;
					gap: 8px !important;
					flex-wrap: wrap !important;
					justify-content: flex-end !important;
				}

				.budget-history-summary span {
					padding: 6px 8px !important;
					border: 1px solid #e5e5e5 !important;
					border-radius: 8px !important;
					color: #666 !important;
					font-size: var(--cobudget-font-sm) !important;
				}

				.budget-history-table td:first-child {
					min-width: 160px !important;
				}

				.budget-history-table td:first-child span {
					display: block !important;
					margin-top: 2px !important;
					color: #666 !important;
					font-size: var(--cobudget-font-sm) !important;
				}

				.budget-history-status {
					display: inline-flex !important;
					padding: 4px 8px !important;
					border-radius: 999px !important;
					font-size: var(--cobudget-font-sm) !important;
					font-weight: 800 !important;
					white-space: nowrap !important;
				}

				.budget-history-status.status-ok {
					background: #e9f7ef !important;
					color: #107c41 !important;
				}

				.budget-history-status.status-warning {
					background: #fff4ce !important;
					color: #8a5a00 !important;
				}

				.budget-history-status.status-exceeded {
					background: var(--cobudget-error-light) !important;
					color: var(--cobudget-error) !important;
				}

				.breakdown-list {
					display: flex !important;
					flex-direction: column !important;
					gap: 12px !important;
				}

				.breakdown-row-main,
				.outlier-row {
					display: flex !important;
					align-items: flex-start !important;
					justify-content: space-between !important;
					gap: 10px !important;
				}

				.breakdown-row-main span {
					min-width: 0 !important;
					overflow: hidden !important;
					text-overflow: ellipsis !important;
					white-space: nowrap !important;
				}

				.breakdown-row-main strong,
				.outlier-row > strong {
					white-space: nowrap !important;
				}

				.breakdown-track {
					height: 8px !important;
					margin: 6px 0 4px !important;
					overflow: hidden !important;
					border-radius: 999px !important;
					background: #ededed !important;
				}

				.breakdown-track span {
					display: block !important;
					height: 100% !important;
					border-radius: inherit !important;
					background: #0082c9 !important;
				}

				.breakdown-row small,
				.baseline-label {
					color: #666 !important;
					font-size: var(--cobudget-font-sm) !important;
				}

				.mini-empty {
					padding: 14px !important;
					border: 1px dashed #e5e5e5 !important;
					border-radius: 8px !important;
					color: #666 !important;
					text-align: center !important;
				}

				.outlier-list {
					display: flex !important;
					flex-direction: column !important;
					border: 1px solid #e5e5e5 !important;
					border-radius: 8px !important;
					overflow: hidden !important;
				}

				.outlier-row {
					padding: 12px 14px !important;
					border-bottom: 1px solid #e5e5e5 !important;
					background: #fff !important;
				}

				.outlier-row:last-child {
					border-bottom: none !important;
				}

				.outlier-row div {
					display: flex !important;
					min-width: 0 !important;
					flex-direction: column !important;
					gap: 4px !important;
				}

				.outlier-row div strong,
				.outlier-row div span {
					min-width: 0 !important;
					overflow: hidden !important;
					text-overflow: ellipsis !important;
					white-space: nowrap !important;
				}

				.outlier-row div span {
					color: #666 !important;
					font-size: var(--cobudget-font-compact) !important;
				}

				.shared-projects-grid {
					display: grid !important;
					grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
					gap: 12px !important;
				}

				.shared-project-card {
					display: flex !important;
					flex-direction: column !important;
					gap: 12px !important;
					padding: 14px !important;
					border: 1px solid #e5e5e5 !important;
					border-radius: 8px !important;
					background: #f5f5f5 !important;
					break-inside: avoid !important;
					page-break-inside: avoid !important;
				}

				.shared-project-header,
				.shared-project-member {
					display: flex !important;
					justify-content: space-between !important;
					gap: 10px !important;
				}

				.shared-project-header h4 {
					margin: 0 !important;
					font-size: var(--cobudget-font-md) !important;
				}

				.shared-project-total {
					display: flex !important;
					flex-direction: column !important;
					align-items: flex-end !important;
					white-space: nowrap !important;
				}

				.shared-project-metrics {
					display: grid !important;
					grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
					gap: 8px !important;
				}

				.shared-project-metrics div,
				.shared-project-members {
					border: 1px solid #e5e5e5 !important;
					border-radius: 8px !important;
					background: #fff !important;
				}

				.shared-project-metrics div {
					display: flex !important;
					flex-direction: column !important;
					gap: 4px !important;
					padding: 10px !important;
				}

				.shared-project-members {
					display: flex !important;
					flex-direction: column !important;
					overflow: hidden !important;
				}

				.shared-project-member {
					padding: 8px 10px !important;
					border-bottom: 1px solid #e5e5e5 !important;
				}

				.shared-project-member:last-child {
					border-bottom: none !important;
				}

				.shared-project-header span,
				.shared-project-total span,
				.shared-project-metrics span {
					color: #666 !important;
					font-size: var(--cobudget-font-sm) !important;
					font-weight: 600 !important;
				}

				.shared-project-balance-label {
					font-size: var(--cobudget-font-xs) !important;
					font-weight: 700 !important;
				}

				.shared-project-balance-positive {
					color: #107c41 !important;
				}

				.shared-project-balance-negative {
					color: var(--cobudget-error) !important;
				}

				.shared-project-balance-neutral {
					color: #666 !important;
				}

				.positive {
					color: #107c41 !important;
				}

				.negative {
					color: var(--cobudget-error) !important;
				}
			`
		},
		preparePrintFrame(printFrame, printDocument) {
			return new Promise(resolve => {
				const resize = () => {
					const height = Math.max(
						printDocument.body?.scrollHeight || 0,
						printDocument.documentElement?.scrollHeight || 0
					)
					printFrame.style.height = `${Math.max(height + 80, 1600)}px`
					resolve()
				}
				window.requestAnimationFrame(() => {
					window.requestAnimationFrame(resize)
				})
			})
		},
		waitForPrintAssets(printDocument) {
			const links = Array.from(printDocument.querySelectorAll('link[rel="stylesheet"]'))
			const images = Array.from(printDocument.images || [])
			const pendingLinks = links
				.filter(link => !link.sheet)
				.map(link => new Promise(resolve => {
					link.addEventListener('load', resolve, { once: true })
					link.addEventListener('error', resolve, { once: true })
				}))
			const pendingImages = images
				.filter(image => !image.complete)
				.map(image => new Promise(resolve => {
					image.addEventListener('load', resolve, { once: true })
					image.addEventListener('error', resolve, { once: true })
				}))
			const timeout = new Promise(resolve => window.setTimeout(resolve, 1200))
			return Promise.race([
				Promise.all([...pendingLinks, ...pendingImages]),
				timeout
			])
		},
		escapeHtml(value) {
			return String(value)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;')
		},
		formatCents(cents) {
			return this.$formatMoneyFromCents(Number(cents || 0))
		},
		formatSignedCents(cents) {
			return this.$formatSignedMoney(Number(cents || 0) / 100)
		},
		formatDate(timestamp) {
			const date = new Date(Number(timestamp || 0) * 1000)
			if (Number.isNaN(date.getTime())) {
				return ''
			}
			return new Intl.DateTimeFormat(undefined, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
		},
		summaryAverageCents(type, unit) {
			const summary = this.analytics.summary || {}
			const metricType = type === 'income' ? 'Income' : 'Expense'
			const metricUnit = unit === 'day' ? 'Day' : unit === 'week' ? 'Week' : 'Month'
			return Number(summary[`average${metricType}Per${metricUnit}Cents`] || 0)
		},
			summaryAverageTooltip(type) {
				const title = type === 'income' ? this.$texts.analytics.averageIncome() : this.$texts.analytics.averageExpenses()
				const basis = this.summaryAverageUnit === 'day'
					? this.$texts.analytics.calculatedUntilToday(Number(this.analytics.summary?.averageDayCount || this.periodDays()))
					: this.$texts.analytics.currentMonthExcluded(Number(this.analytics.summary?.averageMonthCount || this.periodMonths()))
				return [
					title,
					basis,
					this.$texts.analytics.valuePerMonth(this.formatCents(this.summaryAverageCents(type, 'month'))),
					this.$texts.analytics.valuePerWeek(this.formatCents(this.summaryAverageCents(type, 'week'))),
					this.$texts.analytics.valuePerDay(this.formatCents(this.summaryAverageCents(type, 'day')))
				].join('\n')
			},
		amountClass(cents) {
			const value = Number(cents || 0)
			if (value > 0) {
				return 'positive'
			}
			if (value < 0) {
				return 'negative'
			}
			return ''
		},
		expenseComparisonDetail(cents) {
			const value = Number(cents || 0)
			if (value > 0) {
				return 'mehr ausgegeben als im Vormonat'
			}
			if (value < 0) {
				return 'weniger ausgegeben als im Vormonat'
			}
			return 'gleich viel ausgegeben wie im Vormonat'
		},
		chartY(value) {
			const { min, span } = this.chartRange
			return Math.round(235 - (((Number(value || 0) - min) / span) * 200))
		},
		barHeight(cents) {
			const height = Math.max(4, Math.round((Math.abs(Number(cents || 0)) / this.seriesMaxAmount) * 100))
			return `${height}%`
		},
		showSeriesLabel(index) {
			const length = this.series.length
			if (length <= 12) {
				return true
			}
			return index === 0 || index === length - 1 || index % 5 === 0
		},
		breakdownTotalCents(type) {
			const summary = this.analytics.summary || {}
			return type === 'expense'
				? Number(summary.expenseCents || 0)
				: Number(summary.incomeCents || 0)
		},
		breakdownAverageLabel(item) {
			const divisor = this.summaryAverageUnit === 'day' ? this.periodDays() : this.breakdownMonthDivisor()
			return this.formatCents(this.averageCents(item.amountCents, divisor))
		},
		breakdownSectionTotalCents(section) {
			return this.breakdownItemsTotal(section?.items || [])
		},
		breakdownSectionBookingCount(section) {
			return (Array.isArray(section?.items) ? section.items : []).reduce((sum, item) => sum + Number(item.count || 0), 0)
		},
		breakdownSectionShareLabel(section) {
			return this.breakdownSectionTotalCents(section) > 0 ? '100 %' : '0 %'
		},
		breakdownSectionAverageLabel(section) {
			const divisor = this.summaryAverageUnit === 'day' ? this.periodDays() : this.breakdownMonthDivisor()
			return this.formatCents(this.averageCents(this.breakdownSectionTotalCents(section), divisor))
		},
		breakdownShareLabel(item, section) {
			const total = Math.max(0, Number(section.totalCents || 0))
			const amount = Math.max(0, Number(item.amountCents || 0))
			if (total <= 0 || amount <= 0) {
				return '0 %'
			}

			const percent = (amount / total) * 100
			if (percent > 0 && percent < 1) {
				return '<1 %'
			}

			return `${Math.round(percent)} %`
		},
		breakdownTrendIcon(trend) {
			const direction = trend?.direction || ''
			if (direction === 'up' || direction === 'new') {
				return '↑'
			}
			if (direction === 'down' || direction === 'gone') {
				return '↓'
			}
			return '→'
		},
		breakdownTrendClass(trend) {
			return {
				'is-positive': trend?.tone === 'positive',
				'is-negative': trend?.tone === 'negative',
				'is-strong': trend?.level === 'strong'
			}
		},
		breakdownTrendPercentLabel(trend) {
			if (trend?.deltaPercentTenths === null || trend?.deltaPercentTenths === undefined) {
				return ''
			}
			const percent = Math.round(Number(trend.deltaPercentTenths || 0) / 10)
			return `${percent > 0 ? '+' : ''}${percent} %`
		},
		breakdownTrendLabel(trend, context = 'comparison') {
			if (!trend) {
				return ''
			}

				const isDirection = context === 'direction'
				if (trend.direction === 'new') {
					return isDirection
						? this.$texts.analytics.newRecentTrend(this.formatCents(trend.previousCents))
						: this.$texts.analytics.newComparisonTrend(this.formatCents(trend.previousCents))
				}
				if (trend.direction === 'gone') {
					return isDirection
						? this.$texts.analytics.goneRecentTrend(this.formatCents(trend.previousCents))
						: this.$texts.analytics.goneComparisonTrend(this.formatCents(trend.previousCents))
				}

				const percent = this.breakdownTrendPercentLabel(trend)
				return this.$texts.analytics.trendValue(
					this.formatSignedCents(trend.deltaCents),
					percent,
					isDirection ? this.$texts.analytics.recentDevelopment() : this.$texts.analytics.comparisonPeriod()
				)
			},
		breakdownTooltip(item, section) {
				const lines = [
					String(item.name || section.shortTitle || this.$texts.analytics.focus()),
					this.bookingCountLabel(item.count),
					this.$texts.analytics.shareLabel(this.breakdownShareLabel(item, section)),
					this.$texts.analytics.totalLabel(this.formatCents(item.amountCents)),
					this.$texts.analytics.averagePerMonthLabel(this.formatCents(this.averageCents(item.amountCents, this.breakdownMonthDivisor()))),
					this.$texts.analytics.averagePerWeekLabel(this.formatCents(this.averageCents(item.amountCents, this.periodWeeks()))),
					this.$texts.analytics.averagePerDayLabel(this.formatCents(this.averageCents(item.amountCents, this.periodDays())))
				]
				if (item.trend) {
					lines.splice(1, 0, this.$texts.analytics.directionLabel(this.breakdownTrendLabel(item.trend, 'direction')))
				}
				if (item.comparison) {
					lines.splice(item.trend ? 2 : 1, 0, this.$texts.analytics.comparisonLabel(this.breakdownTrendLabel(item.comparison, 'comparison')))
				}
			return lines.join('\n')
		},
		breakdownSectionTooltip(section) {
				const totalCents = this.breakdownSectionTotalCents(section)
				return [
					this.$texts.analytics.totalSuffix(section?.shortTitle || section?.title || this.$texts.analytics.focus()),
					this.bookingCountLabel(this.breakdownSectionBookingCount(section)),
					this.$texts.analytics.shareLabel(this.breakdownSectionShareLabel(section)),
					this.$texts.analytics.totalLabel(this.formatCents(totalCents)),
					this.$texts.analytics.averagePerMonthLabel(this.formatCents(this.averageCents(totalCents, this.breakdownMonthDivisor()))),
					this.$texts.analytics.averagePerWeekLabel(this.formatCents(this.averageCents(totalCents, this.periodWeeks()))),
					this.$texts.analytics.averagePerDayLabel(this.formatCents(this.averageCents(totalCents, this.periodDays())))
				].join('\n')
			},
			bookingCountLabel(count) {
				return this.$texts.analytics.bookings(Number(count || 0))
			},
		averageCents(amountCents, divisor) {
			return Math.round(Number(amountCents || 0) / Math.max(1, Number(divisor || 1)))
		},
		periodDays() {
			const period = this.analytics.period || {}
			const start = Number(period.start || 0)
			const end = Number(period.end || 0)
			if (start <= 0 || end <= start) {
				return 1
			}

			return Math.max(1, Math.ceil((end - start) / 86400))
		},
		periodWeeks() {
			return Math.max(1, this.periodDays() / 7)
		},
		periodMonths() {
			const monthCount = Number(this.analytics.period?.monthCount || 0)
			if (monthCount > 0) {
				return monthCount
			}

			return Math.max(1, this.periodDays() / 30.4375)
		},
		breakdownMonthDivisor() {
			return Math.max(1, Number(this.analytics.summary?.averageMonthCount || 0) || this.periodMonths())
		},
		breakdownWidth(amountCents, items) {
			const max = Math.max(1, ...items.map(item => Number(item.amountCents || 0)))
			return `${Math.max(3, Math.round((Number(amountCents || 0) / max) * 100))}%`
		},
			outlierTitle(item) {
				return item.description || item.paymentPartnerName || item.categoryName || this.$texts.analytics.expense()
			},
			outlierContext(item) {
				return [item.categoryName, item.paymentPartnerName, item.projectName]
					.filter(Boolean)
					.join(' · ') || this.$texts.analytics.noAssignment()
			},
			upcomingTitle(item) {
				return item.description || item.paymentPartnerName || item.categoryName || (item.type === 'income' ? this.$texts.analytics.income() : this.$texts.analytics.expense())
			},
			upcomingContext(item) {
				return [item.categoryName, item.paymentPartnerName, item.projectName]
					.filter(Boolean)
					.join(' · ') || this.$texts.analytics.noAssignment()
			}
	}
}
</script>

<style scoped>
.analytics-view {
	width: 100%;
}

.analytics-subtitle {
	padding-left: 28px;
}

.analytics-section {
	width: min(900px, calc(100% - 56px));
	margin: 0 28px 40px;
}

.mini-switch,
.dimension-switch {
	display: inline-flex;
	padding: 4px;
	border-radius: 8px;
	background: var(--cobudget-surface-muted, #f5f5f5);
	gap: 2px;
}

.dimension-switch {
	margin-bottom: 18px;
	flex-wrap: wrap;
}

.mini-switch button,
.dimension-switch button {
	min-height: 38px;
	padding: 0 14px;
	border: none;
	border-radius: 6px;
	background: transparent;
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-weight: 600;
	cursor: pointer;
}

.mini-switch button:hover,
.mini-switch button:focus-visible,
.dimension-switch button:hover,
.dimension-switch button:focus-visible {
	background: var(--cobudget-surface, #fff);
	outline: 2px solid var(--color-primary-element, var(--color-primary, #0082c9));
	outline-offset: 1px;
}

.mini-switch button.active,
.dimension-switch button.active {
	background: var(--cobudget-surface, #fff);
	color: var(--color-primary-element, var(--color-primary, #0082c9));
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.analytics-empty {
	padding: 32px 20px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	text-align: center;
	background: var(--cobudget-surface, #fff);
}

.analytics-report {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.print-meta {
	display: none;
}

.comparison-grid,
.projection-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: 12px;
}

.comparison-grid,
.projection-grid {
	grid-template-columns: repeat(3, minmax(0, 1fr));
}

.analytics-card,
.comparison-grid div,
.projection-grid div {
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.comparison-grid span,
.projection-grid span {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
}

.analytics-card {
	padding: 18px;
}

.card-header {
	display: flex;
	justify-content: space-between;
	gap: 16px;
	align-items: flex-start;
	margin-bottom: 14px;
}

.card-header h3 {
	margin: 0;
	font-size: var(--cobudget-font-xl);
	line-height: 1.25;
}

.card-header p {
	margin: 4px 0 0;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.analytics-overview-action,
.analytics-overview-action.button-vue {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: var(--cobudget-button-height);
	padding: 0 12px;
	border: 0 !important;
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface-muted, #f5f5f5) !important;
	box-shadow: none !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
	font-style: normal;
	font-weight: 800;
	line-height: 1.2;
	white-space: nowrap;
	transition: background-color 0.15s ease, color 0.15s ease;
}

.analytics-overview-action:hover,
.analytics-overview-action:focus-visible,
.analytics-overview-action.button-vue:hover,
.analytics-overview-action.button-vue:focus-visible {
	border: 0 !important;
	background: var(--cobudget-border, #ddd) !important;
	box-shadow: none !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
	outline: none;
}

.analytics-overview-action :deep(.button-vue__text) {
	color: inherit !important;
	font-weight: inherit;
}

.comparison-grid div,
.projection-grid div {
	padding: 14px;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.comparison-grid strong,
.projection-grid strong {
	font-size: var(--cobudget-font-xl);
}

.comparison-grid small {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.budget-history-summary {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	justify-content: flex-end;
}

.budget-history-summary span {
	padding: 6px 8px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
	font-weight: 600;
	white-space: nowrap;
	background: var(--cobudget-page-background, #fff);
}

.budget-history-summary strong {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.budget-history-summary .positive strong,
.budget-history-summary .positive {
	color: #107c41;
}

.budget-history-summary .negative strong,
.budget-history-summary .negative {
	color: var(--cobudget-error);
}

.budget-history-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	overflow: hidden;
	background: var(--cobudget-page-background, #fff);
}

.budget-history-table th,
.budget-history-table td {
	padding: 10px 12px;
	border-bottom: 1px solid var(--cobudget-border, #e5e5e5);
	text-align: left;
	vertical-align: top;
}

.budget-history-table tr:last-child td {
	border-bottom: none;
}

.budget-history-table th {
	background: var(--cobudget-surface-muted, #f5f5f5);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-xs);
	font-weight: 800;
	letter-spacing: 0;
	text-transform: uppercase;
	white-space: nowrap;
}

.budget-history-table td {
	font-size: var(--cobudget-font-compact);
}

.budget-history-table td:first-child {
	min-width: 190px;
}

.budget-history-table td:first-child strong,
.budget-history-table td:first-child span {
	display: block;
}

.budget-history-table td:first-child span {
	margin-top: 2px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
}

.budget-history-table .progress-cell,
.budget-history-table .amount-cell {
	text-align: right;
	white-space: nowrap;
}

.budget-history-table .progress-cell,
.budget-history-table .amount-cell {
	font-weight: 800;
}

.budget-history-status {
	display: inline-flex;
	padding: 4px 8px;
	border-radius: 999px;
	font-size: var(--cobudget-font-sm);
	font-weight: 800;
	white-space: nowrap;
}

.budget-history-status.status-ok {
	background: #e9f7ef;
	color: #107c41;
}

.budget-history-status.status-warning {
	background: var(--cobudget-warning-light);
	color: var(--cobudget-warning-dark);
}

.budget-history-status.status-exceeded {
	background: var(--cobudget-error-light);
	color: var(--cobudget-error);
}

.breakdown-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 18px;
	flex-wrap: wrap;
}

.breakdown-toolbar .dimension-switch {
	margin-bottom: 0;
}

.drilldown-clear-button {
	min-height: 38px;
	padding: 0 14px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 6px;
	background: var(--cobudget-page-background, #fff);
	color: var(--color-primary-element, #0082c9);
	font-weight: 700;
	cursor: pointer;
}

.drilldown-clear-button:hover,
.drilldown-clear-button:focus-visible {
	border-color: var(--color-primary-element, #0082c9);
	outline: 2px solid var(--color-primary-element, #0082c9);
	outline-offset: 1px;
}

.breakdown-grid,
.breakdown-table-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
	gap: 16px;
}

.breakdown-table-grid {
	grid-template-columns: 1fr;
}

.breakdown-grid.has-three-breakdown-columns,
.breakdown-grid.has-tags-and-projects {
	grid-template-columns: repeat(3, minmax(0, 1fr));
}

.breakdown-table-grid.has-three-breakdown-columns,
.breakdown-table-grid.has-tags-and-projects {
	grid-template-columns: 1fr;
}

.breakdown-side-stack {
	display: flex;
	min-width: 0;
	flex-direction: column;
	gap: 16px;
}

.breakdown-section h4 {
	margin: 0 0 12px;
	font-size: var(--cobudget-font-ui);
}

.breakdown-table,
.upcoming-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	overflow: hidden;
	background: var(--cobudget-page-background, #fff);
}

.breakdown-table th,
.upcoming-table th {
  padding: 4px 10px;
  border-bottom: 1px solid var(--cobudget-border, #ddd);
  color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
  text-align: left;
  background-color: var(--cobudget-surface-muted, #f9f9f9);
  vertical-align: top;
  white-space: nowrap;
}

.breakdown-table td,
.breakdown-table tfoot td,
.upcoming-table td {
  padding: 4px 10px;
  border-bottom: 1px solid var(--cobudget-border, #ddd);
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
  text-align: left;
	vertical-align: top;
}

.breakdown-table tr:last-child td,
.upcoming-table tr:last-child td {
	border-bottom: none;
}

.breakdown-table tfoot td {
	border-top: 1px solid var(--cobudget-border, #e5e5e5);
	border-bottom: none;
	background: var(--cobudget-surface-muted, #f5f5f5);
	font-weight: 800;
}

.breakdown-table td,
.upcoming-table td {
	font-size: var(--cobudget-font-compact);
}

.breakdown-table th:first-child,
.breakdown-table td:first-child {
	width: auto;
	min-width: 220px;
}

.breakdown-table .amount-cell,
.breakdown-table .average-cell,
.breakdown-table .share-cell,
.upcoming-table .amount-cell {
	text-align: right;
	white-space: nowrap;
}

.breakdown-table .amount-cell {
	width: 150px;
	font-weight: 800;
}

.breakdown-table .average-cell {
	width: 120px;
}

.breakdown-table .share-cell {
	width: 90px;
}

.breakdown-name-tooltip {
	cursor: help;
	text-decoration: underline dotted;
	text-decoration-thickness: 1px;
	text-underline-offset: 4px;
	white-space: nowrap;
}

.breakdown-name-line {
	display: inline-flex;
	max-width: 100%;
	align-items: center;
	gap: 7px;
	vertical-align: top;
}

.breakdown-trend-badge {
	display: inline-flex;
	width: 20px;
	height: 20px;
	flex: 0 0 20px;
	align-items: center;
	justify-content: center;
	border-radius: 999px;
	background: var(--cobudget-surface-strong, var(--color-background-darker, #ededed));
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-base);
	font-weight: 900;
	line-height: 1;
	text-decoration: none;
}

.breakdown-trend-badge.is-positive {
	background: rgba(0, 130, 74, .12);
	color: #00824a;
}

.breakdown-trend-badge.is-negative {
	background: var(--cobudget-error-soft);
	color: var(--cobudget-error);
}

.breakdown-trend-badge.is-strong {

}

.breakdown-link.breakdown-name-tooltip {
	cursor: pointer;
	text-decoration-style: solid;
}

.breakdown-table tr.is-clickable {
	cursor: pointer;
}

.breakdown-table tr.is-clickable:hover td,
.breakdown-table tr.is-clickable:focus td,
.breakdown-table tr.is-clickable:focus-within td {
	background: var(--cobudget-surface-muted, #f5f5f5);
}

.breakdown-table tr.is-clickable:focus {
	outline: 2px solid var(--color-primary-element, var(--color-primary, #0082c9));
	outline-offset: -2px;
}

.breakdown-link {
	padding: 0;
	border: 0;
	background: transparent;
	color: var(--color-primary-element, #0082c9);
	font: inherit;
	font-weight: 800;
	text-align: left;
	cursor: pointer;
	text-decoration: underline;
	text-decoration-thickness: 1px;
	text-underline-offset: 3px;
}

.upcoming-table td:first-child {
	width: 120px;
	white-space: nowrap;
}

.upcoming-table td:nth-child(2) {
	min-width: 0;
}

.upcoming-table td:nth-child(2) strong,
.upcoming-table td:nth-child(2) span,
.upcoming-table td:nth-child(2) small,
.upcoming-table td:first-child small {
	display: block;
}

.upcoming-table td:nth-child(2) span,
.upcoming-table td:nth-child(2) small,
.upcoming-table td:first-child small {
	margin-top: 2px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.receipt-check-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 12px;
}

.receipt-check-card {
	display: flex;
	min-height: 128px;
	flex-direction: column;
	align-items: flex-start;
	justify-content: space-between;
	gap: 8px;
	padding: 16px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-text, var(--color-main-text, #222));
	text-align: left;
	cursor: pointer;
	transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
}

.receipt-check-card:hover,
.receipt-check-card:focus-visible {
  border: 1px solid var(--cobudget-primary, #0082c9)!important;
	background: var(--cobudget-surface-muted, #f5f5f5);
	box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
	outline: none;
}

.receipt-check-card span {
  color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
  font-size: var(--cobudget-font-sm);
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}

.receipt-check-card strong {
  font-size: var(--cobudget-font-lg);
  line-height: 1.25;
}

.receipt-check-card small {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.receipt-check-card em {
	color: var(--cobudget-primary, var(--color-primary-element, #0082c9));
	font-style: normal;
	font-weight: 800;
}

.breakdown-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.breakdown-row-main,
.outlier-row {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 10px;
}

.breakdown-row-main span {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.breakdown-row-main strong,
.outlier-row > strong {
	white-space: nowrap;
}

.breakdown-track {
	height: 8px;
	margin: 6px 0 4px;
	overflow: hidden;
	border-radius: 999px;
	background: var(--cobudget-surface-strong, var(--color-background-darker, #ededed));
}

.breakdown-track span {
	display: block;
	height: 100%;
	border-radius: inherit;
	background: var(--color-primary-element, #0082c9);
}

.breakdown-row small,
.baseline-label {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
}

.breakdown-metadata {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	cursor: help;
	text-decoration: underline dotted;
	text-decoration-thickness: 1px;
	text-underline-offset: 3px;
}

.summary-detail-tooltip {
	cursor: help;
	text-decoration: underline dotted;
	text-decoration-thickness: 1px;
	text-underline-offset: 3px;
}

.mini-empty {
	padding: 14px;
	border: 1px dashed var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	text-align: center;
}

.outlier-list {
	display: flex;
	flex-direction: column;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	overflow: hidden;
}

.outlier-row {
	padding: 12px 14px;
	border-bottom: 1px solid var(--cobudget-border, #e5e5e5);
	background: var(--cobudget-page-background, #fff);
}

.outlier-row:last-child {
	border-bottom: none;
}

.outlier-row div {
	display: flex;
	min-width: 0;
	flex-direction: column;
	gap: 4px;
}

.outlier-row div strong,
.outlier-row div span {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.outlier-row div span {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-compact);
}

.shared-projects-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 12px;
}

.shared-project-card {
	display: flex;
	min-width: 0;
	flex-direction: column;
	gap: 12px;
	padding: 14px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-surface-muted, #f5f5f5);
}

.shared-project-header {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	align-items: flex-start;
}

.shared-project-header h4 {
	margin: 0;
	font-size: var(--cobudget-font-md);
	line-height: 1.25;
}

.shared-project-header span,
.shared-project-total span,
.shared-project-metrics span {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
	font-weight: 600;
}

.shared-project-total {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 2px;
	white-space: nowrap;
}

.shared-project-total strong {
	font-size: var(--cobudget-font-lg);
}

.shared-project-metrics {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 8px;
}

.shared-project-metrics div {
	display: flex;
	min-width: 0;
	flex-direction: column;
	gap: 4px;
	padding: 10px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-page-background, #fff);
}

.shared-project-metrics strong,
.shared-project-member strong {
	white-space: nowrap;
}

.shared-project-balance-label {
	font-size: var(--cobudget-font-xs);
	font-weight: 700;
}

.shared-project-balance-positive {
	color: #107c41;
}

.shared-project-balance-negative {
	color: var(--cobudget-error);
}

.shared-project-balance-neutral {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.shared-project-members {
	display: flex;
	flex-direction: column;
	overflow: hidden;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-page-background, #fff);
}

.shared-project-member {
	display: flex;
	justify-content: space-between;
	gap: 10px;
	padding: 9px 10px;
	border-bottom: 1px solid var(--cobudget-border, #e5e5e5);
}

.shared-project-member:last-child {
	border-bottom: none;
}

.shared-project-member span {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.positive {
	color: #107c41;
}

.negative {
	color: var(--cobudget-error);
}

.print-only {
	display: none !important;
}

:global(#cobudget-print-root) {
	display: none;
}

@media (max-width: 900px) {
	.analytics-section {
		width: 100%;
		margin: 0 0 32px;
	}

	.comparison-grid,
	.projection-grid,
	.breakdown-grid,
	.breakdown-table-grid {
		grid-template-columns: 1fr;
	}

	.breakdown-toolbar {
		align-items: stretch;
		flex-direction: column;
	}

	.breakdown-table,
	.budget-history-table,
	.upcoming-table {
		display: block;
		overflow-x: auto;
	}

	.card-header {
		flex-direction: column;
		align-items: stretch;
	}
}

@media print {
	@page {
		margin: 12mm;
	}

	:global(html),
	:global(body) {
		width: auto !important;
		height: auto !important;
		min-height: 0 !important;
		overflow: visible !important;
		background: #fff !important;
	}

	:global(body) {
		padding: 0 !important;
	}

	:global(body *) {
		-webkit-print-color-adjust: exact;
		print-color-adjust: exact;
	}

	:global(body.cobudget-printing > :not(#cobudget-print-root)) {
		display: none !important;
	}

	:global(#cobudget-print-root) {
		display: block !important;
		position: static !important;
		width: 100% !important;
		max-width: none !important;
		height: auto !important;
		min-height: 0 !important;
		max-height: none !important;
		margin: 0 !important;
		padding: 0 !important;
		overflow: visible !important;
		background: #fff !important;
	}

	:global(#cobudget-print-root *) {
		overflow: visible !important;
	}

	:global(#header),
	:global(header#header),
	:global(#app-navigation),
	:global(.app-navigation),
	:global(.app-navigation-toggle),
	:global(.mobile-header),
	:global(.mobile-sidebar-overlay),
	.no-print {
		display: none !important;
	}

	.print-only {
		display: block !important;
	}

	:global(#content),
	:global(#content-vue),
	:global(.content),
	:global(#app),
	:global(.app),
	:global(#cobudget-app),
	:global(.app-content),
	:global(.app-content-wrapper),
	:global(#app-content),
	:global(.content-wrapper) {
		position: static !important;
		inset: auto !important;
		width: 100% !important;
		max-width: none !important;
		height: auto !important;
		min-height: 0 !important;
		max-height: none !important;
		padding: 0 !important;
		margin: 0 !important;
		overflow: visible !important;
		background: #fff !important;
		border: 0 !important;
		box-shadow: none !important;
		transform: none !important;
	}

	.analytics-view {
		width: 100% !important;
		max-width: none !important;
		overflow: visible !important;
		background: #fff !important;
	}

	:global(#cobudget-print-root .analytics-view),
	:global(#cobudget-print-root .analytics-section),
	:global(#cobudget-print-root .analytics-report) {
		display: block !important;
		position: static !important;
		width: 100% !important;
		max-width: none !important;
		height: auto !important;
		min-height: 0 !important;
		max-height: none !important;
		margin-left: 0 !important;
		margin-right: 0 !important;
		overflow: visible !important;
	}

	.analytics-section {
		width: 100% !important;
		margin: 0 !important;
	}

	.analytics-report {
		display: block !important;
	}

	.analytics-report > * {
		margin-bottom: 12px !important;
	}

	.analytics-page-header {
		margin-bottom: 8px;
	}

	.analytics-subtitle,
	.view-header-title {
		padding-left: 0 !important;
	}

	.print-meta {
		display: block;
		margin-bottom: 10px;
		color: #555;
		font-size: var(--cobudget-font-sm);
	}

	.analytics-card,
	.summary-card {
		box-shadow: none;
		overflow: visible !important;
	}

	.breakdown-print-group + .breakdown-print-group {
		margin-top: 18px !important;
		padding-top: 16px !important;
		border-top: 1px solid #e5e5e5 !important;
	}

	.breakdown-print-group > h4 {
		margin: 0 0 12px !important;
		font-size: var(--cobudget-font-md-plus) !important;
	}

	.breakdown-section h5 {
		margin: 0 0 12px !important;
		font-size: var(--cobudget-font-ui) !important;
	}

	.analytics-card {
		break-inside: auto;
		page-break-inside: auto;
	}

	.summary-card,
	.comparison-grid > div,
	.projection-grid > div,
	.available-forecast-main,
	.available-forecast-metrics > div {
		break-inside: avoid;
		page-break-inside: avoid;
	}

	.summary-grid {
		grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
	}

	.breakdown-grid,
	.breakdown-table-grid,
	.comparison-grid,
	.projection-grid,
	.available-forecast-grid {
		grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	}

	.breakdown-grid.has-three-breakdown-columns,
	.breakdown-grid.has-tags-and-projects,
	.breakdown-table-grid.has-three-breakdown-columns,
	.breakdown-table-grid.has-tags-and-projects {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}

	.breakdown-table-grid,
	.breakdown-table-grid.has-three-breakdown-columns,
	.breakdown-table-grid.has-tags-and-projects {
		grid-template-columns: 1fr;
	}

	.breakdown-side-stack {
		display: flex;
		min-width: 0;
		flex-direction: column;
		gap: 12px;
	}

	.line-chart {
		height: 170px;
	}
}
</style>
