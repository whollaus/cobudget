<template>
	<div class="project-list-view">
		<AppPageHeader :title="$texts.areas.title()">
			<template #actions>
				<NcButton variant="primary" class="cobudget-primary-icon-button" @click="openCreateModal" :aria-label="$texts.areas.createArea()" :title="$texts.areas.createArea()">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					<span class="btn-text">{{ $texts.areas.newArea() }}</span>
				</NcButton>
			</template>
		</AppPageHeader>

		<div v-if="activeProjects.length > 0" class="projects-grid">
			<div v-for="project in activeProjects" :key="project.id" class="project-card" @click="$emit('open-project', project.id)">
				<div class="project-card-header">
					<span class="project-icon"><FolderIcon :size="20" :fillColor="project.color || 'var(--color-primary, #0082c9)'" /></span>
					<strong class="project-name">{{ project.name }}</strong>
				</div>
				<div class="project-card-meta">
					<span style="display:flex; align-items:center; gap:4px; pointer-events: none;"><AccountMultipleIcon :size="14" /> {{ memberCountLabel(project.member_count) }}</span>
				</div>
				<div class="project-card-balance" style="margin-top: 8px; font-size: var(--cobudget-font-compact); font-weight: 600;">
					<span v-if="project.personal_balance > 0" class="project-balance-positive">
						{{ $texts.areas.getsBack(formatBalance(project.personal_balance)) }}
					</span>
					<span v-else-if="project.personal_balance < 0" class="project-balance-negative">
						{{ $texts.areas.owes(formatBalance(Math.abs(project.personal_balance))) }}
					</span>
					<span v-else class="project-balance-neutral">
						{{ $texts.areas.balanced() }}
					</span>
				</div>
			</div>
		</div>
		<NcEmptyContent v-else
			:name="$texts.areas.emptyTitle()"
			:description="$texts.areas.emptyDescription()">
			<template #icon>
				<FolderIcon :size="64" />
			</template>
			<template #action>
				<NcButton variant="primary" @click="openCreateModal">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ $texts.areas.newArea() }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<details v-if="archivedProjects.length > 0" class="archived-projects-section">
			<summary>{{ $texts.areas.archivedAreas(archivedProjects.length) }}</summary>
			<div class="projects-grid archived-grid">
				<div v-for="project in archivedProjects" :key="project.id" class="project-card archived-card" @click="$emit('open-project', project.id)">
					<div class="project-card-header">
						<span class="project-icon"><FolderIcon :size="20" :fillColor="project.color || 'currentColor'" /></span>
						<strong class="project-name">{{ project.name }}</strong>
					</div>
					<div class="project-card-meta">
						<span style="display:flex; align-items:center; gap:4px;"><AccountMultipleIcon :size="14" /> {{ memberCountLabel(project.member_count) }}</span>
					</div>
				</div>
			</div>
		</details>

		<!-- Create area modal -->
		<Teleport to="body">
			<div
				class="project-create-modal-backdrop"
				v-if="showCreateModal"
				tabindex="-1"
				@click.self="closeCreateModal"
				@keydown.esc.stop.prevent="closeCreateModal">
				<div class="project-create-modal" role="dialog" aria-modal="true" aria-labelledby="project-create-modal-title">
					<form class="project-create-modal-form" @submit.prevent="createProject">
						<div class="project-create-modal-header">
							<h2 id="project-create-modal-title" class="project-create-modal-title">{{ $texts.areas.createTitle() }}</h2>
							<button
								type="button"
								class="project-create-modal-close"
								:aria-label="$texts.common.close()"
								:title="$texts.common.close()"
								@click="closeCreateModal">
								<CloseIcon :size="22" aria-hidden="true" />
							</button>
						</div>

						<div class="project-create-modal-body">
							<div class="form-group">
								<label>{{ $texts.areas.name() }}</label>
								<input type="text" ref="newProjectNameInput" v-model="newProject.name" class="form-control" :placeholder="$texts.areas.namePlaceholder()" required>
							</div>

							<div class="form-group">
								<label>{{ $texts.areas.color() }}</label>
								<div class="color-picker-wrapper">
									<label class="color-preview" :style="{ backgroundColor: newProject.color || 'transparent', backgroundImage: newProject.color ? 'none' : '' }">
										<input type="color" class="sr-only" :value="newProject.color || '#cccccc'" @input="colorChanged" />
									</label>
									<button type="button" @click="newProject.color = ''" :disabled="!newProject.color" class="btn-secondary color-clear-btn">
										<BackspaceIcon :size="18" /> {{ $texts.areas.removeColor() }}
									</button>
								</div>
							</div>

							<div class="form-group" v-if="$enableSharedProjects">
								<label>{{ $texts.areas.addMembers() }}</label>
								<MemberSearch v-model="newProject.members" :placeholder="$texts.areas.searchUsers()" />
							</div>
						</div>

						<div class="project-create-modal-actions">
							<ModalActions
								flush
								inline-mobile
								:show-cancel="false"
								:primary-disabled="!newProject.name.trim() || creatingProject"
								:primary-busy="creatingProject"
								:primary-label="$texts.common.save()"
								:primary-busy-label="$texts.common.saveBusy()"
								@cancel="closeCreateModal" />
						</div>
					</form>
				</div>
			</div>
		</Teleport>
	</div>
</template>

<script>
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
import MemberSearch from '../components/MemberSearch.vue'
import ModalActions from '../components/ModalActions.vue'
import AppPageHeader from '../components/AppPageHeader.vue'
import { showRequestError, showToast } from '../services/notifications'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import BackspaceIcon from 'vue-material-design-icons/Backspace.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

export default {
	name: 'ProjectList',
	components: { MemberSearch, ModalActions, AppPageHeader, NcButton, NcEmptyContent, FolderIcon, AccountMultipleIcon, PlusIcon, BackspaceIcon, CloseIcon },
	data() {
		return {
			projects: [],
			showCreateModal: false,
			creatingProject: false,
			newProject: {
				name: '',
				color: '',
				members: []
			}
		}
	},
	mounted() {
		this.fetchProjects()
	},
	watch: {
		showCreateModal(newVal) {
			if (newVal) {
				this.$nextTick(() => {
					if (this.$refs.newProjectNameInput) {
						this.$refs.newProjectNameInput.focus()
					}
				})
			}
		}
	},
	computed: {
		activeProjects() {
			return this.projects.filter(p => !p.is_archived);
		},
		archivedProjects() {
			return this.projects.filter(p => p.is_archived);
		}
	},
	methods: {
		openCreateModal() {
			this.showCreateModal = true
		},
		closeCreateModal() {
			if (this.creatingProject) {
				return
			}
			this.showCreateModal = false
		},
		async fetchProjects() {
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/projects'))
				this.projects = response.data || []
			} catch (e) {
				showRequestError(e, this.$texts.areas.loadError(), 'Failed to fetch projects')
			}
		},
		colorChanged(event) {
			this.newProject.color = event.target.value;
		},
		async createProject() {
			const projectName = this.newProject.name.trim()
			if (!projectName || this.creatingProject) return

			this.creatingProject = true
			try {
				await axios.post(generateUrl('/apps/cobudget/api/projects'), {
					name: projectName,
					color: this.newProject.color || '',
					members: this.$enableSharedProjects ? this.newProject.members.map(m => m.id) : []
				})
				this.showCreateModal = false
				this.newProject = { name: '', color: '', members: [] }
				this.fetchProjects()
				this.$emit('refresh-projects')
				showToast(this.$texts.areas.created())
			} catch (e) {
				showRequestError(e, this.$texts.areas.createError(), 'Failed to create project')
			} finally {
				this.creatingProject = false
			}
		},
		formatBalance(amount) {
			return this.$formatSignedMoney(parseFloat(amount || 0), this.$currency || 'EUR', {
				signDisplay: amount > 0 ? 'always' : 'auto',
			});
		},
		memberCountLabel(count) {
			const normalizedCount = Number(count || 0)
			return this.$texts.areas.memberCount(normalizedCount)
		}
	}
}
</script>

<style>
.project-list-view {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.projects-grid {
	margin-top: 0px;
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
	gap: 14px;
	margin-top: 20px;
}
.project-card {
	background: var(--cobudget-surface, var(--cobudget-page-background, #fff));
	border: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 8px);
	color: var(--cobudget-text, var(--color-main-text, #222));
	padding: 18px;
	cursor: pointer;
	transition: box-shadow 0.2s, border-color 0.2s;
}
.project-card:hover {
	box-shadow: 0 2px 12px rgba(0,0,0,0.08);
	border-color: var(--color-primary, #0082c9);
}
.project-card-header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 8px;
}
.project-icon {
	font-size: var(--cobudget-font-xl);
}
.project-name {
	flex-grow: 1;
	font-size: var(--cobudget-font-md);
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.project-card * {
	cursor: pointer;
}

.project-card-meta {
	font-size: var(--cobudget-font-compact);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.project-balance-positive {
	color: var(--cobudget-success, #107c41);
}

.project-balance-negative {
	color: var(--cobudget-error);
}

.project-balance-neutral {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

/* Create area modal follows the payment/template modal shell. */
.project-create-modal-backdrop {
	--cobudget-text: #222;
	--cobudget-text-muted: #666;
	--cobudget-surface: #fff;
	--cobudget-surface-muted: #f5f5f5;
	--cobudget-surface-strong: #e5e5e5;
	--cobudget-page-background: #fff;
	--cobudget-border: #ddd;
	--cobudget-border-strong: #ccc;
	--color-main-text: var(--cobudget-text);
	--color-text-maxcontrast: var(--cobudget-text-muted);
	--color-main-background: var(--cobudget-surface);
	--color-background-hover: var(--cobudget-surface-muted);
	--color-background-dark: var(--cobudget-surface-muted);
	--color-background-darker: var(--cobudget-surface-strong);
	--color-border: var(--cobudget-border);
	--color-border-dark: var(--cobudget-border-strong);

	position: fixed;
	top: 0;
	left: 0;
	width: 100vw;
	height: 100vh;
	background: rgba(0,0,0,0.6);
	display: flex;
	justify-content: center;
	align-items: center;
	z-index: 10000;
	backdrop-filter: blur(2px);
}

html.cobudget-theme-dark .project-create-modal-backdrop,
body.cobudget-theme-dark .project-create-modal-backdrop,
html[data-cobudget-theme="dark"] .project-create-modal-backdrop,
body[data-cobudget-theme="dark"] .project-create-modal-backdrop,
html[data-themes*="dark"]:not(.cobudget-theme-light) .project-create-modal-backdrop,
body[data-themes*="dark"]:not(.cobudget-theme-light) .project-create-modal-backdrop,
html[data-theme*="dark"]:not(.cobudget-theme-light) .project-create-modal-backdrop,
body[data-theme*="dark"]:not(.cobudget-theme-light) .project-create-modal-backdrop,
html[data-theme-default*="dark"]:not(.cobudget-theme-light) .project-create-modal-backdrop,
body[data-theme-default*="dark"]:not(.cobudget-theme-light) .project-create-modal-backdrop,
html[data-color-scheme*="dark"]:not(.cobudget-theme-light) .project-create-modal-backdrop,
body[data-color-scheme*="dark"]:not(.cobudget-theme-light) .project-create-modal-backdrop,
html[data-theme-dark]:not(.cobudget-theme-light) .project-create-modal-backdrop,
body[data-theme-dark]:not(.cobudget-theme-light) .project-create-modal-backdrop,
html.dark:not(.cobudget-theme-light) .project-create-modal-backdrop,
body.dark:not(.cobudget-theme-light) .project-create-modal-backdrop,
html.theme-dark:not(.cobudget-theme-light) .project-create-modal-backdrop,
body.theme-dark:not(.cobudget-theme-light) .project-create-modal-backdrop,
html.theme--dark:not(.cobudget-theme-light) .project-create-modal-backdrop,
body.theme--dark:not(.cobudget-theme-light) .project-create-modal-backdrop {
	--cobudget-text: #f5f5f5;
	--cobudget-text-muted: #b3b3b3;
	--cobudget-surface: #181818;
	--cobudget-surface-muted: #242424;
	--cobudget-surface-strong: #303030;
	--cobudget-page-background: #121212;
	--cobudget-border: #3a3a3a;
	--cobudget-border-strong: #555;
	--color-main-text: var(--cobudget-text);
	--color-text-maxcontrast: var(--cobudget-text-muted);
	--color-main-background: var(--cobudget-surface);
	--color-background-hover: var(--cobudget-surface-muted);
	--color-background-dark: var(--cobudget-surface-muted);
	--color-background-darker: var(--cobudget-surface-strong);
	--color-border: var(--cobudget-border);
	--color-border-dark: var(--cobudget-border-strong);
}

@media (prefers-color-scheme: dark) {
	html.cobudget-theme-auto .project-create-modal-backdrop,
	body.cobudget-theme-auto .project-create-modal-backdrop {
		--cobudget-text: #f5f5f5;
		--cobudget-text-muted: #b3b3b3;
		--cobudget-surface: #181818;
		--cobudget-surface-muted: #242424;
		--cobudget-surface-strong: #303030;
		--cobudget-page-background: #121212;
		--cobudget-border: #3a3a3a;
		--cobudget-border-strong: #555;
		--color-main-text: var(--cobudget-text);
		--color-text-maxcontrast: var(--cobudget-text-muted);
		--color-main-background: var(--cobudget-surface);
		--color-background-hover: var(--cobudget-surface-muted);
		--color-background-dark: var(--cobudget-surface-muted);
		--color-background-darker: var(--cobudget-surface-strong);
		--color-border: var(--cobudget-border);
		--color-border-dark: var(--cobudget-border-strong);
	}
}

.project-create-modal {
	position: relative;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border-radius: var(--border-radius-large, 10px);
	width: min(640px, calc(100vw - 48px));
	max-width: 95vw;
	box-shadow: 0 10px 40px rgba(0,0,0,0.25);
	overflow: visible;
}

.project-create-modal-form {
	display: flex;
	flex-direction: column;
	max-height: 90vh;
	margin: 0;
}

.project-create-modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 10px 20px;
	background: var(--cobudget-surface-muted, #f9f9f9);
	border-bottom: 1px solid var(--cobudget-border, #ddd);
	border-radius: var(--border-radius-large, 10px) var(--border-radius-large, 10px) 0 0;
}

.project-create-modal-title {
	margin: 0;
	font-size: var(--cobudget-font-lg);
	font-weight: 700;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.project-create-modal-close {
	appearance: none;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
	width: var(--cobudget-icon-button-size) !important;
	height: var(--cobudget-icon-button-size) !important;
	min-width: var(--cobudget-icon-button-size) !important;
	min-height: var(--cobudget-icon-button-size) !important;
	max-width: var(--cobudget-icon-button-size) !important;
	max-height: var(--cobudget-icon-button-size) !important;
	border: 0 !important;
	border-radius: var(--cobudget-radius-sm);
	background: transparent !important;
	color: var(--cobudget-text) !important;
	cursor: pointer;
	font-size: 0;
	line-height: 0;
	margin: 0 !important;
	padding: 0 !important;
	box-shadow: none !important;
}

.project-create-modal-close:hover,
.project-create-modal-close:focus-visible {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f5f5f5)) !important;
	outline: none;
}

.project-create-modal-close:focus-visible {
	box-shadow: var(--cobudget-focus-ring, 0 0 0 2px var(--color-primary, #0082c9));
}

.project-create-modal-close .material-design-icon,
.project-create-modal-close .material-design-icon__svg {
	display: block;
	flex: 0 0 auto;
	width: 22px;
	height: 22px;
	color: currentColor;
	fill: currentColor !important;
}

.project-create-modal-body {
	flex-grow: 1;
	overflow-y: auto;
	padding: 20px;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.project-create-modal-actions {
	display: flex;
	align-items: center;
	flex-shrink: 0;
	border-top: 1px solid var(--cobudget-border, #ddd);
	padding: 10px 20px;
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border-radius: 0 0 var(--border-radius-large, 10px) var(--border-radius-large, 10px);
}
.form-group {
	margin-bottom: 16px;
}
.form-group label {
	display: block;
	margin-bottom: 6px;
	font-weight: 600;
	font-size: var(--cobudget-font-compact);
	color: var(--cobudget-text, var(--color-main-text, #222));
}
.form-control {
	width: 100%;
	padding: 10px;
	border: 1px solid var(--cobudget-border, #ccc);
	border-radius: var(--border-radius, 4px);
	font-size: var(--cobudget-font-md);
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	box-sizing: border-box;
}

.form-control::placeholder {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	opacity: 1;
}

.project-create-modal input,
.project-create-modal textarea,
.project-create-modal select {
	background-color: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border-color: var(--cobudget-border-strong, var(--color-border-dark, #ccc));
}

.project-create-modal input::placeholder,
.project-create-modal textarea::placeholder {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	opacity: 1;
}

.project-create-modal .mx-input,
.project-create-modal :is(.input-field__input, .input-field__label, .input-field__main-wrapper, .input-field__input-wrapper) {
	background-color: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border-color: var(--cobudget-border-strong, var(--color-border-dark, #ccc));
}

.project-create-modal :is(.input-field__input, .input-field__label)::placeholder {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	opacity: 1;
}
.form-control:focus {
	border-color: var(--color-primary, #0082c9);
	outline: none;
}
.btn-secondary {
	background-color: var(--cobudget-surface-muted, var(--color-background-hover, transparent));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border: 1px solid transparent;
	padding: 10px 20px;
	border-radius: var(--border-radius, 4px);
	font-weight: bold;
	cursor: pointer;
}

.btn-secondary:hover,
.btn-secondary:focus-visible {
	background-color: var(--cobudget-surface-strong, var(--color-background-dark, #eee));
}
.color-picker-wrapper {
	display: flex;
	align-items: center;
	gap: 12px;
}

.color-preview {
	display: inline-block;
	width: 44px;
	height: 44px;
	border: 1px solid var(--cobudget-border, #ccc);
	border-radius: var(--border-radius, 4px);
	cursor: pointer;
	background-color: transparent;
	background-image: linear-gradient(45deg, #e0e0e0 25%, transparent 25%), linear-gradient(-45deg, #e0e0e0 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e0e0e0 75%), linear-gradient(-45deg, transparent 75%, #e0e0e0 75%);
	background-size: 10px 10px;
	background-position: 0 0, 0 5px, 5px -5px, -5px 0px;
	flex-shrink: 0;
}
.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}
.color-clear-btn {
	height: 44px;
	display: flex;
	align-items: center;
	gap: 8px;
}

.project-create-modal .member-search {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.project-create-modal .member-search :is(input, .input-field__input) {
	background-color: var(--cobudget-surface, var(--color-main-background, #fff)) !important;
	color: var(--cobudget-text, var(--color-main-text, #222)) !important;
	border-color: var(--cobudget-border-strong, var(--color-border-dark, #ccc)) !important;
}

.project-create-modal .member-search :is(input, .input-field__input)::placeholder {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888)) !important;
	opacity: 1;
}

.project-create-modal .search-results,
.project-create-modal .no-results {
	background: var(--cobudget-surface, var(--color-main-background, #fff));
	color: var(--cobudget-text, var(--color-main-text, #222));
	border-color: var(--cobudget-border, var(--color-border, #ddd));
}

.project-create-modal .search-result-item:hover {
	background: var(--cobudget-surface-muted, var(--color-background-hover, #f0f0f0));
}

.project-create-modal .user-name {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.project-create-modal .user-id,
.project-create-modal .no-results {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}
.archived-projects-section {
	margin-top: 30px;
	border-top: none;
	padding-top: 0;
}
.archived-projects-section summary {
	cursor: pointer;
	font-weight: 600;
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
	margin-bottom: 16px;
}
.archived-grid {
	opacity: 0.7;
}
.archived-card {
	background: var(--cobudget-surface-muted, #f9f9f9);
}

@media (max-width: 780px) {
	.project-create-modal {
		width: min(560px, calc(100vw - 24px));
	}

	.project-create-modal-body {
		padding: 24px;
	}
}

@media (max-width: 600px) {
	.project-create-modal {
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;
		max-width: 100%;
		border-radius: 0;
	}

	.project-create-modal-form {
		height: 100%;
		max-height: 100%;
		flex-grow: 1;
	}

	.project-create-modal-header,
	.project-create-modal-actions {
		border-radius: 0;
	}

	.project-create-modal-body {
		padding: 20px;
	}
}
</style>
