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
			readMode: true,
		}
	},

	computed: {
		...mapGetters([
			'currentCollectiveCanEdit',
			'currentPage',
			'currentPageFilePath',
			'isFullWidthView',
			'shareTokenParam',
			'showing',
		]),

		pageContent() {
			return this.editorContent?.trim() || this.davContent
		},

		showOutline() {
			return this.showing('outline')
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

		/**
		 * Set readMode to false
		 */
		readyEditor() {
			this.done('editor')
			this.readMode = false
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
