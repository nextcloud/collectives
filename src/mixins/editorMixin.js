import debounce from 'debounce'
import { mapGetters, mapMutations } from 'vuex'
import linkHandlerMixin from '../mixins/linkHandlerMixin.js'
import PageInfoBar from '../components/Page/PageInfoBar.vue'
import { editorApiReaderFileId } from '../constants.js'

export default {
	mixins: [
		linkHandlerMixin,
	],

	data() {
		return {
			reader: null,
			editor: null,
			davContent: '',
			editorContent: null,
			pageInfoBarPage: null,
			updateEditorContentDebounced: debounce(this.updateEditorContent, 200),
		}
	},

	computed: {
		...mapGetters([
			'currentCollectiveCanEdit',
			'currentPage',
			'editorApiFlags',
			'loading',
			'pageFilePath',
			'shareTokenParam',
			'showing',
		]),

		pageContent() {
			return this.editorContent?.trim() || this.davContent
		},

		showOutline() {
			return this.showing('outline')
		},

		contentLoaded() {
			// Either `pageContent` is filled from editor or we finished fetching it from DAV
			return !!this.pageContent || !this.loading('pageContent')
		},

		/**
		 * Use `this.page` if available (e.g. in `PagePrint`) and fallback to `currentPage`
		 */
		pageToUse() {
			return this.page || this.currentPage
		},
	},

	watch: {
		'showOutline'(value) {
			this.editor?.setShowOutline(value)
			this.reader?.setShowOutline(value)
		},
	},

	beforeDestroy() {
		this.editor?.destroy()
		this.reader?.destroy()
	},

	methods: {
		...mapMutations([
			'hide',
			'show',
		]),

		async setupReader() {
			const fileId = this.editorApiFlags.includes(editorApiReaderFileId)
				? this.pageToUse.id
				: null
			this.reader = await window.OCA.Text.createEditor({
				el: this.$refs.reader,
				fileId,
				useSession: false,
				content: this.pageContent,
				filePath: `/${this.pageFilePath(this.pageToUse)}`,
				readOnly: true,
				shareToken: this.shareTokenParam || null,
				readonlyBar: {
					component: PageInfoBar,
					props: {
						currentPage: this.pageInfoBarPage || this.pageToUse,
					},
				},
				onLinkClick: (_event, attrs) => {
					this.followLink(_event, attrs)
				},
				onOutlineToggle: (visible) => {
					this.toggleOutlineFromEditor(visible)
				},
				onLoaded: () => {
					if (document.location.hash) {
						// scroll to the corresponding header if the page was loaded with a hash
						const element = document.querySelector(`[href="${document.location.hash}"]`)
						element?.click()
					}
				},
			})

			if (!this.loading('pageContent')) {
				this.reader.setContent(this.pageContent)
			}
		},

		async setupEditor() {
			this.editor = this.currentCollectiveCanEdit
				? await window.OCA.Text.createEditor({
					el: this.$refs.editor,
					fileId: this.pageToUse.id,
					filePath: `/${this.pageFilePath(this.pageToUse)}`,
					readOnly: false,
					shareToken: this.shareTokenParam || null,
					autofocus: false,
					onLoaded: () => {
						this.done('editor')
					},
					onUpdate: ({ markdown }) => {
						this.updateEditorContentDebounced(markdown)
					},
					onOutlineToggle: (visible) => {
						this.toggleOutlineFromEditor(visible)
					},
				})
				: null
		},

		updateEditorContent(markdown) {
			this.editorContent = markdown
			this.reader?.setContent(this.editorContent)
		},

		focusEditor() {
			this.editor?.focus()
		},

		save() {
			return this.editor.save()
		},

		toggleOutlineFromEditor(visible) {
			if (visible === true) {
				this.show('outline')
			} else if (visible === false) {
				this.hide('outline')
			}
		},
	},
}
