/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Generate OCS API URL for collectives
 * We cannot use apiURL() from the app as it relies on browser APIs (window)
 *
 * @param version - Version of the API - currently `v1.0`
 * @param parts - URL parts to append - will be joined with `/`
 */
export function apiUrl(version: string, ...parts: (string | number)[]): string {
	const path = ['apps/collectives/api', version, ...parts]
		.join('/')
	return `/ocs/v2.php/${path}`
}
