<template>
	<div class="budget-goals-view">
		<AppPageHeader
			class="budget-page-header"
			:title="$texts.budgetGoals.title()">
			<template #title>
				<ReportPeriodPicker navigation-only force-navigation budget-navigation :budget-goals="goals" :title="$texts.budgetGoals.title()" :period-enabled="false" label="" />
			</template>
			<template #creation>
				<NcButton
					class="budget-new-button mobile-create-fab cobudget-native-button"
					variant="primary"
					:aria-label="$texts.budgetGoals.newTitle()"
					:title="$texts.budgetGoals.newTitle()"
					@click="openNewGoal">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					<span class="creation-label-desktop">{{ $texts.common.newItem() }}</span>
					<span class="btn-text">{{ $texts.budgetGoals.newTitle() }}</span>
				</NcButton>
			</template>
		</AppPageHeader>

		<div class="budget-goals-section cobudget-overview-section">
				<p v-if="loading" class="budget-loading cobudget-form-hint" role="status"><NcLoadingIcon :size="20" /> {{ $texts.budgetGoals.loading() }}</p>
				<NcNoteCard v-else-if="loadError" type="error">
					<p>{{ loadError }}</p>
					<NcButton class="cobudget-native-button" variant="secondary" @click="fetchData">{{ $texts.common.retry() }}</NcButton>
				</NcNoteCard>
				<NcEmptyContent
					v-else-if="goals.length === 0"
					class="budget-empty-content"
					:name="$texts.budgetGoals.emptyTitle()"
					:description="$texts.budgetGoals.emptyDescription()">
					<template #icon>
						<WalletIcon :size="64" />
					</template>
					<template #action>
						<NcButton class="cobudget-native-button" variant="primary" @click="openNewGoal">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
							{{ $texts.budgetGoals.newTitle() }}
						</NcButton>
					</template>
				</NcEmptyContent>
			<div v-else class="budget-goal-list cobudget-overview-list">
				<article
					v-for="goal in goals"
					:key="goal.id"
						class="budget-goal-card cobudget-overview-entry cobudget-focusable"
						role="button"
						tabindex="0"
						:aria-label="$texts.budgetGoals.editNamed(goal.name)"
					@click="editGoal(goal)"
					@keydown.enter.prevent="editGoal(goal)"
					@keydown.space.prevent="editGoal(goal)">
					<div class="budget-card-header">
						<div>
							<h3 class="cobudget-subheading">{{ goal.name }}</h3>
							<p class="cobudget-form-hint">{{ modeLabel(goal.mode) }} · {{ periodLabel(goal.period) }}</p>
						</div>
							<div class="budget-card-amount">
								{{ formatCurrency(goal.evaluation?.spent_cents || 0) }}
								<span>{{ $texts.budgetGoals.amountOf(formatCurrency(goal.amount_cents || 0)) }}</span>
							</div>
					</div>

					<div class="budget-progress" role="progressbar" :aria-label="progressAriaLabel(goal)" :aria-valuenow="progressValue(goal)" aria-valuemin="0" aria-valuemax="100">
						<NcProgressBar :value="progressValue(goal)" size="medium" :color="progressColor(goal)" :error="goal.evaluation?.status === 'exceeded'" aria-hidden="true" />
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
import { REMOTE_DATA_CHANGED_EVENT, changeTouches } from '../services/dataSync'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import WalletIcon from 'vue-material-design-icons/Wallet.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import { extractError } from '../services/notifications'

export default {
	name: 'BudgetGoalsView',
		components: {
			ReportPeriodPicker,
			AppPageHeader,
			NcButton,
			NcEmptyContent,
			NcNoteCard,
			NcLoadingIcon,
			NcProgressBar,
			PlusIcon,
			WalletIcon
		},
	data() {
		return {
			loading: true,
			loadError: '',
			loadRequest: 0,
			goals: []
		}
	},
	mounted() {
		this.fetchData()
		window.addEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
	},
	beforeUnmount() {
		this.loadRequest++
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
	},
	methods: {
		async fetchData() {
			const request = ++this.loadRequest
			this.loading = true
			this.loadError = ''
			try {
				const goalsRes = await axios.get(generateUrl('/apps/cobudget/api/budgets'))
				if (request !== this.loadRequest) return
				this.goals = Array.isArray(goalsRes.data) ? goalsRes.data : []
			} catch (error) {
				if (request === this.loadRequest) this.loadError = extractError(error, this.$texts.budgetGoals.loadError())
			} finally {
				if (request === this.loadRequest) this.loading = false
			}
		},
		onRemoteDataChanged(event) {
			if (changeTouches(event?.detail, ['entries', 'projects', 'budgets'])) {
				this.fetchData()
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
		progressColor(goal) {
			return goal.evaluation?.status === 'warning' ? '#ffc92b' : '#10b981'
		},
		progressValue(goal) {
			const percent = Number(goal.evaluation?.progress_percent || 0)
			return Number.isFinite(percent) ? Math.round(Math.min(100, Math.max(0, percent))) : 0
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

.budget-loading {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}

.budget-card-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: var(--cobudget-card-gap);
}

.budget-card-header h3 {
	margin: 0;
	font-size: var(--cobudget-font-subheading);
	font-weight: var(--cobudget-font-weight-heading);
	color: var(--color-main-text);
	overflow-wrap: anywhere;
	line-height: 1.25;
}

.budget-card-header > div:first-child { min-width: 0; }

.budget-card-header p {
	overflow-wrap: anywhere;
	margin: 4px 0 0;
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
	color: var(--color-main-text);
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
}

.budget-progress {
	margin: var(--cobudget-card-gap) 0 var(--cobudget-content-gap);
}

.budget-metrics {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: var(--cobudget-card-gap);
}

.budget-metrics div {
	min-width: 0;
	overflow-wrap: anywhere;
}


.budget-metrics span {
	display: block;
	margin-bottom: 4px;
	color: var(--color-main-text);
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
}

.budget-metrics strong {
	font-size: var(--cobudget-font-ui);
}

.budget-metrics strong.negative {
	color: var(--cobudget-error);
}

@media (max-width: 768px) {
	.budget-goals-view { --default-clickable-area: var(--cobudget-mobile-touch-size); }

	.budget-card-header {
		flex-direction: column;
		align-items: stretch;
		gap: var(--cobudget-content-gap);
	}

	.budget-card-amount {
		flex-direction: row;
		flex-wrap: wrap;
		align-items: baseline;
		justify-content: space-between;
		gap: var(--default-grid-baseline, 4px) var(--cobudget-content-gap);
		white-space: normal;
		overflow-wrap: anywhere;
	}
	.budget-card-amount span { margin-top: 0; }
	.budget-progress { margin-block: var(--cobudget-content-gap); }

	.budget-metrics {
		grid-template-columns: 1fr;
		gap: calc(var(--default-grid-baseline, 4px) * 2);
	}
	.budget-metrics div { display: flex; justify-content: space-between; align-items: baseline; gap: var(--cobudget-content-gap); }
	.budget-metrics span { margin-bottom: 0; }
	.budget-metrics strong { flex: 0 1 auto; text-align: end; }

}
</style>
