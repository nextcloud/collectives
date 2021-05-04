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
		circles: [],
		collectives: [],
		trashCollectives: [],
		updatedCollective: {},
		pages: [],
		updatedPage: {},
		messages: {},
		showing: {},
		loading: {},
		version: null,
	},

	getters: {

		messages(state) {
			return state.messages
		},

		collectives(state) {
			return state.collectives
		},

		trashCollectives(state) {
			return state.trashCollectives
		},

		circles(state) {
			return state.circles
		},

		availableCircles(state) {
			return state.circles.filter(circle => {
			    const matchUniqueId = c => {
					return (c.circleUniqueId === circle.unique_id)
				}
				const alive = state.collectives.find(matchUniqueId)
				const trashed = state.trashCollectives.find(matchUniqueId)
				return !alive && !trashed
			})
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
			const collective = state.updatedCollective
			return collective && `/${collective.name}`
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
		collectives(state, collectives) {
			state.collectives = collectives
		},
		circles(state, circles) {
			state.circles = circles
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
		deleteCircleFor(state, collective) {
			state.circles.splice(state.circles.findIndex(c => c.unique_id === collective.circleUniqueId), 1)
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

		/**
		 * Get list of all collectives
		 */
		async getCollectives({ commit }) {
			commit('load', 'collectives')
			const response = await axios.get(generateUrl('/apps/collectives/_collectives'))
			commit('collectives', response.data.data)
			commit('done', 'collectives')
		},

		/**
		 * Get list of all circles
		 */
		async getCircles({ commit }) {
			const api = OCA.Circles.api
			api.listCircles('all', '', 9, response => {
				commit('circles', response.data)
			})
		},

		/**
		 * Get list of all collectives in trash
		 */
		async getTrashCollectives({ commit }) {
			commit('load', 'collectiveTrash')
			const response = await axios.get(generateUrl('/apps/collectives/_collectives/trash'))
			commit('trashCollectives', response.data.data)
			commit('done', 'collectiveTrash')
		},

		/**
		 * Create a new collective with the given properties
		 * @param {Object} collective Properties for the new collective (name for now)
		 */
		async newCollective({ commit }, collective) {
			const response = await axios.post(
				generateUrl('/apps/collectives/_collectives'),
				collective,
			)
			commit('info', response.data.message)
			commit('addOrUpdateCollective', response.data.data)
		},

		/**
		 * Trash a collective with the given id
		 * @param {Number} id ID of the colletive to be trashed
		 */
		async trashCollective({ commit }, { id }) {
			const response = await axios.delete(generateUrl('/apps/collectives/_collectives/' + id))
			commit('trashCollective', response.data.data)
		},

		/**
		 * Restore a collective with the given id from trash
		 * @param {Number} id ID of the colletive to be trashed
		 */
		async restoreCollective({ commit }, { id }) {
			const response = await axios.patch(generateUrl('/apps/collectives/_collectives/trash/' + id))
			commit('restoreCollective', response.data.data)
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
			commit('deleteCollective', response.data.data)
			if (circle) {
				commit('deleteCircleFor', response.data.data)
			}
		},
	},

})
