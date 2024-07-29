/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { emit } from '@nextcloud/event-bus'

export const useSearchStore = defineStore('search', {
	state: () => ({
		searchQuery: '',
		matchAll: true,
	}),

	actions: {
		setSearchQuery(query) {
			this.searchQuery = query
			emit('text:editor:search', { query: this.searchQuery, matchAll: this.matchAll })
		},
		toggleMatchAll() {
			this.matchAll = !this.matchAll
			emit('text:editor:search', { query: this.searchQuery, matchAll: this.matchAll })
		},
		nextSearch() {
			this.matchAll = false
			emit('text:editor:search-next', {})
		},
		previousSearch() {
			this.matchAll = false
			emit('text:editor:search-previous', {})
		},
	},
})
