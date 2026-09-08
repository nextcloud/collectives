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
 */
export function createStaticSite(collectiveId, pageIds) {
	return axios.post(
		staticSitesApiUrl(collectiveId),
		{ pageIds },
	)
}
