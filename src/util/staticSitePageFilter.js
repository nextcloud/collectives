/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Pages to show in a page tree filtered by title: the matching pages and their ancestors.
 *
 * @param {Array<{id: number, parentId: number, title: string}>} pages pages of the collective
 * @param {string} filter text to search for in the page titles, case-insensitive
 * @return {?Set<number>} IDs of the pages to show, null if the filter is empty
 */
export function pageIdsMatchingFilter(pages, filter) {
	const query = filter.trim().toLowerCase()
	if (!query) {
		return null
	}

	const parentIds = new Map(pages.map((page) => [page.id, page.parentId]))
	const pageIds = new Set()
	for (const page of pages) {
		if (!page.title.toLowerCase().includes(query)) {
			continue
		}
		let id = page.id
		while (id && !pageIds.has(id)) {
			pageIds.add(id)
			id = parentIds.get(id)
		}
	}
	return pageIds
}
