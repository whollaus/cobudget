<template>
	<div class="table-filters">
		<div class="filter-column">
			<!-- 1. Suche -->
			<div class="filter-group search-group">
				<div class="search-input-wrapper">
					<span class="icon-search"></span>
					<input 
						type="search" 
						v-model="localFilters.search" 
						@input="debouncedEmit"
						:placeholder="$texts.filters.search()" 
						class="form-control search-input"
					>
				</div>
			</div>

			<!-- 2. Typ Dropdown -->
			<div class="filter-group" v-if="$enableIncomes">
				<select v-model="localFilters.type" @change="emitFilters" class="form-control">
					<option value="all">{{ $texts.filters.incomeAndExpense() }}</option>
					<option value="income">{{ $texts.filters.onlyIncome() }}</option>
					<option value="expense">{{ $texts.filters.onlyExpense() }}</option>
				</select>
			</div>

			<!-- 3. Zeitraum Dropdown -->
			<div class="filter-group" v-if="localFilters.tags !== 'future'">
				<select v-model="localFilters.timeRange" @change="emitFilters" class="form-control">
					<option value="all">{{ $texts.filters.allTime() }}</option>
					<option value="currentMonth">{{ $texts.filters.currentMonth() }}</option>
					<option value="lastMonth">{{ $texts.filters.lastMonth() }}</option>
					<option value="last30Days">{{ $texts.filters.last30Days() }}</option>
					<option value="currentYear">{{ $texts.filters.currentYear() }}</option>
					<option value="lastYear">{{ $texts.filters.lastYear() }}</option>
				</select>
			</div>

			<!-- 3. Kategorie Dropdown -->
			<div class="filter-group">
				<select v-model="localFilters.categoryId" @change="emitFilters" class="form-control">
					<option :value="null">{{ $texts.filters.allCategories() }}</option>
					<option v-for="cat in filteredCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
				</select>
			</div>

			<!-- 3. Von / An Dropdown -->
			<div class="filter-group" v-if="paymentPartners && paymentPartners.length > 0">
				<select v-model="localFilters.paymentPartnerId" @change="emitFilters" class="form-control">
					<option :value="null">{{ $texts.filters.allPaymentPartners() }}</option>
					<option v-for="p in filteredPaymentPartners" :key="p.id" :value="p.id">{{ p.name }}</option>
				</select>
			</div>
            
			<!-- 4. Bereiche Dropdown -->
			<div class="filter-group" v-if="projects && projects.length > 0">
				<select v-model="localFilters.projectId" @change="emitFilters" class="form-control">
					<option :value="null">{{ $texts.filters.allAreas() }}</option>
					<option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
				</select>
			</div>
			<!-- 4. Status Dropdown -->
			<div class="filter-group" v-if="showStatusFilter">
				<select v-model="localFilters.status" @change="emitFilters" class="form-control">
					<option value="active">{{ $texts.filters.openEntries() }}</option>
					<option value="settled">{{ $texts.filters.settledEntries() }}</option>
					<option value="all">{{ $texts.filters.allEntries() }}</option>
				</select>
			</div>

			<!-- 5. Tags Dropdown -->
			<div class="filter-group">
				<select v-model="localFilters.tags" @change="emitFilters" class="form-control">
					<option value="all">{{ $texts.filters.allLabels() }}</option>
					<option v-if="$enableImportantPayments" value="important">{{ $texts.filters.onlyImportant() }}</option>
					<option v-if="$enableReviewPayments" value="review">{{ $texts.filters.onlyReview() }}</option>
					<option v-if="$enableFixedCosts" value="fixedCost">{{ $texts.filters.onlyFixedCosts() }}</option>
					<option v-if="$enableChildRelated" value="childRelated">{{ $texts.filters.onlyChildren() }}</option>
					<option v-if="$enableSubscriptions" value="subscription">{{ $texts.filters.onlySubscriptions() }}</option>
					<option v-if="$enableTaxRelevant" value="taxRelevant">{{ $texts.filters.onlyTaxRelevant() }}</option>
					<option value="future">{{ $texts.filters.futurePayments() }}</option>
				</select>
			</div>

			<!-- 6. Erinnerung Dropdown -->
			<div class="filter-group">
				<select v-model="localFilters.hasReminder" @change="emitFilters" class="form-control">
					<option value="all">{{ $texts.filters.allReminders() }}</option>
					<option value="true">{{ $texts.filters.onlyWithReminders() }}</option>
				</select>
			</div>

			<!-- 7. Belege Dropdown -->
			<div class="filter-group" v-if="$enableReceipts">
				<select v-model="localFilters.hasAttachment" @change="emitFilters" class="form-control">
					<option value="all">{{ $texts.filters.allReceipts() }}</option>
					<option value="true">{{ $texts.filters.onlyWithReceipt() }}</option>
					<option value="false">{{ $texts.filters.onlyWithoutReceipt() }}</option>
				</select>
			</div>

			<div class="filter-actions" v-if="hasActiveFilters">
				<button class="btn-clear-text" @click="clearFilters">
					{{ $texts.filters.clearFilters() }}
				</button>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'TableFilters',
	props: {
		categories: {
			type: Array,
			default: () => []
		},
		projects: {
			type: Array,
			default: () => []
		},
		paymentPartners: {
			type: Array,
			default: () => []
		},
		initialFilters: {
			type: Object,
			default: () => ({
				search: '',
				type: 'all',
				status: 'active',
				categoryId: null,
				projectId: null,
				paymentPartnerId: null,
				dateFrom: null,
				dateTo: null,
				timeRange: 'all',
				tags: 'all',
				recurring: 'all',
				hasReminder: 'all',
				hasAttachment: 'all'
			})
		},
		showStatusFilter: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			localFilters: { ...this.initialFilters },
			debounceTimer: null
		}
	},
	computed: {
		hasActiveFilters() {
			return this.localFilters.search !== '' ||
				   this.localFilters.type !== 'all' ||
				   this.localFilters.status !== 'active' && this.localFilters.status !== 'all' ||
				   this.localFilters.categoryId !== null ||
				   this.localFilters.projectId !== null ||
				   this.localFilters.paymentPartnerId !== null ||
				   this.localFilters.timeRange !== 'all' ||
				   this.localFilters.tags !== 'all' ||
				   this.localFilters.hasReminder !== 'all' ||
				   this.localFilters.hasAttachment !== 'all';
		},
		filteredCategories() {
			if (this.localFilters.type === 'all' || !this.localFilters.type) return this.categories;
			return this.categories.filter(c => c.type === this.localFilters.type || !c.type);
		},
		filteredPaymentPartners() {
			if (this.localFilters.type === 'all' || !this.localFilters.type) return this.paymentPartners;
			return this.paymentPartners.filter(p => p.type === this.localFilters.type || !p.type);
		}
	},
	watch: {
		initialFilters: {
			handler(newVal) {
				this.localFilters = { ...newVal };
			},
			deep: true
		}
	},
	methods: {
		debouncedEmit() {
			clearTimeout(this.debounceTimer);
			this.debounceTimer = setTimeout(() => {
				this.emitFilters();
			}, 300);
		},
		emitFilters() {
			this.$emit('update:filters', { ...this.localFilters });
		},
		clearFilters() {
			this.localFilters = {
				search: '',
				type: 'all',
				status: this.initialFilters.status !== undefined ? this.initialFilters.status : 'active',
				categoryId: null,
				projectId: null,
				paymentPartnerId: null,
				dateFrom: null,
				dateTo: null,
				timeRange: 'all',
				tags: 'all',
				recurring: this.initialFilters.recurring !== undefined ? this.initialFilters.recurring : 'all',
				hasReminder: 'all',
				hasAttachment: 'all'
			};
			this.emitFilters();
		}
	}
}
</script>

<style scoped>
.table-filters {
	background: var(--cobudget-surface-muted, #f9f9f9);
	border: none;
	border-radius: var(--border-radius-large, 8px);
	padding: 16px;
	width: 320px;
}

.filter-column {
	display: flex;
	flex-direction: column;
	gap: 12px;
	width: 100%;
}

.filter-group {
	width: 100%;
}

.search-group {
	width: 100%;
}

.search-input-wrapper {
	position: relative;
}

.icon-search {
	position: absolute;
	left: 10px;
	top: 50%;
	transform: translateY(-50%);
	opacity: 0.5;
}

.search-input {
	padding-left: 35px !important;
}

.form-control {
	width: 100%;
	height: 38px;
	padding: 8px 12px;
	border: 1px solid var(--cobudget-border-strong, #ccc);
	border-radius: var(--border-radius, 6px);
	font-size: var(--cobudget-font-base);
	background: var(--cobudget-page-background, #fff);
	color: var(--color-main-text, #222);
}

.form-control:focus {
	border-color: var(--color-primary, #0082c9);
	outline: none;
}

.filter-actions {
	display: flex;
	justify-content: flex-end;
}

.btn-clear-text {
	background: transparent;
	border: none;
	color: var(--color-text-maxcontrast, #888);
	cursor: pointer;
	font-size: var(--cobudget-font-compact);
	padding: 4px 8px;
	border-radius: var(--border-radius, 4px);
}

.btn-clear-text:hover {
	background: var(--color-background-dark, #eee);
	color: var(--color-main-text, #222);
}
</style>
