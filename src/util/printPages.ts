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
 * Render newly selected pages and stop loading unselected ones,
 * so the selected pages load faster
 *
 * Newly rendered pages are added to `waitingFor` until their content is loaded.
 * Unselected pages with loaded content stay rendered, so selecting them again is instant.
 *
 * @param selectedIds IDs of the selected pages
 * @param renderedIds IDs of the rendered pages, updated in place
 * @param waitingFor IDs of the rendered pages whose content is not loaded yet, updated in place
 */
export function updateRenderedPages(selectedIds: Set<number>, renderedIds: Set<number>, waitingFor: Set<number>): void {
	for (const id of selectedIds) {
		if (!renderedIds.has(id)) {
			renderedIds.add(id)
			waitingFor.add(id)
		}
	}
	for (const id of waitingFor) {
		if (!selectedIds.has(id)) {
			waitingFor.delete(id)
			renderedIds.delete(id)
		}
	}
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
