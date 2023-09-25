import FileListInfo from '../views/FileListInfo.vue'
import Vue from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { Header } from '@nextcloud/files'

let vm = null

const FilesCollectiveHeader = new Header({
	id: 'collective',
	order: 9,

	enabled(folder, view) {
		return view.id === 'files' || view.id === 'files.public'
	},

	render(el, folder, view) {
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

	updated(folder, view) {
		vm.path = folder.path
	},
})

export {
	FilesCollectiveHeader,
}
