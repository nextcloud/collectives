/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useLocalStorage } from '@vueuse/core'
import { defineStore } from 'pinia'
import { set } from 'vue'
import * as api from '../apis/collectives/index.js'
import { memberLevels } from '../constants.js'
import randomEmoji from '../util/randomEmoji.js'
import { byName } from '../util/sortOrders.js'
import { useCirclesStore } from './circles.js'
import { removeFrom, updateOrAddTo } from './collectionHelpers.js'
import { useRootStore } from './root.js'
import { useSettingsStore } from './settings.js'

const STORE_PREFIX = 'collectives/pinia/collectives/'

export const useCollectivesStore = defineStore('collectives', {
	state: () => ({
		collectivesState: useLocalStorage(STORE_PREFIX + 'collectives', []),
		publicCollectivesState: useLocalStorage(STORE_PREFIX + 'publicCollectives', {}),
		trashCollectives: useLocalStorage(STORE_PREFIX + 'trashCollectives', []),
		updatedCollective: undefined,
		templatesCollectiveId: undefined,
		membersCollectiveId: undefined,
		settingsCollectiveId: undefined,
	}),

	getters: {
		publicCollective(state) {
			const rootStore = useRootStore()
			return rootStore.isPublic
				? state.publicCollectivesState[rootStore.shareTokenParam]
				: null
		},

		collectives(state) {
			const rootStore = useRootStore()
			return rootStore.isPublic
				? [state.publicCollective]
				: state.collectivesState
		},

		sortedCollectives(state) {
			return state.collectives.sort(byName)
		},

		sortedTrashCollectives(state) {
			return state.trashCollectives.sort(byName)
		},

		currentCollective(state) {
			const rootStore = useRootStore()
			if (rootStore.isPublic) {
				return state.publicCollective
			}
			if (rootStore.collectiveId) {
				return state.collectives.find((collective) => collective.id === rootStore.collectiveId)
			}
			return state.collectives.find((collective) => collective.name === rootStore.collectiveParam)
		},

		collectivePath() {
			return (collective, print = false) => {
				const rootStore = useRootStore()
				const slugOrName = collective.slug ? `${collective.slug}-${collective.id}` : encodeURIComponent(collective.name)
				let prefix = ''
				if (rootStore.isPublic) {
					prefix = `/p/${encodeURIComponent(rootStore.shareTokenParam)}`
					prefix += print ? '/print' : ''
				} else {
					prefix += print ? '/_/print' : ''
				}
				return `${prefix}/${slugOrName}`
			}
		},

		currentCollectivePath(state) {
			return state.collectivePath(state.currentCollective)
		},

		collectivePrintPath(state) {
			return (collective) => {
				return state.collectivePath(collective, true)
			}
		},

		collectiveTitle(state) {
			return (collectiveId) => state.collectives.find((c) => c.id === collectiveId).name
		},

		currentCollectiveTitle(state) {
			const { emoji, name } = state.currentCollective
			return emoji ? `${emoji} ${name}` : name
		},

		currentCollectiveIsPageShare(state) {
			return state.currentCollective.isPageShare
		},

		currentCollectiveCanEdit(state) {
			return state.collectiveCanEdit(state.currentCollective)
		},

		currentCollectiveCanShare(state) {
			return state.collectiveCanShare(state.currentCollective)
		},

		updatedCollectivePath(state) {
			return state.collectivePath(state.updatedCollective)
		},

		collectiveChanged(state) {
			const updated = state.updatedCollective
				&& state.updatedCollective.name
			const current = state.currentCollective
				&& state.currentCollective.name
			return updated && (updated !== current)
		},

		isCollectiveAdmin: () => (collective) => {
			return collective.level >= memberLevels.LEVEL_ADMIN
		},

		isCollectiveOwner: () => (collective) => {
			return collective.level >= memberLevels.LEVEL_OWNER
		},

		collectiveCanEdit: () => (collective) => {
			const rootStore = useRootStore()
			if (!collective) {
				return false
			}
			// For public collectives, take shareEditable into account
			if (rootStore.isPublic && !collective.shareEditable) {
				return false
			}
			return collective.canEdit
		},

		collectiveCanShare: () => (collective) => {
			const rootStore = useRootStore()
			if (!collective) {
				return false
			}
			if (rootStore.isPublic) {
				return false
			}
			return collective.canShare
		},

		allCollectiveEmojis(state) {
			return state.collectives.filter((c) => c.emoji).map((c) => c.emoji)
		},

		// Return a function (with empty arguments list) to prevent caching the result
		randomCollectiveEmoji: (state) => () => {
			return randomEmoji(state.allCollectiveEmojis)
		},

		membersCollective(state) {
			return state.membersCollectiveId
				? state.collectives.find((c) => c.id === state.membersCollectiveId)
				: null
		},

		settingsCollective(state) {
			return state.settingsCollectiveId
				? state.collectives.find((c) => c.id === state.settingsCollectiveId)
				: null
		},

		isFavoritePage: (state) => (id, pageId) => {
			return state.collectives.find((c) => c.id === id).userFavoritePages.includes(pageId)
		},
	},

	actions: {
		setTemplatesCollectiveId(id) {
			this.templatesCollectiveId = id
		},

		setMembersCollectiveId(id) {
			this.membersCollectiveId = id
		},

		setSettingsCollectiveId(id) {
			this.settingsCollectiveId = id
		},

		/**
		 * Get list of all collectives
		 */
		async getCollectives() {
			const rootStore = useRootStore()
			rootStore.load('collectives')
			try {
				if (rootStore.isPublic) {
					const response = await api.getSharedCollective(rootStore.shareTokenParam)
					set(this.publicCollectivesState, rootStore.shareTokenParam, response.data.ocs.data.collectives[0])
				} else {
					const response = await api.getCollectives()
					this.collectivesState = response.data.ocs.data.collectives
				}
			} finally {
				rootStore.done('collectives')
			}
		},

		/**
		 * Get list of all collectives in trash
		 */
		async getTrashCollectives() {
			const rootStore = useRootStore()
			rootStore.load('collectiveTrash')
			const response = await api.getTrashCollectives()
			this.trashCollectives = response.data.ocs.data.collectives
			rootStore.done('collectiveTrash')
		},

		_addOrUpdateCollectiveState(collective) {
			updateOrAddTo(this.collectives, collective)
			this.updatedCollective = collective
		},

		removeCollectiveFromState(collective) {
			removeFrom(this.collectives, collective)
		},

		patchCollectiveWithProperty({ id, property, value }) {
			this.collectives.find((c) => c.id === id)[property] = value
		},

		patchCollectiveWithCircle(circle) {
			this.collectives.find((c) => c.circleId === circle.id).name = circle.sanitizedName
		},

		/**
		 * Create a new collective with the given properties
		 *
		 * @param {object} collective Properties for the new collective
		 */
		async newCollective(collective) {
			const settingsStore = useSettingsStore()
			const response = await api.newCollective(collective)
			this._addOrUpdateCollectiveState(response.data.ocs.data.collective)
			// If collectives folder wasn't initialized already, now it should be there
			if (!settingsStore.collectivesFolder) {
				await settingsStore.getCollectivesFolder()
			}
			return response.data.ocs.data.info
		},

		/**
		 * Update a collective with the given properties
		 *
		 * @param {object} collective Properties for the collective
		 */
		async updateCollective(collective) {
			const response = await api.updateCollective(collective)
			this._addOrUpdateCollectiveState(response.data.ocs.data.collective)
		},

		/**
		 * Trash a collective with the given id
		 *
		 * @param {object} collective identifying object for the collective
		 * @param {number} collective.id ID of the collective to be trashed
		 */
		async trashCollective({ id }) {
			const response = await api.trashCollective(id)
			const collective = response.data.ocs.data.collective
			removeFrom(this.collectives, collective)
			this.trashCollectives.unshift(collective)
		},

		/**
		 * Restore a collective with the given id from trash
		 *
		 * @param {object} collective identifying object for the collective
		 * @param {number} collective.id ID of the collective to be restored
		 */
		async restoreCollective({ id }) {
			const response = await api.restoreCollective(id)
			const collective = response.data.ocs.data.collective
			this.collectives.unshift(collective)
			this.trashCollectives.splice(this.trashCollectives.findIndex((c) => c.id === collective.id), 1)
		},

		/**
		 * Delete a collective with the given id from trash
		 *
		 * @param {object} collective the collective with id and team
		 * @param {number} collective.id ID of the collective to be trashed
		 * @param {boolean} collective.circle Whether to delete the team as well
		 */
		async deleteCollective({ id, circle }) {
			const circlesStore = useCirclesStore()
			const response = await api.deleteCollective(id, circle)
			const collective = response.data.ocs.data.collective
			this.trashCollectives.splice(this.trashCollectives.findIndex((c) => c.id === collective.id), 1)
			if (circle) {
				circlesStore.deleteCircleForCollectiveFromState(collective)
			}
		},

		markCollectiveDeleted(collective) {
			collective.deleted = true
			this._addOrUpdateCollectiveState(collective)
		},

		unmarkCollectiveDeleted(collective) {
			delete collective.deleted
			this._addOrUpdateCollectiveState(collective)
		},

		/**
		 * @param {object} data the data object
		 * @param {number} data.id ID of the collective to be updated
		 * @param {number} data.level new minimum level for sharing
		 */
		async updateCollectiveEditPermissions({ id, level }) {
			const response = await api.updateCollectiveEditPermissions(id, level)
			this._addOrUpdateCollectiveState(response.data.ocs.data.collective)
		},

		/**
		 * @param {object} data the data object
		 * @param {number} data.id ID of the collective to be updated
		 * @param {number} data.level new minimum level for sharing
		 */
		async updateCollectiveSharePermissions({ id, level }) {
			const response = await api.updateCollectiveSharePermissions(id, level)
			this._addOrUpdateCollectiveState(response.data.ocs.data.collective)
		},

		/**
		 * @param {object} data the data object
		 * @param {number} data.id ID of the collective to be updated
		 * @param {number} data.mode page mode
		 */
		async updateCollectivePageMode({ id, mode }) {
			const response = await api.updateCollectivePageMode(id, mode)
			this._addOrUpdateCollectiveState(response.data.ocs.data.collective)
		},

		/**
		 * @param {object} data the data object
		 * @param {number} data.id ID of the collective to be updated
		 * @param {number} data.pageId pageId to toggle in favoritePages
		 */
		async toggleFavoritePage({ id, pageId }) {
			const favoritePages = this.collectives
				.find((c) => c.id === id)
				.userFavoritePages
				// Only unique entries, filter out duplicates
				.filter((value, i, a) => a.indexOf(value) === i)

			if (favoritePages.indexOf(pageId) === -1) {
				favoritePages.push(pageId)
			} else {
				favoritePages.splice(favoritePages.findIndex((id) => id === pageId), 1)
			}
			await this.setCollectiveUserSettingFavoritePages({ id, favoritePages })
		},

		/**
		 * Set the page order for the current user
		 *
		 * @param {object} data the data object
		 * @param {number} data.id ID of the colletive to be updated
		 * @param {number} data.pageOrder the desired page order for the current user
		 */
		async setCollectiveUserSettingPageOrder({ id, pageOrder }) {
			await api.setCollectiveUserSettingPageOrder(id, pageOrder)
			this.patchCollectiveWithProperty({ id, property: 'userPageOrder', value: pageOrder })
		},

		async setCollectiveUserSettingShowMembers({ id, showMembers }) {
			this.patchCollectiveWithProperty({ id, property: 'userShowMembers', value: showMembers })
			await api.setCollectiveUserSettingShowMembers(id, showMembers)
		},

		async setCollectiveUserSettingShowRecentPages({ id, showRecentPages }) {
			this.patchCollectiveWithProperty({ id, property: 'userShowRecentPages', value: showRecentPages })
			await api.setCollectiveUserSettingShowRecentPages(id, showRecentPages)
		},

		async setCollectiveUserSettingFavoritePages({ id, favoritePages }) {
			this.patchCollectiveWithProperty({ id, property: 'userFavoritePages', value: favoritePages })
			await api.setCollectiveUserSettingFavoritePages(id, favoritePages)
		},
	},
})
