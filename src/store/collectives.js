import { byName } from '../util/sortOrders.js'
import randomEmoji from '../util/randomEmoji.js'
import { memberLevels } from '../constants.js'
import * as api from '../apis/collectives/index.js'

import {
	SET_COLLECTIVES,
	SET_TRASH_COLLECTIVES,
	ADD_OR_UPDATE_COLLECTIVE,
	PATCH_COLLECTIVE_WITH_CIRCLE,
	PATCH_COLLECTIVE_WITH_PROPERTY,
	MOVE_COLLECTIVE_INTO_TRASH,
	RESTORE_COLLECTIVE_FROM_TRASH,
	DELETE_COLLECTIVE_FROM_TRASH,
	DELETE_CIRCLE_FOR,
	REMOVE_COLLECTIVE,
	SET_SHARES,
	ADD_OR_UPDATE_SHARE,
	REMOVE_SHARE,
} from './mutations.js'

import {
	GET_COLLECTIVES,
	GET_TRASH_COLLECTIVES,
	NEW_COLLECTIVE,
	UPDATE_COLLECTIVE,
	TRASH_COLLECTIVE,
	DELETE_COLLECTIVE,
	RESTORE_COLLECTIVE,
	GET_SHARES,
	CREATE_SHARE,
	UPDATE_SHARE,
	DELETE_SHARE,
	UPDATE_COLLECTIVE_EDIT_PERMISSIONS,
	UPDATE_COLLECTIVE_SHARE_PERMISSIONS,
	UPDATE_COLLECTIVE_PAGE_MODE,
	SET_COLLECTIVE_USER_SETTING_PAGE_ORDER,
	SET_COLLECTIVE_USER_SETTING_SHOW_RECENT_PAGES,
	MARK_COLLECTIVE_DELETED,
	UNMARK_COLLECTIVE_DELETED,
	GET_COLLECTIVES_FOLDER,
} from './actions.js'

export default {
	state: {
		collectives: [],
		trashCollectives: [],
		shares: [],
		updatedCollective: undefined,
		membersCollectiveId: undefined,
		settingsCollectiveId: undefined,
	},

	getters: {
		currentCollective(state, getters) {
			return state.collectives.find(
				(collective) => collective.name === getters.collectiveParam,
			)
		},

		collectivePath(_state, getters) {
			return (collective) => {
				if (getters.isPublic) {
					return `/p/${getters.shareTokenParam}/${encodeURIComponent(collective.name)}`
				} else {
					return `/${encodeURIComponent(collective.name)}`
				}
			}
		},

		currentCollectivePath(_state, getters) {
			return getters.collectivePath(getters.currentCollective)
		},

		currentCollectiveTitle(_state, getters) {
			const { emoji, name } = getters.currentCollective
			return emoji ? `${emoji} ${name}` : name
		},

		collectiveTitle(state, getters) {
			return (collectiveId) => state.collectives.find(c => c.id === collectiveId).name
		},

		currentCollectiveIsPageShare(_state, getters) {
			return getters.currentCollective.isPageShare
		},

		currentCollectiveCanEdit(_state, getters) {
			return getters.collectiveCanEdit(getters.currentCollective)
		},

		currentCollectiveCanShare(_state, getters) {
			return getters.collectiveCanShare(getters.currentCollective)
		},

		collectives(state) {
			return state.collectives.sort(byName)
		},

		trashCollectives(state) {
			return state.trashCollectives.sort(byName)
		},

		updatedCollectivePath(state) {
			const collective = state.updatedCollective
			return collective?.name && `/${encodeURIComponent(collective.name)}`
		},

		collectiveChanged(state, getters) {
			const updated = state.updatedCollective
				&& state.updatedCollective.name
			const current = getters.currentCollective
				&& getters.currentCollective.name
			return updated && (updated !== current)
		},

		sharesByPageId: (state) => (pageId) => {
			return state.shares.filter(s => s.pageId === pageId)
		},

		isCollectiveAdmin: (state) => (collective) => {
			return collective.level >= memberLevels.LEVEL_ADMIN
		},

		isCollectiveOwner: (state) => (collective) => {
			return collective.level >= memberLevels.LEVEL_OWNER
		},

		collectiveCanEdit: (_state, getters) => (collective) => {
			if (!collective) {
				return false
			}
			// For public collectives, take shareEditable into account
			if (getters.isPublic && !collective.shareEditable) {
				return false
			}
			return collective.canEdit
		},

		collectiveCanShare: (_state, getters) => (collective) => {
			if (!collective) {
				return false
			}
			if (getters.isPublic) {
				return false
			}
			return collective.canShare
		},

		allCollectiveEmojis(state) {
			return state.collectives.filter(c => c.emoji).map(c => c.emoji)
		},

		// Return a function (with empty arguments list) to prevent caching the result
		randomCollectiveEmoji: (_state, getters) => () => {
			return randomEmoji(getters.allCollectiveEmojis)
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

	mutations: {
		[SET_COLLECTIVES](state, collectives) {
			state.collectives = collectives
		},

		[SET_TRASH_COLLECTIVES](state, trashCollectives) {
			state.trashCollectives = trashCollectives
		},

		[ADD_OR_UPDATE_COLLECTIVE](state, collective) {
			const cur = state.collectives.findIndex(c => c.id === collective.id)
			if (cur === -1) {
				state.collectives.unshift(collective)
			} else {
				state.collectives.splice(cur, 1, collective)
			}
			state.updatedCollective = collective
		},

		[PATCH_COLLECTIVE_WITH_CIRCLE](state, circle) {
			state.collectives.find(c => c.circleId === circle.id).name = circle.sanitizedName
		},

		[PATCH_COLLECTIVE_WITH_PROPERTY](state, { id, property, value }) {
			state.collectives.find(c => c.id === id)[property] = value
		},

		[MOVE_COLLECTIVE_INTO_TRASH](state, collective) {
			state.collectives.splice(state.collectives.findIndex(c => c.id === collective.id), 1)
			state.trashCollectives.unshift(collective)
		},

		[RESTORE_COLLECTIVE_FROM_TRASH](state, collective) {
			state.collectives.unshift(collective)
			state.trashCollectives.splice(state.trashCollectives.findIndex(c => c.id === collective.id), 1)
		},

		[DELETE_COLLECTIVE_FROM_TRASH](state, collective) {
			state.trashCollectives.splice(state.trashCollectives.findIndex(c => c.id === collective.id), 1)
		},

		[REMOVE_COLLECTIVE](state, collective) {
			state.collectives.splice(state.collectives.findIndex(c => c.id === collective.id), 1)
		},

		[SET_SHARES](state, shares) {
			state.shares = shares
		},

		[ADD_OR_UPDATE_SHARE](state, share) {
			const cur = state.shares.findIndex(s => s.id === share.id)
			if (cur === -1) {
				state.shares.unshift(share)
			} else {
				state.shares.splice(cur, 1, share)
			}
		},

		[REMOVE_SHARE](state, share) {
			state.shares.splice(state.shares.findIndex(s => s.id === share.id), 1)
		},

		setMembersCollectiveId(state, id) {
			state.membersCollectiveId = id
		},

		setSettingsCollectiveId(state, id) {
			state.settingsCollectiveId = id
		},

		unsetShares(state) {
			state.shares = []
		},
	},

	actions: {
		/**
		 * Get list of all collectives
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 */
		async [GET_COLLECTIVES]({ commit, getters }) {
			commit('load', 'collectives')
			try {
				const response = getters.isPublic
					? await api.getSharedCollective(getters.shareTokenParam)
					: await api.getCollectives()
				commit(SET_COLLECTIVES, response.data.data)
			} finally {
				commit('done', 'collectives')
			}
		},

		/**
		 * Get list of all collectives in trash
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 */
		async [GET_TRASH_COLLECTIVES]({ commit }) {
			commit('load', 'collectiveTrash')
			const response = await api.getTrashCollectives()
			commit(SET_TRASH_COLLECTIVES, response.data.data)
			commit('done', 'collectiveTrash')
		},

		/**
		 * Create a new collective with the given properties
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.rootState root state of the store
		 * @param {Function} store.dispatch dispatch actions
		 * @param {object} collective Properties for the new collective
		 */
		async [NEW_COLLECTIVE]({ commit, rootState, dispatch }, collective) {
			const response = await api.newCollective(collective)
			commit('info', response.data.message)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
			// If collectives folder wasn't initialized already, now it should be there
			if (!rootState.settings.collectivesFolder) {
				dispatch(GET_COLLECTIVES_FOLDER)
			}
		},

		/**
		 * Update a collective with the given properties
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective Properties for the collective
		 */
		async [UPDATE_COLLECTIVE]({ commit }, collective) {
			const response = await api.updateCollective(collective)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
		},

		/**
		 * Trash a collective with the given id
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective identifying object for the collective
		 * @param {number} collective.id ID of the collective to be trashed
		 */
		async [TRASH_COLLECTIVE]({ commit }, { id }) {
			const response = await api.trashCollective(id)
			commit(MOVE_COLLECTIVE_INTO_TRASH, response.data.data)
		},

		/**
		 * Restore a collective with the given id from trash
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective identifying object for the collective
		 * @param {number} collective.id ID of the collective to be restored
		 */
		async [RESTORE_COLLECTIVE]({ commit }, { id }) {
			const response = await api.restoreCollective(id)
			commit(RESTORE_COLLECTIVE_FROM_TRASH, response.data.data)
		},

		/**
		 * Delete a collective with the given id from trash
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective the collective with id and team
		 * @param {number} collective.id ID of the collective to be trashed
		 * @param {boolean} collective.circle Whether to delete the team as well
		 */
		async [DELETE_COLLECTIVE]({ commit }, { id, circle }) {
			const response = await api.deleteCollective(id, circle)
			commit(DELETE_COLLECTIVE_FROM_TRASH, response.data.data)
			if (circle) {
				commit(DELETE_CIRCLE_FOR, response.data.data)
			}
		},

		/**
		 * Get shares of a collective and its pages
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 */
		async [GET_SHARES]({ commit, getters }) {
			commit('load', 'shares')
			const response = await api.getShares(getters.currentCollective.id)
			commit(SET_SHARES, response.data.data)
			commit('done', 'shares')
		},

		/**
		 * Create a public collective/page share
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} object the property object
		 * @param {number} object.collectiveId ID of the collective to be shared
		 * @param {number} object.pageId ID of the page to be shared
		 * @param {string} object.password optional password for the share
		 */
		async [CREATE_SHARE]({ commit }, { collectiveId, pageId = 0, password }) {
			commit('load', 'share')
			const response = pageId
				? await api.createPageShare(collectiveId, pageId, password)
				: await api.createCollectiveShare(collectiveId, password)
			commit(ADD_OR_UPDATE_SHARE, response.data.data)
			commit('done', 'share')
		},

		/**
		 * Update a public collective/page share
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} share the share to be updated
		 */
		async [UPDATE_SHARE]({ commit }, share) {
			commit('load', 'share')
			const response = await api.updateShare(share)
			commit(ADD_OR_UPDATE_SHARE, response.data.data)
			commit('done', 'share')
		},

		/**
		 * Delete a public collective/page share
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} share the share to be deleted
		 */
		async [DELETE_SHARE]({ commit }, share) {
			commit('load', 'unshare')
			await api.deleteShare(share)
			commit(REMOVE_SHARE, share)
			commit('done', 'unshare')
		},

		/**
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} data the data object
		 * @param {number} data.id ID of the collective to be updated
		 * @param {number} data.level new minimum level for sharing
		 */
		async [UPDATE_COLLECTIVE_EDIT_PERMISSIONS]({ commit }, { id, level }) {
			const response = await api.updateCollectiveEditPermissions(id, level)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
		},

		/**
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} data the data object
		 * @param {number} data.id ID of the collective to be updated
		 * @param {number} data.level new minimum level for sharing
		 */
		async [UPDATE_COLLECTIVE_SHARE_PERMISSIONS]({ commit }, { id, level }) {
			const response = await api.updateCollectiveSharePermissions(id, level)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
		},

		/**
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} data the data object
		 * @param {number} data.id ID of the collective to be updated
		 * @param {number} data.mode page mode
		 */
		async [UPDATE_COLLECTIVE_PAGE_MODE]({ commit }, { id, mode }) {
			const response = await api.updateCollectivePageMode(id, mode)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
		},

		/**
		 * Set the page order for the current user
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} data the data object
		 * @param {number} data.id ID of the colletive to be updated
		 * @param {number} data.pageOrder the desired page order for the current user
		 */
		async [SET_COLLECTIVE_USER_SETTING_PAGE_ORDER]({ commit }, { id, pageOrder }) {
			await api.setCollectiveUserSettingPageOrder(id, pageOrder)
			commit(PATCH_COLLECTIVE_WITH_PROPERTY, { id, property: 'userPageOrder', value: pageOrder })
		},

		async [SET_COLLECTIVE_USER_SETTING_SHOW_RECENT_PAGES]({ commit }, { id, showRecentPages }) {
			commit(PATCH_COLLECTIVE_WITH_PROPERTY, { id, property: 'userShowRecentPages', value: showRecentPages })
			await api.setCollectiveUserSettingShowRecentPages(id, showRecentPages)
		},

		[MARK_COLLECTIVE_DELETED]({ commit }, collective) {
			collective.deleted = true
			commit(ADD_OR_UPDATE_COLLECTIVE, collective)
		},

		[UNMARK_COLLECTIVE_DELETED]({ commit }, collective) {
			delete collective.deleted
			commit(ADD_OR_UPDATE_COLLECTIVE, collective)
		},
	},
}
