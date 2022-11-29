<template>
	<AppNavigationItem :key="collective.circleId"
		:title="collective.name"
		:to="`/${encodeURIComponent(collective.name)}`"
		:force-menu="true"
		class="collectives_list_item">
		<template #icon>
			<template v-if="collective.emoji">
				{{ collective.emoji }}
			</template>
			<template v-else>
				<CollectivesIcon :size="20" />
			</template>
		</template>
		<template #actions>
			<CollectiveActions :collective="collective" />
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import CollectiveActions from '../Collective/CollectiveActions.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'

export default {
	name: 'CollectiveListItem',

	components: {
		AppNavigationItem,
		CollectiveActions,
		CollectivesIcon,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .app-navigation-entry-icon {
	font-size: 20px;
}
</style>
