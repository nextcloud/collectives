/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { Header } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import Vue from 'vue'
import FileListInfo from '../views/FileListInfo.vue'

let vm = null

const FilesCollectiveHeader = new Header({
	id: 'collective',
	order: 9,

	enabled(folder, view) {
		return view.id === 'files' || view.id === 'files.public'
	},

	render(el, folder) {
		el.id = 'files-collective-wrapper'
		Vue.prototype.t = window.t
		Vue.prototype.n = window.n
		const collectivesFolder = loadState('collectives', 'user_folder', null)
		const View = Vue.extend(FileListInfo)
		vm = new View({
			propsData: {
				collectivesFolder,
				path: folder.path,
			},
		}).$mount(el)
	},

	updated(folder) {
		vm.path = folder.path
	},
})

export {
	FilesCollectiveHeader,
}
