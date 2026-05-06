/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { settingsGetUserSetting, settingsSetUserSetting } from '../../client/sdk.gen.ts'
import { defaultOptions, path } from './defaultOptions.ts'

const key = 'user_folder'

/**
 * Get collectives folder setting for the current user
 */
export function getCollectivesFolder() {
	settingsGetUserSetting({
		...defaultOptions,
		path: { ...path, key },
	})
}

/**
 * Set collectives folder setting for the current user
 *
 * @param value Name of the collective folder to use
 */
export function setCollectivesFolder(value: string) {
	settingsSetUserSetting({
		...defaultOptions,
		body: { key, value },
	})
}
