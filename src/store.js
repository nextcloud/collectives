/**
 * @copyright Copyright (c) 2020 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import Vuex from 'vuex'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

Vue.use(Vuex)

export default new Vuex.Store({

	state: {
		loading: true,
		wikis: [],
	},

	mutations: {
		loading(state) {
			state.loading = true
		},
		done(state) {
			state.loading = false
		},
		wikis(state, wikis) {
			state.wikis = wikis
		},
	},

	actions: {
		/**
		 * Get list of all pages
		 */
		async getWikis({ commit }) {
			commit('loading')
			const response = await axios.get(generateUrl(`/apps/wiki/_wikis`))

			commit('wikis', response.data)
			commit('done')
		},
	},

})
