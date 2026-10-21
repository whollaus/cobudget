<template>
	<table class="annual-table" :class="{ 'has-total-row': totalRow, 'is-collapsed': collapsible && !open }">
		<caption>
			<button v-if="collapsible" type="button" class="annual-section-toggle" :aria-expanded="open" @mousedown.prevent @click="toggleSection">
				<span class="annual-table-title">{{ title }}</span>
				<ChevronDownIcon v-if="open" :size="18" /><ChevronRightIcon v-else :size="18" />
			</button>
			<span v-else class="annual-table-title">{{ title }}</span>
		</caption>
		<colgroup v-show="open"><col class="label-column"><col v-for="(column, index) in columns" :key="column" :class="isTotal(index) ? 'total-column' : 'amount-column'"></colgroup>
		<thead v-show="open">
			<tr>
				<th scope="col" :title="firstHeading" :class="{ sortable: !summary }" :tabindex="summary ? undefined : 0" :aria-sort="sortAria('title')"
					@click="toggleSort('title')" @keydown.enter.prevent="toggleSort('title')" @keydown.space.prevent="toggleSort('title')">
					{{ firstHeading }}<span v-if="!summary && sortBy === 'title'" class="sort-icon" aria-hidden="true">{{ sortIcon }}</span>
				</th>
				<th v-for="(column, index) in columns" :key="index" scope="col" :title="columnTitle(index)" :class="{ sortable: !summary && isTotal(index), 'annual-year-total': isTotal(index) }"
					:tabindex="!summary && isTotal(index) ? 0 : undefined" :aria-sort="isTotal(index) ? sortAria('amount') : undefined"
					@click="isTotal(index) && toggleSort('amount')" @keydown.enter.prevent="isTotal(index) && toggleSort('amount')" @keydown.space.prevent="isTotal(index) && toggleSort('amount')">
					{{ column }}<span v-if="!summary && isTotal(index) && sortBy === 'amount'" class="sort-icon" aria-hidden="true">{{ sortIcon }}</span>
				</th>
			</tr>
		</thead>
		<tbody v-show="open">
			<tr v-if="!rows.length"><td :colspan="columns.length + 1" class="annual-empty-cell"><div class="annual-empty-message">{{ emptyMessage || $texts.common.noFilteredEntries() }}</div></td></tr>
			<template v-for="row in sortedRows" :key="row.key">
				<tr :class="{ 'annual-total': summary && row.type === 'all', 'annual-parent': row.children?.length }">
					<th scope="row">
						<div class="annual-row-label">
							<NcButton v-if="row.children?.length" variant="tertiary" size="small" class="annual-category-toggle" :title="row.label" :aria-label="row.label" :aria-expanded="Boolean(expanded[row.key])" @click="toggle(row.key)">
								<template #icon><ChevronDownIcon v-if="expanded[row.key]" :size="18" /><ChevronRightIcon v-else :size="18" /></template>
							</NcButton>
							<router-link :to="paymentRoute(row, null)" :title="row.label">{{ row.label }}</router-link>
						</div>
					</th>
					<td v-for="(amount, index) in [...row.months, row.total]" :key="index" :class="{ 'annual-year-total': isTotal(index), 'negative-balance': row.type === 'all' && amount < 0, 'annual-month-empty': isMonthEmpty(row, index, amount) }">
						<NcButton variant="tertiary" size="small" class="annual-amount" :title="formatAmount(amount)" :aria-label="linkLabel(row.label, index, amount)" @click="openDetails(row, index)">{{ cellAmount(row, index, amount) }}</NcButton>
					</td>
				</tr>
				<template v-if="expanded[row.key]">
					<tr v-for="child in row.children" :key="`${row.key}-${child.id}`" class="annual-child">
						<th scope="row"><router-link :to="paymentRoute({ ...child, type: row.type }, null)" :title="child.direct ? $texts.common.mainCategory() : child.name">{{ child.direct ? $texts.common.mainCategory() : child.name }}</router-link></th>
						<td v-for="(amount, index) in [...child.months, child.total]" :key="index" :class="{ 'annual-year-total': isTotal(index), 'annual-month-empty': isMonthEmpty(child, index, amount) }">
							<NcButton variant="tertiary" size="small" class="annual-amount" :title="formatAmount(amount)" :aria-label="linkLabel(child.direct ? $texts.common.mainCategory() : child.name, index, amount)" @click="openDetails({ ...child, type: row.type }, index, false, `${row.label} · ${child.direct ? $texts.common.mainCategory() : child.name}`)">{{ cellAmount(child, index, amount) }}</NcButton>
						</td>
					</tr>
				</template>
			</template>
		</tbody>
		<tfoot v-if="totalRow" v-show="open">
			<tr class="annual-total">
				<th scope="row"><router-link :to="paymentRoute(totalRow, null, true)" :title="$texts.entry.groupTotal(totalRow.label)">{{ $texts.entry.groupTotal(totalRow.label) }}</router-link></th>
				<td v-for="(amount, index) in [...totalRow.months, totalRow.total]" :key="index" :class="{ 'annual-year-total': isTotal(index), 'annual-month-empty': isMonthEmpty(totalRow, index, amount) }">
					<NcButton variant="tertiary" size="small" class="annual-amount" :title="formatAmount(amount)" :aria-label="linkLabel($texts.entry.groupTotal(totalRow.label), index, amount)" @click="openDetails(totalRow, index, true, $texts.entry.groupTotal(totalRow.label))">{{ cellAmount(totalRow, index, amount) }}</NcButton>
				</td>
			</tr>
		</tfoot>
	</table>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import { annualPaymentRoute, readAnnualSectionOpen, rememberAnnualSection } from '../services/annualOverview'
import { getAppLanguage } from '../utils/formatDate'

export default {
	name: 'AnnualOverviewTable',
	components: { NcButton, ChevronDownIcon, ChevronRightIcon },
	emits: ['details'],
	props: {
		title: { type: String, required: true },
		firstHeading: { type: String, required: true },
		columns: { type: Array, required: true },
		columnTitles: { type: Array, default: () => [] },
		rows: { type: Array, required: true },
		selection: { type: Object, required: true },
		periods: { type: Array, required: true },
		summary: { type: Boolean, default: false },
		totalRow: { type: Object, default: null },
		emptyMessage: { type: String, default: '' },
		section: { type: String, default: '' },
	},
	data() { return { expanded: {}, sortBy: 'title', sortDir: 'asc', open: readAnnualSectionOpen(this.section) } },
	computed: {
		collapsible() { return this.section === 'income' || this.section === 'expense' || this.section === 'total' },
		sortIcon() { return this.sortDir === 'asc' ? '↑' : '↓' },
		sortedRows() {
			if (this.summary) return this.rows
			const direction = this.sortDir === 'asc' ? 1 : -1
			const compareTitle = (a, b) => (a.label ?? a.name).localeCompare(b.label ?? b.name, getAppLanguage(), { sensitivity: 'base' })
			const compare = (a, b) => this.sortBy === 'amount'
				? (a.total - b.total) * direction || compareTitle(a, b)
				: compareTitle(a, b) * direction
			return [...this.rows].sort(compare).map(row => ({
				...row,
				children: [...(row.children || [])].sort((a, b) => Number(Boolean(b.direct)) - Number(Boolean(a.direct)) || compare(a, b)),
			}))
		},
	},
	methods: {
		toggleSection() {
			if (!this.collapsible) return
			this.open = !this.open
			rememberAnnualSection(this.section, this.open)
		},
		toggleSort(column) {
			if (this.summary) return
			if (this.sortBy === column) this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'
			else {
				this.sortBy = column
				this.sortDir = column === 'title' ? 'asc' : 'desc'
			}
		},
		sortAria(column) { return this.summary ? undefined : this.sortBy === column ? (this.sortDir === 'asc' ? 'ascending' : 'descending') : 'none' },
		toggle(key) { this.expanded[key] = !this.expanded[key] },
		formatAmount(cents) { return this.$formatMoneyFromCents(cents) },
		linkLabel(label, index, amount) { return this.$texts.annual.showDetails(label, this.columns[index], this.formatAmount(amount)) },
		openDetails(row, index, total = this.summary, label = row.label ?? row.name) {
			this.$emit('details', { row, index, total, label, route: this.paymentRoute(row, index, total) })
		},
		paymentRoute(row, index, total = this.summary) { return annualPaymentRoute(this.selection, index === null ? null : this.periods[index], row.type, total ? null : row) },
		isTotal(index) {
			if (Array.isArray(this.columns) && this.columns.length) return index === this.columns.length - 1
			return index === 12
		},
		columnTitle(index) { return this.columnTitles[index] || this.columns[index] },
		isMonthEmpty(row, index, amount) {
			const totalColumn = Array.isArray(this.columns) && this.columns.length ? this.columns.length - 1 : 12
			if (index === totalColumn) return false
			if (row?.type === 'all') return false
			if (row?.counts && typeof row.counts[index] === 'number') {
				return row.counts[index] === 0
			}
			return amount === 0
		},
		cellAmount(row, index, amount) {
			return this.isMonthEmpty(row, index, amount) ? '–' : this.formatAmount(amount)
		},
	},
}
</script>

<style scoped>
.annual-table {
	table-layout: fixed;
	border-collapse: separate;
	border-spacing: 0;
	width: 100%;
	min-width: calc(var(--annual-label-min, 180px) + (var(--annual-bucket-count, 12) + 1) * var(--annual-bucket-width, 108px) + 2px);
	margin-bottom: 24px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	font-size: var(--cobudget-font-sm);
}
.label-column { width: auto; }
.amount-column,
.total-column { width: var(--annual-bucket-width, 108px); max-width: var(--annual-bucket-width, 108px); }
caption {
	text-align: start;
	font-size: var(--cobudget-font-lg);
	font-weight: 600;
	padding: 12px 0;
	color: var(--cobudget-text);
}
.annual-table-title {
	display: inline-block;
	position: sticky;
	inset-inline-start: 0;
}
.annual-table.is-collapsed { min-width: 0; margin-bottom: 4px; border: 0; }
.annual-section-toggle { display: inline-flex; align-items: center; gap: 2px; position: sticky; inset-inline-start: 0; max-width: 100%; margin: 0; padding: 2px 4px; border: 0; border-radius: var(--border-radius-element, 8px); background: transparent; color: inherit; font: inherit; font-weight: 600; text-align: start; cursor: pointer; }
.annual-section-toggle .annual-table-title { position: static; }
.annual-table .annual-section-toggle:hover { background: var(--cobudget-surface-muted, var(--color-background-hover)); }
.annual-table .annual-section-toggle:focus,
.annual-table .annual-section-toggle:active { background: transparent; }
.annual-section-toggle:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: 2px; }
th, td {
	box-sizing: border-box;
	min-width: 0;
	border-bottom: 1px solid var(--cobudget-border, #ddd);
	text-align: end;
	vertical-align: middle;
	font-size: var(--cobudget-font-sm);
	background: var(--cobudget-surface, #fff);
}
thead th {
	padding: 4px 10px;
	overflow: hidden;
	color: var(--cobudget-text-muted, #888);
	letter-spacing: 0.5px;
	text-overflow: ellipsis;
	white-space: nowrap;
	background: var(--cobudget-surface-muted, #f9f9f9);
}
thead th:first-child { border-start-start-radius: calc(var(--border-radius-large, 8px) - 1px); }
thead th:last-child { border-start-end-radius: calc(var(--border-radius-large, 8px) - 1px); }
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { background: var(--cobudget-surface-strong, #eee); }
.sortable:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: -2px; }
.sort-icon { display: inline-block; margin-left: 4px; font-size: var(--cobudget-font-sm); }
th:first-child {
	position: sticky;
	inset-inline-start: 0;
	text-align: start;
	z-index: 1;
	box-shadow: 1px 0 var(--cobudget-border);
}
.annual-year-total {
	position: sticky;
	inset-inline-end: 0;
	z-index: 1;
	box-shadow: -1px 0 var(--cobudget-border);
}
tbody th, tbody td, tfoot th, tfoot td {
	padding: 0 5px 0 10px;
	color: var(--cobudget-text, #222);
}
tbody th, tfoot th {
	overflow: hidden;
	font-weight: 400;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.annual-table .annual-category-toggle {
	flex: 0 0 auto;
	margin: 0;
	font-size: inherit;
	font-weight: inherit;
}
.annual-row-label { display: flex; align-items: center; }
.annual-row-label a { flex: 1; min-width: 0; }
.annual-table a { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
tbody th:hover, tbody td:hover, tfoot th:hover, tfoot td:hover { background: var(--cobudget-surface-muted, #f6f6f6); }
.annual-table .annual-empty-cell { padding: 0; background: var(--cobudget-surface, #fff); }
.annual-empty-message {
	position: sticky;
	inset-inline-start: 0;
	box-sizing: border-box;
	width: calc(100cqi - 2px);
	max-width: 100%;
	padding: 32px 16px;
	text-align: center;
	white-space: normal;
	color: var(--cobudget-text-muted);
}
.annual-table:not(.has-total-row) tbody tr:last-child th, .annual-table:not(.has-total-row) tbody tr:last-child td, tfoot th, tfoot td { border-bottom: none; }
.annual-table:not(.has-total-row) tbody tr:last-child th:first-child, tfoot th:first-child { border-end-start-radius: calc(var(--border-radius-large, 8px) - 1px); }
.annual-table:not(.has-total-row) tbody tr:last-child td:last-child, tfoot td:last-child { border-end-end-radius: calc(var(--border-radius-large, 8px) - 1px); }
td { font-variant-numeric: tabular-nums; white-space: nowrap; }
.annual-table a {
	display: block;
	padding: 4px 5px 4px 0;
	color: inherit;
	text-decoration: none;
	border-radius: var(--border-radius);
}
.annual-table a:hover { text-decoration: none; color: inherit; }
.annual-table a:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: -2px; }
.annual-table .annual-amount {
	width: 100%; min-width: 0; min-height: 0; height: auto; margin: 0; padding: 4px 5px 4px 0;
	border: 0; border-radius: 0; background: transparent; color: inherit; font: inherit;
	justify-content: flex-end;
}
.annual-table .annual-amount { background: transparent !important; }
.annual-table .annual-amount :deep(.button-vue__wrapper) { min-width: 0; justify-content: flex-end; }
.annual-table .annual-amount :deep(.button-vue__text) { font-weight: inherit; margin: 0; padding: 0 !important; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.annual-table .annual-amount:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: -2px; }
.annual-year-total, .annual-total td, .annual-total th { font-weight: 600; }
.annual-total th, .annual-total td { background: var(--cobudget-surface-muted, #f5f9fb); }
.annual-total th:hover, .annual-total td:hover { background: var(--cobudget-surface-strong); }
.annual-child th { padding-inline-start: 36px; color: var(--cobudget-text-muted); }
tbody td.negative-balance { color: var(--cobudget-error); }
.annual-table td.annual-month-empty .annual-amount { color: var(--cobudget-text-muted, #888); }
</style>
