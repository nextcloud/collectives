import Vue, { set } from 'vue'
import Vuex, { Store } from 'vuex'

import circles from './circles.js'
import collectives from './collectives.js'
import pages from './pages.js'
import settings from './settings.js'
import versions from './versions.js'
import { editorApiReaderFileId, pageModes } from '../constants.js'

Vue.use(Vuex)

export default new Store({

	modules: {
		circles,
		collectives,
		pages,
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

		isIndexPage: (_state, get) =>
			get.currentPage.fileName === 'Readme.md',

		isLandingPage: (_state, get) =>
			get.currentCollectiveIsPageShare
				? false
				: !get.pageParam || get.pageParam === 'Readme',

		isTemplatePage: (_state, get) =>
			get.currentPage.title === 'Template',

		title: (_state, get) =>
			get.isLandingPage ? get.currentCollective.name : get.currentPage.title,

		isPublic: (_state, get) =>
			!!get.shareTokenParam,

		isTextEdit: (state) => state.textMode === pageModes.MODE_EDIT,
		isTextView: (state) => state.textMode === pageModes.MODE_VIEW,

		editorApiVersionCheck: () => (requiredVersion) => {
			const apiVersion = window.OCA?.Text?.apiVersion || '0'
			return apiVersion.localeCompare(requiredVersion, undefined, { numeric: true, sensitivity: 'base' }) >= 0
		},

		useEditorApi(_, get) {
			return !!window.OCA?.Text?.createEditor && get.editorApiVersionCheck('1.0')
		},

		editorApiFlags(_, get) {
			const flags = []
			if (get.editorApiVersionCheck('1.1')) {
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
