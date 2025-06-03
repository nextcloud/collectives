/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore, storeToRefs } from 'pinia'
import { computed, reactive } from 'vue'
import { useRootStore } from './root.js'
import { useCollectivesStore } from './collectives.js'
import * as api from '../apis/collectives/index.js'

export const useSharesStore = defineStore('shares', () => {
	const { load, done } = useRootStore()
	const { currentCollective } = storeToRefs(useCollectivesStore())

	const allShares = reactive({})

	const shares = computed(() => allShares[currentCollective.id] ?? [])
	const sharesByPageId = computed(() => {
		return (pageId) => shares.value.filter(s => s.pageId === pageId)
	})
	const shareIndex = computed(() => {
		return ({ id }) => shares.value.findIndex(s => s.id === id)
	})

	/**
	 * Get shares of a collective and its pages
	 */
	async function getShares() {
		load('shares')
		const response = await api.getShares(currentCollective.id)
		allShares[currentCollective.id] = response.data.data
		done('shares')
	}

	const _addOrUpdateShareState = share => {
		const idx = shareIndex(share).value
		allShares[currentCollective.id] ??= []
		if (idx === -1) {
			allShares[currentCollective.id].unshift(share)
		} else {
			allShares[currentCollective.id].splice(idx, 1, share)
		}
	}

	/**
	 * Create a public collective/page share
	 *
	 * @param {object} object the property object
	 * @param {number} object.collectiveId ID of the collective to be shared
	 * @param {number} object.pageId ID of the page to be shared
	 * @param {string} object.password optional password for the share
	 */
	async function createShare({ collectiveId, pageId = 0, password }) {
		load('share')
		const response = pageId
			? await api.createPageShare(collectiveId, pageId, password)
			: await api.createCollectiveShare(collectiveId, password)
		_addOrUpdateShareState(response.data.data)
		done('share')
	}

	/**
	 * Update a public collective/page share
	 *
	 * @param {object} share the share to be updated
	 */
	async function updateShare(share) {
		load('share')
		const response = await api.updateShare(share)
		_addOrUpdateShareState(response.data.data)
		done('share')
	}

	/**
	 * Delete a public collective/page share
	 *
	 * @param {object} share the share to be deleted
	 */
	async function deleteShare(share) {
		load('unshare')
		const idx = shareIndex(share).value
		await api.deleteShare(share)
		allShares[currentCollective.id]?.splice(idx, 1)
		done('unshare')
	}

	return { sharesByPageId, getShares, createShare, updateShare, deleteShare }
})
