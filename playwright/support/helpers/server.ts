/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Test server runs one of the given nextcloud versions
 *
 * @param versions to check for
 * @example hasServerVersion(32, 33)
 */
export function hasServerVersion(...versions: number[]) {
	return versions
		.map((v) => `stable${v}`)
		.includes(process.env.PLAYWRIGHT_NC_SERVER_BRANCH ?? '')
}
