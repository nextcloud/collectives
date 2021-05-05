<template>
	<AppSidebar
		ref="sidebar"
		:title="title"
		@close="close">
		<template #secondary-actions>
			<ActionButton v-if="!landingPage"
				icon="icon-delete"
				@click="deletePage">
				{{ t('collectives', 'Delete page') }}
			</ActionButton>
		</template>
		<SidebarVersionsTab
			:page-id="page.id"
			:page-title="page.title"
			:page-timestamp="page.timestamp"
			:page-size="page.size" />
	</AppSidebar>
</template>

<script>
import { showSuccess, showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import SidebarVersionsTab from './SidebarVersionsTab'

export default {
	name: 'PageSidebar',

	components: {
		ActionButton,
		AppSidebar,
		SidebarVersionsTab,
	},

	computed: {
		...mapGetters([
			'currentPage',
			'collectiveParam',
			'title',
			'landingPage',
		]),

		page() {
			return this.currentPage
		},
	},

	methods: {
		...mapMutations(['hide']),

		/**
		 * Load the current version and close the sidebar
		 */
		close() {
			this.$store.commit('version', null)
			this.hide('sidebar')
		},

		/**
		 * Delete the current page,
		 * remove it from the frontend and show a hint
		 */
		async deletePage() {
			try {
				await this.$store.dispatch('deletePage')
				this.$router.push(`/${this.collectiveParam}`)
				showSuccess(t('collectives', 'Page deleted'))
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not delete the page'))
			}
		},
	},
}

</script>
