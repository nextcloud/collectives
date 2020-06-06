<template>
	<AppSidebar
		ref="sidebar"
		:title="'Page: ' + page.title"
		subtitle="..."
		@close="$emit('close')">
		<SidebarVersionsTab
			:page-id="page.id"
			:page-title="page.title"
			:reload-versions="reloadVersions"
			@preview-version="emitVersion" />
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import SidebarVersionsTab from './SidebarVersionsTab'

export default {
	name: 'PageSidebar',

	components: {
		AppSidebar,
		SidebarVersionsTab,
	},

	props: {
		page: {
			type: Object,
			required: true,
		},
		reloadVersions: {
			type: Boolean,
			required: true,
		},
	},

	methods: {
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
