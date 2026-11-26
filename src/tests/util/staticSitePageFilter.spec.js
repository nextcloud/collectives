/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import { pageIdsMatchingFilter } from '../../util/staticSitePageFilter.js'

const pages = [
	{ id: 1, parentId: 0, title: '' },
	{ id: 2, parentId: 1, title: 'Meetings' },
	{ id: 3, parentId: 2, title: 'Weekly notes' },
	{ id: 4, parentId: 2, title: 'Retro' },
	{ id: 5, parentId: 1, title: 'Notes' },
	{ id: 6, parentId: 5, title: 'Drafts' },
]

describe('pageIdsMatchingFilter', () => {
	test.each(['', '   '])('returns null for the empty filter %j', (filter) => {
		expect(pageIdsMatchingFilter(pages, filter)).toBeNull()
	})

	test('returns matching pages with their ancestors, but without their subpages', () => {
		expect(pageIdsMatchingFilter(pages, 'notes')).toEqual(new Set([1, 2, 3, 5]))
	})

	test('ignores case and surrounding whitespace', () => {
		expect(pageIdsMatchingFilter(pages, ' RETRO ')).toEqual(new Set([1, 2, 4]))
	})

	test('returns an empty set if no page matches', () => {
		expect(pageIdsMatchingFilter(pages, 'missing')).toEqual(new Set())
	})
})
