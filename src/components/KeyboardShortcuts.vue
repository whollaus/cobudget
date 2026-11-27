<template>
	<div class="keyboard-shortcuts">
		<NcHotkeyList>
			<NcHotkey v-for="row in rows" :key="row.id" :hotkey="row.hotkey" :label="row.label" />
		</NcHotkeyList>
		<p class="keyboard-shortcuts-hint">{{ $texts.shortcuts.hint() }}</p>
	</div>
</template>

<script>
import NcHotkey from '@nextcloud/vue/components/NcHotkey'
import NcHotkeyList from '@nextcloud/vue/components/NcHotkeyList'

export default {
	name: 'KeyboardShortcuts',
	components: { NcHotkey, NcHotkeyList },
	computed: {
		rows() {
			return [
				{ id: 'save', label: this.$texts.shortcuts.save(), hotkey: 'Control S' },
				{ id: 'new', label: this.$texts.shortcuts.newPayment(), hotkey: 'Control N' },
				{ id: 'new-alt', label: this.$texts.shortcuts.newAlternative(), hotkey: 'Alt N' },
				{ id: 'print', label: this.$texts.shortcuts.print(), hotkey: 'Control P' },
				{ id: 'close', label: this.$texts.shortcuts.close(), hotkey: 'Escape' },
			]
		},
	},
}
</script>

<style scoped>
.keyboard-shortcuts-hint {
	margin-block-start: var(--cobudget-content-gap);
	color: var(--cobudget-text-muted);
}
</style>
