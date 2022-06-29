import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { DELETE_PAGE, GET_PAGES, NEW_PAGE, NEW_TEMPLATE, SET_PAGE_EMOJI } from '../store/actions.js'
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
			dispatchDeletePage: DELETE_PAGE,
		}),

		/**
		 * Open existing or create new template page
		 *
		 * @param {number} parentPageId ID of the parent page
		 */
		async editTemplate(parentPageId) {
			const templatePage = this.templatePage(parentPageId)
			if (templatePage) {
				this.$router.push(this.pagePath(templatePage))
				if (this.showTemplates) {
					this.$nextTick(() => scrollToPage(templatePage.id))
				}
				return
			}

			try {
				await this.dispatchNewTemplate(parentPageId)
				this.$router.push(this.newPagePath)
				this.expand(parentPageId)
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
		 * @param {number} parentPageId ID of the parent page
		 */
		async newPage(parentPageId) {
			const page = {
				title: t('collectives', 'New Page'),
				parentId: parentPageId,
			}
			try {
				await this.dispatchNewPage(page)
				this.$router.push(this.newPagePath)
				this.expand(parentPageId)
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
		 * @param {number} parentPageId ID of the parent page
		 * @param {number} pageId ID of the page
		 * @param {string} emoji Emoji for the page
		 */
		async setEmoji(parentPageId, pageId, emoji) {
			try {
				await this.dispatchSetPageEmoji({ parentPageId, pageId, emoji })
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not save emoji for page'))
			}
		},

		/**
		 * Delete the current page,
		 * remove it from the frontend and show a hint
		 *
		 * @param {number} parentPageId ID of the parent page
		 * @param {number} pageId ID of the page
		 */
		async deletePage(parentPageId, pageId) {
			const currentPageId = this.currentPage?.id

			try {
				await this.dispatchDeletePage({ parentPageId, pageId })
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
