<template>
	<div v-if="!withoutArea" class="entry-area" :data-report-export-label="name">
		<TableTooltip :text="[name, statusTooltip].filter(Boolean).join('\n')">
			<span class="entry-area-content">
				<FolderCheckIcon v-if="settled" :fill-color="color || 'var(--color-primary-element)'" :size="size" aria-hidden="true" />
				<AreaIcon v-else :color="color" :size="size" />
			</span>
		</TableTooltip>
	</div>
</template>

<script>
import FolderCheckIcon from 'vue-material-design-icons/FolderCheck.vue'
import AreaIcon from './AreaIcon.vue'
import TableTooltip from './TableTooltip.vue'

export default {
	name: 'EntryAreaCell',
	components: { FolderCheckIcon, AreaIcon, TableTooltip },
	props: {
		name: { type: String, required: true },
		color: { type: String, default: '' },
		withoutArea: { type: Boolean, default: false },
		statusTooltip: { type: String, default: '' },
		settled: { type: Boolean, default: false },
		size: { type: Number, default: 16 },
	},
}
</script>

<style scoped>
.entry-area { display: flex; justify-content: center; }
.entry-area,
.entry-area > span,
.entry-area :deep(.table-tooltip-trigger) {
	min-width: 0;
	max-width: 100%;
}
.entry-area-content {
	display: flex;
	align-items: center;
	min-width: 0;
	font-weight: 400;
	color: var(--cobudget-text);
}
.entry-area-content :deep(.material-design-icon) { flex: 0 0 auto; }
</style>
