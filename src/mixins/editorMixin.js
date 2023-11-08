import debounce from 'debounce'
import { mapGetters, mapMutations } from 'vuex'
import linkHandlerMixin from '../mixins/linkHandlerMixin.js'
import PageInfoBar from '../components/Page/PageInfoBar.vue'

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
		}
	},

	computed: {
		...mapGetters([
			'currentCollectiveCanEdit',
			'currentPage',
			'currentPageFilePath',
			'loading',
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
			this.reader = await window.OCA.Text.createEditor({
				el: this.$refs.reader,
				content: this.pageContent,
				filePath: `/${this.currentPageFilePath}`,
				readOnly: true,
				shareToken: this.shareTokenParam || null,
				readonlyBar: {
					component: PageInfoBar,
					props: {
						currentPage: this.pageInfoBarPage || this.currentPage,
					},
				},
				onLinkClick: (_event, attrs) => {
					this.followLink(_event, attrs)
				},
				onOutlineToggle: (visible) => {
					this.toggleOutlineFromEditor(visible)
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
					fileId: this.currentPage.id,
					filePath: `/${this.currentPageFilePath}`,
					readOnly: false,
					shareToken: this.shareTokenParam || null,
					autofocus: false,
					onLoaded: () => {
						this.done('editor')
					},
					onUpdate: ({ markdown }) => {
						this.updateEditorContent(markdown)
					},
					onOutlineToggle: (visible) => {
						this.toggleOutlineFromEditor(visible)
					},
				})
				: null
		},

		updateEditorContent: debounce(function(markdown) {
			this.editorContent = markdown
			this.reader?.setContent(this.editorContent)
		}, 200),

		focusEditor() {
			this.editor?.focus()
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
