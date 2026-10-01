/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type * as Vue from 'vue'

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useEditor } from '../composables/useEditor.ts'

const mocks = vi.hoisted(() => ({
	pages: { currentPage: { id: 42 }, currentPageId: 42, isTextEdit: false, hasOutline: () => false, pageFilePath: () => 'Page.md', registerEditorAction: vi.fn(), setTextEdit: vi.fn(), setEditorEmbeddedAttachmentSrcs: vi.fn(), setOutlineForCurrentPage: vi.fn() },
	collectives: { currentCollectiveCanEdit: true },
	root: { load: vi.fn(), done: vi.fn(), loading: () => false, shareTokenParam: null },
	beforeUnmount: [] as Array<() => void>,
	createEditor: vi.fn(),
	get: vi.fn(),
}))

vi.mock('vue', async (importOriginal) => ({
	...await importOriginal<typeof Vue>(),
	onBeforeUnmount: (callback: () => void) => mocks.beforeUnmount.push(callback),
}))
vi.mock('@nextcloud/axios', () => ({ default: { get: mocks.get }, isAxiosError: (error: unknown) => Boolean(error && typeof error === 'object' && 'response' in error) }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app: string, message: string) => message }))
vi.mock('@nextcloud/vue/components/NcRichText', () => ({ getReferenceWithPicker: vi.fn() }))
vi.mock('../components/Icon/PageIcon.vue', () => ({ default: {} }))
vi.mock('../composables/useSearch.ts', () => ({ useSearch: vi.fn() }))
vi.mock('../stores/pages.js', () => ({ usePagesStore: () => mocks.pages }))
vi.mock('../stores/root.js', () => ({ useRootStore: () => mocks.root }))
vi.mock('../stores/collectives.js', () => ({ useCollectivesStore: () => mocks.collectives }))
vi.mock('../stores/circles.js', () => ({ useCirclesStore: () => ({}) }))
vi.mock('../stores/search.js', () => ({ useSearchStore: () => ({}) }))

beforeEach(() => {
	vi.clearAllMocks()
	mocks.createEditor.mockImplementation(async (options) => {
		options.onLoaded()
		return makeEditor()
	})
	mocks.pages.registerEditorAction.mockReturnValue(vi.fn())
	mocks.pages.setTextEdit.mockImplementation(() => {
		mocks.pages.isTextEdit = true
	})
	mocks.pages.currentPageId = 42
	mocks.pages.currentPage = { id: 42 }
	mocks.beforeUnmount.length = 0
	mocks.pages.isTextEdit = false
	mocks.collectives.currentCollectiveCanEdit = true
	vi.stubGlobal('document', { location: { hash: '' } })
	vi.stubGlobal('window', { customElements: { get: () => true }, OCA: { Text: { createEditor: mocks.createEditor }, Collectives: { openLink: vi.fn() } } })
})

function makeEditor() {
	return { destroy: vi.fn(), setSearchQuery: vi.fn(), setShowOutline: vi.fn(), save: vi.fn().mockResolvedValue(true) }
}

afterEach(() => {
	mocks.beforeUnmount.forEach((callback) => callback())
	vi.useRealTimers()
})

describe('writable Text editor lifecycle', () => {
	it('does not open a writable session when a page is only read', async () => {
		const { setupEditor } = useEditor(ref('Existing page'))
		await setupEditor()
		expect(mocks.createEditor).not.toHaveBeenCalled()
		expect(mocks.root.load).not.toHaveBeenCalled()
		expect(mocks.root.done).toHaveBeenCalledWith('editor')
	})

	it('creates the editor on entering edit mode and reuses it on subsequent entries', async () => {
		const instance = makeEditor()
		mocks.createEditor.mockImplementation(async (options) => {
			options.onLoaded()
			return instance
		})
		const { setupEditor, editor } = useEditor(ref('Existing page'))
		await setupEditor()
		mocks.pages.isTextEdit = true
		await Promise.all([setupEditor(), setupEditor()])
		expect(mocks.createEditor).toHaveBeenCalledTimes(1)
		expect(mocks.createEditor).toHaveBeenCalledWith(expect.objectContaining({ fileId: 42, readOnly: false }))
		expect(editor.value).toBe(instance)
		mocks.pages.isTextEdit = false
		await setupEditor()
		mocks.pages.isTextEdit = true
		await setupEditor()
		expect(mocks.createEditor).toHaveBeenCalledTimes(1)
	})

	it('does not create an editor without collective edit permission', async () => {
		mocks.collectives.currentCollectiveCanEdit = false
		mocks.pages.isTextEdit = true
		const { setupEditor, editor } = useEditor(ref('Existing page'))
		await setupEditor()
		expect(mocks.createEditor).not.toHaveBeenCalled()
		expect(editor.value).toBeNull()
	})

	it('destroys an editor that finishes initialization after its page is unmounted', async () => {
		let resolveEditor!: (value: { destroy: ReturnType<typeof vi.fn> }) => void
		mocks.createEditor.mockReturnValue(new Promise((resolve) => {
			resolveEditor = resolve
		}))
		mocks.pages.isTextEdit = true
		const { setupEditor } = useEditor(ref('Existing page'))
		const pending = setupEditor()
		const rejected = expect(pending).rejects.toThrow('cancelled')
		await Promise.resolve()
		mocks.beforeUnmount.forEach((callback) => callback())
		const instance = makeEditor()
		resolveEditor(instance)
		await rejected
		await Promise.resolve()
		expect(instance.destroy).toHaveBeenCalledOnce()
	})
})

describe('editor readiness and failures', () => {
	it('waits for onLoaded even when createEditor has resolved', async () => {
		const instance = makeEditor()
		mocks.createEditor.mockResolvedValue(instance)
		mocks.pages.isTextEdit = true
		const { setupEditor, editor } = useEditor(ref('Page'))
		let finished = false
		const pending = setupEditor().then(() => {
			finished = true
		})
		await vi.waitFor(() => expect(mocks.createEditor).toHaveBeenCalledOnce())
		expect(finished).toBe(false)
		expect(editor.value).toBeNull()
		mocks.createEditor.mock.calls[0][0].onLoaded()
		await pending
		expect(editor.value).toBe(instance)
	})

	it('cancels an allocated editor still waiting for its document when navigating away', async () => {
		const instance = makeEditor()
		mocks.createEditor.mockResolvedValue(instance)
		mocks.pages.isTextEdit = true
		const { setupEditor, editor } = useEditor(ref('Page A'))
		const pending = expect(setupEditor()).rejects.toThrow('cancelled')
		await vi.waitFor(() => expect(mocks.createEditor).toHaveBeenCalledOnce())
		mocks.pages.currentPageId = 43
		mocks.beforeUnmount.forEach((callback) => callback())
		await pending
		mocks.root.done.mockClear()
		mocks.createEditor.mock.calls[0][0].onLoaded()
		expect(instance.destroy).toHaveBeenCalledOnce()
		expect(editor.value).toBeNull()
		expect(mocks.root.done).not.toHaveBeenCalled()
	})

	it('allows retry after a rejected initialization', async () => {
		mocks.createEditor.mockRejectedValueOnce(new Error('offline'))
		mocks.pages.isTextEdit = true
		const { setupEditor } = useEditor(ref('Page'))
		await expect(setupEditor()).rejects.toThrow('offline')
		await setupEditor()
		expect(mocks.createEditor).toHaveBeenCalledTimes(2)
		expect(mocks.root.done).toHaveBeenCalledWith('editor')
	})

	it('times out a session that never loads and allows retry', async () => {
		vi.useFakeTimers()
		const failedInstance = makeEditor()
		mocks.createEditor.mockResolvedValueOnce(failedInstance)
		mocks.pages.isTextEdit = true
		const { setupEditor } = useEditor(ref('Page'))
		const pending = expect(setupEditor()).rejects.toThrow('timed out')
		await vi.advanceTimersByTimeAsync(30_000)
		await pending
		expect(failedInstance.destroy).toHaveBeenCalledOnce()
		await setupEditor()
		expect(mocks.createEditor).toHaveBeenCalledTimes(2)
	})

	it('ignores late callbacks for a page that was left', async () => {
		mocks.pages.isTextEdit = true
		const { setupEditor, editorContent } = useEditor(ref('Page A'))
		await setupEditor()
		const options = mocks.createEditor.mock.calls[0][0]
		mocks.beforeUnmount.forEach((callback) => callback())
		mocks.pages.currentPageId = 43
		mocks.root.done.mockClear()
		options.onLoaded()
		options.onUpdate({ markdown: 'late content' })
		options.onAttachmentsUpdated({ attachmentSrcs: ['old.png'] })
		options.onOutlineToggle(true)
		expect(editorContent.value).toBeNull()
		expect(mocks.pages.setEditorEmbeddedAttachmentSrcs).not.toHaveBeenCalled()
		expect(mocks.pages.setOutlineForCurrentPage).not.toHaveBeenCalled()
		expect(mocks.root.done).not.toHaveBeenCalled()
	})

	it('does not start a writable editor if unmounted before creation', async () => {
		mocks.pages.isTextEdit = true
		const { setupEditor } = useEditor(ref('Page A'))
		const pending = expect(setupEditor()).rejects.toThrow()
		mocks.beforeUnmount.forEach((callback) => callback())
		await pending
		expect(mocks.createEditor).not.toHaveBeenCalled()
	})

	it('keeps an active attachment action alive until saving finishes after navigation', async () => {
		useEditor(ref('Page A'))
		const run = mocks.pages.registerEditorAction.mock.calls[0][0]
		let finish!: () => void
		const action = vi.fn<(editor: ReturnType<typeof makeEditor>) => Promise<void>>(() => new Promise<void>((resolve) => {
			finish = resolve
		}))
		const pending = run(action)
		await vi.waitFor(() => expect(action).toHaveBeenCalledOnce())
		const instance = action.mock.calls[0][0]
		mocks.beforeUnmount.forEach((callback) => callback())
		expect(instance.destroy).not.toHaveBeenCalled()
		finish()
		await pending
		expect(instance.destroy).toHaveBeenCalledOnce()
	})
})

describe('persisted editor content', () => {
	it('accepts Text save returning void only after matching the stored Markdown', async () => {
		const instance = makeEditor()
		instance.save.mockResolvedValue(undefined)
		mocks.createEditor.mockImplementation(async (options) => {
			options.onCreate({ markdown: 'Persist me' })
			options.onLoaded()
			return instance
		})
		mocks.get.mockResolvedValue({ data: 'Persist me\n' })
		mocks.pages.isTextEdit = true
		const { setupEditor, saveEditor } = useEditor(ref('Previous content'))
		await setupEditor()
		await expect(saveEditor()).resolves.toBe(true)
		expect(mocks.get).toHaveBeenCalledOnce()
	})

	it('retries saving when Text initially resolves with stale persisted content', async () => {
		const instance = makeEditor()
		mocks.createEditor.mockImplementation(async (options) => {
			options.onCreate({ markdown: 'New content' })
			options.onLoaded()
			return instance
		})
		mocks.get.mockResolvedValueOnce({ data: 'Old content' }).mockResolvedValue({ data: 'New content' })
		mocks.pages.isTextEdit = true
		const { setupEditor, saveEditor } = useEditor(ref('Old content'))
		await setupEditor()
		await expect(saveEditor()).resolves.toBe(true)
		expect(instance.save).toHaveBeenCalledTimes(2)
	})

	it('rejects a resolved save when the persisted Markdown remains stale', async () => {
		mocks.createEditor.mockImplementation(async (options) => {
			options.onCreate({ markdown: 'Unsaved change' })
			options.onLoaded()
			return makeEditor()
		})
		mocks.get.mockResolvedValue({ data: 'Old content' })
		mocks.pages.isTextEdit = true
		const { setupEditor, saveEditor } = useEditor(ref('Old content'))
		await setupEditor()
		await expect(saveEditor()).rejects.toThrow('stored Markdown')
	})
})
