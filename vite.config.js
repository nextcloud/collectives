/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { readdirSync, rmSync, existsSync } from 'node:fs'
import { join, resolve } from 'path'
import { VitePWA } from 'vite-plugin-pwa'

export default createAppConfig(
	{
		'settings-admin': resolve(join('src', 'settings-admin.ts')),
		init: resolve(join('src', 'init.js')),
		main: resolve(join('src', 'main.js')),
		reference: resolve(join('src', 'reference.js')),
	},
	{
		config: {
			build: {
				rollupOptions: {
					// Needed for Nextcloud >= 32. In 33 it got fixed
					// with https://github.com/nextcloud/server/pull/56941
					preserveEntrySignatures: 'strict',
				},
			},
			css: {
				modules: {
					localsConvention: 'camelCase',
				},
			},
			plugins: [
				// Remove stale hashed CSS build artifacts before each build so they don't accumulate.
				// Only removes *.chunk.css and collectives-*.css (entry CSS), not collectives.css (static).
				{
					name: 'clean-css-artifacts',
					buildStart() {
						const cssDir = resolve('css')
						if (!existsSync(cssDir)) return
						for (const file of readdirSync(cssDir)) {
							if (file.endsWith('.chunk.css') || (file.startsWith('collectives-') && file.endsWith('.css'))) {
								rmSync(join(cssDir, file))
							}
						}
					},
				},
				VitePWA({
					// Don't generate a manifest file. It's provided by the theming app already
					manifest: false,
					// Don't try to inject service worker registration. We do it manually at Collectives.vue
					injectRegister: false,
					outDir: 'js',
					// Enable service worker in development build
					devOptions: { enabled: true },
					strategies: 'injectManifest',
					srcDir: 'src',
					filename: 'service-worker.js',
					injectManifest: {
						// Adjust paths in precaching manifest URLs
						manifestTransforms: [
							async (manifest) => {
								manifest.map((entry) => {
									if (entry.url.startsWith('../css/')) {
										entry.url = entry.url.replace('../css/', 'css/')
									} else {
										entry.url = 'js/' + entry.url
									}
									return entry
								})
								return { manifest, warnings: [] }
							},
						],
						globPatterns: [
							'../css/*.css',
							'*.mjs',
						],
						maximumFileSizeToCacheInBytes: 5242880,
					},
				})
			],
		},
		createEmptyCSSEntryPoints: true,
	},
)
