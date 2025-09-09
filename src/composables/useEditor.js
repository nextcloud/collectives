/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import debounce from 'debounce'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useSearchStore } from '../stores/search.js'
import PageInfoBar from '../components/Page/PageInfoBar.vue'
import { editorApiReaderFileId } from '../constants.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { computed, onMounted, onBeforeUnmount, ref, watch, nextTick } from 'vue'

/**
 * Composable for setting up the editor and reader.
 * @param {object} page page to use in place of currentPage - for example in print view.
 */
export function useEditor(page) {
	const reader = ref(null)
	const readerEl = ref(null)
	const editor = ref(null)
	const editorEl = ref(null)
	const davContent = ref('')
	const editorContent = ref(null)
	const pageInfoBarPage = ref(null)
	const updateCounter = ref(0)
	const rootStore = useRootStore()
	const searchStore = useSearchStore()
	const collectivesStore = useCollectivesStore()
	const pagesStore = usePagesStore()

	const pageContent = computed(() => editorContent.value?.trim() || davContent.value)
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

	const updateEditorContent = (markdown) => {
		editorContent.value = markdown
		reader.value?.setContent(editorContent.value)
		if (updateCounter.value === 1) {
			// Scroll to location hash after first setContent (triggered by initial content)
			nextTick(() => {
				setTimeout(scrollToLocationHash, 50)
			})
		}
		updateCounter.value++
	}
	const updateEditorContentDebounced = debounce(updateEditorContent, 200)

	const contentLoaded = computed(() => {
		// Either `pageContent` is filled from editor or we finished fetching it from DAV
		return !!pageContent.value || !rootStore.loading('pageContent')
	})

	/**
	 * Use `page` if available (e.g. in `PagePrint`) and fallback to `currentPage`
	 */
	const pageToUse = computed(() => {
		return page?.value || pagesStore.currentPage
	})

	const searchNext = () => {
		editor.value?.searchNext()
		reader.value?.searchNext()
	}

	const searchPrevious = () => {
		editor.value?.searchPrevious()
		reader.value?.searchPrevious()
	}

	onMounted(() => {
		subscribe('collectives:next-search', searchNext)
		subscribe('collectives:previous-search', searchPrevious)
	})

	onBeforeUnmount(() => {
		unsubscribe('collectives:next-search', searchNext)
		unsubscribe('collectives:previous-search', searchPrevious)
		editor.value?.destroy()
		reader.value?.destroy()
	})

	watch(showCurrentPageOutline, (value) => {
		editor.value?.setShowOutline(value)
		reader.value?.setShowOutline(value)
	})

	watch(
		() => searchStore.searchQuery,
		(value) => {
			editor.value?.setSearchQuery(value)
			reader.value?.setSearchQuery(value)
		},
	)

	watch(
		() => searchStore.matchAll,
		(value) => {
			editor.value?.setSearchQuery(searchStore.searchQuery, value)
			reader.value?.setSearchQuery(searchStore.searchQuery, value)
		},
	)

	/**
	 * Create the reader instance and mount it to readerEl
	 */
	async function setupReader() {
		const fileId = rootStore.editorApiFlags.includes(editorApiReaderFileId)
			? pageToUse.value.id
			: null
		reader.value = await window.OCA.Text.createEditor({
			el: readerEl.value,
			fileId,
			useSession: false,
			content: pageContent.value,
			filePath: `/${pagesStore.pageFilePath(pageToUse.value)}`,
			readOnly: true,
			shareToken: rootStore.shareTokenParam || null,
			readonlyBar: {
				component: PageInfoBar,
				props: {
					currentPage: pageInfoBarPage.value || pageToUse.value,
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
			reader.value.setContent(pageContent.value)
			nextTick(scrollToLocationHash)
		}
	}

	/**
	 * Create the editor instance and mount it to refs.editor
	 */
	async function setupEditor() {
		editor.value = collectivesStore.currentCollectiveCanEdit
			? await window.OCA.Text.createEditor({
				el: editorEl.value,
				fileId: pageToUse.value.id,
				filePath: `/${pagesStore.pageFilePath(pageToUse.value)}`,
				readOnly: false,
				shareToken: rootStore.shareTokenParam || null,
				autofocus: false,
				onCreate: ({ markdown }) => {
					updateEditorContentDebounced(markdown)
				},
				onLoaded: () => {
					editor.value.setSearchQuery(searchStore.searchQuery, searchStore.matchAll)
					editor.value.setShowOutline(showCurrentPageOutline.value)
					rootStore.done('editor')
				},
				onUpdate: ({ markdown }) => {
					updateEditorContentDebounced(markdown)
				},
				onOutlineToggle: pagesStore.setOutlineForCurrentPage,
			})
			: null
	}

	return {
		contentLoaded,
		davContent,
		editor,
		editorEl,
		editorContent,
		pageContent,
		reader,
		readerEl,
		setupReader,
		setupEditor,
	}
}
