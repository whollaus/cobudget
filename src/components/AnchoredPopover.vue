<template>
	<NcPopover popover-base-class="cobudget-menu-popover" ref="popover" v-model:shown="shown">
		<template #trigger="bindings"><slot name="trigger" v-bind="bindings" /></template>
		<template #default="bindings"><slot v-bind="bindings" /></template>
	</NcPopover>
</template>

<script>
import NcPopover from '@nextcloud/vue/components/NcPopover'
import { recomputeAllPoppers } from 'floating-vue'

export default {
	name: 'AnchoredPopover',
	components: { NcPopover },
	data() {
		return { shown: false, anchorFrame: null, lastAnchorRect: null }
	},
	watch: {
		shown(value) {
			this.stopFollowingAnchor()
			if (value) this.anchorFrame = requestAnimationFrame(this.followAnchor)
		},
	},
	beforeUnmount() { this.stopFollowingAnchor() },
	methods: {
		stopFollowingAnchor() {
			if (this.anchorFrame !== null) cancelAnimationFrame(this.anchorFrame)
			this.anchorFrame = null
			this.lastAnchorRect = null
		},
		followAnchor() {
			this.anchorFrame = null
			if (!this.shown) return
			// Native resize handling watches the popup, not a moving trigger. The
			// sidebar animates its margin, so neither window nor trigger size changes.
			const anchor = this.$refs.popover?.$el
			if (anchor?.isConnected) {
				const { x, y, width, height } = anchor.getBoundingClientRect()
				const rect = [x, y, width, height].join(',')
				if (rect !== this.lastAnchorRect) {
					this.lastAnchorRect = rect
					recomputeAllPoppers()
				}
			}
			this.anchorFrame = requestAnimationFrame(this.followAnchor)
		},
	},
}
</script>
