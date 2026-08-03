<template>
	<NcAppSidebar
		class="entry-sidebar"
		:class="{ 'entry-sidebar--mobile-save-visible': showMobileHeaderSave }"
		:name="modalTitle"
		:open="isOpen"
		:no-toggle="true"
		@opened="handleSidebarOpened"
		@update:open="handleSidebarOpenUpdate">
		<template #tertiary-actions>
			<NcButton
				v-if="isWideViewport"
				type="button"
				variant="tertiary"
				class="entry-sidebar-desktop-close"
				:aria-label="$texts.common.close()"
				:title="$texts.common.close()"
				@click="requestCloseSidebar()">
				<template #icon>
					<CloseIcon :size="20" aria-hidden="true" />
				</template>
			</NcButton>
			<NcButton
				v-if="showMobileHeaderSave"
				type="button"
				variant="primary"
				class="entry-sidebar-header-save"
				:aria-label="saveActionLabel"
				:title="saveActionLabel"
				:disabled="isSaveDisabled"
				@pointerdown.prevent
				@click="saveEntry">
				<template #icon>
					<ContentSaveIcon :size="22" aria-hidden="true" />
				</template>
			</NcButton>
		</template>
		<form
			ref="modalContent"
			class="modal-form"
			@submit.prevent="saveEntry"
			@keydown.esc.stop.prevent="handleEscape"
			@focusin="handleModalFocusIn"
			@focusout="handleModalFocusOut">
				<div class="modal-body">
					<div v-if="isEditing && isFutureContext" class="info-banner">
						<strong>{{ $texts.entry.futureOriginalNoticeTitle() }}</strong> {{ $texts.entry.futureOriginalNotice() }}
					</div>

					<div class="entry-required-panel">
						<div class="form-group date-col core-date">
							<input
								type="date"
								v-model="dateString"
								:min="minFutureDate"
								:aria-label="dateLabel"
								class="form-control"
								required>
						</div>

						<div class="form-group amount-col core-amount">
							<div
								class="amount-input-wrap"
								:class="{
									'has-currency': $currency,
									'is-income': entry.type === 'income',
									'is-expense': entry.type === 'expense',
								}">
								<div v-if="$enableIncomes" class="amount-type-field">
									<NcPopover
										v-model:shown="typeMenuOpen"
										:triggers="[]"
										:no-focus-trap="true"
										popup-role="listbox"
										placement="bottom-start">
										<template #trigger>
											<NcButton
												ref="amountTypeTrigger"
												id="entry-type-trigger"
												type="button"
												variant="tertiary-no-background"
												class="amount-type-trigger"
												role="combobox"
												aria-controls="entry-type-options"
												:aria-label="selectedEntryTypeLabel"
												:aria-activedescendant="typeMenuActiveDescendant || undefined"
												:title="selectedEntryTypeLabel"
												@click="toggleTypeMenu"
												@keydown.down.prevent.stop="openTypeMenuAndMove(1)"
												@keydown.up.prevent.stop="openTypeMenuAndMove(-1)"
												@keydown.home.prevent.stop="openTypeMenuAt(0)"
												@keydown.end.prevent.stop="openTypeMenuAt(entryTypeOptions.length - 1)"
												@keydown.enter.prevent.stop="confirmTypeMenuSelection"
												@keydown.space.prevent.stop="confirmTypeMenuSelection"
												@keydown.esc.prevent.stop="closeTypeMenu(true)"
												@keydown.tab="closeTypeMenu(false)">
												<span class="amount-type-trigger-content">
													<span class="amount-type-label">{{ selectedEntryTypeLabel }}</span>
													<ChevronDownIcon :size="18" class="amount-type-chevron" aria-hidden="true" />
												</span>
											</NcButton>
										</template>
										<div
											id="entry-type-options"
											class="amount-type-menu"
											role="listbox"
											aria-labelledby="entry-type-trigger"
											@keydown.esc.prevent.stop="closeTypeMenu(true)">
											<NcButton
												v-for="(option, index) in entryTypeOptions"
												:id="typeOptionId(index)"
												:key="option.value"
												type="button"
												variant="tertiary-no-background"
												class="amount-type-option"
												:class="[
													`is-${option.value}`,
													{
														'is-selected': entry.type === option.value,
														'is-highlighted': highlightedTypeIndex === index,
													}
												]"
												role="option"
												tabindex="-1"
												:aria-selected="entry.type === option.value ? 'true' : 'false'"
												@mouseenter="highlightedTypeIndex = index"
												@mousedown.prevent
												@click="selectEntryType(option.value)">
												<span class="amount-type-option-content">
													<span class="amount-type-check" aria-hidden="true">
														<CheckIcon v-if="entry.type === option.value" :size="18" />
													</span>
													<span>{{ option.label }}</span>
												</span>
											</NcButton>
										</div>
									</NcPopover>
								</div>
								<div class="amount-value-wrap" @click="focusAmountInput">
									<span v-if="$currency" class="amount-currency-prefix" aria-hidden="true">{{ $currency }}</span>
									<input
										type="text"
										ref="amountInput"
										v-model="entry.amountDisplay"
										:aria-label="amountLabel"
										inputmode="text"
										autocomplete="off"
										autocapitalize="off"
										autocorrect="off"
										spellcheck="false"
										pattern="[0-9.,+*\/\-]*"
										@input="sanitizeAmountInput"
										@blur="evaluateAmount"
										@keydown.enter.prevent="focusPaymentPartnerLookup"
										class="form-control amount-input"
										:class="{'bg-income': entry.type === 'income', 'bg-expense': entry.type === 'expense'}"
									required
										placeholder="0.00">
								</div>
							</div>
						</div>
					</div>

					<div class="entry-details-grid">
						<div class="form-group detail-paymentPartner">
							<label>{{ paymentPartnerLabel }}</label>
							<div
								ref="paymentPartnerLookupField"
								class="lookup-field"
								:class="{
									'has-clear-button': entry.paymentPartnerName,
									'has-payment-partner-number': selectedPaymentPartnerNumber,
								}">
								<button
									ref="paymentPartnerLookupTrigger"
									type="button"
									class="form-control lookup-trigger"
									role="combobox"
									aria-haspopup="listbox"
									:aria-expanded="showPaymentPartnerSuggestions ? 'true' : 'false'"
									aria-controls="paymentPartner-suggestions"
									@click="toggleLookup('paymentPartner')"
									@keydown.down.prevent="openLookupAndMove('paymentPartner', 1)"
									@keydown.up.prevent="openLookupAndMove('paymentPartner', -1)"
									@keydown.enter.prevent="handleLookupTriggerEnter('paymentPartner')"
									@keydown.esc.stop.prevent="closeLookup">
									<span v-if="entry.paymentPartnerName" class="lookup-trigger-value payment-partner-lookup-trigger-value">
										<span v-if="selectedPaymentPartnerNumber" class="lookup-trigger-code">{{ selectedPaymentPartnerNumber }}</span>
										<span class="lookup-trigger-name">{{ entry.paymentPartnerName }}</span>
									</span>
									<span v-else class="lookup-trigger-value is-placeholder">
										{{ $texts.entry.selectPlaceholder() }}
									</span>
									<ChevronDownIcon :size="18" class="lookup-chevron" aria-hidden="true" />
								</button>
								<button
									v-if="entry.paymentPartnerName"
									type="button"
									class="lookup-clear-button"
									:aria-label="$texts.entry.clearPaymentPartner()"
									:title="$texts.entry.clearPaymentPartner()"
									@mousedown.prevent
									@click.prevent="clearLookupValue('paymentPartner')">
									<CloseIcon :size="16" aria-hidden="true" />
								</button>
								<div v-if="showPaymentPartnerSuggestions" id="paymentPartner-suggestions" class="lookup-menu">
									<div class="lookup-options" role="listbox">
										<template v-for="section in paymentPartnerSuggestionSections" :key="section.label || 'paymentPartner-results'">
											<div v-if="section.label && section.items.length" class="lookup-group-label">{{ section.label }}</div>
											<button
												v-for="paymentPartner in section.items"
												:key="`${section.label || 'paymentPartner'}-${paymentPartner.id}`"
												type="button"
												class="lookup-option"
												:class="{ active: highlightedPaymentPartnerIndex === lookupIndex('paymentPartner', paymentPartner) }"
												role="option"
												:aria-selected="highlightedPaymentPartnerIndex === lookupIndex('paymentPartner', paymentPartner) ? 'true' : 'false'"
												@click="selectPaymentPartnerSuggestion(paymentPartner)">
												<span class="lookup-option-label">
													<span v-if="paymentPartner.number" class="lookup-option-code">{{ paymentPartner.number }}</span>
													<span class="lookup-option-name">{{ paymentPartner.name }}</span>
												</span>
											</button>
										</template>
										<div v-if="paymentPartnerSuggestions.length === 0" class="lookup-empty">{{ $texts.entry.noMatchingEntries() }}</div>
									</div>
									<div class="lookup-create">
										<button v-if="lookupInputMode !== 'paymentPartner'" type="button" class="lookup-add-button" @click="startLookupInput('paymentPartner')">
											<PlusIcon :size="18" aria-hidden="true" />
											<span>{{ $texts.entry.addNewPaymentPartner() }}</span>
										</button>
										<div v-else class="lookup-create-form">
											<input
												ref="paymentPartnerCreateInput"
												type="text"
												v-model="lookupDraft.paymentPartner"
												class="form-control lookup-create-input"
												:placeholder="$texts.entry.newPaymentPartnerPlaceholder()"
												autocomplete="off"
												@input="resetLookupHighlight('paymentPartner')"
												@keydown.enter.prevent="handleLookupDraftEnter('paymentPartner')"
												@keydown.esc.stop.prevent="cancelLookupInput('paymentPartner')">
											<button type="button" class="lookup-use-button" :disabled="!lookupDraft.paymentPartner.trim()" @click="applyLookupDraft('paymentPartner')">
												{{ $texts.entry.useNewValue() }}
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="form-group detail-category">
							<label>{{ $texts.entry.category() }}</label>
							<div
								ref="categoryLookupField"
									class="lookup-field category-input-wrap"
									:class="{
										'has-leading-icon': selectedCategoryIcon,
										'has-clear-button': hasCategorySelection,
										'has-category-code': selectedCategoryCode,
									}">
								<CategoryIcon v-if="selectedCategoryIcon" :icon="selectedCategoryIcon" :size="18" class="category-input-icon" />
								<button
									ref="categoryLookupTrigger"
									type="button"
									class="form-control lookup-trigger"
									role="combobox"
									aria-haspopup="listbox"
									:aria-expanded="showCategorySuggestions ? 'true' : 'false'"
									aria-controls="category-suggestions"
									@click="toggleLookup('category')"
									@keydown.down.prevent="openLookupAndMove('category', 1)"
									@keydown.up.prevent="openLookupAndMove('category', -1)"
									@keydown.enter.prevent="handleLookupTriggerEnter('category')"
									@keydown.esc.stop.prevent="closeLookup">
									<span v-if="hasCategorySelection" class="lookup-trigger-value category-lookup-trigger-value">
										<span v-if="selectedCategoryCode" class="lookup-trigger-code">{{ selectedCategoryCode }}</span>
										<span class="lookup-trigger-name">{{ selectedCategoryDisplayName }}</span>
									</span>
									<span v-else class="lookup-trigger-value is-placeholder">
										{{ $texts.entry.selectPlaceholder() }}
									</span>
									<ChevronDownIcon :size="18" class="lookup-chevron" aria-hidden="true" />
								</button>
								<button
									v-if="hasCategorySelection"
									type="button"
									class="lookup-clear-button"
									:aria-label="$texts.entry.clearCategory()"
									:title="$texts.entry.clearCategory()"
									@mousedown.prevent
									@click.prevent="clearLookupValue('category')">
									<CloseIcon :size="16" aria-hidden="true" />
								</button>
								<div v-if="showCategorySuggestions" id="category-suggestions" class="lookup-menu">
									<div class="lookup-options" role="listbox">
										<template v-for="section in categorySuggestionSections" :key="section.label || 'category-results'">
											<div v-if="section.label && section.items.length" class="lookup-group-label">{{ section.label }}</div>
											<button
												v-for="cat in section.items"
												:key="`${section.label || 'category'}-${cat.id}`"
												type="button"
												class="lookup-option"
												:class="{
													active: highlightedCategoryIndex === lookupIndex('category', cat),
													'is-selected': isCategorySelected(cat),
													'is-subcategory': !!categoryParentId(cat)
												}"
												role="option"
												:aria-selected="isCategorySelected(cat) ? 'true' : 'false'"
												@click.stop="selectCategorySuggestion(cat)">
												<CategoryIcon :icon="cat.icon || 'Shape'" :size="16" />
												<span class="lookup-option-label">
													<span v-if="cat.code" class="lookup-option-code">{{ cat.code }}</span>
													<span class="lookup-option-name">{{ cat.name }}</span>
												</span>
											</button>
										</template>
										<div v-if="categorySuggestions.length === 0" class="lookup-empty">{{ $texts.entry.noMatchingEntries() }}</div>
									</div>
									<div class="lookup-create">
										<button v-if="lookupInputMode !== 'category'" type="button" class="lookup-add-button" @click="startLookupInput('category')">
											<PlusIcon :size="18" aria-hidden="true" />
											<span>{{ $texts.entry.addNewCategory() }}</span>
										</button>
										<div v-else class="lookup-create-form">
											<input
												ref="categoryCreateInput"
												type="text"
												v-model="lookupDraft.category"
												class="form-control lookup-create-input"
												:placeholder="$texts.entry.newCategoryPlaceholder()"
												autocomplete="off"
												@input="resetLookupHighlight('category')"
												@keydown.enter.prevent="handleLookupDraftEnter('category')"
												@keydown.esc.stop.prevent="cancelLookupInput('category')">
											<button type="button" class="lookup-use-button" :disabled="!lookupDraft.category.trim()" @click="applyLookupDraft('category')">
												{{ $texts.entry.useNewValue() }}
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="form-group core-description detail-description">
							<label>{{ $texts.entry.description() }}</label>
							<input type="text" v-model="entry.description" class="form-control">
						</div>

						<div class="form-group tags-group detail-tags" v-if="hasAvailableTags">
							<div class="tags-toggles">
								<label class="tag-toggle" v-if="$enableImportantPayments">
									<input type="checkbox" v-model="entry.isImportant">
									<span class="tag-btn">{{ $texts.labels.important() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableReviewPayments">
									<input type="checkbox" v-model="entry.needsReview">
									<span class="tag-btn">{{ $texts.labels.review() }}</span>
								</label>
								<label class="tag-toggle" v-if="entry.type === 'expense' && $enableFixedCosts">
									<input type="checkbox" v-model="entry.isFixedCost">
									<span class="tag-btn">{{ $texts.labels.fixedCosts() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableChildRelated">
									<input type="checkbox" v-model="entry.isChildRelated">
									<span class="tag-btn">{{ $texts.labels.children() }}</span>
								</label>
								<label class="tag-toggle" v-if="entry.type === 'expense' && $enableSubscriptions">
									<input type="checkbox" v-model="entry.isSubscription">
									<span class="tag-btn">{{ $texts.labels.subscription() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableTaxRelevant">
									<input type="checkbox" v-model="entry.isTaxRelevant">
									<span class="tag-btn">{{ $texts.labels.taxRelevant() }}</span>
								</label>
							</div>
						</div>

					<div
						v-if="$enableProjects && (!projectSelectionLocked || showProjectPayerSelect || showProjectSplitMode)"
							class="assignment-fields">
							<div class="project-assignment-row" :class="{ 'has-project-payer': showProjectPayerSelect, 'has-split-mode': showProjectSplitMode }">
								<div v-if="!projectSelectionLocked" class="form-group detail-project">
									<label>{{ $texts.entry.area() }}</label>
									<div
										v-if="useDirectAreaSelection"
										class="area-choice-grid"
										:style="{ '--area-choice-count': areaSelectionOptions.length }"
										role="radiogroup"
										:aria-label="$texts.entry.area()">
										<button
											v-for="option in areaSelectionOptions"
											:key="option.key"
											type="button"
											class="area-choice"
											:class="{
												'area-choice--project': option.id !== null,
											}"
											:style="areaChoiceStyle(option)"
											role="radio"
											:aria-checked="isAreaSelected(option.id) ? 'true' : 'false'"
											@click="selectArea(option.id)">
											<span class="area-choice__icon" aria-hidden="true">
												<WalletIcon v-if="option.id === null" :size="22" />
												<FolderIcon v-else :size="22" :fillColor="option.color || 'currentColor'" />
											</span>
											<span class="area-choice__label">{{ option.name }}</span>
										</button>
									</div>
									<select v-else v-model="entry.projectId" class="form-control select-control" :aria-label="$texts.entry.area()">
										<option :value="null">{{ $texts.entry.personalAssignment() }}</option>
										<optgroup v-if="activeProjects.length" :label="$texts.entry.areas()">
											<option v-for="p in activeProjects" :key="p.id" :value="p.id">
												{{ p.name }}
											</option>
										</optgroup>
									</select>
								</div>
								<div class="form-group detail-project-payer" v-if="showProjectPayerSelect">
									<label>{{ projectPayerLabel }}</label>
									<select v-model="entry.userId" class="form-control select-control">
										<option
											v-for="member in projectPayerOptions"
											:key="member.id"
											:value="member.id"
											:disabled="member.isFormer || !member.isActive">
											{{ member.displayName }}
										</option>
									</select>
								</div>
								<div class="form-group detail-split-mode" v-if="showProjectSplitMode">
									<label>{{ $texts.entry.split() }}</label>
									<select v-model="splitModeChoice" class="form-control select-control">
										<option value="project_shares">{{ $texts.entry.projectShares() }}</option>
										<option
											v-for="member in projectSplitOptions"
											:key="`single-${member.id}`"
											:value="`single_user:${member.id}`"
											:disabled="member.isFormer || !member.isActive">
											{{ $texts.entry.singleUserSplitTarget(member.displayName) }}
										</option>
									</select>
								</div>
							</div>
						</div>

					</div>

					<details class="planning-section" :open="showPlanningOptions" @toggle="showPlanningOptions = $event.target.open">
						<summary>
							<span>{{ showAttachmentSection ? $texts.entry.planningWithReceipts() : $texts.entry.planning() }}</span>
							<span
								v-if="!showPlanningOptions && planningStatusItems.length"
								class="planning-summary-status">
								{{ planningStatusItems.join(' · ') }}
							</span>
						</summary>
						<div v-if="showAttachmentSection" class="planning-card planning-attachments-card">
							<div class="attachments-inline planning-attachments">
								<label class="attachment-upload-btn">
									<input ref="attachmentInput" type="file" multiple @change="onAttachmentFilesSelected">
									<span>{{ $texts.entry.addReceipt() }}</span>
								</label>

								<div v-if="attachmentsLoading" class="attachments-empty">{{ $texts.entry.receiptsLoading() }}</div>
								<ul v-if="hasAttachments" class="attachment-list">
									<li v-for="attachment in attachments" :key="`existing-${attachment.id}`">
										<a :href="attachmentDownloadUrl(attachment)" target="_blank" rel="noopener">
											{{ attachment.file_name }}
										</a>
										<span class="attachment-meta">{{ formatFileSize(attachment.file_size) }}</span>
										<button v-if="canDeleteAttachment(attachment)" type="button" class="attachment-remove" :disabled="loading" @click="deleteAttachment(attachment)" :aria-label="$texts.entry.removeReceipt()">×</button>
									</li>
									<li v-for="(file, index) in pendingAttachments" :key="`pending-${index}-${file.name}`" class="attachment-pending">
										<span>{{ file.name }}</span>
										<span class="attachment-meta">{{ $texts.entry.uploadOnSave() }}</span>
										<button type="button" class="attachment-remove" :disabled="loading" @click="removePendingAttachment(index)" :aria-label="$texts.entry.removeSelection()">×</button>
									</li>
								</ul>
							</div>
						</div>
						<div class="planning-grid">
							<div class="form-group recurrence-group planning-card" v-if="$enableFuturePayments">
								<div class="recurrence-options">
									<div class="recurrence-inputs" :class="{ 'is-recurring': entry.recurrenceInterval !== 'none' }">
										<div class="form-group recurrence-multiplier-field" v-if="entry.recurrenceInterval !== 'none'">
											<label>{{ $texts.entry.repeatEvery() }}</label>
											<input type="number" v-model.number="entry.recurrenceMultiplier" class="form-control recurrence-multiplier-input" min="1" required :aria-label="$texts.entry.repeatEvery()">
										</div>
										<div class="form-group recurrence-interval-field">
											<label v-if="entry.recurrenceInterval === 'none'">{{ $texts.entry.repeatEvery() }}</label>
											<select v-model="entry.recurrenceInterval" class="form-control select-control" :aria-label="$texts.entry.recurrenceInterval()">
												<option value="none">{{ $texts.entry.neverOnce() }}</option>
												<option value="day">{{ $texts.entry.days() }}</option>
												<option value="week">{{ $texts.entry.weeks() }}</option>
												<option value="month">{{ $texts.entry.months() }}</option>
											</select>
										</div>
										<div class="form-group recurrence-end-field date-col" v-if="entry.recurrenceInterval !== 'none'">
											<label>{{ $texts.entry.endDateOptional() }}</label>
											<input type="date" v-model="recurrenceEndDateString" class="form-control" />
										</div>
									</div>
									<div v-if="entry.recurrenceInterval !== 'none' && nextRecurrence" class="recurrence-preview">
										{{ $texts.entry.nextEntryAt(nextRecurrence) }}
									</div>
								</div>
							</div>

							<div class="form-group reminder-group planning-card">
								<div class="recurrence-options" :class="{ 'is-active-bg': entry.hasReminder }">
									<div class="form-row align-items-end">
										<div class="form-group half reminder-choice-field">
											<label>{{ $texts.entry.reminder() }}</label>
											<select v-model="entry.hasReminder" class="form-control select-control">
												<option :value="false">{{ $texts.entry.noReminder() }}</option>
												<option :value="true">{{ $texts.entry.remindMeAt() }}</option>
											</select>
										</div>
										<div class="form-group half date-col reminder-date-field" v-if="entry.hasReminder">
											<label>{{ $texts.entry.date() }}</label>
											<input type="date" v-model="reminderDateString" class="form-control" />
										</div>
									</div>
									<div class="form-row reminder-text-row" v-if="entry.hasReminder">
										<div class="form-group full">
											<label>{{ $texts.entry.reminderTextOptional() }}</label>
											<input type="text" v-model="entry.reminderText" class="form-control" :placeholder="$texts.entry.reminderTextPlaceholder()">
										</div>
									</div>
								</div>
							</div>
						</div>
					</details>

				</div>

				<div class="form-actions">
					<ModalActions
						flush
						danger-row
						inline-mobile
						:primary-label="saveActionLabel"
						:primary-disabled="isSaveDisabled"
						:primary-busy="loading"
						primary-type="button"
						:show-cancel="showCancelAction"
						:primary-busy-label="saveActionBusyLabel"
						@primary="saveEntry"
						@cancel="requestCloseSidebar">
						<template v-if="isEditing && entry.can_delete !== false" #left>
							<CbIconButton
								class="entry-delete-icon-button"
								variant="ghost"
								:aria-label="$texts.common.delete()"
								:title="$texts.common.delete()"
								:disabled="loading"
								@click="deleteEntry">
								<DeleteOutlineIcon :size="22" />
							</CbIconButton>
						</template>
					</ModalActions>
				</div>
			</form>
	</NcAppSidebar>
	<ConfirmModal
		:show="!!confirmDialog"
		:title="confirmDialog ? confirmDialog.title : ''"
		:message="confirmDialog ? confirmDialog.message : ''"
		:confirm-label="confirmDialog ? confirmDialog.confirmLabel : ''"
		:confirm-variant="confirmDialog ? confirmDialog.confirmVariant : 'primary'"
		@confirm="resolveConfirm(true)"
		@cancel="resolveConfirm(false)" />
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import CategoryIcon from './CategoryIcon.vue'
import ModalActions from './ModalActions.vue'
import ConfirmModal from './ConfirmModal.vue'
import { showRequestError, showToast } from '../services/notifications'
import { readWorkspaceId } from '../services/workspaceStorage'
import { getAreaColorPalette } from '../utils/areaColor'
import { categoryParentId, sortCategoriesHierarchically } from '../utils/categoryHierarchy'
import { hasEntryFormChanges, snapshotEntryForm } from '../utils/entryFormState'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import WalletIcon from 'vue-material-design-icons/Wallet.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import CbIconButton from './CbIconButton.vue'

const normalizedPositiveId = value => {
	const id = Number(value || 0)
	return Number.isInteger(id) && id > 0 ? id : null
}

export default {
	name: 'EntrySidebar',
	components: { CategoryIcon, ModalActions, ConfirmModal, CloseIcon, ContentSaveIcon, DeleteOutlineIcon, ChevronDownIcon, CheckIcon, PlusIcon, WalletIcon, FolderIcon, NcAppSidebar, NcButton, NcPopover, CbIconButton },
	emits: ['closed', 'mode-changed', 'saved'],
	props: {
		projectId: {
			type: Number,
			default: null
		},
		editingEntry: {
			type: Object,
			default: null
		}
	},
	data() {
		return {
			isOpen: false,
			pendingInitialFocus: false,
			isFutureContext: false,
			loading: false,
			entry: {
				type: 'expense',
				amountDisplay: '',
				description: '',
				categoryId: null,
				categoryName: '',
				paymentPartnerName: '',
				projectId: this.projectId,
				userId: window.OC?.currentUser?.uid || '',
				splitMode: 'project_shares',
				splitUserId: null,
				date: new Date(),
				recurrenceInterval: 'none',
				recurrenceMultiplier: 1,
				recurrenceEndDate: null,
				isSubscription: false,
				isFixedCost: false,
				isChildRelated: false,
				isImportant: false,
				needsReview: false,
				isTaxRelevant: false,
				hasReminder: false,
				reminderDate: null,
				reminderText: ''
			},
			projects: [],
			categories: [],
			paymentPartners: [],
			internalEditingEntry: null,
			isDuplicateMode: false,
			projectSelectionLocked: false,
			newEntryDefaultType: 'expense',
			isInitializingEntry: false,
			originalProjectId: null,
			showPlanningOptions: false,
			confirmDialog: null,
			typeMenuOpen: false,
			highlightedTypeIndex: -1,
			focusedLookupField: null,
			lookupInputMode: null,
			lookupDraft: {
				category: '',
				paymentPartner: ''
			},
			highlightedCategoryIndex: -1,
			highlightedPaymentPartnerIndex: -1,
			attachments: [],
			pendingAttachments: [],
			attachmentsLoading: false,
			modalHistoryToken: null,
			closedByHistory: false,
			ignoreNextPopState: false,
			ignorePopStateTimer: null,
			wideViewportMedia: null,
			isWideViewport: false,
			mobileFormFieldFocused: false,
			mobileFocusTimer: null,
			categorySelectionSource: null,
			categorySuggestionRequestId: 0,
			entryFormBaseline: ''
		}
	},
	computed: {
		modalTitle() {
			const isIncome = this.entry.type === 'income';
			if (this.isEditing) {
				return isIncome
					? this.$texts.entry.editIncome()
					: this.$texts.entry.editExpense();
			}
			if (this.isDuplicateMode) {
				return isIncome
					? this.$texts.entry.copyIncome()
					: this.$texts.entry.copyExpense();
			}
			if (this.isFutureContext) {
				return isIncome
					? this.$texts.entry.planIncome()
					: this.$texts.entry.planExpense();
			}
			return isIncome
				? this.$texts.entry.createNewIncome()
				: this.$texts.entry.createNewExpense();
		},
		dateLabel() {
			if (this.isFutureContext && !this.isEditing) {
				return this.$texts.entry.plannedFor();
			}
			return this.entry.type === 'income' ? this.$texts.entry.receivedOn() : this.$texts.entry.paidOn();
		},
		amountLabel() {
			const label = this.$texts.entry.amount();
			return this.$currency ? `${label} (${this.$currency})` : label;
		},
		entryTypeOptions() {
			return [
				{ value: 'expense', label: this.$texts.common.expense() },
				{ value: 'income', label: this.$texts.common.income() }
			];
		},
		selectedEntryTypeLabel() {
			return this.entry.type === 'income'
				? this.$texts.common.income()
				: this.$texts.common.expense();
		},
		typeMenuActiveDescendant() {
			if (!this.typeMenuOpen || this.highlightedTypeIndex < 0) {
				return '';
			}
			return this.typeOptionId(this.highlightedTypeIndex);
		},
		paymentPartnerLabel() {
			return this.entry.type === 'income' ? this.$texts.entry.receivedFrom() : this.$texts.entry.paidTo();
		},
		hasAvailableTags() {
			const hasGeneralTags = this.$enableChildRelated
				|| this.$enableImportantPayments
				|| this.$enableReviewPayments
				|| this.$enableTaxRelevant;

			if (this.entry.type === 'expense') {
				return hasGeneralTags
					|| this.$enableSubscriptions
					|| this.$enableFixedCosts;
			}

			return hasGeneralTags;
		},
		selectedCategory() {
			const categoryId = normalizedPositiveId(this.entry.categoryId)
			if (categoryId !== null) {
				return this.categories.find(category => Number(category.id) === categoryId) || null
			}

			const name = this.entry.categoryName;
			if (!name) return null;
			return this.categories.find(c => c.name.toLowerCase() === name.toLowerCase()) || null;
		},
		selectedCategoryId() {
			return normalizedPositiveId(this.entry.categoryId);
		},
		hasCategorySelection() {
			return this.selectedCategoryId !== null || String(this.entry.categoryName || '').trim() !== '';
		},
		selectedCategoryIcon() {
			return this.selectedCategory ? (this.selectedCategory.icon || 'Shape') : null;
		},
		selectedCategoryCode() {
			return String(this.selectedCategory?.code ?? '').trim();
		},
		selectedCategoryDisplayName() {
			return this.selectedCategory
				? String(this.selectedCategory.name || '').trim()
				: this.entry.categoryName;
		},
		selectedPaymentPartnerNumber() {
			return String(this.selectedPaymentPartner()?.number ?? '').trim();
		},
		isValid() {
			const amt = this.$parseAmount(this.entry.amountDisplay)
			return !isNaN(amt) && amt > 0;
		},
		filteredCategories() {
			return sortCategoriesHierarchically(this.categories.filter(c => c.type === this.entry.type || !c.type));
		},
		filteredPaymentPartners() {
			return this.paymentPartners.filter(p => p.type === this.entry.type || !p.type);
		},
		categoryLookupQuery() {
			return this.lookupQueryForField('category');
		},
		paymentPartnerLookupQuery() {
			return this.lookupQueryForField('paymentPartner');
		},
		categorySuggestionSections() {
			return this.buildSuggestionSections(
				this.filteredCategories,
				this.categoryLookupQuery,
				this.$texts.entry.allCategories(),
				['code', 'parent_name'],
				true
			);
		},
		categorySuggestions() {
			return this.categorySuggestionSections.flatMap(section => section.items);
		},
		paymentPartnerSuggestionSections() {
			return this.buildSuggestionSections(
				this.filteredPaymentPartners,
				this.paymentPartnerLookupQuery,
				this.$texts.entry.allPaymentPartners(),
				['number']
			);
		},
		paymentPartnerSuggestions() {
			return this.paymentPartnerSuggestionSections.flatMap(section => section.items);
		},
		showCategorySuggestions() {
			return this.focusedLookupField === 'category';
		},
		showPaymentPartnerSuggestions() {
			return this.focusedLookupField === 'paymentPartner';
		},
		isEditing() {
			return !!this.internalEditingEntry;
		},
		isCreatingNewEntry() {
			return this.isOpen && !this.isEditing && !this.isDuplicateMode;
		},
		hasUnsavedChanges() {
			if (!this.isOpen || this.isInitializingEntry) {
				return false;
			}

			return this.isDuplicateMode || hasEntryFormChanges(
				this.entryFormBaseline,
				this.entry,
				this.pendingAttachments.length
			);
		},
		showAttachmentSection() {
			return this.$enableReceipts;
		},
		hasAttachments() {
			return this.attachments.length > 0 || this.pendingAttachments.length > 0;
		},
		planningStatusItems() {
			const items = [];
			const receiptCount = this.attachments.length + this.pendingAttachments.length;

			if (this.showAttachmentSection && receiptCount > 0) {
				items.push(this.$texts.entry.linkedReceipts(receiptCount));
			}
			if (this.$enableFuturePayments && this.entry.recurrenceInterval !== 'none') {
				items.push(this.$texts.entry.recurrenceActive());
			}
			if (this.entry.hasReminder && this.reminderDateString) {
				items.push(this.$texts.entry.reminderActive());
			}

			return items;
		},
		saveActionLabel() {
			return this.$texts.common.save();
		},
		saveActionBusyLabel() {
			return this.$texts.common.saveBusy();
		},
		isSaveDisabled() {
			return !this.isValid
				|| this.loading
				|| this.isInitializingEntry
				|| (this.isEditing && !this.hasUnsavedChanges);
		},
		showCancelAction() {
			return !this.isWideViewport;
		},
		showMobileHeaderSave() {
			return this.isOpen && this.isMobileViewport() && this.mobileFormFieldFocused && !this.confirmDialog;
		},
		activeProjects() {
			if (!this.$enableProjects) {
				return [];
			}
			return this.projects.filter(p => !p.is_archived);
		},
		useDirectAreaSelection() {
			return this.activeProjects.length <= 3;
		},
		areaSelectionOptions() {
			return [
				{
					key: 'personal',
					id: null,
					name: this.$texts.entry.personalAssignment(),
					color: 'var(--color-primary-element, #00679e)'
				},
				...this.activeProjects.map(project => ({
					key: `area-${project.id}`,
					id: project.id,
					name: project.name,
					color: project.color || 'var(--color-primary-element, #00679e)'
				}))
			];
		},
		selectedProject() {
			if (!this.entry.projectId) {
				return null;
			}

			return this.projects.find(p => Number(p.id) === Number(this.entry.projectId)) || null;
		},
		normalizedProjectMembers() {
			if (!this.selectedProject || !Array.isArray(this.selectedProject.members)) {
				return [];
			}

			return this.selectedProject.members
				.map(member => this.normalizeProjectMember(member))
				.filter(member => member.id !== '');
		},
		projectPayerOptions() {
			const preserveHistorical = this.isEditing
				&& Number(this.entry.projectId) === Number(this.originalProjectId)
				? String(this.entry.userId || '')
				: '';
			return this.normalizedProjectMembers.filter(member =>
				(member.isActive !== false && member.isFormer !== true)
				|| member.id === preserveHistorical
			);
		},
		projectSplitOptions() {
			const preserveHistorical = this.isEditing
				&& Number(this.entry.projectId) === Number(this.originalProjectId)
				? String(this.entry.splitUserId || '')
				: '';
			return this.normalizedProjectMembers.filter(member =>
				(member.isActive !== false && member.isFormer !== true)
				|| member.id === preserveHistorical
			);
		},
		showProjectPayerSelect() {
			return this.$enableSharedProjects && !!this.entry.projectId && this.projectPayerOptions.length > 1;
		},
		showProjectSplitMode() {
			return this.$enableSharedProjects
				&& !!this.entry.projectId
				&& this.normalizedProjectMembers.length > 1
				&& this.projectSplitOptions.length > 0;
		},
		projectPayerLabel() {
			return this.entry.type === 'income' ? this.$texts.entry.receivedBy() : this.$texts.entry.paidBy();
		},
		splitModeChoice: {
			get() {
				if (this.entry.splitMode === 'single_user') {
					const targetUserId = this.entry.splitUserId || this.entry.userId || this.currentUserId();
					return `single_user:${targetUserId}`;
				}

				return 'project_shares';
			},
			set(value) {
				const rawValue = String(value || '');
				if (rawValue.startsWith('single_user:')) {
					const userId = rawValue.slice('single_user:'.length);
					this.entry.splitMode = 'single_user';
					this.entry.splitUserId = userId || this.entry.userId || this.currentUserId();
					return;
				}

				this.entry.splitMode = 'project_shares';
				this.entry.splitUserId = null;
			}
		},
		selectedProjectUserLabel() {
			const member = this.normalizedProjectMembers.find(option => option.id === this.entry.userId);
			return member ? member.displayName : this.$texts.entry.selectedUser();
		},
		nextRecurrence() {
			if (this.entry.recurrenceInterval === 'none' || !this.entry.date) return null;
			
			const baseDate = new Date(this.entry.date);
			const multiplier = parseInt(this.entry.recurrenceMultiplier) || 1;
			const interval = this.entry.recurrenceInterval;
			
			const d = new Date(baseDate);
			if (interval === 'day') {
				d.setDate(d.getDate() + multiplier);
			} else if (interval === 'week') {
				d.setDate(d.getDate() + (multiplier * 7));
			} else if (interval === 'month') {
				d.setMonth(d.getMonth() + multiplier);
			}
			d.setHours(9, 0, 0, 0);
			
			if (this.entry.recurrenceEndDate && d > this.entry.recurrenceEndDate) {
				return null; // Stop if past end date
			}
			
			const dateStr = d.toLocaleDateString(undefined, { day: '2-digit', month: '2-digit', year: 'numeric' });
			const timeStr = d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
			return this.$texts.entry.nextRecurrenceAt(dateStr, timeStr);
		},
		dateString: {
			get() {
				if (!this.entry.date) return '';
				const d = new Date(this.entry.date);
				const year = String(d.getFullYear()).padStart(4, '0');
				const month = String(d.getMonth() + 1).padStart(2, '0');
				const day = String(d.getDate()).padStart(2, '0');
				return `${year}-${month}-${day}`;
			},
			set(val) {
				if (val) {
					const d = new Date(val);
					d.setHours(12, 0, 0, 0);
					this.entry.date = d;
				}
			}
		},
		recurrenceEndDateString: {
			get() {
				return this.toDateInputValue(this.entry.recurrenceEndDate);
			},
			set(val) {
				this.entry.recurrenceEndDate = this.fromDateInputValue(val, 23, 59, 59);
			}
		},
		reminderDateString: {
			get() {
				return this.toDateInputValue(this.entry.reminderDate);
			},
			set(val) {
				this.entry.reminderDate = this.fromDateInputValue(val, 9, 0, 0);
			}
		},
		minFutureDate() {
			if (this.isFutureContext && !this.isEditing) {
				const d = new Date();
				const month = String(d.getMonth() + 1).padStart(2, '0');
				const day = String(d.getDate()).padStart(2, '0');
				return `${d.getFullYear()}-${month}-${day}`;
			}
			return null;
		}
	},
	mounted() {
		// Data lists are now fetched when modal opens
		window.addEventListener('popstate', this.handleModalPopState);
		document.addEventListener('mousedown', this.handleLookupOutside);
		if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
			this.wideViewportMedia = window.matchMedia('(min-width: 1025px)');
			this.updateWideViewport(this.wideViewportMedia);
			this.wideViewportMedia.addEventListener?.('change', this.updateWideViewport);
		}
	},
	beforeUnmount() {
		window.removeEventListener('popstate', this.handleModalPopState);
		document.removeEventListener('mousedown', this.handleLookupOutside);
		this.wideViewportMedia?.removeEventListener?.('change', this.updateWideViewport);
		if (this.ignorePopStateTimer) {
			window.clearTimeout(this.ignorePopStateTimer);
			this.ignorePopStateTimer = null;
		}
		this.clearMobileFocusTimer();
		this.releaseModalHistory({ skipBack: true });
	},
	watch: {
		typeMenuOpen(isOpen) {
			if (!isOpen) {
				this.highlightedTypeIndex = -1;
				return;
			}

			if (this.highlightedTypeIndex < 0) {
				this.highlightedTypeIndex = this.selectedTypeOptionIndex();
			}
		},
		'entry.projectId': async function(projectId) {
			if (this.isInitializingEntry) {
				return;
			}

			const previousCategorySource = this.categorySelectionSource;
			const globalLookups = this.selectedGlobalLookupNames();
			if (previousCategorySource === 'suggested') {
				globalLookups.category = '';
			}
			this.invalidateCategorySuggestion(true);
			this.entry.splitMode = 'project_shares';
			this.entry.splitUserId = null;
			this.resetScopedLookups();
			await this.fetchDataLists(projectId);
			this.restoreGlobalLookupNames(globalLookups);
			this.categorySelectionSource = this.entry.categoryName ? previousCategorySource : null;
			await this.ensureProjectMembers(projectId);
			this.syncEntryUserWithProject(this.currentUserId());
			await this.maybeSuggestCategory();
		}
	},
	methods: {
		updateWideViewport(event) {
			this.isWideViewport = !!event?.matches;
		},
		areaChoiceStyle(option) {
			if (option.id === null || !this.isAreaSelected(option.id)) {
				return {};
			}

			const palette = getAreaColorPalette(option.color);
			return {
				'--area-choice-selected-background': palette.background,
				'--area-choice-selected-text': palette.foreground,
				'--area-choice-selected-border': palette.foreground
			};
		},
		isAreaSelected(projectId) {
			if (projectId === null) {
				return !this.entry.projectId;
			}

			return Number(this.entry.projectId) === Number(projectId);
		},
		selectArea(projectId) {
			if (this.isAreaSelected(projectId)) {
				return;
			}

			this.entry.projectId = projectId;
		},
		markEntryClean() {
			this.entryFormBaseline = snapshotEntryForm(this.entry);
		},
		emitSidebarMode() {
			this.$emit('mode-changed', {
				isOpen: this.isOpen,
				isCreatingNewEntry: this.isCreatingNewEntry,
				isEditing: this.isEditing,
				isDuplicateMode: this.isDuplicateMode,
				isFutureContext: this.isFutureContext
			});
		},
		openConfirm({ title, message, confirmLabel, confirmVariant = 'primary' }) {
			return new Promise(resolve => {
				this.confirmDialog = {
					title,
					message,
					confirmLabel,
					confirmVariant,
					resolve
				};
			});
		},
		resolveConfirm(confirmed) {
			const resolver = this.confirmDialog?.resolve;
			this.confirmDialog = null;
			if (resolver) {
				resolver(confirmed);
			}
		},
		async confirmDiscardChanges() {
			if (this.confirmDialog || this.loading) {
				return false;
			}
			if (!this.hasUnsavedChanges) {
				return true;
			}

			return this.openConfirm({
				title: this.$texts.entry.discardChangesTitle(),
				message: this.$texts.entry.discardChangesMessage(),
				confirmLabel: this.$texts.entry.discardChangesConfirm(),
				confirmVariant: 'danger'
			});
		},
		async requestCloseSidebar(options = {}) {
			if (!(await this.confirmDiscardChanges())) {
				return false;
			}

			this.closeSidebar(options);
			return true;
		},
		clearMobileFocusTimer() {
			if (this.mobileFocusTimer) {
				window.clearTimeout(this.mobileFocusTimer);
				this.mobileFocusTimer = null;
			}
		},
		isKeyboardInputTarget(target) {
			if (!target || !target.tagName) {
				return false;
			}

			const tagName = String(target.tagName).toUpperCase();
			if (target.isContentEditable) {
				return true;
			}
			if (tagName === 'TEXTAREA') {
				return true;
			}
			if (tagName !== 'INPUT') {
				return false;
			}

			const type = String(target.type || 'text').toLowerCase();
			return !['button', 'checkbox', 'color', 'file', 'hidden', 'radio', 'range', 'reset', 'submit'].includes(type);
		},
		syncMobileHeaderSaveVisibility() {
			if (typeof document === 'undefined') {
				this.mobileFormFieldFocused = false;
				return;
			}

			const root = this.$refs.modalContent;
			const active = document.activeElement;
			this.mobileFormFieldFocused = !!(
				this.isOpen
				&& root
				&& active
				&& root.contains(active)
				&& this.isKeyboardInputTarget(active)
			);
		},
		handleModalFocusIn(event) {
			this.clearMobileFocusTimer();
			this.mobileFormFieldFocused = this.isKeyboardInputTarget(event.target);
		},
		handleModalFocusOut() {
			this.clearMobileFocusTimer();
			this.mobileFocusTimer = window.setTimeout(() => {
				this.syncMobileHeaderSaveVisibility();
				this.mobileFocusTimer = null;
			}, 120);
		},
		consumeHistoryClose() {
			if (!this.closedByHistory) {
				return false;
			}
			this.closedByHistory = false;
			return true;
		},
		handleEscape() {
			if (this.confirmDialog || this.loading) {
				return;
			}
			if (this.typeMenuOpen) {
				this.closeTypeMenu(true);
				return;
			}
			if (this.focusedLookupField) {
				this.closeLookup();
				return;
			}
			this.requestCloseSidebar();
		},
		async handleSidebarOpenUpdate(isOpen) {
			if (isOpen) {
				this.isOpen = true;
				return;
			}

			if (this.confirmDialog || this.loading) {
				this.isOpen = true;
				return;
			}

			await this.requestCloseSidebar();
		},
		handleSidebarOpened() {
			if (!this.pendingInitialFocus) {
				return;
			}

			this.pendingInitialFocus = false;
			this.focusInitialField();
		},
		usesSidebarHistory() {
			return typeof window !== 'undefined'
				&& typeof window.matchMedia === 'function'
				&& window.matchMedia('(max-width: 1024px)').matches;
		},
		pushModalHistory() {
			if (!this.usesSidebarHistory() || this.modalHistoryToken || typeof window === 'undefined' || !window.history?.pushState) {
				return;
			}

			const token = `entry-modal-${Date.now()}-${Math.random().toString(36).slice(2)}`;
			const currentState = window.history.state && typeof window.history.state === 'object'
				? window.history.state
				: {};

			this.modalHistoryToken = token;
			window.history.pushState({
				...currentState,
				cobudgetModal: 'entry',
				cobudgetModalToken: token
			}, '', window.location.href);
		},
		releaseModalHistory({ skipBack = false } = {}) {
			if (!this.modalHistoryToken || typeof window === 'undefined' || !window.history) {
				return;
			}

			const token = this.modalHistoryToken;
			this.modalHistoryToken = null;

			if (skipBack) {
				return;
			}

			const state = window.history.state || {};
			const isCurrentModalState = state.cobudgetModal === 'entry' && state.cobudgetModalToken === token;
			if (!isCurrentModalState || typeof window.history.back !== 'function') {
				return;
			}

			this.ignoreNextPopState = true;
			window.history.back();
			if (this.ignorePopStateTimer) {
				window.clearTimeout(this.ignorePopStateTimer);
			}
			this.ignorePopStateTimer = window.setTimeout(() => {
				this.ignoreNextPopState = false;
				this.ignorePopStateTimer = null;
			}, 500);
		},
		handleModalPopState() {
			if (this.ignoreNextPopState) {
				this.ignoreNextPopState = false;
				if (this.ignorePopStateTimer) {
					window.clearTimeout(this.ignorePopStateTimer);
					this.ignorePopStateTimer = null;
				}
				return;
			}

			if (!this.modalHistoryToken) {
				return;
			}

			const shouldCloseModal = this.isOpen || !!this.confirmDialog;
			this.closedByHistory = true;
			this.modalHistoryToken = null;

			if (this.confirmDialog) {
				this.resolveConfirm(false);
			}

			if (shouldCloseModal) {
				this.closeSidebar({ skipHistory: true });
			}
		},
		currentUserId() {
			if (window.OC?.currentUser?.uid) {
				return window.OC.currentUser.uid;
			}

			if (typeof window.OC?.getCurrentUser === 'function') {
				const user = window.OC.getCurrentUser();
				if (typeof user === 'string') {
					return user;
				}
				return user?.uid || user?.id || '';
			}

			return '';
		},
		typeOptionId(index) {
			const option = this.entryTypeOptions[index];
			return `entry-type-option-${option ? option.value : index}`;
		},
		selectedTypeOptionIndex() {
			const selectedIndex = this.entryTypeOptions.findIndex(option => option.value === this.entry.type);
			return selectedIndex >= 0 ? selectedIndex : 0;
		},
		typeMenuIndex(index) {
			const optionCount = this.entryTypeOptions.length;
			if (optionCount === 0) {
				return -1;
			}
			return Math.max(0, Math.min(optionCount - 1, Number(index) || 0));
		},
		typeTriggerElement() {
			return this.$refs.amountTypeTrigger?.$el || this.$refs.amountTypeTrigger || null;
		},
		focusAmountInput() {
			this.$refs.amountInput?.focus({ preventScroll: true });
		},
		focusLookup(field) {
			this.openLookup(field);
			this.$nextTick(() => this.lookupTrigger(field)?.focus());
		},
		focusPaymentPartnerLookup() {
			this.evaluateAmount();
			this.focusLookup('paymentPartner');
		},
		advanceToCategoryLookup() {
			void this.maybeSuggestCategory();
			this.closeLookup();
			this.$nextTick(() => this.lookupTrigger('category')?.focus({ preventScroll: true }));
		},
		toggleTypeMenu() {
			if (this.typeMenuOpen) {
				this.closeTypeMenu(false);
				return;
			}

			this.openTypeMenuAt(this.selectedTypeOptionIndex());
		},
		openTypeMenuAt(index) {
			this.closeLookup();
			this.highlightedTypeIndex = this.typeMenuIndex(index);
			this.typeMenuOpen = true;
		},
		openTypeMenuAndMove(direction) {
			if (!this.typeMenuOpen) {
				this.openTypeMenuAt(this.selectedTypeOptionIndex());
				return;
			}

			const optionCount = this.entryTypeOptions.length;
			if (optionCount === 0) {
				return;
			}
			const currentIndex = this.highlightedTypeIndex >= 0
				? this.highlightedTypeIndex
				: this.selectedTypeOptionIndex();
			this.highlightedTypeIndex = (currentIndex + direction + optionCount) % optionCount;
		},
		confirmTypeMenuSelection() {
			if (!this.typeMenuOpen) {
				this.openTypeMenuAt(this.selectedTypeOptionIndex());
				return;
			}

			const option = this.entryTypeOptions[this.highlightedTypeIndex];
			if (option) {
				this.selectEntryType(option.value);
			}
		},
		selectEntryType(type) {
			this.setEntryType(type);
			this.closeTypeMenu(false);
			this.$nextTick(() => this.focusAmountInput());
		},
		closeTypeMenu(restoreFocus = false) {
			const wasOpen = this.typeMenuOpen;
			this.typeMenuOpen = false;
			this.highlightedTypeIndex = -1;
			if (restoreFocus && wasOpen) {
				this.$nextTick(() => this.typeTriggerElement()?.focus());
			}
		},
		setEntryType(type) {
			const nextType = type === 'income' ? 'income' : 'expense';
			if (this.entry.type === nextType) {
				return;
			}

			this.invalidateCategorySuggestion(true);
			this.categorySelectionSource = null;
			this.entry.type = nextType;
			this.entry.categoryId = null;
			this.entry.categoryName = '';
			this.entry.paymentPartnerName = '';
			this.closeLookup();

			if (nextType !== 'expense') {
				this.entry.isSubscription = false;
				this.entry.isFixedCost = false;
			}
		},
		normalizeProjectMember(member) {
			const id = String(member?.id || member?.userId || member?.uid || '').trim();
			return {
				id,
				displayName: member?.displayName || member?.displayname || id,
				shareBasisPoints: parseInt(member?.shareBasisPoints ?? member?.share_basis_points ?? 0, 10) || 0,
				sharePercent: Math.round(parseFloat(member?.sharePercent ?? 0) || 0),
				isFormer: member?.isFormer === true || member?.is_former === true,
				isActive: member?.isActive !== false && member?.is_active !== false
			};
		},
		async ensureProjectMembers(projectId) {
			if (!this.$enableSharedProjects || !projectId) {
				return;
			}

			const project = this.projects.find(p => Number(p.id) === Number(projectId));
			if (!project) {
				return;
			}

			if (Array.isArray(project.members) && project.members.length > 0) {
				return;
			}

			const memberCount = parseInt(project.member_count, 10) || 1;
			if (memberCount <= 1) {
				this.projects = this.projects.map(p => {
					if (Number(p.id) !== Number(projectId)) {
						return p;
					}
					return {
						...p,
						members: [{ id: this.currentUserId(), displayName: this.currentUserId() }]
					};
				});
				return;
			}

			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/projects/${projectId}`));
				const members = (response.data?.members || []).map(member => this.normalizeProjectMember(member));
				this.projects = this.projects.map(p => {
					if (Number(p.id) !== Number(projectId)) {
						return p;
					}
					return {
						...p,
						members,
						member_count: Math.max(memberCount, members.length)
					};
				});
			} catch (e) {
				showRequestError(e, this.$texts.entry.areaMembersLoadError(), 'Failed to fetch project members')
			}
		},
		syncEntryUserWithProject(preferredUserId = null) {
			const currentUserId = this.currentUserId();
			if (!this.$enableSharedProjects) {
				if (this.isInitializingEntry && this.isEditing && this.entry.userId) {
					return;
				}
				this.entry.userId = currentUserId;
				this.entry.splitMode = 'project_shares';
				this.entry.splitUserId = null;
				return;
			}

			if (!this.entry.projectId) {
				this.entry.userId = currentUserId;
				this.entry.splitMode = 'project_shares';
				this.entry.splitUserId = null;
				return;
			}

			const members = this.projectPayerOptions;
			if (!members.length) {
				this.entry.userId = currentUserId;
				this.entry.splitMode = 'project_shares';
				this.entry.splitUserId = null;
				return;
			}
			const preferred = preferredUserId || this.entry.userId || currentUserId;
			if (members.some(member => member.id === preferred)) {
				this.entry.userId = preferred;
				this.syncSplitUserWithProjectMembers(this.projectSplitOptions);
				return;
			}

			if (members.some(member => member.id === currentUserId)) {
				this.entry.userId = currentUserId;
				this.syncSplitUserWithProjectMembers(this.projectSplitOptions);
				return;
			}

			this.entry.userId = members[0].id;
			this.syncSplitUserWithProjectMembers(this.projectSplitOptions);
		},
		syncSplitUserWithProjectMembers(members) {
			if (this.entry.splitMode !== 'single_user') {
				this.entry.splitUserId = null;
				return;
			}

			const fallbackUserId = this.entry.userId || this.currentUserId();
			const selectedUserId = this.entry.splitUserId || fallbackUserId;
			if (members.some(member => member.id === selectedUserId)) {
				this.entry.splitUserId = selectedUserId;
				return;
			}

			this.entry.splitUserId = fallbackUserId;
		},
		entrySplitModeForSave() {
			return this.entry.projectId ? (this.entry.splitMode || 'project_shares') : 'project_shares';
		},
		entrySplitUserIdForSave() {
			if (this.entrySplitModeForSave() !== 'single_user') {
				return null;
			}

			return this.entry.splitUserId || this.entry.userId || this.currentUserId();
		},
		usageCount(item) {
			return parseInt(item?.recent_usage_count, 10) || 0;
		},
		sortByName(items) {
			return [...items].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }));
		},
		sortByUsageThenName(items) {
			return [...items].sort((a, b) => {
				const usageDiff = this.usageCount(b) - this.usageCount(a);
				if (usageDiff !== 0) {
					return usageDiff;
				}

				return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
			});
		},
		buildSuggestionSections(items, query, allLabel, additionalSearchFields = [], hierarchical = false) {
			const normalizedQuery = String(query || '').trim().toLowerCase();
			const searchFields = ['name', ...additionalSearchFields];
			const candidates = normalizedQuery
				? items.filter(item => searchFields.some(field => String(item?.[field] ?? '').toLowerCase().includes(normalizedQuery)))
				: items;

			const frequent = this.sortByUsageThenName(candidates)
				.filter(item => this.usageCount(item) > 0)
				.slice(0, 5);
			const frequentIds = new Set(frequent.map(item => item.id));
			const remainingItems = candidates.filter(item => !frequentIds.has(item.id));
			const remaining = hierarchical
				? sortCategoriesHierarchically(remainingItems)
				: this.sortByName(remainingItems);

			if (normalizedQuery || frequent.length === 0) {
				return [{ label: '', items: [...frequent, ...remaining] }];
			}

			return [
				{ label: this.$texts.entry.frequentlyUsed(), items: frequent },
				{ label: allLabel, items: remaining }
			];
		},
		lookupValue(field) {
			return field === 'category' ? this.entry.categoryName : this.entry.paymentPartnerName;
		},
		categoryParentId,
		isCategorySelected(category) {
			const categoryId = normalizedPositiveId(category?.id);
			if (categoryId !== null && this.selectedCategoryId !== null) {
				return categoryId === this.selectedCategoryId;
			}

			return this.selectedCategoryId === null
				&& String(category?.name || '').trim().localeCompare(
					String(this.entry.categoryName || '').trim(),
					undefined,
					{ sensitivity: 'base' }
				) === 0;
		},
		lookupItems(field) {
			return field === 'category' ? this.filteredCategories : this.filteredPaymentPartners;
		},
		lookupSuggestions(field) {
			return field === 'category' ? this.categorySuggestions : this.paymentPartnerSuggestions;
		},
		exactLookupMatch(field, value = this.lookupDraft[field]) {
			const normalizedValue = String(value || '').trim().toLowerCase();
			if (!normalizedValue) {
				return null;
			}

			return this.lookupItems(field).find(item => {
				const nameMatches = String(item.name || '').trim().toLowerCase() === normalizedValue;
				const numberMatches = field === 'category'
					? String(item.code ?? '').trim().toLowerCase() === normalizedValue
					: String(item.number ?? '').trim().toLowerCase() === normalizedValue;
				return nameMatches || numberMatches;
			}) || null;
		},
		lookupQueryForField(field) {
			return this.lookupInputMode === field
				? String(this.lookupDraft[field] || '').trim().toLowerCase()
				: '';
		},
		lookupIndex(field, item) {
			const suggestions = field === 'category' ? this.categorySuggestions : this.paymentPartnerSuggestions;
			return suggestions.findIndex(suggestion => suggestion.id === item.id);
		},
		lookupField(field) {
			return field === 'category' ? this.$refs.categoryLookupField : this.$refs.paymentPartnerLookupField;
		},
		lookupTrigger(field) {
			return field === 'category' ? this.$refs.categoryLookupTrigger : this.$refs.paymentPartnerLookupTrigger;
		},
		lookupCreateInput(field) {
			return field === 'category' ? this.$refs.categoryCreateInput : this.$refs.paymentPartnerCreateInput;
		},
		toggleLookup(field) {
			if (this.focusedLookupField === field) {
				this.closeLookup();
				return;
			}

			this.openLookup(field);
		},
		openLookup(field) {
			this.closeTypeMenu(false);
			this.focusedLookupField = field;
			this.lookupInputMode = null;
			this.lookupDraft = { category: '', paymentPartner: '' };
			this.resetLookupHighlight(field);
		},
		openLookupAndMove(field, direction) {
			if (this.focusedLookupField !== field) {
				this.openLookup(field);
				if (direction < 0 && this.lookupSuggestions(field).length > 0) {
					const lastIndex = this.lookupSuggestions(field).length - 1;
					if (field === 'category') {
						this.highlightedCategoryIndex = lastIndex;
					} else {
						this.highlightedPaymentPartnerIndex = lastIndex;
					}
				}
				return;
			}

			this.moveLookupHighlight(field, direction);
		},
		handleLookupTriggerEnter(field) {
			if (this.focusedLookupField !== field) {
				this.openLookup(field);
				return;
			}

			if (!this.chooseHighlightedLookup(field)) {
				this.closeLookup();
			}
		},
		startLookupInput(field) {
			this.focusedLookupField = field;
			this.lookupInputMode = field;
			this.lookupDraft = { ...this.lookupDraft, [field]: '' };
			this.resetLookupHighlight(field);
			this.$nextTick(() => this.lookupCreateInput(field)?.focus());
		},
		cancelLookupInput(field) {
			this.lookupInputMode = null;
			this.lookupDraft = { ...this.lookupDraft, [field]: '' };
			this.resetLookupHighlight(field);
			this.$nextTick(() => this.lookupTrigger(field)?.focus());
		},
		applyLookupDraft(field) {
			const name = String(this.lookupDraft[field] || '').trim();
			if (!name) {
				return;
			}

			const exactMatch = this.exactLookupMatch(field, name);
			if (exactMatch) {
				if (field === 'category') {
					this.selectCategorySuggestion(exactMatch);
				} else {
					this.selectPaymentPartnerSuggestion(exactMatch);
				}
				return;
			}

			if (field === 'category') {
				this.invalidateCategorySuggestion();
				this.entry.categoryId = null;
				this.entry.categoryName = name;
				this.categorySelectionSource = 'manual';
				this.closeLookup();
				this.blurLookupTrigger(field);
				return;
			}

			this.invalidateCategorySuggestion(true);
			this.entry.paymentPartnerName = name;
			this.closeLookup();
			this.advanceToCategoryLookup();
		},
		handleLookupDraftEnter(field) {
			const suggestions = this.lookupSuggestions(field);
			const highlightedIndex = field === 'category' ? this.highlightedCategoryIndex : this.highlightedPaymentPartnerIndex;
			const item = highlightedIndex >= 0 ? suggestions[highlightedIndex] : null;
			if (item) {
				if (field === 'category') {
					this.selectCategorySuggestion(item);
				} else {
					this.selectPaymentPartnerSuggestion(item);
				}
				return;
			}

			this.applyLookupDraft(field);
		},
		closeLookup() {
			this.focusedLookupField = null;
			this.lookupInputMode = null;
			this.lookupDraft = { category: '', paymentPartner: '' };
			this.highlightedCategoryIndex = -1;
			this.highlightedPaymentPartnerIndex = -1;
		},
		handleLookupOutside(event) {
			if (!this.focusedLookupField) {
				return;
			}

			const field = this.lookupField(this.focusedLookupField);
			if (field && !field.contains(event.target)) {
				this.closeLookup();
			}
		},
		resetScopedLookups() {
			this.entry.categoryId = null;
			this.entry.categoryName = '';
			this.entry.paymentPartnerName = '';
			this.closeLookup();
		},
		selectedGlobalLookupNames() {
			return {
				category: this.selectedGlobalLookupName('category'),
				paymentPartner: this.selectedGlobalLookupName('paymentPartner')
			};
		},
		selectedGlobalLookupName(field) {
			if (field === 'category' && this.selectedCategory && this.isGlobalLookupItem(this.selectedCategory)) {
				return String(this.selectedCategory.name || '').trim();
			}

			const value = String(this.lookupValue(field) || '').trim();
			if (!value) {
				return '';
			}

			const item = this.lookupItems(field).find(candidate => {
				return this.isGlobalLookupItem(candidate)
					&& String(candidate.name || '').trim().localeCompare(value, undefined, { sensitivity: 'base' }) === 0;
			});

			return item ? String(item.name || '').trim() : '';
		},
		isGlobalLookupItem(item) {
			return item?.is_global === true || item?.is_global === 1 || item?.is_global === '1';
		},
		restoreGlobalLookupNames(globalLookups) {
			const categoryName = String(globalLookups?.category || '').trim();
			const paymentPartnerName = String(globalLookups?.paymentPartner || '').trim();
			const category = this.globalLookupItem('category', categoryName);
			this.entry.categoryId = category ? normalizedPositiveId(category.id) : null;
			this.entry.categoryName = category ? String(category.name || '').trim() : '';
			this.entry.paymentPartnerName = this.globalLookupExists('paymentPartner', paymentPartnerName) ? paymentPartnerName : '';
		},
		globalLookupItem(field, name) {
			if (!name) {
				return null;
			}

			return this.lookupItems(field).find(item => {
				return this.isGlobalLookupItem(item)
					&& String(item.name || '').trim().localeCompare(name, undefined, { sensitivity: 'base' }) === 0;
			}) || null;
		},
		globalLookupExists(field, name) {
			return this.globalLookupItem(field, name) !== null;
		},
		clearLookupValue(field) {
			if (field === 'category') {
				this.invalidateCategorySuggestion();
				this.entry.categoryId = null;
				this.entry.categoryName = '';
				this.categorySelectionSource = null;
			} else {
				this.invalidateCategorySuggestion(true);
				this.entry.paymentPartnerName = '';
			}
			this.openLookup(field);
			this.$nextTick(() => this.lookupTrigger(field)?.focus());
		},
		resetLookupHighlight(field) {
			const suggestions = this.lookupSuggestions(field);
			const selectedItem = field === 'category' ? this.selectedCategory : this.selectedPaymentPartner();
			const selectedIndex = selectedItem
				? suggestions.findIndex(item => Number(item.id) === Number(selectedItem.id))
				: -1;
			const nextIndex = selectedIndex >= 0 ? selectedIndex : (suggestions.length > 0 ? 0 : -1);

			if (field === 'category') {
				this.highlightedCategoryIndex = nextIndex;
			} else {
				this.highlightedPaymentPartnerIndex = nextIndex;
			}
		},
		moveLookupHighlight(field, direction) {
			const suggestions = this.lookupSuggestions(field);
			if (!suggestions.length) return;
			this.focusedLookupField = field;
			const current = field === 'category' ? this.highlightedCategoryIndex : this.highlightedPaymentPartnerIndex;
			const next = (current + direction + suggestions.length) % suggestions.length;
			if (field === 'category') {
				this.highlightedCategoryIndex = next;
			} else {
				this.highlightedPaymentPartnerIndex = next;
			}
		},
		chooseHighlightedLookup(field) {
			const suggestions = this.lookupSuggestions(field);
			const highlightedIndex = field === 'category' ? this.highlightedCategoryIndex : this.highlightedPaymentPartnerIndex;
			const item = suggestions[highlightedIndex];
			if (!item) {
				return false;
			}

			if (field === 'category') {
				this.selectCategorySuggestion(item);
			} else {
				this.selectPaymentPartnerSuggestion(item);
			}
			return true;
		},
		selectCategorySuggestion(category) {
			this.invalidateCategorySuggestion();
			const categoryId = normalizedPositiveId(category?.id);
			if (categoryId === null) {
				return;
			}
			const canonicalCategory = this.categories.find(item => Number(item.id) === categoryId) || category;
			this.entry = {
				...this.entry,
				categoryId,
				categoryName: String(canonicalCategory.name || '').trim()
			};
			this.categorySelectionSource = 'manual';
			this.closeLookup();
			this.blurLookupTrigger('category');
		},
		selectPaymentPartnerSuggestion(paymentPartner) {
			this.invalidateCategorySuggestion(true);
			this.entry.paymentPartnerName = paymentPartner.name;
			this.closeLookup();
			this.advanceToCategoryLookup();
		},
		invalidateCategorySuggestion(clearSuggested = false) {
			this.categorySuggestionRequestId += 1;
			if (clearSuggested && this.categorySelectionSource === 'suggested') {
				this.entry.categoryId = null;
				this.entry.categoryName = '';
				this.categorySelectionSource = null;
			}
		},
		selectedPaymentPartner() {
			const value = String(this.entry.paymentPartnerName || '').trim();
			if (!value) {
				return null;
			}

			return this.filteredPaymentPartners.find(item => {
				return String(item.name || '').trim().localeCompare(value, undefined, { sensitivity: 'base' }) === 0;
			}) || null;
		},
		async maybeSuggestCategory() {
			if (this.categorySelectionSource === 'manual' || this.categorySelectionSource === 'preset') {
				return;
			}

			const paymentPartner = this.selectedPaymentPartner();
			this.invalidateCategorySuggestion(true);
			if (!paymentPartner?.id) {
				return;
			}

			const requestId = this.categorySuggestionRequestId;
			try {
				const params = {
					paymentPartnerId: paymentPartner.id,
					type: this.entry.type
				};
				if (this.entry.projectId) {
					params.projectId = this.entry.projectId;
				}

				const response = await axios.get(
					generateUrl('/apps/cobudget/api/entries/category-suggestion'),
					{ params }
				);
				if (requestId !== this.categorySuggestionRequestId) {
					return;
				}

				const suggestion = response.data?.suggestion;
				const category = this.filteredCategories.find(item => Number(item.id) === Number(suggestion?.categoryId));
				if (!category || this.categorySelectionSource === 'manual' || this.categorySelectionSource === 'preset') {
					return;
				}

				this.entry = {
					...this.entry,
					categoryId: normalizedPositiveId(category.id),
					categoryName: category.name
				};
				this.categorySelectionSource = 'suggested';
			} catch {
				// Recommendations are optional and must never block payment entry.
			}
		},
		blurLookupTrigger(field) {
			this.$nextTick(() => {
				this.lookupTrigger(field)?.blur();
				this.syncMobileHeaderSaveVisibility();
			});
		},
		async openSidebar(entryToEdit = null, defaultProjectId = null, isFutureContext = false, entryToDuplicate = null, defaultType = 'expense', projectSelectionLocked = false, reuseLoadedData = false) {
			const canFocusImmediately = this.isOpen && !this.pendingInitialFocus;
			this.invalidateCategorySuggestion(true);
			this.categorySelectionSource = null;
			this.closeTypeMenu(false);
			this.isOpen = true;
			this.pendingInitialFocus = !canFocusImmediately;
			this.closedByHistory = false;
			this.pushModalHistory();
			this.isFutureContext = isFutureContext;
			this.isDuplicateMode = !!entryToDuplicate && !entryToEdit;
			this.projectSelectionLocked = !!projectSelectionLocked;
			this.originalProjectId = entryToEdit?.project_id || null;
			this.resetAttachments();
			const resolvedDefaultType = defaultType === 'income' ? 'income' : 'expense';
			this.newEntryDefaultType = resolvedDefaultType;
			this.entryFormBaseline = '';

			this.isInitializingEntry = true;
			
			if (entryToEdit) {
				this.internalEditingEntry = entryToEdit;
				this.entry = {
					id: entryToEdit.id,
					type: entryToEdit.type,
					amountDisplay: this.$formatInputAmount(entryToEdit.amount),
					description: entryToEdit.description,
					categoryId: this.sourceCategoryId(entryToEdit),
					categoryName: entryToEdit.category_name || '',
					paymentPartnerName: entryToEdit.paymentPartner || (() => {
						if (!entryToEdit.payment_partner_id) return '';
						const pay = this.paymentPartners.find(p => p.id === entryToEdit.payment_partner_id);
						return pay ? pay.name : '';
					})(),
					projectId: entryToEdit.project_id || null,
					userId: entryToEdit.user_id || this.currentUserId(),
					splitMode: entryToEdit.split_mode || 'project_shares',
					splitUserId: entryToEdit.split_user_id || null,
					date: entryToEdit.date ? new Date(entryToEdit.date * 1000) : new Date(),
					recurrenceInterval: entryToEdit.recurrence_interval || 'none',
					recurrenceMultiplier: entryToEdit.recurrence_multiplier || 1,
					recurrenceEndDate: entryToEdit.recurrence_end_date ? new Date(entryToEdit.recurrence_end_date * 1000) : null,
					isSubscription: !!entryToEdit.is_subscription,
					isFixedCost: !!entryToEdit.is_fixed_cost,
					isChildRelated: !!entryToEdit.is_child_related,
					isImportant: !!entryToEdit.is_important,
					needsReview: !!entryToEdit.needs_review,
					isTaxRelevant: !!entryToEdit.is_tax_relevant,
					hasReminder: !!entryToEdit.reminder_date,
					reminderDate: entryToEdit.reminder_date ? new Date(entryToEdit.reminder_date * 1000) : null,
					reminderText: entryToEdit.reminder_text || ''
				};
			} else if (entryToDuplicate) {
				this.internalEditingEntry = null;
				this.entry = {
					type: entryToDuplicate.type || 'expense',
					amountDisplay: entryToDuplicate.amount ? this.$formatInputAmount(entryToDuplicate.amount) : '',
					description: entryToDuplicate.description || '',
					categoryId: this.sourceCategoryId(entryToDuplicate),
					categoryName: entryToDuplicate.category_name || '',
					paymentPartnerName: entryToDuplicate.paymentPartner || (() => {
						if (!entryToDuplicate.payment_partner_id) return '';
						const pay = this.paymentPartners.find(p => p.id === entryToDuplicate.payment_partner_id);
						return pay ? pay.name : '';
					})(),
					projectId: entryToDuplicate.project_id || defaultProjectId || this.projectId,
					userId: entryToDuplicate.user_id || this.currentUserId(),
					splitMode: entryToDuplicate.split_mode || 'project_shares',
					splitUserId: entryToDuplicate.split_user_id || null,
					date: new Date(),
					recurrenceInterval: 'none',
					recurrenceMultiplier: 1,
					recurrenceEndDate: null,
					isSubscription: !!entryToDuplicate.is_subscription,
					isFixedCost: !!entryToDuplicate.is_fixed_cost,
					isChildRelated: !!entryToDuplicate.is_child_related,
					isImportant: !!entryToDuplicate.is_important,
					needsReview: !!entryToDuplicate.needs_review,
					isTaxRelevant: !!entryToDuplicate.is_tax_relevant,
					hasReminder: !!entryToDuplicate.reminder_date,
					reminderDate: entryToDuplicate.reminder_date ? new Date() : null, // Assuming user will reset it if needed
					reminderText: entryToDuplicate.reminder_text || ''
				};
			} else {
				this.internalEditingEntry = null;
				let defaultDate = new Date();
				
				let defaultIsSubscription = false;
				let defaultIsFixedCost = false;
				let defaultIsChildRelated = false;
				let defaultIsImportant = false;
				let defaultNeedsReview = false;
				let defaultIsTaxRelevant = false;
				if (this.$route && this.$route.query.filter === 'subscription') {
					defaultIsSubscription = true;
				} else if (this.$route && this.$route.query.filter === 'fixedCost') {
					defaultIsFixedCost = true;
				} else if (this.$route && this.$route.query.filter === 'childRelated') {
					defaultIsChildRelated = true;
				} else if (this.$route && this.$route.query.filter === 'important') {
					defaultIsImportant = true;
				} else if (this.$route && this.$route.query.filter === 'review') {
					defaultNeedsReview = true;
				} else if (this.$route && this.$route.query.filter === 'taxRelevant') {
					defaultIsTaxRelevant = true;
				}

				this.entry = {
					type: resolvedDefaultType,
					amountDisplay: '',
					description: '',
					categoryId: null,
					categoryName: '',
					paymentPartnerName: '',
					projectId: defaultProjectId || this.projectId,
					userId: this.currentUserId(),
					splitMode: 'project_shares',
					splitUserId: null,
					date: defaultDate,
					recurrenceInterval: 'none',
					recurrenceMultiplier: 1,
					recurrenceEndDate: null,
					isSubscription: defaultIsSubscription,
					isFixedCost: defaultIsFixedCost,
					isChildRelated: defaultIsChildRelated,
					isImportant: defaultIsImportant,
					needsReview: defaultNeedsReview,
					isTaxRelevant: defaultIsTaxRelevant,
					hasReminder: false,
					reminderDate: null,
					reminderText: ''
				};
			}
			this.emitSidebarMode();

			// Reset the visible form immediately. This keeps consecutive desktop entries
			// ready while fresh lookup data is only loaded for a normal reopen.
			if (canFocusImmediately) {
				this.focusInitialField();
			}
			const sourceEntry = entryToEdit || entryToDuplicate || null;
			if (reuseLoadedData) {
				this.syncLookupNamesFromIds(sourceEntry);
				this.syncEntryUserWithProject(this.entry.userId);
				this.isInitializingEntry = false;
				this.showPlanningOptions = this.shouldExpandPlanningOptions();
				this.markEntryClean();
				return;
			}

			// Projects are needed before the project-scoped category/contact list can be resolved.
			await this.fetchProjects();
			await this.fetchDataLists(this.entry.projectId);
			this.syncLookupNamesFromIds(sourceEntry);
			this.categorySelectionSource = null;
			if ((entryToEdit || entryToDuplicate) && this.entry.categoryName) {
				this.categorySelectionSource = 'preset';
			}
			await this.ensureProjectMembers(this.entry.projectId);
			this.syncEntryUserWithProject(this.entry.userId);
			if (entryToEdit) {
				await this.fetchAttachments(entryToEdit.id);
			}
			this.isInitializingEntry = false;
			this.showPlanningOptions = this.shouldExpandPlanningOptions();
			this.markEntryClean();
		},
		focusInitialField() {
			if (this.isEditing) {
				return;
			}
			if (this.isMobileViewport()) {
				return;
			}

			this.$nextTick(() => {
				const target = this.$refs.amountInput;
				if (!target) {
					return;
				}
				target.focus({ preventScroll: true });
				if (typeof target.select === 'function') {
					target.select();
				}
			});
		},
		isMobileViewport() {
			return typeof window !== 'undefined'
				&& typeof window.matchMedia === 'function'
				&& window.matchMedia('(max-width: 768px)').matches;
		},
		closeSidebar({ preserveHistory = false, skipHistory = false, userInitiated = true } = {}) {
			const wasOpen = this.isOpen;
			if (!preserveHistory && !skipHistory) {
				this.releaseModalHistory();
			}
			this.isOpen = false;
			this.pendingInitialFocus = false;
			this.internalEditingEntry = null;
			this.isDuplicateMode = false;
			this.showPlanningOptions = false;
			this.resetAttachments();
			this.closeTypeMenu(false);
			this.closeLookup();
			this.clearMobileFocusTimer();
			this.mobileFormFieldFocused = false;
			this.entryFormBaseline = '';
			this.emitSidebarMode();
			if (wasOpen) {
				this.$emit('closed', { userInitiated });
			}
		},
		resetAttachments() {
			this.attachments = [];
			this.pendingAttachments = [];
			this.attachmentsLoading = false;
			if (this.$refs.attachmentInput) {
				this.$refs.attachmentInput.value = '';
			}
		},
		async fetchAttachments(entryId) {
			if (!entryId || !this.$enableReceipts) {
				this.attachments = [];
				return;
			}

			this.attachmentsLoading = true;
			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/entries/${entryId}/attachments`));
				this.attachments = response.data?.attachments || [];
			} catch (e) {
				showRequestError(e, this.$texts.entry.receiptsLoadError(), 'Failed to fetch entry attachments');
				this.attachments = [];
			} finally {
				this.attachmentsLoading = false;
			}
		},
		onAttachmentFilesSelected(event) {
			const files = Array.from(event.target.files || []);
			if (files.length === 0) {
				return;
			}
			this.pendingAttachments = this.pendingAttachments.concat(files);
			this.showPlanningOptions = true;
			event.target.value = '';
		},
		removePendingAttachment(index) {
			this.pendingAttachments.splice(index, 1);
		},
		async uploadPendingAttachments(entryId) {
			if (!entryId || !this.$enableReceipts || this.pendingAttachments.length === 0) {
				return;
			}

			const uploaded = [];
			for (const file of this.pendingAttachments) {
				const formData = new FormData();
				formData.append('file', file, file.name);
				const response = await axios.post(generateUrl(`/apps/cobudget/api/entries/${entryId}/attachments`), formData, {
					headers: { Accept: 'application/json' }
				});
				if (response.data?.attachment) {
					uploaded.push(response.data.attachment);
				}
			}

			this.attachments = uploaded.concat(this.attachments);
			this.pendingAttachments = [];
		},
		attachmentDownloadUrl(attachment) {
			if (!this.entry.id || !attachment?.id) {
				return '#';
			}
			const url = generateUrl(`/apps/cobudget/api/entries/${this.entry.id}/attachments/${attachment.id}/download`);
			const workspaceId = readWorkspaceId();
			return workspaceId ? `${url}?workspaceId=${encodeURIComponent(workspaceId)}` : url;
		},
		formatFileSize(size) {
			const bytes = Number(size) || 0;
			if (bytes < 1024) {
				return `${bytes} B`;
			}
			if (bytes < 1024 * 1024) {
				return `${(bytes / 1024).toFixed(1)} KB`;
			}
			return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
		},
		canDeleteAttachment(attachment) {
			const ownerUserId = String(attachment?.owner_user_id || '').trim();
			return ownerUserId !== '' && ownerUserId === this.currentUserId();
		},
		async deleteAttachment(attachment) {
			if (!this.entry.id || !attachment?.id) {
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.entry.receiptRemoveTitle(),
				message: this.$texts.entry.receiptRemoveMessage(),
				confirmLabel: this.$texts.entry.removeReceipt(),
				confirmVariant: 'danger'
			});
			if (!confirmed) {
				this.consumeHistoryClose();
				return;
			}

			this.loading = true;
			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/entries/${this.entry.id}/attachments/${attachment.id}`));
				this.attachments = this.attachments.filter(item => Number(item.id) !== Number(attachment.id));
				showToast(this.$texts.entry.receiptRemoved());
			} catch (e) {
				showRequestError(e, this.$texts.entry.receiptRemoveError(), 'Failed to delete entry attachment');
			} finally {
				this.loading = false;
			}
		},
		async fetchProjects() {
			if (!this.$enableProjects) {
				this.projects = []
				return
			}
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/projects'))
				this.projects = response.data || []
			} catch (e) {
				showRequestError(e, this.$texts.entry.areasLoadError(), 'Failed to fetch projects')
				this.projects = []
			}
		},
		async fetchDataLists(projectId = this.entry.projectId) {
			try {
				const params = this.$enableProjects && projectId ? { projectId } : {};
				const catRes = await axios.get(generateUrl('/apps/cobudget/api/categories'), { params })
				this.categories = sortCategoriesHierarchically(catRes.data || [])
				const payRes = await axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params })
				this.paymentPartners = (payRes.data || []).sort((a, b) => a.name.localeCompare(b.name))
			} catch (e) {
				this.categories = [];
				this.paymentPartners = [];
				showRequestError(e, this.$texts.entry.lookupsLoadError(), 'Failed to fetch categories/paymentPartners')
			}
		},
		syncLookupNamesFromIds(source) {
			if (!source) {
				return;
			}

			const sourceCategoryName = String(source.category_name || source.categoryName || '').trim();
			const sourceCategoryId = this.sourceCategoryId(source);
			const categoryById = sourceCategoryId !== null
				? this.categories.find(category => Number(category.id) === sourceCategoryId) || null
				: null;
			const categoryByName = sourceCategoryName
				? this.categories.find(category =>
					String(category.name || '').trim().localeCompare(sourceCategoryName, undefined, { sensitivity: 'base' }) === 0
					&& (!source.type || !category.type || category.type === source.type)
				) || null
				: null;
			const category = categoryById
				&& (!sourceCategoryName
					|| String(categoryById.name || '').trim().localeCompare(sourceCategoryName, undefined, { sensitivity: 'base' }) === 0)
				? categoryById
				: categoryByName || categoryById;
			if (category) {
				this.entry.categoryId = normalizedPositiveId(category.id);
				this.entry.categoryName = category.name;
			} else if (sourceCategoryId !== null) {
				this.entry.categoryId = sourceCategoryId;
			} else if (!this.entry.categoryName) {
				this.entry.categoryId = null;
			}

			if (!this.entry.paymentPartnerName && source.payment_partner_id) {
				const paymentPartner = this.paymentPartners.find(p => Number(p.id) === Number(source.payment_partner_id));
				this.entry.paymentPartnerName = paymentPartner ? paymentPartner.name : '';
			}
		},
		sourceCategoryId(source) {
			return normalizedPositiveId(source?.category_id ?? source?.categoryId ?? source?.category?.id);
		},
		getProjectName(id) {
			const p = this.projects.find(p => p.id === id)
			return p ? p.name : ''
		},
		shouldExpandPlanningOptions() {
			return (
				this.entry.recurrenceInterval !== 'none'
				|| !!this.entry.recurrenceEndDate
				|| !!this.entry.hasReminder
				|| !!this.entry.reminderText
				|| this.hasAttachments
			);
		},
		entryUserIdForSave() {
			const currentUserId = this.currentUserId();
			if (!this.entry.projectId) {
				return currentUserId;
			}
			if (!this.$enableSharedProjects) {
				return this.isEditing ? (this.entry.userId || currentUserId) : currentUserId;
			}
			return this.entry.userId || currentUserId;
		},
		toDateInputValue(value) {
			if (!value) return '';
			const d = new Date(value);
			if (Number.isNaN(d.getTime())) return '';
			const year = String(d.getFullYear()).padStart(4, '0');
			const month = String(d.getMonth() + 1).padStart(2, '0');
			const day = String(d.getDate()).padStart(2, '0');
			return `${year}-${month}-${day}`;
		},
		fromDateInputValue(value, hours = 12, minutes = 0, seconds = 0) {
			if (!value) return null;
			const [year, month, day] = String(value).split('-').map(Number);
			if (!year || !month || !day) return null;
			const d = new Date(year, month - 1, day);
			d.setHours(hours, minutes, seconds, 0);
			return Number.isNaN(d.getTime()) ? null : d;
		},
		async saveEntry() {
			if (this.isInitializingEntry) {
				return;
			}
			if (this.isEditing && !this.hasUnsavedChanges) {
				return;
			}
			this.evaluateAmount();
			if (!this.isValid) {
				showToast(this.$texts.entry.validAmountRequired(), 'error');
				return;
			}
			
			// Warn if saving a future date outside of the planned payments flow.
			if (!this.isFutureContext && !this.isEditing) {
				const now = new Date();
				now.setHours(0,0,0,0);
				const entryDate = new Date(this.entry.date);
				entryDate.setHours(0,0,0,0);
				
				if (entryDate > now) {
					const confirmed = await this.openConfirm({
						title: this.$texts.entry.futurePaymentTitle(),
						message: this.$texts.entry.futurePaymentMessage(),
						confirmLabel: this.$texts.entry.planPayment()
					});
					if (!confirmed) {
						return;
					}
				}
			}
			
			if (this.isFutureContext && !this.isEditing) {
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				if (this.entry.date <= today) {
					showToast(this.$texts.entry.futureDateRequired(), 'error');
					return;
				}
			}
			
			this.loading = true;
			let entryPersisted = false;
			const wasEditing = this.isEditing;
			const closeAfterSave = this.usesSidebarHistory();
			const prepareNextEntry = !closeAfterSave && !wasEditing;
			const retainedProjectId = this.entry.projectId || null;
			const retainedFutureContext = this.isFutureContext;
			const retainedProjectLock = this.projectSelectionLocked;
			const retainedDefaultType = this.newEntryDefaultType;
			try {
				await this.ensureProjectMembers(this.entry.projectId);
				this.syncEntryUserWithProject(this.entry.userId);

				let categoryId = normalizedPositiveId(this.entry.categoryId);
				const rawCatName = this.entry.categoryName || '';
				if (typeof rawCatName === 'string' && rawCatName.trim() !== '') {
					const cName = rawCatName.trim();
					let cat = categoryId !== null
						? (this.categories || []).find(c => Number(c?.id) === categoryId)
						: (this.categories || []).find(c => c && c.name && String(c.name).toLowerCase() === String(cName).toLowerCase());
					if (!cat) {
						if (categoryId === null) {
							const res = await axios.post(generateUrl('/apps/cobudget/api/categories'), {
								name: cName,
								type: this.entry.type,
								projectId: this.entry.projectId || null
							});
							categoryId = res.data.id;
							this.categories = sortCategoriesHierarchically([
								...this.categories,
								{
									...res.data,
									id: categoryId,
									name: cName,
									type: this.entry.type,
									project_id: this.entry.projectId || null
								}
							]);
						}
					} else {
						categoryId = cat.id;
					}
				} else {
					categoryId = null;
				}

				let paymentPartnerId = null;
				const rawPaymentPartnerName = this.entry.paymentPartnerName || '';
				if (typeof rawPaymentPartnerName === 'string' && rawPaymentPartnerName.trim() !== '') {
					const pName = rawPaymentPartnerName.trim();
					let pay = (this.paymentPartners || []).find(p => p && p.name && String(p.name).toLowerCase() === String(pName).toLowerCase());
					if (!pay) {
							const res = await axios.post(generateUrl('/apps/cobudget/api/payment-partners'), {
								name: pName,
								type: this.entry.type,
								projectId: this.entry.projectId || null
							});
							paymentPartnerId = res.data.id;
							this.paymentPartners = [
								...this.paymentPartners,
								{
									...res.data,
									id: paymentPartnerId,
									name: pName,
									type: this.entry.type,
									project_id: this.entry.projectId || null
								}
							].sort((a, b) => a.name.localeCompare(b.name));
					} else {
						paymentPartnerId = pay.id;
					}
				}

				const isExpense = this.entry.type === 'expense';
				const payload = {
					type: this.entry.type,
					amount: this.$parseAmount(this.entry.amountDisplay),
					description: this.entry.description,
					paymentPartnerId: paymentPartnerId,
					categoryId: categoryId,
					projectId: this.entry.projectId,
					userId: this.entryUserIdForSave(),
					splitMode: this.entrySplitModeForSave(),
					splitUserId: this.entrySplitUserIdForSave(),
					currency: 'EUR',
					date: Math.floor(this.entry.date.getTime() / 1000),
					recurrenceInterval: this.entry.recurrenceInterval !== 'none' ? this.entry.recurrenceInterval : null,
					recurrenceMultiplier: this.entry.recurrenceInterval !== 'none' ? parseInt(this.entry.recurrenceMultiplier) : null,
					recurrenceEndDate: this.entry.recurrenceInterval !== 'none' && this.entry.recurrenceEndDate ? Math.floor(this.entry.recurrenceEndDate.getTime() / 1000) : null,
					recurrenceNextDate: this.entry.recurrenceInterval !== 'none' ? (() => {
						// Calculate the next execution date based on the first payment date.
						const d = new Date(this.entry.date);
						const m = parseInt(this.entry.recurrenceMultiplier) || 1;
						if (this.entry.recurrenceInterval === 'day') {
							d.setDate(d.getDate() + m);
						} else if (this.entry.recurrenceInterval === 'week') {
							d.setDate(d.getDate() + (m * 7));
						} else if (this.entry.recurrenceInterval === 'month') {
							d.setMonth(d.getMonth() + m);
						}
						d.setHours(9, 0, 0, 0);
						return Math.floor(d.getTime() / 1000);
					})() : null,
					isSubscription: isExpense && this.entry.isSubscription,
					isFixedCost: isExpense && this.entry.isFixedCost,
					isChildRelated: this.entry.isChildRelated,
					isImportant: this.entry.isImportant,
					needsReview: this.entry.needsReview,
					isTaxRelevant: this.entry.isTaxRelevant,
					reminderDate: this.entry.hasReminder && this.entry.reminderDate ? (() => {
						const rd = new Date(this.entry.reminderDate);
						rd.setHours(9, 0, 0, 0);
						return Math.floor(rd.getTime() / 1000);
					})() : null,
					reminderNotified: false,
					reminderText: this.entry.hasReminder ? this.entry.reminderText : ''
				};

				if (this.isEditing) {
					await axios.put(generateUrl(`/apps/cobudget/api/entries/${this.entry.id}`), payload);
					entryPersisted = true;
					await this.uploadPendingAttachments(this.entry.id);
					this.markEntryClean();
					showToast(this.$texts.entry.entrySaved());
				} else {
					const response = await axios.post(generateUrl('/apps/cobudget/api/entries'), payload);
					if (response.data?.id) {
						this.entry.id = response.data.id;
					}
					entryPersisted = true;
					await this.uploadPendingAttachments(this.entry.id);
					showToast(prepareNextEntry
						? this.$texts.entry.entryCreatedNewPrepared()
						: this.$texts.entry.entryCreated());
				}

				this.$emit('saved', {
					action: wasEditing ? 'updated' : 'created',
					entryId: this.entry.id || null
				});

				if (closeAfterSave) {
					this.closeSidebar();
				} else if (!wasEditing) {
					await this.openSidebar(
						null,
						retainedProjectId,
						retainedFutureContext,
						null,
							retainedDefaultType,
							retainedProjectLock,
							true
						);
				}
			} catch (e) {
				const fallback = entryPersisted
						? this.$texts.entry.entrySavedReceiptUploadError()
						: wasEditing
						? this.$texts.entry.entrySaveError()
						: this.$texts.entry.entryCreateError();
				showRequestError(e, fallback, 'Failed to save entry')
			}
			this.loading = false;
		},
		async deleteEntry() {
			const deletedEntryId = this.entry.id || null;
			const retainedProjectId = this.entry.projectId || null;
			const retainedFutureContext = this.isFutureContext;
			const retainedProjectLock = this.projectSelectionLocked;
			const retainedDefaultType = this.newEntryDefaultType;
			const msg = (this.isEditing && this.isFutureContext)
				? this.$texts.entry.disableFuturePaymentMessage()
				: this.$texts.entry.deleteEntryMessage();
			const title = (this.isEditing && this.isFutureContext) ? this.$texts.entry.disableFuturePaymentTitle() : this.$texts.entry.deleteEntryTitle();

			const confirmed = await this.openConfirm({
				title,
				message: msg,
				confirmLabel: this.isEditing && this.isFutureContext ? this.$texts.entry.disablePayment() : this.$texts.entry.deleteEntry(),
				confirmVariant: 'danger'
			});
			if (!confirmed) {
				return;
			}
			
			this.loading = true;
			try {
				if (this.isEditing && this.isFutureContext) {
					await axios.post(generateUrl(`/apps/cobudget/api/entries/${this.entry.id}/stop-recurrence`));
					showToast(this.$texts.entry.futurePaymentDisabled());
				} else {
					const deleteId = Number(this.entry.editable_entry_id || this.entry.source_entry_id || this.entry.id);
					await axios.delete(generateUrl(`/apps/cobudget/api/entries/${deleteId}`));
					showToast(this.$texts.entry.entryDeleted());
				}
				this.$emit('saved', { action: 'deleted', entryId: deletedEntryId });
				if (this.usesSidebarHistory()) {
					this.closeSidebar();
				} else {
					await this.openSidebar(
						null,
						retainedProjectId,
						retainedFutureContext,
						null,
							retainedDefaultType,
							retainedProjectLock,
							true
						);
				}
			} catch (e) {
				const fallback = this.isEditing && this.isFutureContext
					? this.$texts.entry.futurePaymentDisableError()
					: this.$texts.entry.entryDeleteError();
				showRequestError(e, fallback, 'Failed to delete entry')
			}
			this.loading = false;
		},
		evaluateAmount() {
			if (!this.entry.amountDisplay) return;
			let str = String(this.entry.amountDisplay).replace(/\s+/g, '');
			
			// Allow only digits, dots, and basic math operators
			if (/^[0-9.,+\-*/()]+$/.test(str)) {
				try {
					str = str.replace(/[0-9][0-9.,]*/g, token => String(this.$parseAmount(token)));
					// Safe math parser without eval/new Function due to Nextcloud CSP
					const tokens = str.match(/([0-9.]+)|([-+*/])/g);
					if (tokens) {
						let next = [];
						for (let i = 0; i < tokens.length; i++) {
							if (tokens[i] === '*' || tokens[i] === '/') {
								let prev = parseFloat(next.pop());
								let op = tokens[i];
								let val = parseFloat(tokens[++i]);
								next.push(op === '*' ? prev * val : prev / val);
							} else {
								next.push(tokens[i]);
							}
						}
						let result = parseFloat(next[0]);
						for (let i = 1; i < next.length; i+=2) {
							let op = next[i];
							let val = parseFloat(next[i+1]);
							if (op === '+') result += val;
							else if (op === '-') result -= val;
						}

						if (result !== null && !isNaN(result) && isFinite(result)) {
							this.entry.amountDisplay = this.$formatInputAmount(result);
							return;
						}
					}
				} catch (e) {
					console.error('Math parser error', e);
				}
			}
			
			// Fallback
			const parsed = this.$parseAmount(str);
			this.entry.amountDisplay = parsed ? this.$formatInputAmount(parsed) : '';
		},
		sanitizeAmountInput() {
			if (!this.entry.amountDisplay) {
				return;
			}
			const sanitized = String(this.entry.amountDisplay)
				.replace(/[^0-9.,+\-*/]/g, '')
				.replace(/^-+/, '');
			if (sanitized !== this.entry.amountDisplay) {
				this.entry.amountDisplay = sanitized;
			}
		}
	}
}
</script>

<style scoped>
.entry-sidebar {
	--entry-sidebar-content-padding: 4px;
	--entry-sidebar-header-action-gap: var(--default-grid-baseline, 4px);
	--entry-sidebar-header-save-width: calc(var(--default-grid-baseline, 4px) * 15);
	overflow: hidden;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

@media only screen and (min-width: 513px) {
	.entry-sidebar {
		--entry-sidebar-width: clamp(225px, 20.25vw, 375px);
		--app-sidebar-width: var(--entry-sidebar-width) !important;
		width: var(--entry-sidebar-width) !important;
		max-width: 375px;
	}
}

.entry-sidebar :deep(.app-sidebar-tabs),
.entry-sidebar :deep(.app-sidebar-tabs__content) {
	min-height: 0;
	overflow: hidden;
}

.entry-sidebar :deep(.app-sidebar-header) {
	border-bottom: none !important;
	background: var(--cobudget-surface-muted, var(--color-background-dark));
}

.entry-sidebar :deep(.app-sidebar-header__desc) {
	padding-inline-start: var(--entry-sidebar-content-padding);
}

.entry-sidebar--mobile-save-visible :deep(.app-sidebar-header__desc) {
	padding-inline-start: var(--entry-sidebar-content-padding) !important;
	padding-inline-end: calc(
		var(--app-sidebar-padding)
		+ var(--default-clickable-area)
		+ var(--entry-sidebar-header-save-width)
		+ var(--entry-sidebar-header-action-gap)
		+ var(--entry-sidebar-header-action-gap)
	) !important;
}

.entry-sidebar--mobile-save-visible :deep(.app-sidebar-header__tertiary-actions) {
	position: absolute;
	z-index: 101;
	top: var(--app-sidebar-padding);
	inset-inline-start: auto;
	inset-inline-end: calc(
		var(--app-sidebar-padding)
		+ var(--default-clickable-area)
		+ var(--entry-sidebar-header-action-gap)
	);
	width: var(--entry-sidebar-header-save-width) !important;
	height: var(--default-clickable-area) !important;
}

.entry-sidebar--mobile-save-visible :deep(.entry-sidebar-header-save.entry-sidebar-header-save.button-vue) {
	width: var(--entry-sidebar-header-save-width) !important;
	min-width: var(--entry-sidebar-header-save-width) !important;
	height: var(--default-clickable-area);
	min-height: var(--default-clickable-area);
}

.entry-sidebar-header-save :deep(.material-design-icon),
.entry-sidebar-header-save :deep(.material-design-icon__svg) {
	display: block;
}

.modal-form {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	width: 100%;
	height: 100%;
	min-height: 0;
	min-width: 0;
	margin: 0;
	padding: 0;
	box-sizing: border-box;
	overflow: hidden;
}

.info-banner {
	background-color: var(--cobudget-primary-light, var(--color-primary-element-light, #eaf4fb));
	color: var(--cobudget-text, var(--color-main-text, #000));
	padding: 12px 20px;
	margin: 0 0 16px 0;
	border-radius: var(--border-radius-large, 8px);
	font-size: var(--cobudget-font-compact);
	line-height: 1.4;
	border: 1px solid var(--cobudget-border, #ccc);
}

.required-marker {
	color: var(--cobudget-error);
}

.modal-body {
	box-sizing: border-box;
	width: 100%;
	min-width: 0;
	overflow-x: hidden;
	overflow-y: auto;
	overscroll-behavior: contain;
	flex: 1 1 auto;
	min-height: 0;
}

.form-row {
	display: flex;
	flex-direction: column;
	gap: 16px;
	margin-bottom: 16px;
}

.form-row .form-group {
	margin-bottom: 0;
}

.half {
	flex: 1;
}

.full {
	flex: 1 1 100%;
}

.date-col { flex: 1; }
.amount-col { flex: 1; }

.entry-required-panel,
.entry-details-grid {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: 16px;
}

.entry-required-panel {
	margin-bottom: 18px;
	align-items: stretch;
  padding-top: 5px;
}

.planning-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 16px;
	margin-top: 14px;
}

.planning-section {
	grid-column: 1 / -1;
	margin-top: 18px;
	padding: 8px 12px 12px;
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface-muted, #f7f7f7);
}

.planning-section summary {
	cursor: pointer;
  font-weight: 600;
  font-size: var(--cobudget-font-compact);
  color: var(--cobudget-text, var(--color-main-text, #333));
}

.planning-section summary:focus-visible {
	outline: 2px solid var(--color-primary, #0082c9);
	outline-offset: 2px;
	border-radius: var(--border-radius, 6px);
}

.planning-summary-status {
	margin-inline-start: 8px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #6b6b6b));
	font-size: var(--cobudget-font-small);
	font-weight: 400;
}

.planning-summary-status::before {
	content: '·';
	margin-inline-end: 8px;
}

.planning-card {
	grid-column: 1 / -1;
}

.project-assignment-row {
	display: grid;
	grid-column: 1 / -1;
	grid-template-columns: minmax(0, 1fr);
	gap: 16px;
	align-items: end;
}

.assignment-fields {
	grid-column: 1 / -1;
	margin-top: 0;
}

.area-choice-grid {
	display: grid;
	grid-template-columns: repeat(var(--area-choice-count), minmax(0, 1fr));
	gap: 10px;
}

.area-choice {
	--area-choice-state-background: var(--color-main-background, #fff);
	--area-choice-state-border: var(--cobudget-border-strong, var(--color-border-dark, #ccc));
	--area-choice-state-text: var(--cobudget-text, var(--color-main-text, #222));

	display: grid;
	grid-template-columns: 24px minmax(0, 1fr);
	align-items: center;
	gap: 10px;
	box-sizing: border-box;
	min-width: 0;
	min-height: 40px;
	margin: 0;
	padding: 8px 5px;
	border: 2px solid var(--area-choice-state-border) !important;
  border-radius: var(--border-radius, 6px);
	background: var(--area-choice-state-background) !important;
	box-shadow: none !important;
  font-weight: normal;
	color: var(--area-choice-state-text) !important;
	text-align: left;
	transform: none !important;
	transition: none;
}

.area-choice:focus {
	outline: none !important;
	box-shadow: none !important;
}

.area-choice:focus-visible {
	outline: 2px solid var(--cobudget-text, var(--color-main-text, #222)) !important;
	outline-offset: 2px;
}

.area-choice[aria-checked='true'] {
	--area-choice-state-border: var(--color-primary-element, #00679e);
	--area-choice-state-background: var(--color-primary-element-light, #e5f1f8);
}

.area-choice--project[aria-checked='true'] {
	--area-choice-state-border: var(--area-choice-selected-border);
	--area-choice-state-background: var(--area-choice-selected-background);
	--area-choice-state-text: var(--area-choice-selected-text);
}

.area-choice__icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	color: currentColor;
}

.area-choice__label {
	min-width: 0;
	overflow-wrap: anywhere;
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
  color: var(--cobudget-text-muted, #888);
}

.project-assignment-row.has-project-payer,
.project-assignment-row.has-split-mode {
	grid-template-columns: minmax(0, 1fr);
}

.core-date,
.core-amount,
.detail-category,
.detail-paymentPartner {
	grid-column: span 1;
}

.core-description,
.detail-tags {
	grid-column: 1 / -1;
}

.lookup-field {
	position: relative;
	width: 100%;
}

.lookup-field.has-leading-icon > .lookup-trigger {
	padding-left: 52px;
}

.lookup-field.has-clear-button > .lookup-trigger {
	padding-right: 76px;
}

.lookup-trigger {
	display: flex;
	align-items: center;
	width: 100%;
	min-height: 44px;
	box-sizing: border-box;
	text-align: left;
	cursor: pointer;
  font-weight: normal;
}

.lookup-trigger:hover:not(:disabled) {
	background: var(--cobudget-surface, var(--color-main-background, #fff));
}

button.lookup-trigger:focus,
button.lookup-trigger:focus-visible {
	background: var(--cobudget-surface, var(--color-main-background, #fff)) !important;
	border-width: 2px !important;
	border-color: var(--color-primary, #0082c9) !important;
	outline: none !important;
	box-shadow: none !important;
}

.lookup-trigger-value {
	flex: 1 1 auto;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.lookup-trigger-value.is-placeholder {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-weight: 400;
}

.category-lookup-trigger-value,
.payment-partner-lookup-trigger-value {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	justify-content: center;
	line-height: 1.2;
}

.category-lookup-trigger-value > span {
	display: block;
	width: 100%;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.lookup-trigger-code {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
	line-height: 1.1;
}

.lookup-trigger-name {
	line-height: 1.2;
}

.category-input-wrap.has-category-code > .lookup-trigger,
.lookup-field.has-payment-partner-number > .lookup-trigger {
	padding-block: var(--default-grid-baseline, 4px);
}

.lookup-chevron {
	position: absolute;
	top: 50%;
	right: 12px;
	z-index: 2;
	transform: translateY(-50%);
	pointer-events: none;
}

.category-input-icon {
	position: absolute;
	top: 50%;
	left: 12px;
	z-index: 2;
	margin-right: 0;
	transform: translateY(-50%);
	pointer-events: none;
}

.lookup-clear-button {
	position: absolute;
	z-index: 3;
  top: 18px;
  right: 30px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	margin: 0;
	padding: 0;
	border: 0;
	border-radius: 50%;
	background: transparent;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	cursor: pointer;
	transform: translateY(-50%);
}

.lookup-clear-button:hover,
.lookup-clear-button:focus-visible {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f2f2f2));
	color: var(--cobudget-text, var(--color-main-text, #222));
	outline: none;
}

.lookup-clear-button :deep(svg) {
	display: block;
}

.lookup-menu {
	position: absolute;
	z-index: 10020;
	top: calc(100% + 6px);
	left: 0;
	right: 0;
	overflow: hidden;
	padding: 6px 0 0;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface, #fff);
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
}

.lookup-options {
	position: relative;
	z-index: 1;
	max-height: min(320px, 42vh);
	overflow-x: hidden;
	overflow-y: auto;
	overscroll-behavior: contain;
	scrollbar-gutter: stable;
	padding: 0 6px 6px;
}

.lookup-create {
	position: relative;
	z-index: 1;
	padding: 6px;
	border-top: 1px solid var(--cobudget-border, #ddd);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
}

.lookup-add-button {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	min-height: 44px;
	margin: 0;
	padding: 0 10px;
	border: 0;
	border-radius: var(--border-radius, 6px);
	background: transparent;
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-weight: 600;
	text-align: left;
	cursor: pointer;
}

.lookup-add-button:hover,
.lookup-add-button:focus-visible {

	outline: none;
}

.lookup-create-form {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: 6px;
	align-items: center;
}

.lookup-create-input {
	min-width: 0;
	height: 44px;
	box-sizing: border-box;
}

.lookup-use-button {
	min-height: 44px;
	margin: 0;
	padding: 0 14px;
	border: 0;
	border-radius: var(--border-radius, 6px);
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f2f2f2));
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-weight: 600;
	cursor: pointer;
}

.lookup-use-button:disabled {
	opacity: 0.55;
	cursor: default;
}

.lookup-empty {
	padding: 12px 10px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.lookup-menu::before {
	content: '';
	position: absolute;
	top: -7px;
	left: 30px;
	width: 12px;
	height: 12px;
	border-top: 1px solid var(--cobudget-border, #ddd);
	border-left: 1px solid var(--cobudget-border, #ddd);
	background: var(--cobudget-surface, #fff);
	transform: rotate(45deg);
}

.lookup-group-label {
	position: relative;
	z-index: 1;
	padding: 8px 10px 4px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-xs);
	font-weight: 700;
	letter-spacing: 0;
	text-transform: uppercase;
}

.lookup-group-label:not(:first-child) {
	margin-top: 6px;
	border-top: 1px solid var(--cobudget-border, #ddd);
	padding-top: 10px;
}

.lookup-option {
	position: relative;
	z-index: 1;
	display: flex;
	align-items: center;
	gap: 10px;
	width: 100%;
	box-sizing: border-box;
	min-height: 36px;
	padding: 8px 10px;
	border: none;
	border-radius: var(--border-radius, 6px);
	background: transparent;
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--cobudget-font-base);
	text-align: left;
  font-weight: normal;
	cursor: pointer;
}

.lookup-option:hover,
.lookup-option.active {
	background: var(--cobudget-surface-muted, #f7f7f7);
}

.lookup-option.is-selected {
	background: var(--color-primary-element-light, #e5f1f8);
}

.lookup-option.is-selected.active,
.lookup-option.is-selected:hover {
	background: var(--color-primary-element-light-hover, var(--color-primary-element-light, #e5f1f8));
}

.lookup-option.is-subcategory {
	padding-inline-start: calc(10px + var(--default-grid-baseline, 4px) * 4);
}

.lookup-option :deep(.category-icon) {
	flex: 0 0 auto;
	margin-right: 0;
}

.lookup-option span {
	min-width: 0;
	overflow-wrap: anywhere;
}

.lookup-option-label {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	align-items: flex-start;
	line-height: 1.25;
}

.lookup-option-name {
	width: 100%;
}

.lookup-option-code {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
	line-height: 1.2;
	text-align: left;
	width: 100%;
}

.planning-card {
	margin: 0;
	padding: 12px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius, 6px);
	background: var(--cobudget-surface, #fff);
}

.planning-attachments-card {
	margin-top: 14px;
}

.planning-card .form-row {
	margin-bottom: 0;
	gap: 12px;
}

.assignment-fields .form-group,
.planning-card .form-group {
	margin-bottom: 0;
}

.reminder-text-row {
	margin-top: 12px;
}

.reminder-choice-field {
	flex: 1 1 auto;
	width: 100%;
	min-width: 0;
}

.reminder-date-field {
	flex: 1 1 auto;
	width: 100%;
	min-width: 0;
	max-width: none;
}

.recurrence-multiplier-input {
	height: 44px !important;
	padding: 10px 4px !important;
	text-align: center;
}

.lookup-option:focus-visible {
	outline: 2px solid var(--color-primary-element, var(--color-primary, #0082c9));
	outline-offset: 2px;
}

.form-group {
	margin-bottom: 0;
}

.form-group label {
	display: block;
  color: var(--cobudget-text-muted, #888);
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
}

.form-control {
	width: 100%;
	height: 44px !important;
	padding: 10px 12px;
	border: 2px solid var(--cobudget-border-strong, #ccc);
	border-radius: var(--border-radius, 6px);
	font-size: var(--cobudget-font-ui);
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-text, var(--color-main-text, #222));
	box-sizing: border-box;
	transition: border-color 0.2s;
}

.form-control::placeholder {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	opacity: 1;
}

.select-control {
	appearance: auto;
	-webkit-appearance: auto;
	-moz-appearance: auto;
	height: 44px !important;
	padding: 0 12px !important;
	line-height: 40px;
}

.form-control:not(.amount-input):hover:not(:disabled) {
	border-color: var(--cobudget-text, var(--color-main-text, #222)) !important;
	background: var(--cobudget-surface, var(--color-main-background, #fff)) !important;
	box-shadow: none !important;
}

.form-control:not(.amount-input):focus:not(:disabled),
.form-control:not(.amount-input):focus-visible:not(:disabled) {
	border: 2px solid var(--color-primary, #0082c9) !important;
	background: var(--cobudget-surface, var(--color-main-background, #fff)) !important;
	outline: none !important;
	box-shadow: none !important;
}

.amount-input {
	flex: 1 1 80px;
	width: 0;
	min-width: 70px;
	font-weight: 600;
	font-family: monospace;
	font-size: var(--cobudget-font-md);
	height: 40px !important;
	padding: 0 12px !important;
	line-height: 40px;
	text-align: right;
	border: 0 !important;
	border-radius: 0;
	outline: none;
	background: transparent !important;
	color: inherit !important;
	box-shadow: none !important;
	transition: background-color 0.2s, color 0.2s;
}

.amount-input:hover,
.amount-input:focus,
.amount-input:focus-visible,
.amount-input:active {
	border: 0 !important;
	outline: none !important;
	background: transparent !important;
	box-shadow: none !important;
}

.amount-input-wrap {
	--amount-accent: var(--cobudget-text, var(--color-main-text, #222));
	position: relative;
	display: flex;
	align-items: stretch;
	width: 100%;
	height: 44px;
	margin-block: 3px;
	border: 2px solid var(--cobudget-border-strong, #ccc);
	border-radius: var(--border-radius, 6px);
	box-sizing: border-box;
	overflow: visible;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--amount-accent);
	outline: none;
	box-shadow: none;
	transition: border-color 0.2s, background-color 0.2s, color 0.2s;
}

.amount-input-wrap.is-expense {
	--amount-accent: var(--cobudget-error);
	background: var(--cobudget-error-light);
}

.amount-input-wrap.is-income {
	--amount-accent: var(--cobudget-success);
	background: var(--cobudget-success-light);
}

.amount-input-wrap:hover {
	border-color: var(--color-border-dark, var(--cobudget-border-strong, #ccc));
}

.amount-input-wrap:focus-within {
	border-color: var(--color-primary-element, var(--color-primary, #0082c9));
	outline: none;
	box-shadow: none;
}

.amount-type-field {
	flex: 0 1 auto;
	align-self: stretch;
	min-width: 0;
	max-width: 48%;
}

.amount-type-field :deep(.v-popper) {
	display: flex;
	height: 100%;
	max-width: 100%;
}

.amount-type-field :deep(.amount-type-trigger.button-vue) {
	--button-size: 40px;
	width: fit-content;
	max-width: 100%;
	height: 40px;
	min-width: 0;
	min-height: 40px;
	padding: 0 10px !important;
	overflow: hidden;
	border: 0;
	border-inline-end: 1px solid color-mix(in srgb, var(--amount-accent) 38%, transparent);
	border-radius: calc(var(--border-radius, 6px) - 2px) 0 0 calc(var(--border-radius, 6px) - 2px);
	background: transparent;
	color: inherit;
	box-shadow: none;
}

.amount-type-field :deep(.amount-type-trigger.button-vue:hover:not(:disabled)),
.amount-type-field :deep(.amount-type-trigger.button-vue:focus),
.amount-type-field :deep(.amount-type-trigger.button-vue:focus-visible),
.amount-type-field :deep(.amount-type-trigger.button-vue:active:not(:disabled)),
.amount-type-field :deep(.amount-type-trigger.button-vue[aria-expanded="true"]) {
	background: transparent !important;
	outline: none !important;
	outline-offset: 0;
	box-shadow: none !important;
}

.amount-type-field :deep(.amount-type-trigger .button-vue__wrapper),
.amount-type-field :deep(.amount-type-trigger .button-vue__text) {
	min-width: 0;
	max-width: 100%;
}

.amount-type-trigger-content {
	display: flex;
	align-items: center;
	gap: 4px;
	min-width: 0;
	max-width: 100%;
}

.amount-type-label {
	min-width: 0;
	overflow: hidden;
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
	line-height: 1.3;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.amount-type-chevron {
	display: inline-flex;
	flex: 0 0 auto;
}

.amount-type-menu {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 150px;
	padding: 4px;
}

.amount-type-menu :deep(.amount-type-option.button-vue) {
	width: 100%;
	min-height: 40px;
	justify-content: flex-start;
	padding: 0 8px !important;
	border: 0;
	border-radius: var(--border-radius, 6px);
	background: transparent;
	box-shadow: none;
}

.amount-type-menu :deep(.amount-type-option.button-vue.is-expense) {
	color: var(--cobudget-error);
}

.amount-type-menu :deep(.amount-type-option.button-vue.is-income) {
	color: var(--cobudget-success);
}

.amount-type-menu :deep(.amount-type-option.button-vue:hover:not(:disabled)),
.amount-type-menu :deep(.amount-type-option.button-vue.is-highlighted) {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5)) !important;
}

.amount-type-menu :deep(.amount-type-option.button-vue.is-selected) {
	background: color-mix(in srgb, currentColor 10%, transparent) !important;
}

.amount-type-option-content {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	font-weight: 600;
}

.amount-type-check {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 18px;
	width: 18px;
	height: 18px;
}

.amount-value-wrap {
	display: flex;
	align-items: center;
	flex: 1 1 auto;
	min-width: 0;
	height: 100%;
	cursor: text;
}

.amount-currency-prefix {
	flex: 0 1 auto;
	margin-inline-start: 12px;
	max-width: 52px;
	overflow: hidden;
	color: inherit;
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
	line-height: 1;
	text-overflow: ellipsis;
	white-space: nowrap;
	pointer-events: none;
}

.amount-col label {
	text-align: right;
}

.amount-input.bg-expense::placeholder {
	color: var(--amount-accent);
	opacity: 0.7;
}

.amount-input.bg-income::placeholder {
	color: var(--amount-accent);
	opacity: 0.7;
}

.date-col :deep(input) {
	height: 44px !important;
	line-height: 40px !important;
	font-size: var(--cobudget-font-ui) !important;
  border-radius: var(--entry-sidebar-control-radius);
	box-sizing: border-box;
	border: 2px solid var(--cobudget-border-strong, #ccc) !important;
	background: var(--cobudget-surface, #fff) !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.help-text {
	margin-top: 6px;
	font-size: var(--cobudget-font-sm);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.tags-group {
	margin-bottom: 0;
	align-self: end;
	box-sizing: border-box;
	padding-inline: var(--default-grid-baseline, 4px);
}

.tags-toggles {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	min-height: 44px;
	align-items: center;
}

.tag-toggle {
	position: relative;
	cursor: pointer;
}

.tag-toggle input {
	position: absolute;
	opacity: 0;
	cursor: pointer;
	height: 0;
	width: 0;
}

.tag-btn {
	display: inline-block;
	padding: 5px 10px;
	background: var(--cobudget-surface-muted, var(--color-background-hover, #eee));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border-radius: 4px;
	font-size: var(--cobudget-font-xs);
	font-weight: 600;
	border: 2px solid var(--cobudget-border-strong, var(--color-border-dark, #ccc));
	cursor: pointer;
}

.tag-toggle input:focus-visible + .tag-btn {
	outline: 2px solid var(--cobudget-text, var(--color-main-text, #222));
	outline-offset: 2px;
}

.tag-toggle input:checked + .tag-btn {
	background: var(--cobudget-primary, var(--color-primary-element, #0082c9));
	color: var(--color-primary-element-text, var(--cobudget-primary-text, #fff));
	border-color: var(--cobudget-primary, var(--color-primary, #0082c9));
}

.attachments-inline {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 8px;
	margin-top: 10px;
}

.planning-attachments {
	margin-top: 0;
}

.attachments-empty,
.attachment-meta {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #777));
	font-size: var(--cobudget-font-sm);
}

.attachments-empty {
	margin: 0;
}

.attachment-upload-btn {
	position: relative;
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	min-height: 34px;
	padding: 0 12px;
	border-radius: var(--border-radius, 6px);
	background: var(--cobudget-surface-muted, #f7f7f7);
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
	cursor: pointer;
}

.attachment-upload-btn:hover {
	background: var(--cobudget-surface-strong, var(--color-background-darker, #ddd));
}

.attachment-upload-btn:focus-within {
	outline: 2px solid var(--color-primary, #0082c9);
	outline-offset: 2px;
}

.attachment-upload-btn input {
	position: absolute;
	width: 1px;
	height: 1px;
	opacity: 0;
	pointer-events: none;
}

.attachment-list {
	display: flex;
	flex-direction: column;
	gap: 0px;
	width: 100%;
	margin: 0;
	padding: 0;
	list-style: none;
}

.attachment-list li {
	display: flex;
	align-items: center;
	gap: 8px;
	min-height: 34px;
	box-sizing: border-box;
	padding: 5px 8px;
	background: var(--cobudget-surface, #fff);
}

.attachment-list a,
.attachment-list li > span:first-child {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: 600;
}

.attachment-pending {
	background: var(--cobudget-surface-muted, #f7f7f7) !important;
}

.attachment-meta {
	margin-left: auto;
	white-space: nowrap;
}

.attachment-remove {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	min-width: 28px;
	min-height: 28px;
	box-sizing: border-box;
	padding: 0;
	border: none;
	border-radius: 50%;
	background: transparent;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #777));
	font-size: var(--cobudget-font-xl);
	font-weight: 700;
	line-height: 28px;
	text-align: center;
	cursor: pointer;
}

.attachment-remove:hover,
.attachment-remove:focus-visible {
	background: var(--cobudget-error-light);
	color: var(--cobudget-error);
	outline: none;
}

.form-actions {
	display: flex;
	align-items: center;
	padding: 10px var(--entry-sidebar-content-padding);
	margin-top: 0;
	border-top: 1px solid var(--cobudget-border, var(--color-border));
	background: var(--cobudget-surface-muted, var(--color-background-dark));
	flex-shrink: 0;
	border-radius: 0 0 10px 10px;
}

.form-actions :deep(.cobudget-button--secondary) {
	background: transparent !important;
	border-color: transparent !important;
	box-shadow: none !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.form-actions :deep(.cobudget-button--secondary:hover:not(:disabled)),
.form-actions :deep(.cobudget-button--secondary:focus-visible:not(:disabled)) {
	background: var(--cobudget-surface-muted) !important;
	border-color: transparent !important;
	box-shadow: none !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.form-actions :deep(.cobudget-button--secondary:focus-visible:not(:disabled)) {
	outline: 2px solid var(--color-primary-element, var(--color-primary, #0082c9));
	outline-offset: 2px;
}

.form-actions :deep(.cobudget-button--secondary:disabled) {
	background: transparent !important;
	border-color: transparent !important;
	box-shadow: none !important;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666)) !important;
	opacity: 0.55 !important;
}

.entry-delete-icon-button {
	color: var(--cobudget-error);
}

.entry-delete-icon-button:hover:not(:disabled),
.entry-delete-icon-button:focus-visible {
	background: var(--cobudget-error-light);
	color: var(--cobudget-error);
}

.entry-delete-icon-button :deep(.material-design-icon),
.entry-delete-icon-button :deep(.material-design-icon__svg) {
	display: block;
}

.recurrence-group {
	margin-top: 0;
	border-top: none;
}

.recurrence-options {
	margin-top: 0;
	background: transparent;
	padding: 0;
	border-radius: 0;
	border: none;
}

.recurrence-group .recurrence-options {
	background: transparent;
	padding: 0;
	border-radius: 0;
	border: none;
}

.recurrence-options.is-active-bg {
	background: transparent;
	padding: 0;
	border-radius: 0;
	border: none;
}

.align-items-end {
	align-items: stretch;
}

.recurrence-preview {
	margin-top: 12px;
	font-size: var(--cobudget-font-compact);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.recurrence-inputs {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: 12px;
	width: 100%;
	min-width: 0;
	align-items: end;
}

.recurrence-inputs.is-recurring {
	grid-template-columns: minmax(calc(var(--default-grid-baseline, 4px) * 18), 0.42fr) minmax(0, 1fr);
	column-gap: calc(var(--default-grid-baseline, 4px) * 2);
}

.recurrence-multiplier-field label {
	white-space: nowrap;
}

.recurrence-end-field {
	grid-column: 1 / -1;
}

.recurrence-inputs .recurrence-multiplier-input {
	width: 100% !important;
	min-width: 0;
}

.recurrence-inputs .select-control {
	width: 100%;
	min-width: 0;
}

.recurrence-inputs.is-recurring .recurrence-multiplier-input,
.recurrence-inputs.is-recurring .recurrence-interval-field .select-control {
	height: var(--default-clickable-area, 44px) !important;
	min-height: var(--default-clickable-area, 44px);
	margin: 0 !important;
}

@media (max-width: 780px) {
	.entry-sidebar {
		--entry-sidebar-content-padding: 16px;
	}

	.planning-summary-status {
		display: block;
		margin-block-start: 4px;
		margin-inline-start: 18px;
	}

	.planning-summary-status::before {
		display: none;
	}

	.modal-body {
		padding: 16px;
	}

	.entry-details-grid,
	.planning-grid {
		grid-template-columns: 1fr;
	}

	.entry-details-grid {
		row-gap: 22px;
	}

	.core-description,
	.project-assignment-row,
	.detail-tags,
	.planning-card,
	.core-date,
	.core-amount,
	.detail-category,
		.detail-paymentPartner {
		grid-column: auto;
	}

	.project-assignment-row,
	.project-assignment-row.has-project-payer,
	.project-assignment-row.has-split-mode {
		grid-template-columns: 1fr;
	}

	.area-choice-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.area-choice {
		min-height: 52px;
	}

	.form-row {
		flex-direction: column;
	}

	.recurrence-inputs {
		grid-template-columns: 1fr;
	}

	.recurrence-inputs.is-recurring {
		grid-template-columns: minmax(calc(var(--default-grid-baseline, 4px) * 18), 0.42fr) minmax(0, 1fr);
	}

	.recurrence-multiplier-field label {
		white-space: nowrap;
	}

	.reminder-group .form-row.align-items-end {
		align-items: stretch;
	}

	.reminder-text-row {
		flex-direction: column;
	}
}

@media (max-width: 600px) {
	.entry-sidebar {
		--entry-sidebar-content-padding: 20px;
	}

	.entry-required-panel {
		grid-template-columns: 1fr;
	}

	.core-date,
	.core-amount {
		grid-column: 1;
	}

	.form-actions {
		border-radius: 0;
		padding: 10px 20px;
	}

	.entry-delete-icon-button {
		width: var(--cobudget-mobile-touch-size);
		min-width: var(--cobudget-mobile-touch-size);
		height: var(--cobudget-mobile-touch-size);
		min-height: var(--cobudget-mobile-touch-size);
	}

	.modal-body {
		padding: 20px;
	}
}

@media (min-width: 1025px) {
	.entry-sidebar {
		--entry-sidebar-control-radius: var(--cobudget-radius-md, var(--border-radius-element, 8px));
		--entry-sidebar-header-edge-inset: var(--app-sidebar-padding, calc(var(--default-grid-baseline, 4px) * 2));
		--entry-sidebar-scrollbar-size: calc(var(--default-grid-baseline, 4px) * 2);

		border-inline-start: 0 !important;
		background: transparent !important;
	}

	.entry-sidebar :deep(.app-sidebar-header) {
		position: relative;
		flex: 0 0 auto;
		width: auto;
		height: auto;
		min-height: var(--default-clickable-area, 44px);
		margin: 0;
		margin-block-start: var(--entry-sidebar-header-edge-inset);
		margin-inline-end: calc(
			var(--entry-sidebar-header-edge-inset)
			+ var(--entry-sidebar-content-padding)
		);
		padding: 0;
		overflow: visible;
		clip: auto;
		clip-path: none;
		white-space: normal;
		background: transparent !important;
	}

	.entry-sidebar :deep(.app-sidebar-header__desc) {
		min-height: var(--default-clickable-area, 44px);
		padding-block: 0;
		padding-inline-start: var(--entry-sidebar-content-padding) !important;
		padding-inline-end: calc(
			var(--entry-sidebar-content-padding)
			+ var(--default-clickable-area, 44px)
			+ var(--entry-sidebar-header-action-gap)
		) !important;
	}

	.entry-sidebar :deep(.app-sidebar-header__mainname) {
		min-height: auto !important;
		font-size: var(--cobudget-font-md, 16px) !important;
		font-weight: var(--font-weight-heading, 600);
		line-height: 1.25 !important;
	}

	.entry-sidebar :deep(.app-sidebar-header__tertiary-actions) {
		position: absolute;
		z-index: 101;
		inset-block-start: 0;
		inset-inline-start: auto;
		inset-inline-end: 0;
		width: var(--default-clickable-area, 44px);
		height: var(--default-clickable-area, 44px);
	}

	.entry-sidebar :deep(.entry-sidebar-desktop-close.button-vue) {
		width: var(--default-clickable-area, 44px);
		min-width: var(--default-clickable-area, 44px);
		height: var(--default-clickable-area, 44px);
		min-height: var(--default-clickable-area, 44px);
	}

	.entry-sidebar :deep(.app-sidebar-header > .app-sidebar__close) {
		display: none;
	}

	.modal-body {
		padding-block-start: var(--entry-sidebar-content-padding);
		scrollbar-color: transparent transparent;
		scrollbar-gutter: stable;
		scrollbar-width: thin;
	}

	.modal-body:hover,
	.modal-body:focus-within {
		scrollbar-color: var(--color-border-dark, var(--color-border)) transparent;
	}

	.modal-body::-webkit-scrollbar {
		width: var(--entry-sidebar-scrollbar-size);
		height: var(--entry-sidebar-scrollbar-size);
	}

	.modal-body::-webkit-scrollbar-track {
		background: transparent;
	}

	.modal-body::-webkit-scrollbar-thumb {
		border-radius: var(--border-radius-pill, 999px);
		background: transparent;
	}

	.modal-body:hover::-webkit-scrollbar-thumb,
	.modal-body:focus-within::-webkit-scrollbar-thumb {
		background: var(--color-border-dark, var(--color-border));
	}

	.form-control,
	.amount-input-wrap,
	.area-choice {
		border-radius: var(--entry-sidebar-control-radius);
	}

	.form-actions {
		padding: calc(var(--default-grid-baseline, 4px) * 2) var(--entry-sidebar-content-padding)
			calc(var(--default-grid-baseline, 4px) * 3);
		border-top: 0;
		border-radius: 0;
		background: transparent;
	}
}

@media (prefers-color-scheme: dark) {
	.amount-input-wrap.is-expense {
		--amount-accent: var(--cobudget-error-dark, var(--cobudget-error));
	}

	.amount-input-wrap.is-income {
		--amount-accent: var(--cobudget-success);
	}

	.amount-input.bg-expense::placeholder,
	.amount-input.bg-income::placeholder {
		opacity: 0.85;
	}
}
</style>
