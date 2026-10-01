/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type * as Vue from 'vue'

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useEditor } from '../composables/useEditor.ts'

const mocks = vi.hoisted(() => ({
	pages: { currentPage: { id: 42 }, currentPageId: 42, isTextEdit: false, hasOutline: () => false, pageFilePath: () => 'Page.md' },
	collectives: { currentCollectiveCanEdit: true },
	root: { load: vi.fn(), done: vi.fn(), loading: () => false, shareTokenParam: null },
	beforeUnmount: [] as Array<() => void>,
	createEditor: vi.fn(),
}))

vi.mock('vue', async (importOriginal) => ({
	...await importOriginal<typeof Vue>(),
	onBeforeUnmount: (callback: () => void) => mocks.beforeUnmount.push(callback),
}))
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
	mocks.createEditor.mockResolvedValue({ destroy: vi.fn() })
	mocks.beforeUnmount.length = 0
	mocks.pages.isTextEdit = false
	mocks.collectives.currentCollectiveCanEdit = true
	vi.stubGlobal('window', { customElements: { get: () => true }, OCA: { Text: { createEditor: mocks.createEditor }, Collectives: { openLink: vi.fn() } } })
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
		const instance = { destroy: vi.fn() }
		mocks.createEditor.mockResolvedValue(instance)
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
		mocks.beforeUnmount.forEach((callback) => callback())
		const instance = { destroy: vi.fn() }
		resolveEditor(instance)
		await pending
		expect(instance.destroy).toHaveBeenCalledOnce()
	})
})
