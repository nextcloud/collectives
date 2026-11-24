/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * URL for the collective static sites API
 *
 * @param {number} collectiveId - ID of the collective
 * @param {...string} parts - URL parts to append - will be joined with `/`
 */
function staticSitesApiUrl(collectiveId, ...parts) {
	return apiUrl('v1.0', 'collectives', collectiveId, 'static-sites', ...parts)
}

/**
 * Get static sites of a collective
 *
 * @param {number} collectiveId Id of the collective
 */
export function getStaticSites(collectiveId) {
	return axios.get(staticSitesApiUrl(collectiveId))
}

/**
 * Start a static site export for a selection of pages of a collective
 *
 * @param {number} collectiveId Id of the collective
 * @param {number[]} pageIds Ids of the pages to publish
 * @param {string} title Title of the static site
 * @param {string} slug Slug of the static site
 * @param {string} theme Theme of the website ("wiki" or "topnav")
 */
export function createStaticSite(collectiveId, pageIds, title, slug, theme) {
	return axios.post(
		staticSitesApiUrl(collectiveId),
		{ pageIds, title, slug, theme },
	)
}

/**
 * Publish an existing static site again to update content, page-selection, title, slug and theme
 *
 * @param {number} collectiveId Id of the collective
 * @param {number} id Id of the static site
 * @param {number[]} pageIds Ids of the pages to publish
 * @param {string} title Title of the static site
 * @param {string} slug Slug of the static site, can only be changed until it got published
 * @param {string} theme Theme of the website ("wiki" or "topnav")
 */
export function updateStaticSite(collectiveId, id, pageIds, title, slug, theme) {
	return axios.put(
		staticSitesApiUrl(collectiveId, id),
		{ pageIds, title, slug, theme },
	)
}

/**
 * Check whether the static site generator is reachable with the saved settings (admin only)
 */
export function checkStaticSiteGeneratorHealth() {
	return axios.get(apiUrl('v1.0', 'static-site-generator', 'health'))
}
