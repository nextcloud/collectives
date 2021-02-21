// ***********************************************************
// This example plugins/index.js can be used to load plugins
//
// You can change the location of this file or turn off loading
// the plugins file with the 'pluginsFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/plugins-guide
// ***********************************************************

// This function is called when a project is opened or re-opened (e.g. due to
// the project's config changing)

const {
	addMatchImageSnapshotPlugin
} = require('cypress-image-snapshot/plugin')
const browserify = require('@cypress/browserify-preprocessor')

module.exports = (on) => {
}

module.exports = (on, config) => {

	on('file:preprocessor', browserify())

	addMatchImageSnapshotPlugin(on, config)

	https://github.com/cypress-io/cypress/issues/350#issuecomment-503231128
	on('before:browser:launch', (browser = {}, launchOptions) => {
		if (browser.name === 'chrome') {
			launchOptions.args.push('--disable-dev-shm-usage')
			return launchOptions
		}
	return launchOptions
	})
}
