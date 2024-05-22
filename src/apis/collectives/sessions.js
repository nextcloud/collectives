import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * Create a new session for the current user
 *
 * @param {number} collectiveId - ID of the collective
 */
export function createSession(collectiveId) {
	return axios.post(apiUrl('v1.0', `session/${collectiveId}`))
}

/**
 * Update an existing session for the current user
 *
 * @param {number} collectiveId - ID of the collective
 * @param {string} token - Session token
 */
export function updateSession(collectiveId, token) {
	return axios.put(apiUrl('v1.0', `session/${collectiveId}`), { token })
}

/**
 * Close a session for the current user
 *
 * @param {number} collectiveId - ID of the collective
 * @param {string} token - Session token
 */
export function closeSession(collectiveId, token) {
	return axios.delete(apiUrl('v1.0', `session/${collectiveId}?token=${token}`))
}
