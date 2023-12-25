import axios from '@nextcloud/axios'
import { collectivesUrl } from './urls.js'

/**
 * Set the page order for the current user
 *
 * @param {number} collectiveId ID of the colletive to be updated
 * @param {number} pageOrder the desired page order for the current user
 */
export function setCollectiveUserSettingPageOrder(collectiveId, pageOrder) {
	return axios.put(
		collectivesUrl(collectiveId, '_userSettings', 'pageOrder'),
		{ pageOrder },
	)
}

/**
 * Set the the `show recent pages` toggle for the current user
 *
 * @param {number} collectiveId ID of the colletive to be updated
 * @param {boolean} showRecentPages the desired value
 */
export function setCollectiveUserSettingShowRecentPages(collectiveId, showRecentPages) {
	return axios.put(
		collectivesUrl(collectiveId, '_userSettings', 'showRecentPages'),
		{ showRecentPages },
	)
}
