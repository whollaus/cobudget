<template>
	<div ref="scrollContainer" class="entry-table-container">
		<table class="data-table" :class="{ archived }">
			<thead>
				<tr>
					<th v-if="showProjectPayer" class="col-user">
						<span class="visually-hidden">{{ $texts.entry.tablePaidBy() }}</span>
					</th>
					<th class="col-date sortable" @click="emitSort('date')">
						{{ dateLabel }}
						<span v-if="sortBy === 'date'" class="sort-icon">{{ sortIcon }}</span>
					</th>
					<th class="col-desc sortable" @click="emitSort('description')">
						{{ $texts.entry.tableDescription() }}
						<span v-if="sortBy === 'description'" class="sort-icon">{{ sortIcon }}</span>
					</th>
					<th class="col-category sortable" @click="emitSort('category_name')">
						{{ $texts.entry.tableCategory() }}
						<span v-if="sortBy === 'category_name'" class="sort-icon">{{ sortIcon }}</span>
					</th>
					<th class="col-paymentPartner sortable" @click="emitSort('paymentPartner')">
						{{ $texts.entry.tablePaymentPartner() }}
						<span v-if="sortBy === 'paymentPartner'" class="sort-icon">{{ sortIcon }}</span>
					</th>
					<th class="col-amount sortable" @click="emitSort('amount')">
						{{ $texts.entry.tableAmount(currency) }}
						<span v-if="sortBy === 'amount'" class="sort-icon">{{ sortIcon }}</span>
					</th>
					<th class="col-actions"></th>
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
					<tr
						v-else-if="row.type === 'group'"
						class="date-group-row"
						:class="`date-group-row--${row.level}`">
						<td :colspan="groupLabelColspan" class="date-group-label">
							{{ row.label }}
						</td>
						<td
							class="date-group-amount"
							:class="groupAmountClass(row.summary)">
							<TableTooltip :text="groupSummaryTooltip(row.summary)">
								<span>{{ formatSignedAmount(row.summary.balance) }}</span>
							</TableTooltip>
						</td>
						<td class="date-group-actions"></td>
					</tr>
					<tr
						v-else
						class="clickable-row"
						:class="entryRowClasses(row.entry)"
						:aria-selected="isEntrySelected(row.entry) ? 'true' : 'false'"
						tabindex="0"
						@click="$emit('row-click', row.entry)"
						@keydown.enter.prevent="$emit('row-click', row.entry)"
						@keydown.space.prevent="$emit('row-click', row.entry)">
						<td
							v-if="showProjectPayer"
							:data-label="$texts.entry.tablePaidBy()"
							:aria-label="$texts.entry.paidByPerson(projectPayerName(row.entry))"
							:title="projectPayerName(row.entry)"
							class="user-cell">
							<div class="paid-by">
								<span v-if="row.entry.user_is_former" class="former-avatar" aria-hidden="true">{{ initials(row.entry.user_display_name) }}</span>
								<NcAvatar v-else :user="row.entry.user_id" :display-name="projectPayerName(row.entry)" :size="24" />
							</div>
						</td>
						<td :data-label="dateLabel" class="date-cell">{{ formatDate(row.entry.date) }}</td>
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
							:show-project-chip="showProjectChip(row.entry)"
							:project-name="projectName(row.entry.project_id)"
							:project-style="projectStyle(row.entry.project_id)"
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
						class="amount-cell"
						:class="{ 'bg-income': row.entry.type === 'income', 'bg-expense': row.entry.type === 'expense' }">
						<EntryAmountCell
							:entry="row.entry"
							:amount="displayAmount(row.entry)"
							:currency="currency"
							:amount-tooltip="amountTooltip(row.entry)"
							:area-settled="isAreaSettled(row.entry)"
							:shared-project-tooltip="sharedProjectTooltip(row.entry)"
							:show-settled-icon="isProjectMode && !!row.entry.is_settled"
							@history="$emit('history', $event)" />
					</td>
					<td class="actions-cell" @click.stop>
						<NcActions v-if="canActOnEntry(row.entry)" :key="`${actionsResetKey}-${row.entry.id}`" class="entry-actions">
							<NcActionButton v-if="canEditEntry(row.entry)" :close-after-click="true" icon="icon-rename" @click="emitAction('edit', row.entry)">
								{{ $texts.entry.editPayment() }}
							</NcActionButton>
							<NcActionButton v-if="canDuplicateEntry(row.entry)" :close-after-click="true" icon="icon-add" @click="emitAction('duplicate', row.entry)">
								{{ $texts.entry.copyPayment() }}
							</NcActionButton>
							<NcActionButton v-if="canDeleteEntry(row.entry)" :close-after-click="true" icon="icon-delete" @click="emitAction('delete', row.entry)">
								{{ $texts.entry.deletePaymentAction() }}
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
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import CategoryIcon from './CategoryIcon.vue'
import EntryAmountCell from './EntryAmountCell.vue'
import EntryDescriptionCell from './EntryDescriptionCell.vue'
import TableTooltip from './TableTooltip.vue'
import { texts } from '../l10n/texts'
import { isAreaSettled } from '../utils/areaSettlementStatus'
import { formatMoney, formatSignedMoney } from '../utils/formatMoney'

const amountResolver = entry => entry?.amount
const falseResolver = () => false
const emptyObjectResolver = () => ({})
const idResolver = value => value

export default {
	name: 'EntryTable',
	components: {
		CategoryIcon,
		EntryAmountCell,
		EntryDescriptionCell,
		NcActionButton,
		NcActions,
		NcAvatar,
		TableTooltip
	},
	props: {
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
		actionsEnabled: {
			type: Boolean,
			default: true
		},
		archived: {
			type: Boolean,
			default: false
		},
		groupByDate: {
			type: Boolean,
			default: true
		},
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
			actionsResetKey: 0
		}
	},
	computed: {
		isProjectMode() {
			return this.mode === 'project'
		},
		showProjectPayer() {
			return this.isProjectMode && this.showPaidBy
		},
		sortIcon() {
			return this.sortDir === 'asc' ? '↑' : '↓'
		},
		groupLabelColspan() {
			return this.showProjectPayer ? 5 : 4
		},
		tableColumnCount() {
			return this.showProjectPayer ? 7 : 6
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
					label: this.formatGroupTotal(group.label),
					summary: this.dateGroupSummaries[group.key] || this.emptyGroupSummary()
				})
			}

			this.entries.forEach(entry => {
				const date = this.dateFromTimestamp(entry.date)
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
						label: this.formatYearGroup(date)
					}
				}

				if (!currentMonthGroup) {
					currentMonthGroup = {
						key: monthKey,
						level: 'month',
						label: this.formatMonthGroup(date)
					}
				}

				appendEntry(entry)
			})

			appendGroupSummary(currentMonthGroup)
			appendGroupSummary(currentYearGroup)

			return rows
		}
	},
	methods: {
		isAreaSettled,
		emitAction(action, entry) {
			this.$emit(action, entry)
			this.actionsResetKey += 1
		},
		emitSort(column) {
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
			return new Date(timestamp * 1000).toLocaleDateString()
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
			return date.toLocaleDateString(undefined, { year: 'numeric' })
		},
		formatMonthGroup(date) {
			return date.toLocaleDateString(undefined, { month: 'long' })
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
				const relativeLabel = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(dayDifference, 'day')
				return relativeLabel.charAt(0).toLocaleUpperCase() + relativeLabel.slice(1)
			}

			return date.toLocaleDateString(undefined, {
				weekday: 'long',
				day: '2-digit',
				month: 'short',
				...(date.getFullYear() !== today.getFullYear() ? { year: 'numeric' } : {})
			})
		},
		formatGroupTotal(label) {
			return this.$texts.entry.groupTotal(label)
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
		amountTooltip(entry) {
			if (this.isProjectMode) {
				return ''
			}
			return this.$texts.entry.totalAmount(this.formatAmount(entry.amount))
		},
		showProjectChip(entry) {
			return !this.isProjectMode && !!(entry.project_id && this.projectName(entry.project_id))
		},
		projectPayerName(entry) {
			return entry.user_display_name || this.memberName(entry.user_id)
		},
		descriptionTooltip(entry) {
			const values = []
			if (this.showProjectChip(entry)) {
				values.push(this.projectName(entry.project_id))
			}
			if (entry.description) {
				values.push(entry.description)
			}
			return values.join(' · ') || null
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
		projectStyle(projectId) {
			if (!projectId) {
				return {}
			}
			return this.projectStyleResolver(projectId)
		},
		memberName(userId) {
			return this.memberNameResolver(userId)
		},
		initials(name) {
			return String(name || '?')
				.split(/\s+/)
				.filter(Boolean)
				.slice(0, 2)
				.map(part => part.charAt(0).toUpperCase())
				.join('') || '?'
		},
		canEditEntry(entry) {
			return !entry.is_locked || entry.can_delete !== false
		},
		canDuplicateEntry(entry) {
			return !entry.is_locked || !!(entry.editable_entry_id || entry.source_entry_id)
		},
		canDeleteEntry(entry) {
			return entry.can_delete !== false
		},
		canActOnEntry(entry) {
			return this.actionsEnabled
				&& !entry.is_settled
				&& (this.canEditEntry(entry) || this.canDuplicateEntry(entry) || this.canDeleteEntry(entry))
		}
	}
}
</script>

<style scoped>
.entry-table-container {
	min-width: 0;
	overflow-x: auto;
	background: var(--cobudget-surface, #fff);
}

.data-table {
	width: 100%;
	min-width: 720px;
	border-collapse: separate;
	border-spacing: 0;
  border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	table-layout: fixed;
}

.data-table.archived {
	opacity: 0.6;
}

.data-table th {
	box-sizing: border-box;
	min-width: 0;
	padding: 4px 10px;
	overflow: hidden;
	border-bottom: 1px solid var(--cobudget-border, #ddd);
	color: var(--cobudget-text-muted, #888);
	font-size: var(--cobudget-font-sm);
	letter-spacing: 0.5px;
	text-align: left;
	text-overflow: ellipsis;
	white-space: nowrap;
  background-color: var(--cobudget-surface-muted, #f9f9f9);
}

.data-table th:first-child {
	border-top-left-radius: calc(var(--border-radius-large, 8px) - 1px);
}

.data-table th:last-child {
	border-top-right-radius: calc(var(--border-radius-large, 8px) - 1px);
}

.data-table tbody tr:last-child td:first-child {
	border-bottom-left-radius: calc(var(--border-radius-large, 8px) - 1px);
}

.data-table tbody tr:last-child td:last-child {
	border-bottom-right-radius: calc(var(--border-radius-large, 8px) - 1px);
}

th.col-date {
	width: 108px;
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
	width: 156px;
	text-align: right;
}

th.col-paymentPartner {
	width: auto;
}

.data-table td {
	padding: 0px 5px 0px 10px;
	border-bottom: 1px solid var(--cobudget-border, #ddd);
	vertical-align: middle;
	font-size: var(--cobudget-font-sm);
	color: var(--cobudget-text, #222);
}

.data-table tbody tr:last-child td {
	border-bottom: none;
}

.data-table tbody tr.clickable-row td {
	cursor: pointer;
}

.date-group-row {
  background-color: var(--cobudget-surface-muted, #f5f9fb);
	cursor: default;
}

.date-group-row:hover {
  background-color: var(--cobudget-surface-muted, #f5f9fb);
}

.date-group-row td {
	border-bottom-color: var(--cobudget-border, #ddd);
  padding: 2px 5px 2px 10px;
}

.date-group-row--year {
  letter-spacing: 0.5px;
  text-align: left;
}

.date-group-row--year:hover {
}

.date-group-label {
	color: var(--cobudget-text, #222);
}

.date-group-row--year .date-group-label {
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
  text-align: left;
  font-weight: 600;
  color: var(--cobudget-text, #222);
}

.date-group-row--month .date-group-label {
  color: var(--cobudget-text-muted, #888);
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
  text-align: left;
}

.date-group-amount {
	padding-right: 10px !important;
	text-align: right;
	font-weight: 800;
	white-space: nowrap;
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
	transition: background-color 0.15s ease;
}

.clickable-row:hover {
	background: var(--cobudget-surface-muted, #f6f6f6);
}

.clickable-row.is-highlight-review {
	background: var(--cobudget-error-light);
	color: var(--cobudget-text);
}

.clickable-row.is-highlight-review td,
.clickable-row.is-highlight-important td,
.clickable-row.is-highlight-tax td {
	color: var(--cobudget-text);
}

.clickable-row.is-highlight-review :deep(.main-title),
.clickable-row.is-highlight-important :deep(.main-title),
.clickable-row.is-highlight-tax :deep(.main-title),
.clickable-row.is-highlight-review :deep(.mobile-date),
.clickable-row.is-highlight-important :deep(.mobile-date),
.clickable-row.is-highlight-tax :deep(.mobile-date) {
	color: var(--cobudget-text);
}

.clickable-row.is-highlight-review:hover {
	background: var(--cobudget-error-soft);
}

.clickable-row.is-highlight-important {
	background: var(--cobudget-warning-light);
	color: var(--cobudget-text);
}

.clickable-row.is-highlight-important:hover {
	background: var(--cobudget-warning-light);
}

.clickable-row.is-highlight-tax {
	background: var(--cobudget-tax-light);
	color: var(--cobudget-text);
}

.clickable-row.is-highlight-tax:hover {
	background: var(--cobudget-tax-light);
}

.clickable-row.is-selected,
.clickable-row.is-selected:hover {
	background: var(--color-primary-element-light, var(--color-background-dark)) !important;
}

@media (min-width: 769px) {
	.clickable-row.is-selected td {
		box-shadow:
			inset 0 2px 0 var(--color-primary, #0082c9),
			inset 0 -2px 0 var(--color-primary, #0082c9);
	}
}

.clickable-row:focus-visible {
	outline: 2px solid var(--color-main-text);
	outline-offset: -2px;
}

.sortable {
	cursor: pointer;
	user-select: none;
}

.sortable:hover {
	background: var(--cobudget-surface-strong, #eee);
}

.sort-icon {
	display: inline-block;
	margin-left: 4px;
	font-size: var(--cobudget-font-sm);
}

.date-cell {
	overflow: hidden;
	color: var(--cobudget-text-muted, #888);
	text-overflow: ellipsis;
	white-space: nowrap;
}

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

.former-avatar {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: var(--cobudget-surface-strong);
	color: var(--cobudget-text-muted);
	font-size: var(--cobudget-font-compact);
	font-weight: 700;
	flex: 0 0 24px;
}

.amount-cell {
	padding-right: 0 !important;
	overflow: hidden;
	text-align: right;
	font-weight: 600;
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

	.data-table tr.clickable-row {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto auto;
		grid-template-areas: "desc amount actions";
		align-items: center;
		gap: var(--default-grid-baseline, 4px) calc(var(--default-grid-baseline, 4px) * 2);
		min-height: calc(var(--default-clickable-area, 44px) + calc(var(--default-grid-baseline, 4px) * 4));
		margin: 0;
		padding: calc(var(--default-grid-baseline, 4px) * 2.5) 0;
		border: none;
		border-bottom: 1px solid var(--cobudget-border, var(--color-border));
		border-radius: 0;
		background: var(--cobudget-surface, var(--color-main-background));
		box-shadow: none;
	}

	.data-table tr.date-group-row {
		display: none;
	}

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

	.date-cell,
	.category-cell,
	.paymentPartner-cell,
	.user-cell {
		display: none !important;
	}

	.desc-cell {
		display: block !important;
		grid-area: desc;
		min-width: 0;
		overflow: hidden;
	}

	.amount-cell {
		display: flex;
		grid-area: amount;
		align-items: center;
		justify-content: flex-end;
		width: auto !important;
		min-width: 0;
		text-align: right;
	}

	.actions-cell {
		display: flex;
		grid-area: actions;
		align-items: center;
		justify-content: flex-end;
		justify-self: end;
		width: auto !important;
	}
}
</style>
