/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'cypress'
import cypressSplit from 'cypress-split'
import vitePreprocessor from 'cypress-vite'

const SEMANTIC_E2E = process.env.CYPRESS_semanticE2E === '1'

export default defineConfig({
	viewportWidth: 1280,
	viewportHeight: 900,
	e2e: {
		setupNodeEvents(on, config) {
			on('file:preprocessor', vitePreprocessor({ configFile: false, plugins: [vue()] }))
			cypressSplit(on, config)
			return config
		},

		baseUrl: 'http://localhost:8081/index.php/',
		specPattern: 'cypress/e2e/**/*.{js,jsx,ts,tsx}',
	},
	expose: {
		semanticE2E: SEMANTIC_E2E,
	},
	defaultCommandTimeout: 7000,
	retries: {
		runMode: 2,
		// do not retry in `cypress open`
		openMode: 0,
	},
	numTestsKeptInMemory: 0,
	allowCypressEnv: false,
	experimentalMemoryManagement: true,
	experimentalFastVisibility: true,
})
