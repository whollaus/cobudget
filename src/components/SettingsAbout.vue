<template>
	<div class="settings-about">
		<section class="settings-about__section" aria-labelledby="settings-about-resources">
			<h4 id="settings-about-resources" class="cobudget-subheading">{{ $texts.about.resources() }}</h4>
			<NcFormBox>
				<NcFormBoxButton :label="$texts.about.changes()" :href="changesUrl" target="_blank" :aria-describedby="loadedAppVersion ? 'settings-about-current-version' : undefined">
					<template v-if="loadedAppVersion" #description><span id="settings-about-current-version">{{ $texts.about.currentVersion(loadedAppVersion) }}</span></template>
				</NcFormBoxButton>
				<NcFormBoxButton :label="$texts.about.documentation()" :href="documentationUrl" target="_blank" aria-describedby="settings-about-documentation-hint">
					<template #description><span id="settings-about-documentation-hint">{{ $texts.about.documentationHint() }}</span></template>
				</NcFormBoxButton>
				<NcFormBoxButton :label="$texts.about.reportBug()" :href="issuesUrl" target="_blank" aria-describedby="settings-about-report-hint">
					<template #description><span id="settings-about-report-hint">{{ $texts.about.reportBugHint() }}</span></template>
				</NcFormBoxButton>
				<NcFormBoxButton :label="$texts.about.sourceCode()" :href="repositoryUrl" target="_blank" aria-describedby="settings-about-license">
					<template #description><span id="settings-about-license">{{ $texts.about.license() }}</span></template>
				</NcFormBoxButton>
			</NcFormBox>
		</section>

		<section class="settings-about__section" aria-labelledby="settings-about-support">
			<h4 id="settings-about-support" class="cobudget-subheading">{{ $texts.about.support() }}</h4>
			<p class="settings-about__support-text">{{ $texts.about.supportText() }}</p>
			<div class="settings-about__support-actions">
				<NcButton :href="githubSupportUrl" target="_blank" rel="noopener noreferrer" variant="tertiary">
					<template #icon><GithubIcon :size="20" /></template>
					{{ $texts.about.supportGitHub() }}
				</NcButton>
				<NcButton href="https://donate.stripe.com/aFaeVdfARbPNe897bu5J600" target="_blank" rel="noopener noreferrer" variant="tertiary">
					<template #icon><HeartOutlineIcon :size="20" /></template>
					{{ $texts.about.supportStripe() }}
				</NcButton>
			</div>
		</section>
	</div>
</template>

<script setup>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import GithubIcon from 'vue-material-design-icons/Github.vue'
import HeartOutlineIcon from 'vue-material-design-icons/HeartOutline.vue'
import { loadedAppVersion } from '../services/dataSync'
import { getAppLanguage } from '../utils/formatDate'

const repositoryUrl = 'https://github.com/whollaus/cobudget'
const issuesUrl = `${repositoryUrl}/issues`
const changesUrl = loadedAppVersion
	? `${repositoryUrl}/releases/tag/v${encodeURIComponent(loadedAppVersion)}`
	: `${repositoryUrl}/blob/main/CHANGELOG.md`
const documentationPaths = new Map([
	['de', 'docs/readme.de.md'],
	['es', 'docs/readme.es.md'],
	['fr', 'docs/readme.fr.md'],
])
const language = getAppLanguage().toLowerCase().split('-')[0]
const documentationUrl = `${repositoryUrl}/blob/main/${documentationPaths.get(language) ?? 'README.md'}`
const githubSupportUrl = 'https://github.com/sponsors/whollaus/sponsorships?frequency=one-time&amount=20'
</script>

<style scoped>
.settings-about__section + .settings-about__section {
	margin-block-start: var(--cobudget-settings-section-gap);
}

.settings-about__support-text {
	margin-block: 0 var(--cobudget-section-gap);
}

.settings-about__support-actions {
	display: flex;
	flex-wrap: wrap;
	gap: var(--cobudget-content-gap);
}

@media (max-width: 480px) {
	.settings-about__support-actions {
		flex-direction: column;
		align-items: flex-start;
	}
}
</style>
