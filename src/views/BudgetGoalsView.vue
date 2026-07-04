<template>
	<div class="budget-goals-view">
		<AppPageHeader
			class="budget-page-header"
			:title="$texts.budgetGoals.title()"
			:subtitle="$texts.budgetGoals.settingsHint()">
			<template #actions>
				<NcButton
					class="budget-new-button"
					variant="primary"
					:aria-label="$texts.budgetGoals.newTitle()"
					:title="$texts.budgetGoals.newTitle()"
					@click="openNewGoal">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ $texts.budgetGoals.newTitle() }}
				</NcButton>
			</template>
		</AppPageHeader>

		<div class="budget-goals-section">
				<div v-if="loading" class="budget-empty-state">
					{{ $texts.budgetGoals.loading() }}
				</div>
				<NcEmptyContent
					v-else-if="goals.length === 0"
					class="budget-empty-content"
					:name="$texts.budgetGoals.emptyTitle()"
					:description="$texts.budgetGoals.emptyDescription()">
					<template #icon>
						<WalletIcon :size="64" />
					</template>
					<template #action>
						<NcButton variant="primary" @click="openNewGoal">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
							{{ $texts.budgetGoals.newTitle() }}
						</NcButton>
					</template>
				</NcEmptyContent>
			<div v-else class="budget-goal-list">
				<article
					v-for="goal in goals"
					:key="goal.id"
						class="budget-goal-card"
						role="button"
						tabindex="0"
						:aria-label="$texts.budgetGoals.editNamed(goal.name)"
					@click="editGoal(goal)"
					@keydown.enter.prevent="editGoal(goal)"
					@keydown.space.prevent="editGoal(goal)">
					<div class="budget-card-header">
						<div>
							<h3>{{ goal.name }}</h3>
							<p>{{ modeLabel(goal.mode) }} · {{ periodLabel(goal.period) }}</p>
						</div>
							<div class="budget-card-amount">
								{{ formatCurrency(goal.evaluation?.spent_cents || 0) }}
								<span>{{ $texts.budgetGoals.amountOf(formatCurrency(goal.amount_cents || 0)) }}</span>
							</div>
					</div>

					<div class="budget-progress-track" :aria-label="progressAriaLabel(goal)">
						<div
							class="budget-progress-fill"
							:class="statusClass(goal.evaluation?.status)"
							:style="{ width: progressWidth(goal) }"></div>
					</div>

					<div class="budget-metrics">
							<div>
								<span>{{ $texts.budgetGoals.plannedUntilToday() }}</span>
								<strong>{{ formatCurrency(goal.evaluation?.planned_cents || 0) }}</strong>
							</div>
							<div>
								<span>{{ $texts.budgetGoals.buffer() }}</span>
								<strong :class="{ negative: (goal.evaluation?.buffer_cents || 0) < 0 }">
									{{ formatCurrency(goal.evaluation?.buffer_cents || 0) }}
								</strong>
							</div>
							<div>
								<span>{{ $texts.budgetGoals.forecast() }}</span>
								<strong>{{ formatCurrency(goal.evaluation?.forecast_cents || 0) }}</strong>
							</div>
					</div>
				</article>
			</div>
		</div>
	</div>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import WalletIcon from 'vue-material-design-icons/Wallet.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import { showRequestError } from '../services/notifications'

export default {
	name: 'BudgetGoalsView',
		components: {
			AppPageHeader,
			NcButton,
			NcEmptyContent,
			PlusIcon,
			WalletIcon
		},
	data() {
		return {
			loading: false,
			goals: []
		}
	},
	mounted() {
		this.fetchData()
	},
	methods: {
		async fetchData() {
			this.loading = true
			try {
				const goalsRes = await axios.get(generateUrl('/apps/cobudget/api/budgets'))
				this.goals = Array.isArray(goalsRes.data) ? goalsRes.data : []
				} catch (error) {
					showRequestError(error, this.$texts.budgetGoals.loadError(), 'Failed to fetch budget goals')
			} finally {
				this.loading = false
			}
		},
		openNewGoal() {
			this.$router.push({ name: 'budget-new' })
		},
		editGoal(goal) {
			this.$router.push({ name: 'budget-edit', params: { id: String(goal.id) } })
		},
			periodLabel(period) {
				return period === 'month' ? this.$texts.budgetGoals.monthly() : this.$texts.budgetGoals.annual()
			},
			modeLabel(mode) {
				return mode === 'hard' ? this.$texts.budgetGoals.fixedLimit() : this.$texts.budgetGoals.flexibleGoalWithForecast()
		},
		statusClass(status) {
			return {
				ok: status === 'ok',
				warning: status === 'warning',
				exceeded: status === 'exceeded'
			}
		},
		progressWidth(goal) {
			const percent = Number(goal.evaluation?.progress_percent || 0)
			return `${Math.min(100, Math.max(0, percent))}%`
		},
			progressAriaLabel(goal) {
				return this.$texts.budgetGoals.percentUsed(Math.round(Number(goal.evaluation?.progress_percent || 0)))
			},
		formatCurrency(cents) {
			return this.$formatMoneyFromCents(cents)
		}
	}
}
</script>

<style scoped>
.budget-goals-view {
	width: 100%;
}

.budget-new-button,
:deep(.budget-new-button.button-vue),
.budget-new-button :deep(.button-vue) {
	min-height: var(--cobudget-button-height, 44px) !important;
	height: var(--cobudget-button-height, 44px) !important;
	width: auto !important;
	min-width: auto !important;
	padding-inline: 14px !important;
	background-color: var(--cobudget-primary) !important;
	color: var(--cobudget-primary-text) !important;
	border-color: var(--cobudget-primary) !important;
}

.budget-new-button :deep(.button-vue__text),
:deep(.budget-new-button.button-vue .button-vue__text) {
	display: inline-flex !important;
}

.budget-new-button:hover,
.budget-new-button:focus-visible,
:deep(.budget-new-button.button-vue:hover),
:deep(.budget-new-button.button-vue:focus-visible),
.budget-new-button :deep(.button-vue:hover),
.budget-new-button :deep(.button-vue:focus-visible) {
	background-color: var(--cobudget-primary-hover) !important;
	color: var(--cobudget-primary-text) !important;
	border-color: var(--cobudget-primary-hover) !important;
}

@media (max-width: 768px) {
	.budget-new-button,
	:deep(.budget-new-button.button-vue),
	.budget-new-button :deep(.button-vue) {
		width: var(--cobudget-icon-button-size, 44px) !important;
		min-width: var(--cobudget-icon-button-size, 44px) !important;
		padding: 0 !important;
	}

	.budget-new-button :deep(.button-vue__text),
	:deep(.budget-new-button.button-vue .button-vue__text) {
		display: none !important;
	}
}

.budget-goals-section {
	width: min(900px, calc(100% - 56px));
	margin: 0 28px 40px;
}

.budget-empty-state {
	padding: 32px 20px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	color: var(--color-text-maxcontrast, #666);
	text-align: center;
	background: var(--cobudget-page-background, #fff);
}

.budget-goal-list {
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.budget-goal-card {
	padding: 20px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-page-background, #fff);
	cursor: pointer;
	transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.budget-goal-card:hover,
.budget-goal-card:focus-visible {
  border: 1px solid var(--color-primary-element, #0082c9);
  //background: var(--color-background-hover, #f5f5f5);
  box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
	outline: none;
}

.budget-card-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 18px;
}

.budget-card-header h3 {
	margin: 0;
	font-size: var(--cobudget-font-xl);
	line-height: 1.25;
}

.budget-card-header p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast, #666);
}

.budget-card-amount {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	font-weight: 700;
	font-size: var(--cobudget-font-xl);
	white-space: nowrap;
}

.budget-card-amount span {
	margin-top: 4px;
	color: var(--color-text-maxcontrast, #666);
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
}

.budget-progress-track {
	height: 10px;
	margin: 18px 0 14px;
	overflow: hidden;
	border-radius: 999px;
	background: var(--color-background-darker, #ededed);
}

.budget-progress-fill {
	height: 100%;
	border-radius: inherit;
	transition: width 0.2s ease;
}

.budget-progress-fill.ok {
	background: #10b981;
}

.budget-progress-fill.warning {
	background: #ffc92b;
}

.budget-progress-fill.exceeded {
	background: var(--cobudget-error);
}

.budget-metrics {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 10px;
	margin-bottom: 14px;
}

.budget-metrics div {
	padding: 10px 12px;
	border-radius: 8px;
	background: var(--cobudget-surface-muted, #f5f5f5);
}

.budget-metrics span {
	display: block;
	margin-bottom: 4px;
	color: var(--color-text-maxcontrast, #666);
	font-size: var(--cobudget-font-compact);
}

.budget-metrics strong {
	font-size: var(--cobudget-font-ui);
}

.budget-metrics strong.negative {
	color: var(--cobudget-error);
}

@media (max-width: 768px) {
	.budget-goals-section {
		width: 100%;
		margin: 0 0 30px;
	}

	.budget-card-header {
		flex-direction: column;
		align-items: stretch;
	}

	.budget-card-amount {
		align-items: flex-start;
	}

	.budget-metrics {
		grid-template-columns: 1fr;
	}
}
</style>
