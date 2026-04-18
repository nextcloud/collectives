/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'
import pluginCypress from 'eslint-plugin-cypress'
import { defineConfig } from 'eslint/config'

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

	{
		name: 'collectives/openapi-ts',
		files: ['src/client/**/*.ts'],
		rules: {
			'no-console': 'error',
			'perfectionist/sort-named-imports': 'off',
			'jsdoc/require-param-description': 'off',
			'@typescript-eslint/ban-ts-comment': 'off',
			'@stylistic/max-statements-per-line': ['error', { max: 2 }],
			'@typescript-eslint/no-explicit-any': 'off',
			// https://github.com/nextcloud-libraries/eslint-config/pull/1379
			'@stylistic/no-mixed-spaces-and-tabs': 'off',
			'perfectionist/sort-named-exports': 'off',
		},
	},
])
