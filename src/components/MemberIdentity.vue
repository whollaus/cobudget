<template>
	<span class="member-identity">
		<NcAvatar class="member-identity-avatar" :user="placeholder ? undefined : userId" :display-name="displayName" :is-no-user="placeholder" :size="size" hide-status disable-menu />
		<span v-if="!avatarOnly" class="member-identity-name"><slot>{{ displayName }}</slot></span>
	</span>
</template>

<script>
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

export default {
	name: 'MemberIdentity',
	components: { NcAvatar },
	props: {
		userId: { type: String, default: '' },
		displayName: { type: String, default: '' },
		isFormer: { type: [Boolean, Number, String], default: false },
		size: { type: Number, default: 24 },
		avatarOnly: { type: Boolean, default: false },
	},
	computed: {
		placeholder() {
			return !this.userId || this.userId.startsWith('former:')
				|| [true, 1, '1', 'true'].includes(this.isFormer)
		},
	},
}
</script>

<style scoped>
.member-identity { display: inline-flex; align-items: center; gap: calc(var(--default-grid-baseline, 4px) * 2); min-width: 0; max-width: 100%; vertical-align: middle; }
.member-identity-avatar { flex-shrink: 0; }
.member-identity-name { min-width: 0; white-space: normal; overflow-wrap: anywhere; }
</style>
