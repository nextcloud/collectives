import Vue from 'vue'
import FileListInfo from '../views/FileListInfo.vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const FilesCollectivesPlugin = {
	el: null,

	async getCollectivesFolder() {
		const response = await axios.get(generateOcsUrl('apps/collectives/api/v1.0/settings/user/user_folder'))
		return response.data.ocs.data
	},

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

	async render(fileList) {
		const collectivesFolder = await this.getCollectivesFolder()
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
