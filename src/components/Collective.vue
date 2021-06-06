<template>
	<div id="app-content-wrapper">
		<PageList />
		<AppContentDetails v-if="currentPage">
			<Version v-if="version" />
			<Page v-else />
		</AppContentDetails>
	</div>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { mapGetters, mapMutations } from 'vuex'
import { GET_PAGES } from '../store/actions'
import { SELECT_VERSION } from '../store/mutations'
import displayError from '../util/displayError'
import AppContentDetails from '@nextcloud/vue/dist/Components/AppContentDetails'
import Page from '../components/Page'
import Version from '../components/Page/Version'
import PageList from '../components/PageList'

export default {
	name: 'Collective',

	components: {
		AppContentDetails,
		Page,
		PageList,
		Version,
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentPage',
			'version',
		]),

	},

	watch: {
		'currentCollective.id'() {
			this.initCollective()
		},
		'currentPage.id'() {
			this.$store.commit(SELECT_VERSION, null)
		},
	},

	mounted() {
		this.initCollective()
	},

	methods: {
		...mapMutations(['show']),

		initCollective() {
			if (this.currentCollective) {
				this.getPages()
				this.closeNav()
				this.show('details')
			} else {
				this.openNav()
			}
		},

		/**
		 * Get list of all pages
		 * @returns {Promise}
		 */
		getPages() {
			return this.$store.dispatch(GET_PAGES)
				.catch(displayError('Could not fetch pages'))
		},

		closeNav() {
			emit('toggle-navigation', { open: false })
		},

		openNav() {
			emit('toggle-navigation', { open: true })
		},

	},

}
</script>
