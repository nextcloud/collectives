/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { createApp, reactive } from 'vue'
import FileListInfo from '../views/FileListInfo.vue'

const props = reactive({
	collectivesFolder: null,
	path: null,
})

/**
 * @type {import('@nextcloud/files').IFileListHeader}
 */
const FilesCollectiveHeader = {
	id: 'collective',
	order: 9,

	enabled(folder, view) {
		return view.id === 'files' || view.id === 'files.public'
	},

	render(el, folder) {
		el.id = 'files-collective-wrapper'
		props.collectivesFolder = loadState('collectives', 'user_folder', null)
		props.path = folder.path
		createApp(FileListInfo, props).mount(el)
	},

	updated(folder) {
		props.path = folder.path
	},
}

export {
	FilesCollectiveHeader,
}
