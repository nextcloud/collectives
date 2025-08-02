<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent :show-details="showing('details')"
		:list-size="20"
		:list-min-width="15"
		@update:showDetails="hide('details')">
		<template #list>
			<PageList v-if="currentCollective" />
		</template>
		<Collective v-if="currentCollective" />
		<NcEmptyContent v-else-if="loading('collectives')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>
		<CollectiveNotFound v-else />
	</NcAppContent>
</template>

<script>

import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useSessionsStore } from '../stores/sessions.js'
import { useTagsStore } from '../stores/tags.js'
import { useTemplatesStore } from '../stores/templates.js'
import { NcAppContent, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import Collective from '../components/Collective.vue'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'
import PageList from '../components/PageList.vue'
import { listen } from '@nextcloud/notify_push'
import displayError from '../util/displayError.js'
import { sessionUpdateInterval } from '../constants.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

export default {
	name: 'CollectiveView',

	components: {
		Collective,
		CollectiveNotFound,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		PageList,
	},

	setup() {
		const pagesStore = usePagesStore()
		const templatesStore = useTemplatesStore()
		const listenPush = listen('collectives_pagelist', (_, message) => {
			pagesStore.updatePages(message.collectiveId, message)
			templatesStore.updateTemplates(message.collectiveId, message)
		})
		return { listenPush }
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
			/** @type {null|number} */
			updateSessionIntervalId: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['loading', 'showing']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentCollectiveIsPageShare',
		]),
		...mapState(useSessionsStore, ['hasSession']),
	},

	watch: {
		'currentCollective.id'(val) {
			this.clearSession()
			if (val) {
				this.getAllPages()
				this.initSession()
			}
		},
	},

	mounted() {
		if (this.currentCollective) {
			this.getAllPages()
			this.initSession()
		}
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
		...mapActions(useRootStore, ['hide']),
		...mapActions(useSessionsStore, ['createSession', 'updateSession', 'closeSession']),
		...mapActions(useTagsStore, ['getTags']),
		...mapActions(useTemplatesStore, ['getTemplates']),
		...mapActions(usePagesStore, ['getPages', 'getTrashPages', 'updatePages']),

		initSession() {
			if (this.listenPush) {
				console.debug('Has notify_push enabled, listening to pagelist updates and slowing polling to 15 minutes')
				this.pollIntervalBase = 15 * 60 * 1000
				if (!this.isPublic) {
					this.createSession()
					this.updateSessionIntervalId = setInterval(this.updateSession, sessionUpdateInterval * 1000)
				}
			}
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
			if (this.currentCollective) {
				this.getAllPages(false)
			}
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
			await this.getTags()
				.catch(displayError('Could not fetch tags'))
			if (this.currentCollectiveCanEdit) {
				if (!this.currentCollectiveIsPageShare) {
					await this.getTemplates(setLoading)
						.catch(displayError('Could not fetch templates'))
					await this.getTrashPages()
						.catch(displayError('Could not fetch page trash'))
				}
			}
		},

	},
}
</script>

<style lang="scss">
// Align details toggle button with page title bar (only relevant on mobile)
button.app-details-toggle {
	z-index: 10023 !important;
	top: 58px !important;
	position: fixed !important;
}
</style>
