<template>
	<section class="budget-goals-settings">
		<div class="section-heading">
			<h3>{{ $texts.budgetGoals.title() }}</h3>
			<p class="settings-hint">
				{{ $texts.budgetGoals.settingsHint() }}
			</p>
		</div>

		<form class="budget-form" @submit.prevent="saveGoal">
			<h4>{{ editingId ? $texts.budgetGoals.editTitle() : $texts.budgetGoals.newTitle() }}</h4>
			<div class="budget-form-grid">
				<div class="form-group">
					<label for="budget-name">{{ $texts.common.name() }}</label>
					<input id="budget-name" v-model="form.name" class="form-control" type="text" maxlength="128" :placeholder="$texts.budgetGoals.namePlaceholder()" required>
				</div>
				<div class="form-group">
					<label for="budget-amount">{{ $texts.budgetGoals.budget() }}</label>
					<input id="budget-amount" v-model="form.amount" class="form-control amount-input" type="number" min="0.01" step="0.01" placeholder="200.00" required>
				</div>
				<div class="form-group">
					<label for="budget-period">{{ $texts.budgetGoals.period() }}</label>
					<select id="budget-period" v-model="form.period" class="form-control select-control">
						<option value="year">{{ $texts.budgetGoals.year() }}</option>
						<option value="month">{{ $texts.budgetGoals.month() }}</option>
					</select>
				</div>
				<div class="form-group">
					<label for="budget-mode">{{ $texts.budgetGoals.evaluation() }}</label>
					<select id="budget-mode" v-model="form.mode" class="form-control select-control">
						<option value="flexible">{{ $texts.budgetGoals.flexibleForecast() }}</option>
						<option value="hard">{{ $texts.budgetGoals.hardLimit() }}</option>
					</select>
				</div>
			</div>

			<div class="criteria-area">
				<p class="criteria-note">{{ $texts.budgetGoals.noSelectionAllExpenses() }}</p>
				<div class="criteria-columns">
					<div class="criteria-block">
						<h5>{{ $texts.budgetGoals.categories() }}</h5>
						<label v-for="category in categoryOptions" :key="category.id" class="criteria-check">
							<input v-model="form.criteria.categoryIds" type="checkbox" :value="Number(category.id)">
							<span>{{ category.name }}</span>
						</label>
						<p v-if="categoryOptions.length === 0" class="empty-note">{{ $texts.budgetGoals.noCategories() }}</p>
					</div>
					<div v-if="enableProjects" class="criteria-block">
						<h5>{{ $texts.budgetGoals.areas() }}</h5>
						<label v-for="project in projectOptions" :key="project.id" class="criteria-check">
							<input v-model="form.criteria.projectIds" type="checkbox" :value="Number(project.id)">
							<span>{{ project.name }}</span>
						</label>
						<p v-if="projectOptions.length === 0" class="empty-note">{{ $texts.budgetGoals.noActiveAreas() }}</p>
					</div>
					<div class="criteria-block">
						<h5>{{ $texts.budgetGoals.labels() }}</h5>
						<label v-for="tag in tagOptions" :key="tag.value" class="criteria-check">
							<input v-model="form.criteria.tags" type="checkbox" :value="tag.value">
							<span>{{ tag.label }}</span>
						</label>
					</div>
				</div>
			</div>

			<div class="form-actions">
				<NcButton v-if="editingId" type="secondary" native-type="button" :disabled="saving" @click="resetForm">{{ $texts.common.cancel() }}</NcButton>
				<NcButton type="primary" native-type="submit" :disabled="saving || !canSave">
					{{ editingId ? $texts.common.save() : $texts.common.add() }}
				</NcButton>
			</div>
		</form>

		<div v-if="loading" class="empty-state">{{ $texts.budgetGoals.loading() }}</div>
		<div v-else-if="goals.length === 0" class="empty-state">{{ $texts.budgetGoals.empty() }}</div>
		<div v-else class="budget-list">
			<article v-for="goal in goals" :key="goal.id" class="budget-card" :class="`status-${goal.evaluation.status}`">
				<div class="budget-card-header">
					<div>
						<h4>{{ goal.name }}</h4>
						<p>{{ periodLabel(goal.period) }} · {{ modeLabel(goal.mode) }} · {{ criteriaLabel(goal.criteria) }}</p>
					</div>
					<strong>{{ formatAmount(goal.amount) }}</strong>
				</div>
				<div class="budget-progress" aria-hidden="true">
					<span :style="{ width: progressWidth(goal) }"></span>
				</div>
				<div class="budget-facts">
					<div>
						<span>{{ $texts.budgetGoals.spent() }}</span>
						<strong>{{ formatAmount(goal.evaluation.spent) }}</strong>
					</div>
					<div>
						<span>{{ $texts.budgetGoals.plannedUntilToday() }}</span>
						<strong>{{ formatAmount(goal.evaluation.planned) }}</strong>
					</div>
					<div>
						<span>{{ goal.evaluation.buffer_cents >= 0 ? $texts.budgetGoals.buffer() : $texts.budgetGoals.overPlan() }}</span>
						<strong :class="goal.evaluation.buffer_cents >= 0 ? 'positive' : 'negative'">
							{{ formatAmount(Math.abs(goal.evaluation.buffer)) }}
						</strong>
					</div>
					<div>
						<span>{{ $texts.budgetGoals.forecast() }}</span>
						<strong>{{ formatAmount(goal.evaluation.forecast) }}</strong>
					</div>
				</div>
				<div class="budget-card-actions">
					<NcButton type="secondary" @click="editGoal(goal)">{{ $texts.budgetGoals.edit() }}</NcButton>
					<NcButton type="error" @click="requestDelete(goal)">{{ $texts.budgetGoals.delete() }}</NcButton>
				</div>
			</article>
		</div>

		<ConfirmModal
			:show="!!goalToDelete"
			:title="$texts.budgetGoals.deleteTitle()"
			:message="goalToDelete ? $texts.budgetGoals.deleteMessage(goalToDelete.name) : ''"
			:confirm-label="$texts.budgetGoals.deleteConfirm()"
			confirm-variant="danger"
			@confirm="deleteGoal"
			@cancel="goalToDelete = null" />
	</section>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import ConfirmModal from './ConfirmModal.vue'
import { showRequestError, showToast } from '../services/notifications'

const emptyForm = () => ({
	name: '',
	amount: '',
	period: 'year',
	mode: 'flexible',
	criteria: {
		categoryIds: [],
		projectIds: [],
		tags: [],
	},
})

export default {
	name: 'BudgetGoalsSettings',
	components: {
		NcButton,
		ConfirmModal,
	},
	props: {
		categories: {
			type: Array,
			default: () => [],
		},
		projects: {
			type: Array,
			default: () => [],
		},
		enableProjects: {
			type: Boolean,
			default: true,
		},
		enableImportantPayments: {
			type: Boolean,
			default: true,
		},
		enableReviewPayments: {
			type: Boolean,
			default: true,
		},
		enableFixedCosts: {
			type: Boolean,
			default: true,
		},
		enableChildRelated: {
			type: Boolean,
			default: true,
		},
		enableSubscriptions: {
			type: Boolean,
			default: true,
		},
		enableTaxRelevant: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			goals: [],
			form: emptyForm(),
			editingId: null,
			loading: false,
			saving: false,
			goalToDelete: null,
		}
	},
	computed: {
		canSave() {
			return this.form.name.trim() && this.$parseAmount(this.form.amount) > 0
		},
		categoryOptions() {
			return this.categories
				.filter(category => category.type === 'expense' && !category.is_hidden)
				.slice()
				.sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { sensitivity: 'base' }))
		},
		projectOptions() {
			return this.projects
				.filter(project => !(project.is_archived === true || project.is_archived === 1 || project.is_archived === '1'))
				.slice()
				.sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { sensitivity: 'base' }))
		},
		tagOptions() {
			return [
				this.enableImportantPayments ? { value: 'important', label: this.$texts.budgetGoals.importantPayments() } : null,
				this.enableReviewPayments ? { value: 'review', label: this.$texts.budgetGoals.reviewPayments() } : null,
				this.enableFixedCosts ? { value: 'fixedCosts', label: this.$texts.budgetGoals.fixedCosts() } : null,
				this.enableChildRelated ? { value: 'childRelated', label: this.$texts.budgetGoals.children() } : null,
				this.enableSubscriptions ? { value: 'subscriptions', label: this.$texts.budgetGoals.subscriptions() } : null,
				this.enableTaxRelevant ? { value: 'taxRelevant', label: this.$texts.budgetGoals.taxRelevant() } : null,
			].filter(Boolean)
		},
	},
	mounted() {
		this.fetchGoals()
	},
	methods: {
		async fetchGoals() {
			this.loading = true
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/budgets'))
				this.goals = Array.isArray(response.data) ? response.data : []
			} catch (e) {
				showRequestError(e, this.$texts.budgetGoals.loadError(), 'Failed to fetch budget goals')
				this.goals = []
			} finally {
				this.loading = false
			}
		},
		async saveGoal() {
			if (!this.canSave) {
				return
			}
			this.saving = true
			const payload = {
				name: this.form.name.trim(),
				amount: this.$parseAmount(this.form.amount),
				period: this.form.period,
				mode: this.form.mode,
				criteria: {
					categoryIds: this.form.criteria.categoryIds.map(Number),
					projectIds: this.form.criteria.projectIds.map(Number),
					tags: [...this.form.criteria.tags],
				},
			}
			try {
				if (this.editingId) {
					await axios.put(generateUrl(`/apps/cobudget/api/budgets/${this.editingId}`), payload)
					showToast(this.$texts.budgetGoals.saved())
				} else {
					await axios.post(generateUrl('/apps/cobudget/api/budgets'), payload)
					showToast(this.$texts.budgetGoals.created())
				}
				this.resetForm()
				await this.fetchGoals()
			} catch (e) {
				showRequestError(e, this.$texts.budgetGoals.saveError(), 'Failed to save budget goal')
			} finally {
				this.saving = false
			}
		},
		editGoal(goal) {
			this.editingId = goal.id
			this.form = {
				name: goal.name || '',
				amount: this.$formatInputAmount(goal.amount || 0),
				period: goal.period || 'year',
				mode: goal.mode || 'flexible',
				criteria: {
					categoryIds: [...(goal.criteria?.categoryIds || [])].map(Number),
					projectIds: [...(goal.criteria?.projectIds || [])].map(Number),
					tags: [...(goal.criteria?.tags || [])],
				},
			}
		},
		resetForm() {
			this.editingId = null
			this.form = emptyForm()
		},
		requestDelete(goal) {
			this.goalToDelete = goal
		},
		async deleteGoal() {
			if (!this.goalToDelete) {
				return
			}
			const goal = this.goalToDelete
			this.goalToDelete = null
			this.saving = true
			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/budgets/${goal.id}`))
				if (this.editingId === goal.id) {
					this.resetForm()
				}
				await this.fetchGoals()
				showToast(this.$texts.budgetGoals.deleted())
			} catch (e) {
				showRequestError(e, this.$texts.budgetGoals.deleteError(), 'Failed to delete budget goal')
			} finally {
				this.saving = false
			}
		},
		formatAmount(value) {
			return this.$formatMoney(value)
		},
		periodLabel(period) {
			return period === 'month' ? this.$texts.budgetGoals.monthlyBudget() : this.$texts.budgetGoals.yearlyBudget()
		},
		modeLabel(mode) {
			return mode === 'hard' ? this.$texts.budgetGoals.modeHard() : this.$texts.budgetGoals.modeFlexible()
		},
		criteriaLabel(criteria) {
			const count = (criteria?.categoryIds?.length || 0)
				+ (criteria?.projectIds?.length || 0)
				+ (criteria?.tags?.length || 0)
			return count === 0 ? this.$texts.budgetGoals.allExpenses() : this.$texts.budgetGoals.selectionCount(count)
		},
		progressWidth(goal) {
			const percent = Math.max(0, Math.min(100, Number(goal.evaluation?.progress_percent || 0)))
			return `${percent}%`
		},
	},
}
</script>

<style scoped>
.budget-goals-settings {
	display: flex;
	flex-direction: column;
	gap: 18px;
}

.section-heading h3 {
	font-size: var(--cobudget-font-section);
	margin: 0 0 6px;
}

.settings-hint,
.criteria-note,
.empty-note {
	color: var(--color-text-maxcontrast, #666);
	margin: 0;
}

.budget-form,
.budget-card {
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	padding: 18px;
}

.budget-form h4,
.budget-card h4 {
	font-size: var(--cobudget-font-md);
	margin: 0;
}

.budget-form-grid {
	display: grid;
	grid-template-columns: minmax(0, 1.4fr) minmax(120px, .8fr) minmax(120px, .8fr) minmax(0, 1fr);
	gap: 12px;
	margin-top: 14px;
}

.form-group label {
	display: block;
	font-size: var(--cobudget-font-compact);
	font-weight: 700;
	margin-bottom: 6px;
}

.form-control {
	width: 100%;
	height: 34px;
	padding: 0 12px;
	border: 1px solid var(--cobudget-border-strong, #888);
	border-radius: var(--border-radius, 6px);
	box-sizing: border-box;
	background: var(--cobudget-page-background, #fff);
	color: var(--color-main-text, #222);
	font-size: var(--cobudget-font-ui);
}

.amount-input {
	text-align: right;
}

.select-control {
	appearance: auto;
}

.criteria-area {
	margin-top: 16px;
}

.criteria-columns {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin-top: 10px;
}

.criteria-block {
	background: var(--cobudget-surface-muted, #f6f6f6);
	border-radius: var(--border-radius-large, 8px);
	padding: 12px;
}

.criteria-block h5 {
	font-size: var(--cobudget-font-compact);
	margin: 0 0 8px;
}

.criteria-check {
	align-items: center;
	display: flex;
	gap: 8px;
	font-size: var(--cobudget-font-base);
	min-height: 28px;
}

.form-actions,
.budget-card-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 16px;
}

.empty-state {
	border: 1px dashed var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	color: var(--color-text-maxcontrast, #666);
	padding: 18px;
	text-align: center;
}

.budget-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.budget-card {
	background: var(--cobudget-page-background, #fff);
}

.budget-card.status-warning {
	border-color: #f59e0b;
}

.budget-card.status-exceeded {
	border-color: var(--cobudget-error);
}

.budget-card-header {
	align-items: flex-start;
	display: flex;
	gap: 16px;
	justify-content: space-between;
}

.budget-card-header p {
	color: var(--color-text-maxcontrast, #666);
	margin: 4px 0 0;
}

.budget-progress {
	background: var(--color-background-dark, #eee);
	border-radius: 999px;
	height: 8px;
	margin-top: 14px;
	overflow: hidden;
}

.budget-progress span {
	background: var(--color-primary, #0082c9);
	display: block;
	height: 100%;
}

.status-warning .budget-progress span {
	background: #f59e0b;
}

.status-exceeded .budget-progress span {
	background: var(--cobudget-error);
}

.budget-facts {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 10px;
	margin-top: 14px;
}

.budget-facts div {
	background: var(--cobudget-surface-muted, #f6f6f6);
	border-radius: var(--border-radius, 6px);
	padding: 10px;
}

.budget-facts span {
	color: var(--color-text-maxcontrast, #666);
	display: block;
	font-size: var(--cobudget-font-sm);
	margin-bottom: 4px;
}

.budget-facts strong,
.budget-card-header strong {
	white-space: nowrap;
}

.positive {
	color: #0f8a45;
}

.negative {
	color: var(--cobudget-error);
}

@media (max-width: 768px) {
	.budget-form-grid,
	.criteria-columns,
	.budget-facts {
		grid-template-columns: 1fr;
	}

	.budget-card-header,
	.form-actions,
	.budget-card-actions {
		align-items: stretch;
		flex-direction: column;
	}
}
</style>
