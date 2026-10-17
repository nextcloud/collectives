/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const SLUG_MAX_LENGTH = 64

const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/

/**
 * Suggest a static site slug based on a name
 *
 * @param {string} name the name to derive the slug from
 * @return {string} lowercase ASCII letters and numbers, separated by single hyphens
 */
export function generateSlug(name) {
	return name
		.normalize('NFKD')
		.replace(/\p{Diacritic}/gu, '')
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '')
		.slice(0, SLUG_MAX_LENGTH)
		.replace(/-+$/, '')
}

/**
 * @param {string} slug the slug to check
 * @return {boolean} whether the slug is accepted by the backend
 */
export function isValidSlug(slug) {
	return slug.length <= SLUG_MAX_LENGTH && SLUG_PATTERN.test(slug)
}
