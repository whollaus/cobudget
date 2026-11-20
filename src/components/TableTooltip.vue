<template>
	<span
		ref="trigger"
		class="table-tooltip-trigger"
		:role="hasMessages ? 'button' : undefined"
		:aria-expanded="hasMessages ? isVisible : undefined"
		:aria-label="description || null"
		:aria-describedby="isVisible && !hasMessages ? tooltipId : null"
		:aria-controls="isVisible && hasMessages ? tooltipId : null"
		:tabindex="passive ? undefined : 0"
		@mouseenter="visibility.enter()"
		@mouseleave="visibility.leave()"
		@focusin="visibility.focus()"
		@focusout="onFocusOut"
		@pointerdown="pointerWasVisible = isVisible"
		@mousedown.stop
		@touchstart.stop
		@click="toggleTooltip"
		@keydown.enter="toggleTooltip"
		@keydown.space="toggleTooltip"
		@keydown.esc.stop.prevent="visibility.hide()">
		<slot />
	</span>
	<Teleport to="body" :disabled="hasMessages">
		<div
			v-if="isVisible && description"
			:id="tooltipId"
			ref="tooltip"
			class="table-tooltip cobudget-tooltip-surface"
			:role="hasMessages ? 'region' : 'tooltip'"
			:aria-label="hasMessages ? messageLines[0]?.message : undefined"
			:popover="hasMessages ? 'manual' : undefined"
			@focusin="hasMessages && visibility.focus()"
			@focusout="onFocusOut"
			@keydown.esc.stop.prevent="dismissPanel"
			:class="{ 'table-tooltip-messages': hasMessages }"
			@mouseenter="hasMessages && visibility.enter()"
			@mouseleave="hasMessages && visibility.leave()"
			:style="tooltipStyle">
			<div v-if="hasMessages" class="table-tooltip-plain">
				<div v-for="(line, index) in messageLines" :key="index">
					<template v-if="line.href">{{ line.before }}<a :href="line.href" target="_blank" rel="noopener noreferrer">{{ line.label }}</a>{{ line.after }}</template>
					<template v-else>{{ line.message }}</template>
				</div>
			</div>
			<template v-else>
				<div
					v-for="(line, index) in tooltipLines"
					:key="index"
					class="table-tooltip-line"
					:class="{ 'has-value': line.value }">
					<span class="table-tooltip-label">{{ line.label }}</span>
					<span v-if="line.value" class="table-tooltip-value">{{ line.value }}</span>
				</div>
			</template>
		</div>
	</Teleport>
</template>

<script>
import { createTooltipVisibility } from '../services/tooltipVisibility'
let nextTooltipId = 0

export default {
	name: 'TableTooltip',
	props: {
		messageLines: { type: Array, default: () => [] },
		passive: { type: Boolean, default: false },
		text: {
			type: String,
			default: ''
		}
	},
	data() {
		return {
			isVisible: false,
			pointerWasVisible: false,
			visibility: null,
			tooltipId: `table-tooltip-${++nextTooltipId}`,
			tooltipStyle: {
				left: '0px',
				top: '0px'
			}
		}
	},
	computed: {
		hasMessages() { return this.messageLines.length > 0 },
		description() {
			return this.hasMessages ? this.messageLines.map(line => line.message).join('\n') : this.text
		},
		tooltipLines() {
			return String(this.text || '')
				.split('\n')
				.filter(line => line.trim() !== '')
				.map(line => {
					const separator = line.indexOf(': ')
					if (separator === -1) {
						return { label: line, value: '' }
					}

					return {
						label: `${line.slice(0, separator)}:`,
						value: line.slice(separator + 2)
					}
				})
		}
	},
	created() {
		this.visibility = createTooltipVisibility({
			isVisible: () => this.isVisible,
			setVisible: value => value ? this.showTooltip() : this.hideTooltip(),
		})
	},
	beforeUnmount() {
		this.visibility.dispose()
		this.detachListeners()
	},
	methods: {
		showTooltip() {
			if (!this.description) {
				return
			}
			this.isVisible = true
			this.$nextTick(() => {
				if (!this.isVisible || !this.$refs.tooltip) return
				// Keep links next to the timestamp in tab order and above scrolling containers.
				if (this.hasMessages) this.$refs.tooltip.showPopover?.()
				this.updatePosition()
				this.attachListeners()
			})
		},
		hideTooltip() {
			this.isVisible = false
			this.detachListeners()
		},
		toggleTooltip(event) {
			if (this.passive) return
			event?.stopPropagation()
			event?.preventDefault()
			// Focus can open the tooltip between the first tap and its click.
			if (event?.type === 'click' && event.detail > 0) {
				if (this.pointerWasVisible) this.visibility.hide()
				else this.visibility.show()
			} else {
				this.visibility.toggle()
			}
		},
		onFocusOut(event) {
			if (this.hasMessages && (this.$refs.tooltip?.contains(event.relatedTarget) || this.$refs.trigger?.contains(event.relatedTarget))) return
			this.visibility.blur()
		},
		dismissPanel() {
			this.$refs.trigger?.focus({ preventScroll: true })
			this.visibility.hide()
		},
		onOutsidePointer(event) {
			if (!this.$refs.tooltip?.contains(event.target) && !this.$refs.trigger?.contains(event.target)) this.visibility.hide()
		},
		attachListeners() {
			if (this.hasMessages) document.addEventListener('pointerdown', this.onOutsidePointer)
			window.addEventListener('resize', this.updatePosition)
			window.addEventListener('scroll', this.updatePosition, true)
		},
		detachListeners() {
			if (this.hasMessages) document.removeEventListener('pointerdown', this.onOutsidePointer)
			window.removeEventListener('resize', this.updatePosition)
			window.removeEventListener('scroll', this.updatePosition, true)
		},
		updatePosition() {
			const trigger = this.$refs.trigger
			const tooltip = this.$refs.tooltip
			if (!trigger || !tooltip) {
				return
			}

			const triggerRect = trigger.getBoundingClientRect()
			const tooltipRect = tooltip.getBoundingClientRect()
			const gap = 8
			const viewportPadding = 8
			let left = triggerRect.left + (triggerRect.width / 2) - (tooltipRect.width / 2)
			left = Math.max(viewportPadding, Math.min(left, window.innerWidth - tooltipRect.width - viewportPadding))

			let top = triggerRect.top - tooltipRect.height - gap
			if (top < viewportPadding) {
				top = triggerRect.bottom + gap
			}

			let maxHeight
			if (this.hasMessages) {
				const above = triggerRect.top - gap - viewportPadding
				const below = window.innerHeight - triggerRect.bottom - gap - viewportPadding
				const height = tooltip.scrollHeight + tooltip.offsetHeight - tooltip.clientHeight
				const useAbove = height <= above || above >= below
				const space = Math.max(0, useAbove ? above : below)
				maxHeight = space + 'px'
				top = useAbove ? triggerRect.top - gap - Math.min(height, space) : triggerRect.bottom + gap
			}
			this.tooltipStyle = {
				maxHeight,
				left: `${left}px`,
				top: `${top}px`
			}
		}
	}
}
</script>

<style scoped>
.table-tooltip-trigger {
	display: inline-flex;
	align-items: center;
	min-width: 0;
	outline: none;
}

.table-tooltip-trigger:focus-visible {
	border-radius: var(--border-radius, 4px);
	outline: var(--cobudget-focus-outline);
	outline-offset: -2px;
}

.table-tooltip {
	position: fixed;
	z-index: 10050;
	width: max-content;
	max-width: min(520px, calc(100vw - 16px));
	padding: 6px 10px;
	border-radius: var(--border-radius, 6px);
	pointer-events: none;
}

.table-tooltip-messages { inset: auto; margin: 0; text-align: start; box-sizing: border-box; overflow-y: auto; pointer-events: auto; }
.table-tooltip-plain a { color: inherit; text-decoration: underline; }
.table-tooltip-plain { white-space: pre-line; overflow-wrap: anywhere; }

.table-tooltip-line {
	display: block;
	min-width: 0;
}

.table-tooltip-line + .table-tooltip-line {
	margin-top: 2px;
}

.table-tooltip-line.has-value {
	display: flex;
	gap: 12px;
	align-items: flex-start;
	justify-content: space-between;
}

.table-tooltip-label {
	min-width: 0;
}

.table-tooltip-value {
	flex: 0 0 auto;
	white-space: nowrap;
}
</style>
