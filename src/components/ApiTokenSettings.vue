<template>
	<section id="api-tokens" class="settings-subsection api-token-settings" aria-labelledby="api-tokens-title">
		<h3 id="api-tokens-title" class="cobudget-section-heading">{{ $texts.apiTokens.title() }}</h3>
		<p class="settings-hint">{{ $texts.apiTokens.description() }}</p>

		<p v-if="!adminLoaded" class="settings-loading" role="status">
			<NcLoadingIcon :size="20" />
			{{ $texts.common.loading() }}
		</p>
		<NcNoteCard v-else-if="!adminEnabled" type="info" class="api-token-admin-disabled-note" :text="$texts.apiTokens.adminDisabledNotice()" />
		<div v-else class="api-token-master-toggle">
			<NcFormBoxSwitch
				:model-value="enabled"
				:label="$texts.apiTokens.enableLabel()"
				:description="$texts.apiTokens.enableDescription()"
				:disabled="settingsBusy"
				@update:model-value="$emit('toggle-enabled', $event)" />
		</div>

		<div v-if="effectiveEnabled || tokens.length || loading || loadError" class="api-token-management">
			<div class="api-token-heading">
				<h4 class="cobudget-subheading">{{ $texts.apiTokens.keysHeading() }}</h4>
				<NcButton
					v-if="effectiveEnabled"
					class="cobudget-native-button"
					variant="secondary"
					:disabled="loading || !!loadError"
					@click="openEditor()">
					<template #icon><PlusIcon :size="20" /></template>
					{{ $texts.apiTokens.addKey() }}
				</NcButton>
			</div>

			<p v-if="loading" class="settings-loading" role="status">
				<NcLoadingIcon :size="20" />
				{{ $texts.common.loading() }}
			</p>
			<NcNoteCard v-else-if="loadError" type="error">
				<p>{{ loadError }}</p>
				<NcButton variant="tertiary" @click="load">{{ $texts.common.retry() }}</NcButton>
			</NcNoteCard>
			<template v-else>
				<NcNoteCard v-if="tableError" type="error" :text="tableError" />
				<table class="api-token-table" aria-labelledby="api-tokens-title">
					<thead>
						<tr>
							<th scope="col">{{ $texts.common.name() }}</th>
							<th scope="col">{{ $texts.apiTokens.tokenColumn() }}</th>
							<th scope="col">{{ $texts.apiTokens.expiryLabel() }}</th>
							<th scope="col" class="api-token-control">{{ $texts.autoImports.active() }}</th>
							<th scope="col">{{ $texts.autoImports.lastRun() }}</th>
							<th scope="col" class="api-token-control api-token-actions-cell"><span class="hidden-visually">{{ $texts.apiTokens.actionsColumn() }}</span></th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="token in tokens"
							:key="token.id"
							:class="{ 'api-token-editable-row': effectiveEnabled }"
							:aria-busy="!!pendingToggle[token.id]"
							@click="openEditor(token)">
							<th scope="row">
								<div class="api-token-name-cell">
									<span class="api-token-name">{{ token.name }}</span>
									<small v-if="showWorkspaceColumn" class="api-token-workspace-meta">{{ workspaceName(token.workspace_id) }}</small>
								</div>
							</th>
							<td :data-label="$texts.apiTokens.tokenColumn()">
								<code class="api-token-preview">{{ formatTokenPreview(token) }}</code>
							</td>
							<td :data-label="$texts.apiTokens.expiryLabel()">
								<div v-if="token.expires_at" class="api-token-expiry-cell">
									<span>{{ formatDateShort(token.expires_at) }}</span>
									<span v-if="token.is_expired" data-cobudget-badge="warning" data-cobudget-badge-size="compact">
										{{ $texts.apiTokens.statusExpired() }}
									</span>
								</div>
								<span v-else class="api-token-muted">{{ $texts.apiTokens.expiryNever() }}</span>
							</td>
							<td class="api-token-control api-token-active" :data-label="$texts.autoImports.active()" @click.stop>
								<NcCheckboxRadioSwitch
									type="switch"
									:model-value="!!token.is_active && !token.is_expired"
									:aria-label="`${$texts.autoImports.active()} · ${token.name}`"
									:disabled="!!pendingToggle[token.id] || !!token.is_expired || (!effectiveEnabled && !token.is_active)"
									@update:model-value="toggleTokenActive(token, $event)" />
							</td>
							<td :data-label="$texts.autoImports.lastRun()">
								<span v-if="token.last_used_at" class="api-token-muted">{{ formatDateTime(token.last_used_at) }}</span>
								<span v-else class="api-token-muted">—</span>
							</td>
							<td class="api-token-control api-token-actions-cell" :data-label="$texts.apiTokens.actionsColumn()" @click.stop>
								<div class="api-token-row-actions">
									<NcButton
										v-if="effectiveEnabled"
										variant="tertiary"
										:title="$texts.apiTokens.endpointsPreviewTitle()"
										:aria-label="`${$texts.apiTokens.endpointsPreviewTitle()}: ${token.name}`"
										@click.stop="openEndpointsPreview(token)">
										<template #icon><ApiIcon :size="20" /></template>
									</NcButton>
									<NcButton
										v-if="effectiveEnabled"
										variant="tertiary"
										:disabled="!!pendingToggle[token.id]"
										:title="$texts.common.edit()"
										:aria-label="`${$texts.common.edit()}: ${token.name}`"
										@click.stop="openEditor(token)">
										<template #icon><PencilIcon :size="20" /></template>
									</NcButton>
									<NcButton
										v-else
										variant="tertiary"
										:disabled="saving || !!pendingToggle[token.id]"
										:title="$texts.common.delete()"
										:aria-label="`${$texts.common.delete()}: ${token.name}`"
										@click.stop="tokenToDelete = token">
										<template #icon><DeleteOutlineIcon :size="20" /></template>
									</NcButton>
								</div>
							</td>
						</tr>
						<tr v-if="!tokens.length" class="api-token-empty-row">
							<td colspan="6" class="api-token-empty-cell">{{ $texts.apiTokens.empty() }}</td>
						</tr>
					</tbody>
				</table>
			</template>
		</div>

		<DialogTransition :show="!!editor" v-slot="{ returnFocus }">
			<NcModal
				:container="null"
				:set-return-focus="returnFocus"
				class="cobudget-native-dialog cobudget-settings-modal api-token-editor-modal"
				:show="true"
				label-id="api-token-editor-title"
				size="large"
				:no-close="saving || confirmDiscard || !!tokenToDelete"
				:close-on-click-outside="!saving && !confirmDiscard && !tokenToDelete"
				@update:show="closeEditor"
				@keydown.esc.stop.prevent="closeEditor">
				<form class="api-token-editor" :aria-busy="saving" @submit.prevent="save">
					<h2 id="api-token-editor-title">{{ editor.token ? $texts.apiTokens.editKey() : $texts.apiTokens.addKey() }}</h2>
					<div class="api-token-editor-body">
						<NcFormBoxSwitch
							v-if="editor.token"
							v-model="editor.isActive"
							class="api-token-active-switch"
							:label="$texts.apiTokens.activeSwitch()"
							:description="$texts.apiTokens.activeSwitchHint()"
							:disabled="saving" />

						<NcFormGroup class="cobudget-form-group">
							<template #label>
								<label class="cobudget-form-label" for="api-token-name">{{ $texts.common.name() }}</label>
							</template>
							<NcInputField
								id="api-token-name"
								v-model="editor.name"
								label-outside
								:label="$texts.common.name()"
								:placeholder="$texts.apiTokens.namePlaceholder()"
								maxlength="128"
								required
								:disabled="saving"
								class="cobudget-native-field" />
						</NcFormGroup>

						<NcFormGroup
							v-if="showWorkspaceField"
							class="cobudget-form-group"
							:description="editor.token ? $texts.apiTokens.workspaceLockedHint() : undefined">
							<template #label>
								<label class="cobudget-form-label" for="api-token-workspace">{{ $texts.apiTokens.workspaceLabel() }}</label>
							</template>
							<NcSelect
								input-id="api-token-workspace"
								class="cobudget-native-select"
								label-outside
								:input-label="$texts.apiTokens.workspaceLabel()"
								:model-value="selectedWorkspaceOption"
								:options="workspaces"
								label="name"
								:clearable="false"
								:disabled="saving || !!editor.token"
								@update:model-value="changeWorkspace" />
						</NcFormGroup>

						<NcFormGroup class="api-token-editor-section cobudget-form-group" :label="$texts.apiTokens.permissionsColumn()">
							<div class="api-token-permissions-stack">
								<div class="api-token-matrix-scroll">
									<table class="api-token-matrix-table" :aria-label="$texts.apiTokens.paymentsHeading()">
										<thead>
											<tr>
												<th scope="col">{{ $texts.apiTokens.paymentsHeading() }}</th>
												<th v-for="col in matrixColumns" :key="col.key" scope="col" class="api-token-matrix-col">{{ col.label }}</th>
											</tr>
										</thead>
										<tbody>
											<tr v-for="row in dataTargetRows" :key="row.key">
												<th scope="row">
													<span class="api-token-matrix-target">
														<AreaIcon :without-area="row.areaId === null" :color="row.color" />
														<span>{{ row.name }}</span>
													</span>
												</th>
												<td v-for="col in matrixColumns" :key="col.key" class="api-token-matrix-col">
													<NcCheckboxRadioSwitch
														:model-value="hasTargetAction(row.key, col.key)"
														:aria-label="`${row.name} – ${col.label}`"
														:disabled="saving"
														@update:model-value="toggleTargetAction(row.key, col.key, $event)" />
												</td>
											</tr>
										</tbody>
									</table>
								</div>

								<div class="api-token-matrix-scroll">
									<table class="api-token-matrix-table" :aria-label="$texts.apiTokens.generalHeading()">
										<thead>
											<tr>
												<th scope="col">{{ $texts.apiTokens.generalHeading() }}</th>
												<th v-for="col in matrixColumns" :key="col.key" scope="col" class="api-token-matrix-col">{{ col.label }}</th>
											</tr>
										</thead>
										<tbody>
											<tr v-for="row in generalMatrixRows" :key="row.key">
												<th scope="row">
													<div class="api-token-matrix-row-heading">
														<span>{{ row.label }}</span>
														<small v-if="row.hint" class="api-token-matrix-hint">{{ row.hint }}</small>
													</div>
												</th>
												<td v-for="col in matrixColumns" :key="col.key" class="api-token-matrix-col">
													<NcCheckboxRadioSwitch
														v-if="row.scopes[col.key]"
														:model-value="editor.globalScopes.includes(row.scopes[col.key])"
														:aria-label="`${row.label} – ${col.label}`"
														:disabled="saving || (row.scopes[col.key] === 'reports:read' && !canUseReportsScope)"
														@update:model-value="toggleGlobalScope(row.scopes[col.key], $event)" />
													<span
														v-else
														class="api-token-matrix-unavailable"
														:aria-label="`${row.label} – ${col.label}: ${$texts.apiTokens.actionUnavailable()}`"
														:title="$texts.apiTokens.actionUnavailable()">—</span>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</NcFormGroup>

						<div class="api-token-editor-section api-token-validity">
							<NcFormGroup class="api-token-validity-field cobudget-form-group">
								<template #label>
									<label class="cobudget-form-label" for="api-token-expiry">{{ $texts.apiTokens.expiryLabel() }}</label>
								</template>
								<NcSelect
									uid="cobudget-api-token-expiry"
									input-id="api-token-expiry"
									class="cobudget-native-select"
									label-outside
									:input-label="$texts.apiTokens.expiryLabel()"
									:model-value="selectedExpiryOption"
									:options="expiryOptions"
									label="label"
									:selectable="option => !option.heading"
									:clearable="false"
									:searchable="false"
									:disabled="saving"
									@update:model-value="changeExpiryPreset">
									<template #option="option">
										<span v-if="option.heading" class="api-token-expiry-group-heading">{{ option.label }}</span>
										<span v-else :class="{ 'api-token-expiry-template-option': option.template }">{{ option.label }}</span>
									</template>
								</NcSelect>
							</NcFormGroup>

							<NcFormGroup class="api-token-validity-field cobudget-form-group">
								<template #label>
									<label class="cobudget-form-label" for="api-token-expiry-date">{{ $texts.apiTokens.expiryDateColumn() }}</label>
								</template>
								<NcInputField
									id="api-token-expiry-date"
									:model-value="editor.expiryDate"
									type="date"
									label-outside
									:label="$texts.apiTokens.expiryDateColumn()"
									:min="editor.expiryPreset === 'none' ? undefined : minExpiryDate"
									max="2099-12-31"
									:required="editor.expiryPreset !== 'none'"
									:disabled="saving || editor.expiryPreset === 'none'"
									class="cobudget-native-field"
									@update:model-value="changeExpiryDate" />
							</NcFormGroup>
						</div>

						<NcNoteCard v-if="saveError" type="error" :text="saveError" />
					</div>

					<div class="api-token-actions">
						<NcButton
							v-if="editor.token"
							class="api-token-delete-button"
							variant="error"
							:aria-label="$texts.common.delete()"
							:title="$texts.common.delete()"
							:disabled="saving"
							@click="tokenToDelete = editor.token">
							<template #icon><DeleteOutlineIcon class="api-token-delete-icon" :size="20" /></template>
							<span class="api-token-delete-label">{{ $texts.common.delete() }}</span>
						</NcButton>
						<div class="api-token-confirm-actions">
							<NcButton variant="tertiary" :disabled="saving" @click="closeEditor">{{ $texts.common.cancel() }}</NcButton>
							<NcButton type="submit" variant="primary" :disabled="saving || !canSave">
								{{ saving ? $texts.common.saveBusy() : (editor.token ? $texts.common.save() : $texts.apiTokens.createButton()) }}
							</NcButton>
						</div>
					</div>
				</form>
			</NcModal>
		</DialogTransition>

		<DialogTransition :show="!!(editor && confirmDiscard)" v-slot="{ returnFocus }">
			<NcDialog
				:container="null"
				:set-return-focus="returnFocus"
				class="cobudget-native-dialog cobudget-standard-dialog"
				:name="$texts.entry.discardChangesTitle()"
				:message="$texts.entry.discardChangesMessage()"
				close-on-click-outside
				@keydown.esc.stop.prevent="confirmDiscard = false"
				@closing="confirmDiscard = false">
				<template #actions>
					<NcButton variant="tertiary" @click="confirmDiscard = false">{{ $texts.common.cancel() }}</NcButton>
					<NcButton variant="error" @click="discardAndClose">{{ $texts.entry.discardChangesConfirm() }}</NcButton>
				</template>
			</NcDialog>
		</DialogTransition>

		<DialogTransition :show="!!tokenToDelete" v-slot="{ returnFocus }">
			<NcDialog
				:container="null"
				:set-return-focus="returnFocus"
				class="cobudget-native-dialog cobudget-standard-dialog"
				:name="$texts.common.delete()"
				:message="$texts.apiTokens.deleteMessage(tokenToDelete?.name || '')"
				:no-close="saving"
				:close-on-click-outside="!saving"
				@keydown.esc.stop.prevent="closeDeleteConfirmation"
				@closing="closeDeleteConfirmation">
				<template #actions>
					<NcButton variant="tertiary" :disabled="saving" @click="closeDeleteConfirmation">{{ $texts.common.cancel() }}</NcButton>
					<NcButton variant="error" :disabled="saving" @click="revokeToken">{{ $texts.common.delete() }}</NcButton>
				</template>
			</NcDialog>
		</DialogTransition>

		<DialogTransition :show="!!revealedToken" v-slot="{ returnFocus }">
			<NcDialog
				:container="null"
				:set-return-focus="returnFocus"
				class="cobudget-native-dialog cobudget-standard-dialog api-token-secret-dialog"
				size="normal"
				:name="$texts.apiTokens.createdTitle()"
				:no-close="false"
				:close-on-click-outside="false"
				@closing="closeRevealedToken">
				<div class="api-token-secret-body">
					<NcNoteCard type="warning" :text="$texts.apiTokens.createdOnceWarning()" />
					<div class="api-token-secret-field">
						<label class="cobudget-form-label" for="api-token-plain-secret">{{ $texts.apiTokens.createdSecretLabel() }}</label>
						<div class="api-token-secret-row">
							<input
								id="api-token-plain-secret"
								ref="plainSecretInput"
								:value="revealedToken.plainToken"
								type="text"
								readonly
								autocomplete="off"
								autocapitalize="off"
								spellcheck="false"
								class="form-control api-token-secret-input"
								data-cobudget-field
								@focus="$event.target.select()">
							<NcButton
								type="button"
								variant="secondary"
								class="cobudget-native-button"
								:aria-label="$texts.apiTokens.copyKey()"
								@click="copyPlainToken">
								<template #icon>
									<CheckIcon v-if="copySucceeded" :size="20" />
									<ContentCopyIcon v-else :size="20" />
								</template>
								{{ $texts.common.copy() }}
							</NcButton>
						</div>
					</div>
				</div>
				<template #actions>
					<NcButton variant="primary" @click="closeRevealedToken">{{ $texts.common.close() }}</NcButton>
				</template>
			</NcDialog>
		</DialogTransition>

		<DialogTransition :show="passwordConfirm.open" v-slot="{ returnFocus }">
			<NcDialog
				:container="null"
				:set-return-focus="returnFocus"
				class="cobudget-native-dialog cobudget-standard-dialog api-token-password-dialog"
				size="normal"
				:name="$texts.apiTokens.passwordConfirmTitle()"
				:no-close="passwordConfirm.busy"
				:close-on-click-outside="!passwordConfirm.busy"
				@closing="cancelPasswordConfirmation">
				<form class="api-token-password-body" @submit.prevent="submitPasswordConfirmation">
					<p class="api-token-password-hint">{{ $texts.apiTokens.passwordConfirmHint() }}</p>
					<NcNoteCard v-if="passwordConfirm.error" type="error" :text="passwordConfirm.error" />
					<NcFormGroup class="cobudget-form-group">
						<template #label>
							<label class="cobudget-form-label" for="api-token-confirm-password">{{ $texts.apiTokens.passwordConfirmLabel() }}</label>
						</template>
						<NcInputField
							id="api-token-confirm-password"
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
				</template>
			</NcDialog>
		</DialogTransition>

		<DialogTransition :show="!!previewToken" v-slot="{ returnFocus }">
			<NcModal
				:container="null"
				:set-return-focus="returnFocus"
				class="cobudget-native-dialog cobudget-settings-modal api-token-endpoints-modal"
				:show="true"
				label-id="api-token-endpoints-title"
				size="large"
				:close-on-click-outside="true"
				@update:show="closeEndpointsPreview"
				@keydown.esc.stop.prevent="closeEndpointsPreview">
				<div class="api-token-endpoints-dialog">
					<h2 id="api-token-endpoints-title">{{ $texts.apiTokens.endpointsPreviewHeading(previewToken?.name || '') }}</h2>
					<div class="api-token-endpoints-body">
						<NcNoteCard v-if="previewToken?.is_expired" type="warning" :text="$texts.apiTokens.endpointsExpiredWarning()" />
						<NcNoteCard v-else-if="previewToken && !previewToken.is_active" type="warning" :text="$texts.apiTokens.endpointsPausedWarning()" />

						<div class="api-token-endpoints-toolbar">
							<NcButton
								variant="secondary"
								:title="$texts.apiTokens.exportOpenApiTooltip()"
								@click="downloadOpenApiSpec">
								<template #icon><DownloadIcon :size="18" /></template>
								{{ $texts.apiTokens.exportOpenApiButton() }}
							</NcButton>
							<NcButton
								variant="secondary"
								:title="$texts.apiTokens.exportHttpTooltip()"
								@click="downloadHttpFile">
								<template #icon><DownloadIcon :size="18" /></template>
								{{ $texts.apiTokens.exportHttpButton() }}
							</NcButton>
						</div>

						<section class="api-token-endpoints-section api-token-endpoints-auth">
							<h3 class="cobudget-section-heading">{{ $texts.apiTokens.endpointsAuthHeading() }}</h3>
							<div class="api-token-endpoint-field api-token-auth-card">
								<div v-if="previewToken" class="api-token-auth-meta">
									<span class="api-token-auth-meta-item">
										<code class="bulk-import-field-code">{{ $texts.apiTokens.endpointsAuthKeyPrefix(formatTokenPreview(previewToken)) }}</code>
									</span>
									<span v-if="showWorkspaceColumn" class="api-token-auth-meta-item">
										{{ $texts.apiTokens.endpointsAuthWorkspaceBadge(workspaceName(previewToken.workspace_id)) }}
									</span>
								</div>
								<table class="bulk-import-table api-token-auth-table">
									<thead>
										<tr>
											<th scope="col" class="col-auth-header">{{ $texts.apiTokens.endpointsAuthFieldLabel() }}</th>
											<th scope="col" class="col-auth-status"><span class="hidden-visually">{{ $texts.bulkImport.fieldColumnRequired() }}</span></th>
											<th scope="col" class="col-auth-example">{{ $texts.apiTokens.endpointsAuthExampleColumn() }}</th>
											<th scope="col" class="col-auth-copy"><span class="hidden-visually">{{ $texts.apiTokens.actionsColumn() }}</span></th>
										</tr>
									</thead>
									<tbody>
										<tr v-for="row in previewAuthHeaderRows" :key="row.key">
											<td :data-label="$texts.apiTokens.endpointsAuthFieldLabel()" class="col-auth-header">
												<code class="bulk-import-field-code">{{ row.name }}</code>
											</td>
											<td :data-label="$texts.bulkImport.fieldColumnRequired()" class="col-auth-status">
												<span
													class="bulk-import-badge"
													:class="row.recommended ? 'bulk-import-badge--required' : 'bulk-import-badge--optional'">
													{{ row.badgeLabel }}
												</span>
											</td>
											<td :data-label="$texts.apiTokens.endpointsAuthExampleColumn()" class="col-auth-example">
												<code class="api-token-auth-example">{{ row.example }}</code>
											</td>
											<td class="col-auth-copy">
												<NcButton
													variant="tertiary"
													class="api-token-auth-copy"
													:title="$texts.apiTokens.copyAuthHeader(row.name)"
													:aria-label="$texts.apiTokens.copyAuthHeader(row.name)"
													@click="copyAuthHeader(row.copyValue)">
													<template #icon><ContentCopyIcon :size="16" /></template>
												</NcButton>
											</td>
										</tr>
									</tbody>
								</table>
								<p class="api-token-auth-note">{{ $texts.apiTokens.endpointsAuthNoUrlHint() }}</p>
							</div>
						</section>

						<section v-for="group in previewEndpointGroups" :key="group.key" class="api-token-endpoints-section api-token-endpoints-group">
							<h3 class="cobudget-section-heading">{{ group.title }}</h3>
							<div class="api-token-endpoints-list">
								<NcFormGroup v-for="endpoint in group.endpoints" :key="endpoint.key" class="cobudget-form-group api-token-endpoint-item">
									<template #label><span class="cobudget-form-label">{{ endpoint.description }}</span></template>
									<template v-if="endpoint.note" #description>
										<span class="api-token-endpoint-description">
											<span>{{ endpoint.note }}</span>
										</span>
									</template>
									<div class="api-token-endpoint-field">
										<div class="api-token-endpoint-header-row">
											<span class="api-token-method-badge" :data-method="endpoint.method">{{ endpoint.method }}</span>
											<code class="api-token-endpoint-url">{{ endpoint.url }}</code>
											<div class="api-token-endpoint-controls">
												<NcButton
													v-if="endpoint.parameters.length"
													variant="tertiary"
													class="api-token-params-toggle"
													:aria-expanded="isEndpointExpanded(endpoint.key) ? 'true' : 'false'"
													:aria-controls="endpointParamsId(endpoint.key)"
													@click="toggleEndpointParams(endpoint.key)">
													<span>{{ endpoint.paramToggleLabel }}</span>
													<ChevronDownIcon
														:size="18"
														aria-hidden="true"
														class="api-token-params-chevron"
														:class="{ 'is-open': isEndpointExpanded(endpoint.key) }" />
												</NcButton>
												<NcButton
													variant="tertiary"
													class="api-token-example-toggle"
													:class="{ 'is-active': isEndpointExampleExpanded(endpoint.key) }"
													:title="$texts.apiTokens.endpointCurlToggle()"
													:aria-label="$texts.apiTokens.endpointCurlToggle()"
													:aria-expanded="isEndpointExampleExpanded(endpoint.key) ? 'true' : 'false'"
													:aria-controls="endpointExampleId(endpoint.key)"
													@click="toggleEndpointExample(endpoint.key)">
													<template #icon><ConsoleIcon :size="18" /></template>
												</NcButton>
												<NcButton
													variant="tertiary"
													class="api-token-endpoint-copy"
													:title="$texts.apiTokens.copyEndpointUrl(endpoint.url)"
													:aria-label="$texts.apiTokens.copyEndpointUrl(endpoint.url)"
													@click="copyEndpointUrl(endpoint.url)">
													<template #icon><ContentCopyIcon :size="18" /></template>
												</NcButton>
											</div>
										</div>
										<div
											v-if="endpoint.parameters.length && isEndpointExpanded(endpoint.key)"
											:id="endpointParamsId(endpoint.key)"
											class="api-token-params-panel">
											<table
												class="bulk-import-table api-token-params-table"
												:class="{ 'api-token-params-table--with-values': hasParamValues(endpoint) }">
												<thead>
													<tr>
														<th scope="col" class="col-field">{{ $texts.bulkImport.fieldColumnName() }}</th>
														<th scope="col" class="col-type">{{ $texts.apiTokens.endpointFieldTypeColumn() }}</th>
														<th scope="col" class="col-required"><span class="hidden-visually">{{ $texts.bulkImport.fieldColumnRequired() }}</span></th>
														<th v-if="hasParamValues(endpoint)" scope="col" class="col-values">{{ $texts.apiTokens.endpointAllowedValuesColumn() }}</th>
														<th scope="col" class="col-notes">{{ $texts.bulkImport.columnDescription() }}</th>
													</tr>
												</thead>
												<tbody>
													<tr v-for="param in endpoint.parameters" :key="`${param.location}:${param.name}`">
														<td :data-label="$texts.bulkImport.fieldColumnName()" class="col-field">
															<code class="bulk-import-field-code">{{ param.name }}</code>
														</td>
														<td :data-label="$texts.apiTokens.endpointFieldTypeColumn()" class="col-type api-token-param-type">
															{{ param.type }}
														</td>
														<td :data-label="$texts.bulkImport.fieldColumnRequired()" class="col-required">
															<span
																:data-cobudget-badge="param.required ? 'info' : 'neutral'"
																data-cobudget-badge-size="compact">
																{{ param.required ? $texts.bulkImport.requiredYes() : $texts.bulkImport.requiredNo() }}
															</span>
														</td>
														<td
															v-if="hasParamValues(endpoint)"
															:data-label="$texts.apiTokens.endpointAllowedValuesColumn()"
															class="col-values api-token-param-values-cell">
															<ul
																v-if="param.values?.length"
																class="api-token-param-values"
																:class="{ 'api-token-param-values--stacked': param.valuesStacked }">
																<li
																	v-for="(item, index) in param.values"
																	:key="`${item.value}:${index}`"
																	class="api-token-param-value-item">
																	<code class="bulk-import-field-code">{{ item.value }}</code>
																	<span v-if="item.label" class="api-token-param-value-label">{{ item.label }}</span>
																</li>
															</ul>
															<span v-else class="api-token-param-value-empty">—</span>
														</td>
														<td :data-label="$texts.bulkImport.columnDescription()" class="col-notes api-token-param-desc">
															{{ param.description }}
														</td>
													</tr>
												</tbody>
											</table>
										</div>
										<div
											v-if="isEndpointExampleExpanded(endpoint.key)"
											:id="endpointExampleId(endpoint.key)"
											class="api-token-example-panel">
											<div class="api-token-example-toolbar">
												<span class="api-token-example-lang">{{ 'curl' }}</span>
												<NcButton
													variant="tertiary"
													class="api-token-example-copy"
													:title="$texts.apiTokens.copyCurlExample()"
													:aria-label="$texts.apiTokens.copyCurlExample()"
													@click="copyEndpointExample(endpoint)">
													<template #icon><ContentCopyIcon :size="16" /></template>
													{{ $texts.common.copy() }}
												</NcButton>
											</div>
											<pre class="api-token-example-code"><code>{{ curlExampleForEndpoint(endpoint) }}</code></pre>
										</div>
									</div>
								</NcFormGroup>
							</div>
						</section>
					</div>
					<div class="api-token-endpoints-actions">
						<NcButton variant="tertiary" @click="closeEndpointsPreview">{{ $texts.common.close() }}</NcButton>
					</div>
				</div>
			</NcModal>
		</DialogTransition>
	</section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import ApiIcon from 'vue-material-design-icons/Api.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ConsoleIcon from 'vue-material-design-icons/Console.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import { downloadTextFile } from '../services/annualOverview'
import axios from '../services/http'
import { extractError, showToast } from '../services/notifications'
import { readWorkspaceId } from '../services/workspaceStorage'
import { buildApiExportFilename, buildCurlExample, buildHttpFile, buildOpenApiSpec } from '../utils/apiExamples'
import { sortAreasByName } from '../utils/areaSorting'
import { getAppLocale } from '../utils/formatMoney'
import { useLocalDate } from '../utils/useLocalDate'
import AreaIcon from './AreaIcon.vue'
import DialogTransition from './DialogTransition.vue'

const VALID_SCOPES = [
	'entries:read',
	'entries:create',
	'entries:write',
	'entries:delete',
	'reports:read',
	'settings:read',
	'settings:write',
]
const TARGET_ACTIONS = ['read', 'write', 'create', 'delete']
const GLOBAL_SCOPES = ['reports:read', 'settings:read', 'settings:write']

function localDateIso(date) {
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${year}-${month}-${day}`
}

function localDatePlusDays(days) {
	const date = new Date()
	date.setDate(date.getDate() + days)
	return localDateIso(date)
}

function timestampToLocalDateIso(timestamp) {
	if (!timestamp) return ''
	const date = new Date(Number(timestamp) * 1000)
	if (Number.isNaN(date.getTime())) return ''
	return localDateIso(date)
}

function legacyCopyText(text) {
	const previousFocus = document.activeElement
	const field = Object.assign(document.createElement('textarea'), { value: text })
	field.setAttribute('readonly', '')
	field.style.cssText = 'position:fixed;inset-block-start:0;opacity:0'
	document.body.append(field)
	field.select()
	try {
		return document.execCommand('copy')
	} finally {
		field.remove()
		previousFocus?.focus?.({ preventScroll: true })
	}
}

export default {
	name: 'ApiTokenSettings',
	components: {
		ApiIcon,
		AreaIcon,
		CheckIcon,
		ChevronDownIcon,
		ConsoleIcon,
		ContentCopyIcon,
		DeleteOutlineIcon,
		DialogTransition,
		DownloadIcon,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcFormBoxSwitch,
		NcFormGroup,
		NcInputField,
		NcLoadingIcon,
		NcModal,
		NcNoteCard,
		NcSelect,
		PencilIcon,
		PlusIcon,
	},
	props: {
		enabled: { type: Boolean, default: false },
		adminEnabled: { type: Boolean, default: true },
		adminLoaded: { type: Boolean, default: true },
		workspacesEnabled: { type: Boolean, default: false },
		areasEnabled: { type: Boolean, default: false },
		settingsBusy: { type: Boolean, default: false },
	},
	setup() {
		return {
			today: useLocalDate(),
		}
	},
	emits: ['toggle-enabled'],
	data() {
		return {
			loading: false,
			loadError: '',
			tableError: '',
			pendingToggle: {},
			workspaces: [],
			areasByWorkspace: {},
			tokens: [],
			editor: null,
			saving: false,
			saveError: '',
			confirmDiscard: false,
			tokenToDelete: null,
			previewTokenId: null,
			expandedEndpoints: {},
			revealedToken: null,
			passwordConfirm: {
				open: false,
				password: '',
				busy: false,
				error: '',
			},
			passwordConfirmResolver: null,
			copySucceeded: false,
			copyTimer: null,
		}
	},
	computed: {
		effectiveEnabled() {
			return this.adminLoaded && this.adminEnabled && this.enabled
		},
		showWorkspaceColumn() {
			return !!this.workspacesEnabled
		},
		previewToken() {
			if (!this.previewTokenId) return null
			return this.tokens.find(item => Number(item.id) === Number(this.previewTokenId)) || null
		},
		previewEndpointGroups() {
			return this.previewToken ? this.endpointsForToken(this.previewToken) : []
		},
		previewAuthHeaderRows() {
			const t = this.$texts.apiTokens
			const rows = [
				{
					key: 'authorization',
					name: 'Authorization',
					badgeLabel: t.endpointsAuthRecommended(),
					recommended: true,
					example: 'Authorization: Bearer <API_KEY>',
					copyValue: 'Authorization: Bearer <API_KEY>',
				},
				{
					key: 'custom-token',
					name: 'X-CoBudget-Token',
					badgeLabel: t.endpointsAuthAlternative(),
					recommended: false,
					example: 'X-CoBudget-Token: <API_KEY>',
					copyValue: 'X-CoBudget-Token: <API_KEY>',
				},
			]
			if (this.previewToken && this.showWorkspaceColumn) {
				const workspaceId = Number(this.previewToken.workspace_id)
				rows.push({
					key: 'workspace',
					name: 'X-Workspace-Id',
					badgeLabel: this.$texts.bulkImport.requiredNo(),
					recommended: false,
					example: `X-Workspace-Id: ${workspaceId}`,
					copyValue: `X-Workspace-Id: ${workspaceId}`,
				})
			}
			return rows
		},
		showWorkspaceField() {
			return !!this.editor && !!this.workspacesEnabled
		},
		currentWorkspaceAreas() {
			if (!this.editor) return []
			return this.areasByWorkspace[String(this.editor.workspaceId)] || []
		},
		activeWorkspaceAreas() {
			if (!this.editor || !this.areasEnabled) return []
			return sortAreasByName(this.currentWorkspaceAreas.filter(area => !area.is_archived))
		},
		dataTargetRows() {
			return [
				{ key: 'personal', areaId: null, name: this.$texts.masterData.noArea(), color: null },
				...this.activeWorkspaceAreas.map(area => ({
					key: String(area.id),
					areaId: Number(area.id),
					name: area.name,
					color: area.color || null,
				})),
			]
		},
		matrixColumns() {
			return [
				{ key: 'read', label: this.$texts.apiTokens.actionRead() },
				{ key: 'write', label: this.$texts.common.edit() },
				{ key: 'create', label: this.$texts.apiTokens.createButton() },
				{ key: 'delete', label: this.$texts.common.delete() },
			]
		},
		selectedWorkspaceOption() {
			if (!this.editor) return null
			return this.workspaces.find(ws => Number(ws.id) === Number(this.editor.workspaceId)) || this.workspaces[0] || null
		},
		canUseReportsScope() {
			if (!this.editor) return false
			return this.dataTargetRows.every(row => this.hasTargetAction(row.key, 'read'))
		},
		generalMatrixRows() {
			return [
				{
					key: 'reports',
					label: this.$texts.apiTokens.reportsAndBudgetsRow(),
					hint: this.canUseReportsScope ? '' : this.$texts.apiTokens.scopeReportsReadBlocked(),
					scopes: { read: 'reports:read' },
				},
				{
					key: 'settings',
					label: this.$texts.settings.settings(),
					hint: '',
					scopes: { read: 'settings:read', write: 'settings:write' },
				},
			]
		},
		expiryOptions() {
			return [
				{ value: 'none', label: this.$texts.apiTokens.expiryNever() },
				{ value: 'date', label: this.$texts.apiTokens.expiryDateField() },
				{ value: 'templates-heading', label: this.$texts.apiTokens.expiryTemplatesHeading(), heading: true },
				{ value: '30d', label: this.$texts.apiTokens.expiryDays(30), template: true },
				{ value: '90d', label: this.$texts.apiTokens.expiryDays(90), template: true },
				{ value: '365d', label: this.$texts.apiTokens.expiryDays(365), template: true },
			]
		},
		selectedExpiryOption() {
			return this.expiryOptions.find(option => !option.heading && option.value === this.editor?.expiryPreset) || this.expiryOptions[0]
		},
		minCustomDate() {
			return localDatePlusDays(1)
		},
		minExpiryDate() {
			if (
				this.editor?.token
				&& this.editor.initialExpiryDate
				&& this.editor.expiryDate === this.editor.initialExpiryDate
				&& this.editor.initialExpiryDate < this.minCustomDate
			) {
				return this.editor.initialExpiryDate
			}
			return this.minCustomDate
		},
		hasChanges() {
			return !!this.editor && this.editor.baseline !== this.draftFingerprint()
		},
		canSave() {
			if (!this.effectiveEnabled || !this.editor) return false
			if (this.editor.token && !this.hasChanges) return false
			const name = String(this.editor.name || '').trim()
			if (!name || name.length > 128) return false
			if (!this.editor.workspaceId) return false
			const hasTargetPermission = Object.values(this.editor.targetPermissions || {}).some(actions => Array.isArray(actions) && actions.length > 0)
			const hasGlobalScope = Array.isArray(this.editor.globalScopes) && this.editor.globalScopes.length > 0
			if (!hasTargetPermission && !hasGlobalScope) return false
			if (this.editor.globalScopes.includes('reports:read') && !this.canUseReportsScope) return false
			if (this.editor.expiryPreset !== 'none') {
				if (!this.editor.expiryDate || this.resolveExpiresAt() === null) return false
			}
			return true
		},
	},
	watch: {
		enabled: {
			immediate: true,
			handler() {
				if (!this.effectiveEnabled && this.editor) this.discardAndClose()
				return this.load()
			},
		},
		adminEnabled(value) {
			if (!value) {
				if (this.editor) this.discardAndClose()
				if (this.previewTokenId) this.closeEndpointsPreview()
			}
		},
	},
	mounted() {
		if (typeof window !== 'undefined') {
			window.addEventListener('workspaces-updated', this.handleExternalChange)
			window.addEventListener('cobudget-data-changed', this.handleExternalChange)
		}
	},
	beforeUnmount() {
		clearTimeout(this.copyTimer)
		this.revealedToken = null
		this.passwordConfirm.password = ''
		this.passwordConfirm.open = false
		if (typeof this.passwordConfirmResolver === 'function') {
			const resolve = this.passwordConfirmResolver
			this.passwordConfirmResolver = null
			resolve(false)
		}
		if (typeof window !== 'undefined') {
			window.removeEventListener('workspaces-updated', this.handleExternalChange)
			window.removeEventListener('cobudget-data-changed', this.handleExternalChange)
		}
	},
	methods: {
		handleExternalChange() {
			if (this.effectiveEnabled || this.tokens.length) this.load({ quiet: true })
		},
		async load({ quiet = false } = {}) {
			if (this.loading) return
			if (!quiet) this.loading = true
			this.loadError = ''
			this.tableError = ''
			try {
				const { data } = await axios.get(generateUrl('/apps/cobudget/api/settings/api-tokens'), { skipWorkspaceHeader: true })
				this.workspaces = Array.isArray(data?.workspaces) ? data.workspaces : []
				this.areasByWorkspace = data?.areas_by_workspace && typeof data.areas_by_workspace === 'object' ? data.areas_by_workspace : {}
				this.tokens = Array.isArray(data?.tokens) ? data.tokens : []
			} catch (error) {
				if (!quiet) this.loadError = extractError(error, this.$texts.apiTokens.loadError())
			} finally {
				if (!quiet) this.loading = false
			}
		},
		isDefaultWorkspace(workspaceId) {
			const ws = this.workspaces.find(item => Number(item.id) === Number(workspaceId))
			return !!ws?.is_default
		},
		workspaceName(workspaceId) {
			return this.workspaces.find(item => Number(item.id) === Number(workspaceId))?.name || this.$texts.workspaces.base()
		},
		formatTokenPreview(token) {
			return `${String(token?.token_prefix || 'cb_')}••••••••`
		},
		formatAuthHeaderPreview(token) {
			return `Authorization: Bearer ${this.formatTokenPreview(token)}`
		},
		formatDateShort(timestamp) {
			return new Intl.DateTimeFormat(getAppLocale(), { dateStyle: 'medium' }).format(new Date(Number(timestamp) * 1000))
		},
		formatDateTime(timestamp) {
			return new Intl.DateTimeFormat(getAppLocale(), { dateStyle: 'short', timeStyle: 'short' }).format(new Date(Number(timestamp) * 1000))
		},
		async toggleTokenActive(token, nextActive) {
			if (!token || this.pendingToggle[token.id] || token.is_expired || (!this.effectiveEnabled && nextActive)) return
			this.pendingToggle[token.id] = true
			this.tableError = ''
			try {
				const useMatrix = token.project_mode === 'matrix' || (!token.project_mode && token.target_permissions && typeof token.target_permissions === 'object')
				const payload = useMatrix
					? {
						name: token.name,
						scopes: GLOBAL_SCOPES.filter(scope => (token.scopes || []).includes(scope)),
						targetPermissions: token.target_permissions || {},
						expiresAt: token.expires_at ?? null,
						isActive: !!nextActive,
					}
					: {
						name: token.name,
						scopes: VALID_SCOPES.filter(scope => (token.scopes || []).includes(scope)),
						allowPersonal: !!token.allow_personal,
						projectMode: token.project_mode || 'none',
						projectIds: token.project_mode === 'selected' && Array.isArray(token.project_ids) ? [...token.project_ids] : [],
						expiresAt: token.expires_at ?? null,
						isActive: !!nextActive,
					}
				payload.revision = token.revision
				const { cancelled, result } = await this.runWithPasswordConfirmation(() => axios.put(
					generateUrl(`/apps/cobudget/api/settings/api-tokens/${token.id}`),
					payload,
					{ skipWorkspaceHeader: true },
				))
				if (cancelled) return
				const { data } = result
				this.tokens = this.tokens.map(item => item.id === data.token.id ? data.token : item)
			} catch (error) {
				if (error?.response?.status === 409) await this.load({ quiet: true })
				this.tableError = extractError(error, this.$texts.apiTokens.saveError())
			} finally {
				delete this.pendingToggle[token.id]
			}
		},
		baseWorkspaceId() {
			return Number((this.workspaces.find(ws => ws.is_default) || this.workspaces[0])?.id || 0)
		},
		defaultWorkspaceId() {
			if (!this.workspacesEnabled) return this.baseWorkspaceId()
			const stored = Number(readWorkspaceId() || 0)
			if (stored && this.workspaces.some(ws => Number(ws.id) === stored)) return stored
			return this.baseWorkspaceId()
		},
		extractTargetPermissions(token, activeAreas) {
			const activeIdSet = new Set(activeAreas.map(area => String(area.id)))
			const matrix = {}
			if (token?.target_permissions && typeof token.target_permissions === 'object') {
				for (const [key, rawActions] of Object.entries(token.target_permissions)) {
					if (key !== 'personal' && !activeIdSet.has(String(key))) continue
					const actions = TARGET_ACTIONS.filter(action => Array.isArray(rawActions) && rawActions.includes(action))
					if (actions.length) matrix[String(key)] = actions
				}
				return matrix
			}

			const actionMap = [
				['entries:read', 'read'],
				['entries:write', 'write'],
				['entries:create', 'create'],
				['entries:delete', 'delete'],
			]
			const legacyActions = actionMap
				.filter(([scope]) => Array.isArray(token?.scopes) && token.scopes.includes(scope))
				.map(([, action]) => action)
			if (!legacyActions.length) return matrix
			if (token?.allow_personal) matrix.personal = [...legacyActions]
			if (token?.project_mode === 'all') {
				for (const area of activeAreas) matrix[String(area.id)] = [...legacyActions]
			} else if (token?.project_mode === 'selected' && Array.isArray(token?.project_ids)) {
				for (const id of token.project_ids) {
					const key = String(id)
					if (activeIdSet.has(key)) matrix[key] = [...legacyActions]
				}
			}
			return matrix
		},
		normalizeTargetPermissionsMap(rawMap) {
			const normalized = {}
			const entries = Object.entries(rawMap || {}).sort(([a], [b]) => {
				if (a === b) return 0
				if (a === 'personal') return -1
				if (b === 'personal') return 1
				return Number(a) - Number(b)
			})
			for (const [key, rawActions] of entries) {
				const actions = TARGET_ACTIONS.filter(action => Array.isArray(rawActions) && rawActions.includes(action))
				if (actions.length) normalized[key] = actions
			}
			return normalized
		},
		openEndpointsPreview(token) {
			if (!token) return
			this.expandedEndpoints = {}
			this.previewTokenId = Number(token.id)
		},
		closeEndpointsPreview() {
			this.previewTokenId = null
			this.expandedEndpoints = {}
		},
		endpointParamsId(key) {
			return `api-token-params-${String(key || '').replace(/[^a-zA-Z0-9_-]+/g, '-')}`
		},
		endpointExampleId(key) {
			return `api-token-example-${String(key || '').replace(/[^a-zA-Z0-9_-]+/g, '-')}`
		},
		isEndpointExpanded(key) {
			return this.expandedEndpoints[key] === 'params'
		},
		isEndpointExampleExpanded(key) {
			return this.expandedEndpoints[key] === 'example'
		},
		hasParamValues(endpoint) {
			return Array.isArray(endpoint?.parameters)
				&& endpoint.parameters.some(param => Array.isArray(param.values) && param.values.length > 0)
		},
		toggleEndpointPanel(key, panel) {
			this.expandedEndpoints = {
				...this.expandedEndpoints,
				[key]: this.expandedEndpoints[key] === panel ? null : panel,
			}
		},
		toggleEndpointParams(key) {
			this.toggleEndpointPanel(key, 'params')
		},
		toggleEndpointExample(key) {
			this.toggleEndpointPanel(key, 'example')
		},
		apiExampleContext() {
			const token = this.previewToken
			const bi = this.$texts.bulkImport
			return {
				tokenName: token ? String(token.name || '') : '',
				workspaceId: token ? Number(token.workspace_id) : null,
				workspacesEnabled: !!this.workspacesEnabled,
				today: this.today,
				exampleCategory: bi.exampleGroceries(),
				examplePartner: bi.exampleSupermarket(),
				exampleDescription: bi.exampleDescription(),
			}
		},
		curlExampleForEndpoint(endpoint) {
			return buildCurlExample(endpoint, this.apiExampleContext())
		},
		apiBaseUrl() {
			const origin = typeof window !== 'undefined' && window.location?.origin ? String(window.location.origin).replace(/\/+$/, '') : ''
			const basePath = String(generateUrl('/apps/cobudget/api/v1') || '/apps/cobudget/api/v1').replace(/\/+$/, '')
			return `${origin}${basePath}`
		},
		endpointsForToken(token) {
			if (!token) return []
			const base = this.apiBaseUrl()
			const t = this.$texts.apiTokens
			const activeAreas = this.areasEnabled
				? sortAreasByName((this.areasByWorkspace[String(token.workspace_id)] || []).filter(area => !area.is_archived))
				: []
			const targetPermissions = this.extractTargetPermissions(token, activeAreas)
			const scopes = Array.isArray(token.scopes) ? token.scopes : []

			const targetCatalog = [
				{ key: 'personal', name: this.$texts.masterData.noArea(), projectId: 0, color: null },
				...activeAreas.map(area => ({
					key: String(area.id),
					name: area.name,
					projectId: Number(area.id),
					color: area.color || null,
				})),
			]
			const hasActionForTarget = (targetKey, action) => (targetPermissions[targetKey] || []).includes(action)
			const targetsForAction = action => targetCatalog.filter(target => hasActionForTarget(target.key, action))

			const anyPaymentTargets = targetCatalog.filter(target => (targetPermissions[target.key] || []).length > 0)
			const anyAreaTargets = anyPaymentTargets.filter(target => target.key !== 'personal')
			const hasPersonalTarget = !!targetPermissions.personal?.length

			const toValueItems = values => values.map(value => ({ value: String(value), label: '' }))
			const targetsToValueItems = list => list.map(target => ({ value: String(target.projectId), label: target.name }))

			const buildEndpoint = ({ method, url, description, note = '', parameters = [], paramToggleLabel = t.endpointParamsToggle() }) => ({
				key: `${method}:${url}`,
				method,
				url,
				description,
				note,
				parameters,
				paramToggleLabel,
			})

			const bi = this.$texts.bulkImport
			const labels = this.$texts.labels
			const enabledLabelFields = [
				{ name: 'important', label: labels.important(), enabled: this.$enableImportantPayments },
				{ name: 'review', label: labels.review(), enabled: this.$enableReviewPayments },
				{ name: 'fixed_cost', label: labels.fixedCosts(), enabled: this.$enableFixedCosts, expenseOnly: true },
				{ name: 'children', label: labels.children(), enabled: this.$enableChildRelated },
				{ name: 'subscription', label: labels.subscription(), enabled: this.$enableSubscriptions, expenseOnly: true },
				{ name: 'tax_relevant', label: labels.taxRelevant(), enabled: this.$enableTaxRelevant },
				{ name: 'insurance', label: labels.insurance(), enabled: this.$enableInsurance },
			].filter(field => field.enabled !== false)
			const bodyParamsForEntryWrite = (isCreate, allowedTargets = []) => {
				const canCreatePersonal = hasActionForTarget('personal', 'create')
				const targetValues = targetsToValueItems(allowedTargets)
				const params = [
					{ name: 'date', location: 'body', type: 'string | int', required: isCreate, description: bi.fieldDateDesc(this.today) },
					{ name: 'amount', location: 'body', type: 'number', required: isCreate, description: bi.fieldAmountDesc() },
				]
				if (isCreate) {
					params.push({
						name: 'project_id',
						location: 'body',
						type: canCreatePersonal ? '?int' : 'int',
						required: !canCreatePersonal,
						values: targetValues,
						valuesStacked: true,
						description: canCreatePersonal ? t.paramProjectIdCreate() : t.paramProjectIdCreateRequired(),
					})
				} else {
					params.push({
						name: 'project_id',
						location: 'body',
						type: '?int',
						required: false,
						values: targetValues,
						valuesStacked: true,
						description: t.paramProjectIdPatch(),
					})
				}
				params.push(
					{ name: 'category', location: 'body', type: '?string', required: false, description: bi.fieldCategoryDesc() },
					{ name: 'payment_partner', location: 'body', type: '?string', required: false, description: bi.fieldPartnerDesc() },
					{ name: 'description', location: 'body', type: '?string', required: false, description: bi.fieldDescriptionDesc() },
					...enabledLabelFields.map(field => ({
						name: field.name,
						location: 'body',
						type: '0 / 1 / bool',
						required: false,
						values: toValueItems(['0', '1', 'true', 'false']),
						description: field.expenseOnly ? bi.expenseLabelHint(field.label) : bi.labelHint(field.label),
					})),
					{ name: 'currency', location: 'body', type: 'string', required: false, description: t.paramCurrencyWrite() },
					{ name: 'user_id', location: 'body', type: 'string', required: false, description: t.paramPayerWrite() },
					{ name: 'split_mode', location: 'body', type: 'string', required: false, values: toValueItems(['project_shares', 'single_user']), description: t.paramSplitModeWrite() },
					{ name: 'split_user_id', location: 'body', type: '?string', required: false, description: t.paramSplitUserWrite() },
					{ name: 'recurrence_interval', location: 'body', type: '?string', required: false, values: toValueItems(['day', 'week', 'month', 'daily', 'weekly', 'monthly', 'yearly', 'null']), description: t.paramRecurrenceInterval() },
					{ name: 'recurrence_multiplier', location: 'body', type: '?int', required: false, values: toValueItems(['1–1000']), description: t.paramRecurrenceMultiplier() },
					{ name: 'recurrence_next_date', location: 'body', type: '?string | ?int', required: false, description: t.paramRecurrenceNextDate() },
					{ name: 'recurrence_end_date', location: 'body', type: '?string | ?int', required: false, description: t.paramRecurrenceEndDate() },
					{ name: 'reminder_date', location: 'body', type: '?string | ?int', required: false, description: t.paramReminderDate() },
					{ name: 'reminder_text', location: 'body', type: '?string', required: false, description: t.paramReminderText() },
				)
				return params
			}

			const groups = [
				{
					key: 'account',
					title: t.endpointsGroupAccount(),
					endpoints: [
						buildEndpoint({ method: 'GET', url: `${base}/me`, description: t.endpointDescMe() }),
					],
				},
			]

			const lookupEndpoints = []
			if (anyAreaTargets.length > 0) {
				lookupEndpoints.push(buildEndpoint({
					method: 'GET',
					url: `${base}/areas`,
					description: t.endpointDescAreas(),
				}))
			}
			if (anyPaymentTargets.length > 0) {
				const querySuffix = hasPersonalTarget ? '' : '?project_id={id}'
				const areaNote = hasPersonalTarget ? '' : t.endpointsAreaIdRequiredHint()
				const lookupParams = [
					{
						name: 'project_id',
						location: 'query',
						type: 'int',
						required: !hasPersonalTarget,
						values: targetsToValueItems(anyPaymentTargets),
						valuesStacked: true,
						description: hasPersonalTarget ? t.paramProjectIdLookup() : t.paramProjectIdLookupRequired(),
					},
				]
				lookupEndpoints.push(
					buildEndpoint({
						method: 'GET',
						url: `${base}/categories${querySuffix}`,
						description: t.endpointDescCategories(),
						note: areaNote,
						parameters: lookupParams,
					}),
					buildEndpoint({
						method: 'GET',
						url: `${base}/payment-partners${querySuffix}`,
						description: t.endpointDescPartners(),
						note: areaNote,
						parameters: lookupParams,
					}),
				)
			}
			if (lookupEndpoints.length > 0) {
				groups.push({ key: 'lookups', title: t.endpointsGroupLookups(), endpoints: lookupEndpoints })
			}

			const paymentEndpoints = []
			const readTargets = targetsForAction('read')
			if (readTargets.length > 0) {
				paymentEndpoints.push(
					buildEndpoint({
						method: 'GET',
						url: `${base}/entries`,
						description: t.endpointDescEntriesList(),
						parameters: [
							{ name: 'project_id', location: 'query', type: 'int', required: false, values: targetsToValueItems(readTargets), valuesStacked: true, description: t.paramProjectIdEntriesFilter() },
							{ name: 'scope', location: 'query', type: 'string', required: false, values: toValueItems(['canonical', 'personal']), description: t.paramScope() },
							{ name: 'type', location: 'query', type: 'string', required: false, values: toValueItems(['all', 'expense', 'income']), description: t.paramTypeFilter() },
							{ name: 'date_from', location: 'query', type: 'int', required: false, description: t.paramDateFrom() },
							{ name: 'date_to', location: 'query', type: 'int', required: false, description: t.paramDateTo() },
							{ name: 'category_id', location: 'query', type: 'int', required: false, description: t.paramCategoryFilter() },
							{ name: 'payment_partner_id', location: 'query', type: 'int', required: false, description: t.paramPartnerFilter() },
							{ name: 'search', location: 'query', type: 'string', required: false, description: t.paramSearch() },
							{ name: 'include_future', location: 'query', type: 'bool', required: false, values: toValueItems(['true', 'false']), description: t.paramIncludeFuture() },
							{ name: 'limit', location: 'query', type: 'int', required: false, values: toValueItems(['1–250']), description: t.paramLimit() },
							{ name: 'offset', location: 'query', type: 'int', required: false, values: toValueItems(['0–1000000']), description: t.paramOffset() },
							{ name: 'sort_by', location: 'query', type: 'string', required: false, values: toValueItems(['date', 'amount', 'id']), description: t.paramSortBy() },
							{ name: 'sort_dir', location: 'query', type: 'string', required: false, values: toValueItems(['desc', 'asc']), description: t.paramSortDir() },
						],
					}),
					buildEndpoint({
						method: 'GET',
						url: `${base}/entries/{id}`,
						description: t.endpointDescEntriesShow(),
						parameters: [
							{ name: 'id', location: 'path', type: 'int', required: true, description: t.paramEntryId() },
						],
					}),
				)
			}
			const createTargets = targetsForAction('create')
			if (createTargets.length > 0) {
				paymentEndpoints.push(buildEndpoint({
					method: 'POST',
					url: `${base}/entries`,
					description: t.endpointDescEntriesCreate(),
					paramToggleLabel: t.endpointBodyToggle(),
					parameters: bodyParamsForEntryWrite(true, createTargets),
				}))
			}
			const writeTargets = targetsForAction('write')
			if (writeTargets.length > 0) {
				paymentEndpoints.push(buildEndpoint({
					method: 'PATCH',
					url: `${base}/entries/{id}`,
					description: t.endpointDescEntriesUpdate(),
					paramToggleLabel: t.endpointBodyToggle(),
					parameters: [
						{ name: 'id', location: 'path', type: 'int', required: true, description: t.paramEntryId() },
						...bodyParamsForEntryWrite(false, writeTargets),
					],
				}))
			}
			const deleteTargets = targetsForAction('delete')
			if (deleteTargets.length > 0) {
				paymentEndpoints.push(buildEndpoint({
					method: 'DELETE',
					url: `${base}/entries/{id}`,
					description: t.endpointDescEntriesDelete(),
					parameters: [
						{ name: 'id', location: 'path', type: 'int', required: true, description: t.paramEntryId() },
					],
				}))
			}
			if (paymentEndpoints.length > 0) {
				groups.push({ key: 'payments', title: t.endpointsGroupPayments(), endpoints: paymentEndpoints })
			}

			const canUseReports = scopes.includes('reports:read')
				&& hasActionForTarget('personal', 'read')
				&& activeAreas.every(area => hasActionForTarget(String(area.id), 'read'))
			if (canUseReports) {
				groups.push({
					key: 'reports',
					title: t.endpointsGroupReports(),
					endpoints: [
						buildEndpoint({
							method: 'GET',
							url: `${base}/reports/summary`,
							description: t.endpointDescReportsSummary(),
							parameters: [
								{ name: 'period', location: 'query', type: 'string', required: false, values: toValueItems(['current-month', 'last-month', 'current-year', 'last-year']), description: t.paramReportPeriod() },
								{ name: 'date_from', location: 'query', type: 'int', required: false, description: t.paramReportDateFrom() },
								{ name: 'date_to', location: 'query', type: 'int', required: false, description: t.paramReportDateTo() },
								{ name: 'include_future', location: 'query', type: 'bool', required: false, values: toValueItems(['true', 'false']), description: t.paramIncludeFuture() },
							],
						}),
						buildEndpoint({
							method: 'GET',
							url: `${base}/reports/annual`,
							description: t.endpointDescReportsAnnual(),
							parameters: [
								{ name: 'year', location: 'query', type: 'int', required: false, values: toValueItems(['1970–2099']), description: t.paramReportYear() },
								{ name: 'include_future', location: 'query', type: 'bool', required: false, values: toValueItems(['true', 'false']), description: t.paramIncludeFuture() },
							],
						}),
						buildEndpoint({ method: 'GET', url: `${base}/budgets`, description: t.endpointDescBudgets() }),
					],
				})
			}

			const settingsEndpoints = []
			if (scopes.includes('settings:read')) {
				settingsEndpoints.push(buildEndpoint({ method: 'GET', url: `${base}/settings`, description: t.endpointDescSettingsGet() }))
			}
			if (scopes.includes('settings:write')) {
				settingsEndpoints.push(buildEndpoint({
					method: 'PATCH',
					url: `${base}/settings`,
					description: t.endpointDescSettingsPatch(),
					paramToggleLabel: t.endpointBodyToggle(),
					parameters: [
						{ name: 'currency', location: 'body', type: 'string', required: false, description: t.paramSettingCurrency() },
						{ name: 'default_start_page', location: 'body', type: 'string', required: false, values: toValueItems(['personal', 'projects', 'project:<id>']), description: t.paramSettingStartPage() },
						{ name: 'entries_per_page', location: 'body', type: 'int', required: false, values: toValueItems(['10', '25', '50', '100', '250']), description: t.paramSettingPageSize() },
						{ name: 'theme_mode', location: 'body', type: 'string', required: false, values: toValueItems(['auto', 'light', 'dark']), description: t.paramSettingTheme() },
						{ name: 'enable_*, show_*, notify_*', location: 'body', type: 'bool', required: false, values: toValueItems(['true', 'false']), description: t.paramSettingBooleans() },
					],
				}))
			}
			if (settingsEndpoints.length > 0) {
				groups.push({ key: 'settings', title: t.endpointsGroupSettings(), endpoints: settingsEndpoints })
			}

			return groups
		},
		async copyTextWithToast(text, successMsg, errorMsg) {
			const value = String(text || '')
			if (!value) return
			try {
				await navigator.clipboard.writeText(value)
				showToast(successMsg, 'success')
			} catch {
				try {
					if (!legacyCopyText(value)) throw new Error('Copy failed')
					showToast(successMsg, 'success')
				} catch {
					showToast(errorMsg, 'error')
				}
			}
		},
		copyEndpointUrl(url) {
			return this.copyTextWithToast(
				url,
				this.$texts.apiTokens.endpointCopied(),
				this.$texts.apiTokens.endpointCopyError(),
			)
		},
		copyAuthHeader(headerValue) {
			return this.copyTextWithToast(
				headerValue,
				this.$texts.apiTokens.authHeaderCopied(),
				this.$texts.apiTokens.authHeaderCopyError(),
			)
		},
		copyEndpointExample(endpoint) {
			return this.copyTextWithToast(
				this.curlExampleForEndpoint(endpoint),
				this.$texts.apiTokens.exampleCopied(),
				this.$texts.apiTokens.exampleCopyError(),
			)
		},
		downloadOpenApiSpec() {
			const token = this.previewToken
			if (!token) return
			const spec = buildOpenApiSpec({
				...this.apiExampleContext(),
				version: 'v1',
				groups: this.previewEndpointGroups,
			})
			downloadTextFile(
				buildApiExportFilename(token.name, 'json'),
				`${JSON.stringify(spec, null, 2)}\n`,
				'application/json;charset=utf-8',
			)
		},
		downloadHttpFile() {
			const token = this.previewToken
			if (!token) return
			const contents = buildHttpFile({
				...this.apiExampleContext(),
				groups: this.previewEndpointGroups,
			})
			downloadTextFile(
				buildApiExportFilename(token.name, 'http'),
				contents,
				'text/plain;charset=utf-8',
			)
		},
		openEditor(token = null) {
			if (!this.effectiveEnabled || (token && this.pendingToggle[token.id])) return
			this.saveError = ''
			this.tableError = ''
			this.confirmDiscard = false
			this.tokenToDelete = null
			const workspaceId = token ? Number(token.workspace_id) : this.defaultWorkspaceId()
			const activeAreas = this.areasEnabled
				? sortAreasByName((this.areasByWorkspace[String(workspaceId)] || []).filter(area => !area.is_archived))
				: []
			const targetPermissions = token
				? this.extractTargetPermissions(token, activeAreas)
				: { personal: ['read'] }
			const globalScopes = token && Array.isArray(token.scopes)
				? GLOBAL_SCOPES.filter(scope => token.scopes.includes(scope))
				: []
			const initialExpiryDate = token?.expires_at ? timestampToLocalDateIso(token.expires_at) : ''
			const draft = {
				token,
				name: token ? String(token.name || '') : '',
				workspaceId,
				targetPermissions,
				globalScopes,
				expiryPreset: initialExpiryDate ? 'date' : 'none',
				expiryDate: initialExpiryDate,
				initialExpiryDate,
				isActive: token ? !!token.is_active : true,
			}
			this.editor = draft
			this.editor.baseline = this.draftFingerprint()
		},
		draftFingerprint() {
			if (!this.editor) return ''
			const globalScopes = GLOBAL_SCOPES.filter(scope => (this.editor.globalScopes || []).includes(scope))
			const targetEntries = Object.entries(this.normalizeTargetPermissionsMap(this.editor.targetPermissions))
			const effectiveExpiryDate = this.editor.expiryPreset === 'none' ? '' : String(this.editor.expiryDate || '')
			return JSON.stringify([
				this.editor.name.trim(),
				Number(this.editor.workspaceId),
				targetEntries,
				globalScopes,
				effectiveExpiryDate,
				!!this.editor.isActive,
			])
		},
		closeEditor() {
			if (this.saving || this.confirmDiscard || this.tokenToDelete) return
			if (this.hasChanges) {
				this.confirmDiscard = true
				return
			}
			this.discardAndClose()
		},
		discardAndClose() {
			if (this.saving) return
			this.editor = null
			this.saveError = ''
			this.confirmDiscard = false
			this.tokenToDelete = null
		},
		closeDeleteConfirmation() {
			if (!this.saving) this.tokenToDelete = null
		},
		changeWorkspace(option) {
			if (!this.editor || this.editor.token || !option || this.saving) return
			const nextId = Number(option.id)
			if (nextId === Number(this.editor.workspaceId)) return
			this.editor.workspaceId = nextId
			const personalActions = Array.isArray(this.editor.targetPermissions?.personal)
				? [...this.editor.targetPermissions.personal]
				: []
			this.editor.targetPermissions = personalActions.length ? { personal: personalActions } : {}
			this.onTargetScopeChange()
		},
		hasTargetAction(targetKey, action) {
			if (!this.editor) return false
			return !!(this.editor.targetPermissions?.[String(targetKey)] || []).includes(action)
		},
		toggleTargetAction(targetKey, action, checked) {
			if (!this.editor || this.saving) return
			const key = String(targetKey)
			const next = { ...(this.editor.targetPermissions || {}) }
			const set = new Set(Array.isArray(next[key]) ? next[key] : [])
			if (checked) set.add(action)
			else set.delete(action)
			const ordered = TARGET_ACTIONS.filter(item => set.has(item))
			if (ordered.length) next[key] = ordered
			else delete next[key]
			this.editor.targetPermissions = next
			this.onTargetScopeChange()
		},
		onTargetScopeChange() {
			if (!this.editor) return
			if (!this.canUseReportsScope && this.editor.globalScopes.includes('reports:read')) {
				this.editor.globalScopes = this.editor.globalScopes.filter(scope => scope !== 'reports:read')
			}
		},
		toggleGlobalScope(scope, checked) {
			if (!this.editor || this.saving) return
			if (!GLOBAL_SCOPES.includes(scope)) return
			if (scope === 'reports:read' && checked && !this.canUseReportsScope) return
			const set = new Set(this.editor.globalScopes)
			if (checked) set.add(scope)
			else set.delete(scope)
			this.editor.globalScopes = GLOBAL_SCOPES.filter(item => set.has(item))
		},
		changeExpiryPreset(option) {
			if (!this.editor || !option || option.heading || this.saving) return
			const value = option.value
			this.editor.expiryPreset = value
			if (value === 'none') {
				this.editor.expiryDate = ''
				return
			}
			if (value === '30d' || value === '90d' || value === '365d') {
				this.editor.expiryDate = localDatePlusDays(Number(value.slice(0, -1)))
				return
			}
			if (value === 'date' && !this.editor.expiryDate) {
				this.editor.expiryDate = this.editor.initialExpiryDate || this.minCustomDate
			}
		},
		changeExpiryDate(value) {
			if (!this.editor || this.saving || this.editor.expiryPreset === 'none') return
			const nextDate = String(value || '')
			if (nextDate === this.editor.expiryDate) return
			this.editor.expiryDate = nextDate
			if (this.editor.expiryPreset !== 'date') {
				this.editor.expiryPreset = 'date'
			}
		},
		resolveExpiresAt() {
			if (!this.editor || this.editor.expiryPreset === 'none') return null
			const rawDate = String(this.editor.expiryDate || '')
			if (this.editor.token && this.editor.initialExpiryDate && rawDate === this.editor.initialExpiryDate) {
				return this.editor.token.expires_at ?? null
			}
			const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(rawDate)
			if (!match || Number(match[1]) > 2099) return null
			const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 23, 59, 59)
			if (Number.isNaN(date.getTime())) return null
			const ts = Math.min(Math.floor(date.getTime() / 1000), 4102444800)
			return ts > Math.floor(Date.now() / 1000) ? ts : null
		},
		isPasswordConfirmationRequired(error) {
			if (error?.response?.status !== 403) return false
			const headers = error.response.headers
			if (!headers || typeof headers !== 'object') return false
			const check = name => {
				const direct = typeof headers.get === 'function' ? headers.get(name) : undefined
				if (direct !== undefined && direct !== null) return String(direct).toLowerCase() === 'true'
				const matchKey = Object.keys(headers).find(key => key.toLowerCase() === name)
				return matchKey ? String(headers[matchKey]).toLowerCase() === 'true' : false
			}
			return check('x-nc-auth-notconfirmed') || check('x-nextcloud-password-confirmation')
		},
		requestPasswordConfirmation() {
			if (typeof this.passwordConfirmResolver === 'function') {
				const prev = this.passwordConfirmResolver
				this.passwordConfirmResolver = null
				prev(false)
			}
			this.passwordConfirm = {
				open: true,
				password: '',
				busy: false,
				error: '',
			}
			return new Promise(resolve => {
				this.passwordConfirmResolver = resolve
			})
		},
		cancelPasswordConfirmation() {
			if (this.passwordConfirm.busy) return
			this.passwordConfirm.password = ''
			this.passwordConfirm.error = ''
			this.passwordConfirm.open = false
			if (typeof this.passwordConfirmResolver === 'function') {
				const resolve = this.passwordConfirmResolver
				this.passwordConfirmResolver = null
				resolve(false)
			}
		},
		async submitPasswordConfirmation() {
			if (!this.passwordConfirm.open || this.passwordConfirm.busy || !this.passwordConfirm.password) return
			this.passwordConfirm.busy = true
			this.passwordConfirm.error = ''
			try {
				const { data } = await axios.post(
					generateUrl('/login/confirm'),
					{ password: this.passwordConfirm.password },
					{ skipWorkspaceHeader: true },
				)
				if (typeof window !== 'undefined' && data?.lastLogin) {
					window.nc_lastLogin = Number(data.lastLogin)
				}
				this.passwordConfirm.password = ''
				this.passwordConfirm.open = false
				if (typeof this.passwordConfirmResolver === 'function') {
					const resolve = this.passwordConfirmResolver
					this.passwordConfirmResolver = null
					resolve(true)
				}
			} catch (error) {
				this.passwordConfirm.password = ''
				if (error?.response?.status === 403) {
					this.passwordConfirm.error = this.$texts.apiTokens.passwordConfirmInvalid()
				} else {
					this.passwordConfirm.error = extractError(error, this.$texts.apiTokens.passwordConfirmError())
				}
			} finally {
				this.passwordConfirm.busy = false
			}
		},
		async runWithPasswordConfirmation(actionFn) {
			try {
				return { cancelled: false, result: await actionFn() }
			} catch (error) {
				if (!this.isPasswordConfirmationRequired(error)) {
					throw error
				}
				const confirmed = await this.requestPasswordConfirmation()
				if (!confirmed) {
					return { cancelled: true, result: null }
				}
				return { cancelled: false, result: await actionFn() }
			}
		},
		async save() {
			if (!this.editor || this.saving || !this.canSave) return
			this.saving = true
			this.saveError = ''
			const isEdit = !!this.editor.token
			const targetPermissions = this.normalizeTargetPermissionsMap(this.editor.targetPermissions)
			const payload = {
				name: this.editor.name.trim(),
				scopes: GLOBAL_SCOPES.filter(scope => this.editor.globalScopes.includes(scope)),
				targetPermissions,
				expiresAt: this.resolveExpiresAt(),
			}
			try {
				if (isEdit) {
					payload.isActive = !!this.editor.isActive
					payload.revision = this.editor.token.revision
					const { cancelled, result } = await this.runWithPasswordConfirmation(() => axios.put(
						generateUrl(`/apps/cobudget/api/settings/api-tokens/${this.editor.token.id}`),
						payload,
						{ skipWorkspaceHeader: true },
					))
					if (cancelled) return
					const { data } = result
					this.tokens = this.tokens.map(item => item.id === data.token.id ? data.token : item)
					this.editor = null
					showToast(this.$texts.apiTokens.savedNotice(), 'success')
				} else {
					payload.workspaceId = Number(this.workspacesEnabled ? this.editor.workspaceId : this.baseWorkspaceId())
					const { cancelled, result } = await this.runWithPasswordConfirmation(() => axios.post(
						generateUrl('/apps/cobudget/api/settings/api-tokens'),
						payload,
						{ skipWorkspaceHeader: true },
					))
					if (cancelled) return
					const { data } = result
					this.tokens = [data.token, ...this.tokens]
					this.editor = null
					this.copySucceeded = false
					this.revealedToken = {
						name: data.token.name,
						plainToken: data.plain_token,
					}
				}
			} catch (error) {
				if (error?.response?.status === 409) await this.load({ quiet: true })
				this.saveError = extractError(error, this.$texts.apiTokens.saveError())
			} finally {
				this.saving = false
			}
		},
		async revokeToken() {
			if (!this.tokenToDelete || this.saving) return
			this.saving = true
			this.saveError = ''
			const id = this.tokenToDelete.id
			try {
				const { cancelled } = await this.runWithPasswordConfirmation(() => axios.delete(
					generateUrl(`/apps/cobudget/api/settings/api-tokens/${id}`),
					{ skipWorkspaceHeader: true },
				))
				if (cancelled) return
				this.tokens = this.tokens.filter(item => item.id !== id)
				this.editor = null
				this.tableError = ''
				showToast(this.$texts.apiTokens.deleteSuccess(), 'success')
			} catch (error) {
				const message = extractError(error, this.$texts.apiTokens.deleteError())
				if (this.editor) this.saveError = message
				else this.tableError = message
			} finally {
				this.tokenToDelete = null
				this.saving = false
			}
		},
		closeRevealedToken() {
			clearTimeout(this.copyTimer)
			this.copySucceeded = false
			this.revealedToken = null
		},
		reportCopyOutcome(ok) {
			clearTimeout(this.copyTimer)
			this.copySucceeded = ok
			if (ok) {
				this.copyTimer = setTimeout(() => {
					this.copySucceeded = false
				}, 2000)
			}
		},
		async copyPlainToken() {
			const text = String(this.revealedToken?.plainToken || '')
			if (!text) return
			try {
				await navigator.clipboard.writeText(text)
				this.reportCopyOutcome(true)
				showToast(this.$texts.apiTokens.copySuccess(), 'success')
			} catch {
				try {
					if (!legacyCopyText(text)) throw new Error('Copy failed')
					this.reportCopyOutcome(true)
					showToast(this.$texts.apiTokens.copySuccess(), 'success')
				} catch {
					this.reportCopyOutcome(false)
					showToast(this.$texts.apiTokens.copyError(), 'error')
				}
			}
		},
	},
}
</script>

<style scoped src="../styles/bulk-import-tables.css"></style>

<style scoped>
.api-token-settings {
	container-type: inline-size;
}
.api-token-settings > h3 {
	margin-block-start: 0;
}
.settings-hint {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	margin-top: 0;
	margin-bottom: var(--cobudget-section-gap, 24px);
}
.api-token-master-toggle {
	margin-bottom: var(--cobudget-section-gap, 24px);
}
.api-token-heading {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	margin-block: calc(var(--default-grid-baseline, 4px) * 4) calc(var(--default-grid-baseline, 4px) * 2);
}
.api-token-heading h4 {
	margin: 0;
	font-size: var(--cobudget-font-subheading);
	font-weight: var(--cobudget-font-weight-heading);
}
.api-token-table {
	width: 100%;
	table-layout: fixed;
	border-collapse: collapse;
	color: var(--cobudget-text);
}
.api-token-table th,
.api-token-table td {
	padding: 8px 6px;
	text-align: start;
	vertical-align: middle;
	border-bottom: 1px solid var(--cobudget-border);
	overflow-wrap: anywhere;
	white-space: normal;
}
.api-token-table th {
	font-weight: var(--cobudget-font-weight-label);
}
.api-token-table thead th {
	color: var(--cobudget-text-muted);
}
.api-token-table tbody tr.api-token-editable-row {
	cursor: pointer;
	transition: background-color var(--cobudget-hover-duration) linear;
}
.api-token-table tbody tr.api-token-editable-row > :not(.api-token-control) :is(span, small, code) {
	cursor: pointer;
}
.api-token-table tbody tr.api-token-editable-row:hover,
.api-token-table tbody tr.api-token-editable-row:focus-within {
	background-color: var(--color-background-hover, var(--cobudget-surface-muted));
}
.api-token-table tbody tr.api-token-editable-row:focus-within {
	transition: none;
}
.api-token-table tbody tr.api-token-empty-row,
.api-token-table tbody tr.api-token-empty-row:hover,
.api-token-table tbody tr.api-token-empty-row:focus,
.api-token-table tbody tr.api-token-empty-row:active {
	background-color: transparent;
}
.api-token-table td.api-token-empty-cell {
	padding-block: 14px;
	text-align: center;
	color: var(--cobudget-text-muted);
}
@container (width > 820px) {
	.api-token-table thead th:nth-child(2) {
		width: 22%;
	}
	.api-token-table thead th:nth-child(3) {
		width: 18%;
	}
	.api-token-table .api-token-control {
		width: calc(var(--cobudget-icon-button-size) + 16px);
		box-sizing: border-box;
		text-align: center;
	}
	.api-token-table thead th:nth-child(5) {
		width: 16%;
		white-space: nowrap;
	}
	.api-token-table .api-token-actions-cell {
		width: calc(var(--cobudget-icon-button-size) * 2 + 12px);
		padding-inline: 4px;
		text-align: end;
		white-space: nowrap;
	}
	.api-token-row-actions {
		display: inline-flex;
		align-items: center;
		justify-content: flex-end;
		gap: 4px;
	}
	.api-token-control > :deep(:is(.checkbox-radio-switch, .button-vue)) {
		margin-inline: auto;
	}
	.api-token-active :deep(.checkbox-radio-switch) {
		justify-content: center;
	}
	.api-token-active :deep(.checkbox-content-switch) {
		padding-inline: 0;
		gap: 0;
	}
}
@container (max-width: 820px) {
	.api-token-active :deep(.checkbox-content-switch) {
		padding-inline-start: 0;
	}
	.api-token-active :deep(.checkbox-content__icon) {
		margin-inline-start: -3px;
	}
	.api-token-table,
	.api-token-table tbody,
	.api-token-table tr {
		display: block;
	}
	.api-token-table thead {
		display: none;
	}
	.api-token-table tr:not(.api-token-empty-row) {
		padding-block: 8px;
		border-block-end: 1px solid var(--cobudget-border);
	}
	.api-token-table tbody th {
		display: block;
		padding: 4px 0 8px;
		border: 0;
	}
	.api-token-table td:not(.api-token-empty-cell) {
		display: grid;
		grid-template-columns: minmax(6rem, 36%) minmax(0, 1fr);
		gap: 12px;
		align-items: center;
		padding: 5px 0;
		border: 0;
	}
	.api-token-table td:not(.api-token-empty-cell)::before {
		content: attr(data-label);
		color: var(--cobudget-text-muted);
	}
	.api-token-control > :deep(.button-vue),
	.api-token-row-actions {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		justify-self: start;
	}
}
.api-token-name-cell {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}
.api-token-name {
	font-weight: var(--cobudget-font-weight-label, 500);
}
.api-token-workspace-meta {
	font-size: var(--cobudget-font-caption, 12px);
	font-weight: normal;
	color: var(--cobudget-text-muted);
}
.api-token-preview {
	display: inline-block;
	padding: 2px 8px;
	border-radius: var(--border-radius, 6px);
	background: color-mix(in srgb, var(--cobudget-text) 6%, transparent);
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	font-size: var(--cobudget-font-sm, 13px);
	color: var(--cobudget-text);
	white-space: nowrap;
}
.api-token-expiry-cell {
	display: inline-flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 6px;
}
.api-token-muted {
	color: var(--cobudget-text-muted);
}
.api-token-active-switch {
	margin-block-end: calc(var(--default-grid-baseline, 4px) * 2);
}
.api-token-validity {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: calc(var(--default-grid-baseline, 4px) * 3);
	align-items: start;
	min-width: 0;
}
.api-token-validity-field {
	min-width: 0;
}
.api-token-validity-field :deep(.input-field__main-wrapper) {
	padding: 0;
}
:global(#vs-cobudget-api-token-expiry__listbox .vs__dropdown-option:has(.api-token-expiry-group-heading)) {
	margin-block-start: 4px;
	padding-block: 6px 2px;
	border-top: 1px solid var(--cobudget-border);
	border-radius: 0 !important;
	background: transparent;
	color: var(--cobudget-text-muted);
	font-size: var(--cobudget-font-caption, 12px);
	font-weight: var(--cobudget-font-weight-label);
	cursor: default;
}
:global(#vs-cobudget-api-token-expiry__listbox .api-token-expiry-template-option) {
	padding-inline-start: 8px;
}
.api-token-editor {
	display: grid;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding, 24px) var(--cobudget-dialog-padding, 24px);
}
.api-token-editor h2 {
	margin: 0 0 calc(var(--default-grid-baseline, 4px) * 2);
	padding-inline-end: var(--cobudget-mobile-touch-size, 44px);
	font-size: var(--cobudget-dialog-title-size, 20px);
	text-align: start;
}
.api-token-editor p {
	margin: 0;
}
.api-token-editor-body {
	display: contents;
}
.api-token-editor-section {
	margin-block-start: calc(var(--default-grid-baseline, 4px) * 6);
}
.api-token-editor :deep(.v-select.select) {
	min-width: 0;
	margin: 0;
}
.api-token-permissions-stack {
	display: grid;
	gap: calc(var(--default-grid-baseline, 4px) * 4);
}
.api-token-matrix-scroll {
	overflow-x: auto;
}
.api-token-matrix-table {
	width: 100%;
	min-width: 420px;
	table-layout: fixed;
	border-collapse: collapse;
	color: var(--cobudget-text);
}
.api-token-matrix-table th,
.api-token-matrix-table td {
	padding: 6px;
	border-bottom: 1px solid var(--cobudget-border);
	vertical-align: middle;
	font-weight: normal;
	color: var(--cobudget-text);
}
.api-token-matrix-table th:first-child {
	text-align: start;
	overflow-wrap: anywhere;
	white-space: normal;
}
.api-token-matrix-table thead th {
	color: var(--cobudget-text-muted);
	font-weight: var(--cobudget-font-weight-label);
}
.api-token-matrix-col {
	width: 88px;
	text-align: center;
	white-space: nowrap;
}
.api-token-matrix-col :deep(.checkbox-radio-switch) {
	width: 44px;
	min-width: 44px;
	min-height: 44px;
	margin-inline: auto;
	justify-content: center;
}
.api-token-matrix-col :deep(.checkbox-content) {
	padding: 0;
	gap: 0;
}
.api-token-matrix-target {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
}
.api-token-matrix-row-heading {
	display: flex;
	flex-direction: column;
	gap: 2px;
}
.api-token-matrix-hint {
	font-size: var(--cobudget-font-caption, 12px);
	line-height: 1.35;
	color: var(--cobudget-text-muted);
	font-weight: normal;
}
.api-token-matrix-unavailable {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 44px;
	color: var(--cobudget-text-muted);
	user-select: none;
}
.api-token-matrix-table tbody tr {
	transition: background-color var(--cobudget-hover-duration) linear;
}
.api-token-matrix-table tbody tr:hover,
.api-token-matrix-table tbody tr:focus-within {
	background-color: var(--color-background-hover, var(--cobudget-surface-muted));
}
@media (max-width: 560px) {
	.api-token-matrix-col {
		width: 68px;
		padding-inline: 2px;
	}
}
.api-token-actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	width: 100%;
	margin-top: 24px;
}
.api-token-confirm-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
	margin-inline-start: auto;
}
.api-token-delete-button :deep(.button-vue__icon) {
	display: none;
}
@media (min-width: 769px) {
	.api-token-editor .api-token-delete-button {
		padding-inline: var(--button-padding-default);
	}
	.api-token-delete-button :deep(.button-vue__text) {
		padding-inline: var(--default-grid-baseline, 4px);
	}
}
@media (max-width: 768px) {
	.api-token-editor-modal {
		--cobudget-dialog-padding: var(--cobudget-space-4, 16px);
	}
	.api-token-editor-modal :deep(.modal-container) {
		height: calc(100% - var(--header-height));
		overflow: hidden;
	}
	.api-token-editor-modal :deep(.modal-container__content) {
		display: flex;
		min-height: 0;
		overflow: hidden;
	}
	.api-token-editor {
		display: flex;
		flex-direction: column;
		width: 100%;
		min-width: 0;
		height: 100%;
		min-height: 0;
		padding: 0;
		gap: 0;
	}
	.api-token-editor h2 {
		flex-shrink: 0;
		margin: 0;
		padding: calc(var(--default-grid-baseline, 4px) * 3) calc(var(--cobudget-mobile-touch-size, 44px) + var(--cobudget-dialog-padding)) var(--cobudget-space-2, 8px) var(--cobudget-dialog-padding);
		line-height: 1.5;
	}
	.api-token-editor-body {
		display: grid;
		align-content: start;
		flex: 1;
		min-height: 0;
		background-color: inherit;
		overflow: auto;
		overscroll-behavior: none;
		transform: translateZ(0);
		backface-visibility: hidden;
		gap: var(--cobudget-space-2, 8px);
		padding: var(--default-grid-baseline, 4px) var(--cobudget-dialog-padding) calc(var(--default-grid-baseline, 4px) * 3);
	}
	.api-token-editor-section {
		margin-block-start: var(--cobudget-space-4, 16px);
	}
	.api-token-validity {
		grid-template-columns: minmax(0, 1fr);
		gap: var(--cobudget-space-2, 8px);
	}
	.api-token-actions {
		flex-shrink: 0;
		flex-wrap: nowrap;
		width: auto;
		margin: 0;
		padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding) max(var(--cobudget-dialog-padding), env(safe-area-inset-bottom));
	}
	.api-token-confirm-actions {
		flex: 1;
		min-width: 0;
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
	.api-token-confirm-actions > :deep(.button-vue) {
		min-width: 0;
		width: 100%;
	}
	.api-token-delete-button {
		flex: 0 0 var(--cobudget-mobile-touch-size, 44px);
		width: var(--cobudget-mobile-touch-size, 44px);
		min-width: 0;
		padding-inline: 0;
	}
	.api-token-delete-button :deep(.button-vue__icon) {
		display: flex;
	}
	.api-token-delete-label {
		display: none;
	}
}
.api-token-password-body,
.api-token-secret-body {
	display: grid;
	gap: var(--cobudget-space-4, 16px);
	padding-block: var(--default-grid-baseline, 4px);
}
.api-token-password-hint {
	margin: 0;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}
.api-token-secret-field {
	display: grid;
	gap: var(--cobudget-space-2, 8px);
}
.api-token-secret-row {
	display: flex;
	align-items: center;
	gap: var(--cobudget-space-2, 8px);
	flex-wrap: wrap;
}
.api-token-secret-input {
	flex: 1 1 240px;
	min-width: 0;
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.api-token-endpoints-dialog {
	display: grid;
	gap: 0;
	padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding, 24px) var(--cobudget-dialog-padding, 24px);
}
.api-token-endpoints-dialog h2 {
	margin: 0 0 calc(var(--default-grid-baseline, 4px) * 4);
	padding-inline-end: var(--cobudget-mobile-touch-size, 44px);
	font-size: var(--cobudget-dialog-title-size, 20px);
	text-align: start;
}
.api-token-endpoints-body {
	display: contents;
}
.api-token-endpoints-toolbar {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px;
	margin-bottom: var(--cobudget-content-gap, 16px);
}
.api-token-endpoints-body > .note-card + .api-token-endpoints-toolbar {
	margin-top: var(--cobudget-content-gap, 16px);
}
.api-token-endpoints-section {
	padding-top: var(--cobudget-settings-section-gap, 32px);
}
.api-token-endpoints-body > .api-token-endpoints-section:first-child {
	padding-top: 0;
}
.api-token-endpoints-body > .note-card + .api-token-endpoints-section,
.api-token-endpoints-toolbar + .api-token-endpoints-section {
	padding-top: var(--cobudget-space-2, 8px);
}
.api-token-endpoints-section > h3 {
	font-size: var(--cobudget-font-section-title);
	font-weight: var(--cobudget-font-weight-heading);
	line-height: 1.5;
	margin: 0 0 14px 0;
	color: var(--cobudget-text);
}
.api-token-endpoints-list {
	display: grid;
	gap: var(--cobudget-section-gap, 24px);
}
.api-token-endpoint-item {
	min-width: 0;
}
.api-token-endpoint-description {
	display: grid;
	gap: 4px;
}
.api-token-endpoint-field {
	box-sizing: border-box;
	width: 100%;
	min-width: 0;
	border: 1px solid var(--color-border-maxcontrast, var(--cobudget-border-strong));
	border-radius: var(--border-radius-large, 10px);
	background: var(--cobudget-surface);
	overflow: hidden;
}
.api-token-auth-meta {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px 16px;
	padding: 8px 12px;
	border-bottom: 1px solid var(--cobudget-border);
	background: var(--cobudget-surface-muted);
	font-size: var(--cobudget-font-caption, 12px);
	color: var(--cobudget-text-muted);
}
.api-token-auth-meta-item {
	display: inline-flex;
	align-items: center;
}
.api-token-auth-table {
	width: 100%;
	table-layout: fixed;
}
.api-token-auth-table :is(th, td) {
	white-space: normal;
	overflow-wrap: anywhere;
}
.api-token-auth-table td {
	vertical-align: top;
	border-bottom: 1px solid var(--cobudget-border);
}
.api-token-auth-table tbody tr:last-child td {
	border-bottom: 0;
}
.api-token-auth-example {
	font-family: var(--font-monospace, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
	font-size: var(--cobudget-font-caption, 12px);
	color: var(--cobudget-text);
	user-select: all;
}
.api-token-auth-table .col-auth-copy {
	text-align: right;
	vertical-align: middle;
}
.api-token-auth-note {
	margin: 0;
	padding: 8px 12px;
	border-top: 1px solid var(--cobudget-border);
	font-size: var(--cobudget-font-caption, 12px);
	line-height: 1.4;
	color: var(--cobudget-text-muted);
}
.api-token-endpoint-auth-field {
	display: flex;
	align-items: center;
	min-height: var(--default-clickable-area, 44px);
	padding: 8px 12px;
}
.api-token-endpoint-header-row {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	align-items: center;
	min-height: var(--default-clickable-area, 44px);
	gap: 6px;
	padding: 3px 4px 3px 8px;
}
.api-token-endpoint-controls {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	flex-shrink: 0;
}
.api-token-example-toggle.is-active {
	background: color-mix(in srgb, var(--color-primary-element) 14%, transparent);
	color: var(--cobudget-text);
}
.api-token-params-toggle :deep(.button-vue__text) {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
.api-token-params-chevron {
	transition: transform var(--cobudget-hover-duration, 150ms) ease;
}
.api-token-params-chevron.is-open {
	transform: rotate(180deg);
}
.api-token-params-panel {
	border-top: 1px solid var(--cobudget-border);
	background: var(--cobudget-surface);
	overflow: hidden;
}
.api-token-example-panel {
	border-top: 1px solid var(--cobudget-border);
	background: var(--cobudget-surface-muted);
}
.api-token-example-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	padding: 6px 12px;
	border-bottom: 1px solid var(--cobudget-border);
	background: var(--cobudget-surface);
}
.api-token-example-lang {
	font-size: var(--cobudget-font-caption, 12px);
	font-weight: var(--font-weight-heading, 600);
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: var(--cobudget-text-muted);
}
.api-token-example-code {
	margin: 0;
	padding: 12px 14px;
	font-family: var(--font-monospace, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
	font-size: var(--cobudget-font-caption, 12px);
	line-height: 1.5;
	color: var(--cobudget-text);
	white-space: pre-wrap;
	overflow-wrap: anywhere;
	overflow-x: auto;
	user-select: all;
}
.api-token-params-table {
	width: 100%;
	table-layout: fixed;
}
.api-token-params-table :is(th, td),
.api-token-params-table .bulk-import-field-code {
	white-space: normal;
	overflow-wrap: anywhere;
}
.api-token-params-table th,
.api-token-params-table td {
	padding: 8px 12px;
}
.api-token-params-table td {
	vertical-align: top;
	border-bottom: 1px solid var(--cobudget-border);
}
.bulk-import-field-code {
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	font-size: var(--cobudget-font-caption);
	color: var(--cobudget-text);
	background: var(--cobudget-surface-muted);
	padding: 2px 6px;
	border-radius: var(--cobudget-badge-radius);
}
.api-token-params-table tbody tr:last-child td {
	border-bottom: 0;
}
.api-token-param-type {
	color: var(--cobudget-text-muted);
}
.api-token-param-values {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	list-style: none;
	margin: 0;
	padding: 0;
}
.api-token-param-values--stacked {
	flex-direction: column;
	align-items: stretch;
	gap: 0;
}
.api-token-param-value-item {
	display: inline-flex;
	align-items: baseline;
	gap: 6px;
	min-width: 0;
	max-width: 100%;
}
.api-token-param-values--stacked .api-token-param-value-item {
	display: flex;
	align-items: flex-start;
	width: 100%;
	padding-block: 5px;
	border-bottom: 1px solid var(--cobudget-border);
}
.api-token-param-values--stacked .api-token-param-value-item:first-child {
	padding-top: 0;
}
.api-token-param-values--stacked .api-token-param-value-item:last-child {
	padding-bottom: 0;
	border-bottom: 0;
}
.api-token-param-values--stacked .bulk-import-field-code {
	flex-shrink: 0;
	white-space: nowrap;
	overflow-wrap: normal;
	min-width: 2.25ch;
	text-align: center;
}
.api-token-param-value-label {
	color: var(--cobudget-text);
	overflow-wrap: anywhere;
}
.api-token-param-value-empty {
	color: var(--cobudget-text-muted);
}
.api-token-param-desc {
	color: var(--cobudget-text);
}
@media (min-width: 769px) {
	.api-token-auth-table .col-auth-header {
		width: 12.5rem;
	}
	.api-token-auth-table .col-auth-status {
		width: 7.5rem;
	}
	.api-token-auth-table .col-auth-copy {
		width: 3.25rem;
	}
	.api-token-params-table .col-field {
		width: 12.5rem;
	}
	.api-token-params-table .col-type {
		width: 7.5rem;
	}
	.api-token-params-table .col-required {
		width: 6.25rem;
	}
	.api-token-params-table--with-values .col-field {
		width: 10.5rem;
	}
	.api-token-params-table--with-values .col-type {
		width: 6.25rem;
	}
	.api-token-params-table--with-values .col-required {
		width: 5.5rem;
	}
	.api-token-params-table--with-values .col-values {
		width: 13.5rem;
	}
}
@media (hover: hover) and (pointer: fine) {
	.api-token-params-table tbody tr:hover td {
		background: var(--cobudget-surface-hover, var(--color-background-hover));
	}
}
@media (max-width: 768px) {
	.api-token-auth-table tbody tr,
	.api-token-params-table tbody tr {
		padding: 8px 12px;
		border-bottom: 1px solid var(--cobudget-border);
	}
	.api-token-auth-table tbody tr:last-child,
	.api-token-params-table tbody tr:last-child {
		border-bottom: 0;
	}
	.api-token-auth-table .col-auth-copy {
		justify-content: flex-end;
	}
	.api-token-params-table .api-token-param-values-cell {
		align-items: flex-start;
	}
	.api-token-param-values {
		justify-content: flex-end;
	}
	.api-token-param-values--stacked {
		align-items: flex-end;
	}
	.api-token-param-values--stacked .api-token-param-value-item {
		justify-content: flex-end;
	}
}
@media (max-width: 640px) {
	.api-token-endpoint-header-row {
		grid-template-columns: auto minmax(0, 1fr);
		padding: 6px 8px;
	}
	.api-token-endpoint-url {
		grid-column: 2 / -1;
	}
	.api-token-endpoint-controls {
		grid-column: 1 / -1;
		justify-self: end;
		flex-wrap: wrap;
	}
}
.api-token-method-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 60px;
	padding: 2px 8px;
	border-radius: var(--border-radius, 6px);
	font-family: inherit;
	font-size: var(--cobudget-font-caption, 12px);
	font-weight: var(--font-weight-heading, 600);
	background: color-mix(in srgb, var(--color-primary-element) 14%, transparent);
	color: var(--cobudget-text);
}
.api-token-method-badge[data-method="POST"] {
	background: color-mix(in srgb, var(--color-success, #2d8a4e) 18%, transparent);
}
.api-token-method-badge[data-method="PATCH"] {
	background: color-mix(in srgb, var(--color-warning, #c77c02) 20%, transparent);
}
.api-token-method-badge[data-method="DELETE"] {
	background: color-mix(in srgb, var(--color-error, #c93b2b) 20%, transparent);
}
.api-token-endpoint-header,
.api-token-endpoint-url {
	display: block;
	padding: 0;
	border-radius: 0;
	background: transparent;
	font-family: inherit;
	font-size: var(--cobudget-font-body, 15px);
	line-height: 1.4;
	color: var(--cobudget-text);
	overflow-wrap: anywhere;
	user-select: all;
}
.api-token-endpoints-actions {
	display: flex;
	justify-content: flex-end;
	margin-top: 24px;
}
@media (max-width: 768px) {
	.api-token-endpoints-modal {
		--cobudget-dialog-padding: var(--cobudget-space-4, 16px);
	}
	.api-token-endpoints-modal :deep(.modal-container) {
		height: calc(100% - var(--header-height));
		overflow: hidden;
	}
	.api-token-endpoints-modal :deep(.modal-container__content) {
		display: flex;
		min-height: 0;
		overflow: hidden;
	}
	.api-token-endpoints-dialog {
		display: flex;
		flex-direction: column;
		width: 100%;
		min-width: 0;
		height: 100%;
		min-height: 0;
		padding: 0;
		gap: 0;
	}
	.api-token-endpoints-dialog h2 {
		flex-shrink: 0;
		padding: calc(var(--default-grid-baseline, 4px) * 3) calc(var(--cobudget-mobile-touch-size, 44px) + var(--cobudget-dialog-padding)) var(--cobudget-space-2, 8px) var(--cobudget-dialog-padding);
		line-height: 1.5;
	}
	.api-token-endpoints-body {
		display: flex;
		flex-direction: column;
		flex: 1;
		min-height: 0;
		max-height: none;
		background-color: inherit;
		overflow: auto;
		overscroll-behavior: none;
		transform: translateZ(0);
		backface-visibility: hidden;
		align-content: start;
		padding: var(--default-grid-baseline, 4px) var(--cobudget-dialog-padding) calc(var(--default-grid-baseline, 4px) * 3);
	}
	.api-token-endpoints-body > * {
		flex-shrink: 0;
	}
	.api-token-endpoints-actions {
		flex-shrink: 0;
		margin: 0;
		padding: calc(var(--default-grid-baseline, 4px) * 3) var(--cobudget-dialog-padding) max(var(--cobudget-dialog-padding), env(safe-area-inset-bottom));
	}
	.api-token-table :deep(.button-vue) {
		min-width: var(--cobudget-mobile-touch-size, 44px);
		min-height: var(--cobudget-mobile-touch-size, 44px);
	}
}
</style>
