<template>
	<!-- We're the only tab so far, so no need for AppSidebarTab
	<AppSidebarTab
		id="versions"
		name="Versions"
		icon="icon-history"
		:active-tab="activeTab"
		:class="{ 'icon-loading': loading }">
	-->
	<div id="versions">
		<!-- error message -->
		<div v-if="error" class="emptycontent">
			<div class="icon icon-error" />
			<h2>{{ error }}</h2>
		</div>

		<!-- versions content -->
		<template v-else>
			Page ID: {{ pageId }}
			<div v-if="!loading">
				List of versions: {{ versions }}
			</div>
		</template>
	</div>
	<!-- </AppSidebarTab> -->
</template>

<script>
//import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'

export default {
	name: 'SidebarVersionsTab',
	/* components: {
		AppSidebarTab,
	}, */
	props: {
		pageId: {
			type: Number,
			required: true,
		},
	},
	data: function() {
		return {
			error: '',
			loading: true,
			versions: null,
		}
	},
}
</script>

<style lang="scss" scoped>
</style>
