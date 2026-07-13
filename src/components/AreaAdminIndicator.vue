<template>
	<TableTooltip :text="label">
		<span class="area-admin-indicator">
			<ShieldAccountIcon :size="18" aria-hidden="true" />
		</span>
	</TableTooltip>
</template>

<script>
import ShieldAccountIcon from 'vue-material-design-icons/ShieldAccount.vue'
import TableTooltip from './TableTooltip.vue'

export default {
	name: 'AreaAdminIndicator',
	components: {
		ShieldAccountIcon,
		TableTooltip,
	},
	props: {
		label: {
			type: String,
			required: true,
		},
	},
}
</script>

<style scoped>
.area-admin-indicator {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	color: var(--color-primary-element, #0082c9);
}
</style>
