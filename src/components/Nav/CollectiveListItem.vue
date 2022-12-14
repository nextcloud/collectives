<template>
	<NcAppNavigationItem :key="collective.circleId"
		:title="collective.name"
		:to="`/${encodeURIComponent(collective.name)}`"
		:force-menu="true"
		:force-display-actions="isMobile"
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
	</NcAppNavigationItem>
</template>

<script>
import { NcAppNavigationItem } from '@nextcloud/vue'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import CollectiveActions from '../Collective/CollectiveActions.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'

export default {
	name: 'CollectiveListItem',

	components: {
		NcAppNavigationItem,
		CollectiveActions,
		CollectivesIcon,
	},

	mixins: [
		isMobile,
	],

	props: {
		collective: {
			type: Object,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.app-navigation-entry-icon) {
	font-size: 20px;
}
</style>
