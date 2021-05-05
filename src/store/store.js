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
import circles from './circles'
import collectives from './collectives'

Vue.use(Vuex)

export default new Vuex.Store({

	modules: {
		circles,
		collectives,
	},

	state: {
		pages: [],
		updatedPage: {},
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

		currentPage(state, getters) {
			const title = getters.pageParam || 'Readme'
			return state.pages.find(
				(page) => page.title === title
			)
		},

		loading: (state) => (aspect) => state.loading[aspect],
		showing: (state) => (aspect) => state.showing[aspect],
		version: (state) => state.version,

		mostRecentPages(_state, getters) {
			return getters.visiblePages.sort((a, b) => b.timestamp - a.timestamp)
		},

		collectivePage(state) {
			return state.pages.find((p) => p.title === 'Readme')
		},

		visiblePages(state) {
			return state.pages.filter((p) => p.title !== 'Readme')
		},

		pagesUrl(_state, getters) {
			return generateUrl(`/apps/collectives/_collectives/${getters.currentCollective.id}/_pages`)
		},

		pageUrl(_state, getters) {
			return (pageId) => `${getters.pagesUrl}/${pageId}`
		},

		touchUrl(_state, getters) {
			return `${getters.pageUrl(getters.currentPage.id)}/touch`
		},

		updatedPagePath(state, getters) {
			const collective = getters.collectiveParam
			const { title, id } = state.updatedPage
			return `/${collective}/${title}?fileId=${id}`
		},

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
		pages(state, pages) {
			state.pages = pages
		},
		updatePage(state, page) {
			state.pages.splice(
				state.pages.findIndex(p => p.id === page.id),
				1,
				page
			)
			state.updatedPage = page
		},
		addPage(state, page) {
			state.pages.unshift(page)
			state.updatedPage = page
		},
		deletePage(state, id) {
			state.pages.splice(state.pages.findIndex(p => p.id === id), 1)
		},
		version(state, version) {
			state.version = version
		},
	},

	actions: {

		/**
		 * Get list of all pages
		 */
		async getPages({ commit, getters }) {
			commit('load', 'collective')
			const response = await axios.get(getters.pagesUrl)
			commit('pages', response.data.data)
			commit('done', 'collective')
		},

		/**
		 * Get a single page and update it in the store
		 * @param {number} pageId Page ID
		 */
		async getPage({ commit, getters, state }, pageId) {
			commit('load', 'page')
			const response = await axios.get(getters.pageUrl(pageId))
			commit('updatePage', response.data.data)
			commit('done', 'page')
		},

		/**
		 * Create a new page
		 * @param {Object} page Properties for the new page (title for now)
		 */
		async newPage({ commit, getters }, page) {
			commit('load')
			commit('load', 'page')
			const response = await axios.post(getters.pagesUrl, page)
			// Add new page to the beginning of pages array
			commit('addPage', { newTitle: '', ...response.data.data })
			commit('done', 'page')
		},

		async touchPage({ commit, getters }) {
			const response = await axios.get(getters.touchUrl)
			commit('updatePage', response.data.data)
		},

		/**
		 * Rename the current page
		 * @param {string} newTitle new title for the page
		 */
		async renamePage({ commit, getters, state }, newTitle) {
			commit('load', 'page')
			const page = getters.currentPage
			page.title = newTitle
			delete page.newTitle
			const response = await axios.put(getters.pageUrl(page.id), page)
			commit('updatePage', response.data.data)
			commit('done', 'page')
		},

		/**
		 * Delete the current page
		 */
		async deletePage({ commit, getters, state }) {
			commit('load', 'page')
			await axios.delete(getters.pageUrl(getters.currentPage.id))
			commit('deletePage', getters.currentPage.id)
			commit('done', 'page')
		},
	},

})
