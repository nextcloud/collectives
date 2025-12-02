/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * URL for the collectives API
 *
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function collectivesApiUrl(...parts) {
	return apiUrl('v1.0', 'collectives', ...parts)
}

/**
 * URL for the public collectives API
 *
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function publicCollectivesApiUrl(...parts) {
	return apiUrl('v1.0', 'p', 'collectives', ...parts)
}

/**
 * URL for the collectives trash API
 *
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function collectivesTrashApiUrl(...parts) {
	return collectivesApiUrl('trash', ...parts)
}

/**
 * Get all active (i.e. not trashed) collectives for the current user
 */
export function getCollectives() {
	return axios.get(collectivesApiUrl())
}

/**
 * Get the shared collective for a given share token.
 *
 * @param {string} shareToken authentication token from the share
 */
export function getSharedCollective(shareToken) {
	return axios.get(publicCollectivesApiUrl(shareToken))
}

/**
 * Get all trashed collectives for the current user
 */
export function getTrashCollectives() {
	return axios.get(collectivesTrashApiUrl())
}

/**
 * Create a new collective with the given properties.
 *
 * @param {object} collective - properties for the new collective
 */
export function newCollective(collective) {
	return axios.post(
		collectivesApiUrl(),
		collective,
	)
}

/**
 * Trash the collective with the given id
 *
 * @param {number} collectiveId - Id of the collective to trash.
 */
export function trashCollective(collectiveId) {
	return axios.delete(collectivesApiUrl(collectiveId))
}

/**
 * Delete the collective with the given id.
 *
 * @param {number} collectiveId - id of the collective to delete
 * @param {boolean} removeCircle - also remove the circle if true
 */
export function deleteCollective(collectiveId, removeCircle) {
	const params = removeCircle ? { circle: 1 } : {}
	return axios.delete(collectivesTrashApiUrl(collectiveId), {
		params,
	})
}

/**
 * Restore a collective with the given id from trash
 *
 * @param {number} collectiveId Id of the colletive to be restored
 */
export function restoreCollective(collectiveId) {
	return axios.patch(collectivesApiUrl('trash', collectiveId))
}

/**
 * Update a collective with the given properties
 *
 * @param {object} collective Properties for the collective
 */
export function updateCollective(collective) {
	return axios.put(
		collectivesApiUrl(collective.id),
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
		collectivesApiUrl(collectiveId, 'editLevel'),
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
		collectivesApiUrl(collectiveId, 'shareLevel'),
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
		collectivesApiUrl(collectiveId, 'pageMode'),
		{ mode },
	)
}
