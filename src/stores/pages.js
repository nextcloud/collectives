/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { generateRemoteUrl } from '@nextcloud/router'
import { useLocalStorage } from '@vueuse/core'
import { defineStore } from 'pinia'
import { set } from 'vue'
import * as api from '../apis/collectives/index.js'
import { INDEX_PAGE, PAGE_SUFFIX, pageModes, TEMPLATE_PATH } from '../constants.js'
import * as sortOrders from '../util/sortOrders.js'
import { removeFrom, updateOrAddTo } from './collectionHelpers.js'
import { useCollectivesStore } from './collectives.js'
import { useRootStore } from './root.js'

const STORE_PREFIX = 'collectives/pinia/pages/'

export const usePagesStore = defineStore('pages', {
	state: () => ({
		// Uses `collectiveId` as index for internal collectives and `share_<shareToken>` for public ones
		allPages: useLocalStorage(STORE_PREFIX + 'allPages', {}),
		allTrashPages: useLocalStorage(STORE_PREFIX + 'allTrashPages', {}),
		allAttachments: useLocalStorage(STORE_PREFIX + 'allAttachments', {}),
		textMode: useLocalStorage(STORE_PREFIX + 'textMode', {}),
		newPage: undefined,
		newPageParentId: null,
		sortBy: undefined,
		collapsed: useLocalStorage(STORE_PREFIX + 'collapsed', {}),
		outline: useLocalStorage(STORE_PREFIX + 'outline', {}),
		deletedAttachments: [],
		attachmentsLoaded: false,
		attachmentsError: false,
		highlightPageId: null,
		highlightAnimationPageId: null,
		isDragoverTargetPage: false,
		draggedPageId: null,
	}),

	getters: {
		collectiveId() {
			const collectivesStore = useCollectivesStore()
			return collectivesStore.currentCollective.id
		},

		collectiveIndex(state) {
			const rootStore = useRootStore()
			return rootStore.isPublic
				? `share_${rootStore.shareTokenParam}`
				: state.collectiveId
		},

		indexForCollective() {
			return (collective) => {
				const rootStore = useRootStore()
				return rootStore.isPublic
					? `share_${collective.shareToken}`
					: collective.id
			}
		},

		context(state) {
			const rootStore = useRootStore()
			return {
				isPublic: rootStore.isPublic,
				collectiveId: state.collectiveId,
				shareTokenParam: rootStore.shareTokenParam,
			}
		},

		pagesForCollective: (state) => {
			return (collective) => state.allPages[state.indexForCollective(collective)] || []
		},

		pages: (state) => {
			return state.allPages[state.collectiveIndex] || []
		},
		trashPages: (state) => {
			return state.allTrashPages[state.collectiveIndex] || []
		},

		attachments: (state) => {
			return state.allAttachments[state.collectiveIndex]?.[state.currentPageId] || []
		},

		pagesLoaded: (state) => {
			return state.pages.length > 0
		},

		isLandingPage: (state) => {
			const rootStore = useRootStore()
			const collectivesStore = useCollectivesStore()

			if (collectivesStore.currentCollectiveIsPageShare) {
				// No landing page in page shares
				return false
			}

			return (!rootStore.pageId && !rootStore.pageParam)
				|| rootStore.pageId === state.rootPage.id
				|| rootStore.pageParam === INDEX_PAGE
		},
		isIndexPage(state) {
			return state.currentPage.fileName === INDEX_PAGE + PAGE_SUFFIX
		},

		rootPage(state) {
			const collectivesStore = useCollectivesStore()
			return collectivesStore.currentCollectiveIsPageShare
				? state.pages[0]
				: state.pages.find((p) => (p.parentId === 0))
		},

		currentPageIds(state) {
			const rootStore = useRootStore()
			// Return root page
			if ((!rootStore.pageId && !rootStore.pageParam)
				|| rootStore.pageParam === INDEX_PAGE) {
				return [state.rootPage.id]
			}

			const pageIds = []
			if (rootStore.pageId) {
				let pageId = rootStore.pageId
				do {
					const page = state.pageById(pageId)
					pageIds.unshift(page.id)
					pageId = page.parentId
				} while (pageId)
				return pageIds
			}

			// Iterate through all path levels to find the correct page
			const parts = rootStore.pageParam.split('/').filter(Boolean)
			let page = state.rootPage
			for (const i in parts) {
				page = state.pages.find((p) => (p.parentId === page.id && p.title === parts[i]))
				if (page) {
					pageIds.push(page.id)
				} else {
					return []
				}
			}
			return pageIds
		},

		currentPage(state) {
			const rootStore = useRootStore()
			if (rootStore.pageId) {
				return state.pageById(rootStore.pageId)
			} else if (rootStore.fileIdQuery) {
				return state.pageById(Number(rootStore.fileIdQuery))
			}
			return state.pages.find((p) => (p.id === state.currentPageIds[state.currentPageIds.length - 1]))
		},

		currentPageId(state) {
			return state.currentPage.id
		},

		pageById(state) {
			return (pageId) => {
				return state.pages.find((p) => p.id === pageId)
			}
		},

		pagePath: (state) => (page) => {
			const collectivesStore = useCollectivesStore()

			// Landing page
			if (page.id === state.rootPage.id) {
				return collectivesStore.currentCollectivePath
			}

			if (!page.slug) {
				const { filePath, fileName, title, id } = page
				const titlePart = fileName !== INDEX_PAGE + PAGE_SUFFIX && title

				const pagePath = [...filePath.split('/'), titlePart]
					.filter(Boolean).map(encodeURIComponent).join('/')
				return pagePath
					? `${collectivesStore.currentCollectivePath}/${pagePath}?fileId=${id}`
					: collectivesStore.currentCollectivePath
			}

			return `${collectivesStore.currentCollectivePath}/${page.slug}-${page.id}`
		},

		currentPagePath(state) {
			return state.pagePath(state.currentPage)
		},

		pagePathTitle: () => (page) => {
			const { filePath, fileName, title } = page
			const titlePart = fileName !== INDEX_PAGE + PAGE_SUFFIX && title
			return [filePath, titlePart].filter(Boolean).join('/')
		},

		pageFilePath: () => (page) => {
			return [
				page.collectivePath,
				page.filePath,
				page.fileName,
			].filter(Boolean).join('/')
		},

		currentPageFilePath(state) {
			return state.pageFilePath(state.currentPage)
		},

		pageDavPath: (state) => (page) => {
			const rootStore = useRootStore()
			const parts = state.pageFilePath(page).split('/')
			if (!rootStore.isPublic) {
				parts.unshift(getCurrentUser().uid)
			}
			return parts
				.map((p) => encodeURIComponent(p))
				.join('/')
		},

		currentPageDavPath(state) {
			return state.pageDavPath(state.currentPage)
		},

		pageDavUrl: (state) => (page) => {
			const rootStore = useRootStore()
			return rootStore.isPublic
				? generateRemoteUrl(`webdav/${state.pageDavPath(page)}`)
						.replace('/remote.php', '/public.php')
				: generateRemoteUrl(`dav/files/${state.pageDavPath(page)}`)
		},

		currentPageDavUrl(state) {
			const rootStore = useRootStore()
			return rootStore.isPublic
				? generateRemoteUrl(`webdav/${state.currentPageDavPath}`)
						.replace('/remote.php', '/public.php')
				: generateRemoteUrl(`dav/files/${state.currentPageDavPath}`)
		},

		currentFileIdPage(state) {
			const rootStore = useRootStore()
			const fileId = Number(rootStore.fileIdQuery)
			return state.pages.find((p) => (p.id === fileId))
		},

		hasSubpages(state) {
			return (pageId) => {
				return state.pages.filter((p) => p.parentId === pageId).length > 0
			}
		},

		favoritePages(state) {
			const collectivesStore = useCollectivesStore()
			const favoritePages = collectivesStore.currentCollective.userFavoritePages
			return state.allPagesSorted(state.rootPage.id).filter((p) => favoritePages.includes(p.id))
		},

		hasFavoritePages(state) {
			return state.favoritePages.length > 0
		},

		sortedSubpagesForCollective(state) {
			return (collective, parentId, sortOrder = null) => {
				const parentPage = state.pagesForCollective(collective).find((p) => p.id === parentId)
				const customOrder = parentPage?.subpageOrder || []
				return state.pagesForCollective(collective)
					.filter((p) => p.parentId === parentId)
					// add the index from customOrder
					.map((p) => ({ ...p, index: customOrder.indexOf(p.id) }))
					// sort by given order, fall back to user setting
					.sort(sortOrders[sortOrder] || state.sortOrder)
			}
		},

		sortedSubpages(state) {
			return (parentId, sortOrder) => {
				const collectivesStore = useCollectivesStore()
				return state.sortedSubpagesForCollective(collectivesStore.currentCollective, parentId, sortOrder)
			}
		},

		allPagesSorted(state) {
			const allSubPagesSorted = (pageId) => {
				const res = []
				state.sortedSubpages(pageId).forEach((element) => {
					res.push(element)
					res.push(...allSubPagesSorted(element.id))
				})
				return res
			}
			return allSubPagesSorted
		},

		visibleSubpages: (state) => (parentId) => {
			return state.sortedSubpages(parentId)
		},

		pagesTreeWalk: (state) => (parentId = 0) => {
			const pages = []
			for (const page of state.visibleSubpages(parentId)) {
				pages.push(page)
				for (const subpage of state.pagesTreeWalk(page.id)) {
					pages.push(subpage)
				}
			}
			return pages
		},

		pageParent: (state) => (pageId) => {
			return state.pages.find((p) => (p.id === pageId)).parentId
		},

		pageParentsForCollective(state) {
			return (collective, pageId) => {
				const pages = []
				while (pageId !== state.rootPage.id) {
					const page = state.pagesForCollective(collective).find((p) => (p.id === pageId))
					if (!page) {
						break
					}
					pages.unshift(page)
					pageId = page.parentId
				}
				return pages
			}
		},

		pageParents(state) {
			return (pageId) => {
				const collectivesStore = useCollectivesStore()
				return state.pageParentsForCollective(collectivesStore.currentCollective, pageId)
			}
		},

		sortOrder(state) {
			return sortOrders[state.sortByOrder] || sortOrders.byOrder
		},

		sortByDefault() {
			const collectivesStore = useCollectivesStore()
			return sortOrders.pageOrdersByNumber[collectivesStore.currentCollective.userPageOrder]
		},

		sortByOrder(state) {
			return state.sortBy ? state.sortBy : state.sortByDefault
		},

		disableDragndropSortOrMove(state) {
			const rootStore = useRootStore()
			const collectivesStore = useCollectivesStore()
			// Disable for readonly collective
			return !collectivesStore.currentCollectiveCanEdit
				// Disable if a page list is loading (e.g. when page move is pending)
				|| rootStore.loading('pagelist-nodrag')
				// For now also disable in alternative page order view
				// TODO: Smoothen UX if allowed to move but not to sort with alternative page orders
				|| (state.sortByOrder !== 'byOrder')
		},

		newPageId(state) {
			return state.newPage?.id
		},

		newPagePath(state) {
			return state.newPage && state.pagePath(state.newPage)
		},

		pageTitle(state) {
			const rootStore = useRootStore()
			return (pageId) => {
				const page = state.pages.find((p) => p.id === pageId)
				return (page.parentId === 0) ? rootStore.collectiveParam : page.title
			}
		},

		getTextMode: (state) => state.textMode[state.currentPageId] ?? pageModes.MODE_VIEW,
		isTextEdit: (state) => state.getTextMode === pageModes.MODE_EDIT,
		isTextView: (state) => state.getTextMode === pageModes.MODE_VIEW,

		isCollapsed(state) {
			// Default to 'true' if unset
			return (pageId) => state.collapsed[pageId] ?? true
		},

		hasOutline(state) {
			// Default to 'false' if unset
			return (pageId) => state.outline[pageId] ?? false
		},

		keptSortable(state) {
			return (pageId) => state.pages.find((p) => p.id === pageId)?.keepSortable
		},

		subpageOrder(state) {
			return (pageId) => state.pages.find((p) => p.id === pageId).subpageOrder
		},

		subpageOrderIndex(state) {
			return (parentId, pageId) => {
				const parentSubpageOrder = state.subpageOrder(parentId)
				return parentSubpageOrder.indexOf(pageId)
			}
		},

		sortedTrashPages(state) {
			return state.trashPages.sort((a, b) => b.trashTimestamp - a.trashTimestamp)
		},

		recentPages(state) {
			return state.pages
				.slice()
				.sort(sortOrders.byTimeAsc)
		},

		recentPagesUserIds(state) {
			return state.recentPages
				// take only userIds
				.map((p) => p.lastUserId)
				// filter out duplicates
				.filter((value, index, array) => {
					return array.indexOf(value) === index
				})
		},

		backlinks(state) {
			return (pageId) => {
				return state.pages.filter((p) => p.linkedPageIds.includes(pageId))
			}
		},

		// TODO: rename
		title: (state) => {
			const collectivesStore = useCollectivesStore()
			return state.isLandingPage
				? collectivesStore.currentCollective.name
				: state.currentPage.title
		},
	},

	actions: {
		updateSubpageOrder({ parentId, subpageOrder }) {
			if (this.allPages[this.collectiveIndex].find((p) => p.id === parentId)) {
				this.allPages[this.collectiveIndex].find((p) => p.id === parentId).subpageOrder = subpageOrder
			}
		},

		/**
		 * Add pageId to subpageOrder of parent page at specified index (only in frontend store)
		 * If no index is provided, add to the beginning of the list.
		 *
		 * Build subpageOrder of parent page to maintain the displayed order. If no subpageOrder
		 * was stored before or it missed pages, pages would jump around otherwise.
		 *
		 * @param {object} object parameters object
		 * @param {number} object.parentId ID of the parent page
		 * @param {number} object.pageId ID of the page to remove
		 * @param {number|undefined} object.newIndex New index for pageId (prepend by default)
		 */
		addToSubpageOrder({ parentId, pageId, newIndex = 0 }) {
			// Get current subpage order of parentId
			const subpageOrder = this.sortedSubpages(parentId, 'byOrder')
				.map((p) => p.id)
				.filter((id) => (id !== pageId))

			// Add pageId to index position
			subpageOrder.splice(newIndex, 0, pageId)

			this.updateSubpageOrder({ parentId, subpageOrder })
		},

		/**
		 * Delete pageId from subpageOrder of parent page (only in frontend store)
		 *
		 * @param {object} object parameters object
		 * @param {number} object.parentId ID of the parent page
		 * @param {number} object.pageId ID of the page to remove
		 */
		deleteFromSubpageOrder({ parentId, pageId }) {
			const parentPage = this.allPages[this.collectiveIndex].find((p) => (p.id === parentId))
			this.updateSubpageOrder({ parentId, subpageOrder: parentPage.subpageOrder.filter((id) => (id !== pageId)) })
		},

		setPageOrder(order) {
			this.sortBy = order
		},

		setTextEdit() { set(this.textMode, this.currentPageId, pageModes.MODE_EDIT) },
		setTextView() { set(this.textMode, this.currentPageId, pageModes.MODE_VIEW) },

		toggleCollapsed(pageId) {
			// Default to 'false' if unset
			set(this.collapsed, pageId, this.collapsed[pageId] === undefined ? false : !this.collapsed[pageId])
		},
		collapse(pageId) { set(this.collapsed, pageId, true) },
		expand(pageId) { set(this.collapsed, pageId, false) },

		toggleOutline(pageId) {
			// Default to 'true' if unset
			set(this.outline, pageId, this.outline[pageId] === undefined ? true : !this.outline[pageId])
		},
		setOutlineForCurrentPage(visible) {
			set(this.outline, this.currentPageId, visible)
		},

		expandParents(pageId) {
			for (const page of this.pageParents(pageId)) {
				this.expand(page.id)
			}
		},

		setNewPageParentId(id) {
			this.newPageParentId = id
		},

		setHighlightPageId(pageId) {
			this.highlightPageId = pageId
		},

		setHighlightAnimationPageId(pageId) {
			this.highlightAnimationPageId = pageId
		},

		setDragoverTargetPage(bool) {
			this.isDragoverTargetPage = bool
		},

		setDraggedPageId(pageId) {
			this.draggedPageId = pageId
		},

		/**
		 * Get list of all pages for a collective
		 *
		 * @param {object} collective The collective
		 * @param {boolean} setLoading Whether to set loading pagelist
		 */
		async getPagesForCollective(collective, setLoading = true) {
			const rootStore = useRootStore()
			if (setLoading && this.pagesForCollective(collective).length === 0) {
				rootStore.load(`pagelist-${collective.id}`)
			}
			const context = {
				isPublic: false,
				collectiveId: collective.id,
				shareTokenParam: null,
			}
			const response = await api.getPages(context)
			set(this.allPages, this.indexForCollective(collective), response.data.ocs.data.pages)
			rootStore.done(`pagelist-${collective.id}`)
		},

		/**
		 * Get list of all pages for current collective
		 *
		 * @param {boolean} setLoading Whether to set loading pagelist
		 */
		async getPages(setLoading = true) {
			const rootStore = useRootStore()
			if (setLoading && !this.pagesLoaded) {
				rootStore.load('pagelist')
			}
			const response = await api.getPages(this.context)
			set(this.allPages, this.collectiveIndex, response.data.ocs.data.pages)
			rootStore.done('pagelist')
		},

		/**
		 * Get list of all pages in trash
		 */
		async getTrashPages() {
			const rootStore = useRootStore()
			rootStore.load('pageTrash')
			const response = await api.getTrashPages(this.context)
			set(this.allTrashPages, this.collectiveIndex, response.data.ocs.data.pages)
			rootStore.done('pageTrash')
		},

		_updatePageState(page, collectiveIndex = this.collectiveIndex) {
			const index = this.allPages[collectiveIndex].findIndex((p) => p.id === page.id)
			if (index > -1) {
				this.allPages[collectiveIndex].splice(index, 1, page)
			}
		},

		/**
		 * Get a single page and update it in the store
		 *
		 * @param {number} pageId Page ID
		 */
		async getPage(pageId) {
			const response = await api.getPage(this.context, pageId)
			this._updatePageState(response.data.ocs.data.page)
		},

		/**
		 * Create a new page
		 *
		 * @param {object} page Properties for the new page (title for now)
		 */
		async createPage(page) {
			const rootStore = useRootStore()
			// Will be done when the title form has focus.
			rootStore.load('newPageTitle')
			// Will be done when the editor is loaded.
			rootStore.load('newPageContent')

			const response = await api.createPage(this.context, page)
			// Add new page to the beginning of pages array
			const newPage = response.data.ocs.data.page
			updateOrAddTo(this.allPages[this.collectiveIndex], newPage)
			this.addToSubpageOrder({ parentId: newPage.parentId, pageId: newPage.id })
			this.newPage = response.data.ocs.data.page
		},

		/**
		 * Touch current page
		 */
		async touchPage() {
			const response = await api.touchPage(this.context, this.currentPageId)
			this._updatePageState(response.data.ocs.data.page)
		},

		/**
		 * Rename the current page
		 *
		 * @param {string} newTitle new title for the page
		 */
		async renamePage(newTitle) {
			const response = await api.renamePage(this.context, this.currentPageId, newTitle)
			this._updatePageState(response.data.ocs.data.page)
		},

		/**
		 * Copy page to another parent
		 *
		 * @param {object} page the page
		 * @param {number} page.newParentId ID of the new parent page
		 * @param {number} page.pageId ID of the page
		 * @param {number} page.index index for subpageOrder of parent page
		 */
		async copyPage({ newParentId, pageId, index }) {
			const rootStore = useRootStore()
			rootStore.load('pagelist-nodrag')
			const page = { ...this.allPages[this.collectiveIndex].find((p) => p.id === pageId) }

			// Keep subpage list of old parent page in DOM to prevent a race condition with sortableJS
			const oldParentId = page.parentId

			// Increment index by one if copying to same folder with an index after the original
			if (oldParentId === newParentId && index >= this.subpageOrderIndex(newParentId, pageId)) {
				index += 1
			}

			try {
				await api.copyPage(this.context, pageId, newParentId, index)
				// Reload the page list to make new page appear
				await this.getPages(false)
			} finally {
				rootStore.done('pagelist-nodrag')
			}
		},

		/**
		 * Move page to another parent
		 *
		 * @param {object} page the page
		 * @param {number} page.newParentId ID of the new parent page
		 * @param {number} page.pageId ID of the page
		 * @param {number} page.index index for subpageOrder of parent page
		 */
		async movePage({ newParentId, pageId, index }) {
			const rootStore = useRootStore()
			rootStore.load('pagelist-nodrag')
			const page = { ...this.allPages[this.collectiveIndex].find((p) => p.id === pageId) }
			const hasSubpages = this.visibleSubpages(pageId).length > 0

			// Save a clone of the page to restore in case of errors
			const pageClone = { ...page }

			// Keep subpage list of old parent page in DOM to prevent a race condition with sortableJS
			const oldParentId = page.parentId
			this.allPages[this.collectiveIndex].find((p) => p.id === oldParentId).keepSortable = true

			// Update page in store first to avoid page order jumping around
			page.parentId = newParentId
			this._updatePageState(page)

			try {
				const response = await api.movePage(this.context, pageId, newParentId, index)
				this._updatePageState(response.data.ocs.data.page)
			} catch (e) {
				this._updatePageState(pageClone)
				throw e
			} finally {
				delete this.allPages[this.collectiveIndex].find((p) => p.id === oldParentId).keepSortable
				rootStore.done('pagelist-nodrag')
			}

			// Reload the page list if moved page had subpages (to get their updated paths)
			if (hasSubpages) {
				await this.getPages(false)
			}
		},

		/**
		 * Copy page to another collective
		 *
		 * @param {object} page the page
		 * @param {number} page.collectiveId ID of the new collective
		 * @param {number} page.newParentId ID of the new parent page
		 * @param {number} page.pageId ID of the page
		 * @param {number} page.index index for subpageOrder of parent page
		 */
		async copyPageToCollective({ collectiveId, newParentId, pageId, index }) {
			const rootStore = useRootStore()
			rootStore.load('pagelist-nodrag')

			await api.copyPageToCollective(this.context, pageId, collectiveId, newParentId, index)
			rootStore.done('pagelist-nodrag')
		},

		/**
		 * Move page to another collective
		 *
		 * @param {object} page the page
		 * @param {number} page.collectiveId ID of the new collective
		 * @param {number} page.newParentId ID of the new parent page
		 * @param {number} page.pageId ID of the page
		 * @param {number} page.index index for subpageOrder of parent page
		 */
		async movePageToCollective({ collectiveId, newParentId, pageId, index }) {
			const rootStore = useRootStore()
			rootStore.load('pagelist-nodrag')
			const page = { ...this.allPages[this.collectiveIndex].find((p) => p.id === pageId) }
			const hasSubpages = this.visibleSubpages(pageId).length > 0

			await api.movePageToCollective(this.context, pageId, collectiveId, newParentId, index)
			removeFrom(this.allPages[this.collectiveIndex], page)
			rootStore.done('pagelist-nodrag')

			// Reload the page list if moved page had subpages (to remove subpages as well)
			if (hasSubpages) {
				await this.getPages(false)
			}
		},

		/**
		 * Set emoji for a page
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page
		 * @param {string} page.emoji emoji for the page
		 */
		async setPageEmoji({ pageId, emoji }) {
			const rootStore = useRootStore()
			rootStore.load(`pageEmoji-${pageId}`)
			try {
				const response = await api.setPageEmoji(this.context, pageId, emoji)
				this._updatePageState(response.data.ocs.data.page)
			} finally {
				rootStore.done(`pageEmoji-${pageId}`)
			}
		},

		/**
		 * Set full width for a page
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page
		 * @param {boolean} page.fullWidthView emoji for the page
		 */
		async setFullWidthView({ pageId, fullWidthView }) {
			const response = await api.setFullWidth(this.context, pageId, fullWidthView)
			this._updatePageState(response.data.ocs.data.page)
		},

		/**
		 * Set subpageOrder for a page
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page
		 * @param {Array} page.subpageOrder subpage order for the page
		 */
		async setPageSubpageOrder({ pageId, subpageOrder }) {
			const rootStore = useRootStore()
			rootStore.load('pagelist-nodrag')
			const page = { ...this.allPages[this.collectiveIndex].find((p) => p.id === pageId) }

			// Save a clone of the page to restore in case of errors
			const pageClone = { ...page }

			// Update page in store first to avoid page order jumping around
			page.subpageOrder = subpageOrder
			this._updatePageState(page)

			try {
				const response = await api.setPageSubpageOrder(
					this.context,
					pageId,
					JSON.stringify(subpageOrder),
				)
				this._updatePageState(response.data.ocs.data.page)
			} catch (e) {
				this._updatePageState(pageClone)
				throw e
			} finally {
				rootStore.done('pagelist-nodrag')
			}
		},

		/**
		 * Add a tag to a page
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page
		 * @param {number} tagId ID of the tag
		 */
		async addPageTag({ pageId }, tagId) {
			const rootStore = useRootStore()
			const page = { ...this.allPages[this.collectiveIndex].find((p) => p.id === pageId) }

			if (page.tags?.includes(tagId)) {
				return
			}

			rootStore.load(`page-tag-${pageId}-${tagId}`)

			// Save a clone of the tags to restore in case of errors
			const tagsClone = [...page.tags]

			// Update page in store first
			page.tags.push(tagId)
			this._updatePageState(page)

			try {
				const response = await api.addPageTag(
					this.context,
					pageId,
					tagId,
				)
				this._updatePageState(response.data.ocs.data.page)
			} catch (e) {
				page.tags = tagsClone
				this._updatePageState(page)
				throw e
			} finally {
				rootStore.done(`page-tag-${pageId}-${tagId}`)
			}
		},

		/**
		 * Remove a tag from a page
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page
		 * @param {number} tagId ID of the tag
		 */
		async removePageTag({ pageId }, tagId) {
			const rootStore = useRootStore()
			const page = { ...this.allPages[this.collectiveIndex].find((p) => p.id === pageId) }

			const tagIndex = page.tags?.indexOf(tagId)
			if (tagIndex === null || tagIndex === -1) {
				return
			}

			rootStore.load(`page-tag-${pageId}-${tagId}`)

			// Save a clone of the tags to restore in case of errors
			const tagsClone = [...page.tags]

			// Update page in store first
			page.tags.splice(tagIndex, 1)
			this._updatePageState(page)

			try {
				const response = await api.removePageTag(
					this.context,
					pageId,
					tagId,
				)
				this._updatePageState(response.data.ocs.data.page)
			} catch (e) {
				page.tags = tagsClone
				this._updatePageState(page)
				throw e
			} finally {
				rootStore.done(`page-tag-${pageId}-${tagId}`)
			}
		},

		/**
		 * Trash the page with the given id
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page
		 */
		async trashPage({ pageId }) {
			const response = await api.trashPage(this.context, pageId)
			const trashPage = response.data.ocs.data.page
			removeFrom(this.allPages[this.collectiveIndex], trashPage)
			updateOrAddTo(this.allTrashPages[this.collectiveIndex], trashPage)
		},

		/**
		 * Restore the page with the given id from trash
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page to restore
		 */
		async restorePage({ pageId }) {
			const response = await api.restorePage(this.context, pageId)
			const trashPage = response.data.ocs.data.page
			updateOrAddTo(this.allPages[this.collectiveIndex], trashPage)
			removeFrom(this.allTrashPages[this.collectiveIndex], trashPage)
		},

		/**
		 * Delete the page with the given id from trash
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page to delete
		 */
		async deletePage({ pageId }) {
			await api.deletePage(this.context, pageId)
			removeFrom(this.allTrashPages[this.collectiveIndex], { id: pageId })
		},

		/**
		 * Update all pages provided, remove those listed as removed
		 *
		 * @param {number} collectiveId ID of the collective to work on
		 * @param {object} changes the page
		 * @param {object[]} changes.pages updated records for pages
		 * @param {number[]} changes.removed ids of all pages that were removed entirely
		 */
		updatePages(collectiveId, { pages, removed }) {
			if (collectiveId !== this.collectiveId) {
				// only handle changes to the current collective
				return
			}
			for (const page of (pages || [])) {
				if (page.filePath === TEMPLATE_PATH || page.filePath.startsWith(TEMPLATE_PATH + '/')) {
					// template pages are handled in the ... templates store.
					continue
				}
				if (page.trashTimestamp) {
					// pages should not be updated in the trash - but better be safe than sorry.
					updateOrAddTo(this.allTrashPages[this.collectiveIndex], page)
					removeFrom(this.allPages[this.collectiveIndex], page)
				} else {
					updateOrAddTo(this.allPages[this.collectiveIndex], page)
					removeFrom(this.allTrashPages[this.collectiveIndex], page)
				}
			}
			for (const id of (removed || [])) {
				removeFrom(this.allTrashPages[this.collectiveIndex], { id })
				removeFrom(this.allPages[this.collectiveIndex], { id })
			}
		},

		/**
		 * Get list of attachments for a page
		 *
		 * @param {object} page Page to get attachments for
		 */
		async getAttachments(page) {
			const response = await api.getPageAttachments(this.context, page.id)
			if (typeof this.allAttachments[this.collectiveIndex] !== 'object') {
				set(this.allAttachments, this.collectiveIndex, {})
			}
			set(this.allAttachments[this.collectiveIndex], page.id, response.data.ocs.data.attachments
				// Disregard deletedAttachments when updating attachments
				.filter((a) => !this.deletedAttachments.map((a) => a.name).includes(a.name)))
			this.deletedAttachments = this.deletedAttachments
				// Only keep deletedAttachments that still exist
				.filter((a) => this.attachments.map((a) => a.name).includes(a.name))
		},

		setAttachmentDeleted(name) {
			const index = this.attachments.findIndex((a) => a.name === name)
			if (index !== -1) {
				const [attachment] = this.allAttachments[this.collectiveIndex][this.currentPageId].splice(index, 1)
				this.deletedAttachments.push(attachment)
			}
		},

		setAttachmentUndeleted(name) {
			const index = this.deletedAttachments.findIndex((a) => a.name === name)
			if (index !== -1) {
				const [attachment] = this.deletedAttachments.splice(index, 1)
				this.allAttachments[this.collectiveIndex][this.currentPageId].push(attachment)
			}
		},

		setAttachmentsLoaded(loaded) {
			this.attachmentsLoaded = loaded
		},

		setAttachmentsError(error) {
			this.attachmentsError = error
		},

		/**
		 *
		 * @param {string} searchString - Content search string
		 */
		async contentSearch(searchString) {
			return await api.contentSearch(this.context, searchString)
		},
	},
})
