<template>
	<div
		ref="scroller"
		class="drag-scroll"
		:class="{ 'is-dragging': isDragging, 'show-scrollbar': showScrollbar, 'native-touch': nativeTouch }"
		tabindex="0"
		@pointerdown="onPointerDown"
		@pointermove="onPointerMove"
		@pointerup="endDrag"
		@pointercancel="endDrag"
		@pointerleave="endDrag"
		@click.capture="onClickCapture"
		@dragstart="onDragStart"
		@keydown="onKeydown">
		<slot />
	</div>
</template>

<script>
export default {
	name: 'DraggableScroller',
	props: {
		allowLinkDrag: { type: Boolean, default: false },
		interactiveDragSelector: { type: String, default: '' },
		showScrollbar: { type: Boolean, default: false },
		nativeTouch: { type: Boolean, default: false },
	},
	data() {
		return {
			isDragging: false,
			dragStartX: 0,
			dragStartScrollLeft: 0,
			suppressClick: false,
			pointerId: null
		}
	},
	methods: {
		onDragStart(event) { if (this.allowLinkDrag) event.preventDefault() },
		onPointerDown(event) {
			this.suppressClick = false;
			if (this.nativeTouch && event.pointerType !== 'mouse') return;
			if (event.target.closest('[data-drag-scroll-ignore]')) return;
			if (event.pointerType === 'mouse' && event.button !== 0) {
				return;
			}

			const control = event.target.closest(this.allowLinkDrag ? 'button, input, select, textarea, [role="button"]' : 'button, a, input, select, textarea, [role="button"], [role="link"]');
			if (control && (!this.interactiveDragSelector || !control.matches(this.interactiveDragSelector))) {
				return;
			}

			const scroller = this.$refs.scroller;
			if (!scroller || scroller.scrollWidth <= scroller.clientWidth) {
				return;
			}

			this.isDragging = true;
			this.dragStartX = event.clientX;
			this.dragStartScrollLeft = scroller.scrollLeft;
			this.suppressClick = false;
			this.pointerId = event.pointerId;
			if (!this.allowLinkDrag && !this.interactiveDragSelector) scroller.setPointerCapture && scroller.setPointerCapture(event.pointerId);
		},
		onPointerMove(event) {
			if (!this.isDragging || event.pointerId !== this.pointerId) {
				return;
			}

			const deltaX = event.clientX - this.dragStartX;
			if (!this.suppressClick) {
				if (Math.abs(deltaX) <= 4) return;
				this.suppressClick = true;
				if (this.allowLinkDrag || this.interactiveDragSelector) this.$refs.scroller.setPointerCapture?.(event.pointerId);
			}
			event.preventDefault();
			this.$refs.scroller.scrollLeft = this.dragStartScrollLeft - deltaX;
		},
		endDrag() {
			if (!this.isDragging) {
				return;
			}

			const scroller = this.$refs.scroller;
			if (scroller && scroller.releasePointerCapture && this.pointerId !== null) {
				try {
					scroller.releasePointerCapture(this.pointerId);
				} catch (error) {
					// Pointer capture may already be gone when the pointer leaves the window.
				}
			}
			this.isDragging = false;
			this.pointerId = null;
		},
		onClickCapture(event) {
			if (!this.suppressClick || event.detail === 0) {
				return;
			}

			this.suppressClick = false;
			event.preventDefault();
			event.stopPropagation();
		},
		onKeydown(event) {
			if (event.defaultPrevented || event.target !== event.currentTarget) return;
			if (event.key === 'ArrowLeft') {
				event.preventDefault();
				this.scrollByKeyboard(-1);
			} else if (event.key === 'ArrowRight') {
				event.preventDefault();
				this.scrollByKeyboard(1);
			}
		},
		scrollByKeyboard(direction) {
			const scroller = this.$refs.scroller;
			if (!scroller) {
				return;
			}

			scroller.scrollBy({
				left: direction * 280,
				behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
			});
		}
	}
}
</script>

<style scoped>
.drag-scroll {
	overflow-x: auto;
	overflow-y: hidden;
	cursor: grab;
	touch-action: pan-y;
}

.drag-scroll:not(.show-scrollbar) { scrollbar-width: none; -ms-overflow-style: none; }
.drag-scroll.native-touch { touch-action: auto; }

.drag-scroll:not(.show-scrollbar)::-webkit-scrollbar {
	display: none;
}

.drag-scroll.is-dragging {
	cursor: grabbing;
	user-select: none;
}

.drag-scroll:focus {
	outline: none;
}

.drag-scroll:focus-visible {
	outline: var(--cobudget-focus-outline);
	outline-offset: -2px;
}
</style>
