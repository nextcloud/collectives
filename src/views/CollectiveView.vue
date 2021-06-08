<template>
	<AppContent>
		<Collective v-if="currentCollective" />
		<CollectiveNotFound v-else />
	</AppContent>
</template>

<script>

import { mapGetters } from 'vuex'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Collective from '../components/Collective'
import CollectiveNotFound from '../components/CollectiveNotFound'

export default {
	name: 'CollectiveView',

	components: {
		AppContent,
		Collective,
		CollectiveNotFound,
	},

	computed: {
		...mapGetters([
			'currentCollective',
		]),
	},

}
</script>
