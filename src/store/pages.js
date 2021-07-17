import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import * as sortOrders from '../util/sortOrders'

import {
	SET_PAGES,
	ADD_PAGE,
	UPDATE_PAGE,
	CLEAR_UPDATED_PAGE,
	DELETE_PAGE_BY_ID,
} from './mutations'

import {
	GET_PAGES,
	GET_PAGE,
	NEW_PAGE,
	TOUCH_PAGE,
	RENAME_PAGE,
	DELETE_PAGE,
} from './actions'

export default {
	state: {
		pages: [],
		updatedPage: undefined,
		sortBy: 'byTimestamp',
	},

	getters: {
		pagePath: (_state, getters) => (page) => {
			const collective = getters.collectiveParam
			const { filePath, fileName, title, id } = page
			const titlePart = fileName !== 'Readme.md' && title
			const pagePath = [
				collective,
				...filePath.split('/'),
				titlePart,
			].filter(Boolean).map(encodeURIComponent).join('/')
			return `/${pagePath}?fileId=${id}`
		},

		currentPages(state, getters) {
			// Return landing page
			if (!getters.pageParam
				|| getters.pageParam === 'Readme') {
				return [getters.collectivePage]
			}

			// Iterate through all path levels to find the correct page
			const pages = []
			const parts = getters.pageParam.split('/').filter(Boolean)
			let page = getters.collectivePage
			for (const i in parts) {
				page = state.pages.find(p => (p.parentId === page.id && p.title === parts[i]))
				if (page) {
					pages.push(page)
				} else {
					return []
				}
			}
			return pages
		},

		currentPage(state, getters) {
			return getters.currentPages[getters.currentPages.length - 1]
			    || state.updatedPage
		},

		currentPageFilePath(_state, getters) {
			return getters.pageFilePath(getters.currentPage)
		},

		pageFilePath: (state) => (page) => {
			return [
				page.collectivePath,
				page.filePath,
				page.fileName,
			].filter(Boolean).join('/')
		},

		currentPageDavPath(_state, getters) {
			return getters.pageDavPath(getters.currentPage)
		},

		pageDavPath: (_state, getters) => (page) => {
			const parts = getters.pageFilePath(page).split('/')
			parts.unshift(getCurrentUser().uid)
			return parts
				.map(p => encodeURIComponent(p))
				.join('/')
		},

		currentPageDavUrl(_state, getters) {
			return generateRemoteUrl(`dav/files/${getters.currentPageDavPath}`)
		},

		pageDavUrl: (_state, getters) => (page) => {
			return generateRemoteUrl(`dav/files/${getters.pageDavPath(page)}`)
		},

		collectivePage(state) {
			return state.pages.find(p => (p.parentId === 0 && p.title === 'Readme'))
		},

		visibleSubpages: (state, getters) => (parentId) => {
			return state.pages
				.filter(p => p.parentId === parentId)
				.sort(getters.sortOrder)
		},

		sortOrder(state) {
			if (state.sortBy === 'byTitle') {
				return sortOrders.byTitle
			} else {
				return sortOrders.byTimestamp
			}
		},

		updatedPagePath(state, getters) {
			return state.updatedPage && getters.pagePath(state.updatedPage)
		},

		pagesUrl(_state, getters) {
			return generateUrl(`/apps/collectives/_collectives/${getters.currentCollective.id}/_pages`)
		},

		pageCreateUrl(_state, getters) {
			return parentId => `${getters.pagesUrl}/parent/${parentId}`
		},

		pageUrl(_state, getters) {
			return (parentId, pageId) => `${getters.pagesUrl}/parent/${parentId}/page/${pageId}`
		},

		touchUrl(_state, getters) {
			return `${getters.pageUrl(getters.currentPage.parentId, getters.currentPage.id)}/touch`
		},
	},

	mutations: {
		[SET_PAGES](state, { pages, current }) {
			// keep track of the current page in case it was renamed.
			if (current) {
				state.updatedPage = pages.find(p => p.id === current.id)
			}
			state.pages = pages
		},

		[UPDATE_PAGE](state, page) {
			state.updatedPage = page
			state.pages.splice(
				state.pages.findIndex(p => p.id === page.id),
				1,
				page
			)
		},

		[CLEAR_UPDATED_PAGE](state) {
			state.updatedPage = undefined
		},

		[ADD_PAGE](state, page) {
			state.pages.unshift(page)
			state.updatedPage = page
		},

		[DELETE_PAGE_BY_ID](state, id) {
			state.pages.splice(state.pages.findIndex(p => p.id === id), 1)
		},

		// using camel case name so this works nicely with mapMutations
		sortPages(state, order) {
			state.sortBy = order
		},

	},

	actions: {

		/**
		 * Get list of all pages
		 */
		async [GET_PAGES]({ commit, getters }) {
			commit('load', 'collective', { root: true })
			const response = await axios.get(getters.pagesUrl)
			commit(SET_PAGES, {
				pages: response.data.data,
				current: getters.currentPage,
			})
			commit('done', 'collective', { root: true })
		},

		/**
		 * Get a single page and update it in the store
		 * @param {number} parentId Parent ID
		 * @param {number} pageId Page ID
		 */
		async [GET_PAGE]({ commit, getters, state }, { parentId, pageId }) {
			commit('load', 'page', { root: true })
			const response = await axios.get(getters.pageUrl(parentId, pageId))
			commit(UPDATE_PAGE, response.data.data)
			commit('done', 'page', { root: true })
		},

		/**
		 * Create a new page
		 * @param {Object} page Properties for the new page (title for now)
		 */
		async [NEW_PAGE]({ commit, getters }, page) {
			// We'll be done when the title form has focus.
			commit('load', 'newPage', { root: true })
			const response = await axios.post(getters.pageCreateUrl(page.parentId), page)
			// Add new page to the beginning of pages array
			commit(ADD_PAGE, response.data.data)
		},

		async [TOUCH_PAGE]({ commit, getters }) {
			const response = await axios.get(getters.touchUrl)
			commit(UPDATE_PAGE, response.data.data)
		},

		/**
		 * Rename the current page
		 * @param {string} newTitle new title for the page
		 */
		async [RENAME_PAGE]({ commit, getters, state }, newTitle) {
			commit('load', 'page', { root: true })
			const page = getters.currentPage
			const url = getters.pageUrl(page.parentId, page.id)
			const response = await axios.put(url, { title: newTitle })
			await commit(UPDATE_PAGE, response.data.data)
			commit('done', 'page', { root: true })
		},

		/**
		 * Delete the current page
		 */
		async [DELETE_PAGE]({ commit, getters, state }) {
			commit('load', 'page', { root: true })
			await axios.delete(getters.pageUrl(getters.currentPage.parentId, getters.currentPage.id))
			commit(DELETE_PAGE_BY_ID, getters.currentPage.id)
			commit('done', 'page', { root: true })
		},

	},
}
