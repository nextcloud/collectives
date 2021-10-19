import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { GET_CIRCLES } from './actions'
import { DELETE_CIRCLE_FOR, SET_CIRCLES } from './mutations'

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
	},

	actions: {
		/**
		 * Get list of all circles
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 */
		async [GET_CIRCLES]({ commit }) {
			const response = await axios.get(generateOcsUrl('apps/circles/circles'))
			commit(SET_CIRCLES, response.data.ocs.data)
		},
	},
}
