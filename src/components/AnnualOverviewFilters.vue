<template>
	<div v-if="sectionAvailable && (!chipsOnly || chips.length)" class="annual-filter-cluster" :class="{ 'annual-filter-cluster--chips': chipsOnly }">
		<div v-if="!hideChips && !section && chips.length" class="annual-filter-chips">
			<NcChip v-for="chip in chips" :key="chip.key" class="cobudget-filter-chip" :text="chip.label" :title="chip.label" :icon-path="filterChipIcon(chip.key === 'labels' && normalized.labels.length === 1 ? `label:${normalized.labels[0]}` : chip.key)"
				:aria-label-close="$texts.annual.removeFilter()" @close="$emit('clear', chip.key)" />
		</div>
		<AnchoredPopover v-if="!chipsOnly" class="annual-filter-popover" placement="bottom-end" :set-return-focus="returnTrigger" @update:shown="onPopoverShown" @after-show="focusPanel">
			<template #trigger>
				<span ref="trigger" class="annual-filter-trigger">
					<NcButton variant="tertiary" class="annual-filter-button cobudget-filter-control" :class="{ 'cobudget-filter-active': triggerActive }" :aria-description="triggerActive ? $texts.filters.active() : undefined" :aria-label="triggerLabel" :title="triggerLabel">
						<span v-if="section" class="annual-filter-shortcut-caption"><component :is="sectionIcon" :size="20" aria-hidden="true" />{{ triggerLabel }}<ChevronDownIcon :size="18" /></span>
						<template v-if="!section" #icon><FilterVariantIcon :size="20" /></template>
						<span v-if="!section" class="annual-filter-caption">{{ $texts.filters.filter() }}</span>
					</NcButton>
				</span>
			</template>
			<div ref="panel" class="annual-filter-panel" :class="{ 'annual-filter-panel--submenu': activeSubmenu }" :style="panelStyle" @keydown.esc="onEscape" @keyup.esc="onEscapeKeyup">
				<NcButton v-if="activeSubmenu && !section" ref="back" variant="tertiary" wide alignment="start" class="annual-filter-back"
					:aria-label="$texts.filters.backToFilters()" @click="backToFilters">
					<template #icon><ArrowLeftIcon :size="20" /></template>{{ submenuTitle }}
				</NcButton>
				<NcInputField ref="search" v-show="!activeSubmenu" v-model="searchText" type="search" class="annual-filter-search cobudget-native-field" :label="$texts.filters.search()" @update:model-value="scheduleSearch" @keydown.enter.prevent="applySearch" @blur="applySearch" />
				<section v-show="!activeSubmenu || activeSubmenu === 'period'" class="annual-filter-section">
					<NcButton v-show="!activeSubmenu" ref="periodTrigger" variant="tertiary" wide alignment="start" class="annual-filter-toggle cobudget-filter-disclosure"
						:aria-description="periodActive ? $texts.filters.active() : undefined" :aria-expanded="activeSubmenu === 'period'" @click="toggleSection('period')">
						<CalendarOutlineIcon :size="20" aria-hidden="true" />
						<span class="annual-filter-heading">{{ $texts.analytics.period() }}</span>
						<span v-if="periodActive" class="cobudget-filter-dot" aria-hidden="true" />
						<ChevronRightIcon :size="18" />
					</NcButton>
					<ReportPeriodPicker v-if="activeSubmenu === 'period'" inline calendar-multiple report-calendar-selection :selection="selection" :years="years"
						:label="$texts.analytics.period()" @change="applyPeriod" />
				</section>
				<section v-if="areaOptions.length" v-show="!activeSubmenu || activeSubmenu === 'areas'" class="annual-filter-section">
					<NcButton v-show="!activeSubmenu" ref="areasTrigger" variant="tertiary" wide alignment="start" class="annual-filter-toggle cobudget-filter-disclosure"
						:aria-description="activeSections.areas ? $texts.filters.active() : undefined" :aria-expanded="activeSubmenu === 'areas'" @click="toggleSection('areas')">
						<FolderIcon :size="20" aria-hidden="true" /><span class="annual-filter-heading">{{ $texts.analytics.areas() }}</span>
						<span v-if="activeSections.areas" class="cobudget-filter-dot" aria-hidden="true" /><ChevronRightIcon :size="18" />
					</NcButton>
					<div v-show="activeSubmenu === 'areas'" class="annual-filter-body">
						<div class="annual-filter-options">
							<NcFormBoxSwitch v-for="option in areaOptions" :key="String(option.value)" class="annual-filter-option"
								:model-value="option.value === null ? !areaIds.length : areaIds.includes(option.value)" @update:model-value="toggleArea(option.value)">
								<span class="annual-filter-category"><AreaIcon v-if="option.icon" :without-area="option.icon === 'without-area'" :color="option.color" aria-hidden="true" /><span>{{ option.label }}</span></span>
							</NcFormBoxSwitch>
						</div>
						<ReportSelect v-if="showAmountScope" :model-value="selection.entryScope" :label="$texts.annual.amountScope()"
							:options="[{ value: 'personal', label: $texts.annual.personalShare() }, { value: 'shared', label: $texts.annual.wholeArea() }]" @update:model-value="$emit('scope-change', $event)" />
					</div>
				</section>
				<section v-if="sections.categories && categoryOptions.length" class="annual-filter-section" v-show="!activeSubmenu || activeSubmenu === 'categories'">
					<NcButton v-show="!activeSubmenu" ref="categoriesTrigger" variant="tertiary" wide alignment="start" class="annual-filter-toggle cobudget-filter-disclosure" :aria-description="activeSections.categories ? $texts.filters.active() : undefined" :aria-expanded="activeSubmenu === 'categories'" @click="toggleSection('categories')">
						<ShapeOutlineIcon :size="20" aria-hidden="true" />
						<span class="annual-filter-heading">{{ $texts.analytics.category() }}</span>
						<span v-if="activeSections.categories" class="cobudget-filter-dot" aria-hidden="true" />
						<ChevronRightIcon :size="18" />
					</NcButton>
					<PaymentFilterSelect v-show="activeSubmenu === 'categories'" :open="activeSubmenu === 'categories'" :show-back="false" multiple
						:model-value="normalized.categories" :options="categoryChoices" :label="$texts.analytics.category()"
						:group-kinds="$enableIncomes !== false ? ['income', 'expense'] : ['expense']" :group-labels="categoryGroupLabels"
						:group-disclosure-label="$texts.filters.chooseCategories()" @update:model-value="setSelection('categories', $event)" />
				</section>
				<section v-if="sections.partners && partnerOptions.length" class="annual-filter-section" v-show="!activeSubmenu || activeSubmenu === 'partners'">
					<NcButton v-show="!activeSubmenu" ref="partnersTrigger" variant="tertiary" wide alignment="start" class="annual-filter-toggle cobudget-filter-disclosure" :aria-description="activeSections.partners ? $texts.filters.active() : undefined" :aria-expanded="activeSubmenu === 'partners'" @click="toggleSection('partners')">
						<AccountOutlineIcon :size="20" aria-hidden="true" />
						<span class="annual-filter-heading">{{ $texts.analytics.paymentPartner() }}</span>
						<span v-if="activeSections.partners" class="cobudget-filter-dot" aria-hidden="true" />
						<ChevronRightIcon :size="18" />
					</NcButton>
					<PaymentFilterSelect v-show="activeSubmenu === 'partners'" :open="activeSubmenu === 'partners'" :show-back="false" multiple
						:model-value="normalized.partners" :options="partnerChoices" :label="$texts.analytics.paymentPartner()"
						:group-kinds="$enableIncomes !== false ? ['income', 'expense'] : ['expense']" :group-labels="partnerGroupLabels"
						:group-disclosure-label="$texts.filters.choosePaymentPartners()" @update:model-value="setSelection('partners', $event)" />
				</section>
				<section v-if="sections.labels && labelOptions.length" class="annual-filter-section" v-show="!activeSubmenu || activeSubmenu === 'labels'">
					<NcButton v-show="!activeSubmenu" ref="labelsTrigger" variant="tertiary" wide alignment="start" class="annual-filter-toggle cobudget-filter-disclosure" :aria-description="activeSections.labels ? $texts.filters.active() : undefined" :aria-expanded="activeSubmenu === 'labels'" @click="toggleSection('labels')">
						<TagOutlineIcon :size="20" aria-hidden="true" />
						<span class="annual-filter-heading">{{ $texts.analytics.labels() }}</span>
						<span v-if="activeSections.labels" class="cobudget-filter-dot" aria-hidden="true" />
						<ChevronRightIcon :size="18" />
					</NcButton>
					<PaymentFilterSelect v-show="activeSubmenu === 'labels'" :open="activeSubmenu === 'labels'" :show-back="false" multiple native-switches
						:model-value="normalized.labels" :options="labelChoices" :label="$texts.analytics.labels()"
						@update:model-value="setSelection('labels', $event)" />
				</section>
				<section v-if="sections.hashtags && hashtagOptions.length" class="annual-filter-section" v-show="!activeSubmenu || activeSubmenu === 'hashtags'">
					<NcButton v-show="!activeSubmenu" ref="hashtagsTrigger" variant="tertiary" wide alignment="start" class="annual-filter-toggle cobudget-filter-disclosure" :aria-description="activeSections.hashtags ? $texts.filters.active() : undefined" :aria-expanded="activeSubmenu === 'hashtags'" @click="toggleSection('hashtags')">
						<PoundIcon :size="20" aria-hidden="true" />
						<span class="annual-filter-heading">{{ $texts.annual.tags() }}</span>
						<span v-if="activeSections.hashtags" class="cobudget-filter-dot" aria-hidden="true" />
						<ChevronRightIcon :size="18" />
					</NcButton>
					<div v-show="activeSubmenu === 'hashtags'" class="annual-filter-body">
						<NcInputField v-if="hashtagOptions.length > 8" v-model="hashtagQuery" type="search" class="annual-filter-search cobudget-native-field" :label="$texts.filters.search()" />
						<div class="annual-filter-options">
							<NcFormBoxSwitch v-for="option in visibleHashtags" :key="option.id" class="annual-filter-option" :model-value="normalized.hashtags.includes(option.id)" @update:model-value="toggle('hashtags', option.id)">
								<span>{{ option.label }}</span>
							</NcFormBoxSwitch>
						</div>
					</div>
				</section>
				<section v-if="!activeSubmenu" class="annual-filter-section">
					<NcButton variant="tertiary" wide alignment="start" class="annual-filter-toggle cobudget-filter-disclosure"
						:aria-description="activeSections.amount ? $texts.filters.active() : undefined" :aria-expanded="amountOpen" @click="toggleAmount">
						<CashIcon :size="20" aria-hidden="true" />
						<span class="annual-filter-heading">{{ $texts.entry.tableAmount($currency) }}</span>
						<span v-if="activeSections.amount" class="cobudget-filter-dot" aria-hidden="true" />
						<ChevronDownIcon :size="18" :class="{ 'annual-filter-chevron--expanded': amountOpen }" />
					</NcButton>
					<form v-if="amountOpen" ref="amountForm" class="annual-filter-amount-form" @submit.prevent="applyAmount">
						<div class="annual-filter-amounts cobudget-filter-bounds">
							<div>
								<label class="cobudget-filter-field-label" :for="`${amountFieldId}-from`">{{ $texts.filters.amountFrom() }}</label>
								<NcInputField :id="`${amountFieldId}-from`" ref="amountMin" v-model="amountMinText" inputmode="decimal" label-outside class="cobudget-native-field" :error="amountError" />
							</div>
							<div>
								<label class="cobudget-filter-field-label" :for="`${amountFieldId}-to`">{{ $texts.filters.amountTo() }}</label>
								<NcInputField :id="`${amountFieldId}-to`" ref="amountMax" v-model="amountMaxText" inputmode="decimal" label-outside class="cobudget-native-field" :error="amountError" />
							</div>
						</div>
						<p v-if="amountError" class="annual-filter-amount-error" role="alert">{{ $texts.filters.invalidAmountRange() }}</p>
						<NcButton type="submit" variant="primary">{{ $texts.filters.applyAmount() }}</NcButton>
					</form>
				</section>
				<div v-if="!activeSubmenu && hasResettableFilters" class="annual-filter-actions">
					<NcButton variant="tertiary" @click="resetFilters">{{ $texts.filters.clearFilters() }}</NcButton>
				</div>
			</div>
		</AnchoredPopover>
	</div>
</template>

<script>
import { useId } from 'vue'
import ReportPeriodPicker from './ReportPeriodPicker.vue'
import CalendarOutlineIcon from 'vue-material-design-icons/CalendarOutline.vue'
import ShapeOutlineIcon from 'vue-material-design-icons/ShapeOutline.vue'
import AccountOutlineIcon from 'vue-material-design-icons/AccountOutline.vue'
import TagOutlineIcon from 'vue-material-design-icons/TagOutline.vue'
import PoundIcon from 'vue-material-design-icons/Pound.vue'
import CashIcon from 'vue-material-design-icons/Cash.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import AnchoredPopover from './AnchoredPopover.vue'
import AreaIcon from './AreaIcon.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import ReportSelect from './ReportSelect.vue'
import PaymentFilterSelect from './PaymentFilterSelect.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import { filterChipIcon } from '../services/filterChipIcons'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import FilterVariantIcon from 'vue-material-design-icons/FilterVariant.vue'
import { annualFilterChips, annualFilterPanelMaxHeight, annualFilterSections, annualSelection, formatAnnualAmountParam, parseAnnualAmountInput } from '../services/annualOverview'
import { categoryChoiceLabel, categoryPathLabel, categoryParentId, sortCategoriesHierarchically } from '../utils/categoryHierarchy'

export default {
	name: 'AnnualOverviewFilters',
	setup() { return { amountFieldId: useId() } },
	components: { ReportPeriodPicker, CalendarOutlineIcon, ShapeOutlineIcon, AccountOutlineIcon, TagOutlineIcon, PoundIcon, CashIcon, ChevronDownIcon, NcButton, AnchoredPopover, AreaIcon, FolderIcon, ReportSelect, PaymentFilterSelect, ChevronRightIcon, ArrowLeftIcon, NcChip, NcInputField, NcFormBoxSwitch, FilterVariantIcon },
	props: {
		section: { type: String, default: '' },
		chipsOnly: { type: Boolean, default: false },
		hideChips: { type: Boolean, default: false },
		areaOptions: { type: Array, default: () => [] },
		areaIds: { type: Array, default: () => [] },
		showAmountScope: { type: Boolean, default: false },
		selection: { type: Object, required: true },
		years: { type: Array, default: () => [] },
		periodLabel: { type: String, default: '' },
		categories: { type: Array, default: () => [] },
		partners: { type: Array, default: () => [] },
		hashtags: { type: Array, default: () => [] },
		categoryIds: { type: Array, default: () => [] },
		partnerIds: { type: Array, default: () => [] },
		labelKeys: { type: Array, default: () => [] },
	},
	emits: ['change', 'clear', 'reset', 'period-change', 'areas-change', 'scope-change'],
	data() {
		return {
			searchText: this.selection.search || '',
			searchTimer: null,
			hashtagQuery: '',
			amountMinText: '',
			amountMaxText: '',
			amountOpen: false,
			amountError: false,
			disposed: false,
			popoverShown: false,
			panelMaxHeight: '',
			viewportBound: false,
			fitFrame: 0,
			fitPasses: 0,
			activeSubmenu: null,
			consumedEscape: false,
		}
	},
	computed: {
		sectionAvailable() {
			if (!this.section) return true
			if (this.section === 'areas') return this.areaOptions.length > 0
			return Boolean(this.sections[this.section] && { categories: this.categoryOptions, partners: this.partnerOptions, labels: this.labelOptions }[this.section]?.length)
		},
		triggerActive() { return this.section ? this.activeSections[this.section] : this.chips.length > 0 || this.periodActive || this.activeSections.areas },
		triggerLabel() { return this.section ? this.sectionTitle(this.section) : this.$texts.annual.filterPayments() },
		sectionIcon() { return { areas: 'FolderIcon', categories: 'ShapeOutlineIcon', partners: 'AccountOutlineIcon', labels: 'TagOutlineIcon' }[this.section] },
		hasResettableFilters() {
			return this.chips.some(chip => !['areas', 'period'].includes(chip.key)) || Boolean(this.normalized.reportFilters || this.normalized.withoutArea)
				|| Boolean(this.searchText.trim() || this.amountMinText || this.amountMaxText)
		},
		periodActive() {
			const current = annualSelection({ timeRange: 'current-year' })
			const { timeRange, year, dateFrom, dateTo } = this.normalized
			if (timeRange === 'calendar-selection') return this.normalized.periodYears.length !== 1 || this.normalized.periodYears[0] !== current.year || this.normalized.periodMonths.length > 0
			return timeRange ? dateFrom !== current.dateFrom || dateTo !== current.dateTo : year !== current.year
		},
		activeSections() {
			return {
				areas: this.areaIds.length > 0,
				categories: this.normalized.categories.length > 0,
				partners: this.normalized.partners.length > 0,
				amount: this.normalized.amountMin !== null || this.normalized.amountMax !== null,
				labels: this.normalized.labels.length > 0,
				hashtags: this.normalized.hashtags.length > 0,
			}
		},
		normalized() { return annualSelection(this.selection) },
		sections() { return annualFilterSections(this.normalized.grouping) },
		categoryNames() { return new Map(this.categoryOptions.map(option => [option.id, option.label])) },
		partnerNames() { return new Map(this.partnerOptions.map(option => [option.id, option.label])) },
		categoryTypes() { return this.kindTypes(this.categoryOptions) },
		categoryGroupLabels() { return { income: this.$texts.filters.allIncomeCategories(), expense: this.$texts.filters.allExpenseCategories() } },
		categoryChoices() {
			return [{ value: 0, label: this.$texts.annual.noCategory() }, ...this.categoryOptions.filter(option => option.id > 0 && (this.$enableIncomes !== false || option.type !== 'income')).map(option => ({
				...option, value: option.id, label: option.name, categoryIcon: option.icon,
				accessibleLabel: option.type ? `${option.pathLabel} · ${this.kindLabel(option.type)}` : option.pathLabel,
			}))]
		},
		partnerGroupLabels() { return { income: this.$texts.filters.allIncomePartners(), expense: this.$texts.filters.allExpensePartners() } },
		partnerChoices() { return [{ value: 0, label: this.$texts.annual.noPartner() }, ...this.partnerOptions.filter(option => option.id > 0).map(option => ({ ...option, value: option.id }))] },
		labelChoices() { return [{ value: null, label: this.$texts.filters.allLabels() }, ...this.labelOptions.map(option => ({ value: option.key, label: option.label }))] },
		partnerTypes() { return this.kindTypes(this.partnerOptions) },
		hashtagNames() { return new Map(this.hashtagOptions.map(option => [option.id, option.name])) },
		labelNames() { return Object.fromEntries(this.labelOptions.map(option => [option.key, option.label])) },
		chips() {
			const chips = annualFilterChips(this.normalized, {
				categoryNames: this.categoryNames,
				categoryGroupLabels: this.categoryGroupLabels,
				partnerNames: this.partnerNames,
				partnerGroupLabels: this.partnerGroupLabels,
				categoryTypes: this.categoryTypes,
				partnerTypes: this.partnerTypes,
				hashtagNames: this.hashtagNames,
				labelNames: this.labelNames,
				withoutArea: this.$texts.annual.withoutArea(),
				noCategory: this.$texts.annual.noCategory(),
				noPartner: this.$texts.annual.noPartner(),
				or: this.$texts.annual.or(),
				atLeast: this.$texts.annual.atLeast(),
				atMost: this.$texts.annual.atMost(),
				formatMoney: cents => this.$formatMoneyFromCents(cents),
			})
			if (this.areaIds.length && !this.normalized.withoutArea) {
				const names = this.areaIds.map(id => this.areaOptions.find(option => option.value === id)?.label || String(id))
				const scope = this.showAmountScope ? ` · ${this.selection.entryScope === 'shared' ? this.$texts.annual.wholeArea() : this.$texts.annual.personalShare()}` : ''
				chips.unshift({ key: 'areas', label: names.join(` ${this.$texts.annual.or()} `) + scope })
			}
			if (this.periodActive && this.periodLabel) chips.unshift({ key: 'period', label: this.periodLabel })
			return chips
		},
		submenuTitle() {
			return this.sectionTitle(this.activeSubmenu)
		},
		categoryOptions() {
			const used = this.usedIds(this.categoryIds, this.normalized.categories)
			const options = this.kindGroups(this.categories, used).flatMap(group => {
				const sorted = sortCategoriesHierarchically(group.items)
				const visibleIds = new Set(sorted.map(category => Number(category.id)))
				return sorted.map(category => {
					const child = visibleIds.has(categoryParentId(category))
					const name = String(category.name || '').trim()
					const parent = this.categories.find(item => Number(item.id) === categoryParentId(category))
					const parentName = parent?.name || category.parent_name || ''
					const pathLabel = categoryPathLabel({ ...category, parent_name: parentName })
					return {
						id: Number(category.id), name, icon: category.icon || 'Shape',
						parentId: categoryParentId(category), pathLabel, context: !child ? parentName : '',
						label: child ? categoryChoiceLabel(category) : name,
						child,
						type: group.type,
					}
				})
			}).filter(option => Number.isSafeInteger(option.id) && option.id > 0 && option.label)
			if (used.has(0)) options.unshift({ id: 0, label: this.$texts.annual.noCategory(), child: false, type: '' })
			return options
		},
		partnerOptions() {
			const used = this.usedIds(this.partnerIds, this.normalized.partners)
			const byLabel = (left, right) => left.label.localeCompare(right.label, undefined, { sensitivity: 'base' })
			const options = this.kindGroups(this.partners, used).flatMap(group => group.items
				.map(partner => ({ id: Number(partner.id), label: String(partner.name || '').trim(), child: false, type: group.type }))
				.filter(option => Number.isSafeInteger(option.id) && option.id > 0 && option.label)
				.sort(byLabel))
			if (used.has(0)) options.unshift({ id: 0, label: this.$texts.annual.noPartner(), child: false, type: '' })
			return options
		},
		hashtagOptions() {
			return [...this.hashtags]
				.map(hashtag => {
					const name = String(hashtag.displayName || hashtag.name || '').trim()
					return { id: Number(hashtag.id), name, label: name.startsWith('#') ? name : `#${name}` }
				})
				.filter(option => Number.isSafeInteger(option.id) && option.id > 0 && option.name)
				.sort((left, right) => left.label.localeCompare(right.label, undefined, { sensitivity: 'base' }))
		},
		labelOptions() {
			const options = [
				['important', this.$enableImportantPayments, this.$texts.labels.important()],
				['review', this.$enableReviewPayments, this.$texts.labels.review()],
				['fixedCost', this.$enableFixedCosts, this.$texts.labels.fixedCosts()],
				['childRelated', this.$enableChildRelated, this.$texts.labels.children()],
				['subscription', this.$enableSubscriptions, this.$texts.labels.subscription()],
				['taxRelevant', this.$enableTaxRelevant, this.$texts.labels.taxRelevant()],
				['insurance', this.$enableInsurance, this.$texts.labels.insurance()],
			]
			return options.filter(([, enabled]) => enabled !== false).map(([key, , label]) => ({ key, label }))
		},
		visibleHashtags() { return this.visibleOptions(this.hashtagOptions, this.hashtagQuery) },
		panelStyle() { return this.panelMaxHeight ? { '--annual-filter-max-height': this.panelMaxHeight } : null },
	},
	watch: {
		'normalized.search'(value) {
			if (!value) { clearTimeout(this.searchTimer); this.searchTimer = null }
			if (!this.searchTimer) this.searchText = value || ''
		},
		'normalized.amountMin': { handler() { this.syncAmount('min') }, immediate: true },
		'normalized.amountMax': { handler() { this.syncAmount('max') }, immediate: true },
		hashtagQuery() { this.scheduleFit() },
	},
	beforeUnmount() {
		this.disposed = true
		clearTimeout(this.searchTimer)
		this.unbindViewport()
		if (this.fitFrame && typeof window !== 'undefined') cancelAnimationFrame(this.fitFrame)
	},
	methods: {
		filterChipIcon,
		sectionTitle(key) {
			return { areas: this.$texts.analytics.areas(), period: this.$texts.analytics.period(), categories: this.$texts.analytics.category(), partners: this.$texts.analytics.paymentPartner(),
				amount: this.$texts.entry.amount(), labels: this.$texts.analytics.labels(), hashtags: this.$texts.annual.tags() }[key] || ''
		},
		toggleArea(value) {
			if (!this.areaOptions.some(option => option.value === value)) return
			this.$emit('areas-change', value === null ? [] : this.areaIds.includes(value) ? this.areaIds.filter(id => id !== value) : [...this.areaIds, value])
		},
		returnTrigger() { return this.$refs.trigger?.querySelector('button') || false },
		focusPanel() {
			this.scheduleFit()
			this.$nextTick(() => {
				const controls = [...(this.$refs.panel?.querySelectorAll('input:not(:disabled), button:not(:disabled)') || [])]
					.filter(control => control.getClientRects().length > 0)
				;(controls.find(control => control.checked) || controls[0])?.focus({ preventScroll: true })
			})
		},
		resetFilters() {
			clearTimeout(this.searchTimer)
			this.searchTimer = null
			this.amountOpen = false
			this.amountError = false
			this.searchText = ''
			this.amountMinText = ''
			this.amountMaxText = ''
			this.hashtagQuery = ''
			this.$emit('reset')
			this.scheduleFit()
			this.$nextTick(() => {
				const root = this.$refs.search?.$el
				const input = root?.querySelector?.('input') || root
				input?.focus({ preventScroll: true })
			})
		},
		scheduleSearch() {
			clearTimeout(this.searchTimer)
			this.searchTimer = setTimeout(() => this.applySearch(), 300)
		},
		applySearch() {
			clearTimeout(this.searchTimer)
			this.searchTimer = null
			if (!this.disposed && this.searchText.trim() !== (this.normalized.search || '')) this.$emit('change', { ...this.normalized, search: this.searchText.trim() })
		},
		kindGroups(items, used) {
			const groups = { expense: [], income: [], other: [] }
			for (const item of Array.isArray(items) ? items : []) {
				if (!used.has(Number(item?.id))) continue
				const type = item?.type === 'income' || item?.type === 'expense' ? item.type : 'other'
				groups[type].push(item)
			}
			return [
				{ type: 'expense', items: groups.expense },
				{ type: 'income', items: groups.income },
				{ type: '', items: groups.other },
			]
		},
		kindTypes(options) {
			if (this.$enableIncomes === false) return new Map()
			return new Map(options.filter(option => option.type).map(option => [option.id, option.type]))
		},
		kindLabel(type) {
			return type === 'income' ? this.$texts.annual.income() : this.$texts.annual.expenses()
		},
		usedIds(ids, selected) {
			const used = new Set((Array.isArray(ids) ? ids : []).map(id => Number(id)).filter(id => Number.isSafeInteger(id) && id >= 0))
			for (const id of selected || []) used.add(id)
			return used
		},
		visibleOptions(options, query) {
			const needle = query.trim().toLocaleLowerCase()
			if (!needle) return options
			return options.filter(option => option.label.toLocaleLowerCase().includes(needle))
		},
		toggleSection(key) {
			this.activeSubmenu = key
			this.$nextTick(() => {
				if (this.$refs.panel) this.$refs.panel.scrollTop = 0
				this.$refs.back?.$el?.focus({ preventScroll: true })
				this.scheduleFit()
			})
		},
		backToFilters() {
			const key = this.activeSubmenu
			this.activeSubmenu = null
			this.$nextTick(() => {
				this.$refs[`${key}Trigger`]?.$el?.focus({ preventScroll: true })
				this.scheduleFit()
			})
		},
		onEscape(event) {
			if (!this.activeSubmenu || this.section) return
			this.consumedEscape = true
			event.preventDefault()
			event.stopPropagation()
			this.backToFilters()
		},
		onEscapeKeyup(event) {
			// NcPopover closes on keyup; going back must consume that event too.
			if (!this.consumedEscape) return
			this.consumedEscape = false
			event.preventDefault()
			event.stopPropagation()
		},
		onPopoverShown(shown) {
			this.popoverShown = shown === true
			this.fitPasses = 0
			if (this.popoverShown) {
				this.activeSubmenu = this.section || null
				this.amountOpen = this.activeSections.amount
				this.panelMaxHeight = ''
				this.bindViewport()
				this.scheduleFit()
			} else {
				// Preserve the current submenu and panel size during native fade-out.
				this.consumedEscape = false
				this.unbindViewport()
			}
		},
		bindViewport() {
			if (this.viewportBound || typeof window === 'undefined') return
			this.viewportBound = true
			window.addEventListener('resize', this.scheduleFit)
			window.addEventListener('scroll', this.scheduleFit, true)
			window.visualViewport?.addEventListener('resize', this.scheduleFit)
			window.visualViewport?.addEventListener('scroll', this.scheduleFit)
		},
		unbindViewport() {
			if (!this.viewportBound || typeof window === 'undefined') return
			this.viewportBound = false
			window.removeEventListener('resize', this.scheduleFit)
			window.removeEventListener('scroll', this.scheduleFit, true)
			window.visualViewport?.removeEventListener('resize', this.scheduleFit)
			window.visualViewport?.removeEventListener('scroll', this.scheduleFit)
		},
		scheduleFit() {
			if (this.disposed || !this.popoverShown || typeof window === 'undefined' || typeof window.requestAnimationFrame !== 'function') return
			if (this.fitFrame) cancelAnimationFrame(this.fitFrame)
			this.fitFrame = window.requestAnimationFrame(() => {
				this.fitFrame = 0
				this.fitPanel()
			})
		},
		fitPanel() {
			if (this.disposed || !this.popoverShown) return
			const panel = this.$refs.panel
			const trigger = this.$refs.trigger
			const popper = panel?.closest?.('.v-popper__popper')
			if (!panel || !trigger || !popper || popper.getAttribute('aria-hidden') === 'true') {
				this.retryFit()
				return
			}
			const triggerRect = trigger.getBoundingClientRect()
			const next = annualFilterPanelMaxHeight({
				triggerTop: triggerRect.top,
				triggerBottom: triggerRect.bottom,
				viewportHeight: window.innerHeight,
				placement: popper.getAttribute('data-popper-placement') || '',
			})
			if (!next) {
				this.retryFit()
				return
			}
			if (this.panelMaxHeight === next) {
				this.fitPasses = 0
				return
			}
			this.panelMaxHeight = next
			this.retryFit()
		},
		retryFit() {
			this.fitPasses += 1
			if (this.fitPasses < 4) this.scheduleFit()
			else this.fitPasses = 0
		},
		setSelection(key, values) {
			this.$emit('change', { ...this.normalized, [key]: values })
			this.scheduleFit()
		},
		toggle(key, id) {
			const current = [...this.normalized[key]]
			const index = current.indexOf(id)
			if (index === -1) current.push(id)
			else current.splice(index, 1)
			this.$emit('change', { ...this.normalized, [key]: current })
		},
		syncAmount(which) {
			const field = which === 'min' ? 'amountMin' : 'amountMax'
			const input = this.$refs?.[field]
			const element = input?.$el || input
			if (typeof document !== 'undefined' && element?.contains(document.activeElement)) return
			this[`${field}Text`] = formatAnnualAmountParam(this.normalized[field])
		},
		applyPeriod(selection) {
			this.$emit('period-change', selection)
			this.scheduleFit()
		},
		toggleAmount() {
			this.amountOpen = !this.amountOpen
			this.$nextTick(() => {
				this.scheduleFit()
				if (this.amountOpen) this.$refs.amountForm?.querySelector('[type="submit"]')?.scrollIntoView({ block: 'nearest' })
			})
		},
		applyAmount() {
			const drafts = [this.amountMinText.trim(), this.amountMaxText.trim()]
			const [amountMin, amountMax] = drafts.map(value => /^[\d.,\s]+$/.test(value) ? parseAnnualAmountInput(value) : null)
			this.amountError = Boolean((drafts[0] && amountMin === null) || (drafts[1] && amountMax === null)
				|| (amountMin !== null && amountMax !== null && amountMin > amountMax))
			if (this.amountError) return
			this.$emit('change', { ...this.normalized, amountMin, amountMax })
		},
	},
}
</script>

<style scoped>
.annual-filter-cluster { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; min-width: 0; max-width: 100%; }
.annual-filter-cluster--chips { display: flex; margin-block: calc(var(--default-grid-baseline, 4px) * 7); }
.annual-filter-shortcut-caption { display: inline-flex; align-items: center; gap: var(--default-grid-baseline, 4px); }
.annual-filter-popover { display: inline-flex; }
.annual-filter-trigger { display: inline-flex; }
.annual-filter-chips { display: flex; flex-wrap: wrap; gap: 4px; min-width: 0; max-width: 100%; }
.annual-filter-panel { --color-background-hover: color-mix(in srgb, var(--color-primary-element) 8%, transparent); box-sizing: border-box; width: min(320px, calc(100vw - 48px)); display: flex; flex-direction: column; gap: 4px; max-height: var(--annual-filter-max-height, none); padding: var(--cobudget-filter-menu-padding); overflow-y: auto; overscroll-behavior: contain; }
.annual-filter-panel--submenu { gap: 0; }
.annual-filter-actions { display: flex; justify-content: flex-end; margin-block-start: calc(var(--default-grid-baseline, 4px) * 2); }
.annual-filter-toggle.button-vue { --font-weight-element: 400; width: 100%; }
.annual-filter-toggle :deep(.button-vue__text) { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; text-align: start; }
.annual-filter-heading { flex: 1; min-width: 0; }
.annual-filter-search { width: 100%; margin-bottom: 6px; }
.annual-filter-options { display: flex; flex-direction: column; gap: var(--default-grid-baseline, 4px); }
.annual-filter-chevron--expanded { transform: rotate(180deg); }
.annual-filter-amount-form { display: grid; gap: 4px; margin-block: 4px; }
.annual-filter-amount-form > .button-vue { justify-self: end; }
.annual-filter-amount-error { margin: 0; color: var(--color-error-text); }
.annual-filter-back { margin-bottom: 8px; }

@keyframes cobudget-annual-submenu-in {
	from { opacity: 0; transform: translateY(-2px); }
	to { opacity: 1; transform: translateY(0); }
}
.annual-filter-back,
.annual-filter-body {
	animation: cobudget-annual-submenu-in var(--animation-quick, 100ms) ease;
}
@media (prefers-reduced-motion: reduce) {
	.annual-filter-back,
	.annual-filter-body {
		animation: none !important;
	}
}
.annual-filter-category { display: flex; align-items: center; gap: 8px; min-width: 0; width: 100%; }
.annual-filter-amounts { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 132px), 1fr)); gap: 8px; }
@media (max-width: 768px) {
	.annual-filter-panel, .annual-filter-button { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); }
}
</style>
