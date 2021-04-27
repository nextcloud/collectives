<template>
	<AppSidebar
		ref="sidebar"
		:title="page.title"
		@close="hide('sidebar')">
		<SidebarVersionsTab
			:page-id="page.id"
			:page-title="page.title"
			:page-timestamp="page.timestamp"
			:page-size="page.size"
			:current-version-timestamp="currentVersionTimestamp"
			@preview-version="emitVersion" />
	</AppSidebar>
</template>

<script>
import { mapMutations } from 'vuex'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import SidebarVersionsTab from './SidebarVersionsTab'

export default {
	name: 'PageSidebar',

	components: {
		AppSidebar,
		SidebarVersionsTab,
	},

	props: {
		currentVersionTimestamp: {
			type: Number,
			required: true,
		},
	},

	computed: {
		page() {
			return this.$store.getters.currentPage
		},
	},

	methods: {
		...mapMutations(['hide']),

		/**
		 * Emit page version URL to the parent component
		 * @param {object} version Page version object
		 */
		emitVersion(version) {
			this.$emit('preview-version', version)
		},
	},
}

</script>
