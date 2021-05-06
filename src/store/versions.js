import { SELECT_VERSION } from './mutations'

export default {
	state: {
		version: null,
	},

	getters: {
		version: (state) => state.version,
	},

	mutations: {
		[SELECT_VERSION](state, version) {
			state.version = version
		},

	},
}
