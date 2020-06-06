<template>
	<AppSidebar
		ref="sidebar"
		:title="'Page: ' + page.title"
		subtitle="..."
		@close="$emit('close')">
		<SidebarVersionsTab
			:page-id="page.id"
			:page-title="page.title"
			@preview-version="emitVersionUrl" />
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
	},

	methods: {
		/**
		 * Emit page version URL to the parent component
		 * @param {string} url Page version url
		 */
		emitVersionUrl(url) {
			this.$emit('preview-version', url)
		},
	},
}

</script>
