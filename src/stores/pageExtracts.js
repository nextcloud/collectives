/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Functions extracted from pinia page store to be reused in other places

/* eslint import/namespace: ['error', { allowComputed: true }] */
import * as sortOrders from '../util/sortOrders.js'
import { TEMPLATE_PAGE } from '../constants.js'

/**
 * @param {object} state state of the pinia store
 */
export function sortedSubpages(state) {
	return (parentId, sortOrder) => {
		const parentPage = state.pages.find(p => p.id === parentId)
		const customOrder = parentPage?.subpageOrder || []
		return state.pages
			.filter(p => p.parentId === parentId)
			// disregard template pages, they're listed first manually
			.filter(p => p.title !== TEMPLATE_PAGE)
			// add the index from customOrder
			.map(p => ({ ...p, index: customOrder.indexOf(p.id) }))
			// sort by given order, fall back to user setting
			.sort(sortOrders[sortOrder] || state.sortOrder)
	}
}

/**
 * @param {object} state state of the pinia store
 */
export function pageParents(state) {
	return (pageId) => {
		const pages = []
		while (pageId !== state.rootPage.id) {
			const page = state.pages.find(p => (p.id === pageId))
			if (!page) {
				break
			}
			pages.unshift(page)
			pageId = page.parentId
		}
		return pages
	}
}
