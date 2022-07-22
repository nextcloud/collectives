<template>
	<Actions :force-menu="true" @click.native.stop>
		<ActionLink v-if="showFilesLink"
			:href="filesUrl"
			icon="icon-files-dark"
			:close-after-click="true">
			{{ t('collectives', 'Show in Files') }}
		</ActionLink>
		<ActionButton v-if="!isTemplate && !isLandingPage"
			:close-after-click="true"
			@click.native="show('details')"
			@click="gotoPageEmojiPicker">
			<template #icon>
				<EmoticonOutlineIcon :size="20" />
			</template>
			{{ setEmojiString }}
		</ActionButton>
		<ActionButton v-if="!isTemplate"
			:close-after-click="true"
			class="action-button-template"
			@click.native="show('details')"
			@click="editTemplate(pageId)">
			<template #icon>
				<PagesTemplateIcon :size="14" />
			</template>
			{{ editTemplateString }}
		</ActionButton>
		<ActionButton v-if="!isLandingPage"
			:close-after-click="true"
			:disabled="hasSubpages"
			@click.native="show('details')"
			@click="deletePage(parentId, pageId)">
			<template #icon>
				<DeleteOffIcon v-if="hasSubpages" :size="20" />
				<DeleteIcon v-else :size="20" />
			</template>
			{{ deletePageString }}
		</ActionButton>
		<ActionSeparator v-if="lastUserId" />
		<li v-if="lastUserId" class="action action--user-bubble">
			<button class="action-button action-button--user-bubble" type="button">
				<ClockOutlineIcon :size="20" />
				<LastUserBubble :last-user-id="lastUserId" :timestamp="timestamp" />
			</button>
		</li>
	</Actions>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline'
import DeleteIcon from 'vue-material-design-icons/Delete'
import DeleteOffIcon from 'vue-material-design-icons/DeleteOff'
import EmoticonOutlineIcon from 'vue-material-design-icons/EmoticonOutline'
import PagesTemplateIcon from '../Icon/PagesTemplateIcon.vue'
import LastUserBubble from '../LastUserBubble.vue'
import pageMixin from '../../mixins/pageMixin.js'

export default {
	name: 'PageActionMenu',

	components: {
		Actions,
		ActionButton,
		ActionLink,
		ActionSeparator,
		ClockOutlineIcon,
		DeleteIcon,
		DeleteOffIcon,
		EmoticonOutlineIcon,
		PagesTemplateIcon,
		LastUserBubble,
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
			return !!this.visibleSubpages(this.pageId.id).length || !!this.hasTemplate
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

<style lang="scss" scoped>
.action-item--single {
	&.action-button-add {
		opacity: 1;
	}
}

.action--user-bubble {
	pointer-events: none;
}

.action-button--user-bubble {
	display: flex;
	align-items: flex-start;
	width: 100%;
	height: auto;
	margin: 0;
	padding: 0;
	padding-right: 14px;
	box-sizing: border-box;
	white-space: nowrap;
	opacity: .7;
	border: 0;
	border-radius: 0;
	background-color: transparent;
	box-shadow: none;
	font-weight: normal;
	font-size: var(--default-font-size);
	line-height: 44px;

	.material-design-icon {
		width: 44px;
		height: 44px;
	}
}
</style>
