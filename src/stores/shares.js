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
		allShares: {},
	}),

	getters: {
		shares: (state) => {
			const collectivesStore = useCollectivesStore()
			return state.allShares[collectivesStore.currentCollective.id] ?? []
		},

		sharesByPageId: () => (pageId) => {
			return this.shares.filter(s => s.pageId === pageId)
		},
	},

	actions: {
		/**
		 * Get shares of a collective and its pages
		 */
		async getShares() {
			const rootStore = useRootStore()
			const collectivesStore = useCollectivesStore()
			const collectiveId = collectivesStore.currentCollective.id
			rootStore.load('shares')
			const response = await api.getShares(collectiveId)
			this.allShares[collectiveId] = response.data.data
			rootStore.done('shares')
		},

		_addOrUpdateShareState(share) {
			const collectivesStore = useCollectivesStore()
			const collectiveId = collectivesStore.currentCollective.id
			const cur = this.shares.findIndex(s => s.id === share.id)
			this.allShares[collectiveId] ??= []
			if (cur === -1) {
				this.allShares[collectiveId].unshift(share)
			} else {
				this.allShares[collectiveId].splice(cur, 1, share)
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
			const collectivesStore = useCollectivesStore()
			const collectiveId = collectivesStore.currentCollective.id
			const rootStore = useRootStore()
			rootStore.load('unshare')
			await api.deleteShare(share)
			this.allShares[collectiveId]?.splice(this.shares.findIndex(s => s.id === share.id), 1)
			rootStore.done('unshare')
		},
	},
})
