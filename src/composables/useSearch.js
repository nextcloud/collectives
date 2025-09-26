/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { onBeforeUnmount, onMounted, watch } from 'vue'
import { useSearchStore } from '../stores/search.js'

/**
 * Handle search state and events.
 *
 * @param {object} searchable Object to search in.
 */
export function useSearch(searchable) {
	const searchStore = useSearchStore()

	const searchNext = () => {
		searchable.value?.searchNext()
	}

	const searchPrevious = () => {
		searchable.value?.searchPrevious()
	}

	onMounted(() => {
		subscribe('collectives:next-search', searchNext)
		subscribe('collectives:previous-search', searchPrevious)
	})

	onBeforeUnmount(() => {
		unsubscribe('collectives:next-search', searchNext)
		unsubscribe('collectives:previous-search', searchPrevious)
	})

	watch(
		() => searchStore.searchQuery,
		(value) => {
			searchable.value?.setSearchQuery(value)
		},
	)

	watch(
		() => searchStore.matchAll,
		(value) => {
			searchable.value?.setSearchQuery(searchStore.searchQuery, value)
		},
	)
}
