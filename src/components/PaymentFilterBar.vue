<template>
	<div v-if="!chipsOnly || chips.length" class="payment-filter-bar" :class="{ 'payment-filter-bar--chips': chipsOnly }">
		<div v-if="!chipsOnly" class="payment-filter-bar__controls cobudget-filter-controls" role="group" :aria-label="$texts.annual.filterSummary()">
			<AnchoredPopover placement="bottom-end" class="payment-category-shortcut" :set-return-focus="() => triggerElement('categoryId')" @after-show="focusPanel('categoryId')">
				<template #trigger>
					<NcButton ref="categoryIdTrigger" class="cobudget-filter-control" variant="tertiary" :class="{ 'cobudget-filter-active': categoryActive }">
						<span class="payment-filter-bar__trigger cobudget-filter-trigger"><ShapeOutlineIcon :size="20" aria-hidden="true" />{{ $texts.analytics.category() }}<ChevronDownIcon :size="18" /></span>
					</NcButton>
				</template>
				<TableFilters ref="categoryIdPanel" section="categoryId" v-bind="filterProps" @update:filters="update" />
			</AnchoredPopover>
			<AnchoredPopover placement="bottom-end" class="payment-partner-shortcut" :set-return-focus="() => triggerElement('paymentPartnerId')" @after-show="focusPanel('paymentPartnerId')">
				<template #trigger>
					<NcButton ref="paymentPartnerIdTrigger" class="cobudget-filter-control" variant="tertiary" :class="{ 'cobudget-filter-active': partnerActive }">
						<span class="payment-filter-bar__trigger cobudget-filter-trigger"><AccountOutlineIcon :size="20" aria-hidden="true" />{{ $texts.analytics.paymentPartners() }}<ChevronDownIcon :size="18" /></span>
					</NcButton>
				</template>
				<TableFilters ref="paymentPartnerIdPanel" section="paymentPartnerId" v-bind="filterProps" @update:filters="update" />
			</AnchoredPopover>
			<NcButton v-if="$enableReviewPayments && (reviewCount > 0 || reviewActive)" variant="tertiary" class="cobudget-filter-control payment-review-shortcut"
				:class="{ 'cobudget-filter-active': reviewActive }" :aria-label="$texts.labels.review()" :title="$texts.labels.review()" :aria-pressed="reviewActive" @click="toggleReview">
				<span class="payment-review-content">
					<ClipboardCheckIcon :size="20" aria-hidden="true" />
					<span class="payment-review-caption">{{ $texts.labels.review() }}</span>
				</span>
			</NcButton>
			<AnchoredPopover placement="bottom-end" :set-return-focus="() => triggerElement('filters')" @after-show="focusPanel('filters')">
				<template #trigger>
					<NcButton ref="filtersTrigger" class="cobudget-filter-control" variant="tertiary" :aria-label="$texts.annual.filterPayments()" :title="$texts.annual.filterPayments()" :class="{ 'cobudget-filter-active': hasActiveFilters }">
						<template #icon><FilterVariantIcon :size="20" /></template>
						<span class="payment-filter-caption">{{ $texts.filters.filter() }}</span>
					</NcButton>
				</template>
				<TableFilters ref="filtersPanel" show-amount show-type-buttons :period-selection="periodSelection" :period-years="periodYears" :period-active="Boolean(periodChipLabel)" :reset-time-range="resetTimeRange ?? defaultTimeRange" v-bind="filterProps" @update:filters="update" />
			</AnchoredPopover>
		</div>
		<div v-else class="payment-filter-bar__chips">
			<NcChip v-for="chip in chips" :key="chip.key" class="payment-filter-chip cobudget-filter-chip" :text="chip.label" :icon-path="filterChipIcon(chip.key)" :title="chip.label" :aria-label-close="$texts.annual.removeFilter()" @close="removeChip(chip)" />
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import FilterVariantIcon from 'vue-material-design-icons/FilterVariant.vue'
import AnchoredPopover from './AnchoredPopover.vue'
import ClipboardCheckIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import AccountOutlineIcon from 'vue-material-design-icons/AccountOutline.vue'
import ShapeOutlineIcon from 'vue-material-design-icons/ShapeOutline.vue'
import TableFilters from './TableFilters.vue'
import { filterChipIcon } from '../services/filterChipIcons'
import { getAppLocale } from '../utils/formatMoney'
import { selectedPaymentLabels, withPaymentLabels } from '../services/entryListRoute'

const dimensions = {
	categoryId: 'narrowCategories', projectId: 'narrowProjects', paymentPartnerId: 'narrowPartners', hashtagId: 'narrowHashtags',
}
const selectedIds = (filters, key) => [...new Set([filters[key], ...(filters[dimensions[key]] || '').split(',')]
	.filter(value => value !== null && value !== undefined && value !== '').map(Number).filter(id => Number.isInteger(id) && id >= 0))]

export default {
	name: 'PaymentFilterBar',
	components: { AccountOutlineIcon, ClipboardCheckIcon, ShapeOutlineIcon, NcButton, NcChip, ChevronDownIcon, FilterVariantIcon, AnchoredPopover, TableFilters },
	props: {
		filters: { type: Object, required: true },
		categories: { type: Array, default: () => [] },
		projects: { type: Array, default: () => [] },
		paymentPartners: { type: Array, default: () => [] },
		hashtags: { type: Array, default: () => [] },
		chipsOnly: { type: Boolean, default: false },
		reviewCount: { type: Number, default: 0 },
		labelCounts: { type: Object, default: () => ({}) },
		filterAvailability: { type: Object, default: null },
		periodSelection: { type: Object, default: null },
		periodYears: { type: Array, default: () => [] },
		periodChipLabel: { type: String, default: '' },
		resetTimeRange: { type: String, default: null },
		defaultTimeRange: { type: String, default: 'currentYear' },
	},
	emits: ['update:filters'],
	computed: {
		labels() { return selectedPaymentLabels(this.filters) },
		reviewActive() { return this.labels.includes('review') },
		categoryActive() { return selectedIds(this.filters, 'categoryId').length > 0 || Boolean(this.filters.narrowCategories) },
		partnerActive() { return selectedIds(this.filters, 'paymentPartnerId').length > 0 || Boolean(this.filters.narrowPartners) },
		filterProps() {
			return { categories: this.categories, projects: this.projects, paymentPartners: this.paymentPartners, hashtags: this.hashtags,
				initialFilters: this.filters, filterAvailability: this.filterAvailability, labelCounts: this.labelCounts,
				showTimeRange: false, preservePaymentView: true, groupLookupFilters: true }
		},
		hasActiveFilters() { return this.chips.length > 0 || this.filters.timeRange !== this.defaultTimeRange },
		chips() {
			const chips = []
			const add = (key, label, values) => chips.push({ key, label, values })
			if (this.periodChipLabel) add('timeRange', this.periodChipLabel, {
				timeRange: this.defaultTimeRange, dateFrom: null, dateTo: null, periodYears: '', periodMonths: '',
			})
			if (this.filters.type === 'income') add('type', this.$texts.filters.onlyIncome(), { type: 'all' })
			if (this.filters.type === 'expense') add('type', this.$texts.filters.onlyExpense(), { type: 'all' })
			if (this.filters.search) add('search', this.filters.search, { search: '' })
			const catalogs = { categoryId: this.categories, projectId: this.projects, paymentPartnerId: this.paymentPartners, hashtagId: this.hashtags }
			for (const key of Object.keys(dimensions)) {
				const ids = selectedIds(this.filters, key)
				const excludedAreas = key === 'projectId' ? (this.filters.narrowProjects || '').split(',')
					.filter(value => value.startsWith('except-')).map(value => Number(value.slice(7))) : []
				if (excludedAreas.length) {
					const labels = excludedAreas.map(id => id === 0 ? this.$texts.annual.withoutArea()
						: this.projects.find(item => Number(item.id) === id)?.name || String(id))
					add(key, `${this.$texts.filters.excludedAreas()}: ${labels.join(', ')}`, { projectId: null, narrowProjects: '' })
					continue
				}
				const groupKeys = key === 'projectId' ? ['assigned'] : ['categoryId', 'paymentPartnerId'].includes(key) ? ['income', 'expense'] : []
				const kinds = (this.filters[dimensions[key]] || '').split(',').filter(kind => groupKeys.includes(kind))
				if (!ids.length && !kinds.length) continue
				const labels = ids.map(id => id === 0 ? key === 'projectId' ? this.$texts.annual.withoutArea() : key === 'categoryId' ? this.$texts.annual.noCategory() : this.$texts.annual.noPartner()
					: key === 'hashtagId' ? '#' + (catalogs[key].find(item => Number(item.id) === id)?.displayName || catalogs[key].find(item => Number(item.id) === id)?.name || id)
					: catalogs[key].find(item => Number(item.id) === id)?.name || String(id))
				const groupLabels = key === 'categoryId'
					? { income: this.$texts.filters.allIncomeCategories(), expense: this.$texts.filters.allExpenseCategories() }
					: { income: this.$texts.filters.allIncomePartners(), expense: this.$texts.filters.allExpensePartners() }
				labels.push(...kinds.map(kind => kind === 'assigned' ? this.$texts.filters.allAreas() : groupLabels[kind]))
				add(key, labels.join(', '), { [key]: null, [dimensions[key]]: '', ...(key === 'categoryId' ? { includeSubcategories: false } : {}) })
			}
			const labelNames = { important: this.$texts.labels.important(), review: this.$texts.labels.review(), fixedCost: this.$texts.labels.fixedCosts(), childRelated: this.$texts.labels.children(), subscription: this.$texts.labels.subscription(), taxRelevant: this.$texts.labels.taxRelevant(), insurance: this.$texts.labels.insurance() }
			for (const label of this.labels) chips.push({ key: `label:${label}`, label: labelNames[label], paymentLabel: label })
			if (this.$enableReminders !== false && this.filters.hasReminder === 'true') add('hasReminder', this.$texts.filters.onlyWithReminders(), { hasReminder: 'all' })
			if (this.filters.hasAttachment === 'true' || this.filters.hasAttachment === 'false') add('hasAttachment', this.filters.hasAttachment === 'true' ? this.$texts.filters.onlyWithReceipt() : this.$texts.filters.onlyWithoutReceipt(), { hasAttachment: 'all' })
			if (this.filters.includeSubcategories) add('includeSubcategories', this.$texts.annual.includeSubcategories(), { includeSubcategories: false })
			if (this.filters.includeFuturePayments) add('includeFuturePayments', this.$texts.annual.includeFuture(), { includeFuturePayments: false })
			if (this.filters.status === 'settled') add('status', this.$texts.filters.settledEntries(), { status: 'all' })
			if (this.filters.amountMin || this.filters.amountMax) {
				const { amountMin, amountMax } = this.filters
				const format = new Intl.NumberFormat(getAppLocale(), { maximumFractionDigits: 2 })
				const bounds = [amountMin && `≥ ${format.format(amountMin)}`, amountMax && `≤ ${format.format(amountMax)}`].filter(Boolean).join(' · ')
				add('amount', bounds, { amountMin: '', amountMax: '' })
			}
			if (this.filters.reportFilters) add('reportFilters', this.$texts.annual.filterSummary(), { reportFilters: '' })
			return chips
		},

	},
	methods: {
		filterChipIcon,
		update(filters) { this.$emit('update:filters', filters) },
		toggleReview() {
			const labels = this.reviewActive ? this.labels.filter(label => label !== 'review') : ['review']
			this.update(withPaymentLabels(this.filters, labels))
		},
		removeChip(chip) { this.update(chip.paymentLabel ? withPaymentLabels(this.filters, this.labels.filter(key => key !== chip.paymentLabel)) : { ...this.filters, ...chip.values }) },
		triggerElement(key) { return this.$refs[key + 'Trigger']?.$el || false },
		focusPanel(key) {
			this.$nextTick(() => {
				const controls = [...(this.$refs[key + 'Panel']?.$el?.querySelectorAll('input:not(:disabled), button:not(:disabled)') || [])]
					.filter(control => control.getClientRects().length > 0)
				;(controls.find(control => control.checked) || controls[0])?.focus({ preventScroll: true })
			})
		},
	},
}
</script>

<style scoped>
.payment-filter-bar { flex: 0 0 auto; min-width: 0; }
.payment-filter-bar--chips { flex-shrink: 0; margin-block: calc(var(--default-grid-baseline, 4px) * 7); margin-inline-start: calc(-2 * var(--default-grid-baseline, 4px)); }
.payment-filter-bar__chips { display: flex; align-items: center; gap: 4px; min-width: 0; }
.payment-filter-bar__chips { flex-wrap: wrap; }
.payment-review-content { display: inline-flex; align-items: center; gap: 4px; }
.payment-filter-chip { min-width: 0; max-width: min(100%, 360px); }
</style>
