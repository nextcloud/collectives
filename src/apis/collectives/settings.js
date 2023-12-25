import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * Get collectives folder setting for the current user
 */
export function getCollectivesFolder() {
	return axios.get(apiUrl('v1.0', 'settings/user/user_folder'))
}

/**
 * Set collectives folder setting for the current user
 * @param {string} value Name of the collective folder to use
 */
export function setCollectivesFolder(value) {
	return axios.post(
		apiUrl('v1.0', 'settings/user'),
		{ key: 'user_folder', value },
	)
}
