<template>
	<NcActions v-if="exportData && exportContext" ref="actions" class="report-section-menu no-print cobudget-action-menu" variant="tertiary" force-menu placement="bottom-end" :open="menuShown" :aria-label="`${$texts.annual.sectionActions()}: ${title}`" @update:open="setMenuShown">
		<NcActionButton class="cobudget-action-menu-option" close-after-click :disabled="exporting || exportDisabled" @click="exportSection('download')"><template #icon><DownloadIcon :size="20" /></template>{{ $texts.common.download() }}</NcActionButton>
		<NcActionButton class="cobudget-action-menu-option" close-after-click :disabled="exporting || exportDisabled" @click="exportSection('save')"><template #icon><CloudUploadOutlineIcon :size="20" /></template>{{ $texts.annual.saveInNextcloud() }}</NcActionButton>
		<NcActionButton class="cobudget-action-menu-option" close-after-click :disabled="exporting || exportDisabled" @click="exportSection('copy')"><template #icon><ClipboardTextOutlineIcon :size="20" /></template>{{ $texts.common.copyToClipboard() }}</NcActionButton>
	</NcActions>
</template>

<script>
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import CloudUploadOutlineIcon from 'vue-material-design-icons/CloudUploadOutline.vue'
import ClipboardTextOutlineIcon from 'vue-material-design-icons/ClipboardTextOutline.vue'
import { deliverReportExport } from '../services/reportExportActions'
import { showRequestError, showToast } from '../services/notifications'

export default {
	name: 'ReportSectionMenu',
	components: { NcActions, NcActionButton, DownloadIcon, CloudUploadOutlineIcon, ClipboardTextOutlineIcon },
	inject: { exportContext: { from: 'reportExportContext', default: null } },
	props: { title: { type: String, required: true }, exportData: { type: Function, default: null }, exportDisabled: { type: Boolean, default: false } },
	data: () => ({ exporting: false, menuShown: false }),
	beforeUnmount() { document.removeEventListener('click', this.onDocumentClick, true) },
	deactivated() { this.setMenuShown(false) },
	methods: {
		setMenuShown(shown) {
			this.menuShown = shown
			// floating-vue only listens for touch outside on iOS, missing mouse/trackpad clicks.
			if (shown) document.addEventListener('click', this.onDocumentClick, true)
			else document.removeEventListener('click', this.onDocumentClick, true)
		},
		onDocumentClick(event) {
			const root = this.$refs.actions?.$el
			// NcActions renders its menu in body, linked to the trigger by aria-controls.
			const menuId = root?.querySelector('button[aria-controls]')?.getAttribute('aria-controls')
			const menu = menuId ? document.getElementById(menuId) : null
			if (root?.contains(event.target) || menu?.contains(event.target)) return
			this.setMenuShown(false)
		},
		exportSection(action) {
			if (this.exporting || this.exportDisabled) return
			this.exporting = true
			try {
				const context = { ...this.exportContext(), section: true }
				return deliverReportExport(action, this.exportData({ display: true }), context, this.$texts)
					.catch(error => this.exportError(error, action)).finally(() => { this.exporting = false })
			} catch (error) { this.exportError(error, action); this.exporting = false }
		},
		exportError(error, action) {
			if (error?.code === 'REPORT_LOADING') showToast(this.$texts.common.loading(), 'info')
			else showRequestError(error, action === 'copy' ? this.$texts.common.copyToClipboardError() : action === 'save' ? this.$texts.annual.saveInNextcloudError() : this.$texts.common.exportCsvError())
		},
	},
}
</script>

<style scoped>
.report-section-menu { flex: 0 0 auto; margin-inline-start: calc(var(--default-grid-baseline, 4px) * 2); color: var(--cobudget-text-muted); }
.report-section-menu :deep(.button-vue) { min-width: 32px; width: 32px; min-height: 32px; height: 32px; margin: 0; padding: 0; color: var(--cobudget-text-muted); }
@media (max-width: 768px) { .report-section-menu :deep(.button-vue) { min-width: 44px; width: 44px; min-height: 44px; height: 44px; } }
</style>
