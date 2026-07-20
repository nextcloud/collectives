<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="!submenu">
		<NcActionButton
			v-if="isCollectiveAdmin(collective)"
			closeAfterClick
			:disabled="!networkOnline"
			@click="openCollectiveMembers()">
			<template #icon>
				<AccountMultipleIcon :size="20" />
			</template>
			{{ t('collectives', 'Manage members') }}
		</NcActionButton>
		<NcActionButton
			v-if="collectiveCanShare(collective)"
			closeAfterClick
			@click="openShareTab(collective)">
			{{ t('collectives', 'Share link') }}
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
		</NcActionButton>
		<NcActionSeparator v-if="isCollectiveAdmin(collective) || collectiveCanShare(collective)" />
		<NcActionButton
			v-if="!isPublic && collective.canEdit"
			closeAfterClick
			:disabled="!networkOnline"
			@click="openTemplates()">
			<template #icon>
				<PageTemplateIcon :size="18" />
			</template>
			{{ t('collectives', 'Manage templates') }}
		</NcActionButton>
		<NcActionLink
			closeAfterClick
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
			closeAfterClick
			:disabled="!networkOnline"
			@click="openCollectiveSettings()">
			<template #icon>
				<CogIcon :size="20" />
			</template>
			{{ t('collectives', 'Settings') }}
		</NcActionButton>
		<NcActionButton
			v-if="!isPublic"
			isMenu
			:disabled="!networkOnline"
			@click="$emit('update:submenu', 'notifications')">
			<template #icon>
				<BellOutlineIcon :size="20" />
			</template>
			{{ t('collectives', 'Notifications') }}
		</NcActionButton>
		<NcActionButton
			v-if="!isPublic && collective.canLeave !== false"
			closeAfterClick
			:disabled="!networkOnline"
			@click="leaveCollectiveWithUndo(collective)">
			{{ t('collectives', 'Leave collective') }}
			<template #icon>
				<LogoutIcon :size="20" />
			</template>
		</NcActionButton>
		<NcActionButton
			v-if="collectiveExtraAction"
			closeAfterClick
			:disabled="!networkOnline"
			@click="collectiveExtraAction.click()">
			{{ collectiveExtraAction.title }}
			<template #icon>
				<OpenInNewIcon :size="20" />
			</template>
		</NcActionButton>
	</div>
	<div v-else-if="submenu === 'notifications'">
		<NcActionButton @click="$emit('update:submenu', null)">
			<template #icon>
				<ArrowLeftIcon :size="20" />
			</template>
			{{ t('collectives', 'Back') }}
		</NcActionButton>
		<NcActionSeparator />
		<NcActionButton
			v-for="option in notifyOptions"
			:key="option.value"
			closeAfterClick
			type="radio"
			:modelValue="String(collective.userNotify)"
			:value="String(option.value)"
			@click="setNotify(option.value)">
			<template #icon>
				<component :is="option.icon" :size="20" />
			</template>
			{{ option.label }}
		</NcActionButton>
	</div>
</template>

<script>
import { showError, showUndo } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { mapActions, mapState } from 'pinia'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultipleOutline.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import BellOffOutlineIcon from 'vue-material-design-icons/BellOffOutline.vue'
import BellOutlineIcon from 'vue-material-design-icons/BellOutline.vue'
import BellRingOutlineIcon from 'vue-material-design-icons/BellRingOutline.vue'
import CogIcon from 'vue-material-design-icons/CogOutline.vue'
import LogoutIcon from 'vue-material-design-icons/Logout.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariantOutline.vue'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import { notifyLevels } from '../../constants.js'
import { useCirclesStore } from '../../stores/circles.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'NcActionCollectiveActions',

	components: {
		AccountMultipleIcon,
		ArrowLeftIcon,
		BellOffOutlineIcon,
		BellOutlineIcon,
		BellRingOutlineIcon,
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
		submenu: {
			type: String,
			default: null,
		},

		collective: {
			type: Object,
			required: true,
		},

		networkOnline: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['update:submenu'],

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

		...mapState(usePagesStore, ['pagesTreeWalkForCollective']),

		circleLink() {
			return generateUrl('/apps/contacts/direct/circle/' + this.collective.circleId)
		},

		printLink() {
			return generateUrl(`/apps/collectives${this.collectivePrintPath(this.collective)}`)
		},

		notifyOptions() {
			return [
				{ value: notifyLevels.NOTIFY_ALL, icon: BellRingOutlineIcon, label: t('collectives', 'All changes') },
				{ value: notifyLevels.NOTIFY_MENTION, icon: BellOutlineIcon, label: t('collectives', '@-mentions only') },
				{ value: notifyLevels.NOTIFY_OFF, icon: BellOffOutlineIcon, label: t('collectives', 'Off') },
			]
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

			const pageIds = this.pagesTreeWalkForCollective(this.collective).map((p) => p.id)
			return {
				title: collectiveExtraAction.title ?? t('collectives', 'Extra action'),
				click: () => collectiveExtraAction.click(pageIds) ?? function() {},
			}
		},
	},

	methods: {
		t,

		...mapActions(useRootStore, ['setActiveSidebarTab', 'showSidebar']),
		...mapActions(useCirclesStore, ['leaveCircle']),
		...mapActions(useCollectivesStore, [
			'markCollectiveDeleted',
			'setMembersCollectiveId',
			'setSettingsCollectiveId',
			'setCollectiveUserSettingNotify',
			'setTemplatesCollectiveId',
			'unmarkCollectiveDeleted',
		]),

		openTemplates() {
			this.setTemplatesCollectiveId(this.collective.id)
		},

		async openShareTab(collective) {
			await this.$router.push(`/${encodeURIComponent(collective.name)}`)
			this.showSidebar()
			this.setActiveSidebarTab('sharing')
		},

		openCollectiveMembers() {
			this.setMembersCollectiveId(this.collective.id)
		},

		openCollectiveSettings() {
			this.setSettingsCollectiveId(this.collective.id)
		},

		setNotify(level) {
			this.setCollectiveUserSettingNotify({
				id: this.collective.id,
				notify: level,
			})
			this.$emit('update:submenu', null)
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
