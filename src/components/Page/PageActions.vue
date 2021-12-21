<template>
	<Actions>
		<ActionLink :href="filesUrl"
			icon="icon-files-dark"
			:close-after-click="true">
			{{ t('collectives', 'Show in Files') }}
		</ActionLink>
		<ActionButton v-if="!isTemplatePage"
			icon="icon-pages-template"
			class="action-button__template"
			:close-after-click="true"
			@click="editTemplate">
			{{ t('collectives', 'Edit template for subpages') }}
		</ActionButton>
		<ActionButton v-if="!landingPage"
			icon="icon-delete"
			:close-after-click="true"
			@click="deletePage">
			{{ t('collectives', 'Delete page') }}
		</ActionButton>
	</Actions>
</template>

<script>
import { showSuccess, showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import { NEW_TEMPLATE, DELETE_PAGE, GET_PAGES } from '../../store/actions'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'PageActions',

	components: {
		Actions,
		ActionButton,
		ActionLink,
	},

	computed: {
		...mapGetters([
			'currentPage',
			'currentCollective',
			'isTemplatePage',
			'landingPage',
			'pagePath',
			'templatePage',
		]),

		page() {
			return this.currentPage
		},

		filesUrl() {
			return generateUrl(`/apps/files/?fileid=${this.page.id}`)
		},
	},

	methods: {
		...mapMutations(['expand']),

		/**
		 * Delete the current page,
		 * remove it from the frontend and show a hint
		 */
		async deletePage() {
			try {
				await this.$store.dispatch(DELETE_PAGE)
				this.$router.push(`/${encodeURIComponent(this.currentCollective.name)}`)
				showSuccess(t('collectives', 'Page deleted'))
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not delete the page'))
			}
		},

		/**
		 * Open existing or create new template page
		 */
		async editTemplate() {
			if (this.templatePage(this.page.id)) {
				this.$router.push(this.pagePath(this.templatePage(this.page.id)))
				return
			}

			try {
				await this.$store.dispatch(NEW_TEMPLATE, this.page)
				this.$router.push(this.$store.getters.newPagePath)
				this.expand(this.page.id)
				// The parents location changes when the first subpage
				// is created.
				this.$store.dispatch(GET_PAGES)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// template icon appears too big with default size (16px)
.action-button__template::v-deep .icon-pages-template {
	background-size: 14px;
}
</style>
