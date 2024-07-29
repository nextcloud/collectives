/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { useRootStore } from './root.js'
import { useCollectivesStore } from './collectives.js'
import * as api from '../apis/collectives/index.js'

export const useSharesStore = defineStore('shares', {
	state: () => ({
		shares: [],
	}),

	getters: {
		sharesByPageId: (state) => (pageId) => {
			return state.shares.filter(s => s.pageId === pageId)
		},
	},

	actions: {
		/**
		 * Get shares of a collective and its pages
		 */
		async getShares() {
			const rootStore = useRootStore()
			const collectivesStore = useCollectivesStore()
			rootStore.load('shares')
			const response = await api.getShares(collectivesStore.currentCollective.id)
			this.shares = response.data.data
			rootStore.done('shares')
		},

		_addOrUpdateShareState(share) {
			const cur = this.shares.findIndex(s => s.id === share.id)
			if (cur === -1) {
				this.shares.unshift(share)
			} else {
				this.shares.splice(cur, 1, share)
			}
		},

		/**
		 * Create a public collective/page share
		 *
		 * @param {object} object the property object
		 * @param {number} object.collectiveId ID of the collective to be shared
		 * @param {number} object.pageId ID of the page to be shared
		 * @param {string} object.password optional password for the share
		 */
		async createShare({ collectiveId, pageId = 0, password }) {
			const rootStore = useRootStore()
			rootStore.load('share')
			const response = pageId
				? await api.createPageShare(collectiveId, pageId, password)
				: await api.createCollectiveShare(collectiveId, password)
			this._addOrUpdateShareState(response.data.data)
			rootStore.done('share')
		},

		/**
		 * Update a public collective/page share
		 *
		 * @param {object} share the share to be updated
		 */
		async updateShare(share) {
			const rootStore = useRootStore()
			rootStore.load('share')
			const response = await api.updateShare(share)
			this._addOrUpdateShareState(response.data.data)
			rootStore.done('share')
		},

		/**
		 * Delete a public collective/page share
		 *
		 * @param {object} share the share to be deleted
		 */
		async deleteShare(share) {
			const rootStore = useRootStore()
			rootStore.load('unshare')
			await api.deleteShare(share)
			this.shares.splice(this.shares.findIndex(s => s.id === share.id), 1)
			rootStore.done('unshare')
		},

		unsetShares() {
			this.shares = []
		},
	},
})
