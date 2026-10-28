/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { filterPrintPages, printPagesQuery } from '../../util/printPages.ts'

const pageIds = [1, 2, 3, 4, 5]
const pages = pageIds.map((id) => ({ id }))

describe('printPagesQuery', () => {
	it('is empty if all pages are selected', () => {
		expect(printPagesQuery(pageIds, new Set(pageIds))).toEqual({})
	})

	it('lists the selected pages if fewer than the unselected', () => {
		expect(printPagesQuery(pageIds, new Set([2, 4]))).toEqual({ pages: '2,4' })
	})

	it('lists the unselected pages if fewer than the selected', () => {
		expect(printPagesQuery(pageIds, new Set([1, 2, 3, 5]))).toEqual({ exclude: '4' })
	})

	it('ignores selected IDs that are not in the page list', () => {
		expect(printPagesQuery(pageIds, new Set([...pageIds, 99]))).toEqual({})
	})
})

describe('filterPrintPages', () => {
	it('returns all pages without query', () => {
		expect(filterPrintPages(pages, {})).toEqual(pages)
	})

	it('keeps the pages in `pages`', () => {
		expect(filterPrintPages(pages, { pages: '2,4' })).toEqual([{ id: 2 }, { id: 4 }])
	})

	it('drops the pages in `exclude`', () => {
		expect(filterPrintPages(pages, { exclude: '4' })).toEqual([{ id: 1 }, { id: 2 }, { id: 3 }, { id: 5 }])
	})

	it('returns no pages for unknown IDs', () => {
		expect(filterPrintPages(pages, { pages: 'abc,99' })).toEqual([])
	})

	it('round-trips with printPagesQuery', () => {
		const selected = new Set([1, 3, 5])
		expect(filterPrintPages(pages, { ...printPagesQuery(pageIds, selected) }).map((p) => p.id))
			.toEqual([1, 3, 5])
	})
})
