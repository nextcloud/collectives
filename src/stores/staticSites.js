/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import * as api from '../apis/collectives/index.js'
import { staticSitePollInterval, staticSitePollTimeout } from '../constants.js'
import { staticSiteErrorMessage } from '../util/staticSiteError.js'

export const useStaticSitesStore = defineStore('staticSites', {
	state: () => ({
		/** @type {Object<number, object[]>} collective ID -> static sites */
		staticSites: {},
		/** @type {Object<number, {collectiveId: number, collectiveName: string, attemptsLeft: number}>} static site ID -> watched publication */
		watchedPublications: {},
		/** @type {?number} */
		pollIntervalId: null,
	}),

	getters: {
		// Most recently updated first
		staticSitesForCollective: (state) => (collectiveId) => {
			return [...(state.staticSites[collectiveId] ?? [])]
				.sort((a, b) => b.updatedAt - a.updatedAt)
		},
	},

	actions: {
		async getStaticSites(collectiveId) {
			const response = await api.getStaticSites(collectiveId)
			this.staticSites[collectiveId] = response.data.ocs.data
		},

		/**
		 * Wait for the publish service to report the result of a started publication back
		 * and show a toast about it, no matter where in the app the user is meanwhile.
		 *
		 * Several publications are watched at once. Publishing a website again restarts
		 * watching it. Watching stops as soon as the publication isn't in progress
		 * anymore - or if it vanished or requests kept failing.
		 *
		 * @param {object} collective Collective of the static site, its name is shown in the toast
		 *   as the user may work in another collective meanwhile
		 * @param {object} publication Static site as returned when the publication was started
		 */
		watchPublication(collective, publication) {
			console.debug(`Waiting for publication result of static site ${publication.id} in collective ${collective.id}`)
			this.watchedPublications[publication.id] = {
				collectiveId: collective.id,
				collectiveName: collective.name,
				attemptsLeft: Math.ceil(staticSitePollTimeout / staticSitePollInterval),
			}
			if (!this.pollIntervalId) {
				this.pollIntervalId = setInterval(() => this.pollWatchedPublications(), staticSitePollInterval * 1000)
			}
		},

		/**
		 * Load the static sites of each collective with watched publications once
		 * and conclude the publications that got a result
		 */
		async pollWatchedPublications() {
			const watchedPublications = Object.entries(this.watchedPublications)
			const collectiveIds = new Set()
			for (const [, watched] of watchedPublications) {
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

				for (const [id, watched] of watchedPublications) {
					// Skip publications concluded or restarted meanwhile
					if (watched.collectiveId === collectiveId && this.watchedPublications[id] === watched) {
						this.checkPublication(Number(id), watched, loaded)
					}
				}
			}))

			if (Object.keys(this.watchedPublications).length === 0) {
				this.stopWatching()
			}
		},

		/**
		 * @param {number} id ID of the watched static site
		 * @param {object} watched watched publication
		 * @param {boolean} loaded whether the static sites of its collective got loaded
		 */
		checkPublication(id, watched, loaded) {
			const staticSite = loaded
				? this.staticSites[watched.collectiveId].find((site) => site.id === id)
				: undefined

			if (staticSite?.status === 'published') {
				delete this.watchedPublications[id]
				showSuccess(t('collectives', 'Website "{title}" of collective "{collective}" published at {url}', {
					title: staticSite.title,
					collective: watched.collectiveName,
					url: staticSite.publishedUrl,
				}))
				return
			}

			if (staticSite?.status === 'failed') {
				delete this.watchedPublications[id]
				showError(t('collectives', 'Website "{title}" of collective "{collective}" could not be published: {reason}', {
					title: staticSite.title,
					collective: watched.collectiveName,
					reason: staticSiteErrorMessage(staticSite.lastError),
				}))
				return
			}

			if (staticSite && !staticSite.isInProgress) {
				delete this.watchedPublications[id]
				showError(t('collectives', 'Website "{title}" of collective "{collective}" could not be published: {reason}', {
					title: staticSite.title,
					collective: watched.collectiveName,
					reason: t('collectives', 'The publish service didn\'t respond in time.'),
				}))
				return
			}

			if ((loaded && !staticSite) || watched.attemptsLeft <= 0) {
				console.debug(`Giving up on publication result of static site ${id}, status ${staticSite?.status ?? (loaded ? 'deleted' : 'unknown')}`)
				delete this.watchedPublications[id]
			}
		},

		stopWatching() {
			clearInterval(this.pollIntervalId)
			this.pollIntervalId = null
			this.watchedPublications = {}
		},
	},
})
