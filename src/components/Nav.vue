<template>
	<AppNavigation>
		<ul>
			<AppNavigationCaption :title="t('wiki', 'Select a wiki')" />
			<AppNavigationItem v-for="wiki in wikis"
				:key="wiki.circleUniqueId"
				:title="wiki.folderName"
				:class="{active: isActive(wiki)}"
				:to="`/${wiki.folderName}`" />
		</ul>
	</AppNavigation>
</template>

<script>
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'

export default {
	name: 'Nav',
	components: {
		AppNavigation,
		AppNavigationItem,
		AppNavigationCaption,
	},
	props: {
		wikis: {
			type: Array,
			required: true,
		},
		selectedWiki: {
			type: String,
			required: false,
			default: null,
		},
		loading: {
			type: Boolean,
			required: true,
		},
	},
	methods: {
		isActive(wiki) {
			return this.selectedWiki === wiki.folderName
		},
	},
}
</script>
