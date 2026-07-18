<template>
	<div class="project-settlements settings-section">
		<AppPageHeader :title="project ? `${$texts.settlements.title()} - ${project.name}` : $texts.settlements.title()" />
		<div class="settings-header">
			<NcButton variant="tertiary" class="back-button" @click="goBackToProject" :aria-label="$texts.settlements.backToArea()">
				<template #icon>
					<ArrowLeftIcon :size="20" />
				</template>
				{{ $texts.settlements.backToArea() }}
			</NcButton>
			<p v-if="project" class="settings-hint">
				{{ $texts.settlements.hint() }}
			</p>
		</div>

		<div v-if="loading" class="empty-state">{{ $texts.settlements.loading() }}</div>
		<div v-else-if="error" class="empty-state error-state">{{ error }}</div>
		<div v-else-if="settlements.length === 0" class="empty-state">
			{{ $texts.settlements.empty() }}
		</div>
		<div v-else class="settlement-list">
			<details
				v-for="settlement in settlements"
				:key="settlement.id"
				class="settlement-card"
				:open="openSettlementId === settlement.id"
				@toggle="handleSettlementToggle(settlement.id, $event)">
				<summary>
					<div class="settlement-summary-title">
						<span>{{ $texts.settlements.settlementFrom(formatDateTime(settlement.createdAt)) }}</span>
						<span class="settlement-summary-meta">{{ settlementEntryCountLabel(settlement.entryCount) }}</span>
					</div>
					<span class="settlement-author">{{ $texts.settlements.createdBy(settlement.createdByDisplayName) }}</span>
				</summary>

				<div class="settlement-card-content">
					<section class="settlement-subsection">
						<h3>{{ $texts.settlements.repayments() }}</h3>
						<div v-if="settlement.transfers && settlement.transfers.length > 0" class="repayment-list">
							<div v-for="transfer in settlement.transfers" :key="`${settlement.id}-${transfer.fromUserId}-${transfer.toUserId}-${transfer.amountCents}`" class="repayment-row">
								<span class="repayment-person">{{ transfer.fromDisplayName }}</span>
								<span class="repayment-arrow">{{ $texts.settlements.paysTo() }}</span>
								<span class="repayment-person">{{ transfer.toDisplayName }}</span>
								<strong class="repayment-amount">{{ formatCurrency(transfer.amount, settlement.currency || $currency) }}</strong>
							</div>
						</div>
						<p v-else class="muted-text">{{ $texts.settlements.noRepaymentNeeded() }}</p>
					</section>

					<section class="settlement-subsection">
						<h3>{{ $texts.settlements.balanceBeforeSettlement() }}</h3>
						<div class="settlement-balance-grid">
							<div v-for="balance in settlement.balances" :key="`${settlement.id}-${balance.userId}`" class="settlement-balance-row">
								<span>{{ balance.displayName }}</span>
								<span>{{ balanceStatusText(balance.balance, settlement.currency || $currency) }}</span>
							</div>
						</div>
					</section>

					<section class="settlement-subsection">
						<h3>{{ $texts.settlements.entries() }}</h3>
						<EntryTable
							v-if="settlement.entries && settlement.entries.length > 0"
							mode="project"
							:entries="settlement.entries"
							:currency="settlement.currency || $currency"
							:date-label="$texts.entry.tableDate()"
							sort-by="date"
							sort-dir="desc"
							:enable-fixed-costs="$enableFixedCosts"
							:enable-subscriptions="$enableSubscriptions"
							:enable-child-related="$enableChildRelated"
							:enable-important-payments="$enableImportantPayments"
							:enable-review-payments="$enableReviewPayments"
							:enable-tax-relevant="$enableTaxRelevant"
							:actions-enabled="false"
							:archived="false"
							:group-by-date="false"
							:project-name-resolver="getProjectName"
							:project-style-resolver="getProjectTagStyle"
							:member-name-resolver="getMemberName"
							@sort="noop"
							@row-click="noop"
							@history="openEntryHistory" />
						<p v-else class="muted-text">{{ $texts.settlements.noEntries() }}</p>
					</section>
				</div>
			</details>
		</div>

		<EntryHistoryModal
			v-if="entryHistoryOpen"
			:history="entryHistoryRows"
			:loading="entryHistoryLoading"
			@close="closeEntryHistory" />
	</div>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import EntryTable from '../components/EntryTable.vue'
import EntryHistoryModal from '../components/EntryHistoryModal.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import { showRequestError } from '../services/notifications'
import { getAreaColorStyle } from '../utils/areaColor'

export default {
	name: 'ProjectSettlementsView',
	components: {
		AppPageHeader,
		ArrowLeftIcon,
		EntryTable,
		EntryHistoryModal,
		NcButton,
	},
	props: ['id'],
	data() {
		return {
			project: null,
			settlements: [],
			loading: true,
			error: '',
			openSettlementId: null,
			entryHistoryOpen: false,
			entryHistoryLoading: false,
			entryHistoryRows: [],
		}
	},
	computed: {
		projectId() {
			return this.id
		},
		memberNameMap() {
			const map = {}
			for (const member of this.project?.members || []) {
				map[member.id] = member.displayName
			}
			for (const settlement of this.settlements) {
				for (const balance of settlement.balances || []) {
					map[balance.userId] = balance.displayName
				}
				for (const entry of settlement.entries || []) {
					if (entry.user_id && entry.user_display_name) {
						map[entry.user_id] = entry.user_display_name
					}
				}
			}
			return map
		},
	},
	mounted() {
		this.fetchSettlements()
	},
	watch: {
		projectId() {
			this.fetchSettlements()
		},
	},
	methods: {
		async openEntryHistory(entry) {
			this.entryHistoryOpen = true
			this.entryHistoryLoading = true
			this.entryHistoryRows = []
			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/entries/${entry.id}/history`))
				this.entryHistoryRows = Array.isArray(response.data?.history) ? response.data.history : []
			} catch (error) {
				showRequestError(error, this.$texts.entry.historyFetchError(), 'Failed to fetch entry history')
			} finally {
				this.entryHistoryLoading = false
			}
		},
		closeEntryHistory() {
			this.entryHistoryOpen = false
			this.entryHistoryLoading = false
			this.entryHistoryRows = []
		},
		async fetchSettlements() {
			this.loading = true
			this.error = ''
			this.openSettlementId = null
			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/settlements`))
				this.project = response.data?.project || null
				this.settlements = Array.isArray(response.data?.settlements) ? response.data.settlements : []
			} catch (error) {
				this.error = this.$texts.settlements.loadError()
				showRequestError(error, this.error, 'Failed to fetch project settlements')
			} finally {
				this.loading = false
			}
		},
		goBackToProject() {
			this.$router.push({ name: 'project-detail', params: { id: this.projectId } })
		},
		handleSettlementToggle(id, event) {
			if (event.target.open) {
				this.openSettlementId = id
				return
			}
			if (this.openSettlementId === id) {
				this.openSettlementId = null
			}
		},
		formatDateTime(timestamp) {
			if (!timestamp) return '-'
			return new Date(timestamp * 1000).toLocaleString(undefined, {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
			})
		},
		formatCurrency(value, currency = null) {
			return this.$formatMoney(value, currency || this.$currency)
		},
		settlementEntryCountLabel(count) {
			const normalizedCount = parseInt(count || 0, 10)
			return this.$texts.settlements.entryCount(normalizedCount)
		},
		balanceStatusText(balance, currency) {
			const amount = parseFloat(balance || 0)
			if (amount > 0) {
				return this.$texts.settlements.getsBack(this.formatCurrency(amount, currency))
			}
			if (amount < 0) {
				return this.$texts.settlements.owes(this.formatCurrency(Math.abs(amount), currency))
			}
			return this.$texts.settlements.balanced()
		},
		getMemberName(userId) {
			return this.memberNameMap[userId] || userId
		},
		getProjectName(id) {
			if (this.project && String(this.project.id) === String(id)) {
				return this.project.name
			}
			return id ? `ID: ${id}` : ''
		},
		getProjectTagStyle(id) {
			if (!id || !this.project || String(this.project.id) !== String(id)) {
				return {}
			}
			return getAreaColorStyle(this.project.color)
		},
		noop() {},
	},
}
</script>

<style scoped>
.project-settlements {
	display: block;
	width: 100%;
	margin: 0;
	padding: 2px 0 calc(var(--default-grid-baseline, 4px) * 5);
	box-sizing: border-box;
}

.settings-header {
	width: min(900px, calc(100% - var(--default-grid-baseline, 4px) * 7 * 2));
	margin: 0 calc(var(--default-grid-baseline, 4px) * 7) 24px;
	box-sizing: border-box;
}

.settings-header h2 {
	margin: 0 0 8px !important;
	font-size: var(--cobudget-font-section);
}

.back-button {
	margin-left: 0px;
}

.settings-hint,
.muted-text,
.settlement-author,
.settlement-summary-meta {
	color: var(--color-text-maxcontrast, #777);
}

.settings-hint {
	margin: 0;
}

.settlement-list {
	display: flex;
	flex-direction: column;
	gap: 14px;
	width: calc(100% - var(--default-grid-baseline, 4px) * 14);
	margin: 0 calc(var(--default-grid-baseline, 4px) * 7);
	box-sizing: border-box;
}

.settlement-card {
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-page-background, #fff);
	overflow: hidden;
	transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.settlement-card:hover {
	border-color: var(--color-primary-element-light, var(--color-primary, #0082c9));
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

.settlement-card[open] {
	background: var(--cobudget-surface-muted, #f7f7f7);
}

.settlement-card summary {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 16px;
	padding: 14px 16px;
	cursor: pointer;
	font-weight: 700;
	transition: background-color 0.15s ease;
}

.settlement-card summary * {
	cursor: pointer;
}

.settlement-card summary:hover {
  border-color: var(--color-primary-element, var(--color-primary, #0082c9));
  background: var(--cobudget-surface-muted, #f5f5f5);
  outline: none;
}

.settlement-card[open] summary:hover {
	background: transparent;
}

.settlement-card summary:focus-visible {
	background: var(--cobudget-surface-muted, #f7f7f7);
	box-shadow: inset 0 0 0 2px var(--color-primary-element, var(--color-primary, #0082c9));
	outline: none;
}

.settlement-summary-title {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.settlement-card-content {
	display: flex;
	flex-direction: column;
	gap: 18px;
	padding: 0 16px 16px;
}

.settlement-subsection h3 {
	margin: 0 0 8px;
	font-size: var(--cobudget-font-md);
}

.repayment-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.repayment-row {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr) auto;
	align-items: center;
	gap: 10px;
	padding: 10px 12px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-page-background, #fff);
}

.repayment-person {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: 600;
}

.repayment-arrow {
	color: var(--color-text-maxcontrast, #777);
}

.repayment-amount {
	white-space: nowrap;
}

.settlement-balance-grid {
	display: flex;
	flex-direction: column;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-page-background, #fff);
	overflow: hidden;
}

.settlement-balance-row {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	padding: 10px 12px;
	border-bottom: 1px solid var(--cobudget-border, #eee);
}

.settlement-balance-row:last-child {
	border-bottom: none;
}

.settlement-balance-row span:last-child {
	text-align: right;
	white-space: nowrap;
}

.empty-state {
	width: calc(100% - var(--default-grid-baseline, 4px) * 14);
	margin: 0 calc(var(--default-grid-baseline, 4px) * 7);
	padding: 32px 20px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-page-background, #fff);
	text-align: center;
	color: var(--color-text-maxcontrast, #777);
	box-sizing: border-box;
}

.error-state {
	color: var(--cobudget-error);
}

@media (max-width: 768px) {
	.project-settlements {
		width: 100%;
		margin: 0;
		padding: 10px;
		box-sizing: border-box;
	}

	.settings-header,
	.settlement-list,
	.empty-state {
		width: 100%;
		margin-left: 0;
		margin-right: 0;
	}

	.back-button {
		margin-left: 0;
	}

	.settlement-card summary,
	.settlement-balance-row {
		grid-template-columns: 1fr;
	}

	.repayment-row {
		grid-template-columns: minmax(0, 1fr) auto;
		gap: 4px 10px;
		padding: 10px;
	}

	.repayment-person {
		min-width: 0;
		max-width: 100%;
	}

	.repayment-arrow {
		grid-column: 1;
		font-size: var(--cobudget-font-xs);
	}

	.repayment-amount {
		grid-column: 2;
		grid-row: 1 / span 3;
		align-self: center;
		text-align: right;
	}
}
</style>
