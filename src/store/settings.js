import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import {
	GET_COLLECTIVES_FOLDER,
	UPDATE_COLLECTIVES_FOLDER,
} from './actions.js'
import { SET_COLLECTIVES_FOLDER } from './mutations.js'

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
			const response = await axios.get(generateOcsUrl('apps/collectives/api/v1.0/settings/user/user_folder'))
			if (response.data.ocs) {
				commit(SET_COLLECTIVES_FOLDER, response.data.ocs.data)
			} else {
				throw response.data
			}
		},

		/**
		 * Update collectives folder setting for user
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {string} collectivesFolder path to collectives folder
		 */
		async [UPDATE_COLLECTIVES_FOLDER]({ commit }, collectivesFolder) {
			const response = await axios.post(generateOcsUrl('apps/collectives/api/v1.0/settings/user'), {
				key: 'user_folder',
				value: collectivesFolder,
			})
			commit(SET_COLLECTIVES_FOLDER, response.data.ocs.data)
		},
	},
}
