/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'
import { getBuilder } from '@nextcloud/browser-storage'
import { getCurrentUser } from '@nextcloud/auth'
import { generateRemoteUrl } from '@nextcloud/router'
import { useRootStore } from './root.js'
import { useCollectivesStore } from './collectives.js'
import { INDEX_PAGE, TEMPLATE_PAGE } from '../constants.js'
/* eslint import/namespace: ['error', { allowComputed: true }] */
import * as sortOrders from '../util/sortOrders.js'
import { pageParents, sortedSubpages } from './pageExtracts.js'
import * as api from '../apis/collectives/index.js'

const persistentStorage = getBuilder('collectives').persist().build()

export const usePagesStore = defineStore('pages', {
	state: () => ({
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
	}),

	getters: {
		context() {
			const rootStore = useRootStore()
			const collectivesStore = useCollectivesStore()
			return {
				isPublic: rootStore.isPublic,
				collectiveId: collectivesStore.currentCollective.id,
				shareTokenParam: rootStore.shareTokenParam,
			}
		},

		isLandingPage: () => {
			const rootStore = useRootStore()
			const collectivesStore = useCollectivesStore()
			return collectivesStore.currentCollectiveIsPageShare
				? false
				: !rootStore.pageParam || rootStore.pageParam === INDEX_PAGE
		},
		isIndexPage: (state) => state.currentPage.fileName === INDEX_PAGE + '.md',
		isTemplatePage: (state) => state.currentPage.title === TEMPLATE_PAGE,

		rootPage(state) {
			const collectivesStore = useCollectivesStore()
			return collectivesStore.currentCollectiveIsPageShare
				? state.pages[0]
				: state.pages.find(p => (p.parentId === 0))
		},

		templatePage: (state) => (parentId) => {
			return state.pages.find(p => (p.parentId === parentId && p.title === TEMPLATE_PAGE))
		},

		currentPageIds(state) {
			const rootStore = useRootStore()
			// Return root page
			if (!rootStore.pageParam
				|| rootStore.pageParam === INDEX_PAGE) {
				return [state.rootPage.id]
			}

			// Iterate through all path levels to find the correct page
			const pageIds = []
			const parts = rootStore.pageParam.split('/').filter(Boolean)
			let page = state.rootPage
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

		currentPage(state) {
			return state.pages.find(p => (p.id === state.currentPageIds[state.currentPageIds.length - 1]))
		},

		pageById(state) {
			return (pageId) => {
				return state.pages.find(p => p.id === pageId)
			}
		},

		pagePath: () => (page) => {
			const rootStore = useRootStore()
			const collectivesStore = useCollectivesStore()
			const collective = collectivesStore.currentCollective.name
			const { filePath, fileName, title, id } = page
			const titlePart = fileName !== INDEX_PAGE + '.md' && title
			// For public collectives, prepend `/p/{shareToken}`
			const pagePath = [
				rootStore.isPublic ? 'p' : null,
				rootStore.isPublic ? rootStore.shareTokenParam : null,
				collective,
				...filePath.split('/'),
				titlePart,
			].filter(Boolean).map(encodeURIComponent).join('/')
			return `/${pagePath}?fileId=${id}`
		},

		pagePathTitle: () => (page) => {
			const { filePath, fileName, title } = page
			const titlePart = fileName !== INDEX_PAGE + '.md' && title
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
				.map(p => encodeURIComponent(p))
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
			return state.pages.find(p => (p.id === fileId))
		},

		hasSubpages(state) {
			return (pageId) => {
				return state.pages.filter(p => p.parentId === pageId).length > 0
			}
		},

		sortedSubpages,

		allPagesSorted(state) {
			const allSubPagesSorted = (pageId) => {
				const res = []
				sortedSubpages(state)(pageId).forEach(element => {
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
			return state.pages.find(p => (p.id === pageId)).parentId
		},

		pageParents,

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
				|| rootStore.loading('pagelist')
				// For now also disable in alternative page order view
				// TODO: Smoothen UX if allowed to move but not to sort with alternative page orders
				|| (state.sortByOrder !== 'byOrder')
		},

		newPagePath(state) {
			return state.newPage && state.pagePath(state.newPage)
		},

		pageTitle(state) {
			const rootStore = useRootStore()
			return pageId => {
				const page = state.pages.find(p => p.id === pageId)
				return (page.parentId === 0) ? rootStore.collectiveParam : page.title
			}
		},

		isCollapsed(state) {
			// Default to 'true' if unset
			return pageId => state.collapsed[pageId] != null ? state.collapsed[pageId] : true
		},

		keptSortable(state) {
			return (pageId) => state.pages.find(p => p.id === pageId)?.keepSortable
		},

		subpageOrder(state) {
			return (pageId) => state.pages.find(p => p.id === pageId).subpageOrder
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
				.filter(p => p.title !== TEMPLATE_PAGE)
				.sort(sortOrders.byTimestamp)
		},

		recentPagesUserIds(state) {
			return state.recentPages
				// take only userIds
				.map(p => p.lastUserId)
				// filter out duplicates
				.filter((value, index, array) => {
					return array.indexOf(value) === index
				})
		},

		isFullWidthView(state) {
			return state.fullWidthPageIds.includes(state.currentPage?.id)
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
		// using camel case name so this works nicely with mapMutations
		unsetPages() {
			this.pages = []
		},

		unsetAttachments() {
			this.attachments = []
			this.deletedAttachments = []
		},

		unsetBacklinks() {
			this.backlinks = []
		},

		updateSubpageOrder({ parentId, subpageOrder }) {
			if (this.pages.find(p => p.id === parentId)) {
				this.pages.find(p => p.id === parentId).subpageOrder = subpageOrder
			}
		},

		setPageOrder(order) {
			this.sortBy = order
		},

		toggleTemplates() {
			this.showTemplates = !this.showTemplates
		},

		toggleCollapsed(pageId) {
			// Default to 'false' if unset
			set(this.collapsed, pageId, this.collapsed[pageId] == null ? false : !this.collapsed[pageId])
		},

		collapse(pageId) { set(this.collapsed, pageId, true) },

		expand(pageId) { set(this.collapsed, pageId, false) },

		expandParents(pageId) {
			for (const page of this.pageParents(pageId)) {
				this.expand(page.id)
			}
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
		 * Get list of all pages for current collective
		 *
		 * @param {boolean} setLoading Whether to set loading('collective')
		 */
		async getPages(setLoading = true) {
			const rootStore = useRootStore()
			if (setLoading) {
				rootStore.load('collective')
			}
			const response = await api.getPages(this.context)
			this.pages = response.data.data
			rootStore.done('collective')
		},

		/**
		 * Get list of all pages in trash
		 */
		async getTrashPages() {
			const rootStore = useRootStore()
			rootStore.load('pageTrash')
			const response = await api.getTrashPages(this.context)
			this.trashPages = response.data.data
			rootStore.done('pageTrash')
		},

		_updatePageState(page) {
			this.pages.splice(
				this.pages.findIndex(p => p.id === page.id),
				1,
				page,
			)
		},

		/**
		 * Get a single page and update it in the store
		 *
		 * @param {number} pageId Page ID
		 */
		async getPage(pageId) {
			const response = await api.getPage(this.context, pageId)
			this._updatePageState(response.data.data)
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
			// We'll be done when the editor is loaded.
			rootStore.load('newPageContent')

			const response = await api.createPage(this.context, page)
			// Add new page to the beginning of pages array
			this.pages.unshift(response.data.data)
			this.newPage = response.data.data
		},

		/**
		 * Create a new template page
		 *
		 * @param {number} parentId ID of parent page for new template
		 */
		async createTemplate(parentId) {
			const rootStore = useRootStore()
			const page = {
				title: TEMPLATE_PAGE,
				parentId,
			}

			// We'll be done when the editor is loaded.
			rootStore.load('newPageContent')

			const response = await api.createPage(this.context, page)
			// Add new page to the beginning of pages array
			this.pages.unshift(response.data.data)
			this.newPage = response.data.data
		},

		/**
		 * Touch current page
		 */
		async touchPage() {
			const response = await api.touchPage(this.context, this.currentPage.id)
			this._updatePageState(response.data.data)
		},

		/**
		 * Rename the current page
		 *
		 * @param {string} newTitle new title for the page
		 */
		async renamePage(newTitle) {
			const response = await api.renamePage(this.context, this.currentPage.id, newTitle)
			this._updatePageState(response.data.data)
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
			rootStore.load('pagelist')
			const page = { ...this.pages.find(p => p.id === pageId) }

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
				rootStore.done('pagelist')
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
			rootStore.load('pagelist')
			const page = { ...this.pages.find(p => p.id === pageId) }
			const hasSubpages = this.visibleSubpages(pageId).length > 0

			// Save a clone of the page to restore in case of errors
			const pageClone = { ...page }

			// Keep subpage list of old parent page in DOM to prevent a race condition with sortableJS
			const oldParentId = page.parentId
			this.pages.find(p => p.id === oldParentId).keepSortable = true

			// Update page in store first to avoid page order jumping around
			page.parentId = newParentId
			this._updatePageState(page)

			try {
				const response = await api.movePage(this.context, pageId, newParentId, index)
				this._updatePageState(response.data.data)
			} catch (e) {
				this._updatePageState(pageClone)
				throw e
			} finally {
				delete this.pages.find(p => p.id === oldParentId).keepSortable
				rootStore.done('pagelist')
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
			rootStore.load('pagelist')

			await api.copyPageToCollective(this.context, pageId, collectiveId, newParentId, index)
			rootStore.done('pagelist')
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
			rootStore.load('pagelist')
			const page = { ...this.pages.find(p => p.id === pageId) }
			const hasSubpages = this.visibleSubpages(pageId).length > 0

			await api.movePageToCollective(this.context, pageId, collectiveId, newParentId, index)
			this.pages.splice(this.pages.findIndex(p => p.id === page.id), 1)
			rootStore.done('pagelist')

			// Reload the page list if moved page had subpages (to remove subpages as well)
			if (hasSubpages) {
				await this.getPages(false)
			}
		},

		/**
		 *
		 * Set emoji for a page
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page
		 * @param {string} page.emoji emoji for the page
		 */
		async setPageEmoji({ pageId, emoji }) {
			const rootStore = useRootStore()
			rootStore.load(`pageEmoji-${pageId}`)
			const response = await api.setPageEmoji(this.context, pageId, emoji)
			this._updatePageState(response.data.data)
			rootStore.done(`pageEmoji-${pageId}`)
		},

		/**
		 *
		 * Set subpageOrder for a page
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page
		 * @param {Array} page.subpageOrder subpage order for the page
		 */
		async setPageSubpageOrder({ pageId, subpageOrder }) {
			const rootStore = useRootStore()
			rootStore.load('pagelist')
			const page = { ...this.pages.find(p => p.id === pageId) }

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
				this._updatePageState(response.data.data)
			} catch (e) {
				this._updatePageState(pageClone)
				throw e
			} finally {
				rootStore.done('pagelist')
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
			const trashPage = response.data.data
			this.pages.splice(this.pages.findIndex(p => p.id === trashPage.id), 1)
			trashPage.trashTimestamp = Date.now() / 1000
			this.trashPages.unshift(trashPage)
		},

		/**
		 * Restore the page with the given id from trash
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page to restore
		 */
		async restorePage({ pageId }) {
			const response = await api.restorePage(this.context, pageId)
			const trashPage = response.data.data
			trashPage.trashTimestamp = null
			this.pages.unshift(trashPage)
			this.trashPages.splice(this.trashPages.findIndex(p => p.id === trashPage.id), 1)
		},

		/**
		 * Delete the page with the given id from trash
		 *
		 * @param {object} page the page
		 * @param {number} page.pageId ID of the page to delete
		 */
		async deletePage({ pageId }) {
			await api.deletePage(this.context, pageId)
			this.trashPages.splice(this.trashPages.findIndex(p => p.id === pageId), 1)
		},

		/**
		 * Get list of attachments for a page
		 *
		 * @param {object} page Page to get attachments for
		 */
		async getAttachments(page) {
			const response = await api.getPageAttachments(this.context, page.id)
			this.attachments = response.data.data
				// Disregard deletedAttachments when updating attachments
				.filter(a => !this.deletedAttachments.map(a => a.name).includes(a.name))
			this.deletedAttachments = this.deletedAttachments
				// Only keep deletedAttachments that still exist
				.filter(a => response.data.data.map(a => a.name).includes(a.name))
		},

		setAttachmentDeleted(name) {
			const index = this.attachments.findIndex(a => a.name === name)
			if (index !== -1) {
				const [attachment] = this.attachments.splice(index, 1)
				this.deletedAttachments.push(attachment)
			}
		},

		setAttachmentUndeleted(name) {
			const index = this.deletedAttachments.findIndex(a => a.name === name)
			if (index !== -1) {
				const [attachment] = this.deletedAttachments.splice(index, 1)
				this.attachments.push(attachment)
			}
		},

		/**
		 * Get list of backlinks for a page
		 *
		 * @param {object} page Page to get backlinks for
		 */
		async getBacklinks(page) {
			const response = await api.getPageBacklinks(this.context, page.id)
			this.backlinks = response.data.data
		},

		/**
		 * Init full width store from browser storage
		 */
		initFullWidthPageIds() {
			this.fullWidthPageIds = JSON.parse(persistentStorage.getItem('text-fullWidthPageIds') ?? '[]')
		},

		/**
		 * Set full width for a page
		 *
		 * @param {boolean} fullWidthView Whether full width view is enabled or not
		 */
		setFullWidthView(fullWidthView) {
			const pageId = this.currentPage.id
			const fullWidthPageIds = JSON.parse(persistentStorage.getItem('text-fullWidthPageIds') ?? '[]')
			if (fullWidthView && !fullWidthPageIds.includes(pageId)) {
				fullWidthPageIds.push(pageId)
				this.fullWidthPageIds = fullWidthPageIds
				persistentStorage.setItem('text-fullWidthPageIds', JSON.stringify(fullWidthPageIds))
			} else if (!fullWidthView && fullWidthPageIds.includes(pageId)) {
				fullWidthPageIds.splice(fullWidthPageIds.indexOf(pageId), 1)
				this.fullWidthPageIds = fullWidthPageIds
				persistentStorage.setItem('text-fullWidthPageIds', JSON.stringify(fullWidthPageIds))
			}
		},

	},
})
