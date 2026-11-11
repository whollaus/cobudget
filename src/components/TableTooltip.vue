<template>
	<span
		ref="trigger"
		class="table-tooltip-trigger"
		:aria-label="text || null"
		:aria-describedby="isVisible ? tooltipId : null"
		:tabindex="passive ? undefined : 0"
		@mouseenter="visibility.enter()"
		@mouseleave="visibility.leave()"
		@focusin="visibility.focus()"
		@focusout="visibility.blur()"
		@mousedown.stop
		@touchstart.stop
		@click="toggleTooltip"
		@keydown.enter="toggleTooltip"
		@keydown.space="toggleTooltip"
		@keydown.esc.stop.prevent="visibility.hide()">
		<slot />
	</span>
	<Teleport to="body">
		<div
			v-if="isVisible && text"
			:id="tooltipId"
			ref="tooltip"
			class="table-tooltip cobudget-tooltip-surface"
			role="tooltip"
			:style="tooltipStyle">
			<div
				v-for="(line, index) in tooltipLines"
				:key="index"
				class="table-tooltip-line"
				:class="{ 'has-value': line.value }">
				<span class="table-tooltip-label">{{ line.label }}</span>
				<span v-if="line.value" class="table-tooltip-value">{{ line.value }}</span>
			</div>
		</div>
	</Teleport>
</template>

<script>
import { createTooltipVisibility } from '../services/tooltipVisibility'
let nextTooltipId = 0

export default {
	name: 'TableTooltip',
	props: {
		passive: { type: Boolean, default: false },
		text: {
			type: String,
			default: ''
		}
	},
	data() {
		return {
			isVisible: false,
			visibility: null,
			tooltipId: `table-tooltip-${++nextTooltipId}`,
			tooltipStyle: {
				left: '0px',
				top: '0px'
			}
		}
	},
	computed: {
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
			if (!this.text) {
				return
			}
			this.isVisible = true
			this.$nextTick(() => {
				if (!this.isVisible || !this.$refs.tooltip) return
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
			this.visibility.toggle()
		},
		attachListeners() {
			window.addEventListener('resize', this.updatePosition)
			window.addEventListener('scroll', this.updatePosition, true)
		},
		detachListeners() {
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

			this.tooltipStyle = {
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
