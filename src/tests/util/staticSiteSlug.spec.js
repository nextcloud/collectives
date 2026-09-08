/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import { generateSlug, isValidSlug } from '../../util/staticSiteSlug.js'

describe('generateSlug', () => {
	test.each([
		['My Collective', 'my-collective'],
		['Über uns & Café', 'uber-uns-cafe'],
		['  --Team 2026--  ', 'team-2026'],
		['🎉 Party 🎉', 'party'],
		['🎉', ''],
	])('%s -> %s', (name, slug) => {
		expect(generateSlug(name)).toBe(slug)
	})

	test('truncates to 64 characters without trailing hyphen', () => {
		const slug = generateSlug('a'.repeat(63) + ' b')
		expect(slug).toBe('a'.repeat(63))
	})

	test('generated slugs are valid', () => {
		expect(isValidSlug(generateSlug('Some Name 42'))).toBe(true)
	})
})

describe('isValidSlug', () => {
	test.each(['abc', 'a-b-c', 'team-2026'])('accepts %s', (slug) => {
		expect(isValidSlug(slug)).toBe(true)
	})

	test.each(['', 'My-Site', 'my site', 'my_site', 'über', '-abc', 'abc-', 'a--b', 'a'.repeat(65)])('rejects %s', (slug) => {
		expect(isValidSlug(slug)).toBe(false)
	})
})
