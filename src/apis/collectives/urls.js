/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateOcsUrl } from '@nextcloud/router'

/**
 * URL for the versioned collectives API
 *
 * @param {string} version - Version of the API - currently `v1.0`
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
export function apiUrl(version, ...parts) {
	const path = ['apps/collectives/api', version, ...parts]
		.join('/')
	return generateOcsUrl(path)
}
