import {
	GET_COLLECTIVES_FOLDER,
	UPDATE_COLLECTIVES_FOLDER,
} from './actions.js'
import { SET_COLLECTIVES_FOLDER } from './mutations.js'
import * as settings from '../apis/collectives/settings.js'

export default {
	state: {
		collectivesFolder: '',
	},

	mutations: {
		[SET_COLLECTIVES_FOLDER](state, collectivesFolder) {
			state.collectivesFolder = collectivesFolder
		},
	},

	actions: {
		/**
		 * Get collectives folder setting for user
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 */
		async [GET_COLLECTIVES_FOLDER]({ commit }) {
			const response = await settings.getCollectivesFolder()
			commit(SET_COLLECTIVES_FOLDER, response.data.ocs.data)
		},

		/**
		 * Update collectives folder setting for user
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {string} collectivesFolder path to collectives folder
		 */
		async [UPDATE_COLLECTIVES_FOLDER]({ commit }, collectivesFolder) {
			const response = await settings.setCollectivesFolder(collectivesFolder)
			commit(SET_COLLECTIVES_FOLDER, response.data.ocs.data)
		},
	},
}
