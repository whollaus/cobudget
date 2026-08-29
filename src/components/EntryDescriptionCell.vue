<template>
	<div class="entry-description-cell">
		<div class="mobile-only mobile-entry-summary">
			<div class="mobile-primary-title" :title="mobilePrimaryTitle">
				<template v-if="entry.description">
					<template v-for="(part, index) in descriptionParts" :key="`mobile-description-${index}`">
						<span v-if="part.isTag" class="description-hashtag">#{{ part.text }}</span>
						<span v-else>{{ part.text }}</span>
					</template>
				</template>
				<template v-else>{{ mobilePrimaryTitle }}</template>
			</div>
			<div v-if="hasMobileSecondary" class="mobile-secondary">
				<span v-if="showMobileDate" class="mobile-secondary-item mobile-date">{{ dateText }}</span>
				<span v-if="showMobileCategory" class="mobile-secondary-item mobile-category">
					<CategoryIcon v-if="entry.category_icon" :icon="entry.category_icon" :size="12" />
					<span class="mobile-secondary-text">{{ entry.category_name }}</span>
				</span>
				<span v-if="showMobilePaymentPartner" class="mobile-secondary-item">
					<span class="mobile-secondary-text">{{ entry.paymentPartner }}</span>
				</span>
				<span v-if="paidByName" class="mobile-secondary-item mobile-paid-by">
					<NcAvatar :user="entry.user_id" :display-name="paidByName" :size="16" />
					<span class="mobile-secondary-text">{{ paidByName }}</span>
				</span>
			</div>
			<div v-if="hasMobileBadges" class="mobile-badges">
				<span v-if="showProjectChip" class="entry-badge mobile-project-badge" :style="projectStyle">{{ projectName }}</span>
				<span v-if="entry.is_important && enableImportantPayments" class="entry-badge badge-important">{{ $texts.labels.important() }}</span>
				<span v-if="entry.needs_review && enableReviewPayments" class="entry-badge badge-review">{{ $texts.labels.review() }}</span>
				<span v-if="entry.is_fixed_cost && enableFixedCosts" class="entry-badge badge-fixed">{{ $texts.labels.fixedCosts() }}</span>
				<span v-if="entry.is_child_related && enableChildRelated" class="entry-badge badge-child">{{ $texts.labels.children() }}</span>
				<span v-if="entry.is_subscription && enableSubscriptions" class="entry-badge badge-abo">{{ $texts.labels.subscription() }}</span>
				<span v-if="entry.is_tax_relevant && enableTaxRelevant" class="entry-badge badge-tax">{{ $texts.labels.taxRelevant() }}</span>
			</div>
		</div>
		<div
			v-if="hasDesktopContent"
			class="desc-text"
			:class="{ 'mobile-hidden-content': !hasDescriptionContent }">
			<span v-if="entry.is_important && enableImportantPayments" class="entry-badge badge-important">{{ $texts.labels.important() }}</span>
			<span v-if="entry.needs_review && enableReviewPayments" class="entry-badge badge-review">{{ $texts.labels.review() }}</span>
			<span v-if="entry.is_fixed_cost && enableFixedCosts" class="entry-badge badge-fixed">{{ $texts.labels.fixedCosts() }}</span>
			<span v-if="entry.is_child_related && enableChildRelated" class="entry-badge badge-child">{{ $texts.labels.children() }}</span>
			<span v-if="entry.is_subscription && enableSubscriptions" class="entry-badge badge-abo">{{ $texts.labels.subscription() }}</span>
			<span v-if="entry.is_tax_relevant && enableTaxRelevant" class="entry-badge badge-tax">{{ $texts.labels.taxRelevant() }}</span>
			<span
				v-if="showProjectChip"
				class="project-chip desktop-only entry-badge"
				:style="projectStyle">
				{{ projectName }}
			</span>
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
import CategoryIcon from './CategoryIcon.vue'

export default {
	name: 'EntryDescriptionCell',
	components: {
		CategoryIcon,
		NcAvatar
	},
	props: {
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
		projectName: {
			type: String,
			default: ''
		},
		projectStyle: {
			type: Object,
			default: () => ({})
		},
		showProjectChip: {
			type: Boolean,
			default: false
		},
		paidByName: {
			type: String,
			default: ''
		}
	},
	computed: {
		hasEntryBadges() {
			return Boolean(
				(this.entry.is_important && this.enableImportantPayments)
				|| (this.entry.needs_review && this.enableReviewPayments)
				|| (this.entry.is_fixed_cost && this.enableFixedCosts)
				|| (this.entry.is_child_related && this.enableChildRelated)
				|| (this.entry.is_subscription && this.enableSubscriptions)
				|| (this.entry.is_tax_relevant && this.enableTaxRelevant)
			)
		},
		hasDescriptionContent() {
			return Boolean(this.entry.description) || this.hasEntryBadges
		},
		hasDesktopContent() {
			return this.hasDescriptionContent || this.showProjectChip
		},
		mobileTitleKind() {
			if (this.entry.description) {
				return 'description'
			}
			if (this.entry.paymentPartner) {
				return 'paymentPartner'
			}
			if (this.entry.category_name) {
				return 'category'
			}
			return 'fallback'
		},
		mobilePrimaryTitle() {
			return this.entry.description
				|| this.entry.paymentPartner
				|| this.entry.category_name
				|| this.$texts.common.payment()
		},
		showMobileCategory() {
			return Boolean(this.entry.category_name) && this.mobileTitleKind !== 'category'
		},
		showMobilePaymentPartner() {
			return Boolean(this.entry.paymentPartner) && this.mobileTitleKind !== 'paymentPartner'
		},
		hasMobileSecondary() {
			return Boolean(
				this.showMobileDate
					|| this.showMobileCategory
					|| this.showMobilePaymentPartner
					|| this.paidByName
			)
		},
		hasMobileBadges() {
			return this.hasEntryBadges || this.showProjectChip
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

.main-title {
	white-space: normal;
	word-break: break-word;
	font-weight: 400;
}

.description-hashtag {
	color: var(--cobudget-primary, var(--color-primary, #0082c9));
	font-weight: 600;
}

.project-chip {
	display: inline-flex;
	align-items: center;
	margin-left: 0;
	padding: 2px 6px;
	border-radius: 4px;
	font-size: var(--cobudget-font-xs);
	font-weight: 600;
	vertical-align: middle;
	white-space: nowrap;
}

.entry-badge {
	display: inline-flex;
	align-items: center;
	padding: 2px 6px;
	border-radius: 4px;
	font-size: var(--cobudget-font-xs);
	font-weight: 600;
	line-height: 1.3;
	vertical-align: middle;
	white-space: nowrap;
}

.badge-abo,
.badge-fixed,
.badge-child {
	background: var(--cobudget-primary-light, var(--color-primary-light, #e0f2fe));
	color: var(--cobudget-primary, var(--color-primary, #0082c9));
	border: 1px solid var(--cobudget-primary, var(--color-primary, #0082c9));
}

.badge-tax {
	background: var(--cobudget-tax-light);
	color: var(--cobudget-tax-dark);
	border: 1px solid var(--cobudget-tax);
}

.badge-important {
	background: var(--cobudget-warning-light);
	color: var(--cobudget-warning-dark);
	border: 1px solid var(--cobudget-warning);
}

.badge-review {
	background: var(--cobudget-error-light);
	color: var(--cobudget-error-dark);
	border: 1px solid var(--cobudget-error);
}

.mobile-only {
	display: none !important;
}

.mobile-entry-summary {
	flex-direction: column;
	min-width: 0;
	gap: var(--default-grid-baseline, 4px);
}

.mobile-primary-title {
	display: -webkit-box;
	min-width: 0;
	overflow: hidden;
	color: var(--cobudget-text, var(--color-main-text));
	font-size: var(--cobudget-font-base, 14px);
	font-weight: 600;
	line-height: 1.3;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 2;
}

.mobile-secondary {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline, 4px) / 2) calc(var(--default-grid-baseline, 4px) * 2);
	min-width: 0;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast));
	font-size: var(--cobudget-font-compact, 12px);
	line-height: 1.35;
}

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

.mobile-category :deep(.material-design-icon),
.mobile-paid-by :deep(.avatardiv) {
	flex: 0 0 auto;
}

.mobile-badges {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline, 4px);
	min-width: 0;
}

.mobile-project-badge {
	max-width: 100%;
	overflow: hidden;
	text-overflow: ellipsis;
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
