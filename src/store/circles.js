import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { GET_CIRCLES, RENAME_CIRCLE, GET_PAGES } from './actions.js'
import {
	SET_CIRCLES,
	UPDATE_CIRCLE,
	DELETE_CIRCLE_FOR,
	PATCH_COLLECTIVE_WITH_CIRCLE,
} from './mutations.js'

export default {
	state: {
		circles: [],
	},

	getters: {
		availableCircles(state, _getters, rootState) {
			return state.circles
				.filter(circle => circle.initiator) // only circles i am a member of
				.filter(circle => {
					const matchCircleId = c => {
						return (c.circleId === circle.id)
					}
					const alive = rootState.collectives.collectives.find(matchCircleId)
					const trashed = rootState.collectives.trashCollectives.find(matchCircleId)
					return !alive && !trashed
				})
		},
	},

	mutations: {
		[SET_CIRCLES](state, circles) {
			state.circles = circles
		},

		[DELETE_CIRCLE_FOR](state, collective) {
			state.circles.splice(state.circles.findIndex(c => c.id === collective.circleId), 1)
		},

		[UPDATE_CIRCLE](state, circle) {
			state.circles.splice(
				state.circles.findIndex(c => c.id === circle.id),
				1,
				circle
			)
		},
	},

	actions: {
		/**
		 * Get list of all circles
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 */
		async [GET_CIRCLES]({ commit, getters }) {
			if (getters.isPublic) {
				return
			}
			const response = await axios.get(generateOcsUrl('apps/circles/circles'))
			commit(SET_CIRCLES, response.data.ocs.data)
		},

		/**
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {Function} store.dispatch dispatch actions
		 * @param {object} collective the collective with circleId and name
		 */
		async [RENAME_CIRCLE]({ commit, getters, dispatch }, collective) {
			const response = await axios.put(
				generateOcsUrl('apps/circles/circles/' + collective.circleId + '/name'),
				{ value: collective.name },
			)
			commit(UPDATE_CIRCLE, response.data.ocs.data)

			if (collective.id === getters.currentCollective.id) {
				// Update page list, properties like `collectivePath` might have changed
				await dispatch(GET_PAGES)
			}
			commit(PATCH_COLLECTIVE_WITH_CIRCLE, response.data.ocs.data)
		},
	},
}
