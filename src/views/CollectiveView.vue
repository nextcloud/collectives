<template>
	<NcAppContent :show-details="showing('details')"
		:list-min-width="20"
		@update:showDetails="hide('details')">
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

import { mapGetters, mapMutations } from 'vuex'
import { NcAppContent, NcEmptyContent } from '@nextcloud/vue'
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

	methods: {
		...mapMutations(['hide']),
	},
}
</script>

<style lang="scss">
button.app-details-toggle {
	position: absolute !important;
	z-index: 10023 !important;
	top: 14px !important;
}

div.splitpanes.splitpanes--vertical div.splitpanes__pane.splitpanes__pane-details {
	overflow: visible;
}
</style>
