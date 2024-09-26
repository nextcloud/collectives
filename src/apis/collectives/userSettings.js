/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { collectivesUrl } from './urls.js'

/**
 * Set the page order for the current user
 *
 * @param {number} collectiveId ID of the collective to be updated
 * @param {number} pageOrder the desired page order for the current user
 */
export function setCollectiveUserSettingPageOrder(collectiveId, pageOrder) {
	return axios.put(
		collectivesUrl(collectiveId, '_userSettings', 'pageOrder'),
		{ pageOrder },
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
		collectivesUrl(collectiveId, '_userSettings', 'showRecentPages'),
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
		collectivesUrl(collectiveId, '_userSettings', 'favoritePages'),
		{ favoritePages: JSON.stringify(favoritePages) },
	)
}
