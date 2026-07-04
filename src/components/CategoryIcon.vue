<template>
	<component :is="iconName" :size="size" class="category-icon" />
</template>

<script>
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
	name: 'CategoryIcon',
	components: {
		Airplane, BabyCarriage, Bank, Briefcase, Car, Cart, Cash, Cellphone, Dog,
		Dumbbell, Flash, GamepadVariant, Gift, Heart, HelpCircle, Home, School, Shape, Sofa,
		Television, Train, TshirtCrew, Water, Wrench, FoodApple, SilverwareForkKnife, Sprout, Tree, Cat, Paw, Run, Bike, MusicNote, Pill, GasStation, BookOpenVariant
	},
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
			const validIcons = [
				'Shape', 'Home', 'Cart', 'Car', 'Train', 'Flash',
				'Water', 'Briefcase', 'Heart', 'Dumbbell', 'School', 'BabyCarriage',
				'Dog', 'Sofa', 'Television', 'GamepadVariant', 'Airplane', 'Gift',
				'TshirtCrew', 'Cellphone', 'Wrench', 'Bank', 'Cash', 'HelpCircle',
				'FoodApple', 'SilverwareForkKnife', 'Sprout', 'Tree', 'Cat', 'Paw',
				'Run', 'Bike', 'MusicNote', 'Pill', 'GasStation', 'BookOpenVariant'
			];
			return validIcons.includes(this.icon) ? this.icon : 'Shape';
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
	color: var(--color-primary, #01679e);
	border-radius: 999px;
	background-color: var(--color-primary-light, rgb(211 224 236));
	padding: 5px;
	margin-right: 8px;
}
</style>
