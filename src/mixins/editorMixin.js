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
import PageInfoBar from '../components/Page/PageInfoBar.vue'
import { editorApiReaderFileId } from '../constants.js'
import { subscribe } from '@nextcloud/event-bus'

export default {
	data() {
		return {
			reader: null,
			editor: null,
			davContent: '',
			editorContent: null,
			pageInfoBarPage: null,
			updateEditorContentDebounced: debounce(this.updateEditorContent, 200),
			updateCounter: 0,
		}
	},

	computed: {
		...mapState(useRootStore, [
			'editorApiFlags',
			'loading',
			'shareTokenParam',
		]),
		...mapState(useSearchStore, ['searchQuery', 'matchAll']),
		...mapState(useCollectivesStore, ['currentCollectiveCanEdit']),
		...mapState(usePagesStore, ['currentPage', 'pageFilePath', 'hasOutline']),

		pageContent() {
			return this.editorContent?.trim() || this.davContent
		},

		showCurrentPageOutline() {
			return this.hasOutline(this.currentPage.id)
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
		'showCurrentPageOutline'(value) {
			this.editor?.setShowOutline(value)
			this.reader?.setShowOutline(value)
		},
		'searchQuery'(value) {
			this.editor?.setSearchQuery(value)
			this.reader?.setSearchQuery(value)
		},
		'matchAll'(value) {
			this.editor?.setSearchQuery(this.searchQuery, value)
			this.reader?.setSearchQuery(this.searchQuery, value)
		},
	},

	beforeDestroy() {
		this.editor?.destroy()
		this.reader?.destroy()
	},

	methods: {
		...mapActions(useRootStore, ['done']),
		...mapActions(usePagesStore, ['showOutline', 'hideOutline']),
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
				onOutlineToggle: (visible) => {
					this.toggleOutlineFromEditor(visible)
				},
				onLoaded: () => {
					this.reader.setSearchQuery(this.searchQuery, this.matchAll)
					this.reader.setShowOutline(this.showCurrentPageOutline)
				},
				onSearch: (results) => {
					this.setSearchResults(results)
					this.showSearchDialog(true)
				},
			})

			if (!this.loading('pageContent')) {
				this.reader.setContent(this.pageContent)
				this.$nextTick(() => {
					this.scrollToLocationHash()
				})
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
						this.editor.setSearchQuery(this.searchQuery, this.matchAll)
						this.editor.setShowOutline(this.showCurrentPageOutline)
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

			if (this.updateCounter === 1) {
				// Scroll to location hash after first setContent (triggered by initial content)
				this.$nextTick(() => {
					setTimeout(() => {
						this.scrollToLocationHash()
					}, 50)
				})
			}
			this.updateCounter++
		},

		focusEditor() {
			this.editor?.focus()
		},

		async save() {
			return this.editor.save()
		},

		toggleOutlineFromEditor(visible) {
			if (visible === true) {
				this.showOutline(this.currentPage.id)
			} else if (visible === false) {
				this.hideOutline(this.currentPage.id)
			}
		},

		scrollToLocationHash() {
			if (document.location.hash) {
				// scroll to the corresponding header if the page was loaded with a hash both in reader and viewer
				const readerEl = document.querySelector('[data-collectives-el="reader"]')
				const editorEl = document.querySelector('[data-collectives-el="editor"]')

				for (const el of [readerEl, editorEl]) {
					el?.querySelector(document.location.hash)?.scrollIntoView({ behavior: 'instant' })
				}
			}
		},
	},
}
