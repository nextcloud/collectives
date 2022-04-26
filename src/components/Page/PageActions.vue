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
			@click="editTemplate(currentPage.id)">
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
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'

import { mapActions, mapGetters } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { DELETE_PAGE } from '../../store/actions'
import pageMixin from '../../mixins/pageMixin'

export default {
	name: 'PageActions',

	components: {
		Actions,
		ActionButton,
		ActionLink,
	},

	mixins: [
		pageMixin,
	],

	computed: {
		...mapGetters([
			'currentPage',
			'currentCollective',
			'isTemplatePage',
			'landingPage',
			'showTemplates',
			'templatePage',
		]),

		filesUrl() {
			return generateUrl(`/apps/files/?fileid=${this.currentPage.id}`)
		},
	},

	methods: {
		...mapActions({
			dispatchDeletePage: DELETE_PAGE,
		}),

		/**
		 * Delete the current page,
		 * remove it from the frontend and show a hint
		 */
		async deletePage() {
			try {
				await this.dispatchDeletePage()
				this.$router.push(`/${encodeURIComponent(this.currentCollective.name)}`)
				showSuccess(t('collectives', 'Page deleted'))
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not delete the page'))
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
