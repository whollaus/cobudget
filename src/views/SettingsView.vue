<template>
	<div class="cobudget-settings">
		<AppPageHeader :title="$texts.settings.settings()" />

		<div class="settings-section">
			<div class="settings-general">
			<h3>{{ $texts.settings.generalTitle() }}</h3>

			<div class="settings-grid">
				<div class="settings-column">
					<div class="setting-item">
						<label>{{ $texts.settings.currency() }}</label>
						<p class="setting-desc">{{ $texts.settings.currencyExamples() }}</p>
						<div style="display: flex; gap: 8px; align-items: center;">
							<div style="flex: 1; max-width: 100px;">
								<NcTextField v-model="currency" :placeholder="$texts.settings.currencyPlaceholder()" />
							</div>
							<NcButton type="primary" @click="saveGeneralSettings" :disabled="loading" :aria-label="$texts.common.save()"
								style="flex-shrink: 0; margin: 0;">{{ $texts.common.save() }}</NcButton>
						</div>
					</div>

					<div class="setting-item" style="margin-top: 24px;">
						<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.startPage() }}</label>
						<p class="setting-desc">{{ $texts.settings.startPageDescription() }}</p>
						<div style="display: flex; gap: 8px; align-items: center;">
							<select v-model="defaultStartPage" class="form-control select-control" style="flex: 1; max-width: 320px;">
								<option value="personal">{{ $texts.settings.myFinances() }}</option>
								<option value="currentYear">{{ $texts.settings.currentYear() }}</option>
								<option v-if="enableProjects" value="projects">{{ $texts.settings.myAreas() }}</option>
								<optgroup v-if="enableProjects && activeProjects.length > 0" :label="$texts.settings.openArea()">
									<option v-for="project in activeProjects" :key="project.id" :value="projectStartPageValue(project)">
										{{ project.name }}
									</option>
								</optgroup>
							</select>
							<NcButton type="primary" @click="saveGeneralSettings" :disabled="loading" :aria-label="$texts.common.save()"
								style="flex-shrink: 0; margin: 0;">{{ $texts.common.save() }}</NcButton>
						</div>
					</div>

					<div class="setting-item" style="margin-top: 24px;">
						<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.entriesPerPage() }}</label>
						<p class="setting-desc">{{ $texts.settings.entriesPerPageDescription() }}</p>
						<div style="display: flex; gap: 8px; align-items: center;">
							<select v-model.number="entryPageSize" class="form-control select-control" style="flex: 1; max-width: 200px;">
								<option v-for="option in entryPageSizeOptions" :key="option" :value="option">{{ option }}</option>
							</select>
							<NcButton type="primary" @click="saveGeneralSettings" :disabled="loading" :aria-label="$texts.common.save()"
								style="flex-shrink: 0; margin: 0;">{{ $texts.common.save() }}</NcButton>
						</div>
					</div>

					<div class="setting-item" style="margin-top: 24px;">
						<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.themeMode() }}</label>
						<p class="setting-desc">{{ $texts.settings.themeModeDescription() }}</p>
						<div style="display: flex; gap: 8px; align-items: center;">
							<select v-model="themeMode" class="form-control select-control" style="flex: 1; max-width: 260px;" @change="applyLocalThemeMode">
								<option value="auto">{{ $texts.settings.themeModeAuto() }}</option>
								<option value="light">{{ $texts.settings.themeModeLight() }}</option>
								<option value="dark">{{ $texts.settings.themeModeDark() }}</option>
							</select>
							<NcButton type="primary" @click="saveGeneralSettings" :disabled="loading" :aria-label="$texts.common.save()"
								style="flex-shrink: 0; margin: 0;">{{ $texts.common.save() }}</NcButton>
						</div>
					</div>

					<div class="settings-mini-section">
						<h4>{{ $texts.settings.receipts() }}</h4>
						<div class="setting-toggle-row">
							<div>
								<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.enableReceipts() }}</label>
								<p class="setting-desc" style="margin: 0;">{{ $texts.settings.enableReceiptsDescription() }}</p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" v-model="enableReceipts" @change="saveGeneralSettings">
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div v-if="enableReceipts" class="setting-item" style="margin-top: 16px;">
							<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.storageFolder() }}</label>
							<p class="setting-desc">{{ $texts.settings.receiptStorageFolderDescription() }}</p>
							<div style="display: flex; gap: 8px; align-items: center;">
								<input type="text" class="form-control" v-model="receiptStorageFolder" :placeholder="$texts.settings.receiptStorageFolderPlaceholder()" style="flex: 1;" />
								<NcButton type="primary" @click="saveGeneralSettings" :disabled="loading || !receiptStorageFolder.trim()" :aria-label="$texts.common.save()"
									style="flex-shrink: 0; margin: 0;">{{ $texts.common.save() }}</NcButton>
							</div>
						</div>
						<div v-if="enableReceipts" class="setting-item" style="margin-top: 16px;">
							<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.folderGrouping() }}</label>
							<p class="setting-desc">{{ $texts.settings.folderGroupingDescription() }}</p>
							<div style="display: flex; gap: 8px; align-items: center;">
								<select v-model="receiptFolderGrouping" class="form-control select-control" style="flex: 1; max-width: 260px;">
									<option value="none">{{ $texts.settings.noSubfolders() }}</option>
									<option value="year">{{ $texts.settings.byYear() }}</option>
									<option value="year_month">{{ $texts.settings.byYearAndMonth() }}</option>
								</select>
								<NcButton type="primary" @click="saveGeneralSettings" :disabled="loading" :aria-label="$texts.common.save()"
									style="flex-shrink: 0; margin: 0;">{{ $texts.common.save() }}</NcButton>
							</div>
						</div>
						<div v-if="enableReceipts" class="setting-toggle-row" style="margin-top: 16px;">
							<div>
								<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.deleteReceiptsWithEntry() }}</label>
								<p class="setting-desc" style="margin: 0;">{{ $texts.settings.deleteReceiptsWithEntryDescription() }}</p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" v-model="deleteReceiptsWithEntry" @change="saveGeneralSettings">
								<span class="toggle-slider"></span>
							</label>
						</div>
					</div>

				</div>

				<div class="settings-column">
					<div class="settings-mini-section settings-areas-section">
						<h4>{{ $texts.settings.areas() }}</h4>
						<div class="setting-toggle-row">
							<div>
								<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.enableAreas() }}</label>
								<p class="setting-desc" style="margin: 0;">{{ $texts.settings.enableAreasDescription() }}</p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" v-model="enableProjects" @change="saveGeneralSettings">
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div v-if="enableProjects" class="setting-toggle-row" style="margin-top: 12px;">
							<div>
								<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.enableSharedAreas() }}</label>
								<p class="setting-desc" style="margin: 0;">{{ $texts.settings.enableSharedAreasDescription() }}</p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" v-model="enableSharedProjects" @change="saveGeneralSettings">
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div v-if="enableProjects && enableSharedProjects" class="settings-notification-options">
							<h5>{{ $texts.settings.notifications() }}</h5>
							<div class="setting-toggle-row">
								<div>
									<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.newPaymentsInSharedAreas() }}</label>
									<p class="setting-desc" style="margin: 0;">{{ $texts.settings.newPaymentsInSharedAreasDescription() }}</p>
								</div>
								<label class="toggle-switch">
									<input type="checkbox" v-model="notifyProjectEntries" @change="saveGeneralSettings">
									<span class="toggle-slider"></span>
								</label>
							</div>
							<div class="setting-toggle-row" style="margin-top: 12px;">
								<div>
									<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.settledAreas() }}</label>
									<p class="setting-desc" style="margin: 0;">{{ $texts.settings.settledAreasDescription() }}</p>
								</div>
								<label class="toggle-switch">
									<input type="checkbox" v-model="notifyProjectSettlements" @change="saveGeneralSettings">
									<span class="toggle-slider"></span>
								</label>
							</div>
						</div>
					</div>

					<div class="settings-mini-section">
						<h4>{{ $texts.settings.features() }}</h4>
						<p class="setting-desc">{{ $texts.settings.featuresDescription() }}</p>
					<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
						<div>
							<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.importantPayments() }}</label>
							<p class="setting-desc" style="margin: 0;">{{ $texts.settings.importantPaymentsDescription() }}</p>
						</div>
						<label class="toggle-switch">
							<input type="checkbox" v-model="enableImportantPayments" @change="saveGeneralSettings">
							<span class="toggle-slider"></span>
						</label>
					</div>
					<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
						<div>
							<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.reviewPayments() }}</label>
							<p class="setting-desc" style="margin: 0;">{{ $texts.settings.reviewPaymentsDescription() }}</p>
						</div>
						<label class="toggle-switch">
							<input type="checkbox" v-model="enableReviewPayments" @change="saveGeneralSettings">
							<span class="toggle-slider"></span>
						</label>
					</div>
					<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
						<div>
							<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.fixedCosts() }}</label>
							<p class="setting-desc" style="margin: 0;">{{ $texts.settings.fixedCostsDescription() }}</p>
						</div>
						<label class="toggle-switch">
							<input type="checkbox" v-model="enableFixedCosts" @change="saveGeneralSettings">
							<span class="toggle-slider"></span>
						</label>
					</div>
					<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
						<div>
							<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.childRelated() }}</label>
							<p class="setting-desc" style="margin: 0;">{{ $texts.settings.childRelatedDescription() }}</p>
						</div>
						<label class="toggle-switch">
							<input type="checkbox" v-model="enableChildRelated" @change="saveGeneralSettings">
							<span class="toggle-slider"></span>
						</label>
					</div>
					<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
						<div>
							<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.subscriptions() }}</label>
							<p class="setting-desc" style="margin: 0;">{{ $texts.settings.subscriptionsDescription() }}</p>
						</div>
						<label class="toggle-switch">
							<input type="checkbox" v-model="enableSubscriptions" @change="saveGeneralSettings">
							<span class="toggle-slider"></span>
						</label>
					</div>
					<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
						<div>
							<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.taxRelevant() }}</label>
							<p class="setting-desc" style="margin: 0;">{{ $texts.settings.taxRelevantDescription() }}</p>
						</div>
						<label class="toggle-switch">
							<input type="checkbox" v-model="enableTaxRelevant" @change="saveGeneralSettings">
							<span class="toggle-slider"></span>
						</label>
					</div>
					<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
						<div>
							<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.incomes() }}</label>
							<p class="setting-desc" style="margin: 0;">{{ $texts.settings.incomesDescription() }}</p>
						</div>
						<label class="toggle-switch">
							<input type="checkbox" v-model="enableIncomes" @change="saveGeneralSettings">
							<span class="toggle-slider"></span>
						</label>
					</div>
						<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
							<div>
								<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.futurePayments() }}</label>
								<p class="setting-desc" style="margin: 0;">{{ $texts.settings.futurePaymentsDescription() }}</p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" v-model="enableFuturePayments" @change="saveGeneralSettings">
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
							<div>
								<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.templates() }}</label>
								<p class="setting-desc" style="margin: 0;">{{ $texts.settings.templatesDescription() }}</p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" v-model="enableTemplates" @change="saveGeneralSettings">
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div style="display: flex; align-items: center; justify-content: space-between; margin-top: 12px;">
							<div>
								<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.budgetGoals() }}</label>
								<p class="setting-desc" style="margin: 0;">{{ $texts.settings.budgetGoalsDescription() }}</p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" v-model="enableBudgetGoals" @change="saveGeneralSettings">
								<span class="toggle-slider"></span>
							</label>
						</div>
					</div>
					</div>
			</div>
		</div>

		<div class="settings-grid" style="margin-top: 30px;">
			<!-- Kategorien -->
			<div class="settings-column">
				<h3>{{ $texts.settings.categories() }}</h3>
				<p class="settings-hint">{{ $texts.settings.categoriesHint() }}</p>
				
				<div class="tabs-container" v-if="$enableIncomes">
					<button class="tab-button" :class="{ active: categoryTab === 'expense' }" @click.prevent="categoryTab = 'expense'">{{ $texts.common.expense() }}</button>
					<button class="tab-button" :class="{ active: categoryTab === 'income' }" @click.prevent="categoryTab = 'income'">{{ $texts.common.income() }}</button>
				</div>

				<form @submit.prevent="addCategory" class="add-form">
					<IconPicker v-model="newCategoryIcon" />
					<input type="text" class="form-control" v-model="newCategory" :placeholder="$texts.settings.newCategory()" style="flex: 1;" required />
					<NcButton type="primary" native-type="submit" :disabled="loading || !newCategory.trim()" :aria-label="$texts.common.add()"
						style="flex-shrink: 0; margin: 0;">{{ $texts.common.add() }}</NcButton>
				</form>
				<SettingsList :items="filteredCategories" :empty-text="$texts.settings.noCategories()">
					<template #item="{ item: cat }">
						<div class="settings-list-info">
							<IconPicker :value="cat.icon || 'Shape'" @input="updateCategoryIcon(cat, $event)" :disabled="cat.is_global" />
							<span>{{ cat.name }}</span>
							<span v-if="cat.is_global" class="badge-global">{{ $texts.common.global() }}</span>
						</div>
						<SettingsItemActions
							class="settings-list-actions"
							:can-edit="!cat.is_global"
							:can-delete="!cat.is_global"
							:can-hide="!cat.is_hidden && (cat.is_global || cat.in_use)"
							:can-unhide="cat.is_hidden"
							@edit="openRenameSettingsItem('category', cat)"
							@delete="deleteCategory(cat)"
							@hide="hideCategory(cat.id)"
							@unhide="unhideCategory(cat.id)" />
					</template>
				</SettingsList>
			</div>

			<!-- Zahlungspartner -->
			<div class="settings-column">
				<h3>{{ $texts.settings.paymentPartners() }}</h3>
				<p class="settings-hint">{{ $texts.settings.paymentPartnersHint() }}</p>

				<div class="tabs-container" v-if="$enableIncomes">
					<button class="tab-button" :class="{ active: paymentPartnerTab === 'expense' }" @click.prevent="paymentPartnerTab = 'expense'">{{ $texts.common.expense() }}</button>
					<button class="tab-button" :class="{ active: paymentPartnerTab === 'income' }" @click.prevent="paymentPartnerTab = 'income'">{{ $texts.common.income() }}</button>
				</div>

				<form @submit.prevent="addPaymentPartner" class="add-form">
					<input type="text" class="form-control" v-model="newPaymentPartner" :placeholder="paymentPartnerPlaceholder" style="flex: 1;" required />
					<NcButton type="primary" native-type="submit" :disabled="loading || !newPaymentPartner.trim()" :aria-label="$texts.common.add()"
						style="flex-shrink: 0; margin: 0;">{{ $texts.common.add() }}</NcButton>
				</form>
				<SettingsList :items="filteredPaymentPartners" :empty-text="paymentPartnerEmptyText">
					<template #item="{ item: paymentPartner }">
						<div class="settings-list-info">
							<span>{{ paymentPartner.name }}</span>
							<span v-if="paymentPartner.is_global" class="badge-global">{{ $texts.common.global() }}</span>
						</div>
						<SettingsItemActions
							class="settings-list-actions"
							:can-edit="!paymentPartner.is_global"
							:can-delete="!paymentPartner.is_global"
							:can-hide="!paymentPartner.is_hidden && (paymentPartner.is_global || paymentPartner.in_use)"
							:can-unhide="paymentPartner.is_hidden"
							@edit="openRenameSettingsItem('paymentPartner', paymentPartner)"
							@delete="deletePaymentPartner(paymentPartner)"
							@hide="hidePaymentPartner(paymentPartner.id)"
							@unhide="unhidePaymentPartner(paymentPartner.id)" />
					</template>
				</SettingsList>
			</div>
		</div>

		<div class="settings-subsection workspaces-section">
			<h3>{{ $texts.settings.workspaces() }}</h3>
			<p class="settings-hint">{{ $texts.settings.workspacesHint() }}</p>

			<div class="setting-toggle-row">
				<div>
					<label style="margin-bottom: 0; display: block;">{{ $texts.settings.enableWorkspaces() }}</label>
				</div>
				<label class="toggle-switch">
					<input type="checkbox" v-model="enableWorkspaces" @change="saveGeneralSettings">
					<span class="toggle-slider"></span>
				</label>
			</div>

			<div v-if="enableWorkspaces" class="setting-toggle-row">
				<div>
					<label style="margin-bottom: 0; display: block;">{{ $texts.settings.workspaceSwitcher() }}</label>
					<p class="setting-desc">{{ $texts.settings.workspaceSwitcherDescription() }}</p>
				</div>
				<label class="toggle-switch">
					<input type="checkbox" v-model="showWorkspaceSwitcher" @change="saveGeneralSettings">
					<span class="toggle-slider"></span>
				</label>
			</div>

			<WorkspaceSettings v-if="enableWorkspaces" />
		</div>

		<div class="settings-subsection backups-section">
			<h3>{{ $texts.settings.backups() }}</h3>
			<p class="settings-hint">{{ $texts.settings.backupsHint() }}</p>

			<div class="backup-settings-grid">
				<div class="setting-item">
					<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.backupFolder() }}</label>
					<p class="setting-desc">{{ $texts.settings.backupFolderDescription() }}</p>
					<input type="text" class="form-control" v-model="backupStorageFolder" placeholder="CoBudget/Export" />
				</div>
				<div class="setting-item">
					<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.retainCount() }}</label>
					<p class="setting-desc">{{ $texts.settings.retainCountDescription() }}</p>
					<input type="number" class="form-control" v-model.number="backupRetentionCount" min="1" max="100" step="1" />
				</div>
				<div class="setting-item">
					<label style="margin-bottom: 4px; display: block;">{{ $texts.settings.automaticBackups() }}</label>
					<p class="setting-desc">{{ $texts.settings.automaticBackupsDescription() }}</p>
					<select class="form-control select-control" v-model="backupSchedule">
						<option value="none">{{ $texts.common.no() }}</option>
						<option value="daily">{{ $texts.settings.daily() }}</option>
						<option value="weekly">{{ $texts.settings.weekly() }}</option>
						<option value="monthly">{{ $texts.settings.monthly() }}</option>
					</select>
				</div>
				<div class="backup-save-action">
					<NcButton type="primary" @click="saveBackupSettings(true)" :disabled="loading || backupCreating || !backupStorageFolder.trim()">
						{{ $texts.common.save() }}
					</NcButton>
				</div>
			</div>

			<div class="backup-manual-actions">
				<NcButton class="backup-create-button" type="primary" @click="createBackup" :disabled="loading || backupCreating || !backupStorageFolder.trim()">
					{{ backupCreating ? $texts.settings.backupCreating() : $texts.settings.createBackupNow() }}
				</NcButton>
			</div>

			<div class="backup-list">
				<div class="backup-list-header">
					<h4>{{ $texts.settings.latestBackups() }}</h4>
				</div>
				<p v-if="backupsLoading" class="settings-hint compact-hint">{{ $texts.settings.loadingBackups() }}</p>
				<p v-else-if="backups.length === 0" class="backup-empty">{{ $texts.settings.noBackups() }}</p>
				<ul v-else class="backup-items">
					<li v-for="backup in backups" :key="backup.file_name" class="backup-item">
						<div class="backup-info">
							<strong>{{ formatBackupDate(backup.created_at) }}</strong>
							<span>{{ backup.file_name }}</span>
							<small>{{ backup.file_path }} · {{ formatFileSize(backup.file_size) }}</small>
						</div>
						<div class="backup-item-actions">
							<NcButton
								type="error"
								:aria-label="$texts.settings.deleteBackupAria(backup.file_name)"
								:title="$texts.settings.deleteBackupAria(backup.file_name)"
								:disabled="backupActionsDisabled"
								@click="deleteBackup(backup)">
								<template #icon>
									<DeleteOutlineIcon :size="20" />
								</template>
							</NcButton>
							<NcButton
								:aria-label="$texts.settings.downloadBackupAria(backup.file_name)"
								:title="$texts.settings.downloadBackupAria(backup.file_name)"
								:disabled="backupActionsDisabled"
								@click="downloadBackup(backup)">
								<template #icon>
									<DownloadIcon :size="20" />
								</template>
							</NcButton>
							<span
								class="backup-restore-action"
								:title="backup.can_restore === false ? $texts.settings.restoreBackupBlocked() : ''">
								<NcButton
									:disabled="backupActionsDisabled || backup.can_restore === false"
									@click="restoreBackup(backup)">
									{{ backupRestoringFileName === backup.file_name ? $texts.settings.restoringBackup() : $texts.settings.restoreBackup() }}
								</NcButton>
							</span>
						</div>
					</li>
				</ul>
			</div>
		</div>

		<div class="settings-subsection reset-section">
			<h3>{{ $texts.settings.resetTitle() }}</h3>
			<p class="settings-hint">
				{{ $texts.settings.resetHint() }}
			</p>
			<NcButton
				class="reset-danger-button"
				type="error"
				:disabled="loading || resetRunning"
				@click="resetAllData">
				{{ resetRunning ? $texts.settings.resetRunning() : $texts.settings.resetButton() }}
			</NcButton>
		</div>
		</div>

		<Teleport to="body">
			<div
				v-if="renameSettingsItem"
				class="settings-modal-backdrop"
				tabindex="-1"
				@click.self="closeRenameSettingsItemModal"
				@keydown.esc.stop.prevent="closeRenameSettingsItemModal">
				<div class="settings-modal" role="dialog" aria-modal="true" aria-labelledby="settings-rename-title">
					<div class="modal-header">
						<h2 id="settings-rename-title">{{ renameSettingsItemTitle }}</h2>
						<button
							type="button"
							class="settings-modal-close-button"
							:aria-label="$texts.common.close()"
							:title="$texts.common.close()"
							@click="closeRenameSettingsItemModal">
							<CloseIcon :size="22" aria-hidden="true" />
						</button>
					</div>
					<p class="modal-note">
						{{ $texts.settings.renameNote(renameSettingsItemLabelWithArticle) }}
					</p>

					<form @submit.prevent="saveRenameSettingsItem">
						<div class="form-group">
							<label for="settings-rename-name">{{ $texts.common.name() }}</label>
							<input id="settings-rename-name" ref="renameSettingsItemInput"
								v-model="renameSettingsItemName" class="form-control" type="text" required />
						</div>
						<p v-if="renameSettingsItemError" class="modal-error">{{ renameSettingsItemError }}</p>
						<ModalActions
							:cancel-disabled="renameSettingsItemSaving"
							:primary-disabled="!canRenameSettingsItem"
							:primary-busy="renameSettingsItemSaving"
							:primary-label="$texts.common.save()"
							:primary-busy-label="$texts.common.saveBusy()"
							@cancel="closeRenameSettingsItemModal" />
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
			:confirm-disabled="!confirmDialogCanConfirm"
			:wide="!!(confirmDialog && confirmDialog.wide)"
			@confirm="resolveConfirm(true)"
			@cancel="resolveConfirm(false)">
			<div v-if="confirmDialog && confirmDialog.requiredText" class="confirm-text-block">
				<label for="settings-confirm-text">
					{{ $texts.settings.confirmationExact(confirmDialog.requiredText) }}
				</label>
				<input
					id="settings-confirm-text"
					v-model="confirmDialog.confirmationText"
					class="form-control"
					type="text"
					autocomplete="off"
					spellcheck="false"
					data-autofocus />
			</div>
		</ConfirmModal>
	</div>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import { showSuccess, showInfo } from '@nextcloud/dialogs'
import { ENTRY_PAGE_SIZE_OPTIONS, normalizeEntryPageSize } from '../services/pagination'
import { applyThemeMode, normalizeThemeMode } from '../services/themeMode'
import { clearWorkspaceId, readWorkspaceId, writeWorkspaceId } from '../services/workspaceStorage'
import ModalActions from '../components/ModalActions.vue'
import ConfirmModal from '../components/ConfirmModal.vue'
import SettingsItemActions from '../components/SettingsItemActions.vue'
import SettingsList from '../components/SettingsList.vue'
import WorkspaceSettings from '../components/WorkspaceSettings.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import FormatListBulletedIcon from 'vue-material-design-icons/FormatListBulleted.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

const IconPicker = defineAsyncComponent(() => import(/* webpackChunkName: "cobudget-icon-picker" */ '../components/IconPicker.vue'))

export default {
	name: 'SettingsView',
	components: {
		NcTextField,
		NcButton,
		IconPicker,
		ModalActions,
		ConfirmModal,
		SettingsItemActions,
		SettingsList,
		WorkspaceSettings,
		AppPageHeader,
		FormatListBulletedIcon,
		AccountMultipleIcon,
		CogIcon,
		DeleteOutlineIcon,
		DownloadIcon,
		CloseIcon
	},
	data() {
		return {
			loading: false,
			currency: '',
			enableSubscriptions: true,
			enableFixedCosts: true,
			enableChildRelated: true,
			enableImportantPayments: true,
			enableReviewPayments: true,
			enableTaxRelevant: true,
			enableFuturePayments: true,
			enableTemplates: true,
			enableBudgetGoals: true,
			enableIncomes: true,
			enableProjects: true,
			enableSharedProjects: true,
			notifyProjectEntries: true,
			notifyProjectSettlements: true,
			enableWorkspaces: false,
			showWorkspaceSwitcher: true,
			enableReceipts: true,
			defaultStartPage: 'personal',
			entryPageSize: 25,
			entryPageSizeOptions: ENTRY_PAGE_SIZE_OPTIONS,
			themeMode: 'auto',
			receiptStorageFolder: 'CoBudget/Belege',
			receiptFolderGrouping: 'year',
			deleteReceiptsWithEntry: false,
			backupStorageFolder: 'CoBudget/Export',
			backupRetentionCount: 7,
			backupSchedule: 'none',
			backups: [],
			backupsLoading: false,
			backupCreating: false,
			backupDeletingFileName: '',
			backupRestoringFileName: '',
			resetRunning: false,
			projects: [],
			categories: [],
			paymentPartners: [],
			newCategory: '',
			newCategoryIcon: 'Shape',
			newCategoryType: 'expense',
			newPaymentPartner: '',
			newPaymentPartnerType: 'expense',
			categoryTab: 'expense',
			paymentPartnerTab: 'expense',
			renameSettingsItemType: '',
			renameSettingsItem: null,
			renameSettingsItemName: '',
			renameSettingsItemError: '',
			renameSettingsItemSaving: false,
			confirmDialog: null
		}
	},
	computed: {
		backupActionsDisabled() {
			return !!this.backupDeletingFileName || !!this.backupRestoringFileName;
		},
		activeProjects() {
			return this.projects
				.filter(project => !this.isArchivedProject(project))
				.slice()
				.sort((a, b) => a.name.localeCompare(b.name));
		},
		filteredCategories() {
			const activeTab = this.$enableIncomes ? this.categoryTab : 'expense';
			return this.categories.filter(c => c.type === activeTab);
		},
		activePaymentPartnerTab() {
			return this.$enableIncomes ? this.paymentPartnerTab : 'expense';
		},
		filteredPaymentPartners() {
			return this.paymentPartners.filter(p => p.type === this.activePaymentPartnerTab);
		},
		paymentPartnerPlaceholder() {
			return this.activePaymentPartnerTab === 'income'
				? this.$texts.settings.newPaymentPartnerIncome()
				: this.$texts.settings.newPaymentPartnerExpense();
		},
		paymentPartnerEmptyText() {
			return this.activePaymentPartnerTab === 'income'
				? this.$texts.settings.noIncomePaymentPartners()
				: this.$texts.settings.noExpensePaymentPartners();
		},
		renameSettingsItemTitle() {
			return this.renameSettingsItemType === 'category'
				? this.$texts.settings.renameCategory()
				: this.$texts.settings.renamePaymentPartner();
		},
		renameSettingsItemLabel() {
			return this.renameSettingsItemType === 'category'
				? this.$texts.settings.categoryLabel()
				: this.$texts.settings.paymentPartnerLabel();
		},
		renameSettingsItemLabelWithArticle() {
			return this.renameSettingsItemType === 'category'
				? this.$texts.settings.categoryLabelWithArticle()
				: this.$texts.settings.paymentPartnerLabelWithArticle();
		},
		canRenameSettingsItem() {
			return this.renameSettingsItem
				&& this.renameSettingsItemName.trim()
				&& this.renameSettingsItemName.trim() !== this.renameSettingsItem.name
				&& !this.renameSettingsItemSaving;
		},
		confirmDialogCanConfirm() {
			if (!this.confirmDialog) {
				return false;
			}
			if (this.confirmDialog.requiredText && this.confirmDialog.confirmationText !== this.confirmDialog.requiredText) {
				return false;
			}
			return true;
		}
	},
	mounted() {
		this.fetchData()
	},
	methods: {
		openConfirm({
			title,
			message,
			confirmLabel,
			confirmVariant = 'primary',
			requiredText = '',
			kind = '',
			wide = false
		}) {
			return new Promise(resolve => {
				this.confirmDialog = {
					title,
					message,
					confirmLabel,
					confirmVariant,
					requiredText,
					confirmationText: '',
					kind,
					wide,
					resolve
				};
			});
		},
		resolveConfirm(confirmed) {
			const dialog = this.confirmDialog;
			const resolver = dialog?.resolve;
			this.confirmDialog = null;
			if (resolver) {
				resolver(confirmed);
			}
		},
		extractError(error, fallback) {
			return error?.response?.data?.error || error?.message || fallback;
		},
		normalizeBackupStorageFolder(folder) {
			const normalized = String(folder || '').trim();
			if (!normalized || normalized === 'CoBudget/Backups') {
				return 'CoBudget/Export';
			}

			return normalized;
		},
		normalizeWorkspace(workspace) {
			return {
				...workspace,
				id: Number(workspace?.id || 0),
				is_default: workspace?.is_default === true || workspace?.is_default === 1 || workspace?.is_default === '1',
			};
		},
		normalizeWorkspaces(payload) {
			const rawWorkspaces = Array.isArray(payload)
				? payload
				: Array.isArray(payload?.workspaces)
					? payload.workspaces
					: [];
			return rawWorkspaces
				.map(workspace => this.normalizeWorkspace(workspace))
				.filter(workspace => workspace.id > 0);
		},
		async ensureValidWorkspaceContext() {
			if (!this.enableWorkspaces) {
				clearWorkspaceId();
				return;
			}

			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/workspaces'), {
					headers: { Accept: 'application/json' },
					params: { _t: Date.now() },
					skipWorkspaceHeader: true,
				});
				const workspaces = this.normalizeWorkspaces(response.data);
				if (workspaces.length === 0) {
					clearWorkspaceId();
					return;
				}

				const storedWorkspaceId = readWorkspaceId();
				if (storedWorkspaceId && workspaces.some(workspace => workspace.id === storedWorkspaceId)) {
					writeWorkspaceId(storedWorkspaceId);
					return;
				}

				const defaultWorkspace = workspaces.find(workspace => workspace.is_default) || workspaces[0];
				writeWorkspaceId(defaultWorkspace.id);
			} catch (e) {
				console.error('Failed to validate workspace context', e);
				clearWorkspaceId();
			}
		},
		async fetchWorkspaceScopedWithRetry(url, config = {}) {
			try {
				return await axios.get(url, config);
			} catch (error) {
				if (error?.response?.status !== 403) {
					throw error;
				}

				clearWorkspaceId();
				await this.ensureValidWorkspaceContext();
				return axios.get(url, config);
			}
		},
		showError(message) {
			if (window.OC && window.OC.Notification) {
				window.OC.Notification.showTemporary(message);
				return;
			}
			alert(message);
		},
		showSuccessMessage(message) {
			if (typeof showSuccess === 'function') {
				showSuccess(message, { timeout: 3000 });
			} else if (typeof showInfo === 'function') {
				showInfo(message, { timeout: 3000 });
			} else if (window.OC && window.OC.Notification) {
				window.OC.Notification.showTemporary(message);
			}
		},
		formatBackupDate(timestamp) {
			const date = new Date(Number(timestamp || 0) * 1000);
			if (Number.isNaN(date.getTime())) {
				return this.$texts.common.unknownDate();
			}
			return date.toLocaleString('de-AT', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit'
			});
		},
		formatFileSize(size) {
			const bytes = Number(size || 0);
			if (bytes < 1024) {
				return `${bytes} B`;
			}
			if (bytes < 1024 * 1024) {
				return `${(bytes / 1024).toFixed(1)} KB`;
			}
			return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
		},
		async fetchBackups() {
			this.backupsLoading = true;
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/backups'), { skipWorkspaceHeader: true });
				this.backups = response.data?.backups || [];
			} catch (e) {
				console.error('Failed to fetch backups', e);
				this.showError(this.extractError(e, this.$texts.settings.backupsLoadError()));
			} finally {
				this.backupsLoading = false;
			}
		},
		async createBackup() {
			if (!this.backupStorageFolder.trim() || this.backupCreating) {
				return;
			}
			this.backupCreating = true;
			try {
				await this.saveBackupSettings(false);
				const response = await axios.post(generateUrl('/apps/cobudget/api/backups'), {}, { skipWorkspaceHeader: true });
				this.backups = response.data?.backups || [];
				this.showSuccessMessage(this.$texts.settings.backupCreated());
			} catch (e) {
				console.error('Failed to create backup', e);
				this.showError(this.extractError(e, this.$texts.settings.backupCreateError()));
			} finally {
				this.backupCreating = false;
			}
		},
		downloadBackup(backup) {
			if (!backup?.file_name) {
				return;
			}
			window.location.href = generateUrl(`/apps/cobudget/api/backups/${encodeURIComponent(backup.file_name)}/download`);
		},
		async deleteBackup(backup) {
			if (!backup?.file_name || this.backupActionsDisabled) {
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.settings.deleteBackupTitle(),
				message: this.$texts.settings.deleteBackupMessage(backup.file_name),
				confirmLabel: this.$texts.settings.deleteBackupConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) {
				return;
			}

			this.backupDeletingFileName = backup.file_name;
			try {
				const response = await axios.delete(generateUrl(`/apps/cobudget/api/backups/${encodeURIComponent(backup.file_name)}`), { skipWorkspaceHeader: true });
				this.backups = response.data?.backups || [];
				this.showSuccessMessage(this.$texts.settings.backupDeleted());
			} catch (e) {
				console.error('Failed to delete backup', e);
				this.showError(this.extractError(e, this.$texts.settings.backupDeleteError()));
			} finally {
				this.backupDeletingFileName = '';
			}
		},
		async restoreBackup(backup) {
			if (!backup?.file_name || this.backupActionsDisabled) {
				return;
			}
			if (backup.can_restore === false) {
				this.showError(this.$texts.settings.restoreBackupBlocked());
				return;
			}
			let restoreInfo = null;
			try {
				const inspectResponse = await axios.get(
					generateUrl(`/apps/cobudget/api/backups/${encodeURIComponent(backup.file_name)}/inspect`),
					{ skipWorkspaceHeader: true }
				);
				restoreInfo = inspectResponse.data?.backup?.restore || null;
			} catch (e) {
				console.error('Failed to inspect personal export', e);
				this.showError(this.extractError(e, this.$texts.settings.backupInspectError()));
				return;
			}
			if (restoreInfo?.can_restore_personally === false) {
				this.showError(this.$texts.settings.restoreBackupSharedDataBlocked());
				return;
			}
			const message = [
				this.$texts.settings.restoreBackupMessage(backup.file_name),
				restoreInfo?.shared_data_restore_mode === 'personal_share'
					? this.$texts.settings.restoreBackupSharedDataImportMode()
					: '',
				restoreInfo?.will_map_source_user
					? this.$texts.settings.restoreBackupUserMapping(restoreInfo.source_user_id, restoreInfo.target_user_id)
					: ''
			].filter(Boolean).join('\n\n');
			const confirmed = await this.openConfirm({
				title: this.$texts.settings.restoreBackupTitle(),
				message,
				confirmLabel: this.$texts.settings.restoreBackupConfirm(),
				confirmVariant: 'danger',
				requiredText: 'RESTORE',
				kind: 'restoreBackup',
				wide: true
			});
			if (!confirmed) {
				return;
			}

			this.backupRestoringFileName = backup.file_name;
			try {
				const response = await axios.post(
					generateUrl(`/apps/cobudget/api/backups/${encodeURIComponent(backup.file_name)}/restore`),
					{ confirmation: 'RESTORE' },
					{ skipWorkspaceHeader: true }
				);
				this.backups = response.data?.backups || [];
				this.showSuccessMessage(this.$texts.settings.backupRestored());
				await this.fetchData();
			} catch (e) {
				console.error('Failed to restore personal export', e);
				this.showError(this.extractError(e, this.$texts.settings.backupRestoreError()));
			} finally {
				this.backupRestoringFileName = '';
			}
		},
		async saveBackupSettings(showMessage = true) {
			const folder = this.normalizeBackupStorageFolder(this.backupStorageFolder);
			const retention = Math.max(1, Math.min(100, Number(this.backupRetentionCount || 7)));
			this.backupStorageFolder = folder;
			this.backupRetentionCount = retention;
			await axios.post(generateUrl('/apps/cobudget/api/settings'), {
				currency: this.currency,
				backup_storage_folder: folder,
				backup_retention_count: retention,
				backup_schedule: this.backupSchedule || 'none'
			}, { skipWorkspaceHeader: true });
			if (showMessage) {
				this.showSuccessMessage(this.$texts.settings.backupSettingsSaved());
			}
		},
		buildResetPreviewMessage(preview) {
			const counts = preview?.counts || {};
			const parts = [
				this.$texts.settings.resetPreviewSummary({
					workspaces: this.$texts.settings.countWorkspaces(Number(counts.workspaces || 0)),
					entries: this.$texts.settings.countEntries(Number(counts.entries || 0)),
					soloProjects: this.$texts.settings.countPersonalAreas(Number(counts.solo_projects || 0)),
					categories: this.$texts.settings.countCategories(Number(counts.categories || 0)),
					paymentPartners: this.$texts.settings.countPaymentPartners(Number(counts.payment_partners || 0)),
					templates: this.$texts.settings.countTemplates(Number(counts.templates || 0)),
					budgetGoals: this.$texts.settings.countBudgetGoals(Number(counts.budget_goals || 0)),
				})
			];
			if (Number(counts.attachments || 0) > 0) {
				parts.push(this.$texts.settings.resetReceiptSummary(this.$texts.settings.countReceiptLinks(Number(counts.attachments || 0))));
			}
			const transferableShared = preview?.transferable_shared_projects || [];
			if (transferableShared.length > 0) {
				const names = transferableShared.map(project => project.name).join(', ');
				parts.push(this.$texts.settings.resetTransferSharedSummary(names));
			}
			const leavableShared = preview?.leavable_shared_projects || [];
			if (leavableShared.length > 0) {
				const names = leavableShared.map(project => project.name).join(', ');
				parts.push(this.$texts.settings.resetLeaveSharedSummary(names));
			}
			parts.push(this.$texts.settings.resetSafetySummary());
			return parts.join(' ');
		},
		buildResetBlockerMessage(preview) {
			const blockers = preview?.blocking_shared_projects || [];
			if (blockers.length === 0) {
				return '';
			}
			const names = blockers
				.map(project => `${project.name} (${this.$texts.settings.openEntryCount(Number(project.open_entries || 0))})`)
				.join(', ');
			return this.$texts.settings.resetBlocked(names);
		},
		async resetAllData() {
			if (this.resetRunning) {
				return;
			}

			this.resetRunning = true;
			try {
				const previewResponse = await axios.get(generateUrl('/apps/cobudget/api/settings/reset-preview'), { skipWorkspaceHeader: true });
				const preview = previewResponse.data?.reset || {};
				const blockerMessage = this.buildResetBlockerMessage(preview);
				if (blockerMessage) {
					this.showError(blockerMessage);
					return;
				}

				const confirmed = await this.openConfirm({
					title: this.$texts.settings.resetTitle(),
					message: this.buildResetPreviewMessage(preview),
					confirmLabel: this.$texts.settings.resetConfirmButton(),
					confirmVariant: 'danger',
					requiredText: 'RESET',
					wide: true
				});
				if (!confirmed) {
					return;
				}

				await axios.post(
					generateUrl('/apps/cobudget/api/settings/reset'),
					{ confirmation: 'RESET' },
					{ skipWorkspaceHeader: true }
				);
				clearWorkspaceId();
				this.showSuccessMessage(this.$texts.settings.resetDone());
				window.setTimeout(() => window.location.reload(), 600);
			} catch (e) {
				console.error('Failed to reset CoBudget data', e);
				const blockerMessage = this.buildResetBlockerMessage(e?.response?.data?.reset);
				this.showError(blockerMessage || this.extractError(e, this.$texts.settings.resetError()));
			} finally {
				this.resetRunning = false;
			}
		},
		isArchivedProject(project) {
			return project?.is_archived === true || project?.is_archived === 1 || project?.is_archived === '1';
		},
		projectStartPageValue(project) {
			return `project:${project.id}`;
		},
		normalizeDefaultStartPageForProjects() {
			if (!this.enableProjects && (this.defaultStartPage === 'projects' || String(this.defaultStartPage).startsWith('project:'))) {
				this.defaultStartPage = 'personal';
				return;
			}

			if (!String(this.defaultStartPage).startsWith('project:')) {
				return;
			}

			const projectId = Number(String(this.defaultStartPage).replace('project:', ''));
			const projectExists = this.activeProjects.some(project => Number(project.id) === projectId);
			if (!projectExists) {
				this.defaultStartPage = 'personal';
			}
		},
		async fetchData() {
			this.loading = true;
			try {
				const settingsRes = await axios.get(generateUrl('/apps/cobudget/api/settings'), { skipWorkspaceHeader: true });
				this.currency = settingsRes.data.currency || '';
				this.enableSubscriptions = settingsRes.data.enable_subscriptions ?? true;
				this.enableFixedCosts = settingsRes.data.enable_fixed_costs ?? true;
				this.enableChildRelated = settingsRes.data.enable_child_related ?? true;
				this.enableImportantPayments = settingsRes.data.enable_important_payments ?? true;
				this.enableReviewPayments = settingsRes.data.enable_review_payments ?? true;
				this.enableTaxRelevant = settingsRes.data.enable_tax_relevant ?? true;
				this.enableFuturePayments = settingsRes.data.enable_future_payments ?? true;
				this.enableTemplates = settingsRes.data.enable_templates ?? true;
				this.enableBudgetGoals = settingsRes.data.enable_budget_goals ?? true;
				this.enableIncomes = settingsRes.data.enable_incomes ?? true;
				this.enableProjects = settingsRes.data.enable_projects ?? true;
				this.enableSharedProjects = settingsRes.data.enable_shared_projects ?? true;
				this.notifyProjectEntries = settingsRes.data.notify_project_entries ?? true;
				this.notifyProjectSettlements = settingsRes.data.notify_project_settlements ?? true;
				this.enableWorkspaces = settingsRes.data.enable_workspaces ?? false;
				this.showWorkspaceSwitcher = settingsRes.data.show_workspace_switcher ?? true;
				this.enableReceipts = settingsRes.data.enable_receipts ?? true;
				this.defaultStartPage = settingsRes.data.default_start_page || 'personal';
				this.entryPageSize = normalizeEntryPageSize(settingsRes.data.entries_per_page);
				this.themeMode = normalizeThemeMode(settingsRes.data.theme_mode);
				applyThemeMode(this.themeMode);
				this.receiptStorageFolder = settingsRes.data.receipt_storage_folder || 'CoBudget/Belege';
				this.receiptFolderGrouping = settingsRes.data.receipt_folder_grouping || 'year';
				this.deleteReceiptsWithEntry = settingsRes.data.delete_receipts_with_entry ?? false;
				this.backupStorageFolder = this.normalizeBackupStorageFolder(settingsRes.data.backup_storage_folder);
				this.backupRetentionCount = Number(settingsRes.data.backup_retention_count || 7);
				this.backupSchedule = settingsRes.data.backup_schedule || 'none';
				await this.fetchBackups();
				await this.ensureValidWorkspaceContext();
				if (this.enableProjects) {
					const projectRes = await this.fetchWorkspaceScopedWithRetry(generateUrl('/apps/cobudget/api/projects'));
					this.projects = Array.isArray(projectRes.data) ? projectRes.data : [];
				} else {
					this.projects = [];
				}
				this.normalizeDefaultStartPageForProjects();
				const catRes = await this.fetchWorkspaceScopedWithRetry(generateUrl('/apps/cobudget/api/categories/settings'));
				this.categories = (catRes.data || []).sort((a, b) => a.name.localeCompare(b.name));
				const paymentPartnerRes = await this.fetchWorkspaceScopedWithRetry(generateUrl('/apps/cobudget/api/payment-partners/settings'));
				this.paymentPartners = (paymentPartnerRes.data || []).sort((a, b) => a.name.localeCompare(b.name));
			} catch (e) {
				console.error('Failed to fetch personal data', e);
				this.showError(this.extractError(e, this.$texts.settings.loadError()));
			} finally {
				this.loading = false;
			}
		},
		async addCategory() {
			if (!this.newCategory.trim()) return;
			this.loading = true;
			try {
				await axios.post(generateUrl('/apps/cobudget/api/categories'), { name: this.newCategory.trim(), icon: this.newCategoryIcon, type: this.categoryTab });
				this.newCategory = '';
				this.newCategoryIcon = 'Shape';
				await this.fetchData();
				this.showSuccessMessage(this.$texts.settings.categorySaved());
			} catch (e) {
				console.error(e);
				this.showError(this.extractError(e, this.$texts.settings.categoryCreateError()));
			}
			this.loading = false;
		},
		applyLocalThemeMode() {
			this.themeMode = applyThemeMode(this.themeMode);
		},
		async updateCategoryIcon(cat, newIcon) {
			try {
				if (cat.is_global) {
					return;
				}
				await axios.put(generateUrl(`/apps/cobudget/api/categories/${cat.id}/icon`), { icon: newIcon });
				await this.fetchData();
				this.showSuccessMessage(this.$texts.settings.categorySaved());
			} catch (e) {
				console.error(e);
				this.showError(this.extractError(e, this.$texts.settings.categoryIconSaveError()));
			}
		},
		openRenameSettingsItem(type, item) {
			if (!item || item.is_global) {
				return;
			}
			this.renameSettingsItemType = type;
			this.renameSettingsItem = item;
			this.renameSettingsItemName = item.name;
			this.renameSettingsItemError = '';
			this.renameSettingsItemSaving = false;
			this.$nextTick(() => {
				if (this.$refs.renameSettingsItemInput) {
					this.$refs.renameSettingsItemInput.focus();
					this.$refs.renameSettingsItemInput.select();
				}
			});
		},
		closeRenameSettingsItemModal() {
			if (this.renameSettingsItemSaving) {
				return;
			}
			this.resetRenameSettingsItemModal();
		},
		resetRenameSettingsItemModal() {
			this.renameSettingsItemType = '';
			this.renameSettingsItem = null;
			this.renameSettingsItemName = '';
			this.renameSettingsItemError = '';
			this.renameSettingsItemSaving = false;
		},
		async saveRenameSettingsItem() {
			if (!this.canRenameSettingsItem) {
				return;
			}

			const item = this.renameSettingsItem;
			const type = this.renameSettingsItemType;
			const newName = this.renameSettingsItemName.trim();
			const successMessage = type === 'category' ? this.$texts.settings.categorySaved() : this.$texts.settings.paymentPartnerSaved();
			const endpoint = type === 'category'
				? `/apps/cobudget/api/categories/${item.id}`
				: `/apps/cobudget/api/payment-partners/${item.id}`;

			this.renameSettingsItemSaving = true;
			this.renameSettingsItemError = '';
			try {
				const response = await axios.put(generateUrl(endpoint), { name: newName }, {
					headers: { Accept: 'application/json' }
				});
				if (response.data && typeof response.data === 'object' && response.data.error) {
					throw new Error(response.data.error);
				}
				this.resetRenameSettingsItemModal();
				await this.fetchData();
				this.showSuccessMessage(successMessage);
			} catch (e) {
				console.error('Failed to rename settings item', e);
				this.renameSettingsItemError = this.extractError(e, this.$texts.settings.genericSaveError());
			} finally {
				this.renameSettingsItemSaving = false;
			}
		},
		async addPaymentPartner() {
			if (!this.newPaymentPartner.trim()) return;
			this.loading = true;
			try {
				await axios.post(generateUrl('/apps/cobudget/api/payment-partners'), { name: this.newPaymentPartner.trim(), type: this.activePaymentPartnerTab });
				this.newPaymentPartner = '';
				await this.fetchData();
				this.showSuccessMessage(this.$texts.settings.paymentPartnerSaved());
			} catch (e) {
				console.error(e);
				this.showError(this.extractError(e, this.$texts.settings.paymentPartnerCreateError()));
			}
			this.loading = false;
		},
		async hideCategory(id) {
			this.loading = true;
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/categories/${id}/hide`));
				await this.fetchData();
				this.showSuccessMessage(this.$texts.settings.categoryHidden());
			} catch (e) {
				console.error(e);
				this.showError(this.extractError(e, this.$texts.settings.categoryHideError()));
			}
			this.loading = false;
		},
		async unhideCategory(id) {
			this.loading = true;
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/categories/${id}/unhide`));
				await this.fetchData();
				this.showSuccessMessage(this.$texts.settings.categoryShown());
			} catch (e) {
				console.error(e);
				this.showError(this.extractError(e, this.$texts.settings.categoryShowError()));
			}
			this.loading = false;
		},
		async deleteCategory(cat) {
			if (cat.in_use) {
				if (window.OC && window.OC.Notification) {
					window.OC.Notification.showTemporary(this.$texts.settings.categoryInUse());
				} else {
					alert(this.$texts.settings.categoryInUse());
				}
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.settings.deleteCategoryTitle(),
				message: this.$texts.settings.deleteCategoryMessage(),
				confirmLabel: this.$texts.settings.deleteCategoryConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) return;
			this.loading = true;
			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/categories/${cat.id}`));
				await this.fetchData();
				this.showSuccessMessage(this.$texts.settings.categoryDeleted());
			} catch (e) {
				console.error(e);
				this.showError(this.extractError(e, this.$texts.settings.categoryDeleteError()));
			}
			this.loading = false;
		},
		async hidePaymentPartner(id) {
			this.loading = true;
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/payment-partners/${id}/hide`));
				await this.fetchData();
				this.showSuccessMessage(this.$texts.settings.paymentPartnerHidden());
			} catch (e) {
				console.error(e);
				this.showError(this.extractError(e, this.$texts.settings.paymentPartnerHideError()));
			}
			this.loading = false;
		},
		async unhidePaymentPartner(id) {
			this.loading = true;
			try {
				await axios.post(generateUrl(`/apps/cobudget/api/payment-partners/${id}/unhide`));
				await this.fetchData();
				this.showSuccessMessage(this.$texts.settings.paymentPartnerShown());
			} catch (e) {
				console.error(e);
				this.showError(this.extractError(e, this.$texts.settings.paymentPartnerShowError()));
			}
			this.loading = false;
		},
		async deletePaymentPartner(paymentPartner) {
			if (paymentPartner.in_use) {
				if (window.OC && window.OC.Notification) {
					window.OC.Notification.showTemporary(this.$texts.settings.paymentPartnerInUse());
				} else {
					alert(this.$texts.settings.paymentPartnerInUse());
				}
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.settings.deletePaymentPartnerTitle(),
				message: this.$texts.settings.deletePaymentPartnerMessage(),
				confirmLabel: this.$texts.settings.deletePaymentPartnerConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) return;

			this.loading = true;
			try {
				await axios.delete(generateUrl(`/apps/cobudget/api/payment-partners/${paymentPartner.id}`));
				await this.fetchData();
				this.showSuccessMessage(this.$texts.settings.paymentPartnerDeleted());
			} catch (e) {
				console.error('Failed to delete paymentPartner', e)
				this.showError(this.extractError(e, this.$texts.settings.paymentPartnerDeleteError()));
			}
			this.loading = false;
		},
		async saveGeneralSettings() {
			this.loading = true;
			try {
				this.normalizeDefaultStartPageForProjects();
				await axios.post(generateUrl('/apps/cobudget/api/settings'), { 
					currency: this.currency, 
					enable_subscriptions: this.enableSubscriptions,
					enable_fixed_costs: this.enableFixedCosts,
					enable_child_related: this.enableChildRelated,
					enable_important_payments: this.enableImportantPayments,
					enable_review_payments: this.enableReviewPayments,
					enable_tax_relevant: this.enableTaxRelevant,
					enable_future_payments: this.enableFuturePayments,
					enable_templates: this.enableTemplates,
					enable_budget_goals: this.enableBudgetGoals,
					enable_incomes: this.enableIncomes,
					enable_projects: this.enableProjects,
					enable_shared_projects: this.enableSharedProjects,
					notify_project_entries: this.notifyProjectEntries,
					notify_project_settlements: this.notifyProjectSettlements,
					enable_workspaces: this.enableWorkspaces,
					show_workspace_switcher: this.showWorkspaceSwitcher,
					enable_receipts: this.enableReceipts,
					default_start_page: this.defaultStartPage,
					entries_per_page: normalizeEntryPageSize(this.entryPageSize),
					theme_mode: normalizeThemeMode(this.themeMode),
					receipt_storage_folder: this.receiptStorageFolder.trim() || 'CoBudget/Belege',
					receipt_folder_grouping: this.receiptFolderGrouping,
					delete_receipts_with_entry: this.deleteReceiptsWithEntry,
					backup_storage_folder: this.normalizeBackupStorageFolder(this.backupStorageFolder),
					backup_retention_count: Math.max(1, Math.min(100, Number(this.backupRetentionCount || 7))),
					backup_schedule: this.backupSchedule || 'none'
				}, { skipWorkspaceHeader: true });
				this.$root.$emit && this.$root.$emit('settings-changed');
				


				if (!this.enableSubscriptions && this.$route && this.$route.query.filter === 'subscription') {
					window.location.hash = '#/';
				} else if (!this.enableTaxRelevant && this.$route && this.$route.query.filter === 'taxRelevant') {
					window.location.hash = '#/';
				} else if (!this.enableFixedCosts && this.$route && this.$route.query.filter === 'fixedCost') {
					window.location.hash = '#/';
				} else if (!this.enableChildRelated && this.$route && this.$route.query.filter === 'childRelated') {
					window.location.hash = '#/';
				} else if (!this.enableImportantPayments && this.$route && this.$route.query.filter === 'important') {
					window.location.hash = '#/';
				} else if (!this.enableReviewPayments && this.$route && this.$route.query.filter === 'review') {
					window.location.hash = '#/';
				} else if (!this.enableFuturePayments && this.$route && this.$route.query.filter === 'future') {
					window.location.hash = '#/';
				} else if (!this.enableIncomes && this.$route && this.$route.query.filter === 'income') {
					window.location.hash = '#/';
				}
				
				window.location.reload(); // Reload to apply settings everywhere easily
			} catch (e) {
				console.error('Failed to save settings', e)
				this.showError(this.extractError(e, this.$texts.settings.generalSaveError()));
			}
			this.loading = false;
		}
	}
}
</script>

<style scoped>
.settings-section {
	display: block;
	margin: calc(var(--default-grid-baseline, 4px) * 7);
	margin-top: 0;
	width: min(900px, calc(100% - var(--default-grid-baseline, 4px) * 7 * 2));
	box-sizing: border-box;
}
.settings-general {
	margin-bottom: 20px;
}
.settings-general h3 {
	font-size: var(--cobudget-font-section);
	margin-top: 0 !important;
}
.settings-subsection {
	margin-top: 30px;
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
.settings-column {
	flex: 1;
}

.confirm-text-block {
	margin-top: 18px;
}

.confirm-text-block label {
	display: block;
	margin-bottom: 8px;
	font-weight: 700;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.settings-mini-section {
	margin-top: 28px;
	padding-top: 18px;
	border-top: 1px solid var(--cobudget-border, #ddd);
}

.settings-areas-section {
	margin-top: 0;
	padding-top: 0;
	border-top: 0;
}

.settings-mini-section h4 {
	font-size: var(--cobudget-font-md);
	font-weight: 700;
	margin: 0 0 14px 0;
}

.settings-notification-options {
	margin-top: 18px;
	padding-top: 14px;
	border-top: 1px solid var(--cobudget-border, #ddd);
}

.settings-notification-options h5 {
	font-size: var(--cobudget-font-base);
	font-weight: 700;
	margin: 0 0 12px 0;
}

.backup-settings-grid {
	display: grid;
	grid-template-columns: minmax(220px, 1fr) minmax(140px, 180px) minmax(180px, 220px) auto;
	gap: 16px;
	align-items: end;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.backup-settings-grid label,
.backup-list-header h4,
.backup-info strong,
.backup-info span {
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.backup-save-action {
	display: flex;
	align-items: flex-end;
}

.backup-manual-actions {
	margin-top: 18px;
}

.restore-report {
	margin-top: 18px;
	padding: 16px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface-muted, #f5f5f5);
}

.restore-report-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
}

.restore-report-header h4 {
	font-size: var(--cobudget-font-md);
	font-weight: 700;
	margin: 0 0 4px 0;
}

.restore-report-header p,
.restore-report-block p,
.restore-report-note {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	margin: 0;
}

.restore-report-close {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border: 0;
	border-radius: var(--border-radius, 6px);
	background: transparent;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	cursor: pointer;
	font-size: var(--cobudget-font-title-sm);
	line-height: 1;
}

.restore-report-close:hover,
.restore-report-close:focus-visible {
	background: var(--cobudget-surface-strong, var(--color-background-dark, #eee));
	color: var(--cobudget-text, var(--color-main-text, #222));
	outline: none;
}

.restore-report-summary {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 10px;
	margin-bottom: 14px;
}

.restore-report-summary div,
.restore-report-block {
	padding: 12px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-page-background, #fff);
}

.restore-report-summary span,
.restore-report-summary strong {
	display: block;
}

.restore-report-summary span {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-compact);
}

.restore-report-summary strong {
	margin-top: 4px;
	font-size: var(--cobudget-font-md);
}

.restore-report-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 10px;
}

.restore-report-block h5 {
	font-size: var(--cobudget-font-base);
	font-weight: 700;
	margin: 0 0 10px 0;
}

.restore-report-block ul {
	list-style: none;
	margin: 0;
	padding: 0;
}

.restore-report-block li {
	display: flex;
	justify-content: space-between;
	gap: 10px;
	padding: 6px 0;
	border-top: 1px solid var(--cobudget-border, #ddd);
}

.restore-report-block li:first-child {
	border-top: 0;
	padding-top: 0;
}

.restore-report-block li span {
	min-width: 0;
	overflow-wrap: anywhere;
}

.restore-report-block li strong {
	flex-shrink: 0;
}

.restore-report-note {
	margin-top: 12px;
	font-size: var(--cobudget-font-compact);
}

.backup-create-button,
:deep(.backup-create-button.button-vue),
.backup-create-button :deep(.button-vue) {
	background-color: var(--color-primary-element, var(--color-primary, #0082c9)) !important;
	color: var(--color-primary-text, #fff) !important;
	border-color: var(--color-primary-element, var(--color-primary, #0082c9)) !important;
}

.backup-create-button:hover,
.backup-create-button:focus-visible,
:deep(.backup-create-button.button-vue:hover),
:deep(.backup-create-button.button-vue:focus-visible),
.backup-create-button :deep(.button-vue:hover),
.backup-create-button :deep(.button-vue:focus-visible) {
	background-color: var(--color-primary-hover, var(--color-primary, #0082c9)) !important;
	color: var(--color-primary-text, #fff) !important;
	border-color: var(--color-primary-hover, var(--color-primary, #0082c9)) !important;
}

.backup-list {
	margin-top: 22px;
}

.backup-list-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 10px;
}

.backup-list-header h4 {
	font-size: var(--cobudget-font-md);
	font-weight: 700;
	margin: 0;
}

.compact-hint {
	margin-bottom: 0;
}

.backup-empty {
	margin: 0;
	padding: 16px;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	font-style: italic;
}

.backup-items {
	list-style: none;
	margin: 0;
	padding: 0;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	overflow: hidden;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.backup-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 12px 14px;
	border-top: 1px solid var(--cobudget-border, #ddd);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.backup-item:first-child {
	border-top: 0;
}

.backup-info {
	display: flex;
	flex-direction: column;
	min-width: 0;
	gap: 2px;
}

.backup-info span,
.backup-info small {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.backup-info small {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888)) !important;
}

.backup-item-actions {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	flex-shrink: 0;
}

.backup-restore-action {
	display: inline-flex;
}

.reset-section {
	padding-top: 22px;
	border-top: 1px solid var(--cobudget-border, #ddd);
}

.reset-section h3 {
	color: var(--cobudget-error);
}

.reset-danger-button,
:deep(.reset-danger-button.button-vue),
.reset-danger-button :deep(.button-vue) {
	background: var(--cobudget-surface, #fff) !important;
	border: 1px solid var(--cobudget-error) !important;
	color: var(--cobudget-error) !important;
}

.reset-danger-button:hover:not(:disabled),
.reset-danger-button:focus-visible:not(:disabled),
:deep(.reset-danger-button.button-vue:hover:not(:disabled)),
:deep(.reset-danger-button.button-vue:focus-visible:not(:disabled)),
.reset-danger-button :deep(.button-vue:hover:not(:disabled)),
.reset-danger-button :deep(.button-vue:focus-visible:not(:disabled)) {
	background: var(--cobudget-error) !important;
  border: 1px solid var(--cobudget-error) !important;
	color: var(--cobudget-primary-text, #fff) !important;
}

.reset-danger-button:disabled,
:deep(.reset-danger-button.button-vue:disabled),
.reset-danger-button :deep(.button-vue:disabled) {
	background: var(--cobudget-surface-muted, #eee) !important;
	border-color: var(--cobudget-border-strong, #ccc) !important;
	color: var(--cobudget-text-muted, #666) !important;
	opacity: 1;
}

.setting-toggle-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}

.setting-desc {
	font-size: var(--cobudget-font-compact);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	margin-top: 2px;
	margin-bottom: 8px;
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
}

.backup-settings-grid .form-control,
.backup-settings-grid input,
.backup-settings-grid select,
.settings-modal input,
.settings-modal select,
.settings-modal textarea {
	background-color: var(--cobudget-surface, var(--color-main-background, #fff)) !important;
	border-color: var(--cobudget-border-strong, var(--color-border-dark, #ccc)) !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.backup-settings-grid input::placeholder,
.settings-modal input::placeholder,
.settings-modal textarea::placeholder {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888)) !important;
	opacity: 1;
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

.select-control {
	appearance: auto;
	-webkit-appearance: auto;
	-moz-appearance: auto;
	cursor: pointer;
	height: 34px;
}

.add-form {
	display: flex;
	gap: 10px;
	margin-bottom: 20px;
	margin-top: 10px;
	align-items: center;
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

/* Custom Toggle Switch */
.toggle-switch {
	position: relative;
	display: inline-block;
	width: 44px;
	height: 24px;
	flex-shrink: 0;
}
.toggle-switch input {
	opacity: 0;
	width: 0;
	height: 0;
}
.toggle-slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: var(--cobudget-border, #ccc);
	transition: .4s;
	border-radius: 24px;
}
.toggle-slider:before {
	position: absolute;
	content: "";
	height: 18px;
	width: 18px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: .4s;
	border-radius: 50%;
	box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
input:checked + .toggle-slider {
	background-color: var(--color-primary, #0082c9);
}
input:checked + .toggle-slider:before {
	transform: translateX(20px);
}

.settings-modal-backdrop {
	--cobudget-text: #222;
	--cobudget-text-muted: #666;
	--cobudget-surface: #fff;
	--cobudget-surface-muted: #f5f5f5;
	--cobudget-surface-strong: #e5e5e5;
	--cobudget-border: #ddd;
	--cobudget-border-strong: #ccc;
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

html.cobudget-theme-dark .settings-modal-backdrop,
body.cobudget-theme-dark .settings-modal-backdrop,
html[data-cobudget-theme="dark"] .settings-modal-backdrop,
body[data-cobudget-theme="dark"] .settings-modal-backdrop,
html[data-themes*="dark"]:not(.cobudget-theme-light) .settings-modal-backdrop,
body[data-themes*="dark"]:not(.cobudget-theme-light) .settings-modal-backdrop,
html[data-theme*="dark"]:not(.cobudget-theme-light) .settings-modal-backdrop,
body[data-theme*="dark"]:not(.cobudget-theme-light) .settings-modal-backdrop,
html[data-theme-default*="dark"]:not(.cobudget-theme-light) .settings-modal-backdrop,
body[data-theme-default*="dark"]:not(.cobudget-theme-light) .settings-modal-backdrop,
html[data-color-scheme*="dark"]:not(.cobudget-theme-light) .settings-modal-backdrop,
body[data-color-scheme*="dark"]:not(.cobudget-theme-light) .settings-modal-backdrop,
html[data-theme-dark]:not(.cobudget-theme-light) .settings-modal-backdrop,
body[data-theme-dark]:not(.cobudget-theme-light) .settings-modal-backdrop,
html.dark:not(.cobudget-theme-light) .settings-modal-backdrop,
body.dark:not(.cobudget-theme-light) .settings-modal-backdrop,
html.theme-dark:not(.cobudget-theme-light) .settings-modal-backdrop,
body.theme-dark:not(.cobudget-theme-light) .settings-modal-backdrop,
html.theme--dark:not(.cobudget-theme-light) .settings-modal-backdrop,
body.theme--dark:not(.cobudget-theme-light) .settings-modal-backdrop {
	--cobudget-text: #f5f5f5;
	--cobudget-text-muted: #b3b3b3;
	--cobudget-surface: #181818;
	--cobudget-surface-muted: #242424;
	--cobudget-surface-strong: #303030;
	--cobudget-border: #3a3a3a;
	--cobudget-border-strong: #555;
	--color-main-text: var(--cobudget-text);
	--color-text-maxcontrast: var(--cobudget-text-muted);
	--color-main-background: var(--cobudget-surface);
	--color-background-hover: var(--cobudget-surface-muted);
	--color-background-dark: var(--cobudget-surface-muted);
	--color-border: var(--cobudget-border);
	--color-border-dark: var(--cobudget-border-strong);
}

@media (prefers-color-scheme: dark) {
	html.cobudget-theme-auto .settings-modal-backdrop,
	body.cobudget-theme-auto .settings-modal-backdrop {
		--cobudget-text: #f5f5f5;
		--cobudget-text-muted: #b3b3b3;
		--cobudget-surface: #181818;
		--cobudget-surface-muted: #242424;
		--cobudget-surface-strong: #303030;
		--cobudget-border: #3a3a3a;
		--cobudget-border-strong: #555;
		--color-main-text: var(--cobudget-text);
		--color-text-maxcontrast: var(--cobudget-text-muted);
		--color-main-background: var(--cobudget-surface);
		--color-background-hover: var(--cobudget-surface-muted);
		--color-background-dark: var(--cobudget-surface-muted);
		--color-border: var(--cobudget-border);
		--color-border-dark: var(--cobudget-border-strong);
	}
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
  color: var(--cobudget-text-muted, #888);
  font-size: var(--cobudget-font-sm);
  letter-spacing: 0.5px;
}

.modal-error {
	color: var(--cobudget-error);
	margin: 0 0 16px 0;
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

	.backup-settings-grid,
	.backup-item {
		grid-template-columns: 1fr;
		flex-direction: column;
		align-items: stretch;
	}

	.backup-save-action {
		align-items: stretch;
	}

	.restore-report-summary,
	.restore-report-grid {
		grid-template-columns: 1fr;
	}

	.backup-item-actions {
		justify-content: flex-start;
		flex-wrap: wrap;
	}

	.settings-modal {
		padding: 20px;
		width: calc(100% - 32px);
	}
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

@media (max-width: 480px) {
	.tab-button {
		padding: 8px 10px;
	}
}
</style>
