const { defineConfig } = require('cypress')

module.exports = defineConfig({
	projectId: 'b6myzg',
	viewportWidth: 1280,
	viewportHeight: 900,
	e2e: {
		setupNodeEvents(on, config) {
			const browserify = require('@cypress/browserify-preprocessor')
			const webpack = require('@cypress/webpack-preprocessor')

			on('file:preprocessor', browserify())
			on('file:preprocessor', webpack())
		},

		baseUrl: 'http://localhost:8081/index.php/',
		specPattern: 'cypress/e2e/**/*.{js,jsx,ts,tsx}',
	},
	retries: {
		runMode: 2,
		// do not retry in `cypress open`
		openMode: 0,
	},
	numTestsKeptInMemory: 5,
})
