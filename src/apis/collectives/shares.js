/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * URL for the shares API
 *
 * @param {number} collectiveId - ID of the collective
 * @param {...any} parts - URL parts to append - will be joined with `/`
 */
function collectiveSharesApiUrl(collectiveId, ...parts) {
	console.debug('parts', ...parts)
	return apiUrl('v1.0', 'shares', collectiveId, ...parts)
}

/**
 * Url of a share
 *
 * @param {object} share Share to update
 * @param {number} share.collectiveId Id of the colletive
 * @param {number} share.pageId Id of the colletive
 * @param {string} share.token Token of the share to be updated
 */
function shareUrl({ collectiveId, pageId, token }) {
	return pageId
		? collectiveSharesApiUrl(collectiveId, 'pages', pageId, token)
		: collectiveSharesApiUrl(collectiveId, token)
}

/**
 * Get shares of a collective and its pages
 *
 * @param {number} collectiveId Id of the colletive
 */
export function getShares(collectiveId) {
	return axios.get(collectiveSharesApiUrl(collectiveId))
}

/**
 * Create a public collective share
 *
 * @param {number} collectiveId Id of the colletive to be shared
 * @param {string} password Optional password for the share
 */
export function createCollectiveShare(collectiveId, password) {
	return axios.post(collectiveSharesApiUrl(collectiveId))
}

/**
 * Create a public page share
 *
 * @param {number} collectiveId Id of the colletive the page belongs to
 * @param {number} pageId Id of the page to be shared
 * @param {string} password Optional password for the share
 */
export function createPageShare(collectiveId, pageId, password) {
	return axios.post(
		collectiveSharesApiUrl(collectiveId, 'pages', pageId),
		{ password },
	)
}

/**
 * Update a public collective share
 *
 * @param {object} share Share to update
 * @param {number} share.collectiveId Id of the colletive
 * @param {number} share.pageId Id of the colletive
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
 * @param {number} share.collectiveId Id of the colletive
 * @param {number} share.pageId Id of the colletive
 * @param {string} share.token Token of the share to be updated
 */
export function deleteShare(share) {
	return axios.delete(
		shareUrl(share),
	)
}
