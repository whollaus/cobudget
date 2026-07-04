<template>
	<div class="budget-goal-form-view">
		<AppPageHeader
			class="budget-page-header">
			<template #title>
				<span class="cobudget-page-title-with-back">
					<NcButton
						variant="tertiary"
						class="cobudget-header-back-button"
						:aria-label="$texts.budgetGoals.backToBudgetGoals()"
						:title="$texts.budgetGoals.backToBudgetGoals()"
						@click="goBack">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
					</NcButton>
					<span>{{ isEdit ? $texts.budgetGoals.editTitle() : $texts.budgetGoals.newTitle() }}</span>
				</span>
			</template>
			<template #actions>
				<NcButton
					v-if="isEdit"
					class="budget-delete-button"
					variant="tertiary"
					:aria-label="$texts.common.delete()"
					:title="$texts.common.delete()"
					@click="askDeleteGoal">
					<template #icon>
						<DeleteOutlineIcon :size="20" />
					</template>
					<span class="btn-text">{{ $texts.common.delete() }}</span>
				</NcButton>
			</template>
		</AppPageHeader>

		<form class="budget-form settings-section" @submit.prevent="saveGoalDetails">
			<section class="budget-form-section">
				<h3>{{ $texts.settings.generalTitle() }}</h3>
				<div class="form-grid two-columns">
					<div class="form-group">
						<label for="budget-name">{{ $texts.common.name() }}</label>
						<input id="budget-name" ref="nameInput" v-model="form.name" class="form-control" type="text" :placeholder="$texts.budgetGoals.namePlaceholderFlexibleFood()" required />
					</div>
					<div class="form-group amount-group">
						<label for="budget-amount">{{ $texts.budgetGoals.budget() }} ({{ displayCurrency }})</label>
						<input id="budget-amount" v-model="form.amount" class="form-control" inputmode="decimal" type="text" placeholder="0.00" required />
					</div>
				</div>

				<div class="form-grid two-columns">
					<div class="form-group">
						<label for="budget-period">{{ $texts.budgetGoals.period() }}</label>
						<select id="budget-period" v-model="form.period" class="form-control select-control">
							<option value="year">{{ $texts.budgetGoals.annual() }}</option>
							<option value="month">{{ $texts.budgetGoals.monthly() }}</option>
						</select>
					</div>
					<div class="form-group">
						<label for="budget-mode">{{ $texts.budgetGoals.type() }}</label>
						<select id="budget-mode" v-model="form.mode" class="form-control select-control">
							<option value="flexible">{{ $texts.budgetGoals.flexibleGoalWithForecast() }}</option>
							<option value="hard">{{ $texts.budgetGoals.fixedLimit() }}</option>
						</select>
					</div>
				</div>

				<div class="budget-section-actions">
					<NcButton class="budget-save-button" type="primary" native-type="submit" :disabled="saving || !canSave">
						{{ saving ? $texts.common.saveBusy() : $texts.common.save() }}
					</NcButton>
				</div>
			</section>
		</form>

		<section class="budget-form budget-criteria-section settings-section">
			<section class="budget-form-section">
				<div class="section-title-row">
					<div>
						<h3>{{ $texts.budgetGoals.criteria() }}</h3>
						<p>
							{{ $texts.budgetGoals.criteriaHint() }}{{ $texts.budgetGoals.multipleRowsHint() }}
						</p>
					</div>
				</div>

				<div class="budget-criteria-table" role="table" :aria-label="$texts.budgetGoals.criteriaTable()">
					<div class="budget-criteria-row budget-criteria-header" role="row">
						<div role="columnheader">{{ $texts.budgetGoals.areas() }}</div>
						<div role="columnheader">{{ $texts.budgetGoals.categories() }}</div>
						<div role="columnheader">{{ $texts.budgetGoals.labels() }}</div>
						<div role="columnheader" class="criteria-action-column"></div>
					</div>
					<div class="budget-criteria-row budget-criteria-input-row" role="row">
						<div role="cell">
							<select
								v-model="newRule.projectId"
								:aria-label="$texts.budgetGoals.areaForNewCriterion()"
								class="form-control select-control"
								@change="onRuleProjectChange(newRule)">
								<option value="">*</option>
								<option v-for="project in activeProjects" :key="project.id" :value="String(project.id)">
									{{ project.name }}
								</option>
							</select>
						</div>
						<div role="cell">
							<select
								v-model="newRule.categoryId"
								:aria-label="$texts.budgetGoals.categoryForNewCriterion()"
								class="form-control select-control">
								<option value="">*</option>
								<option v-for="category in categoryOptionsForRule(newRule)" :key="category.id" :value="String(category.id)">
									{{ categoryOptionLabel(category) }}
								</option>
							</select>
						</div>
						<div role="cell">
							<select v-model="newRule.tag" :aria-label="$texts.budgetGoals.labelForNewCriterion()" class="form-control select-control">
								<option value="">*</option>
								<option v-for="tag in availableTags" :key="tag.value" :value="tag.value">
									{{ tag.label }}
								</option>
							</select>
						</div>
						<div role="cell" class="criteria-action-column">
							<NcButton class="criteria-icon-button cobudget-toolbar-icon-button" :disabled="savingCriteria || isEmptyRule(newRule)" :aria-label="$texts.budgetGoals.addCriterion()" @click.prevent="addRule">
								<template #icon>
									<PlusIcon :size="20" />
								</template>
							</NcButton>
						</div>
					</div>
					<div v-if="rules.length === 0" class="budget-criteria-empty" role="row">
						<div role="cell">
							{{ $texts.budgetGoals.noCriteriaAllExpenses() }}
						</div>
					</div>
					<div v-for="(rule, index) in rules" :key="rule.key" class="budget-criteria-row budget-criteria-data-row" role="row">
						<div role="cell">{{ projectLabelForRule(rule) }}</div>
						<div role="cell">{{ categoryLabelForRule(rule) }}</div>
						<div role="cell">{{ tagLabelForRule(rule) }}</div>
						<div role="cell" class="criteria-action-column">
							<NcButton class="criteria-icon-button cobudget-toolbar-icon-button" :disabled="savingCriteria" :aria-label="$texts.budgetGoals.removeCriterion()" @click.prevent="removeRule(index)">
								<template #icon>
									<DeleteOutlineIcon :size="20" />
								</template>
							</NcButton>
						</div>
					</div>
				</div>

			</section>
		</section>

		<ConfirmModal
			:show="deleteConfirmOpen"
			:title="$texts.budgetGoals.deleteTitle()"
			:message="deleteConfirmMessage"
			:confirm-label="$texts.budgetGoals.deleteConfirm()"
			confirm-variant="danger"
			:busy="deleting"
			@confirm="deleteGoal"
			@cancel="deleteConfirmOpen = false" />
	</div>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ConfirmModal from '../components/ConfirmModal.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import { showRequestError, showToast } from '../services/notifications'

let nextRuleKey = 0

const emptyRule = () => ({
	key: ++nextRuleKey,
	projectId: '',
	categoryId: '',
	tag: ''
})

export default {
	name: 'BudgetGoalFormView',
	components: {
		AppPageHeader,
		NcButton,
		PlusIcon,
		DeleteOutlineIcon,
		ArrowLeftIcon,
		ConfirmModal
	},
	props: {
		id: {
			type: [String, Number],
			default: null
		}
	},
	data() {
		return {
			loading: false,
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
			return this.projects
				.filter(project => !this.isArchivedProject(project))
				.slice()
				.sort((a, b) => a.name.localeCompare(b.name))
		},
		availableTags() {
			return [
				this.$enableImportantPayments ? { value: 'important', label: this.$texts.labels.important() } : null,
				this.$enableReviewPayments ? { value: 'review', label: this.$texts.labels.review() } : null,
				this.$enableFixedCosts ? { value: 'fixedCosts', label: this.$texts.labels.fixedCosts() } : null,
				this.$enableChildRelated ? { value: 'childRelated', label: this.$texts.labels.children() } : null,
				this.$enableSubscriptions ? { value: 'subscriptions', label: this.$texts.labels.subscription() } : null,
				this.$enableTaxRelevant ? { value: 'taxRelevant', label: this.$texts.labels.taxRelevant() } : null
			].filter(Boolean)
		},
		canSave() {
			return this.form.name.trim() && this.parseAmount(this.form.amount) > 0
		},
		displayCurrency() {
			return this.$currency || '€'
		},
		deleteConfirmMessage() {
			const name = this.form.name.trim() || this.$texts.budgetGoals.deleteFallbackName()
			return this.$texts.budgetGoals.deleteNamedMessage(name)
		}
	},
	mounted() {
		this.fetchData()
		this.$nextTick(() => this.$refs.nameInput?.focus?.())
	},
	methods: {
		async fetchData() {
			this.loading = true
			try {
				const [projectsRes, categories] = await Promise.all([
					this.$enableProjects ? axios.get(generateUrl('/apps/cobudget/api/projects')) : Promise.resolve({ data: [] }),
					this.fetchAllCategories()
				])
				this.projects = Array.isArray(projectsRes.data) ? projectsRes.data : []
				this.categories = categories

				if (this.isEdit) {
					const goalsRes = await axios.get(generateUrl('/apps/cobudget/api/budgets'))
					const goals = Array.isArray(goalsRes.data) ? goalsRes.data : []
					const goal = goals.find(item => Number(item.id) === Number(this.id))
					if (!goal) {
						showToast(this.$texts.budgetGoals.notFound(), 'error')
						this.goBack()
						return
					}
					this.applyGoal(goal)
				}
			} catch (error) {
				showRequestError(error, this.$texts.budgetGoals.loadError(), 'Failed to fetch budget goal form data')
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

			try {
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
			} catch (error) {
				return this.uniqueCategories(categories)
			}
		},
		uniqueCategories(categories) {
			const seen = new Set()
			return categories
				.filter(category => category?.type === 'expense')
				.filter(category => {
					const id = Number(category.id)
					if (!id || seen.has(id)) {
						return false
					}
					seen.add(id)
					return true
				})
				.sort((a, b) => a.name.localeCompare(b.name))
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
					projectId: rule.projectId ? String(rule.projectId) : '',
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
			if (!projectId) {
				return category.name
			}
			const project = this.projects.find(item => Number(item.id) === projectId)
			return project ? `${category.name} (${project.name})` : category.name
		},
		projectLabelForRule(rule) {
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
			return !rule.projectId && !rule.categoryId && !rule.tag
		},
		async addRule() {
			if (this.isEmptyRule(this.newRule)) {
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
					projectId: rule.projectId ? Number(rule.projectId) : null,
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
		async saveGoalDetails() {
			if (!this.canSave || this.saving) {
				return
			}

			this.saving = true
			const payload = this.currentDetailsPayload(this.criteriaPayload())

			try {
				if (this.isEdit) {
					const response = await axios.put(generateUrl('/apps/cobudget/api/budgets/{id}', { id: this.id }), payload)
					this.applyGoal(response.data)
					showToast(this.$texts.budgetGoals.saved())
				} else {
					const response = await axios.post(generateUrl('/apps/cobudget/api/budgets'), payload)
					this.applyGoal(response.data)
					showToast(this.$texts.budgetGoals.created())
					if (response.data?.id) {
						this.$router.replace({ name: 'budget-edit', params: { id: response.data.id } })
					}
				}
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
			} catch (error) {
				showRequestError(error, this.$texts.budgetGoals.saveError(), 'Failed to save budget goal')
			} finally {
				this.saving = false
			}
		},
		async saveCriteriaRules(nextRules, afterSuccess = null) {
			if (!this.isEdit || this.savingCriteria) {
				return
			}

			this.savingCriteria = true
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
				showRequestError(error, this.$texts.budgetGoals.saveError(), 'Failed to save budget goal criteria')
			} finally {
				this.savingCriteria = false
			}
		},
		askDeleteGoal() {
			this.deleteConfirmOpen = true
		},
		async deleteGoal() {
			if (!this.isEdit || this.deleting) {
				return
			}

			this.deleting = true
			try {
				await axios.delete(generateUrl('/apps/cobudget/api/budgets/{id}', { id: this.id }))
				showToast(this.$texts.budgetGoals.deleted())
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
				this.goBack()
			} catch (error) {
				showRequestError(error, this.$texts.budgetGoals.deleteError(), 'Failed to delete budget goal')
			} finally {
				this.deleting = false
				this.deleteConfirmOpen = false
			}
		},
		goBack() {
			this.$router.push({ name: 'budgets' })
		}
	}
}
</script>

<style scoped>
.budget-goal-form-view {
	width: 100%;
}

.budget-delete-button,
:deep(.budget-delete-button.button-vue),
.budget-delete-button :deep(.button-vue) {
	min-width: 120px !important;
	width: auto !important;
	min-height: var(--cobudget-button-height) !important;
	padding: 0 20px !important;
	background: var(--cobudget-page-background, #fff) !important;
	border: 1px solid var(--cobudget-error) !important;
	color: var(--cobudget-error) !important;
	box-shadow: none !important;
}

.budget-delete-button:hover,
.budget-delete-button:focus-visible,
:deep(.budget-delete-button.button-vue:hover),
:deep(.budget-delete-button.button-vue:focus-visible),
.budget-delete-button :deep(.button-vue:hover),
.budget-delete-button :deep(.button-vue:focus-visible) {
	background: var(--cobudget-error) !important;
	border: 1px solid var(--cobudget-error) !important;
	color: var(--color-primary-text, #fff) !important;
}

@media (max-width: 768px) {
	.budget-delete-button,
	:deep(.budget-delete-button.button-vue),
	.budget-delete-button :deep(.button-vue) {
		min-width: var(--cobudget-icon-button-size) !important;
		width: var(--cobudget-icon-button-size) !important;
		min-height: var(--cobudget-icon-button-size) !important;
		height: var(--cobudget-icon-button-size) !important;
		padding: 0 !important;
	}

	.budget-delete-button .btn-text,
	.budget-delete-button :deep(.button-vue__text),
	:deep(.budget-delete-button.button-vue .button-vue__text) {
		display: none !important;
	}

	.budget-delete-button :deep(.button-vue__icon),
	:deep(.budget-delete-button.button-vue .button-vue__icon) {
		margin: 0 !important;
	}
}

.budget-form {
	width: min(900px, calc(100% - 56px));
	margin: 0 28px 0;
}

.budget-form-section {
	margin-bottom: 26px;
	padding-bottom: 22px;
}

.budget-form-section h3 {
	margin: 0 0 18px;
	font-size: var(--cobudget-font-section);
	line-height: 1.25;
}

.form-grid {
	display: grid;
	gap: 16px;
	margin-bottom: 16px;
}

.form-grid.two-columns {
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.form-group {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-width: 0;
}

.form-group label {
	display: block;
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
	color: var(--color-main-text, #333);
}

.form-control {
	width: 100%;
	min-height: 44px;
	box-sizing: border-box;
	padding: 8px 12px;
	border: 1px solid var(--cobudget-border-strong, #d0d0d0);
	border-radius: 6px;
	background: var(--cobudget-page-background, #fff);
	color: var(--color-main-text, #222);
	font-size: var(--cobudget-font-md);
}

.select-control {
	appearance: auto;
}

.amount-group input {
	text-align: right;
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	font-weight: 700;
}

.budget-section-actions {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	margin-top: 6px;
}

.budget-save-button,
:deep(.budget-save-button.button-vue),
.budget-save-button :deep(.button-vue) {
	background-color: var(--color-primary-element, var(--color-primary, #0082c9)) !important;
	color: var(--color-primary-text, #fff) !important;
	border-color: var(--color-primary-element, var(--color-primary, #0082c9)) !important;
}

.budget-save-button:hover:not(:disabled),
.budget-save-button:focus-visible:not(:disabled),
:deep(.budget-save-button.button-vue:hover:not(:disabled)),
:deep(.budget-save-button.button-vue:focus-visible:not(:disabled)),
.budget-save-button :deep(.button-vue:hover:not(:disabled)),
.budget-save-button :deep(.button-vue:focus-visible:not(:disabled)) {
	background-color: var(--color-primary-hover, var(--color-primary, #0082c9)) !important;
	color: var(--color-primary-text, #fff) !important;
}

.section-title-row {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 16px;
}

.section-title-row h3 {
	margin-bottom: 6px;
}

.section-title-row p {
	margin: 0;
	color: var(--color-text-maxcontrast, #666);
	line-height: 1.4;
}

.budget-criteria-table {
	overflow: hidden;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-page-background, #fff);
}

.budget-criteria-row {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) 56px;
	gap: 10px;
	align-items: center;
	padding: 10px 10px;
	border-top: 1px solid var(--cobudget-border, #e5e5e5);
}

.budget-criteria-row:first-child {
	border-top: 0;
}

.budget-criteria-header {
	background: var(--cobudget-surface-muted, #f5f5f5);
  color: var(--color-text-maxcontrast, #888);
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
  text-align: left;
  padding: 4px 10px;
}

.budget-criteria-input-row {
	background: var(--cobudget-page-background, #fff);
}

.budget-criteria-data-row {
	min-height: 54px;
	font-weight: 600;
}

.budget-criteria-empty {
	padding: 18px 14px;
	border-top: 1px solid var(--cobudget-border, #e5e5e5);
	color: var(--color-text-maxcontrast, #666);
	font-style: italic;
}

.criteria-action-column {
	display: flex;
	justify-content: center;
}

.criteria-icon-button {
	min-width: 44px;
	height: 44px;
	min-height: 44px;
}

.criteria-icon-button,
:deep(.criteria-icon-button.button-vue),
.criteria-icon-button :deep(.button-vue) {
	min-width: 44px !important;
	height: 44px !important;
	min-height: 44px !important;
	margin: 0 !important;
	border-color: transparent !important;
	background-color: transparent !important;
	box-shadow: none !important;
}

@media (max-width: 900px) {
	.form-grid.two-columns {
		grid-template-columns: 1fr;
	}

	.section-title-row {
		flex-direction: column;
	}

	.budget-criteria-row {
		grid-template-columns: 1fr;
	}

	.budget-criteria-header {
		display: none;
	}

	.criteria-action-column {
		justify-content: flex-start;
	}
}

@media (max-width: 768px) {
	.budget-form {
		width: 100%;
		margin: 0 0 30px;
	}

	.budget-section-actions {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}
</style>
