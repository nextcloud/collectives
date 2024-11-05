/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import debounce from 'debounce'
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useSearchStore } from '../stores/search.js'
import linkHandlerMixin from '../mixins/linkHandlerMixin.js'
import PageInfoBar from '../components/Page/PageInfoBar.vue'
import { editorApiReaderFileId } from '../constants.js'
import { subscribe } from '@nextcloud/event-bus'

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
		...mapState(useRootStore, [
			'editorApiFlags',
			'loading',
			'shareTokenParam',
			'showing',
		]),
		...mapState(useSearchStore, ['searchQuery', 'matchAll']),
		...mapState(useCollectivesStore, ['currentCollectiveCanEdit']),
		...mapState(usePagesStore, ['currentPage', 'pageFilePath']),

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

	created() {
		subscribe('collectives:next-search', () => {
			this.editor?.searchNext()
			this.reader?.searchNext()
		})

		subscribe('collectives:previous-search', () => {
			this.editor?.searchPrevious()
			this.reader?.searchPrevious()
		})
	},

	watch: {
		'showOutline'(value) {
			this.editor?.setShowOutline(value)
			this.reader?.setShowOutline(value)
		},
		'searchQuery'(value) {
			// TODO: remove check once we only support Nextcloud 30+
			this.editor?.setSearchQuery && this.editor.setSearchQuery(value)
			this.reader?.setSearchQuery && this.reader.setSearchQuery(value)
		},
		'matchAll'(value) {
			// TODO: remove check once we only support Nextcloud 30+
			this.editor?.setSearchQuery && this.editor.setSearchQuery(this.searchQuery, value)
			this.reader?.setSearchQuery && this.reader.setSearchQuery(this.searchQuery, value)
		},
	},

	beforeDestroy() {
		this.editor?.destroy()
		this.reader?.destroy()
	},

	methods: {
		...mapActions(useRootStore, ['done', 'hide', 'show']),
		...mapActions(useSearchStore, ['showSearchDialog', 'setSearchResults']),

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
				// Required for link handling on Nextcloud 27+28
				onLinkClick: (_event, attrs) => {
					this.followLink(_event, attrs)
				},
				onOutlineToggle: (visible) => {
					this.toggleOutlineFromEditor(visible)
				},
				onLoaded: () => {
					// TODO: remove check once we only support Nextcloud 30+
					this.reader.setSearchQuery && this.reader.setSearchQuery(this.searchQuery, this.matchAll)
					if (document.location.hash) {
						// scroll to the corresponding header if the page was loaded with a hash
						const element = document.querySelector(`[href="${document.location.hash}"]`)
						element?.click()
					}
				},
				onSearch: (results) => {
					this.setSearchResults(results)
					this.showSearchDialog(true)
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
					onCreate: ({ markdown }) => {
						this.updateEditorContentDebounced(markdown)
					},
					onLoaded: () => {
						// TODO: remove check once we only support Nextcloud 30+
						this.editor.setSearchQuery && this.editor.setSearchQuery(this.searchQuery, this.matchAll)
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

		async save() {
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
