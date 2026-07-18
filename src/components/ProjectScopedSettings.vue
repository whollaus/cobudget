<template>
	<div class="project-scoped-settings">
		<div class="project-settings-grid">
			<section class="project-settings-column">
				<h3>{{ $texts.projectScoped.categories() }}</h3>
				<p class="settings-hint">{{ $texts.projectScoped.categoriesHint() }}</p>

				<div class="tabs-container" v-if="$enableIncomes">
					<button class="tab-button" :class="{ active: categoryTab === 'expense' }" type="button" @click.prevent="categoryTab = 'expense'">{{ $texts.common.expense() }}</button>
					<button class="tab-button" :class="{ active: categoryTab === 'income' }" type="button" @click.prevent="categoryTab = 'income'">{{ $texts.common.income() }}</button>
				</div>

				<form class="add-form" @submit.prevent="addCategory">
					<IconPicker v-model="newCategoryIcon" />
					<input v-model="newCategory" class="form-control" type="text" :placeholder="$texts.projectScoped.newCategory()" required>
					<NcButton type="primary" native-type="submit" :disabled="loading || !newCategory.trim()" :aria-label="$texts.common.add()">
						{{ $texts.common.add() }}
					</NcButton>
				</form>

				<SettingsList :items="filteredCategories" :empty-text="$texts.projectScoped.noCategories()">
					<template #item="{ item: category }">
						<div class="settings-list-info">
							<IconPicker :value="category.icon || 'Shape'" @input="updateCategoryIcon(category, $event)" />
							<span>{{ category.name }}</span>
						</div>
						<SettingsItemActions
							class="settings-list-actions"
							:can-edit="true"
							:can-delete="!category.in_use"
							:delete-label="category.in_use ? $texts.projectScoped.inUse() : $texts.common.delete()"
							@edit="openRenameItem('category', category)"
							@delete="deleteCategory(category)" />
					</template>
				</SettingsList>
			</section>

			<section class="project-settings-column">
				<h3>{{ $texts.projectScoped.paymentPartners() }}</h3>
				<p class="settings-hint">{{ $texts.projectScoped.paymentPartnersHint() }}</p>

				<div class="tabs-container" v-if="$enableIncomes">
					<button class="tab-button" :class="{ active: paymentPartnerTab === 'expense' }" type="button" @click.prevent="paymentPartnerTab = 'expense'">{{ $texts.common.expense() }}</button>
					<button class="tab-button" :class="{ active: paymentPartnerTab === 'income' }" type="button" @click.prevent="paymentPartnerTab = 'income'">{{ $texts.common.income() }}</button>
				</div>

				<form class="add-form" @submit.prevent="addPaymentPartner">
					<input v-model="newPaymentPartner" class="form-control" type="text" :placeholder="paymentPartnerPlaceholder" required>
					<NcButton type="primary" native-type="submit" :disabled="loading || !newPaymentPartner.trim()" :aria-label="$texts.common.add()">
						{{ $texts.common.add() }}
					</NcButton>
				</form>

				<SettingsList :items="filteredPaymentPartners" :empty-text="paymentPartnerEmptyText">
					<template #item="{ item: paymentPartner }">
						<div class="settings-list-info">
							<span>{{ paymentPartner.name }}</span>
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
				<div class="settings-modal" role="dialog" aria-modal="true" aria-labelledby="project-scoped-rename-title">
					<div class="modal-header">
						<h2 id="project-scoped-rename-title">{{ renameTitle }}</h2>
						<button
							type="button"
							class="settings-modal-close-button"
							:aria-label="$texts.common.close()"
							:title="$texts.common.close()"
							@click="closeRenameModal">
							<CloseIcon :size="22" aria-hidden="true" />
						</button>
					</div>
					<p class="modal-note">
						{{ $texts.projectScoped.renameNote() }}
					</p>
					<form @submit.prevent="saveRenameItem">
						<div class="form-group">
							<label for="project-scoped-name">{{ $texts.common.name() }}</label>
							<input id="project-scoped-name" ref="renameInput" v-model="renameName" class="form-control" type="text" required>
						</div>
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
import { defineAsyncComponent } from 'vue'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import axios from '../services/http'
import ConfirmModal from './ConfirmModal.vue'
import ModalActions from './ModalActions.vue'
import SettingsItemActions from './SettingsItemActions.vue'
import SettingsList from './SettingsList.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import { extractError, showRequestError, showToast } from '../services/notifications'

const IconPicker = defineAsyncComponent(() => import(/* webpackChunkName: "cobudget-icon-picker" */ './IconPicker.vue'))

export default {
	name: 'ProjectScopedSettings',
	components: {
		NcButton,
		IconPicker,
		ConfirmModal,
		ModalActions,
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
			paymentPartners: [],
			categoryTab: 'expense',
			paymentPartnerTab: 'expense',
			newCategory: '',
			newCategoryIcon: 'Shape',
			newPaymentPartner: '',
			renameType: '',
			renameItem: null,
			renameName: '',
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
			return this.categories.filter(category => category.type === this.activeCategoryTab);
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
		canRename() {
			return this.renameItem
				&& this.renameName.trim()
				&& this.renameName.trim() !== this.renameItem.name
				&& !this.renameSaving;
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
				this.categories = this.sortByName(categoryRes.data || []);
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
			this.renameType = type;
			this.renameItem = item;
			this.renameName = item.name;
			this.renameError = '';
			this.renameSaving = false;
			this.$nextTick(() => {
				this.$refs.renameInput?.focus();
				this.$refs.renameInput?.select();
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

			this.renameSaving = true;
			this.renameError = '';
			try {
				await axios.put(generateUrl(endpoint), { name: this.renameName.trim() });
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
}

.settings-hint {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	margin-top: 0;
	margin-bottom: 16px;
}

.form-control {
	width: 100%;
	height: 34px;
	padding: 0 12px;
	border: 1px solid var(--cobudget-border-strong, #888);
	border-radius: var(--border-radius, 6px);
	font-size: var(--cobudget-font-ui);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	box-sizing: border-box;
}

.settings-list-info {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

:deep(.settings-list-item.is-hidden) .settings-list-info {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.form-control:focus {
	border-color: var(--color-primary, #0082c9);
	outline: none;
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

.tabs-container {
	display: flex;
	gap: 4px;
	width: 100%;
	margin: 18px 0 18px;
	padding: 4px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface-muted, var(--color-background-dark, #eee));
	box-sizing: border-box;
}

.tab-button {
	flex: 1;
	min-width: 0;
	background: transparent;
	border: none;
	border-radius: calc(var(--border-radius-large, 8px) - 3px);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	cursor: pointer;
	font-size: var(--cobudget-font-base);
	font-weight: 600;
	line-height: 1.3;
	padding: 9px 12px;
	text-align: center;
	transition: background-color 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
}

.tab-button:hover {
	background-color: var(--cobudget-surface-muted, #f6f6f6);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.tab-button.active {
	background: var(--cobudget-surface, var(--cobudget-page-background, #fff));
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
	color: var(--color-primary, #0076a8);
	cursor: default;
}

.tab-button.active:hover {
	background: var(--cobudget-surface, var(--cobudget-page-background, #fff));
	color: var(--color-primary, #0076a8);
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
	background: rgba(0, 0, 0, 0.45);
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
	border-radius: var(--border-radius-large, 8px);
	padding: 28px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	margin: -4px 0 20px;
	padding-bottom: 14px;
	border-bottom: 1px solid var(--cobudget-border, var(--color-border, #ddd));
}

.modal-header h2 {
	margin: 0;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.settings-modal-close-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
	width: var(--cobudget-icon-button-size, 44px);
	height: var(--cobudget-icon-button-size, 44px);
	min-width: var(--cobudget-icon-button-size, 44px);
	padding: 0;
	border: 0;
	border-radius: var(--cobudget-radius-sm, 8px);
	background: transparent;
	color: var(--cobudget-text, var(--color-main-text, #222));
	cursor: pointer;
}

.settings-modal-close-button:hover,
.settings-modal-close-button:focus-visible {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5));
	outline: none;
}

.settings-modal-close-button:focus-visible {
	box-shadow: var(--cobudget-focus-ring, 0 0 0 2px var(--color-primary, #0082c9));
}

.settings-modal-close-button :deep(.material-design-icon),
.settings-modal-close-button :deep(.material-design-icon__svg) {
	display: block;
}

.modal-note {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #777));
	margin-bottom: 20px;
}

.modal-error {
	color: var(--cobudget-error);
	margin: 8px 0 0;
}

.form-group label {
	display: block;
  color: var(--cobudget-text-muted, #888);
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;

}

@media (max-width: 900px) {
	.project-settings-grid {
		grid-template-columns: 1fr;
	}
}
</style>
