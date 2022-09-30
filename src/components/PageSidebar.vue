<template>
	<NcAppSidebar ref="sidebar"
		:title="title"
		@close="close">
		<NcAppSidebarTab id="backlinks"
			:order="0"
			:name="t('collectives', 'Backlinks')">
			<template #icon>
				<ArrowBottomLeftIcon :size="16" />
			</template>
			<div class="app-sidebar-tab-desc">
				{{ t('collectives', 'Pages that link to this one') }}
			</div>
			<SidebarTabBacklinks v-if="showing('sidebar')" :page="page" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="!isPublic && currentCollectiveCanEdit"
			id="versions"
			:order="1"
			:name="t('collectives', 'Versions')">
			<template #icon>
				<RestoreIcon :size="16" />
			</template>
			<div class="app-sidebar-tab-desc">
				{{ t('collectives', 'Old versions of this page') }}
			</div>
			<SidebarTabVersions v-if="showing('sidebar')"
				:page-id="page.id"
				:page-timestamp="page.timestamp"
				:page-size="page.size" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import { NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import ArrowBottomLeftIcon from 'vue-material-design-icons/ArrowBottomLeft.vue'
import { SELECT_VERSION } from '../store/mutations.js'
import SidebarTabBacklinks from './PageSidebar/SidebarTabBacklinks.vue'
import SidebarTabVersions from './PageSidebar/SidebarTabVersions.vue'

export default {
	name: 'PageSidebar',

	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		RestoreIcon,
		ArrowBottomLeftIcon,
		SidebarTabBacklinks,
		SidebarTabVersions,
	},

	computed: {
		...mapGetters([
			'isPublic',
			'currentCollectiveCanEdit',
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
</style>
