import { SELECT_VERSION, SET_VERSIONS } from './mutations'

export default {
	state: {
		version: null,
		versions: [],
	},

	getters: {
		version: (state) => state.version,
	},

	mutations: {
		[SELECT_VERSION](state, version) {
			state.version = version
		},

		[SET_VERSIONS](state, versions) {
			state.versions = versions
		},
	},
}
