<template>
	<AppContent>
		<Collective v-if="currentCollective" />
		<EmptyContent v-else-if="loading('collectives')"
			icon="icon-loading" />
		<CollectiveNotFound v-else />
	</AppContent>
</template>

<script>

import { mapGetters } from 'vuex'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Collective from '../components/Collective'
import CollectiveNotFound from '../components/CollectiveNotFound'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'CollectiveView',

	components: {
		AppContent,
		Collective,
		CollectiveNotFound,
		EmptyContent,
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'loading',
		]),
	},

}
</script>
