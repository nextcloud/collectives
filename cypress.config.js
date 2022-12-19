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
	numTestsKeptInMemory: 5,
})
