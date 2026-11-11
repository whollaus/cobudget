<template>
	<MasterDataSettings :project-id="projectId" :incomes="$enableIncomes" @changed="$emit('changed')" />
</template>
<script>
import MasterDataSettings from './MasterDataSettings.vue'
export default {
	name: 'ProjectScopedSettings',
	components: { MasterDataSettings },
	props: { projectId: { type: [Number, String], required: true } },
	emits: ['changed'],
}
</script>
