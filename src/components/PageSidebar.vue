<template>
	<AppSidebar
		ref="sidebar"
		:title="'Page: ' + page.title"
		subtitle="..."
		@close="$emit('close')">
		<SidebarVersionsTab :page-id="page.id" :page-title="page.title" />
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
}

</script>
