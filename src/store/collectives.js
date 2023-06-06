import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { byName } from '../util/sortOrders.js'
import randomEmoji from '../util/randomEmoji.js'
import { memberLevels } from '../constants.js'

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
} from './mutations.js'

import {
	GET_COLLECTIVES,
	GET_TRASH_COLLECTIVES,
	NEW_COLLECTIVE,
	UPDATE_COLLECTIVE,
	TRASH_COLLECTIVE,
	DELETE_COLLECTIVE,
	RESTORE_COLLECTIVE,
	SHARE_COLLECTIVE,
	UPDATE_SHARE_COLLECTIVE,
	UNSHARE_COLLECTIVE,
	UPDATE_COLLECTIVE_EDIT_PERMISSIONS,
	UPDATE_COLLECTIVE_SHARE_PERMISSIONS,
	UPDATE_COLLECTIVE_PAGE_MODE,
	SET_COLLECTIVE_USER_SETTING_PAGE_ORDER,
	MARK_COLLECTIVE_DELETED,
	UNMARK_COLLECTIVE_DELETED,
	GET_COLLECTIVES_FOLDER,
} from './actions.js'

export default {
	state: {
		collectives: [],
		trashCollectives: [],
		collectiveShares: [],
		updatedCollective: undefined,
		settingsCollectiveId: undefined,
	},

	getters: {
		currentCollective(state, getters) {
			return state.collectives.find(
				(collective) => collective.name === getters.collectiveParam
			)
		},

		collectivePath(state, getters) {
			return (collective) => {
				if (getters.isPublic && collective.shareToken) {
					return `/p/${collective.shareToken}/${encodeURIComponent(collective.name)}`
				} else {
					return `/${encodeURIComponent(collective.name)}`
				}
			}
		},

		currentCollectivePath(state, getters) {
			return getters.collectivePath(getters.currentCollective)
		},

		currentCollectiveTitle(_state, getters) {
			const { emoji, name } = getters.currentCollective
			return emoji ? `${emoji} ${name}` : name
		},

		currentCollectiveCanEdit(state, getters) {
			return getters.collectiveCanEdit(getters.currentCollective)
		},

		currentCollectiveCanShare(state, getters) {
			return getters.collectiveCanShare(getters.currentCollective)
		},

		collectives(state, getters) {
			return state.collectives.sort(byName)
		},

		trashCollectives(state, getters) {
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

		collectiveShare: (state) => ({ id }) => {
			return state.collectiveShares.find(
				(collectiveShare) => collectiveShare.collectiveId === id
			)
		},

		collectiveShareUrl: (state, getters) => (collective) => {
			if (collective.shareToken) {
				return generateUrl(`/apps/collectives/p/${collective.shareToken}/${encodeURIComponent(collective.name)}`)
			} else {
				return null
			}
		},

		isCollectiveAdmin: (state, getters) => (collective) => {
			return collective.level >= memberLevels.LEVEL_ADMIN
		},

		isCollectiveOwner: (state, getters) => (collective) => {
			return collective.level >= memberLevels.LEVEL_OWNER
		},

		collectiveCanEdit: (state, getters) => (collective) => {
			if (!collective) {
				return false
			}
			// For public collectives, take shareEditable into account
			if (getters.isPublic && !collective.shareEditable) {
				return false
			}
			return collective.canEdit
		},

		collectiveCanShare: (state) => (collective) => {
			if (!collective) {
				return false
			}
			return collective.canShare
		},

		allCollectiveEmojis(state) {
			return state.collectives.filter(c => c.emoji).map(c => c.emoji)
		},

		// Return a function (with empty arguments list) to prevent caching the result
		randomCollectiveEmoji: (state, getters) => () => {
			return randomEmoji(getters.allCollectiveEmojis)
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

		setSettingsCollectiveId(state, id) {
			state.settingsCollectiveId = id
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
					? await axios.get(generateUrl(`/apps/collectives/_api/p/${getters.shareTokenParam}`))
					: await axios.get(generateUrl('/apps/collectives/_api'))
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
		 * @param {object} store.getters getters of the store
		 */
		async [GET_TRASH_COLLECTIVES]({ commit, getters }) {
			commit('load', 'collectiveTrash')
			const response = await axios.get(generateUrl('/apps/collectives/_api/trash'))
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
			const response = await axios.post(
				generateUrl('/apps/collectives/_api'),
				collective,
			)
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
			const response = await axios.put(
				generateUrl('/apps/collectives/_api/' + collective.id),
				collective,
			)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
		},

		/**
		 * Trash a collective with the given id
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective identifying object for the collective
		 * @param {number} collective.id ID of the colletive to be trashed
		 */
		async [TRASH_COLLECTIVE]({ commit }, { id }) {
			const response = await axios.delete(generateUrl('/apps/collectives/_api/' + id))
			commit(MOVE_COLLECTIVE_INTO_TRASH, response.data.data)
		},

		/**
		 * Restore a collective with the given id from trash
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective identifying object for the collective
		 * @param {number} collective.id ID of the colletive to be restored
		 */
		async [RESTORE_COLLECTIVE]({ commit }, { id }) {
			const response = await axios.patch(generateUrl('/apps/collectives/_api/trash/' + id))
			commit(RESTORE_COLLECTIVE_FROM_TRASH, response.data.data)
		},

		/**
		 * Delete a collective with the given id from trash
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective the collective with id and circle
		 * @param {number} collective.id ID of the colletive to be trashed
		 * @param {boolean} collective.circle Whether to delete the circle as well
		 */
		async [DELETE_COLLECTIVE]({ commit }, { id, circle }) {
			let doCircle = ''
			if (circle) {
				doCircle = '?circle=1'
			}
			const response = await axios.delete(generateUrl('/apps/collectives/_api/trash/' + id + doCircle))
			commit(DELETE_COLLECTIVE_FROM_TRASH, response.data.data)
			if (circle) {
				commit(DELETE_CIRCLE_FOR, response.data.data)
			}
		},

		/**
		 * Create a public collective share
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective the collective with id
		 * @param {number} collective.id ID of the colletive to be shared
		 */
		async [SHARE_COLLECTIVE]({ commit }, { id }) {
			commit('load', 'share')
			const response = await axios.post(generateUrl('/apps/collectives/_api/' + id + '/share'))
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
			commit('done', 'share')
		},

		/**
		 * Create a public collective share
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective the collective with id
		 * @param {number} collective.id ID of the colletive
		 * @param {string} collective.shareToken Token of the share to be updated
		 * @param {boolean} collective.shareEditable Is collective share editable
		 */
		async [UPDATE_SHARE_COLLECTIVE]({ commit }, { id, shareToken, shareEditable }) {
			commit('load', 'shareEditable')
			const response = await axios.put(
				generateUrl('/apps/collectives/_api/' + id + '/share/' + shareToken),
				{ editable: shareEditable }

			)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
			commit('done', 'shareEditable')
		},

		/**
		 * Delete a public collective share
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {object} collective the collective with id
		 */
		async [UNSHARE_COLLECTIVE]({ commit, getters }, collective) {
			commit('load', 'unshare')
			const response = await axios.delete(
				generateUrl('/apps/collectives/_api/' + collective.id + '/share/' + collective.shareToken)
			)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
			commit('done', 'unshare')
		},

		/**
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} data the data object
		 * @param {number} data.id ID of the colletive to be updated
		 * @param {number} data.level new minimum level for sharing
		 */
		async [UPDATE_COLLECTIVE_EDIT_PERMISSIONS]({ commit }, { id, level }) {
			const response = await axios.put(
				generateUrl('/apps/collectives/_api/' + id + '/editLevel'),
				{ level }
			)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
		},

		/**
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} data the data object
		 * @param {number} data.id ID of the colletive to be updated
		 * @param {number} data.level new minimum level for sharing
		 */
		async [UPDATE_COLLECTIVE_SHARE_PERMISSIONS]({ commit }, { id, level }) {
			const response = await axios.put(
				generateUrl('/apps/collectives/_api/' + id + '/shareLevel'),
				{ level }
			)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
		},

		/**
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} data the data object
		 * @param {number} data.id ID of the colletive to be updated
		 * @param {number} data.mode page mode
		 */
		async [UPDATE_COLLECTIVE_PAGE_MODE]({ commit }, { id, mode }) {
			const response = await axios.put(
				generateUrl('/apps/collectives/_api/' + id + '/pageMode'),
				{ mode }
			)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
		},

		async [SET_COLLECTIVE_USER_SETTING_PAGE_ORDER]({ commit }, { id, pageOrder }) {
			await axios.put(
				generateUrl('/apps/collectives/_api/' + id + '/_userSettings/pageOrder'),
				{ pageOrder }
			)
			commit(PATCH_COLLECTIVE_WITH_PROPERTY, { id, property: 'userPageOrder', value: pageOrder })
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
