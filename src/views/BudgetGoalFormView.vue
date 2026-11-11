<template>
	<div class="budget-goal-form-view">
		<AppPageHeader
			class="budget-page-header">
			<template #title>
				<span class="cobudget-page-title-with-back">
					<NcButton
						variant="tertiary"
						class="cobudget-native-button cobudget-header-back-button"
						:aria-label="$texts.budgetGoals.backToBudgetGoals()"
						:title="$texts.budgetGoals.backToBudgetGoals()"
						:disabled="busy"
						@click="goBack">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
					</NcButton>
					<ReportPeriodPicker navigation-only force-navigation budget-navigation :budget-goals="navigationGoals" :current-budget-id="id" :period-enabled="false" :title="isEdit ? $texts.budgetGoals.editTitle() : $texts.budgetGoals.newTitle()" label="" :disabled="busy" />
				</span>
			</template>

		</AppPageHeader>

		<div class="budget-form">
			<p v-if="loading" class="budget-loading cobudget-form-hint" role="status"><NcLoadingIcon :size="20" /> {{ $texts.budgetGoals.loading() }}</p>
			<NcNoteCard v-else-if="loadError" type="error"><p>{{ loadError }}</p><NcButton @click="fetchData">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
		</div>
		<template v-if="!loading && !loadError">
			<form id="budget-goal-details-form" class="budget-form settings-section" :aria-busy="saving" @submit.prevent="saveGoalDetails">
				<section class="budget-form-section budget-general-section">
					<h3 class="cobudget-section-heading">{{ $texts.settings.generalTitle() }}</h3>
					<div class="form-grid two-columns">
						<NcFormGroup class="cobudget-form-group">
							<template #label><label class="cobudget-form-label" for="budget-name">{{ $texts.masterData.entryLabel() }}</label></template>
							<NcInputField id="budget-name" ref="nameInput" v-model="form.name" label-outside :label="$texts.masterData.entryLabel()" :placeholder="$texts.budgetGoals.namePlaceholderFlexibleFood()" required maxlength="128" :disabled="busy" :error="nameTouched && !form.name.trim()" :helper-text="nameTouched && !form.name.trim() ? $texts.budgetGoals.nameRequired() : ''" @blur="blurDetail('name')" @keydown.enter.prevent="submitDetail('name')" class="cobudget-native-field" />
							<NcNoteCard v-if="detailSaveErrors.name" type="error">
								<p>{{ detailSaveErrors.name }}</p>
								<NcButton :disabled="busy" @click="saveDetailField('name')">{{ $texts.common.retry() }}</NcButton>
							</NcNoteCard>
						</NcFormGroup>
						<NcFormGroup class="cobudget-form-group">
							<template #label><label class="cobudget-form-label" for="budget-amount">{{ $texts.budgetGoals.budget() }} ({{ displayCurrency }})</label></template>
							<NcInputField id="budget-amount" v-model="form.amount" label-outside :label="`${$texts.budgetGoals.budget()} (${displayCurrency})`" inputmode="decimal" type="text" placeholder="0.00" required :disabled="busy" :error="amountTouched && !amountValid" :helper-text="amountTouched && !amountValid ? $texts.budgetGoals.positiveAmountRequired() : ''" @blur="blurDetail('amount')" @keydown.enter.prevent="submitDetail('amount')" class="cobudget-native-field" />
							<NcNoteCard v-if="detailSaveErrors.amount" type="error">
								<p>{{ detailSaveErrors.amount }}</p>
								<NcButton :disabled="busy" @click="saveDetailField('amount')">{{ $texts.common.retry() }}</NcButton>
							</NcNoteCard>
						</NcFormGroup>
						<NcFormGroup class="cobudget-form-group">
							<template #label><label class="cobudget-form-label" for="budget-period">{{ $texts.budgetGoals.period() }}</label></template>
							<NcSelect input-id="budget-period" class="cobudget-native-select" label-outside :input-label="$texts.budgetGoals.period()" :options="periodOptions" :model-value="periodOptions.find(option => option.value === form.period)" label="label" :clearable="false" :searchable="false" :disabled="busy" @update:model-value="selectDetail('period', $event)" />
							<NcNoteCard v-if="detailSaveErrors.period" type="error">
								<p>{{ detailSaveErrors.period }}</p>
								<NcButton :disabled="busy" @click="saveDetailField('period')">{{ $texts.common.retry() }}</NcButton>
							</NcNoteCard>
						</NcFormGroup>
						<NcFormGroup class="cobudget-form-group">
							<template #label><label class="cobudget-form-label" for="budget-mode">{{ $texts.budgetGoals.type() }}</label></template>
							<NcSelect input-id="budget-mode" class="cobudget-native-select" label-outside :input-label="$texts.budgetGoals.type()" :options="modeOptions" :model-value="modeOptions.find(option => option.value === form.mode)" label="label" :clearable="false" :searchable="false" :disabled="busy" @update:model-value="selectDetail('mode', $event)" />
							<NcNoteCard v-if="detailSaveErrors.mode" type="error">
								<p>{{ detailSaveErrors.mode }}</p>
								<NcButton :disabled="busy" @click="saveDetailField('mode')">{{ $texts.common.retry() }}</NcButton>
							</NcNoteCard>
						</NcFormGroup>
					</div>
					<NcNoteCard v-if="saveError" type="error" :text="saveError" />

				</section>
			</form>

			<section class="budget-form budget-criteria-section settings-section">
				<section class="budget-form-section">
					<div class="section-title-row">
						<div>
							<h3 class="cobudget-section-heading">{{ $texts.budgetGoals.criteria() }}</h3>
							<p class="cobudget-form-hint">
								{{ $texts.budgetGoals.criteriaHint() }} {{ $texts.budgetGoals.multipleRowsHint() }}
							</p>
							<p class="cobudget-form-hint">
								{{ $texts.budgetGoals.categoryHierarchyHint() }}
							</p>
						</div>
					</div>

					<NcNoteCard v-if="criteriaError" type="error" :text="criteriaError" />
					<div class="budget-criteria-table-wrapper">
						<table class="budget-criteria-table" :aria-label="$texts.budgetGoals.criteriaTable()">
							<colgroup><col /><col /><col /><col class="criteria-action-column" /></colgroup>
							<thead><tr>
								<th scope="col">{{ $texts.budgetGoals.areas() }}</th>
								<th scope="col">{{ $texts.budgetGoals.categories() }}</th>
								<th scope="col">{{ $texts.budgetGoals.labels() }}</th>
								<th scope="col"><span class="hidden-visually">{{ $texts.common.edit() }}</span></th>
							</tr></thead>
							<tbody>
								<tr class="budget-criteria-input-row" :aria-busy="savingCriteria">
									<td><label class="criteria-mobile-label cobudget-form-label" for="budget-criterion-area">{{ $texts.budgetGoals.areas() }}</label><NcSelect input-id="budget-criterion-area" class="cobudget-native-select" label-outside :aria-label-combobox="$texts.budgetGoals.areaForNewCriterion()" :options="projectOptions" :model-value="projectOptions.find(option => option.value === newRule.projectId)" label="label" :clearable="false" :disabled="busy" @update:model-value="selectRuleProject">
										<template #option="option"><span class="budget-area-option"><AreaIcon v-if="option.value" :without-area="option.value === '0'" :color="option.color" /><span>{{ option.label }}</span></span></template>
										<template #selected-option="option"><span class="budget-area-option budget-area-option--selected" :title="option.label"><AreaIcon v-if="option.value" :without-area="option.value === '0'" :color="option.color" /><span>{{ option.label }}</span></span></template>
									</NcSelect></td>
									<td><label class="criteria-mobile-label cobudget-form-label" for="budget-criterion-category">{{ $texts.budgetGoals.categories() }}</label><NcSelect input-id="budget-criterion-category" class="cobudget-native-select" label-outside :aria-label-combobox="$texts.budgetGoals.categoryForNewCriterion()" :options="categoryOptions" :model-value="categoryOptions.find(option => option.value === newRule.categoryId)" label="label" :clearable="false" :disabled="busy" @update:model-value="newRule.categoryId = $event.value" /></td>
									<td><label class="criteria-mobile-label cobudget-form-label" for="budget-criterion-label">{{ $texts.budgetGoals.labels() }}</label><NcSelect input-id="budget-criterion-label" class="cobudget-native-select" label-outside :aria-label-combobox="$texts.budgetGoals.labelForNewCriterion()" :options="tagOptions" :model-value="tagOptions.find(option => option.value === newRule.tag)" label="label" :clearable="false" :disabled="busy" @update:model-value="newRule.tag = $event.value" /></td>
									<td class="criteria-action-cell"><NcButton class="criteria-icon-button cobudget-native-button" variant="primary" :disabled="busy || isEmptyRule(newRule)" :aria-label="$texts.budgetGoals.addCriterion()" @click.prevent="addRule">
										<template #icon><NcLoadingIcon v-if="savingCriteria" :size="20" /><PlusIcon v-else :size="20" /></template>
									</NcButton></td>
								</tr>
								<tr v-for="(rule, index) in rules" :key="rule.key" class="budget-criteria-data-row">
									<td><span class="criteria-mobile-label cobudget-form-label">{{ $texts.budgetGoals.areas() }}</span><span class="criteria-value budget-area-option"><AreaIcon v-if="rule.projectId !== '' && rule.projectId != null" :without-area="Number(rule.projectId) === 0" :color="projects.find(project => Number(project.id) === Number(rule.projectId))?.color" /><span>{{ projectLabelForRule(rule) }}</span></span></td>
									<td><span class="criteria-mobile-label cobudget-form-label">{{ $texts.budgetGoals.categories() }}</span><span class="criteria-value">{{ categoryLabelForRule(rule) }}</span></td>
									<td><span class="criteria-mobile-label cobudget-form-label">{{ $texts.budgetGoals.labels() }}</span><span class="criteria-value">{{ tagLabelForRule(rule) }}</span></td>
									<td class="criteria-action-cell"><DisabledActionHint :reason="deleteActionHint" :label="$texts.budgetGoals.removeCriterion()" ><NcButton class="criteria-icon-button" variant="tertiary" :disabled="busy" :aria-label="$texts.budgetGoals.removeCriterion()" @click.prevent="removeRule(index)">
										<template #icon><DeleteOutlineIcon :size="20" /></template>
									</NcButton></DisabledActionHint></td>
								</tr>
							</tbody>
						</table>
					</div>
					<NcNoteCard v-if="rules.length === 0" type="info" :text="$texts.budgetGoals.noCriteriaAllExpenses()" />
					<div v-if="!isEdit" class="budget-section-actions">
						<!-- Keep blur validation from moving Cancel between pointer-down and click. -->
						<NcButton variant="tertiary" type="button" :disabled="busy" @pointerdown.prevent @click="goBack">{{ $texts.common.cancel() }}</NcButton>
						<NcButton class="budget-save-button" variant="primary" type="submit" form="budget-goal-details-form" :disabled="busy || !canSave">
							<template v-if="saving" #icon><NcLoadingIcon :size="20" /></template>
							{{ saving ? $texts.common.saveBusy() : $texts.common.save() }}
						</NcButton>
					</div>

				</section>
			</section>

			<section v-if="isEdit" class="budget-form budget-delete-section settings-section" aria-labelledby="budget-delete-title">
				<div class="budget-form-section">
					<h3 id="budget-delete-title" class="cobudget-section-heading">{{ $texts.budgetGoals.deleteTitle() }}</h3>
					<p class="cobudget-form-hint">{{ $texts.budgetGoals.deleteHint() }}</p>
					<DisabledActionHint :reason="deleteActionHint" :label="$texts.budgetGoals.deleteConfirm()" ><NcButton class="budget-delete-button" variant="error" :disabled="busy" @click="askDeleteGoal">
						<template #icon><DeleteOutlineIcon :size="20" /></template>
						{{ $texts.budgetGoals.deleteConfirm() }}
					</NcButton></DisabledActionHint>
				</div>
			</section>

		</template>
		<DialogTransition :show="!!(deleteConfirmOpen)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="$texts.budgetGoals.deleteTitle()" :no-close="deleting" :close-on-click-outside="!deleting" @closing="closeDeleteConfirm">
				<p>{{ deleteConfirmMessage }}</p>
				<NcNoteCard v-if="deleteError" type="error" :text="deleteError" />
				<template #actions>
					<NcButton variant="tertiary" :disabled="deleting" @click="closeDeleteConfirm">{{ $texts.common.cancel() }}</NcButton>
					<DisabledActionHint :reason="deleting ? $texts.common.deleteBusy() : ''" :label="$texts.budgetGoals.deleteConfirm()" ><NcButton variant="error" :disabled="deleting" @click="deleteGoal"><template v-if="deleting" #icon><NcLoadingIcon :size="20" /></template>{{ $texts.budgetGoals.deleteConfirm() }}</NcButton></DisabledActionHint>
				</template>
			</NcDialog>
		</DialogTransition>
	</div>
</template>

<script>
import DialogTransition from '../components/DialogTransition.vue'
import DisabledActionHint from '../components/DisabledActionHint.vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import AreaIcon from '../components/AreaIcon.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import { extractError, showToast } from '../services/notifications'
import { categoryChoiceLabel, sortCategoriesHierarchically } from '../utils/categoryHierarchy'
import { sortAreasByName } from '../utils/areaSorting'

let nextRuleKey = 0

const emptyRule = () => ({
	key: ++nextRuleKey,
	projectId: '',
	categoryId: '',
	tag: ''
})

export default {
	name: 'BudgetGoalFormView',
	components: { ReportPeriodPicker, DialogTransition, DisabledActionHint,
		AppPageHeader,
		NcButton,
		PlusIcon,
		DeleteOutlineIcon,
		ArrowLeftIcon,
		AreaIcon, NcFormGroup, NcInputField, NcSelect, NcNoteCard, NcLoadingIcon, NcDialog
	},
	props: {
		id: {
			type: [String, Number],
			default: null
		}
	},
	data() {
		return {
			loading: true,
			navigationGoals: [],
			loadError: '', saveError: '', criteriaError: '', deleteError: '',
			nameTouched: false, amountTouched: false,
			detailSaveErrors: { name: '', amount: '', period: '', mode: '' },
			saving: false,
			savingCriteria: false,
			deleting: false,
			deleteConfirmOpen: false,
			form: {
				name: '',
				amount: '',
				period: 'year',
				mode: 'flexible'
			},
			savedGoalDetails: null,
			newRule: emptyRule(),
			rules: [],
			projects: [],
			categories: []
		}
	},
	computed: {
		isEdit() {
			return !!this.id
		},
		activeProjects() {
			return sortAreasByName(this.projects.filter(project => !this.isArchivedProject(project)))
		},
		availableTags() {
			return [
				this.$enableImportantPayments ? { value: 'important', label: this.$texts.labels.important() } : null,
				this.$enableReviewPayments ? { value: 'review', label: this.$texts.labels.review() } : null,
				this.$enableFixedCosts ? { value: 'fixedCosts', label: this.$texts.labels.fixedCosts() } : null,
				this.$enableChildRelated ? { value: 'childRelated', label: this.$texts.labels.children() } : null,
				this.$enableSubscriptions ? { value: 'subscriptions', label: this.$texts.labels.subscription() } : null,
				this.$enableTaxRelevant ? { value: 'taxRelevant', label: this.$texts.labels.taxRelevant() } : null,
				this.$enableInsurance ? { value: 'insurance', label: this.$texts.labels.insurance() } : null
			].filter(Boolean)
		},
		deleteActionHint() {
			if (this.deleting) return this.$texts.common.deleteBusy()
			if (this.loading) return this.$texts.common.loading()
			return this.saving || this.savingCriteria ? this.$texts.common.saveBusy() : ''
		},
		busy() { return this.loading || this.saving || this.savingCriteria || this.deleting },
		amountValid() { const amount = this.parseAmount(this.form.amount); return Number.isFinite(amount) && amount > 0 },
		canSave() { return this.form.name.trim().length > 0 && this.form.name.trim().length <= 128 && this.amountValid },
		periodOptions() { return [{value: 'year', label: this.$texts.budgetGoals.annual()}, {value: 'month', label: this.$texts.budgetGoals.monthly()}] },
		modeOptions() { return [{value: 'flexible', label: this.$texts.budgetGoals.flexibleGoalWithForecast()}, {value: 'hard', label: this.$texts.budgetGoals.fixedLimit()}] },
		projectOptions() { return [{value: '', label: '*'}, {value: '0', label: this.$texts.annual.withoutArea()}, ...this.activeProjects.map(project => ({value: String(project.id), label: project.name, color: project.color}))] },
		categoryOptions() { return [{value: '', label: '*'}, ...this.categoryOptionsForRule(this.newRule).map(category => ({value: String(category.id), label: this.categoryOptionLabel(category)}))] },
		tagOptions() { return [{value: '', label: '*'}, ...this.availableTags] },
		displayCurrency() {
			return this.$currency || '€'
		},
		deleteConfirmMessage() {
			const name = this.form.name.trim() || this.$texts.budgetGoals.deleteFallbackName()
			return this.$texts.budgetGoals.deleteNamedMessage(name)
		}
	},
	async mounted() {
		await this.fetchData()
		this.$nextTick(() => this.$refs.nameInput?.focus?.())
	},
	methods: {
		async fetchData() {
			this.loading = true
			this.loadError = ''
			try {
				const [projectsRes, categories, goalsRes] = await Promise.all([
					this.$enableProjects ? axios.get(generateUrl('/apps/cobudget/api/projects')) : Promise.resolve({ data: [] }),
					this.fetchAllCategories(),
					axios.get(generateUrl('/apps/cobudget/api/budgets'))
				])
				this.projects = Array.isArray(projectsRes.data) ? projectsRes.data : []
				this.categories = categories
				this.navigationGoals = Array.isArray(goalsRes.data) ? goalsRes.data : []

				if (this.isEdit) {
					const goal = this.navigationGoals.find(item => Number(item.id) === Number(this.id))
					if (!goal) {
						this.loadError = this.$texts.budgetGoals.notFound()
						return
					}
					this.applyGoal(goal)
				}
			} catch (error) {
				this.loadError = extractError(error, this.$texts.budgetGoals.loadError())
			} finally {
				this.loading = false
			}
		},
		async fetchAllCategories() {
			const baseRes = await axios.get(generateUrl('/apps/cobudget/api/categories/settings'))
			const categories = Array.isArray(baseRes.data) ? baseRes.data : []

			if (!this.$enableProjects) {
				return this.uniqueCategories(categories)
			}

			const projectsRes = await axios.get(generateUrl('/apps/cobudget/api/projects'))
			const activeProjects = Array.isArray(projectsRes.data)
				? projectsRes.data.filter(project => !this.isArchivedProject(project))
				: []
			const projectCategoryResponses = await Promise.all(activeProjects.map(project => axios.get(
				generateUrl('/apps/cobudget/api/categories/settings'),
				{ params: { projectId: project.id } }
			)))
			const projectCategories = projectCategoryResponses.flatMap(response => Array.isArray(response.data) ? response.data : [])
			return this.uniqueCategories([...categories, ...projectCategories])
		},
		uniqueCategories(categories) {
			const seen = new Set()
			const unique = categories
				.filter(category => category?.type === 'expense')
				.filter(category => {
					const id = Number(category.id)
					if (!id || seen.has(id)) {
						return false
					}
					seen.add(id)
					return true
				})
			return sortCategoriesHierarchically(unique)
		},
		applyGoal(goal) {
			this.savedGoalDetails = this.goalDetailsFromGoal(goal)
			this.form = {
				name: goal.name || '',
				amount: this.amountInputValue(goal.amount_cents),
				period: goal.period === 'month' ? 'month' : 'year',
				mode: goal.mode === 'hard' ? 'hard' : 'flexible'
			}
			this.rules = this.rulesFromGoal(goal)
			this.newRule = emptyRule()
		},
		goalDetailsFromGoal(goal) {
			return {
				name: goal.name || '',
				amount: Number(goal.amount_cents || 0) / 100,
				period: goal.period === 'month' ? 'month' : 'year',
				mode: goal.mode === 'hard' ? 'hard' : 'flexible'
			}
		},
		rulesFromGoal(goal) {
			const loadedRules = Array.isArray(goal.criteria?.rules) ? goal.criteria.rules : []
			return loadedRules.length > 0
				? loadedRules.map(rule => ({
					key: ++nextRuleKey,
					projectId: rule.projectId != null && rule.projectId !== '' ? String(rule.projectId) : '',
					categoryId: rule.categoryId ? String(rule.categoryId) : '',
					tag: rule.tag || ''
				}))
				: []
		},
		amountInputValue(cents) {
			return this.$formatInputAmount(Number(cents || 0) / 100)
		},
		parseAmount(value) {
			return this.$parseAmount(value)
		},
		isArchivedProject(project) {
			return project?.is_archived === true || project?.is_archived === 1 || project?.is_archived === '1'
		},
		categoryOptionsForRule(rule) {
			const projectId = rule.projectId ? Number(rule.projectId) : null
			return this.categories.filter(category => {
				const categoryProjectId = category.project_id ? Number(category.project_id) : null
				return categoryProjectId === null || categoryProjectId === projectId
			})
		},
		categoryOptionLabel(category) {
			const projectId = category.project_id ? Number(category.project_id) : null
			const project = this.projects.find(item => Number(item.id) === projectId)
			const hierarchyLabel = categoryChoiceLabel(category)
			return project ? `${hierarchyLabel} (${project.name})` : hierarchyLabel
		},
		projectLabelForRule(rule) {
			if (rule.projectId === 0 || rule.projectId === '0') return this.$texts.annual.withoutArea()
			if (!rule.projectId) {
				return '*'
			}
			const project = this.projects.find(item => Number(item.id) === Number(rule.projectId))
			return project?.name || this.$texts.common.unknownArea()
		},
		categoryLabelForRule(rule) {
			if (!rule.categoryId) {
				return '*'
			}
			const category = this.categories.find(item => Number(item.id) === Number(rule.categoryId))
			return category ? this.categoryOptionLabel(category) : this.$texts.common.unknownCategory()
		},
		tagLabelForRule(rule) {
			if (!rule.tag) {
				return '*'
			}
			const tag = this.availableTags.find(item => item.value === rule.tag)
			return tag?.label || this.$texts.budgetGoals.unknownLabel()
		},
		selectRuleProject(option) {
			if (this.busy || !this.projectOptions.some(item => item.value === option?.value)) return
			this.newRule.projectId = option.value
			this.onRuleProjectChange(this.newRule)
		},
		onRuleProjectChange(rule) {
			if (!rule.categoryId) {
				return
			}
			const stillAvailable = this.categoryOptionsForRule(rule).some(category => Number(category.id) === Number(rule.categoryId))
			if (!stillAvailable) {
				rule.categoryId = ''
			}
		},
		isEmptyRule(rule) {
			return (rule.projectId == null || rule.projectId === '') && !rule.categoryId && !rule.tag
		},
		async addRule() {
			if (this.busy || this.loadError || this.deleteConfirmOpen || this.isEmptyRule(this.newRule)) {
				return
			}
			const nextRules = [
				...this.rules,
				{
					key: ++nextRuleKey,
					projectId: this.newRule.projectId,
					categoryId: this.newRule.categoryId,
					tag: this.newRule.tag
				}
			]

			if (!this.isEdit) {
				this.rules = nextRules
				this.newRule = emptyRule()
				return
			}

			await this.saveCriteriaRules(nextRules, () => {
				this.newRule = emptyRule()
			})
		},
		async removeRule(index) {
			if (this.busy || this.loadError || this.deleteConfirmOpen) return
			const nextRules = this.rules.filter((rule, ruleIndex) => ruleIndex !== index)

			if (!this.isEdit) {
				this.rules = nextRules
				return
			}

			await this.saveCriteriaRules(nextRules)
		},
		criteriaPayloadFromRules(rules) {
			const normalizedRules = rules
				.filter(rule => !this.isEmptyRule(rule))
				.map(rule => ({
					projectId: rule.projectId != null && rule.projectId !== '' ? Number(rule.projectId) : null,
					categoryId: rule.categoryId ? Number(rule.categoryId) : null,
					tag: rule.tag || ''
				}))
			return { rules: normalizedRules }
		},
		criteriaPayload() {
			return this.criteriaPayloadFromRules(this.rules)
		},
		currentDetailsPayload(criteria = this.criteriaPayload()) {
			return {
				name: this.form.name.trim(),
				amount: this.parseAmount(this.form.amount),
				period: this.form.period,
				mode: this.form.mode,
				criteria
			}
		},
		savedDetailsPayload(criteria) {
			const details = this.savedGoalDetails || {
				name: this.form.name.trim(),
				amount: this.parseAmount(this.form.amount),
				period: this.form.period,
				mode: this.form.mode
			}

			return {
				...details,
				criteria
			}
		},
		blurDetail(field) {
			this[`${field}Touched`] = true
			return this.saveDetailField(field)
		},
		submitDetail(field) {
			this[`${field}Touched`] = true
			return this.isEdit ? this.saveDetailField(field) : this.saveGoalDetails()
		},
		selectDetail(field, option) {
			const options = field === 'period' ? this.periodOptions : field === 'mode' ? this.modeOptions : []
			if (this.busy || !options.some(item => item.value === option?.value)) return
			this.form[field] = option.value
			return this.saveDetailField(field)
		},
		async saveDetailField(field) {
			if (!this.isEdit || !this.savedGoalDetails || this.busy || this.loadError || this.deleteConfirmOpen) return
			if (!Object.hasOwn(this.detailSaveErrors, field)) return
			const draft = this.form[field]
			const value = field === 'name' ? draft.trim() : field === 'amount' ? this.parseAmount(draft) : draft
			if (field === 'name' && (!value || value.length > 128)) return
			if (field === 'amount' && (!Number.isFinite(value) || value <= 0)) return
			if (field === 'period' && !this.periodOptions.some(option => option.value === value)) return
			if (field === 'mode' && !this.modeOptions.some(option => option.value === value)) return
			this.detailSaveErrors[field] = ''
			if (value === this.savedGoalDetails[field]) return

			this.saving = true
			// Only commit this field; other inputs may still contain incomplete or failed drafts.
			const payload = { ...this.savedDetailsPayload(this.criteriaPayload()), [field]: value }
			try {
				const response = await axios.put(generateUrl('/apps/cobudget/api/budgets/{id}', { id: this.id }), payload)
				this.savedGoalDetails = this.goalDetailsFromGoal(response.data)
				this.navigationGoals = this.navigationGoals.map(goal => Number(goal.id) === Number(this.id) ? { ...goal, name: response.data.name } : goal)
				if (this.form[field] === draft) {
					this.form[field] = field === 'amount' ? this.amountInputValue(response.data.amount_cents) : this.savedGoalDetails[field]
				}
				showToast(this.$texts.budgetGoals.saved())
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
			} catch (error) {
				this.detailSaveErrors[field] = extractError(error, this.$texts.budgetGoals.saveError())
			} finally {
				this.saving = false
			}
		},
		async saveGoalDetails() {
			if (this.isEdit || !this.canSave || this.busy || this.loadError || this.deleteConfirmOpen) return

			this.saving = true
			this.saveError = ''
			const payload = this.currentDetailsPayload(this.criteriaPayload())
			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/budgets'), payload)
				this.applyGoal(response.data)
				showToast(this.$texts.budgetGoals.created())
				if (response.data?.id) {
					this.$router.replace({ name: 'budget-edit', params: { id: response.data.id } })
				}
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
			} catch (error) {
				this.saveError = extractError(error, this.$texts.budgetGoals.saveError())
			} finally {
				this.saving = false
			}
		},
		async saveCriteriaRules(nextRules, afterSuccess = null) {
			if (!this.isEdit || this.busy || this.loadError || this.deleteConfirmOpen) {
				return
			}

			this.savingCriteria = true
			this.criteriaError = ''
			const previousRules = this.rules
			this.rules = nextRules

			try {
				const response = await axios.put(
					generateUrl('/apps/cobudget/api/budgets/{id}', { id: this.id }),
					this.savedDetailsPayload(this.criteriaPayloadFromRules(nextRules))
				)
				this.savedGoalDetails = this.goalDetailsFromGoal(response.data)
				this.rules = this.rulesFromGoal(response.data)
				if (afterSuccess) {
					afterSuccess()
				}
				showToast(this.$texts.budgetGoals.saved())
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
			} catch (error) {
				this.rules = previousRules
				this.criteriaError = extractError(error, this.$texts.budgetGoals.saveError())
			} finally {
				this.savingCriteria = false
			}
		},
		closeDeleteConfirm() { if (!this.deleting) this.deleteConfirmOpen = false },
		askDeleteGoal() {
			if (!this.isEdit || this.busy || this.loadError) return
			this.deleteError = ''
			this.deleteConfirmOpen = true
		},
		async deleteGoal() {
			if (!this.isEdit || !this.deleteConfirmOpen || this.busy || this.loadError) {
				return
			}

			this.deleting = true
			this.deleteError = ''
			try {
				await axios.delete(generateUrl('/apps/cobudget/api/budgets/{id}', { id: this.id }))
				showToast(this.$texts.budgetGoals.deleted())
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
				this.deleteConfirmOpen = false
				this.$router.push({ name: 'budgets' })
			} catch (error) {
				this.deleteError = extractError(error, this.$texts.budgetGoals.deleteError())
			} finally {
				this.deleting = false
			}
		},
		goBack() {
			if (this.saving || this.savingCriteria || this.deleting) return
			this.$router.push({ name: 'budgets' })
		}
	}
}
</script>

<style scoped>
.budget-area-option { display: inline-flex; align-items: center; gap: 8px; min-width: 0; max-width: 100%; }
.budget-area-option > :last-child { min-width: 0; white-space: normal; overflow-wrap: anywhere; }
.budget-area-option--selected > :last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.budget-goal-form-view {
	width: 100%;
}

.budget-form {
	width: min(var(--cobudget-form-max-width), calc(100% - var(--cobudget-form-inset) * 2));
	margin: 0 var(--cobudget-form-inset) 0;
}

.budget-criteria-section,
.budget-delete-section {
	padding-block-start: var(--cobudget-settings-heading-gap);
}

.budget-form-section h3 {
	margin-block-start: 0;
}

.budget-general-section > h3 {
	margin: 0 0 14px;
	font-size: var(--cobudget-font-section-title);
	font-weight: var(--cobudget-font-weight-heading);
}

.budget-delete-section,
.budget-criteria-section:last-of-type {
	padding-block-end: var(--cobudget-settings-heading-gap);
}

.form-grid {
	display: grid;
	gap: var(--cobudget-section-gap);
}

.form-grid.two-columns {
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.budget-delete-section .cobudget-form-hint {
	margin-block-end: var(--cobudget-section-gap);
}

.budget-section-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	flex-wrap: wrap;
	margin-top: var(--cobudget-section-gap);
}

.section-title-row {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: var(--cobudget-card-gap);
	margin-bottom: var(--cobudget-section-gap);
}

.section-title-row p {
	margin: 0;
}

.budget-criteria-table-wrapper {
	overflow-x: auto;
}
.budget-criteria-table {
	width: 100%;
	min-width: 600px;
	table-layout: fixed;
	border-collapse: separate;
	border-spacing: 0;
}
.budget-criteria-table th, .budget-criteria-table td {
	padding: var(--cobudget-table-cell-padding);
	height: auto;
	text-align: start;
	vertical-align: middle;
	white-space: normal;
	overflow-wrap: anywhere;
	font-weight: 400;
	border-bottom: 1px solid var(--cobudget-border);
}
.budget-criteria-table thead th {
	background: var(--cobudget-table-header-background);
	color: var(--cobudget-text-muted);
	font-size: var(--cobudget-table-header-font-size);
	letter-spacing: normal;
	font-weight: 400;
	height: var(--cobudget-table-header-height);
}
.budget-criteria-table tbody tr:is(:hover, :focus, :active) { background-color: transparent; }
.budget-criteria-table tbody tr:last-child td { border-bottom: 0; }
.budget-criteria-table .criteria-action-column { width: calc(var(--default-clickable-area, 44px) + var(--default-grid-baseline, 4px) * 5); }
.budget-criteria-table .criteria-action-cell { text-align: end; }
/* Remove the native select’s trailing field margin inside centered table cells. */
.budget-criteria-input-row .cobudget-native-select { display: block; margin-block: 0; }
.criteria-mobile-label { display: none; }
.budget-loading { display: flex; align-items: center; gap: 8px; }
.budget-form .cobudget-native-select { min-width: 0; width: 100%; }


@media (max-width: 900px) {
	.form-grid.two-columns {
		grid-template-columns: 1fr;
	}

	.section-title-row {
		flex-direction: column;
	}
}

@media (max-width: 768px) {
	.budget-criteria-table-wrapper { overflow: visible; }
	.budget-criteria-table { display: block; min-width: 0; }
	.budget-criteria-table colgroup { display: none; }
	.budget-criteria-table thead {
		position: absolute;
		width: 1px;
		height: 1px;
		padding: 0;
		margin: -1px;
		overflow: hidden;
		clip-path: inset(50%);
		white-space: nowrap;
	}
	.budget-criteria-table tbody { display: block; }
	.budget-criteria-table tbody tr {
		display: grid;
		grid-template-columns: minmax(0, 1fr);
		gap: var(--cobudget-content-gap);
		padding: var(--cobudget-content-gap);
		border-bottom: 1px solid var(--cobudget-border);
	}
	.budget-criteria-table tbody tr:last-child { border-bottom: 0; }
	.budget-criteria-table tbody td { display: block; min-width: 0; padding: 0; border-bottom: 0; }
	.budget-criteria-table tbody td.criteria-action-cell { display: flex; justify-content: flex-end; }
	.criteria-mobile-label { display: block; margin-block: 0 calc(var(--default-grid-baseline, 4px) * 2); font-size: var(--font-size, 15px); font-weight: var(--font-weight-element, 500); color: var(--color-main-text); }
	.budget-criteria-table tbody .budget-criteria-data-row {
		grid-template-columns: minmax(0, 1fr) var(--cobudget-mobile-touch-size, 44px);
		gap: var(--default-grid-baseline, 4px) calc(var(--default-grid-baseline, 4px) * 2);
	}
	.budget-criteria-table .budget-criteria-data-row td:not(.criteria-action-cell) {
		grid-column: 1;
		display: grid;
		grid-template-columns: minmax(0, 80px) minmax(0, 1fr);
		gap: calc(var(--default-grid-baseline, 4px) * 2);
	}
	.budget-criteria-table .budget-criteria-data-row td.criteria-action-cell {
		grid-column: 2;
		grid-row: 1 / span 3;
		align-items: flex-start;
	}
	.budget-criteria-data-row .criteria-mobile-label { margin: 0; }
	.criteria-value { min-width: 0; }
	.budget-criteria-table .budget-criteria-input-row td:not(.criteria-action-cell) {
		display: grid;
		grid-template-columns: minmax(0, 80px) minmax(0, 1fr);
		align-items: center;
		gap: calc(var(--default-grid-baseline, 4px) * 2);
	}
	.budget-criteria-input-row .criteria-mobile-label { margin: 0; }
	.budget-criteria-table tbody .budget-criteria-input-row { gap: calc(var(--default-grid-baseline, 4px) * 2); }
	.budget-form {
		width: calc(100% - 32px);
		margin: 0 16px;
	}

	form.budget-form { margin-block-start: 16px; }

	.budget-goal-form-view { --default-clickable-area: var(--cobudget-mobile-touch-size); }
}
</style>
