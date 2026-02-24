/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { IFileListHeader, IFolder, IView } from '@nextcloud/files'
import type { App, ComponentPublicInstance } from 'vue'

import { createApp } from 'vue'
import FileListInfo from '../views/FileListInfo.vue'

let app: App | undefined
let instance: ComponentPublicInstance<typeof FileListInfo> | undefined

export const FilesCollectiveHeader: IFileListHeader = {
	id: 'collective',
	order: 9,

	enabled(folder: IFolder, view: IView) {
		return view.id === 'files' || view.id === 'files.public'
	},

	render(el: HTMLElement, folder: IFolder) {
		el.id = 'files-collective-wrapper'
		app?.unmount()
		app = createApp(FileListInfo, { path: folder.path })
		instance = app.mount(el) as ComponentPublicInstance<typeof FileListInfo>
	},

	updated(folder: IFolder) {
		instance!.setPath(folder.path)
	},
}
