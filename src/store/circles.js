import { GET_CIRCLES } from './actions'
import { DELETE_CIRCLE_FOR, SET_CIRCLES } from './mutations'

export default {
	state: {
		circles: [],
	},

	getters: {
		availableCircles(state, _getters, rootState) {
			return state.circles.filter(circle => {
				const matchCircleId = c => {
					return (c.circleId === circle.unique_id)
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
			state.circles.splice(state.circles.findIndex(c => c.unique_id === collective.circleId), 1)
		},
	},

	actions: {
		/**
		 * Get list of all circles
		 */
		async [GET_CIRCLES]({ commit }) {
			const api = OCA.Circles.api
			api.listCircles('all', '', 9, response => {
				commit(SET_CIRCLES, response.data)
			})
		},
	},
}
