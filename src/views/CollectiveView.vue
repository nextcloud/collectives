<template>
	<NcAppContent :show-details="showing('details')" :list-min-width="20">
		<template #list>
			<PageList v-if="currentCollective" />
		</template>
		<Collective v-if="currentCollective" />
		<NcEmptyContent v-else-if="loading('collectives')"
			icon="icon-loading" />
		<CollectiveNotFound v-else />
	</NcAppContent>
</template>

<script>

import { mapGetters } from 'vuex'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import Collective from '../components/Collective.vue'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'
import PageList from '../components/PageList.vue'

export default {
	name: 'CollectiveView',

	components: {
		NcAppContent,
		NcEmptyContent,
		Collective,
		CollectiveNotFound,
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
