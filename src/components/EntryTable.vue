<template>
	<div ref="scrollContainer" class="entry-table-container">
		<table class="data-table" :class="[tableClass, { archived, 'data-table--without-actions': !visibleActionsColumn }]" :aria-label="tableTitle || undefined" :data-print-title="tableTitle || undefined">
			<caption v-if="tableTitle" class="payment-table-caption">{{ tableTitle }}</caption>
			<thead>
				<tr>
					<th class="col-date" :class="{ sortable: sortEnabled }" scope="col" :tabindex="sortEnabled ? 0 : undefined" :aria-sort="sortAria('date')"
						@click="emitSort('date')" @keydown.enter.prevent="emitSort('date')" @keydown.space.prevent="emitSort('date')">
						<span class="column-heading">
							<span class="column-heading-label">{{ dateLabel }}</span>
							<component v-if="sortEnabled && sortBy === 'date'" :is="sortDir === 'asc' ? 'MenuUpIcon' : 'MenuDownIcon'" class="sort-icon" :size="20" aria-hidden="true" />
						</span>
					</th>
					<th class="col-desc" :class="{ sortable: sortEnabled }" scope="col" :tabindex="sortEnabled ? 0 : undefined" :aria-sort="sortAria('description')"
						@click="emitSort('description')" @keydown.enter.prevent="emitSort('description')" @keydown.space.prevent="emitSort('description')">
						<span class="column-heading">
							<span class="column-heading-label">{{ $texts.entry.tableDescription() }}</span>
							<component v-if="sortEnabled && sortBy === 'description'" :is="sortDir === 'asc' ? 'MenuUpIcon' : 'MenuDownIcon'" class="sort-icon" :size="20" aria-hidden="true" />
						</span>
					</th>
					<th class="col-category" :style="columnWidths ? { width: `${columnWidths.category}px` } : undefined" :class="{ sortable: sortEnabled }" scope="col" :tabindex="sortEnabled ? 0 : undefined" :aria-sort="sortAria('category_name')"
						@click="emitSort('category_name')" @keydown.enter.prevent="emitSort('category_name')" @keydown.space.prevent="emitSort('category_name')">
						<span class="column-heading">
							<span class="column-heading-label">{{ $texts.entry.tableCategory() }}</span>
							<component v-if="sortEnabled && sortBy === 'category_name'" :is="sortDir === 'asc' ? 'MenuUpIcon' : 'MenuDownIcon'" class="sort-icon" :size="20" aria-hidden="true" />
						</span>
					</th>
					<th class="col-paymentPartner" :style="columnWidths ? { width: `${columnWidths.partner}px` } : undefined" :class="{ sortable: sortEnabled }" scope="col" :tabindex="sortEnabled ? 0 : undefined" :aria-sort="sortAria('paymentPartner')"
						@click="emitSort('paymentPartner')" @keydown.enter.prevent="emitSort('paymentPartner')" @keydown.space.prevent="emitSort('paymentPartner')">
						<span class="column-heading">
							<span class="column-heading-label">{{ $texts.entry.tablePaymentPartner() }}</span>
							<component v-if="sortEnabled && sortBy === 'paymentPartner'" :is="sortDir === 'asc' ? 'MenuUpIcon' : 'MenuDownIcon'" class="sort-icon" :size="20" aria-hidden="true" />
						</span>
					</th>
					<th class="col-amount" :class="{ sortable: sortEnabled }" scope="col" :tabindex="sortEnabled ? 0 : undefined" :aria-sort="sortAria('amount')"
						@click="emitSort('amount')" @keydown.enter.prevent="emitSort('amount')" @keydown.space.prevent="emitSort('amount')">
						<span class="column-heading">
							<span class="column-heading-label">{{ $texts.entry.tableAmount(currency) }}</span>
							<component v-if="sortEnabled && sortBy === 'amount'" :is="sortDir === 'asc' ? 'MenuUpIcon' : 'MenuDownIcon'" class="sort-icon" :size="20" aria-hidden="true" />
						</span>
					</th>
					<th v-if="showPayerColumn" class="col-user" scope="col">
						<span class="visually-hidden">{{ $texts.entry.tablePaidBy() }}</span>
					</th>
					<th v-if="visibleActionsColumn" class="col-actions"></th>
				</tr>
			</thead>
			<tbody>
				<template v-for="row in tableRows" :key="row.key">
					<tr
						v-if="row.type === 'mobile-date-group'"
						class="mobile-date-group-row">
						<td :colspan="tableColumnCount" class="mobile-date-group-label">
							{{ row.label }}
						</td>
					</tr>
					<tr v-else-if="row.type === 'month-heading'" class="month-heading-row">
						<th :colspan="tableColumnCount" scope="rowgroup"><h3>{{ row.label }}</h3></th>
					</tr>
					<tr
						v-else-if="row.type === 'group'"
						class="date-group-row"
						:class="[`date-group-row--${row.level}`, { 'is-report-link': !!groupReportResolver?.(row) }]"
						:tabindex="groupReportResolver?.(row) ? 0 : undefined"
						:aria-label="groupReportResolver?.(row) ? $texts.entry.groupReport(row.periodLabel) : undefined"
						:aria-description="groupReportResolver?.(row) ? groupSummaryTooltip(row.summary) : undefined"
						:title="groupReportResolver && !groupReportResolver(row) ? $texts.entry.groupReportUnavailable() : undefined"
						@click="openGroupReport(row)"
						@keydown="onGroupReportKeydown($event, row)">
						<td :colspan="groupLabelColspan" class="date-group-label">
							<span class="date-group-period">{{ row.label }}</span>
						</td>
						<td
							class="date-group-amount"
							:class="groupAmountClass(row.summary)">
							<TableTooltip :text="groupSummaryTooltip(row.summary)" :passive="!!groupReportResolver?.(row)">
								<span class="date-group-balance">{{ formatSignedAmount(row.summary.balance) }}</span>
							</TableTooltip>
						</td>
						<td v-if="showPayerColumn" class="date-group-user" aria-hidden="true"></td>
						<td v-if="visibleActionsColumn" class="date-group-actions" aria-hidden="true"></td>
					</tr>
					<tr
						v-else
						class="entry-row"
						:class="[rowClass, entryRowClasses(row.entry), { 'clickable-row': rowClickable }]"
						:style="{ '--payment-row-accent': entryRowAccent(row.entry) }"
						:aria-selected="rowClickable ? (isEntrySelected(row.entry) ? 'true' : 'false') : undefined"
						:tabindex="rowClickable ? 0 : undefined"
						@click="onRowClick(row.entry)"
						@keydown="onRowKeydown($event, row.entry)">

						<td :data-label="dateLabel" :data-report-export-label="formatDate(row.entry.date)" class="date-cell">
							<span v-if="desktopLayout && hasAreaAssignment(row.entry)" class="entry-area-marker">
								<TableTooltip :text="rowAreaTooltip(row.entry)">
									<span class="entry-area-marker-hit" aria-hidden="true" />
								</TableTooltip>
							</span>
							<span class="date-cell-text">{{ formatDate(row.entry.date) }}</span>
						</td>
						<td :data-label="$texts.entry.tableDescription()" :title="descriptionTooltip(row.entry)" class="desc-cell">
						<EntryDescriptionCell
							:entry="row.entry"
							:date-text="formatDate(row.entry.date)"
							:show-mobile-date="!shouldGroupEntries"
							:enable-fixed-costs="enableFixedCosts"
							:enable-subscriptions="enableSubscriptions"
							:enable-child-related="enableChildRelated"
							:enable-important-payments="enableImportantPayments"
							:enable-review-payments="enableReviewPayments"
							:enable-tax-relevant="enableTaxRelevant"
							:enable-insurance="enableInsurance"
							:mobile-area="!desktopLayout && hasAreaAssignment(row.entry) ? areaCellProps(row.entry) : null"
							:paid-by-name="showProjectPayer ? memberName(row.entry.user_id) : ''" />
					</td>
					<td :data-label="$texts.entry.tableCategory()" :title="row.entry.category_name || null" class="category-cell">
						<div v-if="row.entry.category_name" class="category-content">
							<CategoryIcon v-if="row.entry.category_icon" :icon="row.entry.category_icon" :size="16" />
							<span class="cell-text">{{ row.entry.category_name }}</span>
						</div>
					</td>
					<td :data-label="$texts.entry.tablePaymentPartner()" :title="row.entry.paymentPartner || null" class="paymentPartner-cell">{{ row.entry.paymentPartner }}</td>
					<td
						:data-label="$texts.entry.tableAmount(currency)"
						class="amount-cell">
						<EntryAmountCell
							:entry="row.entry"
							:amount="displayAmount(row.entry)"
							:currency="currency"
							:amount-tooltip="amountTooltip(row.entry)"
							:area-settled="isAreaSettled(row.entry)"
							:shared-project-tooltip="isOpenSharedPayment(row.entry) ? amountTooltip(row.entry) : hasAreaAssignment(row.entry) ? '' : sharedProjectTooltip(row.entry)"
							:show-settled-icon="isProjectMode && !!row.entry.is_settled" />
						<NcButton v-if="!desktopLayout && showActionsColumn && row.entry.has_history && !canOpenEntryForm(row.entry)"
							variant="tertiary" class="mobile-history-action" :aria-label="$texts.entry.showHistory()" :title="$texts.entry.showHistory()"
							@click.stop="emitAction('history', row.entry)">
							<template #icon><HistoryIcon :size="20" /></template>
						</NcButton>
					</td>
					<td v-if="showPayerColumn" class="user-cell"
						:data-label="$texts.entry.tablePaidBy()" :data-report-export-label="actualPayerName(row.entry)"
						:aria-label="$texts.entry.paidByPerson(actualPayerName(row.entry))" :title="actualPayerName(row.entry)">
						<div class="paid-by">
							<MemberIdentity :user-id="actualPayerId(row.entry)" :display-name="actualPayerName(row.entry)"
								:is-former="row.entry.paid_by_user_id ? row.entry.paid_by_is_former : row.entry.user_is_former" avatar-only />
						</div>
					</td>
					<td v-if="visibleActionsColumn" class="actions-cell" @click.stop>
						<NcActions v-if="canActOnEntry(row.entry) || row.entry.has_history" :key="`${actionsResetKey}-${row.entry.id}`" class="entry-actions cobudget-action-menu">
							<template v-if="canActOnEntry(row.entry)">
								<NcActionButton class="cobudget-action-menu-option" v-if="canEditEntry(row.entry)" :close-after-click="true" @click="emitAction('edit', row.entry)">
									<template #icon><PencilIcon :size="20" /></template>
									{{ $texts.entry.editPayment() }}
								</NcActionButton>
								<NcActionButton class="cobudget-action-menu-option" v-if="!editOnly && canDuplicateEntry(row.entry)" :close-after-click="true" @click="emitAction('duplicate', row.entry)">
									<template #icon><PlusIcon :size="20" /></template>
									{{ $texts.entry.copyPayment() }}
								</NcActionButton>
								<NcActionButton class="cobudget-action-menu-option" v-if="!editOnly && canDeleteEntry(row.entry)" :close-after-click="true" @click="emitAction('delete', row.entry)">
									<template #icon><DeleteOutlineIcon :size="20" /></template>
									{{ $texts.entry.deletePaymentAction() }}
								</NcActionButton>
							</template>
							<NcActionButton v-if="row.entry.has_history" class="cobudget-action-menu-option entry-history-action" :close-after-click="true" @click="emitAction('history', row.entry)">
								<template #icon><HistoryIcon :size="20" /></template>
								{{ $texts.entry.showHistory() }}
							</NcActionButton>
						</NcActions>
					</td>
				</tr>
				</template>
			</tbody>
		</table>

	</div>
	<slot name="pagination" />
</template>

<script>
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import MenuUpIcon from 'vue-material-design-icons/MenuUp.vue'
import MenuDownIcon from 'vue-material-design-icons/MenuDown.vue'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import MemberIdentity from './MemberIdentity.vue'
import CategoryIcon from './CategoryIcon.vue'
import EntryAmountCell from './EntryAmountCell.vue'
import EntryDescriptionCell from './EntryDescriptionCell.vue'
import TableTooltip from './TableTooltip.vue'
import { texts } from '../l10n/texts'
import { isAreaSettled } from '../utils/areaSettlementStatus'
import { getAppLocale, formatMoney, formatSignedMoney } from '../utils/formatMoney'
import { formatCalendarLabel, getAppLanguage } from '../utils/formatDate'
import { fitPaymentColumns } from '../utils/paymentColumnWidths'

const amountResolver = entry => entry?.amount
const falseResolver = () => false
const emptyObjectResolver = () => ({})
const idResolver = value => value

export default {
	name: 'EntryTable',
	components: {
		NcButton,
		MenuUpIcon,
		MenuDownIcon,
		HistoryIcon,
		PencilIcon,
		PlusIcon,
		DeleteOutlineIcon,
		CategoryIcon,
		EntryAmountCell,
		EntryDescriptionCell,
		NcActionButton,
		NcActions,
		MemberIdentity,
		TableTooltip
	},
	props: {
		tableClass: { type: String, default: '' },
		tableTitle: { type: String, default: '' },
		rowClass: { type: String, default: '' },
		showActionsColumn: { type: Boolean, default: true },
		entries: {
			type: Array,
			required: true
		},
		mode: {
			type: String,
			default: 'personal',
			validator: value => ['personal', 'project'].includes(value)
		},
		currency: {
			type: String,
			default: ''
		},
		dateLabel: {
			type: String,
			default: () => texts.entry.tableDate()
		},
		sortBy: {
			type: String,
			default: 'date'
		},
		sortDir: {
			type: String,
			default: 'desc'
		},
		enableFixedCosts: {
			type: Boolean,
			default: true
		},
		enableSubscriptions: {
			type: Boolean,
			default: true
		},
		enableChildRelated: {
			type: Boolean,
			default: true
		},
		enableImportantPayments: {
			type: Boolean,
			default: true
		},
		enableReviewPayments: {
			type: Boolean,
			default: true
		},
		enableTaxRelevant: {
			type: Boolean,
			default: true
		},
		enableInsurance: {
			type: Boolean,
			default: true
		},
		sortEnabled: { type: Boolean, default: true },
		rowClickable: { type: Boolean, default: true },
		actionsEnabled: {
			type: Boolean,
			default: true
		},
		editOnly: { type: Boolean, default: false },
		archived: {
			type: Boolean,
			default: false
		},
		groupByDate: {
			type: Boolean,
			default: true
		},
		groupReportResolver: { type: Function, default: null },
		dateGroups: {
			type: Object,
			default: null
		},
		amountResolver: {
			type: Function,
			default: amountResolver
		},
		projectNameResolver: {
			type: Function,
			default: () => ''
		},
		projectColorResolver: { type: Function, default: () => '' },
		projectStyleResolver: {
			type: Function,
			default: emptyObjectResolver
		},
		isSharedProjectResolver: {
			type: Function,
			default: falseResolver
		},
		memberNameResolver: {
			type: Function,
			default: idResolver
		},
		showPaidBy: {
			type: Boolean,
			default: true
		},
		selectedEntryId: {
			type: [Number, String],
			default: null
		}
	},
	emits: ['delete', 'duplicate', 'edit', 'history', 'row-click', 'sort'],
	data() {
		return {
			actionsResetKey: 0,
			desktopLayout: false,
			columnWidths: null,
			columnLayoutFrame: null,
			columnResizeObserver: null,
			columnsMounted: false,
		}
	},
	mounted() {
		if (typeof window === 'undefined') return
		this.columnsMounted = true
		this.updateAreaLayout()
		window.addEventListener('resize', this.updateAreaLayout)
		if (typeof ResizeObserver !== 'undefined') {
			this.columnResizeObserver = new ResizeObserver(this.scheduleColumnLayout)
			this.columnResizeObserver.observe(this.$refs.scrollContainer)
		}
		document.fonts?.ready.then(() => this.scheduleColumnLayout())
	},
	beforeUnmount() {
		this.columnsMounted = false
		this.columnResizeObserver?.disconnect()
		if (this.columnLayoutFrame !== null) window.cancelAnimationFrame(this.columnLayoutFrame)
		if (typeof window !== 'undefined') window.removeEventListener('resize', this.updateAreaLayout)
	},
	watch: {
		columnContentKey() { this.scheduleColumnLayout() },
	},
	computed: {
		columnContentKey() {
			const contents = this.desktopLayout ? this.entries.map(entry => [entry.category_name, entry.category_icon, entry.paymentPartner]) : this.entries
			return JSON.stringify([contents, this.sortEnabled, this.shouldGroupEntries, this.showProjectPayer,
				this.enableImportantPayments, this.enableReviewPayments, this.enableTaxRelevant, this.enableFixedCosts,
				this.enableChildRelated, this.enableSubscriptions, this.enableInsurance, this.actionsEnabled, this.showActionsColumn, this.showPayerColumn])
		},
		isProjectMode() {
			return this.mode === 'project'
		},
		visibleActionsColumn() { return this.desktopLayout && this.showActionsColumn },
		showPayerColumn() { return this.showPaidBy },
		showProjectPayer() {
			return this.isProjectMode && this.showPaidBy
		},
		groupLabelColspan() {
			return 4
		},
		tableColumnCount() {
			return this.groupLabelColspan + 1 + Number(this.showPayerColumn) + Number(this.visibleActionsColumn)
		},
		shouldGroupEntries() {
			return this.groupByDate && this.sortBy === 'date'
		},
		hasExternalDateGroups() {
			return !!(
				this.dateGroups
				&& this.dateGroups.summaries
				&& typeof this.dateGroups.summaries === 'object'
				&& Array.isArray(this.dateGroups.visibleKeys)
			)
		},
		visibleDateGroupKeys() {
			return new Set(this.hasExternalDateGroups ? this.dateGroups.visibleKeys : [])
		},
		dateGroupSummaries() {
			if (this.hasExternalDateGroups) {
				return Object.entries(this.dateGroups.summaries).reduce((summaries, [key, summary]) => {
					summaries[key] = this.normalizeGroupSummary(summary)
					return summaries
				}, {})
			}

			const summaries = {}

			this.entries.forEach(entry => {
				const keys = this.dateGroupKeys(entry.date)
				if (!keys) {
					return
				}

				const rawAmount = Number(this.displayAmount(entry))
				const amount = Number.isFinite(rawAmount) ? Math.abs(rawAmount) : 0

				keys.forEach(key => {
					if (!summaries[key]) {
						summaries[key] = {
							income: 0,
							expense: 0,
							balance: 0,
							count: 0
						}
					}

					summaries[key].count += 1
					if (entry.type === 'income') {
						summaries[key].income += amount
						summaries[key].balance += amount
					} else {
						summaries[key].expense += amount
						summaries[key].balance -= amount
					}
				})
			})

			return summaries
		},
		tableRows() {
			if (!this.shouldGroupEntries) {
				return this.entries.map(entry => ({
					type: 'entry',
					key: `entry-${entry.id}`,
					entry
				}))
			}

			const rows = []
			let currentYearGroup = null
			let currentMonthGroup = null
			let currentMobileDayKey = null

			const appendEntry = entry => {
				if (this.shouldGroupEntries) {
					const dayKey = this.mobileDayGroupKey(entry.date)
					if (dayKey !== currentMobileDayKey) {
						rows.push({
							type: 'mobile-date-group',
							key: `mobile-date-${dayKey}-${rows.length}`,
							label: this.formatMobileDayGroup(entry.date)
						})
						currentMobileDayKey = dayKey
					}
				}

				rows.push({
					type: 'entry',
					key: `entry-${entry.id}`,
					entry
				})
			}

			const appendGroupSummary = group => {
				if (!group) {
					return
				}
				if (this.hasExternalDateGroups && !this.visibleDateGroupKeys.has(group.key)) {
					return
				}

				rows.push({
					type: 'group',
					key: `summary-${group.key}`,
					level: group.level,
					label: this.$texts.entry.groupClosingTitle(group.label),
					periodLabel: group.periodLabel,
					dateFrom: group.dateFrom,
					dateTo: group.dateTo,
					summary: this.dateGroupSummaries[group.key] || this.emptyGroupSummary()
				})
			}

			// The requested sort changes before its API response arrives. Group a
			// chronological copy so the previous column order cannot repeat month
			// keys and corrupt Vue's keyed row updates during that transition.
			const direction = this.sortDir === 'asc' ? 1 : -1
			const datedEntries = this.entries.map(entry => ({ entry, date: this.dateFromTimestamp(entry.date) }))
			datedEntries.sort((left, right) => {
				if (!left.date) return right.date ? 1 : 0
				if (!right.date) return -1
				return (left.date.getTime() - right.date.getTime()) * direction
			})

			datedEntries.forEach(({ entry, date }) => {
				if (!date) {
					appendGroupSummary(currentMonthGroup)
					appendGroupSummary(currentYearGroup)
					currentMonthGroup = null
					currentYearGroup = null
					appendEntry(entry)
					return
				}

				const yearKey = this.yearGroupKey(date)
				const monthKey = this.monthGroupKey(date)

				if (currentYearGroup && yearKey !== currentYearGroup.key) {
					appendGroupSummary(currentMonthGroup)
					appendGroupSummary(currentYearGroup)
					currentMonthGroup = null
					currentYearGroup = null
				} else if (currentMonthGroup && monthKey !== currentMonthGroup.key) {
					appendGroupSummary(currentMonthGroup)
					currentMonthGroup = null
				}

				if (!currentYearGroup) {
					currentYearGroup = {
						key: yearKey,
						level: 'year',
						label: this.formatYearGroup(date),
						periodLabel: this.formatYearGroup(date),
						dateFrom: new Date(date.getFullYear(), 0, 1).getTime() / 1000,
						dateTo: new Date(date.getFullYear() + 1, 0, 1).getTime() / 1000 - 1
					}
				}

				if (!currentMonthGroup) {
					currentMonthGroup = {
						key: monthKey,
						level: 'month',
						label: this.formatMonthGroup(date),
						periodLabel: formatCalendarLabel(date, { month: 'long', year: 'numeric' }),
						dateFrom: new Date(date.getFullYear(), date.getMonth(), 1).getTime() / 1000,
						dateTo: new Date(date.getFullYear(), date.getMonth() + 1, 1).getTime() / 1000 - 1
					}
					rows.push({ type: 'month-heading', key: `heading-${monthKey}`, label: currentMonthGroup.periodLabel })
				}

				appendEntry(entry)
			})

			appendGroupSummary(currentMonthGroup)
			appendGroupSummary(currentYearGroup)

			return rows
		}
	},
	methods: {
		updateAreaLayout() {
			this.desktopLayout = window.matchMedia('(min-width: 769px)').matches
			this.scheduleColumnLayout()
		},
		scheduleColumnLayout() {
			if (!this.columnsMounted || this.columnLayoutFrame !== null) return
			this.columnLayoutFrame = window.requestAnimationFrame(() => {
				this.columnLayoutFrame = null
				this.updateColumnWidths()
			})
		},
		updateColumnWidths() {
			const container = this.$refs.scrollContainer
			if (!container?.clientWidth) { this.columnWidths = null; return }
			if (!this.desktopLayout) {
				this.columnWidths = null
				this.updateMobileRowAlignment()
				return
			}
			const categoryHeader = container.querySelector('th.col-category')
			const partnerHeader = container.querySelector('th.col-paymentPartner')
			const context = document.createElement('canvas').getContext('2d')
			if (!categoryHeader || !partnerHeader || !context) return
			const measure = (text, element) => {
				const style = window.getComputedStyle(element)
				context.font = style.font || `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`
				return context.measureText(String(text || '')).width
			}
			const padding = element => {
				const style = window.getComputedStyle(element)
				return parseFloat(style.paddingInlineStart) + parseFloat(style.paddingInlineEnd)
			}
			const categoryCell = container.querySelector('.category-cell') || categoryHeader
			const partnerCell = container.querySelector('.paymentPartner-cell') || partnerHeader
			// Always reserve the sort glyph, so changing the active column does not shift widths.
			const sortSpace = this.sortEnabled ? 28 : 0
			const categoryMinimum = Math.ceil(measure(this.$texts.entry.tableCategory(), categoryHeader) + padding(categoryHeader) + sortSpace)
			const partnerMinimum = Math.ceil(measure(this.$texts.entry.tablePaymentPartner(), partnerHeader) + padding(partnerHeader) + sortSpace)
			const categoryWidth = Math.ceil(Math.max(categoryMinimum, ...this.entries.map(entry => measure(entry.category_name, categoryCell) + (entry.category_name && entry.category_icon ? 24 : 0) + padding(categoryCell))))
			const partnerWidth = Math.ceil(Math.max(partnerMinimum, ...this.entries.map(entry => measure(entry.paymentPartner, partnerCell) + padding(partnerCell))))
			const reserved = [...container.querySelectorAll('thead .col-date, thead .col-amount, thead .col-actions, thead .col-user')].reduce((total, header) => total + header.getBoundingClientRect().width, 0)
			const widths = fitPaymentColumns({ availableWidth: container.clientWidth - reserved, categoryWidth, partnerWidth, categoryMinimum, partnerMinimum })
			if (widths.category !== this.columnWidths?.category || widths.partner !== this.columnWidths?.partner) this.columnWidths = widths
		},
		updateMobileRowAlignment() {
			// Read every row before writing offsets; reuse the existing resize/font/data frame.
			const measurements = [...(this.$refs.scrollContainer?.querySelectorAll('.entry-row') || [])].map(row => {
				const icon = row.querySelector('.mobile-entry-icons')
				if (!icon) return null
				const center = icon.getBoundingClientRect().height / 2
				const amountHeight = row.querySelector('.amount-main')?.getBoundingClientRect().height || 0
				const actionsHeight = row.querySelector('.entry-actions')?.getBoundingClientRect().height || 0
				return { row, amount: amountHeight ? center - amountHeight / 2 : 0, actions: actionsHeight ? center - actionsHeight / 2 : 0 }
			}).filter(Boolean)
			for (const { row, amount, actions } of measurements) {
				for (const [name, offset] of [['--payment-mobile-amount-offset', amount], ['--payment-mobile-actions-offset', actions]]) {
					const value = `${offset}px`
					if (row.style.getPropertyValue(name) !== value) row.style.setProperty(name, value)
				}
			}
		},
		areaCellProps(entry) {
			return {
				name: entry.project_id ? (this.projectName(entry.project_id) || this.$texts.common.unknownArea()) : this.$texts.entry.personalAssignment(),
				color: entry.project_id ? this.projectColorResolver(entry.project_id) : '',
				withoutArea: !entry.project_id,
				statusTooltip: this.areaDetailsTooltip(entry),
				settled: this.isAreaSettled(entry),
			}
		},
		areaDetailsTooltip(entry) {
			const sharedDetails = this.sharedProjectTooltip(entry)
			if (sharedDetails) return sharedDetails
			const payer = this.actualPayerName(entry)
			return [
				payer ? this.$texts.entry.paidByPerson(payer) : '',
				this.isAreaSettled(entry) ? this.$texts.entry.amountAlreadySettled() : '',
			].filter(Boolean).join('\n')
		},
		isAreaSettled,
		emitAction(action, entry) {
			this.$emit(action, entry)
			this.actionsResetKey += 1
		},
		onRowClick(entry) {
			if (this.rowClickable) this.$emit('row-click', entry)
		},
		onRowKeydown(event, entry) {
			// Nested controls (including history) retain their own keyboard activation.
			if (!this.rowClickable || event.target !== event.currentTarget || !['Enter', ' '].includes(event.key)) return
			event.preventDefault()
			this.onRowClick(entry)
		},
		sortAria(column) {
			return this.sortEnabled && this.sortBy === column ? (this.sortDir === 'asc' ? 'ascending' : 'descending') : undefined
		},
		emitSort(column) {
			if (!this.sortEnabled) return
			this.scrollToTop()
			this.$emit('sort', column)
		},
		scrollToTop() {
			if (this.$refs.scrollContainer) {
				this.$refs.scrollContainer.scrollTop = 0
			}
		},
		formatDate(timestamp) {
			if (!timestamp) {
				return '-'
			}
			return new Date(timestamp * 1000).toLocaleDateString(getAppLocale())
		},
		formatAmount(amount) {
			return formatMoney(amount, this.currency)
		},
		formatSignedAmount(amount) {
			return formatSignedMoney(amount, this.currency)
		},
		displayAmount(entry) {
			return this.isProjectMode ? entry.amount : this.amountResolver(entry)
		},
		dateFromTimestamp(timestamp) {
			if (!timestamp) {
				return null
			}

			const date = new Date(timestamp * 1000)
			return Number.isNaN(date.getTime()) ? null : date
		},
		yearGroupKey(date) {
			return `year-${date.getFullYear()}`
		},
		monthGroupKey(date) {
			return `month-${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
		},
		mobileDayGroupKey(timestamp) {
			const date = this.dateFromTimestamp(timestamp)
			if (!date) {
				return 'unknown'
			}

			return [
				date.getFullYear(),
				String(date.getMonth() + 1).padStart(2, '0'),
				String(date.getDate()).padStart(2, '0')
			].join('-')
		},
		dateGroupKeys(timestamp) {
			const date = this.dateFromTimestamp(timestamp)
			if (!date) {
				return null
			}

			return [this.yearGroupKey(date), this.monthGroupKey(date)]
		},
		formatYearGroup(date) {
			return date.toLocaleDateString(getAppLocale(), { year: 'numeric' })
		},
		formatMonthGroup(date) {
			return formatCalendarLabel(date, { month: 'long' })
		},
		formatMobileDayGroup(timestamp) {
			const date = this.dateFromTimestamp(timestamp)
			if (!date) {
				return this.$texts.common.unknownDate()
			}

			const today = new Date()
			const dateStart = new Date(date.getFullYear(), date.getMonth(), date.getDate())
			const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate())
			const dayDifference = Math.round((dateStart.getTime() - todayStart.getTime()) / 86400000)

			if (dayDifference >= -1 && dayDifference <= 1) {
				const relativeLabel = new Intl.RelativeTimeFormat(getAppLanguage(), { numeric: 'auto' }).format(dayDifference, 'day')
				return relativeLabel.charAt(0).toLocaleUpperCase() + relativeLabel.slice(1)
			}

			return formatCalendarLabel(date, {
				weekday: 'long',
				day: '2-digit',
				month: 'short',
				...(date.getFullYear() !== today.getFullYear() ? { year: 'numeric' } : {})
			})
		},
		onGroupReportKeydown(event, row) {
			if (event.target !== event.currentTarget || !['Enter', ' '].includes(event.key) || !this.groupReportResolver?.(row)) return
			event.preventDefault()
			this.openGroupReport(row)
		},
		openGroupReport(row) {
			const location = this.groupReportResolver?.(row)
			if (location) this.$router.push(location)
		},
		emptyGroupSummary() {
			return {
				income: 0,
				expense: 0,
				balance: 0,
				count: 0
			}
		},
		normalizeGroupSummary(summary) {
			const fallback = this.emptyGroupSummary()
			return {
				income: Number.isFinite(Number(summary?.income)) ? Number(summary.income) : fallback.income,
				expense: Number.isFinite(Number(summary?.expense)) ? Number(summary.expense) : fallback.expense,
				balance: Number.isFinite(Number(summary?.balance)) ? Number(summary.balance) : fallback.balance,
				count: Number.isFinite(Number(summary?.count)) ? Number(summary.count) : fallback.count
			}
		},
		groupAmountClass(summary) {
			return {
				'is-positive': summary.balance > 0,
				'is-negative': summary.balance < 0,
				'is-neutral': summary.balance === 0
			}
		},
		groupSummaryTooltip(summary) {
			return [
				this.$texts.entry.groupBalance(this.formatSignedAmount(summary.balance)),
				this.$texts.entry.groupIncome(this.formatSignedAmount(summary.income)),
				this.$texts.entry.groupExpenses(this.formatSignedAmount(-summary.expense)),
				this.$texts.entry.groupPaymentCount(summary.count)
			].join('\n')
		},
		entryRowAccent(entry) {
			if (this.$enableProjects !== false && entry.project_id) return this.projectColorResolver(entry.project_id) || 'var(--cobudget-primary)'
			return 'transparent'
		},
		entryRowClasses(entry) {
			return {
				'is-selected': this.isEntrySelected(entry),
				'is-highlight-review': this.enableReviewPayments && !!entry.needs_review,
				'is-highlight-important': !(this.enableReviewPayments && !!entry.needs_review) && this.enableImportantPayments && !!entry.is_important,
				'is-highlight-tax': !(this.enableReviewPayments && !!entry.needs_review) && !(this.enableImportantPayments && !!entry.is_important) && this.enableTaxRelevant && !!entry.is_tax_relevant
			}
		},
		isEntrySelected(entry) {
			return this.selectedEntryId !== null
				&& String(entry?.id ?? '') === String(this.selectedEntryId);
		},
		isOpenSharedPayment(entry) {
			return !this.isProjectMode && !!entry.is_locked && !!entry.source_entry_id
				&& !!entry.project_id && !this.isAreaSettled(entry)
		},
		amountTooltip(entry) {
			if (this.isProjectMode) {
				return ''
			}
			if (this.isOpenSharedPayment(entry)) {
				const areaName = entry.project_name || this.projectName(entry.project_id)
				const totalCents = entry.source_amount_cents
				return [
					this.$texts.entry.sharedPayment(),
					areaName ? this.$texts.entry.paymentArea(areaName) : '',
					totalCents != null && Number.isFinite(Number(totalCents))
						? this.$texts.entry.totalAmount(this.formatAmount(Number(totalCents) / 100)) : '',
					this.$texts.entry.yourShare(this.formatAmount(this.displayAmount(entry))),
					this.$texts.entry.amountNotSettled(),
				].filter(Boolean).join('\n')
			}
			return this.$texts.entry.totalAmount(this.formatAmount(entry.amount))
		},
		hasAreaAssignment(entry) {
			return !this.isProjectMode && this.$enableProjects !== false && !!entry.project_id
		},
		rowAreaTooltip(entry) {
			const area = this.areaCellProps(entry)
			return [area.name, area.statusTooltip].filter(Boolean).join('\n')
		},
		actualPayerId(entry) { return entry.paid_by_user_id || entry.user_id || '' },
		descriptionTooltip(entry) {
			return entry.description || null
		},
		sharedProjectTooltip(entry) {
			if (this.isProjectMode || !entry.project_id || !this.isSharedProjectResolver(entry.project_id)) {
				return ''
			}
			const paidByName = this.actualPayerName(entry)
			const statusText = this.isAreaSettled(entry)
				? this.$texts.entry.amountAlreadySettled()
				: this.$texts.entry.amountNotSettled()

			return paidByName ? `${this.$texts.entry.paidByPerson(paidByName)}\n${statusText}` : statusText
		},
		actualPayerName(entry) {
			if (entry.paid_by_display_name) {
				return entry.paid_by_display_name
			}
			if (entry.paid_by_user_id) {
				return this.memberName(entry.paid_by_user_id)
			}
			return entry.user_display_name || this.memberName(entry.user_id)
		},
		projectName(projectId) {
			if (!projectId) {
				return ''
			}
			return this.projectNameResolver(projectId)
		},
		memberName(userId) {
			return this.memberNameResolver(userId)
		},
		canEditEntry(entry) {
			return !entry.is_locked || !!(entry.editable_entry_id || entry.source_entry_id)
		},
		canDuplicateEntry(entry) {
			return !entry.is_locked || !!(entry.editable_entry_id || entry.source_entry_id)
		},
		canDeleteEntry(entry) {
			return entry.can_delete !== false
		},
		canOpenEntryForm(entry) {
			return this.rowClickable && this.canActOnEntry(entry) && this.canEditEntry(entry)
				&& !(this.editOnly && entry.is_locked && entry.can_delete === false)
		},
		canActOnEntry(entry) {
			return this.actionsEnabled
				&& (!entry.is_settled || (this.editOnly && !this.isProjectMode))
				&& (this.canEditEntry(entry) || (!this.editOnly && (this.canDuplicateEntry(entry) || this.canDeleteEntry(entry))))
		}
	}
}
</script>

<style scoped>
.entry-table-container {
	--payment-amount-inset: 10px;
	--payment-table-hover: var(--color-background-hover, var(--cobudget-surface-muted));
	--payment-table-separator: var(--cobudget-table-separator);
	container: payment-table / inline-size;
	min-width: 0;
	overflow-x: auto;
	background: var(--cobudget-surface, #fff);
}

.payment-table-caption { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }

.data-table {
	width: 100%;
	min-width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	border: 0;
	border-radius: 0;
	table-layout: fixed;
}

.data-table.archived {
	opacity: 0.6;
}

.data-table th {
	box-sizing: border-box;
	min-width: 0;
	height: var(--cobudget-payment-table-header-height);
	padding: var(--cobudget-table-payment-padding);
	overflow: hidden;
	border-bottom: 1px solid var(--payment-table-separator);
	color: var(--cobudget-text-muted);
	font-size: var(--cobudget-font-base);
	font-weight: 400;
	letter-spacing: normal;
	text-align: left;
	text-overflow: ellipsis;
	white-space: nowrap;
	background: var(--cobudget-surface);
}

.data-table th[aria-sort] { color: var(--cobudget-text); }

th.col-date {
	width: 114px;
	white-space: normal;
	word-wrap: break-word;
	line-height: 1.3;
}

th.col-actions {
	width: 44px;
	text-align: center;
}

th.col-user {
	width: 44px;
	padding-inline: 6px;
	text-align: center;
}

th.col-desc,
th.col-category {
	width: auto;
}

th.col-amount {
	width: 145px;
	padding-inline-end: var(--payment-amount-inset);
	text-align: right;
}

th.col-paymentPartner {
	width: auto;
}

.data-table td {
	padding: var(--cobudget-table-payment-padding);
	border-bottom: 1px solid var(--payment-table-separator);
	vertical-align: middle;
	font-size: var(--cobudget-font-base);
	color: var(--cobudget-text);
}

@media (min-width: 769px) {
	.entry-row { height: var(--cobudget-payment-table-row-height); }
	.data-table tr.date-group-row--month td {
		border-top: 1px solid var(--color-border-maxcontrast, var(--cobudget-border-strong));
		border-bottom: 0;
	}
	.data-table tr > :first-child {
		padding-inline-start: var(--cobudget-content-inset-start, var(--cobudget-content-gap));
	}
	.entry-row > td:first-child {
		position: relative;
		overflow: visible;
		border-inline-start: 5px solid var(--payment-row-accent);
		padding-inline-start: calc(var(--cobudget-content-inset-start, var(--cobudget-content-gap)) - 5px);
	}
	.entry-area-marker {
		position: absolute;
		inset-inline-start: -5px;
		inset-block: 0;
		width: 5px;
	}
	.entry-area-marker :deep(.table-tooltip-trigger),
	.entry-area-marker-hit {
		display: block;
		width: 5px;
		height: 100%;
	}
}

/* Replace the last payment separator with the monthly closing line. */
.data-table tr.entry-row:has(+ .date-group-row--month),
.data-table tr.entry-row:has(+ .date-group-row--month) td {
	border-bottom: 0;
}

.data-table tbody tr.clickable-row td {
	cursor: pointer;
}

.date-group-row.is-report-link,
.date-group-row.is-report-link td { cursor: pointer; }
.date-group-row.is-report-link:hover {
	background: color-mix(in srgb, var(--cobudget-text) 4%, var(--cobudget-surface));
}

.date-group-row {
	background: var(--cobudget-surface);
	cursor: default;
}

.date-group-row td {
	padding-block: 3px;
	font-size: var(--cobudget-font-caption);
}

.date-group-row--year { background: var(--cobudget-surface); }
.date-group-row--year td {
	border-top: 1px solid var(--payment-table-separator);
	font-size: var(--cobudget-font-caption);
	font-weight: 600;
}

.date-group-row .date-group-label { color: var(--cobudget-text-muted); font-weight: 400; }

.date-group-amount {
	padding-inline-end: var(--payment-amount-inset) !important;
	text-align: right;
	font-weight: 600;
	white-space: nowrap;
}

.date-group-balance { font-size: inherit; }
.month-heading-row th {
	padding-block: 20px 8px;
	height: auto;
	border-bottom: 0;
	color: var(--cobudget-text);
}
.month-heading-row h3 {
	margin: 0;
	font-size: var(--cobudget-font-base);
	font-weight: 600;
}

.date-group-amount.is-positive {
	color: var(--cobudget-success);
}

.date-group-amount.is-negative {
	color: var(--cobudget-error);
}

.date-group-amount.is-neutral {
	color: var(--cobudget-text-muted, #666);
}

.date-group-actions {
	padding: 0 !important;
}

.mobile-date-group-row {
	display: none;
}

.clickable-row {
	cursor: pointer;
	transition: background-color var(--cobudget-hover-duration) linear;
}

.clickable-row:hover {
	background: var(--payment-table-hover);
}

.entry-row.is-highlight-review {
	background: var(--cobudget-error-light);
	color: var(--cobudget-text);
}

.entry-row.is-highlight-review td,
.entry-row.is-highlight-important td,
.entry-row.is-highlight-tax td {
	color: var(--cobudget-text);
}

.entry-row.is-highlight-review :deep(.main-title),
.entry-row.is-highlight-important :deep(.main-title),
.entry-row.is-highlight-tax :deep(.main-title),
.entry-row.is-highlight-review :deep(.mobile-date),
.entry-row.is-highlight-important :deep(.mobile-date),
.entry-row.is-highlight-tax :deep(.mobile-date) {
	color: var(--cobudget-text);
}

.clickable-row.is-highlight-review:hover {
	background: var(--cobudget-error-soft);
}

.entry-row.is-highlight-important {
	background: var(--cobudget-warning-light);
	color: var(--cobudget-text);
}

.clickable-row.is-highlight-important:hover {
	background: var(--cobudget-warning-light);
}

.entry-row.is-highlight-tax {
	background: var(--cobudget-tax-light);
	color: var(--cobudget-text);
}

.clickable-row.is-highlight-tax:hover {
	background: var(--cobudget-tax-light);
}

.entry-row.is-selected,
.entry-row.is-selected:hover {
	background: var(--cobudget-table-row-selected) !important;
}

@media (min-width: 769px) {
	.entry-row.is-selected td:first-child {
		box-shadow: inset 3px 0 0 var(--color-primary-element);
	}
	.entry-row.is-selected:dir(rtl) td:first-child {
		box-shadow: inset -3px 0 0 var(--color-primary-element);
	}
}

.date-group-row.is-report-link:focus-visible,
.clickable-row:focus-visible {
	outline: 2px solid var(--color-main-text);
	outline-offset: -2px;
}

.sortable {
	cursor: pointer;
	user-select: none;
}

.sortable,
.sortable *,
.sortable .column-heading,
.sortable .column-heading-label,
.sortable .sort-icon {
	cursor: pointer;
}

.sortable:focus-visible {
	outline: none;
}

.sortable .column-heading {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline, 4px);
	box-sizing: border-box;
	width: calc(100% + 16px);
	min-height: var(--cobudget-control-height-compact, 34px);
	margin-inline: -8px;
	padding: 4px 8px;
	border-radius: var(--border-radius-element, 8px);
	font-weight: normal;
	user-select: none;
	transition: background-color var(--cobudget-hover-duration, 100ms) ease, color var(--cobudget-hover-duration, 100ms) ease;
}

th.col-amount.sortable .column-heading {
	width: calc(100% + 14px);
	margin-inline-start: -8px;
	margin-inline-end: -6px;
	justify-content: flex-end;
}

.sortable:hover .column-heading,
.sortable:focus-visible .column-heading {
	background-color: var(--color-background-hover);
	background-color: color-mix(in srgb, var(--color-primary-element) 8%, transparent);
	color: var(--color-text-maxcontrast, var(--cobudget-text-muted));
	font-weight: normal;
}

.sortable:focus-visible .column-heading {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.sortable:active .column-heading {
	background-color: color-mix(in srgb, var(--color-primary-element) 14%, transparent);
}

.column-heading {
	display: inline-flex;
	align-items: center;
	gap: var(--default-grid-baseline, 4px);
	max-width: 100%;
	vertical-align: middle;
}
.column-heading-label {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	cursor: pointer;
	user-select: none;
}
.sort-icon {
	display: inline-flex;
	align-items: center;
	flex-shrink: 0;
	color: inherit;
	cursor: pointer;
}

.date-cell {
	overflow: hidden;
	color: var(--cobudget-text-muted, #888);
	text-overflow: ellipsis;
	white-space: nowrap;
}
.date-cell-text { display: block; overflow: hidden; text-overflow: ellipsis; }

.desc-cell {
	min-width: 0;
	overflow: hidden;
	font-weight: 500;
}

.category-content,
.paid-by {
	display: flex;
	align-items: center;
	min-width: 0;
}

.category-content {
	gap: 4px;
	overflow: hidden;
	white-space: nowrap;
}

.category-content :deep(.material-design-icon) {
	flex: 0 0 auto;
}

.cell-text,
.paymentPartner-cell {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.user-cell {
	padding-inline: 6px !important;
	overflow: hidden;
}

.paid-by {
	justify-content: center;
}

.visually-hidden {
	position: absolute !important;
	width: 1px !important;
	height: 1px !important;
	padding: 0 !important;
	margin: -1px !important;
	overflow: hidden !important;
	clip: rect(0, 0, 0, 0) !important;
	white-space: nowrap !important;
	border: 0 !important;
}

@media (min-width: 769px) {
	.desc-cell :deep(.entry-description-cell),
	.desc-cell :deep(.desc-text) {
		min-width: 0;
		max-width: 100%;
	}

	.desc-cell :deep(.desc-text) {
		flex-wrap: nowrap;
		overflow: hidden;
	}

	.desc-cell :deep(.entry-badge),
	.desc-cell :deep(.main-title) {
		min-width: 0;
		max-width: 100%;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.amount-cell {
	padding-right: 0 !important;
	overflow: hidden;
	text-align: right;
	font-weight: inherit;
	white-space: nowrap;
}

.amount-cell :deep(.amount-wrapper) {
	min-width: 0;
	overflow: hidden;
}

.amount-cell :deep(.amount-text),
.amount-cell :deep(.shared-icon) {
	flex: 0 0 auto;
}

.actions-cell {
	padding-right: 0 !important;
	padding-left: 0 !important;
	text-align: center;
}

.entry-actions {
	display: inline-flex;
	justify-content: center;
}

@media (max-width: 768px) {
	.entry-table-container {
		--payment-mobile-row-bleed: var(--cobudget-payment-row-bleed, var(--cobudget-mobile-content-padding, calc(var(--default-grid-baseline, 4px) * 2.5)));
		--payment-mobile-group-background: color-mix(in srgb, var(--cobudget-surface-muted) 65%, var(--cobudget-surface));
		border: none;
		background: transparent;
		box-shadow: none;
		overflow: visible;
	}

	.data-table {
		min-width: 100% !important;
		border: none;
		border-radius: 0;
		background: transparent;
	}

	.data-table thead {
		display: none;
	}

	.data-table,
	.data-table tbody,
	.data-table tr,
	.data-table td {
		display: block;
		width: 100%;
	}

	.data-table tr.entry-row {
		display: grid;
		width: calc(100% + var(--payment-mobile-row-bleed) * 2);
		grid-template-columns: minmax(0, 1fr) auto auto;
		grid-template-areas: "desc amount actions";
		align-items: center;
		gap: var(--default-grid-baseline, 4px) calc(var(--default-grid-baseline, 4px) * 2);
		min-height: calc(var(--default-clickable-area, 44px) + calc(var(--default-grid-baseline, 4px) * 4));
		margin: 0;
		margin-inline: calc(0px - var(--payment-mobile-row-bleed));
		padding: calc(var(--default-grid-baseline, 4px) * 2.5) var(--payment-amount-inset);
		border: none;
		border-bottom: 1px solid var(--cobudget-border, var(--color-border));
		border-inline-start: 5px solid var(--payment-row-accent);
		padding-inline-start: calc(var(--payment-amount-inset) - 5px);
		box-sizing: border-box;
		border-radius: 0;
		background: var(--payment-mobile-group-background);
		box-shadow: none;
	}

	.data-table--without-actions tr.entry-row { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "desc amount"; }

	/* Reserve the separator's height even where the closing row supplies its line. */
	.data-table tr.entry-row:has(+ .date-group-row--month) { border-bottom: 1px solid transparent; }

	.data-table tr.entry-row.clickable-row:hover { background: var(--payment-table-hover); }
	.data-table tr.entry-row.is-highlight-review { background: var(--cobudget-error-light); }
	.data-table tr.entry-row.is-highlight-review:hover { background: var(--cobudget-error-soft); }
	.data-table tr.entry-row.is-highlight-important,
	.data-table tr.entry-row.is-highlight-important:hover { background: var(--cobudget-warning-light); }
	.data-table tr.entry-row.is-highlight-tax,
	.data-table tr.entry-row.is-highlight-tax:hover { background: var(--cobudget-tax-light); }

	.data-table tr.date-group-row {
		display: grid;
		width: calc(100% + var(--payment-mobile-row-bleed) * 2);
		margin-inline: calc(0px - var(--payment-mobile-row-bleed));
		box-sizing: border-box;
		grid-template-columns: minmax(0, 1fr) auto auto;
		align-items: center;
		min-height: var(--cobudget-mobile-touch-size, 44px);
		gap: 8px;
		padding: 0 var(--payment-amount-inset);
		border-bottom: 1px solid var(--payment-table-separator);
		background: var(--cobudget-surface);
	}
	.data-table tr.date-group-row--year {
		border-top: 1px solid var(--payment-table-separator);
		background: var(--cobudget-surface);
	}
	.data-table tr.date-group-row--month {
		padding-block: 0;
		border-top: 1px solid var(--color-border-maxcontrast, var(--cobudget-border-strong));
		border-bottom: 0;
		background: var(--cobudget-surface);
	}
	.data-table--without-actions tr.date-group-row { grid-template-columns: minmax(0, 1fr) auto; }

	.date-group-row .date-group-label,
	.date-group-row .date-group-amount {
		min-width: 0;
		width: auto;
		border: none;
		padding-block: 0;
	}
	.date-group-row .date-group-amount { text-align: right; padding-inline-end: 0 !important; }
	.date-group-row .date-group-actions {
		display: flex;
		justify-content: flex-end;
		width: var(--default-clickable-area, 34px);
	}
	.month-heading-row th { display: block; padding: 20px 0 8px; }

	.data-table tr.mobile-date-group-row {
		display: block;
		margin: calc(var(--default-grid-baseline, 4px) * 4) 0 0;
		padding: 0;
		border: none;
		background: transparent;
		box-shadow: none;
	}

	.data-table tr.mobile-date-group-row:first-child {
		margin-block-start: 0;
	}

	.data-table td {
		display: block;
		padding: 0;
		border: none;
		text-align: left;
	}

	.data-table td::before {
		display: none !important;
	}

	.mobile-date-group-row .mobile-date-group-label {
		display: block !important;
		width: 100% !important;
		padding: 0 0 calc(var(--default-grid-baseline, 4px) * 1.5) !important;
		border: none;
		color: var(--cobudget-text-muted, var(--color-text-maxcontrast));
		font-size: var(--cobudget-font-compact, 12px);
		font-weight: var(--cobudget-font-weight-action, 700);
		letter-spacing: 0.02em;
		line-height: 1.3;
		text-transform: none;
	}

	.date-group-user { display: none !important; }

	.date-cell,
	.category-cell,
	.paymentPartner-cell,
	.user-cell {
		display: none !important;
	}

	.desc-cell {
		display: block !important;
		grid-area: desc;
		align-self: start;
		min-width: 0;
		/* The label count extends above its chip; text children handle truncation. */
		overflow: visible;
	}

	.amount-cell {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		grid-area: amount;
		align-self: start;
		margin-block-start: var(--payment-mobile-amount-offset, 0px);
		justify-content: flex-end;
		width: auto !important;
		min-width: 0;
		text-align: right;
	}

	.actions-cell {
		display: flex;
		grid-area: actions;
		align-self: start;
		margin-block-start: var(--payment-mobile-actions-offset, 0px);
		align-items: center;
		justify-content: flex-end;
		justify-self: end;
		width: auto !important;
	}
}
.sortable .column-heading { transition: background-color var(--cobudget-hover-duration) linear, color var(--cobudget-hover-duration) linear; }
.entry-row:is(.is-selected, :focus-within), .sortable:focus-within .column-heading { transition: none; }
</style>
