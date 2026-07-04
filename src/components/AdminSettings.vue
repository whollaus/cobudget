<template>
	<div class="cobudget-admin-settings settings-section">
		<h2>{{ $texts.admin.title() }}</h2>
		<p class="settings-hint">{{ $texts.admin.hint() }}</p>

		<div class="integrity-card">
			<div class="integrity-card-header">
				<div>
					<h3>{{ $texts.admin.dataQuality() }}</h3>
					<p>{{ $texts.admin.dataQualityHint() }}</p>
				</div>
				<div class="integrity-actions">
					<NcButton type="secondary" :disabled="integrityLoading || integrityRepairing" @click="fetchIntegrityReport">
						{{ $texts.admin.checkNow() }}
					</NcButton>
					<NcButton
						v-if="integrityOrphanCount > 0"
						type="primary"
						:disabled="integrityLoading || integrityRepairing"
						@click="repairIntegrityReferences">
						{{ $texts.admin.repairOrphans() }}
					</NcButton>
				</div>
			</div>

			<p v-if="integrityLoading" class="integrity-status">
				{{ $texts.admin.checkingDataQuality() }}
			</p>
			<p v-else-if="!integrityReport" class="integrity-status">
				{{ $texts.admin.noCheckLoaded() }}
			</p>
			<p v-else-if="integrityIsClean" class="integrity-status integrity-status-clean">
				{{ $texts.admin.noDataQualityIssues() }}
			</p>
			<div v-else class="integrity-results">
				<div v-if="integrityOrphanCount > 0" class="integrity-group">
					<h4>{{ $texts.admin.orphanReferences(integrityOrphanCount) }}</h4>
					<p>{{ $texts.admin.orphanReferencesHint() }}</p>
					<ul class="integrity-list">
						<li v-for="issue in integrityOrphanReferences" :key="`${issue.sourceTable}-${issue.column}`">
							<strong>{{ issue.sourceLabel }}: {{ issue.targetLabel }}</strong>
							<span>{{ issue.column }} · IDs: {{ formatIntegrityIds(issue.ids) }}</span>
						</li>
					</ul>
				</div>

				<div v-if="integrityDuplicateCount > 0" class="integrity-group">
					<h4>{{ $texts.admin.visibleDuplicates(integrityDuplicateCount) }}</h4>
					<p>{{ $texts.admin.visibleDuplicatesHint() }}</p>
					<div class="integrity-duplicate-list">
						<div
							v-for="issue in integrityDuplicateVisibleNames"
							:key="`${issue.table}-${issue.type}-${issue.name}-${formatIntegrityIds(issue.ids)}`"
							class="integrity-duplicate-row">
							<div>
								<strong>{{ issue.label }} "{{ issue.name }}"</strong>
								<span>{{ typeLabel(issue.type) }} · IDs: {{ formatIntegrityIds(issue.ids) }}</span>
							</div>
							<div class="integrity-merge-actions">
								<button
									v-for="id in integrityIds(issue)"
									:key="id"
									type="button"
									class="integrity-merge-button"
									:disabled="integrityMergingKey === integrityMergeKey(issue, id)"
									@click="mergeIntegrityDuplicate(issue, id)">
									{{ $texts.admin.keepId(id) }}
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<div class="settings-grid">
			<!-- Kategorien -->
			<div class="settings-column">
				<h3>{{ $texts.admin.globalCategories() }}</h3>
				<div class="tabs-container">
					<button class="tab-button" :class="{ active: categoryTab === 'expense' }" @click.prevent="categoryTab = 'expense'">{{ $texts.common.expense() }}</button>
					<button class="tab-button" :class="{ active: categoryTab === 'income' }" @click.prevent="categoryTab = 'income'">{{ $texts.common.income() }}</button>
				</div>
				<form @submit.prevent="addCategory" class="add-form">
					<IconPicker v-model="newCategoryIcon" />
					<input type="text" class="form-control" v-model="newCategory" :placeholder="$texts.admin.newCategory()" required>
					<NcButton type="primary" native-type="submit" :disabled="loading || !newCategory.trim()" :aria-label="$texts.common.add()"
						style="flex-shrink: 0; margin: 0;">{{ $texts.common.add() }}</NcButton>
				</form>
				<SettingsList :items="filteredCategories" :empty-text="$texts.admin.noGlobalCategories()">
					<template #item="{ item: cat }">
						<div class="settings-list-info">
							<IconPicker :value="cat.icon || 'Shape'" @input="updateCategoryIcon(cat, $event)" />
							<span>{{ cat.name }}</span>
							<span class="badge-global">{{ $texts.common.global() }}</span>
							<span v-if="cat.is_hidden" class="badge-hidden">{{ $texts.admin.hidden() }}</span>
						</div>
						<SettingsItemActions
							class="settings-list-actions"
							:can-edit="true"
							:can-delete="true"
							:can-hide="!cat.is_hidden"
							:can-unhide="cat.is_hidden"
							:edit-label="$texts.admin.editGlobalCategory()"
							:delete-label="$texts.admin.deleteGlobalCategory()"
							:hide-label="$texts.admin.hideGlobalCategory()"
							:unhide-label="$texts.admin.unhideGlobalCategory()"
							@edit="openRenameAdminItem('category', cat)"
							@hide="hideCategory(cat.id)"
							@delete="deleteCategory(cat.id)"
							@unhide="unhideCategory(cat.id)" />
					</template>
				</SettingsList>
			</div>

			<!-- Zahlungspartner -->
			<div class="settings-column">
				<h3>{{ $texts.admin.globalPaymentPartners() }}</h3>
				<div class="tabs-container">
					<button class="tab-button" :class="{ active: paymentPartnerTab === 'expense' }" @click.prevent="paymentPartnerTab = 'expense'">{{ $texts.common.expense() }}</button>
					<button class="tab-button" :class="{ active: paymentPartnerTab === 'income' }" @click.prevent="paymentPartnerTab = 'income'">{{ $texts.common.income() }}</button>
				</div>
				<form @submit.prevent="addPaymentPartner" class="add-form">
					<input type="text" class="form-control" v-model="newPaymentPartner" :placeholder="paymentPartnerPlaceholder" required>
					<NcButton type="primary" native-type="submit" :disabled="loading || !newPaymentPartner.trim()" :aria-label="$texts.common.add()"
						style="flex-shrink: 0; margin: 0;">{{ $texts.common.add() }}</NcButton>
				</form>
				<SettingsList :items="filteredPaymentPartners" :empty-text="paymentPartnerEmptyText">
					<template #item="{ item: paymentPartner }">
						<div class="settings-list-info">
							<span>{{ paymentPartner.name }}</span>
							<span class="badge-global">{{ $texts.common.global() }}</span>
							<span v-if="paymentPartner.is_hidden" class="badge-hidden">{{ $texts.admin.hidden() }}</span>
						</div>
						<SettingsItemActions
							class="settings-list-actions"
							:can-edit="true"
							:can-delete="true"
							:can-hide="!paymentPartner.is_hidden"
							:can-unhide="paymentPartner.is_hidden"
							:edit-label="$texts.admin.editGlobalPaymentPartner()"
							:delete-label="$texts.admin.deleteGlobalPaymentPartner()"
							:hide-label="$texts.admin.hideGlobalPaymentPartner()"
							:unhide-label="$texts.admin.unhideGlobalPaymentPartner()"
							@edit="openRenameAdminItem('paymentPartner', paymentPartner)"
							@hide="hidePaymentPartner(paymentPartner.id)"
							@delete="deletePaymentPartner(paymentPartner.id)"
							@unhide="unhidePaymentPartner(paymentPartner.id)" />
					</template>
				</SettingsList>
			</div>
		</div>
		<Teleport to="body">
			<div
				v-if="renameAdminItem"
				class="settings-modal-backdrop"
				tabindex="-1"
				@click.self="closeRenameAdminItemModal"
				@keydown.esc.stop.prevent="closeRenameAdminItemModal">
				<div class="settings-modal" role="dialog" aria-modal="true" aria-labelledby="admin-rename-title">
					<div class="modal-header">
						<h2 id="admin-rename-title">{{ renameAdminItemTitle }}</h2>
						<button
							type="button"
							class="settings-modal-close-button"
							:aria-label="$texts.common.close()"
							:title="$texts.common.close()"
							@click="closeRenameAdminItemModal">
							<CloseIcon :size="22" aria-hidden="true" />
						</button>
					</div>
					<p class="modal-note">
						{{ $texts.admin.renameNote() }}
					</p>

					<form @submit.prevent="saveRenameAdminItem">
						<div class="form-group">
							<label for="admin-rename-name">{{ $texts.common.name() }}</label>
							<input
								id="admin-rename-name"
								ref="renameAdminItemInput"
								v-model="renameAdminItemName"
								class="form-control"
								type="text"
								required>
						</div>
						<p v-if="renameAdminItemError" class="modal-error">{{ renameAdminItemError }}</p>
						<ModalActions
							:cancel-disabled="renameAdminItemSaving"
							:primary-disabled="!canRenameAdminItem"
							:primary-busy="renameAdminItemSaving"
							:primary-label="$texts.common.save()"
							:primary-busy-label="$texts.common.saveBusy()"
							@cancel="closeRenameAdminItemModal" />
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
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import ConfirmModal from './ConfirmModal.vue'
import ModalActions from './ModalActions.vue'
import SettingsItemActions from './SettingsItemActions.vue'
import SettingsList from './SettingsList.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import { extractError, showRequestError, showToast } from '../services/notifications'

const IconPicker = defineAsyncComponent(() => import(/* webpackChunkName: "cobudget-icon-picker" */ './IconPicker.vue'))

export default {
	name: 'AdminSettings',
	components: {
		NcButton,
		IconPicker,
		ConfirmModal,
		ModalActions,
		SettingsItemActions,
		SettingsList,
		CloseIcon
	},
	data() {
		return {
			loading: false,
			categories: [],
			paymentPartners: [],
			newCategory: '',
			newCategoryIcon: 'Shape',
			newCategoryType: 'expense',
			newPaymentPartner: '',
			newPaymentPartnerType: 'expense',
			categoryTab: 'expense',
			paymentPartnerTab: 'expense',
			renameAdminItemType: '',
			renameAdminItem: null,
			renameAdminItemName: '',
			renameAdminItemError: '',
			renameAdminItemSaving: false,
			integrityReport: null,
			integrityLoading: false,
			integrityRepairing: false,
			integrityMergingKey: '',
			confirmDialog: null
		}
	},
	computed: {
		filteredCategories() {
			return this.categories.filter(c => c.type === this.categoryTab);
		},
		activePaymentPartnerTab() {
			return this.paymentPartnerTab;
		},
		filteredPaymentPartners() {
			return this.paymentPartners.filter(p => p.type === this.activePaymentPartnerTab);
		},
		paymentPartnerPlaceholder() {
			return this.activePaymentPartnerTab === 'income'
				? this.$texts.admin.newPaymentPartnerIncome()
				: this.$texts.admin.newPaymentPartnerExpense();
		},
		paymentPartnerEmptyText() {
			return this.activePaymentPartnerTab === 'income'
				? this.$texts.admin.noIncomePaymentPartners()
				: this.$texts.admin.noExpensePaymentPartners();
		},
		renameAdminItemTitle() {
			return this.renameAdminItemType === 'category'
				? this.$texts.admin.renameGlobalCategory()
				: this.$texts.admin.renameGlobalPaymentPartner();
		},
		canRenameAdminItem() {
			return this.renameAdminItem
				&& this.renameAdminItemName.trim()
				&& this.renameAdminItemName.trim() !== this.renameAdminItem.name
				&& !this.renameAdminItemSaving;
		},
		integrityOrphanReferences() {
			return this.integrityReport?.orphanReferences || [];
		},
		integrityDuplicateVisibleNames() {
			return this.integrityReport?.duplicateVisibleNames || [];
		},
		integrityOrphanCount() {
			return Number(this.integrityReport?.orphanReferenceCount || 0);
		},
		integrityDuplicateCount() {
			return Number(this.integrityReport?.duplicateVisibleNameCount || 0);
		},
		integrityIsClean() {
			return this.integrityReport
				&& this.integrityOrphanCount === 0
				&& this.integrityDuplicateCount === 0;
		}
	},
	mounted() {
		this.fetchData()
		this.fetchIntegrityReport()
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
		async fetchData() {
			this.loading = true;
			try {
				const catRes = await axios.get(generateUrl('/apps/cobudget/api/admin/categories'));
				this.categories = this.normalizeAdminItems(catRes.data || []);
				const paymentPartnerRes = await axios.get(generateUrl('/apps/cobudget/api/admin/payment-partners'));
				this.paymentPartners = this.normalizeAdminItems(paymentPartnerRes.data || []);
			} catch (e) {
				showRequestError(e, this.$texts.admin.loadError(), 'Failed to fetch admin data');
			} finally {
				this.loading = false;
			}
		},
		async fetchIntegrityReport() {
			this.integrityLoading = true;
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/admin/integrity'));
				this.integrityReport = response.data || null;
			} catch (e) {
				showRequestError(e, this.$texts.admin.dataQualityLoadError(), 'Failed to fetch integrity report');
			} finally {
				this.integrityLoading = false;
			}
		},
		async repairIntegrityReferences() {
			const confirmed = await this.openConfirm({
				title: this.$texts.admin.repairOrphansTitle(),
				message: this.$texts.admin.repairOrphansMessage(),
				confirmLabel: this.$texts.admin.repairOrphansConfirm(),
				confirmVariant: 'primary'
			});
			if (!confirmed) return;

			this.integrityRepairing = true;
			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/admin/integrity/repair'));
				this.integrityReport = response.data || null;
				showToast(this.$texts.admin.qualityRepaired());
			} catch (e) {
				showRequestError(e, this.$texts.admin.dataQualityRepairError(), 'Failed to repair integrity report');
			} finally {
				this.integrityRepairing = false;
			}
		},
		async mergeIntegrityDuplicate(issue, keepId) {
			const mergeIds = this.integrityIds(issue).filter(id => id !== keepId);
			if (!keepId || mergeIds.length === 0) {
				return;
			}

			const confirmed = await this.openConfirm({
				title: this.$texts.admin.mergeDuplicatesTitle(),
				message: this.$texts.admin.mergeDuplicatesMessage(issue.label, issue.name, keepId, mergeIds.join(', ')),
				confirmLabel: this.$texts.admin.mergeDuplicatesConfirm(),
				confirmVariant: 'primary'
			});
			if (!confirmed) return;

			const mergeKey = this.integrityMergeKey(issue, keepId);
			this.integrityMergingKey = mergeKey;
			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/admin/integrity/merge'), {
					type: this.integrityKind(issue),
					keepId,
					mergeIds
				});
				this.integrityReport = response.data?.report || response.data || null;
				await this.fetchData();
				showToast(this.$texts.admin.duplicatesMerged());
			} catch (e) {
				showRequestError(e, this.$texts.admin.duplicateMergeError(), 'Failed to merge duplicate names');
			} finally {
				if (this.integrityMergingKey === mergeKey) {
					this.integrityMergingKey = '';
				}
			}
		},
		integrityIds(issue) {
			return (issue?.ids || []).map(id => Number(id)).filter(id => id > 0);
		},
		formatIntegrityIds(ids) {
			return (ids || []).map(id => String(id)).join(', ');
		},
		integrityKind(issue) {
			return issue?.table === 'cobudget_categories' ? 'category' : 'paymentPartner';
		},
		integrityMergeKey(issue, keepId) {
			return `${issue?.table || ''}:${issue?.type || ''}:${issue?.name || ''}:${keepId}`;
		},
		typeLabel(type) {
			if (type === 'income') {
				return this.$texts.admin.typeIncome();
			}
			if (type === 'expense') {
				return this.$texts.admin.typeExpense();
			}
			return this.$texts.admin.typeNone();
		},
		normalizeAdminItems(items) {
			return (items || []).map(item => ({
				...item,
				is_hidden: item.is_hidden === true || item.is_hidden === 1 || item.is_hidden === '1' || item.is_hidden === 'true',
				is_global: item.is_global === true || item.is_global === 1 || item.is_global === '1' || item.is_global === 'true'
			})).sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }));
		},
		async addCategory() {
			if (!this.newCategory.trim()) return;
			this.loading = true;
			try {
				await axios.post(generateUrl('/apps/cobudget/api/admin/categories'), { name: this.newCategory.trim(), icon: this.newCategoryIcon, type: this.categoryTab });
				this.newCategory = '';
				this.newCategoryIcon = 'Shape';
				await this.fetchData();
				showToast(this.$texts.admin.globalCategorySaved());
			} catch (e) {
				showRequestError(e, this.$texts.admin.globalCategoryCreateError(), 'Failed to add category');
			} finally {
				this.loading = false;
			}
		},
		async updateCategoryIcon(cat, newIcon) {
			try {
				await axios.put(generateUrl(`/apps/cobudget/api/admin/categories/${cat.id}/icon`), { icon: newIcon });
				cat.icon = newIcon;
				showToast(this.$texts.admin.globalCategorySaved());
			} catch (e) {
				showRequestError(e, this.$texts.admin.globalCategoryIconSaveError(), 'Failed to update category icon');
			}
		},
		openRenameAdminItem(type, item) {
			this.renameAdminItemType = type;
			this.renameAdminItem = item;
			this.renameAdminItemName = item.name;
			this.renameAdminItemError = '';
			this.renameAdminItemSaving = false;
			this.$nextTick(() => {
				this.$refs.renameAdminItemInput?.focus();
				this.$refs.renameAdminItemInput?.select();
			});
		},
		closeRenameAdminItemModal() {
			if (this.renameAdminItemSaving) {
				return;
			}
			this.resetRenameAdminItemModal();
		},
		resetRenameAdminItemModal() {
			this.renameAdminItemType = '';
			this.renameAdminItem = null;
			this.renameAdminItemName = '';
			this.renameAdminItemError = '';
			this.renameAdminItemSaving = false;
		},
		async saveRenameAdminItem() {
			if (!this.canRenameAdminItem) {
				return;
			}

			const item = this.renameAdminItem;
			const type = this.renameAdminItemType;
			const endpoint = type === 'category'
				? `/apps/cobudget/api/admin/categories/${item.id}`
				: `/apps/cobudget/api/admin/payment-partners/${item.id}`;
			const localizedSuccessMessage = type === 'category'
				? this.$texts.admin.globalCategorySaved()
				: this.$texts.admin.globalPaymentPartnerSaved();

			this.renameAdminItemSaving = true;
			this.renameAdminItemError = '';
			try {
				await axios.put(generateUrl(endpoint), { name: this.renameAdminItemName.trim() });
				this.renameAdminItemSaving = false;
				this.resetRenameAdminItemModal();
				await this.fetchData();
				showToast(localizedSuccessMessage);
			} catch (e) {
				this.renameAdminItemError = extractError(e, this.$texts.settings.genericSaveError());
			} finally {
				this.renameAdminItemSaving = false;
			}
		},
		async hideCategory(id) {
			this.loading = true;
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/admin/categories/${id}/hide`));
				await this.fetchData();
				showToast(this.$texts.admin.globalCategoryHidden());
			} catch (e) {
				showRequestError(e, this.$texts.admin.globalCategoryHideError(), 'Failed to hide category');
			} finally {
				this.loading = false;
			}
		},
		async unhideCategory(id) {
			this.loading = true;
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/admin/categories/${id}/unhide`));
				await this.fetchData();
				showToast(this.$texts.admin.globalCategoryShown());
			} catch (e) {
				showRequestError(e, this.$texts.admin.globalCategoryShowError(), 'Failed to unhide category');
			} finally {
				this.loading = false;
			}
		},
		async deleteCategory(id) {
			const confirmed = await this.openConfirm({
				title: this.$texts.admin.deleteCategoryTitle(),
				message: this.$texts.admin.deleteCategoryMessage(),
				confirmLabel: this.$texts.admin.deleteCategoryConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) return;
			this.loading = true;
			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/admin/categories/${id}`));
				await this.fetchData();
				showToast(this.$texts.admin.globalCategoryDeleted());
			} catch (e) {
				showRequestError(e, this.$texts.admin.globalCategoryDeleteError(), 'Failed to delete category');
			} finally {
				this.loading = false;
			}
		},
		async addPaymentPartner() {
			if (!this.newPaymentPartner.trim()) return;
			this.loading = true;
			try {
				await axios.post(generateUrl('/apps/cobudget/api/admin/payment-partners'), { name: this.newPaymentPartner.trim(), type: this.activePaymentPartnerTab });
				this.newPaymentPartner = '';
				await this.fetchData();
				showToast(this.$texts.admin.globalPaymentPartnerSaved());
			} catch (e) {
				showRequestError(e, this.$texts.admin.globalPaymentPartnerCreateError(), 'Failed to add paymentPartner');
			} finally {
				this.loading = false;
			}
		},
		async hidePaymentPartner(id) {
			this.loading = true;
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/admin/payment-partners/${id}/hide`));
				await this.fetchData();
				showToast(this.$texts.admin.globalPaymentPartnerHidden());
			} catch (e) {
				showRequestError(e, this.$texts.admin.globalPaymentPartnerHideError(), 'Failed to hide paymentPartner');
			} finally {
				this.loading = false;
			}
		},
		async unhidePaymentPartner(id) {
			this.loading = true;
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/admin/payment-partners/${id}/unhide`));
				await this.fetchData();
				showToast(this.$texts.admin.globalPaymentPartnerShown());
			} catch (e) {
				showRequestError(e, this.$texts.admin.globalPaymentPartnerShowError(), 'Failed to unhide paymentPartner');
			} finally {
				this.loading = false;
			}
		},
		async deletePaymentPartner(id) {
			const confirmed = await this.openConfirm({
				title: this.$texts.admin.deletePaymentPartnerTitle(),
				message: this.$texts.admin.deletePaymentPartnerMessage(),
				confirmLabel: this.$texts.admin.deletePaymentPartnerConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) return;
			this.loading = true;
			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/admin/payment-partners/${id}`));
				await this.fetchData();
				showToast(this.$texts.admin.globalPaymentPartnerDeleted());
			} catch (e) {
				showRequestError(e, this.$texts.admin.globalPaymentPartnerDeleteError(), 'Failed to delete paymentPartner');
			} finally {
				this.loading = false;
			}
		}
	}
}
</script>

<style scoped>
.settings-section {
	display: block;
	margin: calc(var(--default-grid-baseline, 4px) * 7);
	width: min(900px, calc(100% - var(--default-grid-baseline, 4px) * 7 * 2));
	box-sizing: border-box;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.settings-section h2,
.settings-section h3,
.settings-section h4 {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.settings-hint {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	margin-top: 0;
	margin-bottom: 24px;
}

.settings-grid {
	display: flex;
	gap: 30px;
}

.integrity-card {
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	margin: 0 0 28px 0;
	padding: 18px;
}

.integrity-card-header {
	align-items: flex-start;
	display: flex;
	gap: 16px;
	justify-content: space-between;
}

.integrity-card h3 {
	font-size: var(--cobudget-font-lg);
	font-weight: 700;
	margin: 0 0 6px 0;
}

.integrity-card p {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	line-height: 1.45;
	margin: 0;
}

.integrity-actions {
	display: flex;
	flex: 0 0 auto;
	flex-wrap: wrap;
	gap: 8px;
	justify-content: flex-end;
}

.integrity-status {
	margin-top: 16px !important;
}

.integrity-status-clean {
	color: #008000!important;
	font-weight: 600;
}

.integrity-results {
	display: grid;
	gap: 18px;
	margin-top: 18px;
}

.integrity-group {
	background: var(--cobudget-surface-muted, #f7f7f7);
	border-radius: var(--border-radius-large, 8px);
	padding: 14px;
}

.integrity-group h4 {
	font-size: var(--cobudget-font-md);
	font-weight: 700;
	margin: 0 0 6px 0;
}

.integrity-list {
	display: grid;
	gap: 8px;
	list-style: none;
	margin: 12px 0 0 0;
	padding: 0;
}

.integrity-list li,
.integrity-duplicate-row {
	background: var(--cobudget-page-background, #fff);
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	padding: 12px;
}

.integrity-list li {
	display: grid;
	gap: 4px;
}

.integrity-list span,
.integrity-duplicate-row span {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.integrity-duplicate-list {
	display: grid;
	gap: 10px;
	margin-top: 12px;
}

.integrity-duplicate-row {
	align-items: center;
	display: grid;
	gap: 12px;
	grid-template-columns: minmax(0, 1fr) auto;
}

.integrity-duplicate-row > div:first-child {
	display: grid;
	gap: 4px;
	min-width: 0;
}

.integrity-merge-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	justify-content: flex-end;
}

.integrity-merge-button {
	background: var(--cobudget-page-background, #fff);
	border: 1px solid var(--cobudget-border-strong, #aaa);
	border-radius: var(--border-radius, 6px);
	color: var(--cobudget-text, var(--color-main-text, #222));
	cursor: pointer;
	font-weight: 600;
	min-height: 34px;
	padding: 0 12px;
}

.integrity-merge-button:hover,
.integrity-merge-button:focus {
	border-color: var(--color-primary, #0076a8);
	color: var(--color-primary, #0076a8);
	outline: none;
}

.integrity-merge-button:disabled {
	cursor: wait;
	opacity: 0.6;
}

.settings-column {
	flex: 1;
}

.settings-column h3 {
	margin-top: 0;
	margin-bottom: 16px;
	font-weight: bold;
}

.add-form {
	display: flex;
	gap: 10px;
	margin-bottom: 20px;
	margin-top: 10px;
	align-items: center;
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
	transition: border-color 0.2s;
	flex: 1;
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

.badge-global {
	font-size: var(--cobudget-font-xxs);
	background-color: #e0f0ff;
	color: #006aa6;
	padding: 2px 6px;
	border-radius: 10px;
	margin-left: 8px;
	text-transform: uppercase;
	font-weight: bold;
}

.badge-hidden {
	font-size: var(--cobudget-font-xxs);
	background-color: var(--cobudget-surface-muted, var(--color-background-dark, #eee));
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	padding: 2px 6px;
	border-radius: 10px;
	text-transform: uppercase;
	font-weight: bold;
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
	top: 0;
	left: 0;
	width: 100vw;
	height: 100vh;
	background: rgba(0, 0, 0, 0.4);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10000;
}

.settings-modal {
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	border-radius: var(--border-radius-large, 8px);
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
	color: var(--cobudget-text, var(--color-main-text, #222));
	max-width: 520px;
	padding: 24px;
	width: 90%;
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
	font-size: var(--cobudget-font-xl);
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
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-base);
	line-height: 1.5;
	margin: 0 0 20px 0;
}

.form-group {
	margin-bottom: 16px;
}

.form-group label {
	display: block;
	font-size: var(--cobudget-font-compact);
	font-weight: 600;
	margin-bottom: 6px;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.modal-error {
	color: var(--cobudget-error);
	margin: 0 0 16px 0;
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
	background: var(--cobudget-page-background, #fff);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
	color: var(--color-primary, #0076a8);
	cursor: default;
}

.tab-button.active:hover {
	background: var(--cobudget-page-background, #fff);
	color: var(--color-primary, #0076a8);
}

@media (max-width: 768px) {
	.settings-section {
		margin: 16px;
		width: calc(100% - 32px);
	}

	.settings-grid {
		flex-direction: column;
		gap: 24px;
	}

	.integrity-card-header,
	.integrity-duplicate-row {
		grid-template-columns: 1fr;
	}

	.integrity-card-header {
		display: grid;
	}

	.integrity-actions,
	.integrity-merge-actions {
		justify-content: flex-start;
	}
}

@media (max-width: 480px) {
	.tab-button {
		padding: 8px 10px;
	}
}
</style>
