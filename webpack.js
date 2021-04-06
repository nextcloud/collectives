const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry['files'] = path.join(__dirname, 'src', 'files.js')

module.exports = webpackConfig
