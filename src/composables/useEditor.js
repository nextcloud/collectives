/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import debounce from 'debounce'
import { computed, defineCustomElement, markRaw, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { getLinkWithPicker } from '@nextcloud/vue/components/NcRichText'
import PageIcon from '../components/Icon/PageIcon.vue'
import { useCirclesStore } from '../stores/circles.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useSearchStore } from '../stores/search.js'
import { useSearch } from './useSearch.js'

/**
 * Composable for setting up the editor and reader.
 *
 * @param {object} davContent markdown content fetched via dav.
 */
export function useEditor(davContent) {
	const editor = ref(null)
	const editorEl = ref(null)
	const editorContent = ref(null)
	let editorPromise = null
	const updateCounter = ref(0)
	const rootStore = useRootStore()
	const circlesStore = useCirclesStore()
	const searchStore = useSearchStore()
	const collectivesStore = useCollectivesStore()
	const pagesStore = usePagesStore()

	const pageContent = computed(() => editorContent.value?.trim() || davContent.value)
	const showCurrentPageOutline = computed(() => {
		return pagesStore.hasOutline(pagesStore.currentPageId)
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
		if (updateCounter.value === 1) {
			// Scroll to location hash after first setContent (triggered by initial content)
			nextTick(() => {
				setTimeout(scrollToLocationHash, 50)
			})
		}
		updateCounter.value++
	}
	const updateEditorContentDebounced = debounce(updateEditorContent, 200)

	useSearch(editor)

	const contentLoaded = computed(() => {
		// Either `pageContent` is filled from editor or we finished fetching it from DAV
		return !!pageContent.value || !rootStore.loading('pageContent')
	})

	onBeforeUnmount(() => {
		editorPromise?.then((ed) => ed.destroy())
	})

	watch(showCurrentPageOutline, (value) => {
		editor.value?.setShowOutline(value)
	})

	/**
	 * Create the editor instance and mount it to refs.editor
	 */
	async function setupEditor() {
		const page = pagesStore.currentPage
		if (!collectivesStore.currentCollectiveCanEdit) {
			editor.value = null
			return
		}

		// Define PageIcon as custom web component
		if (!window.customElements.get('page-icon')) {
			const PageIconCE = defineCustomElement({
				...PageIcon,
				props: {
					...PageIcon.props,
					size: {
						type: Number,
						default: 20,
					},
				},
			}, { shadowRoot: false })
			customElements.define('page-icon', PageIconCE)
		}

		editorPromise = window.OCA.Text.createEditor({
			el: editorEl.value,
			fileId: page.id,
			filePath: `/${pagesStore.pageFilePath(page)}`,
			readOnly: false,
			shareToken: rootStore.shareTokenParam || null,
			autofocus: false,
			menubarLinkCustomAction: {
				label: t('collectives', 'Link to page'),
				icon: 'page-icon',
				action: () => {
					return getLinkWithPicker('collectives-ref-pages', false)
				},
			},
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
			onAttachmentsUpdated({ attachmentSrcs }) {
				pagesStore.setEditorEmbeddedAttachmentSrcs(attachmentSrcs)
			},
			onMentionSearch(query) {
				const users = circlesStore.currentCircleUserMembersSorted
				const lowerQuery = query.toLowerCase().trim()
				return Object.fromEntries(Object.entries(users).filter(([key, value]) => key.toLowerCase().includes(lowerQuery) || value.toLowerCase().includes(lowerQuery)))
			},
			onOutlineToggle: pagesStore.setOutlineForCurrentPage,
		})

		// Use markRaw to prevent Vue 3 from proxying the Vue 2 editor instance
		editor.value = markRaw(await editorPromise)
	}

	return {
		contentLoaded,
		davContent,
		editor,
		editorEl,
		editorContent,
		pageContent,
		setupEditor,
	}
}
