/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
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
	const wb = new Workbox(url, { scope })
	wb.addEventListener('activated', (event) => {
		if (event.isUpdate) {
			console.info('[SW] A new collectives version is available, reloading.')
			window.location.reload()
		} else if (event.isExternal) {
			console.info('[SW] A new collectives version is available, please reload.')
			showError(t('collectives', 'Nextcloud Collectives was updated.') + '\n' + t('collectives', 'Please reload the page.'), { timeout: TOAST_PERMANENT_TIMEOUT })
			// TODO: ask user to reload page
		}
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
