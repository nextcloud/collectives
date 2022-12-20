const { defineConfig } = require('cypress')

module.exports = defineConfig({
	projectId: 'b6myzg',
	viewportWidth: 1280,
	viewportHeight: 900,
	e2e: {
		setupNodeEvents(on, config) {
			const browserify = require('@cypress/browserify-preprocessor')
			on('file:preprocessor', browserify())
		},

		baseUrl: 'http://localhost:8081/index.php/',
		experimentalSessionAndOrigin: true,
		specPattern: 'cypress/e2e/**/*.{js,jsx,ts,tsx}',
	},
	retries: {
		runMode: 2,
		// do not retry in `cypress open`
		openMode: 0,
	},
	numTestsKeptInMemory: 5,
})
