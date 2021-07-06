import Vue from 'vue'
import Vuex from 'vuex'

import circles from './circles'
import collectives from './collectives'
import pages from './pages'
import versions from './versions'

Vue.use(Vuex)

export default new Vuex.Store({

	modules: {
		circles,
		collectives,
		pages,
		versions,
	},

	state: {
		messages: {},
		showing: {},
		loading: {},
	},

	getters: {
		loading: (state) => (aspect) => state.loading[aspect],
		showing: (state) => (aspect) => state.showing[aspect],

		collectiveParam: (state) => state.route.params.collective,
		pageParam: (state) => state.route.params.page,

		indexPage: (_state, get) =>
			get.currentPage.fileName === 'Readme.md',

		landingPage: (_state, get) =>
			!get.pageParam || get.pageParam === 'Readme',

		title: (_state, get) =>
			get.landingPage ? get.currentCollective.name : get.currentPage.title,
	},

	mutations: {
		info: (state, message) => Vue.set(state.messages, 'info', message),

		load: (state, aspect) => Vue.set(state.loading, aspect, true),
		done: (state, aspect) => Vue.set(state.loading, aspect, false),

		show: (state, aspect) => Vue.set(state.showing, aspect, true),
		hide: (state, aspect) => Vue.set(state.showing, aspect, false),
		toggle: (state, aspect) =>
			Vue.set(state.showing, aspect, !state.showing[aspect]),

	},
})
