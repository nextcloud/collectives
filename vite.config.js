/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

export default createAppConfig(
	{
		init: resolve(join('src', 'init.js')),
		main: resolve(join('src', 'main.js')),
		reference: resolve(join('src', 'reference.js')),
	},
	{
		config: {
			build: {
				rollupOptions: {
					output: {
						manualChunks: {
							vendor: ['vue', 'vue-router'],
						},
					},
				},
			},
			css: {
				modules: {
					localsConvention: 'camelCase',
				},
			},
		},
		createEmptyCSSEntryPoints: true,
	},
)
