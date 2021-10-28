import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { byName } from '../util/sortOrders'

import {
	SET_COLLECTIVES,
	SET_TRASH_COLLECTIVES,
	ADD_OR_UPDATE_COLLECTIVE,
	MOVE_COLLECTIVE_INTO_TRASH,
	RESTORE_COLLECTIVE_FROM_TRASH,
	DELETE_COLLECTIVE_FROM_TRASH,
	DELETE_CIRCLE_FOR,
} from './mutations'

import {
	GET_COLLECTIVES,
	GET_TRASH_COLLECTIVES,
	NEW_COLLECTIVE,
	UPDATE_COLLECTIVE,
	TRASH_COLLECTIVE,
	DELETE_COLLECTIVE,
	RESTORE_COLLECTIVE,
} from './actions'

export default {
	state: {
		collectives: [],
		trashCollectives: [],
		updatedCollective: {},
	},
	getters: {
		currentCollective(state, getters) {
			return state.collectives.find(
				(collective) => collective.name === getters.collectiveParam
			)
		},

		currentCollectivePath(state, getters) {
			if (getters.isPublic) {
				return `/p/${getters.shareTokenParam}/${encodeURIComponent(getters.currentCollective.name)}`
			} else {
				return `/${encodeURIComponent(getters.currentCollective.name)}`
			}
		},

		collectives(state, getters) {
			return state.collectives.sort(byName)
		},

		trashCollectives(state, getters) {
			return state.trashCollectives.sort(byName)
		},

		updatedCollectivePath(state) {
			const collective = state.updatedCollective
			return collective && `/${encodeURIComponent(collective.name)}`
		},

		collectiveChanged(state, getters) {
			const updated = state.updatedCollective
				&& state.updatedCollective.name
			const current = getters.currentCollective
				&& getters.currentCollective.name
			return updated && (updated !== current)
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
			if (getters.isPublic) {
				return
			}
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
		 * @param {object} collective Properties for the new collective
		 */
		async [NEW_COLLECTIVE]({ commit }, collective) {
			const response = await axios.post(
				generateUrl('/apps/collectives/_api'),
				collective,
			)
			commit('info', response.data.message)
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
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
	},

}
