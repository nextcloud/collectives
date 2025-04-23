/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { scrollToPage } from '../util/scrollToElement.js'

export default {
	computed: {
		...mapState(useCollectivesStore, [
			'collectiveTitle',
			'currentCollective',
			'currentCollectivePath',
		]),
		...mapState(usePagesStore, [
			'currentPage',
			'newPageId',
			'newPagePath',
			'pageById',
			'pagePath',
			'pageTitle',
			'pages',
			'sortedSubpages',
		]),
	},

	methods: {
		...mapActions(useRootStore, ['done', 'load']),
		...mapActions(usePagesStore, [
			'expand',
			'updateSubpageOrder',
			'getPages',
			'createPage',
			'setPageEmoji',
			'setPageSubpageOrder',
			'copyPage',
			'movePage',
			'copyPageToCollective',
			'movePageToCollective',
			'trashPage',
		]),

		/**
		 * Create a new page and focus the page automatically
		 *
		 * @param {number} parentId ID of the parent page
		 * @param {null|number} templateId ID of the template to use
		 */
		async newPage(parentId, templateId = null) {
			const page = {
				title: t('collectives', 'New page'),
				parentId,
				templateId,
			}
			try {
				await this.createPage(page)
				this.$router.push(this.newPagePath)
				this.expand(parentId)
				this.$nextTick(() => scrollToPage(this.newPageId))

				// Parents location changes when the first subpage is created.
				this.getPages(false)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}

			// Append new page to parent page subpageOrder
			await this.subpageOrderAdd(parentId, this.newPageId)
		},

		/**
		 * Set emoji for a page
		 *
		 * @param {number} pageId ID of the page
		 * @param {string} emoji Emoji for the page
		 */
		async setEmoji(pageId, emoji) {
			try {
				await this.setPageEmoji({ pageId, emoji })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not save emoji for page'))
			}
		},

		/**
		 * Copy a page to another parent
		 *
		 * @param {number} oldParentId ID of the old parent page
		 * @param {number} newParentId ID of the new parent page
		 * @param {number} pageId ID of the page
		 * @param {number} newIndex New index for pageId
		 */
		async copy(oldParentId, newParentId, pageId, newIndex) {
			// Copy subpage to new parent
			try {
				this.load('currentPage')
				await this.copyPage({ newParentId, pageId, index: newIndex })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not copy page'))
				return
			} finally {
				this.done('currentPage')
			}

			showSuccess(t('collectives', `Page ${this.pageTitle(pageId)} copied to ${this.pageTitle(newParentId)}`))
		},

		/**
		 * Move a page to another parent
		 *
		 * @param {number} oldParentId ID of the old parent page
		 * @param {number} newParentId ID of the new parent page
		 * @param {number} pageId ID of the page
		 * @param {number} newIndex New index for pageId
		 */
		async move(oldParentId, newParentId, pageId, newIndex) {
			const currentPageId = this.currentPage?.id

			// Add page to subpageOrder of new parent first for instant UI feedback
			this.subpageOrderAdd(newParentId, pageId, newIndex)

			// Move subpage to new parent
			try {
				this.load('currentPage')
				await this.movePage({ newParentId, pageId, index: newIndex })
			} catch (e) {
				showError(t('collectives', 'Could not move page'))
				return
			} finally {
				this.done('currentPage')
			}

			// Redirect to new page path if currentPage got moved
			if (currentPageId === pageId) {
				this.$router.replace(this.pagePath(this.pageById(currentPageId)))
			}

			// Remove page from subpageOrder of old parent last
			this.subpageOrderDelete(oldParentId, pageId)

			showSuccess(t('collectives', `Page ${this.pageTitle(pageId)} moved to ${this.pageTitle(newParentId)}`))
		},

		/**
		 * Copy a page to another collective
		 *
		 * @param {number} collectiveId ID of the new collective
		 * @param {number} oldParentId ID of the old parent page
		 * @param {number} newParentId ID of the new parent page
		 * @param {number} pageId ID of the page
		 * @param {number} newIndex New index for pageId
		 */
		async copyToCollective(collectiveId, oldParentId, newParentId, pageId, newIndex) {
			const pageTitle = this.pageTitle(pageId)

			// Copy subpage to new collective
			try {
				await this.copyPageToCollective({ collectiveId, newParentId, pageId, index: newIndex })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not copy page to another collective'))
				return
			}

			showSuccess(t('collectives', `Page ${pageTitle} copied to collective ${this.collectiveTitle(collectiveId)}`))
		},

		/**
		 * Move a page to another collective
		 *
		 * @param {number} collectiveId ID of the new collective
		 * @param {number} oldParentId ID of the old parent page
		 * @param {number} newParentId ID of the new parent page
		 * @param {number} pageId ID of the page
		 * @param {number} newIndex New index for pageId
		 */
		async moveToCollective(collectiveId, oldParentId, newParentId, pageId, newIndex) {
			const currentPageId = this.currentPage?.id
			const pageTitle = this.pageTitle(pageId)

			// Move subpage to new collective
			try {
				await this.movePageToCollective({ collectiveId, newParentId, pageId, index: newIndex })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not move page to another collective'))
				return
			}

			// Redirect to root page if currentPage got moved
			if (currentPageId === pageId) {
				this.$router.replace(this.currentCollectivePath)
			}

			// Remove page from subpageOrder of old parent last
			this.subpageOrderDelete(oldParentId, pageId)

			showSuccess(t('collectives', `Page ${pageTitle} moved to collective ${this.collectiveTitle(collectiveId)}`))
		},

		/**
		 * Delete the current page,
		 * remove it from the frontend and show a hint
		 *
		 * @param {number} pageId ID of the page
		 */
		async deletePage(pageId) {
			const currentPageId = this.currentPage?.id

			try {
				await this.trashPage({ pageId })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not delete the page'))
				return
			}

			// Redirect to root page if currentPage got deleted
			if (currentPageId === pageId) {
				this.$router.push(`/${encodeURIComponent(this.currentCollective.name)}`)
			}

			emit('collectives:page-list:page-trashed')
			showSuccess(t('collectives', 'Page deleted'))
		},

		/**
		 * Delete pageId from subpageOrder of parent page (only in frontend store)
		 *
		 * @param {number} parentId ID of the parent page
		 * @param {number} pageId ID of the page to remove
		 */
		subpageOrderDelete(parentId, pageId) {
			const parentPage = this.pages.find(p => (p.id === parentId))
			this.updateSubpageOrder({ parentId, subpageOrder: parentPage.subpageOrder.filter(id => (id !== pageId)) })
		},

		/**
		 * Add pageId to subpageOrder of parent page at specified index (only in frontend store)
		 * If no index is provided, add to the beginning of the list.
		 *
		 * Build subpageOrder of parent page to maintain the displayed order. If no subpageOrder
		 * was stored before or it missed pages, pages would jump around otherwise.
		 *
		 * @param {number} parentId ID of the parent page
		 * @param {number} pageId ID of the page to remove
		 * @param {number} newIndex New index for pageId (prepend by default)
		 */
		async subpageOrderAdd(parentId, pageId, newIndex = 0) {
			// Get current subpage order of parentId
			const subpageOrder = this.sortedSubpages(parentId, 'byOrder')
				.map(p => p.id)
				.filter(id => (id !== pageId))

			// Add pageId to index position
			subpageOrder.splice(newIndex, 0, pageId)

			this.updateSubpageOrder({ parentId, subpageOrder })
		},

		/**
		 * Move pageId to new index in subpageOrder of parent page
		 *
		 * Build subpageOrder of parent page to maintain the displayed order. If no subpageOrder
		 * was stored before or it missed pages, pages would jump around otherwise.
		 *
		 * @param {number} parentId ID of the parent page
		 * @param {number} pageId ID of the page to remove
		 * @param {number} newIndex New index for pageId
		 */
		async subpageOrderUpdate(parentId, pageId, newIndex) {
			const subpageOrder = this.sortedSubpages(parentId)
				.map(p => p.id)
			subpageOrder.splice(subpageOrder.findIndex(id => id === pageId), 1)
			subpageOrder.splice(newIndex, 0, pageId)

			try {
				await this.setPageSubpageOrder({
					pageId: parentId,
					subpageOrder,
				})
			} catch (e) {
				showError(t('collectives', 'Could not change page order'))
				throw e
			}
		},
	},
}
