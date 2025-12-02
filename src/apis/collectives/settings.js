/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * URL for the settings API
 *
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function settingsApiUrl(...parts) {
	return apiUrl('v1.0', 'settings', parts)
}
/**
 * Get collectives folder setting for the current user
 */
export function getCollectivesFolder() {
	return axios.get(settingsApiUrl('user/user_folder'))
}

/**
 * Set collectives folder setting for the current user
 *
 * @param {string} value Name of the collective folder to use
 */
export function setCollectivesFolder(value) {
	return axios.post(
		settingsApiUrl('user'),
		{ key: 'user_folder', value },
	)
}
