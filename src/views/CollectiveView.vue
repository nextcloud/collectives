<template>
	<NcAppContent :show-details="showing('details')"
		:list-size="20"
		:list-min-width="15"
		@update:showDetails="hide('details')">
		<template #list>
			<PageList v-if="currentCollective" />
		</template>
		<Collective v-if="currentCollective" />
		<NcEmptyContent v-else-if="loading('collectives')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>
		<CollectiveNotFound v-else />
	</NcAppContent>
</template>

<script>

import { mapGetters, mapMutations } from 'vuex'
import { NcAppContent, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import Collective from '../components/Collective.vue'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'
import PageList from '../components/PageList.vue'

export default {
	name: 'CollectiveView',

	components: {
		Collective,
		CollectiveNotFound,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
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
// Align details toggle button with page title bar (only relevant on mobile)
button.app-details-toggle {
	z-index: 10023 !important;
	top: 61px !important;
	position: fixed !important;
}
</style>
