import axios from '@nextcloud/axios'
import { collectivesUrl } from './urls.js'

/**
 * Get all active (i.e. not trashed) collectives for the current user
 */
export function getCollectives() {
	return axios.get(collectivesUrl())
}

/**
 * Get the shared collective for a given share token.
 *
 * @param {string} shareToken authentication token from the share
 */
export function getSharedCollective(shareToken) {
	return axios.get(collectivesUrl('p', shareToken))
}

/**
 * Get all trashed collectives for the current user
 */
export function getTrashCollectives() {
	return axios.get(collectivesUrl('trash'))
}

/**
 * Create a new collective with the given properties.
 *
 * @param {object} collective - properties for the new collective
 */
export function newCollective(collective) {
	return axios.post(
		collectivesUrl(),
		collective,
	)
}

/**
 * Trash the collective with the given id
 *
 * @param {number} collectiveId - Id of the collective to trash.
 */
export function trashCollective(collectiveId) {
	return axios.delete(collectivesUrl(collectiveId))
}

/**
 * Delete the collective with the given id.
 *
 * @param {number} collectiveId - id of the collective to delete
 * @param {boolean} removeCircle - also remove the circle if true
 */
export function deleteCollective(collectiveId, removeCircle) {
	const query = removeCircle ? '?circle=1' : ''
	return axios.delete(collectivesUrl('trash', collectiveId + query))
}

/**
 * Restore a collective with the given id from trash
 *
 * @param {number} collectiveId Id of the colletive to be restored
 */
export function restoreCollective(collectiveId) {
	return axios.patch(collectivesUrl('trash', collectiveId))
}

/**
 * Update a collective with the given properties
 *
 * @param {object} collective Properties for the collective
 */
export function updateCollective(collective) {
	return axios.put(
		collectivesUrl(collective.id),
		collective,
	)
}

/**
 * Set the permission level required for editing.
 *
 * @param {number} collectiveId - id of the collective to update
 * @param {number} level - required level for editing
 */
export function updateCollectiveEditPermissions(collectiveId, level) {
	return axios.put(
		collectivesUrl(collectiveId, 'editLevel'),
		{ level },
	)
}

/**
 * Set the permission level required for sharing.
 *
 * @param {number} collectiveId - id of the collective to update
 * @param {number} level - required level for sharing
 */
export function updateCollectiveSharePermissions(collectiveId, level) {
	return axios.put(
		collectivesUrl(collectiveId, 'shareLevel'),
		{ level },
	)
}

/**
 * Set the edit mode for the given collective
 *
 * @param {number} collectiveId - id of the collective to update
 * @param {number} mode - pageMode to use.
 *
 * Possible modes: pageModes.MODE_VIEW or pageModes.MODE_EDIT
 */
export function updateCollectivePageMode(collectiveId, mode) {
	return axios.put(
		collectivesUrl(collectiveId, 'pageMode'),
		{ mode },
	)
}
