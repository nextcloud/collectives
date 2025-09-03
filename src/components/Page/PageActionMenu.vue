<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcActions :force-menu="true" @click.native.stop>
			<!-- Collective actions: only displayed for landing page in page list -->
			<CollectiveActions v-if="displayCollectiveActions"
				:collective="currentCollective" />
			<NcActionButton v-if="displayCollectiveActions && collectiveExtraAction"
				:close-after-click="true"
				@click="collectiveExtraAction.click()">
				{{ collectiveExtraAction.title }}
				<template #icon>
					<OpenInNewIcon :size="20" />
				</template>
			</NcActionButton>
			<NcActionSeparator v-if="displayCollectiveActions" />

			<!-- Last edited info -->
			<PageActionLastUser v-if="displayLastEditedInfo"
				:last-user-id="lastUserId"
				:last-user-display-name="lastUserDisplayName"
				:timestamp="timestamp" />
			<NcActionSeparator v-if="displayLastEditedInfo" />

			<!-- Sidebar toggle: only displayed on mobile and in page title menu -->
			<NcActionButton v-if="displaySidebarAction"
				:aria-label="t('collectives', 'Open page sidebar')"
				aria-controls="app-sidebar-vue"
				:close-after-click="true"
				@click="toggle('sidebar')">
				<template #icon>
					<DockRightIcon :size="20" />
				</template>
				{{ t('collectives', 'Open page sidebar') }}
			</NcActionButton>
			<NcActionSeparator v-if="displaySidebarAction" />

			<!-- Page view options: only displayed in page title menu -->
			<NcActionCheckbox v-if="!inPageList && !isMobile"
				:checked="currentPage.isFullWidth"
				:disabled="!currentCollectiveCanEdit"
				@check="onCheckFullWidthView"
				@uncheck="onUncheckFullWidthView">
				{{ t('collectives', 'Full width') }}
			</NcActionCheckbox>
			<NcActionButton v-if="!inPageList"
				:close-after-click="true"
				@click.native="toggleOutline(currentPage.id)">
				<template #icon>
					<FormatListBulletedIcon :size="20" />
				</template>
				{{ toggleOutlineString }}
			</NcActionButton>

			<NcActionSeparator v-if="!inPageList" />

			<!-- Favor page action: only displayed in page list and not for landing page -->
			<NcActionButton v-if="inPageList"
				:close-after-click="true"
				@click="toggleFavoritePage({ id: currentCollective.id, pageId })">
				<template #icon>
					<StarOffIcon v-if="isFavoritePage(currentCollective.id, pageId)" :size="20" />
					<StarIcon v-else :size="20" />
				</template>
				{{ toggleFavoriteString }}
			</NcActionButton>

			<!-- Share page action: only displayed in page list and not for landing page (already in collectives actions there) -->
			<NcActionButton v-if="inPageList && currentCollectiveCanShare && !isLandingPage"
				:close-after-click="true"
				@click.native="show('details')"
				@click="openShareTab">
				<template #icon>
					<ShareVariantIcon :size="20" />
				</template>
				{{ t('collectives', 'Share link') }}
			</NcActionButton>

			<!-- Edit page emoji: only displayed in page list -->
			<NcActionButton v-if="inPageList && currentCollectiveCanEdit && !isLandingPage"
				:close-after-click="true"
				@click.native="show('details')"
				@click="gotoPageEmojiPicker">
				<template #icon>
					<EmoticonIcon :size="20" />
				</template>
				{{ setEmojiString }}
			</NcActionButton>

			<!-- Open tags modal: always displayed if has edit permissions -->
			<NcActionButton v-if="currentCollectiveCanEdit"
				:close-after-click="true"
				@click="onOpenTagsModal">
				<template #icon>
					<TagMultipleIcon :size="20" />
				</template>
				{{ t('collectives', 'Manage tags') }}
			</NcActionButton>

			<!-- Move/copy page via modal: only displayed in page list -->
			<NcActionButton v-if="inPageList && currentCollectiveCanEdit && !isLandingPage"
				:close-after-click="true"
				@click="onOpenMoveOrCopyModal">
				<template #icon>
					<OpenInNewIcon :size="20" />
				</template>
				{{ t('collectives', 'Move or copy') }}
			</NcActionButton>

			<!-- Download action: only displayed in page title menu -->
			<NcActionLink v-if="!inPageList"
				:href="currentPageDavUrl"
				:download="currentPage.fileName"
				:close-after-click="true">
				<template #icon>
					<DownloadIcon :size="20" />
				</template>
				{{ t('collectives', 'Download') }}
			</NcActionLink>

			<!-- Open in files app action: only displayed in page title menu -->
			<NcActionLink v-if="!inPageList && showFilesLink"
				:href="filesUrl"
				:close-after-click="true">
				<template #icon>
					<FolderIcon :size="20" />
				</template>
				{{ t('collectives', 'Show in Files') }}
			</NcActionLink>

			<!-- Delete page -->
			<NcActionButton v-if="displayDeleteAction"
				:close-after-click="true"
				@click="deletePage(pageId)">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ deletePageString }}
			</NcActionButton>
		</NcActions>
		<MoveOrCopyModal v-if="showMoveOrCopyModal"
			:page-id="pageId"
			:parent-id="parentId"
			@close="onCloseMoveOrCopyModal" />
		<TagsModal v-if="showTagsModal"
			:page-id="pageId"
			@close="onCloseTagsModal" />
	</div>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { generateUrl } from '@nextcloud/router'
import { NcActions, NcActionButton, NcActionCheckbox, NcActionLink, NcActionSeparator } from '@nextcloud/vue'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import CollectiveActions from '../Collective/CollectiveActions.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import DockRightIcon from 'vue-material-design-icons/DockRight.vue'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import EmoticonIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FormatListBulletedIcon from 'vue-material-design-icons/FormatListBulleted.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import MoveOrCopyModal from './MoveOrCopyModal.vue'
import PageActionLastUser from './PageActionLastUser.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariantOutline.vue'
import StarIcon from 'vue-material-design-icons/StarOutline.vue'
import StarOffIcon from 'vue-material-design-icons/StarOffOutline.vue'
import TagMultipleIcon from 'vue-material-design-icons/TagMultiple.vue'
import TagsModal from './TagsModal.vue'
import pageMixin from '../../mixins/pageMixin.js'
import { usePagesStore } from '../../stores/pages.js'

export default {
	name: 'PageActionMenu',

	components: {
		CollectiveActions,
		MoveOrCopyModal,
		NcActions,
		NcActionButton,
		NcActionCheckbox,
		NcActionLink,
		NcActionSeparator,
		DeleteIcon,
		DockRightIcon,
		DownloadIcon,
		EmoticonIcon,
		FolderIcon,
		FormatListBulletedIcon,
		OpenInNewIcon,
		PageActionLastUser,
		ShareVariantIcon,
		StarIcon,
		StarOffIcon,
		TagMultipleIcon,
		TagsModal,
	},

	mixins: [
		isMobile,
		pageMixin,
	],

	props: {
		pageId: {
			type: Number,
			required: true,
		},
		pageUrl: {
			type: String,
			default: null,
		},
		parentId: {
			type: Number,
			required: true,
		},
		timestamp: {
			type: Number,
			required: true,
		},
		lastUserDisplayName: {
			type: String,
			default: null,
		},
		lastUserId: {
			type: String,
			default: null,
		},
		isLandingPage: {
			type: Boolean,
			default: false,
		},
		showFilesLink: {
			type: Boolean,
			default: false,
		},
		inPageList: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			showMoveOrCopyModal: false,
			showTagsModal: false,
		}
	},

	computed: {
		...mapState(useRootStore, ['showing']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentCollectiveCanShare',
			'currentCollectiveIsPageShare',
			'isFavoritePage',
		]),
		...mapState(usePagesStore, [
			'currentPageDavUrl',
			'hasOutline',
			'hasSubpages',
			'pagesTreeWalk',
			'visibleSubpages',
		]),

		displaySidebarAction() {
			return this.isMobile && !this.inPageList && !this.showing('sidebar')
		},

		displayCollectiveActions() {
			return this.inPageList && this.isLandingPage
		},

		displayLastEditedInfo() {
			return this.lastUserId && this.lastUserDisplayName
		},

		displayDeleteAction() {
			return this.currentCollectiveCanEdit
				&& !this.currentCollectiveIsPageShare
				&& !this.isLandingPage
		},

		toggleOutlineString() {
			return this.hasOutline(this.currentPage.id)
				? t('collectives', 'Hide outline')
				: t('collectives', 'Show outline')
		},

		filesUrl() {
			return generateUrl(`/f/${this.currentPage.id}`)
		},

		toggleFavoriteString() {
			return this.isFavoritePage(this.currentCollective.id, this.pageId)
				? t('collectives', 'Remove from favorites')
				: t('collectives', 'Add to favorites')
		},

		setEmojiString() {
			return t('collectives', 'Select emoji')
		},

		deletePageString() {
			return this.hasSubpages(this.pageId)
				? t('collectives', 'Delete page and subpages')
				: t('collectives', 'Delete page')
		},

		/**
		 * Other apps can register an extra collective action via
		 * OCA.Collectives.CollectiveExtraAction
		 */
		collectiveExtraAction() {
			const collectiveExtraAction = this.OCA.Collectives?.CollectiveExtraAction
			if (!collectiveExtraAction) {
				return null
			}

			const pageIds = this.pagesTreeWalk().map(p => p.id)
			return {
				title: collectiveExtraAction.title ?? t('collectives', 'Extra action'),
				click: () => collectiveExtraAction.click(pageIds) ?? function() {},
			}
		},
	},

	methods: {
		...mapActions(useRootStore, [
			'setActiveSidebarTab',
			'show',
			'toggle',
		]),
		...mapActions(useCollectivesStore, [
			'toggleFavoritePage',
		]),
		...mapActions(usePagesStore, [
			'setFullWidthView',
			'toggleOutline',
		]),

		onCheckFullWidthView() {
			this.setFullWidthView({ pageId: this.currentPage.id, fullWidthView: true })
		},

		onUncheckFullWidthView() {
			this.setFullWidthView({ pageId: this.currentPage.id, fullWidthView: false })
		},

		openShareTab() {
			if (this.pageUrl && (this.currentPage.id !== this.pageId)) {
				this.$router.push(this.pageUrl)
			}
			this.$nextTick(() => {
				this.show('sidebar')
				this.setActiveSidebarTab('sharing')
			})
		},

		gotoPageEmojiPicker() {
			if (this.pageUrl && (this.currentPage.id !== this.pageId)) {
				this.$router.push(this.pageUrl)
			}
			this.$nextTick(() => {
				this.show('pageEmojiPicker')
			})
		},

		onOpenMoveOrCopyModal() {
			this.showMoveOrCopyModal = true
		},

		onCloseMoveOrCopyModal() {
			this.showMoveOrCopyModal = false
		},

		onOpenTagsModal() {
			this.showTagsModal = true
		},

		onCloseTagsModal() {
			this.showTagsModal = false
		},
	},
}
</script>
