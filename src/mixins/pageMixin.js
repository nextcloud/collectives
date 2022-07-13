import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { DELETE_PAGE, GET_PAGES, MOVE_PAGE, NEW_PAGE, NEW_TEMPLATE, SET_PAGE_EMOJI } from '../store/actions.js'
import { scrollToPage } from '../util/scrollToElement.js'

export default {
	computed: {
		...mapState({
			newPageId: (state) => state.pages.newPage.id,
		}),

		...mapGetters([
			'currentCollective',
			'currentPage',
			'newPagePath',
			'pagePath',
			'templatePage',
		]),
	},

	methods: {
		...mapMutations([
			'expand',
		]),

		...mapActions({
			dispatchGetPages: GET_PAGES,
			dispatchNewPage: NEW_PAGE,
			dispatchNewTemplate: NEW_TEMPLATE,
			dispatchSetPageEmoji: SET_PAGE_EMOJI,
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
		 * @param {number} newParentPageId ID of the new parent page
		 * @param {number} pageId ID of the page
		 */
		async movePage(newParentPageId, pageId) {
			try {
				await this.dispatchMovePage({ newParentPageId, pageId })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not move page'))
			}
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
			showSuccess(t('collectives', 'Page deleted'))
		},
	},
}
