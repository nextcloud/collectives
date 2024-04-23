import axios from '@nextcloud/axios'
import { collectivesUrl } from './urls.js'

/**
 * Get shares of a collective and its pages
 *
 * @param {number} collectiveId Id of the colletive
 */
export function getShares(collectiveId) {
	return axios.get(collectivesUrl(collectiveId, 'shares'))
}

/**
 * Create a public collective share
 *
 * @param {number} collectiveId Id of the colletive to be shared
 * @param {string} password Optional password for the share
 */
export function createCollectiveShare(collectiveId, password) {
	return axios.post(collectivesUrl(collectiveId, 'share'))
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
		collectivesUrl(collectiveId, '_pages', pageId, 'share'),
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
		? collectivesUrl(collectiveId, '_pages', pageId, 'share', token)
		: collectivesUrl(collectiveId, 'share', token)
}
