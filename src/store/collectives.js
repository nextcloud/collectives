import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

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

		updatedCollectivePath(state) {
			const collective = state.updatedCollective
			return collective && `/${collective.name}`
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
		 */
		async [GET_COLLECTIVES]({ commit }) {
			commit('load', 'collectives', { root: true })
			const response = await axios.get(generateUrl('/apps/collectives/_collectives'))
			commit(SET_COLLECTIVES, response.data.data)
			commit('done', 'collectives', { root: true })
		},

		/**
		 * Get list of all collectives in trash
		 */
		async [GET_TRASH_COLLECTIVES]({ commit }) {
			commit('load', 'collectiveTrash')
			const response = await axios.get(generateUrl('/apps/collectives/_collectives/trash'))
			commit(SET_TRASH_COLLECTIVES, response.data.data)
			commit('done', 'collectiveTrash')
		},

		/**
		 * Create a new collective with the given properties
		 * @param {Object} collective Properties for the new collective (name for now)
		 */
		async [NEW_COLLECTIVE]({ commit }, collective) {
			const response = await axios.post(
				generateUrl('/apps/collectives/_collectives'),
				collective,
			)
			commit('info', response.data.message, { root: true })
			commit(ADD_OR_UPDATE_COLLECTIVE, response.data.data)
		},

		/**
		 * Trash a collective with the given id
		 * @param {Number} id ID of the colletive to be trashed
		 */
		async [TRASH_COLLECTIVE]({ commit }, { id }) {
			const response = await axios.delete(generateUrl('/apps/collectives/_collectives/' + id))
			commit(MOVE_COLLECTIVE_INTO_TRASH, response.data.data)
		},

		/**
		 * Restore a collective with the given id from trash
		 * @param {Number} id ID of the colletive to be trashed
		 */
		async [RESTORE_COLLECTIVE]({ commit }, { id }) {
			const response = await axios.patch(generateUrl('/apps/collectives/_collectives/trash/' + id))
			commit(RESTORE_COLLECTIVE_FROM_TRASH, response.data.data)
		},

		/**
		 * Delete a collective with the given id from trash
		 * @param {Number} id ID of the colletive to be trashed
		 * @param {boolean} circle Whether to delete the circle as well
		 */
		async [DELETE_COLLECTIVE]({ commit }, { id, circle }) {
			let doCircle = ''
			if (circle) {
				doCircle = '?circle=1'
			}
			const response = await axios.delete(generateUrl('/apps/collectives/_collectives/trash/' + id + doCircle))
			commit(DELETE_COLLECTIVE_FROM_TRASH, response.data.data)
			if (circle) {
				commit(DELETE_CIRCLE_FOR, response.data.data, { root: true })
			}
		},
	},

}
