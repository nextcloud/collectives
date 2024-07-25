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
		setSearchQuery(state, query) {
			state.query = query
			emit('text:editor:search', { query: state.query, matchAll: state.matchAll })
		},
		toggleMatchAll(state) {
			state.matchAll = !state.matchAll
			emit('text:editor:search', { query: state.query, matchAll: state.matchAll })
		},
		nextSearch(state) {
			state.matchAll = false
			emit('text:editor:search-next', {})
		},
		previousSearch(state) {
			state.matchAll = false
			emit('text:editor:search-previous', {})
		},
	},
}
