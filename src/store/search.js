import { emit } from '@nextcloud/event-bus'

export default {
	state: {
		query: '',
		matchAll: true,
	},

	getters: {
		searchQuery(state) {
			return state.query
		},
		matchAll(state) {
			return state.matchAll
		},
	},

	mutations: {
		setSearchQuery(state, { query, matchAll }) {
			state.query = query
			emit('text:editor:search', { query, matchAll })
		},
		nextSearch(state) {
			state.matchAll = false
			emit('text:editor:search-next', {})
		},
		previousSearch(state) {
			state.matchAll = false
			emit('text:editor:search-previous', {})
		},
		toggleMatchAll(state) {
			state.matchAll = !state.matchAll
		},
	},

	actions: {
		async toggleMatchAll({ state, commit }) {
			commit('toggleMatchAll')
			commit('setSearchQuery', {
				query: state.query,
				matchAll: state.matchAll,
			})
		},
	},
}
