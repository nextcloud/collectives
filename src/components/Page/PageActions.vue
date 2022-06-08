<template>
	<Actions>
		<ActionLink :href="filesUrl"
			icon="icon-files-dark"
			:close-after-click="true">
			{{ t('collectives', 'Show in Files') }}
		</ActionLink>
		<ActionButton v-if="!isTemplatePage"
			class="action-button__template"
			:close-after-click="true"
			@click="editTemplate(currentPage.id)">
			<template #icon>
				<PagesTemplateIcon :size="14" />
			</template>
			{{ t('collectives', 'Edit template for subpages') }}
		</ActionButton>
		<ActionButton v-if="!landingPage"
			:close-after-click="true"
			@click="deletePage">
			<template #icon>
				<DeleteIcon :size="20" />
			</template>
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
import DeleteIcon from 'vue-material-design-icons/Delete'
import PagesTemplateIcon from '../Icon/PagesTemplateIcon'
import { DELETE_PAGE } from '../../store/actions'
import pageMixin from '../../mixins/pageMixin'

export default {
	name: 'PageActions',

	components: {
		Actions,
		ActionButton,
		ActionLink,
		DeleteIcon,
		PagesTemplateIcon,
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
