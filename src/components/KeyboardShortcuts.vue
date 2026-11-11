<template>
	<section class="settings-subsection keyboard-shortcuts" aria-labelledby="keyboard-shortcuts-title">
		<h3 id="keyboard-shortcuts-title" class="cobudget-section-heading">{{ $texts.shortcuts.title() }}</h3>
		<SettingsList :items="rows" borderless>
			<template #item="{ item }">
				<span>{{ item.label }}</span>
				<span class="keyboard-shortcuts__keys"><NcKbd v-for="key in item.keys" :key="key" :symbol="key" /></span>
			</template>
		</SettingsList>
		<p class="settings-hint">{{ $texts.shortcuts.hint() }}</p>
	</section>
</template>

<script>
import NcKbd from '@nextcloud/vue/components/NcKbd'
import SettingsList from './SettingsList.vue'

export default {
	name: 'KeyboardShortcuts',
	components: { NcKbd, SettingsList },
	computed: {
		rows() {
			return [
				{ id: 'save', label: this.$texts.shortcuts.save(), keys: ['Control', 'S'] },
				{ id: 'new', label: this.$texts.shortcuts.newPayment(), keys: ['Control', 'N'] },
				{ id: 'new-alt', label: this.$texts.shortcuts.newAlternative(), keys: ['Alt', 'N'] },
				{ id: 'print', label: this.$texts.shortcuts.print(), keys: ['Control', 'P'] },
				{ id: 'close', label: this.$texts.shortcuts.close(), keys: ['Escape'] },
			]
		},
	},
}
</script>

<style scoped>
.keyboard-shortcuts__keys { display: flex; gap: 4px; flex-shrink: 0; }
.keyboard-shortcuts :deep(.settings-list-item) { gap: 16px; }
</style>
