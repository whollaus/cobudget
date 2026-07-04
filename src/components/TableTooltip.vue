<template>
	<span
		ref="trigger"
		class="table-tooltip-trigger"
		:aria-label="text || null"
		:aria-describedby="isVisible ? tooltipId : null"
		tabindex="0"
		@mouseenter="showTooltip"
		@mouseleave="hideTooltip"
		@focus="showTooltip"
		@blur="hideTooltip"
		@mousedown.stop
		@touchstart.stop
		@click.stop.prevent="toggleTooltip"
		@keydown.enter.stop.prevent="toggleTooltip"
		@keydown.space.stop.prevent="toggleTooltip">
		<slot />
	</span>
	<Teleport to="body">
		<div
			v-if="isVisible && text"
			:id="tooltipId"
			ref="tooltip"
			class="table-tooltip"
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
let nextTooltipId = 0

export default {
	name: 'TableTooltip',
	props: {
		text: {
			type: String,
			default: ''
		}
	},
	data() {
		return {
			isVisible: false,
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
	beforeUnmount() {
		this.detachListeners()
	},
	methods: {
		showTooltip() {
			if (!this.text) {
				return
			}
			this.isVisible = true
			this.$nextTick(() => {
				this.updatePosition()
				this.attachListeners()
			})
		},
		hideTooltip() {
			this.isVisible = false
			this.detachListeners()
		},
		toggleTooltip() {
			if (!this.text) {
				return
			}

			if (this.isVisible) {
				this.hideTooltip()
				return
			}

			this.showTooltip()
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
	box-shadow: 0 0 0 2px var(--color-primary-element-light, rgba(0, 130, 201, 0.25));
}

.table-tooltip {
	position: fixed;
	z-index: 10050;
	width: max-content;
	max-width: min(520px, calc(100vw - 16px));
	padding: 6px 10px;
	border-radius: var(--border-radius, 6px);
	background: var(--color-main-text, #222);
	color: var(--cobudget-page-background, #fff);
	font-size: var(--cobudget-font-sm);
	font-weight: 600;
	line-height: 1.35;
	pointer-events: none;
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
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

.table-tooltip-line:first-child .table-tooltip-label {
	font-weight: 800;
}

.table-tooltip-value {
	flex: 0 0 auto;
	white-space: nowrap;
}
</style>
