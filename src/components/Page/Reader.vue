<template>
	<div id="text-wrapper" class="richEditor">
		<input id="sharingToken"
			type="hidden"
			name="sharingToken"
			:value="shareTokenParam">
		<div id="text" class="editor">
			<PageInfoBar :current-page="currentPage" :is-full-width-view="isFullWidthView" />
			<SkeletonLoading v-if="loading('pageContent')" type="text" />
			<RichTextReader v-else
				:content="pageContent"
				@click-link="followLink" />
		</div>
	</div>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import { RichTextReader, AttachmentResolver, ATTACHMENT_RESOLVER, OUTLINE_STATE, OUTLINE_ACTIONS } from '@nextcloud/text'
import { getCurrentUser } from '@nextcloud/auth'
import PageInfoBar from './PageInfoBar.vue'
import SkeletonLoading from '../SkeletonLoading.vue'
import linkHandlerMixin from '../../mixins/linkHandlerMixin.js'

export default {
	name: 'Reader',

	components: {
		SkeletonLoading,
		PageInfoBar,
		RichTextReader,
	},

	mixins: [
		linkHandlerMixin,
	],

	provide() {
		const val = {}
		Object.defineProperties(val, {
			[ATTACHMENT_RESOLVER]: { get: () => this.attachmentResolver },
			[OUTLINE_STATE]: { get: () => this.outline },
			[OUTLINE_ACTIONS]: { get: () => ({ toggle: this.toggleOutlineFromTextComponent }) },
		})
		return val
	},

	props: {
		currentPage: {
			type: Object,
			required: true,
		},
		pageContent: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			outline: {
				visible: false,
				enable: false,
			},
		}
	},

	computed: {
		...mapGetters([
			'currentPageDirectory',
			'isFullWidthView',
			'loading',
			'showing',
			'shareTokenParam',
		]),

		attachmentResolver() {
			return new AttachmentResolver({
				fileId: this.currentPage.id,
				currentDirectory: '/' + this.currentPageDirectory,
				user: getCurrentUser(),
				shareToken: this.shareTokenParam,
			})
		},

		showOutline() {
			return this.showing('outline')
		},
	},

	watch: {
		'showOutline'() {
			this.outline.visible = this.showing('outline')
		},
	},

	methods: {
		...mapMutations([
			'toggle',
		]),
	},
}
</script>

<style scoped lang="scss">
@import '~@nextcloud/text/dist/style.css';

#text-wrapper {
	display: flex;
	width: 100%;
	height: 100%;
}

#text-wrapper.icon-loading #editor {
	opacity: 0.3;
}

#text, .editor {
	background: var(--color-main-background);
	color: var(--color-main-text);
	background-clip: padding-box;
	padding: 0;
	position: relative;
	overflow-y: auto;
	overflow-x: hidden;
	width: 100%;
	margin-left: auto;
	margin-right: auto;
	/* Overflow is required for sticky menubar */
	overflow: visible !important;
}

.text-revision {
	background-color: lightcoral;
}
</style>
