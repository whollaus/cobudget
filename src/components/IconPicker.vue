<template>
	<NcPopover placement="bottom-start" :close-on-click-outside="true" :disabled="disabled">
		<template #trigger>
			<button class="icon-picker-btn" :class="{ 'has-icon': value, 'is-disabled': disabled }" type="button" @click.prevent
				:title="disabled ? $texts.iconPicker.adminManaged() : $texts.iconPicker.selectIcon()" :disabled="disabled">
				<component :is="value || 'Shape'" :size="20" class="current-icon" />
			</button>
		</template>

		<div class="icon-picker-grid">
			<button v-for="icon in icons" :key="icon" class="icon-option-btn" :class="{ active: icon === value }"
				type="button" @click="selectIcon(icon)" :title="icon">
				<component :is="icon" :size="24" />
			</button>
		</div>
	</NcPopover>
</template>

<script>
import NcPopover from '@nextcloud/vue/components/NcPopover'
import Airplane from 'vue-material-design-icons/Airplane.vue'
import BabyCarriage from 'vue-material-design-icons/BabyCarriage.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import Briefcase from 'vue-material-design-icons/Briefcase.vue'
import Car from 'vue-material-design-icons/Car.vue'
import Cart from 'vue-material-design-icons/Cart.vue'
import Cash from 'vue-material-design-icons/Cash.vue'
import Cellphone from 'vue-material-design-icons/Cellphone.vue'
import Dog from 'vue-material-design-icons/Dog.vue'
import Dumbbell from 'vue-material-design-icons/Dumbbell.vue'
import Flash from 'vue-material-design-icons/Flash.vue'
import GamepadVariant from 'vue-material-design-icons/GamepadVariant.vue'
import Gift from 'vue-material-design-icons/Gift.vue'
import Heart from 'vue-material-design-icons/Heart.vue'
import HelpCircle from 'vue-material-design-icons/HelpCircle.vue'
import Home from 'vue-material-design-icons/Home.vue'
import School from 'vue-material-design-icons/School.vue'
import Shape from 'vue-material-design-icons/Shape.vue'
import Sofa from 'vue-material-design-icons/Sofa.vue'
import Television from 'vue-material-design-icons/Television.vue'
import Train from 'vue-material-design-icons/Train.vue'
import TshirtCrew from 'vue-material-design-icons/TshirtCrew.vue'
import Water from 'vue-material-design-icons/Water.vue'
import FoodApple from 'vue-material-design-icons/FoodApple.vue'
import SilverwareForkKnife from 'vue-material-design-icons/SilverwareForkKnife.vue'
import Sprout from 'vue-material-design-icons/Sprout.vue'
import Tree from 'vue-material-design-icons/Tree.vue'
import Cat from 'vue-material-design-icons/Cat.vue'
import Paw from 'vue-material-design-icons/Paw.vue'
import Run from 'vue-material-design-icons/Run.vue'
import Bike from 'vue-material-design-icons/Bike.vue'
import MusicNote from 'vue-material-design-icons/MusicNote.vue'
import Pill from 'vue-material-design-icons/Pill.vue'
import GasStation from 'vue-material-design-icons/GasStation.vue'
import BookOpenVariant from 'vue-material-design-icons/BookOpenVariant.vue'
import Wrench from 'vue-material-design-icons/Wrench.vue'

export default {
	name: 'IconPicker',
	components: {
		NcPopover,
		Airplane, BabyCarriage, Bank, Briefcase, Car, Cart, Cash, Cellphone, Dog,
		Dumbbell, Flash, GamepadVariant, Gift, Heart, HelpCircle, Home, School, Shape, Sofa,
		Television, Train, TshirtCrew, Water, Wrench, FoodApple, SilverwareForkKnife, Sprout, Tree, Cat, Paw, Run, Bike, MusicNote, Pill, GasStation, BookOpenVariant
	},
	props: {
		value: {
			type: String,
			default: 'Shape'
		},
		disabled: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			icons: [
				'Shape', 'Home', 'Cart', 'Car', 'Train', 'Flash',
				'Water', 'Briefcase', 'Heart', 'Dumbbell', 'School', 'BabyCarriage',
				'Dog', 'Sofa', 'Television', 'GamepadVariant', 'Airplane', 'Gift',
				'TshirtCrew', 'Cellphone', 'Wrench', 'Bank', 'Cash', 'HelpCircle',
				'FoodApple', 'SilverwareForkKnife', 'Sprout', 'Tree', 'Cat', 'Paw',
				'Run', 'Bike', 'MusicNote', 'Pill', 'GasStation', 'BookOpenVariant'
			]
		}
	},
	methods: {
		selectIcon(icon) {
			this.$emit('input', icon);
		}
	}
}
</script>

<style scoped>
.icon-picker-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	vertical-align: middle;
	color: var(--color-primary, #01679e);
	border-radius: 999px;
	background-color: var(--color-primary-light, rgb(211 224 236));
	padding: 5px;
	cursor: pointer;
	border: none;
	width: 36px;
	height: 36px;
	transition: all 0.2s;
	margin-right: 10px !important;
}

.icon-picker-btn:hover:not(.is-disabled) {
	opacity: 0.8;
}

.icon-picker-btn.is-disabled,
.icon-picker-btn.is-disabled * {
	cursor: not-allowed !important;
}

.icon-picker-btn.is-disabled {
	opacity: 0.7;
}

.icon-picker-grid {
	display: grid;
	grid-template-columns: repeat(6, 1fr);
	gap: 8px;
	padding: 12px;
	background: var(--cobudget-page-background);
}

.icon-option-btn {
	background: transparent;
	border: 1px solid transparent;
	border-radius: 4px;
	padding: 6px;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--color-text-maxcontrast);
	transition: all 0.1s;
}

.icon-option-btn:hover {
	background: var(--cobudget-surface-muted);
}

.icon-option-btn.active {
	background: var(--color-primary-light);
	color: var(--color-primary);
	border-color: var(--color-primary);
}
</style>
