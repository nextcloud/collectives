<template>
	<AppSidebar
		ref="sidebar"
		:title="title"
		@close="close">
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
		<AppSidebarTab v-if="!isPublic"
			id="versions"
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
import { mapGetters, mapMutations } from 'vuex'
import { SELECT_VERSION } from '../store/mutations'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import SidebarTabBacklinks from './PageSidebar/SidebarTabBacklinks'
import SidebarTabVersions from './PageSidebar/SidebarTabVersions'

export default {
	name: 'PageSidebar',

	components: {
		AppSidebar,
		AppSidebarTab,
		SidebarTabBacklinks,
		SidebarTabVersions,
	},

	computed: {
		...mapGetters([
			'isPublic',
			'currentPage',
			'title',
			'showing',
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
			this.$store.commit(SELECT_VERSION, null)
			this.hide('sidebar')
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
