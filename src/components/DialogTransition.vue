<template>
	<Teleport to="body">
		<Transition name="cobudget-dialog-fade" :duration="reducedMotion ? 0 : 250" appear @before-enter="rememberTrigger" @before-leave="makeInactive" @after-leave="restoreTrigger">
			<div v-if="show" class="cobudget-dialog-layer">
				<slot :return-focus="returnFocus" />
			</div>
		</Transition>
	</Teleport>
</template>

<script>
import { captureDialogFocus, trackDialogTriggers } from '../services/dialogFocus'
/**
 * Keep the rendered dialog during Vue's leave phase, even when its owning
 * state is cleared. Children use NcModal/NcDialog with container=null so their
 * native focus, close guards and layout stay inside this teleported layer.
 * No action or state reset is delayed: the leaving DOM is immediately inert.
 */
export default {
	name: 'DialogTransition',
	props: {
		show: { type: Boolean, required: true },
		returnTarget: { default: null },
	},
	data() { return { reducedMotion: false } },
	beforeMount() {
		this.stopTrackingTriggers = trackDialogTriggers(document)
		this.motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)')
		this.updateMotionPreference = () => { this.reducedMotion = this.motionPreference.matches }
		this.updateMotionPreference()
		this.motionPreference.addEventListener('change', this.updateMotionPreference)
	},
	beforeUnmount() {
		this.stopTrackingTriggers?.()
		this.motionPreference?.removeEventListener('change', this.updateMotionPreference)
	},
	methods: {
		rememberTrigger(element) {
			element.inert = false
			element.removeAttribute('aria-hidden')
			this.restoreDialogFocus = captureDialogFocus(element.ownerDocument, this.returnTarget)
		},
		returnFocus() { return this.restoreDialogFocus?.() ?? false },
		restoreTrigger(element) { element.restoreDialogFocus?.() },
		makeInactive(element) {
			element.restoreDialogFocus = this.restoreDialogFocus
			element.inert = true
			element.setAttribute('aria-hidden', 'true')
		},
	},
}
</script>

<style>
.cobudget-dialog-layer {
	position: fixed;
	inset: 0;
	z-index: 9998; /* NcModal's native dialog layer; popovers remain above it. */
	pointer-events: none;
}
.cobudget-dialog-layer > * { pointer-events: auto; }
.cobudget-dialog-layer[inert],
.cobudget-dialog-layer[inert] * { pointer-events: none !important; }
/* Let NcModal run its native mask + wrapper fades on entry. The installed
   component still uses Vue 2's `modal-in-enter` selector for its scale origin;
   Vue 3 emits `modal-in-enter-from`. Keep that compatibility fix local. */
.cobudget-dialog-layer .modal-in-enter-from .modal-container { transform: scale(.9); }
.cobudget-dialog-layer .modal-out-enter-from .modal-container { transform: scale(1.1); }
/* Parent state is cleared immediately on close. Vue retains the last DOM in
   this host, so mirror NcModal's leave states on that snapshot, not the entire
   layer. Native .modal-container still owns its 300 ms transform transition. */
.cobudget-dialog-fade-leave-active .modal-mask,
.cobudget-dialog-fade-leave-active .modal-wrapper { transition: opacity 250ms ease; }
.cobudget-dialog-fade-leave-to .modal-mask,
.cobudget-dialog-fade-leave-to .modal-wrapper { opacity: 0; }
.cobudget-dialog-fade-leave-to .modal-container { transform: scale(.9); }
/* The remaining legacy confirmation shell has no native inner transitions. */
.cobudget-dialog-fade-enter-active .confirm-modal-backdrop,
.cobudget-dialog-fade-leave-active .confirm-modal-backdrop { transition: opacity 250ms ease; }
.cobudget-dialog-layer .confirm-modal { transition: transform 300ms ease; }
.cobudget-dialog-fade-enter-from .confirm-modal-backdrop,
.cobudget-dialog-fade-leave-to .confirm-modal-backdrop { opacity: 0; }
.cobudget-dialog-fade-enter-from .confirm-modal,
.cobudget-dialog-fade-leave-to .confirm-modal { transform: scale(.9); }
@media (prefers-reduced-motion: reduce) {
	.cobudget-dialog-layer .modal-mask,
	.cobudget-dialog-layer .modal-wrapper,
	.cobudget-dialog-layer .modal-container,
	.cobudget-dialog-layer .confirm-modal-backdrop,
	.cobudget-dialog-layer .confirm-modal { transition: none !important; }
	.cobudget-dialog-layer .modal-container,
	.cobudget-dialog-layer .confirm-modal { transform: none !important; }
}
</style>
