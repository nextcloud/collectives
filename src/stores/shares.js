/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useLocalStorage } from '@vueuse/core'
import { defineStore } from 'pinia'
import { set } from 'vue'
import * as api from '../apis/collectives/index.js'
import { useCollectivesStore } from './collectives.js'
import { useRootStore } from './root.js'

const STORE_PREFIX = 'collectives/pinia/shares/'

export const useSharesStore = defineStore('shares', {
	state: () => ({
		allShares: useLocalStorage(STORE_PREFIX + 'allShares', {}),
	}),

	getters: {
		collectiveId() {
			const collectivesStore = useCollectivesStore()
			return collectivesStore.currentCollective.id
		},

		shares: (state) => {
			return state.allShares[state.collectiveId] || []
		},

		sharesByPageId: (state) => {
			return (pageId) => state.shares.filter((s) => s.pageId === pageId)
		},
	},

	actions: {
		/**
		 * Get shares of a collective and its pages
		 */
		async getShares() {
			const rootStore = useRootStore()
			rootStore.load('shares')
			const response = await api.getShares(this.collectiveId)
			set(this.allShares, this.collectiveId, response.data.ocs.data)
			rootStore.done('shares')
		},

		_addOrUpdateShareState(share) {
			if (!this.allShares[this.collectiveId]) {
				set(this.allShares, this.collectiveId, [])
			}
			const idx = this.shares.findIndex((s) => s.id === share.id)
			if (idx === -1) {
				this.allShares[this.collectiveId].unshift(share)
			} else {
				this.allShares[this.collectiveId].splice(idx, 1, share)
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
			this._addOrUpdateShareState(response.data.ocs.data)
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
			this._addOrUpdateShareState(response.data.ocs.data)
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
			const idx = this.shares.findIndex((s) => s.id === share.id)
			if (idx !== -1) {
				this.allShares[collectiveId]?.splice(idx, 1)
			}
			rootStore.done('unshare')
		},
	},
})
