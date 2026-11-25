/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const SLUG_MAX_LENGTH = 64

// TODO(Publish): Use the base URL configured in the admin settings
export const STATIC_SITE_ADDRESS_PREFIX = 'https://example.com/sites/'

const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/

/**
 * @param {string} slug the slug to check
 * @return {boolean} whether the slug is accepted by the backend
 */
export function isValidSlug(slug) {
	return slug.length <= SLUG_MAX_LENGTH && SLUG_PATTERN.test(slug)
}
