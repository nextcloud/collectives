<template>
	<NcActions :force-menu="true" @click.native.stop>
		<NcActionLink v-if="showFilesLink"
			:href="filesUrl"
			icon="icon-files-dark"
			:close-after-click="true">
			{{ t('collectives', 'Show in Files') }}
		</NcActionLink>
		<NcActionButton v-if="!isTemplate && !isLandingPage"
			:close-after-click="true"
			@click.native="show('details')"
			@click="gotoPageEmojiPicker">
			<template #icon>
				<EmoticonOutlineIcon :size="20" />
			</template>
			{{ setEmojiString }}
		</NcActionButton>
		<NcActionButton v-if="!isTemplate"
			:close-after-click="true"
			class="action-button-template"
			@click.native="show('details')"
			@click="editTemplate(pageId)">
			<template #icon>
				<PagesTemplateIcon :size="14" />
			</template>
			{{ editTemplateString }}
		</NcActionButton>
		<NcActionButton v-if="!isLandingPage"
			:close-after-click="true"
			:disabled="hasSubpages"
			@click.native="show('details')"
			@click="deletePage(parentId, pageId)">
			<template #icon>
				<DeleteOffIcon v-if="hasSubpages" :size="20" />
				<DeleteIcon v-else :size="20" />
			</template>
			{{ deletePageString }}
		</NcActionButton>
		<NcActionSeparator v-if="lastUserId && lastUserDisplayName" />
		<PageActionLastUser :last-user-id="lastUserId" :last-user-display-name="lastUserDisplayName" :timestamp="timestamp" />
	</NcActions>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import { NcActions, NcActionButton, NcActionLink, NcActionSeparator } from '@nextcloud/vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DeleteOffIcon from 'vue-material-design-icons/DeleteOff.vue'
import EmoticonOutlineIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import PagesTemplateIcon from '../Icon/PagesTemplateIcon.vue'
import PageActionLastUser from './PageActionLastUser.vue'
import pageMixin from '../../mixins/pageMixin.js'

export default {
	name: 'PageActionMenu',

	components: {
		NcActions,
		NcActionButton,
		NcActionLink,
		NcActionSeparator,
		DeleteIcon,
		DeleteOffIcon,
		EmoticonOutlineIcon,
		PagesTemplateIcon,
		PageActionLastUser,
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
		isTemplate: {
			type: Boolean,
			default: false,
		},
		showFilesLink: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		...mapGetters([
			'loading',
			'showTemplates',
			'visibleSubpages',
		]),

		filesUrl() {
			return generateUrl(`/apps/files/?fileid=${this.currentPage.id}`)
		},

		editTemplateString() {
			return this.hasTemplate
				? t('collectives', 'Edit template for subpages')
				: t('collectives', 'Add template for subpages')
		},

		setEmojiString() {
			return t('collective', 'Select emoji')
		},

		deletePageString() {
			return this.hasSubpages
				? t('collectives', 'Cannot delete page with subpages')
				: this.isTemplate
					? t('collectives', 'Delete template')
					: t('collectives', 'Delete page')
		},

		hasTemplate() {
			return !!this.templatePage(this.pageId)
		},

		hasSubpages() {
			return !!this.visibleSubpages(this.pageId).length || !!this.hasTemplate
		},
	},

	methods: {
		...mapMutations(['show']),

		gotoPageEmojiPicker() {
			if (this.pageUrl && (this.currentPage.id !== this.pageId)) {
				this.$router.push(this.pageUrl)
			}
			this.$nextTick(() => {
				this.show('pageEmojiPicker')
			})
		},
	},
}
</script>
