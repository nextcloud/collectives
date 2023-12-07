// Functions extracted from vuex page store to be reused in other places

/* eslint import/namespace: ['error', { allowComputed: true }] */
import * as sortOrders from '../util/sortOrders.js'
import { TEMPLATE_PAGE } from './pages.js'

/**
 * @param {object} state state of the vuex store
 * @param {object} getters getters of the vuex store
 */
export function sortedSubpages(state, getters) {
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
			.sort(sortOrders[sortOrder] || getters.sortOrder)
	}
}

/**
 * @param {object} state state of the vuex store
 * @param {object} getters getters of the vuex store
 */
export function pageParents(state, getters) {
	return (pageId) => {
		const pages = []
		while (pageId !== getters.rootPage.id) {
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
