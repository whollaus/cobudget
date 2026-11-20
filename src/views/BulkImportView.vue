<template>
	<div class="bulk-import-page settings-section">
		<AppPageHeader class="cobudget-filter-toolbar">
			<template #title>
				<span class="cobudget-page-title-with-back">
					<NcButton
						variant="tertiary"
						class="cobudget-native-button cobudget-header-back-button"
						:aria-label="backLabel"
						:title="backLabel"
						:disabled="importing"
						@click="goBack">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
					</NcButton>
					<ReportPeriodPicker
						navigation-only
						force-navigation
						:period-enabled="false"
						:title="$texts.bulkImport.title()"
						label=""
						:disabled="importing" />
				</span>
			</template>
		</AppPageHeader>

		<div class="bulk-import-content">
			<div v-if="loadingContext" class="bulk-import-loading cobudget-form-hint" role="status">
				<NcLoadingIcon :size="24" />
				<span>{{ $texts.common.loading() }}</span>
			</div>

			<NcNoteCard v-else-if="contextError" type="error" class="bulk-import-note">
				<p>{{ contextError }}</p>
				<NcButton class="cobudget-native-button" variant="secondary" @click="loadContext">
					{{ $texts.common.retry() }}
				</NcButton>
			</NcNoteCard>

			<template v-else>
				<section class="settings-block bulk-import-target-section">
					<h3 class="bulk-import-field-heading">{{ $texts.bulkImport.targetTitle() }}</h3>

					<div class="bulk-import-area-options" :aria-label="$texts.bulkImport.targetTitle()" role="group">
						<NcFormBoxSwitch
							v-for="option in areaSelectionOptions"
							:key="option.key"
							class="bulk-import-area-switch"
							:aria-label="option.name"
							:disabled="busy"
							:model-value="hasSelectedTarget && option.id === projectId"
							@update:model-value="onSelectImportArea(option)">
							<span class="bulk-import-area-switch-content">
								<AreaIcon :without-area="option.id === null" :color="option.color" />
								<span class="bulk-import-area-switch-name">{{ option.name }}</span>
							</span>
						</NcFormBoxSwitch>
					</div>

					<NcNoteCard v-if="areaImportBlocker" type="warning" class="bulk-import-note" :text="areaImportBlocker" />
				</section>

				<section v-if="!previewRows.length" v-show="!validating" class="settings-block bulk-import-upload-section">
					<h3 ref="inputHeading" tabindex="-1" class="bulk-import-field-heading">{{ $texts.bulkImport.inputSectionTitle() }}</h3>
					<div class="bulk-import-file-controls">
						<input
							ref="fileInput"
							type="file"
							accept=".json,application/json"
							hidden
							:disabled="fileSelectDisabled"
							@change="onFileSelected">
						<NcButton
							variant="secondary"
							class="cobudget-native-button"
							:disabled="fileSelectDisabled"
							@click="triggerFileSelect">
							<template #icon><UploadIcon :size="20" /></template>
							{{ $texts.bulkImport.chooseFile() }}
						</NcButton>
						<span v-if="selectedFile" class="bulk-import-selected-file" role="status">
							{{ selectedFile.name }}
						</span>
						<span v-else class="cobudget-form-hint">{{ $texts.bulkImport.fileHint() }}</span>
						<NcButton
							v-if="selectedFile"
							variant="tertiary"
							class="cobudget-native-button"
							:disabled="busy"
							@click="clearSelectedFile">
							<template #icon><CloseIcon :size="20" /></template>
							{{ $texts.bulkImport.clearFile() }}
						</NcButton>
					</div>

					<div class="cobudget-form-group">
						<label class="hidden-visually" for="bulk-import-json-textarea">{{ $texts.bulkImport.inputSectionTitle() }}</label>
						<NcTextArea
							id="bulk-import-json-textarea"
							v-model="jsonTextInput"
							class="cobudget-native-field bulk-import-json-textarea"
							label-outside
							:placeholder="selectedFile ? undefined : singlePaymentPlaceholder"
							:rows="5"
							resize="vertical"
							spellcheck="false"
							:disabled="inputDisabled || !!selectedFile"
							@update:model-value="onJsonTextChanged" />
					</div>

					<div class="bulk-import-form-footer">
						<NcButton
							variant="tertiary"
							class="cobudget-native-button bulk-import-format-trigger"
							aria-haspopup="dialog"
							:disabled="busy"
							@click="openFormatModal">
							<template #icon><HelpCircleOutlineIcon :size="20" /></template>
							{{ $texts.bulkImport.formatTitle() }}
						</NcButton>
						<div class="bulk-import-input-actions">
							<NcButton
								variant="tertiary"
								class="cobudget-native-button"
								:disabled="importing"
								@click="goBack">
								{{ $texts.common.cancel() }}
							</NcButton>
							<NcButton
								variant="primary"
								class="cobudget-native-button"
								:disabled="!canCheckPreview"
								@click="checkAndPreview">
								<template v-if="validating" #icon><NcLoadingIcon :size="20" /></template>
								{{ $texts.bulkImport.checkAndPreview() }}
							</NcButton>
						</div>
					</div>
				</section>

				<div v-if="validating || importing" class="bulk-import-loading cobudget-form-hint" role="status" aria-live="polite">
					<NcLoadingIcon :size="24" />
					<span v-if="importing">{{ $texts.bulkImport.importingProgress(importProgressDone, importProgressTotal) }}</span>
					<span v-else-if="importProgressTotal">{{ $texts.bulkImport.previewProgress(importProgressDone, importProgressTotal) }}</span>
					<span v-else>{{ $texts.bulkImport.validating() }}</span>
					<NcButton v-if="packetAbort" variant="tertiary" @click="packetAbort.abort()">{{ $texts.common.cancel() }}</NcButton>
				</div>

				<section v-else-if="validationErrors.length > 0" class="settings-block" aria-live="polite">
					<NcNoteCard type="error" class="bulk-import-note">
						<p><strong>{{ $texts.bulkImport.validationFailedTitle() }}</strong></p>
						<ul class="bulk-import-error-list">
							<li v-for="(err, idx) in validationErrors" :key="idx">{{ err }}</li>
						</ul>
					</NcNoteCard>
				</section>

				<NcNoteCard v-if="skippedCount && !validating && !importing" type="info" :text="$texts.bulkImport.skipped(skippedCount)" />

				<section v-if="!validating && !importing && previewRows.length > 0 && previewSummary" class="settings-block bulk-import-preview-section" aria-live="polite">
					<h3 ref="previewHeading" tabindex="-1" class="bulk-import-field-heading">{{ $texts.bulkImport.previewTitle() }}</h3>

					<div class="bulk-import-preview-summary" data-cobudget-card="compact">
						<div class="bulk-import-summary-stats">
							<p class="bulk-import-summary-headline">
								{{ $texts.bulkImport.previewSummary(previewSummary.totalCount, previewSummary.expenseCount, previewSummary.incomeCount) }}
							</p>
							<div class="bulk-import-summary-totals">
								<span v-if="previewSummary.expenseCount > 0" class="bulk-import-summary-metric negative">
									{{ $texts.dashboard.expenses() }}: {{ formatSignedAmount(-Math.abs(previewSummary.expenseTotal), previewSummary.currency) }}
								</span>
								<span v-if="previewSummary.incomeCount > 0 && $enableIncomes" class="bulk-import-summary-metric positive">
									{{ $texts.dashboard.income() }}: {{ formatSignedAmount(Math.abs(previewSummary.incomeTotal), previewSummary.currency) }}
								</span>
							</div>
						</div>

						<div v-if="newCategories.length > 0 || newPaymentPartners.length > 0" class="bulk-import-new-masterdata">
							<p class="bulk-import-new-masterdata-heading">
								<strong>{{ $texts.bulkImport.newMasterDataHeading() }}</strong>
							</p>
							<div v-if="newCategories.length > 0" class="bulk-import-new-group">
								<span class="bulk-import-new-group-label">{{ $texts.bulkImport.newCategoriesLabel() }}:</span>
								<div class="bulk-import-chip-list">
									<span
										v-for="item in newCategories"
										:key="`new-cat-${item.type}-${item.name}`"
										class="bulk-import-new-item-badge"
										data-cobudget-badge="info">
										<span>{{ item.name }}</span>
										<span class="bulk-import-new-item-type">({{ item.type === 'income' ? $texts.common.income() : $texts.common.expense() }})</span>
									</span>
								</div>
							</div>
							<div v-if="newPaymentPartners.length > 0" class="bulk-import-new-group">
								<span class="bulk-import-new-group-label">{{ $texts.bulkImport.newPaymentPartnersLabel() }}:</span>
								<div class="bulk-import-chip-list">
									<span
										v-for="item in newPaymentPartners"
										:key="`new-partner-${item.type}-${item.name}`"
										class="bulk-import-new-item-badge"
										data-cobudget-badge="info">
										<span>{{ item.name }}</span>
										<span class="bulk-import-new-item-type">({{ item.type === 'income' ? $texts.common.income() : $texts.common.expense() }})</span>
									</span>
								</div>
							</div>
						</div>
					</div>


					<div
						tabindex="0"
						role="region"
						:aria-label="$texts.bulkImport.previewTitle()"
						class="bulk-import-table-wrapper">
						<table class="bulk-import-table bulk-import-preview-table">
							<thead>
								<tr>
									<th scope="col" class="col-row">{{ $texts.bulkImport.columnRow() }}</th>
									<th scope="col" class="col-date">{{ $texts.bulkImport.columnDate() }}</th>
									<th scope="col" class="col-partner">{{ $texts.bulkImport.columnPartner() }}</th>
									<th scope="col" class="col-category">{{ $texts.bulkImport.columnCategory() }}</th>
									<th scope="col" class="col-description">{{ $texts.bulkImport.columnDescription() }}</th>
									<th scope="col" class="col-labels">{{ $texts.bulkImport.columnLabels() }}</th>
									<th scope="col" class="col-amount">{{ $texts.bulkImport.columnAmount() }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="row in pagedPreviewRows" :key="row.rowNumber">
									<td :data-label="$texts.bulkImport.columnRow()" class="col-row">{{ row.rowNumber }}</td>
									<td :data-label="$texts.bulkImport.columnDate()" class="col-date">{{ formatPreviewDate(row.date) }}</td>
									<td
										:data-label="$texts.bulkImport.columnPartner()"
										class="col-partner"
										:class="{ 'bulk-import-cell--new': row.isNewPaymentPartner }">
										<div v-if="row.paymentPartnerName" class="bulk-import-cell-content">
											<span class="bulk-import-cell-text">{{ row.paymentPartnerName }}</span>
											<span
												v-if="row.isNewPaymentPartner"
												data-cobudget-badge="info"
												data-cobudget-badge-size="compact"
												:title="$texts.bulkImport.newBadgeHint()">
												{{ $texts.bulkImport.newBadge() }}
											</span>
										</div>
										<span v-else class="bulk-import-empty-cell">-</span>
									</td>
									<td
										:data-label="$texts.bulkImport.columnCategory()"
										class="col-category"
										:class="{ 'bulk-import-cell--new': row.isNewCategory }">
										<div v-if="row.categoryName" class="bulk-import-cell-content">
											<span class="bulk-import-cell-text">{{ row.categoryName }}</span>
											<span
												v-if="row.isNewCategory"
												data-cobudget-badge="info"
												data-cobudget-badge-size="compact"
												:title="$texts.bulkImport.newBadgeHint()">
												{{ $texts.bulkImport.newBadge() }}
											</span>
										</div>
										<span v-else class="bulk-import-empty-cell">-</span>
									</td>
									<td :data-label="$texts.bulkImport.columnDescription()" class="col-description">
										<span v-if="row.description">{{ row.description }}</span>
										<span v-else class="bulk-import-empty-cell">-</span>
									</td>
									<td :data-label="$texts.bulkImport.columnLabels()" class="col-labels">
										<PaymentLabelList v-if="rowLabels(row).length" :labels="rowLabels(row)" />
										<span v-else class="bulk-import-empty-cell">-</span>
									</td>
									<td
										:data-label="$texts.bulkImport.columnAmount()"
										class="col-amount"
										:class="row.type === 'income' ? 'positive' : 'negative'">
										{{ formatSignedAmount(row.signedAmount, previewSummary.currency) }}
									</td>
								</tr>
							</tbody>
						</table>
					</div>

					<nav
						v-if="previewRows.length > previewPageSize"
						class="bulk-import-pagination"
						:aria-label="$texts.bulkImport.previewTitle()">
						<NcButton
							:aria-label="$texts.common.previous()"
							variant="secondary"
							class="btn-page cobudget-native-button"
							:style="{ visibility: previewPage > 1 ? 'visible' : 'hidden' }"
							@click="previousPreviewPage">
							<template #icon><ArrowLeftIcon :size="20" /></template>
							<span>{{ $texts.common.previous() }}</span>
						</NcButton>
						<span class="page-info">{{ previewPageInfo }}</span>
						<NcButton
							:aria-label="$texts.common.next()"
							alignment="center-reverse"
							variant="secondary"
							class="btn-page cobudget-native-button"
							:style="{ visibility: previewPage < previewTotalPages ? 'visible' : 'hidden' }"
							@click="nextPreviewPage">
							<span>{{ $texts.common.next() }}</span>
							<template #icon><ArrowRightIcon :size="20" /></template>
						</NcButton>
					</nav>

					<div class="bulk-import-preview-actions">
						<NcButton
							variant="tertiary"
							class="cobudget-native-button"
							:disabled="busy"
							@click="cancelPreview">
							{{ $texts.common.cancel() }}
						</NcButton>
						<NcButton
							variant="primary"
							class="cobudget-native-button"
							:disabled="busy"
							@click="confirmImport">
							{{ $texts.bulkImport.confirmImport(previewSummary.totalCount) }}
						</NcButton>
					</div>
				</section>
			</template>
		</div>

		<ImportFormatDialog :show="formatModalOpen" :project="project" :categories="categories" :payment-partners="paymentPartners"
			:has-selected-target="hasSelectedTarget" @close="closeFormatModal" />
	</div>
</template>

<script>
import { sha256 } from '@noble/hashes/sha256'
import { bytesToHex } from '@noble/hashes/utils'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import HelpCircleOutlineIcon from 'vue-material-design-icons/HelpCircleOutline.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import { useLocalDate } from '../utils/useLocalDate'
import ImportFormatDialog from '../components/ImportFormatDialog.vue'
import AreaIcon from '../components/AreaIcon.vue'
import PaymentLabelList from '../components/PaymentLabelList.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import axios from '../services/http'
import { extractError, showToast } from '../services/notifications'
import { sortAreasByName } from '../utils/areaSorting'
import { formatSignedMoney, getAppLocale } from '../utils/formatMoney'

// Preserve sub-cent literals until the server validates the row. Keep valid numbers numeric for replay detection.
function parsePaymentImport(json) {
	if (/\.\d{3}|[eE]-\d/.test(json)) {
		const tokens = /("(?:[^"\\]|\\.)*")(?:([ \t\r\n]*:[ \t\r\n]*)(-?(0|[1-9]\d*)(?:\.(\d+))?(?:[eE]([+-]?\d+))?))?/g
		json = json.replace(tokens, (token, key, colon, number, whole, fraction = '', exponent = '0') => {
			if (number === undefined || (key !== '"amount"' && JSON.parse(key) !== 'amount')) return token
			const digits = whole + fraction
			let end = digits.length
			while (end > 0 && digits[end - 1] === '0') end--
			const scale = fraction.length - (digits.length - end) - Number(exponent)
			return scale > 2 ? key + colon + JSON.stringify(number) : token
		})
	}
	return JSON.parse(json)
}

const MAX_IMPORT_FILE_BYTES = 5 * 1024 * 1024
const MAX_IMPORT_ENTRIES = 5000
const PREVIEW_PAGE_SIZE = 50
const IMPORT_BATCH_SIZE = 50

export default {
	setup() { return { today: useLocalDate() } },
	name: 'BulkImportView',
	components: {
		ImportFormatDialog,
		AppPageHeader,
		AreaIcon,
		ArrowLeftIcon,
		ArrowRightIcon,
		CloseIcon,
		HelpCircleOutlineIcon,
		NcButton,
		NcFormBoxSwitch,
		NcLoadingIcon,
		NcNoteCard,
		NcTextArea,
		PaymentLabelList,
		ReportPeriodPicker,
		UploadIcon,
	},
	data() {
		return {
			loadingContext: true,
			loadingLookups: false,
			formatModalOpen: false,
			contextError: '',
			projects: [],
			originProjectId: null,
			selectedProjectId: null,
			hasSelectedTarget: true,
			project: null,
			categories: [],
			paymentPartners: [],
			selectedFile: null,
			jsonTextInput: '',
			parsedPayments: null,
			importId: '',
			importScope: null,
			pendingPackets: [],
			skippedCount: 0,
			packetAbort: null,
			validating: false,
			importing: false,
			importProgressDone: 0,
			importProgressTotal: 0,
			validationErrors: [],
			previewSummary: null,
			previewRows: [],
			previewPage: 1,
			previewPageSize: PREVIEW_PAGE_SIZE,
			newCategories: [],
			newPaymentPartners: [],
		}
	},
	created() {
		const initialId = this.queryProjectId
		this.originProjectId = initialId
		this.selectedProjectId = initialId
	},
	computed: {
		queryProjectId() {
			if (this.$enableProjects === false) return null
			const raw = this.$route?.query?.projectId ?? null
			const num = Number(raw || 0)
			return Number.isInteger(num) && num > 0 ? num : null
		},
		projectId() {
			return this.selectedProjectId
		},
		isAreaImport() {
			return this.projectId !== null
		},
		selectableProjects() {
			const list = Array.isArray(this.projects) ? [...this.projects] : []
			const active = sortAreasByName(list.filter(proj => !this.isTruthy(proj?.is_archived) && proj?.status !== 'archived'))
			if (this.isAreaImport && this.project && !active.some(proj => Number(proj.id) === this.projectId)) {
				active.push(this.project)
			}
			return active
		},
		areaSelectionOptions() {
			const options = [
				{
					key: 'personal',
					id: null,
					name: this.$texts.entry.personalAssignment(),
					color: 'var(--color-primary-element, #00679e)',
				},
			]
			if (this.$enableProjects !== false) {
				for (const proj of this.selectableProjects) {
					const numId = Number(proj?.id || 0)
					if (!Number.isInteger(numId) || numId <= 0) continue
					options.push({
						key: `area-${numId}`,
						id: numId,
						name: String(proj.name || ''),
						color: proj.color || 'var(--color-primary-element, #00679e)',
					})
				}
			}
			return options
		},
		backLabel() {
			if (this.originProjectId !== null) {
				return this.$texts.areaSettings.backToArea()
			}
			return this.$texts.bulkImport.backToPayments()
		},
		isAreaArchived() {
			return this.isTruthy(this.project?.is_archived) || this.project?.status === 'archived'
		},
		hasFormerAreaMember() {
			return Array.isArray(this.project?.members)
				&& this.project.members.some(member => this.isTruthy(member?.is_former ?? member?.isFormer))
		},
		areaImportBlocker() {
			if (!this.isAreaImport || !this.project) {
				return ''
			}
			if (this.isAreaArchived) {
				return this.$texts.bulkImport.areaArchivedBlocked()
			}
			if (this.hasFormerAreaMember) {
				return this.$texts.bulkImport.areaFormerBlocked()
			}
			return ''
		},
		busy() {
			return this.loadingContext || this.loadingLookups || this.validating || this.importing
		},
		inputDisabled() {
			return this.busy || !!this.contextError || !!this.areaImportBlocker
		},
		fileSelectDisabled() {
			return this.inputDisabled || this.jsonTextInput.trim().length > 0
		},
		canCheckPreview() {
			return !this.inputDisabled && (!!this.selectedFile || this.jsonTextInput.trim().length > 0)
		},
		singlePaymentPlaceholder() {
			return this.$texts.bulkImport.pasteData() + '\n{"payments": [\n  {"date": "' + this.today + '", "amount": -12.50}\n]}'
		},
		previewTotalPages() {
			return Math.max(1, Math.ceil(this.previewRows.length / PREVIEW_PAGE_SIZE))
		},
		pagedPreviewRows() {
			const page = Math.min(Math.max(1, this.previewPage), this.previewTotalPages)
			const start = (page - 1) * PREVIEW_PAGE_SIZE
			return this.previewRows.slice(start, start + PREVIEW_PAGE_SIZE)
		},
		previewPageInfo() {
			const total = this.previewRows.length
			if (total === 0) return ''
			const page = Math.min(Math.max(1, this.previewPage), this.previewTotalPages)
			const start = (page - 1) * PREVIEW_PAGE_SIZE + 1
			const end = Math.min(page * PREVIEW_PAGE_SIZE, total)
			return this.$texts.common.pageInfo(start, end, total)
		},
	},
	watch: {
		'$route.query.projectId'() {
			this.formatModalOpen = false
			const nextId = this.queryProjectId
			this.originProjectId = nextId
			this.hasSelectedTarget = true
			if (nextId !== this.selectedProjectId) {
				this.selectedProjectId = nextId
				this.clearSelectedFile()
				this.loadContext()
			}
		},
	},
	mounted() {
		this.loadContext()
	},
	beforeUnmount() {
		this.packetAbort?.abort()
	},
	methods: {
		isTruthy(val) {
			return val === true || val === 1 || val === '1' || val === 'true'
		},
		formatSignedAmount(amount, currency) {
			return formatSignedMoney(amount, currency || this.$currency)
		},
		formatPreviewDate(dateStr) {
			const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(dateStr || ''))
			if (!match) return String(dateStr || '-')
			const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
			return date.toLocaleDateString(getAppLocale())
		},
		rowLabels(row) {
			const labels = []
			if (row.is_important && this.$enableImportantPayments !== false) labels.push({ key: 'important', icon: 'important', tone: 'warning', text: this.$texts.labels.important() })
			if (row.needs_review && this.$enableReviewPayments !== false) labels.push({ key: 'review', icon: 'review', tone: 'error', text: this.$texts.labels.review() })
			if (row.is_tax_relevant && this.$enableTaxRelevant !== false) labels.push({ key: 'tax', icon: 'taxRelevant', tone: 'tax', text: this.$texts.labels.taxRelevant() })
			if (row.is_fixed_cost && this.$enableFixedCosts !== false) labels.push({ key: 'fixed', icon: 'fixedCost', tone: 'neutral', text: this.$texts.labels.fixedCosts() })
			if (row.is_child_related && this.$enableChildRelated !== false) labels.push({ key: 'child', icon: 'childRelated', tone: 'neutral', text: this.$texts.labels.children() })
			if (row.is_subscription && this.$enableSubscriptions !== false) labels.push({ key: 'abo', icon: 'subscription', tone: 'neutral', text: this.$texts.labels.subscription() })
			if (row.is_insurance && this.$enableInsurance !== false) labels.push({ key: 'insurance', icon: 'insurance', tone: 'neutral', text: this.$texts.labels.insurance() })
			return labels
		},
		goBack() {
			if (this.importing) return
			if (this.originProjectId !== null) {
				this.$router.push({ name: 'project-detail', params: { id: String(this.originProjectId) } })
				return
			}
			const returnName = this.$route?.query?.returnTo === 'payments' ? 'payments' : 'personal'
			this.$router.push({ name: returnName })
		},
		async onSelectImportArea(option) {
			if (this.busy) return
			const rawId = Number(option?.id || 0)
			const nextProjectId = Number.isInteger(rawId) && rawId > 0 ? rawId : null
			if (this.hasSelectedTarget && nextProjectId === this.selectedProjectId) {
				this.hasSelectedTarget = false
				this.selectedProjectId = null
				this.project = null
				this.resetPreviewState()
				return
			}
			this.hasSelectedTarget = true
			this.selectedProjectId = nextProjectId
			this.project = nextProjectId !== null
				? (this.projects.find(proj => Number(proj.id) === nextProjectId) || null)
				: null
			await this.refreshTargetLookups()
		},
		async loadContext() {
			this.loadingContext = true
			this.contextError = ''
			this.project = null
			try {
				const params = this.isAreaImport ? { projectId: this.projectId } : {}
				const [projectsRes, projectRes, catRes, partnerRes] = await Promise.all([
					this.$enableProjects !== false
						? axios.get(generateUrl('/apps/cobudget/api/projects'))
						: Promise.resolve({ data: [] }),
					this.isAreaImport
						? axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}`))
						: Promise.resolve({ data: null }),
					axios.get(generateUrl('/apps/cobudget/api/categories'), { params }),
					axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params }),
				])
				this.projects = Array.isArray(projectsRes.data) ? projectsRes.data : []
				this.project = projectRes.data || null
				this.categories = Array.isArray(catRes.data) ? catRes.data : []
				this.paymentPartners = Array.isArray(partnerRes.data) ? partnerRes.data : []
			} catch (error) {
				this.contextError = extractError(
					error,
					this.isAreaImport ? this.$texts.bulkImport.areaLoadError() : this.$texts.bulkImport.lookupsLoadError()
				)
			} finally {
				this.loadingContext = false
			}
		},
		async refreshTargetLookups() {
			this.loadingLookups = true
			this.contextError = ''
			this.resetPreviewState()
			try {
				const params = this.isAreaImport ? { projectId: this.projectId } : {}
				const [projectRes, catRes, partnerRes] = await Promise.all([
					this.isAreaImport
						? axios.get(generateUrl(`/apps/cobudget/api/projects/${this.projectId}`))
						: Promise.resolve({ data: null }),
					axios.get(generateUrl('/apps/cobudget/api/categories'), { params }),
					axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params }),
				])
				this.project = projectRes.data || null
				this.categories = Array.isArray(catRes.data) ? catRes.data : []
				this.paymentPartners = Array.isArray(partnerRes.data) ? partnerRes.data : []
			} catch (error) {
				this.contextError = extractError(
					error,
					this.isAreaImport ? this.$texts.bulkImport.areaLoadError() : this.$texts.bulkImport.lookupsLoadError()
				)
			} finally {
				this.loadingLookups = false
			}
		},
		resetPreviewState() {
			this.packetAbort?.abort()
			this.importScope = null
			this.pendingPackets = []
			this.skippedCount = 0
			this.parsedPayments = null
			this.validationErrors = []
			this.previewSummary = null
			this.previewRows = []
			this.previewPage = 1
			this.importProgressDone = 0
			this.importProgressTotal = 0
			this.newCategories = []
			this.newPaymentPartners = []
		},
		previousPreviewPage() {
			if (this.previewPage > 1) {
				this.previewPage--
			}
		},
		nextPreviewPage() {
			if (this.previewPage < this.previewTotalPages) {
				this.previewPage++
			}
		},
		async cancelPreview() {
			this.resetPreviewState()
			await this.$nextTick()
			this.$refs.inputHeading?.focus()
		},
		onJsonTextChanged() {
			if (this.previewRows.length > 0 || this.validationErrors.length > 0 || this.parsedPayments) {
				this.resetPreviewState()
			}
		},
		triggerFileSelect() {
			if (this.fileSelectDisabled) return
			this.$refs.fileInput?.click()
		},
		clearSelectedFile() {
			this.selectedFile = null
			this.resetPreviewState()
		},
		onFileSelected(event) {
			const file = event.target.files[0]
			if (!file || this.fileSelectDisabled) return
			event.target.value = ''
			this.jsonTextInput = ''
			this.selectedFile = file
			this.resetPreviewState()
		},
		async readImportText() {
			let text = this.jsonTextInput
			if ((this.selectedFile || new Blob([text])).size > MAX_IMPORT_FILE_BYTES) {
				throw new Error(this.$texts.bulkImport.fileTooLarge())
			}
			if (this.selectedFile) {
				let buffer
				try {
					buffer = await this.selectedFile.arrayBuffer()
				} catch {
					throw new Error(this.$texts.bulkImport.fileReadError())
				}
				try {
					text = new TextDecoder('utf-8', { fatal: true }).decode(buffer)
				} catch {
					throw new Error(this.$texts.bulkImport.invalidUtf8Encoding())
				}
			}
			if (text.includes('\uFFFD') || text.includes('\u0000')) {
				throw new Error(this.$texts.bulkImport.invalidUtf8Encoding())
			}
			return text.replace(/^\uFEFF/, '')
		},
		async checkAndPreview() {
			if (!this.canCheckPreview) return
			this.resetPreviewState()

			if (!this.hasSelectedTarget) {
				this.validationErrors = [this.$texts.bulkImport.targetRequired()]
				return
			}

			this.validating = true
			const read = new AbortController()
			this.packetAbort = read
			let rawText
			try {
				rawText = await this.readImportText()
				if (read.signal.aborted) return
			} catch (error) {
				this.validationErrors = [error.message]
				return
			} finally {
				this.validating = false
				this.packetAbort = null
			}

			let parsed
			try {
				parsed = parsePaymentImport(rawText)
			} catch {
				this.validationErrors = [this.$texts.bulkImport.invalidJsonSyntax()]
				return
			}

			if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed) || !Array.isArray(parsed.payments)) {
				this.validationErrors = [this.$texts.bulkImport.invalidJsonRoot()]
				return
			}

			if (parsed.payments.length === 0) {
				this.validationErrors = [this.$texts.bulkImport.emptyPaymentsList()]
				return
			}

			if (parsed.payments.length > MAX_IMPORT_ENTRIES) {
				this.validationErrors = [this.$texts.bulkImport.tooManyPayments(MAX_IMPORT_ENTRIES)]
				return
			}

			this.importId = bytesToHex(sha256(JSON.stringify(parsed.payments)))
			this.parsedPayments = parsed.payments
			await this.runDryRunPreview()
			await this.$nextTick()
			this.$refs.previewHeading?.focus()
		},
		extractServerErrors(error, fallback) {
			const serverErrors = error?.response?.data?.errors
			if (Array.isArray(serverErrors) && serverErrors.length > 0) {
				return serverErrors.map(item => String(item))
			}
			return [extractError(error, fallback)]
		},
		async sendPacket(offset, dryRun, limit = IMPORT_BATCH_SIZE) {
			const body = new Blob([JSON.stringify({
				importId: this.importId,
				scope: this.importScope,
				projectId: this.projectId,
				offset,
				dryRun,
				payments: this.parsedPayments.slice(offset, offset + limit),
			})], { type: 'application/octet-stream' })
			const signal = this.packetAbort.signal
			const send = () => axios.put(generateUrl('/apps/cobudget/api/entries/import'), body, {
				headers: { 'Content-Type': 'application/octet-stream' }, signal,
			})
			try {
				return (await send()).data
			} catch (error) {
				if (error.response?.status !== 429 || signal.aborted) throw error
				const seconds = Number(error.response.headers?.['retry-after'])
				await this.waitForRateLimit(Number.isFinite(seconds) && seconds > 0 ? seconds + 1 : 61, signal)
				return (await send()).data
			}
		},
		waitForRateLimit(seconds, signal) {
			return new Promise((resolve, reject) => {
				const abort = () => {
					clearTimeout(timer)
					reject(new DOMException('Aborted', 'AbortError'))
				}
				const timer = setTimeout(() => {
					signal.removeEventListener('abort', abort)
					resolve()
				}, seconds * 1000)
				signal.addEventListener('abort', abort, { once: true })
				if (signal.aborted) abort()
			})
		},
		async runDryRunPreview() {
			if (!this.parsedPayments || this.validating) return
			this.validating = true
			this.packetAbort = new AbortController()
			this.importProgressTotal = this.parsedPayments.length
			const rows = []
			const categories = new Map()
			const partners = new Map()
			const pending = []
			let skipped = 0
			let expenseCents = 0
			let incomeCents = 0
			let expenses = 0
			let currency
			try {
				for (let offset = 0; offset < this.importProgressTotal; offset += IMPORT_BATCH_SIZE) {
					const data = await this.sendPacket(offset, true)
					this.importScope = data.scope
					currency = data.summary.currency
					skipped += data.skippedCount
					// Keep positions in the original file even when some rows were saved earlier.
					const end = Math.min(offset + IMPORT_BATCH_SIZE, this.importProgressTotal)
					for (let start = offset; start < end; start += data.commitLimit) {
						const limit = Math.min(data.commitLimit, end - start)
						const remaining = data.rows.filter(row => row.rowNumber > start && row.rowNumber <= start + limit).length
						if (remaining) {
							pending.push({ offset: start, limit, remaining })
						}
					}
					for (const row of data.rows) {
						rows.push(row)
						if (row.type === 'expense') {
							expenses++
							expenseCents += row.amountCents
						} else {
							incomeCents += row.amountCents
						}
					}
					for (const [items, names] of [[data.newCategories, categories], [data.newPaymentPartners, partners]]) {
						for (const item of items) {
							const key = item.type + ':' + item.name.toLowerCase().replace(/\s+/gu, ' ').trim()
							if (!names.has(key)) names.set(key, item)
						}
					}
					this.importProgressDone = Math.min(offset + IMPORT_BATCH_SIZE, this.importProgressTotal)
				}
				this.previewRows = rows
				this.previewSummary = { totalCount: rows.length, expenseCount: expenses, incomeCount: rows.length - expenses,
					expenseTotal: expenseCents / 100, incomeTotal: incomeCents / 100, currency }
				this.pendingPackets = pending
				this.skippedCount = skipped
				this.newCategories = [...categories.values()]
				this.newPaymentPartners = [...partners.values()]
			} catch (error) {
				if (!this.packetAbort.signal.aborted) this.validationErrors = this.extractServerErrors(error, this.$texts.bulkImport.importError())
			} finally {
				this.validating = false
				this.packetAbort = null
			}
		},
		async confirmImport() {
			if (!this.hasSelectedTarget || this.busy || !this.pendingPackets.length) return
			this.importing = true
			this.packetAbort = new AbortController()
			this.importProgressTotal = this.parsedPayments.length
			this.importProgressDone = this.skippedCount
			let importedCount = 0
			let createdCats = 0
			let createdPartners = 0
			try {
				for (const { offset, limit, remaining } of this.pendingPackets) {
					const data = await this.sendPacket(offset, false, limit)
					importedCount += data.importedCount
					createdCats += data.createdCategoriesCount
					createdPartners += data.createdPaymentPartnersCount
					this.importProgressDone += remaining
				}
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
				if (createdCats || createdPartners) {
					showToast(this.$texts.bulkImport.importSuccessWithMasterData(importedCount, createdCats, createdPartners))
				} else {
					showToast(this.$texts.bulkImport.importSuccess(importedCount))
				}
				this.importing = false
				this.goBack()
			} catch (error) {
				window.dispatchEvent(new CustomEvent('cobudget-data-changed'))
				const message = this.$texts.bulkImport.resumeHint()
				const stopped = this.packetAbort.signal.aborted
				this.resetPreviewState()
				this.validationErrors = stopped ? [message] : [...this.extractServerErrors(error, this.$texts.bulkImport.importError()), message]
			} finally {
				this.importing = false
				this.packetAbort = null
			}
		},
		openFormatModal() {
			this.formatModalOpen = true
		},
		closeFormatModal() {
			this.formatModalOpen = false
		},
	},
}
</script>

<style scoped src="../styles/bulk-import-tables.css"></style>

<style scoped>
.bulk-import-page {
	display: block;
	width: 100%;
	margin: 0;
	padding: 0 0 var(--cobudget-settings-heading-gap);
	box-sizing: border-box;
}

.bulk-import-content {
	width: min(var(--cobudget-form-max-width), calc(100% - var(--cobudget-form-inset) * 2));
	margin: 0 var(--cobudget-form-inset);
	display: flex;
	flex-direction: column;
	gap: var(--cobudget-section-gap);
	box-sizing: border-box;
}

.settings-block {
	display: flex;
	flex-direction: column;
	gap: var(--cobudget-content-gap);
	min-width: 0;
}

.settings-block > .settings-hint {
	margin: 0;
}

.bulk-import-field-heading {
	margin: 0;
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	line-height: 1.5;
	color: var(--color-main-text);
}

.bulk-import-loading {
	display: flex;
	align-items: center;
	gap: var(--cobudget-content-gap);
	padding-block: var(--cobudget-section-gap);
}

.bulk-import-note {
	margin: 0;
}

.bulk-import-error-list {
	margin: calc(var(--default-grid-baseline, 4px) * 2) 0 0;
	padding-inline-start: calc(var(--default-grid-baseline, 4px) * 5);
}

.bulk-import-area-options {
	display: flex;
	flex-direction: column;
	gap: 4px;
	width: min(100%, 420px);
	min-width: 0;
}

.bulk-import-area-switch {
	min-width: 0;
}

.bulk-import-area-switch-content {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	min-width: 0;
}

.bulk-import-area-switch-content > :not(.bulk-import-area-switch-name) {
	flex-shrink: 0;
}

.bulk-import-area-switch-name {
	flex: 1;
	min-width: 0;
	overflow-wrap: anywhere;
}

.bulk-import-preview-summary {
	display: flex;
	flex-direction: column;
	gap: var(--cobudget-content-gap);
}

.bulk-import-summary-headline,
.bulk-import-new-masterdata-heading {
	margin: 0;
}

.bulk-import-form-footer,
.bulk-import-file-controls,
.bulk-import-input-actions,
.bulk-import-preview-actions {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: var(--cobudget-content-gap);
}

.bulk-import-form-footer {
	justify-content: space-between;
	margin-block-start: var(--cobudget-content-gap);
}

.bulk-import-input-actions,
.bulk-import-preview-actions {
	margin-inline-start: auto;
	justify-content: flex-end;
	gap: 8px;
}

.bulk-import-json-textarea {
	width: 100%;
	margin: 0;
}

.bulk-import-json-textarea :deep(textarea) {
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	font-size: var(--cobudget-font-caption);
	line-height: 1.5;
}

.bulk-import-selected-file {
	color: var(--cobudget-text);
	font-size: var(--cobudget-font-body);
	overflow-wrap: anywhere;
}

.bulk-import-summary-stats {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 1.5);
	min-width: 0;
}

.bulk-import-summary-headline {
	font-weight: var(--cobudget-font-weight-heading);
	color: var(--cobudget-text);
}

.bulk-import-summary-totals {
	display: flex;
	flex-wrap: wrap;
	gap: var(--cobudget-content-gap);
	font-size: var(--cobudget-font-body);
	font-variant-numeric: tabular-nums;
}

.bulk-import-summary-metric.positive,
.col-amount.positive {
	color: var(--cobudget-success);
}

.bulk-import-summary-metric.negative,
.col-amount.negative {
	color: var(--cobudget-error);
}

.bulk-import-preview-actions {
	margin-block-start: var(--cobudget-content-gap);
}

.bulk-import-new-masterdata {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	padding-top: var(--cobudget-content-gap);
	border-top: 1px solid var(--cobudget-border);
}

.bulk-import-new-group {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 1.5);
}

.bulk-import-new-group-label {
	font-size: var(--cobudget-font-caption);
	color: var(--cobudget-text-muted);
}

.bulk-import-new-item-badge {
	gap: calc(var(--default-grid-baseline, 4px) * 1.5);
}

.bulk-import-new-item-type {
	font-weight: 400;
	opacity: 0.9;
}

.bulk-import-pagination {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	padding-top: var(--cobudget-content-gap);
}

.bulk-import-pagination .btn-page:first-child {
	justify-self: start;
}

.bulk-import-pagination .btn-page:last-child {
	justify-self: end;
}

.page-info {
	color: var(--color-text-maxcontrast);
	font-size: var(--cobudget-font-compact);
	text-align: center;
	white-space: nowrap;
}

.bulk-import-table .col-row {
	width: 56px;
	color: var(--cobudget-text-muted);
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
}

.bulk-import-table .col-date {
	width: 112px;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

.bulk-import-table .col-amount {
	width: 120px;
	text-align: end;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
	font-weight: 500;
}

.bulk-import-table .col-description {
	overflow-wrap: anywhere;
}

.bulk-import-cell--new {
	background: color-mix(in srgb, var(--cobudget-info-background) 65%, var(--cobudget-surface));
}

.bulk-import-cell-content {
	display: inline-flex;
	align-items: center;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline, 4px) * 1.5);
}

.bulk-import-cell-text {
	overflow-wrap: anywhere;
}

.bulk-import-empty-cell {
	color: var(--cobudget-text-muted);
}

@media (max-width: 768px) {
	.bulk-import-content {
		width: 100%;
		margin: 0;
	}

	.bulk-import-table td.bulk-import-cell--new {
		padding: 4px 8px;
		border-radius: var(--cobudget-badge-radius);
	}

	.btn-page {
		--default-clickable-area: var(--cobudget-mobile-touch-size, 44px);
	}

	.bulk-import-pagination {
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	}

	.page-info {
		grid-row: 1;
		grid-column: 1 / -1;
	}
}
</style>
