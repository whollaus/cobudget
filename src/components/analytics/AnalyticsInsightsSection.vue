<template>
	<section class="analytics-card insights-card">
		<div class="card-header">
			<div>
				<h3>{{ $texts.analytics.insights() }}</h3>
				<p>{{ $texts.analytics.insightsHint() }}</p>
			</div>
		</div>
		<div class="insight-grid">
			<article
				v-for="item in items"
				:key="item.key"
				class="insight-card"
				:class="[`insight-${item.tone || 'neutral'}`, { 'is-clickable': item.action }]"
				:role="item.action ? 'button' : null"
				:tabindex="item.action ? 0 : null"
				@click="open(item)"
				@keydown.enter.prevent="open(item)"
				@keydown.space.prevent="open(item)">
				<span class="insight-kicker">{{ item.kicker }}</span>
				<strong>{{ item.title }}</strong>
				<span>{{ item.description }}</span>
				<small v-if="item.meta">{{ item.meta }}</small>
			</article>
		</div>
	</section>
</template>

<script>
export default {
	name: 'AnalyticsInsightsSection',
	props: {
		items: {
			type: Array,
			required: true
		}
	},
	emits: ['open'],
	methods: {
		open(item) {
			if (item?.action) {
				this.$emit('open', item)
			}
		}
	}
}
</script>

<style scoped>
.analytics-card {
	padding: 18px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.insights-card {
	background-color: var(--cobudget-surface-muted, #f5f9fb);
}

.card-header {
	display: flex;
	justify-content: space-between;
	gap: 16px;
	align-items: flex-start;
	margin-bottom: 14px;
}

.card-header h3 {
	margin: 0;
	font-size: var(--cobudget-font-xl);
	line-height: 1.25;
}

.card-header p {
	margin: 4px 0 0;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.insight-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 12px;
}

.insight-card {
	display: flex;
	min-height: 132px;
	flex-direction: column;
	gap: 8px;
	padding: 14px;
	border: 1px solid var(--cobudget-border, #e5e5e5);
	border-radius: 8px;
	background: var(--cobudget-surface, #fff);
	color: var(--cobudget-text, var(--color-main-text, #222));
	transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}

.insight-card.is-clickable {
	cursor: pointer;
}

.insight-card.is-clickable:hover,
.insight-card.is-clickable:focus-visible {
	border-color: var(--color-primary-element, #0082c9);
	background: var(--cobudget-surface-muted, #f5f5f5);
	box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
	outline: none;
}

.insight-kicker {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	font-size: var(--cobudget-font-sm);
	font-weight: 800;
	letter-spacing: 0;
	text-transform: uppercase;
}

.insight-card strong {
	font-size: var(--cobudget-font-lg);
	line-height: 1.25;
}

.insight-card > span:not(.insight-kicker),
.insight-card small {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
}

.insight-card small {
	margin-top: auto;
	font-weight: 700;
}

.insight-danger {
	border-left: 4px solid var(--cobudget-error);
}

.insight-warning {
	border-left: 4px solid var(--cobudget-warning, #ffc92b);
}

.insight-neutral {
	border-left: 4px solid var(--color-primary-element, #0082c9);
}
</style>
