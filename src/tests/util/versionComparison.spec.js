/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import {
	ComparisonRequestManager,
	ComparisonSnapshotError,
	createVersionComparisonState,
	loadVersionComparisonSnapshots,
	normalizeVersionComparisonPair,
	selectVersionComparisonRenderer,
	VersionComparisonSnapshotCache,
} from '../../util/versionComparison.js'
import { MAX_COMPARISON_ID_LENGTH, resolveVersionComparisonRoute } from '../../util/versionComparisonRoute.js'

const current = {
	label: '',
	mtime: 3000,
	source: '/current',
	url: '/current',
}
const old = {
	fileVersion: '1',
	label: 'Old',
	mtime: 1000,
	source: '/old',
	url: '/old',
}
const middle = {
	fileVersion: '2',
	label: 'Middle',
	mtime: 2000,
	source: '/middle',
	url: '/middle',
}

describe('version comparison pair model', () => {
	it.each([
		['callable factory on desktop', false, vi.fn(), vi.fn(), 'semantic'],
		['callable factory on mobile', true, vi.fn(), vi.fn(), 'semantic'],
		['Viewer on desktop', false, undefined, vi.fn(), 'viewer'],
		['Viewer on mobile', true, undefined, vi.fn(), 'unsupported'],
		['no available renderer', false, undefined, undefined, 'unsupported'],
	])('selects the renderer for %s', (_name, isMobile, comparisonFactory, viewerCompare, expected) => {
		expect(selectVersionComparisonRenderer({ comparisonFactory, viewerCompare, isMobile }))
			.toBe(expected)
	})

	it('uses stable identities without source paths', () => {
		const { options } = createVersionComparisonState(current, [old, current, middle])
		expect(options.map(({ key }) => key)).toEqual([
			'current',
			'version:2',
			'version:1',
		])
		expect(options.map(({ routeId }) => routeId)).toEqual(['current:3', 'version:2', 'version:1'])
		expect(options.map(({ key }) => key).join()).not.toContain('/middle')
		expect(options[0].fileInfo).toBe(current)
	})

	it('keeps a route to Current stable after that snapshot becomes historical', () => {
		const beforeEdit = createVersionComparisonState(current, [old, current]).options
		expect(resolveVersionComparisonRoute(beforeEdit, { from: 'version:1', to: 'current' }).second)
			.toBe(beforeEdit[0])
		const stableCurrentId = beforeEdit[0].routeId
		const editedCurrent = { ...current, mtime: 4000, source: '/edited', url: '/edited' }
		const previousCurrent = { ...current, fileVersion: '3' }
		const afterEdit = createVersionComparisonState(editedCurrent, [old, previousCurrent, editedCurrent]).options
		const resolved = resolveVersionComparisonRoute(afterEdit, { from: 'version:1', to: stableCurrentId })

		expect(stableCurrentId).toBe('current:3')
		expect(resolved.second).toMatchObject({ kind: 'historical', routeId: 'version:3', url: '/current' })
		expect(resolved.missing).toEqual([])
	})

	it('pins Current from DAV when page metadata lags after a write', () => {
		const staleCurrent = { ...current, mtime: 3000 }
		const previousCurrent = { ...current, fileVersion: '3' }
		const davCurrent = {
			...current,
			fileVersion: '4',
			isCurrentSnapshot: true,
			mtime: 4000,
			source: '/edited',
			url: '/edited',
		}
		const { options } = createVersionComparisonState(staleCurrent, [old, previousCurrent, davCurrent])
		const resolved = resolveVersionComparisonRoute(options, { from: 'version:1', to: 'current:3' })

		expect(options[0]).toMatchObject({ kind: 'current', mtime: 4000, routeId: 'current:4' })
		expect(resolved.second).toMatchObject({ kind: 'historical', routeId: 'version:3', url: '/current' })
	})

	it('keeps historical identity stable when a page rename changes its source path', () => {
		const [beforeRename] = createVersionComparisonState(current, [old]).options
		const renamedCurrent = { ...current, source: '/renamed/current', url: '/renamed/current' }
		const renamedOld = { ...old, source: '/renamed/old', url: '/renamed/old' }
		const [afterRename] = createVersionComparisonState(renamedCurrent, [renamedOld]).options
		const historicalBefore = createVersionComparisonState(current, [old]).options[1]
		const historicalAfter = createVersionComparisonState(renamedCurrent, [renamedOld]).options[1]

		expect(beforeRename.routeId).toBe(afterRename.routeId)
		expect(historicalBefore.routeId).toBe(historicalAfter.routeId)
		expect(historicalBefore.key).toBe(historicalAfter.key)
		expect(historicalBefore.url).not.toBe(historicalAfter.url)
	})

	it('quarantines malformed and every duplicate identity without hiding valid options', () => {
		const valid = { ...middle, fileVersion: 'valid' }
		const duplicateFirst = { ...old, fileVersion: 'duplicate' }
		const duplicateSecond = { ...middle, fileVersion: 'duplicate', mtime: 2500 }
		const { ambiguousRouteIds, options, quarantined } = createVersionComparisonState(current, [
			current,
			valid,
			{ ...old, fileVersion: '' },
			{ ...old, fileVersion: undefined, mtime: 1100 },
			{ ...old, fileVersion: 'versions/1', mtime: 1200 },
			{ ...old, fileVersion: 'x'.repeat(MAX_COMPARISON_ID_LENGTH), mtime: 1250 },
			{ ...old, fileVersion: 'current', mtime: 1300 },
			duplicateFirst,
			duplicateSecond,
		])

		expect(options.map(({ routeId }) => routeId)).toEqual(['current:3', 'version:valid', 'version:current'])
		expect(quarantined.map(({ reason }) => reason)).toEqual([
			'invalid',
			'invalid',
			'invalid',
			'invalid',
			'duplicate',
			'duplicate',
		])
		expect(ambiguousRouteIds).toEqual(['version:duplicate', 'current:duplicate'])
		expect(resolveVersionComparisonRoute(options, { from: 'version:valid', to: 'current' }, ambiguousRouteIds))
			.toMatchObject({ second: options[0], missing: [] })
	})

	it('keeps current and historical canonical identities separate', () => {
		const colliding = { ...old, fileVersion: '3' }
		const { ambiguousRouteIds, options } = createVersionComparisonState(current, [colliding])

		expect(ambiguousRouteIds).toEqual([])
		expect(resolveVersionComparisonRoute(options, { from: 'version:3', to: 'current:3' }, ambiguousRouteIds))
			.toEqual({ first: options[1], second: options[0], missing: [] })
	})

	it('keeps the current canonical identity usable when historical aliases are ambiguous', () => {
		const valid = { ...old, fileVersion: 'valid' }
		const duplicateFirst = { ...old, fileVersion: '3', mtime: 1200 }
		const duplicateSecond = { ...middle, fileVersion: '3', mtime: 2200 }
		const { ambiguousRouteIds, options } = createVersionComparisonState(
			current,
			[valid, duplicateFirst, duplicateSecond],
		)

		expect(ambiguousRouteIds).toEqual(['version:3'])
		expect(resolveVersionComparisonRoute(
			options,
			{ from: 'version:valid', to: 'current:3' },
			ambiguousRouteIds,
		)).toMatchObject({ first: options[1], second: options[0], missing: [] })
	})

	it.each([
		['current to old', current, old],
		['old to old', middle, old],
		['reversed selection', old, middle],
	])('normalizes chronology for %s', (_name, firstVersion, secondVersion) => {
		const { options } = createVersionComparisonState(current, [old, middle])
		const byVersion = (version) => options.find(({ fileInfo }) => fileInfo === version)
		const pair = normalizeVersionComparisonPair(byVersion(firstVersion), byVersion(secondVersion))
		expect(pair.earlier.mtime).toBeLessThan(pair.later.mtime)
	})

	it('uses locale-independent identities to order snapshots with the same timestamp', () => {
		const first = { ...old, fileVersion: 'z' }
		const second = { ...old, fileVersion: 'ä' }
		const { options } = createVersionComparisonState(current, [first, second])
		const pair = normalizeVersionComparisonPair(options[1], options[2])

		expect(pair.earlier.routeId).toBe('version:z')
		expect(pair.later.routeId).toBe('version:ä')
	})

	it('blocks identical selection', () => {
		const [option] = createVersionComparisonState(current, [old]).options
		expect(() => normalizeVersionComparisonPair(option, option)).toThrow('distinct')
	})
})

describe('snapshot loading', () => {
	it('loads both persisted snapshots before returning either', async () => {
		const { options } = createVersionComparisonState(current, [old])
		const pair = normalizeVersionComparisonPair(options[0], options[1])
		const fetchSnapshot = vi.fn(async ({ url }) => `${url} content`)
		await expect(loadVersionComparisonSnapshots(pair, fetchSnapshot)).resolves.toEqual({
			afterContent: '/current content',
			beforeContent: '/old content',
		})
		expect(fetchSnapshot).toHaveBeenCalledTimes(2)
	})

	it.each([
		['one failed snapshot', ['/old']],
		['both failed snapshots', ['/old', '/current']],
	])('mounts neither side when %s', async (_name, failures) => {
		const { options } = createVersionComparisonState(current, [old])
		const pair = normalizeVersionComparisonPair(options[0], options[1])
		const fetchSnapshot = vi.fn(async ({ url }) => {
			if (failures.includes(url)) {
				throw new Error(url)
			}
			return url
		})
		await expect(loadVersionComparisonSnapshots(pair, fetchSnapshot))
			.rejects.toBeInstanceOf(ComparisonSnapshotError)
	})

	it('attempts both snapshots when one loader throws synchronously', async () => {
		const { options } = createVersionComparisonState(current, [old])
		const pair = normalizeVersionComparisonPair(options[0], options[1])
		const fetchSnapshot = vi.fn(({ url }) => {
			if (url === '/old') {
				throw new Error(url)
			}
			return Promise.resolve(url)
		})
		await expect(loadVersionComparisonSnapshots(pair, fetchSnapshot))
			.rejects.toBeInstanceOf(ComparisonSnapshotError)
		expect(fetchSnapshot).toHaveBeenCalledTimes(2)
	})

	it('aborts and ignores a stale request after reversed completion', async () => {
		const requests = new ComparisonRequestManager()
		const accepted = []
		let resolveFirst
		let resolveSecond
		const firstResult = new Promise((resolve) => {
			resolveFirst = resolve
		})
		const secondResult = new Promise((resolve) => {
			resolveSecond = resolve
		})
		const first = requests.begin()
		const firstCompletion = firstResult.then((value) => {
			if (requests.isCurrent(first.generation)) {
				accepted.push(value)
			}
		})
		const second = requests.begin()
		const secondCompletion = secondResult.then((value) => {
			if (requests.isCurrent(second.generation)) {
				accepted.push(value)
			}
		})
		expect(first.signal.aborted).toBe(true)
		expect(requests.isCurrent(first.generation)).toBe(false)
		expect(requests.isCurrent(second.generation)).toBe(true)
		resolveSecond('second')
		await secondCompletion
		resolveFirst('first')
		await firstCompletion
		expect(accepted).toEqual(['second'])
		requests.cancel()
		expect(second.signal.aborted).toBe(true)
		expect(requests.isCurrent(second.generation)).toBe(false)
	})
})

describe('comparison snapshot cache', () => {
	it('reuses only successful immutable historical bodies', async () => {
		const cache = new VersionComparisonSnapshotCache()
		const historical = createVersionComparisonState(current, [old]).options[1]
		const fetchSnapshot = vi.fn().mockResolvedValue('historical content')

		await expect(cache.load(historical, fetchSnapshot)).resolves.toBe('historical content')
		await expect(cache.load(historical, fetchSnapshot)).resolves.toBe('historical content')
		expect(fetchSnapshot).toHaveBeenCalledOnce()
	})

	it('always reloads current content', async () => {
		const cache = new VersionComparisonSnapshotCache()
		const currentSnapshot = createVersionComparisonState(current, [old]).options[0]
		const fetchSnapshot = vi.fn()
			.mockResolvedValueOnce('first current content')
			.mockResolvedValueOnce('second current content')

		await expect(cache.load(currentSnapshot, fetchSnapshot)).resolves.toBe('first current content')
		await expect(cache.load(currentSnapshot, fetchSnapshot)).resolves.toBe('second current content')
		expect(fetchSnapshot).toHaveBeenCalledTimes(2)
	})

	it('does not cache failures, aborted completions, or in-flight work', async () => {
		const cache = new VersionComparisonSnapshotCache()
		const historical = createVersionComparisonState(current, [old]).options[1]
		const failure = vi.fn()
			.mockRejectedValueOnce(new Error('failed'))
			.mockResolvedValueOnce('retried content')
		await expect(cache.load(historical, failure)).rejects.toThrow('failed')
		await expect(cache.load(historical, failure)).resolves.toBe('retried content')
		expect(failure).toHaveBeenCalledTimes(2)

		cache.clear()
		const controller = new AbortController()
		const aborted = vi.fn().mockImplementation(async () => {
			controller.abort()
			return 'stale content'
		})
		await expect(cache.load(historical, aborted, controller.signal)).resolves.toBe('stale content')
		await cache.load(historical, aborted)
		expect(aborted).toHaveBeenCalledTimes(2)

		cache.clear()
		let resolveFirst
		const first = new Promise((resolve) => {
			resolveFirst = resolve
		})
		const concurrent = vi.fn().mockReturnValue(first)
		const firstLoad = cache.load(historical, concurrent)
		const secondLoad = cache.load(historical, concurrent)
		expect(concurrent).toHaveBeenCalledTimes(2)
		resolveFirst('concurrent content')
		await Promise.all([firstLoad, secondLoad])
	})

	it('clears historical bodies between page contexts', async () => {
		const cache = new VersionComparisonSnapshotCache()
		const historical = createVersionComparisonState(current, [old]).options[1]
		const fetchSnapshot = vi.fn().mockResolvedValue('historical content')
		await cache.load(historical, fetchSnapshot)
		cache.clear()
		await cache.load(historical, fetchSnapshot)
		expect(fetchSnapshot).toHaveBeenCalledTimes(2)
	})
})
