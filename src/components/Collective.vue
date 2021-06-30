<template>
	<div id="app-content-wrapper">
		<PageList />
		<AppContentDetails>
			<Version v-if="currentPage && version" />
			<Page v-else-if="currentPage" />
			<EmptyContent v-else-if="loading('collective')"
				icon="icon-loading" />
			<PageNotFound v-else />
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
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import Page from '../components/Page'
import Version from '../components/Page/Version'
import PageNotFound from '../components/Page/PageNotFound'
import PageList from '../components/PageList'

export default {
	name: 'Collective',

	components: {
		AppContentDetails,
		EmptyContent,
		Page,
		PageList,
		PageNotFound,
		Version,
	},

	data() {
		return {
			backgroundFetching: false,
			/** @type {number} */
			pollInterval: 60000, // milliseconds
			/** @type {null|number} */
			intervalId: null,
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentPage',
			'loading',
			'pageParam',
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
		this.setupBackgroundFetcher()
	},

	unmounted() {
		this.teardownBackgroundFetcher()
	},

	methods: {
		...mapMutations(['show']),

		initCollective() {
			this.getPages()
			this.closeNav()
			this.show('details')
		},

		setupBackgroundFetcher() {
			if (OC.config.session_keepalive) {
				console.debug('Started background fetcher as session_keepalive is enabled')
				this.intervalId = window.setInterval(
					this.getPages.bind(this),
					this.pollInterval
				)
			} else {
				console.debug('Did not start background fetcher as session_keepalive is off')
			}
		},

		teardownBackgroundFetcher() {
			console.debug('Stopping background fetcher.')
			if (this.intervalId) {
				window.clearInterval(this.intervalId)
				this.intervalId = null
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

<style>
@media print {
	#app-content-vue {
		display: block !important;
		overflow: visible !important;
		padding: 0 !important;
		margin: 0 !important;
	}

	#app-content-wrapper {
		display: block !important;
	}

	#app-sidebar-vue {
		display: none !important;
	}
}
</style>
