import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

if (!process.env.WEBPACK_SERVE) {
	// eslint-disable-next-line
	__webpack_public_path__ = generateFilePath('collectives', '', 'js/')
} else {
	// eslint-disable-next-line
	__webpack_public_path__ = 'http://127.0.0.1:3000/'
}
