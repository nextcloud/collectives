import Vue from 'vue'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import * as sortOrders from '../util/sortOrders'

import {
	SET_PAGES,
	ADD_PAGE,
	UPDATE_PAGE,
	DELETE_PAGE_BY_ID,
	SET_BACKLINKS,
} from './mutations'

import {
	GET_PAGES,
	GET_PAGE,
	NEW_PAGE,
	NEW_TEMPLATE,
	TOUCH_PAGE,
	RENAME_PAGE,
	DELETE_PAGE,
	GET_BACKLINKS,
} from './actions'

export const TEMPLATE_PAGE = 'Template'

export default {
	state: {
		pages: [],
		newPage: undefined,
		sortBy: 'byTimestamp',
		collapsed: {},
		showTemplates: false,
		backlinks: [],
	},

	getters: {
		pagePath: (_state, getters) => (page) => {
			const collective = getters.currentCollective.name
			const { filePath, fileName, title, id } = page
			const titlePart = fileName !== 'Readme.md' && title
			// For public collectives, prepend `/p/{shareToken}`
			const pagePath = [
				getters.isPublic ? 'p' : null,
				getters.isPublic ? getters.shareTokenParam : null,
				collective,
				...filePath.split('/'),
				titlePart,
			].filter(Boolean).map(encodeURIComponent).join('/')
			return `/${pagePath}?fileId=${id}`
		},

		pagePathTitle: (_state, getters) => (page) => {
			const { filePath, fileName, title } = page
			const titlePart = fileName !== 'Readme.md' && title
			return [filePath, titlePart].filter(Boolean).join('/')
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
			// For public collectives, the dav path doesn't contain a username
			if (!getters.isPublic) {
				parts.unshift(getCurrentUser().uid)
			}
			return parts
				.map(p => encodeURIComponent(p))
				.join('/')
		},

		currentPageDavUrl(_state, getters) {
			return getters.isPublic
				? generateRemoteUrl(`webdav/${getters.currentPageDavPath}`)
					.replace('/remote.php', '/public.php')
				: generateRemoteUrl(`dav/files/${getters.currentPageDavPath}`)
		},

		pageDavUrl: (_state, getters) => (page) => {
			return getters.isPublic
				? generateRemoteUrl(`webdav/${getters.pageDavPath(page)}`)
					.replace('/remote.php', '/public.php')
				: generateRemoteUrl(`dav/files/${getters.pageDavPath(page)}`)
		},

		collectivePage(state) {
			return state.pages.find(p => (p.parentId === 0 && p.title === 'Readme'))
		},

		templatePage: (state) => (parentId) => {
			return state.pages.find(p => (p.parentId === parentId && p.title === TEMPLATE_PAGE))
		},

		currentFileIdPage(state, _getters, rootState) {
			const fileId = Number(rootState.route.query.fileId)
			return state.pages.find(p => (p.id === fileId))
		},

		visibleSubpages: (state, getters) => (parentId) => {
			return state.pages
				.filter(p => p.parentId === parentId)
				.filter(p => p.title !== TEMPLATE_PAGE)
				.sort(getters.sortOrder)
		},

		sortOrder(state) {
			if (state.sortBy === 'byTitle') {
				return sortOrders.byTitle
			} else {
				return sortOrders.byTimestamp
			}
		},

		sortBy(state) {
			return state.sortBy
		},

		newPagePath(state, getters) {
			return state.newPage && getters.pagePath(state.newPage)
		},

		pagesUrl(_state, getters) {
			return getters.isPublic
				? generateUrl(`/apps/collectives/_api/p/${getters.shareTokenParam}/_pages`)
				: generateUrl(`/apps/collectives/_api/${getters.currentCollective.id}/_pages`)
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

		backlinksUrl(_state, getters) {
			return (parentId, pageId) => `${getters.pageUrl(parentId, pageId)}/backlinks`
		},

		collapsed(state) {
			// Default to 'true' if unset
			return pageId => state.collapsed[pageId] != null ? state.collapsed[pageId] : true
		},

		showTemplates(state) {
			return state.showTemplates
		},
	},

	mutations: {
		[SET_PAGES](state, { pages }) {
			state.pages = pages
		},

		[UPDATE_PAGE](state, page) {
			state.pages.splice(
				state.pages.findIndex(p => p.id === page.id),
				1,
				page
			)
		},

		[ADD_PAGE](state, page) {
			state.pages.unshift(page)
			state.newPage = page
		},

		[DELETE_PAGE_BY_ID](state, id) {
			state.pages.splice(state.pages.findIndex(p => p.id === id), 1)
		},

		[SET_BACKLINKS](state, { pages }) {
			state.backlinks = pages
		},

		// using camel case name so this works nicely with mapMutations
		unsetPages(state) {
			state.pages = []
		},

		sortPages(state, order) {
			state.sortBy = order
		},

		unsetBacklinks(state) {
			state.backlinks = []
		},

		toggleTemplates(state) {
			state.showTemplates = !state.showTemplates
		},

		collapse: (state, pageId) => Vue.set(state.collapsed, pageId, true),
		expand: (state, pageId) => Vue.set(state.collapsed, pageId, false),
		toggleCollapsed: (state, pageId) =>
			// Default to 'false' if unset
			Vue.set(state.collapsed, pageId, state.collapsed[pageId] == null ? false : !state.collapsed[pageId]),
	},

	actions: {

		/**
		 * Get list of all pages
		 *
		 * @param {boolean} setLoading Whether to set loading('collective')
		 */
		async [GET_PAGES]({ commit, getters }, setLoading = true) {
			if (setLoading) {
				commit('load', 'collective')
			}
			const response = await axios.get(getters.pagesUrl)
			commit(SET_PAGES, {
				pages: response.data.data,
				current: getters.currentPage,
			})
			commit('done', 'collective')
		},

		/**
		 * Get a single page and update it in the store
		 *
		 * @param {number} parentId Parent ID
		 * @param {number} pageId Page ID
		 */
		async [GET_PAGE]({ commit, getters, state }, { parentId, pageId }) {
			commit('load', 'page')
			const response = await axios.get(getters.pageUrl(parentId, pageId))
			commit(UPDATE_PAGE, response.data.data)
			commit('done', 'page')
		},

		/**
		 * Create a new page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} page Properties for the new page (title for now)
		 */
		async [NEW_PAGE]({ commit, getters }, page) {
			// We'll be done when the title form has focus.
			commit('load', 'newPage')

			const response = await axios.post(getters.pageCreateUrl(page.parentId), page)
			// Add new page to the beginning of pages array
			commit(ADD_PAGE, response.data.data)
		},

		/**
		 * Create a new page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} parentPage Parent page for new template
		 */
		async [NEW_TEMPLATE]({ commit, getters }, parentPage) {
			const page = {
				title: 'Template',
				parentId: parentPage.id,
			}

			// We'll be done when the editor has focus.
			commit('load', 'editTemplate')

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
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {string} newTitle new title for the page
		 */
		async [RENAME_PAGE]({ commit, getters }, newTitle) {
			commit('load', 'page')
			const page = getters.currentPage
			const url = getters.pageUrl(page.parentId, page.id)
			const response = await axios.put(url, { title: newTitle })
			await commit(UPDATE_PAGE, response.data.data)
			commit('done', 'page')
		},

		/**
		 * Delete the current page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 */
		async [DELETE_PAGE]({ commit, getters }) {
			commit('load', 'page')
			await axios.delete(getters.pageUrl(getters.currentPage.parentId, getters.currentPage.id))
			commit(DELETE_PAGE_BY_ID, getters.currentPage.id)
			commit('done', 'page')
		},

		/**
		 * Get list of backlinks for a page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} page Page to get backlinks for
		 */
		async [GET_BACKLINKS]({ commit, getters }, page) {
			commit('load', 'backlinks')
			const response = await axios.get(getters.backlinksUrl(page.parentId, page.id))
			commit(SET_BACKLINKS, { pages: response.data.data })
			commit('done', 'backlinks')
		},
	},
}
