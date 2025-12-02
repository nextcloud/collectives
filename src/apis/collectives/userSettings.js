/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * URL for the collective user settings API
 *
 * @param {number} collectiveId - ID of the collective
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function collectiveUserSettingsApiUrl(collectiveId, ...parts) {
	return apiUrl('v1.0', 'collectives', collectiveId, 'userSettings', parts)
}

/**
 * Set the page order for the current user
 *
 * @param {number} collectiveId ID of the collective to be updated
 * @param {number} pageOrder the desired page order for the current user
 */
export function setCollectiveUserSettingPageOrder(collectiveId, pageOrder) {
	return axios.put(
		collectiveUserSettingsApiUrl(collectiveId, 'pageOrder'),
		{ pageOrder },
	)
}

/**
 * Set the `show members` toggle for the current user
 *
 * @param {number} collectiveId ID of the collective to be updated
 * @param {boolean} showMembers the desired value
 */
export function setCollectiveUserSettingShowMembers(collectiveId, showMembers) {
	return axios.put(
		collectiveUserSettingsApiUrl(collectiveId, 'showMembers'),
		{ showMembers },
	)
}

/**
 * Set the `show recent pages` toggle for the current user
 *
 * @param {number} collectiveId ID of the collective to be updated
 * @param {boolean} showRecentPages the desired value
 */
export function setCollectiveUserSettingShowRecentPages(collectiveId, showRecentPages) {
	return axios.put(
		collectiveUserSettingsApiUrl(collectiveId, 'showRecentPages'),
		{ showRecentPages },
	)
}

/**
 * Set favorite pages for the current user
 *
 * @param {number} collectiveId ID of the collective to be updated
 * @param {Array} favoritePages the desired value
 */
export function setCollectiveUserSettingFavoritePages(collectiveId, favoritePages) {
	return axios.put(
		collectiveUserSettingsApiUrl(collectiveId, 'favoritePages'),
		{ favoritePages: JSON.stringify(favoritePages) },
	)
}
