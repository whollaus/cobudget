<template>
	<div class="cobudget-admin-settings settings-section">
		<h2 class="cobudget-section-heading">{{ $texts.admin.title() }}</h2>
		<p class="settings-hint">{{ $texts.admin.hint() }}</p>

		<NcNoteCard v-if="loadError" type="error" :text="loadError" />
		<MasterDataSettings ref="masterData" admin class="admin-master-data" />

		<section id="cobudget-api-settings" class="integrity-card admin-api-card">
			<h3 class="cobudget-section-heading">{{ $texts.admin.apiAccessTitle() }}</h3>
			<p class="admin-section-hint">{{ $texts.admin.apiAccessHint() }}</p>
			<NcNoteCard v-if="apiSettingsLoadError" type="error">
				<p>{{ apiSettingsLoadError }}</p>
				<NcButton variant="tertiary" :disabled="apiSettingsLoading" @click="fetchApiSettings">{{ $texts.common.retry() }}</NcButton>
			</NcNoteCard>
			<NcNoteCard v-if="apiSettingsSaveError" type="error" :text="apiSettingsSaveError" />
			<p v-if="apiSettingsLoading" class="integrity-status settings-loading" role="status">
				<NcLoadingIcon :size="20" />
				{{ $texts.common.loading() }}
			</p>
			<NcFormBoxSwitch
				v-else
				:model-value="apiAccessEnabled"
				:label="$texts.admin.apiAccessEnableLabel()"
				:description="$texts.admin.apiAccessEnableDescription()"
				:disabled="apiSettingsLoading || apiSettingsSaving || !!apiSettingsLoadError"
				@update:model-value="saveApiAccessEnabled" />
		</section>

		<section id="cobudget-api-request-limit" class="integrity-card">
			<h3 class="cobudget-section-heading">{{ $texts.admin.apiRequestSize() }}</h3>
			<p class="admin-section-hint">{{ $texts.admin.apiRequestSizeHint() }}</p>
			<div class="admin-api-limit-actions">
				<NcFormBoxButton :href="overviewUrl" :label="$texts.admin.openOverview()" />
				<NcFormBoxButton :label="$texts.admin.webServerGuide()" @click="openApiLimitGuide">
					<template #icon><FileDocumentOutlineIcon :size="24" /></template>
				</NcFormBoxButton>
			</div>
		</section>

		<div class="integrity-card">
			<h3 class="cobudget-section-heading">{{ $texts.admin.dataQuality() }}</h3>
			<div class="integrity-card-header">
				<div>
					<p>{{ $texts.admin.dataQualityHint() }}</p>
				</div>
				<div class="integrity-actions">
					<NcButton variant="secondary" :disabled="integrityLoading || integrityRepairing" @click="fetchIntegrityReport">
						{{ $texts.admin.checkNow() }}
					</NcButton>
					<NcButton
						v-if="integrityOrphanCount > 0"
						variant="primary"
						:disabled="integrityLoading || integrityRepairing"
						@click="repairIntegrityReferences">
						{{ $texts.admin.repairOrphans() }}
					</NcButton>
				</div>
			</div>

			<NcNoteCard v-if="integrityError" type="error" :text="integrityError" />
			<p v-if="integrityLoading" class="integrity-status settings-loading" role="status"><NcLoadingIcon :size="20" />
				{{ $texts.admin.checkingDataQuality() }}
			</p>
			<p v-else-if="!integrityReport" class="integrity-status">
				{{ $texts.admin.noCheckLoaded() }}
			</p>
			<NcNoteCard v-else-if="integrityIsClean && !integrityError" type="success" :text="$texts.admin.noDataQualityIssues()" />
			<div v-else class="integrity-results">
				<div v-if="integrityOrphanCount > 0" class="integrity-group">
					<h4 class="cobudget-subheading">{{ $texts.admin.orphanReferences(integrityOrphanCount) }}</h4>
					<p>{{ $texts.admin.orphanReferencesHint() }}</p>
					<ul class="integrity-list">
						<li v-for="issue in integrityOrphanReferences" :key="`${issue.sourceTable}-${issue.column}`">
							<strong>{{ issue.sourceLabel }}: {{ issue.targetLabel }}</strong>
							<span>{{ issue.column }} · IDs: {{ formatIntegrityIds(issue.ids) }}</span>
						</li>
					</ul>
				</div>

				<div v-if="integrityProjectionCount > 0" class="integrity-group">
					<h4 class="cobudget-subheading">{{ $texts.admin.projectionIssues(integrityProjectionCount) }}</h4>
					<p>{{ $texts.admin.projectionIssuesHint() }}</p>
					<ul class="integrity-list">
						<li v-for="issue in integrityProjectionIssues" :key="`${issue.code}-${issue.table}-${issue.id}`">
							<strong>{{ issue.code }}</strong>
							<span>{{ $texts.admin.projectionIssueLocation(issue.table, issue.id) }}</span>
						</li>
					</ul>
				</div>

				<div v-if="integrityDuplicateCount > 0" class="integrity-group">
					<h4 class="cobudget-subheading">{{ $texts.admin.visibleDuplicates(integrityDuplicateCount) }}</h4>
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
								<NcButton variant="tertiary"
									v-for="id in integrityIds(issue)"
									:key="id"
									type="button"
									class="integrity-merge-button"
									:disabled="integrityMergingKey === integrityMergeKey(issue, id)"
									@click="mergeIntegrityDuplicate(issue, id)">
									{{ $texts.admin.keepId(id) }}
								</NcButton>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="admin-backup-card">
			<div class="integrity-card-header">
				<div>
					<h3 class="cobudget-section-heading">{{ $texts.admin.fullBackups() }}</h3>
					<p>{{ $texts.admin.fullBackupsHint() }}</p>
				</div>
			</div>

			<NcNoteCard v-if="fullBackupError" type="error" :text="fullBackupError" />
			<NcNoteCard v-if="fullBackupSettingsError" type="error"><p>{{ fullBackupSettingsError }}</p><NcButton variant="tertiary" :disabled="fullBackupBusy || folderPickerOpen || !!fullBackupValidationError" @click="saveFullBackupSettings">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
			<NcNoteCard v-if="fullBackupValidationError && fullBackupRetentionValid && !fullBackupSettingsLoading && !fullBackupLoadError" type="warning" :text="fullBackupValidationError" />
			<NcNoteCard v-if="fullBackupLoadError" type="error"><p>{{ fullBackupLoadError }}</p><NcButton variant="tertiary" :disabled="fullBackupSettingsLoading" @click="fetchFullBackupSettings">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
			<p v-if="fullBackupSettingsLoading" class="integrity-status settings-loading" role="status"><NcLoadingIcon :size="20" />
				{{ $texts.admin.fullBackupSettingsLoading() }}
			</p>
			<form v-else class="admin-backup-settings-grid" :aria-busy="fullBackupBusy" @submit.prevent="saveFullBackupSettings">
				<NcRadioGroup :model-value="fullBackupSchedule" @update:model-value="setFullBackupSchedule" class="cobudget-form-group admin-backup-schedule" :label="$texts.admin.fullBackupSchedule()" :description="$texts.admin.fullBackupScheduleHint()">
					<NcRadioGroupButton value="none" :label="$texts.admin.fullBackupScheduleNone()" :disabled="fullBackupSettingsDisabled" />
					<NcRadioGroupButton value="daily" :label="$texts.admin.fullBackupScheduleDaily()" :disabled="fullBackupSettingsDisabled" />
					<NcRadioGroupButton value="weekly" :label="$texts.admin.fullBackupScheduleWeekly()" :disabled="fullBackupSettingsDisabled" />
					<NcRadioGroupButton value="monthly" :label="$texts.admin.fullBackupScheduleMonthly()" :disabled="fullBackupSettingsDisabled" />
				</NcRadioGroup>
				<NcFormGroup class="cobudget-form-group admin-backup-wide" :description="$texts.admin.fullBackupStorageUserHint()">
					<template #label><label class="cobudget-form-label" for="admin-backup-user">{{ $texts.admin.fullBackupStorageUser() }}</label></template>
					<NcSelect v-model="selectedStorageUser" input-id="admin-backup-user" class="cobudget-native-select" label-outside :input-label="$texts.admin.fullBackupStorageUser()" :options="storageUsers" label="displayName" :filterable="false" :loading="storageUsersLoading" :disabled="fullBackupSettingsDisabled || folderPickerOpen" :placeholder="$texts.areas.searchUsers()" @search="searchStorageUsers">
						<template #option="user"><MemberIdentity :user-id="user.id" :display-name="user.displayName" :size="32" /></template>
						<template #selected-option="user"><MemberIdentity :user-id="user.id" :display-name="user.displayName" /></template>
						<template #no-options>{{ $texts.memberSearch.noResults() }}</template>
					</NcSelect>
					<NcNoteCard v-if="storageUsersError" type="error"><p>{{ storageUsersError }}</p><NcButton variant="tertiary" :disabled="storageUsersLoading" @click="fetchStorageUsers">{{ $texts.common.retry() }}</NcButton></NcNoteCard>
				</NcFormGroup>
				<NcFormGroup class="cobudget-form-group admin-backup-wide" :description="fullBackupStorageUser && !canBrowseBackupFolder ? $texts.admin.fullBackupOtherUserFolderHint() : ''">
					<template #label><label class="cobudget-form-label" for="admin-backup-folder">{{ $texts.admin.fullBackupFolder() }}</label></template>
					<NcFormBoxButton v-if="canBrowseBackupFolder || !fullBackupStorageUser" id="admin-backup-folder" :label="'/' + fullBackupStorageFolder.replace(/^\/+/, '')" :disabled="fullBackupSettingsDisabled || folderPickerOpen || !canBrowseBackupFolder" @click="chooseFullBackupFolder"><template #icon><FolderOpenOutlineIcon :size="24" /></template></NcFormBoxButton>
					<NcInputField v-else id="admin-backup-folder" v-model="fullBackupStorageFolder" label-outside :label="$texts.admin.fullBackupFolder()" class="cobudget-native-field" :disabled="fullBackupSettingsDisabled" @blur="saveFullBackupSettings" @keydown.enter.prevent="$event.target.blur()" />
				</NcFormGroup>
				<NcFormGroup class="cobudget-form-group" :description="$texts.admin.fullBackupRetentionHint()">
					<template #label><label class="cobudget-form-label" for="admin-backup-retention">{{ $texts.admin.fullBackupRetention() }}</label></template>
					<NcInputField id="admin-backup-retention" v-model.number="fullBackupRetentionCount" label-outside :label="$texts.admin.fullBackupRetention()" class="cobudget-native-field" :disabled="fullBackupSettingsDisabled" type="number" min="1" max="100" step="1" required :error="!fullBackupRetentionValid" :helper-text="!fullBackupRetentionValid ? $texts.settings.invalidRetentionCount() : ''" @blur="saveFullBackupSettings" @keydown.enter.prevent="$event.target.blur()" />
				</NcFormGroup>
				<div class="admin-backup-create-action">
					<DisabledActionHint :reason="fullBackupCreateHint" :label="$texts.admin.fullBackupCreateNow()"><NcButton variant="secondary" :disabled="!!fullBackupCreateHint" @click="createFullBackupNow"><template #icon><PlusIcon :size="20" /></template>{{ fullBackupCreating ? $texts.admin.fullBackupCreating() : $texts.admin.fullBackupCreateNow() }}</NcButton></DisabledActionHint>
				</div>
			</form>
			<div v-if="!fullBackupSettingsLoading" class="admin-backup-list">
				<div class="admin-backup-list-header">
					<div>
						<h4 class="cobudget-subheading">{{ $texts.admin.fullBackupLastBackups() }}</h4>
						<p>{{ $texts.admin.fullBackupHistoryHint() }}</p>
					</div>
				</div>
				<p v-if="fullBackups.length === 0" class="integrity-status">
					{{ $texts.admin.fullBackupNoBackups() }}
				</p>
				<ul v-else class="admin-backup-items">
					<li
						v-for="backup in fullBackups"
						:key="backup.file_name"
						class="admin-backup-item">
						<div class="admin-backup-info">
							<strong>{{ formatBackupDate(backup.created_at) }}</strong>
							<small>{{ backup.file_path }} · {{ formatBackupSize(backup.file_size) }}</small>
							<details class="admin-backup-occ">
								<summary>{{ $texts.admin.fullBackupRestoreOccCommand() }}</summary>
								<p class="cobudget-form-hint">{{ $texts.admin.fullBackupRestoreOccHint() }}</p>
								<code>{{ fullBackupOccCommand(backup) }}</code>
							</details>
						</div>
						<div class="admin-backup-item-actions">
							<DisabledActionHint :reason="fullBackupRestoringFileName ? $texts.admin.fullBackupRestoring() : fullBackupSettingsSaving ? $texts.common.saveBusy() : ''" :label="$texts.admin.fullBackupRestore()"><NcButton
								variant="tertiary"
								:disabled="!!fullBackupRestoringFileName || fullBackupSettingsSaving"
								@click="openFullBackupRestore(backup)">
								{{ $texts.admin.fullBackupRestore() }}
							</NcButton></DisabledActionHint>

							<NcButton
								variant="tertiary"
								class="admin-backup-icon-button"
								:aria-label="$texts.admin.fullBackupDownloadAria(backup.file_name)"
								:title="$texts.admin.fullBackupDownloadAria(backup.file_name)"
								:disabled="!!fullBackupRestoringFileName"
								@click="downloadFullBackup(backup)">
								<template #icon>
									<DownloadIcon :size="20" />
								</template>
							</NcButton>

							<NcButton
								variant="tertiary"
								class="admin-backup-icon-button"
								:aria-label="$texts.admin.fullBackupDeleteAria(backup.file_name)"
								:title="$texts.admin.fullBackupDeleteAria(backup.file_name)"
								:disabled="fullBackupDeletingFileName === backup.file_name || !!fullBackupRestoringFileName || fullBackupSettingsSaving"
								@click="deleteFullBackup(backup)">
								<template #icon>
									<DeleteOutlineIcon :size="20" />
								</template>
							</NcButton>
						</div>
					</li>
				</ul>
			</div>
		</div>

		<DialogTransition :show="!!(fullBackupRestoreCandidate)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="$texts.admin.fullBackupRestoreTitle()" :no-close="!!fullBackupRestoringFileName" :close-on-click-outside="!fullBackupRestoringFileName" is-form @submit="confirmFullBackupRestore" @closing="closeFullBackupRestoreModal">
				<p class="cobudget-confirm-message">{{ $texts.admin.fullBackupRestoreMessage(fullBackupRestoreCandidate.file_name) }}</p>
				<NcFormGroup class="cobudget-form-group">
					<template #description><span id="admin-full-backup-restore-hint">{{ $texts.admin.fullBackupRestoreConfirmHint() }}</span></template>
					<template #label><label class="cobudget-form-label" for="admin-full-backup-restore-confirm">{{ $texts.admin.fullBackupRestoreConfirmation() }}</label></template>
					<NcInputField id="admin-full-backup-restore-confirm" aria-describedby="admin-full-backup-restore-hint" v-model="fullBackupRestoreConfirmation" label-outside :label="$texts.admin.fullBackupRestoreConfirmation()" class="cobudget-native-field" autocomplete="off" autocapitalize="off" :spellcheck="false" :disabled="!!fullBackupRestoringFileName" />
				</NcFormGroup>
				<NcNoteCard v-if="fullBackupRestoreError" type="error" :text="fullBackupRestoreError" />
				<template #actions>
					<div class="cobudget-confirm-actions">
						<NcButton variant="tertiary" :disabled="!!fullBackupRestoringFileName" @click="closeFullBackupRestoreModal">{{ $texts.common.cancel() }}</NcButton>
						<DisabledActionHint :reason="fullBackupRestoreHint" :label="$texts.admin.fullBackupRestore()"><NcButton variant="error" type="submit" :disabled="!canRestoreFullBackup">{{ fullBackupRestoringFileName ? $texts.admin.fullBackupRestoring() : $texts.admin.fullBackupRestore() }}</NcButton></DisabledActionHint>
					</div>
				</template>
			</NcDialog>
		</DialogTransition>
		<DialogTransition :show="!!(confirmDialog)" v-slot="{ returnFocus }">
			<NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="confirmDialog.title" close-on-click-outside @closing="resolveConfirm(false)">
				<p class="cobudget-confirm-message">{{ confirmDialog.message }}</p>
				<template #actions>
					<div class="cobudget-confirm-actions">
						<NcButton variant="tertiary" @click="resolveConfirm(false)">{{ $texts.common.cancel() }}</NcButton>
						<NcButton :variant="confirmDialog.confirmVariant === 'danger' ? 'error' : 'primary'" @click="resolveConfirm(true)">{{ confirmDialog.confirmLabel }}</NcButton>
					</div>
				</template>
			</NcDialog>
		</DialogTransition>
		<DialogTransition :show="passwordConfirm.open" v-slot="{ returnFocus }">
			<NcDialog
				:container="null"
				:set-return-focus="returnFocus"
				class="cobudget-native-dialog cobudget-standard-dialog admin-api-password-dialog"
				size="normal"
				:name="$texts.apiTokens.passwordConfirmTitle()"
				:no-close="passwordConfirm.busy"
				:close-on-click-outside="!passwordConfirm.busy"
				@closing="cancelPasswordConfirmation">
				<form class="admin-api-password-body" @submit.prevent="submitPasswordConfirmation">
					<p class="cobudget-confirm-message">{{ $texts.admin.apiAccessPasswordConfirmHint() }}</p>
					<NcNoteCard v-if="passwordConfirm.error" type="error" :text="passwordConfirm.error" />
					<NcFormGroup class="cobudget-form-group">
						<template #label>
							<label class="cobudget-form-label" for="admin-api-confirm-password">{{ $texts.apiTokens.passwordConfirmLabel() }}</label>
						</template>
						<NcInputField
							id="admin-api-confirm-password"
							v-model="passwordConfirm.password"
							type="password"
							autocomplete="current-password"
							label-outside
							:label="$texts.apiTokens.passwordConfirmLabel()"
							required
							:disabled="passwordConfirm.busy" />
					</NcFormGroup>
				</form>
				<template #actions>
					<div class="cobudget-confirm-actions">
						<NcButton
							variant="tertiary"
							:disabled="passwordConfirm.busy"
							@click="cancelPasswordConfirmation">
							{{ $texts.common.cancel() }}
						</NcButton>
						<NcButton
							variant="primary"
							:disabled="passwordConfirm.busy || !passwordConfirm.password"
							@click="submitPasswordConfirmation">
							{{ $texts.apiTokens.passwordConfirmButton() }}
						</NcButton>
					</div>
				</template>
			</NcDialog>
		</DialogTransition>
		<DialogTransition :show="apiLimitGuideOpen" v-slot="{ returnFocus }">
			<NcModal
				:container="null"
				:set-return-focus="returnFocus"
				class="cobudget-native-dialog cobudget-settings-modal admin-api-guide-modal"
				:show="true"
				label-id="admin-api-guide-title"
				size="large"
				:close-on-click-outside="true"
				@update:show="closeApiLimitGuide"
				@keydown.esc.stop.prevent="closeApiLimitGuide">
				<div class="admin-api-guide-dialog">
					<h2 id="admin-api-guide-title">{{ $texts.admin.webServerGuideTitle() }}</h2>
					<div class="admin-api-guide-body">
						<template v-for="(block, blockIndex) in apiLimitGuideBlocks" :key="`guide-${blockIndex}`">
							<h3 v-if="block.type === 'heading' && block.level <= 2" class="cobudget-section-heading admin-api-guide-heading">
								<template v-for="(segment, segIndex) in block.segments" :key="`seg-${segIndex}`">
									<code v-if="segment.type === 'code'">{{ segment.text }}</code>
									<strong v-else-if="segment.type === 'strong'">{{ segment.text }}</strong>
									<a v-else-if="segment.type === 'link'" :href="segment.href" target="_blank" rel="noopener noreferrer">{{ segment.text }}</a>
									<template v-else>{{ segment.text }}</template>
								</template>
							</h3>
							<h4 v-else-if="block.type === 'heading'" class="cobudget-subheading admin-api-guide-heading">
								<template v-for="(segment, segIndex) in block.segments" :key="`seg-${segIndex}`">
									<code v-if="segment.type === 'code'">{{ segment.text }}</code>
									<strong v-else-if="segment.type === 'strong'">{{ segment.text }}</strong>
									<a v-else-if="segment.type === 'link'" :href="segment.href" target="_blank" rel="noopener noreferrer">{{ segment.text }}</a>
									<template v-else>{{ segment.text }}</template>
								</template>
							</h4>
							<p v-else-if="block.type === 'paragraph'" class="admin-api-guide-paragraph">
								<template v-for="(segment, segIndex) in block.segments" :key="`seg-${segIndex}`">
									<code v-if="segment.type === 'code'">{{ segment.text }}</code>
									<strong v-else-if="segment.type === 'strong'">{{ segment.text }}</strong>
									<a v-else-if="segment.type === 'link'" :href="segment.href" target="_blank" rel="noopener noreferrer">{{ segment.text }}</a>
									<template v-else>{{ segment.text }}</template>
								</template>
							</p>
							<div v-else-if="block.type === 'code'" class="admin-api-guide-code-card">
								<div class="admin-api-guide-code-toolbar">
									<span class="admin-api-guide-code-lang">{{ block.lang || 'config' }}</span>
									<NcButton
										variant="tertiary"
										:title="$texts.admin.copyConfigSnippet()"
										:aria-label="$texts.admin.copyConfigSnippet()"
										@click="copyGuideSnippet(block.code)">
										<template #icon><ContentCopyIcon :size="16" /></template>
										{{ $texts.common.copy() }}
									</NcButton>
								</div>
								<pre class="admin-api-guide-code"><code>{{ block.code }}</code></pre>
							</div>
							<div v-else-if="block.type === 'table'" class="bulk-import-table-wrapper admin-api-guide-table-wrap">
								<table class="bulk-import-table admin-api-guide-table">
									<thead>
										<tr>
											<th v-for="(headerCell, cellIndex) in block.headers" :key="`th-${cellIndex}`" scope="col">
												<template v-for="(segment, segIndex) in headerCell" :key="`seg-${segIndex}`">
													<code v-if="segment.type === 'code'">{{ segment.text }}</code>
													<strong v-else-if="segment.type === 'strong'">{{ segment.text }}</strong>
													<a v-else-if="segment.type === 'link'" :href="segment.href" target="_blank" rel="noopener noreferrer">{{ segment.text }}</a>
													<template v-else>{{ segment.text }}</template>
												</template>
											</th>
										</tr>
									</thead>
									<tbody>
										<tr v-for="(row, rowIndex) in block.rows" :key="`tr-${rowIndex}`">
											<td
												v-for="(cell, cellIndex) in row"
												:key="`td-${cellIndex}`"
												:data-label="plainGuideText(block.headers[cellIndex])">
												<template v-for="(segment, segIndex) in cell" :key="`seg-${segIndex}`">
													<code v-if="segment.type === 'code'">{{ segment.text }}</code>
													<strong v-else-if="segment.type === 'strong'">{{ segment.text }}</strong>
													<a v-else-if="segment.type === 'link'" :href="segment.href" target="_blank" rel="noopener noreferrer">{{ segment.text }}</a>
													<template v-else>{{ segment.text }}</template>
												</template>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</template>
					</div>
					<div class="admin-api-guide-actions">
						<NcButton variant="tertiary" @click="closeApiLimitGuide">{{ $texts.common.close() }}</NcButton>
					</div>
				</div>
			</NcModal>
		</DialogTransition>
	</div>
</template>

<script>
import DialogTransition from './DialogTransition.vue'
import { getCurrentUser } from '@nextcloud/auth'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import pickFolder from '../services/folderPicker'
import { Permission } from '@nextcloud/files'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import MemberIdentity from './MemberIdentity.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FolderOpenOutlineIcon from 'vue-material-design-icons/FolderOpenOutline.vue'
import DisabledActionHint from './DisabledActionHint.vue'
import { getAppLocale } from '../utils/formatMoney'
import axios from '../services/http'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import apiLimitGuide from '../../docs/api-request-limits.md'
import parseMarkdownGuide from '../utils/markdownGuide'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/components/NcButton'
import MasterDataSettings from './MasterDataSettings.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import FileDocumentOutlineIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import { extractError, showToast } from '../services/notifications'

export default {
	name: 'AdminSettings',
	components: { DialogTransition, NcLoadingIcon, DisabledActionHint, NcSelect, NcFormBoxButton, MemberIdentity, PlusIcon, FolderOpenOutlineIcon,
		MasterDataSettings,
		NcDialog,
		NcInputField,
		NcFormBoxSwitch,
		NcFormGroup,
		NcModal,
		NcRadioGroup,
		NcRadioGroupButton,
		NcNoteCard,
		NcButton,
		ContentCopyIcon,
		DeleteOutlineIcon,
		DownloadIcon,
		FileDocumentOutlineIcon,
	},
	data() {
		return {
			apiLimitGuideOpen: false,
			overviewUrl: generateUrl('/settings/admin/overview'),
			loading: false,
			loadError: '',
			apiAccessEnabled: false,
			apiSettingsLoading: false,
			apiSettingsSaving: false,
			apiSettingsLoadError: '',
			apiSettingsSaveError: '',
			passwordConfirm: {
				open: false,
				password: '',
				busy: false,
				error: '',
			},
			passwordConfirmResolver: null,
			integrityError: '',
			fullBackupError: '',
			fullBackupLoadError: '',
			fullBackupRestoreError: '',
			integrityReport: null,
			integrityLoading: false,
			integrityRepairing: false,
			integrityMergingKey: '',
			fullBackupSettingsLoading: false,
			fullBackupSettingsSaving: false,
			fullBackupSettingsError: '',
			queuedFullBackupSettings: null,
			fullBackupCreating: false,
			fullBackupStorageUser: '',
			currentUserId: getCurrentUser()?.uid || '',
			storageUsers: [],
			storageUsersLoading: false,
			storageUsersError: '',
			storageUserSearch: '',
			storageUserSearchTimer: null,
			storageUserRequest: 0,
			folderPickerOpen: false,
			savedFullBackupSettings: null,
			fullBackupStorageFolder: 'CoBudget/Backups',
			fullBackupRetentionCount: 7,
			fullBackupSchedule: 'none',
			fullBackups: [],
			fullBackupRestoreCandidate: null,
			fullBackupRestoreConfirmation: '',
			fullBackupRestoringFileName: '',
			fullBackupDeletingFileName: '',
			confirmDialog: null
		}
	},
	computed: {
		apiLimitGuideBlocks() {
			return typeof parseMarkdownGuide === 'function' ? parseMarkdownGuide(apiLimitGuide) : [];
		},
		selectedStorageUser: {
			get() { return this.storageUsers.find(user => user.id === this.fullBackupStorageUser) || (this.fullBackupStorageUser ? { id: this.fullBackupStorageUser, displayName: this.fullBackupStorageUser } : null); },
			set(user) { this.fullBackupStorageUser = user?.id || ''; this.saveFullBackupSettings(); },
		},
		canBrowseBackupFolder() { return !!this.currentUserId && this.fullBackupStorageUser === this.currentUserId; },
		fullBackupSettingsDisabled() { return this.fullBackupSettingsLoading || !!this.fullBackupLoadError || this.fullBackupCreating || !!this.fullBackupRestoringFileName || !!this.fullBackupDeletingFileName || !!this.confirmDialog || !!this.fullBackupRestoreCandidate; },
		fullBackupRetentionValid() { const value = Number(this.fullBackupRetentionCount); return Number.isInteger(value) && value >= 1 && value <= 100; },
		fullBackupSettingsPayload() {
			return { storage_user_id: this.fullBackupStorageUser.trim(), storage_folder: this.fullBackupStorageFolder.trim().replace(/^\/+|\/+$/g, ''), retention_count: Number(this.fullBackupRetentionCount), schedule: this.fullBackupSchedule };
		},
		fullBackupValidationError() {
			const payload = this.fullBackupSettingsPayload;
			if (!payload.storage_user_id && payload.schedule !== 'none') return this.$texts.admin.fullBackupSelectUser();
			if (!payload.storage_folder || payload.storage_folder.length > 180 || /[\\\x00-\x1f]/.test(payload.storage_folder) || /(^|\/)\.\.(\/|$)/.test(payload.storage_folder)) return this.$texts.admin.fullBackupInvalidFolder();
			if (!this.fullBackupRetentionValid) return this.$texts.settings.invalidRetentionCount();
			return '';
		},
		fullBackupSettingsDirty() { return !this.savedFullBackupSettings || Object.entries(this.fullBackupSettingsPayload).some(([key, value]) => value !== this.savedFullBackupSettings[key]); },
		fullBackupCreateHint() {
			if (this.fullBackupLoadError) return this.fullBackupLoadError;
			if (this.fullBackupBusy || this.folderPickerOpen) return this.$texts.common.waitBusy();
			if (!this.fullBackupStorageUser.trim()) return this.$texts.admin.fullBackupSelectUser();
			if (!this.fullBackupStorageFolder.trim()) return this.$texts.admin.fullBackupSelectFolder();
			if (this.fullBackupValidationError) return this.fullBackupValidationError;
			if (this.fullBackupSettingsError) return this.fullBackupSettingsError;
			const saved = this.savedFullBackupSettings;
			if (this.fullBackupSettingsDirty) return this.$texts.admin.fullBackupUnsaved();
			if (!saved.storage_user_exists || !saved.storage_user_is_admin) return this.$texts.admin.fullBackupSelectUser();
			return '';
		},
		fullBackupBusy() { return this.fullBackupSettingsLoading || !!this.fullBackupLoadError || this.fullBackupSettingsSaving || this.fullBackupCreating || !!this.fullBackupRestoringFileName || !!this.fullBackupDeletingFileName; },
		integrityOrphanReferences() {
			return this.integrityReport?.orphanReferences || [];
		},
		integrityDuplicateVisibleNames() {
			return this.integrityReport?.duplicateVisibleNames || [];
		},
		integrityProjectionIssues() {
			return this.integrityReport?.projectionIssues || [];
		},
		integrityOrphanCount() {
			return Number(this.integrityReport?.orphanReferenceCount || 0);
		},
		integrityDuplicateCount() {
			return Number(this.integrityReport?.duplicateVisibleNameCount || 0);
		},
		integrityProjectionCount() {
			return Number(this.integrityReport?.projectionIssueCount || 0);
		},
		integrityIsClean() {
			return this.integrityReport
				&& this.integrityOrphanCount === 0
				&& this.integrityProjectionCount === 0
				&& this.integrityDuplicateCount === 0;
		},
		fullBackupRestoreHint() {
			if (this.fullBackupRestoringFileName) return this.$texts.admin.fullBackupRestoring();
			return this.canRestoreFullBackup ? '' : this.$texts.admin.fullBackupRestoreConfirmHint();
		},
		canRestoreFullBackup() {
			return !!this.fullBackupRestoreCandidate
				&& this.fullBackupRestoreConfirmation === 'RESTORE'
				&& !this.fullBackupRestoringFileName;
		}
	},
	mounted() {
		this.fetchData()
		this.fetchApiSettings()
		this.fetchIntegrityReport()
		this.fetchFullBackupSettings()
		this.fetchStorageUsers()
	},
	beforeUnmount() {
		clearTimeout(this.storageUserSearchTimer);
		this.storageUserRequest++;
		this.passwordConfirm.password = '';
		this.passwordConfirm.open = false;
		if (typeof this.passwordConfirmResolver === 'function') {
			const resolve = this.passwordConfirmResolver;
			this.passwordConfirmResolver = null;
			resolve(false);
		}
	},
	methods: {
		openApiLimitGuide() {
			this.apiLimitGuideOpen = true;
		},
		closeApiLimitGuide() {
			this.apiLimitGuideOpen = false;
		},
		plainGuideText(segments) {
			return Array.isArray(segments) ? segments.map(segment => segment.text || '').join('') : '';
		},
		async copyGuideSnippet(code) {
			const value = String(code || '');
			if (!value) return;
			try {
				if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
					await navigator.clipboard.writeText(value);
					showToast(this.$texts.admin.configSnippetCopied());
					return;
				}
			} catch {
				// Fall through to legacy textarea copy.
			}
			try {
				const previousFocus = document.activeElement;
				const field = Object.assign(document.createElement('textarea'), { value });
				field.setAttribute('readonly', '');
				field.style.cssText = 'position:fixed;inset-block-start:0;opacity:0';
				document.body.append(field);
				field.select();
				const copied = document.execCommand('copy');
				field.remove();
				previousFocus?.focus?.({ preventScroll: true });
				if (!copied) throw new Error('Copy failed');
				showToast(this.$texts.admin.configSnippetCopied());
			} catch {
				showToast(this.$texts.admin.configSnippetCopyError(), 'error');
			}
		},
		searchStorageUsers(term) {
			this.storageUserSearch = term;
			this.storageUserRequest++;
			clearTimeout(this.storageUserSearchTimer);
			this.storageUserSearchTimer = setTimeout(() => this.fetchStorageUsers(), 250);
		},
		async fetchStorageUsers() {
			const request = ++this.storageUserRequest;
			this.storageUsersLoading = true;
			this.storageUsersError = '';
			try {
				const response = await axios.get(generateOcsUrl('/cloud/groups/admin/users/details'), { params: { search: this.storageUserSearch, limit: 50, offset: 0 }, skipWorkspaceHeader: true });
				if (request !== this.storageUserRequest) return;
				this.storageUsers = Object.entries(response.data?.ocs?.data?.users || {}).map(([id, user]) => ({ id, displayName: user.displayname || user.displayName || id }));
			} catch (error) {
				if (request === this.storageUserRequest) this.storageUsersError = extractError(error, this.$texts.memberSearch.searchError());
			} finally { if (request === this.storageUserRequest) this.storageUsersLoading = false; }
		},
		async chooseFullBackupFolder() {
			if (this.fullBackupSettingsDisabled || this.folderPickerOpen || !this.canBrowseBackupFolder) return;
			this.folderPickerOpen = true;
			this.fullBackupError = '';
			const userId = this.fullBackupStorageUser;
			let changed = false;
			try {
				const canPick = node => node.type === 'folder' && node.path !== '/' && !!(node.permissions & Permission.CREATE);
				const folder = await pickFolder(getFilePickerBuilder(this.$texts.admin.fullBackupFolder())
					.setMultiSelect(false).allowDirectories(true).setMimeTypeFilter(['httpd/unix-directory'])
					.startAt('/').setCanPick(canPick)
					.setButtonFactory(nodes => [{ label: this.$texts.settings.selectFolder(), variant: 'primary', disabled: nodes.length !== 1 || !canPick(nodes[0]), callback() {} }]));
				if (typeof folder === 'string' && this.fullBackupStorageUser === userId && folder.replace(/^\/+|\/+$/g, '')) {
					this.fullBackupStorageFolder = folder.replace(/^\/+|\/+$/g, '');
					changed = true;
				}
			} catch (error) {
				if (error?.name !== 'FilePickerClosed') this.fullBackupError = extractError(error, this.$texts.admin.fullBackupSaveError());
			} finally { this.folderPickerOpen = false; }
			if (changed) await this.saveFullBackupSettings();
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
		async fetchData() {
			this.loadError = '';
			this.loading = true;
			try {
				await this.$refs.masterData?.load();
			} catch (e) {
				this.loadError = extractError(e, this.$texts.admin.loadError());
			} finally {
				this.loading = false;
			}
		},
		async fetchApiSettings() {
			this.apiSettingsLoadError = '';
			this.apiSettingsSaveError = '';
			this.apiSettingsLoading = true;
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/admin/api/settings'), { skipWorkspaceHeader: true });
				this.apiAccessEnabled = response.data?.enabled === true;
			} catch (e) {
				this.apiSettingsLoadError = extractError(e, this.$texts.admin.apiAccessLoadError());
			} finally {
				this.apiSettingsLoading = false;
			}
		},
		isPasswordConfirmationRequired(error) {
			if (error?.response?.status !== 403) return false;
			const headers = error.response.headers;
			if (!headers || typeof headers !== 'object') return false;
			const check = name => {
				const direct = typeof headers.get === 'function' ? headers.get(name) : undefined;
				if (direct !== undefined && direct !== null) return String(direct).toLowerCase() === 'true';
				const matchKey = Object.keys(headers).find(key => key.toLowerCase() === name);
				return matchKey ? String(headers[matchKey]).toLowerCase() === 'true' : false;
			};
			return check('x-nc-auth-notconfirmed') || check('x-nextcloud-password-confirmation');
		},
		requestPasswordConfirmation() {
			if (typeof this.passwordConfirmResolver === 'function') {
				const prev = this.passwordConfirmResolver;
				this.passwordConfirmResolver = null;
				prev(false);
			}
			this.passwordConfirm = {
				open: true,
				password: '',
				busy: false,
				error: '',
			};
			return new Promise(resolve => {
				this.passwordConfirmResolver = resolve;
			});
		},
		cancelPasswordConfirmation() {
			if (this.passwordConfirm.busy) return;
			this.passwordConfirm.password = '';
			this.passwordConfirm.error = '';
			this.passwordConfirm.open = false;
			if (typeof this.passwordConfirmResolver === 'function') {
				const resolve = this.passwordConfirmResolver;
				this.passwordConfirmResolver = null;
				resolve(false);
			}
		},
		async submitPasswordConfirmation() {
			if (!this.passwordConfirm.open || this.passwordConfirm.busy || !this.passwordConfirm.password) return;
			this.passwordConfirm.busy = true;
			this.passwordConfirm.error = '';
			try {
				const { data } = await axios.post(
					generateUrl('/login/confirm'),
					{ password: this.passwordConfirm.password },
					{ skipWorkspaceHeader: true },
				);
				if (typeof window !== 'undefined' && data?.lastLogin) {
					window.nc_lastLogin = Number(data.lastLogin);
				}
				this.passwordConfirm.password = '';
				this.passwordConfirm.open = false;
				if (typeof this.passwordConfirmResolver === 'function') {
					const resolve = this.passwordConfirmResolver;
					this.passwordConfirmResolver = null;
					resolve(true);
				}
			} catch (error) {
				this.passwordConfirm.password = '';
				if (error?.response?.status === 403) {
					this.passwordConfirm.error = this.$texts.apiTokens.passwordConfirmInvalid();
				} else {
					this.passwordConfirm.error = extractError(error, this.$texts.apiTokens.passwordConfirmError());
				}
			} finally {
				this.passwordConfirm.busy = false;
			}
		},
		async runWithPasswordConfirmation(actionFn) {
			try {
				return { cancelled: false, result: await actionFn() };
			} catch (error) {
				if (!this.isPasswordConfirmationRequired(error)) {
					throw error;
				}
				const confirmed = await this.requestPasswordConfirmation();
				if (!confirmed) {
					return { cancelled: true, result: null };
				}
				return { cancelled: false, result: await actionFn() };
			}
		},
		async saveApiAccessEnabled(enabled) {
			if (this.apiSettingsLoading || this.apiSettingsSaving || this.apiSettingsLoadError) {
				return false;
			}
			const previous = this.apiAccessEnabled;
			const next = !!enabled;
			this.apiAccessEnabled = next;
			this.apiSettingsSaveError = '';
			this.apiSettingsSaving = true;
			try {
				const { cancelled, result } = await this.runWithPasswordConfirmation(() => axios.post(
					generateUrl('/apps/cobudget/api/admin/api/settings'),
					{ enabled: next },
					{ skipWorkspaceHeader: true }
				));
				if (cancelled) {
					this.apiAccessEnabled = previous;
					return false;
				}
				this.apiAccessEnabled = result.data?.enabled === true;
				showToast(this.$texts.admin.apiAccessSaved());
				return true;
			} catch (e) {
				this.apiAccessEnabled = previous;
				this.apiSettingsSaveError = extractError(e, this.$texts.admin.apiAccessSaveError());
				return false;
			} finally {
				this.apiSettingsSaving = false;
			}
		},
		async fetchIntegrityReport() {
			this.integrityError = '';
			this.integrityLoading = true;
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/admin/integrity'));
				this.integrityReport = response.data || null;
			} catch (e) {
				this.integrityError = extractError(e, this.$texts.admin.dataQualityLoadError());
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

			this.integrityError = '';
			this.integrityRepairing = true;
			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/admin/integrity/repair'));
				this.integrityReport = response.data || null;
				showToast(this.$texts.admin.qualityRepaired());
			} catch (e) {
				this.integrityError = extractError(e, this.$texts.admin.dataQualityRepairError());
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
			this.integrityError = '';
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
				this.integrityError = extractError(e, this.$texts.admin.duplicateMergeError());
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
		applyFullBackupSettings(settings) {
			this.fullBackupStorageUser = settings?.storage_user_id || '';
			this.fullBackupStorageFolder = settings?.storage_folder || 'CoBudget/Backups';
			this.fullBackupRetentionCount = Number(settings?.retention_count || 7);
			this.fullBackupSchedule = settings?.schedule || 'none';
			this.savedFullBackupSettings = { ...settings, storage_user_id: this.fullBackupStorageUser, storage_folder: this.fullBackupStorageFolder, retention_count: this.fullBackupRetentionCount, schedule: this.fullBackupSchedule };
		},
		normalizeFullBackups(backups) {
			return Array.isArray(backups) ? backups : [];
		},
		applyFullBackupResponse(data) {
			this.applyFullBackupSettings(data?.settings || {});
			this.fullBackups = this.normalizeFullBackups(data?.backups);
		},
		async fetchFullBackupSettings() {
			this.fullBackupLoadError = '';
			this.fullBackupSettingsLoading = true;
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/admin/full-backup/settings'), { skipWorkspaceHeader: true });
				this.applyFullBackupResponse(response.data || {});
			} catch (e) {
				this.fullBackupLoadError = extractError(e, this.$texts.admin.fullBackupLoadError());
			} finally {
				this.fullBackupSettingsLoading = false;
			}
		},
		setFullBackupSchedule(value) {
			this.fullBackupSchedule = value;
			this.saveFullBackupSettings();
		},
		async saveFullBackupSettings() {
			if (this.fullBackupSettingsDisabled || this.folderPickerOpen || !this.savedFullBackupSettings) return false;
			// Invalid edits supersede any queued snapshot but must never reach the API.
			if (this.fullBackupValidationError) { this.queuedFullBackupSettings = null; return false; }
			this.queuedFullBackupSettings = { ...this.fullBackupSettingsPayload };
			if (this.fullBackupSettingsSaving) return false;
			if (!this.fullBackupSettingsDirty && !this.fullBackupSettingsError) { this.queuedFullBackupSettings = null; return true; }
			this.fullBackupSettingsSaving = true;
			try {
				while (this.queuedFullBackupSettings) {
					const payload = this.queuedFullBackupSettings;
					this.queuedFullBackupSettings = null;
					const response = await axios.post(generateUrl('/apps/cobudget/api/admin/full-backup/settings'), payload, { skipWorkspaceHeader: true });
					const saved = response.data?.settings;
					if (!saved) throw new Error(this.$texts.admin.fullBackupSaveError());
					const current = this.fullBackupSettingsPayload;
					const fields = { storage_user_id: 'fullBackupStorageUser', storage_folder: 'fullBackupStorageFolder', retention_count: 'fullBackupRetentionCount', schedule: 'fullBackupSchedule' };
					// A response must not overwrite text or a selection changed during this request.
					for (const [key, field] of Object.entries(fields)) {
						if (current[key] === payload[key]) this[field] = saved[key];
					}
					this.savedFullBackupSettings = { ...saved };
					this.fullBackups = this.normalizeFullBackups(response.data?.backups);
					this.fullBackupSettingsError = '';
					if (this.queuedFullBackupSettings && Object.entries(this.queuedFullBackupSettings).every(([key, value]) => value === saved[key])) this.queuedFullBackupSettings = null;
				}
				return true;
			} catch (error) {
				this.queuedFullBackupSettings = null;
				this.fullBackupSettingsError = extractError(error, this.$texts.admin.fullBackupSaveError());
				return false;
			} finally { this.fullBackupSettingsSaving = false; }
		},
		async createFullBackupNow() {
			if (this.fullBackupCreateHint) return;
			this.fullBackupError = '';
			this.fullBackupCreating = true;
			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/admin/full-backup'), {}, { skipWorkspaceHeader: true });
				this.applyFullBackupResponse(response.data || {});
				const filePath = response.data?.backup?.file_path || '';
				showToast(filePath ? this.$texts.admin.fullBackupCreated(filePath) : this.$texts.admin.fullBackupCreatedToast());
			} catch (e) {
				this.fullBackupError = extractError(e, this.$texts.admin.fullBackupCreateError());
			} finally {
				this.fullBackupCreating = false;
			}
		},
		downloadFullBackup(backup) {
			if (!backup?.file_name) {
				return;
			}
			window.location.href = generateUrl(`/apps/cobudget/api/admin/full-backup/${encodeURIComponent(backup.file_name)}/download`);
		},
		async deleteFullBackup(backup) {
			if (!backup?.file_name || this.fullBackupSettingsSaving) {
				return;
			}
			const confirmed = await this.openConfirm({
				title: this.$texts.admin.fullBackupDeleteTitle(),
				message: this.$texts.admin.fullBackupDeleteMessage(backup.file_name),
				confirmLabel: this.$texts.admin.fullBackupDeleteConfirm(),
				confirmVariant: 'danger'
			});
			if (!confirmed) {
				return;
			}
			this.fullBackupError = '';
			this.fullBackupDeletingFileName = backup.file_name;
			try {
				const response = await axios.delete(generateUrl(`/apps/cobudget/api/admin/full-backup/${encodeURIComponent(backup.file_name)}`), { skipWorkspaceHeader: true });
				this.applyFullBackupResponse(response.data || {});
				showToast(this.$texts.admin.fullBackupDeleted());
			} catch (e) {
				this.fullBackupError = extractError(e, this.$texts.admin.fullBackupDeleteError());
			} finally {
				this.fullBackupDeletingFileName = '';
			}
		},
		openFullBackupRestore(backup) {
			if (this.fullBackupSettingsSaving) return;
			this.fullBackupRestoreError = '';
			this.fullBackupRestoreCandidate = backup;
			this.fullBackupRestoreConfirmation = '';
		},
		closeFullBackupRestoreModal() {
			if (this.fullBackupRestoringFileName) {
				return;
			}
			this.fullBackupRestoreCandidate = null;
			this.fullBackupRestoreConfirmation = '';
		},
		async confirmFullBackupRestore() {
			if (!this.canRestoreFullBackup) {
				return;
			}
			const fileName = this.fullBackupRestoreCandidate.file_name;
			this.fullBackupRestoreError = '';
			this.fullBackupRestoringFileName = fileName;
			try {
				const response = await axios.post(generateUrl('/apps/cobudget/api/admin/full-backup/restore'), {
					file_name: fileName,
					confirmation: this.fullBackupRestoreConfirmation
				}, { skipWorkspaceHeader: true });
				this.applyFullBackupResponse(response.data || {});
				this.fullBackupRestoreCandidate = null;
				this.fullBackupRestoreConfirmation = '';
				showToast(this.$texts.admin.fullBackupRestored());
			} catch (e) {
				this.fullBackupRestoreError = extractError(e, this.$texts.admin.fullBackupRestoreError());
			} finally {
				this.fullBackupRestoringFileName = '';
			}
		},
		formatBackupDate(timestamp) {
			const numericTimestamp = Number(timestamp || 0);
			if (!numericTimestamp) {
				return this.$texts.common.unknownDate();
			}
			return new Intl.DateTimeFormat(getAppLocale(), {
				dateStyle: 'medium',
				timeStyle: 'short'
			}).format(new Date(numericTimestamp * 1000));
		},
		formatBackupSize(size) {
			const bytes = Number(size || 0);
			if (bytes <= 0) {
				return '0 B';
			}
			const units = ['B', 'KB', 'MB', 'GB'];
			let value = bytes;
			let unitIndex = 0;
			while (value >= 1024 && unitIndex < units.length - 1) {
				value /= 1024;
				unitIndex += 1;
			}
			return `${value.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
		},
		shellQuote(value) {
			return `"${String(value || '').replace(/(["\\$`])/g, '\\$1')}"`;
		},
		fullBackupOccCommand(backup) {
			return [
				'occ',
				'cobudget:backup:restore-full',
				'--user',
				this.shellQuote(this.fullBackupStorageUser),
				'--folder',
				this.shellQuote(this.fullBackupStorageFolder),
				'--file',
				this.shellQuote(backup?.file_name || '')
			].join(' ');
		},
	}
}
</script>

<style scoped src="../styles/bulk-import-tables.css"></style>

<style scoped>
/* Nextcloud Settings' .section p rule must not offset native note content. */
.cobudget-admin-settings :deep(.notecard p) {
	margin-block: 0;
	color: inherit;
}

.settings-section {
	display: block;
	margin: var(--cobudget-form-inset);
	width: min(var(--cobudget-form-max-width), calc(100% - var(--cobudget-form-inset) * 2));
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
	gap: var(--cobudget-settings-section-gap);
	margin-bottom: 28px;
}

.admin-master-data { margin-block: 0; }
.admin-master-data :deep(.master-section + .master-section) { margin-block-start: var(--cobudget-settings-heading-gap); }
.settings-section > h2 { font-size: var(--cobudget-font-page-title); font-weight: var(--cobudget-font-weight-heading); line-height: 1.3; margin-block: 0 12px; overflow-wrap: anywhere; }
.admin-master-data :deep(.master-section > h3) { font-size: var(--cobudget-font-section-title); font-weight: var(--cobudget-font-weight-heading); }
.admin-master-data :deep(.master-heading h4) { font-size: var(--cobudget-font-subheading); font-weight: var(--cobudget-font-weight-heading); }
.admin-master-data :deep(.master-section > h3) { margin-block: 0 12px; }
.integrity-card,
.admin-backup-card { margin-block: var(--cobudget-settings-heading-gap) 0; }
.settings-loading { display: flex; align-items: center; gap: var(--cobudget-space-2, 8px); }
.admin-backup-settings-grid > *, .integrity-card-header > div { min-width: 0; }
.integrity-list li, .integrity-duplicate-row, .integrity-group h4 { overflow-wrap: anywhere; }

.integrity-card-header {
	align-items: flex-start;
	display: flex;
	gap: 16px;
	justify-content: space-between;
}

.integrity-card h3,
.admin-backup-card h3 {
	font-size: var(--cobudget-font-section-title);
	font-weight: var(--cobudget-font-weight-heading);
	margin: 0 0 12px 0;
}

.admin-section-hint {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	line-height: 1.45;
	margin: 0 0 12px 0;
}

.admin-api-card :deep(.notecard) {
	margin-bottom: 12px;
}

.admin-api-limit-actions {
	display: grid;
	gap: 12px;
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.admin-api-limit-actions > * {
	min-width: 0;
}

.admin-api-guide-dialog {
	display: block;
	padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding, 24px) var(--cobudget-dialog-padding, 24px);
}

.admin-api-guide-dialog h2 {
	color: var(--cobudget-text, var(--color-main-text, #222));
	display: block;
	font-size: var(--cobudget-dialog-title-size, 20px);
	font-weight: var(--cobudget-font-weight-heading, 600);
	line-height: 1.4;
	margin: 0 0 calc(var(--default-grid-baseline, 4px) * 4) 0;
	max-width: none;
	padding-inline-end: var(--cobudget-mobile-touch-size, 44px);
	text-align: start;
}

.admin-api-guide-body {
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.admin-api-guide-dialog h3.admin-api-guide-heading {
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--cobudget-font-section-title, 18px);
	font-weight: var(--cobudget-font-weight-heading, 600);
	line-height: 1.35;
	margin: 14px 0 0 0;
}

.admin-api-guide-dialog h4.admin-api-guide-heading {
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--cobudget-font-subheading, 16px);
	font-weight: var(--cobudget-font-weight-heading, 600);
	line-height: 1.35;
	margin: 10px 0 0 0;
}

.admin-api-guide-heading:first-child {
	margin-top: 0;
}

.admin-api-guide-dialog .admin-api-guide-paragraph {
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--cobudget-font-body, 15px);
	line-height: 1.55;
	margin: 0;
	max-width: none;
}

.admin-api-guide-dialog a {
	color: var(--cobudget-info-text, var(--color-primary-element-light-text, var(--color-primary-element, #0082c9)));
	text-decoration: underline;
	text-underline-offset: 2px;
}

.admin-api-guide-dialog a:hover,
.admin-api-guide-dialog a:focus-visible {
	color: var(--cobudget-primary-hover, var(--color-primary-element-hover, #006a92));
}

.admin-api-guide-paragraph code,
.admin-api-guide-table code {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5));
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius, 4px);
	font-family: var(--font-monospace, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
	font-size: 0.88em;
	padding: 1px 5px;
	overflow-wrap: anywhere;
}

.admin-api-guide-code-card {
	flex: 0 0 auto;
	min-width: 0;
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5));
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
}

.admin-api-guide-code-toolbar {
	align-items: center;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	border-bottom: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px) var(--border-radius-large, 8px) 0 0;
	display: flex;
	gap: 8px;
	justify-content: space-between;
	padding: 4px 8px 4px 12px;
}

.admin-api-guide-code-lang {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-caption, 12px);
	font-weight: var(--font-weight-heading, 600);
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.admin-api-guide-code {
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-family: var(--font-monospace, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
	font-size: var(--cobudget-font-sm, 13px);
	line-height: 1.5;
	margin: 0;
	overflow-x: auto;
	padding: 12px 14px;
}

.admin-api-guide-table-wrap {
	flex: 0 0 auto;
	min-width: 0;
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
}

.admin-api-guide-table :is(th, td) {
	white-space: normal;
	overflow-wrap: anywhere;
}

.admin-api-guide-table tbody tr:last-child td {
	border-bottom: 0;
}

.admin-api-guide-actions {
	border-top: 1px solid var(--cobudget-border, #ddd);
	display: flex;
	justify-content: flex-end;
	margin-top: 20px;
	padding-top: 12px;
}

.integrity-card-header p,
.integrity-status,
.integrity-group > p,
.admin-backup-list-header p {
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

.admin-backup-settings-grid {
	align-items: end;
	display: grid;
	gap: var(--cobudget-section-gap) var(--cobudget-settings-section-gap);
	grid-template-columns: repeat(2, minmax(0, 1fr));
	margin-top: var(--cobudget-section-gap);
}

.admin-backup-wide, .admin-backup-schedule { grid-column: 1 / -1; min-width: 0; }
.admin-backup-create-action { display: flex; justify-content: flex-end; min-width: 0; }

.admin-backup-list {
	margin-top: 32px;
}

.admin-backup-list-header {
	align-items: flex-start;
	display: flex;
	gap: 16px;
	justify-content: space-between;
	margin-bottom: 14px;
}

.admin-backup-list-header h4 {
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--cobudget-font-subheading);
	font-weight: var(--cobudget-font-weight-heading);
	margin: 0 0 4px 0;
}

.admin-backup-items {
	display: grid;
	gap: 0;
	list-style: none;
	margin: 0;
	padding: 0;
}

.admin-backup-item {
	align-items: center;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	border-bottom: 1px solid var(--cobudget-border, #ddd);
	display: flex;
	gap: 16px;
	justify-content: space-between;
	padding: var(--cobudget-table-detail-padding);
	padding-inline: 0;
}

.admin-backup-info {
	display: grid;
	gap: 6px;
	min-width: 0;
}

.admin-backup-info strong {
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	line-height: 1.5;
}

.admin-backup-info span {
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-weight: 700;
	overflow-wrap: anywhere;
}

.admin-backup-info small {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
	overflow-wrap: anywhere;
}

.admin-backup-item-actions {
	flex-wrap: wrap;
	align-items: center;
	display: flex;
	flex: 0 0 auto;
	gap: var(--default-grid-baseline, 4px);
	justify-content: flex-end;
}

.admin-backup-occ {
	margin-top: 4px;
}

.admin-backup-occ summary {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	cursor: pointer;
	font-size: var(--cobudget-font-sm);
	width: max-content;
	max-width: 100%;
}

.admin-backup-occ code {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5));
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius, 6px);
	color: var(--cobudget-text, var(--color-main-text, #222));
	display: block;
	font-size: var(--cobudget-font-caption);
	line-height: 1.45;
	margin-top: 8px;
	max-width: 100%;
	overflow-x: auto;
	padding: 8px 10px;
	white-space: normal;
	word-break: break-word;
}

.integrity-status {
	margin-top: var(--cobudget-section-gap);
}

.admin-backup-item:last-child { border-bottom: 0; }

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
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-element, 500);
	margin: 0 0 12px 0;
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

/* NcRadioGroup renders its buttons in an NcFormBox row without wrapping. */
.admin-api-password-body {
	display: grid;
	gap: 12px;
}

.admin-backup-schedule :deep([class*="ncFormBox_row"]) {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
}

@media (max-width: 768px) {
	.admin-api-limit-actions {
		grid-template-columns: 1fr;
	}
	.admin-api-guide-dialog {
		padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding, 20px) var(--cobudget-dialog-padding, 20px);
	}
	.settings-section { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); }
	.settings-section > h2 { font-size: var(--cobudget-font-page-title-mobile); }
	.settings-section {
		margin: 0;
		width: 100%;
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
		justify-content: flex-end;
	}

	.admin-backup-settings-grid {
		grid-template-columns: 1fr;
	}

	.admin-backup-schedule :deep([class*="ncFormBox_row"]) { grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.admin-backup-create-action {
		justify-content: flex-end;
	}

	.admin-backup-item {
		align-items: stretch;
		flex-direction: column;
	}

	.admin-backup-item-actions {
		justify-content: flex-end;
		max-width: 100%;
	}
	.integrity-actions { flex: 1 1 auto; }
	.integrity-actions :deep(.button-vue), .integrity-merge-actions :deep(.button-vue), .admin-backup-create-action :deep(.button-vue) { max-width: 100%; height: auto; }
	.integrity-actions :deep(.button-vue__text), .integrity-merge-actions :deep(.button-vue__text), .admin-backup-create-action :deep(.button-vue__text) { white-space: normal; overflow-wrap: anywhere; }

}
</style>
