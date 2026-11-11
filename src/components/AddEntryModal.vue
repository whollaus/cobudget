<template>
	<NcAppSidebar
		class="entry-sidebar"
		:class="{ 'entry-sidebar--mobile-save-visible': showMobileHeaderSave, 'entry-sidebar--closing': !isOpen, 'entry-sidebar--type-field-header': $enableIncomes && isWideViewport }"
		:name="sidebarTitle"
		aria-labelledby="entry-sidebar-title"
		:open="isOpen"
		:no-toggle="true"
		@opened="handleSidebarOpened"
		@closed="handleSidebarClosed"
		@update:open="handleSidebarOpenUpdate">
		<template #info>
			<div class="entry-header-content app-sidebar-header__desc app-sidebar-header__desc--with-tertiary-action app-sidebar-header__desc--without-actions">
				<div class="app-sidebar-header__tertiary-actions">
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
				</div>
				<EntrySidebarHeading :class="{ 'hidden-visually entry-type-accessible-heading': $enableIncomes && isWideViewport }" :title="sidebarTitle">{{ sidebarTitle }}</EntrySidebarHeading>
				<EntryTypeField v-if="$enableIncomes && isWideViewport" ref="paymentTypeSelect" header
					:model-value="entryTypeChoice" :options="entryTypeOptions" :disabled="loading || isInitializingEntry"
					@update:model-value="selectEntryType" />
			</div>
		</template>
		<form
			ref="modalContent"
			class="modal-form"
			@submit.prevent="saveEntry"
			@keydown.esc.stop.prevent="handleEscape"
			@focusin="handleModalFocusIn"
			@focusout="handleModalFocusOut">
				<div ref="modalBody" class="modal-body">
					<NcNoteCard v-if="isEditing && isFutureContext" type="info" class="entry-notice">
						<strong>{{ $texts.entry.futureOriginalNoticeTitle() }}</strong> {{ $texts.entry.futureOriginalNotice() }}
					</NcNoteCard>
					<NcNoteCard v-if="isReleasedPersonal" type="info" class="entry-notice" :text="$texts.entry.releasedPersonalNotice()" />
					<NcNoteCard v-for="(message, index) in [projectsError, lookupsError, membersError].filter(Boolean)" :key="index" type="error" class="entry-notice" :text="message" />

					<EntryTypeField v-if="$enableIncomes && !isWideViewport" ref="paymentTypeSelect"
						:model-value="entryTypeChoice" :options="entryTypeOptions" :disabled="loading || isInitializingEntry"
						@update:model-value="selectEntryType" />

					<div class="entry-required-panel">
						<div class="form-group date-col core-date">
							<label for="entry-date">{{ dateLabel }}</label>
							<NcInputField
								id="entry-date"
								:disabled="loading || isInitializingEntry"
								type="date"
								v-model="dateString"
								:min="minFutureDate"
								:aria-label="dateLabel"
								class="cobudget-native-field"
								label-outside
								required />
						</div>

						<div class="form-group amount-col core-amount">
							<label for="entry-amount">{{ $texts.entry.amount() }}</label>
							<div
								class="amount-input-wrap"
								:class="{
									'has-currency': $currency,
								}">

								<div class="amount-value-wrap" @click="focusAmountInput">
									<span v-if="$currency" class="amount-currency-prefix" aria-hidden="true">{{ $currency }}</span>
									<input
										type="text"
										id="entry-amount"
										ref="amountInput"
										v-model="entry.amountDisplay"
										:aria-label="amountLabel"
										:disabled="loading || isInitializingEntry"
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
									required
										placeholder="0.00">
								</div>
							</div>
						</div>
					</div>

					<div class="entry-details-grid">
						<EntryLookupSelect ref="paymentPartnerLookupTrigger" class="form-group detail-paymentPartner"
							input-id="entry-payment-partner" :label="paymentPartnerLabel" :clear-label="$texts.entry.clearPaymentPartner()"
							:value="paymentPartnerLookupSelection" :disabled="loading || isInitializingEntry"
							:get-sections="query => lookupSelectSections('paymentPartner', query)" :exact-match="name => exactLookupMatch('paymentPartner', name)"
							@select="selectNativeLookup('paymentPartner', $event)" @create="createNativeLookup('paymentPartner', $event)"
							@open="focusedLookupField = 'paymentPartner'" @close="handleNativeLookupClose('paymentPartner')" />
						<EntryLookupSelect ref="categoryLookupTrigger" class="form-group detail-category"
							input-id="entry-category" :label="$texts.entry.category()" :clear-label="$texts.entry.clearCategory()" is-category
							:value="categoryLookupSelection" :disabled="loading || isInitializingEntry"
							:get-sections="query => lookupSelectSections('category', query)" :exact-match="name => exactLookupMatch('category', name)"
							@select="selectNativeLookup('category', $event)" @create="createNativeLookup('category', $event)"
							@open="focusedLookupField = 'category'" @close="handleNativeLookupClose('category')" />

						<div class="form-group core-description detail-description">
							<label for="entry-description">{{ $texts.entry.description() }}</label>
							<NcInputField id="entry-description" v-model="entry.description" class="cobudget-native-field" label-outside :placeholder="$texts.entry.descriptionPlaceholder()" :disabled="loading" />
						</div>

						<div class="form-group tags-group detail-tags" v-if="hasAvailableTags">
							<div id="entry-labels-heading" class="entry-group-label">{{ $texts.settings.labels() }}</div>
							<div class="tags-toggles" role="group" aria-labelledby="entry-labels-heading">
								<label class="tag-toggle" v-if="$enableImportantPayments">
									<input type="checkbox" :disabled="loading || isInitializingEntry" v-model="entry.isImportant">
									<span class="tag-btn"><NavigationIcon name="important" :size="16" />{{ $texts.labels.important() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableReviewPayments">
									<input type="checkbox" :disabled="loading || isInitializingEntry" v-model="entry.needsReview">
									<span class="tag-btn"><NavigationIcon name="review" :size="16" />{{ $texts.labels.review() }}</span>
								</label>
								<label class="tag-toggle" v-if="entry.type === 'expense' && $enableFixedCosts">
									<input type="checkbox" :disabled="loading || isInitializingEntry" v-model="entry.isFixedCost">
									<span class="tag-btn"><NavigationIcon name="fixedCost" :size="16" />{{ $texts.labels.fixedCosts() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableChildRelated">
									<input type="checkbox" :disabled="loading || isInitializingEntry" v-model="entry.isChildRelated">
									<span class="tag-btn"><NavigationIcon name="childRelated" :size="16" />{{ $texts.labels.children() }}</span>
								</label>
								<label class="tag-toggle" v-if="entry.type === 'expense' && $enableSubscriptions">
									<input type="checkbox" :disabled="loading || isInitializingEntry" v-model="entry.isSubscription">
									<span class="tag-btn"><NavigationIcon name="subscription" :size="16" />{{ $texts.labels.subscription() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableTaxRelevant">
									<input type="checkbox" :disabled="loading || isInitializingEntry" v-model="entry.isTaxRelevant">
									<span class="tag-btn"><NavigationIcon name="taxRelevant" :size="16" />{{ $texts.labels.taxRelevant() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableInsurance">
									<input type="checkbox" :disabled="loading || isInitializingEntry" v-model="entry.isInsurance">
									<span class="tag-btn"><NavigationIcon name="insurance" :size="16" />{{ $texts.labels.insurance() }}</span>
								</label>
							</div>
						</div>

					<div
						v-if="$enableProjects && (entry.projectId || activeProjects.length > 0)"
							class="assignment-fields">
							<div class="project-assignment-row" :class="{ 'has-project-payer': showProjectPayerSelect, 'has-split-mode': showProjectSplitMode }">
								<div class="form-group detail-project" :class="{ 'detail-project--direct': useDirectAreaSelection, 'detail-project--single-column': areaSelectionOptions.length <= 3 }">
									<label for="entry-project">{{ $texts.entry.area() }}</label>
									<div
										v-if="useDirectAreaSelection"
										class="area-choice-grid"
										:style="{ '--area-choice-columns': areaSelectionOptions.length > 1 ? 2 : 1 }"
										role="radiogroup"
										:aria-label="$texts.entry.area()">
										<button
											v-for="option in areaSelectionOptions"
											:key="option.key"
											type="button"
											class="area-choice"
											:disabled="loading || isInitializingEntry || isAreaAssignmentLocked"
											:style="areaChoiceStyle(option)"
											role="radio"
											:aria-checked="isAreaSelected(option.id) ? 'true' : 'false'"
											@click="selectArea(option.id)">
											<span class="area-choice__icon" aria-hidden="true">
												<AreaIcon :without-area="option.id === null" :size="22" :color="isAreaSelected(option.id) ? 'currentColor' : option.color" />
											</span>
											<span class="area-choice__label">{{ option.name }}</span>
										</button>
									</div>
									<NcSelect
										v-if="!useDirectAreaSelection || areaSelectionOptions.length > 3"
										v-model="areaSelectionChoice"
										input-id="entry-project"
										class="cobudget-native-select entry-member-select entry-area-select"
										label-outside
										:input-label="$texts.entry.area()"
										:options="areaSelectionOptions"
										label="name"
										:clearable="false"
										:searchable="false"
										:disabled="loading || isInitializingEntry || isAreaAssignmentLocked">
										<template #option="option">
											<span class="entry-area-option">
												<AreaIcon :without-area="option.id === null" :size="22" :color="option.color" />
												<span>{{ option.name }}</span>
											</span>
										</template>
										<template #selected-option="option">
											<span class="entry-area-option entry-area-option--selected" :title="option.name">
												<AreaIcon :without-area="option.id === null" :size="22" :color="option.color" />
												<span>{{ option.name }}</span>
											</span>
										</template>
									</NcSelect>
								</div>
								<div class="form-group detail-project-payer" v-if="showProjectPayerSelect">
									<label for="entry-project-payer">{{ projectPayerLabel }}</label>
									<NcSelect v-model="projectPayerChoice" input-id="entry-project-payer" class="cobudget-native-select entry-member-select" label-outside :input-label="projectPayerLabel" :options="projectPayerOptions" label="displayName" :clearable="false" :searchable="false" :selectable="isSelectableProjectMember" :disabled="loading">
										<template #option="member"><MemberIdentity :user-id="member.id" :display-name="member.displayName" :is-former="member.isFormer" :size="32" /></template>
										<template #selected-option="member"><MemberIdentity class="entry-member-selection" :title="member.displayName" :user-id="member.id" :display-name="member.displayName" :is-former="member.isFormer" /></template>
									</NcSelect>
								</div>
								<div class="form-group detail-split-mode" v-if="showProjectSplitMode">
									<label for="entry-project-split">{{ $texts.entry.split() }}</label>
									<NcSelect v-model="projectSplitChoice" input-id="entry-project-split" class="cobudget-native-select entry-member-select" label-outside :input-label="$texts.entry.split()" :options="projectSplitChoices" label="label" :clearable="false" :searchable="false" :selectable="isSelectableProjectMember" :disabled="loading">
										<template #option="option">
											<MemberIdentity v-if="option.id" :user-id="option.id" :display-name="option.displayName" :is-former="option.isFormer" :size="32">{{ option.label }}</MemberIdentity>
											<span v-else class="entry-area-split-option"><AccountMultipleIcon :size="32" /><span>{{ option.label }}</span></span>
										</template>
										<template #selected-option="option">
											<MemberIdentity v-if="option.id" class="entry-member-selection" :title="option.label" :user-id="option.id" :display-name="option.displayName" :is-former="option.isFormer">{{ option.label }}</MemberIdentity>
											<span v-else class="entry-area-split-option entry-member-selection" :title="option.label"><AccountMultipleIcon :size="24" /><span>{{ option.label }}</span></span>
										</template>
									</NcSelect>
								</div>
							</div>
						</div>

					</div>

					<section class="planning-section" :aria-label="$texts.entry.planningWithReceipts()">
						<div id="entry-planning-fields">
							<div v-if="showAttachmentSection" class="planning-card planning-attachments-card" ref="attachmentsGroup">
								<div class="attachments-inline planning-attachments">
									<input ref="attachmentInput" type="file" hidden multiple :disabled="loading" @change="onAttachmentFilesSelected">
									<NcButton class="attachment-upload-btn" variant="tertiary" wide alignment="start" type="button" :disabled="loading || isInitializingEntry"
										:aria-expanded="hasAttachments ? showAttachmentList : undefined" :aria-controls="hasAttachments ? 'entry-attachment-list' : undefined" @click="toggleAttachmentList">
										<template #icon><PaperclipIcon :size="20" /></template>
										<span class="attachment-action-label">{{ hasAttachments ? $texts.entry.receiptCount(attachments.length + pendingAttachments.length) : $texts.entry.addReceipt() }}</span>
										<ChevronDownIcon v-if="hasAttachments && showAttachmentList" :size="20" /><ChevronRightIcon v-else-if="hasAttachments" :size="20" />
									</NcButton>

									<NcNoteCard v-if="receiptsError" type="error" class="entry-notice" :text="receiptsError" />
									<div v-if="attachmentsLoading" class="attachments-empty">{{ $texts.entry.receiptsLoading() }}</div>
									<ul v-if="hasAttachments" v-show="showAttachmentList" id="entry-attachment-list" class="attachment-list">
										<li v-for="attachment in attachments" :key="`existing-${attachment.id}`">
											<a :href="attachmentDownloadUrl(attachment)" target="_blank" rel="noopener">
												{{ attachment.file_name }}
											</a>
											<span class="attachment-meta">{{ formatFileSize(attachment.file_size) }}</span>
											<NcButton v-if="canDeleteAttachment(attachment)" type="button" variant="tertiary" :disabled="loading" @click="deleteAttachment(attachment)" :aria-label="$texts.entry.removeReceipt()"><template #icon><DeleteOutlineIcon :size="20" /></template></NcButton>
										</li>
										<li v-for="(file, index) in pendingAttachments" :key="`pending-${index}-${file.name}`" class="attachment-pending">
											<span>{{ file.name }}</span>
											<span class="attachment-meta">{{ $texts.entry.uploadOnSave() }}</span>
											<NcButton type="button" variant="tertiary" :disabled="loading" @click="removePendingAttachment(index)" :aria-label="$texts.entry.removeSelection()"><template #icon><CloseIcon :size="20" /></template></NcButton>
										</li>
									</ul>
									<NcButton v-if="hasAttachments && showAttachmentList" class="attachment-upload-btn" variant="tertiary" wide alignment="start" :disabled="loading || isInitializingEntry" @click="$refs.attachmentInput.click()">
										<template #icon><PaperclipIcon :size="20" /></template>{{ $texts.entry.addReceipt() }}
									</NcButton>
								</div>
							</div>
							<div class="planning-grid">
								<div ref="recurrenceGroup" class="form-group recurrence-group planning-card" v-if="$enableFuturePayments">
									<NcButton class="attachment-upload-btn recurrence-disclosure" variant="tertiary" wide alignment="start" type="button" :disabled="loading || isInitializingEntry"
										:aria-expanded="showRecurrenceDetails" aria-controls="entry-recurrence-details" @click="toggleRecurrenceDetails">
										<template #icon><NavigationIcon name="future" :size="20" /></template>
										<span class="attachment-action-label">{{ $texts.entry.repeat() }}</span>
										<span v-if="entry.recurrenceInterval !== 'none'" class="cobudget-filter-dot" aria-hidden="true" />
										<ChevronDownIcon :size="20" :class="{ 'disclosure-open': showRecurrenceDetails }" />
									</NcButton>
									<div v-show="showRecurrenceDetails" id="entry-recurrence-details" class="recurrence-options planning-details">
										<fieldset class="planning-fields payment-recurrence-fields" :aria-label="$texts.entry.repeat()" :disabled="loading || isInitializingEntry">
											<div class="recurrence-inputs" :class="{ 'is-recurring': entry.recurrenceInterval !== 'none' }">
												<div class="form-group recurrence-multiplier-field" v-if="entry.recurrenceInterval !== 'none'">
													<label for="entry-recurrence-multiplier">{{ $texts.entry.repeatEvery() }}</label>
													<NcInputField id="entry-recurrence-multiplier" v-model="recurrenceMultiplierValue" type="number" class="cobudget-native-field recurrence-multiplier-input" label-outside min="1" step="1" required :disabled="loading" />
												</div>
												<div class="form-group recurrence-interval-field">
													<NcSelect v-model="recurrenceIntervalChoice" input-id="entry-recurrence-interval"
														class="cobudget-native-select planning-select" label-outside :aria-label-combobox="$texts.entry.recurrenceInterval()"
														:options="recurrenceIntervalOptions" label="label" :clearable="false" :searchable="false" :disabled="loading" />
												</div>
												<div class="form-group recurrence-end-field date-col" v-if="entry.recurrenceInterval !== 'none'">
													<label for="entry-recurrence-end">{{ $texts.entry.endDateOptional() }}</label>
													<NcInputField id="entry-recurrence-end" v-model="recurrenceEndDateString" type="date" class="cobudget-native-field" label-outside :disabled="loading" />
												</div>
											</div>
											<div v-if="entry.recurrenceInterval !== 'none' && nextRecurrence" class="recurrence-preview">
												{{ $texts.entry.nextEntryAt(nextRecurrence) }}
											</div>
										</fieldset>
									</div>
								</div>

								<div v-if="$enableReminders" ref="reminderGroup" class="form-group reminder-group planning-card">
									<NcButton class="attachment-upload-btn reminder-disclosure" variant="tertiary" wide alignment="start" type="button" :disabled="loading || isInitializingEntry"
										:aria-expanded="showReminderDetails" aria-controls="entry-reminder-details" @click="toggleReminderDetails">
										<template #icon><BellOutlineIcon :size="20" /></template>
										<span class="attachment-action-label">{{ $texts.entry.reminder() }}</span>
										<span v-if="entry.hasReminder && entry.reminderDate" class="cobudget-filter-dot" aria-hidden="true" />
										<ChevronDownIcon :size="20" :class="{ 'disclosure-open': showReminderDetails }" />
									</NcButton>
									<NcNoteCard v-if="reminderLoadError" type="error" class="entry-notice">
										{{ reminderLoadError }}
										<NcButton variant="tertiary" :disabled="loading || isInitializingEntry" @click="retryPaymentReminder">{{ $texts.common.retry() }}</NcButton>
									</NcNoteCard>
									<div v-show="showReminderDetails" id="entry-reminder-details" class="recurrence-options planning-details">
										<fieldset class="planning-fields payment-reminder-fields" :aria-label="$texts.entry.reminder()" :disabled="loading || isInitializingEntry || !!reminderLoadError">
											<div class="form-row"><div class="form-group full">
												<label for="entry-reminder-date">{{ $texts.reminders.remindAt() }}</label>
												<NcInputField id="entry-reminder-date" v-model="reminderDateString" type="date" class="cobudget-native-field" label-outside :aria-label="$texts.reminders.remindAt()" />
											</div></div>
											<div class="form-row reminder-text-row"><div class="form-group full">
												<label for="entry-reminder-text">{{ $texts.reminders.titleField() }}</label>
												<NcInputField id="entry-reminder-text" v-model="entry.reminderText" class="cobudget-native-field" label-outside maxlength="255" :aria-label="$texts.reminders.titleField()" />
											</div></div>
											<div class="form-row"><div class="form-group full">
												<label for="entry-reminder-description">{{ $texts.reminders.description() }}</label>
												<NcTextArea id="entry-reminder-description" v-model="entry.reminderDetails.description" class="cobudget-native-field" :label="$texts.reminders.description()" label-outside :rows="4" maxlength="10000" />
											</div></div>
											<div class="form-row"><div class="form-group full">
												<div id="entry-reminder-labels-heading" class="entry-group-label">{{ $texts.reminders.labels() }}</div>
												<div class="tags-toggles" role="group" aria-labelledby="entry-reminder-labels-heading">
													<label v-for="label in ['cancel', 'renew', 'review', 'pay']" :key="label" class="tag-toggle">
														<input v-model="entry.reminderDetails.labels" type="checkbox" :value="label" />
														<span class="tag-btn"><NavigationIcon :name="label" :size="16" />{{ $texts.reminders[label]() }}</span>
													</label>
												</div>
											</div></div>
										</fieldset>
										<div v-if="entry.reminderDetails.id && entry.hasReminder" class="form-row reminder-navigation-row">
											<NcButton variant="tertiary" alignment="center-reverse" :disabled="loading || isInitializingEntry || !!reminderLoadError" @click="openReminderOverview"><template #icon><ArrowRightIcon :size="20" /></template>{{ $texts.reminders.showReminder() }}</NcButton>
										</div>
									</div>
								</div>
							</div>
						</div>
					</section>

					<NcButton v-if="isEditing" class="entry-history-button" type="button" variant="tertiary"
						:disabled="loading || isInitializingEntry" @click="openPaymentHistory">
						<template #icon><HistoryIcon :size="20" /></template>
						{{ $texts.entry.showHistory() }}
					</NcButton>
				</div>

				<div class="form-actions">
					<NcNoteCard v-if="formError" type="error" class="entry-save-error" :text="formError" />
					<ModalActions
						flush
						danger-row
						inline-mobile
						:primary-label="saveActionLabel"
						:primary-disabled="isSaveDisabled"
						:primary-busy="loading"
						primary-type="button"
						:cancel-disabled="loading"
						:primary-busy-label="saveActionBusyLabel"
						@primary="saveEntry"
						@cancel="requestCloseSidebar">
						<template v-if="canDeleteEntry" #left>
							<DisabledActionHint :reason="loading ? $texts.common.waitBusy() : ''" :label="$texts.common.delete()" ><NcButton
								class="entry-delete-button cobudget-native-button"
								type="button"
								variant="error"
								:aria-label="$texts.common.delete()"
								:title="$texts.common.delete()"
								:disabled="loading"
								@click="deleteEntry">
								<template #icon><DeleteOutlineIcon :size="20" /></template>
							</NcButton></DisabledActionHint>
						</template>
					</ModalActions>
				</div>
			</form>
	</NcAppSidebar>
	<DialogTransition :show="paymentHistoryOpen" v-slot="{ returnFocus }">
		<EntryHistoryModal :container="null" :set-return-focus="returnFocus" :history="paymentHistoryRows"
			:loading="paymentHistoryLoading" :error="paymentHistoryError" @retry="openPaymentHistory" @close="closePaymentHistory" />
	</DialogTransition>
	<DialogTransition :show="!!(confirmDialog)" v-slot="{ returnFocus }">
		<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog payment-confirm-dialog"
			:name="confirmDialog.title" :no-close="confirmDialog.busy" :close-on-click-outside="!confirmDialog.busy"
			@closing="resolveConfirm(false)">
			<p class="payment-confirm-message">{{ confirmDialog.message }}</p>
			<NcNoteCard v-if="confirmDialog.error" type="error" :text="confirmDialog.error" />
			<template #actions>
				<NcButton variant="tertiary" :disabled="confirmDialog.busy" @click="resolveConfirm(false)">{{ $texts.common.cancel() }}</NcButton>
				<NcButton :variant="confirmDialog.confirmVariant === 'danger' ? 'error' : 'primary'" :disabled="confirmDialog.busy" @click="resolveConfirm(true)">
					<template v-if="confirmDialog.busy" #icon><NcLoadingIcon :size="20" /></template>
					{{ confirmDialog.busy ? $texts.common.waitBusy() : confirmDialog.confirmLabel }}
				</NcButton>
			</template>
		</NcDialog>
	</DialogTransition>
</template>

<script>
import EntryHistoryModal from './EntryHistoryModal.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import DialogTransition from './DialogTransition.vue'
import EntrySidebarHeading from './EntrySidebarHeading.vue'
import EntryTypeField from './EntryTypeField.vue'
import { captureDialogFocus } from '../services/dialogFocus'
import DisabledActionHint from './DisabledActionHint.vue'
import { getAppLocale } from '../utils/formatMoney'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import CategoryIcon from './CategoryIcon.vue'
import NavigationIcon from './NavigationIcon.vue'
import ModalActions from './ModalActions.vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { extractError, showRequestError, showToast } from '../services/notifications'
import { readWorkspaceId } from '../services/workspaceStorage'
import { getAreaSelectionPalette } from '../utils/areaColor'
import { sortAreasByName } from '../utils/areaSorting'
import { categoryParentId, sortCategoriesHierarchically } from '../utils/categoryHierarchy'
import { localDateKey, millisecondsUntilNextLocalDay, shouldRefreshDefaultEntryDate } from '../utils/entryDefaultDate'
import { hasEntryFormChanges, snapshotEntryForm } from '../utils/entryFormState'
import EntryLookupSelect from './EntryLookupSelect.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import BellOutlineIcon from 'vue-material-design-icons/BellOutline.vue'
import AreaIcon from './AreaIcon.vue'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import MemberIdentity from './MemberIdentity.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'

const normalizedPositiveId = value => {
	const id = Number(value || 0)
	return Number.isInteger(id) && id > 0 ? id : null
}

export default {
	name: 'EntrySidebar',
	components: { NcTextArea, ArrowRightIcon, EntryHistoryModal, HistoryIcon, BellOutlineIcon, EntrySidebarHeading, EntryTypeField, DialogTransition, DisabledActionHint, NcDialog, NcNoteCard, NcLoadingIcon, EntryLookupSelect, NcInputField, ChevronRightIcon, NcSelect, MemberIdentity, AccountMultipleIcon, CategoryIcon, NavigationIcon, ModalActions, CloseIcon, ContentSaveIcon, DeleteOutlineIcon, ChevronDownIcon, PaperclipIcon, AreaIcon, NcAppSidebar, NcButton },
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
		const now = new Date();
		return {
			isOpen: false,
			pendingInitialFocus: false,
			sidebarHasEntered: false,
			isFutureContext: false,
			showRecurrenceDetails: false,
			loading: false,
			showReminderDetails: false,
			reminderLoadError: '',
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
				date: now,
				recurrenceInterval: 'none',
				recurrenceMultiplier: 1,
				recurrenceEndDate: null,
				isSubscription: false,
				isFixedCost: false,
				isChildRelated: false,
				isImportant: false,
				needsReview: false,
				isTaxRelevant: false,
				isInsurance: false,
				hasReminder: false,
				reminderDate: null,
				reminderText: '',
				reminderDetails: { id: null, revision: 0, description: '', labels: [], completed: false, archived: false }
			},
			projects: [],
			categories: [],
			paymentPartners: [],
			internalEditingEntry: null,
			paymentHistoryEntryId: null,
			paymentHistoryOpen: false,
			paymentHistoryRows: [],
			paymentHistoryLoading: false,
			paymentHistoryError: '',
			paymentHistoryRequest: 0,
			isDuplicateMode: false,
			preferAreaDropdown: false,
			newEntryDefaultType: 'expense',
			isInitializingEntry: false,
			originalProjectId: null,
			showAttachmentList: false,
			confirmDialog: null,
			formError: '',
			projectsError: '',
			lookupsError: '',
			membersError: '',
			receiptsError: '',
			focusedLookupField: null,
			lookupDraft: {
				category: '',
				paymentPartner: ''
			},
			attachments: [],
			pendingAttachments: [],
			attachmentsLoading: false,
			modalHistoryToken: null,
			closedByHistory: false,
			ignoreNextPopState: false,
			ignorePopStateTimer: null,
			wideViewportMedia: null,
			isWideViewport: false,
			mobileHeaderViewportMedia: null,
			isMobileHeaderViewport: false,
			mobileFormFieldFocused: false,
			mobileFocusTimer: null,
			currentDateKey: localDateKey(now),
			defaultDateRefreshTimer: null,
			categorySelectionSource: null,
			categorySuggestionRequestId: 0,
			entryFormBaseline: ''
		}
	},
	computed: {
		sidebarTitle() {
			if (this.isWideViewport) return this.modalTitle;
			if (this.isEditing) return this.$texts.entry.editPayment();
			if (this.isDuplicateMode) return this.$texts.entry.copyPayment();
			if (this.isFutureContext) return this.$texts.entry.planPayment();
			return this.$texts.areaDetail.newPayment();
		},
		modalTitle() {
			return this.entryTypeTitle(this.entry.type);
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
				{ value: 'expense', label: this.entryTypeTitle('expense') },
				{ value: 'income', label: this.entryTypeTitle('income') }
			];
		},
		entryTypeChoice() {
			return this.entryTypeOptions.find(option => option.value === this.entry.type);
		},
		paymentPartnerLabel() {
			return this.entry.type === 'income' ? this.$texts.entry.receivedFrom() : this.$texts.entry.paidTo();
		},
		hasAvailableTags() {
			const hasGeneralTags = this.$enableChildRelated
				|| this.$enableImportantPayments
				|| this.$enableReviewPayments
				|| this.$enableTaxRelevant
				|| this.$enableInsurance;

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
		categoryLookupSelection() {
			return this.selectedCategory || (this.hasCategorySelection ? { id: this.selectedCategoryId, name: this.entry.categoryName } : null);
		},
		paymentPartnerLookupSelection() {
			return this.selectedPaymentPartner() || (this.entry.paymentPartnerName ? { name: this.entry.paymentPartnerName } : null);
		},
		isEditing() {
			return !!this.internalEditingEntry;
		},
		isReleasedPersonal() {
			const original = this.internalEditingEntry;
			return this.isEditing && original?.entry_kind === 'personal' && !original.is_locked
				&& !!(original.settlement_id || original.settled_at);
		},
		isAreaAssignmentLocked() {
			return this.isReleasedPersonal || (this.isEditing && this.internalEditingEntry?.entry_kind === 'shared');
		},
		canDeleteEntry() {
			return this.isEditing && !!this.entry.id && this.internalEditingEntry.can_delete !== false;
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
				|| !!this.reminderLoadError
				|| this.invalidPaymentReminder
				|| (this.isEditing && !this.hasUnsavedChanges);
		},
		showMobileHeaderSave() {
			return this.isOpen && this.isMobileHeaderViewport && !this.confirmDialog;
		},
		activeProjects() {
			if (!this.$enableProjects) {
				return [];
			}
			return sortAreasByName(this.projects.filter(p => !p.is_archived));
		},
		useDirectAreaSelection() {
			return !this.preferAreaDropdown && this.activeProjects.length <= 5;
		},
		areaSelectionOptions() {
			if (this.isAreaAssignmentLocked) {
				const original = this.internalEditingEntry;
				const project = this.projects.find(item => Number(item.id) === Number(original.project_id));
				return [{ key: original.project_id ? `area-${original.project_id}` : 'personal',
					id: original.project_id || null,
					name: project?.name || original.project_name || this.$texts.entry.personalAssignment(),
					color: project?.color || 'var(--color-primary-element, #00679e)' }];
			}
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
		areaSelectionChoice: {
			get() {
				return this.areaSelectionOptions.find(option => this.isAreaSelected(option.id)) || null;
			},
			set(option) {
				if (this.loading || this.isInitializingEntry || !option) return;
				const selected = this.areaSelectionOptions.find(item => item.key === option.key && item.id === option.id);
				if (selected) this.selectArea(selected.id);
			}
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
		projectPayerChoice: {
			get() {
				return this.projectPayerOptions.find(member => member.id === this.entry.userId) || null;
			},
			set(option) {
				const member = this.projectPayerOptions.find(member => member.id === option?.id);
				if (!this.isReleasedPersonal && !this.loading && member && this.isSelectableProjectMember(member)) this.entry.userId = member.id;
			}
		},
		projectSplitChoices() {
			return [
				{ value: 'project_shares', label: this.$texts.entry.projectShares(), isActive: true, isFormer: false },
				...this.projectSplitOptions.map(member => ({
					...member,
					value: `single_user:${member.id}`,
					label: this.$texts.entry.singleUserSplitTarget(member.displayName)
				}))
			];
		},
		projectSplitChoice: {
			get() {
				return this.projectSplitChoices.find(option => option.value === this.splitModeChoice) || null;
			},
			set(value) {
				const option = this.projectSplitChoices.find(option => option.value === value?.value);
				if (!this.isReleasedPersonal && !this.loading && option && this.isSelectableProjectMember(option)) this.splitModeChoice = option.value;
			}
		},
		showProjectPayerSelect() {
			return !this.isReleasedPersonal && this.$enableSharedProjects && !!this.entry.projectId && this.projectPayerOptions.length > 1;
		},
		showProjectSplitMode() {
			return !this.isReleasedPersonal && this.$enableSharedProjects
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
			d.setHours(6, 0, 0, 0);
			// The already saved future first payment precedes its next recurrence.
			if (baseDate > new Date()) {
				d.setTime(baseDate.getTime());
				d.setHours(6, 0, 0, 0);
			}

			const endDate = this.entry.recurrenceEndDate ? new Date(this.entry.recurrenceEndDate) : null;
			endDate?.setHours(23, 59, 59, 999);
			if (endDate && d > endDate) {
				return null; // Stop if past end date
			}
			
			const dateStr = d.toLocaleDateString(getAppLocale(), { day: '2-digit', month: '2-digit', year: 'numeric' });
			const timeStr = d.toLocaleTimeString(getAppLocale(), { hour: '2-digit', minute: '2-digit' });
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
					const d = this.fromDateInputValue(val, 12, 0, 0);
					d.setHours(12, 0, 0, 0);
					this.entry.date = d;
				}
			}
		},
		recurrenceIntervalOptions() {
			return ['none', 'day', 'week', 'month'].map((value, index) => ({
				value,
				label: [this.$texts.entry.neverOnce, this.$texts.entry.days, this.$texts.entry.weeks, this.$texts.entry.months][index]()
			}));
		},
		recurrenceIntervalChoice: {
			get() { return this.recurrenceIntervalOptions.find(option => option.value === this.entry.recurrenceInterval); },
			set(option) {
				if (this.loading || this.isInitializingEntry || !this.recurrenceIntervalOptions.some(item => item.value === option?.value)) return;
				this.entry.recurrenceInterval = option.value;
			}
		},
		recurrenceMultiplierValue: {
			get() { return String(this.entry.recurrenceMultiplier ?? ''); },
			set(value) {
				const number = parseFloat(value);
				this.entry.recurrenceMultiplier = Number.isNaN(number) ? value : number;
			}
		},
		invalidPaymentReminder() {
			if (this.$enableReminders === false) return false;
			const details = this.entry.reminderDetails || {};
			// The date enables the reminder; clearing it removes it on Save.
			// Keep text/labels in the draft so an interrupted date edit does not erase them.
			if (!this.entry.hasReminder || !this.entry.reminderDate) return false;
			// Existing untitled reminders remain editable without requiring a new title.
			const legacyTitle = details.id && !this.internalEditingEntry?.reminder_text;
			return !this.entry.reminderText?.trim() && !legacyTitle;
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
				const next = this.fromDateInputValue(val, 9, 0, 0);
				if (next?.getTime() !== this.entry.reminderDate?.getTime() && this.entry.reminderDetails) {
					this.entry.reminderDetails.completed = false;
					this.entry.reminderDetails.archived = false;
				}
				this.entry.reminderDate = next;
				this.entry.hasReminder = !!next;
			}
		},
		minFutureDate() {
			if (this.isFutureContext && !this.isEditing) {
				return this.currentDateKey;
			}
			return null;
		}
	},
	mounted() {
		// Data lists are now fetched when modal opens
		window.addEventListener('popstate', this.handleModalPopState);
		window.addEventListener('focus', this.handleEntryDateResume);
		document.addEventListener('visibilitychange', this.handleEntryDateVisibilityChange);
		if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
			this.wideViewportMedia = window.matchMedia('(min-width: 1025px)');
			this.updateWideViewport(this.wideViewportMedia);
			this.wideViewportMedia.addEventListener?.('change', this.updateWideViewport);
			this.mobileHeaderViewportMedia = window.matchMedia('(max-width: 768px)');
			this.updateMobileHeaderViewport(this.mobileHeaderViewportMedia);
			this.mobileHeaderViewportMedia.addEventListener?.('change', this.updateMobileHeaderViewport);
		}
		this.scheduleDefaultDateRefresh();
	},
	beforeUnmount() {
		window.removeEventListener('popstate', this.handleModalPopState);
		window.removeEventListener('focus', this.handleEntryDateResume);
		document.removeEventListener('visibilitychange', this.handleEntryDateVisibilityChange);
		this.wideViewportMedia?.removeEventListener?.('change', this.updateWideViewport);
		this.mobileHeaderViewportMedia?.removeEventListener?.('change', this.updateMobileHeaderViewport);
		if (this.ignorePopStateTimer) {
			window.clearTimeout(this.ignorePopStateTimer);
			this.ignorePopStateTimer = null;
		}
		this.clearDefaultDateRefreshTimer();
		this.clearMobileFocusTimer();
		this.closePaymentHistory();
		this.releaseModalHistory({ skipBack: true });
	},
	watch: {
		isOpen(open) { if (!open) this.closePaymentHistory() },
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
		paymentDateForSave() {
			const date = new Date(this.entry.date);
			// Do not move already recorded payments when merely editing their recurrence.
			if (this.entry.recurrenceInterval !== 'none' && date > new Date()) date.setHours(6, 0, 0, 0);
			return Math.floor(date.getTime() / 1000);
		},
		async openPaymentHistory() {
			if (!this.isEditing || !this.paymentHistoryEntryId || !this.isOpen) return
			const request = ++this.paymentHistoryRequest
			this.paymentHistoryOpen = true
			this.paymentHistoryLoading = true
			this.paymentHistoryRows = []
			this.paymentHistoryError = ''
			try {
				const { data } = await axios.get(generateUrl(`/apps/cobudget/api/entries/${this.paymentHistoryEntryId}/history`))
				if (request === this.paymentHistoryRequest) this.paymentHistoryRows = data.history || []
			} catch (error) {
				if (request === this.paymentHistoryRequest) this.paymentHistoryError = extractError(error, this.$texts.entry.historyFetchError())
			} finally {
				if (request === this.paymentHistoryRequest) this.paymentHistoryLoading = false
			}
		},
		closePaymentHistory() {
			this.paymentHistoryRequest++
			this.paymentHistoryOpen = false
			this.paymentHistoryLoading = false
			this.paymentHistoryError = ''
			this.paymentHistoryRows = []
		},
		entryTypeTitle(type) {
			const isIncome = type === 'income';
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
				? this.$texts.entry.createIncome()
				: this.$texts.entry.createExpense();
		},
		handleEntryDateResume() {
			this.refreshDefaultEntryDate();
		},
		handleEntryDateVisibilityChange() {
			if (!document.hidden) {
				this.refreshDefaultEntryDate();
			}
		},
		clearDefaultDateRefreshTimer() {
			if (this.defaultDateRefreshTimer) {
				window.clearTimeout(this.defaultDateRefreshTimer);
				this.defaultDateRefreshTimer = null;
			}
		},
		scheduleDefaultDateRefresh(now = new Date()) {
			this.clearDefaultDateRefreshTimer();
			this.defaultDateRefreshTimer = window.setTimeout(() => {
				this.defaultDateRefreshTimer = null;
				this.refreshDefaultEntryDate();
			}, millisecondsUntilNextLocalDay(now) + 100);
		},
		refreshDefaultEntryDate(now = new Date()) {
			this.currentDateKey = localDateKey(now);

			if (!this.isInitializingEntry && shouldRefreshDefaultEntryDate({
				isCreatingNewEntry: this.isCreatingNewEntry,
				hasUnsavedChanges: this.hasUnsavedChanges,
				entryDate: this.entry.date,
				now,
			})) {
				this.entry.date = now;
				this.markEntryClean();
			}

			this.scheduleDefaultDateRefresh(now);
		},
		updateWideViewport(event) {
			this.isWideViewport = !!event?.matches;
		},
		updateMobileHeaderViewport(event) {
			this.isMobileHeaderViewport = !!event?.matches;
		},
		toggleAttachmentList() {
			if (this.loading || this.isInitializingEntry) return;
			if (!this.hasAttachments) {
				this.$refs.attachmentInput.click();
				return;
			}
			this.showAttachmentList = !this.showAttachmentList;
			this.revealPlanningDetails('attachmentsGroup', this.showAttachmentList);
		},
		toggleRecurrenceDetails() {
			if (this.loading || this.isInitializingEntry) return;
			this.showRecurrenceDetails = !this.showRecurrenceDetails;
			this.revealPlanningDetails('recurrenceGroup', this.showRecurrenceDetails);
		},
		toggleReminderDetails() {
			if (this.loading || this.isInitializingEntry) return;
			this.showReminderDetails = !this.showReminderDetails;
			this.revealPlanningDetails('reminderGroup', this.showReminderDetails);
		},
		async loadPaymentReminder() {
			if (this.$enableReminders === false) return;
			const entry = this.entry;
			if (!entry.id) return;
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/reminders'), { params: { entryId: entry.id }, skipWorkspaceHeader: true });
				if (this.entry !== entry) return;
				const row = response.data.reminders.find(item => item.entry_id === entry.id);
				if (row) {
					entry.reminderText = row.title;
					entry.reminderDate = new Date(row.remind_at * 1000);
					entry.hasReminder = true;
					entry.reminderDetails = { id: row.id, revision: row.revision, description: row.description || '', labels: row.labels.filter(label => ['cancel', 'renew', 'review', 'pay'].includes(label)), completed: !!row.completed_at, archived: row.status === 'archived' };
				} else {
					entry.reminderDetails = { id: null, revision: 0, description: '', labels: [], completed: false, archived: false };
					entry.hasReminder = false;
					entry.reminderDate = null;
					entry.reminderText = '';
				}
				this.reminderLoadError = '';
			} catch (error) {
				if (this.entry === entry) this.reminderLoadError = extractError(error, this.$texts.reminders.loadError());
			}
		},
		async retryPaymentReminder() {
			if (this.loading || this.isInitializingEntry) return;
			this.isInitializingEntry = true;
			await this.loadPaymentReminder();
			this.isInitializingEntry = false;
		},
		async revealPlanningDetails(group, enabled) {
			if (!enabled || this.isInitializingEntry) return;
			await this.$nextTick();
			const section = this.$refs[group];
			const body = this.$refs.modalBody;
			const stillEnabled = group === 'recurrenceGroup' ? this.showRecurrenceDetails : group === 'reminderGroup' ? this.showReminderDetails : this.showAttachmentList;
			if (!this.isOpen || !stillEnabled || !section || !body) return;
			const inset = parseFloat(window.getComputedStyle(body).paddingTop) || 0;
			const bounds = section.getBoundingClientRect();
			const viewport = body.getBoundingClientRect();
			const delta = Math.min(bounds.bottom - viewport.bottom + inset, bounds.top - viewport.top - inset);
			if (delta <= 0) return;
			// Keep focus on the switch; scroll only the form, without opening a keyboard.
			body.scrollTo({ top: body.scrollTop + delta, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
		},
		areaChoiceStyle(option) {
			if (!this.isAreaSelected(option.id)) {
				return {};
			}

			// CSS theme colors cannot be parsed by the hex-only area palette.
			// Resolve the default in CSS so it follows Nextcloud's active accent.
			if (!/^#?(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(option.color || ''))) {
				return {
					'--area-choice-selected-background': 'var(--cobudget-primary)',
					'--area-choice-selected-text': 'var(--cobudget-primary-text)',
					'--area-choice-selected-border': 'var(--cobudget-primary)',
					'--area-choice-selected-hover': 'var(--cobudget-primary-hover)'
				};
			}

			const palette = getAreaSelectionPalette(option.color);
			return {
				'--area-choice-selected-background': palette.background,
				'--area-choice-selected-text': palette.foreground,
				'--area-choice-selected-border': palette.background,
				'--area-choice-selected-hover': palette.hover
			};
		},
		isAreaSelected(projectId) {
			if (projectId === null) {
				return !this.entry.projectId;
			}

			return Number(this.entry.projectId) === Number(projectId);
		},
		selectArea(projectId) {
			if (this.isAreaAssignmentLocked || this.loading || this.isInitializingEntry) return;
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
		openConfirm({ title, message, confirmLabel, confirmVariant = 'primary', action = null, errorMessage = '' }) {
			if (this.confirmDialog || this.loading) return Promise.resolve(false);
			return new Promise(resolve => {
				this.confirmDialog = {
					title,
					message,
					confirmLabel,
					confirmVariant, action, errorMessage, busy: false, error: '',
					resolve
				};
			});
		},
		async resolveConfirm(confirmed) {
			const dialog = this.confirmDialog;
			if (!dialog || dialog.busy) return;
			if (confirmed && dialog.action) {
				dialog.busy = true;
				dialog.error = '';
				this.loading = true;
				try {
					await dialog.action();
				} catch (error) {
					if (this.confirmDialog === dialog) dialog.error = extractError(error, dialog.errorMessage);
					return;
				} finally {
					dialog.busy = false;
					this.loading = false;
				}
			}
			if (this.confirmDialog === dialog) this.confirmDialog = null;
			dialog.resolve(confirmed);
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
		async openReminderOverview() {
			if (this.$enableReminders === false) return;
            const id = this.entry.id;
            if (!(await this.confirmDiscardChanges())) return;
            await this.$router.push({ name: 'reminders', query: { entry: String(id) } });
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
			this.refreshDefaultEntryDate();
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
			if (!this.isOpen) return;
			this.sidebarHasEntered = true;
			this.focusSidebarWhenReady();
		},
		focusSidebarWhenReady() {
			if (!this.isOpen || !this.sidebarHasEntered || this.isInitializingEntry || !this.pendingInitialFocus) return;
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
		focusAmountInput() {
			this.$refs.amountInput?.focus({ preventScroll: true });
		},
		focusLookup(field) {
			this.openLookup(field);
			this.$nextTick(() => this.lookupTrigger(field)?.focus({ preventScroll: true }));
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
		selectEntryType(option) {
			if (this.loading || this.isInitializingEntry || !this.entryTypeOptions.some(item => item.value === option?.value) || this.entry.type === option.value) return;
			this.setEntryType(option.value);
			// Let NcSelect finish closing before moving focus out of its native menu.
			this.$nextTick(() => {
				if (this.isOpen && !this.loading && !this.isInitializingEntry && this.entry.type === option.value) {
					this.focusAmountInput();
				}
			});
		},
		closeTypeMenu() {
			// Blurring the native select also dismisses its teleported list on sidebar close.
			this.$refs?.paymentTypeSelect?.$el?.querySelector('input')?.blur();
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
		isSelectableProjectMember(member) {
			return !member.isFormer && member.isActive;
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
			this.membersError = '';
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
				this.membersError = extractError(e, this.$texts.entry.areaMembersLoadError());
			}
		},
		syncEntryUserWithProject(preferredUserId = null) {
			if (this.isReleasedPersonal) {
				this.entry.userId = this.internalEditingEntry.user_id;
				this.entry.splitMode = 'single_user';
				this.entry.splitUserId = this.internalEditingEntry.user_id;
				return;
			}
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
			if (this.isReleasedPersonal) return 'single_user';
			return this.entry.projectId ? (this.entry.splitMode || 'project_shares') : 'project_shares';
		},
		entrySplitUserIdForSave() {
			if (this.isReleasedPersonal) return this.internalEditingEntry.user_id;
			if (this.entrySplitModeForSave() !== 'single_user') {
				return null;
			}

			return this.entry.splitUserId || this.entry.userId || this.currentUserId();
		},
		lookupSelectSections(field, query) {
			return this.buildSuggestionSections(this.lookupItems(field), query,
				field === 'category' ? this.$texts.entry.allCategories() : this.$texts.entry.allPaymentPartners(),
				field === 'category' ? ['code', 'parent_name'] : ['number'], field === 'category');
		},
		handleNativeLookupClose(field) {
			if (this.focusedLookupField === field) this.focusedLookupField = null;
		},
		selectNativeLookup(field, item) {
			if (this.loading || this.isInitializingEntry) return;
			if (!item) this.clearLookupValue(field);
			else if (field === 'category') this.selectCategorySuggestion(item);
			else this.selectPaymentPartnerSuggestion(item);
		},
		createNativeLookup(field, name) {
			if (this.loading || this.isInitializingEntry) return;
			this.lookupDraft[field] = name;
			this.applyLookupDraft(field);
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
		lookupTrigger(field) {
			return field === 'category' ? this.$refs.categoryLookupTrigger : this.$refs.paymentPartnerLookupTrigger;
		},
		openLookup(field) {
			this.closeTypeMenu(false);
			this.focusedLookupField = field;
			this.lookupDraft = { category: '', paymentPartner: '' };
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
		closeLookup() {
			this.$refs?.categoryLookupTrigger?.reset();
			this.$refs?.paymentPartnerLookupTrigger?.reset();
			this.focusedLookupField = null;
			this.lookupDraft = { category: '', paymentPartner: '' };
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
			this.$nextTick(() => this.lookupTrigger(field)?.focus({ preventScroll: true }));
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
		async openSidebar(entryToEdit = null, defaultProjectId = null, isFutureContext = false, entryToDuplicate = null, defaultType = 'expense', preferAreaDropdown = false, reuseLoadedData = false, historyEntryId = null) {
			this.closePaymentHistory();
			this.paymentHistoryEntryId = entryToEdit ? (historyEntryId || entryToEdit.id) : null;
			this.formError = '';
			this.projectsError = '';
			this.lookupsError = '';
			this.membersError = '';
			const openedAt = new Date();
			this.currentDateKey = localDateKey(openedAt);
			this.scheduleDefaultDateRefresh(openedAt);
			const canFocusImmediately = this.isOpen && !this.pendingInitialFocus;
			if (!this.isOpen) {
				this.restoreSidebarFocus = captureDialogFocus(document);
				this.sidebarHasEntered = false;
			}
			if (canFocusImmediately) this.sidebarHasEntered = true;
			this.invalidateCategorySuggestion(true);
			this.categorySelectionSource = null;
			this.closeTypeMenu(false);
			this.isOpen = true;
			this.pendingInitialFocus = true;
			this.closedByHistory = false;
			this.pushModalHistory();
			this.isFutureContext = isFutureContext;
			this.isDuplicateMode = !!entryToDuplicate && !entryToEdit;
			this.preferAreaDropdown = !!preferAreaDropdown;
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
					description: entryToEdit.description || '',
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
					date: entryToEdit.date ? new Date(entryToEdit.date * 1000) : openedAt,
					recurrenceInterval: entryToEdit.recurrence_interval || 'none',
					recurrenceMultiplier: entryToEdit.recurrence_multiplier || 1,
					recurrenceEndDate: entryToEdit.recurrence_end_date ? new Date(entryToEdit.recurrence_end_date * 1000) : null,
					isSubscription: !!entryToEdit.is_subscription,
					isFixedCost: !!entryToEdit.is_fixed_cost,
					isChildRelated: !!entryToEdit.is_child_related,
					isImportant: !!entryToEdit.is_important,
					needsReview: !!entryToEdit.needs_review,
					isTaxRelevant: !!entryToEdit.is_tax_relevant,
					isInsurance: !!entryToEdit.is_insurance,
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
					date: openedAt,
					recurrenceInterval: 'none',
					recurrenceMultiplier: 1,
					recurrenceEndDate: null,
					isSubscription: !!entryToDuplicate.is_subscription,
					isFixedCost: !!entryToDuplicate.is_fixed_cost,
					isChildRelated: !!entryToDuplicate.is_child_related,
					isImportant: !!entryToDuplicate.is_important,
					needsReview: !!entryToDuplicate.needs_review,
					isTaxRelevant: !!entryToDuplicate.is_tax_relevant,
					isInsurance: !!entryToDuplicate.is_insurance,
					hasReminder: !!entryToDuplicate.reminder_date,
					reminderDate: entryToDuplicate.reminder_date ? openedAt : null, // Assuming user will reset it if needed
					reminderText: entryToDuplicate.reminder_text || ''
				};
			} else {
				this.internalEditingEntry = null;
				const defaultDate = openedAt;
				
				let defaultIsSubscription = false;
				let defaultIsFixedCost = false;
				let defaultIsChildRelated = false;
				let defaultIsImportant = false;
				let defaultNeedsReview = false;
				let defaultIsTaxRelevant = false;
				let defaultIsInsurance = false;
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
				} else if (this.$route && this.$route.query.filter === 'insurance') {
					defaultIsInsurance = true;
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
					isInsurance: defaultIsInsurance,
					hasReminder: false,
					reminderDate: null,
					reminderText: ''
				};
			}
			this.entry.reminderDetails = { id: null, revision: 0, description: '', labels: [], completed: false, archived: false };
			this.reminderLoadError = '';
			this.showReminderDetails = false;
			if (entryToEdit) await this.loadPaymentReminder();
			this.showRecurrenceDetails = false;
			this.emitSidebarMode();

			const sourceEntry = entryToEdit || entryToDuplicate || null;
			if (reuseLoadedData) {
				this.normalizeNewEntryArea();
				this.syncLookupNamesFromIds(sourceEntry);
				this.syncEntryUserWithProject(this.entry.userId);
				this.isInitializingEntry = false;
				this.markEntryClean();
				this.focusSidebarWhenReady();
				return;
			}

			// Projects are needed before the project-scoped category/contact list can be resolved.
			await this.fetchProjects();
			this.normalizeNewEntryArea();
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
			this.markEntryClean();
			this.focusSidebarWhenReady();
		},
		focusInitialField() {
			this.$nextTick(() => {
				if (!this.isOpen || !this.sidebarHasEntered || this.isInitializingEntry || this.loading || this.confirmDialog) return;
				const target = this.$refs.amountInput;
				if (!target || target.disabled || target.readOnly) {
					return;
				}
				// Reused editors can still be scrolled down from the last payment.
				if (this.$refs.modalBody) this.$refs.modalBody.scrollTop = 0;
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
			this.sidebarHasEntered = false;
			this.pendingInitialFocus = false;
			this.closeTypeMenu(false);
			this.closeLookup();
			this.clearMobileFocusTimer();
			this.entryFormBaseline = '';
			this.emitSidebarMode();
			if (wasOpen) {
				this.$emit('closed', { userInitiated });
			}
		},
		handleSidebarClosed() {
			// NcAppSidebar owns the slide and reports when its last frame is done.
			// A cancelled leave must not clear a newly opened payment's form.
			if (this.isOpen) return;
			this.internalEditingEntry = null;
			this.isDuplicateMode = false;
			this.showAttachmentList = false;
			this.mobileFormFieldFocused = false;
			this.resetAttachments();
			const restoreFocus = this.restoreSidebarFocus;
			this.restoreSidebarFocus = null;
			// Run after the native focus trap has finished its own deactivation.
			this.$nextTick(() => { if (!this.isOpen) restoreFocus?.(); });
		},
		resetAttachments() {
			this.showAttachmentList = false;
			this.receiptsError = '';
			this.attachments = [];
			this.pendingAttachments = [];
			this.attachmentsLoading = false;
			if (this.$refs.attachmentInput) {
				this.$refs.attachmentInput.value = '';
			}
		},
		async fetchAttachments(entryId) {
			this.receiptsError = '';
			if (!entryId || !this.$enableReceipts) {
				this.attachments = [];
				return;
			}

			this.attachmentsLoading = true;
			try {
				const response = await axios.get(generateUrl(`/apps/cobudget/api/entries/${entryId}/attachments`));
				this.attachments = response.data?.attachments || [];
			} catch (e) {
				this.receiptsError = extractError(e, this.$texts.entry.receiptsLoadError());
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
			this.showAttachmentList = true;
			this.revealPlanningDetails('attachmentsGroup', true);
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
			if (!this.entry.id || !attachment?.id || this.loading || this.confirmDialog) {
				return;
			}
			const entryId = this.entry.id;
			const attachmentId = attachment.id;
			const confirmed = await this.openConfirm({
				title: this.$texts.entry.receiptRemoveTitle(),
				message: this.$texts.entry.receiptRemoveMessage(),
				confirmLabel: this.$texts.entry.removeReceipt(),
				confirmVariant: 'danger',
				errorMessage: this.$texts.entry.receiptRemoveError(),
				action: () => axios.delete(generateUrl(`/apps/cobudget/api/entries/${entryId}/attachments/${attachmentId}`))
			});
			if (!confirmed) {
				this.consumeHistoryClose();
				return;
			}

			if (this.entry.id === entryId) {
				this.attachments = this.attachments.filter(item => Number(item.id) !== Number(attachmentId));
			}
			showToast(this.$texts.entry.receiptRemoved());
		},
		normalizeNewEntryArea() {
			if (this.isEditing || this.projectsError || this.activeProjects.length > 0) return;
			this.entry.projectId = null;
			this.entry.userId = this.currentUserId();
			this.entry.splitMode = 'project_shares';
			this.entry.splitUserId = null;
		},
		async fetchProjects() {
			this.projectsError = '';
			if (!this.$enableProjects) {
				this.projects = []
				return
			}
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/projects'))
				this.projects = response.data || []
			} catch (e) {
				this.projectsError = extractError(e, this.$texts.entry.areasLoadError());
				this.projects = []
			}
		},
		async fetchDataLists(projectId = this.entry.projectId) {
			this.lookupsError = '';
			try {
				const params = this.$enableProjects && projectId ? { projectId } : {};
				const catRes = await axios.get(generateUrl('/apps/cobudget/api/categories'), { params })
				this.categories = sortCategoriesHierarchically(catRes.data || [])
				const payRes = await axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params })
				this.paymentPartners = (payRes.data || []).sort((a, b) => a.name.localeCompare(b.name))
			} catch (e) {
				this.categories = [];
				this.paymentPartners = [];
				this.lookupsError = extractError(e, this.$texts.entry.lookupsLoadError());
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

		entryUserIdForSave() {
			if (this.isReleasedPersonal) return this.internalEditingEntry.user_id;
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
			// Numeric Date constructors map years 0–99 to 1900–1999. Preserve
			// the literal year while users type a native date field digit by digit.
			const d = new Date(0);
			d.setFullYear(year, month - 1, day);
			d.setHours(hours, minutes, seconds, 0);
			return Number.isNaN(d.getTime()) ? null : d;
		},
		async saveEntry() {
			if (this.isInitializingEntry || this.loading || this.confirmDialog || this.reminderLoadError || this.invalidPaymentReminder) {
				return;
			}
			if (this.isEditing && !this.hasUnsavedChanges) {
				return;
			}
			this.formError = '';
			this.evaluateAmount();
			if (!this.isValid) {
				this.formError = this.$texts.entry.validAmountRequired();
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
					this.formError = this.$texts.entry.futureDateRequired();
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
			const retainedAreaDropdown = this.preferAreaDropdown;
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
					date: this.paymentDateForSave(),
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
						d.setHours(6, 0, 0, 0);
						return Math.floor(d.getTime() / 1000);
					})() : null,
					isSubscription: isExpense && this.entry.isSubscription,
					isFixedCost: isExpense && this.entry.isFixedCost,
					isChildRelated: this.entry.isChildRelated,
					isImportant: this.entry.isImportant,
					needsReview: this.entry.needsReview,
					isTaxRelevant: this.entry.isTaxRelevant,
					isInsurance: this.entry.isInsurance,
					...(this.$enableReminders === false ? {} : {
					reminderDate: this.entry.hasReminder && this.entry.reminderDate ? (() => {
						const rd = new Date(this.entry.reminderDate);
						rd.setHours(9, 0, 0, 0);
						return Math.floor(rd.getTime() / 1000);
					})() : null,
					reminderNotified: false,
					reminderText: this.entry.hasReminder ? this.entry.reminderText : '',
					reminderDetails: this.entry.reminderDetails
					})
				};

				if (this.isEditing) {
					await axios.put(generateUrl(`/apps/cobudget/api/entries/${this.entry.id}`), payload);
					entryPersisted = true;
					await this.loadPaymentReminder();
					await this.uploadPendingAttachments(this.entry.id);
					this.markEntryClean();
					showToast(this.$texts.entry.entrySaved());
				} else {
					const response = await axios.post(generateUrl('/apps/cobudget/api/entries'), payload);
					if (response.data?.id) {
						this.entry.id = response.data.id;
					}
					entryPersisted = true;
					await this.loadPaymentReminder();
					await this.uploadPendingAttachments(this.entry.id);
					showToast(prepareNextEntry
						? this.$texts.entry.entrySaved()
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
							retainedAreaDropdown,
							true
						);
				}
			} catch (e) {
				const fallback = entryPersisted
						? this.$texts.entry.entrySavedReceiptUploadError()
						: wasEditing
						? this.$texts.entry.entrySaveError()
						: this.$texts.entry.entryCreateError();
				this.formError = extractError(e, fallback);
			}
			this.loading = false;
		},
		async deleteEntry() {
			if (!this.canDeleteEntry || this.loading || this.confirmDialog) return;
			const deletedEntryId = this.entry.id || null;
			const retainedProjectId = this.entry.projectId || null;
			const retainedFutureContext = this.isFutureContext;
			const stopOnly = retainedFutureContext && Number(this.internalEditingEntry.date || 0) <= Date.now() / 1000;
			const retainedAreaDropdown = this.preferAreaDropdown;
			const retainedDefaultType = this.newEntryDefaultType;
			const msg = stopOnly
				? this.$texts.entry.disableFuturePaymentMessage()
				: this.$texts.entry.deleteEntryMessage();
			const title = stopOnly ? this.$texts.entry.disableFuturePaymentTitle() : this.$texts.entry.deleteEntryTitle();

			const deleteId = Number(this.internalEditingEntry.editable_entry_id || this.internalEditingEntry.source_entry_id || deletedEntryId);
			const confirmed = await this.openConfirm({
				title,
				message: msg,
				confirmLabel: stopOnly ? this.$texts.entry.disablePayment() : this.$texts.entry.deleteEntry(),
				confirmVariant: 'danger',
				errorMessage: stopOnly ? this.$texts.entry.futurePaymentDisableError() : this.$texts.entry.entryDeleteError(),
				action: () => stopOnly
					? axios.post(generateUrl(`/apps/cobudget/api/entries/${deletedEntryId}/stop-recurrence`))
					: axios.delete(generateUrl(`/apps/cobudget/api/entries/${deleteId}`))
			});
			if (!confirmed) {
				return;
			}
			
			this.loading = true;
			try {
				showToast(stopOnly ? this.$texts.entry.futurePaymentDisabled() : this.$texts.entry.entryDeleted());
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
							retainedAreaDropdown,
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
.entry-history-button { display: none; }
@media (max-width: 768px) {
	.entry-history-button { display: inline-flex; }
}

.payment-confirm-message { margin: 0; white-space: pre-line; }
.entry-sidebar {
	--entry-sidebar-content-padding: 4px;
	--entry-sidebar-field-gap: calc(var(--default-grid-baseline, 4px) * 4);
	--entry-sidebar-control-radius: var(--cobudget-control-radius);
	--entry-sidebar-header-action-gap: var(--default-grid-baseline, 4px);
	--entry-sidebar-header-save-width: calc(var(--default-grid-baseline, 4px) * 15);
	overflow: hidden;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

/* Keep the native slide-right timing/easing and width calculation. */
.entry-sidebar--closing { pointer-events: none; }
@media (prefers-reduced-motion: reduce) {
	.entry-sidebar.slide-right-enter-active,
	.entry-sidebar.slide-right-leave-active { transition: none !important; }
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

.entry-sidebar :deep(.app-sidebar-header__mainname) {
	font-size: var(--default-font-size, 15px) !important;
	font-weight: var(--font-weight-element, 500);
	line-height: 1.5 !important;
	white-space: normal !important;
	overflow-wrap: anywhere;
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

.entry-notice {
	margin: 0 0 var(--entry-sidebar-field-gap);
	width: 100%;
	box-sizing: border-box;
}

.entry-save-error {
	margin: 0;
	width: 100%;
	max-height: 25dvh;
	overflow-y: auto;
	box-sizing: border-box;
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
	gap: var(--entry-sidebar-field-gap);
	margin-bottom: var(--entry-sidebar-field-gap);
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
	gap: var(--entry-sidebar-field-gap);
}

.modal-body { container-type: inline-size; container-name: entry-form; }

.entry-required-panel {
	margin-bottom: var(--entry-sidebar-field-gap);
	align-items: stretch;
	padding-top: var(--default-grid-baseline, 4px);
}

.planning-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 0;
}

.planning-section {
	/* Center 20 px option icons below the 16 px label icons (8 px inset). */
	--planning-row-inset: calc(var(--default-grid-baseline, 4px) * 2 - 2px);
	/* Match label text starts despite the 4 px wider option icons. */
	--planning-row-gap: calc(var(--default-grid-baseline, 4px) * 1.5 - 2px);
	--planning-row-hover: color-mix(in srgb, var(--color-primary-element) 8%, transparent);
	grid-column: 1 / -1;
	margin-top: var(--entry-sidebar-field-gap);
}
.planning-section:has(.button-vue--legacy34) {
	--planning-row-hover: var(--color-background-hover);
}

.planning-card .form-row:last-child { margin-bottom: 0; }
.planning-section .planning-switch {
	--nc-form-box-item-border-width: 0px;
	border: none;
	background: transparent;
	padding-inline: var(--planning-row-inset);
	transition-duration: var(--cobudget-hover-duration);
	transition-timing-function: linear;
}
.planning-section .planning-switch:hover:not(:has(:disabled)) {
	background: var(--planning-row-hover);
	color: var(--color-main-text);
}
.planning-section .planning-card { margin-bottom: 0; }
.planning-section .attachment-upload-btn {
	min-height: calc(var(--default-grid-baseline, 4px) * 10);
	padding: 0 var(--planning-row-inset);
	border: none;
}
.attachment-upload-btn :deep(.button-vue__wrapper) { gap: var(--planning-row-gap); }
.attachment-upload-btn :deep(.button-vue__icon) {
	width: 20px;
	min-width: 20px;
	height: 20px;
	min-height: 20px;
}
.attachment-upload-btn :deep(.button-vue__text) {
	display: flex;
	align-items: center;
	gap: var(--planning-row-gap);
	width: 100%;
	margin: 0;
	padding: 0;
}
.attachment-action-label { flex: 1; text-align: start; }
.planning-fields {
	min-width: 0;
	margin: 0;
	padding: var(--cobudget-content-gap);
	border: 0;
	border-radius: var(--cobudget-radius-md);
	background: color-mix(in srgb, var(--cobudget-surface) 70%, transparent);
}
.reminder-navigation-row { margin-block-start: var(--entry-sidebar-field-gap); }
.recurrence-disclosure .disclosure-open,
.reminder-disclosure .disclosure-open { transform: rotate(180deg); }
.planning-details { margin-bottom: var(--entry-sidebar-field-gap); }

.planning-card {
	grid-column: 1 / -1;
}

.project-assignment-row {
	display: grid;
	grid-column: 1 / -1;
	grid-template-columns: minmax(0, 1fr);
	gap: var(--entry-sidebar-field-gap);
	align-items: end;
}

.assignment-fields {
	grid-column: 1 / -1;
	margin-top: 0;
}

.area-choice-grid {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: 12px;
}

/* Count the personal destination too: at most three rows of direct choices. */
.detail-project--direct .area-choice-grid {
	display: none;
}

.detail-project--single-column .area-choice-grid {
	display: grid;
}

@container entry-form (min-width: 340px) {
	.detail-project--direct .area-choice-grid {
		display: grid;
		grid-template-columns: repeat(var(--area-choice-columns), minmax(0, 1fr));
	}

	.detail-project--direct .entry-area-select {
		display: none;
	}
}

.area-choice {
	--area-choice-state-background: var(--color-main-background);
	--area-choice-state-border: var(--color-border-maxcontrast);
	--area-choice-state-text: var(--color-main-text);
	--area-choice-border-width: 1px;

	display: grid;
	grid-template-columns: 24px minmax(0, 1fr);
	align-items: center;
	gap: 8px;
	box-sizing: border-box;
	min-width: 0;
	min-height: var(--default-clickable-area, 34px);
	margin: 0 !important;
	padding: 4px 8px;
	border: none !important;
	border-radius: var(--border-radius-element);
	background: var(--area-choice-state-background) !important;
	box-shadow: inset 0 0 0 var(--area-choice-border-width) var(--area-choice-state-border) !important;
	font-weight: normal;
	color: var(--area-choice-state-text) !important;
	text-align: left;
	transform: none !important;
	transition: box-shadow var(--cobudget-hover-duration) linear;
}

.area-choice:focus {
	outline: none !important;
}

button.area-choice:focus-visible {
	outline: 2px solid var(--color-main-text) !important;
	outline-offset: 2px;
}

.area-choice[aria-checked='true'] {
	--area-choice-state-border: var(--area-choice-selected-border);
	--area-choice-state-background: var(--area-choice-selected-background);
	--area-choice-state-text: var(--area-choice-selected-text);
}

.area-choice__icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	color: currentColor;
}

.area-choice__label {
	min-width: 0;
	overflow-wrap: anywhere;
}

.entry-area-option { display: inline-flex; align-items: center; gap: 8px; min-width: 0; max-width: 100%; }
.entry-area-option > :first-child { flex-shrink: 0; }
.entry-area-option > :last-child { min-width: 0; white-space: normal; overflow-wrap: anywhere; }
.entry-area-option--selected > :last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.entry-member-select { width: 100%; min-width: 0; margin-bottom: 0; }
.entry-member-selection { max-width: 100%; }
.entry-member-selection :deep(.member-identity-name),
.entry-area-split-option.entry-member-selection > :last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.entry-area-split-option { display: inline-flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 2); min-width: 0; }
.entry-area-split-option > :first-child { flex-shrink: 0; }
.entry-area-split-option > :last-child { white-space: normal; overflow-wrap: anywhere; min-width: 0; }

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

.recurrence-multiplier-input :deep(input) {
	text-align: center;
}



.form-group {
	margin-bottom: 0;
}

.form-group label {
	display: block;
}

.form-group label,


/* Match settings label typography while keeping compact payment spacing.
 * Lookup captions and label toggles retain their dedicated roles. */
.form-group > label:not(.tag-toggle),
.entry-group-label {
	margin-block: 0 var(--default-grid-baseline, 4px);
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	line-height: 1.5;
	color: var(--color-main-text);
}

.form-control {
	width: 100%;
	height: var(--cobudget-control-height) !important;
	padding: 10px 12px;
	border: var(--cobudget-control-border) solid var(--cobudget-border-strong, #ccc);
	border-radius: var(--cobudget-control-radius);
	font-size: var(--cobudget-font-base);
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-text, var(--color-main-text, #222));
	box-sizing: border-box;
	transition: border-color var(--cobudget-hover-duration) linear;
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
	border-color: var(--cobudget-control-focus-border) !important;
	background: var(--cobudget-surface, var(--color-main-background, #fff)) !important;
	/* Keep focus inside the field; the sidebar clips an outside ring. */
	outline: none !important;
	box-shadow: none !important;
}

.amount-input {
	margin: 0;
	flex: 1 1 80px;
	width: 0;
	min-width: 70px;
	font-weight: 600;
	font-family: monospace;
	font-size: var(--cobudget-font-md);
	height: 100% !important;
	padding: 0 12px !important;
	line-height: normal;
	text-align: right;
	border: 0 !important;
	border-radius: 0;
	outline: none;
	background: transparent !important;
	color: inherit !important;
	box-shadow: none !important;
	transition: background-color var(--cobudget-hover-duration) linear, color var(--cobudget-hover-duration) linear;
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
	height: var(--default-clickable-area, 44px);
	margin: 0;
	border: none;
	border-radius: var(--border-radius-element);
	box-sizing: border-box;
	overflow: visible;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--amount-accent);
	outline: none;
	box-shadow: inset 0 0 0 1px var(--color-border-maxcontrast);
	transition: background-color var(--cobudget-hover-duration) linear, color var(--cobudget-hover-duration) linear;
}

@media (hover: hover) and (pointer: fine) {
	.amount-input-wrap:hover {
		box-shadow: inset 0 0 0 var(--border-width-input-focused, 2px) var(--amount-accent);
	}
}

.amount-input-wrap:focus-within {
	outline: none;
	box-shadow: inset 0 0 0 var(--border-width-input-focused, 2px) var(--amount-accent);
}

.entry-sidebar.entry-sidebar--type-field-header :deep(.app-sidebar-header__desc) {
	padding-inline-end: var(--entry-sidebar-content-padding) !important;
}
.entry-sidebar :deep(.app-sidebar-header__mainname.entry-type-accessible-heading) { min-height: 0; }

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
	text-align: start;
}

.amount-input::placeholder {
	color: var(--color-text-maxcontrast);
	opacity: 1;
}

.date-col > input {
	height: 44px !important;
	line-height: 40px !important;
	font-size: var(--cobudget-font-base) !important;
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
}


.area-choice__label {
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-default, normal);
	line-height: normal;
	letter-spacing: normal;
	color: inherit;
}

.area-choice:disabled { opacity: 0.5; cursor: default; }

/* Hover is transient pointer feedback, independent of checked/radio state.
 * Touch taps must not leave a hover fill on an unselected option. */
@media (hover: hover) and (pointer: fine) {

	.area-choice:hover:not(:disabled):not([aria-checked='true']) {
		--area-choice-border-width: var(--border-width-input-focused, 2px);
	}

	.area-choice[aria-checked='true']:hover:not(:disabled) {
		--area-choice-border-width: var(--border-width-input-focused, 2px);
		--area-choice-state-border: var(--area-choice-selected-hover);
	}

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

.attachment-upload-btn.button-vue {
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
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
	background: transparent;
}

.attachment-list a,
.attachment-list li > span:first-child {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: var(--font-weight-default, 400);
}

.attachment-meta {
	margin-left: auto;
	white-space: nowrap;
}

.attachment-pending > .button-vue {
	/* Center the removal action over the planning switches below. */
	position: relative;
	inset-inline-start: 2px;
}


.form-actions {
	display: flex;
	flex-direction: column;
	gap: 8px;
	align-items: center;
	padding: 10px var(--entry-sidebar-content-padding);
	margin-top: 0;
	border-top: 1px solid var(--cobudget-border, var(--color-border));
	background: var(--cobudget-surface-muted, var(--color-background-dark));
	flex-shrink: 0;
	border-radius: 0 0 10px 10px;
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
	margin-top: var(--cobudget-content-gap);
	font-size: var(--cobudget-font-sm);
	color: var(--color-text-maxcontrast);
}

.recurrence-inputs {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: var(--entry-sidebar-field-gap);
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

.planning-select,
.planning-card .cobudget-native-field {
	width: 100%;
	min-width: 0;
	margin: 0;
}

/* Align native input interiors with NcSelect without changing its border states. */
.planning-card :deep(.input-field__main-wrapper),
.core-date :deep(.input-field__main-wrapper),
.detail-description :deep(.input-field__main-wrapper) {
	padding: 0;
}

@media (max-width: 780px) {
	.entry-sidebar {
		--entry-sidebar-content-padding: var(--cobudget-content-gap);
	}

	.modal-body {
		padding: var(--entry-sidebar-content-padding);
	}

	.entry-details-grid,
	.planning-grid {
		grid-template-columns: 1fr;
	}

	.entry-details-grid {
		row-gap: var(--entry-sidebar-field-gap);
	}

	.core-description,
	.project-assignment-row,
	.detail-tags,
	.planning-card,
	.detail-category,
	.detail-paymentPartner {
		grid-column: auto;
	}

	.project-assignment-row,
	.project-assignment-row.has-project-payer,
	.project-assignment-row.has-split-mode {
		grid-template-columns: 1fr;
	}

	.area-choice,
	.planning-section .planning-switch,
	.planning-section .attachment-upload-btn {
		min-height: var(--cobudget-mobile-touch-size, 44px);
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
		--entry-sidebar-content-padding: var(--cobudget-content-gap);
	}


	.form-actions {
		border-radius: 0;
		padding: 10px var(--entry-sidebar-content-padding);
	}


	.modal-body {
		padding: var(--entry-sidebar-content-padding);
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

	.entry-sidebar.entry-sidebar--type-field-header :deep(.app-sidebar-header__desc) {
		padding-inline: 0 !important;
	}

	.entry-sidebar :deep(.app-sidebar-header__mainname) {
		min-height: auto !important;
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
		padding: calc(var(--default-grid-baseline, 4px) * 2) 0
			calc(var(--default-grid-baseline, 4px) * 3);
		/* Match the scrolling form body's reserved scrollbar gutter. */
		overflow-y: hidden;
		scrollbar-gutter: stable;
		scrollbar-width: thin;
		border-top: 0;
		border-radius: 0;
		background: transparent;
	}
}

@media (max-width: 768px) {
	.entry-sidebar { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); }

}
/* Match field-ring hover without delaying checked states or keyboard focus. */
.amount-input-wrap {
	transition: box-shadow var(--cobudget-hover-duration) linear;
}
.area-choice:is([aria-checked='true'], :focus-visible, :active, :disabled),
.amount-input-wrap:focus-within,
.form-control:focus {
	transition: none;
}
</style>

<style scoped>
@container entry-form (min-width: 280px) {
	.entry-required-panel {
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
		column-gap: calc(var(--default-grid-baseline, 4px) * 3);
	}
}
.core-date, .core-amount { min-width: 0; }
.core-date :deep(input) { min-width: 0; }
.entry-sidebar :deep(.app-sidebar-header__mainname) {
	display: flex;
	align-items: center;
	min-height: var(--default-clickable-area);
	margin: 0;
	overflow: visible;
}
.entry-header-content {
	min-width: 0;
	padding-block: var(--app-sidebar-padding);
	padding-inline-end: calc(var(--app-sidebar-padding) + var(--default-clickable-area) + var(--default-grid-baseline, 4px) * 2);
}
</style>

<style scoped>
@media (max-width: 1024px) {
	.entry-sidebar :deep(.app-sidebar-header__mainname) {
		white-space: nowrap !important;
		overflow: hidden;
		text-overflow: ellipsis;
		overflow-wrap: normal;
	}
	.entry-header-content {
		justify-content: flex-start;
	}
}
</style>

<style scoped>
.planning-switch-label {
	display: flex;
	align-items: center;
	gap: var(--planning-row-gap);
}
.planning-switch-label > :first-child { flex-shrink: 0; }
.planning-card .planning-details { margin-top: 0; }
</style>

<style scoped src="../styles/label-toggles.css"></style>
