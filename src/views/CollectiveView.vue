<template>
	<AppContent :show-details="showing('details')" :list-min-width="20">
		<template #list>
			<PageList v-if="currentCollective" />
		</template>
		<Collective v-if="currentCollective" />
		<EmptyContent v-else-if="loading('collectives')"
			icon="icon-loading" />
		<CollectiveNotFound v-else />
	</AppContent>
</template>

<script>

import { mapGetters } from 'vuex'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Collective from '../components/Collective.vue'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import PageList from '../components/PageList.vue'

export default {
	name: 'CollectiveView',

	components: {
		AppContent,
		Collective,
		CollectiveNotFound,
		EmptyContent,
		PageList,
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'loading',
			'showing',
		]),
	},
}
</script>

<style>
div.splitpanes.splitpanes--vertical div.splitpanes__pane.splitpanes__pane-details {
	overflow: visible;
}
</style>
