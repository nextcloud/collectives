<template>
	<NcAppContentDetails>
		<div v-if="loading('collective') || loading('currentPage')" class="sheet-view">
			<SkeletonLoading :count="1" class="page-heading-skeleton" type="page-heading" />
		</div>
		<Version v-else-if="currentPage && version" />
		<Page v-else-if="currentPage" />
		<PageNotFound v-else />
	</NcAppContentDetails>
</template>

<script>
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { listen } from '@nextcloud/notify_push'
import { NcAppContentDetails } from '@nextcloud/vue'
import { GET_SHARES, GET_PAGES, GET_TRASH_PAGES, CREATE_SESSION, UPDATE_SESSION, CLOSE_SESSION } from '../store/actions.js'
import { SELECT_VERSION } from '../store/mutations.js'
import displayError from '../util/displayError.js'
import Page from './Page.vue'
import Version from './Page/Version.vue'
import PageNotFound from './Page/PageNotFound.vue'
import SkeletonLoading from './SkeletonLoading.vue'
import { sessionUpdateInterval } from '../constants.js'

export default {
	name: 'Collective',

	components: {
		SkeletonLoading,
		NcAppContentDetails,
		Page,
		PageNotFound,
		Version,
	},

	data() {
		return {
			backgroundFetching: false,
			/** @type {number} */
			pollIntervalBase: 60 * 1000, // milliseconds
			/** @type {number} */
			pollIntervalCurrent: 60 * 1000, // milliseconds
			/** @type {null|number} */
			getPagesIntervalId: null,
			listenPush: null,
			/** @type {null|number} */
			updateSessionIntervalId: null,
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentCollectiveIsPageShare',
			'currentFileIdPage',
			'currentPage',
			'hasSession',
			'isPublic',
			'loading',
			'pageParam',
			'pagePath',
			'version',
		]),

		notFound() {
			return !this.loading('collective') && !this.loading('currentPage') && !this.currentPage
		},
	},

	watch: {
		'currentCollective.id'(val) {
			this.load('collective')
			this.unsetPages()
			this.unsetShares()
			this.clearListenPush()
			if (val) {
				this.initCollective()
				this.initListenPush()
			}
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
		this.initListenPush()
		this._setPollingInterval(this.pollIntervalBase)
		subscribe('networkOffline', this.handleNetworkOffline)
		subscribe('networkOnline', this.handleNetworkOnline)
	},

	beforeDestroy() {
		this.closeSession()
		this.teardownBackgroundFetcher()
		unsubscribe('networkOffline', this.handleNetworkOffline)
		unsubscribe('networkOnline', this.handleNetworkOnline)
	},

	methods: {
		...mapMutations(['show', 'hide', 'load', 'unsetShares', 'unsetPages']),

		...mapActions({
			dispatchGetPages: GET_PAGES,
			dispatchGetTrashPages: GET_TRASH_PAGES,
			dispatchGetShares: GET_SHARES,
			dispatchCreateSession: CREATE_SESSION,
			dispatchUpdateSession: UPDATE_SESSION,
			dispatchCloseSession: CLOSE_SESSION,
		}),

		initCollective() {
			this.getPages()
			this.closeNav()
			this.show('details')
			this.getShares()
		},

		initListenPush() {
			this.listenPush = listen(`collectives_${this.currentCollective.id}_pagelist`, this.getPagesBackground.bind(this))

			if (this.listenPush) {
				console.debug('Has notify_push enabled, listening to pagelist updates and slowing polling to 15 minutes')
				this.pollIntervalBase = 15 * 60 * 1000
				this.createSession()
			}
		},

		clearListenPush() {
			this.closeSession()
			this.listenPush = null
			this.pollIntervalBase = 60 * 1000
		},

		async createSession() {
			await this.dispatchCreateSession()
			this.updateSessionIntervalId = setInterval(this.dispatchUpdateSession, sessionUpdateInterval * 1000)
		},

		async closeSession() {
			if (this.updateSessionIntervalId) {
				clearInterval(this.updateSessionIntervalId)
			}
			if (this.hasSession) {
				await this.dispatchCloseSession()
			}
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
			this.getPagesBackground()
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
				this.getPagesIntervalId = window.setInterval(
					this.getPagesBackground.bind(this),
					this.pollIntervalCurrent,
				)
			} else {
				console.debug('Did not start background fetcher as session_keepalive is off')
			}
		},

		teardownBackgroundFetcher() {
			console.debug('Stopping background fetcher.')
			if (this.getPagesIntervalId) {
				window.clearInterval(this.getPagesIntervalId)
				this.getPagesIntervalId = null
			}
		},

		/**
		 * Get list of all pages
		 */
		async getPages() {
			await this.dispatchGetPages()
				.catch(displayError('Could not fetch pages'))
			if (this.currentCollectiveCanEdit && !this.currentCollectiveIsPageShare) {
				await this.dispatchGetTrashPages()
					.catch(displayError('Could not fetch page trash'))
			}
		},

		/**
		 * Get list of all pages without loading indicator
		 */
		async getPagesBackground() {
			await this.dispatchGetPages(false)
				.catch(displayError('Could not fetch pages'))
			if (this.currentCollectiveCanEdit && !this.currentCollectiveIsPageShare) {
				await this.dispatchGetTrashPages()
					.catch(displayError('Could not fetch page trash'))
			}
		},

		async getShares() {
			if (!this.isPublic) {
				await this.dispatchGetShares()
					.catch(displayError('Could not fetch shares'))
			}
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
	padding: 8px 8px 2px 8px;
	display: flex;
	align-items: center;
	background-color: var(--color-main-background);

	.page-title-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 24px;
		min-width: 44px;
		height: 43px;
		opacity: 0.8;

		.button-emoji-page {
			width: 44px;
			padding: 0px 4px;
			font-size: 24px;

			&.mobile {
				font-size: 25px;
			}
		}

		&.mobile {
			font-size: 25px;
		}
	}

	.title {
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

/* Leave space for page list toggle on small screens (editor 670px + toggle 44px) */
@media only screen and (max-width: calc(670px + 44px)) {
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
