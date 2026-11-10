/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { usePagesStore } from '../../stores/pages.js'

// The store only needs to be importable, no browser environment required for its getters
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'alice' }) }))
vi.mock('@nextcloud/axios', () => ({ default: {} }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path) => `/ocs/${path}`,
	generateRemoteUrl: (path) => `/remote.php/${path}`,
	generateUrl: (path) => `/${path}`,
}))
vi.mock('@vueuse/core', () => ({
	useLocalStorage: (key, initialValue) => ref(initialValue),
	useSessionStorage: (key, initialValue) => ref(initialValue),
}))
vi.mock('../../apis/collectives/index.js', () => ({}))

const collective = { id: 1 }

/**
 * Pages of a collective with the following hierarchy:
 *
 * Root (1)
 *   Child (2)
 *     Grandchild (3)
 *   Sibling (4)
 */
const pages = [
	{ id: 1, title: 'Root', parentId: 0 },
	{ id: 2, title: 'Child', parentId: 1 },
	{ id: 3, title: 'Grandchild', parentId: 2 },
	{ id: 4, title: 'Sibling', parentId: 1 },
]

describe('page ancestors', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = usePagesStore()
		store.allPages = { [collective.id]: pages }
	})

	it('walks up to the root page, closest ancestor first', () => {
		expect(store.pageAncestorIdsForCollective(collective, 3)).toEqual([2, 1])
		expect(store.pageAncestorIdsForCollective(collective, 4)).toEqual([1])
	})

	it('has no ancestors for the root page or an unknown page', () => {
		expect(store.pageAncestorIdsForCollective(collective, 1)).toEqual([])
		// A page that was deleted or moved to another collective meanwhile
		expect(store.pageAncestorIdsForCollective(collective, 999)).toEqual([])
	})

	it('has no ancestors in a collective without pages', () => {
		expect(store.pageAncestorIdsForCollective({ id: 2 }, 3)).toEqual([])
	})
})
