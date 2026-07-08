<template>
	<div class="amount-wrapper">
		<TableTooltip
			v-if="entry.recurrence_interval"
			:text="$texts.entry.nextRecurrenceTooltip(formatDateTime(entry.recurrence_next_date))">
			<span class="shared-icon recurring-icon">
				<SyncIcon :size="16" />
			</span>
		</TableTooltip>
		<TableTooltip
			v-else-if="recurrenceHistoryTooltip"
			:text="recurrenceHistoryTooltip">
			<span class="shared-icon recurrence-history-icon">
				<SyncIcon :size="16" />
			</span>
		</TableTooltip>
		<TableTooltip
			v-if="entry.reminder_date"
			:text="reminderTooltip">
			<span class="shared-icon reminder-icon">
				<BellRingIcon :size="16" />
			</span>
		</TableTooltip>
		<TableTooltip v-if="attachmentTooltip" :text="attachmentTooltip">
			<span class="shared-icon attachment-icon">
				<PaperclipIcon :size="16" />
			</span>
		</TableTooltip>
		<TableTooltip v-if="entry.has_history" :text="$texts.entry.showHistory()">
			<button
				type="button"
				class="shared-icon history-icon"
				:aria-label="$texts.entry.showHistory()"
				@pointerdown.stop
				@click.stop="$emit('history', entry)">
				<HistoryIcon :size="16" />
			</button>
		</TableTooltip>
		<TableTooltip v-if="sharedProjectTooltip" :text="sharedProjectTooltip">
			<span class="shared-icon" :class="{ 'settled-icon': entry.is_settled }">
				<CheckCircleOutlineIcon v-if="entry.is_settled" :size="16" />
				<AccountMultipleIcon v-else :size="16" />
			</span>
		</TableTooltip>
		<TableTooltip v-if="showSettledIcon" :text="$texts.entry.settled()">
			<span class="shared-icon settled-icon">
				<CheckCircleOutlineIcon :size="16" />
			</span>
		</TableTooltip>
		<TableTooltip v-if="amountTooltip" :text="amountTooltip">
			<span class="amount-text">{{ signedAmount }}</span>
		</TableTooltip>
		<span v-else class="amount-text">{{ signedAmount }}</span>
	</div>
</template>

<script>
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import BellRingIcon from 'vue-material-design-icons/BellRing.vue'
import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import SyncIcon from 'vue-material-design-icons/Sync.vue'
import TableTooltip from './TableTooltip.vue'
import { formatMoney } from '../utils/formatMoney'

export default {
	name: 'EntryAmountCell',
	components: {
		AccountMultipleIcon,
		BellRingIcon,
		CheckCircleOutlineIcon,
		HistoryIcon,
		PaperclipIcon,
		SyncIcon,
		TableTooltip
	},
	props: {
		entry: {
			type: Object,
			required: true
		},
		amount: {
			type: [Number, String],
			required: true
		},
		currency: {
			type: String,
			default: ''
		},
		amountTooltip: {
			type: String,
			default: ''
		},
		sharedProjectTooltip: {
			type: String,
			default: ''
		},
		showSettledIcon: {
			type: Boolean,
			default: false
		}
	},
	emits: ['history'],
	computed: {
		signedAmount() {
			const amount = Math.abs(parseFloat(this.amount || 0))
			const signedAmount = this.entry.type === 'expense' ? -amount : amount
			return formatMoney(signedAmount, this.currency, { signDisplay: 'always' })
		},
		recurrenceHistoryTooltip() {
			if (!this.entry.recurrence_series_id || this.entry.recurrence_interval) {
				return ''
			}

			return this.entry.recurrence_parent_id
				? this.$texts.entry.createdFromRecurrence()
				: this.$texts.entry.recurrenceSeriesStart()
		},
		reminderTooltip() {
			const text = this.$texts.entry.reminderAt(this.formatDate(this.entry.reminder_date))
			return this.entry.reminder_text ? `${text} - ${this.entry.reminder_text}` : text
		},
		attachmentCount() {
			return Number(this.entry.attachments_count || 0)
		},
		attachmentTooltip() {
			if (!this.$enableReceipts || this.attachmentCount <= 0) {
				return ''
			}
			return this.$texts.entry.linkedReceipts(this.attachmentCount)
		}
	},
	methods: {
		formatDateTime(timestamp) {
			if (!timestamp) {
				return '-'
			}
			const date = new Date(timestamp * 1000)
			const dateText = date.toLocaleDateString(undefined, {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
			})
			const timeText = date.toLocaleTimeString(undefined, {
				hour: '2-digit',
				minute: '2-digit',
			})

			return `${dateText} um ${timeText} Uhr`
		},
		formatDate(timestamp) {
			if (!timestamp) {
				return '-'
			}
			return new Date(timestamp * 1000).toLocaleDateString()
		},
	}
}
</script>

<style scoped>
.amount-wrapper {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 6px;
}

.shared-icon {
	display: inline-flex;
	align-items: center;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.settled-icon {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.recurrence-history-icon {
	opacity: 0.55;
}

.attachment-icon {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.history-icon {
	appearance: none;
	padding: 2px;
	border: 0;
	border-radius: var(--border-radius-small, 4px);
	background: transparent;
	cursor: pointer;
}

.history-icon:hover,
.history-icon:focus-visible {
	background: var(--color-background-hover, #f5f5f5);
	outline: none;
}

.amount-text {
	display: inline-block;
	padding: 4px 10px;
	border-radius: 6px;
	font-weight: 600;
}

:global(.bg-expense) .amount-text {
	color: var(--cobudget-error);
}

:global(.bg-income) .amount-text {
  color: var(--cobudget-success);
}
</style>
