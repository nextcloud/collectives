<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar
		v-model:active="active"
		v-model:open="open"
		:name="title"
		noToggle
		@close="close">
		<NcAppSidebarTab
			id="attachments"
			:order="0"
			:name="t('collectives', 'Attachments')">
			<template #icon>
				<PaperclipIcon :size="20" />
			</template>
			<SidebarTabAttachments v-if="showingSidebar" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			id="backlinks"
			:order="1"
			:name="t('collectives', 'Backlinks')">
			<template #icon>
				<ArrowBottomLeftIcon :size="20" />
			</template>
			<SidebarTabBacklinks v-if="showingSidebar" :page="currentPage" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			v-if="!isPublic && currentCollectiveCanShare"
			id="sharing"
			:order="2"
			:name="t('collectives', 'Sharing')">
			<template #icon>
				<ShareVariantIcon :size="20" />
			</template>
			<SidebarTabSharing v-if="showingSidebar" :pageId="currentPageId" />
		</NcAppSidebarTab>
		<NcAppSidebarTab
			v-if="!isPublic && currentCollectiveCanEdit"
			id="versions"
			:order="3"
			:name="t('collectives', 'Versions')">
			<template #icon>
				<BackupRestoreIcon :size="20" />
			</template>
			<SidebarTabVersions
				v-if="showingSidebar"
				:pageId="currentPageId"
				:pageTimestamp="currentPage.timestamp" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import ArrowBottomLeftIcon from 'vue-material-design-icons/ArrowBottomLeft.vue'
import BackupRestoreIcon from 'vue-material-design-icons/BackupRestore.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariantOutline.vue'
import SidebarTabAttachments from './PageSidebar/SidebarTabAttachments.vue'
import SidebarTabBacklinks from './PageSidebar/SidebarTabBacklinks.vue'
import SidebarTabSharing from './PageSidebar/SidebarTabSharing.vue'
import SidebarTabVersions from './PageSidebar/SidebarTabVersions.vue'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useVersionsStore } from '../stores/versions.js'

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

	computed: {
		...mapState(useRootStore, ['activeSidebarTab', 'isPublic', 'showingSidebar']),
		...mapState(useCollectivesStore, [
			'currentCollectiveCanEdit',
			'currentCollectiveCanShare',
		]),

		...mapState(usePagesStore, ['currentPage', 'currentPageId', 'title']),

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
				return this.showingSidebar
			},

			set(value) {
				if (value === true) {
					this.showSidebar()
				} else {
					this.hideSidebar()
				}
			},
		},
	},

	methods: {
		t,

		...mapActions(useRootStore, [
			'hideSidebar',
			'setActiveSidebarTab',
			'showSidebar',
		]),

		...mapActions(useVersionsStore, ['selectVersion']),

		/**
		 * Load the current version and close the sidebar
		 */
		close() {
			this.selectVersion(null)
			this.hideSidebar()
		},
	},
}
</script>
