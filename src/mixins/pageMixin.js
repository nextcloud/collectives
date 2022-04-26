import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { showError } from '@nextcloud/dialogs'
import { GET_PAGES, NEW_PAGE, NEW_TEMPLATE } from '../store/actions'
import { scrollToPage } from '../util/scrollToElement'

export default {
	computed: {
		...mapState({
			newPageId: (state) => state.pages.newPage.id,
		}),

		...mapGetters([
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
	},
}
