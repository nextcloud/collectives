/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * URL for the collective session API
 *
 * @param {number} collectiveId - ID of the collective
 */
function sessionApiUrl(collectiveId) {
	return apiUrl('v1.0', 'collectives', collectiveId, 'sessions')
}

/**
 * Create a new session for the current user
 *
 * @param {number} collectiveId - ID of the collective
 */
export function createSession(collectiveId) {
	return axios.post(sessionApiUrl(collectiveId))
}

/**
 * Update an existing session for the current user
 *
 * @param {number} collectiveId - ID of the collective
 * @param {string} token - Session token
 */
export function updateSession(collectiveId, token) {
	return axios.put(sessionApiUrl(collectiveId), { token })
}

/**
 * Close a session for the current user
 *
 * @param {number} collectiveId - ID of the collective
 * @param {string} token - Session token
 */
export function closeSession(collectiveId, token) {
	return axios.delete(sessionApiUrl(collectiveId), {
		params: { token },
	})
}
