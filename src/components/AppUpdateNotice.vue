<template>
	<Teleport to="body">
		<div class="app-update-notice" @keydown.esc="closeOnEscape">
			<div ref="card" class="app-update-notice__card">
				<NcButton
					ref="trigger"
					class="app-update-notice__trigger"
					type="button"
					:variant="expanded ? 'tertiary-no-background' : 'tertiary'"
					alignment="start"
					:aria-expanded="expanded"
					aria-controls="cobudget-update-details"
					@click="expanded = !expanded">
					<template #icon>
						<UpdateIcon :size="20" aria-hidden="true" />
					</template>
					<span>{{ $texts.appUpdate.available() }}</span>
				</NcButton>
				<div v-show="expanded" id="cobudget-update-details" class="app-update-notice__content">
					<p>{{ $texts.appUpdate.version(version) }}</p>
					<p>{{ $texts.appUpdate.saveChanges() }}</p>
					<div class="app-update-notice__actions">
						<NcButton type="button" variant="primary" @click="reloadApp">
							{{ $texts.appUpdate.reloadNow() }}
						</NcButton>
					</div>
				</div>
			</div>
		</div>
	</Teleport>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import UpdateIcon from 'vue-material-design-icons/Update.vue'

defineProps({
	version: { type: String, required: true },
})

const card = ref(null)
const trigger = ref(null)
const expanded = ref(false)

onMounted(() => document.addEventListener('pointerdown', closeOnOutsideClick))
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOnOutsideClick))

function closeOnOutsideClick(event) {
	if (expanded.value && !card.value?.contains(event.target)) {
		expanded.value = false
	}
}

function closeOnEscape(event) {
	if (!expanded.value) return
	event.stopPropagation()
	expanded.value = false
	trigger.value?.$el?.focus({ preventScroll: true })
}

function reloadApp() {
	window.location.reload()
}
</script>

<style scoped>
.app-update-notice {
	--update-notice-header-offset: var(--header-height, 44px);
	position: fixed;
	z-index: 1500;
	inset-block-start: calc(var(--update-notice-header-offset) + 2 * var(--default-grid-baseline, 4px));
	inset-inline-end: calc(2 * var(--default-grid-baseline, 4px));
}

@media (min-width: 769px) {
	:global(html.cobudget-nextcloud-header-desktop-hidden .app-update-notice) {
		--update-notice-header-offset: 0px;
	}
}

@media (max-width: 768px) {
	:global(html.cobudget-nextcloud-header-mobile-hidden .app-update-notice) {
		--update-notice-header-offset: 0px;
	}
}

.app-update-notice__card {
	inline-size: max-content;
	max-inline-size: calc(100vw - 4 * var(--default-grid-baseline, 4px));
	border-radius: var(--border-radius-element);
	background: var(--cobudget-surface);
	color: var(--cobudget-text);
	box-shadow: 0 1px 5px var(--color-box-shadow);
}

.app-update-notice__trigger.button-vue {
	inline-size: 100%;
	padding-inline: calc(2 * var(--default-grid-baseline, 4px));
	border-radius: inherit;
}

.app-update-notice__trigger :deep(.button-vue__text) {
	white-space: normal;
	line-height: 1.2;
}

.app-update-notice__trigger.button-vue:focus-visible {
	outline: none !important;
	box-shadow: none !important;
}

.app-update-notice__card:has(> .app-update-notice__trigger:focus-visible) {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.app-update-notice__content {
	contain: inline-size;
	padding: 0 calc(3 * var(--default-grid-baseline, 4px)) calc(3 * var(--default-grid-baseline, 4px));
	overflow-wrap: anywhere;
}

.app-update-notice__content p {
	white-space: normal;
	margin: 0 0 calc(2 * var(--default-grid-baseline, 4px));
}

.app-update-notice__actions {
	display: flex;
	justify-content: flex-end;
}

@media (max-width: 360px) {
	.app-update-notice__trigger.button-vue {
		font-size: var(--cobudget-font-compact);
	}
}

@media print {
	.app-update-notice {
		display: none;
	}
}
</style>
