/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import { isValidSlug } from '../../util/staticSiteSlug.js'

describe('isValidSlug', () => {
	test.each(['abc', 'a-b-c', 'team-2026'])('accepts %s', (slug) => {
		expect(isValidSlug(slug)).toBe(true)
	})

	test.each(['', 'My-Site', 'my site', 'my_site', 'über', '-abc', 'abc-', 'a--b', 'a'.repeat(65)])('rejects %s', (slug) => {
		expect(isValidSlug(slug)).toBe(false)
	})
})
