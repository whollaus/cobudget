<template>
	<div class="member-search">
		<div class="search-input-wrapper">
			<NcTextField
				ref="searchInput"
				v-model="searchTerm"
				@update:modelValue="onSearch"
				:placeholder="placeholder"
				@focus="showDropdown = true"
			/>
			<div v-if="loading" class="search-spinner">⏳</div>
		</div>

		<!-- Search results dropdown -->
		<ul v-if="showDropdown && results.length > 0" class="search-results">
			<li v-for="user in results" :key="user.id" @click="selectUser(user)" class="search-result-item">
				<span class="user-avatar">👤</span>
				<span class="user-name">{{ user.displayName }}</span>
				<span class="user-id">({{ user.id }})</span>
			</li>
		</ul>
		<div v-if="showDropdown && searchTerm.length >= 1 && !loading && results.length === 0" class="no-results">
			{{ $texts.memberSearch.noResults() }}
		</div>

		<!-- Selected members list -->
		<div v-if="selectedMembers.length > 0" class="selected-members">
			<span v-for="member in selectedMembers" :key="member.id" class="member-chip">
				{{ member.displayName }}
				<button
					type="button"
					:aria-label="$texts.memberSearch.removeMember(member.displayName)"
					:title="$texts.memberSearch.removeMember(member.displayName)"
					@click="removeMember(member)"
					class="remove-btn">✕</button>
			</span>
		</div>
	</div>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { texts } from '../l10n/texts'
import { showRequestError } from '../services/notifications'

export default {
	name: 'MemberSearch',
	components: { NcTextField },
	props: {
		modelValue: {
			type: Array,
			default: () => []
		},
		placeholder: {
			type: String,
			default: () => texts.memberSearch.placeholder()
		},
		excludeIds: {
			type: Array,
			default: () => []
		},
		autofocus: {
			type: Boolean,
			default: false
		}
	},
	emits: ['update:modelValue'],
	data() {
		return {
			searchTerm: '',
			results: [],
			loading: false,
			showDropdown: false,
			searchTimeout: null,
		}
	},
	computed: {
		selectedMembers() {
			return this.modelValue
		}
	},
	mounted() {
		document.addEventListener('click', this.handleClickOutside)
		this.$nextTick(() => {
			if (this.autofocus) {
				const input = this.$el.querySelector('input')
				if (input) {
					input.focus()
				}
			}
		})
	},
	beforeUnmount() {
		document.removeEventListener('click', this.handleClickOutside)
	},
	methods: {
		handleClickOutside(e) {
			if (!this.$el.contains(e.target)) {
				this.showDropdown = false
			}
		},
		onSearch() {
			if (this.searchTimeout) clearTimeout(this.searchTimeout)
			if (this.searchTerm.length < 1) {
				this.results = []
				return
			}
			this.searchTimeout = setTimeout(() => this.fetchUsers(), 300)
		},
		async fetchUsers() {
			this.loading = true
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/users/search'), {
					params: { term: this.searchTerm }
				})
				// Filter out already selected members and excluded members
				const selectedIds = this.selectedMembers.map(m => m.id)
				const toExclude = [...selectedIds, ...this.excludeIds]
				this.results = (response.data || []).filter(u => !toExclude.includes(u.id))
				this.showDropdown = true
			} catch (e) {
				showRequestError(e, this.$texts.memberSearch.searchError(), 'Failed to search users')
				this.results = []
			} finally {
				this.loading = false
			}
		},
		selectUser(user) {
			const updated = [...this.selectedMembers, user]
			this.$emit('update:modelValue', updated)
			this.searchTerm = ''
			this.results = []
			this.showDropdown = false
		},
		removeMember(member) {
			const updated = this.selectedMembers.filter(m => m.id !== member.id)
			this.$emit('update:modelValue', updated)
		}
	}
}
</script>

<style scoped>
.member-search {
	position: relative;
}
.search-input-wrapper {
	position: relative;
}
.search-spinner {
	position: absolute;
	right: 10px;
	top: 50%;
	transform: translateY(-50%);
}
.search-results {
	position: absolute;
	z-index: 1000;
	width: 100%;
	max-height: 200px;
	overflow-y: auto;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: 0 0 var(--border-radius, 4px) var(--border-radius, 4px);
	list-style: none;
	padding: 0;
	margin: 0;
	box-shadow: 0 4px 12px rgba(0,0,0,0.15);
	color: var(--cobudget-text, var(--color-main-text, #222));
}
.search-result-item {
	padding: 10px 12px;
	cursor: pointer;
	display: flex;
	align-items: center;
	gap: 8px;
	transition: background 0.15s;
}
.search-result-item:hover {
	background: var(--cobudget-surface-muted, #f0f0f0);
}
.user-avatar {
	font-size: var(--cobudget-font-md);
}
.user-name {
	font-weight: 500;
	color: var(--cobudget-text, var(--color-main-text, #222));
}
.user-id {
	font-size: var(--cobudget-font-sm);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}
.no-results {
	padding: 10px 12px;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	font-size: var(--cobudget-font-compact);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	border: 1px solid var(--cobudget-border, #ddd);
	border-top: none;
	border-radius: 0 0 var(--border-radius, 4px) var(--border-radius, 4px);
}
.selected-members {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin-top: 8px;
}
.member-chip {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 10px;
	border-radius: 16px;
	background: var(--color-primary-element-light, #e0f0ff);
	color: var(--color-primary-element-light-text, #004a7c);
	font-size: var(--cobudget-font-compact);
	font-weight: 500;
}
.remove-btn {
	background: none;
	border: none;
	cursor: pointer;
	font-size: var(--cobudget-font-sm);
	padding: 0 2px;
	color: inherit;
	opacity: 0.7;
	line-height: 1;
}
.remove-btn:hover {
	opacity: 1;
}
</style>
