/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'
import { editorApiReaderFileId, pageModes } from '../constants.js'

export const useRootStore = defineStore('root', {
	state: () => ({
		textMode: pageModes.MODE_VIEW,
		messages: {},
		showings: {},
		loadings: {},
		printView: false,
		activeSidebarTab: 'attachments',
		collectiveParam: '',
		pageParam: '',
		shareTokenParam: '',
		fileIdQuery: '',
	}),

	getters: {
		loading: (state) => (aspect) => state.loadings[aspect],
		showing: (state) => (aspect) => state.showings[aspect],

		isPublic() { return !!this.shareTokenParam },

		isTextEdit: (state) => state.textMode === pageModes.MODE_EDIT,
		isTextView: (state) => state.textMode === pageModes.MODE_VIEW,

		editorApiVersionCheck: () => (requiredVersion) => {
			const apiVersion = window.OCA?.Text?.apiVersion || '0'
			return apiVersion.localeCompare(requiredVersion, undefined, { numeric: true, sensitivity: 'base' }) >= 0
		},

		editorApiFlags() {
			const flags = []
			if (this.editorApiVersionCheck('1.1')) {
				flags.push(editorApiReaderFileId)
			}
			return flags
		},
	},

	actions: {
		// TODO: restructure
		info(message) { this.messages.info = message },

		load(aspect) { set(this.loadings, aspect, true) },
		done(aspect) { set(this.loadings, aspect, false) },

		show(aspect) { set(this.showings, aspect, true) },
		hide(aspect) { set(this.showings, aspect, false) },
		toggle(aspect) { set(this.showings, aspect, !this.showings[aspect]) },

		setPrintView() { this.printView = true },
		setTextEdit() { this.textMode = pageModes.MODE_EDIT },
		setTextView() { this.textMode = pageModes.MODE_VIEW },
		setActiveSidebarTab(id) { this.activeSidebarTab = id },
	},
})
