/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl, getAppRootUrl } from '@nextcloud/router'

/**
 * Register the service worker providing the apps prefix.
 *
 * @param app name of the app.
 */
export default async function(app = 'collectives') {
	const scope = generateUrl(`/apps/${app}`)
	const url = generateServiceWorkerUrl(app)
	try {
		const registration = await navigator.serviceWorker.register(url, { scope })
		console.debug('SW registered: ', { registration })
	} catch (registrationError) {
		console.error('SW registration failed: ', { registrationError })
	}
}

/**
 * Generate the url for the service worker including the prefix for js files.
 *
 * @param app name of the app.
 */
function generateServiceWorkerUrl(app: string) {
	const route = 'service-worker.js'
	const prefix = getAppRootUrl(app) + '/'
	return generateUrl(
		`/apps/${app}/${route}?prefix=${prefix}`,
		{},
		{ noRewrite: true },
	)
}
