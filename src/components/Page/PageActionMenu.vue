<template>
	<div>
		<NcActions :force-menu="true" @click.native.stop>
			<NcActionButton v-if="!inPageList && !showing('sidebar') && isMobile"
				icon="icon-menu-sidebar"
				:aria-label="t('collectives', 'Open page sidebar')"
				aria-controls="app-sidebar-vue"
				:close-after-click="true"
				@click="toggle('sidebar')">
				{{ t('collectives', 'Open page sidebar') }}
			</NcActionButton>
			<CollectiveActions v-if="inPageList && isLandingPage"
				:collective="currentCollective" />
			<NcActionButton v-if="collectiveExtraAction"
				:close-after-click="true"
				@click="collectiveExtraAction.click()">
				{{ collectiveExtraAction.title }}
				<template #icon>
					<OpenInNewIcon :size="20" />
				</template>
			</NcActionButton>
			<NcActionCheckbox v-if="!inPageList && !isMobile"
				:checked="isFullWidthView"
				@check="onCheckFullWidthView"
				@uncheck="onUncheckFullWidthView">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
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
			<NcActionLink v-if="showFilesLink"
				:href="filesUrl"
				icon="icon-files-dark"
				:close-after-click="true">
				{{ t('collectives', 'Show in Files') }}
			</NcActionLink>
			<NcActionButton v-if="inPageList && currentCollectiveCanEdit && !isTemplate && !isLandingPage"
				:close-after-click="true"
				@click.native="show('details')"
				@click="gotoPageEmojiPicker">
				<template #icon>
					<EmoticonOutlineIcon :size="20" />
				</template>
				{{ setEmojiString }}
			</NcActionButton>
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
			<NcActionButton v-if="inPageList && currentCollectiveCanEdit && !isLandingPage"
				:close-after-click="true"
				@click="onOpenMoveModal">
				<template #icon>
					<OpenInNewIcon :size="20" />
				</template>
				{{ t('collectives', 'Move page') }}
			</NcActionButton>
			<NcActionButton v-if="currentCollectiveCanEdit && !isLandingPage"
				:close-after-click="true"
				@click="deletePage(parentId, pageId)">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ deletePageString }}
			</NcActionButton>
			<NcActionSeparator v-if="lastUserId && lastUserDisplayName" />
			<PageActionLastUser :last-user-id="lastUserId" :last-user-display-name="lastUserDisplayName" :timestamp="timestamp" />
		</NcActions>
		<MoveModal v-if="showMoveModal"
			:page-id="pageId"
			:parent-id="parentId"
			@close="onCloseMoveModal" />
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
import EmoticonOutlineIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import FormatListBulletedIcon from 'vue-material-design-icons/FormatListBulleted.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import MoveModal from './MoveModal.vue'
import PagesTemplateIcon from '../Icon/PagesTemplateIcon.vue'
import PageActionLastUser from './PageActionLastUser.vue'
import pageMixin from '../../mixins/pageMixin.js'

export default {
	name: 'PageActionMenu',

	components: {
		CollectiveActions,
		MoveModal,
		NcActions,
		NcActionButton,
		NcActionCheckbox,
		NcActionLink,
		NcActionSeparator,
		DeleteIcon,
		EmoticonOutlineIcon,
		FormatListBulletedIcon,
		PagesTemplateIcon,
		PageActionLastUser,
		OpenInNewIcon,
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
			showMoveModal: false,
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentCollectiveCanEdit',
			'hasSubpages',
			'isFullWidthView',
			'loading',
			'pagesTreeWalk',
			'showing',
			'showTemplates',
			'visibleSubpages',
		]),

		toggleOutlineString() {
			return this.showing('outline')
				? t('collectives', 'Hide outline')
				: t('collectives', 'Show outline')
		},

		filesUrl() {
			return generateUrl(`/apps/files/?fileid=${this.currentPage.id}`)
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
		...mapMutations(['show', 'toggle']),

		...mapActions({
			dispatchSetFullWidthView: SET_FULL_WIDTH_VIEW,
		}),

		onCheckFullWidthView() {
			this.dispatchSetFullWidthView(true)
		},

		onUncheckFullWidthView() {
			this.dispatchSetFullWidthView(false)
		},

		gotoPageEmojiPicker() {
			if (this.pageUrl && (this.currentPage.id !== this.pageId)) {
				this.$router.push(this.pageUrl)
			}
			this.$nextTick(() => {
				this.show('pageEmojiPicker')
			})
		},

		onOpenMoveModal() {
			this.showMoveModal = true
		},

		onCloseMoveModal() {
			this.showMoveModal = false
		},
	},
}
</script>
