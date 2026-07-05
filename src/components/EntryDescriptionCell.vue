<template>
	<div>
		<div class="desc-text">
			<span v-if="entry.description" class="main-title desktop-only">
				<template v-for="(part, index) in descriptionParts" :key="`desktop-desc-${index}`">
					<span v-if="part.isTag" class="description-hashtag">#{{ part.text }}</span>
					<span v-else>{{ part.text }}</span>
				</template>
			</span>
			<span v-if="entry.description" class="main-title mobile-only">
				<template v-for="(part, index) in descriptionParts" :key="`mobile-desc-${index}`">
					<span v-if="part.isTag" class="description-hashtag">#{{ part.text }}</span>
					<span v-else>{{ part.text }}</span>
				</template>
			</span>
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
		</div>
		<div class="mobile-only mobile-meta">
			<div class="mobile-date">{{ dateText }}</div>
			<div class="mobile-tags">
				<span v-if="paidByName" class="mobile-tag user-tag">
					<NcAvatar :user="entry.user_id" :display-name="paidByName" :size="16" />
					{{ paidByName }}
				</span>
				<span v-if="entry.category_name" class="mobile-tag icon-tag">
					<CategoryIcon v-if="entry.category_icon" :icon="entry.category_icon" :size="12" />
					{{ entry.category_name }}
				</span>
				<span v-if="entry.paymentPartner" class="mobile-tag">{{ entry.paymentPartner }}</span>
				<span v-if="showProjectChip" class="mobile-tag entry-badge" :style="projectStyle">{{ projectName }}</span>
			</div>
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

.mobile-meta {
	flex-direction: column;
	gap: 6px;
}

.mobile-date {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	font-size: var(--cobudget-font-compact);
}

.mobile-tags {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 6px;
}

.mobile-tag {
	display: inline-flex;
	align-items: center;
	min-height: 28px;
	padding: 2px 8px;
	border-radius: 12px;
	background: var(--cobudget-surface-muted, var(--color-background-dark, #eee));
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-xs);
	font-weight: 600;
	line-height: 1.2;
}

.icon-tag,
.user-tag {
	align-items: center;
	gap: 4px;
}

@media (max-width: 768px) {
	.mobile-only {
		display: flex !important;
	}

	.desktop-only {
		display: none !important;
	}
}
</style>
