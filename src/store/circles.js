export const state = {
	circles: [],
}

export const mutations = {
	circles(state, circles) {
		state.circles = circles
	},
	deleteCircleFor(state, collective) {
		state.circles.splice(state.circles.findIndex(c => c.unique_id === collective.circleUniqueId), 1)
	},
}

export const actions = {
	/**
	 * Get list of all circles
	 */
	async getCircles({ commit }) {
		const api = OCA.Circles.api
		api.listCircles('all', '', 9, response => {
			commit('circles', response.data)
		})
	},
}
