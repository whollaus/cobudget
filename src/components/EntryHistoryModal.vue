<template>
	<NcDialog class="entry-history-modal cobudget-native-dialog cobudget-standard-dialog"
		:name="$texts.entry.historyTitle()" size="large" close-on-click-outside
		@closing="$emit('close')">
		<div v-if="loading" class="history-loading history-muted" role="status">
			<NcLoadingIcon :size="32" /><span>{{ $texts.entry.historyLoading() }}</span>
		</div>
		<NcNoteCard v-else-if="error" type="error">
			<p>{{ error }}</p>
			<NcButton variant="secondary" class="cobudget-native-button" @click="$emit('retry')">{{ $texts.common.retry() }}</NcButton>
		</NcNoteCard>
		<NcEmptyContent v-else-if="history.length === 0" :name="$texts.entry.historyEmpty()">
			<template #icon><HistoryIcon :size="64" /></template>
		</NcEmptyContent>
		<div v-else class="history-table-wrapper" role="region" :aria-label="$texts.entry.historyTitle()" tabindex="0">
		<table class="history-table">
			<thead>
				<tr>
					<th scope="col">{{ $texts.entry.historyChangedAt() }}</th>
					<th scope="col">{{ $texts.entry.historyChangedBy() }}</th>
					<th scope="col">{{ $texts.entry.historyField() }}</th>
					<th scope="col">{{ $texts.entry.historyPrevious() }}</th>
					<th scope="col">{{ $texts.entry.historyCurrent() }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in history" :key="row.id">
					<td>{{ row.changed_at_display }}</td>
					<td>{{ row.changed_by_display_name }}</td>
					<td>{{ row.field_label }}</td>
					<td>{{ displayValue(row.old_display) }}</td>
					<td>{{ displayValue(row.new_display) }}</td>
				</tr>
			</tbody>
		</table>
		</div>
		<template #actions>
			<NcButton variant="tertiary" class="cobudget-native-button" @click="$emit('close')">{{ $texts.common.close() }}</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcButton from '@nextcloud/vue/components/NcButton'
import HistoryIcon from 'vue-material-design-icons/History.vue'

export default {
	name: 'EntryHistoryModal',
	components: { NcDialog, NcLoadingIcon, NcEmptyContent, NcNoteCard, NcButton, HistoryIcon },
	props: {
		error: { type: String, default: '' },
		history: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
	},
	emits: ['close', 'retry'],
	methods: {
		displayValue(value) {
			return value === '' || value === null || typeof value === 'undefined' ? '-' : value
		},
	},
}
</script>

<style scoped>
.history-muted {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size, 15px);
}
.history-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 12px;
	padding-block: 24px;
}
.history-table-wrapper {
	overflow-x: auto;
}
.history-table-wrapper:focus-visible {
	outline: var(--cobudget-focus-outline);
	outline-offset: -2px;
}
.history-table {
	width: 100%;
	border-collapse: collapse;
	min-width: 680px;
	table-layout: fixed;
}
.history-table th,
.history-table td {
	padding: var(--cobudget-table-cell-padding);
	border-bottom: 1px solid var(--color-border);
	text-align: start;
	vertical-align: top;
	font-size: var(--font-size, 15px);
	line-height: 1.5;
	overflow-wrap: anywhere;
}
.history-table th {
	background: var(--cobudget-table-header-background);
	color: var(--color-main-text);
	font-weight: 400;
	font-size: var(--cobudget-table-header-font-size);
	height: var(--cobudget-table-header-height);
}
.history-table td { color: var(--color-main-text); white-space: pre-wrap; }
.history-table tr:last-child td { border-bottom: 0; }
</style>
