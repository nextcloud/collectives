/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { emit } from '@nextcloud/event-bus'
import { defineStore } from 'pinia'

export const useSearchStore = defineStore('search', {
	state: () => ({
		searchQuery: '',
		matchAll: true,
		shouldShowSearchDialog: false,
		results: null,
	}),

	actions: {
		setSearchQuery(query) {
			this.searchQuery = query
		},
		showSearchDialog(value) {
			this.shouldShowSearchDialog = value
		},
		toggleMatchAll() {
			this.matchAll = !this.matchAll
		},
		setSearchResults(results) {
			this.results = results
		},
		searchNext() {
			emit('collectives:next-search')
			this.matchAll = false
		},
		searchPrevious() {
			emit('collectives:previous-search')
			this.matchAll = false
		},
	},
})
