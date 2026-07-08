<template>
	<Teleport to="body">
		<div
			class="modal-backdrop"
			v-if="isOpen"
			tabindex="-1"
			@click.self="closeModal"
			@keydown.esc.stop.prevent="handleEscape">
		<div
			ref="modalContent"
			class="modal-content"
			role="dialog"
			aria-modal="true"
			:aria-labelledby="modalTitleId"
			@focusin="handleModalFocusIn"
			@focusout="handleModalFocusOut">
			<form @submit.prevent="saveEntry" class="modal-form">
				<div class="modal-header">
					<h2 :id="modalTitleId" class="modal-title">{{ modalTitle }}</h2>
					<div class="modal-header-actions">
						<button
							v-if="showMobileHeaderSave"
							type="button"
							class="modal-header-save-button"
							:aria-label="saveActionLabel"
							:title="saveActionLabel"
							:disabled="!isValid || loading"
							@pointerdown.prevent
							@click="saveEntry">
							<ContentSaveIcon :size="22" aria-hidden="true" />
						</button>
						<button
							type="button"
							class="modal-close-button"
							:aria-label="$texts.common.close()"
							:title="$texts.common.close()"
							@click="closeModal">
							<CloseIcon :size="22" aria-hidden="true" />
						</button>
					</div>
				</div>

				<div class="modal-body">
					<div v-if="isEditing && isFutureContext" class="info-banner">
						<strong>{{ $texts.entry.futureOriginalNoticeTitle() }}</strong> {{ $texts.entry.futureOriginalNotice() }}
					</div>

					<div class="entry-required-panel">
						<div class="form-group type-group core-type" v-if="$enableIncomes">
							<div class="type-toggle">
								<button type="button" class="type-btn" :class="{active: entry.type === 'income', 'income-active': entry.type === 'income'}" @click="setEntryType('income')">{{ $texts.common.income() }}</button>
								<button type="button" class="type-btn" :class="{active: entry.type === 'expense', 'expense-active': entry.type === 'expense'}" @click="setEntryType('expense')">{{ $texts.common.expense() }}</button>
							</div>
						</div>

						<div class="form-group core-template-name" v-if="isTemplateMode">
							<label>{{ $texts.entry.templateName() }} <span class="required-marker">*</span></label>
							<input ref="templateNameInput" type="text" v-model="templateName" class="form-control" :placeholder="$texts.entry.templateNamePlaceholder()" required>
						</div>

						<div class="form-group date-col core-date" v-else>
							<label>{{ dateLabel }}</label>
							<input type="date" v-model="dateString" :min="minFutureDate" class="form-control" required />
						</div>

						<div class="form-group amount-col" :class="isTemplateMode ? 'core-template-amount' : 'core-amount'">
							<label>{{ amountLabel }}</label>
							<input
								type="text"
								ref="amountInput"
								v-model="entry.amountDisplay"
								inputmode="text"
								autocomplete="off"
								autocapitalize="off"
								autocorrect="off"
								spellcheck="false"
								pattern="[0-9.,+\-*/]*"
								@input="sanitizeAmountInput"
								@blur="evaluateAmount"
								class="form-control amount-input"
								:class="{'bg-income': entry.type === 'income', 'bg-expense': entry.type === 'expense'}"
								:required="!isTemplateMode"
								placeholder="0.00">
						</div>
					</div>

					<div class="entry-details-grid">
						<div class="form-group detail-category">
							<label>{{ $texts.entry.category() }}</label>
							<div class="lookup-field category-input-wrap" :class="{ 'has-leading-icon': selectedCategoryIcon, 'has-clear-button': entry.categoryName }">
								<CategoryIcon v-if="selectedCategoryIcon" :icon="selectedCategoryIcon" :size="18" class="category-input-icon" />
								<input
									ref="categoryLookupInput"
									type="text"
									v-model="entry.categoryName"
									class="form-control"
									:placeholder="$texts.entry.lookupPlaceholder()"
									autocomplete="off"
									role="combobox"
									:aria-expanded="showCategorySuggestions ? 'true' : 'false'"
									aria-controls="category-suggestions"
									@focus="openLookup('category')"
									@click="openLookup('category')"
									@input="onLookupInput('category')"
									@keydown.down.prevent="moveLookupHighlight('category', 1)"
									@keydown.up.prevent="moveLookupHighlight('category', -1)"
									@keydown.enter="handleLookupEnter($event, 'category')"
									@keydown.esc.stop.prevent="closeLookup"
									@blur="deferLookupClose('category')">
								<button
									v-if="entry.categoryName"
									type="button"
									class="lookup-clear-button"
									:aria-label="$texts.entry.clearCategory()"
									:title="$texts.entry.clearCategory()"
									@mousedown.prevent
									@click.prevent="clearLookupValue('category')">
									<CloseIcon :size="16" aria-hidden="true" />
								</button>
								<div v-if="showCategorySuggestions" id="category-suggestions" class="lookup-menu" role="listbox">
									<template v-for="section in categorySuggestionSections" :key="section.label || 'category-results'">
										<div v-if="section.label && section.items.length" class="lookup-group-label">{{ section.label }}</div>
										<button
											v-for="cat in section.items"
											:key="`${section.label || 'category'}-${cat.id}`"
											type="button"
											class="lookup-option"
											:class="{ active: highlightedCategoryIndex === lookupIndex('category', cat) }"
											role="option"
											:aria-selected="highlightedCategoryIndex === lookupIndex('category', cat) ? 'true' : 'false'"
											@mousedown.prevent="selectCategorySuggestion(cat)"
											@click="selectCategorySuggestion(cat)">
											<CategoryIcon :icon="cat.icon || 'Shape'" :size="16" />
											<span>{{ cat.name }}</span>
										</button>
									</template>
								</div>
							</div>
						</div>

						<div class="form-group detail-paymentPartner">
							<label>{{ paymentPartnerLabel }}</label>
							<div class="lookup-field" :class="{ 'has-clear-button': entry.paymentPartnerName }">
								<input
									ref="paymentPartnerLookupInput"
									type="text"
									v-model="entry.paymentPartnerName"
									class="form-control"
									:placeholder="$texts.entry.lookupPlaceholder()"
									autocomplete="off"
									role="combobox"
									:aria-expanded="showPaymentPartnerSuggestions ? 'true' : 'false'"
									aria-controls="paymentPartner-suggestions"
									@focus="openLookup('paymentPartner')"
									@click="openLookup('paymentPartner')"
									@input="onLookupInput('paymentPartner')"
									@keydown.down.prevent="moveLookupHighlight('paymentPartner', 1)"
									@keydown.up.prevent="moveLookupHighlight('paymentPartner', -1)"
									@keydown.enter="handleLookupEnter($event, 'paymentPartner')"
									@keydown.esc.stop.prevent="closeLookup"
									@blur="deferLookupClose('paymentPartner')">
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
								<div v-if="showPaymentPartnerSuggestions" id="paymentPartner-suggestions" class="lookup-menu" role="listbox">
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
											@mousedown.prevent="selectPaymentPartnerSuggestion(paymentPartner)"
											@click="selectPaymentPartnerSuggestion(paymentPartner)">
											<span>{{ paymentPartner.name }}</span>
										</button>
									</template>
								</div>
							</div>
						</div>

						<div class="form-group core-description detail-description">
							<label>{{ $texts.entry.description() }}</label>
							<input type="text" v-model="entry.description" class="form-control" :placeholder="$texts.entry.descriptionPlaceholder()">
						</div>

						<div class="form-group tags-group detail-tags" v-if="hasAvailableTags">
							<div class="tags-toggles">
								<label class="tag-toggle" v-if="$enableImportantPayments">
									<input type="checkbox" v-model="entry.isImportant">
									<span class="tag-btn" :class="{active: entry.isImportant}">{{ $texts.labels.important() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableReviewPayments">
									<input type="checkbox" v-model="entry.needsReview">
									<span class="tag-btn" :class="{active: entry.needsReview}">{{ $texts.labels.review() }}</span>
								</label>
								<label class="tag-toggle" v-if="entry.type === 'expense' && $enableFixedCosts">
									<input type="checkbox" v-model="entry.isFixedCost">
									<span class="tag-btn" :class="{active: entry.isFixedCost}">{{ $texts.labels.fixedCosts() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableChildRelated">
									<input type="checkbox" v-model="entry.isChildRelated">
									<span class="tag-btn" :class="{active: entry.isChildRelated}">{{ $texts.labels.children() }}</span>
								</label>
								<label class="tag-toggle" v-if="entry.type === 'expense' && $enableSubscriptions">
									<input type="checkbox" v-model="entry.isSubscription">
									<span class="tag-btn" :class="{active: entry.isSubscription}">{{ $texts.labels.subscription() }}</span>
								</label>
								<label class="tag-toggle" v-if="$enableTaxRelevant">
									<input type="checkbox" v-model="entry.isTaxRelevant">
									<span class="tag-btn" :class="{active: entry.isTaxRelevant}">{{ $texts.labels.taxRelevant() }}</span>
								</label>
							</div>
						</div>

						<div class="attachments-inline detail-attachments" v-if="showAttachmentSection">
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
									<button type="button" class="attachment-remove" :disabled="loading" @click="deleteAttachment(attachment)" :aria-label="$texts.entry.removeReceipt()">×</button>
								</li>
								<li v-for="(file, index) in pendingAttachments" :key="`pending-${index}-${file.name}`" class="attachment-pending">
									<span>{{ file.name }}</span>
									<span class="attachment-meta">{{ $texts.entry.uploadOnSave() }}</span>
									<button type="button" class="attachment-remove" :disabled="loading" @click="removePendingAttachment(index)" :aria-label="$texts.entry.removeSelection()">×</button>
								</li>
							</ul>
						</div>

					</div>
					<details class="assignment-section" v-if="!isTemplateMode && $enableProjects">
						<summary>{{ assignmentSummary }}</summary>
						<div class="assignment-card">
							<div class="project-assignment-row" :class="{ 'has-project-payer': showProjectPayerSelect, 'has-split-mode': showProjectSplitMode }">
								<div class="form-group detail-project">
									<label>{{ $texts.entry.assignment() }}</label>
									<select v-model="entry.projectId" class="form-control select-control" :aria-label="$texts.entry.assignment()">
										<option :value="null">{{ $texts.entry.personalAssignment() }}</option>
										<optgroup v-if="activeProjects.length" :label="$texts.entry.areas()">
											<option v-for="p in activeProjects" :key="p.id" :value="p.id">
												{{ projectOptionLabel(p) }}
											</option>
										</optgroup>
									</select>
								</div>
								<div class="form-group detail-project-payer" v-if="showProjectPayerSelect">
									<label>{{ projectPayerLabel }}</label>
									<select v-model="entry.userId" class="form-control select-control">
										<option v-for="member in projectPayerOptions" :key="member.id" :value="member.id">
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
											:value="`single_user:${member.id}`">
											{{ $texts.entry.singleUserSplitTarget(member.displayName) }}
										</option>
									</select>
								</div>
							</div>
						</div>
					</details>

					<details class="planning-section" v-if="!isTemplateMode" :open="showPlanningOptions" @toggle="showPlanningOptions = $event.target.open">
						<summary>{{ $texts.entry.planning() }}</summary>
						<div class="planning-grid">
							<div class="form-group recurrence-group planning-card" v-if="$enableFuturePayments">
								<div class="recurrence-options">
									<div class="recurrence-inputs" :class="{ 'is-recurring': entry.recurrenceInterval !== 'none' }">
										<div class="form-group recurrence-multiplier-field" v-if="entry.recurrenceInterval !== 'none'">
											<label>{{ $texts.entry.repeatEvery() }}</label>
											<input type="number" v-model.number="entry.recurrenceMultiplier" class="form-control recurrence-multiplier-input" min="1" required :aria-label="$texts.entry.recurrenceInterval()">
										</div>
										<div class="form-group recurrence-interval-field">
											<label v-if="entry.recurrenceInterval === 'none'">{{ $texts.entry.repeatEvery() }}</label>
											<select v-model="entry.recurrenceInterval" class="form-control select-control">
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
						:primary-menu-items="saveMenuItems"
						:primary-disabled="!isValid || loading"
						:primary-busy="loading"
						primary-type="button"
						:show-cancel="false"
						:primary-busy-label="$texts.common.saveBusy()"
						@primary="saveEntry"
						@primary-menu="handleSaveMenuAction"
						@cancel="closeModal">
						<template v-if="isEditing && !isTemplateMode" #left>
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
		</div>
	</div>
	</Teleport>
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
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import CbIconButton from './CbIconButton.vue'

export default {
	name: 'AddEntryModal',
	components: { CategoryIcon, ModalActions, ConfirmModal, CloseIcon, ContentSaveIcon, DeleteOutlineIcon, CbIconButton },
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
			isFutureContext: false,
			loading: false,
			entry: {
				type: 'expense',
				amountDisplay: '',
				description: '',
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
			isTemplateMode: false,
			isDuplicateMode: false,
			isInitializingEntry: false,
			saveAsTemplate: false,
			templateName: '',
			templateDescription: '',
			showPlanningOptions: false,
			confirmDialog: null,
			focusedLookupField: null,
			lookupSearchMode: {
				category: false,
				paymentPartner: false
			},
			highlightedCategoryIndex: -1,
			highlightedPaymentPartnerIndex: -1,
			attachments: [],
			pendingAttachments: [],
			attachmentsLoading: false,
			templates: [],
			templatesLoading: false,
			sourceTemplateId: null,
			modalTitleId: 'entry-modal-title',
			modalHistoryToken: null,
			closedByHistory: false,
			ignoreNextPopState: false,
			ignorePopStateTimer: null,
			mobileFormFieldFocused: false,
			mobileFocusTimer: null
		}
	},
	computed: {
		modalTitle() {
			if (this.isTemplateMode) {
				return this.$texts.entry.newTemplate();
			}
			if (this.isEditing) {
				return this.$texts.entry.editPayment();
			}
			if (this.isDuplicateMode) {
				return this.$texts.entry.copyPayment();
			}
			if (this.isFutureContext) {
				return this.$texts.entry.planPayment();
			}
			return this.$texts.areaDetail.newPayment();
		},
		dateLabel() {
			if (this.isFutureContext && !this.isEditing) {
				return this.$texts.entry.plannedFor();
			}
			return this.entry.type === 'income' ? this.$texts.entry.receivedOn() : this.$texts.entry.paidOn();
		},
		amountLabel() {
			const base = this.isTemplateMode ? this.$texts.entry.amountOptional() : this.$texts.entry.amount();
			return this.$currency ? `${base} (${this.$currency})` : base;
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
		selectedCategoryIcon() {
			const name = this.entry.categoryName;
			if (!name) return null;
			const cat = this.categories.find(c => c.name.toLowerCase() === name.toLowerCase());
			return cat ? (cat.icon || 'Shape') : null;
		},
		isValid() {
			if (this.isTemplateMode) {
				return this.templateName.trim() !== '';
			}
			const amt = this.$parseAmount(this.entry.amountDisplay)
			return !isNaN(amt) && amt > 0;
		},
		filteredCategories() {
			return this.categories.filter(c => c.type === this.entry.type || !c.type);
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
			return this.buildSuggestionSections(this.filteredCategories, this.categoryLookupQuery, this.$texts.entry.allCategories());
		},
		categorySuggestions() {
			return this.categorySuggestionSections.flatMap(section => section.items);
		},
		paymentPartnerSuggestionSections() {
			return this.buildSuggestionSections(this.filteredPaymentPartners, this.paymentPartnerLookupQuery, this.$texts.entry.allPaymentPartners());
		},
		paymentPartnerSuggestions() {
			return this.paymentPartnerSuggestionSections.flatMap(section => section.items);
		},
		showCategorySuggestions() {
			return this.focusedLookupField === 'category' && this.categorySuggestions.length > 0;
		},
		showPaymentPartnerSuggestions() {
			return this.focusedLookupField === 'paymentPartner' && this.paymentPartnerSuggestions.length > 0;
		},
		isEditing() {
			return !!this.internalEditingEntry;
		},
		showAttachmentSection() {
			return !this.isTemplateMode && this.$enableReceipts;
		},
		hasAttachments() {
			return this.attachments.length > 0 || this.pendingAttachments.length > 0;
		},
		saveActionLabel() {
			if (this.isTemplateMode) {
				return this.$texts.entry.saveTemplate();
			}
			return this.isFutureContext && !this.isEditing ? this.$texts.entry.planPayment() : this.$texts.common.save();
		},
		showSaveSplitMenu() {
			return !this.isTemplateMode && !this.isEditing && !this.loading;
		},
		showMobileHeaderSave() {
			return this.isOpen && this.mobileFormFieldFocused && !this.confirmDialog;
		},
		saveMenuItems() {
			if (!this.showSaveSplitMenu) {
				return [];
			}

			const newEntryAction = { key: 'new', label: this.$texts.entry.saveNew() };

			if (!this.$enableTemplates) {
				return [newEntryAction];
			}

			if (this.templatesLoading) {
				return [
					{ key: 'templates-loading', label: this.$texts.entry.templatesLoading(), disabled: true },
					{ key: 'template-separator', separator: true },
					newEntryAction
				];
			}

			if (!this.templates.length) {
				return [
					{ key: 'no-templates', label: this.$texts.entry.noTemplates(), disabled: true },
					{ key: 'template-separator', separator: true },
					newEntryAction
				];
			}

			const templateActions = this.templates.map(template => ({
					key: `template-${template.id}`,
					label: this.$texts.entry.saveTemplateNamed(template.name),
					title: this.$texts.entry.openTemplateAfterSave(template.name),
					template
				}));

			return [
				// The menu opens upwards, so the visual template order must be inverted.
				...templateActions.reverse(),
				{ key: 'template-separator', separator: true },
				newEntryAction
			];
		},
		activeProjects() {
			if (!this.$enableProjects) {
				return [];
			}
			return this.projects.filter(p => !p.is_archived);
		},
		selectedProject() {
			if (!this.entry.projectId) {
				return null;
			}

			return this.projects.find(p => Number(p.id) === Number(this.entry.projectId)) || null;
		},
		projectPayerOptions() {
			if (!this.selectedProject || !Array.isArray(this.selectedProject.members)) {
				return [];
			}

			return this.selectedProject.members
				.map(member => this.normalizeProjectMember(member))
				.filter(member => member.id !== '');
		},
		projectSplitOptions() {
			return this.projectPayerOptions;
		},
		showProjectPayerSelect() {
			return this.$enableSharedProjects && !this.isTemplateMode && !!this.entry.projectId && this.projectPayerOptions.length > 1;
		},
		showProjectSplitMode() {
			return this.showProjectPayerSelect;
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
		assignmentSummary() {
			if (!this.selectedProject) {
				return this.$texts.entry.personalAssignmentSummary();
			}

			if (this.entry.splitMode === 'single_user') {
				return this.$texts.entry.areaOnlyUser(this.selectedSplitUserLabel);
			}

			return this.$texts.entry.areaAssigned(this.selectedProject.name, this.projectShareLabel(this.selectedProject));
		},
		selectedProjectUserLabel() {
			const member = this.projectPayerOptions.find(option => option.id === this.entry.userId);
			return member ? member.displayName : this.$texts.entry.selectedUser();
		},
		selectedSplitUserLabel() {
			const splitUserId = this.entry.splitUserId || this.entry.userId;
			const member = this.projectPayerOptions.find(option => option.id === splitUserId);
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
	},
	beforeUnmount() {
		window.removeEventListener('popstate', this.handleModalPopState);
		if (this.ignorePopStateTimer) {
			window.clearTimeout(this.ignorePopStateTimer);
			this.ignorePopStateTimer = null;
		}
		this.clearMobileFocusTimer();
		this.releaseModalHistory({ skipBack: true });
	},
	watch: {
		'entry.projectId': async function(projectId) {
			if (this.isInitializingEntry) {
				return;
			}

			this.entry.splitMode = 'project_shares';
			this.entry.splitUserId = null;
			this.resetScopedLookups();
			await this.fetchDataLists(projectId);
			await this.ensureProjectMembers(projectId);
			this.syncEntryUserWithProject(this.currentUserId());
		}
	},
	methods: {
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
			if (this.focusedLookupField) {
				this.closeLookup();
				return;
			}
			this.closeModal();
		},
		pushModalHistory() {
			if (this.modalHistoryToken || typeof window === 'undefined' || !window.history?.pushState) {
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
				this.closeModal({ skipHistory: true });
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
		setEntryType(type) {
			const nextType = type === 'income' ? 'income' : 'expense';
			if (this.entry.type === nextType) {
				return;
			}

			this.entry.type = nextType;
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
					sharePercent: Math.round(parseFloat(member?.sharePercent ?? 0) || 0)
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
			if (members.length <= 1) {
				this.entry.splitMode = 'project_shares';
				this.entry.splitUserId = null;
			}

			const preferred = preferredUserId || this.entry.userId || currentUserId;
			if (members.some(member => member.id === preferred)) {
				this.entry.userId = preferred;
				this.syncSplitUserWithProjectMembers(members);
				return;
			}

			if (members.some(member => member.id === currentUserId)) {
				this.entry.userId = currentUserId;
				this.syncSplitUserWithProjectMembers(members);
				return;
			}

			this.entry.userId = members[0].id;
			this.syncSplitUserWithProjectMembers(members);
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
		sortTemplates(templates) {
			return [...templates].sort((a, b) => {
				const usageDiff = (parseInt(b.usage_count, 10) || 0) - (parseInt(a.usage_count, 10) || 0);
				if (usageDiff !== 0) {
					return usageDiff;
				}

				return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
			});
		},
		buildSuggestionSections(items, query, allLabel) {
			const normalizedQuery = String(query || '').trim().toLowerCase();
			const candidates = normalizedQuery
				? items.filter(item => String(item.name || '').toLowerCase().includes(normalizedQuery))
				: items;

			const frequent = this.sortByUsageThenName(candidates)
				.filter(item => this.usageCount(item) > 0)
				.slice(0, 5);
			const frequentIds = new Set(frequent.map(item => item.id));
			const remaining = this.sortByName(candidates.filter(item => !frequentIds.has(item.id)));

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
		lookupItems(field) {
			return field === 'category' ? this.filteredCategories : this.filteredPaymentPartners;
		},
		lookupSuggestions(field) {
			return field === 'category' ? this.categorySuggestions : this.paymentPartnerSuggestions;
		},
		setLookupSearchMode(field, isSearching) {
			this.lookupSearchMode = {
				...this.lookupSearchMode,
				[field]: isSearching
			};
		},
		exactLookupMatch(field) {
			const value = String(this.lookupValue(field) || '').trim().toLowerCase();
			if (!value) {
				return null;
			}

			return this.lookupItems(field).find(item => String(item.name || '').trim().toLowerCase() === value) || null;
		},
		lookupQueryForField(field) {
			const value = String(this.lookupValue(field) || '').trim();
			if (!value) {
				return '';
			}

			if (this.focusedLookupField === field && !this.lookupSearchMode[field] && this.exactLookupMatch(field)) {
				return '';
			}

			return value.toLowerCase();
		},
		lookupIndex(field, item) {
			const suggestions = field === 'category' ? this.categorySuggestions : this.paymentPartnerSuggestions;
			return suggestions.findIndex(suggestion => suggestion.id === item.id);
		},
		openLookup(field) {
			this.focusedLookupField = field;
			const value = String(this.lookupValue(field) || '').trim();
			this.setLookupSearchMode(field, value !== '' && !this.exactLookupMatch(field));
			this.resetLookupHighlight(field);
		},
		onLookupInput(field) {
			this.focusedLookupField = field;
			this.setLookupSearchMode(field, true);
			this.resetLookupHighlight(field);
		},
		closeLookup() {
			this.focusedLookupField = null;
			this.highlightedCategoryIndex = -1;
			this.highlightedPaymentPartnerIndex = -1;
		},
		resetScopedLookups() {
			this.entry.categoryName = '';
			this.entry.paymentPartnerName = '';
			this.closeLookup();
		},
		clearLookupValue(field) {
			if (field === 'category') {
				this.entry.categoryName = '';
			} else if (field === 'paymentPartner') {
				this.entry.paymentPartnerName = '';
			}

			this.focusedLookupField = field;
			this.setLookupSearchMode(field, false);
			this.$nextTick(() => {
				const input = field === 'category' ? this.$refs.categoryLookupInput : this.$refs.paymentPartnerLookupInput;
				if (input && typeof input.focus === 'function') {
					input.focus();
				}
				this.resetLookupHighlight(field);
			});
		},
		deferLookupClose(field) {
			window.setTimeout(() => {
				if (this.focusedLookupField === field) {
					this.closeLookup();
				}
			}, 120);
		},
		resetLookupHighlight(field) {
			const suggestions = this.lookupSuggestions(field);
			const exactMatch = !this.lookupSearchMode[field] ? this.exactLookupMatch(field) : null;
			const exactIndex = exactMatch ? suggestions.findIndex(suggestion => suggestion.id === exactMatch.id) : -1;
			const nextIndex = exactIndex >= 0 ? exactIndex : (suggestions.length > 0 ? 0 : -1);

			if (field === 'category') {
				this.highlightedCategoryIndex = nextIndex;
			} else if (field === 'paymentPartner') {
				this.highlightedPaymentPartnerIndex = nextIndex;
			}
		},
		moveLookupHighlight(field, direction) {
			const suggestions = field === 'category' ? this.categorySuggestions : this.paymentPartnerSuggestions;
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
			if (field === 'category') {
				const item = this.categorySuggestions[this.highlightedCategoryIndex];
				if (item) {
					this.selectCategorySuggestion(item);
				}
			} else if (field === 'paymentPartner') {
				const item = this.paymentPartnerSuggestions[this.highlightedPaymentPartnerIndex];
				if (item) {
					this.selectPaymentPartnerSuggestion(item);
				}
			}
		},
		handleLookupEnter(event, field) {
			const suggestions = field === 'category' ? this.categorySuggestions : this.paymentPartnerSuggestions;
			const highlightedIndex = field === 'category' ? this.highlightedCategoryIndex : this.highlightedPaymentPartnerIndex;
			const item = suggestions[highlightedIndex];
			if (item) {
				event.preventDefault();
				this.chooseHighlightedLookup(field);
				return;
			}
			this.closeLookup();
		},
		selectCategorySuggestion(category) {
			this.entry.categoryName = category.name;
			this.setLookupSearchMode('category', false);
			this.closeLookup();
			this.blurLookupInput('category');
		},
		selectPaymentPartnerSuggestion(paymentPartner) {
			this.entry.paymentPartnerName = paymentPartner.name;
			this.setLookupSearchMode('paymentPartner', false);
			this.closeLookup();
			this.blurLookupInput('paymentPartner');
		},
		blurLookupInput(field) {
			this.$nextTick(() => {
				const input = field === 'category' ? this.$refs.categoryLookupInput : this.$refs.paymentPartnerLookupInput;
				if (input && typeof input.blur === 'function') {
					input.blur();
				}
				this.syncMobileHeaderSaveVisibility();
			});
		},
		async openModal(entryToEdit = null, defaultProjectId = null, isFutureContext = false, templateToLoad = null, isTemplateMode = false, entryToDuplicate = null, defaultType = 'expense') {
			if (!this.$enableTemplates) {
				templateToLoad = null;
				isTemplateMode = false;
			}
			this.isOpen = true;
			this.closedByHistory = false;
			this.pushModalHistory();
			this.isFutureContext = isFutureContext;
			this.isTemplateMode = isTemplateMode;
			this.isDuplicateMode = !!entryToDuplicate && !entryToEdit && !isTemplateMode;
			this.saveAsTemplate = false;
			this.templateName = '';
			this.templateDescription = '';
			this.sourceTemplateId = null;
			this.resetAttachments();
			const resolvedDefaultType = defaultType === 'income' ? 'income' : 'expense';
			
			// Projects are needed before the project-scoped category/contact list can be resolved.
			await this.fetchProjects();
			if (!entryToEdit && !isTemplateMode) {
				await this.fetchTemplates();
			} else {
				this.templates = [];
				this.templatesLoading = false;
			}
			this.isInitializingEntry = true;
			
			if (entryToEdit) {
				this.internalEditingEntry = entryToEdit;
				this.entry = {
					id: entryToEdit.id,
					type: entryToEdit.type,
					amountDisplay: this.$formatInputAmount(entryToEdit.amount),
					description: entryToEdit.description,
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
			} else if (templateToLoad) {
				this.internalEditingEntry = null;
				this.sourceTemplateId = templateToLoad.id || null;
				this.entry = {
					type: templateToLoad.type || 'expense',
					amountDisplay: templateToLoad.amount ? this.$formatInputAmount(templateToLoad.amount) : '',
					description: templateToLoad.description || '',
					categoryName: (() => {
						if (!templateToLoad.category_id) return '';
						const cat = this.categories.find(c => c.id === templateToLoad.category_id);
						return cat ? cat.name : '';
					})(),
					paymentPartnerName: templateToLoad.paymentPartner || (() => {
						if (!templateToLoad.payment_partner_id) return '';
						const pay = this.paymentPartners.find(p => p.id === templateToLoad.payment_partner_id);
						return pay ? pay.name : '';
					})(),
					projectId: templateToLoad.project_id || defaultProjectId || this.projectId,
					userId: this.currentUserId(),
					splitMode: templateToLoad.split_mode || 'project_shares',
					splitUserId: templateToLoad.split_user_id || null,
					date: new Date(),
					recurrenceInterval: 'none',
					recurrenceMultiplier: 1,
					recurrenceEndDate: null,
					isSubscription: !!templateToLoad.is_subscription,
					isFixedCost: !!templateToLoad.is_fixed_cost,
					isChildRelated: !!templateToLoad.is_child_related,
					isImportant: !!templateToLoad.is_important,
					needsReview: !!templateToLoad.needs_review,
					isTaxRelevant: !!templateToLoad.is_tax_relevant,
					hasReminder: false,
					reminderDate: null,
					reminderText: ''
				};
			} else if (entryToDuplicate) {
				this.internalEditingEntry = null;
				this.entry = {
					type: entryToDuplicate.type || 'expense',
					amountDisplay: entryToDuplicate.amount ? this.$formatInputAmount(entryToDuplicate.amount) : '',
					description: entryToDuplicate.description || '',
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
					date: this.entryDateFromSource(entryToDuplicate),
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
			await this.fetchDataLists(this.entry.projectId);
			this.syncLookupNamesFromIds(entryToEdit || templateToLoad || entryToDuplicate || null);
			await this.ensureProjectMembers(this.entry.projectId);
			this.syncEntryUserWithProject(this.entry.userId);
			this.isInitializingEntry = false;
			this.showPlanningOptions = this.shouldExpandPlanningOptions();
			if (entryToEdit && !this.isTemplateMode) {
				await this.fetchAttachments(entryToEdit.id);
			}
			this.focusInitialField();
		},
		focusInitialField() {
			if (this.isEditing && !this.isTemplateMode) {
				return;
			}

			this.$nextTick(() => {
				const target = this.isTemplateMode ? this.$refs.templateNameInput : this.$refs.amountInput;
				if (!target) {
					return;
				}
				target.focus();
				if (typeof target.select === 'function') {
					target.select();
				}
			});
		},
		closeModal({ preserveHistory = false, skipHistory = false } = {}) {
			if (!preserveHistory && !skipHistory) {
				this.releaseModalHistory();
			}
			this.isOpen = false;
			this.internalEditingEntry = null;
			this.isDuplicateMode = false;
			this.sourceTemplateId = null;
			this.showPlanningOptions = false;
			this.resetAttachments();
			this.closeLookup();
			this.clearMobileFocusTimer();
			this.mobileFormFieldFocused = false;
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
			if (!entryId || this.isTemplateMode || !this.$enableReceipts) {
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
		async fetchTemplates() {
			if (!this.$enableTemplates || this.isTemplateMode || this.isEditing) {
				this.templates = [];
				return;
			}

			this.templatesLoading = true;
			try {
				const res = await axios.get(generateUrl('/apps/cobudget/api/templates'));
				this.templates = this.sortTemplates(res.data || []);
			} catch (e) {
				showRequestError(e, this.$texts.entry.templatesLoadError(), 'Failed to fetch templates');
				this.templates = [];
			} finally {
				this.templatesLoading = false;
			}
		},
		async markTemplateUsed(templateId) {
			if (!templateId || !this.$enableTemplates) {
				return;
			}

			try {
				await axios.post(generateUrl(`/apps/cobudget/api/templates/${templateId}/use`));
			} catch (e) {
				// The entry save already succeeded; a stale usage counter should not block the user flow.
			}
		},
		async fetchDataLists(projectId = this.entry.projectId) {
			try {
				const params = this.$enableProjects && projectId ? { projectId } : {};
				const catRes = await axios.get(generateUrl('/apps/cobudget/api/categories'), { params })
				this.categories = (catRes.data || []).sort((a, b) => a.name.localeCompare(b.name))
				const payRes = await axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params })
				this.paymentPartners = (payRes.data || []).sort((a, b) => a.name.localeCompare(b.name))
			} catch (e) {
				showRequestError(e, this.$texts.entry.lookupsLoadError(), 'Failed to fetch categories/paymentPartners')
			}
		},
		syncLookupNamesFromIds(source) {
			if (!source) {
				return;
			}

			if (!this.entry.categoryName && source.category_id) {
				const category = this.categories.find(c => Number(c.id) === Number(source.category_id));
				this.entry.categoryName = category ? category.name : '';
			}

			if (!this.entry.paymentPartnerName && source.payment_partner_id) {
				const paymentPartner = this.paymentPartners.find(p => Number(p.id) === Number(source.payment_partner_id));
				this.entry.paymentPartnerName = paymentPartner ? paymentPartner.name : '';
			}
		},
		getProjectName(id) {
			const p = this.projects.find(p => p.id === id)
			return p ? p.name : ''
		},
		projectOptionLabel(project) {
			return `${project.name} (${this.projectShareLabel(project)})`;
		},
		projectShareLabel(project) {
			if (!this.$enableSharedProjects) {
				return this.$texts.entry.myShare(100);
			}

			const memberCount = Math.max(1, parseInt(project?.member_count, 10) || 1);
			if (memberCount === 1) {
				return this.$texts.entry.myShare(100);
			}

			const shareBasisPoints = parseInt(project?.my_share_basis_points, 10);
			if (Number.isFinite(shareBasisPoints) && shareBasisPoints > 0) {
				return this.$texts.entry.myShare(this.formatShareBasisPoints(shareBasisPoints));
			}

			return this.$texts.entry.myShare(Math.round(100 / memberCount));
		},
			formatShareBasisPoints(shareBasisPoints) {
				const value = Math.round((parseInt(shareBasisPoints, 10) || 0) / 100);
				return value.toLocaleString(undefined, { maximumFractionDigits: 0 });
			},
		shouldExpandPlanningOptions() {
			return !this.isTemplateMode && (
				this.entry.recurrenceInterval !== 'none'
				|| !!this.entry.recurrenceEndDate
				|| !!this.entry.hasReminder
				|| !!this.entry.reminderText
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
		normalizeSaveAction(action) {
			if (!action || (typeof Event !== 'undefined' && action instanceof Event)) {
				return { type: 'default' };
			}
			return action.type ? action : { type: 'default' };
		},
		handleSaveMenuAction(item) {
			if (item?.key === 'new') {
				this.saveEntry({ type: 'new' });
				return;
			}
			if (item?.template) {
				this.saveEntry({ type: 'template', template: item.template });
			}
		},
		entryDateFromSource(source) {
			if (!source?.date) {
				return new Date();
			}

			if (source.date instanceof Date) {
				return new Date(source.date);
			}

			const timestamp = Number(source.date);
			if (Number.isFinite(timestamp)) {
				return new Date(timestamp * 1000);
			}

			const parsed = new Date(source.date);
			return Number.isNaN(parsed.getTime()) ? new Date() : parsed;
		},
		buildNewEntrySeed() {
			return {
				type: this.entry.type,
				amount: '',
				description: '',
				category_name: '',
				paymentPartner: this.entry.paymentPartnerName || '',
				project_id: this.entry.projectId || null,
				user_id: this.entryUserIdForSave(),
				split_mode: this.entrySplitModeForSave(),
				split_user_id: this.entrySplitUserIdForSave(),
				date: Math.floor(this.entry.date.getTime() / 1000),
				is_subscription: false,
				is_fixed_cost: false,
				is_child_related: false,
				is_important: false,
				needs_review: false,
				is_tax_relevant: false,
				reminder_date: null,
				reminder_text: ''
			};
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
		async saveEntry(action = null) {
			const saveAction = this.normalizeSaveAction(action);
			if (!this.isTemplateMode) {
				this.evaluateAmount();
			}
			if (!this.isValid) {
				showToast(this.isTemplateMode ? this.$texts.entry.validTemplateNameRequired() : this.$texts.entry.validAmountRequired(), 'error');
				return;
			}
			
			// Warn if saving a future date outside of the planned payments flow.
			if (!this.isFutureContext && !this.isEditing) {
				const now = new Date();
				now.setHours(0,0,0,0);
				const entryDate = new Date(this.entry.date);
				entryDate.setHours(0,0,0,0);
				
				if (entryDate > now) {
					this.isOpen = false;
					const confirmed = await this.openConfirm({
						title: this.$texts.entry.futurePaymentTitle(),
						message: this.$texts.entry.futurePaymentMessage(),
						confirmLabel: this.$texts.entry.planPayment()
					});
					if (!confirmed) {
						if (!this.consumeHistoryClose()) {
							this.isOpen = true;
						}
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
			try {
				await this.ensureProjectMembers(this.entry.projectId);
				this.syncEntryUserWithProject(this.entry.userId);
				const followUpSeed = !this.isEditing && saveAction.type === 'new'
					? this.buildNewEntrySeed()
					: null;
				const followUpTemplate = !this.isEditing && saveAction.type === 'template'
					? saveAction.template
					: null;

				let categoryId = null;
				const rawCatName = this.entry.categoryName || '';
				if (typeof rawCatName === 'string' && rawCatName.trim() !== '') {
					const cName = rawCatName.trim();
					let cat = (this.categories || []).find(c => c && c.name && String(c.name).toLowerCase() === String(cName).toLowerCase());
					if (!cat) {
						const res = await axios.post(generateUrl('/apps/cobudget/api/categories'), {
							name: cName,
							type: this.entry.type,
							projectId: this.entry.projectId || null
						});
						categoryId = res.data.id;
					} else {
						categoryId = cat.id;
					}
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

				let templateSaved = false;
				if (this.isTemplateMode || this.saveAsTemplate) {
					const templatePayload = {
						name: this.templateName,
						description: this.entry.description,
						type: this.entry.type,
						amount: this.entry.amountDisplay ? this.$parseAmount(this.entry.amountDisplay) : null,
						categoryId: categoryId,
						projectId: this.entry.projectId || null,
						paymentPartnerId: paymentPartnerId,
						splitMode: this.entrySplitModeForSave(),
						splitUserId: this.entrySplitUserIdForSave(),
						isSubscription: isExpense && this.entry.isSubscription,
						isFixedCost: isExpense && this.entry.isFixedCost,
						isChildRelated: this.entry.isChildRelated,
						isImportant: this.entry.isImportant,
						needsReview: this.entry.needsReview,
						isTaxRelevant: this.entry.isTaxRelevant
					};
					await axios.post(generateUrl('/apps/cobudget/api/templates'), templatePayload);
					templateSaved = true;
				}

				if (this.isTemplateMode) {
					showToast(this.$texts.entry.templateSaved());
					this.$emit('saved');
					this.closeModal();
					this.loading = false;
					return;
				}

				if (this.isEditing) {
					await axios.put(generateUrl(`/apps/cobudget/api/entries/${this.entry.id}`), payload);
					entryPersisted = true;
					await this.uploadPendingAttachments(this.entry.id);
					showToast(this.$texts.entry.entrySaved());
				} else {
					const response = await axios.post(generateUrl('/apps/cobudget/api/entries'), payload);
					if (response.data?.id) {
						this.entry.id = response.data.id;
					}
					entryPersisted = true;
					await this.markTemplateUsed(this.sourceTemplateId);
					await this.uploadPendingAttachments(this.entry.id);
					if (templateSaved) {
						showToast(this.$texts.entry.entryAndTemplateCreated());
					} else if (!followUpSeed && !followUpTemplate) {
						showToast(this.$texts.entry.entryCreated());
					}
				}

				this.$emit('saved');

				if (followUpSeed) {
					showToast(this.$texts.entry.entryCreatedNewPrepared());
					this.closeModal({ preserveHistory: true });
					await this.$nextTick();
					await this.openModal(null, followUpSeed.project_id || null, this.isFutureContext, null, false, followUpSeed, followUpSeed.type);
					this.loading = false;
					return;
				}

				if (followUpTemplate) {
					showToast(this.$texts.entry.entryCreatedTemplateOpened(followUpTemplate.name));
					const fallbackProjectId = this.entry.projectId || null;
					this.closeModal({ preserveHistory: true });
					await this.$nextTick();
					await this.openModal(null, fallbackProjectId, this.isFutureContext, followUpTemplate, false, null, followUpTemplate.type || this.entry.type);
					this.loading = false;
					return;
				}

				this.closeModal();
			} catch (e) {
				const fallback = this.isTemplateMode
					? this.$texts.entry.templateSaveError()
					: entryPersisted
						? this.$texts.entry.entrySavedReceiptUploadError()
						: this.isEditing
						? this.$texts.entry.entrySaveError()
						: this.$texts.entry.entryCreateError();
				showRequestError(e, fallback, 'Failed to save entry')
			}
			this.loading = false;
		},
		async deleteEntry() {
			this.isOpen = false;
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
				if (!this.consumeHistoryClose()) {
					this.isOpen = true;
				}
				return;
			}
			
			this.loading = true;
			try {
				if (this.isEditing && this.isFutureContext) {
					await axios.post(generateUrl(`/apps/cobudget/api/entries/${this.entry.id}/stop-recurrence`));
					showToast(this.$texts.entry.futurePaymentDisabled());
				} else {
					await axios.delete(generateUrl(`/apps/cobudget/api/entries/${this.entry.id}`));
					showToast(this.$texts.entry.entryDeleted());
				}
				this.$emit('saved');
				this.releaseModalHistory();
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
			const sanitized = String(this.entry.amountDisplay).replace(/[^0-9.,+\-*/]/g, '');
			if (sanitized !== this.entry.amountDisplay) {
				this.entry.amountDisplay = sanitized;
			}
		}
	}
}
</script>

<style scoped>
.modal-backdrop {
	--color-main-text: var(--cobudget-text);
	--color-text-maxcontrast: var(--cobudget-text-muted);
	--color-main-background: var(--cobudget-surface);
	--color-background-hover: var(--cobudget-surface-muted);
	--color-background-dark: var(--cobudget-surface-muted);
	--color-background-darker: var(--cobudget-surface-strong);
	--color-border: var(--cobudget-border);
	--color-border-dark: var(--cobudget-border-strong);
	position: fixed;
	top: 0; left: 0; width: 100%; height: 100%;
	background: rgba(0,0,0,0.6);
	display: flex;
	justify-content: center;
	align-items: center;
	z-index: 10000;
	backdrop-filter: blur(2px);
}

.modal-content {
	position: relative;
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-text, #222);
	padding: 0;
	border-radius: var(--border-radius-large, 10px);
	width: min(640px, calc(100vw - 48px));
	max-width: 95vw;
	box-shadow: 0 10px 40px rgba(0,0,0,0.25);
	overflow: visible; /* Prevent cutting off DatePicker popover */
}

.modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 16px;
	padding: 10px 20px;
	background: var(--cobudget-surface-muted, #f9f9f9);
	border-bottom: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 10px) var(--border-radius-large, 10px) 0 0;
}

.modal-title {
	margin: 0;
	font-size: var(--cobudget-font-lg);
	font-weight: 700;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.modal-header-actions {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	flex: 0 0 auto;
}

.modal-header-save-button,
.modal-close-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
	width: var(--cobudget-icon-button-size);
	height: var(--cobudget-icon-button-size);
	min-width: var(--cobudget-icon-button-size);
	border: 0;
	border-radius: var(--cobudget-radius-sm);
	background: transparent;
	color: var(--cobudget-text);
	cursor: pointer;
	padding: 0;
}

.modal-header-save-button {
	display: none;
	color: var(--cobudget-primary, var(--color-primary, #0082c9));
}

.modal-header-save-button:disabled {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	cursor: not-allowed;
	opacity: 0.55;
}

.modal-header-save-button:hover:not(:disabled),
.modal-header-save-button:focus-visible:not(:disabled),
.modal-close-button:hover,
.modal-close-button:focus-visible {
	background: var(--cobudget-surface-strong);
	outline: none;
}

.modal-header-save-button:focus-visible,
.modal-close-button:focus-visible {
	box-shadow: var(--cobudget-focus-ring);
}

.modal-header-save-button :deep(.material-design-icon),
.modal-header-save-button :deep(.material-design-icon__svg),
.modal-close-button :deep(.material-design-icon),
.modal-close-button :deep(.material-design-icon__svg) {
	display: block;
}

form {
	display: flex;
	flex-direction: column;
	max-height: 90vh;
	margin: 0;
}

.modal-form {
	padding: 0;
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
  padding: 20px 20px;
	overflow-y: auto;
	flex-grow: 1;
}

.form-row {
	display: flex;
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
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px;
}

.entry-required-panel {
	margin-bottom: 18px;
	align-items: stretch;
}

.planning-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 16px;
	margin-top: 14px;
}

.assignment-section {

}

.assignment-section,
.planning-section {
	grid-column: 1 / -1;
	margin-top: 18px;
	padding: 8px 12px 12px;
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface-muted, #f7f7f7);
}

.assignment-section summary,
.planning-section summary {
	cursor: pointer;
  font-weight: 600;
  font-size: var(--cobudget-font-compact);
  color: var(--cobudget-text, var(--color-main-text, #333));
}

.assignment-section summary:focus-visible,
.planning-section summary:focus-visible {
	outline: 2px solid var(--color-primary, #0082c9);
	outline-offset: 2px;
	border-radius: var(--border-radius, 6px);
}

.core-type,
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

.project-assignment-row.has-project-payer,
.project-assignment-row.has-split-mode {
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.project-assignment-row.has-project-payer .detail-project,
.project-assignment-row.has-split-mode .detail-project {
	grid-column: 1 / -1;
}

.core-date,
.core-amount,
.core-template-name,
.core-template-amount,
.core-description,
.detail-category,
.detail-paymentPartner,
.detail-tags {
	grid-column: span 1;
}

.detail-attachments {
	grid-column: 1 / -1;
	margin-top: -4px;
}

.lookup-field {
	position: relative;
	width: 100%;
}

.lookup-field.has-leading-icon .form-control {
	padding-left: 52px;
}

.lookup-field.has-clear-button .form-control {
	padding-right: 44px;
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
	top: 50%;
	right: 8px;
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
	overflow-x: hidden;
	padding: 6px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface, #fff);
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
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
	cursor: pointer;
}

.lookup-option:hover,
.lookup-option.active {
	background: var(--cobudget-surface-muted, #f7f7f7);
}

.lookup-option :deep(.category-icon) {
	flex: 0 0 auto;
	margin-right: 0;
}

.lookup-option span {
	min-width: 0;
	overflow-wrap: anywhere;
}

.assignment-card,
.planning-card {
	margin: 0;
	padding: 12px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius, 6px);
	background: var(--cobudget-surface, #fff);
}

.assignment-card {
	margin-top: 14px;
}

.planning-card .form-row {
	margin-bottom: 0;
	gap: 12px;
}

.assignment-card .form-group,
.planning-card .form-group {
	margin-bottom: 0;
}

.reminder-text-row {
	margin-top: 12px;
}

.reminder-choice-field {
	flex: 1 1 66%;
	min-width: 0;
}

.reminder-date-field {
	flex: 0 1 34%;
	min-width: 170px;
	max-width: 260px;
}

.recurrence-multiplier-input {
	height: 44px !important;
	padding: 10px 4px !important;
	text-align: center;
}

.type-group {
	margin-bottom: 0;
}
.type-toggle {
	display: flex;
	width: 100%;
	background: var(--cobudget-surface-muted, var(--color-background-dark, #eee));
	border-radius: var(--border-radius-large, 8px);
	padding: 4px;
	box-sizing: border-box;
}
.type-btn {
	flex: 1;
	padding: 10px 0;
	text-align: center;
	border: none;
	background: transparent;
	border-radius: var(--border-radius, 6px);
	font-size: var(--cobudget-font-ui);
	font-weight: 600;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	cursor: pointer;
	transition: all 0.2s ease;
}

.type-btn:focus-visible,
.lookup-option:focus-visible {
	outline: 2px solid var(--color-primary-element, var(--color-primary, #0082c9));
	outline-offset: 2px;
}

.type-btn.active.expense-active {
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-error);
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.type-btn.active.income-active {
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-success);
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-group {
	margin-bottom: 0;
}

.form-group label {
	display: block;
	font-weight: 600;
	font-size: var(--cobudget-font-compact);
	color: var(--cobudget-text, var(--color-main-text, #333));
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

.form-control:focus {
	border-color: var(--color-primary, #0082c9);
	outline: none;
}

.amount-input {
	font-weight: 600;
	font-family: monospace;
	font-size: var(--cobudget-font-md);
	height: 44px !important;
	padding: 0 12px !important;
	line-height: 40px;
	text-align: right;
	transition: background-color 0.2s, color 0.2s;
}

.amount-col label {
	text-align: right;
}

.amount-input.bg-expense {
	border-color: var(--cobudget-error) !important;
	background-color: var(--cobudget-error-light) !important;
	color: var(--cobudget-error) !important;
}

.amount-input.bg-income {
	border-color: var(--cobudget-success) !important;
	background-color: var(--cobudget-success-light) !important;
	color: var(--cobudget-success) !important;
}

.amount-input.bg-expense::placeholder {
	color: var(--cobudget-error);
	opacity: 0.7;
}

.amount-input.bg-income::placeholder {
	color: var(--cobudget-success);
	opacity: 0.7;
}

.date-col :deep(input) {
	height: 44px !important;
	line-height: 40px !important;
	font-size: var(--cobudget-font-ui) !important;
	border-radius: 6px !important;
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
	background: var(--cobudget-surface-muted, var(--color-background-dark, #eee));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border-radius: 4px;
	font-size: var(--cobudget-font-xs);
	font-weight: 600;
	transition: all 0.2s;
	border: 1px solid transparent;
	cursor: pointer;
}

.tag-toggle:hover .tag-btn {
	background: var(--cobudget-surface-strong, var(--color-background-darker, #ddd));
}

.tag-toggle input:focus-visible + .tag-btn {
	outline: 2px solid var(--color-primary, #0082c9);
	outline-offset: 2px;
	box-shadow: 0 0 0 3px var(--color-primary-light, #e0f2fe);
}

.tag-btn.active {
	background: var(--cobudget-primary-light, var(--color-primary-light, #e0f2fe));
	color: var(--cobudget-primary, var(--color-primary, #0082c9));
	border-color: var(--cobudget-primary, var(--color-primary, #0082c9));
}

.attachments-inline {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 8px;
	margin-top: 10px;
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
  padding: 10px 20px;
	margin-top: 0;
	border-top: 1px solid var(--cobudget-border, #eee);
	background: var(--cobudget-surface, #fff);
	flex-shrink: 0;
	border-radius: 0 0 10px 10px;
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
	align-items: flex-end;
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
	grid-template-columns: minmax(128px, 150px) minmax(0, 1fr) minmax(0, 1fr);
}

.recurrence-multiplier-field label {
	white-space: nowrap;
}

.recurrence-end-field {
	grid-column: auto;
}

.recurrence-inputs .recurrence-multiplier-input {
	width: 100% !important;
	min-width: 0;
}

.recurrence-inputs .select-control {
	width: 100%;
	min-width: 0;
}

@media (max-width: 780px) {
	.modal-content {
		width: min(560px, calc(100vw - 24px));
	}

	.modal-header-save-button {
		display: inline-flex;
	}

	.modal-body {
		padding: 24px;
	}

	.entry-details-grid,
	.planning-grid {
		grid-template-columns: 1fr;
	}

	.entry-details-grid {
		row-gap: 22px;
	}

	.core-description,
	.core-template-name,
	.core-template-amount,
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

	.core-type {
		grid-column: 1 / -1;
	}

	.form-row {
		flex-direction: column;
	}

	.recurrence-inputs {
		grid-template-columns: 1fr;
	}

	.recurrence-inputs.is-recurring {
		grid-template-columns: minmax(112px, 0.42fr) minmax(0, 1fr);
	}

	.recurrence-inputs.is-recurring .recurrence-end-field {
		grid-column: 1 / -1;
	}

	.recurrence-multiplier-field label {
		white-space: nowrap;
	}

	.reminder-group .form-row.align-items-end {
		flex-direction: row;
		align-items: flex-end;
	}

	.reminder-choice-field {
		flex: 1 1 auto;
	}

	.reminder-date-field {
		flex: 0 0 132px;
		min-width: 132px;
		max-width: 150px;
	}

	.reminder-text-row {
		flex-direction: column;
	}
}

@media (max-width: 600px) {
	.modal-content {
		width: 100%;
		height: 100%;
		max-width: 100%;
		border-radius: 0;
		display: flex;
		flex-direction: column;
	}
	form {
		height: 100%;
		max-height: 100%;
		flex-grow: 1;
	}
	.form-actions {
		border-radius: 0;
		padding: 10px 20px;
	}

	.modal-header-save-button,
	.modal-close-button {
		width: var(--cobudget-mobile-touch-size);
		min-width: var(--cobudget-mobile-touch-size);
		height: var(--cobudget-mobile-touch-size);
		min-height: var(--cobudget-mobile-touch-size);
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

@media (prefers-color-scheme: dark) {
	.amount-input.bg-expense {
		color: #ffb4ba !important;
	}

	.amount-input.bg-income {
		color: #b8f7c5 !important;
	}

	.amount-input.bg-expense::placeholder,
	.amount-input.bg-income::placeholder {
		opacity: 0.85;
	}
}
</style>
