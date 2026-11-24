/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const THEME_WIKI = 'wiki'
export const THEME_TOPNAV = 'topnav'

/**
 * @param {string} theme the theme to check
 * @return {boolean} whether the theme is accepted by the backend
 */
export function isValidTheme(theme) {
	return theme === THEME_WIKI || theme === THEME_TOPNAV
}
