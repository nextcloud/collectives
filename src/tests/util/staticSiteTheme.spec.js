/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import { isValidTheme, THEME_TOPNAV, THEME_WIKI } from '../../util/staticSiteTheme.js'

describe('isValidTheme', () => {
	test.each([
		THEME_WIKI,
		THEME_TOPNAV,
	])('accepts %j', (theme) => {
		expect(isValidTheme(theme)).toBe(true)
	})

	test.each([
		'',
		'Wiki',
		'topbar',
	])('rejects %j', (theme) => {
		expect(isValidTheme(theme)).toBe(false)
	})
})
