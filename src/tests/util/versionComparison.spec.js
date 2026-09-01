/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import {
	classifyComparisonSnapshotError,
	ComparisonSnapshotEncodingError,
	ComparisonSnapshotError,
	ComparisonSnapshotLimitError,
	createComparisonRequestManager,
	createVersionComparisonState,
	CurrentSnapshotPreparationError,
	isVersionComparisonCancellation,
	loadBoundedSnapshotPreview,
	loadVersionComparisonSnapshots,
	normalizeVersionComparisonPair,
	prepareVersionComparisonPair,
	selectVersionComparisonRenderer,
	VERSION_COMPARISON_LIMITS,
	VersionComparisonSnapshotCache,
} from '../../util/versionComparison.js'

const current = { fileId: 7, label: '', mtime: 3000, size: 10, source: '/current', url: '/current' }
const old = { fileVersion: '1', label: 'Old', mtime: 1000, size: 10, source: '/old', url: '/old' }
const middle = { fileVersion: '2', label: 'Middle', mtime: 2000, size: 10, source: '/middle', url: '/middle' }

function measured(content, byteLength = new TextEncoder().encode(content).byteLength) {
	return { byteLength, content }
}

function state(versions = [old, middle]) {
	return createVersionComparisonState(current, versions)
}

function pair(first, second) {
	const { options } = state()
	const option = (version) => options.find(({ fileInfo }) => fileInfo === version)
	return normalizeVersionComparisonPair(option(first), option(second))
}

describe('version comparison pair model', () => {
	it.each([
		[false, vi.fn(), vi.fn(), 'semantic'],
		[true, vi.fn(), vi.fn(), 'semantic'],
		[false, undefined, vi.fn(), 'viewer'],
		[true, undefined, vi.fn(), 'unsupported'],
		[false, undefined, undefined, 'unsupported'],
	])('selects supported renderers without breaking the desktop Viewer fallback', (isMobile, comparisonFactory, viewerCompare, expected) => {
		expect(selectVersionComparisonRenderer({ comparisonFactory, viewerCompare, isMobile })).toBe(expected)
	})

	it('creates stable current and historical identities in newest-first selector order', () => {
		expect(state().options.map(({ key }) => key)).toEqual(['current', 'version:2', 'version:1'])
	})

	it('quarantines invalid and duplicate historical identities', () => {
		const result = state([
			{ ...old, fileVersion: '' },
			{ ...old, fileVersion: 'same' },
			{ ...middle, fileVersion: 'same' },
			middle,
		])
		expect(result.options.map(({ key }) => key)).toEqual(['current', 'version:2'])
		expect(result.quarantinedCount).toBe(3)
		const usableRouteIds = result.options.flatMap(({ routeId, routeAliases = [] }) => [routeId, ...routeAliases])
		expect(result.ambiguousRouteIds.some((routeId) => usableRouteIds.includes(routeId))).toBe(false)
	})

	it('quarantines a historical identity that matches the current snapshot', () => {
		const currentSnapshot = {
			...current,
			fileVersion: 'same',
			isCurrentSnapshot: true,
		}
		const result = state([
			{ ...old, fileVersion: 'same' },
			currentSnapshot,
		])

		expect(result.options.map(({ key }) => key)).toEqual(['current'])
		expect(result.options[0].routeId).toBe('current:same')
		expect(result.quarantinedCount).toBe(1)
		expect(result.ambiguousRouteIds).toEqual(['version:same'])
	})

	it('C01 normalizes current and historical snapshots by chronology and identity', () => {
		expect(pair(current, old)).toMatchObject({
			earlier: { key: 'version:1', kind: 'historical', label: 'Old', mtime: 1000 },
			later: { key: 'current', kind: 'current', mtime: 3000 },
		})
	})

	it('C02 loads both immutable historical snapshots in chronological order', async () => {
		const comparisonPair = pair(middle, old)
		const fetchSnapshot = vi.fn(async ({ url }) => measured(`body:${url}`))

		await expect(loadVersionComparisonSnapshots(comparisonPair, fetchSnapshot)).resolves.toEqual({
			afterContent: 'body:/middle',
			beforeContent: 'body:/old',
		})
		expect(fetchSnapshot.mock.calls.map(([snapshot]) => snapshot.key))
			.toEqual(['version:1', 'version:2'])
	})

	it('C03 normalizes reversed selectors without swapping visible labels', () => {
		expect(pair(middle, old)).toMatchObject({
			earlier: { key: 'version:1', label: 'Old', mtime: 1000 },
			later: { key: 'version:2', label: 'Middle', mtime: 2000 },
		})
	})

	it('C04 rejects an identical pair before comparison starts', () => {
		const option = state().options[0]
		expect(() => normalizeVersionComparisonPair(option, option)).toThrow('distinct')
	})
})

describe('atomic current snapshot freshness', () => {
	it('AUD-03 binds current bytes to the committed DAV snapshot resource', () => {
		const committed = {
			...middle,
			etag: '"committed"',
			fileVersion: '4',
			isCurrentSnapshot: true,
			mtime: 4000,
			source: '/versions/4',
		}
		const [currentOption] = createVersionComparisonState(
			{ ...current, mtime: 4000, source: '/mutable-live' },
			[old, committed],
		).options

		expect(currentOption).toMatchObject({
			etag: '"committed"',
			routeId: 'current:4',
			url: '/versions/4',
		})
	})

	it('AUD-03 does not pair a committed identity with the mutable live URL', () => {
		const committedWithoutSource = {
			...middle,
			fileVersion: '4',
			isCurrentSnapshot: true,
			mtime: 4000,
			source: undefined,
			url: undefined,
		}
		const [currentOption] = createVersionComparisonState(
			{ ...current, etag: '"mutable"', mtime: 4000, size: 99, source: '/mutable-live', url: '/mutable-live' },
			[old, committedWithoutSource],
		).options

		expect(currentOption).toMatchObject({
			routeId: 'current:4',
			size: committedWithoutSource.size,
		})
		expect(currentOption.etag).toBeUndefined()
		expect(currentOption.url).toBeUndefined()
	})

	it('AUD-03 resolves identity and bytes from the committed preparation result', async () => {
		const stalePair = pair(current, old)
		const committedPair = {
			earlier: stalePair.earlier,
			later: { ...stalePair.later, mtime: 4000, routeId: 'current:4', url: '/current-4' },
		}
		const prepare = vi.fn().mockResolvedValue(committedPair)
		const resolvePair = vi.fn((prepared) => prepared ?? stalePair)

		await expect(prepareVersionComparisonPair(stalePair, prepare, resolvePair))
			.resolves.toBe(committedPair)
		expect(resolvePair).toHaveBeenCalledWith(committedPair)
	})

	it('AUD-03 keeps committed route and bytes stable across a later save race', async () => {
		const stalePair = pair(current, old)
		const liveCurrent = { ...current, mtime: 4000, source: '/mutable-live-4' }
		const committed = {
			...middle,
			fileVersion: '4',
			isCurrentSnapshot: true,
			mtime: 4000,
			source: '/versions/4',
		}
		const committedPair = await prepareVersionComparisonPair(
			stalePair,
			vi.fn().mockResolvedValue([old, committed]),
			(versions) => {
				const options = createVersionComparisonState(liveCurrent, versions).options
				return normalizeVersionComparisonPair(
					options.find(({ key }) => key === stalePair.earlier.key),
					options.find(({ key }) => key === stalePair.later.key),
				)
			},
		)

		liveCurrent.mtime = 5000
		liveCurrent.source = '/mutable-live-5'
		committed.source = '/versions/5'
		const contents = await loadVersionComparisonSnapshots(
			committedPair,
			async ({ url }) => measured(`bytes:${url}`),
		)

		expect(committedPair.later).toMatchObject({
			routeId: 'current:4',
			url: '/versions/4',
		})
		expect(contents.afterContent).toBe('bytes:/versions/4')
	})

	it('C08 rejects failed current preparation before resolving a stale pair', async () => {
		const stalePair = pair(current, old)
		const failure = new Error('save failed')
		const prepare = vi.fn().mockRejectedValue(failure)
		const resolvePair = vi.fn()

		await expect(prepareVersionComparisonPair(stalePair, prepare, resolvePair))
			.rejects.toMatchObject({ cause: failure })
		expect(resolvePair).not.toHaveBeenCalled()
	})

	it('reports a missing current preparer as a typed preparation failure', async () => {
		const stalePair = pair(current, old)
		const resolvePair = vi.fn()

		await expect(prepareVersionComparisonPair(
			stalePair,
			vi.fn().mockResolvedValue(undefined),
			resolvePair,
		)).rejects.toBeInstanceOf(CurrentSnapshotPreparationError)
		expect(resolvePair).not.toHaveBeenCalled()
	})

	it('rebinds the current route identity after preparation refreshes versions', async () => {
		const stalePair = pair(current, old)
		const freshCurrent = { ...current, mtime: 4000 }
		const freshVersions = [{ ...old }, { ...middle, fileVersion: '4', mtime: 4000, isCurrentSnapshot: true }]
		const prepare = vi.fn().mockResolvedValue(freshVersions)
		const resolvePair = vi.fn((versions) => {
			const options = createVersionComparisonState(freshCurrent, versions).options
			return normalizeVersionComparisonPair(
				options.find(({ key }) => key === stalePair.earlier.key),
				options.find(({ key }) => key === stalePair.later.key),
			)
		})

		const refreshedPair = await prepareVersionComparisonPair(stalePair, prepare, resolvePair)

		expect(prepare).toHaveBeenCalledOnce()
		expect(resolvePair).toHaveBeenCalledOnce()
		expect(refreshedPair.later.routeId).toBe('current:4')
	})
})

describe('atomic loading, cancellation, and cache', () => {
	it.each([
		['unknown', undefined],
		['negative', -1],
		['over the ceiling', VERSION_COMPARISON_LIMITS.maximumSnapshotBytes + 1],
	])('AUD-08 rejects %s snapshot size before either body request', async (_name, size) => {
		const comparisonPair = pair(old, middle)
		comparisonPair.earlier = { ...comparisonPair.earlier, size }
		const fetchSnapshot = vi.fn()

		await expect(loadVersionComparisonSnapshots(comparisonPair, fetchSnapshot))
			.rejects.toBeInstanceOf(ComparisonSnapshotLimitError)
		expect(fetchSnapshot).not.toHaveBeenCalled()
	})

	it.each([
		VERSION_COMPARISON_LIMITS.maximumSnapshotBytes - 1,
		VERSION_COMPARISON_LIMITS.maximumSnapshotBytes,
	])('AUD-08 accepts trusted metadata at %i bytes', async (size) => {
		const comparisonPair = pair(old, middle)
		comparisonPair.earlier = { ...comparisonPair.earlier, size }
		comparisonPair.later = { ...comparisonPair.later, size }
		const fetchSnapshot = vi.fn().mockResolvedValue(measured('bounded'))

		await expect(loadVersionComparisonSnapshots(comparisonPair, fetchSnapshot)).resolves.toBeDefined()
		expect(fetchSnapshot).toHaveBeenCalledTimes(2)
	})

	it('AUD-08 bounds a preview when the server ignores Range', async () => {
		const cancel = vi.fn()
		const fetchPreview = vi.fn().mockResolvedValue({
			body: {
				getReader: () => ({
					cancel,
					read: vi.fn()
						.mockResolvedValueOnce({ done: false, value: new TextEncoder().encode('x'.repeat(VERSION_COMPARISON_LIMITS.maximumPreviewBytes + 100)) })
						.mockResolvedValueOnce({ done: true }),
				}),
			},
			ok: true,
		})

		const preview = await loadBoundedSnapshotPreview(state().options[1], fetchPreview)

		expect(new TextEncoder().encode(preview.content).byteLength).toBeLessThanOrEqual(VERSION_COMPARISON_LIMITS.maximumPreviewBytes)
		expect([...preview.content]).toHaveLength(VERSION_COMPARISON_LIMITS.maximumPreviewCharacters)
		expect(preview.truncated).toBe(true)
		expect(cancel).toHaveBeenCalledOnce()
		expect(fetchPreview.mock.calls[0][1].headers.Range).toBe(`bytes=0-${VERSION_COMPARISON_LIMITS.maximumPreviewBytes - 1}`)
	})

	it('AUD-08 keeps UTF-8 preview text and DOM characters bounded', async () => {
		const bytes = new TextEncoder().encode('é'.repeat(VERSION_COMPARISON_LIMITS.maximumPreviewCharacters + 10))
		const fetchPreview = vi.fn().mockResolvedValue(new Response(bytes))

		const preview = await loadBoundedSnapshotPreview(state().options[1], fetchPreview)

		expect(preview.content).not.toContain('\uFFFD')
		expect([...preview.content]).toHaveLength(VERSION_COMPARISON_LIMITS.maximumPreviewCharacters)
		expect(preview.truncated).toBe(true)
	})

	it('AUD-08 rejects interior malformed UTF-8 without consuming the remaining preview', async () => {
		const cancel = vi.fn()
		const read = vi.fn()
			.mockResolvedValueOnce({ done: false, value: Uint8Array.from([0x61]) })
			.mockResolvedValueOnce({ done: false, value: Uint8Array.from([0x80]) })
			.mockResolvedValueOnce({ done: false, value: Uint8Array.from([0x62]) })
		const fetchPreview = vi.fn().mockResolvedValue({
			body: { getReader: () => ({ cancel, read }) },
			ok: true,
		})

		await expect(loadBoundedSnapshotPreview(state().options[1], fetchPreview))
			.rejects.toThrow()
		expect(cancel).toHaveBeenCalledOnce()
		expect(read).toHaveBeenCalledTimes(2)
	})

	it.each([
		[1, [0xf0]],
		[2, [0xf0, 0x9f]],
		[3, [0xf0, 0x9f, 0x98]],
	])('AUD-08 drops only an incomplete trailing UTF-8 sequence split after %i bytes', async (_length, suffix) => {
		const bytes = Uint8Array.from([0x6f, 0x6b, ...suffix])
		const fetchPreview = vi.fn().mockResolvedValue(new Response(bytes))

		await expect(loadBoundedSnapshotPreview(state().options[1], fetchPreview)).resolves.toEqual({
			content: 'ok',
			truncated: true,
		})
	})

	it.each([
		[[{ response: { status: 403 } }], 'permission-one'],
		[[{ response: { status: 403 } }, { response: { status: 403 } }], 'permission-two'],
		[[{ response: { status: 404 } }], 'expired-one'],
		[[{ response: { status: 410 } }, { response: { status: 404 } }], 'expired-two'],
		[[{ response: { status: 403 } }, { response: { status: 404 } }], 'network'],
		[[{ response: { status: 404 } }, new Error('offline')], 'network'],
		[[new ComparisonSnapshotEncodingError()], 'encoding'],
		[[new Error('offline')], 'network'],
	])('classifies snapshot failures for actionable messages', (reasons, expected) => {
		const error = { reasons }
		expect(classifyComparisonSnapshotError(error)).toBe(expected)
	})

	it('distinguishes cancellation from a network failure', () => {
		expect(isVersionComparisonCancellation({ name: 'AbortError' })).toBe(true)
		expect(isVersionComparisonCancellation({ name: 'CanceledError' })).toBe(true)
		expect(isVersionComparisonCancellation(new Error('offline'))).toBe(false)
		expect(isVersionComparisonCancellation({ __CANCEL__: true })).toBe(true)
	})

	it('loads both immutable snapshots and publishes neither when one or both fail', async () => {
		const comparisonPair = pair(old, middle)
		const fetchSnapshot = vi.fn(async ({ url }) => {
			if (url === '/old') {
				throw new Error('expired')
			}
			return measured(url)
		})
		const error = await loadVersionComparisonSnapshots(comparisonPair, fetchSnapshot).catch((reason) => reason)
		expect(error).toBeInstanceOf(ComparisonSnapshotError)
		expect(error.reasons).toHaveLength(1)
		expect(fetchSnapshot).toHaveBeenCalledTimes(2)
	})

	it('C05 reuses a successful historical body without another DAV load', async () => {
		const cache = new VersionComparisonSnapshotCache()
		const historical = state().options[1]
		const fetchSnapshot = vi.fn(async ({ kind }) => measured(`${kind}-${fetchSnapshot.mock.calls.length}`))

		expect(await cache.load(historical, fetchSnapshot)).toBe(await cache.load(historical, fetchSnapshot))
		expect(fetchSnapshot).toHaveBeenCalledOnce()
	})

	it('C06 reloads the current snapshot instead of serving a stale cache entry', async () => {
		const cache = new VersionComparisonSnapshotCache()
		const currentSnapshot = state().options[0]
		const fetchSnapshot = vi.fn(async () => measured(`current-${fetchSnapshot.mock.calls.length}`))

		await cache.load(currentSnapshot, fetchSnapshot)
		await cache.load(currentSnapshot, fetchSnapshot)
		expect(fetchSnapshot).toHaveBeenCalledTimes(2)
	})

	it('C11 does not cache a failed or aborted historical completion before retry', async () => {
		const cache = new VersionComparisonSnapshotCache()
		const historical = state().options[1]
		const failed = vi.fn().mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(measured('ok'))
		await expect(cache.load(historical, failed)).rejects.toThrow('offline')
		await expect(cache.load(historical, failed)).resolves.toEqual(measured('ok'))
		cache.clear()
		const controller = new AbortController()
		const aborted = vi.fn(async () => {
			controller.abort()
			return measured('stale')
		})
		await cache.load(historical, aborted, controller.signal)
		await cache.load(historical, aborted)
		expect(aborted).toHaveBeenCalledTimes(2)
	})

	it('AUD-15 enforces entry, UTF-8 byte, identity, recency, and clear boundaries', async () => {
		const cache = new VersionComparisonSnapshotCache({ maximumBytes: 8, maximumEntries: 2 })
		const snapshots = [
			{ ...state().options[1], fileVersion: 'a', url: '/a' },
			{ ...state().options[1], fileVersion: 'b', url: '/b' },
			{ ...state().options[1], fileVersion: 'c', url: '/c' },
		]
		const fetchSnapshot = vi.fn(async ({ fileVersion }) => measured(({ a: 'éé', b: 'bbbb', c: 'cccc' })[fileVersion]))

		await cache.load(snapshots[0], fetchSnapshot)
		await cache.load(snapshots[1], fetchSnapshot)
		await cache.load(snapshots[0], fetchSnapshot)
		await cache.load(snapshots[2], fetchSnapshot)
		await cache.load(snapshots[1], fetchSnapshot)
		expect(fetchSnapshot).toHaveBeenCalledTimes(4)

		const sameVersionDifferentUrl = { ...snapshots[0], url: '/different' }
		await cache.load(sameVersionDifferentUrl, fetchSnapshot)
		expect(fetchSnapshot).toHaveBeenCalledTimes(5)

		cache.clear()
		await cache.load(sameVersionDifferentUrl, fetchSnapshot)
		expect(fetchSnapshot).toHaveBeenCalledTimes(6)
	})

	it('AUD-15 reuses the bounded loader byte measurement when caching', async () => {
		const cache = new VersionComparisonSnapshotCache({ maximumBytes: 4, maximumEntries: 1 })
		const historical = state().options[1]
		const fetchSnapshot = vi.fn().mockResolvedValue({
			byteLength: 5,
			content: 'x',
		})

		await expect(cache.load(historical, fetchSnapshot)).resolves.toEqual({ byteLength: 5, content: 'x' })
		await cache.load(historical, fetchSnapshot)

		expect(fetchSnapshot).toHaveBeenCalledTimes(2)
	})

	it.each([
		['two-byte characters', 'éé', 3],
		['an astral character', '😀', 3],
		['an unpaired surrogate', '\ud800', 2],
	])('AUD-15 does not cache %s above the UTF-8 byte budget', async (_name, content, maximumBytes) => {
		const cache = new VersionComparisonSnapshotCache({ maximumBytes, maximumEntries: 2 })
		const historical = state().options[1]
		const fetchSnapshot = vi.fn().mockResolvedValue(measured(content))

		await cache.load(historical, fetchSnapshot)
		await cache.load(historical, fetchSnapshot)
		expect(fetchSnapshot).toHaveBeenCalledTimes(2)
	})

	it('C09 aborts a superseded request and prevents its generation from publishing', () => {
		const manager = createComparisonRequestManager()
		const first = manager.begin()
		const second = manager.begin()
		expect(first.signal.aborted).toBe(true)
		expect(manager.isCurrent(first.generation)).toBe(false)
		expect(manager.isCurrent(second.generation)).toBe(true)
	})

	it('C10 allows only the latest rapid pair generation to publish', () => {
		const manager = createComparisonRequestManager()
		const first = manager.begin()
		const second = manager.begin()
		const published = []

		if (manager.isCurrent(first.generation)) {
			published.push('stale')
		}
		if (manager.isCurrent(second.generation)) {
			published.push('latest')
		}

		expect(published).toEqual(['latest'])
	})

	it('cancels the active comparison request', () => {
		const manager = createComparisonRequestManager()
		const active = manager.begin()

		manager.cancel()
		expect(active.signal.aborted).toBe(true)
		expect(manager.isCurrent(active.generation)).toBe(false)
	})
})
