<template>
	<div>
		<NcActionButton v-if="isCollectiveAdmin(collective)"
			:close-after-click="true"
			@click="openCollectiveMembers()">
			<template #icon>
				<AccountMultipleIcon :size="20" />
			</template>
			{{ t('collectives', 'Manage members') }}
		</NcActionButton>
		<NcActionSeparator v-if="isCollectiveAdmin(collective)" />
		<NcActionButton v-if="collectiveCanShare(collective)"
			:close-after-click="true"
			@click="openShareTab(collective)">
			{{ t('collectives', 'Share link') }}
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
		</NcActionButton>
		<NcActionLink :close-after-click="true"
			:href="printLink"
			target="_blank">
			{{ t('collectives', 'Export or print') }}
			<template #icon>
				<DownloadIcon :size="20" />
			</template>
		</NcActionLink>
		<NcActionButton v-if="isCollectiveAdmin(collective)"
			:close-after-click="true"
			@click="openCollectiveSettings()">
			<template #icon>
				<CogIcon :size="20" />
			</template>
			{{ t('collectives', 'Settings') }}
		</NcActionButton>
		<NcActionButton v-if="!isPublic && collective.canLeave !== false"
			:close-after-click="true"
			@click="leaveCollectiveWithUndo(collective)">
			{{ t('collectives', 'Leave collective') }}
			<template #icon>
				<LogoutIcon :size="20" />
			</template>
		</NcActionButton>
	</div>
</template>

<script>
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { NcActionButton, NcActionLink, NcActionSeparator } from '@nextcloud/vue'
import { showError, showUndo } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import LogoutIcon from 'vue-material-design-icons/Logout.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import {
	LEAVE_CIRCLE,
	MARK_COLLECTIVE_DELETED,
	UNMARK_COLLECTIVE_DELETED,
} from '../../store/actions.js'

export default {
	name: 'CollectiveActions',

	components: {
		AccountMultipleIcon,
		CogIcon,
		DownloadIcon,
		LogoutIcon,
		NcActionButton,
		NcActionLink,
		NcActionSeparator,
		ShareVariantIcon,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			leaveTimeout: null,
		}
	},

	computed: {
		...mapGetters([
			'collectiveCanShare',
			'isCollectiveAdmin',
			'isPublic',
			'shareTokenParam',
		]),

		circleLink() {
			return generateUrl('/apps/contacts/direct/circle/' + this.collective.circleId)
		},

		printLink() {
			return this.isPublic
				? generateUrl(`/apps/collectives/p/${this.shareTokenParam}/print/${this.collective.name}`)
				: generateUrl(`/apps/collectives/_/print/${this.collective.name}`)
		},
	},

	methods: {
		...mapActions({
			dispatchLeaveCircle: LEAVE_CIRCLE,
			dispatchMarkCollectiveDeleted: MARK_COLLECTIVE_DELETED,
			dispatchUnmarkCollectiveDeleted: UNMARK_COLLECTIVE_DELETED,
		}),

		...mapMutations([
			'setActiveSidebarTab',
			'setMembersCollectiveId',
			'setSettingsCollectiveId',
			'show',
		]),

		openShareTab(collective) {
			this.$router.push(`/${encodeURIComponent(collective.name)}`)
			this.show('sidebar')
			this.setActiveSidebarTab('sharing')
		},

		openCollectiveMembers() {
			this.setMembersCollectiveId(this.collective.id)
		},

		openCollectiveSettings() {
			this.setSettingsCollectiveId(this.collective.id)
		},

		leaveCollectiveWithUndo(collective) {
			showUndo(
				t('collectives', 'Left collective {name}', { name: collective.name }),
				() => {
					clearTimeout(this.leaveTimeout)
					this.leaveTimeout = null
					this.dispatchUnmarkCollectiveDeleted(collective)
				},
			)

			this.dispatchMarkCollectiveDeleted(collective)

			this.leaveTimeout = setTimeout(() => {
				this.dispatchLeaveCircle(collective).catch((e) => {
					console.error('Failed to leave collective', e)
					let errorMessage = ''
					if (e.response?.data?.ocs?.meta?.message) {
						errorMessage = e.response.data.ocs.meta.message
					}
					showError(t('collectives', 'Could not leave the collective. {errorMessage}', { errorMessage }))
					this.dispatchUnmarkCollectiveDeleted(collective)
				})
			}, 10000)
		},
	},
}
</script>

<style scoped>

</style>
