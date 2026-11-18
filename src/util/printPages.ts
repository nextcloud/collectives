/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface PrintPagesQuery {
	pages?: string
	exclude?: string
}

/**
 * Query parameters for the print view to print the selected pages
 *
 * Uses the shorter of the selected (`pages`) and the unselected (`exclude`) page IDs
 * to keep the URL short in large collectives.
 *
 * @param pageIds IDs of all pages
 * @param selectedIds IDs of the selected pages
 */
export function printPagesQuery(pageIds: number[], selectedIds: Set<number>): PrintPagesQuery {
	const included = pageIds.filter((id) => selectedIds.has(id))
	const excluded = pageIds.filter((id) => !selectedIds.has(id))
	if (excluded.length === 0) {
		return {}
	}
	return included.length <= excluded.length
		? { pages: included.join(',') }
		: { exclude: excluded.join(',') }
}

/**
 * Filter pages according to the `pages` or `exclude` query parameter
 *
 * @param pages Pages to filter
 * @param query Route query with comma-separated page IDs in `pages` or `exclude`
 */
export function filterPrintPages<T extends { id: number }>(pages: T[], query: Record<string, unknown>): T[] {
	const toIds = (value: unknown) => new Set(String(value).split(',').map(Number))
	if (query.pages) {
		const pageIds = toIds(query.pages)
		return pages.filter((page) => pageIds.has(page.id))
	}
	if (query.exclude) {
		const pageIds = toIds(query.exclude)
		return pages.filter((page) => !pageIds.has(page.id))
	}
	return pages
}
