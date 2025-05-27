<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar ref="sidebar"
		:name="title"
		:active.sync="active"
		:open.sync="open"
		:no-toggle="isMobile"
		:toggle-classes="{
			'page-sidebar-button': true,
			'pre-nc30': isPreNc30,
		}"
		@close="close">
		<NcAppSidebarTab id="attachments"
			:order="0"
			:name="t('collectives', 'Attachments')">
			<template #icon>
				<PaperclipIcon :size="20" />
			</template>
			<SidebarTabAttachments v-if="showing('sidebar')" :page="currentPage" />
		</NcAppSidebarTab>
		<NcAppSidebarTab id="backlinks"
			:order="1"
			:name="t('collectives', 'Backlinks')">
			<template #icon>
				<ArrowBottomLeftIcon :size="20" />
			</template>
			<SidebarTabBacklinks v-if="showing('sidebar')" :page="currentPage" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="!isPublic && currentCollectiveCanShare"
			id="sharing"
			:order="2"
			:name="t('collectives', 'Sharing')">
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
			<SidebarTabSharing v-if="showing('sidebar')" :page-id="currentPage.id" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="!isPublic && currentCollectiveCanEdit"
			id="versions"
			:order="3"
			:name="t('collectives', 'Versions')">
			<template #icon>
				<BackupRestoreIcon :size="20" />
			</template>
			<SidebarTabVersions v-if="showing('sidebar')"
				:page-id="currentPage.id"
				:page-timestamp="currentPage.timestamp" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useVersionsStore } from '../stores/versions.js'
import { NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'
import BackupRestoreIcon from 'vue-material-design-icons/BackupRestore.vue'
import ArrowBottomLeftIcon from 'vue-material-design-icons/ArrowBottomLeft.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import SidebarTabAttachments from './PageSidebar/SidebarTabAttachments.vue'
import SidebarTabBacklinks from './PageSidebar/SidebarTabBacklinks.vue'
import SidebarTabSharing from './PageSidebar/SidebarTabSharing.vue'
import SidebarTabVersions from './PageSidebar/SidebarTabVersions.vue'

export default {
	name: 'PageSidebar',

	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		BackupRestoreIcon,
		ArrowBottomLeftIcon,
		PaperclipIcon,
		ShareVariantIcon,
		SidebarTabAttachments,
		SidebarTabBacklinks,
		SidebarTabSharing,
		SidebarTabVersions,
	},

	mixins: [
		isMobile,
	],

	computed: {
		...mapState(useRootStore, ['activeSidebarTab', 'isPublic', 'showing']),
		...mapState(useCollectivesStore, [
			'currentCollectiveCanEdit',
			'currentCollectiveCanShare',
		]),
		...mapState(usePagesStore, ['currentPage', 'title']),

		active: {
			get() {
				return this.activeSidebarTab
			},
			set(id) {
				this.setActiveSidebarTab(id)
			},
		},

		open: {
			get() {
				return this.showing('sidebar') || false
			},
			set(value) {
				if (value === true) {
					this.show('sidebar')
				} else {
					this.hide('sidebar')
				}
			},
		},

		// TODO: remove when we stop supporting NC < 30
		isPreNc30() {
			return window.getComputedStyle(document.body).getPropertyValue('--default-clickable-area') === '44px'
		},
	},

	methods: {
		...mapActions(useRootStore, [
			'hide',
			'setActiveSidebarTab',
			'show',
		]),
		...mapActions(useVersionsStore, ['selectVersion']),

		/**
		 * Load the current version and close the sidebar
		 */
		close() {
			this.selectVersion(null)
			this.hide('sidebar')
		},
	},
}
</script>

<style lang="scss">
.page-sidebar-button.pre-nc30 {
	inset-block-start: 2px;
}
</style>
