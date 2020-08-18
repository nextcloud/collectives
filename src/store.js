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
		pages: [],
		wikis: [],
		updatedPage: {},
		updatedWiki: {},
	},

	getters: {

		pageParam(state) {
			return state.route.params.page
		},

		currentPage(state, getters) {
			return state.pages.find(
				(page) => page.title === getters.pageParam
			)
		},

		wikiParam(state) {
			return state.route.params.wiki
		},

		currentWiki(state, getters) {
			return state.wikis.find(
				(wiki) => wiki.name === getters.wikiParam
			)
		},

		pagesUrl(_state, getters) {
			return generateUrl(`/apps/wiki/_wikis/${getters.currentWiki.id}/_pages`)
		},

		pageUrl(_state, getters) {
			return (pageId) => `${getters.pagesUrl}/${pageId}`
		},

		updatedPagePath(state, getters) {
			const wiki = getters.wikiParam
			const { title, id } = state.updatedPage
			return `/${wiki}/${title}?fileId=${id}`
		},

		updatedWikiPath(state, getters) {
			const wiki = state.updatedWiki
			return `/${wiki.name}`
		},

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
		addWiki(state, wiki) {
			state.wikis.unshift(wiki)
			state.updatedWiki = wiki
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
	},

	actions: {

		/**
		 * Get list of all pages
		 */
		async getPages({ commit, getters }) {
			commit('loading')
			const response = await axios.get(getters.pagesUrl)
			this.commit('pages',
				// sort pages by timestamp
				response.data.sort((a, b) => b.timestamp - a.timestamp)
			)
			commit('done')
		},

		/**
		 * Get a single page and update it in the store
		 * @param {number} pageId Page ID
		 */
		async getPage({ commit, getters, state }, pageId) {
			commit('loading')
			const response = await axios.get(getters.pageUrl(pageId))
			commit('updatePage', response.data)
			commit('done')
		},

		/**
		 * Create a new page
		 */
		async newPage({ commit, getters }) {
			commit('loading')
			const page = {
				title: 'New Page',
			}
			const response = await axios.post(getters.pagesUrl, page)
			// Add new page to the beginning of pages array
			commit('addPage', { newTitle: '', ...response.data })
			commit('done')
		},

		/**
		 * Rename the current page
		 * @param {string} newTitle new title for the page
		 */
		async renamePage({ commit, getters, state }, newTitle) {
			commit('loading')
			const page = getters.currentPage
			page.title = newTitle
			delete page.newTitle
			const response = await axios.put(getters.pageUrl(page.id), page)
			commit('updatePage', response.data)
			commit('done')
		},

		/**
		 * Delete the current page
		 */
		async deletePage({ commit, getters, state }) {
			commit('loading')
			await axios.delete(getters.pageUrl(getters.currentPage.id))
			commit('deletePage', getters.currentPage.id)
			commit('done')
		},

		/**
		 * Get list of all wikis
		 */
		async getWikis({ commit }) {
			commit('loading')
			const response = await axios.get(generateUrl(`/apps/wiki/_wikis`))

			commit('wikis', response.data)
			commit('done')
		},

		/**
		 * Create a new wiki with the given properties
		 * @param {Object} wiki Properties for the new wiki (name for now)
		 */
		async newWiki({ commit }, wiki) {
			commit('loading')
			const response = await axios.post(generateUrl(`/apps/wiki/_wikis`), wiki)
			commit('addWiki', response.data)
			commit('done')
		},

	},

})
