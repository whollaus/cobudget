<template>
	<div class="entry-table-container">
		<table class="data-table" :class="{ archived }">
			<thead>
				<tr>
					<th class="col-date sortable" @click="emitSort('date')">
						{{ dateLabel }}
						<span v-if="sortBy === 'date'" class="sort-icon">{{ sortIcon }}</span>
					</th>
					<th v-if="showProjectPayer" class="col-user">{{ $texts.entry.tablePaidBy() }}</th>
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
						v-if="row.type === 'group'"
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
						@click="$emit('row-click', row.entry)">
						<td :data-label="dateLabel" class="date-cell">{{ formatDate(row.entry.date) }}</td>
						<td v-if="showProjectPayer" :data-label="$texts.entry.tablePaidBy()" class="user-cell">
							<div class="paid-by">
								<span v-if="row.entry.user_is_former" class="former-avatar" aria-hidden="true">{{ initials(row.entry.user_display_name) }}</span>
								<NcAvatar v-else :user="row.entry.user_id" :display-name="memberName(row.entry.user_id)" :size="24" />
								{{ row.entry.user_display_name || memberName(row.entry.user_id) }}
							</div>
						</td>
						<td :data-label="$texts.entry.tableDescription()" class="desc-cell">
						<EntryDescriptionCell
							:entry="row.entry"
							:date-text="formatDate(row.entry.date)"
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
					<td :data-label="$texts.entry.tableCategory()" class="category-cell">
						<div v-if="row.entry.category_name" class="category-content">
							<CategoryIcon v-if="row.entry.category_icon" :icon="row.entry.category_icon" :size="16" />
							{{ row.entry.category_name }}
						</div>
					</td>
					<td :data-label="$texts.entry.tablePaymentPartner()" class="paymentPartner-cell">{{ row.entry.paymentPartner }}</td>
					<td
						:data-label="$texts.entry.tableAmount(currency)"
						class="amount-cell"
						:class="{ 'bg-income': row.entry.type === 'income', 'bg-expense': row.entry.type === 'expense' }">
						<EntryAmountCell
							:entry="row.entry"
							:amount="displayAmount(row.entry)"
							:currency="currency"
							:amount-tooltip="amountTooltip(row.entry)"
							:shared-project-tooltip="sharedProjectTooltip(row.entry)"
							:show-settled-icon="isProjectMode && !!row.entry.is_settled"
							@history="$emit('history', $event)" />
					</td>
					<td class="actions-cell" @click.stop>
						<NcActions v-if="canActOnEntry(row.entry)" :key="`${actionsResetKey}-${row.entry.id}`" class="entry-actions">
							<NcActionButton :close-after-click="true" icon="icon-rename" @click="emitAction('edit', row.entry)">
								{{ $texts.entry.editPayment() }}
							</NcActionButton>
							<NcActionButton v-if="!row.entry.is_locked" :close-after-click="true" icon="icon-add" @click="emitAction('duplicate', row.entry)">
								{{ $texts.entry.copyPayment() }}
							</NcActionButton>
							<NcActionButton v-if="row.entry.can_delete !== false" :close-after-click="true" icon="icon-delete" @click="emitAction('delete', row.entry)">
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
					rows.push({
						type: 'entry',
						key: `entry-${entry.id}`,
						entry
					})
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

				rows.push({
					type: 'entry',
					key: `entry-${entry.id}`,
					entry
				})
			})

			appendGroupSummary(currentMonthGroup)
			appendGroupSummary(currentYearGroup)

			return rows
		}
	},
	methods: {
		emitAction(action, entry) {
			this.$emit(action, entry)
			this.actionsResetKey += 1
		},
		emitSort(column) {
			this.$emit('sort', column)
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
				'is-highlight-review': this.enableReviewPayments && !!entry.needs_review,
				'is-highlight-important': !(this.enableReviewPayments && !!entry.needs_review) && this.enableImportantPayments && !!entry.is_important,
				'is-highlight-tax': !(this.enableReviewPayments && !!entry.needs_review) && !(this.enableImportantPayments && !!entry.is_important) && this.enableTaxRelevant && !!entry.is_tax_relevant
			}
		},
		amountTooltip(entry) {
			if (this.isProjectMode) {
				return ''
			}
			return this.$texts.entry.totalAmount(this.formatAmount(entry.amount))
		},
		showProjectChip(entry) {
			return !!(entry.project_id && this.projectName(entry.project_id))
		},
		sharedProjectTooltip(entry) {
			if (this.isProjectMode || !entry.project_id || !this.isSharedProjectResolver(entry.project_id)) {
				return ''
			}
			const paidByName = this.actualPayerName(entry)
			const statusText = entry.is_settled
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
		canActOnEntry(entry) {
			return this.actionsEnabled
				&& !entry.is_settled
				&& (!entry.is_locked || entry.can_delete !== false)
		}
	}
}
</script>

<style scoped>
.entry-table-container {
	overflow-x: auto;
	background: var(--cobudget-surface, #fff);
}

.data-table {
	width: 100%;
	min-width: 800px;
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
	padding: 4px 10px;
	border-bottom: 1px solid var(--cobudget-border, #ddd);
	color: var(--cobudget-text-muted, #888);
	font-size: var(--cobudget-font-sm);
	letter-spacing: 0.5px;
	text-align: left;
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
	width: 140px;
	white-space: normal;
	word-wrap: break-word;
	line-height: 1.3;
}

th.col-actions {
	width: 50px;
	text-align: center;
}

th.col-user {
	width: 200px;
}

th.col-category {
	width: 200px;
}

th.col-paymentPartner {
	width: 200px;
}

th.col-amount {
	width: 180px;
	text-align: right;
}

th.col-desc {
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
	color: var(--cobudget-text-muted, #888);
	white-space: nowrap;
}

.desc-cell {
	font-weight: 500;
}

.category-content,
.paid-by {
	display: flex;
	align-items: center;
	min-width: 0;
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
	text-align: right;
	font-weight: 600;
	white-space: nowrap;
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
	}

	.data-table {
		min-width: 100% !important;
		border: none;
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

	.data-table tr {
		display: grid;
		grid-template-columns: 1fr auto;
		grid-template-areas:
			"desc amount"
			"desc actions";
		gap: 8px 12px;
		margin-bottom: 12px;
		padding: 12px;
		border: 1px solid var(--cobudget-border, #ddd);
		border-radius: 8px;
		background: var(--cobudget-surface, #fff);
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
	}

	.data-table tr.date-group-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin: 12px 0 8px;
		padding: 8px 12px;
		border: 1px solid var(--cobudget-border, #ddd);
		border-radius: 8px;
		background-color: var(--cobudget-surface, #fff) !important;
		box-shadow: none;
	}

	.data-table tr.date-group-row--year {

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

	.date-group-row .date-group-label,
	.date-group-row .date-group-amount {
		display: block !important;
		width: auto !important;
		border: none;
	}

	.date-group-row .date-group-label {
		padding: 0 !important;
	}

	.date-group-row .date-group-actions {
		display: none !important;
	}

	.date-cell,
	.category-cell,
	.paymentPartner-cell,
	.user-cell {
		display: none !important;
	}

	.desc-cell {
		display: flex !important;
		grid-area: desc;
		flex-direction: column;
		gap: 4px;
	}

	.amount-cell {
		display: flex;
		grid-area: amount;
		align-items: flex-start;
		justify-content: flex-end;
		width: auto !important;
		text-align: right;
	}

	.actions-cell {
		display: flex;
		grid-area: actions;
		align-items: flex-end;
		justify-content: flex-end;
		justify-self: end;
		width: auto !important;
	}
}
</style>
