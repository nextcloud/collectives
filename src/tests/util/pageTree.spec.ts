/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { IndexedPage } from '../../util/pageTree.ts'

import { describe, expect, it } from 'vitest'
import { buildPageTree, findRootPage, flattenPageTree } from '../../util/pageTree.ts'

interface TestPage {
	id: number
	parentId: number
	subpageOrder?: number[]
}

// Pages in `subpageOrder` first, others by ID
function byIndexThenId(a: IndexedPage<TestPage>, b: IndexedPage<TestPage>) {
	const indexA = a.index === -1 ? Infinity : a.index
	const indexB = b.index === -1 ? Infinity : b.index
	return (indexA - indexB) || (a.id - b.id)
}

const pages: TestPage[] = [
	{ id: 1, parentId: 0, subpageOrder: [3, 2] },
	{ id: 2, parentId: 1, subpageOrder: [] },
	{ id: 3, parentId: 1 },
	{ id: 4, parentId: 2 },
	{ id: 5, parentId: 1 },
]

describe('findRootPage', () => {
	it('finds the page without parent', () => {
		expect(findRootPage([pages[1], pages[0]], false)).toBe(pages[0])
	})

	it('uses the first page in page shares', () => {
		expect(findRootPage([pages[1], pages[3]], true)).toBe(pages[1])
	})

	it('returns undefined without pages', () => {
		expect(findRootPage([], false)).toBeUndefined()
	})
})

describe('buildPageTree', () => {
	it('builds the nested tree sorted by subpage order', () => {
		const tree = buildPageTree(pages, 0, byIndexThenId)
		const ids = (nodes: typeof tree): unknown[] => nodes.map((n) => [n.page.id, ids(n.children)])
		expect(ids(tree)).toEqual([[1, [[3, []], [2, [[4, []]]], [5, []]]]])
	})

	it('adds the index in the parent subpage order', () => {
		const [root] = buildPageTree(pages, 0, byIndexThenId)
		expect(root!.children.map((n) => n.page.index)).toEqual([0, 1, -1])
	})

	it('starts at the given parent', () => {
		const tree = buildPageTree(pages, 1, byIndexThenId)
		expect(tree.map((n) => n.page.id)).toEqual([3, 2, 5])
	})

	it('returns an empty tree without pages', () => {
		expect(buildPageTree([], 0, byIndexThenId)).toEqual([])
	})
})

describe('flattenPageTree', () => {
	it('lists pages depth first', () => {
		const flat = flattenPageTree(buildPageTree(pages, 0, byIndexThenId))
		expect(flat.map((p) => p.id)).toEqual([1, 3, 2, 4, 5])
	})
})
