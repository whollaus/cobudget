<template>
	<component :is="iconName" :size="size" class="category-icon" />
</template>

<script>
import { categoryIcons, categoryIconNames } from './categoryIcons'

export default {
	name: 'CategoryIcon',
	components: categoryIcons,
	props: {
		icon: {
			type: String,
			default: 'Shape'
		},
		size: {
			type: Number,
			default: 18
		}
	},
	computed: {
		iconName() {
			return categoryIconNames.includes(this.icon) ? this.icon : 'Shape';
		}
	}
}
</script>

<style scoped>
.category-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	vertical-align: middle;
	color: var(--cobudget-info-text);
	border-radius: 999px;
	background-color: transparent;
	margin-right: 4px;
}
</style>
