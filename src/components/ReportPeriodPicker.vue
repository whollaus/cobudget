<template>
	<component :is="inline ? 'div' : 'NcPopover'" v-if="!navigationOnly || navigationEnabled"
		:class="inline ? 'report-period-picker--inline' : 'report-period-picker'"
		v-bind="inline ? {} : { shown, placement: 'bottom-start', popoverBaseClass: 'report-period-popover cobudget-menu-popover', popupRole: 'dialog' }"
		@update:shown="shown = $event; onShown($event)">
		<template v-if="!inline" #trigger>
			<NcButton ref="trigger" variant="tertiary" class="report-period-trigger" :class="{ 'app-title-trigger': title, 'app-title-trigger--open': title && shown, 'cobudget-filter-active': calendarMultiple && !currentCalendarYear }" :aria-label="triggerLabel" :title="calendarMultiple ? triggerLabel : undefined" :disabled="disabled">
				<AreaIcon v-if="area" :color="area.color || ''" :size="20" />
				<CalendarIcon v-else-if="calendarMultiple" :size="20" aria-hidden="true" />
				<span class="report-period-caption">{{ title ? triggerLabel : toolbarCaption }}</span><ChevronDownIcon :size="20" />
			</NcButton>
		</template>
		<div ref="panel" class="report-period-panel" :class="{ 'report-period-panel--filter': calendarMultiple, 'report-period-panel--custom': submenu === 'custom' }" :style="{ maxHeight: panelMaxHeight }" :role="inline ? 'group' : 'dialog'" :aria-label="navigationLevel ? $texts.mobileNavigation.label() : $texts.annual.selectedPeriod()" @keydown.esc="onEscape" @keydown="onPanelKeydown">
			<div v-if="navigationLevel" class="report-period-options title-navigation-options">
				<template v-for="(item, index) in navigationEntries" :key="item.id">
					<div class="title-navigation-row" :class="{ 'title-navigation-row--section': item.section }">
						<NcButton variant="tertiary" alignment="start" class="report-period-option cobudget-native-button" :data-navigation="item.id" :aria-current="item.current ? 'page' : undefined" @click="navigate(item)"><span class="title-navigation-label"><AreaIcon v-if="item.kind === 'area'" :color="item.color || ''" :size="20" /><NavigationIcon v-else :name="item.kind === 'budget' ? 'budgets' : item.id" :size="20" />{{ item.label }}</span></NcButton>
						<NcButton v-if="item.period && !forceNavigation" variant="tertiary" class="title-navigation-period" :aria-label="`${item.label}: ${$texts.annual.selectedPeriod()}`" @click="openPeriod(item)">
							<template #icon><ChevronRightIcon :size="20" /></template>
						</NcButton>
					</div>
					<div v-if="!areaNavigation && !area && !budgetNavigation && quickLabels.length && (item.id === 'reminders' || (item.kind === 'payment' && navigationEntries[index + 1]?.section))" class="title-navigation-row--section">
						<NcButton v-for="item in quickLabels" :key="item.id" variant="tertiary" alignment="start" class="report-period-option" @click="shown = false; $emit('quick-label', item.id)">
							<span class="title-navigation-label"><NavigationIcon :name="item.id" :size="20" />{{ item.label }}</span>
						</NcButton>
					</div>
				</template>
			</div>
			<div v-else-if="calendarMultiple" class="report-period-form">
				<div class="report-period-years report-period-switches">
					<template v-for="(year, index) in calendarYearOptions" :key="year">
						<NcButton v-if="index === 2" variant="tertiary" alignment="start" class="report-period-option cobudget-filter-disclosure"
							:aria-expanded="additionalYearsOpen" @click="additionalYearsOpen = !additionalYearsOpen">
							<span>{{ $texts.annual.moreYears() }}</span>
							<ChevronDownIcon :size="18" :class="{ 'report-period-chevron--expanded': additionalYearsOpen }" />
						</NcButton>
						<NcFormBoxSwitch v-if="index < 2 || additionalYearsOpen" :label="String(year)"
							:model-value="calendarYears.includes(String(year))"
							:disabled="calendarYears.includes(String(year)) ? calendarYears.length === 1 : calendarYears.length >= 50"
							@update:model-value="toggleCalendarChoice('calendarYears', year, $event)" />
					</template>
				</div>
				<div class="report-period-switches">
					<NcFormBoxSwitch :label="$texts.annual.allMonths()" :model-value="calendarMonths.length === 0"
						:disabled="calendarMonths.length === 0" @update:model-value="selectAllCalendarMonths" />
					<NcButton variant="tertiary" alignment="start" class="report-period-option cobudget-filter-disclosure"
						:aria-expanded="monthChoicesOpen" @click="monthChoicesOpen = !monthChoicesOpen">
						<span>{{ $texts.annual.chooseMonths() }}</span>
						<ChevronDownIcon :size="18" :class="{ 'report-period-chevron--expanded': monthChoicesOpen }" />
					</NcButton>
					<div v-if="monthChoicesOpen" class="report-period-switches report-period-months" role="group" :aria-label="$texts.annual.months()">
						<NcFormBoxSwitch v-for="(month, index) in monthOptions" :key="index" :label="month"
							:model-value="calendarMonths.includes(String(index + 1))" @update:model-value="toggleCalendarChoice('calendarMonths', index + 1, $event)" />
					</div>
				</div>
				<NcButton variant="tertiary" type="button" alignment="start" class="report-period-option cobudget-filter-disclosure"
					data-period="custom" :aria-expanded="submenu === 'custom'" @click="submenu ? backToOptions() : choose('custom')">
					<span>{{ $texts.annual.customRange() }}</span>
					<ChevronDownIcon :size="18" :class="{ 'report-period-chevron--expanded': submenu === 'custom' }" />
				</NcButton>
			</div>
			<template v-else>
			<NcButton v-if="navigationEnabled && !submenu" ref="navigationBack" variant="tertiary" wide alignment="start" class="report-period-back" @click="backToNavigation">
				<template #icon><ArrowLeftIcon :size="20" /></template>{{ pendingNavigation?.label || title }}
			</NcButton>
			<div v-if="!submenu" class="report-period-options">
				<NcButton v-for="option in options" :key="option.value" :variant="draft.timeRange === option.value ? 'secondary' : 'tertiary'" alignment="start"
					class="report-period-option cobudget-native-button" :class="{ 'is-selected': draft.timeRange === option.value }"
					:data-period="option.value" :aria-pressed="draft.timeRange === option.value" @click="choose(option.value)"><span>{{ option.label }}</span><ChevronRightIcon v-if="option.value === 'year' || option.value === 'custom'" :size="18" /></NcButton>
			</div>
			<template v-else>
				<NcButton ref="backButton" variant="tertiary" wide alignment="start" class="report-period-back" :aria-label="$texts.annual.back()" @click="backToOptions">
					<template #icon><ArrowLeftIcon :size="20" /></template>
					{{ submenu === 'year' ? $texts.annual.chooseYear() : $texts.annual.customRange() }}
				</NcButton>
				<div v-if="submenu === 'year'" class="report-period-options">
					<NcButton v-for="year in yearOptions" :key="year" :variant="draft.year === year ? 'secondary' : 'tertiary'" alignment="start" class="report-period-option cobudget-native-button" :aria-pressed="draft.year === year" @click="chooseYear(year); apply()">{{ year }}</NcButton>
				</div>
			</template>
			</template>
			<form v-if="submenu === 'custom' && !navigationLevel" ref="customForm" class="report-period-form" @submit.prevent="apply">
				<div class="report-period-date-fields cobudget-filter-bounds">
					<div class="report-period-date-field">
						<label class="cobudget-filter-field-label" :for="`${dateFieldId}-from`">{{ $texts.annual.dateFrom() }}</label>
						<NcInputField :id="`${dateFieldId}-from`" v-model="dates.dateFrom" label-outside class="cobudget-native-field"
							type="date" min="1970-01-01" max="9999-12-31" required />
					</div>
					<div class="report-period-date-field">
						<label class="cobudget-filter-field-label" :for="`${dateFieldId}-to`">{{ $texts.annual.dateTo() }}</label>
						<NcInputField :id="`${dateFieldId}-to`" v-model="dates.dateTo" label-outside class="cobudget-native-field"
							type="date" min="1970-01-01" max="9999-12-31" required />
					</div>
				</div>
				<p v-if="validationMessage" class="report-period-error" role="alert">{{ validationMessage }}</p>
				<div class="report-period-actions">
					<NcButton v-if="!calendarMultiple" variant="tertiary" @click="shown = false">{{ $texts.common.cancel() }}</NcButton>
					<NcButton variant="primary" type="submit" :disabled="Boolean(rangeError)">{{ $texts.annual.applyRange() }}</NcButton>
				</div>
			</form>
		</div>
	</component>
	<span v-else class="report-period-static">{{ title }}</span>
</template>

<script>
import { useId } from 'vue'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import { getAppLocale } from '../utils/formatMoney'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import CalendarIcon from 'vue-material-design-icons/CalendarOutline.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import AreaIcon from './AreaIcon.vue'
import NavigationIcon from './NavigationIcon.vue'
import { annualSelection, parseReportDate, reportSelectionWithDates, reportRangeError, reportDate } from '../services/annualOverview'

function pickerSelection(selection) {
	return annualSelection(selection)
}

export default {
	name: 'ReportPeriodPicker',
	setup() { return { dateFieldId: useId() } },
	components: { CalendarIcon, NcInputField, NcButton, NcFormBoxSwitch, NcPopover, ChevronDownIcon, ArrowLeftIcon, ChevronRightIcon, AreaIcon, NavigationIcon },
	inject: { appNavigation: { default: null } },
	props: {
		selection: { type: Object, default: () => ({ timeRange: 'current-year' }) },
		title: { type: String, default: '' },
		periodEnabled: { type: Boolean, default: true },
		navigationOnly: { type: Boolean, default: false },
		forceNavigation: { type: Boolean, default: false },
		areaNavigation: { type: Boolean, default: false },
		budgetNavigation: { type: Boolean, default: false },
		budgetGoals: { type: Array, default: () => [] },
		currentBudgetId: { type: [String, Number], default: null },
		quickLabels: { type: Array, default: () => [] },
		disabled: { type: Boolean, default: false },
		area: { type: Object, default: null },
		years: { type: Array, default: () => [] },
		label: { type: String, required: true },
		allowAll: { type: Boolean, default: true },
		calendarMultiple: Boolean,
		singleCalendarPeriod: Boolean,
		reportCalendarSelection: Boolean,
		inline: Boolean,
		showLastYear: { type: Boolean, default: true },
	},
	emits: ['change', 'quick-label'],
	data() {
		return { additionalYearsOpen: false, monthChoicesOpen: false, calendarYears: [], calendarMonths: [], shown: false, panelMaxHeight: null, navigationLevel: false, pendingNavigation: null, submenu: null, draft: pickerSelection(this.selection), dates: { dateFrom: this.selection.dateFrom, dateTo: this.selection.dateTo } }
	},
	mounted() {
		if (this.inline) this.onShown(true)
		else window.addEventListener('resize', this.updatePanelHeight)
	},
	beforeUnmount() { window.removeEventListener('resize', this.updatePanelHeight) },
	watch: {
		disabled(value) { if (value) this.shown = false },
		shown(value, previous) { if (!value && previous) this.returnFocus() },
		'appNavigation.open'() { this.shown = false },
		$route(to, from) {
			if (to.fullPath === from.fullPath) return
			if (this.calendarMultiple && !this.submenu && to.path === from.path) {
				if (this.singleCalendarPeriod) {
					const applied = annualSelection(to.query), selected = this.calendarPeriodSelection()
					if (applied.dateFrom === selected.dateFrom && applied.dateTo === selected.dateTo) return
				} else if (['calendarSelection', 'calendar-selection'].includes(to.query.timeRange)
					&& to.query.periodYears === this.calendarYears.join(',')
					&& (to.query.periodMonths || '') === this.calendarMonths.join(',')) return
			}
			this.shown = false
		},
	},
	computed: {
		navigationEnabled() { return Boolean(this.title && this.appNavigation && (this.forceNavigation || this.areaNavigation || this.budgetNavigation || !this.appNavigation.open || this.area)) },
		navigationEntries() {
			const entries = this.appNavigation?.entries || []
			if (this.budgetNavigation) {
				const goals = this.budgetGoals.map(goal => ({ id: `budget:${goal.id}`, budgetId: goal.id, kind: 'budget', label: goal.name, current: String(goal.id) === String(this.currentBudgetId) }))
				return entries.flatMap((item, index) => item.id === 'budgets' ? [item, ...goals]
					: [{ ...item, section: item.section || (goals.length > 0 && entries[index - 1]?.id === 'budgets') }])
			}
			if (!this.areaNavigation && !this.area) return entries
			const areas = [...(this.appNavigation?.areas || [])]
			if (this.area && !areas.some(item => String(item.areaId) === String(this.area.id))) areas.push({ id: `area:${this.area.id}`, areaId: this.area.id, kind: 'area', label: this.area.name, color: this.area.color, current: true })
			return entries.flatMap((item, index) => {
				if (item.id === 'projects') return [item, ...areas]
				return [{ ...item, section: item.section || (areas.length > 0 && entries[index - 1]?.id === 'projects') }]
			})
		},
		currentCalendarYear() {
			const currentYear = new Date().getFullYear()
			const selection = pickerSelection(this.selection)
			return selection.timeRange === 'calendar-selection'
				? selection.periodYears.length === 1 && selection.periodYears[0] === currentYear && selection.periodMonths.length === 0
				: selection.timeRange !== 'all' && selection.dateFrom === `${currentYear}-01-01` && selection.dateTo === `${currentYear}-12-31`
		},
		toolbarCaption() {
			if (!this.calendarMultiple) return this.label
			return this.currentCalendarYear ? String(new Date().getFullYear()) : this.$texts.analytics.period()
		},
		triggerLabel() { return this.title ? `${this.title}${this.periodEnabled ? ` ${this.label}` : ''}` : `${this.$texts.annual.selectedPeriod()}: ${this.label}` },
		monthOptions() {
			const formatter = new Intl.DateTimeFormat(getAppLocale(), { month: 'long' })
			return Array.from({ length: 12 }, (_, month) => formatter.format(new Date(2020, month, 1)))
		},
		options() { return [
			{ value: 'current-year', label: this.$texts.analytics.currentYear() },
			...(this.showLastYear ? [{ value: 'last-year', label: this.$texts.analytics.lastYear() }] : []),
			{ value: 'year', label: this.$texts.annual.chooseYear() },
			{ value: 'current-month', label: this.$texts.analytics.currentMonth() },
			{ value: 'last-month', label: this.$texts.analytics.lastMonth() },
			{ value: 'last-12-months', label: this.$texts.analytics.last12Months() },
			...(this.allowAll ? [{ value: 'all', label: this.$texts.filters.allTime() }] : []),
			{ value: 'custom', label: this.$texts.annual.customRange() },
		] },
		yearOptions() { return [...new Set([...this.years, ...(this.navigationEnabled ? this.appNavigation.years || [] : []), this.draft.year])].sort((a, b) => b - a) },
		calendarYearOptions() {
			const current = new Date().getFullYear()
			const recent = [current, current - 1]
			const additional = [...new Set([...this.yearOptions, ...(this.selection.periodYears || [])])]
				.filter(year => !recent.includes(year)).sort((a, b) => b - a)
			return [...recent, ...additional]
		},
		proposed() { return this.draft.timeRange === 'custom' ? reportSelectionWithDates(this.draft, this.dates) : this.draft },
		rangeError() { return reportRangeError(this.proposed) },
		validationMessage() {
			if (!this.dates.dateFrom || !this.dates.dateTo) return ''
			const error = this.rangeError
			return error === 'dates' ? this.$texts.annual.invalidDates() : error === 'columns' ? this.$texts.annual.tooManyColumns() : ''
		},
	},
	methods: {
		updatePanelHeight() {
			if (typeof window === 'undefined') return
			const trigger = this.$refs.trigger?.$el
			if (!trigger) return
			// Keep even a long navigation list inside a short mobile viewport.
			this.panelMaxHeight = `${Math.max(44, window.innerHeight - trigger.getBoundingClientRect().bottom - 24)}px`
		},
		returnFocus() {
			const active = document.activeElement
			if (!this.$refs.panel?.contains(active) && active !== document.body) return
			this.$nextTick(() => this.$refs.trigger?.$el?.focus({ preventScroll: true }))
		},
		focusPanel() {
			this.$nextTick(() => this.$refs.panel?.querySelector('button')?.focus({ preventScroll: true }))
		},
		onPanelKeydown(event) {
			if (event.target.closest('input, textarea') || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return
			const buttons = [...this.$refs.panel.querySelectorAll('button:not(:disabled)')]
			const index = buttons.indexOf(document.activeElement)
			const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length
			event.preventDefault()
			buttons[next]?.focus({ preventScroll: true })
		},
		onEscape(event) {
			if (this.inline) return
			event.stopPropagation()
			event.preventDefault()
			this.escapeLevel()
		},
		escapeLevel() {
			if (this.submenu) this.backToOptions()
			else if (this.navigationEnabled && !this.navigationLevel) this.backToNavigation()
			else this.shown = false
		},
		navigate(item) {
			if (!item.current) this.appNavigation.navigate(item, item.period ? pickerSelection(this.selection) : null)
			this.shown = false
		},
		openPeriod(item) {
			this.pendingNavigation = item
			this.navigationLevel = false
			this.focusPanel()
		},
		backToNavigation() {
			const id = this.pendingNavigation?.id
			this.pendingNavigation = null
			this.navigationLevel = true
			this.$nextTick(() => [...this.$refs.panel.querySelectorAll('[data-navigation]')].find(el => el.dataset.navigation === id)?.focus({ preventScroll: true }))
		},
		complete(selection) {
			if (this.pendingNavigation && !this.pendingNavigation.current) this.appNavigation.navigate(this.pendingNavigation, selection)
			else this.$emit('change', selection)
			this.shown = false
		},
		onShown(shown) {
			if (!shown) return
			this.updatePanelHeight()
			this.navigationLevel = this.navigationEnabled
			this.pendingNavigation = null
			this.submenu = null
			this.draft = pickerSelection(this.selection)
			this.dates = { dateFrom: this.selection.dateFrom, dateTo: this.selection.dateTo }
			if (this.calendarMultiple) {
				if (['custom', 'last-12-months'].includes(this.selection.timeRange)) {
					this.submenu = 'custom'
					this.draft = pickerSelection({ ...this.selection, timeRange: 'custom' })
				}
				const from = parseReportDate(this.draft.dateFrom), to = parseReportDate(this.draft.dateTo)
				const fullMonth = from && to && from.getFullYear() === to.getFullYear() && from.getMonth() === to.getMonth()
					&& from.getDate() === 1 && to.getDate() === new Date(to.getFullYear(), to.getMonth() + 1, 0).getDate()
				this.calendarYears = (this.selection.periodYears || [(this.singleCalendarPeriod || this.reportCalendarSelection) && from ? from.getFullYear() : this.selection.year]).map(String)
				this.calendarMonths = (this.selection.periodMonths || ((this.singleCalendarPeriod || this.reportCalendarSelection) && fullMonth ? [from.getMonth() + 1] : [])).map(String)
				if ((this.singleCalendarPeriod || this.reportCalendarSelection) && fullMonth) this.submenu = null
				this.monthChoicesOpen = this.calendarMonths.length > 0
				this.additionalYearsOpen = this.calendarYearOptions.slice(2).some(year => this.calendarYears.includes(String(year)))
				if (!this.inline) this.$nextTick(() => this.$refs.panel?.querySelector('.report-period-years button:not(:disabled), .report-period-years input:not(:disabled)')?.focus({ preventScroll: true }))
			} else {
				this.focusPanel()
			}
		},
		backToOptions() {
			const previous = this.submenu
			this.submenu = null
			this.$nextTick(() => this.$refs.panel?.querySelector(`[data-period="${previous}"]`)?.focus())
		},
		choose(timeRange) {
			if (timeRange === 'all' && this.allowAll) {
				this.complete(annualSelection({ ...this.selection, timeRange: 'all', year: new Date().getFullYear(), period: undefined, dateFrom: undefined, dateTo: undefined }))
				return
			}
			const year = parseReportDate(this.selection.dateFrom)?.getFullYear() || this.selection.year
			this.draft = annualSelection({ ...this.selection, year, timeRange, period: undefined, month: 0 })
			this.dates = { dateFrom: this.draft.dateFrom, dateTo: this.draft.dateTo }
			if (timeRange === 'year' || timeRange === 'custom') {
				this.submenu = timeRange
				if (this.calendarMultiple) {
					this.$nextTick(() => this.$refs.customForm?.querySelector('[type="submit"]')?.scrollIntoView({ block: 'nearest' }))
				} else {
					this.$nextTick(() => this.$refs.backButton?.$el?.focus())
				}
			} else {
				this.complete(this.draft)
			}
		},
		toggleCalendarChoice(field, value, checked) {
			const key = String(value)
			this[field] = checked
				? (this.singleCalendarPeriod ? [key] : [...this[field], key]).sort((a, b) => Number(a) - Number(b))
				: this[field].filter(item => item !== key)
			if (this.calendarMonths.length === 12) this.calendarMonths = []
			this.applyCalendar()
		},
		selectAllCalendarMonths(checked) {
			if (!checked) return
			this.monthChoicesOpen = false
			this.calendarMonths = []
			this.applyCalendar()
		},
		calendarPeriodSelection() {
			const year = Number(this.calendarYears[0]), month = Number(this.calendarMonths[0])
			return annualSelection({ ...this.selection, year, timeRange: month ? 'custom' : 'year',
				dateFrom: reportDate(new Date(year, month ? month - 1 : 0, 1)),
				dateTo: reportDate(new Date(year, month || 12, 0)) })
		},
		applyCalendar() {
			this.submenu = null
			this.$emit('change', this.singleCalendarPeriod ? this.calendarPeriodSelection()
				: { ...(this.reportCalendarSelection ? this.selection : {}), timeRange: 'calendar-selection', periodYears: this.calendarYears.map(Number), periodMonths: this.calendarMonths.map(Number) })
		},
		chooseYear(value) {
			const year = Number(value)
			if (this.yearOptions.includes(year)) this.draft = annualSelection({ ...this.draft, year, includeFuturePayments: false })
		},
		apply() {
			if (reportRangeError(this.proposed)) return
			this.complete(this.proposed)
		},
	},
}
</script>

<style scoped>
.report-period-picker { display: inline-flex; min-width: 0; max-width: 100%; }
.report-period-trigger { min-width: 0; max-width: 100%; padding-inline: var(--default-grid-baseline, 4px); font: inherit; line-height: inherit; color: inherit; }
.report-period-trigger.button-vue { min-height: var(--default-clickable-area); padding-block: 0; border-block-width: 0; }
.report-period-trigger :deep(.button-vue__wrapper) { min-width: 0; max-width: 100%; }
.report-period-trigger.button-vue :deep(.button-vue__text) { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); min-width: 0; margin-block: 0; padding-block: 0; overflow: visible; white-space: normal; font: inherit; line-height: inherit; }
.report-period-caption { overflow-wrap: anywhere; text-align: start; }
.report-period-trigger :deep(.material-design-icon) { flex-shrink: 0; }
.report-period-panel { --color-background-hover: color-mix(in srgb, var(--color-primary-element) 8%, transparent); width: min(calc(var(--default-grid-baseline, 4px) * 60), calc(100vw - var(--default-grid-baseline, 4px) * 8)); max-height: calc(100dvh - var(--default-grid-baseline, 4px) * 8); overflow-y: auto; padding: var(--cobudget-menu-padding); color: var(--cobudget-text, var(--color-main-text)); font-size: var(--default-font-size, 15px); font-weight: 400; }
.report-period-picker--inline .report-period-panel { width: 100%; max-height: none; padding: 0; overflow: visible; }
.report-period-panel--custom { width: min(var(--report-period-custom-width, 304px), calc(100vw - var(--default-grid-baseline, 4px) * 8)); }
.report-period-panel--filter { --report-period-custom-width: 320px; width: min(320px, calc(100vw - var(--default-grid-baseline, 4px) * 8)); padding: var(--cobudget-filter-menu-padding); }
.report-period-option :deep(.button-vue__text) { display: flex; align-items: center; justify-content: space-between; gap: var(--default-grid-baseline); width: 100%; }
.report-period-back { margin-bottom: calc(var(--default-grid-baseline) * 2); }
.report-period-options { display: flex; flex-direction: column; }
.report-period-panel .report-period-option { --font-weight-element: 400; width: 100%; color: inherit; font: inherit; text-align: start; }
.report-period-form { display: flex; flex-direction: column; gap: calc(var(--default-grid-baseline, 4px) * 3); padding: calc(var(--default-grid-baseline, 4px) * 2); }
.report-period-panel--filter .report-period-form { padding: 0; }
.report-period-switches { display: grid; gap: var(--default-grid-baseline, 4px); }
.report-period-months { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.report-period-months > * { min-width: 0; overflow-wrap: anywhere; }
.report-period-chevron--expanded { transform: rotate(180deg); }
.report-period-panel--filter .report-period-form + form { margin-block-start: var(--default-grid-baseline, 4px); }
.report-period-panel--filter .report-period-form { gap: var(--default-grid-baseline, 4px); }
.report-period-date-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 132px), 1fr)); gap: calc(var(--default-grid-baseline, 4px) * 2); }
.report-period-date-field { min-width: 0; }
.report-period-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--default-grid-baseline, 4px); }
.report-period-error { margin: 0; color: var(--color-error-text); }
@media (max-width: 768px) {
	.report-period-panel { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); }
	:global(.report-period-popover) { z-index: 10000 !important; }
	.report-period-trigger.button-vue { min-height: var(--cobudget-mobile-touch-size, 44px); padding-inline: 0; border-inline-width: 0; }
	.report-period-trigger.button-vue :deep(.button-vue__wrapper > .button-vue__text) { padding-inline: 0; }
}
</style>

<style scoped>
.app-title-trigger.button-vue {
	font-size: var(--default-font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	line-height: 1.5;
}
.app-title-trigger :deep(.button-vue__text) { font: inherit; }
/* Files uses the native tertiary tint when closed and a neutral surface when open. */
.report-period-picker .app-title-trigger.button-vue.app-title-trigger--open:not(:disabled),
.report-period-panel .title-navigation-options .button-vue:is(:hover, :active, [aria-current="page"]):not(:disabled) {
	background-color: var(--cobudget-surface-muted, var(--color-background-hover));
}
.title-navigation-row { display: flex; align-items: stretch; }
.title-navigation-row .report-period-option { flex: 1; min-width: 0; }
.title-navigation-row--section { border-block-start: 1px solid var(--color-border); margin-block-start: var(--default-grid-baseline, 4px); padding-block-start: var(--default-grid-baseline, 4px); }
.title-navigation-period { flex-shrink: 0; }
.title-navigation-options { min-width: 0; }
</style>

<style scoped>
.title-navigation-label { display: flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 2); min-width: 0; overflow-wrap: anywhere; }
</style>
