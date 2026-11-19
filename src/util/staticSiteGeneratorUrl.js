/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const URL_PATTERN = /^https?:\/\/\S+$/

/**
 * @param {string} url the static site generator URL to check
 * @return {boolean} whether the URL is empty (not configured) or an HTTP(S) URL
 */
export function isValidStaticSiteGeneratorUrl(url) {
	return url === '' || URL_PATTERN.test(url)
}
