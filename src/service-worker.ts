/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { clientsClaim } from 'workbox-core'
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching'

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

self.skipWaiting()
clientsClaim()
