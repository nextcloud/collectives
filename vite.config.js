/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { VitePWA } from 'vite-plugin-pwa'
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
			plugins: [
				VitePWA({
					// Don't generate a manifest file. It's provided by the theming app already
					manifest: false,
					// Don't try to inject service worker registration. We do it manually at Collectives.vue
					injectRegister: false,
					outDir: './',
					// Enable service worker in development build
					devOptions: { enabled: true },
					strategies: 'injectManifest',
					srcDir: 'src',
					filename: 'service-worker.js',
					injectManifest: {
						globPatterns: [
							 "css/*.css",
							 "js/*.mjs",
						],
						maximumFileSizeToCacheInBytes: 5242880,
					},
				})
			],
		},
		createEmptyCSSEntryPoints: true,
	},
)
