<template>
	<NcAppContent>
		<CollectivePrint v-if="currentCollective" />
		<NcEmptyContent v-else-if="loading('collectives')"
			icon="icon-loading" />
		<CollectiveNotFound v-else />
	</NcAppContent>
</template>

<script>

import { mapGetters, mapMutations } from 'vuex'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import CollectivePrint from '../components/CollectivePrint.vue'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

export default {
	name: 'CollectivePrintView',

	components: {
		NcAppContent,
		CollectivePrint,
		CollectiveNotFound,
		NcEmptyContent,
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
