<template>
	<NcPopover v-if="reason" v-bind="$attrs" v-model:shown="shown" class="disabled-action-hint" popover-base-class="cobudget-tooltip-popover" placement="top-start" no-focus-trap :triggers="[]" :popover-triggers="[]">
		<template #trigger="{ attrs }">
			<span v-bind="attrs" class="disabled-action-hint__trigger" tabindex="0" role="button" aria-disabled="true" :aria-label="`${label}: ${reason}`" @mouseenter="visibility.enter()" @mouseleave="visibility.leave()" @focus="visibility.focus()" @blur="visibility.blur()" @click.stop.prevent="visibility.show()" @keydown.enter.stop.prevent="visibility.show()" @keydown.space.stop.prevent="visibility.show()" @keydown.esc.stop.prevent="visibility.hide()">
				<slot />
			</span>
		</template>
		<p class="disabled-action-hint__text" role="tooltip">{{ reason }}</p>
	</NcPopover>
	<slot v-else />
</template>

<script>
import { createTooltipVisibility } from '../services/tooltipVisibility'
import NcPopover from '@nextcloud/vue/components/NcPopover'

export default {
	name: 'DisabledActionHint',
	inheritAttrs: false,
	components: { NcPopover },
	props: {
		reason: { type: String, default: '' },
		label: { type: String, required: true },
	},
	data: () => ({ shown: false, visibility: null }),
	created() {
		this.visibility = createTooltipVisibility({ isVisible: () => this.shown, setVisible: value => { this.shown = value } })
	},
	beforeUnmount() { this.visibility.dispose() },
	watch: { reason() { this.visibility.hide() } },
}
</script>

<style scoped>
.disabled-action-hint { display: inline-flex; max-width: 100%; }
.disabled-action-hint__trigger { display: inline-flex; width: 100%; min-width: 0; border-radius: var(--border-radius-element); }
.disabled-action-hint__trigger:focus-visible { outline: 2px solid var(--color-main-text); outline-offset: 2px; }
.disabled-action-hint__trigger :deep(button:disabled) { pointer-events: none; }
.disabled-action-hint__text { margin: 0; padding: var(--cobudget-space-3, 12px); max-width: min(320px, calc(100vw - 48px)); overflow-wrap: anywhere; }
</style>
