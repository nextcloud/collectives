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
			:class="{'sheet-view': !isFullWidthView}" />
		<div v-if="currentCollectiveCanEdit"
			v-show="showEditor"
			ref="editor"
			data-collectives-el="editor"
			:class="{'sheet-view': !isFullWidthView}" />
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
import linkHandlerMixin from '../../mixins/linkHandlerMixin.js'
import pageContentMixin from '../../mixins/pageContentMixin.js'
import PageInfoBar from '../Page/PageInfoBar.vue'
import SkeletonLoading from '../SkeletonLoading.vue'

export default {
	name: 'TextEditor',

	components: {
		SkeletonLoading,
		WidgetHeading,
	},

	mixins: [
		linkHandlerMixin,
		pageContentMixin,
	],

	data() {
		return {
			editor: null,
			davContent: '',
			editorContent: null,
			reader: null,
			readMode: true,
			scrollTop: 0,
			textEditWatcher: null,
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentPage',
			'currentPageDavUrl',
			'currentPageFilePath',
			'hasVersionsLoaded',
			'isFullWidthView',
			'isLandingPage',
			'isPublic',
			'isTemplatePage',
			'isTextEdit',
			'loading',
			'shareTokenParam',
			'showing',
		]),

		pageContent() {
			return this.editorContent?.trim() || this.davContent
		},

		showReader() {
			return this.readOnly
		},

		showEditor() {
			return !this.readOnly
		},

		waitForEditor() {
			return this.readMode && this.isTextEdit
		},

		readOnly() {
			return !this.currentCollectiveCanEdit || this.readMode | !this.isTextEdit
		},

		showOutline() {
			return this.showing('outline')
		},

		contentLoaded() {
			// Either `pageContent` is filled from editor or we finished fetching it from DAV
			return this.pageContent || !this.loading('pageContent')
		},
	},

	watch: {
		'currentPage.timestamp'() {
			this.getPageContent()
		},
		'showOutline'(value) {
			this.editor?.setShowOutline(value)
			this.reader?.setShowOutline(value)
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
			if (val === true) {
				this.startEdit()
			} else {
				this.stopEdit()
			}
		})
		subscribe('collectives:attachment:restore', this.restoreAttachment)
	},

	beforeDestroy() {
		unsubscribe('collectives:attachment:restore', this.restoreAttachment)
		this.textEditWatcher()
		this.editor?.destroy()
		this.reader?.destroy()
	},

	methods: {
		...mapMutations([
			'load',
			'done',
			'hide',
			'setTextEdit',
			'setTextView',
			'show',
		]),

		...mapActions({
			dispatchTouchPage: TOUCH_PAGE,
			dispatchGetVersions: GET_VERSIONS,
		}),

		async setupReader() {
			this.reader = await window.OCA.Text.createEditor({
				el: this.$refs.reader,
				content: this.pageContent,
				readOnly: true,
				readonlyBar: {
					component: PageInfoBar,
					props: {
						currentPage: this.currentPage,
						isFullWidthView: this.isFullWidthView,
					},
				},
				onLinkClick: (_event, attrs) => {
					this.followLink(_event, attrs)
				},
				onOutlineToggle: (visible) => {
					this.toggleOutlineFromEditor(visible)
				},
			})
		},

		async setupEditor() {
			this.editor = this.currentCollectiveCanEdit
				? await window.OCA.Text.createEditor({
					el: this.$refs.editor,
					fileId: this.currentPage.id,
					filePath: `/${this.currentPageFilePath}`,
					readOnly: false,
					shareToken: this.shareTokenParam || null,
					autofocus: false,
					onLoaded: () => {
						this.readyEditor()
					},
					onUpdate: ({ markdown }) => {
						this.editorContent = markdown
						this.reader?.setContent(this.pageContent)
					},
					onOutlineToggle: (visible) => {
						this.toggleOutlineFromEditor(visible)
					},
				})
				: null
		},

		focusEditor() {
			this.editor?.focus()
		},

		restoreAttachment(name) {
			// inspired by the fixedEncodeURIComponent function suggested in
			// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURIComponent
			const src = '.attachments.' + this.currentPage.id + '/' + name
			// simply get rid of brackets to make sure link text is valid
			// as it does not need to be unique and matching the real file name
			const alt = name.replaceAll(/[[\]]/g, '')

			this.editor.insertAtCursor(`<img src="${src}" alt="${alt}" />`)
		},

		/**
		 * Set readMode to false
		 */
		readyEditor() {
			this.done('editor')

			this.readMode = false

			// Don't steal the focus from title if a new page
			if (this.loading('newPage')) {
				this.done('newPage')
				return
			}

			if (this.isTextEdit) {
				this.$nextTick(() => {
					this.focusEditor()
				})
			}
		},

		initEditMode() {
			// Open in edit mode when pageMode is set
			if (!!this.currentCollective.pageMode
				// for template pages
				|| this.isTemplatePage
				// for new pages
				|| this.loading('newPage')
				// or when page is empty
				|| !this.pageContent?.trim()) {
				this.setTextEdit()
			}
		},

		startEdit() {
			this.scrollTop = document.getElementById('text')?.scrollTop || 0
			this.$nextTick(() => {
				document.getElementById('editor')?.scrollTo(0, this.scrollTop)
			})
		},

		stopEdit() {
			this.scrollTop = document.getElementById('editor')?.scrollTop || 0

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

			this.$nextTick(() => {
				document.getElementById('text')?.scrollTo(0, this.scrollTop)
			})
		},

		toggleOutlineFromEditor(visible) {
			if (visible === true) {
				this.show('outline')
			} else if (visible === false) {
				this.hide('outline')
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
