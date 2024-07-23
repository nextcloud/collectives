import { emit } from '@nextcloud/event-bus'

export default {
	state: {
		query: '',
	},

	mutations: {
		setSearchQuery(state, query) {
			state.query = query
			emit('text:editor:search', { query })
		},
	}
}