<template>
	<AppNavigation>
		<template #list>
			<AppNavigationCaption :title="t('unite', 'Select a collective')" />
			<AppNavigationItem v-for="collective in collectives"
				:key="collective.circleUniqueId"
				:title="collective.name"
				:class="{active: isActive(collective)}"
				:to="`/${collective.name}`"
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
		collectives() {
			return this.$store.state.collectives
		},
	},
	methods: {
		isActive(collective) {
			return this.$store.getters.collectiveParam === collective.name
		},
	},
}
</script>
