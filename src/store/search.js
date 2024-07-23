import { emit } from '@nextcloud/event-bus'

export default {
	state: {
		query: '',
	},

	getters: {
		searchQuery(state) {
			return state.query
		},
	},

	mutations: {
		setSearchQuery(state, query) {
			state.query = query
			emit('text:editor:search', { query })
		},
	},
}
