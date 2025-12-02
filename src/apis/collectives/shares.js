/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * URL for the collective shares API
 *
 * @param {number} collectiveId - ID of the collective
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function collectiveSharesApiUrl(collectiveId, ...parts) {
	return apiUrl('v1.0', 'collectives', collectiveId, 'shares', ...parts)
}

/**
 * URL for the page shares API
 *
 * @param {number} collectiveId - ID of the collective
 * @param {number} pageId - ID of the page
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function pageSharesApiUrl(collectiveId, pageId, ...parts) {
	return apiUrl('v1.0', 'collectives', collectiveId, 'pages', pageId, 'shares', ...parts)
}

/**
 * Url of a share
 *
 * @param {object} share Share to update
 * @param {number} share.collectiveId Id of the collective
 * @param {number} share.pageId Id of the collective
 * @param {string} share.token Token of the share to be updated
 */
function shareUrl({ collectiveId, pageId, token }) {
	return pageId
		? pageSharesApiUrl(collectiveId, pageId, token)
		: collectiveSharesApiUrl(collectiveId, token)
}

/**
 * Get shares of a collective and its pages
 *
 * @param {number} collectiveId Id of the collective
 */
export function getShares(collectiveId) {
	return axios.get(collectiveSharesApiUrl(collectiveId))
}

/**
 * Create a public collective share
 *
 * @param {number} collectiveId Id of the collective to be shared
 * @param {string} password Optional password for the share
 */
export function createCollectiveShare(collectiveId, password) {
	return axios.post(
		collectiveSharesApiUrl(collectiveId),
		{ password },
	)
}

/**
 * Create a public page share
 *
 * @param {number} collectiveId Id of the collective the page belongs to
 * @param {number} pageId Id of the page to be shared
 * @param {string} password Optional password for the share
 */
export function createPageShare(collectiveId, pageId, password) {
	return axios.post(
		pageSharesApiUrl(collectiveId, pageId),
		{ password },
	)
}

/**
 * Update a public collective share
 *
 * @param {object} share Share to update
 * @param {number} share.collectiveId Id of the collective
 * @param {number} share.pageId Id of the collective
 * @param {string} share.token Token of the share to be updated
 * @param {boolean} share.editable editable state to set
 * @param {boolean} share.password optional password for the share
 */
export function updateShare(share) {
	return axios.put(
		shareUrl(share),
		{ editable: share.editable, password: share.password ?? '' },
	)
}

/**
 * Delete a public collective share
 *
 * @param {object} share Share to update
 * @param {number} share.collectiveId Id of the collective
 * @param {number} share.pageId Id of the collective
 * @param {string} share.token Token of the share to be updated
 */
export function deleteShare(share) {
	return axios.delete(shareUrl(share))
}
