<template>
	<AppSidebar
		ref="sidebar"
		:title="title"
		@close="close">
		<template #secondary-actions>
			<ActionLink :href="filesUrl(page)"
				icon="icon-files-dark"
				:close-after-click="true">
				{{ t('collectives', 'Show in Files') }}
			</ActionLink>
			<ActionButton v-if="!isTemplatePage"
				icon="icon-pages-template"
				:close-after-click="true"
				@click="editTemplate(page)">
				{{ t('collectives', 'Edit template for subpages') }}
			</ActionButton>
			<ActionButton v-if="!landingPage"
				icon="icon-delete"
				:close-after-click="true"
				@click="deletePage">
				{{ t('collectives', 'Delete page') }}
			</ActionButton>
		</template>
		<AppSidebarTab id="backlinks"
			:order="0"
			:name="t('collectives', 'Backlinks')"
			icon="icon-search">
			<div class="app-sidebar-tab-desc">
				{{ t('collectives', 'Pages that link to this one') }}
			</div>
			<SidebarTabBacklinks
				v-if="showing('sidebar')"
				:page="page" />
		</AppSidebarTab>
		<AppSidebarTab id="versions"
			:order="1"
			:name="t('collectives', 'Versions')"
			icon="icon-history">
			<div class="app-sidebar-tab-desc">
				{{ t('collectives', 'Old versions of this page') }}
			</div>
			<SidebarTabVersions
				v-if="showing('sidebar')"
				:page-id="page.id"
				:page-title="page.title"
				:page-timestamp="page.timestamp"
				:page-size="page.size" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import { showSuccess, showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import { NEW_TEMPLATE, DELETE_PAGE, GET_PAGES } from '../store/actions'
import { SELECT_VERSION } from '../store/mutations'
import { generateUrl } from '@nextcloud/router'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import SidebarTabBacklinks from './PageSidebar/SidebarTabBacklinks'
import SidebarTabVersions from './PageSidebar/SidebarTabVersions'

export default {
	name: 'PageSidebar',

	components: {
		ActionButton,
		ActionLink,
		AppSidebar,
		AppSidebarTab,
		SidebarTabBacklinks,
		SidebarTabVersions,
	},

	computed: {
		...mapGetters([
			'pagePath',
			'currentCollective',
			'currentPage',
			'title',
			'landingPage',
			'templatePage',
			'isTemplatePage',
			'showing',
		]),

		page() {
			return this.currentPage
		},
	},

	methods: {
		...mapMutations(['hide', 'expand']),

		/**
		 * Load the current version and close the sidebar
		 */
		close() {
			this.$store.commit(SELECT_VERSION, null)
			this.hide('sidebar')
		},

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

		filesUrl(page) {
			return generateUrl(`/apps/files/?fileid=${page.id}`)
		},

		/**
		 * Open existing or create new template page
		 *
		 * @param {object} parentPage Parent page
		 */
		async editTemplate(parentPage) {
			if (this.templatePage(parentPage.id)) {
				this.$router.push(this.pagePath(this.templatePage(parentPage.id)))
				return
			}

			try {
				await this.$store.dispatch(NEW_TEMPLATE, parentPage)
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

<style>
.app-sidebar-tab-desc {
	font-weight: bold;
}

@media print {
	.app-content-list {
		display: none !important;
	}
}
</style>
