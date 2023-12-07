import { set } from 'vue'
import { getCurrentUser } from '@nextcloud/auth'
import { getBuilder } from '@nextcloud/browser-storage'
import axios from '@nextcloud/axios'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
/* eslint import/namespace: ['error', { allowComputed: true }] */
import * as sortOrders from '../util/sortOrders.js'
import { sortedSubpages, pageParents } from './pageExtracts.js'

import {
	SET_PAGES,
	SET_TRASH_PAGES,
	ADD_PAGE,
	UPDATE_PAGE,
	MOVE_PAGE_INTO_TRASH,
	REMOVE_PAGE,
	RESTORE_PAGE_FROM_TRASH,
	DELETE_PAGE_FROM_TRASH_BY_ID,
	SET_ATTACHMENTS,
	SET_ATTACHMENT_DELETED,
	SET_ATTACHMENT_UNDELETED,
	SET_BACKLINKS,
	KEEP_SORTABLE,
	CLEAR_SORTABLE,
	SET_FULL_WIDTH_PAGEIDS,
} from './mutations.js'

import {
	EXPAND_PARENTS,
	GET_PAGES,
	GET_TRASH_PAGES,
	GET_PAGE,
	NEW_PAGE,
	NEW_TEMPLATE,
	TOUCH_PAGE,
	RENAME_PAGE,
	COPY_PAGE,
	MOVE_PAGE,
	COPY_PAGE_TO_COLLECTIVE,
	MOVE_PAGE_TO_COLLECTIVE,
	SET_PAGE_EMOJI,
	SET_PAGE_SUBPAGE_ORDER,
	TRASH_PAGE,
	RESTORE_PAGE,
	DELETE_PAGE,
	GET_ATTACHMENTS,
	GET_BACKLINKS,
	INIT_FULL_WIDTH_PAGEIDS,
	SET_FULL_WIDTH_VIEW,
} from './actions.js'

const persistentStorage = getBuilder('collectives').persist().build()

export const TEMPLATE_PAGE = 'Template'

export default {
	state: {
		pages: [],
		trashPages: [],
		newPage: undefined,
		sortBy: undefined,
		collapsed: {},
		showTemplates: false,
		attachments: [],
		deletedAttachments: [],
		backlinks: [],
		highlightPageId: null,
		highlightAnimationPageId: null,
		isDragoverTargetPage: false,
		draggedPageId: null,
		fullWidthPageIds: [],
	},

	getters: {
		pageById(state) {
			return (pageId) => {
				return state.pages.find(p => p.id === pageId)
			}
		},

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
			// Return root page
			if (!getters.pageParam
				|| getters.pageParam === 'Readme') {
				return [getters.rootPage.id]
			}

			// Iterate through all path levels to find the correct page
			const pageIds = []
			const parts = getters.pageParam.split('/').filter(Boolean)
			let page = getters.rootPage
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

		pageFilePath: () => (page) => {
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

		rootPage(state, getters) {
			return getters.currentCollectiveIsPageShare
				? state.pages[0]
				: state.pages.find(p => (p.parentId === 0))
		},

		templatePage: (state) => (parentId) => {
			return state.pages.find(p => (p.parentId === parentId && p.title === TEMPLATE_PAGE))
		},

		currentFileIdPage(state, _getters, rootState) {
			const fileId = Number(rootState.route.query.fileId)
			return state.pages.find(p => (p.id === fileId))
		},

		hasSubpages(state) {
			return (pageId) => {
				return state.pages.filter(p => p.parentId === pageId).length > 0
			}
		},

		sortedSubpages,

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

		pageParents,

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
			return parentId => `${getters.pagesUrl}/${parentId}`
		},

		pageUrl(_state, getters) {
			return (pageId) => `${getters.pagesUrl}/${pageId}`
		},

		emojiUrl(_state, getters) {
			return (pageId) => `${getters.pageUrl(pageId)}/emoji`
		},

		subpageOrderUrl(_state, getters) {
			return (pageId) => `${getters.pageUrl(pageId)}/subpageOrder`
		},

		touchUrl(_state, getters) {
			return `${getters.pageUrl(getters.currentPage.id)}/touch`
		},

		attachmentsUrl(_state, getters) {
			return (pageId) => `${getters.pageUrl(pageId)}/attachments`
		},

		backlinksUrl(_state, getters) {
			return (pageId) => `${getters.pageUrl(pageId)}/backlinks`
		},

		trashIndexUrl(_state, getters) {
			return `${getters.pagesUrl}/trash`
		},

		trashActionUrl(_state, getters) {
			return (pageId) => `${getters.pagesUrl}/trash/${pageId}`
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

		subpageOrder(state) {
			return (pageId) => state.pages.find(p => p.id === pageId).subpageOrder
		},

		subpageOrderIndex(state, getters) {
			return (parentId, pageId) => {
				const parentSubpageOrder = getters.subpageOrder(parentId)
				return parentSubpageOrder.indexOf(pageId)
			}
		},

		trashPages(state) {
			return state.trashPages.sort((a, b) => b.trashTimestamp - a.trashTimestamp)
		},

		recentPages(state) {
			return state.pages
				.slice()
				.filter(p => p.title !== 'Template')
				.sort(sortOrders.byTimestamp)
		},

		recentPagesUserIds(_state, getters) {
			return getters.recentPages
				// take only userIds
				.map(p => p.lastUserId)
				// filter out duplicates
				.filter((value, index, array) => {
					return array.indexOf(value) === index
				})
		},

		isFullWidthView(state, getters) {
			return state.fullWidthPageIds.includes(getters.currentPage?.id)
		},
	},

	mutations: {
		[SET_PAGES](state, { pages }) {
			state.pages = pages
		},

		[SET_TRASH_PAGES](state, trashPages) {
			state.trashPages = trashPages
		},

		[UPDATE_PAGE](state, page) {
			state.pages.splice(
				state.pages.findIndex(p => p.id === page.id),
				1,
				page,
			)
		},

		[ADD_PAGE](state, page) {
			state.pages.unshift(page)
			state.newPage = page
		},

		[MOVE_PAGE_INTO_TRASH](state, page) {
			const trashPage = { ...page }
			state.pages.splice(state.pages.findIndex(p => p.id === page.id), 1)
			trashPage.trashTimestamp = Date.now() / 1000
			state.trashPages.unshift(trashPage)
		},

		[REMOVE_PAGE](state, page) {
			state.pages.splice(state.pages.findIndex(p => p.id === page.id), 1)
		},

		[RESTORE_PAGE_FROM_TRASH](state, trashPage) {
			const page = { ...trashPage }
			page.trashTimestamp = null
			state.pages.unshift(page)
			state.trashPages.splice(state.trashPages.findIndex(p => p.id === trashPage.id), 1)
		},

		[DELETE_PAGE_FROM_TRASH_BY_ID](state, id) {
			state.trashPages.splice(state.trashPages.findIndex(p => p.id === id), 1)
		},

		[SET_ATTACHMENTS](state, { attachments }) {
			state.attachments = attachments
				// Disregard deletedAttachments when updating attachments
				.filter(a => !state.deletedAttachments.map(a => a.name).includes(a.name))
			state.deletedAttachments = state.deletedAttachments
				// Only keep deletedAttachments that still exist
				.filter(a => attachments.map(a => a.name).includes(a.name))
		},

		[SET_ATTACHMENT_DELETED](state, name) {
			const index = state.attachments.findIndex(a => a.name === name)
			if (index !== -1) {
				const [attachment] = state.attachments.splice(index, 1)
				state.deletedAttachments.push(attachment)
			}
		},

		[SET_ATTACHMENT_UNDELETED](state, name) {
			const index = state.deletedAttachments.findIndex(a => a.name === name)
			if (index !== -1) {
				const [attachment] = state.deletedAttachments.splice(index, 1)
				state.attachments.push(attachment)
			}
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

		[SET_FULL_WIDTH_PAGEIDS](state, fullWidthPageIds) {
			state.fullWidthPageIds = fullWidthPageIds
		},

		// using camel case name so this works nicely with mapMutations
		unsetPages(state) {
			state.pages = []
		},

		unsetAttachments(state) {
			state.attachments = []
			state.deletedAttachments = []
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

		setHighlightAnimationPageId(state, pageId) {
			state.highlightAnimationPageId = pageId
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
		 * Expand all parents of a page
		 * Needs to be an action to have access to the getter `pageParents`
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {number} pageId Page ID
		 */
		[EXPAND_PARENTS]({ commit, getters }, pageId) {
			for (const page of getters.pageParents(pageId)) {
				commit('expand', page.id)
			}
		},

		/**
		 * Get list of all pages for current collective
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
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
		 * Get list of all pages in trash
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 */
		async [GET_TRASH_PAGES]({ commit, getters }) {
			commit('load', 'pageTrash')
			const response = await axios.get(getters.trashIndexUrl)
			commit(SET_TRASH_PAGES, response.data.data)
			commit('done', 'pageTrash')
		},

		/**
		 * Get a single page and update it in the store
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} store.state state of the store
		 * @param {number} pageId Page ID
		 */
		async [GET_PAGE]({ commit, getters, state }, pageId) {
			const response = await axios.get(getters.pageUrl(pageId))
			commit(UPDATE_PAGE, response.data.data)
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
			// Will be done when the title form has focus.
			commit('load', 'newPageTitle')
			// We'll be done when the editor is loaded.
			commit('load', 'newPageContent')

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

			// We'll be done when the editor is loaded.
			commit('load', 'newPageContent')

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
			const url = getters.pageUrl(getters.currentPage.id)
			const response = await axios.put(url, { title: newTitle })
			await commit(UPDATE_PAGE, response.data.data)
		},

		/**
		 * Copy page to another parent
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
		async [COPY_PAGE]({ commit, getters, state, dispatch }, { newParentId, pageId, index }) {
			commit('load', 'pagelist')
			const page = { ...state.pages.find(p => p.id === pageId) }

			// Keep subpage list of old parent page in DOM to prevent a race condition with sortableJS
			const oldParentId = page.parentId

			// Increment index by one if copying to same folder with an index after the original
			if (oldParentId === newParentId && index >= getters.subpageOrderIndex(newParentId, pageId)) {
				index += 1
			}

			const url = getters.pageUrl(pageId)
			try {
				await axios.put(url, { index, parentId: newParentId, copy: true })
				// Reload the page list to make new page appear
				await dispatch(GET_PAGES, false)
			} finally {
				commit('done', 'pagelist')
			}
		},

		/**
		 * Move page to another parent
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
			const hasSubpages = getters.visibleSubpages(pageId).length > 0

			// Save a clone of the page to restore in case of errors
			const pageClone = { ...page }

			// Keep subpage list of old parent page in DOM to prevent a race condition with sortableJS
			const oldParentId = page.parentId
			commit(KEEP_SORTABLE, oldParentId)

			// Update page in store first to avoid page order jumping around
			page.parentId = newParentId
			commit(UPDATE_PAGE, page)

			const url = getters.pageUrl(pageId)
			try {
				const response = await axios.put(url, { index, parentId: newParentId })
				commit(UPDATE_PAGE, response.data.data)
			} catch (e) {
				commit(UPDATE_PAGE, pageClone)
				throw e
			} finally {
				commit(CLEAR_SORTABLE, oldParentId)
				commit('done', 'pagelist')
			}

			// Reload the page list if moved page had subpages (to get their updated paths)
			if (hasSubpages) {
				await dispatch(GET_PAGES, false)
			}
		},

		/**
		 * Copy page to another collective
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} store.state state of the store
		 * @param {Function} store.dispatch dispatch actions
		 * @param {object} page the page
		 * @param {number} page.collectiveId ID of the new collective
		 * @param {number} page.newParentId ID of the new parent page
		 * @param {number} page.pageId ID of the page
		 * @param {number} page.index index for subpageOrder of parent page
		 */
		async [COPY_PAGE_TO_COLLECTIVE]({ commit, getters, state, dispatch }, { collectiveId, newParentId, pageId, index }) {
			commit('load', 'pagelist')

			const url = `${getters.pageUrl(pageId)}/to/${collectiveId}`
			await axios.put(url, { index, parentId: newParentId, copy: true })
			commit('done', 'pagelist')
		},

		/**
		 * Move page to another collective
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} store.state state of the store
		 * @param {Function} store.dispatch dispatch actions
		 * @param {object} page the page
		 * @param {number} page.collectiveId ID of the new collective
		 * @param {number} page.newParentId ID of the new parent page
		 * @param {number} page.pageId ID of the page
		 * @param {number} page.index index for subpageOrder of parent page
		 */
		async [MOVE_PAGE_TO_COLLECTIVE]({ commit, getters, state, dispatch }, { collectiveId, newParentId, pageId, index }) {
			commit('load', 'pagelist')
			const page = { ...state.pages.find(p => p.id === pageId) }
			const hasSubpages = getters.visibleSubpages(pageId).length > 0

			const url = `${getters.pageUrl(pageId)}/to/${collectiveId}`
			await axios.put(url, { index, parentId: newParentId })
			commit(REMOVE_PAGE, page)
			commit('done', 'pagelist')

			// Reload the page list if moved page had subpages (to remove subpages as well)
			if (hasSubpages) {
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
		 * @param {number} page.pageId ID of the page
		 * @param {string} page.emoji emoji for the page
		 */
		async [SET_PAGE_EMOJI]({ commit, getters }, { pageId, emoji }) {
			commit('load', `pageEmoji-${pageId}`)
			const response = await axios.put(getters.emojiUrl(pageId), { emoji })
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
		 * @param {number} page.pageId ID of the page
		 * @param {Array} page.subpageOrder subpage order for the page
		 */
		async [SET_PAGE_SUBPAGE_ORDER]({ commit, getters, state }, { pageId, subpageOrder }) {
			commit('load', 'pagelist')
			const page = { ...state.pages.find(p => p.id === pageId) }

			// Save a clone of the page to restore in case of errors
			const pageClone = { ...page }

			// Update page in store first to avoid page order jumping around
			page.subpageOrder = subpageOrder
			commit(UPDATE_PAGE, page)

			try {
				const response = await axios.put(
					getters.subpageOrderUrl(pageId),
					{ subpageOrder: JSON.stringify(subpageOrder) },
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
		 * Trash the page with the given id
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page
		 */
		async [TRASH_PAGE]({ commit, getters }, { pageId }) {
			const response = await axios.delete(getters.pageUrl(pageId))
			commit(MOVE_PAGE_INTO_TRASH, response.data.data)
		},

		/**
		 * Restore the page with the given id from trash
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page to restore
		 */
		async [RESTORE_PAGE]({ commit, getters }, { pageId }) {
			const response = await axios.patch(getters.trashActionUrl(pageId))
			commit(RESTORE_PAGE_FROM_TRASH, response.data.data)
		},

		/**
		 * Delete the page with the given id from trash
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page to delete
		 */
		async [DELETE_PAGE]({ commit, getters }, { pageId }) {
			axios.delete(getters.trashActionUrl(pageId))
			commit(DELETE_PAGE_FROM_TRASH_BY_ID, pageId)
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
			const response = await axios.get(getters.attachmentsUrl(page.id))
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
			const response = await axios.get(getters.backlinksUrl(page.id))
			commit(SET_BACKLINKS, { pages: response.data.data })
		},

		/**
		 * Init full width store from browser storage
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 */
		[INIT_FULL_WIDTH_PAGEIDS]({ commit }) {
			commit(SET_FULL_WIDTH_PAGEIDS, JSON.parse(persistentStorage.getItem('text-fullWidthPageIds') ?? '[]'))
		},

		/**
		 * Set full width for a page
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {boolean} fullWidthView Whether full width view is enabled or not
		 */
		[SET_FULL_WIDTH_VIEW]({ commit, getters }, fullWidthView) {
			const pageId = getters.currentPage.id
			const fullWidthPageIds = JSON.parse(persistentStorage.getItem('text-fullWidthPageIds') ?? '[]')
			if (fullWidthView && !fullWidthPageIds.includes(pageId)) {
				fullWidthPageIds.push(pageId)
				commit(SET_FULL_WIDTH_PAGEIDS, fullWidthPageIds)
				persistentStorage.setItem('text-fullWidthPageIds', JSON.stringify(fullWidthPageIds))
			} else if (!fullWidthView && fullWidthPageIds.includes(pageId)) {
				fullWidthPageIds.splice(fullWidthPageIds.indexOf(pageId), 1)
				commit(SET_FULL_WIDTH_PAGEIDS, fullWidthPageIds)
				persistentStorage.setItem('text-fullWidthPageIds', JSON.stringify(fullWidthPageIds))
			}
		},
	},
}
