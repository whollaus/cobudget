<template>
	<component :is="withoutArea ? 'FolderOutlineIcon' : globalScope ? 'WalletIcon' : 'FolderIcon'" class="area-icon" :size="size" :fill-color="color || (withoutArea ? 'currentColor' : 'var(--color-primary-element)')" aria-hidden="true" />
</template>

<script>
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import WalletIcon from 'vue-material-design-icons/WalletOutline.vue'

export default {
	name: 'AreaIcon',
	components: { FolderIcon, FolderOutlineIcon, WalletIcon },
	props: {
		color: { type: String, default: '' },
		withoutArea: { type: Boolean, default: false },
		globalScope: { type: Boolean, default: false },
		size: { type: Number, default: 20 },
	},
}
</script>

<style scoped>
.area-icon { flex-shrink: 0; }
</style>
