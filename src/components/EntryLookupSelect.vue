<template>
	<div class="entry-lookup" @keydown.esc.stop>
		<label :for="inputId">{{ label }}</label>
		<NcSelect
			ref="select"
			class="cobudget-native-select entry-lookup-select"
			:input-id="inputId" label-outside :input-label="label"
			:model-value="enteringName ? null : value" :keep-open="!search.trim()" :options="options" label="name"
			:calculate-position="positionEntryLookup" :get-option-key="optionKey" :selectable="isSelectable"
			:filterable="false" :disabled="disabled" :clearable="true"
			:aria-label-clear-selected="clearLabel" :placeholder="enteringName ? $texts.entry.newLookupPlaceholder() : $texts.entry.selectPlaceholder()"
			@search="search = $event" @update:model-value="selectOption"
			@open="$emit('open')" @close="close">
			<template #option="option">
				<span v-if="option.lookupKind === 'heading'" class="entry-lookup-heading">{{ option.name }}</span>
				<NcButton v-else-if="option.lookupKind === 'new'" type="button" variant="tertiary" tabindex="-1">
					<template #icon><PlusIcon :size="20" /></template>
					{{ option.name }}
				</NcButton>
				<span v-else-if="option.lookupKind === 'create'" class="entry-lookup-option">
					<PlusIcon :size="20" /><span class="entry-lookup-text">{{ option.name }}</span>
				</span>
				<span v-else class="entry-lookup-option" :class="{ 'is-child': isNested(option) }">
					<CategoryIcon v-if="isCategory" :icon="option.icon || 'Shape'" :size="18" />
					<span class="entry-lookup-text">
						<small v-if="code(option)">{{ code(option) }}</small>
						<span>{{ option.name }}</span>
						<small v-if="isCategory && option.parent_name && !isNested(option)">{{ option.parent_name }}</small>
					</span>
				</span>
			</template>
			<template #selected-option="option">
				<span class="entry-lookup-option">
					<CategoryIcon v-if="isCategory" :icon="option.icon || 'Shape'" :size="18" />
					<span class="entry-lookup-text">
						<small v-if="code(option)">{{ code(option) }}</small>
						<span>{{ option.name }}</span>
					</span>
				</span>
			</template>
		</NcSelect>
	</div>
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CategoryIcon from './CategoryIcon.vue'
import { positionEntryLookup } from '../utils/entryLookupPosition'
import { categoryParentId } from '../utils/categoryHierarchy'

export default {
	name: 'EntryLookupSelect',
	components: { NcSelect, NcButton, PlusIcon, CategoryIcon },
	props: {
		inputId: { type: String, required: true },
		label: { type: String, required: true },
		clearLabel: { type: String, required: true },
		value: { type: Object, default: null },
		isCategory: Boolean,
		disabled: Boolean,
		getSections: { type: Function, required: true },
		exactMatch: { type: Function, required: true },
	},
	emits: ['select', 'create', 'open', 'close'],
	data: () => ({ search: '', enteringName: false }),
	computed: {
		options() {
			const options = this.getSections(this.search).flatMap(section => [
				...(section.label && section.items.length ? [{ lookupKind: 'heading', name: section.label }] : []),
				...section.items,
			]);
			const lookupName = this.search.trim();
			if (!lookupName && !this.enteringName) {
				options.push({ lookupKind: 'new', name: this.isCategory ? this.$texts.entry.newCategory() : this.$texts.entry.newPaymentPartner() });
			} else if (lookupName && !this.exactMatch(lookupName)) {
				options.push({ lookupKind: 'create', lookupName, name: this.$texts.entry.addLookupValue(lookupName) });
			}
			return options;
		},
		// Like the payment filters, a subcategory directly below its main
		// category (or below a sibling) is indented with a tree connector and
		// needs no repeated parent name. Detached rows, e.g. frequent or search
		// results without their parent, keep the main category as context.
		// NcSelect copies options into slot props, so compare stable keys.
		nestedOptions() {
			const nested = new Set();
			if (!this.isCategory) return nested;
			let previous = null;
			for (const option of this.options) {
				const parentId = option.lookupKind ? null : categoryParentId(option);
				if (parentId !== null && previous && (Number(previous.id) === parentId || (nested.has(this.optionKey(previous)) && categoryParentId(previous) === parentId))) nested.add(this.optionKey(option));
				previous = option.lookupKind ? null : option;
			}
			return nested;
		},
	},
	methods: {
		categoryParentId,
		positionEntryLookup,
		isNested(option) { return this.nestedOptions.has(this.optionKey(option)); },
		code(option) {
			if (!this.$enableAdvancedMasterData) return '';
			return this.isCategory ? option.code : option.number;
		},
		optionKey(option) { return option.lookupKind ? `${option.lookupKind}:${option.name}` : (option.id ?? `draft:${option.name}`); },
		isSelectable(option) { return option.lookupKind !== 'heading'; },
		selectOption(option) {
			if (this.disabled) return;
			if (option?.lookupKind === 'new') {
				this.enteringName = true;
				this.focus({ preventScroll: true });
				return;
			}
			this.enteringName = false;
			if (option?.lookupKind === 'create') {
				// Capture the option's name: NcSelect can clear the search during selection.
				const name = option.lookupName?.trim();
				if (!name) return;
				const match = this.exactMatch(name);
				this.$emit(match ? 'select' : 'create', match || name);
			} else if (!option || this.isSelectable(option)) {
				this.$emit('select', option);
			}
		},
		close() { this.enteringName = false; this.search = ''; this.$emit('close'); },
		focus(options) { this.$refs.select?.$el.querySelector('input')?.focus(options); },
		blur() { this.$refs.select?.$el.querySelector('input')?.blur(); },
		reset() { this.enteringName = false; this.search = ''; this.blur(); },
	},
}
</script>

<style scoped>
.entry-lookup { min-width: 0; }
.entry-lookup > label {
	display: block;
	margin-bottom: var(--default-grid-baseline, 4px);
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	color: var(--color-main-text);
}
.entry-lookup-select { width: 100%; min-width: 0; margin: 0; }
.entry-lookup-option { display: flex; align-items: center; gap: 8px; min-width: 0; }
.entry-lookup-option.is-child::before { content: ''; width: 12px; height: 12px; margin-inline-start: 8px; margin-block-start: -10px; border-inline-start: 1px solid var(--color-border-maxcontrast); border-bottom: 1px solid var(--color-border-maxcontrast); flex-shrink: 0; }
.entry-lookup-option :deep(.category-icon), .entry-lookup-option > .material-design-icon { flex-shrink: 0; }
.entry-lookup-text { display: flex; flex-direction: column; min-width: 0; white-space: normal; overflow-wrap: anywhere; }
.entry-lookup-text small, .entry-lookup-heading { color: var(--color-text-maxcontrast); font-size: var(--cobudget-font-sm); }
.entry-lookup-heading { font-weight: var(--font-weight-element, 500); }
</style>
