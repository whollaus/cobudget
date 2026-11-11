<template>
	<div class="payment-filter-select" @keydown.esc="onEscape" @keyup.esc="onEscapeKeyup">
		<NcButton v-if="!open" ref="trigger" variant="tertiary" wide alignment="start" class="filter-choice-button" :class="{ 'cobudget-filter-disclosure': fixedLabel }"
			:aria-label="fixedLabel ? label : (multiple ? selectionLabel : (selectedOption?.accessibleLabel || selectedOption?.label || label))" :aria-description="active ? $texts.filters.active() : undefined" :aria-expanded="open" @click="setOpen(true)">
			<span class="filter-choice-content">
				<component :is="triggerIcon" v-if="triggerIcon" :size="20" aria-hidden="true" />
				<CategoryIcon v-else-if="!fixedLabel && selectedOption?.categoryIcon" :icon="selectedOption.categoryIcon" :size="20" aria-hidden="true" />
				<AreaIcon v-else-if="!fixedLabel && selectedOption?.icon" :color="selectedOption.color" :without-area="selectedOption.icon === 'without-area'" />
				<span class="filter-choice-name">{{ fixedLabel ? label : (multiple ? selectionLabel : (selectedOption?.label || label)) }}</span>
				<span v-if="active" class="cobudget-filter-dot" aria-hidden="true" />
				<ChevronRightIcon :size="18" />
			</span>
		</NcButton>
		<template v-else>
			<NcButton v-if="showBack" ref="back" variant="tertiary" wide alignment="start" class="filter-choice-button filter-choice-back"
				:aria-label="$texts.filters.backToFilters()" @click="setOpen(false)">
				<template #icon><ArrowLeftIcon :size="20" /></template>{{ label }}
			</NcButton>
			<slot name="options">
				<div class="filter-choice-options" :class="{ 'filter-choice-options--groups': useSwitches }" :aria-label="label" role="group">
					<template v-for="(option, index) in displayOptions" :key="useSwitches ? option.value : index">
						<template v-if="option.groupSwitch">
							<NcFormBoxSwitch :model-value="groupSelection.includes(option.value)"
								:disabled="groupSelection.length === 1 && groupSelection.includes(option.value)"
								@update:model-value="toggleGroupedOption(option)">
								<span v-if="option.icon" class="filter-choice-content">
									<AreaIcon :color="option.color" :without-area="option.icon === 'without-area'" />
									<span class="filter-choice-name">{{ option.label }}</span>
								</span>
								<template v-else>{{ option.label }}</template>
							</NcFormBoxSwitch>
							<NcButton v-if="option.value !== 0 && groupOptions(option.value).length" variant="tertiary" wide alignment="start"
								class="filter-choice-button cobudget-filter-disclosure" :aria-expanded="expandedKinds.includes(option.value)"
								:aria-label="`${option.label} · ${groupDisclosureLabel}`" @click="toggleGroupDisclosure(option.value, $event.currentTarget)">
								<span class="filter-choice-content">
									<span class="filter-choice-name">{{ groupDisclosureLabel }}</span>
									<ChevronDownIcon :size="18" :class="{ 'filter-group-chevron--expanded': expandedKinds.includes(option.value) }" />
								</span>
							</NcButton>
						</template>
						<template v-else>
							<h4 v-if="option.heading" class="filter-choice-heading">{{ option.heading }}</h4>
							<component :is="useSwitches ? 'NcFormBoxSwitch' : 'NcCheckboxRadioSwitch'" v-if="multiple"
								:class="useSwitches ? 'filter-choice-group-switch' : 'filter-choice-checkbox'"
								:type="useSwitches ? undefined : 'checkbox'" :data-filter-kind="useSwitches ? option.type : undefined"
								:aria-label="option.accessibleLabel || option.label"
								:disabled="nativeSwitches ? option.value === null && modelValue.length === 0 : useSwitches && groupSelection.length === 1 && groupSelection.includes(option.value)"
								:model-value="option.value === null ? modelValue.length === 0 : (useSwitches ? groupSelection : modelValue).includes(option.value)"
								@update:model-value="select(option)">
								<span class="filter-choice-content" :class="{ 'filter-choice-child': option.child }">
									<CategoryIcon v-if="option.categoryIcon" :icon="option.categoryIcon" :size="20" aria-hidden="true" />
									<AreaIcon v-else-if="option.icon" :color="option.color" :without-area="option.icon === 'without-area'" />
									<span class="filter-choice-name">{{ option.label }}<small v-if="option.context">{{ option.context }}</small></span>
									<span v-if="option.count !== undefined" class="filter-choice-count" :title="$texts.filters.labelCountHint()">{{ option.count }}</span>
									<span v-if="option.typeLabel" class="filter-choice-kind" :class="`is-${option.type}`" :title="option.typeLabel" aria-hidden="true">{{ option.type === 'income' ? '+' : '−' }}</span>
								</span>
							</component>
							<NcButton v-else :variant="option.value === modelValue ? 'secondary' : 'tertiary'" wide alignment="start"
								class="filter-choice-button" :class="{ 'filter-choice-child': option.child }"
								:aria-pressed="option.value === modelValue" :aria-label="option.accessibleLabel || option.label" @click="select(option)">
								<span class="filter-choice-content">
									<CategoryIcon v-if="option.categoryIcon" :icon="option.categoryIcon" :size="20" aria-hidden="true" />
									<AreaIcon v-else-if="option.icon" :color="option.color" :without-area="option.icon === 'without-area'" />
									<span class="filter-choice-name">{{ option.label }}<small v-if="option.context">{{ option.context }}</small></span>
									<span v-if="option.count !== undefined" class="filter-choice-count" :title="$texts.filters.labelCountHint()">{{ option.count }}</span>
									<span v-if="option.typeLabel" class="filter-choice-kind" :class="`is-${option.type}`" :title="option.typeLabel" aria-hidden="true">{{ option.type === 'income' ? '+' : '−' }}</span>
									<CheckIcon v-if="option.value === modelValue" :size="18" aria-hidden="true" />
								</span>
							</NcButton>
						</template>
					</template>
				</div>
			</slot>
			<slot />
		</template>
	</div>
</template>

<script>
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CategoryIcon from './CategoryIcon.vue'
import AreaIcon from './AreaIcon.vue'

export default {
	name: 'PaymentFilterSelect',
	components: { NcFormBoxSwitch, ChevronDownIcon, NcCheckboxRadioSwitch, NcButton, ArrowLeftIcon, ChevronRightIcon, CheckIcon, CategoryIcon, AreaIcon },
	props: {
		modelValue: { type: [String, Number, Boolean, Array], default: null },
		multiple: { type: Boolean, default: false },
		active: { type: Boolean, default: false },
		options: { type: Array, required: true },
		label: { type: String, required: true },
		open: { type: Boolean, default: false },
		triggerIcon: { type: Object, default: null },
		fixedLabel: Boolean,
		nativeSwitches: Boolean,
		flatSwitches: Boolean,
		groupKinds: { type: Array, default: () => [] },
		groupLabels: { type: Object, default: () => ({}) },
		groupDisclosureLabel: { type: String, default: '' },
		showBack: { type: Boolean, default: true },
	},
	emits: ['update:modelValue', 'update:open'],
	data() { return { consumedEscape: false, expandedKinds: [] } },
	watch: {
		open: {
			immediate: true,
			handler(open) {
				if (open) this.expandedKinds = this.groupKinds.filter(kind => this.groupOptions(kind)
					.some(option => this.modelValue.includes(option.value)))
			},
		},
	},
	computed: {
		useSwitches() { return this.nativeSwitches || this.flatSwitches || this.groupKinds.length > 0 },
		groupSelection() {
			if (this.flatSwitches) {
				const excluded = this.modelValue.filter(value => typeof value === 'string' && value.startsWith('except-'))
				if (!this.modelValue.length || excluded.length) {
					return this.displayOptions.map(option => option.value).filter(value => !excluded.includes(`except-${value}`))
				}
				if (this.modelValue.includes('assigned')) {
					return this.displayOptions.map(option => option.value).filter(value => value > 0 || this.modelValue.includes(value))
				}
			}
			return this.modelValue.length ? this.modelValue : [0, ...this.groupKinds]
		},
		displayOptions() {
			if (this.flatSwitches) return this.options.filter(option => option.value !== null)
			if (!this.groupKinds.length) return this.options
			return [
				{ ...this.options.find(option => option.value === 0), value: 0, groupSwitch: true },
				...this.groupKinds.flatMap(kind => [
					{ value: kind, label: this.groupLabels[kind], groupSwitch: true },
					...(this.expandedKinds.includes(kind) ? this.groupOptions(kind) : []),
				]),
				...this.options.filter(option => option.value > 0 && !this.groupKinds.includes(option.type)),
			]
		},
		selectionLabel() {
			if ((this.flatSwitches || this.groupKinds.length) && this.modelValue.length) return this.label
			const selected = this.options.filter(option => this.modelValue.includes(option.value))
			if (!selected.length) return this.options.find(option => option.value === null)?.label || this.label
			return selected.length === 1 ? selected[0].label : `${this.label} (${selected.length})`
		},
		selectedOption() {
			const value = this.multiple ? (this.modelValue.length === 1 ? this.modelValue[0] : null) : this.modelValue
			return this.options.find(option => option.value === value) || null
		},
	},
	methods: {
		setOpen(open) {
			this.$emit('update:open', open)
			this.$nextTick(() => {
				this.$refs[open ? 'back' : 'trigger']?.$el?.focus({ preventScroll: open })
				if (open) this.revealOptions(this.$el?.querySelector('.filter-choice-options, .report-period-picker--inline'), this.$el)
			})
		},
		onEscape(event) {
			if (!this.open || this.showBack === false) return
			this.consumedEscape = true
			event.preventDefault()
			event.stopPropagation()
			this.setOpen(false)
		},
		onEscapeKeyup(event) {
			// NcPopover dismisses on keyup; keep it open after going back on keydown.
			if (!this.consumedEscape) return
			this.consumedEscape = false
			event.preventDefault()
			event.stopPropagation()
		},
		groupOptions(kind) {
			return this.options.filter(option => option.type === kind).map(option => ({ ...option, heading: '', typeLabel: '' }))
		},
		toggleGroupDisclosure(kind, trigger) {
			if (this.expandedKinds.includes(kind)) {
				this.expandedKinds = this.expandedKinds.filter(value => value !== kind)
				return
			}
			this.expandedKinds = [...this.expandedKinds, kind]
			this.$nextTick(() => {
				const rows = [...this.$el.querySelectorAll('[data-filter-kind]')].filter(row => row.dataset.filterKind === kind)
				this.revealOptions(trigger, rows.at(-1))
			})
		},
		revealOptions(start, end = start) {
			const panel = start?.closest('.table-filters, .annual-filter-panel')
			if (!panel || !end) return
			const { top, bottom } = panel.getBoundingClientRect()
			const overflow = end.getBoundingClientRect().bottom - Math.min(bottom, window.innerHeight)
			const roomAbove = start.getBoundingClientRect().top - Math.max(top, 0)
			// Keep the start of the selection visible for long lists.
			panel.scrollTop += Math.max(0, Math.min(overflow, roomAbove))
		},
		toggleGroupedOption(option) {
			const { value, type, groupSwitch } = option
			const selection = this.groupSelection
			const next = selection.includes(value)
				? selection.filter(item => item !== value)
				: [...selection.filter(item => groupSwitch
					? !this.groupOptions(value).some(category => category.value === item)
					: item !== type), value]
			if (!next.length || next.filter(item => typeof item === 'number').length > 50) return
			const all = [0, ...this.groupKinds]
			this.$emit('update:modelValue', all.every(item => next.includes(item)) && next.length === all.length ? [] : next)
		},
		select(option) {
			if (this.flatSwitches) {
				const next = this.groupSelection.includes(option.value)
					? this.groupSelection.filter(value => value !== option.value)
					: [...this.groupSelection, option.value]
				if (!next.length) return
				const excluded = this.displayOptions.filter(item => !next.includes(item.value)).map(item => `except-${item.value}`)
				this.$emit('update:modelValue', excluded)
				return
			}
			if (this.groupKinds.length) {
				this.toggleGroupedOption(option)
				return
			}
			if (!option || !this.options.some(item => item.value === option.value)) return
			if (this.multiple) {
				const values = option.value === null ? [] : this.modelValue.includes(option.value)
					? this.modelValue.filter(value => value !== option.value)
					: [...this.modelValue, option.value]
				this.$emit('update:modelValue', values)
				return
			}
			if (option.value !== this.modelValue) this.$emit('update:modelValue', option.value)
			this.setOpen(false)
		},
	},
}
</script>

<style scoped>
.filter-choice-count { min-width: 22px; padding-inline: 6px; border-radius: 12px; background: var(--color-background-dark); color: var(--color-main-text); text-align: center; font-variant-numeric: tabular-nums; font-size: 12px; }
.payment-filter-select { width: 100%; min-width: 0; }
.filter-choice-button.button-vue { --font-weight-element: 400; height: auto; min-height: var(--default-clickable-area); padding-block: 4px; }
.filter-choice-button :deep(.button-vue__wrapper),
.filter-choice-button :deep(.button-vue__text) { width: 100%; min-width: 0; white-space: normal; text-align: start; }
.filter-choice-content { display: flex; align-items: center; gap: 8px; width: 100%; min-width: 0; }
.filter-choice-content > :not(.filter-choice-name) { flex-shrink: 0; }
.filter-choice-content :deep(.category-icon) { margin: 0; }
.filter-choice-name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.filter-choice-name small { display: block; color: var(--color-text-maxcontrast); font-size: 12px; }
.filter-choice-back { margin-bottom: 8px; }
.filter-choice-options { max-height: min(440px, calc(100dvh - 200px)); overflow-y: auto; padding: 4px; margin-inline: -4px; }
/* The outer TableFilters panel owns vertical scrolling for grouped lookup filters. */
.filter-choice-options--groups { max-height: none; overflow: visible; padding-block: 0; display: flex; flex-direction: column; gap: 4px; }
.filter-group-chevron--expanded { transform: rotate(180deg); }
.filter-choice-checkbox { width: 100%; min-width: 0; --color-primary-element-light-hover: var(--cobudget-interaction-hover); }
.filter-choice-checkbox :deep(.checkbox-radio-switch__label) { width: 100%; min-width: 0; }
.filter-choice-checkbox :deep(.checkbox-radio-switch__content) { max-width: none; box-sizing: border-box; }
.filter-choice-group-switch { min-width: 0; }
:is(.filter-choice-child .filter-choice-content, .filter-choice-content.filter-choice-child)::before { content: ''; width: 12px; height: 12px; margin-inline-start: 8px; margin-block-start: -10px; border-inline-start: 1px solid var(--color-border-maxcontrast); border-bottom: 1px solid var(--color-border-maxcontrast); flex-shrink: 0; }
.filter-choice-kind { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 4px; font-weight: 600; }
.filter-choice-kind.is-income { background: var(--cobudget-success-light); color: var(--cobudget-success); }
.filter-choice-kind.is-expense { background: var(--cobudget-error-soft); color: var(--cobudget-error); }

@keyframes cobudget-submenu-in {
	from { opacity: 0; transform: translateY(-2px); }
	to { opacity: 1; transform: translateY(0); }
}
.filter-choice-back,
.filter-choice-options {
	animation: cobudget-submenu-in var(--animation-quick, 100ms) ease;
}
@media (prefers-reduced-motion: reduce) {
	.filter-choice-back,
	.filter-choice-options {
		animation: none !important;
	}
}
.filter-choice-heading { margin: 12px 8px 4px; font-size: var(--font-size, 15px); font-weight: var(--font-weight-element, 500); color: var(--color-text-maxcontrast); }
</style>
