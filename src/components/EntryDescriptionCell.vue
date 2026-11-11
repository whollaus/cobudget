<template>
	<div class="entry-description-cell">
		<div class="mobile-only mobile-entry-summary">
			<div class="mobile-entry-icons">
				<span class="mobile-entry-icon">
					<TableTooltip v-if="mobileArea" :text="[mobileArea.name, mobileArea.statusTooltip].filter(Boolean).join('\n')">
						<span class="mobile-area-details" :data-report-export-label="mobileArea.name" aria-hidden="true">
							<component :is="mobileIconComponent" v-bind="mobileIconProps" />
						</span>
					</TableTooltip>
					<span v-else aria-hidden="true">
						<component :is="mobileIconComponent" v-bind="mobileIconProps" />
					</span>
				</span>
			</div>
			<div class="mobile-entry-content" :class="{ 'mobile-entry-content--labels': hasEntryBadges }">
				<div class="mobile-entry-heading">
					<div class="mobile-entry-heading-text">
						<div class="mobile-primary-title" :title="mobilePrimaryTitle">{{ mobilePrimaryTitle }}</div>
						<div v-if="entry.paymentPartner" class="mobile-payment-partner" :title="entry.paymentPartner">{{ entry.paymentPartner }}</div>
						<span v-if="showMobileDate" class="mobile-secondary-item mobile-date">{{ dateText }}</span>
					</div>
					<PaymentLabelList v-if="hasEntryBadges" class="mobile-badges" :labels="allPaymentLabels" summary />
				</div>
				<div v-if="entry.description" class="mobile-description" :class="{ 'mobile-description--beside-label': hasEntryBadges && !showMobileDate && !entry.paymentPartner }" :title="entry.description">
					<template v-for="(part, index) in descriptionParts" :key="`mobile-description-${index}`">
						<span v-if="part.isTag" class="description-hashtag">#{{ part.text }}</span>
						<span v-else>{{ part.text }}</span>
					</template>
				</div>
				<div v-if="paidByName" class="mobile-secondary-item mobile-paid-by" :aria-label="$texts.entry.paidByPerson(paidByName)">
					<NcAvatar :user="entry.user_id" :display-name="paidByName" :size="16" />
					<span class="mobile-secondary-text">{{ paidByName }}</span>
				</div>
			</div>
		</div>
		<div v-if="hasDescriptionContent" class="desc-text">
			<PaymentLabelList v-if="statusLabels.length" class="desktop-status-labels" :labels="statusLabels" />
			<PaymentLabelList v-if="metadataLabels.length" :labels="metadataLabels" collapse :description-present="!!entry.description" :status-count="statusLabels.length" />
			<span v-if="entry.description" class="main-title">
				<template v-for="(part, index) in descriptionParts" :key="`description-${index}`">
					<span v-if="part.isTag" class="description-hashtag">#{{ part.text }}</span>
					<span v-else>{{ part.text }}</span>
				</template>
			</span>
		</div>
	</div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import TrendingUpIcon from 'vue-material-design-icons/TrendingUp.vue'
import TrendingDownIcon from 'vue-material-design-icons/TrendingDown.vue'
import CategoryIcon from './CategoryIcon.vue'
import PaymentLabelList from './PaymentLabelList.vue'
import TableTooltip from './TableTooltip.vue'

export default {
	name: 'EntryDescriptionCell',
	components: {
		TrendingUpIcon,
		TrendingDownIcon,
		CategoryIcon,
		PaymentLabelList,
		TableTooltip,
		NcAvatar
	},
	props: {
		mobileArea: { type: Object, default: null },
		entry: {
			type: Object,
			required: true
		},
		dateText: {
			type: String,
			required: true
		},
		showMobileDate: {
			type: Boolean,
			default: true
		},
		enableFixedCosts: {
			type: Boolean,
			default: true
		},
		enableSubscriptions: {
			type: Boolean,
			default: true
		},
		enableChildRelated: {
			type: Boolean,
			default: true
		},
		enableImportantPayments: {
			type: Boolean,
			default: true
		},
		enableReviewPayments: {
			type: Boolean,
			default: true
		},
		enableTaxRelevant: {
			type: Boolean,
			default: true
		},
		enableInsurance: {
			type: Boolean,
			default: true
		},
		paidByName: {
			type: String,
			default: ''
		}
	},
	computed: {
		mobileIconComponent() {
			return this.entry.category_name ? CategoryIcon : this.entry.type === 'income' ? TrendingUpIcon : TrendingDownIcon
		},
		mobileIconProps() {
			return this.entry.category_name ? { size: 24, icon: this.entry.category_icon || 'Shape' } : { size: 24 }
		},
		statusLabels() {
			const labels = []
			if (this.entry.is_important && this.enableImportantPayments) labels.push({ key: 'important', icon: 'important', tone: 'warning', text: this.$texts.labels.important() })
			if (this.entry.needs_review && this.enableReviewPayments) labels.push({ key: 'review', icon: 'review', tone: 'error', text: this.$texts.labels.review() })
			if (this.entry.is_tax_relevant && this.enableTaxRelevant) labels.push({ key: 'tax', icon: 'taxRelevant', tone: 'tax', text: this.$texts.labels.taxRelevant() })
			return labels
		},
		metadataLabels() {
			const labels = []
			if (this.entry.is_fixed_cost && this.enableFixedCosts) labels.push({ key: 'fixed', icon: 'fixedCost', tone: 'neutral', text: this.$texts.labels.fixedCosts() })
			if (this.entry.is_child_related && this.enableChildRelated) labels.push({ key: 'child', icon: 'childRelated', tone: 'neutral', text: this.$texts.labels.children() })
			if (this.entry.is_subscription && this.enableSubscriptions) labels.push({ key: 'abo', icon: 'subscription', tone: 'neutral', text: this.$texts.labels.subscription() })
			if (this.entry.is_insurance && this.enableInsurance) labels.push({ key: 'insurance', icon: 'insurance', tone: 'neutral', text: this.$texts.labels.insurance() })
			return labels
		},
		allPaymentLabels() { return [...this.statusLabels, ...this.metadataLabels] },
		hasEntryBadges() { return this.allPaymentLabels.length > 0 },
		hasDescriptionContent() {
			return Boolean(this.entry.description) || this.hasEntryBadges
		},
		mobilePrimaryTitle() {
			return this.entry.category_name
				|| (this.entry.type === 'income' ? this.$texts.common.income() : this.$texts.common.expense())
		},
		descriptionParts() {
			const text = String(this.entry.description || '')
			if (!text) {
				return []
			}

			const parts = []
			const regex = /(^|[^\p{L}\p{N}_])#([\p{L}\p{N}_][\p{L}\p{N}_-]{0,63})/gu
			let lastIndex = 0
			let match

			while ((match = regex.exec(text)) !== null) {
				const prefix = match[1] || ''
				const tagStart = match.index + prefix.length
				const tagText = match[2] || ''
				const tagEnd = tagStart + tagText.length + 1

				if (tagStart > lastIndex) {
					parts.push({ text: text.slice(lastIndex, tagStart), isTag: false })
				}

				parts.push({ text: tagText, isTag: true })
				lastIndex = tagEnd
			}

			if (lastIndex < text.length) {
				parts.push({ text: text.slice(lastIndex), isTag: false })
			}

			return parts.length > 0 ? parts : [{ text, isTag: false }]
		}
	}
}
</script>

<style scoped>
.desc-text {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px;
	font-weight: 400;
}

.desktop-status-labels { flex: 0 0 auto; }

.main-title {
	flex: 1 1 auto;
	min-width: 0;
	white-space: normal;
	word-break: break-word;
	font-weight: 400;
}

.description-hashtag {
	color: var(--cobudget-primary, var(--color-primary, #0082c9));
	font-weight: 600;
}

.mobile-only {
	display: none !important;
}

.mobile-entry-summary {
	--payment-mobile-heading-height: calc(var(--cobudget-font-base, 15px) * 1.4 + 3px + var(--cobudget-font-caption, 12px) * 1.4);
	align-items: stretch;
	min-width: 0;
	gap: calc(var(--default-grid-baseline, 4px) * 3);
}
.mobile-entry-icons {
	display: flex;
	flex-direction: column;
	flex: 0 0 24px;
	width: 24px;
	max-height: var(--payment-mobile-heading-height);
}
.mobile-entry-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 1 1 auto;
	width: 24px;
	min-height: 0;
	/* Anchor the icon to the first two text lines, regardless of later details. */
	max-height: var(--payment-mobile-heading-height);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast));
}
.mobile-entry-icon :deep(.category-icon) { margin: 0; }
.mobile-entry-icon :deep(.table-tooltip-trigger) {
	width: 24px;
	height: 24px;
	justify-content: center;
}
.mobile-area-details { display: inline-flex; }
.mobile-entry-content {
	position: relative;
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
	min-width: 0;
	gap: 3px;
}
.mobile-entry-heading {
	display: flex;
	align-items: stretch;
	gap: 8px;
	min-width: 0;
}
.mobile-entry-content--labels .mobile-entry-heading { padding-inline-end: 40px; }
.mobile-entry-heading-text {
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
	gap: 3px;
	min-width: 0;
}
.mobile-primary-title {
	display: -webkit-box;
	min-width: 0;
	overflow: hidden;
	overflow-wrap: anywhere;
	color: var(--cobudget-text, var(--color-main-text));
	font-size: var(--cobudget-font-base, 15px);
	font-weight: var(--font-weight-element, 500);
	line-height: 1.4;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 2;
}
.mobile-payment-partner,
.mobile-description,
.mobile-secondary-item {
	min-width: 0;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast));
	font-size: var(--cobudget-font-caption, 12px);
	font-weight: 400;
	line-height: 1.4;
}
.mobile-payment-partner {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.mobile-description {
	display: -webkit-box;
	overflow: hidden;
	overflow-wrap: anywhere;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 2;
}
.mobile-description--beside-label { padding-inline-end: 40px; }
.mobile-secondary-item {
	display: inline-flex;
	align-items: center;
	gap: var(--default-grid-baseline, 4px);
	min-width: 0;
	max-width: 100%;
}

.mobile-secondary-text {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.mobile-paid-by :deep(.avatardiv) {
	flex: 0 0 auto;
}

.mobile-badges {
	position: absolute;
	inset-block-start: 0;
	inset-inline-end: 0;
	width: 32px;
	height: var(--payment-mobile-heading-height);
	justify-content: flex-end;
	max-height: 100%;
}

@media (max-width: 768px) {
	.entry-description-cell {
		display: block;
		min-width: 0;
	}

	.mobile-only {
		display: flex !important;
	}

	.desktop-only {
		display: none !important;
	}

	.desc-text {
		display: none !important;
	}
}
</style>
