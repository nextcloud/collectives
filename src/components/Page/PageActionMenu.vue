<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcActions :force-menu="true" @click.native.stop>
			<!-- Collective actions: only displayed for landing page -->
			<template v-if="isLandingPage">
				<CollectiveActions :collective="currentCollective" :network-online="networkOnline" />
				<NcActionSeparator />
			</template>

			<!-- Last edited info -->
			<PageActionLastUser
				v-if="displayLastEditedInfo"
				:last-user-id="lastUserId"
				:last-user-display-name="lastUserDisplayName"
				:timestamp="timestamp" />
			<NcActionSeparator v-if="displayLastEditedInfo" />

			<!-- Sidebar toggle: only displayed on mobile and in page title menu -->
			<template v-if="displaySidebarAction">
				<NcActionButton
					:aria-label="t('collectives', 'Open page sidebar')"
					aria-controls="app-sidebar-vue"
					:close-after-click="true"
					@click="toggle('sidebar')">
					<template #icon>
						<DockRightIcon :size="20" />
					</template>
					{{ t('collectives', 'Open page sidebar') }}
				</NcActionButton>
				<NcActionSeparator />
			</template>

			<!-- Page view options: only displayed in page title menu -->
			<template v-if="!inPageList">
				<NcActionCheckbox
					v-if="!isMobile"
					v-model="currentPage.isFullWidth"
					:disabled="!networkOnline || !currentCollectiveCanEdit"
					@check="onCheckFullWidthView"
					@uncheck="onUncheckFullWidthView">
					{{ t('collectives', 'Full width') }}
				</NcActionCheckbox>
				<NcActionButton
					:close-after-click="true"
					@click.native="toggleOutline(currentPage.id)">
					<template #icon>
						<FormatListBulletedIcon :size="20" />
					</template>
					{{ toggleOutlineString }}
				</NcActionButton>
				<NcActionSeparator v-if="!inPageList" />
			</template>

			<!-- Favor page action: not displayed for landing page -->
			<NcActionButton
				v-if="!isLandingPage"
				:close-after-click="true"
				:disabled="!networkOnline"
				@click="toggleFavoritePage({ id: currentCollective.id, pageId })">
				<template #icon>
					<StarOffIcon v-if="isFavoritePage(currentCollective.id, pageId)" :size="20" />
					<StarIcon v-else :size="20" />
				</template>
				{{ toggleFavoriteString }}
			</NcActionButton>

			<!-- Share page action: not displayed for landing page (already in collectives actions there) -->
			<NcActionButton
				v-if="currentCollectiveCanShare && !isLandingPage"
				:close-after-click="true"
				@click.native="show('details')"
				@click="openShareTab">
				<template #icon>
					<ShareVariantIcon :size="20" />
				</template>
				{{ t('collectives', 'Share link') }}
			</NcActionButton>

			<!-- Edit page emoji: not displayed for landing page -->
			<NcActionButton
				v-if="currentCollectiveCanEdit && !isLandingPage"
				:close-after-click="true"
				:disabled="!networkOnline"
				@click.native="show('details')"
				@click="gotoPageEmojiPicker">
				<template #icon>
					<EmoticonIcon :size="20" />
				</template>
				{{ setEmojiString }}
			</NcActionButton>

			<!-- Open tags modal: always displayed if has edit permissions -->
			<NcActionButton
				v-if="currentCollectiveCanEdit"
				:close-after-click="true"
				:disabled="!networkOnline"
				@click="onOpenTagsModal">
				<template #icon>
					<TagMultipleIcon :size="20" />
				</template>
				{{ t('collectives', 'Manage tags') }}
			</NcActionButton>

			<!-- Move/copy page via modal: always displayed if has edit permissions -->
			<NcActionButton
				v-if="currentCollectiveCanEdit && !isLandingPage"
				:close-after-click="true"
				:disabled="!networkOnline"
				@click="onOpenMoveOrCopyModal">
				<template #icon>
					<OpenInNewIcon :size="20" />
				</template>
				{{ t('collectives', 'Move or copy') }}
			</NcActionButton>

			<!-- Download action: always displayed -->
			<NcActionLink
				:href="currentPageDavUrl"
				:class="{ 'action-link--disabled': !networkOnline }"
				:download="currentPage.fileName"
				:close-after-click="true">
				<template #icon>
					<DownloadIcon :size="20" />
				</template>
				{{ t('collectives', 'Download') }}
			</NcActionLink>

			<!-- Delete page -->
			<NcActionButton
				v-if="displayDeleteAction"
				:close-after-click="true"
				:disabled="!networkOnline"
				@click="deletePage(pageId)">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ deletePageString }}
			</NcActionButton>
		</NcActions>
		<MoveOrCopyModal
			v-if="showMoveOrCopyModal"
			:page-id="pageId"
			:parent-id="parentId"
			@close="onCloseMoveOrCopyModal" />
		<TagsModal
			v-if="showTagsModal"
			:page-id="pageId"
			@close="onCloseTagsModal" />
	</div>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { NcActionButton, NcActionCheckbox, NcActionLink, NcActions, NcActionSeparator } from '@nextcloud/vue'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import DockRightIcon from 'vue-material-design-icons/DockRight.vue'
import EmoticonIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import FormatListBulletedIcon from 'vue-material-design-icons/FormatListBulleted.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariantOutline.vue'
import StarOffIcon from 'vue-material-design-icons/StarOffOutline.vue'
import StarIcon from 'vue-material-design-icons/StarOutline.vue'
import TagMultipleIcon from 'vue-material-design-icons/TagMultiple.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import CollectiveActions from '../Collective/CollectiveActions.vue'
import MoveOrCopyModal from './MoveOrCopyModal.vue'
import PageActionLastUser from './PageActionLastUser.vue'
import TagsModal from './TagsModal.vue'
import pageMixin from '../../mixins/pageMixin.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

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

		inPageList: {
			type: Boolean,
			default: false,
		},

		networkOnline: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const isMobile = useIsMobile()
		return { isMobile }
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
		]),

		displaySidebarAction() {
			return this.isMobile && !this.inPageList && !this.showing('sidebar')
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
				emit('collectives:page:open-emoji-picker')
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

<style scoped lang="scss">
.action-link--disabled {
	pointer-events: none;
	opacity: 0.5;
}
</style>
