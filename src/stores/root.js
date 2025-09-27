/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useLocalStorage } from '@vueuse/core'
import { defineStore } from 'pinia'
import { set } from 'vue'
import { editorApiReaderFileId, editorApiUpdateReadonlyBarProps } from '../constants.js'

const STORE_PREFIX = 'collectives/pinia/root/'

export const useRootStore = defineStore('root', {
	state: () => ({
		showings: useLocalStorage(STORE_PREFIX + 'showings', {}),
		loadings: {},
		printView: false,
		activeSidebarTab: useLocalStorage(STORE_PREFIX + 'activeSidebarTab', 'attachments'),
		collectiveParam: '',
		collectiveId: null,
		pageParam: '',
		pageId: null,
		shareTokenParam: '',
		fileIdQuery: '',
		listenPush: false,
	}),

	getters: {
		loading: (state) => (aspect) => state.loadings[aspect],
		showing: (state) => (aspect) => state.showings[aspect],

		isPublic() { return !!this.shareTokenParam },

		editorApiVersionCheck: () => (requiredVersion) => {
			const apiVersion = window.OCA?.Text?.apiVersion || '0'
			return apiVersion.localeCompare(requiredVersion, undefined, { numeric: true, sensitivity: 'base' }) >= 0
		},

		editorApiFlags() {
			const flags = []
			if (this.editorApiVersionCheck('1.1')) {
				flags.push(editorApiReaderFileId)
			}
			if (this.editorApiVersionCheck('1.2')) {
				flags.push(editorApiUpdateReadonlyBarProps)
			}
			return flags
		},
	},

	actions: {
		load(aspect) { set(this.loadings, aspect, true) },
		done(aspect) { set(this.loadings, aspect, false) },

		show(aspect) { set(this.showings, aspect, true) },
		hide(aspect) { set(this.showings, aspect, false) },
		toggle(aspect) { set(this.showings, aspect, !this.showings[aspect]) },

		setPrintView() { this.printView = true },
		setActiveSidebarTab(id) { this.activeSidebarTab = id },
	},
})
