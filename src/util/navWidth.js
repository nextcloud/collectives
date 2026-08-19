/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const NAV_WIDTH_DEFAULT = 300
const MIN_WIDTH = 200
const MAX_WIDTH = 500

/**
 * Clamp a page list width to the allowed range
 *
 * @param {number} width Width in px
 * @return {number} Clamped width in px
 */
export function clampNavWidth(width) {
	return Math.min(Math.max(width, MIN_WIDTH), MAX_WIDTH)
}
