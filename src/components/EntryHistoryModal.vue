<template>
	<Teleport to="body">
		<div class="entry-history-backdrop" @click.self="$emit('close')">
			<section
				ref="modal"
				class="entry-history-modal cobudget-dialog"
				role="dialog"
				aria-modal="true"
				:aria-label="$texts.entry.historyTitle()"
				tabindex="-1"
				@keydown.esc.stop.prevent="$emit('close')">
				<header class="entry-history-header cobudget-dialog-header cobudget-dialog-header--padded">
					<h2>{{ $texts.entry.historyTitle() }}</h2>
					<button
						type="button"
						class="entry-history-close cobudget-dialog-close cobudget-focusable"
						:aria-label="$texts.common.close()"
						@click="$emit('close')">
						<CloseIcon :size="24" aria-hidden="true" />
					</button>
				</header>

				<div class="entry-history-body">
					<p v-if="loading" class="history-muted">
						{{ $texts.entry.historyLoading() }}
					</p>
					<p v-else-if="history.length === 0" class="history-muted">
						{{ $texts.entry.historyEmpty() }}
					</p>
					<div v-else class="history-table-wrapper">
						<table class="history-table">
							<thead>
								<tr>
									<th>{{ $texts.entry.historyChangedAt() }}</th>
									<th>{{ $texts.entry.historyChangedBy() }}</th>
									<th>{{ $texts.entry.historyField() }}</th>
									<th>{{ $texts.entry.historyPrevious() }}</th>
									<th>{{ $texts.entry.historyCurrent() }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="row in history" :key="row.id">
									<td>{{ row.changed_at_display }}</td>
									<td>{{ row.changed_by_display_name }}</td>
									<td>{{ row.field_label }}</td>
									<td>{{ displayValue(row.old_display) }}</td>
									<td>{{ displayValue(row.new_display) }}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</section>
		</div>
	</Teleport>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'

export default {
	name: 'EntryHistoryModal',
	components: {
		CloseIcon,
	},
	props: {
		history: {
			type: Array,
			default: () => [],
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['close'],
	mounted() {
		this.$nextTick(() => {
			this.$refs.modal?.focus({ preventScroll: true })
		})
	},
	methods: {
		displayValue(value) {
			return value === '' || value === null || typeof value === 'undefined' ? '-' : value
		},
	},
}
</script>

<style scoped>
.entry-history-backdrop {
	--cobudget-history-text: var(--cobudget-text, var(--color-main-text, #222));
	--cobudget-history-muted: var(--cobudget-text-muted, var(--color-text-maxcontrast, #666));
	--cobudget-history-surface: var(--cobudget-surface, var(--color-main-background, #fff));
	--cobudget-history-surface-muted: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5));
	--cobudget-history-border: var(--cobudget-border, var(--color-border, #ddd));
	position: fixed;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 24px;
	background: var(--cobudget-dialog-backdrop);
	backdrop-filter: blur(2px);
	z-index: 10000;
}

.entry-history-modal {
	width: min(900px, 100%);
	max-height: min(780px, calc(100vh - 48px));
	display: flex;
	flex-direction: column;
	overflow: hidden;
	border: 1px solid var(--cobudget-history-border);
	border-radius: var(--cobudget-dialog-radius);
	background: var(--cobudget-history-surface);
	color: var(--cobudget-history-text);
	box-shadow: var(--cobudget-dialog-shadow);
}

.entry-history-body {
	padding: 16px var(--cobudget-dialog-padding) var(--cobudget-dialog-padding);
	overflow: auto;
}

.history-muted {
	margin: 0;
	color: var(--cobudget-history-muted);
	font-size: var(--cobudget-font-base, 14px);
}

.history-table-wrapper {
	overflow-x: auto;
	border: 1px solid var(--cobudget-history-border);
	border-radius: var(--cobudget-table-radius);
}

.history-table {
	width: 100%;
	border-collapse: collapse;
	min-width: 680px;
}

.history-table th,
.history-table td {
  padding: var(--cobudget-table-cell-padding);
  border-bottom: 1px solid var(--cobudget-border, #ddd);
  letter-spacing: 0.5px;
	text-align: left;
	vertical-align: top;
	font-size: var(--cobudget-font-sm, 12px);
	line-height: 1.35;
}

.history-table th {
	background: var(--cobudget-table-header-background);
  color: var(--cobudget-text-muted, #888);
	letter-spacing: 0;
}

.history-table td {
	color: var(--cobudget-history-text);
}

.history-table tr:last-child td {
	border-bottom: 0;
}

@media (max-width: 768px) {
	.entry-history-backdrop {
		align-items: stretch;
		padding: 0;
	}

	.entry-history-modal {
		width: 100%;
		max-height: 100vh;
		border-radius: 0;
		border-left: 0;
		border-right: 0;
	}

	.entry-history-header,
	.entry-history-body {
		padding-left: 20px;
		padding-right: 20px;
	}
}
</style>
