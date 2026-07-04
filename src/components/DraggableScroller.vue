<template>
	<div
		ref="scroller"
		class="drag-scroll"
		:class="{ 'is-dragging': isDragging }"
		tabindex="0"
		@pointerdown="onPointerDown"
		@pointermove="onPointerMove"
		@pointerup="endDrag"
		@pointercancel="endDrag"
		@pointerleave="endDrag"
		@click.capture="onClickCapture"
		@keydown="onKeydown">
		<slot />
	</div>
</template>

<script>
export default {
	name: 'DraggableScroller',
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
		onPointerDown(event) {
			if (event.pointerType === 'mouse' && event.button !== 0) {
				return;
			}

			if (event.target.closest('button, a, input, select, textarea, [role="button"], [role="link"]')) {
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
			scroller.setPointerCapture && scroller.setPointerCapture(event.pointerId);
		},
		onPointerMove(event) {
			if (!this.isDragging) {
				return;
			}

			const deltaX = event.clientX - this.dragStartX;
			if (Math.abs(deltaX) > 4) {
				this.suppressClick = true;
				event.preventDefault();
			}
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

			if (this.suppressClick) {
				window.setTimeout(() => {
					this.suppressClick = false;
				}, 50);
			}
		},
		onClickCapture(event) {
			if (!this.suppressClick) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
		},
		onKeydown(event) {
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
				behavior: 'smooth'
			});
		}
	}
}
</script>

<style scoped>
.drag-scroll {
	overflow-x: auto;
	overflow-y: hidden;
	scrollbar-width: none;
	-ms-overflow-style: none;
	cursor: grab;
	touch-action: pan-y;
}

.drag-scroll::-webkit-scrollbar {
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
	box-shadow: 0 0 0 2px var(--color-primary-element, var(--color-primary, #0082c9));
}
</style>
