<template>
	<div class="annual-filter-cluster">
		<ul v-if="chips.length" class="annual-filter-chips">
			<li v-for="chip in chips" :key="chip.key" class="annual-filter-chip">
				<span class="annual-filter-chip-label">{{ chip.label }}</span>
				<button type="button" class="annual-filter-remove" :aria-label="$texts.annual.removeFilter()" :title="$texts.annual.removeFilter()" @click="$emit('clear', chip.key)">
					<CloseIcon :size="18" />
				</button>
			</li>
		</ul>
		<NcPopover class="annual-filter-popover" placement="bottom-start" @update:shown="onPopoverShown" @after-show="scheduleFit">
			<template #trigger>
				<span ref="trigger" class="annual-filter-trigger">
					<NcButton variant="tertiary" class="cobudget-toolbar-icon-button" :class="{ 'is-active': chips.length }" :aria-label="$texts.annual.filterPayments()" :title="$texts.annual.filterPayments()">
						<template #icon><FilterVariantIcon :size="20" /></template>
					</NcButton>
				</span>
			</template>
			<div ref="panel" class="annual-filter-panel" :style="panelStyle">
				<section v-if="sections.categories && categoryOptions.length" class="annual-filter-section">
					<button type="button" class="annual-filter-toggle" :class="{ 'is-quiet': quiet === 'categories' }" :aria-expanded="open.categories" @mousedown.prevent @mouseleave="clearQuiet('categories')" @click="toggleSection('categories', $event)">
						<span class="annual-filter-heading">{{ $texts.analytics.category() }}</span>
						<ChevronDownIcon v-if="open.categories" :size="18" /><ChevronRightIcon v-else :size="18" />
					</button>
					<div v-show="open.categories" class="annual-filter-body">
						<input v-if="categoryOptions.length > 8" v-model="categoryQuery" type="search" class="annual-filter-search" :aria-label="$texts.filters.search()" :placeholder="$texts.filters.search()">
						<div class="annual-filter-options">
							<label v-for="option in visibleCategories" :key="option.id" class="annual-filter-option" :class="{ child: option.child }">
								<input type="checkbox" :checked="normalized.categories.includes(option.id)" @change="toggle('categories', option.id)">
								<span v-if="showKind(option)" class="annual-filter-kind" :class="`is-${option.type}`" :title="kindLabel(option.type)">
									<span aria-hidden="true">{{ option.type === 'income' ? '+' : '−' }}</span>
									<span class="visually-hidden">{{ kindLabel(option.type) }}</span>
								</span>
								<span>{{ option.label }}</span>
							</label>
						</div>
					</div>
				</section>
				<section v-if="sections.partners && partnerOptions.length" class="annual-filter-section">
					<button type="button" class="annual-filter-toggle" :class="{ 'is-quiet': quiet === 'partners' }" :aria-expanded="open.partners" @mousedown.prevent @mouseleave="clearQuiet('partners')" @click="toggleSection('partners', $event)">
						<span class="annual-filter-heading">{{ $texts.analytics.paymentPartner() }}</span>
						<ChevronDownIcon v-if="open.partners" :size="18" /><ChevronRightIcon v-else :size="18" />
					</button>
					<div v-show="open.partners" class="annual-filter-body">
						<input v-if="partnerOptions.length > 8" v-model="partnerQuery" type="search" class="annual-filter-search" :aria-label="$texts.filters.search()" :placeholder="$texts.filters.search()">
						<div class="annual-filter-options">
							<label v-for="option in visiblePartners" :key="option.id" class="annual-filter-option">
								<input type="checkbox" :checked="normalized.partners.includes(option.id)" @change="toggle('partners', option.id)">
								<span v-if="showKind(option)" class="annual-filter-kind" :class="`is-${option.type}`" :title="kindLabel(option.type)">
									<span aria-hidden="true">{{ option.type === 'income' ? '+' : '−' }}</span>
									<span class="visually-hidden">{{ kindLabel(option.type) }}</span>
								</span>
								<span>{{ option.label }}</span>
							</label>
						</div>
					</div>
				</section>
				<section class="annual-filter-section">
					<button type="button" class="annual-filter-toggle" :class="{ 'is-quiet': quiet === 'amount' }" :aria-expanded="open.amount" @mousedown.prevent @mouseleave="clearQuiet('amount')" @click="toggleSection('amount', $event)">
						<span class="annual-filter-heading">{{ $texts.entry.amount() }}</span>
						<ChevronDownIcon v-if="open.amount" :size="18" /><ChevronRightIcon v-else :size="18" />
					</button>
					<div v-show="open.amount" class="annual-filter-body">
						<div class="annual-filter-amounts">
							<label>
								<span>{{ $texts.annual.atLeast() }}</span>
								<input ref="amountMin" v-model="amountMinText" type="text" inputmode="decimal" @input="scheduleAmount">
							</label>
							<label>
								<span>{{ $texts.annual.atMost() }}</span>
								<input ref="amountMax" v-model="amountMaxText" type="text" inputmode="decimal" @input="scheduleAmount">
							</label>
						</div>
					</div>
				</section>
				<section v-if="sections.labels && labelOptions.length" class="annual-filter-section">
					<button type="button" class="annual-filter-toggle" :class="{ 'is-quiet': quiet === 'labels' }" :aria-expanded="open.labels" @mousedown.prevent @mouseleave="clearQuiet('labels')" @click="toggleSection('labels', $event)">
						<span class="annual-filter-heading">{{ $texts.analytics.labels() }}</span>
						<ChevronDownIcon v-if="open.labels" :size="18" /><ChevronRightIcon v-else :size="18" />
					</button>
					<div v-show="open.labels" class="annual-filter-body">
						<div class="annual-filter-options">
							<label v-for="option in labelOptions" :key="option.key" class="annual-filter-option">
								<input type="checkbox" :checked="normalized.labels.includes(option.key)" @change="toggle('labels', option.key)">
								<span>{{ option.label }}</span>
							</label>
						</div>
					</div>
				</section>
				<section v-if="sections.hashtags && hashtagOptions.length" class="annual-filter-section">
					<button type="button" class="annual-filter-toggle" :class="{ 'is-quiet': quiet === 'hashtags' }" :aria-expanded="open.hashtags" @mousedown.prevent @mouseleave="clearQuiet('hashtags')" @click="toggleSection('hashtags', $event)">
						<span class="annual-filter-heading">{{ $texts.annual.tags() }}</span>
						<ChevronDownIcon v-if="open.hashtags" :size="18" /><ChevronRightIcon v-else :size="18" />
					</button>
					<div v-show="open.hashtags" class="annual-filter-body">
						<input v-if="hashtagOptions.length > 8" v-model="hashtagQuery" type="search" class="annual-filter-search" :aria-label="$texts.filters.search()" :placeholder="$texts.filters.search()">
						<div class="annual-filter-options">
							<label v-for="option in visibleHashtags" :key="option.id" class="annual-filter-option">
								<input type="checkbox" :checked="normalized.hashtags.includes(option.id)" @change="toggle('hashtags', option.id)">
								<span>{{ option.label }}</span>
							</label>
						</div>
					</div>
				</section>
			</div>
		</NcPopover>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import FilterVariantIcon from 'vue-material-design-icons/FilterVariant.vue'
import { annualFilterChips, annualFilterPanelMaxHeight, annualFilterSections, annualSelection, formatAnnualAmountParam, parseAnnualAmountInput } from '../services/annualOverview'
import { categoryChoiceLabel, categoryParentId, sortCategoriesHierarchically } from '../utils/categoryHierarchy'

export default {
	name: 'AnnualOverviewFilters',
	components: { NcButton, NcPopover, ChevronDownIcon, ChevronRightIcon, CloseIcon, FilterVariantIcon },
	props: {
		selection: { type: Object, required: true },
		categories: { type: Array, default: () => [] },
		partners: { type: Array, default: () => [] },
		hashtags: { type: Array, default: () => [] },
		categoryIds: { type: Array, default: () => [] },
		partnerIds: { type: Array, default: () => [] },
		labelKeys: { type: Array, default: () => [] },
	},
	emits: ['change', 'clear'],
	data() {
		return {
			categoryQuery: '',
			partnerQuery: '',
			hashtagQuery: '',
			amountMinText: '',
			amountMaxText: '',
			amountTimer: null,
			disposed: false,
			quiet: '',
			popoverShown: false,
			panelMaxHeight: '',
			viewportBound: false,
			fitFrame: 0,
			fitPasses: 0,
			open: { categories: false, partners: false, amount: false, labels: false, hashtags: false },
		}
	},
	computed: {
		normalized() { return annualSelection(this.selection) },
		sections() { return annualFilterSections(this.normalized.grouping) },
		categoryNames() { return new Map(this.categoryOptions.map(option => [option.id, option.label])) },
		partnerNames() { return new Map(this.partnerOptions.map(option => [option.id, option.label])) },
		categoryTypes() { return this.kindTypes(this.categoryOptions) },
		partnerTypes() { return this.kindTypes(this.partnerOptions) },
		hashtagNames() { return new Map(this.hashtagOptions.map(option => [option.id, option.name])) },
		labelNames() { return Object.fromEntries(this.labelOptions.map(option => [option.key, option.label])) },
		chips() {
			return annualFilterChips(this.normalized, {
				categoryNames: this.categoryNames,
				partnerNames: this.partnerNames,
				categoryTypes: this.categoryTypes,
				partnerTypes: this.partnerTypes,
				hashtagNames: this.hashtagNames,
				labelNames: this.labelNames,
				noCategory: this.$texts.annual.noCategory(),
				noPartner: this.$texts.annual.noPartner(),
				or: this.$texts.annual.or(),
				atLeast: this.$texts.annual.atLeast(),
				atMost: this.$texts.annual.atMost(),
				formatMoney: cents => this.$formatMoneyFromCents(cents),
			})
		},
		categoryOptions() {
			const used = this.usedIds(this.categoryIds, this.normalized.categories)
			const options = this.kindGroups(this.categories, used).flatMap(group => {
				const sorted = sortCategoriesHierarchically(group.items)
				const visibleIds = new Set(sorted.map(category => Number(category.id)))
				return sorted.map(category => {
					const child = visibleIds.has(categoryParentId(category))
					const name = String(category.name || '').trim()
					return {
						id: Number(category.id),
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
			const used = new Set([...(this.labelKeys || []), ...this.normalized.labels])
			return options.filter(([key, enabled]) => enabled !== false && used.has(key)).map(([key, , label]) => ({ key, label }))
		},
		visibleCategories() { return this.visibleOptions(this.categoryOptions, this.categoryQuery) },
		visiblePartners() { return this.visibleOptions(this.partnerOptions, this.partnerQuery) },
		visibleHashtags() { return this.visibleOptions(this.hashtagOptions, this.hashtagQuery) },
		panelStyle() { return this.panelMaxHeight ? { '--annual-filter-max-height': this.panelMaxHeight } : null },
	},
	watch: {
		'normalized.amountMin': { handler() { this.syncAmount('min') }, immediate: true },
		'normalized.amountMax': { handler() { this.syncAmount('max') }, immediate: true },
		categoryQuery() { this.scheduleFit() },
		partnerQuery() { this.scheduleFit() },
		hashtagQuery() { this.scheduleFit() },
	},
	created() {
		const value = this.normalized
		this.open.categories = value.categories.length > 0
		this.open.partners = value.partners.length > 0
		this.open.amount = value.amountMin !== null || value.amountMax !== null
		this.open.labels = value.labels.length > 0
		this.open.hashtags = value.hashtags.length > 0
	},
	beforeUnmount() {
		this.disposed = true
		clearTimeout(this.amountTimer)
		this.unbindViewport()
		if (this.fitFrame && typeof window !== 'undefined') cancelAnimationFrame(this.fitFrame)
	},
	methods: {
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
		showKind(option) {
			return this.$enableIncomes !== false && (option?.type === 'income' || option?.type === 'expense')
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
		toggleSection(key, event) {
			const opening = !this.open[key]
			this.open[key] = opening
			if (opening) {
				if (this.quiet === key) this.quiet = ''
			} else {
				// Nextcloud fills a focused native button, and the popover trap can put that focus back.
				this.quiet = key
				const button = event?.currentTarget
				this.$nextTick(() => {
					if (this.disposed) return
					if (button && document.activeElement === button) button.blur()
					this.focusPanel()
				})
			}
			this.scheduleFit()
		},
		clearQuiet(key) {
			if (this.quiet === key) this.quiet = ''
		},
		focusPanel() {
			const panel = this.$refs.panel
			if (!panel || typeof panel.focus !== 'function') return
			if (!panel.hasAttribute('tabindex')) panel.setAttribute('tabindex', '-1')
			panel.focus({ preventScroll: true })
		},
		onPopoverShown(shown) {
			this.popoverShown = shown === true
			this.fitPasses = 0
			if (this.popoverShown) {
				this.bindViewport()
				this.scheduleFit()
			} else {
				this.quiet = ''
				this.panelMaxHeight = ''
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
			if (typeof document !== 'undefined' && input && document.activeElement === input) return
			this[`${field}Text`] = formatAnnualAmountParam(this.normalized[field])
		},
		scheduleAmount() {
			clearTimeout(this.amountTimer)
			this.amountTimer = setTimeout(() => {
				if (this.disposed) return
				const amountMin = parseAnnualAmountInput(this.amountMinText)
				const amountMax = parseAnnualAmountInput(this.amountMaxText)
				if (this.amountMinText.trim() && amountMin === null) return
				if (this.amountMaxText.trim() && amountMax === null) return
				this.$emit('change', { ...this.normalized, amountMin, amountMax })
			}, 300)
		},
	},
}
</script>

<style scoped>
.annual-filter-cluster { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; min-width: 0; max-width: 100%; }
.annual-filter-popover { display: inline-flex; }
.annual-filter-trigger { display: inline-flex; }
.annual-filter-chips { display: contents; }
.annual-filter-chip { display: inline-flex; align-items: center; gap: 4px; min-width: 0; max-width: 100%; min-height: 36px; margin: 0; padding-inline: 8px; border-radius: var(--border-radius-element, 8px); color: var(--cobudget-text-muted); font-weight: 400; }
.annual-filter-chip:hover, .annual-filter-chip:focus-within { background: var(--cobudget-surface-muted); }
.annual-filter-chip-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.annual-filter-remove { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 18px; height: 18px; margin: 0; padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; }
.annual-filter-panel { width: min(360px, calc(100vw - 48px)); max-height: var(--annual-filter-max-height, none); padding: 8px; overflow-y: auto; overscroll-behavior: contain; }
.annual-filter-panel:focus { outline: none; }
.annual-filter-section + .annual-filter-section { margin-top: 2px; }
.annual-filter-toggle { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; min-height: 36px; margin: 0; padding: 6px 8px; border: 0; border-radius: var(--border-radius-element, 8px); background-color: transparent; color: var(--cobudget-text, var(--color-main-text)); font: inherit; font-weight: 400; text-align: start; cursor: pointer; }
.annual-filter-panel .annual-filter-toggle:focus,
.annual-filter-panel .annual-filter-toggle:active,
.annual-filter-panel .annual-filter-toggle.is-quiet,
.annual-filter-panel .annual-filter-toggle.is-quiet:hover,
.annual-filter-panel .annual-filter-toggle.is-quiet:focus,
.annual-filter-panel .annual-filter-toggle.is-quiet:active { background-color: transparent; }
.annual-filter-panel .annual-filter-toggle:hover:not(.is-quiet) { background-color: var(--cobudget-surface-muted, var(--color-background-hover)); }
.annual-filter-heading { min-width: 0; }
.annual-filter-body { padding: 2px 4px 8px; }
.annual-filter-search, .annual-filter-amounts input { width: 100%; margin: 0 0 6px; box-sizing: border-box; }
.annual-filter-options { display: flex; flex-direction: column; }
.annual-filter-option { display: flex; align-items: center; gap: 8px; min-height: 28px; margin: 0; padding: 2px 8px; border-radius: var(--border-radius-element, 8px); cursor: pointer; }
.annual-filter-option:hover, .annual-filter-option:focus-within { background: var(--cobudget-surface-muted, var(--color-background-hover)); }
.annual-filter-option input[type="checkbox"] { appearance: auto; width: 16px; height: 16px; min-width: 16px; min-height: 16px; flex: 0 0 16px; margin: 0; padding: 0; border: 0; border-radius: 0; background: transparent; box-shadow: none; }
.annual-filter-option input[type="checkbox"]:hover,
.annual-filter-option input[type="checkbox"]:focus,
.annual-filter-option input[type="checkbox"]:active { border: 0; background: transparent; box-shadow: none; }
.annual-filter-option input[type="checkbox"]:focus-visible { outline: 2px solid var(--color-main-text); outline-offset: 2px; }
.annual-filter-option.child { padding-inline-start: 26px; }
.annual-filter-kind { position: relative; display: inline-flex; flex: 0 0 16px; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 4px; font-size: 13px; font-weight: 600; line-height: 16px; }
.annual-filter-kind.is-income { color: #107C41; background: rgba(16, 124, 65, 0.12); }
.annual-filter-kind.is-expense { color: var(--cobudget-error, #e9322d); background: rgba(233, 50, 45, 0.12); }
.visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
.annual-filter-amounts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding-inline: 8px; }
.annual-filter-amounts label { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.annual-filter-amounts span { color: var(--cobudget-text-muted, var(--color-text-maxcontrast)); }
</style>
