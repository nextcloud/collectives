<template>
	<AppNavigation>
		<template #list>
			<AppNavigationCaption :title="t('wiki', 'Select a wiki')" />
			<AppNavigationItem v-for="wiki in wikis"
				:key="wiki.circleUniqueId"
				:title="wiki.name"
				:class="{active: isActive(wiki)}"
				:to="`/${wiki.name}`"
				icon="icon-star" />
		</template>
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
	computed: {
		wikis() {
			return this.$store.state.wikis
		},
	},
	methods: {
		isActive(wiki) {
			return this.$store.getters.wikiParam === wiki.name
		},
	},
}
</script>
