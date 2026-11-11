<template>
	<div class="project-list-view">
		<AppPageHeader :title="$texts.areas.title()">
			<template #title>
				<ReportPeriodPicker navigation-only force-navigation area-navigation :title="$texts.areas.title()" :period-enabled="false" label="" />
			</template>
			<template #creation>
				<NcButton variant="primary" class="cobudget-native-button mobile-create-fab" @click="$router.push({ name: 'project-new' })" :aria-label="$texts.areas.createArea()" :title="$texts.areas.createArea()">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					<span class="creation-label-desktop">{{ $texts.common.newItem() }}</span>
					<span class="btn-text">{{ $texts.areas.newArea() }}</span>
				</NcButton>
			</template>
		</AppPageHeader>

		<div class="cobudget-overview-section">
		<p v-if="loadingProjects" class="projects-loading" role="status"><NcLoadingIcon :size="20" /> {{ $texts.common.loading() }}</p>
		<NcNoteCard v-else-if="projectsError" type="error">
			<p>{{ projectsError }}</p>
			<NcButton class="cobudget-native-button" variant="secondary" @click="fetchProjects">{{ $texts.common.retry() }}</NcButton>
		</NcNoteCard>
		<template v-else>
		<div v-if="activeProjects.length > 0" class="projects-grid cobudget-overview-list">
			<div v-for="project in activeProjects" :key="project.id" class="project-card cobudget-overview-entry cobudget-focusable" role="link" tabindex="0" @click="$emit('open-project', project.id)" @keydown.enter.prevent="$emit('open-project', project.id)" @keydown.space.prevent="$emit('open-project', project.id)">
				<div class="project-card-header">
					<span class="project-icon"><FolderIcon :size="20" :fillColor="project.color || 'var(--color-primary, #0082c9)'" /></span>
					<h3 class="cobudget-subheading project-name">{{ project.name }}</h3>
					<NcChip class="project-admin-desktop" v-if="isSharedProject(project) && isAreaAdmin(project)" :text="$texts.areaSettings.areaAdmin()" no-close variant="secondary" />
				</div>

				<div v-if="isSharedProject(project)" class="project-card-balance">
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
				<div class="project-card-meta">
					<span v-if="isSharedProject(project)" class="project-member-label"><AccountMultipleIcon :size="16" aria-hidden="true" /> {{ memberCountLabel(project.member_count) }}</span>
					<span v-else class="project-member-label"><AccountIcon :size="16" aria-hidden="true" /> {{ $texts.areas.personalArea() }}</span>
					<NcChip class="project-admin-mobile" v-if="isSharedProject(project) && isAreaAdmin(project)" :text="$texts.areaSettings.areaAdmin()" no-close variant="secondary" />
					<span v-if="isSharedProject(project) && defaultShareLabel(project) !== null" class="project-share-label">{{ $texts.areas.defaultShareForNewPayments(defaultShareLabel(project)) }}</span>
				</div>
			</div>
		</div>
		<NcEmptyContent v-else
			:name="archivedProjects.length ? $texts.areas.noActiveTitle() : $texts.areas.emptyTitle()"
			:description="archivedProjects.length ? $texts.areas.noActiveDescription() : $texts.areas.emptyDescription()">
			<template #icon>
				<FolderIcon :size="64" />
			</template>
			<template #action>
				<NcButton class="cobudget-native-button" variant="primary" @click="$router.push({ name: 'project-new' })">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ $texts.areas.newArea() }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<section v-if="archivedProjects.length > 0" class="archived-projects-section">
			<NcButton class="archive-toggle cobudget-native-button" variant="tertiary" :aria-expanded="archivesOpen" aria-controls="archived-area-list" @click="archivesOpen = !archivesOpen">
				<template #icon><ChevronDownIcon v-if="archivesOpen" :size="20" /><ChevronRightIcon v-else :size="20" /></template>
				{{ $texts.areas.archivedAreas(archivedProjects.length) }}
			</NcButton>
			<div v-show="archivesOpen" id="archived-area-list" class="projects-grid archived-grid cobudget-overview-list">
				<div v-for="project in archivedProjects" :key="project.id" class="project-card archived-card cobudget-overview-entry cobudget-focusable" role="link" tabindex="0" @click="$emit('open-project', project.id)" @keydown.enter.prevent="$emit('open-project', project.id)" @keydown.space.prevent="$emit('open-project', project.id)">
					<div class="project-card-header">
						<span class="project-icon"><FolderIcon :size="20" :fillColor="project.color || 'currentColor'" /></span>
						<h3 class="cobudget-subheading project-name">{{ project.name }}</h3>
					<NcChip class="project-admin-desktop" v-if="isSharedProject(project) && isAreaAdmin(project)" :text="$texts.areaSettings.areaAdmin()" no-close variant="secondary" />
					</div>
					<div class="project-card-meta">
						<span v-if="isSharedProject(project)" class="project-member-label"><AccountMultipleIcon :size="16" aria-hidden="true" /> {{ memberCountLabel(project.member_count) }}</span>
						<span v-else class="project-member-label"><AccountIcon :size="16" aria-hidden="true" /> {{ $texts.areas.personalArea() }}</span>
						<NcChip class="project-admin-mobile" v-if="isSharedProject(project) && isAreaAdmin(project)" :text="$texts.areaSettings.areaAdmin()" no-close variant="secondary" />
					</div>
				</div>
			</div>
		</section>
		</template>

		</div>


	</div>
</template>

<script>
import axios from '../services/http'
import { REMOTE_DATA_CHANGED_EVENT, changeTouches } from '../services/dataSync'
import { generateUrl } from '@nextcloud/router'
import AppPageHeader from '../components/AppPageHeader.vue'
import ReportPeriodPicker from '../components/ReportPeriodPicker.vue'
import { extractError } from '../services/notifications'
import { sortAreasByName } from '../utils/areaSorting'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import { getAppLocale } from '../utils/formatMoney'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'

export default {
	name: 'ProjectList',
	components: { ReportPeriodPicker, NcChip, ChevronDownIcon, ChevronRightIcon, AppPageHeader, NcButton, NcEmptyContent, FolderIcon, AccountIcon, AccountMultipleIcon, PlusIcon, NcNoteCard, NcLoadingIcon },
	data() {
		return {
			projects: [],
			loadingProjects: true, projectsError: '', projectsRequest: 0,
			archivesOpen: false,
		}
	},
	mounted() {
		this.fetchProjects()
		window.addEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
	},
	beforeUnmount() {
		this.projectsRequest++
		window.removeEventListener(REMOTE_DATA_CHANGED_EVENT, this.onRemoteDataChanged)
	},
	computed: {
		activeProjects() {
			return sortAreasByName(this.projects.filter(p => !p.is_archived));
		},
		archivedProjects() {
			return sortAreasByName(this.projects.filter(p => p.is_archived));
		}
	},
	methods: {
		async fetchProjects() {
			const request = ++this.projectsRequest
			this.loadingProjects = true
			this.projectsError = ''
			try {
				const response = await axios.get(generateUrl('/apps/cobudget/api/projects'))
				if (request !== this.projectsRequest) return
				this.projects = Array.isArray(response.data) ? response.data : []
			} catch (e) {
				if (request === this.projectsRequest) this.projectsError = extractError(e, this.$texts.areas.loadError())
			} finally {
				if (request === this.projectsRequest) this.loadingProjects = false
			}
		},
		onRemoteDataChanged(event) {
			if (changeTouches(event?.detail, ['entries', 'projects'])) {
				this.fetchProjects()
			}
		},
		formatBalance(amount) {
			return this.$formatSignedMoney(parseFloat(amount || 0), this.$currency || 'EUR', {
				signDisplay: amount > 0 ? 'always' : 'auto',
			});
		},
		isAreaAdmin(project) {
			return [true, 1, '1', 'true'].includes(project?.is_owner)
		},
		defaultShareLabel(project) {
			const raw = project?.my_share_basis_points
			if (raw === null || raw === undefined || raw === '') return null
			const basisPoints = Number(raw)
			if (!Number.isInteger(basisPoints) || basisPoints < 0 || basisPoints > 10000) return null
			return (basisPoints / 10000).toLocaleString(getAppLocale(), { style: 'percent', maximumFractionDigits: 2 })
		},
		memberCountLabel(count) {
			const normalizedCount = Number(count || 0)
			return this.$texts.areas.memberCount(normalizedCount)
		},
		isSharedProject(project) {
			if (project?.is_shared !== undefined) {
				return project.is_shared === true || project.is_shared === 1 || project.is_shared === '1' || project.is_shared === 'true'
			}
			return Number(project?.member_count || 0) > 1
		}
	}
}
</script>

<style>
.project-list-view {
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.project-card {
	display: grid;
	grid-template-columns: minmax(0, 1fr) fit-content(45%);
	gap: 8px var(--cobudget-section-gap);
	color: var(--color-main-text);
}
.project-card-header {
	grid-column: 1;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--cobudget-content-gap);
}
.project-icon {
	flex-shrink: 0;
	display: inline-flex;
	align-items: center;
	font-size: var(--cobudget-font-xl);
}
.project-name {
	flex: 0 1 auto;
	min-width: 0;
	max-width: 100%;
	margin: 0;
	font-size: var(--cobudget-font-subheading);
	font-weight: var(--cobudget-font-weight-heading);
	line-height: 1.25;
	overflow-wrap: anywhere;
	color: var(--cobudget-text, var(--color-main-text, #222));
}

.project-card-meta {
	grid-column: 1 / -1;
	display: flex;
	flex-wrap: wrap;
	gap: 8px var(--cobudget-card-gap);
	font-size: var(--font-size, 15px);
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

.project-list-view .project-card-meta .project-admin-mobile { display: none; }

.project-member-label { display: flex; align-items: center; gap: var(--default-grid-baseline, 4px); }
.project-card-balance {
	grid-column: 2;
	grid-row: 1;
	align-self: start;
	text-align: end;
	font-size: var(--font-size, 15px);
	font-weight: var(--font-weight-default, normal);
	overflow-wrap: anywhere;
}
.projects-loading { display: flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 2); color: var(--color-text-maxcontrast); }

.project-balance-positive {
	color: var(--cobudget-success, #107c41);
}

.project-balance-negative {
	color: var(--cobudget-error);
}

.project-balance-neutral {
	color: var(--cobudget-text-muted, var(--color-text-maxcontrast, #888));
}

@media (max-width: 768px) {
	.project-list-view { --default-clickable-area: var(--cobudget-mobile-touch-size); }
}

.archived-projects-section {
	margin-top: var(--cobudget-settings-section-gap);
}
.archive-toggle.button-vue { font-size: var(--font-size, 15px); font-weight: var(--font-weight-element, 500); }
.archived-grid { margin-block-start: var(--cobudget-card-gap); }
@media (max-width: 768px) {
	.project-card {
		column-gap: var(--cobudget-content-gap);
	}
	.project-card-header { flex-wrap: nowrap; align-items: flex-start; gap: calc(var(--default-grid-baseline, 4px) * 2); }
	.project-icon { min-height: calc(var(--cobudget-font-subheading) * 1.25); }
	.project-card-meta { align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 2) var(--cobudget-content-gap); }
	.project-list-view .project-card-header .project-admin-desktop { display: none; }
	.project-list-view .project-card-meta .project-admin-mobile { display: inline-flex; }
	.project-share-label { flex-basis: 100%; }

}

</style>
