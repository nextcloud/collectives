/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 *
 * @param {string} url - URL to parse
 */
function getSearchParams(url) {
	return url
		.split(/[?&]/)
		.reduce((acc, cur) => {
			const parts = cur.split('=')
			parts[1] && (acc[parts[0]] = parts[1])
			return acc
		}, {})
}

const randHash = () => Math.random().toString(36).replace(/[^a-z]+/g, '').substr(0, 10)

export default { getSearchParams, randHash }
