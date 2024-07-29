<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

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
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useCirclesStore } from '../../stores/circles.js'
import { NcActionButton, NcActionLink, NcActionSeparator } from '@nextcloud/vue'
import { showError, showUndo } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import LogoutIcon from 'vue-material-design-icons/Logout.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'

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
		...mapState(useRootStore, ['isPublic', 'shareTokenparam']),
		...mapState(useCollectivesStore, [
			'collectiveCanShare',
			'isCollectiveAdmin',
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
		...mapActions(useRootStore, ['setActiveSidebarTab', 'show']),
		...mapActions(useCirclesStore, ['leaveCircle']),
		...mapActions(useCollectivesStore, [
			'markCollectiveDeleted',
			'setMembersCollectiveId',
			'setSettingsCollectiveId',
			'unmarkCollectiveDeleted',
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
					this.unmarkCollectiveDeleted(collective)
				},
			)

			this.markCollectiveDeleted(collective)

			this.leaveTimeout = setTimeout(() => {
				this.leaveCircle(collective).catch((e) => {
					console.error('Failed to leave collective', e)
					let errorMessage = ''
					if (e.response?.data?.ocs?.meta?.message) {
						errorMessage = e.response.data.ocs.meta.message
					}
					showError(t('collectives', 'Could not leave the collective. {errorMessage}', { errorMessage }))
					this.unmarkCollectiveDeleted(collective)
				})
			}, 10000)
		},
	},
}
</script>

<style scoped>

</style>
