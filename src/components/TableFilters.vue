<template>
	<div ref="panel" class="table-filters" :class="{ 'table-filters--compact': showTypeButtons }">
		<div class="filter-column">
			<!-- 1. Suche -->
			<div v-if="!excludedSections.includes('search')" v-show="!activeSubmenu || activeSubmenu === 'search'" class="filter-group search-group">
				<NcInputField v-model="localFilters.search" type="search" :label="$texts.filters.search()"
					@update:model-value="debouncedEmit" @blur="flushSearch" @keydown.enter.prevent="flushSearch" class="cobudget-native-field" />
			</div>


			<!-- 2. Typ Dropdown -->
			<NcRadioGroup v-if="$enableIncomes && showTypeButtons" v-show="!activeSubmenu"
				:label="$texts.entry.paymentType()" hide-label :model-value="localFilters.type" @update:model-value="setSelection('type', $event)">
				<NcRadioGroupButton value="all" :label="$texts.annual.all()" />
				<NcRadioGroupButton value="income" :label="$texts.labels.incomePayments()">
					<template #icon><PlusIcon :size="16" /></template>
				</NcRadioGroupButton>
				<NcRadioGroupButton value="expense" :label="$texts.dashboard.expenses()">
					<template #icon><MinusIcon :size="16" /></template>
				</NcRadioGroupButton>
			</NcRadioGroup>
			<div class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'type'" v-if="$enableIncomes && !showTypeButtons && !excludedSections.includes('type')">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" :model-value="localFilters.type" :active="activeSections.type" :open="activeSubmenu === 'type'" @update:open="activeSubmenu = $event ? 'type' : null" :options="typeChoices"
					:label="$texts.filters.incomeAndExpense()"
					@update:model-value="setSelection('type', $event)" />
			</div>

			<!-- 3. Zeitraum Dropdown -->
			<div v-if="periodSelection" v-show="!activeSubmenu || activeSubmenu === 'timeRange'" class="filter-group">
				<PaymentFilterSelect fixed-label :trigger-icon="filterIcons.period" :label="$texts.analytics.period()" :options="[]" :active="periodActive"
					:open="activeSubmenu === 'timeRange'" @update:open="activeSubmenu = $event ? 'timeRange' : null">
					<template #options>
						<ReportPeriodPicker inline calendar-multiple :label="$texts.analytics.period()" :selection="periodSelection" :years="periodYears"
							@change="applyPeriodSelection" />
					</template>
				</PaymentFilterSelect>
			</div>

			<div class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'timeRange'" v-if="showTimeRange && localFilters.tags !== 'future'">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" :model-value="localFilters.timeRange" :active="activeSections.timeRange" :open="activeSubmenu === 'timeRange'" @update:open="activeSubmenu = $event ? 'timeRange' : null" :options="timeRangeChoices"
					:label="$texts.filters.allTime()"
					@update:model-value="setSelection('timeRange', $event)" />
			</div>

			<p v-show="!activeSubmenu || activeSubmenu === 'timeRange'" v-if="showTimeRange && localFilters.timeRange === 'custom'" class="custom-period">{{ customPeriodLabel }}</p>
			<NcCheckboxRadioSwitch v-show="!activeSubmenu" v-if="localFilters.annualReturn || localFilters.includeFuturePayments" v-model="localFilters.includeFuturePayments" type="checkbox" class="annual-filter-option" @update:model-value="emitFilters">
				{{ $texts.annual.includeFuture() }}
			</NcCheckboxRadioSwitch>

			<!-- 3. Kategorie Dropdown -->
			<div v-if="!excludedSections.includes('categoryId')" class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'categoryId'">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" multiple :model-value="categoryFilterSelection" :group-kinds="lookupFilterKinds"
					:group-labels="{ income: $texts.filters.allIncomeCategories(), expense: $texts.filters.allExpenseCategories() }" :group-disclosure-label="$texts.filters.chooseCategories()" :active="activeSections.categoryId" :open="activeSubmenu === 'categoryId'" @update:open="activeSubmenu = $event ? 'categoryId' : null" :options="categoryIdChoices"
					:trigger-icon="groupLookupFilters ? filterIcons.categories : null" :label="$texts.analytics.categories()"
					@update:model-value="setLookupSelection('categoryId', $event)" />
			</div>

			<!-- 3. Von / An Dropdown -->
			<div class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'paymentPartnerId'" v-if="!excludedSections.includes('paymentPartnerId') && (section === 'paymentPartnerId' || paymentPartners.length > 0 || partnerFilterSelection.length > 0 || groupLookupFilters)">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" multiple :model-value="partnerFilterSelection" :group-kinds="lookupFilterKinds"
					:group-labels="{ income: $texts.filters.allIncomePartners(), expense: $texts.filters.allExpensePartners() }" :group-disclosure-label="$texts.filters.choosePaymentPartners()" :active="activeSections.paymentPartnerId" :open="activeSubmenu === 'paymentPartnerId'" @update:open="activeSubmenu = $event ? 'paymentPartnerId' : null" :options="paymentPartnerIdChoices"
					:trigger-icon="groupLookupFilters ? filterIcons.partners : null" :label="$texts.analytics.paymentPartners()"
					@update:model-value="setLookupSelection('paymentPartnerId', $event)" />
			</div>

			<!-- 4. Bereiche Dropdown -->
			<div class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'projectId'" v-if="$enableProjects !== false && !excludedSections.includes('projectId') && projects.some(project => !project.is_archived)">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" multiple :model-value="projectFilterSelection" :flat-switches="groupLookupFilters" :active="activeSections.projectId" :open="activeSubmenu === 'projectId'" @update:open="activeSubmenu = $event ? 'projectId' : null" :options="projectIdChoices"
					:trigger-icon="groupLookupFilters ? filterIcons.areas : null" :label="$texts.analytics.areas()"
					@update:model-value="setLookupSelection('projectId', $event)" />
			</div>

			<!-- 4. Status Dropdown -->
			<div class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'status'" v-if="showStatusFilter">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" :model-value="localFilters.status" :active="activeSections.status" :open="activeSubmenu === 'status'" @update:open="activeSubmenu = $event ? 'status' : null" :options="statusChoices"
					:label="$texts.filters.openEntries()"
					@update:model-value="setSelection('status', $event)" />
			</div>

			<!-- 5. Tags Dropdown -->
			<div v-if="!excludedSections.includes('tags') && (tagsChoices.length > 1 || (!groupLookupFilters && $enableFuturePayments))" class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'tags'">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" multiple :native-switches="groupLookupFilters" :model-value="selectedLabels" :active="activeSections.tags" :open="activeSubmenu === 'tags'" @update:open="activeSubmenu = $event ? 'tags' : null" :options="tagsChoices"
					:trigger-icon="groupLookupFilters ? filterIcons.labels : null" :label="$texts.analytics.labels()"
					@update:model-value="setLabels($event)">
					<NcCheckboxRadioSwitch v-if="!groupLookupFilters && !section && $enableFuturePayments" class="annual-filter-option planned-filter-option" type="checkbox"
						:model-value="localFilters.tags === 'future'" @update:model-value="setFutureOnly">
						{{ $texts.filters.futurePayments() }}
					</NcCheckboxRadioSwitch>
				</PaymentFilterSelect>
			</div>

			<!-- 4. Freie Hashtags aus Beschreibungen -->
			<div class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'hashtagId'" v-if="!excludedSections.includes('hashtagId') && hashtagIdChoices.length > 1">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" multiple :native-switches="groupLookupFilters" :model-value="selectedHashtags" :active="activeSections.hashtagId" :open="activeSubmenu === 'hashtagId'" @update:open="activeSubmenu = $event ? 'hashtagId' : null" :options="hashtagIdChoices"
					:trigger-icon="groupLookupFilters ? filterIcons.tags : null" :label="$texts.annual.tags()"
					@update:model-value="setHashtags($event)" />
			</div>

			<!-- 6. Erinnerung Dropdown -->
			<div class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'hasReminder'" v-if="$enableReminders && showReminderFilter">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" :model-value="localFilters.hasReminder" :active="activeSections.hasReminder" :open="activeSubmenu === 'hasReminder'" @update:open="activeSubmenu = $event ? 'hasReminder' : null" :options="hasReminderChoices"
					:trigger-icon="groupLookupFilters ? filterIcons.reminder : null" :label="groupLookupFilters ? $texts.entry.reminder() : $texts.filters.allReminders()"
					@update:model-value="setSelection('hasReminder', $event)" />
			</div>

			<!-- 7. Belege Dropdown -->
			<div class="filter-group" v-show="!activeSubmenu || activeSubmenu === 'hasAttachment'" v-if="$enableReceipts && showAttachmentFilter">
				<PaymentFilterSelect :show-back="!section" :fixed-label="groupLookupFilters" class="table-filter-select" :model-value="localFilters.hasAttachment" :active="activeSections.hasAttachment" :open="activeSubmenu === 'hasAttachment'" @update:open="activeSubmenu = $event ? 'hasAttachment' : null" :options="hasAttachmentChoices"
					:trigger-icon="groupLookupFilters ? filterIcons.receipts : null" :label="groupLookupFilters ? $texts.settings.receipts() : $texts.filters.allReceipts()"
					@update:model-value="setSelection('hasAttachment', $event)" />
			</div>

			<template v-if="showAmount && !activeSubmenu">
				<NcButton variant="tertiary" wide alignment="start" class="cobudget-filter-disclosure payment-amount-trigger"
					:aria-expanded="amountOpen" :aria-description="amountActive ? $texts.filters.active() : undefined" @click="toggleAmountForm">
					<component :is="filterIcons.amount" :size="20" aria-hidden="true" />
					<span class="payment-amount-label">{{ $texts.entry.tableAmount($currency) }}</span>
					<span v-if="amountActive" class="cobudget-filter-dot" aria-hidden="true" />
					<ChevronDownIcon :size="18" :class="{ 'payment-amount-chevron--expanded': amountOpen }" />
				</NcButton>
				<form v-if="amountOpen" ref="amountForm" class="payment-amount-filter" @submit.prevent="applyAmount">
					<div class="payment-amount-bounds cobudget-filter-bounds">
						<div>
							<label class="cobudget-filter-field-label" :for="`${amountFieldId}-from`">{{ $texts.filters.amountFrom() }}</label>
							<NcInputField :id="`${amountFieldId}-from`" v-model="amountMinDraft" inputmode="decimal" label-outside class="cobudget-native-field" :error="amountError" />
						</div>
						<div>
							<label class="cobudget-filter-field-label" :for="`${amountFieldId}-to`">{{ $texts.filters.amountTo() }}</label>
							<NcInputField :id="`${amountFieldId}-to`" v-model="amountMaxDraft" inputmode="decimal" label-outside class="cobudget-native-field" :error="amountError" />
						</div>
					</div>
					<p v-if="amountError" class="payment-amount-error" role="alert">{{ $texts.filters.invalidAmountRange() }}</p>
					<NcButton type="submit" variant="primary">{{ $texts.filters.applyAmount() }}</NcButton>
				</form>
			</template>

			<NcFormBoxSwitch v-if="groupLookupFilters && !section && !activeSubmenu && $enableFuturePayments"
				:model-value="localFilters.tags === 'future'" @update:model-value="setFutureOnly">
				<span class="planned-filter-label">
					<component :is="filterIcons.planned" :size="20" aria-hidden="true" />
					{{ $texts.filters.futurePayments() }}
				</span>
			</NcFormBoxSwitch>

			<div class="filter-actions" v-if="hasActiveFilters && !activeSubmenu">
				<NcButton variant="tertiary" @click="clearFilters">
					{{ showTypeButtons ? $texts.filters.resetAll() : $texts.filters.clearFilters() }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { useId } from 'vue'
import CalendarOutlineIcon from 'vue-material-design-icons/CalendarOutline.vue'
import ShapeOutlineIcon from 'vue-material-design-icons/ShapeOutline.vue'
import AccountOutlineIcon from 'vue-material-design-icons/AccountOutline.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import TagOutlineIcon from 'vue-material-design-icons/TagOutline.vue'
import PoundIcon from 'vue-material-design-icons/Pound.vue'
import BellOutlineIcon from 'vue-material-design-icons/BellOutline.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import CashIcon from 'vue-material-design-icons/Cash.vue'
import CalendarClockOutlineIcon from 'vue-material-design-icons/CalendarClockOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import MinusIcon from 'vue-material-design-icons/Minus.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import ReportPeriodPicker from './ReportPeriodPicker.vue'
import { paymentPickerFilters } from '../services/paymentSummary'
import PaymentFilterSelect from './PaymentFilterSelect.vue'
import { formatAnnualAmountParam, parseAnnualAmountInput } from '../services/annualOverview'
import { selectedPaymentLabels, withPaymentLabels } from '../services/entryListRoute'
import { categoryParentId, categoryPathLabel, sortCategoriesHierarchically } from '../utils/categoryHierarchy'
import { getAppLocale } from '../utils/formatMoney'
import { sortAreasByName } from '../utils/areaSorting'
export default {
	name: 'TableFilters',
	setup() {
		return {
			amountFieldId: useId(),
			filterIcons: {
				period: CalendarOutlineIcon,
				categories: ShapeOutlineIcon,
				partners: AccountOutlineIcon,
				areas: FolderIcon,
				labels: TagOutlineIcon,
				tags: PoundIcon,
				reminder: BellOutlineIcon,
				receipts: PaperclipIcon,
				amount: CashIcon,
				planned: CalendarClockOutlineIcon,
			},
		}
	},
	components: { ReportPeriodPicker, PlusIcon, MinusIcon, ChevronDownIcon, NcFormBoxSwitch, NcInputField, NcCheckboxRadioSwitch, NcRadioGroup, NcRadioGroupButton, NcButton, PaymentFilterSelect },
	emits: ['update:filters'],
	props: {
		section: { type: String, default: '' },
		periodSelection: { type: Object, default: null },
		periodYears: { type: Array, default: () => [] },
		periodActive: Boolean,
		showAmount: { type: Boolean, default: false },
		groupLookupFilters: { type: Boolean, default: false },
		showTypeButtons: { type: Boolean, default: false },
		resetTimeRange: { type: String, default: null },
		excludedSections: { type: Array, default: () => [] },
		labelCounts: { type: Object, default: () => ({}) },
		preservePaymentView: { type: Boolean, default: false },
		showTimeRange: { type: Boolean, default: true },
		filterAvailability: { type: Object, default: null },
		categories: {
			type: Array,
			default: () => []
		},
		projects: {
			type: Array,
			default: () => []
		},
		paymentPartners: {
			type: Array,
			default: () => []
		},
		hashtags: {
			type: Array,
			default: () => []
		},
		initialFilters: {
			type: Object,
			default: () => ({
				search: '',
				type: 'all',
				status: 'active',
				categoryId: null,
				projectId: null,
				paymentPartnerId: null,
				dateFrom: null,
				dateTo: null,
				timeRange: 'all',
				tags: 'all',
				hashtagId: null,
				recurring: 'all',
				hasReminder: 'all',
				hasAttachment: 'all'
			})
		},
		showStatusFilter: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			localFilters: { ...this.initialFilters },
			amountMinDraft: this.initialFilters.amountMin || '',
			amountMaxDraft: this.initialFilters.amountMax || '',
			amountError: false,
			amountOpen: Boolean(this.initialFilters.amountMin || this.initialFilters.amountMax),
			debounceTimer: null,
			activeSubmenu: this.section || null
		}
	},
	computed: {
		amountActive() { return Boolean(this.localFilters.amountMin || this.localFilters.amountMax) },
		activeSections() {
			return {
				type: Boolean(this.localFilters.type && this.localFilters.type !== 'all'),
				timeRange: Boolean(this.localFilters.timeRange && this.localFilters.timeRange !== 'all'),
				categoryId: this.categoryFilterSelection.length > 0,
				paymentPartnerId: this.partnerFilterSelection.length > 0,
				projectId: this.projectFilterSelection.length > 0,
				hashtagId: this.selectedHashtags.length > 0,
				status: this.localFilters.status === 'settled',
				tags: this.selectedLabels.length > 0 || (!this.groupLookupFilters && this.localFilters.tags === 'future'),
				hasReminder: this.localFilters.hasReminder === 'true',
				hasAttachment: ['true', 'false'].includes(this.localFilters.hasAttachment),
			}
		},
		showReminderFilter() {
			return this.filterAvailability?.hasReminders === true || this.localFilters.hasReminder === 'true' || this.activeSubmenu === 'hasReminder'
		},
		showAttachmentFilter() {
			return this.filterAvailability?.hasAttachments === true || ['true', 'false'].includes(this.localFilters.hasAttachment) || this.activeSubmenu === 'hasAttachment'
		},
		typeChoices() { return [
			{ value: 'all', label: this.$texts.filters.incomeAndExpense() },
			{ value: 'income', label: this.$texts.filters.onlyIncome() },
			{ value: 'expense', label: this.$texts.filters.onlyExpense() }
		] },
		timeRangeChoices() { return [
			{ value: 'all', label: this.$texts.filters.allTime() },
			...(this.localFilters.timeRange === 'custom' ? [{ value: 'custom', label: this.$texts.annual.selectedPeriod() }] : []),
			{ value: 'currentMonth', label: this.$texts.filters.currentMonth() },
			{ value: 'lastMonth', label: this.$texts.filters.lastMonth() },
			{ value: 'last30Days', label: this.$texts.filters.last30Days() },
			{ value: 'currentYear', label: this.$texts.filters.currentYear() },
			{ value: 'lastYear', label: this.$texts.filters.lastYear() }
		] },
		lookupFilterKinds() { return this.groupLookupFilters ? (this.$enableIncomes ? ['income', 'expense'] : ['expense']) : [] },
		categoryFilterSelection() { return this.lookupSelection(this.selectedCategories, this.localFilters.narrowCategories) },
		partnerFilterSelection() { return this.lookupSelection(this.selectedPartners, this.localFilters.narrowPartners) },
		selectedCategories() { return this.selectedIds('categoryId', 'narrowCategories') },
		selectedPartners() { return this.selectedIds('paymentPartnerId', 'narrowPartners') },
		projectFilterSelection() {
			const markers = this.groupLookupFilters ? (this.localFilters.narrowProjects || '').split(',')
				.filter(value => value === 'assigned' || /^except-\d+$/.test(value)) : []
			return [...this.selectedProjects, ...markers]
		},
		selectedProjects() { return this.selectedIds('projectId', 'narrowProjects') },
		categoryIdChoices() {
			const options = [
				{ value: null, label: this.$texts.filters.allCategories() },
				{ value: 0, label: this.$texts.annual.noCategory() },
			]
			const types = ['income', 'expense', '']
			for (const type of types) {
				const categories = this.filteredCategories.filter(category => (['income', 'expense'].includes(category.type) ? category.type : '') === type)
				const byId = new Map(categories.map(category => [Number(category.id), category]))
				const typeLabel = type === 'income' ? this.$texts.annual.income() : type === 'expense' ? this.$texts.annual.expenses() : ''
				options.push(...sortCategoriesHierarchically(categories).map((category, index) => {
					const parentId = categoryParentId(category)
					const parent = byId.get(parentId)
					const path = categoryPathLabel({ ...category, parent_name: parent?.name || category.parent_name })
					return { value: Number(category.id), label: category.name, categoryIcon: category.icon || 'Shape',
						child: Boolean(parent), context: parentId && !parent ? category.parent_name : '',
						type, typeLabel, heading: index === 0 ? typeLabel : '',
						accessibleLabel: typeLabel ? `${path} · ${typeLabel}` : path }
				}))
			}
			return this.withSelectedIds(options, this.selectedCategories)
		},
		paymentPartnerIdChoices() { return this.withSelectedIds([
			{ value: null, label: this.$texts.filters.allPaymentPartners() },
			{ value: 0, label: this.$texts.annual.noPartner() },
			...this.filteredPaymentPartners.map(p => ({ value: Number(p.id), label: p.name, type: p.type }))
		], this.selectedPartners) },
		projectIdChoices() { return this.withSelectedIds([
			{ value: null, label: this.$texts.filters.allAreas() },
			{ value: 0, label: this.$texts.annual.withoutArea(), icon: 'without-area' },
			...this.sortedProjects.map(p => ({ value: Number(p.id), label: p.name, icon: 'folder', color: p.color }))
		], this.selectedProjects) },
		selectedHashtags() {
			return [...new Set([this.localFilters.hashtagId, ...(this.localFilters.narrowHashtags || '').split(',')]
				.map(Number).filter(id => Number.isSafeInteger(id) && id > 0))]
		},
		hashtagIdChoices() { return [
			{ value: null, label: this.$texts.filters.allHashtags() },
			...this.hashtags.map(tag => ({ value: Number(tag.id), label: '#' + (tag.displayName || tag.name) })),
			...this.selectedHashtags.filter(id => !this.hashtags.some(tag => Number(tag.id) === id))
				.map(id => ({ value: id, label: `#${id}` })),
		] },
		selectedLabels() { return selectedPaymentLabels(this.localFilters) },
		statusChoices() { return [
			{ value: 'active', label: this.$texts.filters.openEntries() },
			{ value: 'settled', label: this.$texts.filters.settledEntries() },
			{ value: 'all', label: this.$texts.filters.allEntries() }
		] },
		tagsChoices() {
			const labels = [
				['important', this.$enableImportantPayments, this.$texts.labels.important()],
				['review', this.$enableReviewPayments, this.$texts.labels.review()],
				['fixedCost', this.$enableFixedCosts, this.$texts.labels.fixedCosts()],
				['childRelated', this.$enableChildRelated, this.$texts.labels.children()],
				['subscription', this.$enableSubscriptions, this.$texts.labels.subscription()],
				['taxRelevant', this.$enableTaxRelevant, this.$texts.labels.taxRelevant()],
				['insurance', this.$enableInsurance, this.$texts.labels.insurance()],
			]
			return [{ value: null, label: this.$texts.filters.allLabels() },
				...labels.filter(([, enabled]) => enabled)
					.map(([value, , label]) => ({ value, label,
						...(['important', 'review', 'taxRelevant'].includes(value) && this.labelCounts?.[value] !== undefined
							? { count: this.labelCounts[value], accessibleLabel: `${label} (${this.labelCounts[value]})` } : {}) }))]
		},
		hasReminderChoices() { return [
			{ value: 'all', label: this.$texts.filters.allReminders() },
			{ value: 'true', label: this.$texts.filters.onlyWithReminders() }
		] },
		hasAttachmentChoices() { return [
			{ value: 'all', label: this.$texts.filters.allReceipts() },
			{ value: 'true', label: this.$texts.filters.onlyWithReceipt() },
			{ value: 'false', label: this.$texts.filters.onlyWithoutReceipt() }
		] },
		sortedProjects() { return sortAreasByName(this.projects) },
		customPeriodLabel() {
			const format = value => value === null ? '' : new Date(value * 1000).toLocaleDateString(getAppLocale())
			return `${format(this.localFilters.dateFrom)} – ${format(this.localFilters.dateTo)}`
		},
		hasActiveFilters() {
			return this.localFilters.search !== '' ||
				   ((!this.preservePaymentView || this.showTypeButtons) && this.localFilters.type !== 'all') ||
				   this.localFilters.status !== 'active' && this.localFilters.status !== 'all' ||
				   this.localFilters.categoryId !== null ||
				   this.localFilters.projectId != null ||
				   this.localFilters.paymentPartnerId !== null ||
				   this.localFilters.hashtagId !== null ||
				   ((this.showTimeRange || this.resetTimeRange != null) && this.localFilters.timeRange !== (this.resetTimeRange ?? 'all')) ||
				   (this.localFilters.tags !== 'all' && (!this.preservePaymentView || this.showTypeButtons || this.localFilters.tags !== 'future')) ||
				   this.localFilters.hasReminder !== 'all' ||
				   this.localFilters.hasAttachment !== 'all' ||
				   Boolean(this.localFilters.narrowCategories) ||
				   Boolean(this.localFilters.narrowPartners) ||
				   Boolean(this.localFilters.narrowProjects) ||
				   Boolean(this.localFilters.narrowLabels) ||
				   Boolean(this.localFilters.narrowHashtags) ||
				   Boolean(this.localFilters.amountMin) ||
				   Boolean(this.localFilters.amountMax);
		},
		filteredCategories() {
			const categories = this.$enableIncomes === false ? this.categories.filter(category => category.type !== 'income') : this.categories
			if (this.groupLookupFilters || this.localFilters.type === 'all' || !this.localFilters.type) return categories
			return categories.filter(c => c.type === this.localFilters.type || !c.type || this.selectedCategories.includes(Number(c.id)));
		},
		filteredPaymentPartners() {
			const partners = this.$enableIncomes === false ? this.paymentPartners.filter(partner => partner.type !== 'income') : this.paymentPartners
			if (this.groupLookupFilters || this.localFilters.type === 'all' || !this.localFilters.type) return partners
			return partners.filter(p => p.type === this.localFilters.type || !p.type || this.selectedPartners.includes(Number(p.id)));
		}
	},
	watch: {
		initialFilters: {
			handler(newVal) {
				clearTimeout(this.debounceTimer)
				this.debounceTimer = null
				if (newVal.amountMin !== this.localFilters.amountMin || newVal.amountMax !== this.localFilters.amountMax) {
					this.amountMinDraft = newVal.amountMin || ''
					this.amountMaxDraft = newVal.amountMax || ''
					this.amountError = false
				}
				this.localFilters = { ...newVal };
			},
			deep: true
		}
	},
	beforeUnmount() { clearTimeout(this.debounceTimer) },
	methods: {
		selectedIds(key, multiKey) {
			return [...new Set([this.localFilters[key], ...(this.localFilters[multiKey] || '').split(',')]
				.filter(value => value !== null && value !== undefined && value !== '')
				.map(Number).filter(id => Number.isSafeInteger(id) && id >= 0))]
		},
		withSelectedIds(options, selected) {
			return [...options, ...selected.filter(id => !options.some(option => option.value === id))
				.map(id => ({ value: id, label: `#${id}` }))]
		},
		lookupSelection(ids, value, kinds = this.lookupFilterKinds) {
			return [...ids, ...(value || '').split(',').filter(kind => kinds.includes(kind))]
		},
		setLookupSelection(key, values) {
			if (!this.groupLookupFilters) {
				this.setIdSelection(key, values)
				return
			}
			this.localFilters[key] = null
			const multiKey = { categoryId: 'narrowCategories', paymentPartnerId: 'narrowPartners', projectId: 'narrowProjects' }[key]
			this.localFilters[multiKey] = values.join(',')
			if (key === 'categoryId') this.localFilters.includeSubcategories = false
			this.emitFilters()
		},
		setIdSelection(key, values) {
			const multiKey = { categoryId: 'narrowCategories', paymentPartnerId: 'narrowPartners', projectId: 'narrowProjects' }[key]
			if (!multiKey) return
			const selected = [...new Set(values)].filter(value => value !== null && this[`${key}Choices`].some(option => option.value === value)).slice(0, 50)
			const withoutAreaOnly = key === 'projectId' && selected.length === 1 && selected[0] === 0
			this.localFilters[key] = selected.length === 1 && !withoutAreaOnly ? selected[0] : null
			this.localFilters[multiKey] = selected.length > 1 || withoutAreaOnly ? selected.join(',') : ''
			if (key === 'categoryId') this.localFilters.includeSubcategories = false
			this.emitFilters()
		},
		setHashtags(values) {
			const selected = [...new Set(values)].filter(value => value !== null && this.hashtagIdChoices.some(option => option.value === value)).slice(0, 50)
			this.localFilters.hashtagId = selected.length === 1 ? selected[0] : null
			this.localFilters.narrowHashtags = selected.length > 1 ? selected.join(',') : ''
			this.emitFilters()
		},
		setLabels(values) {
			const selected = values.filter(value => this.tagsChoices.some(option => option.value === value))
			this.localFilters = withPaymentLabels(this.localFilters, selected)
			this.emitFilters()
		},
		setFutureOnly(enabled) {
			const labels = this.selectedLabels
			this.localFilters.tags = enabled ? 'future' : 'all'
			this.setLabels(labels)
		},
		setSelection(key, value) {
			if (!this[`${key}Choices`]?.some(option => option.value === value)) return
			this.localFilters[key] = value
			if (key === 'categoryId') this.localFilters.includeSubcategories = false
			this.emitFilters()
		},
		debouncedEmit() {
			clearTimeout(this.debounceTimer);
			this.debounceTimer = setTimeout(() => {
				this.emitFilters();
			}, 300);
		},
		flushSearch() {
			if (!this.debounceTimer) return
			this.emitFilters()
		},
		emitFilters() {
			clearTimeout(this.debounceTimer)
			this.debounceTimer = null
			this.$emit('update:filters', { ...this.localFilters });
		},
		applyPeriodSelection(selection) {
			this.localFilters = paymentPickerFilters(this.localFilters, selection)
			this.emitFilters()
		},
		toggleAmountForm() {
			this.amountOpen = !this.amountOpen
			if (this.amountOpen) {
				this.$nextTick(() => this.$refs.amountForm?.querySelector('[type="submit"]')?.scrollIntoView({ block: 'nearest' }))
			}
		},
		applyAmount() {
			const drafts = [this.amountMinDraft.trim(), this.amountMaxDraft.trim()]
			const [min, max] = drafts.map(value => /^[\d.,\s]+$/.test(value) ? parseAnnualAmountInput(value) : null)
			this.amountError = Boolean((drafts[0] && min === null) || (drafts[1] && max === null)
				|| (min !== null && max !== null && min > max))
			if (this.amountError) return
			this.localFilters.amountMin = formatAnnualAmountParam(min)
			this.localFilters.amountMax = formatAnnualAmountParam(max)
			this.emitFilters()
		},
		clearFilters() {
			this.amountOpen = false
			this.amountMinDraft = ''
			this.amountMaxDraft = ''
			this.amountError = false
			clearTimeout(this.debounceTimer)
			this.localFilters = {
				annualReturn: this.initialFilters.annualReturn || '',
				includeFuturePayments: false,
				includeSubcategories: false,
				search: '',
				type: 'all',
				status: this.initialFilters.status !== undefined ? this.initialFilters.status : 'active',
				categoryId: null,
				projectId: null,
				paymentPartnerId: null,
				dateFrom: null,
				dateTo: null,
				timeRange: 'all',
				tags: 'all',
				hashtagId: null,
				recurring: this.initialFilters.recurring !== undefined ? this.initialFilters.recurring : 'all',
				hasReminder: 'all',
				hasAttachment: 'all',
				narrowCategories: '',
				narrowPartners: '',
				narrowProjects: '',
				narrowLabels: '',
				narrowHashtags: '',
				amountMin: '',
				amountMax: '',
				...(this.preservePaymentView ? { type: this.showTypeButtons ? 'all' : this.localFilters.type, tags: !this.showTypeButtons && this.localFilters.tags === 'future' ? 'future' : 'all', reportFilters: '' } : {}),
				...(!this.showTimeRange ? { timeRange: this.localFilters.timeRange, dateFrom: this.localFilters.dateFrom, dateTo: this.localFilters.dateTo } : {}),
				...(this.resetTimeRange != null ? { timeRange: this.resetTimeRange, dateFrom: null, dateTo: null } : {}),
			};
			this.emitFilters();
		}
	}
}
</script>

<style scoped>
.annual-filter-option { min-width: 0; --color-primary-element-light-hover: var(--cobudget-interaction-hover); }
.custom-period { margin: 0; font-variant-numeric: tabular-nums; }
.table-filters {
	/* The native popover or mobile sheet owns the surface. */
	background: transparent;
	border: none;
	border-radius: var(--border-radius-large, 8px);
	padding: var(--cobudget-filter-menu-padding);
	box-sizing: border-box;
	width: 320px;
	max-width: 100%;
	max-height: min(65vh, 560px);
	overflow-y: auto;
}

.filter-column {
	display: flex;
	flex-direction: column;
	gap: var(--cobudget-content-gap);
	width: 100%;
}

.table-filters--compact .filter-column { gap: 4px; }
.table-filters--compact .search-group :deep(.input-field__input) { padding-inline-start: calc(var(--default-grid-baseline, 4px) * 3 - 2px); }
.payment-amount-trigger :deep(.button-vue__text) { display: flex; align-items: center; width: 100%; gap: 8px; }
.payment-amount-label { flex: 1; text-align: start; }
.planned-filter-label { display: flex; align-items: center; gap: 8px; }
.payment-amount-chevron--expanded { transform: rotate(180deg); }
.payment-amount-filter { display: grid; gap: 4px; margin-block: 4px; }
.payment-amount-error { margin: 0; color: var(--color-error-text); }
.payment-amount-filter > .button-vue { justify-self: end; }
.payment-amount-bounds { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 132px), 1fr)); gap: 8px; }
.filter-group {
	width: 100%;
}

.table-filter-select { width: 100%; }
.planned-filter-option { margin-top: 12px; }
.annual-filter-option.checkbox-radio-switch :deep(.checkbox-radio-switch__content) { max-width: none; box-sizing: border-box; }
.table-filters { --color-background-hover: var(--cobudget-interaction-hover); }
.filter-actions { display: flex; justify-content: flex-end; }
@media (max-width: 768px) {
	.table-filters { --default-clickable-area: var(--cobudget-mobile-touch-size); }
}
</style>
