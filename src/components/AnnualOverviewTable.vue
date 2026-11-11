<template>
	<table class="annual-table" :class="{ 'report-tree-table': hasHierarchy, 'has-total-row': totalRow, 'has-controls': Boolean($slots.controls), 'is-embedded': embedded }" :data-print-title="printTitle || undefined">
		<caption v-if="embedded" class="annual-embedded-caption">{{ title }}</caption>
		<caption v-else>
			<div class="annual-section-header">
				<div class="annual-section-heading">
					<h3 class="cobudget-section-heading annual-table-title">{{ title }}</h3>
					<ReportSectionMenu v-if="!embedded" class="annual-section-menu" :title="title" :export-data="exportData" :export-disabled="exportDisabled" />
				</div>
				<div v-if="$slots.controls" class="annual-section-controls"><slot name="controls" /></div>
				<ReportScrollControls class="annual-section-scroll-controls" :navigation="scrollNavigation" />
			</div>
		</caption>
		<colgroup><col class="label-column"><col v-for="(column, index) in columns" :key="index" :class="isTotal(index) ? 'total-column' : 'amount-column'"></colgroup>
		<thead>
			<tr>
				<th scope="col" :title="firstHeading" :class="{ sortable: !summary }" :tabindex="summary ? undefined : 0" :aria-sort="sortAria('title')"
					@click="toggleSort('title')" @keydown.enter.prevent="toggleSort('title')" @keydown.space.prevent="toggleSort('title')">
					<span class="report-column-heading"><span class="report-column-label">{{ firstHeading }}</span><component v-if="!summary && sortBy === 'title'" :is="sortDir === 'asc' ? 'MenuUpIcon' : 'MenuDownIcon'" class="sort-icon" :size="24" aria-hidden="true" /></span>
				</th>
				<th v-for="(column, index) in columns" :key="index" scope="col" :title="columnTitle(index)" :class="{ sortable: !summary && isTotal(index), 'annual-year-total': isTotal(index) }"
					:tabindex="!summary && isTotal(index) ? 0 : undefined" :aria-sort="isTotal(index) ? sortAria('amount') : undefined"
					@click="isTotal(index) && toggleSort('amount')" @keydown.enter.prevent="isTotal(index) && toggleSort('amount')" @keydown.space.prevent="isTotal(index) && toggleSort('amount')">
					<span class="report-column-heading"><span class="report-column-label">{{ column }}</span><component v-if="!summary && isTotal(index) && sortBy === 'amount'" :is="sortDir === 'asc' ? 'MenuUpIcon' : 'MenuDownIcon'" class="sort-icon" :size="24" aria-hidden="true" /></span>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr v-if="!rows.length"><td :colspan="columns.length + 1" class="annual-empty-cell"><div class="annual-empty-message"><slot name="empty">{{ emptyMessage || $texts.common.noFilteredEntries() }}</slot></div></td></tr>
			<tr v-for="row in visibleRows" :key="row.key" :class="{ 'annual-total': summary && row.type === 'all', 'annual-parent': descendants(row).length, 'annual-child': row.depth > 0 }">
				<th scope="row">
					<div class="annual-row-label report-tree-label" :class="{ 'report-tree-child': row.depth > 0, 'report-tree-last': row.treeLast, 'report-tree-parent': descendants(row).length }" :style="{ '--report-tree-depth': row.depth }">
						<template v-for="(continues, level) in row.treeGuides" :key="level"><span v-if="continues" class="report-tree-guide" :style="{ '--report-tree-level': level }" aria-hidden="true" /></template>
						<span v-if="hasHierarchy && (!row.depth || descendants(row).length)" class="report-tree-gutter">
						<NcButton v-if="descendants(row).length" variant="tertiary" size="small" class="annual-category-toggle" :title="row.label" :aria-label="row.label" :aria-expanded="Boolean(expanded[row.key])" @click="toggle(row.key)">
							<template #icon><ChevronDownIcon v-if="expanded[row.key]" :size="18" /><ChevronRightIcon v-else :size="18" /></template>
						</NcButton>
						</span>
						<router-link :to="detailLocation(row, columns.length - 1)" :title="row.label" :class="{ 'report-category-label': row.icon && !row.direct && row.areaId === undefined }"><CategoryIcon v-if="row.icon && !row.direct && row.areaId === undefined" :icon="row.icon" :size="16" aria-hidden="true" /><span>{{ row.label }}</span></router-link>
					</div>
				</th>
				<td v-for="(amount, index) in [...row.months, row.total]" :key="index" :class="{ 'annual-year-total': isTotal(index), 'negative-balance': row.type === 'all' && amount < 0, 'annual-month-empty': isMonthEmpty(row, index, amount) }">
					<NcButton variant="tertiary" size="small" class="annual-amount" :title="formatAmount(amount)" :aria-label="linkLabel(row.label, index, amount)" @click="openDetails(row, index, summary, row.fullLabel)">{{ cellAmount(row, index, amount) }}</NcButton>
				</td>
			</tr>
		</tbody>
		<tfoot v-if="totalRow">
			<tr class="annual-total">
				<th scope="row"><router-link v-if="totalInteractive" :to="detailLocation(totalRow, columns.length - 1, true)" :title="$texts.entry.groupTotal(totalRow.label)">{{ $texts.entry.groupTotal(totalRow.label) }}</router-link><span v-else class="annual-static-label" :title="totalRow.label">{{ totalRow.label }}</span></th>
				<td v-for="(amount, index) in [...totalRow.months, totalRow.total]" :key="index" :class="{ 'annual-year-total': isTotal(index), 'annual-month-empty': isMonthEmpty(totalRow, index, amount) }">
					<NcButton v-if="totalInteractive" variant="tertiary" size="small" class="annual-amount" :title="formatAmount(amount)" :aria-label="linkLabel($texts.entry.groupTotal(totalRow.label), index, amount)" @click="openDetails(totalRow, index, true, $texts.entry.groupTotal(totalRow.label))">{{ cellAmount(totalRow, index, amount) }}</NcButton><span v-else class="annual-static-amount">{{ cellAmount(totalRow, index, amount) }}</span>
				</td>
			</tr>
		</tfoot>
	</table>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import MenuUpIcon from 'vue-material-design-icons/MenuUp.vue'
import MenuDownIcon from 'vue-material-design-icons/MenuDown.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import CategoryIcon from './CategoryIcon.vue'
import ReportSectionMenu from './ReportSectionMenu.vue'
import ReportScrollControls from './ReportScrollControls.vue'
import { annualPaymentRoute, annualRouteQuery } from '../services/annualOverview'
import { reportDetailQuery, reportAreaRows } from '../services/reportDetails'
import { getAppLanguage } from '../utils/formatDate'
import { reportAmountExportSection, reportDisplayedTableExport, reportExportNotReady } from '../services/reportExport'

export default {
	name: 'AnnualOverviewTable',
	components: { MenuUpIcon, MenuDownIcon, CategoryIcon, NcButton, ChevronDownIcon, ChevronRightIcon, ReportSectionMenu, ReportScrollControls },
	emits: ['details'],
	props: {
		title: { type: String, required: true },
		printTitle: { type: String, default: '' },
		firstHeading: { type: String, required: true },
		columns: { type: Array, required: true },
		columnTitles: { type: Array, default: () => [] },
		rows: { type: Array, required: true },
		selection: { type: Object, required: true },
		periods: { type: Array, required: true },
		summary: { type: Boolean, default: false },
		totalRow: { type: Object, default: null },
		totalInteractive: { type: Boolean, default: true },
		detailLocationResolver: { type: Function, default: null },
		areaOrder: { type: Boolean, default: false },
		emptyMessage: { type: String, default: '' },
		section: { type: String, default: '' },
		embedded: { type: Boolean, default: false },
		exportDisabled: { type: Boolean, default: false },
		scrollNavigation: { type: Object, default: null },
	},
	data() {
		const sortByAmount = !this.summary && ['income', 'expense'].includes(this.section)
		return { expanded: {}, sortBy: sortByAmount ? 'amount' : 'title', sortDir: sortByAmount ? 'desc' : 'asc' }
	},
	computed: {
		hasHierarchy() { return this.sortedRows.some(row => this.descendants(row).length) },
		visibleRows() {
			const result = []
			const walk = (rows, depth = 0, prefix = '', treeGuides = []) => {
				for (const [index, row] of rows.entries()) {
					const treeLast = index === rows.length - 1
					const label = row.label ?? (row.areaId === 0 ? this.$texts.annual.withoutArea() : row.direct ? this.$texts.common.mainCategory() : row.name)
					const key = row.key ?? `${row.type}-${row.reportKey ?? row.id}-${Boolean(row.direct)}`
					const fullLabel = prefix ? `${prefix} · ${label}` : label
					result.push({ ...row, key, label, fullLabel, depth, treeLast, treeGuides })
					if (this.expanded[key]) walk(this.descendants(row).map(child => ({ ...child, type: row.type })), depth + 1, fullLabel, depth ? [...treeGuides, !treeLast] : [])
				}
			}
			walk(this.sortedRows)
			return result
		},
		sortedRows() {
			if (this.summary) return this.rows
			const direction = this.sortDir === 'asc' ? 1 : -1
			const compareTitle = (a, b) => this.areaOrder
				? Number(b.id === 0) - Number(a.id === 0) || (a.label ?? a.name).localeCompare(b.label ?? b.name, getAppLanguage(), { sensitivity: 'base', numeric: true, ignorePunctuation: true })
				: (a.label ?? a.name).localeCompare(b.label ?? b.name, getAppLanguage(), { sensitivity: 'base' })
			const compare = (a, b) => this.sortBy === 'amount'
				? (a.total - b.total) * direction || compareTitle(a, b)
				: compareTitle(a, b) * direction
			const sortTree = rows => [...rows].sort((a, b) => Number(Boolean(b.direct)) - Number(Boolean(a.direct)) || compare(a, b)).map(row => ({
				...row, children: sortTree(row.children || []), areas: sortTree(row.areas || []),
			}))
			return sortTree(this.rows)
		},
	},
	methods: {
		descendants(row) { return row.children?.length ? row.children : reportAreaRows(row) },
		exportData({ display = false } = {}) {
			if (this.exportDisabled) throw reportExportNotReady()
			if (display) return reportDisplayedTableExport(this.$el, this.printTitle || this.title)
			return [reportAmountExportSection({ title: this.printTitle || this.title, firstHeading: this.firstHeading, columns: this.columnTitles.length ? this.columnTitles : this.columns, rows: this.sortedRows, totalRow: this.totalRow, totalLabel: !this.totalInteractive ? this.totalRow?.label : '', texts: this.$texts })]
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
			if (this.detailLocationResolver) { this.$router.push(this.detailLocation(row, index, total)); return }
			this.$emit('details', { row, index, total, label, route: this.paymentRoute(row, index, total) })
		},
		detailLocation(row, index, total = this.summary) { return this.detailLocationResolver ? this.detailLocationResolver(row, index, total) : { name: 'annual-overview', query: { ...annualRouteQuery(this.selection), ...reportDetailQuery({ row, index, total }, this.periods) } } },
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

<style src="../styles/reportTree.css"></style>
<style scoped>
.annual-table {
	table-layout: fixed;
	border-collapse: separate;
	border-spacing: 0;
	width: 100%;
	min-width: calc(var(--annual-label-min, 180px) + (var(--annual-bucket-count, 12) + 1) * var(--annual-bucket-width, 108px) + var(--annual-table-inline-borders, 0px));
	margin: 0 0 var(--cobudget-report-section-gap, 48px);
	border: 0;
	border-radius: var(--cobudget-table-radius);
	font-size: var(--cobudget-font-base);
}
.label-column { width: auto; }
.amount-column,
.total-column { width: var(--annual-bucket-width, 108px); max-width: var(--annual-bucket-width, 108px); }
caption {
	text-align: start;
	font-size: var(--cobudget-font-body);
	padding: 0;
	color: var(--cobudget-text);
}
.annual-table-title {
	display: inline-block;
	margin: 0;
}
.annual-section-header { position: sticky; inset-inline-start: 0; display: flex; flex-wrap: wrap; align-items: center; gap: var(--default-grid-baseline, 4px); box-sizing: border-box; width: calc(100cqi - var(--annual-table-inline-borders, 0px)); max-width: 100%; min-height: var(--cobudget-report-header-height, 44px); margin-block-end: var(--cobudget-content-gap); }
.annual-section-heading { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); min-width: 0; }
.annual-section-controls { display: flex; justify-content: flex-end; min-width: 0; max-width: 100%; margin-inline-start: auto; }
.annual-section-scroll-controls { margin-inline-start: auto; }
.annual-section-controls ~ .annual-section-scroll-controls { margin-inline-start: 0; }
.annual-table.is-embedded { margin-bottom: 0; }
.annual-embedded-caption { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
th, td {
	box-sizing: border-box;
	min-width: 0;
	border-bottom: 1px solid var(--cobudget-table-separator);
	text-align: end;
	vertical-align: middle;
	font-size: var(--cobudget-font-base);
	background: var(--cobudget-surface, #fff);
}
.annual-table thead th {
	padding: var(--cobudget-table-payment-padding);
	overflow: hidden;
	color: var(--cobudget-text-muted, #888);
	letter-spacing: normal;
	font-size: var(--cobudget-table-header-font-size);
	font-weight: 400;
	height: var(--cobudget-table-header-height);
	text-overflow: ellipsis;
	white-space: nowrap;
	background: var(--cobudget-table-header-background);
}
thead th:first-child { border-start-start-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
thead th:last-child { border-start-end-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { background: var(--cobudget-table-sort-hover); }
.sortable:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: -2px; }
.report-column-heading { display: inline-flex; align-items: center; gap: var(--default-grid-baseline, 4px); max-width: 100%; vertical-align: middle; }
.report-column-label { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.sort-icon { display: inline-flex; flex-shrink: 0; color: inherit; }
thead th[aria-sort]:not([aria-sort="none"]) { color: var(--cobudget-text); }
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
	padding: var(--cobudget-table-payment-padding);
	height: var(--cobudget-payment-table-row-height);
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
.annual-row-label a > span:last-child { overflow: hidden; text-overflow: ellipsis; }
.annual-table a { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
tbody th:hover, tbody td:hover, tfoot th:hover, tfoot td:hover { background: var(--cobudget-table-row-hover); }
.annual-table .annual-empty-cell { padding: 0; background: var(--cobudget-surface, #fff); }
.annual-empty-message {
	position: sticky;
	inset-inline-start: 0;
	box-sizing: border-box;
	width: calc(100cqi - var(--annual-table-inline-borders, 0px));
	max-width: 100%;
	padding: 32px 16px;
	text-align: center;
	white-space: normal;
	color: var(--cobudget-text-muted);
}
.annual-table:not(.has-total-row) tbody tr:last-child th, .annual-table:not(.has-total-row) tbody tr:last-child td, tfoot th, tfoot td { border-bottom: none; }
.annual-table:not(.has-total-row) tbody tr:last-child th:first-child, tfoot th:first-child { border-end-start-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
.annual-table:not(.has-total-row) tbody tr:last-child td:last-child, tfoot td:last-child { border-end-end-radius: max(0px, calc(var(--cobudget-table-radius) - 1px)); }
td { font-variant-numeric: tabular-nums; white-space: nowrap; }
.annual-table a {
	display: block;
	padding: 4px 0;
	color: inherit;
	text-decoration: none;
	border-radius: var(--border-radius);
}
.annual-table a:hover { text-decoration: none; color: inherit; }
.annual-table a:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: -2px; }
.annual-table .annual-amount {
	width: 100%; min-width: 0; min-height: calc(var(--cobudget-payment-table-row-height) - 1px); height: auto; margin: 0; padding: 0;
	border: 0; border-radius: 0; background: transparent; color: inherit; font: inherit;
	justify-content: flex-end;
}
.annual-table .annual-amount { background: transparent !important; }
.annual-table .annual-amount :deep(.button-vue__wrapper) { min-width: 0; justify-content: flex-end; }
.annual-table .annual-amount :deep(.button-vue__text) { font-weight: inherit; margin: 0; padding: 0 !important; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.annual-table .annual-amount:focus-visible { outline: 2px solid var(--cobudget-primary); outline-offset: -2px; box-shadow: none !important; }
.annual-year-total, .annual-total td, .annual-total th { font-weight: 600; }
.annual-static-label, .annual-static-amount { display: block; padding: 4px 0; }
.annual-total th, .annual-total td { background: var(--cobudget-surface); border-top: 1px solid var(--color-border-maxcontrast, var(--cobudget-border-strong)); border-bottom: 0; }
.annual-table.has-total-row tbody tr:last-child > *, .annual-table tbody tr:has(+ .annual-total) > * { border-bottom: 0; }
.annual-total th:hover, .annual-total td:hover { background: var(--cobudget-table-row-hover); }
.annual-child th { color: var(--cobudget-text-muted); }
tbody td.negative-balance { color: var(--cobudget-error); }
.annual-table td.annual-month-empty .annual-amount { color: var(--cobudget-text-muted, #888); }
/* Track the full row while preserving cell backgrounds and sticky-column shadows. */
.annual-table :is(tbody, tfoot) tr:hover > :is(th, td):not(.annual-empty-cell) {
	background-image: linear-gradient(var(--cobudget-primary), var(--cobudget-primary)), linear-gradient(var(--cobudget-primary), var(--cobudget-primary));
	background-size: 100% 1px;
	background-position: top, bottom;
	background-repeat: no-repeat;
}
@media (max-width: 768px) {
	.annual-section-controls { order: 2; }
	.annual-section-scroll-controls { order: 1; margin-inline-start: auto; }
	.annual-table .annual-category-toggle { min-width: var(--cobudget-mobile-touch-size, 44px); min-height: var(--cobudget-mobile-touch-size, 44px); }
	/* Mobile reports meet the viewport edge, including individual corner cells. */
	.annual-table { --cobudget-table-radius: 0px; }
	tbody th, tfoot th { white-space: normal; }
	.annual-table a, .annual-static-label {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		white-space: normal;
		overflow-wrap: anywhere;
		overflow: hidden;
	}
}
.annual-table :is(th, td) { transition: background-color var(--cobudget-hover-duration) linear; }
.annual-table tr:focus-within > * { transition: none; }
</style>
