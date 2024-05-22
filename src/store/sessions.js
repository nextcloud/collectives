import * as api from '../apis/collectives/index.js'

import {
	SET_SESSION,
	CLEAR_SESSION,
} from './mutations.js'

import {
	CREATE_SESSION,
	UPDATE_SESSION,
	CLOSE_SESSION,
} from './actions.js'

export default {
	state: {
		session: {},
	},

	getters: {
		hasSession(state) {
			return Object.keys(state.session).length
		},
	},

	mutations: {
		[SET_SESSION](state, { collectiveId, token }) {
			state.session = { collectiveId, token }
		},
		[CLEAR_SESSION](state) {
			state.session = {}
		},
	},

	actions: {
		async [CREATE_SESSION]({ commit, getters }) {
			const response = await api.createSession(getters.currentCollective.id)
			commit(SET_SESSION, { collectiveId: getters.currentCollective.id, token: response.data.ocs.data.token })
		},

		async [UPDATE_SESSION]({ state, dispatch }) {
			try {
				await api.updateSession(state.session.collectiveId, state.session.token)
			} catch (e) {
				console.error('Session update failed, creating a new one')
				await dispatch(CREATE_SESSION)
			}
		},

		async [CLOSE_SESSION]({ commit, state }) {
			await api.closeSession(state.session.collectiveId, state.session.token)
			commit(CLEAR_SESSION)
		},
	},
}
