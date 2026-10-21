<template>
	<nav class="mobile-report-switch no-print" :aria-label="$texts.mobileNavigation.analytics()">
		<router-link :to="destination('annual-overview')" :class="{ active: $route.name === 'annual-overview' }"
			:aria-current="$route.name === 'annual-overview' ? 'page' : undefined">
			{{ $texts.annual.title() }}
		</router-link>
		<router-link :to="destination('analytics')" :class="{ active: $route.name === 'analytics' }"
			:aria-current="$route.name === 'analytics' ? 'page' : undefined">
			{{ $texts.mobileNavigation.statistics() }}
		</router-link>
	</nav>
</template>

<script>
export default {
	name: 'MobileReportSwitch',
	methods: {
		destination(name) {
			return { name, ...(this.$route.name === name ? { query: this.$route.query } : {}) }
		},
	},
}
</script>

<style scoped>
.mobile-report-switch { display: none; }
@media screen and (max-width: 768px) {
	.mobile-report-switch {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 4px;
		margin-block: 12px 16px;
		padding: 4px;
		border-radius: var(--border-radius-large, 8px);
		background: var(--cobudget-surface-muted, #f5f5f5);
	}
	.mobile-report-switch a {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 44px;
		padding: 6px 8px;
		border-radius: var(--border-radius, 6px);
		color: var(--cobudget-text, var(--color-main-text));
		font-weight: 600;
		text-align: center;
		text-decoration: none;
		overflow-wrap: anywhere;
	}
	.mobile-report-switch a.active {
		background: var(--cobudget-surface, #fff);
		color: var(--cobudget-primary, var(--color-primary-element));
		box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
	}
	.mobile-report-switch a:hover { background: var(--cobudget-surface, #fff); }
	.mobile-report-switch a:focus-visible { outline: 2px solid var(--color-primary-element); outline-offset: -2px; }
}
</style>
