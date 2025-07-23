<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContentDetails>
		<div v-if="loading('pagelist') || loading('currentPage')" class="sheet-view">
			<SkeletonLoading :count="1" class="page-heading-skeleton" type="page-heading" />
		</div>
		<PageVersion v-else-if="currentPage && selectedVersion" />
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
import { useTemplatesStore } from '../stores/templates.js'
import { useVersionsStore } from '../stores/versions.js'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { listen } from '@nextcloud/notify_push'
import { NcAppContentDetails } from '@nextcloud/vue'
import displayError from '../util/displayError.js'
import Page from './Page.vue'
import PageVersion from './PageVersion.vue'
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
		PageVersion,
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
		...mapState(useRootStore, ['isPublic', 'loading', 'pageParam', 'pageId']),
		...mapState(useCollectivesStore, [
			'collectivePath',
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentCollectiveIsPageShare',
			'currentCollectivePath',
		]),
		...mapState(useSessionsStore, ['hasSession']),
		...mapState(usePagesStore, [
			'currentFileIdPage',
			'currentPage',
			'isLandingPage',
			'pagePath',
			'currentPagePath',
		]),
		...mapState(useVersionsStore, ['selectedVersion']),

		notFound() {
			return !this.loading('pagelist') && !this.loading('currentPage') && !this.currentPage
		},
	},

	watch: {
		'currentCollective.id'(val) {
			this.clearListenPush()
			if (val) {
				this.initCollective()
				this.initListenPush()
			}
		},
		'currentPage.id'() {
			this.selectVersion(null)
			this.slugUrl()
		},
		'notFound'(current) {
			if (current && this.currentFileIdPage) {
				this.$router.replace(this.pagePath(this.currentFileIdPage) + document.location.hash)
			}
		},
	},

	mounted() {
		this.initCollective()
		this.initListenPush()
		this._setPollingInterval(this.pollIntervalBase)
		subscribe('networkOffline', this.handleNetworkOffline)
		subscribe('networkOnline', this.handleNetworkOnline)
		this.slugUrl()
	},

	beforeDestroy() {
		this.clearSession()
		this.teardownBackgroundFetcher()
		unsubscribe('networkOffline', this.handleNetworkOffline)
		unsubscribe('networkOnline', this.handleNetworkOnline)
	},

	methods: {
		...mapActions(useRootStore, ['hide', 'load', 'show']),
		...mapActions(useSharesStore, ['getShares']),
		...mapActions(useTemplatesStore, ['getTemplates']),
		...mapActions(useSessionsStore, ['createSession', 'updateSession', 'closeSession']),
		...mapActions(usePagesStore, ['getPages', 'getTrashPages']),
		...mapActions(useVersionsStore, ['selectVersion']),

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
				if (!this.isPublic) {
					this.createSession()
					this.updateSessionIntervalId = setInterval(this.updateSession, sessionUpdateInterval * 1000)
				}
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
			if (this.currentCollectiveCanEdit) {
				if (!this.currentCollectiveIsPageShare) {
					await this.getTemplates(setLoading)
						.catch(displayError('Could not fetch templates'))
					await this.getTrashPages()
						.catch(displayError('Could not fetch page trash'))
				}
			}
		},

		closeNav() {
			emit('toggle-navigation', { open: false })
		},

		slugUrl() {
			// Redirect to slugified URL if possible
			if (this.currentCollective
				&& this.isLandingPage
				&& this.$route.path !== this.currentCollectivePath) {
				this.$router.replace({ path: this.currentCollectivePath, hash: document.location.hash })
			} else if (this.currentPage
				&& this.$route.path !== this.currentPagePath) {
				this.$router.replace({ path: this.currentPagePath, hash: document.location.hash })
			}
		},
	},

}
</script>

<style lang="scss">
.app-content-details {
	// Required for search dialog to stick to the bottom
	height: 100%;
}

.page-heading-skeleton {
	width: 100%;
}

/* Format page title in Page.vue and PageVersion.vue */
.page-title {
	position: relative;
	z-index: 10022;
	padding: 0 8px;
}

// Align sidebar toggle
.app-sidebar__toggle {
	inset-block-start: 7px !important;
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
