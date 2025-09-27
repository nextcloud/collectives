/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getLanguage } from '@nextcloud/l10n'
import { useLocalStorage } from '@vueuse/core'
import { defineStore } from 'pinia'
import { set } from 'vue'
import * as api from '../apis/collectives/index.js'
import { useCollectivesStore } from './collectives.js'
import { useRootStore } from './root.js'

const STORE_PREFIX = 'collectives/pinia/tags/'

export const useTagsStore = defineStore('tags', {
	state: () => ({
		allTags: useLocalStorage(STORE_PREFIX + 'allTags', {}),
		allTagsLoaded: useLocalStorage(STORE_PREFIX + 'allTagsLoaded', {}),
		filterTagIds: [],
	}),

	getters: {
		collectiveId() {
			const collectivesStore = useCollectivesStore()
			return collectivesStore.currentCollective.id
		},

		context() {
			const rootStore = useRootStore()
			return {
				isPublic: rootStore.isPublic,
				collectiveId: this.collectiveId,
				shareTokenParam: rootStore.shareTokenParam,
			}
		},

		tags: (state) => {
			return state.allTags[state.collectiveId] || []
		},

		sortedTags(state) {
			return state.tags
				.sort((a, b) => a.name.localeCompare(b.name, getLanguage(), { ignorePunctuation: true }))
		},

		tagsLoaded: (state) => {
			return state.allTagsLoaded[state.collectiveId] || false
		},

		filterTags: (state) => {
			return state.tags.filter((t) => state.filterTagIds.includes(t.id))
		},
	},

	actions: {
		addFilterTagId(tagId) {
			this.filterTagIds.push(tagId)
		},

		removeFilterTagId(tagId) {
			const idx = this.filterTagIds.indexOf(tagId)
			if (idx > -1) {
				this.filterTagIds.splice(idx, 1)
			}
		},

		clearFilterTags() {
			this.filterTagIds = []
		},

		/**
		 * Get tags of a collective
		 */
		async getTags() {
			const response = await api.getTags(this.context)
			set(this.allTags, this.collectiveId, response.data.ocs.data.tags)
			set(this.allTagsLoaded, this.collectiveId, true)
		},

		_addOrUpdateTagState(tag) {
			if (!this.allTags[this.collectiveId]) {
				set(this.allTags, this.collectiveId, [])
			}
			const idx = this.tags.findIndex((t) => t.id === tag.id)
			if (idx === -1) {
				this.allTags[this.collectiveId].unshift(tag)
			} else {
				this.allTags[this.collectiveId].splice(idx, 1, tag)
			}
		},

		/**
		 * Create a tag
		 *
		 * @param {object} object the property object
		 * @param {string} object.name Name of the tag
		 * @param {string} object.color Color of the tag in hex RGB code
		 */
		async createTag({ name, color }) {
			const response = await api.createTag(this.context, name, color)
			this._addOrUpdateTagState(response.data.ocs.data.tag)
		},

		/**
		 * Update a tag
		 *
		 * @param {object} tag the tag to be updated
		 */
		async updateTag(tag) {
			const response = await api.updateTag(this.context, tag.id, tag.name, tag.color)
			this._addOrUpdateTagState(response.data.ocs.data.tag)
		},

		/**
		 * Delete a tag
		 *
		 * @param {object} tag the tag to be deleted
		 */
		async deleteTag(tag) {
			await api.deleteTag(this.context, tag.id)
			const idx = this.tags.findIndex((t) => t.id === tag.id)
			if (idx !== -1) {
				this.allTags[this.collectiveId]?.splice(idx, 1)
			}
		},
	},
})
