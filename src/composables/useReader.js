/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import PageInfoBar from '../components/Page/PageInfoBar.vue'
import { editorApiReaderFileId } from '../constants.js'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useSearchStore } from '../stores/search.js'
import { useSearch } from './useSearch.js'

/**
 * Composable for setting up the editor and reader.
 *
 * @param {object} content ref to the markdown content.
 */
export function useReader(content) {
	const reader = ref(null)
	const readerEl = ref(null)
	const pageInfoBarPage = ref(null)
	const rootStore = useRootStore()
	const searchStore = useSearchStore()
	const pagesStore = usePagesStore()

	const showCurrentPageOutline = computed(() => {
		return pagesStore.hasOutline(pagesStore.currentPage.id)
	})

	const scrollToLocationHash = () => {
		if (document.location.hash) {
			// scroll to the corresponding header if the page was loaded with a hash both in reader and viewer
			[
				document.querySelector('[data-collectives-el="reader"]'),
				document.querySelector('[data-collectives-el="editor"]'),
			].forEach((el) => {
				el?.querySelector(document.location.hash)?.scrollIntoView({ behavior: 'instant' })
			})
		}
	}

	if (content) {
		watch(content, (content) => {
			reader.value?.setContent(content)
		})
	}

	useSearch(reader)

	onBeforeUnmount(() => {
		reader.value?.destroy()
	})

	watch(showCurrentPageOutline, (value) => {
		reader.value?.setShowOutline(value)
	})

	/**
	 * Create the reader instance and mount it to readerEl
	 *
	 * @param {object} page handed to the text app editor.
	 */
	async function setupReader(page) {
		const readerPage = pageInfoBarPage.value || page
		const fileId = rootStore.editorApiFlags.includes(editorApiReaderFileId)
			? readerPage.id
			: null
		reader.value = await window.OCA.Text.createEditor({
			el: readerEl.value,
			fileId,
			useSession: false,
			content: content.value.trim(),
			filePath: `/${pagesStore.pageFilePath(page)}`,
			readOnly: true,
			shareToken: rootStore.shareTokenParam || null,
			readonlyBar: {
				component: PageInfoBar,
				props: {
					currentPage: readerPage,
					attachmentCount: pagesStore.attachments.length,
					backlinkCount: pagesStore.backlinks(readerPage.id).length,
				},
			},
			onOutlineToggle: pagesStore.setOutlineForCurrentPage,
			onLoaded: () => {
				reader.value.setSearchQuery(searchStore.searchQuery, searchStore.matchAll)
				reader.value.setShowOutline(showCurrentPageOutline.value)
			},
			onSearch: (results) => {
				searchStore.setSearchResults(results)
				searchStore.showSearchDialog(true)
			},
		})

		if (!rootStore.loading('pageContent')) {
			reader.value.setContent(content.value.trim())
			nextTick(scrollToLocationHash)
		}
	}

	return {
		pageInfoBarPage,
		reader,
		readerEl,
		setupReader,
	}
}
