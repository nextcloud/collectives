/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import { isValidTitle, TITLE_MAX_LENGTH } from '../../util/staticSiteTitle.js'

describe('isValidTitle', () => {
	test.each([
		'My website',
		' My website ',
		'a'.repeat(TITLE_MAX_LENGTH),
	])('accepts %j', (title) => {
		expect(isValidTitle(title)).toBe(true)
	})

	test.each([
		'',
		'   ',
		'a'.repeat(TITLE_MAX_LENGTH + 1),
	])('rejects %j', (title) => {
		expect(isValidTitle(title)).toBe(false)
	})
})
