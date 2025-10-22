/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	type RouteMatchCallbackOptions,

	clientsClaim,
} from 'workbox-core'
import { ExpirationPlugin } from 'workbox-expiration'
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching'
import { registerRoute } from 'workbox-routing'
import { NetworkFirst } from 'workbox-strategies'

declare let self: ServiceWorkerGlobalScope

// self.__WB_MANIFEST is default injection point
const manifest = self.__WB_MANIFEST

const registrationUrl = new URL(location.href)
const prefix = registrationUrl.searchParams.get('prefix')

const itemsToPrecache = manifest.map((item) => {
	if (typeof item === 'string') {
		return prefix + item
	} else {
		item.url = prefix + item.url
		return item
	}
})

precacheAndRoute(itemsToPrecache)

// clean old assets
cleanupOutdatedCaches()

/**
 * Route match callback function for `collectives-vue-router`
 * Matches navigation requests within the vue router scope
 *
 * @param matchOptions options
 * @param matchOptions.url URL to match
 * @param matchOptions.request request to match
 */
function matchNavigateCb({ url, request }: RouteMatchCallbackOptions) {
	return request.mode === 'navigate' && url.pathname.match(/(?:\/index.php)?\/apps\/collectives/)
}
registerRoute(matchNavigateCb, new NetworkFirst({
	cacheName: 'collectives-vue-router',
	plugins: [
		new ExpirationPlugin({
			maxAgeSeconds: 3600 * 24 * 7, // one week
		}),
		{
			cacheKeyWillBeUsed: async () => '/apps/collectives',
		},
	],
}))

/**
 * Route match callback function for `collectives-assets`
 * Matches static assets, theming app icons and avatars
 *
 * @param matchOptions options
 * @param matchOptions.url URL to match
 */
function matchAssetsCb({ url }: RouteMatchCallbackOptions) {
	return url.pathname.match(/.*\.(css|js|mjs|svg|webp)($|\?)/)
		|| url.pathname.match(/\/apps\/text\/(image|mediaPreview)\?/)
		|| url.pathname.match(/\/apps\/theming\/((fav)?icon|manifest)\//)
		|| url.pathname.match(/\/avatar\/[^/]+\/[0-9]+$/)
}

// Cache for assets from other apps
registerRoute(matchAssetsCb, new NetworkFirst({
	cacheName: 'collectives-assets',
	plugins: [
		new ExpirationPlugin({
			maxAgeSeconds: 3600 * 24 * 7, // one week
		}),
	],
}))

self.skipWaiting()
clientsClaim()
