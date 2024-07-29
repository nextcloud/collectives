/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { useRootStore } from './root.js'
import { byName } from '../util/sortOrders.js'
import { memberLevels } from '../constants.js'
import randomEmoji from '../util/randomEmoji.js'
import * as api from '../apis/collectives/index.js'
import { useSettingsStore } from './settings.js'
import { useCirclesStore } from './circles.js'

export const useCollectivesStore = defineStore('collectives', {
	state: () => ({
		collectives: [],
		trashCollectives: [],
		updatedCollective: undefined,
		membersCollectiveId: undefined,
		settingsCollectiveId: undefined,
	}),

	getters: {
		sortedCollectives(state) {
			return state.collectives.sort(byName)
		},

		sortedTrashCollectives(state) {
			return state.trashCollectives.sort(byName)
		},

		currentCollective(state) {
			const rootStore = useRootStore()
			return state.collectives.find(
				(collective) => collective.name === rootStore.collectiveParam,
			)
		},

		collectivePath() {
			return (collective) => {
				const rootStore = useRootStore()
				if (rootStore.isPublic) {
					return `/p/${rootStore.shareTokenParam}/${encodeURIComponent(collective.name)}`
				} else {
					return `/${encodeURIComponent(collective.name)}`
				}
			}
		},

		currentCollectivePath(state) {
			return state.collectivePath(state.currentCollective)
		},

		collectiveTitle(state) {
			return (collectiveId) => state.collectives.find(c => c.id === collectiveId).name
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
			const collective = state.updatedCollective
			return collective?.name && `/${encodeURIComponent(collective.name)}`
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
			return state.collectives.filter(c => c.emoji).map(c => c.emoji)
		},

		// Return a function (with empty arguments list) to prevent caching the result
		randomCollectiveEmoji: (state) => () => {
			return randomEmoji(state.allCollectiveEmojis)
		},

		membersCollective(state) {
			return state.membersCollectiveId
				? state.collectives.find(c => c.id === state.membersCollectiveId)
				: null
		},

		settingsCollective(state) {
			return state.settingsCollectiveId
				? state.collectives.find(c => c.id === state.settingsCollectiveId)
				: null
		},
	},

	actions: {
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
				const response = rootStore.isPublic
					? await api.getSharedCollective(rootStore.shareTokenParam)
					: await api.getCollectives()
				this.collectives = response.data.data
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
			this.trashCollectives = response.data.data
			rootStore.done('collectiveTrash')
		},

		_addOrUpdateCollectiveState(collective) {
			const cur = this.collectives.findIndex(c => c.id === collective.id)
			if (cur === -1) {
				this.collectives.unshift(collective)
			} else {
				this.collectives.splice(cur, 1, collective)
			}
			this.updatedCollective = collective
		},

		removeCollectiveFromState(collective) {
			this.collectives.splice(this.collectives.findIndex(c => c.id === collective.id), 1)
		},

		patchCollectiveWithProperty({ id, property, value }) {
			this.collectives.find(c => c.id === id)[property] = value
		},

		patchCollectiveWithCircle(circle) {
			this.collectives.find(c => c.id === circle.id).name = circle.sanitizedName
		},

		/**
		 * Create a new collective with the given properties
		 *
		 * @param {object} collective Properties for the new collective
		 */
		async newCollective(collective) {
			const rootStore = useRootStore()
			const settingsStore = useSettingsStore()
			const response = await api.newCollective(collective)
			rootStore.info(response.data.message)
			this._addOrUpdateCollectiveState(response.data.data)
			// If collectives folder wasn't initialized already, now it should be there
			if (!settingsStore.collectivesFolder) {
				await settingsStore.getCollectivesFolder()
			}
		},

		/**
		 * Update a collective with the given properties
		 *
		 * @param {object} collective Properties for the collective
		 */
		async updateCollective(collective) {
			const response = await api.updateCollective(collective)
			this._addOrUpdateCollectiveState(response.data.data)
		},

		/**
		 * Trash a collective with the given id
		 *
		 * @param {object} collective identifying object for the collective
		 * @param {number} collective.id ID of the collective to be trashed
		 */
		async trashCollective({ id }) {
			const response = await api.trashCollective(id)
			const collective = response.data.data
			this.collectives.splice(this.collectives.findIndex(c => c.id === collective.id), 1)
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
			const collective = response.data.data
			this.collectives.unshift(collective)
			this.trashCollectives.splice(this.trashCollectives.findIndex(c => c.id === collective.id), 1)
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
			const collective = response.data.data
			this.trashCollectives.splice(this.trashCollectives.findIndex(c => c.id === collective.id), 1)
			if (circle) {
				circlesStore.deleteCircleForCollectiveFromState(response.data.data)
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
			this._addOrUpdateCollectiveState(response.data.data)
		},

		/**
		 * @param {object} data the data object
		 * @param {number} data.id ID of the collective to be updated
		 * @param {number} data.level new minimum level for sharing
		 */
		async updateCollectiveSharePermissions({ id, level }) {
			const response = await api.updateCollectiveSharePermissions(id, level)
			this._addOrUpdateCollectiveState(response.data.data)
		},

		/**
		 * @param {object} data the data object
		 * @param {number} data.id ID of the collective to be updated
		 * @param {number} data.mode page mode
		 */
		async updateCollectivePageMode({ id, mode }) {
			const response = await api.updateCollectivePageMode(id, mode)
			this._addOrUpdateCollectiveState(response.data.data)
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

		async setCollectiveUserSettingShowRecentPages({ id, showRecentPages }) {
			this.patchCollectiveWithProperty({ id, property: 'userShowRecentPages', value: showRecentPages })
			await api.setCollectiveUserSettingShowRecentPages(id, showRecentPages)
		},
	},
})
