import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { showError, showSuccess } from '@nextcloud/dialogs'
import {
	DELETE_PAGE,
	GET_PAGES,
	MOVE_PAGE,
	NEW_PAGE,
	NEW_TEMPLATE,
	SET_PAGE_EMOJI,
	SET_PAGE_SUBPAGE_ORDER,
} from '../store/actions.js'
import { scrollToPage } from '../util/scrollToElement.js'

export default {
	computed: {
		...mapState({
			pages: (state) => state.pages.pages,
			newPageId: (state) => state.pages.newPage?.id,
		}),

		...mapGetters([
			'currentCollective',
			'currentPage',
			'newPagePath',
			'pagePath',
			'pageTitle',
			'sortedSubpages',
			'templatePage',
		]),
	},

	methods: {
		...mapMutations([
			'done',
			'expand',
			'load',
		]),

		...mapActions({
			dispatchGetPages: GET_PAGES,
			dispatchNewPage: NEW_PAGE,
			dispatchNewTemplate: NEW_TEMPLATE,
			dispatchSetPageEmoji: SET_PAGE_EMOJI,
			dispatchSetPageSubpageOrder: SET_PAGE_SUBPAGE_ORDER,
			dispatchMovePage: MOVE_PAGE,
			dispatchDeletePage: DELETE_PAGE,
		}),

		/**
		 * Open existing or create new template page
		 *
		 * @param {number} parentId ID of the parent page
		 */
		async editTemplate(parentId) {
			const templatePage = this.templatePage(parentId)
			if (templatePage) {
				this.$router.push(this.pagePath(templatePage))
				if (this.showTemplates) {
					this.$nextTick(() => scrollToPage(templatePage.id))
				}
				return
			}

			try {
				await this.dispatchNewTemplate(parentId)
				this.$router.push(this.newPagePath)
				this.expand(parentId)
				if (this.showTemplates) {
					this.$nextTick(() => scrollToPage(this.newPageId))
				}

				// Parents location changes when the first subpage is created.
				this.dispatchGetPages()
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
		},

		/**
		 * Create a new page and focus the page automatically
		 *
		 * @param {number} parentId ID of the parent page
		 */
		async newPage(parentId) {
			const page = {
				title: t('collectives', 'New Page'),
				parentId,
			}
			try {
				await this.dispatchNewPage(page)
				this.$router.push(this.newPagePath)
				this.expand(parentId)
				this.$nextTick(() => scrollToPage(this.newPageId))

				// Parents location changes when the first subpage is created.
				this.dispatchGetPages()
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
		 * @param {number} parentId ID of the parent page
		 * @param {number} pageId ID of the page
		 * @param {string} emoji Emoji for the page
		 */
		async setEmoji(parentId, pageId, emoji) {
			try {
				await this.dispatchSetPageEmoji({ parentId, pageId, emoji })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not save emoji for page'))
			}
		},

		/**
		 * Move a page to another parent
		 *
		 * @param {number} oldParentId ID of the old parent page
		 * @param {number} newParentId ID of the new parent page
		 * @param {number} pageId ID of the page
		 * @param {number} newIndex New index for pageId
		 */
		async movePage(oldParentId, newParentId, pageId, newIndex) {
			// Add page to subpageOrder of new parent first to ensure correct sorting
			// Don't await response to prevent jumping around
			this.subpageOrderAdd(newParentId, pageId, newIndex)

			// Move subpage to new parent
			try {
				this.load('page')
				await this.dispatchMovePage({ newParentId, pageId })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not move page'))
				return
			} finally {
				this.done('page')
			}

			// Remove page from subpageOrder of old parent last (ensures correct sorting in case of errors)
			await this.subpageOrderDelete(oldParentId, pageId)

			showSuccess(t('collectives', `Page ${this.pageTitle(pageId)} moved to ${this.pageTitle(newParentId)}`))
		},

		/**
		 * Delete the current page,
		 * remove it from the frontend and show a hint
		 *
		 * @param {number} parentId ID of the parent page
		 * @param {number} pageId ID of the page
		 */
		async deletePage(parentId, pageId) {
			const currentPageId = this.currentPage?.id

			try {
				await this.dispatchDeletePage({ parentId, pageId })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not delete the page'))
				return
			}

			// Redirect to landing page if currentPage got deleted
			if (currentPageId === pageId) {
				this.$router.push(`/${encodeURIComponent(this.currentCollective.name)}`)
			}

			// Delete pageId from parent page subpageOrder
			await this.subpageOrderDelete(parentId, pageId)

			showSuccess(t('collectives', 'Page deleted'))
		},

		/**
		 * Delete pageId from subpageOrder of parent page
		 *
		 * @param {number} parentId ID of the parent page
		 * @param {number} pageId ID of the page to remove
		 */
		async subpageOrderDelete(parentId, pageId) {
			const parentPage = this.pages.find(p => (p.id === parentId))
			const subpageOrder = parentPage.subpageOrder
				.filter(id => (id !== pageId))

			try {
				await this.dispatchSetPageSubpageOrder({
					parentId: parentPage.parentId,
					pageId: parentId,
					subpageOrder,
				})
			} catch (e) {
				showError(t('collectives', 'Could not change page order'))
				throw e
			}
		},

		/**
		 * Add pageId to subpageOrder of parent page at specified index
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
			const parentPage = this.pages.find(p => (p.id === parentId))

			// Get current subpage order of parentId
			const subpageOrder = this.sortedSubpages(parentId, 'byOrder')
				.map(p => p.id)
				.filter(id => (id !== pageId))

			// Add pageId to index position
			subpageOrder.splice(newIndex, 0, pageId)

			try {
				await this.dispatchSetPageSubpageOrder({
					parentId: parentPage.parentId,
					pageId: parentId,
					subpageOrder,
				})
			} catch (e) {
				showError(t('collectives', 'Could not change page order'))
				throw e
			}
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
			console.debug('subpageOrderUpdate', {parentId, pageId, newIndex})
			const parentPage = this.pages.find(p => (p.id === parentId))
			const subpageOrder = this.sortedSubpages(parentId)
				.map(p => p.id)
			subpageOrder.splice(subpageOrder.findIndex(id => id === pageId), 1)
			subpageOrder.splice(newIndex, 0, pageId)

			try {
				await this.dispatchSetPageSubpageOrder({
					parentId: parentPage.parentId,
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
