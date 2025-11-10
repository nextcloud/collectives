<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcActionButton
			v-if="isCollectiveAdmin(collective)"
			:close-after-click="true"
			:disabled="!networkOnline"
			@click="openCollectiveMembers()">
			<template #icon>
				<AccountMultipleIcon :size="20" />
			</template>
			{{ t('collectives', 'Manage members') }}
		</NcActionButton>
		<NcActionButton
			v-if="collectiveCanShare(collective)"
			:close-after-click="true"
			@click="openShareTab(collective)">
			{{ t('collectives', 'Share link') }}
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
		</NcActionButton>
		<NcActionSeparator v-if="isCollectiveAdmin(collective) || collectiveCanShare(collective)" />
		<NcActionButton
			v-if="!isPublic && collective.canEdit"
			:close-after-click="true"
			:disabled="!networkOnline"
			@click="openTemplates()">
			<template #icon>
				<PageTemplateIcon :size="18" />
			</template>
			{{ t('collectives', 'Manage templates') }}
		</NcActionButton>
		<NcActionLink
			:close-after-click="true"
			:href="printLink"
			:class="{ 'action-link--disabled': !networkOnline }"
			target="_blank">
			{{ t('collectives', 'Export or print') }}
			<template #icon>
				<DownloadIcon :size="20" />
			</template>
		</NcActionLink>
		<NcActionButton
			v-if="isCollectiveAdmin(collective)"
			:close-after-click="true"
			:disabled="!networkOnline"
			@click="openCollectiveSettings()">
			<template #icon>
				<CogIcon :size="20" />
			</template>
			{{ t('collectives', 'Settings') }}
		</NcActionButton>
		<NcActionButton
			v-if="!isPublic && collective.canLeave !== false"
			:close-after-click="true"
			:disabled="!networkOnline"
			@click="leaveCollectiveWithUndo(collective)">
			{{ t('collectives', 'Leave collective') }}
			<template #icon>
				<LogoutIcon :size="20" />
			</template>
		</NcActionButton>
		<NcActionButton
			v-if="collectiveExtraAction"
			:close-after-click="true"
			:disabled="!networkOnline"
			@click="collectiveExtraAction.click()">
			{{ collectiveExtraAction.title }}
			<template #icon>
				<OpenInNewIcon :size="20" />
			</template>
		</NcActionButton>
	</div>
</template>

<script>
import { showError, showUndo } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import { NcActionButton, NcActionLink, NcActionSeparator } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultipleOutline.vue'
import CogIcon from 'vue-material-design-icons/CogOutline.vue'
import LogoutIcon from 'vue-material-design-icons/Logout.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariantOutline.vue'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import { useCirclesStore } from '../../stores/circles.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

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
		OpenInNewIcon,
		PageTemplateIcon,
		ShareVariantIcon,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},

		networkOnline: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			leaveTimeout: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['isPublic']),
		...mapState(useCollectivesStore, [
			'collectiveCanShare',
			'collectivePrintPath',
			'isCollectiveAdmin',
		]),

		...mapState(usePagesStore, ['pagesTreeWalk']),

		circleLink() {
			return generateUrl('/apps/contacts/direct/circle/' + this.collective.circleId)
		},

		printLink() {
			return generateUrl(`/apps/collectives${this.collectivePrintPath(this.collective)}`)
		},

		/**
		 * Other apps can register an extra collective action via
		 * window.OCA.Collectives.CollectiveExtraAction
		 */
		collectiveExtraAction() {
			const collectiveExtraAction = window.OCA.Collectives?.CollectiveExtraAction
			if (!collectiveExtraAction) {
				return null
			}

			const pageIds = this.pagesTreeWalk().map((p) => p.id)
			return {
				title: collectiveExtraAction.title ?? t('collectives', 'Extra action'),
				click: () => collectiveExtraAction.click(pageIds) ?? function() {},
			}
		},
	},

	methods: {
		...mapActions(useRootStore, ['setActiveSidebarTab', 'show']),
		...mapActions(useCirclesStore, ['leaveCircle']),
		...mapActions(useCollectivesStore, [
			'markCollectiveDeleted',
			'setMembersCollectiveId',
			'setSettingsCollectiveId',
			'setTemplatesCollectiveId',
			'unmarkCollectiveDeleted',
		]),

		openTemplates() {
			this.setTemplatesCollectiveId(this.collective.id)
		},

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
				t('collectives', 'You left collective {name}', { name: collective.name }),
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

<style scoped lang="scss">
.action-link--disabled {
	pointer-events: none;
	opacity: 0.5;
}
</style>
