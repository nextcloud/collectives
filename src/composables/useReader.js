/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import PageInfoBar from '../components/Page/PageInfoBar.vue'
import { editorApiReaderFileId } from '../constants.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useSearchStore } from '../stores/search.js'
import { useSearch } from './useSearch.js'

/**
 * Composable for setting up the editor and reader.
 *
 * @param {object} content ref to the Markdown content.
 */
export function useReader(content) {
	const reader = ref(null)
	const readerEl = ref(null)
	const pageInfoBarPage = ref(null)
	const rootStore = useRootStore()
	const collectivesStore = useCollectivesStore()
	const searchStore = useSearchStore()
	const pagesStore = usePagesStore()

	const defaultReaderPage = computed(() => {
		return pageInfoBarPage.value || pagesStore.currentPage
	})

	const showCurrentPageOutline = computed(() => {
		return pagesStore.hasOutline(pagesStore.currentPage.id)
	})

	const attachmentCount = computed(() => {
		return pagesStore.attachments.length
	})

	const backlinkCount = computed(() => {
		return pagesStore.backlinks(defaultReaderPage.id).length
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

	watch(defaultReaderPage.timestamp, (value) => {
		if (value) {
			updateReadonlyBarProps()
		}
	})

	watch(attachmentCount, () => {
		updateReadonlyBarProps()
	})

	watch(backlinkCount, () => {
		updateReadonlyBarProps()
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
					canEdit: collectivesStore.currentCollectiveCanEdit,
					attachmentCount,
					backlinkCount,
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
			onAttachmentsUpdated({ attachmentSrcs }) {
				pagesStore.setReaderEmbeddedAttachmentSrcs(attachmentSrcs)
			},
		})

		if (!rootStore.loading('pageContent')) {
			reader.value.setContent(content.value.trim())
			nextTick(scrollToLocationHash)
		}
	}

	/**
	 * Update properties of the PageInfoBar in the reader
	 */
	function updateReadonlyBarProps() {
		// Update currentPage in PageInfoBar component through Text editorAPI
		if (reader.value?.updateReadonlyBarProps) {
			reader.value?.updateReadonlyBarProps({
				currentPage: defaultReaderPage.value,
				canEdit: collectivesStore.currentCollectiveCanEdit,
				attachmentCount: attachmentCount.value,
				backlinkCount: backlinkCount.value,
			})
		}
	}

	return {
		pageInfoBarPage,
		reader,
		readerEl,
		setupReader,
	}
}
