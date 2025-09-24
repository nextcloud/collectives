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
	const { Workbox } = await import('workbox-window')
	const url = generateServiceWorkerUrl(app)
	const scope = generateUrl(`/apps/${app}`)
	const wb = new Workbox(url, { scope });
	wb.addEventListener('activated', (event) => {
		if (event.isUpdate || event.isExternal)
			console.info('A new collectives version is available.')
			// window.location.reload()
	})

	try {
		wb.register()
		console.debug('SW registered.')
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
