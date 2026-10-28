/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

interface TreePage {
	id: number
	parentId: number
	subpageOrder?: number[]
}

// Position in the parent's `subpageOrder`, -1 if not in there (used by `byOrder` sort)
export type IndexedPage<T> = T & { index: number }

export interface PageTreeNode<T> {
	page: IndexedPage<T>
	children: PageTreeNode<T>[]
}

/**
 * Find the root page of a collective
 *
 * In page shares, the shared page is the root and comes first. It keeps its original parent.
 *
 * @param pages All pages of the collective
 * @param isPageShare Whether the pages belong to a page share
 */
export function findRootPage<T extends TreePage>(pages: T[], isPageShare: boolean): T | undefined {
	return isPageShare
		? pages[0]
		: pages.find((p) => p.parentId === 0)
}

/**
 * Build the page tree below `rootParentId`, with subpages sorted by `compare`
 *
 * Groups the pages by parent once, so it scales with large collectives.
 *
 * @param pages All pages of the collective
 * @param rootParentId Parent ID of the top level pages in the tree
 * @param compare Sort function for the subpages of each page
 */
export function buildPageTree<T extends TreePage>(
	pages: T[],
	rootParentId: number,
	compare: (a: IndexedPage<T>, b: IndexedPage<T>) => number,
): PageTreeNode<T>[] {
	const pagesById = new Map(pages.map((p) => [p.id, p]))
	const pagesByParentId = new Map<number, T[]>()
	for (const page of pages) {
		const siblings = pagesByParentId.get(page.parentId)
		if (siblings) {
			siblings.push(page)
		} else {
			pagesByParentId.set(page.parentId, [page])
		}
	}

	const build = (parentId: number): PageTreeNode<T>[] => {
		const customOrder = new Map((pagesById.get(parentId)?.subpageOrder ?? []).map((id, i) => [id, i]))
		return (pagesByParentId.get(parentId) ?? [])
			.map((p) => ({ ...p, index: customOrder.get(p.id) ?? -1 }))
			.sort(compare)
			.map((page) => ({ page, children: build(page.id) }))
	}

	return build(rootParentId)
}

/**
 * Flatten a page tree into a list of pages in tree order (depth first)
 *
 * @param nodes Page tree
 */
export function flattenPageTree<T>(nodes: PageTreeNode<T>[]): IndexedPage<T>[] {
	return nodes.flatMap((node) => [node.page, ...flattenPageTree(node.children)])
}
