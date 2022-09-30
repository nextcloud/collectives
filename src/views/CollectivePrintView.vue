<template>
	<NcAppContent>
		<CollectivePrint v-if="currentCollective" />
		<NcEmptyContent v-else-if="loading('collectives')" />
		<CollectiveNotFound v-else />
	</NcAppContent>
</template>

<script>

import { mapGetters, mapMutations } from 'vuex'
import { NcAppContent, NcEmptyContent } from '@nextcloud/vue'
import CollectivePrint from '../components/CollectivePrint.vue'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'

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

<style>
@media print {
	#content-vue {
		position: static;
		overflow: visible;
		height: auto;
	}
}
</style>
