<template>
	<div class="project-settlements settings-section">
		<AppPageHeader>
			<template #title>
				<span class="cobudget-page-title-with-back">
					<NcButton
						variant="tertiary"
						class="cobudget-header-back-button"
						:aria-label="$texts.settlements.backToArea()"
						:title="$texts.settlements.backToArea()"
						@click="goBackToProject">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
					</NcButton>
					<span>{{ $texts.settlements.title() }}</span>
				</span>
			</template>
		</AppPageHeader>
		<div class="settings-header">
			<p v-if="project" class="settings-hint">
				{{ $texts.settlements.hint() }}
			</p>
		</div>

		<div v-if="loading" class="empty-state">{{ $texts.settlements.loading() }}</div>
		<div v-else-if="error" class="empty-state error-state">{{ error }}</div>
		<div v-else-if="pagination.total === 0" class="empty-state">
			{{ $texts.settlements.empty() }}
		</div>
		<template v-else>
		<div class="settlement-list">
			<details
				v-for="settlement in settlements"
				:key="settlement.id"
				class="settlement-card"
				:data-settlement-id="settlement.id"
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

					<section class="settlement-subsection settlement-entries-section">
						<h3>{{ $texts.settlements.entries() }}</h3>
						<p v-if="settlementEntryState(settlement).loading" class="settlement-entry-state muted-text">
							{{ $texts.common.loading() }}
						</p>
						<p v-else-if="settlementEntryState(settlement).error" class="settlement-entry-state error-state">
							{{ settlementEntryState(settlement).error }}
						</p>
						<template v-else>
							<div
								v-if="settlementEntryState(settlement).entries.length > 0"
								class="settlement-entry-table"
								:aria-busy="settlementEntryState(settlement).loading ? 'true' : 'false'">
								<EntryTable
									mode="project"
									:entries="settlementEntryState(settlement).entries"
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
							</div>
							<p v-else class="muted-text">{{ $texts.settlements.noEntries() }}</p>

							<nav
								v-if="settlementEntryState(settlement).total > settlementEntryState(settlement).limit"
								class="settlement-entry-pagination"
								:aria-label="$texts.settlements.entries()">
								<NcButton
									variant="secondary"
									class="btn-page cobudget-toolbar-text-button"
									:style="{ visibility: settlementEntryState(settlement).offset > 0 ? 'visible' : 'hidden' }"
									@click="previousSettlementEntryPage(settlement)">
									<ArrowLeftIcon class="pagination-icon" :size="16" aria-hidden="true" />
									<span>{{ $texts.common.previous() }}</span>
								</NcButton>
								<span class="page-info">{{ settlementEntryPaginationInfo(settlement) }}</span>
								<NcButton
									variant="secondary"
									class="btn-page cobudget-toolbar-text-button"
									:style="{ visibility: settlementEntryHasNextPage(settlement) ? 'visible' : 'hidden' }"
									@click="nextSettlementEntryPage(settlement)">
									<span>{{ $texts.common.next() }}</span>
									<ArrowRightIcon class="pagination-icon" :size="16" aria-hidden="true" />
								</NcButton>
							</nav>
						</template>
					</section>
				</div>
			</details>
		</div>

		<nav
			v-if="pagination.total > pagination.limit"
			class="settlement-pagination"
			:aria-label="$texts.settlements.title()">
			<NcButton
				variant="secondary"
				class="btn-page cobudget-toolbar-text-button"
				:style="{ visibility: pagination.offset > 0 ? 'visible' : 'hidden' }"
				@click="previousPage">
				<ArrowLeftIcon class="pagination-icon" :size="16" aria-hidden="true" />
				<span>{{ $texts.common.previous() }}</span>
			</NcButton>
			<span class="page-info">{{ paginationInfo }}</span>
			<NcButton
				variant="secondary"
				class="btn-page cobudget-toolbar-text-button"
				:style="{ visibility: pagination.offset + pagination.limit < pagination.total ? 'visible' : 'hidden' }"
				@click="nextPage">
				<span>{{ $texts.common.next() }}</span>
				<ArrowRightIcon class="pagination-icon" :size="16" aria-hidden="true" />
			</NcButton>
		</nav>
		</template>

		<EntryHistoryModal
			v-if="entryHistoryOpen"
			:history="entryHistoryRows"
			:loading="entryHistoryLoading"
			@close="closeEntryHistory" />
	</div>
</template>

<script>
import axios from '../services/http'
import { REMOTE_DATA_CHANGED_EVENT, changeAffectsProject, changeTouches } from '../services/dataSync'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import EntryTable from '../components/EntryTable.vue'
import EntryHistoryModal from '../components/EntryHistoryModal.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import { showRequestError } from '../services/notifications'
import { normalizeEntryPageSize, shouldIgnorePaginationKeydown } from '../services/pagination'
import { getAreaColorStyle } from '../utils/areaColor'

const SETTLEMENT_PAGE_SIZE = 10
const MAX_SETTLEMENT_PAGE = 10001

export default {
	name: 'ProjectSettlementsView',
	components: {
		AppPageHeader,
		ArrowLeftIcon,
		ArrowRightIcon,
		EntryTable,
		EntryHistoryModal,
		NcButton,
	},
	props: ['id'],
	data() {
		return {
			project: null,
			settlements: [],
			pagination: {
				limit: SETTLEMENT_PAGE_SIZE,
				offset: 0,
				total: 0,
			},
			loading: true,
			error: '',
			settlementFetchRequestId: 0,
			settlementEntryStates: {},
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
		currentPage() {
			return Math.floor(this.pagination.offset / this.pagination.limit) + 1
		},
		totalPages() {
			return Math.max(1, Math.ceil(Number(this.pagination.total || 0) / this.pagination.limit))
		},
		paginationInfo() {
			return this.$texts.common.pageNumber(this.currentPage, this.totalPages)
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
			}
			for (const state of Object.values(this.settlementEntryStates)) {
				for (const entry of state.entries || []) {
					if (entry.user_id && entry.user_display_name) {
						map[entry.user_id] = entry.user_display_name
					}
				}
			}
			return map
		},
	},
	mounted() {
		this.handleSettlementRouteChange()
		window.addEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
		window.addEventListener('keydown', this.onPaginationKeydown)
	},
	beforeUnmount() {
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
		window.removeEventListener('keydown', this.onPaginationKeydown)
	},
	watch: {
		'$route.fullPath': 'handleSettlementRouteChange',
	},
	methods: {
		routePage() {
			const routeValue = Array.isArray(this.$route.query.page)
				? this.$route.query.page[0]
				: this.$route.query.page
			const parsedPage = Number.parseInt(routeValue, 10)
			return Number.isSafeInteger(parsedPage) && parsedPage > 0 ? Math.min(parsedPage, MAX_SETTLEMENT_PAGE) : 1
		},
		handleSettlementRouteChange() {
			this.pagination.offset = (this.routePage() - 1) * this.pagination.limit
			this.openSettlementId = null
			this.fetchSettlements()
		},
		settlementEntryState(settlement) {
			return this.settlementEntryStates[String(settlement.id)] || {
				entries: [],
				loaded: false,
				loading: false,
				error: '',
				limit: normalizeEntryPageSize(this.$entriesPerPage),
				offset: 0,
				total: Math.max(0, Number(settlement.entryCount) || 0),
				requestId: 0,
			}
		},
		ensureSettlementEntryState(settlement) {
			const key = String(settlement.id)
			if (!this.settlementEntryStates[key]) {
				this.settlementEntryStates[key] = this.settlementEntryState(settlement)
			}
			return this.settlementEntryStates[key]
		},
		settlementEntryCurrentPage(settlement) {
			const state = this.settlementEntryState(settlement)
			return Math.floor(state.offset / state.limit) + 1
		},
		settlementEntryTotalPages(settlement) {
			const state = this.settlementEntryState(settlement)
			return Math.max(1, Math.ceil(Number(state.total || 0) / state.limit))
		},
		settlementEntryPaginationInfo(settlement) {
			return this.$texts.common.pageNumber(
				this.settlementEntryCurrentPage(settlement),
				this.settlementEntryTotalPages(settlement),
			)
		},
		settlementEntryHasNextPage(settlement) {
			const state = this.settlementEntryState(settlement)
			return state.offset + state.limit < state.total
		},
		onPaginationKeydown(event) {
			if (shouldIgnorePaginationKeydown(event)) {
				return
			}

			const openSettlement = this.settlements.find(settlement => String(settlement.id) === String(this.openSettlementId))
			if (openSettlement) {
				const entryState = this.settlementEntryState(openSettlement)
				if (entryState.total > entryState.limit) {
					if (event.key === 'ArrowLeft' && entryState.offset > 0) {
						event.preventDefault()
						this.previousSettlementEntryPage(openSettlement)
					} else if (event.key === 'ArrowRight' && this.settlementEntryHasNextPage(openSettlement)) {
						event.preventDefault()
						this.nextSettlementEntryPage(openSettlement)
					}
					return
				}
			}

			if (this.pagination.total <= this.pagination.limit) {
				return
			}

			if (event.key === 'ArrowLeft' && this.pagination.offset > 0) {
				event.preventDefault()
				this.previousPage()
			} else if (event.key === 'ArrowRight' && this.pagination.offset + this.pagination.limit < this.pagination.total) {
				event.preventDefault()
				this.nextPage()
			}
		},
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
		async fetchSettlementEntries(settlement, offset = 0) {
			const key = String(settlement.id)
			const state = this.ensureSettlementEntryState(settlement)
			const requestId = state.requestId + 1
			const requestedProjectId = String(this.projectId)
			state.requestId = requestId
			state.offset = Math.max(0, Number(offset) || 0)
			state.loading = true
			state.error = ''

			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/settlements/${settlement.id}/entries`), {
					params: {
						limit: state.limit,
						offset: state.offset,
					},
				})
				if (this.settlementEntryStates[key] !== state
					|| state.requestId !== requestId
					|| requestedProjectId !== String(this.projectId)) {
					return
				}

				state.entries = Array.isArray(response.data?.entries) ? response.data.entries : []
				state.limit = Math.max(1, Number(response.data?.limit) || state.limit)
				state.offset = Math.max(0, Number(response.data?.offset) || 0)
				state.total = Math.max(0, Number(response.data?.total) || 0)
				state.loaded = true

				const currentPage = this.settlementEntryCurrentPage(settlement)
				const totalPages = this.settlementEntryTotalPages(settlement)
				if (currentPage > totalPages) {
					this.fetchSettlementEntries(settlement, (totalPages - 1) * state.limit)
				}
			} catch (error) {
				if (this.settlementEntryStates[key] !== state || state.requestId !== requestId) {
					return
				}
				state.error = this.$texts.areaDetail.entriesLoadError()
				showRequestError(error, state.error, 'Failed to fetch settlement entries')
			} finally {
				if (this.settlementEntryStates[key] === state && state.requestId === requestId) {
					state.loading = false
				}
			}
		},
		async fetchSettlements() {
			const requestId = ++this.settlementFetchRequestId
			const requestedProjectId = String(this.projectId)
			this.loading = true
			this.error = ''
			this.openSettlementId = null
			this.settlementEntryStates = {}
			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}/settlements`), {
					params: {
						limit: this.pagination.limit,
						offset: this.pagination.offset,
					},
				})
				if (requestId !== this.settlementFetchRequestId || requestedProjectId !== String(this.projectId)) {
					return
				}
				this.project = response.data?.project || null
				this.settlements = Array.isArray(response.data?.settlements) ? response.data.settlements : []
				this.pagination.limit = Number(response.data?.limit) || this.pagination.limit
				this.pagination.offset = Math.max(0, Number(response.data?.offset) || 0)
				this.pagination.total = Math.max(0, Number(response.data?.total) || 0)
				if (this.currentPage > this.totalPages) {
					this.replaceSettlementPage(this.totalPages)
				}
			} catch (error) {
				if (requestId !== this.settlementFetchRequestId) {
					return
				}
				this.error = this.$texts.settlements.loadError()
				showRequestError(error, this.error, 'Failed to fetch project settlements')
			} finally {
				if (requestId === this.settlementFetchRequestId) {
					this.loading = false
				}
			}
		},
		onRemoteDataChanged(event) {
			const change = event?.detail
			if (changeAffectsProject(change, this.projectId) && changeTouches(change, ['projects', 'settlements'])) {
				this.fetchSettlements()
			}
		},
		goBackToProject() {
			this.$router.push({ name: 'project-detail', params: { id: this.projectId } })
		},
		previousPage() {
			if (this.pagination.offset > 0) {
				this.goToSettlementPage(this.currentPage - 1)
			}
		},
		nextPage() {
			if (this.pagination.offset + this.pagination.limit < this.pagination.total) {
				this.goToSettlementPage(this.currentPage + 1)
			}
		},
		previousSettlementEntryPage(settlement) {
			const state = this.ensureSettlementEntryState(settlement)
			if (state.offset > 0 && !state.loading) {
				this.goToSettlementEntryPage(settlement, this.settlementEntryCurrentPage(settlement) - 1)
			}
		},
		nextSettlementEntryPage(settlement) {
			const state = this.ensureSettlementEntryState(settlement)
			if (this.settlementEntryHasNextPage(settlement) && !state.loading) {
				this.goToSettlementEntryPage(settlement, this.settlementEntryCurrentPage(settlement) + 1)
			}
		},
		goToSettlementEntryPage(settlement, page) {
			const state = this.ensureSettlementEntryState(settlement)
			const targetPage = Math.min(
				this.settlementEntryTotalPages(settlement),
				Math.max(1, Number(page) || 1),
			)
			this.scrollSettlementEntrySectionToTop(settlement)
			this.fetchSettlementEntries(settlement, (targetPage - 1) * state.limit)
		},
		scrollSettlementEntrySectionToTop(settlement) {
			this.$nextTick(() => {
				const settlementId = Number(settlement.id)
				const section = this.$el?.querySelector?.(`[data-settlement-id="${settlementId}"] .settlement-entries-section`)
				section?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
			})
		},
		goToSettlementPage(page) {
			const targetPage = Math.min(this.totalPages, Math.max(1, Number(page) || 1))
			const query = { ...this.$route.query }
			if (targetPage > 1) {
				query.page = String(targetPage)
			} else {
				delete query.page
			}
			this.scrollToPageTop()
			this.$router.push({
				name: 'project-settlements',
				params: { id: this.projectId },
				query,
			}).catch(() => {})
		},
		replaceSettlementPage(page) {
			const targetPage = Math.max(1, Number(page) || 1)
			const query = { ...this.$route.query }
			if (targetPage > 1) {
				query.page = String(targetPage)
			} else {
				delete query.page
			}
			this.$router.replace({
				name: 'project-settlements',
				params: { id: this.projectId },
				query,
			}).catch(() => {})
		},
		scrollToPageTop() {
			this.$nextTick(() => {
				const scroller = this.$el?.closest?.('.app-content')
				if (scroller && typeof scroller.scrollTo === 'function') {
					scroller.scrollTo({ top: 0, behavior: 'smooth' })
				}
			})
		},
		handleSettlementToggle(id, event) {
			if (event.target.open) {
				this.openSettlementId = id
				const settlement = this.settlements.find(item => String(item.id) === String(id))
				if (settlement) {
					const state = this.ensureSettlementEntryState(settlement)
					if (state.total === 0) {
						state.loaded = true
					} else if ((!state.loaded || state.error) && !state.loading) {
						this.fetchSettlementEntries(settlement)
					}
				}
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

.settlement-pagination {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	width: calc(100% - var(--default-grid-baseline, 4px) * 14);
	margin: calc(var(--default-grid-baseline, 4px) * 4) calc(var(--default-grid-baseline, 4px) * 7) 0;
	box-sizing: border-box;
}

.settlement-pagination .btn-page:first-child {
	justify-self: start;
}

.settlement-pagination .btn-page:last-child {
	justify-self: end;
}

.btn-page {
	min-width: 0 !important;
	border-color: transparent !important;
	background: var(--cobudget-surface-muted, var(--color-background-hover)) !important;
	box-shadow: none !important;
	color: var(--cobudget-text, var(--color-main-text)) !important;
	font-size: var(--cobudget-font-sm);
}

.btn-page :deep(.button-vue__wrapper),
.btn-page :deep(.button-vue__text) {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	line-height: 1 !important;
}

.pagination-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
	width: calc(var(--default-grid-baseline, 4px) * 4);
	height: calc(var(--default-grid-baseline, 4px) * 4);
	line-height: 0;
}

.pagination-icon :deep(.material-design-icon),
.pagination-icon :deep(.material-design-icon__svg) {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	line-height: 1;
	bottom: auto;
}

.page-info {
	color: var(--color-text-maxcontrast);
	font-size: var(--cobudget-font-compact);
	text-align: center;
	white-space: nowrap;
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

.settlement-entries-section {
	min-width: 0;
	scroll-margin-block-start: calc(var(--default-grid-baseline, 4px) * 18);
}

.settlement-entry-table {
	min-width: 0;
	overflow: hidden;
	border-radius: var(--border-radius-large, 8px);
}

.settlement-entry-table :deep(.entry-table-container),
.settlement-entry-table :deep(.data-table) {
	border-radius: inherit;
}

.settlement-entry-state {
	margin: 0;
	padding: calc(var(--default-grid-baseline, 4px) * 4);
	border: 1px solid var(--cobudget-border, var(--color-border));
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-page-background, var(--color-main-background));
	text-align: center;
}

.settlement-entry-pagination {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	margin-block-start: calc(var(--default-grid-baseline, 4px) * 2);
	padding: calc(var(--default-grid-baseline, 4px) * 2);
	border: 1px solid var(--cobudget-border, var(--color-border));
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-page-background, var(--color-main-background));
}

.settlement-entry-pagination .btn-page:first-child {
	justify-self: start;
}

.settlement-entry-pagination .btn-page:last-child {
	justify-self: end;
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
		--settlement-mobile-content-gutter: var(--cobudget-mobile-content-padding, calc(var(--default-grid-baseline, 4px) * 2.5));

		width: 100%;
		margin: 0;
		padding: 0 0 var(--settlement-mobile-content-gutter);
		box-sizing: border-box;
	}

	.settings-header,
	.settlement-list,
	.settlement-pagination,
	.empty-state {
		width: calc(100% - var(--settlement-mobile-content-gutter) * 2);
		margin-inline: var(--settlement-mobile-content-gutter);
	}

	.settlement-pagination {
		padding: calc(var(--default-grid-baseline, 4px) * 2);
		border: 1px solid var(--cobudget-border, var(--color-border));
		border-radius: var(--border-radius-large, 8px);
		background: var(--cobudget-surface-muted, var(--color-background-hover));
	}

	.settlement-pagination .btn-page,
	.settlement-pagination .btn-page.button-vue {
		width: auto !important;
		padding-inline: calc(var(--default-grid-baseline, 4px) * 2) !important;
		background: transparent !important;
	}

	.settlement-entry-table {
		border: 1px solid var(--cobudget-border, var(--color-border));
		background: var(--cobudget-page-background, var(--color-main-background));
	}

	.settlement-entry-table :deep(.entry-table-container),
	.settlement-entry-table :deep(.data-table) {
		border-radius: inherit;
	}

	.settlement-entry-table :deep(.data-table tr.clickable-row) {
		box-sizing: border-box;
		padding-inline: calc(var(--default-grid-baseline, 4px) * 3);
	}

	.settlement-entry-table :deep(.data-table tbody tr.clickable-row:last-child) {
		border-block-end: none;
	}

	.settlement-entry-pagination .btn-page,
	.settlement-entry-pagination .btn-page.button-vue {
		width: auto !important;
		padding-inline: calc(var(--default-grid-baseline, 4px) * 2) !important;
		background: transparent !important;
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
