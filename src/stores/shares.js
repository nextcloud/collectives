/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore, storeToRefs } from 'pinia'
import { computed, ref } from 'vue'
import { useRootStore } from './root.js'
import { useCollectivesStore } from './collectives.js'
import * as api from '../apis/collectives/index.js'

export const useSharesStore = defineStore('shares', () => {
	const { load, done } = useRootStore()
	const { currentCollective } = storeToRefs(useCollectivesStore())

	const allShares = ref([])

	const shares = computed(() => {
		return allShares.value
			.filter((s) => s.collectiveId === currentCollective.value.id)
	})

	const sharesByPageId = (pageId) => computed(() => {
		return shares.value
			.filter(s => s.pageId === pageId)
	})

	const shareIndex = ({ id }) => shares.value
		.findIndex(s => s.id === id)

	/**
	 * Get shares of a collective and its pages
	 */
	async function getShares() {
		load('shares')
		const response = await api.getShares(currentCollective.value.id)
		response.data.data.forEach(_addOrUpdateShareState)
		shares.value
			.filter(share => !response.data.data.some(s => s.id === share.id))
			.forEach(deleteShare)
		done('shares')
	}

	const _addOrUpdateShareState = share => {
		const collectiveId = currentCollective.value.id
		const idx = shareIndex(share)
		if (idx === -1) {
			allShares.value.unshift({ ...share, collectiveId })
		} else {
			allShares.value.splice(idx, 1, { ...share, collectiveId })
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
		const idx = shareIndex(share)
		await api.deleteShare(share)
		shares.value?.splice(idx, 1)
		done('unshare')
	}

	return { allShares, shares, sharesByPageId, getShares, createShare, updateShare, deleteShare }
})
