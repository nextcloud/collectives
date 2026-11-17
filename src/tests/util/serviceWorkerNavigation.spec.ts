/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { isCacheableNavigation, navigationCacheKey } from '../../util/serviceWorkerNavigation.ts'

const url = (path: string) => new URL(`https://cloud.example${path}`)

describe('isCacheableNavigation', () => {
	it.each([
		['app', '/index.php/apps/collectives/'],
		['app with pretty URLs', '/apps/collectives/'],
		['page', '/index.php/apps/collectives/Garden-12/Roses-34'],
		['public share', '/index.php/apps/collectives/p/abc123/'],
		['page in public share', '/index.php/apps/collectives/p/abc123/Garden/Roses'],
	])('caches navigation to %s', (_, path) => {
		expect(isCacheableNavigation(url(path), 'navigate')).toBe(true)
	})

	it.each([
		['public share password form', '/index.php/apps/collectives/p/abc123/authenticate/showshare'],
		['public share password form with pretty URLs', '/apps/collectives/p/abc123/authenticate/showshare'],
		['other app', '/index.php/apps/files/'],
	])('does not cache navigation to %s', (_, path) => {
		expect(isCacheableNavigation(url(path), 'navigate')).toBe(false)
	})

	it('ignores requests that are not navigations', () => {
		expect(isCacheableNavigation(url('/index.php/apps/collectives/'), 'cors')).toBe(false)
	})
})

describe('navigationCacheKey', () => {
	it.each([
		['/index.php/apps/collectives/'],
		['/apps/collectives'],
		['/index.php/apps/collectives/Garden-12/Roses-34'],
	])('uses one key for all app routes (%s)', (path) => {
		expect(navigationCacheKey(url(path))).toBe('/apps/collectives')
	})

	it.each([
		['/index.php/apps/collectives/p/abc123'],
		['/index.php/apps/collectives/p/abc123/'],
		['/apps/collectives/p/abc123/Garden/Roses'],
	])('uses one key per public share (%s)', (path) => {
		expect(navigationCacheKey(url(path))).toBe('/apps/collectives/p/abc123')
	})

	it('separates different public shares', () => {
		expect(navigationCacheKey(url('/index.php/apps/collectives/p/abc123/')))
			.not.toBe(navigationCacheKey(url('/index.php/apps/collectives/p/def456/')))
	})
})
