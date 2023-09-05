<template>
	<div>
		<WidgetHeading v-if="isLandingPage"
			:title="t('collectives', 'Landing page')"
			class="text-container-heading" />
		<div v-show="showReader"
			id="text-container"
			:key="'text-' + currentPage.id"
			:aria-label="t('collectives', 'Page content')">
			<Reader :key="`reader-${currentPage.id}`"
				:current-page="currentPage"
				:page-content="pageContent" />
		</div>
		<div v-if="currentCollectiveCanEdit"
			v-show="showEditor"
			ref="editor" />
	</div>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import Reader from './Reader.vue'
import WidgetHeading from './LandingPageWidgets/WidgetHeading.vue'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import {
	GET_VERSIONS,
	TOUCH_PAGE,
} from '../../store/actions.js'
import pageContentMixin from '../../mixins/pageContentMixin.js'

export default {
	name: 'TextEditor',

	components: {
		Reader,
		WidgetHeading,
	},

	mixins: [
		pageContentMixin,
	],

	data() {
		return {
			editor: null,
			davContent: '',
			editorContent: null,
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
			'hasVersionsLoaded',
			'isLandingPage',
			'isTemplatePage',
			'isTextEdit',
			'isPublic',
			'loading',
		]),

		pageContent() {
			return this.editorContent || this.davContent
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
		this.initEditMode()
		this.getPageContent()
		this.setupEditor()

		this.textEditWatcher = this.$watch('isTextEdit', (val) => {
			if (val === true) {
				this.startEdit()
			} else {
				this.stopEdit()
			}
		})
		subscribe('collectives:attachment:restore', this.addImage)
	},

	beforeDestroy() {
		unsubscribe('collectives:attachment:restore', this.addImage)
		this.textEditWatcher()
		this.editor?.destroy()
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

		async setupEditor() {
			this.editor = await window.OCA.Text.createEditor({
				el: this.$refs.editor,
				fileId: this.currentPage.id,
				readOnly: false,
				onUpdate: ({ markdown }) => {
					this.editorContent = markdown
				},
			})
			this.readyEditor()
		},

		focusEditor() {
			this.editor?.focus()
		},

		addImage(name) {
			// inspired by the fixedEncodeURIComponent function suggested in
			// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURIComponent
			const src = '.attachments.' + this.currentPage.id + '/' + name
			// simply get rid of brackets to make sure link text is valid
			// as it does not need to be unique and matching the real file name
			const alt = name.replaceAll(/[[\]]/g, '')

			// TODO: insert image
			//this.legacyWrapper()?.$editor?.commands.setImage({ src, alt })
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
			// Open in edit mode when pageMode is set, for template pages and for new pages
			if (!!this.currentCollective.pageMode || this.isTemplatePage || this.loading('newPage')) {
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
			if (!this.pageContent.trim()) {
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
				//this.legacySyncService()?.save()
			}

			this.$nextTick(() => {
				document.getElementById('text')?.scrollTo(0, this.scrollTop)
			})
		},

		async getPageContent() {
			this.davContent = await this.fetchPageContent(this.currentPageDavUrl)
			this.done('pageContent')
		},
	},
}
</script>

<style lang="scss" scoped>
.text-container-heading {
	max-width: 670px;
	margin: auto;
	padding-left: 14px;
}

#text-container {
	display: block;
	width: 100%;
	max-width: 100%;
	left: 0;
	margin: 0 auto;
	background-color: var(--color-main-background);
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
