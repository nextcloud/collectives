import Vue from 'vue'
import FileListInfo from '../views/FileListInfo.vue'
import { loadState } from '@nextcloud/initial-state'

// For Nextcloud <= 27
const FilesCollectivesPlugin = {
	el: null,

	attach(fileList) {
		if (fileList.id !== 'files' && fileList.id !== 'files.public') {
			return
		}

		this.el = document.createElement('div')
		this.el.id = 'files-collectives-info-wrapper'
		fileList.registerHeader({
			id: 'collectives',
			el: this.el,
			render: this.render.bind(this),
			// Higher order than Text rich workspaces
			order: -1,
		})
	},

	render(fileList) {
		const collectivesFolder = loadState('collectives', 'user_folder', null)
		const View = Vue.extend(FileListInfo)
		Vue.prototype.t = window.t
		Vue.prototype.n = window.n
		const vm = new View({
			propsData: {
				collectivesFolder,
				path: fileList.getCurrentDirectory(),
			},
		}).$mount(this.el)

		fileList.$el.on('urlChanged', data => {
			vm.path = data.dir.toString()
		})

		fileList.$el.on('changeDirectory', data => {
			vm.path = data.dir.toString()
		})

		return this.el
	},
}

export {
	FilesCollectivesPlugin,
}
