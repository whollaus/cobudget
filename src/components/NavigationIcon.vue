<template>
	<component :is="icon" :size="size" aria-hidden="true" />
</template>

<script>
import CoBudgetIcon from './CoBudgetIcon.vue'
import Minus from 'vue-material-design-icons/Minus.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import CalendarSyncOutline from 'vue-material-design-icons/CalendarSyncOutline.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
import ClipboardCheckOutline from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import BankOutline from 'vue-material-design-icons/BankOutline.vue'
import AccountChildOutline from 'vue-material-design-icons/AccountChildOutline.vue'
import Sync from 'vue-material-design-icons/Sync.vue'
import ReceiptTextCheckOutline from 'vue-material-design-icons/ReceiptTextCheckOutline.vue'
import ShieldCheckOutline from 'vue-material-design-icons/ShieldCheckOutline.vue'
import ChartLine from 'vue-material-design-icons/ChartLine.vue'
import WalletOutline from 'vue-material-design-icons/WalletOutline.vue'
import ViewGridOutline from 'vue-material-design-icons/ViewGridOutline.vue'
import CogOutline from 'vue-material-design-icons/CogOutline.vue'

import CalendarRemoveOutline from 'vue-material-design-icons/CalendarRemoveOutline.vue'
import CashCheck from 'vue-material-design-icons/CashCheck.vue'
import BellOutline from 'vue-material-design-icons/BellOutline.vue'

const icons = {
	cancel: CalendarRemoveOutline,
	renew: Sync,
	pay: CashCheck,
	reminders: BellOutline,
	'': CoBudgetIcon,
	income: Plus,
	expense: Minus,
	future: CalendarSyncOutline,
	important: StarOutline,
	review: ClipboardCheckOutline,
	fixedCost: BankOutline,
	childRelated: AccountChildOutline,
	subscription: Sync,
	taxRelevant: ReceiptTextCheckOutline,
	insurance: ShieldCheckOutline,
	'annual-overview': ChartLine,
	budgets: WalletOutline,
	projects: ViewGridOutline,
	settings: CogOutline,
}

export default {
	name: 'NavigationIcon',
	props: {
		name: { type: String, default: '' },
		size: { type: Number, default: 20 },
	},
	computed: {
		icon() { return Object.hasOwn(icons, this.name) ? icons[this.name] : null },
	},
}
</script>
