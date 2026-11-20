<template>
	<section id="automatic-imports" class="settings-subsection auto-import-settings" aria-labelledby="auto-import-title">
		<div class="auto-import-heading">
			<h3 id="auto-import-title" ref="heading" class="cobudget-section-heading" tabindex="-1">{{ t.title() }}</h3>
			<p class="auto-import-description">{{ t.description() }}</p>
			<NcButton class="auto-import-format-button" variant="tertiary" @click="openFormat">
				<template #icon><HelpCircleOutlineIcon :size="20" /></template>
				{{ $texts.bulkImport.formatTitle() }}
			</NcButton>
		</div>
		<p v-if="loading" class="auto-import-loading" role="status"><NcLoadingIcon :size="20" /> {{ $texts.common.loading() }}</p>
		<NcNoteCard v-else-if="loadError" type="error">
			<p>{{ loadError }}</p>
			<NcButton variant="tertiary" @click="load">{{ $texts.common.retry() }}</NcButton>
		</NcNoteCard>
		<template v-else>
			<NcNoteCard v-if="error" type="error" :text="error" />
			<table class="auto-import-table" aria-labelledby="auto-import-title">
				<thead><tr>
					<th scope="col">{{ $texts.entry.area() }}</th>
					<th scope="col">{{ t.folder() }}</th>
					<th scope="col">{{ t.interval() }}</th>
					<th scope="col">{{ t.notification() }}</th>
					<th scope="col" class="auto-import-control">{{ t.active() }}</th>
					<th scope="col" class="auto-import-control"><span class="hidden-visually">{{ t.runNow() }}</span></th>
					<th scope="col">{{ t.lastRun() }}</th>
				</tr></thead>
				<tbody>
					<tr v-for="row in rows" :key="row.key" :aria-busy="!!pending[row.key]">
						<th scope="row"><span class="auto-import-area"><AreaIcon :without-area="row.area.id === null" :color="row.area.color" /><span>{{ row.area.name }}</span></span></th>
						<td :data-label="t.folder()">
							<div class="auto-import-folder">
								<NcButton variant="secondary" :title="row.rule?.folder ? displayPath(row.rule.folder) : undefined"
									:aria-label="controlLabel(row, t.folder())" :disabled="!!pending[row.key] || picking || row.unavailable"
									@click="chooseFolder(row)">
									<template #icon><FolderIcon :size="20" /></template>
									{{ row.rule?.folder ? displayPath(row.rule.folder) : $texts.settings.selectFolder() }}
								</NcButton>
								<CbIconButton v-if="row.rule && (row.rule.folder || row.unavailable)" :aria-label="t.removeNamed(row.rule.folder ? displayPath(row.rule.folder) : row.area.name)"
									:disabled="!!pending[row.key] || picking === row.key" @click="remove(row)"><CloseIcon :size="16" /></CbIconButton>
							</div>
						</td>
						<td :data-label="t.interval()">
							<label class="hidden-visually" :for="row.key + '-interval'">{{ controlLabel(row, t.interval()) }}</label>
							<NcSelect :input-id="row.key + '-interval'" class="cobudget-native-select" :options="intervals" label="label"
								:clearable="false" :searchable="false" :disabled="!!pending[row.key] || picking === row.key || row.unavailable"
								:model-value="intervals.find(option => option.value === (row.rule?.interval_minutes ?? 60))"
								@update:model-value="save(row, { intervalMinutes: $event.value })">
								<template #option="{ label }"><span>{{ label }}</span></template>
								<template #selected-option="{ label }"><span class="auto-import-choice" :title="label">{{ label }}</span></template>
							</NcSelect>
						</td>
						<td :data-label="t.notification()">
							<label class="hidden-visually" :for="row.key + '-notification'">{{ controlLabel(row, t.notification()) }}</label>
							<NcSelect :input-id="row.key + '-notification'" class="cobudget-native-select" :options="notifications" label="label"
								:clearable="false" :searchable="false" :disabled="!!pending[row.key] || picking === row.key || row.unavailable"
								:model-value="notifications.find(option => option.value === (row.rule?.notify_mode ?? 'error'))"
								@update:model-value="save(row, { notifyMode: $event.value })">
								<template #option="{ label }"><span>{{ label }}</span></template>
								<template #selected-option="{ label }"><span class="auto-import-choice" :title="label">{{ label }}</span></template>
							</NcSelect>
						</td>
						<td class="auto-import-control auto-import-active" :data-label="t.active()">
							<NcCheckboxRadioSwitch type="switch" :model-value="!!row.rule?.enabled" :aria-label="controlLabel(row, t.active())"
								:disabled="!!pending[row.key] || picking === row.key || !row.rule?.folder || (row.unavailable && !row.rule.enabled)"
								@update:model-value="save(row, { enabled: $event })" />
						</td>
						<td class="auto-import-control auto-import-run" :data-label="t.runNow()">
							<NcButton variant="tertiary" :aria-label="controlLabel(row, t.runNow())" :title="t.runNow()"
								:disabled="!canRun(row)" :aria-busy="pending[row.key] === 'run' || undefined" @click="runNow(row)">
								<template #icon>
									<NcLoadingIcon v-if="pending[row.key] === 'run'" :size="20" />
									<PlayIcon v-else :size="20" />
								</template>
							</NcButton>
						</td>
						<td :data-label="t.lastRun()">
							<TableTooltip v-if="row.rule?.last_run || row.rule?.waiting_messages?.length || row.rule?.last_message"
								:message-lines="resultDetails(row.rule)">
								<span class="auto-import-result">
									<AlertCircleOutlineIcon v-if="row.rule.last_error || row.rule.waiting_messages?.length"
										class="auto-import-result-alert" :size="16" aria-hidden="true" />
									{{ row.rule.last_run ? formatDate(row.rule.last_run) : t.lastResult() }}
								</span>
							</TableTooltip>
							<span v-else>—</span>
						</td>
					</tr>
				</tbody>
			</table>
		</template>
		<ImportFormatDialog :show="formatOpen" :project="formatProject" :categories="formatCategories" :payment-partners="formatPartners"
			:loading="formatLoading" :error="formatError" @close="closeFormat">
			<template #target>
				<div v-if="formatTargets.length > 1" class="auto-import-format-target">
					<label for="auto-import-format-area" class="cobudget-form-label">{{ $texts.entry.area() }}</label>
					<NcSelect input-id="auto-import-format-area" class="cobudget-native-select" label="name" :options="formatTargets"
						:model-value="formatProject || formatTargets[0]" :clearable="false" :append-to-body="false" @update:model-value="loadFormat($event)">
						<template #option="area"><span class="auto-import-area"><AreaIcon :without-area="area.id === null" :color="area.color" />{{ area.name }}</span></template>
						<template #selected-option="area"><span class="auto-import-area"><AreaIcon :without-area="area.id === null" :color="area.color" />{{ area.name }}</span></template>
					</NcSelect>
				</div>
			</template>
		</ImportFormatDialog>
	</section>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import HelpCircleOutlineIcon from 'vue-material-design-icons/HelpCircleOutline.vue'
import ImportFormatDialog from './ImportFormatDialog.vue'
import PlayIcon from 'vue-material-design-icons/Play.vue'
import AlertCircleOutlineIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import TableTooltip from './TableTooltip.vue'
import AreaIcon from './AreaIcon.vue'
import FolderIcon from 'vue-material-design-icons/FolderOpenOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { Permission } from '@nextcloud/files'
import { getClient } from '@nextcloud/files/dav'
import CbIconButton from './CbIconButton.vue'
import { pickFolder } from '../services/folderPicker'
import { extractError, showToast } from '../services/notifications'
import { getAppLocale } from '../utils/formatMoney'
import axios from '../services/http'

const endpoint = '/apps/cobudget/api/auto-imports'
const suggestedFolder = '/CoBudget/Import'

export default {
	name: 'AutomaticImportSettings',
	components: { PlayIcon, TableTooltip, AlertCircleOutlineIcon, HelpCircleOutlineIcon, ImportFormatDialog, CbIconButton, NcButton, NcCheckboxRadioSwitch, NcLoadingIcon, NcNoteCard, NcSelect, AreaIcon, CloseIcon, FolderIcon },
	emits: ['activity-change'],
	props: {
		projects: { type: Array, required: true },
		confirmRemoval: { type: Function, required: true },
	},
	data() {
		return {
			rules: [], activeElsewhere: null, pending: {}, loading: true, picking: null, error: '', loadError: '', pickerStart: '/',
			formatOpen: false, formatProject: null, formatCategories: [], formatPartners: [],
			formatLoading: false, formatError: '', formatRequest: 0,
		}
	},
	computed: {
		t() { return this.$texts.autoImports },
		hasActiveImports() {
			return this.activeElsewhere === null ? null : this.activeElsewhere || this.rows.some(row => row.rule?.enabled)
		},
		rows() {
			const areas = new Map([[null, { id: null, name: this.$texts.entry.personalAssignment() }]])
			for (const project of this.projects) areas.set(Number(project.id), { ...project, id: Number(project.id) })
			const grouped = new Map()
			for (const rule of this.rules) {
				const id = rule.project_id
				if (!areas.has(id)) areas.set(id, { id, name: this.t.unavailableArea(id), unavailable: true })
				if (!grouped.has(id)) grouped.set(id, [])
				grouped.get(id).push(rule)
			}
			// Older configurations may contain multiple folders for one area.
			return [...areas.values()].filter(area => !area.archived).flatMap(area => (grouped.get(area.id) || [null]).map((rule, index) => ({
				key: index ? 'automatic-import-rule-' + rule.id : 'automatic-import-area-' + (area.id ?? 'personal'),
				area, rule, unavailable: !!area.unavailable,
			})))
		},
		formatTargets() {
			return [{ id: null, name: this.$texts.entry.personalAssignment() }, ...this.projects.filter(project => !project.archived)]
		},
		intervals() {
			return [
				{ value: 5, label: this.t.minutes(5) }, { value: 15, label: this.t.minutes(15) },
				{ value: 60, label: this.t.hourly() }, { value: 1440, label: this.$texts.settings.daily() },
			]
		},
		notifications() {
			return [
				{ value: 'none', label: this.t.off() }, { value: 'error', label: this.t.errorsOnly() },
				{ value: 'all', label: this.t.everyImport() },
			]
		},
	},
	watch: {
		hasActiveImports(active) {
			if (active !== null) this.$emit('activity-change', active)
		},
	},
	async mounted() {
		await this.load()
		if (this.$route?.query.section === 'automatic-imports') this.$refs.heading?.focus()
	},
	methods: {
		openFormat() {
			this.formatOpen = true
			this.loadFormat(this.formatTargets[0])
		},
		closeFormat() {
			this.formatOpen = false
			this.formatRequest++
		},
		async loadFormat(target) {
			const request = ++this.formatRequest
			this.formatProject = target.id === null ? null : target
			this.formatLoading = true
			this.formatError = ''
			this.formatCategories = []
			this.formatPartners = []
			const params = target.id === null ? {} : { projectId: target.id }
			try {
				const [categories, partners] = await Promise.all([
					axios.get(generateUrl('/apps/cobudget/api/categories'), { params }),
					axios.get(generateUrl('/apps/cobudget/api/payment-partners'), { params }),
				])
				if (request === this.formatRequest) {
					this.formatCategories = categories.data
					this.formatPartners = partners.data
				}
			} catch (error) {
				if (request === this.formatRequest) this.formatError = extractError(error, this.$texts.bulkImport.lookupsLoadError())
			} finally {
				if (request === this.formatRequest) this.formatLoading = false
			}
		},
		async load() {
			this.loading = true
			this.loadError = ''
			try {
				const { data } = await axios.get(generateUrl(endpoint), { params: { includeActivity: 1 } })
				this.rules = data.rules
				this.activeElsewhere = data.activeElsewhere
				this.pickerStart = await this.prepareImportFolder()
			} catch (error) {
				this.loadError = extractError(error, this.t.loadError())
			} finally { this.loading = false }
		},
		async prepareImportFolder() {
			let files
			try {
				files = getClient(generateRemoteUrl('webdav'))
				await files.createDirectory(suggestedFolder, { recursive: true })
				return suggestedFolder
			} catch {
				// Another tab may have created it. A file at this path or read-only storage still allows picking elsewhere.
				try {
					if ((await files.stat(suggestedFolder)).type === 'directory') return suggestedFolder
				} catch { /* No accessible suggested folder. */ }
				return '/'
			}
		},
		displayPath(folder) { return '/' + folder.replace(/^\/+/, '') },
		controlLabel(row, label) {
			return label + ' · ' + row.area.name + (row.rule?.folder ? ' · ' + this.displayPath(row.rule.folder) : '')
		},
		resultDetails(rule) {
			const waiting = rule.waiting_results ?? (rule.waiting_messages || []).map(message => ({ message }))
			let heading = rule.last_error ? this.t.importError() : this.t.lastResult()
			if (rule.last_run) heading += ' · ' + this.formatDate(rule.last_run)
			const lines = [{ message: heading }]
			if (rule.last_message && !waiting.some(line => line.message === rule.last_message)) {
				lines.push({ message: rule.last_message, file: rule.last_file })
			}
			if (waiting.length) lines.push({ message: this.t.waitingFiles() }, ...waiting)
			return lines.map(({ message, file }) => {
				const offset = file?.name && Number.isSafeInteger(file.id) && file.id > 0 ? message.indexOf(file.name) : -1
				if (offset < 0) return { message }
				return { message, before: message.slice(0, offset), label: file.name, after: message.slice(offset + file.name.length),
					href: generateUrl('/f/' + file.id) }
			})
		},
		formatDate(timestamp) {
			return new Intl.DateTimeFormat(getAppLocale(), { dateStyle: 'short', timeStyle: 'short' }).format(Number(timestamp) * 1000)
		},
		async chooseFolder(row) {
			if (this.picking || this.pending[row.key] || row.unavailable) return
			this.picking = row.key
			this.error = ''
			try {
				const canPick = node => node.type === 'folder' && node.path !== '/' && !!(node.permissions & Permission.CREATE)
					&& !this.rules.some(rule => rule.id !== row.rule?.id && rule.folder
						&& ((node.fileid > 0 && rule.folder_id === node.fileid) || this.displayPath(rule.folder) === node.path))
				let selectedFolderId
				const folder = await pickFolder(getFilePickerBuilder(this.t.folder()).setMultiSelect(false)
					.allowDirectories(true).setMimeTypeFilter(['httpd/unix-directory']).startAt(row.rule?.folder ? '/' : this.pickerStart)
					.setCanPick(canPick).setButtonFactory(nodes => [{ label: this.$texts.settings.selectFolder(),
						variant: 'primary', disabled: nodes.length !== 1 || !canPick(nodes[0]),
						callback: ([node]) => { selectedFolderId = node.fileid } }]))
				if (typeof folder === 'string' && folder !== '/') {
					await this.save(row, { folder: folder.replace(/^\/+|\/+$/g, ''), selectedFolderId })
				}
			} catch (error) {
				if (error?.name !== 'FilePickerClosed') this.error = extractError(error, this.t.folderError())
			} finally { this.picking = null }
		},
		canRun(row) {
			return !!row.rule?.enabled && !!row.rule.folder && !row.unavailable && !this.pending[row.key] && !this.picking
		},
		async runNow(row) {
			if (!this.canRun(row)) return
			this.pending[row.key] = 'run'
			this.error = ''
			try {
				const { data } = await axios.post(generateUrl(endpoint + '/' + row.rule.id + '/run'))
				this.rules = this.rules.map(rule => rule.id === data.rule.id ? data.rule : rule)
				if (data.outcome === 'skipped' || data.outcome === 'error') {
					this.error = (data.outcome === 'error' && data.rule.last_message) || this.t.runError()
				} else {
					showToast(data.outcome === 'more' ? this.t.runContinues() : this.t.folderChecked(), 'info')
				}
			} catch (error) {
				this.error = extractError(error, this.t.runError())
			} finally {
				delete this.pending[row.key]
			}
		},
		async save(row, changes) {
			if (this.pending[row.key] || (row.unavailable && changes.enabled !== false)) return
			const rule = row.rule
			const fields = {
				projectId: rule?.project_id ?? row.area.id, folder: rule?.folder ?? '',
				intervalMinutes: rule?.interval_minutes ?? 60, notifyMode: rule?.notify_mode ?? 'error',
				enabled: rule?.enabled ?? false, timezone: rule?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
				...changes,
			}
			if (fields.enabled && !fields.folder) return
			this.pending[row.key] = true
			this.error = ''
			try {
				const { data } = rule ? await axios.put(generateUrl(endpoint + '/' + rule.id), fields) : await axios.post(generateUrl(endpoint), fields)
				if (rule) this.rules = this.rules.map(item => item.id === data.id ? data : item)
				else this.rules.push(data)
			} catch (error) {
				this.error = extractError(error, this.t.saveError())
			} finally { delete this.pending[row.key] }
		},
		async remove(row) {
			if (this.pending[row.key] || !row.rule) return
			this.pending[row.key] = true
			this.error = ''
			try {
				const target = row.rule.folder ? this.displayPath(row.rule.folder) : row.area.name
				const ok = await this.confirmRemoval({ title: this.t.removeTitle(), message: this.t.removeHint(target),
					confirmLabel: this.$texts.common.delete(), confirmVariant: 'danger' })
				if (!ok) return
				await axios.delete(generateUrl(endpoint + '/' + row.rule.id))
				this.rules = this.rules.filter(rule => rule.id !== row.rule.id)
			} catch (error) {
				this.error = extractError(error, this.t.deleteError())
			} finally { delete this.pending[row.key] }
		},
	},
}
</script>

<style scoped>
.auto-import-heading {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	grid-template-areas: "title format" "description description";
	gap: var(--cobudget-content-gap);
	align-items: end;
	margin-block-end: var(--cobudget-content-gap);
}
.auto-import-heading h3 { grid-area: title; margin: 0; }
.auto-import-format-button { grid-area: format; }
.auto-import-description { grid-area: description; color: var(--cobudget-text-muted); margin: 0; }
@container (max-width: 420px) {
	.auto-import-heading { grid-template-columns: 1fr; grid-template-areas: "title" "description" "format"; }
	.auto-import-format-button { justify-self: start; }
}
.auto-import-format-target { display: grid; gap: 4px; }
.auto-import-settings { container-type: inline-size; }
.auto-import-loading, .auto-import-area, .auto-import-folder { display: flex; align-items: center; gap: 6px; min-width: 0; }
.auto-import-table { width: 100%; table-layout: fixed; border-collapse: collapse; color: var(--cobudget-text); }
.auto-import-table th, .auto-import-table td { padding: 8px 6px; text-align: start; vertical-align: middle; border-bottom: 1px solid var(--cobudget-border); overflow-wrap: anywhere; white-space: normal; }
.auto-import-table th { font-weight: var(--cobudget-font-weight-label); }
.auto-import-table thead th { color: var(--cobudget-text-muted); }
.auto-import-table thead th:nth-child(2) { width: 23%; }
.auto-import-table thead th:nth-child(3) { width: 15%; }
.auto-import-table thead th:nth-child(4) { width: 21%; }
@container (width > 820px) {
	.auto-import-table thead th:last-child { width: 14%; box-sizing: border-box; white-space: nowrap; }
	.auto-import-table tr > :last-child { text-align: end; }
	.auto-import-table .auto-import-control {
		width: calc(var(--cobudget-icon-button-size) + 16px);
		box-sizing: border-box;
		text-align: center;
	}
	.auto-import-table .auto-import-control:nth-child(6) {
		width: calc(var(--cobudget-icon-button-size) + 8px);
		padding-inline: 4px;
	}
	.auto-import-control > :is(.checkbox-radio-switch, .button-vue) { margin-inline: auto; }
	.auto-import-active :deep(.checkbox-radio-switch) { justify-content: center; }
	.auto-import-active :deep(.checkbox-content-switch) { padding-inline: 0; gap: 0; }
}
.auto-import-table :deep(.v-select.select) { width: 100%; min-width: 0; margin: 0; }
.auto-import-folder > :first-child { min-width: 0; max-width: 100%; }
.auto-import-folder > :last-child:not(:first-child) { flex-shrink: 0; }
.auto-import-table :deep(.nc-select.v-select.select.vs--single .vs__selected) {
	width: 100%;
	max-width: 100%;
	box-sizing: border-box;
	padding-inline: 8px 28px;
}
.auto-import-choice { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.auto-import-result { display: inline-flex; gap: 4px; align-items: center; min-height: var(--default-clickable-area, 24px); color: var(--cobudget-text-muted); cursor: help; }
.auto-import-result-alert { flex-shrink: 0; color: var(--cobudget-error-dark); }
@container (max-width: 820px) {
	.auto-import-run > .button-vue { justify-self: start; }
	.auto-import-active :deep(.checkbox-content-switch) { padding-inline-start: 0; }
	.auto-import-active :deep(.checkbox-content__icon) { margin-inline-start: -3px; }
	.auto-import-table, .auto-import-table tbody, .auto-import-table tr { display: block; }
	.auto-import-table thead { position: absolute; clip-path: inset(50%); width: 1px; height: 1px; overflow: hidden; }
	.auto-import-table tr { padding-block: 8px; border-block-end: 1px solid var(--cobudget-border); }
	.auto-import-table tbody th { display: block; padding: 4px 0 8px; border: 0; }
	.auto-import-table td { display: grid; grid-template-columns: minmax(6rem, 36%) minmax(0, 1fr); gap: 12px; align-items: center; padding: 5px 0; border: 0; }
	.auto-import-table td:last-child::before { white-space: nowrap; }
	.auto-import-table td::before { content: attr(data-label); color: var(--cobudget-text-muted); }
}
</style>
