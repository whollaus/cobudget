<template>
	<div class="settings-item-actions">
		<CbIconButton v-if="canEdit" :aria-label="editLabel" :title="editLabel" @click="$emit('edit')">
			<PencilIcon :size="20" />
		</CbIconButton>
		<CbIconButton v-if="canDelete" :aria-label="deleteLabel" :title="deleteLabel" @click="$emit('delete')">
			<DeleteOutlineIcon :size="20" />
		</CbIconButton>
		<CbIconButton v-if="canHide" :aria-label="hideLabel" :title="hideLabel" @click="$emit('hide')">
			<EyeOffIcon :size="20" />
		</CbIconButton>
		<CbIconButton v-if="canUnhide" :aria-label="unhideLabel" :title="unhideLabel" @click="$emit('unhide')">
			<PlusIcon :size="20" />
		</CbIconButton>
	</div>
</template>

<script>
import CbIconButton from './CbIconButton.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import EyeOffIcon from 'vue-material-design-icons/EyeOff.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { texts } from '../l10n/texts'

export default {
	name: 'SettingsItemActions',
	components: {
		CbIconButton,
		DeleteOutlineIcon,
		EyeOffIcon,
		PencilIcon,
		PlusIcon
	},
	props: {
		canEdit: {
			type: Boolean,
			default: false
		},
		canDelete: {
			type: Boolean,
			default: false
		},
		canHide: {
			type: Boolean,
			default: false
		},
		canUnhide: {
			type: Boolean,
			default: false
		},
		editLabel: {
			type: String,
			default: () => texts.actions.edit()
		},
		deleteLabel: {
			type: String,
			default: () => texts.actions.delete()
		},
		hideLabel: {
			type: String,
			default: () => texts.actions.hide()
		},
		unhideLabel: {
			type: String,
			default: () => texts.actions.unhide()
		}
	},
	emits: ['edit', 'delete', 'hide', 'unhide']
}
</script>

<style scoped>
.settings-item-actions {
	display: flex;
	gap: 5px;
	flex-wrap: wrap;
	justify-content: flex-end;
}
</style>
