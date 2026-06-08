/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'
import type { PageInfo, TextEditorInstance } from '../types.ts'

import { computed, defineCustomElement, markRaw, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import PageInfoBar from '../components/Page/PageInfoBar.vue'
import { editorApiReaderFileId } from '../constants.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useSearchStore } from '../stores/search.js'
import { useSearch } from './useSearch.ts'

/**
 * Vue custom element interface for <page-info-bar>.
 * Props are exposed as JavaScript properties on the DOM element.
 */
const PageInfoBarCE = defineCustomElement(PageInfoBar, { shadowRoot: false })
type PageInfoBarElement = InstanceType<typeof PageInfoBarCE>

/**
 * Composable for setting up the editor and reader.
 *
 * @param content ref to the Markdown content.
 */
export function useReader(content: Ref<string>) {
	const reader = ref<TextEditorInstance | null>(null)
	const readerEl = ref<HTMLElement | null>(null)
	const pageInfoBarPage = ref<PageInfo | null>(null)
	const rootStore = useRootStore()
	const collectivesStore = useCollectivesStore()
	const searchStore = useSearchStore()
	const pagesStore = usePagesStore()

	const defaultReaderPage = computed(() => {
		return pageInfoBarPage.value || pagesStore.currentPage
	})

	const showCurrentPageOutline = computed(() => {
		return pagesStore.hasOutline(pagesStore.currentPageId)
	})

	const attachmentCount = computed(() => {
		return pagesStore.attachments.length
	})

	const backlinkCount = computed(() => {
		return pagesStore.backlinks(defaultReaderPage.value?.id).length
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

	watch(() => defaultReaderPage.value?.timestamp, (value) => {
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
	 * @param page handed to the text app editor.
	 */
	async function setupReader(page: PageInfo) {
		const readerPage = pageInfoBarPage.value || page
		const fileId = rootStore.editorApiFlags.includes(editorApiReaderFileId)
			? readerPage.id
			: null

		// Define PageInfoBar as custom web component
		if (!window.customElements.get('page-info-bar')) {
			customElements.define('page-info-bar', PageInfoBarCE)
		}

		let resolveLoaded: () => void
		const loadedPromise = new Promise<void>((resolve) => {
			resolveLoaded = resolve
		})

		const readerInstance = await window.OCA.Text.createEditor({
			el: readerEl.value,
			fileId,
			useSession: false,
			content: content.value.trim(),
			filePath: `/${pagesStore.pageFilePath(page)}`,
			readOnly: true,
			shareToken: rootStore.shareTokenParam || null,
			readonlyBar: {
				component: 'page-info-bar',
				props: {},
			},
			noLazyImages: rootStore.printView,
			openLinkHandler: window.OCA.Collectives.openLink,
			onOutlineToggle: pagesStore.setOutlineForCurrentPage,
			onLoaded: () => {
				nextTick(updateReadonlyBarProps)
				reader.value?.setSearchQuery(searchStore.searchQuery, searchStore.matchAll)
				reader.value?.setShowOutline(showCurrentPageOutline.value)
				resolveLoaded()
			},
			onSearch: (results: unknown) => {
				searchStore.setSearchResults(results)
				searchStore.showSearchDialog(true)
			},
			onAttachmentsUpdated({ attachmentSrcs }: { attachmentSrcs: string[] }) {
				pagesStore.setReaderEmbeddedAttachmentSrcs(attachmentSrcs)
			},
		})

		// Use markRaw to prevent Vue 3 from proxying the Vue 2 editor instance
		reader.value = markRaw(readerInstance)
		await loadedPromise

		if (!rootStore.loading('pageContent')) {
			reader.value?.setContent(content.value.trim())
			nextTick(scrollToLocationHash)
		}
	}

	/**
	 * Update properties of the PageInfoBar in the reader
	 */
	function updateReadonlyBarProps() {
		const el = readerEl.value?.querySelector<PageInfoBarElement>('page-info-bar')
		if (!el) {
			return
		}
		el.currentPage = defaultReaderPage.value
		el.canEdit = collectivesStore.currentCollectiveCanEdit
		el.attachmentCount = attachmentCount.value
		el.backlinkCount = backlinkCount.value
	}

	return {
		pageInfoBarPage,
		reader,
		readerEl,
		setupReader,
	}
}
