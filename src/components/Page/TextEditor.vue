<template>
	<div>
		<WidgetHeading v-if="isLandingPage"
			:title="t('collectives', 'Landing page')"
			class="text-container-heading"
			:class="[isFullWidthView ? 'full-width-view' : 'sheet-view']" />
		<SkeletonLoading v-show="!contentLoaded" class="page-content-skeleton" type="text" />
		<div v-show="contentLoaded && showReader"
			ref="reader"
			data-collectives-el="reader"
			:class="[isFullWidthView ? 'full-width-view' : 'sheet-view']" />
		<div v-if="currentCollectiveCanEdit"
			v-show="showEditor"
			ref="editor"
			data-collectives-el="editor"
			:class="[isFullWidthView ? 'full-width-view' : 'sheet-view']" />
	</div>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { showError } from '@nextcloud/dialogs'
import WidgetHeading from './LandingPageWidgets/WidgetHeading.vue'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import {
	GET_VERSIONS,
	TOUCH_PAGE,
} from '../../store/actions.js'
import pageContentMixin from '../../mixins/pageContentMixin.js'
import editorMixin from '../../mixins/editorMixin.js'
import SkeletonLoading from '../SkeletonLoading.vue'

export default {
	name: 'TextEditor',

	components: {
		SkeletonLoading,
		WidgetHeading,
	},

	mixins: [
		pageContentMixin,
		editorMixin,
	],

	data() {
		return {
			textEditWatcher: null,
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentPage',
			'currentPageDavUrl',
			'hasVersionsLoaded',
			'isFullWidthView',
			'isLandingPage',
			'isPublic',
			'isTemplatePage',
			'isTextEdit',
			'loading',
		]),

		showReader() {
			return this.readOnly
		},

		showEditor() {
			return !this.readOnly
		},

		readOnly() {
			return !this.currentCollectiveCanEdit || this.readMode || !this.isTextEdit
		},

		contentLoaded() {
			// Either `pageContent` is filled from editor or we finished fetching it from DAV
			return !!this.pageContent || !this.loading('pageContent')
		},
	},

	watch: {
		'currentPage.timestamp'() {
			this.getPageContent()
		},
	},

	beforeMount() {
		// Change back to default view mode
		this.setTextView()

		this.load('editor')
		this.load('pageContent')
	},

	mounted() {
		this.setupReader()
		this.setupEditor()
		this.getPageContent().then(() => {
			this.initEditMode()
		})

		this.textEditWatcher = this.$watch('isTextEdit', (val) => {
			if (val === false) {
				this.stopEdit()
			}
		})
		subscribe('collectives:attachment:restore', this.restoreAttachment)
	},

	beforeDestroy() {
		unsubscribe('collectives:attachment:restore', this.restoreAttachment)
		this.textEditWatcher()
	},

	methods: {
		...mapMutations([
			'load',
			'done',
			'setTextEdit',
			'setTextView',
		]),

		...mapActions({
			dispatchTouchPage: TOUCH_PAGE,
			dispatchGetVersions: GET_VERSIONS,
		}),

		restoreAttachment(name) {
			// inspired by the fixedEncodeURIComponent function suggested in
			// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURIComponent
			const src = '.attachments.' + this.currentPage.id + '/' + name
			// simply get rid of brackets to make sure link text is valid
			// as it does not need to be unique and matching the real file name
			const alt = name.replaceAll(/[[\]]/g, '')

			this.editor.insertAtCursor(`<img src="${src}" alt="${alt}" />`)
		},

		initEditMode() {
			// Open in edit mode when pageMode is set
			if (!!this.currentCollective.pageMode
				// for template pages
				|| this.isTemplatePage
				// for new pages
				|| this.loading('newPageContent')
				// or when page is empty
				|| !this.pageContent?.trim()) {
				this.setTextEdit()
				this.done('newPageContent')
			}
		},

		stopEdit() {
			// switch back to edit if there's no content
			if (!this.pageContent?.trim()) {
				this.setTextEdit()
				this.$nextTick(() => {
					this.focusEditor()
				})
				return
			}

			const changed = this.editorContent && (this.editorContent !== this.davContent)
			if (changed) {
				this.dispatchTouchPage()
				if (!this.isPublic && this.hasVersionsLoaded) {
					this.dispatchGetVersions(this.currentPage.id)
				}

				// Save pending changes in editor
				// TODO: detect missing connection and display warning
				this.editor.save()
					.catch(() => {
						showError(t('collectives', 'Error saving the document. Please try again.'))
						this.setTextEdit()
					})
			}
		},

		async getPageContent() {
			this.davContent = await this.fetchPageContent(this.currentPageDavUrl)
			this.reader?.setContent(this.pageContent)
			this.done('pageContent')
		},
	},
}
</script>

<style lang="scss" scoped>
.text-container-heading {
	padding-left: 14px;
}

.page-content-skeleton {
	padding-top: 44px;
}

:deep([data-text-el='editor-container']) {
	/* Remove scrolling mechanism from editor-container, required for menubar stickyness */
	overflow: visible;

	div.editor {
		/* Adjust to page titlebar height */
		div.text-menubar {
			margin: auto;
			top: 59px;
		}
	}
}

@media print {
	/* Don't print unwanted elements */
	.text-container-heading {
		display: none !important;
	}
}
</style>

<style lang="scss">
@media print {
	h1, h2, h3 {
		page-break-after: avoid;
		break-after: avoid;
	}
}
</style>
