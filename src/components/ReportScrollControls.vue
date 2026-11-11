<template>
	<div v-if="navigation?.overflow" class="report-scroll-controls no-print" role="group" :aria-label="$texts.annual.scrollTables()">
		<NcButton variant="tertiary" :disabled="!navigation.canScrollLeft" :aria-controls="navigation.controls"
			:aria-label="$texts.annual.scrollTablesLeft()" :title="$texts.annual.scrollTablesLeft()" @click="navigation.scroll(-1)">
			<template #icon><ChevronLeftIcon :size="20" /></template>
		</NcButton>
		<NcButton variant="tertiary" :disabled="!navigation.canScrollRight" :aria-controls="navigation.controls"
			:aria-label="$texts.annual.scrollTablesRight()" :title="$texts.annual.scrollTablesRight()" @click="navigation.scroll(1)">
			<template #icon><ChevronRightIcon :size="20" /></template>
		</NcButton>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'

export default {
	name: 'ReportScrollControls',
	components: { NcButton, ChevronLeftIcon, ChevronRightIcon },
	props: { navigation: { type: Object, default: null } },
}
</script>

<style scoped>
.report-scroll-controls { display: inline-flex; align-items: center; flex-shrink: 0; }
@media (max-width: 768px) {
	.report-scroll-controls { --default-clickable-area: var(--cobudget-mobile-touch-size, 44px); }
}
</style>
