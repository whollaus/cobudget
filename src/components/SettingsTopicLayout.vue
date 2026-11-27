<template>
	<div class="settings-layout" :class="{ 'settings-layout--topic': requestedTopic }">
		<nav ref="topicNavigation" class="settings-topics" :aria-label="label">
			<NcAppNavigationList>
				<RouterLink v-for="topic in topics"
					:key="topic.id"
					:to="{ ...to, query: { section: topic.id } }"
					custom
					v-slot="{ href, navigate }">
					<NcAppNavigationItem
						:name="topic.label"
						:href="href"
						:data-settings-topic="topic.id"
						:active="currentTopicId === topic.id"
						@click="navigate">
						<template #icon>
							<component :is="topic.icon" :size="20" aria-hidden="true" />
						</template>
					</NcAppNavigationItem>
				</RouterLink>
			</NcAppNavigationList>
		</nav>

		<div class="settings-topic-content">
			<RouterLink class="settings-topic-back" :to="to">
				<ArrowLeftIcon :size="20" aria-hidden="true" />
				{{ $texts.settings.backToSettings() }}
			</RouterLink>
			<h3 ref="topicHeading" class="cobudget-section-heading settings-topic-title" tabindex="-1">{{ activeTopic.label }}</h3>
			<slot />
		</div>
	</div>
</template>

<script>
import NcAppNavigationList from '@nextcloud/vue/components/NcAppNavigationList'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'

export default {
	name: 'SettingsTopicLayout',
	components: { NcAppNavigationList, NcAppNavigationItem, ArrowLeftIcon },
	props: {
		label: { type: String, required: true },
		to: { type: Object, required: true },
		topics: { type: Array, required: true },
		requestedTopic: { type: Object, default: undefined },
		preventFocusScroll: Boolean,
	},
	data() {
		const stackedLayoutQuery = window.matchMedia('(max-width: 768px)')
		return { stackedLayoutQuery, stackedLayout: stackedLayoutQuery.matches }
	},
	computed: {
		activeTopic() { return this.requestedTopic ?? this.topics[0] },
		requestedTopicId() { return this.requestedTopic?.id },
		currentTopicId() {
			return this.stackedLayout && !this.requestedTopic ? undefined : this.activeTopic.id
		},
	},
	watch: {
		async requestedTopicId(id, previousId) {
			await this.$nextTick()
			const previousLink = this.$refs.topicNavigation?.querySelector(`[data-settings-topic="${previousId}"] a`)
			const focus = !id && previousLink?.getClientRects().length ? previousLink : this.$refs.topicHeading
			focus?.focus({ preventScroll: this.preventFocusScroll })
		},
	},
	mounted() {
		this.stackedLayoutQuery.addEventListener('change', this.updateStackedLayout)
	},
	beforeUnmount() {
		this.stackedLayoutQuery.removeEventListener('change', this.updateStackedLayout)
	},
	methods: {
		updateStackedLayout(event) {
			this.stackedLayout = event.matches
		},
	},
}
</script>

<style scoped>
.settings-layout {
	margin: var(--cobudget-form-inset);
	margin-top: 0;
	box-sizing: border-box;
	display: grid;
	grid-template-columns: minmax(10rem, 15rem) minmax(0, var(--cobudget-form-max-width));
	gap: var(--cobudget-settings-section-gap);
	align-items: start;
}
.settings-topics {
	--app-navigation-padding: 0;
	--cobudget-mobile-navigation-background: transparent;
	min-width: 0;
}
.settings-topics :deep(.app-navigation-entry .app-navigation-entry-link:focus-visible) {
	outline-offset: -2px;
}
.settings-topics :deep(.app-navigation-entry-link) {
	align-items: center;
	height: auto;
	min-height: var(--cobudget-mobile-touch-size, 44px);
}
@media (min-width: 769px) {
	.settings-topics {
		position: sticky;
		inset-block-start: var(--cobudget-content-gap);
		max-block-size: calc(100dvh - var(--header-height, 50px) - 2 * var(--cobudget-content-gap));
		overflow: auto;
	}
}
.settings-topics :deep(.app-navigation-entry .app-navigation-entry-link .app-navigation-entry__name) {
	white-space: normal;
	overflow-wrap: anywhere;
	line-height: 1.35;
}
.settings-topic-content { min-width: 0; }
.settings-topic-back {
	display: none;
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	min-height: var(--cobudget-mobile-touch-size, 44px);
	color: var(--cobudget-text);
}
.settings-topic-title {
	margin: 0 0 var(--cobudget-section-gap);
	font-size: var(--cobudget-font-section-title);
	scroll-margin-block-start: calc(var(--cobudget-mobile-touch-size, 44px) + var(--cobudget-content-gap));
}

@media (max-width: 768px) {
	.settings-layout {
		margin: 16px;
		width: calc(100% - 32px);
		display: block;
	}
	.settings-layout--topic .settings-topics,
	.settings-layout:not(.settings-layout--topic) .settings-topic-content { display: none; }
	.settings-topic-back { display: inline-flex; margin-bottom: var(--cobudget-content-gap); }
}
</style>
