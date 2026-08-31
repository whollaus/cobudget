<template>
	<header
		class="app-page-header view-header"
		:class="{
			'app-page-header--scrolled': isScrolled,
			'app-page-header--fab-collapsed': isFabCollapsed,
			'app-page-header--pagination-visible': isPaginationVisible,
		}">
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
const MOBILE_FAB_COLLAPSE_SCROLL_TOP = 12
const MOBILE_FAB_EXPAND_SCROLL_TOP = 1

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
	data() {
		return {
			isScrolled: false,
			isFabCollapsed: false,
			isPaginationVisible: false,
			paginationIntersectionObserver: null,
			paginationMutationObserver: null,
			paginationObservedElement: null,
			paginationRefreshFrame: null,
		}
	},
	mounted() {
		if (typeof window === 'undefined') {
			return
		}

		this.getScrollTarget()?.addEventListener('scroll', this.updateScrollState, { passive: true })
		window.addEventListener('resize', this.updateScrollState, { passive: true })
		window.addEventListener('resize', this.schedulePaginationObservationRefresh, { passive: true })
		this.updateScrollState()
		this.$nextTick(this.startPaginationObservation)
	},
	beforeUnmount() {
		if (typeof window === 'undefined') {
			return
		}

		this.getScrollTarget()?.removeEventListener('scroll', this.updateScrollState)
		window.removeEventListener('resize', this.updateScrollState)
		window.removeEventListener('resize', this.schedulePaginationObservationRefresh)
		this.paginationIntersectionObserver?.disconnect()
		this.paginationMutationObserver?.disconnect()
		if (this.paginationRefreshFrame !== null) {
			window.cancelAnimationFrame(this.paginationRefreshFrame)
		}
	},
	methods: {
		getScrollTarget() {
			if (typeof window === 'undefined') {
				return null
			}

			return this.$el?.closest?.('.app-content') || window
		},
		currentScrollTop() {
			const scrollTarget = this.getScrollTarget()
			if (!scrollTarget) {
				return 0
			}

			if (scrollTarget === window) {
				return window.scrollY || document.documentElement.scrollTop || 0
			}

			return scrollTarget.scrollTop || 0
		},
		updateScrollState() {
			const isMobile = typeof window !== 'undefined'
				&& window.matchMedia('(max-width: 768px)').matches
			const scrollTop = this.currentScrollTop()
			const nextState = isMobile
				&& scrollTop > 1
			const nextFabCollapsedState = isMobile
				&& (this.isFabCollapsed
					? scrollTop > MOBILE_FAB_EXPAND_SCROLL_TOP
					: scrollTop >= MOBILE_FAB_COLLAPSE_SCROLL_TOP)

			if (this.isScrolled !== nextState) {
				this.isScrolled = nextState
			}
			if (this.isFabCollapsed !== nextFabCollapsedState) {
				this.isFabCollapsed = nextFabCollapsedState
			}
		},
		startPaginationObservation() {
			if (typeof window === 'undefined') {
				return
			}

			const viewRoot = this.$el?.parentElement
			if (viewRoot && typeof MutationObserver !== 'undefined') {
				this.paginationMutationObserver = new MutationObserver(() => {
					this.schedulePaginationObservationRefresh()
				})
				this.paginationMutationObserver.observe(viewRoot, {
					attributes: true,
					attributeFilter: ['class'],
					childList: true,
					subtree: true,
				})
			}

			this.refreshPaginationObservation()
		},
		schedulePaginationObservationRefresh() {
			if (typeof window === 'undefined' || this.paginationRefreshFrame !== null) {
				return
			}

			this.paginationRefreshFrame = window.requestAnimationFrame(() => {
				this.paginationRefreshFrame = null
				this.refreshPaginationObservation()
			})
		},
		refreshPaginationObservation() {
			const isMobile = typeof window !== 'undefined'
				&& window.matchMedia('(max-width: 768px)').matches
			const paginationElement = isMobile
				? this.$el?.parentElement?.querySelector?.('.pagination-footer:not(.pagination-footer--single):not(.pagination-footer--dashboard)') || null
				: null

			if (paginationElement === this.paginationObservedElement && this.paginationIntersectionObserver) {
				return
			}

			this.paginationIntersectionObserver?.disconnect()
			this.paginationIntersectionObserver = null
			this.paginationObservedElement = paginationElement

			if (!paginationElement || typeof IntersectionObserver === 'undefined') {
				this.isPaginationVisible = false
				return
			}

			const scrollTarget = this.getScrollTarget()
			this.paginationIntersectionObserver = new IntersectionObserver(([entry]) => {
				const nextState = Boolean(entry?.isIntersecting)
				if (this.isPaginationVisible !== nextState) {
					this.isPaginationVisible = nextState
				}
			}, {
				root: scrollTarget === window ? null : scrollTarget,
				threshold: 0.01,
			})
			this.paginationIntersectionObserver.observe(paginationElement)
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
		--app-page-header-mobile-gutter: var(--cobudget-mobile-content-padding, calc(var(--default-grid-baseline, 4px) * 2.5));
		--app-page-header-mobile-overlap: calc(var(--default-grid-baseline, 4px) * 1.5);
		--app-page-header-mobile-sticky-offset: var(--default-grid-baseline, 4px);
		--app-page-header-mobile-fab-lift: 0px;

		position: sticky;
		z-index: 30;
		inset-block-start: var(--app-page-header-mobile-sticky-offset);
		align-items: flex-start;
		flex-direction: row;
		gap: 8px;
		width: calc(100% + var(--app-page-header-mobile-gutter) + var(--app-page-header-mobile-gutter));
		min-height: var(--cobudget-button-height, 44px);
		margin-inline: calc(0px - var(--app-page-header-mobile-gutter));
		margin-bottom: 0;
		padding-inline: var(--app-page-header-mobile-gutter);
		padding-block-end: calc(var(--default-grid-baseline, 4px) * 3);
		border-block-end: 1px solid transparent;
		overflow: visible;
		margin-top: calc(0px - var(--app-page-header-mobile-overlap));
		background-color: var(--cobudget-page-background, var(--color-main-background));
		box-shadow: none;
		transition:
			border-color var(--animation-quick, 100ms) ease,
			box-shadow var(--animation-quick, 100ms) ease;
	}

	.app-page-header::before {
		position: absolute;
		inset-inline: 0;
		inset-block-end: 100%;
		height: var(--app-page-header-mobile-sticky-offset);
		background-color: inherit;
		content: '';
		pointer-events: none;
	}

	.app-page-header--scrolled {
		border-block-end-color: var(--cobudget-border, var(--color-border));
		box-shadow: var(--cobudget-shadow-header, var(--box-shadow-header, var(--cobudget-shadow-sm)));
	}

	.app-page-header--pagination-visible {
		--app-page-header-mobile-fab-lift: var(--cobudget-mobile-fab-pagination-lift, calc(var(--default-grid-baseline, 4px) * 10));
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
	.app-page-header__actions :deep(.budget-new-button .button-vue) {
		min-width: var(--cobudget-icon-button-size, 44px) !important;
		width: var(--cobudget-icon-button-size, 44px) !important;
		min-height: var(--cobudget-icon-button-size, 44px) !important;
		height: var(--cobudget-icon-button-size, 44px) !important;
		padding: 0 !important;
		align-items: center !important;
		justify-content: center !important;
	}

	.app-page-header__actions :deep(.new-payment-main-button.button-vue),
	.app-page-header__actions :deep(.new-payment-main-button .button-vue),
	.app-page-header__actions :deep(.mobile-create-fab.button-vue),
	.app-page-header__actions :deep(.mobile-create-fab .button-vue) {
		--cobudget-mobile-fab-size: calc(var(--default-grid-baseline, 4px) * 16);
		--cobudget-mobile-fab-icon-size: calc(var(--default-grid-baseline, 4px) * 7);
		--app-page-header-mobile-fab-label-gap: calc(var(--default-grid-baseline, 4px) * 2);
		--app-page-header-mobile-fab-label-max-width: calc(var(--default-grid-baseline, 4px) * 45);

		position: fixed !important;
		z-index: 1290 !important;
		inset-inline-end: var(--cobudget-mobile-fab-inline-offset, max(calc(var(--default-grid-baseline, 4px) * 4), env(safe-area-inset-right, 0px))) !important;
		inset-block-end: calc(var(--cobudget-mobile-bottom-navigation-height, 64px) + var(--default-grid-baseline, 4px) * 4 + var(--app-page-header-mobile-fab-lift)) !important;
		min-width: var(--cobudget-mobile-fab-size) !important;
		width: fit-content !important;
		max-width: calc(100vw - var(--cobudget-mobile-fab-inline-offset, calc(var(--default-grid-baseline, 4px) * 4)) - var(--default-grid-baseline, 4px) * 4) !important;
		min-height: var(--cobudget-mobile-fab-size) !important;
		height: var(--cobudget-mobile-fab-size) !important;
		padding: 0 calc(var(--default-grid-baseline, 4px) * 4) !important;
		align-items: center !important;
		justify-content: center !important;
		border-radius: var(--border-radius-pill, 999px) !important;
		box-shadow: var(--cobudget-shadow-md, var(--box-shadow)) !important;
		box-sizing: border-box;
		overflow: hidden;
		transition:
			inset-block-end var(--animation-slow, 200ms) ease,
			max-width var(--animation-slow, 200ms) ease !important;
	}

	.app-page-header__actions :deep(.new-payment-main-button .button-vue__wrapper),
	.app-page-header__actions :deep(.new-payment-main-button.button-vue .button-vue__wrapper),
	.app-page-header__actions :deep(.mobile-create-fab .button-vue__wrapper),
	.app-page-header__actions :deep(.mobile-create-fab.button-vue .button-vue__wrapper) {
		gap: 0;
		min-width: 0;
	}

	.app-page-header__actions :deep(.new-payment-main-button .button-vue__icon),
	.app-page-header__actions :deep(.new-payment-main-button.button-vue .button-vue__icon),
	.app-page-header__actions :deep(.mobile-create-fab .button-vue__icon),
	.app-page-header__actions :deep(.mobile-create-fab.button-vue .button-vue__icon) {
		width: var(--cobudget-mobile-fab-icon-size) !important;
		min-width: var(--cobudget-mobile-fab-icon-size) !important;
		height: var(--cobudget-mobile-fab-icon-size) !important;
		min-height: var(--cobudget-mobile-fab-icon-size) !important;
		margin: 0 !important;
	}

	.app-page-header__actions :deep(.new-payment-main-button .material-design-icon),
	.app-page-header__actions :deep(.new-payment-main-button.button-vue .material-design-icon),
	.app-page-header__actions :deep(.mobile-create-fab .material-design-icon),
	.app-page-header__actions :deep(.mobile-create-fab.button-vue .material-design-icon) {
		font-size: var(--cobudget-mobile-fab-icon-size) !important;
	}

	.app-page-header__actions :deep(.budget-new-button .button-vue__icon),
	.app-page-header__actions :deep(.budget-new-button.button-vue .button-vue__icon) {
		margin: 0 !important;
	}

	.app-page-header__actions :deep(.new-payment-main-button .button-vue__text),
	.app-page-header__actions :deep(.new-payment-main-button.button-vue .button-vue__text),
	.app-page-header__actions :deep(.mobile-create-fab .button-vue__text),
	.app-page-header__actions :deep(.mobile-create-fab.button-vue .button-vue__text) {
		display: inline-flex !important;
		min-width: 0;
		max-width: var(--app-page-header-mobile-fab-label-max-width);
		margin-inline-start: var(--app-page-header-mobile-fab-label-gap);
		overflow: hidden;
		opacity: 1;
		white-space: nowrap;
		transition:
			max-width var(--animation-slow, 200ms) ease,
			margin-inline-start var(--animation-slow, 200ms) ease,
			opacity var(--animation-quick, 100ms) linear;
	}

	.app-page-header__actions :deep(.new-payment-main-button .btn-text),
	.app-page-header__actions :deep(.new-payment-main-button.button-vue .btn-text),
	.app-page-header__actions :deep(.mobile-create-fab .btn-text),
	.app-page-header__actions :deep(.mobile-create-fab.button-vue .btn-text) {
		display: block !important;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.app-page-header--fab-collapsed .app-page-header__actions :deep(.new-payment-main-button .button-vue__text),
	.app-page-header--fab-collapsed .app-page-header__actions :deep(.new-payment-main-button.button-vue .button-vue__text),
	.app-page-header--fab-collapsed .app-page-header__actions :deep(.mobile-create-fab .button-vue__text),
	.app-page-header--fab-collapsed .app-page-header__actions :deep(.mobile-create-fab.button-vue .button-vue__text) {
		max-width: 0;
		margin-inline-start: 0;
		opacity: 0;
	}

}

@media (max-width: 768px) and (prefers-reduced-motion: reduce) {
	.app-page-header__actions :deep(.new-payment-main-button.button-vue),
	.app-page-header__actions :deep(.new-payment-main-button .button-vue),
	.app-page-header__actions :deep(.mobile-create-fab.button-vue),
	.app-page-header__actions :deep(.mobile-create-fab .button-vue) {
		transition: none !important;
	}

	.app-page-header__actions :deep(.new-payment-main-button .button-vue__text),
	.app-page-header__actions :deep(.new-payment-main-button.button-vue .button-vue__text),
	.app-page-header__actions :deep(.mobile-create-fab .button-vue__text),
	.app-page-header__actions :deep(.mobile-create-fab.button-vue .button-vue__text) {
		transition: none;
	}
}

@media print {
	.app-page-header {
		position: static;
		width: 100%;
		margin-inline: 0;
		padding-inline: 0;
		padding-block-end: 0;
		border-block-end: 0;
		box-shadow: none;
	}

	.app-page-header::before {
		content: none;
	}
}

/*
 * NcAppSidebar returns focus to its trigger after closing. On touch devices,
 * that programmatic focus can inherit :focus-visible from a form field and
 * leave NcButton's white keyboard ring behind. Touch users already receive
 * pressed-state feedback; keep the ring for keyboard-capable layouts only.
 */
@media (max-width: 768px) and (pointer: coarse) {
	.app-page-header__actions :deep(.new-payment-main-button.button-vue:focus-visible),
	.app-page-header__actions :deep(.new-payment-main-button .button-vue:focus-visible) {
		outline: none !important;
		box-shadow: none !important;
	}
}
</style>
