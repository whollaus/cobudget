<template>
	<CbButton
		:variant="variant"
		:size="size"
		:disabled="disabled"
		:busy="busy"
		:aria-label="ariaLabel"
		:title="title || ariaLabel"
		icon-only
		@click="$emit('click', $event)">
		<template #icon>
			<slot />
		</template>
	</CbButton>
</template>

<script>
import CbButton from './CbButton.vue'

export default {
	name: 'CbIconButton',
	components: {
		CbButton
	},
	props: {
		variant: {
			type: String,
			default: 'ghost'
		},
		size: {
			type: String,
			default: 'normal'
		},
		disabled: {
			type: Boolean,
			default: false
		},
		busy: {
			type: Boolean,
			default: false
		},
		ariaLabel: {
			type: String,
			required: true
		},
		title: {
			type: String,
			default: ''
		}
	},
	emits: ['click']
}
</script>
