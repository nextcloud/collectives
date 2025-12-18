/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig } from 'cypress'
import cypressSplit from 'cypress-split'

export default defineConfig({
	viewportWidth: 1280,
	viewportHeight: 900,
	e2e: {
		setupNodeEvents(on, config) {
			cypressSplit(on, config)
			return config
		},

		baseUrl: 'http://localhost:8081/index.php/',
		specPattern: 'cypress/e2e/**/*.{js,jsx,ts,tsx}',
	},
	defaultCommandTimeout: 7000,
	retries: {
		runMode: 2,
		// do not retry in `cypress open`
		openMode: 0,
	},
	numTestsKeptInMemory: 5,
	experimentalMemoryManagement: true,
	// TODO: remove once we upgrade to newer Cypress
	userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Cypress/13.6.4 Chrome/144.0.0.0 Electron/25.8.4 Safari/537.36',
	experimentalFastVisibility: true,
})
