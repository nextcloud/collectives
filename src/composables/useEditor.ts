/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'
import type { TextEditorInstance } from '../types.ts'

import axios, { isAxiosError } from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import debounce from 'debounce'
import { computed, defineCustomElement, markRaw, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { getReferenceWithPicker } from '@nextcloud/vue/components/NcRichText'
import PageIcon from '../components/Icon/PageIcon.vue'
import { useCirclesStore } from '../stores/circles.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useSearchStore } from '../stores/search.js'
import { useSearch } from './useSearch.ts'

/**
 * Composable for setting up the editor and reader.
 *
 * @param davContent markdown content fetched via dav.
 */
export function useEditor(davContent: Ref<string>) {
	const editor = ref<TextEditorInstance | null>(null)
	const editorEl = ref<HTMLElement | null>(null)
	const editorContent = ref<string | null>(null)
	let editorPromise: Promise<TextEditorInstance> | null = null
	let instance: TextEditorInstance | null = null
	let disposed = false
	let cancelInitialization: (() => void) | null = null
	let pendingActions = 0
	let actionQueue = Promise.resolve<unknown>(undefined)
	const rootStore = useRootStore()
	const circlesStore = useCirclesStore()
	const searchStore = useSearchStore()
	const collectivesStore = useCollectivesStore()
	const pagesStore = usePagesStore()

	const pageId = pagesStore.currentPageId
	const davUrl = pagesStore.currentPageDavUrl
	let latestMarkdown: string | null = null
	const isCurrentPage = () => !disposed && pagesStore.currentPageId === pageId
	const pageContent = computed(() => editorContent.value === null ? davContent.value : editorContent.value.trim())
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

	const updateEditorContent = (markdown: string) => {
		if (isCurrentPage()) {
			editorContent.value = markdown
		}
	}
	const updateEditorContentDebounced = debounce(updateEditorContent, 200)

	useSearch(editor)

	const contentLoaded = computed(() => {
		// Either `pageContent` is filled from editor or we finished fetching it from DAV
		return !!pageContent.value || !rootStore.loading('pageContent')
	})

	const unregister = pagesStore.registerEditorAction((action: (editor: TextEditorInstance, save: () => Promise<true>) => Promise<unknown>) => {
		const task = actionQueue.then(async () => {
			if (!isCurrentPage() || !collectivesStore.currentCollectiveCanEdit) {
				throw new Error('The page is no longer available for editing.')
			}
			pagesStore.setTextEdit()
			const ed = await setupEditor()
			if (!ed || !isCurrentPage() || !collectivesStore.currentCollectiveCanEdit) {
				throw new Error('The page changed before editing was ready.')
			}
			pendingActions++
			try {
				return await action(ed, saveEditor)
			} finally {
				updateEditorContentDebounced.flush()
				pendingActions--
				if (disposed && pendingActions === 0) {
					instance?.destroy()
					instance = null
				}
			}
		})
		actionQueue = task.catch(() => {})
		return task
	})

	onBeforeUnmount(() => {
		disposed = true
		unregister()
		cancelInitialization?.()
		updateEditorContentDebounced.clear()
		if (pendingActions === 0) {
			instance?.destroy()
			instance = null
		}
	})

	watch(showCurrentPageOutline, (value) => {
		editor.value?.setShowOutline(value)
	})

	/**
	 * Create the editor instance and mount it to refs.editor
	 */
	async function setupEditor() {
		if (!isCurrentPage()) {
			throw new Error('The page is no longer mounted.')
		}
		const page = pagesStore.currentPage
		if (!collectivesStore.currentCollectiveCanEdit) {
			editor.value = null
			return
		}

		// Reading a page must not open a writable Text session or lock its file.
		if (!pagesStore.isTextEdit) {
			rootStore.done('editor')
			return
		}

		// Switching back from preview reuses the existing editor.
		if (editorPromise) {
			return editorPromise
		}

		rootStore.load('editor')

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

		let valid = true
		let resolveLoaded!: () => void
		const loaded = new Promise<void>((resolve) => {
			resolveLoaded = resolve
		})
		let timeout: ReturnType<typeof setTimeout>
		const cancelled = new Promise<never>((_resolve, reject) => {
			cancelInitialization = () => reject(new Error('Editor initialization was cancelled.'))
			timeout = setTimeout(() => reject(new Error('Editor initialization timed out.')), 30_000)
		})

		// createEditor resolves before the document/session is ready. Wait for both.
		editorPromise = (async () => {
			try {
				const created = Promise.resolve().then(() => {
					if (!valid || !isCurrentPage()) {
						throw new Error('The page changed before editor creation.')
					}
					return window.OCA.Text.createEditor({
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
								return getReferenceWithPicker('collectives-ref-pages', false)
							},
						},
						openLinkHandler: window.OCA.Collectives.openLink,
						onCreate: ({ markdown }: { markdown: string }) => {
							if (valid && (isCurrentPage() || pendingActions > 0)) {
								latestMarkdown = markdown
								if (isCurrentPage()) {
									updateEditorContentDebounced(markdown)
								}
							}
						},
						onLoaded: () => resolveLoaded(),
						onUpdate: ({ markdown }: { markdown: string }) => {
							if (valid && (isCurrentPage() || pendingActions > 0)) {
								latestMarkdown = markdown
								if (isCurrentPage()) {
									updateEditorContentDebounced(markdown)
								}
							}
						},
						onAttachmentsUpdated({ attachmentSrcs }: { attachmentSrcs: string[] }) {
							if (valid && isCurrentPage()) {
								pagesStore.setEditorEmbeddedAttachmentSrcs(attachmentSrcs)
							}
						},
						onMentionSearch(query: string) {
							const users = circlesStore.currentCircleUserMembersSorted
							const lowerQuery = query.toLowerCase().trim()
							return Object.fromEntries(Object.entries(users).filter(([key, value]) => key.toLowerCase().includes(lowerQuery) || value.toLowerCase().includes(lowerQuery)))
						},
						onOutlineToggle: (value: boolean) => {
							if (valid && isCurrentPage()) {
								pagesStore.setOutlineForCurrentPage(value)
							}
						},
					})
				})
				const ready = created.then((ed: TextEditorInstance) => {
					if (!valid || !isCurrentPage()) {
						ed.destroy()
						throw new Error('The page changed during editor initialization.')
					}
					instance = markRaw(ed)
					return ed
				})
				const [ed] = await Promise.race([Promise.all([ready, loaded]), cancelled])
				if (!isCurrentPage()) {
					throw new Error('The page changed during editor initialization.')
				}
				editor.value = markRaw(ed)
				ed.setSearchQuery(searchStore.searchQuery, searchStore.matchAll)
				ed.setShowOutline(showCurrentPageOutline.value)
				return ed
			} catch (error) {
				valid = false
				instance?.destroy()
				instance = null
				editor.value = null
				editorContent.value = null
				latestMarkdown = null
				editorPromise = null
				updateEditorContentDebounced.clear()
				throw error
			} finally {
				clearTimeout(timeout!)
				cancelInitialization = null
				if (isCurrentPage()) {
					rootStore.done('editor')
					if (editor.value) {
						nextTick(() => {
							if (isCurrentPage()) {
								scrollToLocationHash()
							}
						})
					}
				}
			}
		})()
		return editorPromise
	}

	/** Confirm the persisted Markdown, including Text versions whose save returns void. */
	async function saveEditor(): Promise<true> {
		if (!instance || latestMarkdown === null) {
			throw new Error('The editor is not ready to save.')
		}
		// Older Text versions may resolve save() before a pending sync is saved.
		// Retry saving as well as reading, rather than confirming stale Markdown.
		for (let attempt = 0; attempt < 5; attempt++) {
			if (!instance || await instance.save() === false) {
				throw new Error('The editor could not save the document.')
			}
			updateEditorContentDebounced.flush()
			const expected = latestMarkdown.trim()
			try {
				const response = await axios.get<string>(davUrl, {
					params: { timestamp: Date.now() },
					responseType: 'text',
					transformResponse: [(data: string) => data],
				})
				if (response.data.trim() === expected) {
					return true
				}
			} catch (error) {
				// A concurrent save can briefly hold a DAV read lock.
				if (!isAxiosError(error) || error.response?.status !== 423) {
					throw error
				}
			}
			await new Promise((resolve) => setTimeout(resolve, 250))
		}
		throw new Error('The stored Markdown does not match the editor.')
	}

	return {
		contentLoaded,
		davContent,
		editor,
		editorEl,
		editorContent,
		pageContent,
		setupEditor,
		saveEditor,
	}
}
