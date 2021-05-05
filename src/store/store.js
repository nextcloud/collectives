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

import circles from './circles'
import collectives from './collectives'
import pages from './pages'

Vue.use(Vuex)

export default new Vuex.Store({

	modules: {
		circles,
		collectives,
		pages,
	},

	state: {
		messages: {},
		showing: {},
		loading: {},
		version: null,
	},

	getters: {

		collectiveParam(state, rootState) {
			return state.route.params.collective
		},

		messages(state) {
			return state.messages
		},

		pageParam(state) {
			return state.route.params.page
		},

		landingPage(_state, getters) {
			return !getters.pageParam || getters.pageParam === 'Readme'
		},

		title(_state, getters) {
			return getters.landingPage
				? getters.currentCollective.name
				: getters.currentPage.title
		},

		loading: (state) => (aspect) => state.loading[aspect],
		showing: (state) => (aspect) => state.showing[aspect],
		version: (state) => state.version,

		updatedCollectivePath(state, getters) {
			const collective = state.collectives.updatedCollective
			return collective && `/${collective.name}`
		},

		collectiveChanged(state, getters) {
			const updated = state.collectives.updatedCollective
				&& state.collectives.updatedCollective.name
			const current = getters.currentCollective
				&& getters.currentCollective.name
			return updated && (updated !== current)
		},
	},

	mutations: {
		info(state, message) {
			if (message) {
				Vue.set(state.messages, 'info', message)
			}
		},
		load(state, aspect) {
			Vue.set(state.loading, aspect, true)
		},
		done(state, aspect) {
			Vue.set(state.loading, aspect, false)
		},
		show(state, aspect) {
			Vue.set(state.showing, aspect, true)
		},
		hide(state, aspect) {
			Vue.set(state.showing, aspect, false)
		},
		toggle(state, aspect) {
			Vue.set(state.showing, aspect, !state.showing[aspect])
		},
		version(state, version) {
			state.version = version
		},
	},

})
