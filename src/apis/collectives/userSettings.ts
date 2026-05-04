/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	collectiveUserSettingsSetFavoritePages,
	collectiveUserSettingsSetPageOrder,
	collectiveUserSettingsSetShowMembers,
	collectiveUserSettingsSetShowRecentPages,
} from '../../client/sdk.gen.js'
import { defaultOptions, path } from './defaultOptions.js'

/**
 * Set the page order for the current user
 *
 * @param collectiveId ID of the collective to be updated
 * @param pageOrder the desired page order for the current user
 */
export function setCollectiveUserSettingPageOrder(collectiveId: number, pageOrder: number) {
	return collectiveUserSettingsSetPageOrder({
		...defaultOptions,
		path: { ...path, collectiveId },
		body: { pageOrder },
	})
}

/**
 * Set the `show members` toggle for the current user
 *
 * @param collectiveId ID of the collective to be updated
 * @param showMembers the desired value
 */
export function setCollectiveUserSettingShowMembers(collectiveId: number, showMembers: boolean) {
	return collectiveUserSettingsSetShowMembers({
		...defaultOptions,
		path: { ...path, collectiveId },
		body: { showMembers },
	})
}

/**
 * Set the `show recent pages` toggle for the current user
 *
 * @param collectiveId ID of the collective to be updated
 * @param showRecentPages the desired value
 */
export function setCollectiveUserSettingShowRecentPages(collectiveId: number, showRecentPages: boolean) {
	return collectiveUserSettingsSetShowRecentPages({
		...defaultOptions,
		path: { ...path, collectiveId },
		body: { showRecentPages },
	})
}

/**
 * Set favorite pages for the current user
 *
 * @param collectiveId ID of the collective to be updated
 * @param favoritePages the desired value
 */
export function setCollectiveUserSettingFavoritePages(collectiveId: number, favoritePages: string) {
	return collectiveUserSettingsSetFavoritePages({
		...defaultOptions,
		path: { ...path, collectiveId },
		body: { favoritePages },
	})
}
