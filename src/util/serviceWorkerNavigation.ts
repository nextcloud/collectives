/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const appPath = /(?:\/index.php)?\/apps\/collectives/
const sharePath = /\/apps\/collectives\/p\/([^/]+)/
const shareAuthenticatePath = /\/apps\/collectives\/p\/[^/]+\/authenticate(\/|$)/

/**
 * Whether the service worker caches the page for a navigation request
 *
 * Matches navigation requests within the app,
 * except for the password form of public shares.
 *
 * @param url URL of the request
 * @param mode mode of the request
 */
export function isCacheableNavigation(url: URL, mode: RequestMode): boolean {
	return mode === 'navigate'
		&& appPath.test(url.pathname)
		&& !shareAuthenticatePath.test(url.pathname)
}

/**
 * Cache key for a navigation request
 *
 * All routes of the app are served by the same page, so they share one cache entry.
 * Each public share gets its own entry, so it never replaces the app or another share.
 *
 * @param url URL of the request
 */
export function navigationCacheKey(url: URL): string {
	const shareToken = url.pathname.match(sharePath)?.[1]
	return shareToken
		? `/apps/collectives/p/${shareToken}`
		: '/apps/collectives'
}
