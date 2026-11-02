/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import * as api from '../apis/collectives/index.js'
import { staticSitePollInterval, staticSitePublicationTimeout } from '../constants.js'
import { staticSiteErrorMessage } from '../util/staticSiteError.js'

export const useStaticSitesStore = defineStore('staticSites', {
	state: () => ({
		/** @type {Object<number, object[]>} collective ID -> static sites */
		staticSites: {},
		/** @type {?number} */
		pollIntervalId: null,
	}),

	getters: {
		publishedUrlForCollective: (state) => (collectiveId) => {
			return state.staticSites[collectiveId]?.find((staticSite) => staticSite.publishedUrl)?.publishedUrl ?? null
		},

		staticSiteForCollective: (state) => (collectiveId) => {
			return state.staticSites[collectiveId]?.[0] ?? null
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
		 * Polling starts over with every publication and stops as soon as the website got
		 * published or failed - or if the publication vanished or stayed quiet for too long.
		 *
		 * @param {number} collectiveId ID of the collective
		 * @param {object} publication Static site as returned when the publication was started
		 */
		watchPublication(collectiveId, publication) {
			this.stopWatching()
			console.debug(`Waiting for publication result of static site ${publication.id} in collective ${collectiveId}`)

			let attemptsLeft = Math.ceil(staticSitePublicationTimeout / staticSitePollInterval)
			this.pollIntervalId = setInterval(async () => {
				// Failed requests count as well, e.g. after the user got logged out
				const isLastAttempt = --attemptsLeft <= 0
				try {
					await this.getStaticSites(collectiveId)
				} catch (e) {
					console.error('Could not load static sites for collective', e)
					if (isLastAttempt) {
						console.debug(`Giving up on publication result of static site ${publication.id}, requests failed`)
						this.stopWatching()
					}
					return
				}

				const staticSite = this.staticSites[collectiveId].find(({ id }) => id === publication.id)
				// The status and the published URL get stored one after the other
				if (staticSite?.status === 'published' && staticSite.publishedUrl) {
					this.stopWatching()
					showSuccess(t('collectives', 'Website "{title}" published at {url}', {
						title: staticSite.title,
						url: staticSite.publishedUrl,
					}))
					return
				}

				if (staticSite?.status === 'failed') {
					this.stopWatching()
					showError(t('collectives', 'Website "{title}" could not be published: {reason}', {
						title: staticSite.title,
						reason: staticSiteErrorMessage(staticSite.lastError),
					}))
					return
				}

				if (!staticSite || isLastAttempt) {
					console.debug(`Giving up on publication result of static site ${publication.id}, status ${staticSite?.status ?? 'deleted'}`)
					this.stopWatching()
				}
			}, staticSitePollInterval * 1000)
		},

		stopWatching() {
			clearInterval(this.pollIntervalId)
			this.pollIntervalId = null
		},
	},
})
