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

	it('uses stable selector keys without source paths', () => {
		const { options } = createVersionComparisonState(current, [old, current, middle])
		expect(options.map(({ key }) => key)).toEqual([
			'current',
			'version:2',
			'version:1',
		])
		expect(options.map(({ key }) => key).join()).not.toContain('/middle')
		expect(options[0].fileInfo).toBe(current)
	})

	it('quarantines malformed and duplicate identities without hiding valid options', () => {
		const valid = { ...middle, fileVersion: 'valid' }
		const duplicateFirst = { ...old, fileVersion: 'duplicate' }
		const duplicateSecond = { ...middle, fileVersion: 'duplicate', mtime: 2500 }
		const { options, quarantined } = createVersionComparisonState(current, [
			current,
			valid,
			{ ...old, fileVersion: '' },
			{ ...old, fileVersion: undefined, mtime: 1100 },
			{ ...old, fileVersion: 'current', mtime: 1300 },
			duplicateFirst,
			duplicateSecond,
		])

		expect(options.map(({ key }) => key)).toEqual(['current', 'version:valid', 'version:current'])
		expect(quarantined.map(({ reason }) => reason)).toEqual([
			'invalid',
			'invalid',
			'duplicate',
			'duplicate',
		])
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

	it('uses locale-independent keys to order snapshots with the same timestamp', () => {
		const first = { ...old, fileVersion: 'z' }
		const second = { ...old, fileVersion: 'ä' }
		const { options } = createVersionComparisonState(current, [first, second])
		const pair = normalizeVersionComparisonPair(options[1], options[2])

		expect(pair.earlier.key).toBe('version:z')
		expect(pair.later.key).toBe('version:ä')
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
