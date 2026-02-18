/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'
import { defineConfig } from 'eslint/config'
import pluginCypress from 'eslint-plugin-cypress'

export default defineConfig([
	...recommended,

	{
		name: 'cypress',
		extends: [
			pluginCypress.configs.recommended,
		],
	},

	{
		name: 'playwright',
		files: ['playwright/start-nextcloud-server.js'],
		languageOptions: {
			globals: {
				process: 'readonly',
			},
		},
	},

	{
		name: 'collectives/disable',
		rules: {
			'no-console': 'off', // we make extensive use of console
		},
	},
])
