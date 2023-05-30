const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpack = require('webpack')

const isDevServer = process.env.WEBPACK_SERVE
if (isDevServer) {
	webpackConfig.output.publicPath = 'http://127.0.0.1:3000/'
	webpackConfig.plugins.push(
		new webpack.DefinePlugin({
			'process.env.WEBPACK_SERVE': true,
		})
	)
}

webpackConfig.entry['files'] = path.join(__dirname, 'src', 'files.js')
webpackConfig.entry['reference'] = path.join(__dirname, 'src', 'reference.js')

module.exports = webpackConfig
