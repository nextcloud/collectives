/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as davApi from '../../apis/dav/index.js'
import { useVersionsStore } from '../../stores/versions.js'

vi.mock('../../apis/dav/index.js', () => ({
	deleteVersion: vi.fn(),
	getVersions: vi.fn(),
	restoreVersion: vi.fn(),
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, message) => message }))
vi.mock('@nextcloud/moment', () => ({
	default: (value) => ({
		format: () => String(value),
		unix: () => Math.floor(new Date(value).getTime() / 1000),
	}),
}))
vi.mock('@nextcloud/paths', () => ({ encodePath: (value) => value, join: (...parts) => parts.join('') }))
vi.mock('@nextcloud/router', () => ({ generateRemoteUrl: () => '/remote.php/dav' }))
vi.mock('../../stores/collectives.js', () => ({ useCollectivesStore: () => ({ currentCollectiveCanEdit: true }) }))
vi.mock('../../stores/pages.js', () => ({
	usePagesStore: () => ({
		currentPage: { id: 7, title: 'Page' },
		pageDavPath: () => '',
	}),
}))

function deferred() {
	let reject
	let resolve
	const promise = new Promise((resolvePromise, rejectPromise) => {
		reject = rejectPromise
		resolve = resolvePromise
	})
	return { promise, reject, resolve }
}

function response(fileVersion, lastmod) {
	return {
		data: [{
			basename: fileVersion,
			filename: `/versions/7/${fileVersion}`,
			lastmod,
			mime: 'text/markdown',
			props: { getetag: `"${fileVersion}"`, 'version-author': 'user', 'version-label': '' },
			size: 10,
			type: 'file',
		}],
	}
}

describe('versions metadata generations', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.restoreAllMocks()
	})

	it('publishes only the latest A to B to A request without redirecting stale results', async () => {
		const firstA = deferred()
		const b = deferred()
		const latestA = deferred()
		vi.mocked(davApi.getVersions)
			.mockReturnValueOnce(firstA.promise)
			.mockReturnValueOnce(b.promise)
			.mockReturnValueOnce(latestA.promise)
		const store = useVersionsStore()

		const firstRequest = store.getVersions(7)
		const secondRequest = store.getVersions(8)
		const latestRequest = store.getVersions(7)
		firstA.resolve(response('100', '2026-08-29T10:00:00Z'))
		b.resolve(response('200', '2026-08-29T10:01:00Z'))

		await expect(firstRequest).resolves.toEqual([expect.objectContaining({ fileVersion: '100' })])
		await expect(secondRequest).resolves.toEqual([expect.objectContaining({ fileVersion: '200' })])
		expect(store.versions).toEqual([])
		latestA.resolve(response('300', '2026-08-29T10:02:00Z'))

		const latestResult = await latestRequest
		expect(latestResult).toEqual(store.versions)
		expect(store.versions.map(({ fileVersion }) => fileVersion)).toEqual(['300'])
		expect(store.loadedPageId).toBe(7)
	})

	it('returns the stale transport failure instead of a synthetic superseded error', async () => {
		const stale = deferred()
		const current = deferred()
		vi.mocked(davApi.getVersions)
			.mockReturnValueOnce(stale.promise)
			.mockReturnValueOnce(current.promise)
		const store = useVersionsStore()

		const staleRequest = store.getVersions(7)
		const currentRequest = store.getVersions(8)
		stale.reject(new Error('stale transport failed'))
		current.resolve(response('200', '2026-08-29T10:01:00Z'))

		await expect(staleRequest).rejects.toThrow('stale transport failed')
		await currentRequest
		expect(store.loadedPageId).toBe(8)
	})

	it('keeps the current snapshot preparer outside reactive Pinia state', async () => {
		const store = useVersionsStore()
		const prepare = vi.fn().mockResolvedValue('prepared')
		const unregister = store.registerCurrentSnapshotPreparer(prepare)

		expect(Object.hasOwn(store.$state, 'currentSnapshotPreparer')).toBe(false)
		await expect(store.prepareCurrentSnapshot()).resolves.toBe('prepared')
		unregister()
		await expect(store.prepareCurrentSnapshot()).resolves.toBeUndefined()
	})

	it.each([
		['restore', 'restoreVersion'],
		['delete', 'deleteVersion'],
	])('handles a metadata refresh failure after %s', async (_operation, action) => {
		vi.mocked(davApi[action]).mockResolvedValue()
		const store = useVersionsStore()
		vi.spyOn(store, 'getVersions').mockRejectedValue(new Error('refresh failed'))
		const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
		const version = { basename: 'Initial version', fileId: 7, fileVersion: '100' }

		await expect(store[action](version)).resolves.toBeUndefined()

		expect(davApi[action]).toHaveBeenCalledWith(7, '100')
		expect(store.getVersions).toHaveBeenCalledWith(7)
		expect(consoleError).toHaveBeenCalledWith('Failed to refresh page versions', expect.any(Error))
	})
})
