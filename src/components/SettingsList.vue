<template>
	<ul class="settings-list">
		<li
			v-for="(item, index) in items"
			:key="itemKey(item, index)"
			class="settings-list-item"
			:class="{ 'is-hidden': isHidden(item) }">
			<slot name="item" :item="item" />
		</li>
		<li v-if="items.length === 0" class="settings-list-empty">{{ emptyText }}</li>
	</ul>
</template>

<script>
import { texts } from '../l10n/texts'

export default {
	name: 'SettingsList',
	props: {
		items: {
			type: Array,
			default: () => []
		},
		emptyText: {
			type: String,
			default: () => texts.common.noEntries()
		},
		keyField: {
			type: String,
			default: 'id'
		},
		hiddenField: {
			type: String,
			default: 'is_hidden'
		}
	},
	methods: {
		itemKey(item, index) {
			return item && item[this.keyField] !== undefined ? item[this.keyField] : index;
		},
		isHidden(item) {
			if (!item || !this.hiddenField) {
				return false;
			}
			const value = item[this.hiddenField];
			return value === true || value === 1 || value === '1' || value === 'true';
		}
	}
}
</script>

<style scoped>
.settings-list {
	list-style: none;
	padding: 0;
	margin: 0;
	border: 1px solid var(--cobudget-border, #eee);
	border-radius: var(--border-radius-large, 8px);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.settings-list-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 10px 15px;
	border-bottom: 1px solid var(--cobudget-border, #eee);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.settings-list-item:last-child {
	border-bottom: none;
}

.settings-list-item.is-hidden {
	background: var(--cobudget-surface-muted, #f9f9f9);
	color: var(--cobudget-text-muted, #666);
}

.settings-list-item.is-hidden :deep(.settings-list-info),
.settings-list-item.is-hidden :deep(.settings-list-info > span:not(.badge-global):not(.badge-hidden)),
.settings-list-item.is-hidden :deep(.settings-list-info > strong) {
	color: var(--cobudget-text-muted, #666) !important;
}

.settings-list-item.is-hidden :deep(.settings-list-actions),
.settings-list-item.is-hidden :deep(.badge-global),
.settings-list-item.is-hidden :deep(.badge-hidden) {
	opacity: 0.9;
}

.settings-list-empty {
	padding: 15px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	text-align: center;
	font-style: italic;
}

:deep(.settings-list-info) {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
	flex: 1;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
}

:deep(.settings-list-info > span:not(.badge-global):not(.badge-hidden)),
:deep(.settings-list-info > strong) {
	color: inherit !important;
	overflow-wrap: anywhere;
}

:slotted(.settings-list-info) {
	color: inherit !important;
}

:deep(.settings-list-actions) {
	display: flex;
	flex-shrink: 0;
	justify-content: flex-end;
}

@media (max-width: 768px) {
	.settings-list-item {
		align-items: stretch;
		flex-direction: column;
	}

	:deep(.settings-list-actions) {
		justify-content: flex-start;
	}
}
</style>
