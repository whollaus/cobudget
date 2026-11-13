<template>
	<div class="cobudget-settings">
		<AppPageHeader :title="$texts.settings.settings()">
			<template #title>
				<ReportPeriodPicker navigation-only force-navigation :period-enabled="false" :title="$texts.settings.settings()" label="" />
			</template>
		</AppPageHeader>

		<NcNoteCard v-if="settingsActionError" type="error" :text="settingsActionError" />
		<NcNoteCard v-if="settingsLoadError" type="error">
			<p>{{ settingsLoadError }}</p><NcButton :disabled="loading || backupSettingsSaving" @click="fetchData">{{ $texts.common.retry() }}</NcButton>
		</NcNoteCard>
		<div v-else class="settings-section">
			<div class="settings-general">
			<div class="settings-grid">
				<div class="settings-column">
					<div class="settings-mini-section settings-basic-section">
						<h3 class="cobudget-section-heading">{{ $texts.settings.generalTitle() }}</h3>
						<div class="settings-basic-fields">
							<NcFormGroup class="setting-item cobudget-form-group">
								<template #label><label class="cobudget-form-label" for="settings-currency">{{ $texts.settings.currency() }}</label></template>
								<template #description><span id="settings-currency-hint">{{ $texts.settings.currencyExamples() }}</span></template>
								<NcInputField id="settings-currency" v-model="currency" label-outside :label="$texts.settings.currency()" :placeholder="$texts.settings.currencyPlaceholder()" aria-describedby="settings-currency-hint" :disabled="loading || backupSettingsSaving" @blur="saveCurrency" @keydown.enter.prevent="$event.target.blur()" class="cobudget-native-field" />
							</NcFormGroup>
							<NcFormGroup class="setting-item cobudget-form-group">
								<template #label><label class="cobudget-form-label" for="settings-start-page">{{ $texts.settings.startPage() }}</label></template>
								<template #description><span id="settings-start-page-hint">{{ $texts.settings.startPageDescription() }}</span></template>
								<NcSelect input-id="settings-start-page" class="cobudget-native-select" label-outside :input-label="$texts.settings.startPage()" :model-value="startPageChoices.find(option => option.value === defaultStartPage)" :options="startPageChoices" label="label" :clearable="false" :selectable="option => !option.heading" aria-describedby="settings-start-page-hint" :disabled="loading || backupSettingsSaving" @update:model-value="saveBasicSelection('defaultStartPage', $event)" />
							</NcFormGroup>
							<div class="setting-item">
								<NcRadioGroup class="cobudget-form-group" :label="$texts.settings.entriesPerPage()" :description="$texts.settings.entriesPerPageDescription()" :model-value="String(entryPageSize)" @update:model-value="saveBasicSelection('entryPageSize', { value: Number($event) })">
									<NcRadioGroupButton v-for="size in entryPageSizeOptions" :key="size" :value="String(size)" :label="String(size)" :disabled="loading || backupSettingsSaving" />
								</NcRadioGroup>
							</div>
							<div class="setting-item">
								<NcRadioGroup class="cobudget-form-group" :label="$texts.settings.themeMode()" :model-value="themeMode" @update:model-value="saveBasicSelection('themeMode', { value: $event })">
									<NcRadioGroupButton v-for="option in themeChoices" :key="option.value" :value="option.value" :label="option.label" :disabled="loading || backupSettingsSaving" />
								</NcRadioGroup>
							</div>
						</div>
					</div>

					<div class="settings-mini-section">
						<h3 class="cobudget-section-heading">{{ $texts.settings.nextcloudHeader() }}</h3>
						<div class="settings-native-options settings-header-options">
							<NcFormBoxSwitch v-model="showNextcloudHeaderDesktop" :label="$texts.settings.showNextcloudHeaderDesktop()" :disabled="loading || backupSettingsSaving" @update:model-value="saveNextcloudHeaderSettings()" />
							<NcFormBoxSwitch v-model="showNextcloudHeaderMobile" :label="$texts.settings.showNextcloudHeaderMobile()" :disabled="loading || backupSettingsSaving" @update:model-value="saveNextcloudHeaderSettings()" />
						</div>
					</div>

					<div class="settings-mini-section settings-notification-options">
						<h3 class="cobudget-section-heading">{{ $texts.settings.notifications() }}</h3>
						<div class="settings-native-options">
							<table class="settings-notification-table" :aria-label="$texts.settings.notifications()">
								<thead><tr><th scope="col">{{ $texts.settings.notificationEvent() }}</th><th class="notification-channel-nextcloud" scope="col">{{ $texts.settings.nextcloudChannel() }}</th><th class="notification-channel-email" scope="col">{{ $texts.settings.emailChannel() }}</th><th v-if="talk.available" class="notification-channel-talk" scope="col">{{ $texts.settings.talkChannel() }}</th><th v-if="calendar.available" class="notification-channel-calendar" scope="col">{{ $texts.settings.calendarChannel() }}</th></tr></thead>
								<tbody>
									<tr v-for="row in notificationRows" :key="row.field">
										<th scope="row">{{ row.label }}</th>
										<td class="notification-channel-nextcloud"><NcCheckboxRadioSwitch :model-value="row.value" :aria-label="`${row.label} – Nextcloud`" :disabled="loading || backupSettingsSaving" @update:model-value="saveNotificationPreference(row.field, $event)" /></td>
										<td class="notification-channel-email"><NcCheckboxRadioSwitch :model-value="this[row.field + 'Email']" :aria-label="`${row.label} – ${$texts.settings.emailChannel()}`" :disabled="loading || backupSettingsSaving || (!emailAddressAvailable && !this[row.field + 'Email'])" @update:model-value="saveEmailPreference(row.field + 'Email', $event)" /></td>
										<td v-if="talk.available" class="notification-channel-talk">
											<NcCheckboxRadioSwitch v-if="row.channel" :model-value="!!talk[row.talkField]" :aria-label="`${row.label} – Talk`" :disabled="talkBusy || !!talkError || !talkLoaded || (!talk.available && !talk[row.talkField])" @update:model-value="saveTalkSettings($event, row.channel)" />
											<span v-else :aria-label="$texts.settings.notificationChannelUnavailable()" :title="$texts.settings.notificationChannelUnavailable()">—</span>
										</td>
										<td v-if="calendar.available" class="notification-channel-calendar">
											<NcCheckboxRadioSwitch v-if="row.field === 'notifyReminders'" :model-value="calendar.enabled || calendarSetupOpen" :aria-label="`${row.label} – ${$texts.settings.calendarChannel()}`" :disabled="calendarBusy || !!calendarError" @update:model-value="toggleCalendarReminders" />
											<span v-else :aria-label="$texts.settings.notificationChannelUnavailable()" :title="$texts.settings.notificationChannelUnavailable()">—</span>
										</td>
									</tr>
								</tbody>
							</table>
							<div v-if="calendar.available && (calendar.enabled || calendarSetupOpen)" class="settings-calendar-target">
								<label for="settings-reminder-calendar">{{ $texts.settings.targetCalendar() }}</label>
								<NcSelect input-id="settings-reminder-calendar" class="cobudget-native-select" label-outside :placeholder="$texts.settings.chooseCalendar()" :options="calendar.calendars" label="label" :clearable="false" :model-value="calendar.calendars.find(option => option.id === calendar.calendar_id) || null" :disabled="calendarBusy || !!calendarError" @update:model-value="saveCalendarSettings(true, $event.id)" />
								<p class="setting-desc">{{ $texts.settings.calendarSyncDescription() }}</p>
							</div>
							<NcNoteCard v-if="calendarError" type="error"><p>{{ calendarError }}</p><NcButton :disabled="calendarBusy" @click="fetchCalendarSettings">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
							<NcNoteCard v-else-if="calendar.sync_pending" type="warning"><p>{{ $texts.settings.calendarSyncPending() }}</p><NcButton :disabled="calendarBusy" @click="saveCalendarSettings(calendar.enabled, calendar.calendar_id)">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
							<NcNoteCard v-if="!loading && !emailAddressAvailable" type="warning">{{ $texts.settings.emailAddressMissing() }}</NcNoteCard>
							<NcNoteCard v-if="talkError" type="error"><p>{{ talkError }}</p><NcButton :disabled="talkBusy" @click="fetchTalkSettings">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
							<div v-if="!talkError && (!talkLoaded || (talk.available && (talk.enabled || talk.reminders_enabled || talk.planned_enabled) && !talk.ready))" class="settings-talk-status">
								<p v-if="!talkLoaded" class="settings-loading" role="status"><NcLoadingIcon :size="20" /> {{ $texts.common.loading() }}</p>
								<template v-else>
									<template v-if="(talk.enabled || talk.reminders_enabled || talk.planned_enabled) && !talk.ready">
										<p class="setting-desc">{{ $texts.settings.talkReconnectDescription() }}</p>
										<NcButton :disabled="talkBusy" @click="saveTalkSettings(true, talk.reminders_enabled ? 'reminders' : (talk.planned_enabled ? 'planned' : 'payments'))">{{ $texts.settings.reconnectPersonalTalk() }}</NcButton>
									</template>
								</template>
							</div>
						</div>
					</div>

					<div class="settings-mini-section">
						<h3 class="cobudget-section-heading">{{ $texts.settings.receipts() }}</h3>
						<NcFormBoxSwitch v-model="enableReceipts" :label="$texts.settings.enableReceipts()" :description="$texts.settings.enableReceiptsDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
						<div v-if="enableReceipts" class="setting-item" style="margin-top: 16px;">
							<NcFormBoxButton id="settings-receipt-folder" :label="$texts.settings.storageFolder()" :description="folderDisplayPath(receiptStorageFolder)" inverted-accent :disabled="loading || folderPickerOpen" @click="chooseSettingsFolder('receipt')">
								<template #icon><FolderOpenOutlineIcon :size="24" /></template>
							</NcFormBoxButton>
						</div>
						<div v-if="enableReceipts" class="setting-item" style="margin-top: 16px;">
							<NcRadioGroup class="cobudget-form-group settings-folder-grouping" :label="$texts.settings.folderGrouping()" :description="$texts.settings.folderGroupingDescription()" :model-value="receiptFolderGrouping" @update:model-value="saveBasicSelection('receiptFolderGrouping', { value: $event })">
								<NcRadioGroupButton value="none" :label="$texts.settings.noSubfolders()" :disabled="loading || backupSettingsSaving" />
								<NcRadioGroupButton value="year" :label="$texts.settings.byYear()" :disabled="loading || backupSettingsSaving" />
								<NcRadioGroupButton value="year_month" :label="$texts.settings.byYearAndMonth()" :disabled="loading || backupSettingsSaving" />
							</NcRadioGroup>
						</div>
						<NcFormBoxSwitch v-if="enableReceipts" v-model="deleteReceiptsWithEntry" class="settings-receipt-delete" :label="$texts.settings.deleteReceiptsWithEntry()" :description="$texts.settings.deleteReceiptsWithEntryDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
					</div>

				</div>

				<div class="settings-column">

					<div class="settings-mini-section settings-feature-options">
						<h3 class="cobudget-section-heading">{{ $texts.settings.features() }}</h3>
						<p class="setting-desc">{{ $texts.settings.featuresDescription() }}</p>
						<div class="settings-native-options">
							<NcFormBoxSwitch v-model="enableIncomes" :label="$texts.settings.incomes()" :description="$texts.settings.incomesDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableFuturePayments" :label="$texts.settings.futurePayments()" :description="$texts.settings.futurePaymentsDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableProjects" :label="$texts.settings.enableAreas()" :description="$texts.settings.enableAreasDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-if="enableProjects" v-model="enableSharedProjects" :label="$texts.settings.enableSharedAreas()" :description="$texts.settings.enableSharedAreasDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableReminders" :label="$texts.settings.reminders()" :description="$texts.settings.remindersFeatureDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableBudgetGoals" :label="$texts.settings.budgetGoals()" :description="$texts.settings.budgetGoalsDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableAdvancedMasterData" :label="$texts.settings.advancedCategoryPaymentPartnerSettings()" :description="$texts.settings.advancedCategoryPaymentPartnerSettingsDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<div ref="workspaceOptions" class="settings-native-options settings-workspace-options">
								<NcFormBoxSwitch v-model="enableWorkspaces" :label="$texts.settings.enableWorkspaces()" :description="$texts.settings.workspacesHint()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
								<NcFormBoxSwitch v-if="enableWorkspaces" v-model="showWorkspaceSwitcher" :label="$texts.settings.workspaceSwitcher()" :description="$texts.settings.workspaceSwitcherDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							</div>
						</div>
					</div>

					<div class="settings-mini-section settings-label-options">
						<h3 class="cobudget-section-heading">{{ $texts.settings.labels() }}</h3>
						<p class="setting-desc">{{ $texts.settings.labelsDescription() }}</p>
						<div class="settings-native-options">
							<NcFormBoxSwitch v-model="enableImportantPayments" :label="$texts.settings.importantPayments()" :description="$texts.settings.importantPaymentsDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableReviewPayments" :label="$texts.settings.reviewPayments()" :description="$texts.settings.reviewPaymentsDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableFixedCosts" :label="$texts.settings.fixedCosts()" :description="$texts.settings.fixedCostsDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableChildRelated" :label="$texts.settings.childRelated()" :description="$texts.settings.childRelatedDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableSubscriptions" :label="$texts.settings.subscriptions()" :description="$texts.settings.subscriptionsDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableTaxRelevant" :label="$texts.settings.taxRelevant()" :description="$texts.settings.taxRelevantDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
							<NcFormBoxSwitch v-model="enableInsurance" :label="$texts.settings.insurance()" :description="$texts.settings.insuranceDescription()" :disabled="loading || backupSettingsSaving" @update:model-value="saveGeneralSettings()" />
						</div>
					</div>
				</div>
			</div>
		</div>

		<MasterDataSettings ref="masterDataSettings" class="settings-master-data" :advanced="enableAdvancedMasterData" :incomes="enableIncomes" @loaded="onMasterDataLoaded" />

		<div v-if="savedGeneralSettings?.enableWorkspaces" class="settings-subsection workspaces-section">
			<div class="workspace-section-header">
				<h3 class="cobudget-section-heading">{{ $texts.settings.workspaces() }}</h3>
				<NcButton v-if="enableWorkspaces" class="workspace-add-button cobudget-native-button" variant="secondary" :disabled="loading || workspaceManagementLoading" @click="$refs.workspaceSettings?.openCreateWorkspaceModal()">
					<template #icon><PlusIcon :size="20" /></template>
					{{ $texts.workspaces.add() }}
				</NcButton>
			</div>

			<WorkspaceSettings v-if="savedGeneralSettings?.enableWorkspaces" ref="workspaceSettings" @loading="workspaceManagementLoading = $event" />
		</div>

		<div class="settings-subsection backups-section">
			<h3 class="cobudget-section-heading">{{ $texts.settings.backups() }}</h3>
			<p class="settings-hint">{{ $texts.settings.backupsHint() }}</p>

			<div class="backup-settings-grid">
				<div class="setting-item backup-schedule-setting">
					<NcRadioGroup class="cobudget-form-group backup-schedule-group" :label="$texts.settings.automaticBackups()" :description="$texts.settings.automaticBackupsDescription()" :model-value="backupSchedule" @update:model-value="saveBackupSchedule({ value: $event })">
						<div class="backup-schedule-options">
							<NcRadioGroupButton v-for="option in backupScheduleChoices" :key="option.value" :value="option.value" :label="option.label" :disabled="backupSettingsDisabled || !backupSettingsValid" />
						</div>
					</NcRadioGroup>
				</div>
				<NcFormGroup class="setting-item cobudget-form-group backup-folder-setting">
					<template #label><label class="cobudget-form-label" for="settings-backup-folder">{{ $texts.settings.backupFolder() }}</label></template>
					<NcFormBoxButton id="settings-backup-folder" :label="folderDisplayPath(backupStorageFolder)" :disabled="backupSettingsDisabled || !backupSettingsValid || folderPickerOpen" @click="chooseSettingsFolder('backup')">
						<template #icon><FolderOpenOutlineIcon :size="24" /></template>
					</NcFormBoxButton>
				</NcFormGroup>
				<NcFormGroup class="setting-item cobudget-form-group">
					<template #label><label class="cobudget-form-label" for="settings-backup-retention">{{ $texts.settings.retainCount() }}</label></template>
					<template #description><span id="settings-backup-retention-hint">{{ $texts.settings.retainCountDescription() }}</span></template>
					<NcInputField id="settings-backup-retention" v-model="backupRetentionCount" type="number" label-outside :label="$texts.settings.retainCount()" aria-describedby="settings-backup-retention-hint" :disabled="backupSettingsDisabled" required min="1" max="100" step="1" :error="!backupRetentionValid" :helper-text="!backupRetentionValid ? $texts.settings.invalidRetentionCount() : ''" @blur="autoSaveBackupSettings" @keydown.enter.prevent="$event.target.blur()" class="cobudget-native-field" />
				</NcFormGroup>
				<div class="backup-manual-actions">
					<NcButton class="backup-create-button cobudget-native-button" variant="secondary" @click="createBackup" :disabled="backupSettingsDisabled || !backupSettingsValid">
						<template #icon><PlusIcon :size="20" /></template>
						{{ backupCreating ? $texts.settings.backupCreating() : $texts.settings.createBackupNow() }}
					</NcButton>
				</div>
			</div>

			<div class="backup-list">
				<div class="backup-list-header">
					<h4 class="cobudget-subheading">{{ $texts.settings.latestBackups() }}</h4>
				</div>
				<NcNoteCard v-if="backupActionError" type="error" :text="backupActionError" />
				<p v-if="backupsLoading" class="settings-loading" role="status"><NcLoadingIcon :size="20" /> {{ $texts.settings.loadingBackups() }}</p>
				<NcNoteCard v-else-if="backupsError" type="error"><p>{{ backupsError }}</p><NcButton @click="fetchBackups">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
				<SettingsList v-else class="backup-items" borderless :items="backups" key-field="file_name" hidden-field="" :empty-text="$texts.settings.noBackups()">
					<template #item="{ item: backup }">
						<div class="backup-info">
							<span class="backup-date">{{ formatBackupDate(backup.created_at) }}</span>
							<small>{{ backup.file_path }} · {{ formatFileSize(backup.file_size) }}</small>
						</div>
						<div class="settings-list-actions backup-item-actions">
							<span class="backup-restore-action">
								<DisabledActionHint :reason="backupRestoreHint(backup)" :label="$texts.settings.restoreBackup()"><NcButton variant="tertiary"
									:disabled="backupActionsDisabled || backup.can_restore === false"
									@click="restoreBackup(backup)">
									{{ backupRestoringFileName === backup.file_name ? $texts.settings.restoringBackup() : $texts.settings.restoreBackup() }}
								</NcButton></DisabledActionHint>
							</span>
							<NcButton variant="tertiary"
								:aria-label="$texts.settings.downloadBackupAria(backup.file_name)"
								:title="$texts.settings.downloadBackupAria(backup.file_name)"
								:disabled="backupActionsDisabled"
								@click="downloadBackup(backup)">
								<template #icon>
									<DownloadIcon :size="20" />
								</template>
							</NcButton>
							<DisabledActionHint :reason="backupActionsDisabled ? $texts.common.waitBusy() : ''" :label="$texts.settings.deleteBackupAria(backup.file_name)" ><NcButton
								variant="tertiary"
								:aria-label="$texts.settings.deleteBackupAria(backup.file_name)"
								:title="$texts.settings.deleteBackupAria(backup.file_name)"
								:disabled="backupActionsDisabled"
								@click="deleteBackup(backup)">
								<template #icon>
									<DeleteOutlineIcon :size="20" />
								</template>
							</NcButton></DisabledActionHint>
						</div>
					</template>
				</SettingsList>
			</div>
		</div>

		<KeyboardShortcuts />

		<div class="settings-subsection reset-section">
			<h3 class="cobudget-section-heading">{{ $texts.settings.resetTitle() }}</h3>
			<p class="settings-hint">
				{{ $texts.settings.resetHint() }}
			</p>
			<NcNoteCard v-if="resetPreviewError" id="settings-reset-error" type="error">
				<p>{{ resetPreviewError }}</p>
				<NcButton :disabled="resetPreviewLoading || resetRunning" @click="refreshResetAvailability">{{ $texts.common.retry() }}</NcButton>
			</NcNoteCard>
			<DisabledActionHint :reason="resetButtonDisabled ? (resetBlockerMessage || resetPreviewError || (resetRunning ? $texts.settings.resetRunning() : $texts.common.loading())) : ''" :label="$texts.settings.resetButton()" ><NcButton
				class="reset-danger-button"
				variant="error"
				:disabled="resetButtonDisabled"
				:aria-describedby="resetPreviewError ? 'settings-reset-error' : undefined"
				@click="resetAllData">
				{{ resetRunning ? $texts.settings.resetRunning() : $texts.settings.resetButton() }}
			</NcButton></DisabledActionHint>
		</div>
		</div>


		<DialogTransition :show="!!(confirmDialog)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="confirmDialog.title" :size="confirmDialog.wide ? 'large' : 'normal'" close-on-click-outside @closing="resolveConfirm(false)">
				<p class="settings-confirm-message cobudget-confirm-message">{{ confirmDialog.message }}</p>
				<NcInputField v-if="confirmDialog.requiredText" :id="`settings-${confirmDialog.kind}-confirmation`" v-model="confirmDialog.confirmationText" :label="$texts.settings.confirmationExact(confirmDialog.requiredText)" autocomplete="off" autocapitalize="off" :spellcheck="false" class="cobudget-native-field" />
				<template #actions>
					<div class="settings-confirm-actions cobudget-confirm-actions">
						<NcButton variant="tertiary" @click="resolveConfirm(false)">{{ $texts.common.cancel() }}</NcButton>
						<DisabledActionHint :reason="!confirmDialogCanConfirm ? $texts.settings.confirmationExact(confirmDialog.requiredText) : ''" :label="confirmDialog.confirmLabel"><NcButton :variant="confirmDialog.confirmVariant === 'danger' ? 'error' : 'primary'" :disabled="!confirmDialogCanConfirm" @click="resolveConfirm(true)">{{ confirmDialog.confirmLabel }}</NcButton></DisabledActionHint>
					</div>
				</template>
			</NcDialog>
		</DialogTransition>
	</div>
</template>

<script>
import KeyboardShortcuts from '../components/KeyboardShortcuts.vue'
import DialogTransition from '../components/DialogTransition.vue'
import DisabledActionHint from '../components/DisabledActionHint.vue'
import { getAppLocale } from '../utils/formatMoney'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { showToast, extractError } from '../services/notifications'
import { generalSettingsFields, applySavedSettings } from '../services/settingsState'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import { Permission } from '@nextcloud/files'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { pickFolder } from '../services/folderPicker'
import { ENTRY_PAGE_SIZE_OPTIONS, normalizeEntryPageSize } from '../services/pagination'
import { applyThemeMode, normalizeThemeMode } from '../services/themeMode'
import { applyNextcloudHeaderVisibility } from '../services/nextcloudHeader'
import { clearWorkspaceId, readWorkspaceId, writeWorkspaceId } from '../services/workspaceStorage'
import { sortAreasByName } from '../utils/areaSorting'
import MasterDataSettings from '../components/MasterDataSettings.vue'
import WorkspaceSettings from '../components/WorkspaceSettings.vue'
import SettingsList from '../components/SettingsList.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import FormatListBulletedIcon from 'vue-material-design-icons/FormatListBulleted.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FolderOpenOutlineIcon from 'vue-material-design-icons/FolderOpenOutline.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'


export default {
	name: 'SettingsView',
	components: { KeyboardShortcuts, DialogTransition, DisabledActionHint,
		MasterDataSettings,
		NcInputField,
		NcSelect,
		NcRadioGroup,
		NcFormGroup,
		NcRadioGroupButton,
		NcButton,
		NcDialog,
		NcNoteCard,
		NcLoadingIcon,
		NcFormBoxSwitch,
		NcCheckboxRadioSwitch,
		NcFormBoxButton,
		FolderOpenOutlineIcon,
		PlusIcon,
		WorkspaceSettings,
		SettingsList,
		AppPageHeader,
		ReportPeriodPicker,
		FormatListBulletedIcon,
		AccountMultipleIcon,
		CogIcon,
		DeleteOutlineIcon,
		DownloadIcon,
	},
	data() {
		return {
			loading: false,
			settingsLoadError: '',
			settingsActionError: '',
			backupActionError: '',
			savedGeneralSettings: null,
			backupsError: '',
			emailAddressAvailable: false,
			notifyRemindersEmail: false,
			notifyPlannedPaymentsEmail: false,
			notifyProjectEntriesEmail: false,
			notifyProjectSettlementsEmail: false,
			talkError: '',
			folderPickerOpen: false,
			currency: '',
			savedCurrency: '',
			pendingWorkspaceScroll: false,
			pendingMasterDataSection: '',
			workspaceManagementLoading: true,
			masterDataLoaded: false,
			enableSubscriptions: true,
			enableFixedCosts: true,
			enableChildRelated: true,
			enableImportantPayments: true,
			enableReviewPayments: true,
			enableTaxRelevant: true,
			enableInsurance: true,
			enableFuturePayments: true,
			enableReminders: true,
			enableBudgetGoals: true,
			enableAdvancedMasterData: false,
			enableIncomes: true,
			enableProjects: true,
			enableSharedProjects: true,
			notifyProjectEntries: true,
			notifyReminders: true,
			notifyPlannedPayments: false,
			calendar: { available: false, enabled: false, calendars: [], calendar_id: '', sync_pending: false },
			calendarBusy: false,
			calendarError: '',
			calendarSetupOpen: false,
			talk: { available: false, enabled: false, reminders_enabled: false, planned_enabled: false, ready: false, url: null },
			talkBusy: false,
			talkLoaded: false,
			notifyProjectSettlements: true,
			enableWorkspaces: false,
			showWorkspaceSwitcher: true,
			enableReceipts: true,
			defaultStartPage: 'personal',
			entryPageSize: 25,
			entryPageSizeOptions: ENTRY_PAGE_SIZE_OPTIONS,
			themeMode: 'auto',
			showNextcloudHeaderDesktop: true,
			showNextcloudHeaderMobile: true,
			receiptStorageFolder: 'CoBudget/Belege',
			savedReceiptStorageFolder: 'CoBudget/Belege',
			receiptFolderGrouping: 'year',
			deleteReceiptsWithEntry: false,
			backupStorageFolder: 'CoBudget/Export',
			backupSettingsSaving: false,
			savedBackupSettings: null,
			backupRetentionCount: 7,
			backupSchedule: 'none',
			backups: [],
			backupsLoading: false,
			backupCreating: false,
			backupDeletingFileName: '',
			backupRestoringFileName: '',
			resetRunning: false,
			resetPreview: null,
			resetPreviewLoading: false,
			resetPreviewError: '',
			projects: [],
			confirmDialog: null
		}
	},
	computed: {
		notificationRows() {
			const rows = [
				{ field: 'notifyReminders', label: this.$texts.settings.reminders(), value: this.notifyReminders, channel: 'reminders', talkField: 'reminders_enabled' },
				{ field: 'notifyPlannedPayments', label: this.$texts.settings.plannedPaymentNotifications(), value: this.notifyPlannedPayments, channel: 'planned', talkField: 'planned_enabled' },
			];
			if (this.enableProjects && this.enableSharedProjects) rows.push(
				{ field: 'notifyProjectEntries', label: this.$texts.settings.newPaymentsInSharedAreas(), value: this.notifyProjectEntries, channel: 'payments', talkField: 'enabled' },
				{ field: 'notifyProjectSettlements', label: this.$texts.settings.settledAreas(), value: this.notifyProjectSettlements },
			);
			return rows;
		},
		startPageChoices() {
			const choices = [
				{ value: 'personal', label: this.$texts.labels.allPayments() },
			];
			if (this.enableProjects) {
				choices.push({ value: 'projects', label: this.$texts.settings.myAreas() });
				if (this.activeProjects.length) {
					choices.push({ value: 'area-heading', label: this.$texts.settings.openArea(), heading: true });
					choices.push(...this.activeProjects.map(project => ({ value: this.projectStartPageValue(project), label: project.name })));
				}
			}
			return choices;
		},
		themeChoices() {
			return [
				{ value: 'auto', label: this.$texts.settings.themeModeAuto() },
				{ value: 'light', label: this.$texts.settings.themeModeLight() },
				{ value: 'dark', label: this.$texts.settings.themeModeDark() },
			];
		},
		backupScheduleChoices() {
			return [
				{ value: 'none', label: this.$texts.common.no() },
				{ value: 'daily', label: this.$texts.settings.daily() },
				{ value: 'weekly', label: this.$texts.settings.weekly() },
				{ value: 'monthly', label: this.$texts.settings.monthly() },
			];
		},
		backupSettingsDisabled() {
			return this.loading || this.backupCreating || this.backupSettingsSaving || this.backupActionsDisabled;
		},
		backupRetentionValid() {
			const count = Number(this.backupRetentionCount);
			return Number.isInteger(count) && count >= 1 && count <= 100;
		},
		backupSettingsValid() {
			return !!this.backupStorageFolder.trim() && this.backupRetentionValid;
		},
		backupSettingsPayload() {
			return {
				backup_storage_folder: this.normalizeBackupStorageFolder(this.backupStorageFolder),
				backup_retention_count: Number(this.backupRetentionCount),
				backup_schedule: this.backupSchedule || 'none',
			};
		},
		backupActionsDisabled() {
			return !!this.backupDeletingFileName || !!this.backupRestoringFileName;
		},
		activeProjects() {
			return sortAreasByName(this.projects.filter(project => !this.isArchivedProject(project)));
		},
		resetBlockerMessage() {
			return this.buildResetBlockerMessage(this.resetPreview);
		},
		resetButtonDisabled() {
			return this.loading || this.resetRunning || this.resetPreviewLoading || !this.resetPreview || !!this.resetPreviewError || !!this.resetBlockerMessage;
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
	watch: {
		'$route.query.section'(section) {
			this.consumeRequestedSection(section);
		}
	},
	mounted() {
		try {
			this.pendingWorkspaceScroll = sessionStorage.getItem('cobudget-settings-return-to-workspaces') === window.location.href;
			sessionStorage.removeItem('cobudget-settings-return-to-workspaces');
		} catch { /* Settings still work when browser storage is unavailable. */ }
		this.consumeRequestedSection(this.$route?.query?.section);
		this.fetchData()
		this.fetchTalkSettings()
		this.fetchCalendarSettings()
		this.refreshResetAvailability()
		window.addEventListener('focus', this.refreshResetAvailability)
		window.addEventListener('cobudget-data-changed', this.refreshResetAvailability)
		window.addEventListener('workspaces-updated', this.refreshResetAvailability)
	},
	beforeUnmount() {
		window.removeEventListener('focus', this.refreshResetAvailability)
		window.removeEventListener('cobudget-data-changed', this.refreshResetAvailability)
		window.removeEventListener('workspaces-updated', this.refreshResetAvailability)
	},
	methods: {
		async fetchCalendarSettings() {
			if (this.calendarBusy) return;
			this.calendarBusy = true;
			this.calendarError = '';
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/settings/calendar-reminders'), { skipWorkspaceHeader: true });
				this.calendar = response.data;
				this.calendarSetupOpen = false;
			} catch (error) {
				this.calendarError = this.extractError(error, this.$texts.settings.calendarSettingsError());
			} finally { this.calendarBusy = false; }
		},
		async toggleCalendarReminders(enabled) {
			if (this.calendarBusy || this.calendarError) return;
			if (enabled && !this.calendar.calendars.some(option => option.id === this.calendar.calendar_id)) {
				this.calendarSetupOpen = true;
				return;
			}
			if (!enabled && !this.calendar.enabled) { this.calendarSetupOpen = false; return; }
			await this.saveCalendarSettings(enabled, this.calendar.calendar_id);
		},
		async saveCalendarSettings(enabled, calendarId) {
			if (this.calendarBusy || this.calendarError) return;
			this.calendarBusy = true;
			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/settings/calendar-reminders'), { enabled, calendarId }, { skipWorkspaceHeader: true });
				this.calendar = response.data;
				this.calendarSetupOpen = false;
			} catch (error) {
				this.calendarError = this.extractError(error, this.$texts.settings.calendarSettingsError());
			} finally { this.calendarBusy = false; }
		},
		async saveEmailPreference(field, enabled) {
			if (this.loading || this.backupSettingsSaving || (enabled && !this.emailAddressAvailable) || !this.notificationRows.some(row => row.field + 'Email' === field)) return;
			this[field] = enabled;
			await this.saveGeneralSettings();
		},
		async saveNotificationPreference(field, enabled) {
			if (this.loading || this.backupSettingsSaving || !this.notificationRows.some(row => row.field === field)) return;
			this[field] = enabled;
			await this.saveGeneralSettings();
		},
		backupRestoreHint(backup) {
			if (backup.can_restore === false) return this.$texts.settings.restoreBackupBlocked();
			if (this.backupRestoringFileName) return this.$texts.settings.restoringBackup();
			if (this.backupDeletingFileName) return this.$texts.common.deleteBusy();
			return '';
		},
		async saveCurrency() {
			if (this.loading || this.backupSettingsSaving) return;
			this.currency = this.currency.trim() || this.savedCurrency;
			if (this.currency === this.savedCurrency) return;
			if (await this.saveGeneralSettings()) this.savedCurrency = this.currency;
		},
		folderDisplayPath(folder) { return '/' + String(folder || '').replace(/^\/+/, '') },
		async chooseSettingsFolder(kind) {
			const backup = kind === 'backup';
			if (this.folderPickerOpen || this.loading || (backup && (this.backupSettingsDisabled || !this.backupSettingsValid))) return;
			this.folderPickerOpen = true;
			this.settingsActionError = '';
			if (backup) this.backupActionError = '';
			const field = backup ? 'backupStorageFolder' : 'receiptStorageFolder';
			const previous = this[field];
			try {
				// The settings API requires a subfolder; do not offer root or read-only folders.
				const canPick = node => node.type === 'folder' && node.path !== '/' && !!(node.permissions & Permission.CREATE);
				const folder = await pickFolder(getFilePickerBuilder(backup ? this.$texts.settings.backupFolder() : this.$texts.settings.storageFolder())
					.setMultiSelect(false).allowDirectories(true).setMimeTypeFilter(['httpd/unix-directory'])
					.startAt('/').setCanPick(canPick)
					.setButtonFactory(nodes => [{ label: this.$texts.settings.selectFolder(), variant: 'primary', disabled: nodes.length !== 1 || !canPick(nodes[0]), callback() {} }]));
				if (typeof folder !== 'string') return;
				const path = folder.replace(/^\/+|\/+$/g, '');
				if (!path || this.folderDisplayPath(path) === this.folderDisplayPath(previous)) return;
				this[field] = path;
				const saved = backup ? await this.autoSaveBackupSettings() : await this.saveReceiptStorageFolder();
				if (!saved) this[field] = previous;
			} catch (error) {
				this[field] = previous;
				if (error?.name !== 'FilePickerClosed') this.showError(this.extractError(error, this.$texts.common.saveError()));
			} finally { this.folderPickerOpen = false; }
		},
		async saveReceiptStorageFolder() {
			if (this.loading || !this.receiptStorageFolder.trim()) return false;
			this.receiptStorageFolder = this.receiptStorageFolder.trim();
			if (this.receiptStorageFolder === this.savedReceiptStorageFolder) return true;
			if (!await this.saveGeneralSettings({ reload: false })) return false;
			this.savedReceiptStorageFolder = this.receiptStorageFolder;
			return true;
		},
		async saveBasicSelection(field, option) {
			if (this.loading || !option || option.heading || this[field] === option.value) return;
			const previous = this[field];
			this[field] = option.value;
			if (field === 'themeMode') this.applyLocalThemeMode();
			if (!await this.saveGeneralSettings()) {
				this[field] = previous;
				if (field === 'themeMode') this.applyLocalThemeMode();
			}
		},
		consumeRequestedSection(section) {
			if (!['categories', 'payment-partners'].includes(section)) return;
			this.pendingMasterDataSection = section;
			if (this.$route?.query?.section) {
				const query = { ...this.$route.query };
				delete query.section;
				this.$router?.replace?.({ name: this.$route.name || 'settings', query });
			}
			this.restoreWorkspacePosition();
		},
		onMasterDataLoaded() {
			this.masterDataLoaded = true;
			this.restoreWorkspacePosition();
		},
		restoreWorkspacePosition() {
			// Wait for settings content to settle before returning to the workspace switches or jumping to master data.
			if ((!this.pendingWorkspaceScroll && !this.pendingMasterDataSection) || this.loading || this.settingsLoadError || !this.masterDataLoaded || !this.talkLoaded) return;
			const masterDataSection = this.pendingMasterDataSection;
			const scrollWorkspace = this.pendingWorkspaceScroll;
			this.pendingMasterDataSection = '';
			this.pendingWorkspaceScroll = false;
			this.$nextTick(() => {
				if (masterDataSection) {
					if (!this.$refs.masterDataSettings?.scrollToSection?.(masterDataSection)) {
						this.pendingMasterDataSection = masterDataSection;
						this.$refs.masterDataSettings?.$el?.scrollIntoView?.({ block: 'start', inline: 'nearest', behavior: 'instant' });
					}
					return;
				}
				if (scrollWorkspace) {
					this.$refs.workspaceOptions?.scrollIntoView({ block: 'start', inline: 'nearest', behavior: 'instant' });
				}
			});
		},
		async fetchTalkSettings() {
			this.talkLoaded = false;
			this.talkError = '';
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/settings/talk'), { skipWorkspaceHeader: true });
				this.talk = response.data;
			} catch (error) {
				this.talkError = this.extractError(error, this.$texts.settings.talkSettingsError());
			} finally {
				this.talkLoaded = true;
				this.restoreWorkspacePosition();
			}
		},
		async saveTalkSettings(enabled, channel = 'payments') {
			if (this.talkBusy || !this.talkLoaded || this.talkError) return;
			this.talkBusy = true;
			this.talkError = '';
			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/settings/talk'), { enabled, channel }, { skipWorkspaceHeader: true });
				this.talk = response.data;
			} catch (error) {
				this.talkError = this.extractError(error, this.$texts.settings.talkSettingsError());
			} finally {
				this.talkBusy = false;
			}
		},
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
			if (confirmed && !this.confirmDialogCanConfirm) return;
			const dialog = this.confirmDialog;
			const resolver = dialog?.resolve;
			this.confirmDialog = null;
			if (resolver) {
				resolver(confirmed);
			}
		},
		extractError(error, fallback) {
			return extractError(error, fallback);
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
			this.settingsActionError = message;
		},
		showSuccessMessage(message) {
			showToast(message, 'success');
		},
		formatBackupDate(timestamp) {
			const date = new Date(Number(timestamp || 0) * 1000);
			if (Number.isNaN(date.getTime())) {
				return this.$texts.common.unknownDate();
			}
			return date.toLocaleString(getAppLocale(), {
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
				return `${(bytes / 1024).toLocaleString(getAppLocale(), { minimumFractionDigits: 1, maximumFractionDigits: 1 })} KB`;
			}
			return `${(bytes / (1024 * 1024)).toLocaleString(getAppLocale(), { minimumFractionDigits: 1, maximumFractionDigits: 1 })} MB`;
		},
		async fetchBackups() {
			this.backupsLoading = true;
			this.backupsError = '';
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/backups'), { skipWorkspaceHeader: true });
				this.backups = response.data?.backups || [];
			} catch (e) {
				console.error('Failed to fetch backups', e);
				this.backupsError = this.extractError(e, this.$texts.settings.backupsLoadError());
			} finally {
				this.backupsLoading = false;
			}
		},
		async createBackup() {
			this.backupActionError = '';
			if (this.backupSettingsDisabled || !this.backupSettingsValid) {
				return;
			}
			this.backupCreating = true;
			try {
				await this.saveBackupSettings();
				const response = await axios.post(generateUrl('/apps/cobudget/api/backups'), {}, { skipWorkspaceHeader: true });
				this.backups = response.data?.backups || [];
				this.showSuccessMessage(this.$texts.settings.backupCreated());
			} catch (e) {
				console.error('Failed to create backup', e);
				this.backupActionError = this.extractError(e, this.$texts.settings.backupCreateError());
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
			this.backupActionError = '';
			if (!backup?.file_name || this.backupActionsDisabled) {
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.settings.deleteBackupTitle(),
				message: this.$texts.settings.deleteBackupMessage(backup.file_name),
				confirmLabel: this.$texts.settings.deleteBackupConfirm(),
				confirmVariant: 'danger',
				kind: 'deleteBackup'
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
				this.backupActionError = this.extractError(e, this.$texts.settings.backupDeleteError());
			} finally {
				this.backupDeletingFileName = '';
			}
		},
		async restoreBackup(backup) {
			this.backupActionError = '';
			if (!backup?.file_name || this.backupActionsDisabled) {
				return;
			}
			if (backup.can_restore === false) return;
			let restoreInfo = null;
			try {
				const inspectResponse = await axios.get(
					generateUrl(`/apps/cobudget/api/backups/${encodeURIComponent(backup.file_name)}/inspect`),
					{ skipWorkspaceHeader: true }
				);
				restoreInfo = inspectResponse.data?.backup?.restore || null;
			} catch (e) {
				console.error('Failed to inspect personal export', e);
				this.backupActionError = this.extractError(e, this.$texts.settings.backupInspectError());
				return;
			}
			if (restoreInfo?.can_restore_personally === false) {
				this.backupActionError = this.$texts.settings.restoreBackupSharedDataBlocked();
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
				this.backupActionError = this.extractError(e, this.$texts.settings.backupRestoreError());
			} finally {
				this.backupRestoringFileName = '';
			}
		},
		async autoSaveBackupSettings() {
			if (this.backupSettingsDisabled || !this.backupSettingsValid) return false;
			this.backupActionError = '';
			const payload = this.backupSettingsPayload;
			if (JSON.stringify(payload) === JSON.stringify(this.savedBackupSettings)) return true;
			try {
				await this.saveBackupSettings();
				return true;
			} catch (e) {
				this.backupActionError = this.extractError(e, this.$texts.common.saveError());
				return false;
			}
		},
		async saveBackupSchedule(option) {
			if (!option || this.backupSettingsDisabled || !this.backupSettingsValid || option.value === this.backupSchedule) return;
			const previous = this.backupSchedule;
			this.backupSchedule = option.value;
			if (!await this.autoSaveBackupSettings()) this.backupSchedule = previous;
		},
		async saveBackupSettings() {
			const payload = this.backupSettingsPayload;
			const folderChanged = payload.backup_storage_folder !== this.savedBackupSettings?.backup_storage_folder;
			this.backupSettingsSaving = true;
			try {
				await axios.post(generateUrl('/apps/cobudget/api/settings'), {
					currency: this.savedCurrency,
					...payload,
				}, { skipWorkspaceHeader: true });
				this.backupStorageFolder = payload.backup_storage_folder;
				this.backupRetentionCount = payload.backup_retention_count;
				this.savedBackupSettings = { ...payload };
				if (folderChanged) await this.fetchBackups();
			} finally {
				this.backupSettingsSaving = false;
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
		refreshResetAvailability() {
			if (!this.resetRunning) return this.fetchResetPreview();
		},
		async fetchResetPreview() {
			if (this.resetPreviewLoading) return null;
			this.resetPreviewLoading = true;
			this.resetPreviewError = '';
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/settings/reset-preview'), { skipWorkspaceHeader: true });
				const preview = response.data?.reset;
				if (!preview || !Array.isArray(preview.blocking_shared_projects)) throw new Error(this.$texts.settings.resetPreviewError());
				this.resetPreview = preview;
				return preview;
			} catch (error) {
				this.resetPreview = null;
				this.resetPreviewError = this.extractError(error, this.$texts.settings.resetPreviewError());
				return null;
			} finally {
				this.resetPreviewLoading = false;
			}
		},
		async resetAllData() {
			if (this.resetButtonDisabled) return;
			this.resetRunning = true;
			try {
				// Recheck immediately before confirmation; the server checks again on reset.
				const preview = await this.fetchResetPreview();
				if (!preview || this.buildResetBlockerMessage(preview)) return;

				const confirmed = await this.openConfirm({
					title: this.$texts.settings.resetTitle(),
					message: this.buildResetPreviewMessage(preview),
					confirmLabel: this.$texts.settings.resetConfirmButton(),
					confirmVariant: 'danger',
					requiredText: 'RESET',
					kind: 'reset',
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
				const blockedPreview = e?.response?.data?.reset;
				if (this.buildResetBlockerMessage(blockedPreview)) {
					this.resetPreview = blockedPreview;
					this.resetPreviewError = '';
				} else {
					this.resetPreviewError = this.extractError(e, this.$texts.settings.resetError());
				}
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
			this.settingsLoadError = '';
			try {
				const settingsRes = await axios.get(generateUrl('/apps/cobudget/api/settings'), { skipWorkspaceHeader: true });
				this.currency = settingsRes.data.currency || '';
				this.savedCurrency = this.currency;
				this.enableSubscriptions = settingsRes.data.enable_subscriptions ?? true;
				this.enableFixedCosts = settingsRes.data.enable_fixed_costs ?? true;
				this.enableChildRelated = settingsRes.data.enable_child_related ?? true;
				this.enableImportantPayments = settingsRes.data.enable_important_payments ?? true;
				this.enableReviewPayments = settingsRes.data.enable_review_payments ?? true;
				this.enableTaxRelevant = settingsRes.data.enable_tax_relevant ?? true;
				this.enableInsurance = settingsRes.data.enable_insurance ?? true;
				this.enableFuturePayments = settingsRes.data.enable_future_payments ?? true;
				this.enableReminders = settingsRes.data.enable_reminders ?? true;
				this.enableBudgetGoals = settingsRes.data.enable_budget_goals ?? true;
				this.enableAdvancedMasterData = settingsRes.data.enable_advanced_master_data ?? false;
				this.enableIncomes = settingsRes.data.enable_incomes ?? true;
				this.enableProjects = settingsRes.data.enable_projects ?? true;
				this.enableSharedProjects = settingsRes.data.enable_shared_projects ?? true;
				this.notifyProjectEntries = settingsRes.data.notify_project_entries ?? true;
				this.emailAddressAvailable = settingsRes.data.email_address_available ?? false;
				this.notifyRemindersEmail = settingsRes.data.notify_reminders_email ?? false;
				this.notifyPlannedPaymentsEmail = settingsRes.data.notify_planned_payments_email ?? false;
				this.notifyProjectEntriesEmail = settingsRes.data.notify_project_entries_email ?? false;
				this.notifyProjectSettlementsEmail = settingsRes.data.notify_project_settlements_email ?? false;
				this.notifyReminders = settingsRes.data.notify_reminders ?? true;
				this.notifyPlannedPayments = settingsRes.data.notify_planned_payments ?? false;
				this.notifyProjectSettlements = settingsRes.data.notify_project_settlements ?? true;
				this.enableWorkspaces = settingsRes.data.enable_workspaces ?? false;
				this.showWorkspaceSwitcher = settingsRes.data.show_workspace_switcher ?? true;
				this.enableReceipts = settingsRes.data.enable_receipts ?? true;
				this.defaultStartPage = settingsRes.data.default_start_page || 'personal';
				this.entryPageSize = normalizeEntryPageSize(settingsRes.data.entries_per_page);
				this.themeMode = normalizeThemeMode(settingsRes.data.theme_mode);
				applyThemeMode(this.themeMode);
				this.showNextcloudHeaderDesktop = settingsRes.data.show_nextcloud_header_desktop ?? true;
				this.showNextcloudHeaderMobile = settingsRes.data.show_nextcloud_header_mobile ?? true;
				const headerVisibility = this.applyLocalNextcloudHeaderVisibility();
				this.$showNextcloudHeaderDesktop = headerVisibility.showDesktop;
				this.$showNextcloudHeaderMobile = headerVisibility.showMobile;
				this.receiptStorageFolder = settingsRes.data.receipt_storage_folder || 'CoBudget/Belege';
				this.savedReceiptStorageFolder = this.receiptStorageFolder;
				this.receiptFolderGrouping = settingsRes.data.receipt_folder_grouping || 'year';
				this.deleteReceiptsWithEntry = settingsRes.data.delete_receipts_with_entry ?? false;
				this.backupStorageFolder = this.normalizeBackupStorageFolder(settingsRes.data.backup_storage_folder);
				this.backupRetentionCount = Number(settingsRes.data.backup_retention_count || 7);
				this.backupSchedule = settingsRes.data.backup_schedule || 'none';
				this.savedBackupSettings = { ...this.backupSettingsPayload };
				this.savedGeneralSettings = this.generalSettingsSnapshot();
				await this.fetchBackups();
				await this.ensureValidWorkspaceContext();
				{
					const projectRes = await this.fetchWorkspaceScopedWithRetry(generateUrl('/apps/cobudget/api/projects'));
					this.projects = Array.isArray(projectRes.data) ? projectRes.data : [];
				}
				this.normalizeDefaultStartPageForProjects();

			} catch (e) {
				console.error('Failed to fetch personal data', e);
				this.masterDataLoaded = false;
				this.settingsLoadError = this.extractError(e, this.$texts.settings.loadError());
			} finally {
				this.loading = false;
				this.restoreWorkspacePosition();
			}
		},
		applyLocalThemeMode() {
			this.themeMode = applyThemeMode(this.themeMode);
		},
		applyLocalNextcloudHeaderVisibility() {
			const visibility = applyNextcloudHeaderVisibility({
				showDesktop: this.showNextcloudHeaderDesktop,
				showMobile: this.showNextcloudHeaderMobile,
			});
			this.showNextcloudHeaderDesktop = visibility.showDesktop;
			this.showNextcloudHeaderMobile = visibility.showMobile;
			return visibility;
		},
		async saveNextcloudHeaderSettings() {
			const previousVisibility = {
				showDesktop: this.$showNextcloudHeaderDesktop ?? true,
				showMobile: this.$showNextcloudHeaderMobile ?? true,
			};
			const visibility = this.applyLocalNextcloudHeaderVisibility();
			const saved = await this.saveGeneralSettings({ reload: false });

			if (saved) {
				this.$showNextcloudHeaderDesktop = visibility.showDesktop;
				this.$showNextcloudHeaderMobile = visibility.showMobile;
				this.showSuccessMessage(this.$texts.settings.generalSaved());
				return;
			}

			this.showNextcloudHeaderDesktop = previousVisibility.showDesktop;
			this.showNextcloudHeaderMobile = previousVisibility.showMobile;
			this.applyLocalNextcloudHeaderVisibility();
		},
		generalSettingsSnapshot() {
			return {
				...Object.fromEntries(Object.keys(generalSettingsFields).map(field => [field, this[field]])),
				entryPageSize: normalizeEntryPageSize(this.entryPageSize),
				themeMode: normalizeThemeMode(this.themeMode),
				receiptStorageFolder: this.receiptStorageFolder.trim() || 'CoBudget/Belege',
			};
		},
		async saveGeneralSettings(options = {}) {
			if (this.loading || this.backupSettingsSaving || !this.savedGeneralSettings || this.settingsLoadError) return false;
			this.loading = true;
			this.settingsActionError = '';
			const previous = { ...this.savedGeneralSettings };
			let saved;
			try {
				this.normalizeDefaultStartPageForProjects();
				saved = this.generalSettingsSnapshot();
				const changed = Object.keys(generalSettingsFields).filter(field => saved[field] !== previous[field]);
				if (!changed.length) return true;
				// Currency is required by this API, even for a partial preference update.
				const payload = { currency: saved.currency };
				for (const field of changed) payload[generalSettingsFields[field]] = saved[field];
				const response = await axios.post(generateUrl('/apps/cobudget/api/settings'), payload, { skipWorkspaceHeader: true });
				if (typeof response.data?.currency === 'string') saved.currency = response.data.currency;
			} catch (e) {
				Object.assign(this, previous);
				this.applyLocalThemeMode();
				this.applyLocalNextcloudHeaderVisibility();
				this.showError(this.extractError(e, this.$texts.settings.generalSaveError()));
				return false;
			} finally {
				this.loading = false;
			}
			Object.assign(this, saved);
			this.savedGeneralSettings = saved;
			this.savedCurrency = saved.currency;
			this.savedReceiptStorageFolder = saved.receiptStorageFolder;
			applySavedSettings(saved);
			// Changing workspace mode changes the data context. Reinitialize it before
			// loading scoped lists, and retain the user's position in the settings.
			if (saved.enableWorkspaces !== previous.enableWorkspaces) {
				try { sessionStorage.setItem('cobudget-settings-return-to-workspaces', window.location.href); } catch { /* Saving must not depend on session storage. */ }
				window.location.reload();
			} else {
				window.dispatchEvent(new CustomEvent('cobudget-settings-changed'));
			}
			return true;
		}
	}
}
</script>

<style scoped>
.settings-loading { display: flex; align-items: center; gap: var(--cobudget-space-2, 8px); color: var(--color-text-maxcontrast); }
.settings-section {
	display: block;
	margin: var(--cobudget-form-inset);
	margin-top: 0;
	width: min(var(--cobudget-form-max-width), calc(100% - var(--cobudget-form-inset) * 2));
	box-sizing: border-box;
}
.settings-basic-fields { display: grid; gap: var(--cobudget-section-gap, 24px); }
.settings-basic-fields .setting-item { min-width: 0; }
.settings-basic-fields :deep(.v-select.select) { width: 100%; min-width: 0; margin: 0; }
.settings-general {
	margin-bottom: 0;
}
.settings-master-data { margin-block: 0; }
.settings-master-data :deep(.master-section) {
	padding-block-start: var(--cobudget-settings-heading-gap);
	scroll-margin-block-start: calc(var(--cobudget-mobile-touch-size, 44px) + var(--default-grid-baseline, 4px) * 4);
}
.settings-master-data :deep(.master-section + .master-section) { margin-block-start: 0; }
.settings-master-data :deep(.master-section > h3),
.settings-subsection:not(.reset-section) > h3 { margin-block-start: 0; }
.settings-subsection:not(.reset-section) {
	margin-block-start: 0;
	padding-block-start: var(--cobudget-settings-heading-gap);
}
.settings-subsection {
	margin-top: var(--cobudget-settings-section-gap);
}
.settings-workspace-options {
	scroll-margin-block-start: calc(var(--cobudget-mobile-touch-size, 44px) + var(--default-grid-baseline, 4px) * 4);
}
.workspace-section-header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--cobudget-content-gap);
	margin-bottom: var(--cobudget-section-gap);
}
.workspace-section-header h3 { margin: 0; }
.workspace-add-button { flex-shrink: 0; margin-inline-start: auto; }
.settings-hint {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	margin-top: 0;
	margin-bottom: 24px;
}
.settings-grid {
	display: flex;
	gap: var(--cobudget-settings-section-gap);
}
.settings-column {
	flex: 1;
	min-width: 0;
}


.settings-mini-section {
	margin-top: 0;
	padding-top: var(--cobudget-settings-heading-gap);
}

.settings-column > .settings-mini-section:first-child {
	margin-top: 0;
	padding-top: 0;
	border-top: 0;
}

.settings-receipt-delete { margin-top: 16px; }

.settings-mini-section h3 {
	font-size: var(--cobudget-font-section-title);
	font-weight: var(--cobudget-font-weight-heading);
	margin: 0 0 14px 0;
}

.backup-settings-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: var(--cobudget-section-gap);
	align-items: start;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.backup-settings-grid label,
.backup-list-header h4,
.backup-info span {
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.backup-settings-grid .setting-item { min-width: 0; }
.backup-schedule-setting,
.backup-folder-setting { grid-column: 1 / -1; }
.backup-schedule-options {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: var(--default-grid-baseline, 4px);
	width: 100%;
}

.backup-manual-actions {
	display: flex;
	justify-content: flex-end;
	align-self: end;
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
	font-size: var(--cobudget-font-subheading);
	font-weight: var(--cobudget-font-weight-heading);
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
	background: var(--cobudget-interaction-hover);
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
	font-weight: var(--cobudget-font-weight-heading);
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
	font-size: var(--cobudget-font-subheading);
	font-weight: var(--cobudget-font-weight-heading);
	color: var(--color-main-text);
	margin: 0;
}

.backup-info .backup-date {
	font-size: var(--cobudget-font-label);
	font-weight: var(--font-weight-element, 500);
	color: var(--color-main-text);
	margin: 0;
}

.compact-hint {
	margin-bottom: 0;
}

.backup-info {
	flex: 1;
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

.backup-items :deep(.backup-item-actions) {
	align-items: center;
	justify-content: flex-end;
	gap: var(--default-grid-baseline, 4px);
	flex-wrap: wrap;
}

.backup-restore-action {
	display: inline-flex;
}

.reset-section {
	padding-top: 22px;
}

.reset-section h3 {
	color: var(--cobudget-error);
}

.settings-native-options {
	display: grid;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}

.settings-talk-status {
	display: grid;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	justify-items: start;
}

.setting-toggle-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}

.setting-desc {
	font-size: var(--font-size, 15px);
	color: var(--color-text-maxcontrast);
	margin-top: 2px;
	margin-bottom: 8px;
}

.settings-modal input,
.settings-modal select,
.settings-modal textarea {
	background-color: var(--cobudget-surface, var(--color-main-background, #fff)) !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

.settings-modal input::placeholder,
.settings-modal textarea::placeholder {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888)) !important;
	opacity: 1;
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
	background: var(--cobudget-dialog-backdrop);
	backdrop-filter: blur(2px);
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
	border-radius: var(--cobudget-dialog-radius);
	box-shadow: var(--cobudget-dialog-shadow);
	color: var(--cobudget-text, var(--color-main-text, #222));
	max-width: 520px;
	padding: var(--cobudget-dialog-padding);
	width: 90%;
}

.settings-modal.settings-modal--payment-partner {
	box-sizing: border-box;
	max-height: calc(100vh - 32px);
	max-width: 760px;
	overflow-y: auto;
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
	margin-bottom: 0;
}

.category-parent-field .select-control {
	margin: 0;
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

	.backup-schedule-options { grid-template-columns: repeat(2, minmax(0, 1fr)); }

	.backup-settings-grid {
		grid-template-columns: 1fr;
		flex-direction: column;
		align-items: stretch;
	}


	.restore-report-summary,
	.restore-report-grid {
		grid-template-columns: 1fr;
	}


	.settings-modal {
		padding: 20px;
		width: calc(100% - 32px);
	}

	.rename-fields.has-category-number {
		grid-template-columns: minmax(0, 1fr);
	}
}

/* A compact channel matrix keeps event names visible at narrow widths. */
.settings-notification-table {
	width: 100%;
	table-layout: fixed;
	border-collapse: collapse;
}
.settings-notification-table th,
.settings-notification-table td {
	padding: 4px 0;
	border-bottom: 1px solid var(--color-border);
	vertical-align: middle;
	font-weight: normal;
}
.settings-notification-table th { text-align: start; overflow-wrap: anywhere; white-space: normal; }
.settings-notification-table thead th { color: var(--color-text-maxcontrast); }
.settings-notification-table th:not(:first-child),
.settings-notification-table td { width: 76px; text-align: center; }
.settings-notification-table td :deep(.checkbox-radio-switch) { width: 44px; min-width: 44px; min-height: 44px; margin-inline: auto; justify-content: center; }


.settings-calendar-target { display: grid; gap: 8px; }
.settings-calendar-target :deep(.v-select.select) { width: 100%; min-width: 0; margin: 0; }

@media (max-width: 480px) {
	.settings-notification-table th.notification-channel-nextcloud,
	.settings-notification-table td.notification-channel-nextcloud { width: 80px; }
	.settings-notification-table th.notification-channel-email,
	.settings-notification-table td.notification-channel-email,
	.settings-notification-table th.notification-channel-talk,
	.settings-notification-table td.notification-channel-talk { width: 44px; }
	.settings-notification-table th.notification-channel-calendar,
	.settings-notification-table td.notification-channel-calendar { width: 62px; }
}
</style>
