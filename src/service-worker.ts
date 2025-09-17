/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { clientsClaim, type RouteMatchCallbackOptions } from 'workbox-core'
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching'
import { registerRoute } from 'workbox-routing'
import { NetworkFirst } from 'workbox-strategies'
import { ExpirationPlugin } from 'workbox-expiration'

declare let self: ServiceWorkerGlobalScope

// self.__WB_MANIFEST is default injection point
const manifest = self.__WB_MANIFEST

const registrationUrl = new URL(location.href)
const prefix = registrationUrl.searchParams.get('prefix')

const itemsToPrecache = manifest.map(item => {
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

// Cache for navigation requests within the vue router scope
const matchNavigateCb = ({ url, request }: RouteMatchCallbackOptions) => {
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

// Cache for assets from other apps
registerRoute(/.*\.(css|js|mjs|svg|webp)$/, new NetworkFirst({
	cacheName: 'collectives-assets',
	plugins: [
		new ExpirationPlugin({
			maxAgeSeconds: 3600 * 24 * 7, // one week
		}),
	],
}))

self.skipWaiting()
clientsClaim()
