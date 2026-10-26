<template>
	<div class="project-scoped-settings">
		<div class="project-settings-grid">
			<section class="project-settings-column">
				<h3>{{ $texts.projectScoped.categories() }}</h3>
				<p class="settings-hint">{{ $texts.projectScoped.categoriesHint() }}</p>

				<CbTypeTabs v-if="$enableIncomes" v-model="categoryTab" :label="$texts.projectScoped.categories()" />

				<form class="add-form" @submit.prevent="addCategory">
					<IconPicker v-model="newCategoryIcon" />
					<input v-model="newCategory" class="form-control" data-cobudget-field="compact" type="text" :placeholder="$texts.projectScoped.newCategory()" required>
					<NcButton variant="primary" type="submit" :disabled="loading || !newCategory.trim()" :aria-label="$texts.common.add()">
						{{ $texts.common.add() }}
					</NcButton>
				</form>

				<SettingsList :items="filteredCategories" :empty-text="$texts.projectScoped.noCategories()">
					<template #item="{ item: category }">
						<div class="settings-list-info">
							<IconPicker
								:value="category.icon || 'Shape'"
								:disabled="category.is_global"
								@input="updateCategoryIcon(category, $event)" />
							<div class="category-list-label">
								<small v-if="category.code" class="category-list-code">{{ category.code }}</small>
								<span>{{ category.name }}</span>
							</div>
							<span v-if="category.is_global" class="badge-global" data-cobudget-badge="info">{{ $texts.common.global() }}</span>
						</div>
						<SettingsItemActions
							class="settings-list-actions"
							:can-edit="!category.is_global"
							:can-delete="!category.is_global && !category.in_use && !category.has_children"
							:can-hide="category.is_global && !category.is_hidden"
							:can-unhide="category.is_global && category.is_hidden"
							:delete-label="category.in_use ? $texts.projectScoped.inUse() : $texts.common.delete()"
							@edit="openRenameItem('category', category)"
							@delete="deleteCategory(category)"
							@hide="hideCategory(category)"
							@unhide="unhideCategory(category)" />
					</template>
				</SettingsList>
			</section>

			<section class="project-settings-column">
				<h3>{{ $texts.projectScoped.paymentPartners() }}</h3>
				<p class="settings-hint">{{ $texts.projectScoped.paymentPartnersHint() }}</p>

				<CbTypeTabs v-if="$enableIncomes" v-model="paymentPartnerTab" :label="$texts.projectScoped.paymentPartners()" />

				<form class="add-form" @submit.prevent="addPaymentPartner">
					<input v-model="newPaymentPartner" class="form-control" data-cobudget-field="compact" type="text" :placeholder="paymentPartnerPlaceholder" required>
					<NcButton variant="primary" type="submit" :disabled="loading || !newPaymentPartner.trim()" :aria-label="$texts.common.add()">
						{{ $texts.common.add() }}
					</NcButton>
				</form>

				<SettingsList :items="filteredPaymentPartners" :empty-text="paymentPartnerEmptyText">
					<template #item="{ item: paymentPartner }">
						<div class="settings-list-info">
							<div class="category-list-label">
								<small v-if="paymentPartner.number" class="category-list-code">{{ paymentPartner.number }}</small>
								<span>{{ paymentPartner.name }}</span>
							</div>
						</div>
						<SettingsItemActions
							class="settings-list-actions"
							:can-edit="true"
							:can-delete="!paymentPartner.in_use"
							:delete-label="paymentPartner.in_use ? $texts.projectScoped.inUse() : $texts.common.delete()"
							@edit="openRenameItem('paymentPartner', paymentPartner)"
							@delete="deletePaymentPartner(paymentPartner)" />
					</template>
				</SettingsList>
			</section>
		</div>

		<Teleport to="body">
			<div
				v-if="renameItem"
				class="settings-modal-backdrop"
				tabindex="-1"
				@click.self="closeRenameModal"
				@keydown.esc.stop.prevent="closeRenameModal">
				<div
					class="settings-modal cobudget-dialog cobudget-dialog--contained"
					:class="{ 'settings-modal--payment-partner': renameType === 'paymentPartner' }"
					role="dialog"
					aria-modal="true"
					aria-labelledby="project-scoped-rename-title">
					<div class="modal-header cobudget-dialog-header">
						<h2 id="project-scoped-rename-title">{{ renameTitle }}</h2>
						<button
							type="button"
							class="settings-modal-close-button cobudget-dialog-close cobudget-focusable"
							:aria-label="$texts.common.close()"
							:title="$texts.common.close()"
							@click="closeRenameModal">
							<CloseIcon :size="24" aria-hidden="true" />
						</button>
					</div>
					<p class="modal-note">
						{{ renameType === 'paymentPartner'
							? $texts.paymentPartnerDetails.internalNote()
							: $texts.projectScoped.renameNote() }}
					</p>
					<form @submit.prevent="saveRenameItem">
						<template v-if="renameType === 'category'">
							<div class="rename-fields has-category-number">
								<div class="form-group">
									<label for="project-scoped-category-code">{{ $texts.common.number() }}</label>
									<input
										id="project-scoped-category-code"
										ref="renameCodeInput"
										v-model="renameCode"
										class="form-control" data-cobudget-field="compact"
										type="text"
										maxlength="128"
										autocomplete="off"
										spellcheck="false">
								</div>
								<div class="form-group">
									<label for="project-scoped-name">{{ $texts.common.name() }}</label>
									<input id="project-scoped-name" ref="renameInput" v-model="renameName" class="form-control" data-cobudget-field="compact" type="text" maxlength="128" required>
								</div>
							</div>
							<div class="form-group category-parent-field">
								<label for="project-scoped-category-parent">{{ $texts.common.parentCategoryOptional() }}</label>
								<select
									id="project-scoped-category-parent"
									v-model="renameParentCategoryId"
									class="form-control" data-cobudget-field="compact"
									:disabled="!!renameItem.has_children">
									<option value="">{{ $texts.common.noParentMainCategory() }}</option>
									<option
										v-for="category in renameParentCategoryOptions"
										:key="category.id"
										:value="String(category.id)">
										{{ category.name }}
									</option>
								</select>
								<p v-if="renameItem.has_children" class="category-parent-hint">
									{{ $texts.common.mainCategoryHasChildrenHint() }}
								</p>
							</div>
						</template>
						<PaymentPartnerEditFields
							v-else
							ref="renamePaymentPartnerFields"
							id-prefix="project-payment-partner"
							:name="renameName"
							:details="renamePaymentPartnerDetails"
							@update:name="renameName = $event"
							@update:details="renamePaymentPartnerDetails = $event" />
						<p v-if="renameError" class="modal-error">{{ renameError }}</p>
						<ModalActions
							:cancel-disabled="renameSaving"
							:primary-disabled="!canRename"
							:primary-busy="renameSaving"
							:primary-label="$texts.common.save()"
							:primary-busy-label="$texts.common.saveBusy()"
							@cancel="closeRenameModal" />
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
	</div>
</template>

<script>
import CbTypeTabs from './CbTypeTabs.vue'
import { defineAsyncComponent } from 'vue'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import axios from '../services/http'
import ConfirmModal from './ConfirmModal.vue'
import ModalActions from './ModalActions.vue'
import PaymentPartnerEditFields from './PaymentPartnerEditFields.vue'
import SettingsItemActions from './SettingsItemActions.vue'
import SettingsList from './SettingsList.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import { extractError, showRequestError, showToast } from '../services/notifications'
import { mainCategoryOptions, sortCategoriesHierarchically } from '../utils/categoryHierarchy'
import {
	createPaymentPartnerDetails,
	isValidPaymentPartnerEmail,
	paymentPartnerDetailsChanged,
	paymentPartnerDetailsPayload
} from '../utils/paymentPartnerDetails'

const IconPicker = defineAsyncComponent(() => import(/* webpackChunkName: "cobudget-icon-picker" */ './IconPicker.vue'))

export default {
	name: 'ProjectScopedSettings',
	components: {
		CbTypeTabs,
		NcButton,
		IconPicker,
		ConfirmModal,
		ModalActions,
		PaymentPartnerEditFields,
		SettingsItemActions,
		SettingsList,
		CloseIcon
	},
	props: {
		projectId: {
			type: [Number, String],
			required: true
		}
	},
	emits: ['changed'],
	data() {
		return {
			loading: false,
			categories: [],
			parentCategories: [],
			paymentPartners: [],
			categoryTab: 'expense',
			paymentPartnerTab: 'expense',
			newCategory: '',
			newCategoryIcon: 'Shape',
			newPaymentPartner: '',
			renameType: '',
			renameItem: null,
			renameName: '',
			renameCode: '',
			renameParentCategoryId: '',
			renamePaymentPartnerDetails: createPaymentPartnerDetails(),
			renameError: '',
			renameSaving: false,
			confirmDialog: null
		}
	},
	computed: {
		activeCategoryTab() {
			return this.$enableIncomes ? this.categoryTab : 'expense';
		},
		activePaymentPartnerTab() {
			return this.$enableIncomes ? this.paymentPartnerTab : 'expense';
		},
		filteredCategories() {
			return sortCategoriesHierarchically(this.categories.filter(category => category.type === this.activeCategoryTab));
		},
		filteredPaymentPartners() {
			return this.paymentPartners.filter(paymentPartner => paymentPartner.type === this.activePaymentPartnerTab);
		},
		paymentPartnerPlaceholder() {
			return this.activePaymentPartnerTab === 'income'
				? this.$texts.projectScoped.newPaymentPartnerIncome()
				: this.$texts.projectScoped.newPaymentPartnerExpense();
		},
		paymentPartnerEmptyText() {
			return this.activePaymentPartnerTab === 'income'
				? this.$texts.projectScoped.noIncomePaymentPartners()
				: this.$texts.projectScoped.noExpensePaymentPartners();
		},
		renameTitle() {
			return this.renameType === 'category'
				? this.$texts.projectScoped.editCategory()
				: this.$texts.projectScoped.editPaymentPartner();
		},
		renameParentCategoryOptions() {
			if (this.renameType !== 'category' || !this.renameItem) {
				return [];
			}
			return mainCategoryOptions(this.parentCategories, this.renameItem);
		},
		canRename() {
			if (!this.renameItem || !this.renameName.trim() || this.renameSaving) {
				return false;
			}
			if (this.renameType === 'paymentPartner'
				&& !isValidPaymentPartnerEmail(this.renamePaymentPartnerDetails.email)) {
				return false;
			}

			const nameChanged = this.renameName.trim() !== this.renameItem.name;
			const codeChanged = this.renameType === 'category'
				&& this.renameCode.trim() !== String(this.renameItem.code || '').trim();
			const parentChanged = this.renameType === 'category'
				&& this.renameParentCategoryId !== String(this.renameItem.parent_category_id || '');
			const detailsChanged = this.renameType === 'paymentPartner'
				&& paymentPartnerDetailsChanged(this.renameItem, this.renamePaymentPartnerDetails);
			return nameChanged || codeChanged || parentChanged || detailsChanged;
		}
	},
	watch: {
		projectId: {
			immediate: true,
			handler() {
				this.fetchData();
			}
		}
	},
	methods: {
		requestParams() {
			return { projectId: this.projectId };
		},
		openConfirm({ title, message, confirmLabel, confirmVariant = 'primary' }) {
			return new Promise(resolve => {
				this.confirmDialog = { title, message, confirmLabel, confirmVariant, resolve };
			});
		},
		resolveConfirm(confirmed) {
			const resolver = this.confirmDialog?.resolve;
			this.confirmDialog = null;
			if (resolver) {
				resolver(confirmed);
			}
		},
		sortByName(items) {
			return (items || []).sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }));
		},
		boolValue(value) {
			return value === true || value === 1 || value === '1' || value === 'true';
		},
		normalizeCategory(category) {
			return {
				...category,
				is_global: this.boolValue(category?.is_global),
				is_hidden: this.boolValue(category?.is_hidden),
				in_use: this.boolValue(category?.in_use),
				has_children: this.boolValue(category?.has_children)
			};
		},
		async fetchData() {
			if (!this.projectId) {
				return;
			}

			this.loading = true;
			try {
				const params = this.requestParams();
				const [categoryRes, paymentPartnerRes] = await Promise.all([
					axios.get(generateUrl('/apps/cobudget/api/categories/settings'), { params }),
					axios.get(generateUrl('/apps/cobudget/api/payment-partners/settings'), { params })
				]);
				this.categories = sortCategoriesHierarchically((categoryRes.data || []).map(this.normalizeCategory));
				this.parentCategories = [...this.categories];
				this.paymentPartners = this.sortByName(paymentPartnerRes.data || []);
			} catch (e) {
				showRequestError(e, this.$texts.projectScoped.loadError(), 'Failed to fetch project-scoped settings');
			} finally {
				this.loading = false;
			}
		},
		async addCategory() {
			if (!this.newCategory.trim()) {
				return;
			}

			this.loading = true;
			try {
				await axios.post(generateUrl('/apps/cobudget/api/categories'), {
					name: this.newCategory.trim(),
					icon: this.newCategoryIcon,
					type: this.activeCategoryTab,
					projectId: this.projectId
				});
				this.newCategory = '';
				this.newCategoryIcon = 'Shape';
				await this.fetchData();
				this.$emit('changed');
				showToast(this.$texts.projectScoped.categorySaved());
			} catch (e) {
				showRequestError(e, this.$texts.projectScoped.categoryCreateError(), 'Failed to create project category');
			} finally {
				this.loading = false;
			}
		},
		async addPaymentPartner() {
			if (!this.newPaymentPartner.trim()) {
				return;
			}

			this.loading = true;
			try {
				await axios.post(generateUrl('/apps/cobudget/api/payment-partners'), {
					name: this.newPaymentPartner.trim(),
					type: this.activePaymentPartnerTab,
					projectId: this.projectId
				});
				this.newPaymentPartner = '';
				await this.fetchData();
				this.$emit('changed');
				showToast(this.$texts.projectScoped.paymentPartnerSaved());
			} catch (e) {
				showRequestError(e, this.$texts.projectScoped.paymentPartnerCreateError(), 'Failed to create project paymentPartner');
			} finally {
				this.loading = false;
			}
		},
		async updateCategoryIcon(category, icon) {
			if (category.is_global) {
				return;
			}
			try {
				await axios.put(generateUrl(`/apps/cobudget/api/categories/${category.id}/icon`), { icon });
				await this.fetchData();
				this.$emit('changed');
				showToast(this.$texts.projectScoped.categorySaved());
			} catch (e) {
				showRequestError(e, this.$texts.projectScoped.categorySaveError(), 'Failed to update project category icon');
			}
		},
		openRenameItem(type, item) {
			if (type === 'category' && item.is_global) {
				return;
			}
			this.renameType = type;
			this.renameItem = item;
			this.renameName = item.name;
			this.renameCode = type === 'category' ? String(item.code || '') : '';
			this.renameParentCategoryId = type === 'category' ? String(item.parent_category_id || '') : '';
			this.renamePaymentPartnerDetails = type === 'paymentPartner'
				? createPaymentPartnerDetails(item)
				: createPaymentPartnerDetails();
			this.renameError = '';
			this.renameSaving = false;
			this.$nextTick(() => {
				if (type === 'paymentPartner') {
					this.$refs.renamePaymentPartnerFields?.focusName();
					return;
				}
				this.$refs.renameCodeInput?.focus();
				this.$refs.renameCodeInput?.select();
			});
		},
		closeRenameModal() {
			if (this.renameSaving) {
				return;
			}
			this.resetRenameModal();
		},
		resetRenameModal() {
			this.renameType = '';
			this.renameItem = null;
			this.renameName = '';
			this.renameCode = '';
			this.renameParentCategoryId = '';
			this.renamePaymentPartnerDetails = createPaymentPartnerDetails();
			this.renameError = '';
			this.renameSaving = false;
		},
		async saveRenameItem() {
			if (!this.canRename) {
				return;
			}

			const item = this.renameItem;
			const endpoint = this.renameType === 'category'
				? `/apps/cobudget/api/categories/${item.id}`
				: `/apps/cobudget/api/payment-partners/${item.id}`;
			const successMessage = this.renameType === 'category'
				? this.$texts.projectScoped.categorySaved()
				: this.$texts.projectScoped.paymentPartnerSaved();
			const payload = { name: this.renameName.trim() };
			if (this.renameType === 'category') {
				payload.code = this.renameCode.trim();
				payload.parentCategoryId = this.renameParentCategoryId
					? Number(this.renameParentCategoryId)
					: 0;
			} else {
				Object.assign(payload, paymentPartnerDetailsPayload(this.renamePaymentPartnerDetails));
			}

			this.renameSaving = true;
			this.renameError = '';
			try {
				await axios.put(generateUrl(endpoint), payload);
				this.renameSaving = false;
				this.resetRenameModal();
				await this.fetchData();
				this.$emit('changed');
				showToast(successMessage);
			} catch (e) {
				this.renameError = extractError(e, this.$texts.settings.genericSaveError());
			} finally {
				this.renameSaving = false;
			}
		},
		async deleteCategory(category) {
			if (category.is_global) {
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.projectScoped.deleteCategoryTitle(),
				message: this.$texts.projectScoped.deleteCategoryMessage(),
				confirmLabel: this.$texts.projectScoped.deleteCategoryConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) {
				return;
			}

			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/categories/${category.id}`));
				await this.fetchData();
				this.$emit('changed');
				showToast(this.$texts.projectScoped.categoryDeleted());
			} catch (e) {
				showRequestError(e, this.$texts.projectScoped.categoryDeleteError(), 'Failed to delete project category');
			}
		},
		async hideCategory(category) {
			if (!category.is_global || category.is_hidden) {
				return;
			}
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/categories/${category.id}/hide`), this.requestParams());
				await this.fetchData();
				this.$emit('changed');
				showToast(this.$texts.projectScoped.categoryHidden());
			} catch (e) {
				showRequestError(e, this.$texts.projectScoped.categoryHideError(), 'Failed to hide global category for project');
			}
		},
		async unhideCategory(category) {
			if (!category.is_global || !category.is_hidden) {
				return;
			}
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/categories/${category.id}/unhide`), this.requestParams());
				await this.fetchData();
				this.$emit('changed');
				showToast(this.$texts.projectScoped.categoryShown());
			} catch (e) {
				showRequestError(e, this.$texts.projectScoped.categoryShowError(), 'Failed to show global category for project');
			}
		},
		async deletePaymentPartner(paymentPartner) {
			const confirmed = await this.openConfirm({
				title: this.$texts.projectScoped.deletePaymentPartnerTitle(),
				message: this.$texts.projectScoped.deletePaymentPartnerMessage(),
				confirmLabel: this.$texts.projectScoped.deletePaymentPartnerConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) {
				return;
			}

			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/payment-partners/${paymentPartner.id}`));
				await this.fetchData();
				this.$emit('changed');
				showToast(this.$texts.projectScoped.paymentPartnerDeleted());
			} catch (e) {
				showRequestError(e, this.$texts.projectScoped.paymentPartnerDeleteError(), 'Failed to delete project paymentPartner');
			}
		}
	}
}
</script>

<style scoped>
.project-scoped-settings {
	margin-top: 0;
}

.project-settings-grid {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	gap: 28px;
}

.project-settings-column h3 {
	margin-top: 0;
	font-size: var(--cobudget-font-section-title);
}

.settings-hint {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	margin-top: 0;
	margin-bottom: 16px;
}

.settings-list-info {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.category-list-label {
	display: flex;
	flex-direction: column;
	min-width: 0;
	line-height: 1.25;
}

.category-list-label > span {
	color: inherit;
	overflow-wrap: anywhere;
}

.category-list-code {
	color: var(--cobudget-text-muted, var(--color-text-light));
	font-size: var(--cobudget-font-sm);
	line-height: 1.3;
	overflow-wrap: anywhere;
}

:deep(.settings-list-item.is-hidden) .settings-list-info {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.add-form {
	display: flex;
	gap: 10px;
	margin: 10px 0 20px;
	align-items: center;
}

.add-form .form-control {
	flex: 1;
}

.settings-modal-backdrop {
	--color-main-text: var(--cobudget-text);
	--color-text-maxcontrast: var(--cobudget-text-muted);
	--color-main-background: var(--cobudget-surface);
	--color-background-hover: var(--cobudget-surface-muted);
	--color-background-dark: var(--cobudget-surface-muted);
	--color-border: var(--cobudget-border);
	--color-border-dark: var(--cobudget-border-strong);

	position: fixed;
	inset: 0;
	background: var(--cobudget-dialog-backdrop);
	backdrop-filter: blur(2px);
	z-index: 10000;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px;
}

.settings-modal {
	width: min(560px, 100%);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border-radius: var(--cobudget-dialog-radius);
	padding: var(--cobudget-dialog-padding);
	box-shadow: var(--cobudget-dialog-shadow);
}

.settings-modal.settings-modal--payment-partner {
	box-sizing: border-box;
	max-height: calc(100vh - 40px);
	overflow-y: auto;
	width: min(760px, 100%);
}

.modal-note {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #777));
	margin-bottom: 20px;
}

.modal-error {
	color: var(--cobudget-error);
	margin: 8px 0 0;
}

.rename-fields {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: 12px;
	margin-bottom: 16px;
}

.rename-fields.has-category-number {
	grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
}

.rename-fields .form-group {
	min-width: 0;
}

.category-parent-hint {
	color: var(--cobudget-text-muted, var(--color-text-light));
	font-size: var(--cobudget-font-sm);
	line-height: 1.4;
	margin: 6px 0 0;
}

.form-group label {
	display: block;
  color: var(--cobudget-text-muted, #888);
  font-size: var(--cobudget-font-label);
  letter-spacing: normal;

}

@media (max-width: 900px) {
	.project-settings-grid {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 768px) {
	.rename-fields.has-category-number {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
