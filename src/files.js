import { generateUrl, imagePath } from '@nextcloud/router'
import { FilesCollectivesPlugin } from './helpers/files.js'
import './shared-init.js'

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Theming) {
		OC.MimeType._mimeTypeIcons['dir-collective'] = generateUrl('/apps/theming/img/collectives/folder-collective.svg?v=' + OCA.Theming.cacheBuster)
	} else {
		OC.MimeType._mimeTypeIcons['dir-collective'] = imagePath('collectives', 'folder-collective')
	}
})

OC.Plugins.register('OCA.Files.FileList', FilesCollectivesPlugin)
