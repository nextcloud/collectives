import { generateUrl, imagePath } from '@nextcloud/router'

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('collectives', 'js/') // eslint-disable-line

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Theming) {
		OC.MimeType._mimeTypeIcons['dir-collective'] = generateUrl('/apps/theming/img/collectives/folder-collective.svg?v=' + OCA.Theming.cacheBuster)
	} else {
		OC.MimeType._mimeTypeIcons['dir-collective'] = imagePath('collectives', 'folder-collective')
	}
})
