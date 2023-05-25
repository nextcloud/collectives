// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken)

if (!process.env.WEBPACK_SERVE) {
	// eslint-disable-next-line
	__webpack_public_path__ = OC.linkTo('collectives', 'js/')
} else {
	// eslint-disable-next-line
	__webpack_public_path__ = 'http://127.0.0.1:3000/'
}
