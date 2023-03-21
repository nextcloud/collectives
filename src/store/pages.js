import { set } from 'vue'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
/* eslint import/namespace: ['error', { allowComputed: true }] */
import * as sortOrders from '../util/sortOrders.js'

import {
	SET_PAGES,
	ADD_PAGE,
	UPDATE_PAGE,
	DELETE_PAGE_BY_ID,
	SET_ATTACHMENTS,
	SET_BACKLINKS,
	KEEP_SORTABLE,
	CLEAR_SORTABLE,
} from './mutations.js'

import {
	GET_PAGES,
	GET_PAGE,
	NEW_PAGE,
	NEW_TEMPLATE,
	TOUCH_PAGE,
	RENAME_PAGE,
	MOVE_PAGE,
	SET_PAGE_EMOJI,
	SET_PAGE_SUBPAGE_ORDER,
	DELETE_PAGE,
	GET_ATTACHMENTS,
	GET_BACKLINKS,
} from './actions.js'

export const TEMPLATE_PAGE = 'Template'

export default {
	state: {
		pages: [],
		newPage: undefined,
		sortBy: undefined,
		collapsed: {},
		showTemplates: false,
		attachments: [],
		backlinks: [],
		highlightPageId: null,
		isDragoverTargetPage: false,
		draggedPageId: null,
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

		currentPageIds(state, getters) {
			// Return landing page
			if (!getters.pageParam
				|| getters.pageParam === 'Readme') {
				return [getters.collectivePage.id]
			}

			// Iterate through all path levels to find the correct page
			const pageIds = []
			const parts = getters.pageParam.split('/').filter(Boolean)
			let page = getters.collectivePage
			for (const i in parts) {
				page = state.pages.find(p => (p.parentId === page.id && p.title === parts[i]))
				if (page) {
					pageIds.push(page.id)
				} else {
					return []
				}
			}
			return pageIds
		},

		currentPage(state, getters) {
			return state.pages.find(p => (p.id === getters.currentPageIds[getters.currentPageIds.length - 1]))
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

		currentPageDirectory(_state, getters) {
			return getters.pageDirectory(getters.currentPage)
		},

		pageDirectory: () => (page) => {
			const { collectivePath, filePath } = page
			return [collectivePath, filePath].filter(Boolean).join('/')
		},

		collectivePage(state) {
			return state.pages.find(p => (p.parentId === 0))
		},

		templatePage: (state) => (parentId) => {
			return state.pages.find(p => (p.parentId === parentId && p.title === TEMPLATE_PAGE))
		},

		currentFileIdPage(state, _getters, rootState) {
			const fileId = Number(rootState.route.query.fileId)
			return state.pages.find(p => (p.id === fileId))
		},

		sortedSubpages(state, getters) {
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
		},

		pagesTreeWalk: (_state, getters) => (parentId = 0) => {
			const pages = []
			for (const page of getters.visibleSubpages(parentId)) {
				pages.push(page)
				for (const subpage of getters.pagesTreeWalk(page.id)) {
					pages.push(subpage)
				}
			}
			return pages
		},

		pageParent: (state) => (pageId) => {
			return state.pages.find(p => (p.id === pageId)).parentId
		},

		pageParents: (state, getters) => (pageId) => {
			const pages = []
			while (pageId !== getters.collectivePage.id) {
				const page = state.pages.find(p => (p.id === pageId))
				if (!page) {
					break
				}
				pages.unshift(page)
				pageId = page.parentId
			}
			return pages
		},

		visibleSubpages: (state, getters) => (parentId) => {
			return getters.sortedSubpages(parentId)
		},

		sortOrder(state, getters) {
			return sortOrders[getters.sortBy] || sortOrders.byOrder
		},

		sortByDefault(_state, getters) {
			return sortOrders.pageOrdersByNumber[getters.currentCollective.userPageOrder]
		},

		sortBy(state, getters) {
			return state.sortBy ? state.sortBy : getters.sortByDefault
		},

		disableDragndropSortOrMove(state, getters) {
			// Disable for readonly collective
			return !getters.currentCollectiveCanEdit
				// Disable if a page list is loading (e.g. when page move is pending)
				|| getters.loading('pagelist')
				// For now also disable in alternative page order view
				// TODO: Smoothen UX if allowed to move but not to sort with alternative page orders
				|| (getters.sortBy !== 'byOrder')
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

		emojiUrl(_state, getters) {
			return (parentId, pageId) => `${getters.pageUrl(parentId, pageId)}/emoji`
		},

		subpageOrderUrl(_state, getters) {
			return (parentId, pageId) => `${getters.pageUrl(parentId, pageId)}/subpageOrder`
		},

		touchUrl(_state, getters) {
			return `${getters.pageUrl(getters.currentPage.parentId, getters.currentPage.id)}/touch`
		},

		attachmentsUrl(_state, getters) {
			return (parentId, pageId) => `${getters.pageUrl(parentId, pageId)}/attachments`
		},

		backlinksUrl(_state, getters) {
			return (parentId, pageId) => `${getters.pageUrl(parentId, pageId)}/backlinks`
		},

		pageTitle(state, getters) {
			return pageId => {
				const page = state.pages.find(p => p.id === pageId)
				return (page.parentId === 0) ? getters.collectiveParam : page.title
			}
		},

		collapsed(state) {
			// Default to 'true' if unset
			return pageId => state.collapsed[pageId] != null ? state.collapsed[pageId] : true
		},

		showTemplates(state) {
			return state.showTemplates
		},

		keptSortable(state) {
			return (pageId) => state.pages.find(p => p.id === pageId)?.keepSortable
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

		[SET_ATTACHMENTS](state, { attachments }) {
			state.attachments = attachments
		},

		[SET_BACKLINKS](state, { pages }) {
			state.backlinks = pages
		},

		[KEEP_SORTABLE](state, pageId) {
			state.pages.find(p => p.id === pageId).keepSortable = true
		},

		[CLEAR_SORTABLE](state, pageId) {
			delete state.pages.find(p => p.id === pageId).keepSortable
		},

		// using camel case name so this works nicely with mapMutations
		unsetPages(state) {
			state.pages = []
		},

		updateSubpageOrder(state, { parentId, subpageOrder }) {
			if (state.pages.find(p => p.id === parentId)) {
				state.pages.find(p => p.id === parentId).subpageOrder = subpageOrder
			}
		},

		setPageOrder(state, order) {
			state.sortBy = order
		},

		unsetBacklinks(state) {
			state.backlinks = []
		},

		toggleTemplates(state) {
			state.showTemplates = !state.showTemplates
		},

		collapse: (state, pageId) => set(state.collapsed, pageId, true),

		expand: (state, pageId) => set(state.collapsed, pageId, false),

		toggleCollapsed: (state, pageId) =>
			// Default to 'false' if unset
			set(state.collapsed, pageId, state.collapsed[pageId] == null ? false : !state.collapsed[pageId]),

		setHighlightPageId(state, pageId) {
			state.highlightPageId = pageId
		},

		setDragoverTargetPage(state, bool) {
			state.isDragoverTargetPage = bool
		},

		setDraggedPageId(state, pageId) {
			state.draggedPageId = pageId
		},
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
		 * @param {number} parentId ID of parent page for new template
		 */
		async [NEW_TEMPLATE]({ commit, getters }, parentId) {
			const page = {
				title: 'Template',
				parentId,
			}

			// We'll be done when the editor has focus.
			commit('load', 'newTemplate')

			const response = await axios.post(getters.pageCreateUrl(page.parentId), page)
			// Add new page to the beginning of pages array
			commit(ADD_PAGE, response.data.data)
		},

		/**
		 * Touch current page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 */
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
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} store.state state of the store
		 * @param {Function} store.dispatch dispatch actions
		 * @param {object} page the page
		 * @param {number} page.newParentId ID of the new parent page
		 * @param {number} page.pageId ID of the page
		 * @param {number} page.index index for subpageOrder of parent page
		 */
		async [MOVE_PAGE]({ commit, getters, state, dispatch }, { newParentId, pageId, index }) {
			commit('load', 'pagelist')
			const page = { ...state.pages.find(p => p.id === pageId) }

			// Save a clone of the page to restore in case of errors
			const pageClone = { ...page }

			// Keep subpage list of old parent page in DOM to prevent a race condition with sortableJS
			const oldParentId = page.parentId
			commit(KEEP_SORTABLE, oldParentId)

			// Update page in store first to avoid page order jumping around
			page.parentId = newParentId
			commit(UPDATE_PAGE, page)

			const url = getters.pageUrl(newParentId, pageId)
			try {
				const response = await axios.put(url, { index })
				commit(UPDATE_PAGE, response.data.data)
			} catch (e) {
				commit(UPDATE_PAGE, pageClone)
				throw e
			} finally {
				commit(CLEAR_SORTABLE, oldParentId)
				commit('done', 'pagelist')
			}

			// Reload the page list if moved page had subpages (to get their updated paths)
			if (getters.visibleSubpages(pageId).length > 0) {
				await dispatch(GET_PAGES, false)
			}
		},

		/**
		 *
		 * Set emoji for a page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} page the page
		 * @param {number} page.parentId ID of the parent page
		 * @param {number} page.pageId ID of the page
		 * @param {string} page.emoji emoji for the page
		 */
		async [SET_PAGE_EMOJI]({ commit, getters }, { parentId, pageId, emoji }) {
			commit('load', `pageEmoji-${pageId}`)
			const response = await axios.put(getters.emojiUrl(parentId, pageId), { emoji })
			commit(UPDATE_PAGE, response.data.data)
			commit('done', `pageEmoji-${pageId}`)
		},

		/**
		 *
		 * Set subpageOrder for a page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} store.state state of the store
		 * @param {object} page the page
		 * @param {number} page.parentId ID of the parent page
		 * @param {number} page.pageId ID of the page
		 * @param {Array} page.subpageOrder subpage order for the page
		 */
		async [SET_PAGE_SUBPAGE_ORDER]({ commit, getters, state }, { parentId, pageId, subpageOrder }) {
			commit('load', 'pagelist')
			const page = { ...state.pages.find(p => p.id === pageId) }

			// Save a clone of the page to restore in case of errors
			const pageClone = { ...page }

			// Update page in store first to avoid page order jumping around
			page.subpageOrder = subpageOrder
			commit(UPDATE_PAGE, page)

			try {
				const response = await axios.put(
					getters.subpageOrderUrl(parentId, pageId),
					{ subpageOrder: JSON.stringify(subpageOrder) }
				)
				commit(UPDATE_PAGE, response.data.data)
			} catch (e) {
				commit(UPDATE_PAGE, pageClone)
				throw e
			} finally {
				commit('done', 'pagelist')
			}
		},

		/**
		 *
		 * Delete the current page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} page the page
		 * @param {number} page.parentId ID of the parent page
		 * @param {number} page.pageId ID of the page
		 */
		async [DELETE_PAGE]({ commit, getters }, { parentId, pageId }) {
			commit('load', 'page')
			await axios.delete(getters.pageUrl(parentId, pageId))
			commit(DELETE_PAGE_BY_ID, pageId)
			commit('done', 'page')
		},

		/**
		 * Get list of attachments for a page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} page Page to get attachments for
		 */
		async [GET_ATTACHMENTS]({ commit, getters }, page) {
			const response = await axios.get(getters.attachmentsUrl(page.parentId, page.id))
			commit(SET_ATTACHMENTS, { attachments: response.data.data })
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
