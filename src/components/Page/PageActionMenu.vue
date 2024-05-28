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
				:checked="isFullWidthView"
				@check="onCheckFullWidthView"
				@uncheck="onUncheckFullWidthView">
				{{ t('collectives', 'Full width') }}
			</NcActionCheckbox>
			<NcActionButton v-if="!inPageList"
				:close-after-click="true"
				@click.native="toggle('outline')">
				<template #icon>
					<FormatListBulletedIcon :size="20" />
				</template>
				{{ toggleOutlineString }}
			</NcActionButton>
			<NcActionSeparator v-if="!inPageList" />

			<!-- Open in files app action: only displayed in page title menu -->
			<NcActionLink v-if="!inPageList && showFilesLink"
				:href="filesUrl"
				icon="icon-files-dark"
				:close-after-click="true">
				{{ t('collectives', 'Show in Files') }}
			</NcActionLink>

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
			<NcActionButton v-if="inPageList && currentCollectiveCanEdit && !isTemplate && !isLandingPage"
				:close-after-click="true"
				@click.native="show('details')"
				@click="gotoPageEmojiPicker">
				<template #icon>
					<EmoticonOutlineIcon :size="20" />
				</template>
				{{ setEmojiString }}
			</NcActionButton>

			<!-- Edit template for subpages -->
			<NcActionButton v-if="currentCollectiveCanEdit && !isTemplate"
				:close-after-click="true"
				class="action-button-template"
				@click.native="show('details')"
				@click="editTemplate(pageId)">
				<template #icon>
					<PagesTemplateIcon :size="18" />
				</template>
				{{ editTemplateString }}
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
	</div>
</template>

<script>
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import { SET_FULL_WIDTH_VIEW } from '../../store/actions.js'
import { NcActions, NcActionButton, NcActionCheckbox, NcActionLink, NcActionSeparator } from '@nextcloud/vue'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import CollectiveActions from '../Collective/CollectiveActions.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DockRightIcon from 'vue-material-design-icons/DockRight.vue'
import EmoticonOutlineIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import FormatListBulletedIcon from 'vue-material-design-icons/FormatListBulleted.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import MoveOrCopyModal from './MoveOrCopyModal.vue'
import PagesTemplateIcon from '../Icon/PagesTemplateIcon.vue'
import PageActionLastUser from './PageActionLastUser.vue'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import pageMixin from '../../mixins/pageMixin.js'

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
		EmoticonOutlineIcon,
		FormatListBulletedIcon,
		OpenInNewIcon,
		PagesTemplateIcon,
		PageActionLastUser,
		ShareVariantIcon,
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
		isTemplate: {
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
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentCollectiveCanShare',
			'currentCollectiveIsPageShare',
			'hasSubpages',
			'isFullWidthView',
			'loading',
			'pagesTreeWalk',
			'showing',
			'showTemplates',
			'visibleSubpages',
		]),

		displaySidebarAction() {
			return isMobile && !this.inPageList && !this.showing('sidebar')
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
			return this.showing('outline')
				? t('collectives', 'Hide outline')
				: t('collectives', 'Show outline')
		},

		filesUrl() {
			return generateUrl(`/f/${this.currentPage.id}`)
		},

		editTemplateString() {
			return this.hasTemplate
				? t('collectives', 'Edit template for subpages')
				: t('collectives', 'Add template for subpages')
		},

		setEmojiString() {
			return t('collectives', 'Select emoji')
		},

		deletePageString() {
			return this.hasSubpages(this.pageId)
				? t('collectives', 'Delete page and subpages')
				: this.isTemplate
					? t('collectives', 'Delete template')
					: t('collectives', 'Delete page')
		},

		hasTemplate() {
			return !!this.templatePage(this.pageId)
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
		...mapMutations([
			'setActiveSidebarTab',
			'show',
			'toggle',
		]),

		...mapActions({
			dispatchSetFullWidthView: SET_FULL_WIDTH_VIEW,
		}),

		onCheckFullWidthView() {
			this.dispatchSetFullWidthView(true)
		},

		onUncheckFullWidthView() {
			this.dispatchSetFullWidthView(false)
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
	},
}
</script>
