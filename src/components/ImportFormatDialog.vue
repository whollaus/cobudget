<template>
	<DialogTransition :show="show" v-slot="{ returnFocus }">
		<NcModal
			:container="null"
			:set-return-focus="returnFocus"
			class="cobudget-native-dialog cobudget-settings-modal bulk-import-format-modal"
			:show="true"
			label-id="bulk-import-format-modal-title"
			size="large"
			:close-on-click-outside="true"
			@update:show="$emit('close')"
			@keydown.esc.stop.prevent="$emit('close')">
			<div class="bulk-import-format-dialog">
				<h2 id="bulk-import-format-modal-title">{{ $texts.bulkImport.formatTitle() }}</h2>
				<div class="bulk-import-format-dialog-body">
					<slot name="target" />
					<p v-if="loading" role="status" class="cobudget-form-hint">{{ $texts.common.loading() }}</p>
					<NcNoteCard v-else-if="error" type="error" :text="error" />
					<div v-else class="bulk-import-example-block">
						<h3 class="bulk-import-subheading">{{ $texts.bulkImport.exampleTitle() }}</h3>
						<p class="cobudget-form-hint">{{ $texts.bulkImport.formatDescription() }}</p>
						<pre class="bulk-import-json-example" data-cobudget-card="compact"><code>{{ exampleJsonText }}</code></pre>
						<div class="bulk-import-example-actions">
							<NcButton
								variant="secondary"
								class="cobudget-native-button"
								@click="copyExampleJson">
								<template #icon><ContentCopyIcon :size="20" /></template>
								{{ $texts.bulkImport.copyExample() }}
							</NcButton>
							<NcButton
								variant="secondary"
								class="cobudget-native-button"
								@click="downloadExampleJson">
								<template #icon><DownloadIcon :size="20" /></template>
								{{ $texts.bulkImport.downloadExample() }}
							</NcButton>
						</div>
					</div>

					<ul class="bulk-import-rules">
						<li>{{ $texts.bulkImport.amountHint() }}</li>
						<li>{{ $texts.bulkImport.importLimits() }}</li>
						<li v-if="project">{{ $texts.bulkImport.areaTargetDescription(project.name) }}</li>
					</ul>

					<div class="bulk-import-example-block">
						<h3 class="bulk-import-subheading">{{ $texts.autoImports.title() }}</h3>
						<p class="cobudget-form-hint">{{ $texts.bulkImport.folderFilenameHint() }}</p>
						<p class="cobudget-form-hint">{{ $texts.bulkImport.folderCorrectionHint() }}</p>
					</div>

					<details class="bulk-import-details">
						<summary>{{ $texts.bulkImport.fieldReferenceCaption() }}</summary>
						<div
							tabindex="0"
							role="region"
							:aria-label="$texts.bulkImport.fieldReferenceCaption()"
							class="bulk-import-table-wrapper bulk-import-reference-wrapper">
							<table class="bulk-import-table bulk-import-reference-table">
								<caption class="hidden-visually">{{ $texts.bulkImport.fieldReferenceCaption() }}</caption>
								<thead>
									<tr>
										<th scope="col" class="col-field">{{ $texts.bulkImport.fieldColumnName() }}</th>
										<th scope="col" class="col-required"><span class="hidden-visually">{{ $texts.bulkImport.fieldColumnRequired() }}</span></th>
										<th scope="col" class="col-notes">{{ $texts.bulkImport.columnDescription() }}</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="fieldRow in fieldReferenceRows" :key="fieldRow.name">
										<td :data-label="$texts.bulkImport.fieldColumnName()" class="col-field">
											<code class="bulk-import-field-code">{{ fieldRow.name }}</code>
										</td>
										<td :data-label="$texts.bulkImport.fieldColumnRequired()" class="col-required">
											<span :data-cobudget-badge="fieldRow.required ? 'info' : 'neutral'" data-cobudget-badge-size="compact">
												{{ fieldRow.required ? $texts.bulkImport.requiredYes() : $texts.bulkImport.requiredNo() }}
											</span>
										</td>
										<td :data-label="$texts.bulkImport.columnDescription()" class="col-notes">{{ fieldRow.description }}</td>
									</tr>
								</tbody>
							</table>
						</div>
					</details>

					<details v-if="!loading && !error" class="bulk-import-details">
						<summary>{{ $texts.bulkImport.availableLookupsTitle() }}</summary>
						<div class="bulk-import-lookups-block">
							<p class="cobudget-form-hint">{{ $texts.bulkImport.availableLookupsHint() }}</p>
							<p v-if="hasSelectedTarget" class="cobudget-form-hint">
								{{ canCreateAreaMasterData ? $texts.bulkImport.areaAdminCreateNotice() : $texts.bulkImport.areaNonAdminNote() }}
							</p>
							<div class="bulk-import-catalog-grid">
								<div class="bulk-import-catalog-card" data-cobudget-card="compact">
									<h5 class="bulk-import-catalog-title">{{ $texts.analytics.categories() }}</h5>
									<div class="bulk-import-catalog-group">
										<span class="bulk-import-catalog-type">{{ $texts.common.expense() }}</span>
										<div v-if="expenseCategories.length" class="bulk-import-chip-list">
											<span
												v-for="cat in expenseCategories"
												:key="`cat-exp-${cat.id}`"
												role="button"
												tabindex="0"
												class="bulk-import-catalog-chip"
												:title="categoryChipTitle(cat)"
												:aria-label="$texts.bulkImport.clickToCopy(cat.name)"
												@click="copyLookupName(cat.name)"
												@keydown.enter.prevent="copyLookupName(cat.name)"
												@keydown.space.prevent="copyLookupName(cat.name)">
												<CategoryIcon :icon="cat.icon || 'Shape'" :size="16" />
												<span>{{ cat.name }}</span>
											</span>
										</div>
										<p v-else class="cobudget-form-hint bulk-import-catalog-empty">{{ $texts.bulkImport.noAvailableLookups() }}</p>
									</div>
									<div v-if="$enableIncomes" class="bulk-import-catalog-group">
										<span class="bulk-import-catalog-type">{{ $texts.common.income() }}</span>
										<div v-if="incomeCategories.length" class="bulk-import-chip-list">
											<span
												v-for="cat in incomeCategories"
												:key="`cat-inc-${cat.id}`"
												role="button"
												tabindex="0"
												class="bulk-import-catalog-chip"
												:title="categoryChipTitle(cat)"
												:aria-label="$texts.bulkImport.clickToCopy(cat.name)"
												@click="copyLookupName(cat.name)"
												@keydown.enter.prevent="copyLookupName(cat.name)"
												@keydown.space.prevent="copyLookupName(cat.name)">
												<CategoryIcon :icon="cat.icon || 'Shape'" :size="16" />
												<span>{{ cat.name }}</span>
											</span>
										</div>
										<p v-else class="cobudget-form-hint bulk-import-catalog-empty">{{ $texts.bulkImport.noAvailableLookups() }}</p>
									</div>
								</div>

								<div class="bulk-import-catalog-card" data-cobudget-card="compact">
									<h5 class="bulk-import-catalog-title">{{ $texts.analytics.paymentPartners() }}</h5>
									<div class="bulk-import-catalog-group">
										<span class="bulk-import-catalog-type">{{ $texts.common.expense() }}</span>
										<div v-if="expensePartners.length" class="bulk-import-chip-list">
											<span
												v-for="partner in expensePartners"
												:key="`partner-exp-${partner.id}`"
												role="button"
												tabindex="0"
												class="bulk-import-catalog-chip"
												:title="$texts.bulkImport.clickToCopy(partner.name)"
												:aria-label="$texts.bulkImport.clickToCopy(partner.name)"
												@click="copyLookupName(partner.name)"
												@keydown.enter.prevent="copyLookupName(partner.name)"
												@keydown.space.prevent="copyLookupName(partner.name)">
												<span>{{ partner.name }}</span>
											</span>
										</div>
										<p v-else class="cobudget-form-hint bulk-import-catalog-empty">{{ $texts.bulkImport.noAvailableLookups() }}</p>
									</div>
									<div v-if="$enableIncomes" class="bulk-import-catalog-group">
										<span class="bulk-import-catalog-type">{{ $texts.common.income() }}</span>
										<div v-if="incomePartners.length" class="bulk-import-chip-list">
											<span
												v-for="partner in incomePartners"
												:key="`partner-inc-${partner.id}`"
												role="button"
												tabindex="0"
												class="bulk-import-catalog-chip"
												:title="$texts.bulkImport.clickToCopy(partner.name)"
												:aria-label="$texts.bulkImport.clickToCopy(partner.name)"
												@click="copyLookupName(partner.name)"
												@keydown.enter.prevent="copyLookupName(partner.name)"
												@keydown.space.prevent="copyLookupName(partner.name)">
												<span>{{ partner.name }}</span>
											</span>
										</div>
										<p v-else class="cobudget-form-hint bulk-import-catalog-empty">{{ $texts.bulkImport.noAvailableLookups() }}</p>
									</div>
								</div>
							</div>
						</div>
					</details>
				</div>
				<div class="bulk-import-format-dialog-actions">
					<NcButton
						variant="tertiary"
						class="cobudget-native-button"
						@click="$emit('close')">
						{{ $texts.common.close() }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</DialogTransition>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import CategoryIcon from './CategoryIcon.vue'
import { useLocalDate } from '../utils/useLocalDate'
import DialogTransition from './DialogTransition.vue'
import { downloadTextFile } from '../services/annualOverview'
import { copyReportText } from '../services/reportExportActions'
import { categoryPathLabel, sortCategoriesHierarchically } from '../utils/categoryHierarchy'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { showToast } from '../services/notifications'

export default {
	setup() { return { today: useLocalDate() } },
	name: 'ImportFormatDialog',
	components: { CategoryIcon, ContentCopyIcon, DialogTransition, DownloadIcon, NcButton, NcModal, NcNoteCard },
	props: {
		show: { type: Boolean, required: true },
		project: { type: Object, default: null },
		categories: { type: Array, required: true },
		paymentPartners: { type: Array, required: true },
		hasSelectedTarget: { type: Boolean, default: true },
		loading: Boolean,
		error: { type: String, default: '' },
	},
	emits: ['close'],
	computed: {
		canCreateAreaMasterData() {
			return !this.project || [true, 1, '1', 'true'].includes(this.project.is_owner)
		},
		expenseCategories() {
			return sortCategoriesHierarchically(this.categories.filter(cat => cat && cat.type === 'expense'))
		},
		incomeCategories() {
			return sortCategoriesHierarchically(this.categories.filter(cat => cat && cat.type === 'income'))
		},
		expensePartners() {
			return [...this.paymentPartners]
				.filter(partner => partner && partner.type === 'expense')
				.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }))
		},
		incomePartners() {
			return [...this.paymentPartners]
				.filter(partner => partner && partner.type === 'income')
				.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }))
		},
		exampleJsonText() {
			const expense = {
				date: this.today,
				amount: -42.75,
				category: this.exampleName(this.expenseCategories, this.$texts.bulkImport.exampleGroceries()),
				payment_partner: this.exampleName(this.expensePartners, this.$texts.bulkImport.exampleSupermarket()),
			}
			const secondPayment = this.$enableIncomes === false
				? { ...expense, amount: -18.90 }
				: {
					date: this.today,
					amount: 1850,
					category: this.exampleName(this.incomeCategories, this.$texts.bulkImport.exampleSalary()),
					payment_partner: this.exampleName(this.incomePartners, this.$texts.bulkImport.exampleEmployer()),
				}
			expense.description = this.$texts.bulkImport.exampleDescription()
			for (const field of this.enabledLabelFields.slice(0, 2)) {
				expense[field.name] = 1
			}
			return JSON.stringify({ payments: [expense, secondPayment] }, null, 2)
		},
		enabledLabelFields() {
			const labels = this.$texts.labels
			return [
				{ name: 'important', label: labels.important(), enabled: this.$enableImportantPayments },
				{ name: 'review', label: labels.review(), enabled: this.$enableReviewPayments },
				{ name: 'fixed_cost', label: labels.fixedCosts(), enabled: this.$enableFixedCosts, expenseOnly: true },
				{ name: 'children', label: labels.children(), enabled: this.$enableChildRelated },
				{ name: 'subscription', label: labels.subscription(), enabled: this.$enableSubscriptions, expenseOnly: true },
				{ name: 'tax_relevant', label: labels.taxRelevant(), enabled: this.$enableTaxRelevant },
				{ name: 'insurance', label: labels.insurance(), enabled: this.$enableInsurance },
			].filter(field => field.enabled !== false)
		},
		fieldReferenceRows() {
			const t = this.$texts.bulkImport
			return [
				{ name: 'date', required: true, description: t.fieldDateDesc(this.today) },
				{ name: 'amount', required: true, description: t.fieldAmountDesc() },
				{ name: 'category', description: t.fieldCategoryDesc() },
				{ name: 'payment_partner', description: t.fieldPartnerDesc() },
				{ name: 'description', description: t.fieldDescriptionDesc() },
				...this.enabledLabelFields.map(field => ({
					name: field.name,
					description: field.expenseOnly ? t.expenseLabelHint(field.label) : t.labelHint(field.label),
				})),
			]
		},
	},
	methods: {
		categoryChipTitle(cat) {
			const name = String(cat?.name || '')
			const path = categoryPathLabel(cat)
			const copyHint = this.$texts.bulkImport.clickToCopy(name)
			return path && path !== name ? `${path} — ${copyHint}` : copyHint
		},
		async copyLookupName(name) {
			const text = String(name || '').trim()
			if (!text) return
			try {
				await copyReportText(text)
				showToast(this.$texts.bulkImport.lookupCopied(text))
			} catch {
				showToast(this.$texts.bulkImport.lookupCopyError(), 'error')
			}
		},
		exampleName(options, preferredName) {
			if (options.some(option => option.name === preferredName)) return preferredName
			if (options.length) return options[0].name
			// Area members can import payments only with lookup names that already exist.
			return this.canCreateAreaMasterData ? preferredName : undefined
		},
		async copyExampleJson() {
			try {
				await copyReportText(this.exampleJsonText)
				showToast(this.$texts.bulkImport.exampleCopied())
			} catch {
				showToast(this.$texts.bulkImport.exampleCopyError(), 'error')
			}
		},
		downloadExampleJson() {
			downloadTextFile('cobudget-import-example.json', this.exampleJsonText + '\n', 'application/json;charset=utf-8')
		},
	},
}
</script>

<style scoped src="../styles/bulk-import-tables.css"></style>

<style scoped>
.bulk-import-catalog-empty { margin: 0; }
.bulk-import-catalog-chip {
	display: inline-flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 1.5);
	padding: 4px 8px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: var(--border-radius-element);
	border: none;
	box-shadow: inset 0 0 0 1px var(--color-border-maxcontrast);
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-default, normal);
	line-height: normal;
	letter-spacing: normal;
	cursor: pointer;
	user-select: none;
	transition: box-shadow var(--cobudget-hover-duration) linear;
}

.bulk-import-catalog-chip,
.bulk-import-catalog-chip :deep(*) {
	cursor: pointer;
}

.bulk-import-catalog-chip:focus-visible {
	outline: 2px solid var(--cobudget-text, var(--color-main-text, #222));
	outline-offset: 2px;
	transition: none;
}

@media (hover: hover) and (pointer: fine) {
	.bulk-import-catalog-chip:hover {
		box-shadow: inset 0 0 0 var(--border-width-input-focused, 2px) var(--color-border-maxcontrast);
	}
}

@media (min-width: 769px) {
	.bulk-import-reference-table {
		table-layout: fixed;
	}

	.bulk-import-reference-table .col-field {
		width: 11rem;
	}

	.bulk-import-reference-table .col-required {
		width: 6.5rem;
	}
}

.bulk-import-reference-table :is(th, td),
.bulk-import-field-code {
	white-space: normal;
	overflow-wrap: anywhere;
}

.bulk-import-field-code {
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	font-size: var(--cobudget-font-caption);
	color: var(--cobudget-text);
	background: var(--cobudget-surface-muted);
	padding: 2px 6px;
	border-radius: var(--cobudget-badge-radius);
}

.bulk-import-lookups-block,
.bulk-import-example-block {
	display: flex;
	flex-direction: column;
	gap: var(--cobudget-content-gap);
}

.bulk-import-subheading {
	margin: 0;
	font-size: var(--cobudget-font-subheading);
	font-weight: var(--cobudget-font-weight-heading);
	color: var(--cobudget-text);
}

.bulk-import-catalog-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: var(--cobudget-content-gap);
}

.bulk-import-catalog-card {
	display: flex;
	flex-direction: column;
	gap: var(--cobudget-content-gap);
	min-width: 0;
}

.bulk-import-catalog-title {
	margin: 0;
	font-size: var(--cobudget-font-body);
	font-weight: var(--cobudget-font-weight-heading);
	color: var(--cobudget-text);
}

.bulk-import-catalog-group {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 1.5);
}

.bulk-import-catalog-type {
	font-size: var(--cobudget-font-caption);
	color: var(--cobudget-text-muted);
}

.bulk-import-example-actions {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: var(--cobudget-content-gap);
}

.bulk-import-json-example {
	margin: 0;
	overflow-x: auto;
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	font-size: var(--cobudget-font-caption);
	line-height: 1.5;
	color: var(--cobudget-text);
	background: var(--cobudget-surface-muted);
}

.bulk-import-format-modal :deep(.modal-container) {
	overflow: hidden;
}

.bulk-import-format-modal :deep(.modal-container__content) {
	display: flex;
	min-height: 0;
	overflow: hidden;
}

.bulk-import-format-dialog {
	display: flex;
	flex-direction: column;
	width: 100%;
	min-width: 0;
	max-height: 100%;
	gap: var(--cobudget-content-gap);
	padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding, 24px) var(--cobudget-dialog-padding, 24px);
}

.bulk-import-format-dialog h2 {
	flex-shrink: 0;
	margin: 0;
	padding-inline-end: var(--cobudget-mobile-touch-size, 44px);
	font-size: var(--cobudget-dialog-title-size, 20px);
	line-height: 1.5;
	text-align: start;
	color: var(--cobudget-text);
}

.bulk-import-format-dialog-body {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-height: 0;
	gap: var(--cobudget-content-gap);
	max-height: min(68dvh, 660px);
	background-color: inherit;
	overflow-y: auto;
	overscroll-behavior: none;
	transform: translateZ(0);
	backface-visibility: hidden;
	scrollbar-gutter: stable;
	padding-block: var(--default-grid-baseline, 4px);
}

.bulk-import-format-dialog-body > * {
	flex-shrink: 0;
}

.bulk-import-format-dialog-body p,
.bulk-import-rules {
	margin: 0;
}

.bulk-import-rules {
	padding-inline-start: var(--cobudget-section-gap);
	list-style: disc;
}

.bulk-import-details summary {
	cursor: pointer;
	font-weight: var(--cobudget-font-weight-heading);
	padding-block: var(--default-grid-baseline, 4px);
}

.bulk-import-details[open] > summary {
	margin-block-end: var(--cobudget-content-gap);
}

.bulk-import-format-dialog-actions {
	flex-shrink: 0;
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	padding-top: var(--default-grid-baseline, 4px);
}

@media (max-width: 768px) {
	.bulk-import-format-modal {
		--cobudget-dialog-padding: var(--cobudget-space-4, 16px);
	}

	.bulk-import-format-modal :deep(.modal-container) {
		height: calc(100% - var(--header-height));
		overflow: hidden;
	}

	.bulk-import-format-modal :deep(.modal-container__content) {
		display: flex;
		min-height: 0;
		overflow: hidden;
	}

	.bulk-import-format-dialog {
		height: 100%;
		min-height: 0;
		width: 100%;
		padding: 0;
		gap: 0;
	}

	.bulk-import-format-dialog h2 {
		flex-shrink: 0;
		padding: calc(var(--default-grid-baseline, 4px) * 3) calc(var(--cobudget-mobile-touch-size, 44px) + var(--cobudget-dialog-padding)) var(--cobudget-space-2, 8px) var(--cobudget-dialog-padding);
	}

	.bulk-import-format-dialog-body {
		flex: 1;
		min-height: 0;
		max-height: none;
		padding: var(--default-grid-baseline, 4px) var(--cobudget-dialog-padding) calc(var(--default-grid-baseline, 4px) * 3);
	}

	.bulk-import-format-dialog-actions {
		flex-shrink: 0;
		padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding) max(var(--cobudget-dialog-padding), env(safe-area-inset-bottom));
	}

	.bulk-import-catalog-grid {
		grid-template-columns: 1fr;
	}

}
</style>
