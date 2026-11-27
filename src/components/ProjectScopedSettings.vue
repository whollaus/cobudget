<template>
	<MasterDataSettings :project-id="projectId" :section-kind="sectionKind" :incomes="$enableIncomes" @changed="$emit('changed')" />
</template>
<script>
import MasterDataSettings from './MasterDataSettings.vue'
export default {
	name: 'ProjectScopedSettings',
	components: { MasterDataSettings },
	props: {
		projectId: { type: [Number, String], required: true },
		sectionKind: { type: String, default: '' },
	},
	emits: ['changed'],
}
</script>
