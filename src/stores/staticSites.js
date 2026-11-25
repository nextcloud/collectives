/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import * as api from '../apis/collectives/index.js'
import { staticSitePollInterval, staticSitePollTimeout } from '../constants.js'
import { byTitleAsc } from '../util/sortOrders.js'
import { staticSiteFailureReason, staticSiteState } from '../util/staticSiteStatus.js'

export const useStaticSitesStore = defineStore('staticSites', {
	state: () => ({
		/** @type {Object<number, object[]>} collective ID -> static sites */
		staticSites: {},
		/** @type {Object<number, {operation: 'publish'|'delete', title: string, collectiveId: number, collectiveName: string, attemptsLeft: number}>} static site ID -> watched request */
		watchedRequests: {},
		/** @type {?number} */
		pollIntervalId: null,
	}),

	getters: {
		// Sorted by title, so the order stays the same while a website gets published
		staticSitesForCollective: (state) => (collectiveId) => {
			return (state.staticSites[collectiveId] ?? []).toSorted(byTitleAsc)
		},
	},

	actions: {
		async getStaticSites(collectiveId) {
			const response = await api.getStaticSites(collectiveId)
			this.staticSites[collectiveId] = response.data.ocs.data
		},

		/**
		 * Delete a static site. If the publish service deletes it asynchronously, wait for the
		 * result in the background.
		 *
		 * @param {object} collective Collective of the static site
		 * @param {object} staticSite Static site to delete
		 */
		async deleteStaticSite(collective, staticSite) {
			const response = await api.deleteStaticSite(collective.id, staticSite.id)
			const staticSites = this.staticSites[collective.id] ?? []
			if (response.status === 202) {
				const deleting = response.data.ocs.data
				this.staticSites[collective.id] = staticSites.map((site) => (site.id === deleting.id ? deleting : site))
				showSuccess(t('collectives', 'Website deletion started'))
				this.watchDeletion(collective, deleting)
				return
			}
			this.staticSites[collective.id] = staticSites.filter((site) => site.id !== staticSite.id)
			this.showDeleted(staticSite.title, collective.name)
		},

		/**
		 * Wait for the publish service to report the result of a started publication back
		 * and show a toast about it, no matter where in the app the user is meanwhile.
		 *
		 * @param {object} collective Collective of the static site, its name is shown in the toast
		 *   as the user may work in another collective meanwhile
		 * @param {object} publication Static site as returned when the publication was started
		 */
		watchPublication(collective, publication) {
			this.watchRequest('publish', collective, publication)
		},

		/**
		 * Wait for the publish service to report the result of a started deletion back
		 * and show a toast about it, no matter where in the app the user is meanwhile.
		 *
		 * @param {object} collective Collective of the static site, its name is shown in the toast
		 * @param {object} deletion Static site being deleted
		 */
		watchDeletion(collective, deletion) {
			this.watchRequest('delete', collective, deletion)
		},

		/**
		 * Several requests are watched at once. A new request for a static site restarts
		 * watching it. Watching stops as soon as the request isn't in progress anymore -
		 * or if requests kept failing.
		 *
		 * @param {'publish'|'delete'} operation Requested operation
		 * @param {object} collective Collective of the static site
		 * @param {object} staticSite Static site as returned when the request was started
		 */
		watchRequest(operation, collective, staticSite) {
			console.debug(`Waiting for ${operation} result of static site ${staticSite.id} in collective ${collective.id}`)
			this.watchedRequests[staticSite.id] = {
				operation,
				// A deleted static site isn't listed anymore
				title: staticSite.title,
				collectiveId: collective.id,
				collectiveName: collective.name,
				attemptsLeft: Math.ceil(staticSitePollTimeout / staticSitePollInterval),
			}
			if (!this.pollIntervalId) {
				this.pollIntervalId = setInterval(() => this.pollWatchedRequests(), staticSitePollInterval * 1000)
			}
		},

		/**
		 * Load the static sites of each collective with watched requests once
		 * and conclude the requests that got a result
		 */
		async pollWatchedRequests() {
			const watchedRequests = Object.entries(this.watchedRequests)
			const collectiveIds = new Set()
			for (const [, watched] of watchedRequests) {
				// Failed requests count as well, e.g. after the user got logged out
				watched.attemptsLeft--
				collectiveIds.add(watched.collectiveId)
			}

			await Promise.all([...collectiveIds].map(async (collectiveId) => {
				let loaded = true
				try {
					await this.getStaticSites(collectiveId)
				} catch (e) {
					console.error('Could not load static sites for collective', e)
					loaded = false
				}

				for (const [id, watched] of watchedRequests) {
					// Skip requests concluded or restarted meanwhile
					if (watched.collectiveId === collectiveId && this.watchedRequests[id] === watched) {
						this.checkWatchedRequest(Number(id), watched, loaded)
					}
				}
			}))

			if (Object.keys(this.watchedRequests).length === 0) {
				this.stopWatching()
			}
		},

		/**
		 * @param {number} id ID of the watched static site
		 * @param {object} watched watched request
		 * @param {boolean} loaded whether the static sites of its collective got loaded
		 */
		checkWatchedRequest(id, watched, loaded) {
			const staticSite = loaded
				? this.staticSites[watched.collectiveId].find((site) => site.id === id)
				: undefined
			const concluded = watched.operation === 'delete'
				? this.concludeDeletion(staticSite, watched, loaded)
				: this.concludePublication(staticSite, watched)

			if (concluded) {
				delete this.watchedRequests[id]
				return
			}
			if ((loaded && !staticSite) || watched.attemptsLeft <= 0) {
				console.debug(`Giving up on ${watched.operation} result of static site ${id}, status ${staticSite?.status ?? (loaded ? 'deleted' : 'unknown')}`)
				delete this.watchedRequests[id]
			}
		},

		/**
		 * @param {object|undefined} staticSite watched static site, undefined if it vanished or wasn't loaded
		 * @param {object} watched watched request
		 * @return {boolean} true if the publication got a result
		 */
		concludePublication(staticSite, watched) {
			if (staticSite?.status === 'published') {
				showSuccess(t('collectives', 'Website "{title}" of collective "{collective}" published at {url}', {
					title: staticSite.title,
					collective: watched.collectiveName,
					url: staticSite.publishedUrl,
				}))
				return true
			}

			if (staticSite && staticSiteState(staticSite) === 'failed') {
				showError(t('collectives', 'Website "{title}" of collective "{collective}" could not be published: {reason}', {
					title: staticSite.title,
					collective: watched.collectiveName,
					reason: staticSiteFailureReason(staticSite),
				}))
				return true
			}
			return false
		},

		/**
		 * @param {object|undefined} staticSite watched static site, undefined if it vanished or wasn't loaded
		 * @param {object} watched watched request
		 * @param {boolean} loaded whether the static sites of its collective got loaded
		 * @return {boolean} true if the deletion got a result
		 */
		concludeDeletion(staticSite, watched, loaded) {
			if (loaded && !staticSite) {
				this.showDeleted(watched.title, watched.collectiveName)
				return true
			}

			if (staticSite && staticSiteState(staticSite) === 'deleteFailed') {
				showError(t('collectives', 'Website "{title}" of collective "{collective}" could not be deleted: {reason}', {
					title: staticSite.title,
					collective: watched.collectiveName,
					reason: staticSiteFailureReason(staticSite),
				}))
				return true
			}
			return false
		},

		/**
		 * @param {string} title Title of the deleted static site
		 * @param {string} collectiveName Name of its collective
		 */
		showDeleted(title, collectiveName) {
			showSuccess(t('collectives', 'Website "{title}" of collective "{collective}" deleted', {
				title,
				collective: collectiveName,
			}))
		},

		stopWatching() {
			clearInterval(this.pollIntervalId)
			this.pollIntervalId = null
			this.watchedRequests = {}
		},
	},
})
