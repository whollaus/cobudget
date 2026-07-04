<template>
	<header class="app-page-header view-header">
		<div class="app-page-header__content view-header-content">
			<h2 class="app-page-header__title view-header-title">
				<slot name="title">{{ title }}</slot>
			</h2>
			<p v-if="subtitle || $slots.subtitle" class="app-page-header__subtitle view-header-subtitle">
				<slot name="subtitle">{{ subtitle }}</slot>
			</p>
		</div>
		<div v-if="$slots.actions" class="app-page-header__actions view-header-actions">
			<slot name="actions" />
		</div>
	</header>
</template>

<script>
export default {
	name: 'AppPageHeader',
	props: {
		title: {
			type: String,
			default: '',
		},
		subtitle: {
			type: String,
			default: '',
		},
	},
}
</script>

<style scoped>
.app-page-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: var(--default-grid-baseline, 4px);
	width: 100%;
	min-height: 44px;
	padding-top: 3px;
	margin-bottom: 15px;
	box-sizing: border-box;
}

.app-page-header__content {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	min-width: 0;
}

.app-page-header__title {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
	padding-left: 28px;
	margin-top: 2px;
	color: var(--cobudget-text, var(--color-main-text, #222));
	font-size: var(--cobudget-font-title);
	font-weight: 700;
	line-height: 1.1;
	letter-spacing: 0;
}

.app-page-header__subtitle {
	margin: 8px 0 0;
	padding-left: 28px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #6b7280));
	font-size: var(--cobudget-font-base);
	line-height: 1.4;
}

.app-page-header__actions {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	min-width: 0;
}

@media (max-width: 768px) {
	.app-page-header {
		align-items: flex-start;
		flex-direction: row;
		gap: 8px;
		min-height: var(--cobudget-button-height, 44px);
		overflow: visible;
    margin-top: -6px;
	}

	.app-page-header__content {
		flex: 1 1 auto;
		min-width: 0;
		overflow: hidden;
	}

	.app-page-header__title,
	.app-page-header__subtitle {
		padding-left: 34px;
	}

	.app-page-header__title {
		align-items: center;
		flex-wrap: nowrap;
		width: 100%;
		max-width: 100%;
		margin-top: 0;
		padding-right: 4px;
		overflow-x: auto;
		overflow-y: hidden;
		overscroll-behavior-x: contain;
		scrollbar-width: none;
		font-size: var(--cobudget-font-lg-plus, 20px);
		min-height: var(--cobudget-button-height, 44px);
		line-height: 1.15;
		white-space: nowrap;
		-webkit-overflow-scrolling: touch;
	}

	.app-page-header__title::-webkit-scrollbar {
		display: none;
	}

	.app-page-header__title > * {
		flex: 0 0 auto;
	}

	.app-page-header__title :deep(.cobudget-page-title-with-back) {
		flex: 0 0 auto;
		max-width: none;
	}

	.app-page-header__title :deep(.cobudget-page-title-with-back > span:last-child) {
		overflow: visible;
		text-overflow: clip;
		white-space: nowrap;
	}

	.app-page-header__subtitle {
		margin-top: 4px;
		font-size: var(--cobudget-font-sm, 14px);
		line-height: 1.3;
	}

	.app-page-header__actions {
		flex: 0 0 auto;
		justify-content: flex-start;
		gap: 4px;
		min-width: max-content;
		padding-left: 0;
		padding-right: 0;
		overflow: visible;
		white-space: nowrap;
	}

	.app-page-header__actions > * {
		flex: 0 0 auto;
	}

	.app-page-header__actions :deep(.btn-text) {
		display: none !important;
	}

	.app-page-header__actions :deep(.button-vue) {
		min-height: var(--cobudget-mobile-touch-size, 44px) !important;
		height: var(--cobudget-mobile-touch-size, 44px) !important;
	}

	.app-page-header__actions :deep(.cobudget-primary-icon-button.button-vue),
	.app-page-header__actions :deep(.cobudget-primary-icon-button .button-vue),
	.app-page-header__actions :deep(.budget-new-button.button-vue),
	.app-page-header__actions :deep(.budget-new-button .button-vue),
	.app-page-header__actions :deep(.new-payment-main-button.button-vue),
	.app-page-header__actions :deep(.new-payment-main-button .button-vue),
	.app-page-header__actions :deep(.new-payment-toggle-button.button-vue),
	.app-page-header__actions :deep(.new-payment-toggle-button .button-vue) {
		min-width: var(--cobudget-icon-button-size, 44px) !important;
		width: var(--cobudget-icon-button-size, 44px) !important;
		min-height: var(--cobudget-icon-button-size, 44px) !important;
		height: var(--cobudget-icon-button-size, 44px) !important;
		padding: 0 !important;
		align-items: center !important;
		justify-content: center !important;
	}

	.app-page-header__actions :deep(.new-payment-main-button .button-vue__icon),
	.app-page-header__actions :deep(.new-payment-main-button.button-vue .button-vue__icon),
	.app-page-header__actions :deep(.budget-new-button .button-vue__icon),
	.app-page-header__actions :deep(.budget-new-button.button-vue .button-vue__icon),
	.app-page-header__actions :deep(.new-payment-toggle-button .button-vue__icon),
	.app-page-header__actions :deep(.new-payment-toggle-button.button-vue .button-vue__icon) {
		margin: 0 !important;
	}

	.app-page-header__actions :deep(.new-payment-main-button .button-vue__text),
	.app-page-header__actions :deep(.new-payment-main-button.button-vue .button-vue__text),
	.app-page-header__actions :deep(.budget-new-button .button-vue__text),
	.app-page-header__actions :deep(.budget-new-button.button-vue .button-vue__text) {
		display: none !important;
	}
}
</style>
