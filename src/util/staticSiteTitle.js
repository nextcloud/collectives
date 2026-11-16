/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const TITLE_MAX_LENGTH = 255

/**
 * @param {string} title the title to check, surrounding whitespace is ignored
 * @return {boolean} whether the title is accepted by the backend
 */
export function isValidTitle(title) {
	const length = title.trim().length
	return length > 0 && length <= TITLE_MAX_LENGTH
}
