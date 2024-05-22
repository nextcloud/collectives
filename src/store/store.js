import Vue, { set } from 'vue'
import Vuex, { Store } from 'vuex'

import circles from './circles.js'
import collectives from './collectives.js'
import pages from './pages.js'
import sessions from './sessions.js'
import settings from './settings.js'
import versions from './versions.js'
import { editorApiReaderFileId, pageModes } from '../constants.js'

Vue.use(Vuex)

export default new Store({

	modules: {
		circles,
		collectives,
		pages,
		sessions,
		settings,
		versions,
	},

	state: {
		textMode: pageModes.MODE_VIEW,
		messages: {},
		showing: {},
		loading: {},
		printView: false,
		activeSidebarTab: 'attachments',
	},

	getters: {
		loading: (state) => (aspect) => state.loading[aspect],
		showing: (state) => (aspect) => state.showing[aspect],

		collectiveParam: (state) => state.route.params.collective,
		pageParam: (state) => state.route.params.page,
		shareTokenParam: (state) => state.route.params.token,

		isIndexPage: (_state, getters) =>
			getters.currentPage.fileName === 'Readme.md',

		isLandingPage: (_state, getters) =>
			getters.currentCollectiveIsPageShare
				? false
				: !getters.pageParam || getters.pageParam === 'Readme',

		isTemplatePage: (_state, getters) =>
			getters.currentPage.title === 'Template',

		title: (_state, getters) =>
			getters.isLandingPage ? getters.currentCollective.name : getters.currentPage.title,

		isPublic: (_state, getters) =>
			!!getters.shareTokenParam,

		isTextEdit: (state) => state.textMode === pageModes.MODE_EDIT,
		isTextView: (state) => state.textMode === pageModes.MODE_VIEW,

		editorApiVersionCheck: () => (requiredVersion) => {
			const apiVersion = window.OCA?.Text?.apiVersion || '0'
			return apiVersion.localeCompare(requiredVersion, undefined, { numeric: true, sensitivity: 'base' }) >= 0
		},

		editorApiFlags(_state, getters) {
			const flags = []
			if (getters.editorApiVersionCheck('1.1')) {
				flags.push(editorApiReaderFileId)
			}
			return flags
		},

		activeSidebarTab: (state) => state.activeSidebarTab,
	},

	mutations: {
		info: (state, message) => set(state.messages, 'info', message),

		load: (state, aspect) => set(state.loading, aspect, true),
		done: (state, aspect) => set(state.loading, aspect, false),

		show: (state, aspect) => set(state.showing, aspect, true),
		hide: (state, aspect) => set(state.showing, aspect, false),
		toggle: (state, aspect) =>
			set(state.showing, aspect, !state.showing[aspect]),
		setPrintView: (state) => { state.printView = true },
		setTextEdit: (state) => { state.textMode = pageModes.MODE_EDIT },
		setTextView: (state) => { state.textMode = pageModes.MODE_VIEW },
		setActiveSidebarTab: (state, id) => { state.activeSidebarTab = id },
	},
})
