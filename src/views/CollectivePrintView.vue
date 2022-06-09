<template>
	<AppContent>
		<CollectivePrint v-if="currentCollective" />
		<EmptyContent v-else-if="loading('collectives')"
			icon="icon-loading" />
		<CollectiveNotFound v-else />
	</AppContent>
</template>

<script>

import { mapGetters, mapMutations } from 'vuex'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import CollectivePrint from '../components/CollectivePrint.vue'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'CollectivePrintView',

	components: {
		AppContent,
		CollectivePrint,
		CollectiveNotFound,
		EmptyContent,
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'loading',
		]),
	},

	mounted() {
		this.setPrintView()
	},

	methods: {
		...mapMutations(['setPrintView']),
	},
}
</script>
