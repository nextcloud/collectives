<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

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
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { useSharesStore } from '../stores/shares.js'
import { useSessionsStore } from '../stores/sessions.js'
import { usePagesStore } from '../stores/pages.js'
import { useVersionsStore } from '../stores/versions.js'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { listen } from '@nextcloud/notify_push'
import { NcAppContentDetails } from '@nextcloud/vue'
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
		...mapState(useRootStore, ['isPublic', 'loading', 'pageParam']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentCollectiveIsPageShare',
		]),
		...mapState(useSessionsStore, ['hasSession']),
		...mapState(usePagesStore, [
			'currentFileIdPage',
			'currentPage',
			'pagePath',
		]),
		...mapState(useVersionsStore, ['version']),

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
			this.selectVersion(null)
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
		this.clearSession()
		this.teardownBackgroundFetcher()
		unsubscribe('networkOffline', this.handleNetworkOffline)
		unsubscribe('networkOnline', this.handleNetworkOnline)
	},

	methods: {
		...mapActions(useRootStore, ['hide', 'load', 'show']),
		...mapActions(useSharesStore, ['getShares', 'unsetShares']),
		...mapActions(useVersionsStore, ['selectVersion']),
		...mapActions(usePagesStore, ['getPages', 'getTrashPages', 'unsetPages']),

		initCollective() {
			this.getAllPages()
			this.closeNav()
			this.show('details')

			if (!this.isPublic) {
				this.getShares()
					.catch(displayError('Could not fetch shares'))
			}
		},

		initListenPush() {
			this.listenPush = listen(`collectives_${this.currentCollective.id}_pagelist`, this.getAllPages.bind(this, false))

			if (this.listenPush) {
				console.debug('Has notify_push enabled, listening to pagelist updates and slowing polling to 15 minutes')
				this.pollIntervalBase = 15 * 60 * 1000
				this.createSession()
				this.updateSessionIntervalId = setInterval(this.updateSession, sessionUpdateInterval * 1000)
			}
		},

		clearListenPush() {
			this.clearSession()
			this.listenPush = null
			this.pollIntervalBase = 60 * 1000
		},

		async clearSession() {
			if (this.updateSessionIntervalId) {
				clearInterval(this.updateSessionIntervalId)
			}
			if (this.hasSession) {
				await this.closeSession()
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
			this.getAllPages(false)
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
					this.getAllPages.bind(this, false),
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

		async getAllPages(setLoading = true) {
			await this.getPages(setLoading)
				.catch(displayError('Could not fetch pages'))
			if (this.currentCollectiveCanEdit && !this.currentCollectiveIsPageShare) {
				await this.getTrashPages()
					.catch(displayError('Could not fetch page trash'))
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
	padding: 0 8px 2px 8px;
	display: flex;
	align-items: center;
	background-color: var(--color-main-background);
	font-size: 30px;

	::placeholder {
		font-size: 30px;
	}

	.page-title-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		min-width: var(--default-clickable-area);
		height: 43px;

		.button-emoji-page {
			width: var(--default-clickable-area);
			padding: 0px 4px;
			font-size: 0.8em;
		}
	}

	.title {
		overflow: hidden;
		text-overflow: ellipsis;
		font-size: 30px;
	}
}

/* Leave space for page list toggle on small screens (editor 670px + toggle button) */
@media screen and (max-width: calc(670px + 44px)) {
	.page-title {
		padding-left: calc(var(--default-clickable-area) + 4px);
	}
}

@media print {
	/* Don't print splitpane list and splitter panes */
	div.splitpanes__pane-list, div.splitpanes__splitter {
		display: none !important;
	}

	/* Don't print page list, list toggle and page sidebar toggle */
	#app-sidebar-vue, .app-navigation, .app-sidebar__toggle {
		display: none !important;
	}

	div.splitpanes__pane-details {
		width: unset !important;
	}
}
</style>
