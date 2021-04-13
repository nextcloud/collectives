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

import decorate from './decorators'

Vue.use(Vuex)

export default new Vuex.Store({

	state: {
		messages: {},
		loading: {},
		collectives: [],
		trashCollectives: [],
		pages: [],
		updatedPage: {},
		updatedTrashCollective: {},
	},

	getters: {

		messages(state) {
			return state.messages
		},

		collectives(state) {
			return state.collectives.map(decorate.collective)
		},

		trashCollectives(state) {
			return state.trashCollectives.map(decorate.collective)
		},

		pageParam(state) {
			return state.route.params.page
		},

		currentPage(state, getters) {
			const title = getters.pageParam || 'Readme'
			return state.pages.find(
				(page) => page.title === title
			)
		},

		collectiveParam(state) {
			return state.route.params.collective
		},

		currentCollective(_state, getters) {
			return getters.collectives.find(
				(collective) => collective.name === getters.collectiveParam
			)
		},

		mostRecentPages(_state, getters) {
			return getters.visiblePages.sort((a, b) => b.timestamp - a.timestamp)
		},

		visiblePages(state) {
			if (state.loading.collective) {
				return []
			}
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
			const collective = state.updatedCollective
			return `/${collective.name}`
		},

		collectiveChanged(state, getters) {
			const updated = state.updatedCollective
				&& state.updatedCollective.name
			const current = getters.currentCollective
				&& getters.currentCollective.name
			return updated && (updated !== current)
		},
	},

	mutations: {
		info(state, message) {
			Vue.set(state.messages, 'info', message)
		},
		loading(state, aspect) {
			Vue.set(state.loading, aspect, true)
		},
		done(state, aspect) {
			Vue.set(state.loading, aspect, false)
		},
		collectives(state, collectives) {
			state.collectives = collectives
		},
		trashCollectives(state, trashCollectives) {
			state.trashCollectives = trashCollectives
		},
		addOrUpdateCollective(state, collective) {
			const cur = state.collectives.findIndex(c => c.id === collective.id)
			if (cur === -1) {
				state.collectives.unshift(collective)
			} else {
				state.collectives.splice(cur, 1, collective)
			}
			state.updatedCollective = collective
		},
		trashCollective(state, collective) {
			state.collectives.splice(state.collectives.findIndex(c => c.id === collective.id), 1)
			state.trashCollectives.unshift(collective)
		},
		restoreCollective(state, collective) {
			state.trashCollectives.splice(state.trashCollectives.findIndex(c => c.id === collective.id), 1)
			state.collectives.unshift(collective)
		},
		deleteCollective(state, collective) {
			state.trashCollectives.splice(state.trashCollectives.findIndex(c => c.id === collective.id), 1)
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
			commit('loading', 'collective')
			const response = await axios.get(getters.pagesUrl)
			commit('pages', response.data)
			commit('done', 'collective')
		},

		/**
		 * Get a single page and update it in the store
		 * @param {number} pageId Page ID
		 */
		async getPage({ commit, getters, state }, pageId) {
			commit('loading', 'page')
			const response = await axios.get(getters.pageUrl(pageId))
			commit('updatePage', response.data)
			commit('done', 'page')
		},

		/**
		 * Create a new page
		 * @param {Object} page Properties for the new page (title for now)
		 */
		async newPage({ commit, getters }, page) {
			commit('loading')
			commit('loading', 'page')
			const response = await axios.post(getters.pagesUrl, page)
			// Add new page to the beginning of pages array
			commit('addPage', { newTitle: '', ...response.data })
			commit('done', 'page')
		},

		async touchPage({ commit, getters }) {
			const response = await axios.get(getters.touchUrl)
			commit('updatePage', response.data)
		},

		/**
		 * Rename the current page
		 * @param {string} newTitle new title for the page
		 */
		async renamePage({ commit, getters, state }, newTitle) {
			commit('loading', 'page')
			const page = getters.currentPage
			page.title = newTitle
			delete page.newTitle
			const response = await axios.put(getters.pageUrl(page.id), page)
			commit('updatePage', response.data)
			commit('done', 'page')
		},

		/**
		 * Delete the current page
		 */
		async deletePage({ commit, getters, state }) {
			commit('loading', 'page')
			await axios.delete(getters.pageUrl(getters.currentPage.id))
			commit('deletePage', getters.currentPage.id)
			commit('done', 'page')
		},

		/**
		 * Get list of all collectives
		 */
		async getCollectives({ commit }) {
			commit('loading', 'collective')
			const response = await axios.get(generateUrl('/apps/collectives/_collectives'))
			commit('collectives', response.data)
			commit('done', 'collective')
		},

		/**
		 * Get list of all collectives in trash
		 */
		async getTrashCollectives({ commit }) {
			commit('loading', 'collectiveTrash')
			const response = await axios.get(generateUrl('/apps/collectives/_collectives/trash'))
			commit('trashCollectives', response.data)
			commit('done', 'collectiveTrash')
		},

		/**
		 * Create a new collective with the given properties
		 * @param {Object} collective Properties for the new collective (name for now)
		 */
		async newCollective({ commit }, collective) {
			commit('loading', 'collective')
			try {
				const response = await axios.post(
					generateUrl('/apps/collectives/_collectives'),
					collective,
					// Http status 409 indicates the collective already existed.
					{ validateStatus: s => (s < 300 || s === 409) },
				)
				if (response.status === 409) {
					commit('info', 'Collective already existed.')
				}
				commit('addOrUpdateCollective', response.data)
			} finally {
				commit('done', 'collective')
			}
		},

		/**
		 * Trash a collective with the given id
		 * @param {Number} id ID of the colletive to be trashed
		 */
		async trashCollective({ commit }, { id }) {
			const response = await axios.delete(generateUrl('/apps/collectives/_collectives/' + id))
			commit('trashCollective', response.data)
		},

		/**
		 * Restore a collective with the given id from trash
		 * @param {Number} id ID of the colletive to be trashed
		 */
		async restoreCollective({ commit }, { id }) {
			const response = await axios.patch(generateUrl('/apps/collectives/_collectives/trash/' + id))
			commit('restoreCollective', response.data)
		},

		/**
		 * Delete a collective with the given id from trash
		 * @param {Number} id ID of the colletive to be trashed
		 * @param {boolean} circle Whether to delete the circle as well
		 */
		async deleteCollective({ commit }, { id, circle }) {
			let doCircle = ''
			if (circle) {
				doCircle = '?circle=1'
			}
			const response = await axios.delete(generateUrl('/apps/collectives/_collectives/trash/' + id + doCircle))
			commit('deleteCollective', response.data)
		},
	},

})
