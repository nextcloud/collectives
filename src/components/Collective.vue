<template>
	<NcAppContentDetails>
		<Version v-if="currentPage && version" />
		<Page v-else-if="currentPage" />
		<NcEmptyContent v-else-if="loading('collective') || loading('page')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>
		<PageNotFound v-else />
	</NcAppContentDetails>
</template>

<script>
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { listen } from '@nextcloud/notify_push'
import { NcAppContentDetails, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { GET_PAGES } from '../store/actions.js'
import { SELECT_VERSION } from '../store/mutations.js'
import displayError from '../util/displayError.js'
import Page from '../components/Page.vue'
import Version from '../components/Page/Version.vue'
import PageNotFound from '../components/Page/PageNotFound.vue'

export default {
	name: 'Collective',

	components: {
		NcAppContentDetails,
		NcEmptyContent,
		NcLoadingIcon,
		Page,
		PageNotFound,
		Version,
	},

	data() {
		return {
			backgroundFetching: false,
			/** @type {number} */
			pollIntervalBase: 60 * 1000, // milliseconds
			pollIntervalCurrent: 60 * 1000, // milliseconds
			/** @type {null|number} */
			intervalId: null,
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentFileIdPage',
			'currentPage',
			'collectivePage',
			'loading',
			'pageParam',
			'pagePath',
			'version',
		]),

		notFound() {
			return !this.loading('collective') && !this.loading('pagelist') && !this.currentPage
		},
	},

	watch: {
		'currentCollective.id'() {
			this.load('collective')
			this.unsetPages()
			this.initCollective()
		},
		'currentPage.id'() {
			this.$store.commit(SELECT_VERSION, null)
		},
		'notFound'(current) {
			if (current && this.currentFileIdPage) {
				this.$router.replace(this.pagePath(this.currentFileIdPage))
			}
		},
	},

	mounted() {
		this.initCollective()
		const hasPush = listen('notify_file', this.getPagesBackground.bind(this))
		if (hasPush) {
			console.debug('Has notify_push enabled, slowing polling to 15 minutes')
			this.pollIntervalBase = 15 * 60 * 1000
		}
		this._setPollingInterval(this.pollIntervalBase)
		subscribe('networkOffline', this.handleNetworkOffline)
		subscribe('networkOnline', this.handleNetworkOnline)
	},

	beforeDestroy() {
		this.teardownBackgroundFetcher()
		unsubscribe('networkOffline', this.handleNetworkOffline)
		unsubscribe('networkOnline', this.handleNetworkOnline)
	},

	methods: {
		...mapMutations(['show', 'hide', 'load', 'unsetPages']),

		...mapActions({
			dispatchGetPages: GET_PAGES,
		}),

		initCollective() {
			this.getPages()
			this.closeNav()
			this.show('details')
		},

		handleNetworkOffline() {
			// If we poll less than every 10 Minutes
			// - do not slow down further.
			if (this.pollIntervalBase > 10 * 60 * 1000) {
				return
			}
			console.debug('Network is offline.')
			this._setPollingInterval(this.pollIntervalBase * 10)
		},

		handleNetworkOnline() {
			this.getPages()
			console.debug('Network is online.')
			this._setPollingInterval(this.pollIntervalBase)
		},

		_setPollingInterval(pollInterval) {
			console.debug(`Polling every ${pollInterval / 1000} seconds.`)
			if (this.interval && pollInterval === this.pollIntervalCurrent) {
				return
			}

			if (this.interval) {
				window.clearInterval(this.interval)
				this.interval = null
			}

			this.pollIntervalCurrent = pollInterval
			this.setupBackgroundFetcher()
		},

		setupBackgroundFetcher() {
			if (OC.config.session_keepalive) {
				console.debug('Started background fetcher as session_keepalive is enabled')
				this.intervalId = window.setInterval(
					this.getPagesBackground.bind(this),
					this.pollIntervalCurrent
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
		 */
		async getPages() {
			await this.dispatchGetPages()
				.catch(displayError('Could not fetch pages'))
		},

		/**
		 * Get list of all pages without loading indicator
		 */
		async getPagesBackground() {
			await this.dispatchGetPages(false)
				.catch(displayError('Could not fetch pages'))
		},

		closeNav() {
			emit('toggle-navigation', { open: false })
		},
	},

}
</script>

<style lang="scss">
/* Format page title in Page.vue and Version.vue */
.page-title {
	position: sticky;
	top: 0;
	padding: 8px 0px 2px 8px;
	margin: auto;
	max-width: 670px;
	display: flex;
	align-items: center;
	background-color: var(--color-main-background);

	.page-title-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 30px;
		min-width: 44px;
		height: 43px;
		opacity: 0.8;

		.button-emoji-page {
			width: 44px;
			padding: 0px 4px;
			font-size: 30px;
		}
	}

	.title {
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

/* Leave space for page list toggle on small screens (editor 670px + toggle 44px) */
@media only screen and (max-width: 670px + 44px) {
	.page-title {
		padding-left: 40px;
	}
}

@media print {
	/* Don't print page list */
	#app-sidebar-vue {
		display: none !important;
	}

	/* Fix alignment of app details for print */
	div.splitpanes__pane-list, div.splitpanes__splitter {
		display: none !important;
	}
}
</style>
