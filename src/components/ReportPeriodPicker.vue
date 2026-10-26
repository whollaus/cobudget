<template>
	<NcPopover v-model:shown="shown" class="report-period-picker" placement="bottom-start"
		popover-base-class="report-period-popover" popup-role="dialog" @update:shown="onShown">
		<template #trigger>
			<NcButton variant="tertiary" class="report-period-trigger" :aria-label="`${$texts.annual.selectedPeriod()}: ${label}`">
				<span class="report-period-caption">{{ label }}</span><ChevronDownIcon :size="20" />
			</NcButton>
		</template>
		<div class="report-period-panel" role="dialog" :aria-label="$texts.annual.selectedPeriod()">
			<div class="report-period-options">
				<NcButton v-for="option in options" :key="option.value" variant="tertiary" alignment="start"
					class="report-period-option" :class="{ 'is-selected': draft.timeRange === option.value }"
					:aria-pressed="draft.timeRange === option.value" @click="choose(option.value)">{{ option.label }}</NcButton>
			</div>
			<form v-if="draft.timeRange === 'year' || draft.timeRange === 'custom'" class="report-period-form" @submit.prevent="apply">
				<label v-if="draft.timeRange === 'year'">
					<span>{{ $texts.annual.year() }}</span>
					<select :value="draft.year" :aria-label="$texts.annual.year()" @change="chooseYear">
						<option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
					</select>
				</label>
				<template v-else>
					<label><span>{{ $texts.annual.dateFrom() }}</span><input v-model="dates.dateFrom" type="date" min="1970-01-01" max="9999-12-31" required :aria-label="$texts.annual.dateFrom()"></label>
					<label><span>{{ $texts.annual.dateTo() }}</span><input v-model="dates.dateTo" type="date" min="1970-01-01" max="9999-12-31" required :aria-label="$texts.annual.dateTo()"></label>
				</template>
				<p v-if="validationMessage" class="report-period-error" role="alert">{{ validationMessage }}</p>
				<div class="report-period-actions">
					<NcButton variant="tertiary" @click="shown = false">{{ $texts.common.cancel() }}</NcButton>
					<NcButton variant="primary" type="submit" :disabled="Boolean(validationMessage)">{{ $texts.annual.applyRange() }}</NcButton>
				</div>
			</form>
		</div>
	</NcPopover>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import { annualSelection, parseReportDate, reportSelectionWithDates, reportRangeError } from '../services/annualOverview'

export default {
	name: 'ReportPeriodPicker',
	components: { NcButton, NcPopover, ChevronDownIcon },
	props: {
		selection: { type: Object, required: true },
		years: { type: Array, default: () => [] },
		label: { type: String, required: true },
	},
	emits: ['change'],
	data() {
		return { shown: false, draft: annualSelection(this.selection), dates: { dateFrom: this.selection.dateFrom, dateTo: this.selection.dateTo } }
	},
	computed: {
		options() { return [
			{ value: 'current-year', label: this.$texts.analytics.currentYear() },
			{ value: 'last-year', label: this.$texts.analytics.lastYear() },
			{ value: 'year', label: this.$texts.annual.chooseYear() },
			{ value: 'current-month', label: this.$texts.analytics.currentMonth() },
			{ value: 'last-month', label: this.$texts.analytics.lastMonth() },
			{ value: 'last-12-months', label: this.$texts.analytics.last12Months() },
			{ value: 'custom', label: this.$texts.annual.customRange() },
		] },
		yearOptions() { return [...new Set([...this.years, this.draft.year])].sort((a, b) => b - a) },
		proposed() { return this.draft.timeRange === 'custom' ? reportSelectionWithDates(this.draft, this.dates) : this.draft },
		validationMessage() {
			const error = reportRangeError(this.proposed)
			return error === 'dates' ? this.$texts.annual.invalidDates() : error === 'columns' ? this.$texts.annual.tooManyColumns() : ''
		},
	},
	methods: {
		onShown(shown) {
			if (!shown) return
			this.draft = annualSelection(this.selection)
			this.dates = { dateFrom: this.selection.dateFrom, dateTo: this.selection.dateTo }
		},
		choose(timeRange) {
			const year = parseReportDate(this.selection.dateFrom)?.getFullYear() || this.selection.year
			this.draft = annualSelection({ ...this.selection, year, timeRange, period: undefined, month: 0 })
			this.dates = { dateFrom: this.draft.dateFrom, dateTo: this.draft.dateTo }
			if (timeRange !== 'year' && timeRange !== 'custom') {
				this.$emit('change', this.draft)
				this.shown = false
			}
		},
		chooseYear(event) {
			const year = Number(event.target.value)
			if (this.yearOptions.includes(year)) this.draft = annualSelection({ ...this.draft, year, includeFuturePayments: false })
		},
		apply() {
			if (reportRangeError(this.proposed)) return
			this.$emit('change', this.proposed)
			this.shown = false
		},
	},
}
</script>

<style scoped>
.report-period-picker { display: inline-flex; min-width: 0; max-width: 100%; }
.report-period-trigger { min-width: 0; max-width: 100%; padding-inline: var(--default-grid-baseline, 4px); font: inherit; line-height: inherit; color: inherit; }
.report-period-trigger.button-vue { min-height: 0; padding-block: 0; border-block-width: 0; }
.report-period-trigger :deep(.button-vue__wrapper) { min-width: 0; max-width: 100%; }
.report-period-trigger.button-vue :deep(.button-vue__text) { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); min-width: 0; margin-block: 0; padding-block: 0; overflow: visible; white-space: normal; font: inherit; line-height: inherit; }
.report-period-caption { overflow-wrap: anywhere; text-align: start; }
.report-period-trigger :deep(.material-design-icon) { flex-shrink: 0; }
.report-period-panel { width: min(calc(var(--default-grid-baseline, 4px) * 60), calc(100vw - var(--default-grid-baseline, 4px) * 8)); max-height: calc(100dvh - var(--default-grid-baseline, 4px) * 8); overflow-y: auto; padding: var(--cobudget-menu-padding); color: var(--cobudget-text, var(--color-main-text)); font-size: var(--default-font-size, 15px); font-weight: 400; }
.report-period-options { display: flex; flex-direction: column; gap: var(--default-grid-baseline, 4px); }
.report-period-panel .report-period-option { --font-weight-element: 400; width: 100%; color: inherit; font: inherit; text-align: start; }
.report-period-picker .report-period-trigger:hover:not(:disabled),
.report-period-picker .report-period-trigger:active:not(:disabled),
.report-period-panel .report-period-option.is-selected,
.report-period-panel .report-period-option:hover:not(:disabled),
.report-period-panel .report-period-option:active:not(:disabled) { background-color: var(--cobudget-surface-muted, var(--color-background-hover)); }
.report-period-form { display: flex; flex-direction: column; gap: calc(var(--default-grid-baseline, 4px) * 3); margin-top: calc(var(--default-grid-baseline, 4px) * 3); padding-top: calc(var(--default-grid-baseline, 4px) * 3); border-top: 1px solid var(--color-border); }
.report-period-form label { display: flex; flex-direction: column; gap: var(--default-grid-baseline, 4px); min-width: 0; }
.report-period-form input, .report-period-form select { width: 100%; min-width: 0; margin: 0; color: var(--color-main-text); }
.report-period-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--default-grid-baseline, 4px); }
.report-period-error { margin: 0; color: var(--color-error-text); }
@media (max-width: 768px) {
	:global(.report-period-popover) { z-index: 10000 !important; }
	.report-period-trigger.button-vue { min-height: var(--cobudget-mobile-touch-size, 44px); padding-inline: 0; border-inline-width: 0; }
	.report-period-trigger.button-vue :deep(.button-vue__wrapper > .button-vue__text) { padding-inline: 0; }
}
</style>
